<?php
/**
 * WordPress privacy tools: data export and erasure.
 *
 * The free plugin stores no personal data of its own. Passports contain no customer
 * data and are only linked to the order number. The export lists the passports of a
 * customer's orders; extensions add their own data via filters. Passports are kept on
 * erasure because they must remain available as proof of origin.
 *
 * @package HandwerkProduktpass
 */

defined( 'ABSPATH' ) || exit;

class HWPP_Privacy {

	public static function init() {
		add_filter( 'wp_privacy_personal_data_exporters', array( __CLASS__, 'register_exporter' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( __CLASS__, 'register_eraser' ) );
	}

	public static function register_exporter( $exporters ) {
		$exporters['codefokus-handwerk-produktpass'] = array(
			'exporter_friendly_name' => __( 'Handwerk Produktpass', 'codefokus-handwerk-produktpass' ),
			'callback'               => array( __CLASS__, 'export' ),
		);
		return $exporters;
	}

	public static function register_eraser( $erasers ) {
		$erasers['codefokus-handwerk-produktpass'] = array(
			'eraser_friendly_name' => __( 'Handwerk Produktpass', 'codefokus-handwerk-produktpass' ),
			'callback'             => array( __CLASS__, 'erase' ),
		);
		return $erasers;
	}

	/**
	 * Orders of one e-mail address.
	 *
	 * @param string $email Address.
	 * @return WC_Order[]
	 */
	private static function orders_for( $email ) {
		if ( '' === $email || ! function_exists( 'wc_get_orders' ) ) {
			return array();
		}
		return wc_get_orders(
			array(
				'billing_email' => $email,
				'limit'         => -1,
				'return'        => 'objects',
			)
		);
	}

	/**
	 * Export callback.
	 *
	 * @param string $email_address Address.
	 * @param int    $page          Page number (unused, all orders are returned at once).
	 * @return array
	 */
	public static function export( $email_address, $page = 1 ) {
		$label = __( 'Digital product passports (Handwerk Produktpass)', 'codefokus-handwerk-produktpass' );
		$items = array();

		foreach ( self::orders_for( $email_address ) as $order ) {
			$passes = HWPP_Repository::find_by_order( $order->get_id() );
			$data   = array();
			if ( $passes ) {
				$data[] = array(
					'name'  => __( 'Order number', 'codefokus-handwerk-produktpass' ),
					'value' => $order->get_order_number(),
				);
				$data[] = array(
					'name'  => __( 'Digital product passports of this order', 'codefokus-handwerk-produktpass' ),
					'value' => implode( ', ', wp_list_pluck( $passes, 'serial' ) ),
				);
			}

			/**
			 * Personal data of an order that an extension stores (name/value pairs).
			 *
			 * @param array    $data  Data so far.
			 * @param WC_Order $order Order.
			 */
			$data = (array) apply_filters( 'hwpp_privacy_export_order', $data, $order );
			if ( ! $data ) {
				continue;
			}

			$items[] = array(
				'group_id'    => 'hwpp-passports',
				'group_label' => $label,
				'item_id'     => 'hwpp-order-' . $order->get_id(),
				'data'        => $data,
			);
		}

		return array(
			'data' => $items,
			'done' => true,
		);
	}

	/**
	 * Erasure callback. Passports are retained, extensions remove their own data.
	 *
	 * @param string $email_address Address.
	 * @param int    $page          Page number (unused).
	 * @return array
	 */
	public static function erase( $email_address, $page = 1 ) {
		$removed  = false;
		$retained = false;
		$messages = array();

		foreach ( self::orders_for( $email_address ) as $order ) {
			/**
			 * Lets an extension remove its personal data from an order.
			 *
			 * @param bool     $removed Whether something was removed so far.
			 * @param WC_Order $order   Order.
			 */
			$removed = (bool) apply_filters( 'hwpp_privacy_erase_order', $removed, $order );

			if ( count( HWPP_Repository::find_by_order( $order->get_id() ) ) > 0 ) {
				$retained = true;
			}
		}

		if ( $retained ) {
			$messages[] = __( 'Digital product passports were kept. They contain no personal data and must remain available under the EU regulation.', 'codefokus-handwerk-produktpass' );
		}

		return array(
			'items_removed'  => $removed,
			'items_retained' => $retained,
			'messages'       => $messages,
			'done'           => true,
		);
	}
}
