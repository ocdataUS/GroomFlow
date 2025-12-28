<?php
/**
 * Visits REST controller.
 *
 * @package BB_GroomFlow
 */

namespace BBGF\API;

use BBGF\Plugin;
use BBGF\Data\Visit_Service;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Provides REST endpoints for visit intake, updates, stage moves, and board listings.
 */
class Visits_Controller extends REST_Controller {

	/**
	 * REST route base.
	 *
	 * @var string
	 */
	protected $rest_base = 'visits';

	/**
	 * Plugin instance.
	 *
	 * @var Plugin
	 */
	private Plugin $plugin;

	/**
	 * Visit service.
	 *
	 * @var Visit_Service
	 */
	private Visit_Service $visit_service;

	/**
	 * Cached schema definition.
	 *
	 * @var array<string,mixed>|null
	 */
	protected $schema = null;

	/**
	 * Constructor.
	 *
	 * @param Plugin        $plugin        Plugin instance.
	 * @param Visit_Service $visit_service Visit service.
	 */
	public function __construct( Plugin $plugin, Visit_Service $visit_service ) {
		parent::__construct();

		$this->plugin        = $plugin;
		$this->visit_service = $visit_service;
	}

	/**
	 * Register REST routes.
	 */
	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/board',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_board' ),
					'permission_callback' => array( $this, 'get_board_permissions_check' ),
					'args'                => array(
						'view'           => array(
							'description' => __( 'View slug.', 'bb-groomflow' ),
							'type'        => 'string',
							'required'    => false,
						),
						'stages'         => array(
							'description' => __( 'Limit response to specific stage keys.', 'bb-groomflow' ),
							'type'        => 'array',
							'items'       => array(
								'type' => 'string',
							),
						),
						'modified_after' => array(
							'description' => __( 'Only return visits modified after this ISO8601 timestamp.', 'bb-groomflow' ),
							'type'        => 'string',
							'format'      => 'date-time',
						),
						'public_token'   => array(
							'description' => __( 'Public board token for lobby/kiosk views.', 'bb-groomflow' ),
							'type'        => 'string',
						),
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_item' ),
					'permission_callback' => array( $this, 'create_item_permissions_check' ),
					'args'                => $this->get_endpoint_args_for_item_schema( true ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/intake-search',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'search_intake' ),
					'permission_callback' => array( $this, 'create_item_permissions_check' ),
					'args'                => array(
						'query' => array(
							'description' => __( 'Search across clients and guardians.', 'bb-groomflow' ),
							'type'        => 'string',
						),
						'limit' => array(
							'description' => __( 'Maximum number of matches to return.', 'bb-groomflow' ),
							'type'        => 'integer',
							'default'     => 10,
							'minimum'     => 1,
							'maximum'     => 20,
						),
					),
				),
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
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_item' ),
					'permission_callback' => array( $this, 'update_item_permissions_check' ),
					'args'                => $this->get_endpoint_args_for_item_schema( false ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			sprintf( '/%s/(?P<id>\\d+)/move', $this->rest_base ),
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'move_visit' ),
					'permission_callback' => array( $this, 'move_visit_permissions_check' ),
					'args'                => array(
						'to_stage' => array(
							'type'        => 'string',
							'required'    => true,
							'description' => __( 'Destination stage key.', 'bb-groomflow' ),
						),
						'comment'  => array(
							'type'        => 'string',
							'description' => __( 'Optional move comment.', 'bb-groomflow' ),
						),
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			sprintf( '/%s/(?P<id>\\d+)/checkout', $this->rest_base ),
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'checkout_visit' ),
					'permission_callback' => array( $this, 'checkout_visit_permissions_check' ),
					'args'                => array(
						'comment' => array(
							'type'        => 'string',
							'description' => __( 'Optional checkout note.', 'bb-groomflow' ),
						),
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			sprintf( '/%s/(?P<id>\\d+)/photo', $this->rest_base ),
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'add_photo' ),
					'permission_callback' => array( $this, 'add_photo_permissions_check' ),
					'args'                => array(
						'attachment_id'       => array(
							'type'        => 'integer',
							'description' => __( 'Existing attachment ID to associate.', 'bb-groomflow' ),
						),
						'visible_to_guardian' => array(
							'type'        => 'boolean',
							'description' => __( 'Whether the photo can be shown to guardians.', 'bb-groomflow' ),
						),
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			sprintf( '/%s/(?P<id>\\d+)/photo/(?P<photo_id>\\d+)', $this->rest_base ),
			array(
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_photo' ),
					'permission_callback' => array( $this, 'add_photo_permissions_check' ),
					'args'                => array(
						'visible_to_guardian' => array(
							'type'        => 'boolean',
							'description' => __( 'Whether the photo can be shown to guardians.', 'bb-groomflow' ),
						),
						'is_primary'          => array(
							'type'        => 'boolean',
							'description' => __( 'Mark the photo as the primary visit image.', 'bb-groomflow' ),
						),
					),
				),
			)
		);
	}

	/**
	 * Determine if current user can view the board endpoint.
	 *
	 * @param WP_REST_Request $request Request instance.
	 * @return true|WP_Error
	 */
	public function get_board_permissions_check( $request ) {
		$public_token = $request->get_param( 'public_token' );
		if ( null !== $public_token && '' !== $public_token ) {
			return true;
		}

		return $this->check_capability( 'bbgf_view_board' );
	}

	/**
	 * Permission check for intake creation.
	 *
	 * @param WP_REST_Request $request Request instance.
	 * @return true|WP_Error
	 */
	public function create_item_permissions_check( $request ) {
		return $this->check_capability( 'bbgf_edit_visits' );
	}

	/**
	 * Permission check for retrieving a visit.
	 *
	 * @param WP_REST_Request $request Request instance.
	 * @return true|WP_Error
	 */
	public function get_item_permissions_check( $request ) {
		return $this->check_capability( 'bbgf_edit_visits' );
	}

	/**
	 * Permission check for updating a visit.
	 *
	 * @param WP_REST_Request $request Request instance.
	 * @return true|WP_Error
	 */
	public function update_item_permissions_check( $request ) {
		return $this->check_capability( 'bbgf_edit_visits' );
	}

	/**
	 * Permission check for moving a visit between stages.
	 *
	 * @param WP_REST_Request $request Request instance.
	 * @return true|WP_Error
	 */
	public function move_visit_permissions_check( $request ) {
		return $this->check_capability( 'bbgf_move_stages' );
	}

	/**
	 * Permission check for checking out a visit.
	 *
	 * @param WP_REST_Request $request Request instance.
	 * @return true|WP_Error
	 */
	public function checkout_visit_permissions_check( $request ) {
		return $this->check_capability( 'bbgf_edit_visits' );
	}

	/**
	 * Permission check for uploading or linking a visit photo.
	 *
	 * @param WP_REST_Request $request Request instance.
	 * @return true|WP_Error
	 */
	public function add_photo_permissions_check( $request ) {
		return $this->check_capability( 'bbgf_edit_visits' );
	}

	/**
	 * Retrieve board data for a view.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_board( $request ) {
		$view_param     = $request->get_param( 'view' );
		$public_token   = (string) $request->get_param( 'public_token' );
		$modified_after = $request->get_param( 'modified_after' );
		$stages_filter  = $request->get_param( 'stages' );

		$view = null;
		if ( is_string( $view_param ) && '' !== $view_param ) {
			$view = $this->visit_service->get_view_by_slug( $view_param );
		} else {
			$view = $this->visit_service->get_default_view();
		}

		if ( null === $view ) {
			return new WP_Error(
				'bbgf_view_not_found',
				__( 'Requested view could not be found.', 'bb-groomflow' ),
				array( 'status' => 404 )
			);
		}

		$is_public_request = '' !== $public_token;
		if ( $is_public_request ) {
			if ( ! $this->visit_service->verify_public_token( $view, $public_token ) ) {
				return new WP_Error(
					'bbgf_view_invalid_token',
					__( 'This view requires a valid public token.', 'bb-groomflow' ),
					array( 'status' => 401 )
				);
			}
		} else {
			$permission = $this->check_capability( 'bbgf_view_board' );
			if ( is_wp_error( $permission ) ) {
				return $permission;
			}
		}

		$view_type             = isset( $view['type'] ) ? (string) $view['type'] : 'internal';
		$masked_view_types     = array( 'lobby', 'kiosk' );
		$should_mask_guardian  = $is_public_request
			|| in_array( $view_type, $masked_view_types, true )
			|| ! (bool) $view['show_guardian'];
		$mask_sensitive_fields = $is_public_request || in_array( $view_type, $masked_view_types, true );
		$is_read_only          = $is_public_request || in_array( $view_type, $masked_view_types, true );

		if ( is_string( $stages_filter ) && '' !== $stages_filter ) {
			$stages_filter = array( $stages_filter );
		}

		$payload = $this->visit_service->build_board_payload(
			array(
				'view'           => $view,
				'modified_after' => $modified_after,
				'stages'         => is_array( $stages_filter ) ? $stages_filter : array(),
				'mask_guardian'  => $should_mask_guardian,
				'mask_sensitive' => $mask_sensitive_fields,
				'readonly'       => $is_read_only,
				'is_public'      => $is_public_request,
			)
		);

		return rest_ensure_response( $payload );
	}

	/**
	 * Retrieve a single visit.
	 *
	 * @param WP_REST_Request $request Request instance.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_item( $request ) {
		$visit_id     = (int) $request->get_param( 'id' );
		$public_token = (string) $request->get_param( 'public_token' );
		$view_slug    = (string) $request->get_param( 'view' );
		$is_public    = '' !== $public_token;
		$view         = null;

		if ( $is_public ) {
			if ( '' === $view_slug ) {
				return new WP_Error(
					'bbgf_visit_view_required',
					__( 'View slug is required when using a public token.', 'bb-groomflow' ),
					array( 'status' => 400 )
				);
			}

			$view = $this->visit_service->get_view_by_slug( $view_slug );
			if ( null === $view ) {
				return new WP_Error(
					'bbgf_view_not_found',
					__( 'Requested view could not be found.', 'bb-groomflow' ),
					array( 'status' => 404 )
				);
			}

			if ( ! $this->visit_service->verify_public_token( $view, $public_token ) ) {
				return new WP_Error(
					'bbgf_view_invalid_token',
					__( 'This view requires a valid public token.', 'bb-groomflow' ),
					array( 'status' => 401 )
				);
			}
		} else {
			$permission = $this->check_capability( 'bbgf_edit_visits' );
			if ( is_wp_error( $permission ) ) {
				return $permission;
			}
		}

		$should_mask_guardian  = $is_public || ( $view ? ! (bool) $view['show_guardian'] : false );
		$mask_sensitive_fields = $is_public || ( $view && in_array( $view['type'], array( 'lobby', 'kiosk' ), true ) );

		$visit = $this->visit_service->get_visit(
			$visit_id,
			array(
				'mask_guardian'    => $should_mask_guardian,
				'mask_sensitive'   => $mask_sensitive_fields,
				'include_history'  => ! $is_public,
				'include_previous' => ! $is_public,
			)
		);

		if ( null === $visit ) {
			return new WP_Error(
				'bbgf_visit_not_found',
				__( 'Visit not found.', 'bb-groomflow' ),
				array( 'status' => 404 )
			);
		}

		if ( $is_public && $view && (int) $visit['view_id'] !== (int) $view['id'] ) {
			return new WP_Error(
				'bbgf_visit_not_in_view',
				__( 'Visit is not available for the requested view.', 'bb-groomflow' ),
				array( 'status' => 404 )
			);
		}

		return rest_ensure_response( $visit );
	}

	/**
	 * Intake search across clients and guardians.
	 *
	 * @param WP_REST_Request $request Request instance.
	 * @return WP_REST_Response
	 */
	public function search_intake( $request ) {
		$query = (string) $request->get_param( 'query' );
		$limit = (int) $request->get_param( 'limit' );
		$limit = max( 1, min( 20, $limit ) );

		$results = $this->visit_service->search_intake_entities( $query, $limit );

		return rest_ensure_response(
			array(
				'items' => $results,
			)
		);
	}

	/**
	 * Create a visit intake.
	 *
	 * @param WP_REST_Request $request Request instance.
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_item( $request ) {
		if ( $this->is_public_token_request( $request ) ) {
			return new WP_Error(
				'bbgf_visit_public_readonly',
				__( 'Public board access is read-only.', 'bb-groomflow' ),
				array( 'status' => 403 )
			);
		}

		$validated = $this->validate_visit_payload( $request );
		if ( is_wp_error( $validated ) ) {
			return $validated;
		}

		$result = $this->visit_service->create_visit( $validated['visit'], $validated['services'], $validated['flags'] );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$response = new WP_REST_Response( $result, 201 );
		$response->header(
			'Location',
			rest_url( sprintf( '%s/%s/%d', $this->namespace, $this->rest_base, $result['id'] ) )
		);

		return $response;
	}

	/**
	 * Update a visit.
	 *
	 * @param WP_REST_Request $request Request instance.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_item( $request ) {
		if ( $this->is_public_token_request( $request ) ) {
			return new WP_Error(
				'bbgf_visit_public_readonly',
				__( 'Public board access is read-only.', 'bb-groomflow' ),
				array( 'status' => 403 )
			);
		}

		$visit_id    = (int) $request->get_param( 'id' );
		$current_row = $this->visit_service->get_visit_row( $visit_id );
		if ( null === $current_row ) {
			return new WP_Error(
				'bbgf_visit_not_found',
				__( 'Visit not found.', 'bb-groomflow' ),
				array( 'status' => 404 )
			);
		}

		$validated = $this->validate_visit_payload( $request, $current_row );
		if ( is_wp_error( $validated ) ) {
			return $validated;
		}

		$result = $this->visit_service->update_visit( $visit_id, $validated['visit'], $validated['services'], $validated['flags'], $current_row );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( $result );
	}

	/**
	 * Move a visit to a new stage and record history.
	 *
	 * @param WP_REST_Request $request Request instance.
	 * @return WP_REST_Response|WP_Error
	 */
	public function move_visit( $request ) {
		if ( $this->is_public_token_request( $request ) ) {
			return new WP_Error(
				'bbgf_visit_public_readonly',
				__( 'Public board access is read-only.', 'bb-groomflow' ),
				array( 'status' => 403 )
			);
		}

		$visit_id    = (int) $request->get_param( 'id' );
		$destination = sanitize_key( (string) $request->get_param( 'to_stage' ) );
		if ( '' === $destination ) {
			return new WP_Error(
				'bbgf_visit_invalid_stage',
				__( 'Destination stage is required.', 'bb-groomflow' ),
				array( 'status' => 400 )
			);
		}

		$comment = (string) $request->get_param( 'comment' );
		$user    = wp_get_current_user();
		$user_id = $user ? (int) $user->ID : 0;

		$result = $this->visit_service->move_visit( $visit_id, $destination, $comment, $user_id );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( $result );
	}

	/**
	 * Check out a visit and remove it from active boards.
	 *
	 * @param WP_REST_Request $request Request instance.
	 * @return WP_REST_Response|WP_Error
	 */
	public function checkout_visit( $request ) {
		if ( $this->is_public_token_request( $request ) ) {
			return new WP_Error(
				'bbgf_visit_public_readonly',
				__( 'Public board access is read-only.', 'bb-groomflow' ),
				array( 'status' => 403 )
			);
		}

		$visit_id = (int) $request->get_param( 'id' );
		$comment  = (string) $request->get_param( 'comment' );
		$user     = wp_get_current_user();
		$user_id  = $user ? (int) $user->ID : 0;

		$result = $this->visit_service->checkout_visit( $visit_id, $user_id, $comment );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( $result );
	}

	/**
	 * Attach or upload a photo for a visit.
	 *
	 * @param WP_REST_Request $request Request instance.
	 * @return WP_REST_Response|WP_Error
	 */
	public function add_photo( $request ) {
		if ( $this->is_public_token_request( $request ) ) {
			return new WP_Error(
				'bbgf_visit_public_readonly',
				__( 'Public board access is read-only.', 'bb-groomflow' ),
				array( 'status' => 403 )
			);
		}

		$visit_id            = (int) $request->get_param( 'id' );
		$files               = $request->get_file_params();
		$visible             = $request->get_param( 'visible_to_guardian' );
		$visible_to_guardian = null === $visible ? true : (bool) $visible;

		if ( isset( $files['file'] ) && ! empty( $files['file']['name'] ) ) {
			if ( ! current_user_can( 'upload_files' ) ) {
				return new WP_Error(
					'bbgf_visit_photo_capability',
					__( 'You are not allowed to upload files.', 'bb-groomflow' ),
					array( 'status' => 403 )
				);
			}

			$result = $this->visit_service->attach_uploaded_photo( $visit_id, $files['file'], $visible_to_guardian );
		} else {
			$attachment_id = (int) $request->get_param( 'attachment_id' );
			if ( $attachment_id <= 0 ) {
				return new WP_Error(
					'bbgf_visit_photo_missing',
					__( 'Provide an attachment_id or upload a file.', 'bb-groomflow' ),
					array( 'status' => 400 )
				);
			}

			$result = $this->visit_service->attach_existing_photo( $visit_id, $attachment_id, $visible_to_guardian );
		}

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return new WP_REST_Response( $result, 201 );
	}

	/**
	 * Update photo metadata for a visit.
	 *
	 * @param WP_REST_Request $request Request instance.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_photo( $request ) {
		if ( $this->is_public_token_request( $request ) ) {
			return new WP_Error(
				'bbgf_visit_public_readonly',
				__( 'Public board access is read-only.', 'bb-groomflow' ),
				array( 'status' => 403 )
			);
		}

		$visit_id   = (int) $request->get_param( 'id' );
		$photo_id   = (int) $request->get_param( 'photo_id' );
		$visible    = $request->get_param( 'visible_to_guardian' );
		$is_primary = $request->get_param( 'is_primary' );
		$is_public  = $this->is_public_token_request( $request );

		$capability_check = $this->check_capability( 'bbgf_edit_visits' ); // phpcs:ignore WordPress.WP.Capabilities.Unknown
		if ( is_wp_error( $capability_check ) ) {
			return $capability_check;
		}

		$visible_to_guardian = null === $visible ? null : (bool) $visible;

		$response = null;

		if ( null !== $visible_to_guardian ) {
			$response = $this->visit_service->update_photo_visibility( $visit_id, $photo_id, $visible_to_guardian );
			if ( is_wp_error( $response ) ) {
				return $response;
			}
		}

		if ( null !== $is_primary ) {
			$response = $this->visit_service->set_primary_photo( $visit_id, $photo_id );
			if ( is_wp_error( $response ) ) {
				return $response;
			}
		}

		if ( null === $response ) {
			$response = $this->visit_service->get_visit( $visit_id );
		}

		return rest_ensure_response( $response );
	}

	/**
	 * Prepare JSON schema for visits.
	 *
	 * @return array<string,mixed>
	 */
	public function get_item_schema(): array {
		if ( null !== $this->schema ) {
			return $this->schema;
		}

		$this->schema = array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'bbgf_visit',
			'type'       => 'object',
			'properties' => array(
				'id'              => array(
					'type'     => 'integer',
					'context'  => array( 'view', 'edit' ),
					'readonly' => true,
				),
				'client_id'       => array(
					'type'    => 'integer',
					'context' => array( 'view', 'edit' ),
				),
				'client'          => array(
					'type'       => 'object',
					'context'    => array( 'edit' ),
					'properties' => array(
						'name'        => array(
							'type' => 'string',
						),
						'guardian_id' => array(
							'type' => array( 'integer', 'null' ),
						),
						'notes'       => array(
							'type' => 'string',
						),
					),
				),
				'guardian_id'     => array(
					'type'    => array( 'integer', 'null' ),
					'context' => array( 'view', 'edit' ),
				),
				'guardian'        => array(
					'type'       => 'object',
					'context'    => array( 'edit' ),
					'properties' => array(
						'first_name' => array(
							'type' => 'string',
						),
						'last_name'  => array(
							'type' => 'string',
						),
						'phone'      => array(
							'type' => 'string',
						),
						'email'      => array(
							'type' => 'string',
						),
					),
				),
				'view_id'         => array(
					'type'    => array( 'integer', 'null' ),
					'context' => array( 'view', 'edit' ),
				),
				'current_stage'   => array(
					'type'      => 'string',
					'context'   => array( 'view', 'edit' ),
					'minLength' => 1,
				),
				'status'          => array(
					'type'    => 'string',
					'context' => array( 'view', 'edit' ),
				),
				'check_in_at'     => array(
					'type'    => array( 'string', 'null' ),
					'format'  => 'date-time',
					'context' => array( 'view', 'edit' ),
				),
				'check_out_at'    => array(
					'type'    => array( 'string', 'null' ),
					'format'  => 'date-time',
					'context' => array( 'view', 'edit' ),
				),
				'assigned_staff'  => array(
					'type'    => array( 'integer', 'null' ),
					'context' => array( 'view', 'edit' ),
				),
				'instructions'    => array(
					'type'    => 'string',
					'context' => array( 'view', 'edit' ),
				),
				'private_notes'   => array(
					'type'    => 'string',
					'context' => array( 'view', 'edit' ),
				),
				'public_notes'    => array(
					'type'    => 'string',
					'context' => array( 'view', 'edit' ),
				),
				'services'        => array(
					'type'    => 'array',
					'context' => array( 'view', 'edit' ),
					'items'   => array(
						'type' => 'integer',
					),
				),
				'flags'           => array(
					'type'    => 'array',
					'context' => array( 'view', 'edit' ),
					'items'   => array(
						'type' => 'integer',
					),
				),
				'photos'          => array(
					'type'     => 'array',
					'context'  => array( 'view', 'edit' ),
					'readonly' => true,
					'items'    => array(
						'type'       => 'object',
						'properties' => array(
							'id'                  => array(
								'type' => 'integer',
							),
							'url'                 => array(
								'type'   => 'string',
								'format' => 'uri',
							),
							'mime_type'           => array(
								'type' => 'string',
							),
							'thumbnail'           => array(
								'type'       => array( 'object', 'null' ),
								'properties' => array(
									'url'    => array(
										'type'   => 'string',
										'format' => 'uri',
									),
									'width'  => array(
										'type' => 'integer',
									),
									'height' => array(
										'type' => 'integer',
									),
								),
							),
							'alt'                 => array(
								'type' => 'string',
							),
							'sizes'               => array(
								'type' => 'object',
							),
							'visible_to_guardian' => array(
								'type' => 'boolean',
							),
						),
					),
				),
				'previous_visits' => array(
					'type'     => 'array',
					'context'  => array( 'view' ),
					'readonly' => true,
					'items'    => array(
						'type'       => 'object',
						'properties' => array(
							'id'            => array( 'type' => 'integer' ),
							'stage'         => array( 'type' => 'string' ),
							'status'        => array( 'type' => 'string' ),
							'check_in_at'   => array(
								'type'   => array( 'string', 'null' ),
								'format' => 'date-time',
							),
							'check_out_at'  => array(
								'type'   => array( 'string', 'null' ),
								'format' => 'date-time',
							),
							'instructions'  => array( 'type' => 'string' ),
							'public_notes'  => array( 'type' => 'string' ),
							'private_notes' => array( 'type' => 'string' ),
						),
					),
				),
				'history'         => array(
					'type'     => 'array',
					'context'  => array( 'view' ),
					'readonly' => true,
					'items'    => array(
						'type'       => 'object',
						'properties' => array(
							'id'              => array(
								'type' => 'integer',
							),
							'from_stage'      => array(
								'type'       => 'object',
								'properties' => array(
									'key'   => array(
										'type' => 'string',
									),
									'label' => array(
										'type' => 'string',
									),
								),
							),
							'to_stage'        => array(
								'type'       => 'object',
								'properties' => array(
									'key'   => array(
										'type' => 'string',
									),
									'label' => array(
										'type' => 'string',
									),
								),
							),
							'comment'         => array(
								'type' => 'string',
							),
							'changed_by'      => array(
								'type' => array( 'integer', 'null' ),
							),
							'changed_by_name' => array(
								'type' => 'string',
							),
							'changed_at'      => array(
								'type'   => array( 'string', 'null' ),
								'format' => 'date-time',
							),
							'elapsed_seconds' => array(
								'type' => 'integer',
							),
						),
					),
				),
				'created_at'      => array(
					'type'     => array( 'string', 'null' ),
					'format'   => 'date-time',
					'context'  => array( 'view' ),
					'readonly' => true,
				),
				'updated_at'      => array(
					'type'     => array( 'string', 'null' ),
					'format'   => 'date-time',
					'context'  => array( 'view' ),
					'readonly' => true,
				),
			),
			'required'   => array( 'current_stage' ),
		);

		return $this->schema;
	}

	/**
	 * Validate visit payload for create/update.
	 *
	 * @param WP_REST_Request $request Request instance.
	 * @param array|null      $existing Existing visit row.
	 * @return array<string,mixed>|WP_Error
	 */
	private function validate_visit_payload( WP_REST_Request $request, ?array $existing = null ) {
		$is_update = is_array( $existing );
		$visit     = array();
		$tables    = $this->plugin->get_table_names();

		$guardian_payload = $request->get_param( 'guardian' );
		$guardian_id      = $request->get_param( 'guardian_id' );

		if ( is_array( $guardian_payload ) ) {
			if ( null !== $guardian_id ) {
				$guardian_payload['id'] = (int) $guardian_id;
			}

			$guardian_result = $this->visit_service->create_or_update_guardian( $guardian_payload );
			if ( is_wp_error( $guardian_result ) ) {
				return $guardian_result;
			}

			$guardian_id = $guardian_result;
		}

		$client_id = $request->get_param( 'client_id' );
		if ( null === $client_id ) {
			$client_payload = $request->get_param( 'client' );
			if ( is_array( $client_payload ) ) {
				if ( null === $guardian_id && isset( $client_payload['guardian_id'] ) ) {
					$guardian_id = $client_payload['guardian_id'];
				}

				if ( isset( $guardian_id ) && ! isset( $client_payload['guardian_id'] ) ) {
					$client_payload['guardian_id'] = $guardian_id;
				}

				$client_result = $this->visit_service->create_or_update_client( $client_payload );
				if ( is_wp_error( $client_result ) ) {
					return $client_result;
				}
				$client_id = $client_result['client_id'];
				if ( isset( $client_result['guardian_id'] ) ) {
					$visit['guardian_id'] = $client_result['guardian_id'];
				}
			}
		} elseif ( '' === $client_id ) {
			$client_id = null;
		}

		if ( null === $client_id && $is_update && isset( $existing['client_id'] ) ) {
			$client_id = (int) $existing['client_id'];
		}

		$client_id = (int) $client_id;
		if ( $client_id <= 0 ) {
			return new WP_Error(
				'bbgf_visit_missing_client',
				__( 'Client ID is required.', 'bb-groomflow' ),
				array( 'status' => 400 )
			);
		}

		if ( ! $this->visit_service->record_exists( $tables['clients'], $client_id ) ) {
			return new WP_Error(
				'bbgf_visit_invalid_client',
				__( 'Client does not exist.', 'bb-groomflow' ),
				array( 'status' => 400 )
			);
		}

		$visit['client_id'] = $client_id;

		if ( null !== $guardian_id ) {
			$guardian_id = (int) $guardian_id;
			if ( $guardian_id > 0 ) {
				if ( ! $this->visit_service->record_exists( $tables['guardians'], $guardian_id ) ) {
					return new WP_Error(
						'bbgf_visit_invalid_guardian',
						__( 'Guardian does not exist.', 'bb-groomflow' ),
						array( 'status' => 400 )
					);
				}
				$visit['guardian_id'] = $guardian_id;
			} else {
				$visit['guardian_id'] = null;
			}
		} elseif ( $is_update && isset( $existing['guardian_id'] ) ) {
			$existing_guardian_id = (int) $existing['guardian_id'];
			$visit['guardian_id'] = $existing_guardian_id > 0 ? $existing_guardian_id : null;
		} else {
			$visit['guardian_id'] = null;
		}

		$view_id = $request->get_param( 'view_id' );
		if ( null !== $view_id ) {
			$view_id = (int) $view_id;
			if ( $view_id > 0 ) {
				if ( ! $this->visit_service->record_exists( $tables['views'], $view_id ) ) {
					return new WP_Error(
						'bbgf_visit_invalid_view',
						__( 'View does not exist.', 'bb-groomflow' ),
						array( 'status' => 400 )
					);
				}
				$visit['view_id'] = $view_id;
			} else {
				$visit['view_id'] = null;
			}
		} elseif ( $is_update && isset( $existing['view_id'] ) ) {
			$existing_view_id = (int) $existing['view_id'];
			$visit['view_id'] = $existing_view_id > 0 ? $existing_view_id : null;
		} else {
			$visit['view_id'] = null;
		}

		$stage_key = $request->get_param( 'current_stage' );
		if ( null === $stage_key && $is_update ) {
			$stage_key = (string) ( $existing['current_stage'] ?? '' );
		}

		$stage_key = sanitize_key( (string) $stage_key );
		if ( '' === $stage_key ) {
			return new WP_Error(
				'bbgf_visit_missing_stage',
				__( 'Stage is required.', 'bb-groomflow' ),
				array( 'status' => 400 )
			);
		}

		if ( null === $this->visit_service->get_stage_definition( $stage_key ) ) {
			return new WP_Error(
				'bbgf_visit_invalid_stage',
				__( 'Stage does not exist.', 'bb-groomflow' ),
				array( 'status' => 400 )
			);
		}

		$visit['current_stage'] = $stage_key;

		$status = $request->get_param( 'status' );
		if ( null === $status && $is_update ) {
			$status = $existing['status'] ?? 'in_progress';
		}

		$status_value    = '' !== (string) $status ? (string) $status : 'in_progress';
		$visit['status'] = sanitize_key( $status_value );

		$visit['check_in_at']    = $this->sanitize_datetime( $request->get_param( 'check_in_at' ), $existing['check_in_at'] ?? null );
		$visit['check_out_at']   = $this->sanitize_datetime( $request->get_param( 'check_out_at' ), $existing['check_out_at'] ?? null );
		$visit['assigned_staff'] = $this->sanitize_int_or_null( $request->get_param( 'assigned_staff' ), $existing['assigned_staff'] ?? null );
		$visit['instructions']   = $this->sanitize_text( $request->get_param( 'instructions' ), $existing['instructions'] ?? '' );
		$visit['private_notes']  = $this->sanitize_text( $request->get_param( 'private_notes' ), $existing['private_notes'] ?? '' );
		$visit['public_notes']   = $this->sanitize_text( $request->get_param( 'public_notes' ), $existing['public_notes'] ?? '' );

		if ( ! $is_update ) {
			$visit['timer_elapsed_seconds'] = 0;
		} else {
			$visit['timer_elapsed_seconds'] = (int) ( $existing['timer_elapsed_seconds'] ?? 0 );
		}

		$services = $is_update ? null : array();
		$flags    = $is_update ? null : array();

		if ( $request->has_param( 'services' ) ) {
			$services_input = $request->get_param( 'services' );
			if ( ! is_array( $services_input ) ) {
				return new WP_Error(
					'bbgf_visit_invalid_services',
					__( 'Services must be an array of IDs.', 'bb-groomflow' ),
					array( 'status' => 400 )
				);
			}

			foreach ( $services_input as $service_id ) {
				$service_id = (int) $service_id;
				if ( $service_id > 0 ) {
					$services[] = $service_id;
				}
			}

			if ( ! empty( $services ) ) {
				$services = array_values( array_unique( $services ) );
				$result   = $this->visit_service->assert_ids_exist( $tables['services'], $services, 'bbgf_visit_invalid_service' );
				if ( is_wp_error( $result ) ) {
					return $result;
				}
			}
		}

		if ( $request->has_param( 'flags' ) ) {
			$flags_input = $request->get_param( 'flags' );
			if ( ! is_array( $flags_input ) ) {
				return new WP_Error(
					'bbgf_visit_invalid_flags',
					__( 'Flags must be an array of IDs.', 'bb-groomflow' ),
					array( 'status' => 400 )
				);
			}

			foreach ( $flags_input as $flag_id ) {
				$flag_id = (int) $flag_id;
				if ( $flag_id > 0 ) {
					$flags[] = $flag_id;
				}
			}

			if ( ! empty( $flags ) ) {
				$flags  = array_values( array_unique( $flags ) );
				$result = $this->visit_service->assert_ids_exist( $tables['flags'], $flags, 'bbgf_visit_invalid_flag' );
				if ( is_wp_error( $result ) ) {
					return $result;
				}
			}
		}

		return array(
			'visit'    => $visit,
			'services' => $services,
			'flags'    => $flags,
		);
	}

	/**
	 * Sanitize text value with fallback.
	 *
	 * @param mixed  $value    Value.
	 * @param string $fallback Fallback.
	 * @return string
	 */
	private function sanitize_text( $value, string $fallback ): string {
		if ( null === $value ) {
			return $fallback;
		}

		return sanitize_textarea_field( (string) $value );
	}

	/**
	 * Sanitise datetime string.
	 *
	 * @param mixed       $value    Value.
	 * @param string|null $fallback Fallback.
	 * @return string|null
	 */
	private function sanitize_datetime( $value, ?string $fallback ): ?string {
		if ( null === $value ) {
			return $fallback;
		}

		$value = trim( (string) $value );
		if ( '' === $value ) {
			return null;
		}

		$timestamp = strtotime( $value );
		if ( false === $timestamp ) {
			return $fallback;
		}

		return gmdate( 'Y-m-d H:i:s', $timestamp );
	}

	/**
	 * Sanitize integer or null.
	 *
	 * @param mixed    $value    Value.
	 * @param int|null $fallback Fallback.
	 * @return int|null
	 */
	private function sanitize_int_or_null( $value, ?int $fallback ): ?int {
		if ( null === $value ) {
			return $fallback;
		}

		$value = (int) $value;

		return $value > 0 ? $value : null;
	}

	/**
	 * Determine if the request uses a public token.
	 *
	 * @param WP_REST_Request $request Request instance.
	 * @return bool
	 */
	private function is_public_token_request( WP_REST_Request $request ): bool {
		$public_token = $request->get_param( 'public_token' );

		return is_string( $public_token ) && '' !== $public_token;
	}
}
