<?php
/**
 * Plugin Name:       Bubbles & Bows GroomFlow
 * Plugin URI:        https://github.com/ocdataUS/GroomFlow
 * Description:       Native WordPress Kanban workflow for animal grooming salons.
 * Version:           0.1.0-dev
 * Requires at least: 6.8
 * Tested up to:      6.9
 * Requires PHP:      8.2
 * Author:            OC Data
 * Text Domain:       bb-groomflow
 * Domain Path:       /languages
 *
 * @package BB_GroomFlow
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'BBGF_VERSION', '0.1.0-dev' );
define( 'BBGF_DB_VERSION', '1.5.0' );
define( 'BBGF_PLUGIN_FILE', __FILE__ );
define( 'BBGF_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'BBGF_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once BBGF_PLUGIN_DIR . 'includes/class-plugin.php';

register_activation_hook(
	__FILE__,
	static function (): void {
		BBGF\Plugin::activate();
	}
);

register_deactivation_hook(
	__FILE__,
	static function (): void {
		BBGF\Plugin::deactivate();
	}
);

/**
 * Returns the singleton plugin instance.
 *
 * @return \BBGF\Plugin
 */
function bbgf(): BBGF\Plugin {
	return BBGF\Plugin::instance();
}

bbgf();
