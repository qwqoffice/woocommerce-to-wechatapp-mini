<?php
/**
 * REST API Customers controller
 *
 * Handles requests to the /customers endpoint.
 *
 * @author   WooThemes
 * @category API
 * @package  WooCommerce/API
 * @since    2.6.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * REST API Customers controller class.
 *
 * @package WooCommerce/API
 * @extends WC_REST_Controller
 */
class W2W_REST_Customers_Controller extends WC_REST_Controller {

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
	protected $rest_base = 'customers';

	/**
	 * Register the routes for customers.
	 */
	public function register_routes() {
		
		register_rest_route( $this->namespace, '/' . $this->rest_base . '/login', array(
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'login' ),
				'permission_callback' => array( $this, 'get_item_permissions_check' ),
				'args'                => array(
					'context' => $this->get_context_param( array( 'default' => 'view' ) ),
					'js_code' => array(
						'required' => true
					),
					'encryptedData' => array(
						'required' => true
					),
					'iv' => array(
						'required' => true
					),
				),
			),
			'schema' => array( $this, 'get_public_item_schema' ),
		) );
		
		register_rest_route( $this->namespace, '/' . $this->rest_base . '/orderinfo', array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_orders_info' ),
				'permission_callback' => array( $this, 'get_item_permissions_check' ),
				'args'                => array(
					'context' => $this->get_context_param( array( 'default' => 'view' ) )
				),
			),
			'schema' => array( $this, 'get_public_item_schema' ),
		) );
	}

	/**
	 * Check whether a given request has permission to read customers.
	 *
	 * @param  WP_REST_Request $request Full details about the request.
	 * @return WP_Error|boolean
	 */
	public function get_items_permissions_check( $request ) {
		return true;
	}

	/**
	 * Check if a given request has access create customers.
	 *
	 * @param  WP_REST_Request $request Full details about the request.
	 * @return boolean
	 */
	public function create_item_permissions_check( $request ) {
		return true;
	}

	/**
	 * Check if a given request has access to read a customer.
	 *
	 * @param  WP_REST_Request $request Full details about the request.
	 * @return WP_Error|boolean
	 */
	public function get_item_permissions_check( $request ) {
		return true;
	}

	/**
	 * Check if a given request has access update a customer.
	 *
	 * @param  WP_REST_Request $request Full details about the request.
	 * @return boolean
	 */
	public function update_item_permissions_check( $request ) {
		$id = (int) $request['id'];

		if ( ! wc_rest_check_user_permissions( 'edit', $id ) ) {
			return new WP_Error( 'woocommerce_rest_cannot_edit', __( 'Sorry, you are not allowed to edit this resource.', 'woocommerce' ), array( 'status' => rest_authorization_required_code() ) );
		}

		return true;
	}

	/**
	 * Logs in a customer.
	 *
	 * @param  WP_REST_Request $request Full details about the request.
	 * @return Customer
	 */
	public function login( $request ) {
		
		$settings = get_option( 'w2w-settings' );
		
		// js_code 换取 openid
		$api_url = add_query_arg( array(
			'appid' => $settings['appid'],
			'secret' => $settings['appsecret'],
			'js_code' => $request['js_code'],
			'grant_type' => 'authorization_code'
		), 'https://api.weixin.qq.com/sns/jscode2session' );
					
		$api_response = wp_remote_get( $api_url );
		if( is_wp_error( $api_response ) ) {
			return $api_response;
		}
		
		$api_result = json_decode( $api_response['body'], true );
		if( empty( $api_result['openid'] ) || empty( $api_result['session_key'] ) || ! empty( $api_result['errcode'] ) ) {
			return new WP_Error( 'w2w_rest_customer_api_error', 'API错误：' . json_encode( $api_result ), array( 'status' => 400 ) );
		}
		
		// 解密用户信息
		$err_code = W2W_Util::decrypt_data( $settings['appid'], $api_result['session_key'], $request['encryptedData'], urldecode( $request['iv'] ), $data );

		if( $err_code != 0 ) {
			return new WP_Error( 'w2w_rest_customer_decrypt_error', '解密错误：' . $err_code, array( 'status' => 400 ) );
		}
		
		$data = json_decode( $data, true );
		
		// 创建顾客
		if( ! username_exists( $api_result['openid'] ) ) {
			
			$new_customer_data = apply_filters( 'woocommerce_new_customer_data', array(
				'user_login'    => $api_result['openid'],
				'first_name'	=> $data['nickName'],
				'nickname'      => $data['nickName'],
				'user_nicename' => $data['nickName'],
				'display_name'  => $data['nickName'],
				'user_pass'     => null,
				'role'          => 'customer',
			) );
			
			$customer_id = wp_insert_user( $new_customer_data );
			
			if ( is_wp_error( $customer_id ) ) {
				return $customer_id;
			}

			$customer = get_user_by( 'login', $api_result['openid'] );

			$this->update_additional_fields_for_object( $customer, $request );

			// Add customer data.
			$this->update_customer_meta_fields( $customer, $request );
			
			update_user_meta( $customer_id, 'w2w_avatar', $data['avatarUrl'] );
			update_user_meta( $customer_id, 'w2w_userinfo', $data );
			
			// 保存可能返回的 Union ID
			if( isset( $data['unionId'] ) ) {
				
				update_user_meta( $customer_id, 'w2w_unionid', $data['unionId'] );
			}
			
			/**
			 * Fires after a customer is created or updated via the REST API.
			 *
			 * @param WP_User         $customer  Data used to create the customer.
			 * @param WP_REST_Request $request   Request object.
			 * @param boolean         $creating  True when creating customer, false when updating customer.
			 */
			do_action( 'woocommerce_rest_insert_customer', $customer, $request, true );
			
			//$response->set_status( 201 );
			//$response->header( 'Location', rest_url( sprintf( '/%s/%s/%d', $this->namespace, $this->rest_base, $customer_id ) ) );
		}
		//更新顾客信息
		else {
			
			$customer = get_user_by( 'login', $api_result['openid'] );
			wp_update_user( array(
				'ID'            => $customer->ID,
				'first_name'	=> $data['nickName'],
				'nickname'      => $data['nickName'],
				'user_nicename' => $data['nickName'],
				'display_name'  => $data['nickName']
			) );
			update_user_meta( $customer->ID, 'w2w_avatar', $data['avatarUrl'] );
			update_user_meta( $customer->ID, 'w2w_userinfo', $data );
			
			// 保存可能返回的 Union ID
			if( isset( $data['unionId'] ) ) {
				
				update_user_meta( $customer_id, 'w2w_unionid', $data['unionId'] );
			}
		}
		
		// 生成SESSION
		$w2w_session = W2W_Util::generate_session( $customer->ID );
		W2W_Util::login( $w2w_session );
		
		do_action( 'w2w_login_with_user_info', $customer->ID );
		
		$response = array(
			'user_id' => $customer->ID,
			'w2w_session' => $w2w_session
		);
		$response = rest_ensure_response( $response );
		
		return $response;
	}
	
	/**
	 * Update customer meta fields.
	 *
	 * @param WP_User $customer
	 * @param WP_REST_Request $request
	 */
	protected function update_customer_meta_fields( $customer, $request ) {
		$schema = $this->get_item_schema();

		// Customer first name.
		if ( isset( $request['first_name'] ) ) {
			update_user_meta( $customer->ID, 'first_name', wc_clean( $request['first_name'] ) );
		}

		// Customer last name.
		if ( isset( $request['last_name'] ) ) {
			update_user_meta( $customer->ID, 'last_name', wc_clean( $request['last_name'] ) );
		}

		// Customer billing address.
		if ( isset( $request['billing'] ) ) {
			foreach ( array_keys( $schema['properties']['billing']['properties'] ) as $address ) {
				if ( isset( $request['billing'][ $address ] ) ) {
					update_user_meta( $customer->ID, 'billing_' . $address, wc_clean( $request['billing'][ $address ] ) );
				}
			}
		}

		// Customer shipping address.
		if ( isset( $request['shipping'] ) ) {
			foreach ( array_keys( $schema['properties']['shipping']['properties'] ) as $address ) {
				if ( isset( $request['shipping'][ $address ] ) ) {
					update_user_meta( $customer->ID, 'shipping_' . $address, wc_clean( $request['shipping'][ $address ] ) );
				}
			}
		}
	}
	
	
	// 获取用户订单信息
	public function get_orders_info( $request ) {
		
		$statuses = array(
			'pending',
			'processing',
			'shipped',
			'completed',
		);
		
		$data = array();
		$data['orders_info']['total'] = 0;
		if( is_user_logged_in() ) {
			$orders_controller = new W2W_REST_Orders_Controller();
			
			// 订单信息
			$request['paginate'] = false;
			$request['limit'] = -1;
			$order_counts = array();
			$total = 0;
			foreach( $statuses as $status ) {
				$request['status'] = $status;
				$count = count( $orders_controller->get_my_orders( $request ) );
				$total += $count;
				$order_counts[$status] = $count;
			}
			$data['orders_info'] = $order_counts;
			$data['orders_info']['total'] = $total;
		}
		
		return $data;
	}

	/**
	 * Get the Customer's schema, conforming to JSON Schema.
	 *
	 * @return array
	 */
	public function get_item_schema() {
		$schema = array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'customer',
			'type'       => 'object',
			'properties' => array(
				'id' => array(
					'description' => __( 'Unique identifier for the resource.', 'woocommerce' ),
					'type'        => 'integer',
					'context'     => array( 'view', 'edit' ),
					'readonly'    => true,
				),
				'date_created' => array(
					'description' => __( "The date the customer was created, in the site's timezone.", 'woocommerce' ),
					'type'        => 'date-time',
					'context'     => array( 'view', 'edit' ),
					'readonly'    => true,
				),
				'date_modified' => array(
					'description' => __( "The date the customer was last modified, in the site's timezone.", 'woocommerce' ),
					'type'        => 'date-time',
					'context'     => array( 'view', 'edit' ),
					'readonly'    => true,
				),
				'email' => array(
					'description' => __( 'The email address for the customer.', 'woocommerce' ),
					'type'        => 'string',
					'format'      => 'email',
					'context'     => array( 'view', 'edit' ),
				),
				'first_name' => array(
					'description' => __( 'Customer first name.', 'woocommerce' ),
					'type'        => 'string',
					'context'     => array( 'view', 'edit' ),
					'arg_options' => array(
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
				'last_name' => array(
					'description' => __( 'Customer last name.', 'woocommerce' ),
					'type'        => 'string',
					'context'     => array( 'view', 'edit' ),
					'arg_options' => array(
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
				'username' => array(
					'description' => __( 'Customer login name.', 'woocommerce' ),
					'type'        => 'string',
					'context'     => array( 'view', 'edit' ),
					'arg_options' => array(
						'sanitize_callback' => 'sanitize_user',
					),
				),
				'password' => array(
					'description' => __( 'Customer password.', 'woocommerce' ),
					'type'        => 'string',
					'context'     => array( 'edit' ),
				),
				'last_order' => array(
					'description' => __( 'Last order data.', 'woocommerce' ),
					'type'        => 'array',
					'context'     => array( 'view', 'edit' ),
					'readonly'    => true,
					'properties'  => array(
						'id' => array(
							'description' => __( 'Last order ID.', 'woocommerce' ),
							'type'        => 'integer',
							'context'     => array( 'view', 'edit' ),
							'readonly'    => true,
						),
						'date' => array(
							'description' => __( 'UTC DateTime of the customer last order.', 'woocommerce' ),
							'type'        => 'date-time',
							'context'     => array( 'view', 'edit' ),
							'readonly'    => true,
						),
					),
				),
				'orders_count' => array(
					'description' => __( 'Quantity of orders made by the customer.', 'woocommerce' ),
					'type'        => 'integer',
					'context'     => array( 'view', 'edit' ),
					'readonly'    => true,
				),
				'total_spent' => array(
					'description' => __( 'Total amount spent.', 'woocommerce' ),
					'type'        => 'string',
					'context'     => array( 'view', 'edit' ),
					'readonly'    => true,
				),
				'avatar_url' => array(
					'description' => __( 'Avatar URL.', 'woocommerce' ),
					'type'        => 'string',
					'context'     => array( 'view', 'edit' ),
					'readonly'    => true,
				),
				'billing' => array(
					'description' => __( 'List of billing address data.', 'woocommerce' ),
					'type'        => 'array',
					'context'     => array( 'view', 'edit' ),
					'properties' => array(
						'first_name' => array(
							'description' => __( 'First name.', 'woocommerce' ),
							'type'        => 'string',
							'context'     => array( 'view', 'edit' ),
						),
						'last_name' => array(
							'description' => __( 'Last name.', 'woocommerce' ),
							'type'        => 'string',
							'context'     => array( 'view', 'edit' ),
						),
						'company' => array(
							'description' => __( 'Company name.', 'woocommerce' ),
							'type'        => 'string',
							'context'     => array( 'view', 'edit' ),
						),
						'address_1' => array(
							'description' => __( 'Address line 1.', 'woocommerce' ),
							'type'        => 'string',
							'context'     => array( 'view', 'edit' ),
						),
						'address_2' => array(
							'description' => __( 'Address line 2.', 'woocommerce' ),
							'type'        => 'string',
							'context'     => array( 'view', 'edit' ),
						),
						'city' => array(
							'description' => __( 'City name.', 'woocommerce' ),
							'type'        => 'string',
							'context'     => array( 'view', 'edit' ),
						),
						'state' => array(
							'description' => __( 'ISO code or name of the state, province or district.', 'woocommerce' ),
							'type'        => 'string',
							'context'     => array( 'view', 'edit' ),
						),
						'postcode' => array(
							'description' => __( 'Postal code.', 'woocommerce' ),
							'type'        => 'string',
							'context'     => array( 'view', 'edit' ),
						),
						'country' => array(
							'description' => __( 'ISO code of the country.', 'woocommerce' ),
							'type'        => 'string',
							'context'     => array( 'view', 'edit' ),
						),
						'email' => array(
							'description' => __( 'Email address.', 'woocommerce' ),
							'type'        => 'string',
							'format'      => 'email',
							'context'     => array( 'view', 'edit' ),
						),
						'phone' => array(
							'description' => __( 'Phone number.', 'woocommerce' ),
							'type'        => 'string',
							'context'     => array( 'view', 'edit' ),
						),
					),
				),
				'shipping' => array(
					'description' => __( 'List of shipping address data.', 'woocommerce' ),
					'type'        => 'array',
					'context'     => array( 'view', 'edit' ),
					'properties' => array(
						'first_name' => array(
							'description' => __( 'First name.', 'woocommerce' ),
							'type'        => 'string',
							'context'     => array( 'view', 'edit' ),
						),
						'last_name' => array(
							'description' => __( 'Last name.', 'woocommerce' ),
							'type'        => 'string',
							'context'     => array( 'view', 'edit' ),
						),
						'company' => array(
							'description' => __( 'Company name.', 'woocommerce' ),
							'type'        => 'string',
							'context'     => array( 'view', 'edit' ),
						),
						'address_1' => array(
							'description' => __( 'Address line 1.', 'woocommerce' ),
							'type'        => 'string',
							'context'     => array( 'view', 'edit' ),
						),
						'address_2' => array(
							'description' => __( 'Address line 2.', 'woocommerce' ),
							'type'        => 'string',
							'context'     => array( 'view', 'edit' ),
						),
						'city' => array(
							'description' => __( 'City name.', 'woocommerce' ),
							'type'        => 'string',
							'context'     => array( 'view', 'edit' ),
						),
						'state' => array(
							'description' => __( 'ISO code or name of the state, province or district.', 'woocommerce' ),
							'type'        => 'string',
							'context'     => array( 'view', 'edit' ),
						),
						'postcode' => array(
							'description' => __( 'Postal code.', 'woocommerce' ),
							'type'        => 'string',
							'context'     => array( 'view', 'edit' ),
						),
						'country' => array(
							'description' => __( 'ISO code of the country.', 'woocommerce' ),
							'type'        => 'string',
							'context'     => array( 'view', 'edit' ),
						),
					),
				),
			),
		);

		return $this->add_additional_fields_schema( $schema );
	}

	/**
	 * Get role names.
	 *
	 * @return array
	 */
	protected function get_role_names() {
		global $wp_roles;

		return array_keys( $wp_roles->role_names );
	}

	/**
	 * Get the query params for collections.
	 *
	 * @return array
	 */
	public function get_collection_params() {
		$params = parent::get_collection_params();

		$params['context']['default'] = 'view';

		$params['exclude'] = array(
			'description'        => __( 'Ensure result set excludes specific ids.', 'woocommerce' ),
			'type'               => 'array',
			'default'            => array(),
			'sanitize_callback'  => 'wp_parse_id_list',
		);
		$params['include'] = array(
			'description'        => __( 'Limit result set to specific ids.', 'woocommerce' ),
			'type'               => 'array',
			'default'            => array(),
			'sanitize_callback'  => 'wp_parse_id_list',
		);
		$params['offset'] = array(
			'description'        => __( 'Offset the result set by a specific number of items.', 'woocommerce' ),
			'type'               => 'integer',
			'sanitize_callback'  => 'absint',
			'validate_callback'  => 'rest_validate_request_arg',
		);
		$params['order'] = array(
			'default'            => 'asc',
			'description'        => __( 'Order sort attribute ascending or descending.', 'woocommerce' ),
			'enum'               => array( 'asc', 'desc' ),
			'sanitize_callback'  => 'sanitize_key',
			'type'               => 'string',
			'validate_callback'  => 'rest_validate_request_arg',
		);
		$params['orderby'] = array(
			'default'            => 'name',
			'description'        => __( 'Sort collection by object attribute.', 'woocommerce' ),
			'enum'               => array(
				'id',
				'include',
				'name',
				'registered_date',
			),
			'sanitize_callback'  => 'sanitize_key',
			'type'               => 'string',
			'validate_callback'  => 'rest_validate_request_arg',
		);
		$params['email'] = array(
			'description'        => __( 'Limit result set to resources with a specific email.', 'woocommerce' ),
			'type'               => 'string',
			'format'             => 'email',
			'validate_callback'  => 'rest_validate_request_arg',
		);
		$params['role'] = array(
			'description'        => __( 'Limit result set to resources with a specific role.', 'woocommerce' ),
			'type'               => 'string',
			'default'            => 'customer',
			'enum'               => array_merge( array( 'all' ), $this->get_role_names() ),
			'validate_callback'  => 'rest_validate_request_arg',
		);
		return $params;
	}
}
