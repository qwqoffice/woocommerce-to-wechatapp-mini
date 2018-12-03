<?php
/**
 * WooCommerce API
 *
 * Handles WC-API endpoint requests.
 *
 * @author   WooThemes
 * @category API
 * @package  WooCommerce/API
 * @since    2.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class W2W_WC_API extends WC_API {

	/**
	 * Setup class.
	 * @since 2.0
	 */
	public function __construct() {
		parent::__construct();

		// Add query vars.
		add_filter( 'query_vars', array( $this, 'add_query_vars' ), 0 );

		// Register API endpoints.
		add_action( 'init', array( $this, 'add_endpoint' ), 0 );

		// Handle wc-api endpoint requests.
		add_action( 'parse_request', array( $this, 'handle_api_requests' ), 0 );

		// Ensure payment gateways are initialized in time for API requests.
		add_action( 'woocommerce_api_request', array( 'WC_Payment_Gateways', 'instance' ), 0 );

		// WP REST API.
		$this->rest_api_init();
	}

	/**
	 * Add new query vars.
	 *
	 * @since 2.0
	 * @param array $vars
	 * @return string[]
	 */
	public function add_query_vars( $vars ) {
		$vars   = parent::add_query_vars( $vars );
		$vars[] = 'wc-api';
		return $vars;
	}

	/**
	 * WC API for payment gateway IPNs, etc.
	 * @since 2.0
	 */
	public static function add_endpoint() {
		parent::add_endpoint();
		add_rewrite_endpoint( 'wc-api', EP_ALL );
	}

	/**
	 * API request - Trigger any API requests.
	 *
	 * @since   2.0
	 * @version 2.4
	 */
	public function handle_api_requests() {
		global $wp;
		ob_start();

		if ( ! empty( $_GET['wc-api'] ) ) {
			$wp->query_vars['wc-api'] = $_GET['wc-api'];
		}
		
		if ( strpos($_SERVER['REQUEST_URI'], 'wp-json') !== false ) {
			if ( ! defined( 'DOING_AJAX' ) ) {
				define( 'DOING_AJAX', true );
			}
			if ( ! defined( 'WC_DOING_AJAX' ) ) {
				define( 'WC_DOING_AJAX', true );
			}
		}
	
		// wc-api endpoint requests.
		if ( ! empty( $wp->query_vars['wc-api'] ) ) {

			// No cache headers.
			nocache_headers();
			
			send_origin_headers();

			// Clean the API request.
			$api_request = strtolower( wc_clean( $wp->query_vars['wc-api'] ) );

			// Trigger generic action before request hook.
			do_action( 'woocommerce_api_request', $api_request );

			// Is there actually something hooked into this API request? If not trigger 400 - Bad request.
			status_header( has_action( 'woocommerce_api_' . $api_request ) ? 200 : 400 );

			// Trigger an action which plugins can hook into to fulfill the request.
			do_action( 'woocommerce_api_' . $api_request );

			// Done, clear buffer and exit.
			ob_end_clean();
			die( '-1' );
		}
	}

	/**
	 * Init WP REST API.
	 * @since 2.6.0
	 */
	private function rest_api_init() {
		global $wp_version;

		// REST API was included starting WordPress 4.4.
		if ( version_compare( $wp_version, 4.4, '<' ) ) {
			return;
		}

		$this->rest_api_includes();

		// Init REST API routes.
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
	}

	/**
	 * Include REST API classes.
	 * @since 2.6.0
	 */
	private function rest_api_includes() {
		
		include_once( 'api/class-w2w-rest-cart-controller.php' );
		include_once( 'api/class-w2w-rest-product-categories-controller.php' );
		include_once( 'api/class-w2w-rest-products-controller.php' );
		include_once( 'api/class-w2w-rest-orders-controller.php' );
		include_once( 'api/class-w2w-rest-customers-controller.php' );
		include_once( 'api/class-w2w-rest-payment-controller.php' );
		include_once( 'api/class-w2w-rest-store-controller.php' );
	}

	/**
	 * Register REST API routes.
	 * @since 2.6.0
	 */
	public function register_rest_routes() {
		$controllers = array(
			'W2W_REST_Cart_Controller',
			'W2W_REST_Products_Controller',
			'W2W_REST_Product_Categories_Controller',
			'W2W_REST_Store_Controller',
			'W2W_REST_Orders_Controller',
			'W2W_REST_Customers_Controller',
			'W2W_REST_Payment_Controller'
		);

		foreach ( $controllers as $controller ) {
			$this->$controller = new $controller();
			$this->$controller->register_routes();
		}
	}
}
