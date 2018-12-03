<?php

if ( ! defined( 'ABSPATH' ) ) exit;

require_once( WP_W2W_PATH . 'includes/wxpay/class.WxPay.Api.php' );

class W2W_WC_Gateway_WeChatPay extends WC_Payment_Gateway {
	
	public static $log_enabled = false;
	public static $log = false;
	
	public function __construct() {
		
		$this->id = 'wxapay';
		$this->has_fields = false;
		$this->order_button_text  = '微信支付';
		$this->title = '微信支付';
		$this->method_title = '微信支付';
		$this->method_description = '小程序微信支付';
		$this->supports = array(
			'products',
			'refunds',
		);
		
		$this->init_form_fields();
		$this->init_settings();
		
		//self::$log_enabled = $this->get_option( 'debug' ) === 'on';
		
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
	}
	
	/*
	// 日志
    public static function log( $message, $level = 'info' ) {
        if ( self::$log_enabled ) {
            if ( empty( self::$log ) ) {
                self::$log = wc_get_logger();
            }
            self::$log->log( $level, $message, array( 'source' => 'wxapay' ) );
        }
    }*/
	
	// 设置字段
	public function init_form_fields() {
		$this->form_fields = array(
			'enabled' => array(
				'title' => '启用禁用',
				'type' => 'checkbox',
				'label' => '启用微信支付',
				'default' => 'yes'
			),
			'title' => array(
				'title' => '标题',
				'type' => 'text',
				'default' => '微信支付'
			),
			'description' => array(
				'title' => '描述',
				'type' => 'text',
				'default' => '小程序微信支付'
			),
			'mchid' => array(
				'title' => '商户号（必填）',
				'type' => 'text',
				'description' => '获取地址：<a href="https://pay.weixin.qq.com/index.php/core/account/info" target="_blank">https://pay.weixin.qq.com/index.php/core/account/info</a>，微信支付商户号',
			),
			'key' => array(
				'title' => '商户支付密钥（必填）',
				'type' => 'text',
				'description' => '获取地址：<a href="https://pay.weixin.qq.com/index.php/core/cert/api_cert" target="_blank">https://pay.weixin.qq.com/index.php/core/cert/api_cert</a>，API密钥-设置密钥',
			),
			'body' => array(
				'title' => '支付简要描述（必填）',
				'type' => 'text',
				'description' => '商家名称-销售商品类目，例如：腾讯-游戏。线上电商，商家名称必须为实际销售商品的商家',
			),
			/*
			'debug' => array(
				'title' => '调试模式',
				'type' => 'checkbox',
				'label' => '启用调试模式',
				'default' => 'off'
			)*/
		);
	}
	
	// 支付处理
	function process_payment( $order_id ) {
		
		global $woocommerce;
		$order = new WC_Order( $order_id );

		// 添加订单备注
		$order->add_order_note( '等待微信支付' );

		// 减少库存
		$order->reduce_order_stock();

		// 清空购物车
		$woocommerce->cart->empty_cart();

		return array(
			'result' => 'success',
			'order' => $order->get_data()
		);
	}
}