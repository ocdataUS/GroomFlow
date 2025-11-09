<?php
/**
 * Views REST controller.
 *
 * @package BB_GroomFlow
 */

namespace BBGF\API;

use BBGF\Admin\Views_Admin;
use BBGF\Plugin;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use wpdb;

/**
 * Provides CRUD endpoints for board views.
 */
class Views_Controller extends REST_Controller {
	// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared
	/**
	 * REST route base.
	 *
	 * @var string
	 */
	protected $rest_base = 'views';

	/**
	 * Plugin instance.
	 *
	 * @var Plugin
	 */
	private Plugin $plugin;

	/**
	 * Database instance.
	 *
	 * @var wpdb
	 */
	private wpdb $wpdb;

	/**
	 * Cached table names.
	 *
	 * @var array<string,string>
	 */
	private array $tables;

	/**
	 * Cached schema definition.
	 *
	 * @var array<string,mixed>|null
	 */
	protected $schema = null;

	/**
	 * Constructor.
	 *
	 * @param Plugin $plugin Plugin instance.
	 */
	public function __construct( Plugin $plugin ) {
		parent::__construct();

		$this->plugin = $plugin;
		$this->wpdb   = $plugin->get_wpdb();
		$this->tables = $plugin->get_table_names();
	}

	/**
	 * Register REST routes.
	 */
	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => array( $this, 'get_items_permissions_check' ),
					'args'                => array(
						'search'   => array(
							'description' => __( 'Search by view name.', 'bb-groomflow' ),
							'type'        => 'string',
						),
						'page'     => array(
							'description' => __( 'Page of results to return.', 'bb-groomflow' ),
							'type'        => 'integer',
							'default'     => 1,
							'minimum'     => 1,
						),
						'per_page' => array(
							'description' => __( 'Number of items to return per page.', 'bb-groomflow' ),
							'type'        => 'integer',
							'default'     => 20,
							'minimum'     => 1,
							'maximum'     => 100,
						),
					),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_item' ),
					'permission_callback' => array( $this, 'create_item_permissions_check' ),
					'args'                => $this->get_endpoint_args_for_item_schema( true ),
				),
				'schema' => array( $this, 'get_public_item_schema' ),
			)
		);

		register_rest_route(
			$this->namespace,
			sprintf( '/%s/(?P<id>\\d+)', $this->rest_base ),
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => array( $this, 'get_item_permissions_check' ),
					'args'                => array(
						'context' => $this->get_context_param(
							array( 'default' => 'view' )
						),
					),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_item' ),
					'permission_callback' => array( $this, 'update_item_permissions_check' ),
					'args'                => $this->get_endpoint_args_for_item_schema( false ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_item' ),
					'permission_callback' => array( $this, 'delete_item_permissions_check' ),
				),
				'schema' => array( $this, 'get_public_item_schema' ),
			)
		);
	}

	/**
	 * Permission check for listing views.
	 *
	 * @param WP_REST_Request $request Request instance.
	 * @return true|WP_Error
	 */
	public function get_items_permissions_check( $request ) {
		return $this->check_capability( 'bbgf_manage_views' );
	}

	/**
	 * Permission check for retrieving a single view.
	 *
	 * @param WP_REST_Request $request Request instance.
	 * @return true|WP_Error
	 */
	public function get_item_permissions_check( $request ) {
		return $this->check_capability( 'bbgf_manage_views' );
	}

	/**
	 * Permission check for creating a view.
	 *
	 * @param WP_REST_Request $request Request instance.
	 * @return true|WP_Error
	 */
	public function create_item_permissions_check( $request ) {
		return $this->check_capability( 'bbgf_manage_views' );
	}

	/**
	 * Permission check for updating a view.
	 *
	 * @param WP_REST_Request $request Request instance.
	 * @return true|WP_Error
	 */
	public function update_item_permissions_check( $request ) {
		return $this->check_capability( 'bbgf_manage_views' );
	}

	/**
	 * Permission check for deleting a view.
	 *
	 * @param WP_REST_Request $request Request instance.
	 * @return true|WP_Error
	 */
	public function delete_item_permissions_check( $request ) {
		return $this->check_capability( 'bbgf_manage_views' );
	}

	/**
	 * Retrieve a collection of views.
	 *
	 * @param WP_REST_Request $request Request instance.
	 * @return WP_REST_Response
	 */
	public function get_items( $request ): WP_REST_Response {
		$search   = trim( (string) $request->get_param( 'search' ) );
		$page     = max( 1, (int) $request->get_param( 'page' ) );
		$per_page = (int) $request->get_param( 'per_page' );
		$per_page = max( 1, min( 100, $per_page ) );
		$offset   = ( $page - 1 ) * $per_page;

		$where = '';
		$args  = array();
		if ( '' !== $search ) {
			$where = 'WHERE name LIKE %s';
			$args  = array( '%' . $this->wpdb->esc_like( $search ) . '%' );
		}

		$count_sql = "SELECT COUNT(*) FROM {$this->tables['views']} {$where}";
		$count     = (int) $this->wpdb->get_var( $this->wpdb->prepare( $count_sql, ...$args ) );

		$sql      = "SELECT id, name, slug, type, allow_switcher, refresh_interval, show_guardian, public_token_hash, settings, created_at, updated_at FROM {$this->tables['views']} {$where} ORDER BY name ASC LIMIT %d OFFSET %d";
		$sql_args = array_merge( $args, array( $per_page, $offset ) );

		$views = $this->wpdb->get_results( $this->wpdb->prepare( $sql, ...$sql_args ), ARRAY_A );
		$views = $this->hydrate_view_stages( $views );

		$data = array();
		foreach ( $views as $view ) {
			$item   = $this->prepare_item_for_response( $view, $request );
			$data[] = $this->prepare_response_for_collection( $item );
		}

		$response = rest_ensure_response( $data );
		$response->header( 'X-WP-Total', (string) $count );
		$total_pages = (int) ceil( $count / $per_page );
		$response->header( 'X-WP-TotalPages', (string) max( 1, $total_pages ) );

		return $response;
	}

	/**
	 * Retrieve a single view.
	 *
	 * @param WP_REST_Request $request Request instance.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_item( $request ) {
		$view_id = (int) $request->get_param( 'id' );
		$view    = $this->get_view( $view_id );

		if ( null === $view ) {
			return new WP_Error(
				'bbgf_view_not_found',
				__( 'View not found.', 'bb-groomflow' ),
				array( 'status' => 404 )
			);
		}

		return $this->prepare_item_for_response( $view, $request );
	}

	/**
	 * Create a new view.
	 *
	 * @param WP_REST_Request $request Request instance.
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_item( $request ) {
		$validated = $this->validate_and_prepare_item( $request );
		if ( is_wp_error( $validated ) ) {
			return $validated;
		}

		$record = $validated['record'];
		$stages = $validated['stages'];
		$now    = $this->plugin->now();

		$record['slug']              = $this->plugin->unique_slug( $record['name'], $this->tables['views'], 'slug' );
		$record['public_token_hash'] = '';
		$record['created_at']        = $now;
		$record['updated_at']        = $now;

		$this->wpdb->insert(
			$this->tables['views'],
			$record,
			array( '%s', '%s', '%d', '%d', '%d', '%s', '%s', '%s', '%s' )
		);

		$view_id = (int) $this->wpdb->insert_id;

		$this->replace_view_stages( $view_id, $stages );

		$view = $this->get_view( $view_id );
		if ( null === $view ) {
			return new WP_Error(
				'bbgf_view_not_found',
				__( 'View not found after creation.', 'bb-groomflow' ),
				array( 'status' => 500 )
			);
		}

		$response = $this->prepare_item_for_response( $view, $request );
		$response->set_status( 201 );
		$response->header(
			'Location',
			rest_url( sprintf( '%s/%s/%d', $this->namespace, $this->rest_base, $view_id ) )
		);

		return $response;
	}

	/**
	 * Update an existing view.
	 *
	 * @param WP_REST_Request $request Request instance.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_item( $request ) {
		$view_id = (int) $request->get_param( 'id' );
		$current = $this->get_view( $view_id );

		if ( null === $current ) {
			return new WP_Error(
				'bbgf_view_not_found',
				__( 'View not found.', 'bb-groomflow' ),
				array( 'status' => 404 )
			);
		}

		$validated = $this->validate_and_prepare_item( $request, $current );
		if ( is_wp_error( $validated ) ) {
			return $validated;
		}

		$record               = $validated['record'];
		$stages               = $validated['stages'];
		$record['slug']       = $this->plugin->unique_slug( $record['name'], $this->tables['views'], 'slug', $view_id );
		$record['updated_at'] = $this->plugin->now();

		$this->wpdb->update(
			$this->tables['views'],
			$record,
			array( 'id' => $view_id ),
			array( '%s', '%s', '%d', '%d', '%d', '%s', '%s', '%s' ),
			array( '%d' )
		);

		$this->replace_view_stages( $view_id, $stages );

		$view = $this->get_view( $view_id );
		if ( null === $view ) {
			return new WP_Error(
				'bbgf_view_not_found',
				__( 'View not found after update.', 'bb-groomflow' ),
				array( 'status' => 500 )
			);
		}

		return $this->prepare_item_for_response( $view, $request );
	}

	/**
	 * Delete a view.
	 *
	 * @param WP_REST_Request $request Request instance.
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete_item( $request ) {
		$view_id = (int) $request->get_param( 'id' );
		$view    = $this->get_view( $view_id );

		if ( null === $view ) {
			return new WP_Error(
				'bbgf_view_not_found',
				__( 'View not found.', 'bb-groomflow' ),
				array( 'status' => 404 )
			);
		}

		$this->wpdb->delete( $this->tables['views'], array( 'id' => $view_id ), array( '%d' ) );
		$this->wpdb->delete( $this->tables['view_stages'], array( 'view_id' => $view_id ), array( '%d' ) );

		$response = array(
			'deleted'  => true,
			'previous' => $this->prepare_item_for_response( $view, $request )->get_data(),
		);

		return rest_ensure_response( $response );
	}

	/**
	 * Prepare output response for a single view.
	 *
	 * @param array           $item    Raw database row.
	 * @param WP_REST_Request $request Request instance.
	 * @return WP_REST_Response
	 */
	public function prepare_item_for_response( $item, $request ): WP_REST_Response {
		$settings = array();
		if ( isset( $item['settings'] ) ) {
			$decoded = json_decode( $item['settings'], true );
			if ( is_array( $decoded ) ) {
				$settings = $decoded;
			}
		}

		$stages = array();
		if ( isset( $item['stages'] ) && is_array( $item['stages'] ) ) {
			foreach ( $item['stages'] as $stage ) {
				$stages[] = array(
					'stage_key'              => (string) $stage['stage_key'],
					'label'                  => (string) $stage['label'],
					'sort_order'             => (int) $stage['sort_order'],
					'capacity_soft_limit'    => (int) $stage['capacity_soft_limit'],
					'capacity_hard_limit'    => (int) $stage['capacity_hard_limit'],
					'timer_threshold_green'  => (int) $stage['timer_threshold_green'],
					'timer_threshold_yellow' => (int) $stage['timer_threshold_yellow'],
					'timer_threshold_red'    => (int) $stage['timer_threshold_red'],
				);
			}
		}

		$data = array(
			'id'                => (int) $item['id'],
			'name'              => $item['name'],
			'slug'              => $item['slug'],
			'type'              => $item['type'],
			'allow_switcher'    => (bool) $item['allow_switcher'],
			'refresh_interval'  => (int) $item['refresh_interval'],
			'show_guardian'     => (bool) $item['show_guardian'],
			'public_token_hash' => '' !== ( $item['public_token_hash'] ?? '' ) ? $item['public_token_hash'] : null,
			'settings'          => $settings,
			'stages'            => $stages,
			'created_at'        => isset( $item['created_at'] ) ? mysql_to_rfc3339( $item['created_at'] ) : null,
			'updated_at'        => isset( $item['updated_at'] ) ? mysql_to_rfc3339( $item['updated_at'] ) : null,
		);

		return rest_ensure_response( $data );
	}

	/**
	 * Provide JSON schema for views.
	 *
	 * @return array<string,mixed>
	 */
	public function get_item_schema(): array {
		if ( null !== $this->schema ) {
			return $this->schema;
		}

		$this->schema = array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'bbgf_view',
			'type'       => 'object',
			'properties' => array(
				'id'                => array(
					'type'     => 'integer',
					'context'  => array( 'view', 'edit' ),
					'readonly' => true,
				),
				'name'              => array(
					'type'      => 'string',
					'context'   => array( 'view', 'edit' ),
					'minLength' => 1,
				),
				'slug'              => array(
					'type'     => 'string',
					'context'  => array( 'view' ),
					'readonly' => true,
				),
				'type'              => array(
					'type'    => 'string',
					'context' => array( 'view', 'edit' ),
					'enum'    => Views_Admin::VIEW_TYPES,
				),
				'allow_switcher'    => array(
					'type'    => 'boolean',
					'context' => array( 'view', 'edit' ),
				),
				'refresh_interval'  => array(
					'type'    => 'integer',
					'context' => array( 'view', 'edit' ),
					'minimum' => 15,
				),
				'show_guardian'     => array(
					'type'    => 'boolean',
					'context' => array( 'view', 'edit' ),
				),
				'public_token_hash' => array(
					'type'     => array( 'string', 'null' ),
					'context'  => array( 'view' ),
					'readonly' => true,
				),
				'settings'          => array(
					'type'    => 'object',
					'context' => array( 'view', 'edit' ),
				),
				'stages'            => array(
					'type'    => 'array',
					'context' => array( 'view', 'edit' ),
					'items'   => array(
						'type'       => 'object',
						'properties' => array(
							'stage_key'              => array(
								'type'     => 'string',
								'required' => true,
							),
							'label'                  => array(
								'type' => 'string',
							),
							'sort_order'             => array(
								'type' => 'integer',
							),
							'capacity_soft_limit'    => array(
								'type' => 'integer',
							),
							'capacity_hard_limit'    => array(
								'type' => 'integer',
							),
							'timer_threshold_green'  => array(
								'type' => 'integer',
							),
							'timer_threshold_yellow' => array(
								'type' => 'integer',
							),
							'timer_threshold_red'    => array(
								'type' => 'integer',
							),
						),
					),
				),
				'created_at'        => array(
					'type'     => array( 'string', 'null' ),
					'format'   => 'date-time',
					'context'  => array( 'view' ),
					'readonly' => true,
				),
				'updated_at'        => array(
					'type'     => array( 'string', 'null' ),
					'format'   => 'date-time',
					'context'  => array( 'view' ),
					'readonly' => true,
				),
			),
			'required'   => array( 'name', 'type' ),
		);

		return $this->schema;
	}

	/**
	 * Fetch a single view with stage assignments.
	 *
	 * @param int $view_id View ID.
	 * @return array<string,mixed>|null
	 */
	private function get_view( int $view_id ): ?array {
		if ( $view_id <= 0 ) {
			return null;
		}

		$sql = $this->wpdb->prepare(
			"SELECT id, name, slug, type, allow_switcher, refresh_interval, show_guardian, public_token_hash, settings, created_at, updated_at
			FROM {$this->tables['views']}
			WHERE id = %d",
			$view_id
		);

		$row = $this->wpdb->get_row( $sql, ARRAY_A );
		if ( ! $row ) {
			return null;
		}

		$views = array( $row );
		$views = $this->hydrate_view_stages( $views );

		return $views[0];
	}

	/**
	 * Attach stage configurations to views.
	 *
	 * @param array<int,array<string,mixed>> $views View records.
	 * @return array<int,array<string,mixed>>
	 */
	private function hydrate_view_stages( array $views ): array {
		if ( empty( $views ) ) {
			return $views;
		}

		$view_ids = array();
		foreach ( $views as $view ) {
			if ( isset( $view['id'] ) ) {
				$view_ids[] = (int) $view['id'];
			}
		}

		$view_ids = array_values( array_unique( array_filter( $view_ids ) ) );
		if ( empty( $view_ids ) ) {
			return $views;
		}

		$id_list = implode( ',', $view_ids );
		$sql     = "SELECT view_id, stage_key, label, sort_order, capacity_soft_limit, capacity_hard_limit, timer_threshold_green, timer_threshold_yellow, timer_threshold_red
			FROM {$this->tables['view_stages']}
			WHERE view_id IN ({$id_list})
			ORDER BY view_id ASC, sort_order ASC";

		$rows   = $this->wpdb->get_results( $sql, ARRAY_A );
		$stages = array();

		foreach ( $rows as $row ) {
			$view_id = (int) $row['view_id'];
			if ( ! isset( $stages[ $view_id ] ) ) {
				$stages[ $view_id ] = array();
			}

			$stages[ $view_id ][] = array(
				'stage_key'              => (string) $row['stage_key'],
				'label'                  => (string) $row['label'],
				'sort_order'             => (int) $row['sort_order'],
				'capacity_soft_limit'    => (int) $row['capacity_soft_limit'],
				'capacity_hard_limit'    => (int) $row['capacity_hard_limit'],
				'timer_threshold_green'  => (int) $row['timer_threshold_green'],
				'timer_threshold_yellow' => (int) $row['timer_threshold_yellow'],
				'timer_threshold_red'    => (int) $row['timer_threshold_red'],
			);
		}

		foreach ( $views as &$view ) {
			$view_id        = (int) $view['id'];
			$view['stages'] = $stages[ $view_id ] ?? array();
		}
		unset( $view );

		return $views;
	}

	/**
	 * Replace view stage assignments.
	 *
	 * @param int                                 $view_id View ID.
	 * @param array<int,array<string,int|string>> $stages  Stage configuration.
	 */
	private function replace_view_stages( int $view_id, array $stages ): void {
		$view_id = max( 0, $view_id );
		if ( $view_id <= 0 ) {
			return;
		}

		$this->wpdb->delete( $this->tables['view_stages'], array( 'view_id' => $view_id ), array( '%d' ) );

		if ( empty( $stages ) ) {
			return;
		}

		$sort_order = 1;
		foreach ( $stages as $stage ) {
			$this->wpdb->insert(
				$this->tables['view_stages'],
				array(
					'view_id'                => $view_id,
					'stage_key'              => $stage['stage_key'],
					'label'                  => $stage['label'],
					'sort_order'             => $sort_order++,
					'capacity_soft_limit'    => (int) $stage['capacity_soft_limit'],
					'capacity_hard_limit'    => (int) $stage['capacity_hard_limit'],
					'timer_threshold_green'  => (int) $stage['timer_threshold_green'],
					'timer_threshold_yellow' => (int) $stage['timer_threshold_yellow'],
					'timer_threshold_red'    => (int) $stage['timer_threshold_red'],
				),
				array( '%d', '%s', '%s', '%d', '%d', '%d', '%d', '%d', '%d' )
			);
		}
	}

	/**
	 * Validate and sanitise request data.
	 *
	 * @param WP_REST_Request          $request  Request instance.
	 * @param array<string,mixed>|null $existing Existing view data.
	 * @return array<string,mixed>|WP_Error
	 */
	private function validate_and_prepare_item( WP_REST_Request $request, ?array $existing = null ) {
		$is_update = is_array( $existing );
		$library   = $this->get_stage_library();

		$has_name = $request->has_param( 'name' );
		if ( $has_name ) {
			$name = trim( (string) $request->get_param( 'name' ) );
		} elseif ( $is_update ) {
			$name = (string) ( $existing['name'] ?? '' );
		} else {
			$name = '';
		}

		if ( '' === $name ) {
			return new WP_Error(
				'bbgf_view_missing_name',
				__( 'View name is required.', 'bb-groomflow' ),
				array( 'status' => 400 )
			);
		}

		$has_type = $request->has_param( 'type' );
		if ( $has_type ) {
			$type = strtolower( (string) $request->get_param( 'type' ) );
		} elseif ( $is_update ) {
			$type = strtolower( (string) ( $existing['type'] ?? 'internal' ) );
		} else {
			$type = 'internal';
		}

		if ( ! in_array( $type, Views_Admin::VIEW_TYPES, true ) ) {
			return new WP_Error(
				'bbgf_view_invalid_type',
				__( 'View type is not supported.', 'bb-groomflow' ),
				array( 'status' => 400 )
			);
		}

		$allow_switcher = $request->has_param( 'allow_switcher' )
			? (bool) $request->get_param( 'allow_switcher' )
			: (bool) ( $existing['allow_switcher'] ?? false );

		if ( 'internal' !== $type ) {
			$allow_switcher = false;
		}

		$has_refresh = $request->has_param( 'refresh_interval' );
		if ( $has_refresh ) {
			$refresh_interval = max( 15, (int) $request->get_param( 'refresh_interval' ) );
		} elseif ( $is_update ) {
			$refresh_interval = max( 15, (int) ( $existing['refresh_interval'] ?? 60 ) );
		} else {
			$refresh_interval = 60;
		}

		$show_guardian = $request->has_param( 'show_guardian' )
			? (bool) $request->get_param( 'show_guardian' )
			: (bool) ( $existing['show_guardian'] ?? false );

		$settings_param = $request->has_param( 'settings' )
			? $request->get_param( 'settings' )
			: ( $existing['settings'] ?? array() );

		$settings = $this->sanitize_settings( $settings_param );

		if ( $is_update && isset( $existing['settings'] ) && ! $request->has_param( 'settings' ) ) {
			$existing_settings = json_decode( (string) $existing['settings'], true );
			if ( is_array( $existing_settings ) ) {
				$settings = $existing_settings;
			}
		}

		$stages_param = $request->get_param( 'stages' );
		if ( $request->has_param( 'stages' ) ) {
			$stages = $this->normalise_stages_input( $stages_param, $library, $type );
			if ( is_wp_error( $stages ) ) {
				return $stages;
			}
		} elseif ( $is_update && isset( $existing['stages'] ) ) {
			$stages = array();
			foreach ( $existing['stages'] as $stage ) {
				$stages[] = array(
					'stage_key'              => (string) $stage['stage_key'],
					'label'                  => (string) $stage['label'],
					'capacity_soft_limit'    => (int) $stage['capacity_soft_limit'],
					'capacity_hard_limit'    => (int) $stage['capacity_hard_limit'],
					'timer_threshold_green'  => (int) $stage['timer_threshold_green'],
					'timer_threshold_yellow' => (int) $stage['timer_threshold_yellow'],
					'timer_threshold_red'    => (int) $stage['timer_threshold_red'],
				);
			}
		} else {
			$stages = array();
		}

		if ( empty( $stages ) ) {
			return new WP_Error(
				'bbgf_view_missing_stages',
				__( 'Assign at least one stage to the view.', 'bb-groomflow' ),
				array( 'status' => 400 )
			);
		}

		return array(
			'record' => array(
				'name'             => sanitize_text_field( $name ),
				'type'             => $type,
				'allow_switcher'   => $allow_switcher ? 1 : 0,
				'refresh_interval' => $refresh_interval,
				'show_guardian'    => $show_guardian ? 1 : 0,
				'settings'         => wp_json_encode( $settings ),
			),
			'stages' => $stages,
		);
	}

	/**
	 * Normalise stage payload into storage format.
	 *
	 * @param mixed                             $input   Raw stage input.
	 * @param array<string,array<string,mixed>> $library Stage library keyed by stage key.
	 * @param string                            $view_type View type.
	 * @return array<int,array<string,int|string>>|WP_Error
	 */
	private function normalise_stages_input( $input, array $library, string $view_type ) {
		if ( ! is_array( $input ) ) {
			return new WP_Error(
				'bbgf_view_invalid_stages',
				__( 'Stages must be provided as an array.', 'bb-groomflow' ),
				array( 'status' => 400 )
			);
		}

		if ( empty( $library ) ) {
			return new WP_Error(
				'bbgf_view_missing_stage_library',
				__( 'No stages are available to assign.', 'bb-groomflow' ),
				array( 'status' => 400 )
			);
		}

		$normalised = array();

		foreach ( $input as $entry ) {
			if ( ! is_array( $entry ) ) {
				continue;
			}

			$stage_key = sanitize_key( $entry['stage_key'] ?? '' );
			if ( '' === $stage_key ) {
				continue;
			}

			if ( ! isset( $library[ $stage_key ] ) ) {
				return new WP_Error(
					'bbgf_view_invalid_stage',
					sprintf(
						/* translators: %s: stage key */
						__( 'Stage "%s" is not available.', 'bb-groomflow' ),
						$stage_key
					),
					array( 'status' => 400 )
				);
			}

			$canonical  = $library[ $stage_key ];
			$label      = isset( $entry['label'] ) && '' !== trim( (string) $entry['label'] )
				? sanitize_text_field( (string) $entry['label'] )
				: (string) ( $canonical['label'] ?? ucfirst( $stage_key ) );
			$soft_limit = isset( $entry['capacity_soft_limit'] ) ? (int) $entry['capacity_soft_limit'] : (int) ( $canonical['capacity_soft_limit'] ?? 0 );
			$hard_limit = isset( $entry['capacity_hard_limit'] ) ? (int) $entry['capacity_hard_limit'] : (int) ( $canonical['capacity_hard_limit'] ?? 0 );
			$green      = isset( $entry['timer_threshold_green'] ) ? (int) $entry['timer_threshold_green'] : (int) ( $canonical['timer_threshold_green'] ?? 0 );
			$yellow     = isset( $entry['timer_threshold_yellow'] ) ? (int) $entry['timer_threshold_yellow'] : (int) ( $canonical['timer_threshold_yellow'] ?? 0 );
			$red        = isset( $entry['timer_threshold_red'] ) ? (int) $entry['timer_threshold_red'] : (int) ( $canonical['timer_threshold_red'] ?? 0 );

			$normalised[] = array(
				'stage_key'              => $stage_key,
				'label'                  => $label,
				'capacity_soft_limit'    => max( 0, $soft_limit ),
				'capacity_hard_limit'    => max( 0, $hard_limit ),
				'timer_threshold_green'  => max( 0, $green ),
				'timer_threshold_yellow' => max( 0, $yellow ),
				'timer_threshold_red'    => max( 0, $red ),
			);
		}

		if ( empty( $normalised ) ) {
			return new WP_Error(
				'bbgf_view_missing_stages',
				__( 'Assign at least one stage to the view.', 'bb-groomflow' ),
				array( 'status' => 400 )
			);
		}

		return $normalised;
	}

	/**
	 * Sanitize settings input.
	 *
	 * @param mixed $input Raw settings input.
	 * @return array<string,string>
	 */
	private function sanitize_settings( $input ): array {
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
	 * Retrieve the stage library keyed by stage key.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	private function get_stage_library(): array {
		$sql = "SELECT stage_key, label, capacity_soft_limit, capacity_hard_limit, timer_threshold_green, timer_threshold_yellow, timer_threshold_red
			FROM {$this->tables['stages']}";

		$rows    = $this->wpdb->get_results( $sql, ARRAY_A );
		$library = array();

		foreach ( $rows as $row ) {
			$key             = (string) $row['stage_key'];
			$library[ $key ] = $row;
		}

		return $library;
	}
	// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared
}
