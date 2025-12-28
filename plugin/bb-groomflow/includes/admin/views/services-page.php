<?php
/**
 * Services admin page view.
 *
 * @package BB_GroomFlow
 */

use BBGF\Admin\Services_Admin;
use BBGF\Admin\Services_List_Table;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$messages = array(
	'service-created'       => __( 'Service created successfully.', 'bb-groomflow' ),
	'service-updated'       => __( 'Service updated successfully.', 'bb-groomflow' ),
	'service-deleted'       => __( 'Service deleted.', 'bb-groomflow' ),
	'service-empty-name'    => __( 'Service name is required.', 'bb-groomflow' ),
	'service-invalid-price' => __( 'Price must be a number (leave blank if not used).', 'bb-groomflow' ),
);

$editing      = is_array( $current_row );
$duration_val = $current_row['duration_minutes'] ?? '';
$price_value  = isset( $current_row['price'] ) && '' !== $current_row['price'] && null !== $current_row['price']
	? number_format_i18n( (float) $current_row['price'], 2 )
	: '';
$tags_value   = $current_row['tags'] ?? '';
?>
<div class="wrap bbgf-services-admin">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Services Catalog', 'bb-groomflow' ); ?></h1>
	<?php if ( $editing ) : ?>
	<p class="description"><?php esc_html_e( 'Editing an existing service. Update details, then save.', 'bb-groomflow' ); ?></p>
	<?php endif; ?>

	<?php if ( ! empty( $message ) && isset( $messages[ $message ] ) ) : ?>
		<?php
		$notice_type = in_array( $message, array( 'service-empty-name', 'service-invalid-price' ), true ) ? 'notice-error' : 'notice-success';
		printf( '<div class="notice %1$s is-dismissible"><p>%2$s</p></div>', esc_attr( $notice_type ), esc_html( $messages[ $message ] ) );
		?>
	<?php endif; ?>

	<hr class="wp-header-end" />

	<div class="bbgf-service-editor">
		<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=' . Services_Admin::PAGE_SLUG ) ); ?>" class="bbgf-service-form">
			<?php wp_nonce_field( 'bbgf_save_service', 'bbgf_service_nonce' ); ?>
			<input type="hidden" name="service_id" value="<?php echo esc_attr( $current_row['id'] ?? 0 ); ?>" />

			<table class="form-table" role="presentation">
				<tbody>
					<tr>
						<th scope="row"><label for="bbgf-service-name"><?php esc_html_e( 'Service name', 'bb-groomflow' ); ?></label></th>
						<td><input type="text" class="regular-text" required id="bbgf-service-name" name="name" value="<?php echo esc_attr( $current_row['name'] ?? '' ); ?>" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="bbgf-service-icon"><?php esc_html_e( 'Icon / Emoji', 'bb-groomflow' ); ?></label></th>
						<td>
							<div class="bbgf-emoji-field" data-bbgf-emoji-field>
								<div class="bbgf-emoji-field__controls">
									<input type="text" class="small-text" id="bbgf-service-icon" name="icon" value="<?php echo esc_attr( $current_row['icon'] ?? '' ); ?>" maxlength="32" data-bbgf-emoji-input />
									<span data-bbgf-emoji-mount></span>
								</div>
								<p class="description"><?php esc_html_e( 'Displayed on visit cards and chips to visually identify the service.', 'bb-groomflow' ); ?></p>
							</div>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="bbgf-service-color"><?php esc_html_e( 'Accent color', 'bb-groomflow' ); ?></label></th>
						<td>
							<input
								type="text"
								class="bbgf-color-picker"
								id="bbgf-service-color"
								name="color"
								value="<?php echo esc_attr( $current_row['color'] ?? '' ); ?>"
								data-default-color="#6366f1"
							/>
							<p class="description"><?php esc_html_e( 'Sets the badge/background accent used for this service in admin tables and the board.', 'bb-groomflow' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="bbgf-service-duration"><?php esc_html_e( 'Estimated duration (minutes)', 'bb-groomflow' ); ?></label></th>
						<td><input type="number" class="small-text" id="bbgf-service-duration" name="duration_minutes" min="0" value="<?php echo esc_attr( $duration_val ); ?>" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="bbgf-service-price"><?php esc_html_e( 'Price', 'bb-groomflow' ); ?></label></th>
						<td>
							<input type="text" class="small-text" id="bbgf-service-price" name="price" value="<?php echo esc_attr( $price_value ); ?>" />
							<p class="description"><?php esc_html_e( 'Optional. Enter a number (e.g., 45 or 45.00). Leave blank to hide pricing.', 'bb-groomflow' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="bbgf-service-tags"><?php esc_html_e( 'Tags', 'bb-groomflow' ); ?></label></th>
						<td>
							<input type="text" class="regular-text" id="bbgf-service-tags" name="tags" value="<?php echo esc_attr( $tags_value ); ?>" />
							<p class="description"><?php esc_html_e( 'Comma-separated descriptors (e.g., Bath, Deshed, Premium). Used for filtering and reporting.', 'bb-groomflow' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="bbgf-service-description"><?php esc_html_e( 'Description', 'bb-groomflow' ); ?></label></th>
						<td><textarea id="bbgf-service-description" name="description" rows="4" class="large-text"><?php echo esc_textarea( $current_row['description'] ?? '' ); ?></textarea></td>
					</tr>
				</tbody>
			</table>

			<?php submit_button( $editing ? __( 'Update Service', 'bb-groomflow' ) : __( 'Add Service', 'bb-groomflow' ) ); ?>
		</form>
	</div>

	<form method="get" action="">
		<input type="hidden" name="page" value="<?php echo esc_attr( Services_Admin::PAGE_SLUG ); ?>" />
		<?php $list->search_box( __( 'Search services', 'bb-groomflow' ), 'bbgf-services' ); ?>
		<?php $list->display(); ?>
	</form>
</div>
