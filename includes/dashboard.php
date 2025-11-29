<?php
/**
 * Business Report Dashboard
 * Handles the main Business Summary page.
 *
 * @package BusinessReport
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Renders the main Dashboard Page.
 */
function br_render_dashboard_page() {
    // Check permissions
    if ( ! current_user_can( 'manage_woocommerce' ) ) {
        return;
    }

    // 1. Get Date Range Logic (FIXED)
    $current_range_key = isset($_GET['range']) ? sanitize_key($_GET['range']) : 'today';
    
    // FIX: Only look at start/end date inputs if the user specifically clicked "custom" (the 'Go' button)
    // This prevents preset buttons (Today, 7D, etc.) from being overridden by the hidden input values.
    if ( $current_range_key === 'custom' ) {
        $start_date_get = isset($_GET['start_date']) ? sanitize_text_field($_GET['start_date']) : null;
        $end_date_get   = isset($_GET['end_date']) ? sanitize_text_field($_GET['end_date']) : null;
    } else {
        $start_date_get = null;
        $end_date_get   = null;
    }

    $is_custom_range = !empty($start_date_get) && !empty($end_date_get);

    // Use the existing helper from meta-ads.php or define fallback
    if (function_exists('br_get_date_range')) {
        // If preset, we pass empty inputs to force the helper to use the range key
        $date_range = br_get_date_range($is_custom_range ? '' : $current_range_key, $start_date_get, $end_date_get);
    } else {
        // Fallback if helper not loaded
        $date_range = ['start' => date('Y-m-d'), 'end' => date('Y-m-d')];
    }

    $start_date = $date_range['start'];
    $end_date = $date_range['end'];

    // 2. Fetch Data
    $stats = br_get_dashboard_stats($start_date, $end_date);
    $charts = br_get_dashboard_charts_data(); // Always last 30 days
    $top_products = br_get_top_selling_products($start_date, $end_date);

    ?>
    <div class="wrap br-wrap br-dashboard-wrap">
        <div class="br-header-section">
            <h1 class="br-dash-title"><?php _e('Business Summary', 'business-report'); ?></h1>
            
            <!-- Filter Section -->
            <form method="GET" class="br-dash-filters">
                <input type="hidden" name="page" value="business-report">
                <?php
                $ranges = [
                    'today' => 'Today',
                    'yesterday' => 'Yesterday',
                    'last_7_days' => '7D',
                    'last_30_days' => '30D',
                    'this_month' => 'This Month'
                ];
                foreach ($ranges as $key => $label) {
                    // Check active status strictly against the range key
                    $active = ($current_range_key === $key) ? 'active' : '';
                    echo '<button type="submit" name="range" value="' . esc_attr($key) . '" class="br-filter-btn ' . esc_attr($active) . '">' . esc_html($label) . '</button>';
                }
                ?>
                <!-- Simple Custom Date Inputs -->
                <div class="br-custom-date-inputs">
                    <input type="date" name="start_date" value="<?php echo esc_attr($start_date); ?>" placeholder="Start">
                    <input type="date" name="end_date" value="<?php echo esc_attr($end_date); ?>" placeholder="End">
                    <button type="submit" class="br-filter-btn" name="range" value="custom">Go</button>
                </div>
            </form>
        </div>

        <!-- Section 1: Order Summary -->
        <div class="br-section-title"><?php _e('Order Summary', 'business-report'); ?></div>
        <div class="br-kpi-row">
            <?php br_render_kpi_card('Order Place', $stats['total_orders'], '', 'no-bg'); ?>
            <?php br_render_kpi_card('Total Confirm', $stats['confirmed_orders'], '', 'no-bg'); ?>
            <?php br_render_kpi_card('Total Cancel', $stats['cancelled_orders'], '', 'no-bg'); ?>
            <?php br_render_kpi_card('Total Delivered', $stats['delivered_orders'], '', 'no-bg'); ?>
            <?php br_render_kpi_card('Total Return', $stats['returned_orders'], '', 'no-bg'); ?>
        </div>

        <div class="br-dashboard-grid-2-1">
            <!-- Chart: Last 30 Days Orders -->
            <div class="br-card br-chart-card">
                <h4><?php _e('Last 30 Days Orders', 'business-report'); ?></h4>
                <div class="br-chart-wrapper">
                    <canvas id="br-orders-chart"></canvas>
                </div>
            </div>
            <!-- Table: Top Selling -->
            <div class="br-card br-table-card">
                <h4><?php _e('Top Selling Products', 'business-report'); ?></h4>
                <table class="br-dash-table">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Sold</th>
                            <th>Revenue</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(!empty($top_products)): foreach($top_products as $prod): ?>
                        <tr>
                            <td>
                                <div class="br-prod-info">
                                    <?php echo $prod['image']; ?>
                                    <span><?php echo esc_html(mb_strimwidth($prod['name'], 0, 20, '...')); ?></span>
                                </div>
                            </td>
                            <td><?php echo esc_html($prod['qty']); ?></td>
                            <td><?php echo wc_price($prod['revenue']); ?></td>
                        </tr>
                        <?php endforeach; else: ?>
                        <tr><td colspan="3">No sales in this period.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Section 2: Ads Summary -->
        <div class="br-section-title"><?php _e('Ads Summary', 'business-report'); ?></div>
        <div class="br-kpi-row">
            <?php br_render_kpi_card('Order Place', $stats['total_orders'], '', 'white-bg'); ?>
            <?php br_render_kpi_card('Ads Cost/Order', $stats['ad_cost_per_order'], 'currency', 'white-bg'); ?>
            <?php br_render_kpi_card('Ads Cost/Confirm Order', $stats['ad_cost_per_confirmed'], 'currency', 'white-bg'); ?>
            <?php br_render_kpi_card('ROAS', $stats['roas'], 'decimal', 'white-bg', 'x'); ?>
            <?php br_render_kpi_card('ROI', $stats['roi'], 'percent', 'white-bg'); ?>
        </div>

        <!-- Section 3: Financial Summary -->
        <div class="br-section-title"><?php _e('Financial Summary', 'business-report'); ?></div>
        <div class="br-kpi-row">
            <?php br_render_kpi_card('Total Revenue', $stats['total_revenue'], 'currency', 'white-bg'); ?>
            <?php br_render_kpi_card('Total Profit', $stats['gross_profit'], 'currency', 'white-bg'); ?>
            <?php br_render_kpi_card('Total Expense', $stats['total_expenses_all'], 'currency', 'white-bg'); ?>
            <?php br_render_kpi_card('Total Ad Cost', $stats['total_ad_spend'], 'currency', 'white-bg'); ?>
            <?php br_render_kpi_card('Net Margin', $stats['net_margin'], 'percent', 'white-bg'); ?>
        </div>

        <div class="br-dashboard-grid-2-1">
            <!-- Chart: Profit vs Ad Cost -->
            <div class="br-card br-chart-card">
                <h4><?php _e('Last 30 days Profit vs Ad Cost', 'business-report'); ?></h4>
                <div class="br-chart-wrapper">
                    <canvas id="br-profit-ad-chart"></canvas>
                </div>
            </div>
            <!-- Table: Profit & Loss -->
            <div class="br-card br-table-card">
                <h4><?php _e('Profit & Loss', 'business-report'); ?></h4>
                <table class="br-dash-table br-pnl-table">
                    <thead>
                        <tr>
                            <th>LINE ITEM</th>
                            <th class="text-right">AMOUNT</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Gross Revenue</td>
                            <td class="text-right"><?php echo wc_price($stats['total_revenue']); ?></td>
                        </tr>
                        <tr>
                            <td>Cost of Goods Sold</td>
                            <td class="text-right text-red">-<?php echo wc_price($stats['total_cogs']); ?></td>
                        </tr>
                        <tr>
                            <td>Marketing Spend (Ads)</td>
                            <td class="text-right text-red">-<?php echo wc_price($stats['total_ad_spend']); ?></td>
                        </tr>
                        <tr>
                            <td>Shipping Costs</td>
                            <td class="text-right text-red">-<?php echo wc_price($stats['total_shipping']); ?></td>
                        </tr>
                        <!-- Added Discount Row -->
                        <tr>
                            <td>Discount</td>
                            <td class="text-right text-red">-<?php echo wc_price($stats['total_discount']); ?></td>
                        </tr>
                        <tr>
                            <td>Return Cost (Courier)</td>
                            <td class="text-right text-red">-<?php echo wc_price($stats['return_courier_cost']); ?></td>
                        </tr>
                        <tr>
                            <td>Operational Expenses</td>
                            <td class="text-right text-red">-<?php echo wc_price($stats['operational_expenses']); ?></td>
                        </tr>
                        <tr class="br-total-row">
                            <td><strong>True Net Profit</strong></td>
                            <td class="text-right"><strong><?php echo wc_price($stats['true_net_profit']); ?></strong></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    <!-- Pass Chart Data to JS -->
    <script>
        var br_dashboard_charts = {
            orders: <?php echo json_encode($charts['orders']); ?>,
            profit_ad: <?php echo json_encode($charts['profit_ad']); ?>
        };
    </script>
    <?php
}

/**
 * Helper to fetch all stats based on date range.
 */
function br_get_dashboard_stats($start_date, $end_date) {
    global $wpdb;
    
    // Tables
    $t_orders = $wpdb->prefix . 'br_orders';
    $t_ads = $wpdb->prefix . 'br_meta_ad_summary';
    $t_accounts = $wpdb->prefix . 'br_meta_ad_accounts';
    $t_expenses = $wpdb->prefix . 'br_expenses';

    // 1. Order Stats
    // Map 'Confirm' to Converted status set in settings
    // Map 'Cancel' to wc-cancelled, wc-failed, wc-refunded
    $orders_query = $wpdb->get_row($wpdb->prepare(
        "SELECT 
            COUNT(id) as total,
            SUM(CASE WHEN is_converted = 1 THEN 1 ELSE 0 END) as confirmed,
            SUM(CASE WHEN order_status IN ('wc-cancelled', 'wc-failed', 'wc-refunded', 'wc-trash') THEN 1 ELSE 0 END) as cancelled,
            SUM(CASE WHEN courier_status = 0 THEN 1 ELSE 0 END) as delivered,
            SUM(CASE WHEN courier_status IN (1, 2) THEN 1 ELSE 0 END) as returned,
            SUM(total_value) as revenue,  /* UPDATED: Using total_value (Grand Total) */
            SUM(cogs_total) as cogs,
            SUM(shipping_cost) as shipping,
            SUM(discount) as discount     /* Added Discount Calculation */
         FROM $t_orders 
         WHERE order_date BETWEEN %s AND %s",
         $start_date . ' 00:00:00', 
         $end_date . ' 23:59:59'
    ));

    $total_orders = $orders_query->total ?? 0;
    $confirmed_orders = $orders_query->confirmed ?? 0;
    $cancelled_orders = $orders_query->cancelled ?? 0;
    $delivered_orders = $orders_query->delivered ?? 0;
    $returned_orders = $orders_query->returned ?? 0;
    $revenue = $orders_query->revenue ?? 0;
    $cogs = $orders_query->cogs ?? 0;
    $shipping = $orders_query->shipping ?? 0;
    $discount = $orders_query->discount ?? 0; // Get discount

    // 2. Ad Stats
    $ads_query = $wpdb->get_row($wpdb->prepare(
        "SELECT SUM(s.spend_usd * a.usd_to_bdt_rate) as total_spend, SUM(s.purchase_value) as val 
         FROM $t_ads s 
         JOIN $t_accounts a ON s.account_fk_id = a.id 
         WHERE s.report_date BETWEEN %s AND %s",
         $start_date, $end_date
    ));
    $ad_spend = $ads_query->total_spend ?? 0;
    $ad_revenue = $ads_query->val ?? 0; // Value tracked by pixel (optional usage)

    // 3. Operational Expenses
    $expenses_query = $wpdb->get_var($wpdb->prepare(
        "SELECT SUM(amount) FROM $t_expenses WHERE expense_date BETWEEN %s AND %s",
        $start_date, $end_date
    ));
    $op_expenses = $expenses_query ?? 0;

    // 4. Return Courier Cost Calculation
    $return_charge = get_option('br_courier_return_cost', 0);
    $return_courier_cost = $returned_orders * floatval($return_charge);

    // 5. Calculations
    $gross_profit = $revenue - $cogs; // Standard Gross Profit (Revenue - COGS)
    
    // "Total Expense" Calculation
    // Updated to include Discount as per request:
    // Total Expense = Ad Spend + Op Expenses + Return Cost + Shipping Cost + Discount
    $total_expenses_all = $ad_spend + $op_expenses + $return_courier_cost + $shipping + $discount;

    $true_net_profit = $gross_profit - $total_expenses_all; // Revenue - COGS - All Expenses

    $ad_cost_per_order = ($total_orders > 0) ? $ad_spend / $total_orders : 0;
    $ad_cost_per_confirmed = ($confirmed_orders > 0) ? $ad_spend / $confirmed_orders : 0;
    $roas = ($ad_spend > 0) ? $revenue / $ad_spend : 0; // Revenue based ROAS
    
    // ROI = (Net Profit / Cost of Investment) * 100
    // Investment = COGS + Ad Spend + Expenses + Discount
    $total_investment = $cogs + $total_expenses_all;
    $roi = ($total_investment > 0) ? ($true_net_profit / $total_investment) * 100 : 0;

    $net_margin = ($revenue > 0) ? ($true_net_profit / $revenue) * 100 : 0;

    return [
        'total_orders' => $total_orders,
        'confirmed_orders' => $confirmed_orders,
        'cancelled_orders' => $cancelled_orders,
        'delivered_orders' => $delivered_orders,
        'returned_orders' => $returned_orders,
        'ad_cost_per_order' => $ad_cost_per_order,
        'ad_cost_per_confirmed' => $ad_cost_per_confirmed,
        'roas' => $roas,
        'roi' => $roi,
        'total_revenue' => $revenue,
        'gross_profit' => $gross_profit, // For KPI
        'total_cogs' => $cogs,
        'total_shipping' => $shipping,
        'total_discount' => $discount, // Pass discount to array
        'total_ad_spend' => $ad_spend,
        'operational_expenses' => $op_expenses,
        'return_courier_cost' => $return_courier_cost,
        'total_expenses_all' => $total_expenses_all,
        'true_net_profit' => $true_net_profit,
        'net_margin' => $net_margin
    ];
}

/**
 * Helper to get fixed 30-day chart data.
 */
function br_get_dashboard_charts_data() {
    global $wpdb;
    $t_orders = $wpdb->prefix . 'br_orders';
    $t_ads = $wpdb->prefix . 'br_meta_ad_summary';
    $t_accounts = $wpdb->prefix . 'br_meta_ad_accounts';

    // Last 30 days fixed
    $end = date('Y-m-d');
    $start = date('Y-m-d', strtotime('-29 days'));

    // 1. Orders Chart Data
    $orders_daily = $wpdb->get_results($wpdb->prepare(
        "SELECT DATE(order_date) as date, COUNT(id) as count 
         FROM $t_orders 
         WHERE order_date BETWEEN %s AND %s 
         GROUP BY DATE(order_date)", 
         $start . ' 00:00:00', $end . ' 23:59:59'
    ));
    
    // 2. Profit vs Ad Cost Data
    // We need daily Gross Profit, Daily Ad Spend.
    // Daily Net = Gross - Ad Spend (Approximation for chart)
    $finance_daily = $wpdb->get_results($wpdb->prepare(
        "SELECT DATE(order_date) as date, SUM(gross_profit) as gross 
         FROM $t_orders 
         WHERE order_date BETWEEN %s AND %s 
         GROUP BY DATE(order_date)", 
         $start . ' 00:00:00', $end . ' 23:59:59'
    ));

    $ads_daily = $wpdb->get_results($wpdb->prepare(
        "SELECT s.report_date as date, SUM(s.spend_usd * a.usd_to_bdt_rate) as spend 
         FROM $t_ads s 
         JOIN $t_accounts a ON s.account_fk_id = a.id 
         WHERE s.report_date BETWEEN %s AND %s 
         GROUP BY s.report_date",
         $start, $end
    ));

    // Map to date keys
    $dates = [];
    $order_data = [];
    $profit_data = [];
    $ad_data = [];

    $period = new DatePeriod(new DateTime($start), new DateInterval('P1D'), new DateTime($end . ' +1 day'));
    
    $order_map = []; foreach($orders_daily as $o) $order_map[$o->date] = $o->count;
    $gross_map = []; foreach($finance_daily as $f) $gross_map[$f->date] = $f->gross;
    $ads_map = []; foreach($ads_daily as $a) $ads_map[$a->date] = $a->spend;

    foreach ($period as $dt) {
        $d = $dt->format('Y-m-d');
        $dates[] = $dt->format('d M');
        $order_data[] = $order_map[$d] ?? 0;
        
        $g = $gross_map[$d] ?? 0;
        $a = $ads_map[$d] ?? 0;
        $profit_data[] = $g - $a; // Net for chart (Gross - Ads)
        $ad_data[] = $a;
    }

    return [
        'orders' => ['labels' => $dates, 'data' => $order_data],
        'profit_ad' => ['labels' => $dates, 'profit' => $profit_data, 'ads' => $ad_data]
    ];
}

/**
 * Helper to get Top Selling Products from WC Lookup Tables (Better Performance).
 */
function br_get_top_selling_products($start, $end) {
    global $wpdb;
    // Uses standard WC lookup table if available (WC 4.0+)
    $table = $wpdb->prefix . 'wc_order_product_lookup';
    
    // Check if table exists, else fallback or return empty
    if($wpdb->get_var("SHOW TABLES LIKE '$table'") != $table) return [];

    $results = $wpdb->get_results($wpdb->prepare(
        "SELECT product_id, SUM(product_qty) as qty, SUM(product_net_revenue) as revenue 
         FROM $table 
         WHERE date_created BETWEEN %s AND %s 
         GROUP BY product_id 
         ORDER BY qty DESC 
         LIMIT 5",
         $start . ' 00:00:00', $end . ' 23:59:59'
    ));

    $data = [];
    foreach($results as $row) {
        $product = wc_get_product($row->product_id);
        if(!$product) continue;
        
        $image = $product->get_image([40, 40]);
        $data[] = [
            'name' => $product->get_name(),
            'image' => $image ? $image : '<span class="br-no-img"></span>',
            'qty' => $row->qty,
            'revenue' => $row->revenue
        ];
    }
    return $data;
}

/**
 * HTML Helper for KPI Card
 */
function br_render_kpi_card($title, $value, $format = '', $bg_class = '', $suffix = '') {
    $formatted = $value;
    if ($format === 'currency') $formatted = wc_price($value);
    elseif ($format === 'percent') $formatted = number_format($value, 0) . '%';
    elseif ($format === 'decimal') $formatted = number_format($value, 2);
    else $formatted = number_format_i18n($value);

    echo '<div class="br-dash-kpi ' . esc_attr($bg_class) . '">';
    echo '<h4>' . esc_html($title) . '</h4>';
    echo '<div class="br-kpi-val">' . $formatted . esc_html($suffix) . '</div>';
    echo '</div>';
}