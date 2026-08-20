<?php

/**
 * The file that defines the core plugin class
 *
 * A class definition that includes attributes and functions used across both the
 * public-facing side of the site and the admin area.
 *
 
 * @since      1.3.2
 *
 * @package    Chilexpress_Woo_Oficial
 * @subpackage Chilexpress_Woo_Oficial/includes
 */

/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 *
 * @since      1.3.2
 * @package    Chilexpress_Woo_Oficial
 * @subpackage Chilexpress_Woo_Oficial/includes
 * @author     Chilexpress
 */
class Chilexpress_Woo_Oficial {

	/**
	 * The loader that's responsible for maintaining and registering all hooks that power
	 * the plugin.
	 *
	 * @since    1.3.2
	 * @access   protected
	 * @var      Chilexpress_Woo_Oficial_Loader    $loader    Maintains and registers all hooks for the plugin.
	 */
	protected $loader;

	/**
	 * The unique identifier of this plugin.
	 *
	 * @since    1.3.2
	 * @access   protected
	 * @var      string    $plugin_name    The string used to uniquely identify this plugin.
	 */
	protected $plugin_name;

	/**
	 * The current version of the plugin.
	 *
	 * @since    1.3.2
	 * @access   protected
	 * @var      string    $version    The current version of the plugin.
	 */
	protected $version;

	/**
	 * Define the core functionality of the plugin.
	 *
	 * Set the plugin name and the plugin version that can be used throughout the plugin.
	 * Load the dependencies, define the locale, and set the hooks for the admin area and
	 * the public-facing side of the site.
	 *
	 * @since    1.3.4
	 */
	public function __construct() {
		if ( defined( 'CHILEXPRESS_WOO_OFICIAL_VERSION' ) ) {
			$this->version = CHILEXPRESS_WOO_OFICIAL_VERSION;
		} else {
			$this->version = '1.3.4';
		}
		$this->plugin_name = 'chilexpress-woo-oficial';

		$this->load_dependencies();
		$this->set_locale();
		$this->define_admin_hooks();
		$this->define_public_hooks();

	}

	/**
	 * Load the required dependencies for this plugin.
	 *
	 * Include the following files that make up the plugin:
	 *
	 * - Chilexpress_Woo_Oficial_Loader. Orchestrates the hooks of the plugin.
	 * - Chilexpress_Woo_Oficial_i18n. Defines internationalization functionality.
	 * - Chilexpress_Woo_Oficial_Admin. Defines all hooks for the admin area.
	 * - Chilexpress_Woo_Oficial_Public. Defines all hooks for the public side of the site.
	 *
	 * Create an instance of the loader which will be used to register the hooks
	 * with WordPress.
	 *
	 * @since    1.3.2
	 * @access   private
	 */
	private function load_dependencies() {

		/**
		 * The class responsible for orchestrating the actions and filters of the
		 * core plugin.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-chilexpress-woo-oficial-loader.php';

		/**
		 * The class responsible for defining internationalization functionality
		 * of the plugin.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-chilexpress-woo-oficial-i18n.php';

		/**
		 * The class responsible for defining all actions that occur in the admin area.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/class-chilexpress-woo-oficial-admin.php';

		/**
		 * The class responsible for defining all actions that occur in the public-facing
		 * side of the site.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'public/class-chilexpress-woo-oficial-public.php';


		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-chilexpress-woo-oficial-api.php';

		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-chilexpress-woo-oficial-coverage.php';

		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/chilexpress-utils.php';

		$this->loader = new Chilexpress_Woo_Oficial_Loader();

	}

	/**
	 * Define the locale for this plugin for internationalization.
	 *
	 * Uses the Chilexpress_Woo_Oficial_i18n class in order to set the domain and to register the hook
	 * with WordPress.
	 *
	 * @since    1.3.2
	 * @access   private
	 */
	private function set_locale() {

		$plugin_i18n = new Chilexpress_Woo_Oficial_i18n();

		$this->loader->add_action( 'plugins_loaded', $plugin_i18n, 'load_plugin_textdomain' );

	}

	/**
	 * Register all of the hooks related to the admin area functionality
	 * of the plugin.
	 *
	 * @since    1.3.2
	 * @access   private
	 */
	private function define_admin_hooks() {

		$plugin_admin = new Chilexpress_Woo_Oficial_Admin( $this->get_plugin_name(), $this->get_version() );

		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_styles' );
		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts' );
		$this->loader->add_action( 'admin_menu', $plugin_admin, 'add_menus' );

		
		$this->loader->add_action( 'wp_ajax_obtener_regiones', $plugin_admin, 'obtener_regiones_handle_ajax_request' );
  	$this->loader->add_action( 'wp_ajax_nopriv_obtener_regiones', $plugin_admin, 'obtener_regiones_handle_ajax_request' );

		$this->loader->add_action( 'wp_ajax_obtener_comunas_desde_region', $plugin_admin, 'obtener_comunas_desde_region_handle_ajax_request' );
  	$this->loader->add_action( 'wp_ajax_nopriv_obtener_comunas_desde_region', $plugin_admin, 'obtener_comunas_desde_region_handle_ajax_request' );


  	$this->loader->add_action( 'wp_ajax_track_order', $plugin_admin, 'track_order_handle_ajax_request' );
  	$this->loader->add_action( 'wp_ajax_nopriv_track_order', $plugin_admin, 'track_order_handle_ajax_request' );


  	$this->loader->add_action( 'wp_ajax_set_dimension', $plugin_admin, 'set_dimension_handle_ajax_request' );
  	$this->loader->add_action( 'wp_ajax_nopriv_set_dimension', $plugin_admin, 'set_dimension_handle_ajax_request' );


  	$this->loader->add_action( 'wp_ajax_get_cotizacion', $plugin_admin, 'get_cotizacion_handle_ajax_request' );
  	$this->loader->add_action( 'wp_ajax_nopriv_get_cotizacion', $plugin_admin, 'get_cotizacion_handle_ajax_request' );


  	$this->loader->add_action( 'wp_ajax_close_certificate', $plugin_admin, 'close_certificate_handle_ajax_request' );
  	$this->loader->add_action( 'wp_ajax_nopriv_close_certificate', $plugin_admin, 'close_certificate_handle_ajax_request' );


  	$this->loader->add_filter( 'bulk_actions-woocommerce_page_wc-orders', $plugin_admin, 'bulk_actions_multiples_ot' );
  	$this->loader->add_filter( 'handle_bulk_actions-woocommerce_page_wc-orders', $plugin_admin, 'handle_bulk_action_multiples_ot', 10, 3 );


  	$this->loader->add_action( 'admin_notices', $plugin_admin, 'aviso_ot_generadas' );

	}

	/**
	 * Register all of the hooks related to the public-facing functionality
	 * of the plugin.
	 *
	 * @since    1.3.2
	 * @access   private
	 */
	private function define_public_hooks() {

		$plugin_public = new Chilexpress_Woo_Oficial_Public( $this->get_plugin_name(), $this->get_version() );

		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_styles' );
		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_scripts' );
		$this->loader->add_filter( 'woocommerce_shipping_fields', $plugin_public, 'chilexpress_woo_oficial_change_city_to_dropdown', 10 );
		$this->loader->add_filter( 'woocommerce_checkout_fields', $plugin_public, 'chilexpress_woo_oficial_change_city_to_dropdown', 20 );
		$this->loader->add_filter( 'woocommerce_checkout_fields', $plugin_public, 'checkout_fields_override', 30);
		$this->loader->add_action( 'woocommerce_checkout_update_order_meta', $plugin_public, 'custom_checkout_field_update_order_meta' );
		$this->loader->add_filter( 'woocommerce_default_address_fields', $plugin_public, 'reorder_fields') ;
		$this->loader->add_filter( 'woocommerce_default_address_fields' , $plugin_public, 'override_postcode_validation' );
		$this->loader->add_action( 'woocommerce_review_order_before_cart_contents', $plugin_public, 'chilexpress_woo_oficial_validate_order', 10 );
		$this->loader->add_action( 'woocommerce_after_checkout_validation', $plugin_public, 'chilexpress_woo_oficial_validate_order', 10 );
		$this->loader->add_filter( 'woocommerce_states', $plugin_public, 'get_states' );

		$this->loader->add_filter( 'generate_rewrite_rules', $plugin_public ,'rewrite_pdf_label');
		$this->loader->add_filter( 'query_vars', $plugin_public ,'add_pdf_label_query_vars');
		$this->loader->add_action( 'template_redirect', $plugin_public ,'template_redirect_pdf_label' );

		$this->loader->add_action( 'woocommerce_after_shipping_rate', $plugin_public , 'action_after_shipping_rate', 10, 2 );

		$this->loader->add_action( 'woocommerce_cart_totals_after_shipping', $plugin_public , 'mensaje_nota_tiempo_preparacion' );
		$this->loader->add_action( 'woocommerce_review_order_after_shipping', $plugin_public , 'mensaje_nota_tiempo_preparacion' );

		$this->loader->add_filter('woocommerce_checkout_get_value', $plugin_public ,'empty_checkout_get_value', 10, 2);

/*		// NOSONAR // $this->loader->add_filter( 'woocommerce_order_formatted_shipping_address', $plugin_public ,'format_address', 100  );
		// NOSONAR // $this->loader->add_filter( 'woocommerce_order_formatted_billing_address', $plugin_public ,'format_address', 100  ); */

	}

	/**
	 * Run the loader to execute all of the hooks with WordPress.
	 *
	 * @since    1.3.2
	 */
	public function run() {
		$this->loader->run();
	}

	/**
	 * The name of the plugin used to uniquely identify it within the context of
	 * WordPress and to define internationalization functionality.
	 *
	 * @since     1.3.2
	 * @return    string    The name of the plugin.
	 */
	public function get_plugin_name() {
		return $this->plugin_name;
	}

	/**
	 * The reference to the class that orchestrates the hooks with the plugin.
	 *
	 * @since     1.3.2
	 * @return    Chilexpress_Woo_Oficial_Loader    Orchestrates the hooks of the plugin.
	 */
	public function get_loader() {
		return $this->loader;
	}

	/**
	 * Retrieve the version number of the plugin.
	 *
	 * @since     1.3.2
	 * @return    string    The version number of the plugin.
	 */
	public function get_version() {
		return $this->version;
	}

}
