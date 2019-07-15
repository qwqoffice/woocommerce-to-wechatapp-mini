<?php
/**
 * REST API Products controller
 *
 * Handles requests to the /products endpoint.
 *
 * @author   WooThemes
 * @category API
 * @package  WooCommerce/API
 * @since    2.6.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

include_once( WP_W2W_PATH . 'vendor/html2wxml/class.ToWXML.php' );

/**
 * REST API Products controller class.
 *
 * @package WooCommerce/API
 * @extends WC_REST_Posts_Controller
 */
class W2W_REST_Products_Controller extends W2W_REST_Posts_Controller {

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
	protected $rest_base = 'products';

	/**
	 * Post type.
	 *
	 * @var string
	 */
	protected $post_type = 'product';
	
	/**
	 * Initialize product actions.
	 */
	public function __construct() {
		add_filter( "woocommerce_rest_{$this->post_type}_query", array( $this, 'query_args' ), 10, 2 );
		add_action( "woocommerce_rest_insert_{$this->post_type}", array( $this, 'clear_transients' ) );
	}

	/**
	 * Register the routes for products.
	 */
	public function register_routes() {
		register_rest_route( $this->namespace, '/' . $this->rest_base, array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_items' ),
				'permission_callback' => array( $this, 'get_items_permissions_check' ),
				'args'                => $this->get_collection_params(),
			),
			'schema' => array( $this, 'get_public_item_schema' ),
		) );
		
		register_rest_route( $this->namespace, '/' . $this->rest_base. '/search', array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'search_products' ),
				'permission_callback' => array( $this, 'get_items_permissions_check' ),
				'args'                => $this->get_collection_params(),
			),
			'schema' => array( $this, 'get_public_item_schema' ),
		) );

		register_rest_route( $this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)', array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_item' ),
				'permission_callback' => array( $this, 'get_items_permissions_check' ),
				'args'                => array(
					'context' => $this->get_context_param( array( 'default' => 'view' ) ),
				),
			),
			'schema' => array( $this, 'get_public_item_schema' ),
		) );
		
		register_rest_route( $this->namespace, '/' . $this->rest_base . '/qrcode', array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_qrcode_url' ),
				'permission_callback' => array( $this, 'get_items_permissions_check' ),
				'args'                => array(
					'context' => $this->get_context_param( array( 'default' => 'view' ) ),
					'id' => array(
						'required' => true
					),
				),
			),
			'schema' => array( $this, 'get_public_item_schema' ),
		) );
	}
	
	// 获取产品小程序码
	public function get_qrcode_url( $request ) {
		
		if( ! is_user_logged_in() ) {
			return new WP_Error( 'w2w_need_login', '请登录后继续操作', array( 'status' => rest_authorization_required_code() ) );
		}
		
		$id = intval( $request['id'] );
		
		$upload_dir = wp_upload_dir();
		$w2w_qrcode_dir = $upload_dir['basedir'] . '/w2w_qrcode/';
		if( ! is_dir( $w2w_qrcode_dir ) ) wp_mkdir_p( $w2w_qrcode_dir );
		
		$qrcode_filename = 'product-' . $id . '.jpg';
		$qrcode_file = $w2w_qrcode_dir . $qrcode_filename;
		
		if( ! file_exists( $qrcode_file ) ) {
			
			$path = 'pages/product-detail/product-detail?id=' . $id;
			$qrcode_image = W2W()->wxapi->get_qrcode( $path );
			file_put_contents( $qrcode_file, $qrcode_image );
		}
		
		return $upload_dir['baseurl'] . '/w2w_qrcode/' . $qrcode_filename;
	}

	/**
	 * Check whether a given request has permission to read products.
	 *
	 * @param  WP_REST_Request $request Full details about the request.
	 * @return WP_Error|boolean
	 */
	public function get_items_permissions_check( $request ) {
		return true;
	}
	
	/**
	 * Get post types.
	 *
	 * @return array
	 */
	protected function get_post_types() {
		return array( 'product', 'product_variation' );
	}

	/**
	 * Query args.
	 *
	 * @param array $args
	 * @param WP_REST_Request $request
	 * @return array
	 */
	public function query_args( $args, $request ) {
		// Set post_status.
		$args['post_status'] = $request['status'];

		// Taxonomy query to filter products by type, category,
		// tag, shipping class, and attribute.
		$tax_query = array();
		$meta_query = array();

		// Map between taxonomy name and arg's key.
		$taxonomies = array(
			'product_cat'            => 'category',
			'product_tag'            => 'tag',
			'product_shipping_class' => 'shipping_class',
		);

		// Set tax_query for each passed arg.
		foreach ( $taxonomies as $taxonomy => $key ) {
			if ( ! empty( $request[ $key ] ) ) {
				$terms = explode( ',', $request[ $key ] );

				$tax_query[] = array(
					'taxonomy' => $taxonomy,
					'field'    => 'term_id',
					'terms'    => $terms,
				);
			}
		}

		// Filter product type by slug.
		if ( ! empty( $request['type'] ) ) {
			$terms = explode( ',', $request['type'] );

			$tax_query[] = array(
				'taxonomy' => 'product_type',
				'field'    => 'slug',
				'terms'    => $terms,
			);
		}
		
		/*
		if ( ! empty( $request['attribute'] ) && ! empty( $request['attribute_term'] ) ) {
			
			if ( in_array( $request['attribute'], wc_get_attribute_taxonomy_names(), true ) ) {
				
				$terms = explode( ',', $request['attribute_term'] );
				
				$tax_query[] = array(
					'taxonomy' => $request['attribute'],
					'field'    => 'term_id',
					'terms'    => $terms,
					'operator' => 'AND'
				);
			}
		}*/
		
		// Filter featured.
		if ( isset( $request['featured'] ) ) {
			$tax_query[] = array(
				'taxonomy' => 'product_visibility',
				'field'    => 'name',
				'terms'    => 'featured',
				'operator' => true == $request['featured'] ? 'IN' : 'NOT IN',
			);
		}

		if ( ! empty( $tax_query ) ) {
			$args['tax_query'] = $tax_query;
		}

		// Filter product in stock or out of stock.
		$hide_out_of_stock_items = 'yes' === get_option( 'woocommerce_hide_out_of_stock_items' );
		if ( isset( $request['in_stock'] ) ) {
			$meta_query[] = array(
				'key'   => '_stock_status',
				'value' => true == $request['in_stock'] ? 'instock' : 'outofstock',
			);
		}
		else {
			if( $hide_out_of_stock_items ) {
				$meta_query[] = array(
					'key'   => '_stock_status',
					'value' => 'instock',
				);
			}
		}

		// Filter by sku.
		if ( ! empty( $request['sku'] ) ) {
			$skus = explode( ',', $request['sku'] );
			// Include the current string as a SKU too.
			if ( 1 < count( $skus ) ) {
				$skus[] = $request['sku'];
			}

			$meta_query[] = array(
				'key'     => '_sku',
				'value'   => $skus,
				'compare' => 'IN',
			);
		}
		
		if ( ! empty( $meta_query ) ) {
			$args['meta_query'] = $meta_query;
		}
		
		// Filter by on sale products.
		if ( isset( $request['on_sale'] ) ) {
			$on_sale_key = $request['on_sale'] ? 'post__in' : 'post__not_in';
			$on_sale_ids = wc_get_product_ids_on_sale();

			// Use 0 when there's no on sale products to avoid return all products.
			$on_sale_ids = empty( $on_sale_ids ) ? array( 0 ) : $on_sale_ids;

			$args[ $on_sale_key ] += $on_sale_ids;
		}
		
		// Force the post_type argument, since it's not a user input variable.
		if ( ! empty( $request['sku'] ) ) {
			$args['post_type'] = array( 'product', 'product_variation' );
		} else {
			$args['post_type'] = $this->post_type;
		}

		return $args;
	}

	/**
	 * Get the downloads for a product or product variation.
	 *
	 * @param WC_Product|WC_Product_Variation $product
	 * @return array
	 */
	protected function get_downloads( $product ) {
		$downloads = array();

		if ( $product->is_downloadable() ) {
			foreach ( $product->get_files() as $file_id => $file ) {
				$downloads[] = array(
					'id'   => $file_id, // MD5 hash.
					'name' => $file['name'],
					'file' => $file['file'],
				);
			}
		}

		return $downloads;
	}

	/**
	 * Get taxonomy terms.
	 *
	 * @param WC_Product $product
	 * @param string $taxonomy
	 * @return array
	 */
	protected function get_taxonomy_terms( $product, $taxonomy = 'cat' ) {
		$terms = array();

		foreach ( wp_get_post_terms( $product->id, 'product_' . $taxonomy ) as $term ) {
			$terms[] = array(
				'id'   => $term->term_id,
				'name' => $term->name,
				'slug' => $term->slug,
			);
		}

		return $terms;
	}

	/**
	 * Get the images for a product or product variation.
	 *
	 * @param WC_Product|WC_Product_Variation $product
	 * @return array
	 */
	protected function get_images( $product ) {
		$images = array();
		$attachment_ids = array();

		if ( $product->is_type( 'variation' ) ) {
			if ( has_post_thumbnail( $product->get_variation_id() ) ) {
				// Add variation image if set.
				$attachment_ids[] = get_post_thumbnail_id( $product->get_variation_id() );
			} elseif ( has_post_thumbnail( $product->id ) ) {
				// Otherwise use the parent product featured image if set.
				$attachment_ids[] = get_post_thumbnail_id( $product->id );
			}
		} else {
			// Add featured image.
			if ( has_post_thumbnail( $product->id ) ) {
				$attachment_ids[] = get_post_thumbnail_id( $product->id );
			}
			// Add gallery images.
			$attachment_ids = array_merge( $attachment_ids, $product->get_gallery_attachment_ids() );
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

	/**
	 * Get attribute taxonomy label.
	 *
	 * @param  string $name
	 * @return string
	 */
	protected function get_attribute_taxonomy_label( $name ) {
		$tax    = get_taxonomy( $name );
		$labels = get_taxonomy_labels( $tax );

		return $labels->singular_name;
	}

	/**
	 * Get default attributes.
	 *
	 * @param WC_Product $product
	 * @return array
	 */
	protected function get_default_attributes( $product ) {
		$default = array();

		if ( $product->is_type( 'variable' ) ) {
			foreach ( array_filter( (array) get_post_meta( $product->id, '_default_attributes', true ), 'strlen' ) as $key => $value ) {
				if ( 0 === strpos( $key, 'pa_' ) ) {
					$default[ 'attribute_' . $key ] = array(
						'id'			=> wc_attribute_taxonomy_id_by_name( $key ),
						'name'			=> $this->get_attribute_taxonomy_label( $key ),
						'is_taxonomy'	=> true,
						'option'		=> $value,
					);
				} else {
					$default[ 'attribute_' . $key ] = array(
						'id'			=> 0,
						'name'			=> str_replace( 'pa_', '', $key ),
						'is_taxonomy'	=> true,
						'option'		=> $value,
					);
				}
			}
		}

		return $default;
	}

	/**
	 * Get attribute options.
	 *
	 * @param int $product_id
	 * @param array $attribute
	 * @return array
	 */
	protected function get_attribute_options( $product_id, $attribute ) {
		if ( isset( $attribute['is_taxonomy'] ) && $attribute['is_taxonomy'] ) {
			return wc_get_product_terms( $product_id, $attribute['name'], array( 'fields' => 'all' ) );
		} elseif ( isset( $attribute['value'] ) ) {
			$options = array();
			foreach( explode( '|', $attribute['value'] ) as $value ) {
				$options[] = array(
					'name' => trim( $value ),
					'slug' => trim( $value )
				);
			}
			return $options;
		}

		return array();
	}

	/**
	 * Get the attributes for a product or product variation.
	 *
	 * @param WC_Product|WC_Product_Variation $product
	 * @return array
	 */
	protected function get_attributes( $product ) {
		$attributes = array();

		if ( $product->is_type( 'variation' ) ) {
			// Variation attributes.
			foreach ( $product->get_variation_attributes() as $attribute_name => $attribute ) {
				$name = str_replace( 'attribute_', '', $attribute_name );

				// Taxonomy-based attributes are prefixed with `pa_`, otherwise simply `attribute_`.
				if ( 0 === strpos( $attribute_name, 'attribute_pa_' ) ) {
					$attributes[ $attribute_name ] = array(
						'id'     => wc_attribute_taxonomy_id_by_name( $name ),
						'name'   => $this->get_attribute_taxonomy_label( $name ),
						'option' => $attribute,
					);
				} else {
					$attributes[ $attribute_name ] = array(
						'id'     => 0,
						'name'   => str_replace( 'pa_', '', $name ),
						'option' => $attribute,
					);
				}
			}
		} else {
			foreach ( $product->get_attributes() as $slug => $attribute ) {
				if ( $attribute['is_taxonomy'] ) {
					$attributes[ 'attribute_' . $slug ] = array(
						'id'			=> wc_attribute_taxonomy_id_by_name( $attribute['name'] ),
						'name'			=> $this->get_attribute_taxonomy_label( $attribute['name'] ),
						'slug'			=> 'attribute_' . $slug,
						'is_taxonomy'	=> (bool) $attribute['is_taxonomy'],
						'position'		=> (int) $attribute['position'],
						'visible'		=> (bool) $attribute['is_visible'],
						'variation'		=> (bool) $attribute['is_variation'],
						'options'		=> $this->get_attribute_options( $product->id, $attribute ),
					);
				} else {
					$attributes[ 'attribute_' . $slug ] = array(
						'id'			=> 0,
						'name'			=> str_replace( 'pa_', '', $attribute['name'] ),
						'slug'			=> 'attribute_' . $slug,
						'is_taxonomy'	=> (bool) $attribute['is_taxonomy'],
						'position'		=> (int) $attribute['position'],
						'visible'		=> (bool) $attribute['is_visible'],
						'variation'		=> (bool) $attribute['is_variation'],
						'options'		=> $this->get_attribute_options( $product->id, $attribute ),
					);
				}
			}
		}

		return $attributes;
	}

	/**
	 * Get product menu order.
	 *
	 * @param WC_Product $product
	 * @return int
	 */
	protected function get_product_menu_order( $product ) {
		$menu_order = $product->get_post_data()->menu_order;

		if ( $product->is_type( 'variation' ) ) {
			$variation  = get_post( $product->get_variation_id() );
			$menu_order = $variation->menu_order;
		}

		return $menu_order;
	}

	/**
	 * Get product data.
	 *
	 * @param WC_Product $product
	 * @return array
	 */
	protected function get_product_data( $product ) {
		
		$settings = get_option( 'w2w-settings' );
		$related_products_quantity = ! empty( $settings['related_products_quantity'] ) ? absint( $settings['related_products_quantity'] ) : 4;
		$towxml = new ToWXML();
		
		$data = array(
			'id'                    => (int) $product->is_type( 'variation' ) ? $product->get_variation_id() : $product->id,
			'name'                  => $product->get_title(),
			'slug'                  => $product->get_post_data()->post_name,
			//'permalink'             => $product->get_permalink(),
			'date_created'          => wc_rest_prepare_date_response( $product->get_post_data()->post_date_gmt ),
			'date_modified'         => wc_rest_prepare_date_response( $product->get_post_data()->post_modified_gmt ),
			'type'                  => $product->product_type,
			'status'                => $product->get_post_data()->post_status,
			'featured'              => $product->is_featured(),
			'catalog_visibility'    => $product->visibility,
			//'description'           => wpautop( do_shortcode( $product->get_post_data()->post_content ) ),
			'short_description_html'     => apply_filters( 'woocommerce_short_description', $product->get_post_data()->post_excerpt ),
			'description'           => $towxml->towxml( wpautop( do_shortcode( $product->get_post_data()->post_content ) ), array( 'encode' => false ) ),
			'short_description'     => $towxml->towxml( apply_filters( 'woocommerce_short_description', $product->get_post_data()->post_excerpt ), array( 'encode' => false ) ),
			'sku'                   => $product->get_sku(),
			'price'                 => $product->get_price(),
			'regular_price'         => $product->get_regular_price(),
			'sale_price'            => $product->get_sale_price() ? $product->get_sale_price() : '',
			'date_on_sale_from'     => $product->sale_price_dates_from ? $product->get_date_on_sale_from()->date_i18n('Y-m-d H:i:s') : '',
			'date_on_sale_to'       => $product->sale_price_dates_to ? $product->get_date_on_sale_to()->date_i18n('Y-m-d H:i:s') : '',
			//'price_html'            => $product->get_price_html(),
			'on_sale'               => $product->is_on_sale(),
			'purchasable'           => $product->is_purchasable(),
			'total_sales'           => (int) get_post_meta( $product->id, 'total_sales', true ),
			'virtual'               => $product->is_virtual(),
			'downloadable'          => $product->is_downloadable(),
			'downloads'             => $this->get_downloads( $product ),
			'download_limit'        => '' !== $product->download_limit ? (int) $product->download_limit : -1,
			'download_expiry'       => '' !== $product->download_expiry ? (int) $product->download_expiry : -1,
			'download_type'         => $product->download_type ? $product->download_type : 'standard',
			'external_url'          => $product->is_type( 'external' ) ? $product->get_product_url() : '',
			'button_text'           => $product->is_type( 'external' ) ? $product->get_button_text() : '',
			'tax_status'            => $product->get_tax_status(),
			'tax_class'             => $product->get_tax_class(),
			'manage_stock'          => $product->managing_stock(),
			'stock_quantity'        => $product->get_stock_quantity(),
			'in_stock'              => $product->is_in_stock(),
			'backorders'            => $product->backorders,
			'backorders_allowed'    => $product->backorders_allowed(),
			'backordered'           => $product->is_on_backorder(),
			'sold_individually'     => $product->is_sold_individually(),
			'weight'                => $product->get_weight(),
			'dimensions'            => array(
				'length' => $product->get_length(),
				'width'  => $product->get_width(),
				'height' => $product->get_height(),
			),
			'shipping_required'     => $product->needs_shipping(),
			'shipping_taxable'      => $product->is_shipping_taxable(),
			'shipping_class'        => $product->get_shipping_class(),
			'shipping_class_id'     => (int) $product->get_shipping_class_id(),
			'reviews_allowed'       => ( 'open' === $product->get_post_data()->comment_status ),
			'average_rating'        => wc_format_decimal( $product->get_average_rating(), 2 ),
			'rating_count'          => (int) $product->get_rating_count(),
			'related_ids'           => array_map( 'absint', array_values( $product->get_related( $related_products_quantity ) ) ),
			'upsell_ids'            => array_map( 'absint', $product->get_upsells() ),
			'cross_sell_ids'        => array_map( 'absint', $product->get_cross_sells() ),
			'parent_id'             => $product->is_type( 'variation' ) ? $product->parent->id : $product->get_post_data()->post_parent,
			'purchase_note'         => wpautop( do_shortcode( wp_kses_post( $product->purchase_note ) ) ),
			'categories'            => $this->get_taxonomy_terms( $product ),
			'tags'                  => $this->get_taxonomy_terms( $product, 'tag' ),
			'images'                => $this->get_images( $product ),
			'attributes'            => $this->get_attributes( $product ),
			'default_attributes'    => $this->get_default_attributes( $product ),
			'variations'            => array(),
			'grouped_products'      => array(),
			'menu_order'            => $this->get_product_menu_order( $product )
		);

		return $data;
	}

	/**
	 * Get an individual variation's data.
	 *
	 * @param WC_Product $product
	 * @return array
	 */
	protected function get_variation_data( $product ) {
		$variations = array();

		foreach ( $product->get_children() as $child_id ) {
			$variation = $product->get_child( $child_id );
			if ( ! $variation->exists() ) {
				continue;
			}

			$post_data = get_post( $variation->get_variation_id() );

			$variations[] = array(
				'id'                 => $variation->get_variation_id(),
				'description'        => version_compare( WC_VERSION, '3.0', '<' ) ? $variation->get_variation_description() : $variation->get_description(),
				'date_created'       => wc_rest_prepare_date_response( $post_data->post_date_gmt ),
				'date_modified'      => wc_rest_prepare_date_response( $post_data->post_modified_gmt ),
				'permalink'          => $variation->get_permalink(),
				'sku'                => $variation->get_sku(),
				'price'              => $variation->get_price(),
				'regular_price'      => $variation->get_regular_price(),
				'sale_price'         => $variation->get_sale_price(),
				'date_on_sale_from'  => $variation->get_date_on_sale_from() ? $variation->get_date_on_sale_from()->date_i18n('Y-m-d H:i:s') : '',
				'date_on_sale_to'    => $variation->get_date_on_sale_to() ? $variation->get_date_on_sale_to()->date_i18n('Y-m-d H:i:s') : '',
				'on_sale'            => $variation->is_on_sale(),
				'purchasable'        => $variation->is_purchasable(),
				'visible'            => $variation->is_visible(),
				'virtual'            => $variation->is_virtual(),
				'downloadable'       => $variation->is_downloadable(),
				'downloads'          => $this->get_downloads( $variation ),
				'download_limit'     => '' !== $variation->download_limit ? (int) $variation->download_limit : -1,
				'download_expiry'    => '' !== $variation->download_expiry ? (int) $variation->download_expiry : -1,
				'tax_status'         => $variation->get_tax_status(),
				'tax_class'          => $variation->get_tax_class(),
				'manage_stock'       => $variation->managing_stock(),
				'stock_quantity'     => $variation->get_stock_quantity(),
				'in_stock'           => $variation->is_in_stock(),
				'backorders'         => $variation->backorders,
				'backorders_allowed' => $variation->backorders_allowed(),
				'backordered'        => $variation->is_on_backorder(),
				'weight'             => $variation->get_weight(),
				'dimensions'         => array(
					'length' => $variation->get_length(),
					'width'  => $variation->get_width(),
					'height' => $variation->get_height(),
				),
				'shipping_class'     => $variation->get_shipping_class(),
				'shipping_class_id'  => $variation->get_shipping_class_id(),
				'image'              => $this->get_images( $variation ),
				'attributes'         => $this->get_attributes( $variation ),
			);
		}

		return $variations;
	}

	/**
	 * Prepare a single product output for response.
	 *
	 * @param WP_Post $post Post object.
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response $data
	 */
	public function prepare_item_for_response( $post, $request ) {
		$product = wc_get_product( $post );
		$data    = $this->get_product_data( $product );

		// Add variations to variable products.
		if ( $product->is_type( 'variable' ) && $product->has_child() ) {
			$data['variations'] = $this->get_variation_data( $product );
			
			$prices = $product->get_variation_prices( true );
			$data['min_price']			= floatval( current( $prices['price'] ) );
			$data['max_price']			= floatval( end( $prices['price'] ) );
			$data['min_regular_price']	= floatval( current( $prices['regular_price'] ) );
			$data['max_regular_price']	= floatval( end( $prices['regular_price'] ) );
		}

		// Add grouped products data.
		if ( $product->is_type( 'grouped' ) && $product->has_child() ) {
			$data['grouped_products'] = $product->get_children();
		}

		$context = ! empty( $request['context'] ) ? $request['context'] : 'view';
		$data    = $this->add_additional_fields_to_object( $data, $request );
		$data    = $this->filter_response_by_context( $data, $context );

		// Wrap the data in a response object.
		$response = rest_ensure_response( $data );

		$response->add_links( $this->prepare_links( $product ) );

		/**
		 * Filter the data for a response.
		 *
		 * The dynamic portion of the hook name, $this->post_type, refers to post_type of the post being
		 * prepared for the response.
		 *
		 * @param WP_REST_Response   $response   The response object.
		 * @param WP_Post            $post       Post object.
		 * @param WP_REST_Request    $request    Request object.
		 */
		return apply_filters( "woocommerce_rest_prepare_{$this->post_type}", $response, $post, $request );
	}

	/**
	 * Prepare links for the request.
	 *
	 * @param WC_Product $product Product object.
	 * @return array Links for the given product.
	 */
	protected function prepare_links( $product ) {
		$links = array(
			'self' => array(
				'href' => rest_url( sprintf( '/%s/%s/%d', $this->namespace, $this->rest_base, $product->id ) ),
			),
			'collection' => array(
				'href' => rest_url( sprintf( '/%s/%s', $this->namespace, $this->rest_base ) ),
			),
		);

		if ( $product->is_type( 'variation' ) && $product->parent ) {
			$links['up'] = array(
				'href' => rest_url( sprintf( '/%s/products/%d', $this->namespace, $product->parent->id ) ),
			);
		} elseif ( $product->is_type( 'simple' ) && ! empty( $product->post->post_parent ) ) {
			$links['up'] = array(
				'href' => rest_url( sprintf( '/%s/products/%d', $this->namespace, $product->post->post_parent ) ),
			);
		}

		return $links;
	}


	/**
	 * Search for products by given term.
	 *
	 * @param  WP_REST_Request $request Full details about the request.
	 */
	public function search_products( $request ) {
		global $wpdb;

		if ( empty( $term ) ) {
			$term = wc_clean( stripslashes( $_GET['term'] ) );
		} else {
			$term = wc_clean( $term );
		}

		if ( empty( $term ) ) {
			die();
		}

		$limit = isset( $_GET['limit'] ) ? $_GET['limit'] : 10;
		$page = isset( $_GET['page'] ) ? $_GET['page'] : 1;
		$offset = $limit * ($page - 1);
		
		$post_types = array('product');

		$like_term = '%' . $wpdb->esc_like( $term ) . '%';

		if ( is_numeric( $term ) ) {
			$query = $wpdb->prepare( "
				SELECT DISTINCT(ID) FROM {$wpdb->posts} posts LEFT JOIN {$wpdb->postmeta} postmeta ON posts.ID = postmeta.post_id
				WHERE posts.post_status = 'publish'
				AND (
					posts.post_parent = %s
					OR posts.ID = %s
					OR posts.post_title LIKE %s
					OR (
						postmeta.meta_key = '_sku' AND postmeta.meta_value LIKE %s
					)
				)
			", $term, $term, $term, $like_term );
		} else {
			$query = $wpdb->prepare( "
				SELECT DISTINCT(ID) FROM {$wpdb->posts} posts LEFT JOIN {$wpdb->postmeta} postmeta ON posts.ID = postmeta.post_id
				WHERE posts.post_status = 'publish'
				AND (
					posts.post_title LIKE %s
					or posts.post_content LIKE %s
					OR (
						postmeta.meta_key = '_sku' AND postmeta.meta_value LIKE %s
					)
				)
			", $like_term, $like_term, $like_term );
		}

		$query .= " AND posts.post_type IN ('" . implode( "','", array_map( 'esc_sql', $post_types ) ) . "')";

		if ( ! empty( $_GET['exclude'] ) ) {
			$query .= " AND posts.ID NOT IN (" . implode( ',', array_map( 'intval', explode( ',', $_GET['exclude'] ) ) ) . ")";
		}

		if ( ! empty( $_GET['include'] ) ) {
			$query .= " AND posts.ID IN (" . implode( ',', array_map( 'intval', explode( ',', $_GET['include'] ) ) ) . ")";
		}

		$query .= " LIMIT " . intval( $offset ) . ", ". intval( $limit );

		$posts          = array_unique( $wpdb->get_col( $query ) );
		$found_products = array();

		if ( ! empty( $posts ) ) {
			foreach ( $posts as $post ) {
				$product = wc_get_product( $post );
				$data    = $this->get_product_data( $product );
				
				// Add variations to variable products.
				if ( $product->is_type( 'variable' ) && $product->has_child() ) {
					$data['variations'] = $this->get_variation_data( $product );
					
					$prices = $product->get_variation_prices( true );
					$data['min_price']			= floatval( current( $prices['price'] ) );
					$data['max_price']			= floatval( end( $prices['price'] ) );
					$data['min_regular_price']	= floatval( current( $prices['regular_price'] ) );
					$data['max_regular_price']	= floatval( end( $prices['regular_price'] ) );
				}

				// Add grouped products data.
				if ( $product->is_type( 'grouped' ) && $product->has_child() ) {
					$data['grouped_products'] = $product->get_children();
				}

				if ( ! $product || ( $product->is_type( 'variation' ) && empty( $product->parent ) ) ) {
					continue;
				}

				$found_products[] = $data;
			}
		}
		
		return $found_products;
	}
	
	/**
	 * Clear cache/transients.
	 *
	 * @param WP_Post $post Post data.
	 */
	public function clear_transients( $post ) {
		wc_delete_product_transients( $post->ID );
	}


	/**
	 * Get the Product's schema, conforming to JSON Schema.
	 *
	 * @return array
	 */
	public function get_item_schema() {
		$weight_unit    = get_option( 'woocommerce_weight_unit' );
		$dimension_unit = get_option( 'woocommerce_dimension_unit' );
		$schema         = array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => $this->post_type,
			'type'       => 'object',
			'properties' => array(
				'id' => array(
					'description' => __( 'Unique identifier for the resource.', 'woocommerce' ),
					'type'        => 'integer',
					'context'     => array( 'view', 'edit' ),
					'readonly'    => true,
				),
				'name' => array(
					'description' => __( 'Product name.', 'woocommerce' ),
					'type'        => 'string',
					'context'     => array( 'view', 'edit' ),
				),
				'slug' => array(
					'description' => __( 'Product slug.', 'woocommerce' ),
					'type'        => 'string',
					'context'     => array( 'view', 'edit' ),
				),
				'permalink' => array(
					'description' => __( 'Product URL.', 'woocommerce' ),
					'type'        => 'string',
					'format'      => 'uri',
					'context'     => array( 'view', 'edit' ),
					'readonly'    => true,
				),
				'date_created' => array(
					'description' => __( "The date the product was created, in the site's timezone.", 'woocommerce' ),
					'type'        => 'date-time',
					'context'     => array( 'view', 'edit' ),
					'readonly'    => true,
				),
				'date_modified' => array(
					'description' => __( "The date the product was last modified, in the site's timezone.", 'woocommerce' ),
					'type'        => 'date-time',
					'context'     => array( 'view', 'edit' ),
					'readonly'    => true,
				),
				'type' => array(
					'description' => __( 'Product type.', 'woocommerce' ),
					'type'        => 'string',
					'default'     => 'simple',
					'enum'        => array_keys( wc_get_product_types() ),
					'context'     => array( 'view', 'edit' ),
				),
				'status' => array(
					'description' => __( 'Product status (post status).', 'woocommerce' ),
					'type'        => 'string',
					'default'     => 'publish',
					'enum'        => array_keys( get_post_statuses() ),
					'context'     => array( 'view', 'edit' ),
				),
				'featured' => array(
					'description' => __( 'Featured product.', 'woocommerce' ),
					'type'        => 'boolean',
					'default'     => false,
					'context'     => array( 'view', 'edit' ),
				),
				'catalog_visibility' => array(
					'description' => __( 'Catalog visibility.', 'woocommerce' ),
					'type'        => 'string',
					'default'     => 'visible',
					'enum'        => array( 'visible', 'catalog', 'search', 'hidden' ),
					'context'     => array( 'view', 'edit' ),
				),
				'description' => array(
					'description' => __( 'Product description.', 'woocommerce' ),
					'type'        => 'string',
					'context'     => array( 'view', 'edit' ),
				),
				'short_description' => array(
					'description' => __( 'Product short description.', 'woocommerce' ),
					'type'        => 'string',
					'context'     => array( 'view', 'edit' ),
				),
				'sku' => array(
					'description' => __( 'Unique identifier.', 'woocommerce' ),
					'type'        => 'string',
					'context'     => array( 'view', 'edit' ),
				),
				'price' => array(
					'description' => __( 'Current product price.', 'woocommerce' ),
					'type'        => 'string',
					'context'     => array( 'view', 'edit' ),
					'readonly'    => true,
				),
				'regular_price' => array(
					'description' => __( 'Product regular price.', 'woocommerce' ),
					'type'        => 'string',
					'context'     => array( 'view', 'edit' ),
				),
				'sale_price' => array(
					'description' => __( 'Product sale price.', 'woocommerce' ),
					'type'        => 'string',
					'context'     => array( 'view', 'edit' ),
				),
				'date_on_sale_from' => array(
					'description' => __( 'Start date of sale price.', 'woocommerce' ),
					'type'        => 'string',
					'context'     => array( 'view', 'edit' ),
				),
				'date_on_sale_to' => array(
					'description' => __( 'End data of sale price.', 'woocommerce' ),
					'type'        => 'string',
					'context'     => array( 'view', 'edit' ),
				),
				'price_html' => array(
					'description' => __( 'Price formatted in HTML.', 'woocommerce' ),
					'type'        => 'string',
					'context'     => array( 'view', 'edit' ),
					'readonly'    => true,
				),
				'on_sale' => array(
					'description' => __( 'Shows if the product is on sale.', 'woocommerce' ),
					'type'        => 'boolean',
					'context'     => array( 'view', 'edit' ),
					'readonly'    => true,
				),
				'purchasable' => array(
					'description' => __( 'Shows if the product can be bought.', 'woocommerce' ),
					'type'        => 'boolean',
					'context'     => array( 'view', 'edit' ),
					'readonly'    => true,
				),
				'total_sales' => array(
					'description' => __( 'Amount of sales.', 'woocommerce' ),
					'type'        => 'integer',
					'context'     => array( 'view', 'edit' ),
					'readonly'    => true,
				),
				'virtual' => array(
					'description' => __( 'If the product is virtual.', 'woocommerce' ),
					'type'        => 'boolean',
					'default'     => false,
					'context'     => array( 'view', 'edit' ),
				),
				'downloadable' => array(
					'description' => __( 'If the product is downloadable.', 'woocommerce' ),
					'type'        => 'boolean',
					'default'     => false,
					'context'     => array( 'view', 'edit' ),
				),
				'downloads' => array(
					'description' => __( 'List of downloadable files.', 'woocommerce' ),
					'type'        => 'array',
					'context'     => array( 'view', 'edit' ),
					'properties'  => array(
						'id' => array(
							'description' => __( 'File MD5 hash.', 'woocommerce' ),
							'type'        => 'string',
							'context'     => array( 'view', 'edit' ),
							'readonly'    => true,
						),
						'name' => array(
							'description' => __( 'File name.', 'woocommerce' ),
							'type'        => 'string',
							'context'     => array( 'view', 'edit' ),
						),
						'file' => array(
							'description' => __( 'File URL.', 'woocommerce' ),
							'type'        => 'string',
							'context'     => array( 'view', 'edit' ),
						),
					),
				),
				'download_limit' => array(
					'description' => __( 'Amount of times the product can be downloaded.', 'woocommerce' ),
					'type'        => 'integer',
					'default'     => -1,
					'context'     => array( 'view', 'edit' ),
				),
				'download_expiry' => array(
					'description' => __( 'Number of days that the customer has up to be able to download the product.', 'woocommerce' ),
					'type'        => 'integer',
					'default'     => -1,
					'context'     => array( 'view', 'edit' ),
				),
				'download_type' => array(
					'description' => __( 'Download type, this controls the schema on the front-end.', 'woocommerce' ),
					'type'        => 'string',
					'default'     => 'standard',
					'enum'        => array( 'standard', 'application', 'music' ),
					'context'     => array( 'view', 'edit' ),
				),
				'external_url' => array(
					'description' => __( 'Product external URL. Only for external products.', 'woocommerce' ),
					'type'        => 'string',
					'format'      => 'uri',
					'context'     => array( 'view', 'edit' ),
				),
				'button_text' => array(
					'description' => __( 'Product external button text. Only for external products.', 'woocommerce' ),
					'type'        => 'string',
					'context'     => array( 'view', 'edit' ),
				),
				'tax_status' => array(
					'description' => __( 'Tax status.', 'woocommerce' ),
					'type'        => 'string',
					'default'     => 'taxable',
					'enum'        => array( 'taxable', 'shipping', 'none' ),
					'context'     => array( 'view', 'edit' ),
				),
				'tax_class' => array(
					'description' => __( 'Tax class.', 'woocommerce' ),
					'type'        => 'string',
					'context'     => array( 'view', 'edit' ),
				),
				'manage_stock' => array(
					'description' => __( 'Stock management at product level.', 'woocommerce' ),
					'type'        => 'boolean',
					'default'     => false,
					'context'     => array( 'view', 'edit' ),
				),
				'stock_quantity' => array(
					'description' => __( 'Stock quantity.', 'woocommerce' ),
					'type'        => 'integer',
					'context'     => array( 'view', 'edit' ),
				),
				'in_stock' => array(
					'description' => __( 'Controls whether or not the product is listed as "in stock" or "out of stock" on the frontend.', 'woocommerce' ),
					'type'        => 'boolean',
					'default'     => true,
					'context'     => array( 'view', 'edit' ),
				),
				'backorders' => array(
					'description' => __( 'If managing stock, this controls if backorders are allowed.', 'woocommerce' ),
					'type'        => 'string',
					'default'     => 'no',
					'enum'        => array( 'no', 'notify', 'yes' ),
					'context'     => array( 'view', 'edit' ),
				),
				'backorders_allowed' => array(
					'description' => __( 'Shows if backorders are allowed.', 'woocommerce' ),
					'type'        => 'boolean',
					'context'     => array( 'view', 'edit' ),
					'readonly'    => true,
				),
				'backordered' => array(
					'description' => __( 'Shows if the product is on backordered.', 'woocommerce' ),
					'type'        => 'boolean',
					'context'     => array( 'view', 'edit' ),
					'readonly'    => true,
				),
				'sold_individually' => array(
					'description' => __( 'Allow one item to be bought in a single order.', 'woocommerce' ),
					'type'        => 'boolean',
					'default'     => false,
					'context'     => array( 'view', 'edit' ),
				),
				'weight' => array(
					'description' => sprintf( __( 'Product weight (%s).', 'woocommerce' ), $weight_unit ),
					'type'        => 'string',
					'context'     => array( 'view', 'edit' ),
				),
				'dimensions' => array(
					'description' => __( 'Product dimensions.', 'woocommerce' ),
					'type'        => 'array',
					'context'     => array( 'view', 'edit' ),
					'properties'  => array(
						'length' => array(
							'description' => sprintf( __( 'Product length (%s).', 'woocommerce' ), $dimension_unit ),
							'type'        => 'string',
							'context'     => array( 'view', 'edit' ),
						),
						'width' => array(
							'description' => sprintf( __( 'Product width (%s).', 'woocommerce' ), $dimension_unit ),
							'type'        => 'string',
							'context'     => array( 'view', 'edit' ),
						),
						'height' => array(
							'description' => sprintf( __( 'Product height (%s).', 'woocommerce' ), $dimension_unit ),
							'type'        => 'string',
							'context'     => array( 'view', 'edit' ),
						),
					),
				),
				'shipping_required' => array(
					'description' => __( 'Shows if the product need to be shipped.', 'woocommerce' ),
					'type'        => 'boolean',
					'context'     => array( 'view', 'edit' ),
					'readonly'    => true,
				),
				'shipping_taxable' => array(
					'description' => __( 'Shows whether or not the product shipping is taxable.', 'woocommerce' ),
					'type'        => 'boolean',
					'context'     => array( 'view', 'edit' ),
					'readonly'    => true,
				),
				'shipping_class' => array(
					'description' => __( 'Shipping class slug.', 'woocommerce' ),
					'type'        => 'string',
					'context'     => array( 'view', 'edit' ),
				),
				'shipping_class_id' => array(
					'description' => __( 'Shipping class ID.', 'woocommerce' ),
					'type'        => 'string',
					'context'     => array( 'view', 'edit' ),
					'readonly'    => true,
				),
				'reviews_allowed' => array(
					'description' => __( 'Allow reviews.', 'woocommerce' ),
					'type'        => 'boolean',
					'default'     => true,
					'context'     => array( 'view', 'edit' ),
				),
				'average_rating' => array(
					'description' => __( 'Reviews average rating.', 'woocommerce' ),
					'type'        => 'string',
					'context'     => array( 'view', 'edit' ),
					'readonly'    => true,
				),
				'rating_count' => array(
					'description' => __( 'Amount of reviews that the product have.', 'woocommerce' ),
					'type'        => 'integer',
					'context'     => array( 'view', 'edit' ),
					'readonly'    => true,
				),
				'related_ids' => array(
					'description' => __( 'List of related products IDs.', 'woocommerce' ),
					'type'        => 'array',
					'context'     => array( 'view', 'edit' ),
					'readonly'    => true,
				),
				'upsell_ids' => array(
					'description' => __( 'List of up-sell products IDs.', 'woocommerce' ),
					'type'        => 'array',
					'context'     => array( 'view', 'edit' ),
				),
				'cross_sell_ids' => array(
					'description' => __( 'List of cross-sell products IDs.', 'woocommerce' ),
					'type'        => 'array',
					'context'     => array( 'view', 'edit' ),
				),
				'parent_id' => array(
					'description' => __( 'Product parent ID.', 'woocommerce' ),
					'type'        => 'integer',
					'context'     => array( 'view', 'edit' ),
				),
				'purchase_note' => array(
					'description' => __( 'Optional note to send the customer after purchase.', 'woocommerce' ),
					'type'        => 'string',
					'context'     => array( 'view', 'edit' ),
				),
				'categories' => array(
					'description' => __( 'List of categories.', 'woocommerce' ),
					'type'        => 'array',
					'context'     => array( 'view', 'edit' ),
					'properties'  => array(
						'id' => array(
							'description' => __( 'Category ID.', 'woocommerce' ),
							'type'        => 'integer',
							'context'     => array( 'view', 'edit' ),
						),
						'name' => array(
							'description' => __( 'Category name.', 'woocommerce' ),
							'type'        => 'string',
							'context'     => array( 'view', 'edit' ),
							'readonly'    => true,
						),
						'slug' => array(
							'description' => __( 'Category slug.', 'woocommerce' ),
							'type'        => 'string',
							'context'     => array( 'view', 'edit' ),
							'readonly'    => true,
						),
					),
				),
				'tags' => array(
					'description' => __( 'List of tags.', 'woocommerce' ),
					'type'        => 'array',
					'context'     => array( 'view', 'edit' ),
					'properties'  => array(
						'id' => array(
							'description' => __( 'Tag ID.', 'woocommerce' ),
							'type'        => 'integer',
							'context'     => array( 'view', 'edit' ),
						),
						'name' => array(
							'description' => __( 'Tag name.', 'woocommerce' ),
							'type'        => 'string',
							'context'     => array( 'view', 'edit' ),
							'readonly'    => true,
						),
						'slug' => array(
							'description' => __( 'Tag slug.', 'woocommerce' ),
							'type'        => 'string',
							'context'     => array( 'view', 'edit' ),
							'readonly'    => true,
						),
					),
				),
				'images' => array(
					'description' => __( 'List of images.', 'woocommerce' ),
					'type'        => 'array',
					'context'     => array( 'view', 'edit' ),
					'properties'  => array(
						'id' => array(
							'description' => __( 'Image ID.', 'woocommerce' ),
							'type'        => 'integer',
							'context'     => array( 'view', 'edit' ),
						),
						'date_created' => array(
							'description' => __( "The date the image was created, in the site's timezone.", 'woocommerce' ),
							'type'        => 'date-time',
							'context'     => array( 'view', 'edit' ),
							'readonly'    => true,
						),
						'date_modified' => array(
							'description' => __( "The date the image was last modified, in the site's timezone.", 'woocommerce' ),
							'type'        => 'date-time',
							'context'     => array( 'view', 'edit' ),
							'readonly'    => true,
						),
						'src' => array(
							'description' => __( 'Image URL.', 'woocommerce' ),
							'type'        => 'string',
							'format'      => 'uri',
							'context'     => array( 'view', 'edit' ),
						),
						'name' => array(
							'description' => __( 'Image name.', 'woocommerce' ),
							'type'        => 'string',
							'context'     => array( 'view', 'edit' ),
						),
						'alt' => array(
							'description' => __( 'Image alternative text.', 'woocommerce' ),
							'type'        => 'string',
							'context'     => array( 'view', 'edit' ),
						),
						'position' => array(
							'description' => __( 'Image position. 0 means that the image is featured.', 'woocommerce' ),
							'type'        => 'integer',
							'context'     => array( 'view', 'edit' ),
						),
					),
				),
				'attributes' => array(
					'description' => __( 'List of attributes.', 'woocommerce' ),
					'type'        => 'array',
					'context'     => array( 'view', 'edit' ),
					'properties'  => array(
						'id' => array(
							'description' => __( 'Attribute ID.', 'woocommerce' ),
							'type'        => 'integer',
							'context'     => array( 'view', 'edit' ),
						),
						'name' => array(
							'description' => __( 'Attribute name.', 'woocommerce' ),
							'type'        => 'string',
							'context'     => array( 'view', 'edit' ),
						),
						'position' => array(
							'description' => __( 'Attribute position.', 'woocommerce' ),
							'type'        => 'integer',
							'context'     => array( 'view', 'edit' ),
						),
						'visible' => array(
							'description' => __( "Define if the attribute is visible on the \"Additional Information\" tab in the product's page.", 'woocommerce' ),
							'type'        => 'boolean',
							'default'     => false,
							'context'     => array( 'view', 'edit' ),
						),
						'variation' => array(
							'description' => __( 'Define if the attribute can be used as variation.', 'woocommerce' ),
							'type'        => 'boolean',
							'default'     => false,
							'context'     => array( 'view', 'edit' ),
						),
						'options' => array(
							'description' => __( 'List of available term names of the attribute.', 'woocommerce' ),
							'type'        => 'array',
							'context'     => array( 'view', 'edit' ),
						),
					),
				),
				'default_attributes' => array(
					'description' => __( 'Defaults variation attributes.', 'woocommerce' ),
					'type'        => 'array',
					'context'     => array( 'view', 'edit' ),
					'properties'  => array(
						'id' => array(
							'description' => __( 'Attribute ID.', 'woocommerce' ),
							'type'        => 'integer',
							'context'     => array( 'view', 'edit' ),
						),
						'name' => array(
							'description' => __( 'Attribute name.', 'woocommerce' ),
							'type'        => 'string',
							'context'     => array( 'view', 'edit' ),
						),
						'option' => array(
							'description' => __( 'Selected attribute term name.', 'woocommerce' ),
							'type'        => 'string',
							'context'     => array( 'view', 'edit' ),
						),
					),
				),
				'variations' => array(
					'description' => __( 'List of variations.', 'woocommerce' ),
					'type'        => 'array',
					'context'     => array( 'view', 'edit' ),
					'properties'  => array(
						'id' => array(
							'description' => __( 'Variation ID.', 'woocommerce' ),
							'type'        => 'integer',
							'context'     => array( 'view', 'edit' ),
							'readonly'    => true,
						),
						'date_created' => array(
							'description' => __( "The date the variation was created, in the site's timezone.", 'woocommerce' ),
							'type'        => 'date-time',
							'context'     => array( 'view', 'edit' ),
							'readonly'    => true,
						),
						'date_modified' => array(
							'description' => __( "The date the variation was last modified, in the site's timezone.", 'woocommerce' ),
							'type'        => 'date-time',
							'context'     => array( 'view', 'edit' ),
							'readonly'    => true,
						),
						'permalink' => array(
							'description' => __( 'Variation URL.', 'woocommerce' ),
							'type'        => 'string',
							'format'      => 'uri',
							'context'     => array( 'view', 'edit' ),
							'readonly'    => true,
						),
						'sku' => array(
							'description' => __( 'Unique identifier.', 'woocommerce' ),
							'type'        => 'string',
							'context'     => array( 'view', 'edit' ),
						),
						'price' => array(
							'description' => __( 'Current variation price.', 'woocommerce' ),
							'type'        => 'string',
							'context'     => array( 'view', 'edit' ),
							'readonly'    => true,
						),
						'regular_price' => array(
							'description' => __( 'Variation regular price.', 'woocommerce' ),
							'type'        => 'string',
							'context'     => array( 'view', 'edit' ),
						),
						'sale_price' => array(
							'description' => __( 'Variation sale price.', 'woocommerce' ),
							'type'        => 'string',
							'context'     => array( 'view', 'edit' ),
						),
						'date_on_sale_from' => array(
							'description' => __( 'Start date of sale price.', 'woocommerce' ),
							'type'        => 'string',
							'context'     => array( 'view', 'edit' ),
						),
						'date_on_sale_to' => array(
							'description' => __( 'End data of sale price.', 'woocommerce' ),
							'type'        => 'string',
							'context'     => array( 'view', 'edit' ),
						),
						'on_sale' => array(
							'description' => __( 'Shows if the variation is on sale.', 'woocommerce' ),
							'type'        => 'boolean',
							'context'     => array( 'view', 'edit' ),
							'readonly'    => true,
						),
						'purchasable' => array(
							'description' => __( 'Shows if the variation can be bought.', 'woocommerce' ),
							'type'        => 'boolean',
							'context'     => array( 'view', 'edit' ),
							'readonly'    => true,
						),
						'visible' => array(
							'description' => __( 'If the variation is visible.', 'woocommerce' ),
							'type'        => 'boolean',
							'context'     => array( 'view', 'edit' )
						),
						'virtual' => array(
							'description' => __( 'If the variation is virtual.', 'woocommerce' ),
							'type'        => 'boolean',
							'default'     => false,
							'context'     => array( 'view', 'edit' ),
						),
						'downloadable' => array(
							'description' => __( 'If the variation is downloadable.', 'woocommerce' ),
							'type'        => 'boolean',
							'default'     => false,
							'context'     => array( 'view', 'edit' ),
						),
						'downloads' => array(
							'description' => __( 'List of downloadable files.', 'woocommerce' ),
							'type'        => 'array',
							'context'     => array( 'view', 'edit' ),
							'properties'  => array(
								'id' => array(
									'description' => __( 'File MD5 hash.', 'woocommerce' ),
									'type'        => 'string',
									'context'     => array( 'view', 'edit' ),
									'readonly'    => true,
								),
								'name' => array(
									'description' => __( 'File name.', 'woocommerce' ),
									'type'        => 'string',
									'context'     => array( 'view', 'edit' ),
								),
								'file' => array(
									'description' => __( 'File URL.', 'woocommerce' ),
									'type'        => 'string',
									'context'     => array( 'view', 'edit' ),
								),
							),
						),
						'download_limit' => array(
							'description' => __( 'Amount of times the variation can be downloaded.', 'woocommerce' ),
							'type'        => 'integer',
							'default'     => null,
							'context'     => array( 'view', 'edit' ),
						),
						'download_expiry' => array(
							'description' => __( 'Number of days that the customer has up to be able to download the variation.', 'woocommerce' ),
							'type'        => 'integer',
							'default'     => null,
							'context'     => array( 'view', 'edit' ),
						),
						'tax_status' => array(
							'description' => __( 'Tax status.', 'woocommerce' ),
							'type'        => 'string',
							'default'     => 'taxable',
							'enum'        => array( 'taxable', 'shipping', 'none' ),
							'context'     => array( 'view', 'edit' ),
						),
						'tax_class' => array(
							'description' => __( 'Tax class.', 'woocommerce' ),
							'type'        => 'string',
							'context'     => array( 'view', 'edit' ),
						),
						'manage_stock' => array(
							'description' => __( 'Stock management at variation level.', 'woocommerce' ),
							'type'        => 'boolean',
							'default'     => false,
							'context'     => array( 'view', 'edit' ),
						),
						'stock_quantity' => array(
							'description' => __( 'Stock quantity.', 'woocommerce' ),
							'type'        => 'integer',
							'context'     => array( 'view', 'edit' ),
						),
						'in_stock' => array(
							'description' => __( 'Controls whether or not the variation is listed as "in stock" or "out of stock" on the frontend.', 'woocommerce' ),
							'type'        => 'boolean',
							'default'     => true,
							'context'     => array( 'view', 'edit' ),
						),
						'backorders' => array(
							'description' => __( 'If managing stock, this controls if backorders are allowed.', 'woocommerce' ),
							'type'        => 'string',
							'default'     => 'no',
							'enum'        => array( 'no', 'notify', 'yes' ),
							'context'     => array( 'view', 'edit' ),
						),
						'backorders_allowed' => array(
							'description' => __( 'Shows if backorders are allowed.', 'woocommerce' ),
							'type'        => 'boolean',
							'context'     => array( 'view', 'edit' ),
							'readonly'    => true,
						),
						'backordered' => array(
							'description' => __( 'Shows if the variation is on backordered.', 'woocommerce' ),
							'type'        => 'boolean',
							'context'     => array( 'view', 'edit' ),
							'readonly'    => true,
						),
						'weight' => array(
							'description' => sprintf( __( 'Variation weight (%s).', 'woocommerce' ), $weight_unit ),
							'type'        => 'string',
							'context'     => array( 'view', 'edit' ),
						),
						'dimensions' => array(
							'description' => __( 'Variation dimensions.', 'woocommerce' ),
							'type'        => 'array',
							'context'     => array( 'view', 'edit' ),
							'properties'  => array(
								'length' => array(
									'description' => sprintf( __( 'Variation length (%s).', 'woocommerce' ), $dimension_unit ),
									'type'        => 'string',
									'context'     => array( 'view', 'edit' ),
								),
								'width' => array(
									'description' => sprintf( __( 'Variation width (%s).', 'woocommerce' ), $dimension_unit ),
									'type'        => 'string',
									'context'     => array( 'view', 'edit' ),
								),
								'height' => array(
									'description' => sprintf( __( 'Variation height (%s).', 'woocommerce' ), $dimension_unit ),
									'type'        => 'string',
									'context'     => array( 'view', 'edit' ),
								),
							),
						),
						'shipping_class' => array(
							'description' => __( 'Shipping class slug.', 'woocommerce' ),
							'type'        => 'string',
							'context'     => array( 'view', 'edit' ),
						),
						'shipping_class_id' => array(
							'description' => __( 'Shipping class ID.', 'woocommerce' ),
							'type'        => 'string',
							'context'     => array( 'view', 'edit' ),
							'readonly'    => true,
						),
						'image' => array(
							'description' => __( 'Variation image data.', 'woocommerce' ),
							'type'        => 'array',
							'context'     => array( 'view', 'edit' ),
							'properties'  => array(
								'id' => array(
									'description' => __( 'Image ID.', 'woocommerce' ),
									'type'        => 'integer',
									'context'     => array( 'view', 'edit' ),
								),
								'date_created' => array(
									'description' => __( "The date the image was created, in the site's timezone.", 'woocommerce' ),
									'type'        => 'date-time',
									'context'     => array( 'view', 'edit' ),
									'readonly'    => true,
								),
								'date_modified' => array(
									'description' => __( "The date the image was last modified, in the site's timezone.", 'woocommerce' ),
									'type'        => 'date-time',
									'context'     => array( 'view', 'edit' ),
									'readonly'    => true,
								),
								'src' => array(
									'description' => __( 'Image URL.', 'woocommerce' ),
									'type'        => 'string',
									'format'      => 'uri',
									'context'     => array( 'view', 'edit' ),
								),
								'name' => array(
									'description' => __( 'Image name.', 'woocommerce' ),
									'type'        => 'string',
									'context'     => array( 'view', 'edit' ),
								),
								'alt' => array(
									'description' => __( 'Image alternative text.', 'woocommerce' ),
									'type'        => 'string',
									'context'     => array( 'view', 'edit' ),
								),
								'position' => array(
									'description' => __( 'Image position. 0 means that the image is featured.', 'woocommerce' ),
									'type'        => 'integer',
									'context'     => array( 'view', 'edit' ),
								),
							),
						),
						'attributes' => array(
							'description' => __( 'List of attributes.', 'woocommerce' ),
							'type'        => 'array',
							'context'     => array( 'view', 'edit' ),
							'properties'  => array(
								'id' => array(
									'description' => __( 'Attribute ID.', 'woocommerce' ),
									'type'        => 'integer',
									'context'     => array( 'view', 'edit' ),
								),
								'name' => array(
									'description' => __( 'Attribute name.', 'woocommerce' ),
									'type'        => 'string',
									'context'     => array( 'view', 'edit' ),
								),
								'option' => array(
									'description' => __( 'Selected attribute term name.', 'woocommerce' ),
									'type'        => 'string',
									'context'     => array( 'view', 'edit' ),
								),
							),
						),
					),
				),
				'grouped_products' => array(
					'description' => __( 'List of grouped products ID.', 'woocommerce' ),
					'type'        => 'array',
					'context'     => array( 'view', 'edit' ),
					'readonly'    => true,
				),
				'menu_order' => array(
					'description' => __( 'Menu order, used to custom sort products.', 'woocommerce' ),
					'type'        => 'integer',
					'context'     => array( 'view', 'edit' ),
				),
			),
		);

		return $this->add_additional_fields_schema( $schema );
	}

	/**
	 * Get the query params for collections of attachments.
	 *
	 * @return array
	 */
	public function get_collection_params() {
		$params = parent::get_collection_params();

		$params['slug'] = array(
			'description'       => __( 'Limit result set to products with a specific slug.', 'woocommerce', 'woocommerce' ),
			'type'              => 'string',
			'validate_callback' => 'rest_validate_request_arg',
		);
		$params['status'] = array(
			'default'           => 'any',
			'description'       => __( 'Limit result set to products assigned a specific status.', 'woocommerce' ),
			'type'              => 'string',
			'enum'              => array_merge( array( 'any' ), array_keys( get_post_statuses() ) ),
			'sanitize_callback' => 'sanitize_key',
			'validate_callback' => 'rest_validate_request_arg',
		);
		$params['type'] = array(
			'description'       => __( 'Limit result set to products assigned a specific type.', 'woocommerce' ),
			'type'              => 'string',
			'enum'              => array_keys( wc_get_product_types() ),
			'sanitize_callback' => 'sanitize_key',
			'validate_callback' => 'rest_validate_request_arg',
		);
		$params['category'] = array(
			'description'       => __( 'Limit result set to products assigned a specific category.', 'woocommerce' ),
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'validate_callback' => 'rest_validate_request_arg',
		);
		$params['tag'] = array(
			'description'       => __( 'Limit result set to products assigned a specific tag.', 'woocommerce' ),
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'validate_callback' => 'rest_validate_request_arg',
		);
		$params['shipping_class'] = array(
			'description'       => __( 'Limit result set to products assigned a specific shipping class.', 'woocommerce' ),
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'validate_callback' => 'rest_validate_request_arg',
		);
		$params['attribute'] = array(
			'description'       => __( 'Limit result set to products with a specific attribute.', 'woocommerce' ),
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'validate_callback' => 'rest_validate_request_arg',
		);
		$params['attribute_term'] = array(
			'description'       => __( 'Limit result set to products with a specific attribute term (required an assigned attribute).', 'woocommerce' ),
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'validate_callback' => 'rest_validate_request_arg',
		);
		$params['sku'] = array(
			'description'       => __( 'Limit result set to products with a specific SKU.', 'woocommerce' ),
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'validate_callback' => 'rest_validate_request_arg',
		);

		return $params;
	}
}
