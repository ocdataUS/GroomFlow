<?php
/**
 * Manage Visits admin screen.
 *
 * @package BB_GroomFlow
 */

namespace BBGF\Admin;

use BBGF\Bootstrap\Admin_Menu_Service;
use BBGF\Plugin;
/**
 * Handles the Manage Visits admin page.
 */
class Manage_Visits_Admin implements Admin_Page_Interface {
	/**
	 * Menu slug.
	 */
	public const PAGE_SLUG = 'bbgf-manage-visits';

	/**
	 * Admin-post action for export.
	 */
	public const ACTION_EXPORT = 'bbgf_export_visits';

	/**
	 * Admin-post action for uncheckout.
	 */
	public const ACTION_UNCHECKOUT = 'bbgf_uncheckout_visit';

	/**
	 * Export nonce action.
	 */
	public const NONCE_EXPORT = 'bbgf_export_visits';

	/**
	 * Uncheckout nonce action.
	 */
	public const NONCE_UNCHECKOUT = 'bbgf_uncheckout_visit';

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
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_post_' . self::ACTION_EXPORT, array( $this, 'handle_export' ) );
		add_action( 'admin_post_' . self::ACTION_UNCHECKOUT, array( $this, 'handle_uncheckout' ) );
	}

	/**
	 * Register submenu page.
	 */
	public function register_menu(): void {
		add_submenu_page(
			Admin_Menu_Service::MENU_SLUG,
			__( 'Manage Visits', 'bb-groomflow' ),
			__( 'Manage Visits', 'bb-groomflow' ),
			'bbgf_edit_visits',
			self::PAGE_SLUG,
			array( $this, 'render_page' ),
			5
		);
	}

	/**
	 * Enqueue assets for the page.
	 *
	 * @param string $hook Current admin hook.
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
			'bbgf-manage-visits',
			BBGF_PLUGIN_URL . 'assets/js/manage-visits.js',
			array(),
			BBGF_VERSION,
			true
		);
	}

	/**
	 * Render the admin page.
	 */
	public function render_page(): void {
		if ( ! current_user_can( 'bbgf_edit_visits' ) ) {
			wp_die( esc_html__( 'You do not have permission to manage visits.', 'bb-groomflow' ) );
		}

		$message_code = isset( $_GET['bbgf_message'] ) ? sanitize_key( wp_unslash( $_GET['bbgf_message'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( '' !== $message_code ) {
			$this->render_notice( $message_code );
		}

		$filters = $this->get_filters_from_request( $_GET ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$list_table = new Manage_Visits_List_Table( $this->plugin, $filters );

		if ( '' !== $filters['search'] ) {
			$_REQUEST['s'] = $filters['search']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}

		$list_table->prepare_items();

		include __DIR__ . '/views/manage-visits-page.php';
	}

	/**
	 * Handle uncheckout action.
	 */
	public function handle_uncheckout(): void {
		if ( ! current_user_can( 'bbgf_edit_visits' ) ) {
			return;
		}

		$visit_id = isset( $_GET['visit_id'] ) ? absint( $_GET['visit_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( $visit_id <= 0 ) {
			$this->redirect_with_message( 'visit-reopen-failed' );
		}

		$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! wp_verify_nonce( $nonce, self::NONCE_UNCHECKOUT . '_' . $visit_id ) ) {
			$this->redirect_with_message( 'visit-reopen-failed' );
		}

		$result = $this->plugin->visit_service()->uncheckout_visit( $visit_id, get_current_user_id() );

		if ( is_wp_error( $result ) ) {
			$code = $result->get_error_code();
			if ( 'bbgf_visit_not_checked_out' === $code ) {
				$this->redirect_with_message( 'visit-already-active' );
			}

			$this->redirect_with_message( 'visit-reopen-failed' );
		}

		$this->redirect_with_message( 'visit-reopened' );
	}

	/**
	 * Handle export requests.
	 */
	public function handle_export(): void {
		if ( ! current_user_can( 'bbgf_edit_visits' ) ) {
			return;
		}

		$nonce = isset( $_REQUEST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, self::NONCE_EXPORT ) ) {
			return;
		}

		$export_type = isset( $_REQUEST['export_type'] ) ? sanitize_key( wp_unslash( $_REQUEST['export_type'] ) ) : 'table';
		if ( ! in_array( $export_type, array( 'table', 'all' ), true ) ) {
			$export_type = 'table';
		}

		$filters  = $this->get_filters_from_request( $_REQUEST );
		$ordering = Manage_Visits_List_Table::get_ordering_from_request();

		if ( headers_sent() ) {
			$this->redirect_with_message( 'export-failed' );
		}

		$filename = 'bbgf-manage-visits-' . $export_type . '-' . gmdate( 'Ymd-His' ) . '.csv';
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=' . $filename );

		$output = fopen( 'php://output', 'w' );
		if ( false === $output ) {
			$this->redirect_with_message( 'export-failed' );
		}

		if ( 'all' === $export_type ) {
			fputcsv( $output, $this->get_all_fields_export_headers() );
			$this->stream_all_fields_export( $output, $filters, $ordering );
		} else {
			fputcsv( $output, $this->get_table_export_headers() );
			$this->stream_table_export( $output, $filters, $ordering );
		}

		fclose( $output ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		exit;
	}

	/**
	 * Stream table export rows.
	 *
	 * @param resource             $output   Output handle.
	 * @param array<string,mixed>  $filters  Filters.
	 * @param array<string,string> $ordering Ordering.
	 * @return void
	 */
	private function stream_table_export( $output, array $filters, array $ordering ): void {
		$stage_labels = $this->get_stage_labels();
		$limit        = 500;
		$offset       = 0;

		while ( true ) {
			$rows = $this->plugin->visit_service()->get_manage_visits_page( $filters, $ordering, $limit, $offset );
			if ( empty( $rows ) ) {
				break;
			}

			foreach ( $rows as $row ) {
				$service_names = $this->get_service_names( $row );
				$stage_key     = sanitize_key( (string) ( $row['current_stage'] ?? '' ) );
				$stage_label   = '' !== $stage_key ? ( $stage_labels[ $stage_key ] ?? $stage_key ) : '';
				$status_label  = $this->format_status_label( (string) ( $row['status'] ?? '' ) );
				$check_in      = $this->get_display_check_in( $row );

				fputcsv(
					$output,
					array(
						(int) ( $row['id'] ?? 0 ),
						$this->format_datetime( $check_in ),
						(string) ( $row['client_name'] ?? '' ),
						$this->get_guardian_name( $row ),
						implode( ', ', $service_names ),
						$stage_label,
						$status_label,
						$this->format_datetime( (string) ( $row['check_out_at'] ?? '' ) ),
					)
				);
			}

			$offset += $limit;
			if ( count( $rows ) < $limit ) {
				break;
			}
		}
	}

	/**
	 * Stream all fields export rows.
	 *
	 * @param resource             $output   Output handle.
	 * @param array<string,mixed>  $filters  Filters.
	 * @param array<string,string> $ordering Ordering.
	 * @return void
	 */
	private function stream_all_fields_export( $output, array $filters, array $ordering ): void {
		$stage_labels = $this->get_stage_labels();
		$limit        = 500;
		$offset       = 0;

		while ( true ) {
			$rows = $this->plugin->visit_service()->get_manage_visits_page( $filters, $ordering, $limit, $offset );
			if ( empty( $rows ) ) {
				break;
			}

			foreach ( $rows as $row ) {
				$service_names = $this->get_service_names( $row );
				$service_ids   = $this->get_service_ids( $row );
				$photo_ids     = $this->get_photo_ids( $row );
				$photo_urls    = $this->get_photo_urls( $row );
				$stage_key     = sanitize_key( (string) ( $row['current_stage'] ?? '' ) );
				$stage_label   = '' !== $stage_key ? ( $stage_labels[ $stage_key ] ?? $stage_key ) : '';

				fputcsv(
					$output,
					array(
						(int) ( $row['id'] ?? 0 ),
						(int) ( $row['client_id'] ?? 0 ),
						(string) ( $row['client_name'] ?? '' ),
						(int) ( $row['guardian_id'] ?? 0 ),
						(string) ( $row['guardian_first_name'] ?? '' ),
						(string) ( $row['guardian_last_name'] ?? '' ),
						$this->get_guardian_name( $row ),
						(int) ( $row['view_id'] ?? 0 ),
						(string) ( $row['view_name'] ?? '' ),
						(string) ( $row['view_slug'] ?? '' ),
						(string) ( $row['current_stage'] ?? '' ),
						$stage_label,
						(string) ( $row['status'] ?? '' ),
						(string) ( $row['check_in_at'] ?? '' ),
						(string) ( $row['check_out_at'] ?? '' ),
						(int) ( $row['assigned_staff'] ?? 0 ),
						(string) ( $row['instructions'] ?? '' ),
						(string) ( $row['public_notes'] ?? '' ),
						(string) ( $row['private_notes'] ?? '' ),
						(string) ( $row['timer_started_at'] ?? '' ),
						(int) ( $row['timer_elapsed_seconds'] ?? 0 ),
						(string) ( $row['created_at'] ?? '' ),
						(string) ( $row['updated_at'] ?? '' ),
						implode( ', ', $service_ids ),
						implode( ', ', $service_names ),
						implode( ', ', $photo_ids ),
						implode( ', ', $photo_urls ),
					)
				);
			}

			$offset += $limit;
			if ( count( $rows ) < $limit ) {
				break;
			}
		}
	}

	/**
	 * Export headers for table columns.
	 *
	 * @return array<int,string>
	 */
	private function get_table_export_headers(): array {
		return array(
			__( 'Visit ID', 'bb-groomflow' ),
			__( 'Check-in', 'bb-groomflow' ),
			__( 'Client', 'bb-groomflow' ),
			__( 'Guardian', 'bb-groomflow' ),
			__( 'Services', 'bb-groomflow' ),
			__( 'Current Stage', 'bb-groomflow' ),
			__( 'Status', 'bb-groomflow' ),
			__( 'Checked-out At', 'bb-groomflow' ),
		);
	}

	/**
	 * Export headers for all fields.
	 *
	 * @return array<int,string>
	 */
	private function get_all_fields_export_headers(): array {
		return array(
			'id',
			'client_id',
			'client_name',
			'guardian_id',
			'guardian_first_name',
			'guardian_last_name',
			'guardian_name',
			'view_id',
			'view_name',
			'view_slug',
			'current_stage',
			'stage_label',
			'status',
			'check_in_at',
			'check_out_at',
			'assigned_staff',
			'instructions',
			'public_notes',
			'private_notes',
			'timer_started_at',
			'timer_elapsed_seconds',
			'created_at',
			'updated_at',
			'service_ids',
			'service_names',
			'photo_ids',
			'photo_urls',
		);
	}

	/**
	 * Normalize filters from request input.
	 *
	 * @param array<string,mixed> $source Request input.
	 * @return array<string,mixed>
	 */
	private function get_filters_from_request( array $source ): array {
		$search = isset( $source['s'] ) ? sanitize_text_field( wp_unslash( $source['s'] ) ) : '';
		$status = isset( $source['bbgf_filter_status'] ) ? sanitize_key( wp_unslash( $source['bbgf_filter_status'] ) ) : '';
		if ( ! in_array( $status, array( '', 'active', 'completed', 'cancelled' ), true ) ) {
			$status = '';
		}

		return array(
			'search'     => $search,
			'status'     => $status,
			'date_start' => isset( $source['bbgf_filter_date_start'] ) ? $this->sanitize_date( sanitize_text_field( wp_unslash( $source['bbgf_filter_date_start'] ) ) ) : '',
			'date_end'   => isset( $source['bbgf_filter_date_end'] ) ? $this->sanitize_date( sanitize_text_field( wp_unslash( $source['bbgf_filter_date_end'] ) ) ) : '',
			'view_id'    => isset( $source['bbgf_filter_view'] ) ? absint( $source['bbgf_filter_view'] ) : 0,
			'stage'      => isset( $source['bbgf_filter_stage'] ) ? sanitize_key( wp_unslash( $source['bbgf_filter_stage'] ) ) : '',
			'service_id' => isset( $source['bbgf_filter_service'] ) ? absint( $source['bbgf_filter_service'] ) : 0,
		);
	}

	/**
	 * Render admin notices based on message code.
	 *
	 * @param string $code Message code.
	 */
	private function render_notice( string $code ): void {
		$messages = array(
			'visit-reopened'       => array( 'success', __( 'Visit reopened.', 'bb-groomflow' ) ),
			'visit-reopen-failed'  => array( 'error', __( 'Unable to reopen the visit.', 'bb-groomflow' ) ),
			'visit-already-active' => array( 'warning', __( 'This visit is already active.', 'bb-groomflow' ) ),
			'export-failed'        => array( 'error', __( 'Export failed. Please try again.', 'bb-groomflow' ) ),
		);

		if ( ! isset( $messages[ $code ] ) ) {
			return;
		}

		$type    = $messages[ $code ][0];
		$message = $messages[ $code ][1];

		printf(
			'<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
			esc_attr( $type ),
			esc_html( $message )
		);
	}

	/**
	 * Redirect with message code.
	 *
	 * @param string $message Message code.
	 */
	private function redirect_with_message( string $message ): void {
		$redirect = wp_get_referer();
		if ( ! $redirect ) {
			$redirect = $this->get_page_url();
		}

		$redirect = remove_query_arg( array( 'bbgf_message', 'paged' ), $redirect );
		$redirect = add_query_arg( 'bbgf_message', $message, $redirect );

		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * Build the base admin URL for the page.
	 *
	 * @return string
	 */
	private function get_page_url(): string {
		return admin_url( 'admin.php?page=' . self::PAGE_SLUG );
	}

	/**
	 * Normalize date string (Y-m-d) or return null.
	 *
	 * @param string $raw_date Raw date string.
	 * @return string|null
	 */
	private function sanitize_date( string $raw_date ): ?string {
		$raw_date = trim( $raw_date );
		if ( '' === $raw_date ) {
			return null;
		}

		$parsed = date_create_from_format( 'Y-m-d', $raw_date );
		if ( ! $parsed ) {
			return null;
		}

		return $parsed->format( 'Y-m-d' );
	}

	/**
	 * Get stage labels map.
	 *
	 * @return array<string,string>
	 */
	private function get_stage_labels(): array {
		$wpdb   = $this->plugin->get_wpdb();
		$tables = $this->plugin->get_table_names();

		if ( empty( $tables['stages'] ) ) {
			return array();
		}

		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare( // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				'SELECT stage_key, label FROM %i WHERE 1 = %d ORDER BY sort_order ASC, label ASC',
				$tables['stages'],
				1
			),
			ARRAY_A
		);

		$labels = array();
		foreach ( $rows as $row ) {
			$key = sanitize_key( (string) ( $row['stage_key'] ?? '' ) );
			if ( '' === $key ) {
				continue;
			}
			$label          = (string) ( $row['label'] ?? '' );
			$labels[ $key ] = '' !== $label ? $label : ucfirst( $key );
		}

		return $labels;
	}

	/**
	 * Get guardian name for export.
	 *
	 * @param array $row Row data.
	 * @return string
	 */
	private function get_guardian_name( array $row ): string {
		$first = trim( (string) ( $row['guardian_first_name'] ?? '' ) );
		$last  = trim( (string) ( $row['guardian_last_name'] ?? '' ) );

		return trim( $first . ' ' . $last );
	}

	/**
	 * Get service names for export.
	 *
	 * @param array $row Row data.
	 * @return array<int,string>
	 */
	private function get_service_names( array $row ): array {
		$services = isset( $row['services'] ) && is_array( $row['services'] ) ? $row['services'] : array();
		$names    = array();

		foreach ( $services as $service ) {
			$name = trim( (string) ( $service['name'] ?? '' ) );
			if ( '' !== $name ) {
				$names[] = $name;
			}
		}

		return $names;
	}

	/**
	 * Get service IDs for export.
	 *
	 * @param array $row Row data.
	 * @return array<int,string>
	 */
	private function get_service_ids( array $row ): array {
		$services = isset( $row['services'] ) && is_array( $row['services'] ) ? $row['services'] : array();
		$ids      = array();

		foreach ( $services as $service ) {
			$id = isset( $service['id'] ) ? (int) $service['id'] : 0;
			if ( $id > 0 ) {
				$ids[] = (string) $id;
			}
		}

		return $ids;
	}

	/**
	 * Get photo IDs for export.
	 *
	 * @param array $row Row data.
	 * @return array<int,string>
	 */
	private function get_photo_ids( array $row ): array {
		$photos = isset( $row['photos'] ) && is_array( $row['photos'] ) ? $row['photos'] : array();
		$ids    = array();

		foreach ( $photos as $photo ) {
			$id = isset( $photo['id'] ) ? (int) $photo['id'] : 0;
			if ( $id > 0 ) {
				$ids[] = (string) $id;
			}
		}

		return $ids;
	}

	/**
	 * Get photo URLs for export.
	 *
	 * @param array $row Row data.
	 * @return array<int,string>
	 */
	private function get_photo_urls( array $row ): array {
		$photos = isset( $row['photos'] ) && is_array( $row['photos'] ) ? $row['photos'] : array();
		$urls   = array();

		foreach ( $photos as $photo ) {
			$url = trim( (string) ( $photo['url'] ?? '' ) );
			if ( '' !== $url ) {
				$urls[] = $url;
			}
		}

		return $urls;
	}

	/**
	 * Format status label for table export.
	 *
	 * @param string $status Raw status.
	 * @return string
	 */
	private function format_status_label( string $status ): string {
		$status = sanitize_key( $status );
		if ( '' === $status ) {
			return __( 'Active', 'bb-groomflow' );
		}

		return ucwords( str_replace( '_', ' ', $status ) );
	}

	/**
	 * Get display check-in datetime for export.
	 *
	 * @param array $row Row data.
	 * @return string
	 */
	private function get_display_check_in( array $row ): string {
		$check_in = (string) ( $row['check_in_at'] ?? '' );
		if ( '' !== $check_in ) {
			return $check_in;
		}

		$created = (string) ( $row['created_at'] ?? '' );
		if ( '' !== $created ) {
			return $created;
		}

		return (string) ( $row['updated_at'] ?? '' );
	}

	/**
	 * Format datetime for table export.
	 *
	 * @param string $datetime Datetime string.
	 * @return string
	 */
	private function format_datetime( string $datetime ): string {
		if ( '' === $datetime ) {
			return '';
		}

		$timestamp = strtotime( $datetime );
		if ( false === $timestamp ) {
			return $datetime;
		}

		return wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $timestamp );
	}
}
