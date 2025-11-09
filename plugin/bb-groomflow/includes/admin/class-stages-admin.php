<?php
/**
 * Stages admin management.
 *
 * @package BB_GroomFlow
 */

namespace BBGF\Admin;

use BBGF\Plugin;
use WP_Error;

/**
 * Handles the Stages admin screen.
 */
class Stages_Admin implements Admin_Page_Interface {
	// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
	/**
	 * Page slug.
	 */
	public const PAGE_SLUG = 'bbgf-stages';

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
		$this->plugin = $plugin;
	}

	/**
	 * Register WordPress hooks for the stages admin.
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_init', array( $this, 'maybe_handle_actions' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Register submenu entry.
	 */
	public function register_menu(): void {
		add_submenu_page(
			'bbgf-dashboard',
			__( 'Stages', 'bb-groomflow' ),
			__( 'Stages', 'bb-groomflow' ),
			'bbgf_manage_views', // phpcs:ignore WordPress.WP.Capabilities.Unknown
			self::PAGE_SLUG,
			array( $this, 'render_page' )
		);
	}

	/**
	 * Potentially handle create/update/delete actions.
	 */
	public function maybe_handle_actions(): void {
		if ( ! isset( $_POST['bbgf_stage_nonce'] ) ) {
			return;
		}

		$nonce = sanitize_text_field( wp_unslash( $_POST['bbgf_stage_nonce'] ) );
		if ( ! wp_verify_nonce( $nonce, 'bbgf_manage_stage' ) ) {
			return;
		}

		if ( ! current_user_can( 'bbgf_manage_views' ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown
			return;
		}

		$post_data = wp_unslash( $_POST );

		if ( ! isset( $post_data['bbgf_stage_action'] ) ) {
			return;
		}

		$raw_action = $post_data['bbgf_stage_action'];
		$stage_id   = 0;

		if ( is_array( $raw_action ) ) {
			$stage_id = (int) key( $raw_action );
			$action   = sanitize_text_field( reset( $raw_action ) );
		} else {
			$action = sanitize_text_field( $raw_action );
		}

		$redirect = $this->get_page_url();

		switch ( $action ) {
			case 'save':
				$result = $this->handle_stage_save( $stage_id, $post_data['stage'][ $stage_id ] ?? array() );
				break;
			case 'bulk-save':
				$result = $this->handle_stage_bulk_save( $post_data['stage'] ?? array() );
				break;
			case 'create':
				$result = $this->handle_stage_create( $post_data['new_stage'] ?? array() );
				break;
			case 'delete':
				$result = $this->handle_stage_delete( $stage_id );
				break;
			default:
				$result = new WP_Error( 'bbgf_stage_unknown_action', __( 'Unknown stage action.', 'bb-groomflow' ) );
				break;
		}

		if ( is_wp_error( $result ) ) {
			$redirect = add_query_arg(
				array(
					'bbgf_stage_message' => $result->get_error_code(),
				),
				$redirect
			);
		} else {
			$redirect = add_query_arg(
				array(
					'bbgf_stage_message' => $result,
				),
				$redirect
			);
		}

		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_assets( string $hook ): void {
		if ( false === strpos( $hook, self::PAGE_SLUG ) ) {
			return;
		}

		wp_enqueue_style(
			'bbgf-admin',
			BBGF_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			BBGF_VERSION
		);

		wp_enqueue_script(
			'bbgf-admin',
			BBGF_PLUGIN_URL . 'assets/js/admin.js',
			array( 'jquery', 'wp-i18n', 'wp-color-picker' ),
			BBGF_VERSION,
			true
		);
	}

	/**
	 * Render the stages admin page.
	 */
	public function render_page(): void {
		if ( ! current_user_can( 'bbgf_manage_views' ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown
			wp_die( esc_html__( 'You do not have permission to manage stages.', 'bb-groomflow' ) );
		}

		$wpdb   = $this->plugin->get_wpdb();
		$tables = $this->plugin->get_table_names();

		$stage_table  = $tables['stages'];
		$stages_query = sprintf(
			'SELECT * FROM %s WHERE 1 = %%d ORDER BY sort_order ASC, label ASC',
			$stage_table
		);
		$stages       = $wpdb->get_results(
			$wpdb->prepare( $stages_query, 1 ),
			ARRAY_A
		); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared

		$usage_map = $this->get_stage_usage_map( wp_list_pluck( $stages, 'stage_key' ) );
		$message   = isset( $_GET['bbgf_stage_message'] ) ? sanitize_text_field( wp_unslash( $_GET['bbgf_stage_message'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		include __DIR__ . '/views/stages-page.php';
	}

	/**
	 * Process stage creation.
	 *
	 * @param array $data Submitted data.
	 * @return string|WP_Error
	 */
	private function handle_stage_create( array $data ) {
		$wpdb   = $this->plugin->get_wpdb();
		$tables = $this->plugin->get_table_names();

		$stage_key = sanitize_title( $data['stage_key'] ?? '' );
		$label     = sanitize_text_field( $data['label'] ?? '' );

		if ( '' === $stage_key || '' === $label ) {
			return new WP_Error( 'bbgf_stage_missing_fields', __( 'Stage key and label are required.', 'bb-groomflow' ) );
		}

		$duplicate_check = sprintf(
			'SELECT COUNT(*) FROM %s WHERE stage_key = %%s',
			$tables['stages']
		);
		$existing        = (int) $wpdb->get_var(
			$wpdb->prepare( $duplicate_check, $stage_key )
		); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
		if ( $existing > 0 ) {
			return new WP_Error( 'bbgf_stage_key_exists', __( 'A stage with that key already exists.', 'bb-groomflow' ) );
		}

		$now = $this->plugin->now();

		$record = array(
			'stage_key'              => $stage_key,
			'label'                  => $label,
			'description'            => sanitize_textarea_field( $data['description'] ?? '' ),
			'capacity_soft_limit'    => absint( $data['capacity_soft_limit'] ?? 0 ),
			'capacity_hard_limit'    => absint( $data['capacity_hard_limit'] ?? 0 ),
			'timer_threshold_green'  => absint( $data['timer_threshold_green'] ?? 0 ),
			'timer_threshold_yellow' => absint( $data['timer_threshold_yellow'] ?? 0 ),
			'timer_threshold_red'    => absint( $data['timer_threshold_red'] ?? 0 ),
			'sort_order'             => absint( $data['sort_order'] ?? 0 ),
			'created_at'             => $now,
			'updated_at'             => $now,
		);

		if ( $record['capacity_hard_limit'] > 0 && $record['capacity_soft_limit'] > 0 && $record['capacity_hard_limit'] < $record['capacity_soft_limit'] ) {
			$record['capacity_hard_limit'] = $record['capacity_soft_limit'];
		}

		if ( $record['timer_threshold_yellow'] > 0 && $record['timer_threshold_yellow'] < $record['timer_threshold_green'] ) {
			$record['timer_threshold_yellow'] = $record['timer_threshold_green'];
		}

		if ( $record['timer_threshold_red'] > 0 && $record['timer_threshold_red'] < $record['timer_threshold_yellow'] ) {
			$record['timer_threshold_red'] = $record['timer_threshold_yellow'];
		}

		$wpdb->insert(
			$tables['stages'],
			$record,
			array(
				'%s',
				'%s',
				'%s',
				'%d',
				'%d',
				'%d',
				'%d',
				'%d',
				'%d',
				'%s',
				'%s',
			)
		); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

		return 'stage-created';
	}

	/**
	 * Process stage updates.
	 *
	 * @param int   $stage_id Stage ID.
	 * @param array $data     Submitted data.
	 * @return string|WP_Error
	 */
	private function handle_stage_save( int $stage_id, array $data ) {
		if ( $stage_id <= 0 ) {
			return new WP_Error( 'bbgf_stage_missing_id', __( 'Stage identifier missing.', 'bb-groomflow' ) );
		}

		$wpdb   = $this->plugin->get_wpdb();
		$tables = $this->plugin->get_table_names();

		$stage_lookup = sprintf(
			'SELECT * FROM %s WHERE id = %%d',
			$tables['stages']
		);
		$stage        = $wpdb->get_row(
			$wpdb->prepare( $stage_lookup, $stage_id ),
			ARRAY_A
		);

		if ( ! $stage ) {
			return new WP_Error( 'bbgf_stage_not_found', __( 'Stage not found.', 'bb-groomflow' ) );
		}

		$stage_key = sanitize_title( $data['stage_key'] ?? $stage['stage_key'] );
		$label     = sanitize_text_field( $data['label'] ?? $stage['label'] );

		if ( '' === $stage_key || '' === $label ) {
			return new WP_Error( 'bbgf_stage_missing_fields', __( 'Stage key and label are required.', 'bb-groomflow' ) );
		}

		$existing_query = sprintf(
			'SELECT id FROM %s WHERE stage_key = %%s AND id <> %%d',
			$tables['stages']
		);
		$existing       = $wpdb->get_var(
			$wpdb->prepare( $existing_query, $stage_key, $stage_id )
		);

		if ( $existing ) {
			return new WP_Error( 'bbgf_stage_key_exists', __( 'Another stage already uses that key.', 'bb-groomflow' ) );
		}

		$record = array(
			'stage_key'              => $stage_key,
			'label'                  => $label,
			'description'            => sanitize_textarea_field( $data['description'] ?? $stage['description'] ?? '' ),
			'capacity_soft_limit'    => absint( $data['capacity_soft_limit'] ?? $stage['capacity_soft_limit'] ?? 0 ),
			'capacity_hard_limit'    => absint( $data['capacity_hard_limit'] ?? $stage['capacity_hard_limit'] ?? 0 ),
			'timer_threshold_green'  => absint( $data['timer_threshold_green'] ?? $stage['timer_threshold_green'] ?? 0 ),
			'timer_threshold_yellow' => absint( $data['timer_threshold_yellow'] ?? $stage['timer_threshold_yellow'] ?? 0 ),
			'timer_threshold_red'    => absint( $data['timer_threshold_red'] ?? $stage['timer_threshold_red'] ?? 0 ),
			'sort_order'             => absint( $data['sort_order'] ?? $stage['sort_order'] ?? 0 ),
			'updated_at'             => $this->plugin->now(),
		);

		if ( $record['capacity_hard_limit'] > 0 && $record['capacity_soft_limit'] > 0 && $record['capacity_hard_limit'] < $record['capacity_soft_limit'] ) {
			$record['capacity_hard_limit'] = $record['capacity_soft_limit'];
		}

		if ( $record['timer_threshold_yellow'] > 0 && $record['timer_threshold_yellow'] < $record['timer_threshold_green'] ) {
			$record['timer_threshold_yellow'] = $record['timer_threshold_green'];
		}

		if ( $record['timer_threshold_red'] > 0 && $record['timer_threshold_red'] < $record['timer_threshold_yellow'] ) {
			$record['timer_threshold_red'] = $record['timer_threshold_yellow'];
		}

		$wpdb->update(
			$tables['stages'],
			$record,
			array( 'id' => $stage_id ),
			array(
				'%s',
				'%s',
				'%s',
				'%d',
				'%d',
				'%d',
				'%d',
				'%d',
				'%d',
				'%s',
			),
			array( '%d' )
		); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

		$this->sync_stage_to_views( $stage['stage_key'], $record );

		if ( $stage['stage_key'] !== $stage_key ) {
			$this->sync_stage_key_change( $stage['stage_key'], $stage_key );
		}

		return 'stage-updated';
	}

	/**
	 * Process bulk stage updates.
	 *
	 * @param array $stages Posted stage data keyed by ID.
	 * @return string|WP_Error
	 */
	private function handle_stage_bulk_save( array $stages ) {
		if ( empty( $stages ) ) {
			return 'stage-bulk-saved';
		}

		foreach ( $stages as $stage_id => $stage_data ) {
			$stage_id = (int) $stage_id;
			if ( $stage_id <= 0 || ! is_array( $stage_data ) ) {
				continue;
			}

			$result = $this->handle_stage_save( $stage_id, $stage_data );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
		}

		return 'stage-bulk-saved';
	}

	/**
	 * Process stage delete.
	 *
	 * @param int $stage_id Stage ID.
	 * @return string|WP_Error
	 */
	private function handle_stage_delete( int $stage_id ) {
		if ( $stage_id <= 0 ) {
			return new WP_Error( 'bbgf_stage_missing_id', __( 'Stage identifier missing.', 'bb-groomflow' ) );
		}

		$wpdb   = $this->plugin->get_wpdb();
		$tables = $this->plugin->get_table_names();

		$stage = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$tables['stages']} WHERE id = %d", $stage_id ), // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			ARRAY_A
		);

		if ( ! $stage ) {
			return new WP_Error( 'bbgf_stage_not_found', __( 'Stage not found.', 'bb-groomflow' ) );
		}

		$stage_key = $stage['stage_key'];

		$usage       = $this->get_stage_usage_map( array( $stage_key ) );
		$views_using = $usage[ $stage_key ]['views'] ?? array();

		$removal_plan = array();
		if ( ! empty( $views_using ) ) {
			$plan = $this->plan_stage_removal( $stage_key, $views_using );
			if ( is_wp_error( $plan ) ) {
				return $plan;
			}
			$removal_plan = $plan;
		}

		$wpdb->delete( $tables['stages'], array( 'id' => $stage_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

		if ( ! empty( $removal_plan ) ) {
			$this->execute_stage_removal_plan( $stage_key, $removal_plan );
		}

		return 'stage-deleted';
	}

	/**
	 * Update view stage rows when canonical stage changes.
	 *
	 * @param string $old_stage_key Original stage key.
	 * @param array  $stage_record  Updated record.
	 */
	private function sync_stage_to_views( string $old_stage_key, array $stage_record ): void {
		$wpdb   = $this->plugin->get_wpdb();
		$tables = $this->plugin->get_table_names();

		$wpdb->update(
			$tables['view_stages'],
			array(
				'label'                  => $stage_record['label'],
				'capacity_soft_limit'    => $stage_record['capacity_soft_limit'],
				'capacity_hard_limit'    => $stage_record['capacity_hard_limit'],
				'timer_threshold_green'  => $stage_record['timer_threshold_green'],
				'timer_threshold_yellow' => $stage_record['timer_threshold_yellow'],
				'timer_threshold_red'    => $stage_record['timer_threshold_red'],
			),
			array( 'stage_key' => $old_stage_key ),
			array( '%s', '%d', '%d', '%d', '%d', '%d' ),
			array( '%s' )
		); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
	}

	/**
	 * Update view stages and visits when the stage key changes.
	 *
	 * @param string $old_stage_key Original key.
	 * @param string $new_stage_key New key.
	 */
	private function sync_stage_key_change( string $old_stage_key, string $new_stage_key ): void {
		$wpdb   = $this->plugin->get_wpdb();
		$tables = $this->plugin->get_table_names();

		$wpdb->update(
			$tables['view_stages'],
			array( 'stage_key' => $new_stage_key ),
			array( 'stage_key' => $old_stage_key ),
			array( '%s' ),
			array( '%s' )
		); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

		$wpdb->update(
			$tables['visits'],
			array( 'current_stage' => $new_stage_key ),
			array( 'current_stage' => $old_stage_key ),
			array( '%s' ),
			array( '%s' )
		); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

		$wpdb->update(
			$tables['stage_history'],
			array( 'stage_key' => $new_stage_key ),
			array( 'stage_key' => $old_stage_key ),
			array( '%s' ),
			array( '%s' )
		); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
	}

	/**
	 * Build a stage removal plan ensuring each affected view has a fallback.
	 *
	 * @param string $stage_key Stage key being removed.
	 * @param array  $views     Views using the stage.
	 * @return array<int,array<string,mixed>>|WP_Error
	 */
	private function plan_stage_removal( string $stage_key, array $views ) {
		$wpdb   = $this->plugin->get_wpdb();
		$tables = $this->plugin->get_table_names();

		$plan = array();

		foreach ( $views as $view ) {
			$view_id = (int) $view['view_id'];
			if ( $view_id <= 0 ) {
				continue;
			}

			$view_stage_query = sprintf(
				'SELECT stage_key, sort_order FROM %s WHERE view_id = %%d ORDER BY sort_order ASC',
				$tables['view_stages']
			);
			$rows             = $wpdb->get_results(
				$wpdb->prepare( $view_stage_query, $view_id ),
				ARRAY_A
			);

			$fallback = $this->determine_fallback_stage_key( $rows, $stage_key );
			if ( ! $fallback ) {
				return new WP_Error(
					'bbgf_stage_delete_blocked',
					__( 'Add a replacement stage to affected views before removing this one.', 'bb-groomflow' )
				);
			}

			$plan[ $view_id ] = array(
				'fallback' => $fallback,
				'rows'     => $rows,
			);
		}

		return $plan;
	}

	/**
	 * Execute a stage removal plan.
	 *
	 * @param string $stage_key Stage key.
	 * @param array  $plan      Plan built by plan_stage_removal().
	 */
	private function execute_stage_removal_plan( string $stage_key, array $plan ): void {
		$wpdb   = $this->plugin->get_wpdb();
		$tables = $this->plugin->get_table_names();

		foreach ( $plan as $view_id => $details ) {
			$fallback = $details['fallback'];

			$wpdb->update(
				$tables['visits'],
				array( 'current_stage' => $fallback ),
				array(
					'view_id'       => $view_id,
					'current_stage' => $stage_key,
				),
				array( '%s' ),
				array( '%d', '%s' )
			); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

			$wpdb->delete(
				$tables['view_stages'],
				array(
					'view_id'   => $view_id,
					'stage_key' => $stage_key,
				),
				array( '%d', '%s' )
			); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

			$this->resequence_view_stages( $view_id );
		}
	}

	/**
	 * Determine fallback stage key for visit reassignment.
	 *
	 * @param array  $rows      Sorted stage rows.
	 * @param string $stage_key Stage being removed.
	 * @return string|null
	 */
	private function determine_fallback_stage_key( array $rows, string $stage_key ): ?string {
		$total = count( $rows );
		if ( $total <= 1 ) {
			return null;
		}

		foreach ( $rows as $index => $row ) {
			if ( $row['stage_key'] === $stage_key ) {
				if ( $index > 0 ) {
					return $rows[ $index - 1 ]['stage_key'];
				}

				if ( $index + 1 < $total ) {
					return $rows[ $index + 1 ]['stage_key'];
				}
			}
		}

		return null;
	}

	/**
	 * Resequence sort order after removal.
	 *
	 * @param int $view_id View identifier.
	 */
	private function resequence_view_stages( int $view_id ): void {
		$wpdb   = $this->plugin->get_wpdb();
		$tables = $this->plugin->get_table_names();

		$view_stage_query = sprintf(
			'SELECT stage_key FROM %s WHERE view_id = %%d ORDER BY sort_order ASC',
			$tables['view_stages']
		);
		$rows             = $wpdb->get_results(
			$wpdb->prepare( $view_stage_query, $view_id ),
			ARRAY_A
		);

		$order = 1;
		foreach ( $rows as $row ) {
			$wpdb->update(
				$tables['view_stages'],
				array( 'sort_order' => $order ),
				array(
					'view_id'   => $view_id,
					'stage_key' => $row['stage_key'],
				),
				array( '%d' ),
				array( '%d', '%s' )
			); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			++$order;
		}
	}

	/**
	 * Fetch usage information for stage keys.
	 *
	 * @param array $stage_keys Stage keys to inspect.
	 * @return array<string,array<string,mixed>>
	 */
	private function get_stage_usage_map( array $stage_keys ): array {
		$stage_keys = array_filter( array_map( 'sanitize_title', $stage_keys ) );
		if ( empty( $stage_keys ) ) {
			return array();
		}

		$wpdb   = $this->plugin->get_wpdb();
		$tables = $this->plugin->get_table_names();

		$placeholders   = implode( ',', array_fill( 0, count( $stage_keys ), '%s' ) );
		$query_template = sprintf(
			'SELECT vs.stage_key, vs.view_id, v.name
			FROM %1$s AS vs
			INNER JOIN %2$s AS v ON vs.view_id = v.id
			WHERE vs.stage_key IN (%3$s)
			ORDER BY v.name ASC',
			$tables['view_stages'],
			$tables['views'],
			$placeholders
		);

		$query   = $wpdb->prepare( $query_template, ...$stage_keys );
		$results = $wpdb->get_results( $query, ARRAY_A );
		$usage   = array();

		foreach ( $stage_keys as $key ) {
			$usage[ $key ] = array(
				'views' => array(),
			);
		}

		foreach ( $results as $row ) {
			$key = $row['stage_key'];
			if ( isset( $usage[ $key ] ) ) {
				$usage[ $key ]['views'][] = array(
					'view_id' => (int) $row['view_id'],
					'name'    => $row['name'],
				);
			}
		}

		return $usage;
	}

	/**
	 * Generate admin URL for stages page.
	 *
	 * @return string
	 */
	private function get_page_url(): string {
		return $this->plugin->admin_url( self::PAGE_SLUG );
	}

	// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
}
