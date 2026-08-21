<?php
/**
 * Plugin Name: CXP Incident PDF
 * Description: Generador genérico de PDF cliente/técnico a partir de ticket + run + comparison. Sin endpoints AJAX.
 * Version: 1.1.0
 *
 * Uso (desde runner / tickets UI):
 *   Cxp_Incident_Pdf::download_client( $ticket, $run, $comparison );
 *   Cxp_Incident_Pdf::download_technical( $ticket, $run, $comparison );
 *   Cxp_Incident_Pdf::render_client( $ticket, $run, $comparison )->save( $path );
 *   Cxp_Incident_Pdf::filename( $ticket, $run, 'cliente' );
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Cxp_Incident_Pdf {

	/**
	 * Ensure Cxp_Simple_Pdf is loaded. Safe to call repeatedly.
	 *
	 * @return bool
	 */
	public static function ensure_engine() {
		if ( class_exists( 'Cxp_Simple_Pdf', false ) ) {
			return true;
		}
		$path = WP_CONTENT_DIR . '/mu-plugins/cxp-simple-pdf.php';
		if ( is_readable( $path ) ) {
			require_once $path;
		}
		return class_exists( 'Cxp_Simple_Pdf', false );
	}

	/**
	 * Suggested download name: {ticket}-{run}-cliente.pdf | {ticket}-{run}-tecnico.pdf
	 *
	 * @param array  $ticket
	 * @param array  $run
	 * @param string $kind cliente|tecnico|client|technical
	 */
	public static function filename( array $ticket, array $run = array(), $kind = 'cliente' ) {
		$ticket_id = self::sanitize_id( self::ticket_id( $ticket ) );
		$run_id    = self::sanitize_id( self::run_id( $run ) );
		$kind      = strtolower( (string) $kind );
		if ( in_array( $kind, array( 'technical', 'tecnico', 'tech' ), true ) ) {
			$suffix = 'tecnico';
		} else {
			$suffix = 'cliente';
		}
		if ( $run_id === '' || 'sin-run' === $run_id ) {
			return $ticket_id . '-' . $suffix . '.pdf';
		}
		return $ticket_id . '-' . $run_id . '-' . $suffix . '.pdf';
	}

	/** @return Cxp_Simple_Pdf */
	public static function render_client( array $ticket, array $run = array(), array $comparison = array() ) {
		self::require_engine();
		$ticket_id = self::ticket_id( $ticket );
		$run_id    = self::run_id( $run );
		$site      = self::site_label( $ticket );
		$title     = $ticket_id . '  |  Informe para el cliente  |  ' . $site;

		$pdf = new Cxp_Simple_Pdf( $title );
		$pdf->set_header( $title );
		$pdf->set_footer( self::aeolabs_short() );

		$pdf->heading( 'Informe de incidencia (lenguaje simple)' );
		$pdf->note( self::aeolabs_line() );
		$pdf->note( 'Ticket: ' . $ticket_id . ( $run_id !== '' ? '  ·  Ejecucion: ' . $run_id : '' ) );
		$pdf->note( 'Fecha del informe: ' . self::report_date( $run, $comparison ) );
		$pdf->spacer( 4 );

		$pdf->heading( '1. Resumen', 2 );
		$pdf->para( self::client_summary( $ticket, $comparison ) );

		$pdf->heading( '2. Que se reporto', 2 );
		self::write_reported_client( $pdf, $ticket, $comparison );
		self::write_support_evidence( $pdf, $ticket );

		$pdf->heading( '3. Que se reprodujo en laboratorio', 2 );
		self::write_actual_client( $pdf, $run, $comparison );

		$pdf->heading( '4. Resultado comparado', 2 );
		self::write_verdict_client( $pdf, $comparison, $run );

		$pdf->heading( '5. Entorno exacto probado', 2 );
		self::write_stack_tables( $pdf, $ticket, $run, $comparison );
		self::write_inventory( $pdf, $ticket, $run );

		$pdf->heading( '6. Causa probable', 2 );
		self::write_cause_client( $pdf, $comparison );

		$pdf->heading( '7. Impacto', 2 );
		$pdf->para( self::impact_text( $ticket ) );

		$pdf->heading( '8. Recomendaciones y proximos pasos', 2 );
		self::write_recommendations( $pdf, $comparison, true );

		$pdf->spacer( 8 );
		$pdf->hr();
		$pdf->note( 'Documento generado por ' . self::aeolabs_line() . '. Sin secretos ni traces extensos.' );
		$pdf->note( 'Chilexpress Oficial no se modifica en este laboratorio salvo que el ticket lo documente aparte.' );

		return $pdf;
	}

	/** @return Cxp_Simple_Pdf */
	public static function render_technical( array $ticket, array $run = array(), array $comparison = array() ) {
		self::require_engine();
		$ticket_id = self::ticket_id( $ticket );
		$run_id    = self::run_id( $run );
		$site      = self::site_label( $ticket );
		$title     = $ticket_id . '  |  Informe tecnico  |  ' . $site;

		$pdf = new Cxp_Simple_Pdf( $title );
		$pdf->set_header( $title );
		$pdf->set_footer( self::aeolabs_short() );

		$pdf->heading( 'Informe tecnico de incidencia' );
		$pdf->note( self::aeolabs_line() );
		$pdf->kv( 'Ticket', $ticket_id );
		$pdf->kv( 'Run', $run_id !== '' ? $run_id : '(sin run)' );
		$pdf->kv( 'Fecha', self::report_date( $run, $comparison ) );
		$pdf->kv( 'Sitio', $site );
		$pdf->spacer( 4 );

		$pdf->heading( '1. Identidad y alcance', 2 );
		$pdf->para( 'Laboratorio Aeolabs. Autor: ' . self::author_name() . '. Contacto: ' . self::author_email() . '.' );
		$pdf->para( 'Este PDF refleja exactamente el run indicado. El ticket JSON original no se muta.' );

		$pdf->heading( '2. Pila solicitada vs real', 2 );
		self::write_stack_tables( $pdf, $ticket, $run, $comparison );

		$pdf->heading( '3. Inventario de plugins / tema', 2 );
		self::write_inventory( $pdf, $ticket, $run );

		$pdf->heading( '4. Reportado vs real (firma)', 2 );
		self::write_reported_vs_real_tech( $pdf, $ticket, $run, $comparison );
		self::write_support_evidence( $pdf, $ticket );

		$pdf->heading( '5. Diff estructurado', 2 );
		self::write_diff( $pdf, $comparison );

		$pdf->heading( '6. Causa probable y reglas', 2 );
		self::write_cause_tech( $pdf, $comparison );

		$pdf->heading( '7. Recomendaciones', 2 );
		self::write_recommendations( $pdf, $comparison, false );

		$pdf->heading( '8. Pasos y assertions del run', 2 );
		self::write_steps( $pdf, $run );

		$pdf->heading( '9. Evidencia tecnica', 2 );
		self::write_technical_evidence( $pdf, $run, $comparison );

		$pdf->heading( '10. Limitaciones y restauracion', 2 );
		self::write_limitations( $pdf, $run, $comparison );

		$pdf->spacer( 8 );
		$pdf->hr();
		$pdf->note( 'Generado por ' . self::aeolabs_line() . ' · ' . self::author_email() );

		return $pdf;
	}

	public static function download_client( array $ticket, array $run = array(), array $comparison = array(), $filename = '' ) {
		if ( $filename === '' ) {
			$filename = self::filename( $ticket, $run, 'cliente' );
		}
		self::render_client( $ticket, $run, $comparison )->output( $filename );
	}

	public static function download_technical( array $ticket, array $run = array(), array $comparison = array(), $filename = '' ) {
		if ( $filename === '' ) {
			$filename = self::filename( $ticket, $run, 'tecnico' );
		}
		self::render_technical( $ticket, $run, $comparison )->output( $filename );
	}

	/**
	 * @return string Absolute path written
	 */
	public static function save_client( array $ticket, array $run, array $comparison, $path ) {
		return self::render_client( $ticket, $run, $comparison )->save( $path );
	}

	/**
	 * @return string Absolute path written
	 */
	public static function save_technical( array $ticket, array $run, array $comparison, $path ) {
		return self::render_technical( $ticket, $run, $comparison )->save( $path );
	}

	/** PDF binary for client report (no headers). */
	public static function bytes_client( array $ticket, array $run = array(), array $comparison = array() ) {
		return self::render_client( $ticket, $run, $comparison )->bytes();
	}

	/** PDF binary for technical report (no headers). */
	public static function bytes_technical( array $ticket, array $run = array(), array $comparison = array() ) {
		return self::render_technical( $ticket, $run, $comparison )->bytes();
	}

	// -------------------------------------------------------------------------
	// Writers
	// -------------------------------------------------------------------------

	private static function write_reported_client( Cxp_Simple_Pdf $pdf, array $ticket, array $comparison ) {
		$rep = self::reported_block( $ticket, $comparison );
		if ( $rep['summary'] !== '' ) {
			$pdf->para( $rep['summary'] );
		}
		if ( $rep['message'] !== '' ) {
			$pdf->kv( 'Mensaje', $rep['message'] );
		}
		if ( $rep['url'] !== '' ) {
			$pdf->kv( 'Donde falla', $rep['url'] );
		}
		foreach ( array(
			'Resultado esperado' => $ticket['sintoma']['resultado_esperado'] ?? '',
			'Resultado obtenido' => $ticket['sintoma']['resultado_obtenido'] ?? '',
			'Precondiciones' => $ticket['sintoma']['precondiciones'] ?? '',
			'Datos de prueba' => $ticket['sintoma']['datos_prueba'] ?? '',
		) as $label => $value ) {
			if ( trim( (string) $value ) !== '' ) {
				$pdf->kv( $label, self::clip( (string) $value, 700 ) );
			}
		}
		foreach ( $rep['steps'] as $step ) {
			$pdf->bullet( $step );
		}
		if ( $rep['summary'] === '' && $rep['message'] === '' && ! $rep['steps'] ) {
			$pdf->note( 'Sin detalle de reporte en el ticket/comparison.' );
		}
	}

	private static function write_actual_client( Cxp_Simple_Pdf $pdf, array $run, array $comparison ) {
		$act = self::actual_block( $run, $comparison );
		if ( $act['summary'] !== '' ) {
			$pdf->para( $act['summary'] );
		}
		if ( $act['message'] !== '' ) {
			$pdf->kv( 'Mensaje observado', $act['message'] );
		}
		if ( $act['url'] !== '' ) {
			$pdf->kv( 'URL / endpoint', $act['url'] );
		}
		$status = self::pick( $run, array( 'status', 'estado', 'result_status' ), '' );
		if ( $status !== '' ) {
			$pdf->kv( 'Estado del run', (string) $status );
		}
		if ( $act['summary'] === '' && $act['message'] === '' && $status === '' ) {
			$pdf->note( 'Aun no hay resultado de ejecucion. Genere el PDF despues de correr el flujo.' );
		}
	}

	private static function write_support_evidence( Cxp_Simple_Pdf $pdf, array $ticket ) {
		$evidence = is_array( $ticket['evidencia'] ?? null ) ? $ticket['evidencia'] : array();
		$text = trim( (string) ( $evidence['ticket_texto'] ?? '' ) );
		$notes = trim( (string) ( $evidence['capturas_notas'] ?? '' ) );
		$file = trim( (string) ( $evidence['captura_archivo'] ?? '' ) );
		if ( '' !== $text ) {
			$pdf->kv( 'Texto del ticket/correo', '' );
			$pdf->code( self::clip( $text, 2500 ) );
		}
		if ( '' !== $notes ) {
			$pdf->kv( 'Descripción del pantallazo', self::clip( $notes, 1000 ) );
		}
		if ( '' !== $file ) {
			$pdf->kv( 'Pantallazo adjunto', 'incidents/' . ltrim( $file, '/' ) );
		}
	}

	private static function write_verdict_client( Cxp_Simple_Pdf $pdf, array $comparison, array $run = array() ) {
		$v = self::verdict_info( $comparison );
		$pdf->para( $v['label_es'] );
		$total = $run['steps_total'] ?? null;
		if ( $total !== null && $total !== '' ) {
			$ok = (int) ( $run['steps_ok'] ?? 0 );
			$pdf->kv( 'Pruebas automaticas', $ok . '/' . (int) $total . ' correctas' );
			$pdf->para(
				(int) $total === $ok
					? 'Todas las comprobaciones se ejecutaron sin errores tecnicos de la prueba.'
					: 'Algunas comprobaciones no pasaron; el detalle esta en el informe tecnico.'
			);
		}
		$pdf->para(
			! empty( $comparison['issue_reproduced'] )
				? 'Resultado: el laboratorio SI reprodujo el problema informado.'
				: 'Resultado: el laboratorio NO reprodujo el problema informado con esta pila y estos pasos.'
		);
		if ( $v['explanation'] !== '' ) {
			$pdf->para( $v['explanation'] );
		}
		foreach ( $v['markers_present'] as $m ) {
			$pdf->bullet( 'Presente: ' . $m );
		}
		foreach ( $v['markers_missing'] as $m ) {
			$pdf->bullet( 'Ausente: ' . $m );
		}
	}

	private static function write_cause_client( Cxp_Simple_Pdf $pdf, array $comparison ) {
		$cause = self::cause_block( $comparison );
		if ( $cause['title'] !== '' ) {
			$pdf->para( $cause['title'] );
		}
		$explain = $cause['explanation_simple'] !== '' ? $cause['explanation_simple'] : $cause['explanation'];
		if ( $explain !== '' ) {
			$pdf->para( $explain );
		}
		if ( $cause['title'] === '' && $explain === '' ) {
			$pdf->note( 'Causa probable pendiente de reglas de diagnostico.' );
		}
	}

	private static function write_recommendations( Cxp_Simple_Pdf $pdf, array $comparison, $client_tone ) {
		$recs = self::recommendations( $comparison );
		if ( ! $recs ) {
			$pdf->note( $client_tone
				? 'Sin recomendaciones automaticas todavia. El laboratorio las completara tras la replica.'
				: 'Sin recomendaciones en comparison.recommendations / recomendaciones.'
			);
			return;
		}
		$i = 1;
		foreach ( $recs as $rec ) {
			if ( is_array( $rec ) ) {
				$text = (string) ( $rec['text'] ?? $rec['titulo'] ?? $rec['title'] ?? $rec['paso'] ?? '' );
				if ( $text === '' ) {
					$text = wp_json_encode( $rec, JSON_UNESCAPED_UNICODE );
				}
			} else {
				$text = (string) $rec;
			}
			$text = trim( $text );
			if ( $text === '' ) {
				continue;
			}
			$pdf->bullet( $i . '. ' . $text );
			$i++;
		}
	}

	private static function write_stack_tables( Cxp_Simple_Pdf $pdf, array $ticket, array $run, array $comparison ) {
		$requested = self::stack_requested( $ticket, $run, $comparison );
		$actual    = self::stack_actual( $run, $comparison );
		$keys      = array_unique( array_merge( array_keys( $requested ), array_keys( $actual ) ) );
		$rows      = array();
		foreach ( $keys as $k ) {
			$rows[] = array(
				$k,
				isset( $requested[ $k ] ) ? (string) $requested[ $k ] : '—',
				isset( $actual[ $k ] ) ? (string) $actual[ $k ] : '—',
			);
		}
		if ( $rows ) {
			$pdf->table( array( 'Componente', 'Solicitado', 'Real' ), $rows, array( 140, 160, 160 ) );
		} else {
			$pdf->note( 'Sin datos de pila en ticket/run/comparison.' );
		}
	}

	private static function write_inventory( Cxp_Simple_Pdf $pdf, array $ticket, array $run ) {
		$requested = ! empty( $ticket['plugins'] ) && is_array( $ticket['plugins'] ) ? $ticket['plugins'] : array();
		$actual = array();
		if ( ! empty( $run['stack_actual']['plugins'] ) && is_array( $run['stack_actual']['plugins'] ) ) {
			$actual = $run['stack_actual']['plugins'];
		} elseif ( ! empty( $run['inventory']['plugins'] ) && is_array( $run['inventory']['plugins'] ) ) {
			$actual = $run['inventory']['plugins'];
		}
		$rows = array();
		$seen = array();
		foreach ( $requested as $p ) {
			$slug = (string) ( $p['slug'] ?? '' );
			$real = is_array( $actual[ $slug ] ?? null ) ? $actual[ $slug ] : array();
			$seen[ $slug ] = true;
			$rows[] = array(
				(string) ( $p['nombre'] ?? $p['name'] ?? $slug ),
				(string) ( $p['version'] ?? '' ),
				(string) ( $real['version'] ?? 'no instalado' ),
				( isset( $p['activo'] ) ? ( $p['activo'] ? 'si' : 'no' ) : '—' ) . ' / ' .
					( array_key_exists( 'active', $real ) ? ( $real['active'] ? 'si' : 'no' ) : '—' ),
				(string) ( $p['fuente'] ?? $p['source'] ?? '' ),
			);
		}
		foreach ( $actual as $slug => $p ) {
			if ( isset( $seen[ (string) $slug ] ) ) {
				continue;
			}
			if ( ! is_array( $p ) ) {
				continue;
			}
			$rows[] = array(
				(string) ( $p['name'] ?? $slug ),
				'no solicitado',
				(string) ( $p['version'] ?? '' ),
				'— / ' . ( ! empty( $p['active'] ) ? 'si' : 'no' ),
				'existente laboratorio',
			);
		}
		if ( $rows ) {
			$pdf->table( array( 'Plugin', 'Solicitada', 'Real', 'Activo req/real', 'Fuente' ), $rows, array( 150, 70, 70, 85, 85 ) );
		} else {
			$pdf->note( 'Sin inventario de plugins.' );
		}

		$sources = self::pick( $run, array( 'sources', 'fuentes', 'checksums' ), null );
		if ( is_array( $sources ) && $sources ) {
			$pdf->spacer( 4 );
			$pdf->para( 'Fuentes / checksums del run:' );
			foreach ( $sources as $k => $v ) {
				if ( is_array( $v ) ) {
					$v = wp_json_encode( $v, JSON_UNESCAPED_UNICODE );
				}
				$pdf->kv( (string) $k, (string) $v );
			}
		}
	}

	private static function write_reported_vs_real_tech( Cxp_Simple_Pdf $pdf, array $ticket, array $run, array $comparison ) {
		$rep = self::reported_block( $ticket, $comparison );
		$act = self::actual_block( $run, $comparison );
		$pdf->table(
			array( 'Campo', 'Reportado', 'Real' ),
			array(
				array( 'Mensaje', self::clip( $rep['message'], 90 ), self::clip( $act['message'], 90 ) ),
				array( 'Clase/tipo', $rep['class'], $act['class'] ),
				array( 'Archivo', $rep['file'], $act['file'] ),
				array( 'Linea', $rep['line'], $act['line'] ),
				array( 'URL', $rep['url'], $act['url'] ),
				array( 'HTTP', $rep['status'], $act['status'] ),
			),
			array( 90, 200, 200 )
		);
		$v = self::verdict_info( $comparison );
		$pdf->spacer( 4 );
		$pdf->kv( 'Veredicto', $v['code'] . ' — ' . $v['label_es'] );
		if ( isset( $comparison['score'] ) ) {
			$pdf->kv( 'Score de coincidencia', (string) $comparison['score'] );
		}
		if ( ! empty( $comparison['checks'] ) && is_array( $comparison['checks'] ) ) {
			$rows = array();
			foreach ( $comparison['checks'] as $field => $ok ) {
				$rows[] = array( (string) $field, $ok ? 'coincide' : 'no coincide' );
			}
			$pdf->table( array( 'Campo comparado', 'Resultado' ), $rows, array( 200, 120 ) );
		}
		if ( isset( $run['steps_total'] ) && $run['steps_total'] !== null ) {
			$pdf->kv( 'Pasos OK', (int) ( $run['steps_ok'] ?? 0 ) . '/' . (int) $run['steps_total'] );
		}
		if ( ! empty( $comparison['rules_version'] ) ) {
			$pdf->kv( 'Version de reglas', (string) $comparison['rules_version'] );
		}
	}

	private static function write_diff( Cxp_Simple_Pdf $pdf, array $comparison ) {
		$diffs = array();
		if ( ! empty( $comparison['differences'] ) && is_array( $comparison['differences'] ) ) {
			$diffs = $comparison['differences'];
		} elseif ( ! empty( $comparison['diff'] ) && is_array( $comparison['diff'] ) ) {
			$diffs = $comparison['diff'];
		} elseif ( ! empty( $comparison['diferencias'] ) && is_array( $comparison['diferencias'] ) ) {
			$diffs = $comparison['diferencias'];
		}
		if ( ! $diffs ) {
			$pdf->note( 'Sin diff estructurado en comparison.' );
			return;
		}
		foreach ( $diffs as $d ) {
			if ( is_array( $d ) ) {
				$field = (string) ( $d['field'] ?? $d['campo'] ?? $d['key'] ?? 'campo' );
				$exp   = (string) ( $d['expected'] ?? $d['reportado'] ?? $d['left'] ?? '' );
				$got   = (string) ( $d['actual'] ?? $d['real'] ?? $d['right'] ?? '' );
				$pdf->bullet( $field . ': reportado=[' . self::clip( $exp, 60 ) . '] real=[' . self::clip( $got, 60 ) . ']' );
			} else {
				$pdf->bullet( (string) $d );
			}
		}
	}

	private static function write_cause_tech( Cxp_Simple_Pdf $pdf, array $comparison ) {
		$cause = self::cause_block( $comparison );
		if ( $cause['id'] !== '' ) {
			$pdf->kv( 'Regla / id', $cause['id'] );
		}
		if ( $cause['title'] !== '' ) {
			$pdf->kv( 'Titulo', $cause['title'] );
		}
		if ( $cause['confidence'] !== '' ) {
			$pdf->kv( 'Confianza', $cause['confidence'] );
		}
		if ( $cause['explanation'] !== '' ) {
			$pdf->para( $cause['explanation'] );
		}
		$rules = array();
		if ( ! empty( $comparison['rules_matched'] ) && is_array( $comparison['rules_matched'] ) ) {
			$rules = $comparison['rules_matched'];
		} elseif ( ! empty( $comparison['reglas'] ) && is_array( $comparison['reglas'] ) ) {
			$rules = $comparison['reglas'];
		} elseif ( ! empty( $cause['rules'] ) && is_array( $cause['rules'] ) ) {
			$rules = $cause['rules'];
		}
		if ( $rules ) {
			$pdf->para( 'Reglas que justifican el diagnostico:' );
			foreach ( $rules as $r ) {
				if ( is_array( $r ) ) {
					$id   = (string) ( $r['id'] ?? $r['rule'] ?? '' );
					$text = (string) ( $r['title'] ?? $r['titulo'] ?? $r['reason'] ?? $r['text'] ?? wp_json_encode( $r, JSON_UNESCAPED_UNICODE ) );
					$pdf->bullet( trim( $id . ' — ' . $text, ' —' ) );
				} else {
					$pdf->bullet( (string) $r );
				}
			}
		}
		if ( $cause['id'] === '' && $cause['title'] === '' && $cause['explanation'] === '' && ! $rules ) {
			$pdf->note( 'Sin causa probable ni reglas en comparison.' );
		}
	}

	private static function write_steps( Cxp_Simple_Pdf $pdf, array $run ) {
		$steps = array();
		if ( ! empty( $run['steps'] ) && is_array( $run['steps'] ) ) {
			$steps = $run['steps'];
		} elseif ( ! empty( $run['timeline'] ) && is_array( $run['timeline'] ) ) {
			$steps = $run['timeline'];
		}
		if ( ! $steps ) {
			$pdf->note( 'Sin pasos/assertions en el run.' );
			return;
		}
		$rows = array();
		$i    = 1;
		foreach ( $steps as $step ) {
			if ( ! is_array( $step ) ) {
				$rows[] = array( (string) $i, (string) $step, '', '' );
				$i++;
				continue;
			}
			$op   = (string) ( $step['op'] ?? $step['action'] ?? $step['tipo'] ?? $step['type'] ?? '' );
			$name = (string) ( $step['name'] ?? $step['label'] ?? $step['titulo'] ?? $op );
			$ok   = self::step_ok_label( $step );
			$det  = (string) ( $step['assertion'] ?? $step['detail'] ?? $step['message'] ?? $step['error'] ?? '' );
			$rows[] = array( (string) $i, self::clip( $name !== '' ? $name : $op, 40 ), $ok, self::clip( $det, 50 ) );
			$i++;
		}
		$pdf->table( array( '#', 'Paso', 'Resultado', 'Detalle' ), $rows, array( 30, 170, 70, 190 ) );
	}

	private static function write_technical_evidence( Cxp_Simple_Pdf $pdf, array $run, array $comparison ) {
		$ev = array();
		if ( ! empty( $run['evidence'] ) && is_array( $run['evidence'] ) ) {
			$ev = $run['evidence'];
		} elseif ( ! empty( $run['evidencia'] ) && is_array( $run['evidencia'] ) ) {
			$ev = $run['evidencia'];
		}

		$http = $ev['http'] ?? $run['http'] ?? null;
		if ( is_array( $http ) ) {
			$pdf->para( 'HTTP:' );
			foreach ( array( 'status', 'url', 'method', 'body_excerpt', 'body' ) as $k ) {
				if ( isset( $http[ $k ] ) && $http[ $k ] !== '' && $http[ $k ] !== null ) {
					$val = is_scalar( $http[ $k ] ) ? (string) $http[ $k ] : wp_json_encode( $http[ $k ], JSON_UNESCAPED_UNICODE );
					if ( in_array( $k, array( 'body', 'body_excerpt' ), true ) ) {
						$pdf->kv( $k, '' );
						$pdf->code( self::clip( $val, 2500 ) );
					} else {
						$pdf->kv( $k, self::clip( $val, 200 ) );
					}
				}
			}
		} elseif ( is_string( $http ) && $http !== '' ) {
			$pdf->para( 'HTTP:' );
			$pdf->code( self::clip( $http, 1500 ) );
		}

		$php = $ev['php'] ?? $run['php_errors'] ?? $run['php'] ?? null;
		if ( $php ) {
			$pdf->para( 'PHP / errores:' );
			$pdf->code( self::clip( is_string( $php ) ? $php : wp_json_encode( $php, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT ), 2500 ) );
		}

		$js = $ev['js'] ?? $run['js_errors'] ?? null;
		if ( $js ) {
			$pdf->para( 'JS / red:' );
			$pdf->code( self::clip( is_string( $js ) ? $js : wp_json_encode( $js, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT ), 1500 ) );
		}

		$stack = $ev['stack_trace'] ?? $ev['stack'] ?? $run['stack_trace'] ?? $run['stack'] ?? $comparison['stack_trace'] ?? '';
		if ( is_array( $stack ) ) {
			$stack = implode( "\n", $stack );
		}
		if ( (string) $stack !== '' ) {
			$pdf->para( 'Stack trace:' );
			$pdf->code( self::clip( (string) $stack, 3500 ) );
		}

		$log = $ev['debug_log'] ?? $ev['log'] ?? $run['debug_log'] ?? $run['log'] ?? '';
		if ( (string) $log !== '' ) {
			$pdf->para( 'debug.log (extracto):' );
			$pdf->code( self::clip( (string) $log, 3500 ) );
		}

		$output = $run['output'] ?? $run['probe_output'] ?? $ev['output'] ?? '';
		if ( (string) $output !== '' ) {
			$pdf->para( 'Salida del probe / run:' );
			$pdf->code( self::clip( (string) $output, 2500 ) );
		}

		if ( ! $http && ! $php && ! $js && (string) $stack === '' && (string) $log === '' && (string) $output === '' ) {
			$pdf->note( 'Sin evidencia tecnica adjunta en run/comparison.' );
		}
	}

	private static function write_limitations( Cxp_Simple_Pdf $pdf, array $run, array $comparison ) {
		$lim = self::pick( $comparison, array( 'limitations', 'limitaciones' ), null );
		if ( is_array( $lim ) ) {
			foreach ( $lim as $item ) {
				$pdf->bullet( is_scalar( $item ) ? (string) $item : wp_json_encode( $item, JSON_UNESCAPED_UNICODE ) );
			}
		} elseif ( is_string( $lim ) && $lim !== '' ) {
			$pdf->para( $lim );
		} else {
			$pdf->bullet( 'La replica puede diferir del hosting real (opcache, object cache, CDN, trafico concurrente).' );
			$pdf->bullet( 'El JSON del cliente nunca ejecuta PHP arbitrario; solo operaciones declarativas permitidas.' );
		}

		$restored = self::pick( $run, array( 'restored', 'restaurado', 'restoration' ), null );
		$snap     = self::pick( $run, array( 'snapshot_id', 'snapshot' ), '' );
		if ( $snap !== '' && $snap !== null ) {
			$pdf->kv( 'Snapshot', is_scalar( $snap ) ? (string) $snap : wp_json_encode( $snap ) );
		}
		if ( $restored === true || $restored === 1 || $restored === '1' || $restored === 'si' || $restored === 'yes' ) {
			$pdf->kv( 'Restauracion', 'Realizada tras el run' );
		} elseif ( is_string( $restored ) && $restored !== '' ) {
			$pdf->kv( 'Restauracion', $restored );
		} elseif ( is_array( $restored ) ) {
			$pdf->kv( 'Restauracion', wp_json_encode( $restored, JSON_UNESCAPED_UNICODE ) );
		} else {
			$pdf->note( 'Estado de restauracion no indicado en el run.' );
		}
	}

	// -------------------------------------------------------------------------
	// Data helpers
	// -------------------------------------------------------------------------

	private static function require_engine() {
		if ( ! self::ensure_engine() ) {
			wp_die( 'Falta Cxp_Simple_Pdf (mu-plugin cxp-simple-pdf.php).', 'PDF', array( 'response' => 500 ) );
		}
	}

	private static function ticket_id( array $ticket ) {
		$id = self::pick( $ticket, array( 'ticket_id', 'id', 'ticket' ), 'SIN-ID' );
		return (string) $id;
	}

	private static function run_id( array $run ) {
		$id = self::pick( $run, array( 'run_id', 'id', 'run' ), '' );
		return (string) $id;
	}

	private static function site_label( array $ticket ) {
		$origen = isset( $ticket['origen'] ) && is_array( $ticket['origen'] ) ? $ticket['origen'] : array();
		$url    = (string) ( $origen['sitio_url'] ?? $origen['site_url'] ?? $ticket['site_url'] ?? '' );
		$emp    = (string) ( $origen['empresa'] ?? $origen['company'] ?? '' );
		if ( $url !== '' ) {
			$host = wp_parse_url( $url, PHP_URL_HOST );
			return $host ? $host : $url;
		}
		return $emp !== '' ? $emp : 'laboratorio';
	}

	private static function report_date( array $run, array $comparison ) {
		$d = self::pick( $run, array( 'finished_at', 'ended_at', 'completed_at', 'at', 'fecha' ), '' );
		if ( $d === '' ) {
			$d = self::pick( $comparison, array( 'at', 'fecha', 'compared_at' ), '' );
		}
		if ( $d === '' ) {
			$d = gmdate( 'Y-m-d H:i' ) . ' UTC';
		}
		return (string) $d;
	}

	private static function client_summary( array $ticket, array $comparison ) {
		$custom = self::pick( $comparison, array( 'summary_client', 'resumen_cliente', 'summary' ), '' );
		if ( is_string( $custom ) && $custom !== '' ) {
			return $custom;
		}
		$sintoma = isset( $ticket['sintoma'] ) && is_array( $ticket['sintoma'] ) ? $ticket['sintoma'] : array();
		$resumen = (string) ( $sintoma['resumen'] ?? '' );
		$v       = self::verdict_info( $comparison );
		$parts   = array();
		if ( $resumen !== '' ) {
			$parts[] = $resumen;
		}
		$parts[] = 'Resultado de la replica: ' . $v['label_es'];
		return implode( ' ', $parts );
	}

	private static function impact_text( array $ticket ) {
		$sintoma = isset( $ticket['sintoma'] ) && is_array( $ticket['sintoma'] ) ? $ticket['sintoma'] : array();
		$impacto = (string) ( $sintoma['impacto'] ?? $ticket['impacto'] ?? '' );
		$pedido  = isset( $ticket['pedido'] ) && is_array( $ticket['pedido'] ) ? $ticket['pedido'] : array();
		$need    = (string) ( $pedido['que_necesitan'] ?? '' );
		$urg     = (string) ( $pedido['urgencia'] ?? '' );
		$bits    = array();
		if ( $impacto !== '' ) {
			$bits[] = 'Impacto declarado: ' . $impacto . '.';
		}
		if ( $urg !== '' ) {
			$bits[] = 'Urgencia: ' . $urg . '.';
		}
		if ( $need !== '' ) {
			$bits[] = $need;
		}
		return $bits ? implode( ' ', $bits ) : 'Impacto no detallado en el ticket.';
	}

	private static function reported_block( array $ticket, array $comparison ) {
		$src = array();
		if ( ! empty( $comparison['reported'] ) && is_array( $comparison['reported'] ) ) {
			$src = $comparison['reported'];
		} elseif ( ! empty( $comparison['reportado'] ) && is_array( $comparison['reportado'] ) ) {
			$src = $comparison['reportado'];
		}
		$sintoma = isset( $ticket['sintoma'] ) && is_array( $ticket['sintoma'] ) ? $ticket['sintoma'] : array();
		$steps   = array();
		if ( ! empty( $sintoma['pasos_para_reproducir'] ) && is_array( $sintoma['pasos_para_reproducir'] ) ) {
			foreach ( $sintoma['pasos_para_reproducir'] as $s ) {
				$steps[] = (string) $s;
			}
		}
		return array(
			'summary' => (string) ( $src['summary'] ?? $src['resumen'] ?? $sintoma['resumen'] ?? '' ),
			'message' => (string) ( $src['message'] ?? $src['mensaje'] ?? $sintoma['mensaje_error'] ?? '' ),
			'class'   => (string) ( $src['class'] ?? $src['clase'] ?? $src['type'] ?? $src['tipo'] ?? '' ),
			'file'    => (string) ( $src['file'] ?? $src['archivo'] ?? '' ),
			'line'    => (string) ( $src['line'] ?? $src['linea'] ?? '' ),
			'url'     => (string) ( $src['url'] ?? $sintoma['url_donde_falla'] ?? '' ),
			'status'  => (string) ( $src['status'] ?? $src['http_status'] ?? '' ),
			'steps'   => $steps,
		);
	}

	private static function actual_block( array $run, array $comparison ) {
		$src = array();
		if ( ! empty( $comparison['actual'] ) && is_array( $comparison['actual'] ) ) {
			$src = $comparison['actual'];
		} elseif ( ! empty( $comparison['real'] ) && is_array( $comparison['real'] ) ) {
			$src = $comparison['real'];
		} elseif ( ! empty( $comparison['reproduced'] ) && is_array( $comparison['reproduced'] ) ) {
			$src = $comparison['reproduced'];
		} elseif ( ! empty( $comparison['reproducido'] ) && is_array( $comparison['reproducido'] ) ) {
			$src = $comparison['reproducido'];
		} elseif ( ! empty( $run['actual'] ) && is_array( $run['actual'] ) ) {
			$src = $run['actual'];
		} elseif ( ! empty( $run['result'] ) && is_array( $run['result'] ) ) {
			$src = $run['result'];
		}
		return array(
			'summary' => (string) ( $src['summary'] ?? $src['resumen'] ?? $run['summary'] ?? '' ),
			'message' => (string) ( $src['message'] ?? $src['mensaje'] ?? $run['message'] ?? $run['text'] ?? '' ),
			'class'   => (string) ( $src['class'] ?? $src['clase'] ?? $src['type'] ?? $src['tipo'] ?? '' ),
			'file'    => (string) ( $src['file'] ?? $src['archivo'] ?? '' ),
			'line'    => (string) ( $src['line'] ?? $src['linea'] ?? '' ),
			'url'     => (string) ( $src['url'] ?? $run['url'] ?? '' ),
			'status'  => (string) ( $src['status'] ?? $src['http_status'] ?? $run['http_status'] ?? '' ),
		);
	}

	private static function verdict_info( array $comparison ) {
		$code = strtolower( (string) self::pick( $comparison, array( 'verdict', 'resultado', 'result' ), '' ) );
		if ( $code === '' ) {
			if ( array_key_exists( 'coincide', $comparison ) ) {
				$c = $comparison['coincide'];
				if ( $c === true || $c === 1 || $c === '1' || $c === 'si' || $c === 'yes' ) {
					$code = 'coincide';
				} elseif ( is_string( $c ) && stripos( $c, 'parcial' ) !== false ) {
					$code = 'coincide_parcialmente';
				} else {
					$code = 'no_coincide';
				}
			} elseif ( isset( $comparison['ok'] ) ) {
				$code = ! empty( $comparison['ok'] ) ? 'coincide' : 'no_coincide';
			} else {
				$code = 'pendiente';
			}
		}
		$code = str_replace( array( ' ', '-' ), array( '_', '_' ), $code );
		$map  = array(
			'coincide'              => 'COINCIDE — el laboratorio reprodujo el fallo reportado.',
			'coincide_parcialmente' => 'COINCIDE PARCIALMENTE — hay solapamiento, con diferencias.',
			'parcial'               => 'COINCIDE PARCIALMENTE — hay solapamiento, con diferencias.',
			'no_coincide'           => 'NO COINCIDE — el resultado real difiere de lo reportado.',
			'no_reproducible'       => 'NO REPRODUCIBLE — no se pudo forzar el fallo en laboratorio.',
			'pendiente'             => 'PENDIENTE — aun no hay comparacion para este run.',
		);
		$label = isset( $map[ $code ] ) ? $map[ $code ] : strtoupper( $code );
		$expl  = (string) self::pick( $comparison, array( 'verdict_explanation', 'explicacion', 'explanation' ), '' );
		$present = array();
		$missing = array();
		if ( ! empty( $comparison['markers_present'] ) && is_array( $comparison['markers_present'] ) ) {
			$present = array_map( 'strval', $comparison['markers_present'] );
		} elseif ( ! empty( $comparison['hits'] ) && is_array( $comparison['hits'] ) ) {
			$present = array_map( 'strval', $comparison['hits'] );
		}
		if ( ! empty( $comparison['markers_missing'] ) && is_array( $comparison['markers_missing'] ) ) {
			$missing = array_map( 'strval', $comparison['markers_missing'] );
		}
		return array(
			'code'             => $code,
			'label_es'         => $label,
			'explanation'      => $expl,
			'markers_present'  => $present,
			'markers_missing'  => $missing,
		);
	}

	private static function cause_block( array $comparison ) {
		$c = array();
		if ( ! empty( $comparison['probable_cause'] ) && is_array( $comparison['probable_cause'] ) ) {
			$c = $comparison['probable_cause'];
		} elseif ( ! empty( $comparison['causa_probable'] ) && is_array( $comparison['causa_probable'] ) ) {
			$c = $comparison['causa_probable'];
		} elseif ( ! empty( $comparison['cause'] ) && is_array( $comparison['cause'] ) ) {
			$c = $comparison['cause'];
		}
		return array(
			'id'                  => (string) ( $c['id'] ?? $c['rule'] ?? '' ),
			'title'               => (string) ( $c['title'] ?? $c['titulo'] ?? '' ),
			'explanation'         => (string) ( $c['explanation'] ?? $c['explicacion'] ?? $c['detail'] ?? '' ),
			'explanation_simple'  => (string) ( $c['explanation_simple'] ?? $c['explicacion_simple'] ?? $c['client'] ?? '' ),
			'confidence'          => (string) ( $c['confidence'] ?? $c['confianza'] ?? '' ),
			'rules'               => isset( $c['rules'] ) && is_array( $c['rules'] ) ? $c['rules'] : array(),
		);
	}

	private static function recommendations( array $comparison ) {
		if ( ! empty( $comparison['recommendations'] ) && is_array( $comparison['recommendations'] ) ) {
			return $comparison['recommendations'];
		}
		if ( ! empty( $comparison['recomendaciones'] ) && is_array( $comparison['recomendaciones'] ) ) {
			return $comparison['recomendaciones'];
		}
		if ( ! empty( $comparison['next_steps'] ) && is_array( $comparison['next_steps'] ) ) {
			return $comparison['next_steps'];
		}
		return array();
	}

	private static function stack_requested( array $ticket, array $run, array $comparison ) {
		$pila = array();
		if ( ! empty( $ticket['pila'] ) && is_array( $ticket['pila'] ) ) {
			$pila = $ticket['pila'];
		}
		if ( ! empty( $run['stack_requested'] ) && is_array( $run['stack_requested'] ) ) {
			$pila = array_merge( $pila, $run['stack_requested'] );
		}
		if ( ! empty( $comparison['stack_requested'] ) && is_array( $comparison['stack_requested'] ) ) {
			$pila = array_merge( $pila, $comparison['stack_requested'] );
		}
		return self::flatten_stack( $pila );
	}

	private static function stack_actual( array $run, array $comparison ) {
		$pila = array();
		if ( ! empty( $run['stack_actual'] ) && is_array( $run['stack_actual'] ) ) {
			$pila = $run['stack_actual'];
		} elseif ( ! empty( $run['stack'] ) && is_array( $run['stack'] ) ) {
			$pila = $run['stack'];
		} elseif ( ! empty( $run['pila_real'] ) && is_array( $run['pila_real'] ) ) {
			$pila = $run['pila_real'];
		}
		if ( ! empty( $comparison['stack_actual'] ) && is_array( $comparison['stack_actual'] ) ) {
			$pila = array_merge( $pila, $comparison['stack_actual'] );
		}
		return self::flatten_stack( $pila );
	}

	private static function flatten_stack( array $pila ) {
		$labels = array(
			'php' => 'PHP',
			'wordpress' => 'WordPress',
			'woocommerce' => 'WooCommerce',
			'chilexpress_oficial' => 'Chilexpress Oficial',
			'chilexpress-oficial' => 'Chilexpress Oficial',
		);
		$out = array();
		foreach ( $pila as $k => $v ) {
			$key = (string) $k;
			if ( 'plugins' === $key ) {
				foreach ( (array) $v as $slug => $plugin ) {
					if ( ! is_array( $plugin ) ) {
						continue;
					}
					$slug = (string) $slug;
					if ( ! in_array( $slug, array( 'woocommerce', 'chilexpress-oficial' ), true ) ) {
						continue;
					}
					$out[ $labels[ $slug ] ] = (string) ( $plugin['version'] ?? '' );
				}
				continue;
			}
			if ( in_array( $key, array( 'tema', 'theme' ), true ) && is_array( $v ) ) {
				$out['Tema'] = trim( (string) ( $v['nombre'] ?? $v['name'] ?? $v['slug'] ?? '' ) . ' ' . (string) ( $v['version'] ?? '' ) );
				if ( ! empty( $v['padre_slug'] ) ) {
					$out['Tema padre'] = trim( (string) $v['padre_slug'] . ' ' . (string) ( $v['padre_version'] ?? '' ) );
				}
				continue;
			}
			if ( is_array( $v ) ) {
				$name = (string) ( $v['nombre'] ?? $v['name'] ?? $key );
				$ver  = (string) ( $v['version'] ?? '' );
				$out[ $name ] = $ver !== '' ? $ver : wp_json_encode( $v, JSON_UNESCAPED_UNICODE );
			} else {
				$out[ $labels[ $key ] ?? $key ] = (string) $v;
			}
		}
		return $out;
	}

	private static function step_ok_label( array $step ) {
		if ( array_key_exists( 'ok', $step ) ) {
			return ! empty( $step['ok'] ) ? 'OK' : 'FAIL';
		}
		if ( array_key_exists( 'passed', $step ) ) {
			return ! empty( $step['passed'] ) ? 'OK' : 'FAIL';
		}
		$status = strtolower( (string) ( $step['status'] ?? $step['result'] ?? '' ) );
		if ( in_array( $status, array( 'ok', 'pass', 'passed', 'success', 'exito' ), true ) ) {
			return 'OK';
		}
		if ( in_array( $status, array( 'fail', 'failed', 'error', 'fallo' ), true ) ) {
			return 'FAIL';
		}
		return $status !== '' ? $status : '—';
	}

	private static function pick( array $arr, array $keys, $default = '' ) {
		foreach ( $keys as $k ) {
			if ( array_key_exists( $k, $arr ) && $arr[ $k ] !== null && $arr[ $k ] !== '' ) {
				return $arr[ $k ];
			}
		}
		return $default;
	}

	private static function clip( $text, $max = 120 ) {
		$text = (string) $text;
		if ( strlen( $text ) <= $max ) {
			return $text;
		}
		return substr( $text, 0, max( 0, $max - 3 ) ) . '...';
	}

	private static function sanitize_id( $id ) {
		$id = strtolower( (string) $id );
		$id = preg_replace( '/[^a-z0-9._-]+/', '-', $id );
		$id = trim( $id, '-.' );
		return $id !== '' ? $id : 'sin-id';
	}

	private static function author_name() {
		return function_exists( 'cxp_author_name' ) ? cxp_author_name() : 'Alexander Alejandro Cautivo Ramos';
	}

	private static function author_email() {
		return function_exists( 'cxp_author_email_public' ) ? cxp_author_email_public() : 'alexander.cautivo@aeolabs.io';
	}

	private static function aeolabs_line() {
		return self::author_name() . '  ·  Aeolabs  ·  ' . self::author_email();
	}

	private static function aeolabs_short() {
		return function_exists( 'cxp_author_line' ) ? cxp_author_line( true ) : 'Alexander Cautivo · Aeolabs.io';
	}
}
