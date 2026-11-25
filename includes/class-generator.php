<?php
/**
 * Automatic passport creation for orders (and in test mode).
 *
 * @package HandwerkProduktpass
 */

defined( 'ABSPATH' ) || exit;

class HWPP_Generator {

	public static function init() {
		// Priority 1 on the status hook: runs BEFORE the customer e-mails and PDF invoice attachments,
		// so that the QR code and links are already included there.
		foreach ( (array) HWPP_Settings::get( 'trigger_statuses' ) as $status ) {
			add_action( 'woocommerce_order_status_' . sanitize_key( $status ), array( __CLASS__, 'on_status' ), 1, 1 );
		}
		add_action( 'woocommerce_payment_complete', array( __CLASS__, 'on_payment_complete' ), 1, 1 );
		add_action( 'hwpp_generate_for_order', array( __CLASS__, 'generate_for_order' ) );
	}

	public static function on_status( $order_id ) {
		HWPP_Lifecycle::reactivate_cancelled( $order_id );
		self::generate_for_order( $order_id );
	}

	public static function on_payment_complete( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( $order && in_array( $order->get_status(), (array) HWPP_Settings::get( 'trigger_statuses' ), true ) ) {
			self::generate_for_order( $order_id );
		}
	}

	/**
	 * Creates missing passports for all line items of an order.
	 * Existing passports are never changed or created twice.
	 *
	 * @return array{created:int,errors:string[]}
	 */
	public static function generate_for_order( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order || $order instanceof WC_Order_Refund ) {
			return array( 'created' => 0, 'errors' => array() );
		}

		$created = 0;
		$errors  = array();
		$pos     = 0;

		foreach ( $order->get_items( 'line_item' ) as $item_id => $item ) {
			$pos++;
			/** @var WC_Order_Item_Product $item */
			$product = $item->get_product();
			if ( ! $product ) {
				/* translators: %s: item name */
				$errors[] = sprintf( __( 'The product passport for “%s” could not be created because the product was deleted from the shop. Please restore the product from the trash and then click “Generate passports now”.', 'codefokus-handwerk-produktpass' ), $item->get_name() );
				continue;
			}
			if ( ! HWPP_Product_Data::applies_to( $product->get_id() ) ) {
				continue;
			}

			$qty      = max( 1, (int) $item->get_quantity() - absint( $order->get_qty_refunded_for_item( $item_id ) ) );
			$existing = HWPP_Repository::count_for_item( $order->get_id(), $item_id );
			if ( $existing >= $qty ) {
				continue;
			}

			$missing = HWPP_Fields::missing_required( $product->get_id() );
			if ( $missing ) {
				$errors[] = self::missing_message( $product, $missing );
				continue;
			}

			for ( $unit = $existing + 1; $unit <= $qty; $unit++ ) {
				$serial  = self::serial( $order, $item, $product, $pos, $unit, $qty, false );
				$payload = self::build_payload( $product, $item, $order, $serial, false );
				$result  = HWPP_Repository::insert(
					array(
						'serial'     => $serial,
						'order_id'   => $order->get_id(),
						'item_id'    => $item_id,
						'unit_index' => $unit,
						'product_id' => $product->get_id(),
						'is_test'    => 0,
						'language'   => $payload['primary_language'],
					),
					$payload
				);
				if ( is_wp_error( $result ) ) {
					$errors[] = $result->get_error_message();
					break;
				}
				$created++;
			}
		}

		if ( $created ) {
			/* translators: %d: number of passports */
			$order->add_order_note( sprintf( _n( '%d digital product passport was issued.', '%d digital product passports were issued.', $created, 'codefokus-handwerk-produktpass' ), $created ) );
		}
		HWPP_Errors::set_order_errors( $order, $errors );
		return array( 'created' => $created, 'errors' => $errors );
	}

	/**
	 * Clear error message with a way to fix it.
	 */
	public static function missing_message( $product, array $missing ) {
		$parts = wp_list_pluck( $missing, 'missing' );
		$last  = array_pop( $parts );
		$list  = $parts ? implode( ', ', $parts ) . ' ' . __( 'and', 'codefokus-handwerk-produktpass' ) . ' ' . $last : $last;
		$name  = $product->get_parent_id() ? get_the_title( $product->get_parent_id() ) : $product->get_name();
		return sprintf(
			/* translators: 1: product name, 2: list of missing data */
			__( 'The product passport could not be created because %2$s is missing for the product “%1$s”. How to fix it: edit product → “Product passport” tab → add the information → Update. Then click “Generate passports now” in the order.', 'codefokus-handwerk-produktpass' ),
			$name,
			$list
		);
	}

	public static function serial( $order, $item, $product, $pos, $unit, $qty, $is_test ) {
		$format = (string) HWPP_Settings::get( 'serial_format', 'DPP-{year}-{order}-{pos}' );
		if ( $qty > 1 && false === strpos( $format, '{unit}' ) ) {
			$format .= '-{unit}';
		}
		$sku     = $product ? preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $product->get_sku() ) : '';
		$replace = array(
			'{year}'  => gmdate( 'Y' ),
			'{order}' => $order ? $order->get_order_number() : '0000',
			'{pos}'   => str_pad( (string) $pos, 2, '0', STR_PAD_LEFT ),
			'{unit}'  => (string) $unit,
			'{sku}'   => $sku ? $sku : 'X',
			'{rand}'  => strtoupper( substr( bin2hex( random_bytes( 3 ) ), 0, 5 ) ),
		);
		$serial = strtr( $format, $replace );
		$serial = preg_replace( '/[^A-Za-z0-9._-]/', '', $serial );
		if ( $is_test ) {
			$serial = 'TEST-' . $serial . '-' . $replace['{rand}'];
		}
		return apply_filters( 'hwpp_serial', $serial, $order, $item, $product, $unit );
	}

	/**
	 * Builds the unchangeable data snapshot of a passport.
	 * Deliberately contains NO personal customer data (GDPR), because the passport is publicly accessible.
	 */
	/**
	 * Keys of the product attributes of an order item (e.g. "pa_holzart", "farbe").
	 *
	 * @param WC_Order_Item_Product $item Order item.
	 * @return string[]
	 */
	public static function attribute_keys( $item ) {
		$keys    = array();
		$product = is_callable( array( $item, 'get_product' ) ) ? $item->get_product() : null;
		if ( $product ) {
			$source = $product->get_parent_id() ? wc_get_product( $product->get_parent_id() ) : $product;
			foreach ( array( $product, $source ) as $p ) {
				if ( $p ) {
					foreach ( array_keys( (array) $p->get_attributes() ) as $k ) {
						$keys[] = sanitize_title( (string) $k );
					}
				}
			}
		}
		/**
		 * Order item meta keys that may appear in the passport as product attributes.
		 *
		 * @param string[] $keys Attribute keys.
		 * @param object   $item Order item.
		 */
		return array_values( array_unique( (array) apply_filters( 'hwpp_item_attribute_keys', $keys, $item ) ) );
	}

	public static function build_payload( $product, $item, $order, $serial, $is_test ) {
		$parent_id = $product->get_parent_id() ? $product->get_parent_id() : $product->get_id();
		$languages = HWPP_I18n::languages();
		$primary   = $order ? HWPP_I18n::order_language( $order ) : HWPP_I18n::default_language();
		if ( ! isset( $languages[ $primary ] ) ) {
			$languages[ $primary ] = get_locale();
		}

		$image_id = $product->get_image_id() ? $product->get_image_id() : get_post_thumbnail_id( $parent_id );
		$s        = HWPP_Settings::all();

		$payload = array(
			'schema'           => 'hwpp/1',
			'issued_at'        => gmdate( 'c' ),
			'serial'           => $serial,
			'test'             => (bool) $is_test,
			'primary_language' => $primary,
			'languages'        => $languages,
			'order'            => $order ? array(
				'number' => (string) $order->get_order_number(),
				'date'   => $order->get_date_created() ? $order->get_date_created()->date( 'Y-m-d' ) : '',
			) : null,
			'product'          => array(
				'id'         => $product->get_id(),
				'parent_id'  => $parent_id,
				'sku'        => (string) $product->get_sku(),
				'gtin'       => method_exists( $product, 'get_global_unique_id' ) ? (string) $product->get_global_unique_id() : '',
				'image'      => $image_id ? (string) wp_get_attachment_image_url( $image_id, 'large' ) : '',
				'dimensions' => self::dimensions( $product ),
				'weight'     => $product->get_weight() ? $product->get_weight() . ' ' . get_option( 'woocommerce_weight_unit' ) : '',
			),
			'manufacturer'     => array(
				'name'     => $s['company_name'],
				'street'   => $s['company_street'],
				'zip'      => $s['company_zip'],
				'city'     => $s['company_city'],
				'country'  => $s['company_country'],
				'email'    => $s['company_email'],
				'phone'    => $s['company_phone'],
				'website'  => $s['company_website'],
				'vat'      => $s['company_vat'],
				'register' => $s['company_register'],
			),
			'content'          => array(),
		);

		foreach ( array_keys( $languages ) as $lang ) {
			$source_id = $parent_id;
			if ( $lang !== $primary ) {
				$source_id = HWPP_I18n::translated_id( $parent_id, 'product', $lang );
				if ( ! $source_id ) {
					continue; // Keine Übersetzung vorhanden – Anzeige fällt auf die Hauptsprache zurück.
				}
			}
			$payload['content'][ $lang ] = self::content_for( $source_id, $parent_id, $product, $item, $lang, $lang === $primary );
		}

		return apply_filters( 'hwpp_payload', $payload, $product, $item, $order );
	}

	/**
	 * Dimensions as a finished text (L × W × H), so the order in the hash snapshot stays stable.
	 */
	private static function dimensions( $product ) {
		$dims = array_filter( array( (string) $product->get_length(), (string) $product->get_width(), (string) $product->get_height() ), 'strlen' );
		if ( count( $dims ) < 2 ) {
			return '';
		}
		return implode( ' × ', $dims ) . ' ' . get_option( 'woocommerce_dimension_unit' );
	}

	private static function content_for( $source_id, $parent_id, $product, $item, $lang, $is_primary ) {
		$source = wc_get_product( $source_id );
		$name   = $source ? $source->get_name() : $product->get_name();
		if ( $is_primary && $item ) {
			$name = $item->get_name();
		} elseif ( $is_primary && $product->get_parent_id() ) {
			$name = $product->get_name();
		}

		$fields = array();
		foreach ( HWPP_Fields::product_fields() as $key => $def ) {
			if ( in_array( $def['type'], array( 'materials', 'gallery' ), true ) ) {
				continue;
			}
			$v = HWPP_Fields::get_product_value( $source_id, $key );
			if ( '' === (string) $v ) {
				$v = HWPP_Fields::get_product_value( $parent_id, $key ); // Fallback auf Original.
			}
			if ( '' !== (string) $v ) {
				$fields[ $key ] = (string) $v;
			}
		}

		$materials = array();
		$ids       = HWPP_Fields::get_product_value( $parent_id, 'materials' );
		foreach ( $ids as $mid ) {
			$tid  = $is_primary ? 0 : HWPP_I18n::translated_id( $mid, HWPP_Materials::POST_TYPE, $lang );
			$snap = HWPP_Materials::snapshot( $tid ? $tid : $mid );
			if ( $snap ) {
				$materials[] = $snap;
			}
		}

		$attributes = array();
		if ( $is_primary && $item ) {
			$allowed = self::attribute_keys( $item );
			foreach ( $item->get_formatted_meta_data( '_', true ) as $meta ) {
				// Only product attributes (e.g. wood type of a variation) – never other item
				// data, which may contain information entered by the customer.
				if ( ! in_array( sanitize_title( (string) $meta->key ), $allowed, true ) ) {
					continue;
				}
				$attributes[] = array(
					'label' => wp_strip_all_tags( $meta->display_key ),
					'value' => wp_strip_all_tags( $meta->display_value ),
				);
			}
		}

		$gallery = array();
		foreach ( HWPP_Fields::get_product_value( $parent_id, 'gallery' ) as $aid ) {
			$large = wp_get_attachment_image_url( $aid, 'large' );
			if ( $large ) {
				$gallery[] = array(
					'url'   => (string) $large,
					'thumb' => (string) wp_get_attachment_image_url( $aid, 'medium' ),
					'alt'   => (string) get_post_meta( $aid, '_wp_attachment_image_alt', true ),
				);
			}
		}

		$design = HWPP_Settings::design();
		$certs  = (string) HWPP_Settings::get( 'default_certifications' );

		return array(
			'name'                   => wp_strip_all_tags( $name ),
			'short_description'      => $source ? wp_strip_all_tags( $source->get_short_description() ) : '',
			'attributes'             => $attributes,
			'fields'                 => $fields,
			'materials'              => $materials,
			'gallery'                => $gallery,
			'company_certifications' => $is_primary ? $certs : HWPP_I18n::translate_string( 'default_certifications', $certs, $lang ),
			/**
			 * Texts stored with the passport (e.g. titles). Extensions add their own texts.
			 *
			 * @param array  $texts  Texts by key.
			 * @param array  $design Design settings.
			 * @param string $lang   Language code.
			 */
			'texts'                  => (array) apply_filters(
				'hwpp_payload_texts',
				array(
					'passport_title' => HWPP_I18n::translate_string( 'passport_title', $design['passport_title'], $lang ),
				),
				$design,
				$lang
			),
		);
	}

	/**
	 * Test mode: creates a sample passport without an order.
	 *
	 * @return object|WP_Error
	 */
	public static function generate_test( $product_id ) {
		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			return new WP_Error( 'hwpp_no_product', __( 'Please select a product for which the test passport should be created.', 'codefokus-handwerk-produktpass' ) );
		}
		$missing = HWPP_Fields::missing_required( $product->get_id() );
		if ( $missing ) {
			return new WP_Error( 'hwpp_missing', self::missing_message( $product, $missing ) );
		}
		$serial  = self::serial( null, null, $product, 1, 1, 1, true );
		$payload = self::build_payload( $product, null, null, $serial, true );
		return HWPP_Repository::insert(
			array(
				'serial'     => $serial,
				'order_id'   => 0,
				'item_id'    => 0,
				'unit_index' => 1,
				'product_id' => $product->get_id(),
				'is_test'    => 1,
				'language'   => $payload['primary_language'],
			),
			$payload
		);
	}
}
