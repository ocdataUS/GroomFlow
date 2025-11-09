<?php
/**
 * Guardians admin page view.
 *
 * @package BB_GroomFlow
 */

use BBGF\Admin\Guardians_Admin;
use BBGF\Admin\Guardians_List_Table;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$contact_options = array(
	''             => __( 'Not specified', 'bb-groomflow' ),
	'email'        => __( 'Email', 'bb-groomflow' ),
	'phone_mobile' => __( 'Mobile Phone', 'bb-groomflow' ),
	'phone_alt'    => __( 'Alternate Phone', 'bb-groomflow' ),
	'sms'          => __( 'SMS', 'bb-groomflow' ),
);

$editing = is_array( $current_record );
?>
<div class="wrap bbgf-guardians-admin">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Guardians', 'bb-groomflow' ); ?></h1>
	<?php if ( $editing ) : ?>
	<p class="description"><?php esc_html_e( 'Editing guardian contact details.', 'bb-groomflow' ); ?></p>
	<?php endif; ?>

	<?php if ( ! empty( $message ) ) : ?>
		<?php
		$messages = array(
			'guardian-created'    => __( 'Guardian created successfully.', 'bb-groomflow' ),
			'guardian-updated'    => __( 'Guardian updated successfully.', 'bb-groomflow' ),
			'guardian-deleted'    => __( 'Guardian deleted.', 'bb-groomflow' ),
			'guardian-empty-name' => __( 'First and last name are required.', 'bb-groomflow' ),
		);
		if ( isset( $messages[ $message ] ) ) :
			printf( '<div class="notice notice-success is-dismissible"><p>%s</p></div>', esc_html( $messages[ $message ] ) );
		endif;
		?>
	<?php endif; ?>

	<hr class="wp-header-end" />

	<div class="bbgf-guardian-editor">
		<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=' . Guardians_Admin::PAGE_SLUG ) ); ?>" class="bbgf-guardian-form">
			<?php wp_nonce_field( 'bbgf_save_guardian', 'bbgf_guardian_nonce' ); ?>
			<input type="hidden" name="guardian_id" value="<?php echo esc_attr( $current_record['id'] ?? 0 ); ?>" />

			<table class="form-table">
				<tbody>
				<tr>
					<th scope="row"><label for="bbgf-guardian-first-name"><?php esc_html_e( 'First name', 'bb-groomflow' ); ?></label></th>
					<td><input type="text" class="regular-text" required id="bbgf-guardian-first-name" name="first_name" value="<?php echo esc_attr( $current_record['first_name'] ?? '' ); ?>" /></td>
				</tr>
				<tr>
					<th scope="row"><label for="bbgf-guardian-last-name"><?php esc_html_e( 'Last name', 'bb-groomflow' ); ?></label></th>
					<td><input type="text" class="regular-text" required id="bbgf-guardian-last-name" name="last_name" value="<?php echo esc_attr( $current_record['last_name'] ?? '' ); ?>" /></td>
				</tr>
				<tr>
					<th scope="row"><label for="bbgf-guardian-email"><?php esc_html_e( 'Email', 'bb-groomflow' ); ?></label></th>
					<td><input type="email" class="regular-text" id="bbgf-guardian-email" name="email" value="<?php echo esc_attr( $current_record['email'] ?? '' ); ?>" /></td>
				</tr>
				<tr>
					<th scope="row"><label for="bbgf-guardian-phone-mobile"><?php esc_html_e( 'Mobile phone', 'bb-groomflow' ); ?></label></th>
					<td><input type="text" class="regular-text" id="bbgf-guardian-phone-mobile" name="phone_mobile" value="<?php echo esc_attr( $current_record['phone_mobile'] ?? '' ); ?>" /></td>
				</tr>
				<tr>
					<th scope="row"><label for="bbgf-guardian-phone-alt"><?php esc_html_e( 'Alternate phone', 'bb-groomflow' ); ?></label></th>
					<td><input type="text" class="regular-text" id="bbgf-guardian-phone-alt" name="phone_alt" value="<?php echo esc_attr( $current_record['phone_alt'] ?? '' ); ?>" /></td>
				</tr>
				<tr>
					<th scope="row"><label for="bbgf-guardian-preferred"><?php esc_html_e( 'Preferred contact method', 'bb-groomflow' ); ?></label></th>
					<td>
						<select id="bbgf-guardian-preferred" name="preferred_contact">
							<?php foreach ( $contact_options as $value => $label ) : ?>
								<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $current_record['preferred_contact'] ?? '', $value ); ?>><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="bbgf-guardian-address"><?php esc_html_e( 'Address', 'bb-groomflow' ); ?></label></th>
					<td><textarea id="bbgf-guardian-address" name="address" rows="3" class="large-text"><?php echo esc_textarea( $current_record['address'] ?? '' ); ?></textarea></td>
				</tr>
				<tr>
					<th scope="row"><label for="bbgf-guardian-notes"><?php esc_html_e( 'Notes', 'bb-groomflow' ); ?></label></th>
					<td><textarea id="bbgf-guardian-notes" name="notes" rows="3" class="large-text"><?php echo esc_textarea( $current_record['notes'] ?? '' ); ?></textarea></td>
				</tr>
				</tbody>
			</table>

			<?php submit_button( $editing ? __( 'Update Guardian', 'bb-groomflow' ) : __( 'Add Guardian', 'bb-groomflow' ) ); ?>
		</form>
	</div>

	<form method="get" action="">
		<input type="hidden" name="page" value="<?php echo esc_attr( Guardians_Admin::PAGE_SLUG ); ?>" />
		<?php $list->display(); ?>
	</form>
</div>
