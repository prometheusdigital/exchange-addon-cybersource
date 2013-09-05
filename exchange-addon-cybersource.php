<?php
/*
 * Plugin Name: iThemes Exchange - Cybersource Add-on
 * Version: 1.0.0
 * Description: Adds the ability for users to checkout with Cybersource.
 * Plugin URI: http://ithemes.com/exchange/cybersource/
 * Author: WebDevStudios
 * Author URI: http://webdevstudios.com
 * iThemes Package: exchange-addon-cybersource

 * Installation:
 * 1. Download and unzip the latest release zip file.
 * 2. If you use the WordPress plugin uploader to install this plugin skip to step 4.
 * 3. Upload the entire plugin directory to your `/wp-content/plugins/` directory.
 * 4. Activate the plugin through the 'Plugins' menu in WordPress Administration.
 *
*/

/**
 * This registers our plugin as a Cybersource addon
 *
 * @since 1.0.0
*/
function it_exchange_register_cybersource_addon() {
	$options = array(
		'name'              => __( 'Cybersource', 'it-l10n-exchange-addon-cybersource' ),
		'description'       => __( 'Process transactions via Cybersource.', 'it-l10n-exchange-addon-cybersource' ),
		'author'            => 'WebDevStudios',
		'author_url'        => 'http://webdevstudios.com',
		'icon'              => ITUtility::get_url_from_file( dirname( __FILE__ ) . '/lib/images/cybersource50px.png' ),
		'wizard-icon'       => ITUtility::get_url_from_file( dirname( __FILE__ ) . '/lib/images/wizard-cybersource.png' ),
		'file'              => dirname( __FILE__ ) . '/init.php',
		'category'          => 'transaction-methods',
		'settings-callback' => 'it_exchange_cybersource_addon_settings_callback',
	);
	it_exchange_register_addon( 'cybersource', $options );
}
add_action( 'it_exchange_register_addons', 'it_exchange_register_cybersource_addon' );

/**
 * Loads the translation data for WordPress
 *
 * @since 1.0.0
*/
function it_exchange_cybersource_set_textdomain() {
	load_plugin_textdomain( 'it-l10n-exchange-addon-cybersource', false, dirname( plugin_basename( __FILE__  ) ) . '/lang/' );
}
add_action( 'plugins_loaded', 'it_exchange_cybersource_set_textdomain' );

/**
 * Registers Plugin with iThemes updater class
 *
 * @since 1.0.0
 * @param object $updater ithemes updater object
*/
function ithemes_exchange_addon_cybersource_updater_register( $updater ) {
	    $updater->register( 'exchange-addon-cybersource', __FILE__ );
}
add_action( 'ithemes_updater_register', 'ithemes_exchange_addon_cybersource_updater_register' );
require( dirname( __FILE__ ) . '/lib/updater/load.php' );
