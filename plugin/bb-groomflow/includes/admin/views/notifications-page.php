<?php
/**
 * Notifications admin view.
 *
 * @package BB_GroomFlow
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$editing     = is_array( $current );
$submit_text = $editing ? __( 'Update Template', 'bb-groomflow' ) : __( 'Add Template', 'bb-groomflow' );
$channel     = $current['channel'] ?? 'email';

?>
<div class="wrap bbgf-wrap">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Notification Templates', 'bb-groomflow' ); ?></h1>
	<p class="description">
		<?php esc_html_e( 'Create reusable email templates that can be triggered when a visit enters a specific stage.', 'bb-groomflow' ); ?>
	</p>
	<p class="description">
		<?php esc_html_e( 'Use merge tags like {{client_name}}, {{guardian_full_name}}, {{guardian_email}}, {{visit_stage}}, and {{visit_comment}} to personalize messages. The subject will automatically include the optional prefix configured in GroomFlow settings.', 'bb-groomflow' ); ?>
	</p>

	<?php if ( ! empty( $message ) ) : ?>
		<div class="notice notice-success is-dismissible">
			<p>
				<?php
				switch ( $message ) {
					case 'notification-created':
						esc_html_e( 'Notification template created.', 'bb-groomflow' );
						break;
					case 'notification-updated':
						esc_html_e( 'Notification template updated.', 'bb-groomflow' );
						break;
					case 'notification-deleted':
						esc_html_e( 'Notification template deleted.', 'bb-groomflow' );
						break;
					case 'notification-empty-name':
						esc_html_e( 'Template name is required.', 'bb-groomflow' );
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
					<?php echo $editing ? esc_html__( 'Edit Template', 'bb-groomflow' ) : esc_html__( 'Add Template', 'bb-groomflow' ); ?>
				</h2>
				<form method="post" action="<?php echo esc_url( $this->get_page_url() ); ?>">
					<?php wp_nonce_field( 'bbgf_save_notification', 'bbgf_notification_nonce' ); ?>
					<input type="hidden" name="notification_id" value="<?php echo esc_attr( $current['id'] ?? 0 ); ?>" />

					<table class="form-table" role="presentation">
						<tbody>
							<tr>
								<th scope="row"><label for="bbgf-notification-name"><?php esc_html_e( 'Template Name', 'bb-groomflow' ); ?></label></th>
								<td>
									<input type="text" class="regular-text" id="bbgf-notification-name" name="name" value="<?php echo esc_attr( $current['name'] ?? '' ); ?>" required />
									<p class="description"><?php esc_html_e( 'Internal name shown in the triggers list.', 'bb-groomflow' ); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="bbgf-notification-channel"><?php esc_html_e( 'Channel', 'bb-groomflow' ); ?></label></th>
								<td>
									<select id="bbgf-notification-channel" name="channel" disabled>
										<option value="email" <?php selected( 'email', $channel ); ?>><?php esc_html_e( 'Email', 'bb-groomflow' ); ?></option>
									</select>
									<input type="hidden" name="channel" value="email" />
									<p class="description"><?php esc_html_e( 'Email is supported today. Additional channels can be added via hooks.', 'bb-groomflow' ); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="bbgf-notification-subject"><?php esc_html_e( 'Subject', 'bb-groomflow' ); ?></label></th>
								<td>
									<input type="text" class="regular-text" id="bbgf-notification-subject" name="subject" value="<?php echo esc_attr( $current['subject'] ?? '' ); ?>" />
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="bbgf-notification-body-html"><?php esc_html_e( 'Email Body (HTML)', 'bb-groomflow' ); ?></label></th>
								<td>
									<?php
									wp_editor(
										$current['body_html'] ?? '',
										'bbgf-notification-body-html',
										array(
											'textarea_name' => 'body_html',
											'media_buttons' => false,
											'textarea_rows' => 8,
										)
									);
									?>
									<p class="description"><?php esc_html_e( 'Use merge-friendly HTML (e.g., {{client_name}}) for future token support.', 'bb-groomflow' ); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="bbgf-notification-body-text"><?php esc_html_e( 'Plain Text Fallback', 'bb-groomflow' ); ?></label></th>
								<td>
									<textarea class="large-text code" rows="6" id="bbgf-notification-body-text" name="body_text"><?php echo esc_textarea( $current['body_text'] ?? '' ); ?></textarea>
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
			</div>
			<div class="card bbgf-merge-tags">
				<h2 class="title"><?php esc_html_e( 'Available Merge Tags', 'bb-groomflow' ); ?></h2>
				<p class="description">
					<?php esc_html_e( 'Click a tag to copy it, then paste into the subject, HTML, or plain-text body.', 'bb-groomflow' ); ?>
				</p>
				<ul class="bbgf-merge-tags__list" data-bbgf-merge-tags>
					<?php
					$merge_tags = array(
						array(
							'tag'         => '{{client_name}}',
							'description' => __( 'Client name (e.g., Bella)', 'bb-groomflow' ),
						),
						array(
							'tag'         => '{{guardian_first_name}}',
							'description' => __( 'Guardian first name', 'bb-groomflow' ),
						),
						array(
							'tag'         => '{{guardian_last_name}}',
							'description' => __( 'Guardian last name', 'bb-groomflow' ),
						),
						array(
							'tag'         => '{{guardian_full_name}}',
							'description' => __( 'Guardian full name', 'bb-groomflow' ),
						),
						array(
							'tag'         => '{{guardian_email}}',
							'description' => __( 'Guardian email address', 'bb-groomflow' ),
						),
						array(
							'tag'         => '{{visit_stage}}',
							'description' => __( 'Current visit stage', 'bb-groomflow' ),
						),
						array(
							'tag'         => '{{visit_comment}}',
							'description' => __( 'Latest stage-move comment (if provided)', 'bb-groomflow' ),
						),
						array(
							'tag'         => '{{visit_id}}',
							'description' => __( 'Visit ID (internal reference)', 'bb-groomflow' ),
						),
						array(
							'tag'         => '{{site_name}}',
							'description' => __( 'Site title from WordPress settings', 'bb-groomflow' ),
						),
					);

					foreach ( $merge_tags as $merge_tag ) :
						?>
						<li>
							<button type="button" class="button button-secondary bbgf-merge-tags__item" data-bbgf-merge-tag="<?php echo esc_attr( $merge_tag['tag'] ); ?>">
								<code><?php echo esc_html( $merge_tag['tag'] ); ?></code>
							</button>
							<span class="description"><?php echo esc_html( $merge_tag['description'] ); ?></span>
						</li>
						<?php
					endforeach;
					?>
				</ul>
				<p class="description bbgf-merge-tags__feedback" data-bbgf-merge-tags-feedback hidden>
					<?php esc_html_e( 'Tag copied to clipboard.', 'bb-groomflow' ); ?>
				</p>
			</div>
		</div>
	</div>
</div>
