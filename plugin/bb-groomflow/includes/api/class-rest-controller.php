<?php
/**
 * Base REST controller shared across GroomFlow endpoints.
 *
 * @package BB_GroomFlow
 */

namespace BBGF\API;

use WP_Error;
use WP_REST_Controller;

/**
 * Abstract base controller that handles shared configuration.
 */
abstract class REST_Controller extends WP_REST_Controller {
	/**
	 * Constructor sets namespace for all GroomFlow endpoints.
	 */
	public function __construct() {
		$this->namespace = 'bb-groomflow/v1';
	}

	/**
	 * Verify the current user can perform an action.
	 *
	 * @param string $capability Capability to validate.
	 * @return true|WP_Error
	 */
	protected function check_capability( string $capability ) {
		if ( current_user_can( $capability ) ) {
			return true;
		}

		return new WP_Error(
			'bbgf_rest_forbidden',
			__( 'You are not allowed to access this resource.', 'bb-groomflow' ),
			array(
				'status' => rest_authorization_required_code(),
			)
		);
	}
}
