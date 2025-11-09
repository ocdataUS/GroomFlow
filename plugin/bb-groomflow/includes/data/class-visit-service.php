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
use WP_REST_Response;
use wpdb;

/**
 * Encapsulates visit CRUD, stage transitions, board payloads, and media helpers.
 */
class Visit_Service {
	// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared
	/**
	 * Meta key linking attachment IDs to visits.
	 */
	private const VISIT_PHOTO_META_KEY = '_bbgf_visit_id';

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
	 * Constructor.
	 *
	 * @param Plugin $plugin Plugin instance.
	 */
	public function __construct( Plugin $plugin ) {
		$this->plugin = $plugin;
		$this->wpdb   = $plugin->get_wpdb();
		$this->tables = $plugin->get_table_names();
	}

	/**
	 * Retrieve a view by slug.
	 *
	 * @param string $slug View slug.
	 * @return array<string,mixed>|null
	 */
	public function get_view_by_slug( string $slug ): ?array {
		$sql = $this->wpdb->prepare(
			"SELECT * FROM {$this->tables['views']} WHERE slug = %s",
			$slug
		);

		$row = $this->wpdb->get_row( $sql, ARRAY_A );

		return is_array( $row ) ? $row : null;
	}

	/**
	 * Retrieve the earliest view (default) when no slug provided.
	 *
	 * @return array<string,mixed>|null
	 */
	public function get_default_view(): ?array {
		$sql = "SELECT * FROM {$this->tables['views']} ORDER BY created_at ASC LIMIT 1";
		$row = $this->wpdb->get_row( $sql, ARRAY_A );

		return is_array( $row ) ? $row : null;
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

		$modified_clause = '';
		$modified_args   = array();
		if ( is_string( $modified_after ) && '' !== $modified_after ) {
			$modified_time = strtotime( $modified_after );
			if ( false !== $modified_time ) {
				$modified_clause = ' AND v.updated_at >= %s';
				$modified_args[] = gmdate( 'Y-m-d H:i:s', $modified_time );
			}
		}

		$sql      = "SELECT v.id, v.client_id, v.guardian_id, v.view_id, v.current_stage, v.status, v.check_in_at, v.check_out_at, v.assigned_staff, v.instructions, v.private_notes, v.public_notes, v.timer_started_at, v.timer_elapsed_seconds, v.created_at, v.updated_at,
			c.name AS client_name, c.slug AS client_slug, c.breed AS client_breed, c.weight AS client_weight,
			g.first_name AS guardian_first_name, g.last_name AS guardian_last_name, g.phone_mobile AS guardian_phone, g.email AS guardian_email
			FROM {$this->tables['visits']} AS v
			LEFT JOIN {$this->tables['clients']} AS c ON c.id = v.client_id
			LEFT JOIN {$this->tables['guardians']} AS g ON g.id = v.guardian_id
			WHERE v.view_id = %d{$modified_clause}";
		$args_sql = array_merge( array( (int) $view['id'] ), $modified_args );

		if ( ! empty( $stage_filter_list ) ) {
			$placeholders = implode( ',', array_fill( 0, count( $stage_filter_list ), '%s' ) );
			$sql         .= " AND v.current_stage IN ({$placeholders})";
			$args_sql     = array_merge( $args_sql, $stage_filter_list );
		}

		$sql .= ' ORDER BY v.updated_at DESC';

		$visits = $this->wpdb->get_results( $this->wpdb->prepare( $sql, ...$args_sql ), ARRAY_A );

		$visit_ids = array_map(
			static function ( $row ) {
				return (int) ( $row['id'] ?? 0 );
			},
			$visits
		);

		$services_map = $this->get_visit_services_map( $visit_ids );
		$flags_map    = $this->get_visit_flags_map( $visit_ids );
		$photos_map   = $this->get_visit_photos_map( $visit_ids );

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
				)
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
		$stage_rows    = $this->get_view_stage_rows( (int) $view['id'] );

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
				'timer_thresholds' => array(
					'green'  => (int) ( $row['timer_threshold_green'] ?? 0 ),
					'yellow' => (int) ( $row['timer_threshold_yellow'] ?? 0 ),
					'red'    => (int) ( $row['timer_threshold_red'] ?? 0 ),
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
				'timer_thresholds' => array(
					'green'  => (int) ( $row['timer_threshold_green'] ?? 0 ),
					'yellow' => (int) ( $row['timer_threshold_yellow'] ?? 0 ),
					'red'    => (int) ( $row['timer_threshold_red'] ?? 0 ),
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

		$latest_modified = $latest_modified ? gmdate( 'Y-m-d H:i:s', $latest_modified ) : current_time( 'mysql', true );

		return array(
			'view'         => array(
				'id'               => (int) $view['id'],
				'slug'             => $view['slug'],
				'name'             => $view['name'],
				'type'             => $view['type'],
				'allow_switcher'   => (bool) $view['allow_switcher'],
				'refresh_interval' => (int) $view['refresh_interval'],
				'show_guardian'    => (bool) $view['show_guardian'],
			),
			'stages'       => array_values( $stages_payload ),
			'last_updated' => $latest_modified,
			'visibility'   => array(
				'mask_guardian'  => $mask_guardian,
				'mask_sensitive' => $mask_sensitive_fields,
			),
			'readonly'     => $is_read_only,
			'is_public'    => $is_public_request,
		);
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
		$options  = is_array( $options ) ? $options : array();

		$include_history = ! empty( $options['include_history'] );
		$history_limit   = isset( $options['history_limit'] ) ? (int) $options['history_limit'] : 50;
		$history         = array();

		if ( $include_history ) {
			$history = $this->get_stage_history( $visit_id, $history_limit );
		}

		$options['include_history'] = $include_history;

		return $this->format_visit_payload(
			$row,
			$services[ $visit_id ] ?? array(),
			$flags[ $visit_id ] ?? array(),
			$photos[ $visit_id ] ?? array(),
			$options,
			$history
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

		$sql = $this->wpdb->prepare(
			"SELECT id, visit_id, from_stage, to_stage, comment, changed_by, changed_at, elapsed_seconds
			FROM {$this->tables['stage_history']}
			WHERE visit_id = %d
			ORDER BY changed_at DESC
			LIMIT %d",
			$visit_id,
			$limit
		);

		$rows = $this->wpdb->get_results( $sql, ARRAY_A );
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

		return $payload;
	}

	/**
	 * Update a visit and return the payload.
	 *
	 * @param int                 $visit_id Visit ID.
	 * @param array<string,mixed> $visit    Visit columns.
	 * @param array<int>          $services Services to sync.
	 * @param array<int>          $flags    Flags to sync.
	 * @return array<string,mixed>|WP_Error
	 */
	public function update_visit( int $visit_id, array $visit, array $services, array $flags ) {
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
				'%s',
				'%d',
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

		$this->sync_visit_services( $visit_id, $services );
		$this->sync_visit_flags( $visit_id, $flags );

		$payload = $this->get_visit( $visit_id );
		if ( null === $payload ) {
			return new WP_Error(
				'bbgf_visit_update_fetch_failed',
				__( 'Visit updated but could not be loaded.', 'bb-groomflow' ),
				array( 'status' => 500 )
			);
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

		$now_gmt = $this->plugin->now();
		$elapsed = $this->calculate_elapsed_seconds( $visit_row );

		$result = $this->wpdb->update(
			$this->tables['visits'],
			array(
				'current_stage'         => $destination,
				'timer_started_at'      => $now_gmt,
				'timer_elapsed_seconds' => 0,
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
			$elapsed
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

		return array(
			'visit'         => $updated,
			'history_entry' => $history_entry,
		);
	}

	/**
	 * Link an existing attachment to a visit.
	 *
	 * @param int $visit_id       Visit ID.
	 * @param int $attachment_id  Attachment ID.
	 * @return array<string,mixed>|WP_Error
	 */
	public function attach_existing_photo( int $visit_id, int $attachment_id ) {
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

		$photo = $this->format_photo_payload( $attachment_id );
		if ( null === $photo ) {
			return new WP_Error(
				'bbgf_visit_photo_failed',
				__( 'Unable to prepare photo data.', 'bb-groomflow' ),
				array( 'status' => 500 )
			);
		}

		do_action( 'bbgf_visit_photo_added', $visit_id, $attachment_id, $photo );

		$payload = $this->get_visit( $visit_id );
		if ( null === $payload ) {
			return new WP_Error(
				'bbgf_visit_photo_fetch_failed',
				__( 'Visit not found after attaching photo.', 'bb-groomflow' ),
				array( 'status' => 500 )
			);
		}

		return array(
			'photo' => $photo,
			'visit' => $payload,
		);
	}

	/**
	 * Upload a new photo file and link it to a visit.
	 *
	 * @param int                 $visit_id Visit ID.
	 * @param array<string,mixed> $file     File array.
	 * @return array<string,mixed>|WP_Error
	 */
	public function attach_uploaded_photo( int $visit_id, array $file ) {
		$attachment_id = $this->handle_photo_upload( $file );
		if ( is_wp_error( $attachment_id ) ) {
			return $attachment_id;
		}

		return $this->attach_existing_photo( $visit_id, $attachment_id );
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

		$data = array(
			'name'        => sanitize_text_field( $name ),
			'guardian_id' => $guardian_id > 0 ? $guardian_id : null,
			'notes'       => sanitize_textarea_field( (string) ( $payload['notes'] ?? '' ) ),
			'updated_at'  => $this->plugin->now(),
		);

		if ( isset( $payload['id'] ) ) {
			$client_id = (int) $payload['id'];
			if ( $client_id > 0 ) {
				$this->wpdb->update(
					$this->tables['clients'],
					$data,
					array( 'id' => $client_id ),
					array( '%s', '%d', '%s', '%s' ),
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

		$this->wpdb->insert(
			$this->tables['clients'],
			$data,
			array( '%s', '%d', '%s', '%s', '%s' )
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
	 * Ensure referenced IDs exist in a table.
	 *
	 * @param string $table Table name.
	 * @param int[]  $ids   IDs to check.
	 * @param string $error Error code.
	 * @return true|WP_Error
	 */
	public function assert_ids_exist( string $table, array $ids, string $error ) {
		$ids = array_filter( array_map( 'intval', $ids ) );
		if ( empty( $ids ) ) {
			return true;
		}

		$id_list = implode( ',', $ids );
		$sql     = "SELECT id FROM {$table} WHERE id IN ({$id_list})";
		$found   = array_map( 'intval', $this->wpdb->get_col( $sql ) );
		$missing = array_diff( $ids, $found );

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
		$sql = $this->wpdb->prepare( "SELECT id FROM {$table} WHERE id = %d", $id );
		return (int) $this->wpdb->get_var( $sql ) > 0;
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

		$now     = time();
		$current = max( 0, $now - $start_time );

		return (int) ( $visit['timer_elapsed_seconds'] ?? 0 ) + $current;
	}

	/**
	 * Calculate the live timer seconds for the current stage.
	 *
	 * @param array $visit Visit row.
	 * @return int
	 */
	private function calculate_stage_timer_seconds( array $visit ): int {
		$base    = (int) ( $visit['timer_elapsed_seconds'] ?? 0 );
		$started = $visit['timer_started_at'] ?? null;

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
		if ( $visit_id <= 0 ) {
			return null;
		}

		$sql = $this->wpdb->prepare(
			"SELECT v.*, c.name AS client_name, c.slug AS client_slug, c.breed AS client_breed, c.weight AS client_weight,
				g.first_name AS guardian_first_name, g.last_name AS guardian_last_name, g.phone_mobile AS guardian_phone, g.email AS guardian_email
			FROM {$this->tables['visits']} AS v
			LEFT JOIN {$this->tables['clients']} AS c ON c.id = v.client_id
			LEFT JOIN {$this->tables['guardians']} AS g ON g.id = v.guardian_id
			WHERE v.id = %d",
			$visit_id
		);

		$row = $this->wpdb->get_row( $sql, ARRAY_A );

		return is_array( $row ) ? $row : null;
	}

	/**
	 * Format visit payload with related data.
	 *
	 * @param array<int|string,mixed>        $visit_row Visit row.
	 * @param array<int,array<string,mixed>> $services  Services.
	 * @param array<int,array<string,mixed>> $flags     Flags.
	 * @param array<int,array<string,mixed>> $photos    Photos.
	 * @param array<string,bool>             $options   Options.
	 * @param array<int,array<string,mixed>> $history   Stage history entries.
	 * @return array<string,mixed>
	 */
	private function format_visit_payload( array $visit_row, array $services, array $flags, array $photos = array(), array $options = array(), array $history = array() ): array {
		$mask_guardian        = ! empty( $options['mask_guardian'] );
		$mask_sensitive       = ! empty( $options['mask_sensitive'] );
		$hide_sensitive_flags = ! empty( $options['hide_sensitive_flags'] );
		$include_history      = ! empty( $options['include_history'] );

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

		$instructions  = (string) ( $visit_row['instructions'] ?? '' );
		$private_notes = (string) ( $visit_row['private_notes'] ?? '' );
		$public_notes  = (string) ( $visit_row['public_notes'] ?? '' );
		$assigned      = isset( $visit_row['assigned_staff'] ) ? (int) $visit_row['assigned_staff'] : null;

		if ( $mask_sensitive ) {
			$instructions  = '';
			$private_notes = '';
			$public_notes  = '';
			$assigned      = null;
		}

		$payload = array(
			'id'                    => (int) $visit_row['id'],
			'client'                => array(
				'id'     => (int) $visit_row['client_id'],
				'name'   => (string) ( $visit_row['client_name'] ?? '' ),
				'slug'   => (string) ( $visit_row['client_slug'] ?? '' ),
				'breed'  => (string) ( $visit_row['client_breed'] ?? '' ),
				'weight' => isset( $visit_row['client_weight'] ) ? (float) $visit_row['client_weight'] : null,
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
			'timer_elapsed_seconds' => $this->calculate_stage_timer_seconds( $visit_row ),
			'services'              => $normalised_services,
			'flags'                 => $filtered_flags,
			'photos'                => $normalised_photos,
			'created_at'            => $this->maybe_rfc3339( $visit_row['created_at'] ?? null ),
			'updated_at'            => $this->maybe_rfc3339( $visit_row['updated_at'] ?? null ),
		);

		if ( $include_history ) {
			$payload['history'] = $history;
		}

		return $payload;
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

		$id_list = implode( ',', $visit_ids );

		$sql = "SELECT vs.visit_id, vs.service_id, vs.package_id, s.name, s.icon
			FROM {$this->tables['visit_services']} AS vs
			LEFT JOIN {$this->tables['services']} AS s ON s.id = vs.service_id
			WHERE vs.visit_id IN ({$id_list})
			ORDER BY vs.added_at ASC";

		$rows = $this->wpdb->get_results( $sql, ARRAY_A );

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

		$id_list = implode( ',', $visit_ids );

		$sql = "SELECT vf.visit_id, vf.flag_id, f.name, f.emoji, f.color, f.severity
			FROM {$this->tables['visit_flags']} AS vf
			LEFT JOIN {$this->tables['flags']} AS f ON f.id = vf.flag_id
			WHERE vf.visit_id IN ({$id_list})
			ORDER BY vf.added_at ASC";

		$rows = $this->wpdb->get_results( $sql, ARRAY_A );

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

		return array(
			'id'          => (int) $attachment_id,
			'url'         => (string) $url,
			'alt'         => (string) $alt,
			'mime_type'   => (string) get_post_mime_type( $attachment_id ),
			'sizes'       => $sizes,
			'uploaded_at' => $uploaded_at ? mysql_to_rfc3339( $uploaded_at ) : null,
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

		$sql = $this->wpdb->prepare(
			"SELECT id, visit_id, from_stage, to_stage, comment, changed_by, changed_at, elapsed_seconds
			FROM {$this->tables['stage_history']}
			WHERE id = %d",
			$history_id
		);

		$row = $this->wpdb->get_row( $sql, ARRAY_A );
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
		$sql  = "SELECT stage_key, label, description, capacity_soft_limit, capacity_hard_limit, timer_threshold_green, timer_threshold_yellow, timer_threshold_red, sort_order
			FROM {$this->tables['stages']}
			ORDER BY sort_order ASC";
		$rows = $this->wpdb->get_results( $sql, ARRAY_A );

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

		return $library;
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

		$sql = $this->wpdb->prepare(
			"SELECT stage_key, label, sort_order, capacity_soft_limit, capacity_hard_limit, timer_threshold_green, timer_threshold_yellow, timer_threshold_red
			FROM {$this->tables['view_stages']}
			WHERE view_id = %d
			ORDER BY sort_order ASC",
			$view_id
		);

		$rows = $this->wpdb->get_results( $sql, ARRAY_A );
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
	// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared
}
