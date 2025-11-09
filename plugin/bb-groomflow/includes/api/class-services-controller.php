<?php
/**
 * Services REST controller.
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
 * Provides CRUD endpoints for services.
 */
class Services_Controller extends REST_Controller {
	// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared
	/**
	 * REST route base.
	 *
	 * @var string
	 */
	protected $rest_base = 'services';

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
							'description' => __( 'Search by service name, description, or tags.', 'bb-groomflow' ),
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
			sprintf( '/%s/(?P<id>\d+)', $this->rest_base ),
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
	 * Permission check for listing services.
	 *
	 * @param WP_REST_Request $request Request instance.
	 * @return true|WP_Error
	 */
	public function get_items_permissions_check( $request ) {
		return $this->check_capability( 'bbgf_manage_services' );
	}

	/**
	 * Permission check for retrieving a single service.
	 *
	 * @param WP_REST_Request $request Request instance.
	 * @return true|WP_Error
	 */
	public function get_item_permissions_check( $request ) {
		return $this->check_capability( 'bbgf_manage_services' );
	}

	/**
	 * Permission check for creating a service.
	 *
	 * @param WP_REST_Request $request Request instance.
	 * @return true|WP_Error
	 */
	public function create_item_permissions_check( $request ) {
		return $this->check_capability( 'bbgf_manage_services' );
	}

	/**
	 * Permission check for updating a service.
	 *
	 * @param WP_REST_Request $request Request instance.
	 * @return true|WP_Error
	 */
	public function update_item_permissions_check( $request ) {
		return $this->check_capability( 'bbgf_manage_services' );
	}

	/**
	 * Permission check for deleting a service.
	 *
	 * @param WP_REST_Request $request Request instance.
	 * @return true|WP_Error
	 */
	public function delete_item_permissions_check( $request ) {
		return $this->check_capability( 'bbgf_manage_services' );
	}

	/**
	 * Retrieve a collection of services.
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

		$table  = $this->tables['services'];
		$select = 'id, name, slug, icon, color, duration_minutes, price, description, flags, created_at, updated_at';

		if ( '' !== $search ) {
			$like = '%' . $this->wpdb->esc_like( $search ) . '%';

			$query = $this->wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT {$select}
				FROM {$table}
				WHERE (
					name LIKE %s
					OR description LIKE %s
					OR flags LIKE %s
				)
				ORDER BY name ASC
				LIMIT %d OFFSET %d",
				$like,
				$like,
				$like,
				$per_page,
				$offset
			);

			$count_sql = $this->wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT COUNT(*) FROM {$table}
				WHERE (
					name LIKE %s
					OR description LIKE %s
					OR flags LIKE %s
				)",
				$like,
				$like,
				$like
			);
		} else {
			$query = $this->wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT {$select}
				FROM {$table}
				ORDER BY name ASC
				LIMIT %d OFFSET %d",
				$per_page,
				$offset
			);

			$count_sql = "SELECT COUNT(*) FROM {$table}"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}

		$rows = $this->wpdb->get_results( $query, ARRAY_A );
		$data = array();

		foreach ( $rows as $row ) {
			$item   = $this->prepare_item_for_response( $row, $request );
			$data[] = $this->prepare_response_for_collection( $item );
		}

		$count = (int) $this->wpdb->get_var( $count_sql );

		$response = rest_ensure_response( $data );
		$response->header( 'X-WP-Total', (string) $count );
		$total_pages = (int) ceil( $count / $per_page );
		$response->header( 'X-WP-TotalPages', (string) max( 1, $total_pages ) );

		return $response;
	}

	/**
	 * Retrieve a single service.
	 *
	 * @param WP_REST_Request $request Request instance.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_item( $request ) {
		$service_id = (int) $request->get_param( 'id' );
		$service    = $this->get_service( $service_id );

		if ( null === $service ) {
			return new WP_Error(
				'bbgf_service_not_found',
				__( 'Service not found.', 'bb-groomflow' ),
				array( 'status' => 404 )
			);
		}

		return $this->prepare_item_for_response( $service, $request );
	}

	/**
	 * Create a new service.
	 *
	 * @param WP_REST_Request $request Request instance.
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_item( $request ) {
		$prepared = $this->validate_and_prepare_item( $request );
		if ( is_wp_error( $prepared ) ) {
			return $prepared;
		}

		$now                    = $this->plugin->now();
		$prepared['slug']       = $this->plugin->unique_slug( $prepared['name'], $this->tables['services'], 'slug' );
		$prepared['created_at'] = $now;
		$prepared['updated_at'] = $now;

		$data_and_formats = $this->filter_null_columns( $prepared );
		$data             = $data_and_formats['data'];
		$formats          = $data_and_formats['formats'];

		$this->wpdb->insert( $this->tables['services'], $data, $formats );
		$service_id = (int) $this->wpdb->insert_id;

		$service = $this->get_service( $service_id );
		if ( null === $service ) {
			return new WP_Error(
				'bbgf_service_not_found',
				__( 'Service not found after creation.', 'bb-groomflow' ),
				array( 'status' => 500 )
			);
		}

		$response = $this->prepare_item_for_response( $service, $request );
		$response->set_status( 201 );
		$response->header(
			'Location',
			rest_url( sprintf( '%s/%s/%d', $this->namespace, $this->rest_base, $service_id ) )
		);

		return $response;
	}

	/**
	 * Update an existing service.
	 *
	 * @param WP_REST_Request $request Request instance.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_item( $request ) {
		$service_id = (int) $request->get_param( 'id' );
		$current    = $this->get_service( $service_id );

		if ( null === $current ) {
			return new WP_Error(
				'bbgf_service_not_found',
				__( 'Service not found.', 'bb-groomflow' ),
				array( 'status' => 404 )
			);
		}

		$price_should_clear = ( $request->has_param( 'price' ) && null === $request->get_param( 'price' ) );

		$prepared = $this->validate_and_prepare_item( $request, $service_id, $current );
		if ( is_wp_error( $prepared ) ) {
			return $prepared;
		}

		$prepared['slug']       = $this->plugin->unique_slug( $prepared['name'], $this->tables['services'], 'slug', $service_id );
		$prepared['updated_at'] = $this->plugin->now();

		$data_and_formats = $this->filter_null_columns( $prepared );
		$data             = $data_and_formats['data'];
		$formats          = $data_and_formats['formats'];

		$this->wpdb->update(
			$this->tables['services'],
			$data,
			array( 'id' => $service_id ),
			$formats,
			array( '%d' )
		);

		if ( $price_should_clear ) {
			$this->wpdb->query(
				$this->wpdb->prepare(
					"UPDATE {$this->tables['services']} SET price = NULL WHERE id = %d",
					$service_id
				)
			);
		}

		$service = $this->get_service( $service_id );
		if ( null === $service ) {
			return new WP_Error(
				'bbgf_service_not_found',
				__( 'Service not found after update.', 'bb-groomflow' ),
				array( 'status' => 500 )
			);
		}

		return $this->prepare_item_for_response( $service, $request );
	}

	/**
	 * Delete a service.
	 *
	 * @param WP_REST_Request $request Request instance.
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete_item( $request ) {
		$service_id = (int) $request->get_param( 'id' );
		$service    = $this->get_service( $service_id );

		if ( null === $service ) {
			return new WP_Error(
				'bbgf_service_not_found',
				__( 'Service not found.', 'bb-groomflow' ),
				array( 'status' => 404 )
			);
		}

		$this->wpdb->delete( $this->tables['services'], array( 'id' => $service_id ), array( '%d' ) );
		$this->wpdb->delete( $this->tables['service_package_items'], array( 'service_id' => $service_id ), array( '%d' ) );

		$response = array(
			'deleted'  => true,
			'previous' => $this->prepare_item_for_response( $service, $request )->get_data(),
		);

		return rest_ensure_response( $response );
	}

	/**
	 * Prepare output response for a single service.
	 *
	 * @param array           $item    Raw database row.
	 * @param WP_REST_Request $request Request instance.
	 * @return WP_REST_Response
	 */
	public function prepare_item_for_response( $item, $request ): WP_REST_Response {
		$color       = isset( $item['color'] ) ? trim( (string) $item['color'] ) : '';
		$description = isset( $item['description'] ) ? trim( (string) $item['description'] ) : '';
		$price       = $item['price'];

		$data = array(
			'id'               => (int) $item['id'],
			'name'             => $item['name'],
			'slug'             => $item['slug'],
			'icon'             => $item['icon'],
			'color'            => '' !== $color ? $item['color'] : null,
			'duration_minutes' => isset( $item['duration_minutes'] ) ? (int) $item['duration_minutes'] : 0,
			'price'            => ( null === $price || '' === $price ) ? null : round( (float) $price, 2 ),
			'description'      => '' !== $description ? $item['description'] : null,
			'tags'             => $this->decode_tags( $item['flags'] ?? null ),
			'created_at'       => isset( $item['created_at'] ) ? mysql_to_rfc3339( $item['created_at'] ) : null,
			'updated_at'       => isset( $item['updated_at'] ) ? mysql_to_rfc3339( $item['updated_at'] ) : null,
		);

		return rest_ensure_response( $data );
	}

	/**
	 * Provide JSON schema for services.
	 *
	 * @return array<string,mixed>
	 */
	public function get_item_schema(): array {
		if ( null !== $this->schema ) {
			return $this->schema;
		}

		$this->schema = array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'bbgf_service',
			'type'       => 'object',
			'properties' => array(
				'id'               => array(
					'type'     => 'integer',
					'context'  => array( 'view', 'edit' ),
					'readonly' => true,
				),
				'name'             => array(
					'type'      => 'string',
					'context'   => array( 'view', 'edit' ),
					'minLength' => 1,
				),
				'slug'             => array(
					'type'     => 'string',
					'context'  => array( 'view' ),
					'readonly' => true,
				),
				'icon'             => array(
					'type'    => 'string',
					'context' => array( 'view', 'edit' ),
				),
				'color'            => array(
					'type'    => array( 'string', 'null' ),
					'context' => array( 'view', 'edit' ),
					'pattern' => '^#([A-Fa-f0-9]{3}){1,2}$',
				),
				'duration_minutes' => array(
					'type'    => 'integer',
					'context' => array( 'view', 'edit' ),
					'minimum' => 0,
				),
				'price'            => array(
					'type'    => array( 'number', 'null' ),
					'context' => array( 'view', 'edit' ),
				),
				'description'      => array(
					'type'    => array( 'string', 'null' ),
					'context' => array( 'view', 'edit' ),
				),
				'tags'             => array(
					'type'    => 'array',
					'context' => array( 'view', 'edit' ),
					'items'   => array(
						'type' => 'string',
					),
				),
				'created_at'       => array(
					'type'     => array( 'string', 'null' ),
					'format'   => 'date-time',
					'context'  => array( 'view' ),
					'readonly' => true,
				),
				'updated_at'       => array(
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
	 * Fetch a service row.
	 *
	 * @param int $service_id Service ID.
	 * @return array<string,mixed>|null
	 */
	private function get_service( int $service_id ): ?array {
		if ( $service_id <= 0 ) {
			return null;
		}

		$row = $this->wpdb->get_row(
			$this->wpdb->prepare(
				"SELECT id, name, slug, icon, color, duration_minutes, price, description, flags, created_at, updated_at
				FROM {$this->tables['services']}
				WHERE id = %d",
				$service_id
			),
			ARRAY_A
		);

		return is_array( $row ) ? $row : null;
	}

	/**
	 * Validate and sanitize request data.
	 *
	 * @param WP_REST_Request          $request    Request instance.
	 * @param int                      $service_id Existing service ID when updating.
	 * @param array<string,mixed>|null $existing Existing service row when updating.
	 * @return array<string,mixed>|WP_Error
	 */
	private function validate_and_prepare_item( WP_REST_Request $request, int $service_id = 0, ?array $existing = null ) {
		$is_update = ( $service_id > 0 && is_array( $existing ) );

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
				'bbgf_service_missing_name',
				__( 'Service name is required.', 'bb-groomflow' ),
				array( 'status' => 400 )
			);
		}

		$has_color = $request->has_param( 'color' );
		if ( $has_color ) {
			$color_param = $request->get_param( 'color' );
			if ( null === $color_param || '' === trim( (string) $color_param ) ) {
				$color_value = '';
			} else {
				$sanitized_color = sanitize_hex_color( (string) $color_param );
				if ( ! $sanitized_color ) {
					return new WP_Error(
						'bbgf_service_invalid_color',
						__( 'Color must be a valid hex value (e.g. #AABBCC).', 'bb-groomflow' ),
						array( 'status' => 400 )
					);
				}
				$color_value = $sanitized_color;
			}
		} else {
			$existing_color = trim( (string) ( $existing['color'] ?? '' ) );
			$color_value    = '';
			if ( '' !== $existing_color ) {
				$sanitized_existing = sanitize_hex_color( $existing_color );
				if ( $sanitized_existing ) {
					$color_value = $sanitized_existing;
				}
			}
		}

		$has_duration = $request->has_param( 'duration_minutes' );
		if ( $has_duration ) {
			$duration_param = $request->get_param( 'duration_minutes' );
			if ( null === $duration_param || '' === $duration_param ) {
				$duration = 0;
			} elseif ( ! is_numeric( $duration_param ) ) {
				return new WP_Error(
					'bbgf_service_invalid_duration',
					__( 'Duration must be a numeric value.', 'bb-groomflow' ),
					array( 'status' => 400 )
				);
			} else {
				$duration = max( 0, (int) $duration_param );
			}
		} else {
			$duration = (int) ( $existing['duration_minutes'] ?? 0 );
		}

		$has_price = $request->has_param( 'price' );
		if ( $has_price ) {
			$price_param = $request->get_param( 'price' );
			if ( null === $price_param || '' === $price_param ) {
				$price = null;
			} elseif ( ! is_numeric( $price_param ) ) {
				return new WP_Error(
					'bbgf_service_invalid_price',
					__( 'Price must be numeric.', 'bb-groomflow' ),
					array( 'status' => 400 )
				);
			} else {
				$price = round( (float) $price_param, 2 );
			}
		} else {
			$existing_price = $existing['price'] ?? null;
			if ( null === $existing_price || '' === $existing_price ) {
				$price = null;
			} else {
				$price = round( (float) $existing_price, 2 );
			}
		}

		$has_description = $request->has_param( 'description' );
		if ( $has_description ) {
			$description = sanitize_textarea_field( (string) $request->get_param( 'description' ) );
		} else {
			$description = sanitize_textarea_field( (string) ( $existing['description'] ?? '' ) );
		}

		$has_tags = $request->has_param( 'tags' );
		if ( $has_tags ) {
			$tags_param = $request->get_param( 'tags' );
			$tags       = array();
			if ( is_array( $tags_param ) ) {
				foreach ( $tags_param as $tag ) {
					$clean_tag = sanitize_text_field( (string) $tag );
					if ( '' !== $clean_tag ) {
						$tags[] = $clean_tag;
					}
				}
			} elseif ( is_string( $tags_param ) && '' !== trim( $tags_param ) ) {
				$parts = array_map( 'trim', explode( ',', $tags_param ) );
				foreach ( $parts as $tag ) {
					$clean_tag = sanitize_text_field( $tag );
					if ( '' !== $clean_tag ) {
						$tags[] = $clean_tag;
					}
				}
			}
		} else {
			$tags = $this->decode_tags( $existing['flags'] ?? null );
		}

		$has_icon = $request->has_param( 'icon' );
		if ( $has_icon ) {
			$icon = sanitize_text_field( (string) $request->get_param( 'icon' ) );
		} else {
			$icon = sanitize_text_field( (string) ( $existing['icon'] ?? '' ) );
		}

		return array(
			'name'             => sanitize_text_field( $name ),
			'icon'             => $icon,
			'color'            => $color_value,
			'duration_minutes' => $duration,
			'price'            => $price,
			'description'      => $description,
			'flags'            => wp_json_encode( array_values( $tags ) ),
		);
	}

	/**
	 * Filter null values before database operations.
	 *
	 * @param array<string,mixed> $data Raw data.
	 * @return array{data:array<string,mixed>,formats:array<int,string>}
	 */
	private function filter_null_columns( array $data ): array {
		$format_map = array(
			'name'             => '%s',
			'slug'             => '%s',
			'icon'             => '%s',
			'color'            => '%s',
			'duration_minutes' => '%d',
			'price'            => '%f',
			'description'      => '%s',
			'flags'            => '%s',
			'created_at'       => '%s',
			'updated_at'       => '%s',
		);

		foreach ( $data as $column => $value ) {
			if ( null === $value ) {
				unset( $data[ $column ] );
			}
		}

		$formats = array();
		foreach ( array_keys( $data ) as $column ) {
			$formats[] = $format_map[ $column ] ?? '%s';
		}

		return array(
			'data'    => $data,
			'formats' => array_values( $formats ),
		);
	}

	/**
	 * Decode stored tags JSON into an array.
	 *
	 * @param string|null $raw_tags Raw JSON string.
	 * @return array<int,string>
	 */
	private function decode_tags( ?string $raw_tags ): array {
		if ( null === $raw_tags || '' === $raw_tags ) {
			return array();
		}

		$decoded = json_decode( $raw_tags, true );
		if ( ! is_array( $decoded ) ) {
			return array();
		}

		$tags = array();
		foreach ( $decoded as $tag ) {
			$clean_tag = sanitize_text_field( (string) $tag );
			if ( '' !== $clean_tag ) {
				$tags[] = $clean_tag;
			}
		}

		return $tags;
	}
	// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared
}
