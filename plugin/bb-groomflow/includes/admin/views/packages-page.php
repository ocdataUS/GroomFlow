<?php
/**
 * Packages admin page view.
 *
 * @package BB_GroomFlow
 */

use BBGF\Admin\Packages_Admin;
use BBGF\Admin\Packages_List_Table;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$editing     = is_array( $current_package );
$price_value = isset( $current_package['price'] ) && '' !== $current_package['price'] && null !== $current_package['price']
	? number_format_i18n( (float) $current_package['price'], 2 )
	: '';
$messages    = array(
	'package-created'        => __( 'Package created successfully.', 'bb-groomflow' ),
	'package-updated'        => __( 'Package updated successfully.', 'bb-groomflow' ),
	'package-deleted'        => __( 'Package deleted.', 'bb-groomflow' ),
	'package-empty-name'     => __( 'Package name is required.', 'bb-groomflow' ),
	'package-invalid-price'  => __( 'Price must be a number (leave blank if not used).', 'bb-groomflow' ),
	'package-empty-services' => __( 'Select at least one service for this package.', 'bb-groomflow' ),
);

$selected_map          = is_array( $selected_map ?? null ) ? $selected_map : array();
$selected_services_csv = implode( ',', array_keys( $selected_map ) );
?>
<div class="wrap bbgf-packages-admin">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Service Packages', 'bb-groomflow' ); ?></h1>
	<?php if ( $editing ) : ?>
		<p class="description"><?php esc_html_e( 'Editing a package. Update details and included services, then save.', 'bb-groomflow' ); ?></p>
	<?php endif; ?>

	<?php if ( ! empty( $message ) && isset( $messages[ $message ] ) ) : ?>
		<?php
		$notice_type = in_array( $message, array( 'package-empty-name', 'package-invalid-price', 'package-empty-services' ), true ) ? 'notice-error' : 'notice-success';
		printf( '<div class="notice %1$s is-dismissible"><p>%2$s</p></div>', esc_attr( $notice_type ), esc_html( $messages[ $message ] ) );
		?>
	<?php endif; ?>

	<hr class="wp-header-end" />

	<div class="bbgf-package-editor">
		<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=' . Packages_Admin::PAGE_SLUG ) ); ?>" class="bbgf-package-form">
			<?php wp_nonce_field( 'bbgf_save_package', 'bbgf_package_nonce' ); ?>
			<input type="hidden" name="package_id" value="<?php echo esc_attr( $current_package['id'] ?? 0 ); ?>" />

			<table class="form-table" role="presentation">
				<tbody>
					<tr>
						<th scope="row"><label for="bbgf-package-name"><?php esc_html_e( 'Package name', 'bb-groomflow' ); ?></label></th>
						<td><input type="text" class="regular-text" required id="bbgf-package-name" name="name" value="<?php echo esc_attr( $current_package['name'] ?? '' ); ?>" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="bbgf-package-icon"><?php esc_html_e( 'Icon / Emoji', 'bb-groomflow' ); ?></label></th>
						<td>
							<div class="bbgf-emoji-field" data-bbgf-emoji-field>
								<div class="bbgf-emoji-field__controls">
									<input type="text" class="small-text" id="bbgf-package-icon" name="icon" value="<?php echo esc_attr( $current_package['icon'] ?? '' ); ?>" maxlength="32" data-bbgf-emoji-input />
									<span data-bbgf-emoji-mount></span>
								</div>
								<p class="description"><?php esc_html_e( 'Appears on package badges and summaries throughout the admin.', 'bb-groomflow' ); ?></p>
							</div>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="bbgf-package-color"><?php esc_html_e( 'Accent color', 'bb-groomflow' ); ?></label></th>
						<td>
							<input
								type="text"
								class="bbgf-color-picker"
								id="bbgf-package-color"
								name="color"
								value="<?php echo esc_attr( $current_package['color'] ?? '' ); ?>"
								data-default-color="#f472b6"
							/>
							<p class="description"><?php esc_html_e( 'Used to tint package cards and highlights wherever this bundle appears.', 'bb-groomflow' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="bbgf-package-price"><?php esc_html_e( 'Price', 'bb-groomflow' ); ?></label></th>
						<td>
							<input type="text" class="small-text" id="bbgf-package-price" name="price" value="<?php echo esc_attr( $price_value ); ?>" />
							<p class="description"><?php esc_html_e( 'Optional. Enter a number (e.g., 120 or 120.00). Leave blank to hide pricing.', 'bb-groomflow' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="bbgf-package-description"><?php esc_html_e( 'Description', 'bb-groomflow' ); ?></label></th>
						<td><textarea id="bbgf-package-description" name="description" rows="4" class="large-text"><?php echo esc_textarea( $current_package['description'] ?? '' ); ?></textarea></td>
					</tr>
				</tbody>
			</table>

			<h2><?php esc_html_e( 'Included Services', 'bb-groomflow' ); ?></h2>
			<?php if ( empty( $services ) ) : ?>
				<div class="notice notice-warning inline"><p><?php esc_html_e( 'Add services before configuring packages.', 'bb-groomflow' ); ?></p></div>
			<?php else : ?>
				<p class="description"><?php esc_html_e( 'Select the services bundled with this package and optionally specify their display order.', 'bb-groomflow' ); ?></p>
				<table class="widefat fixed striped bbgf-package-services">
					<thead>
						<tr>
							<th scope="col" class="column-primary"><?php esc_html_e( 'Include', 'bb-groomflow' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Service', 'bb-groomflow' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Duration', 'bb-groomflow' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Price', 'bb-groomflow' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Order', 'bb-groomflow' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php
						foreach ( $services as $service ) :
							$service_id       = (int) $service['id'];
							$is_selected      = array_key_exists( $service_id, $selected_map );
							$order_value      = $is_selected ? (int) $selected_map[ $service_id ] : '';
							$duration_minutes = $service['duration_minutes'] ? (int) $service['duration_minutes'] : null;
							$price_amount     = ( '' !== $service['price'] && null !== $service['price'] ) ? (float) $service['price'] : null;
							?>
							<tr>
								<th scope="row" class="check-column">
									<label class="screen-reader-text" for="bbgf-package-service-<?php echo esc_attr( $service_id ); ?>">
										<?php
										printf(
											/* translators: %s: service name. */
											esc_html__( 'Include %s', 'bb-groomflow' ),
											esc_html( $service['name'] )
										);
										?>
									</label>
									<input type="checkbox" id="bbgf-package-service-<?php echo esc_attr( $service_id ); ?>" name="services[]" value="<?php echo esc_attr( $service_id ); ?>" <?php checked( $is_selected ); ?> />
								</th>
								<td>
									<strong><?php echo esc_html( $service['name'] ); ?></strong>
									<?php if ( ! empty( $service['icon'] ) ) : ?>
										<span class="bbgf-package-service-icon" aria-hidden="true"><?php echo esc_html( $service['icon'] ); ?></span>
									<?php endif; ?>
								</td>
								<td>
									<?php
									if ( null === $duration_minutes ) {
										echo '&mdash;';
									} else {
										echo esc_html( (string) $duration_minutes );
									}
									?>
								</td>
								<td>
									<?php
									if ( null === $price_amount ) {
										echo '&mdash;';
									} else {
										$formatted_price = '$' . number_format_i18n( $price_amount, 2 );
										echo esc_html( $formatted_price );
									}
									?>
								</td>
								<td>
									<label class="screen-reader-text" for="bbgf-package-order-<?php echo esc_attr( $service_id ); ?>">
										<?php
										printf(
											/* translators: %s: service name. */
											esc_html__( 'Order for %s', 'bb-groomflow' ),
											esc_html( $service['name'] )
										);
										?>
									</label>
									<input type="number" class="small-text" id="bbgf-package-order-<?php echo esc_attr( $service_id ); ?>" name="service_order[<?php echo esc_attr( $service_id ); ?>]" value="<?php echo esc_attr( $order_value ); ?>" min="1" />
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>

			<input type="hidden" name="services_selection" value="<?php echo esc_attr( $selected_services_csv ); ?>" data-bbgf-services-selection />
			<?php submit_button( $editing ? __( 'Update Package', 'bb-groomflow' ) : __( 'Add Package', 'bb-groomflow' ) ); ?>
		</form>
	</div>

	<form method="get" action="">
		<input type="hidden" name="page" value="<?php echo esc_attr( Packages_Admin::PAGE_SLUG ); ?>" />
		<?php $list->search_box( __( 'Search packages', 'bb-groomflow' ), 'bbgf-packages' ); ?>
		<?php $list->display(); ?>
	</form>
</div>
