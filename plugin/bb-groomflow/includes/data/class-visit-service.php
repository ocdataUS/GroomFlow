<?php
/**
 * Visit data services.
 *
 * @package BB_GroomFlow
 */

namespace BBGF\Data;

use BBGF\Plugin;
use DateTimeInterface;
use WP_Error;
use wpdb;

/**
 * Encapsulates visit CRUD, stage transitions, board payloads, and media helpers.
 */
class Visit_Service {
	/**
	 * Meta key linking attachment IDs to visits.
	 */
	private const VISIT_PHOTO_META_KEY          = '_bbgf_visit_id';
	private const VISIT_PHOTO_GUARDIAN_META_KEY = '_bbgf_photo_guardian_visible';
	private const VISIT_PHOTO_PRIMARY_META_KEY  = '_bbgf_photo_is_primary';

	/**
	 * Cache group for board/stage/view payloads.
	 */
	private const CACHE_GROUP = 'bbgf_board_cache';

	/**
	 * Cache index key for tracking view-level board payload caches.
	 */
	private const CACHE_INDEX_ALL_VIEWS = 'board_cache_views';

	/**
	 * Default cache TTLs (seconds).
	 */
	private const DEFAULT_BOARD_CACHE_TTL    = 5;
	private const DEFAULT_METADATA_CACHE_TTL = 120;

	/**
	 * Client-level meta keys we care about.
	 */
	private const CLIENT_META_FLAGS      = 'flags';
	private const CLIENT_META_MAIN_PHOTO = 'main_photo_id';

	/**
	 * Plugin reference.
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
	 * Plugin table map.
	 *
	 * @var array<string,string>
	 */
	private array $tables;

	/**
	 * Visit repository for SQL queries.
	 *
	 * @var Visit_Repository
	 */
	private Visit_Repository $visit_repository;

	/**
	 * Constructor.
	 *
	 * @param Plugin $plugin Plugin instance.
	 */
	public function __construct( Plugin $plugin ) {
		$this->plugin           = $plugin;
		$this->wpdb             = $plugin->get_wpdb();
		$this->tables           = $plugin->get_table_names();
		$this->visit_repository = new Visit_Repository( $this->wpdb, $this->tables );
	}

	/**
	 * Decode a JSON meta blob safely.
	 *
	 * @param string|null $raw_meta Raw meta value from the database.
	 * @return array<string,mixed>
	 */
	private function decode_meta_value( ?string $raw_meta ): array {
		if ( null === $raw_meta || '' === $raw_meta ) {
			return array();
		}

		$decoded = json_decode( $raw_meta, true );
		if ( null === $decoded || JSON_ERROR_NONE !== json_last_error() || ! is_array( $decoded ) ) {
			return array();
		}

		return $decoded;
	}

	/**
	 * Retrieve a view by slug.
	 *
	 * @param string $slug View slug.
	 * @return array<string,mixed>|null
	 */
	public function get_view_by_slug( string $slug ): ?array {
		$wpdb = $this->wpdb;
		$row  = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare( // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Dynamic filters built from sanitized values.
				'SELECT * FROM %i WHERE slug = %s',
				$this->tables['views'],
				$slug
			),
			ARRAY_A
		);

		return is_array( $row ) ? $row : null;
	}

	/**
	 * Retrieve the earliest view (default) when no slug provided.
	 *
	 * @return array<string,mixed>|null
	 */
	public function get_default_view(): ?array {
		$wpdb = $this->wpdb;
		$row  = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare( // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Placeholder list built from sanitized IDs.
				'SELECT * FROM %i ORDER BY created_at ASC LIMIT %d',
				$this->tables['views'],
				1
			),
			ARRAY_A
		);

		return is_array( $row ) ? $row : null;
	}

	/**
	 * Retrieve all board views for selectors.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public function get_views_list(): array {
		$cache_ttl = $this->get_cache_ttl( 'views' );
		$cache_key = 'views_list';
		$cache_hit = $cache_ttl > 0 ? wp_cache_get( $cache_key, self::CACHE_GROUP ) : false;

		if ( is_array( $cache_hit ) ) {
			return $cache_hit;
		}

		if ( empty( $this->tables['views'] ) ) {
			return array();
		}

		$wpdb = $this->wpdb;
		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare( // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Placeholder list built from sanitized IDs.
				'SELECT id, name, slug, type, allow_switcher, show_guardian, refresh_interval FROM %i ORDER BY name ASC',
				$this->tables['views']
			),
			ARRAY_A
		);

		if ( empty( $rows ) ) {
			return array();
		}

		$views = array();

		foreach ( $rows as $row ) {
			$slug = sanitize_key( (string) ( $row['slug'] ?? '' ) );
			if ( '' === $slug ) {
				continue;
			}

			$views[] = array(
				'id'               => (int) ( $row['id'] ?? 0 ),
				'slug'             => $slug,
				'name'             => (string) ( $row['name'] ?? $slug ),
				'type'             => (string) ( $row['type'] ?? 'internal' ),
				'allow_switcher'   => (bool) ( $row['allow_switcher'] ?? false ),
				'show_guardian'    => (bool) ( $row['show_guardian'] ?? true ),
				'refresh_interval' => (int) ( $row['refresh_interval'] ?? 0 ),
			);
		}

		if ( $cache_ttl > 0 ) {
			wp_cache_set( $cache_key, $views, self::CACHE_GROUP, $cache_ttl );
		}

		return $views;
	}

	/**
	 * Validate a public board token against the stored hash.
	 *
	 * @param array<string,mixed> $view   View row.
	 * @param string              $token  Provided token.
	 * @return bool
	 */
	public function verify_public_token( array $view, string $token ): bool {
		if ( '' === $token ) {
			return false;
		}

		if ( empty( $view['public_token_hash'] ) ) {
			return false;
		}

		return wp_check_password( $token, $view['public_token_hash'] );
	}

	/**
	 * Retrieve a single stage definition from the library.
	 *
	 * @param string $stage_key Stage key.
	 * @return array<string,mixed>|null
	 */
	public function get_stage_definition( string $stage_key ): ?array {
		$stage_key = sanitize_key( $stage_key );
		if ( '' === $stage_key ) {
			return null;
		}

		$library = $this->get_stage_library();

		return $library[ $stage_key ] ?? null;
	}

	/**
	 * Build the board payload for a view.
	 *
	 * @param array<string,mixed> $args Arguments (view, filters, masking).
	 * @return array<string,mixed>
	 */
	public function build_board_payload( array $args ): array {
		$view                  = $args['view'];
		$modified_after        = $args['modified_after'] ?? '';
		$stage_filter_list     = isset( $args['stages'] ) && is_array( $args['stages'] ) ? array_filter(
			array_unique(
				array_map(
					static function ( $stage ) {
						return sanitize_key( (string) $stage );
					},
					$args['stages']
				)
			)
		) : array();
		$mask_guardian         = ! empty( $args['mask_guardian'] );
		$mask_sensitive_fields = ! empty( $args['mask_sensitive'] );
		$is_read_only          = ! empty( $args['readonly'] );
		$is_public_request     = ! empty( $args['is_public'] );
		$view_id               = isset( $view['id'] ) ? (int) $view['id'] : 0;

		$cache_ttl        = $this->get_cache_ttl( 'board' );
		$latest_hint      = $view_id > 0 ? $this->get_latest_visit_updated_at( $view_id, $stage_filter_list ) : '';
		$latest_hint_time = $latest_hint ? strtotime( $latest_hint ) : null;
		$cache_key        = null;
		$should_cache     = '' === $modified_after && $cache_ttl > 0;

		if ( $should_cache ) {
			$cache_key = $this->get_board_cache_key(
				array(
					'view_id'        => $view_id,
					'stages'         => $stage_filter_list,
					'mask_guardian'  => $mask_guardian,
					'mask_sensitive' => $mask_sensitive_fields,
					'readonly'       => $is_read_only,
					'is_public'      => $is_public_request,
				),
				$latest_hint
			);

			$cached = wp_cache_get( $cache_key, self::CACHE_GROUP );
			if ( is_array( $cached ) && isset( $cached['payload'] ) ) {
				return $cached['payload'];
			}
		}

		$modified_after = is_string( $modified_after ) ? $modified_after : '';
		$visits         = $this->visit_repository->get_board_visits( $view_id, $stage_filter_list, $modified_after );

		$visit_ids = array_map(
			static function ( $row ) {
				return (int) ( $row['id'] ?? 0 );
			},
			$visits
		);

		$client_ids          = array_map(
			static function ( $row ) {
				return (int) ( $row['client_id'] ?? 0 );
			},
			$visits
		);
		$client_meta_map     = $this->get_clients_meta_map( $client_ids );
		$client_flag_ids     = $this->collect_client_flag_ids( $client_meta_map );
		$client_flags_lookup = $this->get_flags_by_ids( $client_flag_ids );

		$services_map   = $this->get_visit_services_map( $visit_ids );
		$flags_map      = $this->get_visit_flags_map( $visit_ids );
		$photos_map     = $this->get_visit_photos_map( $visit_ids );
		$history_totals = $this->get_stage_elapsed_totals( $visit_ids );

		$stage_buckets   = array();
		$latest_modified = null;

		foreach ( $visits as $visit ) {
			$stage_key = sanitize_key( (string) ( $visit['current_stage'] ?? '' ) );
			if ( '' === $stage_key ) {
				$stage_key = 'unassigned';
			}

			$visit_id = (int) ( $visit['id'] ?? 0 );
			$item     = $this->format_visit_payload(
				$visit,
				$services_map[ $visit_id ] ?? array(),
				$flags_map[ $visit_id ] ?? array(),
				$photos_map[ $visit_id ] ?? array(),
				array(
					'mask_guardian'        => $mask_guardian,
					'mask_sensitive'       => $mask_sensitive_fields,
					'hide_sensitive_flags' => $mask_sensitive_fields,
					'client_meta'          => $client_meta_map[ (int) ( $visit['client_id'] ?? 0 ) ] ?? array(),
					'flag_lookup'          => $client_flags_lookup,
				),
				array(),
				$history_totals
			);

			if ( ! isset( $stage_buckets[ $stage_key ] ) ) {
				$stage_buckets[ $stage_key ] = array();
			}

			$stage_buckets[ $stage_key ][] = $item;

			if ( ! empty( $visit['updated_at'] ) ) {
				$timestamp = strtotime( (string) $visit['updated_at'] );
				if ( false !== $timestamp ) {
					if ( null === $latest_modified || $timestamp > $latest_modified ) {
						$latest_modified = $timestamp;
					}
				}
			}
		}

		$stage_library = $this->get_stage_library();
		$stage_rows    = $this->get_view_stage_rows( $view_id );

		if ( empty( $stage_rows ) && ! empty( $stage_library ) ) {
			foreach ( $stage_library as $stage_key => $row ) {
				$stage_key = sanitize_key( (string) $stage_key );
				if ( '' === $stage_key ) {
					continue;
				}

				$stage_rows[] = array(
					'stage_key'              => $stage_key,
					'label'                  => (string) ( $row['label'] ?? $stage_key ),
					'sort_order'             => (int) ( $row['sort_order'] ?? 0 ),
					'capacity_soft_limit'    => (int) ( $row['capacity_soft_limit'] ?? 0 ),
					'capacity_hard_limit'    => (int) ( $row['capacity_hard_limit'] ?? 0 ),
					'timer_threshold_green'  => (int) ( $row['timer_threshold_green'] ?? 0 ),
					'timer_threshold_yellow' => (int) ( $row['timer_threshold_yellow'] ?? 0 ),
					'timer_threshold_red'    => (int) ( $row['timer_threshold_red'] ?? 0 ),
				);
			}
		}

		$stages_payload   = array();
		$known_stage_keys = array();

		foreach ( $stage_rows as $row ) {
			$stage_key                      = (string) $row['stage_key'];
			$known_stage_keys[ $stage_key ] = true;

			$visits_for_stage = $stage_buckets[ $stage_key ] ?? array();

			$soft_limit = (int) ( $row['capacity_soft_limit'] ?? 0 );
			$hard_limit = (int) ( $row['capacity_hard_limit'] ?? 0 );
			$count      = count( $visits_for_stage );

			$stages_payload[] = array(
				'key'              => $stage_key,
				'label'            => (string) ( $row['label'] ?? $stage_key ),
				'sort_order'       => (int) ( $row['sort_order'] ?? 0 ),
				'capacity'         => array(
					'soft'             => $soft_limit,
					'hard'             => $hard_limit,
					'is_soft_exceeded' => $soft_limit > 0 ? $count > $soft_limit : false,
					'is_hard_exceeded' => $hard_limit > 0 ? $count > $hard_limit : false,
					'available_soft'   => $soft_limit > 0 ? max( 0, $soft_limit - $count ) : null,
					'available_hard'   => $hard_limit > 0 ? max( 0, $hard_limit - $count ) : null,
				),
				'timer_thresholds' => $this->normalize_timer_thresholds(
					array(
						'green'  => (int) ( $row['timer_threshold_green'] ?? 0 ),
						'yellow' => (int) ( $row['timer_threshold_yellow'] ?? 0 ),
						'red'    => (int) ( $row['timer_threshold_red'] ?? 0 ),
					)
				),
				'visit_count'      => $count,
				'visits'           => $visits_for_stage,
			);
		}

		foreach ( $stage_buckets as $stage_key => $visits_for_stage ) {
			if ( isset( $known_stage_keys[ $stage_key ] ) ) {
				continue;
			}

			$row        = $stage_library[ $stage_key ] ?? array();
			$soft_limit = (int) ( $row['capacity_soft_limit'] ?? 0 );
			$hard_limit = (int) ( $row['capacity_hard_limit'] ?? 0 );
			$count      = count( $visits_for_stage );

			$stages_payload[] = array(
				'key'              => $stage_key,
				'label'            => (string) ( $row['label'] ?? ucwords( str_replace( array( '-', '_' ), ' ', $stage_key ) ) ),
				'sort_order'       => (int) ( $row['sort_order'] ?? PHP_INT_MAX ),
				'capacity'         => array(
					'soft'             => $soft_limit,
					'hard'             => $hard_limit,
					'is_soft_exceeded' => $soft_limit > 0 ? $count > $soft_limit : false,
					'is_hard_exceeded' => $hard_limit > 0 ? $count > $hard_limit : false,
					'available_soft'   => $soft_limit > 0 ? max( 0, $soft_limit - $count ) : null,
					'available_hard'   => $hard_limit > 0 ? max( 0, $hard_limit - $count ) : null,
				),
				'timer_thresholds' => $this->normalize_timer_thresholds(
					array(
						'green'  => (int) ( $row['timer_threshold_green'] ?? 0 ),
						'yellow' => (int) ( $row['timer_threshold_yellow'] ?? 0 ),
						'red'    => (int) ( $row['timer_threshold_red'] ?? 0 ),
					)
				),
				'visit_count'      => $count,
				'visits'           => $visits_for_stage,
			);
		}

		usort(
			$stages_payload,
			static function ( $a, $b ) {
				return ( $a['sort_order'] ?? 0 ) <=> ( $b['sort_order'] ?? 0 );
			}
		);

		if ( null === $latest_modified && null !== $latest_hint_time ) {
			$latest_modified = $latest_hint_time;
		}

		$latest_modified_value = $latest_modified ? gmdate( 'Y-m-d H:i:s', $latest_modified ) : current_time( 'mysql', true );

		$payload = array(
			'view'         => array(
				'id'               => $view_id,
				'slug'             => $view['slug'],
				'name'             => $view['name'],
				'type'             => $view['type'],
				'allow_switcher'   => (bool) $view['allow_switcher'],
				'refresh_interval' => (int) $view['refresh_interval'],
				'show_guardian'    => (bool) $view['show_guardian'],
			),
			'stages'       => array_values( $stages_payload ),
			'last_updated' => $latest_modified_value,
			'visibility'   => array(
				'mask_guardian'  => $mask_guardian,
				'mask_sensitive' => $mask_sensitive_fields,
			),
			'readonly'     => $is_read_only,
			'is_public'    => $is_public_request,
		);

		if ( $should_cache && $cache_key ) {
			wp_cache_set(
				$cache_key,
				array(
					'payload' => $payload,
				),
				self::CACHE_GROUP,
				$cache_ttl
			);
			$this->track_board_cache_key( $view_id, $cache_key );
		}

		return $payload;
	}

	/**
	 * Retrieve visit payload by ID.
	 *
	 * @param int                $visit_id Visit ID.
	 * @param array<string,bool> $options  Payload options.
	 * @return array<string,mixed>|null
	 */
	public function get_visit( int $visit_id, array $options = array() ): ?array {
		$row = $this->get_visit_row( $visit_id );
		if ( null === $row ) {
			return null;
		}

		$services = $this->get_visit_services_map( array( $visit_id ) );
		$flags    = $this->get_visit_flags_map( array( $visit_id ) );
		$photos   = $this->get_visit_photos_map( array( $visit_id ) );
		$totals   = $this->get_stage_elapsed_totals( array( $visit_id ) );
		$options  = is_array( $options ) ? $options : array();

		$include_history  = ! empty( $options['include_history'] );
		$include_previous = array_key_exists( 'include_previous', $options ) ? (bool) $options['include_previous'] : true;
		$history_limit    = isset( $options['history_limit'] ) ? (int) $options['history_limit'] : 50;
		$history          = array();
		$previous_visits  = array();

		if ( $include_history ) {
			$history = $this->get_stage_history( $visit_id, $history_limit );
		}

		if ( $include_previous ) {
			$previous_visits = $this->get_previous_visits_for_client(
				(int) ( $row['client_id'] ?? 0 ),
				$visit_id,
				! empty( $options['mask_sensitive'] )
			);
		}

		$client_meta_map            = $this->get_clients_meta_map( array( (int) ( $row['client_id'] ?? 0 ) ) );
		$client_meta                = $client_meta_map[ (int) ( $row['client_id'] ?? 0 ) ] ?? array();
		$client_flags               = array_map( 'intval', $client_meta[ self::CLIENT_META_FLAGS ] ?? array() );
		$flag_lookup                = $this->get_flags_by_ids( $client_flags );
		$options['include_history'] = $include_history;

		return $this->format_visit_payload(
			$row,
			$services[ $visit_id ] ?? array(),
			$flags[ $visit_id ] ?? array(),
			$photos[ $visit_id ] ?? array(),
			$options,
			$history,
			$totals,
			$previous_visits,
			$client_meta,
			$flag_lookup
		);
	}

	/**
	 * Retrieve stage history entries for a visit.
	 *
	 * @param int $visit_id Visit ID.
	 * @param int $limit    Maximum number of entries to return.
	 * @return array<int,array<string,mixed>>
	 */
	public function get_stage_history( int $visit_id, int $limit = 50 ): array {
		$visit_id = (int) $visit_id;
		if ( $visit_id <= 0 ) {
			return array();
		}

		$limit = max( 1, min( (int) $limit, 100 ) );

		$wpdb = $this->wpdb;
		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare( // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Placeholder list built from sanitized IDs.
				'SELECT id, visit_id, from_stage, to_stage, comment, changed_by, changed_at, elapsed_seconds
				FROM %i
				WHERE visit_id = %d
				ORDER BY changed_at DESC
				LIMIT %d',
				$this->tables['stage_history'],
				$visit_id,
				$limit
			),
			ARRAY_A
		);
		if ( empty( $rows ) ) {
			return array();
		}

		$library = $this->get_stage_library();

		return array_map(
			function ( $row ) use ( $library ) {
				return $this->format_stage_history_row( $row, $library );
			},
			$rows
		);
	}

	/**
	 * Retrieve previous visits for the same client.
	 *
	 * @param int  $client_id      Client ID.
	 * @param int  $current_visit  Current visit ID to exclude.
	 * @param bool $mask_sensitive Whether to hide notes/instructions.
	 * @param int  $limit          Number of entries to return.
	 * @return array<int,array<string,mixed>>
	 */
	private function get_previous_visits_for_client( int $client_id, int $current_visit, bool $mask_sensitive = false, int $limit = 5 ): array {
		$client_id     = (int) $client_id;
		$current_visit = (int) $current_visit;

		if ( $client_id <= 0 ) {
			return array();
		}

		$limit = max( 1, min( $limit, 10 ) );

		$wpdb = $this->wpdb;
		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare( // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Placeholder list built from sanitized IDs.
				'SELECT id, current_stage, status, check_in_at, check_out_at, instructions, public_notes, private_notes, created_at
				FROM %i
				WHERE client_id = %d AND id <> %d
				ORDER BY check_in_at DESC, created_at DESC
				LIMIT %d',
				$this->tables['visits'],
				$client_id,
				$current_visit,
				$limit
			),
			ARRAY_A
		);

		$previous = array();
		foreach ( $rows as $row ) {
			$instructions  = (string) ( $row['instructions'] ?? '' );
			$public_notes  = (string) ( $row['public_notes'] ?? '' );
			$private_notes = (string) ( $row['private_notes'] ?? '' );

			if ( $mask_sensitive ) {
				$instructions  = '';
				$public_notes  = '';
				$private_notes = '';
			}

			$previous[] = array(
				'id'            => (int) ( $row['id'] ?? 0 ),
				'stage'         => (string) ( $row['current_stage'] ?? '' ),
				'status'        => (string) ( $row['status'] ?? '' ),
				'check_in_at'   => $this->maybe_rfc3339( $row['check_in_at'] ?? null ),
				'check_out_at'  => $this->maybe_rfc3339( $row['check_out_at'] ?? null ),
				'instructions'  => $instructions,
				'public_notes'  => $public_notes,
				'private_notes' => $private_notes,
			);
		}

		return $previous;
	}

	/**
	 * Fetch paginated visit history for a client.
	 *
	 * @param int $client_id     Client ID.
	 * @param int $exclude_visit Visit ID to exclude (typically the active one).
	 * @param int $page          Page number.
	 * @param int $per_page      Items per page.
	 * @return array<string,mixed>
	 */
	public function get_client_history( int $client_id, int $exclude_visit = 0, int $page = 1, int $per_page = 5 ): array {
		$client_id     = (int) $client_id;
		$exclude_visit = (int) $exclude_visit;
		$pagination    = Query_Helpers::normalize_pagination( (int) $page, (int) $per_page, 20 );
		$page          = $pagination['page'];
		$per_page      = $pagination['per_page'];
		$offset        = $pagination['offset'];
		$limit_plus    = $per_page + 1; // fetch one extra to signal has_more.

		if ( $client_id <= 0 ) {
			return array(
				'items'    => array(),
				'page'     => $page,
				'per_page' => $per_page,
				'has_more' => false,
			);
		}

		$rows     = $this->fetch_client_history_rows( $client_id, $exclude_visit, $limit_plus, $offset );
		$has_more = count( $rows ) > $per_page;
		if ( $has_more ) {
			$rows = array_slice( $rows, 0, $per_page );
		}

		$items = $this->format_client_history_items( $rows );

		return array(
			'items'    => $items,
			'page'     => $page,
			'per_page' => $per_page,
			'has_more' => $has_more,
		);
	}

	/**
	 * Fetch raw visit history rows for a client.
	 *
	 * @param int $client_id Client ID.
	 * @param int $exclude_visit Visit ID to exclude.
	 * @param int $limit Max rows to return.
	 * @param int $offset Offset for pagination.
	 * @return array<int,array<string,mixed>>
	 */
	private function fetch_client_history_rows( int $client_id, int $exclude_visit, int $limit, int $offset ): array {
		$wpdb = $this->wpdb;

		return $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				'SELECT id, current_stage, status, check_in_at, check_out_at, instructions, public_notes, private_notes, created_at
				FROM %i
				WHERE client_id = %d AND id <> %d
				ORDER BY check_in_at DESC, created_at DESC
				LIMIT %d OFFSET %d',
				$this->tables['visits'],
				$client_id,
				$exclude_visit,
				$limit,
				$offset
			),
			ARRAY_A
		);
	}

	/**
	 * Format visit history rows for the client history endpoint.
	 *
	 * @param array<int,array<string,mixed>> $rows History rows.
	 * @return array<int,array<string,mixed>>
	 */
	private function format_client_history_items( array $rows ): array {
		if ( empty( $rows ) ) {
			return array();
		}

		$visit_ids    = array_map(
			static function ( $row ) {
				return (int) ( $row['id'] ?? 0 );
			},
			$rows
		);
		$services_map = $this->get_visit_services_map( $visit_ids );
		$photos_map   = $this->get_visit_photos_map( $visit_ids );
		$flags_map    = $this->get_visit_flags_map( $visit_ids );
		$library      = $this->get_stage_library();

		$items = array();
		foreach ( $rows as $row ) {
			$visit_id   = (int) ( $row['id'] ?? 0 );
			$stage_key  = sanitize_key( (string) ( $row['current_stage'] ?? '' ) );
			$stage_data = $library[ $stage_key ] ?? null;
			$items[]    = array(
				'id'            => $visit_id,
				'stage'         => $stage_key,
				'stage_label'   => $stage_data['label'] ?? $stage_key,
				'status'        => (string) ( $row['status'] ?? '' ),
				'check_in_at'   => $this->maybe_rfc3339( $row['check_in_at'] ?? null ),
				'check_out_at'  => $this->maybe_rfc3339( $row['check_out_at'] ?? null ),
				'instructions'  => (string) ( $row['instructions'] ?? '' ),
				'public_notes'  => (string) ( $row['public_notes'] ?? '' ),
				'private_notes' => (string) ( $row['private_notes'] ?? '' ),
				'services'      => $services_map[ $visit_id ] ?? array(),
				'flags'         => $flags_map[ $visit_id ] ?? array(),
				'photos'        => $photos_map[ $visit_id ] ?? array(),
				'created_at'    => $this->maybe_rfc3339( $row['created_at'] ?? null ),
			);
		}

		return $items;
	}

	/**
	 * Persist a new visit record and return the payload.
	 *
	 * @param array<string,mixed> $visit     Visit DB columns.
	 * @param array<int>          $services  Service IDs.
	 * @param array<int>          $flags     Flag IDs.
	 * @return array<string,mixed>|WP_Error
	 */
	public function create_visit( array $visit, array $services, array $flags ) {
		$now = $this->plugin->now();

		$visit['created_at'] = $this->normalize_timestamp_value( $visit['created_at'] ?? null, $now );

		$visit['updated_at'] = $this->normalize_timestamp_value( $visit['updated_at'] ?? null, $visit['created_at'] );

		$timer_fallback = $visit['check_in_at'] ?? $visit['created_at'];

		$visit['timer_started_at'] = $this->normalize_timestamp_value( $visit['timer_started_at'] ?? null, $timer_fallback );
		if ( isset( $visit['timer_elapsed_seconds'] ) ) {
			$visit['timer_elapsed_seconds'] = max( 0, (int) $visit['timer_elapsed_seconds'] );
		} else {
			$visit['timer_elapsed_seconds'] = 0;
		}

		$result = $this->wpdb->insert(
			$this->tables['visits'],
			$visit,
			array(
				'%d',
				'%d',
				'%d',
				'%s',
				'%s',
				'%s',
				'%s',
				'%d',
				'%s',
				'%s',
				'%s',
				'%s',
				'%d',
				'%s',
				'%s',
			)
		);

		if ( false === $result ) {
			return new WP_Error(
				'bbgf_visit_create_failed',
				__( 'Unable to create visit.', 'bb-groomflow' ),
				array( 'status' => 500 )
			);
		}

		$visit_id = (int) $this->wpdb->insert_id;

		$this->sync_visit_services( $visit_id, $services );
		$this->sync_visit_flags( $visit_id, $flags );

		$payload = $this->get_visit( $visit_id );
		if ( null === $payload ) {
			return new WP_Error(
				'bbgf_visit_create_fetch_failed',
				__( 'Visit created but could not be loaded.', 'bb-groomflow' ),
				array( 'status' => 500 )
			);
		}

		$view_id = isset( $visit['view_id'] ) ? (int) $visit['view_id'] : 0;
		$this->flush_cache( $view_id > 0 ? $view_id : null );

		return $payload;
	}

	/**
	 * Update a visit and return the payload.
	 *
	 * @param int                      $visit_id Visit ID.
	 * @param array<string,mixed>      $visit    Visit columns.
	 * @param array<int>|null          $services Services to sync (null to keep existing).
	 * @param array<int>|null          $flags    Flags to sync (null to keep existing).
	 * @param array<string,mixed>|null $existing_row Existing visit row (optional) for cache invalidation.
	 * @param int                      $user_id Acting user ID for attribution.
	 * @param string                   $comment Optional comment for history.
	 * @return array<string,mixed>|WP_Error
	 */
	public function update_visit( int $visit_id, array $visit, ?array $services, ?array $flags, ?array $existing_row = null, int $user_id = 0, string $comment = '' ) {
		$existing_row        = $existing_row ?? $this->get_visit_row( $visit_id );
		$previous_view_id    = isset( $existing_row['view_id'] ) ? (int) $existing_row['view_id'] : null;
		$visit['updated_at'] = $this->plugin->now();

		$result = $this->wpdb->update(
			$this->tables['visits'],
			$visit,
			array( 'id' => $visit_id ),
			array(
				'%d',
				'%d',
				'%d',
				'%s',
				'%s',
				'%s',
				'%s',
				'%d',
				'%s',
				'%s',
				'%s',
				'%d',
				'%s',
			),
			array( '%d' )
		);

		if ( false === $result ) {
			return new WP_Error(
				'bbgf_visit_update_failed',
				__( 'Unable to update visit.', 'bb-groomflow' ),
				array( 'status' => 500 )
			);
		}

		if ( is_array( $services ) ) {
			$this->sync_visit_services( $visit_id, $services );
		}

		if ( is_array( $flags ) ) {
			$this->sync_visit_flags( $visit_id, $flags );
		}

		$payload = $this->get_visit( $visit_id );
		if ( null === $payload ) {
			return new WP_Error(
				'bbgf_visit_update_fetch_failed',
				__( 'Visit updated but could not be loaded.', 'bb-groomflow' ),
				array( 'status' => 500 )
			);
		}

		$new_view_id = isset( $visit['view_id'] ) ? (int) $visit['view_id'] : $previous_view_id;

		$view_ids = array_filter( array_unique( array_map( 'intval', array( $previous_view_id, $new_view_id ) ) ) );
		if ( empty( $view_ids ) ) {
			$this->flush_cache();
		} else {
			$this->flush_cache_for_views( $view_ids );
		}

		if ( $user_id > 0 ) {
			$history_comment = '' !== $comment ? sanitize_textarea_field( $comment ) : __( 'Visit updated', 'bb-groomflow' );
			$from_stage      = sanitize_key( (string) ( $existing_row['current_stage'] ?? $visit['current_stage'] ?? 'active' ) );
			$from_stage      = '' !== $from_stage ? $from_stage : 'active';
			$this->log_stage_history( $visit_id, $from_stage, 'updated', $history_comment, $user_id, 0 );
		}

		return $payload;
	}

	/**
	 * Move a visit to another stage.
	 *
	 * @param int    $visit_id    Visit ID.
	 * @param string $destination Destination stage key.
	 * @param string $comment     Stage change comment.
	 * @param int    $user_id     Acting user.
	 * @return array<string,mixed>|WP_Error
	 */
	public function move_visit( int $visit_id, string $destination, string $comment, int $user_id ) {
		$visit_row = $this->get_visit_row( $visit_id );
		if ( null === $visit_row ) {
			return new WP_Error(
				'bbgf_visit_not_found',
				__( 'Visit not found.', 'bb-groomflow' ),
				array( 'status' => 404 )
			);
		}

		$stage_library = $this->get_stage_library();
		if ( ! isset( $stage_library[ $destination ] ) ) {
			return new WP_Error(
				'bbgf_visit_unknown_stage',
				__( 'Destination stage is not recognised.', 'bb-groomflow' ),
				array( 'status' => 400 )
			);
		}

		$current_stage = (string) ( $visit_row['current_stage'] ?? '' );
		if ( $current_stage === $destination ) {
			$payload = $this->get_visit(
				$visit_id,
				array(
					'include_history' => true,
				)
			);

			if ( null === $payload ) {
				return new WP_Error(
					'bbgf_visit_not_found',
					__( 'Visit not found.', 'bb-groomflow' ),
					array( 'status' => 404 )
				);
			}

			return array(
				'visit'         => $payload,
				'history_entry' => null,
			);
		}

		$now_gmt          = $this->plugin->now();
		$history_totals   = $this->get_stage_elapsed_totals( array( $visit_id ) );
		$destination_base = $history_totals[ $visit_id ][ $destination ] ?? 0;

		$current_started = isset( $visit_row['timer_started_at'] ) ? strtotime( (string) $visit_row['timer_started_at'] ) : false;
		$current_base    = isset( $visit_row['timer_elapsed_seconds'] ) ? (int) $visit_row['timer_elapsed_seconds'] : 0;
		$stint_seconds   = 0;

		if ( false !== $current_started && $current_started > 0 ) {
			$stint_seconds = max( 0, time() - $current_started );
		}

		$result = $this->wpdb->update(
			$this->tables['visits'],
			array(
				'current_stage'         => $destination,
				'timer_started_at'      => $now_gmt,
				'timer_elapsed_seconds' => $destination_base,
				'updated_at'            => $now_gmt,
			),
			array( 'id' => $visit_id ),
			array( '%s', '%s', '%d', '%s' ),
			array( '%d' )
		);

		if ( false === $result ) {
			return new WP_Error(
				'bbgf_visit_move_failed',
				__( 'Unable to move visit to the requested stage.', 'bb-groomflow' ),
				array( 'status' => 500 )
			);
		}

		$history_id = $this->log_stage_history(
			$visit_id,
			$current_stage,
			$destination,
			$comment,
			$user_id,
			$stint_seconds
		);

		$history_entry = null;
		if ( $history_id ) {
			$history_entry = $this->get_stage_history_entry( $history_id );
		}

		$updated = $this->get_visit(
			$visit_id,
			array(
				'include_history' => true,
			)
		);
		if ( null === $updated ) {
			return new WP_Error(
				'bbgf_visit_move_fetch_failed',
				__( 'Visit moved but could not be loaded.', 'bb-groomflow' ),
				array( 'status' => 500 )
			);
		}

		do_action(
			'bbgf_visit_stage_changed',
			$updated,
			$current_stage,
			$destination,
			array(
				'comment'   => $comment,
				'user_id'   => $user_id,
				'timestamp' => $now_gmt,
			)
		);

		$this->flush_cache( isset( $visit_row['view_id'] ) ? (int) $visit_row['view_id'] : null );

		return array(
			'visit'         => $updated,
			'history_entry' => $history_entry,
		);
	}

	/**
	 * Mark a visit as checked out and close the lifecycle.
	 *
	 * @param int    $visit_id Visit ID.
	 * @param int    $user_id  Acting user ID.
	 * @param string $comment  Optional checkout comment.
	 * @return array<string,mixed>|WP_Error
	 */
	public function checkout_visit( int $visit_id, int $user_id, string $comment = '' ) {
		$visit_row = $this->get_visit_row( $visit_id );
		if ( null === $visit_row ) {
			return new WP_Error(
				'bbgf_visit_not_found',
				__( 'Visit not found.', 'bb-groomflow' ),
				array( 'status' => 404 )
			);
		}

		if ( ! empty( $visit_row['check_out_at'] ) ) {
			return new WP_Error(
				'bbgf_visit_already_checked_out',
				__( 'This visit has already been checked out.', 'bb-groomflow' ),
				array( 'status' => 409 )
			);
		}

		$stage_key        = sanitize_key( (string) ( $visit_row['current_stage'] ?? '' ) );
		$history_totals   = $this->get_stage_elapsed_totals( array( $visit_id ) );
		$stage_total      = $history_totals[ $visit_id ][ $stage_key ] ?? 0;
		$current_base     = isset( $visit_row['timer_elapsed_seconds'] ) ? (int) $visit_row['timer_elapsed_seconds'] : 0;
		$started_at       = isset( $visit_row['timer_started_at'] ) ? strtotime( (string) $visit_row['timer_started_at'] ) : false;
		$stint_elapsed    = ( false !== $started_at && $started_at > 0 ) ? max( 0, time() - $started_at ) : 0;
		$elapsed_total    = max( $stage_total + $stint_elapsed, $current_base );
		$checkout_comment = '' === $comment ? __( 'Checked out', 'bb-groomflow' ) : sanitize_textarea_field( $comment );
		$now_gmt          = $this->plugin->now();

		$wpdb   = $this->wpdb;
		$result = $wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				'UPDATE %i
				SET status = %s, check_out_at = %s, timer_elapsed_seconds = %d, timer_started_at = NULL, updated_at = %s
				WHERE id = %d',
				$this->tables['visits'],
				'completed',
				$now_gmt,
				$elapsed_total,
				$now_gmt,
				$visit_id
			)
		);

		if ( false === $result ) {
			return new WP_Error(
				'bbgf_visit_checkout_failed',
				__( 'Unable to check out the visit.', 'bb-groomflow' ),
				array( 'status' => 500 )
			);
		}

		$history_elapsed = max( 0, $elapsed_total - $stage_total );
		$history_id      = $this->log_stage_history(
			$visit_id,
			'' !== $stage_key ? $stage_key : 'active',
			'checked_out',
			$checkout_comment,
			$user_id,
			$history_elapsed
		);

		$updated = $this->get_visit(
			$visit_id,
			array(
				'include_history' => true,
			)
		);

		if ( null === $updated ) {
			return new WP_Error(
				'bbgf_visit_checkout_fetch_failed',
				__( 'Visit checked out but could not be loaded.', 'bb-groomflow' ),
				array( 'status' => 500 )
			);
		}

		$history_entry = null;
		if ( $history_id ) {
			$history_entry = $this->get_stage_history_entry( $history_id );
		}

		$this->flush_cache( isset( $visit_row['view_id'] ) ? (int) $visit_row['view_id'] : null );

		return array(
			'visit'         => $updated,
			'history_entry' => $history_entry,
		);
	}

	/**
	 * Link an existing attachment to a visit.
	 *
	 * @param int  $visit_id              Visit ID.
	 * @param int  $attachment_id         Attachment ID.
	 * @param bool $visible_to_guardian   Whether guardian can view the photo.
	 * @return array<string,mixed>|WP_Error
	 */
	public function attach_existing_photo( int $visit_id, int $attachment_id, bool $visible_to_guardian = true ) {
		$visit = $this->get_visit_row( $visit_id );
		if ( null === $visit ) {
			return new WP_Error(
				'bbgf_visit_not_found',
				__( 'Visit not found.', 'bb-groomflow' ),
				array( 'status' => 404 )
			);
		}

		$attachment = get_post( $attachment_id );
		if ( ! $attachment || 'attachment' !== $attachment->post_type ) {
			return new WP_Error(
				'bbgf_visit_photo_invalid',
				__( 'Attachment not found.', 'bb-groomflow' ),
				array( 'status' => 404 )
			);
		}

		$existing_visit = (int) get_post_meta( $attachment_id, self::VISIT_PHOTO_META_KEY, true );
		if ( $existing_visit && $existing_visit !== $visit_id ) {
			return new WP_Error(
				'bbgf_visit_photo_in_use',
				__( 'This attachment is already linked to another visit.', 'bb-groomflow' ),
				array( 'status' => 409 )
			);
		}

		update_post_meta( $attachment_id, self::VISIT_PHOTO_META_KEY, $visit_id );
		update_post_meta( $attachment_id, self::VISIT_PHOTO_GUARDIAN_META_KEY, $visible_to_guardian ? 1 : 0 );
		if ( ! $this->has_primary_photo( $visit_id ) ) {
			update_post_meta( $attachment_id, self::VISIT_PHOTO_PRIMARY_META_KEY, 1 );
		}

		$photo = $this->format_photo_payload( $attachment_id );
		if ( null === $photo ) {
			return new WP_Error(
				'bbgf_visit_photo_failed',
				__( 'Unable to prepare photo data.', 'bb-groomflow' ),
				array( 'status' => 500 )
			);
		}

		do_action( 'bbgf_visit_photo_added', $visit_id, $attachment_id, $photo );

		$this->set_client_main_photo( (int) ( $visit['client_id'] ?? 0 ), $attachment_id );
		$payload = $this->get_visit( $visit_id );
		if ( null === $payload ) {
			return new WP_Error(
				'bbgf_visit_photo_fetch_failed',
				__( 'Visit not found after attaching photo.', 'bb-groomflow' ),
				array( 'status' => 500 )
			);
		}
		$this->flush_cache();

		return array(
			'photo' => $photo,
			'visit' => $payload,
		);
	}

	/**
	 * Fetch decoded client meta for updates.
	 *
	 * @param int $client_id Client ID.
	 * @return array<string,mixed>
	 */
	private function get_client_meta_raw( int $client_id ): array {
		if ( $client_id <= 0 ) {
			return array();
		}

		$meta_map = $this->get_clients_meta_map( array( $client_id ) );
		$meta     = $meta_map[ $client_id ]['raw'] ?? array();

		return is_array( $meta ) ? $meta : array();
	}

	/**
	 * Persist client meta updates.
	 *
	 * @param int                 $client_id Client ID.
	 * @param array<string,mixed> $meta Meta payload.
	 * @return void
	 */
	private function persist_client_meta( int $client_id, array $meta ): void {
		if ( $client_id <= 0 ) {
			return;
		}

		$this->wpdb->update(
			$this->tables['clients'],
			array(
				'meta'       => wp_json_encode( $meta ),
				'updated_at' => $this->plugin->now(),
			),
			array( 'id' => $client_id ),
			array( '%s', '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Persist the client's main photo reference.
	 *
	 * @param int $client_id     Client ID.
	 * @param int $attachment_id Attachment/Photo ID.
	 * @return void
	 */
	private function set_client_main_photo( int $client_id, int $attachment_id ): void {
		if ( $client_id <= 0 || $attachment_id <= 0 ) {
			return;
		}

		$meta                                 = $this->get_client_meta_raw( $client_id );
		$meta[ self::CLIENT_META_MAIN_PHOTO ] = $attachment_id;

		$this->persist_client_meta( $client_id, $meta );
	}

	/**
	 * Upload a new photo file and link it to a visit.
	 *
	 * @param int                 $visit_id Visit ID.
	 * @param array<string,mixed> $file     File array.
	 * @param bool                $visible_to_guardian Whether guardian can view the photo.
	 * @return array<string,mixed>|WP_Error
	 */
	public function attach_uploaded_photo( int $visit_id, array $file, bool $visible_to_guardian = true ) {
		$attachment_id = $this->handle_photo_upload( $file );
		if ( is_wp_error( $attachment_id ) ) {
			return $attachment_id;
		}

		return $this->attach_existing_photo( $visit_id, $attachment_id, $visible_to_guardian );
	}

	/**
	 * Handle a raw photo upload and return the new attachment ID.
	 *
	 * @param array<string,mixed> $file File array from the REST request.
	 * @return int|WP_Error Attachment ID or error.
	 */
	private function handle_photo_upload( array $file ) {
		if ( empty( $file['name'] ) ) {
			return new WP_Error(
				'bbgf_visit_photo_missing',
				__( 'Provide an attachment_id or upload a file.', 'bb-groomflow' ),
				array( 'status' => 400 )
			);
		}

		$error_code = (int) ( $file['error'] ?? UPLOAD_ERR_OK );
		if ( UPLOAD_ERR_OK !== $error_code ) {
			return new WP_Error(
				'bbgf_visit_photo_upload_failed',
				__( 'Unable to upload the photo.', 'bb-groomflow' ),
				array(
					'status'       => 400,
					'upload_error' => $error_code,
				)
			);
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$upload = wp_handle_upload(
			$file,
			array(
				'test_form' => false,
			)
		);

		if ( ! is_array( $upload ) || isset( $upload['error'] ) ) {
			$message = is_array( $upload ) && ! empty( $upload['error'] ) ? (string) $upload['error'] : __( 'Upload failed.', 'bb-groomflow' );

			return new WP_Error(
				'bbgf_visit_photo_upload_failed',
				$message,
				array( 'status' => 500 )
			);
		}

		$uploaded_file = (string) ( $upload['file'] ?? '' );
		if ( '' === $uploaded_file ) {
			return new WP_Error(
				'bbgf_visit_photo_upload_failed',
				__( 'Unable to process the uploaded photo.', 'bb-groomflow' ),
				array( 'status' => 500 )
			);
		}

		$max_dimension = 1800;
		$image_editor  = wp_get_image_editor( $uploaded_file );
		if ( ! is_wp_error( $image_editor ) ) {
			$size = $image_editor->get_size();
			if ( is_array( $size ) && ( (int) $size['width'] > $max_dimension || (int) $size['height'] > $max_dimension ) ) {
				$image_editor->set_quality( 82 );
				$image_editor->resize( $max_dimension, $max_dimension, false );
				$saved = $image_editor->save( $uploaded_file );
				if ( is_wp_error( $saved ) ) {
					return new WP_Error(
						'bbgf_visit_photo_upload_failed',
						__( 'Unable to normalise the uploaded photo.', 'bb-groomflow' ),
						array( 'status' => 500 )
					);
				}

				if ( ! empty( $saved['mime-type'] ) ) {
					$upload['type'] = $saved['mime-type'];
				}
			}
		}

		$attachment = array(
			'post_mime_type' => (string) ( $upload['type'] ?? '' ),
			'post_title'     => sanitize_file_name( pathinfo( (string) $file['name'], PATHINFO_FILENAME ) ),
			'post_content'   => '',
			'post_status'    => 'inherit',
		);

		$attachment_id = wp_insert_attachment( $attachment, $uploaded_file );
		if ( ! $attachment_id || is_wp_error( $attachment_id ) ) {
			return new WP_Error(
				'bbgf_visit_photo_upload_failed',
				__( 'Unable to save the uploaded photo.', 'bb-groomflow' ),
				array( 'status' => 500 )
			);
		}

		$metadata = wp_generate_attachment_metadata( $attachment_id, $uploaded_file );
		if ( is_wp_error( $metadata ) ) {
			return new WP_Error(
				'bbgf_visit_photo_upload_failed',
				__( 'Unable to generate photo metadata.', 'bb-groomflow' ),
				array( 'status' => 500 )
			);
		}

		wp_update_attachment_metadata( $attachment_id, $metadata );

		return (int) $attachment_id;
	}

	/**
	 * Update guardian visibility for a visit photo.
	 *
	 * @param int  $visit_id    Visit ID.
	 * @param int  $attachment_id Attachment/Photo ID.
	 * @param bool $visible     Whether the guardian can view the photo.
	 * @return array<string,mixed>|WP_Error
	 */
	public function update_photo_visibility( int $visit_id, int $attachment_id, bool $visible ) {
		$visit = $this->get_visit_row( $visit_id );
		if ( null === $visit ) {
			return new WP_Error(
				'bbgf_visit_not_found',
				__( 'Visit not found.', 'bb-groomflow' ),
				array( 'status' => 404 )
			);
		}

		$linked_visit = (int) get_post_meta( $attachment_id, self::VISIT_PHOTO_META_KEY, true );
		if ( $linked_visit !== $visit_id ) {
			return new WP_Error(
				'bbgf_visit_photo_not_linked',
				__( 'This photo is not linked to the selected visit.', 'bb-groomflow' ),
				array( 'status' => 404 )
			);
		}

		update_post_meta( $attachment_id, self::VISIT_PHOTO_GUARDIAN_META_KEY, $visible ? 1 : 0 );

		$photo   = $this->format_photo_payload( $attachment_id );
		$payload = $this->get_visit( $visit_id );

		if ( null === $photo || null === $payload ) {
			return new WP_Error(
				'bbgf_visit_photo_update_failed',
				__( 'Unable to refresh photo after updating visibility.', 'bb-groomflow' ),
				array( 'status' => 500 )
			);
		}

		$this->flush_cache( isset( $visit['view_id'] ) ? (int) $visit['view_id'] : null );

		return array(
			'photo' => $photo,
			'visit' => $payload,
		);
	}

	/**
	 * Set a primary photo for a visit, clearing previous primaries.
	 *
	 * @param int $visit_id       Visit ID.
	 * @param int $attachment_id  Attachment/Photo ID.
	 * @return array<string,mixed>|WP_Error
	 */
	public function set_primary_photo( int $visit_id, int $attachment_id ) {
		$visit = $this->get_visit_row( $visit_id );
		if ( null === $visit ) {
			return new WP_Error(
				'bbgf_visit_not_found',
				__( 'Visit not found.', 'bb-groomflow' ),
				array( 'status' => 404 )
			);
		}

		$linked_visit = (int) get_post_meta( $attachment_id, self::VISIT_PHOTO_META_KEY, true );
		if ( $linked_visit !== $visit_id ) {
			return new WP_Error(
				'bbgf_visit_photo_not_linked',
				__( 'This photo is not linked to the selected visit.', 'bb-groomflow' ),
				array( 'status' => 404 )
			);
		}

		$this->clear_primary_photo( $visit_id );
		update_post_meta( $attachment_id, self::VISIT_PHOTO_PRIMARY_META_KEY, 1 );
		$this->set_client_main_photo( (int) ( $visit['client_id'] ?? 0 ), $attachment_id );

		$photo   = $this->format_photo_payload( $attachment_id );
		$payload = $this->get_visit( $visit_id );

		if ( null === $photo || null === $payload ) {
			return new WP_Error(
				'bbgf_visit_photo_update_failed',
				__( 'Unable to refresh photo after updating primary.', 'bb-groomflow' ),
				array( 'status' => 500 )
			);
		}

		$this->flush_cache();

		return array(
			'photo' => $photo,
			'visit' => $payload,
		);
	}

	/**
	 * Determine if a visit already has a primary photo.
	 *
	 * @param int $visit_id Visit ID.
	 * @return bool
	 */
	private function has_primary_photo( int $visit_id ): bool {
		$query = get_posts(
			array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'posts_per_page' => 1,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Narrow lookup scoped to visit attachments.
				'meta_query'     => array(
					'relation' => 'AND',
					array(
						'key'     => self::VISIT_PHOTO_META_KEY,
						'value'   => $visit_id,
						'compare' => '=',
						'type'    => 'NUMERIC',
					),
					array(
						'key'     => self::VISIT_PHOTO_PRIMARY_META_KEY,
						'value'   => 1,
						'compare' => '=',
						'type'    => 'NUMERIC',
					),
				),
				'fields'         => 'ids',
				'no_found_rows'  => true,
			)
		);

		return ! empty( $query );
	}

	/**
	 * Clear the primary flag for all photos on a visit.
	 *
	 * @param int $visit_id Visit ID.
	 * @return void
	 */
	private function clear_primary_photo( int $visit_id ): void {
		$query = get_posts(
			array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'posts_per_page' => -1,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Narrow lookup scoped to visit attachments.
				'meta_query'     => array(
					array(
						'key'     => self::VISIT_PHOTO_META_KEY,
						'value'   => $visit_id,
						'compare' => '=',
						'type'    => 'NUMERIC',
					),
				),
				'fields'         => 'ids',
				'no_found_rows'  => true,
			)
		);

		foreach ( $query as $attachment_id ) {
			update_post_meta( (int) $attachment_id, self::VISIT_PHOTO_PRIMARY_META_KEY, 0 );
		}
	}

	/**
	 * Create or update a lightweight client record.
	 *
	 * @param array $payload Client payload.
	 * @return array{client_id:int,guardian_id?:int}|WP_Error
	 */
	public function create_or_update_client( array $payload ) {
		$name = trim( (string) ( $payload['name'] ?? '' ) );
		if ( '' === $name ) {
			return new WP_Error(
				'bbgf_visit_client_missing_name',
				__( 'Client name is required.', 'bb-groomflow' ),
				array( 'status' => 400 )
			);
		}

		$guardian_id = isset( $payload['guardian_id'] ) ? (int) $payload['guardian_id'] : 0;

		$data    = array(
			'name'        => sanitize_text_field( $name ),
			'guardian_id' => $guardian_id > 0 ? $guardian_id : null,
			'breed'       => sanitize_text_field( (string) ( $payload['breed'] ?? '' ) ),
			'temperament' => sanitize_text_field( (string) ( $payload['temperament'] ?? '' ) ),
			'notes'       => sanitize_textarea_field( (string) ( $payload['notes'] ?? '' ) ),
			'updated_at'  => $this->plugin->now(),
		);
		$formats = array( '%s', '%d', '%s', '%s', '%s', '%s' );

		$weight_raw = isset( $payload['weight'] ) ? trim( (string) $payload['weight'] ) : '';
		if ( '' !== $weight_raw ) {
			$data['weight'] = (float) $weight_raw;
			$formats[]      = '%f';
		}

		if ( isset( $payload['id'] ) ) {
			$client_id = (int) $payload['id'];
			if ( $client_id > 0 ) {
				$this->wpdb->update(
					$this->tables['clients'],
					$data,
					array( 'id' => $client_id ),
					$formats,
					array( '%d' )
				);

				return array(
					'client_id'   => $client_id,
					'guardian_id' => $data['guardian_id'],
				);
			}
		}

		$data['slug']       = $this->plugin->unique_slug( $data['name'], $this->tables['clients'], 'slug' );
		$data['created_at'] = $this->plugin->now();
		$formats[]          = '%s';
		$formats[]          = '%s';

		$this->wpdb->insert(
			$this->tables['clients'],
			$data,
			$formats
		);

		return array(
			'client_id'   => (int) $this->wpdb->insert_id,
			'guardian_id' => $data['guardian_id'],
		);
	}

	/**
	 * Create or update a guardian record.
	 *
	 * @param array $payload Guardian payload.
	 * @return int|WP_Error
	 */
	public function create_or_update_guardian( array $payload ) {
		$first = trim( (string) ( $payload['first_name'] ?? '' ) );
		$last  = trim( (string) ( $payload['last_name'] ?? '' ) );

		if ( '' === $first || '' === $last ) {
			return new WP_Error(
				'bbgf_visit_guardian_missing_name',
				__( 'Guardian first and last name are required.', 'bb-groomflow' ),
				array( 'status' => 400 )
			);
		}

		$data = array(
			'first_name'        => sanitize_text_field( $first ),
			'last_name'         => sanitize_text_field( $last ),
			'email'             => sanitize_email( (string) ( $payload['email'] ?? '' ) ),
			'phone_mobile'      => sanitize_text_field( (string) ( $payload['phone'] ?? '' ) ),
			'preferred_contact' => sanitize_text_field( (string) ( $payload['preferred_contact'] ?? '' ) ),
			'notes'             => sanitize_textarea_field( (string) ( $payload['notes'] ?? '' ) ),
			'updated_at'        => $this->plugin->now(),
		);

		if ( isset( $payload['id'] ) ) {
			$guardian_id = (int) $payload['id'];
			if ( $guardian_id > 0 ) {
				$this->wpdb->update(
					$this->tables['guardians'],
					$data,
					array( 'id' => $guardian_id ),
					array( '%s', '%s', '%s', '%s', '%s', '%s', '%s' ),
					array( '%d' )
				);

				return $guardian_id;
			}
		}

		$data['created_at'] = $this->plugin->now();
		$this->wpdb->insert(
			$this->tables['guardians'],
			$data,
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		return (int) $this->wpdb->insert_id;
	}

	/**
	 * Search clients and guardians for intake workflows.
	 *
	 * @param string $query Search string.
	 * @param int    $limit Max rows to return.
	 * @return array<int,array<string,mixed>>
	 */
	public function search_intake_entities( string $query, int $limit = 10 ): array {
		$wpdb = $this->wpdb;

		$query = trim( $query );
		$limit = max( 1, min( 20, $limit ) );

		if ( '' !== $query ) {
			$like = '%' . $this->wpdb->esc_like( $query ) . '%';
			$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->prepare(
					'SELECT c.id AS client_id, c.name AS client_name, c.breed, c.weight, c.temperament, c.guardian_id,
						g.id AS guardian_id, g.first_name, g.last_name, g.email, g.phone_mobile, g.phone_alt, g.preferred_contact
					FROM %i AS c
					LEFT JOIN %i AS g ON g.id = c.guardian_id
					WHERE (c.name LIKE %s OR c.breed LIKE %s OR c.notes LIKE %s OR g.first_name LIKE %s OR g.last_name LIKE %s OR g.email LIKE %s OR g.phone_mobile LIKE %s OR g.phone_alt LIKE %s)
					ORDER BY c.name ASC
					LIMIT %d',
					$this->tables['clients'],
					$this->tables['guardians'],
					$like,
					$like,
					$like,
					$like,
					$like,
					$like,
					$like,
					$like,
					$limit
				),
				ARRAY_A
			);
		} else {
			$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->prepare(
					'SELECT c.id AS client_id, c.name AS client_name, c.breed, c.weight, c.temperament, c.guardian_id,
						g.id AS guardian_id, g.first_name, g.last_name, g.email, g.phone_mobile, g.phone_alt, g.preferred_contact
					FROM %i AS c
					LEFT JOIN %i AS g ON g.id = c.guardian_id
					ORDER BY c.updated_at DESC, c.name ASC
					LIMIT %d',
					$this->tables['clients'],
					$this->tables['guardians'],
					$limit
				),
				ARRAY_A
			);
		}

		if ( empty( $rows ) ) {
			return array();
		}

		return array_map(
			static function ( $row ) {
				$guardian_id = isset( $row['guardian_id'] ) ? (int) $row['guardian_id'] : 0;
				$client_id   = isset( $row['client_id'] ) ? (int) $row['client_id'] : 0;

				return array(
					'client'   => array(
						'id'          => $client_id,
						'name'        => (string) ( $row['client_name'] ?? '' ),
						'breed'       => (string) ( $row['breed'] ?? '' ),
						'weight'      => isset( $row['weight'] ) ? (float) $row['weight'] : null,
						'temperament' => (string) ( $row['temperament'] ?? '' ),
						'guardian_id' => $guardian_id > 0 ? $guardian_id : null,
					),
					'guardian' => $guardian_id > 0 ? array(
						'id'                => $guardian_id,
						'first_name'        => (string) ( $row['first_name'] ?? '' ),
						'last_name'         => (string) ( $row['last_name'] ?? '' ),
						'email'             => (string) ( $row['email'] ?? '' ),
						'phone_mobile'      => (string) ( $row['phone_mobile'] ?? '' ),
						'phone_alt'         => (string) ( $row['phone_alt'] ?? '' ),
						'preferred_contact' => (string) ( $row['preferred_contact'] ?? '' ),
					) : null,
				);
			},
			$rows
		);
	}

	/**
	 * Ensure referenced IDs exist in a table.
	 *
	 * @param string $table Table name.
	 * @param int[]  $ids   IDs to check.
	 * @param string $error Error code.
	 * @return true|WP_Error
	 */
	public function assert_ids_exist( string $table, array $ids, string $error ) {
		$wpdb = $this->wpdb;

		$ids = array_filter( array_map( 'intval', $ids ) );
		if ( empty( $ids ) ) {
			return true;
		}

		$allowed_tables = array_values( $this->tables );
		if ( ! in_array( $table, $allowed_tables, true ) ) {
			return new WP_Error(
				$error,
				__( 'Invalid table reference.', 'bb-groomflow' ),
				array( 'status' => 400 )
			);
		}

		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		$args         = array_merge( array( $table ), $ids );
		$found        = array_map(
			'intval',
			$wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->prepare( // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Placeholder list built from sanitized IDs.
					'SELECT id FROM %i WHERE id IN (' . $placeholders . ')', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Placeholder list built from sanitized IDs.
					...$args
				)
			)
		);
		$missing      = array_diff( $ids, $found );

		if ( ! empty( $missing ) ) {
			return new WP_Error(
				$error,
				__( 'Referenced records do not exist.', 'bb-groomflow' ),
				array( 'status' => 400 )
			);
		}

		return true;
	}

	/**
	 * Check if a record exists.
	 *
	 * @param string $table Table.
	 * @param int    $id    ID.
	 * @return bool
	 */
	public function record_exists( string $table, int $id ): bool {
		$wpdb = $this->wpdb;

		$allowed_tables = array_values( $this->tables );
		if ( ! in_array( $table, $allowed_tables, true ) ) {
			return false;
		}

		$result = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				'SELECT id FROM %i WHERE id = %d',
				$table,
				$id
			)
		) > 0;

		return $result;
	}

	/**
	 * Calculate elapsed seconds for the current stage.
	 *
	 * @param array $visit Visit row.
	 * @return int
	 */
	private function calculate_elapsed_seconds( array $visit ): int {
		$start = $visit['timer_started_at'] ?? null;
		if ( ! $start ) {
			return (int) ( $visit['timer_elapsed_seconds'] ?? 0 );
		}

		$start_time = strtotime( $start );
		if ( false === $start_time ) {
			return (int) ( $visit['timer_elapsed_seconds'] ?? 0 );
		}

		$now = time();
		if ( $now <= $start_time ) {
			return (int) ( $visit['timer_elapsed_seconds'] ?? 0 );
		}

		return (int) ( $visit['timer_elapsed_seconds'] ?? 0 ) + max( 0, $now - $start_time );
	}

	/**
	 * Calculate the live timer seconds for the current stage.
	 *
	 * @param array $visit Visit row.
	 * @param int   $history_total Previously accrued seconds for the stage.
	 * @return int
	 */
	private function calculate_stage_timer_seconds( array $visit, int $history_total = 0 ): int {
		$base    = max( $history_total, (int) ( $visit['timer_elapsed_seconds'] ?? 0 ) );
		$closed  = ! empty( $visit['check_out_at'] );
		$started = $visit['timer_started_at'] ?? null;

		if ( $closed ) {
			return $base;
		}

		if ( ! $started ) {
			return $base;
		}

		$started_timestamp = strtotime( $started );
		if ( false === $started_timestamp ) {
			return $base;
		}

		$now = time();
		if ( $now <= $started_timestamp ) {
			return $base;
		}

		return $base + ( $now - $started_timestamp );
	}

	/**
	 * Retrieve raw visit row with joins.
	 *
	 * @param int $visit_id Visit ID.
	 * @return array<string,mixed>|null
	 */
	public function get_visit_row( int $visit_id ): ?array {
		return $this->visit_repository->get_visit_row( $visit_id );
	}

	/**
	 * Format visit payload with related data.
	 *
	 * @param array<int|string,mixed>        $visit_row Visit row.
	 * @param array<int,array<string,mixed>> $services  Services.
	 * @param array<int,array<string,mixed>> $flags           Flags.
	 * @param array<int,array<string,mixed>> $photos          Photos.
	 * @param array<string,bool>             $options         Options.
	 * @param array<int,array<string,mixed>> $history         Stage history entries.
	 * @param array<int,array<string,int>>   $history_totals  Stage elapsed totals keyed by visit ID then stage key.
	 * @param array<int,array<string,mixed>> $previous_visits Prior visits for the client.
	 * @param array<string,mixed>            $client_meta     Client meta map.
	 * @param array<int,array<string,mixed>> $flag_lookup     Flag lookup keyed by ID.
	 * @return array<string,mixed>
	 */
	private function format_visit_payload( array $visit_row, array $services, array $flags, array $photos = array(), array $options = array(), array $history = array(), array $history_totals = array(), array $previous_visits = array(), array $client_meta = array(), array $flag_lookup = array() ): array {
		$mask_guardian        = ! empty( $options['mask_guardian'] );
		$mask_sensitive       = ! empty( $options['mask_sensitive'] );
		$hide_sensitive_flags = ! empty( $options['hide_sensitive_flags'] );
		$include_history      = ! empty( $options['include_history'] );
		$client_meta          = is_array( $options['client_meta'] ?? $client_meta ) ? ( $options['client_meta'] ?? $client_meta ) : array();
		$flag_lookup          = is_array( $options['flag_lookup'] ?? $flag_lookup ) ? ( $options['flag_lookup'] ?? $flag_lookup ) : array();

		$guardian = array();
		if ( ! $mask_guardian ) {
			$guardian = array(
				'id'         => (int) ( $visit_row['guardian_id'] ?? 0 ),
				'first_name' => (string) ( $visit_row['guardian_first_name'] ?? '' ),
				'last_name'  => (string) ( $visit_row['guardian_last_name'] ?? '' ),
				'phone'      => (string) ( $visit_row['guardian_phone'] ?? '' ),
				'email'      => (string) ( $visit_row['guardian_email'] ?? '' ),
			);
		}

		$normalised_services = array();
		foreach ( $services as $service ) {
			$normalised_services[] = array(
				'id'         => (int) ( $service['id'] ?? 0 ),
				'name'       => (string) ( $service['name'] ?? '' ),
				'icon'       => (string) ( $service['icon'] ?? '' ),
				'package_id' => isset( $service['package_id'] ) ? (int) $service['package_id'] : null,
			);
		}

		$filtered_flags = array();
		foreach ( $flags as $flag ) {
			$severity = isset( $flag['severity'] ) ? strtolower( (string) $flag['severity'] ) : '';
			if ( $hide_sensitive_flags && in_array( $severity, array( 'high', 'critical', 'internal', 'private' ), true ) ) {
				continue;
			}

			$filtered_flags[] = array(
				'id'       => (int) ( $flag['id'] ?? 0 ),
				'name'     => (string) ( $flag['name'] ?? '' ),
				'emoji'    => (string) ( $flag['emoji'] ?? '' ),
				'color'    => (string) ( $flag['color'] ?? '' ),
				'severity' => $severity,
			);
		}

		$normalised_photos = array();
		foreach ( $photos as $photo ) {
			if ( is_array( $photo ) && ! empty( $photo ) ) {
				$normalised_photos[] = $photo;
			}
		}

		if ( ! empty( $normalised_photos ) ) {
			usort(
				$normalised_photos,
				static function ( array $a, array $b ) {
					$is_primary_a = ! empty( $a['is_primary'] );
					$is_primary_b = ! empty( $b['is_primary'] );

					if ( $is_primary_a === $is_primary_b ) {
						return 0;
					}

					return $is_primary_a ? -1 : 1;
				}
			);
		}

		$instructions  = (string) ( $visit_row['instructions'] ?? '' );
		$private_notes = (string) ( $visit_row['private_notes'] ?? '' );
		$public_notes  = (string) ( $visit_row['public_notes'] ?? '' );
		$assigned      = isset( $visit_row['assigned_staff'] ) ? (int) $visit_row['assigned_staff'] : null;
		$client_flags  = $this->map_client_flags( $client_meta, $flag_lookup, $hide_sensitive_flags );
		$client_photo  = $this->format_client_main_photo( $client_meta );

		if ( $mask_sensitive ) {
			$instructions  = '';
			$private_notes = '';
			$public_notes  = '';
			$assigned      = null;
		}

		$visit_id      = (int) $visit_row['id'];
		$current_stage = (string) ( $visit_row['current_stage'] ?? '' );
		$history_total = $history_totals[ $visit_id ][ $current_stage ] ?? 0;

		$payload = array(
			'id'                    => (int) $visit_row['id'],
			'client'                => array(
				'id'         => (int) $visit_row['client_id'],
				'name'       => (string) ( $visit_row['client_name'] ?? '' ),
				'slug'       => (string) ( $visit_row['client_slug'] ?? '' ),
				'breed'      => (string) ( $visit_row['client_breed'] ?? '' ),
				'weight'     => isset( $visit_row['client_weight'] ) ? (float) $visit_row['client_weight'] : null,
				'flags'      => $client_flags,
				'main_photo' => $client_photo,
			),
			'guardian'              => $guardian,
			'view_id'               => (int) ( $visit_row['view_id'] ?? 0 ),
			'current_stage'         => (string) ( $visit_row['current_stage'] ?? '' ),
			'status'                => (string) ( $visit_row['status'] ?? '' ),
			'check_in_at'           => $this->maybe_rfc3339( $visit_row['check_in_at'] ?? null ),
			'check_out_at'          => $this->maybe_rfc3339( $visit_row['check_out_at'] ?? null ),
			'assigned_staff'        => $assigned,
			'instructions'          => $instructions,
			'private_notes'         => $private_notes,
			'public_notes'          => $public_notes,
			'timer_started_at'      => $this->maybe_rfc3339( $visit_row['timer_started_at'] ?? null ),
			'timer_elapsed_seconds' => $this->calculate_stage_timer_seconds( $visit_row, $history_total ),
			'services'              => $normalised_services,
			'flags'                 => $filtered_flags,
			'photos'                => $normalised_photos,
			'previous_visits'       => $previous_visits,
			'history'               => $include_history ? $history : array(),
			'history_total'         => $history_total,
			'created_at'            => $this->maybe_rfc3339( $visit_row['created_at'] ?? null ),
			'updated_at'            => $this->maybe_rfc3339( $visit_row['updated_at'] ?? null ),
		);

		return $payload;
	}

	/**
	 * Collect unique client-level flag IDs from a meta map.
	 *
	 * @param array<int,array<string,mixed>> $client_meta_map Client meta map keyed by ID.
	 * @return array<int>
	 */
	private function collect_client_flag_ids( array $client_meta_map ): array {
		$flag_ids = array();
		foreach ( $client_meta_map as $meta ) {
			foreach ( $meta[ self::CLIENT_META_FLAGS ] ?? array() as $flag_id ) {
				$flag_id = (int) $flag_id;
				if ( $flag_id > 0 ) {
					$flag_ids[ $flag_id ] = $flag_id;
				}
			}
		}

		return array_values( $flag_ids );
	}

	/**
	 * Map client-level flag IDs into full flag objects while respecting masking.
	 *
	 * @param array<string,mixed>            $client_meta  Client meta map.
	 * @param array<int,array<string,mixed>> $flag_lookup  Flag lookup keyed by ID.
	 * @param bool                           $hide_flagged Whether to hide sensitive flags.
	 * @return array<int,array<string,mixed>>
	 */
	private function map_client_flags( array $client_meta, array $flag_lookup, bool $hide_flagged = false ): array {
		$flags    = array();
		$flag_ids = array_map( 'intval', $client_meta[ self::CLIENT_META_FLAGS ] ?? array() );

		foreach ( $flag_ids as $flag_id ) {
			if ( ! isset( $flag_lookup[ $flag_id ] ) ) {
				continue;
			}

			$flag     = $flag_lookup[ $flag_id ];
			$severity = isset( $flag['severity'] ) ? strtolower( (string) $flag['severity'] ) : '';
			if ( $hide_flagged && in_array( $severity, array( 'high', 'critical', 'internal', 'private' ), true ) ) {
				continue;
			}

			$flags[ $flag_id ] = array(
				'id'       => (int) ( $flag['id'] ?? $flag_id ),
				'name'     => (string) ( $flag['name'] ?? '' ),
				'emoji'    => (string) ( $flag['emoji'] ?? '' ),
				'color'    => (string) ( $flag['color'] ?? '' ),
				'severity' => $severity,
			);
		}

		return array_values( $flags );
	}

	/**
	 * Format the client main photo attachment (if any).
	 *
	 * @param array<string,mixed> $client_meta Client meta map.
	 * @return array<string,mixed>|null
	 */
	private function format_client_main_photo( array $client_meta ): ?array {
		$photo_id = isset( $client_meta[ self::CLIENT_META_MAIN_PHOTO ] ) ? (int) $client_meta[ self::CLIENT_META_MAIN_PHOTO ] : 0;
		if ( $photo_id <= 0 ) {
			return null;
		}

		return $this->format_photo_payload( $photo_id );
	}

	/**
	 * Retrieve services keyed by visit ID.
	 *
	 * @param array<int> $visit_ids Visit IDs.
	 * @return array<int,array<mixed>>
	 */
	private function get_visit_services_map( array $visit_ids ): array {
		$visit_ids = array_filter( array_map( 'intval', $visit_ids ) );
		if ( empty( $visit_ids ) ) {
			return array();
		}

		$wpdb         = $this->wpdb;
		$placeholders = implode( ',', array_fill( 0, count( $visit_ids ), '%d' ) );
		$args         = array_merge( array( $this->tables['visit_services'], $this->tables['services'] ), $visit_ids );
		$rows         = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare( // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Placeholder list is dynamic.
				'SELECT vs.visit_id, vs.service_id, vs.package_id, s.name, s.icon'
				. ' FROM %i AS vs'
				. ' LEFT JOIN %i AS s ON s.id = vs.service_id'
				. ' WHERE vs.visit_id IN (' . $placeholders . ')' // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Placeholder list built from sanitized IDs.
				. ' ORDER BY vs.added_at ASC',
				...$args
			),
			ARRAY_A
		);

		$map = array();
		foreach ( $rows as $row ) {
			$visit_id = (int) $row['visit_id'];
			if ( ! isset( $map[ $visit_id ] ) ) {
				$map[ $visit_id ] = array();
			}

			$map[ $visit_id ][] = array(
				'id'         => (int) $row['service_id'],
				'name'       => (string) ( $row['name'] ?? '' ),
				'icon'       => (string) ( $row['icon'] ?? '' ),
				'package_id' => isset( $row['package_id'] ) ? (int) $row['package_id'] : null,
			);
		}

		return $map;
	}

	/**
	 * Retrieve flags keyed by visit ID.
	 *
	 * @param array<int> $visit_ids Visit IDs.
	 * @return array<int,array<mixed>>
	 */
	private function get_visit_flags_map( array $visit_ids ): array {
		$visit_ids = array_filter( array_map( 'intval', $visit_ids ) );
		if ( empty( $visit_ids ) ) {
			return array();
		}

		$wpdb         = $this->wpdb;
		$placeholders = implode( ',', array_fill( 0, count( $visit_ids ), '%d' ) );
		$args         = array_merge( array( $this->tables['visit_flags'], $this->tables['flags'] ), $visit_ids );
		$rows         = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare( // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Placeholder list is dynamic.
				'SELECT vf.visit_id, vf.flag_id, f.name, f.emoji, f.color, f.severity'
				. ' FROM %i AS vf'
				. ' LEFT JOIN %i AS f ON f.id = vf.flag_id'
				. ' WHERE vf.visit_id IN (' . $placeholders . ')' // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Placeholder list built from sanitized IDs.
				. ' ORDER BY vf.added_at ASC',
				...$args
			),
			ARRAY_A
		);

		$map = array();
		foreach ( $rows as $row ) {
			$visit_id = (int) $row['visit_id'];
			if ( ! isset( $map[ $visit_id ] ) ) {
				$map[ $visit_id ] = array();
			}

			$map[ $visit_id ][] = array(
				'id'       => (int) $row['flag_id'],
				'name'     => (string) ( $row['name'] ?? '' ),
				'emoji'    => (string) ( $row['emoji'] ?? '' ),
				'color'    => (string) ( $row['color'] ?? '' ),
				'severity' => (string) ( $row['severity'] ?? '' ),
			);
		}

		return $map;
	}

	/**
	 * Fetch flag definitions keyed by ID.
	 *
	 * @param array<int> $flag_ids Flag IDs.
	 * @return array<int,array<string,string>>
	 */
	private function get_flags_by_ids( array $flag_ids ): array {
		$flag_ids = array_filter( array_map( 'intval', $flag_ids ) );
		if ( empty( $flag_ids ) ) {
			return array();
		}

		$wpdb         = $this->wpdb;
		$placeholders = implode( ',', array_fill( 0, count( $flag_ids ), '%d' ) );
		$args         = array_merge( array( $this->tables['flags'] ), $flag_ids );
		$rows         = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				'SELECT id, name, emoji, color, severity
				FROM %i
				WHERE id IN (' . $placeholders . ')', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Placeholder list built from sanitized IDs.
				...$args
			),
			ARRAY_A
		);

		$lookup = array();
		foreach ( $rows as $row ) {
			$id = (int) ( $row['id'] ?? 0 );
			if ( $id <= 0 ) {
				continue;
			}

			$lookup[ $id ] = array(
				'id'       => $id,
				'name'     => (string) ( $row['name'] ?? '' ),
				'emoji'    => (string) ( $row['emoji'] ?? '' ),
				'color'    => (string) ( $row['color'] ?? '' ),
				'severity' => (string) ( $row['severity'] ?? '' ),
			);
		}

		return $lookup;
	}

	/**
	 * Retrieve and decode meta for a set of clients.
	 *
	 * @param array<int> $client_ids Client IDs.
	 * @return array<int,array<string,mixed>>
	 */
	private function get_clients_meta_map( array $client_ids ): array {
		$client_ids = array_filter( array_map( 'intval', $client_ids ) );
		if ( empty( $client_ids ) ) {
			return array();
		}

		$wpdb         = $this->wpdb;
		$placeholders = implode( ',', array_fill( 0, count( $client_ids ), '%d' ) );
		$args         = array_merge( array( $this->tables['clients'] ), $client_ids );
		$rows         = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				'SELECT id, meta FROM %i WHERE id IN (' . $placeholders . ')', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Placeholder list built from sanitized IDs.
				...$args
			),
			ARRAY_A
		);

		$map = array();
		foreach ( $rows as $row ) {
			$id = (int) ( $row['id'] ?? 0 );
			if ( $id <= 0 ) {
				continue;
			}

			$meta        = $this->decode_meta_value( $row['meta'] ?? null );
			$flag_ids    = array();
			$main_photo  = null;
			$maybe_flags = $meta[ self::CLIENT_META_FLAGS ] ?? array();
			$maybe_photo = $meta[ self::CLIENT_META_MAIN_PHOTO ] ?? null;

			if ( is_array( $maybe_flags ) ) {
				$flag_ids = array_filter(
					array_map(
						static function ( $flag_id ) {
							return (int) $flag_id;
						},
						$maybe_flags
					)
				);
			}

			if ( ! empty( $maybe_photo ) ) {
				$main_photo = (int) $maybe_photo;
			}

			$map[ $id ] = array(
				self::CLIENT_META_FLAGS      => $flag_ids,
				self::CLIENT_META_MAIN_PHOTO => $main_photo,
				'raw'                        => $meta,
			);
		}

		return $map;
	}

	/**
	 * Retrieve accumulated elapsed seconds per visit per stage from history.
	 *
	 * @param array<int> $visit_ids Visit IDs.
	 * @return array<int,array<string,int>>
	 */
	private function get_stage_elapsed_totals( array $visit_ids ): array {
		$visit_ids = array_filter( array_map( 'intval', $visit_ids ) );
		if ( empty( $visit_ids ) ) {
			return array();
		}

		$wpdb         = $this->wpdb;
		$placeholders = implode( ',', array_fill( 0, count( $visit_ids ), '%d' ) );
		$args         = array_merge( array( $this->tables['stage_history'] ), $visit_ids );
		$rows         = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				'SELECT visit_id, from_stage, SUM(elapsed_seconds) AS total'
				. ' FROM %i'
				. ' WHERE visit_id IN (' . $placeholders . ')' // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Placeholder list built from sanitized IDs.
				. ' GROUP BY visit_id, from_stage',
				...$args
			),
			ARRAY_A
		);

		$totals = array();
		foreach ( $rows as $row ) {
			$visit_id = (int) ( $row['visit_id'] ?? 0 );
			$from     = sanitize_key( (string) ( $row['from_stage'] ?? '' ) );
			$elapsed  = isset( $row['total'] ) ? (int) $row['total'] : 0;
			if ( $visit_id <= 0 || '' === $from ) {
				continue;
			}

			if ( ! isset( $totals[ $visit_id ] ) ) {
				$totals[ $visit_id ] = array();
			}

			$totals[ $visit_id ][ $from ] = $elapsed;
		}

		return $totals;
	}

	/**
	 * Retrieve photos keyed by visit ID.
	 *
	 * @param array<int> $visit_ids Visit IDs.
	 * @return array<int,array<int,array<string,mixed>>>
	 */
	private function get_visit_photos_map( array $visit_ids ): array {
		$visit_ids = array_filter( array_map( 'intval', $visit_ids ) );
		if ( empty( $visit_ids ) ) {
			return array();
		}

		$query = get_posts(
			array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'posts_per_page' => -1,
				'orderby'        => 'date',
				'order'          => 'ASC',
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Attachment lookup constrained to specific visit IDs.
				'meta_query'     => array(
					array(
						'key'     => self::VISIT_PHOTO_META_KEY,
						'value'   => $visit_ids,
						'compare' => 'IN',
						'type'    => 'NUMERIC',
					),
				),
			)
		);

		$map = array();
		foreach ( $query as $attachment ) {
			$linked_visit = (int) get_post_meta( $attachment->ID, self::VISIT_PHOTO_META_KEY, true );
			if ( $linked_visit <= 0 ) {
				continue;
			}

			$photo = $this->format_photo_payload( $attachment->ID );
			if ( null === $photo ) {
				continue;
			}

			if ( ! isset( $map[ $linked_visit ] ) ) {
				$map[ $linked_visit ] = array();
			}

			$map[ $linked_visit ][] = $photo;
		}

		return $map;
	}

	/**
	 * Normalise attachment metadata for front-end photo rendering.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return array<string,mixed>|null
	 */
	private function format_photo_payload( int $attachment_id ): ?array {
		$attachment = get_post( $attachment_id );
		if ( ! $attachment || 'attachment' !== $attachment->post_type ) {
			return null;
		}

		if ( ! wp_attachment_is_image( $attachment_id ) ) {
			return null;
		}

		$src = wp_get_attachment_image_src( $attachment_id, 'full' );
		if ( ! $src || empty( $src[0] ) ) {
			$url = wp_get_attachment_url( $attachment_id );
			if ( ! $url ) {
				return null;
			}
		} else {
			$url = (string) $src[0];
		}

		$alt = get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );
		if ( ! is_string( $alt ) || '' === trim( $alt ) ) {
			$alt = $attachment->post_title ?? '';
		}

		$sizes       = array();
		$image_sizes = get_intermediate_image_sizes();
		foreach ( $image_sizes as $size ) {
			$details = wp_get_attachment_image_src( $attachment_id, $size );
			if ( ! $details || empty( $details[0] ) ) {
				continue;
			}

			$sizes[ $size ] = array(
				'url'    => (string) $details[0],
				'width'  => isset( $details[1] ) ? (int) $details[1] : 0,
				'height' => isset( $details[2] ) ? (int) $details[2] : 0,
			);
		}

		$uploaded_at = (string) ( $attachment->post_date_gmt ?? '' );
		if ( '' === $uploaded_at ) {
			$uploaded_at = (string) ( $attachment->post_date ?? '' );
		}

		$uploaded_at = is_string( $uploaded_at ) && '' !== $uploaded_at ? $uploaded_at : null;
		$visible     = get_post_meta( $attachment_id, self::VISIT_PHOTO_GUARDIAN_META_KEY, true );

		return array(
			'id'                  => (int) $attachment_id,
			'url'                 => (string) $url,
			'alt'                 => (string) $alt,
			'mime_type'           => (string) get_post_mime_type( $attachment_id ),
			'sizes'               => $sizes,
			'thumbnail'           => $sizes['thumbnail'] ?? null,
			'width'               => $sizes['full']['width'] ?? ( $sizes['large']['width'] ?? null ),
			'height'              => $sizes['full']['height'] ?? ( $sizes['large']['height'] ?? null ),
			'uploaded_at'         => $uploaded_at ? mysql_to_rfc3339( $uploaded_at ) : null,
			'visible_to_guardian' => (bool) ( '' === $visible ? true : (int) $visible ),
			'is_primary'          => (bool) get_post_meta( $attachment_id, self::VISIT_PHOTO_PRIMARY_META_KEY, true ),
		);
	}

	/**
	 * Synchronise visit services on the pivot table.
	 *
	 * @param int   $visit_id Visit ID.
	 * @param array $services Services.
	 * @return void
	 */
	private function sync_visit_services( int $visit_id, array $services ): void {
		$this->wpdb->delete( $this->tables['visit_services'], array( 'visit_id' => $visit_id ), array( '%d' ) );

		$services = array_unique( array_filter( array_map( 'intval', $services ) ) );
		if ( empty( $services ) ) {
			return;
		}

		$now     = $this->plugin->now();
		$user_id = get_current_user_id();

		foreach ( $services as $service_id ) {
			$this->wpdb->insert(
				$this->tables['visit_services'],
				array(
					'visit_id'   => $visit_id,
					'service_id' => $service_id,
					'package_id' => null,
					'added_by'   => $user_id > 0 ? $user_id : null,
					'added_at'   => $now,
				),
				array( '%d', '%d', '%d', '%d', '%s' )
			);
		}
	}

	/**
	 * Synchronise visit flags on the pivot table.
	 *
	 * @param int   $visit_id Visit ID.
	 * @param array $flags    Flags.
	 * @return void
	 */
	private function sync_visit_flags( int $visit_id, array $flags ): void {
		$this->wpdb->delete( $this->tables['visit_flags'], array( 'visit_id' => $visit_id ), array( '%d' ) );

		$flags = array_unique( array_filter( array_map( 'intval', $flags ) ) );
		if ( empty( $flags ) ) {
			return;
		}

		$now     = $this->plugin->now();
		$user_id = get_current_user_id();

		foreach ( $flags as $flag_id ) {
			$this->wpdb->insert(
				$this->tables['visit_flags'],
				array(
					'visit_id' => $visit_id,
					'flag_id'  => $flag_id,
					'notes'    => '',
					'added_by' => $user_id > 0 ? $user_id : null,
					'added_at' => $now,
				),
				array( '%d', '%d', '%s', '%d', '%s' )
			);
		}
	}

	/**
	 * Log a stage change history record.
	 *
	 * @param int    $visit_id Visit ID.
	 * @param string $from     From stage.
	 * @param string $to       Destination stage.
	 * @param string $comment  Comment.
	 * @param int    $user_id  User ID.
	 * @param int    $elapsed  Elapsed seconds.
	 * @return int|null Inserted history row ID on success.
	 */
	private function log_stage_history( int $visit_id, string $from, string $to, string $comment, int $user_id, int $elapsed ): ?int {
		$result = $this->wpdb->insert(
			$this->tables['stage_history'],
			array(
				'visit_id'        => $visit_id,
				'from_stage'      => $from,
				'to_stage'        => $to,
				'comment'         => $comment,
				'changed_by'      => $user_id > 0 ? $user_id : null,
				'changed_at'      => $this->plugin->now(),
				'elapsed_seconds' => max( 0, $elapsed ),
			),
			array( '%d', '%s', '%s', '%s', '%d', '%s', '%d' )
		);

		if ( false === $result ) {
			return null;
		}

		return (int) $this->wpdb->insert_id;
	}

	/**
	 * Retrieve a single stage history entry by ID.
	 *
	 * @param int $history_id History row ID.
	 * @return array<string,mixed>|null
	 */
	private function get_stage_history_entry( int $history_id ): ?array {
		$history_id = (int) $history_id;
		if ( $history_id <= 0 ) {
			return null;
		}

		$wpdb = $this->wpdb;
		$row  = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				'SELECT id, visit_id, from_stage, to_stage, comment, changed_by, changed_at, elapsed_seconds
				FROM %i
				WHERE id = %d',
				$this->tables['stage_history'],
				$history_id
			),
			ARRAY_A
		);
		if ( ! is_array( $row ) ) {
			return null;
		}

		return $this->format_stage_history_row( $row, $this->get_stage_library() );
	}

	/**
	 * Normalise a raw stage history row for API responses.
	 *
	 * @param array $row Stage history row.
	 * @param array $library Stage library keyed by slug.
	 * @return array<string,mixed>
	 */
	private function format_stage_history_row( array $row, array $library ): array {
		$from_key = sanitize_key( (string) ( $row['from_stage'] ?? '' ) );
		$to_key   = sanitize_key( (string) ( $row['to_stage'] ?? '' ) );

		$from = array(
			'key'   => $from_key,
			'label' => $library[ $from_key ]['label'] ?? ucwords( str_replace( array( '-', '_' ), ' ', $from_key ) ),
		);

		$to = array(
			'key'   => $to_key,
			'label' => $library[ $to_key ]['label'] ?? ucwords( str_replace( array( '-', '_' ), ' ', $to_key ) ),
		);

		$user_id   = isset( $row['changed_by'] ) ? (int) $row['changed_by'] : 0;
		$user_name = '';
		if ( $user_id > 0 ) {
			$user      = get_userdata( $user_id );
			$user_name = $user ? $user->display_name : '';
		}

		return array(
			'id'              => (int) $row['id'],
			'from_stage'      => $from,
			'to_stage'        => $to,
			'comment'         => (string) ( $row['comment'] ?? '' ),
			'changed_by'      => $user_id > 0 ? $user_id : null,
			'changed_by_name' => $user_name,
			'changed_at'      => $this->maybe_rfc3339( $row['changed_at'] ?? null ),
			'elapsed_seconds' => (int) ( $row['elapsed_seconds'] ?? 0 ),
		);
	}

	/**
	 * Retrieve stage definitions keyed by stage key.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	private function get_stage_library(): array {
		$wpdb = $this->wpdb;

		$cache_ttl = $this->get_cache_ttl( 'metadata' );
		$cache_key = 'stage_library';

		if ( $cache_ttl > 0 ) {
			$cached = wp_cache_get( $cache_key, self::CACHE_GROUP );
			if ( is_array( $cached ) ) {
				return $cached;
			}
		}

		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				'SELECT stage_key, label, description, capacity_soft_limit, capacity_hard_limit, timer_threshold_green, timer_threshold_yellow, timer_threshold_red, sort_order
				FROM %i
				WHERE 1 = %d
				ORDER BY sort_order ASC',
				$this->tables['stages'],
				1
			),
			ARRAY_A
		);

		$library = array();
		foreach ( $rows as $row ) {
			$stage_key = sanitize_key( (string) ( $row['stage_key'] ?? '' ) );
			if ( '' === $stage_key ) {
				continue;
			}

			$library[ $stage_key ] = array(
				'label'                  => (string) ( $row['label'] ?? $stage_key ),
				'description'            => (string) ( $row['description'] ?? '' ),
				'sort_order'             => (int) ( $row['sort_order'] ?? 0 ),
				'capacity_soft_limit'    => (int) ( $row['capacity_soft_limit'] ?? 0 ),
				'capacity_hard_limit'    => (int) ( $row['capacity_hard_limit'] ?? 0 ),
				'timer_threshold_green'  => (int) ( $row['timer_threshold_green'] ?? 0 ),
				'timer_threshold_yellow' => (int) ( $row['timer_threshold_yellow'] ?? 0 ),
				'timer_threshold_red'    => (int) ( $row['timer_threshold_red'] ?? 0 ),
			);
		}

		if ( $cache_ttl > 0 ) {
			wp_cache_set( $cache_key, $library, self::CACHE_GROUP, $cache_ttl );
		}

		return $library;
	}

	/**
	 * Normalise timer thresholds to sane defaults.
	 *
	 * @param array<string,int> $thresholds Raw thresholds.
	 * @return array<string,int>
	 */
	private function normalize_timer_thresholds( array $thresholds ): array {
		$defaults = array(
			'green'  => 900,   // 15 minutes.
			'yellow' => 1800,  // 30 minutes.
			'red'    => 2700,  // 45 minutes.
		);

		$normalized = array_merge( $defaults, $thresholds );

		// If values look like minutes (tiny numbers), treat them as minutes and convert to seconds.
		foreach ( array( 'green', 'yellow', 'red' ) as $key ) {
			if ( isset( $normalized[ $key ] ) && $normalized[ $key ] > 0 && $normalized[ $key ] <= 120 ) {
				$normalized[ $key ] = $normalized[ $key ] * 60;
			}
		}

		if ( $normalized['yellow'] < $normalized['green'] ) {
			$normalized['yellow'] = $normalized['green'];
		}
		if ( $normalized['red'] < $normalized['yellow'] ) {
			$normalized['red'] = $normalized['yellow'];
		}

		return $normalized;
	}

	/**
	 * Retrieve ordered stage rows for a specific view.
	 *
	 * @param int $view_id View ID.
	 * @return array<int,array<string,mixed>>
	 */
	private function get_view_stage_rows( int $view_id ): array {
		if ( $view_id <= 0 ) {
			return array();
		}

		$wpdb      = $this->wpdb;
		$cache_ttl = $this->get_cache_ttl( 'metadata' );
		$cache_key = $this->get_view_stage_cache_key( $view_id );
		if ( $cache_ttl > 0 ) {
			$cached = wp_cache_get( $cache_key, self::CACHE_GROUP );
			if ( is_array( $cached ) ) {
				return $cached;
			}
		}

		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				'SELECT stage_key, label, sort_order, capacity_soft_limit, capacity_hard_limit, timer_threshold_green, timer_threshold_yellow, timer_threshold_red
				FROM %i
				WHERE view_id = %d
				ORDER BY sort_order ASC',
				$this->tables['view_stages'],
				$view_id
			),
			ARRAY_A
		);
		if ( ! is_array( $rows ) ) {
			return array();
		}

		$normalised = array();
		foreach ( $rows as $row ) {
			$stage_key = sanitize_key( (string) ( $row['stage_key'] ?? '' ) );
			if ( '' === $stage_key ) {
				continue;
			}

			$normalised[] = array(
				'stage_key'              => $stage_key,
				'label'                  => (string) ( $row['label'] ?? '' ),
				'sort_order'             => (int) ( $row['sort_order'] ?? 0 ),
				'capacity_soft_limit'    => (int) ( $row['capacity_soft_limit'] ?? 0 ),
				'capacity_hard_limit'    => (int) ( $row['capacity_hard_limit'] ?? 0 ),
				'timer_threshold_green'  => (int) ( $row['timer_threshold_green'] ?? 0 ),
				'timer_threshold_yellow' => (int) ( $row['timer_threshold_yellow'] ?? 0 ),
				'timer_threshold_red'    => (int) ( $row['timer_threshold_red'] ?? 0 ),
			);
		}

		if ( $cache_ttl > 0 ) {
			wp_cache_set( $cache_key, $normalised, self::CACHE_GROUP, $cache_ttl );
		}

		return $normalised;
	}

	/**
	 * Convert MySQL datetime to RFC3339 string.
	 *
	 * @param string|null $datetime Datetime.
	 * @return string|null
	 */
	private function maybe_rfc3339( ?string $datetime ): ?string {
		if ( null === $datetime || '' === $datetime ) {
			return null;
		}

		$datetime = trim( $datetime );
		if ( 0 === strpos( $datetime, '0000-' ) ) {
			return null;
		}

		$time = strtotime( $datetime );
		if ( false === $time ) {
			return null;
		}

		return gmdate( DATE_RFC3339, $time );
	}

	/**
	 * Flush cached board payloads for multiple views.
	 *
	 * Board payloads are cached per view + filters; centralizing invalidation avoids duplicate flushes.
	 *
	 * @param array<int> $view_ids View IDs.
	 * @return void
	 */
	private function flush_cache_for_views( array $view_ids ): void {
		$view_ids = array_values( array_unique( array_filter( array_map( 'intval', $view_ids ) ) ) );
		if ( empty( $view_ids ) ) {
			$this->flush_cache();
			return;
		}

		foreach ( $view_ids as $view_id ) {
			$this->flush_cache( $view_id );
		}
	}

	/**
	 * Clear cached board payloads and metadata.
	 *
	 * @param int|null $view_id Optional view ID to target. Flushes all caches when omitted.
	 * @return void
	 */
	public function flush_cache( ?int $view_id = null ): void {
		if ( null === $view_id ) {
			$cached_views = wp_cache_get( self::CACHE_INDEX_ALL_VIEWS, self::CACHE_GROUP );

			wp_cache_delete( 'views_list', self::CACHE_GROUP );
			wp_cache_delete( 'stage_library', self::CACHE_GROUP );

			if ( is_array( $cached_views ) ) {
				foreach ( $cached_views as $cached_view_id ) {
					wp_cache_delete( $this->get_view_stage_cache_key( (int) $cached_view_id ), self::CACHE_GROUP );
				}
			}

			$this->purge_board_cache_keys();

			return;
		}

		$view_id = (int) $view_id;
		if ( $view_id <= 0 ) {
			return;
		}

		wp_cache_delete( $this->get_view_stage_cache_key( $view_id ), self::CACHE_GROUP );
		$this->purge_board_cache_keys( $view_id );
	}

	/**
	 * Fetch the latest visit update timestamp for a view/stage filter.
	 *
	 * @param int               $view_id           View ID.
	 * @param array<int|string> $stage_filter_list Stage filter list.
	 * @return string
	 */
	private function get_latest_visit_updated_at( int $view_id, array $stage_filter_list ): string {
		if ( $view_id <= 0 ) {
			return '';
		}

		$wpdb = $this->wpdb;

		$sql_args     = array( $this->tables['visits'], $view_id, 'completed' );
		$stage_keys   = array();
		$stage_clause = '';

		if ( ! empty( $stage_filter_list ) ) {
			$stage_keys   = array_values(
				array_filter(
					array_map(
						static function ( $stage ) {
							return sanitize_key( (string) $stage );
						},
						$stage_filter_list
					)
				)
			);
			$placeholders = implode( ',', array_fill( 0, count( $stage_keys ), '%s' ) );
			$stage_clause = ' AND current_stage IN (' . $placeholders . ')';
			$sql_args     = array_merge( $sql_args, $stage_keys );
		}

		$latest = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare( // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Stage filters append dynamic placeholders.
				'SELECT MAX(updated_at) FROM %i WHERE view_id = %d AND check_out_at IS NULL AND (status IS NULL OR status <> %s)'
				. $stage_clause, // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Stage filters appended from sanitized values.
				...$sql_args
			)
		);

		return is_string( $latest ) ? $latest : '';
	}

	/**
	 * Create a stable cache key for a board payload context.
	 *
	 * @param array<string,mixed> $context Cache context (view, filters, masking).
	 * @param string              $latest_hint Latest visit timestamp hint.
	 * @return string
	 */
	private function get_board_cache_key( array $context, string $latest_hint ): string {
		$context['stages'] = array_values(
			array_filter(
				array_unique(
					array_map(
						static function ( $stage ) {
							return sanitize_key( (string) $stage );
						},
						$context['stages'] ?? array()
					)
				)
			)
		);

		sort( $context['stages'] );

		$context['latest'] = $latest_hint;

		return 'board_' . md5( wp_json_encode( $context ) );
	}

	/**
	 * Track cache keys for a view so they can be purged later.
	 *
	 * @param int    $view_id   View ID.
	 * @param string $cache_key Cache key to track.
	 * @return void
	 */
	private function track_board_cache_key( int $view_id, string $cache_key ): void {
		if ( $view_id <= 0 || '' === $cache_key ) {
			return;
		}

		$index_key = $this->get_board_cache_index_key( $view_id );
		$keys      = wp_cache_get( $index_key, self::CACHE_GROUP );
		if ( ! is_array( $keys ) ) {
			$keys = array();
		}

		if ( ! in_array( $cache_key, $keys, true ) ) {
			$keys[] = $cache_key;
			wp_cache_set( $index_key, $keys, self::CACHE_GROUP, $this->get_cache_ttl( 'board' ) );
		}

		$views_index = wp_cache_get( self::CACHE_INDEX_ALL_VIEWS, self::CACHE_GROUP );
		if ( ! is_array( $views_index ) ) {
			$views_index = array();
		}

		if ( ! in_array( $view_id, $views_index, true ) ) {
			$views_index[] = $view_id;
			wp_cache_set( self::CACHE_INDEX_ALL_VIEWS, $views_index, self::CACHE_GROUP );
		}
	}

	/**
	 * Purge cached board payloads.
	 *
	 * @param int|null $view_id View ID to target or null for all.
	 * @return void
	 */
	private function purge_board_cache_keys( ?int $view_id = null ): void {
		$view_ids = array();

		if ( null === $view_id ) {
			$view_ids = wp_cache_get( self::CACHE_INDEX_ALL_VIEWS, self::CACHE_GROUP );
			if ( ! is_array( $view_ids ) ) {
				$view_ids = array();
			}
		} else {
			$view_ids = array( (int) $view_id );
		}

		foreach ( $view_ids as $cached_view_id ) {
			$index_key = $this->get_board_cache_index_key( (int) $cached_view_id );
			$keys      = wp_cache_get( $index_key, self::CACHE_GROUP );

			if ( is_array( $keys ) ) {
				foreach ( $keys as $key ) {
					wp_cache_delete( $key, self::CACHE_GROUP );
				}
			}

			wp_cache_delete( $index_key, self::CACHE_GROUP );
		}

		if ( null === $view_id ) {
			wp_cache_delete( self::CACHE_INDEX_ALL_VIEWS, self::CACHE_GROUP );
		}
	}

	/**
	 * Cache key for a view's stage definitions.
	 *
	 * @param int $view_id View ID.
	 * @return string
	 */
	private function get_view_stage_cache_key( int $view_id ): string {
		return 'view_stages_' . $view_id;
	}

	/**
	 * Cache key for tracking board cache entries per view.
	 *
	 * @param int $view_id View ID.
	 * @return string
	 */
	private function get_board_cache_index_key( int $view_id ): string {
		return 'board_cache_keys_' . $view_id;
	}

	/**
	 * Resolve cache TTL for a given cache bucket.
	 *
	 * @param string $type Cache bucket (board|metadata|views).
	 * @return int
	 */
	private function get_cache_ttl( string $type ): int {
		$defaults = array(
			'board'    => self::DEFAULT_BOARD_CACHE_TTL,
			'metadata' => self::DEFAULT_METADATA_CACHE_TTL,
			'views'    => self::DEFAULT_METADATA_CACHE_TTL,
		);

		$filters = array(
			'board'    => 'bbgf_board_payload_cache_ttl',
			'metadata' => 'bbgf_board_metadata_cache_ttl',
			'views'    => 'bbgf_board_views_cache_ttl',
		);

		$default_ttl = $defaults[ $type ] ?? self::DEFAULT_METADATA_CACHE_TTL;
		$filter_name = $filters[ $type ] ?? 'bbgf_board_metadata_cache_ttl';

		$ttl = (int) apply_filters( $filter_name, $default_ttl, $type );

		if ( $ttl < 0 ) {
			return 0;
		}

		return $ttl;
	}

	/**
	 * Normalize a timestamp-like input into a MySQL datetime string.
	 *
	 * @param mixed  $value    Candidate value (string/int/DateTimeInterface).
	 * @param string $fallback Fallback timestamp if normalization fails.
	 * @return string
	 */
	private function normalize_timestamp_value( $value, string $fallback ): string {
		if ( $value instanceof DateTimeInterface ) {
			return gmdate( 'Y-m-d H:i:s', $value->getTimestamp() );
		}

		if ( is_int( $value ) ) {
			return gmdate( 'Y-m-d H:i:s', $value );
		}

		if ( is_string( $value ) ) {
			$value = trim( $value );
			if ( 1 === preg_match( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $value ) ) {
				return $value;
			}

			$timestamp = strtotime( $value );
			if ( false !== $timestamp ) {
				return gmdate( 'Y-m-d H:i:s', $timestamp );
			}
		}

		return $fallback;
	}

	/**
	 * Load media helper functions when required.
	 *
	 * @return void
	 */
	private function load_media_dependencies(): void {
		if ( ! function_exists( 'wp_handle_upload' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		if ( ! function_exists( 'wp_generate_attachment_metadata' ) ) {
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}

		if ( ! function_exists( 'media_handle_upload' ) ) {
			require_once ABSPATH . 'wp-admin/includes/media.php';
		}
	}
}
