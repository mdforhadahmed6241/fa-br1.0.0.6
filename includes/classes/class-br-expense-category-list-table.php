<?php
/**
 * Creates the WP_List_Table for displaying expense categories with totals.
 */

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class BR_Expense_Category_List_Table extends WP_List_Table {

	public function __construct() {
		parent::__construct( [
			'singular' => 'Expense Category',
			'plural'   => 'Expense Categories',
			'ajax'     => false,
		] );
	}

	public function get_columns() {
		return [
			'category_name' => __( 'Category Name', 'business-report' ),
			'total_cost'    => __( 'Total Cost', 'business-report' ),
            'actions'       => __( 'Actions', 'business-report' ),
		];
	}

	public function prepare_items() {
		global $wpdb;
		$this->_column_headers = [ $this->get_columns(), [], [] ];

        $current_range_key = isset($_GET['range']) ? sanitize_key($_GET['range']) : 'this_month';
        $start_date_get = isset($_GET['start_date']) ? sanitize_text_field($_GET['start_date']) : null;
		$end_date_get = isset($_GET['end_date']) ? sanitize_text_field($_GET['end_date']) : null;
		$is_custom_range = !empty($start_date_get) && !empty($end_date_get);
		$date_range = br_get_date_range($is_custom_range ? '' : $current_range_key, $start_date_get, $end_date_get);
        
        $expenses_table = $wpdb->prefix . 'br_expenses';
        $categories_table = $wpdb->prefix . 'br_expense_categories';

		$this->items = $wpdb->get_results( $wpdb->prepare( "
            SELECT c.id, c.name AS category_name, SUM(e.amount) AS total_cost
            FROM {$categories_table} c
            LEFT JOIN {$expenses_table} e ON c.id = e.category_id AND e.expense_date BETWEEN %s AND %s
            GROUP BY c.id, c.name
            ORDER BY total_cost DESC
        ", $date_range['start'], $date_range['end'] ), ARRAY_A );
	}

	public function column_default( $item, $column_name ) {
		switch ( $column_name ) {
			case 'category_name':
				return esc_html( $item['category_name'] );
			case 'total_cost':
				return wc_price( $item['total_cost'] ?? 0 );
            case 'actions':
                // Prevent deleting "Uncategorized" (usually ID 1 or name 'Uncategorized')
                $is_uncategorized = (strtolower($item['category_name']) === 'uncategorized');
                
                $buttons = sprintf(
                    '<button class="button br-edit-category-btn" data-id="%d">Edit</button>',
                    $item['id']
                );

                if (!$is_uncategorized) {
                    $buttons .= sprintf(
                        '<button class="button-link-delete br-delete-category-btn" data-id="%d"><span class="dashicons dashicons-trash"></span></button>',
                        $item['id']
                    );
                }
                
                // Added wrapper div for consistent styling with global CSS
                return '<div class="br-action-buttons">' . $buttons . '</div>';
			default:
				return '';
		}
	}

    public function no_items() {
        _e('No expense categories found.', 'business-report');
    }
}