<?php
/**
 * Clients list table implementation.
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
 * List table for client records.
 */
class Clients_List_Table extends WP_List_Table {
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
				'singular' => 'client',
				'plural'   => 'clients',
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
			'name'       => array( 'name', true ),
			'created_at' => array( 'created_at', true ),
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
				$order = $order_value;
			}
		}

		$wpdb   = $this->plugin->get_wpdb();
		$tables = $this->plugin->get_table_names();

		/* phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared */
		$total_items = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$tables['clients']}" );

		$sql = "SELECT clients.*, guardians.first_name AS guardian_first, guardians.last_name AS guardian_last
			FROM {$tables['clients']} AS clients
			LEFT JOIN {$tables['guardians']} AS guardians ON clients.guardian_id = guardians.id
			ORDER BY {$order_by} {$order}
			LIMIT %d OFFSET %d";

		/* phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared */
		$this->items = $wpdb->get_results( $wpdb->prepare( $sql, $per_page, $offset ), ARRAY_A );

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
			'name'        => __( 'Client', 'bb-groomflow' ),
			'guardian'    => __( 'Guardian', 'bb-groomflow' ),
			'breed'       => __( 'Breed', 'bb-groomflow' ),
			'temperament' => __( 'Temperament', 'bb-groomflow' ),
			'updated_at'  => __( 'Last Updated', 'bb-groomflow' ),
		);
	}

	/**
	 * Sortable columns.
	 */
	protected function get_sortable_columns() {
		return array(
			'name'       => array( 'name', false ),
			'updated_at' => array( 'updated_at', true ),
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
			case 'guardian':
				$guardian = trim( sprintf( '%s %s', $item['guardian_first'], $item['guardian_last'] ) );
				return $guardian ? esc_html( $guardian ) : '&mdash;';
			case 'breed':
				return esc_html( $item['breed'] );
			case 'temperament':
				return esc_html( $item['temperament'] );
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
		$client_id  = (int) $item['id'];
		$edit_url   = add_query_arg(
			array(
				'client_id' => $client_id,
			),
			$this->plugin->admin_url( Clients_Admin::PAGE_SLUG )
		);
		$delete_url = wp_nonce_url(
			add_query_arg(
				array(
					'action'    => 'delete',
					'client_id' => $client_id,
				),
				$this->plugin->admin_url( Clients_Admin::PAGE_SLUG )
			),
			'bbgf_delete_client'
		);

		$actions = array(
			'edit'   => sprintf( '<a href="%s">%s</a>', esc_url( $edit_url ), esc_html__( 'Edit', 'bb-groomflow' ) ),
			'delete' => sprintf( '<a href="%s" onclick="return confirm(\'%s\');">%s</a>', esc_url( $delete_url ), esc_js( __( 'Delete this client?', 'bb-groomflow' ) ), esc_html__( 'Delete', 'bb-groomflow' ) ),
		);

		return sprintf( '<strong>%s</strong> %s', esc_html( $item['name'] ), $this->row_actions( $actions ) );
	}

	/**
	 * Display message for no items.
	 */
	public function no_items(): void {
		esc_html_e( 'No clients found yet.', 'bb-groomflow' );
	}

	/**
	 * Format datetime.
	 *
	 * @param string|null $datetime Datetime.
	 * @return string
	 */
	private function format_datetime( $datetime ): string {
		if ( empty( $datetime ) ) {
			return '&mdash;';
		}

		$ts = strtotime( $datetime );

		return $ts ? esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $ts ) ) : esc_html( $datetime );
	}
}
