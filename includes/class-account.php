<?php
/**
 * Customer account („My orders“) and customer e-mails.
 *
 * @package HandwerkProduktpass
 */

defined( 'ABSPATH' ) || exit;

class HWPP_Account {

	public static function init() {
		if ( HWPP_Settings::get( 'account_links' ) ) {
			add_action( 'woocommerce_order_details_after_order_table', array( __CLASS__, 'order_details' ), 5 );
			add_filter( 'woocommerce_my_account_my_orders_actions', array( __CLASS__, 'orders_action' ), 10, 2 );
		}
		if ( HWPP_Settings::get( 'email_links' ) ) {
			add_action( 'woocommerce_email_after_order_table', array( __CLASS__, 'email_block' ), 15, 4 );
		}
	}

	/**
	 * Passes of an order, grouped by line item.
	 */
	public static function grouped( $order ) {
		$out = array();
		foreach ( HWPP_Repository::find_current_by_order( $order->get_id() ) as $row ) {
			$out[ (int) $row->item_id ][] = $row;
		}
		return $out;
	}

	public static function order_details( $order ) {
		if ( ! $order instanceof WC_Order ) {
			return;
		}
		$grouped = self::grouped( $order );
		if ( ! $grouped ) {
			return;
		}
		echo '<section class="hwpp-account" id="produktpass">';
		echo '<h2 class="woocommerce-order-details__title">' . esc_html__( 'Digital product passports', 'codefokus-handwerk-produktpass' ) . '</h2>';
		echo '<p>' . esc_html__( 'A tamper-proof digital product passport has been issued for each of your unique pieces – with materials, origin, care and repair information.', 'codefokus-handwerk-produktpass' ) . '</p>';
		echo '<table class="woocommerce-table shop_table hwpp-account-table"><thead><tr><th>' . esc_html__( 'Product', 'codefokus-handwerk-produktpass' ) . '</th><th>' . esc_html__( 'Serial number', 'codefokus-handwerk-produktpass' ) . '</th><th></th></tr></thead><tbody>';
		foreach ( $order->get_items() as $item_id => $item ) {
			if ( empty( $grouped[ $item_id ] ) ) {
				continue;
			}
			foreach ( $grouped[ $item_id ] as $row ) {
				echo '<tr><td>' . esc_html( $item->get_name() ) . '</td><td><code>' . esc_html( $row->serial ) . '</code>' . ( 'active' !== $row->status ? '<br><small>' . esc_html( HWPP_Lifecycle::label( $row->status ) ) . '</small>' : '' ) . '</td><td>';
				echo '<a class="woocommerce-button button" href="' . esc_url( HWPP_Repository::public_url( $row ) ) . '" target="_blank" rel="noopener">' . esc_html__( 'View product passport', 'codefokus-handwerk-produktpass' ) . '</a> ';
				echo '<a class="woocommerce-button button" href="' . esc_url( HWPP_Repository::certificate_url( $row ) ) . '" target="_blank" rel="noopener">' . esc_html__( 'Certificate (PDF)', 'codefokus-handwerk-produktpass' ) . '</a>';
				echo '</td></tr>';
			}
		}
		echo '</tbody></table></section>';
	}

	public static function orders_action( $actions, $order ) {
		if ( HWPP_Repository::find_current_by_order( $order->get_id() ) ) {
			$actions['hwpp'] = array(
				'url'  => $order->get_view_order_url() . '#produktpass',
				'name' => __( 'Product passport', 'codefokus-handwerk-produktpass' ),
			);
		}
		return $actions;
	}

	public static function email_block( $order, $sent_to_admin, $plain_text, $email = null ) {
		if ( $sent_to_admin || ! $order instanceof WC_Order ) {
			return;
		}
		if ( $email && ! in_array( $email->id, array( 'customer_processing_order', 'customer_completed_order', 'customer_invoice', 'customer_on_hold_order' ), true ) ) {
			return;
		}
		$rows = HWPP_Repository::find_current_by_order( $order->get_id(), true );
		if ( ! $rows ) {
			return;
		}
		$items = $order->get_items();
		if ( $plain_text ) {
			echo "\n" . esc_html( strtoupper( __( 'Your digital product passports', 'codefokus-handwerk-produktpass' ) ) ) . "\n";
			foreach ( $rows as $row ) {
				$name = isset( $items[ $row->item_id ] ) ? $items[ $row->item_id ]->get_name() : $row->serial;
				echo esc_html( $name . ' (' . $row->serial . '): ' ) . esc_url( HWPP_Repository::public_url( $row ) ) . "\n";
			}
			return;
		}
		echo '<h2>' . esc_html__( 'Your digital product passports', 'codefokus-handwerk-produktpass' ) . '</h2><ul>';
		foreach ( $rows as $row ) {
			$name = isset( $items[ $row->item_id ] ) ? $items[ $row->item_id ]->get_name() : $row->serial;
			echo '<li><a href="' . esc_url( HWPP_Repository::public_url( $row ) ) . '">' . esc_html( $name ) . '</a> – ' . esc_html( $row->serial ) . '</li>';
		}
		echo '</ul>';
	}
}
