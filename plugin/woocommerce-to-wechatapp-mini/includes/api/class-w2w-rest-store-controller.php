<?php
/**
 * REST API Store controller
 *
 * Handles requests to the /store endpoint.
 *
 * @author   QWQ
 * @category API
 * @package  WooCommerce/API
 * @since    2.6.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * REST API Store controller class.
 *
 * @package WooCommerce/API
 * @extends WC_REST_Posts_Controller
 */
class W2W_REST_Store_Controller extends W2W_REST_Posts_Controller {

	/**
	 * Endpoint namespace.
	 *
	 * @var string
	 */
	protected $namespace = 'w2w/v1';

	/**
	 * Route base.
	 *
	 * @var string
	 */
	protected $rest_base = 'store';

	
	/**
	 * Initialize store actions.
	 */
	public function __construct() {
		
	}

	/**
	 * Register the routes for store.
	 */
	public function register_routes() {
		
		register_rest_route( $this->namespace, '/' . $this->rest_base . '/home', array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_store_home' ),
				'permission_callback' => array( $this, 'get_items_permissions_check' ),
				'args'                => $this->get_collection_params(),
			)
		) );
		
		register_rest_route( $this->namespace, '/' . $this->rest_base . '/index', array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_store_index' ),
				'permission_callback' => array( $this, 'get_items_permissions_check' ),
				'args'                => $this->get_collection_params(),
			)
		) );
		
		register_rest_route( $this->namespace, '/' . $this->rest_base . '/currency', array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_base_currency' ),
				'permission_callback' => array( $this, 'get_items_permissions_check' ),
				'args'                => $this->get_collection_params(),
			)
		) );
		
		register_rest_route( $this->namespace, '/' . $this->rest_base . '/banner', array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_banner' ),
				'permission_callback' => array( $this, 'get_items_permissions_check' ),
				'args'                => $this->get_collection_params(),
			)
		) );
		
		register_rest_route( $this->namespace, '/' . $this->rest_base . '/about', array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_about_page' ),
				'permission_callback' => array( $this, 'get_items_permissions_check' ),
				'args'                => $this->get_collection_params(),
			)
		) );
	}
	
	/**
	 * Check whether a given request has permission to read order notes.
	 *
	 * @param  WP_REST_Request $request Full details about the request.
	 * @return WP_Error|boolean
	 */
	public function get_items_permissions_check( $request ) {
		return true;
	}
	
	// 获取商店APP所需要的数据
	public function get_store_home( $request ) {
		
		$customer_controller = new W2W_REST_Customers_Controller();
		$session = $customer_controller->check_session( $request )->data;
		$currency = $this->get_base_currency( $request );
		
		$cart_controller = new W2W_REST_Cart_Controller();
		$cart = $cart_controller->get_cart_totals( $request );
		
		return array(
			'session' => $session,
			'currency' => $currency,
			'cart' => $cart
		);
	}
	
	// 获取商店首页所需要的数据
	public function get_store_index( $request ) {
		
		// 轮播图
		$banner = $this->get_banner( $request );
		
		$products_controller = new W2W_REST_Products_Controller();
		
		$products = $products_controller->get_items( $request )->data;
		
		// 精选产品
		$request['featured'] = true;
		$featured_products = $products_controller->get_items( $request )->data;
		
		// 店铺公告
		$store_notice = get_option( 'woocommerce_demo_store' ) === 'yes'
			? html_entity_decode( get_option( 'woocommerce_demo_store_notice' ) ) : null;
		
		$index_data = array(
			'banner'            => $banner,
			'lastest_products'  => $products,
			'featured_products' => $featured_products,
			'notice'			=> $store_notice
		);
		return apply_filters( 'w2w_index_data', $index_data );
	}

	/**
	 * Get base currency.
	 *
	 * @param WP_REST_Request $request
	 * @return string
	 */
	public function get_base_currency( $request ) {

		$data = get_woocommerce_currency_symbol();
		
		return html_entity_decode( $data );
	}
	
	// 获取轮播图
	public function get_banner( $request ) {
		
		$settings = get_option('w2w-settings');
		$banners = array();
		
		if( isset( $settings['banner'] ) ) {
			foreach( $settings['banner'] as $banner ) {
				$banners[] = $banner;
			}
		}
		
		return $banners;
	}
	
	// 获取关于页面内容
	public function get_about_page( $request ) {
		
		include_once( WP_W2W_PATH . 'vendor/html2wxml/class.ToWXML.php' );
		$settings = get_option('w2w-settings');
		
		if( empty( $settings['about_page'] ) ) {
			return array();
		}
		
		$towxml = new ToWXML();
		$page = get_page( $settings['about_page'] );
		$content = $towxml->towxml( wpautop( do_shortcode( $page->post_content ) ), array( 'encode' => false ) );
		
		return $content;
	}
	
}
