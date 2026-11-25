<?php
/**
 * Public pass page (/produktpass/{id}/) and certificate download.
 *
 * @package HandwerkProduktpass
 */

defined( 'ABSPATH' ) || exit;

class HWPP_Public_View {

	public static function init() {
		add_action( 'init', array( __CLASS__, 'add_rewrite_rules' ) );
		add_action( 'init', array( __CLASS__, 'maybe_flush' ), 99 );
		add_filter( 'query_vars', array( __CLASS__, 'query_vars' ) );
		add_action( 'template_redirect', array( __CLASS__, 'dispatch' ), 1 );
		add_shortcode( 'hwpp_verify', array( __CLASS__, 'shortcode_verify' ) );
	}

	public static function add_rewrite_rules() {
		$slug = self::slug();
		add_rewrite_rule( '^' . preg_quote( $slug, '/' ) . '/([a-f0-9]{32})/?$', 'index.php?hwpp_pass=$matches[1]', 'top' );
		/**
		 * Lets extensions register further passport addresses.
		 *
		 * @param string $slug Base path of passport pages.
		 */
		do_action( 'hwpp_rewrite_rules', $slug );
	}

	/** Base path of the passport pages (e.g. "produktpass"). */
	public static function slug() {
		$slug = sanitize_title( HWPP_Settings::get( 'public_slug', 'produktpass' ) );
		return $slug ? $slug : 'produktpass';
	}

	public static function maybe_flush() {
		if ( get_option( 'hwpp_flush_rewrite' ) ) {
			delete_option( 'hwpp_flush_rewrite' );
			flush_rewrite_rules( false );
		}
	}

	public static function query_vars( $vars ) {
		$vars[] = 'hwpp_pass';
		return $vars;
	}

	/**
	 * Reads a public request parameter (query var or GET parameter).
	 *
	 * @param string $key Name.
	 */
	public static function qv( $key ) {
		$v = get_query_var( $key );
		if ( ! $v && isset( $_GET[ $key ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification -- public read access.
			$v = sanitize_text_field( wp_unslash( $_GET[ $key ] ) ); // phpcs:ignore WordPress.Security.NonceVerification
		}
		return (string) $v;
	}

	/** Requested display language (?pp_lang=). */
	public static function requested_lang() {
		return isset( $_GET['pp_lang'] ) ? sanitize_key( wp_unslash( $_GET['pp_lang'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
	}

	/** Headers for every passport page: never cache, never index. */
	public static function send_headers() {
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- Standard constant read by caching plugins: never cache passport pages.
		}
		nocache_headers();
		header( 'X-Robots-Tag: noindex, nofollow', true );
	}

	public static function dispatch() {
		$uid = self::qv( 'hwpp_pass' );
		if ( ! $uid ) {
			return;
		}
		self::send_headers();
		self::serve( HWPP_Repository::find_by_uid( $uid ) );
	}

	/**
	 * Shows a passport (or the "not found" page) and ends the request.
	 *
	 * @param object|null $row Passport record.
	 */
	public static function serve( $row ) {
		if ( ! $row ) {
			status_header( 404 );
			self::render_not_found();
			exit;
		}

		$view = self::prepare( $row, self::requested_lang() );
		$view = self::maybe_unlock( $view );

		/**
		 * Fires before the passport page is rendered. Extensions may answer the
		 * request themselves (e.g. certificate PDF) and exit.
		 *
		 * @param array  $view Prepared passport data.
		 * @param object $row  Passport record.
		 */
		do_action( 'hwpp_serve_passport', $view, $row );

		self::render_template( $view );
		if ( $view['switched'] ) {
			restore_previous_locale();
		}
		exit;
	}

	/**
	 * Includes the passport template.
	 *
	 * @param array $view Prepared passport data.
	 */
	public static function render_template( array $view ) {
		status_header( 200 );
		$template = locate_template( 'hwpp/passport.php' );
		$template = $template ? $template : HWPP_DIR . 'templates/passport.php';
		$template = apply_filters( 'hwpp_passport_template', $template, $view );
		include $template;
	}

	public static function gtin14( $gtin ) {
		return str_pad( preg_replace( '/\D/', '', (string) $gtin ), 14, '0', STR_PAD_LEFT );
	}

	/* ---------------------------------------------------------------
	 * Protected data (access code for repair shops and authorities)
	 * --------------------------------------------------------------- */

	public static function access_code( $row ) {
		$raw = strtoupper( substr( hash_hmac( 'sha256', 'access|' . $row->uid, HWPP_Integrity::secret() ), 0, 10 ) );
		return substr( $raw, 0, 5 ) . '-' . substr( $raw, 5 );
	}

	public static function has_restricted() {
		return (bool) self::restricted_keys();
	}

	private static function maybe_unlock( array $view ) {
		$view['unlocked']     = false;
		$view['unlock_error'] = '';
		if ( ! self::has_restricted() ) {
			return $view;
		}
		$code = isset( $_REQUEST['hwpp_code'] ) ? strtoupper( preg_replace( '/[^A-Za-z0-9]/', '', sanitize_text_field( wp_unslash( $_REQUEST['hwpp_code'] ) ) ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		if ( '' === $code ) {
			return $view;
		}
		$key   = 'hwpp_unlock_' . md5( $view['row']->uid . ( isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '' ) );
		$tries = (int) get_transient( $key );
		if ( $tries >= 10 ) {
			$view['unlock_error'] = __( 'Too many failed attempts. Please try again in an hour.', 'codefokus-handwerk-produktpass' );
			return $view;
		}
		if ( hash_equals( str_replace( '-', '', self::access_code( $view['row'] ) ), $code ) ) {
			$view['unlocked'] = true;
		} else {
			set_transient( $key, $tries + 1, HOUR_IN_SECONDS );
			$view['unlock_error'] = __( 'The access code is not correct. It is printed on the certificate of authenticity under “Access code for repair businesses”.', 'codefokus-handwerk-produktpass' );
		}
		return $view;
	}

	/**
	 * Prepares all data for display and PDF.
	 */
	public static function prepare( $row, $requested_lang = '' ) {
		$payload   = HWPP_Repository::payload( $row );
		$content   = isset( $payload['content'] ) ? (array) $payload['content'] : array();
		$primary   = isset( $payload['primary_language'] ) ? $payload['primary_language'] : key( $content );
		$languages = isset( $payload['languages'] ) ? (array) $payload['languages'] : array();
		$lang      = $primary;

		if ( $requested_lang && isset( $content[ $requested_lang ] ) ) {
			$lang = $requested_lang;
		} elseif ( ! $requested_lang && ! empty( $_SERVER['HTTP_ACCEPT_LANGUAGE'] ) ) {
			$accept = strtolower( substr( sanitize_text_field( wp_unslash( $_SERVER['HTTP_ACCEPT_LANGUAGE'] ) ), 0, 2 ) );
			if ( isset( $content[ $accept ] ) ) {
				$lang = $accept;
			}
		}
		if ( ! isset( $content[ $lang ] ) ) {
			$lang = key( $content );
		}

		$switched = HWPP_I18n::switch_to( $lang, $languages );

		$view_unlocked = false;
		$correction = isset( $payload['correction'] ) ? (array) $payload['correction'] : array();
		$newer      = ! empty( $row->superseded_by ) ? HWPP_Repository::find_by_uid( $row->superseded_by ) : null;
		$older      = ! empty( $correction['supersedes'] ) ? HWPP_Repository::find_by_uid( $correction['supersedes'] ) : null;

		return array(
			'status'       => isset( $row->status ) ? $row->status : 'active',
			'notice'       => HWPP_Lifecycle::public_notice( $row ),
			'correction'   => $correction,
			'newer_url'    => $newer ? HWPP_Repository::public_url( $newer, $lang ) : '',
			'older_url'    => $older ? HWPP_Repository::uid_url( $older, $lang ) : '',
			'unlocked'     => $view_unlocked,
			'row'          => $row,
			'payload'      => $payload,
			'lang'         => $lang,
			'languages'    => array_keys( $content ),
			'c'            => isset( $content[ $lang ] ) ? $content[ $lang ] : array(),
			'verify'       => HWPP_Integrity::verify( $row ),
			'fingerprint'  => HWPP_Integrity::fingerprint( $row->hash ),
			'url'          => HWPP_Repository::public_url( $row ),
			'cert_url'     => HWPP_Repository::certificate_url( $row, $lang ),
			'design'       => HWPP_Settings::design(),
			'switched'     => $switched,
		);
	}

	/**
	 * Returns rows (label => value) for a section – shared by HTML and PDF.
	 *
	 * @return array<int, array{label:string, value:string}>
	 */
	public static function section_rows( $key, array $view, $context = 'web' ) {
		$c    = $view['c'];
		$p    = $view['payload'];
		$f    = isset( $c['fields'] ) ? $c['fields'] : array();
		$defs = HWPP_Fields::product_fields();
		$rows = array();
		$add  = static function ( $label, $value ) use ( &$rows ) {
			if ( '' !== trim( (string) $value ) ) {
				$rows[] = array( 'label' => $label, 'value' => (string) $value );
			}
		};
		$restricted = self::restricted_keys();
		$unlocked   = ! empty( $view['unlocked'] );

		// Alle Produktfelder dieses Abschnitts (inkl. Gewerk-Felder) in Feld-Reihenfolge.
		$section_fields = static function () use ( $key, $f, $defs, $add, $restricted, $unlocked ) {
			foreach ( $defs as $k => $def ) {
				if ( $def['section'] !== $key || ! isset( $f[ $k ] ) || in_array( $def['type'], array( 'materials', 'gallery' ), true ) ) {
					continue;
				}
				if ( in_array( $k, $restricted, true ) && ! $unlocked ) {
					continue;
				}
				// The history appears as running text without its own label.
				$add( 'story' === $k ? '' : $def['label'], self::display_value( $k, $def, $f[ $k ] ) );
			}
		};

		switch ( $key ) {
			case 'product':
				if ( 'pdf' !== $context && ! empty( $view['row']->serial ) ) {
					$add( __( 'Serial number', 'codefokus-handwerk-produktpass' ), $view['row']->serial );
				}
				$add( __( 'SKU', 'codefokus-handwerk-produktpass' ), isset( $p['product']['sku'] ) ? $p['product']['sku'] : '' );
				$add( __( 'GTIN / EAN', 'codefokus-handwerk-produktpass' ), isset( $p['product']['gtin'] ) ? $p['product']['gtin'] : '' );
				foreach ( isset( $c['attributes'] ) ? (array) $c['attributes'] : array() as $a ) {
					$add( $a['label'], $a['value'] );
				}
				$add( __( 'Dimensions (L × W × H)', 'codefokus-handwerk-produktpass' ), isset( $p['product']['dimensions'] ) && is_string( $p['product']['dimensions'] ) ? $p['product']['dimensions'] : '' );
				$add( __( 'Weight', 'codefokus-handwerk-produktpass' ), isset( $p['product']['weight'] ) ? $p['product']['weight'] : '' );
				$section_fields();
				if ( 'pdf' !== $context && empty( $view['model'] ) ) {
					$add( __( 'Issued on', 'codefokus-handwerk-produktpass' ), self::format_date( $view['row']->created_at ) );
				}
				break;

			case 'care':
				$section_fields();
				foreach ( isset( $c['materials'] ) ? (array) $c['materials'] : array() as $m ) {
					if ( ! empty( $m['care'] ) ) {
						$add( $m['name'], $m['care'] );
					}
				}
				break;

			case 'sustainability':
				$section_fields();
				$add( __( 'Company certifications', 'codefokus-handwerk-produktpass' ), isset( $c['company_certifications'] ) ? $c['company_certifications'] : '' );
				break;

			case 'manufacturer':
				$m = isset( $p['manufacturer'] ) ? $p['manufacturer'] : array();
				$add( __( 'Company', 'codefokus-handwerk-produktpass' ), isset( $m['name'] ) ? $m['name'] : '' );
				$address = trim( ( isset( $m['street'] ) ? $m['street'] : '' ) . ', ' . ( isset( $m['zip'] ) ? $m['zip'] : '' ) . ' ' . ( isset( $m['city'] ) ? $m['city'] : '' ) . ', ' . HWPP_Fields::country_name( isset( $m['country'] ) ? $m['country'] : '' ), ', ' );
				$add( __( 'Address', 'codefokus-handwerk-produktpass' ), $address );
				$add( __( 'Email', 'codefokus-handwerk-produktpass' ), isset( $m['email'] ) ? $m['email'] : '' );
				$add( __( 'Phone', 'codefokus-handwerk-produktpass' ), isset( $m['phone'] ) ? $m['phone'] : '' );
				$add( __( 'Website', 'codefokus-handwerk-produktpass' ), isset( $m['website'] ) ? $m['website'] : '' );
				$add( __( 'VAT ID', 'codefokus-handwerk-produktpass' ), isset( $m['vat'] ) ? $m['vat'] : '' );
				$add( __( 'Register', 'codefokus-handwerk-produktpass' ), isset( $m['register'] ) ? $m['register'] : '' );
				break;

			case 'verification':
				if ( ! empty( $view['model'] ) ) {
					break;
				}
				$add( __( 'Status', 'codefokus-handwerk-produktpass' ), $view['verify']['message'] );
				if ( 'active' !== $view['status'] ) {
					$add( __( 'Validity', 'codefokus-handwerk-produktpass' ), HWPP_Lifecycle::label( $view['status'] ) . ( ! empty( $view['row']->status_changed_at ) ? ' (' . self::format_date( $view['row']->status_changed_at ) . ')' : '' ) );
				}
				$add( __( 'Checksum (fingerprint)', 'codefokus-handwerk-produktpass' ), $view['fingerprint'] );
				break;

			default:
				$section_fields();
		}
		return apply_filters( 'hwpp_section_rows', $rows, $key, $view, $context );
	}

	/**
	 * Readable value of a field (countries, selection fields, units).
	 */
	public static function display_value( $k, array $def, $v ) {
		if ( 'country' === $def['type'] ) {
			return HWPP_Fields::country_name( $v );
		}
		if ( 'select' === $def['type'] ) {
			return isset( $def['options'][ $v ] ) ? $def['options'][ $v ] : $v;
		}
		if ( 'number' === $def['type'] ) {
			$num = (float) $v;
			$fmt = function_exists( 'number_format_i18n' ) ? number_format_i18n( $num, floor( $num ) === $num ? 0 : 1 ) : (string) $v;
			switch ( $k ) {
				case 'co2':
					return $fmt . ' kg CO₂e';
				case 'care_interval':
					/* translators: %s: months */
					return sprintf( __( 'every %s months', 'codefokus-handwerk-produktpass' ), $fmt );
				case 'work_hours':
					/* translators: %s: hours */
					return sprintf( __( 'approx. %s hours of handwork', 'codefokus-handwerk-produktpass' ), $fmt );
			}
			return $fmt;
		}
		return (string) $v;
	}

	/**
	 * Field keys that are only visible with an access code (e.g. for repair shops and authorities).
	 */
	public static function restricted_keys() {
		return (array) HWPP_Settings::get( 'restricted_fields', array() );
	}

	public static function material_lines( array $m, $unlocked = true ) {
		$restricted = self::restricted_keys();
		if ( ! $unlocked ) {
			if ( in_array( 'material_supplier', $restricted, true ) ) {
				unset( $m['supplier'] );
			}
		}
		$types = HWPP_Fields::material_types();
		$lines = array();
		if ( ! empty( $m['material_type'] ) && isset( $types[ $m['material_type'] ] ) ) {
			$lines[ __( 'Type', 'codefokus-handwerk-produktpass' ) ] = $types[ $m['material_type'] ];
		}
		$origin = trim( HWPP_Fields::country_name( isset( $m['origin_country'] ) ? $m['origin_country'] : '' ) . ( ! empty( $m['origin_region'] ) ? ' – ' . $m['origin_region'] : '' ), ' –' );
		if ( $origin ) {
			$lines[ __( 'Origin', 'codefokus-handwerk-produktpass' ) ] = $origin;
		}
		if ( ! empty( $m['supplier'] ) ) {
			$lines[ __( 'Supplier', 'codefokus-handwerk-produktpass' ) ] = $m['supplier'];
		}
		if ( ! empty( $m['certifications'] ) ) {
			$lines[ __( 'Certificates', 'codefokus-handwerk-produktpass' ) ] = $m['certifications'];
		}
		if ( isset( $m['recycled_share'] ) && '' !== $m['recycled_share'] ) {
			$lines[ __( 'Recycled content', 'codefokus-handwerk-produktpass' ) ] = $m['recycled_share'] . ' %';
		}
		if ( ! empty( $m['sustainability'] ) ) {
			$lines[ __( 'Sustainability', 'codefokus-handwerk-produktpass' ) ] = $m['sustainability'];
		}
		return $lines;
	}

	public static function format_date( $gmt ) {
		$ts = strtotime( $gmt . ' UTC' );
		return $ts ? wp_date( get_option( 'date_format' ), $ts ) : $gmt;
	}

	public static function language_name( $code ) {
		$names = array(
			'de' => 'Deutsch', 'en' => 'English', 'fr' => 'Français', 'it' => 'Italiano', 'es' => 'Español', 'nl' => 'Nederlands',
			'pl' => 'Polski', 'da' => 'Dansk', 'sv' => 'Svenska', 'cs' => 'Čeština', 'pt' => 'Português', 'fi' => 'Suomi', 'hu' => 'Magyar',
		);
		return isset( $names[ $code ] ) ? $names[ $code ] : strtoupper( $code );
	}

	public static function render_not_found() {
		$title = __( 'Product passport not found', 'codefokus-handwerk-produktpass' );
		echo '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex"><meta name="hwpp-status" content="not-found"><title>' . esc_html( $title ) . '</title>';
		echo '<style>body{font-family:system-ui,sans-serif;max-width:560px;margin:15vh auto;padding:0 20px;color:#333;line-height:1.6}h1{font-size:1.5rem}</style></head><body>';
		echo '<h1>' . esc_html( $title ) . '</h1><p>' . esc_html__( 'No product passport exists for this QR code or link. Please check that the link is complete. If you have doubts about the authenticity of a product, please contact the manufacturer directly.', 'codefokus-handwerk-produktpass' ) . '</p>';
		echo '<p><a href="' . esc_url( home_url( '/' ) ) . '">' . esc_html( get_bloginfo( 'name' ) ) . '</a></p></body></html>';
	}

	/**
	 * [hwpp_verify] – form for checking authenticity by serial number.
	 */
	public static function shortcode_verify() {
		$out = '<form class="hwpp-verify" method="get"><label>' . esc_html__( 'Check serial number', 'codefokus-handwerk-produktpass' ) . ' <input type="text" name="hwpp_serial" required></label> <button type="submit">' . esc_html__( 'Check', 'codefokus-handwerk-produktpass' ) . '</button></form>';
		if ( ! empty( $_GET['hwpp_serial'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			global $wpdb;
			$serial = sanitize_text_field( wp_unslash( $_GET['hwpp_serial'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
			$t      = HWPP_Install::table();
			$row    = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM %i WHERE serial = %s AND is_test = 0 LIMIT 1", $t, $serial ) ); // phpcs:ignore WordPress.DB
			if ( $row ) {
				$res  = HWPP_Integrity::verify( $row );
				$out .= '<p class="hwpp-verify-result">' . esc_html( $res['message'] ) . ' <a href="' . esc_url( HWPP_Repository::public_url( $row ) ) . '">' . esc_html__( 'Open product passport', 'codefokus-handwerk-produktpass' ) . '</a></p>';
			} else {
				$out .= '<p class="hwpp-verify-result">' . esc_html__( 'No product passport was found for this serial number.', 'codefokus-handwerk-produktpass' ) . '</p>';
			}
		}
		return $out;
	}
}
