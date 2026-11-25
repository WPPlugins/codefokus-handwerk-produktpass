<?php
/**
 * Box „Digital product passports“ in the order view (HPOS-compatible).
 *
 * @package HandwerkProduktpass
 */

defined( 'ABSPATH' ) || exit;

class HWPP_Order_Admin {

	public static function init() {
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_box' ) );
		add_action( 'admin_post_hwpp_generate_order', array( __CLASS__, 'handle_generate' ) );
	}

	public static function add_box() {
		$screen = function_exists( 'wc_get_page_screen_id' ) ? wc_get_page_screen_id( 'shop-order' ) : 'shop_order';
		add_meta_box( 'hwpp_order_box', __( 'Digital product passports', 'codefokus-handwerk-produktpass' ), array( __CLASS__, 'render' ), $screen, 'side', 'default' );
	}

	public static function render( $post_or_order ) {
		$order = $post_or_order instanceof WC_Order ? $post_or_order : wc_get_order( $post_or_order->ID );
		if ( ! $order ) {
			return;
		}
		$rows   = HWPP_Repository::find_by_order( $order->get_id() );
		$errors = (array) $order->get_meta( '_hwpp_errors' );
		$errors = array_filter( $errors );

		if ( $errors ) {
			echo '<div class="hwpp-box-error">';
			foreach ( $errors as $e ) {
				echo '<p>' . esc_html( $e ) . '</p>';
			}
			echo '</div>';
		}

		if ( $rows ) {
			echo '<ul class="hwpp-order-list">';
			foreach ( $rows as $row ) {
				$res = HWPP_Integrity::verify( $row );
				echo '<li class="hwpp-status-' . esc_attr( $row->status ) . '"><span class="hwpp-dot ' . ( $res['valid'] ? 'is-ok' : 'is-bad' ) . '" title="' . esc_attr( $res['message'] ) . '"></span>';
				echo '<code>' . esc_html( $row->serial ) . '</code>';
				if ( 'active' !== $row->status ) {
					echo ' <span class="hwpp-badge hwpp-badge--warn">' . esc_html( HWPP_Lifecycle::label( $row->status ) ) . '</span>';
				}
				echo '<br><a href="' . esc_url( HWPP_Repository::public_url( $row ) ) . '" target="_blank">' . esc_html__( 'Passport', 'codefokus-handwerk-produktpass' ) . '</a>';
				$cert = HWPP_Repository::certificate_url( $row );
				if ( $cert ) {
					echo ' · <a href="' . esc_url( $cert ) . '" target="_blank">' . esc_html__( 'Certificate (PDF)', 'codefokus-handwerk-produktpass' ) . '</a>';
				}
				if ( $row->views ) {
					/* translators: %d: views */
					echo ' · <span class="hwpp-muted">' . esc_html( sprintf( _n( '%d view', '%d views', (int) $row->views, 'codefokus-handwerk-produktpass' ), (int) $row->views ) ) . '</span>';
				}
				echo wp_kses( HWPP_Lifecycle::action_forms( $row ) . '</li>', HWPP_Admin::kses() );
			}
			echo '</ul>';
			/**
			 * Fires below the passport list in the order box.
			 *
			 * @param WC_Order $order Order.
			 * @param array    $rows  Passport records.
			 */
			do_action( 'hwpp_order_box_after_list', $order, $rows );
			if ( HWPP_Public_View::has_restricted() ) {
				$current = HWPP_Repository::find_current_by_order( $order->get_id() );
				if ( $current ) {
					echo '<p class="description">' . esc_html__( 'Access codes for restricted information:', 'codefokus-handwerk-produktpass' ) . '<br>';
					foreach ( $current as $r ) {
						echo '<code>' . esc_html( $r->serial ) . '</code>: <code>' . esc_html( HWPP_Public_View::access_code( $r ) ) . '</code><br>';
					}
					echo '</p>';
				}
			}
		} elseif ( ! $errors ) {
			$statuses = array_intersect_key( HWPP_Settings::status_choices(), array_flip( (array) HWPP_Settings::get( 'trigger_statuses' ) ) );
			/* translators: %s: list of order statuses */
			echo '<p class="hwpp-muted">' . esc_html( sprintf( __( 'No passports yet. They will be created automatically as soon as the order receives the status “%s”.', 'codefokus-handwerk-produktpass' ), implode( '” / “', $statuses ) ) ) . '</p>';
		}

		$url = wp_nonce_url( admin_url( 'admin-post.php?action=hwpp_generate_order&order_id=' . $order->get_id() ), 'hwpp_generate_' . $order->get_id() );
		echo '<p><a class="button" href="' . esc_url( $url ) . '">' . esc_html__( 'Generate passports now', 'codefokus-handwerk-produktpass' ) . '</a></p>';
		echo wp_kses( '<p class="description">' . esc_html__( 'Only creates missing passports. Passports already issued remain unchanged.', 'codefokus-handwerk-produktpass' ) . ' ' . HWPP_Glossary::tip( 'unveraenderlich' ) . '</p>', HWPP_Admin::kses() );
	}

	public static function handle_generate() {
		$order_id = isset( $_GET['order_id'] ) ? absint( $_GET['order_id'] ) : 0;
		check_admin_referer( 'hwpp_generate_' . $order_id );
		if ( ! current_user_can( 'edit_shop_orders' ) && ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to edit orders.', 'codefokus-handwerk-produktpass' ) );
		}
		$res = HWPP_Generator::generate_for_order( $order_id );
		set_transient(
			'hwpp_flash_' . get_current_user_id(),
			$res['errors']
				? array( 'type' => 'error', 'msg' => implode( ' ', $res['errors'] ) )
				/* translators: %d: number of passports */
				: array( 'type' => 'success', 'msg' => $res['created'] ? sprintf( _n( '%d product passport was created.', '%d product passports were created.', $res['created'], 'codefokus-handwerk-produktpass' ), $res['created'] ) : __( 'All passports for this order already exist.', 'codefokus-handwerk-produktpass' ) ),
			60
		);
		$order = wc_get_order( $order_id );
		wp_safe_redirect( $order ? $order->get_edit_order_url() : admin_url() );
		exit;
	}
}
