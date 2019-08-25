<?php
/**
 * Plugin Name: WooCommerce 微信小程序迷你版
 * Plugin URI: https://www.qwqoffice.com/article-30.html
 * Description: 使用WooCommerce作为后台的商城小程序，与WooCommerce完美整合，无需编程技能，最快5分钟即可完成接入
 * Version: 1.9
 * Author: QwqOffice
 * Author URI: https://www.qwqoffice.com/
 * WC requires at least: 3.0.0
 * WC tested up to: 3.7.0
**/

if ( ! class_exists( 'WooCommerce_To_WeChatApp' ) ) {
	
	//定义常量
	define( 'WP_W2W_PATH', plugin_dir_path( __FILE__ ) );
	define( 'WP_W2W_URL', plugin_dir_url( __FILE__ ) );

	include_once( WP_W2W_PATH . 'includes/class-w2w-util.php' );
	include_once( WP_W2W_PATH . 'includes/class-w2w-weixin-api.php' );
	include_once( WP_W2W_PATH . 'includes/class-w2w-template-message.php' );
	include_once( WP_W2W_PATH . 'vendor/plugin-update-checker-4.6/plugin-update-checker.php' );
	
	class WooCommerce_To_WeChatApp {
		
		private static $_instance = null;
		public $logger = null;
		public $notices = array();
		public $extensions = array();
		public $w2wapi = null;
		public $wxapi = null;
		public $template_message = null;
		public $payments = array(
			'wxapay',
			'cod',
			'bacs'
		);
		
		public function __construct() {
			
			// Woocommerce插件依赖
			add_action( 'admin_init', array( $this, 'check_woocommerce' ) );

			// 所有插件加载完成
			add_action( 'plugins_loaded', array( $this, 'plugins_loaded' ) );
			
			// 根据 SESSION 登录对应用户
			add_action( 'init', array( $this, 'login_with_session' ), -1 );
			
			// 根据 SESSION 返回是否需要登录
			add_filter( 'rest_pre_echo_response', array( $this, 'send_need_login_header' ), 10, 3 );
			
			// 微信资料显示
			add_filter( 'manage_users_columns', array( $this, 'add_wechat_column' ) );
			add_filter( 'manage_users_custom_column', array( $this, 'wechat_column_content' ), 10, 3 );
			
			// WooCommerce 加载完成
			add_action( 'woocommerce_init', array( $this, 'woocommerce_loaded' ) );
			
			// 添加微信支付网关
			add_filter( 'woocommerce_payment_gateways', array( $this, 'payment_gateway_classes' ) );
			
			// 订单金额为0时清空购物车
			add_filter( 'woocommerce_checkout_no_payment_needed_redirect', array( $this, 'empty_cart_no_payment_needed' ) );
			
			// 提交订单时生成订单号
			add_action( 'woocommerce_checkout_order_processed', array( $this, 'generate_order_number' ), 10, 3 );
			
			// 可根据订单号搜索订单
			add_filter( 'woocommerce_shop_order_search_fields', array( $this, 'shop_order_search_order_number' ) );
			
			// 可根据订单号获取订单
			add_filter( 'woocommerce_order_data_store_cpt_get_orders_query', array( $this, 'handle_w2w_order_number_query_var' ), 10, 2 );
			
			// 订单列表显示订单号
			add_filter( 'manage_edit-shop_order_columns', array( $this, 'shop_order_orer_number_column' ) );
			add_action( 'manage_shop_order_posts_custom_column', array( $this, 'shop_order_orer_number_column_content' ) );
			
			// 后台
			if ( is_admin() ) {
				
				// 后台菜单
				add_action( 'admin_menu', array( $this, 'plugin_setup_menu' ) );
				
				// 后台通知
				add_action( 'admin_notices', array( $this, 'admin_notices' ), 15 );
				
				// 后台样式
				add_action( 'admin_enqueue_scripts', array( $this, 'admin_style' ) );
				
				// 设置按钮
				add_filter( 'plugin_action_links_' . plugin_basename(__FILE__),	array( $this, 'add_action_links' ) );
				
				// 高级设置
				add_action( 'w2w_settings_advanced_tab_content_start', array( $this, 'advanced_settings' ) );
			}
			
			// 小程序内
			if( $this->is_in_miniprogram() ) {
				// 自定义结算字段
				add_filter( 'woocommerce_checkout_fields' , array( $this, 'override_checkout_fields' ), 99999 );
				
				// 仅对微信小程序开启微信支付
				add_filter( 'woocommerce_available_payment_gateways', array( $this, 'wxpay_only_for_wxapp' ) );
				
				// 兼容WC3.6.0新增的frontend判断
				add_filter( 'woocommerce_is_rest_api_request', '__return_false' );
			}
			
			// 当前选项卡、修改AppID或AppSecret时删除AccessToken缓存
			add_filter( 'pre_update_option_w2w-settings', array( $this, 'pre_update_option' ), 10, 3 );
			
			//插件更新
			Puc_v4_Factory::buildUpdateChecker(
				'https://www.qwqoffice.com/wordpress-dev/plugins/woocommerce-to-wechatapp-mini/info.json',
				__FILE__,
				'woocommerce-to-wechatapp-mini'
			);
			
			$this->wxapi = new W2W_Weixin_API();
			$this->template_message = new W2W_Template_Message();
		}
		
		private function __clone(){}
		
        public static function instance(){
            if ( is_null( self::$_instance ) ) {
                self::$_instance = new self();
            }
            return self::$_instance;
        }
		
		// 写入日志
		public function log( $level, $message ) {
			
			if( $this->logger == null ) {
				$this->logger = wc_get_logger();
			}
			
			$settings = get_option( 'w2w-settings' );
			if( ! isset( $settings['debug'] ) ) return;
			
			$this->logger->log( $level, $message, array( 'source' => 'w2w' ) );
		}
		
		// 是否微信内访问
		public function is_in_miniprogram() {
			
			return ! empty( $_SERVER['HTTP_USER_AGENT'] ) && ! empty( $_SERVER['HTTP_REFERER'] ) && preg_match( '/servicewechat\.com/i', $_SERVER['HTTP_REFERER'] );
		}
		
		// Woocommerce插件依赖
		public function check_woocommerce() {
			
			if( is_admin() && ! class_exists( 'WooCommerce' ) ) {
				
				$woocommerce_missing = sprintf( ' <a href="%s" class="%s" aria-label="%s" data-title="%s">%s</a>',
					esc_url( network_admin_url( 'plugin-install.php?tab=plugin-information&plugin=woocommerce' .
						'&TB_iframe=true&width=600&height=550' ) ),
					"thickbox open-plugin-details-modal",
					esc_attr( '更多关于 WooCommerce 的信息' ),
					esc_attr( 'WooCommerce' ),
					'查看详情'
				);
				$this->add_admin_notice( 'no_woocommerce', 'error', 'WooCommerce 微信小程序 要求激活 WooCommerce 3.0 或以上' . $woocommerce_missing );
				
				deactivate_plugins( plugin_basename( __FILE__ ) ); 

				if( isset( $_GET['activate'] ) ){
					
					unset( $_GET['activate'] );
				}
			}
		}
		
		// 所有插件加载完成
		public function plugins_loaded() {
			
			// 微信支付
			if( class_exists( 'WooCommerce' ) ) {
				
				include( WP_W2W_PATH . 'includes/class-w2w-wc-gateway-wechatpay.php' );
			}
		}
		
		// 根据 SESSION 登录对应用户
		public function login_with_session() {
			
			if( ! empty( $_REQUEST['w2w_session'] ) ) {
				W2W_Util::login( $_REQUEST['w2w_session'] );
			}
		}
		
		// 根据 SESSION 返回是否需要登录
		public function send_need_login_header( $result, $server, $request ) {
			
			if( ! empty( $request['w2w_session'] ) ) {
				
				$session = W2W_Util::get_session( $request['w2w_session'] );
				
				if( $session && time() >= $session['expire'] ) {
					
					$server->send_header( 'X-W2W-Session-Refresh', 'True' );
				}
				
				if( ! $session ) {
					
					$server->send_header( 'X-W2W-Session-Invalid', 'True' );
				}
			}

			return $result;
		}
		
		// 微信资料显示
		public function add_wechat_column( $columns ) {
			
			$i = 0;
			foreach( $columns as $k => $v ) {
				if( $k == 'name' ) {
					$columns = array_merge( array_slice( $columns, 0, $i + 1 ), array( 'wx_info' => '微信资料' ), array_splice( $columns, $i + 1 ) );
					break;
				}
				$i++;
			}
			return $columns;
		}

		public function wechat_column_content( $val, $column_name, $user_id ) {
			
			switch( $column_name ) {
				case 'wx_info' :
					$user_info = get_userdata( $user_id );
					$avatar = get_user_meta( $user_id, 'w2w_avatar', true );
					return $avatar ? ( '<img alt="" src="'. $avatar
							.'" class="avatar avatar-32 photo" height="32" width="32" style="margin-right:5px;vertical-align: middle">'
							.'<strong style="vertical-align: middle">'. $user_info->display_name .'</strong>' ) : '';
					break;
				default:
			}
			return $val;
		}
		
		// WooCommerce 激活时加载文件
		public function woocommerce_loaded() {
			$settings = get_option( 'w2w-settings' );
			
			if( ! empty( $settings['appid'] ) && ! empty( $settings['appsecret'] ) ) {
				include( 'includes/class-w2w-wc-api.php' );
				$this->w2wapi = new W2W_WC_API();
			}
			else{
				$api_missing = sprintf( ' <a href="%s" class="%s">%s</a>',
					esc_url( network_admin_url( 'options-general.php?page=w2w-settings' ) ),
					'',
					'立即填写'
				);
				$this->add_admin_notice( 'no_wechatapp', 'notice notice-warning', 'WooCommerce 微信小程序 要求填写 AppID 和 AppSecret' . $api_missing );
			}
		}
		
		// 移除姓氏和邮箱字段
		public function override_checkout_fields( $fields ) {
			$fields['billing']['billing_last_name']['required'] = false;
			$fields['billing']['billing_email']['required'] = false;
			return $fields;
		}
		
		// 添加微信支付网关
		public function payment_gateway_classes( $methods ) {
			
			$methods[] = 'W2W_WC_Gateway_WeChatPay';
			return $methods;
		}
		
		// 仅对微信小程序开启微信支付
		public function wxpay_only_for_wxapp( $gateways ) {
			unset( $gateways['wxapay'] );
			return $gateways;
		}
		
		// 订单金额为0时清空购物车
		public function empty_cart_no_payment_needed( $url ) {
			
			WC()->cart->empty_cart();
			return $url;
		}
		
		// 提交订单时生成订单号
		public function generate_order_number( $order_id, $posted_data, $order ) {
			
			$settings = get_option( 'w2w-settings' );
			$prefix = isset( $settings['order_number_prefix'] ) ? $settings['order_number_prefix'] : '';
			$order_number = $prefix . $order->get_date_created()->date_i18n( 'YmdHis' ) . $order_id;
			update_post_meta( $order_id, 'w2w_order_number', $order_number );
		}
		
		// 可根据订单号搜索订单
		public function shop_order_search_order_number( $search_fields ) {

			$search_fields[] = 'w2w_order_number';
			return $search_fields;
		}
		
		// 可根据订单号获取订单
		public function handle_w2w_order_number_query_var( $query, $query_vars ) {
			if ( ! empty( $query_vars['w2w_order_number'] ) ) {
				$query['meta_query'][] = array(
					'key' => 'w2w_order_number',
					'value' => esc_attr( $query_vars['w2w_order_number'] ),
				);
			}
			return $query;
		}
		
		// 订单列表显示订单号
		public function shop_order_orer_number_column( $columns ) {

			$new_columns = array();
			foreach ($columns as $column_name => $column_info) {
				$new_columns[$column_name] = $column_info;
				if ('order_total' === $column_name) {
					$new_columns['w2w_order_number'] = '订单号';
				}
			}
			return $new_columns;
		}

		public function shop_order_orer_number_column_content( $column ) {
			
			global $post;

			if ( 'w2w_order_number' === $column ) {
				$order = wc_get_order( $post->ID );
				$order_number = get_post_meta( $order->get_id(), 'w2w_order_number', true );
				echo $order_number ? $order_number : ( $order->get_date_created() ? $order->get_date_created()->date_i18n( 'YmdHis' ) . $order->get_id() : '' );
			}
		}
		
		// 添加通知
		public function add_admin_notice( $slug, $class, $message ) {
			
			$this->notices[ $slug ] = array(
				'class'   => $class,
				'message' => $message
			);
		}
		
		// 显示通知
		public function admin_notices() {
			
			foreach ( (array) $this->notices as $notice_key => $notice ) {
			?>
			<div class="<?php echo esc_attr( $notice['class'] ) ?>"><p><?php echo $notice['message'] ?></p></div>
			<?php
			}
		}
		
		// 后台样式
		public function admin_style() {
			 wp_enqueue_style( 'w2w-admin-css', WP_W2W_URL. 'assets/css/menu.css' );
		}
		
		// 设置按钮
		public function add_action_links( $links ) {

			$setting_link = array(
				'<a href="' . admin_url('options-general.php?page=w2w-settings') . '">设置</a>',
				'<a href="https://www.qwqoffice.com/article-30.html" style="color:#f00;font-weight:bold;" target="_blank">升级高级版！</a>',
			);

			return array_merge( $setting_link, $links );
		}
		
		// 高级设置
		public function advanced_settings( $settings ) {
			
		?>
		<tr><th scope="row"><label for="w2w-settings[order_number_prefix]">订单号前缀</label></th><td>
		
		<input type="text"
				id="w2w-settings[order_number_prefix]"
				name="w2w-settings[order_number_prefix]"
				value="<?php echo isset( $settings['order_number_prefix'] ) ? $settings['order_number_prefix'] : '' ?>"
		/>
		<p class="description">更改后仅对新订单生效，请确保没有待付款的订单再修改</p>
		</td></tr>
		<?php
		}
		
		// 后台设置项
		public function plugin_setup_menu() {
			
			$menu_title = '商城小程序<span style="font-family:W2W; display:inline-block; font-size:10px; transform:translateX(3px) translateY(-5px);">&#xe900;</span>';
			add_menu_page( 'WooCommerce 微信小程序设置', $menu_title, 'manage_options', 'w2w-settings', array( $this, 'settings_init' ), 'none', 56 );
			add_action( 'admin_init', array( $this, 'register_settings' ) );
		}
				 
		public function settings_init() {
			
			include( WP_W2W_PATH . 'templates/settings.php' );
		}
		
		public function register_settings() {
			
			register_setting( 'w2w-settings-group', 'w2w-settings' );
		}
		
		// 当前选项卡、修改AppID或AppSecret时删除AccessToken缓存
		public function pre_update_option( $value, $old_value, $option ) {
			
			if( isset( $value['current_tab'] ) ) {
				$_REQUEST['_wp_http_referer'] = add_query_arg( 'tab', $value['current_tab'], $_REQUEST['_wp_http_referer'] );
				unset( $value['current_tab'] );
			}
			
			if( isset( $value['appid'], $value['appsecret'], $old_value['appid'], $old_value['appsecret'] )
				&& ( $value['appid'] != $old_value['appid'] || $value['appsecret'] != $old_value['appsecret'] ) ) {
				
				delete_option( 'w2w-access_token' );
			}
			
			return $value;
		}
	}
	
	function W2W() {
		return WooCommerce_To_WeChatApp::instance();
	}
	
	// 实例化并加入全局变量
	$GLOBALS['w2w'] = W2W();
}