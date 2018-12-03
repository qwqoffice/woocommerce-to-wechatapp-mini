<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Abstract Rest Terms Controler Class
 *
 * @author   WooThemes
 * @category API
 * @package  WooCommerce/Abstracts
 * @version  2.6.0
 */
abstract class W2W_REST_Terms_Controller extends WC_REST_Controller {

	/**
	 * Route base.
	 *
	 * @var string
	 */
	protected $rest_base = '';

	/**
	 * Taxonomy.
	 *
	 * @var string
	 */
	protected $taxonomy = '';

	/**
	 * Register the routes for terms.
	 */
	public function register_routes() {
		register_rest_route( $this->namespace, '/' . $this->rest_base, array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_items' ),
				'permission_callback' => array( $this, 'get_items_permissions_check' ),
				'args'                => $this->get_collection_params(),
			),
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'create_item' ),
				'permission_callback' => array( $this, 'create_item_permissions_check' ),
				'args'                => array_merge( $this->get_endpoint_args_for_item_schema( WP_REST_Server::CREATABLE ), array(
					'name' => array(
						'required' => true,
					),
				) ),
			),
			'schema' => array( $this, 'get_public_item_schema' ),
		));

		register_rest_route( $this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)', array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_item' ),
				'permission_callback' => array( $this, 'get_item_permissions_check' ),
				'args'                => array(
					'context'         => $this->get_context_param( array( 'default' => 'view' ) ),
				),
			),
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array( $this, 'update_item' ),
				'permission_callback' => array( $this, 'update_item_permissions_check' ),
				'args'                => $this->get_endpoint_args_for_item_schema( WP_REST_Server::EDITABLE ),
			),
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => array( $this, 'delete_item' ),
				'permission_callback' => array( $this, 'delete_item_permissions_check' ),
				'args'                => array(
					'force' => array(
						'default'     => false,
						'description' => __( 'Required to be true, as resource does not support trashing.', 'woocommerce' ),
					),
				),
			),
			'schema' => array( $this, 'get_public_item_schema' ),
		) );

		register_rest_route( $this->namespace, '/' . $this->rest_base . '/batch', array(
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array( $this, 'batch_items' ),
				'permission_callback' => array( $this, 'batch_items_permissions_check' ),
				'args'                => $this->get_endpoint_args_for_item_schema( WP_REST_Server::EDITABLE ),
			),
			'schema' => array( $this, 'get_public_batch_schema' ),
		) );
	}

	/**
	 * Check if a given request has access to read the terms.
	 *
	 * @param  WP_REST_Request $request Full details about the request.
	 * @return WP_Error|boolean
	 */
	public function get_items_permissions_check( $request ) {
		$permissions = $this->check_permissions( $request, 'read' );
		if ( is_wp_error( $permissions ) ) {
			return $permissions;
		}

		if ( ! $permissions ) {
			return new WP_Error( 'woocommerce_rest_cannot_view', __( 'Sorry, you cannot list resources.', 'woocommerce' ), array( 'status' => rest_authorization_required_code() ) );
		}

		return true;
	}


	/**
	 * Check if a given request has access to read a term.
	 *
	 * @param  WP_REST_Request $request Full details about the request.
	 * @return WP_Error|boolean
	 */
	public function get_item_permissions_check( $request ) {
		return true;
	}

	/**
	 * Check permissions.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @param string $context Request context.
	 * @return bool|WP_Error
	 */
	protected function check_permissions( $request, $context = 'read' ) {
		return true;
	}

	/**
	 * Get terms associated with a taxonomy.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_items( $request ) {
		$taxonomy      = $this->get_taxonomy( $request );
		$prepared_args = array(
			'exclude'    => $request['exclude'],
			'include'    => $request['include'],
			'order'      => $request['order'],
			'orderby'    => $request['orderby'],
			'product'    => $request['product'],
			'hide_empty' => $request['hide_empty'],
			'number'     => $request['per_page'],
			'search'     => $request['search'],
			'slug'       => $request['slug'],
		);

		if ( ! empty( $request['offset'] ) ) {
			$prepared_args['offset'] = $request['offset'];
		} else {
			$prepared_args['offset']  = ( $request['page'] - 1 ) * $prepared_args['number'];
		}

		$taxonomy_obj = get_taxonomy( $taxonomy );

		if ( $taxonomy_obj->hierarchical && isset( $request['parent'] ) ) {
			if ( 0 === $request['parent'] ) {
				// Only query top-level terms.
				$prepared_args['parent'] = 0;
			} else {
				if ( $request['parent'] ) {
					$prepared_args['parent'] = $request['parent'];
				}
			}
		}

		/**
		 * Filter the query arguments, before passing them to `get_terms()`.
		 *
		 * Enables adding extra arguments or setting defaults for a terms
		 * collection request.
		 *
		 * @see https://developer.wordpress.org/reference/functions/get_terms/
		 *
		 * @param array           $prepared_args Array of arguments to be
		 *                                       passed to get_terms.
		 * @param WP_REST_Request $request       The current request.
		 */
		$prepared_args = apply_filters( "woocommerce_rest_{$taxonomy}_query", $prepared_args, $request );

		if ( ! empty( $prepared_args['product'] )  ) {
			$query_result = $this->get_terms_for_product( $prepared_args );
			$total_terms = $this->total_terms;
		} else {
			$query_result = get_terms( $taxonomy, $prepared_args );

			$count_args = $prepared_args;
			unset( $count_args['number'] );
			unset( $count_args['offset'] );
			$total_terms = wp_count_terms( $taxonomy, $count_args );

			// Ensure we don't return results when offset is out of bounds.
			// See https://core.trac.wordpress.org/ticket/35935
			if ( $prepared_args['offset'] >= $total_terms ) {
				$query_result = array();
			}

			// wp_count_terms can return a falsy value when the term has no children.
			if ( ! $total_terms ) {
				$total_terms = 0;
			}
		}
		$response = array();
		foreach ( $query_result as $term ) {
			$data = $this->prepare_item_for_response( $term, $request );
			$response[] = $this->prepare_response_for_collection( $data );
		}

		$response = rest_ensure_response( $response );

		// Store pagation values for headers then unset for count query.
		$per_page = (int) $prepared_args['number'];
		$page = $per_page == 0 ? 1 : ceil( ( ( (int) $prepared_args['offset'] ) / $per_page ) + 1 );

		$response->header( 'X-WP-Total', (int) $total_terms );
		$max_pages = $per_page == 0 ? 1 : ceil( $total_terms / $per_page );
		$response->header( 'X-WP-TotalPages', (int) $max_pages );

		return $response;
	}

	/**
	 * Get a single term from a taxonomy.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Request|WP_Error
	 */
	public function get_item( $request ) {
		$taxonomy = $this->get_taxonomy( $request );
		$term     = get_term( (int) $request['id'], $taxonomy );

		if ( is_wp_error( $term ) ) {
			return $term;
		}

		$response = $this->prepare_item_for_response( $term, $request );

		return rest_ensure_response( $response );
	}

	/**
	 * Prepare links for the request.
	 *
	 * @param object $term Term object.
	 * @param WP_REST_Request $request Full details about the request.
	 * @return array Links for the given term.
	 */
	protected function prepare_links( $term, $request ) {
		$base = '/' . $this->namespace . '/' . $this->rest_base;

		if ( ! empty( $request['attribute_id'] ) ) {
			$base = str_replace( '(?P<attribute_id>[\d]+)', (int) $request['attribute_id'], $base );
		}

		$links = array(
			'self' => array(
				'href' => rest_url( trailingslashit( $base ) . $term->term_id ),
			),
			'collection' => array(
				'href' => rest_url( $base ),
			),
		);

		if ( $term->parent ) {
			$parent_term = get_term( (int) $term->parent, $term->taxonomy );
			if ( $parent_term ) {
				$links['up'] = array(
					'href' => rest_url( trailingslashit( $base ) . $parent_term->term_id ),
				);
			}
		}

		return $links;
	}

	/**
	 * Get the terms attached to a product.
	 *
	 * This is an alternative to `get_terms()` that uses `get_the_terms()`
	 * instead, which hits the object cache. There are a few things not
	 * supported, notably `include`, `exclude`. In `self::get_items()` these
	 * are instead treated as a full query.
	 *
	 * @param array $prepared_args Arguments for `get_terms()`.
	 * @return array List of term objects. (Total count in `$this->total_terms`).
	 */
	protected function get_terms_for_product( $prepared_args ) {
		$taxonomy = $this->get_taxonomy( $request );

		$query_result = get_the_terms( $prepared_args['product'], $taxonomy );
		if ( empty( $query_result ) ) {
			$this->total_terms = 0;
			return array();
		}

		// get_items() verifies that we don't have `include` set, and default.
		// ordering is by `name`.
		if ( ! in_array( $prepared_args['orderby'], array( 'name', 'none', 'include' ) ) ) {
			switch ( $prepared_args['orderby'] ) {
				case 'id' :
					$this->sort_column = 'term_id';
					break;

				case 'slug' :
				case 'term_group' :
				case 'description' :
				case 'count' :
					$this->sort_column = $prepared_args['orderby'];
					break;
			}
			usort( $query_result, array( $this, 'compare_terms' ) );
		}
		if ( strtolower( $prepared_args['order'] ) !== 'asc' ) {
			$query_result = array_reverse( $query_result );
		}

		// Pagination.
		$this->total_terms = count( $query_result );
		$query_result = array_slice( $query_result, $prepared_args['offset'], $prepared_args['number'] );

		return $query_result;
	}

	/**
	 * Comparison function for sorting terms by a column.
	 *
	 * Uses `$this->sort_column` to determine field to sort by.
	 *
	 * @param stdClass $left Term object.
	 * @param stdClass $right Term object.
	 * @return int <0 if left is higher "priority" than right, 0 if equal, >0 if right is higher "priority" than left.
	 */
	protected function compare_terms( $left, $right ) {
		$col       = $this->sort_column;
		$left_val  = $left->$col;
		$right_val = $right->$col;

		if ( is_int( $left_val ) && is_int( $right_val ) ) {
			return $left_val - $right_val;
		}

		return strcmp( $left_val, $right_val );
	}

	/**
	 * Get the query params for collections
	 *
	 * @return array
	 */
	public function get_collection_params() {
		$params = parent::get_collection_params();

		if ( '' !== $this->taxonomy ) {
			$taxonomy = get_taxonomy( $this->taxonomy );
		} else {
			$taxonomy = new stdClass();
			$taxonomy->hierarchical = true;
		}

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
		if ( ! $taxonomy->hierarchical ) {
			$params['offset'] = array(
				'description'        => __( 'Offset the result set by a specific number of items.', 'woocommerce' ),
				'type'               => 'integer',
				'sanitize_callback'  => 'absint',
				'validate_callback'  => 'rest_validate_request_arg',
			);
		}
		// 2018-03-16 更改 pre_page 参数最小值限制为0
		$params['per_page']   = array(
			'description'        => __( 'Maximum number of items to be returned in result set.', 'woocommerce' ),
			'type'               => 'integer',
			'default'            => 10,
			'minimum'            => 0,
			'maximum'            => 100,
			'sanitize_callback'  => 'absint',
			'validate_callback'  => 'rest_validate_request_arg',
		);
		$params['order']      = array(
			'description'           => __( 'Order sort attribute ascending or descending.', 'woocommerce' ),
			'type'                  => 'string',
			'sanitize_callback'     => 'sanitize_key',
			'default'               => 'asc',
			'enum'                  => array(
				'asc',
				'desc',
			),
			'validate_callback'     => 'rest_validate_request_arg',
		);
		$params['orderby']    = array(
			'description'           => __( 'Sort collection by resource attribute.', 'woocommerce' ),
			'type'                  => 'string',
			'sanitize_callback'     => 'sanitize_key',
			'default'               => 'name',
			'enum'                  => array(
				'id',
				'include',
				'name',
				'slug',
				'term_group',
				'description',
				'count',
			),
			'validate_callback'     => 'rest_validate_request_arg',
		);
		$params['hide_empty'] = array(
			'description'           => __( 'Whether to hide resources not assigned to any products.', 'woocommerce' ),
			'type'                  => 'boolean',
			'default'               => false,
			'validate_callback'     => 'rest_validate_request_arg',
		);
		if ( $taxonomy->hierarchical ) {
			$params['parent'] = array(
				'description'        => __( 'Limit result set to resources assigned to a specific parent.', 'woocommerce' ),
				'type'               => 'integer',
				'sanitize_callback'  => 'absint',
				'validate_callback'  => 'rest_validate_request_arg',
			);
		}
		$params['product'] = array(
			'description'           => __( 'Limit result set to resources assigned to a specific product.', 'woocommerce' ),
			'type'                  => 'integer',
			'default'               => null,
			'validate_callback'     => 'rest_validate_request_arg',
		);
		$params['slug']    = array(
			'description'        => __( 'Limit result set to resources with a specific slug.', 'woocommerce' ),
			'type'               => 'string',
			'validate_callback'  => 'rest_validate_request_arg',
		);

		return $params;
	}

	/**
	 * Get taxonomy.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return int|WP_Error
	 */
	protected function get_taxonomy( $request ) {
		// Check if taxonomy is defined.
		// Prevents check for attribute taxonomy more than one time for each query.
		if ( '' !== $this->taxonomy ) {
			return $this->taxonomy;
		}

		if ( ! empty( $request['attribute_id'] ) ) {
			$taxonomy = wc_attribute_taxonomy_name_by_id( (int) $request['attribute_id'] );

			$this->taxonomy = $taxonomy;
		}

		return $this->taxonomy;
	}
}
