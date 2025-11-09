<?php
/**
 * Clients admin page view.
 *
 * @package BB_GroomFlow
 */

use BBGF\Admin\Clients_Admin;
use BBGF\Admin\Clients_List_Table;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$sex_options = array(
	''       => __( 'Not specified', 'bb-groomflow' ),
	'male'   => __( 'Male', 'bb-groomflow' ),
	'female' => __( 'Female', 'bb-groomflow' ),
	'other'  => __( 'Other', 'bb-groomflow' ),
);

$editing = is_array( $current_client );
?>
<div class="wrap bbgf-clients-admin">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Clients', 'bb-groomflow' ); ?></h1>
	<?php if ( $editing ) : ?>
	<p class="description"><?php esc_html_e( 'Editing client profile. Update fields and save.', 'bb-groomflow' ); ?></p>
	<?php endif; ?>

	<?php if ( ! empty( $message ) ) : ?>
		<?php
		$messages = array(
			'client-created'    => __( 'Client profile created.', 'bb-groomflow' ),
			'client-updated'    => __( 'Client profile updated.', 'bb-groomflow' ),
			'client-deleted'    => __( 'Client profile deleted.', 'bb-groomflow' ),
			'client-empty-name' => __( 'Client name is required.', 'bb-groomflow' ),
		);
		if ( isset( $messages[ $message ] ) ) :
			printf( '<div class="notice notice-success is-dismissible"><p>%s</p></div>', esc_html( $messages[ $message ] ) );
		endif;
		?>
	<?php endif; ?>

	<hr class="wp-header-end" />

	<div class="bbgf-client-editor">
		<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=' . Clients_Admin::PAGE_SLUG ) ); ?>" class="bbgf-client-form">
			<?php wp_nonce_field( 'bbgf_save_client', 'bbgf_client_nonce' ); ?>
			<input type="hidden" name="client_id" value="<?php echo esc_attr( $current_client['id'] ?? 0 ); ?>" />

			<table class="form-table">
				<tbody>
				<tr>
					<th scope="row"><label for="bbgf-client-name"><?php esc_html_e( 'Client name', 'bb-groomflow' ); ?></label></th>
					<td><input type="text" class="regular-text" required id="bbgf-client-name" name="name" value="<?php echo esc_attr( $current_client['name'] ?? '' ); ?>" /></td>
				</tr>
				<tr>
					<th scope="row"><label for="bbgf-client-guardian"><?php esc_html_e( 'Guardian', 'bb-groomflow' ); ?></label></th>
					<td>
						<select id="bbgf-client-guardian" name="guardian_id" class="regular-text">
							<option value="0"><?php esc_html_e( 'Unassigned', 'bb-groomflow' ); ?></option>
							<?php foreach ( $guardians as $guardian ) : ?>
								<option value="<?php echo esc_attr( $guardian->id ); ?>" <?php selected( (int) ( $current_client['guardian_id'] ?? 0 ), (int) $guardian->id ); ?>>
									<?php echo esc_html( trim( sprintf( '%s %s', $guardian->first_name, $guardian->last_name ) ) ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="bbgf-client-breed"><?php esc_html_e( 'Breed / type', 'bb-groomflow' ); ?></label></th>
					<td><input type="text" class="regular-text" id="bbgf-client-breed" name="breed" value="<?php echo esc_attr( $current_client['breed'] ?? '' ); ?>" /></td>
				</tr>
				<tr>
					<th scope="row"><label for="bbgf-client-weight"><?php esc_html_e( 'Weight (lbs)', 'bb-groomflow' ); ?></label></th>
					<td><input type="number" step="0.1" min="0" class="small-text" id="bbgf-client-weight" name="weight" value="<?php echo esc_attr( $current_client['weight'] ?? '' ); ?>" /></td>
				</tr>
				<tr>
					<th scope="row"><label for="bbgf-client-sex"><?php esc_html_e( 'Sex', 'bb-groomflow' ); ?></label></th>
					<td>
						<select id="bbgf-client-sex" name="sex">
							<?php foreach ( $sex_options as $value => $label ) : ?>
								<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $current_client['sex'] ?? '', $value ); ?>><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="bbgf-client-dob"><?php esc_html_e( 'Date of Birth', 'bb-groomflow' ); ?></label></th>
					<td><input type="date" id="bbgf-client-dob" name="dob" value="<?php echo esc_attr( $current_client['dob'] ?? '' ); ?>" /></td>
				</tr>
				<tr>
					<th scope="row"><label for="bbgf-client-temperament"><?php esc_html_e( 'Temperament / behavior notes', 'bb-groomflow' ); ?></label></th>
					<td><input type="text" class="regular-text" id="bbgf-client-temperament" name="temperament" value="<?php echo esc_attr( $current_client['temperament'] ?? '' ); ?>" /></td>
				</tr>
				<tr>
					<th scope="row"><label for="bbgf-client-preferred-groomer"><?php esc_html_e( 'Preferred Groomer', 'bb-groomflow' ); ?></label></th>
					<td><input type="text" class="regular-text" id="bbgf-client-preferred-groomer" name="preferred_groomer" value="<?php echo esc_attr( $current_client['preferred_groomer'] ?? '' ); ?>" /></td>
				</tr>
				<tr>
					<th scope="row"><label for="bbgf-client-notes"><?php esc_html_e( 'Notes', 'bb-groomflow' ); ?></label></th>
					<td><textarea id="bbgf-client-notes" name="notes" rows="4" class="large-text"><?php echo esc_textarea( $current_client['notes'] ?? '' ); ?></textarea></td>
				</tr>
				</tbody>
			</table>

			<?php submit_button( $editing ? __( 'Update Client', 'bb-groomflow' ) : __( 'Add Client', 'bb-groomflow' ) ); ?>
		</form>
	</div>

	<form method="get" action="">
		<input type="hidden" name="page" value="<?php echo esc_attr( Clients_Admin::PAGE_SLUG ); ?>" />
		<?php $list->display(); ?>
	</form>
</div>
