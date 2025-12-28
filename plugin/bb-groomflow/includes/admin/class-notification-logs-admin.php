<?php
/**
 * Notification logs admin screen.
 *
 * @package BB_GroomFlow
 */

namespace BBGF\Admin;

use BBGF\Bootstrap\Admin_Menu_Service;
use BBGF\Plugin;

/**
 * Read-only view of notification delivery logs.
 */
class Notification_Logs_Admin implements Admin_Page_Interface {
	/**
	 * Menu slug.
	 */
	public const PAGE_SLUG = 'bbgf-notification-logs';

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
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_init', array( $this, 'maybe_handle_resend' ) );
		add_action( 'admin_init', array( $this, 'maybe_export_logs' ) );
	}

	/**
	 * Register submenu entry.
	 *
	 * @return void
	 */
	public function register_menu(): void {
		add_submenu_page(
			Admin_Menu_Service::MENU_SLUG,
			__( 'Notification Activity', 'bb-groomflow' ),
			__( 'Notification Activity', 'bb-groomflow' ),
			'bbgf_manage_notifications', // phpcs:ignore WordPress.WP.Capabilities.Unknown
			self::PAGE_SLUG,
			array( $this, 'render_page' ),
			54
		);
	}

	/**
	 * Render admin page.
	 *
	 * @return void
	 */
	public function render_page(): void {
		if ( ! current_user_can( 'bbgf_manage_notifications' ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown
			wp_die( esc_html__( 'You do not have permission to view notification logs.', 'bb-groomflow' ) );
		}

		$message_code = isset( $_GET['bbgf_message'] ) ? sanitize_key( wp_unslash( $_GET['bbgf_message'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( '' !== $message_code ) {
			$this->render_notice( $message_code );
		}

		$filters = array(
			'stage'           => isset( $_GET['bbgf_filter_stage'] ) ? sanitize_key( wp_unslash( $_GET['bbgf_filter_stage'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			'status'          => isset( $_GET['bbgf_filter_status'] ) ? sanitize_key( wp_unslash( $_GET['bbgf_filter_status'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			'notification_id' => isset( $_GET['bbgf_filter_notification'] ) ? absint( $_GET['bbgf_filter_notification'] ) : 0, // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			'date_start'      => isset( $_GET['bbgf_filter_date_start'] ) ? $this->sanitize_date( sanitize_text_field( wp_unslash( $_GET['bbgf_filter_date_start'] ) ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			'date_end'        => isset( $_GET['bbgf_filter_date_end'] ) ? $this->sanitize_date( sanitize_text_field( wp_unslash( $_GET['bbgf_filter_date_end'] ) ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			'search'          => isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		);

		$list_table = new Notification_Logs_List_Table( $this->plugin, $filters );

		if ( '' !== $filters['search'] ) {
			$_REQUEST['s'] = $filters['search']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}

		$list_table->prepare_items();

		$stage_options        = $list_table->get_stage_options();
		$status_options       = $list_table->get_status_options();
		$notification_options = $list_table->get_notification_options();

		include __DIR__ . '/views/notification-logs-page.php';
	}

	/**
	 * Handle CSV export requests for notification logs.
	 *
	 * @return void
	 */
	public function maybe_export_logs(): void {
		if ( ! isset( $_GET['page'] ) || self::PAGE_SLUG !== sanitize_key( wp_unslash( $_GET['page'] ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		if ( ! isset( $_GET['bbgf_export_logs'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		if ( ! current_user_can( 'bbgf_manage_notifications' ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown
			return;
		}

		$nonce = isset( $_GET['bbgf_export_nonce'] ) ? sanitize_text_field( wp_unslash( $_GET['bbgf_export_nonce'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! wp_verify_nonce( $nonce, 'bbgf_export_notification_logs' ) ) {
			return;
		}

		$filters = array(
			'stage'           => isset( $_GET['bbgf_filter_stage'] ) ? sanitize_key( wp_unslash( $_GET['bbgf_filter_stage'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			'status'          => isset( $_GET['bbgf_filter_status'] ) ? sanitize_key( wp_unslash( $_GET['bbgf_filter_status'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			'notification_id' => isset( $_GET['bbgf_filter_notification'] ) ? absint( $_GET['bbgf_filter_notification'] ) : 0, // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			'date_start'      => isset( $_GET['bbgf_filter_date_start'] ) ? sanitize_text_field( wp_unslash( $_GET['bbgf_filter_date_start'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			'date_end'        => isset( $_GET['bbgf_filter_date_end'] ) ? sanitize_text_field( wp_unslash( $_GET['bbgf_filter_date_end'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			'search'          => isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		);

		$rows = $this->query_logs_for_export( $filters );

		if ( headers_sent() ) {
			$this->redirect_with_message( 'export-failed' );
		}

		$filename = 'bbgf-notification-logs-' . gmdate( 'Ymd-His' ) . '.csv';
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=' . $filename );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		$output = fopen( 'php://output', 'w' );
		if ( false === $output ) {
			$this->redirect_with_message( 'export-failed' );
		}

		$headers = array(
			__( 'Sent At', 'bb-groomflow' ),
			__( 'Stage', 'bb-groomflow' ),
			__( 'Template', 'bb-groomflow' ),
			__( 'Channel', 'bb-groomflow' ),
			__( 'Recipients', 'bb-groomflow' ),
			__( 'Status', 'bb-groomflow' ),
			__( 'Subject', 'bb-groomflow' ),
			__( 'Error', 'bb-groomflow' ),
		);

		fputcsv( $output, $headers );

		foreach ( $rows as $row ) {
			$template_name = $row['notification_name'] ?? '';
			if ( '' === $template_name && ! empty( $row['notification_id'] ) ) {
				/* translators: %d: notification template ID. */
				$template_name = sprintf( __( 'Template #%d', 'bb-groomflow' ), (int) $row['notification_id'] );
			}

			fputcsv(
				$output,
				array(
					$row['sent_at'],
					$row['stage'],
					$template_name,
					$row['channel'],
					$row['recipients'],
					$row['status'],
					$row['subject'],
					$row['error_message'],
				)
			);
		}

		fclose( $output ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		exit;
	}

	/**
	 * Fetch log rows for export, honoring current filters.
	 *
	 * @param array<string,mixed> $filters Filter parameters.
	 * @return array<int,array<string,mixed>>
	 */
	private function query_logs_for_export( array $filters ): array {
		$wpdb   = $this->plugin->get_wpdb();
		$tables = $this->plugin->get_table_names();

		$logs_table          = $tables['notification_logs'];
		$notifications_table = $tables['notifications'];
		$triggers_table      = $tables['notification_triggers'];

		$where_clauses = array();
		$where_params  = array();

		if ( ! empty( $filters['stage'] ) ) {
			$where_clauses[] = 'logs.stage = %s';
			$where_params[]  = sanitize_key( $filters['stage'] );
		}

		if ( ! empty( $filters['status'] ) ) {
			$where_clauses[] = 'logs.status = %s';
			$where_params[]  = sanitize_key( $filters['status'] );
		}

		if ( ! empty( $filters['notification_id'] ) ) {
			$where_clauses[] = 'logs.notification_id = %d';
			$where_params[]  = (int) $filters['notification_id'];
		}

		$date_start = $this->sanitize_date( $filters['date_start'] ?? '' );
		if ( $date_start ) {
			$where_clauses[] = 'logs.sent_at >= %s';
			$where_params[]  = $date_start . ' 00:00:00';
		}

		$date_end = $this->sanitize_date( $filters['date_end'] ?? '' );
		if ( $date_end ) {
			$where_clauses[] = 'logs.sent_at <= %s';
			$where_params[]  = $date_end . ' 23:59:59';
		}

		$search = trim( (string) ( $filters['search'] ?? '' ) );
		if ( '' !== $search ) {
			$like            = '%' . $wpdb->esc_like( $search ) . '%';
			$where_clauses[] = '( logs.recipients LIKE %s OR logs.subject LIKE %s OR logs.error_message LIKE %s )';
			$where_params[]  = $like;
			$where_params[]  = $like;
			$where_params[]  = $like;
		}

		$where_sql = '';
		if ( ! empty( $where_clauses ) ) {
			$where_sql = ' WHERE ' . implode( ' AND ', $where_clauses );
		}

		$sql = "SELECT logs.sent_at, logs.stage, logs.channel, logs.recipients, logs.status, logs.subject, logs.error_message, logs.notification_id, n.name AS notification_name
			FROM {$logs_table} AS logs
			LEFT JOIN {$notifications_table} AS n ON n.id = logs.notification_id
			LEFT JOIN {$triggers_table} AS t ON t.id = logs.trigger_id
			{$where_sql}
			ORDER BY logs.sent_at DESC";

		$prepared = ! empty( $where_params ) ? $wpdb->prepare( $sql, $where_params ) : $sql; // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		$results = $wpdb->get_results( $prepared, ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared

		return $results ? $results : array();
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
	 * Handle resend requests for a specific log entry.
	 *
	 * @return void
	 */
	public function maybe_handle_resend(): void {
		if ( ! isset( $_GET['page'] ) || self::PAGE_SLUG !== sanitize_key( wp_unslash( $_GET['page'] ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		if ( ! isset( $_GET['bbgf_resend_log'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		if ( ! current_user_can( 'bbgf_manage_notifications' ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown
			return;
		}

		$nonce = isset( $_GET['bbgf_resend_nonce'] ) ? sanitize_text_field( wp_unslash( $_GET['bbgf_resend_nonce'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! wp_verify_nonce( $nonce, 'bbgf_resend_notification_log' ) ) {
			$this->redirect_with_message( 'resend-failed' );
		}

		$log_id = isset( $_GET['bbgf_resend_log'] ) ? absint( $_GET['bbgf_resend_log'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( $log_id <= 0 ) {
			$this->redirect_with_message( 'resend-failed' );
		}

		$result = $this->plugin->notifications_service()->resend_notification( $log_id );

		if ( is_wp_error( $result ) ) {
			$this->redirect_with_message( 'resend-failed' );
		}

		$this->redirect_with_message( 'resend-success' );
	}

	/**
	 * Render success/error notices.
	 *
	 * @param string $code Message code.
	 * @return void
	 */
	private function render_notice( string $code ): void {
		$message = '';
		switch ( $code ) {
			case 'resend-success':
				$message = __( 'Notification resend queued successfully.', 'bb-groomflow' );
				break;
			case 'resend-failed':
				$message = __( 'Unable to resend this notification. Please verify the log entry and try again.', 'bb-groomflow' );
				break;
			case 'export-failed':
				$message = __( 'Unable to export notification logs.', 'bb-groomflow' );
				break;
			default:
				return;
		}
		echo '<div class="notice notice-info is-dismissible"><p>' . esc_html( $message ) . '</p></div>';
	}

	/**
	 * Redirect back to logs page with message.
	 *
	 * @param string $code Message key.
	 * @return void
	 */
	private function redirect_with_message( string $code ): void {
		$url = add_query_arg(
			array(
				'page'         => self::PAGE_SLUG,
				'bbgf_message' => $code,
			),
			admin_url( 'admin.php' )
		);

		wp_safe_redirect( $url );
		exit;
	}
}
