<?php

/**
 * The admin-specific functionality of the plugin.
 *
 * @since      1.3.2
 *
 * @package    Chilexpress_Woo_Oficial
 * @subpackage Chilexpress_Woo_Oficial/admin
 */

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    Chilexpress_Woo_Oficial
 * @subpackage Chilexpress_Woo_Oficial/admin
 * @author     Chilexpress
 */

// WP_List_Table is not loaded automatically so we need to load it in our application
if( ! class_exists( 'WP_List_Table' ) ) {
    require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' ); // NOSONAR
}


// WC_Settings_API is not loaded automatically so we need to load it in our application.
// Es requerida para cargar la clase 'WC_Shipping_Method'
if( ! class_exists( 'WC_Settings_API' ) ) {
    require_once( ABSPATH . 'wp-content/plugins/woocommerce/includes/abstracts/abstract-wc-settings-api.php' );  // NOSONAR
}

// WC_Shipping_Method is not loaded automatically so we need to load it in our application
if( ! class_exists( 'WC_Shipping_Method' ) ) {
    require_once( ABSPATH . 'wp-content/plugins/woocommerce/includes/abstracts/abstract-wc-shipping-method.php' );  // NOSONAR
}

require_once( plugin_dir_path( __FILE__ ) . 'class-chilexpress-woo-oficial-tablas.php' ); // NOSONAR
require_once( plugin_dir_path( __DIR__ ) . 'includes/class-chilexpress-woo-oficial-shipping-method.php' ); // NOSONAR

define('CWO_LABEL_MAX_LENGTH', 48*1000);
define('CWO_LABEL_COUNT_KEY', 'cwo-count-labels');
class Chilexpress_Woo_Oficial_Admin {

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


	const TRANSPORT_ORDER_NUMBERS = 'transportOrderNumbers';
	const GENERAR_OT = 'generar_ot';
	const IMPRIMIR_OT = 'imprimir_ot';
	const JQUERY = 'jquery';
	const WIDTH_KEY = 'width';
	const HEIGHT_KEY = 'height';


	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.3.2
	 * @param      string    $plugin_name       The name of this plugin.
	 * @param      string    $version    The version of this plugin.
	 */

	/**
	 * The coverage data instance.
	 *
	 * @since    1.3.2
	 * @access   private
	 * @var      Chilexpress_Woo_Oficial_Coverage    $coverage_data    The coverage data instance.
	 */
	private $coverage_data;

	/**
	 * The API instance.
	 *
	 * @since    1.3.2
	 * @access   private
	 * @var      Chilexpress_Woo_Oficial_API    $api    The API instance.
	 */
	private $api;

	/**
	 * The shipping method instance.
	 *
	 * @since    1.3.2
	 * @access   private
	 * @var      Chilexpress_Woo_Oficial_Shipping_Method    $shipping_method    The shipping method instance.
	 */
	private $shipping_method;


	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version = $version;
		$this->coverage_data = new Chilexpress_Woo_Oficial_Coverage();
		$this->api = new Chilexpress_Woo_Oficial_API();
		$this->shipping_method = new Chilexpress_Woo_Oficial_Shipping_Method();

		add_action( 'admin_init', array( $this, 'page_init' ) );
		add_filter( 'woocommerce_admin_order_actions', array($this, 'add_custom_order_status_actions_button'), 100, 2 );
		// we add the style for the custom order actuion buttons	
		add_action( 'admin_head', array($this, 'add_custom_order_status_actions_button_css') );

		// we add the tracking column
		add_filter('manage_edit-shop_order_columns', array($this, 'wc_order_columns'));
		// we add the content of the tracking column
		add_action('manage_shop_order_posts_custom_column', array($this, 'wc_order_column'), 10, 2);

		// we add the tracking column
		add_filter( 'woocommerce_account_orders_columns', array($this, 'wc_add_my_account_orders_column') );	
		// we add the content of the tracking column
		add_action( 'woocommerce_my_account_my_orders_column_order-tracking', array($this, 'wc_user_tracking_column') );
		// we insert the Modal template on the admin footer
		add_action( 'admin_footer', array($this, 'wc_insert_footer') );
		add_action( 'wp_footer', array($this, 'wc_insert_footer'));
	}

	/**
	 * Register the stylesheets for the admin area.
	 *
	 * @since    1.3.2
	 */
	public function enqueue_styles() {

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
		wp_enqueue_style(
			'select2',
			plugin_dir_url( __FILE__ ). '../public/css/select2.min.css'
		);
		wp_enqueue_style(
			$this->plugin_name,
			plugin_dir_url( __FILE__ ) . 'css/chilexpress-woo-oficial-admin.css',
			array(),
			$this->version,
			'all'
		);
		wp_enqueue_style(
			'woocommerce_admin_styles-css',
			$woocommerce->plugin_url().'/assets/css/admin.css',
			array(),
			$woocommerce->version,
			'all'
		);

	}

	/**
	 * Set the style for the tracking order menus
	 *
	 * @since    1.3.2
	 */
	public function add_custom_order_status_actions_button_css() {
	    $action_slug = self::GENERAR_OT; // The key slug defined for your action button
	    $action_slug2 = self::IMPRIMIR_OT; // The key slug defined for your action button

	    echo '<style>.wc-action-button-'.$action_slug.'::after  {'.
	    	' font-size:1.4em; '.
			' font-family: dashicons !important; '.
			' content: "\f111" !important; '.
			' margin-top:-1px !important; '.
			' }</style>';
	    echo '<style>.wc-action-button-'.$action_slug2.'::after {'.
			' font-size:1.4em; '.
			' font-family: dashicons !important; '.
			' content: "\f457" !important; '.
			' margin-top:-1px !important; '.
			' }</style>';

	}

	/**
	 * Register the JavaScript for the admin area.
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


		// We need select2 to show fancy selects with search capabilities
		wp_enqueue_script(
			'select2',
			plugin_dir_url( __FILE__ ) . '../public/js/select2.min.js',
			array(self::JQUERY)
		);
		// We need the the plugin admin js
		wp_enqueue_script(
			$this->plugin_name,
			plugin_dir_url( __FILE__ ) . 'js/chilexpress-woo-oficial-admin.js',
			array( self::JQUERY ),
			$this->version,
			false
		);
		// we use this ajax call to show get global  ars and the right nonce that we need
		wp_localize_script( $this->plugin_name, 'ajax_var', array(
	        'url'    => admin_url( 'admin-ajax.php' ),
	        'nonce'  => wp_create_nonce( 'cwo-ajax-nonce' ),
	        'action' => 'event-list'
    	) );
		// we need to show a modal for edit.php?post_type=shop_order
		$backbone_url = '';
		if ($woocommerce) {
			$backbone_url =  $woocommerce->plugin_url().'/assets/js/admin/backbone-modal.js';	
		}
		
    	wp_enqueue_script(
		   'backbone-modal',
		   $backbone_url,
		   array(self::JQUERY, 'wp-util', 'backbone')
		);
	}



	public function wc_add_my_account_orders_column( $columns ) {

		$new_columns = array();

		foreach ( $columns as $key => $name ) {

			$new_columns[ $key ] = $name;

			// add ship-to after order status column
			if ( 'order-actions' === $key ) {
				$new_columns['order-tracking'] = 'Tracking';
			}
		}

		return $new_columns;
	}

	public function wc_user_tracking_column( $order ) {
		$transportOrderNumbers = $order->get_meta( self::TRANSPORT_ORDER_NUMBERS); // NOSONAR
		if (is_array($transportOrderNumbers)) {
			$out = array();
			foreach($transportOrderNumbers as $transportOrderNumber) {
				$out[] = '<a href="javascript:;" class="tracking-link" data-pid="'.$order->get_id().'"'.
						' data-ot="'.$transportOrderNumber.'">'.$transportOrderNumber.'</a>';
			}
			echo implode(", ", $out);
		} else {
			echo '-';
		}
	}

	public function wc_insert_footer() {
	    require_once plugin_dir_path( __FILE__ ) . 'partials/chilexpress-woo-oficial-admin-tracking-template.php'; // NOSONAR
	}
	
	public function wc_order_columns( $columns ) {
	    $columns["tracking"] = "Tracking";
	    return $columns;
	}

	public function wc_order_column( $colname, $order_id ) {
		if ( $colname == 'tracking') {
			$order = wc_get_order( $order_id );
			$transportOrderNumbers = $order->get_meta( self::TRANSPORT_ORDER_NUMBERS);
			if (is_array($transportOrderNumbers)) {
				$out = array();
				foreach($transportOrderNumbers as $transportOrderNumber) {
					$out[] = '<a href="javascript:;" data-pid="'.$order_id.'" data-ot="'.$transportOrderNumber.'">'.
						$transportOrderNumber.'</a>';
				}
				echo implode(", ", $out);
			}
		}
	}



	// We add our custom buttons when the order is marked as completed
	public function add_custom_order_status_actions_button( $actions, $order ) {
	    // Display the button for all orders that have a 'processing' status


		// The key slug defined for your action button
		$action_slug = self::GENERAR_OT;
		$action_slug2 = self::IMPRIMIR_OT;

		$ot_status = $order->get_meta('ot_status');
		$transportOrderNumbers = $order->get_meta( self::TRANSPORT_ORDER_NUMBERS);

		if( $order->has_shipping_method('chilexpress_woo_oficial') )
		{
			// Set the action button
			if(!$ot_status || ($ot_status == 'created' && count($transportOrderNumbers) == 0)){
				$actions[$action_slug] = array(
					'url'       => wp_nonce_url(
						admin_url(
							'admin.php?page=chilexpress_woo_oficial_generar_ot&action=generar_ot&order_id=' . $order->get_id()
						),
						'generar-ot'
					),
					'name'      => 'Generar OT',
					'action'    => $action_slug,
				);
			}
			if($ot_status == 'created' && count($transportOrderNumbers) > 0) {
				// Set the action button
				$actions[$action_slug2] = array(
					'url'       =>  wp_nonce_url(
						admin_url(
							'admin.php?page=chilexpress_woo_oficial_generar_ot&action=imprimir_ot&order_id=' . $order->get_id()
						),
						'generar-ot'
					),
					'name'      => 'Imprimir OT',
					'action'    => $action_slug2,
				);
			}
		}
	    return $actions;
	}



	public function register_setting_() {
		register_setting( 'chilexpress-woo-oficial', 'chilexpress_woo_oficial');
		add_settings_section(
	        'habilitar_modulo_georeferencia_section_1',
	        '&nbsp;',
	        array($this, 'stp_api_settings_section_callback'),
	        'chilexpress-woo-oficial'
	    );
        add_settings_field(
	        'api_key_georeferencia_enabled',
	        'Módulo de Georeferencia',
	        array($this, 'chilexpress_woo_oficial_field_1_render'),
	        'chilexpress-woo-oficial',
	        'habilitar_modulo_georeferencia_section_1'
	    );
	    add_settings_field(
	        'api_key_georeferencia_value',
	        'API KEY Georeferencia',
	        array($this, 'chilexpress_woo_oficial_field_2_render'),
	        'chilexpress-woo-oficial',
	        'habilitar_modulo_georeferencia_section_1'
	    );
	    add_settings_field(
	        'api_key_generacion_ot_enabled',
	        'Módulo de generacion de OT',
	        array($this, 'chilexpress_woo_oficial_field_3_render'),
	        'chilexpress-woo-oficial',
	        'habilitar_modulo_georeferencia_section_1'
	    );
	    add_settings_field(
	        'api_key_generacion_ot_value',
	        'API KEY Órdenes de transporte',
	        array($this, 'chilexpress_woo_oficial_field_4_render'),
	        'chilexpress-woo-oficial',
	        'habilitar_modulo_georeferencia_section_1'
	    );
	    add_settings_field(
	        'api_key_cotizacion_enabled',
	        'Módulo de Cotización',
	        array($this, 'chilexpress_woo_oficial_field_5_render'),
	        'chilexpress-woo-oficial',
	        'habilitar_modulo_georeferencia_section_1'
	    );
	    add_settings_field(
	        'api_key_cotizacion_value',
	        'API KEY Módulo de Cotización',
	        array($this, 'chilexpress_woo_oficial_field_6_render'),
	        'chilexpress-woo-oficial',
	        'habilitar_modulo_georeferencia_section_1'
	    );
	    add_settings_field(
	        'ambiente',
	        'Ambiente',
	        array($this, 'chilexpress_woo_oficial_field_7_render'),
	        'chilexpress-woo-oficial',
	        'habilitar_modulo_georeferencia_section_1'
	    );
	}

	public function register_setting_general() { // NOSONAR
		$ARTICULOS_TIENDA_STR = 'articulos_tienda'; // NOSONAR

		register_setting('chilexpress-woo-oficial-general','chilexpress_woo_oficial_general');
		register_setting('chilexpress-woo-oficial-general-2','chilexpress_woo_oficial_general');
		register_setting('chilexpress-woo-oficial-general-3','chilexpress_woo_oficial_general');
		register_setting('chilexpress-woo-oficial-general-4','chilexpress_woo_oficial_general');
		register_setting('chilexpress-woo-oficial-general-5','chilexpress_woo_oficial_general');

		/* Datos de la Tienda*/
		add_settings_section(
	        'store_section',
	        'Datos de la tienda',
	        array($this, 'stp_api_settings_section_callback'),
	        'chilexpress-woo-oficial-general'
	    );

		add_settings_field(
	        $ARTICULOS_TIENDA_STR,
	        'Descripción de articulos de la tienda',
	        array($this, 'articulos_tienda_render'),
	        'chilexpress-woo-oficial-general',
	        'store_section'
	    );

	    add_settings_field(
	        'dias_procesamiento',
	        'Días adicionales para la preparación de los pedidos.',
	        array($this, 'preparacion_pedidos_render'),
	        'chilexpress-woo-oficial-general',
	        'store_section'
	    );

	    add_settings_field(
	        'tipo_prioridad',
	        'Tipo de prioridad de los métodos de envío.',
	        array($this, 'tipo_prioridad_render'),
	        'chilexpress-woo-oficial-general',
	        'store_section'
	    );

	    add_settings_field(
	        'ancho_producto_defecto',
	        'Ancho por defecto de los productos (cm).',
	        array($this, 'ancho_producto_defecto_render'),
	        'chilexpress-woo-oficial-general',
	        'store_section'
	    );

	    add_settings_field(
	        'alto_producto_defecto',
	        'Alto por defecto de los productos (cm).',
	        array($this, 'alto_producto_defecto_render'),
	        'chilexpress-woo-oficial-general',
	        'store_section'
	    );

	    add_settings_field(
	        'largo_producto_defecto',
	        'Largo por defecto de los productos (cm).',
	        array($this, 'largo_producto_defecto_render'),
	        'chilexpress-woo-oficial-general',
	        'store_section'
	    );

	    add_settings_field(
	        'peso_producto_defecto',
	        'Peso por defecto de los productos (kg).',
	        array($this, 'peso_producto_defecto_render'),
	        'chilexpress-woo-oficial-general',
	        'store_section'
	    );

		add_settings_field(
	        'porcentaje_descuento',
	        'Descuento a los métodos de envío Chilexpress (%).',
	        array($this, 'porcentaje_descuento_render'),
	        'chilexpress-woo-oficial-general',
	        'store_section'
	    );

		/* Datos de Origen*/
		add_settings_section(
	        'origen_section',
	        'Datos de Origen',
	        array($this, 'stp_api_settings_section_callback'),
	        'chilexpress-woo-oficial-general-2'
	    );
	    add_settings_field(
	        'region_origen',
	        'Region de Origen',
	        array($this, 'region_origen_render'),
	        'chilexpress-woo-oficial-general-2',
	        'origen_section'
	    );
	    add_settings_field(
	        'codigo_comuna_origen',
	        'Código de comuna de origen',
	        array($this, 'comuna_origen_render'),
	        'chilexpress-woo-oficial-general-2',
	        'origen_section'
	    );
	    add_settings_field(
	        'numero_tcc_origen',
	        'Número TCC',
	        array($this, 'numero_tcc_origen_render'),
	        'chilexpress-woo-oficial-general-2',
	        'origen_section'
	    );


		/* Datos de Remitente */
	    add_settings_section(
	        'remitente_section',
	        'Datos del Remitente',
	        array($this, 'stp_api_settings_section_callback'),
	        'chilexpress-woo-oficial-general-3'
	    );
	    add_settings_field(
	        'nombre_remitente',
	        'Nombre',
	        array($this, 'nombre_remitente_render'),
	        'chilexpress-woo-oficial-general-3',
	        'remitente_section'
	    );
	    add_settings_field(
	        'telefono_remitente',
	        'Teléfono',
	        array($this, 'telefono_remitente_render'),
	        'chilexpress-woo-oficial-general-3',
	        'remitente_section'
	    );
	    add_settings_field(
	        'email_remitente',
	        'E-mail',
	        array($this, 'email_remitente_render'),
	        'chilexpress-woo-oficial-general-3',
	        'remitente_section'
	    );
	    add_settings_field(
	        'rut_seller_remitente',
	        'Rut Seller',
	        array($this, 'rut_seller_remitente_render'),
	        'chilexpress-woo-oficial-general-3',
	        'remitente_section'
	    );

	    add_settings_field(
	        'rut_marketplace_remitente',
	        'Rut marketplace',
	        array($this, 'rut_marketplace_remitente_render'),
	        'chilexpress-woo-oficial-general-3',
	        'remitente_section'
	    );


		/* Direccion de Devolucion */

	    add_settings_section(
	        'devolucion_section',
	        'Dirección de devolución',
	        array($this, 'stp_api_settings_section_callback'),
	        'chilexpress-woo-oficial-general-4'
	    );
	    add_settings_field(
	        'region_origen',
	        'Region de devolución:',
	        array($this, 'region_devolucion_render'),
	        'chilexpress-woo-oficial-general-4',
	        'devolucion_section'
	    );
	    add_settings_field(
	        'codigo_comuna_devolucion',
	        'Código de comuna:',
	        array($this, 'comuna_devolucion_render'),
	        'chilexpress-woo-oficial-general-4',
	        'devolucion_section'
	    );
	    add_settings_field(
	        'calle_devolucion',
	        'Nombre de la calle',
	        array($this, 'calle_devolucion_render'),
	        'chilexpress-woo-oficial-general-4',
	        'devolucion_section'
	    );
	    add_settings_field(
	        'numero_calle_devolucion',
	        'Número de la dirección',
	        array($this, 'numero_calle_devolucion_render'),
	        'chilexpress-woo-oficial-general-4',
	        'devolucion_section'
	    );
	    add_settings_field(
	        'complemento_devolucion',
	        'Complemento',
	        array($this, 'complemento_devolucion_render'),
	        'chilexpress-woo-oficial-general-4',
	        'devolucion_section'
	    );



	    /* Metodo entrega AMPM */

	    add_settings_section(
	        'corte_horario_section',
	        'Opciones método entrega el mismo día',
	        array($this, 'corte_horario_section_callback'),
	        'chilexpress-woo-oficial-general-5'
	    );



	    add_settings_field(
	        'corte_horario',
	        'Corte horario (hrs)',
	        array($this, 'corte_horario_render'),
	        'chilexpress-woo-oficial-general-5',
	        'corte_horario_section'
	    );

	    add_settings_field(
	        'dias_semana',
	        'Días de la semana',
	        array($this, 'dias_semana_render'),
	        'chilexpress-woo-oficial-general-5',
	        'corte_horario_section'
	    );

	}

	public function register_setting_region_comuna(){

		register_setting('chilexpress-woo-oficial-region-comuna','chilexpress_woo_oficial_region_comuna');

		add_settings_section(
	        'region_section',
	        '',
	        array($this, 'stp_api_settings_section_callback'),
	        'chilexpress-woo-oficial-region-comuna'
	    );

	    add_settings_field(
	        'regiones_comunas',
	        'Comunas o regiones a las que quiere ofrecer el envío por Chilexpress.',
	        array($this, 'region_comunas_habilitadas_render'),
	        'chilexpress-woo-oficial-region-comuna',
	        'region_section'
	    );
	}

	public function page_init() {
		$this->register_setting_();
		$this->register_setting_general();
		$this->register_setting_region_comuna();
	}

	public function corte_horario_section_callback() {

		echo '<p>Los servicios Chilexpress que pertenecen al método'.
			' "Entrega el mismo día" son: <br> <b>AMPM</b> [ Hoy (hasta las 22:00 hrs) ]</p>';
		echo '<p>Para operar con el método de entrega el mismo día (AMPM)'.
			' solicita a tu ejecutivo la <br> activación del servicio en tu TCC.</p>';
		echo '<p>Recuerda que la admisión de este tipo de envíos en '.
			'sucursales Chilexpress es hasta las <br> 13:00 horas, de Lunes a Viernes. '.
			'Y no pueden ser admitidos en puntos Pick Up y <br> Agentes Autorizados.</p>';
	}

	public function stp_api_settings_section_callback(  ) {
   		echo '';
	}

	public function articulos_tienda_render() {
		$ARTICULOS_TIENDA_STR = 'articulos_tienda'; // NOSONAR
		$articulos = $this->coverage_data->obtener_descripcion_articulos();
		
		if (is_wp_error($articulos) || count($articulos) == 0) {
			echo "Hubo un error al acceder a la api de chilexpress, reintente mas tarde.";
			return;
		}

		$options = get_option( 'chilexpress_woo_oficial_general' );
		if (!isset($options[$ARTICULOS_TIENDA_STR])){
			$options[$ARTICULOS_TIENDA_STR] = 5;
		}
		?>
		 <select name='chilexpress_woo_oficial_general[articulos_tienda]' class="regular-text wc-enhanced-select">
		 	<?php foreach ($articulos as $key => $item) {?>
	    	<option value="<?php echo $item['value']; ?>" <?php if(isset($options[$ARTICULOS_TIENDA_STR]) &&  $options[$ARTICULOS_TIENDA_STR] == $item['value']) { echo 'selected="selected"'; } ?>><?php  // NOSONAR
				if(is_array($item))
				{
					echo $item['label'];
				} else {
					echo $item;
				}
			 ?></option>
	    <?php } ?>
	    </select>
		<?php
	}

	public function tipo_prioridad_render() {

		$options = get_option( 'chilexpress_woo_oficial_general' );
		if ( !isset($options['tipo_prioridad']) ){
			$options['tipo_prioridad'] = 2; // Por defecto es "No Prioritario"
		}
		?>

		<select name='chilexpress_woo_oficial_general[tipo_prioridad]'>
	    	<option value="1" <?php if($options['tipo_prioridad'] == '1') { echo 'selected="selected"'; } ?>
				>Entrega el mismo día
			</option>
	    	<option value="2" <?php if($options['tipo_prioridad'] == '2') { echo 'selected="selected"'; } ?>
				>Entrega desde el día siguiente
			</option>
	    	<option value="0" <?php if($options['tipo_prioridad'] == '0') { echo 'selected="selected"'; } ?>
				>Ambos
			</option>
	    </select>

		<span class="dashicons dashicons-info-outline btn-nota-tipo-prioridad"></span>

	    <div class="nota-tipo-prioridad" style="width: 350px; display: none;">
			<p style="font-size: 9px;">
				Los servicios Chilexpress que pertenecen al método "Entrega el mismo día" son: </br>
				<b>AMPM</b> [ Hoy (hasta las 22:00 hrs) ]  </br></br>

				Los servicios Chilexpress que pertenecen al método "Entrega desde el día siguiente" son: </br> 
				<b>PRIORITARIO</b> [1 día hábil] (hasta las 11:00 hrs) / <b>EXPRESS</b>
				 [1 día hábil] (hasta las 19:00 hrs) 
				 / <b>EXTENDIDO</b> [1 a 2 días hábiles] / <b>EXTREMO</b> [2 a 3 días hábiles] </br></br>

				*Recuerda que la fecha de entrega de los servicios Chilexpress está sujeta
				 a al fecha de admisión en sucursal o del retiro.</br></br>

				**Para operar con el método de entrega el mismo día (<b>AMPM</b>)
				 solicita a tu ejecutivo la activación del servicio en tu TCC.
			</p>
		<div>

		<?php
	}

	public function ancho_producto_defecto_render(){

		$options = get_option( 'chilexpress_woo_oficial_general' );
		if ( !isset($options['ancho_producto_defecto']) ){
			$options['ancho_producto_defecto'] = 1;
		}
		?>

		<input type="number" name="chilexpress_woo_oficial_general[ancho_producto_defecto]"
		 class="regular-text" value="<?php echo $options['ancho_producto_defecto']; ?>" min="1" />

		<?php
	}

	public function alto_producto_defecto_render(){

		$options = get_option( 'chilexpress_woo_oficial_general' );
		if ( !isset($options['alto_producto_defecto']) ){
			$options['alto_producto_defecto'] = 1;
		}
		?>

		<input type="number" name="chilexpress_woo_oficial_general[alto_producto_defecto]"
		 class="regular-text" value="<?php echo $options['alto_producto_defecto']; ?>" min="1" />

		<?php
	}

	public function largo_producto_defecto_render(){

		$options = get_option( 'chilexpress_woo_oficial_general' );
		if ( !isset($options['largo_producto_defecto']) ){
			$options['largo_producto_defecto'] = 1;
		}
		?>

		<input type="number" name="chilexpress_woo_oficial_general[largo_producto_defecto]"
		 class="regular-text" value="<?php echo $options['largo_producto_defecto']; ?>" min="1" />

		<?php
	}

	public function peso_producto_defecto_render(){

		$options = get_option( 'chilexpress_woo_oficial_general' );
		if ( !isset($options['peso_producto_defecto']) ){
			$options['peso_producto_defecto'] = 1;
		}
		?>

		<input type="number" name="chilexpress_woo_oficial_general[peso_producto_defecto]"
		 class="regular-text" value="<?php echo $options['peso_producto_defecto']; ?>" min="1" />

		<?php
	}

	public function porcentaje_descuento_render(){

		$options = get_option( 'chilexpress_woo_oficial_general' );
		if ( !isset($options['porcentaje_descuento']) ){
			$options['porcentaje_descuento'] = 0;
		}
		?>

		<input type="number" id="input_porcentaje_descuento" 
		name="chilexpress_woo_oficial_general[porcentaje_descuento]" class="regular-text"
		 value="<?php echo $options['porcentaje_descuento']; ?>" min="0" max="100" pattern="[0-9]+" />

		<span class="dashicons dashicons-info-outline btn-nota-porcentaje-descuento"></span>

	    <div class="nota-porcentaje-descuento" style="width: 350px; display: none;">
			<p style="font-size: 9px; font-weight: bold;">
			  Ofrece a tu comprador una rebaja a la tarifa de los métodos de envíos Chilexpress, mediante
              un porcentaje de descuento, desde el 0% hasta el 100%. Donde cero refleja una tarifa sin
              descuento y cien representa un envío gratuito.
              <br/><br/>
              *Recuerda que la tarifa con descuento configurada en tu tienda, no representará el cobro
              del servicio Chilexpress en el proceso de facturación. Nuestro proceso solo reconoce
              descuentos establecidos mediantes acuerdos comerciales.
			</p>
		<div>

		<?php
	}

	public function preparacion_pedidos_render() {
		$options = get_option( 'chilexpress_woo_oficial_general' );
		$dias = 0;
		if ( isset($options['dias_procesamiento']) ){
			$dias = $options['dias_procesamiento'];
		}
		?>
		
		<input type="number" name="chilexpress_woo_oficial_general[dias_procesamiento]"
		 class="regular-text" value="<?php echo $dias; ?>" min="0" />

		<span class="dashicons dashicons-info-outline btn-nota-dia-procesamiento"></span>

		<div class="nota-dia-procesamiento" style="width: 350px; display: none;">
			<p style="font-size: 9px; font-weight: bold;">
				La cantidad de días ingresados en este parámetro se adicionará a la promesa
				 de entrega de los métodos de envíos por Chilexpress.
			</p>
		<div>
		 
		<?php
		
	}

	public function chilexpress_woo_oficial_field_1_render() {
		$options = get_option( 'chilexpress_woo_oficial' );
	    ?>
	    <label for="api_key_georeferencia_enabled">
	    	<input type='checkbox' id="api_key_georeferencia_enabled"
			 name='chilexpress_woo_oficial[api_key_georeferencia_enabled]'
			  value='1' 
			  <?php 
			  if(
				isset($options['api_key_georeferencia_enabled']) &&
				$options['api_key_georeferencia_enabled'] == '1') { 
					echo 'checked'.'="checked"';
				} ?>> Habilitar
	    	<br /><small>Necesitas este módulo para poder obtener información
				 actualizada de Regiones y Comunas, crea tu API KEY 
				 <a rel="noopener noreferrer" 
				 href="https://developers.wschilexpress.com/products/georeference/subscribe"
				  target="_blank">aquí</a>.</small>
		</label>
	    <?php
	}

	public function chilexpress_woo_oficial_field_2_render() {
		$options = get_option( 'chilexpress_woo_oficial' );
		if (
			isset($options['api_key_georeferencia_value']) &&
			!empty($options['api_key_georeferencia_value']) &&
			trim($options['api_key_georeferencia_value']) != "" ) {
			$value = $options['api_key_georeferencia_value'];
		} else {
			$value = "134b01b545bc4fb29a994cddedca9379";
		}
	    ?>
	    <input type='text'
		 name='chilexpress_woo_oficial[api_key_georeferencia_value]'
		  value='<?php echo $value;?>' class="regular-text"> 
	    <br /><small>Puedes encontrar esta Api Key, bajo el producto 
			Coberturas en tu página de 
			<a 
				rel="noopener noreferrer"
				href="https://developers.wschilexpress.com/developer"
				target="_blank">perfil</a>.
			</small>
	    <?php
	}

	public function chilexpress_woo_oficial_field_3_render() {
		$options = get_option( 'chilexpress_woo_oficial' );
	    ?>
	    <label for="chilexpress_woo_oficial[api_key_generacion_ot_enabled]">
	    	<input type='checkbox' id="generacion_ot"
			 name='chilexpress_woo_oficial[api_key_generacion_ot_enabled]'
			 value='1'
			  <?php 
			  if(
				isset($options['api_key_generacion_ot_enabled']) &&
				$options['api_key_generacion_ot_enabled'] == '1') {
					 echo 'checked='.'"checked"';
			 } ?>> 
			Habilitar
			<br /><small>Necesitas este módulo para poder obtener generar Ordenes de Transporte e Imprimir
				 tus etiquetas, crea tu API KEY 
				 <a 
				 	rel="noopener noreferrer"
					href="https://developers.wschilexpress.com/products/transportorders/subscribe"
					target="_blank">aquí</a>.</small>
		</label>
	    <?php
	}

	public function chilexpress_woo_oficial_field_4_render() {
		$options = get_option( 'chilexpress_woo_oficial' );
		if (
			isset($options['api_key_generacion_ot_value']) &&
			!empty($options['api_key_generacion_ot_value']) && 
			trim($options['api_key_generacion_ot_value']) != "" 
		) {
			$value = $options['api_key_generacion_ot_value'];
		} else {
			$value = "5a77a19b76a24297ba01c158286641b7";
		}

	    ?>
	    <input type='text' name='chilexpress_woo_oficial[api_key_generacion_ot_value]' 
		value='<?php echo $value; ?>' class="regular-text"> 
	    <br /><small>Puedes encontrar esta Api Key, bajo el producto Envíos en tu página de 
			<a 
				rel="noopener noreferrer" 
				href="https://developers.wschilexpress.com/developer" target="_blank">perfil</a>.</small>
	    <?php
	}

	public function chilexpress_woo_oficial_field_5_render() {
		$options = get_option( 'chilexpress_woo_oficial' );
	    ?>
	    <label for="chilexpress_woo_oficial[api_key_cotizador_enabled]">
	    	<input 
				type='checkbox'
				id="generacion_ot"
				name='chilexpress_woo_oficial[api_key_cotizador_enabled]'
				value='1' 
				<?php if(
							isset($options['api_key_cotizador_enabled']) &&
							$options['api_key_cotizador_enabled'] == '1'
						) {
							 echo 'checked="checked"'; 
					} ?>> 
			Habilitar
			<br /><small>Necesitas este módulo para poder obtener calcular los gastos
				 de envío de forma automática, crea tu API KEY 
				<a 
				rel="noopener noreferrer"
				href="https://developers.wschilexpress.com/products/rating/subscribe"
				target="_blank">aquí</a>.</small>
		</label>
	    <?php
	}

	public function chilexpress_woo_oficial_field_6_render() {
		$options = get_option( 'chilexpress_woo_oficial' );
		if (
				isset($options['api_key_cotizador_value']) &&
				!empty($options['api_key_cotizador_value']) &&
				trim($options['api_key_cotizador_value']) != "" ) {
			$value = $options['api_key_cotizador_value'];
		} else {
			$value = "fd46aa18a9fe44c6b49626692605a2e8";
		}
	    ?>
	    <input type='text' name='chilexpress_woo_oficial[api_key_cotizador_value]'
		 value='<?php echo $value; ?>' class="regular-text"> 
	    <br /><small>Puedes encontrar esta Api Key, bajo el producto Cotizador en tu página de 
			<a 
				rel="noopener noreferrer"
				href="https://developers.wschilexpress.com/developer"
				target="_blank">perfil</a>.</small>
	    <?php
	}

	public function chilexpress_woo_oficial_field_7_render() {
		$options = get_option( 'chilexpress_woo_oficial' );
	    ?>
	    <select name='chilexpress_woo_oficial[ambiente]'>
	    	<option value="staging" 
			<?php if(isset($options['ambiente']) && $options['ambiente'] == 'staging') {
				 echo 'selected="selected"'; 
			} ?>>Staging</option>
	    	<option value="production" <?php if(isset($options['ambiente']) && $options['ambiente'] == 'production') {
				 echo 'selected="selected"';
			} ?>>Production</option>
	    </select>
	    <br /><small>
			Elige el ambiente de Staging para hacer las pruebas con tu plugin,
			y el ambiente de production una vez estas seguro(a) que todo funciona correctamente.</small>
	    <?php
	}


	/* Datos generales origen */

	public function region_origen_render() {
		$regiones = $this->coverage_data->obtener_regiones();
		$options = get_option( 'chilexpress_woo_oficial_general' );
		?>
		 <select name='chilexpress_woo_oficial_general[region_origen]' 
		 class="regular-text wc-enhanced-select select-county" data-city="comuna_origen">
		 	<?php foreach ($regiones as $key => $value) {?>
	    	<option value="<?php echo $key; ?>" <?php
				if(isset($options['region_origen']) &&  $options['region_origen'] == $key) {
					echo 'selected="selected"';
				} ?>><?php echo $value; ?></option>
	    <?php } ?>
	    </select>
		<?php
	}

	public function comuna_origen_render() {
		$options = get_option( 'chilexpress_woo_oficial_general' );
		if (isset($options['region_origen'])) {
			$region = $options['region_origen'];
		} else {
			$region = "R1";
		}
		$comunas = $this->coverage_data->obtener_comunas($region);
		$comuna_id = reset($comunas); // First element's value
		?>
		 <input type="text" disabled="true" 
		  value="<?php
		    if(isset($options['comuna_origen'])){
			 	echo $options['comuna_origen']; 
			} else {
				echo $comuna_id;
			}?>" style="width:6em;" />
		
		 <select name="chilexpress_woo_oficial_general[comuna_origen]" id="comuna_origen"
		  class="regular-text wc-enhanced-select select-city">
	    <?php foreach ($comunas as $key => $value) {?>
	    	<option value="<?php echo $value; ?>"
			<?php if(isset($options['comuna_origen']) &&  $options['comuna_origen'] == $value) {
				echo 'selected="selected"';
			} ?>
			><?php echo $key; ?></option>
	    <?php } ?>
	    </select>
		<?php
	}

	public function numero_tcc_origen_render() {
		$options = get_option( 'chilexpress_woo_oficial_general' );
		?>
		 <input type="number" min="0" name='chilexpress_woo_oficial_general[numero_tcc_origen]'
		 value="<?php if(isset($options['numero_tcc_origen'])) { 
			echo $options['numero_tcc_origen']; 
			}?>" class="regular-text"/>
		<?php
	}


	/* Datos generales remitente */
	
	public function nombre_remitente_render() {
		$options = get_option( 'chilexpress_woo_oficial_general' );
		?>
		 <input type="text" name='chilexpress_woo_oficial_general[nombre_remitente]'
		 value="<?php if(isset($options['nombre_remitente']))  {
			 echo $options['nombre_remitente']; 
			 }?>" class="regular-text"/>
		<?php
	}

	public function telefono_remitente_render() {
		$options = get_option( 'chilexpress_woo_oficial_general' );
		?>
		 <input type="number" min="0" name='chilexpress_woo_oficial_general[telefono_remitente]'
		 value="<?php if(isset($options['telefono_remitente'])) { 
			echo $options['telefono_remitente']; 
			}?>" class="regular-text"/>
		<?php
	}

	public function email_remitente_render() {
		$options = get_option( 'chilexpress_woo_oficial_general' );
		?>
		 <input type="text" name='chilexpress_woo_oficial_general[email_remitente]'
		 value="<?php if(isset($options['email_remitente'])) {
			 echo $options['email_remitente']; 
			}?>" class="regular-text"/>
		<?php
	}

	public function rut_seller_remitente_render() {
		$options = get_option( 'chilexpress_woo_oficial_general' );
		?>
		 <input type="text" name='chilexpress_woo_oficial_general[rut_seller_remitente]' 
		 value="<?php if(isset($options['rut_seller_remitente'])) {
			 echo $options['rut_seller_remitente']; 
			 }?>" class="regular-text"/>
		<?php
	}

	public function rut_marketplace_remitente_render() {
		$options = get_option( 'chilexpress_woo_oficial_general' );
		?>
		 <input type="text" name='chilexpress_woo_oficial_general[rut_marketplace_remitente]'
		 value="<?php if(isset($options['rut_marketplace_remitente'])) { 
			echo $options['rut_marketplace_remitente']; 
		}?>" class="regular-text"/>
		<?php
	}


	/* Datos generales devolucion */

	public function region_devolucion_render() {
		$regiones = $this->coverage_data->obtener_regiones();
		$options = get_option( 'chilexpress_woo_oficial_general' );
		?>
		 <select name='chilexpress_woo_oficial_general[region_devolucion]' 
		 class="regular-text wc-enhanced-select select-county" data-city="comuna_devolucion">
		 	<?php foreach ($regiones as $key => $value) {?>
	    	<option value="<?php echo $key; ?>"
			<?php
				 if(isset($options['region_devolucion']) &&  $options['region_devolucion'] == $key) {
					 echo 'selected="selected"'; 
				} ?>><?php echo $value; ?></option>
	    <?php } ?>
	    </select>
		<?php
	}

	public function comuna_devolucion_render() {
		$options = get_option( 'chilexpress_woo_oficial_general' );

		if (isset($options['region_devolucion'])) {
			$region = $options['region_devolucion'];
		} else {
			$region = "R1";
		}		

		$comunas = $this->coverage_data->obtener_comunas($region);
		$comuna_id = reset($comunas); // First element's value

		?>
		 <input type="text" disabled="true" 
		 	value="<?php
			 if(isset($options['comuna_devolucion'])){
				 echo $options['comuna_devolucion'];
			 } else {
				echo $comuna_id;
			 }?>" style="width:6em;"/>
		
		 <select name="chilexpress_woo_oficial_general[comuna_devolucion]"
		 id="comuna_devolucion" class="regular-text wc-enhanced-select select-city">
	    <?php foreach ($comunas as $key => $value) {?>
	    	<option 
				value="<?php echo $value; ?>"
				<?php if(isset($options['comuna_devolucion']) &&  $options['comuna_devolucion'] == $value) {
					 echo 'selected="selected"';
				} ?>><?php echo $key; ?></option>
	    <?php } ?>
	    </select>
		<?php
	}

	public function calle_devolucion_render() {
		$options = get_option( 'chilexpress_woo_oficial_general' );
		?>
		 <input type="text" name='chilexpress_woo_oficial_general[calle_devolucion]'
			value="<?php if(isset($options['calle_devolucion'])) { echo $options['calle_devolucion']; }?>"
			class="regular-text"/>
		<?php
	}

	public function numero_calle_devolucion_render() {
		$options = get_option( 'chilexpress_woo_oficial_general' );
		?>
		 <input type="number" min="0" name='chilexpress_woo_oficial_general[numero_calle_devolucion]'
		 	value="<?php if(isset($options['numero_calle_devolucion'])){ echo $options['numero_calle_devolucion']; }?>"
			class="regular-text"/>
		<?php
	}

	public function complemento_devolucion_render() {
		$options = get_option( 'chilexpress_woo_oficial_general' );
		?>
		 <input type="text" name='chilexpress_woo_oficial_general[complemento_devolucion]'
		  value="<?php if(isset($options['complemento_devolucion'])){ echo $options['complemento_devolucion']; }?>"
		  class="regular-text"/>
		<?php
	}

	/* Opciones servicio AMPM */

	public function corte_horario_render() {
		$options = get_option( 'chilexpress_woo_oficial_general' );
		if ( !isset($options['corte_horario']) ){
			$options['corte_horario'] = 12; // Por defecto es "Hasta 12:00 horas"
		}
		?>
		<select 
			name='chilexpress_woo_oficial_general[corte_horario]'
			class="regular-text wc-enhanced-select select-county">
	    	<option value="10" 
			<?php if(isset($options['corte_horario']) &&  $options['corte_horario'] == 10) { 
				echo 'selected="selected"'; 
			} ?>>
			Hasta 10:00 horas</option>
	    	<option value="11" 
			<?php if(isset($options['corte_horario']) &&  $options['corte_horario'] == 11) {
				echo 'selected="selected"';
			} ?>>
			Hasta 11:00 horas</option>
	    	<option value="12" 
			<?php if(isset($options['corte_horario']) &&  $options['corte_horario'] == 12) {
				echo 'selected="selected"'; 
			} ?>>
			Hasta 12:00 horas</option>
	    </select>

	    <span class="dashicons dashicons-info-outline btn-nota-corte-horario"></span>

	    <div class="nota-corte-horario" style="width: 350px; display: none;">
			<p style="font-size: 9px; font-weight: bold;">
				La hora seleccionada permitirá restringir la visualización del servicio AMPM en el carro de compra.
			</p>
		<div>
		<?php
	}

	public function dias_semana_render() {

		$options = get_option( 'chilexpress_woo_oficial_general' );

		if( !isset($options['dias_semana']) ){
			$options['dias_semana'] = array(1,2,3,4,5);
		}
		?>
		<ul>
			<li><input type="checkbox" name="chilexpress_woo_oficial_general[dias_semana][]" value="1" 
			<?php if( in_array(1, $options['dias_semana']) ) { echo 'checked'; } ?> />Lunes</li>
			<li><input type="checkbox" name="chilexpress_woo_oficial_general[dias_semana][]" value="2" 
			<?php if( in_array(2, $options['dias_semana']) ) { echo 'checked'; } ?> />Martes</li>
			<li><input type="checkbox" name="chilexpress_woo_oficial_general[dias_semana][]" value="3" 
			<?php if( in_array(3, $options['dias_semana']) ) { echo 'checked'; } ?> />Miércoles</li>
			<li><input type="checkbox" name="chilexpress_woo_oficial_general[dias_semana][]" value="4" 
			<?php if( in_array(4, $options['dias_semana']) ) { echo 'checked'; } ?> />Jueves</li>
			<li><input type="checkbox" name="chilexpress_woo_oficial_general[dias_semana][]" value="5" 
			<?php if( in_array(5, $options['dias_semana']) ) { echo 'checked'; } ?> />Viernes</li>
		</ul>

		<span class="dashicons dashicons-info-outline btn-nota-dias-semana"></span>

		<div class="nota-dias-semana" style="width: 350px; display: none;">
			<p style="font-size: 9px; font-weight: bold;">
				Selecciona los días de la semana que quieras ofrecer servicio AMPM en el carro de compra.
			</p>
		<div>
		<?php

	}


	/* Datos Regiones y Comunas Habiliadas */

	public function region_comunas_habilitadas_render() { // NOSONAR
		$regiones = $this->coverage_data->obtener_regiones();
	
		// Trae opciones y normaliza a array vacío si no hay nada
		$options = get_option( 'chilexpress_woo_oficial_region_comuna' );
		if ( !is_array($options) ) {
			$options = [];
		}
	
		// Atajos para no repetir condiciones
		$regiones_habilitadas = (isset($options['regiones_habilitadas']) && is_array($options['regiones_habilitadas']))
			? $options['regiones_habilitadas'] : [];
		$comunas_habilitadas = (isset($options['comunas_habilitadas']) && is_array($options['comunas_habilitadas']))
			? $options['comunas_habilitadas'] : [];
		?>
	
		<ul>
			<li class="check-todo"><input type="checkbox" id="check_todo" />TODO</li>
			<?php 
				foreach ($regiones as $region_id => $region) {
	
					// Si no hay opciones, queda sin 'checked'
					$marcado_region = in_array($region_id, $regiones_habilitadas, true) ? 'checked' : '';
	
					echo '<li><input type="checkbox" class="check-region" id="'.$region_id.
						'" name="chilexpress_woo_oficial_region_comuna[regiones_habilitadas][]" value="'.
						$region_id.'" '.$marcado_region.' /><span class="nombre-region" id="'.$region_id.'">'.$region.
						'</span></li>';
	
					echo '<ul class="ul-comunas hijo-'.$region_id.'">';
						
						$comunas = $this->coverage_data->obtener_comunas($region_id);
	
						foreach ($comunas as $comuna => $comuna_id) {
	
							// Si no hay opciones, queda sin 'checked'
							$marcado_comuna = (array_search($comuna_id, $comunas_habilitadas, true) !== false) ? 'checked' : '';
							
							echo '<li>
									<input type="checkbox" 
										   class="check-comuna check-comuna-'.$region_id.'" 
										   data-region_padre="'.$region_id.'" 
										   name="chilexpress_woo_oficial_region_comuna[comunas_habilitadas]['.$comuna.']"
										   value="'.$comuna_id.'" '.$marcado_comuna.' />
									'.$comuna.'
								 </li>';
						}
	
					echo '</ul>';
				}
			?>
		</ul>
		<?php
	}
	


	public function add_menus() {
		add_menu_page ( 'Chilexpress', 'Chilexpress', 'manage_options',
			'chilexpress_woo_oficial_menu', array($this, 'chilexpress'), 'dashicons-admin-generic' );
		add_submenu_page ( 'chilexpress_woo_oficial_menu',
			'Habilitación de Módulos', 'Habilitación de Módulos', 'manage_options',
			'chilexpress_woo_oficial_menu', array($this, 'habilitar_modulos') );
		add_submenu_page ( 'chilexpress_woo_oficial_menu',
			'Configuración General', 'Configuración General', 'manage_options',
			'chilexpress_woo_oficial_submenu2', array($this, 'configuracion_general') );
		add_submenu_page ( 'chilexpress_woo_oficial_menu',
			'Comunas y Regiones', 'Comunas y Regiones', 'manage_options',
			'chilexpress_woo_oficial_habilitar_regiones_comunas', array($this, 'config_comunas_regiones') );
		add_submenu_page ( 'chilexpress_woo_oficial_menu',
			'Pedidos Chilexpress', 'Pedidos Chilexpress', 'manage_options',
			'chilexpress_woo_oficial_listado_pedidos', array($this, 'listado_pedidos_chilexpress') );
		add_submenu_page ( 'chilexpress_woo_oficial_menu',
			'Generador de OT', 'Generador de OT', 'manage_options',
			'chilexpress_woo_oficial_generar_ot', array($this, self::GENERAR_OT) );
	}

	public function generar_ot() {
		
		if (isset($_GET['_wpnonce'])) {
			$nonce = $_GET['_wpnonce'];
			if ( ! wp_verify_nonce( $nonce, 'generar-ot' ) ) {
			     die( 'Invalid Nonce' ); 
			}
		} else {
			die( 'Missing Nonce' );
		}

		$action = isset($_GET['action'])?sanitize_text_field($_GET['action']):self::GENERAR_OT;
		$order_id = isset($_GET['order_id'])?sanitize_text_field($_GET['order_id']):1;
		if (!$order_id && $order_id < 0) {
			die("Invalid Order Id");
		}
		$order = wc_get_order( $order_id );
		
		if (!$order) {
			die("Invalid Order");	
		}

		if ($action == self::GENERAR_OT) {
			$this->_generar_ot($order_id);
		} else if($action == self::IMPRIMIR_OT) {
			$this->_generar_e_imprimir_ot($order);
		}
	}

	public function _generar_ot( $orders ) { // NOSONAR
		
		$orders = ( is_array($orders) ) ? $orders : array($orders);
		$options = get_option( 'chilexpress_woo_oficial_general' );

		if (
			isset($_POST['subaction']) && 
			sanitize_text_field($_POST['subaction']) == 'generar' || isset($_POST['subaction']) && sanitize_text_field($_POST['subaction']) == 'generar-multiple') {
			
			$errorMsg = '';
			$payload = $this->_generar_payload_para_ot( $orders, $options, $errorMsg );
			
			
			// se validan los datos. Si no son consistentes se muestra un error
			if( !$payload ) {
				?><div id="message.error.info_ot" class="notice notice-error"> 
				<h1>Estimado Usuario</h1> <p>
				<p> <strong> <?php echo $errorMsg ?></strong>.
				La Orden de Transporte no fue generada, por favor edite los datos y vuelva a intentarlo más tarde.</p>
				</div> <?php
			  die();
			}
			$result = $this->api->generar_ot($payload);

			if ( is_wp_error( $result ) ) {
				$error_message = $result->get_error_message();
				echo "Something went wrong: $error_message";
				die();
			} else {
				$json_response = $result;
					
				$statusCode = $json_response->statusCode;
				$statusDescriptions = array();
				$countOfGeneratedOrders = 0;
				if($statusCode == 99) // statusCode 99 significa error al llamar a la API
				{
					$countOfGeneratedOrders = count($json_response->data->detail);
					for($i = 0; $i < $countOfGeneratedOrders; $i++ )
					{
						$statusDescriptions[] =  $json_response->data->detail[$i]->statusDescription;
					}
					if ($countOfGeneratedOrders > 0) {
						?><div id="message2" class="notice notice-error">
							<p>Hubo un error al llamar a la API de Chilexpress <strong>
								<?php echo esc_html(implode(", ", $statusDescriptions));?></strong>.
								 </p>
								 <p>La orden de transporte no fue generada, por favor intentelo mas tarde.</p>
						</div><?php
					} else {
						?><div id="message2" class="notice notice-error">
							<p>Hubo un error al llamar a la API de Chilexpress 
								<strong>No hay ordenes generadas</strong>.
							 </p><p>La orden de transporte no fue generada, por favor intentelo mas tarde.</p>
							 </div><?php
					}
					die();
				}

				// tenemos un llamado sin error asi que continuamos
				$countOfGeneratedOrders = $json_response->data->header->countOfGeneratedOrders;
				$certificateNumber = $json_response->data->header->certificateNumber;

				$transportOrderNumbers = array();
				$barcodes = array();
				$labelsData = array();
				$references = array();
				$productDescriptions = array();
				$serviceDescription_ = array();
				$classificationData_ = array();
				$companyName_ = array();
				$recipient_ = array();
				$address_ = array();
				$printedDate_ = array();

				for($i = 0; $i < $countOfGeneratedOrders; $i++ ) {
					$transportOrderNumbers[$i] = $json_response->data->detail[$i]->transportOrderNumber;
					$barcodes[$i] = $json_response->data->detail[$i]->barcode;
					$references[$i] = $json_response->data->detail[$i]->reference;
					$productDescriptions[$i] = $json_response->data->detail[$i]->productDescription;
					$serviceDescription_[$i] = $json_response->data->detail[$i]->serviceDescription;
					$classificationData_[$i] = $json_response->data->detail[$i]->classificationData;
					$companyName_[$i] = $json_response->data->detail[$i]->companyName;
					$recipient_[$i] = $json_response->data->detail[$i]->recipient;
					$address_[$i] = $json_response->data->detail[$i]->address;
					$printedDate_[$i] = $json_response->data->detail[$i]->printedDate;
					$labelsData[] = str_split(
										$json_response->data->detail[$i]->label->labelData,
										CWO_LABEL_MAX_LENGTH
									);
				}

				if($_POST['subaction'] != 'generar-multiple'){

					$objSimpleOrder = wc_get_order( $orders[0] );
					$objSimpleOrder->update_meta_data( self::TRANSPORT_ORDER_NUMBERS, $transportOrderNumbers );
					$objSimpleOrder->update_meta_data( 'barcodes', $barcodes );
					$objSimpleOrder->update_meta_data( 'references', $references );
					$objSimpleOrder->update_meta_data( 'productDescriptions', $productDescriptions );
					$objSimpleOrder->update_meta_data( 'serviceDescription_', $serviceDescription_ );
					$objSimpleOrder->update_meta_data( 'classificationData_', $classificationData_ );
					$objSimpleOrder->update_meta_data( 'companyName_', $companyName_ );
					$objSimpleOrder->update_meta_data( 'recipient_', $recipient_ );
					$objSimpleOrder->update_meta_data( 'address_', $address_ );
					$objSimpleOrder->update_meta_data( 'printedDate_', $printedDate_ );
					$objSimpleOrder->update_meta_data( 'ot_status', 'created' );
					$objSimpleOrder->update_meta_data( 'certificateNumber', $certificateNumber );
					$counts = array();
					for($a = 0; $a <count($labelsData); $a++) {
						$counts[$a] = count($labelsData[$a]);
						for($i = 0; $i < count($labelsData[$a]); $i++)  {
							$objSimpleOrder->update_meta_data( "labelsData_".$a."_".($i+1), $labelsData[$a][$i] );
						}
						
						
					}
					$objSimpleOrder->update_meta_data( 'labelsPartsPerLabel', $counts );
					$objSimpleOrder->update_meta_data( 'labelsCount', count($labelsData));
					$objSimpleOrder->save();

					$this->enviar_notificacion( $objSimpleOrder );
				}

				if($_POST['subaction'] == 'generar-multiple'){
					
					foreach ($references as $key => $refOrder) {

						$porciones = explode("-", $refOrder);
						$orderId = $porciones[1];
						
						$objOrder = wc_get_order( $orderId );
						$objOrder->update_meta_data( 
							self::TRANSPORT_ORDER_NUMBERS,
							array($transportOrderNumbers[$key])
						);
						$objOrder->update_meta_data( 'barcodes', array( $barcodes[$key] ) );
						$objOrder->update_meta_data( 'references', array( $references[$key] ) );
						$objOrder->update_meta_data( 'productDescriptions', array( $productDescriptions[$key] ) );
						$objOrder->update_meta_data( 'serviceDescription_', array( $serviceDescription_[$key] ) );
						$objOrder->update_meta_data( 'classificationData_', array( $classificationData_[$key] ) );
						$objOrder->update_meta_data( 'companyName_', array( $companyName_[$key] ) );
						$objOrder->update_meta_data( 'recipient_', array( $recipient_[$key] ) );
						$objOrder->update_meta_data( 'address_', array( $address_[$key] ) );
						$objOrder->update_meta_data( 'printedDate_', array( $printedDate_[$key] ) );						
						$objOrder->update_meta_data( 'ot_status', 'created' );
						$objOrder->update_meta_data( 'certificateNumber', $certificateNumber );
						$counts = array();
						for($a = 0; $a <count($labelsData); $a++) {
							$counts[$a] = count($labelsData[$a]);
							for($i = 0; $i < count($labelsData[$a]); $i++)  {
								$objOrder->update_meta_data( "labelsData_".$a."_".($i+1), $labelsData[0][$i]);
							}
							
							
						}
						$objOrder->update_meta_data( 'labelsPartsPerLabel', $counts );
						$objOrder->update_meta_data( 'labelsCount', count($labelsData));
						$objOrder->save();
							
						$this->enviar_notificacion( $objOrder );
					}
				}

				if($_POST['subaction'] != 'generar-multiple'){
					$redireccion = (isset($_GET['pedidos_cxp'])) ? 
						admin_url('admin.php?page=chilexpress_woo_oficial_listado_pedidos') :
						admin_url('edit.php?post_type=shop_order');
			
				?>
					<p>Redireccionando...</p>
					<script type="text/javascript">document.location = '<?php echo $redireccion; ?>';</script>
				<?php
					die();
				}
			}
		}

		
		$continuar_orden = true;
		$continuar_orden = $continuar_orden && true; // this is better to add than NOSONARQUBE 
		if (!isset($options["numero_tcc_origen"]) ||($options["numero_tcc_origen"]) == ""){
			$continuar_orden = false;
			?>
			<div id="message" class="notice notice-error"><p>Debe ingresar un <strong>Número TCC</strong>
			 en la configuración general de Chilexpress para <strong>Generar una OT</strong>.</p></div>
			<?php
		}
		if (!isset($options["comuna_origen"]) || ($options["comuna_origen"]) == "" ){
			$continuar_orden = false;
			?>
			<div id="message2" class="notice notice-error">
				<p>Debe seleccionar una <strong>Comuna de Origen</strong>
				 en la configuración general de Chilexpress para <strong>Generar una OT</strong>.</p></div>
			<?php
		}
		if (!isset($options["rut_marketplace_remitente"]) ||($options["rut_marketplace_remitente"]) == ""){
			$continuar_orden = false;
			?>
			<div id="message2" class="notice notice-error">
				<p>Debe seleccionar un <strong>Rut Marketplace</strong> 
				en la configuración general de Chilexpress para <strong>Generar una OT</strong>.</p></div>
			<?php
		}

		if( !isset( $_POST['subaction'] ) || isset( $_POST['subaction'] ) && sanitize_text_field( $_POST['subaction'] ) != 'generar-multiple' ){
			$order = wc_get_order( $orders[0] );
			$order_data = $order->get_data();
			$complemento = get_post_meta($order->get_id(),'_shipping_address_3', true); // NOSONAR
			$region = $order_data["shipping"]["state"];
		    $comunas = $this->coverage_data->obtener_comunas($region);
			$comuna_name = $order_data["shipping"]["city"];
			$availableComunaName = array_key_exists($comuna_name, $comunas);
            $comuna_code = $availableComunaName ? $comunas[$comuna_name] : $comuna_name;

			require plugin_dir_path( __FILE__ ) . 'partials/chilexpress-woo-oficial-admin-form-ot.php'; // NOSONAR
		}	
	}

	public function _generar_payload_para_ot( $orders, $options, &$errorMsg ) { // NOSONAR

		$shipping_address_3 = '_shipping_address_3';
		$titles = "titles";
		$DEFAULT = "DEFAULT";
		$ARTICULOS_TIENDA_STR = 'articulos_tienda'; // NOSONAR
		$CHILEXPRESS_BASICO = 'Chilexpress - BASICO';
		$metodos = array (
			2  => 'Chilexpress - PRIORITARIO',
			3  => 'Chilexpress - EXPRESS',
			4  => 'Chilexpress - EXTENDIDO',
			5  => 'Chilexpress - EXTREMOS',
			8  => 'Chilexpress - SERVICIO AMPM',
			41 => 'Chilexpress - ENC. GRANDES',
			42 => 'Chilexpress - ENC. GRANDES EXTENDIDO',
			53 => $CHILEXPRESS_BASICO,
			54 => $CHILEXPRESS_BASICO,
			55 => $CHILEXPRESS_BASICO,
			56 => 'Chilexpress - BASICO EXTREMO SUR',
		);
		
		$payload_header = array(
			"certificateNumber"          => 0, //Número de certificado, si no se ingresa se creará uno nuevo
			// Número de Tarjeta Cliente Chilexpress (TCC)
			"customerCardNumber"         => $options["numero_tcc_origen"],
			"countyOfOriginCoverageCode" => $options["comuna_origen"], // Comuna de origen
			"labelType"                  => 2, // Imagen
			// Rut asociado al Marketplace
			"marketplaceRut"             => intval($options["rut_marketplace_remitente"]),
			"sellerRut"                  => $DEFAULT, // Rut asociado al Vendedor,
			"sourceChannel"              => 5 // woocommerce se identifica en el sistema como 5
		);

		foreach ($orders as $key => $id) 
		{
			$order = wc_get_order( $id );
			$order_id = $order->get_id();
			$order_data = $order->get_data();
			$complemento = get_post_meta($order_id, $shipping_address_3, true) ?
					get_post_meta($order_id,$shipping_address_3, true) :
					get_post_meta($order_id,'_billing_address_3', true);
			$serviceTypeId = 3; // POR DEFECTO
			$region = $order_data["shipping"]["state"];
		    $comunas = $this->coverage_data->obtener_comunas($region);
			$comuna_name = $order_data["shipping"]["city"];
			$availableComunaName = array_key_exists($comuna_name, $comunas);
            $comuna_code = $availableComunaName ? $comunas[$comuna_name] : $comuna_name;

			$order_shipping_methods = $order->get_shipping_methods();
			
			$found_service_code_in_meta = false;
			foreach ( $order_shipping_methods as $method_id => $method ) {
				foreach( $method->get_all_formatted_meta_data() as $meta_id => $meta_array ) {
					if ($meta_array->key === 'serviceTypeCode') {
						$serviceTypeId = $meta_array->value;
						$found_service_code_in_meta = true;
					}
				}
			}
			$order_shipping_method = $order->get_shipping_method();
			if (!$found_service_code_in_meta) {
				foreach ( $metodos as $method_id => $method_name ) {
					if ( $order_shipping_method == $method_name ) {
						$serviceTypeId = $method_id;
					}
				}
			}

			$payload_address_destino = array(
				"addressId"                  => 0, // Id de la dirección obtenida de la API Validar dirección
				// Cobertura de destino obtenido por la API Consultar Coberturas
				"countyCoverageCode"         => $comuna_code,
				"streetName"                 => $order_data["shipping"]["address_1"], // Nombre de la calle
				"streetNumber"               => $order_data["shipping"]["address_2"], // Numeración de la calle
				"supplement"                 => $complemento, // Información complementaria de la dirección
				"addressType"                => "DEST", // Tipo de dirección; DEST = Entrega, DEV = Devolución.
				// Indicador si es una entrega en oficina comercial (true) o entrega en domicilio (false)
				"deliveryOnCommercialOffice" => false, 
				"commercialOfficeId"         => "",
				"observation"                => $DEFAULT // Observaciones adicionales
			);

			$payload_address_devolucion = array(
				"addressId"=> 0,
				"countyCoverageCode"         => $options['comuna_devolucion'],
				"streetName"                 => $options['calle_devolucion'],
				"streetNumber"               => $options['numero_calle_devolucion'],
				"supplement"                 => $options['complemento_devolucion'],
				"addressType"                => "DEV",
				"deliveryOnCommercialOffice" => false,
				"observation"                => $DEFAULT
			);

			$payload_contact_devolucion = array(
				"name"        => $options['nombre_remitente'],
				"phoneNumber" => $options['telefono_remitente'],
				"mail"        => $options['email_remitente'],
				"contactType" => "R" // Tipo de contacto; Destinatario (D), Remitente (R)
			);

			// Formato de tel'efono 
			$phone_destination = $order_data["billing"]["phone"];
			if( strlen($phone_destination) === 8 ){
				$phone_destination = "569".$phone_destination;
			}
			else if( strlen($phone_destination) === 9 ){
				$phone_destination = "56".$phone_destination;
			}
			else if( strlen($phone_destination) >= 10 ){
				if( substr($phone_destination, 0, 1) === "+" ){
					$phone_destination = substr($phone_destination, 0, 10);
				}
			}
			else{
				$errorMsg = "El teléfono del destino ($phone_destination) tiene un formato incorrecto";
				return false;
			}


			$payload_contact_destino = array(
				"name"        => $order_data["shipping"]["first_name"]." ".$order_data["shipping"]["last_name"],
				"phoneNumber" => $phone_destination,
				"mail"        => $order_data["billing"]["email"],
				"contactType" => "D" // Tipo de contacto; Destinatario (D), Remitente (R)
			);

			$pre_paquetes = array();
			$paquetes = array();

			if($_POST['subaction'] == 'generar'){
				$opcion_paquetes = array( $_POST["paquetes"] );
			}
			else if($_POST['subaction'] == 'generar-multiple'){
				$opcion_paquetes = $_POST["paquetes"];	
			}
			else{
				$opcion_paquetes = [];	
			}	

			foreach($opcion_paquetes[$key] as $prodid => $numero_paquete ):
				foreach ($order->get_items() as $item_key => $item ):
					$item_id = $item->get_id();
					$product = $item->get_product(); // Get the WC_Product object

					$weight = 
						($product->get_weight() != '') ? $product->get_weight() : $options['peso_producto_defecto'];
					$height = 
						($product->get_height() != '') ? $product->get_height() : $options['alto_producto_defecto'];
					$width = 
						($product->get_width() != '') ? $product->get_width() : $options['ancho_producto_defecto'];
					$length = 
						($product->get_length() != '') ? $product->get_length() : $options['largo_producto_defecto'];

					$product_id = $item->get_product_id(); // the Product id
					$quantity = $item->get_quantity();					
					if ("$prodid" == "$product_id" && $item_key == $item_key."") { // just to give sonarqube master as it wishes
						if (isset($pre_paquetes[$numero_paquete])) {
							$pre_paquetes[$numero_paquete]["weight"] += $weight * $quantity;
							$pre_paquetes[$numero_paquete]["total"] += $product->get_price()*$quantity;
							$pre_paquetes[$numero_paquete][$titles][] = $product->get_title()." x ".$quantity;
							$pre_paquetes[$numero_paquete]["volumes"]["$item_id"] = 
								$height * $quantity * $width * $length;
						} else {
							$pre_paquetes[$numero_paquete] = array(
								"weight"=> $weight * $quantity,
								"total"=> $product->get_price()*$quantity,
								$titles => array($product->get_title()." x ".$quantity),
								"volumes" => array(
									"$item_id" =>  $height * $quantity * $width * $length
								)
							);
						}
					}
				endforeach;
			endforeach;
			
			foreach($pre_paquetes as $numero_paquete => $base_paquete ):
				// ordenamos los volumenes en volumen de mayor a menor
				arsort($base_paquete["volumes"]);
				// obtenemos el id del producto 
				$biggest_product_id = array_key_first($base_paquete["volumes"]);
				foreach ($order->get_items() as $item_key => $item ):
					$item_id = $item->get_id();
					$product = $item->get_product(); // Get the WC_Product object

					$height = 
						($product->get_height() != '') ? $product->get_height() : $options['alto_producto_defecto'];
					$width =
						($product->get_width() != '') ? $product->get_width() : $options['ancho_producto_defecto'];
					$length = 
						($product->get_length() != '') ? $product->get_length() : $options['largo_producto_defecto'];

					if ($item_id == $biggest_product_id) {
						$paquetes[] =  array(
								"weight"=> $base_paquete["weight"], // Peso en kilogramos
								"height"=> $height, // Altura en centímetros
								"width"=> $width, // Ancho en centímetros
								"length"=> $length,  // Largo en centímetros
								// Código del servicio de entrega, obtenido de la API Cotización
								"serviceDeliveryCode"=> $serviceTypeId,
								// Código del tipo de roducto a enviar; 1 = Documento, 3 = Encomienda
								"productCode"=> "3", 
								// Referencia que permite identificar el envío por parte del cliente.
								"deliveryReference"=> "ORDEN-".$order_id,
								// Referencia que permite identificar un grupo de bultos que va por parte del cliente.
								"groupReference"=> "ORDEN-".$order_id."-GRUPO-1",
								// Valor declarado del producto
								"declaredValue"=> $base_paquete["total"],
								// Tipo de producto enviado;
								// 1 = Moda, 2 = Tecnologia, 3 = Repuestos, 4 = Productos medicos, 5 = Otros
								"declaredContent"=> 
									isset($options[$ARTICULOS_TIENDA_STR])?$options[$ARTICULOS_TIENDA_STR]:"5", 
								"descriptionContent" => implode(";", $base_paquete[$titles]),
								// Indicador de contratación de cobertura extendida 0 = No, 1 = Si
								"extendedCoverageAreaIndicator"=> false,
								// Monto a cobrar, en caso que el cliente tenga habilitada esta opción. 
								//Queda en 0 a petici'on de RCEA
								"receivableAmountInDelivery"=> 0 
							);
					}
				endforeach;
			endforeach;

			$details[] = array(
				"addresses" => array(
					$payload_address_destino,
					$payload_address_devolucion
				),
				// Se debe entregar un detalle para los datos de contacto 
				// del destinatario (D) y otro para los del remitente (R)
				"contacts" => array( 
					$payload_contact_devolucion,
					$payload_contact_destino
				),
				"packages" => $paquetes
			);	
		}

		$response = array(
			"header"  => $payload_header,
			"details" => $details
		);

		return $response;
	}

	public function _generar_e_imprimir_ot($order) {
		$order_id = $order->get_id();
		$transportOrderNumbers = $order->get_meta( self::TRANSPORT_ORDER_NUMBERS);
		$barcodes = $order->get_meta( 'barcodes');
		$references = $order->get_meta( 'references');
		$productDescriptions = $order->get_meta( 'productDescriptions');
		$serviceDescription_ = $order->get_meta( 'serviceDescription_');
		$classificationData_ = $order->get_meta( 'classificationData_');
		$companyName_ = $order->get_meta( 'companyName_');
		$recipient_ = $order->get_meta( 'recipient_');
		$address_ = $order->get_meta( 'address_' );
		$printedDate_ = $order->get_meta( 'printedDate_');
		$labelsData = $order->get_meta( 'labelsData');

		if ($labelsData == '') {
			$labelsData = array();
			$labelsCount = $order->get_meta( 'labelsCount');
			$partsCountPerlabel = $order->get_meta( 'labelsPartsPerLabel');
			for($i = 1; $i <= $labelsCount; $i++) {	
				$parts = '';
				for($j = 1; $j <= $partsCountPerlabel[$i-1]; $j++ ){
					$parts .= $order->get_meta( "labelsData_".($i-1)."_".$j);
				}
				$labelsData[] = ($parts);
			}
		}
	 ?>
	 <h2>Imprimir OT</h2>
	 <h3>Etiquetas</h3>
	 <?php 
	 	if (is_array($transportOrderNumbers)) {
			for($i = 0; $i <count($transportOrderNumbers); $i++) {
			 // Next lines are used in the include file, but are marked as unused by sonar
			 $print_url = get_site_url().'?order_label='.$order_id; // NOSONAR
			 $src = 'data:image/jpg;base64,'.$labelsData[$i]; // NOSONAR
			 ?>
			 <table>
			 <caption style="display:none;">Contenedor etiqueta</caption>
			 <tr>
				 <th scope="col" style="width:50%;">
					 <h4 id="etiqueta" style="display:none;">Etiqueta</h4>
					 <table class="form-table" aria-describedby="etiqueta">
						 <thead>
						 <tr style="display:none;">
							 <th scope="col">Campo</th>
							 <th scope="col">Valor</th>
						 </tr>
						 </thead>
						 <tbody>
							 <tr>
								 <td>Numero de OT</td>
								 <td><input type="text"  disabled="disabled" 
								 value="<?php  echo $transportOrderNumbers[$i];  ?>" class="regular-text"></td>
							 </tr>
							 <tr>
								 <td>Referencia</td>
								 <td><input type="text" disabled="disabled" 
								 value="<?php  echo $references[$i];  ?>" class="regular-text"></td>
							 </tr>
							 <tr>
								 <td>Descripcion del producto</td>
								 <td><input type="text" disabled="disabled" 
								 value="<?php  echo $productDescriptions[$i];  ?>" class="regular-text"></td>
							 </tr>
							 <tr>
								 <td>Descripción adicional</td>
								 <td><input type="text" disabled="disabled" 
								 value="<?php  echo $serviceDescription_[$i];  ?>" class="regular-text"></td>
							 </tr>
							 <tr>
								 <td>Código de barras</td>
								 <td><input type="text" disabled="disabled" 
									value="<?php  echo $barcodes[$i];  ?>"
									class="regular-text"></td>
							 </tr>
							 <tr>
								 <td>Clasificación</td>
								 <td><input type="text" disabled="disabled" 
								 	value="<?php echo $classificationData_[$i];?>"
								  	class="regular-text"></td>
							 </tr>
							 <tr>
								 <td>Compañia</td>
								 <td><input type="text" disabled="disabled" value="<?php  echo $companyName_[$i];  ?>"
								  class="regular-text"></td>
							 </tr>
							 <tr>
								 <td>Recibe</td>
								 <td><input type="text" disabled="disabled" value="<?php  echo $recipient_[$i]; ?>"
								  class="regular-text"></td>
							 </tr>
							 <tr>
								 <td>Dirección</td>
								 <td><input type="text" disabled="disabled" value="<?php  echo $address_[$i]; ?>" 
								 class="regular-text"></td>
							 </tr>
							 <tr>
								 <td>Fecha de impresión</td>
								 <td><input type="text" disabled="disabled" value="<?php  echo $printedDate_[$i]; ?>"
								 class="regular-text"></td>
							 </tr>
						 </tbody>
					 </table>
				 </th>
				 <td style="width:5%; vertical-align:top;">&nbsp;</td>
				 <td style="width:45%;  vertical-align:top;">
					 <h4 style="display:none;">Imagen de la etiqueta</h4>
					 <?php echo '<img src="' . $src . '" />'; ?>
					 <br />
					 <br />
					 <a rel="noopener noreferrer" href="<?php echo $print_url; ?>" class="button button-primary"
					 target="_blank">Imprimir PDF</a>
					 <a rel="noopener noreferrer" href="<?php echo $print_url; ?>&order_type=3" class="button button-secondary"
					 target="_blank">Descargar ZPL</a>
					 <a rel="noopener noreferrer" href="<?php echo $print_url; ?>&order_type=2" class="button button-secondary"
					 target="_blank">Descargar Imagen</a>
				 </td>
			 </tr>

		 </table>
		 <hr />
			 <?php
			}
	 	}
	}

	public function aviso_ot_generadas() {

	    if ( empty( $_REQUEST['generar_multiples_ot'] ) ) {
			return; // Exit
		}

	    $count = intval( $_REQUEST['processed_count'] );

	    printf( '<div id="message" class="updated fade"><p>' .
	        _n( '%s Pedidos procesados.',
	        '%s Pedidos procesados.',
	        $count,
	        'generar_multiples_ot'
	    ) . '</p></div>', $count );
	}


	public function habilitar_modulos() {
		$countries_obj   = new WC_Countries();
    	$shipping_countries = $countries_obj->get_shipping_countries( );
    	if(!array_key_exists("CL", $shipping_countries) || count($shipping_countries) > 1){
    		?>
    		 <div id="message" class="notice notice-error">
				<p>El Plugin de Chilexpress solo funciona para enviós en Chile,
					 se recomienda deshabilitar el envio a otros paises 
					 <a
					 	rel="noopener noreferrer"
						href="<?php echo admin_url().'admin.php?page'.'=wc-settings'?>"
					>aquí</a>
					 en la sección <strong>Opciones Generales</strong>.</p></div>
    		<?php
    	}
	?>
    <form action='options.php' method='post' class="chilexpress-modules-form">
        <h2>Habilitar módulos</h2>
        <?php  if (isset($_GET['settings-updated'])): ?>
            <div id="message" class="updated notice is-dismissible">
				<p>La configuración de módulos fue actualizada con éxito.</p></div>
        <?php endif; ?>
        <p style="margin-bottom: -3em;">
			Para poder trabajar de forma adecuada necesitas crear tus Api Keys en el siguiente URL
        	<a 
				rel="noopener noreferrer"
				href="https://developers.wschilexpress.com/products"
				target="_blank">https://developers.wschilexpress.com/products</a>
        </p>
        <?php
        settings_fields( 'chilexpress-woo-oficial' ); 
        do_settings_sections( 'chilexpress-woo-oficial' );
        submit_button("Guardar");
        ?>
    </form>
    <?php
	}


	public function configuracion_general() { // NOSONAR
		$countries_obj   = new WC_Countries();
    	$shipping_countries = $countries_obj->get_shipping_countries( );
    	if(!array_key_exists("CL", $shipping_countries) || count($shipping_countries) > 1){
    		?>
    		 <div id="message" class="notice notice-error">
				<p>El Plugin de Chilexpress solo funciona para enviós en Chile, 
					se recomienda deshabilitar el envio a otros paises 
					<a 
						rel="noopener noreferrer" 
						href="<?php echo admin_url().'admin.php?page='.'wc-settings'?>">
						aquí
					</a> en la sección <strong>Opciones Generales</strong>.</p></div>
    		<?php
    	}
		$class_active = 'class="active"';
		$display_none = 'style="display: none;"';
	?>
    	<h1>Opciones Generales</h1>
    	<?php  if (isset($_GET['settings-updated'])): ?>
            <div id="message" class="updated notice is-dismissible" style="margin-bottom: 10px;">
				<p>Las opciones generales de Chilexpress fueron actualizadas con éxito.</p>
			</div>
        <?php endif; ?>

        <!-- Contenedor para cargar los mensajes de respuesta del ajax -->
        <div id="response_dimensions"></div>

        <div class="container">
        	<ul class="tabs">
				<li <?php if( isset($_GET['tabs']) && $_GET['tabs'] == 'DT' ){ echo $class_active; } ?>>
					<a href="?page=chilexpress_woo_oficial_submenu2&tabs=DT#tab1">Datos de la tienda</a>
				</li>
			  	<li <?php if( isset($_GET['tabs']) && $_GET['tabs'] == 'DO' ){ echo $class_active; } ?>>
					<a href="?page=chilexpress_woo_oficial_submenu2&tabs=DO#tab2">Datos de Origen</a>
				</li>
			  	<li <?php if( isset($_GET['tabs']) && $_GET['tabs'] == 'DR' ){ echo $class_active; } ?>>
					<a href="?page=chilexpress_woo_oficial_submenu2&tabs=DR#tab3">Datos del Remitente</a>
				</li>
			  	<li <?php if( isset($_GET['tabs']) && $_GET['tabs'] == 'DD' ){ echo $class_active; } ?>>
					<a href="?page=chilexpress_woo_oficial_submenu2&tabs=DD#tab4">Dirección de Devolución</a>
				</li>
			  	<li <?php if( isset($_GET['tabs']) && $_GET['tabs'] == 'EMD' ){ echo $class_active; } ?>>
					<a href="?page=chilexpress_woo_oficial_submenu2&tabs=EMD#tab5">Entrega el mismo día</a>
				</li>
			  	<li <?php if( isset($_GET['tabs']) && $_GET['tabs'] == 'DP' ){ echo $class_active; } ?>>
					<a href="?page=chilexpress_woo_oficial_submenu2&tabs=DP#tab6">Dimensiones de Producto</a>
				</li>
			</ul>

			<div class="tab_container">
				<form action='options.php<?php 
						if(
							isset($_GET['tabs']) &&
							in_array($_GET['tabs'], array('DT','DO','DR','DD','EMD','DP'))
						) {
								 echo '?tabs='.$_GET['tabs']; 
						} ?>' method='post' class="form-opciones-generales">
				    <div class="tab_content" 
						<?php if( isset($_GET['tabs']) && $_GET['tabs'] != 'DT' ){ echo $display_none; } ?>
						id="tab1">
				    	<?php do_settings_sections( 'chilexpress-woo-oficial-general' ); ?>
				    	<?php
				        	settings_fields( 'chilexpress-woo-oficial-general' );
				        	submit_button("Guardar");
				        ?>
				    </div>
				    <div class="tab_content"
						<?php if( isset($_GET['tabs']) && $_GET['tabs'] != 'DO' ){ echo $display_none; } ?>
						id="tab2">
				       <?php do_settings_sections( 'chilexpress-woo-oficial-general-2' ); ?> 
				       <?php
				        	settings_fields( 'chilexpress-woo-oficial-general' );
				        	submit_button("Guardar");
				        ?>
				    </div>
				    <div class="tab_content"
						<?php if( isset($_GET['tabs']) && $_GET['tabs'] != 'DR' ){ echo $display_none; } ?>
						id="tab3">
				       <?php do_settings_sections( 'chilexpress-woo-oficial-general-3' ); ?>
				       <?php
			        		settings_fields( 'chilexpress-woo-oficial-general' );
			        		submit_button("Guardar");
			        	?>
				    </div>
				    <div class="tab_content"
						<?php if( isset($_GET['tabs']) && $_GET['tabs'] != 'DD' ){ echo $display_none; } ?>
						id="tab4">
				       <?php do_settings_sections( 'chilexpress-woo-oficial-general-4' ); ?>
				       <?php
				        	settings_fields( 'chilexpress-woo-oficial-general' );
				        	submit_button("Guardar");
				        ?>
				    </div>
				    <div class="tab_content" 
						<?php if( isset($_GET['tabs']) && $_GET['tabs'] != 'EMD' ){ echo $display_none; } ?>
						id="tab5">
				       <?php do_settings_sections( 'chilexpress-woo-oficial-general-5' ); ?>
				       <?php
				        	settings_fields( 'chilexpress-woo-oficial-general' );
				        	submit_button("Guardar");
				        ?>
				    </div>
				</form>
			    <div class="tab_content" 
						<?php if( isset($_GET['tabs']) && $_GET['tabs'] != 'DP' ){ echo $display_none; } ?> 
					id="tab6">
			       	<?php 
			       		$tablaProductosChilexpress = new Tabla_Productos_Chilexpress();
        				$tablaProductosChilexpress->prepare_items();
        			?>
			       	<form method="post">
	                	<input 
						type="hidden"
						name="page"
						value="<?php echo admin_url('admin.php?page=chilexpress_woo_oficial_submenu2&tabs=DP');?>"/>
					  	<?php $tablaProductosChilexpress->search_box( 'Buscar' , 'search_id' ); ?>
					</form>

					<a href="<?php echo admin_url('admin.php?page=chilexpress_woo_oficial_submenu2&tabs=DP'); ?>">
						Mostrar Todo</a>

	                <?php $tablaProductosChilexpress->display(); ?>

			    </div>
			</div>
        </div>
    <?php
	}

	public function config_comunas_regiones() {
		$countries_obj   = new WC_Countries();
    	$shipping_countries = $countries_obj->get_shipping_countries( );
    	if(!array_key_exists("CL", $shipping_countries) || count($shipping_countries) > 1){
    		?>
    		 <div id="message" class="notice notice-error">
				<p>El Plugin de Chilexpress solo funciona para enviós en Chile, 
					se recomienda deshabilitar el envio a otros paises 
					<a rel="noopener noreferrer"  href="<?php echo admin_url().'admin.php?page=wc-settings'?>">aquí</a>
					 en la sección <strong>Opciones Generales</strong>.</p></div>
    		<?php
    	}
	?>
    <form action='options.php' method='post' class="form-comunas-regiones">	
    	<h1>Habilitar Regiones y Comunas</h1>
    	
    	<?php  if (isset($_GET['settings-updated'])): ?>
            <div id="message" class="updated notice is-dismissible">
				<p>Las opciones generales de Chilexpress fueron actualizadas con éxito.</p>
			</div>
        <?php endif; ?>

        <div class="container-regiones-comunas">
        	<?php do_settings_sections( 'chilexpress-woo-oficial-region-comuna' ); ?>
        </div>

        <?php
		    settings_fields( 'chilexpress-woo-oficial-region-comuna' );
		    submit_button("Guardar");
		?>    
    </form>
    <?php
	}

	public function listado_pedidos_chilexpress()
    {
        $tablaPedidosChilexpress = new Tabla_Pedidos_Chilexpress();
        $tablaPedidosChilexpress->prepare_items();
        ?>
            <div class="wrap">
                <div id="icon-users" class="icon32"></div>
                <h2>Listado de Pedidos Chilexpress</h2>
                
                <form method="get">
                	<input type="hidden" name="page" value="chilexpress_woo_oficial_listado_pedidos" />
				  	<?php $tablaPedidosChilexpress->search_box( 'Buscar' , 'search_id' ); ?>
				</form>

				<a href="<?php echo admin_url('admin.php?page=chilexpress_woo_oficial_listado_pedidos'); ?>">
					Mostrar Todo</a>
				
                <form method="post">
                	<?php $tablaPedidosChilexpress->display(); ?>
            	</form>
            </div>
        <?php
    }

	public function obtener_regiones_handle_ajax_request() {
		
		$response	= array();
		$response['message'] = "Successfull Request";
		$regiones = $this->coverage_data->obtener_regiones();
		$response['regiones'] = $regiones;

    	echo json_encode($response);
    	exit;
	}

	public function obtener_comunas_desde_region_handle_ajax_request() {
 		$region	= isset($_POST['region'])? sanitize_text_field($_POST['region']):"";
		$response	= array();
		$response['message'] = "Successfull Request";
		$comunas = $this->coverage_data->obtener_comunas($region);
		$response['comunas'] = $comunas;

    	echo json_encode($response);
    	exit;
	}

	public function track_order_handle_ajax_request() {
		$ot	= isset($_POST['ot'])?sanitize_text_field($_POST['ot']):"";
		$pid = isset($_POST['pid'])?sanitize_text_field($_POST['pid']):1;
		$options = get_option( 'chilexpress_woo_oficial_general' );

		$order = wc_get_order( $pid );
		$transportOrderNumbers = $order->get_meta(self::TRANSPORT_ORDER_NUMBERS);
		if (!in_array($ot, $transportOrderNumbers)) {
			echo json_encode(
				array(
					'ot'=>$ot,
					'error'=> "Numero de Orden Invalida",
					'response' => json_decode(array())
				));
			exit;
		}

		$result = $this->api->obtener_estado_ot($ot, "ORDEN-".$pid, intval($options["rut_marketplace_remitente"])); 

		if ( is_wp_error( $result ) ) {
		   $error_message = $result->get_error_message();
		   echo json_encode(array('ot'=>$ot,'error'=> $error_message ));
		} else {
		   echo json_encode(array('ot'=>$ot,'response' => $result));
		}
		
		exit;
	}

	public function set_dimension_handle_ajax_request() {

		$update = true;
		if(
			$_POST['ampm'] == 'checked' &&
			$_POST['peso'] > 15 || $_POST['alto'] > 70 || $_POST['largo'] > 70 || $_POST['ancho'] > 70
		) {
			$update = false;
		}
		if($update) {
			update_post_meta( intval($_POST['product_id']), '_height', $_POST['alto'] );
			update_post_meta( intval($_POST['product_id']), '_length', $_POST['largo'] );
			update_post_meta( intval($_POST['product_id']), '_width', $_POST['ancho'] );
			update_post_meta( intval($_POST['product_id']), '_weight', $_POST['peso'] );
			update_post_meta( intval($_POST['product_id']), '_ampm', $_POST['ampm'] );

			echo json_encode( array('status' => 'success', 'response' => 'Dimensiones Asignadas.!') );
		}
		else{
			echo json_encode(
				array(
					'status' => 'error',
					'response' => 'El producto seleccionado supera las dimensiones'.
							      ' y/o peso habilitadas para un servicio AMPM.'
				)
			);	
		}

		exit;
	}

	public function close_certificate_handle_ajax_request() {

		$result = get_post_meta( intval($_POST['order_id']), '_closed_certificate', true );

		if($result == ''){

			$payload = array(
				"certificateNumber" => floatval($_POST['numero_ce']),
				"certificateType"   => 1,
				"dropNumber"        => 0
			);

			$response = $this->api->cerrar_certificado($payload);

			if ( is_wp_error( $response ) ) {

				$data = array(
					"status"   => "error",
					"response" => "Error en respuesta de la API",
					"error"    => $response
				);

			}
			else{

				update_post_meta( intval($_POST['order_id']), '_closed_certificate', $response );

				$data = array(
					'status' => 'success', 
					'response' => $response
				);

			}	
		}
		else{
			$data = array(
				'status' => 'success-bd', 
				'response' => $result
			);
		}

		echo json_encode( $data );
		
		exit;
	}

	public function get_cotizacion_handle_ajax_request() { // NOSONAR

		$_orderID = $_POST['orderID'];
		$order = wc_get_order($_orderID);

		if (!$order || empty( $order->get_items() ) ) 
		{
			$data = array(
				"status"   => "error",
			    "response" => "Por favor, agregue productos al pedido.!"
			);
		}
		else
		{
			$items = $order->get_items();
			$weight = 0;
			$price = 0;
			$biggest_product = false;
			$biggest_size = 0;
			$options = get_option( 'chilexpress_woo_oficial_general' );
			$products_id = array();

			$i = 0;
	        foreach ( $items as $item_id => $item ) 
	        {
	            $_product = $item->get_product();
	            $dimensions = $_product->get_dimensions(false);

				array_push($products_id, $_product->get_id());

	            if (
					$dimensions["width"] != "" &&
					$dimensions["height"] != "" &&
					$dimensions["length"] != "" &&
					"$item_id" == "$item_id".""
				) {
	               	if( $biggest_size < $_product->get_height() * $_product->get_width() *$_product->get_length())
	                {
	                	$biggest_size = $_product->get_height() * $_product->get_width() * $_product->get_length();
	                	$biggest_product = $_product;
	                }
	                $i++;
	           	}
	           	else if ($dimensions["width"] == "" && $dimensions["height"] == "" && $dimensions["length"] == "") {
                	if( 
						$biggest_size < 
						floatval($options['alto_producto_defecto']) *
						floatval($options['ancho_producto_defecto']) *
						floatval($options['largo_producto_defecto'])
					){
                		$biggest_size = floatval($options['alto_producto_defecto']) *
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
	               	$weight = $weight + ( $_product->get_weight() * $item['quantity'] );
	            }
	            else{
                	$weight = $weight + ( floatval($options['peso_producto_defecto']) * $item['quantity'] );
                }
                
				$price += $_product->get_price() * $item['quantity'];
	        }

			$shippingCity = $order->get_shipping_city();
			$shippingCityForm = $_POST['shippingCityForm'];
			$comuna = ( $shippingCity == $shippingCityForm ) ? $shippingCity : $shippingCityForm;

			$result = $this->api->obtener_cotizacion(
				$options['comuna_origen'],  
				$comuna, 
				( !isset($options["tipo_prioridad"]) ) ? 2 : $options["tipo_prioridad"] ,
				$options["numero_tcc_origen"],
				$weight, 
				$biggest_product->get_height(), 
				$biggest_product->get_width(), 
				$biggest_product->get_length(), 
				$price	
			);

			/*
				2) PRIORITARIO: 1 dia
				3) EXPRESS: 1 dia
				4) EXTENDIDO: 1 - 2 dias
				5) EXTREMO: 2 - 3 dias
				8) AM/PM: (mismo día hasta las 20 hrs)
				53) BASICO: 3 dias
				54) BASICO: 4 dias
				55) BASICO: 5 dias
			*/

			if ( !isset($options['dias_procesamiento']) ){
				$options['dias_procesamiento'] = 0;
			}

			$service_ampm = $this->shipping_method->validate_ampm_service($options, $products_id);
			$servicios_excluidos = array(11,14,15,16,43,44,45,46,47,48);

			foreach ($result as $key => $item)
			{

				if( !in_array($item->serviceTypeCode, $servicios_excluidos) ) {

					if( $item->serviceTypeCode == 2 ) {
				    $result[$key]->sellerProcessingDays = " 1 día hábil (hasta 11 hrs)";
					}

					if( $item->serviceTypeCode == 3 ) {
							$result[$key]->sellerProcessingDays = " 1 día hábil (hasta 19 hrs)";
					}

					if( $item->serviceTypeCode == 4 ) {
							$result[$key]->sellerProcessingDays = "1 a 2 días hábiles";
					}

					if( $item->serviceTypeCode == 5 ) {
							$result[$key]->sellerProcessingDays = "2 a 3 días hábiles";
					}

					if( $item->serviceTypeCode == 8 ) {
							$result[$key]->sellerProcessingDays = "mismo día hasta las 22 hrs";
					}

					if( $item->serviceTypeCode == 53 ) {
							$result[$key]->sellerProcessingDays = "3 días hábiles entre 11:00 y 19:00hrs";
					}

					if( $item->serviceTypeCode == 54 ) {
							$result[$key]->sellerProcessingDays = "4 días hábiles entre 11:00 y 19:00hrs";
					}

					if( $item->serviceTypeCode == 55 ) {
							$result[$key]->sellerProcessingDays = "5 días hábiles entre 11:00 y 19:00hrs";
					}

					if( $item->serviceTypeCode == 56 ) {
							$result[$key]->sellerProcessingDays = "12 días hábiles entre 11:00 y 19:00hrs";
					}

					$result[$key]->serviceValueDiscountWithFormat = wc_price( $item->serviceValueDiscount );

				}
				else{
					//Elimino el servicio que no deba ir de la lista final
					unset($result[$key]);
				}

			}

			if ( is_wp_error( $result ) ) 
			{
				$data = array(
				    "status"   => "error",
				    "response" => $result->errors["chilexpress-woo-oficial"]
				);
			}
			else
			{
				$data = array(
				  "status"   => "success",
				  "response" => $result,
					"ampm"	   => $service_ampm,
				  "logo"     => site_url("wp-content/plugins/chilexpress-oficial/public/imgs/logo-chilexpress-negro.png")
				);	
			}
		}

    echo json_encode( $data );
		exit;
	}

	public function bulk_actions_multiples_ot( $bulk_actions ){
		$bulk_actions['generar_multiples_ot'] = __( 'Generar Multiples OT', 'woocommerce' );
    return $bulk_actions;
	}

	public function handle_bulk_action_multiples_ot( $redirect_to, $action, $post_ids ) {

		//check_admin_referer( 'bulk-action' );
	    
		if ( $action !== 'generar_multiples_ot' ) {
				return $redirect_to; // Exit
		}

		$ordenes = $post_ids;

		foreach ( $ordenes as $key => $order_id ) {
				$order = wc_get_order( $order_id );

			if (!$order) {
				die("Invalid Order: ".$order_id);
			}

			$ot_status = $order->get_meta('ot_status');
			$transportOrderNumbers = $order->get_meta( self::TRANSPORT_ORDER_NUMBERS );

			if(!$ot_status || ($ot_status == 'created' && count($transportOrderNumbers) == 0))
			{
				$items = $order->get_items();
				foreach ($items as  $item) 
				{
					$_POST["paquetes"][$key][$item->get_product_id()] = "1";
				}
			}
			else
			{
				unset( $ordenes[$key] );
			}
		}

		$_POST["subaction"] = 'generar-multiple';
		$this->_generar_ot( $ordenes );

		return $redirect_to = add_query_arg( array(
				'generar_multiples_ot' => '1',
				'processed_count' => count( $ordenes ),
				'processed_ids' => implode( ',', $ordenes ),
		), $redirect_to );
	}

	public function enviar_notificacion( $order ) {

		$transportOrderNumbers = $order->get_meta( self::TRANSPORT_ORDER_NUMBERS );
		
		if (is_array($transportOrderNumbers)) 
		{
			$out = array();
			
			foreach($transportOrderNumbers as $transportOrderNumber) {
				$out[] = $transportOrderNumber;
			}

			$tracking = implode(", ", $out);
		}

		$wcEmail = WC()->mailer();

		$emailer = $wcEmail->get_emails()['WC_Email_Customer_Note']; //Enviar una nota al usuario

		$emailer->trigger(
			array(
				'order_id'      => $order->get_order_number(), //Número de la orden
				'customer_note' => '<h4><strong>N° Tracking: '.$tracking.'</strong></h4>'  //Contenido de la nota
			)
		);
	}


	function chilexpress() {
		// We need to declare this function for the hooks
	}
}


if (!function_exists('array_key_first')) {
    function array_key_first(array $arr) {
        foreach($arr as $key => $unused) {
            return $key;
        }
        return NULL;
    }
}
