<?php
/**
 * Plugin Name: WP Rephrase Intro
 * Plugin URI: https://github.com/lobsi97/amplifyapp
 * Description: Reformule automatiquement les introductions des articles de blog et pages WordPress en utilisant une API d'intelligence artificielle (Claude ou OpenAI).
 * Version: 1.0.0
 * Author: lobsi97
 * Author URI: https://github.com/lobsi97
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: wp-rephrase-intro
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WPRI_VERSION', '1.0.0' );
define( 'WPRI_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WPRI_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once WPRI_PLUGIN_DIR . 'includes/class-wpri-settings.php';
require_once WPRI_PLUGIN_DIR . 'includes/class-wpri-rephraser.php';
require_once WPRI_PLUGIN_DIR . 'includes/class-wpri-metabox.php';
require_once WPRI_PLUGIN_DIR . 'includes/class-wpri-ajax.php';

/**
 * Initialize the plugin.
 */
function wpri_init() {
	load_plugin_textdomain( 'wp-rephrase-intro', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

	WPRI_Settings::init();
	WPRI_Metabox::init();
	WPRI_Ajax::init();
}
add_action( 'plugins_loaded', 'wpri_init' );

/**
 * Plugin activation hook.
 */
function wpri_activate() {
	$defaults = array(
		'wpri_api_provider' => 'openai',
		'wpri_api_key'      => '',
		'wpri_model'        => '',
		'wpri_intro_length' => 2,
		'wpri_tone'         => 'professional',
		'wpri_language'     => 'fr',
	);

	foreach ( $defaults as $key => $value ) {
		if ( false === get_option( $key ) ) {
			add_option( $key, $value );
		}
	}
}
register_activation_hook( __FILE__, 'wpri_activate' );
