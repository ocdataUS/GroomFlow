<?php
/**
 * Clients REST controller.
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
 * Provides CRUD endpoints for client records.
 */
class Clients_Controller extends REST_Controller {
	// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared
	/**
	 * REST route base.
	 *
	 * @var string
	 */
	protected $rest_base = 'clients';

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
	 * Cached schema.
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
							'description' => __( 'Search by client name or breed.', 'bb-groomflow' ),
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
				'schema' => array( $this, 'get_public_item_schema' ),
			)
		);
	}

	/**
	 * Permission check for listing clients.
	 *
	 * @param WP_REST_Request $request Request instance.
	 * @return true|WP_Error
	 */
	public function get_items_permissions_check( $request ) {
		return $this->check_capability( 'bbgf_edit_visits' );
	}

	/**
	 * Permission check for retrieving a single client.
	 *
	 * @param WP_REST_Request $request Request instance.
	 * @return true|WP_Error
	 */
	public function get_item_permissions_check( $request ) {
		return $this->check_capability( 'bbgf_edit_visits' );
	}

	/**
	 * Permission check for creating a client.
	 *
	 * @param WP_REST_Request $request Request instance.
	 * @return true|WP_Error
	 */
	public function create_item_permissions_check( $request ) {
		return $this->check_capability( 'bbgf_edit_visits' );
	}

	/**
	 * Permission check for updating a client.
	 *
	 * @param WP_REST_Request $request Request instance.
	 * @return true|WP_Error
	 */
	public function update_item_permissions_check( $request ) {
		return $this->check_capability( 'bbgf_edit_visits' );
	}

	/**
	 * Retrieve a collection of clients.
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

		$table = $this->tables['clients'];

		if ( '' !== $search ) {
			$like = '%' . $this->wpdb->esc_like( $search ) . '%';

			$query = $this->wpdb->prepare( // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared
				"SELECT id, name, slug, guardian_id, breed, weight, sex, dob, temperament, preferred_groomer, notes, meta, created_at, updated_at
				FROM {$table}
				WHERE (name LIKE %s OR breed LIKE %s)
				ORDER BY name ASC
				LIMIT %d OFFSET %d",
				$like,
				$like,
				$per_page,
				$offset
			);

			$count_sql = $this->wpdb->prepare( // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared
				"SELECT COUNT(*) FROM {$table} WHERE (name LIKE %s OR breed LIKE %s)",
				$like,
				$like
			);
		} else {
			$query = $this->wpdb->prepare( // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared
				"SELECT id, name, slug, guardian_id, breed, weight, sex, dob, temperament, preferred_groomer, notes, meta, created_at, updated_at
				FROM {$table}
				ORDER BY name ASC
				LIMIT %d OFFSET %d",
				$per_page,
				$offset
			);

			$count_sql = "SELECT COUNT(*) FROM {$table}"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared
		}

		$rows = $this->wpdb->get_results( $query, ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared
		$data = array();

		foreach ( $rows as $row ) {
			$item   = $this->prepare_item_for_response( $row, $request );
			$data[] = $this->prepare_response_for_collection( $item );
		}

		$count = (int) $this->wpdb->get_var( $count_sql ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared

		$response = rest_ensure_response( $data );
		$response->header( 'X-WP-Total', (string) $count );
		$total_pages = (int) ceil( $count / $per_page );
		$response->header( 'X-WP-TotalPages', (string) max( 1, $total_pages ) );

		return $response;
	}

	/**
	 * Retrieve a single client.
	 *
	 * @param WP_REST_Request $request Request instance.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_item( $request ) {
		$client_id = (int) $request->get_param( 'id' );
		$client    = $this->get_client( $client_id );

		if ( null === $client ) {
			return new WP_Error(
				'bbgf_client_not_found',
				__( 'Client not found.', 'bb-groomflow' ),
				array( 'status' => 404 )
			);
		}

		return $this->prepare_item_for_response( $client, $request );
	}

	/**
	 * Create a new client.
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
		$prepared['slug']       = $this->plugin->unique_slug( $prepared['name'], $this->tables['clients'], 'slug' );
		$prepared['created_at'] = $now;
		$prepared['updated_at'] = $now;

		$data_and_formats = $this->filter_null_columns( $prepared );
		$data             = $data_and_formats['data'];
		$formats          = $data_and_formats['formats'];

		$this->wpdb->insert( $this->tables['clients'], $data, $formats ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$client_id = (int) $this->wpdb->insert_id;

		$client = $this->get_client( $client_id );

		if ( null === $client ) {
			return new WP_Error(
				'bbgf_client_not_found',
				__( 'Client not found after creation.', 'bb-groomflow' ),
				array( 'status' => 500 )
			);
		}

		$response = $this->prepare_item_for_response( $client, $request );
		$response->set_status( 201 );
		$response->header(
			'Location',
			rest_url( sprintf( '%s/%s/%d', $this->namespace, $this->rest_base, $client_id ) )
		);

		$this->plugin->visit_service()->flush_cache();

		return $response;
	}

	/**
	 * Update an existing client.
	 *
	 * @param WP_REST_Request $request Request instance.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_item( $request ) {
		$client_id = (int) $request->get_param( 'id' );
		$current   = $this->get_client( $client_id );

		if ( null === $current ) {
			return new WP_Error(
				'bbgf_client_not_found',
				__( 'Client not found.', 'bb-groomflow' ),
				array( 'status' => 404 )
			);
		}

		$prepared = $this->validate_and_prepare_item( $request, $client_id );
		if ( is_wp_error( $prepared ) ) {
			return $prepared;
		}

		$prepared['slug']       = $this->plugin->unique_slug( $prepared['name'], $this->tables['clients'], 'slug', $client_id );
		$prepared['updated_at'] = $this->plugin->now();

		$data_and_formats = $this->filter_null_columns( $prepared );
		$data             = $data_and_formats['data'];
		$formats          = $data_and_formats['formats'];

		$this->wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$this->tables['clients'],
			$data,
			array( 'id' => $client_id ),
			$formats,
			array( '%d' )
		);

		$client = $this->get_client( $client_id );

		$this->plugin->visit_service()->flush_cache();

		return $this->prepare_item_for_response( $client, $request );
	}

	/**
	 * Prepare output response for a single client.
	 *
	 * @param array           $item    Raw database row.
	 * @param WP_REST_Request $request Request instance.
	 * @return WP_REST_Response
	 */
	public function prepare_item_for_response( $item, $request ): WP_REST_Response {
		$guardian_id = isset( $item['guardian_id'] ) ? (int) $item['guardian_id'] : 0;
		$weight      = isset( $item['weight'] ) ? $item['weight'] : null;
		$dob         = isset( $item['dob'] ) ? trim( (string) $item['dob'] ) : '';

		$data = array(
			'id'                => (int) $item['id'],
			'name'              => $item['name'],
			'slug'              => $item['slug'],
			'guardian_id'       => $guardian_id > 0 ? $guardian_id : null,
			'breed'             => $item['breed'],
			'weight'            => ( '' === $weight || null === $weight ) ? null : (float) $weight,
			'sex'               => $item['sex'],
			'dob'               => '' !== $dob ? $dob : null,
			'temperament'       => $item['temperament'],
			'preferred_groomer' => $item['preferred_groomer'],
			'notes'             => $item['notes'],
			'meta'              => $this->decode_meta( $item['meta'] ?? null ),
			'created_at'        => isset( $item['created_at'] ) ? mysql_to_rfc3339( $item['created_at'] ) : null,
			'updated_at'        => isset( $item['updated_at'] ) ? mysql_to_rfc3339( $item['updated_at'] ) : null,
		);

		return rest_ensure_response( $data );
	}

	/**
	 * Provide JSON schema for clients.
	 *
	 * @return array<string,mixed>
	 */
	public function get_item_schema(): array {
		if ( null !== $this->schema ) {
			return $this->schema;
		}

		$this->schema = array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'bbgf_client',
			'type'       => 'object',
			'properties' => array(
				'id'                => array(
					'type'        => 'integer',
					'context'     => array( 'view', 'edit' ),
					'readonly'    => true,
					'description' => __( 'Unique identifier for the client.', 'bb-groomflow' ),
				),
				'name'              => array(
					'type'        => 'string',
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'Client display name.', 'bb-groomflow' ),
				),
				'slug'              => array(
					'type'        => 'string',
					'context'     => array( 'view', 'edit' ),
					'readonly'    => true,
					'description' => __( 'URL-friendly identifier.', 'bb-groomflow' ),
				),
				'guardian_id'       => array(
					'type'        => array( 'integer', 'null' ),
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'Linked guardian ID.', 'bb-groomflow' ),
				),
				'breed'             => array(
					'type'    => 'string',
					'context' => array( 'view', 'edit' ),
				),
				'weight'            => array(
					'type'    => array( 'number', 'null' ),
					'context' => array( 'view', 'edit' ),
				),
				'sex'               => array(
					'type'    => 'string',
					'context' => array( 'view', 'edit' ),
				),
				'dob'               => array(
					'type'        => array( 'string', 'null' ),
					'format'      => 'date',
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'Date of birth (YYYY-MM-DD) if known.', 'bb-groomflow' ),
				),
				'temperament'       => array(
					'type'    => 'string',
					'context' => array( 'view', 'edit' ),
				),
				'preferred_groomer' => array(
					'type'    => 'string',
					'context' => array( 'view', 'edit' ),
				),
				'notes'             => array(
					'type'    => 'string',
					'context' => array( 'view', 'edit' ),
				),
				'meta'              => array(
					'type'                 => 'object',
					'context'              => array( 'view', 'edit' ),
					'properties'           => array(),
					'additionalProperties' => true,
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
			'required'   => array( 'name' ),
		);

		return $this->schema;
	}

	/**
	 * Fetch a client row.
	 *
	 * @param int $client_id Client ID.
	 * @return array<string,mixed>|null
	 */
	private function get_client( int $client_id ): ?array {
		if ( $client_id <= 0 ) {
			return null;
		}

		$row = $this->wpdb->get_row(
			$this->wpdb->prepare(
				"SELECT id, name, slug, guardian_id, breed, weight, sex, dob, temperament, preferred_groomer, notes, meta, created_at, updated_at
				FROM {$this->tables['clients']}
				WHERE id = %d",
				$client_id
			), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared
			ARRAY_A
		); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

		return is_array( $row ) ? $row : null;
	}

	/**
	 * Validate and sanitize request data.
	 *
	 * @param WP_REST_Request $request   Request instance.
	 * @param int             $client_id Existing client ID when updating.
	 * @return array<string,mixed>|WP_Error
	 */
	private function validate_and_prepare_item( WP_REST_Request $request, int $client_id = 0 ) {
		$name = trim( (string) $request->get_param( 'name' ) );
		if ( '' === $name ) {
			return new WP_Error(
				'bbgf_client_missing_name',
				__( 'Client name is required.', 'bb-groomflow' ),
				array( 'status' => 400 )
			);
		}

		$guardian_id = (int) $request->get_param( 'guardian_id' );
		if ( $guardian_id < 0 ) {
			$guardian_id = 0;
		}

		if ( $guardian_id > 0 && ! $this->guardian_exists( $guardian_id ) ) {
			return new WP_Error(
				'bbgf_client_invalid_guardian',
				__( 'Selected guardian does not exist.', 'bb-groomflow' ),
				array( 'status' => 400 )
			);
		}

		$dob = trim( (string) $request->get_param( 'dob' ) );
		if ( '' !== $dob && ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $dob ) ) {
			return new WP_Error(
				'bbgf_client_invalid_dob',
				__( 'Date of birth must use the format YYYY-MM-DD.', 'bb-groomflow' ),
				array( 'status' => 400 )
			);
		}

		$weight_param = $request->get_param( 'weight' );
		$weight       = null;
		if ( null !== $weight_param && '' !== $weight_param ) {
			if ( ! is_numeric( $weight_param ) ) {
				return new WP_Error(
					'bbgf_client_invalid_weight',
					__( 'Weight must be numeric.', 'bb-groomflow' ),
					array( 'status' => 400 )
				);
			}
			$weight = round( (float) $weight_param, 2 );
		}

		$meta_param = $request->get_param( 'meta' );
		$meta       = null;
		if ( is_array( $meta_param ) ) {
			$meta = $meta_param;
		} elseif ( is_string( $meta_param ) && '' !== trim( $meta_param ) ) {
			$decoded = json_decode( $meta_param, true );
			if ( null !== $decoded && JSON_ERROR_NONE === json_last_error() && is_array( $decoded ) ) {
				$meta = $decoded;
			}
		}

		return array(
			'name'              => sanitize_text_field( $name ),
			'guardian_id'       => $guardian_id,
			'breed'             => sanitize_text_field( (string) $request->get_param( 'breed' ) ),
			'weight'            => $weight,
			'sex'               => sanitize_text_field( (string) $request->get_param( 'sex' ) ),
			'dob'               => '' !== $dob ? $dob : null,
			'temperament'       => sanitize_text_field( (string) $request->get_param( 'temperament' ) ),
			'preferred_groomer' => sanitize_text_field( (string) $request->get_param( 'preferred_groomer' ) ),
			'notes'             => sanitize_textarea_field( (string) $request->get_param( 'notes' ) ),
			'meta'              => null !== $meta ? wp_json_encode( $meta ) : null,
		);
	}

	/**
	 * Confirm guardian existence.
	 *
	 * @param int $guardian_id Guardian ID.
	 * @return bool
	 */
	private function guardian_exists( int $guardian_id ): bool {
		if ( $guardian_id <= 0 ) {
			return false;
		}

		$exists = $this->wpdb->get_var(
			$this->wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->tables['guardians']} WHERE id = %d",
				$guardian_id
			) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared
		); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

		return (int) $exists > 0;
	}

	/**
	 * Filter null values before database operations.
	 *
	 * @param array<string,mixed> $data Raw data.
	 * @return array{data:array<string,mixed>,formats:array<int,string>}
	 */
	private function filter_null_columns( array $data ): array {
		$format_map = array(
			'name'              => '%s',
			'slug'              => '%s',
			'guardian_id'       => '%d',
			'breed'             => '%s',
			'weight'            => '%f',
			'sex'               => '%s',
			'dob'               => '%s',
			'temperament'       => '%s',
			'preferred_groomer' => '%s',
			'notes'             => '%s',
			'meta'              => '%s',
			'created_at'        => '%s',
			'updated_at'        => '%s',
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
	 * Decode optional meta column.
	 *
	 * @param string|null $raw_meta Raw meta string.
	 * @return array<string,mixed>
	 */
	private function decode_meta( ?string $raw_meta ): array {
		if ( null === $raw_meta || '' === $raw_meta ) {
			return array();
		}

		$decoded = json_decode( $raw_meta, true );
		if ( null === $decoded || JSON_ERROR_NONE !== json_last_error() || ! is_array( $decoded ) ) {
			return array();
		}

		return $decoded;
	}

	// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared
}
