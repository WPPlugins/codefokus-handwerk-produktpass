<?php
/**
 * Languages of the passports.
 *
 * The free plugin issues passports in the site language. Multilingual plugins
 * (e.g. via the Pro add-on) can add languages through the filters below.
 *
 * @package HandwerkProduktpass
 */

defined( 'ABSPATH' ) || exit;

class HWPP_I18n {

	/**
	 * Active languages as array( 'de' => 'de_DE' ).
	 */
	public static function languages() {
		$locale = get_locale();
		/**
		 * Languages in which passports are issued (code => locale).
		 *
		 * @param array $languages Default: site language only.
		 */
		$out = (array) apply_filters( 'hwpp_languages', array( substr( $locale, 0, 2 ) => $locale ) );
		return $out ? $out : array( substr( $locale, 0, 2 ) => $locale );
	}

	public static function default_language() {
		/**
		 * Main language of the passports.
		 *
		 * @param string $lang Two-letter code of the site language.
		 */
		return (string) apply_filters( 'hwpp_default_language', substr( get_locale(), 0, 2 ) );
	}

	/**
	 * Language of an order.
	 *
	 * @param WC_Order|null $order Order.
	 */
	public static function order_language( $order ) {
		/**
		 * Language of an order (e.g. stored by a translation plugin).
		 *
		 * @param string        $lang  Default language.
		 * @param WC_Order|null $order Order.
		 */
		return (string) apply_filters( 'hwpp_order_language', self::default_language(), $order );
	}

	/**
	 * ID of the translation of a post (product or material) in a language, 0 if none.
	 */
	public static function translated_id( $post_id, $post_type, $lang ) {
		return (int) apply_filters( 'hwpp_translated_id', 0, $post_id, $post_type, $lang );
	}

	/**
	 * Translation of a free-text setting (e.g. passport title).
	 */
	public static function translate_string( $key, $value, $lang ) {
		return (string) apply_filters( 'hwpp_translate_string', $value, $key, $lang );
	}

	/**
	 * Temporarily switches the locale (for translated labels in the passport).
	 */
	public static function switch_to( $lang, array $languages ) {
		$locale = isset( $languages[ $lang ] ) ? $languages[ $lang ] : '';
		if ( $locale && $locale !== determine_locale() && function_exists( 'switch_to_locale' ) ) {
			return switch_to_locale( $locale );
		}
		return false;
	}
}
