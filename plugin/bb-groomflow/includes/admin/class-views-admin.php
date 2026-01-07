<?php
/**
 * Views admin management.
 *
 * @package BB_GroomFlow
 */

namespace BBGF\Admin;

use BBGF\Bootstrap\Admin_Menu_Service;
use BBGF\Plugin;
use WP_Error;

/**
 * Handles the Views admin screen.
 */
class Views_Admin implements Admin_Page_Interface {
	// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
	/**
	 * Page slug.
	 */
	public const PAGE_SLUG = 'bbgf-views';

	/**
	 * Allowed view types.
	 *
	 * @var string[]
	 */
	public const VIEW_TYPES = array( 'internal', 'lobby', 'kiosk' );

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
	 * Register hooks.
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_init', array( $this, 'maybe_handle_form_submission' ) );
		add_action( 'admin_init', array( $this, 'maybe_handle_delete' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Register submenu page.
	 */
	public function register_menu(): void {
		add_submenu_page(
			Admin_Menu_Service::MENU_SLUG,
			__( 'Views', 'bb-groomflow' ),
			__( 'Views', 'bb-groomflow' ),
			'bbgf_manage_views',
			self::PAGE_SLUG,
			array( $this, 'render_page' ),
			12
		);
	}

	/**
	 * Handle create/update submissions.
	 */
	public function maybe_handle_form_submission(): void {
		if ( ! isset( $_POST['bbgf_view_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['bbgf_view_nonce'] ) ), 'bbgf_save_view' ) ) {
			return;
		}

		if ( ! current_user_can( 'bbgf_manage_views' ) ) {
			return;
		}

		$post_data = wp_unslash( $_POST );

		$view_id = isset( $post_data['view_id'] ) ? absint( $post_data['view_id'] ) : 0;

		$name = isset( $post_data['name'] ) ? sanitize_text_field( $post_data['name'] ) : '';
		if ( '' === $name ) {
			wp_safe_redirect( add_query_arg( 'bbgf_message', 'view-empty-name', $this->get_page_url() ) );
			exit;
		}

		$type = isset( $post_data['type'] ) ? sanitize_text_field( $post_data['type'] ) : 'internal';
		if ( ! in_array( $type, self::VIEW_TYPES, true ) ) {
			$type = 'internal';
		}

		$allow_switcher = isset( $post_data['allow_switcher'] ) ? 1 : 0;
		if ( 'internal' !== $type ) {
			$allow_switcher = 0;
		}

		$refresh_interval = isset( $post_data['refresh_interval'] ) ? absint( $post_data['refresh_interval'] ) : 60;
		if ( $refresh_interval < 15 ) {
			$refresh_interval = 15;
		}

		$show_guardian = isset( $post_data['show_guardian'] ) ? 1 : 0;

		$stage_result = $this->prepare_stage_records( $post_data['stages'] ?? array() );
		if ( is_wp_error( $stage_result ) ) {
			wp_safe_redirect( add_query_arg( 'bbgf_message', $stage_result->get_error_code(), $this->get_page_url() ) );
			exit;
		}

		$stages = $stage_result;
		if ( empty( $stages ) ) {
			wp_safe_redirect( add_query_arg( 'bbgf_message', 'view-empty-stages', $this->get_page_url() ) );
			exit;
		}

		$wpdb        = $this->plugin->get_wpdb();
		$tables      = $this->plugin->get_table_names();
		$now         = $this->plugin->now();
		$message     = 'view-created';
		$view_record = array(
			'name'             => $name,
			'type'             => $type,
			'allow_switcher'   => $allow_switcher,
			'refresh_interval' => $refresh_interval,
			'show_guardian'    => $show_guardian,
			'settings'         => wp_json_encode( $this->sanitize_settings_input( $post_data['settings'] ?? array() ) ),
			'updated_at'       => $now,
		);

		$existing_record = null;
		if ( $view_id > 0 ) {
			$existing_record = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->prepare( "SELECT public_token_hash FROM {$tables['views']} WHERE id = %d", $view_id ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared
				ARRAY_A
			);
		}

		$view_record['public_token_hash'] = $existing_record['public_token_hash'] ?? '';
		$view_record['slug']              = $this->plugin->unique_slug( $view_record['name'], $tables['views'], 'slug', $view_id );

		$formats = array(
			'%s', // name.
			'%s', // type.
			'%d', // allow_switcher.
			'%d', // refresh_interval.
			'%d', // show_guardian.
			'%s', // settings.
			'%s', // public_token_hash.
			'%s', // slug.
			'%s', // updated_at.
		);

		if ( $view_id > 0 ) {
			$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
				$tables['views'],
				$view_record,
				array( 'id' => $view_id ),
				$formats,
				array( '%d' )
			);
			$message = 'view-updated';
		} else {
			$view_record['created_at'] = $now;
			$insert_formats            = $formats;
			$insert_formats[]          = '%s';

			$wpdb->insert( $tables['views'], $view_record, $insert_formats ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$view_id = (int) $wpdb->insert_id;
		}

		if ( $view_id > 0 ) {
			$wpdb->delete( $tables['view_stages'], array( 'view_id' => $view_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

			$sort_position = 1;
			foreach ( $stages as $stage ) {
				$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
					$tables['view_stages'],
					array(
						'view_id'                => $view_id,
						'stage_key'              => $stage['stage_key'],
						'label'                  => $stage['label'],
						'sort_order'             => $sort_position++,
						'capacity_soft_limit'    => $stage['capacity_soft_limit'],
						'capacity_hard_limit'    => $stage['capacity_hard_limit'],
						'timer_threshold_green'  => $stage['timer_threshold_green'],
						'timer_threshold_yellow' => $stage['timer_threshold_yellow'],
						'timer_threshold_red'    => $stage['timer_threshold_red'],
					),
					array( '%d', '%s', '%s', '%d', '%d', '%d', '%d', '%d', '%d' )
				);
			}
		}

		$this->plugin->visit_service()->flush_cache();

		wp_safe_redirect( add_query_arg( 'bbgf_message', $message, $this->get_page_url() ) );
		exit;
	}

	/**
	 * Handle deleting a view.
	 */
	public function maybe_handle_delete(): void {
		if ( ! isset( $_GET['page'], $_GET['action'] ) || self::PAGE_SLUG !== sanitize_text_field( wp_unslash( $_GET['page'] ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		$action = sanitize_text_field( wp_unslash( $_GET['action'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( 'delete' !== $action || ! isset( $_GET['_wpnonce'], $_GET['view_id'] ) ) {
			return;
		}

		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'bbgf_delete_view' ) ) {
			return;
		}

		if ( ! current_user_can( 'bbgf_manage_views' ) ) {
			return;
		}

		$view_id = absint( $_GET['view_id'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( $view_id <= 0 ) {
			return;
		}

		$wpdb   = $this->plugin->get_wpdb();
		$tables = $this->plugin->get_table_names();

		$wpdb->delete( $tables['views'], array( 'id' => $view_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->delete( $tables['view_stages'], array( 'view_id' => $view_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

		$this->plugin->visit_service()->flush_cache();

		wp_safe_redirect(
			add_query_arg(
				'bbgf_message',
				'view-deleted',
				remove_query_arg( array( 'action', 'view_id', '_wpnonce' ), $this->get_page_url() )
			)
		);
		exit;
	}

	/**
	 * Render admin page.
	 */
	public function render_page(): void {
		if ( ! current_user_can( 'bbgf_manage_views' ) ) {
			wp_die( esc_html__( 'You do not have permission to manage views.', 'bb-groomflow' ) );
		}

		$wpdb          = $this->plugin->get_wpdb();
		$tables        = $this->plugin->get_table_names();
		$current_view  = null;
		$selected_rows = array();
		$stage_library = $this->get_stage_library();
		$selected_ids  = array();

		if ( isset( $_GET['view_id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$view_id = absint( $_GET['view_id'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( $view_id > 0 ) {
				$current_view = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
					$wpdb->prepare( "SELECT * FROM {$tables['views']} WHERE id = %d", $view_id ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared
					ARRAY_A
				);

				if ( $current_view ) {
					$raw_rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
						$wpdb->prepare( "SELECT * FROM {$tables['view_stages']} WHERE view_id = %d ORDER BY sort_order ASC", $view_id ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared
						ARRAY_A
					);

					foreach ( $raw_rows as $row ) {
						$stage_key = $row['stage_key'];
						$stage_id  = $this->find_stage_id_by_key( $stage_library, $stage_key );

						if ( $stage_id ) {
							$selected_ids[]  = $stage_id;
							$selected_rows[] = $this->build_selected_stage_row( $stage_library[ $stage_id ], false );
						} else {
							$selected_rows[] = $this->build_missing_stage_row( $row );
						}
					}
				}
			}
		}

		if ( empty( $selected_rows ) && ! empty( $stage_library ) ) {
			$preselect_ids = array_slice( array_keys( $stage_library ), 0, min( 4, count( $stage_library ) ) );
			foreach ( $preselect_ids as $stage_id ) {
				$selected_ids[]  = $stage_id;
				$selected_rows[] = $this->build_selected_stage_row( $stage_library[ $stage_id ], false );
			}
		}

		$message = isset( $_GET['bbgf_message'] ) ? sanitize_text_field( wp_unslash( $_GET['bbgf_message'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$list = new Views_List_Table( $this->plugin );
		$list->prepare_items();

		$available_stages   = $stage_library;
		$selected_stage_ids = $selected_ids;
		$selected_stages    = $selected_rows;

		include __DIR__ . '/views/views-page.php';
	}

	/**
	 * Enqueue admin assets for the views screen.
	 *
	 * @param string $hook Current admin hook.
	 */
	public function enqueue_assets( string $hook ): void {
		if ( false === strpos( $hook, 'bbgf-views' ) ) {
			return;
		}

		wp_enqueue_style( 'wp-color-picker' );
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
	 * Helper to get admin URL.
	 */
	private function get_page_url(): string {
		return admin_url( 'admin.php?page=' . self::PAGE_SLUG );
	}

	/**
	 * Convert submitted stage IDs into view stage records.
	 *
	 * @param mixed $input Raw stage input.
	 * @return array<int,array<string,int|string>>|WP_Error
	 */
	private function prepare_stage_records( $input ) {
		if ( ! is_array( $input ) ) {
			return array();
		}

		$stage_ids = array_values( array_filter( array_unique( array_map( 'absint', $input ) ) ) );
		if ( empty( $stage_ids ) ) {
			return array();
		}

		$wpdb   = $this->plugin->get_wpdb();
		$tables = $this->plugin->get_table_names();

		$placeholders = implode( ',', array_fill( 0, count( $stage_ids ), '%d' ) );
		$sql          = "SELECT * FROM {$tables['stages']} WHERE id IN ({$placeholders})";
		$prepared     = $wpdb->prepare( $sql, ...$stage_ids ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$rows = $wpdb->get_results( $prepared, ARRAY_A );
		if ( empty( $rows ) ) {
			return new WP_Error( 'view-stages-missing', __( 'The selected stages could not be found. Refresh and try again.', 'bb-groomflow' ) );
		}

		$stage_map = array();
		foreach ( $rows as $row ) {
			$stage_map[ (int) $row['id'] ] = $row;
		}

		$missing_ids = array_diff( $stage_ids, array_keys( $stage_map ) );
		if ( ! empty( $missing_ids ) ) {
			return new WP_Error( 'view-stages-missing', __( 'One or more selected stages are no longer available. Refresh the page and try again.', 'bb-groomflow' ) );
		}

		$records  = array();
		$position = 1;

		foreach ( $stage_ids as $stage_id ) {
			$stage = $stage_map[ $stage_id ];

			$records[] = array(
				'stage_key'              => sanitize_title( $stage['stage_key'] ?? '' ),
				'label'                  => sanitize_text_field( $stage['label'] ?? '' ),
				'sort_order'             => $position++,
				'capacity_soft_limit'    => (int) ( $stage['capacity_soft_limit'] ?? 0 ),
				'capacity_hard_limit'    => (int) ( $stage['capacity_hard_limit'] ?? 0 ),
				'timer_threshold_green'  => (int) ( $stage['timer_threshold_green'] ?? 0 ),
				'timer_threshold_yellow' => (int) ( $stage['timer_threshold_yellow'] ?? 0 ),
				'timer_threshold_red'    => (int) ( $stage['timer_threshold_red'] ?? 0 ),
			);
		}

		return $records;
	}

	/**
	 * Sanitize nested settings input.
	 *
	 * @param mixed $input Raw input.
	 * @return array<string,mixed>
	 */
	private function sanitize_settings_input( $input ): array {
		if ( ! is_array( $input ) ) {
			return array();
		}

		$settings = array();

		if ( isset( $input['accent_color'] ) ) {
			$color = sanitize_hex_color( (string) $input['accent_color'] );
			if ( $color ) {
				$settings['accent_color'] = $color;
			}
		}

		if ( isset( $input['background_color'] ) ) {
			$color = sanitize_hex_color( (string) $input['background_color'] );
			if ( $color ) {
				$settings['background_color'] = $color;
			}
		}

		return $settings;
	}

	/**
	 * Retrieve all canonical stages keyed by ID.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private function get_stage_library(): array {
		$wpdb   = $this->plugin->get_wpdb();
		$tables = $this->plugin->get_table_names();

		$sql  = "SELECT id, stage_key, label, description, capacity_soft_limit, capacity_hard_limit, timer_threshold_green, timer_threshold_yellow, timer_threshold_red, sort_order
		FROM {$tables['stages']}
		ORDER BY sort_order ASC, label ASC";
		$rows = $wpdb->get_results(
			$sql,
			ARRAY_A
		); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$library = array();
		foreach ( $rows as $row ) {
			$library[ (int) $row['id'] ] = $row;
		}

		return $library;
	}

	/**
	 * Locate a stage ID by its key.
	 *
	 * @param array  $library   Stage library keyed by ID.
	 * @param string $stage_key Stage key.
	 * @return int
	 */
	private function find_stage_id_by_key( array $library, string $stage_key ): int {
		foreach ( $library as $id => $stage ) {
			if ( $stage['stage_key'] === $stage_key ) {
				return (int) $id;
			}
		}

		return 0;
	}

	/**
	 * Build a display row for a selected stage.
	 *
	 * @param array $stage    Canonical stage definition.
	 * @param bool  $missing  Whether the stage is missing from the catalog.
	 * @return array<string,mixed>
	 */
	private function build_selected_stage_row( array $stage, bool $missing ): array {
		return array(
			'id'                     => (int) $stage['id'],
			'stage_key'              => (string) $stage['stage_key'],
			'label'                  => (string) $stage['label'],
			'description'            => (string) ( $stage['description'] ?? '' ),
			'capacity_soft_limit'    => (int) ( $stage['capacity_soft_limit'] ?? 0 ),
			'capacity_hard_limit'    => (int) ( $stage['capacity_hard_limit'] ?? 0 ),
			'timer_threshold_green'  => (int) ( $stage['timer_threshold_green'] ?? 0 ),
			'timer_threshold_yellow' => (int) ( $stage['timer_threshold_yellow'] ?? 0 ),
			'timer_threshold_red'    => (int) ( $stage['timer_threshold_red'] ?? 0 ),
			'missing'                => $missing,
		);
	}

	/**
	 * Build a placeholder row for an orphaned stage reference.
	 *
	 * @param array $row View stage row.
	 * @return array<string,mixed>
	 */
	private function build_missing_stage_row( array $row ): array {
		return array(
			'id'                     => 0,
			'stage_key'              => (string) $row['stage_key'],
			'label'                  => (string) $row['label'],
			'description'            => '',
			'capacity_soft_limit'    => (int) $row['capacity_soft_limit'],
			'capacity_hard_limit'    => (int) $row['capacity_hard_limit'],
			'timer_threshold_green'  => (int) $row['timer_threshold_green'],
			'timer_threshold_yellow' => (int) $row['timer_threshold_yellow'],
			'timer_threshold_red'    => (int) $row['timer_threshold_red'],
			'missing'                => true,
		);
	}

	// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
}
