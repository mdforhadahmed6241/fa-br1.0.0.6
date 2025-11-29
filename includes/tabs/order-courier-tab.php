<?php
/**
 * Order Report Courier Tab HTML/Logic
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Renders the HTML for the custom date range modal specific to the Courier tab.
 */
function br_order_courier_custom_range_filter_modal_html() {
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
                <input type="hidden" name="tab" value="courier">
                <div class="form-row">
                    <div>
                        <label for="br_order_filter_start_date_courier"><?php _e('Start Date', 'business-report'); ?></label>
                        <input type="text" id="br_order_filter_start_date_courier" name="start_date" class="br-datepicker" value="<?php echo esc_attr($start_date); ?>" autocomplete="off" required>
                    </div>
                    <div>
                        <label for="br_order_filter_end_date_courier"><?php _e('End Date', 'business-report'); ?></label>
                        <input type="text" id="br_order_filter_end_date_courier" name="end_date" class="br-datepicker" value="<?php echo esc_attr($end_date); ?>" autocomplete="off" required>
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
 * Helper function to get performance comment for Delivered Orders.
 */
function br_get_delivered_performance_comment($percent) {
    if ($percent >= 95) {
        return ['text' => '✅ Excellent! You’re maintaining an outstanding delivery performance. Keep it up — customers clearly trust your service.', 'class' => 'excellent'];
    } elseif ($percent >= 90) {
        return ['text' => '🌟 Great! Your delivery rate is strong. A little more focus on follow-up can take you to the next level.', 'class' => 'great'];
    } elseif ($percent >= 80) {
        return ['text' => '👍 Good! You’re doing well, but there’s room to improve. Strengthen your logistics and customer communication.', 'class' => 'good'];
    } elseif ($percent >= 70) {
        return ['text' => '⚠️ Needs Attention: Delivery rate is below average. Communicate regularly with customers to ensure product handover.', 'class' => 'low'];
    } else { // 1-69%
        return ['text' => '❌ Critical: Delivery rate is very poor. Confirm every order before dispatch, and maintain consistent coordination with riders and customers..', 'class' => 'poor'];
    }
}

/**
 * Helper function to get performance comment for Total Returned Orders.
 */
function br_get_returned_performance_comment($percent) {
    if ($percent <= 3) {
        return ['text' => '✅ Excellent! Very few returns — your customers are happy and satisfied.', 'class' => 'excellent'];
    } elseif ($percent <= 6) {
        return ['text' => '🌟 Great! Acceptable return rate. Keep verifying products before dispatch to maintain this.', 'class' => 'great'];
    } elseif ($percent <= 13) {
        return ['text' => '👍 Good! Returns are moderate. Review product info, packaging, and communication to reduce them further.', 'class' => 'good'];
    } elseif ($percent <= 20) {
        return ['text' => '⚠️ Needs Attention: Return rate is high. Ensure accurate product descriptions and strong quality control.', 'class' => 'low'];
    } else { // 21-100%
        return ['text' => '❌ Critical: Extremely high return rate. Recheck your confirmation process, packaging, and customer feedback immediately.', 'class' => 'poor'];
    }
}

/**
 * Helper function to get performance comment for Non-Partially Returned Orders (Failed Deliveries).
 */
function br_get_non_partial_returned_comment($percent) {
    if ($percent <= 3) {
        return ['text' => '✅ Excellent! Almost all orders reached customers successfully. Maintain your current coordination with couriers and customers.', 'class' => 'excellent'];
    } elseif ($percent <= 6) {
        return ['text' => '🌟 Great! Few orders couldn’t be delivered. Keep following up and checking courier readiness to reduce failures.', 'class' => 'great'];
    } elseif ($percent <= 13) {
        return ['text' => '👍 Good: Some orders couldn’t reach customers. Verify customer contact info and ensure delivery reminders are sent.', 'class' => 'good'];
    } elseif ($percent <= 20) {
        return ['text' => '⚠️ Needs Attention: A significant number of orders failed. Communicate clearly with customers and coordinate with couriers before sending products.', 'class' => 'low'];
    } else { // 21-100%
        return ['text' => '❌ Critical Issue: Too many deliveries failed. Check courier readiness, maintain customer reminders, and confirm delivery availability before dispatching any order.', 'class' => 'poor'];
    }
}

/**
 * Helper function to get performance comment for Partially Returned Orders.
 */
function br_get_partial_returned_comment($percent) {
     if ($percent <= 3) {
        return ['text' => '✅ Excellent! Almost no partial deliveries — customers are receiving exactly what they expect. Great job maintaining product accuracy.', 'class' => 'excellent'];
    } elseif ($percent <= 6) {
        return ['text' => '🌟 Great! A few cases of dissatisfaction. Try adding clearer photos and detailed product descriptions.', 'class' => 'great'];
    } elseif ($percent <= 13) {
        return ['text' => '👍 Good: Some customers are unhappy after seeing the product. Improve packaging visuals and confirm details before dispatch.', 'class' => 'good'];
    } elseif ($percent <= 20) {
        return ['text' => '⚠️ Needs Attention: Partial delivery rate is growing. Ensure pre-shipment quality checks and better expectation management.', 'class' => 'low'];
    } else { // 21-100%
        return ['text' => '❌ Critical Issue: Many customers are rejecting products even after paying delivery charges. Review product accuracy, images, and descriptions immediately.', 'class' => 'poor'];
    }
}


/**
 * Renders the HTML for the Courier summary tab.
 * UPDATED: v1.6.6 - Added check for $total_orders before showing comments
 */
function br_order_courier_tab_html() {
    global $wpdb;
    $orders_table = $wpdb->prefix . 'br_orders';

    // Ensure helper functions are available
    if (!function_exists('br_get_date_range')) {
        if (!br_require_dependency_once(BR_PLUGIN_DIR . 'includes/meta-ads.php')) {
             echo '<div class="notice notice-error"><p>Error: The required Meta Ads module file is missing. Date filtering may not work.</p></div>';
            return;
        }
    }

    $current_range_key = isset($_GET['range']) ? sanitize_key($_GET['range']) : 'today';
    $start_date_get = isset($_GET['start_date']) ? sanitize_text_field($_GET['start_date']) : null;
    $end_date_get = isset($_GET['end_date']) ? sanitize_text_field($_GET['end_date']) : null;
    $is_custom_range = !empty($start_date_get) && !empty($end_date_get);

    $date_range = br_get_date_range($is_custom_range ? '' : $current_range_key, $start_date_get, $end_date_get);
    $start_date = $date_range['start'] . ' 00:00:00';
    $end_date = $date_range['end'] . ' 23:59:59';

    // --- Data Fetching Logic (Current Period) ---
    // COURIER_STATUS: 0 = Delivered, 1 = Returned (Full/Non-Partial), 2 = Returned (Partial)
    
    $current_stats_sql = $wpdb->prepare(
        "SELECT
            COUNT(id) AS total_orders,
            SUM(CASE WHEN courier_status = 0 THEN 1 ELSE 0 END) AS delivered_orders,
            SUM(CASE WHEN courier_status = 1 THEN 1 ELSE 0 END) AS non_partial_returned_orders,
            SUM(CASE WHEN courier_status = 2 THEN 1 ELSE 0 END) AS partially_returned_orders
         FROM {$orders_table}
         WHERE updated_at BETWEEN %s AND %s
         AND courier_status IN (0, 1, 2)", // Only count orders that have a defined courier status
        $start_date, $end_date
    );
    $stats = $wpdb->get_row( $current_stats_sql );

    $total_orders = $stats->total_orders ?? 0;
    $delivered_orders = $stats->delivered_orders ?? 0;
    $non_partial_returned_orders = $stats->non_partial_returned_orders ?? 0;
    $partially_returned_orders = $stats->partially_returned_orders ?? 0;
    
    // Total Returned = Non-Partial (Full) + Partial
    $total_returned_orders = $non_partial_returned_orders + $partially_returned_orders;

    // --- Percentage Calculations ---
    $delivered_percent = ($total_orders > 0) ? ($delivered_orders / $total_orders) * 100 : 0;
    $partially_returned_percent = ($total_orders > 0) ? ($partially_returned_orders / $total_orders) * 100 : 0;
    $non_partial_returned_percent = ($total_orders > 0) ? ($non_partial_returned_orders / $total_orders) * 100 : 0;
    $total_returned_percent = ($total_orders > 0) ? ($total_returned_orders / $total_orders) * 100 : 0;

    // --- Get Dynamic Comments ---
    // Only calculate comments if there are orders to analyze
    if ($total_orders > 0) {
        $delivered_comment = br_get_delivered_performance_comment($delivered_percent);
        $returned_comment = br_get_returned_performance_comment($total_returned_percent);
        $non_partial_comment = br_get_non_partial_returned_comment($non_partial_returned_percent);
        $partial_comment = br_get_partial_returned_comment($partially_returned_percent);
    } else {
        // Set empty defaults if no orders
        $delivered_comment = ['text' => '', 'class' => ''];
        $returned_comment = ['text' => '', 'class' => ''];
        $non_partial_comment = ['text' => '', 'class' => ''];
        $partial_comment = ['text' => '', 'class' => ''];
    }


    br_order_render_date_filters_html('courier');
    ?>
    <div class="br-page-note">
        <p><?php _e('Note: Courier reports filter orders based on the **"Last Order Update Date"** and use the statuses defined in **"Settings > Courier"**.', 'business-report'); ?></p>
    </div>
    
    <div class="br-grid-two-column-uneven">
        <!-- Card 1: Total Orders -->
        <div class="br-kpi-card br-courier-total-card">
            <div class="br-courier-total-count">
                <?php echo esc_html(number_format_i18n($total_orders)); ?>
            </div>
            <h4>
                <?php _e('Total Orders', 'business-report'); ?>
                <span class="dashicons dashicons-info br-tooltip">
                    <span class="br-tooltip-text"><?php _e('Total orders updated in the selected date range matching Delivered, Returned, or Partially Returned statuses.', 'business-report'); ?></span>
                </span>
            </h4>
            <p class="br-courier-total-note"><?php _e('Total Orders Update in selected Date', 'business-report'); ?></p>

            <div class="br-segment-bar">
                <div class="br-segment-delivered" style="width: <?php echo esc_attr($delivered_percent); ?>%;" title="Delivered: <?php echo esc_attr(number_format_i18n($delivered_percent, 1)); ?>%"></div>
                <div class="br-segment-partial-returned" style="width: <?php echo esc_attr($partially_returned_percent); ?>%;" title="Partially Returned: <?php echo esc_attr(number_format_i18n($partially_returned_percent, 1)); ?>%"></div>
                <div class="br-segment-returned" style="width: <?php echo esc_attr($non_partial_returned_percent); ?>%;" title="Non Partial Returned: <?php echo esc_attr(number_format_i18n($non_partial_returned_percent, 1)); ?>%"></div>
            </div>

            <ul class="br-courier-legend">
                <li>
                    <span class="legend-color-dot delivered"></span>
                    <?php echo esc_html(number_format_i18n($delivered_percent, 1)); ?>% <?php _e('Delivered', 'business-report'); ?>
                </li>
                <li>
                    <span class="legend-color-dot partial-returned"></span>
                    <?php echo esc_html(number_format_i18n($partially_returned_percent, 1)); ?>% <?php _e('Partially Returned', 'business-report'); ?>
                </li>
                <li>
                    <span class="legend-color-dot returned"></span>
                    <?php echo esc_html(number_format_i18n($non_partial_returned_percent, 1)); ?>% <?php _e('Non Partial', 'business-report'); ?>
                </li>
            </ul>
        </div>

        <!-- Card 2: Courier Performance Breakdown -->
        <div class="br-kpi-card br-performance-card">
            <h4><?php _e('Courier Performance breakdown', 'business-report'); ?></h4>

            <div class="br-performance-list">
                <!-- Delivered Order -->
                <div class="br-performance-list-item">
                    <div class="br-performance-details">
                        <span class="title"><?php _e('Delivered Order', 'business-report'); ?></span>
                        <?php if ($total_orders > 0) : ?>
                        <div class="br-performance-comment <?php echo esc_attr($delivered_comment['class']); ?>">
                            <?php echo esc_html($delivered_comment['text']); ?>
                        </div>
                        <?php endif; ?>
                    </div>
                     <div class="br-performance-stats-inline">
                        <span class="count"><?php echo esc_html(number_format_i18n($delivered_orders)); ?></span>
                        <span class="percent"><?php echo esc_html(number_format_i18n($delivered_percent, 0)); ?>%</span>
                    </div>
                </div>

                <!-- Total Returned -->
                <div class="br-performance-list-item">
                     <div class="br-performance-details">
                        <span class="title"><?php _e('Total Returned (Partially + Non P...)', 'business-report'); ?></span>
                        <?php if ($total_orders > 0) : ?>
                        <div class="br-performance-comment <?php echo esc_attr($returned_comment['class']); ?>">
                           <?php echo esc_html($returned_comment['text']); ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="br-performance-stats-inline">
                        <span class="count"><?php echo esc_html(number_format_i18n($total_returned_orders)); ?></span>
                        <span class="percent"><?php echo esc_html(number_format_i18n($total_returned_percent, 0)); ?>%</span>
                    </div>
                </div>

                <!-- Non Partially Returned -->
                <div class="br-performance-list-item">
                    <div class="br-performance-details">
                        <span class="title"><?php _e('Non Partially Returned', 'business-report'); ?></span>
                        <?php if ($total_orders > 0) : ?>
                         <div class="br-performance-comment <?php echo esc_attr($non_partial_comment['class']); ?>">
                            <?php echo esc_html($non_partial_comment['text']); ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="br-performance-stats-inline">
                        <span class="count"><?php echo esc_html(number_format_i18n($non_partial_returned_orders)); ?></span>
                        <span class="percent"><?php echo esc_html(number_format_i18n($non_partial_returned_percent, 0)); ?>%</span>
                    </div>
                </div>

                <!-- Partially Returned -->
                <div class="br-performance-list-item">
                    <div class="br-performance-details">
                        <span class="title"><?php _e('Partially Returned', 'business-report'); ?></span>
                        <?php if ($total_orders > 0) : ?>
                         <div class="br-performance-comment <?php echo esc_attr($partial_comment['class']); ?>">
                           <?php echo esc_html($partial_comment['text']); ?>
                        </div>
                        <?php endif; ?>
                    </div>
                     <div class="br-performance-stats-inline">
                        <span class="count"><?php echo esc_html(number_format_i18n($partially_returned_orders)); ?></span>
                        <span class="percent"><?php echo esc_html(number_format_i18n($partially_returned_percent, 0)); ?>%</span>
                    </div>
                </div>
            </div>

        </div>
    </div>
    <?php
}

/**
 * Renders the date filter buttons and dropdown for the Courier Tab.
 */
function br_order_render_date_filters_html($current_tab = 'summary') {
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

