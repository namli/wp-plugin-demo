<?php

/**
 * Define the internationalization functionality
 *
 * Loads and defines the internationalization files for this plugin
 * so that it is ready for translation.
 *
 * @link       https://www.phpninja.info/
 * @since      1.0.0
 *
 * @package    Woodeliveryratesimport
 * @subpackage Woodeliveryratesimport/includes
 */

/**
 * Define the internationalization functionality.
 *
 * Loads and defines the internationalization files for this plugin
 * so that it is ready for translation.
 *
 * @since      1.0.0
 * @package    Woodeliveryratesimport
 * @subpackage Woodeliveryratesimport/includes
 * @author     Phpninga <contacto@phpninja.info>
 */
class Woodeliveryratesimport_i18n {


	/**
	 * Load the plugin text domain for translation.
	 *
	 * @since    1.0.0
	 */
	public function load_plugin_textdomain() {

		load_plugin_textdomain(
			'woodeliveryratesimport',
			false,
			dirname( dirname( plugin_basename( __FILE__ ) ) ) . '/languages/'
		);

	}



}
