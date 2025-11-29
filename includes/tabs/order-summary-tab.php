 <?php
 /**
  * Order Report Summary Tab Content
  *
  * @package BusinessReport
  */

 // If this file is called directly, abort.
 if ( ! defined( 'WPINC' ) ) {
 	die;
 }

 /**
  * Renders the HTML for the custom date range modal.
  */
 function br_order_summary_custom_range_filter_modal_html() {
     $start_date = isset($_GET['start_date']) ? sanitize_text_field($_GET['start_date']) : '';
     $end_date = isset($_GET['end_date']) ? sanitize_text_field($_GET['end_date']) : '';
     ?>
     <div id="br-order-custom-range-filter-modal" class="br-modal" style="display: none;">
         <div class="br-modal-content">
             <button class="br-modal-close">&times;</button>
             <h3><?php _e('Select Custom Date Range', 'business-report'); ?></h3>
             <p><?php _e('Filter the report by a specific date range.', 'business-report'); ?></p>
             <form id="br-order-custom-range-filter-form" method="GET">
                 <input type="hidden" name="page" value="br-order-report">
                 <input type="hidden" name="tab" value="summary">
                 <div class="form-row">
                     <div>
                         <label for="br_order_filter_start_date"><?php _e('Start Date', 'business-report'); ?></label>
                         <input type="text" id="br_order_filter_start_date" name="start_date" class="br-datepicker" value="<?php echo esc_attr($start_date); ?>" autocomplete="off" required>
                     </div>
                     <div>
                         <label for="br_order_filter_end_date"><?php _e('End Date', 'business-report'); ?></label>
                         <input type="text" id="br_order_filter_end_date" name="end_date" class="br-datepicker" value="<?php echo esc_attr($end_date); ?>" autocomplete="off" required>
                     </div>
                 </div>
                 <div class="form-footer">
                     <div></div>
                     <div>
                         <button type="button" class="button br-modal-cancel"><?php _e('Cancel', 'business-report'); ?></button>
                         <button type="submit" class="button button-primary"><?php _e('Apply Filter', 'business-report'); ?></button>
                     </div>
                 </div>
             </form>
         </div>
     </div>
 <?php }


 /**
  * Renders the date filter buttons and dropdown.
  */
 function br_order_summary_render_date_filters_html($current_tab = 'summary') {
     $current_range_key = isset($_GET['range']) ? sanitize_key($_GET['range']) : 'today';
     $start_date_get = isset($_GET['start_date']) ? sanitize_text_field($_GET['start_date']) : null;
     $end_date_get = isset($_GET['end_date']) ? sanitize_text_field($_GET['end_date']) : null;
     $is_custom_range = !empty($start_date_get) && !empty($end_date_get);

     $filters_main = ['today' => 'Today', 'yesterday' => 'Yesterday', 'last_7_days' => '7D', 'last_30_days' => '30D'];
     $filters_dropdown = ['this_month' => 'This Month', 'this_year' => 'This Year', 'lifetime' => 'Lifetime', 'custom' => 'Custom Range'];
     ?>
     <div class="br-filters">
         <div class="br-date-filters">
             <?php
             foreach($filters_main as $key => $label) {
                 $is_active = ($current_range_key === $key) && !$is_custom_range;

                 // Set 'Today' as default if no range is set
                 if ( !isset($_GET['range']) && !$is_custom_range && $key === 'today' ) {
                      $is_active = true;
                 }

                 echo sprintf('<a href="?page=br-order-report&tab=%s&range=%s" class="button %s">%s</a>', esc_attr($current_tab), esc_attr($key), $is_active ? 'active' : '', esc_html($label));
             }
             ?>
             <div class="br-dropdown">
                 <button class="button br-dropdown-toggle <?php echo ($is_custom_range || in_array($current_range_key, array_keys($filters_dropdown))) ? 'active' : ''; ?>">...</button>
                 <div class="br-dropdown-menu">
                     <?php
                     foreach($filters_dropdown as $key => $label) {
                         if ($key === 'custom') {
                             echo sprintf('<a href="#" id="br-order-custom-range-trigger">%s</a>', esc_html($label));
                         } else {
                             echo sprintf('<a href="?page=br-order-report&tab=%s&range=%s">%s</a>', esc_attr($current_tab), esc_attr($key), esc_html($label));
                         }
                     }
                     ?>
                 </div>
             </div>
         </div>
     </div>
     <?php
 }

 /**
  * NEW: Helper function to render the new KPI card style from the reference image.
  * UPDATED: Added $icon argument, Refined percentage calculation
  */
 function br_display_summary_kpi_card_new( $title, $current_value, $previous_value, $format = 'number', $icon = 'dashicons-chart-bar' ) {
     $current_value = $current_value ?? 0;
     $previous_value = $previous_value ?? 0;

     $display_value = '';
     $percentage_value = 0;

     if ( $format === 'price' ) {
         // Ensure wc_price exists before calling
         $display_value = function_exists('wc_price') ? wc_price( $current_value ) : number_format_i18n($current_value, 2);
     } elseif ( $format === 'percentage' ) {
         $display_value = number_format_i18n($current_value, 0) . '%'; // Show percentage with 0 decimals
     } else {
         $display_value = number_format_i18n( $current_value, 0 ); // Show numbers with 0 decimals
     }

     // Refined Percentage Calculation
     if ( $previous_value != 0 ) {
         $percentage_value = ( ( $current_value - $previous_value ) / abs($previous_value) ) * 100; // Use abs() to avoid issues with negative previous values
     } elseif ( $current_value != 0 ) { // Show 100% only if current > 0 and previous is 0
         $percentage_value = 100;
     } // else percentage_value remains 0 if both are zero

     $comparison_class = $percentage_value > 0.01 ? 'increase' : ( $percentage_value < -0.01 ? 'decrease' : 'neutral' );
     $comparison_icon = $percentage_value > 0.01 ? 'dashicons-arrow-up-alt' : 'dashicons-arrow-down-alt';

     // Don't show icon if neutral
     if ($comparison_class === 'neutral') {
         $comparison_icon = '';
         $percentage_value = 0; // Explicitly set to 0 for neutral display
     }

     ?>
     <div class="br-summary-kpi-card <?php echo esc_attr( $comparison_class ); ?>">
         <div class="br-kpi-header">
             <h4><?php echo esc_html( $title ); ?></h4>
             <span class="dashicons <?php echo esc_attr( $icon ); ?>"></span>
         </div>
         <p><?php echo wp_kses_post( $display_value ); ?></p>
         <div class="br-kpi-comparison-new">
             <?php if ( $comparison_icon ): ?>
                 <span class="dashicons <?php echo esc_attr( $comparison_icon ); ?>"></span>
             <?php endif; ?>
             <strong><?php echo esc_html( number_format_i18n( $percentage_value, 2 ) ); ?>%</strong>
         </div>
     </div>
     <?php
 }

 /**
  * NEW: Helper function to render the dual-value (Count + Percent) KPI card.
  * UPDATED: Refined percentage calculation
  */
 function br_display_summary_kpi_card_dual( $title, $current_count, $previous_count, $current_percent, $icon = 'dashicons-chart-bar' ) {
     $current_count = $current_count ?? 0;
     $previous_count = $previous_count ?? 0;
     $current_percent = $current_percent ?? 0;

     $percentage_value = 0; // This is for the comparison trend, not the display percentage

     // Refined Percentage Calculation
     if ( $previous_count != 0 ) {
          $percentage_value = ( ( $current_count - $previous_count ) / abs($previous_count) ) * 100; // Use abs()
     } elseif ( $current_count != 0 ) { // Show 100% only if current > 0 and previous is 0
         $percentage_value = 100;
     } // else percentage_value remains 0 if both are zero

     $comparison_class = $percentage_value > 0.01 ? 'increase' : ( $percentage_value < -0.01 ? 'decrease' : 'neutral' );
     $comparison_icon = $percentage_value > 0.01 ? 'dashicons-arrow-up-alt' : 'dashicons-arrow-down-alt';

     if ($comparison_class === 'neutral') {
         $comparison_icon = '';
         $percentage_value = 0; // Explicitly set to 0 for neutral display
     }

     ?>
     <div class="br-summary-kpi-card <?php echo esc_attr( $comparison_class ); ?>">
         <div class="br-kpi-header">
             <h4><?php echo esc_html( $title ); ?></h4>
             <span class="dashicons <?php echo esc_attr( $icon ); ?>"></span>
         </div>
         <div class="br-kpi-dual-value">
             <p><?php echo esc_html( number_format_i18n( $current_count, 0 ) ); ?></p>
             <small><?php echo esc_html( number_format_i18n( $current_percent, 0 ) ); ?>%</small>
         </div>
         <div class="br-kpi-comparison-new">
             <?php if ( $comparison_icon ): ?>
                 <span class="dashicons <?php echo esc_attr( $comparison_icon ); ?>"></span>
             <?php endif; ?>
             <strong><?php echo esc_html( number_format_i18n( $percentage_value, 2 ) ); ?>%</strong>
         </div>
     </div>
     <?php
 }


 /**
  * NEW: Helper function to get daily stats for the chart.
  * UPDATED: Uses gross_profit instead of revenue. Explicit Net Profit Calculation.
  */
 function br_get_daily_chart_stats($start_date, $end_date) {
     global $wpdb;
     $orders_table = $wpdb->prefix . 'br_orders';
     $meta_summary_table = $wpdb->prefix . 'br_meta_ad_summary';
     $meta_accounts_table = $wpdb->prefix . 'br_meta_ad_accounts';

     $days = (new DateTime($end_date))->diff(new DateTime($start_date))->days + 1;

     // 1. Initialize an array for all days in the range
     $stats = [];
     $current_date = new DateTime($start_date);
     for ($i = 0; $i < $days; $i++) {
         $date_key = $current_date->format('Y-m-d');
         $stats[$date_key] = [
             'label' => $current_date->format('D'), // e.g., 'Mon'
             'gross_profit' => 0.0,
             'ad_cost' => 0.0,
             'net_profit' => 0.0 // To be calculated later
         ];
         $current_date->modify('+1 day');
     }

     // 2. Get daily order stats (Gross Profit)
     $order_stats = $wpdb->get_results($wpdb->prepare(
         "SELECT
             DATE(order_date) AS report_date,
             SUM(CASE WHEN is_converted = 1 THEN gross_profit ELSE 0 END) AS gross_profit
          FROM {$orders_table}
          WHERE order_date BETWEEN %s AND %s
          GROUP BY DATE(order_date)",
         $start_date . ' 00:00:00',
         $end_date . ' 23:59:59'
     ));

     if ($order_stats) {
         foreach ($order_stats as $stat) {
             if (isset($stats[$stat->report_date])) {
                 $stats[$stat->report_date]['gross_profit'] = (float) $stat->gross_profit;
             }
         }
     }

     // 3. Get daily ad cost stats
     $ad_stats = $wpdb->get_results($wpdb->prepare(
         "SELECT
             s.report_date,
             SUM(s.spend_usd * a.usd_to_bdt_rate) AS ad_cost
          FROM {$meta_summary_table} s
          JOIN {$meta_accounts_table} a ON s.account_fk_id = a.id
          WHERE a.is_active = 1 AND s.report_date BETWEEN %s AND %s
          GROUP BY s.report_date",
         $start_date, $end_date
     ));

     if ($ad_stats) {
         foreach ($ad_stats as $stat) {
             // Ensure report_date exists as a key before assigning
             if (isset($stats[$stat->report_date])) {
                  $stats[$stat->report_date]['ad_cost'] = (float) $stat->ad_cost;
             } else {
                 // Log if a date from ad costs isn't in our initial range (shouldn't happen with correct query)
                 error_log("BR Log Warning: Ad cost found for date {$stat->report_date} which is outside the expected range {$start_date} to {$end_date}");
             }
         }
     }

     // 4. Calculate net profit for each day - REFINED
     foreach ($stats as $date_key => $daily_data) {
         // Explicitly calculate net profit using the potentially updated gross_profit and ad_cost
         $stats[$date_key]['net_profit'] = $daily_data['gross_profit'] - $daily_data['ad_cost'];
     }

     return $stats;
 }


 /**
  * Main HTML function for the Order Summary Tab.
  * UPDATED: Profit block now uses date-filtered data. Chart remains 7 days.
  */
 function br_order_summary_tab_html() {
     global $wpdb;
     $orders_table = $wpdb->prefix . 'br_orders';
     $meta_summary_table = $wpdb->prefix . 'br_meta_ad_summary';
     $meta_accounts_table = $wpdb->prefix . 'br_meta_ad_accounts';

     if (!function_exists('br_get_date_range')) {
         // Fallback or dependency check for date range function
         if (file_exists(BR_PLUGIN_DIR . 'includes/meta-ads.php')) {
             require_once BR_PLUGIN_DIR . 'includes/meta-ads.php';
         }
     }

     // === 1. DATA FOR KPI CARDS & PROFIT BLOCK (Uses selected date range) ===
     $current_range_key = isset($_GET['range']) ? sanitize_key($_GET['range']) : 'today';
     $start_date_get = isset($_GET['start_date']) ? sanitize_text_field($_GET['start_date']) : null;
     $end_date_get = isset($_GET['end_date']) ? sanitize_text_field($_GET['end_date']) : null;
     $is_custom_range = !empty($start_date_get) && !empty($end_date_get);

     $date_range = br_get_date_range($is_custom_range ? '' : $current_range_key, $start_date_get, $end_date_get);
     $start_date = $date_range['start'] . ' 00:00:00';
     $end_date = $date_range['end'] . ' 23:59:59';

     // Get stats for the selected period
     // **** UPDATED: Added total_gross_profit ****
     $current_stats_sql = $wpdb->prepare(
         "SELECT
             COUNT(id) AS total_orders,
             SUM(CASE WHEN is_converted = 1 THEN 1 ELSE 0 END) AS converted_orders,
             SUM(CASE WHEN is_converted = 0 THEN 1 ELSE 0 END) AS not_converted_orders,
             SUM(CASE WHEN is_converted = 1 THEN total_items ELSE 0 END) AS total_items,
             SUM(CASE WHEN is_converted = 1 THEN total_order_value ELSE 0 END) AS total_order_value,
             SUM(CASE WHEN is_converted = 1 THEN cogs_total ELSE 0 END) AS total_cogs,
             SUM(CASE WHEN is_converted = 1 THEN gross_profit ELSE 0 END) AS total_gross_profit
          FROM {$orders_table}
          WHERE order_date BETWEEN %s AND %s",
         $start_date, $end_date
     );
     $current_stats = $wpdb->get_row( $current_stats_sql );

     // Get stats for the previous period comparison
     $start_obj = new DateTime($date_range['start']);
     $end_obj = new DateTime($date_range['end']);
     $interval = $start_obj->diff($end_obj);
     $days = $interval->days + 1;

     $previous_end_obj = ( clone $start_obj )->modify( '-1 day' );
     $previous_start_obj = ( clone $previous_end_obj )->modify( '-' . ($days - 1) . ' days' );

     $previous_start = $previous_start_obj->format( 'Y-m-d 00:00:00' );
     $previous_end = $previous_end_obj->format( 'Y-m-d 23:59:59' );

      // **** UPDATED: Added total_gross_profit ****
      $previous_stats_sql = $wpdb->prepare(
         "SELECT
             COUNT(id) AS total_orders,
             SUM(CASE WHEN is_converted = 1 THEN 1 ELSE 0 END) AS converted_orders,
             SUM(CASE WHEN is_converted = 0 THEN 1 ELSE 0 END) AS not_converted_orders,
             SUM(CASE WHEN is_converted = 1 THEN total_items ELSE 0 END) AS total_items,
             SUM(CASE WHEN is_converted = 1 THEN total_order_value ELSE 0 END) AS total_order_value,
             SUM(CASE WHEN is_converted = 1 THEN cogs_total ELSE 0 END) AS total_cogs,
             SUM(CASE WHEN is_converted = 1 THEN gross_profit ELSE 0 END) AS total_gross_profit
          FROM {$orders_table}
          WHERE order_date BETWEEN %s AND %s",
         $previous_start, $previous_end
     );
     $previous_stats = $wpdb->get_row( $previous_stats_sql );

     // --- Ads Cost Calculation (for selected period) ---
     $current_ads_cost_bdt = $wpdb->get_var($wpdb->prepare(
         "SELECT SUM(s.spend_usd * a.usd_to_bdt_rate)
          FROM {$meta_summary_table} s
          JOIN {$meta_accounts_table} a ON s.account_fk_id = a.id
          WHERE a.is_active = 1 AND s.report_date BETWEEN %s AND %s",
         $date_range['start'], $date_range['end']
     ));
     $current_ads_cost_bdt = floatval($current_ads_cost_bdt);

     $previous_ads_cost_bdt = $wpdb->get_var($wpdb->prepare(
         "SELECT SUM(s.spend_usd * a.usd_to_bdt_rate)
          FROM {$meta_summary_table} s
          JOIN {$meta_accounts_table} a ON s.account_fk_id = a.id
          WHERE a.is_active = 1 AND s.report_date BETWEEN %s AND %s",
         $previous_start_obj->format('Y-m-d'), $previous_end_obj->format('Y-m-d')
     ));
     $previous_ads_cost_bdt = floatval($previous_ads_cost_bdt);

     // --- KPI Card Calculations (selected period) ---
     $current_total_orders = $current_stats->total_orders ?? 0;
     $previous_total_orders = $previous_stats->total_orders ?? 0;
     $current_converted_orders = $current_stats->converted_orders ?? 0;
     $previous_converted_orders = $previous_stats->converted_orders ?? 0;
     $current_not_converted_orders = $current_stats->not_converted_orders ?? 0;
     $previous_not_converted_orders = $previous_stats->not_converted_orders ?? 0;


     $current_ads_cost_per_order = $current_total_orders > 0 ? $current_ads_cost_bdt / $current_total_orders : 0;
     $previous_ads_cost_per_order = $previous_total_orders > 0 ? $previous_ads_cost_bdt / $previous_total_orders : 0;

     $current_ads_cost_per_converted = $current_converted_orders > 0 ? $current_ads_cost_bdt / $current_converted_orders : 0;
     $previous_ads_cost_per_converted = $previous_converted_orders > 0 ? $previous_ads_cost_bdt / $previous_converted_orders : 0;

     $current_conversion_rate = $current_total_orders > 0 ? ($current_converted_orders / $current_total_orders) * 100 : 0;

     $current_non_conversion_rate = $current_total_orders > 0 ? ($current_not_converted_orders / $current_total_orders) * 100 : 0;

     // === CALCULATIONS FOR DATE-FILTERED PROFIT BLOCK ===
     $current_total_gross_profit = $current_stats->total_gross_profit ?? 0;
     $previous_total_gross_profit = $previous_stats->total_gross_profit ?? 0;

     $current_total_net_profit = $current_total_gross_profit - $current_ads_cost_bdt;
     $previous_total_net_profit = $previous_total_gross_profit - $previous_ads_cost_bdt;

     // --- Percentage changes for date-filtered profit block ---
     $gross_profit_filtered_change = 0;
     if ($previous_total_gross_profit != 0) {
         $gross_profit_filtered_change = (($current_total_gross_profit - $previous_total_gross_profit) / abs($previous_total_gross_profit)) * 100;
     } elseif ($current_total_gross_profit != 0) {
         $gross_profit_filtered_change = 100;
     }
     $gross_profit_filtered_class = $gross_profit_filtered_change > 0.01 ? 'increase' : ( $gross_profit_filtered_change < -0.01 ? 'decrease' : 'neutral' );
     $gross_profit_filtered_icon = $gross_profit_filtered_change > 0.01 ? 'dashicons-arrow-up-alt' : 'dashicons-arrow-down-alt';
     if ($gross_profit_filtered_class === 'neutral') {
         $gross_profit_filtered_icon = '';
         $gross_profit_filtered_change = 0;
     }

     $net_profit_filtered_change = 0;
     if ($previous_total_net_profit != 0) {
         $net_profit_filtered_change = (($current_total_net_profit - $previous_total_net_profit) / abs($previous_total_net_profit)) * 100;
     } elseif ($current_total_net_profit != 0) {
         $net_profit_filtered_change = 100;
     }
     $net_profit_filtered_class = $net_profit_filtered_change > 0.01 ? 'increase' : ( $net_profit_filtered_change < -0.01 ? 'decrease' : 'neutral' );
     $net_profit_filtered_icon = $net_profit_filtered_change > 0.01 ? 'dashicons-arrow-up-alt' : 'dashicons-arrow-down-alt';
     if ($net_profit_filtered_class === 'neutral') {
         $net_profit_filtered_icon = '';
         $net_profit_filtered_change = 0;
     }
     // === END OF DATE-FILTERED PROFIT BLOCK CALCULATIONS ===


     // === 2. DATA FOR CHART (FIXED Last 7 Days) ===

     // --- Current 7 Days ---
     $chart_range = br_get_date_range('last_7_days');
     $chart_start_date = $chart_range['start'];
     $chart_end_date = $chart_range['end'];

     $daily_stats_current = br_get_daily_chart_stats($chart_start_date, $chart_end_date);

     $chart_labels = [];
     $chart_gross_profit_data = [];
     $chart_net_profit_data = [];

     foreach ($daily_stats_current as $day_stats) {
         $chart_labels[] = $day_stats['label'];
         $chart_gross_profit_data[] = $day_stats['gross_profit'];
         $chart_net_profit_data[] = $day_stats['net_profit'];
     }

     $chart_data_json = wp_json_encode([
         'labels' => $chart_labels,
         'datasets' => [
             [
                 'label' => 'Net Profit', // Tooltip label
                 'data' => $chart_net_profit_data,
                 'backgroundColor' => '#FFD947', // Reference Image: Net Profit is Orange/Yellow
                 'borderColor' => '#FFD947',
                 'borderWidth' => 1,
                 'barThickness' => 12,
                 'borderRadius' => 6,
             ],
             [
                 'label' => 'Gross Profit', // Changed Tooltip label
                 'data' => $chart_gross_profit_data, // Use gross_profit data
                 'backgroundColor' => '#490AA3', // Reference Image: "Revenue" (now Gross Profit) is Blue/Purple
                 'borderColor' => '#490AA3',
                 'borderWidth' => 1,
                 'barThickness' => 12,
                 'borderRadius' => 6,
             ]
         ]
     ]);

     // Pass this data to JavaScript
     wp_add_inline_script( 'br-order-report-admin-js', 'const br_summary_chart_data = ' . $chart_data_json . ';', 'before' );

     // === 3. RENDER THE HTML ===
     br_order_summary_render_date_filters_html('summary');
     ?>

     <div class="br-summary-layout-grid">

         <!-- Column 1: KPIs (Date-Filtered) -->
         <div class="br-summary-kpi-grid">
             <?php
             // Row 1
             br_display_summary_kpi_card_new( 'Total Orders', $current_total_orders, $previous_total_orders, 'number', 'dashicons-cart' );
             br_display_summary_kpi_card_dual( 'Converted Orders', $current_converted_orders, $previous_converted_orders, $current_conversion_rate, 'dashicons-yes-alt' );
             br_display_summary_kpi_card_dual( 'Non Converted Orders', $current_not_converted_orders, $previous_not_converted_orders, $current_non_conversion_rate, 'dashicons-no' );

             // Row 2
             br_display_summary_kpi_card_new( 'Total Items Sold', $current_stats->total_items ?? 0, $previous_stats->total_items ?? 0, 'number', 'dashicons-products' );
             br_display_summary_kpi_card_new( 'Total Item Selling Price', $current_stats->total_order_value ?? 0, $previous_stats->total_order_value ?? 0, 'price', 'dashicons-tag' );
             br_display_summary_kpi_card_new( 'Total Item Cost', $current_stats->total_cogs ?? 0, $previous_stats->total_cogs ?? 0, 'price', 'dashicons-money-alt' );

             // Row 3
             br_display_summary_kpi_card_new( 'Total Ads Cost', $current_ads_cost_bdt, $previous_ads_cost_bdt, 'price', 'dashicons-megaphone' );
             br_display_summary_kpi_card_new( 'Ads Cost Per Order', $current_ads_cost_per_order, $previous_ads_cost_per_order, 'price', 'dashicons-chart-line' );
             br_display_summary_kpi_card_new( 'Ads Cost Per Converted Order', $current_ads_cost_per_converted, $previous_ads_cost_per_converted, 'price', 'dashicons-chart-area' );
             ?>
         </div>

         <!-- Column 2: Profit & Chart -->
         <div class="br-summary-profit-chart-wrapper">

             <!-- Profit Block (Date-Filtered) -->
             <div class="br-summary-profit-block">
                 <div class="br-profit-item">
                     <div class="br-profit-label">
                         <span class="br-legend-dot revenue"></span>
                         <?php _e('Gross Profit', 'business-report'); ?>
                     </div>
                     <div class="br-profit-value">
                         <?php echo function_exists('wc_price') ? wc_price($current_total_gross_profit) : number_format_i18n($current_total_gross_profit, 2); ?>
                     </div>
                     <div class="br-profit-comparison <?php echo esc_attr($gross_profit_filtered_class); ?>">
                         <?php if ($gross_profit_filtered_icon): ?>
                              <span class="dashicons <?php echo esc_attr($gross_profit_filtered_icon); ?>"></span>
                         <?php endif; ?>
                         <?php echo esc_html( number_format_i18n( $gross_profit_filtered_change, 2 ) ); ?>%
                     </div>
                 </div>
                 <div class="br-profit-item">
                      <div class="br-profit-label">
                         <span class="br-legend-dot profit"></span>
                         <?php _e('Net Profit (Gross Profit - Ads Cost)', 'business-report'); ?>
                     </div>
                     <div class="br-profit-value">
                         <?php echo function_exists('wc_price') ? wc_price($current_total_net_profit) : number_format_i18n($current_total_net_profit, 2); ?>
                     </div>
                      <div class="br-profit-comparison <?php echo esc_attr($net_profit_filtered_class); ?>">
                         <?php if ($net_profit_filtered_icon): ?>
                              <span class="dashicons <?php echo esc_attr($net_profit_filtered_icon); ?>"></span>
                         <?php endif; ?>
                         <?php echo esc_html( number_format_i18n( $net_profit_filtered_change, 2 ) ); ?>%
                     </div>
                 </div>
             </div>

             <!-- Chart (Fixed 7 Days) -->
             <div class="br-summary-chart-container">
                 <canvas id="br-summary-chart-canvas"></canvas>
                 <!-- NEW: Added Chart Note -->
                 <p class="br-chart-note"><?php _e('Chart shows last 7 days Gross Profit and Net Profit', 'business-report'); ?></p>
             </div>

         </div>

     </div>
     <?php
 }
 

