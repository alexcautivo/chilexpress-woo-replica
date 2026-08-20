<?php

/**
 * Define the internationalization functionality
 *
 * Loads and defines the internationalization files for this plugin
 * so that it is ready for translation.
 *
 
 * @since      1.3.2
 *
 * @package    Chilexpress_Woo_Oficial
 * @subpackage Chilexpress_Woo_Oficial/includes
 */

/**
 * Define the internationalization functionality.
 *
 * Loads and defines the internationalization files for this plugin
 * so that it is ready for translation.
 *
 * @since      1.3.2
 * @package    Chilexpress_Woo_Oficial
 * @subpackage Chilexpress_Woo_Oficial/includes
 * @author     Chilexpress
 */
class Chilexpress_Woo_Oficial_i18n {


	/**
	 * Load the plugin text domain for translation.
	 *
	 * @since    1.3.2
	 */
	public function load_plugin_textdomain() {

		load_plugin_textdomain(
			'chilexpress-woo-oficial',
			false,
			dirname( dirname( plugin_basename( __FILE__ ) ) ) . '/languages/'
		);

	}



}
