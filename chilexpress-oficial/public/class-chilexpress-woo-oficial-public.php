<?php

/**
 * The public-facing functionality of the plugin.
 *
 
 * @since      1.3.2
 *
 * @package    Chilexpress_Woo_Oficial
 * @subpackage Chilexpress_Woo_Oficial/public
 */

/**
 * The public-facing functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the public-facing stylesheet and JavaScript.
 *
 * @package    Chilexpress_Woo_Oficial
 * @subpackage Chilexpress_Woo_Oficial/public
 * @author     Chilexpress
 */
class Chilexpress_Woo_Oficial_Public {

	const SHIPPING_KEY = 'shipping';
	const BILLING_KEY = 'billing';
	const PRIORITY_KEY = 'priority';
	const REQUIRED_KEY = 'required';
	const ADDRESS_1_KEY = 'address_1';
	const ADDRESS_2_KEY = 'address_2';
	const SHIPPING_ADDRESS_3_KEY = 'shipping_address_3';
	const BILLING_ADDRESS_3_KEY = 'billing_address_3';
	const LABEL_KEY = 'label';
	const PLACEHOLDER_KEY = 'placeholder';

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.3.2
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.3.2
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.3.2
	 * @param      string    $plugin_name       The name of the plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version = $version;
	}

	/**
	 * Register the stylesheets for the public-facing side of the site.
	 *
	 * @since    1.3.2
	 */
	public function enqueue_styles() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Chilexpress_Woo_Oficial_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Chilexpress_Woo_Oficial_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */
		if ( is_dir( WP_PLUGIN_DIR . '/woocommerce-3.9.2' ) ) {
			$backbone_path = '/woocommerce-3.9.2';
		}
		elseif( is_dir( WP_PLUGIN_DIR . '/woocommerce') )
		{
			$backbone_path = '/woocommerce';
		}

		wp_enqueue_style( $this->plugin_name.'_select2', plugin_dir_url( __FILE__ ) . 'css/select2.min.css', array(), '4.0.3', 'all' );
		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/chilexpress-woo-oficial-public.css', array(), $this->version, 'all' );
		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . '..'.$backbone_path.'assets/css/admin.css', array(), $this->version, 'all' );

	}

	/**
	 * Register the JavaScript for the public-facing side of the site.
	 *
	 * @since    1.3.2
	 */
	public function enqueue_scripts() {
		global $woocommerce;
		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Chilexpress_Woo_Oficial_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Chilexpress_Woo_Oficial_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_script( 
				$this->plugin_name,
				plugin_dir_url( __FILE__ ) . 'js/chilexpress-woo-oficial-public.js',
				array( 'jquery' ),
				$this->version,
				false
		);


		$backbone_url = '';
		if ($woocommerce) {
			$backbone_url =  $woocommerce->plugin_url().'/assets/js/admin/backbone-modal.js';	
		}

		wp_enqueue_script(
		   'backbone-modal',
		   $backbone_url,
		   array('jquery', 'wp-util', 'backbone')
		);

		wp_localize_script( $this->plugin_name, 'woocommerce_chilexpress', array(
	        'base_url'    => plugin_dir_url( __FILE__ ),
	        'nonce'  => wp_create_nonce( 'cwo-clxp-ajax-nonce' )
    	) );

	}

	public function chilexpress_woo_oficial_change_city_to_dropdown( $fields ) {	
		$state = WC()->checkout->get_value('billing_state');
		if (!$state) {
			$state = 'R1';
		}

		if (isset($fields[self::SHIPPING_KEY])){

			$options = array();
			$coverage_data = new Chilexpress_Woo_Oficial_Coverage();
			$comunas = $coverage_data->obtener_comunas($state);
			foreach ($comunas as $key => $value) {
				$options[$key] = $value;
			}

			$city_args = wp_parse_args( array(
				'type' => 'select',
				'options' => $options,
				'input_class' => array(
					'wc-enhanced-select',
				)
			), $fields[self::SHIPPING_KEY]['shipping_city'] );
			$fields['shipping_state'][self::PRIORITY_KEY] = '65';
			$fields[self::SHIPPING_KEY]['shipping_city'] = $city_args;
			$fields[self::BILLING_KEY]['billing_city'] = $city_args; // Also change for billing field

			wc_enqueue_js( "
			jQuery( ':input.wc-enhanced-select' ).filter( ':not(.enhanced)' ).each( function() {
				var select2_args = { minimumResultsForSearch: 5 };
				jQuery( this ).select2( select2_args ).addClass( 'enhanced' );
			});" );

		}
		return $fields;
	}

	public function checkout_fields_override( $fields ) {
		unset($fields[self::BILLING_KEY]['billing_postcode']);
		unset($fields[self::SHIPPING_KEY]['shipping_postcode']);
		
		
		$base_field =  array(
			'label'     => __('Complemento', 'woocommerce'),
			'placeholder'   => _x('N° Depto, Villa, Población, Sector, Etc', self::PLACEHOLDER_KEY, 'woocommerce'),
			'required'  => false,
			'class'     => array('form-row-wide'),
			'clear'     => true,
			'priority'  => 62
		);
		$fields[self::SHIPPING_KEY][self::SHIPPING_ADDRESS_3_KEY] = $base_field;
		$fields[self::BILLING_KEY][self::BILLING_ADDRESS_3_KEY] = $base_field;
		$fields[self::SHIPPING_KEY][self::SHIPPING_ADDRESS_3_KEY] = $base_field;
		$fields[self::BILLING_KEY][self::BILLING_ADDRESS_3_KEY] = $base_field;

		return $fields;
	}

	function custom_checkout_field_update_order_meta($order_id) {
		if ( ! empty( $_POST[self::BILLING_ADDRESS_3_KEY] ) ) {
			update_post_meta( $order_id, self::BILLING_ADDRESS_3_KEY, 
				sanitize_text_field( $_POST[self::BILLING_ADDRESS_3_KEY] )
			);
		}
		if ( ! empty( $_POST[self::SHIPPING_ADDRESS_3_KEY] ) ) {
			update_post_meta( $order_id, self::SHIPPING_ADDRESS_3_KEY,
				sanitize_text_field( $_POST[self::SHIPPING_ADDRESS_3_KEY] )
			);
		}
	}

	public function reorder_fields($fields) {

		$fields[self::ADDRESS_1_KEY][self::LABEL_KEY] = 'Nombre de la Calle';
		$fields[self::ADDRESS_1_KEY][self::REQUIRED_KEY] = true;
		$fields[self::ADDRESS_2_KEY][self::LABEL_KEY] = 'N&uacute;mero';
		$fields[self::ADDRESS_2_KEY][self::PLACEHOLDER_KEY] = 'Número';
		$fields[self::ADDRESS_2_KEY][self::REQUIRED_KEY] = true;
		$fields['state'][self::PRIORITY_KEY] = 42;
		$fields['city'][self::PRIORITY_KEY] = 43;
		$fields['email'][self::PRIORITY_KEY] = 22;
		return $fields;
	}

	public function override_postcode_validation( $address_fields ) {	
		unset($address_fields['postcode']);
		return $address_fields;
	}

	// Argument is required by wordpress?
	public function chilexpress_woo_oficial_validate_order( $posted ) { // NOSONAR

		$packages = WC()->shipping->get_packages();

		$chosen_methods = WC()->session->get( 'chosen_shipping_methods' );
		if( is_array( $chosen_methods ) && in_array( 'chilexpress_woo_oficial', $chosen_methods ) ) {
			foreach ( $packages as $i => $package ) {

				if ( $chosen_methods[ $i ] != "chilexpress_woo_oficial" ) {
					continue;
				}
				$weight = 0;
				foreach ( $package['contents'] as $item_id => $values ) 
				{ 
					$item_id = $item_id."";
					$_product = $values['data']; 
					$weight = $weight + $_product->get_weight() * $values['quantity']; 
				}
				$weight = wc_get_weight( $weight, 'kg' );
			}       
		} 
	}

	static function get_states( $states ) {
		$coverage_data = new Chilexpress_Woo_Oficial_Coverage();
		$regiones = $coverage_data->obtener_regiones();
		$states['CL'] = array();
		if(isset($regiones)){
			foreach ($regiones as $key => $value) {
				$states['CL'][$key] = $value;
			}
		}
		return $states;
	}

	public function rewrite_pdf_label( $wp_rewrite ){
		$wp_rewrite->rules = array_merge(
			['download-order-label/(\d+)/?$' => 'index.php?order_label=$matches[1]'],
			$wp_rewrite->rules
		);
	}
	
	public function add_pdf_label_query_vars( $query_vars ){
		$query_vars[] = 'order_label';
		$query_vars[] = 'order_type';
		return $query_vars;
	}

	function template_redirect_pdf_label(){
		$order_label = intval( get_query_var( 'order_label' ) );
		if ( $order_label ) {
			include plugin_dir_path( __FILE__ ) . '../print-label.php';
			die;
		}
	}

	function format_address($address) {
		if ($address['country'] == 'CL' && $address['stat'.'e'] != NULL) {
			$cov = new Chilexpress_Woo_Oficial_Coverage();
			$comunas = $cov->obtener_comunas($address['sta'.'te']);
			$address["city"] = $comunas[$address["city"]];
		}
		return $address;
	}

	public function action_after_shipping_rate ( $method, $index ) {
	    
	    /*
	    // Targeting checkout page only:
	    if( is_cart() ) return; // Exit on cart page*/

	    /*
            2)  PRIORITARIO: 1 dia (día sig habil hasta 11 am)
            3)  EXPRESS: 1 dia
            4)  EXTENDIDO: 1 - 2 dias
            5)  EXTREMOS: 2 - 3 dias
            8)  AM/PM: 0 dias (mismo día hasta 22 hrs)
            41) ENC. GRANDES: 2 días hábiles, entre 11:00 y 19:00 hrs
            42) ENC. GRANDES EXTENDIDO: 6 días hábiles, entre 11:00 y 19:00 hrs
			53) BASICO: 3 días hábiles, entre 11:00 y 19:00 hrs
			54) BASICO: 4 días hábiles, entre 11:00 y 19:00 hrs
			55) BASICO: 5 días hábiles, entre 11:00 y 19:00 hrs
			56) EXTREMO SUR: 12 días hábiles, entre 11:00 y 19:00 hrs
        */

	    $options = get_option( 'chilexpress_woo_oficial_general' );
		if ( !isset($options['dias_procesamiento']) ){
			$options['dias_procesamiento'] = 0;
		}

		$seller_processing_days = intval( $options['dias_procesamiento'] );
		$psmall_open = "<p><small>[ ";
	    if( 'chilexpress_woo_oficial:2' === $method->id ) {
	        echo __($psmall_open . ($seller_processing_days + 1) .
				" días hábiles (hasta las 11 hrs) ]</small></p>");
	    }

	    if( 'chilexpress_woo_oficial:3' === $method->id ) {
	        echo __($psmall_open . ($seller_processing_days + 1) .
			" días hábiles (hasta las 19 hrs) ]</small></p>");
	    }

	    if( 'chilexpress_woo_oficial:4' === $method->id ) {
	        echo __($psmall_open . ($seller_processing_days + 1) . " a " .
				($seller_processing_days + 2) . " días hábiles ]</small></p>");
	    }

	    if( 'chilexpress_woo_oficial:5' === $method->id ) {
	        echo __($psmall_open . ($seller_processing_days + 2) . " a " .
			($seller_processing_days + 3) . " días hábiles ]</small></p>");
	    }

	    if( 'chilexpress_woo_oficial:8' === $method->id ) {
	    	echo __("<p><small>[ mismo día hasta las 22 hrs ]</small></p>");
	    }

		if( 'chilexpress_woo_oficial:41' === $method->id ) {
	        echo __($psmall_open . ($seller_processing_days + 2) .
				" días hábiles (entre 11:00 y 19:00 hrs) ]</small></p>");
	    }

		if( 'chilexpress_woo_oficial:42' === $method->id ) {
	        echo __($psmall_open . ($seller_processing_days + 6) .
				" días hábiles (entre 11:00 y 19:00 hrs) ]</small></p>");
	    }

		if( 'chilexpress_woo_oficial:53' === $method->id ) {
	        echo __($psmall_open . ($seller_processing_days + 3) .
				" días hábiles (entre 11:00 y 19:00 hrs) ]</small></p>");
	    }

		if( 'chilexpress_woo_oficial:54' === $method->id ) {
	        echo __($psmall_open . ($seller_processing_days + 4) .
				" días hábiles (entre 11:00 y 19:00 hrs) ]</small></p>");
	    }

		if( 'chilexpress_woo_oficial:55' === $method->id ) {
	        echo __($psmall_open . ($seller_processing_days + 5) .
				" días hábiles (entre 11:00 y 19:00 hrs) ]</small></p>");
	    }

		if( 'chilexpress_woo_oficial:56' === $method->id ) {
	        echo __($psmall_open . ($seller_processing_days + 12) .
				" días hábiles (entre 11:00 y 19:00 hrs) ]</small></p>");
	    }

	}

	public function mensaje_nota_tiempo_preparacion () {


		$chosen_methods = WC()->session->get( 'chosen_shipping_methods' ); // Método de envío seleccionado
		$chosen_method = explode(':', reset($chosen_methods) );
		$options = get_option( 'chilexpress_woo_oficial_general' );
		$options['dias_procesamiento'] = ( isset($options['dias_procesamiento']) ) ? $options['dias_procesamiento']:0;
		$message = 'La fecha de entrega de los servicios Chilexpress'.
				   ' considera el tiempo de preparación del pedido, ['.
				   $options['dias_procesamiento'].'] día(s), establecido por la tienda.';

		if( $chosen_method[0] == 'chilexpress_woo_oficial' ){
			echo '<tr class="msg-shipping">
				<td colspan="2" style="text-align:center; font-size:12px;"><strong>' . $message . '</strong></td>
			</tr>';
		}
	}

	public function empty_checkout_get_value( $valor ){
	    $valor = '';
	    return $valor;
	}
	
}
