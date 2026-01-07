<?php
/**
 * Flags admin management.
 *
 * @package BB_GroomFlow
 */

namespace BBGF\Admin;

use BBGF\Bootstrap\Admin_Menu_Service;
use BBGF\Plugin;
use wpdb;
use BBGF\Admin\Flags_List_Table;

/**
 * Handles the Flags admin screen.
 */
class Flags_Admin implements Admin_Page_Interface {
	/**
	 * Menu slug for the page.
	 */
	public const PAGE_SLUG = 'bbgf-flags';

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
			Admin_Menu_Service::MENU_SLUG,
			__( 'Flags', 'bb-groomflow' ),
			__( 'Flags', 'bb-groomflow' ),
			'bbgf_manage_flags',
			self::PAGE_SLUG,
			array( $this, 'render_page' ),
			40
		);
	}

	/**
	 * Handle create/update submissions.
	 */
	public function maybe_handle_form_submission(): void {
		if ( ! isset( $_POST['bbgf_flag_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['bbgf_flag_nonce'] ) ), 'bbgf_save_flag' ) ) {
			return;
		}

		if ( ! current_user_can( 'bbgf_manage_flags' ) ) {
			return;
		}

		$flag_id = isset( $_POST['flag_id'] ) ? absint( $_POST['flag_id'] ) : 0;

		$flag = array(
			'name'        => isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '',
			'emoji'       => isset( $_POST['emoji'] ) ? sanitize_text_field( wp_unslash( $_POST['emoji'] ) ) : '',
			'color'       => isset( $_POST['color'] ) ? sanitize_hex_color( wp_unslash( $_POST['color'] ) ) : '',
			'severity'    => isset( $_POST['severity'] ) ? sanitize_text_field( wp_unslash( $_POST['severity'] ) ) : '',
			'description' => isset( $_POST['description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['description'] ) ) : '',
		);

		if ( empty( $flag['name'] ) ) {
			wp_safe_redirect( add_query_arg( 'bbgf_message', 'flag-empty-name', $this->get_page_url() ) );
			exit;
		}

		$wpdb    = $this->plugin->get_wpdb();
		$tables  = $this->plugin->get_table_names();
		$now     = $this->plugin->now();
		$message = 'flag-created';

		$flag['slug'] = $this->plugin->unique_slug( $flag['name'], $tables['flags'], 'slug', $flag_id );

		$data = array_merge(
			$flag,
			array(
				'updated_at' => $now,
			)
		);

		$formats = array( '%s', '%s', '%s', '%s', '%s', '%s', '%s' );

		if ( $flag_id > 0 ) {
			$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
				$tables['flags'],
				$data,
				array( 'id' => $flag_id ),
				$formats,
				array( '%d' )
			);
			$message = 'flag-updated';
		} else {
			$data['created_at'] = $now;
			$wpdb->insert( $tables['flags'], $data, $formats ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		}

		$this->plugin->visit_service()->flush_cache();

		wp_safe_redirect( add_query_arg( 'bbgf_message', $message, $this->get_page_url() ) );
		exit;
	}

	/**
	 * Handle deleting a flag.
	 */
	public function maybe_handle_delete(): void {
		if ( ! isset( $_GET['page'], $_GET['action'] ) || self::PAGE_SLUG !== sanitize_text_field( wp_unslash( $_GET['page'] ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		$action = sanitize_text_field( wp_unslash( $_GET['action'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( 'delete' !== $action || ! isset( $_GET['_wpnonce'], $_GET['flag_id'] ) ) {
			return;
		}

		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'bbgf_delete_flag' ) ) {
			return;
		}

		if ( ! current_user_can( 'bbgf_manage_flags' ) ) {
			return;
		}

		$flag_id = absint( $_GET['flag_id'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( $flag_id <= 0 ) {
			return;
		}

		$wpdb   = $this->plugin->get_wpdb();
		$tables = $this->plugin->get_table_names();

		$wpdb->delete( $tables['flags'], array( 'id' => $flag_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

		$this->plugin->visit_service()->flush_cache();

		wp_safe_redirect( add_query_arg( 'bbgf_message', 'flag-deleted', remove_query_arg( array( 'action', 'flag_id', '_wpnonce' ) ) ) );
		exit;
	}

	/**
	 * Render admin page.
	 */
	public function render_page(): void {
		if ( ! current_user_can( 'bbgf_manage_flags' ) ) {
			wp_die( esc_html__( 'You do not have permission to manage flags.', 'bb-groomflow' ) );
		}

		$wpdb   = $this->plugin->get_wpdb();
		$tables = $this->plugin->get_table_names();

		$current_flag = null;
		if ( isset( $_GET['flag_id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$flag_id = absint( $_GET['flag_id'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( $flag_id > 0 ) {
				$current_flag = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$tables['flags']} WHERE id = %d", $flag_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			}
		}

		$message = isset( $_GET['bbgf_message'] ) ? sanitize_text_field( wp_unslash( $_GET['bbgf_message'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$list    = new Flags_List_Table( $this->plugin );
		$list->prepare_items();

		include __DIR__ . '/views/flags-page.php';
	}

	/**
	 * Helper to get admin URL.
	 */
	private function get_page_url(): string {
		return admin_url( 'admin.php?page=' . self::PAGE_SLUG );
	}

	/**
	 * Enqueue shared admin assets for the emoji picker.
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
