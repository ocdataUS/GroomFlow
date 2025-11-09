<?php
/**
 * Flags list table implementation.
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
 * List table for behaviour flags.
 */
class Flags_List_Table extends WP_List_Table {
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
				'singular' => 'flag',
				'plural'   => 'flags',
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
		$sortable = array();

		$this->_column_headers = array( $columns, $hidden, $sortable );

		$per_page = 20;
		$current  = $this->get_pagenum();
		$offset   = ( $current - 1 ) * $per_page;

		$wpdb   = $this->plugin->get_wpdb();
		$tables = $this->plugin->get_table_names();

		/* phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared */
		$total_items = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$tables['flags']}" );
		/* phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared */
		$items = $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM {$tables['flags']} ORDER BY created_at DESC LIMIT %d OFFSET %d", $per_page, $offset ), // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			ARRAY_A
		);

		$this->items = $items;

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
			'name'        => __( 'Flag', 'bb-groomflow' ),
			'emoji'       => __( 'Emoji', 'bb-groomflow' ),
			'color'       => __( 'Color', 'bb-groomflow' ),
			'severity'    => __( 'Severity', 'bb-groomflow' ),
			'description' => __( 'Description', 'bb-groomflow' ),
		);
	}

	/**
	 * Default column output.
	 *
	 * @param array  $item        Current row.
	 * @param string $column_name Column name.
	 */
	protected function column_default( $item, $column_name ) {
		switch ( $column_name ) {
			case 'name':
				return $this->column_name( $item );
			case 'emoji':
				return esc_html( $item['emoji'] );
			case 'color':
				return sprintf( '<span class="bbgf-flag-swatch" style="background:%1$s"></span> %1$s', esc_html( $item['color'] ) );
			case 'severity':
				return esc_html( ucfirst( $item['severity'] ) );
			case 'description':
				return esc_html( $item['description'] );
			default:
				return '';
		}
	}

	/**
	 * Name column with row actions.
	 *
	 * @param array $item Row.
	 */
	protected function column_name( $item ) {
		$flag_id    = (int) $item['id'];
		$edit_url   = add_query_arg(
			array(
				'flag_id' => $flag_id,
			),
			$this->plugin->admin_url( Flags_Admin::PAGE_SLUG )
		);
		$delete_url = wp_nonce_url(
			add_query_arg(
				array(
					'action'  => 'delete',
					'flag_id' => $flag_id,
				),
				$this->plugin->admin_url( Flags_Admin::PAGE_SLUG )
			),
			'bbgf_delete_flag'
		);

		$actions           = array();
		$actions['edit']   = sprintf(
			'<a href="%s">%s</a>',
			esc_url( $edit_url ),
			esc_html__( 'Edit', 'bb-groomflow' )
		);
		$actions['delete'] = sprintf(
			'<a href="%s" class="bbgf-delete-flag" onclick="return confirm(\'%s\');">%s</a>',
			esc_url( $delete_url ),
			esc_js( __( 'Delete this flag?', 'bb-groomflow' ) ),
			esc_html__( 'Delete', 'bb-groomflow' )
		);

		return sprintf( '<strong>%s</strong> %s', esc_html( $item['name'] ), $this->row_actions( $actions ) );
	}

	/**
	 * Fallback when no items exist.
	 */
	public function no_items(): void {
		esc_html_e( 'No flags found yet.', 'bb-groomflow' );
	}
}
