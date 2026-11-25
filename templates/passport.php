<?php
/**
 * Template: public view of a digital product passport.
 *
 * Can be overridden in the theme: wp-content/themes/YOUR-THEME/hwpp/passport.php
 *
 * @package HandwerkProduktpass
 * @var array $view
 */

defined( 'ABSPATH' ) || exit;

$hwpp_d        = $view['design'];
$hwpp_c        = $view['c'];
$hwpp_p        = $view['payload'];
$hwpp_row      = $view['row'];
$hwpp_texts    = isset( $hwpp_c['texts'] ) ? $hwpp_c['texts'] : array();
$hwpp_title    = ! empty( $hwpp_texts['passport_title'] ) ? $hwpp_texts['passport_title'] : $hwpp_d['passport_title'];
$hwpp_name     = isset( $hwpp_c['name'] ) ? $hwpp_c['name'] : '';
$hwpp_labels   = HWPP_Fields::sections();
$hwpp_logo     = ! empty( $hwpp_d['logo_id'] ) ? wp_get_attachment_image_url( (int) $hwpp_d['logo_id'], 'medium' ) : '';
$hwpp_is_test  = ! empty( $hwpp_row->is_test );
$hwpp_valid    = $view['verify']['valid'];
$hwpp_inactive = 'active' !== $view['status'];
$hwpp_font     = 'sans' === $hwpp_d['font'] ? 'system-ui,-apple-system,"Segoe UI",Roboto,sans-serif' : 'Georgia,"Iowan Old Style","Palatino Linotype",serif';
$hwpp_company  = isset( $hwpp_p['manufacturer']['name'] ) ? $hwpp_p['manufacturer']['name'] : '';
?><!doctype html>
<html lang="<?php echo esc_attr( $view['lang'] ); ?>">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="robots" content="noindex, nofollow">
	<title><?php echo esc_html( $hwpp_title . ' – ' . $hwpp_name ); ?></title>
	<?php
	/**
	 * Fires inside <head> of the passport page (e.g. structured data).
	 *
	 * @param array $view Prepared passport data.
	 */
	do_action( 'hwpp_passport_head', $view );
	?>
	<?php
	// Standalone page without theme: print the plugin stylesheet and the design colours directly.
	wp_register_style( 'hwpp-public', HWPP_URL . 'assets/public.css', array(), HWPP_VERSION );
	wp_add_inline_style( 'hwpp-public', ':root{--hwpp-primary:' . sanitize_hex_color( $hwpp_d['primary_color'] ) . ';--hwpp-accent:' . sanitize_hex_color( $hwpp_d['accent_color'] ) . ';--hwpp-bg:' . sanitize_hex_color( $hwpp_d['background'] ) . ';--hwpp-heading-font:' . $hwpp_font . ';}' );
	wp_print_styles( array( 'hwpp-public' ) );
	?>
</head>
<body class="hwpp-page">
<?php if ( $hwpp_is_test ) : ?>
	<div class="hwpp-testbar"><?php esc_html_e( 'TEST PASSPORT – created in test mode, not linked to a real order.', 'codefokus-handwerk-produktpass' ); ?></div>
<?php endif; ?>

<?php if ( $view['notice'] ) : ?>
	<div class="hwpp-statusbar hwpp-statusbar--<?php echo esc_attr( $view['status'] ); ?>" role="alert">
		<?php echo esc_html( $view['notice'] ); ?>
		<?php if ( $view['newer_url'] ) : ?>
			<a href="<?php echo esc_url( $view['newer_url'] ); ?>"><?php esc_html_e( 'Go to the valid version →', 'codefokus-handwerk-produktpass' ); ?></a>
		<?php endif; ?>
	</div>
<?php endif; ?>

<header class="hwpp-header">
	<div class="hwpp-wrap hwpp-header__inner">
		<div class="hwpp-brand">
			<?php if ( $hwpp_logo ) : ?>
				<img src="<?php echo esc_url( $hwpp_logo ); ?>" alt="<?php echo esc_attr( $hwpp_company ); ?>" class="hwpp-logo">
			<?php else : ?>
				<span class="hwpp-brand__name"><?php echo esc_html( $hwpp_company ); ?></span>
			<?php endif; ?>
		</div>
		<?php if ( count( $view['languages'] ) > 1 ) : ?>
			<nav class="hwpp-langs" aria-label="<?php esc_attr_e( 'Language', 'codefokus-handwerk-produktpass' ); ?>">
				<?php foreach ( $view['languages'] as $hwpp_code ) : ?>
					<a href="<?php echo esc_url( add_query_arg( 'pp_lang', $hwpp_code, $view['url'] ) ); ?>" class="<?php echo $hwpp_code === $view['lang'] ? 'is-active' : ''; ?>" hreflang="<?php echo esc_attr( $hwpp_code ); ?>"><?php echo esc_html( strtoupper( $hwpp_code ) ); ?></a>
				<?php endforeach; ?>
			</nav>
		<?php endif; ?>
	</div>
</header>

<main class="hwpp-wrap">
	<section class="hwpp-hero<?php echo empty( $hwpp_p['product']['image'] ) ? ' hwpp-hero--noimg' : ''; ?>">
		<?php if ( ! empty( $hwpp_p['product']['image'] ) ) : ?>
			<img class="hwpp-hero__img" src="<?php echo esc_url( $hwpp_p['product']['image'] ); ?>" alt="<?php echo esc_attr( $hwpp_name ); ?>">
		<?php endif; ?>
		<div class="hwpp-hero__text">
			<p class="hwpp-eyebrow"><?php echo esc_html( $hwpp_title ); ?></p>
			<h1><?php echo esc_html( $hwpp_name ); ?></h1>
			<?php if ( ! empty( $hwpp_c['short_description'] ) ) : ?>
				<p class="hwpp-lead"><?php echo esc_html( wp_trim_words( $hwpp_c['short_description'], 40 ) ); ?></p>
			<?php endif; ?>
			<?php if ( ! empty( $view['model'] ) ) : ?>
				<div class="hwpp-badge is-model" role="status">
					<span class="hwpp-badge__icon" aria-hidden="true">i</span>
					<span><strong><?php esc_html_e( 'Product information before purchase', 'codefokus-handwerk-produktpass' ); ?></strong><br><small><?php esc_html_e( 'Every item sold additionally receives its own tamper-proof product passport with a serial number.', 'codefokus-handwerk-produktpass' ); ?></small></span>
				</div>
			<?php elseif ( $hwpp_valid && ! $hwpp_inactive ) : ?>
				<div class="hwpp-badge is-valid" role="status">
					<span class="hwpp-badge__icon" aria-hidden="true">✓</span>
					<span><strong><?php esc_html_e( 'Authenticity confirmed', 'codefokus-handwerk-produktpass' ); ?></strong><br><small><?php echo esc_html( $view['verify']['message'] ); ?></small></span>
				</div>
			<?php elseif ( $hwpp_valid ) : ?>
				<div class="hwpp-badge is-inactive" role="status">
					<span class="hwpp-badge__icon" aria-hidden="true">!</span>
					<span><strong><?php echo esc_html( HWPP_Lifecycle::label( $view['status'] ) ); ?></strong><br><small><?php esc_html_e( 'The data is genuine and unchanged, but this passport is no longer valid (see notice above).', 'codefokus-handwerk-produktpass' ); ?></small></span>
				</div>
			<?php else : ?>
				<div class="hwpp-badge is-invalid" role="status">
					<span class="hwpp-badge__icon" aria-hidden="true">!</span>
					<span><strong><?php esc_html_e( 'Authenticity not confirmed', 'codefokus-handwerk-produktpass' ); ?></strong><br><small><?php echo esc_html( $view['verify']['message'] ); ?></small></span>
				</div>
			<?php endif; ?>
			<?php if ( ! empty( $view['correction']['reason'] ) ) : ?>
				<p class="hwpp-correction">
					<?php
					/* translators: 1: version number, 2: reason */
					echo esc_html( sprintf( __( 'Corrected version %1$d – reason: %2$s', 'codefokus-handwerk-produktpass' ), (int) $view['correction']['version'], $view['correction']['reason'] ) );
					?>
					<?php if ( $view['older_url'] ) : ?> · <a href="<?php echo esc_url( $view['older_url'] ); ?>"><?php esc_html_e( 'previous version', 'codefokus-handwerk-produktpass' ); ?></a><?php endif; ?>
				</p>
			<?php endif; ?>
			<?php if ( empty( $view['model'] ) ) : ?>
			<p class="hwpp-serial"><?php esc_html_e( 'Serial number', 'codefokus-handwerk-produktpass' ); ?>: <code><?php echo esc_html( $hwpp_row->serial ); ?></code></p>
			<p class="hwpp-actions">
				<?php if ( ! empty( $view['cert_url'] ) ) : ?>
				<a class="hwpp-btn" href="<?php echo esc_url( $view['cert_url'] ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Certificate of authenticity (PDF)', 'codefokus-handwerk-produktpass' ); ?></a>
				<?php endif; ?>
				<button class="hwpp-btn hwpp-btn--ghost" type="button" onclick="window.print()"><?php esc_html_e( 'Print', 'codefokus-handwerk-produktpass' ); ?></button>
			</p>
			<?php elseif ( ! empty( $view['product_url'] ) ) : ?>
			<p class="hwpp-actions"><a class="hwpp-btn" href="<?php echo esc_url( $view['product_url'] ); ?>"><?php esc_html_e( 'View product in shop', 'codefokus-handwerk-produktpass' ); ?></a></p>
			<?php endif; ?>
		</div>
	</section>

	<?php include __DIR__ . '/sections.php'; ?>

	<?php if ( empty( $view['model'] ) && HWPP_Public_View::has_restricted() ) : ?>
		<section class="hwpp-card hwpp-unlock-card">
			<?php if ( ! empty( $view['unlocked'] ) ) : ?>
				<p class="hwpp-unlock-ok">✓ <?php esc_html_e( 'Restricted information for repair businesses and authorities has been unlocked and is shown above.', 'codefokus-handwerk-produktpass' ); ?></p>
			<?php else : ?>
				<form class="hwpp-unlock" method="post">
					<label for="hwpp-code"><?php esc_html_e( 'For repair businesses and authorities: unlock further information with the access code from the certificate of authenticity.', 'codefokus-handwerk-produktpass' ); ?></label><br>
					<input id="hwpp-code" type="text" name="hwpp_code" autocomplete="off" placeholder="XXXXX-XXXXX" required>
					<button class="hwpp-btn" type="submit"><?php esc_html_e( 'Unlock', 'codefokus-handwerk-produktpass' ); ?></button>
					<?php if ( ! empty( $view['unlock_error'] ) ) : ?><p role="alert"><?php echo esc_html( $view['unlock_error'] ); ?></p><?php endif; ?>
				</form>
			<?php endif; ?>
		</section>
	<?php endif; ?>
</main>

<?php
/**
 * Fires below the passport content (e.g. signature and workshop stamp).
 *
 * @param array $view Prepared passport data.
 */
do_action( 'hwpp_passport_after_content', $view );
?>
<footer class="hwpp-footer">
	<div class="hwpp-wrap">
		<p><?php echo esc_html( $hwpp_company ); ?> · <?php echo esc_html( $hwpp_title ); ?> · <?php echo empty( $view['model'] ) ? esc_html( HWPP_Public_View::format_date( $hwpp_row->created_at ) ) : ''; ?></p>
	</div>
</footer>
</body>
</html>
