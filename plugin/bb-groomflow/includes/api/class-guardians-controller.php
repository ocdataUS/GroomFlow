<?php
/**
 * Guardians REST controller.
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
 * Provides CRUD endpoints for guardian records.
 */
class Guardians_Controller extends REST_Controller {
	// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared
	/**
	 * REST route base.
	 *
	 * @var string
	 */
	protected $rest_base = 'guardians';

	/**
	 * Allowed preferred contact values.
	 *
	 * @var string[]
	 */
	private const CONTACT_OPTIONS = array( '', 'email', 'phone_mobile', 'phone_alt', 'sms' );

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
							'description' => __( 'Search by name, email, or phone number.', 'bb-groomflow' ),
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
	 * Permission check for listing guardians.
	 *
	 * @param WP_REST_Request $request Request instance.
	 * @return true|WP_Error
	 */
	public function get_items_permissions_check( $request ) {
		return $this->check_capability( 'bbgf_edit_visits' );
	}

	/**
	 * Permission check for retrieving a single guardian.
	 *
	 * @param WP_REST_Request $request Request instance.
	 * @return true|WP_Error
	 */
	public function get_item_permissions_check( $request ) {
		return $this->check_capability( 'bbgf_edit_visits' );
	}

	/**
	 * Permission check for creating a guardian.
	 *
	 * @param WP_REST_Request $request Request instance.
	 * @return true|WP_Error
	 */
	public function create_item_permissions_check( $request ) {
		return $this->check_capability( 'bbgf_edit_visits' );
	}

	/**
	 * Permission check for updating a guardian.
	 *
	 * @param WP_REST_Request $request Request instance.
	 * @return true|WP_Error
	 */
	public function update_item_permissions_check( $request ) {
		return $this->check_capability( 'bbgf_edit_visits' );
	}

	/**
	 * Retrieve a collection of guardians.
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

		$table  = $this->tables['guardians'];
		$select = 'id, first_name, last_name, email, phone_mobile, phone_alt, preferred_contact, address, notes, meta, created_at, updated_at';

		if ( '' !== $search ) {
			$like = '%' . $this->wpdb->esc_like( $search ) . '%';

			$query = $this->wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT {$select}
				FROM {$table}
				WHERE (
					first_name LIKE %s
					OR last_name LIKE %s
					OR email LIKE %s
					OR phone_mobile LIKE %s
					OR phone_alt LIKE %s
				)
				ORDER BY last_name ASC, first_name ASC
				LIMIT %d OFFSET %d",
				$like,
				$like,
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
					first_name LIKE %s
					OR last_name LIKE %s
					OR email LIKE %s
					OR phone_mobile LIKE %s
					OR phone_alt LIKE %s
				)",
				$like,
				$like,
				$like,
				$like,
				$like
			);
		} else {
			$query = $this->wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT {$select}
				FROM {$table}
				ORDER BY last_name ASC, first_name ASC
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
	 * Retrieve a single guardian.
	 *
	 * @param WP_REST_Request $request Request instance.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_item( $request ) {
		$guardian_id = (int) $request->get_param( 'id' );
		$guardian    = $this->get_guardian( $guardian_id );

		if ( null === $guardian ) {
			return new WP_Error(
				'bbgf_guardian_not_found',
				__( 'Guardian not found.', 'bb-groomflow' ),
				array( 'status' => 404 )
			);
		}

		return $this->prepare_item_for_response( $guardian, $request );
	}

	/**
	 * Create a new guardian.
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
		$prepared['created_at'] = $now;
		$prepared['updated_at'] = $now;

		$data_and_formats = $this->filter_null_columns( $prepared );
		$data             = $data_and_formats['data'];
		$formats          = $data_and_formats['formats'];

		$this->wpdb->insert( $this->tables['guardians'], $data, $formats );
		$guardian_id = (int) $this->wpdb->insert_id;

		$guardian = $this->get_guardian( $guardian_id );
		if ( null === $guardian ) {
			return new WP_Error(
				'bbgf_guardian_not_found',
				__( 'Guardian not found after creation.', 'bb-groomflow' ),
				array( 'status' => 500 )
			);
		}

		$response = $this->prepare_item_for_response( $guardian, $request );
		$response->set_status( 201 );
		$response->header(
			'Location',
			rest_url( sprintf( '%s/%s/%d', $this->namespace, $this->rest_base, $guardian_id ) )
		);

		return $response;
	}

	/**
	 * Update an existing guardian.
	 *
	 * @param WP_REST_Request $request Request instance.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_item( $request ) {
		$guardian_id = (int) $request->get_param( 'id' );
		$current     = $this->get_guardian( $guardian_id );

		if ( null === $current ) {
			return new WP_Error(
				'bbgf_guardian_not_found',
				__( 'Guardian not found.', 'bb-groomflow' ),
				array( 'status' => 404 )
			);
		}

		$prepared = $this->validate_and_prepare_item( $request, $guardian_id );
		if ( is_wp_error( $prepared ) ) {
			return $prepared;
		}

		$prepared['updated_at'] = $this->plugin->now();

		$data_and_formats = $this->filter_null_columns( $prepared );
		$data             = $data_and_formats['data'];
		$formats          = $data_and_formats['formats'];

		$this->wpdb->update(
			$this->tables['guardians'],
			$data,
			array( 'id' => $guardian_id ),
			$formats,
			array( '%d' )
		);

		$guardian = $this->get_guardian( $guardian_id );
		if ( null === $guardian ) {
			return new WP_Error(
				'bbgf_guardian_not_found',
				__( 'Guardian not found after update.', 'bb-groomflow' ),
				array( 'status' => 500 )
			);
		}

		return $this->prepare_item_for_response( $guardian, $request );
	}

	/**
	 * Prepare output response for a single guardian.
	 *
	 * @param array           $item    Raw database row.
	 * @param WP_REST_Request $request Request instance.
	 * @return WP_REST_Response
	 */
	public function prepare_item_for_response( $item, $request ): WP_REST_Response {
		$email     = isset( $item['email'] ) ? trim( (string) $item['email'] ) : '';
		$mobile    = isset( $item['phone_mobile'] ) ? trim( (string) $item['phone_mobile'] ) : '';
		$alternate = isset( $item['phone_alt'] ) ? trim( (string) $item['phone_alt'] ) : '';
		$address   = isset( $item['address'] ) ? trim( (string) $item['address'] ) : '';
		$notes     = isset( $item['notes'] ) ? trim( (string) $item['notes'] ) : '';
		$preferred = isset( $item['preferred_contact'] ) ? trim( (string) $item['preferred_contact'] ) : '';

		$data = array(
			'id'                => (int) $item['id'],
			'first_name'        => $item['first_name'],
			'last_name'         => $item['last_name'],
			'email'             => '' !== $email ? $item['email'] : null,
			'phone_mobile'      => '' !== $mobile ? $item['phone_mobile'] : null,
			'phone_alt'         => '' !== $alternate ? $item['phone_alt'] : null,
			'preferred_contact' => '' !== $preferred ? $item['preferred_contact'] : null,
			'address'           => '' !== $address ? $item['address'] : null,
			'notes'             => '' !== $notes ? $item['notes'] : null,
			'meta'              => $this->decode_meta( $item['meta'] ?? null ),
			'created_at'        => isset( $item['created_at'] ) ? mysql_to_rfc3339( $item['created_at'] ) : null,
			'updated_at'        => isset( $item['updated_at'] ) ? mysql_to_rfc3339( $item['updated_at'] ) : null,
		);

		return rest_ensure_response( $data );
	}

	/**
	 * Provide JSON schema for guardians.
	 *
	 * @return array<string,mixed>
	 */
	public function get_item_schema(): array {
		if ( null !== $this->schema ) {
			return $this->schema;
		}

		$this->schema = array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'bbgf_guardian',
			'type'       => 'object',
			'properties' => array(
				'id'                => array(
					'type'     => 'integer',
					'context'  => array( 'view', 'edit' ),
					'readonly' => true,
				),
				'first_name'        => array(
					'type'      => 'string',
					'context'   => array( 'view', 'edit' ),
					'minLength' => 1,
				),
				'last_name'         => array(
					'type'      => 'string',
					'context'   => array( 'view', 'edit' ),
					'minLength' => 1,
				),
				'email'             => array(
					'type'    => array( 'string', 'null' ),
					'context' => array( 'view', 'edit' ),
					'format'  => 'email',
				),
				'phone_mobile'      => array(
					'type'    => array( 'string', 'null' ),
					'context' => array( 'view', 'edit' ),
				),
				'phone_alt'         => array(
					'type'    => array( 'string', 'null' ),
					'context' => array( 'view', 'edit' ),
				),
				'preferred_contact' => array(
					'type'    => array( 'string', 'null' ),
					'context' => array( 'view', 'edit' ),
					'enum'    => array_values( self::CONTACT_OPTIONS ),
				),
				'address'           => array(
					'type'    => array( 'string', 'null' ),
					'context' => array( 'view', 'edit' ),
				),
				'notes'             => array(
					'type'    => array( 'string', 'null' ),
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
			'required'   => array( 'first_name', 'last_name' ),
		);

		return $this->schema;
	}

	/**
	 * Fetch a guardian row.
	 *
	 * @param int $guardian_id Guardian ID.
	 * @return array<string,mixed>|null
	 */
	private function get_guardian( int $guardian_id ): ?array {
		if ( $guardian_id <= 0 ) {
			return null;
		}

		$row = $this->wpdb->get_row(
			$this->wpdb->prepare(
				"SELECT id, first_name, last_name, email, phone_mobile, phone_alt, preferred_contact, address, notes, meta, created_at, updated_at
				FROM {$this->tables['guardians']}
				WHERE id = %d",
				$guardian_id
			),
			ARRAY_A
		);

		if ( ! $row ) {
			return null;
		}

		return $row;
	}

	/**
	 * Validate and sanitize request data.
	 *
	 * @param WP_REST_Request $request     Request instance.
	 * @param int             $guardian_id Existing guardian ID when updating.
	 * @return array<string,mixed>|WP_Error
	 */
	private function validate_and_prepare_item( WP_REST_Request $request, int $guardian_id = 0 ) {
		$first_name = trim( (string) $request->get_param( 'first_name' ) );
		$last_name  = trim( (string) $request->get_param( 'last_name' ) );

		if ( '' === $first_name ) {
			return new WP_Error(
				'bbgf_guardian_missing_first_name',
				__( 'First name is required.', 'bb-groomflow' ),
				array( 'status' => 400 )
			);
		}

		if ( '' === $last_name ) {
			return new WP_Error(
				'bbgf_guardian_missing_last_name',
				__( 'Last name is required.', 'bb-groomflow' ),
				array( 'status' => 400 )
			);
		}

		$email = sanitize_email( (string) $request->get_param( 'email' ) );
		if ( '' !== $email && ! is_email( $email ) ) {
			return new WP_Error(
				'bbgf_guardian_invalid_email',
				__( 'Email address is invalid.', 'bb-groomflow' ),
				array( 'status' => 400 )
			);
		}

		$preferred = sanitize_text_field( (string) $request->get_param( 'preferred_contact' ) );
		if ( ! in_array( $preferred, self::CONTACT_OPTIONS, true ) ) {
			return new WP_Error(
				'bbgf_guardian_invalid_preferred_contact',
				__( 'Preferred contact method is not supported.', 'bb-groomflow' ),
				array( 'status' => 400 )
			);
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
			'first_name'        => sanitize_text_field( $first_name ),
			'last_name'         => sanitize_text_field( $last_name ),
			'email'             => $email,
			'phone_mobile'      => sanitize_text_field( (string) $request->get_param( 'phone_mobile' ) ),
			'phone_alt'         => sanitize_text_field( (string) $request->get_param( 'phone_alt' ) ),
			'preferred_contact' => $preferred,
			'address'           => sanitize_textarea_field( (string) $request->get_param( 'address' ) ),
			'notes'             => sanitize_textarea_field( (string) $request->get_param( 'notes' ) ),
			'meta'              => null !== $meta ? wp_json_encode( $meta ) : null,
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
			'first_name'        => '%s',
			'last_name'         => '%s',
			'email'             => '%s',
			'phone_mobile'      => '%s',
			'phone_alt'         => '%s',
			'preferred_contact' => '%s',
			'address'           => '%s',
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
