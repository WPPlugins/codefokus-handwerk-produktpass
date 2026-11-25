<?php
/**
 * Product data tab „Produktpass“ and required-field check on save and publish.
 *
 * @package HandwerkProduktpass
 */

defined( 'ABSPATH' ) || exit;

class HWPP_Product_Data {

	public static function init() {
		add_filter( 'woocommerce_product_data_tabs', array( __CLASS__, 'add_tab' ) );
		add_action( 'woocommerce_product_data_panels', array( __CLASS__, 'render_panel' ) );
		add_action( 'woocommerce_process_product_meta', array( __CLASS__, 'save' ), 20 );
		add_action( 'admin_notices', array( __CLASS__, 'show_product_notice' ) );
		add_filter( 'manage_edit-product_columns', array( __CLASS__, 'add_column' ), 20 );
		add_action( 'manage_product_posts_custom_column', array( __CLASS__, 'render_column' ), 10, 2 );
	}

	/**
	 * Does the product passport apply to this product?
	 */
	public static function applies_to( $product_id ) {
		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			return false;
		}
		$pid = $product->get_parent_id() ? $product->get_parent_id() : $product->get_id();
		if ( 'no' === get_post_meta( $pid, '_hwpp_enabled', true ) ) {
			return false;
		}
		$cats = array_filter( array_map( 'absint', (array) HWPP_Settings::get( 'product_categories' ) ) );
		if ( $cats && ! has_term( $cats, 'product_cat', $pid ) ) {
			return false;
		}
		return (bool) apply_filters( 'hwpp_applies_to_product', true, $pid );
	}

	public static function add_tab( $tabs ) {
		$tabs['hwpp'] = array(
			'label'    => __( 'Product passport', 'codefokus-handwerk-produktpass' ),
			'target'   => 'hwpp_product_data',
			'class'    => array(),
			'priority' => 65,
		);
		return $tabs;
	}

	public static function render_panel() {
		global $post;
		$pid      = $post->ID;
		$fields   = HWPP_Fields::product_fields();
		$required = (array) HWPP_Settings::get( 'required_fields' );
		$enabled  = 'no' !== get_post_meta( $pid, '_hwpp_enabled', true );
		$missing  = HWPP_Fields::missing_required( $pid );

		wp_nonce_field( 'hwpp_product_save', 'hwpp_product_nonce' );
		echo '<div id="hwpp_product_data" class="panel woocommerce_options_panel hidden hwpp-product-panel">';

		echo '<div class="hwpp-panel-intro">';
		echo '<p><strong>' . esc_html__( 'Digital product passport', 'codefokus-handwerk-produktpass' ) . '</strong> ';
		HWPP_Glossary::e( 'dpp' );
		echo '<br>' . esc_html__( 'When sold, this information is automatically and immutably included in the customer\'s product passport.', 'codefokus-handwerk-produktpass' ) . ' ';
		HWPP_Glossary::e( 'unveraenderlich' );
		echo '</p>';

		if ( $enabled && $missing ) {
			echo '<div class="hwpp-inline-warning"><strong>' . esc_html__( 'Not complete yet:', 'codefokus-handwerk-produktpass' ) . '</strong><ul>';
			foreach ( $missing as $m ) {
				echo '<li>' . esc_html( ucfirst( $m['missing'] ) ) . '</li>';
			}
			echo '</ul>' . esc_html__( 'Without this information, no product passport can be created for an order.', 'codefokus-handwerk-produktpass' ) . '</div>';
		} elseif ( $enabled ) {
			echo '<div class="hwpp-inline-ok">✓ ' . esc_html__( 'All required information present – the product passport can be issued.', 'codefokus-handwerk-produktpass' ) . '</div>';
		}
		echo '</div>';

		echo '<p class="form-field"><label for="hwpp_enabled">' . esc_html__( 'Issue product passport', 'codefokus-handwerk-produktpass' ) . '</label>';
		echo '<input type="checkbox" id="hwpp_enabled" name="hwpp_enabled" value="yes" ' . checked( $enabled, true, false ) . '> ';
		echo '<span class="description">' . esc_html__( 'Automatically create a passport every time this product is sold.', 'codefokus-handwerk-produktpass' ) . '</span></p>';

		$section_labels = HWPP_Fields::sections();
		foreach ( $section_labels as $section_key => $section_label ) {
			$in_section = array_filter( $fields, static function ( $f ) use ( $section_key ) { return $f['section'] === $section_key; } );
			if ( ! $in_section ) {
				continue;
			}
			echo '<h4 class="hwpp-panel-heading">' . esc_html( $section_label ) . '</h4>';
			foreach ( $in_section as $key => $field ) {
				self::render_product_field( $key, $field, HWPP_Fields::get_product_value( $pid, $key ), in_array( $key, $required, true ) );
			}
		}
		echo '</div>';
	}

	private static function render_product_field( $key, $field, $value, $is_required ) {
		$id    = 'hwpp_' . $key;
		$name  = 'hwpp[' . $key . ']';
		$label = esc_html( $field['label'] ) . ( $is_required ? ' <abbr class="required" title="' . esc_attr__( 'Required field', 'codefokus-handwerk-produktpass' ) . '">*</abbr>' : '' );
		$tip   = ! empty( $field['tip'] ) ? HWPP_Glossary::tip( $field['tip'] ) : '';
		$ph    = isset( $field['placeholder'] ) ? $field['placeholder'] : '';

		echo '<p class="form-field ' . esc_attr( $id ) . '_field">';
		echo wp_kses( '<label for="' . esc_attr( $id ) . '">' . $label . ' ' . $tip . '</label>', HWPP_Admin::kses() );

		switch ( $field['type'] ) {
			case 'country':
				$countries = WC()->countries->get_countries();
				echo '<select id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" class="wc-enhanced-select" style="width:50%">';
				echo '<option value="">' . esc_html__( '– please select –', 'codefokus-handwerk-produktpass' ) . '</option>';
				foreach ( $countries as $code => $cname ) {
					echo '<option value="' . esc_attr( $code ) . '" ' . selected( $value, $code, false ) . '>' . esc_html( html_entity_decode( $cname ) ) . '</option>';
				}
				echo '</select>';
				break;

			case 'materials':
				$choices = HWPP_Materials::choices();
				echo '<select id="' . esc_attr( $id ) . '" name="hwpp[materials][]" multiple class="wc-enhanced-select" style="width:50%" data-placeholder="' . esc_attr__( 'Choose materials from the library …', 'codefokus-handwerk-produktpass' ) . '">';
				foreach ( $choices as $mid => $mname ) {
					echo '<option value="' . esc_attr( $mid ) . '" ' . selected( in_array( (int) $mid, (array) $value, true ), true, false ) . '>' . esc_html( $mname ) . '</option>';
				}
				echo '</select>';
				echo '<span class="description" style="display:block;clear:both;margin-left:150px">';
				if ( empty( $choices ) ) {
					echo esc_html__( 'Your material library is still empty.', 'codefokus-handwerk-produktpass' ) . ' ';
				}
				echo '<a href="' . esc_url( admin_url( 'post-new.php?post_type=' . HWPP_Materials::POST_TYPE ) ) . '" target="_blank">' . esc_html__( '+ Create new material (opens a new tab, then reload this page)', 'codefokus-handwerk-produktpass' ) . '</a></span>';
				break;

			case 'select':
				echo '<select id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '"><option value="">' . esc_html__( '– please select –', 'codefokus-handwerk-produktpass' ) . '</option>';
				foreach ( $field['options'] as $k => $l ) {
					echo '<option value="' . esc_attr( $k ) . '" ' . selected( $value, $k, false ) . '>' . esc_html( $l ) . '</option>';
				}
				echo '</select>';
				break;

			case 'gallery':
				$ids = (array) $value;
				echo '<span class="hwpp-gallery-field" data-title="' . esc_attr__( 'Select workshop photos', 'codefokus-handwerk-produktpass' ) . '">';
				echo '<input type="hidden" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( implode( ',', $ids ) ) . '">';
				echo '<span class="hwpp-gallery-thumbs">';
				foreach ( $ids as $aid ) {
					echo wp_get_attachment_image( $aid, array( 60, 60 ) );
				}
				echo '</span><button type="button" class="button hwpp-gallery-select">' . esc_html__( 'Choose photos', 'codefokus-handwerk-produktpass' ) . '</button> ';
				echo '<span class="description">' . esc_html__( 'e.g. raw wood, work steps, finished piece in the workshop', 'codefokus-handwerk-produktpass' ) . '</span></span>';
				break;

			case 'textarea':
				echo '<textarea id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" rows="3" placeholder="' . esc_attr( $ph ) . '" style="width:60%">' . esc_textarea( (string) $value ) . '</textarea>';
				break;

			case 'number':
				echo '<input type="number" step="0.01" min="0" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( (string) $value ) . '" style="width:120px"> ' . esc_html( $ph );
				break;

			default:
				echo '<input type="text" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( (string) $value ) . '" placeholder="' . esc_attr( $ph ) . '" style="width:60%">';
		}
		echo '</p>';
	}

	public static function save( $product_id ) {
		if ( ! isset( $_POST['hwpp_product_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['hwpp_product_nonce'] ) ), 'hwpp_product_save' ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_product', $product_id ) ) {
			return;
		}

		update_post_meta( $product_id, '_hwpp_enabled', isset( $_POST['hwpp_enabled'] ) ? 'yes' : 'no' );

		$data = isset( $_POST['hwpp'] ) ? HWPP_Admin::clean_input( wp_unslash( $_POST['hwpp'] ) ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitised by clean_input(), then per field.
		foreach ( HWPP_Fields::product_fields() as $key => $field ) {
			$raw = isset( $data[ $key ] ) ? $data[ $key ] : ( 'materials' === $field['type'] ? array() : '' );
			if ( 'materials' === $field['type'] ) {
				$value = array_values( array_unique( array_filter( array_map( 'intval', (array) $raw ) ) ) );
			} elseif ( 'gallery' === $field['type'] ) {
				$value = array_values( array_unique( array_filter( array_map( 'intval', explode( ',', (string) $raw ) ) ) ) );
			} else {
				$value = HWPP_Admin::sanitize_field( $raw, $field );
			}
			update_post_meta( $product_id, HWPP_Fields::META_PREFIX . $key, $value );
		}

		self::validate_after_save( $product_id );
	}

	/**
	 * Warns about missing required information and optionally sets the product to „Draft“.
	 */
	private static function validate_after_save( $product_id ) {
		if ( ! self::applies_to( $product_id ) ) {
			return;
		}
		$missing = HWPP_Fields::missing_required( $product_id );
		if ( ! $missing ) {
			return;
		}
		$status   = get_post_status( $product_id );
		$demoted  = false;
		if ( 'publish' === $status && HWPP_Settings::get( 'block_publish' ) ) {
			remove_action( 'woocommerce_process_product_meta', array( __CLASS__, 'save' ), 20 );
			wp_update_post( array( 'ID' => $product_id, 'post_status' => 'draft' ) );
			$demoted = true;
		}
		set_transient(
			'hwpp_product_notice_' . get_current_user_id(),
			array(
				'product_id' => $product_id,
				'missing'    => wp_list_pluck( $missing, 'missing' ),
				'demoted'    => $demoted,
				'published'  => 'publish' === $status,
			),
			120
		);
	}

	public static function show_product_notice() {
		$key    = 'hwpp_product_notice_' . get_current_user_id();
		$notice = get_transient( $key );
		if ( ! $notice ) {
			return;
		}
		delete_transient( $key );
		$name = get_the_title( $notice['product_id'] );
		echo '<div class="notice notice-warning hwpp-notice"><p><strong>';
		if ( $notice['demoted'] ) {
			/* translators: %s: product name */
			echo esc_html( sprintf( __( 'The product “%s” was saved as a draft because required information for the product passport is missing.', 'codefokus-handwerk-produktpass' ), $name ) );
		} else {
			/* translators: %s: product name */
			echo esc_html( sprintf( __( 'Information for the product passport is missing for the product “%s”.', 'codefokus-handwerk-produktpass' ), $name ) );
		}
		echo '</strong></p><p>' . esc_html__( 'Please complete in the “Product passport” tab (at the bottom of the product data):', 'codefokus-handwerk-produktpass' ) . '</p><ul class="ul-disc">';
		foreach ( $notice['missing'] as $m ) {
			echo '<li>' . esc_html( ucfirst( $m ) ) . '</li>';
		}
		echo '</ul>';
		if ( ! $notice['demoted'] && $notice['published'] ) {
			echo '<p>' . esc_html__( 'The product is still visible. If it is sold like this, no product passport can be issued – you will then see a notice in the order and can generate the passport with one click after completing the information.', 'codefokus-handwerk-produktpass' ) . '</p>';
		}
		echo '</div>';
	}

	public static function add_column( $cols ) {
		$cols['hwpp'] = '<span title="' . esc_attr__( 'Product passport', 'codefokus-handwerk-produktpass' ) . '">' . esc_html__( 'Passport', 'codefokus-handwerk-produktpass' ) . '</span>';
		return $cols;
	}

	public static function render_column( $col, $post_id ) {
		if ( 'hwpp' !== $col ) {
			return;
		}
		if ( ! self::applies_to( $post_id ) ) {
			echo '<span class="hwpp-muted" title="' . esc_attr__( 'No passport is issued for this product.', 'codefokus-handwerk-produktpass' ) . '">–</span>';
			return;
		}
		$missing = HWPP_Fields::missing_required( $post_id );
		if ( $missing ) {
			$list = implode( ', ', wp_list_pluck( $missing, 'label' ) );
			/* translators: %s: comma separated list of missing fields */
			echo '<span class="hwpp-badge hwpp-badge--warn" title="' . esc_attr( sprintf( __( 'Missing: %s', 'codefokus-handwerk-produktpass' ), $list ) ) . '">⚠ ' . esc_html( count( $missing ) ) . '</span>';
		} else {
			echo '<span class="hwpp-badge hwpp-badge--ok" title="' . esc_attr__( 'Complete', 'codefokus-handwerk-produktpass' ) . '">✓</span>';
		}
	}
}
