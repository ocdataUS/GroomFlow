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
use BBGF\Bootstrap\Admin_Menu_Service;
use BBGF\Bootstrap\Assets_Service;
use BBGF\Bootstrap\Cli_Service;
use BBGF\Bootstrap\Rest_Service;
use BBGF\Notifications\Notifications_Service;
use BBGF\Data\Visit_Service;
use BBGF\Database\Schema;
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
require_once BBGF_PLUGIN_DIR . 'includes/bootstrap/class-assets-service.php';
require_once BBGF_PLUGIN_DIR . 'includes/bootstrap/class-admin-menu-service.php';
require_once BBGF_PLUGIN_DIR . 'includes/bootstrap/class-rest-service.php';
require_once BBGF_PLUGIN_DIR . 'includes/bootstrap/class-cli-service.php';

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
	 * Board/assets service.
	 *
	 * @var Assets_Service
	 */
	private Assets_Service $assets_service;
	/**
	 * Admin menu service.
	 *
	 * @var Admin_Menu_Service
	 */
	private Admin_Menu_Service $admin_menu_service;
	/**
	 * REST bootstrap service.
	 *
	 * @var Rest_Service
	 */
	private Rest_Service $rest_service;
	/**
	 * CLI service instance (only when WP-CLI is available).
	 *
	 * @var Cli_Service|null
	 */
	private ?Cli_Service $cli_service = null;
	/**
	 * Holds the singleton instance.
	 *
	 * @var Plugin|null
	 */
	private static ?Plugin $instance = null;
	/**
	 * Plugin constructor.
	 */
	private function __construct() {
		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
		add_action( 'init', array( $this, 'register_roles_and_capabilities' ) );
		add_action( 'init', array( $this, 'maybe_upgrade_database' ), 5 );

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
		$this->assets_service              = new Assets_Service( $this );
		$this->admin_menu_service          = new Admin_Menu_Service( $this, $this->assets_service );
		$this->rest_service                = new Rest_Service( $this );

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
		$this->assets_service->register();
		$this->admin_menu_service->register();
		$this->rest_service->register();

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			$this->cli_service = new Cli_Service( $this );
			$this->cli_service->register();
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
			$triggers_table        = $tables['notification_triggers'];
			$needs_recipient_type  = ! $this->column_exists( $wpdb, $triggers_table, 'recipient_type' );
			$needs_recipient_email = ! $this->column_exists( $wpdb, $triggers_table, 'recipient_email' );

			if ( $needs_recipient_type || $needs_recipient_email ) {
				require_once ABSPATH . 'wp-admin/includes/upgrade.php';

				$table_sql = Schema::get_table_sql( $wpdb );
				if ( isset( $table_sql['notification_triggers'] ) ) {
					dbDelta( $table_sql['notification_triggers'] );
				}
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
	 * Build placeholder board markup shared by the shortcode and admin preview.
	 *
	 * @param array<string,mixed> $args Additional display arguments.
	 * @return string
	 */
	public function get_placeholder_board_markup( array $args = array() ): string {
		return $this->assets_service->get_placeholder_board_markup( $args );
	}




	// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
}
