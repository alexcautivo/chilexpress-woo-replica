<?php
/**
 * Minimal PDF writer (A4, Helvetica, Latin-1). For replica lab reports only.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Cxp_Simple_Pdf {
	private $pages   = array();
	private $current = '';
	private $y       = 0;
	private $title   = '';

	public const PAGE_W = 595.28;
	public const PAGE_H = 841.89;
	public const MARGIN = 48.0;

	public function __construct( $title = '' ) {
		$this->title = $title;
		$this->new_page();
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

		foreach ( $this->pages as $i => $stream ) {
			$objects[] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 ' . self::PAGE_W . ' ' . self::PAGE_H . '] /Contents ' . $content_objs[ $i ] . ' 0 R /Resources << /Font << /F1  ' . ( 3 + ( $n * 2 ) ) . ' 0 R >> >> >>';
			$objects[] = '<< /Length ' . strlen( $stream ) . " >>\nstream\n" . $stream . "\nendstream";
		}
		$objects[] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';

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
		$this->text( $this->title !== '' ? $this->title : 'Informe laboratorio Chilexpress', 8, false );
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
		$this->current .= $this->tj( 'Pagina ' . $n, 8, self::PAGE_W / 2 - 20, $footer_y, false );
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
		$this->current .= $this->tj( $text, $size, self::MARGIN, $this->y, $bold );
		$this->y       -= $size + 4;
	}

	private function flow( $text, $size, $bold, $leading, $indent = 0 ) {
		$width = self::PAGE_W - ( 2 * self::MARGIN ) - $indent;
		$lines = $this->wrap( $text, $size, $width );
		foreach ( $lines as $line ) {
			$this->ensure( $leading );
			$this->current .= $this->tj( $line, $size, self::MARGIN + $indent, $this->y, $bold );
			$this->y       -= $leading;
		}
	}

	private function tj( $text, $size, $x, $y, $bold ) {
		$font = $bold ? 'Helvetica-Bold' : 'Helvetica';
		$esc  = $this->esc( $this->latin( $text ) );
		return sprintf(
			"BT /F1 %.2f Tf 0 g %.2f %.2f Td (%s) Tj ET\n",
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

	private function latin( $text ) {
		$text = html_entity_decode( (string) $text, ENT_QUOTES, 'UTF-8' );
		$out  = function_exists( 'iconv' ) ? @iconv( 'UTF-8', 'ISO-8859-1//TRANSLIT', $text ) : false;
		return false !== $out ? $out : utf8_decode( $text );
	}

	private function esc( $text ) {
		return str_replace( array( '\\', '(', ')' ), array( '\\\\', '\\(', '\\)' ), $text );
	}
}
