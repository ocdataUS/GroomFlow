<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * @package BB_GroomFlow
 */

use BBGF\Database\Schema;

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

if ( defined( 'BBGF_PRESERVE_DATA_ON_UNINSTALL' ) && (bool) BBGF_PRESERVE_DATA_ON_UNINSTALL ) {
	return;
}

if ( function_exists( 'current_user_can' ) && ! current_user_can( 'delete_plugins' ) && ( ! defined( 'WP_CLI' ) || ! WP_CLI ) ) {
	return;
}

if ( false === apply_filters( 'bbgf_allow_uninstall_cleanup', true ) ) {
	return;
}

require_once __DIR__ . '/includes/database/class-schema.php';

$site_ids = bbgf_get_site_ids();

foreach ( $site_ids as $site_id ) {
	if ( is_multisite() ) {
		switch_to_blog( $site_id );
	}

	bbgf_cleanup_site_data();

	if ( is_multisite() ) {
		restore_current_blog();
	}
}

bbgf_delete_network_options();

/**
 * Get the site IDs that should be cleaned up.
 *
 * @return int[]
 */
function bbgf_get_site_ids(): array {
	if ( ! is_multisite() ) {
		return array( (int) get_current_blog_id() );
	}

	$ids = get_sites(
		array(
			'fields' => 'ids',
		)
	);

	if ( empty( $ids ) ) {
		return array();
	}

	return array_map( 'absint', $ids );
}

/**
 * Drop plugin tables and options for a given site.
 */
function bbgf_cleanup_site_data(): void {
	bbgf_drop_plugin_tables();
	bbgf_delete_prefixed_options();
}

/**
 * Drop every plugin-managed table for the current site.
 */
function bbgf_drop_plugin_tables(): void {
	global $wpdb;

	$tables = Schema::get_table_names( $wpdb );

	foreach ( $tables as $table_name ) {
		if ( '' === $table_name ) {
			continue;
		}

		$wpdb->query( "DROP TABLE IF EXISTS {$table_name}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared
	}
}

/**
 * Delete all plugin options for the current site.
 */
function bbgf_delete_prefixed_options(): void {
	global $wpdb;

	$like = $wpdb->esc_like( 'bbgf_' ) . '%';

		$option_names = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
				$like
			)
		);

	if ( empty( $option_names ) ) {
		return;
	}

	foreach ( $option_names as $option_name ) {
		delete_option( (string) $option_name );
	}
}

/**
 * Delete network-level options that match the plugin prefix.
 */
function bbgf_delete_network_options(): void {
	if ( ! is_multisite() ) {
		return;
	}

	global $wpdb;

	$like = $wpdb->esc_like( 'bbgf_' ) . '%';

		$meta_keys = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				"SELECT meta_key FROM {$wpdb->sitemeta} WHERE meta_key LIKE %s",
				$like
			)
		);

	if ( empty( $meta_keys ) ) {
		return;
	}

	foreach ( $meta_keys as $meta_key ) {
		delete_site_option( (string) $meta_key );
	}
}
