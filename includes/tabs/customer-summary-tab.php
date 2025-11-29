<?php
/**
 * Customer Report - Summary Tab (Dashboard)
 *
 * @package BusinessReport
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

function br_customer_summary_tab_html() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'br_orders';

    // 1. Date Range Logic
    $current_range_key = isset($_GET['range']) ? sanitize_key($_GET['range']) : 'today';
    $start_date_get = isset($_GET['start_date']) ? sanitize_text_field($_GET['start_date']) : null;
    $end_date_get = isset($_GET['end_date']) ? sanitize_text_field($_GET['end_date']) : null;
    $is_custom_range = !empty($start_date_get) && !empty($end_date_get);

    if (function_exists('br_get_date_range')) {
        $date_range = br_get_date_range($is_custom_range ? '' : $current_range_key, $start_date_get, $end_date_get);
    } else {
        $date_range = ['start' => date('Y-m-d'), 'end' => date('Y-m-d')];
    }
    $start_date_sql = $date_range['start'] . ' 00:00:00';
    $end_date_sql = $date_range['end'] . ' 23:59:59';

    // --- RENDER FILTERS ---
    if (function_exists('br_customer_render_filters')) {
        br_customer_render_filters('summary');
    }

    // --- SQL HELPERS ---
    // Normalize phone: take last 11 digits to merge 88017... and 017...
    // We use this in WHERE/GROUP BY clauses
    $phone_col = "RIGHT(customer_phone, 11)";

    // 2. Fetch KPI Data (Strictly Converted Orders Only)
    
    // KPI: Total Active Customers (Unique Normalized Phones with Converted Orders in range)
    $total_customers = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(DISTINCT $phone_col) 
         FROM {$table_name} 
         WHERE order_date BETWEEN %s AND %s 
         AND is_converted = 1 
         AND customer_phone IS NOT NULL AND customer_phone != ''",
        $start_date_sql, $end_date_sql
    ));

    // KPI: Total Revenue, Profit, Count (Converted orders only)
    $financials = $wpdb->get_row($wpdb->prepare(
        "SELECT 
            SUM(total_value) as revenue, 
            SUM(net_profit) as profit,
            COUNT(id) as total_orders 
         FROM {$table_name} 
         WHERE order_date BETWEEN %s AND %s AND is_converted = 1",
        $start_date_sql, $end_date_sql
    ));
    $total_revenue = $financials->revenue ?? 0;
    $total_profit = $financials->profit ?? 0;
    $total_orders_period = $financials->total_orders ?? 0;

    // KPI: New Customers (First converted order date is within range)
    $new_customers = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM (
            SELECT $phone_col as phone, MIN(order_date) as first_order 
            FROM {$table_name} 
            WHERE customer_phone IS NOT NULL AND customer_phone != '' AND is_converted = 1
            GROUP BY $phone_col
            HAVING first_order BETWEEN %s AND %s
        ) as new_cust_table",
        $start_date_sql, $end_date_sql
    ));

    // KPI: Historical Avg CLTV (Lifetime Value)
    // Formula: Total Lifetime Revenue / Total Lifetime Unique Customers
    // This is the most robust "Ecommerce Friendly" metric.
    $lifetime_stats = $wpdb->get_row(
        "SELECT 
            SUM(total_value) as lifetime_rev,
            COUNT(DISTINCT RIGHT(customer_phone, 11)) as lifetime_cust
         FROM {$table_name} 
         WHERE is_converted = 1 
         AND customer_phone IS NOT NULL AND customer_phone != ''"
    );
    $lifetime_rev = $lifetime_stats->lifetime_rev ?? 0;
    $lifetime_cust = $lifetime_stats->lifetime_cust ?? 0;
    
    $avg_cltv = ($lifetime_cust > 0) ? ($lifetime_rev / $lifetime_cust) : 0;

    // KPI: Avg Order Value (Period)
    $aov = ($total_orders_period > 0) ? $total_revenue / $total_orders_period : 0;

    // 3. Fetch Chart Data: Top 5 Customers by Profit (Normalized Phone)
    $top_customers = $wpdb->get_results($wpdb->prepare(
        "SELECT MAX(customer_name) as name, SUM(net_profit) as total_profit 
         FROM {$table_name} 
         WHERE order_date BETWEEN %s AND %s 
         AND is_converted = 1
         AND customer_phone IS NOT NULL AND customer_phone != ''
         GROUP BY $phone_col 
         ORDER BY total_profit DESC 
         LIMIT 5",
        $start_date_sql, $end_date_sql
    ));

    $chart_labels = [];
    $chart_data = [];
    foreach ($top_customers as $cust) {
        $chart_labels[] = $cust->name ?: 'Unknown';
        $chart_data[] = $cust->total_profit;
    }
    $chart_data_json = json_encode(['labels' => $chart_labels, 'data' => $chart_data]);

    ?>

    <!-- KPI Grid -->
    <div class="br-kpi-grid">
        <div class="br-kpi-card">
            <h4><?php _e('Active Customers', 'business-report'); ?></h4>
            <p><?php echo number_format_i18n($total_customers); ?></p>
        </div>
        <div class="br-kpi-card">
            <h4><?php _e('New Customers', 'business-report'); ?></h4>
            <p><?php echo number_format_i18n($new_customers); ?></p>
        </div>
        <div class="br-kpi-card">
            <h4><?php _e('Total Revenue', 'business-report'); ?></h4>
            <p><?php echo wc_price($total_revenue); ?></p>
        </div>
        <div class="br-kpi-card">
            <h4><?php _e('Total Profit', 'business-report'); ?></h4>
            <p><?php echo wc_price($total_profit); ?></p>
        </div>
        <div class="br-kpi-card">
            <h4><?php _e('Avg Customer Value (LTV)', 'business-report'); ?></h4>
            <p><?php echo wc_price($avg_cltv); ?></p>
            <small style="color:#666; font-size:11px;">(Historical Average)</small>
        </div>
        <div class="br-kpi-card">
            <h4><?php _e('Avg Order Value (AOV)', 'business-report'); ?></h4>
            <p><?php echo wc_price($aov); ?></p>
        </div>
    </div>

    <!-- Chart Section -->
    <div class="br-card" style="margin-top: 20px; padding: 20px; background: #fff; border: 1px solid #d1d5db; border-radius: 8px;">
        <h3><?php _e('Top 5 Customers by Profit (This Period)', 'business-report'); ?></h3>
        <div style="height: 300px; position: relative;">
            <canvas id="br-top-customers-chart"></canvas>
        </div>
    </div>

    <!-- Chart JS Initialization -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const ctx = document.getElementById('br-top-customers-chart');
            if (ctx && typeof Chart !== 'undefined') {
                const chartData = <?php echo $chart_data_json; ?>;
                new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: chartData.labels,
                        datasets: [{
                            label: 'Total Profit',
                            data: chartData.data,
                            backgroundColor: '#490AA3',
                            borderRadius: 4,
                            barThickness: 20
                        }]
                    },
                    options: {
                        indexAxis: 'y',
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: false }
                        },
                        scales: {
                            x: { beginAtZero: true }
                        }
                    }
                });
            }
        });
    </script>
    <?php
}