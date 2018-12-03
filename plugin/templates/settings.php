<?php

//禁止直接访问
if ( ! defined( 'ABSPATH' ) ) exit;

wp_enqueue_media();

$settings = get_option( 'w2w-settings' );

$tabs = array(
	'general' => '常规',
	'advanced' => '高级'
);
$tabs = apply_filters( 'w2w_settings_tabs', $tabs );
$tabs_slugs = array_keys($tabs);
$current_tab = isset ( $_GET['tab'] ) ? $_GET['tab'] : $tabs_slugs[0];

?>
<div class="wrap">
<h1>WooCommerce 微信小程序设置</h1>

<nav class="nav-tab-wrapper">
<?php foreach( $tabs as $key => $label ): ?>
	<a href="#tab-<?php echo $key ?>" class="nav-tab <?php if( $key == $current_tab ) echo 'nav-tab-active' ?>" data-name="<?php echo $key ?>"><?php echo $label ?></a>
<?php endforeach; ?>
<a href="https://www.qwqoffice.com/article-30.html" class="nav-tab" target="_blank">升级高级版<span class="badge" style="background-color:#F44336;border-radius:50%;"></span></a>
</nav>

<form id="setting-form" method="post" action="options.php" enctype="multipart/form-data">

	<?php settings_fields( 'w2w-settings-group' ); ?>
	<?php do_settings_sections( 'w2w-settings-group' ); ?>
	
	<?php foreach( $tabs as $key => $label ): ?>
	
	<div id="tab-<?php echo $key ?>" <?php if( $current_tab == $key ) echo 'class="tab-content-active"' ?>>
	<table class="form-table settings-table">
	
	<?php do_action( 'w2w_settings_' . $key . '_tab_content_start', $settings ) ?>
	
	<?php if( $key == 'general' ): // 常规 ?>
		
		<tr>
		<th scope="row"><label for="w2w-settings[appid]">微信小程序 AppID</label></th>
		<td><input type="text" id="w2w-settings[appid]" name="w2w-settings[appid]" value="<?php echo isset( $settings['appid'] ) ? esc_attr( $settings['appid'] ) : '' ?>" class="regular-text" autocomplete="off"></td>
		</tr>
		
		<tr>
		<th scope="row"><label for="w2w-settings[appsecret]">微信小程序 AppSecret</label></th>
		<td><input type="text" id="w2w-settings[appsecret]" name="w2w-settings[appsecret]" value="<?php echo isset( $settings['appsecret'] ) ? esc_attr( $settings['appsecret'] ) : '' ?>" class="regular-text" autocomplete="off"></td>
		</tr>
		
		<tr><td colspan="2" style="padding: 0;">
			<h4>轮播图</h4>
			<div class="banner-wrapper">
			<?php if( ! empty( $settings['banner'] ) ): ?>
				<?php foreach( (array) $settings['banner'] as $i => $banner ): ?>
				<div class="banner">
					<i class="dashicons dashicons-no-alt remove-banner"></i><i class="dashicons dashicons-move move-banner"></i>
					<button><div class="thumb" style="background-image:url(<?php echo $banner['image'] ?>)"></div></button>
					<input type="hidden" name="w2w-settings[banner][<?php echo $i ?>][image]" value="<?php echo $banner['image'] ?>"/>
					<input type="text" name="w2w-settings[banner][<?php echo $i ?>][url]" value="<?php echo $banner['url'] ?>" placeholder="链接"/>
				</div>
				<?php endforeach; ?>
			<?php else: ?>
				<div class="banner">
					<i class="dashicons dashicons-no-alt remove-banner"></i><i class="dashicons dashicons-move move-banner"></i>
					<button><div class="thumb"></div></button>
					<input type="hidden" name="w2w-settings[banner][0][image]" />
					<input type="text" name="w2w-settings[banner][0][url]" placeholder="链接" />
				</div>
			<?php endif; ?>
			</div>
			<button class="button button-default add-banner">添加</button>
		</td></tr>
		
		<tr>
		<th scope="row">调试模式</th>
		<td><label for="w2w-settings[debug]"><input type="checkbox" id="w2w-settings[debug]" name="w2w-settings[debug]" <?php checked( isset( $settings['debug'] ) ) ?>>开启调试模式</label></td>
		</tr>
		
	<?php elseif( $key == 'advanced' ):
	
		$pages = get_pages( array(
			'sort_order' => 'desc',
			'sort_column' => 'ID'
		) );
	?>
	
		<tr>
		<th scope="row"><label for="w2w-settings[about_page]">关于页面</label></th>
		<td>
		<select name="w2w-settings[about_page]">
			<option value="0">请选择</option>
		<?php foreach( $pages as $page ): ?>
			<option value="<?php echo $page->ID ?>" <?php selected( isset( $settings['about_page'] ) && $settings['about_page'] == $page->ID ) ?>>
				<?php echo $page->post_title ?>
			</option>
		<?php endforeach; ?>
		</select>
		</td>
		</tr>
		
		<tr><th scope="row">产品详情页相关产品个数</th><td>
		<label for="w2w-settings[related_products_quantity]">
		<input type="number" id="w2w-settings[related_products_quantity]" name="w2w-settings[related_products_quantity]" value="<?php echo isset( $settings['related_products_quantity'] ) ? $settings['related_products_quantity'] : '' ?>" placeholder="4" />
		<p class="description">同一分类、同一标签、推荐销售和交叉销售均视为相关产品</p>
		</label>
		</td></tr>

	<?php endif; ?>

	<?php do_action( 'w2w_settings_' . $key . '_tab_content_end', $settings ) ?>

	</table>
	</div>
	
	<?php endforeach; ?>
	
	<?php submit_button(); ?>
	<input type="hidden" name="w2w-settings[current_tab]" value="<?php echo $current_tab ?>">
</form>
</div>
<script>
;(function($) {
	
	function resetBannerIndex() {
		$('.banner-wrapper .banner').each(function(i,e) {
			$(this).find('input[type="hidden"]').attr('name', 'w2w-settings[banner]['+ i +'][image]');
			$(this).find('input[type="text"]').attr('name', 'w2w-settings[banner]['+ i +'][url]');
		});
	}
	var mediaUploader;
	
	$(function() {
		
		// 媒体库调用
		$('.banner button').click(function(e) {
			e.preventDefault();
			
			trigger = $(this);
			
			if (mediaUploader) {
				mediaUploader.open();
				return;
			}
			mediaUploader = wp.media.frames.file_frame = wp.media({
				title: '选择图片',
				button: {
					text: '选择'
				},
				multiple: false
			});
				
			mediaUploader.on('select', function() {
				var attachment = mediaUploader.state().get('selection').first().toJSON();
				trigger.find('.thumb').css('background-image','url('+ attachment.url +')');
				trigger.next('input[type="hidden"]').val( attachment.url );
			});
			mediaUploader.open();
		});
		
		// Banner拖拽排序
		$('.banner-wrapper').sortable({
			handle: '.move-banner',
			tolerance: 'pointer',
			start: function(e, ui) {
				ui.placeholder.width( ui.item.width() );
				ui.placeholder.height( ui.item.height() );
				ui.placeholder.css( 'visibility', 'visible' );
			},
			update: function() {
				resetBannerIndex();
			}
		});
		
		// 移除Banner
		$('.remove-banner').click(function() {
			if( $('.banner-wrapper .banner').length > 1 ) {
				$(this).parents('.banner').remove();
				resetBannerIndex();
			}
			return false;
		});
		
		// 添加Banner
		$('.add-banner').click(function() {
			var count = $('.banner-wrapper .banner').length;
			$banner = $('.banner-wrapper .banner:first').clone(true);
			$banner.find('.thumb').css('background-image', '');
			$banner.find('input[type="hidden"]').val('');
			$banner.find('input[type="text"]').val('');
			$('.banner-wrapper').append( $banner );
			resetBannerIndex();
			return false;
		});
		
		// 选项卡切换
		$('.nav-tab-wrapper a').click(function() {
			var href = $(this).attr('href');
			if( href.indexOf('#') < 0 ) {
				return;
			}
			
			$('.nav-tab-wrapper a').addClass('nav-tab-active').not($(this)).removeClass('nav-tab-active');
			$('form > div').addClass('tab-content-active').not( $(href) ).removeClass('tab-content-active');
			
			$('input[name="w2w-settings[current_tab]"]').val( $(this).data('name') );
			history.replaceState( null, null, changeURLArg( location.href, 'tab', $(this).data('name') ) );
			return false;
		});
		
		function changeURLArg( url, arg, arg_val ) {
			var pattern = arg + '=([^&]*)';
			var replaceText = arg + '=' + arg_val; 
			if( url.match( pattern ) ) {
				var tmp = '/(' + arg + '=)([^&]*)/gi';
				tmp = url.replace( eval(tmp), replaceText );
				return tmp;
			}else{ 
				if( url.match('[\?]') ) { 
					return url + '&' + replaceText; 
				}else{ 
					return url + '?' + replaceText; 
				} 
			}
		}
	})
})(jQuery)
</script>
<style>
.nav-tab-wrapper .nav-tab {
	position: relative;
}
.nav-tab-wrapper .nav-tab .badge {
	position: absolute;
	bottom: 100%;
	left: 100%;
	transform: translateX(-50%) translateY(50%);
	display: inline-block;
	width: 9px;
	height: 9px;
}
.nav-tab-wrapper .nav-tab .badge.count {
	width: auto;
	height: auto;
	line-height: 1;
	padding: 2px 6px;
	color: #FFF;
	background-color: #F44336;
	border-radius: 10px;
}
form > div {
	display: none;
}
form > div.tab-content-active {
	display: block;
}
.banner-wrapper,
.banner-wrapper * {
	box-sizing: border-box;
}
.settings-table .banner-wrapper {
	display: flex;
	display: -webkit-flex;
	flex-wrap: wrap;
	-webkit-flex-wrap: wrap;
}
.settings-table .banner {
	position: relative;
	width: 150px;
	float: left;
	margin-right: 10px;
	margin-bottom: 10px;
    box-sizing: content-box;
    border: 1px solid transparent;
}
.settings-table .banner.ui-sortable-placeholder {
	border: 1px dashed #AAA;
	background-color: #C8EFD0;
}
.settings-table .banner button,
.settings-table .banner input[type="text"] {
	width: 100%;
	margin: 0;
}
.settings-table .banner button {
	font-size: 16px;
	line-height: 20px;
	color: #AAA;
	outline: 0;
	cursor: pointer;
	background: none;
	box-shadow: none;
	border: 0;
	padding: 0;
}
.settings-table .banner .remove-banner {
	color: #FFF;
	position: absolute;
	top: 5px;
	right: 5px;
	background-color: #F44336;
	cursor: pointer;
}
.settings-table .banner .move-banner {
	color: #555;
	position: absolute;
	top: 5px;
	left: 5px;
	cursor: -webkit-grab;
}
.settings-table .banner button .thumb {
	height: 150px;
	width: 150px;
	background-size: auto 150px;
	background-position: center;
	background-color: #DDD;
	background-repeat: no-repeat;
}
</style>