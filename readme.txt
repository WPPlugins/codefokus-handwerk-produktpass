=== codefokus Handwerk Produktpass for WooCommerce ===
Contributors: codefokus
Tags: digital product passport, qr code, woocommerce, espr, handmade
Requires at least: 6.2
Tested up to: 7.1
Requires PHP: 7.4
Requires Plugins: woocommerce
Stable tag: 2.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Tamper-evident digital product passports with QR code for handmade products – built for workshops, furniture makers and jewellers.

== Description ==

When an order is paid, Handwerk Produktpass automatically issues one immutable digital product passport per item and links it to a serial number. Your customer scans the QR code and sees where the material comes from, who made the piece, which certificates apply and how to care for it – together with a check that the passport is genuine and unchanged.

= Passport =
* Public, mobile-friendly passport page per item: materials, raw material origin, manufacturing, care, repair, sustainability, manufacturer details and authenticity check.
* QR code for every passport – download as PNG or SVG for printing, engraving or labels.
* Links to the passports in order emails and in the customer account.
* Clean, mobile-friendly standard design in the language of your site.

= Trust =
* SHA-256 checksum, HMAC signature and a hash chain across all passports; one-click integrity check.
* Corrected versions keep the serial number and are linked to the original; old QR codes lead to the current version.
* Status tracking: cancelled, refunded and reported-stolen passports show a clear public notice. Cancellations and refunds are applied automatically.
* Restricted information (e.g. suppliers, repair details) can be unlocked with a per-passport access code.
* No personal customer data in passports. QR codes are generated locally – the plugin contacts no external services.

= Built for craft businesses =
* Material library: record woods, leathers or metals once with origin, supplier, certificates and evidence.
* Trade presets (furniture, jewellery, leather, textile, ceramics, metalwork) add the fields that matter for your craft.
* Required fields per product, so no passport is issued with missing core information.
* Setup checklist, test mode, diagnostics page (also in Tools → Site Health) and tooltips for every technical term.
* Translations are provided via translate.wordpress.org.
* Compatible with HPOS and the cart/checkout blocks.

= codefokus Handwerk Produktpass Pro =
An optional paid add-on adds a template designer (logo, colours, font, section order), multilingual passports with WPML and Polylang, a certificate of authenticity (PDF) with signature and workshop stamp, engraving templates and labels, the QR code on invoices and packing slips, care reminders by email, a product passport tab before purchase, scan statistics, CSV import/export and bulk editing, RFC 3161 timestamps, an archive export, a REST API with JSON-LD and GS1 Digital Link. The free plugin is fully functional without it. More information: [codefokus.de](https://codefokus.de/shop?p=handwerk-produktpass-digitaler-produktpass-fuer-woocommerce-und-handwerksbetriebe)

== Installation ==

1. Install and activate WooCommerce.
2. Install codefokus Handwerk Produktpass under Plugins → Add New and activate it.
3. Open the “Product passport” menu and follow the setup checklist.
4. Recommended: copy the signing key into wp-config.php (ready-made line under Settings → Advanced).

== Frequently Asked Questions ==

= Does this make my shop compliant with the EU Ecodesign Regulation (ESPR)? =
The plugin helps you record and publish the core content of a digital product passport. Which information is mandatory for a product group, and the technical format, is defined step by step by the EU in delegated acts – for many product groups these are not yet published. The plugin does not replace legal advice and does not guarantee compliance.

= What happens to issued passports if I change a product? =
Nothing – issued passports are immutable. Changes apply to future passports. To fix an error in an issued passport, issue a corrected version.

= Can I test without real orders? =
Yes. Test mode runs the complete process without creating an order or sending emails.

= How do I get the QR code onto my product? =
Download the QR code (PNG or SVG) from the order or the overview and print, engrave or burn it. The Pro add-on also creates ready-made engraving templates in millimetres, labels and A4 label sheets, and prints the QR code on invoices.

= Does the plugin send data to external servers? =
No. Passports, QR codes and checks are created on your own server. The diagnostics page calls one of your own passport addresses to check that it is reachable.

= What happens to the passports if I delete the plugin? =
They stay in the database unless you enable “Remove data on deletion” under Settings → Advanced. Issued passports serve as proof – printed QR codes would otherwise lead nowhere.

== Developers ==

Filters: hwpp_product_fields, hwpp_material_fields, hwpp_material_types, hwpp_trades, hwpp_payload, hwpp_payload_texts, hwpp_serial, hwpp_applies_to_product, hwpp_missing_required, hwpp_section_rows, hwpp_passport_template, hwpp_public_url, hwpp_certificate_url, hwpp_settings_defaults, hwpp_settings_tabs, hwpp_save_settings, hwpp_setup_steps, hwpp_languages, hwpp_default_language, hwpp_order_language, hwpp_translated_id, hwpp_translate_string, hwpp_passport_admin_links, hwpp_health_checks, hwpp_glossary, hwpp_privacy_export_order, hwpp_privacy_erase_order.
Actions: hwpp_loaded, hwpp_passport_created, hwpp_status_changed, hwpp_generate_for_order, hwpp_rewrite_rules, hwpp_serve_passport, hwpp_passport_head, hwpp_passport_after_content, hwpp_settings_rows, hwpp_test_result, hwpp_order_box_after_list.
Shortcode: [hwpp_verify]
Template override: your-theme/hwpp/passport.php

== Screenshots ==

1. Public passport page: authenticity check, serial number and product data.
2. Materials and raw material origin from the material library, plus manufacturing details.
3. The passport on a smartphone – opened by scanning the QR code on the product.
4. Product tab "Product passport": story, materials and manufacturing data per product – with live required-field check.
5. Material library: enter origin, supplier, certificates and recycled content once, reuse them in every product.
6. Settings: company details that appear as "Manufacturer" in every passport.
7. Rules & required fields: decide which information is mandatory and at which order status the passport is issued.
8. Test mode: simulate a purchase and get a real passport with checksum, signature and QR code – without creating an order.
9. Diagnostics: built-in health check with concrete tips on how to fix anything that is missing.

== Changelog ==

= 2.0.0 =
* New: free version for WordPress.org under the name “codefokus Handwerk Produktpass for WooCommerce”. Certificate PDF, engraving and labels, invoice QR code, care reminders, product passport before purchase, statistics, CSV tools, timestamps, archive and REST API moved to the separate Pro add-on.
* New: QR code download as PNG and SVG for every passport.
* Changed: template designer and WPML/Polylang integration moved to the Pro add-on.
* Changed: translations come from translate.wordpress.org; no language files are bundled.
* New: extension hooks for add-ons (see “Developers”).
* Changed: the plugin starts on “init” so translations load at the right time (WordPress 6.7+).
* Changed: no licence key or licence server in the free version.

= 1.2.2 =
* Fixed: signature and stamp are placed below the product details on the certificate.

= 1.2.0 =
* New: signature and workshop stamp as image for certificate and passport.

= 1.1.0 =
* New: German translations (de_DE, de_DE_formal, de_AT, de_CH), privacy tools, licence server connection.

== Upgrade Notice ==

= 2.0.0 =
Pro features (designer, multilingual passports, certificate PDF, engraving, labels, invoice QR code, reminders and more) are now in the separate Pro add-on. If you used them, install Handwerk Produktpass Pro after updating. Issued passports remain unchanged.
