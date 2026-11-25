<?php
/**
 * Trade templates: suitable extra fields and recommended required fields per trade.
 *
 * @package HandwerkProduktpass
 */

defined( 'ABSPATH' ) || exit;

class HWPP_Presets {

	public static function trades() {
		$yes_no = array(
			'yes' => __( 'Yes', 'codefokus-handwerk-produktpass' ),
			'no'  => __( 'No', 'codefokus-handwerk-produktpass' ),
		);
		return apply_filters(
			'hwpp_trades',
			array(
				'generic'   => array(
					'label'    => __( 'General craft', 'codefokus-handwerk-produktpass' ),
					'fields'   => array(),
					'required' => array(),
				),
				'furniture' => array(
					'label'    => __( 'Furniture making / joinery / carpentry', 'codefokus-handwerk-produktpass' ),
					'fields'   => array(
						'surface_treatment' => array( 'label' => __( 'Surface treatment', 'codefokus-handwerk-produktpass' ), 'type' => 'text', 'section' => 'materials', 'placeholder' => __( 'e.g. hard wax oil, solvent-free', 'codefokus-handwerk-produktpass' ), 'missing' => __( 'the surface treatment', 'codefokus-handwerk-produktpass' ) ),
						'emission_class'    => array( 'label' => __( 'Emissions / pollutant testing', 'codefokus-handwerk-produktpass' ), 'type' => 'text', 'section' => 'sustainability', 'placeholder' => __( 'e.g. board material E1, oil according to DIN EN 71-3', 'codefokus-handwerk-produktpass' ), 'missing' => __( 'the emissions information', 'codefokus-handwerk-produktpass' ) ),
						'load_capacity'     => array( 'label' => __( 'Load capacity', 'codefokus-handwerk-produktpass' ), 'type' => 'text', 'section' => 'product', 'placeholder' => __( 'e.g. shelf max. 25 kg', 'codefokus-handwerk-produktpass' ), 'missing' => __( 'the load capacity', 'codefokus-handwerk-produktpass' ) ),
						'assembly'          => array( 'label' => __( 'Assembly / disassembly', 'codefokus-handwerk-produktpass' ), 'type' => 'textarea', 'section' => 'repair', 'placeholder' => __( 'e.g. frame can be dismantled with an Allen key, instructions included.', 'codefokus-handwerk-produktpass' ), 'missing' => __( 'the assembly information', 'codefokus-handwerk-produktpass' ) ),
					),
					'required' => array( 'surface_treatment' ),
				),
				'jewelry'   => array(
					'label'    => __( 'Goldsmith / jewellery studio', 'codefokus-handwerk-produktpass' ),
					'fields'   => array(
						'fineness'      => array( 'label' => __( 'Fineness', 'codefokus-handwerk-produktpass' ), 'type' => 'text', 'section' => 'materials', 'placeholder' => __( 'e.g. 750/000 yellow gold, 925/000 silver', 'codefokus-handwerk-produktpass' ), 'missing' => __( 'the fineness', 'codefokus-handwerk-produktpass' ) ),
						'hallmark'      => array( 'label' => __( 'Hallmark', 'codefokus-handwerk-produktpass' ), 'type' => 'text', 'section' => 'product', 'placeholder' => __( 'e.g. 750 + maker\'s mark “AM”', 'codefokus-handwerk-produktpass' ), 'missing' => __( 'the hallmark', 'codefokus-handwerk-produktpass' ) ),
						'gemstones'     => array( 'label' => __( 'Gemstones & origin', 'codefokus-handwerk-produktpass' ), 'type' => 'textarea', 'section' => 'materials', 'placeholder' => __( 'e.g. 1 sapphire, 0.42 ct, untreated, Sri Lanka', 'codefokus-handwerk-produktpass' ), 'missing' => __( 'the gemstone information', 'codefokus-handwerk-produktpass' ) ),
						'conflict_free' => array( 'label' => __( 'Conflict-free origin declared', 'codefokus-handwerk-produktpass' ), 'type' => 'select', 'options' => $yes_no, 'section' => 'sustainability', 'tip' => 'konfliktfrei', 'missing' => __( 'the declaration of conflict-free origin', 'codefokus-handwerk-produktpass' ) ),
					),
					'required' => array( 'fineness' ),
				),
				'leather'   => array(
					'label'    => __( 'Saddlery / leather goods', 'codefokus-handwerk-produktpass' ),
					'fields'   => array(
						'tanning'       => array(
							'label'   => __( 'Tanning method', 'codefokus-handwerk-produktpass' ),
							'type'    => 'select',
							'section' => 'materials',
							'options' => array(
								'vegetable'   => __( 'Vegetable tanned', 'codefokus-handwerk-produktpass' ),
								'chrome_free' => __( 'Chrome-free tanned', 'codefokus-handwerk-produktpass' ),
								'chrome'      => __( 'Chrome tanned', 'codefokus-handwerk-produktpass' ),
								'other'       => __( 'Other method', 'codefokus-handwerk-produktpass' ),
							),
							'missing' => __( 'the tanning method', 'codefokus-handwerk-produktpass' ),
						),
						'animal_origin' => array( 'label' => __( 'Animal species & origin of hides', 'codefokus-handwerk-produktpass' ), 'type' => 'text', 'section' => 'materials', 'placeholder' => __( 'e.g. cattle, by-product of food production, southern Germany', 'codefokus-handwerk-produktpass' ), 'missing' => __( 'the origin of the hides', 'codefokus-handwerk-produktpass' ) ),
						'hardware'      => array( 'label' => __( 'Hardware & thread', 'codefokus-handwerk-produktpass' ), 'type' => 'text', 'section' => 'materials', 'placeholder' => __( 'e.g. solid brass, waxed linen thread', 'codefokus-handwerk-produktpass' ), 'missing' => __( 'the hardware information', 'codefokus-handwerk-produktpass' ) ),
					),
					'required' => array( 'tanning' ),
				),
				'textile'   => array(
					'label'    => __( 'Textile / weaving / tailoring', 'codefokus-handwerk-produktpass' ),
					'fields'   => array(
						'fiber_composition' => array( 'label' => __( 'Fibre composition', 'codefokus-handwerk-produktpass' ), 'type' => 'text', 'section' => 'materials', 'tip' => 'textilkennzeichnung', 'placeholder' => __( 'e.g. 100 % linen', 'codefokus-handwerk-produktpass' ), 'missing' => __( 'the fibre composition', 'codefokus-handwerk-produktpass' ) ),
						'care_symbols'      => array( 'label' => __( 'Care symbols', 'codefokus-handwerk-produktpass' ), 'type' => 'text', 'section' => 'care', 'placeholder' => __( 'e.g. 40 °C delicate wash, do not tumble dry', 'codefokus-handwerk-produktpass' ), 'missing' => __( 'the care symbols', 'codefokus-handwerk-produktpass' ) ),
						'dyeing'            => array( 'label' => __( 'Dyeing', 'codefokus-handwerk-produktpass' ), 'type' => 'text', 'section' => 'sustainability', 'placeholder' => __( 'e.g. plant dyes, undyed', 'codefokus-handwerk-produktpass' ), 'missing' => __( 'the dyeing information', 'codefokus-handwerk-produktpass' ) ),
					),
					'required' => array( 'fiber_composition' ),
				),
				'ceramics'  => array(
					'label'    => __( 'Ceramics / pottery / glass', 'codefokus-handwerk-produktpass' ),
					'fields'   => array(
						'food_safe'  => array( 'label' => __( 'Food safe', 'codefokus-handwerk-produktpass' ), 'type' => 'select', 'options' => $yes_no, 'section' => 'product', 'missing' => __( 'the food safety information', 'codefokus-handwerk-produktpass' ) ),
						'dishwasher' => array( 'label' => __( 'Dishwasher safe', 'codefokus-handwerk-produktpass' ), 'type' => 'select', 'options' => $yes_no, 'section' => 'care', 'missing' => __( 'the dishwasher information', 'codefokus-handwerk-produktpass' ) ),
						'firing'     => array( 'label' => __( 'Firing & glaze', 'codefokus-handwerk-produktpass' ), 'type' => 'text', 'section' => 'materials', 'placeholder' => __( 'e.g. stoneware, 1,240 °C, lead-free glaze', 'codefokus-handwerk-produktpass' ), 'missing' => __( 'the firing and glaze information', 'codefokus-handwerk-produktpass' ) ),
					),
					'required' => array( 'food_safe' ),
				),
				'metal'     => array(
					'label'    => __( 'Blacksmithing / metalwork', 'codefokus-handwerk-produktpass' ),
					'fields'   => array(
						'alloy'     => array( 'label' => __( 'Material / alloy', 'codefokus-handwerk-produktpass' ), 'type' => 'text', 'section' => 'materials', 'placeholder' => __( 'e.g. Damascus steel from 1.2842 and 15N20', 'codefokus-handwerk-produktpass' ), 'missing' => __( 'the material', 'codefokus-handwerk-produktpass' ) ),
						'corrosion' => array( 'label' => __( 'Corrosion protection', 'codefokus-handwerk-produktpass' ), 'type' => 'text', 'section' => 'care', 'placeholder' => __( 'e.g. Dry after use and oil lightly', 'codefokus-handwerk-produktpass' ), 'missing' => __( 'the corrosion protection information', 'codefokus-handwerk-produktpass' ) ),
					),
					'required' => array( 'alloy' ),
				),
			)
		);
	}

	public static function current() {
		$trade  = (string) HWPP_Settings::get( 'trade', 'generic' );
		$trades = self::trades();
		return isset( $trades[ $trade ] ) ? $trade : 'generic';
	}

	/**
	 * Extra fields of the selected trade.
	 */
	public static function extra_fields() {
		$trades = self::trades();
		return $trades[ self::current() ]['fields'];
	}

	public static function choices() {
		return wp_list_pluck( self::trades(), 'label' );
	}

	/**
	 * When the trade changes: add recommended required fields (existing ones are kept).
	 */
	public static function apply( $trade ) {
		$trades = self::trades();
		if ( ! isset( $trades[ $trade ] ) ) {
			return;
		}
		$required = array_values( array_unique( array_merge( (array) HWPP_Settings::get( 'required_fields' ), $trades[ $trade ]['required'] ) ) );
		HWPP_Settings::update( array( 'trade' => $trade, 'required_fields' => $required ) );
	}
}
