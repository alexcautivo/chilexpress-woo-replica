<?php

/**
 * Datos de Cobertura (Región/comuna)
 *
 
 * @since      1.3.2
 *
 * @package    Chilexpress_Woo_Oficial
 * @subpackage Chilexpress_Woo_Oficial/includes
 */

/**
 * Coverage data for Chilexpress
 *
 *
 * @since      1.3.2
 * @package    Chilexpress_Woo_Oficial
 * @subpackage Chilexpress_Woo_Oficial/includes
 * @author     Chilexpress
 */
class Chilexpress_Woo_Oficial_Coverage { // NOSONAR

	private function get_local_file_contents( $file_path ) {
	    ob_start();
	    include $file_path; // NOSONAR
	    return ob_get_clean();
	}

	public function obtener_regiones() {
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/chilexpress-utils.php';
		$util = new Chilexpress_Woo_Oficial_Utils();
		
		$force_update = false;

		$regiones = array();
		$directory = trailingslashit( plugin_dir_path( dirname( __FILE__ ) ) . 'includes/data/regiones/' );
		$file_path = $directory . 'regiones.json';

		if ( file_exists($file_path) ) {
			$util->write_log( '[Chilexpress][obtener_regiones] Archivo de regiones existe: ' . $file_path );
			$raw_json = $this->get_local_file_contents( $file_path );
			$data = json_decode( $raw_json );
			if ( is_null( $data ) ) {
				$util->write_log( '[Chilexpress][obtener_regiones] Error decodificando los datos de regiones' );
			}
			else {
				$util->write_log( '[Chilexpress][obtener_regiones] Datos decodificados con exito, fecha del archivo: ' . $data->dateUpdated );
				// Check if the data is older than 24 hours
				$date_updated = strtotime($data->dateUpdated);
				$current_time = time();
				$one_hour_ago = $current_time - (3600 * 24); // 24 hours in seconds
				if ($date_updated < $one_hour_ago) {
					$util->write_log( '[Chilexpress][obtener_regiones] El archivo local de regiones es antiguo, forzando actualización' );
					$force_update = true;
				} else {
					$util->write_log( '[Chilexpress][obtener_regiones] El archivo local de regiones es reciente, no es necesario actualizar' );
					foreach ($data->regions as $region) {
						$regiones[$region->regionId] = $region->regionName;
					}
				}
			}
		}
		if ( $force_update == true || count($regiones) == 0 ) {
			$api = new Chilexpress_Woo_Oficial_API();
			$response = $api->obtener_regiones();
			if ( $response && (is_wp_error($response) || count($response) == 0 )) {
				$util->write_log( '[Chilexpress][obtener_regiones] Error obteniendo regiones desde API ' );
			}else {
				$util->write_log( '[Chilexpress][obtener_regiones] Regiones obtenidas desde API ' );
				$regiones = $response ;

				// Save the response to a local file for future use
				if ( !file_exists($directory) ) {
					mkdir($directory, 0755, true);
				}
				// build the response data
				$regiones_json = array();
				// Loop through the response and build the array
				foreach ($regiones as $key => $name) {
					$value = array('regionId' =>  $key , 'regionName' => $name );
					array_push($regiones_json, $value);
				}

				$data_to_save = array(
					'regions' => $regiones_json,
					'dateUpdated' => date('Y-m-d H:i:s')
				);

				// Save the data to the file
				$json_data = json_encode($data_to_save, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

				if ( file_put_contents($file_path, $json_data) == false )
        			$util->write_log( '[Chilexpress][obtener_regiones] Error guardando archivo de regiones ' );
				else
					$util->write_log( '[Chilexpress][obtener_regiones] Archivo de regiones guardado correctamente: ' . $file_path );
			}
		}
		return $regiones;
	}

	public function obtener_comunas($codigo_region = "") {
		$comunas = array();
		if (!$codigo_region || $codigo_region == "") {
			return $comunas;
		}

		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/chilexpress-utils.php';
		$util = new Chilexpress_Woo_Oficial_Utils();
		$util->write_log( '[Chilexpress][obtener_comunas] para la region: ' . $codigo_region );

		$directory = trailingslashit( plugin_dir_path( dirname( __FILE__ ) ) . 'includes/data/comunas/' );
		$file_path = $directory . $codigo_region .".json";
		$util->write_log( '[Chilexpress][obtener_comunas] Busco comunas en el archivo local: ' . $file_path );
		$force_update = false;

		if (file_exists($file_path)) {

			$util->write_log( '[Chilexpress][obtener_comunas] El archivo local existe !' );
			$raw_json = $this->get_local_file_contents( $file_path );
			$data = json_decode( $raw_json );
			if ( is_null( $data ) ) {
				$util->write_log( '[Chilexpress][obtener_comunas] Error decodificando los datos' );
			} else {
				$util->write_log( '[Chilexpress][obtener_comunas] Datos decodificados con exito, fecha del archivo: ' . $data->dateUpdated );
				// Check if the data is older than 5 hours
				$date_updated = strtotime($data->dateUpdated);
				$current_time = time();
				$one_hour_ago = $current_time - (3600 * 5); // 5 hours in seconds
				if ($date_updated < $one_hour_ago) {
					$util->write_log( '[Chilexpress][obtener_comunas] El archivo local es antiguo, forzando actualización' );
					$force_update = true;
				} else {
					$util->write_log( '[Chilexpress][obtener_comunas] El archivo local es reciente, no es necesario actualizar' );
					foreach ($data->coverageAreas as $comuna) {
						$comunas[$comuna->coverageName] = $comuna->countyCode;
					}
				}
			}
		}

		if ( count($comunas) == 0 || $force_update == true ) {
			$api = new Chilexpress_Woo_Oficial_API();
			$response = $api->obtener_comunas_desde_region($codigo_region);
			if ( is_wp_error($response) || count($response) == 0 ) {
				$util->write_log( '[Chilexpress][obtener_comunas] Error obteniendo comunas desde API, retorno un array vacio ' );
			} else {
				$util->write_log( '[Chilexpress][obtener_comunas] Se obtuvieron ' . count($response) . '  comunas desde la API ' );
				$comunas = $response;
				// Save the response to a local file for future use
				if ( !file_exists($directory) ) {
					mkdir($directory, 0755, true);
				}
				// build the response data
				$comunas_json = array();
				// Loop through the response and build the array
				foreach ($comunas as $key => $name) {
					$value = array('countyCode' =>  $name, 'coverageName' => $key );
					array_push($comunas_json, $value);
				}

				$data_to_save = array(
					'coverageAreas' => $comunas_json,
					'dateUpdated' => date('Y-m-d H:i:s')
				);

				// Save the data to the file
				$json_data = json_encode($data_to_save, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

				if ( file_put_contents($file_path, $json_data) == false )
					$util->write_log( '[Chilexpress][obtener_comunas] Error guardando archivo de comunas ' );
				else
					$util->write_log( '[Chilexpress][obtener_comunas] Archivo de comunas guardado correctamente: ' . $file_path );
			}
		}
		return $comunas;
	}

	public function obtener_descripcion_articulos() {

		$api = new Chilexpress_Woo_Oficial_API();
		$response = $api->obtener_descripcion_articulos();
		if (!is_wp_error($response)) {
			return $response;
		} else {
			return array();
		}

	}
	
	

}
