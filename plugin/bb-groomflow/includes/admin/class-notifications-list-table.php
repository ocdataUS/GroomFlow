<?php
/**
 * Notifications list table.
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
 * List table for notification templates.
 */
class Notifications_List_Table extends WP_List_Table {
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
				'singular' => 'notification',
				'plural'   => 'notifications',
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

		$notifications_table = $tables['notifications'];

		/* phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared */
		$total_items = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$notifications_table}" );

		/* phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching */
		$query = sprintf(
			'SELECT id, name, channel, subject, updated_at FROM %s ORDER BY updated_at DESC LIMIT %d OFFSET %d',
			$notifications_table,
			$per_page,
			$offset
		);

		$items = $wpdb->get_results( $query, ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared

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
	 *
	 * @return array<string,string>
	 */
	public function get_columns(): array {
		return array(
			'name'    => __( 'Template', 'bb-groomflow' ),
			'channel' => __( 'Channel', 'bb-groomflow' ),
			'subject' => __( 'Subject', 'bb-groomflow' ),
			'updated' => __( 'Last Updated', 'bb-groomflow' ),
			'actions' => __( 'Actions', 'bb-groomflow' ),
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
			case 'name':
				return $this->column_name( $item );
			case 'channel':
				return esc_html( ucfirst( $item['channel'] ?? 'email' ) );
			case 'subject':
				return esc_html( $item['subject'] );
			case 'updated':
				return esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $item['updated_at'] ?? '' ) );
			case 'actions':
				return $this->column_actions( $item );
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
		return sprintf( '<strong>%s</strong>', esc_html( $item['name'] ) );
	}

	/**
	 * Actions column output.
	 *
	 * @param array $item Row data.
	 * @return string
	 */
	protected function column_actions( array $item ): string {
		$notification_id = (int) $item['id'];

		$edit_url = add_query_arg(
			array(
				'notification_id' => $notification_id,
			),
			$this->plugin->admin_url( \BBGF\Admin\Notifications_Admin::PAGE_SLUG )
		);

		$delete_url = wp_nonce_url(
			add_query_arg(
				array(
					'action'          => 'delete',
					'notification_id' => $notification_id,
				),
				$this->plugin->admin_url( \BBGF\Admin\Notifications_Admin::PAGE_SLUG )
			),
			'bbgf_delete_notification'
		);

		return sprintf(
			'<div class="bbgf-table-actions"><a class="button button-small" href="%s">%s</a> <a class="button button-small button-link-delete" href="%s" onclick="return confirm(\'%s\');">%s</a></div>',
			esc_url( $edit_url ),
			esc_html__( 'Edit', 'bb-groomflow' ),
			esc_url( $delete_url ),
			esc_js( __( 'Delete this notification template?', 'bb-groomflow' ) ),
			esc_html__( 'Delete', 'bb-groomflow' )
		);
	}

	/**
	 * Empty state.
	 */
	public function no_items(): void {
		esc_html_e( 'No notification templates found yet.', 'bb-groomflow' );
	}
}
