<?php
/**
 * Manage Visits admin view.
 *
 * @package BB_GroomFlow
 */

use BBGF\Admin\Manage_Visits_Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap bbgf-manage-visits">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Manage Visits', 'bb-groomflow' ); ?></h1>
	<p class="description">
		<?php esc_html_e( 'Review visit history, reopen checked-out visits, and export filtered results.', 'bb-groomflow' ); ?>
	</p>

	<form method="get">
		<input type="hidden" name="page" value="<?php echo esc_attr( Manage_Visits_Admin::PAGE_SLUG ); ?>" />
		<?php
		$list_table->search_box( __( 'Search visits', 'bb-groomflow' ), 'bbgf-manage-visits' );
		$list_table->display();
		?>
	</form>
</div>
