<?php
/**
 * Diagnostics tool („health check“) with clear instructions for fixing problems.
 * Also shown under „Tools → Site Health“ in WordPress.
 *
 * @package HandwerkProduktpass
 */

defined( 'ABSPATH' ) || exit;

class HWPP_Health {

	const GOOD     = 'good';
	const WARN     = 'recommended';
	const CRITICAL = 'critical';

	public static function init() {
		add_filter( 'site_status_tests', array( __CLASS__, 'site_health' ) );
	}

	public static function check( $status, $label, $message, $fix = '', $link = '' ) {
		return compact( 'status', 'label', 'message', 'fix', 'link' );
	}

	/**
	 * Runs all checks.
	 *
	 * @param bool $with_loopback Also call the public pass address live.
	 */
	public static function run( $with_loopback = true ) {
		$c = array();
		$s = HWPP_Settings::all();

		// --- Server ---------------------------------------------------------
		$c['php'] = version_compare( PHP_VERSION, '8.1', '>=' )
			? self::check( self::GOOD, __( 'PHP version', 'codefokus-handwerk-produktpass' ), sprintf( 'PHP %s', PHP_VERSION ) )
			: self::check( self::WARN, __( 'PHP version', 'codefokus-handwerk-produktpass' ), sprintf( /* translators: %s: version */ __( 'PHP %s works but is outdated.', 'codefokus-handwerk-produktpass' ), PHP_VERSION ), __( 'Switch to PHP 8.1 or newer in your hosting control panel – it is faster and more secure.', 'codefokus-handwerk-produktpass' ) );

		$wc = defined( 'WC_VERSION' ) ? WC_VERSION : '0';
		$c['wc'] = version_compare( $wc, '7.0', '>=' )
			? self::check( self::GOOD, 'WooCommerce', 'WooCommerce ' . $wc )
			: self::check( self::CRITICAL, 'WooCommerce', sprintf( /* translators: %s: version */ __( 'WooCommerce %s is too old.', 'codefokus-handwerk-produktpass' ), $wc ), __( 'Update WooCommerce under “Dashboard → Updates” (create a backup first).', 'codefokus-handwerk-produktpass' ), admin_url( 'update-core.php' ) );

		$c['db'] = HWPP_Install::table_exists()
			? self::check( self::GOOD, __( 'Database table', 'codefokus-handwerk-produktpass' ), __( 'Present.', 'codefokus-handwerk-produktpass' ) )
			: self::check( self::CRITICAL, __( 'Database table', 'codefokus-handwerk-produktpass' ), __( 'The table for product passports is missing – no passports can be saved.', 'codefokus-handwerk-produktpass' ), __( 'Deactivate the plugin under “Plugins” and activate it again right away. This recreates the table. If that does not help, the database user lacks the “CREATE TABLE” permission – please ask your host.', 'codefokus-handwerk-produktpass' ), admin_url( 'plugins.php' ) );

		$c['gd'] = function_exists( 'imagecreatetruecolor' )
			? self::check( self::GOOD, __( 'Image processing (GD)', 'codefokus-handwerk-produktpass' ), __( 'Available – QR codes on invoices and logos on the certificate work.', 'codefokus-handwerk-produktpass' ) )
			: self::check( self::WARN, __( 'Image processing (GD)', 'codefokus-handwerk-produktpass' ), __( 'Not available. QR codes are embedded as SVG instead (not displayed by some invoice plugins), PNG logos will not appear on the certificate.', 'codefokus-handwerk-produktpass' ), __( 'Ask your host to enable the PHP extension “GD” (standard with almost all hosts). Alternatively, upload your logo as JPG.', 'codefokus-handwerk-produktpass' ) );

		$c['mb'] = ( function_exists( 'mb_convert_encoding' ) || function_exists( 'iconv' ) )
			? self::check( self::GOOD, __( 'Special characters in PDF', 'codefokus-handwerk-produktpass' ), __( 'Character set conversion available (ä, ö, ü, ß, €).', 'codefokus-handwerk-produktpass' ) )
			: self::check( self::WARN, __( 'Special characters in PDF', 'codefokus-handwerk-produktpass' ), __( 'Neither “mbstring” nor “iconv” is available – special characters will appear as “?” on the certificate.', 'codefokus-handwerk-produktpass' ), __( 'Ask your host to enable the PHP extension “mbstring”.', 'codefokus-handwerk-produktpass' ) );

		$mem = wp_convert_hr_to_bytes( (string) ini_get( 'memory_limit' ) );
		$c['mem'] = ( $mem <= 0 || $mem >= 64 * MB_IN_BYTES )
			? self::check( self::GOOD, __( 'Memory', 'codefokus-handwerk-produktpass' ), (string) ini_get( 'memory_limit' ) )
			: self::check( self::WARN, __( 'Memory', 'codefokus-handwerk-produktpass' ), sprintf( /* translators: %s: memory */ __( 'Only %s – PDF creation may fail with large logos.', 'codefokus-handwerk-produktpass' ), ini_get( 'memory_limit' ) ), __( 'Add this to wp-config.php: define( \'WP_MEMORY_LIMIT\', \'256M\' ); or ask your host.', 'codefokus-handwerk-produktpass' ) );

		// --- Funktions-Selbsttests ------------------------------------------
		try {
			$m       = HWPP_QR::matrix( home_url( '/produktpass/0123456789abcdef0123456789abcdef/' ) );
			$c['qr'] = count( $m ) >= 21
				? self::check( self::GOOD, __( 'QR code generation', 'codefokus-handwerk-produktpass' ), __( 'Self-test successful (local, no external services).', 'codefokus-handwerk-produktpass' ) )
				: self::check( self::CRITICAL, __( 'QR code generation', 'codefokus-handwerk-produktpass' ), __( 'Self-test failed.', 'codefokus-handwerk-produktpass' ) );
		} catch ( Throwable $e ) {
			$c['qr'] = self::check( self::CRITICAL, __( 'QR code generation', 'codefokus-handwerk-produktpass' ), $e->getMessage(), __( 'Please send this diagnostics page (Ctrl+P → save as PDF) to plugin support.', 'codefokus-handwerk-produktpass' ) );
		}

		// --- Erreichbarkeit -------------------------------------------------
		if ( ! get_option( 'permalink_structure' ) ) {
			$c['permalinks'] = self::check( self::WARN, __( 'Passport addresses', 'codefokus-handwerk-produktpass' ), __( 'Plain permalinks active – passports work, but have unattractive addresses (…/?hwpp_pass=…).', 'codefokus-handwerk-produktpass' ), __( 'Under “Settings → Permalinks”, choose e.g. “Post name”. Important: ideally BEFORE the first sale, as printed QR codes contain the address (old addresses will keep working, though).', 'codefokus-handwerk-produktpass' ), admin_url( 'options-permalink.php' ) );
		} else {
			$rules = (array) get_option( 'rewrite_rules', array() );
			$found = false;
			foreach ( array_keys( $rules ) as $pattern ) {
				if ( false !== strpos( (string) $pattern, preg_quote( sanitize_title( $s['public_slug'] ), '/' ) ) ) {
					$found = true;
					break;
				}
			}
			$c['permalinks'] = $found
				? self::check( self::GOOD, __( 'Passport addresses', 'codefokus-handwerk-produktpass' ), home_url( '/' . $s['public_slug'] . '/…/' ) )
				: self::check( self::CRITICAL, __( 'Passport addresses', 'codefokus-handwerk-produktpass' ), __( 'The address rule for passport pages is not registered – QR codes would lead to an error page.', 'codefokus-handwerk-produktpass' ), __( 'Open “Settings → Permalinks” and click “Save Changes” without changing anything. This renews the address rules.', 'codefokus-handwerk-produktpass' ), admin_url( 'options-permalink.php' ) );
		}

		if ( $with_loopback ) {
			$c['loopback'] = self::loopback();
		}

		// --- Signing key / integrity ---------------------------------
		if ( ! HWPP_Integrity::secret() ) {
			$c['secret'] = self::check( self::CRITICAL, __( 'Signing key', 'codefokus-handwerk-produktpass' ), __( 'No key present – passports cannot be signed.', 'codefokus-handwerk-produktpass' ), __( 'Deactivate and reactivate the plugin once – this generates a key.', 'codefokus-handwerk-produktpass' ), admin_url( 'plugins.php' ) );
		} elseif ( 'config' === HWPP_Integrity::secret_source() ) {
			$c['secret'] = self::check( self::GOOD, __( 'Signing key', 'codefokus-handwerk-produktpass' ), __( 'Stored securely in wp-config.php.', 'codefokus-handwerk-produktpass' ) );
		} else {
			$c['secret'] = self::check( self::WARN, __( 'Signing key', 'codefokus-handwerk-produktpass' ), __( 'Present, but stored in the database.', 'codefokus-handwerk-produktpass' ), __( 'For maximum tamper protection, move the key to wp-config.php (ready-made line under “Settings → Advanced”).', 'codefokus-handwerk-produktpass' ), admin_url( 'admin.php?page=hwpp-settings&tab=advanced' ) );
		}

		$last = get_option( 'hwpp_last_integrity_check' );
		if ( HWPP_Repository::count( false ) > 0 ) {
			if ( ! $last ) {
				$c['integrity'] = self::check( self::WARN, __( 'Integrity check', 'codefokus-handwerk-produktpass' ), __( 'Never run.', 'codefokus-handwerk-produktpass' ), __( 'Click “Check integrity of all passports” in the overview.', 'codefokus-handwerk-produktpass' ), admin_url( 'admin.php?page=hwpp' ) );
			} elseif ( $last['invalid'] || $last['broken'] ) {
				$c['integrity'] = self::check( self::CRITICAL, __( 'Integrity check', 'codefokus-handwerk-produktpass' ), __( 'The last check found altered or missing passports.', 'codefokus-handwerk-produktpass' ), __( 'Details in the overview. Most common cause: the signing key was changed or a database backup was partially restored.', 'codefokus-handwerk-produktpass' ), admin_url( 'admin.php?page=hwpp' ) );
			} else {
				/* translators: %d: count */
				$c['integrity'] = self::check( self::GOOD, __( 'Integrity check', 'codefokus-handwerk-produktpass' ), sprintf( __( '%d passports checked, all unchanged.', 'codefokus-handwerk-produktpass' ), $last['checked'] ) );
			}
		}

		// --- Konfiguration -------------------------------------------------
		$missing_company = array();
		foreach ( array( 'company_name' => __( 'Company name', 'codefokus-handwerk-produktpass' ), 'company_street' => __( 'Street', 'codefokus-handwerk-produktpass' ), 'company_city' => __( 'City', 'codefokus-handwerk-produktpass' ), 'company_email' => __( 'Email', 'codefokus-handwerk-produktpass' ) ) as $k => $l ) {
			if ( empty( $s[ $k ] ) ) {
				$missing_company[] = $l;
			}
		}
		$c['company'] = $missing_company
			/* translators: %s: list */
			? self::check( self::CRITICAL, __( 'Company details', 'codefokus-handwerk-produktpass' ), sprintf( __( 'Missing: %s.', 'codefokus-handwerk-produktpass' ), implode( ', ', $missing_company ) ), __( 'The manufacturer must be named in every product passport. Please complete the details.', 'codefokus-handwerk-produktpass' ), admin_url( 'admin.php?page=hwpp-settings&tab=company' ) )
			: self::check( self::GOOD, __( 'Company details', 'codefokus-handwerk-produktpass' ), __( 'Complete.', 'codefokus-handwerk-produktpass' ) );

		$mat = (int) wp_count_posts( HWPP_Materials::POST_TYPE )->publish;
		$c['materials'] = $mat
			/* translators: %d: count */
			? self::check( self::GOOD, __( 'Material library', 'codefokus-handwerk-produktpass' ), sprintf( _n( '%d material created.', '%d materials created.', $mat, 'codefokus-handwerk-produktpass' ), $mat ) )
			: self::check( self::WARN, __( 'Material library', 'codefokus-handwerk-produktpass' ), __( 'Still empty.', 'codefokus-handwerk-produktpass' ), __( 'Create your frequently used materials once – this saves a lot of time later.', 'codefokus-handwerk-produktpass' ), admin_url( 'post-new.php?post_type=' . HWPP_Materials::POST_TYPE ) );

		$incomplete = 0;
		$checked    = 0;
		foreach ( get_posts( array( 'post_type' => 'product', 'post_status' => 'publish', 'posts_per_page' => 200, 'fields' => 'ids' ) ) as $pid ) {
			if ( ! HWPP_Product_Data::applies_to( $pid ) ) {
				continue;
			}
			$checked++;
			if ( HWPP_Fields::missing_required( $pid ) ) {
				$incomplete++;
			}
		}
		$c['products'] = $incomplete
			/* translators: 1: incomplete, 2: total */
			? self::check( self::WARN, __( 'Products', 'codefokus-handwerk-produktpass' ), sprintf( __( '%1$d of %2$d published products are incomplete – no passport would be created when sold.', 'codefokus-handwerk-produktpass' ), $incomplete, $checked ), __( 'In the product list, the “Passport” column marks affected products with ⚠. Hover over it to see the missing details.', 'codefokus-handwerk-produktpass' ), admin_url( 'edit.php?post_type=product' ) )
			/* translators: %d: count */
			: self::check( self::GOOD, __( 'Products', 'codefokus-handwerk-produktpass' ), sprintf( __( 'All %d checked products are complete.', 'codefokus-handwerk-produktpass' ), $checked ) );

		$open = count( HWPP_Errors::open() );
		if ( $open ) {
			/* translators: %d: count */
			$c['errors'] = self::check( self::CRITICAL, __( 'Open orders', 'codefokus-handwerk-produktpass' ), sprintf( _n( 'A passport is still missing for %d order.', 'Passports are still missing for %d orders.', $open, 'codefokus-handwerk-produktpass' ), $open ), __( 'The notice at the top of every admin page names the product and the missing detail. After completing it, click “Generate passports now” in the order.', 'codefokus-handwerk-produktpass' ) );
		}

		if ( empty( $s['trigger_statuses'] ) ) {
			$c['status'] = self::check( self::CRITICAL, __( 'Trigger', 'codefokus-handwerk-produktpass' ), __( 'No order status selected as trigger – passports will never be created.', 'codefokus-handwerk-produktpass' ), '', admin_url( 'admin.php?page=hwpp-settings&tab=rules' ) );
		}

		return apply_filters( 'hwpp_health_checks', $c );
	}

	/**
	 * Calls a pass address over HTTP and checks whether this plugin responds (detects caching or server problems).
	 */
	private static function loopback() {
		$cached = get_transient( 'hwpp_loopback' );
		if ( is_array( $cached ) ) {
			return $cached;
		}
		$fake = (object) array( 'uid' => str_repeat( '0', 32 ) );
		$url  = HWPP_Repository::public_url( $fake );
		$ssl  = apply_filters( 'https_local_ssl_verify', false ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WordPress core filter for loopback requests.
		$res  = wp_remote_get( $url, array( 'timeout' => 8, 'sslverify' => $ssl, 'redirection' => 2 ) );
		$label = __( 'Live test of the passport page', 'codefokus-handwerk-produktpass' );
		if ( is_wp_error( $res ) ) {
			$check = self::check( self::WARN, $label, sprintf( /* translators: %s: error */ __( 'Your own website could not be reached (%s). This is often due to the server configuration and does not necessarily mean a problem for your customers.', 'codefokus-handwerk-produktpass' ), $res->get_error_message() ), __( 'Open a test passport on your phone in test mode. If it loads correctly, everything is fine.', 'codefokus-handwerk-produktpass' ), admin_url( 'admin.php?page=hwpp-test' ) );
		} elseif ( false !== strpos( (string) wp_remote_retrieve_body( $res ), 'hwpp-status' ) ) {
			$check = self::check( self::GOOD, $label, __( 'Passport pages are publicly accessible.', 'codefokus-handwerk-produktpass' ) );
		} else {
			$check = self::check( self::CRITICAL, $label, sprintf( /* translators: %d: HTTP code */ __( 'The passport address does not deliver the passport page (HTTP %d). QR codes would lead nowhere.', 'codefokus-handwerk-produktpass' ), (int) wp_remote_retrieve_response_code( $res ) ), __( '1) Open “Settings → Permalinks” and save. 2) Clear the cache of your caching plugin and exclude the address /produktpass/ from caching. 3) Check maintenance mode/security plugins.', 'codefokus-handwerk-produktpass' ), admin_url( 'options-permalink.php' ) );
		}
		set_transient( 'hwpp_loopback', $check, 5 * MINUTE_IN_SECONDS );
		return $check;
	}

	public static function page() {
		if ( isset( $_GET['refresh'] ) && check_admin_referer( 'hwpp_health_refresh' ) ) {
			delete_transient( 'hwpp_loopback' );
		}
		$checks = self::run( true );
		$counts = array_count_values( wp_list_pluck( $checks, 'status' ) );
		$icons  = array( self::GOOD => '✓', self::WARN => '!', self::CRITICAL => '✗' );
		?>
		<div class="wrap hwpp-wrap">
			<h1><?php esc_html_e( 'Diagnostics (health check)', 'codefokus-handwerk-produktpass' ); ?></h1>
			<div class="hwpp-health-summary">
				<?php if ( empty( $counts[ self::CRITICAL ] ) && empty( $counts[ self::WARN ] ) ) : ?>
					<p class="hwpp-health-big is-good">✓ <?php esc_html_e( 'All good – the product passport is fully operational.', 'codefokus-handwerk-produktpass' ); ?></p>
				<?php elseif ( empty( $counts[ self::CRITICAL ] ) ) : ?>
					<p class="hwpp-health-big is-warn"><?php echo esc_html( sprintf( /* translators: %d: count */ __( 'Operational – %d recommendation(s) for improvement.', 'codefokus-handwerk-produktpass' ), $counts[ self::WARN ] ) ); ?></p>
				<?php else : ?>
					<p class="hwpp-health-big is-critical"><?php echo esc_html( sprintf( /* translators: %d: count */ __( '%d problem(s) prevent smooth operation. The solution is shown directly below each one.', 'codefokus-handwerk-produktpass' ), $counts[ self::CRITICAL ] ) ); ?></p>
				<?php endif; ?>
				<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=hwpp-health&refresh=1' ), 'hwpp_health_refresh' ) ); ?>"><?php esc_html_e( 'Check again', 'codefokus-handwerk-produktpass' ); ?></a>
			</div>
			<?php
			// Kritisches zuerst.
			uasort(
				$checks,
				static function ( $a, $b ) {
					$order = array( 'critical' => 0, 'recommended' => 1, 'good' => 2 );
					return $order[ $a['status'] ] - $order[ $b['status'] ];
				}
			);
			?>
			<div class="hwpp-health-list">
				<?php foreach ( $checks as $check ) : ?>
					<div class="hwpp-health-item is-<?php echo esc_attr( $check['status'] ); ?>">
						<span class="hwpp-health-icon"><?php echo esc_html( $icons[ $check['status'] ] ); ?></span>
						<div>
							<strong><?php echo esc_html( $check['label'] ); ?></strong> – <?php echo esc_html( $check['message'] ); ?>
							<?php if ( $check['fix'] && self::GOOD !== $check['status'] ) : ?>
								<p class="hwpp-fix"><strong><?php esc_html_e( 'How to fix it:', 'codefokus-handwerk-produktpass' ); ?></strong> <?php echo esc_html( $check['fix'] ); ?>
								<?php if ( $check['link'] ) : ?> <a href="<?php echo esc_url( $check['link'] ); ?>"><?php esc_html_e( 'Go there →', 'codefokus-handwerk-produktpass' ); ?></a><?php endif; ?></p>
							<?php endif; ?>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
			<details class="hwpp-panel">
				<summary><?php esc_html_e( 'System information for support', 'codefokus-handwerk-produktpass' ); ?></summary>
				<textarea readonly class="large-text code hwpp-select-all" rows="10"><?php echo esc_textarea( self::system_report( $checks ) ); ?></textarea>
				<p class="description"><?php esc_html_e( 'If you do need help: copy this text and send it along. It contains no customer data and no passwords.', 'codefokus-handwerk-produktpass' ); ?></p>
			</details>
		</div>
		<?php
	}

	private static function system_report( array $checks ) {
		global $wp_version;
		$lines = array(
			'Handwerk Produktpass ' . HWPP_VERSION,
			'WordPress ' . $wp_version . ' | WooCommerce ' . ( defined( 'WC_VERSION' ) ? WC_VERSION : '-' ) . ' | PHP ' . PHP_VERSION,
			'Site: ' . home_url(),
			'HPOS: ' . ( class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' ) && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled() ? 'yes' : 'no' ),
			'Passes: ' . HWPP_Repository::count( false ) . ' (tests: ' . HWPP_Repository::count( true ) . ')',
			'Theme: ' . wp_get_theme()->get( 'Name' ),
			'',
		);
		foreach ( $checks as $key => $c ) {
			$lines[] = sprintf( '[%s] %s: %s', strtoupper( $c['status'] ), $key, $c['message'] );
		}
		return implode( "\n", $lines );
	}

	/**
	 * Einbindung in „Werkzeuge → Website-Zustand“.
	 */
	public static function site_health( $tests ) {
		$tests['direct']['hwpp'] = array(
			'label' => __( 'Handwerk Produktpass', 'codefokus-handwerk-produktpass' ),
			'test'  => array( __CLASS__, 'site_health_test' ),
		);
		return $tests;
	}

	public static function site_health_test() {
		$checks   = self::run( false );
		$critical = array_filter( $checks, static function ( $c ) { return self::CRITICAL === $c['status']; } );
		$warn     = array_filter( $checks, static function ( $c ) { return self::WARN === $c['status']; } );
		$status   = $critical ? 'critical' : ( $warn ? 'recommended' : 'good' );
		$list     = '';
		foreach ( array_merge( $critical, $warn ) as $c ) {
			$list .= '<li><strong>' . esc_html( $c['label'] ) . ':</strong> ' . esc_html( $c['message'] ) . ( $c['fix'] ? ' ' . esc_html( $c['fix'] ) : '' ) . '</li>';
		}
		return array(
			'label'       => 'good' === $status ? __( 'The digital product passport is operational', 'codefokus-handwerk-produktpass' ) : __( 'The digital product passport needs attention', 'codefokus-handwerk-produktpass' ),
			'status'      => $status,
			'badge'       => array( 'label' => __( 'Product passport', 'codefokus-handwerk-produktpass' ), 'color' => 'good' === $status ? 'green' : ( 'critical' === $status ? 'red' : 'orange' ) ),
			'description' => $list ? '<ul>' . $list . '</ul>' : '<p>' . esc_html__( 'All checks passed.', 'codefokus-handwerk-produktpass' ) . '</p>',
			'actions'     => '<a href="' . esc_url( admin_url( 'admin.php?page=hwpp-health' ) ) . '">' . esc_html__( 'Go to product passport diagnostics', 'codefokus-handwerk-produktpass' ) . '</a>',
			'test'        => 'hwpp',
		);
	}
}
