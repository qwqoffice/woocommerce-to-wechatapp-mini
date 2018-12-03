<?php
/**
 * REST API Cart controller
 *
 * Handles requests to the /cart endpoint.
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
 * REST API Cart controller class.
 *
 * @package WooCommerce/API
 * @extends WC_REST_Posts_Controller
 */
class W2W_REST_Cart_Controller extends W2W_REST_Posts_Controller {

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
	protected $rest_base = 'cart';

	/**
	 * Initialize orders actions.
	 */
	public function __construct() {
		
	}

	/**
	 * Register the routes for orders.
	 */
	public function register_routes() {

		register_rest_route( $this->namespace, '/' . $this->rest_base . '/add', array(
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'add_item_to_cart' ),
				'permission_callback' => array( $this, 'get_items_permissions_check' ),
				'args'                => $this->get_collection_params(),
			)
		) );
		
		register_rest_route( $this->namespace, '/' . $this->rest_base . '/', array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_cart_totals' ),
				'permission_callback' => array( $this, 'get_items_permissions_check' ),
				'args'                => $this->get_collection_params(),
			)
		) );
		
		register_rest_route( $this->namespace, '/' . $this->rest_base . '/update_quantity', array(
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'update_cart_item' ),
				'permission_callback' => array( $this, 'get_items_permissions_check' ),
				'args'                => $this->get_collection_params(),
			)
		) );
		
		register_rest_route( $this->namespace, '/' . $this->rest_base . '/delete', array(
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'remove_cart_item' ),
				'permission_callback' => array( $this, 'get_items_permissions_check' ),
				'args'                => $this->get_collection_params(),
			)
		) );
		
		register_rest_route( $this->namespace, '/' . $this->rest_base . '/address', array(
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'set_address' ),
				'permission_callback' => array( $this, 'get_items_permissions_check' ),
				'args'                => $this->get_collection_params(),
			)
		) );
		
		register_rest_route( $this->namespace, '/' . $this->rest_base . '/shipping', array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_shipping_methods' ),
				'permission_callback' => array( $this, 'get_items_permissions_check' ),
				'args'                => $this->get_collection_params(),
			),
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'save_shipping_method' ),
				'permission_callback' => array( $this, 'get_items_permissions_check' ),
				'args'                => $this->get_collection_params(),
			)
		) );
		
		register_rest_route( $this->namespace, '/' . $this->rest_base . '/payment', array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_payment_methods' ),
				'permission_callback' => array( $this, 'get_items_permissions_check' ),
				'args'                => $this->get_collection_params(),
			),
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'save_payment_method' ),
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
	
	/**
	 * Get product data.
	 *
	 * @param WC_Product $product
	 * @return array
	 */
	protected function get_product_data( $product ) {
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
			//'short_description'     => apply_filters( 'woocommerce_short_description', $product->get_post_data()->post_excerpt ),
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
			'related_ids'           => array_map( 'absint', array_values( $product->get_related() ) ),
			'upsell_ids'            => array_map( 'absint', $product->get_upsells() ),
			'cross_sell_ids'        => array_map( 'absint', $product->get_cross_sells() ),
			'parent_id'             => $product->is_type( 'variation' ) ? $product->parent->id : $product->get_post_data()->post_parent,
			'images'                => $this->get_images( $product ),
			'attributes'            => $this->get_attributes( $product ),
		);

		return $data;
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
						'name'   => $attribute_name,
						'option' => $attribute,
					);
				}
			}
		} else {
			foreach ( $product->get_attributes() as $slug => $attribute ) {
				if ( $attribute['is_taxonomy'] ) {
					$attributes[] = array(
						'id'        => wc_attribute_taxonomy_id_by_name( $attribute['name'] ),
						'name'      => $this->get_attribute_taxonomy_label( $attribute['name'] ),
						'slug'		=> 'attribute_' . $slug,
						'position'  => (int) $attribute['position'],
						'visible'   => (bool) $attribute['is_visible'],
						'variation' => (bool) $attribute['is_variation'],
						'options'   => $this->get_attribute_options( $product->id, $attribute ),
					);
				} else {
					$attributes[] = array(
						'id'        => 0,
						'name'      => str_replace( 'pa_', '', $attribute['name'] ),
						'slug'		=> 'attribute_' . $slug,
						'position'  => (int) $attribute['position'],
						'visible'   => (bool) $attribute['is_visible'],
						'variation' => (bool) $attribute['is_variation'],
						'options'   => $this->get_attribute_options( $product->id, $attribute ),
					);
				}
			}
		}

		return $attributes;
	}
	
	/**
	 * Adds an item to the cart.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_Error|WP_REST_Response
	 */
	public function add_item_to_cart( $request ) {
		
		$product_id        = apply_filters( 'woocommerce_add_to_cart_product_id', absint( $request['product_id'] ) );
		$quantity          = empty( $request['quantity'] ) ? 1 : wc_stock_amount( $request['quantity'] );
		$variation_id	   = $request['variation_id'];
		$variation         = $request['variation'];
	
		WC()->cart->add_to_cart( $product_id, $quantity, $variation_id, $variation);
		do_action( 'woocommerce_ajax_added_to_cart', $product_id );

		return $this->_get_cart_totals( $request, array( 'address', 'coupon', 'shipping' ) );
	}
	
	/**
	 * Update cart item quantity.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_Error|WP_REST_Response
	 */
	public function update_cart_item( $request ) {
		$cart_key        = $request['cart_key'];
		
		if ( ! WC()->cart->is_empty() ) {
			
			foreach ( WC()->cart->get_cart() as $cart_item_key => $values ) {
				
				if( $cart_item_key == $cart_key ) {
					
					$_product = $values['data'];

					// Sanitize.
					$quantity = apply_filters( 'woocommerce_stock_amount_cart_item', wc_stock_amount( preg_replace( '/[^0-9\.]/', '', $request['quantity'] ) ), $cart_item_key );

					if ( '' === $quantity || $quantity === $values['quantity'] ) {
						break;
					}

					// Update cart validation.
					$passed_validation = apply_filters( 'woocommerce_update_cart_validation', true, $cart_item_key, $values, $quantity );

					// is_sold_individually.
					if ( $_product->is_sold_individually() && $quantity > 1 ) {
						/* Translators: %s Product title. */
						wc_add_notice( sprintf( __( 'You can only have 1 %s in your cart.', 'woocommerce' ), $_product->get_name() ), 'error' );
						$passed_validation = false;
					}

					if ( $passed_validation ) {
						WC()->cart->set_quantity( $cart_item_key, $quantity, false );
					}
					
					break;
				}
			}
		}
		
		return $this->_get_cart_totals( $request, array( 'address', 'coupon', 'shipping' ), true );
	}
	
	/**
	 * Removes cart item by cart key.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_Error|WP_REST_Response
	 */
	public function remove_cart_item( $request ) {
		$cart_key        = $request['cart_key'];		
		WC()->cart->remove_cart_item( $cart_key );
		
		return $this->_get_cart_totals( $request, array( 'address', 'coupon', 'shipping' ), true );
	}
	
	
	private function _get_shipping_methods( $request ) {
		
		if ( ! defined( 'WOOCOMMERCE_CART' ) ) {
			define( 'WOOCOMMERCE_CART', true );
		}
		
		//$this->handle_checkout_params( $request, array( 'address', 'coupon' ) );
		//$this->handle_checkout_params( $request, array( 'address' ) );
		
		WC()->cart->calculate_totals();
		
		$packages = WC()->cart->get_shipping_packages();
		WC()->shipping->calculate_shipping($packages);
		$packages = WC()->shipping->packages;
		
		$response = array();
		if( $packages[0] && $packages[0]['rates'] ) {
			
			foreach( $packages[0]['rates'] as $rate ) {
				
				$response['methods'][] = array(
					'id' => $rate->get_id(),
					'method_id' => $rate->get_method_id(),
					'instance_id' => $rate->get_instance_id(),
					'label' => $rate->get_label(),
					'cost' => floatval( $rate->get_cost() ),
					'taxes' => $rate->get_taxes(),
					'shipping_tax' => $rate->get_shipping_tax()
				);
			}
		}
		else{
			
			$response['methods'] = array();
		}
		
		$response['chosen_method'] = isset( WC()->session->chosen_shipping_methods[ 0 ] ) ? WC()->session->chosen_shipping_methods[ 0 ] : '';
		
		return $response;
	}
	
	/**
	 * Get available shipping methods.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_Error|WP_REST_Response
	 */
	public function get_shipping_methods( $request ) {
		
		$this->_set_address( $request );
		
		return array(
			'shipping' => $this->_get_shipping_methods( $request ),
			'payment' => $this->get_payment_methods( $request ),
			'cart' => $this->_get_cart_totals( $request, array(), true )
		);
	}
	
	private function _save_shipping_method( $request ) {
		
		if ( ! defined( 'WOOCOMMERCE_CART' ) ) {
			define( 'WOOCOMMERCE_CART', true );
		}
		
		$chosen_shipping_methods = WC()->session->get( 'chosen_shipping_methods' );

		/*if ( isset( $request['shipping_method'] ) && is_array( $request['shipping_method'] ) ) {
			foreach ( $request['shipping_method'] as $i => $value ) {
				$chosen_shipping_methods[ $i ] = wc_clean( $value );
			}
		}*/
		
		if ( isset( $request['shipping_method'] ) ) {
			$chosen_shipping_methods[0] = wc_clean( $request['shipping_method'] );
		}

		WC()->session->set( 'chosen_shipping_methods', $chosen_shipping_methods );
	}
	
	/**
	 * Save shipping method.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_Error|WP_REST_Response
	 */
	public function save_shipping_method( $request ) {
		
		$this->_save_shipping_method( $request );
		
		return array(
			'shipping' => $this->_get_shipping_methods( $request ),
			'payment' => $this->get_payment_methods( $request ),
			'cart' => $this->_get_cart_totals( $request, array() )
		);
	}
	
	private function _set_address( $request ) {
		
		if ( ! defined( 'WOOCOMMERCE_CART' ) ) {
			define( 'WOOCOMMERCE_CART', true );
		}

		WC()->shipping->reset_shipping();

		$country  = wc_clean( $request['country_id'] );
		$state    = wc_clean( isset( $request['state'] ) ? $request['state'] : '' );
		$postcode = apply_filters( 'woocommerce_shipping_calculator_enable_postcode', true ) ? wc_clean( $request['postcode'] ) : '';
		$city     = apply_filters( 'woocommerce_shipping_calculator_enable_city', false ) ? wc_clean( $request['city'] ) : '';

		if ( $postcode && ! WC_Validation::is_postcode( $postcode, $country ) ) {
			throw new Exception( __( 'Please enter a valid postcode/ZIP.', 'woocommerce' ) );
		} elseif ( $postcode ) {
			$postcode = wc_format_postcode( $postcode, $country );
		}

		if ( $country ) {
			WC()->customer->set_location( $country, $state, $postcode, $city );
			WC()->customer->set_shipping_location( $country, $state, $postcode, $city );
		} else {
			WC()->customer->set_to_base();
			WC()->customer->set_shipping_to_base();
		}

		WC()->customer->calculated_shipping( true );
		
		do_action( 'woocommerce_calculated_shipping' );

		WC()->cart->calculate_totals();
		
		// Check cart items are valid
		//do_action( 'woocommerce_check_cart_items' );
	}
	
	/**
	 * Set customer address.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_Error|WP_REST_Response
	 */
	public function set_address( $request ) {
		
		$this->_set_address( $request );
		
		return array(
			'shipping' => $this->_get_shipping_methods( $request ),
			'payment' => $this->get_payment_methods( $request ),
			'cart' => $this->_get_cart_totals( $request, array() )
		);
	}
	
	/**
	 * Get available payment methods.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_Error|WP_REST_Response
	 */
	public function get_payment_methods( $request ) {
		
		$support_payment_methods = apply_filters( 'w2w_available_payments', W2W()->payments );
		
		$response = array();
		if ( WC()->cart->needs_payment() ) {
			
			$payment_methods = WC()->payment_gateways->get_available_payment_gateways();
			$response['methods'] = array();
			$payment_keys = array();
			
			foreach( $payment_methods as $method ) {
				
				foreach( $support_payment_methods as $payment_method ) {
				
					if( $payment_method == $method->id ) {
						
						$payment = array(
							'id' => $method->id,
							'title' => isset( $method->settings['title'] ) ? $method->settings['title'] : $method->method_title,
							'description' => isset( $method->settings['description'] ) ? $method->settings['description'] : $method->method_description,
							'instructions' => isset( $method->settings['instructions'] ) ? $method->settings['instructions'] : $method->method_description
						);

						$response['methods'][] = $payment;
						$payment_keys[] = $payment['id'];
						break;
					}
				}
			}
			$response['methods'] = apply_filters( 'w2w_get_payment_menthods', $response['methods'] );
			$response['chosen_method'] = WC()->session->get( 'chosen_payment_method' );
			$response['chosen_method'] = ! empty( $response['chosen_method'] ) && in_array( $response['chosen_method'], $payment_keys ) 
										 ? $response['chosen_method']
										 : ( ! empty( $response['methods'] ) ? $response['methods'][0]['id'] : false );
		} else {
			$response['methods'] = array();
			$response['chosen_method'] = '';
		}
		
		return $response;
	}
	
	/**
	 * Save payment method.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_Error|WP_REST_Response
	 */
	public function save_payment_method( $request ) {
		if ( ! defined('WOOCOMMERCE_CART') ) {
			define( 'WOOCOMMERCE_CART', true );
		}

		if ( isset( $request['payment_method'] ) ) {
			WC()->session->set( 'chosen_payment_method', $request['payment_method'] );
		}
		
		return array(
			'shipping' => $this->_get_shipping_methods( $request ),
			'payment' => $this->get_payment_methods( $request ),
			'cart' => $this->_get_cart_totals( $request, array() )
		);
	}
	
	private function handle_checkout_params( $request, $fields = array( 'address', 'coupon', 'shipping' ) ) {
		
		foreach( $fields as $field ) {
			
			switch( $field ) {
				
				case 'address':
					if( isset( $request['country_id'] ) && in_array( 'address', $fields ) ) $this->_set_address( $request );
					break;
				case 'shipping':
					if( isset( $request['shipping_method'] ) && in_array( 'shipping', $fields ) ) $this->_save_shipping_method( $request );
					break;
			}
		}
	}
	
	private function _get_cart_totals( $request, $fields = array( 'address', 'coupon', 'shipping' ), $check_cart_items = false ) {
		
		//$this->handle_checkout_params( $request, $fields );
		
		$request['w2w_without_checkout_params'] = true;
		$request['check_cart_items'] = $check_cart_items;
		return $this->get_cart_totals( $request );
	}
	
	/**
	 * Get cart items and totals.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_Error|WP_REST_Response
	 */
	public function get_cart_totals( $request ) {
		
		if ( ! defined( 'WOOCOMMERCE_CART' ) ) {
			define( 'WOOCOMMERCE_CART', true );
		}
		
		/*
		//处理结算字段
		if( ! isset( $request['w2w_without_checkout_params'] ) || $request['w2w_without_checkout_params'] != true ) {
			//$this->handle_checkout_params( $request, array( 'address', 'coupon', 'shipping' ) );
		}
		*/

		$json = array();

		WC()->cart->calculate_totals();
		
		$temp = WC()->cart->get_cart();
		foreach($temp as $key => $cartitem) {
			$product_id   = $cartitem['variation_id'] > 0 ? $cartitem['variation_id'] : $cartitem['product_id'];
			$post = wc_get_product( $product_id );
			$product = $this->get_product_data($post);
			
			$cartitem['product'] = $product;
			
			$json['cart'][$key] = $cartitem;
			
			$json['cart'][$key]['parent_attributes'] = $post->get_type() == 'variation' ? $this->get_attributes( wc_get_product( $json['cart'][$key]['product_id'] ) ) : null;
		}
		
		$cross_sells = WC()->cart->get_cross_sells();
		shuffle( $cross_sells );
		
		$json['cart_total'] = floatval( WC()->cart->total );
		$json['cart_contents_total'] = floatval( WC()->cart->cart_contents_total );
		$json['cart_subtotal'] = WC()->cart->subtotal;
		$json['shipping_total'] = floatval( WC()->cart->shipping_total );
		$json['shipping_tax_total'] = WC()->cart->shipping_tax_total;
		$json['needs_shipping'] = WC()->cart->needs_shipping();
		$json['subtotal_ex_tax'] = WC()->cart->subtotal_ex_tax;
		$json['taxes_total'] = WC()->cart->get_taxes_total();
		$json['cart_contents_count'] = WC()->cart->get_cart_contents_count();
		$json['currency'] = get_woocommerce_currency();
		$json['discount_cart'] = WC()->cart->discount_cart;
		$json['cross_sells'] = $cross_sells;
		$json['_wpnonce'] = wp_create_nonce('woocommerce-process_checkout');
		
		
		if( isset( $request['check_cart_items'] ) && $request['check_cart_items'] == true ) {
			
			do_action( 'woocommerce_check_cart_items' );
		}
		
		// 输出可能的错误
		$all_notices = wc_get_notices();
		wc_clear_notices();
		
		foreach( $all_notices as $type => &$notice ) {
			
			foreach( $notice as &$n ) {
				$n = W2W_Util::strip_html_tags( array( 'a', 'strong' ), html_entity_decode( $n ), false );
			}
		}
		
		$json['errors'] = isset( $all_notices['error'] ) ? $all_notices['error'] : array();
		$json['success'] = isset( $all_notices['success'] ) ? $all_notices['success'] : array();
		$json['notices'] = isset( $all_notices['notice'] ) ? $all_notices['notice'] : array();
		return $json;
	}
}
