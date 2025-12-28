<?php
/**
 * Packages admin management.
 *
 * @package BB_GroomFlow
 */

namespace BBGF\Admin;

use BBGF\Bootstrap\Admin_Menu_Service;
use BBGF\Plugin;

/**
 * Handles the Packages admin screen.
 */
class Packages_Admin implements Admin_Page_Interface {
	/**
	 * Menu slug for the packages screen.
	 */
	public const PAGE_SLUG = 'bbgf-packages';

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
	 * Register WordPress hooks for the packages admin.
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_init', array( $this, 'maybe_handle_form_submission' ) );
		add_action( 'admin_init', array( $this, 'maybe_handle_delete' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Register submenu entry under the GroomFlow dashboard.
	 */
	public function register_menu(): void {
		add_submenu_page(
			Admin_Menu_Service::MENU_SLUG,
			__( 'Packages', 'bb-groomflow' ),
			__( 'Packages', 'bb-groomflow' ),
			'bbgf_manage_services', // phpcs:ignore WordPress.WP.Capabilities.Unknown
			self::PAGE_SLUG,
			array( $this, 'render_page' ),
			32
		);
	}

	/**
	 * Handle package create/update submissions.
	 */
	public function maybe_handle_form_submission(): void {
		if ( ! isset( $_POST['bbgf_package_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['bbgf_package_nonce'] ) ), 'bbgf_save_package' ) ) {
			return;
		}

		if ( ! current_user_can( 'bbgf_manage_services' ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown
			return;
		}

		$post_data  = wp_unslash( $_POST );
		$package_id = isset( $post_data['package_id'] ) ? absint( $post_data['package_id'] ) : 0;

		$name = isset( $post_data['name'] ) ? sanitize_text_field( $post_data['name'] ) : '';
		if ( '' === $name ) {
			wp_safe_redirect( add_query_arg( 'bbgf_message', 'package-empty-name', $this->get_page_url() ) );
			exit;
		}

		$price_raw = isset( $post_data['price'] ) ? trim( (string) $post_data['price'] ) : '';
		if ( '' !== $price_raw && ! is_numeric( $price_raw ) ) {
			wp_safe_redirect( add_query_arg( 'bbgf_message', 'package-invalid-price', $this->get_page_url() ) );
			exit;
		}

		$services_input = isset( $post_data['services'] ) ? array_map( 'absint', (array) $post_data['services'] ) : array();
		$services_input = array_filter( $services_input );

		$available_services = $this->get_services();
		$valid_service_ids  = array_map( 'intval', array_column( $available_services, 'id' ) );

		$selected_services = array();
		foreach ( $services_input as $service_id ) {
			if ( in_array( $service_id, $valid_service_ids, true ) ) {
				$selected_services[] = $service_id;
			}
		}

		if ( empty( $selected_services ) ) {
			$fallback_raw = isset( $post_data['services_selection'] ) ? sanitize_text_field( (string) $post_data['services_selection'] ) : '';
			$fallback_raw = trim( $fallback_raw );

			if ( '' !== $fallback_raw ) {
				$fallback_ids = array_filter(
					array_map(
						'absint',
						preg_split( '/\s*,\s*/', $fallback_raw )
					)
				);

				foreach ( $fallback_ids as $service_id ) {
					if ( in_array( $service_id, $valid_service_ids, true ) ) {
						$selected_services[] = $service_id;
					}
				}

				$selected_services = array_values( array_unique( $selected_services ) );
			}
		}

		if ( empty( $selected_services ) ) {
			wp_safe_redirect( add_query_arg( 'bbgf_message', 'package-empty-services', $this->get_page_url() ) );
			exit;
		}

		$order_input       = isset( $post_data['service_order'] ) && is_array( $post_data['service_order'] ) ? $post_data['service_order'] : array();
		$service_order_map = array();
		$position          = 0;
		foreach ( $selected_services as $service_id ) {
			$sort = isset( $order_input[ $service_id ] ) ? intval( $order_input[ $service_id ] ) : 0;
			if ( $sort <= 0 ) {
				$sort = ++$position;
			}

			$service_order_map[ $service_id ] = $sort;
		}

		asort( $service_order_map, SORT_NUMERIC );
		$ordered_services = array_keys( $service_order_map );

		$price = '' === $price_raw ? null : round( (float) $price_raw, 2 );

		$package = array(
			'name'        => $name,
			'icon'        => isset( $post_data['icon'] ) ? sanitize_text_field( $post_data['icon'] ) : '',
			'color'       => $this->sanitize_color( isset( $post_data['color'] ) ? $post_data['color'] : '' ),
			'price'       => $price,
			'description' => isset( $post_data['description'] ) ? sanitize_textarea_field( $post_data['description'] ) : '',
		);

		$wpdb    = $this->plugin->get_wpdb();
		$tables  = $this->plugin->get_table_names();
		$now     = $this->plugin->now();
		$message = 'package-created';

		$package['slug'] = $this->plugin->unique_slug( $package['name'], $tables['service_packages'], 'slug', $package_id );

		$data = array_merge(
			$package,
			array( 'updated_at' => $now )
		);

		$formats = array( '%s', '%s', '%s', '%s', '%s', '%s', '%s' );
		if ( null === $price ) {
			unset( $data['price'], $formats[3] );
		}
		$formats = array_values( $formats );

		if ( $package_id > 0 ) {
			$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
				$tables['service_packages'],
				$data,
				array( 'id' => $package_id ),
				$formats,
				array( '%d' )
			);
			$message = 'package-updated';
		} else {
			$data['created_at'] = $now;
			$insert_formats     = $formats;
			$insert_formats[]   = '%s';

			$wpdb->insert( $tables['service_packages'], $data, $insert_formats ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$package_id = (int) $wpdb->insert_id;
		}

		if ( $package_id > 0 ) {
			$wpdb->delete( $tables['service_package_items'], array( 'package_id' => $package_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

			$sort_position = 1;
			foreach ( $ordered_services as $service_id ) {
				$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
					$tables['service_package_items'],
					array(
						'package_id' => $package_id,
						'service_id' => $service_id,
						'sort_order' => $sort_position++,
					),
					array( '%d', '%d', '%d' )
				);
			}
		}

		$this->plugin->visit_service()->flush_cache();

		wp_safe_redirect( add_query_arg( 'bbgf_message', $message, $this->get_page_url() ) );
		exit;
	}

	/**
	 * Handle package deletion.
	 */
	public function maybe_handle_delete(): void {
		if ( ! isset( $_GET['page'], $_GET['action'] ) || self::PAGE_SLUG !== sanitize_text_field( wp_unslash( $_GET['page'] ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		$action = sanitize_text_field( wp_unslash( $_GET['action'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( 'delete' !== $action || ! isset( $_GET['_wpnonce'], $_GET['package_id'] ) ) {
			return;
		}

		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'bbgf_delete_package' ) ) {
			return;
		}

		if ( ! current_user_can( 'bbgf_manage_services' ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown
			return;
		}

		$package_id = absint( $_GET['package_id'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( $package_id <= 0 ) {
			return;
		}

		$wpdb   = $this->plugin->get_wpdb();
		$tables = $this->plugin->get_table_names();

		$wpdb->delete( $tables['service_packages'], array( 'id' => $package_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->delete( $tables['service_package_items'], array( 'package_id' => $package_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

		$this->plugin->visit_service()->flush_cache();

		wp_safe_redirect(
			add_query_arg(
				'bbgf_message',
				'package-deleted',
				remove_query_arg( array( 'action', 'package_id', '_wpnonce' ), $this->get_page_url() )
			)
		);
		exit;
	}

	/**
	 * Render the packages administration page.
	 */
	public function render_page(): void {
		if ( ! current_user_can( 'bbgf_manage_services' ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown
			wp_die( esc_html__( 'You do not have permission to manage packages.', 'bb-groomflow' ) );
		}

		$wpdb            = $this->plugin->get_wpdb();
		$tables          = $this->plugin->get_table_names();
		$current_package = null;
		$selected_map    = array();

		if ( isset( $_GET['package_id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$package_id = absint( $_GET['package_id'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( $package_id > 0 ) {
				$current_package = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
					$wpdb->prepare( "SELECT * FROM {$tables['service_packages']} WHERE id = %d", $package_id ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared
					ARRAY_A
				);

				if ( $current_package ) {
					$items = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
						$wpdb->prepare( "SELECT service_id, sort_order FROM {$tables['service_package_items']} WHERE package_id = %d ORDER BY sort_order ASC", $package_id ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared
						ARRAY_A
					);

					foreach ( $items as $item ) {
						$selected_map[ (int) $item['service_id'] ] = (int) $item['sort_order'];
					}
				}
			}
		}

		$services = $this->get_services();
		$message  = isset( $_GET['bbgf_message'] ) ? sanitize_text_field( wp_unslash( $_GET['bbgf_message'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$list = new Packages_List_Table( $this->plugin );
		$list->prepare_items();

		include __DIR__ . '/views/packages-page.php';
	}

	/**
	 * Generate an admin URL back to this screen.
	 */
	private function get_page_url(): string {
		return admin_url( 'admin.php?page=' . self::PAGE_SLUG );
	}

	/**
	 * Retrieve service catalog records for checkboxes.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private function get_services(): array {
		$wpdb   = $this->plugin->get_wpdb();
		$tables = $this->plugin->get_table_names();

		$services = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			sprintf( 'SELECT id, name, icon, color, duration_minutes, price FROM %s ORDER BY name ASC', $tables['services'] ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared
			ARRAY_A
		);

		return is_array( $services ) ? $services : array();
	}

	/**
	 * Sanitize a color value into a hex code.
	 *
	 * @param string $value Raw color input.
	 * @return string
	 */
	private function sanitize_color( string $value ): string {
		$sanitized = sanitize_hex_color( $value );
		return $sanitized ? $sanitized : '';
	}

	/**
	 * Enqueue shared admin assets.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_assets( string $hook ): void {
		if ( false === strpos( $hook, self::PAGE_SLUG ) ) {
			return;
		}

		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_style( 'bbgf-admin', BBGF_PLUGIN_URL . 'assets/css/admin.css', array(), BBGF_VERSION );
		wp_enqueue_script(
			'bbgf-admin',
			BBGF_PLUGIN_URL . 'assets/js/admin.js',
			array( 'jquery', 'wp-i18n', 'wp-color-picker' ),
			BBGF_VERSION,
			true
		);
	}
}
