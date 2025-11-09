<?php
/**
 * Notification triggers view.
 *
 * @package BB_GroomFlow
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$editing                   = is_array( $current );
$submit_text               = $editing ? __( 'Update Trigger', 'bb-groomflow' ) : __( 'Add Trigger', 'bb-groomflow' );
$current_stage             = $current['trigger_stage'] ?? '';
$current_template_id       = isset( $current['notification_id'] ) ? (int) $current['notification_id'] : 0;
$current_enabled           = ! empty( $current['enabled'] );
$recipient_type            = $current['recipient_type'] ?? 'guardian_primary';
$recipient_email           = $current['recipient_email'] ?? '';
$recipient_requires_custom = $this->recipient_type_requires_custom( $recipient_type );

?>
<div class="wrap bbgf-wrap">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Notification Triggers', 'bb-groomflow' ); ?></h1>
	<p class="description">
		<?php esc_html_e( 'Choose which template should send when a visit enters a specific stage.', 'bb-groomflow' ); ?>
	</p>

	<?php if ( ! empty( $message ) ) : ?>
		<div class="notice notice-success is-dismissible">
			<p>
				<?php
				switch ( $message ) {
					case 'trigger-created':
						esc_html_e( 'Notification trigger created.', 'bb-groomflow' );
						break;
					case 'trigger-updated':
						esc_html_e( 'Notification trigger updated.', 'bb-groomflow' );
						break;
					case 'trigger-deleted':
						esc_html_e( 'Notification trigger removed.', 'bb-groomflow' );
						break;
					case 'trigger-missing-fields':
						esc_html_e( 'Select both a stage and template before saving.', 'bb-groomflow' );
						break;
					case 'trigger-missing-recipient':
						esc_html_e( 'Enter at least one email address for the custom recipient option.', 'bb-groomflow' );
						break;
					default:
						echo esc_html( $message );
						break;
				}
				?>
			</p>
		</div>
	<?php endif; ?>

	<div class="bbgf-admin-grid">
		<div class="bbgf-admin-grid__primary">
			<?php $list->display(); ?>
		</div>
		<div class="bbgf-admin-grid__secondary">
			<div class="card">
				<h2 class="title">
					<?php echo $editing ? esc_html__( 'Edit Trigger', 'bb-groomflow' ) : esc_html__( 'Add Trigger', 'bb-groomflow' ); ?>
				</h2>
				<?php if ( empty( $template_options ) ) : ?>
					<p>
						<?php
						printf(
							/* translators: %s: URL to notification templates */
							esc_html__( 'Create a notification template before adding triggers. %s', 'bb-groomflow' ),
							sprintf(
								'<a href="%s">%s</a>',
								esc_url( $this->plugin->admin_url( \BBGF\Admin\Notifications_Admin::PAGE_SLUG ) ),
								esc_html__( 'Manage templates', 'bb-groomflow' )
							)
						);
						?>
					</p>
				<?php else : ?>
					<form method="post" action="<?php echo esc_url( $this->get_page_url() ); ?>">
						<?php wp_nonce_field( 'bbgf_save_notification_trigger', 'bbgf_notification_trigger_nonce' ); ?>
						<input type="hidden" name="trigger_id" value="<?php echo esc_attr( $current['id'] ?? 0 ); ?>" />

						<table class="form-table" role="presentation">
							<tbody>
								<tr>
									<th scope="row"><label for="bbgf-trigger-stage"><?php esc_html_e( 'Stage', 'bb-groomflow' ); ?></label></th>
									<td>
										<select id="bbgf-trigger-stage" name="trigger_stage" required>
											<option value=""><?php esc_html_e( 'Select stage…', 'bb-groomflow' ); ?></option>
											<?php foreach ( (array) $stage_options as $stage ) : ?>
												<option value="<?php echo esc_attr( $stage['stage_key'] ); ?>" <?php selected( $stage['stage_key'], $current_stage ); ?>>
													<?php echo esc_html( $stage['label'] ?? $stage['stage_key'] ); ?>
												</option>
											<?php endforeach; ?>
										</select>
									</td>
								</tr>
								<tr>
									<th scope="row"><label for="bbgf-trigger-template"><?php esc_html_e( 'Template', 'bb-groomflow' ); ?></label></th>
									<td>
										<select id="bbgf-trigger-template" name="notification_id" required>
											<option value=""><?php esc_html_e( 'Select template…', 'bb-groomflow' ); ?></option>
											<?php foreach ( (array) $template_options as $template ) : ?>
												<option value="<?php echo esc_attr( $template['id'] ); ?>" <?php selected( (int) $template['id'], $current_template_id ); ?>>
													<?php echo esc_html( $template['name'] ); ?>
												</option>
											<?php endforeach; ?>
										</select>
									</td>
								</tr>
								<tr>
									<th scope="row"><?php esc_html_e( 'Recipient', 'bb-groomflow' ); ?></th>
									<td>
										<fieldset class="bbgf-recipient-options" data-bbgf-recipient-options>
											<legend class="screen-reader-text"><?php esc_html_e( 'Choose who should receive this notification.', 'bb-groomflow' ); ?></legend>
											<?php foreach ( (array) $recipient_options as $option_key => $option ) : ?>
												<label class="bbgf-recipient-option">
													<input
														type="radio"
														name="recipient_type"
														value="<?php echo esc_attr( $option_key ); ?>"
														<?php checked( $option_key, $recipient_type ); ?>
														data-requires-custom="<?php echo ! empty( $option['requires_custom'] ) ? '1' : '0'; ?>"
													/>
													<span class="bbgf-recipient-option__label"><?php echo esc_html( $option['label'] ); ?></span>
													<span class="description"><?php echo esc_html( $option['description'] ); ?></span>
												</label>
											<?php endforeach; ?>
										</fieldset>
										<div
											class="bbgf-recipient-custom-field"
											data-bbgf-recipient-custom
											<?php echo $recipient_requires_custom ? '' : ' hidden'; ?>
										>
											<label class="screen-reader-text" for="bbgf-trigger-recipient-email"><?php esc_html_e( 'Additional email addresses', 'bb-groomflow' ); ?></label>
											<textarea
												class="large-text"
												rows="2"
												id="bbgf-trigger-recipient-email"
												name="recipient_email"
												placeholder="team@example.com, manager@example.com"
											><?php echo esc_textarea( $recipient_email ); ?></textarea>
											<p class="description bbgf-recipient-custom-help">
												<?php esc_html_e( 'Separate multiple addresses with commas or line breaks. Invalid emails are ignored.', 'bb-groomflow' ); ?>
											</p>
										</div>
									</td>
								</tr>
								<tr>
									<th scope="row"><?php esc_html_e( 'Enabled', 'bb-groomflow' ); ?></th>
									<td>
										<label for="bbgf-trigger-enabled">
											<input type="checkbox" id="bbgf-trigger-enabled" name="enabled" value="1" <?php checked( $current_enabled ); ?> />
											<?php esc_html_e( 'Send when the stage is reached', 'bb-groomflow' ); ?>
										</label>
									</td>
								</tr>
							</tbody>
						</table>

						<p class="submit">
							<button type="submit" class="button button-primary"><?php echo esc_html( $submit_text ); ?></button>
							<?php if ( $editing ) : ?>
								<a class="button" href="<?php echo esc_url( $this->get_page_url() ); ?>"><?php esc_html_e( 'Cancel', 'bb-groomflow' ); ?></a>
							<?php endif; ?>
						</p>
					</form>
				<?php endif; ?>
			</div>
		</div>
	</div>
</div>
