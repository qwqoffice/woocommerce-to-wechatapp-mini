<?php

if ( ! defined( 'ABSPATH' ) ) exit;

class W2W_Weixin_API {
	
	function __construct() {
		
	}
	
	//获取Access Token
	public function get_access_token() {
		
		if( ( $access_token = get_option('w2w-access_token') ) !== false && ! empty( $access_token ) && time() < $access_token['expire_time']) {
			return $access_token['access_token'];
		}
		
		$settings = get_option('w2w-settings');
		if( empty( $settings['appid'] ) || empty( $settings['appsecret'] ) ) {
			return false;
		}
		
		$api_url = 'https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid='. $settings['appid'] .'&secret='. $settings['appsecret'];
		$response = wp_remote_get( $api_url );
		
		if( ! is_wp_error( $response ) && is_array( $response ) && isset( $response['body'] ) ) {
			
			$result = json_decode( $response['body'], true );
			
			if( ! isset( $result['errcode'] ) || $result['errcode'] == 0 ) {
				
				$access_token = array(
					'access_token' => $result['access_token'],
					'expire_time' => time() + intval( $result['expires_in'] )
				);
				update_option( 'w2w-access_token', $access_token );
				
				return $access_token['access_token'];
			}
		}
		
		W2W()->log( 'error', '获取AccessToken错误: ' .  json_encode( $response ) );
		
		return false;
	}
	
	// 获取微信公众平台API地址
	public function API( $key ) {
		
		$access_token = $this->get_access_token();
		$api_urls = array(
			'all_template' => 'https://api.weixin.qq.com/cgi-bin/wxopen/template/library/list',
			'my_template' => 'https://api.weixin.qq.com/cgi-bin/wxopen/template/list',
			'get_template_keywords' => 'https://api.weixin.qq.com/cgi-bin/wxopen/template/library/get',
			'add_template' => 'https://api.weixin.qq.com/cgi-bin/wxopen/template/add',
			'delete_template' => 'https://api.weixin.qq.com/cgi-bin/wxopen/template/del',
			'send_template' => 'https://api.weixin.qq.com/cgi-bin/message/wxopen/template/send',
			//接口A小程序码,总数10万个（永久有效，扫码进入path对应的动态页面）
			'get_qrcode' => 'https://api.weixin.qq.com/wxa/getwxacode',
			//接口B小程序码,不限制数量（永久有效，将统一打开首页，可根据scene跟踪推广人员或场景）
			//'get_qrcode' => 'https://api.weixin.qq.com/wxa/getwxacodeunlimit',
			//接口C小程序二维码,总数10万个（永久有效，扫码进入path对应的动态页面）
			//'get_qrcode' => 'http://api.weixin.qq.com/cgi-bin/wxaapp/createwxaqrcode',
		);
		
		return $api_urls[$key] . '?access_token=' . $access_token;
	}
	
	// 发起API请求
	private function request( $url, $method, $body, $deocde = true ) {
		
		$response = wp_remote_request( $url, array(
			'method' => $method,
			'body' => json_encode( $body )
		) );
		
		if( ! is_wp_error( $response ) && is_array( $response ) && isset( $response['body'] ) ) {
			
			if( $deocde ) {
				$result = json_decode( $response['body'], true );
				
				if( ! isset( $result['errcode'] ) || $result['errcode'] == 0 ) {
					
					return $result;
				}
			}
			else {
				return $response['body'];
			}
		}
		
		W2W()->log( 'error', '微信API请求错误: ' . json_encode( array( 'request' => $body, 'response' => $response ) ) );
		
		return false;
	}
	
	// 获取所有模板列表或帐号下模板列表
	public function get_templates( $type = 'my', $args = array() ) {
		
		$key = '';
		switch( $type ) {
			case 'all':
				$key = 'all_template';
				break;
			case 'my':
				$key = 'my_template';
				break;
			default:
				return false;
		}
		
		$api_url = $this->API($key);
		$body = W2W_Util::param_atts( array(
			'offset' => 0,
			'count' => 10
		), $args );
		
		$result = $this->request( $api_url, 'POST', $body );
		return $result ? $result['list'] : false;
	}
	
	// 获取模板库某个模板标题下关键词库
	public function get_template_keywords( $id ) {
		
		$api_url = $this->API('get_template_keywords');
		
		$result = $this->request( $api_url, 'POST', array( 'id' => $id ) );
		return $result ? $result['keyword_list'] : false;
	}
	
	// 组合模板并添加至帐号下的个人模板库
	public function add_template( $args ) {
		
		$api_url = $this->API('add_template');
		
		$result = $this->request( $api_url, 'POST', $args );
		return $result ? $result['template_id'] : false;
	}
	
	// 删除帐号下的某个模板
	public function delete_template( $template_id ) {
		
		$api_url = $this->API('delete_template');
		
		$result = $this->request( $api_url, 'POST', array( 'template_id' => $template_id ) );
		return $result ? true : false;
	}
	
	// 发送模板消息
	public function send_template( $touser, $template_id, $page, $form_id, $data, $emphasis_keyword ) {
		
		$api_url = $this->API('send_template');
		
		$result = $this->request( $api_url, 'POST', array(
			'touser' => $touser,
			'template_id' => $template_id,
			'page' => $page,
			'form_id' => $form_id,
			'data' => $data,
			'emphasis_keyword' => $emphasis_keyword,
		) );
		return $result ? true : false;
	}
	
	// 获取小程序码
	public function get_qrcode( $path ) {
		
		$api_url = $this->API('get_qrcode');
		
		$result = $this->request( $api_url, 'POST', array(
			'path' => $path,
			'width' => 100,
			'auto_color' => false,
			'line_color' => array(
				'r' => '0',
				'g' => '0',
				'b' => '0'
			),
		), false );
		return $result;
	}
}
