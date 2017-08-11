<?php
/*
 * Plugin Name: ExchangeWP - CyberSource Add-on
 * Version: 1.0.9
 * Description: Adds the ability for users to checkout with CyberSource.
 * Plugin URI: http://exchangewp.com/downloads/cybersource/
 * Author: ExchangeWP
 * Author URI: https://exchangewp.com
 * ExchangeWP Package: exchange-addon-cybersource

 * Installation:
 * 1. Download and unzip the latest release zip file.
 * 2. If you use the WordPress plugin uploader to install this plugin skip to step 4.
 * 3. Upload the entire plugin directory to your `/wp-content/plugins/` directory.
 * 4. Activate the plugin through the 'Plugins' menu in WordPress Administration.
 *
*/

/**
 * This registers our plugin as a CyberSource addon
 *
 * @since 1.0.0
 */
function it_exchange_register_cybersource_addon() {

	$options = array(
		'name' => __( 'CyberSource', 'LION' ),
		'description' => __( 'Process transactions via CyberSource.', 'LION' ),
		'author' => 'ExchangeWP',
		'author_url' => 'https://exchangewp.com',
		'icon' => ITUtility::get_url_from_file( dirname( __FILE__ ) . '/lib/images/cybersource50px.png' ),
		'wizard-icon' => ITUtility::get_url_from_file( dirname( __FILE__ ) . '/lib/images/wizard-cybersource.png' ),
		'file' => dirname( __FILE__ ) . '/init.php',
		'category' => 'transaction-methods',
		'settings-callback' => 'it_exchange_cybersource_addon_settings_callback',
	);

	it_exchange_register_addon( 'cybersource', $options );

}
add_action( 'it_exchange_register_addons', 'it_exchange_register_cybersource_addon' );

/**
 * Require other add-ons that may be needed
 *
 * @since 1.0.0
 */
function it_exchange_cybersource_required_addons() {

	add_filter( 'it_exchange_billing_address_purchase_requirement_enabled', '__return_true' );

}
add_action( 'it_exchange_enabled_addons_loaded', 'it_exchange_cybersource_required_addons' );

/**
 * Loads the translation data for WordPress
 *
 * @since 1.0.0
 */
function it_exchange_cybersource_set_textdomain() {

	load_plugin_textdomain( 'LION', false, dirname( plugin_basename( __FILE__ ) ) . '/lang/' );

}
add_action( 'plugins_loaded', 'it_exchange_cybersource_set_textdomain' );

/**
 * Registers Plugin with iThemes updater class
 *
 * @since 1.0.0
 *
 * @param object $updater ithemes updater object
 */
function ithemes_exchange_addon_cybersource_updater_register( $updater ) {

	$updater->register( 'exchange-addon-cybersource', __FILE__ );

}
add_action( 'ithemes_updater_register', 'ithemes_exchange_addon_cybersource_updater_register' );

// require( dirname( __FILE__ ) . '/lib/updater/load.php' );

if ( ! class_exists( 'EDD_SL_Plugin_Updater' ) )  {
	require_once 'EDD_SL_Plugin_Updater.php';
}

function exchange_cybersource_plugin_updater() {

	// retrieve our license key from the DB
	// this is going to have to be pulled from a seralized array to get the actual key.
	// $license_key = trim( get_option( 'exchange_cybersource_license_key' ) );
	$exchangewp_cybersource_options = get_option( 'it-storage-exchange_addon_cybersource' );
	$license_key = $exchangewp_cybersource_options['cybersource_license'];

	// setup the updater
	$edd_updater = new EDD_SL_Plugin_Updater( 'https://exchangewp.com', __FILE__, array(
			'version' 		=> '1.0.9', 				// current version number
			'license' 		=> $license_key, 		// license key (used get_option above to retrieve from DB)
			'item_name' 	=> 'cybersource', 	  // name of this plugin
			'author' 	  	=> 'ExchangeWP',    // author of this plugin
			'url'       	=> home_url(),
			'wp_override' => true,
			'beta'		  	=> false
		)
	);
	// var_dump($edd_updater);
	// die();

}

add_action( 'admin_init', 'exchange_cybersource_plugin_updater', 0 );
