<?php
/**
 * Database schema definitions.
 *
 * @package BB_GroomFlow
 */

namespace BBGF\Database;

use wpdb;

/**
 * Provides table definitions and helpers for installing/upgrading the schema.
 */
class Schema {
	/**
	 * Maps logical identifiers to database table suffixes.
	 *
	 * @var array<string,string>
	 */
	public const TABLE_MAP = array(
		'clients'               => 'bb_clients',
		'guardians'             => 'bb_guardians',
		'services'              => 'bb_services',
		'service_packages'      => 'bb_service_packages',
		'service_package_items' => 'bb_service_package_items',
		'stages'                => 'bb_stages',
		'flags'                 => 'bb_flags',
		'views'                 => 'bb_views',
		'view_stages'           => 'bb_view_stages',
		'visits'                => 'bb_visits',
		'visit_services'        => 'bb_visit_services',
		'visit_flags'           => 'bb_visit_flags',
		'stage_history'         => 'bb_stage_history',
		'notifications'         => 'bb_notifications',
		'notification_triggers' => 'bb_notification_triggers',
		'notification_logs'     => 'bb_notification_logs',
	);

	/**
	 * Returns fully-qualified table names keyed by logical identifier.
	 *
	 * @param wpdb $wpdb Global database object.
	 * @return array<string,string>
	 */
	public static function get_table_names( wpdb $wpdb ): array {
		$tables = array();

		foreach ( self::TABLE_MAP as $key => $suffix ) {
			$tables[ $key ] = $wpdb->prefix . $suffix;
		}

		return $tables;
	}

	/**
	 * Returns the CREATE TABLE statements for all plugin tables.
	 *
	 * @param wpdb $wpdb Global database object.
	 * @return array<int,string>
	 */
	public static function get_table_sql( wpdb $wpdb ): array {
		$charset_collate = $wpdb->get_charset_collate();
		$tables          = self::get_table_names( $wpdb );

		$sql = array();

		$sql[] = "CREATE TABLE {$tables['clients']} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			name VARCHAR(191) NOT NULL,
			slug VARCHAR(191) NOT NULL,
			guardian_id BIGINT UNSIGNED DEFAULT NULL,
			breed VARCHAR(191) DEFAULT '' NOT NULL,
			weight DECIMAL(6,2) DEFAULT NULL,
			sex VARCHAR(20) DEFAULT '' NOT NULL,
			dob DATE DEFAULT NULL,
			temperament VARCHAR(191) DEFAULT '' NOT NULL,
			preferred_groomer VARCHAR(191) DEFAULT '' NOT NULL,
			notes LONGTEXT,
			meta LONGTEXT,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY slug (slug),
			KEY guardian_id (guardian_id)
		) {$charset_collate};";

		$sql[] = "CREATE TABLE {$tables['guardians']} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			first_name VARCHAR(191) NOT NULL,
			last_name VARCHAR(191) NOT NULL,
			email VARCHAR(191) DEFAULT '' NOT NULL,
			phone_mobile VARCHAR(50) DEFAULT '' NOT NULL,
			phone_alt VARCHAR(50) DEFAULT '' NOT NULL,
			preferred_contact VARCHAR(32) DEFAULT '' NOT NULL,
			address TEXT,
			notes LONGTEXT,
			meta LONGTEXT,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY email (email)
		) {$charset_collate};";

		$sql[] = "CREATE TABLE {$tables['services']} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			name VARCHAR(191) NOT NULL,
			slug VARCHAR(191) NOT NULL,
			icon VARCHAR(64) DEFAULT '' NOT NULL,
			color VARCHAR(32) DEFAULT '' NOT NULL,
			duration_minutes SMALLINT UNSIGNED DEFAULT 0 NOT NULL,
			price DECIMAL(10,2) DEFAULT NULL,
			description TEXT,
			flags LONGTEXT,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY slug (slug)
		) {$charset_collate};";

		$sql[] = "CREATE TABLE {$tables['service_packages']} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			name VARCHAR(191) NOT NULL,
			slug VARCHAR(191) NOT NULL,
			icon VARCHAR(64) DEFAULT '' NOT NULL,
			color VARCHAR(32) DEFAULT '' NOT NULL,
			price DECIMAL(10,2) DEFAULT NULL,
			description TEXT,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY slug (slug)
		) {$charset_collate};";

		$sql[] = "CREATE TABLE {$tables['service_package_items']} (
			package_id BIGINT UNSIGNED NOT NULL,
			service_id BIGINT UNSIGNED NOT NULL,
			sort_order SMALLINT UNSIGNED DEFAULT 0 NOT NULL,
			PRIMARY KEY  (package_id, service_id),
			KEY service_id (service_id)
		) {$charset_collate};";

		$sql[] = "CREATE TABLE {$tables['stages']} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			stage_key VARCHAR(64) NOT NULL,
			label VARCHAR(191) NOT NULL,
			description TEXT,
			capacity_soft_limit SMALLINT UNSIGNED DEFAULT 0 NOT NULL,
			capacity_hard_limit SMALLINT UNSIGNED DEFAULT 0 NOT NULL,
			timer_threshold_green SMALLINT UNSIGNED DEFAULT 0 NOT NULL,
			timer_threshold_yellow SMALLINT UNSIGNED DEFAULT 0 NOT NULL,
			timer_threshold_red SMALLINT UNSIGNED DEFAULT 0 NOT NULL,
			sort_order SMALLINT UNSIGNED DEFAULT 0 NOT NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY stage_key (stage_key)
		) {$charset_collate};";

		$sql[] = "CREATE TABLE {$tables['flags']} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			name VARCHAR(191) NOT NULL,
			slug VARCHAR(191) NOT NULL,
			emoji VARCHAR(16) DEFAULT '' NOT NULL,
			color VARCHAR(32) DEFAULT '' NOT NULL,
			severity VARCHAR(32) DEFAULT '' NOT NULL,
			description TEXT,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY slug (slug)
		) {$charset_collate};";

		$sql[] = "CREATE TABLE {$tables['views']} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			name VARCHAR(191) NOT NULL,
			slug VARCHAR(191) NOT NULL,
			type VARCHAR(32) DEFAULT 'internal' NOT NULL,
			allow_switcher TINYINT(1) DEFAULT 1 NOT NULL,
			refresh_interval SMALLINT UNSIGNED DEFAULT 60 NOT NULL,
			show_guardian TINYINT(1) DEFAULT 1 NOT NULL,
			public_token_hash VARCHAR(255) DEFAULT '' NOT NULL,
			settings LONGTEXT,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY slug (slug),
			KEY public_token_hash (public_token_hash)
		) {$charset_collate};";

		$sql[] = "CREATE TABLE {$tables['view_stages']} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			view_id BIGINT UNSIGNED NOT NULL,
			stage_key VARCHAR(64) NOT NULL,
			label VARCHAR(191) NOT NULL,
			sort_order SMALLINT UNSIGNED DEFAULT 0 NOT NULL,
			capacity_soft_limit SMALLINT UNSIGNED DEFAULT 0 NOT NULL,
			capacity_hard_limit SMALLINT UNSIGNED DEFAULT 0 NOT NULL,
			timer_threshold_green SMALLINT UNSIGNED DEFAULT 0 NOT NULL,
			timer_threshold_yellow SMALLINT UNSIGNED DEFAULT 0 NOT NULL,
			timer_threshold_red SMALLINT UNSIGNED DEFAULT 0 NOT NULL,
			PRIMARY KEY  (id),
			KEY view_id (view_id),
			KEY stage_key (stage_key)
		) {$charset_collate};";

		$sql[] = "CREATE TABLE {$tables['visits']} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			client_id BIGINT UNSIGNED NOT NULL,
			guardian_id BIGINT UNSIGNED DEFAULT NULL,
			view_id BIGINT UNSIGNED DEFAULT NULL,
			current_stage VARCHAR(64) DEFAULT '' NOT NULL,
			status VARCHAR(32) DEFAULT '' NOT NULL,
			check_in_at DATETIME DEFAULT NULL,
			check_out_at DATETIME DEFAULT NULL,
			assigned_staff BIGINT UNSIGNED DEFAULT NULL,
			instructions LONGTEXT,
			private_notes LONGTEXT,
			public_notes LONGTEXT,
			timer_started_at DATETIME DEFAULT NULL,
			timer_elapsed_seconds INT UNSIGNED DEFAULT 0 NOT NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY client_id (client_id),
			KEY guardian_id (guardian_id),
			KEY view_id (view_id),
			KEY current_stage (current_stage)
		) {$charset_collate};";

		$sql[] = "CREATE TABLE {$tables['visit_services']} (
			visit_id BIGINT UNSIGNED NOT NULL,
			service_id BIGINT UNSIGNED NOT NULL,
			package_id BIGINT UNSIGNED DEFAULT NULL,
			added_by BIGINT UNSIGNED DEFAULT NULL,
			added_at DATETIME NOT NULL,
			PRIMARY KEY  (visit_id, service_id),
			KEY package_id (package_id)
		) {$charset_collate};";

		$sql[] = "CREATE TABLE {$tables['visit_flags']} (
			visit_id BIGINT UNSIGNED NOT NULL,
			flag_id BIGINT UNSIGNED NOT NULL,
			notes TEXT,
			added_by BIGINT UNSIGNED DEFAULT NULL,
			added_at DATETIME NOT NULL,
			PRIMARY KEY  (visit_id, flag_id)
		) {$charset_collate};";

		$sql[] = "CREATE TABLE {$tables['stage_history']} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			visit_id BIGINT UNSIGNED NOT NULL,
			from_stage VARCHAR(64) DEFAULT '' NOT NULL,
			to_stage VARCHAR(64) DEFAULT '' NOT NULL,
			comment TEXT,
			changed_by BIGINT UNSIGNED DEFAULT NULL,
			changed_at DATETIME NOT NULL,
			elapsed_seconds INT UNSIGNED DEFAULT 0 NOT NULL,
			PRIMARY KEY  (id),
			KEY visit_id (visit_id),
			KEY to_stage (to_stage)
		) {$charset_collate};";

		$sql[] = "CREATE TABLE {$tables['notifications']} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			name VARCHAR(191) NOT NULL,
			channel VARCHAR(32) DEFAULT 'email' NOT NULL,
			subject VARCHAR(191) DEFAULT '' NOT NULL,
			body_html LONGTEXT,
			body_text LONGTEXT,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY channel (channel)
		) {$charset_collate};";

		$sql[] = "CREATE TABLE {$tables['notification_triggers']} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			trigger_stage VARCHAR(64) NOT NULL,
			notification_id BIGINT UNSIGNED NOT NULL,
			enabled TINYINT(1) DEFAULT 1 NOT NULL,
			recipient_type VARCHAR(32) DEFAULT 'guardian_primary' NOT NULL,
			recipient_email VARCHAR(191) DEFAULT '' NOT NULL,
			conditions LONGTEXT,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY trigger_stage (trigger_stage),
			KEY notification_id (notification_id)
		) {$charset_collate};";

		$sql[] = "CREATE TABLE {$tables['notification_logs']} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			notification_id BIGINT UNSIGNED DEFAULT NULL,
			trigger_id BIGINT UNSIGNED DEFAULT NULL,
			visit_id BIGINT UNSIGNED DEFAULT NULL,
			stage VARCHAR(64) DEFAULT '' NOT NULL,
			channel VARCHAR(32) DEFAULT 'email' NOT NULL,
			recipients TEXT,
			subject VARCHAR(191) DEFAULT '' NOT NULL,
			status VARCHAR(32) DEFAULT 'sent' NOT NULL,
			error_message TEXT,
			sent_at DATETIME NOT NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY notification_id (notification_id),
			KEY trigger_id (trigger_id),
			KEY visit_id (visit_id),
			KEY stage (stage)
		) {$charset_collate};";

		return $sql;
	}
}
