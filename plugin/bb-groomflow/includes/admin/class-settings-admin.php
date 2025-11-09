<?php
/**
 * Settings admin management.
 *
 * @package BB_GroomFlow
 */

namespace BBGF\Admin;

use BBGF\Plugin;

/**
 * Handles the GroomFlow settings page.
 */
class Settings_Admin implements Admin_Page_Interface {
	/**
	 * Page slug.
	 */
	public const PAGE_SLUG = 'bbgf-settings';

	/**
	 * Plugin instance.
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
	 * Register hooks.
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_init', array( $this, 'maybe_handle_form_submission' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Register submenu page.
	 */
	public function register_menu(): void {
		add_submenu_page(
			'bbgf-dashboard',
			__( 'Settings', 'bb-groomflow' ),
			__( 'Settings', 'bb-groomflow' ),
			'bbgf_manage_settings', // phpcs:ignore WordPress.WP.Capabilities.Unknown
			self::PAGE_SLUG,
			array( $this, 'render_page' )
		);
	}

	/**
	 * Handle settings submission.
	 */
	public function maybe_handle_form_submission(): void {
		if ( ! isset( $_POST['bbgf_settings_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['bbgf_settings_nonce'] ) ), 'bbgf_save_settings' ) ) {
			return;
		}

		if ( ! current_user_can( 'bbgf_manage_settings' ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown
			return;
		}

		$post_data = wp_unslash( $_POST );
		$submitted = isset( $post_data['settings'] ) ? $post_data['settings'] : array();
		$settings  = $this->sanitize_settings( $submitted );

		update_option( 'bbgf_settings', $settings, false );
		$this->plugin->refresh_settings_cache();

		wp_safe_redirect( add_query_arg( 'bbgf_message', 'settings-saved', $this->get_page_url() ) );
		exit;
	}

	/**
	 * Render the settings page.
	 */
	public function render_page(): void {
		if ( ! current_user_can( 'bbgf_manage_settings' ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown
			wp_die( esc_html__( 'You do not have permission to manage settings.', 'bb-groomflow' ) );
		}

		$settings = $this->plugin->get_settings();
		$message  = isset( $_GET['bbgf_message'] ) ? sanitize_text_field( wp_unslash( $_GET['bbgf_message'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		include __DIR__ . '/views/settings-page.php';
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook Current admin hook.
	 */
	public function enqueue_assets( string $hook ): void {
		if ( false === strpos( $hook, 'bbgf-settings' ) ) {
			return;
		}

		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_style(
			'bbgf-admin',
			BBGF_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			BBGF_VERSION
		);

		wp_enqueue_script(
			'bbgf-admin',
			BBGF_PLUGIN_URL . 'assets/js/admin.js',
			array( 'jquery', 'wp-i18n', 'wp-color-picker' ),
			BBGF_VERSION,
			true
		);
	}

	/**
	 * Helper to get admin URL.
	 */
	private function get_page_url(): string {
		return admin_url( 'admin.php?page=' . self::PAGE_SLUG );
	}

	/**
	 * Sanitize settings payload.
	 *
	 * @param mixed $input Raw input data.
	 * @return array<string,mixed>
	 */
	private function sanitize_settings( $input ): array {
		$defaults = $this->plugin->get_default_settings();
		$input    = is_array( $input ) ? $input : array();

		$board     = isset( $input['board'] ) && is_array( $input['board'] ) ? $input['board'] : array();
		$lobby     = isset( $input['lobby'] ) && is_array( $input['lobby'] ) ? $input['lobby'] : array();
		$notify    = isset( $input['notifications'] ) && is_array( $input['notifications'] ) ? $input['notifications'] : array();
		$branding  = isset( $input['branding'] ) && is_array( $input['branding'] ) ? $input['branding'] : array();
		$elementor = isset( $input['elementor'] ) && is_array( $input['elementor'] ) ? $input['elementor'] : array();

		$show_client_photo = ! empty( $lobby['show_client_photo'] );

		$sanitized = array(
			'board'         => array(
				'poll_interval'         => max( 15, absint( $board['poll_interval'] ?? $defaults['board']['poll_interval'] ) ),
				'default_soft_capacity' => absint( $board['default_soft_capacity'] ?? $defaults['board']['default_soft_capacity'] ),
				'default_hard_capacity' => absint( $board['default_hard_capacity'] ?? $defaults['board']['default_hard_capacity'] ),
				'timer_thresholds'      => array(
					'green'  => absint( $board['timer_thresholds']['green'] ?? $defaults['board']['timer_thresholds']['green'] ),
					'yellow' => absint( $board['timer_thresholds']['yellow'] ?? $defaults['board']['timer_thresholds']['yellow'] ),
					'red'    => absint( $board['timer_thresholds']['red'] ?? $defaults['board']['timer_thresholds']['red'] ),
				),
				'accent_color'          => $this->sanitize_color( $board['accent_color'] ?? $defaults['board']['accent_color'] ),
				'background_color'      => $this->sanitize_color( $board['background_color'] ?? $defaults['board']['background_color'] ),
			),
			'lobby'         => array(
				'mask_guardian'     => ! empty( $lobby['mask_guardian'] ) ? 1 : 0,
				'show_client_photo' => $show_client_photo ? 1 : 0,
				'enable_fullscreen' => ! empty( $lobby['enable_fullscreen'] ) ? 1 : 0,
			),
			'notifications' => array(
				'enable_stage_notifications' => ! empty( $notify['enable_stage_notifications'] ) ? 1 : 0,
				'from_name'                  => sanitize_text_field( $notify['from_name'] ?? $defaults['notifications']['from_name'] ),
				'from_email'                 => sanitize_email( $notify['from_email'] ?? $defaults['notifications']['from_email'] ),
				'subject_prefix'             => sanitize_text_field( $notify['subject_prefix'] ?? $defaults['notifications']['subject_prefix'] ),
			),
			'branding'      => array(
				'primary_color' => $this->sanitize_color( $branding['primary_color'] ?? $defaults['branding']['primary_color'] ),
				'accent_color'  => $this->sanitize_color( $branding['accent_color'] ?? $defaults['branding']['accent_color'] ),
				'font_family'   => sanitize_text_field( $branding['font_family'] ?? $defaults['branding']['font_family'] ),
			),
			'elementor'     => array(
				'card_style'         => sanitize_key( $elementor['card_style'] ?? $defaults['elementor']['card_style'] ),
				'stage_label_format' => sanitize_key( $elementor['stage_label_format'] ?? $defaults['elementor']['stage_label_format'] ),
			),
		);

		// Ensure timer thresholds are monotonic.
		if ( $sanitized['board']['timer_thresholds']['yellow'] < $sanitized['board']['timer_thresholds']['green'] ) {
			$sanitized['board']['timer_thresholds']['yellow'] = $sanitized['board']['timer_thresholds']['green'];
		}

		if ( $sanitized['board']['timer_thresholds']['red'] < $sanitized['board']['timer_thresholds']['yellow'] ) {
			$sanitized['board']['timer_thresholds']['red'] = $sanitized['board']['timer_thresholds']['yellow'];
		}

		return $sanitized;
	}

	/**
	 * Basic color sanitizer (hex with fallback).
	 *
	 * @param string $value Raw input.
	 */
	private function sanitize_color( string $value ): string {
		$color = sanitize_hex_color( $value );
		return $color ? $color : '';
	}
}
