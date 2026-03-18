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
	 * Fetch visits for the Manage Visits admin table.
	 *
	 * @param array<string,mixed>  $filters  Filter values.
	 * @param array<string,string> $ordering Order configuration (orderby, order).
	 * @param int                  $limit    Max rows.
	 * @param int                  $offset   Offset.
	 * @return array<int,array<string,mixed>>
	 */
	public function get_manage_visits( array $filters, array $ordering, int $limit, int $offset ): array {
		$wpdb                 = $this->wpdb;
		$visits_table         = $this->table( 'visits' );
		$clients_table        = $this->table( 'clients' );
		$guardians_table      = $this->table( 'guardians' );
		$views_table          = $this->table( 'views' );
		$visit_services_table = $this->table( 'visit_services' );

		if ( '' === $visits_table || '' === $clients_table || '' === $guardians_table || '' === $views_table ) {
			return array();
		}

		$where = $this->build_manage_visits_where( $filters, $visit_services_table );
		if ( $where['invalid'] ) {
			return array();
		}

		$order_sql = $this->build_manage_visits_order( $ordering );

		$sql = 'SELECT v.id, v.client_id, v.guardian_id, v.view_id, v.current_stage, v.status, v.check_in_at, v.check_out_at, v.assigned_staff, v.instructions, v.private_notes, v.public_notes, v.timer_started_at, v.timer_elapsed_seconds, v.created_at, v.updated_at,
				c.name AS client_name,
				g.first_name AS guardian_first_name, g.last_name AS guardian_last_name,
				vw.name AS view_name, vw.slug AS view_slug
			FROM %i AS v
			LEFT JOIN %i AS c ON c.id = v.client_id
			LEFT JOIN %i AS g ON g.id = v.guardian_id
			LEFT JOIN %i AS vw ON vw.id = v.view_id'
			. $where['sql'] // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Dynamic clauses built from sanitized values.
			. $order_sql // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Order clause built from allowlisted columns.
			. ' LIMIT %d OFFSET %d';

		$params = array_merge(
			array( $visits_table, $clients_table, $guardians_table, $views_table ),
			$where['params'],
			array( $limit, $offset )
		);

		$prepared = $wpdb->prepare( // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Dynamic clauses built from sanitized values.
			$sql, // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Assembled with sanitized placeholders.
			...$params
		);

		return $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
			$prepared, // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Prepared above.
			ARRAY_A
		);
	}

	/**
	 * Count visits for the Manage Visits admin table.
	 *
	 * @param array<string,mixed> $filters Filter values.
	 * @return int
	 */
	public function get_manage_visits_count( array $filters ): int {
		$wpdb                 = $this->wpdb;
		$visits_table         = $this->table( 'visits' );
		$clients_table        = $this->table( 'clients' );
		$guardians_table      = $this->table( 'guardians' );
		$visit_services_table = $this->table( 'visit_services' );

		if ( '' === $visits_table || '' === $clients_table || '' === $guardians_table ) {
			return 0;
		}

		$where = $this->build_manage_visits_where( $filters, $visit_services_table );
		if ( $where['invalid'] ) {
			return 0;
		}

		$sql = 'SELECT COUNT(*) FROM %i AS v
			LEFT JOIN %i AS c ON c.id = v.client_id
			LEFT JOIN %i AS g ON g.id = v.guardian_id'
			. $where['sql']; // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Dynamic clauses built from sanitized values.

		$params = array_merge(
			array( $visits_table, $clients_table, $guardians_table ),
			$where['params']
		);

		$prepared = $wpdb->prepare( $sql, ...$params ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Dynamic clauses built from sanitized values.

		return (int) $wpdb->get_var( $prepared ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Build WHERE clause for Manage Visits queries.
	 *
	 * @param array<string,mixed> $filters              Filters.
	 * @param string              $visit_services_table Visit services table name.
	 * @return array{sql:string,params:array,invalid:bool}
	 */
	private function build_manage_visits_where( array $filters, string $visit_services_table ): array {
		$where  = array();
		$params = array();

		$stage = isset( $filters['stage'] ) ? sanitize_key( (string) $filters['stage'] ) : '';
		if ( '' !== $stage ) {
			$where[]  = 'v.current_stage = %s';
			$params[] = $stage;
		}

		$view_id = isset( $filters['view_id'] ) ? (int) $filters['view_id'] : 0;
		if ( $view_id > 0 ) {
			$where[]  = 'v.view_id = %d';
			$params[] = $view_id;
		}

		$service_id = isset( $filters['service_id'] ) ? (int) $filters['service_id'] : 0;
		if ( $service_id > 0 ) {
			if ( '' === $visit_services_table ) {
				return array(
					'sql'     => '',
					'params'  => array(),
					'invalid' => true,
				);
			}

			$where[]  = 'EXISTS (SELECT 1 FROM %i AS vs WHERE vs.visit_id = v.id AND vs.service_id = %d)';
			$params[] = $visit_services_table;
			$params[] = $service_id;
		}

		$status = isset( $filters['status'] ) ? sanitize_key( (string) $filters['status'] ) : '';
		if ( '' !== $status ) {
			if ( 'active' === $status ) {
				$where[]  = 'v.check_out_at IS NULL AND (v.status IS NULL OR v.status = %s OR v.status NOT IN (%s, %s))';
				$params[] = '';
				$params[] = 'completed';
				$params[] = 'cancelled';
			} elseif ( 'completed' === $status ) {
				$where[]  = '(v.check_out_at IS NOT NULL OR v.status = %s)';
				$params[] = 'completed';
			} elseif ( 'cancelled' === $status ) {
				$where[]  = 'v.status = %s';
				$params[] = 'cancelled';
			} else {
				$where[]  = 'v.status = %s';
				$params[] = $status;
			}
		}

		$date_start = isset( $filters['date_start'] ) ? trim( (string) $filters['date_start'] ) : '';
		if ( '' !== $date_start ) {
			$where[]  = 'COALESCE(v.check_in_at, v.created_at) >= %s';
			$params[] = $date_start . ' 00:00:00';
		}

		$date_end = isset( $filters['date_end'] ) ? trim( (string) $filters['date_end'] ) : '';
		if ( '' !== $date_end ) {
			$where[]  = 'COALESCE(v.check_in_at, v.created_at) <= %s';
			$params[] = $date_end . ' 23:59:59';
		}

		$search = isset( $filters['search'] ) ? trim( (string) $filters['search'] ) : '';
		if ( '' !== $search ) {
			$like = '%' . $this->wpdb->esc_like( $search ) . '%';
			if ( is_numeric( $search ) ) {
				$where[]  = '(v.id = %d OR c.name LIKE %s OR g.first_name LIKE %s OR g.last_name LIKE %s OR CONCAT(g.first_name, " ", g.last_name) LIKE %s)';
				$params[] = (int) $search;
				$params[] = $like;
				$params[] = $like;
				$params[] = $like;
				$params[] = $like;
			} else {
				$where[]  = '(c.name LIKE %s OR g.first_name LIKE %s OR g.last_name LIKE %s OR CONCAT(g.first_name, " ", g.last_name) LIKE %s)';
				$params[] = $like;
				$params[] = $like;
				$params[] = $like;
				$params[] = $like;
			}
		}

		$where_sql = '';
		if ( ! empty( $where ) ) {
			$where_sql = ' WHERE ' . implode( ' AND ', $where );
		}

		return array(
			'sql'     => $where_sql,
			'params'  => $params,
			'invalid' => false,
		);
	}

	/**
	 * Build ORDER BY clause for Manage Visits queries.
	 *
	 * @param array<string,string> $ordering Order config.
	 * @return string
	 */
	private function build_manage_visits_order( array $ordering ): string {
		$orderby = isset( $ordering['orderby'] ) ? sanitize_key( (string) $ordering['orderby'] ) : '';
		$order   = isset( $ordering['order'] ) ? strtoupper( (string) $ordering['order'] ) : 'DESC';

		if ( ! in_array( $order, array( 'ASC', 'DESC' ), true ) ) {
			$order = 'DESC';
		}

		switch ( $orderby ) {
			case 'visit_id':
				return " ORDER BY v.id {$order}";
			case 'check_in_at':
				return " ORDER BY COALESCE(v.check_in_at, v.created_at, v.updated_at) {$order}, v.id {$order}";
			case 'client':
				return " ORDER BY c.name {$order}, v.id {$order}";
			case 'guardian':
				return " ORDER BY g.last_name {$order}, g.first_name {$order}, v.id {$order}";
			case 'status':
				return " ORDER BY v.status {$order}, v.id {$order}";
			case 'stage':
				return " ORDER BY v.current_stage {$order}, v.id {$order}";
			case 'check_out_at':
				return " ORDER BY v.check_out_at {$order}, v.id {$order}";
			default:
				return " ORDER BY COALESCE(v.check_in_at, v.created_at, v.updated_at) {$order}, v.id {$order}";
		}
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
