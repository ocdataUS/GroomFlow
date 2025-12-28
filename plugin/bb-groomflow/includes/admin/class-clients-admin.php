<?php
/**
 * Clients admin management.
 *
 * @package BB_GroomFlow
 */

namespace BBGF\Admin;

use BBGF\Bootstrap\Admin_Menu_Service;
use BBGF\Plugin;
use wpdb;

/**
 * Handles the Clients admin screen.
 */
class Clients_Admin implements Admin_Page_Interface {
	// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared
	/**
	 * Page slug.
	 */
	public const PAGE_SLUG = 'bbgf-clients';

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
			__( 'Clients', 'bb-groomflow' ),
			__( 'Clients', 'bb-groomflow' ),
			'bbgf_edit_visits', // phpcs:ignore WordPress.WP.Capabilities.Unknown
			self::PAGE_SLUG,
			array( $this, 'render_page' ),
			20
		);
	}

	/**
	 * Handle create/update submissions.
	 */
	public function maybe_handle_form_submission(): void {
		if ( ! isset( $_POST['bbgf_client_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['bbgf_client_nonce'] ) ), 'bbgf_save_client' ) ) {
			return;
		}

		if ( ! current_user_can( 'bbgf_edit_visits' ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown
			return;
		}

		$client_id = isset( $_POST['client_id'] ) ? absint( $_POST['client_id'] ) : 0;

		$client = array(
			'name'              => isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '',
			'guardian_id'       => isset( $_POST['guardian_id'] ) ? absint( $_POST['guardian_id'] ) : 0,
			'breed'             => isset( $_POST['breed'] ) ? sanitize_text_field( wp_unslash( $_POST['breed'] ) ) : '',
			'weight'            => isset( $_POST['weight'] ) ? floatval( wp_unslash( $_POST['weight'] ) ) : null,
			'sex'               => isset( $_POST['sex'] ) ? sanitize_text_field( wp_unslash( $_POST['sex'] ) ) : '',
			'dob'               => isset( $_POST['dob'] ) ? sanitize_text_field( wp_unslash( $_POST['dob'] ) ) : null,
			'temperament'       => isset( $_POST['temperament'] ) ? sanitize_text_field( wp_unslash( $_POST['temperament'] ) ) : '',
			'preferred_groomer' => isset( $_POST['preferred_groomer'] ) ? sanitize_text_field( wp_unslash( $_POST['preferred_groomer'] ) ) : '',
			'notes'             => isset( $_POST['notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['notes'] ) ) : '',
		);

		if ( empty( $client['name'] ) ) {
			wp_safe_redirect( add_query_arg( 'bbgf_message', 'client-empty-name', $this->get_page_url() ) );
			exit;
		}

		if ( empty( $client['dob'] ) ) {
			$client['dob'] = null;
		}

		$wpdb    = $this->plugin->get_wpdb();
		$tables  = $this->plugin->get_table_names();
		$now     = $this->plugin->now();
		$message = 'client-created';

		$client['slug'] = $this->plugin->unique_slug( $client['name'], $tables['clients'], 'slug', $client_id );

		$data = array_merge(
			$client,
			array(
				'updated_at' => $now,
			)
		);

		$formats = array( '%s', '%s', '%d', '%s', '%f', '%s', '%s', '%s', '%s', '%s' );

		if ( $client_id > 0 ) {
			$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
				$tables['clients'],
				$data,
				array( 'id' => $client_id ),
				$formats,
				array( '%d' )
			);
			$message = 'client-updated';
		} else {
			$data['created_at'] = $now;
			$wpdb->insert( $tables['clients'], $data, $formats ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		}

		$this->plugin->visit_service()->flush_cache();

		wp_safe_redirect( add_query_arg( 'bbgf_message', $message, $this->get_page_url() ) );
		exit;
	}

	/**
	 * Handle deleting a client.
	 */
	public function maybe_handle_delete(): void {
		if ( ! isset( $_GET['page'], $_GET['action'] ) || self::PAGE_SLUG !== sanitize_text_field( wp_unslash( $_GET['page'] ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		$action = sanitize_text_field( wp_unslash( $_GET['action'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( 'delete' !== $action || ! isset( $_GET['_wpnonce'], $_GET['client_id'] ) ) {
			return;
		}

		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'bbgf_delete_client' ) ) {
			return;
		}

		if ( ! current_user_can( 'bbgf_edit_visits' ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown
			return;
		}

		$client_id = absint( $_GET['client_id'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( $client_id <= 0 ) {
			return;
		}

		$wpdb   = $this->plugin->get_wpdb();
		$tables = $this->plugin->get_table_names();

		$wpdb->delete( $tables['clients'], array( 'id' => $client_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

		$this->plugin->visit_service()->flush_cache();

		wp_safe_redirect( add_query_arg( 'bbgf_message', 'client-deleted', remove_query_arg( array( 'action', 'client_id', '_wpnonce' ) ) ) );
		exit;
	}

	/**
	 * Render admin page.
	 */
	public function render_page(): void {
		if ( ! current_user_can( 'bbgf_edit_visits' ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown
			wp_die( esc_html__( 'You do not have permission to manage clients.', 'bb-groomflow' ) );
		}

		$wpdb           = $this->plugin->get_wpdb();
		$tables         = $this->plugin->get_table_names();
		$current_client = null;

		if ( isset( $_GET['client_id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$client_id = absint( $_GET['client_id'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( $client_id > 0 ) {
				$current_client = $wpdb->get_row(
					$wpdb->prepare(
						"SELECT * FROM {$tables['clients']} WHERE id = %d",
						$client_id
					),
					ARRAY_A
				); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			}
		}

		$guardians = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, first_name, last_name FROM {$tables['guardians']} WHERE 1 = %d ORDER BY last_name ASC",
				1
			)
		); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$message = isset( $_GET['bbgf_message'] ) ? sanitize_text_field( wp_unslash( $_GET['bbgf_message'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

			$list = new Clients_List_Table( $this->plugin );
		$list->prepare_items();

		include __DIR__ . '/views/clients-page.php';
	}

	/**
	 * Helper to get admin URL for this page.
	 */
	private function get_page_url(): string {
		return admin_url( 'admin.php?page=' . self::PAGE_SLUG );
	}

	// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared
}
