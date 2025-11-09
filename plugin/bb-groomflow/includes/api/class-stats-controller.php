<?php
/**
 * Statistics REST controller.
 *
 * @package BB_GroomFlow
 */

namespace BBGF\API;

use BBGF\Plugin;
use DateTimeImmutable;
use DateTimeZone;
use WP_Error;
use WP_REST_Server;
use wpdb;

/**
 * Provides aggregated statistics for dashboards.
 */
class Stats_Controller extends REST_Controller {
	// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	/**
	 * REST base.
	 *
	 * @var string
	 */
	protected $rest_base = 'stats';

	/**
	 * Plugin instance.
	 *
	 * @var Plugin
	 */
	private Plugin $plugin;

	/**
	 * Database handle.
	 *
	 * @var wpdb
	 */
	private wpdb $wpdb;

	/**
	 * Table map.
	 *
	 * @var array<string,string>
	 */
	private array $tables;

	/**
	 * Constructor.
	 *
	 * @param Plugin $plugin Plugin instance.
	 */
	public function __construct( Plugin $plugin ) {
		parent::__construct();

		$this->plugin = $plugin;
		$this->wpdb   = $plugin->get_wpdb();
		$this->tables = $plugin->get_table_names();
	}

	/**
	 * Register REST routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/daily',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_daily_stats' ),
					'permission_callback' => array( $this, 'reports_permission_check' ),
					'args'                => array(
						'date' => array(
							'description' => __( 'Target date (Y-m-d). Defaults to today.', 'bb-groomflow' ),
							'type'        => 'string',
							'required'    => false,
						),
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/stage-averages',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_stage_averages' ),
					'permission_callback' => array( $this, 'reports_permission_check' ),
					'args'                => array(
						'start' => array(
							'description' => __( 'Range start (Y-m-d). Defaults to 7 days ago.', 'bb-groomflow' ),
							'type'        => 'string',
						),
						'end'   => array(
							'description' => __( 'Range end (Y-m-d). Defaults to today.', 'bb-groomflow' ),
							'type'        => 'string',
						),
						'view'  => array(
							'description' => __( 'Optional view slug filter.', 'bb-groomflow' ),
							'type'        => 'string',
						),
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/service-mix',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_service_mix' ),
					'permission_callback' => array( $this, 'reports_permission_check' ),
					'args'                => array(
						'start' => array(
							'description' => __( 'Range start (Y-m-d). Defaults to 30 days ago.', 'bb-groomflow' ),
							'type'        => 'string',
						),
						'end'   => array(
							'description' => __( 'Range end (Y-m-d). Defaults to today.', 'bb-groomflow' ),
							'type'        => 'string',
						),
						'view'  => array(
							'description' => __( 'Optional view slug filter.', 'bb-groomflow' ),
							'type'        => 'string',
						),
					),
				),
			)
		);
	}

	/**
	 * Capability check shared by stats endpoints.
	 *
	 * @return true|WP_Error
	 */
	public function reports_permission_check() {
		return $this->check_capability( 'bbgf_view_reports' );
	}

	/**
	 * Return service and package usage counts for a date range.
	 *
	 * @param \WP_REST_Request $request Request instance.
	 * @return \WP_REST_Response|WP_Error
	 */
	public function get_service_mix( $request ) {
		$start_input = (string) $request->get_param( 'start' );
		$end_input   = (string) $request->get_param( 'end' );
		$view_slug   = sanitize_title( (string) $request->get_param( 'view' ) );

		$end   = $this->parse_date( $end_input, 'today' );
		$start = $this->parse_date( $start_input, '-30 days' );

		if ( is_wp_error( $start ) ) {
			return $start;
		}

		if ( is_wp_error( $end ) ) {
			return $end;
		}

		if ( $start > $end ) {
			return new WP_Error(
				'bbgf_invalid_date_range',
				__( 'Start date must be before end date.', 'bb-groomflow' ),
				array( 'status' => 400 )
			);
		}

		$services = $this->get_service_usage( $start, $end, $view_slug );
		$packages = $this->get_package_usage( $start, $end, $view_slug );

		$response = array(
			'range'    => array(
				'start' => $start->format( 'Y-m-d' ),
				'end'   => $end->format( 'Y-m-d' ),
			),
			'view'     => '' !== $view_slug ? $view_slug : null,
			'services' => $services,
			'packages' => $packages,
		);

		return rest_ensure_response( $response );
	}

	/**
	 * Aggregate service usage counts.
	 *
	 * @param DateTimeImmutable $start     Range start.
	 * @param DateTimeImmutable $end       Range end.
	 * @param string            $view_slug Optional view slug.
	 * @return array<int,array<string,mixed>>
	 */
	private function get_service_usage( DateTimeImmutable $start, DateTimeImmutable $end, string $view_slug ): array {
		$visit_table    = $this->tables['visits'];
		$join_table     = $this->tables['visit_services'];
		$services_table = $this->tables['services'];
		$views_table    = $this->tables['views'];

		$sql = "SELECT vs.service_id AS id, s.name, s.icon, s.color, COUNT(*) AS usage_count
			FROM {$join_table} AS vs
			INNER JOIN {$visit_table} AS v ON v.id = vs.visit_id
			LEFT JOIN {$services_table} AS s ON s.id = vs.service_id
			LEFT JOIN {$views_table} AS vw ON vw.id = v.view_id
			WHERE v.created_at BETWEEN %s AND %s";

		$args = array(
			$start->setTime( 0, 0, 0 )->format( 'Y-m-d H:i:s' ),
			$end->setTime( 23, 59, 59 )->format( 'Y-m-d H:i:s' ),
		);

		if ( '' !== $view_slug ) {
			$sql   .= ' AND vw.slug = %s';
			$args[] = $view_slug;
		}

		$sql .= ' GROUP BY vs.service_id ORDER BY usage_count DESC';

		$query = $this->wpdb->prepare( $sql, ...$args );
		$rows  = $this->wpdb->get_results( $query, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		$total = 0;
		foreach ( (array) $rows as $row ) {
			$total += (int) ( $row['usage_count'] ?? 0 );
		}

		return array_map(
			static function ( array $row ) use ( $total ): array {
				$count = (int) ( $row['usage_count'] ?? 0 );
				$share = $total > 0 ? round( ( $count / $total ) * 100, 2 ) : 0.0;

				return array(
					'id'         => (int) ( $row['id'] ?? 0 ),
					'name'       => (string) ( $row['name'] ?? '' ),
					'icon'       => (string) ( $row['icon'] ?? '' ),
					'color'      => (string) ( $row['color'] ?? '' ),
					'count'      => $count,
					'percentage' => $share,
				);
			},
			(array) $rows
		);
	}

	/**
	 * Aggregate package usage counts based on visit services rows.
	 *
	 * @param DateTimeImmutable $start     Range start.
	 * @param DateTimeImmutable $end       Range end.
	 * @param string            $view_slug Optional view slug.
	 * @return array<int,array<string,mixed>>
	 */
	private function get_package_usage( DateTimeImmutable $start, DateTimeImmutable $end, string $view_slug ): array {
		if ( ! isset( $this->tables['service_packages'] ) ) {
			return array();
		}

		$visit_table    = $this->tables['visits'];
		$join_table     = $this->tables['visit_services'];
		$packages_table = $this->tables['service_packages'];
		$views_table    = $this->tables['views'];

		$sql = "SELECT vs.package_id AS id, p.name, p.icon, p.color, COUNT(*) AS usage_count
			FROM {$join_table} AS vs
			INNER JOIN {$visit_table} AS v ON v.id = vs.visit_id
			LEFT JOIN {$packages_table} AS p ON p.id = vs.package_id
			LEFT JOIN {$views_table} AS vw ON vw.id = v.view_id
			WHERE vs.package_id IS NOT NULL AND v.created_at BETWEEN %s AND %s";

		$args = array(
			$start->setTime( 0, 0, 0 )->format( 'Y-m-d H:i:s' ),
			$end->setTime( 23, 59, 59 )->format( 'Y-m-d H:i:s' ),
		);

		if ( '' !== $view_slug ) {
			$sql   .= ' AND vw.slug = %s';
			$args[] = $view_slug;
		}

		$sql .= ' GROUP BY vs.package_id ORDER BY usage_count DESC';

		$query = $this->wpdb->prepare( $sql, ...$args );
		$rows  = $this->wpdb->get_results( $query, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		$total = 0;
		foreach ( (array) $rows as $row ) {
			$total += (int) ( $row['usage_count'] ?? 0 );
		}

		return array_map(
			static function ( array $row ) use ( $total ): array {
				$count = (int) ( $row['usage_count'] ?? 0 );
				$share = $total > 0 ? round( ( $count / $total ) * 100, 2 ) : 0.0;

				return array(
					'id'         => (int) ( $row['id'] ?? 0 ),
					'name'       => (string) ( $row['name'] ?? '' ),
					'icon'       => (string) ( $row['icon'] ?? '' ),
					'color'      => (string) ( $row['color'] ?? '' ),
					'count'      => $count,
					'percentage' => $share,
				);
			},
			(array) $rows
		);
	}

	/**
	 * Return daily visit summary.
	 *
	 * @param \WP_REST_Request $request Request instance.
	 * @return \WP_REST_Response|WP_Error
	 */
	public function get_daily_stats( $request ) {
		$input_date = (string) $request->get_param( 'date' );
		$target     = $this->parse_date( $input_date, 'today' );

		if ( is_wp_error( $target ) ) {
			return $target;
		}

		$sql = $this->wpdb->prepare(
			"SELECT
				COUNT(*) AS total_visits,
				SUM(CASE WHEN check_out_at IS NULL THEN 1 ELSE 0 END) AS in_progress,
				SUM(CASE WHEN check_out_at IS NOT NULL THEN 1 ELSE 0 END) AS completed_visits,
				AVG(
					CASE
						WHEN check_in_at IS NOT NULL AND check_out_at IS NOT NULL
						THEN TIMESTAMPDIFF(SECOND, check_in_at, check_out_at)
						ELSE NULL
					END
				) AS avg_duration_seconds
			FROM {$this->tables['visits']}
			WHERE DATE(created_at) = %s",
			$target->format( 'Y-m-d' )
		);

		$row = $this->wpdb->get_row( $sql, ARRAY_A );

		if ( ! is_array( $row ) ) {
			$row = array(
				'total_visits'         => 0,
				'in_progress'          => 0,
				'completed_visits'     => 0,
				'avg_duration_seconds' => null,
			);
		}

		$average_minutes = null;
		if ( isset( $row['avg_duration_seconds'] ) && null !== $row['avg_duration_seconds'] ) {
			$average_minutes = round( (float) $row['avg_duration_seconds'] / 60, 2 );
		}

		$response = array(
			'date'     => $target->format( 'Y-m-d' ),
			'summary'  => array(
				'total'       => (int) ( $row['total_visits'] ?? 0 ),
				'in_progress' => (int) ( $row['in_progress'] ?? 0 ),
				'completed'   => (int) ( $row['completed_visits'] ?? 0 ),
			),
			'averages' => array(
				'duration_seconds' => isset( $row['avg_duration_seconds'] ) ? (float) $row['avg_duration_seconds'] : null,
				'duration_minutes' => $average_minutes,
			),
		);

		return rest_ensure_response( $response );
	}

	/**
	 * Return average stage dwell times.
	 *
	 * @param \WP_REST_Request $request Request instance.
	 * @return \WP_REST_Response|WP_Error
	 */
	public function get_stage_averages( $request ) {
		$start_input = (string) $request->get_param( 'start' );
		$end_input   = (string) $request->get_param( 'end' );
		$view_slug   = sanitize_title( (string) $request->get_param( 'view' ) );

		$end   = $this->parse_date( $end_input, 'today' );
		$start = $this->parse_date( $start_input, '-7 days' );

		if ( is_wp_error( $start ) ) {
			return $start;
		}

		if ( is_wp_error( $end ) ) {
			return $end;
		}

		if ( $start > $end ) {
			return new WP_Error(
				'bbgf_invalid_date_range',
				__( 'Start date must be before end date.', 'bb-groomflow' ),
				array( 'status' => 400 )
			);
		}

		$sql = "SELECT h.to_stage AS stage_key, AVG(h.elapsed_seconds) AS avg_seconds, COUNT(*) AS samples
			FROM {$this->tables['stage_history']} AS h
			LEFT JOIN {$this->tables['visits']} AS v ON v.id = h.visit_id
			LEFT JOIN {$this->tables['views']} AS vw ON vw.id = v.view_id
			WHERE h.changed_at BETWEEN %s AND %s";

		$sql_args = array(
			$start->setTime( 0, 0, 0 )->format( 'Y-m-d H:i:s' ),
			$end->setTime( 23, 59, 59 )->format( 'Y-m-d H:i:s' ),
		);

		if ( '' !== $view_slug ) {
				$sql   .= ' AND vw.slug = %s';
			$sql_args[] = $view_slug;
		}

		$sql .= '
			GROUP BY h.to_stage
			HAVING samples > 0
			ORDER BY avg_seconds ASC';

		$query = $this->wpdb->prepare( $sql, ...$sql_args );

		$rows = $this->wpdb->get_results( $query, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		$stage_labels = $this->get_stage_labels();
		$data         = array();

		foreach ( (array) $rows as $row ) {
			$key         = (string) ( $row['stage_key'] ?? '' );
			$avg_seconds = isset( $row['avg_seconds'] ) ? (float) $row['avg_seconds'] : 0.0;

			$data[] = array(
				'stage_key'       => $key,
				'label'           => $stage_labels[ $key ] ?? ucwords( str_replace( array( '_', '-' ), ' ', $key ) ),
				'average_seconds' => $avg_seconds,
				'average_minutes' => round( $avg_seconds / 60, 2 ),
				'sample_size'     => (int) ( $row['samples'] ?? 0 ),
			);
		}

		$response = array(
			'range'  => array(
				'start' => $start->format( 'Y-m-d' ),
				'end'   => $end->format( 'Y-m-d' ),
			),
			'view'   => '' !== $view_slug ? $view_slug : null,
			'stages' => $data,
		);

		return rest_ensure_response( $response );
	}

	/**
	 * Parse a date string into a DateTimeImmutable.
	 *
	 * @param string $value    Raw value.
	 * @param string $fallback Relative strtotime string when empty.
	 * @return DateTimeImmutable|WP_Error
	 */
	private function parse_date( string $value, string $fallback ) {
		$timezone = new DateTimeZone( 'UTC' );

		if ( '' === $value ) {
			return new DateTimeImmutable( $fallback, $timezone );
		}

		$timestamp = strtotime( $value );
		if ( false === $timestamp ) {
			return new WP_Error(
				'bbgf_invalid_date',
				__( 'Invalid date format. Use Y-m-d.', 'bb-groomflow' ),
				array( 'status' => 400 )
			);
		}

		return ( new DateTimeImmutable( '@' . $timestamp ) )->setTimezone( $timezone );
	}

	/**
	 * Retrieve stage labels keyed by stage key.
	 *
	 * @return array<string,string>
	 */
	private function get_stage_labels(): array {
		$sql  = "SELECT stage_key, label FROM {$this->tables['stages']}";
		$rows = $this->wpdb->get_results( $sql, ARRAY_A );

		$labels = array();
		foreach ( (array) $rows as $row ) {
			$key = sanitize_key( (string) ( $row['stage_key'] ?? '' ) );
			if ( '' === $key ) {
				continue;
			}

			$labels[ $key ] = (string) ( $row['label'] ?? $key );
		}

		return $labels;
	}
	// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
}
