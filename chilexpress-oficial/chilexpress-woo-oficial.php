<?php

/**
 * The plugin bootstrap file 
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 
 * @since             1.4.0
 * @package           Chilexpress_Woo_Oficial
 *
 * @wordpress-plugin
 * Plugin Name:       Chilexpress Oficial para Woocommerce
 * Plugin URI:        https://developers.wschilexpress.com/
 * Description:       Soporte oficial de Chilexpress para Woocommerce
 * Version:           1.4.0
 * Author:            Chilexpress
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       chilexpress-woo-oficial
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}


/**
 * Currently plugin version.
 * Start at version 1.3.3 and use SemVer - https://semver.org
 * Rename this for your plugin and update it as you release new versions.
 */
define( 'CHILEXPRESS_WOO_OFICIAL_VERSION', '1.4.0' );

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-chilexpress-woo-oficial-activator.php
 */
function activate_chilexpress_woo_oficial() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-chilexpress-woo-oficial-activator.php'; // NOSONAR
	Chilexpress_Woo_Oficial_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-chilexpress-woo-oficial-deactivator.php
 */
function deactivate_chilexpress_woo_oficial() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-chilexpress-woo-oficial-deactivator.php'; // NOSONAR
	Chilexpress_Woo_Oficial_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_chilexpress_woo_oficial' );
register_deactivation_hook( __FILE__, 'deactivate_chilexpress_woo_oficial' );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path( __FILE__ ) . 'includes/class-chilexpress-woo-oficial.php'; // NOSONAR


$woocommerce_is_present = false;

$all_plugins = apply_filters('active_plugins', get_option('active_plugins'));
if (stripos(implode($all_plugins), 'woocommerce.php')) {
    $woocommerce_is_present = true;
}

/*
 * Check if WooCommerce is active
 */
if ( $woocommerce_is_present ) {
	function chilexpress_woo_oficial_shipping_method() {
		require_once plugin_dir_path( __FILE__ ) . 
			'includes/class-chilexpress-woo-oficial-shipping-method.php'; // NOSONAR

	}
	add_action( 'woocommerce_shipping_init', 'chilexpress_woo_oficial_shipping_method' );

	function add_chilexpress_woo_oficial_shipping_method( $methods ) {
        $methods[] = 'Chilexpress_Woo_Oficial_Shipping_Method';
        return $methods;
    }
 
    add_filter( 'woocommerce_shipping_methods', 'add_chilexpress_woo_oficial_shipping_method' );
}

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.4.0
 */
function run_chilexpress_woo_oficial() {

	$plugin = new Chilexpress_Woo_Oficial();
	$plugin->run();

}
add_action('plugins_loaded', 'run_chilexpress_woo_oficial', 10);
