<?php
/**
 * Central plugin class: loads all components.
 *
 * @package HandwerkProduktpass
 */

defined( 'ABSPATH' ) || exit;

final class HWPP_Plugin {

	/** @var HWPP_Plugin|null */
	private static $instance = null;

	/** Product page of the Pro add-on. */
	const PRO_URL = 'https://codefokus.de/shop?p=handwerk-produktpass-digitaler-produktpass-fuer-woocommerce-und-handwerksbetriebe';

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Whether the Pro add-on is active. Only used to hide upgrade hints.
	 */
	public static function is_pro() {
		return defined( 'HWPP_PRO_VERSION' );
	}

	/**
	 * Link to the product page of the Pro add-on (plain link, no tracking parameters).
	 */
	public static function pro_url() {
		return self::PRO_URL . '#pakete';
	}

	private function __construct() {
		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action( 'admin_notices', array( $this, 'notice_missing_woocommerce' ) );
			return;
		}

		HWPP_Install::maybe_upgrade();

		HWPP_Materials::init();
		HWPP_Product_Data::init();
		HWPP_Generator::init();
		HWPP_Lifecycle::init();
		HWPP_Public_View::init();
		HWPP_Account::init();
		HWPP_Privacy::init();

		if ( is_admin() ) {
			HWPP_Admin::init();
			HWPP_Order_Admin::init();
			HWPP_Health::init();
			HWPP_Upsell::init();
		}

		/**
		 * Fires when Handwerk Produktpass is fully loaded. Extensions hook in here.
		 */
		do_action( 'hwpp_loaded' );
	}

	public function notice_missing_woocommerce() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		echo '<div class="notice notice-error"><p><strong>' . esc_html__( 'Handwerk Produktpass is not active yet.', 'codefokus-handwerk-produktpass' ) . '</strong> ';
		echo esc_html__( 'The plugin requires WooCommerce. Please install and activate WooCommerce first under “Plugins → Add New”. After that, the product passport works automatically.', 'codefokus-handwerk-produktpass' );
		echo '</p></div>';
	}
}
