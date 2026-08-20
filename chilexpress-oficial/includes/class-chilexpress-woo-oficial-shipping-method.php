<?php

if ( ! class_exists( 'Chilexpress_Woo_Oficial_Shipping_Method' ) ) {
	class Chilexpress_Woo_Oficial_Shipping_Method extends WC_Shipping_Method {
		
		const DESCRIPTION = 'Envios con Chilexpress';
		const ENABLED_KEY = 'enabled';
		const TITLE_KEY = 'tittle';

		/**
    * @var Chilexpress_Woo_Oficial_Coverage $coverage_data The coverage data instance.
    */
		private $coverage_data;

		/**
			* @var Chilexpress_Woo_Oficial_API $api The API instance.
			*/
		private $api;

		/**
			* @var string $id The ID of this shipping method.
			*/
		public $id;

		/**
			* @var string $method_title The title of this shipping method.
			*/
			public $method_title;

		/**
			* @var string $method_description The description of this shipping method.
			*/
			public $method_description;

		/**
			* @var bool $enabled Whether this shipping method is enabled.
			*/
			public $enabled;

		/**
			* @var string $title The title of this shipping method.
			*/
			public $title;

		/**
		 * Constructor for your shipping class
		 *
		 * @access public
		 * @return void
		 */
		public function __construct() {
			$this->id                 = 'chilexpress_woo_oficial';
			$this->method_title       = 'Chilexpress';
			$this->method_description = self::DESCRIPTION;

			$this->coverage_data = new Chilexpress_Woo_Oficial_Coverage();
			$this->api = new Chilexpress_Woo_Oficial_API();

			// Availability & Countries
            $this->availability = 'including';
            $this->countries = array(
                'CL' // Chile
            );

			$this->init();

			$this->enabled = isset( $this->settings[self::ENABLED_KEY] ) ? $this->settings[self::ENABLED_KEY] :
			 'yes';
			$this->title = isset( $this->settings[self::TITLE_KEY] ) ? $this->settings[self::TITLE_KEY] :
			 self::DESCRIPTION;
		}

		/**
		 * Init your settings
		 *
		 * @access public
		 * @return void
		 */
		function init() {
			// Load the settings API
			$this->init_form_fields(); 
			$this->init_settings(); 

			// Save settings in admin if you have any defined
			add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );
		}


		/**
		 * Define settings field for this shipping
		 * @return void 
		 */
		function init_form_fields() { 
			$this->form_fields = array(

				'enabled' => array(
					'title' => 'Habilitar', // NOSONAR
					'type' => 'checkbox',
					'description' => 'Habilitar este método de envío.',
					'default' => 'yes'
				),

				'title' => array(
					'title' => 'Title',
					'type' => 'text',
					'description' =>  'Titulo a mostrar en el sitio',
					'default' => self::DESCRIPTION
				)

			);

        }

		/**
		 * This function is used to calculate the shipping cost. Within this function we can check for weights,
		 *  dimensions and other parameters.
		 *
		 * @access public
		 * @param mixed $package
		 * @return void
		 */
		public function calculate_shipping( $package = array() ) {
			// We will add the cost, rate and logics in here
			$weight = 0;
			$price = 0;
			$biggest_product = false;
			$biggest_size = 0;
			$options = get_option( 'chilexpress_woo_oficial_general' );
			$products_id = array();

			$i = 0;
            foreach ( $package['contents'] as $item_id => $values ) { 

                $_product = $values['data']; 
                $dimensions = $_product->get_dimensions(false);

                array_push($products_id, $values["product_id"]);

                if (
					$dimensions["width"]!="" && 
					$dimensions["height"]!="" && 
					$dimensions["length"]!="" && 
					"$item_id"=="$item_id"."" ) {
                	if( $biggest_size < $_product->get_height() * $_product->get_width() *$_product->get_length())
                	{
                		$biggest_size = $_product->get_height() * $_product->get_width() * $_product->get_length();
                		$biggest_product = $_product;
                	}
                	$i++;
                }
                else if ($dimensions["width"] == "" && $dimensions["height"] == "" && $dimensions["length"] == "") {
                	if( $biggest_size < 
						floatval($options['alto_producto_defecto']) *
						floatval($options['ancho_producto_defecto']) *
						floatval($options['largo_producto_defecto'])
					) {
                		$biggest_size = 
							floatval($options['alto_producto_defecto']) *
							floatval($options['ancho_producto_defecto']) *
							floatval($options['largo_producto_defecto']);
                		$biggest_product = $_product;
                		
                		$biggest_product->set_height( floatval($options['alto_producto_defecto']) );
						$biggest_product->set_width( floatval($options['ancho_producto_defecto']) );
						$biggest_product->set_length( floatval($options['largo_producto_defecto']) );
                	}
                	$i++;
                }

                if ($_product->get_weight() != "" && $_product->get_weight() != "0") {
                	$weight = $weight + $_product->get_weight() * $values['quantity']; 
                }
                else{
                	$weight = $weight + floatval($options['peso_producto_defecto']) * $values['quantity'];	
                }

				$price += $_product->get_price() * $values['quantity'];
            }

			$optionsRC = get_option( 'chilexpress_woo_oficial_region_comuna' );
			if ( empty($optionsRC) || !is_array($optionsRC) ) {
				return false;
			}
			$region = $package['destination']['state'];
			$comuna_name = $package['destination']['city'];
			$availableComunaName = array_key_exists($comuna_name, $optionsRC['comunas_habilitadas']);
			$comuna_code = $availableComunaName ?
				$optionsRC['comunas_habilitadas'][$comuna_name] :
				NULL;
			$regionHabilitada = true;
			$comunaHabilitada = true;

			if(
				isset($optionsRC['regiones_habilitadas']) &&
				!in_array($region, $optionsRC['regiones_habilitadas']) ){
				$regionHabilitada = false;
			}

			if( 
				isset($optionsRC['comunas_habilitadas']) &&
				!in_array($comuna_code, $optionsRC['comunas_habilitadas']) ){
				$comunaHabilitada = false;
			}

			if ($comuna_code == NULL || $region == NULL) {
				// write_log('Shipping calculation aborted: Missing comuna or region in package destination.');
				return false;
			} 
			else if( $regionHabilitada == false ){
				// write_log('Shipping calculation aborted: Region no habilitada.');
				return false;
			}
			else if( $comunaHabilitada == false){
				// write_log('Shipping calculation aborted: Comuna no habilitada.');
				return false;
			}
			else {
				if ($biggest_product) {
					$response = $this->api->obtener_cotizacion(
						isset($options['comuna_origen']) ? $options['comuna_origen'] : $comuna_code,
						$comuna_code ,
						( !isset($options["tipo_prioridad"]) ) ? 2 : $options["tipo_prioridad"],
						isset($options["numero_tcc_origen"]) ? $options["numero_tcc_origen"] : 0,
						$weight, 
						$biggest_product->get_height(), 
						$biggest_product->get_width(), 
						$biggest_product->get_length(), 
						$price
					);
				} else {
					$rate = array(
						'id' => $this->id,
						'label' => "Ningun producto que eligió tiene fijado su tamaño,".
						" comuniquese con el administrador",  // NOSONAR
						'cost' => -1
					);
					$this->add_rate( $rate );
					return;
				}
				if ( is_wp_error( $response ) ) {
					$error_message = $response->get_error_message();
					$rate = array(
						'id' => $this->id,
						'label' => "Hubo un error: $error_message", // NOSONAR
						'cost' => -1
					);
					$this->add_rate( $rate );
				} else {

					$porcentaje_descuento = ( !isset($options['porcentaje_descuento']) ) ? 
						0 :
						intval( $options['porcentaje_descuento'] );

					foreach($response as $soption) {

						$mostrar_servicio = true;
						$service_value = $this->discount_shipping( 
								$soption->serviceValueDiscount, $porcentaje_descuento );
						$label = 'Chilexpress - '. $soption->serviceDescription;

						if($service_value === 0){
							$label = $label.' <strong>Gratis</strong>';
						}

						$servicios_excluidos = array(11,14,15,16,43,44,45,46,47,48);
						if( !in_array($soption->serviceTypeCode, $servicios_excluidos) ) {

							if($soption->serviceTypeCode == 8){ //Si el servicio es AMPM
								$service = $this->validate_ampm_service($options, $products_id);
								if(!$service) {
									$mostrar_servicio = false;
								}

							}

							if($mostrar_servicio) {
								$rate = array(
									'id'    => $this->id.':'. $soption->serviceTypeCode,
									'label' => $label,
									'cost'  => $service_value,
									"meta_data" => array("serviceTypeCode" => $soption->serviceTypeCode)
								);
								// print_r($rate);

								$this->add_rate( $rate );
							}
						}
					}
					
					
				}
			}
			//die();
		}

		function discount_shipping( $price, $porcent ) {

			$discount_price = $price;

			if( $porcent > 0){

				$discount = ($price * $porcent) / 100;
				$discount_price = $price - $discount;

			}
			
			return $discount_price;
		}

		public function validate_ampm_service( $options, $products ) {

			date_default_timezone_set("America/Santiago");
			$date = new DateTime();
			
			$hora = true;
			$dia = true;
			$diaH = true;
			$productos = true;
			$service = true;
			
			/*Inicio validar corte horario*/
			$corte_horario = $options['corte_horario'].":00";
			$hora_actual = $date->format('H:i');

			if( strtotime( $hora_actual ) > strtotime( $corte_horario ) ) {
				$hora = false;
			}
			/*Final validar corte horario*/

			/*Inicio validar dia*/
			$dias_semana = $options['dias_semana'];
			$dia_actual = $date->format('w');

			if( !in_array($dia_actual, $dias_semana) ) {
				$dia = false;
			}
			/*Final validar dia*/

			/*Inicio validar dia habil*/
			$dia_habil = $this->api->validar_dia_habil();
			if( $dia_habil["codigo"] > 0 ) {
				$diaH = false;
			}
			/*Final validar dia habil*/

			/*Inicio validar productos*/
			foreach ( $products as $key => $id ) {

                $_ampm_meta = get_post_meta( $id, '_ampm', true );

				if($_ampm_meta == '') {
					$productos = false;
				}
            }

			/*Final validar productos*/

			if(!$hora || !$dia || !$diaH || !$productos) {
				$service = false;
			}

			return $service;

		}
	}
}
