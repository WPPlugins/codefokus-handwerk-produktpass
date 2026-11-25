<?php
/**
 * Plugin Name:       codefokus Handwerk Produktpass for WooCommerce
 * Plugin URI:        https://wordpress.org/plugins/codefokus-handwerk-produktpass/
 * Description:       Automatically issues a tamper-evident digital product passport (DPP) with QR code for every item sold in WooCommerce. Material library, trade presets, required fields, corrections and status tracking, test mode and diagnostics. Built for workshops, furniture makers, jewellers and other craft businesses.
 * Version:           2.0.0
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * Requires Plugins:  woocommerce
 * Author:            codefokus
 * Author URI:        https://codefokus.de/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       codefokus-handwerk-produktpass
 * WC requires at least: 7.0
 * WC tested up to:   11.1
 *
 * @package HandwerkProduktpass
 */

defined( 'ABSPATH' ) || exit;

define( 'HWPP_VERSION', '2.0.0' );
define( 'HWPP_DB_VERSION', '3' );
define( 'HWPP_FILE', __FILE__ );
define( 'HWPP_DIR', plugin_dir_path( __FILE__ ) );
define( 'HWPP_URL', plugin_dir_url( __FILE__ ) );
define( 'HWPP_MIN_PHP', '7.4' );

spl_autoload_register(
	static function ( $class ) {
		if ( 0 !== strpos( $class, 'HWPP_' ) ) {
			return;
		}
		$file = HWPP_DIR . 'includes/class-' . strtolower( str_replace( '_', '-', substr( $class, 5 ) ) ) . '.php';
		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}
);

register_activation_hook( __FILE__, array( 'HWPP_Install', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'HWPP_Install', 'deactivate' ) );

// Declare HPOS and cart/checkout block compatibility.
add_action(
	'before_woocommerce_init',
	static function () {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
		}
	}
);

// Start on "init" so that translations are loaded at the right time (WordPress 6.7+).
add_action( 'init', array( 'HWPP_Plugin', 'instance' ), 1 );
