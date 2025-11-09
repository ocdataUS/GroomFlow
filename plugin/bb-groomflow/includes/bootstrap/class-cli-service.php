<?php
/**
 * WP-CLI bootstrapper.
 *
 * @package BB_GroomFlow
 */

namespace BBGF\Bootstrap;

use BBGF\CLI\Sync_Command;
use BBGF\CLI\Visits_Command;
use BBGF\Plugin;

/**
 * Registers the plugin WP-CLI commands when available.
 */
class Cli_Service {
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
	 * Wire command registrations.
	 */
	public function register(): void {
		require_once BBGF_PLUGIN_DIR . 'includes/cli/class-base-command.php';
		require_once BBGF_PLUGIN_DIR . 'includes/cli/class-visits-command.php';
		require_once BBGF_PLUGIN_DIR . 'includes/cli/class-sync-command.php';

		Visits_Command::register( $this->plugin );
		Sync_Command::register( $this->plugin );
	}
}
