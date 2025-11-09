<?php
/**
 * Guardians list table implementation.
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
 * List table for guardian records.
 */
class Guardians_List_Table extends WP_List_Table {
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
				'singular' => 'guardian',
				'plural'   => 'guardians',
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
			'last_name'  => array( 'last_name', true ),
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
		$total_items = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$tables['guardians']}" );

		$sql = "SELECT * FROM {$tables['guardians']} ORDER BY {$order_by} {$order} LIMIT %d OFFSET %d";

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
			'name'              => __( 'Guardian', 'bb-groomflow' ),
			'email'             => __( 'Email', 'bb-groomflow' ),
			'phone_mobile'      => __( 'Mobile', 'bb-groomflow' ),
			'preferred_contact' => __( 'Preferred Contact', 'bb-groomflow' ),
			'updated_at'        => __( 'Last Updated', 'bb-groomflow' ),
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
			case 'email':
				return $item['email'] ? sprintf( '<a href="mailto:%1$s">%1$s</a>', esc_html( $item['email'] ) ) : '&mdash;';
			case 'phone_mobile':
				return $item['phone_mobile'] ? esc_html( $item['phone_mobile'] ) : '&mdash;';
			case 'preferred_contact':
				return esc_html( ucfirst( $item['preferred_contact'] ) );
			case 'updated_at':
				return $this->format_datetime( $item['updated_at'] );
			default:
				return '';
		}
	}

	/**
	 * Name column with row actions.
	 *
	 * @param array $item Row data.
	 * @return string
	 */
	protected function column_name( $item ) {
		$guardian_id = (int) $item['id'];
		$edit_url    = add_query_arg(
			array(
				'guardian_id' => $guardian_id,
			),
			$this->plugin->admin_url( Guardians_Admin::PAGE_SLUG )
		);
		$delete_url  = wp_nonce_url(
			add_query_arg(
				array(
					'action'      => 'delete',
					'guardian_id' => $guardian_id,
				),
				$this->plugin->admin_url( Guardians_Admin::PAGE_SLUG )
			),
			'bbgf_delete_guardian'
		);

		$name    = trim( sprintf( '%s %s', $item['first_name'], $item['last_name'] ) );
		$actions = array(
			'edit'   => sprintf( '<a href="%s">%s</a>', esc_url( $edit_url ), esc_html__( 'Edit', 'bb-groomflow' ) ),
			'delete' => sprintf( '<a href="%s" onclick="return confirm(\'%s\');">%s</a>', esc_url( $delete_url ), esc_js( __( 'Delete this guardian?', 'bb-groomflow' ) ), esc_html__( 'Delete', 'bb-groomflow' ) ),
		);

		return sprintf( '<strong>%s</strong> %s', esc_html( $name ), $this->row_actions( $actions ) );
	}

	/**
	 * Display message for no items.
	 */
	public function no_items(): void {
		esc_html_e( 'No guardians found yet.', 'bb-groomflow' );
	}

	/**
	 * Format datetime.
	 *
	 * @param string|null $datetime Datetime string.
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
