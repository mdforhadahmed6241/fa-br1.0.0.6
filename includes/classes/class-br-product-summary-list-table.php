<?php
/**
 * Creates the WP_List_Table for displaying product inventory summary by category.
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

class BR_Product_Summary_List_Table extends WP_List_Table {

    /**
     * Stores the raw inventory data passed from the summary tab function.
     * @var array
     */
    private $inventory_data = [];

    /**
     * Stores the processed category data.
     * @var array
     */
    private $category_data = [];

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct( [
			'singular' => 'Inventory Category',
			'plural'   => 'Inventory Categories',
			'ajax'     => false,
		] );
	}

    /**
     * Set the inventory data from the main tab function.
     * @param array $data The inventory data.
     */
    public function set_inventory_data( $data ) {
        $this->inventory_data = $data;
    }

	/**
	 * Define the columns that are going to be used in the table.
	 *
	 * @return array
	 */
	public function get_columns() {
		return [
			'category_name'      => __( 'Category Name', 'business-report' ),
			'total_products'     => __( 'Total Product', 'business-report' ),
			'total_stock'        => __( 'Total Stock', 'business-report' ),
			'total_sell_value'   => __( 'Total Sell Value', 'business-report' ),
			'total_cost_value'   => __( 'Total Cost Value', 'business-report' ),
			'expected_profit'    => __( 'Expected Profit', 'business-report' ),
		];
	}

	/**
	 * Define which columns are sortable.
	 *
	 * @return array
	 */
	public function get_sortable_columns() {
		return [
			'category_name'      => [ 'category_name', false ],
			'total_products'     => [ 'total_products', false ],
			'total_stock'        => [ 'total_stock', false ],
			'total_sell_value'   => [ 'total_sell_value', false ],
			'total_cost_value'   => [ 'total_cost_value', false ],
			'expected_profit'    => [ 'expected_profit', false ],
		];
	}

    /**
     * Helper function to initialize a category data array.
     */
    private function get_initial_category_data( $term_id, $name ) {
        return [
            'term_id'                => $term_id,
            'category_name'          => $name,
            'total_products'         => 0, // Count of all products in this category
            'total_stock'            => 0, // Sum of stock for managed items
            'total_sell_value'       => 0, // Sum of sell value for managed items
            'total_cost_value'       => 0, // Sum of cost value for managed items
            'expected_profit'        => 0, // Calculated profit for managed items
            'has_managed_stock_item' => false, // Flag
        ];
    }

	/**
	 * Prepare the items for the table to process.
	 * This now processes the data passed from the summary tab function.
	 */
	public function prepare_items() {
		$this->_column_headers = [ $this->get_columns(), [], $this->get_sortable_columns() ];

        $category_summary = [];

        // 1. Get a map of all product categories
        $all_categories = get_terms([
            'taxonomy'   => 'product_cat',
            'hide_empty' => false,
            'fields'     => 'id=>name', // Get as ID => Name pairs
        ]);
        
        // 2. Process inventory data
        if ( ! empty( $this->inventory_data ) ) {
            foreach ( $this->inventory_data as $item ) {
                $category_ids = $item['category_ids'];
                
                if ( empty( $category_ids ) ) {
                    // Handle Uncategorized
                    if ( ! isset( $category_summary[0] ) ) {
                         $category_summary[0] = $this->get_initial_category_data( 0, __('Uncategorized', 'business-report') );
                    }
                    $category_ids = [0]; // Assign to Uncategorized group
                }

                foreach ( $category_ids as $cat_id ) {
                    // If we haven't seen this category yet (e.g., product assigned to a deleted category)
                    // Let's ensure we only process categories that actually exist in $all_categories or is Uncategorized
                    if ( ! isset( $all_categories[ $cat_id ] ) && $cat_id != 0 ) {
                        continue; // Skip this category ID
                    }
                    
                    // Initialize the category in our summary array if it's the first time we see a product for it
                    if ( ! isset( $category_summary[ $cat_id ] ) ) {
                        $cat_name = ( $cat_id == 0 ) ? __('Uncategorized', 'business-report') : $all_categories[ $cat_id ];
                        $category_summary[ $cat_id ] = $this->get_initial_category_data( $cat_id, $cat_name );
                    }

                    // Increment total product count for this category
                    $category_summary[ $cat_id ]['total_products']++;

                    // Check for "Managed Stock" items for summing values
                    if ( $item['manage_stock'] === true && $item['stock_status'] === 'instock' && $item['stock_quantity'] > 0 ) {
                        $category_summary[ $cat_id ]['has_managed_stock_item'] = true;
                        $stock_qty = $item['stock_quantity'];
                        
                        $category_summary[ $cat_id ]['total_stock'] += $stock_qty;
                        $category_summary[ $cat_id ]['total_sell_value'] += $item['price'] * $stock_qty;
                        $category_summary[ $cat_id ]['total_cost_value'] += $item['cost'] * $stock_qty;
                    }
                }
            }
        }

        // 3. Add any remaining categories that have 0 products
        // This ensures ALL categories are shown
        foreach ( $all_categories as $term_id => $name ) {
            if ( ! isset( $category_summary[ $term_id ] ) ) {
                $category_summary[ $term_id ] = $this->get_initial_category_data( $term_id, $name );
            }
        }

        // 4. Final calculation
        foreach ( $category_summary as $cat_id => $data ) {
            // Only calculate profit if there was managed stock
            if ( $data['has_managed_stock_item'] ) {
                $category_summary[ $cat_id ]['expected_profit'] = $data['total_sell_value'] - $data['total_cost_value'];
            }
        }
        
        // Handle sorting
		usort( $category_summary, [ &$this, 'usort_reorder' ] );

		$this->items = $category_summary;
	}

	/**
	 * Render the 'category_name' column.
	 *
	 * @param array $item
	 * @return string
	 */
	public function column_category_name( $item ) {
		return '<strong>' . esc_html( $item['category_name'] ) . '</strong>';
	}

	/**
	 * Render other columns.
	 */
	public function column_default( $item, $column_name ) {
        
        $has_managed_stock = $item['has_managed_stock_item'];
        $has_products = $item['total_products'] > 0;

		switch ( $column_name ) {
			case 'total_products':
				return number_format_i18n( $item[ $column_name ] );
            
            case 'total_stock':
                // Show '∞' if category has products but none are managed
                if ( $has_products && ! $has_managed_stock ) {
                    return '∞';
                }
                // Show 0 if no products OR if managed stock items sum to 0
                return number_format_i18n( $item[ $column_name ] );

			case 'total_sell_value':
			case 'total_cost_value':
			case 'expected_profit':
                // Show 'Unavailable' if category has products but none are managed
                if ( $has_products && ! $has_managed_stock ) {
                    return 'Unavailable';
                }
                // Show 0 (as wc_price) if no products OR if managed stock values sum to 0
				return wc_price( $item[ $column_name ] );
			
            default:
				return print_r( $item, true );
		}
	}

    /**
     * Message to display when no items are found
     */
    public function no_items() {
        _e( 'No product categories found.', 'business-report' );
    }

	/**
	 * Allows for sorting of data.
	 */
	private function usort_reorder( $a, $b ) {
		$orderby = ( ! empty( $_GET['orderby'] ) ) ? $_GET['orderby'] : 'category_name';
		$order   = ( ! empty( $_GET['order'] ) ) ? $_GET['order'] : 'asc';
        
        // Handle numeric sorting for all columns except name
        if ( $orderby !== 'category_name' ) {
            // Special handling for 'Unavailable' or '∞'
            $a_val = $a[ $orderby ];
            $b_val = $b[ $orderby ];
            
            if ( ! is_numeric( $a_val ) ) $a_val = -1; // Treat 'Unavailable' / '∞' as -1 for sorting
            if ( ! is_numeric( $b_val ) ) $b_val = -1;

            $result = $a_val - $b_val;
        } else {
            // Default string sorting
            $result  = strcmp( $a[ $orderby ], $b[ $orderby ] );
        }

		return ( 'asc' === $order ) ? $result : -$result;
	}
}

