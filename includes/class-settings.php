<?php
/**
 * Einstellungen: Standardwerte, Lesen, Bereinigen.
 *
 * @package HandwerkProduktpass
 */

defined( 'ABSPATH' ) || exit;

class HWPP_Settings {

	const OPTION = 'hwpp_settings';

	/** @var array|null */
	private static $cache = null;

	public static function defaults() {
		$sections = array();
		foreach ( array_keys( HWPP_Fields::sections() ) as $key ) {
			$sections[] = array(
				'key' => $key,
				'web' => 1,
				'pdf' => in_array( $key, array( 'manufacturer', 'story' ), true ) ? 0 : 1,
			);
		}

		$defaults = array(
			// Unternehmens-Stammdaten (Wirtschaftsakteur).
			'company_name'            => get_bloginfo( 'name' ),
			'company_street'          => get_option( 'woocommerce_store_address', '' ),
			'company_zip'             => get_option( 'woocommerce_store_postcode', '' ),
			'company_city'            => get_option( 'woocommerce_store_city', '' ),
			'company_country'         => self::store_country(),
			'company_email'           => get_option( 'admin_email' ),
			'company_phone'           => '',
			'company_website'         => home_url( '/' ),
			'company_vat'             => '',
			'company_register'        => '',
			'default_certifications'  => '',
			'trade'                   => 'generic',

			// Regeln.
			'required_fields'         => array( 'manufacturing_country', 'materials', 'care', 'repairability' ),
			'require_material_origin' => 1,
			'block_publish'           => 0,
			'trigger_statuses'        => array( 'processing', 'completed' ),
			'serial_format'           => 'DPP-{year}-{order}-{pos}',
			'product_categories'      => array(), // leer = alle Produkte.

			// Auslieferung.
			'email_links'             => 1,
			'account_links'           => 1,
			'public_slug'             => 'produktpass',
			'restricted_fields'       => array(),

			// Design.
			'design'                  => array(
				'primary_color'  => '#2f4f3a',
				'accent_color'   => '#b08d57',
				'background'     => '#faf8f3',
				'font'           => 'serif',
				'logo_id'        => 0,
				'passport_title' => __( 'Digital product passport', 'codefokus-handwerk-produktpass' ),
				'sections'       => $sections,
			),

			'delete_on_uninstall'     => 0,
		);

		/**
		 * Default settings. Extensions add their own keys here.
		 *
		 * @param array $defaults Default values.
		 */
		$defaults = (array) apply_filters( 'hwpp_settings_defaults', $defaults );
		return $defaults;
	}

	/**
	 * Forget the cached settings (e.g. after an extension registered new defaults).
	 */
	public static function flush_cache() {
		self::$cache = null;
	}

	private static function store_country() {
		$raw = (string) get_option( 'woocommerce_default_country', 'DE' );
		$parts = explode( ':', $raw );
		return $parts[0] ? $parts[0] : 'DE';
	}

	public static function all() {
		if ( null === self::$cache ) {
			$saved    = get_option( self::OPTION, array() );
			$defaults = self::defaults();
			$merged   = array_merge( $defaults, is_array( $saved ) ? $saved : array() );
			$merged['design'] = array_merge( $defaults['design'], isset( $saved['design'] ) && is_array( $saved['design'] ) ? $saved['design'] : array() );
			$merged['design']['sections'] = self::normalize_sections( $merged['design']['sections'] );
			self::$cache = $merged;
		}
		return self::$cache;
	}

	public static function get( $key, $fallback = null ) {
		$all = self::all();
		return array_key_exists( $key, $all ) ? $all[ $key ] : $fallback;
	}

	public static function design( $key = null ) {
		$design = self::get( 'design' );
		if ( null === $key ) {
			return $design;
		}
		return isset( $design[ $key ] ) ? $design[ $key ] : null;
	}

	public static function update( array $values ) {
		$current = get_option( self::OPTION, array() );
		$current = is_array( $current ) ? $current : array();
		if ( isset( $values['design'] ) ) {
			$values['design'] = array_merge( isset( $current['design'] ) ? (array) $current['design'] : array(), $values['design'] );
		}
		update_option( self::OPTION, array_merge( $current, $values ) );
		self::$cache = null;
	}

	/**
	 * Stellt sicher, dass alle bekannten Abschnitte genau einmal vorkommen.
	 */
	public static function normalize_sections( $sections ) {
		$known = array_keys( HWPP_Fields::sections() );
		$out   = array();
		$seen  = array();
		foreach ( (array) $sections as $s ) {
			if ( empty( $s['key'] ) || ! in_array( $s['key'], $known, true ) || isset( $seen[ $s['key'] ] ) ) {
				continue;
			}
			$seen[ $s['key'] ] = true;
			$out[] = array(
				'key' => $s['key'],
				'web' => empty( $s['web'] ) ? 0 : 1,
				'pdf' => empty( $s['pdf'] ) ? 0 : 1,
			);
		}
		foreach ( $known as $key ) {
			if ( ! isset( $seen[ $key ] ) ) {
				$out[] = array( 'key' => $key, 'web' => 1, 'pdf' => in_array( $key, array( 'manufacturer', 'story' ), true ) ? 0 : 1 );
			}
		}
		return $out;
	}

	public static function sanitize_color( $value, $fallback ) {
		$color = sanitize_hex_color( $value );
		return $color ? $color : $fallback;
	}

	/**
	 * Order statuses that trigger generation.
	 */
	public static function status_choices() {
		$statuses = function_exists( 'wc_get_order_statuses' ) ? wc_get_order_statuses() : array();
		$out      = array();
		foreach ( $statuses as $key => $label ) {
			$key = 'wc-' === substr( $key, 0, 3 ) ? substr( $key, 3 ) : $key;
			if ( in_array( $key, array( 'pending', 'failed', 'cancelled', 'refunded', 'checkout-draft' ), true ) ) {
				continue;
			}
			$out[ $key ] = $label;
		}
		return $out;
	}
}
