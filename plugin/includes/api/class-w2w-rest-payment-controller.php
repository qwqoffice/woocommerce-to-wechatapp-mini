<?php
/**
 * REST API Payment controller
 *
 * Handles requests to the /payment endpoint.
 *
 * @author   QWQ
 * @category API
 * @package  WooCommerce/API
 * @since    2.6.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once( WP_W2W_PATH . 'includes/wxpay/class.WxPay.Api.php' );
require_once( WP_W2W_PATH . 'includes/wxpay/class.WxPay.JsApiPay.php' );
require_once( WP_W2W_PATH . 'includes/wxpay/class.WxPay.Notify.php' );

/**
 * REST API Payment controller class.
 *
 * @package WooCommerce/API
 * @extends WC_REST_Posts_Controller
 */
class W2W_REST_Payment_Controller extends W2W_REST_Posts_Controller {

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
	protected $rest_base = 'payment';

	
	/**
	 * Initialize payment actions.
	 */
	public function __construct() {
		
	}

	/**
	 * Register the routes for payment.
	 */
	public function register_routes() {
		
		register_rest_route( $this->namespace, '/' . $this->rest_base . '/', array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_payment_params' ),
				'permission_callback' => array( $this, 'get_items_permissions_check' ),
				'args'                => $this->get_collection_params(),
			)
		) );

		register_rest_route( $this->namespace, '/' . $this->rest_base . '/notify', array(
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'notify' ),
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
	
	// 获取支付参数
	public function get_payment_params( $request ) {
		
		$session = W2W_Util::get_session( $request['w2w_session'] );
		if( ! $session ) return array( 'success' => false, 'msg' => 'Session过期' );
		
		$wc_order = wc_get_order( $request['id'] );
		if( ! $wc_order ) return;
		
		$settings = get_option( 'w2w-settings' );
		$payment_gateway = new W2W_WC_Gateway_WeChatPay();
		$wxpay_mchid = $payment_gateway->get_option( 'mchid' );
		$wxpay_key = $payment_gateway->get_option( 'key' );
		$wxpay_body = $payment_gateway->get_option( 'body' );
		
		if( empty( $settings['appid'] ) || empty( $wxpay_mchid ) || empty( $wxpay_key ) || empty( $wxpay_body ) ) {
			
			return array( 'success' => false, 'msg' => '请填写AppID、商户号、商户支付密钥和支付简要描述' );
		}

		$tools = new W2W_JsApiPay();
		$openid = $session['openid'];

		$input = new W2W_WxPayUnifiedOrder();
		$input->SetBody( $wxpay_body );
		$input->SetOut_trade_no( get_post_meta( $wc_order->get_id(), 'w2w_order_number', true ) );
		$input->SetTotal_fee( intval( strval( floatval( $wc_order->get_total() ) * 100 ) ) );
		$input->SetTime_start( date_i18n( 'YmdHis' ) );
		$input->SetTime_expire( date( 'YmdHis', strtotime( date_i18n( 'YmdHis' ) ) + 600 ) );
		$input->SetNotify_url( get_rest_url( null, $this->namespace . '/' . $this->rest_base . '/notify' ) );
		$input->SetTrade_type( 'JSAPI' );
		$input->SetOpenid( $openid );
		
		$order = W2W_WxPayApi::unifiedOrder( $input );
		
		// 存储prepay_id
		if( isset( $order['prepay_id'] ) ) {
			update_post_meta( $request['id'], 'w2w_prepay_id', $order['prepay_id'] );
		}
		
		$js_api_params = $tools->GetJsApiParameters( $order );
		$js_api_params['success'] = ! isset( $js_api_params['return_code'] );
		
		return $js_api_params;
	}
	
	// 支付通知
	public function notify( $request ) {
		
		W2W()->log( 'info', '支付通知开始' );
		$notify = new W2W_PayNotifyCallBack();
		$notify->Handle( false );
	}
}

class W2W_PayNotifyCallBack extends W2W_WxPayNotify {
	
	// 重写回调处理函数
	public function NotifyProcess( $data, &$msg ) {
		
		W2W()->log( 'info', '支付通知: ' . json_encode( $data ) );
		
		if( ! array_key_exists( 'transaction_id' , $data ) ) {
			$msg = '输入参数不正确';
			return false;
		}
		
		//查询订单，判断订单真实性
		$wechat_order = W2W_Util::query_order( $data['transaction_id'] );
		if( ! $wechat_order['success'] ) {
			$msg = '订单查询失败';
			return false;
		}
		
		$payment_gateway = new W2W_WC_Gateway_WeChatPay();
		if( ! W2W_Util::check_notify_sign( $data, $payment_gateway->get_option( 'key' ) ) ) {
			return false;
		}
		
		// 更改订单状态
		$orders = wc_get_orders( array( 'w2w_order_number' => $data['out_trade_no'] ) );
		
		if( count( $orders ) > 0 ) {
			
			$order = $orders[0];
			
			if( $order->get_status() == 'pending' ) {
				$order->payment_complete( $data['transaction_id'] );
				do_action( 'w2w_order_paid', $order->get_id() );
				//$order->update_status( 'processing', '微信支付完成' );
			}
		}
		
		return true;
	}
}
