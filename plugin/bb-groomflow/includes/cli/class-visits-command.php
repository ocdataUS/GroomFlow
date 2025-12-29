<?php
/**
 * Visits-related WP-CLI commands.
 *
 * @package BB_GroomFlow
 */

namespace BBGF\CLI;

use BBGF\Plugin;
use WP_CLI;
use function WP_CLI\Utils\format_items;
use function WP_CLI\Utils\get_flag_value;
use function sanitize_email;
use function sanitize_key;
use function sanitize_text_field;
use function sanitize_textarea_field;
use function sanitize_title;
use function wp_json_encode;
use function wp_rand;

/**
 * Provides data discovery helpers for the visits table.
 */
class Visits_Command extends Base_Command {
	/**
	 * Register the command with WP-CLI.
	 *
	 * @param Plugin $plugin Plugin instance.
	 * @return void
	 */
	public static function register( Plugin $plugin ): void {
		WP_CLI::add_command(
			'bbgf visits',
			new self( $plugin )
		);
	}

	/**
	 * List recent visits with optional stage/view filtering.
	 *
	 * ## OPTIONS
	 *
	 * [--stage=<stage_key>]
	 * : Limit results to a specific pipeline stage.
	 *
	 * [--view=<view_slug>]
	 * : Only include visits assigned to the given view slug.
	 *
	 * [--limit=<number>]
	 * : Maximum number of records to return (default 20, max 100).
	 *
	 * [--format=<format>]
	 * : Render output in a supported format (table, csv, json, yaml, ids).
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - csv
	 *   - json
	 *   - yaml
	 *   - ids
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp bbgf visits list --stage=grooming --limit=10
	 *     wp bbgf visits list --view=lobby-display --format=json
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 * @return void
	 */
	public function list( array $args, array $assoc_args ): void {
		unset( $args ); // Unused.

		$tables = $this->plugin->get_table_names();

		$stage  = sanitize_key( (string) get_flag_value( $assoc_args, 'stage', '' ) );
		$view   = sanitize_title( (string) get_flag_value( $assoc_args, 'view', '' ) );
		$limit  = (int) get_flag_value( $assoc_args, 'limit', 20 );
		$limit  = max( 1, min( 100, $limit ) );
		$format = get_flag_value( $assoc_args, 'format', 'table' );

		$conditions = array();
		$sql_args   = array();

		if ( '' !== $stage ) {
			$conditions[] = 'v.current_stage = %s';
			$sql_args[]   = $stage;
		}

		if ( '' !== $view ) {
			$conditions[] = 'vw.slug = %s';
			$sql_args[]   = $view;
		}

		$where = '';
		if ( ! empty( $conditions ) ) {
			$where = 'WHERE ' . implode( ' AND ', $conditions );
		}

		$sql_args[] = $limit;

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared -- Table names and WHERE clause built from schema map and sanitized inputs.
		$sql = $this->wpdb->prepare(
			"SELECT v.id, c.name AS client, v.current_stage AS stage, v.status, v.updated_at
			FROM {$tables['visits']} AS v
			LEFT JOIN {$tables['clients']} AS c ON c.id = v.client_id
			LEFT JOIN {$tables['views']} AS vw ON vw.id = v.view_id
			{$where}
			ORDER BY v.updated_at DESC
			LIMIT %d",
			...$sql_args
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared

		$rows = $this->wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		if ( empty( $rows ) ) {
			$this->info( __( 'No visits found matching the requested filters.', 'bb-groomflow' ) );
			return;
		}

		$data = array_map(
			static function ( array $row ): array {
				$row['updated_at'] = mysql2date( DATE_RFC3339, $row['updated_at'], true );
				return $row;
			},
			$rows
		);

		format_items(
			$format,
			$data,
			array( 'id', 'client', 'stage', 'status', 'updated_at' )
		);
	}

	/**
	 * Seed demo visits, clients, and guardians for local testing.
	 *
	 * ## OPTIONS
	 *
	 * @subcommand seed-demo
	 *
	 * [--count=<number>]
	 * : Number of visits to generate per view (default 6, max 12).
	 *
	 * [--force]
	 * : Remove existing visits before seeding demo data.
	 *
	 * ## EXAMPLES
	 *
	 *     wp bbgf visits seed-demo
	 *     wp bbgf visits seed-demo --count=8 --force
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 * @return void
	 */
	public function seed_demo( array $args, array $assoc_args ): void {
		unset( $args ); // Unused.

		$tables = $this->plugin->get_table_names();
		if ( empty( $tables['visits'] ) ) {
			WP_CLI::error( __( 'Visit table is not registered. Run database migrations first.', 'bb-groomflow' ) );
		}

		$force     = (bool) get_flag_value( $assoc_args, 'force', false );
		$count     = (int) get_flag_value( $assoc_args, 'count', 6 );
		$count     = max( 1, min( 12, $count ) );
		$refreshed = 0;

		if ( $force ) {
			$deleted_rows = $this->truncate_visit_tables( $tables );
			WP_CLI::log(
				sprintf(
					/* translators: %d: number of rows removed */
					__( 'Removed %d existing visit rows via --force.', 'bb-groomflow' ),
					$deleted_rows
				)
			);
		}

		$existing_visits_by_view = $force ? array() : $this->get_existing_visits_by_view( $tables );
		if ( ! $force && ! empty( $existing_visits_by_view ) ) {
			$existing_total = array_sum(
				array_map(
					static function ( $items ) {
						return count( $items );
					},
					$existing_visits_by_view
				)
			);

			if ( $existing_total > 0 ) {
				WP_CLI::log(
					sprintf(
						/* translators: %d: number of existing visits */
						__( 'Found %d existing visit(s); refreshing timers and topping up without duplicates.', 'bb-groomflow' ),
						$existing_total
					)
				);
			}
		}

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared -- Table name provided by schema map.
		$views_sql = $this->wpdb->prepare(
			"SELECT id, slug, type FROM {$tables['views']} WHERE 1 = %d ORDER BY id ASC",
			1
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared
		$views = $this->wpdb->get_results( $views_sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query prepared above.

		if ( empty( $views ) ) {
			WP_CLI::error( __( 'No board views found. Create at least one view before seeding demo data.', 'bb-groomflow' ) );
		}

		$service_map = $this->map_by_slug( $tables['services'], 'slug' );
		$flag_map    = $this->map_by_slug( $tables['flags'], 'slug' );

		if ( empty( $service_map ) ) {
			WP_CLI::warning( __( 'No services found. Demo visits will be created without service chips.', 'bb-groomflow' ) );
		}

		$profiles      = $this->get_demo_profiles();
		$visit_service = $this->plugin->visit_service();
		$now_mysql     = $this->plugin->now();
		$now_utc       = time();

		$created = 0;

		foreach ( $views as $view ) {
			$view_slug = sanitize_title( (string) ( $view['slug'] ?? '' ) );
			WP_CLI::log(
				sprintf(
					/* translators: 1: visit count 2: view slug */
					__( 'Creating %1$d demo visit(s) for view "%2$s".', 'bb-groomflow' ),
					$count,
					'' !== $view_slug ? $view_slug : (string) ( $view['id'] ?? '?' )
				)
			);
			$stage_keys = $this->get_stage_keys_for_view( (int) $view['id'], $tables );
			if ( empty( $stage_keys ) ) {
				WP_CLI::warning(
					sprintf(
						/* translators: %s: view slug */
						__( 'Skipping view "%s" because it has no configured stages.', 'bb-groomflow' ),
						$view['slug']
					)
				);
				continue;
			}

			$offsets           = $this->build_time_offsets( $count );
			$existing_for_view = $existing_visits_by_view[ (int) $view['id'] ] ?? array();
			$refresh_total     = min( count( $existing_for_view ), $count );

			for ( $index = 0; $index < $refresh_total; $index++ ) {
				$visit_row   = $existing_for_view[ $index ];
				$stage_key   = sanitize_key( (string) ( $visit_row['current_stage'] ?? '' ) );
				$stage_key   = ( '' !== $stage_key && in_array( $stage_key, $stage_keys, true ) ) ? $stage_key : $stage_keys[0];
				$minutes_ago = $offsets[ $index ] ?? wp_rand( 6, 28 );
				$refreshed  += $this->refresh_visit_timer( $visit_row, $stage_key, $minutes_ago, $now_utc, $now_mysql, $tables ) ? 1 : 0;
			}

			for ( $index = $refresh_total; $index < $count; $index++ ) {
				$profile     = $profiles[ $index % count( $profiles ) ];
				$stage_key   = $stage_keys[ $index % count( $stage_keys ) ];
				$guardian_id = $this->get_or_create_guardian( $profile['guardian'], $tables, $now_mysql );
				$client_id   = $this->get_or_create_client( $profile['client'], $guardian_id, $tables, $now_mysql );
				$service_ids = array();
				$flag_ids    = array();

				foreach ( $profile['services'] as $service_slug ) {
					if ( isset( $service_map[ $service_slug ] ) ) {
						$service_ids[] = (int) $service_map[ $service_slug ]['id'];
					}
				}

				foreach ( $profile['flags'] as $flag_slug ) {
					if ( isset( $flag_map[ $flag_slug ] ) ) {
						$flag_ids[] = (int) $flag_map[ $flag_slug ]['id'];
					}
				}

				$minutes_ago  = $offsets[ $index ] ?? ( ( $index * 12 ) + wp_rand( 6, 28 ) );
				$check_in_ts  = $now_utc - ( $minutes_ago * MINUTE_IN_SECONDS );
				$check_in_at  = gmdate( 'Y-m-d H:i:s', $check_in_ts );
				$elapsed_secs = max( 180, (int) ( $now_utc - $check_in_ts ) );

				$status = 'in_progress';
				if ( 'ready' === $stage_key ) {
					$status = 'ready';
				}

				$instructions  = sanitize_textarea_field( $profile['instructions'] );
				$public_notes  = sanitize_textarea_field( $profile['public_notes'] );
				$private_notes = sanitize_textarea_field( $profile['private_notes'] );

				$result = $visit_service->create_visit(
					array(
						'client_id'             => $client_id,
						'guardian_id'           => $guardian_id,
						'view_id'               => (int) $view['id'],
						'current_stage'         => $stage_key,
						'status'                => $status,
						'check_in_at'           => $check_in_at,
						'check_out_at'          => null,
						'assigned_staff'        => 0,
						'instructions'          => $instructions,
						'private_notes'         => $private_notes,
						'public_notes'          => $public_notes,
						'timer_elapsed_seconds' => $elapsed_secs,
						'timer_started_at'      => $check_in_at,
						'created_at'            => $check_in_at,
						'updated_at'            => $now_mysql,
					),
					$service_ids,
					$flag_ids
				);

				if ( is_wp_error( $result ) ) {
					$client_name = sanitize_text_field( $profile['client']['name'] ?? '' );
					WP_CLI::error(
						sprintf(
							/* translators: 1: client name 2: view slug 3: error message */
							__( 'Failed creating visit for %1$s in view "%2$s": %3$s', 'bb-groomflow' ),
							'' !== $client_name ? $client_name : __( 'Unnamed client', 'bb-groomflow' ),
							'' !== $view_slug ? $view_slug : (string) ( $view['id'] ?? '?' ),
							$result->get_error_message()
						)
					);
				}

				++$created;
			}
		}

		WP_CLI::success(
			sprintf(
				/* translators: 1: number of new visits 2: refreshed visits 3: number of views */
				__( 'Seeded %1$d new visit(s) and refreshed %2$d existing across %3$d view(s).', 'bb-groomflow' ),
				$created,
				$refreshed,
				count( $views )
			)
		);
	}

	/**
	 * Remove existing visit data so demo records can be created cleanly.
	 *
	 * @param array<string,string> $tables Table map.
	 * @return int Total deleted rows.
	 */
	private function truncate_visit_tables( array $tables ): int {
		$total = 0;

		$targets = array( 'visit_services', 'visit_flags', 'stage_history', 'visits' );
		foreach ( $targets as $key ) {
			if ( empty( $tables[ $key ] ) ) {
				continue;
			}
			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared -- Table name provided by schema map.
			$sql = $this->wpdb->prepare(
				"DELETE FROM {$tables[ $key ]} WHERE 1 = %d",
				1
			);
			// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared
			$result = $this->wpdb->query( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query prepared above.
			if ( false === $result ) {
				WP_CLI::error(
					sprintf(
						/* translators: %s: table name */
						__( 'Unable to clear table: %s.', 'bb-groomflow' ),
						$key
					)
				);
			}
			$total += (int) $result;
		}

		return $total;
	}

	/**
	 * Fetch existing visits grouped by view ID.
	 *
	 * @param array<string,string> $tables Table map.
	 * @return array<int,array<int,array<string,mixed>>>
	 */
	private function get_existing_visits_by_view( array $tables ): array {
		if ( empty( $tables['visits'] ) ) {
			return array();
		}

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared -- Table name provided by schema map.
		$sql = $this->wpdb->prepare(
			"SELECT id, view_id, current_stage FROM {$tables['visits']} WHERE 1 = %d ORDER BY view_id ASC, id ASC",
			1
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared
		$rows = $this->wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query prepared above.

		if ( empty( $rows ) ) {
			return array();
		}

		$grouped = array();
		foreach ( $rows as $row ) {
			$view_id = isset( $row['view_id'] ) ? (int) $row['view_id'] : 0;
			if ( $view_id <= 0 ) {
				continue;
			}

			if ( ! isset( $grouped[ $view_id ] ) ) {
				$grouped[ $view_id ] = array();
			}

			$grouped[ $view_id ][] = $row;
		}

		return $grouped;
	}

	/**
	 * Build staggered time offsets for demo visits.
	 *
	 * @param int $count Visit count.
	 * @return array<int,int>
	 */
	private function build_time_offsets( int $count ): array {
		$offsets = array();

		for ( $index = 0; $index < $count; $index++ ) {
			$offsets[ $index ] = ( $index * 12 ) + wp_rand( 6, 28 );
		}

		return $offsets;
	}

	/**
	 * Refresh an existing visit timer without creating a duplicate.
	 *
	 * @param array<string,mixed>  $visit_row Existing visit row.
	 * @param string               $stage_key Stage key to keep.
	 * @param int                  $minutes_ago Minutes ago the visit was checked in.
	 * @param int                  $now_utc Current time (UTC timestamp).
	 * @param string               $now_mysql Current time (MySQL datetime).
	 * @param array<string,string> $tables Table map.
	 * @return bool True on success.
	 */
	private function refresh_visit_timer( array $visit_row, string $stage_key, int $minutes_ago, int $now_utc, string $now_mysql, array $tables ): bool {
		$visit_id = isset( $visit_row['id'] ) ? (int) $visit_row['id'] : 0;
		if ( $visit_id <= 0 ) {
			return false;
		}

		$check_in_ts  = $now_utc - ( $minutes_ago * MINUTE_IN_SECONDS );
		$check_in_at  = gmdate( 'Y-m-d H:i:s', $check_in_ts );
		$elapsed_secs = max( 180, (int) ( $now_utc - $check_in_ts ) );

		$status = 'in_progress';
		if ( in_array( $stage_key, array( 'ready', 'ready_pickup', 'ready_for_service' ), true ) ) {
			$status = 'ready';
		}

		if ( ! empty( $tables['stage_history'] ) ) {
			$this->wpdb->delete( $tables['stage_history'], array( 'visit_id' => $visit_id ), array( '%d' ) );
		}

		$result = $this->wpdb->update(
			$tables['visits'],
			array(
				'current_stage'         => $stage_key,
				'status'                => $status,
				'check_in_at'           => $check_in_at,
				'check_out_at'          => null,
				'timer_elapsed_seconds' => $elapsed_secs,
				'timer_started_at'      => $check_in_at,
				'updated_at'            => $now_mysql,
			),
			array( 'id' => $visit_id ),
			array( '%s', '%s', '%s', '%s', '%d', '%s', '%s' ),
			array( '%d' )
		);

		return false !== $result;
	}

	/**
	 * Retrieve table rows keyed by slug.
	 *
	 * @param string $table Table name.
	 * @param string $slug_column Slug column.
	 * @return array<string,array<string,mixed>>
	 */
	private function map_by_slug( string $table, string $slug_column ): array {
		$slug_column = sanitize_key( $slug_column );
		if ( '' === $slug_column ) {
			return array();
		}

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared -- Table name provided by schema map.
		$sql = $this->wpdb->prepare(
			"SELECT * FROM {$table} WHERE 1 = %d",
			1
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared
		$rows = $this->wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query prepared above.

		if ( empty( $rows ) ) {
			return array();
		}

		$map = array();
		foreach ( $rows as $row ) {
			$slug = sanitize_key( (string) ( $row[ $slug_column ] ?? '' ) );
			if ( '' === $slug ) {
				continue;
			}
			$map[ $slug ] = $row;
		}

		return $map;
	}

	/**
	 * Ensure a guardian exists with the provided demo data.
	 *
	 * @param array<string,string> $guardian Guardian seed data.
	 * @param array<string,string> $tables   Table map.
	 * @param string               $timestamp Current timestamp.
	 * @return int Guardian ID.
	 */
	private function get_or_create_guardian( array $guardian, array $tables, string $timestamp ): int {
		$email    = sanitize_email( $guardian['email'] ?? '' );
		$existing = 0;

		if ( $email ) {
			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared -- Table name provided by schema map.
			$existing = (int) $this->wpdb->get_var(
				$this->wpdb->prepare(
					"SELECT id FROM {$tables['guardians']} WHERE email = %s LIMIT 1",
					$email
				)
			);
			// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared
		}

		if ( $existing > 0 ) {
			return $existing;
		}

		$data = array(
			'first_name'        => sanitize_text_field( $guardian['first_name'] ?? '' ),
			'last_name'         => sanitize_text_field( $guardian['last_name'] ?? '' ),
			'email'             => $email,
			'phone_mobile'      => sanitize_text_field( $guardian['phone'] ?? '' ),
			'preferred_contact' => 'phone',
			'notes'             => sanitize_textarea_field( $guardian['notes'] ?? '' ),
			'meta'              => wp_json_encode( array( 'source' => 'bbgf-demo' ) ),
			'created_at'        => $timestamp,
			'updated_at'        => $timestamp,
		);

		$result = $this->wpdb->insert(
			$tables['guardians'],
			$data,
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( false === $result ) {
			$guardian_label = trim(
				sprintf(
					'%s %s',
					sanitize_text_field( $guardian['first_name'] ?? '' ),
					sanitize_text_field( $guardian['last_name'] ?? '' )
				)
			);
			if ( '' === $guardian_label && '' !== $email ) {
				$guardian_label = $email;
			}

			WP_CLI::error(
				sprintf(
					/* translators: %s: guardian identifier */
					__( 'Unable to insert demo guardian "%s".', 'bb-groomflow' ),
					'' !== $guardian_label ? $guardian_label : __( 'unknown', 'bb-groomflow' )
				)
			);
		}

		return (int) $this->wpdb->insert_id;
	}

	/**
	 * Ensure a client exists with the provided demo data.
	 *
	 * @param array<string,mixed>  $client     Client seed data.
	 * @param int                  $guardian_id Guardian ID.
	 * @param array<string,string> $tables     Table map.
	 * @param string               $timestamp  Current timestamp.
	 * @return int Client ID.
	 */
	private function get_or_create_client( array $client, int $guardian_id, array $tables, string $timestamp ): int {
		$slug_seed = (string) ( $client['slug'] ?? $client['name'] ?? '' );
		if ( '' === $slug_seed ) {
			$slug_seed = 'client-' . wp_rand( 1000, 9999 );
		}
		$slug = sanitize_title( 'demo-' . $slug_seed );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared -- Table name provided by schema map.
		$existing = (int) $this->wpdb->get_var(
			$this->wpdb->prepare(
				"SELECT id FROM {$tables['clients']} WHERE slug = %s LIMIT 1",
				$slug
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared

		if ( $existing > 0 ) {
			return $existing;
		}

		$data = array(
			'name'        => sanitize_text_field( $client['name'] ?? '' ),
			'slug'        => $slug,
			'guardian_id' => $guardian_id > 0 ? $guardian_id : 0,
			'breed'       => sanitize_text_field( $client['breed'] ?? '' ),
			'weight'      => isset( $client['weight'] ) ? (float) $client['weight'] : null,
			'sex'         => sanitize_text_field( $client['sex'] ?? '' ),
			'temperament' => sanitize_text_field( $client['temperament'] ?? '' ),
			'notes'       => sanitize_textarea_field( $client['notes'] ?? '' ),
			'meta'        => wp_json_encode( array( 'source' => 'bbgf-demo' ) ),
			'created_at'  => $timestamp,
			'updated_at'  => $timestamp,
		);

		$result = $this->wpdb->insert(
			$tables['clients'],
			$data,
			array( '%s', '%s', '%d', '%s', '%f', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( false === $result ) {
			$client_label = sanitize_text_field( $client['name'] ?? '' );
			WP_CLI::error(
				sprintf(
					/* translators: %s: client name */
					__( 'Unable to insert demo client "%s".', 'bb-groomflow' ),
					'' !== $client_label ? $client_label : __( 'unknown', 'bb-groomflow' )
				)
			);
		}

		return (int) $this->wpdb->insert_id;
	}

	/**
	 * Get the ordered stage keys for a given view.
	 *
	 * @param int                  $view_id View ID.
	 * @param array<string,string> $tables Table map.
	 * @return array<int,string>
	 */
	private function get_stage_keys_for_view( int $view_id, array $tables ): array {
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared -- Table name provided by schema map.
		$stage_rows_sql = $this->wpdb->prepare(
			"SELECT stage_key FROM {$tables['view_stages']} WHERE view_id = %d ORDER BY sort_order ASC, id ASC",
			$view_id
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared
		$rows = $this->wpdb->get_results( $stage_rows_sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query prepared above.

		if ( ! empty( $rows ) ) {
			return array_values(
				array_filter(
					array_map(
						static function ( $row ) {
							$stage = sanitize_key( (string) ( $row['stage_key'] ?? '' ) );
							return '' === $stage ? null : $stage;
						},
						$rows
					)
				)
			);
		}

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared -- Table name provided by schema map.
		$sql = $this->wpdb->prepare(
			"SELECT stage_key FROM {$tables['stages']} WHERE 1 = %d ORDER BY sort_order ASC, id ASC",
			1
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared
		$library = $this->wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query prepared above.

		if ( empty( $library ) ) {
			return array();
		}

		return array_values(
			array_filter(
				array_map(
					static function ( $row ) {
						$stage = sanitize_key( (string) ( $row['stage_key'] ?? '' ) );
						return '' === $stage ? null : $stage;
					},
					$library
				)
			)
		);
	}

	/**
	 * Demo profile definitions used for seeding.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private function get_demo_profiles(): array {
		return array(
			array(
				'client'        => array(
					'name'        => 'Misty',
					'slug'        => 'misty',
					'breed'       => 'Golden Retriever',
					'weight'      => 63.5,
					'temperament' => 'Gentle',
					'notes'       => 'Prefers lavender finishing spray.',
				),
				'guardian'      => array(
					'first_name' => 'Jessie',
					'last_name'  => 'Chen',
					'email'      => 'demo+jessie@groomflow.test',
					'phone'      => '555-0101',
					'notes'      => 'Text on pickup, works nearby.',
				),
				'services'      => array( 'full-groom', 'teeth-polish' ),
				'flags'         => array( 'vip-client' ),
				'instructions'  => 'Use hypoallergenic shampoo and low heat dryer.',
				'public_notes'  => 'Pickup at 3:00pm. Treat waiting at checkout.',
				'private_notes' => 'Add groomer notes to CRM after pickup.',
			),
			array(
				'client'        => array(
					'name'        => 'Baxter',
					'slug'        => 'baxter',
					'breed'       => 'Mini Schnauzer',
					'weight'      => 22.1,
					'temperament' => 'Alert',
					'notes'       => 'Clipper guard #1 on body, #2 on legs.',
				),
				'guardian'      => array(
					'first_name' => 'Morgan',
					'last_name'  => 'Lopez',
					'email'      => 'demo+morgan@groomflow.test',
					'phone'      => '555-0102',
					'notes'      => 'Mention add-on teeth polish upsell.',
				),
				'services'      => array( 'full-groom' ),
				'flags'         => array( 'needs-muzzle' ),
				'instructions'  => 'Apply calming spray before grooming.',
				'public_notes'  => 'Guardian will wait in lobby.',
				'private_notes' => 'Mark grooming notes in Baxter profile after visit.',
			),
			array(
				'client'        => array(
					'name'        => 'Luna',
					'slug'        => 'luna',
					'breed'       => 'Samoyed',
					'weight'      => 48.7,
					'temperament' => 'Playful',
					'notes'       => 'Brush coat thoroughly before bath.',
				),
				'guardian'      => array(
					'first_name' => 'Cam',
					'last_name'  => 'Patel',
					'email'      => 'demo+cam@groomflow.test',
					'phone'      => '555-0103',
					'notes'      => 'Prefers email updates.',
				),
				'services'      => array( 'spa-bath', 'teeth-polish' ),
				'flags'         => array( 'first-visit' ),
				'instructions'  => 'Add extra conditioner, schedule follow-up for 6 weeks.',
				'public_notes'  => 'Guardian returning at 4:15pm.',
				'private_notes' => '',
			),
			array(
				'client'        => array(
					'name'        => 'Pepper',
					'slug'        => 'pepper',
					'breed'       => 'Australian Shepherd',
					'weight'      => 41.4,
					'temperament' => 'High energy',
					'notes'       => 'Trim paw fur tight for traction.',
				),
				'guardian'      => array(
					'first_name' => 'Drew',
					'last_name'  => 'Holland',
					'email'      => 'demo+drew@groomflow.test',
					'phone'      => '555-0104',
					'notes'      => 'Has standing Friday appointments.',
				),
				'services'      => array( 'full-groom', 'spa-bath' ),
				'flags'         => array(),
				'instructions'  => 'Use blueberry facial, towel dry before blowout.',
				'public_notes'  => 'Add seasonal bandana before pickup.',
				'private_notes' => '',
			),
			array(
				'client'        => array(
					'name'        => 'Clover',
					'slug'        => 'clover',
					'breed'       => 'Shih Tzu',
					'weight'      => 14.9,
					'temperament' => 'Calm',
					'notes'       => 'Trim face short, leave top knot.',
				),
				'guardian'      => array(
					'first_name' => 'Robin',
					'last_name'  => 'Nguyen',
					'email'      => 'demo+robin@groomflow.test',
					'phone'      => '555-0105',
					'notes'      => 'Wants photo texted when finished.',
				),
				'services'      => array( 'full-groom' ),
				'flags'         => array( 'vip-client' ),
				'instructions'  => 'Finish with coat gloss spray.',
				'public_notes'  => 'Send progress photo when styling starts.',
				'private_notes' => '',
			),
			array(
				'client'        => array(
					'name'        => 'Tofu',
					'slug'        => 'tofu',
					'breed'       => 'French Bulldog',
					'weight'      => 26.2,
					'temperament' => 'Chill',
					'notes'       => 'Clean facial folds carefully.',
				),
				'guardian'      => array(
					'first_name' => 'Sky',
					'last_name'  => 'Martinez',
					'email'      => 'demo+sky@groomflow.test',
					'phone'      => '555-0106',
					'notes'      => 'Ask about nail trim add-on.',
				),
				'services'      => array( 'spa-bath' ),
				'flags'         => array(),
				'instructions'  => 'Keep water lukewarm, dry with towel + cool air.',
				'public_notes'  => 'Ready for couch cuddles after grooming!',
				'private_notes' => '',
			),
		);
	}
}
