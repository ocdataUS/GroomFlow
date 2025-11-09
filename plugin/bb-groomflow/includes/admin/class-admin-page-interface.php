<?php
/**
 * Admin page contract.
 *
 * @package BB_GroomFlow
 */

namespace BBGF\Admin;

/**
 * Common interface for admin pages.
 */
interface Admin_Page_Interface {
	/**
	 * Bootstraps hooks for the page.
	 *
	 * @return void
	 */
	public function register(): void;
}
