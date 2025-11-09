<?php
/**
 * Notification logs admin view.
 *
 * @package BB_GroomFlow
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap bbgf-wrap">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Notification Activity', 'bb-groomflow' ); ?></h1>
	<p class="description">
		<?php esc_html_e( 'Review delivery history for stage-triggered notifications. Use the filters to narrow by stage, status, or template.', 'bb-groomflow' ); ?>
	</p>

	<form method="get">
		<input type="hidden" name="page" value="<?php echo esc_attr( \BBGF\Admin\Notification_Logs_Admin::PAGE_SLUG ); ?>" />
		<?php
		$list_table->search_box( __( 'Search logs', 'bb-groomflow' ), 'bbgf-notification-logs' );
		$list_table->display();
		?>
	</form>
</div>
