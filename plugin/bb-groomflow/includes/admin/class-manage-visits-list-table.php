<?php
/**
 * Manage Visits list table.
 *
 * @package BB_GroomFlow
 */

namespace BBGF\Admin;

use BBGF\Plugin;
use WP_List_Table;

if ( ! class_exists( '\WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * List table for visit history management.
 */
class Manage_Visits_List_Table extends WP_List_Table {
	/**
	 * Default sort column.
	 */
	private const DEFAULT_ORDER_BY = 'check_in_at';

	/**
	 * Default sort direction.
	 */
	private const DEFAULT_ORDER = 'desc';

	/**
	 * Allowed orderby keys.
	 *
	 * @var array<int,string>
	 */
	private const ORDERABLE_COLUMNS = array(
		'visit_id',
		'check_in_at',
		'client',
		'guardian',
		'stage',
		'status',
		'check_out_at',
	);

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
	 * Stage options keyed by stage key.
	 *
	 * @var array<string,string>
	 */
	private array $stage_options = array();

	/**
	 * View options keyed by view ID.
	 *
	 * @var array<int,string>
	 */
	private array $view_options = array();

	/**
	 * Service options keyed by service ID.
	 *
	 * @var array<int,string>
	 */
	private array $service_options = array();

	/**
	 * Cached ordering data.
	 *
	 * @var array<string,string>
	 */
	private array $ordering = array(
		'orderby' => self::DEFAULT_ORDER_BY,
		'order'   => self::DEFAULT_ORDER,
	);

	/**
	 * Constructor.
	 *
	 * @param Plugin               $plugin  Plugin instance.
	 * @param array<string,string> $filters Filters to apply.
	 */
	public function __construct( Plugin $plugin, array $filters = array() ) {
		parent::__construct(
			array(
				'singular' => 'visit',
				'plural'   => 'visits',
				'ajax'     => false,
			)
		);

		$this->plugin  = $plugin;
		$this->filters = wp_parse_args(
			$filters,
			array(
				'search'     => '',
				'status'     => '',
				'date_start' => '',
				'date_end'   => '',
				'view_id'    => 0,
				'stage'      => '',
				'service_id' => 0,
			)
		);
	}

	/**
	 * Normalize ordering from the current request.
	 *
	 * @return array{orderby:string,order:string}
	 */
	public static function get_ordering_from_request(): array {
		$orderby = isset( $_GET['orderby'] ) ? sanitize_key( wp_unslash( $_GET['orderby'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! in_array( $orderby, self::ORDERABLE_COLUMNS, true ) ) {
			$orderby = self::DEFAULT_ORDER_BY;
		}

		$order = isset( $_GET['order'] ) ? strtolower( sanitize_text_field( wp_unslash( $_GET['order'] ) ) ) : self::DEFAULT_ORDER; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! in_array( $order, array( 'asc', 'desc' ), true ) ) {
			$order = self::DEFAULT_ORDER;
		}

		return array(
			'orderby' => $orderby,
			'order'   => $order,
		);
	}

	/**
	 * Prepare items for display.
	 */
	public function prepare_items(): void {
		$columns  = $this->get_columns();
		$hidden   = array();
		$sortable = $this->get_sortable_columns();

		$this->_column_headers = array( $columns, $hidden, $sortable );

		$per_page       = 20;
		$current_page   = max( 1, $this->get_pagenum() );
		$this->ordering = self::get_ordering_from_request();

		$result = $this->plugin->visit_service()->get_manage_visits( $this->filters, $this->ordering, $current_page, $per_page );

		$this->items = $result['items'] ?? array();

		$this->set_pagination_args(
			array(
				'total_items' => (int) ( $result['total'] ?? 0 ),
				'per_page'    => (int) ( $result['per_page'] ?? $per_page ),
			)
		);

		$this->stage_options   = $this->fetch_stage_options();
		$this->view_options    = $this->fetch_view_options();
		$this->service_options = $this->fetch_service_options();
	}

	/**
	 * Columns definition.
	 *
	 * @return array<string,string>
	 */
	public function get_columns(): array {
		return array(
			'visit_id'     => __( 'Visit ID', 'bb-groomflow' ),
			'check_in_at'  => __( 'Check-in', 'bb-groomflow' ),
			'client'       => __( 'Client', 'bb-groomflow' ),
			'guardian'     => __( 'Guardian', 'bb-groomflow' ),
			'services'     => __( 'Services', 'bb-groomflow' ),
			'stage'        => __( 'Current Stage', 'bb-groomflow' ),
			'status'       => __( 'Status', 'bb-groomflow' ),
			'check_out_at' => __( 'Checked-out At', 'bb-groomflow' ),
			'actions'      => __( 'Actions', 'bb-groomflow' ),
		);
	}

	/**
	 * Sortable columns.
	 *
	 * @return array<string,array>
	 */
	protected function get_sortable_columns() {
		return array(
			'visit_id'     => array( 'visit_id', false ),
			'check_in_at'  => array( 'check_in_at', true ),
			'client'       => array( 'client', false ),
			'guardian'     => array( 'guardian', false ),
			'stage'        => array( 'stage', false ),
			'status'       => array( 'status', false ),
			'check_out_at' => array( 'check_out_at', false ),
		);
	}

	/**
	 * Default column renderer.
	 *
	 * @param array  $item        Row data.
	 * @param string $column_name Column key.
	 * @return string
	 */
	protected function column_default( $item, $column_name ) {
		switch ( $column_name ) {
			case 'client':
				return $this->render_client_column( $item );
			case 'guardian':
				return $this->render_guardian_column( $item );
			case 'services':
				return $this->render_services_column( $item );
			case 'stage':
				return $this->render_stage_column( $item );
			case 'status':
				return $this->render_status_column( $item );
			default:
				return '';
		}
	}

	/**
	 * Visit ID column.
	 *
	 * @param array $item Row data.
	 * @return string
	 */
	protected function column_visit_id( $item ): string {
		$visit_id = isset( $item['id'] ) ? (int) $item['id'] : 0;

		return $visit_id > 0 ? sprintf( '<strong>#%d</strong>', $visit_id ) : '&mdash;';
	}

	/**
	 * Check-in column.
	 *
	 * @param array $item Row data.
	 * @return string
	 */
	protected function column_check_in_at( $item ): string {
		return $this->format_datetime( $this->get_display_check_in( $item ) );
	}

	/**
	 * Checked-out column.
	 *
	 * @param array $item Row data.
	 * @return string
	 */
	protected function column_check_out_at( $item ): string {
		return $this->format_datetime( $item['check_out_at'] ?? '' );
	}

	/**
	 * Actions column.
	 *
	 * @param array $item Row data.
	 * @return string
	 */
	protected function column_actions( $item ): string {
		$visit_id = isset( $item['id'] ) ? (int) $item['id'] : 0;
		if ( $visit_id <= 0 ) {
			return '';
		}

		$details_id = 'bbgf-visit-details-' . $visit_id;
		$actions    = array(
			sprintf(
				'<button type="button" class="button button-small bbgf-visit-toggle" data-open-text="%1$s" data-close-text="%2$s" aria-expanded="false" aria-controls="%3$s">%1$s</button>',
				esc_html__( 'Details', 'bb-groomflow' ),
				esc_html__( 'Hide Details', 'bb-groomflow' ),
				esc_attr( $details_id )
			),
		);

		if ( $this->is_uncheckout_available( $item ) ) {
			$actions[] = sprintf(
				'<a class="button button-small" href="%1$s" onclick="return confirm(\'%2$s\');">%3$s</a>',
				esc_url( $this->get_uncheckout_url( $visit_id ) ),
				esc_js( __( 'Reopen this visit?', 'bb-groomflow' ) ),
				esc_html__( 'Uncheckout', 'bb-groomflow' )
			);
		}

		return '<div class="bbgf-table-actions">' . implode( ' ', $actions ) . '</div>';
	}

	/**
	 * Render client name column.
	 *
	 * @param array $item Row data.
	 * @return string
	 */
	private function render_client_column( array $item ): string {
		$name = trim( (string) ( $item['client_name'] ?? '' ) );

		return '' !== $name ? esc_html( $name ) : '&mdash;';
	}

	/**
	 * Render guardian column.
	 *
	 * @param array $item Row data.
	 * @return string
	 */
	private function render_guardian_column( array $item ): string {
		$guardian = $this->get_guardian_name( $item );

		return '' !== $guardian ? esc_html( $guardian ) : '&mdash;';
	}

	/**
	 * Render services summary column.
	 *
	 * @param array $item Row data.
	 * @return string
	 */
	private function render_services_column( array $item ): string {
		$service_names = $this->get_service_names( $item );
		if ( empty( $service_names ) ) {
			return '&mdash;';
		}

		if ( count( $service_names ) <= 2 ) {
			return esc_html( implode( ', ', $service_names ) );
		}

		$label = sprintf(
			/* translators: %d: number of services. */
			__( '%d services', 'bb-groomflow' ),
			count( $service_names )
		);

		return sprintf(
			'<span title="%1$s">%2$s</span>',
			esc_attr( implode( ', ', $service_names ) ),
			esc_html( $label )
		);
	}

	/**
	 * Render stage column.
	 *
	 * @param array $item Row data.
	 * @return string
	 */
	private function render_stage_column( array $item ): string {
		$stage_key = sanitize_key( (string) ( $item['current_stage'] ?? '' ) );
		if ( '' === $stage_key ) {
			return '&mdash;';
		}

		$label = $this->stage_options[ $stage_key ] ?? $stage_key;

		return esc_html( $label );
	}

	/**
	 * Render status column.
	 *
	 * @param array $item Row data.
	 * @return string
	 */
	private function render_status_column( array $item ): string {
		$status = sanitize_key( (string) ( $item['status'] ?? '' ) );
		if ( '' === $status ) {
			return esc_html__( 'Active', 'bb-groomflow' );
		}

		$label = ucwords( str_replace( '_', ' ', $status ) );

		return esc_html( $label );
	}

	/**
	 * Display filter controls and export buttons.
	 *
	 * @param string $which Table position.
	 */
	protected function extra_tablenav( $which ): void {
		if ( 'top' !== $which ) {
			return;
		}

		$search     = trim( (string) ( $this->filters['search'] ?? '' ) );
		$status     = sanitize_key( (string) ( $this->filters['status'] ?? '' ) );
		$stage      = sanitize_key( (string) ( $this->filters['stage'] ?? '' ) );
		$view_id    = (int) ( $this->filters['view_id'] ?? 0 );
		$service_id = (int) ( $this->filters['service_id'] ?? 0 );
		$date_start = $this->sanitize_date( (string) ( $this->filters['date_start'] ?? '' ) ) ?? '';
		$date_end   = $this->sanitize_date( (string) ( $this->filters['date_end'] ?? '' ) ) ?? '';

		echo '<div class="alignleft actions">';

		if ( ! empty( $this->ordering['orderby'] ) ) {
			printf(
				'<input type="hidden" name="orderby" value="%s" />',
				esc_attr( $this->ordering['orderby'] )
			);
		}

		if ( ! empty( $this->ordering['order'] ) ) {
			printf(
				'<input type="hidden" name="order" value="%s" />',
				esc_attr( $this->ordering['order'] )
			);
		}

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

		echo '<label class="screen-reader-text" for="bbgf-filter-stage">' . esc_html__( 'Filter by stage', 'bb-groomflow' ) . '</label>';
		echo '<select name="bbgf_filter_stage" id="bbgf-filter-stage">';
		echo '<option value="">' . esc_html__( 'All stages', 'bb-groomflow' ) . '</option>';
		foreach ( $this->stage_options as $key => $label ) {
			printf(
				'<option value="%1$s"%2$s>%3$s</option>',
				esc_attr( $key ),
				selected( $stage, $key, false ),
				esc_html( $label )
			);
		}
		echo '</select>';

		echo '<label class="screen-reader-text" for="bbgf-filter-view">' . esc_html__( 'Filter by view', 'bb-groomflow' ) . '</label>';
		echo '<select name="bbgf_filter_view" id="bbgf-filter-view">';
		echo '<option value="0">' . esc_html__( 'All views', 'bb-groomflow' ) . '</option>';
		foreach ( $this->view_options as $id => $label ) {
			printf(
				'<option value="%1$s"%2$s>%3$s</option>',
				esc_attr( (string) $id ),
				selected( $view_id, $id, false ),
				esc_html( $label )
			);
		}
		echo '</select>';

		echo '<label class="screen-reader-text" for="bbgf-filter-service">' . esc_html__( 'Filter by service', 'bb-groomflow' ) . '</label>';
		echo '<select name="bbgf_filter_service" id="bbgf-filter-service">';
		echo '<option value="0">' . esc_html__( 'All services', 'bb-groomflow' ) . '</option>';
		foreach ( $this->service_options as $id => $label ) {
			printf(
				'<option value="%1$s"%2$s>%3$s</option>',
				esc_attr( (string) $id ),
				selected( $service_id, $id, false ),
				esc_html( $label )
			);
		}
		echo '</select>';

		submit_button( __( 'Filter', 'bb-groomflow' ), 'secondary', 'bbgf_filter_submit', false );

		$has_filters = ! empty( $search ) || ! empty( $status ) || ! empty( $stage ) || $view_id > 0 || $service_id > 0 || '' !== $date_start || '' !== $date_end;
		if ( $has_filters ) {
			$reset_url = remove_query_arg(
				array(
					'bbgf_filter_status',
					'bbgf_filter_stage',
					'bbgf_filter_view',
					'bbgf_filter_service',
					'bbgf_filter_date_start',
					'bbgf_filter_date_end',
					'paged',
					's',
				)
			);
			printf(
				' <a class="button" href="%s">%s</a>',
				esc_url( $reset_url ),
				esc_html__( 'Reset', 'bb-groomflow' )
			);
		}

		$export_params = array(
			'bbgf_filter_status'     => $status,
			'bbgf_filter_stage'      => $stage,
			'bbgf_filter_view'       => $view_id,
			'bbgf_filter_service'    => $service_id,
			'bbgf_filter_date_start' => $date_start,
			'bbgf_filter_date_end'   => $date_end,
			's'                      => $search,
			'orderby'                => $this->ordering['orderby'] ?? self::DEFAULT_ORDER_BY,
			'order'                  => $this->ordering['order'] ?? self::DEFAULT_ORDER,
		);

		printf(
			' <a class="button button-secondary" href="%1$s">%2$s</a>',
			esc_url( $this->build_export_url( 'table', $export_params ) ),
			esc_html__( 'Export CSV (Table Columns)', 'bb-groomflow' )
		);

		printf(
			' <a class="button button-secondary" href="%1$s">%2$s</a>',
			esc_url( $this->build_export_url( 'all', $export_params ) ),
			esc_html__( 'Export CSV (All Fields)', 'bb-groomflow' )
		);

		echo '</div>';
	}

	/**
	 * Add a details row after each visit row.
	 *
	 * @param array $item Row data.
	 */
	public function single_row( $item ): void {
		parent::single_row( $item );

		$visit_id = isset( $item['id'] ) ? (int) $item['id'] : 0;
		if ( $visit_id <= 0 ) {
			return;
		}

		$details_id = 'bbgf-visit-details-' . $visit_id;
		$colspan    = count( $this->get_columns() );

		printf(
			'<tr id="%1$s" class="bbgf-visit-details-row" hidden><td colspan="%2$d">%3$s</td></tr>',
			esc_attr( $details_id ),
			(int) $colspan,
			$this->render_details_panel( $item ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped within render_details_panel().
		);
	}

	/**
	 * Message for empty table.
	 */
	public function no_items(): void {
		esc_html_e( 'No visits found for the current filters.', 'bb-groomflow' );
	}

	/**
	 * Fetch stage options.
	 *
	 * @return array<string,string>
	 */
	private function fetch_stage_options(): array {
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

		$options = array();
		foreach ( $rows as $row ) {
			$key = sanitize_key( (string) ( $row['stage_key'] ?? '' ) );
			if ( '' === $key ) {
				continue;
			}
			$label           = (string) ( $row['label'] ?? '' );
			$options[ $key ] = '' !== $label ? $label : ucfirst( $key );
		}

		return $options;
	}

	/**
	 * Fetch view options.
	 *
	 * @return array<int,string>
	 */
	private function fetch_view_options(): array {
		$views   = $this->plugin->visit_service()->get_views_list();
		$options = array();

		foreach ( $views as $view ) {
			$id = isset( $view['id'] ) ? (int) $view['id'] : 0;
			if ( $id <= 0 ) {
				continue;
			}
			$options[ $id ] = (string) ( $view['name'] ?? $view['slug'] ?? $id );
		}

		return $options;
	}

	/**
	 * Fetch service options.
	 *
	 * @return array<int,string>
	 */
	private function fetch_service_options(): array {
		$wpdb   = $this->plugin->get_wpdb();
		$tables = $this->plugin->get_table_names();

		if ( empty( $tables['services'] ) ) {
			return array();
		}

		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare( // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				'SELECT id, name FROM %i ORDER BY name ASC',
				$tables['services']
			),
			ARRAY_A
		);

		$options = array();
		foreach ( $rows as $row ) {
			$id = isset( $row['id'] ) ? (int) $row['id'] : 0;
			if ( $id <= 0 ) {
				continue;
			}
			$options[ $id ] = (string) ( $row['name'] ?? $id );
		}

		return $options;
	}

	/**
	 * Build export URL with filters.
	 *
	 * @param string              $type   Export type.
	 * @param array<string,mixed> $params Query parameters.
	 * @return string
	 */
	private function build_export_url( string $type, array $params ): string {
		$url = add_query_arg(
			array_merge(
				$params,
				array(
					'action'      => Manage_Visits_Admin::ACTION_EXPORT,
					'export_type' => $type,
				)
			),
			admin_url( 'admin-post.php' )
		);

		return wp_nonce_url( $url, Manage_Visits_Admin::NONCE_EXPORT );
	}

	/**
	 * Build uncheckout URL.
	 *
	 * @param int $visit_id Visit ID.
	 * @return string
	 */
	private function get_uncheckout_url( int $visit_id ): string {
		$url = add_query_arg(
			array(
				'action'   => Manage_Visits_Admin::ACTION_UNCHECKOUT,
				'visit_id' => $visit_id,
			),
			admin_url( 'admin-post.php' )
		);

		return wp_nonce_url( $url, Manage_Visits_Admin::NONCE_UNCHECKOUT . '_' . $visit_id );
	}

	/**
	 * Determine if uncheckout should be shown.
	 *
	 * @param array $item Row data.
	 * @return bool
	 */
	private function is_uncheckout_available( array $item ): bool {
		$check_out_at = (string) ( $item['check_out_at'] ?? '' );
		$status       = sanitize_key( (string) ( $item['status'] ?? '' ) );

		return '' !== $check_out_at || 'completed' === $status;
	}

	/**
	 * Get guardian full name.
	 *
	 * @param array $item Row data.
	 * @return string
	 */
	private function get_guardian_name( array $item ): string {
		$first = trim( (string) ( $item['guardian_first_name'] ?? '' ) );
		$last  = trim( (string) ( $item['guardian_last_name'] ?? '' ) );

		return trim( $first . ' ' . $last );
	}

	/**
	 * Get service names for a visit.
	 *
	 * @param array $item Row data.
	 * @return array<int,string>
	 */
	private function get_service_names( array $item ): array {
		$services = isset( $item['services'] ) && is_array( $item['services'] ) ? $item['services'] : array();
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
	 * Determine the display check-in timestamp.
	 *
	 * @param array $item Row data.
	 * @return string
	 */
	private function get_display_check_in( array $item ): string {
		$check_in = (string) ( $item['check_in_at'] ?? '' );
		if ( '' !== $check_in ) {
			return $check_in;
		}

		$created = (string) ( $item['created_at'] ?? '' );
		if ( '' !== $created ) {
			return $created;
		}

		return (string) ( $item['updated_at'] ?? '' );
	}

	/**
	 * Render the details panel.
	 *
	 * @param array $item Row data.
	 * @return string
	 */
	private function render_details_panel( array $item ): string {
		$instructions  = $this->format_notes_value( (string) ( $item['instructions'] ?? '' ) );
		$public_notes  = $this->format_notes_value( (string) ( $item['public_notes'] ?? '' ) );
		$private_notes = $this->format_notes_value( (string) ( $item['private_notes'] ?? '' ) );
		$services      = $this->render_services_list( $item );
		$photos        = $this->render_photos( $item );
		$meta          = $this->render_meta_list( $item );

		return sprintf(
			'<div class="bbgf-visit-details"><div class="bbgf-visit-details-grid">
				<div class="bbgf-visit-detail"><h4>%1$s</h4>%2$s</div>
				<div class="bbgf-visit-detail"><h4>%3$s</h4>%4$s</div>
				<div class="bbgf-visit-detail"><h4>%5$s</h4>%6$s</div>
				<div class="bbgf-visit-detail"><h4>%7$s</h4>%8$s</div>
				<div class="bbgf-visit-detail"><h4>%9$s</h4>%10$s</div>
				<div class="bbgf-visit-detail"><h4>%11$s</h4>%12$s</div>
			</div></div>',
			esc_html__( 'Instructions', 'bb-groomflow' ),
			$instructions,
			esc_html__( 'Public Notes', 'bb-groomflow' ),
			$public_notes,
			esc_html__( 'Private Notes', 'bb-groomflow' ),
			$private_notes,
			esc_html__( 'Services', 'bb-groomflow' ),
			$services,
			esc_html__( 'Photos', 'bb-groomflow' ),
			$photos,
			esc_html__( 'Key Meta', 'bb-groomflow' ),
			$meta
		);
	}

	/**
	 * Render notes content.
	 *
	 * @param string $text Raw text.
	 * @return string
	 */
	private function format_notes_value( string $text ): string {
		$text = trim( $text );
		if ( '' === $text ) {
			return '<span class="bbgf-muted">&mdash;</span>';
		}

		return nl2br( esc_html( $text ) );
	}

	/**
	 * Render services list for details panel.
	 *
	 * @param array $item Row data.
	 * @return string
	 */
	private function render_services_list( array $item ): string {
		$service_names = $this->get_service_names( $item );
		if ( empty( $service_names ) ) {
			return '<span class="bbgf-muted">&mdash;</span>';
		}

		$items = array();
		foreach ( $service_names as $name ) {
			$items[] = sprintf( '<li>%s</li>', esc_html( $name ) );
		}

		return '<ul class="bbgf-visit-services">' . implode( '', $items ) . '</ul>';
	}

	/**
	 * Render photo thumbnails for details panel.
	 *
	 * @param array $item Row data.
	 * @return string
	 */
	private function render_photos( array $item ): string {
		$photos = isset( $item['photos'] ) && is_array( $item['photos'] ) ? $item['photos'] : array();
		if ( empty( $photos ) ) {
			return '<span class="bbgf-muted">&mdash;</span>';
		}

		$thumbs = array();
		foreach ( $photos as $photo ) {
			if ( empty( $photo['url'] ) ) {
				continue;
			}

			$photo_id  = isset( $photo['id'] ) ? (int) $photo['id'] : 0;
			$thumb_url = '';
			if ( isset( $photo['thumbnail'] ) && is_array( $photo['thumbnail'] ) ) {
				$thumb_url = (string) ( $photo['thumbnail']['url'] ?? '' );
			}

			$link = $photo_id > 0 ? get_edit_post_link( $photo_id ) : '';
			if ( ! $link ) {
				$link = (string) $photo['url'];
			}

			$thumbs[] = sprintf(
				'<a href="%1$s" target="_blank" rel="noopener"><img src="%2$s" alt="%3$s" /></a>',
				esc_url( $link ),
				esc_url( '' !== $thumb_url ? $thumb_url : $photo['url'] ),
				esc_attr( (string) ( $photo['alt'] ?? '' ) )
			);
		}

		if ( empty( $thumbs ) ) {
			return '<span class="bbgf-muted">&mdash;</span>';
		}

		return '<div class="bbgf-visit-photos">' . implode( '', $thumbs ) . '</div>';
	}

	/**
	 * Render key meta list.
	 *
	 * @param array $item Row data.
	 * @return string
	 */
	private function render_meta_list( array $item ): string {
		$view_id   = isset( $item['view_id'] ) ? (int) $item['view_id'] : 0;
		$view_name = trim( (string) ( $item['view_name'] ?? '' ) );
		if ( '' === $view_name && $view_id > 0 ) {
			$view_name = sprintf(
				/* translators: %d: view ID. */
				__( 'View #%d', 'bb-groomflow' ),
				$view_id
			);
		}
		$view_name = '' !== $view_name ? esc_html( $view_name ) : '';

		$meta = array(
			__( 'View', 'bb-groomflow' )        => '' !== $view_name ? $view_name : '&mdash;',
			__( 'Checked In', 'bb-groomflow' )  => $this->format_datetime( $this->get_display_check_in( $item ) ),
			__( 'Checked Out', 'bb-groomflow' ) => $this->format_datetime( (string) ( $item['check_out_at'] ?? '' ) ),
			__( 'Created', 'bb-groomflow' )     => $this->format_datetime( (string) ( $item['created_at'] ?? '' ) ),
			__( 'Updated', 'bb-groomflow' )     => $this->format_datetime( (string) ( $item['updated_at'] ?? '' ) ),
		);

		$rows = array();
		foreach ( $meta as $label => $value ) {
			$value  = '' !== $value ? $value : '&mdash;';
			$rows[] = sprintf(
				'<dt>%1$s</dt><dd>%2$s</dd>',
				esc_html( $label ),
				$value
			);
		}

		return '<dl class="bbgf-visit-meta">' . implode( '', $rows ) . '</dl>';
	}

	/**
	 * Format datetime for display.
	 *
	 * @param string $datetime Datetime string.
	 * @return string
	 */
	private function format_datetime( string $datetime ): string {
		if ( '' === $datetime ) {
			return '&mdash;';
		}

		$timestamp = strtotime( $datetime );
		if ( false === $timestamp ) {
			return esc_html( $datetime );
		}

		return esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $timestamp ) );
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
	 * Status filter options.
	 *
	 * @return array<string,string>
	 */
	private function get_status_options(): array {
		return array(
			''          => __( 'All statuses', 'bb-groomflow' ),
			'active'    => __( 'Active', 'bb-groomflow' ),
			'completed' => __( 'Completed', 'bb-groomflow' ),
			'cancelled' => __( 'Cancelled', 'bb-groomflow' ),
		);
	}
}
