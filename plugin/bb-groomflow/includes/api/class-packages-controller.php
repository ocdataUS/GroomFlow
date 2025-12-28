<?php
/**
 * Packages REST controller.
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
 * Provides CRUD endpoints for service packages.
 */
class Packages_Controller extends REST_Controller {
	// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared
	/**
	 * REST route base.
	 *
	 * @var string
	 */
	protected $rest_base = 'packages';

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
							'description' => __( 'Search by package name or included services.', 'bb-groomflow' ),
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
	 * Permission check for listing packages.
	 *
	 * @param WP_REST_Request $request Request instance.
	 * @return true|WP_Error
	 */
	public function get_items_permissions_check( $request ) {
		return $this->check_capability( 'bbgf_manage_services' );
	}

	/**
	 * Permission check for retrieving a single package.
	 *
	 * @param WP_REST_Request $request Request instance.
	 * @return true|WP_Error
	 */
	public function get_item_permissions_check( $request ) {
		return $this->check_capability( 'bbgf_manage_services' );
	}

	/**
	 * Permission check for creating a package.
	 *
	 * @param WP_REST_Request $request Request instance.
	 * @return true|WP_Error
	 */
	public function create_item_permissions_check( $request ) {
		return $this->check_capability( 'bbgf_manage_services' );
	}

	/**
	 * Permission check for updating a package.
	 *
	 * @param WP_REST_Request $request Request instance.
	 * @return true|WP_Error
	 */
	public function update_item_permissions_check( $request ) {
		return $this->check_capability( 'bbgf_manage_services' );
	}

	/**
	 * Permission check for deleting a package.
	 *
	 * @param WP_REST_Request $request Request instance.
	 * @return true|WP_Error
	 */
	public function delete_item_permissions_check( $request ) {
		return $this->check_capability( 'bbgf_manage_services' );
	}

	/**
	 * Retrieve a collection of packages.
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

		$package_ids = array();
		$total       = 0;

		if ( '' !== $search ) {
			$like      = '%' . $this->wpdb->esc_like( $search ) . '%';
			$base_from = sprintf(
				'%s AS packages
			LEFT JOIN %s AS items ON packages.id = items.package_id
			LEFT JOIN %s AS services ON items.service_id = services.id',
				$this->tables['service_packages'],
				$this->tables['service_package_items'],
				$this->tables['services']
			);

			$count_sql = $this->wpdb->prepare(
				"SELECT COUNT( DISTINCT packages.id )
				FROM {$base_from}
				WHERE packages.name LIKE %s OR services.name LIKE %s",
				$like,
				$like
			);

			$ids_sql = $this->wpdb->prepare(
				"SELECT DISTINCT packages.id
				FROM {$base_from}
				WHERE packages.name LIKE %s OR services.name LIKE %s
				ORDER BY packages.name ASC
				LIMIT %d OFFSET %d",
				$like,
				$like,
				$per_page,
				$offset
			);

			$total       = (int) $this->wpdb->get_var( $count_sql );
			$package_ids = array_map( 'intval', $this->wpdb->get_col( $ids_sql ) );
		} else {
			$count_sql = "SELECT COUNT(*) FROM {$this->tables['service_packages']}";
			$ids_sql   = $this->wpdb->prepare(
				"SELECT id
				FROM {$this->tables['service_packages']}
				ORDER BY name ASC
				LIMIT %d OFFSET %d",
				$per_page,
				$offset
			);

			$total       = (int) $this->wpdb->get_var( $count_sql );
			$package_ids = array_map( 'intval', $this->wpdb->get_col( $ids_sql ) );
		}

		$packages = $this->get_packages_by_ids( $package_ids );
		$packages = $this->hydrate_package_services( $packages );

		$data = array();
		foreach ( $packages as $package ) {
			$item   = $this->prepare_item_for_response( $package, $request );
			$data[] = $this->prepare_response_for_collection( $item );
		}

		$response = rest_ensure_response( $data );
		$response->header( 'X-WP-Total', (string) $total );
		$total_pages = (int) ceil( $total / $per_page );
		$response->header( 'X-WP-TotalPages', (string) max( 1, $total_pages ) );

		return $response;
	}

	/**
	 * Retrieve a single package.
	 *
	 * @param WP_REST_Request $request Request instance.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_item( $request ) {
		$package_id = (int) $request->get_param( 'id' );
		$package    = $this->get_package( $package_id );

		if ( null === $package ) {
			return new WP_Error(
				'bbgf_package_not_found',
				__( 'Package not found.', 'bb-groomflow' ),
				array( 'status' => 404 )
			);
		}

		return $this->prepare_item_for_response( $package, $request );
	}

	/**
	 * Create a new package.
	 *
	 * @param WP_REST_Request $request Request instance.
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_item( $request ) {
		$validated = $this->validate_and_prepare_item( $request );
		if ( is_wp_error( $validated ) ) {
			return $validated;
		}

		$record               = $validated['record'];
		$services             = $validated['services'];
		$clear_price          = (bool) $validated['clear_price'];
		$now                  = $this->plugin->now();
		$record['slug']       = $this->plugin->unique_slug( $record['name'], $this->tables['service_packages'], 'slug' );
		$record['created_at'] = $now;
		$record['updated_at'] = $now;

		$data_and_formats = $this->filter_null_columns( $record );
		$data             = $data_and_formats['data'];
		$formats          = $data_and_formats['formats'];

		$this->wpdb->insert( $this->tables['service_packages'], $data, $formats );
		$package_id = (int) $this->wpdb->insert_id;

		$this->replace_package_services( $package_id, $services );

		if ( $clear_price ) {
			$this->wpdb->query(
				$this->wpdb->prepare(
					"UPDATE {$this->tables['service_packages']} SET price = NULL WHERE id = %d",
					$package_id
				)
			);
		}

		$package = $this->get_package( $package_id );
		if ( null === $package ) {
			return new WP_Error(
				'bbgf_package_not_found',
				__( 'Package not found after creation.', 'bb-groomflow' ),
				array( 'status' => 500 )
			);
		}

		$response = $this->prepare_item_for_response( $package, $request );
		$response->set_status( 201 );
		$response->header(
			'Location',
			rest_url( sprintf( '%s/%s/%d', $this->namespace, $this->rest_base, $package_id ) )
		);

		$this->plugin->visit_service()->flush_cache();

		return $response;
	}

	/**
	 * Update an existing package.
	 *
	 * @param WP_REST_Request $request Request instance.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_item( $request ) {
		$package_id = (int) $request->get_param( 'id' );
		$current    = $this->get_package( $package_id );

		if ( null === $current ) {
			return new WP_Error(
				'bbgf_package_not_found',
				__( 'Package not found.', 'bb-groomflow' ),
				array( 'status' => 404 )
			);
		}

		$validated = $this->validate_and_prepare_item( $request, $package_id, $current );
		if ( is_wp_error( $validated ) ) {
			return $validated;
		}

		$record      = $validated['record'];
		$services    = $validated['services'];
		$clear_price = (bool) $validated['clear_price'];

		$record['slug']       = $this->plugin->unique_slug( $record['name'], $this->tables['service_packages'], 'slug', $package_id );
		$record['updated_at'] = $this->plugin->now();

		$data_and_formats = $this->filter_null_columns( $record );
		$data             = $data_and_formats['data'];
		$formats          = $data_and_formats['formats'];

		$this->wpdb->update(
			$this->tables['service_packages'],
			$data,
			array( 'id' => $package_id ),
			$formats,
			array( '%d' )
		);

		if ( $clear_price ) {
			$this->wpdb->query(
				$this->wpdb->prepare(
					"UPDATE {$this->tables['service_packages']} SET price = NULL WHERE id = %d",
					$package_id
				)
			);
		}

		$this->replace_package_services( $package_id, $services );

		$package = $this->get_package( $package_id );
		if ( null === $package ) {
			return new WP_Error(
				'bbgf_package_not_found',
				__( 'Package not found after update.', 'bb-groomflow' ),
				array( 'status' => 500 )
			);
		}

		$this->plugin->visit_service()->flush_cache();

		return $this->prepare_item_for_response( $package, $request );
	}

	/**
	 * Delete a package.
	 *
	 * @param WP_REST_Request $request Request instance.
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete_item( $request ) {
		$package_id = (int) $request->get_param( 'id' );
		$package    = $this->get_package( $package_id );

		if ( null === $package ) {
			return new WP_Error(
				'bbgf_package_not_found',
				__( 'Package not found.', 'bb-groomflow' ),
				array( 'status' => 404 )
			);
		}

		$this->wpdb->delete( $this->tables['service_packages'], array( 'id' => $package_id ), array( '%d' ) );
		$this->wpdb->delete( $this->tables['service_package_items'], array( 'package_id' => $package_id ), array( '%d' ) );

		$this->plugin->visit_service()->flush_cache();

		$response = array(
			'deleted'  => true,
			'previous' => $this->prepare_item_for_response( $package, $request )->get_data(),
		);

		return rest_ensure_response( $response );
	}

	/**
	 * Prepare output response for a single package.
	 *
	 * @param array           $item    Package data including services.
	 * @param WP_REST_Request $request Request instance.
	 * @return WP_REST_Response
	 */
	public function prepare_item_for_response( $item, $request ): WP_REST_Response {
		$color = isset( $item['color'] ) ? trim( (string) $item['color'] ) : '';
		$price = $item['price'] ?? null;

		$services = array();
		if ( isset( $item['services'] ) && is_array( $item['services'] ) ) {
			foreach ( $item['services'] as $service ) {
				$services[] = array(
					'id'         => (int) ( $service['service_id'] ?? 0 ),
					'name'       => (string) ( $service['name'] ?? '' ),
					'icon'       => (string) ( $service['icon'] ?? '' ),
					'sort_order' => (int) ( $service['sort_order'] ?? 0 ),
				);
			}
		}

		$description = isset( $item['description'] ) ? trim( (string) $item['description'] ) : '';

		$data = array(
			'id'          => (int) $item['id'],
			'name'        => $item['name'],
			'slug'        => $item['slug'],
			'icon'        => $item['icon'],
			'color'       => '' !== $color ? $item['color'] : null,
			'price'       => ( null === $price || '' === $price ) ? null : round( (float) $price, 2 ),
			'description' => '' !== $description ? $item['description'] : null,
			'services'    => $services,
			'created_at'  => isset( $item['created_at'] ) ? mysql_to_rfc3339( $item['created_at'] ) : null,
			'updated_at'  => isset( $item['updated_at'] ) ? mysql_to_rfc3339( $item['updated_at'] ) : null,
		);

		return rest_ensure_response( $data );
	}

	/**
	 * Provide JSON schema for packages.
	 *
	 * @return array<string,mixed>
	 */
	public function get_item_schema(): array {
		if ( null !== $this->schema ) {
			return $this->schema;
		}

		$this->schema = array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'bbgf_package',
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
				'icon'        => array(
					'type'    => 'string',
					'context' => array( 'view', 'edit' ),
				),
				'color'       => array(
					'type'    => array( 'string', 'null' ),
					'context' => array( 'view', 'edit' ),
					'pattern' => '^#([A-Fa-f0-9]{3}){1,2}$',
				),
				'price'       => array(
					'type'    => array( 'number', 'null' ),
					'context' => array( 'view', 'edit' ),
				),
				'description' => array(
					'type'    => array( 'string', 'null' ),
					'context' => array( 'view', 'edit' ),
				),
				'services'    => array(
					'type'    => 'array',
					'context' => array( 'view', 'edit' ),
					'items'   => array(
						'type'       => array( 'object', 'integer' ),
						'properties' => array(
							'id'         => array(
								'type'     => 'integer',
								'required' => true,
							),
							'sort_order' => array(
								'type' => 'integer',
							),
						),
					),
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
	 * Fetch package records for a set of IDs.
	 *
	 * @param array<int,int> $ids Package IDs.
	 * @return array<int,array<string,mixed>>
	 */
	private function get_packages_by_ids( array $ids ): array {
		if ( empty( $ids ) ) {
			return array();
		}

		$ids = array_values( array_unique( array_map( 'intval', $ids ) ) );

		$id_list = implode( ',', $ids );
		$sql     = "SELECT id, name, slug, icon, color, price, description, created_at, updated_at
		FROM {$this->tables['service_packages']}
		WHERE id IN ({$id_list})";

		$rows = $this->wpdb->get_results( $sql, ARRAY_A );
		if ( empty( $rows ) ) {
			return array();
		}

		$map = array();
		foreach ( $rows as $row ) {
			$map[ (int) $row['id'] ] = $row;
		}

		$ordered = array();
		foreach ( $ids as $id ) {
			if ( isset( $map[ $id ] ) ) {
				$ordered[] = $map[ $id ];
			}
		}

		return $ordered;
	}

	/**
	 * Retrieve a single package including its services.
	 *
	 * @param int $package_id Package ID.
	 * @return array<string,mixed>|null
	 */
	private function get_package( int $package_id ): ?array {
		if ( $package_id <= 0 ) {
			return null;
		}

		$sql = $this->wpdb->prepare(
			"SELECT id, name, slug, icon, color, price, description, created_at, updated_at
			FROM {$this->tables['service_packages']}
			WHERE id = %d",
			$package_id
		);

		$row = $this->wpdb->get_row( $sql, ARRAY_A );
		if ( ! $row ) {
			return null;
		}

		$packages = array( $row );
		$packages = $this->hydrate_package_services( $packages );

		return $packages[0];
	}

	/**
	 * Attach service relationships to packages.
	 *
	 * @param array<int,array<string,mixed>> $packages Package records.
	 * @return array<int,array<string,mixed>>
	 */
	private function hydrate_package_services( array $packages ): array {
		if ( empty( $packages ) ) {
			return $packages;
		}

		$ids = array();
		foreach ( $packages as $package ) {
			if ( isset( $package['id'] ) ) {
				$ids[] = (int) $package['id'];
			}
		}

		$ids = array_values( array_unique( array_filter( $ids ) ) );
		if ( empty( $ids ) ) {
			return $packages;
		}

		$id_list = implode( ',', $ids );
		$sql     = "SELECT items.package_id, items.service_id, items.sort_order, services.name, services.icon
		FROM {$this->tables['service_package_items']} AS items
		LEFT JOIN {$this->tables['services']} AS services ON services.id = items.service_id
		WHERE items.package_id IN ({$id_list})
		ORDER BY items.package_id ASC, items.sort_order ASC, items.service_id ASC";

		$rows     = $this->wpdb->get_results( $sql, ARRAY_A );
		$services = array();

		foreach ( $rows as $row ) {
			$package_id = (int) $row['package_id'];
			if ( ! isset( $services[ $package_id ] ) ) {
				$services[ $package_id ] = array();
			}

			$services[ $package_id ][] = array(
				'service_id' => (int) $row['service_id'],
				'sort_order' => (int) $row['sort_order'],
				'name'       => (string) ( $row['name'] ?? '' ),
				'icon'       => (string) ( $row['icon'] ?? '' ),
			);
		}

		foreach ( $packages as &$package ) {
			$package_id          = (int) $package['id'];
			$package['services'] = $services[ $package_id ] ?? array();
		}
		unset( $package );

		return $packages;
	}

	/**
	 * Replace package service assignments.
	 *
	 * @param int                          $package_id Package ID.
	 * @param array<int,array<string,int>> $services   Normalised service data.
	 */
	private function replace_package_services( int $package_id, array $services ): void {
		$package_id = max( 0, $package_id );
		if ( $package_id <= 0 ) {
			return;
		}

		$this->wpdb->delete( $this->tables['service_package_items'], array( 'package_id' => $package_id ), array( '%d' ) );

		if ( empty( $services ) ) {
			return;
		}

		$sort_position = 1;
		foreach ( $services as $service ) {
			$service_id = max( 0, (int) ( $service['id'] ?? 0 ) );
			if ( $service_id <= 0 ) {
				continue;
			}

			$this->wpdb->insert(
				$this->tables['service_package_items'],
				array(
					'package_id' => $package_id,
					'service_id' => $service_id,
					'sort_order' => $sort_position++,
				),
				array( '%d', '%d', '%d' )
			);
		}
	}

	/**
	 * Validate and sanitise request data.
	 *
	 * @param WP_REST_Request          $request    Request instance.
	 * @param int                      $package_id Existing package ID when updating.
	 * @param array<string,mixed>|null $existing   Existing package data.
	 * @return array<string,mixed>|WP_Error
	 */
	private function validate_and_prepare_item( WP_REST_Request $request, int $package_id = 0, ?array $existing = null ) {
		$is_update = ( $package_id > 0 && is_array( $existing ) );

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
				'bbgf_package_missing_name',
				__( 'Package name is required.', 'bb-groomflow' ),
				array( 'status' => 400 )
			);
		}

		$has_icon = $request->has_param( 'icon' );
		if ( $has_icon ) {
			$icon = sanitize_text_field( (string) $request->get_param( 'icon' ) );
		} else {
			$icon = sanitize_text_field( (string) ( $existing['icon'] ?? '' ) );
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
						'bbgf_package_invalid_color',
						__( 'Color must be a valid hex value (e.g. #AABBCC).', 'bb-groomflow' ),
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

		$has_price = $request->has_param( 'price' );
		if ( $has_price ) {
			$price_param = $request->get_param( 'price' );
			if ( null === $price_param || '' === $price_param ) {
				$price = null;
			} elseif ( ! is_numeric( $price_param ) ) {
				return new WP_Error(
					'bbgf_package_invalid_price',
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

		$existing_services = array();
		if ( $is_update && isset( $existing['services'] ) && is_array( $existing['services'] ) ) {
			foreach ( $existing['services'] as $service ) {
				$existing_services[] = array(
					'id'         => (int) ( $service['service_id'] ?? 0 ),
					'sort_order' => (int) ( $service['sort_order'] ?? 0 ),
				);
			}
		}

		$services_param = $request->get_param( 'services' );
		if ( $request->has_param( 'services' ) ) {
			$services = $this->normalise_services_input( $services_param );
			if ( is_wp_error( $services ) ) {
				return $services;
			}
		} elseif ( $is_update ) {
			$services = $existing_services;
		} else {
			$services = array();
		}

		if ( empty( $services ) ) {
			return new WP_Error(
				'bbgf_package_missing_services',
				__( 'Select at least one service for the package.', 'bb-groomflow' ),
				array( 'status' => 400 )
			);
		}

		$validated_services = $this->validate_service_ids( $services );
		if ( is_wp_error( $validated_services ) ) {
			return $validated_services;
		}

		$clear_price = ( $has_price && null === $price );

		return array(
			'record'      => array(
				'name'        => sanitize_text_field( $name ),
				'icon'        => $icon,
				'color'       => $color,
				'price'       => $price,
				'description' => $description,
			),
			'services'    => $validated_services,
			'clear_price' => $clear_price,
		);
	}

	/**
	 * Normalise services payload into ID/sort order pairs.
	 *
	 * @param mixed $input Raw services input.
	 * @return array<int,array<string,int>>|WP_Error
	 */
	private function normalise_services_input( $input ) {
		if ( ! is_array( $input ) ) {
			return new WP_Error(
				'bbgf_package_invalid_services',
				__( 'Services must be provided as an array.', 'bb-groomflow' ),
				array( 'status' => 400 )
			);
		}

		$items = array();
		$index = 1;

		foreach ( $input as $entry ) {
			if ( is_array( $entry ) ) {
				$service_id = isset( $entry['id'] ) ? absint( $entry['id'] ) : 0;
				$sort_order = isset( $entry['sort_order'] ) ? (int) $entry['sort_order'] : $index;
			} else {
				$service_id = absint( $entry );
				$sort_order = $index;
			}

			if ( $service_id <= 0 ) {
				continue;
			}

			if ( $sort_order <= 0 ) {
				$sort_order = $index;
			}

			if ( isset( $items[ $service_id ] ) ) {
				continue;
			}

			$items[ $service_id ] = array(
				'id'         => $service_id,
				'sort_order' => $sort_order,
			);

			++$index;
		}

		if ( empty( $items ) ) {
			return array();
		}

		$items = array_values( $items );
		usort(
			$items,
			static function ( $a, $b ) {
				return $a['sort_order'] <=> $b['sort_order'];
			}
		);

		$position = 1;
		foreach ( $items as &$item ) {
			$item['sort_order'] = $position++;
		}
		unset( $item );

		return $items;
	}

	/**
	 * Confirm services exist in the catalog.
	 *
	 * @param array<int,array<string,int>> $services Normalised services.
	 * @return array<int,array<string,int>>|WP_Error
	 */
	private function validate_service_ids( array $services ) {
		if ( empty( $services ) ) {
			return array();
		}

		$ids = array();
		foreach ( $services as $service ) {
			$ids[] = (int) $service['id'];
		}

		$ids = array_values( array_unique( array_filter( $ids ) ) );
		if ( empty( $ids ) ) {
			return new WP_Error(
				'bbgf_package_missing_services',
				__( 'Select at least one service for the package.', 'bb-groomflow' ),
				array( 'status' => 400 )
			);
		}

		$id_list = implode( ',', $ids );
		$sql     = "SELECT id FROM {$this->tables['services']} WHERE id IN ({$id_list})";

		$found_ids = array_map( 'intval', $this->wpdb->get_col( $sql ) );
		$missing   = array_diff( $ids, $found_ids );

		if ( ! empty( $missing ) ) {
			return new WP_Error(
				'bbgf_package_invalid_service',
				__( 'One or more selected services no longer exist.', 'bb-groomflow' ),
				array( 'status' => 400 )
			);
		}

		return $services;
	}

	/**
	 * Filter null values before database operations.
	 *
	 * @param array<string,mixed> $data Raw data.
	 * @return array{data:array<string,mixed>,formats:array<int,string>}
	 */
	private function filter_null_columns( array $data ): array {
		$format_map = array(
			'name'        => '%s',
			'slug'        => '%s',
			'icon'        => '%s',
			'color'       => '%s',
			'price'       => '%f',
			'description' => '%s',
			'created_at'  => '%s',
			'updated_at'  => '%s',
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
	// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared
}
