<?php
/**
 * Views list table implementation.
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
 * List table for Kanban views.
 */
class Views_List_Table extends WP_List_Table {
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
		parent::__construct(
			array(
				'singular' => 'view',
				'plural'   => 'views',
				'ajax'     => false,
			)
		);

		$this->plugin = $plugin;
	}

	/**
	 * Prepare items for display.
	 */
	public function prepare_items(): void {
		$columns  = $this->get_columns();
		$hidden   = array();
		$sortable = array(
			'name'             => array( 'name', true ),
			'type'             => array( 'type', false ),
			'refresh_interval' => array( 'refresh_interval', false ),
			'updated_at'       => array( 'updated_at', true ),
		);

		$this->_column_headers = array( $columns, $hidden, $sortable );

		$per_page = 20;
		$current  = $this->get_pagenum();
		$offset   = ( $current - 1 ) * $per_page;

		$order_by = 'created_at';
		$order    = 'DESC';

		if ( isset( $_GET['orderby'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$requested = sanitize_key( wp_unslash( $_GET['orderby'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( array_key_exists( $requested, $sortable ) ) {
				$order_by = $requested;
			}
		}

		if ( isset( $_GET['order'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$order_value = strtolower( sanitize_text_field( wp_unslash( $_GET['order'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( in_array( $order_value, array( 'asc', 'desc' ), true ) ) {
				$order = strtoupper( $order_value );
			}
		}

		$wpdb      = $this->plugin->get_wpdb();
		$tables    = $this->plugin->get_table_names();
		$search    = '';
		$where_sql = '';

		if ( isset( $_REQUEST['s'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$search = trim( sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}

		$base_from = sprintf(
			'%s AS v
		LEFT JOIN %s AS s ON v.id = s.view_id',
			$tables['views'],
			$tables['view_stages']
		);

		if ( '' !== $search ) {
			$like      = '%' . $wpdb->esc_like( $search ) . '%';
			$where_sql = $wpdb->prepare(
				'WHERE v.name LIKE %s OR v.slug LIKE %s OR v.type LIKE %s',
				$like,
				$like,
				$like
			);
		}

		$total_sql = "SELECT COUNT( DISTINCT v.id ) FROM {$base_from}";
		if ( $where_sql ) {
			$total_sql .= ' ' . $where_sql;
		}

		/* phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared */
		$total_items = (int) $wpdb->get_var( $total_sql );

		$order_clause = sprintf( '%s %s', esc_sql( $order_by ), esc_sql( $order ) );
		$data_sql     = sprintf(
			"SELECT v.*, COUNT(s.id) AS stage_count, GROUP_CONCAT(DISTINCT s.label ORDER BY s.sort_order ASC SEPARATOR ', ') AS stage_labels
			FROM %1\$s
			%2\$s
			GROUP BY v.id
			ORDER BY %3\$s
			LIMIT %4\$d OFFSET %5\$d",
			$base_from,
			$where_sql,
			$order_clause,
			(int) $per_page,
			(int) $offset
		);

		$this->items = $wpdb->get_results( $data_sql, ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared

		$this->set_pagination_args(
			array(
				'total_items' => $total_items,
				'per_page'    => $per_page,
			)
		);
	}

	/**
	 * Columns definition.
	 */
	public function get_columns(): array {
		return array(
			'name'             => __( 'View', 'bb-groomflow' ),
			'type'             => __( 'Type', 'bb-groomflow' ),
			'stage_count'      => __( 'Stage Count', 'bb-groomflow' ),
			'stage_list'       => __( 'Stages', 'bb-groomflow' ),
			'refresh_interval' => __( 'Refresh (s)', 'bb-groomflow' ),
			'updated_at'       => __( 'Last Updated', 'bb-groomflow' ),
		);
	}

	/**
	 * Sortable columns.
	 */
	protected function get_sortable_columns(): array {
		return array(
			'name'             => array( 'name', false ),
			'type'             => array( 'type', false ),
			'refresh_interval' => array( 'refresh_interval', false ),
			'updated_at'       => array( 'updated_at', true ),
		);
	}

	/**
	 * Default column output.
	 *
	 * @param array  $item        Current row.
	 * @param string $column_name Column name.
	 */
	protected function column_default( $item, $column_name ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundInExtendedClassBeforeLastUsed
		switch ( $column_name ) {
			case 'type':
				$types    = array(
					'internal' => __( 'Internal', 'bb-groomflow' ),
					'lobby'    => __( 'Lobby', 'bb-groomflow' ),
					'kiosk'    => __( 'Kiosk', 'bb-groomflow' ),
				);
				$type_key = $item['type'] ?? 'internal';
				return esc_html( $types[ $type_key ] ?? ucfirst( $type_key ) );
			case 'stage_count':
				return esc_html( (string) ( $item['stage_count'] ?? 0 ) );
			case 'stage_list':
				$labels = isset( $item['stage_labels'] ) ? array_filter( array_map( 'trim', explode( ',', (string) $item['stage_labels'] ) ) ) : array();
				if ( empty( $labels ) ) {
					return '<span class="bbgf-muted">' . esc_html__( 'No stages', 'bb-groomflow' ) . '</span>';
				}

				$chips = array();
				foreach ( $labels as $label ) {
					$chips[] = sprintf( '<span class="bbgf-chip">%s</span>', esc_html( $label ) );
				}

				return implode( ' ', $chips );
			case 'refresh_interval':
				return esc_html( (string) ( $item['refresh_interval'] ?? 0 ) );
			case 'updated_at':
				return $this->format_datetime( $item['updated_at'] );
			default:
				return '';
		}
	}

	/**
	 * Name column containing row actions.
	 *
	 * @param array $item Row data.
	 * @return string
	 */
	protected function column_name( $item ) {
		$view_id    = (int) $item['id'];
		$edit_url   = add_query_arg(
			array(
				'view_id' => $view_id,
			),
			$this->plugin->admin_url( Views_Admin::PAGE_SLUG )
		);
		$delete_url = wp_nonce_url(
			add_query_arg(
				array(
					'action'  => 'delete',
					'view_id' => $view_id,
				),
				$this->plugin->admin_url( Views_Admin::PAGE_SLUG )
			),
			'bbgf_delete_view'
		);

		$actions = array(
			'edit'   => sprintf( '<a href="%s">%s</a>', esc_url( $edit_url ), esc_html__( 'Edit', 'bb-groomflow' ) ),
			'delete' => sprintf( '<a href="%s" onclick="return confirm(\'%s\');">%s</a>', esc_url( $delete_url ), esc_js( __( 'Delete this view?', 'bb-groomflow' ) ), esc_html__( 'Delete', 'bb-groomflow' ) ),
		);

		$badges = $this->build_badges( $item );

		return sprintf(
			'<strong>%s</strong> %s %s',
			esc_html( $item['name'] ),
			$badges,
			$this->row_actions( $actions )
		);
	}

	/**
	 * Display message when no items exist.
	 */
	public function no_items(): void {
		esc_html_e( 'No views configured yet.', 'bb-groomflow' );
	}

	/**
	 * Format datetime.
	 *
	 * @param string|null $datetime Datetime column.
	 */
	private function format_datetime( $datetime ): string {
		if ( empty( $datetime ) ) {
			return '&mdash;';
		}

		$ts = strtotime( $datetime );

		return $ts ? esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $ts ) ) : esc_html( (string) $datetime );
	}

	/**
	 * Build badge markup for a view.
	 *
	 * @param array $item View row.
	 * @return string
	 */
	private function build_badges( array $item ): string {
		$badges = array();

		$type = $item['type'] ?? 'internal';
		if ( 'lobby' === $type ) {
			$badges[] = '<span class="bbgf-badge bbgf-badge--lobby">' . esc_html__( 'Lobby', 'bb-groomflow' ) . '</span>';
		} elseif ( 'kiosk' === $type ) {
			$badges[] = '<span class="bbgf-badge bbgf-badge--kiosk">' . esc_html__( 'Kiosk', 'bb-groomflow' ) . '</span>';
		} else {
			$badges[] = '<span class="bbgf-badge bbgf-badge--internal">' . esc_html__( 'Internal', 'bb-groomflow' ) . '</span>';
		}

		if ( ! empty( $item['allow_switcher'] ) ) {
			$badges[] = '<span class="bbgf-badge bbgf-badge--switcher">' . esc_html__( 'Switcher', 'bb-groomflow' ) . '</span>';
		}

		if ( ! empty( $item['show_guardian'] ) ) {
			$badges[] = '<span class="bbgf-badge bbgf-badge--guardian">' . esc_html__( 'Guardian', 'bb-groomflow' ) . '</span>';
		}

		return implode( ' ', $badges );
	}
}
