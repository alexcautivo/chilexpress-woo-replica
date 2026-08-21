<?php
/**
 * Minimal PDF writer (A4, Helvetica/Courier, Latin-1). For replica lab reports.
 *
 * Public API (existing callers unchanged):
 *   heading(), para(), bullet(), kv(), note(), spacer(), save(), output()
 *
 * Optional extras:
 *   set_header(), set_footer(), page_break(), hr(), table(), code(), bytes()
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Cxp_Simple_Pdf {
	private $pages   = array();
	private $current = '';
	private $y       = 0;
	private $title   = '';
	private $header  = '';
	private $footer  = '';

	public const PAGE_W = 595.28;
	public const PAGE_H = 841.89;
	public const MARGIN = 48.0;

	public function __construct( $title = '' ) {
		$this->title  = (string) $title;
		$this->header = $this->title;
		$this->new_page();
	}

	/**
	 * Header line drawn at the top of every page (including the first).
	 * Empty string keeps the constructor title behaviour.
	 */
	public function set_header( $text ) {
		$this->header = (string) $text;
		return $this;
	}

	/**
	 * Optional left-side footer text. Page numbers remain on the right/center.
	 */
	public function set_footer( $text ) {
		$this->footer = (string) $text;
		return $this;
	}

	public function heading( $text, $level = 1 ) {
		$size = 1 === $level ? 16 : ( 2 === $level ? 13 : 11 );
		$gap  = 1 === $level ? 10 : 8;
		$this->ensure( $size + $gap + 6 );
		if ( 1 === $level && $this->y < self::PAGE_H - self::MARGIN - 20 ) {
			$this->rule();
		}
		$this->text( $text, $size, true );
		$this->y -= $gap;
	}

	public function para( $text ) {
		$this->flow( $text, 10, false, 13 );
		$this->y -= 4;
	}

	public function bullet( $text ) {
		$this->flow( '• ' . $text, 10, false, 13, 12 );
		$this->y -= 2;
	}

	public function kv( $key, $value ) {
		$this->flow( $key . ': ' . $value, 10, false, 13 );
	}

	public function note( $text ) {
		$this->flow( $text, 9, false, 12 );
		$this->y -= 3;
	}

	public function spacer( $n = 8 ) {
		$this->y -= $n;
	}

	/** Force a new page (header/footer applied automatically). */
	public function page_break() {
		$this->new_page();
		return $this;
	}

	/** Public horizontal rule. */
	public function hr() {
		$this->ensure( 10 );
		$this->rule();
		return $this;
	}

	/**
	 * Simple multi-column table. Cells wrap within column width.
	 *
	 * @param string[]                $headers
	 * @param array<int, string[]>    $rows
	 * @param float[]|null            $col_widths Optional absolute widths (points). Null = equal split.
	 */
	public function table( array $headers, array $rows, $col_widths = null ) {
		$n = count( $headers );
		if ( $n < 1 ) {
			return $this;
		}
		$usable = self::PAGE_W - ( 2 * self::MARGIN );
		if ( ! is_array( $col_widths ) || count( $col_widths ) !== $n ) {
			$col_widths = array_fill( 0, $n, $usable / $n );
		}
		$size     = 9;
		$leading  = 11;
		$pad      = 3;

		$draw_row = function ( array $cells, $bold ) use ( $n, $col_widths, $size, $leading, $pad ) {
			$wrapped = array();
			$max     = 1;
			for ( $i = 0; $i < $n; $i++ ) {
				$cell        = isset( $cells[ $i ] ) ? (string) $cells[ $i ] : '';
				$wrapped[ $i ] = $this->wrap( $cell, $size, max( 12.0, $col_widths[ $i ] - ( 2 * $pad ) ) );
				$max         = max( $max, count( $wrapped[ $i ] ) );
			}
			$need = ( $max * $leading ) + 4;
			$this->ensure( $need );
			$y0 = $this->y;
			$x  = self::MARGIN;
			for ( $i = 0; $i < $n; $i++ ) {
				$ly = $y0;
				foreach ( $wrapped[ $i ] as $line ) {
					$this->current .= $this->tj( $line, $size, $x + $pad, $ly, $bold, 'H' );
					$ly           -= $leading;
				}
				$x += $col_widths[ $i ];
			}
			$this->y = $y0 - $need + 2;
			$this->current .= sprintf(
				"0.75 G %.2f %.2f m %.2f %.2f l S\n0 G\n",
				self::MARGIN,
				$this->y + 2,
				self::PAGE_W - self::MARGIN,
				$this->y + 2
			);
			$this->y -= 2;
		};

		$draw_row( $headers, true );
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				$row = array( (string) $row );
			}
			$draw_row( $row, false );
		}
		$this->y -= 4;
		return $this;
	}

	/**
	 * Monospace-ish code / log / stack block. Preserves newlines; long lines wrap.
	 */
	public function code( $text ) {
		$text  = str_replace( array( "\r\n", "\r" ), "\n", (string) $text );
		$lines = explode( "\n", $text );
		$size  = 8;
		$lead  = 10;
		$width = self::PAGE_W - ( 2 * self::MARGIN ) - 8;
		$this->ensure( $lead + 4 );
		foreach ( $lines as $line ) {
			$chunks = $this->wrap_keep( $line, $size, $width );
			foreach ( $chunks as $chunk ) {
				$this->ensure( $lead );
				$this->current .= $this->tj( $chunk, $size, self::MARGIN + 4, $this->y, false, 'C' );
				$this->y       -= $lead;
			}
		}
		$this->y -= 4;
		return $this;
	}

	/** PDF bytes without writing headers or files. */
	public function bytes() {
		return $this->build_pdf();
	}

	public function save( $path ) {
		$pdf = $this->build_pdf();
		file_put_contents( $path, $pdf );
		return $path;
	}

	public function output( $filename ) {
		$pdf = $this->build_pdf();
		nocache_headers();
		header( 'Content-Type: application/pdf' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Content-Length: ' . strlen( $pdf ) );
		echo $pdf;
		exit;
	}

	private function build_pdf() {
		$this->flush_page();
		$objects = array();
		$objects[] = '<< /Type /Catalog /Pages 2 0 R >>';

		$kids = array();
		$page_objs = array();
		$content_objs = array();
		$n = count( $this->pages );
		for ( $i = 0; $i < $n; $i++ ) {
			$page_objs[]    = 3 + ( $i * 2 );
			$content_objs[] = 4 + ( $i * 2 );
			$kids[]         = ( 3 + ( $i * 2 ) ) . ' 0 R';
		}
		$objects[] = '<< /Type /Pages /Kids [' . implode( ' ', $kids ) . '] /Count ' . $n . ' >>';

		$font_base = 3 + ( $n * 2 );
		$f1        = $font_base;
		$f2        = $font_base + 1;
		$f3        = $font_base + 2;
		$font_res  = sprintf(
			'/Font << /F1 %d 0 R /F2 %d 0 R /F3 %d 0 R >>',
			$f1,
			$f2,
			$f3
		);

		foreach ( $this->pages as $i => $stream ) {
			$objects[] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 ' . self::PAGE_W . ' ' . self::PAGE_H . '] /Contents ' . $content_objs[ $i ] . ' 0 R /Resources << ' . $font_res . ' >> >>';
			$objects[] = '<< /Length ' . strlen( $stream ) . " >>\nstream\n" . $stream . "\nendstream";
		}
		$objects[] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
		$objects[] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';
		$objects[] = '<< /Type /Font /Subtype /Type1 /BaseFont /Courier /Encoding /WinAnsiEncoding >>';

		$pdf  = "%PDF-1.4\n";
		$xref = array( 0 );
		foreach ( $objects as $i => $body ) {
			$xref[] = strlen( $pdf );
			$pdf   .= ( $i + 1 ) . " 0 obj\n" . $body . "\nendobj\n";
		}
		$start = strlen( $pdf );
		$pdf  .= "xref\n0 " . ( count( $objects ) + 1 ) . "\n";
		$pdf  .= "0000000000 65535 f \n";
		for ( $i = 1; $i <= count( $objects ); $i++ ) {
			$pdf .= sprintf( "%010d 00000 n \n", $xref[ $i ] );
		}
		$pdf .= 'trailer << /Size ' . ( count( $objects ) + 1 ) . ' /Root 1 0 R >>' . "\n";
		$pdf .= "startxref\n" . $start . "\n%%EOF";
		return $pdf;
	}

	private function new_page() {
		$this->flush_page();
		$this->current = '';
		$this->y       = self::PAGE_H - self::MARGIN;
		$head          = $this->header !== '' ? $this->header : ( $this->title !== '' ? $this->title : 'Informe laboratorio Chilexpress' );
		$this->text( $head, 8, false );
		$this->y -= 2;
		$this->rule();
		$this->y -= 8;
	}

	private function flush_page() {
		if ( '' === $this->current ) {
			return;
		}
		$n = count( $this->pages ) + 1;
		$footer_y = 28;
		if ( $this->footer !== '' ) {
			$this->current .= $this->tj( $this->footer, 7, self::MARGIN, $footer_y, false, 'H' );
		}
		$this->current .= $this->tj( 'Pagina ' . $n, 8, self::PAGE_W / 2 - 20, $footer_y, false, 'H' );
		$this->pages[]  = $this->current;
		$this->current  = '';
	}

	private function rule() {
		$y = $this->y;
		$this->current .= sprintf( "0.7 G 48 %.2f m 547 %.2f l S\n0 G\n", $y, $y );
		$this->y       -= 6;
	}

	private function ensure( $need ) {
		if ( $this->y - $need < 56 ) {
			$this->new_page();
		}
	}

	private function text( $text, $size, $bold ) {
		$this->ensure( $size + 4 );
		$this->current .= $this->tj( $text, $size, self::MARGIN, $this->y, $bold, 'H' );
		$this->y       -= $size + 4;
	}

	private function flow( $text, $size, $bold, $leading, $indent = 0 ) {
		$width = self::PAGE_W - ( 2 * self::MARGIN ) - $indent;
		$lines = $this->wrap( $text, $size, $width );
		foreach ( $lines as $line ) {
			$this->ensure( $leading );
			$this->current .= $this->tj( $line, $size, self::MARGIN + $indent, $this->y, $bold, 'H' );
			$this->y       -= $leading;
		}
	}

	/**
	 * @param string $face H = Helvetica family, C = Courier
	 */
	private function tj( $text, $size, $x, $y, $bold, $face = 'H' ) {
		if ( 'C' === $face ) {
			$tag = '/F3';
		} else {
			$tag = $bold ? '/F2' : '/F1';
		}
		$esc = $this->esc( $this->latin( $text ) );
		return sprintf(
			"BT %s %.2f Tf 0 g %.2f %.2f Td (%s) Tj ET\n",
			$tag,
			$size,
			$x,
			$y,
			$esc
		);
	}

	private function wrap( $text, $size, $width ) {
		$text  = preg_replace( '/\s+/u', ' ', trim( wp_strip_all_tags( (string) $text ) ) );
		$words = explode( ' ', $text );
		$lines = array();
		$cur   = '';
		$cw    = $size * 0.5;
		foreach ( $words as $w ) {
			$try = '' === $cur ? $w : $cur . ' ' . $w;
			if ( strlen( $this->latin( $try ) ) * $cw > $width && $cur !== '' ) {
				$lines[] = $cur;
				$cur     = $w;
			} else {
				$cur = $try;
			}
		}
		if ( $cur !== '' ) {
			$lines[] = $cur;
		}
		return $lines ? $lines : array( '' );
	}

	/** Wrap without collapsing internal spaces (for code). */
	private function wrap_keep( $text, $size, $width ) {
		$text = wp_strip_all_tags( (string) $text );
		$cw   = $size * 0.6;
		$max  = max( 8, (int) floor( $width / $cw ) );
		$out  = array();
		while ( $text !== '' ) {
			if ( strlen( $this->latin( $text ) ) <= $max ) {
				$out[] = $text;
				break;
			}
			$take = min( strlen( $text ), $max );
			while ( $take > 1 && strlen( $this->latin( substr( $text, 0, $take ) ) ) > $max ) {
				$take--;
			}
			$out[] = substr( $text, 0, $take );
			$text  = substr( $text, $take );
		}
		return $out ? $out : array( '' );
	}

	private function latin( $text ) {
		$text = html_entity_decode( (string) $text, ENT_QUOTES, 'UTF-8' );
		$out  = function_exists( 'iconv' ) ? @iconv( 'UTF-8', 'ISO-8859-1//TRANSLIT', $text ) : false;
		return false !== $out ? $out : utf8_decode( $text );
	}

	private function esc( $text ) {
		return str_replace( array( '\\', '(', ')' ), array( '\\\\', '\\(', '\\)' ), $text );
	}
}
