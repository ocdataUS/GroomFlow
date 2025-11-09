<?php
/**
 * Synchronisation helpers for GroomFlow.
 *
 * @package BB_GroomFlow
 */

namespace BBGF\CLI;

use BBGF\Plugin;
use WP_CLI;
use function WP_CLI\Utils\get_flag_value;

/**
 * Provides guidance and scaffolding around content synchronisation.
 */
class Sync_Command extends Base_Command {
	/**
	 * Register the command with WP-CLI.
	 *
	 * @param Plugin $plugin Plugin instance.
	 * @return void
	 */
	public static function register( Plugin $plugin ): void {
		WP_CLI::add_command(
			'bbgf sync',
			new self( $plugin )
		);
	}

	/**
	 * Display the production snapshot preparation checklist.
	 *
	 * ## OPTIONS
	 *
	 * [--dry-run]
	 * : Output the actions without executing any filesystem changes.
	 *
	 * ## EXAMPLES
	 *
	 *     wp bbgf sync prepare
	 *     wp bbgf sync prepare --dry-run
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative options.
	 * @return void
	 */
	public function prepare( array $args, array $assoc_args ): void {
		unset( $args ); // Unused.

		$dry_run = get_flag_value( $assoc_args, 'dry-run', false );

		$steps = array(
			__( 'Ensure docker/prod-sync contains the latest production snapshot.', 'bb-groomflow' ),
			__( 'Run scripts/load_prod_snapshot.sh to hydrate the Docker environment.', 'bb-groomflow' ),
			__( 'Rebuild the GroomFlow ZIP (bash scripts/build_plugin_zip.sh) and install it via WP-CLI.', 'bb-groomflow' ),
			__( 'Log QA findings in docs/breadcrumbs and archive any artifacts from /opt/qa/artifacts.', 'bb-groomflow' ),
		);

		if ( $dry_run ) {
			$this->info( __( 'Dry-run: the following steps would be executed:', 'bb-groomflow' ) );
		} else {
			$this->info( __( 'Follow the snapshot preparation checklist:', 'bb-groomflow' ) );
		}

		foreach ( $steps as $index => $step ) {
			WP_CLI::log( sprintf( '%d. %s', $index + 1, $step ) );
		}

		if ( ! $dry_run ) {
			WP_CLI::success( __( 'Snapshot checklist reviewed. Execute each step manually as documented.', 'bb-groomflow' ) );
		}
	}
}
