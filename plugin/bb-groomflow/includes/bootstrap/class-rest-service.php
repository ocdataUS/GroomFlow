<?php
/**
 * REST bootstrap wiring.
 *
 * @package BB_GroomFlow
 */

namespace BBGF\Bootstrap;

use BBGF\API\Clients_Controller;
use BBGF\API\Flags_Controller;
use BBGF\API\Guardians_Controller;
use BBGF\API\Health_Controller;
use BBGF\API\Packages_Controller;
use BBGF\API\Services_Controller;
use BBGF\API\Stats_Controller;
use BBGF\API\Views_Controller;
use BBGF\API\Visits_Controller;
use BBGF\Plugin;

/**
 * Registers the plugin REST controllers.
 */
class Rest_Service {
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
	 * Hook REST registrations.
	 */
	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
	}

	/**
	 * Register REST API routes.
	 */
	public function register_rest_routes(): void {
		foreach ( $this->get_rest_controllers() as $controller ) {
			$controller->register_routes();
		}
	}

	/**
	 * Instantiate REST controllers used by the plugin.
	 *
	 * @return array<int,\WP_REST_Controller>
	 */
	private function get_rest_controllers(): array {
		require_once BBGF_PLUGIN_DIR . 'includes/api/class-rest-controller.php';
		require_once BBGF_PLUGIN_DIR . 'includes/api/class-health-controller.php';
		require_once BBGF_PLUGIN_DIR . 'includes/api/class-clients-controller.php';
		require_once BBGF_PLUGIN_DIR . 'includes/api/class-guardians-controller.php';
		require_once BBGF_PLUGIN_DIR . 'includes/api/class-services-controller.php';
		require_once BBGF_PLUGIN_DIR . 'includes/api/class-packages-controller.php';
		require_once BBGF_PLUGIN_DIR . 'includes/api/class-flags-controller.php';
		require_once BBGF_PLUGIN_DIR . 'includes/api/class-views-controller.php';
		require_once BBGF_PLUGIN_DIR . 'includes/api/class-visits-controller.php';
		require_once BBGF_PLUGIN_DIR . 'includes/api/class-stats-controller.php';

		$plugin        = $this->plugin;
		$visit_service = $plugin->visit_service();

		return array(
			new Health_Controller(),
			new Clients_Controller( $plugin ),
			new Guardians_Controller( $plugin ),
			new Services_Controller( $plugin ),
			new Packages_Controller( $plugin ),
			new Flags_Controller( $plugin ),
			new Views_Controller( $plugin ),
			new Visits_Controller( $plugin, $visit_service ),
			new Stats_Controller( $plugin ),
		);
	}
}
