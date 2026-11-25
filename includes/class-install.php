<?php
/**
 * Installation, database table and updates.
 *
 * @package HandwerkProduktpass
 */

defined( 'ABSPATH' ) || exit;

class HWPP_Install {

	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'hwpp_passports';
	}

	public static function activate() {
		if ( version_compare( PHP_VERSION, HWPP_MIN_PHP, '<' ) ) {
			deactivate_plugins( plugin_basename( HWPP_FILE ) );
			wp_die(
				esc_html(
					sprintf(
						/* translators: 1: required PHP version, 2: current PHP version */
						__( 'Handwerk Produktpass requires PHP %1$s or newer. Your server uses PHP %2$s. Please ask your host to update the PHP version (usually one click in the hosting control panel).', 'codefokus-handwerk-produktpass' ),
						HWPP_MIN_PHP,
						PHP_VERSION
					)
				)
			);
		}
		self::create_table();
		self::ensure_secret();
		if ( false === get_option( HWPP_Settings::OPTION ) ) {
			add_option( HWPP_Settings::OPTION, HWPP_Settings::defaults() );
		}
		HWPP_Materials::register_post_type();
		HWPP_Public_View::add_rewrite_rules();
		flush_rewrite_rules();
		update_option( 'hwpp_db_version', HWPP_DB_VERSION );
		set_transient( 'hwpp_activated', 1, 60 );
	}

	public static function deactivate() {
		flush_rewrite_rules();
	}

	public static function maybe_upgrade() {
		if ( get_option( 'hwpp_db_version' ) !== HWPP_DB_VERSION ) {
			self::create_table();
			self::ensure_secret();
			update_option( 'hwpp_db_version', HWPP_DB_VERSION );
			update_option( 'hwpp_flush_rewrite', 1 );
		}
	}

	public static function create_table() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset = $wpdb->get_charset_collate();
		$table   = self::table();
		$sql     = "CREATE TABLE {$table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			uid CHAR(32) NOT NULL,
			serial VARCHAR(80) NOT NULL,
			order_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			item_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			unit_index SMALLINT UNSIGNED NOT NULL DEFAULT 1,
			product_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			is_test TINYINT(1) NOT NULL DEFAULT 0,
			language VARCHAR(12) NOT NULL DEFAULT '',
			payload LONGTEXT NOT NULL,
			prev_hash CHAR(64) NOT NULL DEFAULT '',
			hash CHAR(64) NOT NULL,
			signature CHAR(64) NOT NULL,
			created_at DATETIME NOT NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'active',
			status_note VARCHAR(255) NOT NULL DEFAULT '',
			status_changed_at DATETIME NULL DEFAULT NULL,
			supersedes CHAR(32) NOT NULL DEFAULT '',
			superseded_by CHAR(32) NOT NULL DEFAULT '',
			views INT UNSIGNED NOT NULL DEFAULT 0,
			last_view DATETIME NULL DEFAULT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY uid (uid),
			KEY serial (serial),
			KEY order_item (order_id, item_id),
			KEY is_test (is_test),
			KEY status (status)
		) {$charset};";
		dbDelta( $sql );

	}

	public static function ensure_secret() {
		if ( ! get_option( 'hwpp_secret' ) ) {
			add_option( 'hwpp_secret', bin2hex( random_bytes( 32 ) ), '', 'no' );
		}
	}

	public static function table_exists() {
		global $wpdb;
		$table = self::table();
		return $table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}
}
