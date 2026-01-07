<?php
/**
 * Visit query repository.
 *
 * @package BB_GroomFlow
 */

namespace BBGF\Data;

use wpdb;

/**
 * Encapsulates visit-focused SQL queries.
 */
final class Visit_Repository {
	/**
	 * Database handle.
	 *
	 * @var wpdb
	 */
	private wpdb $wpdb;

	/**
	 * Plugin table map.
	 *
	 * @var array<string,string>
	 */
	private array $tables;

	/**
	 * Allowlisted identifiers for %i placeholders.
	 *
	 * @var array<int,string>
	 */
	private array $allowed_identifiers;

	/**
	 * Constructor.
	 *
	 * @param wpdb                 $wpdb   Database handle.
	 * @param array<string,string> $tables Table map.
	 */
	public function __construct( wpdb $wpdb, array $tables ) {
		$this->wpdb                = $wpdb;
		$this->tables              = $tables;
		$this->allowed_identifiers = array_values( $tables );
	}

	/**
	 * Fetch board visits for a view.
	 *
	 * @param int    $view_id           View ID.
	 * @param array  $stage_filter_list Stage keys.
	 * @param string $modified_after    Optional RFC3339 timestamp.
	 * @return array<int,array<string,mixed>>
	 */
	public function get_board_visits( int $view_id, array $stage_filter_list = array(), string $modified_after = '' ): array {
		$wpdb            = $this->wpdb;
		$visits_table    = $this->table( 'visits' );
		$clients_table   = $this->table( 'clients' );
		$guardians_table = $this->table( 'guardians' );

		if ( '' === $visits_table || '' === $clients_table || '' === $guardians_table ) {
			return array();
		}

		$modified_clause = '';
		$modified_args   = array();
		if ( '' !== $modified_after ) {
			$modified_time = strtotime( $modified_after );
			if ( false !== $modified_time ) {
				$modified_clause = ' AND v.updated_at >= %s';
				$modified_args[] = gmdate( 'Y-m-d H:i:s', $modified_time );
			}
		}

		$stage_clause = '';
		$stage_args   = array();
		if ( ! empty( $stage_filter_list ) ) {
			$prepared = Query_Helpers::prepare_in_clause( $stage_filter_list, 'sanitize_key', '%s' );
			if ( '' !== $prepared['placeholders'] ) {
				$stage_clause = ' AND v.current_stage IN (' . $prepared['placeholders'] . ')'; // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Placeholder list built from sanitized values.
				$stage_args   = $prepared['values'];
			}
		}

		$args_sql = array_merge(
			array(
				$visits_table,
				$clients_table,
				$guardians_table,
				$view_id,
				'completed',
			),
			$modified_args,
			$stage_args
		);

		return $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare( // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Dynamic clauses built from sanitized values.
				'SELECT v.id, v.client_id, v.guardian_id, v.view_id, v.current_stage, v.status, v.check_in_at, v.check_out_at, v.assigned_staff, v.instructions, v.private_notes, v.public_notes, v.timer_started_at, v.timer_elapsed_seconds, v.created_at, v.updated_at,
					c.name AS client_name, c.slug AS client_slug, c.breed AS client_breed, c.weight AS client_weight, c.meta AS client_meta,
					g.first_name AS guardian_first_name, g.last_name AS guardian_last_name, g.phone_mobile AS guardian_phone, g.email AS guardian_email
				FROM %i AS v
				LEFT JOIN %i AS c ON c.id = v.client_id
				LEFT JOIN %i AS g ON g.id = v.guardian_id
				WHERE v.view_id = %d AND v.check_out_at IS NULL AND (v.status IS NULL OR v.status <> %s)'
				. $modified_clause // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Dynamic clauses built from sanitized values.
				. $stage_clause // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Dynamic clauses built from sanitized values.
				. ' ORDER BY COALESCE(v.check_in_at, v.created_at, v.updated_at) ASC, v.id ASC', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Dynamic clauses built from sanitized values.
				...$args_sql
			),
			ARRAY_A
		);
	}

	/**
	 * Retrieve raw visit row with joins.
	 *
	 * @param int $visit_id Visit ID.
	 * @return array<string,mixed>|null
	 */
	public function get_visit_row( int $visit_id ): ?array {
		if ( $visit_id <= 0 ) {
			return null;
		}

		$wpdb            = $this->wpdb;
		$visits_table    = $this->table( 'visits' );
		$clients_table   = $this->table( 'clients' );
		$guardians_table = $this->table( 'guardians' );

		if ( '' === $visits_table || '' === $clients_table || '' === $guardians_table ) {
			return null;
		}

		$row = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				'SELECT v.*, c.name AS client_name, c.slug AS client_slug, c.breed AS client_breed, c.weight AS client_weight, c.meta AS client_meta,
					g.first_name AS guardian_first_name, g.last_name AS guardian_last_name, g.phone_mobile AS guardian_phone, g.email AS guardian_email
				FROM %i AS v
				LEFT JOIN %i AS c ON c.id = v.client_id
				LEFT JOIN %i AS g ON g.id = v.guardian_id
				WHERE v.id = %d',
				$visits_table,
				$clients_table,
				$guardians_table,
				$visit_id
			),
			ARRAY_A
		);

		return is_array( $row ) ? $row : null;
	}

	/**
	 * Resolve a safe table identifier for %i usage.
	 *
	 * @param string $key Table map key.
	 * @return string
	 */
	private function table( string $key ): string {
		$table = $this->tables[ $key ] ?? '';

		return Query_Helpers::safe_identifier( $table, $this->allowed_identifiers );
	}
}
