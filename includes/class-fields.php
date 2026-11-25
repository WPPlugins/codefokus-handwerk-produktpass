<?php
/**
 * Central field registry for product passport data.
 *
 * All fields are defined here once and reused everywhere (product tab, required-field check,
 * passport output, error messages).
 *
 * @package HandwerkProduktpass
 */

defined( 'ABSPATH' ) || exit;

class HWPP_Fields {

	const META_PREFIX = '_hwpp_';

	/**
	 * Product-related passport fields.
	 *
	 * type: text|textarea|country|number|materials
	 * missing: phrase for clear error messages („… because product X is missing {missing}“)
	 */
	public static function product_fields() {
		$fields = array(
			'manufacturing_country' => array(
				'label'    => __( 'Country of manufacture', 'codefokus-handwerk-produktpass' ),
				'type'     => 'country',
				'section'  => 'origin',
				'tip'      => 'herstellungsort',
				'missing'  => __( 'the country of manufacture', 'codefokus-handwerk-produktpass' ),
				'legal'    => true,
			),
			'manufacturing_place'   => array(
				'label'       => __( 'Place of manufacture / workshop', 'codefokus-handwerk-produktpass' ),
				'type'        => 'text',
				'section'     => 'origin',
				'tip'         => 'herstellungsort',
				'placeholder' => __( 'e.g. Muster Joinery, 79098 Freiburg', 'codefokus-handwerk-produktpass' ),
				'missing'     => __( 'the place of manufacture', 'codefokus-handwerk-produktpass' ),
				'legal'       => true,
			),
			'maker'                 => array(
				'label'       => __( 'Made by', 'codefokus-handwerk-produktpass' ),
				'type'        => 'text',
				'section'     => 'origin',
				'placeholder' => __( 'e.g. master joiner Anna Muster', 'codefokus-handwerk-produktpass' ),
				'missing'     => __( 'the name of the maker', 'codefokus-handwerk-produktpass' ),
			),
			'materials'             => array(
				'label'   => __( 'Materials used', 'codefokus-handwerk-produktpass' ),
				'type'    => 'materials',
				'section' => 'materials',
				'tip'     => 'rohstoffherkunft',
				'missing' => __( 'at least one material from the material library', 'codefokus-handwerk-produktpass' ),
				'legal'   => true,
			),
			'composition'           => array(
				'label'       => __( 'Material composition', 'codefokus-handwerk-produktpass' ),
				'type'        => 'text',
				'section'     => 'materials',
				'placeholder' => __( 'e.g. 92 % solid oak, 8 % steel', 'codefokus-handwerk-produktpass' ),
				'missing'     => __( 'the material composition', 'codefokus-handwerk-produktpass' ),
			),
			'care'                  => array(
				'label'       => __( 'Care instructions', 'codefokus-handwerk-produktpass' ),
				'type'        => 'textarea',
				'section'     => 'care',
				'placeholder' => __( 'e.g. Clean with a slightly damp cloth, re-oil once a year.', 'codefokus-handwerk-produktpass' ),
				'missing'     => __( 'the care instructions', 'codefokus-handwerk-produktpass' ),
				'legal'       => true,
			),
			'repairability'         => array(
				'label'       => __( 'Repairability', 'codefokus-handwerk-produktpass' ),
				'type'        => 'textarea',
				'section'     => 'repair',
				'tip'         => 'reparierbarkeit',
				'placeholder' => __( 'e.g. Screwed instead of glued, surface can be sanded and re-oiled. Repairs possible in our workshop.', 'codefokus-handwerk-produktpass' ),
				'missing'     => __( 'the repairability information', 'codefokus-handwerk-produktpass' ),
				'legal'       => true,
			),
			'spare_parts'           => array(
				'label'       => __( 'Spare parts & service', 'codefokus-handwerk-produktpass' ),
				'type'        => 'textarea',
				'section'     => 'repair',
				'tip'         => 'ersatzteile',
				'placeholder' => __( 'e.g. Fittings available for at least 10 years.', 'codefokus-handwerk-produktpass' ),
				'missing'     => __( 'the spare parts information', 'codefokus-handwerk-produktpass' ),
			),
			'warranty'              => array(
				'label'       => __( 'Guarantee / warranty', 'codefokus-handwerk-produktpass' ),
				'type'        => 'text',
				'section'     => 'repair',
				'placeholder' => __( 'e.g. 2-year statutory warranty + 5-year workshop guarantee', 'codefokus-handwerk-produktpass' ),
				'missing'     => __( 'the guarantee information', 'codefokus-handwerk-produktpass' ),
			),
			'end_of_life'           => array(
				'label'       => __( 'Disposal & recycling', 'codefokus-handwerk-produktpass' ),
				'type'        => 'textarea',
				'section'     => 'sustainability',
				'tip'         => 'recycling',
				'placeholder' => __( 'e.g. Untreated wood compostable/recyclable, metal parts to the recycling centre.', 'codefokus-handwerk-produktpass' ),
				'missing'     => __( 'the disposal and recycling information', 'codefokus-handwerk-produktpass' ),
			),
			'certifications'        => array(
				'label'       => __( 'Product certifications', 'codefokus-handwerk-produktpass' ),
				'type'        => 'text',
				'section'     => 'sustainability',
				'tip'         => 'zertifizierung',
				'placeholder' => __( 'e.g. Blue Angel, master craft business certified by the Chamber of Crafts', 'codefokus-handwerk-produktpass' ),
				'missing'     => __( 'the certification information', 'codefokus-handwerk-produktpass' ),
			),
			'co2'                   => array(
				'label'       => __( 'CO₂ footprint', 'codefokus-handwerk-produktpass' ),
				'type'        => 'number',
				'placeholder' => 'kg CO₂e',
				'section'     => 'sustainability',
				'tip'         => 'co2',
				'missing'     => __( 'the CO₂ footprint', 'codefokus-handwerk-produktpass' ),
			),
			'care_interval'         => array(
				'label'       => __( 'Care interval (months)', 'codefokus-handwerk-produktpass' ),
				'type'        => 'number',
				'section'     => 'care',
				'tip'         => 'pflegeerinnerung',
				'placeholder' => __( 'Months', 'codefokus-handwerk-produktpass' ),
				'missing'     => __( 'the care interval', 'codefokus-handwerk-produktpass' ),
			),
			'story'                 => array(
				'label'       => __( 'Story', 'codefokus-handwerk-produktpass' ),
				'type'        => 'textarea',
				'section'     => 'story',
				'tip'         => 'geschichte',
				'placeholder' => __( 'e.g. The wood comes from an oak that fell in a storm on the Schauinsland in 2019. After three years of air drying …', 'codefokus-handwerk-produktpass' ),
				'missing'     => __( 'the story', 'codefokus-handwerk-produktpass' ),
			),
			'work_hours'            => array(
				'label'       => __( 'Working hours (approx.)', 'codefokus-handwerk-produktpass' ),
				'type'        => 'number',
				'section'     => 'story',
				'placeholder' => __( 'Hours', 'codefokus-handwerk-produktpass' ),
				'missing'     => __( 'the working hours', 'codefokus-handwerk-produktpass' ),
			),
			'gallery'               => array(
				'label'   => __( 'Workshop photos', 'codefokus-handwerk-produktpass' ),
				'type'    => 'gallery',
				'section' => 'story',
				'missing' => __( 'at least one workshop photo', 'codefokus-handwerk-produktpass' ),
			),
		);
		foreach ( HWPP_Presets::extra_fields() as $key => $def ) {
			if ( ! isset( $fields[ $key ] ) ) {
				$fields[ $key ] = $def;
			}
		}
		return apply_filters( 'hwpp_product_fields', $fields );
	}

	/**
	 * Fields of a material in the material library.
	 */
	public static function material_fields() {
		return apply_filters(
			'hwpp_material_fields',
			array(
				'material_type'  => array(
					'label'   => __( 'Material type', 'codefokus-handwerk-produktpass' ),
					'type'    => 'select',
					'options' => self::material_types(),
				),
				'origin_country' => array(
					'label' => __( 'Country of origin of the raw material', 'codefokus-handwerk-produktpass' ),
					'type'  => 'country',
					'tip'   => 'rohstoffherkunft',
				),
				'origin_region'  => array(
					'label'       => __( 'Region of origin / forest / mine', 'codefokus-handwerk-produktpass' ),
					'type'        => 'text',
					'placeholder' => __( 'e.g. Black Forest, Kirchzarten forestry', 'codefokus-handwerk-produktpass' ),
				),
				'supplier'       => array(
					'label'       => __( 'Supplier', 'codefokus-handwerk-produktpass' ),
					'type'        => 'text',
					'tip'         => 'lieferkette',
					'placeholder' => __( 'e.g. Huber sawmill Ltd.', 'codefokus-handwerk-produktpass' ),
				),
				'certifications' => array(
					'label'       => __( 'Certificates', 'codefokus-handwerk-produktpass' ),
					'type'        => 'text',
					'tip'         => 'zertifizierung',
					'placeholder' => __( 'e.g. FSC® 100 % (FSC-C000000), PEFC, Fairmined', 'codefokus-handwerk-produktpass' ),
				),
				'recycled_share' => array(
					'label' => __( 'Recycled content (%)', 'codefokus-handwerk-produktpass' ),
					'type'  => 'number',
				),
				'sustainability' => array(
					'label'       => __( 'Sustainability evidence', 'codefokus-handwerk-produktpass' ),
					'type'        => 'textarea',
					'tip'         => 'nachhaltigkeit',
					'placeholder' => __( 'e.g. Regionally harvested (< 50 km), air-dried, no tropical woods.', 'codefokus-handwerk-produktpass' ),
				),
				'evidence_url'   => array(
					'label' => __( 'Evidence document (link or file)', 'codefokus-handwerk-produktpass' ),
					'type'  => 'url',
					'tip'   => 'nachweis',
				),
				'care'           => array(
					'label'       => __( 'Material care note (optional)', 'codefokus-handwerk-produktpass' ),
					'type'        => 'textarea',
					'placeholder' => __( 'Shown in the passport in addition to the product care instructions.', 'codefokus-handwerk-produktpass' ),
				),
			)
		);
	}

	public static function material_types() {
		return apply_filters(
			'hwpp_material_types',
			array(
				'wood'    => __( 'Wood', 'codefokus-handwerk-produktpass' ),
				'leather' => __( 'Leather', 'codefokus-handwerk-produktpass' ),
				'metal'   => __( 'Metal', 'codefokus-handwerk-produktpass' ),
				'precious'=> __( 'Precious metal', 'codefokus-handwerk-produktpass' ),
				'gem'     => __( 'Gemstone', 'codefokus-handwerk-produktpass' ),
				'textile' => __( 'Textile', 'codefokus-handwerk-produktpass' ),
				'stone'   => __( 'Stone', 'codefokus-handwerk-produktpass' ),
				'glass'   => __( 'Glass / ceramics', 'codefokus-handwerk-produktpass' ),
				'finish'  => __( 'Finish (oil, wax, lacquer)', 'codefokus-handwerk-produktpass' ),
				'other'   => __( 'Other', 'codefokus-handwerk-produktpass' ),
			)
		);
	}

	/**
	 * Sections of the passport (order is set in the designer).
	 */
	public static function sections() {
		return array(
			'product'        => __( 'Product details', 'codefokus-handwerk-produktpass' ),
			'story'          => __( 'Story', 'codefokus-handwerk-produktpass' ),
			'materials'      => __( 'Materials & raw material origin', 'codefokus-handwerk-produktpass' ),
			'origin'         => __( 'Manufacturing', 'codefokus-handwerk-produktpass' ),
			'care'           => __( 'Care instructions', 'codefokus-handwerk-produktpass' ),
			'repair'         => __( 'Repair, spare parts & guarantee', 'codefokus-handwerk-produktpass' ),
			'sustainability' => __( 'Sustainability & certificates', 'codefokus-handwerk-produktpass' ),
			'manufacturer'   => __( 'Manufacturer (economic operator)', 'codefokus-handwerk-produktpass' ),
			'verification'   => __( 'Authenticity check', 'codefokus-handwerk-produktpass' ),
		);
	}

	public static function get_product_value( $product_id, $key ) {
		$value = get_post_meta( $product_id, self::META_PREFIX . $key, true );
		if ( 'materials' === $key || 'gallery' === $key ) {
			if ( is_string( $value ) ) {
				$value = explode( ',', $value );
			}
			return array_values( array_filter( array_map( 'absint', (array) $value ) ) );
		}
		return is_string( $value ) ? trim( $value ) : $value;
	}

	/**
	 * Checks which required fields are missing for a product.
	 *
	 * @return array List of messages (array('key' => …, 'label' => …, 'missing' => …)).
	 */
	public static function missing_required( $product_id ) {
		$product = wc_get_product( $product_id );
		if ( $product && $product->get_parent_id() ) {
			$product_id = $product->get_parent_id();
		}
		$required = (array) HWPP_Settings::get( 'required_fields' );
		$fields   = self::product_fields();
		$missing  = array();

		foreach ( $required as $key ) {
			if ( ! isset( $fields[ $key ] ) ) {
				continue;
			}
			$value = self::get_product_value( $product_id, $key );
			if ( ( is_array( $value ) && empty( $value ) ) || ( ! is_array( $value ) && '' === (string) $value ) ) {
				$missing[] = array(
					'key'     => $key,
					'label'   => $fields[ $key ]['label'],
					'missing' => $fields[ $key ]['missing'],
				);
			}
		}

		if ( HWPP_Settings::get( 'require_material_origin' ) ) {
			foreach ( (array) self::get_product_value( $product_id, 'materials' ) as $mid ) {
				if ( 'publish' !== get_post_status( $mid ) ) {
					continue;
				}
				if ( '' === (string) get_post_meta( $mid, self::META_PREFIX . 'origin_country', true ) ) {
					$missing[] = array(
						'key'     => 'material_origin_' . $mid,
						'label'   => __( 'Raw material origin', 'codefokus-handwerk-produktpass' ),
						/* translators: %s: material name */
						'missing' => sprintf( __( 'the country of origin of the material “%s”', 'codefokus-handwerk-produktpass' ), get_the_title( $mid ) ),
						'material_id' => $mid,
					);
				}
			}
		}

		return apply_filters( 'hwpp_missing_required', $missing, $product_id );
	}

	public static function country_name( $code ) {
		if ( ! $code ) {
			return '';
		}
		$countries = function_exists( 'WC' ) && WC()->countries ? WC()->countries->get_countries() : array();
		return isset( $countries[ $code ] ) ? html_entity_decode( $countries[ $code ], ENT_QUOTES, 'UTF-8' ) : $code;
	}
}
