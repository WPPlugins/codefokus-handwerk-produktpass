<?php
/**
 * Forgery protection: hash (SHA-256), signature (HMAC) and hash chain.
 *
 * @package HandwerkProduktpass
 */

defined( 'ABSPATH' ) || exit;

class HWPP_Integrity {

	/**
	 * Canonical JSON: sorted keys, so the hash can be reproduced.
	 */
	public static function canonical( $data ) {
		return wp_json_encode( self::sort_keys( $data ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
	}

	private static function sort_keys( $data ) {
		if ( ! is_array( $data ) ) {
			return is_float( $data ) ? (string) $data : $data;
		}
		$is_list = array_keys( $data ) === range( 0, count( $data ) - 1 );
		if ( ! $is_list ) {
			ksort( $data, SORT_STRING );
		}
		foreach ( $data as $k => $v ) {
			$data[ $k ] = self::sort_keys( $v );
		}
		return $data;
	}

	public static function compute_hash( $row ) {
		$payload = json_decode( (string) $row->payload, true );
		$canon   = self::canonical( is_array( $payload ) ? $payload : array() );
		return hash(
			'sha256',
			implode( '|', array( 'hwpp-v1', $row->uid, $row->serial, (int) $row->order_id, (int) $row->item_id, (int) $row->unit_index, (int) $row->product_id, (int) $row->is_test, $row->created_at, $row->prev_hash, $canon ) )
		);
	}

	/**
	 * Signing key. Recommended: store it as HWPP_SECRET in wp-config.php, so that
	 * nobody with read-only database access can forge valid passports.
	 */
	public static function secret() {
		if ( defined( 'HWPP_SECRET' ) && strlen( (string) HWPP_SECRET ) >= 32 ) {
			return (string) HWPP_SECRET;
		}
		return (string) get_option( 'hwpp_secret' );
	}

	public static function secret_source() {
		return ( defined( 'HWPP_SECRET' ) && strlen( (string) HWPP_SECRET ) >= 32 ) ? 'config' : 'database';
	}

	public static function sign( $hash ) {
		return hash_hmac( 'sha256', $hash, self::secret() );
	}

	/**
	 * Checks a single passport.
	 *
	 * @return array{valid:bool,hash_ok:bool,sig_ok:bool,message:string}
	 */
	public static function verify( $row ) {
		$hash_ok = hash_equals( (string) $row->hash, self::compute_hash( $row ) );
		$sig_ok  = $hash_ok && hash_equals( (string) $row->signature, self::sign( $row->hash ) );
		if ( $hash_ok && $sig_ok ) {
			$msg = __( 'Genuine and unchanged. The data is exactly as it was at the time of issue.', 'codefokus-handwerk-produktpass' );
		} elseif ( ! $hash_ok ) {
			$msg = __( 'Warning: The data of this passport has been changed after it was issued. Please contact the manufacturer.', 'codefokus-handwerk-produktpass' );
		} else {
			$msg = __( 'Warning: The signature is invalid. This passport was not issued by this shop, or the shop key has been changed.', 'codefokus-handwerk-produktpass' );
		}
		return array(
			'valid'   => $hash_ok && $sig_ok,
			'hash_ok' => $hash_ok,
			'sig_ok'  => $sig_ok,
			'message' => $msg,
		);
	}

	/**
	 * Checks all passports, including the complete hash chain.
	 *
	 * @return array{checked:int,invalid:array,broken_links:array}
	 */
	public static function verify_all( $is_test = 0 ) {
		global $wpdb;
		$t       = HWPP_Install::table();
		$prev    = '';
		$checked = 0;
		$invalid = array();
		$broken  = array();
		$last_id = 0;
		do {
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM %i WHERE is_test = %d AND id > %d ORDER BY id ASC LIMIT 500", $t, $is_test, $last_id ) ); // phpcs:ignore WordPress.DB
			foreach ( $rows as $row ) {
				$checked++;
				$res = self::verify( $row );
				if ( ! $res['valid'] ) {
					$invalid[] = $row;
				}
				if ( $row->prev_hash !== $prev ) {
					$broken[] = $row;
				}
				$prev    = $row->hash;
				$last_id = (int) $row->id;
			}
		} while ( count( $rows ) === 500 );

		update_option(
			'hwpp_last_integrity_check',
			array(
				'time'    => time(),
				'checked' => $checked,
				'invalid' => count( $invalid ),
				'broken'  => count( $broken ),
			),
			false
		);

		return array( 'checked' => $checked, 'invalid' => $invalid, 'broken_links' => $broken );
	}

	/**
	 * Short, easy-to-read fingerprint for print and display (e.g. „3F2A 9C1B 77E0 4D21“).
	 */
	public static function fingerprint( $hash ) {
		return strtoupper( trim( chunk_split( substr( (string) $hash, 0, 16 ), 4, ' ' ) ) );
	}
}
