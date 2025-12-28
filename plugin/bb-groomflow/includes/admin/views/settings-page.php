<?php
/**
 * Settings admin page view.
 *
 * @package BB_GroomFlow
 */

use BBGF\Admin\Settings_Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$messages = array(
	'settings-saved' => __( 'Settings saved successfully.', 'bb-groomflow' ),
);
?>
<div class="wrap bbgf-settings-admin">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'GroomFlow Settings', 'bb-groomflow' ); ?></h1>
	<p class="description"><?php esc_html_e( 'Set global defaults for the board experience, lobby displays, and notifications.', 'bb-groomflow' ); ?></p>

	<?php if ( ! empty( $message ) && isset( $messages[ $message ] ) ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php echo esc_html( $messages[ $message ] ); ?></p></div>
	<?php endif; ?>

	<hr class="wp-header-end" />

	<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=' . Settings_Admin::PAGE_SLUG ) ); ?>" class="bbgf-settings-form">
		<?php wp_nonce_field( 'bbgf_save_settings', 'bbgf_settings_nonce' ); ?>

		<h2><?php esc_html_e( 'Board Defaults', 'bb-groomflow' ); ?></h2>
		<table class="form-table" role="presentation">
			<tbody>
				<tr>
					<th scope="row"><label for="bbgf-board-poll-interval"><?php esc_html_e( 'Poll interval (seconds)', 'bb-groomflow' ); ?></label></th>
					<td>
						<input type="number" min="15" class="small-text" id="bbgf-board-poll-interval" name="settings[board][poll_interval]" value="<?php echo esc_attr( $settings['board']['poll_interval'] ); ?>" />
						<p class="description"><?php esc_html_e( 'Used for live board refresh when REST endpoints land.', 'bb-groomflow' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Default capacity', 'bb-groomflow' ); ?></th>
					<td>
						<label for="bbgf-board-soft">
							<?php esc_html_e( 'Soft limit', 'bb-groomflow' ); ?>
							<input type="number" min="0" class="small-text" id="bbgf-board-soft" name="settings[board][default_soft_capacity]" value="<?php echo esc_attr( $settings['board']['default_soft_capacity'] ); ?>" />
						</label>
						<span aria-hidden="true">/</span>
						<label for="bbgf-board-hard">
							<?php esc_html_e( 'Hard limit', 'bb-groomflow' ); ?>
							<input type="number" min="0" class="small-text" id="bbgf-board-hard" name="settings[board][default_hard_capacity]" value="<?php echo esc_attr( $settings['board']['default_hard_capacity'] ); ?>" />
						</label>
						<p class="description"><?php esc_html_e( 'Applied when new views or stages are created.', 'bb-groomflow' ); ?></p>
					</td>
				</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Timer thresholds (minutes)', 'bb-groomflow' ); ?></th>
				<td>
					<div class="bbgf-timer-thresholds">
						<label for="bbgf-board-green">
							<?php esc_html_e( 'Green', 'bb-groomflow' ); ?>
							<input type="number" min="0" class="small-text" id="bbgf-board-green" name="settings[board][timer_thresholds][green]" value="<?php echo esc_attr( $settings['board']['timer_thresholds']['green'] ); ?>" />
						</label>
						<label for="bbgf-board-yellow">
							<?php esc_html_e( 'Yellow', 'bb-groomflow' ); ?>
							<input type="number" min="0" class="small-text" id="bbgf-board-yellow" name="settings[board][timer_thresholds][yellow]" value="<?php echo esc_attr( $settings['board']['timer_thresholds']['yellow'] ); ?>" />
						</label>
						<label for="bbgf-board-red">
							<?php esc_html_e( 'Red', 'bb-groomflow' ); ?>
							<input type="number" min="0" class="small-text" id="bbgf-board-red" name="settings[board][timer_thresholds][red]" value="<?php echo esc_attr( $settings['board']['timer_thresholds']['red'] ); ?>" />
						</label>
					</div>
					<p class="description"><?php esc_html_e( 'Controls card timer colors once real-time tracking is wired.', 'bb-groomflow' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="bbgf-board-accent"><?php esc_html_e( 'Board accent color', 'bb-groomflow' ); ?></label></th>
				<td>
					<input type="text" class="bbgf-color-picker" id="bbgf-board-accent" name="settings[board][accent_color]" value="<?php echo esc_attr( $settings['board']['accent_color'] ?? '' ); ?>" data-default-color="#6366f1" />
					<p class="description"><?php esc_html_e( 'Default accent used for column headers and interactive controls unless overridden per view.', 'bb-groomflow' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="bbgf-board-background"><?php esc_html_e( 'Board background', 'bb-groomflow' ); ?></label></th>
				<td>
					<input type="text" class="bbgf-color-picker" id="bbgf-board-background" name="settings[board][background_color]" value="<?php echo esc_attr( $settings['board']['background_color'] ?? '' ); ?>" data-default-color="#f1f5f9" />
					<p class="description"><?php esc_html_e( 'Baseline background color shown on the internal board when a view lacks custom styling.', 'bb-groomflow' ); ?></p>
				</td>
			</tr>
			</tbody>
		</table>

		<h2><?php esc_html_e( 'Lobby & Public Displays', 'bb-groomflow' ); ?></h2>
		<table class="form-table" role="presentation">
			<tbody>
				<tr>
					<th scope="row"><?php esc_html_e( 'Visibility', 'bb-groomflow' ); ?></th>
					<td>
						<label for="bbgf-lobby-mask">
							<input type="checkbox" id="bbgf-lobby-mask" name="settings[lobby][mask_guardian]" value="1" <?php checked( ! empty( $settings['lobby']['mask_guardian'] ) ); ?> />
							<?php esc_html_e( 'Mask guardian names on lobby slides', 'bb-groomflow' ); ?>
						</label>
						<br />
						<label for="bbgf-lobby-photo">
							<input type="checkbox" id="bbgf-lobby-photo" name="settings[lobby][show_client_photo]" value="1" <?php checked( ! empty( $settings['lobby']['show_client_photo'] ) ); ?> />
							<?php esc_html_e( 'Show client photos on cards', 'bb-groomflow' ); ?>
						</label>
						<br />
						<label for="bbgf-lobby-fullscreen">
							<input type="checkbox" id="bbgf-lobby-fullscreen" name="settings[lobby][enable_fullscreen]" value="1" <?php checked( ! empty( $settings['lobby']['enable_fullscreen'] ) ); ?> />
							<?php esc_html_e( 'Enable fullscreen toggle for lobby displays', 'bb-groomflow' ); ?>
						</label>
					</td>
				</tr>
			</tbody>
		</table>

		<h2><?php esc_html_e( 'Notifications', 'bb-groomflow' ); ?></h2>
		<table class="form-table" role="presentation">
			<tbody>
				<tr>
					<th scope="row"><?php esc_html_e( 'Stage emails', 'bb-groomflow' ); ?></th>
					<td>
						<label for="bbgf-notify-enable">
							<input type="checkbox" id="bbgf-notify-enable" name="settings[notifications][enable_stage_notifications]" value="1" <?php checked( ! empty( $settings['notifications']['enable_stage_notifications'] ) ); ?> />
							<?php esc_html_e( 'Enable stage-triggered email notifications', 'bb-groomflow' ); ?>
						</label>
						<p class="description"><?php esc_html_e( 'Templates and routing for stage notifications are managed here.', 'bb-groomflow' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="bbgf-notify-name"><?php esc_html_e( 'From name', 'bb-groomflow' ); ?></label></th>
					<td><input type="text" class="regular-text" id="bbgf-notify-name" name="settings[notifications][from_name]" value="<?php echo esc_attr( $settings['notifications']['from_name'] ); ?>" /></td>
				</tr>
				<tr>
					<th scope="row"><label for="bbgf-notify-email"><?php esc_html_e( 'From email', 'bb-groomflow' ); ?></label></th>
					<td><input type="email" class="regular-text" id="bbgf-notify-email" name="settings[notifications][from_email]" value="<?php echo esc_attr( $settings['notifications']['from_email'] ); ?>" /></td>
				</tr>
				<tr>
					<th scope="row"><label for="bbgf-notify-prefix"><?php esc_html_e( 'Subject prefix', 'bb-groomflow' ); ?></label></th>
					<td><input type="text" class="regular-text" id="bbgf-notify-prefix" name="settings[notifications][subject_prefix]" value="<?php echo esc_attr( $settings['notifications']['subject_prefix'] ); ?>" /></td>
				</tr>
			</tbody>
		</table>

		<h2><?php esc_html_e( 'Branding', 'bb-groomflow' ); ?></h2>
		<table class="form-table" role="presentation">
			<tbody>
				<tr>
				<th scope="row"><label for="bbgf-brand-primary"><?php esc_html_e( 'Primary color', 'bb-groomflow' ); ?></label></th>
				<td>
					<input type="text" class="bbgf-color-picker" id="bbgf-brand-primary" name="settings[branding][primary_color]" value="<?php echo esc_attr( $settings['branding']['primary_color'] ); ?>" data-default-color="#1f2937" />
					<p class="description"><?php esc_html_e( 'Primary typography and heading color for branded surfaces.', 'bb-groomflow' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="bbgf-brand-accent"><?php esc_html_e( 'Accent color', 'bb-groomflow' ); ?></label></th>
				<td>
					<input type="text" class="bbgf-color-picker" id="bbgf-brand-accent" name="settings[branding][accent_color]" value="<?php echo esc_attr( $settings['branding']['accent_color'] ); ?>" data-default-color="#0ea5e9" />
					<p class="description"><?php esc_html_e( 'Accent tint used for highlights within dashboard previews.', 'bb-groomflow' ); ?></p>
				</td>
			</tr>
				<tr>
					<th scope="row"><label for="bbgf-brand-font"><?php esc_html_e( 'Font family', 'bb-groomflow' ); ?></label></th>
					<td><input type="text" class="regular-text" id="bbgf-brand-font" name="settings[branding][font_family]" value="<?php echo esc_attr( $settings['branding']['font_family'] ); ?>" placeholder="Inter, sans-serif" /></td>
				</tr>
			</tbody>
		</table>

		<?php submit_button( __( 'Save Settings', 'bb-groomflow' ) ); ?>
	</form>
</div>
