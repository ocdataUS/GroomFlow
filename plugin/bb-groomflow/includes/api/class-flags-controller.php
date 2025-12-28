<?php
/**
 * Flags REST controller.
 *
 * @package BB_GroomFlow
 */

namespace BBGF\API;

use BBGF\Plugin;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use wpdb;

/**
 * Provides CRUD endpoints for behavior flags.
 */
class Flags_Controller extends REST_Controller {
	// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared
	/**
	 * REST route base.
	 *
	 * @var string
	 */
	protected $rest_base = 'flags';

	/**
	 * Allowed severity values.
	 *
	 * @var string[]
	 */
	private const SEVERITY = array( 'low', 'medium', 'high' );

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
							'description' => __( 'Search by flag name or description.', 'bb-groomflow' ),
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
	 * Permission check for listing flags.
	 *
	 * @param WP_REST_Request $request Request instance.
	 * @return true|WP_Error
	 */
	public function get_items_permissions_check( $request ) {
		return $this->check_capability( 'bbgf_manage_flags' );
	}

	/**
	 * Permission check for retrieving a single flag.
	 *
	 * @param WP_REST_Request $request Request instance.
	 * @return true|WP_Error
	 */
	public function get_item_permissions_check( $request ) {
		return $this->check_capability( 'bbgf_manage_flags' );
	}

	/**
	 * Permission check for creating a flag.
	 *
	 * @param WP_REST_Request $request Request instance.
	 * @return true|WP_Error
	 */
	public function create_item_permissions_check( $request ) {
		return $this->check_capability( 'bbgf_manage_flags' );
	}

	/**
	 * Permission check for updating a flag.
	 *
	 * @param WP_REST_Request $request Request instance.
	 * @return true|WP_Error
	 */
	public function update_item_permissions_check( $request ) {
		return $this->check_capability( 'bbgf_manage_flags' );
	}

	/**
	 * Permission check for deleting a flag.
	 *
	 * @param WP_REST_Request $request Request instance.
	 * @return true|WP_Error
	 */
	public function delete_item_permissions_check( $request ) {
		return $this->check_capability( 'bbgf_manage_flags' );
	}

	/**
	 * Retrieve a collection of flags.
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

		$table = $this->tables['flags'];

		if ( '' !== $search ) {
			$like = '%' . $this->wpdb->esc_like( $search ) . '%';

			$sql = $this->wpdb->prepare(
				"SELECT id, name, slug, emoji, color, severity, description, created_at, updated_at
				FROM {$table}
				WHERE name LIKE %s OR description LIKE %s
				ORDER BY name ASC
				LIMIT %d OFFSET %d",
				$like,
				$like,
				$per_page,
				$offset
			);

			$count_sql = $this->wpdb->prepare(
				"SELECT COUNT(*)
				FROM {$table}
				WHERE name LIKE %s OR description LIKE %s",
				$like,
				$like
			);
		} else {
			$sql = $this->wpdb->prepare(
				"SELECT id, name, slug, emoji, color, severity, description, created_at, updated_at
				FROM {$table}
				ORDER BY name ASC
				LIMIT %d OFFSET %d",
				$per_page,
				$offset
			);

			$count_sql = "SELECT COUNT(*) FROM {$table}";
		}

		$rows  = $this->wpdb->get_results( $sql, ARRAY_A );
		$count = (int) $this->wpdb->get_var( $count_sql );

		$data = array();
		foreach ( $rows as $row ) {
			$item   = $this->prepare_item_for_response( $row, $request );
			$data[] = $this->prepare_response_for_collection( $item );
		}

		$response = rest_ensure_response( $data );
		$response->header( 'X-WP-Total', (string) $count );
		$total_pages = (int) ceil( $count / $per_page );
		$response->header( 'X-WP-TotalPages', (string) max( 1, $total_pages ) );

		return $response;
	}

	/**
	 * Retrieve a single flag.
	 *
	 * @param WP_REST_Request $request Request instance.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_item( $request ) {
		$flag_id = (int) $request->get_param( 'id' );
		$flag    = $this->get_flag( $flag_id );

		if ( null === $flag ) {
			return new WP_Error(
				'bbgf_flag_not_found',
				__( 'Flag not found.', 'bb-groomflow' ),
				array( 'status' => 404 )
			);
		}

		return $this->prepare_item_for_response( $flag, $request );
	}

	/**
	 * Create a new flag.
	 *
	 * @param WP_REST_Request $request Request instance.
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_item( $request ) {
		$validated = $this->validate_and_prepare_item( $request );
		if ( is_wp_error( $validated ) ) {
			return $validated;
		}

		$record               = $validated;
		$record['slug']       = $this->plugin->unique_slug( $record['name'], $this->tables['flags'], 'slug' );
		$record['created_at'] = $this->plugin->now();
		$record['updated_at'] = $record['created_at'];

		$this->wpdb->insert(
			$this->tables['flags'],
			$record,
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		$flag_id = (int) $this->wpdb->insert_id;

		$flag = $this->get_flag( $flag_id );
		if ( null === $flag ) {
			return new WP_Error(
				'bbgf_flag_not_found',
				__( 'Flag not found after creation.', 'bb-groomflow' ),
				array( 'status' => 500 )
			);
		}

		$response = $this->prepare_item_for_response( $flag, $request );
		$response->set_status( 201 );
		$response->header(
			'Location',
			rest_url( sprintf( '%s/%s/%d', $this->namespace, $this->rest_base, $flag_id ) )
		);

		$this->plugin->visit_service()->flush_cache();

		return $response;
	}

	/**
	 * Update an existing flag.
	 *
	 * @param WP_REST_Request $request Request instance.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_item( $request ) {
		$flag_id = (int) $request->get_param( 'id' );
		$current = $this->get_flag( $flag_id );

		if ( null === $current ) {
			return new WP_Error(
				'bbgf_flag_not_found',
				__( 'Flag not found.', 'bb-groomflow' ),
				array( 'status' => 404 )
			);
		}

		$validated = $this->validate_and_prepare_item( $request, $current );
		if ( is_wp_error( $validated ) ) {
			return $validated;
		}

		$record               = $validated;
		$record['slug']       = $this->plugin->unique_slug( $record['name'], $this->tables['flags'], 'slug', $flag_id );
		$record['updated_at'] = $this->plugin->now();

		$this->wpdb->update(
			$this->tables['flags'],
			$record,
			array( 'id' => $flag_id ),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s' ),
			array( '%d' )
		);

		$flag = $this->get_flag( $flag_id );
		if ( null === $flag ) {
			return new WP_Error(
				'bbgf_flag_not_found',
				__( 'Flag not found after update.', 'bb-groomflow' ),
				array( 'status' => 500 )
			);
		}

		$this->plugin->visit_service()->flush_cache();

		return $this->prepare_item_for_response( $flag, $request );
	}

	/**
	 * Delete a flag.
	 *
	 * @param WP_REST_Request $request Request instance.
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete_item( $request ) {
		$flag_id = (int) $request->get_param( 'id' );
		$flag    = $this->get_flag( $flag_id );

		if ( null === $flag ) {
			return new WP_Error(
				'bbgf_flag_not_found',
				__( 'Flag not found.', 'bb-groomflow' ),
				array( 'status' => 404 )
			);
		}

		$this->wpdb->delete( $this->tables['flags'], array( 'id' => $flag_id ), array( '%d' ) );

		$this->plugin->visit_service()->flush_cache();

		$response = array(
			'deleted'  => true,
			'previous' => $this->prepare_item_for_response( $flag, $request )->get_data(),
		);

		return rest_ensure_response( $response );
	}

	/**
	 * Prepare output response for a single flag.
	 *
	 * @param array           $item    Raw database row.
	 * @param WP_REST_Request $request Request instance.
	 * @return WP_REST_Response
	 */
	public function prepare_item_for_response( $item, $request ): WP_REST_Response {
		$color       = isset( $item['color'] ) ? trim( (string) $item['color'] ) : '';
		$description = isset( $item['description'] ) ? trim( (string) $item['description'] ) : '';

		$data = array(
			'id'          => (int) $item['id'],
			'name'        => $item['name'],
			'slug'        => $item['slug'],
			'emoji'       => $item['emoji'],
			'color'       => '' !== $color ? $item['color'] : null,
			'severity'    => $item['severity'],
			'description' => '' !== $description ? $item['description'] : null,
			'created_at'  => isset( $item['created_at'] ) ? mysql_to_rfc3339( $item['created_at'] ) : null,
			'updated_at'  => isset( $item['updated_at'] ) ? mysql_to_rfc3339( $item['updated_at'] ) : null,
		);

		return rest_ensure_response( $data );
	}

	/**
	 * Provide JSON schema for flags.
	 *
	 * @return array<string,mixed>
	 */
	public function get_item_schema(): array {
		if ( null !== $this->schema ) {
			return $this->schema;
		}

		$this->schema = array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'bbgf_flag',
			'type'       => 'object',
			'properties' => array(
				'id'          => array(
					'type'     => 'integer',
					'context'  => array( 'view', 'edit' ),
					'readonly' => true,
				),
				'name'        => array(
					'type'      => 'string',
					'context'   => array( 'view', 'edit' ),
					'minLength' => 1,
				),
				'slug'        => array(
					'type'     => 'string',
					'context'  => array( 'view' ),
					'readonly' => true,
				),
				'emoji'       => array(
					'type'    => 'string',
					'context' => array( 'view', 'edit' ),
				),
				'color'       => array(
					'type'    => array( 'string', 'null' ),
					'context' => array( 'view', 'edit' ),
					'pattern' => '^#([A-Fa-f0-9]{3}){1,2}$',
				),
				'severity'    => array(
					'type'    => 'string',
					'context' => array( 'view', 'edit' ),
					'enum'    => self::SEVERITY,
				),
				'description' => array(
					'type'    => array( 'string', 'null' ),
					'context' => array( 'view', 'edit' ),
				),
				'created_at'  => array(
					'type'     => array( 'string', 'null' ),
					'format'   => 'date-time',
					'context'  => array( 'view' ),
					'readonly' => true,
				),
				'updated_at'  => array(
					'type'     => array( 'string', 'null' ),
					'format'   => 'date-time',
					'context'  => array( 'view' ),
					'readonly' => true,
				),
			),
			'required'   => array( 'name' ),
		);

		return $this->schema;
	}

	/**
	 * Fetch a flag row.
	 *
	 * @param int $flag_id Flag ID.
	 * @return array<string,mixed>|null
	 */
	private function get_flag( int $flag_id ): ?array {
		if ( $flag_id <= 0 ) {
			return null;
		}

		$sql = $this->wpdb->prepare(
			"SELECT id, name, slug, emoji, color, severity, description, created_at, updated_at
			FROM {$this->tables['flags']}
			WHERE id = %d",
			$flag_id
		);

		$row = $this->wpdb->get_row( $sql, ARRAY_A );

		return is_array( $row ) ? $row : null;
	}

	/**
	 * Validate and sanitise request data.
	 *
	 * @param WP_REST_Request          $request  Request instance.
	 * @param array<string,mixed>|null $existing Existing flag data.
	 * @return array<string,mixed>|WP_Error
	 */
	private function validate_and_prepare_item( WP_REST_Request $request, ?array $existing = null ) {
		$is_update = is_array( $existing );

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
				'bbgf_flag_missing_name',
				__( 'Flag name is required.', 'bb-groomflow' ),
				array( 'status' => 400 )
			);
		}

		$has_emoji = $request->has_param( 'emoji' );
		if ( $has_emoji ) {
			$emoji = sanitize_text_field( (string) $request->get_param( 'emoji' ) );
		} else {
			$emoji = sanitize_text_field( (string) ( $existing['emoji'] ?? '' ) );
		}

		$has_color = $request->has_param( 'color' );
		if ( $has_color ) {
			$color_param = $request->get_param( 'color' );
			if ( null === $color_param || '' === trim( (string) $color_param ) ) {
				$color = '';
			} else {
				$sanitized_color = sanitize_hex_color( (string) $color_param );
				if ( ! $sanitized_color ) {
					return new WP_Error(
						'bbgf_flag_invalid_color',
						__( 'Color must be a valid hex value (e.g. #F87171).', 'bb-groomflow' ),
						array( 'status' => 400 )
					);
				}

				$color = $sanitized_color;
			}
		} else {
			$existing_color = trim( (string) ( $existing['color'] ?? '' ) );
			$color          = '';
			if ( '' !== $existing_color ) {
				$sanitized_existing = sanitize_hex_color( $existing_color );
				if ( $sanitized_existing ) {
					$color = $sanitized_existing;
				}
			}
		}

		$has_severity = $request->has_param( 'severity' );
		if ( $has_severity ) {
			$severity = strtolower( (string) $request->get_param( 'severity' ) );
		} elseif ( $is_update ) {
			$severity = strtolower( (string) ( $existing['severity'] ?? 'medium' ) );
		} else {
			$severity = 'medium';
		}

		if ( ! in_array( $severity, self::SEVERITY, true ) ) {
			return new WP_Error(
				'bbgf_flag_invalid_severity',
				__( 'Severity must be low, medium, or high.', 'bb-groomflow' ),
				array( 'status' => 400 )
			);
		}

		$has_description = $request->has_param( 'description' );
		if ( $has_description ) {
			$description = sanitize_textarea_field( (string) $request->get_param( 'description' ) );
		} else {
			$description = sanitize_textarea_field( (string) ( $existing['description'] ?? '' ) );
		}

		return array(
			'name'        => sanitize_text_field( $name ),
			'emoji'       => $emoji,
			'color'       => $color,
			'severity'    => $severity,
			'description' => $description,
		);
	}
	// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared
}
