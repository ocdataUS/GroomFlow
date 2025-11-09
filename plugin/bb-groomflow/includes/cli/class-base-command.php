<?php
/**
 * Shared helpers for GroomFlow WP-CLI commands.
 *
 * @package BB_GroomFlow
 */

namespace BBGF\CLI;

use BBGF\Plugin;
use WP_CLI;
use WP_CLI_Command;
use wpdb;

/**
 * Base command providing access to the plugin and database instances.
 */
abstract class Base_Command extends WP_CLI_Command {
	/**
	 * Plugin instance.
	 *
	 * @var Plugin
	 */
	protected Plugin $plugin;

	/**
	 * Database handle.
	 *
	 * @var wpdb
	 */
	protected wpdb $wpdb;

	/**
	 * Constructor.
	 *
	 * @param Plugin $plugin Plugin instance.
	 */
	public function __construct( Plugin $plugin ) {
		$this->plugin = $plugin;
		$this->wpdb   = $plugin->get_wpdb();
	}

	/**
	 * Output a formatted notice.
	 *
	 * @param string $message Message to output.
	 */
	protected function info( string $message ): void {
		WP_CLI::log( $message );
	}
}
