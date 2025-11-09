<?php
/**
 * Health check endpoint for GroomFlow REST API.
 *
 * @package BB_GroomFlow
 */

namespace BBGF\API;

use WP_REST_Request;

/**
 * Provides a simple health/status endpoint for Sprint 0.
 */
class Health_Controller extends REST_Controller {
	/**
	 * REST route base.
	 *
	 * @var string
	 */
	protected $rest_base = 'health';

	/**
	 * Register routes with the WordPress REST API.
	 */
	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'check_health' ),
					'permission_callback' => array( $this, 'permissions_check' ),
				),
				'schema' => array( $this, 'get_item_schema' ),
			)
		);
	}

	/**
	 * Capability check for the health endpoint.
	 *
	 * @return true|\WP_Error
	 */
	public function permissions_check() {
		return $this->check_capability( 'bbgf_view_board' );
	}

	/**
	 * Return a basic health payload.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function check_health( WP_REST_Request $request ) {
		return rest_ensure_response(
			array(
				'status'      => 'ok',
				'version'     => BBGF_VERSION,
				'timestamp'   => current_time( 'mysql', true ),
				'description' => __( 'GroomFlow REST namespace is online.', 'bb-groomflow' ),
			)
		);
	}

	/**
	 * JSON schema for the health payload.
	 *
	 * @return array
	 */
	public function get_item_schema(): array {
		return array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'bbgf_health',
			'type'       => 'object',
			'properties' => array(
				'status'      => array(
					'type' => 'string',
				),
				'version'     => array(
					'type' => 'string',
				),
				'timestamp'   => array(
					'type'   => 'string',
					'format' => 'date-time',
				),
				'description' => array(
					'type' => 'string',
				),
			),
		);
	}
}
