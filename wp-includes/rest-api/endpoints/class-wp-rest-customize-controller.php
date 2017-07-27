<?php
/**
 * REST API: WP_REST_Customize_Controller class
 *
 * @package WordPress
 * @subpackage REST_API
 * @since 4.7.0
 */

/**
 * Core base controller for managing and interacting between Customizer REST API objects.
 *
 * @since 4.?.?
 */
abstract class WP_REST_Customize_Controller extends WP_REST_Controller {

	/**
	 * Ensure customize manager.
	 *
	 * @return WP_Customize_Manager Manager.
	 * @global WP_Customize_Manager $wp_customize
	 */
	public function ensure_customize_manager() {
		global $wp_customize;
		if ( empty( $wp_customize ) ) {
			$wp_customize = new WP_Customize_Manager(); // WPCS: global override ok.
		}
		if ( ! did_action( 'customize_register' ) ) {

			/** This action is documented in wp-includes/class-wp-customize-manager.php */
			do_action( 'customize_register', $wp_customize );
		}
		return $wp_customize;
	}
}
