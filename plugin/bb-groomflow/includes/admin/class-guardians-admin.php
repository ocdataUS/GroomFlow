<?php
/**
 * Guardians admin management.
 *
 * @package BB_GroomFlow
 */

namespace BBGF\Admin;

use BBGF\Bootstrap\Admin_Menu_Service;
use BBGF\Plugin;
use wpdb;

/**
 * Handles the Guardians admin screen.
 */
class Guardians_Admin implements Admin_Page_Interface {
	/**
	 * Page slug.
	 */
	public const PAGE_SLUG = 'bbgf-guardians';

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
	}

	/**
	 * Register submenu page.
	 */
	public function register_menu(): void {
		add_submenu_page(
			Admin_Menu_Service::MENU_SLUG,
			__( 'Guardians', 'bb-groomflow' ),
			__( 'Guardians', 'bb-groomflow' ),
			'bbgf_edit_visits',
			self::PAGE_SLUG,
			array( $this, 'render_page' ),
			22
		);
	}

	/**
	 * Handle create/update submissions.
	 */
	public function maybe_handle_form_submission(): void {
		if ( ! isset( $_POST['bbgf_guardian_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['bbgf_guardian_nonce'] ) ), 'bbgf_save_guardian' ) ) {
			return;
		}

		if ( ! current_user_can( 'bbgf_edit_visits' ) ) {
			return;
		}

		$guardian_id = isset( $_POST['guardian_id'] ) ? absint( $_POST['guardian_id'] ) : 0;

		$guardian = array(
			'first_name'        => isset( $_POST['first_name'] ) ? sanitize_text_field( wp_unslash( $_POST['first_name'] ) ) : '',
			'last_name'         => isset( $_POST['last_name'] ) ? sanitize_text_field( wp_unslash( $_POST['last_name'] ) ) : '',
			'email'             => isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '',
			'phone_mobile'      => isset( $_POST['phone_mobile'] ) ? sanitize_text_field( wp_unslash( $_POST['phone_mobile'] ) ) : '',
			'phone_alt'         => isset( $_POST['phone_alt'] ) ? sanitize_text_field( wp_unslash( $_POST['phone_alt'] ) ) : '',
			'preferred_contact' => isset( $_POST['preferred_contact'] ) ? sanitize_text_field( wp_unslash( $_POST['preferred_contact'] ) ) : '',
			'address'           => isset( $_POST['address'] ) ? sanitize_textarea_field( wp_unslash( $_POST['address'] ) ) : '',
			'notes'             => isset( $_POST['notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['notes'] ) ) : '',
		);

		if ( empty( $guardian['first_name'] ) || empty( $guardian['last_name'] ) ) {
			wp_safe_redirect( add_query_arg( 'bbgf_message', 'guardian-empty-name', $this->get_page_url() ) );
			exit;
		}

		$wpdb    = $this->plugin->get_wpdb();
		$tables  = $this->plugin->get_table_names();
		$now     = $this->plugin->now();
		$message = 'guardian-created';

		$data = array_merge(
			$guardian,
			array(
				'updated_at' => $now,
			)
		);

		$formats = array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' );

		if ( $guardian_id > 0 ) {
			$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
				$tables['guardians'],
				$data,
				array( 'id' => $guardian_id ),
				$formats,
				array( '%d' )
			);
			$message = 'guardian-updated';
		} else {
			$data['created_at'] = $now;
			$wpdb->insert( $tables['guardians'], $data, $formats ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		}

		$this->plugin->visit_service()->flush_cache();

		wp_safe_redirect( add_query_arg( 'bbgf_message', $message, $this->get_page_url() ) );
		exit;
	}

	/**
	 * Handle deleting a guardian.
	 */
	public function maybe_handle_delete(): void {
		if ( ! isset( $_GET['page'], $_GET['action'] ) || self::PAGE_SLUG !== sanitize_text_field( wp_unslash( $_GET['page'] ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		$action = sanitize_text_field( wp_unslash( $_GET['action'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( 'delete' !== $action || ! isset( $_GET['_wpnonce'], $_GET['guardian_id'] ) ) {
			return;
		}

		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'bbgf_delete_guardian' ) ) {
			return;
		}

		if ( ! current_user_can( 'bbgf_edit_visits' ) ) {
			return;
		}

		$guardian_id = absint( $_GET['guardian_id'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( $guardian_id <= 0 ) {
			return;
		}

		$wpdb   = $this->plugin->get_wpdb();
		$tables = $this->plugin->get_table_names();

		$wpdb->delete( $tables['guardians'], array( 'id' => $guardian_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

		$this->plugin->visit_service()->flush_cache();

		wp_safe_redirect( add_query_arg( 'bbgf_message', 'guardian-deleted', remove_query_arg( array( 'action', 'guardian_id', '_wpnonce' ) ) ) );
		exit;
	}

	/**
	 * Render admin page.
	 */
	public function render_page(): void {
		if ( ! current_user_can( 'bbgf_edit_visits' ) ) {
			wp_die( esc_html__( 'You do not have permission to manage guardians.', 'bb-groomflow' ) );
		}

		$wpdb           = $this->plugin->get_wpdb();
		$tables         = $this->plugin->get_table_names();
		$current_record = null;

		if ( isset( $_GET['guardian_id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$guardian_id = absint( $_GET['guardian_id'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( $guardian_id > 0 ) {
				$current_record = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$tables['guardians']} WHERE id = %d", $guardian_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			}
		}

		$message = isset( $_GET['bbgf_message'] ) ? sanitize_text_field( wp_unslash( $_GET['bbgf_message'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$list = new Guardians_List_Table( $this->plugin );
		$list->prepare_items();

		include __DIR__ . '/views/guardians-page.php';
	}

	/**
	 * Helper to get admin URL.
	 */
	private function get_page_url(): string {
		return admin_url( 'admin.php?page=' . self::PAGE_SLUG );
	}
}
