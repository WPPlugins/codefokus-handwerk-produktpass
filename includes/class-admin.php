<?php
/**
 * Back end: menu, overview, settings, designer, test mode.
 *
 * @package HandwerkProduktpass
 */

defined( 'ABSPATH' ) || exit;

class HWPP_Admin {

	const CAP = 'manage_woocommerce';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 55 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ), 20 );
		add_action( 'admin_notices', array( __CLASS__, 'notices' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( HWPP_FILE ), array( __CLASS__, 'action_links' ) );

		foreach ( array( 'save_settings', 'test_generate', 'test_delete', 'integrity', 'dismiss_error', 'qr' ) as $action ) {
			add_action( 'admin_post_hwpp_' . $action, array( __CLASS__, 'handle_' . $action ) );
		}
	}

	public static function menu() {
		add_menu_page( __( 'Product passport', 'codefokus-handwerk-produktpass' ), __( 'Product passport', 'codefokus-handwerk-produktpass' ), self::CAP, 'hwpp', array( __CLASS__, 'page_overview' ), 'dashicons-id-alt', 56 );
		add_submenu_page( 'hwpp', __( 'Overview', 'codefokus-handwerk-produktpass' ), __( 'Overview & passports', 'codefokus-handwerk-produktpass' ), self::CAP, 'hwpp', array( __CLASS__, 'page_overview' ) );
		// The material library is attached via show_in_menu of the post type.
		add_submenu_page( 'hwpp', __( 'Settings', 'codefokus-handwerk-produktpass' ), __( 'Settings', 'codefokus-handwerk-produktpass' ), self::CAP, 'hwpp-settings', array( __CLASS__, 'page_settings' ) );
		add_submenu_page( 'hwpp', __( 'Test mode', 'codefokus-handwerk-produktpass' ), __( 'Test mode', 'codefokus-handwerk-produktpass' ), self::CAP, 'hwpp-test', array( __CLASS__, 'page_test' ) );
		add_submenu_page( 'hwpp', __( 'Diagnostics', 'codefokus-handwerk-produktpass' ), __( 'Diagnostics (health check)', 'codefokus-handwerk-produktpass' ), self::CAP, 'hwpp-health', array( 'HWPP_Health', 'page' ) );
	}

	public static function action_links( $links ) {
		array_unshift(
			$links,
			'<a href="' . esc_url( admin_url( 'admin.php?page=hwpp-settings' ) ) . '">' . esc_html__( 'Settings', 'codefokus-handwerk-produktpass' ) . '</a>',
			'<a href="' . esc_url( admin_url( 'admin.php?page=hwpp-health' ) ) . '">' . esc_html__( 'Diagnostics', 'codefokus-handwerk-produktpass' ) . '</a>'
		);
		return $links;
	}

	public static function assets( $hook ) {
		$screen  = get_current_screen();
		$is_ours = false !== strpos( (string) $hook, 'hwpp' )
			|| ( $screen && in_array( $screen->post_type, array( 'product', HWPP_Materials::POST_TYPE ), true ) )
			|| ( $screen && function_exists( 'wc_get_page_screen_id' ) && wc_get_page_screen_id( 'shop-order' ) === $screen->id );
		if ( ! $is_ours ) {
			return;
		}
		wp_enqueue_style( 'hwpp-admin', HWPP_URL . 'assets/admin.css', array(), HWPP_VERSION );
		// Convenience selection fields (countries, materials) also on our own pages.
		if ( wp_script_is( 'wc-enhanced-select', 'registered' ) ) {
			wp_enqueue_script( 'wc-enhanced-select' );
			wp_enqueue_style( 'woocommerce_admin_styles' );
		}

		if ( $screen && 'product' === $screen->post_type && 'post' === $screen->base ) {
			wp_enqueue_media();
			wp_enqueue_script( 'hwpp-product', HWPP_URL . 'assets/product.js', array( 'jquery' ), HWPP_VERSION, true );
		}

		if ( false !== strpos( (string) $hook, 'hwpp' ) ) {
			wp_enqueue_script( 'hwpp-admin', HWPP_URL . 'assets/admin.js', array( 'jquery' ), HWPP_VERSION, true );
		}
	}

	public static function notices() {
		HWPP_Errors::render_notice();

		$flash = get_transient( 'hwpp_flash_' . get_current_user_id() );
		if ( $flash ) {
			delete_transient( 'hwpp_flash_' . get_current_user_id() );
			printf( '<div class="notice notice-%1$s is-dismissible hwpp-notice"><p>%2$s</p></div>', esc_attr( $flash['type'] ), esc_html( $flash['msg'] ) );
		}

		if ( get_transient( 'hwpp_activated' ) && current_user_can( self::CAP ) ) {
			delete_transient( 'hwpp_activated' );
			echo '<div class="notice notice-success hwpp-notice"><p><strong>' . esc_html__( 'Handwerk Produktpass is active.', 'codefokus-handwerk-produktpass' ) . '</strong> ';
			echo esc_html__( 'Ready in 3 steps: check company details, create materials, generate a test passport.', 'codefokus-handwerk-produktpass' ) . ' ';
			echo '<a class="button button-primary" href="' . esc_url( admin_url( 'admin.php?page=hwpp' ) ) . '">' . esc_html__( 'Go to setup', 'codefokus-handwerk-produktpass' ) . '</a></p></div>';
		}
	}

	private static function flash( $type, $msg ) {
		set_transient( 'hwpp_flash_' . get_current_user_id(), array( 'type' => $type, 'msg' => $msg ), 60 );
	}

	private static function guard( $nonce_action ) {
		self::require_cap();
		check_admin_referer( $nonce_action );
	}

	/** Stops unless the current user may manage passports. */
	public static function require_cap() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'You do not have permission for this action. Please log in as shop manager or administrator.', 'codefokus-handwerk-produktpass' ) );
		}
	}

	/**
	 * Download address for the QR code of a passport.
	 *
	 * @param object $row    Passport record.
	 * @param string $format "png" or "svg".
	 */
	public static function qr_download_url( $row, $format = 'png' ) {
		return wp_nonce_url( admin_url( 'admin-post.php?action=hwpp_qr&uid=' . rawurlencode( $row->uid ) . '&format=' . ( 'svg' === $format ? 'svg' : 'png' ) ), 'hwpp_qr' );
	}

	public static function handle_qr() {
		self::require_cap();
		check_admin_referer( 'hwpp_qr' );
		$uid = isset( $_GET['uid'] ) ? sanitize_key( wp_unslash( $_GET['uid'] ) ) : '';
		$row = HWPP_Repository::find_by_uid( $uid );
		if ( ! $row ) {
			wp_die( esc_html__( 'Passport not found.', 'codefokus-handwerk-produktpass' ) );
		}
		$url  = HWPP_Repository::public_url( $row );
		$name = sanitize_file_name( 'qr-' . $row->serial );
		$svg  = isset( $_GET['format'] ) && 'svg' === sanitize_key( wp_unslash( $_GET['format'] ) );
		$png  = $svg ? '' : HWPP_QR::png( $url, 12 );
		nocache_headers();
		if ( $png ) {
			header( 'Content-Type: image/png' );
			header( 'Content-Disposition: attachment; filename="' . $name . '.png"' );
			echo $png; // phpcs:ignore WordPress.Security.EscapeOutput -- binary image.
		} else {
			header( 'Content-Type: image/svg+xml; charset=utf-8' );
			header( 'Content-Disposition: attachment; filename="' . $name . '.svg"' );
			echo HWPP_QR::svg( $url, 600 ); // phpcs:ignore WordPress.Security.EscapeOutput -- SVG built from numbers only.
		}
		exit;
	}

	/* =====================================================================
	 * Gemeinsame Formular-Helfer
	 * ===================================================================== */

	public static function render_field_row( $name, $id, array $field, $value ) {
		$tip = ! empty( $field['tip'] ) ? ' ' . HWPP_Glossary::tip( $field['tip'] ) : '';
		echo wp_kses( '<tr><th scope="row"><label for="' . esc_attr( $id ) . '">' . esc_html( $field['label'] ) . '</label>' . $tip . '</th><td>', HWPP_Admin::kses() );
		$ph = isset( $field['placeholder'] ) ? $field['placeholder'] : '';
		switch ( $field['type'] ) {
			case 'country':
				echo '<select id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" class="wc-enhanced-select" style="min-width:300px"><option value="">' . esc_html__( '– please select –', 'codefokus-handwerk-produktpass' ) . '</option>';
				foreach ( WC()->countries->get_countries() as $code => $cname ) {
					echo '<option value="' . esc_attr( $code ) . '" ' . selected( $value, $code, false ) . '>' . esc_html( html_entity_decode( $cname ) ) . '</option>';
				}
				echo '</select>';
				break;
			case 'select':
				echo '<select id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '"><option value="">–</option>';
				foreach ( $field['options'] as $k => $l ) {
					echo '<option value="' . esc_attr( $k ) . '" ' . selected( $value, $k, false ) . '>' . esc_html( $l ) . '</option>';
				}
				echo '</select>';
				break;
			case 'textarea':
				echo '<textarea id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" rows="3" class="large-text" placeholder="' . esc_attr( $ph ) . '">' . esc_textarea( (string) $value ) . '</textarea>';
				break;
			case 'number':
				echo '<input type="number" step="0.01" min="0" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( (string) $value ) . '" class="small-text">';
				break;
			case 'url':
				echo '<input type="url" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( (string) $value ) . '" class="regular-text" placeholder="https://">';
				break;
			case 'checkbox':
				echo '<label><input type="checkbox" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="1" ' . checked( ! empty( $value ), true, false ) . '> ' . esc_html( isset( $field['cb_label'] ) ? $field['cb_label'] : '' ) . '</label>';
				break;
			default:
				echo '<input type="text" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( (string) $value ) . '" class="regular-text" placeholder="' . esc_attr( $ph ) . '">';
		}
		if ( ! empty( $field['desc'] ) ) {
			echo '<p class="description">' . wp_kses_post( $field['desc'] ) . '</p>';
		}
		echo '</td></tr>';
	}

	/**
	 * Allowed HTML for plugin-generated admin markup (buttons, tooltips, forms, images).
	 *
	 * @return array
	 */
	public static function kses() {
		$common = array( 'class' => true, 'id' => true, 'style' => true, 'title' => true, 'aria-label' => true, 'role' => true, 'tabindex' => true, 'data-tip' => true, 'data-key' => true );
		return array(
			'a'        => $common + array( 'href' => true, 'target' => true, 'rel' => true, 'onclick' => true ),
			'img'      => $common + array( 'src' => true, 'alt' => true, 'loading' => true, 'width' => true, 'height' => true ),
			'span'     => $common,
			'div'      => $common,
			'p'        => $common,
			'strong'   => $common,
			'em'       => $common,
			'br'       => array(),
			'ul'       => $common,
			'li'       => $common,
			'h3'       => $common,
			'aside'    => $common,
			'label'    => $common + array( 'for' => true ),
			'details'  => $common + array( 'open' => true ),
			'summary'  => $common,
			'form'     => $common + array( 'method' => true, 'action' => true, 'onsubmit' => true ),
			'input'    => $common + array( 'type' => true, 'name' => true, 'value' => true, 'placeholder' => true, 'checked' => true, 'required' => true, 'size' => true ),
			'select'   => $common + array( 'name' => true, 'required' => true ),
			'option'   => array( 'value' => true, 'selected' => true ),
			'textarea' => $common + array( 'name' => true, 'rows' => true, 'cols' => true, 'placeholder' => true, 'required' => true ),
			'button'   => $common + array( 'type' => true, 'name' => true, 'value' => true ),
		);
	}

	/**
	 * First cleaning step for submitted form arrays: every value is sanitised as
	 * (multi-line) plain text right when it is received. Each field is then
	 * sanitised again with its specific rule (sanitize_field()).
	 *
	 * @param mixed $raw Unslashed input.
	 * @return array
	 */
	public static function clean_input( $raw ) {
		return is_array( $raw ) ? map_deep( $raw, 'sanitize_textarea_field' ) : array();
	}

	public static function sanitize_field( $raw, array $field ) {
		switch ( $field['type'] ) {
			case 'textarea':
				return sanitize_textarea_field( (string) $raw );
			case 'number':
				return '' === trim( (string) $raw ) ? '' : (string) max( 0, (float) str_replace( ',', '.', (string) $raw ) );
			case 'url':
				return esc_url_raw( (string) $raw );
			case 'country':
				$raw = strtoupper( sanitize_text_field( (string) $raw ) );
				return preg_match( '/^[A-Z]{2}$/', $raw ) ? $raw : '';
			case 'select':
				return isset( $field['options'][ $raw ] ) ? $raw : '';
			case 'checkbox':
				return empty( $raw ) ? 0 : 1;
			default:
				return sanitize_text_field( (string) $raw );
		}
	}

	/* =====================================================================
	 * Overview
	 * ===================================================================== */

	public static function setup_steps() {
		$s         = HWPP_Settings::all();
		$materials = wp_count_posts( HWPP_Materials::POST_TYPE );
		$steps     = array(
			array(
				'done'  => 'generic' !== HWPP_Presets::current() || (bool) get_option( 'hwpp_trade_chosen' ),
				'title' => __( 'Choose your trade', 'codefokus-handwerk-produktpass' ),
				'desc'  => __( 'Unlocks matching additional fields (e.g. fineness for jewellery, tanning method for leather).', 'codefokus-handwerk-produktpass' ),
				'url'   => admin_url( 'admin.php?page=hwpp-settings&tab=company' ),
			),
			array(
				'done'  => ! empty( $s['company_name'] ) && ! empty( $s['company_street'] ) && ! empty( $s['company_city'] ) && ! empty( $s['company_email'] ),
				'title' => __( 'Enter company details', 'codefokus-handwerk-produktpass' ),
				'desc'  => __( 'Name, address and contact appear in every passport (mandatory for the economic operator).', 'codefokus-handwerk-produktpass' ),
				'url'   => admin_url( 'admin.php?page=hwpp-settings&tab=company' ),
			),
			array(
				'done'  => $materials && $materials->publish > 0,
				'title' => __( 'Create materials', 'codefokus-handwerk-produktpass' ),
				'desc'  => __( 'Record woods, leathers and metals once with origin and certificates.', 'codefokus-handwerk-produktpass' ),
				'url'   => admin_url( 'post-new.php?post_type=' . HWPP_Materials::POST_TYPE ),
			),
			array(
				'done'  => (bool) get_option( 'hwpp_product_ready' ) || self::count_ready_products() > 0,
				'title' => __( 'Complete at least one product', 'codefokus-handwerk-produktpass' ),
				'desc'  => __( 'Fill in the “Product passport” tab in the product. In the product list, the “Passport” column shows the status.', 'codefokus-handwerk-produktpass' ),
				'url'   => admin_url( 'edit.php?post_type=product' ),
			),
			array(
				'done'  => HWPP_Repository::count( true ) > 0 || HWPP_Repository::count( false ) > 0,
				'title' => __( 'Generate test passport', 'codefokus-handwerk-produktpass' ),
				'desc'  => __( 'Run through the complete process once – without a real order.', 'codefokus-handwerk-produktpass' ),
				'url'   => admin_url( 'admin.php?page=hwpp-test' ),
			),
		);

		/**
		 * Setup checklist on the overview page.
		 *
		 * @param array $steps Steps with done, title, desc, url.
		 */
		return (array) apply_filters( 'hwpp_setup_steps', $steps );
	}

	private static function count_ready_products() {
		$ids = get_posts( array( 'post_type' => 'product', 'post_status' => 'publish', 'posts_per_page' => 30, 'fields' => 'ids' ) );
		foreach ( $ids as $id ) {
			if ( HWPP_Product_Data::applies_to( $id ) && ! HWPP_Fields::missing_required( $id ) ) {
				update_option( 'hwpp_product_ready', 1, false );
				return 1;
			}
		}
		return 0;
	}

	public static function page_overview() {
		$steps  = self::setup_steps();
		$done   = count( array_filter( wp_list_pluck( $steps, 'done' ) ) );
		$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		$paged  = max( 1, isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1 ); // phpcs:ignore WordPress.Security.NonceVerification
		$per    = 25;
		$rows   = HWPP_Repository::latest( $per, ( $paged - 1 ) * $per, $search, false );
		$last   = get_option( 'hwpp_last_integrity_check' );
		$result = get_transient( 'hwpp_integrity_result_' . get_current_user_id() );
		?>
		<div class="wrap hwpp-wrap">
			<h1><?php esc_html_e( 'Digital product passport', 'codefokus-handwerk-produktpass' ); ?> <?php HWPP_Glossary::e( 'dpp' ); ?></h1>

			<?php if ( $done < count( $steps ) ) : ?>
				<div class="hwpp-panel hwpp-setup">
					<h2><?php echo esc_html( sprintf( /* translators: 1: done, 2: total */ __( 'Setup: %1$d of %2$d steps done', 'codefokus-handwerk-produktpass' ), $done, count( $steps ) ) ); ?></h2>
					<div class="hwpp-progress"><span style="width:<?php echo (int) round( $done / count( $steps ) * 100 ); ?>%"></span></div>
					<ol class="hwpp-steps">
						<?php foreach ( $steps as $step ) : ?>
							<li class="<?php echo $step['done'] ? 'is-done' : ''; ?>">
								<span class="hwpp-step-icon"><?php echo $step['done'] ? '✓' : ''; ?></span>
								<div><strong><?php echo esc_html( $step['title'] ); ?></strong><br><span class="description"><?php echo esc_html( $step['desc'] ); ?></span></div>
								<?php if ( ! $step['done'] ) : ?><a class="button" href="<?php echo esc_url( $step['url'] ); ?>"><?php esc_html_e( 'Do it now', 'codefokus-handwerk-produktpass' ); ?></a><?php endif; ?>
							</li>
						<?php endforeach; ?>
					</ol>
				</div>
			<?php endif; ?>

			<div class="hwpp-stats">
				<div class="hwpp-stat"><strong><?php echo esc_html( number_format_i18n( HWPP_Repository::count( false ) ) ); ?></strong><span><?php esc_html_e( 'issued passports', 'codefokus-handwerk-produktpass' ); ?></span></div>
				<div class="hwpp-stat"><strong><?php echo esc_html( number_format_i18n( (int) wp_count_posts( HWPP_Materials::POST_TYPE )->publish ) ); ?></strong><span><?php esc_html_e( 'materials in the library', 'codefokus-handwerk-produktpass' ); ?></span></div>
				<div class="hwpp-stat <?php echo HWPP_Errors::open() ? 'is-warn' : ''; ?>"><strong><?php echo esc_html( number_format_i18n( count( HWPP_Errors::open() ) ) ); ?></strong><span><?php esc_html_e( 'orders with open notices', 'codefokus-handwerk-produktpass' ); ?></span></div>
				<div class="hwpp-stat">
					<strong><?php echo $last ? ( $last['invalid'] || $last['broken'] ? '⚠' : '✓' ) : '–'; ?></strong>
					<span><?php echo $last ? esc_html( sprintf( /* translators: %s: date */ __( 'Integrity checked on %s', 'codefokus-handwerk-produktpass' ), wp_date( get_option( 'date_format' ) . ' H:i', $last['time'] ) ) ) : esc_html__( 'Integrity not checked yet', 'codefokus-handwerk-produktpass' ); ?> <?php HWPP_Glossary::e( 'hashkette' ); ?></span>
				</div>
			</div>

			<?php do_action( 'hwpp_overview_after_stats' ); // Fires below the key figures on the overview page. ?>

			<?php if ( $result ) : ?>
				<?php delete_transient( 'hwpp_integrity_result_' . get_current_user_id() ); ?>
				<div class="notice <?php echo $result['ok'] ? 'notice-success' : 'notice-error'; ?> inline"><p><?php echo esc_html( $result['msg'] ); ?></p>
				<?php if ( ! empty( $result['details'] ) ) : ?><ul class="ul-disc"><?php foreach ( $result['details'] as $line ) : ?><li><?php echo esc_html( $line ); ?></li><?php endforeach; ?></ul><?php endif; ?>
				</div>
			<?php endif; ?>

			<div class="hwpp-panel">
				<div class="hwpp-panel-head">
					<h2><?php esc_html_e( 'Issued product passports', 'codefokus-handwerk-produktpass' ); ?></h2>
					<div>
						<form method="get" class="hwpp-inline-form">
							<input type="hidden" name="page" value="hwpp">
							<input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Serial number or order ID', 'codefokus-handwerk-produktpass' ); ?>">
							<button class="button"><?php esc_html_e( 'Search', 'codefokus-handwerk-produktpass' ); ?></button>
						</form>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="hwpp-inline-form">
							<?php wp_nonce_field( 'hwpp_integrity' ); ?>
							<input type="hidden" name="action" value="hwpp_integrity">
							<button class="button button-secondary"><?php esc_html_e( 'Check integrity of all passports', 'codefokus-handwerk-produktpass' ); ?></button>
						</form>
					</div>
				</div>
				<?php if ( ! $rows ) : ?>
					<p class="hwpp-empty"><?php echo $search ? esc_html__( 'No passports found for this search.', 'codefokus-handwerk-produktpass' ) : esc_html__( 'No passports issued yet. As soon as an order is paid, the passport appears here automatically. Try it out in test mode first.', 'codefokus-handwerk-produktpass' ); ?></p>
				<?php else : ?>
					<table class="widefat striped hwpp-table">
						<thead><tr><th><?php esc_html_e( 'Serial number', 'codefokus-handwerk-produktpass' ); ?></th><th><?php esc_html_e( 'Product', 'codefokus-handwerk-produktpass' ); ?></th><th><?php esc_html_e( 'Order', 'codefokus-handwerk-produktpass' ); ?></th><th><?php esc_html_e( 'Issued', 'codefokus-handwerk-produktpass' ); ?></th><th><?php esc_html_e( 'Authenticity', 'codefokus-handwerk-produktpass' ); ?> <?php HWPP_Glossary::e( 'qr_pruefsumme' ); ?></th><th></th></tr></thead>
						<tbody>
						<?php foreach ( $rows as $row ) : ?>
							<?php
							$payload = HWPP_Repository::payload( $row );
							$lang    = isset( $payload['primary_language'] ) ? $payload['primary_language'] : '';
							$name    = isset( $payload['content'][ $lang ]['name'] ) ? $payload['content'][ $lang ]['name'] : '#' . $row->product_id;
							$order   = wc_get_order( $row->order_id );
							$res     = HWPP_Integrity::verify( $row );
							?>
							<tr>
								<td><code><?php echo esc_html( $row->serial ); ?></code></td>
								<td><?php echo esc_html( $name ); ?></td>
								<td><?php echo $order ? '<a href="' . esc_url( $order->get_edit_order_url() ) . '">#' . esc_html( $order->get_order_number() ) . '</a>' : '#' . (int) $row->order_id; ?></td>
								<td><?php echo esc_html( HWPP_Public_View::format_date( $row->created_at ) ); ?></td>
								<td><span class="hwpp-dot <?php echo $res['valid'] ? 'is-ok' : 'is-bad'; ?>"></span> <?php echo $res['valid'] ? esc_html__( 'valid', 'codefokus-handwerk-produktpass' ) : esc_html__( 'altered!', 'codefokus-handwerk-produktpass' ); ?>
									<?php if ( 'active' !== $row->status ) : ?><br><span class="hwpp-badge hwpp-badge--warn"><?php echo esc_html( HWPP_Lifecycle::label( $row->status ) ); ?></span><?php endif; ?></td>
								<td><a href="<?php echo esc_url( HWPP_Repository::public_url( $row ) ); ?>" target="_blank"><?php esc_html_e( 'Passport', 'codefokus-handwerk-produktpass' ); ?></a> · <?php $cert = HWPP_Repository::certificate_url( $row ); ?><?php if ( $cert ) : ?><a href="<?php echo esc_url( $cert ); ?>" target="_blank"><?php esc_html_e( 'PDF', 'codefokus-handwerk-produktpass' ); ?></a><?php endif; ?>
									<?php if ( $row->views ) : ?><br><span class="hwpp-muted"><?php echo esc_html( sprintf( /* translators: %d: views */ _n( '%d view', '%d views', (int) $row->views, 'codefokus-handwerk-produktpass' ), (int) $row->views ) ); ?></span><?php endif; ?>
									<?php echo wp_kses( HWPP_Lifecycle::action_forms( $row ), HWPP_Admin::kses() ); ?></td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
					<?php
					$total = $search ? 0 : HWPP_Repository::count( false );
					if ( $total > $per ) {
						echo '<div class="tablenav"><div class="tablenav-pages">' . wp_kses_post( paginate_links( array( 'base' => add_query_arg( 'paged', '%#%' ), 'format' => '', 'current' => $paged, 'total' => (int) ceil( $total / $per ) ) ) ) . '</div></div>';
					}
					?>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	public static function handle_integrity() {
		self::guard( 'hwpp_integrity' );
		$res     = HWPP_Integrity::verify_all( 0 );
		$details = array();
		foreach ( array_slice( $res['invalid'], 0, 10 ) as $row ) {
			/* translators: %s: serial */
			$details[] = sprintf( __( 'Passport %s: data or signature no longer match the original.', 'codefokus-handwerk-produktpass' ), $row->serial );
		}
		foreach ( array_slice( $res['broken_links'], 0, 10 ) as $row ) {
			/* translators: %s: serial */
			$details[] = sprintf( __( 'A link of the hash chain is missing before passport %s – an older passport was deleted or altered.', 'codefokus-handwerk-produktpass' ), $row->serial );
		}
		$ok = ! $res['invalid'] && ! $res['broken_links'];
		set_transient(
			'hwpp_integrity_result_' . get_current_user_id(),
			array(
				'ok'      => $ok,
				'msg'     => $ok
					/* translators: %d: count */
					? sprintf( __( 'All good: %d passports checked, all genuine and unchanged, hash chain complete.', 'codefokus-handwerk-produktpass' ), $res['checked'] )
					/* translators: %d: count */
					: sprintf( __( 'Warning: inconsistencies were found when checking %d passports. This indicates a later change directly in the database or a changed signing key. If in doubt, restore a backup.', 'codefokus-handwerk-produktpass' ), $res['checked'] ),
				'details' => $details,
			),
			120
		);
		wp_safe_redirect( admin_url( 'admin.php?page=hwpp' ) );
		exit;
	}

	public static function handle_dismiss_error() {
		self::require_cap();
		check_admin_referer( 'hwpp_dismiss_error' );
		$order_id = isset( $_GET['order_id'] ) ? absint( $_GET['order_id'] ) : 0;
		HWPP_Errors::dismiss( $order_id );
		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url( 'admin.php?page=hwpp' ) );
		exit;
	}

	/* =====================================================================
	 * Einstellungen
	 * ===================================================================== */

	private static function settings_tabs() {
		$tabs = array(
			'company'  => __( 'Company', 'codefokus-handwerk-produktpass' ),
			'rules'    => __( 'Rules & required fields', 'codefokus-handwerk-produktpass' ),
			'delivery' => __( 'Delivery', 'codefokus-handwerk-produktpass' ),
			'advanced' => __( 'Advanced', 'codefokus-handwerk-produktpass' ),
		);
		/**
		 * Tabs of the settings page. Extensions add their own tabs.
		 *
		 * @param array $tabs Labels by key (may contain <span class="…">).
		 */
		return (array) apply_filters( 'hwpp_settings_tabs', $tabs );
	}

	public static function page_settings() {
		$tabs = self::settings_tabs();
		$tab  = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'company'; // phpcs:ignore WordPress.Security.NonceVerification
		$tab  = isset( $tabs[ $tab ] ) ? $tab : 'company';
		$s    = HWPP_Settings::all();
		/**
		 * Whether a settings tab shows a form with a save button.
		 *
		 * @param bool   $has_form Default true.
		 * @param string $tab      Tab key.
		 */
		$has_form = (bool) apply_filters( 'hwpp_settings_tab_has_form', true, $tab );
		?>
		<div class="wrap hwpp-wrap">
			<h1><?php esc_html_e( 'Product passport – settings', 'codefokus-handwerk-produktpass' ); ?></h1>
			<nav class="nav-tab-wrapper">
				<?php foreach ( $tabs as $key => $label ) : ?>
					<a class="nav-tab <?php echo $key === $tab ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( admin_url( 'admin.php?page=hwpp-settings&tab=' . $key ) ); ?>"><?php echo wp_kses( $label, array( 'span' => array( 'class' => true ) ) ); ?></a>
				<?php endforeach; ?>
			</nav>
			<div class="hwpp-layout">
			<div class="hwpp-layout__main">
			<?php if ( ! $has_form ) : ?>
				<?php
				/**
				 * Content of a settings tab without form.
				 *
				 * @param string $tab Tab key.
				 * @param array  $s   Settings.
				 */
				do_action( 'hwpp_settings_tab_content', $tab, $s );
				?>
			<?php else : ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="hwpp-panel">
			<?php wp_nonce_field( 'hwpp_save_settings' ); ?>
				<input type="hidden" name="action" value="hwpp_save_settings">
				<input type="hidden" name="tab" value="<?php echo esc_attr( $tab ); ?>">
				<table class="form-table hwpp-form">
				<?php
				switch ( $tab ) {
					case 'company':
						echo wp_kses( '<tr><td colspan="2" class="hwpp-intro">' . esc_html__( 'This information appears as “Manufacturer” in every product passport.', 'codefokus-handwerk-produktpass' ) . ' ' . HWPP_Glossary::tip( 'wirtschaftsakteur' ) . '</td></tr>', HWPP_Admin::kses() );
						self::render_field_row( 's[trade]', 'hwpp_trade', array( 'label' => __( 'Your trade', 'codefokus-handwerk-produktpass' ), 'type' => 'select', 'options' => HWPP_Presets::choices(), 'tip' => 'gewerk', 'desc' => esc_html__( 'Adds matching additional fields to the product tab (e.g. fineness and hallmark for jewellery) and adds recommended required fields.', 'codefokus-handwerk-produktpass' ) ), HWPP_Presets::current() );
						foreach ( self::company_fields() as $key => $field ) {
							self::render_field_row( 's[' . $key . ']', 'hwpp_' . $key, $field, $s[ $key ] );
						}
						break;

					case 'rules':
						self::render_required_fields( $s );
						self::render_field_row( 's[require_material_origin]', 'hwpp_rmo', array( 'label' => __( 'Enforce raw material origin', 'codefokus-handwerk-produktpass' ), 'type' => 'checkbox', 'cb_label' => __( 'Every assigned material must have a country of origin.', 'codefokus-handwerk-produktpass' ), 'tip' => 'rohstoffherkunft' ), $s['require_material_origin'] );
						self::render_field_row( 's[block_publish]', 'hwpp_bp', array( 'label' => __( 'Prevent publishing', 'codefokus-handwerk-produktpass' ), 'type' => 'checkbox', 'cb_label' => __( 'Automatically save incomplete products as drafts (otherwise warning only).', 'codefokus-handwerk-produktpass' ) ), $s['block_publish'] );
						self::render_statuses( $s );
						self::render_field_row(
							's[serial_format]',
							'hwpp_serial',
							array(
								'label' => __( 'Serial number format', 'codefokus-handwerk-produktpass' ),
								'type'  => 'text',
								'tip'   => 'seriennummer',
								/* translators: %s: example serial */
								'desc'  => esc_html( sprintf( __( 'Example with current format: %s', 'codefokus-handwerk-produktpass' ), strtr( $s['serial_format'], array( '{year}' => gmdate( 'Y' ), '{order}' => '1042', '{pos}' => '01', '{unit}' => '1', '{sku}' => 'TISCH-EI', '{rand}' => 'A7K2Q' ) ) ) ),
							),
							$s['serial_format']
						);
						self::render_categories( $s );
						self::render_restricted( $s );
						self::render_field_row( 's[default_certifications]', 'hwpp_certs', array( 'label' => __( 'Default company certifications', 'codefokus-handwerk-produktpass' ), 'type' => 'textarea', 'tip' => 'zertifizierung', 'placeholder' => __( 'e.g. master craft business (Chamber of Crafts Freiburg), FSC® chain of custody FSC-C000000', 'codefokus-handwerk-produktpass' ), 'desc' => esc_html__( 'Appears in every passport in addition to the product certifications.', 'codefokus-handwerk-produktpass' ) ), $s['default_certifications'] );
						break;

					case 'delivery':
						self::render_field_row( 's[email_links]', 'hwpp_el', array( 'label' => __( 'Links in customer emails', 'codefokus-handwerk-produktpass' ), 'type' => 'checkbox', 'cb_label' => __( 'Add links to the product passports in order and completion emails.', 'codefokus-handwerk-produktpass' ) ), $s['email_links'] );
						self::render_field_row( 's[account_links]', 'hwpp_al', array( 'label' => __( 'Customer account', 'codefokus-handwerk-produktpass' ), 'type' => 'checkbox', 'cb_label' => __( 'Show passports under “My account → Orders”.', 'codefokus-handwerk-produktpass' ) ), $s['account_links'] );
						self::render_field_row(
							's[public_slug]',
							'hwpp_slug',
							array(
								'label' => __( 'Address of passport pages', 'codefokus-handwerk-produktpass' ),
								'type'  => 'text',
								/* translators: %s: example URL */
								'desc'  => esc_html( sprintf( __( 'Example: %s – Warning: if changed, QR codes that have already been printed will stop working!', 'codefokus-handwerk-produktpass' ), home_url( '/' . $s['public_slug'] . '/…/' ) ) ),
							),
							$s['public_slug']
						);
						break;

					case 'advanced':
						self::render_secret_row();
						self::render_field_row( 's[delete_on_uninstall]', 'hwpp_del', array( 'label' => __( 'Remove data on deletion', 'codefokus-handwerk-produktpass' ), 'type' => 'checkbox', 'cb_label' => __( 'Irrevocably remove all passports, materials and settings when the plugin is deleted.', 'codefokus-handwerk-produktpass' ), 'desc' => esc_html__( 'Not recommended: issued passports serve as evidence for warranty claims. Printed QR codes would lead nowhere.', 'codefokus-handwerk-produktpass' ) ), $s['delete_on_uninstall'] );
						break;
				}
				/**
				 * Fires at the end of a settings tab (inside the form table).
				 * Tabs registered by extensions are rendered here completely.
				 *
				 * @param string $tab Tab key.
				 * @param array  $s   Settings.
				 */
				do_action( 'hwpp_settings_rows', $tab, $s );
				?>
				</table>
				<?php submit_button( __( 'Save settings', 'codefokus-handwerk-produktpass' ) ); ?>
			</form>
			<?php endif; ?>
			</div>
			<?php self::sidebar( 'settings' ); ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Optional sidebar next to the main content (tips, upgrade box).
	 *
	 * @param string $context Page key.
	 */
	public static function sidebar( $context ) {
		ob_start();
		/**
		 * Content of the sidebar on plugin pages.
		 *
		 * @param string $context Page key.
		 */
		do_action( 'hwpp_admin_sidebar', $context );
		$html = trim( (string) ob_get_clean() );
		if ( '' !== $html ) {
			echo wp_kses( '<aside class="hwpp-layout__side">' . $html . '</aside>', HWPP_Admin::kses() );
		}
	}

	private static function company_fields() {
		return array(
			'company_name'     => array( 'label' => __( 'Company name', 'codefokus-handwerk-produktpass' ), 'type' => 'text' ),
			'company_street'   => array( 'label' => __( 'Street & house number', 'codefokus-handwerk-produktpass' ), 'type' => 'text' ),
			'company_zip'      => array( 'label' => __( 'Postcode', 'codefokus-handwerk-produktpass' ), 'type' => 'text' ),
			'company_city'     => array( 'label' => __( 'City', 'codefokus-handwerk-produktpass' ), 'type' => 'text' ),
			'company_country'  => array( 'label' => __( 'Country', 'codefokus-handwerk-produktpass' ), 'type' => 'country' ),
			'company_email'    => array( 'label' => __( 'Contact email', 'codefokus-handwerk-produktpass' ), 'type' => 'text' ),
			'company_phone'    => array( 'label' => __( 'Phone', 'codefokus-handwerk-produktpass' ), 'type' => 'text' ),
			'company_website'  => array( 'label' => __( 'Website', 'codefokus-handwerk-produktpass' ), 'type' => 'url' ),
			'company_vat'      => array( 'label' => __( 'VAT ID', 'codefokus-handwerk-produktpass' ), 'type' => 'text' ),
			'company_register' => array( 'label' => __( 'Commercial register / register of craftsmen', 'codefokus-handwerk-produktpass' ), 'type' => 'text', 'placeholder' => __( 'e.g. register of craftsmen HWK Freiburg no. 12345', 'codefokus-handwerk-produktpass' ) ),
		);
	}

	private static function render_required_fields( $s ) {
		echo wp_kses( '<tr><th scope="row">' . esc_html__( 'Required fields per product', 'codefokus-handwerk-produktpass' ) . ' ' . HWPP_Glossary::tip( 'pflichtfeld' ) . '</th><td><fieldset class="hwpp-checklist">', HWPP_Admin::kses() );
		foreach ( HWPP_Fields::product_fields() as $key => $field ) {
			echo '<label><input type="checkbox" name="s[required_fields][]" value="' . esc_attr( $key ) . '" ' . checked( in_array( $key, (array) $s['required_fields'], true ), true, false ) . '> ' . esc_html( $field['label'] );
			if ( ! empty( $field['legal'] ) ) {
				echo ' <span class="hwpp-badge hwpp-badge--info" title="' . esc_attr__( 'Core information of typical EU DPP requirements (ESPR). Recommended as a required field.', 'codefokus-handwerk-produktpass' ) . '">' . esc_html__( 'recommended', 'codefokus-handwerk-produktpass' ) . '</span>';
			}
			echo '</label>';
		}
		echo wp_kses( '</fieldset><p class="description">' . esc_html__( 'If any of this information is missing, you are warned when saving the product and no passport is issued.', 'codefokus-handwerk-produktpass' ) . ' ' . HWPP_Glossary::tip( 'espr' ) . '</p></td></tr>', HWPP_Admin::kses() );
	}

	private static function render_restricted( $s ) {
		$choices = array( 'material_supplier' => __( 'Suppliers of the materials', 'codefokus-handwerk-produktpass' ) );
		foreach ( HWPP_Fields::product_fields() as $key => $field ) {
			if ( ! in_array( $field['type'], array( 'materials', 'gallery' ), true ) ) {
				$choices[ $key ] = $field['label'];
			}
		}
		echo wp_kses( '<tr><th scope="row">' . esc_html__( 'Restricted information', 'codefokus-handwerk-produktpass' ) . ' ' . HWPP_Glossary::tip( 'geschuetzt' ) . '</th><td><fieldset class="hwpp-checklist">', HWPP_Admin::kses() );
		foreach ( $choices as $key => $label ) {
			echo '<label><input type="checkbox" name="s[restricted_fields][]" value="' . esc_attr( $key ) . '" ' . checked( in_array( $key, (array) $s['restricted_fields'], true ), true, false ) . '> ' . esc_html( $label ) . '</label>';
		}
		echo '</fieldset><p class="description">' . esc_html__( 'This information is only visible with the access code printed on the certificate of authenticity – e.g. for repair businesses or authorities. If nothing is selected, everything is public.', 'codefokus-handwerk-produktpass' ) . '</p></td></tr>';
	}

	private static function render_statuses( $s ) {
		echo '<tr><th scope="row">' . esc_html__( 'Issue passport at status', 'codefokus-handwerk-produktpass' ) . '</th><td><fieldset class="hwpp-checklist">';
		foreach ( HWPP_Settings::status_choices() as $key => $label ) {
			echo '<label><input type="checkbox" name="s[trigger_statuses][]" value="' . esc_attr( $key ) . '" ' . checked( in_array( $key, (array) $s['trigger_statuses'], true ), true, false ) . '> ' . esc_html( $label ) . '</label>';
		}
		echo '</fieldset><p class="description">' . esc_html__( 'Recommended: “Processing” (= paid) and “Completed”. The passport is created only once, no matter how often the status changes.', 'codefokus-handwerk-produktpass' ) . '</p></td></tr>';
	}

	private static function render_categories( $s ) {
		$terms = get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => false ) );
		if ( is_wp_error( $terms ) || ! $terms ) {
			return;
		}
		echo '<tr><th scope="row"><label for="hwpp_cats">' . esc_html__( 'Only for these categories', 'codefokus-handwerk-produktpass' ) . '</label></th><td>';
		echo '<select id="hwpp_cats" name="s[product_categories][]" multiple class="wc-enhanced-select" style="min-width:300px" data-placeholder="' . esc_attr__( 'All products', 'codefokus-handwerk-produktpass' ) . '">';
		foreach ( $terms as $t ) {
			echo '<option value="' . esc_attr( $t->term_id ) . '" ' . selected( in_array( (int) $t->term_id, array_map( 'intval', (array) $s['product_categories'] ), true ), true, false ) . '>' . esc_html( $t->name ) . '</option>';
		}
		echo '</select><p class="description">' . esc_html__( 'Leave empty = product passport for all products. Individual products can additionally be excluded in the product tab.', 'codefokus-handwerk-produktpass' ) . '</p></td></tr>';
	}

	private static function render_secret_row() {
		echo wp_kses( '<tr><th scope="row">' . esc_html__( 'Signing key', 'codefokus-handwerk-produktpass' ) . ' ' . HWPP_Glossary::tip( 'signatur' ) . '</th><td>', HWPP_Admin::kses() );
		if ( 'config' === HWPP_Integrity::secret_source() ) {
			echo '<p><span class="hwpp-badge hwpp-badge--ok">✓</span> ' . esc_html__( 'The key is stored securely in wp-config.php.', 'codefokus-handwerk-produktpass' ) . '</p>';
		} else {
			echo '<p>' . esc_html__( 'The key is currently stored in the database. For maximum tamper protection, copy the following line into your wp-config.php (above “That’s all, stop editing”). Important: use exactly this value, otherwise existing passports will be shown as invalid.', 'codefokus-handwerk-produktpass' ) . '</p>';
			if ( current_user_can( 'manage_options' ) ) {
				echo '<input type="text" readonly class="large-text code hwpp-select-all" value="' . esc_attr( "define( 'HWPP_SECRET', '" . get_option( 'hwpp_secret' ) . "' );" ) . '">';
			} else {
				echo '<p class="description">' . esc_html__( 'Only administrators can view the key.', 'codefokus-handwerk-produktpass' ) . '</p>';
			}
		}
		echo '<p class="description">' . esc_html__( 'Also keep the key in a safe place (e.g. password manager). If it is lost, existing passports can no longer be confirmed as genuine.', 'codefokus-handwerk-produktpass' ) . '</p></td></tr>';
	}

	public static function handle_save_settings() {
		self::require_cap();
		check_admin_referer( 'hwpp_save_settings' );
		$tab = isset( $_POST['tab'] ) ? sanitize_key( wp_unslash( $_POST['tab'] ) ) : 'company';
		$in  = isset( $_POST['s'] ) ? self::clean_input( wp_unslash( $_POST['s'] ) ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitised by clean_input() (map_deep + sanitize_textarea_field).
		$out = array();

		switch ( $tab ) {
			case 'company':
				$trade = sanitize_key( isset( $in['trade'] ) ? $in['trade'] : 'generic' );
				update_option( 'hwpp_trade_chosen', 1, false );
				if ( $trade !== HWPP_Presets::current() ) {
					HWPP_Presets::apply( $trade );
				}
				foreach ( self::company_fields() as $key => $field ) {
					$out[ $key ] = self::sanitize_field( isset( $in[ $key ] ) ? $in[ $key ] : '', $field );
				}
				if ( $out['company_email'] && ! is_email( $out['company_email'] ) ) {
					self::flash( 'error', __( 'The contact email address looks invalid (example: info@workshop.com). Please correct it.', 'codefokus-handwerk-produktpass' ) );
					wp_safe_redirect( admin_url( 'admin.php?page=hwpp-settings&tab=company' ) );
					exit;
				}
				break;

			case 'rules':
				$fields                     = array_keys( HWPP_Fields::product_fields() );
				$out['required_fields']     = array_values( array_intersect( $fields, array_map( 'sanitize_key', (array) ( isset( $in['required_fields'] ) ? $in['required_fields'] : array() ) ) ) );
				$out['require_material_origin'] = empty( $in['require_material_origin'] ) ? 0 : 1;
				$out['block_publish']       = empty( $in['block_publish'] ) ? 0 : 1;
				$out['trigger_statuses']    = array_values( array_intersect( array_keys( HWPP_Settings::status_choices() ), array_map( 'sanitize_key', (array) ( isset( $in['trigger_statuses'] ) ? $in['trigger_statuses'] : array() ) ) ) );
				$fmt                        = preg_replace( '/[^A-Za-z0-9{}._-]/', '', (string) ( isset( $in['serial_format'] ) ? $in['serial_format'] : '' ) );
				$out['serial_format']       = $fmt ? $fmt : 'DPP-{year}-{order}-{pos}';
				$out['product_categories']  = array_map( 'absint', (array) ( isset( $in['product_categories'] ) ? $in['product_categories'] : array() ) );
				$out['default_certifications'] = sanitize_textarea_field( isset( $in['default_certifications'] ) ? $in['default_certifications'] : '' );
				$allowed_restricted         = array_merge( array( 'material_supplier' ), array_keys( HWPP_Fields::product_fields() ) );
				$out['restricted_fields']   = array_values( array_intersect( $allowed_restricted, array_map( 'sanitize_key', (array) ( isset( $in['restricted_fields'] ) ? $in['restricted_fields'] : array() ) ) ) );
				if ( ! $out['trigger_statuses'] ) {
					$out['trigger_statuses'] = array( 'processing', 'completed' );
					self::flash( 'warning', __( 'You have not selected an order status – then no passport would ever be created. We have restored the recommendation “Processing” and “Completed”.', 'codefokus-handwerk-produktpass' ) );
				}
				if ( false === strpos( $out['serial_format'], '{order}' ) && false === strpos( $out['serial_format'], '{rand}' ) ) {
					$out['serial_format'] .= '-{order}-{pos}';
					self::flash( 'warning', __( 'The serial number format must contain {order} or {rand} so that every number is unique. We have added “-{order}-{pos}”.', 'codefokus-handwerk-produktpass' ) );
				}
				break;

			case 'delivery':
				foreach ( array( 'email_links', 'account_links' ) as $k ) {
					$out[ $k ] = empty( $in[ $k ] ) ? 0 : 1;
				}
				$slug               = sanitize_title( isset( $in['public_slug'] ) ? $in['public_slug'] : '' );
				$out['public_slug'] = $slug ? $slug : 'produktpass';
				if ( $out['public_slug'] !== HWPP_Settings::get( 'public_slug' ) ) {
					update_option( 'hwpp_flush_rewrite', 1 );
				}
				break;

			case 'advanced':
				$out['delete_on_uninstall'] = empty( $in['delete_on_uninstall'] ) ? 0 : 1;
				break;
		}

		/**
		 * Sanitised values of a settings tab before they are saved.
		 *
		 * @param array  $out Values to save.
		 * @param string $tab Tab key.
		 * @param array  $in  Raw input (unslashed, not sanitised).
		 */
		$out = (array) apply_filters( 'hwpp_save_settings', $out, $tab, $in );
		HWPP_Settings::update( $out );
		if ( ! get_transient( 'hwpp_flash_' . get_current_user_id() ) ) {
			self::flash( 'success', __( 'Settings saved.', 'codefokus-handwerk-produktpass' ) );
		}
		wp_safe_redirect( admin_url( 'admin.php?page=hwpp-settings&tab=' . $tab ) );
		exit;
	}

	/* =====================================================================
	 * Testmodus
	 * ===================================================================== */

	public static function page_test() {
		$products = wc_get_products( array( 'status' => array( 'publish', 'draft', 'private' ), 'limit' => 300, 'orderby' => 'title', 'order' => 'ASC', 'type' => array( 'simple', 'variable', 'external', 'grouped' ) ) );
		$tests    = HWPP_Repository::latest( 20, 0, '', true );
		$result   = get_transient( 'hwpp_test_result_' . get_current_user_id() );
		if ( $result ) {
			delete_transient( 'hwpp_test_result_' . get_current_user_id() );
		}
		?>
		<div class="wrap hwpp-wrap">
			<h1><?php esc_html_e( 'Test mode', 'codefokus-handwerk-produktpass' ); ?> <?php HWPP_Glossary::e( 'testmodus' ); ?></h1>
			<div class="hwpp-panel">
				<h2><?php esc_html_e( 'Simulate test purchase', 'codefokus-handwerk-produktpass' ); ?></h2>
				<p><?php esc_html_e( 'Runs through the complete process of an order: required field check → passport creation with checksum and signature → web passport → QR code → certificate PDF. No order is created, no email is sent and nothing is charged.', 'codefokus-handwerk-produktpass' ); ?></p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="hwpp-inline-form">
					<?php wp_nonce_field( 'hwpp_test_generate' ); ?>
					<input type="hidden" name="action" value="hwpp_test_generate">
					<select name="product_id" class="wc-enhanced-select" style="min-width:320px" required>
						<option value=""><?php esc_html_e( '– Choose product –', 'codefokus-handwerk-produktpass' ); ?></option>
						<?php foreach ( $products as $product ) : ?>
							<?php $missing = HWPP_Fields::missing_required( $product->get_id() ); ?>
							<option value="<?php echo (int) $product->get_id(); ?>"><?php echo esc_html( $product->get_name() . ( $missing ? ' ⚠' : ' ✓' ) ); ?></option>
						<?php endforeach; ?>
					</select>
					<button class="button button-primary"><?php esc_html_e( 'Generate test passport', 'codefokus-handwerk-produktpass' ); ?></button>
				</form>
				<p class="description">✓ = <?php esc_html_e( 'complete', 'codefokus-handwerk-produktpass' ); ?> · ⚠ = <?php esc_html_e( 'Required information missing (the test shows you exactly which)', 'codefokus-handwerk-produktpass' ); ?></p>
			</div>

			<?php if ( $result ) : ?>
				<?php if ( ! empty( $result['error'] ) ) : ?>
					<div class="hwpp-panel hwpp-test-result is-error">
						<h2>✗ <?php esc_html_e( 'Test failed', 'codefokus-handwerk-produktpass' ); ?></h2>
						<p><?php echo esc_html( $result['error'] ); ?></p>
						<?php if ( ! empty( $result['product_id'] ) ) : ?>
							<p><a class="button button-primary" href="<?php echo esc_url( get_edit_post_link( $result['product_id'] ) ); ?>"><?php esc_html_e( 'Complete product now', 'codefokus-handwerk-produktpass' ); ?></a></p>
						<?php endif; ?>
					</div>
				<?php else : ?>
					<?php $row = HWPP_Repository::find_by_uid( $result['uid'] ); ?>
					<?php if ( $row ) : ?>
						<?php $verify = HWPP_Integrity::verify( $row ); ?>
						<div class="hwpp-panel hwpp-test-result is-ok">
							<h2>✓ <?php esc_html_e( 'Test successful – this is what a real order looks like', 'codefokus-handwerk-produktpass' ); ?></h2>
							<div class="hwpp-test-grid">
								<div>
									<ol class="hwpp-checks">
										<li>✓ <?php esc_html_e( 'Required fields complete', 'codefokus-handwerk-produktpass' ); ?></li>
										<li>✓ <?php echo esc_html( sprintf( /* translators: %s: serial */ __( 'Passport created: %s', 'codefokus-handwerk-produktpass' ), $row->serial ) ); ?></li>
										<li><?php echo $verify['valid'] ? '✓' : '✗'; ?> <?php esc_html_e( 'Checksum and signature', 'codefokus-handwerk-produktpass' ); ?>: <code><?php echo esc_html( HWPP_Integrity::fingerprint( $row->hash ) ); ?></code></li>
										<li>✓ <?php esc_html_e( 'QR code generated (on the right – scan it with your phone!)', 'codefokus-handwerk-produktpass' ); ?></li>
									</ol>
									<p>
										<a class="button button-primary" target="_blank" href="<?php echo esc_url( HWPP_Repository::public_url( $row ) ); ?>"><?php esc_html_e( 'Open web passport', 'codefokus-handwerk-produktpass' ); ?></a>
										<?php if ( HWPP_Repository::certificate_url( $row ) ) : ?>
											<a class="button" target="_blank" href="<?php echo esc_url( HWPP_Repository::certificate_url( $row ) ); ?>"><?php esc_html_e( 'Open certificate (PDF)', 'codefokus-handwerk-produktpass' ); ?></a>
										<?php endif; ?>
										<a class="button" href="<?php echo esc_url( self::qr_download_url( $row ) ); ?>"><?php esc_html_e( 'Download QR code (PNG)', 'codefokus-handwerk-produktpass' ); ?></a>
									</p>
									<?php
									/**
									 * Fires below a successful test result.
									 *
									 * @param object $row Test passport.
									 */
									do_action( 'hwpp_test_result', $row );
									?>
								</div>
								<div class="hwpp-test-qr"><?php echo HWPP_QR::svg( HWPP_Repository::public_url( $row ), 180 ); // phpcs:ignore WordPress.Security.EscapeOutput ?></div>
							</div>
						</div>
					<?php endif; ?>
				<?php endif; ?>
			<?php endif; ?>

			<div class="hwpp-panel">
				<div class="hwpp-panel-head">
					<h2><?php esc_html_e( 'Previous test passports', 'codefokus-handwerk-produktpass' ); ?></h2>
					<?php if ( $tests ) : ?>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
							<?php wp_nonce_field( 'hwpp_test_delete' ); ?>
							<input type="hidden" name="action" value="hwpp_test_delete">
							<button class="button button-link-delete" onclick="return confirm('<?php echo esc_js( __( 'Delete all test passports? Real passports remain untouched.', 'codefokus-handwerk-produktpass' ) ); ?>');"><?php esc_html_e( 'Delete all test passports', 'codefokus-handwerk-produktpass' ); ?></button>
						</form>
					<?php endif; ?>
				</div>
				<?php if ( ! $tests ) : ?>
					<p class="hwpp-empty"><?php esc_html_e( 'No test passports yet.', 'codefokus-handwerk-produktpass' ); ?></p>
				<?php else : ?>
					<table class="widefat striped hwpp-table"><tbody>
						<?php foreach ( $tests as $row ) : ?>
							<tr><td><code><?php echo esc_html( $row->serial ); ?></code></td><td><?php echo esc_html( HWPP_Public_View::format_date( $row->created_at ) ); ?></td>
							<td><a target="_blank" href="<?php echo esc_url( HWPP_Repository::public_url( $row ) ); ?>"><?php esc_html_e( 'Passport', 'codefokus-handwerk-produktpass' ); ?></a> · <?php if ( HWPP_Repository::certificate_url( $row ) ) : ?><a target="_blank" href="<?php echo esc_url( HWPP_Repository::certificate_url( $row ) ); ?>"><?php esc_html_e( 'PDF', 'codefokus-handwerk-produktpass' ); ?></a> · <?php endif; ?><a href="<?php echo esc_url( self::qr_download_url( $row ) ); ?>"><?php esc_html_e( 'QR code (PNG)', 'codefokus-handwerk-produktpass' ); ?></a></td></tr>
						<?php endforeach; ?>
					</tbody></table>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	public static function handle_test_generate() {
		self::require_cap();
		check_admin_referer( 'hwpp_test_generate' );
		$product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
		$res        = HWPP_Generator::generate_test( $product_id );
		$product    = wc_get_product( $product_id );
		$edit_id    = $product ? ( $product->get_parent_id() ? $product->get_parent_id() : $product->get_id() ) : 0;
		set_transient(
			'hwpp_test_result_' . get_current_user_id(),
			is_wp_error( $res ) ? array( 'error' => $res->get_error_message(), 'product_id' => $edit_id ) : array( 'uid' => $res->uid ),
			300
		);
		wp_safe_redirect( admin_url( 'admin.php?page=hwpp-test' ) );
		exit;
	}

	public static function handle_test_delete() {
		self::guard( 'hwpp_test_delete' );
		$n = HWPP_Repository::delete_tests();
		/* translators: %d: count */
		self::flash( 'success', sprintf( _n( '%d test passport deleted.', '%d test passports deleted.', $n, 'codefokus-handwerk-produktpass' ), $n ) );
		wp_safe_redirect( admin_url( 'admin.php?page=hwpp-test' ) );
		exit;
	}
}
