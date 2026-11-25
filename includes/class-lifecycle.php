<?php
/**
 * Lifecycle of a passport: status (cancelled, refunded, stolen …) and corrected versions.
 *
 * The status is deliberately OUTSIDE the signed data: it may change without the
 * hash becoming invalid. Corrections, however, create a NEW signed passport that refers to the
 * old one – the hash chain stays complete.
 *
 * @package HandwerkProduktpass
 */

defined( 'ABSPATH' ) || exit;

class HWPP_Lifecycle {

	public static function init() {
		add_action( 'woocommerce_order_status_cancelled', array( __CLASS__, 'on_cancelled' ), 20 );
		add_action( 'woocommerce_order_status_refunded', array( __CLASS__, 'on_refunded' ), 20 );
		add_action( 'woocommerce_order_partially_refunded', array( __CLASS__, 'on_partial_refund' ), 20, 2 );
		add_action( 'admin_post_hwpp_correct', array( __CLASS__, 'handle_correct' ) );
		add_action( 'admin_post_hwpp_set_status', array( __CLASS__, 'handle_set_status' ) );
	}

	public static function statuses() {
		return array(
			'active'     => __( 'Valid', 'codefokus-handwerk-produktpass' ),
			'superseded' => __( 'Corrected (superseded)', 'codefokus-handwerk-produktpass' ),
			'cancelled'  => __( 'Cancelled', 'codefokus-handwerk-produktpass' ),
			'refunded'   => __( 'Returned / refunded', 'codefokus-handwerk-produktpass' ),
			'stolen'     => __( 'Reported stolen', 'codefokus-handwerk-produktpass' ),
		);
	}

	/** Statuses that a merchant may set manually. */
	public static function manual_statuses() {
		$all = self::statuses();
		unset( $all['superseded'] );
		return $all;
	}

	public static function label( $status ) {
		$all = self::statuses();
		return isset( $all[ $status ] ) ? $all[ $status ] : $status;
	}

	/**
	 * Customer-facing notice for the public pass page (empty = everything normal).
	 */
	public static function public_notice( $row ) {
		$status = isset( $row->status ) ? $row->status : 'active';
		switch ( $status ) {
			case 'superseded':
				return __( 'A corrected version of the product passport has been issued for this item. This version is kept for traceability; the new version is the valid one.', 'codefokus-handwerk-produktpass' );
			case 'cancelled':
				return __( 'The associated order was cancelled. This product passport is no longer valid.', 'codefokus-handwerk-produktpass' );
			case 'refunded':
				return __( 'This item was returned to the manufacturer. The product passport is no longer valid for the previous purchase.', 'codefokus-handwerk-produktpass' );
			case 'stolen':
				return __( 'Warning: this item has been reported stolen. If this item is offered to you for sale, please contact the manufacturer or the police.', 'codefokus-handwerk-produktpass' );
		}
		return '';
	}

	public static function set_status( $row, $status, $note = '' ) {
		global $wpdb;
		if ( ! isset( self::statuses()[ $status ] ) ) {
			return false;
		}
		$ok = $wpdb->update( // phpcs:ignore WordPress.DB
			HWPP_Install::table(),
			array(
				'status'            => $status,
				'status_note'       => substr( (string) $note, 0, 255 ),
				'status_changed_at' => gmdate( 'Y-m-d H:i:s' ),
			),
			array( 'id' => (int) $row->id )
		);
		do_action( 'hwpp_status_changed', $row, $status, $note );
		return false !== $ok;
	}

	/* ---------------------------------------------------------------
	 * Automation on cancellation and refund
	 * --------------------------------------------------------------- */

	public static function on_cancelled( $order_id ) {
		self::set_order_status( $order_id, 'cancelled', __( 'Order cancelled', 'codefokus-handwerk-produktpass' ) );
	}

	public static function on_refunded( $order_id ) {
		self::set_order_status( $order_id, 'refunded', __( 'Order fully refunded', 'codefokus-handwerk-produktpass' ) );
	}

	private static function set_order_status( $order_id, $status, $note ) {
		$n = 0;
		foreach ( HWPP_Repository::find_by_order( $order_id ) as $row ) {
			if ( 'active' === $row->status ) {
				self::set_status( $row, $status, $note );
				$n++;
			}
		}
		$order = wc_get_order( $order_id );
		if ( $n && $order ) {
			/* translators: 1: count, 2: status */
			$order->add_order_note( sprintf( __( '%1$d product passport(s) set to “%2$s”.', 'codefokus-handwerk-produktpass' ), $n, self::label( $status ) ) );
		}
	}

	/**
	 * Partial refund: for each line item, as many passports are set to „refunded“ as units were refunded
	 * (always the most recently issued units).
	 */
	public static function on_partial_refund( $order_id, $refund_id = 0 ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}
		$by_item = array();
		foreach ( HWPP_Repository::find_by_order( $order_id ) as $row ) {
			if ( 'superseded' !== $row->status ) {
				$by_item[ (int) $row->item_id ][] = $row;
			}
		}
		foreach ( $by_item as $item_id => $rows ) {
			$refunded_qty = absint( $order->get_qty_refunded_for_item( $item_id ) );
			$already      = count( array_filter( $rows, static function ( $r ) { return 'refunded' === $r->status; } ) );
			$todo         = $refunded_qty - $already;
			usort( $rows, static function ( $a, $b ) { return $b->unit_index - $a->unit_index; } );
			foreach ( $rows as $row ) {
				if ( $todo <= 0 ) {
					break;
				}
				if ( 'active' === $row->status ) {
					self::set_status( $row, 'refunded', __( 'Partial refund', 'codefokus-handwerk-produktpass' ) );
					$todo--;
				}
			}
		}
	}

	/**
	 * When a trigger status is set again (e.g. cancellation reversed), cancelled passports are reactivated.
	 */
	public static function reactivate_cancelled( $order_id ) {
		foreach ( HWPP_Repository::find_by_order( $order_id ) as $row ) {
			if ( 'cancelled' === $row->status ) {
				self::set_status( $row, 'active', __( 'Order resumed', 'codefokus-handwerk-produktpass' ) );
			}
		}
	}

	/* ---------------------------------------------------------------
	 * Berichtigte Fassung
	 * --------------------------------------------------------------- */

	/**
	 * Issues a corrected version with the CURRENT product data. The serial number stays the same (same unit).
	 *
	 * @return object|WP_Error New passport.
	 */
	public static function correct( $old, $reason ) {
		global $wpdb;
		$reason = trim( (string) $reason );
		if ( '' === $reason ) {
			return new WP_Error( 'hwpp_reason', __( 'Please briefly state what was corrected (e.g. “Place of manufacture corrected”). The reason is shown in the passport.', 'codefokus-handwerk-produktpass' ) );
		}
		if ( 'superseded' === $old->status ) {
			return new WP_Error( 'hwpp_superseded', __( 'This passport has already been corrected. Please correct the latest version.', 'codefokus-handwerk-produktpass' ) );
		}
		$product = wc_get_product( $old->product_id );
		if ( ! $product ) {
			return new WP_Error( 'hwpp_no_product', __( 'The product no longer exists. Please restore it from the trash.', 'codefokus-handwerk-produktpass' ) );
		}
		$missing = HWPP_Fields::missing_required( $product->get_id() );
		if ( $missing ) {
			return new WP_Error( 'hwpp_missing', HWPP_Generator::missing_message( $product, $missing ) );
		}
		$order = $old->order_id ? wc_get_order( $old->order_id ) : null;
		$item  = $order ? $order->get_item( $old->item_id ) : null;

		$old_payload = HWPP_Repository::payload( $old );
		$version     = isset( $old_payload['correction']['version'] ) ? (int) $old_payload['correction']['version'] + 1 : 2;

		$payload               = HWPP_Generator::build_payload( $product, $item ? $item : null, $order ? $order : null, $old->serial, (bool) $old->is_test );
		$payload['correction'] = array(
			'supersedes' => $old->uid,
			'reason'     => sanitize_text_field( $reason ),
			'version'    => $version,
		);

		$new = HWPP_Repository::insert(
			array(
				'serial'     => $old->serial,
				'order_id'   => $old->order_id,
				'item_id'    => $old->item_id,
				'unit_index' => $old->unit_index,
				'product_id' => $old->product_id,
				'is_test'    => $old->is_test,
				'language'   => $payload['primary_language'],
			),
			$payload
		);
		if ( is_wp_error( $new ) ) {
			return $new;
		}
		$t = HWPP_Install::table();
		$wpdb->update( $t, array( 'supersedes' => $old->uid, 'status' => $old->status ), array( 'id' => (int) $new->id ) ); // phpcs:ignore WordPress.DB
		$wpdb->update( $t, array( 'superseded_by' => $new->uid ), array( 'id' => (int) $old->id ) ); // phpcs:ignore WordPress.DB
		self::set_status( $old, 'superseded', $reason );

		if ( $order ) {
			/* translators: 1: serial, 2: reason */
			$order->add_order_note( sprintf( __( 'Corrected version of product passport %1$s issued: %2$s', 'codefokus-handwerk-produktpass' ), $old->serial, $reason ) );
		}
		return HWPP_Repository::find_by_uid( $new->uid );
	}

	/* ---------------------------------------------------------------
	 * Backend-Aktionen
	 * --------------------------------------------------------------- */

	private static function require_cap() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission for this action.', 'codefokus-handwerk-produktpass' ) );
		}
	}

	private static function back( $type, $msg ) {
		set_transient( 'hwpp_flash_' . get_current_user_id(), array( 'type' => $type, 'msg' => $msg ), 60 );
		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url( 'admin.php?page=hwpp' ) );
		exit;
	}

	public static function handle_correct() {
		self::require_cap();
		check_admin_referer( 'hwpp_correct' );
		$uid = isset( $_POST['uid'] ) ? sanitize_key( wp_unslash( $_POST['uid'] ) ) : '';
		$row = HWPP_Repository::find_by_uid( $uid );
		if ( ! $row ) {
			self::back( 'error', __( 'Passport not found.', 'codefokus-handwerk-produktpass' ) );
		}
		$res = self::correct( $row, isset( $_POST['reason'] ) ? sanitize_text_field( wp_unslash( $_POST['reason'] ) ) : '' );
		if ( is_wp_error( $res ) ) {
			self::back( 'error', $res->get_error_message() );
		}
		/* translators: %s: serial */
		self::back( 'success', sprintf( __( 'Corrected version for %s issued. The old passport automatically refers to the new version – printed QR codes keep working.', 'codefokus-handwerk-produktpass' ), $res->serial ) );
	}

	public static function handle_set_status() {
		self::require_cap();
		check_admin_referer( 'hwpp_status' );
		$uid = isset( $_POST['uid'] ) ? sanitize_key( wp_unslash( $_POST['uid'] ) ) : '';
		$row    = HWPP_Repository::find_by_uid( $uid );
		$status = isset( $_POST['status'] ) ? sanitize_key( wp_unslash( $_POST['status'] ) ) : '';
		if ( ! $row || ! isset( self::manual_statuses()[ $status ] ) || 'superseded' === $row->status ) {
			self::back( 'error', __( 'The status could not be changed.', 'codefokus-handwerk-produktpass' ) );
		}
		self::set_status( $row, $status, isset( $_POST['note'] ) ? sanitize_text_field( wp_unslash( $_POST['note'] ) ) : '' );
		/* translators: 1: serial, 2: status */
		self::back( 'success', sprintf( __( 'Status of %1$s: %2$s', 'codefokus-handwerk-produktpass' ), $row->serial, self::label( $status ) ) );
	}

	/**
	 * Small action forms (change status / correct) for the order box and overview.
	 */
	public static function action_forms( $row ) {
		if ( 'superseded' === $row->status ) {
			$new = HWPP_Repository::find_by_uid( $row->superseded_by );
			return $new ? '<span class="hwpp-muted">→ ' . esc_html( __( 'new version', 'codefokus-handwerk-produktpass' ) ) . ' <a href="' . esc_url( HWPP_Repository::public_url( $new ) ) . '" target="_blank">' . esc_html__( 'view', 'codefokus-handwerk-produktpass' ) . '</a></span>' : '';
		}
		$post = esc_url( admin_url( 'admin-post.php' ) );
		$html = '<details class="hwpp-actions-menu"><summary>' . esc_html__( 'Actions', 'codefokus-handwerk-produktpass' ) . '</summary>';

		$html .= '<form method="post" action="' . $post . '">' . wp_nonce_field( 'hwpp_status', '_wpnonce', true, false )
			. '<input type="hidden" name="action" value="hwpp_set_status"><input type="hidden" name="uid" value="' . esc_attr( $row->uid ) . '">'
			. '<label>' . esc_html__( 'Status', 'codefokus-handwerk-produktpass' ) . ' <select name="status">';
		foreach ( self::manual_statuses() as $k => $l ) {
			$html .= '<option value="' . esc_attr( $k ) . '" ' . selected( $row->status, $k, false ) . '>' . esc_html( $l ) . '</option>';
		}
		$html .= '</select></label> <input type="text" name="note" placeholder="' . esc_attr__( 'Note (optional)', 'codefokus-handwerk-produktpass' ) . '"> <button class="button button-small">' . esc_html__( 'Save', 'codefokus-handwerk-produktpass' ) . '</button></form>';

		$html .= '<form method="post" action="' . $post . '">' . wp_nonce_field( 'hwpp_correct', '_wpnonce', true, false )
			. '<input type="hidden" name="action" value="hwpp_correct"><input type="hidden" name="uid" value="' . esc_attr( $row->uid ) . '">'
			. '<label>' . esc_html__( 'Correct – reason:', 'codefokus-handwerk-produktpass' ) . ' ' . HWPP_Glossary::tip( 'berichtigung' ) . '<br><input type="text" name="reason" required placeholder="' . esc_attr__( 'e.g. Place of manufacture corrected', 'codefokus-handwerk-produktpass' ) . '"></label> '
			. '<button class="button button-small">' . esc_html__( 'Issue corrected version', 'codefokus-handwerk-produktpass' ) . '</button></form>';

		$links = array(
			'qr_png' => '<a href="' . esc_url( HWPP_Admin::qr_download_url( $row ) ) . '">' . esc_html__( 'QR code (PNG)', 'codefokus-handwerk-produktpass' ) . '</a>',
			'qr_svg' => '<a href="' . esc_url( HWPP_Admin::qr_download_url( $row, 'svg' ) ) . '">' . esc_html__( 'QR code (SVG)', 'codefokus-handwerk-produktpass' ) . '</a>',
		);
		/**
		 * Download links shown for a passport in the back end (already escaped HTML).
		 *
		 * @param array  $links Links by key.
		 * @param object $row   Passport record.
		 */
		$links = (array) apply_filters( 'hwpp_passport_admin_links', $links, $row );
		$html .= '<p class="hwpp-small-links">' . implode( ' · ', $links ) . '</p>';
		return $html . '</details>';
	}
}
