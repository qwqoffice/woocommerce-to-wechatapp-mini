<?php

if ( ! defined( 'ABSPATH' ) ) exit;

class W2W_Template_Message {
	
	public $default_templates = array(
		'paid' => array(
			'id' => 'AT0002',
			'keyword_id_list' => array( 24, 5, 40, 6, 4 )
		),
		'note' => array(
			'id' => 'AT0202',
			'keyword_id_list' => array( 11, 5, 9 )
		)
	);
	
	public $template_labels = array(
		'paid' => '订单支付模板',
		'note' => '订单备注模板',
	);
	
	public function __construct() {
		
		// 后台设置项
		add_filter( 'w2w_settings_tabs', array( $this, 'template_message_tab' ), 15 );
		add_action( 'w2w_settings_template_message_tab_content_start', array( $this, 'template_message_tab_content' ) );
		
		// Ajax一键导入模板
		add_action( 'wp_ajax_w2w_import_template', array( $this, 'ajax_import_template' ) );
		
		// 订单支付模板消息
		add_action( 'w2w_order_paid', array( $this, 'send_paid_template_message' ) );
		add_action( 'woocommerce_payment_complete', array( $this, 'send_paid_template_message_for_free_order' ) );
		
		// 存储订单form_id
		add_action( 'woocommerce_checkout_order_processed', array( $this, 'save_order_formid' ), 10, 3 );
		
		// 给顾客的备注模板消息
		add_action( 'woocommerce_new_customer_note', array( $this, 'send_note_template_message' ) );
	}

	// Ajax一键导入模板
	public function ajax_import_template() {
		
		if( ! current_user_can('manage_options') ) wp_die();
		
		$my_templates = W2W()->wxapi->get_templates();
		foreach( $my_templates as $template ) {
			W2W()->wxapi->delete_template( $template['template_id'] );
		}
		
		$setting_templates = array();
		foreach( $this->default_templates as $name => $args ) {
			$setting_templates[$name] = W2W()->wxapi->add_template( $args );
		}
		
		$settings = get_option( 'w2w-settings' );
		$settings['templates'] = $setting_templates;
		update_option( 'w2w-settings', $settings );
		
		echo json_encode( $setting_templates );
		wp_die();
	}
	
	// 后台设置项
	public function template_message_tab( $tabs ) {
		
		$tabs['template_message'] = '模板消息';
		return $tabs;
	}
	
	public function template_message_tab_content( $settings ) {
	?>
	<?php if( empty( $settings['appid'] ) || empty( $settings['appsecret'] ) ): ?>

		<div><p>请先填写 AppID 和 AppSecret</p></div>
		
	<?php else: ?>
		
		<?php $my_templates = W2W()->wxapi->get_templates(); ?>

		<tr><td style="padding: 0;">
		
			<p><button class="button button-default btn-import-template">一键导入模板</button></p>
			<h3>我的模板</h3>
			<table class="widefat fixed my-templates">
				<thead><tr>
					<td>标题</td>
					<td>关键词</td>
					<td>模板ID</td>
					<td>用途</td>
				</tr></thead>
				<tbody>
				<?php if( ! empty( $my_templates ) ):foreach( (array) $my_templates as $template ): ?>
				<tr>
					<td><?php echo $template['title'] ?></td>
					<td><?php $content = preg_replace('/\{\{.*?\}\}/', ', ', $template['content'] ); echo trim( trim( $content ), ', ' ); ?></td>
					<td><?php echo $template['template_id'] ?></td>
					<td>
					<?php
						foreach( $this->default_templates as $name => $args ) {
							if( isset( $settings['templates'][$name] ) && $settings['templates'][$name] == $template['template_id'] ) {
								echo $this->template_labels[$name] . '<i class="dashicons dashicons-yes"></i>';
								break;
							}
						}
					?>
					</td>
				</tr>
				<?php endforeach; endif; ?>
				</tbody>
			</table>

		</td></tr>
		
	<?php endif; ?>
	
	<?php foreach( $this->default_templates as $name => $args ): ?>
		<input type="hidden" name="w2w-settings[templates][<?php echo $name ?>]" value="<?php echo isset( $settings['templates'][$name] ) ? $settings['templates'][$name] : '' ?>" />
	<?php endforeach; ?>
	
	<script>
	;(function($) {
	$(function() {
	// 一键导入模板
	$('.btn-import-template').click(function() {
		
		if( ! confirm('确定删除已有模板并导入预设模板？') ) return false;
		
		var that = this;
		$(that).attr( 'disabled', true ).text('正在导入...');
		
		$.ajax({
			url: '<?php echo admin_url('admin-ajax.php') . '?action=w2w_import_template' ?>',
			type: 'POST',
			dataType: 'json',
			success: function(data) {
				$(that).attr( 'disabled', true ).text('导入成功 请等待跳转...');
				$.each( data, function( name, template_id ) {
					$('input[type="hidden"][name="w2w-settings[templates]['+ name +']"]').val( template_id );
				} );
				$('#setting-form input[type="submit"]').click();
			}
		});
		return false;
	});
	})
	})(jQuery)
	</script>
	<style>
	#tab-template_message .my-templates td {
		word-break: break-all;
	}
	#tab-template_message .my-templates .dashicons {
		color: #4CAF50;
	}
	</style>
	<?php
	}
	
	// 0金额订单模板消息
	public function send_paid_template_message_for_free_order( $order_id ) {
		
		$order = wc_get_order( $order_id );
		if( floatval( $order->get_total() ) == 0 ) {
			$this->send_paid_template_message( $order );
		}
	}
	
	// 订单支付模板消息
	public function send_paid_template_message( $order ) {
		
		$settings = get_option( 'w2w-settings' );
		if( empty( $settings['templates']['paid'] ) ) {
			W2W()->log( 'warning', '请到设置-模板消息页面导入模板' );
			return;
		}
		
		if( ! is_object( $order ) ) {
			
			$order = wc_get_order( $order );
		}
		
		$order_id = $order->get_id();
		$customer = $order->get_user();
		
		if( ! $customer ) return;
		
		$order_number = get_post_meta( $order->get_id(), 'w2w_order_number', true );
		$prepay_id = get_post_meta( $order_id, 'w2w_prepay_id', true );
		$form_id = get_post_meta( $order_id, 'w2w_form_id', true );
		$form_id = floatval( $order->get_total() ) == 0 ? $form_id : $prepay_id;
		if( ! $form_id ) return;
		
		$touser = $customer->user_login;
		$template_id = $settings['templates']['paid'];
		$page = 'pages/order-detail/order-detail?id=' . $order_id;
		$data = array(
			'keyword1' => array(
				'value' => '¥' . floatval( $order->get_total() )
			),
			'keyword2' => array(
				'value' => $this->get_goods_for_template( $order )
			),
			'keyword3' => array(
				'value' => $order_number ? $order_number : ( $order->get_date_created() ? $order->get_date_created()->date_i18n( 'YmdHis' ) . $order->get_id() : '' )
			),
			'keyword4' => array(
				'value' => floatval( $order->get_total() ) == 0 ? '-' : $order->get_transaction_id()
			),
			'keyword5' => array(
				'value' => $order->get_date_created() ? $order->get_date_created()->date_i18n( 'Y年m月d日 H:i:s' ) : ''
			)
		);
		$emphasis_keyword = 'keyword1.DATA';
		
		W2W()->wxapi->send_template( $touser, $template_id, $page, $form_id, $data, $emphasis_keyword );
	}
	
	// 存储订单form_id
	public function save_order_formid( $order_id, $posted_data, $order ) {
		if( isset( $_REQUEST['form_id'] ) ) {
			update_post_meta( $order_id, 'w2w_form_id', $_REQUEST['form_id'] );
		}
	}
	
	// 给顾客的备注模板消息
	public function send_note_template_message( $note ) {
		
		$settings = get_option( 'w2w-settings' );
		if( empty( $settings['templates']['note'] ) ) {
			W2W()->log( 'warning', '请到设置-模板消息页面导入模板' );
			return;
		}
		
		$order_id = $note['order_id'];
		
		$order = wc_get_order( $order_id );
		$customer = $order->get_user();
		
		if( ! $customer ) return;
		
		$order_number = get_post_meta( $order->get_id(), 'w2w_order_number', true );
		$prepay_id = get_post_meta( $order_id, 'w2w_prepay_id', true );
		
		$touser = $customer->user_login;
		$template_id = $settings['templates']['note'];
		$page = 'pages/order-detail/order-detail?id=' . $order_id;

		$data = array(
			'keyword1' => array(
				'value' => $this->get_goods_for_template( $order )
			),
			'keyword2' => array(
				'value' => $order_number
			),
			'keyword3' => array(
				'value' => $note['customer_note'],
				'color' => '#f39800'
			)
		);
		$emphasis_keyword = '';
		
		W2W()->wxapi->send_template( $touser, $template_id, $page, $prepay_id, $data, $emphasis_keyword );
	}
	
	// 获取订单物品描述
	public function get_goods_for_template( $order ) {
		
		$items = $order->get_items();
		$items_count = 0;
		$first_item = current( $items );
		$goods_desciption = $first_item['name'];
		
		foreach( $items as $item ) {
			
			$items_count += wc_stock_amount( $item['qty'] );
		}
		
		$goods_desciption .= ( $items_count > 1 ? ' 等' . $items_count . '件物品' : '' );
		return $goods_desciption;
	}
}