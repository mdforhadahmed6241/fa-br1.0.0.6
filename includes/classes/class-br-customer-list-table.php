<?php
/**
 * Creates the WP_List_Table for displaying customer data grouped by phone number.
 *
 * @package BusinessReport
 */

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class BR_Customer_List_Table extends WP_List_Table {

	public function __construct() {
		parent::__construct( [
			'singular' => 'Customer',
			'plural'   => 'Customers',
			'ajax'     => false,
		] );
	}

	public function get_columns() {
		return [
			'customer_info' => __( 'Customer', 'business-report' ),
			'total_orders'  => __( 'Orders', 'business-report' ),
			'success_rate'  => __( 'Success Rate', 'business-report' ),
			'total_revenue' => __( 'Revenue', 'business-report' ),
			'total_profit'  => __( 'Profit', 'business-report' ),
			'return_rate'   => __( 'Return Rate', 'business-report' ),
			'first_seen'    => __( 'First Seen', 'business-report' ),
			'last_active'   => __( 'Last Active', 'business-report' ),
			'status'        => __( 'Status', 'business-report' ),
		];
	}

	public function get_sortable_columns() {
		return [
			'total_orders'  => [ 'total_orders', false ],
			'total_revenue' => [ 'total_revenue', false ],
			'total_profit'  => [ 'total_profit', false ],
			'last_active'   => [ 'last_active', false ],
            'first_seen'    => [ 'first_seen', false ],
		];
	}

	public function prepare_items() {
		global $wpdb;
		$this->_column_headers = [ $this->get_columns(), [], $this->get_sortable_columns() ];

		$per_page     = 20;
		$current_page = $this->get_pagenum();
        
        $current_range_key = isset($_GET['range']) ? sanitize_key($_GET['range']) : 'today';
        $start_date_get = isset($_GET['start_date']) ? sanitize_text_field($_GET['start_date']) : null;
        $end_date_get = isset($_GET['end_date']) ? sanitize_text_field($_GET['end_date']) : null;
        $is_custom_range = !empty($start_date_get) && !empty($end_date_get);
        
        if (function_exists('br_get_date_range')) {
            $date_range = br_get_date_range($is_custom_range ? '' : $current_range_key, $start_date_get, $end_date_get);
        } else {
            $date_range = ['start' => date('Y-m-d'), 'end' => date('Y-m-d')];
        }

        $table_name = $wpdb->prefix . 'br_orders';
        
        // PHONE NORMALIZATION: Group by last 11 digits
        $phone_group = "RIGHT(customer_phone, 11)";

        // QUERY: Aggregate metrics
        // total_orders: Count ONLY converted orders (is_converted = 1)
        // all_attempts: Count ALL rows (for success rate calc)
        $sql = "SELECT 
                    $phone_group as phone_key,
                    MAX(customer_name) as customer_name,
                    MAX(customer_phone) as customer_phone,
                    MAX(customer_email) as customer_email,
                    SUM(CASE WHEN is_converted = 1 THEN 1 ELSE 0 END) as total_orders,
                    COUNT(id) as all_attempts, 
                    SUM(CASE WHEN is_converted = 1 THEN total_value ELSE 0 END) as total_revenue,
                    SUM(CASE WHEN is_converted = 1 THEN net_profit ELSE 0 END) as total_profit,
                    SUM(CASE WHEN courier_status IN (1, 2) THEN 1 ELSE 0 END) as returned_orders,
                    MIN(order_date) as first_seen,
                    MAX(order_date) as last_active
                FROM {$table_name}
                WHERE order_date BETWEEN %s AND %s
                AND customer_phone IS NOT NULL AND customer_phone != ''
        ";
        
        $params = [$date_range['start'] . ' 00:00:00', $date_range['end'] . ' 23:59:59'];

        if ( ! empty( $_REQUEST['s'] ) ) {
            $search = '%' . $wpdb->esc_like( sanitize_text_field( $_REQUEST['s'] ) ) . '%';
            $sql .= " AND (customer_name LIKE %s OR customer_phone LIKE %s OR customer_email LIKE %s)";
            $params[] = $search; $params[] = $search; $params[] = $search;
        }

        $sql .= " GROUP BY $phone_group";

        $orderby = ( ! empty( $_REQUEST['orderby'] ) ) ? sanitize_sql_orderby( $_REQUEST['orderby'] ) : 'total_profit'; 
		$order   = ( ! empty( $_REQUEST['order'] ) ) ? sanitize_sql_orderby( $_REQUEST['order'] ) : 'DESC';
        $sql .= " ORDER BY {$orderby} {$order}";

        $offset = ( $current_page - 1 ) * $per_page;
        
        // Count total groups (inefficient but necessary for WP_List_Table pagination on grouped data)
        $count_results = $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A);
		$total_items = count($count_results);

        // Pagination Limit
        $sql_limit = $sql . " LIMIT %d, %d";
        $params_limit = array_merge($params, [$offset, $per_page]);
        
        $this->items = $wpdb->get_results($wpdb->prepare($sql_limit, $params_limit), ARRAY_A);

		$this->set_pagination_args( [
			'total_items' => $total_items,
			'per_page'    => $per_page,
		] );
	}

	public function column_customer_info( $item ) {
        $name = !empty($item['customer_name']) ? esc_html($item['customer_name']) : 'Unknown';
        $phone = esc_html($item['customer_phone']);
        $email = !empty($item['customer_email']) ? esc_html($item['customer_email']) : '';
		return sprintf(
            '<strong>%s</strong><br><span style="color:#666;">%s</span><br>%s', 
            $name, $phone, $email
        );
	}

    public function column_default( $item, $column_name ) {
        switch ( $column_name ) {
            case 'total_orders':
                return number_format_i18n($item['total_orders']);
            
            case 'success_rate':
                $attempts = $item['all_attempts'];
                $rate = ($attempts > 0) ? ($item['total_orders'] / $attempts) * 100 : 0;
                return number_format_i18n($rate, 1) . '%';

            case 'total_revenue':
                return wc_price($item['total_revenue']);

            case 'total_profit':
                $profit = $item['total_profit'];
                $color = ($profit >= 0) ? '#057A55' : '#E02424';
                return '<span style="color:' . $color . '; font-weight:bold;">' . wc_price($profit) . '</span>';

            case 'return_rate':
                // Return rate usually calculated against Total Orders (Converted) or All Attempts?
                // Using Total Converted Orders as base for return rate is standard for e-commerce performance
                $base = $item['total_orders']; 
                $rate = ($base > 0) ? ($item['returned_orders'] / $base) * 100 : 0;
                $style = ($rate > 20) ? 'color:#E02424;' : '';
                return '<span style="' . $style . '">' . number_format_i18n($rate, 1) . '%</span>';

            case 'first_seen':
                return $item['first_seen'] ? date_i18n(get_option('date_format'), strtotime($item['first_seen'])) : '-';

            case 'last_active':
                return $item['last_active'] ? date_i18n(get_option('date_format'), strtotime($item['last_active'])) : '-';

            case 'status':
                return $this->get_customer_status_badge($item);

            default:
                return print_r( $item, true );
        }
    }

    private function get_customer_status_badge($item) {
        $badges = [];
        $profit = (float) $item['total_profit'];
        $orders = (int) $item['total_orders'];
        $last_active = strtotime($item['last_active']);
        $days_inactive = (time() - $last_active) / (60 * 60 * 24);

        if ($profit > 5000) {
            $badges[] = '<span style="background:#DEF7EC; color:#03543F; padding:2px 6px; border-radius:4px; font-size:11px;">VIP</span>';
        }
        
        if ($orders > 2) {
            $badges[] = '<span style="background:#E1EFFE; color:#1E429F; padding:2px 6px; border-radius:4px; font-size:11px;">Regular</span>';
        } elseif ($orders === 1) {
            $badges[] = '<span style="background:#F3F4F6; color:#374151; padding:2px 6px; border-radius:4px; font-size:11px;">New</span>';
        }

        if ($days_inactive > 60) {
            $badges[] = '<span style="background:#FDE8E8; color:#9B1C1C; padding:2px 6px; border-radius:4px; font-size:11px;">At Risk</span>';
        }

        return implode(' ', $badges);
    }
}