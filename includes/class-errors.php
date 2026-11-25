<?php
/**
 * Clear error management: messages per order plus a notice in the back end.
 *
 * @package HandwerkProduktpass
 */

defined( 'ABSPATH' ) || exit;

class HWPP_Errors {

	const OPTION = 'hwpp_open_errors';

	public static function set_order_errors( $order, array $errors ) {
		$open = get_option( self::OPTION, array() );
		$open = is_array( $open ) ? $open : array();
		$oid  = $order->get_id();

		if ( $errors ) {
			$order->update_meta_data( '_hwpp_errors', $errors );
			$order->save_meta_data();
			$new = ! isset( $open[ $oid ] ) || $open[ $oid ]['errors'] !== $errors;
			$open[ $oid ] = array(
				'number' => $order->get_order_number(),
				'errors' => $errors,
				'time'   => time(),
			);
			if ( $new ) {
				$order->add_order_note( __( 'Product passport notice:', 'codefokus-handwerk-produktpass' ) . ' ' . implode( ' ', $errors ) );
			}
		} else {
			if ( $order->get_meta( '_hwpp_errors' ) ) {
				$order->delete_meta_data( '_hwpp_errors' );
				$order->save_meta_data();
			}
			unset( $open[ $oid ] );
		}
		update_option( self::OPTION, $open, false );
	}

	public static function open() {
		$open = get_option( self::OPTION, array() );
		return is_array( $open ) ? $open : array();
	}

	public static function dismiss( $order_id ) {
		$open = self::open();
		unset( $open[ (int) $order_id ] );
		update_option( self::OPTION, $open, false );
	}

	/**
	 * Admin notice: open problems (up to 3 orders in detail).
	 */
	public static function render_notice() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		$open = self::open();
		if ( ! $open ) {
			return;
		}
		echo '<div class="notice notice-error hwpp-notice"><p><strong>' . esc_html__( 'Product passport: no passport could be created yet for some orders.', 'codefokus-handwerk-produktpass' ) . '</strong></p><ul>';
		$shown = 0;
		foreach ( array_reverse( $open, true ) as $order_id => $info ) {
			if ( ++$shown > 3 ) {
				break;
			}
			$order = wc_get_order( $order_id );
			$url   = $order ? $order->get_edit_order_url() : '#';
			echo '<li><a href="' . esc_url( $url ) . '">' . esc_html( sprintf( /* translators: %s: order number */ __( 'Order #%s', 'codefokus-handwerk-produktpass' ), $info['number'] ) ) . '</a>: ';
			echo esc_html( reset( $info['errors'] ) );
			$dismiss = wp_nonce_url( admin_url( 'admin-post.php?action=hwpp_dismiss_error&order_id=' . (int) $order_id ), 'hwpp_dismiss_error' );
			echo ' <a href="' . esc_url( $dismiss ) . '" class="hwpp-muted">' . esc_html__( '(hide)', 'codefokus-handwerk-produktpass' ) . '</a></li>';
		}
		echo '</ul>';
		if ( count( $open ) > 3 ) {
			/* translators: %d: number of further orders */
			echo '<p>' . esc_html( sprintf( __( '… and %d more orders.', 'codefokus-handwerk-produktpass' ), count( $open ) - 3 ) ) . '</p>';
		}
		echo '</div>';
	}
}
