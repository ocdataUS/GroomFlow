<?php
/**
 * Core plugin bootstrap.
 *
 * @package BB_GroomFlow
 */

namespace BBGF;

use BBGF\Admin\Flags_Admin;
use BBGF\Admin\Clients_Admin;
use BBGF\Admin\Guardians_Admin;
use BBGF\Admin\Services_Admin;
use BBGF\Admin\Packages_Admin;
use BBGF\Admin\Stages_Admin;
use BBGF\Admin\Views_Admin;
use BBGF\Admin\Settings_Admin;
use BBGF\Admin\Notifications_Admin;
use BBGF\Admin\Notification_Triggers_Admin;
use BBGF\Admin\Notification_Logs_Admin;
use BBGF\API\Clients_Controller;
use BBGF\API\Health_Controller;
use BBGF\API\Guardians_Controller;
use BBGF\API\Services_Controller;
use BBGF\API\Flags_Controller;
use BBGF\API\Packages_Controller;
use BBGF\API\Views_Controller;
use BBGF\API\Visits_Controller;
use BBGF\API\Stats_Controller;
use BBGF\Notifications\Notifications_Service;
use BBGF\Data\Visit_Service;
use BBGF\Database\Schema;
use BBGF\Elementor\Board_Widget;
use Elementor\Plugin as ElementorPlugin;
use wpdb;

require_once BBGF_PLUGIN_DIR . 'includes/database/class-schema.php';
require_once BBGF_PLUGIN_DIR . 'includes/admin/class-admin-page-interface.php';
require_once BBGF_PLUGIN_DIR . 'includes/admin/class-flags-admin.php';
require_once BBGF_PLUGIN_DIR . 'includes/admin/class-flags-list-table.php';
require_once BBGF_PLUGIN_DIR . 'includes/admin/class-clients-admin.php';
require_once BBGF_PLUGIN_DIR . 'includes/admin/class-clients-list-table.php';
require_once BBGF_PLUGIN_DIR . 'includes/admin/class-guardians-admin.php';
require_once BBGF_PLUGIN_DIR . 'includes/admin/class-guardians-list-table.php';
require_once BBGF_PLUGIN_DIR . 'includes/admin/class-services-admin.php';
require_once BBGF_PLUGIN_DIR . 'includes/admin/class-services-list-table.php';
require_once BBGF_PLUGIN_DIR . 'includes/admin/class-packages-admin.php';
require_once BBGF_PLUGIN_DIR . 'includes/admin/class-packages-list-table.php';
require_once BBGF_PLUGIN_DIR . 'includes/admin/class-stages-admin.php';
require_once BBGF_PLUGIN_DIR . 'includes/admin/class-views-admin.php';
require_once BBGF_PLUGIN_DIR . 'includes/admin/class-views-list-table.php';
require_once BBGF_PLUGIN_DIR . 'includes/admin/class-settings-admin.php';
require_once BBGF_PLUGIN_DIR . 'includes/admin/class-notifications-admin.php';
require_once BBGF_PLUGIN_DIR . 'includes/admin/class-notifications-list-table.php';
require_once BBGF_PLUGIN_DIR . 'includes/admin/class-notification-triggers-admin.php';
require_once BBGF_PLUGIN_DIR . 'includes/admin/class-notification-triggers-list-table.php';
require_once BBGF_PLUGIN_DIR . 'includes/admin/class-notification-logs-admin.php';
require_once BBGF_PLUGIN_DIR . 'includes/admin/class-notification-logs-list-table.php';
require_once BBGF_PLUGIN_DIR . 'includes/notifications/class-notifications-service.php';
require_once BBGF_PLUGIN_DIR . 'includes/data/class-visit-service.php';

/**
 * Main plugin class handling lifecycle events and hooks.
 */
final class Plugin {
	// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	/**
	 * Plugin-specific capabilities keyed by slug.
	 *
	 * @var string[]
	 */
	private const CAPABILITIES = array(
		'bbgf_view_board',
		'bbgf_move_stages',
		'bbgf_edit_visits',
		'bbgf_manage_views',
		'bbgf_manage_services',
		'bbgf_manage_flags',
		'bbgf_manage_notifications',
		'bbgf_view_reports',
		'bbgf_manage_settings',
	);

	/**
	 * Role to capability assignments.
	 *
	 * @var array<string, string[]>
	 */
	private const ROLE_MAP = array(
		'bb_manager'   => self::CAPABILITIES,
		'bb_reception' => array(
			'bbgf_view_board',
			'bbgf_move_stages',
			'bbgf_edit_visits',
			'bbgf_view_reports',
		),
		'bb_bather'    => array(
			'bbgf_view_board',
			'bbgf_move_stages',
			'bbgf_edit_visits',
		),
		'bb_groomer'   => array(
			'bbgf_view_board',
			'bbgf_move_stages',
			'bbgf_edit_visits',
		),
		'bb_lobby'     => array(
			'bbgf_view_board',
		),
	);

	/**
	 * Asset handle for the placeholder board bundle.
	 */
	private const BOARD_ASSET_HANDLE = 'bbgf-board';

	/**
	 * Option key for storing database version.
	 */
	private const OPTION_DB_VERSION = 'bbgf_db_version';

	/**
	 * Cached table map.
	 *
	 * @var array<string,string>
	 */
	private array $table_names = array();

	/**
	 * Cached plugin settings.
	 *
	 * @var array<string,mixed>
	 */
	private array $settings = array();

	/**
	 * Tracks whether the Elementor config stub has been injected.
	 *
	 * @var bool
	 */
	private bool $elementor_config_stub_added = false;

	/**
	 * Flags admin handler.
	 *
	 * @var Flags_Admin
	 */
	private Flags_Admin $flags_admin;

	/**
	 * Clients admin handler.
	 *
	 * @var Clients_Admin
	 */
	private Clients_Admin $clients_admin;

	/**
	 * Guardians admin handler.
	 *
	 * @var Guardians_Admin
	 */
	private Guardians_Admin $guardians_admin;

	/**
	 * Services admin handler.
	 *
	 * @var Services_Admin
	 */
	private Services_Admin $services_admin;

	/**
	 * Packages admin handler.
	 *
	 * @var Packages_Admin
	 */
	private Packages_Admin $packages_admin;

	/**
	 * Stages admin handler.
	 *
	 * @var Stages_Admin
	 */
	private Stages_Admin $stages_admin;

	/**
	 * Views admin handler.
	 *
	 * @var Views_Admin
	 */
	private Views_Admin $views_admin;

	/**
	 * Settings admin handler.
	 *
	 * @var Settings_Admin
	 */
	private Settings_Admin $settings_admin;
	/**
	 * Notifications admin handler.
	 *
	 * @var Notifications_Admin
	 */
	private Notifications_Admin $notifications_admin;
	/**
	 * Notification triggers admin handler.
	 *
	 * @var Notification_Triggers_Admin
	 */
	private Notification_Triggers_Admin $notification_triggers_admin;

	/**
	 * Notification logs admin handler.
	 *
	 * @var Notification_Logs_Admin
	 */
	private Notification_Logs_Admin $notification_logs_admin;
	/**
	 * Notifications service.
	 *
	 * @var Notifications_Service
	 */
	private Notifications_Service $notifications;
	/**
	 * Visit domain service.
	 *
	 * @var Visit_Service
	 */
	private Visit_Service $visit_service;

	/**
	 * Holds the singleton instance.
	 *
	 * @var Plugin|null
	 */
	private static ?Plugin $instance = null;

	/**
	 * Tracks whether Elementor hooks are registered.
	 *
	 * @var bool
	 */
	private bool $elementor_bootstrapped = false;

	/**
	 * Plugin constructor.
	 */
	private function __construct() {
		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
		add_action( 'init', array( $this, 'register_roles_and_capabilities' ) );
		add_action( 'init', array( $this, 'register_assets' ) );
		add_action( 'init', array( $this, 'maybe_upgrade_database' ), 5 );
		add_action( 'admin_menu', array( $this, 'register_admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'maybe_enqueue_admin_assets' ) );
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
		add_shortcode( 'bbgf_board', array( $this, 'render_board_shortcode' ) );

		$this->flags_admin                 = new Flags_Admin( $this );
		$this->clients_admin               = new Clients_Admin( $this );
		$this->guardians_admin             = new Guardians_Admin( $this );
		$this->services_admin              = new Services_Admin( $this );
		$this->packages_admin              = new Packages_Admin( $this );
		$this->stages_admin                = new Stages_Admin( $this );
		$this->views_admin                 = new Views_Admin( $this );
		$this->settings_admin              = new Settings_Admin( $this );
		$this->notifications_admin         = new Notifications_Admin( $this );
		$this->notification_triggers_admin = new Notification_Triggers_Admin( $this );
		$this->notification_logs_admin     = new Notification_Logs_Admin( $this );
		$this->notifications               = new Notifications_Service( $this );
		$this->visit_service               = new Visit_Service( $this );

		$this->flags_admin->register();
		$this->clients_admin->register();
		$this->guardians_admin->register();
		$this->services_admin->register();
		$this->packages_admin->register();
		$this->stages_admin->register();
		$this->views_admin->register();
		$this->settings_admin->register();
		$this->notifications_admin->register();
		$this->notification_triggers_admin->register();
		$this->notification_logs_admin->register();
		$this->notifications->register();

		if ( did_action( 'elementor/loaded' ) ) {
			$this->bootstrap_elementor();
		} else {
			add_action( 'elementor/loaded', array( $this, 'bootstrap_elementor' ) );
		}

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			$this->register_cli_commands();
		}
	}

	/**
	 * Returns the singleton instance.
	 *
	 * @return Plugin
	 */
	public static function instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Activation hook callback.
	 */
	public static function activate(): void {
		$instance = self::instance();
		$instance->register_roles_and_capabilities();
		$instance->install_database();
		flush_rewrite_rules();
	}

	/**
	 * Deactivation hook callback.
	 */
	public static function deactivate(): void {
		self::instance()->remove_roles_and_capabilities();
		flush_rewrite_rules();
	}

	/**
	 * Load plugin translations.
	 */
	public function load_textdomain(): void {
		load_plugin_textdomain( 'bb-groomflow', false, dirname( plugin_basename( BBGF_PLUGIN_FILE ) ) . '/languages/' );
	}

	/**
	 * Ensure plugin roles and capabilities exist.
	 */
	public function register_roles_and_capabilities(): void {
		foreach ( self::ROLE_MAP as $role_key => $caps ) {
			$role = get_role( $role_key );

			if ( ! $role ) {
				$role = add_role(
					$role_key,
					$this->get_role_label( $role_key ),
					array( 'read' => true )
				);
			}

			if ( ! $role ) {
				continue;
			}

			foreach ( self::CAPABILITIES as $capability ) {
				if ( in_array( $capability, $caps, true ) ) {
					$role->add_cap( $capability );
				} else {
					$role->remove_cap( $capability );
				}
			}
		}

		$administrator = get_role( 'administrator' );
		if ( $administrator ) {
			foreach ( self::CAPABILITIES as $capability ) {
				$administrator->add_cap( $capability );
			}
		}
	}

	/**
	 * Retrieve a localized label for plugin-defined roles.
	 *
	 * @param string $role_key Role identifier.
	 * @return string
	 */
	private function get_role_label( string $role_key ): string {
		switch ( $role_key ) {
			case 'bb_manager':
				return translate_user_role( __( 'GroomFlow Manager', 'bb-groomflow' ) );
			case 'bb_reception':
				return translate_user_role( __( 'GroomFlow Reception', 'bb-groomflow' ) );
			case 'bb_bather':
				return translate_user_role( __( 'GroomFlow Bather', 'bb-groomflow' ) );
			case 'bb_groomer':
				return translate_user_role( __( 'GroomFlow Groomer', 'bb-groomflow' ) );
			case 'bb_lobby':
				return translate_user_role( __( 'GroomFlow Lobby Display', 'bb-groomflow' ) );
			default:
				$readable = ucwords( str_replace( array( 'bb_', '_' ), array( '', ' ' ), $role_key ) );
				return translate_user_role( $readable );
		}
	}

	/**
	 * Remove custom roles and capabilities on deactivation.
	 */
	private function remove_roles_and_capabilities(): void {
		foreach ( array_keys( self::ROLE_MAP ) as $role_key ) {
			remove_role( $role_key );
		}

		$administrator = get_role( 'administrator' );
		if ( $administrator ) {
			foreach ( self::CAPABILITIES as $capability ) {
				$administrator->remove_cap( $capability );
			}
		}
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
	 * Install or upgrade the database schema when needed.
	 */
	public function maybe_upgrade_database(): void {
		$current = get_option( self::OPTION_DB_VERSION );
		$this->upgrade_database( (string) $current );

		if ( BBGF_DB_VERSION === $current ) {
			return;
		}

		$this->install_database();
	}

	/**
	 * Run incremental migrations before dbDelta.
	 *
	 * @param string $current_version Stored database version (may be empty on first install).
	 */
	private function upgrade_database( string $current_version ): void {
		global $wpdb;

		$normalized_version = $current_version;
		if ( '' === $normalized_version ) {
			$normalized_version = '0';
		}

		$tables = Schema::get_table_names( $wpdb );

		if ( version_compare( $normalized_version, '1.4.0', '<' ) && isset( $tables['notification_triggers'] ) ) {
			$triggers_table = $tables['notification_triggers'];

			if ( ! $this->column_exists( $wpdb, $triggers_table, 'recipient_type' ) ) {
				$wpdb->query( sprintf( "ALTER TABLE %s ADD COLUMN recipient_type VARCHAR(32) NOT NULL DEFAULT 'guardian_primary' AFTER enabled", $triggers_table ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.SchemaChange.SchemaChange
			}

			if ( ! $this->column_exists( $wpdb, $triggers_table, 'recipient_email' ) ) {
				$wpdb->query( sprintf( "ALTER TABLE %s ADD COLUMN recipient_email VARCHAR(191) NOT NULL DEFAULT '' AFTER recipient_type", $triggers_table ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.SchemaChange.SchemaChange
			}
		}

		// Reset cached table names so subsequent calls use the latest schema mapping.
		$this->table_names = array();
	}

	/**
	 * Ensure plugin tables exist and seed default data.
	 */
	private function install_database(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		foreach ( Schema::get_table_sql( $wpdb ) as $statement ) {
			dbDelta( $statement );
		}

		update_option( self::OPTION_DB_VERSION, BBGF_DB_VERSION );

		$this->seed_default_data( $wpdb );
	}

	// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
	/**
	 * Seed baseline records if tables are empty.
	 *
	 * @param wpdb $wpdb Database instance.
	 */
	private function seed_default_data( wpdb $wpdb ): void {
		$tables = Schema::get_table_names( $wpdb );

		$this->seed_default_flags( $wpdb, $tables );
		$this->seed_default_services( $wpdb, $tables );
		$this->seed_default_stages( $wpdb, $tables );
		$this->seed_default_view( $wpdb, $tables );
	}

	/**
	 * Determine if a table exists.
	 *
	 * @param wpdb   $wpdb  Database instance.
	 * @param string $table Fully-qualified table name.
	 * @return bool
	 */
	private function table_exists( wpdb $wpdb, string $table ): bool {
		$like = $wpdb->esc_like( $table );
		return (bool) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $like ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
	}

	/**
	 * Determine if a column exists on a table.
	 *
	 * @param wpdb   $wpdb   Database instance.
	 * @param string $table  Table name.
	 * @param string $column Column name.
	 * @return bool
	 */
	private function column_exists( wpdb $wpdb, string $table, string $column ): bool {
		$query = sprintf(
			'SHOW COLUMNS FROM %s LIKE %%s',
			$table
		);

		return (bool) $wpdb->get_var( $wpdb->prepare( $query, $column ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
	}

	/**
	 * Determine if an index exists on a table.
	 *
	 * @param wpdb   $wpdb  Database instance.
	 * @param string $table Table name.
	 * @param string $index Index name.
	 * @return bool
	 */
	private function index_exists( wpdb $wpdb, string $table, string $index ): bool {
		$query = sprintf(
			'SHOW INDEX FROM %s WHERE Key_name = %%s',
			$table
		);

		return (bool) $wpdb->get_var( $wpdb->prepare( $query, $index ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
	}

	/**
	 * Insert starter behaviour flags.
	 *
	 * @param wpdb  $wpdb   Database instance.
	 * @param array $tables Table map.
	 */
	private function seed_default_flags( wpdb $wpdb, array $tables ): void {
		$count_query = sprintf(
			'SELECT COUNT(*) FROM %s WHERE 1 = %%d',
			$tables['flags']
		);
		$count       = (int) $wpdb->get_var(
			$wpdb->prepare( $count_query, 1 )
		);
		if ( $count > 0 ) {
			return;
		}

		$now   = $this->now();
		$flags = array(
			array(
				'name'        => 'VIP',
				'slug'        => 'vip',
				'emoji'       => '🌟',
				'color'       => '#fbbf24',
				'severity'    => 'medium',
				'description' => 'Very important pup or preferred client.',
			),
			array(
				'name'        => 'DNP without owner',
				'slug'        => 'dnp-without-owner',
				'emoji'       => '🚨',
				'color'       => '#ef4444',
				'severity'    => 'high',
				'description' => 'Do not proceed unless guardian is present.',
			),
		);

		foreach ( $flags as $flag ) {
			$wpdb->insert(
				$tables['flags'],
				array_merge(
					$flag,
					array(
						'created_at' => $now,
						'updated_at' => $now,
					)
				),
				array(
					'%s', // name.
					'%s', // slug.
					'%s', // emoji.
					'%s', // color.
					'%s', // severity.
					'%s', // description.
					'%s',
					'%s',
				)
			);
		}
	}

	/**
	 * Insert starter services and packages.
	 *
	 * @param wpdb  $wpdb   Database instance.
	 * @param array $tables Table map.
	 */
	private function seed_default_services( wpdb $wpdb, array $tables ): void {
		$count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$tables['services']}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( $count > 0 ) {
			return;
		}

		$now      = $this->now();
		$services = array(
			array(
				'name'             => 'Full Groom',
				'slug'             => 'full-groom',
				'icon'             => '✂️',
				'color'            => '#6366f1',
				'duration_minutes' => 120,
				'price'            => null,
				'description'      => 'Complete groom with styling and finishing touches.',
			),
			array(
				'name'             => 'Spa Bath',
				'slug'             => 'spa-bath',
				'icon'             => '🫧',
				'color'            => '#22d3ee',
				'duration_minutes' => 60,
				'price'            => null,
				'description'      => 'Gentle cleanse with aromatherapy products.',
			),
			array(
				'name'             => 'Teeth Polish',
				'slug'             => 'teeth-polish',
				'icon'             => '🦷',
				'color'            => '#38bdf8',
				'duration_minutes' => 15,
				'price'            => null,
				'description'      => 'Teeth polish and breath refresher.',
			),
		);

		foreach ( $services as $service ) {
			$wpdb->insert(
				$tables['services'],
				array_merge(
					$service,
					array(
						'created_at' => $now,
						'updated_at' => $now,
					)
				),
				array(
					'%s',
					'%s',
					'%s',
					'%s',
					'%d',
					'%f',
					'%s',
					'%s',
					'%s',
				)
			);
		}

		// Seed starter package linking to services where possible.
		$package_exists = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$tables['service_packages']}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( 0 === $package_exists ) {
			$wpdb->insert(
				$tables['service_packages'],
				array(
					'name'        => 'Day Spa Refresh',
					'slug'        => 'day-spa-refresh',
					'icon'        => '🧴',
					'color'       => '#f472b6',
					'price'       => null,
					'description' => 'Spa bath followed by coat conditioning and polish.',
					'created_at'  => $now,
					'updated_at'  => $now,
				),
				array(
					'%s',
					'%s',
					'%s',
					'%s',
					'%f',
					'%s',
					'%s',
					'%s',
				)
			);

			$package_id = (int) $wpdb->insert_id;
			if ( $package_id > 0 ) {
				$service_rows = $wpdb->get_results( "SELECT id, slug FROM {$tables['services']}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$bindings     = array(
					'spa-bath'     => 0,
					'teeth-polish' => 1,
				);
				foreach ( $service_rows as $row ) {
					if ( isset( $bindings[ $row->slug ] ) ) {
						$wpdb->insert(
							$tables['service_package_items'],
							array(
								'package_id' => $package_id,
								'service_id' => $row->id,
								'sort_order' => $bindings[ $row->slug ],
							),
							array( '%d', '%d', '%d' )
						);
					}
				}
			}
		}
	}

	/**
	 * Insert canonical stage definitions if missing.
	 *
	 * @param wpdb  $wpdb   Database instance.
	 * @param array $tables Table map.
	 */
	private function seed_default_stages( wpdb $wpdb, array $tables ): void {
		$count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$tables['stages']}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( $count > 0 ) {
			return;
		}

		$now    = $this->now();
		$stages = array(
			array(
				'stage_key'              => 'check-in',
				'label'                  => 'Check-In',
				'description'            => 'Greeting the guardian, confirming services, and capturing notes.',
				'capacity_soft_limit'    => 4,
				'capacity_hard_limit'    => 6,
				'timer_threshold_green'  => 20,
				'timer_threshold_yellow' => 40,
				'timer_threshold_red'    => 60,
				'sort_order'             => 1,
			),
			array(
				'stage_key'              => 'bath',
				'label'                  => 'Bath & Dry',
				'description'            => 'Spa bath, blow dry, paw prep.',
				'capacity_soft_limit'    => 5,
				'capacity_hard_limit'    => 7,
				'timer_threshold_green'  => 45,
				'timer_threshold_yellow' => 75,
				'timer_threshold_red'    => 105,
				'sort_order'             => 2,
			),
			array(
				'stage_key'              => 'grooming',
				'label'                  => 'Grooming',
				'description'            => 'Full styling, trims, and detailing.',
				'capacity_soft_limit'    => 4,
				'capacity_hard_limit'    => 6,
				'timer_threshold_green'  => 60,
				'timer_threshold_yellow' => 90,
				'timer_threshold_red'    => 130,
				'sort_order'             => 3,
			),
			array(
				'stage_key'              => 'ready',
				'label'                  => 'Ready for Pickup',
				'description'            => 'Final touch-ups and checkout prep.',
				'capacity_soft_limit'    => 3,
				'capacity_hard_limit'    => 5,
				'timer_threshold_green'  => 15,
				'timer_threshold_yellow' => 30,
				'timer_threshold_red'    => 60,
				'sort_order'             => 4,
			),
		);

		foreach ( $stages as $stage ) {
			$wpdb->insert(
				$tables['stages'],
				array_merge(
					$stage,
					array(
						'created_at' => $now,
						'updated_at' => $now,
					)
				),
				array(
					'%s',
					'%s',
					'%s',
					'%d',
					'%d',
					'%d',
					'%d',
					'%d',
					'%d',
					'%s',
					'%s',
				)
			); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		}
	}

	/**
	 * Create the default day view and stages.
	 *
	 * @param wpdb  $wpdb   Database instance.
	 * @param array $tables Table map.
	 */
	private function seed_default_view( wpdb $wpdb, array $tables ): void {
		$slug     = 'day-flow';
		$existing = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$tables['views']} WHERE slug = %s", $slug ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$now      = $this->now();

		if ( $existing ) {
			return;
		}

		$wpdb->insert(
			$tables['views'],
			array(
				'name'              => 'Day Flow',
				'slug'              => $slug,
				'type'              => 'internal',
				'allow_switcher'    => 1,
				'refresh_interval'  => 60,
				'show_guardian'     => 1,
				'public_token_hash' => '',
				'settings'          => wp_json_encode( array() ),
				'created_at'        => $now,
				'updated_at'        => $now,
			),
			array(
				'%s',
				'%s',
				'%s',
				'%d',
				'%d',
				'%d',
				'%s',
				'%s',
				'%s',
				'%s',
			)
		);

		$view_id = (int) $wpdb->insert_id;
		if ( $view_id <= 0 ) {
			return;
		}

			$stage_query = sprintf(
				'SELECT stage_key, label, capacity_soft_limit, capacity_hard_limit, timer_threshold_green, timer_threshold_yellow, timer_threshold_red
				FROM %s
				WHERE 1 = %%d
				ORDER BY sort_order ASC, id ASC',
				$tables['stages']
			);
			$stage_rows  = $wpdb->get_results(
				$wpdb->prepare( $stage_query, 1 ),
				ARRAY_A
			);

		if ( empty( $stage_rows ) ) {
			$stage_rows = array(
				array(
					'stage_key'              => 'check-in',
					'label'                  => 'Check-In',
					'capacity_soft_limit'    => 4,
					'capacity_hard_limit'    => 6,
					'timer_threshold_green'  => 20,
					'timer_threshold_yellow' => 40,
					'timer_threshold_red'    => 60,
				),
			);
		}

		$position = 0;
		foreach ( $stage_rows as $stage ) {
			++$position;
			$wpdb->insert(
				$tables['view_stages'],
				array(
					'view_id'                => $view_id,
					'stage_key'              => $stage['stage_key'],
					'label'                  => $stage['label'],
					'sort_order'             => $position,
					'capacity_soft_limit'    => (int) ( $stage['capacity_soft_limit'] ?? 0 ),
					'capacity_hard_limit'    => (int) ( $stage['capacity_hard_limit'] ?? 0 ),
					'timer_threshold_green'  => (int) ( $stage['timer_threshold_green'] ?? 0 ),
					'timer_threshold_yellow' => (int) ( $stage['timer_threshold_yellow'] ?? 0 ),
					'timer_threshold_red'    => (int) ( $stage['timer_threshold_red'] ?? 0 ),
				),
				array( '%d', '%s', '%s', '%d', '%d', '%d', '%d', '%d', '%d' )
			);
		}
	}

	/**
	 * Helper to get the current timestamp.
	 *
	 * @return string
	 */
	public function now(): string {
		return current_time( 'mysql' );
	}

	/**
	 * Accessor for the notifications service.
	 *
	 * @return Notifications_Service
	 */
	public function notifications_service(): Notifications_Service {
		return $this->notifications;
	}
	/**
	 * Accessor for the visit service.
	 *
	 * @return Visit_Service
	 */
	public function visit_service(): Visit_Service {
		return $this->visit_service;
	}
	/**
	 * Generate unique slug for a table based on name.
	 *
	 * @param string $name       Raw name.
	 * @param string $table      Table name.
	 * @param string $column     Column name for slug.
	 * @param int    $exclude_id Optional ID to exclude from uniqueness check.
	 * @return string
	 */
	public function unique_slug( string $name, string $table, string $column = 'slug', int $exclude_id = 0 ): string {
		global $wpdb;

		$base_slug = sanitize_title( $name );
		if ( '' === $base_slug ) {
			$base_slug = uniqid( 'bbgf-' );
		}

		$slug   = $base_slug;
		$index  = 1;
		$column = sanitize_key( $column );

		do {
			$sql  = "SELECT COUNT(*) FROM {$table} WHERE {$column} = %s";
			$args = array( $slug );

			if ( $exclude_id > 0 ) {
				$sql   .= ' AND id <> %d';
				$args[] = $exclude_id;
			}

				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
				$exists = (int) $wpdb->get_var(
					// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared
					$wpdb->prepare( $sql, ...$args )
				);
			if ( 0 === $exists ) {
				break;
			}
			$slug = sprintf( '%s-%d', $base_slug, ++$index );
		} while ( $index < 50 );

		return $slug;
	}

	/**
	 * Register the primary admin menu shell.
	 */
	public function register_admin_menu(): void {
		add_menu_page(
			__( 'GroomFlow Dashboard', 'bb-groomflow' ),
			__( 'GroomFlow', 'bb-groomflow' ),
			'bbgf_view_board', // phpcs:ignore WordPress.WP.Capabilities.Unknown -- Custom capability registered on activation.
			'bbgf-dashboard',
			array( $this, 'render_dashboard_page' ),
			'dashicons-pets',
			3
		);

			add_submenu_page(
				'bbgf-dashboard',
				__( 'Dashboard', 'bb-groomflow' ),
				__( 'Dashboard', 'bb-groomflow' ),
				'bbgf_view_board', // phpcs:ignore WordPress.WP.Capabilities.Unknown -- Custom capability registered on activation.
				'bbgf-dashboard',
				array( $this, 'render_dashboard_page' )
			);

		$this->register_stub_submenu(
			'bbgf-reports',
			__( 'Reports', 'bb-groomflow' ),
			__( 'Reports', 'bb-groomflow' ),
			'bbgf_view_reports',
			__( 'KPI dashboards and exports are scheduled for later sprints. Expect time-per-stage insights and CSV snapshots.', 'bb-groomflow' )
		);
	}

	/**
	 * Render placeholder overview page.
	 */
	public function render_dashboard_page(): void {
		$default_view = $this->visit_service()->get_default_view();
		$active_view  = is_array( $default_view ) ? sanitize_key( (string) ( $default_view['slug'] ?? '' ) ) : '';

		$this->enqueue_board_assets(
			array(
				'view' => $active_view,
			)
		);

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'Bubbles & Bows GroomFlow', 'bb-groomflow' ) . '</h1>';
		echo '<p class="description">' . esc_html__( 'Preview the calming GroomFlow Kanban experience. Upcoming sprints wire this shell into live data, drag-and-drop, and notifications.', 'bb-groomflow' ) . '</p>';
		echo $this->get_placeholder_board_markup( array( 'active_view' => $active_view ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Markup escaped within the renderer.
		echo '</div>';
	}

	/**
	 * Conditionally enqueue assets on the GroomFlow dashboard.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function maybe_enqueue_admin_assets( string $hook ): void {
		if ( 'toplevel_page_bbgf-dashboard' !== $hook ) {
			return;
		}

		$this->enqueue_board_assets();
	}

	/**
	 * Enqueue board assets for the shortcode or admin dashboard.
	 *
	 * @param array<string,mixed> $context Rendering context (view slug, public token, etc).
	 */
	private function enqueue_board_assets( array $context = array() ): void {
		if (
			! $this->elementor_config_stub_added
			&& (
				wp_script_is( 'elementor-frontend', 'registered' )
				|| wp_script_is( 'elementor-frontend', 'enqueued' )
			)
		) {
			$stub = 'window.elementorFrontendConfig = window.elementorFrontendConfig || {' .
				'environment: { mode: "production", isScriptDebug: false, is_rtl: false },' .
				'i18n: {},' .
				'urls: {},' .
				'settings: { page: [], general: [] },' .
				'kit: {},' .
				'post: {}' .
			'};';
			wp_add_inline_script( 'elementor-frontend', $stub, 'before' );
			$this->elementor_config_stub_added = true;
		}

		if ( wp_script_is( self::BOARD_ASSET_HANDLE, 'registered' ) ) {
			wp_localize_script( self::BOARD_ASSET_HANDLE, 'bbgfBoardSettings', $this->get_board_bootstrap_settings( $context ) );
			wp_enqueue_script( self::BOARD_ASSET_HANDLE );
		}

		if ( wp_style_is( self::BOARD_ASSET_HANDLE, 'registered' ) ) {
			wp_enqueue_style( self::BOARD_ASSET_HANDLE );
		}
	}

	/**
	 * Register Elementor integration hooks.
	 */
	public function bootstrap_elementor(): void {
		if ( $this->elementor_bootstrapped ) {
			return;
		}

		if ( ! class_exists( '\Elementor\Plugin' ) || ! class_exists( '\Elementor\Widget_Base' ) ) {
			return;
		}

		$this->elementor_bootstrapped = true;

		add_action( 'elementor/widgets/register', array( $this, 'register_elementor_widget' ) );
		add_action( 'elementor/widgets/widgets_registered', array( $this, 'register_elementor_widget' ) );
		add_action( 'elementor/elements/categories_registered', array( $this, 'register_elementor_category' ) );
	}

	/**
	 * Register the GroomFlow widget with Elementor.
	 *
	 * @param \Elementor\Widgets_Manager $widgets_manager Elementor widgets manager.
	 */
	public function register_elementor_widget( $widgets_manager = null ): void {
		require_once BBGF_PLUGIN_DIR . 'includes/elementor/class-board-widget.php';

		if ( null === $widgets_manager ) {
			$widgets_manager = ElementorPlugin::instance()->widgets_manager;
		}

		if ( ! $widgets_manager || ! class_exists( Board_Widget::class ) ) {
			return;
		}

		if ( method_exists( $widgets_manager, 'is_widget_registered' ) && $widgets_manager->is_widget_registered( 'bbgf_board' ) ) {
			return;
		}

		if ( method_exists( $widgets_manager, 'get_widget_types' ) ) {
			$types = $widgets_manager->get_widget_types();
			if ( isset( $types['bbgf_board'] ) ) {
				return;
			}
		}

		if ( method_exists( $widgets_manager, 'register' ) ) {
			$widgets_manager->register( new Board_Widget() );
		} elseif ( method_exists( $widgets_manager, 'register_widget_type' ) ) {
			$widgets_manager->register_widget_type( new Board_Widget() );
		}
	}

	/**
	 * Add the GroomFlow category for Elementor widgets.
	 *
	 * @param \Elementor\Elements_Manager $elements_manager Elementor elements manager.
	 */
	public function register_elementor_category( $elements_manager = null ): void {
		if ( null === $elements_manager ) {
			$elements_manager = ElementorPlugin::instance()->elements_manager;
		}

		if ( method_exists( $elements_manager, 'add_category' ) ) {
			$elements_manager->add_category(
				'bbgf',
				array(
					'title' => __( 'GroomFlow', 'bb-groomflow' ),
					'icon'  => 'eicon-favorite',
				)
			);
		}
	}

	/**
	 * Register REST API routes.
	 */
	public function register_rest_routes(): void {
		foreach ( $this->get_rest_controllers() as $controller ) {
			$controller->register_routes();
		}
	}

	/**
	 * Instantiate REST controllers used by the plugin.
	 *
	 * @return array<int,\WP_REST_Controller>
	 */
	private function get_rest_controllers(): array {
		require_once BBGF_PLUGIN_DIR . 'includes/api/class-rest-controller.php';
		require_once BBGF_PLUGIN_DIR . 'includes/api/class-health-controller.php';
		require_once BBGF_PLUGIN_DIR . 'includes/api/class-clients-controller.php';
		require_once BBGF_PLUGIN_DIR . 'includes/api/class-guardians-controller.php';
		require_once BBGF_PLUGIN_DIR . 'includes/api/class-services-controller.php';
		require_once BBGF_PLUGIN_DIR . 'includes/api/class-packages-controller.php';
		require_once BBGF_PLUGIN_DIR . 'includes/api/class-flags-controller.php';
		require_once BBGF_PLUGIN_DIR . 'includes/api/class-views-controller.php';
		require_once BBGF_PLUGIN_DIR . 'includes/api/class-visits-controller.php';
		require_once BBGF_PLUGIN_DIR . 'includes/api/class-stats-controller.php';

		return array(
			new Health_Controller(),
			new Clients_Controller( $this ),
			new Guardians_Controller( $this ),
			new Services_Controller( $this ),
			new Packages_Controller( $this ),
			new Flags_Controller( $this ),
			new Views_Controller( $this ),
			new Visits_Controller( $this, $this->visit_service ),
			new Stats_Controller( $this ),
		);
	}

	/**
	 * Output a standard stub admin page.
	 *
	 * @param string $title   Page heading.
	 * @param string $message Placeholder description.
	 */
	private function render_stub_admin_page( string $title, string $message ): void {
		echo '<div class="wrap">';
		printf(
			'<h1>%s</h1>',
			esc_html( $title )
		);
		printf(
			'<p class="description">%s</p>',
			esc_html( $message )
		);

		echo '<div class="notice notice-info"><p>';
		echo esc_html__( 'Heads up: Sprint 1 focuses on data entry and CRUD tooling. These sections will unlock as schemas and admin UIs land.', 'bb-groomflow' );
		echo '</p></div>';
		echo '</div>';
	}

	/**
	 * Helper to register a stub submenu page.
	 *
	 * @param string $slug       Menu slug.
	 * @param string $page_title Page title.
	 * @param string $menu_title Menu label.
	 * @param string $capability Required capability.
	 * @param string $message    Placeholder body copy.
	 */
	private function register_stub_submenu( string $slug, string $page_title, string $menu_title, string $capability, string $message ): void {
		add_submenu_page( // phpcs:ignore WordPress.WP.Capabilities.Unknown -- Custom capabilities registered on activation.
			'bbgf-dashboard',
			$page_title,
			$menu_title,
			$capability,
			$slug,
			function () use ( $page_title, $message ) {
				$this->render_stub_admin_page( $page_title, $message );
			}
		);
	}

	/**
	 * Register WP-CLI command classes.
	 *
	 * @return void
	 */
	private function register_cli_commands(): void {
		require_once BBGF_PLUGIN_DIR . 'includes/cli/class-base-command.php';
		require_once BBGF_PLUGIN_DIR . 'includes/cli/class-visits-command.php';
		require_once BBGF_PLUGIN_DIR . 'includes/cli/class-sync-command.php';

		\BBGF\CLI\Visits_Command::register( $this );
		\BBGF\CLI\Sync_Command::register( $this );
	}

	/**
	 * Access the global wpdb instance.
	 *
	 * @return wpdb
	 */
	public function get_wpdb(): wpdb {
		global $wpdb;

		return $wpdb;
	}

	/**
	 * Retrieve table names keyed by logical identifier.
	 *
	 * @return array<string,string>
	 */
	public function get_table_names(): array {
		if ( empty( $this->table_names ) ) {
			$this->table_names = Schema::get_table_names( $this->get_wpdb() );
		}

		return $this->table_names;
	}

	/**
	 * Retrieve the default plugin settings.
	 *
	 * @return array<string,mixed>
	 */
	public function get_default_settings(): array {
		return array(
			'board'         => array(
				'poll_interval'         => 60,
				'default_soft_capacity' => 4,
				'default_hard_capacity' => 6,
				'timer_thresholds'      => array(
					'green'  => 20,
					'yellow' => 45,
					'red'    => 60,
				),
				'accent_color'          => '#6366f1',
				'background_color'      => '#f1f5f9',
			),
			'lobby'         => array(
				'mask_guardian'     => 1,
				'show_client_photo' => 1,
				'enable_fullscreen' => 1,
			),
			'notifications' => array(
				'enable_stage_notifications' => 0,
				'from_name'                  => get_bloginfo( 'name' ),
				'from_email'                 => get_bloginfo( 'admin_email' ),
				'subject_prefix'             => '[GroomFlow]',
			),
			'branding'      => array(
				'primary_color' => '#1f2937',
				'accent_color'  => '#0ea5e9',
				'font_family'   => 'Inter, sans-serif',
			),
			'elementor'     => array(
				'card_style'         => 'calm',
				'stage_label_format' => 'name_timer',
			),
		);
	}

	/**
	 * Return merged plugin settings with defaults.
	 *
	 * @return array<string,mixed>
	 */
	public function get_settings(): array {
		if ( ! empty( $this->settings ) ) {
			return $this->settings;
		}

		$defaults = $this->get_default_settings();
		$stored   = get_option( 'bbgf_settings', array() );

		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		$board                     = isset( $stored['board'] ) && is_array( $stored['board'] ) ? $stored['board'] : array();
		$board                     = wp_parse_args( $board, $defaults['board'] );
		$board['timer_thresholds'] = wp_parse_args(
			isset( $board['timer_thresholds'] ) && is_array( $board['timer_thresholds'] ) ? $board['timer_thresholds'] : array(),
			$defaults['board']['timer_thresholds']
		);

		$lobby = isset( $stored['lobby'] ) && is_array( $stored['lobby'] ) ? $stored['lobby'] : array();
		$lobby = wp_parse_args( $lobby, $defaults['lobby'] );

		$notifications = isset( $stored['notifications'] ) && is_array( $stored['notifications'] ) ? $stored['notifications'] : array();
		$notifications = wp_parse_args( $notifications, $defaults['notifications'] );

		$branding = isset( $stored['branding'] ) && is_array( $stored['branding'] ) ? $stored['branding'] : array();
		$branding = wp_parse_args( $branding, $defaults['branding'] );

		$elementor = isset( $stored['elementor'] ) && is_array( $stored['elementor'] ) ? $stored['elementor'] : array();
		$elementor = wp_parse_args( $elementor, $defaults['elementor'] );

		$show_client_photo = ! empty( $lobby['show_client_photo'] );

		$settings = array(
			'board'         => array(
				'poll_interval'         => max( 15, (int) $board['poll_interval'] ),
				'default_soft_capacity' => (int) $board['default_soft_capacity'],
				'default_hard_capacity' => (int) $board['default_hard_capacity'],
				'timer_thresholds'      => array(
					'green'  => (int) $board['timer_thresholds']['green'],
					'yellow' => (int) $board['timer_thresholds']['yellow'],
					'red'    => (int) $board['timer_thresholds']['red'],
				),
				'accent_color'          => $this->sanitize_color_value( $board['accent_color'] ?? $defaults['board']['accent_color'] ),
				'background_color'      => $this->sanitize_color_value( $board['background_color'] ?? $defaults['board']['background_color'] ),
			),
			'lobby'         => array(
				'mask_guardian'     => ! empty( $lobby['mask_guardian'] ) ? 1 : 0,
				'show_client_photo' => $show_client_photo ? 1 : 0,
				'enable_fullscreen' => ! empty( $lobby['enable_fullscreen'] ) ? 1 : 0,
			),
			'notifications' => array(
				'enable_stage_notifications' => ! empty( $notifications['enable_stage_notifications'] ) ? 1 : 0,
				'from_name'                  => sanitize_text_field( $notifications['from_name'] ),
				'from_email'                 => sanitize_email( $notifications['from_email'] ),
				'subject_prefix'             => sanitize_text_field( $notifications['subject_prefix'] ),
			),
			'branding'      => array(
				'primary_color' => $this->sanitize_color_value( $branding['primary_color'] ),
				'accent_color'  => $this->sanitize_color_value( $branding['accent_color'] ),
				'font_family'   => sanitize_text_field( $branding['font_family'] ),
			),
			'elementor'     => array(
				'card_style'         => sanitize_key( $elementor['card_style'] ),
				'stage_label_format' => sanitize_key( $elementor['stage_label_format'] ),
			),
		);

		if ( $settings['board']['timer_thresholds']['yellow'] < $settings['board']['timer_thresholds']['green'] ) {
			$settings['board']['timer_thresholds']['yellow'] = $settings['board']['timer_thresholds']['green'];
		}

		if ( $settings['board']['timer_thresholds']['red'] < $settings['board']['timer_thresholds']['yellow'] ) {
			$settings['board']['timer_thresholds']['red'] = $settings['board']['timer_thresholds']['yellow'];
		}

		$this->settings = $settings;

		return $this->settings;
	}

	/**
	 * Clear cached settings so the next fetch reads from the database.
	 */
	public function refresh_settings_cache(): void {
		$this->settings = array();
	}

	/**
	 * Settings payload passed to front-end scripts.
	 *
	 * @return array<string,mixed>
	 */
	private function get_board_script_settings(): array {
		$settings = $this->get_settings();

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

		$visit_service = $this->visit_service();
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
			$presentation_mode             = ( ! empty( $visibility_config['readonly'] ) || $is_public ) ? 'display' : 'interactive';
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
				'board'    => esc_url_raw( rest_url( 'bb-groomflow/v1/board' ) ),
				'visits'   => esc_url_raw( rest_url( 'bb-groomflow/v1/visits' ) ),
				'services' => esc_url_raw( rest_url( 'bb-groomflow/v1/services' ) ),
			),
		);

		$settings['capabilities'] = array(
			'viewBoard'      => current_user_can( 'bbgf_view_board' ),
			'moveStages'     => current_user_can( 'bbgf_move_stages' ),
			'editVisits'     => current_user_can( 'bbgf_edit_visits' ),
			'manageViews'    => current_user_can( 'bbgf_manage_views' ),
			'manageServices' => current_user_can( 'bbgf_manage_services' ),
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
			'loading'           => __( 'Loading GroomFlow board…', 'bb-groomflow' ),
			'emptyColumn'       => __( 'No visits in this stage yet', 'bb-groomflow' ),
			'noVisits'          => __( 'No visits available.', 'bb-groomflow' ),
			'refresh'           => __( 'Refresh', 'bb-groomflow' ),
			'lastUpdated'       => __( 'Last updated', 'bb-groomflow' ),
			'viewSwitcher'      => __( 'Board views', 'bb-groomflow' ),
			'services'          => __( 'Services', 'bb-groomflow' ),
			'flags'             => __( 'Behavior flags', 'bb-groomflow' ),
			'notes'             => __( 'Notes', 'bb-groomflow' ),
			'checkIn'           => __( 'Check-in', 'bb-groomflow' ),
			'movePrev'          => __( 'Back', 'bb-groomflow' ),
			'moveNext'          => __( 'Next', 'bb-groomflow' ),
			'unknownClient'     => __( 'Client', 'bb-groomflow' ),
			'stageControls'     => __( 'Stage controls', 'bb-groomflow' ),
			'loadingError'      => __( 'Unable to load the board. Please refresh.', 'bb-groomflow' ),
			'modalTitle'        => __( 'Visit details', 'bb-groomflow' ),
			'modalLoading'      => __( 'Loading visit…', 'bb-groomflow' ),
			'modalClose'        => __( 'Close', 'bb-groomflow' ),
			'modalReadOnly'     => __( 'Read-only view', 'bb-groomflow' ),
			'modalSummary'      => __( 'Summary', 'bb-groomflow' ),
			'modalNotes'        => __( 'Notes', 'bb-groomflow' ),
			'modalServices'     => __( 'Services', 'bb-groomflow' ),
			'modalHistory'      => __( 'History', 'bb-groomflow' ),
			'modalPhotos'       => __( 'Photos', 'bb-groomflow' ),
			'modalSave'         => __( 'Save changes', 'bb-groomflow' ),
			'modalSaving'       => __( 'Saving…', 'bb-groomflow' ),
			'modalNoHistory'    => __( 'No history recorded yet.', 'bb-groomflow' ),
			'modalNoPhotos'     => __( 'No photos uploaded for this visit.', 'bb-groomflow' ),
			'searchPlaceholder' => __( 'Search clients, guardians, services…', 'bb-groomflow' ),
			'filterServices'    => __( 'Services', 'bb-groomflow' ),
			'filterFlags'       => __( 'Flags', 'bb-groomflow' ),
			'filterAll'         => __( 'All', 'bb-groomflow' ),
			'errorFetching'     => __( 'Unable to refresh the board. Please try again.', 'bb-groomflow' ),
			'moveSuccess'       => __( 'Visit moved.', 'bb-groomflow' ),
			'fullscreen'        => __( 'Fullscreen', 'bb-groomflow' ),
			'exitFullscreen'    => __( 'Exit fullscreen', 'bb-groomflow' ),
			'autoRefresh'       => __( 'Auto-refresh in', 'bb-groomflow' ),
			'maskedGuardian'    => __( 'Guardian hidden for lobby view', 'bb-groomflow' ),
		);

		return $settings;
	}

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

		$tables = $this->get_table_names();
		if ( empty( $tables['views'] ) ) {
			return array();
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			"SELECT id, name, slug, type, allow_switcher, show_guardian, refresh_interval
			FROM {$tables['views']}
			ORDER BY name ASC",
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

		return $views;
	}

	/**
	 * Normalize color values to hex strings.
	 *
	 * @param string $value Raw color value.
	 * @return string
	 */
	private function sanitize_color_value( string $value ): string {
		$color = sanitize_hex_color( $value );
		return $color ? $color : '';
	}

	/**
	 * Helper to build admin URLs for plugin pages.
	 *
	 * @param string $slug Menu slug.
	 * @return string
	 */
	public function admin_url( string $slug ): string {
		return admin_url( 'admin.php?page=' . $slug );
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
				'view'         => '',
				'public_token' => '',
				'mode'         => '',
				'fullscreen'   => '',
				'toolbar'      => '',
				'search'       => '',
				'filters'      => '',
				'refresh'      => '',
				'last_updated' => '',
				'countdown'    => '',
				'mask_badge'   => '',
				'view_switcher'=> '',
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
					'mode'            => 'display',
					'show_toolbar'    => false,
					'show_search'     => false,
					'show_filters'    => false,
					'show_refresh'    => false,
					'show_last_updated' => false,
					'show_countdown'  => false,
					'show_fullscreen' => true,
					'show_mask_badge' => false,
					'show_notes'      => false,
					'view_switcher'   => isset( $presentation['view_switcher'] ) ? $presentation['view_switcher'] : 'none',
				),
				$presentation
			);
		} elseif ( 'interactive' === $mode_param && ! isset( $presentation['view_switcher'] ) ) {
			$presentation['view_switcher'] = 'dropdown';
		}

		$this->enqueue_board_assets(
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
			)
		);
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
	 * Build placeholder board markup shared by the shortcode, admin preview, and Elementor widget.
	 *
	 * @param array<string,mixed> $args Additional display arguments.
	 * @return string
	 */
	public function get_placeholder_board_markup( array $args = array() ): string {
		$args         = wp_parse_args(
			$args,
			array(
				'view'         => 'day',
				'active_view'  => '',
				'public_token' => '',
			)
		);
		$view_key     = (string) $args['view'];
		$data         = $this->get_placeholder_board_data( $view_key );
		$columns      = $data['columns'];
		$active_view  = sanitize_key( (string) $args['active_view'] );
		$public_token = sanitize_text_field( (string) $args['public_token'] );
		$boot_settings = $this->get_board_bootstrap_settings(
			array(
				'view'         => $view_key,
				'public_token' => $public_token,
				'presentation' => isset( $args['presentation'] ) && is_array( $args['presentation'] ) ? $args['presentation'] : array(),
			)
		);
		$boot_view       = isset( $boot_settings['view'] ) && is_array( $boot_settings['view'] ) ? $boot_settings['view'] : array();
		$boot_visibility = isset( $boot_settings['visibility'] ) && is_array( $boot_settings['visibility'] ) ? $boot_settings['visibility'] : array();
		$boot_board      = isset( $boot_settings['initialBoard'] ) && is_array( $boot_settings['initialBoard'] ) ? $boot_settings['initialBoard'] : array();

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
		if ( isset( $boot_settings['presentation']['mode'] ) ) {
			$presentation_mode = sanitize_key( (string) $boot_settings['presentation']['mode'] );
		}

		if ( '' === $presentation_mode ) {
			$presentation_mode = ( $is_readonly || $is_public || in_array( $view_type, array( 'lobby', 'kiosk' ), true ) )
				? 'display'
				: 'interactive';
		}

		$board_mode = $presentation_mode;

		$class_names  = array( 'bbgf-board-wrapper' );

		if ( 'display' === $board_mode ) {
			$class_names[] = 'bbgf-board-wrapper--display';
		}

		ob_start();
		?>
		<div
			id="bbgf-board-root"
			class="<?php echo esc_attr( implode( ' ', array_unique( $class_names ) ) ); ?>"
			aria-live="polite"
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
					<section
						class="bbgf-column <?php echo esc_attr( $column['state_class'] ); ?>"
						role="listitem"
						aria-label="<?php echo esc_attr( $column['label'] ); ?>"
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
					$column_aria_label = sprintf( __( '%s column cards', 'bb-groomflow' ), $column['label'] );
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
									<span
										class="bbgf-card-timer"
										data-state="<?php echo esc_attr( $card['timer']['state'] ); ?>"
										data-seconds="<?php echo esc_attr( $card['timer']['seconds'] ); ?>"
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
													<span class="bbgf-service-chip" aria-hidden="true">
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
	 * Placeholder data used to render the sprint-zero Kanban preview.
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

		return array(
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
	}

	// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
}
