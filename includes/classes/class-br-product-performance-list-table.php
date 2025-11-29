<?php
/**
 * Creates the WP_List_Table for displaying product performance data.
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

class BR_Product_Performance_List_Table extends WP_List_Table {

	/**
	 * Stores the aggregated product data.
	 * @var array
	 */
	private $product_data = [];

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct( [
			'singular' => 'Product Performance',
			'plural'   => 'Product Performances',
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
			'cb'                  => '<input type="checkbox" />',
			'image'               => __( 'Image', 'business-report' ),
			'name'                => __( 'Name', 'business-report' ),
			'total_sell'          => __( 'Total Sell', 'business-report' ),
			'total_delivered'     => __( 'Total Delivered', 'business-report' ),
			'total_returned'      => __( 'Total Returned', 'business-report' ),
			'total_selling_price' => __( 'Total Selling Price', 'business-report' ),
			'total_cost_price'    => __( 'Total Cost Price', 'business-report' ),
			'total_profit'        => __( 'Total Profit', 'business-report' ),
		];
	}

	/**
	 * Define which columns are sortable.
	 *
	 * @return array
	 */
	public function get_sortable_columns() {
		return [
			'name'                => [ 'name', false ],
			'total_sell'          => [ 'total_sell', false ],
			'total_delivered'     => [ 'total_delivered', false ],
			'total_returned'      => [ 'total_returned', false ],
			'total_selling_price' => [ 'total_selling_price', false ],
			'total_cost_price'    => [ 'total_cost_price', false ],
			'total_profit'        => [ 'total_profit', false ],
		];
	}

	/**
	 * Prepare the items for the table to process.
	 */
	public function prepare_items() {
		global $wpdb;
		$this->_column_headers = [ $this->get_columns(), [], $this->get_sortable_columns() ];

		// 1. Get Date Range
		$current_range_key = isset( $_GET['range'] ) ? sanitize_key( $_GET['range'] ) : 'today';
		$start_date_get    = isset( $_GET['start_date'] ) ? sanitize_text_field( $_GET['start_date'] ) : null;
		$end_date_get      = isset( $_GET['end_date'] ) ? sanitize_text_field( $_GET['end_date'] ) : null;
		$is_custom_range   = ! empty( $start_date_get ) && ! empty( $end_date_get );
		$date_range        = br_get_date_range( $is_custom_range ? '' : $current_range_key, $start_date_get, $end_date_get );
		$start_date        = $date_range['start'] . ' 00:00:00';
		$end_date          = $date_range['end'] . ' 23:59:59';

		// 2. Get Filters
		$search_term     = isset( $_GET['s'] ) ? sanitize_text_field( $_GET['s'] ) : '';
		$category_filter = isset( $_GET['product_cat'] ) ? sanitize_text_field( $_GET['product_cat'] ) : '';
		$brand_filter    = isset( $_GET['product_brand'] ) ? sanitize_text_field( $_GET['product_brand'] ) : '';

		// 3. Fetch orders within date range
		$orders_table    = $wpdb->prefix . 'br_orders';
		$orders_in_range = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT order_id, courier_status FROM {$orders_table} WHERE order_date BETWEEN %s AND %s",
				$start_date,
				$end_date
			),
			ARRAY_A
		);

		if ( empty( $orders_in_range ) ) {
			$this->items = [];
			return;
		}

		$product_performance_data = [];
		$order_courier_status_map = array_column( $orders_in_range, 'courier_status', 'order_id' );

		// 4. Loop through orders and aggregate item data
		foreach ( $order_courier_status_map as $order_id => $courier_status ) {
			$order = wc_get_order( $order_id );
			if ( ! $order ) {
				continue;
			}

			foreach ( $order->get_items() as $item_id => $item ) {
				if ( ! is_a( $item, 'WC_Order_Item_Product' ) ) {
					continue;
				}

				$product_id     = $item->get_product_id();
				$variation_id   = $item->get_variation_id();
				$post_id        = $variation_id > 0 ? $variation_id : $product_id;
				$quantity       = $item->get_quantity();
				$line_total     = $item->get_total(); // Price after discounts, before tax
				$cost           = (float) br_get_product_cost( $post_id );
				$item_cost      = $cost * $quantity;
				$item_profit    = $line_total - $item_cost;
				$product_object = $item->get_product(); // Get WC_Product

				if ( ! $product_object ) {
					continue;
				}

				// Initialize product data if it doesn't exist
				if ( ! isset( $product_performance_data[ $post_id ] ) ) {
					$product_performance_data[ $post_id ] = [
						'id'                  => $post_id,
						'image_id'            => $product_object->get_image_id(),
						'name'                => $product_object->get_formatted_name(),
						'sku'                 => $product_object->get_sku(), // NEW: Store SKU
						'total_sell'          => 0,
						'total_delivered'     => 0,
						'total_returned'      => 0,
						'total_selling_price' => 0,
						'total_cost_price'    => 0,
						'total_profit'        => 0,
					];
				}

				// Aggregate total sell quantity (always)
				$product_performance_data[ $post_id ]['total_sell'] += $quantity;

				// Check courier status from our map
				// COURIER_STATUS: 0 = Delivered, 1 = Returned (Full/Non-Partial), 2 = Returned (Partial)
				if ( isset( $courier_status ) ) {
					if ( $courier_status == 0 ) {
						// CORRECTION 1: Only add to financials if delivered
						$product_performance_data[ $post_id ]['total_delivered']     += $quantity;
						$product_performance_data[ $post_id ]['total_selling_price'] += $line_total;
						$product_performance_data[ $post_id ]['total_cost_price']    += $item_cost;
						$product_performance_data[ $post_id ]['total_profit']        += $item_profit;
					} elseif ( in_array( $courier_status, [ 1, 2 ] ) ) {
						// CORRECTION 2: This logic is correct (sums full and partial returns)
						$product_performance_data[ $post_id ]['total_returned'] += $quantity;
					}
				}
			}
		}

		// 5. Filter the aggregated data
		$filtered_data = [];
		if ( ! empty( $search_term ) || ! empty( $category_filter ) || ! empty( $brand_filter ) ) {
			foreach ( $product_performance_data as $post_id => $data ) {
				// Search filter
				if ( ! empty( $search_term ) ) {
					if ( stripos( $data['name'], $search_term ) === false ) {
						continue; // Skip if name doesn't match
					}
				}

				// Category filter
				if ( ! empty( $category_filter ) ) {
					if ( ! has_term( $category_filter, 'product_cat', $post_id ) ) {
						continue; // Skip if not in category
					}
				}

				// Brand filter
				if ( ! empty( $brand_filter ) ) {
					if ( ! has_term( $brand_filter, 'product_brand', $post_id ) ) {
						continue; // Skip if not in brand
					}
				}

				$filtered_data[ $post_id ] = $data;
			}
		} else {
			$filtered_data = $product_performance_data;
		}

		// 6. Handle sorting
		usort( $filtered_data, [ &$this, 'usort_reorder' ] );

		// 7. Pagination
		$per_page     = 20;
		$current_page = $this->get_pagenum();
		$total_items  = count( $filtered_data );

		$this->set_pagination_args( [
			'total_items' => $total_items,
			'per_page'    => $per_page,
		] );
		$this->items = array_slice( $filtered_data, ( ( $current_page - 1 ) * $per_page ), $per_page );
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
	 * Render the name column.
	 */
	public function column_name( $item ) {
		// CORRECTION 3: Truncate name and add truncated SKU
		$name_raw = wp_strip_all_tags( $item['name'] ); // Remove HTML like <small> from variation names
		$name_truncated = mb_strimwidth( $name_raw, 0, 25, '...' );

		$sku_raw = $item['sku'];
		$sku_display = '';
		if ( ! empty( $sku_raw ) ) {
			$sku_truncated = mb_strimwidth( $sku_raw, 0, 20, '...' );
			$sku_display = '<br><small>SKU: ' . esc_html( $sku_truncated ) . '</small>';
		}

		return '<strong>' . esc_html( $name_truncated ) . '</strong>' . $sku_display;
	}

	/**
	 * Render other columns.
	 */
	public function column_default( $item, $column_name ) {
		$total_sell = (int) $item['total_sell'];
		if ( $total_sell === 0 ) {
			$total_sell = 1; // Prevent division by zero
		}

		switch ( $column_name ) {
			case 'total_sell':
				return '<strong>' . number_format_i18n( $item[ $column_name ] ) . '</strong>';

			case 'total_delivered':
				$count = (int) $item[ $column_name ];
				$percent = ( $count / $total_sell ) * 100;
				return '<div class="br-performance-cell">' . number_format_i18n( $count ) . '<small>' . number_format_i18n( $percent, 1 ) . '%</small></div>';

			case 'total_returned':
				$count = (int) $item[ $column_name ];
				$percent = ( $count / $total_sell ) * 100;
				return '<div class="br-performance-cell">' . number_format_i18n( $count ) . '<small>' . number_format_i18n( $percent, 1 ) . '%</small></div>';

			case 'total_selling_price':
			case 'total_cost_price':
			case 'total_profit':
				return wc_price( $item[ $column_name ] );
			default:
				return '–';
		}
	}

	/**
	 * Renders the filter dropdowns for category and brand.
	 *
	 * @param string $which 'top' or 'bottom'
	 */
	protected function extra_tablenav( $which ) {
		if ( 'top' !== $which ) {
			return;
		}
		?>
		<div class="alignleft actions br-product-filters">
			<?php
			// Add Category Filter
			br_product_taxonomy_filter_dropdown( 'product_cat', 'product_cat', __( 'All Categories', 'business-report' ) );

			// Add Brand Filter (checks if 'product_brand' taxonomy exists)
			if ( taxonomy_exists( 'product_brand' ) ) {
				br_product_taxonomy_filter_dropdown( 'product_brand', 'product_brand', __( 'All Brands', 'business-report' ) );
			}

			submit_button( __( 'Filter' ), 'button', 'filter_action', false, [ 'id' => 'post-query-submit' ] );
			?>
		</div>
		<?php
	}

	/**
	 * Allows for sorting of data.
	 */
	private function usort_reorder( $a, $b ) {
		$orderby = ( ! empty( $_GET['orderby'] ) ) ? $_GET['orderby'] : 'name';
		$order   = ( ! empty( $_GET['order'] ) ) ? $_GET['order'] : 'asc';

		// Handle numeric sorting
		if ( in_array( $orderby, [ 'total_sell', 'total_delivered', 'total_returned', 'total_selling_price', 'total_cost_price', 'total_profit' ] ) ) {
			$a_val = is_numeric( $a[ $orderby ] ) ? $a[ $orderby ] : 0;
			$b_val = is_numeric( $b[ $orderby ] ) ? $b[ $orderby ] : 0;
			$result = $a_val - $b_val;
		} else {
			// Default string sorting
			$result = strcmp( $a[ $orderby ], $b[ $orderby ] );
		}

		return ( 'asc' === $order ) ? $result : -$result;
	}
}

