<?php
/**
 * Hints about the Pro add-on.
 *
 * Shown only on the plugin's own admin pages (and as a link in the plugin list),
 * never site-wide. The one notice is dismissible. Nothing in the free plugin is
 * locked: Pro features are not part of this plugin, they come with the add-on.
 *
 * @package HandwerkProduktpass
 */

defined( 'ABSPATH' ) || exit;

class HWPP_Upsell {

	const DISMISS_META = 'hwpp_upsell_dismissed';

	public static function init() {
		if ( HWPP_Plugin::is_pro() ) {
			return;
		}
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 99 );
		add_filter( 'plugin_action_links_' . plugin_basename( HWPP_FILE ), array( __CLASS__, 'action_link' ), 20 );
		add_filter( 'hwpp_settings_tabs', array( __CLASS__, 'tabs' ) );
		add_filter( 'hwpp_settings_tab_has_form', array( __CLASS__, 'tab_has_form' ), 10, 2 );
		add_action( 'hwpp_settings_tab_content', array( __CLASS__, 'tab_content' ) );
		add_action( 'hwpp_settings_rows', array( __CLASS__, 'settings_rows' ) );
		add_action( 'hwpp_admin_sidebar', array( __CLASS__, 'sidebar' ) );
		add_action( 'hwpp_overview_after_stats', array( __CLASS__, 'overview' ) );
		add_action( 'hwpp_test_result', array( __CLASS__, 'test_result' ) );
		add_action( 'admin_notices', array( __CLASS__, 'notice' ) );
		add_action( 'admin_post_hwpp_upsell_dismiss', array( __CLASS__, 'handle_dismiss' ) );
	}

	/* ------------------------------------------------------------------
	 * Building blocks
	 * ------------------------------------------------------------------ */

	/**
	 * Small "Pro" link, e.g. next to a feature that the add-on provides.
	 *
	 * @param string $placement Campaign marker.
	 * @param string $label     Visible text.
	 */
	public static function inline_badge( $placement, $label = '' ) {
		if ( HWPP_Plugin::is_pro() ) {
			return;
		}
		echo '<a class="hwpp-pro-link" href="' . esc_url( HWPP_Plugin::pro_url() ) . '" target="_blank" rel="noopener">'
			. ( $label ? esc_html( $label ) . ' ' : '' ) . '<span class="hwpp-pro-tag">Pro</span></a>';
	}

	/**
	 * Call-to-action button.
	 *
	 * @param string $placement Campaign marker.
	 * @param string $label     Button text.
	 * @param string $class     Extra CSS class.
	 */
	private static function button( $placement, $label = '', $class = '' ) {
		$label = $label ? $label : __( 'Get Pro now', 'codefokus-handwerk-produktpass' );
		return '<a class="button button-primary hwpp-pro-btn ' . esc_attr( $class ) . '" href="' . esc_url( HWPP_Plugin::pro_url() ) . '" target="_blank" rel="noopener">' . esc_html( $label ) . ' →</a>';
	}

	/** Image shipped with the plugin (examples of Pro output). */
	private static function image( $file, $alt ) {
		return '<img class="hwpp-pro-img" src="' . esc_url( HWPP_URL . 'assets/pro/' . $file ) . '" alt="' . esc_attr( $alt ) . '" loading="lazy">';
	}

	/**
	 * All Pro features: key => [title, text].
	 */
	public static function features() {
		return array(
			'design'      => array( __( 'Your design: logo, colours and font', 'codefokus-handwerk-produktpass' ), __( 'The passport page in your look – with logo, brand colours, font, your own title and the order of the sections.', 'codefokus-handwerk-produktpass' ) ),
			'multilingual' => array( __( 'Multilingual passports (WPML & Polylang)', 'codefokus-handwerk-produktpass' ), __( 'Every passport in all languages of your shop – customers see it in their language automatically.', 'codefokus-handwerk-produktpass' ) ),
			'certificate' => array( __( 'Certificate of authenticity (PDF)', 'codefokus-handwerk-produktpass' ), __( 'With your logo, signature and workshop stamp. Your customers download it from the passport – or you put it in the parcel.', 'codefokus-handwerk-produktpass' ) ),
			'engraving'   => array( __( 'Engraving template & labels', 'codefokus-handwerk-produktpass' ), __( 'SVG template for laser and CNC, single labels and A4 label sheets – the QR code goes straight onto the piece.', 'codefokus-handwerk-produktpass' ) ),
			'invoice'     => array( __( 'QR code on invoice & packing slip', 'codefokus-handwerk-produktpass' ), __( 'Printed automatically below every item – no extra step when packing.', 'codefokus-handwerk-produktpass' ) ),
			'reminders'   => array( __( 'Care reminders that bring customers back', 'codefokus-handwerk-produktpass' ), __( 'After the care interval your customer receives your care tips by email – with a link to your care kit or repair service.', 'codefokus-handwerk-produktpass' ) ),
			'model'       => array( __( 'Product passport before the purchase', 'codefokus-handwerk-produktpass' ), __( 'A “Product passport” tab on the product page shows materials, origin and care where the buying decision is made.', 'codefokus-handwerk-produktpass' ) ),
			'stats'       => array( __( 'Scan statistics', 'codefokus-handwerk-produktpass' ), __( 'See how often your passports are opened and which pieces are scanned most.', 'codefokus-handwerk-produktpass' ) ),
			'tools'       => array( __( 'CSV import/export & bulk editing', 'codefokus-handwerk-produktpass' ), __( 'Fill in hundreds of products in minutes instead of clicking through them one by one.', 'codefokus-handwerk-produktpass' ) ),
			'anchor'      => array( __( 'Independent timestamp (RFC 3161)', 'codefokus-handwerk-produktpass' ), __( 'Daily receipt from an external timestamp service: proof that your passports have not been changed since.', 'codefokus-handwerk-produktpass' ) ),
			'archive'     => array( __( 'Passport archive as a website', 'codefokus-handwerk-produktpass' ), __( 'Export all passports as a self-contained website – they stay available even if your shop changes.', 'codefokus-handwerk-produktpass' ) ),
			'api'         => array( __( 'REST API, JSON-LD & GS1 Digital Link', 'codefokus-handwerk-produktpass' ), __( 'Structured data for search engines, merchandise management and marketplaces.', 'codefokus-handwerk-produktpass' ) ),
		);
	}

	/* ------------------------------------------------------------------
	 * Menu & plugin list
	 * ------------------------------------------------------------------ */

	public static function menu() {
		add_submenu_page( 'hwpp', __( 'Template designer', 'codefokus-handwerk-produktpass' ), esc_html__( 'Template designer', 'codefokus-handwerk-produktpass' ) . ' <span class="hwpp-pro-tag">Pro</span>', HWPP_Admin::CAP, 'hwpp-design', array( __CLASS__, 'design_page' ), 2 );
		add_submenu_page( 'hwpp', __( 'Handwerk Produktpass Pro', 'codefokus-handwerk-produktpass' ), '<span class="hwpp-menu-pro">★ ' . esc_html__( 'Upgrade to Pro', 'codefokus-handwerk-produktpass' ) . '</span>', HWPP_Admin::CAP, 'hwpp-pro', array( __CLASS__, 'page' ) );
	}

	public static function action_link( $links ) {
		$links['hwpp_pro'] = '<a href="' . esc_url( HWPP_Plugin::pro_url() ) . '" target="_blank" rel="noopener" style="color:#1a7f37;font-weight:600">' . esc_html__( 'Get Pro', 'codefokus-handwerk-produktpass' ) . '</a>';
		return $links;
	}

	/* ------------------------------------------------------------------
	 * Pro page
	 * ------------------------------------------------------------------ */

	public static function page() {
		$f = self::features();
		?>
		<div class="wrap hwpp-wrap hwpp-pro-page">
			<section class="hwpp-pro-hero">
				<div class="hwpp-pro-hero__text">
					<p class="hwpp-pro-eyebrow"><?php esc_html_e( 'Handwerk Produktpass Pro', 'codefokus-handwerk-produktpass' ); ?></p>
					<h1><?php esc_html_e( 'Make every piece look as valuable as it is.', 'codefokus-handwerk-produktpass' ); ?></h1>
					<p class="hwpp-pro-lead"><?php esc_html_e( 'The free version gives every piece its digital passport. Pro turns it into something your customers can hold: a certificate of authenticity with your signature and workshop stamp, an engraving template for the QR code and ready-to-print labels – created automatically with every order.', 'codefokus-handwerk-produktpass' ); ?></p>
					<p class="hwpp-pro-cta"><?php echo wp_kses( self::button( 'pro-page-hero' ), HWPP_Admin::kses() ); ?> <a class="button hwpp-pro-btn-ghost" href="#hwpp-compare"><?php esc_html_e( 'Compare features', 'codefokus-handwerk-produktpass' ); ?></a></p>
					<p class="hwpp-pro-small"><?php esc_html_e( 'Installs alongside the free version. All passports you have already issued stay exactly as they are.', 'codefokus-handwerk-produktpass' ); ?></p>
				</div>
				<div class="hwpp-pro-hero__img"><?php echo wp_kses( self::image( 'certificate.jpg', __( 'Example certificate of authenticity', 'codefokus-handwerk-produktpass' ) ), HWPP_Admin::kses() ); ?></div>
			</section>

			<section class="hwpp-pro-why">
				<h2><?php esc_html_e( 'Why craft businesses choose Pro', 'codefokus-handwerk-produktpass' ); ?></h2>
				<div class="hwpp-pro-why__grid">
					<div><strong><?php esc_html_e( 'Your price needs a reason.', 'codefokus-handwerk-produktpass' ); ?></strong><p><?php esc_html_e( 'A signed certificate with serial number shows in black and white what makes your piece different from mass production.', 'codefokus-handwerk-produktpass' ); ?></p></div>
					<div><strong><?php esc_html_e( 'No extra work when packing.', 'codefokus-handwerk-produktpass' ); ?></strong><p><?php esc_html_e( 'Certificate, invoice QR code and labels are created with the order. You print – done.', 'codefokus-handwerk-produktpass' ); ?></p></div>
					<div><strong><?php esc_html_e( 'Customers come back.', 'codefokus-handwerk-produktpass' ); ?></strong><p><?php esc_html_e( 'Care reminders bring your workshop back to mind exactly when care, repairs or the next piece are due.', 'codefokus-handwerk-produktpass' ); ?></p></div>
				</div>
			</section>

			<section class="hwpp-pro-features">
				<?php foreach ( $f as $key => $item ) : ?>
					<div class="hwpp-pro-feature">
						<h3><?php echo esc_html( $item[0] ); ?></h3>
						<p><?php echo esc_html( $item[1] ); ?></p>
						<?php if ( 'engraving' === $key ) : ?>
							<div class="hwpp-pro-feature__imgs"><?php echo wp_kses( self::image( 'engraving.png', __( 'Example engraving template', 'codefokus-handwerk-produktpass' ) ) . self::image( 'label.png', __( 'Example label', 'codefokus-handwerk-produktpass' ) ), HWPP_Admin::kses() ); ?></div>
						<?php endif; ?>
					</div>
				<?php endforeach; ?>
			</section>

			<section class="hwpp-pro-compare" id="hwpp-compare">
				<h2><?php esc_html_e( 'Free and Pro compared', 'codefokus-handwerk-produktpass' ); ?></h2>
				<table class="widefat hwpp-pro-table">
					<thead><tr><th></th><th><?php esc_html_e( 'Free', 'codefokus-handwerk-produktpass' ); ?></th><th class="is-pro"><?php esc_html_e( 'Pro', 'codefokus-handwerk-produktpass' ); ?></th></tr></thead>
					<tbody>
						<?php
						$free_rows = array(
							__( 'Digital product passport per piece with QR code', 'codefokus-handwerk-produktpass' ),
							__( 'Automatic creation with every order', 'codefokus-handwerk-produktpass' ),
							__( 'Tamper-evident signature and hash chain', 'codefokus-handwerk-produktpass' ),
							__( 'Material library, trade presets and required fields', 'codefokus-handwerk-produktpass' ),
							__( 'Corrections, status (refund, recall) and test mode', 'codefokus-handwerk-produktpass' ),
							__( 'Clean standard design for the passport page', 'codefokus-handwerk-produktpass' ),
							__( 'QR code download (PNG/SVG)', 'codefokus-handwerk-produktpass' ),
						);
						foreach ( $free_rows as $label ) {
							echo '<tr><td>' . esc_html( $label ) . '</td><td class="yes">✓</td><td class="yes is-pro">✓</td></tr>';
						}
						foreach ( $f as $item ) {
							echo '<tr><td>' . esc_html( $item[0] ) . '</td><td class="no">–</td><td class="yes is-pro">✓</td></tr>';
						}
						?>
						<tr><td><?php esc_html_e( 'Plugin updates', 'codefokus-handwerk-produktpass' ); ?></td><td class="yes">✓</td><td class="yes is-pro">✓</td></tr>
					</tbody>
				</table>
			</section>

			<section class="hwpp-pro-final">
				<h2><?php esc_html_e( 'Your customers pay for craftsmanship. Show it to them.', 'codefokus-handwerk-produktpass' ); ?></h2>
				<p><?php echo wp_kses( self::button( 'pro-page-bottom', __( 'Get Handwerk Produktpass Pro', 'codefokus-handwerk-produktpass' ), 'button-hero' ), HWPP_Admin::kses() ); ?></p>
				<p class="hwpp-pro-small"><?php esc_html_e( 'Purchase and licence on codefokus.de. After the purchase you receive the Pro add-on as a ZIP file and your licence key by email.', 'codefokus-handwerk-produktpass' ); ?></p>
			</section>
		</div>
		<?php
	}

	/**
	 * "Template designer" in the free version: shows what the designer in Pro does.
	 */
	public static function design_page() {
		$f = self::features();
		?>
		<div class="wrap hwpp-wrap">
			<h1><?php esc_html_e( 'Template designer', 'codefokus-handwerk-produktpass' ); ?> <span class="hwpp-pro-tag">Pro</span></h1>
			<div class="hwpp-layout">
				<div class="hwpp-layout__main">
					<div class="hwpp-panel hwpp-pro-panel">
						<h2><?php esc_html_e( 'Make the passport look like your workshop', 'codefokus-handwerk-produktpass' ); ?></h2>
						<p class="hwpp-pro-lead"><?php esc_html_e( 'The free version shows every passport in a clean standard design. With the designer in Pro, the passport page and the certificate carry your logo, your colours and your signature – so customers recognise your brand at first glance.', 'codefokus-handwerk-produktpass' ); ?></p>
						<ul class="hwpp-pro-checks">
							<?php
							foreach ( array(
								__( 'Logo in the header of the passport and on the certificate', 'codefokus-handwerk-produktpass' ),
								__( 'Primary colour, accent colour and background', 'codefokus-handwerk-produktpass' ),
								__( 'Classic or modern font', 'codefokus-handwerk-produktpass' ),
								__( 'Order and visibility of the sections – online and on the certificate', 'codefokus-handwerk-produktpass' ),
								__( 'Your own passport title, certificate title and text', 'codefokus-handwerk-produktpass' ),
								__( 'Signature and workshop stamp as image', 'codefokus-handwerk-produktpass' ),
								__( 'Live preview while you design', 'codefokus-handwerk-produktpass' ),
							) as $line ) {
								echo '<li>' . esc_html( $line ) . '</li>';
							}
							?>
						</ul>
						<p><?php echo wp_kses( self::button( 'designer-page' ), HWPP_Admin::kses() ); ?></p>
						<?php echo wp_kses( self::image( 'passport.jpg', __( 'Example passport page in your own design', 'codefokus-handwerk-produktpass' ) ), HWPP_Admin::kses() ); ?>
					</div>
				</div>
				<aside class="hwpp-layout__side">
					<div class="hwpp-pro-box">
						<?php echo wp_kses( self::image( 'certificate.jpg', __( 'Example certificate of authenticity', 'codefokus-handwerk-produktpass' ) ), HWPP_Admin::kses() ); ?>
						<h3><?php esc_html_e( 'Your design – on paper too', 'codefokus-handwerk-produktpass' ); ?></h3>
						<p><?php esc_html_e( 'With Pro, every passport also becomes a certificate of authenticity in your colours, with your signature and workshop stamp.', 'codefokus-handwerk-produktpass' ); ?></p>
						<?php echo wp_kses( self::button( 'designer-sidebar' ), HWPP_Admin::kses() ); ?>
					</div>
				</aside>
			</div>
		</div>
		<?php
	}

	/* ------------------------------------------------------------------
	 * Settings
	 * ------------------------------------------------------------------ */

	public static function tabs( $tabs ) {
		$tabs['reminders'] = esc_html__( 'Care reminders', 'codefokus-handwerk-produktpass' ) . ' <span class="hwpp-pro-tag">Pro</span>';
		$tabs['pro']       = '★ ' . esc_html__( 'Pro', 'codefokus-handwerk-produktpass' );
		return $tabs;
	}

	public static function tab_has_form( $has_form, $tab ) {
		return in_array( $tab, array( 'reminders', 'pro' ), true ) ? false : $has_form;
	}

	public static function tab_content( $tab ) {
		if ( 'pro' === $tab ) {
			echo '<div class="hwpp-panel hwpp-pro-panel">';
			echo '<h2>' . esc_html__( 'Everything that makes your passport tangible', 'codefokus-handwerk-produktpass' ) . '</h2>';
			self::feature_list( array_keys( self::features() ) );
			echo wp_kses( '<p>' . self::button( 'settings-tab' ) . ' <a class="button" href="' . esc_url( admin_url( 'admin.php?page=hwpp-pro' ) ) . '">' . esc_html__( 'Compare features', 'codefokus-handwerk-produktpass' ) . '</a></p>', HWPP_Admin::kses() );
			echo '</div>';
			return;
		}
		if ( 'reminders' === $tab ) {
			$f = self::features();
			echo '<div class="hwpp-panel hwpp-pro-panel">';
			echo '<h2>' . esc_html( $f['reminders'][0] ) . ' <span class="hwpp-pro-tag">Pro</span></h2>';
			echo '<p class="hwpp-pro-lead">' . esc_html__( 'A well-cared-for piece is the best advertisement for your workshop. With Pro, customers who agreed at checkout automatically receive the care instructions from their product passport after the care interval – with a button to your care kit or repair service.', 'codefokus-handwerk-produktpass' ) . '</p>';
			echo '<ul class="hwpp-pro-checks">';
			foreach ( array(
				__( 'Consent checkbox at checkout (unchecked by default, GDPR-friendly)', 'codefokus-handwerk-produktpass' ),
				__( 'Interval per product, e.g. oil the tabletop every 12 months', 'codefokus-handwerk-produktpass' ),
				__( 'Your own subject, text and offer link', 'codefokus-handwerk-produktpass' ),
				__( 'One-click unsubscribe in every email', 'codefokus-handwerk-produktpass' ),
			) as $line ) {
				echo '<li>' . esc_html( $line ) . '</li>';
			}
			echo wp_kses( '</ul><p>' . self::button( 'reminders-tab' ) . '</p></div>', HWPP_Admin::kses() );
		}
	}

	/**
	 * Bulleted list of Pro features.
	 *
	 * @param string[] $keys Feature keys.
	 */
	private static function feature_list( array $keys ) {
		$f = self::features();
		echo '<ul class="hwpp-pro-checks">';
		foreach ( $keys as $key ) {
			if ( isset( $f[ $key ] ) ) {
				echo '<li><strong>' . esc_html( $f[ $key ][0] ) . '</strong><br><span>' . esc_html( $f[ $key ][1] ) . '</span></li>';
			}
		}
		echo '</ul>';
	}

	public static function settings_rows( $tab ) {
		$map = array(
			'delivery' => array( array( 'invoice', 'engraving', 'model', 'api' ), __( 'More ways to deliver the passport', 'codefokus-handwerk-produktpass' ) ),
			'advanced' => array( array( 'anchor', 'tools', 'archive' ), __( 'More protection and less typing', 'codefokus-handwerk-produktpass' ) ),
		);
		if ( ! isset( $map[ $tab ] ) ) {
			return;
		}
		echo '<tr class="hwpp-pro-row"><th scope="row">' . esc_html( $map[ $tab ][1] ) . ' <span class="hwpp-pro-tag">Pro</span></th><td>';
		self::feature_list( $map[ $tab ][0] );
		echo wp_kses( self::button( 'settings-' . $tab ) . '</td></tr>', HWPP_Admin::kses() );
	}

	public static function sidebar( $context ) {
		echo '<div class="hwpp-pro-box">';
		echo '<p class="hwpp-pro-eyebrow">' . esc_html__( 'Handwerk Produktpass Pro', 'codefokus-handwerk-produktpass' ) . '</p>';
		echo '<h3>' . esc_html__( 'More than a passport', 'codefokus-handwerk-produktpass' ) . '</h3>';
		echo '<ul class="hwpp-pro-checks is-compact">';
		foreach ( array( 'design', 'certificate', 'engraving', 'invoice', 'reminders', 'multilingual' ) as $key ) {
			echo '<li>' . esc_html( self::features()[ $key ][0] ) . '</li>';
		}
		echo wp_kses( '</ul>' . self::button( 'settings-sidebar' ), HWPP_Admin::kses() );
		echo '<p class="hwpp-pro-small">' . esc_html__( 'Installs alongside the free version.', 'codefokus-handwerk-produktpass' ) . '</p></div>';
	}

	/* ------------------------------------------------------------------
	 * Overview, designer, test mode, orders
	 * ------------------------------------------------------------------ */

	public static function overview() {
		echo '<div class="hwpp-panel hwpp-pro-strip"><div><strong>' . esc_html__( 'How often are your passports scanned?', 'codefokus-handwerk-produktpass' ) . '</strong> '
			. esc_html__( 'Pro shows scans per day and your most viewed pieces – and gives every passport a certificate of authenticity as PDF.', 'codefokus-handwerk-produktpass' ) . '</div>'
			. wp_kses( self::button( 'overview' ), HWPP_Admin::kses() ) . '</div>';
	}

	public static function test_result() {
		echo '<div class="hwpp-pro-test">';
		echo wp_kses( '<div>' . self::image( 'certificate.jpg', __( 'Example certificate of authenticity', 'codefokus-handwerk-produktpass' ) ) . '</div>', HWPP_Admin::kses() );
		echo '<div><h3>' . esc_html__( 'Something your customers can hold in their hands', 'codefokus-handwerk-produktpass' ) . '</h3>';
		echo '<p>' . esc_html__( 'With Pro, this test passport would also come with a certificate of authenticity (PDF) with your signature and stamp, an engraving template, a label – and the QR code would be printed on the invoice.', 'codefokus-handwerk-produktpass' ) . '</p>';
		echo wp_kses( self::button( 'test-result' ) . '</div></div>', HWPP_Admin::kses() );
	}

	/* ------------------------------------------------------------------
	 * One dismissible notice on the plugin's own pages
	 * ------------------------------------------------------------------ */

	private static function is_plugin_screen() {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		return 0 === strpos( $page, 'hwpp' ) && 'hwpp-pro' !== $page;
	}

	public static function notice() {
		if ( ! self::is_plugin_screen() || ! current_user_can( HWPP_Admin::CAP ) || get_user_meta( get_current_user_id(), self::DISMISS_META, true ) ) {
			return;
		}
		$count = HWPP_Repository::count( false );
		if ( $count < 3 ) {
			return;
		}
		$dismiss = wp_nonce_url( admin_url( 'admin-post.php?action=hwpp_upsell_dismiss' ), 'hwpp_upsell_dismiss' );
		echo '<div class="notice notice-info hwpp-pro-notice"><p><strong>'
			/* translators: %s: number of passports */
			. esc_html( sprintf( _n( 'You have already issued %s product passport.', 'You have already issued %s product passports.', $count, 'codefokus-handwerk-produktpass' ), number_format_i18n( $count ) ) )
			. '</strong> ' . esc_html__( 'Give your customers something to hold: with Pro they also receive a certificate of authenticity with your signature and workshop stamp.', 'codefokus-handwerk-produktpass' )
			. '</p><p>' . wp_kses( self::button( 'notice', __( 'Show me Pro', 'codefokus-handwerk-produktpass' ) ), HWPP_Admin::kses() )
			. ' <a class="button-link" href="' . esc_url( $dismiss ) . '">' . esc_html__( 'No thanks, hide this', 'codefokus-handwerk-produktpass' ) . '</a></p></div>';
	}

	public static function handle_dismiss() {
		if ( ! current_user_can( HWPP_Admin::CAP ) ) {
			wp_die( esc_html__( 'You do not have permission for this action. Please log in as shop manager or administrator.', 'codefokus-handwerk-produktpass' ) );
		}
		check_admin_referer( 'hwpp_upsell_dismiss' );
		update_user_meta( get_current_user_id(), self::DISMISS_META, time() );
		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url( 'admin.php?page=hwpp' ) );
		exit;
	}
}
