<?php
/**
 * Notification logs list table.
 *
 * @package BB_GroomFlow
 */

namespace BBGF\Admin;

use BBGF\Plugin;
use WP_List_Table;
use wpdb;

if ( ! class_exists( '\WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Displays notification delivery history.
 */
class Notification_Logs_List_Table extends WP_List_Table {
	/**
	 * Plugin instance.
	 *
	 * @var Plugin
	 */
	private Plugin $plugin;

	/**
	 * Active filters.
	 *
	 * @var array<string,mixed>
	 */
	private array $filters;

	/**
	 * Cached stage options.
	 *
	 * @var array<int,string>
	 */
	private array $stage_options = array();

	/**
	 * Cached notification options.
	 *
	 * @var array<int,array<string,mixed>>
	 */
	private array $notification_options = array();

	/**
	 * Constructor.
	 *
	 * @param Plugin               $plugin  Plugin instance.
	 * @param array<string,string> $filters Filters to apply.
	 */
	public function __construct( Plugin $plugin, array $filters = array() ) {
		parent::__construct(
			array(
				'singular' => 'notification_log',
				'plural'   => 'notification_logs',
				'ajax'     => false,
			)
		);

		$this->plugin  = $plugin;
		$this->filters = wp_parse_args(
			$filters,
			array(
				'stage'           => '',
				'status'          => '',
				'notification_id' => 0,
				'date_start'      => '',
				'date_end'        => '',
				'search'          => '',
			)
		);
	}

	/**
	 * Prepare items.
	 *
	 * @return void
	 */
	public function prepare_items(): void {
		$columns  = $this->get_columns();
		$hidden   = array();
		$sortable = array();

		$this->_column_headers = array( $columns, $hidden, $sortable );

		$per_page     = 20;
		$current_page = max( 1, $this->get_pagenum() );
		$offset       = ( $current_page - 1 ) * $per_page;

		$wpdb   = $this->plugin->get_wpdb();
		$tables = $this->plugin->get_table_names();

		$logs_table          = $tables['notification_logs'];
		$notifications_table = $tables['notifications'];
		$triggers_table      = $tables['notification_triggers'];

		$where_clauses = array();
		$where_params  = array();

		if ( ! empty( $this->filters['stage'] ) ) {
			$where_clauses[] = 'logs.stage = %s';
			$where_params[]  = $this->filters['stage'];
		}

		if ( ! empty( $this->filters['status'] ) ) {
			$where_clauses[] = 'logs.status = %s';
			$where_params[]  = $this->filters['status'];
		}

		if ( ! empty( $this->filters['notification_id'] ) ) {
			$where_clauses[] = 'logs.notification_id = %d';
			$where_params[]  = (int) $this->filters['notification_id'];
		}

		$date_start = $this->sanitize_date( (string) $this->filters['date_start'] );
		if ( $date_start ) {
			$where_clauses[] = 'logs.sent_at >= %s';
			$where_params[]  = $date_start . ' 00:00:00';
		}

		$date_end = $this->sanitize_date( (string) $this->filters['date_end'] );
		if ( $date_end ) {
			$where_clauses[] = 'logs.sent_at <= %s';
			$where_params[]  = $date_end . ' 23:59:59';
		}

		$search = trim( (string) $this->filters['search'] );
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

		$base_query = "FROM {$logs_table} AS logs
			LEFT JOIN {$notifications_table} AS n ON n.id = logs.notification_id
			LEFT JOIN {$triggers_table} AS t ON t.id = logs.trigger_id";

		$count_sql = "SELECT COUNT(*) {$base_query}{$where_sql}";
		if ( ! empty( $where_params ) ) {
			$count_sql = $wpdb->prepare( $count_sql, $where_params ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}
		$total_items = (int) $wpdb->get_var( $count_sql ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared

		$query_sql = "SELECT logs.id, logs.sent_at, logs.stage, logs.recipients, logs.status, logs.subject, logs.channel, logs.error_message, logs.notification_id,
				n.name AS notification_name, logs.trigger_id, t.trigger_stage
			{$base_query}{$where_sql}
			ORDER BY logs.sent_at DESC
			LIMIT %d OFFSET %d";

		$query_params   = $where_params;
		$query_params[] = $per_page;
		$query_params[] = $offset;

		$query = $wpdb->prepare( $query_sql, $query_params ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$items = $wpdb->get_results( $query, ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared

		$this->items = $items ? $items : array();

		$this->set_pagination_args(
			array(
				'total_items' => $total_items,
				'per_page'    => $per_page,
			)
		);

		$this->stage_options        = $this->fetch_stage_options( $wpdb, $logs_table );
		$this->notification_options = $this->fetch_notification_options( $wpdb, $logs_table, $notifications_table );
	}

	/**
	 * Fetch distinct stage values.
	 *
	 * @param wpdb   $wpdb       Database connection.
	 * @param string $logs_table Logs table name.
	 * @return array<int,string>
	 */
	private function fetch_stage_options( wpdb $wpdb, string $logs_table ): array {
		$results = $wpdb->get_col( "SELECT DISTINCT stage FROM {$logs_table} WHERE stage <> '' ORDER BY stage ASC" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( empty( $results ) ) {
			return array();
		}

		return array_map( 'sanitize_key', array_filter( $results ) );
	}

	/**
	 * Fetch notification templates referenced by logs.
	 *
	 * @param wpdb   $wpdb                 Database connection.
	 * @param string $logs_table           Logs table.
	 * @param string $notifications_table  Notifications table.
	 * @return array<int,array<string,mixed>>
	 */
	private function fetch_notification_options( wpdb $wpdb, string $logs_table, string $notifications_table ): array {
		$sql = "SELECT DISTINCT logs.notification_id, COALESCE(n.name, CONCAT('#', logs.notification_id)) AS name
			FROM {$logs_table} AS logs
			LEFT JOIN {$notifications_table} AS n ON n.id = logs.notification_id
			WHERE logs.notification_id IS NOT NULL
			ORDER BY name ASC";

		$results = $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared

		return $results ? $results : array();
	}

	/**
	 * Columns.
	 *
	 * @return array<string,string>
	 */
	public function get_columns(): array {
		return array(
			'sent_at'      => __( 'Sent At', 'bb-groomflow' ),
			'notification' => __( 'Template', 'bb-groomflow' ),
			'stage'        => __( 'Stage', 'bb-groomflow' ),
			'channel'      => __( 'Channel', 'bb-groomflow' ),
			'recipients'   => __( 'Recipients', 'bb-groomflow' ),
			'status'       => __( 'Status', 'bb-groomflow' ),
			'subject'      => __( 'Subject', 'bb-groomflow' ),
			'actions'      => __( 'Actions', 'bb-groomflow' ),
		);
	}

	/**
	 * Default column renderer.
	 *
	 * @param array  $item        Current row.
	 * @param string $column_name Column key.
	 * @return string
	 */
	protected function column_default( $item, $column_name ) {
		switch ( $column_name ) {
			case 'sent_at':
				return esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $item['sent_at'] ?? '' ) );
			case 'notification':
				$name = $item['notification_name'] ?? '';
				if ( '' === $name ) {
					/* translators: %d: notification template ID. */
					$name = sprintf( __( 'Template #%d', 'bb-groomflow' ), (int) $item['notification_id'] );
				}
				return esc_html( $name );
			case 'stage':
				return esc_html( $item['stage'] ?? '' );
			case 'channel':
				return esc_html( ucfirst( $item['channel'] ?? 'email' ) );
			case 'recipients':
				return esc_html( $item['recipients'] ?? '' );
			case 'status':
				return $this->render_status_column( $item );
			case 'subject':
				return esc_html( $item['subject'] ?? '' );
			case 'actions':
				return $this->column_actions( $item );
			default:
				return '';
		}
	}

	/**
	 * Render the status column with failure details when present.
	 *
	 * @param array $item Row data.
	 * @return string
	 */
	private function render_status_column( array $item ): string {
		$status = sanitize_key( $item['status'] ?? 'sent' );
		$error  = trim( (string) ( $item['error_message'] ?? '' ) );

		$label = 'sent' === $status ? __( 'Sent', 'bb-groomflow' ) : __( 'Failed', 'bb-groomflow' );
		$class = 'sent' === $status ? 'bbgf-status--sent' : 'bbgf-status--failed';

		$output = sprintf( '<span class="bbgf-status %1$s">%2$s</span>', esc_attr( $class ), esc_html( $label ) );

		if ( '' !== $error ) {
			$output .= sprintf( '<span class="description">%s</span>', esc_html( $error ) );
		}

		return $output;
	}

	/**
	 * Actions column (resend button).
	 *
	 * @param array $item Log row.
	 * @return string
	 */
	private function column_actions( array $item ): string {
		$log_id = isset( $item['id'] ) ? (int) $item['id'] : 0;
		if ( $log_id <= 0 ) {
			return '';
		}

		$resend_url = $this->get_resend_url( $log_id );

		return sprintf(
			'<div class="bbgf-table-actions"><a class="button button-small" href="%1$s">%2$s</a></div>',
			esc_url( $resend_url ),
			esc_html__( 'Resend', 'bb-groomflow' )
		);
	}

	/**
	 * Display filter controls.
	 *
	 * @param string $which Table position.
	 * @return void
	 */
	protected function extra_tablenav( $which ): void {
		if ( 'top' !== $which ) {
			return;
		}

		$stage           = $this->filters['stage'];
		$status          = $this->filters['status'];
		$notification_id = (int) $this->filters['notification_id'];
		$date_start      = $this->sanitize_date( (string) $this->filters['date_start'] ) ?? '';
		$date_end        = $this->sanitize_date( (string) $this->filters['date_end'] ) ?? '';
		$search          = trim( (string) $this->filters['search'] );

		echo '<div class="alignleft actions">';

		echo '<label class="screen-reader-text" for="bbgf-filter-stage">' . esc_html__( 'Filter by stage', 'bb-groomflow' ) . '</label>';
		echo '<select name="bbgf_filter_stage" id="bbgf-filter-stage">';
		echo '<option value="">' . esc_html__( 'All stages', 'bb-groomflow' ) . '</option>';
		foreach ( $this->stage_options as $option_stage ) {
			printf(
				'<option value="%1$s"%2$s>%3$s</option>',
				esc_attr( $option_stage ),
				selected( $stage, $option_stage, false ),
				esc_html( ucfirst( $option_stage ) )
			);
		}
		echo '</select>';

		echo '<label class="screen-reader-text" for="bbgf-filter-date-start">' . esc_html__( 'Filter by start date', 'bb-groomflow' ) . '</label>';
		echo '<input type="date" id="bbgf-filter-date-start" name="bbgf_filter_date_start" value="' . esc_attr( $date_start ) . '" />';

		echo '<label class="screen-reader-text" for="bbgf-filter-date-end">' . esc_html__( 'Filter by end date', 'bb-groomflow' ) . '</label>';
		echo '<input type="date" id="bbgf-filter-date-end" name="bbgf_filter_date_end" value="' . esc_attr( $date_end ) . '" />';

		echo '<label class="screen-reader-text" for="bbgf-filter-status">' . esc_html__( 'Filter by status', 'bb-groomflow' ) . '</label>';
		echo '<select name="bbgf_filter_status" id="bbgf-filter-status">';
		foreach ( $this->get_status_options() as $value => $label ) {
			printf(
				'<option value="%1$s"%2$s>%3$s</option>',
				esc_attr( $value ),
				selected( $status, $value, false ),
				esc_html( $label )
			);
		}
		echo '</select>';

		echo '<label class="screen-reader-text" for="bbgf-filter-notification">' . esc_html__( 'Filter by template', 'bb-groomflow' ) . '</label>';
		echo '<select name="bbgf_filter_notification" id="bbgf-filter-notification">';
		echo '<option value="0">' . esc_html__( 'All templates', 'bb-groomflow' ) . '</option>';
		foreach ( $this->notification_options as $option ) {
			$id = isset( $option['notification_id'] ) ? (int) $option['notification_id'] : 0;

			if ( empty( $option['name'] ) ) {
				/* translators: %d: notification template ID. */
				$name = sprintf( __( 'Template #%d', 'bb-groomflow' ), $id );
			} else {
				$name = $option['name'];
			}

			printf(
				'<option value="%1$s"%2$s>%3$s</option>',
				esc_attr( (string) $id ),
				selected( $notification_id, $id, false ),
				esc_html( $name )
			);
		}
		echo '</select>';

		submit_button( __( 'Filter', 'bb-groomflow' ), 'secondary', 'bbgf_filter_submit', false );

		$has_filters = ! empty( $stage ) || ! empty( $status ) || ! empty( $notification_id ) || '' !== $date_start || '' !== $date_end || '' !== $search;
		if ( $has_filters ) {
			$reset_url = remove_query_arg( array( 'bbgf_filter_stage', 'bbgf_filter_status', 'bbgf_filter_notification', 'bbgf_filter_date_start', 'bbgf_filter_date_end', 'paged', 's' ) );
			printf(
				' <a class="button" href="%s">%s</a>',
				esc_url( $reset_url ),
				esc_html__( 'Reset', 'bb-groomflow' )
			);
		}

		$export_url = $this->build_export_url(
			array(
				'bbgf_filter_stage'        => $stage,
				'bbgf_filter_status'       => $status,
				'bbgf_filter_notification' => $notification_id,
				'bbgf_filter_date_start'   => $date_start,
				'bbgf_filter_date_end'     => $date_end,
				's'                        => $search,
			)
		);

		printf(
			' <a class="button button-secondary" href="%s">%s</a>',
			esc_url( $export_url ),
			esc_html__( 'Export CSV', 'bb-groomflow' )
		);

		echo '</div>';
	}

	/**
	 * Stage options for filters.
	 *
	 * @return array<int,string>
	 */
	public function get_stage_options(): array {
		return $this->stage_options;
	}

	/**
	 * Status options for filters.
	 *
	 * @return array<string,string>
	 */
	public function get_status_options(): array {
		return array(
			''       => __( 'All statuses', 'bb-groomflow' ),
			'sent'   => __( 'Sent', 'bb-groomflow' ),
			'failed' => __( 'Failed', 'bb-groomflow' ),
		);
	}

	/**
	 * Notification options for filters.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public function get_notification_options(): array {
		return $this->notification_options;
	}

	/**
	 * Empty message.
	 *
	 * @return void
	 */
	public function no_items(): void {
		esc_html_e( 'No notification deliveries recorded yet.', 'bb-groomflow' );
	}

	/**
	 * Normalize a Y-m-d date value.
	 *
	 * @param string $date Raw date string.
	 * @return string|null
	 */
	private function sanitize_date( string $date ): ?string {
		$date = trim( $date );
		if ( '' === $date ) {
			return null;
		}

		$parsed = date_create_from_format( 'Y-m-d', $date );
		if ( ! $parsed ) {
			return null;
		}

		return $parsed->format( 'Y-m-d' );
	}

	/**
	 * Build export URL including active filters and nonce.
	 *
	 * @param array<string,mixed> $args Query args to include.
	 * @return string
	 */
	private function build_export_url( array $args ): string {
		$base_args = array(
			'page'            => Notification_Logs_Admin::PAGE_SLUG, // phpcs:ignore WordPress.Arrays.MultipleStatementAlignment.DoubleArrowNotAligned
			'bbgf_export_logs' => 1,
		);

		$sanitized_args = array();
		foreach ( $args as $key => $value ) {
			if ( '' === $value || null === $value ) {
				continue;
			}

			$sanitized_args[ $key ] = $value;
		}

		$url = add_query_arg( array_merge( $base_args, $sanitized_args ) );

		return wp_nonce_url( $url, 'bbgf_export_notification_logs', 'bbgf_export_nonce' );
	}

	/**
	 * Build resend URL for a log entry with nonce.
	 *
	 * @param int $log_id Log ID.
	 * @return string
	 */
	private function get_resend_url( int $log_id ): string {
		$url = add_query_arg(
			array(
				'page'             => Notification_Logs_Admin::PAGE_SLUG, // phpcs:ignore WordPress.Arrays.MultipleStatementAlignment.DoubleArrowNotAligned
				'bbgf_resend_log' => $log_id,
			),
			admin_url( 'admin.php' )
		);

		return wp_nonce_url( $url, 'bbgf_resend_notification_log', 'bbgf_resend_nonce' );
	}
}
