<?php
/**
 * Views admin page view.
 *
 * @package BB_GroomFlow
 */

use BBGF\Admin\Views_Admin;
use BBGF\Admin\Views_List_Table;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$editing  = is_array( $current_view );
$messages = array(
	'view-created'        => __( 'View created successfully.', 'bb-groomflow' ),
	'view-updated'        => __( 'View updated successfully.', 'bb-groomflow' ),
	'view-deleted'        => __( 'View deleted.', 'bb-groomflow' ),
	'view-empty-name'     => __( 'View name is required.', 'bb-groomflow' ),
	'view-empty-stages'   => __( 'Add at least one stage to the view.', 'bb-groomflow' ),
	'view-stages-missing' => __( 'One or more selected stages are no longer available. Refresh the page and try again.', 'bb-groomflow' ),
);

$settings = array();
if ( isset( $current_view['settings'] ) ) {
	$decoded = json_decode( (string) $current_view['settings'], true );
	if ( is_array( $decoded ) ) {
		$settings = $decoded;
	}
}

?>
<div class="wrap bbgf-views-admin">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Kanban Views', 'bb-groomflow' ); ?></h1>
	<?php if ( $editing ) : ?>
		<p class="description"><?php esc_html_e( 'Editing a view. Adjust details, stages, and settings below.', 'bb-groomflow' ); ?></p>
	<?php endif; ?>

	<?php if ( ! empty( $message ) && isset( $messages[ $message ] ) ) : ?>
		<?php
		$notice_type = in_array( $message, array( 'view-empty-name', 'view-empty-stages' ), true ) ? 'notice-error' : 'notice-success';
		printf( '<div class="notice %1$s is-dismissible"><p>%2$s</p></div>', esc_attr( $notice_type ), esc_html( $messages[ $message ] ) );
		?>
	<?php endif; ?>

	<hr class="wp-header-end" />

	<div class="bbgf-view-editor">
		<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=' . Views_Admin::PAGE_SLUG ) ); ?>" class="bbgf-view-form">
			<?php wp_nonce_field( 'bbgf_save_view', 'bbgf_view_nonce' ); ?>
			<input type="hidden" name="view_id" value="<?php echo esc_attr( $current_view['id'] ?? 0 ); ?>" />

			<table class="form-table" role="presentation">
				<tbody>
					<tr>
						<th scope="row"><label for="bbgf-view-name"><?php esc_html_e( 'View name', 'bb-groomflow' ); ?></label></th>
						<td><input type="text" class="regular-text" required id="bbgf-view-name" name="name" value="<?php echo esc_attr( $current_view['name'] ?? '' ); ?>" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="bbgf-view-type"><?php esc_html_e( 'Type', 'bb-groomflow' ); ?></label></th>
						<td>
							<select id="bbgf-view-type" name="type">
								<?php
								$view_type = $current_view['type'] ?? 'internal';
								foreach ( Views_Admin::VIEW_TYPES as $type_option ) :
									$label = array(
										'internal' => __( 'Internal', 'bb-groomflow' ),
										'lobby'    => __( 'Lobby', 'bb-groomflow' ),
										'kiosk'    => __( 'Kiosk', 'bb-groomflow' ),
									);
									?>
									<option value="<?php echo esc_attr( $type_option ); ?>" <?php selected( $view_type, $type_option ); ?>>
										<?php echo esc_html( $label[ $type_option ] ?? ucfirst( $type_option ) ); ?>
									</option>
								<?php endforeach; ?>
							</select>
							<p class="description"><?php esc_html_e( 'Internal views allow switching; lobby/kiosk views are locked for public display.', 'bb-groomflow' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Options', 'bb-groomflow' ); ?></th>
						<td>
							<label for="bbgf-view-switcher">
								<input type="checkbox" id="bbgf-view-switcher" name="allow_switcher" value="1" <?php checked( ! empty( $current_view['allow_switcher'] ) ); ?> />
								<?php esc_html_e( 'Allow staff to switch to this view from the toolbar', 'bb-groomflow' ); ?>
							</label>
							<br />
							<label for="bbgf-view-guardian">
								<input type="checkbox" id="bbgf-view-guardian" name="show_guardian" value="1" <?php checked( ! empty( $current_view['show_guardian'] ) ); ?> />
								<?php esc_html_e( 'Display guardian details on cards', 'bb-groomflow' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="bbgf-view-refresh"><?php esc_html_e( 'Refresh interval (seconds)', 'bb-groomflow' ); ?></label></th>
						<td>
							<input type="number" class="small-text" min="15" id="bbgf-view-refresh" name="refresh_interval" value="<?php echo esc_attr( $current_view['refresh_interval'] ?? 60 ); ?>" />
							<p class="description"><?php esc_html_e( 'Minimum 15 seconds. Lobby and kiosk views will auto-refresh using this value.', 'bb-groomflow' ); ?></p>
						</td>
					</tr>
				</tbody>
			</table>

		<h2><?php esc_html_e( 'Stages', 'bb-groomflow' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Select the stages to display in this view and arrange their order. Edit stage details under GroomFlow → Stages.', 'bb-groomflow' ); ?></p>

		<div class="bbgf-stage-selector">
			<label class="screen-reader-text" for="bbgf-stage-select"><?php esc_html_e( 'Add stage to view', 'bb-groomflow' ); ?></label>
			<select id="bbgf-stage-select" class="bbgf-stage-select">
				<option value=""><?php esc_html_e( 'Select a stage…', 'bb-groomflow' ); ?></option>
				<?php foreach ( $available_stages as $stage_id => $stage ) : ?>
					<option value="<?php echo esc_attr( $stage_id ); ?>" <?php disabled( in_array( (int) $stage_id, $selected_stage_ids, true ) ); ?>
						data-stage-key="<?php echo esc_attr( $stage['stage_key'] ); ?>"
						data-stage-label="<?php echo esc_attr( $stage['label'] ); ?>"
						data-stage-description="<?php echo esc_attr( $stage['description'] ?? '' ); ?>"
						data-stage-soft="<?php echo esc_attr( $stage['capacity_soft_limit'] ?? 0 ); ?>"
						data-stage-hard="<?php echo esc_attr( $stage['capacity_hard_limit'] ?? 0 ); ?>"
						data-stage-green="<?php echo esc_attr( $stage['timer_threshold_green'] ?? 0 ); ?>"
						data-stage-yellow="<?php echo esc_attr( $stage['timer_threshold_yellow'] ?? 0 ); ?>"
						data-stage-red="<?php echo esc_attr( $stage['timer_threshold_red'] ?? 0 ); ?>"
					>
						<?php echo esc_html( $stage['label'] ); ?>
					</option>
				<?php endforeach; ?>
			</select>
			<button type="button" class="button button-secondary" id="bbgf-stage-add">
				<?php esc_html_e( 'Add Stage', 'bb-groomflow' ); ?>
			</button>
			<span class="description"><?php esc_html_e( 'Manage stage details under GroomFlow → Stages.', 'bb-groomflow' ); ?></span>
		</div>

		<table class="widefat fixed striped bbgf-selected-stages" id="bbgf-selected-stages">
			<thead>
				<tr>
					<th scope="col" class="bbgf-col-order"><?php esc_html_e( 'Order', 'bb-groomflow' ); ?></th>
					<th scope="col" class="bbgf-col-stage"><?php esc_html_e( 'Stage', 'bb-groomflow' ); ?></th>
					<th scope="col" class="bbgf-col-capacity"><?php esc_html_e( 'Capacity (soft / hard)', 'bb-groomflow' ); ?></th>
					<th scope="col" class="bbgf-col-timers"><?php esc_html_e( 'Timer thresholds (min)', 'bb-groomflow' ); ?></th>
					<th scope="col" class="bbgf-col-notes"><?php esc_html_e( 'Notes', 'bb-groomflow' ); ?></th>
					<th scope="col" class="bbgf-col-actions"><span class="screen-reader-text"><?php esc_html_e( 'Actions', 'bb-groomflow' ); ?></span></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $selected_stages ) ) : ?>
					<tr class="bbgf-selected-stage is-empty">
						<td colspan="6"><?php esc_html_e( 'No stages selected. Add a stage from the menu above.', 'bb-groomflow' ); ?></td>
					</tr>
				<?php else : ?>
					<?php foreach ( $selected_stages as $stage ) : ?>
						<tr class="bbgf-selected-stage<?php echo $stage['missing'] ? ' is-missing' : ''; ?>" data-stage-id="<?php echo esc_attr( $stage['id'] ); ?>" data-stage-key="<?php echo esc_attr( $stage['stage_key'] ); ?>">
							<td class="bbgf-col-order">
								<div class="bbgf-order-controls">
									<button type="button" class="button button-secondary bbgf-stage-move bbgf-stage-move-up" aria-label="<?php esc_attr_e( 'Move stage up', 'bb-groomflow' ); ?>">▲</button>
									<button type="button" class="button button-secondary bbgf-stage-move bbgf-stage-move-down" aria-label="<?php esc_attr_e( 'Move stage down', 'bb-groomflow' ); ?>">▼</button>
								</div>
							</td>
							<td class="bbgf-col-stage">
								<strong class="bbgf-stage-label"><?php echo esc_html( $stage['label'] ); ?></strong>
								<span class="bbgf-stage-key"><code><?php echo esc_html( $stage['stage_key'] ); ?></code></span>
								<?php if ( $stage['missing'] ) : ?>
									<span class="bbgf-stage-warning"><?php esc_html_e( 'Stage not found in catalog. Add or map it under GroomFlow → Stages.', 'bb-groomflow' ); ?></span>
								<?php endif; ?>
							</td>
							<td class="bbgf-col-capacity">
								<span class="bbgf-capacity-value"><?php echo esc_html( $stage['capacity_soft_limit'] ); ?></span>
								<span aria-hidden="true">/</span>
								<span class="bbgf-capacity-value"><?php echo esc_html( $stage['capacity_hard_limit'] ); ?></span>
								<span class="bbgf-help dashicons dashicons-editor-help" title="<?php esc_attr_e( 'Soft limit triggers warnings; hard limit blocks new cards.', 'bb-groomflow' ); ?>"></span>
							</td>
							<td class="bbgf-col-timers">
								<ul>
									<li><?php esc_html_e( 'Green:', 'bb-groomflow' ); ?> <span><?php echo esc_html( $stage['timer_threshold_green'] ); ?></span></li>
									<li><?php esc_html_e( 'Yellow:', 'bb-groomflow' ); ?> <span><?php echo esc_html( $stage['timer_threshold_yellow'] ); ?></span></li>
									<li><?php esc_html_e( 'Red:', 'bb-groomflow' ); ?> <span><?php echo esc_html( $stage['timer_threshold_red'] ); ?></span></li>
								</ul>
							</td>
							<td class="bbgf-col-notes">
								<?php if ( ! empty( $stage['description'] ) ) : ?>
									<p><?php echo esc_html( $stage['description'] ); ?></p>
								<?php else : ?>
									<span class="bbgf-muted"><?php esc_html_e( 'No notes', 'bb-groomflow' ); ?></span>
								<?php endif; ?>
							</td>
							<td class="bbgf-col-actions">
								<button type="button" class="button button-link-delete bbgf-stage-remove" data-stage-label="<?php echo esc_attr( $stage['label'] ); ?>" data-stage-key="<?php echo esc_attr( $stage['stage_key'] ); ?>">
									<?php esc_html_e( 'Remove', 'bb-groomflow' ); ?>
								</button>
								<?php if ( $stage['id'] > 0 ) : ?>
									<input type="hidden" name="stages[]" value="<?php echo esc_attr( $stage['id'] ); ?>" />
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>

		<template id="bbgf-stage-row-template">
			<tr class="bbgf-selected-stage" data-stage-id="" data-stage-key="">
				<td class="bbgf-col-order">
					<div class="bbgf-order-controls">
						<button type="button" class="button button-secondary bbgf-stage-move bbgf-stage-move-up" aria-label="<?php esc_attr_e( 'Move stage up', 'bb-groomflow' ); ?>">▲</button>
						<button type="button" class="button button-secondary bbgf-stage-move bbgf-stage-move-down" aria-label="<?php esc_attr_e( 'Move stage down', 'bb-groomflow' ); ?>">▼</button>
					</div>
				</td>
				<td class="bbgf-col-stage">
					<strong class="bbgf-stage-label"></strong>
					<span class="bbgf-stage-key"><code></code></span>
				</td>
				<td class="bbgf-col-capacity">
					<span class="bbgf-capacity-value" data-field="soft"></span>
					<span aria-hidden="true">/</span>
					<span class="bbgf-capacity-value" data-field="hard"></span>
					<span class="bbgf-help dashicons dashicons-editor-help" title="<?php esc_attr_e( 'Soft limit triggers warnings; hard limit blocks new cards.', 'bb-groomflow' ); ?>"></span>
				</td>
				<td class="bbgf-col-timers">
					<ul>
						<li><?php esc_html_e( 'Green:', 'bb-groomflow' ); ?> <span data-field="green"></span></li>
						<li><?php esc_html_e( 'Yellow:', 'bb-groomflow' ); ?> <span data-field="yellow"></span></li>
						<li><?php esc_html_e( 'Red:', 'bb-groomflow' ); ?> <span data-field="red"></span></li>
					</ul>
				</td>
				<td class="bbgf-col-notes">
					<p data-field="description"></p>
				</td>
				<td class="bbgf-col-actions">
					<button type="button" class="button button-link-delete bbgf-stage-remove">
						<?php esc_html_e( 'Remove', 'bb-groomflow' ); ?>
					</button>
					<input type="hidden" name="stages[]" value="" />
				</td>
			</tr>
		</template>

		<h2><?php esc_html_e( 'Branding', 'bb-groomflow' ); ?></h2>
			<table class="form-table" role="presentation">
				<tbody>
					<tr>
						<th scope="row"><label for="bbgf-view-accent"><?php esc_html_e( 'Accent color', 'bb-groomflow' ); ?></label></th>
						<td>
							<input type="text" class="regular-text" id="bbgf-view-accent" name="settings[accent_color]" value="<?php echo esc_attr( $settings['accent_color'] ?? '' ); ?>" placeholder="<?php esc_attr_e( '#6366f1', 'bb-groomflow' ); ?>" />
							<p class="description"><?php esc_html_e( 'Optional. Applies to headers, buttons, and hover states.', 'bb-groomflow' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="bbgf-view-background"><?php esc_html_e( 'Background color', 'bb-groomflow' ); ?></label></th>
						<td>
							<input type="text" class="regular-text" id="bbgf-view-background" name="settings[background_color]" value="<?php echo esc_attr( $settings['background_color'] ?? '' ); ?>" placeholder="<?php esc_attr_e( '#f1f5f9', 'bb-groomflow' ); ?>" />
							<p class="description"><?php esc_html_e( 'Optional. Sets the board background tone.', 'bb-groomflow' ); ?></p>
						</td>
					</tr>
				</tbody>
			</table>

			<?php submit_button( $editing ? __( 'Update View', 'bb-groomflow' ) : __( 'Add View', 'bb-groomflow' ) ); ?>
		</form>
	</div>

	<form method="get" action="">
		<input type="hidden" name="page" value="<?php echo esc_attr( Views_Admin::PAGE_SLUG ); ?>" />
		<?php $list->search_box( __( 'Search views', 'bb-groomflow' ), 'bbgf-views' ); ?>
		<?php $list->display(); ?>
	</form>
</div>
