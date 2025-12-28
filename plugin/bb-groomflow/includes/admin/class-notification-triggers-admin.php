<?php
/**
 * Notification triggers admin screen.
 *
 * @package BB_GroomFlow
 */

namespace BBGF\Admin;

use BBGF\Bootstrap\Admin_Menu_Service;
use BBGF\Plugin;
use wpdb;

/**
 * Admin page for mapping stages to notification templates.
 */
class Notification_Triggers_Admin implements Admin_Page_Interface {
	/**
	 * Menu slug.
	 */
	public const PAGE_SLUG = 'bbgf-notification-triggers';

	/**
	 * Plugin reference.
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
	 * Register submenu.
	 */
	public function register_menu(): void {
		add_submenu_page(
			Admin_Menu_Service::MENU_SLUG,
			__( 'Notification Triggers', 'bb-groomflow' ),
			__( 'Notification Triggers', 'bb-groomflow' ),
			'bbgf_manage_notifications', // phpcs:ignore WordPress.WP.Capabilities.Unknown
			self::PAGE_SLUG,
			array( $this, 'render_page' ),
			52
		);
	}

	/**
	 * Handle create/update.
	 */
	public function maybe_handle_form_submission(): void {
		if ( ! isset( $_POST['bbgf_notification_trigger_nonce'] ) ) {
			return;
		}

		$nonce = sanitize_text_field( wp_unslash( $_POST['bbgf_notification_trigger_nonce'] ) );
		if ( ! wp_verify_nonce( $nonce, 'bbgf_save_notification_trigger' ) ) {
			return;
		}

		if ( ! current_user_can( 'bbgf_manage_notifications' ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown
			return;
		}

		$tables = $this->plugin->get_table_names();
		$wpdb   = $this->plugin->get_wpdb();
		$now    = $this->plugin->now();

		$trigger_id      = isset( $_POST['trigger_id'] ) ? absint( $_POST['trigger_id'] ) : 0;
		$stage           = isset( $_POST['trigger_stage'] ) ? sanitize_key( wp_unslash( $_POST['trigger_stage'] ) ) : '';
		$notification_id = isset( $_POST['notification_id'] ) ? absint( $_POST['notification_id'] ) : 0;
		$recipient_type  = isset( $_POST['recipient_type'] ) ? sanitize_key( wp_unslash( $_POST['recipient_type'] ) ) : 'guardian_primary';
		$recipient_email = isset( $_POST['recipient_email'] ) ? sanitize_text_field( wp_unslash( $_POST['recipient_email'] ) ) : '';
		$enabled         = isset( $_POST['enabled'] ) ? 1 : 0;

		if ( '' === $stage || $notification_id <= 0 ) {
			wp_safe_redirect(
				add_query_arg(
					'bbgf_message',
					'trigger-missing-fields',
					$this->get_page_url()
				)
			);
			exit;
		}

		$allowed_types   = array_keys( $this->get_recipient_options() );
		$recipient_type  = in_array( $recipient_type, $allowed_types, true ) ? $recipient_type : 'guardian_primary';
		$requires_custom = $this->recipient_type_requires_custom( $recipient_type );

		if ( $requires_custom ) {
			$recipient_email = $this->normalize_recipient_emails( $recipient_email );

			if ( '' === $recipient_email ) {
				wp_safe_redirect(
					add_query_arg(
						'bbgf_message',
						'trigger-missing-recipient',
						$this->get_page_url()
					)
				);
				exit;
			}
		} else {
			$recipient_email = '';
		}

		$data = array(
			'trigger_stage'   => $stage,
			'notification_id' => $notification_id,
			'enabled'         => $enabled ? 1 : 0,
			'recipient_type'  => $recipient_type,
			'recipient_email' => $recipient_email,
			'conditions'      => '{}',
			'updated_at'      => $now,
		);

		$message = 'trigger-created';

		if ( $trigger_id > 0 ) {
			$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
				$tables['notification_triggers'],
				$data,
				array( 'id' => $trigger_id ),
				array( '%s', '%d', '%d', '%s', '%s', '%s' ),
				array( '%d' )
			);
			$message = 'trigger-updated';
		} else {
			$data['created_at'] = $now;
			$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
				$tables['notification_triggers'],
				$data,
				array( '%s', '%d', '%d', '%s', '%s', '%s', '%s' )
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
	 * Handle delete action.
	 */
	public function maybe_handle_delete(): void {
		if ( ! isset( $_GET['page'], $_GET['action'] ) || self::PAGE_SLUG !== sanitize_key( wp_unslash( $_GET['page'] ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		if ( 'delete' !== $_GET['action'] || ! isset( $_GET['_wpnonce'], $_GET['trigger_id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'bbgf_delete_notification_trigger' ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		if ( ! current_user_can( 'bbgf_manage_notifications' ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown
			return;
		}

		$trigger_id = absint( $_GET['trigger_id'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( $trigger_id <= 0 ) {
			return;
		}

		$wpdb   = $this->plugin->get_wpdb();
		$tables = $this->plugin->get_table_names();

		$wpdb->delete( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$tables['notification_triggers'],
			array( 'id' => $trigger_id ),
			array( '%d' )
		);

		wp_safe_redirect(
			add_query_arg(
				'bbgf_message',
				'trigger-deleted',
				remove_query_arg( array( 'trigger_id', 'action', '_wpnonce' ) )
			)
		);
		exit;
	}

	/**
	 * Render admin page.
	 */
	public function render_page(): void {
		if ( ! current_user_can( 'bbgf_manage_notifications' ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown
			wp_die( esc_html__( 'You do not have permission to manage notification triggers.', 'bb-groomflow' ) );
		}

		$wpdb   = $this->plugin->get_wpdb();
		$tables = $this->plugin->get_table_names();

		$current = null;
		if ( isset( $_GET['trigger_id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$trigger_id = absint( $_GET['trigger_id'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( $trigger_id > 0 ) {
				$sql     = sprintf( 'SELECT * FROM %s WHERE id = %%d', $tables['notification_triggers'] );
				$query   = $wpdb->prepare( $sql, $trigger_id ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- table name injected via sprintf.
				$current = $wpdb->get_row( $query, ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
			}
		}

		$stage_sql     = sprintf( 'SELECT stage_key, label FROM %s ORDER BY sort_order ASC, label ASC', $tables['stages'] );
		$stage_options = $wpdb->get_results( $stage_sql, ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared

		$template_sql     = sprintf( 'SELECT id, name FROM %s ORDER BY name ASC', $tables['notifications'] );
		$template_options = $wpdb->get_results( $template_sql, ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared

		$message           = isset( $_GET['bbgf_message'] ) ? sanitize_text_field( wp_unslash( $_GET['bbgf_message'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$recipient_options = $this->get_recipient_options();
		$list              = new Notification_Triggers_List_Table( $this->plugin );
		$list->prepare_items();

		include __DIR__ . '/views/notification-triggers-page.php';
	}

	/**
	 * Helper to fetch admin page URL.
	 *
	 * @return string
	 */
	private function get_page_url(): string {
		return $this->plugin->admin_url( self::PAGE_SLUG );
	}

	/**
	 * Enqueue shared admin assets on the triggers screen.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_assets( string $hook ): void {
		if ( false === strpos( $hook, self::PAGE_SLUG ) ) {
			return;
		}

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

	/**
	 * Provide available recipient options for the trigger form.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	private function get_recipient_options(): array {
		return array(
			'guardian_primary'            => array(
				'label'           => __( 'Guardian primary email', 'bb-groomflow' ),
				'description'     => __( 'Send to the guardian email saved with the visit.', 'bb-groomflow' ),
				'requires_custom' => false,
			),
			'guardian_primary_and_custom' => array(
				'label'           => __( 'Guardian + additional emails', 'bb-groomflow' ),
				'description'     => __( 'Send to the guardian email as well as any additional recipients listed below.', 'bb-groomflow' ),
				'requires_custom' => true,
			),
			'custom_email'                => array(
				'label'           => __( 'Custom recipients only', 'bb-groomflow' ),
				'description'     => __( 'Skip the guardian and send only to the addresses listed below (great for internal alerts).', 'bb-groomflow' ),
				'requires_custom' => true,
			),
		);
	}

	/**
	 * Determine whether the selected recipient strategy requires custom emails.
	 *
	 * @param string $recipient_type Recipient strategy key.
	 * @return bool
	 */
	private function recipient_type_requires_custom( string $recipient_type ): bool {
		$options = $this->get_recipient_options();

		return ! empty( $options[ $recipient_type ]['requires_custom'] );
	}

	/**
	 * Normalize and sanitize custom recipient emails into a comma-separated list.
	 *
	 * @param string $raw_emails Submitted email string.
	 * @return string
	 */
	private function normalize_recipient_emails( string $raw_emails ): string {
		if ( '' === trim( $raw_emails ) ) {
			return '';
		}

		$normalized = str_replace(
			array( "\r\n", "\r", "\n", ';' ),
			array( ',', ',', ',', ',' ),
			$raw_emails
		);

		$parts  = array_filter( array_map( 'trim', explode( ',', $normalized ) ) );
		$emails = array();

		foreach ( $parts as $part ) {
			$validated = sanitize_email( $part );
			if ( '' !== $validated ) {
				$emails[ $validated ] = $validated;
			}
		}

		return implode( ', ', array_values( $emails ) );
	}
}
