<?php
/**
 * Wiederverwendbare Material-Bibliothek (eigener Beitragstyp).
 *
 * @package HandwerkProduktpass
 */

defined( 'ABSPATH' ) || exit;

class HWPP_Materials {

	const POST_TYPE = 'hwpp_material';

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_post_type' ) );
		add_action( 'add_meta_boxes_' . self::POST_TYPE, array( __CLASS__, 'add_meta_box' ) );
		add_action( 'save_post_' . self::POST_TYPE, array( __CLASS__, 'save' ), 10, 2 );
		add_filter( 'manage_' . self::POST_TYPE . '_posts_columns', array( __CLASS__, 'columns' ) );
		add_action( 'manage_' . self::POST_TYPE . '_posts_custom_column', array( __CLASS__, 'column_content' ), 10, 2 );
		add_filter( 'enter_title_here', array( __CLASS__, 'title_placeholder' ), 10, 2 );
	}

	public static function register_post_type() {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'              => array(
					'name'               => __( 'Materials', 'codefokus-handwerk-produktpass' ),
					'singular_name'      => __( 'Material', 'codefokus-handwerk-produktpass' ),
					'add_new'            => __( 'Add material', 'codefokus-handwerk-produktpass' ),
					'add_new_item'       => __( 'Create new material', 'codefokus-handwerk-produktpass' ),
					'edit_item'          => __( 'Edit material', 'codefokus-handwerk-produktpass' ),
					'search_items'       => __( 'Search materials', 'codefokus-handwerk-produktpass' ),
					'not_found'          => __( 'No materials created yet. Create your woods, leathers or metals once and then assign them to as many products as you like.', 'codefokus-handwerk-produktpass' ),
					'menu_name'          => __( 'Material library', 'codefokus-handwerk-produktpass' ),
				),
				'public'              => false,
				'show_ui'             => true,
				'show_in_menu'        => 'hwpp',
				'supports'            => array( 'title', 'thumbnail' ),
				'capability_type'     => 'product',
				'map_meta_cap'        => true,
				'show_in_rest'        => false,
				'exclude_from_search' => true,
			)
		);
	}

	public static function title_placeholder( $text, $post ) {
		if ( $post && self::POST_TYPE === $post->post_type ) {
			return __( 'Material name, e.g. “Solid oak, oiled”', 'codefokus-handwerk-produktpass' );
		}
		return $text;
	}

	public static function add_meta_box() {
		add_meta_box( 'hwpp_material_data', __( 'Origin, certificates & evidence', 'codefokus-handwerk-produktpass' ), array( __CLASS__, 'render_meta_box' ), self::POST_TYPE, 'normal', 'high' );
	}

	public static function render_meta_box( $post ) {
		wp_nonce_field( 'hwpp_material_save', 'hwpp_material_nonce' );
		echo '<p class="description">' . esc_html__( 'You only enter this information once. Every product you assign this material to automatically includes it in the product passport.', 'codefokus-handwerk-produktpass' ) . '</p>';
		echo '<table class="form-table hwpp-form">';
		foreach ( HWPP_Fields::material_fields() as $key => $field ) {
			$value = get_post_meta( $post->ID, HWPP_Fields::META_PREFIX . $key, true );
			HWPP_Admin::render_field_row( 'hwpp_material[' . $key . ']', 'hwpp_m_' . $key, $field, $value );
		}
		echo '</table>';
	}

	public static function save( $post_id, $post ) {
		if ( ! isset( $_POST['hwpp_material_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['hwpp_material_nonce'] ) ), 'hwpp_material_save' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		$data = isset( $_POST['hwpp_material'] ) ? HWPP_Admin::clean_input( wp_unslash( $_POST['hwpp_material'] ) ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitised by clean_input(), then per field.
		foreach ( HWPP_Fields::material_fields() as $key => $field ) {
			$raw = isset( $data[ $key ] ) ? $data[ $key ] : '';
			update_post_meta( $post_id, HWPP_Fields::META_PREFIX . $key, HWPP_Admin::sanitize_field( $raw, $field ) );
		}
	}

	public static function columns( $cols ) {
		$new = array();
		foreach ( $cols as $k => $v ) {
			$new[ $k ] = $v;
			if ( 'title' === $k ) {
				$new['hwpp_type']   = __( 'Type', 'codefokus-handwerk-produktpass' );
				$new['hwpp_origin'] = __( 'Origin', 'codefokus-handwerk-produktpass' );
				$new['hwpp_certs']  = __( 'Certificates', 'codefokus-handwerk-produktpass' );
				$new['hwpp_usage']  = __( 'Used in', 'codefokus-handwerk-produktpass' );
			}
		}
		return $new;
	}

	public static function column_content( $col, $post_id ) {
		$p = HWPP_Fields::META_PREFIX;
		switch ( $col ) {
			case 'hwpp_type':
				$types = HWPP_Fields::material_types();
				$t     = get_post_meta( $post_id, $p . 'material_type', true );
				echo esc_html( isset( $types[ $t ] ) ? $types[ $t ] : '–' );
				break;
			case 'hwpp_origin':
				$c = get_post_meta( $post_id, $p . 'origin_country', true );
				$r = get_post_meta( $post_id, $p . 'origin_region', true );
				if ( ! $c ) {
					echo '<span class="hwpp-badge hwpp-badge--warn">' . esc_html__( 'Origin missing', 'codefokus-handwerk-produktpass' ) . '</span>';
				} else {
					echo esc_html( trim( HWPP_Fields::country_name( $c ) . ( $r ? ' – ' . $r : '' ) ) );
				}
				break;
			case 'hwpp_certs':
				echo esc_html( get_post_meta( $post_id, $p . 'certifications', true ) ?: '–' );
				break;
			case 'hwpp_usage':
				echo esc_html( (string) self::usage_count( $post_id ) );
				break;
		}
	}

	public static function usage_count( $material_id ) {
		global $wpdb;
		// Materials are stored as a serialised array: i:<idx>;i:<id>;
		$like = '%' . $wpdb->esc_like( 'i:' . absint( $material_id ) . ';' ) . '%';
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value LIKE %s", HWPP_Fields::META_PREFIX . 'materials', $like ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	public static function choices() {
		$posts = get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => 500,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'suppress_filters' => false,
			)
		);
		$out = array();
		foreach ( $posts as $p ) {
			$out[ $p->ID ] = $p->post_title;
		}
		return $out;
	}

	/**
	 * Data snapshot of a material for the passport.
	 */
	public static function snapshot( $material_id ) {
		$post = get_post( $material_id );
		if ( ! $post || self::POST_TYPE !== $post->post_type ) {
			return null;
		}
		$data = array( 'id' => (int) $material_id, 'name' => $post->post_title );
		foreach ( array_keys( HWPP_Fields::material_fields() ) as $key ) {
			$v = get_post_meta( $material_id, HWPP_Fields::META_PREFIX . $key, true );
			if ( '' !== (string) $v ) {
				$data[ $key ] = (string) $v;
			}
		}
		return $data;
	}
}
