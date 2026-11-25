<?php
/**
 * Uninstall. Data is deleted only if this has been explicitly enabled in the settings,
 * because issued product passports serve as proof (warranty, printed QR codes).
 *
 * @package HandwerkProduktpass
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$hwpp_settings = get_option( 'hwpp_settings', array() );
if ( empty( $hwpp_settings['delete_on_uninstall'] ) ) {
	return;
}

global $wpdb;
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}hwpp_passports" ); // phpcs:ignore WordPress.DB

$hwpp_materials = get_posts( array( 'post_type' => 'hwpp_material', 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids' ) );
foreach ( $hwpp_materials as $hwpp_id ) {
	wp_delete_post( $hwpp_id, true );
}
$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE %s", $wpdb->esc_like( '_hwpp_' ) . '%' ) ); // phpcs:ignore WordPress.DB

foreach ( array( 'hwpp_settings', 'hwpp_secret', 'hwpp_db_version', 'hwpp_open_errors', 'hwpp_last_integrity_check', 'hwpp_flush_rewrite', 'hwpp_product_ready', 'hwpp_trade_chosen' ) as $hwpp_opt ) {
	delete_option( $hwpp_opt );
}
delete_metadata( 'user', 0, 'hwpp_upsell_dismissed', '', true );
