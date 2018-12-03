<?php

if ( ! defined( 'ABSPATH' ) ) exit;

require_once( WP_W2W_PATH . 'includes/wxpay/class.WxPay.Api.php' );
require_once( WP_W2W_PATH . 'includes/wxpay/class.WxPay.Notify.php' );
	
class W2W_Util {
	
	/**
	 * 检验数据的真实性，并且获取解密后的明文.
	 * @param $sessionKey string 用户在小程序登录后获取的会话密钥
	 * @param $appid string 小程序的appid
	 * @param $encryptedData string 加密的用户数据
	 * @param $iv string 与用户数据一同返回的初始向量
	 * @param $data string 解密后的原文
	 *
	 * @return int 成功0，失败返回对应的错误码
	 */
	public static function decrypt_data( $appid, $sessionKey, $encryptedData, $iv, &$data ) {
		
		$errors = array(
			'OK'				=> 0,
			'IllegalAesKey'		=> -41001,
			'IllegalIv'			=> -41002,
			'IllegalBuffer'		=> -41003,
			'DecodeBase64Error' => -41004
		);
		
		if (strlen($sessionKey) != 24)
		{
			return $errors['IllegalAesKey'];
		}
		$aesKey=base64_decode($sessionKey);

		
		if (strlen($iv) != 24)
		{
			return $errors['IllegalIv'];
		}
		$aesIV=base64_decode($iv);

		$aesCipher=base64_decode($encryptedData);

		$result=openssl_decrypt( $aesCipher, 'AES-128-CBC', $aesKey, 1, $aesIV);

		$dataObj=json_decode( $result );
		if( $dataObj  == NULL )
		{
			return $errors['IllegalBuffer'];
		}
		if( $dataObj->watermark->appid != $appid )
		{
			return $errors['IllegalBuffer'];
		}
		$data = $result;
		return $errors['OK'];
	}
	
	// 32位随机数
	public static function get_random() {
		return md5( uniqid( md5( microtime( true ) ), true ) );
	}
	
	// 生成Session
	public static function generate_session( $customer_id ) {
		
		$w2w_session = md5( uniqid( md5( microtime( true ) ), true ) );
		update_user_meta( $customer_id, 'w2w_session', $w2w_session );
		update_user_meta( $customer_id, 'w2w_session_expire', time() + 7200 );
		
		return $w2w_session;
	}
	
	// 根据 Session 获取 OpenID
	public static function get_session( $session_id ) {
		
		if( empty( $session_id ) ) {
			return false;
		}
		
		$user_query = new WP_User_Query( array( 'meta_key' => 'w2w_session', 'meta_value' => $session_id ) );
		$users = $user_query->get_results();
		
		if( ! empty( $users ) ) {
			$customer = $users[0];
			return array(
				'user_id' => $customer->ID,
				'openid' => $customer->user_login,
				'expire' => get_user_meta( $customer->ID, 'w2w_session_expire', true )
			);
		}
		
		return false;
	}

	// 根据 Session 登录对应用户
	public static function login( $session_id ) {
		
		$session = self::get_session( $session_id );
		if( $session ) {
			
			wp_set_current_user( $session['user_id'] );
			wc_set_customer_auth_cookie( $session['user_id'] );
			return true;
		}
		
		return false;
	}
	
	// Union ID 获取对应小程序用户
	public static function get_user_by_unionid( $unionid ) {
		
		if( empty( $unionid ) ) {
			return false;
		}
		
		$user_query = new WP_User_Query( array( 'meta_key' => 'w2w_unionid', 'meta_value' => $unionid ) );
		$users = $user_query->get_results();
		
		if( ! empty( $users ) ) {
			$customer = $users[0];
			return $customer;
		}
		
		return false;
	}
	
	// 组合参数
	public static function param_atts( $pairs, $atts ) {
		
		$atts = (array)$atts;
		$out = array();
		foreach( $pairs as $name => $default ) {
			if( array_key_exists( $name, $atts ) ) {
				$out[$name] = $atts[$name];
			}    
			else {
				$out[$name] = $default;
			}
		}
		
		return $out;
	}
	
	// 检查付款通知签名
	public static function check_notify_sign( $data, $key ) {
		
		ksort( $data );
		$buff = '';
		
		foreach( $data as $k => $v ) {
			if( $k == 'sign' ||  is_array( $v ) ) continue;
			$buff .= $k . '=' . $v . '&';
		}
		
		$string_sign = $buff . 'key=' . $key;
		$sign = strtoupper( md5( $string_sign ) );
		
		if( $sign == $data['sign'] ) {
			
			return true;
		}
	}
	
	// 查询订单
	public static function query_order( $order_id, $type = 'wechat_order' ) {
		
		$payment_gateway = new W2W_WC_Gateway_WeChatPay();
		$input = new W2W_WxPayOrderQuery();
		if( $type == 'wechat_order' ) {
			$input->SetTransaction_id( $order_id );
		}
		else if( $type == 'wc_order' ) {
			$input->SetOut_trade_no( $order_id );
		}
		
		$result = W2W_WxPayApi::orderQuery( $input );
		W2W()->log( 'info', '订单查询: ' . json_encode( $result ) );
		
		if( array_key_exists( 'return_code', $result )
			&& array_key_exists( 'result_code', $result )
			&& $result['return_code'] == 'SUCCESS'
			&& $result['result_code'] == 'SUCCESS'
			&& $result['trade_state'] == 'SUCCESS'
			&& self::check_notify_sign( $result, $payment_gateway->get_option( 'key' ) ) )
		{
			$success = true;
		}
		else{
			$success = false;
		}
		
		return array( 'success' => $success, 'order' => $result );
	}
	
	// 根据省代码获取名字
	public static function get_state_name( $country, $state ) {
		
		$states = WC()->countries->get_states();
		return isset( $states[$country][$state] ) ? $states[$country][$state] : '';
	}
	
	/**
	 * 删除指定标签
	 *
	 * @param array $tags     删除的标签  数组形式
	 * @param string $str     html字符串
	 * @param bool $content   true保留标签的内容text
	 * @return mixed
	 */
	public static function strip_html_tags( $tags, $str, $content = true ) {
		
		$html = array();
		foreach ($tags as $tag) {
			if( $content ) {
				$html[] = '/(<(?:\/' . $tag . '|' . $tag . ')[^>]*>)/is';
			}
			else{
				$html[] = '/(<' . $tag . '.*?>[\s|\S]*?<\/' . $tag . '>)/is';
			}
			
		}
		$data = preg_replace( $html, '', $str );
		return $data;
	}
	
	// 获取时间线格式的时间
	public static function get_timeline_date_format( $datetime ) {
		
		$date_diff = strtotime( date_i18n('Y-m-d') ) - strtotime( date( 'Y-m-d', strtotime( $datetime ) ) );
		$day = (int)( $date_diff / ( 3600 * 24 ) );
		//相差7天不作修改
		if( $day > 7 ) {
			return date( 'Y-m-d', strtotime( $datetime ) );
		}
		//...天前
		else if( $day >= 3 && $day <= 7 ) {
			return $day . ' 天前';
		}
		//前天...
		else if( $day == 2 ) {
			return ' 前天 '. date( 'H:i', strtotime( $datetime ) );
		}
		//昨天...
		else if( $day == 1 ) {
			return ' 昨天 ' . date( 'H:i', strtotime( $datetime ) );
		}
		//今天
		else if( $day < 1 ) {
			$time_diff = time() - strtotime( $datetime );
			$hour = (int)( ( $time_diff % ( 3600 * 24 ) ) / 3600 );
			$min = (int)( $time_diff % 3600 / 60 );
			$sec = (int)( $time_diff % 60 );
			//...小时前
			if( $hour >= 1 ) {
				return $hour . ' 小时前';
			}
			else {
				//半小时前
				if( $min >= 30 ) {
					return '半小时前';
				}
				//...分钟前
				else if( $min >= 1 && $min < 30 ) {
					return $min . ' 分钟前';
				}
				//...秒前
				else {
					return $sec . ' 秒前';
				}
			}
		}
	}
	
	// 获取产品图片
	public static function get_product_images( $product ) {
		$images = array();
		$attachment_ids = array();

		if ( $product->is_type( 'variation' ) ) {
			if ( has_post_thumbnail( $product->get_variation_id() ) ) {
				// Add variation image if set.
				$attachment_ids[] = get_post_thumbnail_id( $product->get_variation_id() );
			} elseif ( has_post_thumbnail( $product->get_id() ) ) {
				// Otherwise use the parent product featured image if set.
				$attachment_ids[] = get_post_thumbnail_id( $product->get_id() );
			}
		} else {
			// Add featured image.
			if ( has_post_thumbnail( $product->get_id() ) ) {
				$attachment_ids[] = get_post_thumbnail_id( $product->get_id() );
			}
			// Add gallery images.
			$attachment_ids = array_merge( $attachment_ids, $product->get_gallery_image_ids() );
		}

		// Build image data.
		foreach ( $attachment_ids as $position => $attachment_id ) {
			$attachment_post = get_post( $attachment_id );
			if ( is_null( $attachment_post ) ) {
				continue;
			}

			$attachment = wp_get_attachment_image_src( $attachment_id, 'full' );
			if ( ! is_array( $attachment ) ) {
				continue;
			}

			$images[] = array(
				'id'            => (int) $attachment_id,
				'date_created'  => wc_rest_prepare_date_response( $attachment_post->post_date_gmt ),
				'date_modified' => wc_rest_prepare_date_response( $attachment_post->post_modified_gmt ),
				'src'           => current( $attachment ),
				'shop_single'     => current( wp_get_attachment_image_src( $attachment_id, 'shop_single')),
				'shop_thumbnail'     => current( wp_get_attachment_image_src( $attachment_id, 'shop_thumbnail')),
				'name'          => get_the_title( $attachment_id ),
				'alt'           => get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ),
				'position'      => (int) $position,
			);
		}

		// Set a placeholder image if the product has no images set.
		if ( empty( $images ) ) {
			$images[] = array(
				'id'            => 0,
				'date_created'  => wc_rest_prepare_date_response( current_time( 'mysql' ) ), // Default to now.
				'date_modified' => wc_rest_prepare_date_response( current_time( 'mysql' ) ),
				'src'           => wc_placeholder_img_src(),
				'shop_single'     => wc_placeholder_img_src(),
				'shop_thumbnail'     => wc_placeholder_img_src(),
				'name'          => __( 'Placeholder', 'woocommerce' ),
				'alt'           => __( 'Placeholder', 'woocommerce' ),
				'position'      => 0,
			);
		}

		return $images;
	}
	
	// 获取文章图片
	public static function get_post_image( $post_id ) {
		$src = wp_get_attachment_image_src( (int) get_post_thumbnail_id( $post_id ), 'medium_large' );
		return $src ? current( $src ) : false;
	}
}