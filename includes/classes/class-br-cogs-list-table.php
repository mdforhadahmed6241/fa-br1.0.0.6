<?php
/**
 * Creates the WP_List_Table for displaying products and variations with COGS.
 *
 * @package BusinessReport
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

// WP_List_Table is not loaded automatically so we need to load it.
if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class BR_COGS_List_Table extends WP_List_Table {

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct( [
			'singular' => 'Product Cost',
			'plural'   => 'Product Costs',
			'ajax'     => false,
		] );
	}

	/**
	 * Define the columns that are going to be used in the table.
	 *
	 * @return array
	 */
	public function get_columns() {
		return [
			'cb'            => '<input type="checkbox" />',
			'image'         => __( 'Image', 'business-report' ),
			'name'          => __( 'Name', 'business-report' ),
			'stock_qty'     => __( 'Stock Qty', 'business-report' ),
			'cost_price'    => __( 'Cost Price', 'business-report' ),
			'selling_price' => __( 'Selling Price', 'business-report' ),
			'actions'       => __( 'Edit', 'business-report' ),
		];
	}

	/**
	 * Define which columns are sortable.
	 *
	 * @return array
	 */
	public function get_sortable_columns() {
		return [
			'name'          => [ 'name', false ],
			'stock_qty'     => [ 'stock_qty', false ],
			'cost_price'    => [ 'cost_price', false ],
			'selling_price' => [ 'selling_price', false ],
		];
	}

	/**
	 * Prepare the items for the table to process.
	 */
	public function prepare_items() {
		$columns  = $this->get_columns();
		$hidden   = [];
		$sortable = $this->get_sortable_columns();
		$this->_column_headers = [ $columns, $hidden, $sortable ];

		$per_page     = 20;
		$current_page = $this->get_pagenum();
        
        // Fetch data includes filtering logic now
		$data         = $this->fetch_table_data();

        // Search is handled by WP_Query in fetch_table_data
        
		// Handle sorting
		usort( $data, [ &$this, 'usort_reorder' ] );

		$total_items  = count( $data );
		$this->set_pagination_args( [
			'total_items' => $total_items,
			'per_page'    => $per_page,
		] );
		$this->items = array_slice( $data, ( ( $current_page - 1 ) * $per_page ), $per_page );
	}

	/**
	 * Fetch the data for the table.
	 *
	 * @return array
	 */
	private function fetch_table_data() {
		$table_data = [];
		$args       = [
			'post_type'      => 'product',
			'posts_per_page' => -1,
			'post_status'    => 'publish',
		];

        // --- NEW: Handle Search ---
        if ( isset($_GET['s']) && ! empty($_GET['s']) ) {
            $args['s'] = sanitize_text_field($_GET['s']);
        }

        // --- NEW: Handle Taxonomy Filters ---
        $tax_query = ['relation' => 'AND'];
        
        $category_filter = isset($_GET['product_cat']) ? sanitize_text_field($_GET['product_cat']) : '';
        if ( ! empty($category_filter) ) {
            $tax_query[] = [
                'taxonomy' => 'product_cat',
                'field'    => 'slug',
                'terms'    => $category_filter,
            ];
        }

        $brand_filter = isset($_GET['product_brand']) ? sanitize_text_field($_GET['product_brand']) : '';
        if ( ! empty($brand_filter) && taxonomy_exists('product_brand') ) {
            $tax_query[] = [
                'taxonomy' => 'product_brand',
                'field'    => 'slug',
                'terms'    => $brand_filter,
            ];
        }

        if ( count($tax_query) > 1 ) {
            $args['tax_query'] = $tax_query;
        }
        // --- End of new filters ---

		$products_query = new WP_Query( $args );

		if ( $products_query->have_posts() ) {
			while ( $products_query->have_posts() ) {
				$products_query->the_post();
				$product = wc_get_product( get_the_ID() );

				if ( ! $product ) {
					continue;
				}

				if ( $product->is_type( 'variable' ) ) {
					$variations = $product->get_children();
					foreach ( $variations as $variation_id ) {
						$variation = wc_get_product( $variation_id );
						if ( ! $variation ) {
							continue;
						}
						// Image fallback: use parent image if variation has none.
						$image_id = $variation->get_image_id() ? $variation->get_image_id() : $product->get_image_id();
						
						$table_data[] = [
							'id'            => $variation_id,
							'image_id'      => $image_id,
							'name'          => $variation->get_formatted_name(),
                            'sku'           => $variation->get_sku(),
							'stock_qty'     => $variation->get_stock_quantity() ?? 'N/A',
							'cost_price'    => br_get_product_cost( $variation_id ),
							'selling_price' => $variation->get_price(),
							'edit_link'     => get_edit_post_link( $product->get_id() ),
						];
					}
				} else {
					// Handle simple products
					$table_data[] = [
						'id'            => $product->get_id(),
						'image_id'      => $product->get_image_id(),
						'name'          => $product->get_name(),
                        'sku'           => $product->get_sku(),
						'stock_qty'     => $product->get_stock_quantity() ?? 'N/A',
						'cost_price'    => br_get_product_cost( $product->get_id() ),
						'selling_price' => $product->get_price(),
						'edit_link'     => get_edit_post_link( $product->get_id() ),
					];
				}
			}
		}
		wp_reset_postdata();
		return $table_data;
	}

	/**
	 * Render the checkbox column.
	 *
	 * @param array $item
	 * @return string
	 */
	public function column_cb( $item ) {
		return sprintf( '<input type="checkbox" name="product_id[]" value="%s" />', $item['id'] );
	}

	/**
	 * Render the image column.
	 */
	public function column_image( $item ) {
		$image = wp_get_attachment_image( $item['image_id'], [ 60, 60 ], true );
		return $image ? $image : '<span class="br-no-image"></span>';
	}

	/**
	 * Render the name column with actions.
	 * FIX: Changed esc_html() to wp_kses_post() to allow variation HTML.
	 */
	public function column_name( $item ) {
		return '<strong>' . wp_kses_post( $item['name'] ) . '</strong>';
	}

	/**
	 * Render the edit actions column.
	 */
	public function column_actions( $item ) {
		return sprintf( '<a href="%s" class="button br-edit-btn">Edit</a>', esc_url( $item['edit_link'] ) );
	}

	/**
	 * Render other columns.
	 */
	public function column_default( $item, $column_name ) {
		switch ( $column_name ) {
			case 'stock_qty':
                $stock_qty = $item['stock_qty'];
                // Check if stock is managed
                $product = wc_get_product($item['id']);
                if ($product && !$product->managing_stock() && $product->is_in_stock()) {
                    return '&#8734;'; // Infinity symbol
                }
                return is_numeric( $stock_qty ) ? $stock_qty : '0';
			case 'cost_price':
			case 'selling_price':
				return wc_price( $item[ $column_name ] );
			default:
				return print_r( $item, true );
		}
	}

	/**
	 * Allows for sorting of data.
	 */
	private function usort_reorder( $a, $b ) {
		$orderby = ( ! empty( $_GET['orderby'] ) ) ? $_GET['orderby'] : 'name';
		$order   = ( ! empty( $_GET['order'] ) ) ? $_GET['order'] : 'asc';
        
        // Handle numeric sorting for price/stock
        if (in_array($orderby, ['stock_qty', 'cost_price', 'selling_price'])) {
            $a_val = is_numeric($a[$orderby]) ? $a[$orderby] : 0;
            $b_val = is_numeric($b[$orderby]) ? $b[$orderby] : 0;
            $result = $a_val - $b_val;
        } else {
            // Default string sorting
            $result  = strcmp( $a[ $orderby ], $b[ $orderby ] );
        }

		return ( 'asc' === $order ) ? $result : -$result;
	}
}
