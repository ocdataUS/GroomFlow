<?php
/**
 * Services list table implementation.
 *
 * @package BB_GroomFlow
 */

namespace BBGF\Admin;

use BBGF\Plugin;
use WP_List_Table;

if ( ! class_exists( '\\WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * List table for catalog services.
 */
class Services_List_Table extends WP_List_Table {
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
				'singular' => 'service',
				'plural'   => 'services',
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
			'duration_minutes' => array( 'duration_minutes', false ),
			'price'            => array( 'price', false ),
			'updated_at'       => array( 'updated_at', true ),
		);

		$this->_column_headers = array( $columns, $hidden, $sortable );

		$per_page = 20;
		$current  = $this->get_pagenum();
		$offset   = ( $current - 1 ) * $per_page;

		$order_by = 'created_at';
		if ( isset( $_GET['orderby'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$requested = sanitize_key( wp_unslash( $_GET['orderby'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( array_key_exists( $requested, $sortable ) ) {
				$order_by = $requested;
			}
		}

		$order = 'DESC';
		if ( isset( $_GET['order'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$order_value = strtolower( sanitize_text_field( wp_unslash( $_GET['order'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( in_array( $order_value, array( 'asc', 'desc' ), true ) ) {
				$order = strtoupper( $order_value );
			}
		}

		$allowed_order_by = array( 'name', 'duration_minutes', 'price', 'updated_at', 'created_at' );
		if ( ! in_array( $order_by, $allowed_order_by, true ) ) {
			$order_by = 'created_at';
		}

		$wpdb      = $this->plugin->get_wpdb();
		$tables    = $this->plugin->get_table_names();
		$search    = '';
		$where_sql = '';

		if ( isset( $_REQUEST['s'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$search = trim( sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}

		if ( '' !== $search ) {
			$like      = '%' . $wpdb->esc_like( $search ) . '%';
			$where_sql = $wpdb->prepare(
				'WHERE name LIKE %s OR description LIKE %s OR flags LIKE %s',
				$like,
				$like,
				$like
			);
		}

		$sql_base = "FROM {$tables['services']}";
		if ( $where_sql ) {
			$sql_base .= ' ' . $where_sql;
		}

		/* phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared */
		$total_items = (int) $wpdb->get_var( "SELECT COUNT(*) {$sql_base}" );

		$order_clause = sprintf( '%s %s', esc_sql( $order_by ), esc_sql( $order ) );
		$data_sql     = sprintf(
			'SELECT * %1$s ORDER BY %2$s LIMIT %3$d OFFSET %4$d',
			$sql_base,
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
			'name'             => __( 'Service', 'bb-groomflow' ),
			'icon'             => __( 'Icon', 'bb-groomflow' ),
			'color'            => __( 'Color', 'bb-groomflow' ),
			'duration_minutes' => __( 'Duration (min)', 'bb-groomflow' ),
			'price'            => __( 'Price', 'bb-groomflow' ),
			'updated_at'       => __( 'Last Updated', 'bb-groomflow' ),
		);
	}

	/**
	 * Sortable columns.
	 */
	protected function get_sortable_columns(): array {
		return array(
			'name'             => array( 'name', false ),
			'duration_minutes' => array( 'duration_minutes', false ),
			'price'            => array( 'price', false ),
			'updated_at'       => array( 'updated_at', true ),
		);
	}

	/**
	 * Default column output.
	 *
	 * @param array  $item        Current row.
	 * @param string $column_name Column name.
	 * @return string
	 */
	protected function column_default( $item, $column_name ) {
		switch ( $column_name ) {
			case 'icon':
				return esc_html( $item['icon'] );
			case 'color':
				if ( empty( $item['color'] ) ) {
					return '&mdash;';
				}

				return sprintf(
					'<span class="bbgf-service-color" style="display:inline-block;width:14px;height:14px;border-radius:999px;margin-right:6px;vertical-align:middle;background:%1$s;"></span> %1$s',
					esc_html( $item['color'] )
				);
			case 'duration_minutes':
				return $item['duration_minutes'] ? esc_html( (string) $item['duration_minutes'] ) : '&mdash;';
			case 'price':
				if ( '' === $item['price'] || null === $item['price'] ) {
					return '&mdash;';
				}

				return sprintf( '$%s', esc_html( number_format_i18n( (float) $item['price'], 2 ) ) );
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
		$service_id = (int) $item['id'];
		$edit_url   = add_query_arg(
			array(
				'service_id' => $service_id,
			),
			$this->plugin->admin_url( Services_Admin::PAGE_SLUG )
		);
		$delete_url = wp_nonce_url(
			add_query_arg(
				array(
					'action'     => 'delete',
					'service_id' => $service_id,
				),
				$this->plugin->admin_url( Services_Admin::PAGE_SLUG )
			),
			'bbgf_delete_service'
		);

		$actions = array(
			'edit'   => sprintf( '<a href="%s">%s</a>', esc_url( $edit_url ), esc_html__( 'Edit', 'bb-groomflow' ) ),
			'delete' => sprintf( '<a href="%s" onclick="return confirm(\'%s\');">%s</a>', esc_url( $delete_url ), esc_js( __( 'Delete this service?', 'bb-groomflow' ) ), esc_html__( 'Delete', 'bb-groomflow' ) ),
		);

		return sprintf( '<strong>%s</strong> %s', esc_html( $item['name'] ), $this->row_actions( $actions ) );
	}

	/**
	 * Display message when no items exist.
	 */
	public function no_items(): void {
		esc_html_e( 'No services defined yet.', 'bb-groomflow' );
	}

	/**
	 * Format datetime helper.
	 *
	 * @param string|null $datetime Datetime column.
	 * @return string
	 */
	private function format_datetime( $datetime ): string {
		if ( empty( $datetime ) ) {
			return '&mdash;';
		}

		$ts = strtotime( $datetime );

		return $ts ? esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $ts ) ) : esc_html( (string) $datetime );
	}
}
