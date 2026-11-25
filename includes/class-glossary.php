<?php
/**
 * Context-sensitive help: explanations of technical terms (tooltips).
 *
 * @package HandwerkProduktpass
 */

defined( 'ABSPATH' ) || exit;

class HWPP_Glossary {

	public static function terms() {
		return apply_filters(
			'hwpp_glossary',
			array(
				'dpp'              => __( 'Digital product passport (DPP): a digital record about a product – e.g. materials, origin, care and repair. It is accessed via a QR code on the product, the invoice or the certificate.', 'codefokus-handwerk-produktpass' ),
				'espr'             => __( 'ESPR (Ecodesign Regulation, EU 2024/1781): EU framework that gradually requires a digital product passport for more and more product groups. Exactly which information is mandatory is set by the EU per product group in separate legal acts.', 'codefokus-handwerk-produktpass' ),
				'lieferkette'      => __( 'Supply chain: the path of a raw material from its origin (e.g. forest, tannery, mine) to your workshop. Supply chain laws require large companies to exercise due diligence along this path – customers and business partners increasingly ask small businesses about it too.', 'codefokus-handwerk-produktpass' ),
				'lieferkettengesetz' => __( 'Supply chain law (German LkSG / EU due diligence directive): obliges larger companies to review human rights and environmental risks in their supply chains. Small craft businesses are usually not directly affected, but are often asked by business customers for proof of origin.', 'codefokus-handwerk-produktpass' ),
				'rohstoffherkunft' => __( 'Raw material origin: the country or region a material comes from (e.g. “oak from the Black Forest”). This is a core part of every product passport and is maintained once in the material library.', 'codefokus-handwerk-produktpass' ),
				'herstellungsort'  => __( 'Place of manufacture: where the product was actually made – country and ideally town or workshop. Not to be confused with the registered office if, for example, you work in a different workshop.', 'codefokus-handwerk-produktpass' ),
				'reparierbarkeit'  => __( 'Repairability: can the product be repaired, and how? (e.g. screwed instead of glued, surface can be refinished, repair service). The EU places great emphasis on durable, repairable products.', 'codefokus-handwerk-produktpass' ),
				'ersatzteile'      => __( 'Spare parts & service: which parts can be bought again, for how long, and where does the customer get help? Example: “Replacement fittings available for 10 years, repairs in our workshop”.', 'codefokus-handwerk-produktpass' ),
				'recycling'        => __( 'Disposal & recycling: how the product can be sensibly recycled at the end of its life – e.g. which parts can be separated and where they belong.', 'codefokus-handwerk-produktpass' ),
				'zertifizierung'   => __( 'Certification: independent labels or evidence, e.g. FSC®/PEFC (wood), LWG (leather), Fairmined/RJC (precious metals) or your master craftsman\'s certificate. Only state labels for which you have valid evidence.', 'codefokus-handwerk-produktpass' ),
				'nachhaltigkeit'   => __( 'Sustainability evidence: a short, verifiable statement about the environmental impact of a material – e.g. regional sourcing, recycled content, vegetable tanning. Avoid sweeping claims such as “eco-friendly” without proof.', 'codefokus-handwerk-produktpass' ),
				'nachweis'         => __( 'Evidence document: link to a certificate or delivery note that supports your information. You can upload a file to the media library and paste the link here.', 'codefokus-handwerk-produktpass' ),
				'co2'              => __( 'CO₂ footprint: estimated greenhouse gas emissions from manufacturing in kilograms of CO₂ equivalent. Optional – only state it if you have a traceable calculation.', 'codefokus-handwerk-produktpass' ),
				'qr_pruefsumme'    => __( 'QR checksum (hash): a digital “fingerprint” of the passport data. If even a single letter changes, a completely different fingerprint results. It is shown on the certificate and the passport – so any later change can be detected.', 'codefokus-handwerk-produktpass' ),
				'signatur'         => __( 'Digital signature (HMAC): the fingerprint is additionally sealed with a secret key of your shop. Only your shop can issue valid passports.', 'codefokus-handwerk-produktpass' ),
				'hashkette'        => __( 'Hash chain: every new passport contains the fingerprint of the previous passport. If an older passport is altered or deleted, the chain breaks – the integrity check shows this immediately.', 'codefokus-handwerk-produktpass' ),
				'seriennummer'     => __( 'Serial number: unique identifier of the individual item. Placeholders: {year} = year, {order} = order number, {pos} = position in the order, {unit} = item no. if quantity > 1, {sku} = SKU, {rand} = random code.', 'codefokus-handwerk-produktpass' ),
				'wirtschaftsakteur'=> __( 'Economic operator: the company that places the product on the market – usually your workshop. Name, address and contact details belong in every product passport.', 'codefokus-handwerk-produktpass' ),
				'testmodus'        => __( 'Test mode: creates a sample passport for a product without creating an order, sending an email or charging anything. Test passports are clearly marked as “TEST” and can be deleted at any time.', 'codefokus-handwerk-produktpass' ),
				'pflichtfeld'      => __( 'Required field: without this information no product passport is issued. You are warned when saving the product so there are no surprises later when an order comes in.', 'codefokus-handwerk-produktpass' ),
				'berichtigung'     => __( 'Corrected version: if an issued passport contains an error, first correct the product and then issue a corrected version. It keeps the same serial number, is signed again and refers to the old version. The old QR code automatically leads to the new version.', 'codefokus-handwerk-produktpass' ),
				'passstatus'       => __( 'Status: cancelled, refunded or reported-stolen passports are kept for evidence purposes but display a clear notice. The status is not part of the signed data – changing it does not invalidate the checksum.', 'codefokus-handwerk-produktpass' ),
				'gewerk'           => __( 'Trade: different information matters depending on the craft – fineness and hallmark for jewellery, tanning method for leather goods, fibre composition for textiles. The selection shows matching fields; you can switch at any time.', 'codefokus-handwerk-produktpass' ),
				'geschuetzt'       => __( 'Restricted information: the EU envisages that some passport data is only accessible to certain people (e.g. repair businesses, authorities). Selected fields are only shown after entering the access code printed on the certificate of authenticity.', 'codefokus-handwerk-produktpass' ),
				'zeitstempel'      => __( 'Independent timestamp (RFC 3161): once a day, the current fingerprint of the hash chain is signed with date and time by an external service. This lets you prove to third parties that a passport already existed at that time and has not been changed since – not even you as shop owner could backdate anything unnoticed.', 'codefokus-handwerk-produktpass' ),
				'modellpass'       => __( 'Product passport before purchase: shows the passport information (materials, origin, care, repair) on the product page already, so customers can find out before buying. After purchase, each item additionally receives its own signed passport.', 'codefokus-handwerk-produktpass' ),
				'gs1'              => __( 'GS1 Digital Link: internationally standardised link format (/01/GTIN/21/serial number) that point-of-sale and inventory systems also understand. Only relevant if your products have a GTIN/EAN from GS1.', 'codefokus-handwerk-produktpass' ),
				'datentraeger'     => __( 'Data carrier: the QR code should also be attached to the product itself – as a label, swing tag or permanently as an engraving or brand mark. Under “Actions” of each passport you will find a label (PDF) and an engraving template (SVG for laser, CNC router or branding iron).', 'codefokus-handwerk-produktpass' ),
				'pflegeerinnerung' => __( 'Care reminder: if the customer agreed at checkout, they receive an email with the care instructions for their item after this interval. Leave empty = default interval from the settings.', 'codefokus-handwerk-produktpass' ),
				'geschichte'       => __( 'Story: tell where the material comes from and how the piece was made. This turns the passport into an experience – customers like to show it to others.', 'codefokus-handwerk-produktpass' ),
				'konfliktfrei'     => __( 'Conflict-free origin: declaration that gold, gemstones and other raw materials do not come from conflict areas (cf. EU Conflict Minerals Regulation). Only state this with evidence from your supplier.', 'codefokus-handwerk-produktpass' ),
				'textilkennzeichnung' => __( 'Fibre composition: mandatory for textiles in the EU anyway (Textile Labelling Regulation 1007/2011) – e.g. “100 % linen” or “70 % wool, 30 % silk”.', 'codefokus-handwerk-produktpass' ),
				'archiv'           => __( 'Archive export: product passports should remain accessible as long as the product is in use – often for decades. The archive contains all passports as simple web pages. Even if you close the shop one day, upload the folder to inexpensive web space and all QR codes keep working.', 'codefokus-handwerk-produktpass' ),
				'unveraenderlich'  => __( 'Immutable: an issued passport is never overwritten. If you change product data later, this only applies to future passports – items already sold keep their original data (important for warranty and evidence).', 'codefokus-handwerk-produktpass' ),
			)
		);
	}

	/**
	 * Outputs a question-mark symbol with an explanation (accessible: focusable by keyboard).
	 */
	public static function tip( $term ) {
		$terms = self::terms();
		if ( empty( $terms[ $term ] ) ) {
			return '';
		}
		return sprintf(
			'<span class="hwpp-tip" tabindex="0" role="note" aria-label="%1$s" data-tip="%1$s">?</span>',
			esc_attr( $terms[ $term ] )
		);
	}

	public static function e( $term ) {
		echo wp_kses( self::tip( $term ), HWPP_Admin::kses() );
	}
}
