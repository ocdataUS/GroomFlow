<?php
/**
 * Services admin management.
 *
 * @package BB_GroomFlow
 */

namespace BBGF\Admin;

use BBGF\Bootstrap\Admin_Menu_Service;
use BBGF\Plugin;

/**
 * Handles the Services admin screen.
 */
class Services_Admin implements Admin_Page_Interface {
	/**
	 * Page slug.
	 */
	public const PAGE_SLUG = 'bbgf-services';

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
		add_action( 'admin_init', array( $this, 'maybe_handle_delete' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Register submenu page.
	 */
	public function register_menu(): void {
		add_submenu_page(
			Admin_Menu_Service::MENU_SLUG,
			__( 'Services', 'bb-groomflow' ),
			__( 'Services', 'bb-groomflow' ),
			'bbgf_manage_services',
			self::PAGE_SLUG,
			array( $this, 'render_page' ),
			30
		);
	}

	/**
	 * Handle create/update submissions.
	 */
	public function maybe_handle_form_submission(): void {
		if ( ! isset( $_POST['bbgf_service_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['bbgf_service_nonce'] ) ), 'bbgf_save_service' ) ) {
			return;
		}

		if ( ! current_user_can( 'bbgf_manage_services' ) ) {
			return;
		}

		$post_data = wp_unslash( $_POST );

		$service_id = isset( $post_data['service_id'] ) ? absint( $post_data['service_id'] ) : 0;

		$name = isset( $post_data['name'] ) ? sanitize_text_field( $post_data['name'] ) : '';
		if ( '' === $name ) {
			wp_safe_redirect( add_query_arg( 'bbgf_message', 'service-empty-name', $this->get_page_url() ) );
			exit;
		}

		$icon_raw  = isset( $post_data['icon'] ) ? $post_data['icon'] : '';
		$color_raw = isset( $post_data['color'] ) ? $post_data['color'] : '';

		$price_raw = isset( $post_data['price'] ) ? trim( (string) $post_data['price'] ) : '';
		if ( '' !== $price_raw && ! is_numeric( $price_raw ) ) {
			wp_safe_redirect( add_query_arg( 'bbgf_message', 'service-invalid-price', $this->get_page_url() ) );
			exit;
		}

		$duration  = isset( $post_data['duration_minutes'] ) ? absint( $post_data['duration_minutes'] ) : 0;
		$price     = '' === $price_raw ? null : round( (float) $price_raw, 2 );
		$icon      = sanitize_text_field( $icon_raw );
		$color     = sanitize_hex_color( $color_raw );
		$color     = $color ? $color : '';
		$tags_raw  = isset( $post_data['tags'] ) ? sanitize_text_field( $post_data['tags'] ) : '';
		$tags_list = array_filter(
			array_map(
				static function ( $tag ) {
					$tag = sanitize_text_field( $tag );
					return '' === $tag ? null : $tag;
				},
				array_map( 'trim', explode( ',', $tags_raw ) )
			)
		);

		$service = array(
			'name'             => $name,
			'icon'             => $icon,
			'color'            => $color,
			'duration_minutes' => $duration,
			'price'            => $price,
			'description'      => isset( $post_data['description'] ) ? sanitize_textarea_field( $post_data['description'] ) : '',
			'flags'            => wp_json_encode( array_values( $tags_list ) ),
		);

		$wpdb    = $this->plugin->get_wpdb();
		$tables  = $this->plugin->get_table_names();
		$now     = $this->plugin->now();
		$message = 'service-created';

		$service['slug'] = $this->plugin->unique_slug( $service['name'], $tables['services'], 'slug', $service_id );

		$data = array_merge(
			$service,
			array(
				'updated_at' => $now,
			)
		);

		$formats = array(
			'%s', // name.
			'%s', // icon.
			'%s', // color.
			'%d', // duration_minutes.
			'%s', // price.
			'%s', // description.
			'%s', // flags.
			'%s', // slug.
			'%s', // updated_at.
		);

		if ( null === $price ) {
			unset( $data['price'] );
			unset( $formats[4] );
		}

		// Reorder formats to match data after potential price removal.
		$formats = array_values( $formats );

		if ( $service_id > 0 ) {
			$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
				$tables['services'],
				$data,
				array( 'id' => $service_id ),
				$formats,
				array( '%d' )
			);
			$message = 'service-updated';
		} else {
			$data['created_at'] = $now;
			$insert_formats     = $formats;
			$insert_formats[]   = '%s'; // created_at.

			$wpdb->insert( $tables['services'], $data, $insert_formats ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		}

		$this->plugin->visit_service()->flush_cache();

		wp_safe_redirect( add_query_arg( 'bbgf_message', $message, $this->get_page_url() ) );
		exit;
	}

	/**
	 * Handle deleting a service.
	 */
	public function maybe_handle_delete(): void {
		if ( ! isset( $_GET['page'], $_GET['action'] ) || self::PAGE_SLUG !== sanitize_text_field( wp_unslash( $_GET['page'] ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		$action = sanitize_text_field( wp_unslash( $_GET['action'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( 'delete' !== $action || ! isset( $_GET['_wpnonce'], $_GET['service_id'] ) ) {
			return;
		}

		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'bbgf_delete_service' ) ) {
			return;
		}

		if ( ! current_user_can( 'bbgf_manage_services' ) ) {
			return;
		}

		$service_id = absint( $_GET['service_id'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( $service_id <= 0 ) {
			return;
		}

		$wpdb   = $this->plugin->get_wpdb();
		$tables = $this->plugin->get_table_names();
		$wpdb->delete( $tables['services'], array( 'id' => $service_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->delete( $tables['service_package_items'], array( 'service_id' => $service_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

		$this->plugin->visit_service()->flush_cache();

		wp_safe_redirect(
			add_query_arg(
				'bbgf_message',
				'service-deleted',
				remove_query_arg( array( 'action', 'service_id', '_wpnonce' ), $this->get_page_url() )
			)
		);
		exit;
	}

	/**
	 * Render admin page.
	 */
	public function render_page(): void {
		if ( ! current_user_can( 'bbgf_manage_services' ) ) {
			wp_die( esc_html__( 'You do not have permission to manage services.', 'bb-groomflow' ) );
		}

		$wpdb        = $this->plugin->get_wpdb();
		$tables      = $this->plugin->get_table_names();
		$current_row = null;

		if ( isset( $_GET['service_id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$service_id = absint( $_GET['service_id'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( $service_id > 0 ) {
				$current_row = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
					$wpdb->prepare( "SELECT * FROM {$tables['services']} WHERE id = %d", $service_id ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					ARRAY_A
				);
			}
		}

		if ( is_array( $current_row ) && isset( $current_row['flags'] ) ) {
			$decoded = json_decode( $current_row['flags'], true );
			if ( is_array( $decoded ) ) {
				$current_row['tags'] = implode( ', ', $decoded );
			} else {
				$current_row['tags'] = '';
			}
		}

		$message = isset( $_GET['bbgf_message'] ) ? sanitize_text_field( wp_unslash( $_GET['bbgf_message'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$list = new Services_List_Table( $this->plugin );
		$list->prepare_items();

		include __DIR__ . '/views/services-page.php';
	}

	/**
	 * Helper to get admin URL.
	 */
	private function get_page_url(): string {
		return admin_url( 'admin.php?page=' . self::PAGE_SLUG );
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook Current admin hook.
	 */
	public function enqueue_assets( string $hook ): void {
		if ( false === strpos( $hook, self::PAGE_SLUG ) ) {
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
}
