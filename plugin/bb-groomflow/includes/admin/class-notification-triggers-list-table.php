<?php
/**
 * Notification triggers list table.
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
 * Displays stage-to-template trigger mappings.
 */
class Notification_Triggers_List_Table extends WP_List_Table {
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
				'singular' => 'notification_trigger',
				'plural'   => 'notification_triggers',
				'ajax'     => false,
			)
		);

		$this->plugin = $plugin;
	}

	/**
	 * Prepare items.
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

		$triggers_table = $tables['notification_triggers'];
		$notifications  = $tables['notifications'];
		$stages         = $tables['stages'];

		/* phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared */
		$total_items = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$triggers_table}" );

		$sql = sprintf(
			'SELECT t.id, t.trigger_stage, t.notification_id, t.enabled, t.recipient_type, t.recipient_email, t.updated_at,
				s.label AS stage_label,
				n.name AS notification_name
			FROM %1$s AS t
			LEFT JOIN %2$s AS s ON s.stage_key = t.trigger_stage
			LEFT JOIN %3$s AS n ON n.id = t.notification_id
			ORDER BY s.sort_order ASC, t.updated_at DESC
			LIMIT %%d OFFSET %%d',
			$triggers_table,
			$stages,
			$notifications
		); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

			$query = $wpdb->prepare( $sql, $per_page, $offset ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared

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
	 * Columns.
	 *
	 * @return array<string,string>
	 */
	public function get_columns(): array {
		return array(
			'stage'       => __( 'Stage', 'bb-groomflow' ),
			'template'    => __( 'Template', 'bb-groomflow' ),
			'recipient'   => __( 'Recipient', 'bb-groomflow' ),
			'enabled'     => __( 'Enabled', 'bb-groomflow' ),
			'last_update' => __( 'Last Updated', 'bb-groomflow' ),
			'actions'     => __( 'Actions', 'bb-groomflow' ),
		);
	}

	/**
	 * Default column output.
	 *
	 * @param array  $item Row data.
	 * @param string $column_name Column key.
	 * @return string
	 */
	protected function column_default( $item, $column_name ) {
		switch ( $column_name ) {
			case 'stage':
				return $this->column_stage( $item );
			case 'template':
				return esc_html( $item['notification_name'] ?? __( '—', 'bb-groomflow' ) );
			case 'recipient':
				return esc_html( $this->format_recipient( $item ) );
			case 'enabled':
				return ! empty( $item['enabled'] ) ? esc_html__( 'Yes', 'bb-groomflow' ) : esc_html__( 'No', 'bb-groomflow' );
			case 'last_update':
				return esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $item['updated_at'] ?? '' ) );
			case 'actions':
				return $this->column_actions( $item );
			default:
				return '';
		}
	}

	/**
	 * Format recipient description.
	 *
	 * @param array $item Row data.
	 * @return string
	 */
	private function format_recipient( array $item ): string {
		$type  = $item['recipient_type'] ?? 'guardian_primary';
		$email = trim( (string) ( $item['recipient_email'] ?? '' ) );

		switch ( $type ) {
			case 'custom_email':
				if ( '' !== $email ) {
					return sprintf( /* translators: %s: custom email address list. */ __( 'Custom: %s', 'bb-groomflow' ), $email );
				}
				return __( 'Custom recipients', 'bb-groomflow' );
			case 'guardian_primary_and_custom':
				if ( '' === $email ) {
					return __( 'Guardian email', 'bb-groomflow' );
				}

				return sprintf(
					/* translators: %s: custom email address list. */
					__( 'Guardian + %s', 'bb-groomflow' ),
					$email
				);
			case 'guardian_primary':
			default:
				return __( 'Guardian email', 'bb-groomflow' );
		}
	}

	/**
	 * Stage column with actions.
	 *
	 * @param array $item Row data.
	 * @return string
	 */
	protected function column_stage( $item ) {
		$stage_name = $item['stage_label'] ?? $item['trigger_stage'];

		return sprintf( '<strong>%s</strong>', esc_html( $stage_name ) );
	}

	/**
	 * Actions column output.
	 *
	 * @param array $item Row data.
	 * @return string
	 */
	protected function column_actions( array $item ): string {
		$trigger_id = (int) $item['id'];

		$edit_url = add_query_arg(
			array(
				'trigger_id' => $trigger_id,
			),
			$this->plugin->admin_url( \BBGF\Admin\Notification_Triggers_Admin::PAGE_SLUG )
		);

		$delete_url = wp_nonce_url(
			add_query_arg(
				array(
					'action'     => 'delete',
					'trigger_id' => $trigger_id,
				),
				$this->plugin->admin_url( \BBGF\Admin\Notification_Triggers_Admin::PAGE_SLUG )
			),
			'bbgf_delete_notification_trigger'
		);

		return sprintf(
			'<div class="bbgf-table-actions"><a class="button button-small" href="%s">%s</a> <a class="button button-small button-link-delete" href="%s" onclick="return confirm(\'%s\');">%s</a></div>',
			esc_url( $edit_url ),
			esc_html__( 'Edit', 'bb-groomflow' ),
			esc_url( $delete_url ),
			esc_js( __( 'Delete this trigger?', 'bb-groomflow' ) ),
			esc_html__( 'Delete', 'bb-groomflow' )
		);
	}

	/**
	 * Empty state message.
	 */
	public function no_items(): void {
		esc_html_e( 'No stage triggers configured yet.', 'bb-groomflow' );
	}
}
