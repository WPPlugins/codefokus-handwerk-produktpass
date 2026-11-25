<?php
/**
 * Data access for issued product passports.
 *
 * Deliberately there is NO update method: issued passports are unchangeable.
 *
 * @package HandwerkProduktpass
 */

defined( 'ABSPATH' ) || exit;

class HWPP_Repository {

	public static function find_by_uid( $uid ) {
		global $wpdb;
		if ( ! preg_match( '/^[a-f0-9]{32}$/', (string) $uid ) ) {
			return null;
		}
		$t = HWPP_Install::table();
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM %i WHERE uid = %s", $t, $uid ) ); // phpcs:ignore WordPress.DB
	}

	public static function find_by_order( $order_id ) {
		global $wpdb;
		$t = HWPP_Install::table();
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM %i WHERE order_id = %d AND is_test = 0 ORDER BY item_id ASC, unit_index ASC", $t, $order_id ) ); // phpcs:ignore WordPress.DB
	}

	public static function count_for_item( $order_id, $item_id ) {
		global $wpdb;
		$t = HWPP_Install::table();
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM %i WHERE order_id = %d AND item_id = %d AND is_test = 0 AND status <> 'superseded'", $t, $order_id, $item_id ) ); // phpcs:ignore WordPress.DB
	}

	/**
	 * Current (not replaced) passports of an order – for the customer, e-mails and invoices.
	 */
	public static function find_current_by_order( $order_id, $only_active = false ) {
		return array_values(
			array_filter(
				self::find_by_order( $order_id ),
				static function ( $r ) use ( $only_active ) {
					return 'superseded' !== $r->status && ( ! $only_active || 'active' === $r->status );
				}
			)
		);
	}

	public static function find_by_serial( $serial ) {
		global $wpdb;
		$t = HWPP_Install::table();
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM %i WHERE serial = %s AND is_test = 0 AND status <> 'superseded' ORDER BY id DESC LIMIT 1", $t, $serial ) ); // phpcs:ignore WordPress.DB
	}

	/**
	 * Privacy-friendly view counter (no IP, no cookies).
	 */
	public static function count_view( $row ) {
		global $wpdb;
		$t = HWPP_Install::table();
		$wpdb->query( $wpdb->prepare( "UPDATE %i SET views = views + 1, last_view = %s WHERE id = %d", $t, gmdate( 'Y-m-d H:i:s' ), (int) $row->id ) ); // phpcs:ignore WordPress.DB
	}

	public static function latest( $limit = 50, $offset = 0, $search = '', $test = null ) {
		global $wpdb;
		$t      = HWPP_Install::table();
		$limit  = (int) $limit;
		$offset = (int) $offset;
		$like   = '%' . $wpdb->esc_like( (string) $search ) . '%';
		$num    = absint( $search );
		$flag   = $test ? 1 : 0;

		// Fixed queries (no dynamically assembled SQL).
		if ( '' !== $search && null !== $test ) {
			return $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i WHERE (serial LIKE %s OR order_id = %d) AND is_test = %d ORDER BY id DESC LIMIT %d OFFSET %d', $t, $like, $num, $flag, $limit, $offset ) ); // phpcs:ignore WordPress.DB
		}
		if ( '' !== $search ) {
			return $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i WHERE (serial LIKE %s OR order_id = %d) ORDER BY id DESC LIMIT %d OFFSET %d', $t, $like, $num, $limit, $offset ) ); // phpcs:ignore WordPress.DB
		}
		if ( null !== $test ) {
			return $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i WHERE is_test = %d ORDER BY id DESC LIMIT %d OFFSET %d', $t, $flag, $limit, $offset ) ); // phpcs:ignore WordPress.DB
		}
		return $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i ORDER BY id DESC LIMIT %d OFFSET %d', $t, $limit, $offset ) ); // phpcs:ignore WordPress.DB
	}

	public static function count( $test = null ) {
		global $wpdb;
		$t = HWPP_Install::table();
		if ( null === $test ) {
			return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM %i", $t ) ); // phpcs:ignore WordPress.DB
		}
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM %i WHERE is_test = %d", $t, $test ? 1 : 0 ) ); // phpcs:ignore WordPress.DB
	}

	public static function last_hash( $is_test ) {
		global $wpdb;
		$t = HWPP_Install::table();
		return (string) $wpdb->get_var( $wpdb->prepare( "SELECT hash FROM %i WHERE is_test = %d ORDER BY id DESC LIMIT 1", $t, $is_test ? 1 : 0 ) ); // phpcs:ignore WordPress.DB
	}

	/**
	 * Creates a new passport (incl. hash chain and signature).
	 *
	 * @return object|WP_Error
	 */
	public static function insert( array $row, array $payload ) {
		global $wpdb;
		$t       = HWPP_Install::table();
		$is_test = ! empty( $row['is_test'] ) ? 1 : 0;

		$wpdb->query( "SELECT GET_LOCK('hwpp_chain', 10)" ); // phpcs:ignore WordPress.DB

		$record = (object) array(
			'uid'        => bin2hex( random_bytes( 16 ) ),
			'serial'     => substr( (string) $row['serial'], 0, 80 ),
			'order_id'   => (int) $row['order_id'],
			'item_id'    => (int) $row['item_id'],
			'unit_index' => (int) $row['unit_index'],
			'product_id' => (int) $row['product_id'],
			'is_test'    => $is_test,
			'language'   => (string) $row['language'],
			'payload'    => HWPP_Integrity::canonical( $payload ),
			'prev_hash'  => self::last_hash( $is_test ),
			'created_at' => gmdate( 'Y-m-d H:i:s' ),
		);
		$record->hash      = HWPP_Integrity::compute_hash( $record );
		$record->signature = HWPP_Integrity::sign( $record->hash );

		$ok = $wpdb->insert( $t, (array) $record ); // phpcs:ignore WordPress.DB
		$wpdb->query( "SELECT RELEASE_LOCK('hwpp_chain')" ); // phpcs:ignore WordPress.DB

		if ( ! $ok ) {
			return new WP_Error( 'hwpp_db', __( 'The product passport could not be saved (database error). Please open “Product passport → Diagnostics” – it checks whether the database table exists.', 'codefokus-handwerk-produktpass' ) );
		}
		$record->id = (int) $wpdb->insert_id;
		do_action( 'hwpp_passport_created', $record, $payload );
		return $record;
	}

	/**
	 * Deletes test passports only.
	 */
	public static function delete_tests() {
		global $wpdb;
		$t = HWPP_Install::table();
		return (int) $wpdb->query( $wpdb->prepare( "DELETE FROM %i WHERE is_test = 1", $t ) ); // phpcs:ignore WordPress.DB
	}

	public static function payload( $row ) {
		$data = json_decode( (string) $row->payload, true );
		return is_array( $data ) ? $data : array();
	}

	/**
	 * Public address. With the option enabled and a GTIN present, in GS1 Digital Link format.
	 */
	public static function public_url( $row, $lang = '' ) {
		/**
		 * Public address of a passport (the target of the QR code).
		 *
		 * @param string $url  Default address.
		 * @param object $row  Passport record.
		 * @param string $lang Language code or empty.
		 */
		return (string) apply_filters( 'hwpp_public_url', self::uid_url( $row, $lang ), $row, $lang );
	}

	public static function uid_url( $row, $lang = '' ) {
		$slug = sanitize_title( HWPP_Settings::get( 'public_slug', 'produktpass' ) );
		if ( get_option( 'permalink_structure' ) ) {
			$url = home_url( user_trailingslashit( $slug . '/' . $row->uid ) );
		} else {
			$url = add_query_arg( 'hwpp_pass', $row->uid, home_url( '/' ) );
		}
		return $lang ? add_query_arg( 'pp_lang', $lang, $url ) : $url;
	}

	/**
	 * Address of the certificate PDF. Empty if no extension provides certificates.
	 *
	 * @param object $row  Passport record.
	 * @param string $lang Language code or empty.
	 */
	public static function certificate_url( $row, $lang = '' ) {
		return (string) apply_filters( 'hwpp_certificate_url', '', $row, $lang );
	}
}
