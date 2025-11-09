<?php
/**
 * Notifications admin screen.
 *
 * @package BB_GroomFlow
 */

namespace BBGF\Admin;

use BBGF\Plugin;
use wpdb;

/**
 * Admin management for notification templates.
 */
class Notifications_Admin implements Admin_Page_Interface {
	/**
	 * Menu slug.
	 */
	public const PAGE_SLUG = 'bbgf-notifications';

	/**
	 * Plugin instance.
	 *
	 * @var Plugin
	 */
	private Plugin $plugin;

	/**
	 * Constructor.
	 *
	 * @param Plugin $plugin Plugin instance.
	 */
	public function __construct( Plugin $plugin ) {
		$this->plugin = $plugin;
	}

	/**
	 * Register hooks.
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_init', array( $this, 'maybe_handle_form_submission' ) );
		add_action( 'admin_init', array( $this, 'maybe_handle_delete' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Register submenu page.
	 */
	public function register_menu(): void {
		add_submenu_page(
			'bbgf-dashboard',
			__( 'Notifications', 'bb-groomflow' ),
			__( 'Notifications', 'bb-groomflow' ),
			'bbgf_manage_notifications', // phpcs:ignore WordPress.WP.Capabilities.Unknown -- custom capability defined by plugin.
			self::PAGE_SLUG,
			array( $this, 'render_page' )
		);
	}

	/**
	 * Handle template create/update.
	 */
	public function maybe_handle_form_submission(): void {
		if ( ! isset( $_POST['bbgf_notification_nonce'] ) ) {
			return;
		}

		$nonce = sanitize_text_field( wp_unslash( $_POST['bbgf_notification_nonce'] ) );
		if ( ! wp_verify_nonce( $nonce, 'bbgf_save_notification' ) ) {
			return;
		}

		if ( ! current_user_can( 'bbgf_manage_notifications' ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown
			return;
		}

		$tables = $this->plugin->get_table_names();
		$wpdb   = $this->plugin->get_wpdb();
		$now    = $this->plugin->now();

		$notification_id = isset( $_POST['notification_id'] ) ? absint( $_POST['notification_id'] ) : 0;
		$name            = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
		$channel         = isset( $_POST['channel'] ) ? sanitize_key( wp_unslash( $_POST['channel'] ) ) : 'email';
		$subject         = isset( $_POST['subject'] ) ? sanitize_text_field( wp_unslash( $_POST['subject'] ) ) : '';
		$body_html       = isset( $_POST['body_html'] ) ? wp_kses_post( wp_unslash( $_POST['body_html'] ) ) : '';
		$body_text       = isset( $_POST['body_text'] ) ? sanitize_textarea_field( wp_unslash( $_POST['body_text'] ) ) : '';

		if ( '' === $name ) {
			wp_safe_redirect(
				add_query_arg(
					'bbgf_message',
					'notification-empty-name',
					$this->get_page_url()
				)
			);
			exit;
		}

		if ( ! in_array( $channel, array( 'email' ), true ) ) {
			$channel = 'email';
		}

		$data = array(
			'name'       => $name,
			'channel'    => $channel,
			'subject'    => $subject,
			'body_html'  => $body_html,
			'body_text'  => $body_text,
			'updated_at' => $now,
		);

		$formats = array( '%s', '%s', '%s', '%s', '%s', '%s' );
		$message = 'notification-created';

		if ( $notification_id > 0 ) {
			$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
				$tables['notifications'],
				$data,
				array( 'id' => $notification_id ),
				$formats,
				array( '%d' )
			);
			$message = 'notification-updated';
		} else {
			$data['created_at'] = $now;
			$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
				$tables['notifications'],
				$data,
				array_merge( $formats, array( '%s' ) )
			);
		}

		wp_safe_redirect(
			add_query_arg(
				'bbgf_message',
				$message,
				$this->get_page_url()
			)
		);
		exit;
	}

	/**
	 * Handle deleting a notification template.
	 */
	public function maybe_handle_delete(): void {
		if ( ! isset( $_GET['page'], $_GET['action'] ) || self::PAGE_SLUG !== sanitize_key( wp_unslash( $_GET['page'] ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		if ( 'delete' !== $_GET['action'] || ! isset( $_GET['_wpnonce'], $_GET['notification_id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'bbgf_delete_notification' ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		if ( ! current_user_can( 'bbgf_manage_notifications' ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown
			return;
		}

		$notification_id = absint( $_GET['notification_id'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( $notification_id <= 0 ) {
			return;
		}

		$wpdb   = $this->plugin->get_wpdb();
		$tables = $this->plugin->get_table_names();

		$wpdb->delete( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$tables['notifications'],
			array( 'id' => $notification_id ),
			array( '%d' )
		);

		// Remove trigger mappings referencing this template.
		if ( isset( $tables['notification_triggers'] ) ) {
			$wpdb->delete( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
				$tables['notification_triggers'],
				array( 'notification_id' => $notification_id ),
				array( '%d' )
			);
		}

		wp_safe_redirect(
			add_query_arg(
				'bbgf_message',
				'notification-deleted',
				remove_query_arg( array( 'notification_id', 'action', '_wpnonce' ) )
			)
		);
		exit;
	}

	/**
	 * Render admin page.
	 */
	public function render_page(): void {
		if ( ! current_user_can( 'bbgf_manage_notifications' ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown
			wp_die( esc_html__( 'You do not have permission to manage notifications.', 'bb-groomflow' ) );
		}

		$wpdb   = $this->plugin->get_wpdb();
		$tables = $this->plugin->get_table_names();

		$current = null;
		if ( isset( $_GET['notification_id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$notification_id = absint( $_GET['notification_id'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( $notification_id > 0 ) {
				$sql     = sprintf( 'SELECT * FROM %s WHERE id = %%d', $tables['notifications'] );
				$query   = $wpdb->prepare( $sql, $notification_id ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- table name injected via sprintf.
				$current = $wpdb->get_row( $query, ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
			}
		}

		$message = isset( $_GET['bbgf_message'] ) ? sanitize_text_field( wp_unslash( $_GET['bbgf_message'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$list = new Notifications_List_Table( $this->plugin );
		$list->prepare_items();

		include __DIR__ . '/views/notifications-page.php';
	}

	/**
	 * Helper to get admin URL.
	 *
	 * @return string
	 */
	private function get_page_url(): string {
		return $this->plugin->admin_url( self::PAGE_SLUG );
	}

	/**
	 * Enqueue admin assets for the notifications screen.
	 *
	 * @param string $hook Current admin hook.
	 */
	public function enqueue_assets( string $hook ): void {
		if ( false === strpos( $hook, self::PAGE_SLUG ) ) {
			return;
		}

		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_style(
			'bbgf-admin',
			BBGF_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			BBGF_VERSION
		);

		wp_enqueue_script(
			'bbgf-admin',
			BBGF_PLUGIN_URL . 'assets/js/admin.js',
			array( 'jquery', 'wp-i18n', 'wp-color-picker' ),
			BBGF_VERSION,
			true
		);
	}
}
