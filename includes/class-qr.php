<?php
/**
 * Standalone QR code generator (ISO/IEC 18004, byte mode, error correction M, versions 1–40).
 *
 * No external libraries or online services needed: QR codes are generated locally –
 * no data is sent to third parties (GDPR-friendly).
 * Algorithm based on the freely documented reference implementation by Project Nayuki.
 *
 * @package HandwerkProduktpass
 */

defined( 'ABSPATH' ) || exit;

class HWPP_QR {

	/** Error-correction codewords per block (level M), indexed by version. */
	private static $ecc_per_block = array( -1, 10, 16, 26, 18, 24, 16, 18, 22, 22, 26, 30, 22, 22, 24, 24, 28, 28, 26, 26, 26, 26, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28 );

	/** Number of error-correction blocks (level M), indexed by version. */
	private static $num_blocks = array( -1, 1, 1, 1, 2, 2, 4, 4, 4, 5, 5, 5, 8, 9, 9, 10, 10, 11, 13, 14, 16, 17, 17, 18, 20, 21, 23, 25, 26, 28, 29, 31, 33, 35, 37, 38, 40, 43, 45, 47, 49 );

	private $size;
	private $version;
	private $modules  = array();
	private $function = array();

	/**
	 * Builds the module matrix (true = dark).
	 *
	 * @param string $text Inhalt (UTF-8).
	 * @return bool[][]
	 */
	public static function matrix( $text ) {
		$qr = new self( (string) $text );
		return $qr->modules;
	}

	private function __construct( $text ) {
		$bytes = array_values( unpack( 'C*', $text ) ?: array() );
		$len   = count( $bytes );

		// Kleinste passende Version finden.
		for ( $ver = 1; $ver <= 40; $ver++ ) {
			$cap_bits  = self::num_data_codewords( $ver ) * 8;
			$used_bits = 4 + ( $ver <= 9 ? 8 : 16 ) + $len * 8;
			if ( $used_bits <= $cap_bits ) {
				break;
			}
		}
		if ( $ver > 40 ) {
			throw new RuntimeException( 'QR: Inhalt zu lang.' );
		}
		$this->version = $ver;
		$this->size    = $ver * 4 + 17;

		// Bitstrom aufbauen.
		$bits = array();
		self::append_bits( $bits, 0x4, 4 );
		self::append_bits( $bits, $len, $ver <= 9 ? 8 : 16 );
		foreach ( $bytes as $b ) {
			self::append_bits( $bits, $b, 8 );
		}
		$cap = self::num_data_codewords( $ver ) * 8;
		self::append_bits( $bits, 0, min( 4, $cap - count( $bits ) ) );
		self::append_bits( $bits, 0, ( 8 - count( $bits ) % 8 ) % 8 );
		for ( $pad = 0xEC; count( $bits ) < $cap; $pad ^= 0xEC ^ 0x11 ) {
			self::append_bits( $bits, $pad, 8 );
		}
		$data = array_fill( 0, intdiv( count( $bits ), 8 ), 0 );
		foreach ( $bits as $i => $bit ) {
			$data[ $i >> 3 ] |= $bit << ( 7 - ( $i & 7 ) );
		}

		$row = array_fill( 0, $this->size, false );
		$this->modules  = array_fill( 0, $this->size, $row );
		$this->function = array_fill( 0, $this->size, $row );

		$this->draw_function_patterns();
		$all = $this->add_ecc_and_interleave( $data );
		$this->draw_codewords( $all );

		// Choose the best mask.
		$best_mask    = 0;
		$best_penalty = PHP_INT_MAX;
		for ( $m = 0; $m < 8; $m++ ) {
			$this->apply_mask( $m );
			$this->draw_format_bits( $m );
			$p = $this->penalty();
			if ( $p < $best_penalty ) {
				$best_penalty = $p;
				$best_mask    = $m;
			}
			$this->apply_mask( $m ); // Rückgängig (XOR).
		}
		$this->apply_mask( $best_mask );
		$this->draw_format_bits( $best_mask );
	}

	private static function append_bits( array &$bits, $val, $len ) {
		for ( $i = $len - 1; $i >= 0; $i-- ) {
			$bits[] = ( $val >> $i ) & 1;
		}
	}

	private static function num_raw_data_modules( $ver ) {
		$result = ( 16 * $ver + 128 ) * $ver + 64;
		if ( $ver >= 2 ) {
			$num_align = intdiv( $ver, 7 ) + 2;
			$result   -= ( 25 * $num_align - 10 ) * $num_align - 55;
			if ( $ver >= 7 ) {
				$result -= 36;
			}
		}
		return $result;
	}

	private static function num_data_codewords( $ver ) {
		return intdiv( self::num_raw_data_modules( $ver ), 8 ) - self::$ecc_per_block[ $ver ] * self::$num_blocks[ $ver ];
	}

	private function set_function( $x, $y, $dark ) {
		$this->modules[ $y ][ $x ]  = (bool) $dark;
		$this->function[ $y ][ $x ] = true;
	}

	private function draw_function_patterns() {
		$s = $this->size;
		for ( $i = 0; $i < $s; $i++ ) {
			$this->set_function( 6, $i, 0 === $i % 2 );
			$this->set_function( $i, 6, 0 === $i % 2 );
		}
		$this->draw_finder( 3, 3 );
		$this->draw_finder( $s - 4, 3 );
		$this->draw_finder( 3, $s - 4 );

		$pos = $this->alignment_positions();
		$n   = count( $pos );
		for ( $i = 0; $i < $n; $i++ ) {
			for ( $j = 0; $j < $n; $j++ ) {
				if ( ( 0 === $i && 0 === $j ) || ( 0 === $i && $n - 1 === $j ) || ( $n - 1 === $i && 0 === $j ) ) {
					continue;
				}
				for ( $dy = -2; $dy <= 2; $dy++ ) {
					for ( $dx = -2; $dx <= 2; $dx++ ) {
						$this->set_function( $pos[ $i ] + $dx, $pos[ $j ] + $dy, 1 !== max( abs( $dx ), abs( $dy ) ) );
					}
				}
			}
		}
		$this->draw_format_bits( 0 ); // Platzhalter reservieren.
		$this->draw_version();
	}

	private function draw_finder( $x, $y ) {
		for ( $dy = -4; $dy <= 4; $dy++ ) {
			for ( $dx = -4; $dx <= 4; $dx++ ) {
				$dist = max( abs( $dx ), abs( $dy ) );
				$xx   = $x + $dx;
				$yy   = $y + $dy;
				if ( $xx >= 0 && $xx < $this->size && $yy >= 0 && $yy < $this->size ) {
					$this->set_function( $xx, $yy, 2 !== $dist && 4 !== $dist );
				}
			}
		}
	}

	private function alignment_positions() {
		$ver = $this->version;
		if ( 1 === $ver ) {
			return array();
		}
		$num  = intdiv( $ver, 7 ) + 2;
		$step = ( 32 === $ver ) ? 26 : (int) ceil( ( $ver * 4 + 4 ) / ( $num * 2 - 2 ) ) * 2;
		$res  = array( 6 );
		for ( $pos = $this->size - 7; count( $res ) < $num; $pos -= $step ) {
			array_splice( $res, 1, 0, array( $pos ) );
		}
		return $res;
	}

	private function draw_format_bits( $mask ) {
		$data = ( 0 << 3 ) | $mask; // Fehlerkorrektur M = 00.
		$rem  = $data;
		for ( $i = 0; $i < 10; $i++ ) {
			$rem = ( $rem << 1 ) ^ ( ( $rem >> 9 ) * 0x537 );
		}
		$bits = ( ( $data << 10 ) | $rem ) ^ 0x5412;
		$s    = $this->size;

		for ( $i = 0; $i <= 5; $i++ ) {
			$this->set_function( 8, $i, ( $bits >> $i ) & 1 );
		}
		$this->set_function( 8, 7, ( $bits >> 6 ) & 1 );
		$this->set_function( 8, 8, ( $bits >> 7 ) & 1 );
		$this->set_function( 7, 8, ( $bits >> 8 ) & 1 );
		for ( $i = 9; $i < 15; $i++ ) {
			$this->set_function( 14 - $i, 8, ( $bits >> $i ) & 1 );
		}
		for ( $i = 0; $i < 8; $i++ ) {
			$this->set_function( $s - 1 - $i, 8, ( $bits >> $i ) & 1 );
		}
		for ( $i = 8; $i < 15; $i++ ) {
			$this->set_function( 8, $s - 15 + $i, ( $bits >> $i ) & 1 );
		}
		$this->set_function( 8, $s - 8, true );
	}

	private function draw_version() {
		if ( $this->version < 7 ) {
			return;
		}
		$rem = $this->version;
		for ( $i = 0; $i < 12; $i++ ) {
			$rem = ( $rem << 1 ) ^ ( ( $rem >> 11 ) * 0x1F25 );
		}
		$bits = ( $this->version << 12 ) | $rem;
		for ( $i = 0; $i < 18; $i++ ) {
			$bit = ( $bits >> $i ) & 1;
			$a   = $this->size - 11 + $i % 3;
			$b   = intdiv( $i, 3 );
			$this->set_function( $a, $b, $bit );
			$this->set_function( $b, $a, $bit );
		}
	}

	private function add_ecc_and_interleave( array $data ) {
		$ver        = $this->version;
		$num_blocks = self::$num_blocks[ $ver ];
		$ecc_len    = self::$ecc_per_block[ $ver ];
		$raw        = intdiv( self::num_raw_data_modules( $ver ), 8 );
		$num_short  = $num_blocks - $raw % $num_blocks;
		$short_len  = intdiv( $raw, $num_blocks );

		$divisor = self::rs_divisor( $ecc_len );
		$blocks  = array();
		$k       = 0;
		for ( $i = 0; $i < $num_blocks; $i++ ) {
			$dat_len = $short_len - $ecc_len + ( $i < $num_short ? 0 : 1 );
			$dat     = array_slice( $data, $k, $dat_len );
			$k      += $dat_len;
			$ecc     = self::rs_remainder( $dat, $divisor );
			if ( $i < $num_short ) {
				$dat[] = 0;
			}
			$blocks[] = array_merge( $dat, $ecc );
		}

		$result = array();
		$blen   = count( $blocks[0] );
		for ( $i = 0; $i < $blen; $i++ ) {
			foreach ( $blocks as $j => $block ) {
				if ( $i !== $short_len - $ecc_len || $j >= $num_short ) {
					$result[] = $block[ $i ];
				}
			}
		}
		return $result;
	}

	private static function rs_multiply( $x, $y ) {
		$z = 0;
		for ( $i = 7; $i >= 0; $i-- ) {
			$z  = ( $z << 1 ) ^ ( ( $z >> 7 ) * 0x11D );
			$z ^= ( ( $y >> $i ) & 1 ) * $x;
		}
		return $z & 0xFF;
	}

	private static function rs_divisor( $degree ) {
		$result              = array_fill( 0, $degree, 0 );
		$result[ $degree - 1 ] = 1;
		$root                = 1;
		for ( $i = 0; $i < $degree; $i++ ) {
			for ( $j = 0; $j < $degree; $j++ ) {
				$result[ $j ] = self::rs_multiply( $result[ $j ], $root );
				if ( $j + 1 < $degree ) {
					$result[ $j ] ^= $result[ $j + 1 ];
				}
			}
			$root = self::rs_multiply( $root, 0x02 );
		}
		return $result;
	}

	private static function rs_remainder( array $data, array $divisor ) {
		$result = array_fill( 0, count( $divisor ), 0 );
		foreach ( $data as $b ) {
			$factor = $b ^ array_shift( $result );
			$result[] = 0;
			foreach ( $divisor as $i => $coef ) {
				$result[ $i ] ^= self::rs_multiply( $coef, $factor );
			}
		}
		return $result;
	}

	private function draw_codewords( array $data ) {
		$i     = 0;
		$total = count( $data ) * 8;
		$s     = $this->size;
		for ( $right = $s - 1; $right >= 1; $right -= 2 ) {
			if ( 6 === $right ) {
				$right = 5;
			}
			for ( $vert = 0; $vert < $s; $vert++ ) {
				for ( $j = 0; $j < 2; $j++ ) {
					$x      = $right - $j;
					$upward = 0 === ( ( $right + 1 ) & 2 );
					$y      = $upward ? $s - 1 - $vert : $vert;
					if ( ! $this->function[ $y ][ $x ] && $i < $total ) {
						$this->modules[ $y ][ $x ] = (bool) ( ( $data[ $i >> 3 ] >> ( 7 - ( $i & 7 ) ) ) & 1 );
						$i++;
					}
				}
			}
		}
	}

	private function apply_mask( $mask ) {
		$s = $this->size;
		for ( $y = 0; $y < $s; $y++ ) {
			for ( $x = 0; $x < $s; $x++ ) {
				switch ( $mask ) {
					case 0: $inv = 0 === ( $x + $y ) % 2; break;
					case 1: $inv = 0 === $y % 2; break;
					case 2: $inv = 0 === $x % 3; break;
					case 3: $inv = 0 === ( $x + $y ) % 3; break;
					case 4: $inv = 0 === ( intdiv( $x, 3 ) + intdiv( $y, 2 ) ) % 2; break;
					case 5: $inv = 0 === ( $x * $y % 2 + $x * $y % 3 ); break;
					case 6: $inv = 0 === ( $x * $y % 2 + $x * $y % 3 ) % 2; break;
					default: $inv = 0 === ( ( $x + $y ) % 2 + $x * $y % 3 ) % 2;
				}
				if ( $inv && ! $this->function[ $y ][ $x ] ) {
					$this->modules[ $y ][ $x ] = ! $this->modules[ $y ][ $x ];
				}
			}
		}
	}

	/**
	 * Strafpunkte nach ISO 18004 (vereinfachte, aber regelkonforme Bewertung).
	 */
	private function penalty() {
		$s      = $this->size;
		$result = 0;
		$lines  = array();
		for ( $y = 0; $y < $s; $y++ ) {
			$row = '';
			for ( $x = 0; $x < $s; $x++ ) {
				$row .= $this->modules[ $y ][ $x ] ? '1' : '0';
			}
			$lines[] = $row;
		}
		for ( $x = 0; $x < $s; $x++ ) {
			$col = '';
			for ( $y = 0; $y < $s; $y++ ) {
				$col .= $this->modules[ $y ][ $x ] ? '1' : '0';
			}
			$lines[] = $col;
		}
		$dark = 0;
		foreach ( $lines as $idx => $line ) {
			// Rule 1: runs of the same colour ≥ 5.
			if ( preg_match_all( '/0{5,}|1{5,}/', $line, $m ) ) {
				foreach ( $m[0] as $run ) {
					$result += 3 + strlen( $run ) - 5;
				}
			}
			// Rule 3: finder-like patterns.
			$padded  = '0000' . $line . '0000';
			$result += 40 * ( substr_count( $padded, '10111010000' ) + substr_count( $padded, '00001011101' ) );
			if ( $idx < $s ) {
				$dark += substr_count( $line, '1' );
			}
		}
		// Rule 2: 2×2 blocks.
		for ( $y = 0; $y < $s - 1; $y++ ) {
			for ( $x = 0; $x < $s - 1; $x++ ) {
				$c = $this->modules[ $y ][ $x ];
				if ( $c === $this->modules[ $y ][ $x + 1 ] && $c === $this->modules[ $y + 1 ][ $x ] && $c === $this->modules[ $y + 1 ][ $x + 1 ] ) {
					$result += 3;
				}
			}
		}
		// Rule 4: light/dark ratio.
		$total   = $s * $s;
		$k       = (int) ceil( abs( $dark * 20 - $total * 10 ) / $total ) - 1;
		$result += max( 0, $k ) * 10;
		return $result;
	}

	/* ------------------------------------------------------------------
	 * Ausgabeformate
	 * ------------------------------------------------------------------ */

	public static function svg( $text, $size_px = 200, $dark = '#000000', $quiet = 4 ) {
		$m   = self::matrix( $text );
		$n   = count( $m );
		$dim = $n + 2 * $quiet;
		$d   = '';
		foreach ( $m as $y => $row ) {
			$x = 0;
			while ( $x < $n ) {
				if ( $row[ $x ] ) {
					$start = $x;
					while ( $x < $n && $row[ $x ] ) {
						$x++;
					}
					$d .= 'M' . ( $start + $quiet ) . ' ' . ( $y + $quiet ) . 'h' . ( $x - $start ) . 'v1h-' . ( $x - $start ) . 'z';
				} else {
					$x++;
				}
			}
		}
		return sprintf(
			'<svg xmlns="http://www.w3.org/2000/svg" width="%1$d" height="%1$d" viewBox="0 0 %2$d %2$d" shape-rendering="crispEdges" role="img" aria-label="QR-Code"><rect width="%2$d" height="%2$d" fill="#ffffff"/><path d="%3$s" fill="%4$s"/></svg>',
			(int) $size_px,
			$dim,
			$d,
			esc_attr( $dark )
		);
	}

	public static function png( $text, $scale = 6, $quiet = 4 ) {
		if ( ! function_exists( 'imagecreatetruecolor' ) ) {
			return '';
		}
		$m   = self::matrix( $text );
		$n   = count( $m );
		$dim = ( $n + 2 * $quiet ) * $scale;
		$img = imagecreatetruecolor( $dim, $dim );
		$w   = imagecolorallocate( $img, 255, 255, 255 );
		$b   = imagecolorallocate( $img, 0, 0, 0 );
		imagefilledrectangle( $img, 0, 0, $dim, $dim, $w );
		foreach ( $m as $y => $row ) {
			foreach ( $row as $x => $dark ) {
				if ( $dark ) {
					$px = ( $x + $quiet ) * $scale;
					$py = ( $y + $quiet ) * $scale;
					imagefilledrectangle( $img, $px, $py, $px + $scale - 1, $py + $scale - 1, $b );
				}
			}
		}
		ob_start();
		imagepng( $img );
		$data = ob_get_clean();
		if ( PHP_VERSION_ID < 80000 ) {
			imagedestroy( $img ); // Not needed (and deprecated) since PHP 8.
		}
		return $data;
	}

	/**
	 * Data URI for <img> tags (PNG if GD is available, otherwise SVG). Ideal for PDF invoice plugins.
	 */
	public static function data_uri( $text, $scale = 6 ) {
		$png = self::png( $text, $scale );
		if ( $png ) {
			return 'data:image/png;base64,' . base64_encode( $png ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions
		}
		return 'data:image/svg+xml;base64,' . base64_encode( self::svg( $text ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions
	}
}
