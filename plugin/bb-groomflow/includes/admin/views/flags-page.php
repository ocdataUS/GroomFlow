<?php
/**
 * Flags admin page view.
 *
 * @package BB_GroomFlow
 */

use BBGF\Admin\Flags_Admin;
use BBGF\Admin\Flags_List_Table;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$severity_options = array(
	'low'    => __( 'Low', 'bb-groomflow' ),
	'medium' => __( 'Medium', 'bb-groomflow' ),
	'high'   => __( 'High', 'bb-groomflow' ),
);

$editing = is_array( $current_flag );
?>
<div class="wrap bbgf-flags-admin">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Behavior Flags', 'bb-groomflow' ); ?></h1>
	<?php if ( $editing ) : ?>
	<p class="description"><?php esc_html_e( 'Editing an existing flag. Update the details below and save.', 'bb-groomflow' ); ?></p>
	<?php endif; ?>

	<?php if ( ! empty( $message ) ) : ?>
		<?php
			$messages = array(
				'flag-created'    => __( 'Flag created successfully.', 'bb-groomflow' ),
				'flag-updated'    => __( 'Flag updated successfully.', 'bb-groomflow' ),
				'flag-deleted'    => __( 'Flag deleted.', 'bb-groomflow' ),
				'flag-empty-name' => __( 'Flag name is required.', 'bb-groomflow' ),
			);
			if ( isset( $messages[ $message ] ) ) :
				printf( '<div class="notice notice-success is-dismissible"><p>%s</p></div>', esc_html( $messages[ $message ] ) );
			endif;
			?>
	<?php endif; ?>

	<hr class="wp-header-end" />

	<div class="bbgf-flag-editor">
		<form method="post" action="<?php echo esc_url( $this->plugin->admin_url( Flags_Admin::PAGE_SLUG ) ); ?>" class="bbgf-flag-form">
			<?php wp_nonce_field( 'bbgf_save_flag', 'bbgf_flag_nonce' ); ?>
			<input type="hidden" name="flag_id" value="<?php echo esc_attr( $current_flag['id'] ?? 0 ); ?>" />

			<table class="form-table">
				<tbody>
				<tr>
					<th scope="row"><label for="bbgf-flag-name"><?php esc_html_e( 'Flag name', 'bb-groomflow' ); ?></label></th>
					<td><input type="text" class="regular-text" required id="bbgf-flag-name" name="name" value="<?php echo esc_attr( $current_flag['name'] ?? '' ); ?>" /></td>
				</tr>
					<tr>
						<th scope="row"><label for="bbgf-flag-emoji"><?php esc_html_e( 'Emoji icon', 'bb-groomflow' ); ?></label></th>
						<td>
							<div class="bbgf-emoji-field" data-bbgf-emoji-field>
								<div class="bbgf-emoji-field__controls">
									<input type="text" class="small-text" id="bbgf-flag-emoji" name="emoji" value="<?php echo esc_attr( $current_flag['emoji'] ?? '' ); ?>" maxlength="16" data-bbgf-emoji-input />
									<span data-bbgf-emoji-mount></span>
								</div>
								<p class="description"><?php esc_html_e( 'Used on visit cards, tables, and notices to quickly identify this flag.', 'bb-groomflow' ); ?></p>
							</div>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="bbgf-flag-color"><?php esc_html_e( 'Color', 'bb-groomflow' ); ?></label></th>
						<td>
							<input
								type="text"
								class="bbgf-color-picker"
								id="bbgf-flag-color"
								name="color"
								value="<?php echo esc_attr( $current_flag['color'] ?? '' ); ?>"
								data-default-color="#ef4444"
							/>
							<p class="description"><?php esc_html_e( 'Tint applied to the flag chip across the admin and board UI.', 'bb-groomflow' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="bbgf-flag-severity"><?php esc_html_e( 'Severity', 'bb-groomflow' ); ?></label></th>
						<td>
			<select id="bbgf-flag-severity" name="severity">
								<?php foreach ( $severity_options as $value => $label ) : ?>
									<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $current_flag['severity'] ?? 'medium', $value ); ?>><?php echo esc_html( $label ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="bbgf-flag-description"><?php esc_html_e( 'Description', 'bb-groomflow' ); ?></label></th>
						<td>
							<textarea id="bbgf-flag-description" name="description" rows="3" class="large-text"><?php echo esc_textarea( $current_flag['description'] ?? '' ); ?></textarea>
							<p class="description"><?php esc_html_e( 'Internal note explaining how staff should interpret the flag.', 'bb-groomflow' ); ?></p>
						</td>
					</tr>
				</tbody>
			</table>

			<?php submit_button( $editing ? __( 'Update Flag', 'bb-groomflow' ) : __( 'Create Flag', 'bb-groomflow' ) ); ?>
		</form>
	</div>

	<form method="get" action="">
		<input type="hidden" name="page" value="<?php echo esc_attr( Flags_Admin::PAGE_SLUG ); ?>" />
		<?php $list->display(); ?>
	</form>
</div>
