<?php
/**
 * Stages admin page view.
 *
 * @package BB_GroomFlow
 */

use BBGF\Admin\Stages_Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$messages = array(
	'stage-created'             => __( 'Stage created successfully.', 'bb-groomflow' ),
	'stage-updated'             => __( 'Stage updated successfully.', 'bb-groomflow' ),
	'stage-bulk-saved'          => __( 'Stages updated successfully.', 'bb-groomflow' ),
	'stage-deleted'             => __( 'Stage removed. Linked views were updated and active clients were moved to the previous stage.', 'bb-groomflow' ),
	'bbgf_stage_missing_fields' => __( 'Stage key and label are required.', 'bb-groomflow' ),
	'bbgf_stage_key_exists'     => __( 'Stage key must be unique.', 'bb-groomflow' ),
	'bbgf_stage_not_found'      => __( 'That stage could not be found.', 'bb-groomflow' ),
	'bbgf_stage_missing_id'     => __( 'Stage identifier missing.', 'bb-groomflow' ),
	'bbgf_stage_delete_blocked' => __( 'Add a replacement stage to affected views before removing this one.', 'bb-groomflow' ),
	'bbgf_stage_unknown_action' => __( 'Unknown stage action.', 'bb-groomflow' ),
);

$message_key = isset( $messages[ $message ] ) ? $message : '';
?>
<div class="wrap bbgf-stages-admin">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Stages', 'bb-groomflow' ); ?></h1>
	<p class="description"><?php esc_html_e( 'Manage the canonical GroomFlow stages. Views reference these stages and inherit their capacity and timer defaults.', 'bb-groomflow' ); ?></p>
	<p class="bbgf-stage-callout"><?php esc_html_e( 'Removing a stage will also remove it from any views and move active clients back to the previous stage automatically.', 'bb-groomflow' ); ?></p>

	<?php if ( $message_key ) : ?>
		<div class="notice notice-<?php echo str_starts_with( $message_key, 'bbgf_' ) ? 'error' : 'success'; ?> is-dismissible">
			<p><?php echo esc_html( $messages[ $message_key ] ); ?></p>
		</div>
	<?php endif; ?>

	<p id="bbgf-capacity-help" class="screen-reader-text"><?php esc_html_e( 'Soft limit triggers warnings; hard limit blocks new cards.', 'bb-groomflow' ); ?></p>
	<p id="bbgf-threshold-help" class="screen-reader-text"><?php esc_html_e( 'Timers shift from green to yellow to red after the specified minute thresholds.', 'bb-groomflow' ); ?></p>

	<form method="post" action="<?php echo esc_url( $this->get_page_url() ); ?>" class="bbgf-stages-form">
		<?php wp_nonce_field( 'bbgf_manage_stage', 'bbgf_stage_nonce' ); ?>
		<input type="hidden" name="bbgf_stage_action" value="" />

		<table class="widefat fixed striped bbgf-stages-table">
			<thead>
				<tr>
					<th scope="col" class="bbgf-col-order">
						<?php esc_html_e( 'Order', 'bb-groomflow' ); ?>
						<span class="bbgf-help bbgf-help--header dashicons dashicons-editor-help" role="img" aria-label="<?php esc_attr_e( 'Drag the handle to reorder stages.', 'bb-groomflow' ); ?>" title="<?php esc_attr_e( 'Drag the handle to reorder stages.', 'bb-groomflow' ); ?>"></span>
					</th>
					<th scope="col" class="bbgf-col-key"><?php esc_html_e( 'Stage Key', 'bb-groomflow' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Label', 'bb-groomflow' ); ?></th>
					<th scope="col" class="bbgf-col-capacity">
						<?php esc_html_e( 'Soft / Hard Capacity', 'bb-groomflow' ); ?>
						<span class="bbgf-help bbgf-help--header dashicons dashicons-editor-help" role="img" aria-label="<?php esc_attr_e( 'Soft limit triggers warnings; hard limit blocks new cards.', 'bb-groomflow' ); ?>" title="<?php esc_attr_e( 'Soft limit triggers warnings; hard limit blocks new cards.', 'bb-groomflow' ); ?>"></span>
					</th>
					<th scope="col" class="bbgf-col-timers">
						<?php esc_html_e( 'Timer Thresholds (min)', 'bb-groomflow' ); ?>
						<span class="bbgf-help bbgf-help--header dashicons dashicons-editor-help" role="img" aria-label="<?php esc_attr_e( 'Timers shift from green to yellow to red after the specified minute thresholds.', 'bb-groomflow' ); ?>" title="<?php esc_attr_e( 'Timers shift from green to yellow to red after the specified minute thresholds.', 'bb-groomflow' ); ?>"></span>
					</th>
					<th scope="col"><?php esc_html_e( 'Description', 'bb-groomflow' ); ?></th>
					<th scope="col" class="bbgf-col-usage">
						<?php esc_html_e( 'Usage', 'bb-groomflow' ); ?>
						<span class="bbgf-help bbgf-help--header dashicons dashicons-editor-help" role="img" aria-label="<?php esc_attr_e( 'Shows which views reference this stage.', 'bb-groomflow' ); ?>" title="<?php esc_attr_e( 'Shows which views reference this stage.', 'bb-groomflow' ); ?>"></span>
					</th>
					<th scope="col" class="bbgf-col-actions"><span class="screen-reader-text"><?php esc_html_e( 'Actions', 'bb-groomflow' ); ?></span></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $stages ) ) : ?>
					<tr>
						<td colspan="8"><?php esc_html_e( 'No stages defined yet. Add your first stage below.', 'bb-groomflow' ); ?></td>
					</tr>
				<?php else : ?>
					<?php foreach ( $stages as $index => $stage ) : ?>
						<?php
						$stage_id      = (int) $stage['id'];
						$stage_key     = (string) $stage['stage_key'];
						$usage_list    = $usage_map[ $stage_key ]['views'] ?? array();
						$usage_names   = wp_list_pluck( $usage_list, 'name' );
						$usage_summary = '';
						if ( ! empty( $usage_names ) ) {
							$usage_summary = ' - ' . implode( "\n - ", $usage_names );
						}
						$initial_order = (int) ( $stage['sort_order'] ?? 0 );
						if ( $initial_order <= 0 ) {
							$initial_order = $index + 1;
						}
						?>
						<tr class="bbgf-stage-row">
							<td class="bbgf-col-order">
								<span class="bbgf-drag-handle dashicons dashicons-menu" role="img" aria-label="<?php esc_attr_e( 'Drag to reorder stage', 'bb-groomflow' ); ?>" tabindex="0"></span>
								<span class="bbgf-order-display"><?php echo esc_html( $initial_order ); ?></span>
								<input type="hidden" name="stage[<?php echo esc_attr( $stage_id ); ?>][sort_order]" value="<?php echo esc_attr( $initial_order ); ?>" class="bbgf-order-input" />
							</td>
							<td class="bbgf-col-key">
								<label class="screen-reader-text" for="bbgf-stage-key-<?php echo esc_attr( $stage_id ); ?>">
									<?php esc_html_e( 'Stage key', 'bb-groomflow' ); ?>
								</label>
								<input type="text" id="bbgf-stage-key-<?php echo esc_attr( $stage_id ); ?>" name="stage[<?php echo esc_attr( $stage_id ); ?>][stage_key]" value="<?php echo esc_attr( $stage_key ); ?>" required />
							</td>
							<td>
								<label class="screen-reader-text" for="bbgf-stage-label-<?php echo esc_attr( $stage_id ); ?>">
									<?php esc_html_e( 'Stage label', 'bb-groomflow' ); ?>
								</label>
								<input type="text" id="bbgf-stage-label-<?php echo esc_attr( $stage_id ); ?>" name="stage[<?php echo esc_attr( $stage_id ); ?>][label]" value="<?php echo esc_attr( $stage['label'] ); ?>" required />
							</td>
							<td class="bbgf-col-capacity">
								<div class="bbgf-capacity-group" aria-describedby="bbgf-capacity-help">
									<input type="number" min="0" class="small-text bbgf-capacity-input" name="stage[<?php echo esc_attr( $stage_id ); ?>][capacity_soft_limit]" value="<?php echo esc_attr( $stage['capacity_soft_limit'] ); ?>" />
									<span aria-hidden="true">/</span>
									<input type="number" min="0" class="small-text bbgf-capacity-input" name="stage[<?php echo esc_attr( $stage_id ); ?>][capacity_hard_limit]" value="<?php echo esc_attr( $stage['capacity_hard_limit'] ); ?>" />
									<span class="bbgf-help dashicons dashicons-editor-help" role="img" aria-label="<?php esc_attr_e( 'Soft limit triggers warnings; hard limit blocks new cards.', 'bb-groomflow' ); ?>" title="<?php esc_attr_e( 'Soft limit triggers warnings; hard limit blocks new cards.', 'bb-groomflow' ); ?>"></span>
								</div>
							</td>
							<td class="bbgf-col-timers">
								<div class="bbgf-threshold-group" aria-describedby="bbgf-threshold-help">
									<input type="number" min="0" class="small-text bbgf-threshold-input bbgf-threshold-green" name="stage[<?php echo esc_attr( $stage_id ); ?>][timer_threshold_green]" value="<?php echo esc_attr( $stage['timer_threshold_green'] ); ?>" />
									<input type="number" min="0" class="small-text bbgf-threshold-input bbgf-threshold-yellow" name="stage[<?php echo esc_attr( $stage_id ); ?>][timer_threshold_yellow]" value="<?php echo esc_attr( $stage['timer_threshold_yellow'] ); ?>" />
									<input type="number" min="0" class="small-text bbgf-threshold-input bbgf-threshold-red" name="stage[<?php echo esc_attr( $stage_id ); ?>][timer_threshold_red]" value="<?php echo esc_attr( $stage['timer_threshold_red'] ); ?>" />
									<span class="bbgf-help dashicons dashicons-editor-help" role="img" aria-label="<?php esc_attr_e( 'Timers shift from green to yellow to red after these minute thresholds.', 'bb-groomflow' ); ?>" title="<?php esc_attr_e( 'Timers shift from green to yellow to red after these minute thresholds.', 'bb-groomflow' ); ?>"></span>
								</div>
							</td>
							<td>
								<textarea rows="2" name="stage[<?php echo esc_attr( $stage_id ); ?>][description]" placeholder="<?php esc_attr_e( 'Optional notes for staff context', 'bb-groomflow' ); ?>"><?php echo esc_textarea( $stage['description'] ?? '' ); ?></textarea>
							</td>
							<td class="bbgf-col-usage">
								<?php if ( empty( $usage_list ) ) : ?>
									<span class="bbgf-pill bbgf-pill--muted"><?php esc_html_e( 'Unused', 'bb-groomflow' ); ?></span>
								<?php else : ?>
									<ul>
										<?php foreach ( $usage_list as $view ) : ?>
											<li><?php echo esc_html( $view['name'] ); ?></li>
										<?php endforeach; ?>
									</ul>
								<?php endif; ?>
							</td>
							<td class="bbgf-col-actions">
				<button type="submit" class="button button-link-delete bbgf-stage-delete" name="bbgf_stage_action[<?php echo esc_attr( $stage_id ); ?>]" value="delete" data-stage-id="<?php echo esc_attr( $stage_id ); ?>" data-stage-key="<?php echo esc_attr( $stage_key ); ?>" data-stage-label="<?php echo esc_attr( $stage['label'] ); ?>" data-stage-usage="<?php echo esc_attr( $usage_summary ); ?>">
									<?php esc_html_e( 'Remove', 'bb-groomflow' ); ?>
								</button>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>
		<div class="bbgf-stages-footer">
			<button type="submit" class="button button-primary bbgf-stages-save" data-bbgf-stage-save><?php esc_html_e( 'Save stages', 'bb-groomflow' ); ?></button>
		</div>
	</form>

	<hr class="wp-header-end" />

	<h2><?php esc_html_e( 'Add Stage', 'bb-groomflow' ); ?></h2>
	<form method="post" action="<?php echo esc_url( $this->get_page_url() ); ?>" class="bbgf-add-stage-form">
		<?php wp_nonce_field( 'bbgf_manage_stage', 'bbgf_stage_nonce' ); ?>
		<input type="hidden" name="bbgf_stage_action" value="create" />

		<table class="form-table" role="presentation">
			<tbody>
				<tr>
					<th scope="row"><label for="bbgf-new-stage-key"><?php esc_html_e( 'Stage key', 'bb-groomflow' ); ?></label></th>
					<td>
						<input type="text" class="regular-text" id="bbgf-new-stage-key" name="new_stage[stage_key]" required placeholder="<?php esc_attr_e( 'check-in', 'bb-groomflow' ); ?>" />
						<p class="description"><?php esc_html_e( 'Lowercase slug used in URLs and automations (letters, numbers, hyphens).', 'bb-groomflow' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="bbgf-new-stage-label"><?php esc_html_e( 'Label', 'bb-groomflow' ); ?></label></th>
					<td>
						<input type="text" class="regular-text" id="bbgf-new-stage-label" name="new_stage[label]" required placeholder="<?php esc_attr_e( 'Check-In', 'bb-groomflow' ); ?>" />
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="bbgf-new-stage-description"><?php esc_html_e( 'Description (optional)', 'bb-groomflow' ); ?></label></th>
					<td>
						<textarea id="bbgf-new-stage-description" name="new_stage[description]" rows="3" class="large-text" placeholder="<?php esc_attr_e( 'Visible to staff as guidance.', 'bb-groomflow' ); ?>"></textarea>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Capacity limits', 'bb-groomflow' ); ?></th>
					<td>
						<div class="bbgf-capacity-group" aria-describedby="bbgf-capacity-help">
							<input type="number" min="0" class="small-text bbgf-capacity-input" name="new_stage[capacity_soft_limit]" placeholder="<?php esc_attr_e( 'Soft', 'bb-groomflow' ); ?>" />
							<span aria-hidden="true">/</span>
							<input type="number" min="0" class="small-text bbgf-capacity-input" name="new_stage[capacity_hard_limit]" placeholder="<?php esc_attr_e( 'Hard', 'bb-groomflow' ); ?>" />
							<span class="bbgf-help dashicons dashicons-editor-help" role="img" aria-label="<?php esc_attr_e( 'Soft limit triggers warnings; hard limit blocks new cards.', 'bb-groomflow' ); ?>" title="<?php esc_attr_e( 'Soft limit triggers warnings; hard limit blocks new cards.', 'bb-groomflow' ); ?>"></span>
						</div>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Timer thresholds (minutes)', 'bb-groomflow' ); ?></th>
					<td>
						<div class="bbgf-threshold-group" aria-describedby="bbgf-threshold-help">
							<input type="number" min="0" class="small-text bbgf-threshold-input bbgf-threshold-green" name="new_stage[timer_threshold_green]" placeholder="<?php esc_attr_e( 'Green', 'bb-groomflow' ); ?>" />
							<input type="number" min="0" class="small-text bbgf-threshold-input bbgf-threshold-yellow" name="new_stage[timer_threshold_yellow]" placeholder="<?php esc_attr_e( 'Yellow', 'bb-groomflow' ); ?>" />
							<input type="number" min="0" class="small-text bbgf-threshold-input bbgf-threshold-red" name="new_stage[timer_threshold_red]" placeholder="<?php esc_attr_e( 'Red', 'bb-groomflow' ); ?>" />
							<span class="bbgf-help dashicons dashicons-editor-help" role="img" aria-label="<?php esc_attr_e( 'Timers shift from green to yellow to red after these minute thresholds.', 'bb-groomflow' ); ?>" title="<?php esc_attr_e( 'Timers shift from green to yellow to red after these minute thresholds.', 'bb-groomflow' ); ?>"></span>
						</div>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="bbgf-new-stage-order"><?php esc_html_e( 'Default order', 'bb-groomflow' ); ?></label></th>
					<td>
						<input type="number" min="0" class="small-text" id="bbgf-new-stage-order" name="new_stage[sort_order]" placeholder="<?php esc_attr_e( '0', 'bb-groomflow' ); ?>" />
						<p class="description"><?php esc_html_e( 'Used when new views are created; existing views keep their custom ordering.', 'bb-groomflow' ); ?></p>
					</td>
				</tr>
			</tbody>
		</table>

		<?php submit_button( __( 'Add Stage', 'bb-groomflow' ) ); ?>
	</form>
</div>
