<?php
/**
 * Elementor widget skeleton for the GroomFlow board.
 *
 * @package BB_GroomFlow
 */

namespace BBGF\Elementor;

use Elementor\Controls_Manager;
use Elementor\Widget_Base;

/**
 * Elementor widget for rendering the placeholder GroomFlow board.
 */
class Board_Widget extends Widget_Base {
	/**
	 * Widget unique identifier.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return 'bbgf_board';
	}

	/**
	 * Widget display name.
	 *
	 * @return string
	 */
	public function get_title(): string {
		return __( 'GroomFlow Board (Preview)', 'bb-groomflow' );
	}

	/**
	 * Widget icon within Elementor.
	 *
	 * @return string
	 */
	public function get_icon(): string {
		return 'eicon-table';
	}

	/**
	 * Categories this widget belongs to.
	 *
	 * @return string[]
	 */
	public function get_categories(): array {
		return array( 'bbgf' );
	}

	/**
	 * Search keywords.
	 *
	 * @return string[]
	 */
	public function get_keywords(): array {
		return array( 'grooming', 'kanban', 'board', 'clients', 'pets', 'schedule' );
	}

	/**
	 * Register widget controls.
	 */
	protected function register_controls(): void {
		$this->start_controls_section(
			'content_section',
			array(
				'label' => __( 'Board Settings', 'bb-groomflow' ),
				'tab'   => Controls_Manager::TAB_CONTENT,
			)
		);

		$this->add_control(
			'view',
			array(
				'label'   => __( 'Board View', 'bb-groomflow' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'day',
				'options' => array(
					'day'     => __( 'Today (Sample)', 'bb-groomflow' ),
					'express' => __( 'Express (Sample)', 'bb-groomflow' ),
					'lobby'   => __( 'Lobby Display (Sample)', 'bb-groomflow' ),
				),
			)
		);

		$this->add_control(
			'show_switcher',
			array(
				'label'        => __( 'Show View Switcher', 'bb-groomflow' ),
				'type'         => Controls_Manager::SWITCHER,
				'label_on'     => __( 'Show', 'bb-groomflow' ),
				'label_off'    => __( 'Hide', 'bb-groomflow' ),
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'layout_section',
			array(
				'label' => __( 'Layout', 'bb-groomflow' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'column_note',
			array(
				'type'            => Controls_Manager::RAW_HTML,
				'raw'             => __( 'Full styling tools arrive in Sprint 4. For now, enjoy the calming preview as we wire up schemas and REST endpoints.', 'bb-groomflow' ),
				'content_classes' => 'elementor-descriptor',
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Render the widget output.
	 */
	protected function render(): void {
		$settings = $this->get_settings_for_display();
		$view     = is_string( $settings['view'] ?? '' ) ? $settings['view'] : 'day';

		if ( function_exists( '\\BBGF\\bbgf' ) ) {
			$markup = bbgf()->get_placeholder_board_markup(
				array(
					'view' => $view,
				)
			);

			echo $markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Board markup already escaped.

			if ( isset( $settings['show_switcher'] ) && 'yes' !== $settings['show_switcher'] ) {
				?>
				<script>
					document.addEventListener( 'DOMContentLoaded', function onBbgfReady() {
						var boardRoot = document.getElementById( 'bbgf-board-root' );
						if ( boardRoot ) {
							var toolbar = boardRoot.querySelector( '#bbgf-board-toolbar .bbgf-toolbar-view' );
							if ( toolbar ) {
								toolbar.setAttribute( 'hidden', 'hidden' );
							}
						}
					} );
				</script>
				<?php
			}
		} else {
			echo '<p>' . esc_html__( 'Activate GroomFlow to render this widget.', 'bb-groomflow' ) . '</p>';
		}
	}
}
