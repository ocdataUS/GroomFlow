<?php
/**
 * Board assets + presentation service.
 *
 * @package BB_GroomFlow
 */

namespace BBGF\Bootstrap;

use BBGF\Plugin;

/**
 * Handles board asset registration, localization, and placeholder rendering.
 */
class Assets_Service {
	/**
	 * Asset handle for the placeholder board bundle.
	 */
	private const BOARD_ASSET_HANDLE = 'bbgf-board';

	/**
	 * Primary plugin instance.
	 *
	 * @var Plugin
	 */
	private Plugin $plugin;

	/**
	 * Constructor.
	 *
	 * @param Plugin $plugin Plugin instance.
	 */
	public function __construct( Plugin $plugin ) {
		$this->plugin = $plugin;
	}

	/**
	 * Wire WordPress hooks for asset registration and integrations.
	 */
	public function register(): void {
		add_action( 'init', array( $this, 'register_assets' ) );
		add_shortcode( 'bbgf_board', array( $this, 'render_board_shortcode' ) );
	}

	/**
	 * Register script and style assets for the board experience.
	 */
	public function register_assets(): void {
		$script_path     = BBGF_PLUGIN_DIR . 'assets/build/board.js';
		$asset_meta_path = BBGF_PLUGIN_DIR . 'assets/build/board.asset.php';
		$style_path      = BBGF_PLUGIN_DIR . 'assets/build/board.css';
		$dependencies    = array();
		$asset_version   = BBGF_VERSION;

		if ( file_exists( $asset_meta_path ) ) {
			$asset_meta = include $asset_meta_path;
			if ( isset( $asset_meta['dependencies'] ) && is_array( $asset_meta['dependencies'] ) ) {
				$dependencies = $asset_meta['dependencies'];
			}

			if ( isset( $asset_meta['version'] ) && is_string( $asset_meta['version'] ) ) {
				$asset_version = $asset_meta['version'];
			}
		} elseif ( file_exists( $script_path ) ) {
			$asset_version = (string) filemtime( $script_path );
		}

		if ( file_exists( $script_path ) ) {
			wp_register_script(
				self::BOARD_ASSET_HANDLE,
				BBGF_PLUGIN_URL . 'assets/build/board.js',
				$dependencies,
				$asset_version,
				true
			);

			if ( function_exists( 'wp_set_script_translations' ) ) {
				wp_set_script_translations(
					self::BOARD_ASSET_HANDLE,
					'bb-groomflow',
					BBGF_PLUGIN_DIR . 'languages'
				);
			}
		}

		if ( file_exists( $style_path ) ) {
			wp_register_style(
				self::BOARD_ASSET_HANDLE,
				BBGF_PLUGIN_URL . 'assets/build/board.css',
				array(),
				$asset_version
			);
		}
	}

	/**
	 * Enqueue board assets for the shortcode or admin dashboard.
	 *
	 * @param array<string,mixed> $context           Rendering context (view slug, public token, etc).
	 * @param array<string,mixed> $prebuilt_settings Optional precomputed settings to localize.
	 * @return array<string,mixed> Localized board settings.
	 */
	public function enqueue_board_assets( array $context = array(), ?array $prebuilt_settings = null ): array {
		$board_settings = $prebuilt_settings ?? $this->get_board_bootstrap_settings( $context );

		if ( wp_script_is( self::BOARD_ASSET_HANDLE, 'registered' ) ) {
			wp_localize_script( self::BOARD_ASSET_HANDLE, 'bbgfBoardSettings', $board_settings );
			wp_enqueue_script( self::BOARD_ASSET_HANDLE );
		}

		if ( wp_style_is( self::BOARD_ASSET_HANDLE, 'registered' ) ) {
			wp_enqueue_style( self::BOARD_ASSET_HANDLE );
		}

		return $board_settings;
	}

	/**
	 * Render board container for the shortcode.
	 *
	 * @param array<string,mixed> $atts Shortcode attributes.
	 * @param string              $content Unused content.
	 * @param string              $tag Shortcode tag.
	 * @return string
	 */
	public function render_board_shortcode( array $atts = array(), string $content = '', string $tag = '' ): string {
		unset( $content ); // Unused.

		$shortcode_tag = $tag ? (string) $tag : 'bbgf_board';

		$atts = shortcode_atts(
			array(
				'view'          => '',
				'public_token'  => '',
				'mode'          => '',
				'fullscreen'    => '',
				'toolbar'       => '',
				'search'        => '',
				'filters'       => '',
				'refresh'       => '',
				'last_updated'  => '',
				'countdown'     => '',
				'mask_badge'    => '',
				'view_switcher' => '',
			),
			$atts,
			$shortcode_tag
		);

		$view_slug    = sanitize_key( (string) $atts['view'] );
		$public_token = sanitize_text_field( (string) $atts['public_token'] );
		$mode_param   = sanitize_key( (string) $atts['mode'] );
		$mode_param   = in_array( $mode_param, array( 'display', 'interactive' ), true ) ? $mode_param : '';

		$presentation = array();

		if ( '' !== $mode_param ) {
			$presentation['mode'] = $mode_param;
		}

		$boolean_map = array(
			'fullscreen'   => 'show_fullscreen',
			'toolbar'      => 'show_toolbar',
			'search'       => 'show_search',
			'filters'      => 'show_filters',
			'refresh'      => 'show_refresh',
			'last_updated' => 'show_last_updated',
			'countdown'    => 'show_countdown',
			'mask_badge'   => 'show_mask_badge',
		);

		foreach ( $boolean_map as $attribute => $target_key ) {
			if ( array_key_exists( $attribute, $atts ) && '' !== $atts[ $attribute ] ) {
				$parsed = $this->parse_optional_bool( $atts[ $attribute ] );
				if ( null !== $parsed ) {
					$presentation[ $target_key ] = $parsed;
				}
			}
		}

		if ( isset( $atts['view_switcher'] ) && '' !== $atts['view_switcher'] ) {
			$view_switcher = sanitize_key( (string) $atts['view_switcher'] );
			if ( in_array( $view_switcher, array( 'dropdown', 'buttons', 'none' ), true ) ) {
				$presentation['view_switcher'] = $view_switcher;
			}
		}

		if ( 'display' === $mode_param ) {
			$presentation = array_merge(
				array(
					'mode'              => 'display',
					'show_toolbar'      => false,
					'show_search'       => false,
					'show_filters'      => false,
					'show_refresh'      => false,
					'show_last_updated' => false,
					'show_countdown'    => false,
					'show_fullscreen'   => true,
					'show_mask_badge'   => false,
					'show_notes'        => false,
					'view_switcher'     => isset( $presentation['view_switcher'] ) ? $presentation['view_switcher'] : 'none',
				),
				$presentation
			);
		} elseif ( 'interactive' === $mode_param && ! isset( $presentation['view_switcher'] ) ) {
			$presentation['view_switcher'] = 'dropdown';
		}

		$board_settings = $this->enqueue_board_assets(
			array(
				'view'         => $view_slug,
				'public_token' => $public_token,
				'presentation' => $presentation,
			)
		);

		return $this->get_placeholder_board_markup(
			array(
				'active_view'  => $view_slug,
				'public_token' => $public_token,
				'presentation' => $presentation,
			),
			$board_settings
		);
	}

	/**
	 * Build the payload passed to the front-end board application.
	 *
	 * @param array<string,mixed> $context Rendering context.
	 * @return array<string,mixed>
	 */
	private function get_board_bootstrap_settings( array $context = array() ): array {
		$settings = $this->get_board_script_settings();

		$presentation_request = array();
		if ( isset( $context['presentation'] ) && is_array( $context['presentation'] ) ) {
			$presentation_request = $context['presentation'];
		}
		$presentation_config = $this->normalize_presentation_config( $presentation_request );

		$view_slug    = isset( $context['view'] ) ? sanitize_key( (string) $context['view'] ) : '';
		$public_token = isset( $context['public_token'] ) ? sanitize_text_field( (string) $context['public_token'] ) : '';
		$is_public    = '' !== $public_token;

		$visit_service = $this->plugin->visit_service();
		$view          = null;

		if ( '' !== $view_slug ) {
			$view = $visit_service->get_view_by_slug( $view_slug );
		}

		if ( null === $view ) {
			$view = $visit_service->get_default_view();
		}

		$initial_payload   = null;
		$visibility_config = array(
			'mask_guardian'  => false,
			'mask_sensitive' => false,
			'readonly'       => false,
		);
		$active_view_slug  = '';

		if ( is_array( $view ) ) {
			$visibility_config = $this->determine_board_visibility( $view, $is_public );
			$active_view_slug  = (string) ( $view['slug'] ?? '' );

			$initial_payload = $visit_service->build_board_payload(
				array(
					'view'           => $view,
					'modified_after' => '',
					'stages'         => array(),
					'mask_guardian'  => $visibility_config['mask_guardian'],
					'mask_sensitive' => $visibility_config['mask_sensitive'],
					'readonly'       => $visibility_config['readonly'],
					'is_public'      => $is_public,
				)
			);

			$settings['view'] = array(
				'id'               => (int) ( $view['id'] ?? 0 ),
				'slug'             => $active_view_slug,
				'name'             => (string) ( $view['name'] ?? $active_view_slug ),
				'type'             => isset( $view['type'] ) ? (string) $view['type'] : 'internal',
				'allow_switcher'   => isset( $view['allow_switcher'] ) ? (bool) $view['allow_switcher'] : false,
				'refresh_interval' => isset( $view['refresh_interval'] ) ? (int) $view['refresh_interval'] : 0,
				'show_guardian'    => isset( $view['show_guardian'] ) ? (bool) $view['show_guardian'] : true,
			);
		}

		$settings['initialBoard'] = $initial_payload;
		$settings['visibility']   = $visibility_config;
		$settings['context']      = array(
			'view'        => $active_view_slug,
			'publicToken' => $is_public ? $public_token : '',
		);

		$presentation_mode = isset( $presentation_config['mode'] ) ? $presentation_config['mode'] : '';
		if ( '' === $presentation_mode ) {
			$presentation_mode           = ( ! empty( $visibility_config['readonly'] ) || $is_public ) ? 'display' : 'interactive';
			$presentation_config['mode'] = $presentation_mode;
		}

		if ( empty( $presentation_config['view_switcher'] ) ) {
			$presentation_config['view_switcher'] = 'display' === $presentation_mode ? 'none' : 'dropdown';
		}

		if ( isset( $settings['view']['allow_switcher'] ) && ! $settings['view']['allow_switcher'] ) {
			$presentation_config['view_switcher'] = 'none';
		}

		$settings['views'] = $this->get_frontend_views_list();

		$settings['rest'] = array(
			'root'      => esc_url_raw( rest_url() ),
			'namespace' => 'bb-groomflow/v1',
			'nonce'     => is_user_logged_in() ? wp_create_nonce( 'wp_rest' ) : '',
			'endpoints' => array(
				'board'        => esc_url_raw( rest_url( 'bb-groomflow/v1/board' ) ),
				'visits'       => esc_url_raw( rest_url( 'bb-groomflow/v1/visits' ) ),
				'intakeSearch' => esc_url_raw( rest_url( 'bb-groomflow/v1/visits/intake-search' ) ),
				'services'     => esc_url_raw( rest_url( 'bb-groomflow/v1/services' ) ),
			),
		);

		$settings['capabilities'] = array(
			'viewBoard'      => current_user_can( 'bbgf_view_board' ), // phpcs:ignore WordPress.WP.Capabilities.Unknown -- Custom capability registered in register_roles_and_capabilities().
			'moveStages'     => current_user_can( 'bbgf_move_stages' ), // phpcs:ignore WordPress.WP.Capabilities.Unknown -- Custom capability registered in register_roles_and_capabilities().
			'editVisits'     => current_user_can( 'bbgf_edit_visits' ), // phpcs:ignore WordPress.WP.Capabilities.Unknown -- Custom capability registered in register_roles_and_capabilities().
			'manageViews'    => current_user_can( 'bbgf_manage_views' ), // phpcs:ignore WordPress.WP.Capabilities.Unknown -- Custom capability registered in register_roles_and_capabilities().
			'manageServices' => current_user_can( 'bbgf_manage_services' ), // phpcs:ignore WordPress.WP.Capabilities.Unknown -- Custom capability registered in register_roles_and_capabilities().
		);

		$current_user     = wp_get_current_user();
		$settings['user'] = array(
			'id'          => $current_user instanceof \WP_User ? (int) $current_user->ID : 0,
			'displayName' => $current_user instanceof \WP_User ? (string) $current_user->display_name : '',
		);

		$settings['presentation'] = $presentation_config;
		$settings['placeholders'] = array(
			'photos' => $this->get_placeholder_photos(),
		);

		$settings['strings'] = array(
			'loading'                => __( 'Loading GroomFlow board…', 'bb-groomflow' ),
			'emptyColumn'            => __( 'No visits in this stage yet', 'bb-groomflow' ),
			'noVisits'               => __( 'No visits available.', 'bb-groomflow' ),
			'refresh'                => __( 'Refresh', 'bb-groomflow' ),
			'lastUpdated'            => __( 'Last updated', 'bb-groomflow' ),
			'viewSwitcher'           => __( 'Board views', 'bb-groomflow' ),
			'services'               => __( 'Services', 'bb-groomflow' ),
			'flags'                  => __( 'Behavior flags', 'bb-groomflow' ),
			'notes'                  => __( 'Notes', 'bb-groomflow' ),
			'checkIn'                => __( 'Check-in', 'bb-groomflow' ),
			'addVisit'               => __( 'Add visit', 'bb-groomflow' ),
			'movePrev'               => __( 'Back', 'bb-groomflow' ),
			'moveNext'               => __( 'Next', 'bb-groomflow' ),
			'unknownClient'          => __( 'Client', 'bb-groomflow' ),
			'stageControls'          => __( 'Stage controls', 'bb-groomflow' ),
			'loadingError'           => __( 'Unable to load the board. Please refresh.', 'bb-groomflow' ),
			'modalTitle'             => __( 'Visit details', 'bb-groomflow' ),
			'modalLoading'           => __( 'Loading visit…', 'bb-groomflow' ),
			'modalClose'             => __( 'Close', 'bb-groomflow' ),
			'modalReadOnly'          => __( 'Read-only view', 'bb-groomflow' ),
			'modalSummary'           => __( 'Summary', 'bb-groomflow' ),
			'modalNotes'             => __( 'Notes', 'bb-groomflow' ),
			'modalServices'          => __( 'Services', 'bb-groomflow' ),
			'modalVisit'             => __( 'Visit', 'bb-groomflow' ),
			'modalHistory'           => __( 'History', 'bb-groomflow' ),
			'modalPhotos'            => __( 'Photos', 'bb-groomflow' ),
			'modalNoPrevious'        => __( 'No previous visits found.', 'bb-groomflow' ),
			'modalUploadPhoto'       => __( 'Upload photo', 'bb-groomflow' ),
			'modalVisibleToGuardian' => __( 'Visible to guardian', 'bb-groomflow' ),
			'modalViewPhoto'         => __( 'View full size', 'bb-groomflow' ),
			'modalSave'              => __( 'Save changes', 'bb-groomflow' ),
			'modalSaving'            => __( 'Saving…', 'bb-groomflow' ),
			'modalCheckout'          => __( 'Check out', 'bb-groomflow' ),
			'modalCheckedOut'        => __( 'Checked out', 'bb-groomflow' ),
			'modalCheckoutAt'        => __( 'Checked out at', 'bb-groomflow' ),
			'modalPreparingUpload'   => __( 'Preparing photos…', 'bb-groomflow' ),
			'modalCheckoutConfirm'   => __( 'Are you sure you want to check out this visit?', 'bb-groomflow' ),
			'modalNoHistory'         => __( 'No history recorded yet.', 'bb-groomflow' ),
			'modalNoPhotos'          => __( 'No photos uploaded for this visit.', 'bb-groomflow' ),
			'searchPlaceholder'      => __( 'Search clients, guardians, services…', 'bb-groomflow' ),
			'filterServices'         => __( 'Services', 'bb-groomflow' ),
			'filterFlags'            => __( 'Flags', 'bb-groomflow' ),
			'filterAll'              => __( 'All', 'bb-groomflow' ),
			'errorFetching'          => __( 'Unable to refresh the board. Please try again.', 'bb-groomflow' ),
			'moveSuccess'            => __( 'Visit moved.', 'bb-groomflow' ),
			'fullscreen'             => __( 'Fullscreen', 'bb-groomflow' ),
			'exitFullscreen'         => __( 'Exit fullscreen', 'bb-groomflow' ),
			'autoRefresh'            => __( 'Auto-refresh in', 'bb-groomflow' ),
			'maskedGuardian'         => __( 'Guardian hidden for lobby view', 'bb-groomflow' ),
			'intakeTitle'            => __( 'Add visit', 'bb-groomflow' ),
			'intakeSearchLabel'      => __( 'Search clients or guardians', 'bb-groomflow' ),
			'intakeNoResults'        => __( 'No matches found in this tab. Try Guardian/Client or add details below.', 'bb-groomflow' ),
			'intakeGuardian'         => __( 'Guardian', 'bb-groomflow' ),
			'intakeClient'           => __( 'Client', 'bb-groomflow' ),
			'intakeVisit'            => __( 'Visit details', 'bb-groomflow' ),
			'intakeSubmit'           => __( 'Create visit', 'bb-groomflow' ),
			'intakeSaving'           => __( 'Creating visit…', 'bb-groomflow' ),
			'intakeSuccess'          => __( 'Visit created and added to the board.', 'bb-groomflow' ),
			'intakeSearchHint'       => __( 'Search by client, guardian, phone, or email.', 'bb-groomflow' ),
			'intakeSelect'           => __( 'Select', 'bb-groomflow' ),
		);

		/**
		 * Filters the localized board settings before they are exposed to JavaScript.
		 *
		 * @param array<string,mixed> $settings Built settings array.
		 * @param array<string,mixed> $context  Request context (view, token, presentation overrides).
		 */
		return apply_filters( 'bbgf_board_script_settings', $settings, $context );
	}

	/**
	 * Normalise presentation configuration for the front-end board.
	 *
	 * @param array<string,mixed> $input Raw presentation overrides.
	 * @return array<string,mixed>
	 */
	private function normalize_presentation_config( array $input ): array {
		$config = array();

		if ( isset( $input['mode'] ) ) {
			$mode = sanitize_key( (string) $input['mode'] );
			if ( in_array( $mode, array( 'display', 'interactive' ), true ) ) {
				$config['mode'] = $mode;
			}
		}

		$flags = array(
			'show_toolbar',
			'show_search',
			'show_filters',
			'show_refresh',
			'show_last_updated',
			'show_countdown',
			'show_fullscreen',
			'show_mask_badge',
			'show_notes',
		);

		foreach ( $flags as $flag ) {
			if ( array_key_exists( $flag, $input ) ) {
				$value = $this->parse_optional_bool( $input[ $flag ] );
				if ( null !== $value ) {
					$config[ $flag ] = $value;
				}
			}
		}

		if ( isset( $input['view_switcher'] ) ) {
			$choice = sanitize_key( (string) $input['view_switcher'] );
			if ( in_array( $choice, array( 'dropdown', 'buttons', 'none' ), true ) ) {
				$config['view_switcher'] = $choice;
			}
		}

		return $config;
	}

	/**
	 * Parse a truthy/falsey shortcode attribute.
	 *
	 * @param mixed $value Raw attribute value.
	 * @return bool|null
	 */
	private function parse_optional_bool( $value ): ?bool {
		if ( is_bool( $value ) ) {
			return $value;
		}

		if ( null === $value ) {
			return null;
		}

		$value = strtolower( trim( (string) $value ) );

		if ( '' === $value ) {
			return null;
		}

		if ( in_array( $value, array( '1', 'true', 'yes', 'on' ), true ) ) {
			return true;
		}

		if ( in_array( $value, array( '0', 'false', 'no', 'off' ), true ) ) {
			return false;
		}

		return null;
	}

	/**
	 * Determine masking and read-only rules for a board payload.
	 *
	 * @param array<string,mixed> $view      Active view configuration.
	 * @param bool                $is_public Whether the request was initiated with a public token.
	 * @return array{mask_guardian:bool,mask_sensitive:bool,readonly:bool}
	 */
	private function determine_board_visibility( array $view, bool $is_public ): array {
		$view_type         = isset( $view['type'] ) ? (string) $view['type'] : 'internal';
		$masked_view_types = array( 'lobby', 'kiosk' );

		$mask_guardian = $is_public
			|| in_array( $view_type, $masked_view_types, true )
			|| ! (bool) ( $view['show_guardian'] ?? true );

		$mask_sensitive = $is_public || in_array( $view_type, $masked_view_types, true );
		$readonly       = $is_public || in_array( $view_type, $masked_view_types, true );

		return array(
			'mask_guardian'  => $mask_guardian,
			'mask_sensitive' => $mask_sensitive,
			'readonly'       => $readonly,
		);
	}

	/**
	 * Retrieve all configured views for the board switcher.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private function get_frontend_views_list(): array {
		global $wpdb;

		$tables = $this->plugin->get_table_names();
		if ( empty( $tables['views'] ) ) {
			return array();
		}

		$table = $tables['views'];
		$sql   = sprintf(
			'SELECT id, name, slug, type, allow_switcher, show_guardian, refresh_interval
			FROM %s
			ORDER BY name ASC',
			$table
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
		$rows = $wpdb->get_results( $sql, ARRAY_A );

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

		return $views;
	}

	/**
	 * Settings payload passed to front-end scripts.
	 *
	 * @return array<string,mixed>
	 */
	private function get_board_script_settings(): array {
		$settings = $this->plugin->get_settings();

		return array(
			'pollInterval'    => (int) $settings['board']['poll_interval'],
			'timerThresholds' => array(
				'green'  => (int) $settings['board']['timer_thresholds']['green'],
				'yellow' => (int) $settings['board']['timer_thresholds']['yellow'],
				'red'    => (int) $settings['board']['timer_thresholds']['red'],
			),
			'defaultCapacity' => array(
				'soft' => (int) $settings['board']['default_soft_capacity'],
				'hard' => (int) $settings['board']['default_hard_capacity'],
			),
			'boardColors'     => array(
				'accent'     => $settings['board']['accent_color'],
				'background' => $settings['board']['background_color'],
			),
			'branding'        => array(
				'primaryColor' => $settings['branding']['primary_color'],
				'accentColor'  => $settings['branding']['accent_color'],
				'fontFamily'   => $settings['branding']['font_family'],
			),
			'lobby'           => array(
				'maskGuardian'     => (bool) $settings['lobby']['mask_guardian'],
				'showClientPhoto'  => (bool) $settings['lobby']['show_client_photo'],
				'enableFullscreen' => (bool) $settings['lobby']['enable_fullscreen'],
			),
		);
	}

	/**
	 * Build board markup shared by the shortcode and admin preview.
	 *
	 * @param array<string,mixed> $args      Additional display arguments.
	 * @param array<string,mixed> $settings  Optional precomputed board settings.
	 * @return string
	 */
	public function get_placeholder_board_markup( array $args = array(), ?array $settings = null ): string {
		$args         = wp_parse_args(
			$args,
			array(
				'view'         => 'day',
				'active_view'  => '',
				'public_token' => '',
			)
		);
		$view_key     = (string) $args['view'];
		$active_view  = sanitize_key( (string) $args['active_view'] );
		$public_token = sanitize_text_field( (string) $args['public_token'] );

		if ( null === $settings ) {
			$settings = $this->get_board_bootstrap_settings(
				array(
					'view'         => $view_key,
					'public_token' => $public_token,
					'presentation' => isset( $args['presentation'] ) && is_array( $args['presentation'] ) ? $args['presentation'] : array(),
				)
			);
		}

		$data = null;
		if ( isset( $settings['initialBoard'] ) && is_array( $settings['initialBoard'] ) ) {
			$data = $this->convert_board_payload_to_preview_data( $settings['initialBoard'], $settings );
		}

		if ( ! $data ) {
			$data = $this->get_placeholder_board_data( $view_key );
		} elseif ( isset( $data['active_view'] ) && '' !== $data['active_view'] ) {
			$active_view = sanitize_key( (string) $data['active_view'] );
			if ( '' !== $active_view ) {
				$view_key = $active_view;
			}
		}

		$columns         = $data['columns'];
		$boot_view       = isset( $settings['view'] ) && is_array( $settings['view'] ) ? $settings['view'] : array();
		$boot_visibility = isset( $settings['visibility'] ) && is_array( $settings['visibility'] ) ? $settings['visibility'] : array();
		$boot_board      = isset( $settings['initialBoard'] ) && is_array( $settings['initialBoard'] ) ? $settings['initialBoard'] : array();

		$view_type = sanitize_key(
			(string) (
				$boot_view['type'] ??
				$data['view_type'] ??
				''
			)
		);

		$is_readonly = ! empty( $boot_visibility['readonly'] ) || ! empty( $boot_board['readonly'] );
		if ( ! $is_readonly && isset( $data['readonly'] ) ) {
			$is_readonly = (bool) $data['readonly'];
		}

		$is_public = ! empty( $boot_board['is_public'] ) || ! empty( $data['is_public'] );

		$presentation_mode = '';
		if ( isset( $settings['presentation']['mode'] ) ) {
			$presentation_mode = sanitize_key( (string) $settings['presentation']['mode'] );
		}

		if ( '' === $presentation_mode ) {
			$presentation_mode = ( $is_readonly || $is_public || in_array( $view_type, array( 'lobby', 'kiosk' ), true ) )
				? 'display'
				: 'interactive';
		}

		$board_mode = $data['board_mode'] ?? $presentation_mode;

		$active_view_label = '';
		if ( isset( $boot_view['name'] ) && '' !== (string) $boot_view['name'] ) {
			$active_view_label = (string) $boot_view['name'];
		}

		if ( '' === $active_view_label ) {
			foreach ( $data['views'] as $view_option ) {
				if ( $view_option['key'] === $view_key ) {
					$active_view_label = (string) $view_option['label'];
					break;
				}
			}
		}

		if ( '' === $active_view_label ) {
			$active_view_label = ucwords( str_replace( '-', ' ', $view_key ) );
		}

		/* translators: %s: Board view label. */
		$board_aria_label = sprintf( __( 'GroomFlow board preview for the %s view', 'bb-groomflow' ), $active_view_label );

		$class_names = array( 'bbgf-board-wrapper' );

		if ( 'display' === $board_mode ) {
			$class_names[] = 'bbgf-board-wrapper--display';
		}

		ob_start();
		?>
		<div
			id="bbgf-board-root"
			class="<?php echo esc_attr( implode( ' ', array_unique( $class_names ) ) ); ?>"
			role="region"
			aria-live="polite"
			aria-atomic="true"
			aria-label="<?php echo esc_attr( $board_aria_label ); ?>"
			data-readonly="<?php echo esc_attr( $is_readonly ? 'true' : 'false' ); ?>"
			data-is-public="<?php echo esc_attr( $is_public ? 'true' : 'false' ); ?>"
			data-active-view="<?php echo esc_attr( $active_view ); ?>"
			data-public-token="<?php echo esc_attr( $public_token ); ?>"
			data-view-type="<?php echo esc_attr( $view_type ); ?>"
			data-board-mode="<?php echo esc_attr( $board_mode ); ?>"
		>
			<?php if ( 'display' !== $board_mode ) : ?>
			<div id="bbgf-board-toolbar" class="bbgf-board-toolbar">
				<div class="bbgf-toolbar-view" role="group" aria-label="<?php esc_attr_e( 'Board views', 'bb-groomflow' ); ?>">
					<?php
					foreach ( $data['views'] as $view ) {
						printf(
							'<button type="button" class="bbgf-button %1$s" aria-pressed="%2$s">%3$s</button>',
							$view['active'] ? 'bbgf-button--primary' : 'bbgf-button--ghost',
							$view['active'] ? 'true' : 'false',
							esc_html( $view['label'] )
						);
					}
					?>
				</div>
				<div class="bbgf-toolbar-controls">
					<button type="button" class="bbgf-refresh-button bbgf-button" aria-live="polite">
						<span class="bbgf-refresh-button__icon" aria-hidden="true">⟳</span>
						<?php esc_html_e( 'Refresh', 'bb-groomflow' ); ?>
					</button>
					<span
						class="bbgf-last-updated"
						data-role="bbgf-last-updated"
						data-prefix="<?php esc_attr_e( 'Updated', 'bb-groomflow' ); ?>"
						role="status"
						aria-live="polite"
						aria-atomic="true"
					>
						<?php
						printf(
							/* translators: %s: relative time string. */
							esc_html__( 'Updated %s', 'bb-groomflow' ),
							esc_html( $data['last_updated'] )
						);
						?>
					</span>
				</div>
			</div>
		<?php else : ?>
			<div class="bbgf-display-controls">
				<button type="button" class="bbgf-button bbgf-button--ghost bbgf-toolbar-fullscreen" data-role="bbgf-fullscreen-toggle">
					<?php esc_html_e( 'Fullscreen', 'bb-groomflow' ); ?>
				</button>
			</div>
		<?php endif; ?>

			<div class="bbgf-board" role="list">
				<?php foreach ( $columns as $column ) : ?>
					<?php
					/* translators: 1: Column label, 2: capacity hint. */
					$column_region_label = sprintf( __( '%1$s column — %2$s', 'bb-groomflow' ), $column['label'], $column['capacity_hint'] );
					?>
					<section
						class="bbgf-column <?php echo esc_attr( $column['state_class'] ); ?>"
						role="listitem"
						aria-label="<?php echo esc_attr( $column_region_label ); ?>"
						data-stage="<?php echo esc_attr( $column['key'] ); ?>"
						data-capacity-soft="<?php echo esc_attr( $column['soft_capacity'] ); ?>"
						data-capacity-hard="<?php echo esc_attr( $column['hard_capacity'] ); ?>"
						data-visit-count="<?php echo esc_attr( $column['visit_count'] ); ?>"
						data-available-soft="<?php echo esc_attr( null === $column['available_soft'] ? '' : $column['available_soft'] ); ?>"
						data-available-hard="<?php echo esc_attr( null === $column['available_hard'] ? '' : $column['available_hard'] ); ?>"
						data-soft-exceeded="<?php echo esc_attr( $column['is_soft_exceeded'] ? 'true' : 'false' ); ?>"
						data-hard-exceeded="<?php echo esc_attr( $column['is_hard_exceeded'] ? 'true' : 'false' ); ?>"
						data-capacity-hint="<?php echo esc_attr( $column['capacity_hint'] ); ?>"
					>
						<header class="bbgf-column-header">
							<div class="bbgf-column-title">
								<span class="bbgf-column-label"><?php echo esc_html( $column['label'] ); ?></span>
								<span
									class="bbgf-column-count"
									data-role="bbgf-column-count"
									data-soft-limit="<?php echo esc_attr( $column['soft_capacity'] ); ?>"
									role="status"
									aria-live="polite"
									aria-atomic="true"
								>
									<?php
										printf(
											/* translators: 1: Current count, 2: capacity. */
											esc_html__( '%1$s of %2$s', 'bb-groomflow' ),
											esc_html( number_format_i18n( $column['visit_count'] ) ),
											esc_html( number_format_i18n( $column['soft_capacity'] ) )
										);
									?>
								</span>
							</div>
							<span
								class="bbgf-capacity-badge"
								data-role="bbgf-capacity-hint"
								aria-hidden="true"
							>
								<?php echo esc_html( $column['capacity_hint'] ); ?>
							</span>
						</header>

					<?php
					/* translators: %s: Column label. */
					$column_aria_label = sprintf( __( 'Cards in the %s column', 'bb-groomflow' ), $column['label'] );
					?>
				<div class="bbgf-column-body" role="list" aria-label="<?php echo esc_attr( $column_aria_label ); ?>">
							<?php foreach ( $column['cards'] as $card ) : ?>
								<article
									class="bbgf-card <?php echo esc_attr( $card['modifiers'] ); ?>"
									data-visit-id="<?php echo esc_attr( (string) $card['id'] ); ?>"
									data-stage="<?php echo esc_attr( $column['key'] ); ?>"
									data-updated-at="<?php echo esc_attr( $card['updated_at'] ); ?>"
									data-timer-seconds="<?php echo esc_attr( $card['timer']['seconds'] ); ?>"
									role="listitem"
									aria-label="<?php echo esc_attr( sprintf( '%1$s — %2$s', $card['name'], $column['label'] ) ); ?>"
								>
									<div class="bbgf-card-photo" aria-hidden="true"><?php echo esc_html( $card['avatar'] ); ?></div>
									<div class="bbgf-card-body">
										<div class="bbgf-card-header">
											<p class="bbgf-card-name">
												<?php echo esc_html( $card['name'] ); ?>
									<?php
									$timer_state_label = $this->get_timer_state_label( (string) ( $card['timer']['state'] ?? '' ) );
									if ( '' !== $timer_state_label ) {
										/* translators: 1: elapsed time, 2: timer state. */
										$timer_aria_label = sprintf( __( '%1$s elapsed — %2$s', 'bb-groomflow' ), $card['timer']['value'], $timer_state_label );
									} else {
										/* translators: %s: elapsed time. */
										$timer_aria_label = sprintf( __( '%s elapsed', 'bb-groomflow' ), $card['timer']['value'] );
									}
									?>
									<span
										class="bbgf-card-timer"
										data-state="<?php echo esc_attr( $card['timer']['state'] ); ?>"
										data-seconds="<?php echo esc_attr( $card['timer']['seconds'] ); ?>"
										aria-label="<?php echo esc_attr( $timer_aria_label ); ?>"
									>
										<?php echo esc_html( $card['timer']['value'] ); ?>
									</span>
											</p>
											<p class="bbgf-card-meta">
												<span><?php echo esc_html( $card['service_summary'] ); ?></span>
												<span aria-hidden="true">•</span>
												<span><?php echo esc_html( $card['arrival'] ); ?></span>
											</p>
										</div>

										<?php if ( ! empty( $card['services'] ) ) : ?>
											<div class="bbgf-card-services" aria-label="<?php esc_attr_e( 'Services', 'bb-groomflow' ); ?>">
												<?php foreach ( $card['services'] as $service ) : ?>
													<span class="bbgf-service-chip">
														<?php echo esc_html( $service['icon'] . ' ' . $service['label'] ); ?>
													</span>
												<?php endforeach; ?>
											</div>
										<?php endif; ?>

										<?php if ( ! empty( $card['flags'] ) ) : ?>
											<div class="bbgf-card-flags" aria-label="<?php esc_attr_e( 'Behavior flags', 'bb-groomflow' ); ?>">
												<?php foreach ( $card['flags'] as $flag ) : ?>
													<span class="bbgf-flag-chip">
														<span aria-hidden="true"><?php echo esc_html( $flag['emoji'] ); ?></span>
														<span><?php echo esc_html( $flag['label'] ); ?></span>
													</span>
												<?php endforeach; ?>
											</div>
										<?php endif; ?>

										<?php if ( ! empty( $card['notes'] ) ) : ?>
											<p class="bbgf-card-notes"><?php echo esc_html( $card['notes'] ); ?></p>
										<?php endif; ?>

										<div class="bbgf-card-actions" aria-label="<?php esc_attr_e( 'Stage controls', 'bb-groomflow' ); ?>">
											<?php if ( ! empty( $card['previous_label'] ) ) : ?>
												<button type="button" class="bbgf-button bbgf-move-prev">
													<span aria-hidden="true">←</span>
													<?php echo esc_html( $card['previous_label'] ); ?>
												</button>
											<?php endif; ?>
											<?php if ( ! empty( $card['next_label'] ) ) : ?>
												<button type="button" class="bbgf-button bbgf-move-next">
													<?php echo esc_html( $card['next_label'] ); ?>
													<span aria-hidden="true">→</span>
												</button>
											<?php endif; ?>
										</div>
									</div>
								</article>
							<?php endforeach; ?>
						</div>
					</section>
				<?php endforeach; ?>
			</div>
		</div>
		<?php

		return trim( (string) ob_get_clean() );
	}

	/**
	 * Retrieve placeholder photo URLs for visits without media.
	 *
	 * @return array<int,string>
	 */
	private function get_placeholder_photos(): array {
		$files = array(
			'assets/images/placeholder-dog-1.jpg',
			'assets/images/placeholder-dog-2.jpg',
			'assets/images/placeholder-dog-3.jpg',
		);

		$photos = array();
		foreach ( $files as $relative_path ) {
			$absolute = BBGF_PLUGIN_DIR . $relative_path;
			if ( file_exists( $absolute ) ) {
				$photos[] = BBGF_PLUGIN_URL . $relative_path;
			}
		}

		return $photos;
	}

	/**
	 * Helper to describe placeholder timers.
	 *
	 * @param string $label   Human readable label.
	 * @param int    $seconds Elapsed seconds.
	 * @param string $state   Visual state hint.
	 * @return array<string,mixed>
	 */
	private function make_placeholder_timer( string $label, int $seconds, string $state ): array {
		return array(
			'value'   => $label,
			'seconds' => max( 0, $seconds ),
			'state'   => $state,
		);
	}

	/**
	 * Provide human-friendly timer state descriptions for assistive tech.
	 *
	 * @param string $state Timer state key.
	 * @return string
	 */
	private function get_timer_state_label( string $state ): string {
		switch ( $state ) {
			case 'critical':
				return __( 'running late', 'bb-groomflow' );
			case 'warning':
				return __( 'nearing the time limit', 'bb-groomflow' );
			case 'on-track':
				return __( 'on schedule', 'bb-groomflow' );
			default:
				return '';
		}
	}

	/**
	 * Placeholder data used to render the Kanban preview.
	 *
	 * @param string $active_view Active placeholder view key.
	 * @return array<string, mixed>
	 */
	private function get_placeholder_board_data( string $active_view = 'day' ): array {
		$columns = array(
			array(
				'key'            => 'check-in',
				'label'          => __( 'Check-In', 'bb-groomflow' ),
				'soft_capacity'  => 4,
				'hard_capacity'  => 6,
				'capacity_label' => __( 'Intake staging', 'bb-groomflow' ),
				'cards'          => array(
					array(
						'id'              => 101,
						'name'            => 'Bella',
						'avatar'          => '🐩',
						'arrival'         => __( 'Arrived 8:05 AM', 'bb-groomflow' ),
						'service_summary' => __( 'Full Groom • Teeth Polish', 'bb-groomflow' ),
						'services'        => array(
							array(
								'label' => __( 'Full Groom', 'bb-groomflow' ),
								'icon'  => '✂️',
							),
							array(
								'label' => __( 'Teeth', 'bb-groomflow' ),
								'icon'  => '🦷',
							),
						),
						'flags'           => array(
							array(
								'label' => __( 'VIP', 'bb-groomflow' ),
								'emoji' => '🌟',
							),
						),
						'notes'           => __( 'Prefers warm towel wrap before dryer.', 'bb-groomflow' ),
						'timer'           => $this->make_placeholder_timer( '12m', 12 * MINUTE_IN_SECONDS, 'on-track' ),
						'previous_label'  => '',
						'next_label'      => __( 'Send to Bath', 'bb-groomflow' ),
						'modifiers'       => '',
						'updated_at'      => '2025-10-21T13:58:00Z',
					),
					array(
						'id'              => 102,
						'name'            => 'Ollie',
						'avatar'          => '🐕',
						'arrival'         => __( 'Arrived 8:20 AM', 'bb-groomflow' ),
						'service_summary' => __( 'Bath & Deshed', 'bb-groomflow' ),
						'services'        => array(
							array(
								'label' => __( 'Bath', 'bb-groomflow' ),
								'icon'  => '🫧',
							),
							array(
								'label' => __( 'Deshed', 'bb-groomflow' ),
								'icon'  => '🐾',
							),
						),
						'flags'           => array(
							array(
								'label' => __( 'Gentle handling', 'bb-groomflow' ),
								'emoji' => '💜',
							),
						),
						'notes'           => __( 'New pup — greet with treats at intake.', 'bb-groomflow' ),
						'timer'           => $this->make_placeholder_timer( '04m', 4 * MINUTE_IN_SECONDS, 'on-track' ),
						'previous_label'  => '',
						'next_label'      => __( 'Begin Bath', 'bb-groomflow' ),
						'modifiers'       => '',
						'updated_at'      => '2025-10-21T14:01:00Z',
					),
				),
			),
			array(
				'key'            => 'bath',
				'label'          => __( 'Bath & Dry', 'bb-groomflow' ),
				'soft_capacity'  => 5,
				'hard_capacity'  => 6,
				'capacity_label' => __( 'Nurturing rinse', 'bb-groomflow' ),
				'cards'          => array(
					array(
						'id'              => 201,
						'name'            => 'Nala',
						'avatar'          => '🦴',
						'arrival'         => __( 'In bath 7:55 AM', 'bb-groomflow' ),
						'service_summary' => __( 'Spa Bath • Paw Balm', 'bb-groomflow' ),
						'services'        => array(
							array(
								'label' => __( 'Spa Bath', 'bb-groomflow' ),
								'icon'  => '🛁',
							),
							array(
								'label' => __( 'Paw Balm', 'bb-groomflow' ),
								'icon'  => '🧴',
							),
						),
						'flags'           => array(),
						'notes'           => __( 'Queued for fluff dry; follow-up text to guardian.', 'bb-groomflow' ),
						'timer'           => $this->make_placeholder_timer( '28m', 28 * MINUTE_IN_SECONDS, 'warning' ),
						'previous_label'  => __( 'Back to Check-In', 'bb-groomflow' ),
						'next_label'      => __( 'Move to Groom', 'bb-groomflow' ),
						'modifiers'       => 'bbgf-card--capacity-warning',
						'updated_at'      => '2025-10-21T13:50:00Z',
					),
					array(
						'id'              => 202,
						'name'            => 'Cooper',
						'avatar'          => '🦮',
						'arrival'         => __( 'In bath 8:10 AM', 'bb-groomflow' ),
						'service_summary' => __( 'Bath • Nail Grind', 'bb-groomflow' ),
						'services'        => array(
							array(
								'label' => __( 'Bath', 'bb-groomflow' ),
								'icon'  => '🫧',
							),
							array(
								'label' => __( 'Nail Grind', 'bb-groomflow' ),
								'icon'  => '🪛',
							),
						),
						'flags'           => array(
							array(
								'label' => __( 'Sensitive ears', 'bb-groomflow' ),
								'emoji' => '🎧',
							),
						),
						'notes'           => __( 'Limit dryer noise — quick cool air finish.', 'bb-groomflow' ),
						'timer'           => $this->make_placeholder_timer( '18m', 18 * MINUTE_IN_SECONDS, 'on-track' ),
						'previous_label'  => __( 'Back to Check-In', 'bb-groomflow' ),
						'next_label'      => __( 'Send to Groom', 'bb-groomflow' ),
						'modifiers'       => '',
						'updated_at'      => '2025-10-21T13:56:00Z',
					),
				),
			),
			array(
				'key'            => 'grooming',
				'label'          => __( 'Grooming', 'bb-groomflow' ),
				'soft_capacity'  => 4,
				'hard_capacity'  => 5,
				'capacity_label' => __( 'Signature finishes', 'bb-groomflow' ),
				'cards'          => array(
					array(
						'id'              => 301,
						'name'            => 'Milo',
						'avatar'          => '🐕‍🦺',
						'arrival'         => __( 'In groom 7:40 AM', 'bb-groomflow' ),
						'service_summary' => __( 'Breed Cut • Teeth', 'bb-groomflow' ),
						'services'        => array(
							array(
								'label' => __( 'Breed Cut', 'bb-groomflow' ),
								'icon'  => '✂️',
							),
							array(
								'label' => __( 'Teeth', 'bb-groomflow' ),
								'icon'  => '🦷',
							),
						),
						'flags'           => array(
							array(
								'label' => __( 'DNP without owner', 'bb-groomflow' ),
								'emoji' => '🚨',
							),
						),
						'notes'           => __( 'Guardian requested text 10 minutes before pickup.', 'bb-groomflow' ),
						'timer'           => $this->make_placeholder_timer( '46m', 46 * MINUTE_IN_SECONDS, 'critical' ),
						'previous_label'  => __( 'Return to Bath', 'bb-groomflow' ),
						'next_label'      => __( 'Mark Ready', 'bb-groomflow' ),
						'modifiers'       => 'bbgf-card--overdue',
						'updated_at'      => '2025-10-21T13:40:00Z',
					),
				),
			),
			array(
				'key'            => 'ready',
				'label'          => __( 'Ready for Pickup', 'bb-groomflow' ),
				'soft_capacity'  => 3,
				'hard_capacity'  => 4,
				'capacity_label' => __( 'Lobby ambience', 'bb-groomflow' ),
				'cards'          => array(
					array(
						'id'              => 401,
						'name'            => 'Luna',
						'avatar'          => '🌙',
						'arrival'         => __( 'Ready 8:25 AM', 'bb-groomflow' ),
						'service_summary' => __( 'Express Groom • Blueberry Facial', 'bb-groomflow' ),
						'services'        => array(
							array(
								'label' => __( 'Express Groom', 'bb-groomflow' ),
								'icon'  => '⚡',
							),
							array(
								'label' => __( 'Facial', 'bb-groomflow' ),
								'icon'  => '🫐',
							),
						),
						'flags'           => array(
							array(
								'label' => __( 'Photo consent', 'bb-groomflow' ),
								'emoji' => '📷',
							),
						),
						'notes'           => __( 'Guardian loves lobby slideshow highlight reel.', 'bb-groomflow' ),
						'timer'           => $this->make_placeholder_timer( '2m', 2 * MINUTE_IN_SECONDS, 'on-track' ),
						'previous_label'  => __( 'Back to Groom', 'bb-groomflow' ),
						'next_label'      => __( 'Complete Visit', 'bb-groomflow' ),
						'modifiers'       => '',
						'updated_at'      => '2025-10-21T14:02:00Z',
					),
				),
			),
		);

		$view_type             = 'internal';
		$readonly_placeholder  = false;
		$is_public_placeholder = false;

		if ( 'lobby' === $active_view ) {
			$view_type             = 'lobby';
			$readonly_placeholder  = true;
			$is_public_placeholder = true;
		}

		$board_mode = ( $readonly_placeholder || $is_public_placeholder || in_array( $view_type, array( 'lobby', 'kiosk' ), true ) )
			? 'display'
			: 'interactive';

		foreach ( $columns as &$column ) {
			$count         = count( $column['cards'] );
			$soft_capacity = (int) $column['soft_capacity'];
			$hard_capacity = (int) $column['hard_capacity'];

			$column['visit_count']      = $count;
			$column['available_soft']   = $soft_capacity > 0 ? max( 0, $soft_capacity - $count ) : null;
			$column['available_hard']   = $hard_capacity > 0 ? max( 0, $hard_capacity - $count ) : null;
			$column['is_soft_exceeded'] = $soft_capacity > 0 && $count > $soft_capacity;
			$column['is_hard_exceeded'] = $hard_capacity > 0 && $count > $hard_capacity;

			$state_classes = array();
			if ( $column['is_hard_exceeded'] ) {
				$state_classes[] = 'bbgf-column--hard-full';
			} elseif ( $column['is_soft_exceeded'] ) {
				$state_classes[] = 'bbgf-column--soft-full';
			} elseif ( $soft_capacity > 0 && $column['available_soft'] <= 1 ) {
				$state_classes[] = 'bbgf-column--near-capacity';
			}

			$column['state_class'] = implode( ' ', $state_classes );

			if ( $column['is_hard_exceeded'] ) {
				$column['capacity_hint'] = __( 'Hard limit exceeded', 'bb-groomflow' );
			} elseif ( $column['is_soft_exceeded'] ) {
				$column['capacity_hint'] = __( 'At capacity', 'bb-groomflow' );
			} elseif ( $soft_capacity > 0 ) {
				if ( null !== $column['available_soft'] && $column['available_soft'] > 1 ) {
					$column['capacity_hint'] = sprintf(
						/* translators: %s: number of open slots. */
						__( '%s slots open', 'bb-groomflow' ),
						number_format_i18n( $column['available_soft'] )
					);
				} elseif ( 1 === $column['available_soft'] ) {
					$column['capacity_hint'] = __( '1 slot open', 'bb-groomflow' );
				} else {
					$column['capacity_hint'] = __( 'Fully booked', 'bb-groomflow' );
				}
			} else {
				$column['capacity_hint'] = __( 'Flexible capacity', 'bb-groomflow' );
			}

			foreach ( $column['cards'] as &$card ) {
				if ( isset( $card['timer']['seconds'] ) ) {
					$card['timer']['seconds'] = max( 0, (int) $card['timer']['seconds'] );
				}
			}
			unset( $card );
		}
		unset( $column );

		$data = array(
			'views'        => array(
				array(
					'key'    => 'day',
					'label'  => __( 'Today', 'bb-groomflow' ),
					'active' => 'day' === $active_view,
				),
				array(
					'key'    => 'express',
					'label'  => __( 'Express', 'bb-groomflow' ),
					'active' => 'express' === $active_view,
				),
				array(
					'key'    => 'lobby',
					'label'  => __( 'Lobby Display', 'bb-groomflow' ),
					'active' => 'lobby' === $active_view,
				),
			),
			'last_updated' => __( 'just now', 'bb-groomflow' ),
			'columns'      => $columns,
			'readonly'     => $readonly_placeholder,
			'is_public'    => $is_public_placeholder,
			'view_type'    => $view_type,
			'board_mode'   => $board_mode,
		);

		/**
		 * Filters the placeholder board data used by the shortcode/admin preview.
		 *
		 * @param array<string,mixed> $data        Placeholder dataset.
		 * @param string              $active_view Requested view key.
		 */
		return apply_filters( 'bbgf_placeholder_board_data', $data, $active_view );
	}

	/**
	 * Convert a live board payload into the preview structure consumed by the PHP markup renderer.
	 *
	 * @param array<string,mixed> $board     Board payload from Visit_Service::build_board_payload().
	 * @param array<string,mixed> $settings  Localized settings array.
	 * @return array<string,mixed>
	 */
	private function convert_board_payload_to_preview_data( array $board, array $settings ): array {
		$active_slug     = sanitize_key( (string) ( $board['view']['slug'] ?? $settings['view']['slug'] ?? '' ) );
		$available_views = isset( $settings['views'] ) && is_array( $settings['views'] ) ? $settings['views'] : array();
		$views           = array();

		foreach ( $available_views as $view_option ) {
			$slug = sanitize_key( (string) ( $view_option['slug'] ?? $view_option['key'] ?? '' ) );
			if ( '' === $slug ) {
				continue;
			}

			$views[] = array(
				'key'    => $slug,
				'label'  => (string) ( $view_option['name'] ?? $view_option['label'] ?? $slug ),
				'active' => $slug === $active_slug,
			);
		}

		if ( '' === $active_slug && ! empty( $views ) ) {
			$views[0]['active'] = true;
		}

		if ( empty( $views ) && '' !== $active_slug ) {
			$views[] = array(
				'key'    => $active_slug,
				'label'  => (string) ( $board['view']['name'] ?? $active_slug ),
				'active' => true,
			);
		}

		$stages  = isset( $board['stages'] ) && is_array( $board['stages'] ) ? $board['stages'] : array();
		$columns = array();

		foreach ( $stages as $index => $stage ) {
			$previous  = $stages[ $index - 1 ] ?? null;
			$next      = $stages[ $index + 1 ] ?? null;
			$columns[] = $this->convert_stage_to_column_data( $stage, $previous, $next );
		}

		$view_type  = sanitize_key( (string) ( $board['view']['type'] ?? $settings['view']['type'] ?? 'internal' ) );
		$readonly   = ! empty( $board['readonly'] );
		$is_public  = ! empty( $board['is_public'] );
		$board_mode = ( $readonly || $is_public || in_array( $view_type, array( 'lobby', 'kiosk' ), true ) )
			? 'display'
			: 'interactive';

		return array(
			'views'        => $views,
			'columns'      => $columns,
			'last_updated' => $this->humanize_last_updated_text( $board['last_updated'] ?? '' ),
			'readonly'     => $readonly,
			'is_public'    => $is_public,
			'view_type'    => $view_type,
			'board_mode'   => $board_mode,
			'active_view'  => $active_slug,
		);
	}

	/**
	 * Convert a stage payload into the simplified column data structure used by the preview markup.
	 *
	 * @param array<string,mixed>      $stage          Stage payload.
	 * @param array<string,mixed>|null $previous_stage Previous stage payload.
	 * @param array<string,mixed>|null $next_stage     Next stage payload.
	 * @return array<string,mixed>
	 */
	private function convert_stage_to_column_data( array $stage, ?array $previous_stage, ?array $next_stage ): array {
		$stage_key = sanitize_key( (string) ( $stage['stage_key'] ?? $stage['key'] ?? uniqid( 'stage_', false ) ) );
		$label     = (string) ( $stage['label'] ?? $stage_key );
		$soft      = (int) ( $stage['capacity_soft_limit'] ?? 0 );
		$hard      = (int) ( $stage['capacity_hard_limit'] ?? 0 );

		$cards  = array();
		$visits = isset( $stage['visits'] ) && is_array( $stage['visits'] ) ? $stage['visits'] : array();

		foreach ( $visits as $visit ) {
			$cards[] = $this->convert_visit_to_card_data( $visit, $stage, $previous_stage, $next_stage );
		}

		$count          = count( $cards );
		$available_soft = $soft > 0 ? max( 0, $soft - $count ) : null;
		$available_hard = $hard > 0 ? max( 0, $hard - $count ) : null;
		$is_soft_full   = $soft > 0 && $count > $soft;
		$is_hard_full   = $hard > 0 && $count > $hard;

		$state_class = '';
		if ( $is_hard_full ) {
			$state_class = 'bbgf-column--hard-full';
		} elseif ( $is_soft_full ) {
			$state_class = 'bbgf-column--soft-full';
		} elseif ( $soft > 0 && ( null !== $available_soft && $available_soft <= 1 ) ) {
			$state_class = 'bbgf-column--near-capacity';
		}

		return array(
			'key'              => $stage_key,
			'label'            => $label,
			'soft_capacity'    => $soft,
			'hard_capacity'    => $hard,
			'capacity_label'   => (string) ( $stage['description'] ?? '' ),
			'cards'            => $cards,
			'visit_count'      => $count,
			'available_soft'   => $available_soft,
			'available_hard'   => $available_hard,
			'is_soft_exceeded' => $is_soft_full,
			'is_hard_exceeded' => $is_hard_full,
			'state_class'      => $state_class,
			'capacity_hint'    => $this->generate_capacity_hint( $soft, $hard, $count, $available_soft ),
		);
	}

	/**
	 * Convert a visit payload into the simplified card dataset used by the preview markup.
	 *
	 * @param array<string,mixed>      $visit          Visit payload.
	 * @param array<string,mixed>      $stage          Current stage definition.
	 * @param array<string,mixed>|null $previous_stage Previous stage definition.
	 * @param array<string,mixed>|null $next_stage     Next stage definition.
	 * @return array<string,mixed>
	 */
	private function convert_visit_to_card_data( array $visit, array $stage, ?array $previous_stage, ?array $next_stage ): array {
		$client_name = (string) ( $visit['client']['name'] ?? __( 'Client', 'bb-groomflow' ) );

		$services      = array();
		$service_names = array();
		foreach ( isset( $visit['services'] ) && is_array( $visit['services'] ) ? $visit['services'] : array() as $service ) {
			$label = (string) ( $service['name'] ?? '' );
			$icon  = (string) ( $service['icon'] ?? '' );
			if ( '' !== $label || '' !== $icon ) {
				$services[] = array(
					'label' => $label,
					'icon'  => $icon,
				);
			}

			if ( '' !== $label ) {
				$service_names[] = $label;
			}
		}

		$flags = array();
		foreach ( isset( $visit['flags'] ) && is_array( $visit['flags'] ) ? $visit['flags'] : array() as $flag ) {
			$flags[] = array(
				'label' => (string) ( $flag['name'] ?? '' ),
				'emoji' => (string) ( $flag['emoji'] ?? '' ),
			);
		}

		$notes = (string) ( $visit['public_notes'] ?? $visit['instructions'] ?? '' );

		$seconds     = isset( $visit['timer_elapsed_seconds'] ) ? max( 0, (int) $visit['timer_elapsed_seconds'] ) : 0;
		$timer_state = $this->determine_timer_state(
			$seconds,
			array(
				'yellow' => (int) ( $stage['timer_threshold_yellow'] ?? 0 ),
				'red'    => (int) ( $stage['timer_threshold_red'] ?? 0 ),
			)
		);
		$timer_value = $this->format_timer_value( $seconds );
		$timer       = $this->make_placeholder_timer( $timer_value, $seconds, $timer_state );

		$modifiers = array();
		if ( 'critical' === $timer_state ) {
			$modifiers[] = 'bbgf-card--overdue';
		} elseif ( 'warning' === $timer_state ) {
			$modifiers[] = 'bbgf-card--capacity-warning';
		}

		if ( ! empty( $flags ) ) {
			$modifiers[] = 'bbgf-card--flagged';
		}

		return array(
			'id'              => (int) ( $visit['id'] ?? 0 ),
			'name'            => $client_name,
			'avatar'          => $this->build_avatar_placeholder( $visit ),
			'arrival'         => $this->format_arrival_label( $visit['check_in_at'] ?? '' ),
			'service_summary' => implode( ' • ', $service_names ),
			'services'        => $services,
			'flags'           => $flags,
			'notes'           => $notes,
			'timer'           => $timer,
			'previous_label'  => (string) ( $previous_stage['label'] ?? '' ),
			'next_label'      => (string) ( $next_stage['label'] ?? '' ),
			'modifiers'       => implode( ' ', array_filter( $modifiers ) ),
			'updated_at'      => (string) ( $visit['updated_at'] ?? '' ),
		);
	}

	/**
	 * Generate a capacity hint string for column headers.
	 *
	 * @param int      $soft_capacity Soft limit.
	 * @param int      $hard_capacity Hard limit.
	 * @param int      $count         Current visit count.
	 * @param int|null $available     Remaining soft slots.
	 * @return string
	 */
	private function generate_capacity_hint( int $soft_capacity, int $hard_capacity, int $count, ?int $available ): string {
		if ( $hard_capacity > 0 && $count > $hard_capacity ) {
			return __( 'Hard limit exceeded', 'bb-groomflow' );
		}

		if ( $soft_capacity > 0 && $count > $soft_capacity ) {
			return __( 'At capacity', 'bb-groomflow' );
		}

		if ( $soft_capacity > 0 ) {
			$available = null === $available ? max( 0, $soft_capacity - $count ) : $available;

			if ( $available > 1 ) {
				return sprintf(
					/* translators: %s: number of open slots. */
					__( '%s slots open', 'bb-groomflow' ),
					number_format_i18n( $available )
				);
			}

			if ( 1 === $available ) {
				return __( '1 slot open', 'bb-groomflow' );
			}

			return __( 'Fully booked', 'bb-groomflow' );
		}

		return __( 'Flexible capacity', 'bb-groomflow' );
	}

	/**
	 * Determine a timer state based on elapsed seconds and thresholds.
	 *
	 * @param int               $seconds    Elapsed seconds.
	 * @param array<string,int> $thresholds Stage thresholds.
	 * @return string
	 */
	private function determine_timer_state( int $seconds, array $thresholds ): string {
		$yellow = isset( $thresholds['yellow'] ) ? (int) $thresholds['yellow'] : 0;
		$red    = isset( $thresholds['red'] ) ? (int) $thresholds['red'] : 0;

		if ( $red > 0 && $seconds >= $red ) {
			return 'critical';
		}

		if ( $yellow > 0 && $seconds >= $yellow ) {
			return 'warning';
		}

		return 'on-track';
	}

	/**
	 * Format elapsed seconds into a compact timer label.
	 *
	 * @param int $seconds Elapsed seconds.
	 * @return string
	 */
	private function format_timer_value( int $seconds ): string {
		if ( $seconds >= HOUR_IN_SECONDS ) {
			$hours   = floor( $seconds / HOUR_IN_SECONDS );
			$minutes = floor( ( $seconds % HOUR_IN_SECONDS ) / MINUTE_IN_SECONDS );

			return sprintf( '%dh %02dm', $hours, $minutes );
		}

		if ( $seconds >= MINUTE_IN_SECONDS ) {
			$minutes = floor( $seconds / MINUTE_IN_SECONDS );
			return sprintf( '%dm', $minutes );
		}

		return sprintf( '%ds', $seconds );
	}

	/**
	 * Provide a human-friendly "last updated" string for the toolbar badge.
	 *
	 * @param string $timestamp ISO8601 timestamp.
	 * @return string
	 */
	private function humanize_last_updated_text( string $timestamp ): string {
		if ( '' === $timestamp ) {
			return __( 'just now', 'bb-groomflow' );
		}

		$time = strtotime( $timestamp );
		if ( false === $time ) {
			return __( 'just now', 'bb-groomflow' );
		}

		$diff = human_time_diff( $time, time() );
		if ( '' === $diff ) {
			return __( 'just now', 'bb-groomflow' );
		}

		return sprintf(
			/* translators: %s: relative time (e.g., "5 mins"). */
			__( '%s ago', 'bb-groomflow' ),
			$diff
		);
	}

	/**
	 * Format the arrival label for a visit card.
	 *
	 * @param string $timestamp ISO8601 timestamp.
	 * @return string
	 */
	private function format_arrival_label( string $timestamp ): string {
		if ( '' === $timestamp ) {
			return '';
		}

		$time = strtotime( $timestamp );
		if ( false === $time ) {
			return '';
		}

		$formatted = wp_date( get_option( 'time_format', 'g:i a' ), $time );
		if ( '' === $formatted ) {
			return '';
		}

		/* translators: %s: localized time string. */
		return sprintf( __( 'Arrived %s', 'bb-groomflow' ), $formatted );
	}

	/**
	 * Build an avatar placeholder (initial) for a visit when no photo is available.
	 *
	 * @param array<string,mixed> $visit Visit payload.
	 * @return string
	 */
	private function build_avatar_placeholder( array $visit ): string {
		$name = (string) ( $visit['client']['name'] ?? '' );
		if ( '' === $name ) {
			return '🐾';
		}

		if ( function_exists( 'mb_substr' ) ) {
			return strtoupper( mb_substr( $name, 0, 1 ) );
		}

		return strtoupper( substr( $name, 0, 1 ) );
	}
}
