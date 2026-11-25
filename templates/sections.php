<?php
/**
 * Partial template: sections of a product passport (used by the pass page and the product tab).
 *
 * @package HandwerkProduktpass
 * @var array $view
 */

defined( 'ABSPATH' ) || exit;

$hwpp_d      = $view['design'];
$hwpp_c      = $view['c'];
$hwpp_labels = HWPP_Fields::sections();
?>
	<?php foreach ( $hwpp_d['sections'] as $hwpp_section ) : ?>
		<?php
		if ( empty( $hwpp_section['web'] ) ) {
			continue;
		}
		$hwpp_key  = $hwpp_section['key'];
		$hwpp_rows = HWPP_Public_View::section_rows( $hwpp_key, $view );
		$hwpp_mats    = 'materials' === $hwpp_key && ! empty( $hwpp_c['materials'] ) ? $hwpp_c['materials'] : array();
		$hwpp_gallery = 'story' === $hwpp_key && ! empty( $hwpp_c['gallery'] ) ? $hwpp_c['gallery'] : array();
		if ( 'verification' === $hwpp_key && ! empty( $view['model'] ) ) {
			continue;
		}
		if ( ! $hwpp_rows && ! $hwpp_mats && ! $hwpp_gallery ) {
			continue;
		}
		?>
		<section class="hwpp-card hwpp-card--<?php echo esc_attr( $hwpp_key ); ?>">
			<h2><?php echo esc_html( $hwpp_labels[ $hwpp_key ] ); ?></h2>

			<?php if ( $hwpp_gallery ) : ?>
				<div class="hwpp-gallery">
					<?php foreach ( $hwpp_gallery as $hwpp_img ) : ?>
						<a href="<?php echo esc_url( $hwpp_img['url'] ); ?>" target="_blank" rel="noopener"><img src="<?php echo esc_url( $hwpp_img['thumb'] ? $hwpp_img['thumb'] : $hwpp_img['url'] ); ?>" alt="<?php echo esc_attr( $hwpp_img['alt'] ); ?>" loading="lazy"></a>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

			<?php if ( $hwpp_mats ) : ?>
				<div class="hwpp-materials">
					<?php foreach ( $hwpp_mats as $hwpp_m ) : ?>
						<article class="hwpp-material">
							<h3><?php echo esc_html( $hwpp_m['name'] ); ?></h3>
							<dl>
								<?php foreach ( HWPP_Public_View::material_lines( $hwpp_m, ! empty( $view['unlocked'] ) ) as $hwpp_label => $hwpp_value ) : ?>
									<dt><?php echo esc_html( $hwpp_label ); ?></dt><dd><?php echo esc_html( $hwpp_value ); ?></dd>
								<?php endforeach; ?>
							</dl>
							<?php if ( ! empty( $hwpp_m['evidence_url'] ) ) : ?>
								<a class="hwpp-link" href="<?php echo esc_url( $hwpp_m['evidence_url'] ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'View evidence', 'codefokus-handwerk-produktpass' ); ?> →</a>
							<?php endif; ?>
						</article>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

			<?php if ( $hwpp_rows ) : ?>
				<dl class="hwpp-rows">
					<?php foreach ( $hwpp_rows as $hwpp_r ) : ?>
						<?php if ( '' === $hwpp_r['label'] ) : ?>
							<div class="hwpp-row hwpp-row--text"><dd><?php echo wp_kses_post( wpautop( esc_html( $hwpp_r['value'] ) ) ); ?></dd></div>
						<?php else : ?>
							<div class="hwpp-row"><dt><?php echo esc_html( $hwpp_r['label'] ); ?></dt><dd><?php echo nl2br( esc_html( $hwpp_r['value'] ) ); ?></dd></div>
						<?php endif; ?>
					<?php endforeach; ?>
				</dl>
			<?php endif; ?>

			<?php if ( 'verification' === $hwpp_key ) : ?>
				<div class="hwpp-verify">
					<div class="hwpp-qr"><?php echo HWPP_QR::svg( $view['url'], 140, '#111111' ); // phpcs:ignore WordPress.Security.EscapeOutput -- selbst erzeugtes SVG. ?></div>
					<p class="hwpp-small"><?php esc_html_e( 'The checksum is a digital fingerprint of all passport data. It is also printed on the certificate of authenticity. If both match, the data has not been changed since it was issued.', 'codefokus-handwerk-produktpass' ); ?></p>
				</div>
			<?php endif; ?>
		</section>
	<?php endforeach; ?>
