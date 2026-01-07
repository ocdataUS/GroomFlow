<?php
/**
 * Admin menu bootstrap service.
 *
 * @package BB_GroomFlow
 */

namespace BBGF\Bootstrap;

use BBGF\Plugin;

/**
 * Registers the GroomFlow admin shell and renders the placeholder board.
 */
class Admin_Menu_Service {
	public const MENU_SLUG       = 'bbgf-dashboard';
	public const MENU_CAPABILITY = 'bbgf_view_board';
	public const MENU_POSITION   = 3;
	public const DASHBOARD_ORDER = 0;
	public const REPORTS_ORDER   = 80;
	private const SUBMENU_ORDER  = array(
		self::MENU_SLUG,
		'bbgf-stages',
		'bbgf-views',
		'bbgf-clients',
		'bbgf-guardians',
		'bbgf-services',
		'bbgf-packages',
		'bbgf-flags',
		'bbgf-notifications',
		'bbgf-notification-triggers',
		'bbgf-notification-logs',
		'bbgf-settings',
		'bbgf-reports',
	);

	/**
	 * Plugin instance.
	 *
	 * @var Plugin
	 */
	private Plugin $plugin;

	/**
	 * Board assets service.
	 *
	 * @var Assets_Service
	 */
	private Assets_Service $assets;

	/**
	 * Constructor.
	 *
	 * @param Plugin         $plugin Plugin instance.
	 * @param Assets_Service $assets Assets helper.
	 */
	public function __construct( Plugin $plugin, Assets_Service $assets ) {
		$this->plugin = $plugin;
		$this->assets = $assets;
	}

	/**
	 * Register admin hooks.
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'register_admin_menu' ), 5 );
		add_action( 'admin_menu', array( $this, 'sort_submenus' ), 99 );
		add_action( 'admin_enqueue_scripts', array( $this, 'maybe_enqueue_admin_assets' ) );
	}

	/**
	 * Register the primary admin menu shell.
	 */
	public function register_admin_menu(): void {
		add_menu_page(
			__( 'GroomFlow Dashboard', 'bb-groomflow' ),
			__( 'GroomFlow', 'bb-groomflow' ),
			self::MENU_CAPABILITY, // Custom capability registered on activation.
			self::MENU_SLUG,
			array( $this, 'render_dashboard_page' ),
			'dashicons-pets',
			self::MENU_POSITION
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Dashboard', 'bb-groomflow' ),
			__( 'Dashboard', 'bb-groomflow' ),
			self::MENU_CAPABILITY, // Custom capability registered on activation.
			self::MENU_SLUG,
			array( $this, 'render_dashboard_page' ),
			self::DASHBOARD_ORDER
		);

		$this->register_stub_submenu(
			'bbgf-reports',
			__( 'Reports', 'bb-groomflow' ),
			__( 'Reports', 'bb-groomflow' ),
			'bbgf_view_reports',
			__( 'KPI dashboards and exports live here; keep capability checks intact while reporting endpoints are exercised.', 'bb-groomflow' ),
			self::REPORTS_ORDER
		);
	}

	/**
	 * Render placeholder overview page.
	 */
	public function render_dashboard_page(): void {
		$default_view = $this->plugin->visit_service()->get_default_view();
		$active_view  = is_array( $default_view ) ? sanitize_key( (string) ( $default_view['slug'] ?? '' ) ) : '';

		$board_settings = $this->assets->enqueue_board_assets(
			array(
				'view' => $active_view,
			)
		);

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'Bubbles & Bows GroomFlow', 'bb-groomflow' ) . '</h1>';
		echo '<p class="description">' . esc_html__( 'Preview the calming GroomFlow Kanban experience with live data, drag-and-drop, and notifications.', 'bb-groomflow' ) . '</p>';
		echo $this->assets->get_placeholder_board_markup( array( 'active_view' => $active_view ), $board_settings ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Markup escaped within the renderer.
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
	 * @param int    $position   Optional menu position.
	 */
	private function register_stub_submenu( string $slug, string $page_title, string $menu_title, string $capability, string $message, int $position = 80 ): void {
		add_submenu_page( // Custom capabilities registered on activation.
			self::MENU_SLUG,
			$page_title,
			$menu_title,
			$capability,
			$slug,
			function () use ( $page_title, $message ) {
				$this->render_stub_admin_page( $page_title, $message );
			},
			$position
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
	 * Conditionally enqueue assets on the GroomFlow dashboard.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function maybe_enqueue_admin_assets( string $hook ): void {
		if ( 'toplevel_page_' . self::MENU_SLUG !== $hook ) {
			return;
		}

		$this->assets->enqueue_board_assets();
	}

	/**
	 * Normalize submenu ordering after all pages are registered.
	 */
	public function sort_submenus(): void {
		global $submenu;

		if ( empty( $submenu[ self::MENU_SLUG ] ) || ! is_array( $submenu[ self::MENU_SLUG ] ) ) {
			return;
		}

		$order_map = array_flip( self::SUBMENU_ORDER );

		usort(
			$submenu[ self::MENU_SLUG ],
			static function ( array $a, array $b ) use ( $order_map ) {
				$slug_a = $a[2] ?? '';
				$slug_b = $b[2] ?? '';

				$pos_a = $order_map[ $slug_a ] ?? PHP_INT_MAX;
				$pos_b = $order_map[ $slug_b ] ?? PHP_INT_MAX;

				if ( $pos_a === $pos_b ) {
					return strcasecmp( (string) ( $a[3] ?? $a[0] ?? '' ), (string) ( $b[3] ?? $b[0] ?? '' ) );
				}

				return $pos_a <=> $pos_b;
			}
		);
	}
}
