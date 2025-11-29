<?php
/**
 * Order Report Source Tab HTML/Logic
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Renders the HTML for the custom date range modal specific to the Source tab.
 */
function br_order_source_custom_range_filter_modal_html() {
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
                <input type="hidden" name="tab" value="source">
                <div class="form-row">
                    <div>
                        <label for="br_order_filter_start_date_source"><?php _e('Start Date', 'business-report'); ?></label>
                        <input type="text" id="br_order_filter_start_date_source" name="start_date" class="br-datepicker" value="<?php echo esc_attr($start_date); ?>" autocomplete="off" required>
                    </div>
                    <div>
                        <label for="br_order_filter_end_date_source"><?php _e('End Date', 'business-report'); ?></label>
                        <input type="text" id="br_order_filter_end_date_source" name="end_date" class="br-datepicker" value="<?php echo esc_attr($end_date); ?>" autocomplete="off" required>
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
 * Renders the date filter buttons and dropdown for the Source Tab.
 */
function br_order_source_render_date_filters_html($current_tab = 'source') {
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

/**
 * Helper function to group web sources.
 */
function br_group_web_source($source) {
    $source = strtolower(trim($source));
    
    // Facebook
    $fb_sources = ['fb', 'm.facebook.com', 'lm.facebook.com', 'l.facebook.com', 'www.facebook.com', 'web.facebook.com'];
    if (in_array($source, $fb_sources)) return 'Facebook';

    // Instagram
    $ig_sources = ['ig', 'instagram.com', 'www.instagram.com'];
    if (in_array($source, $ig_sources)) return 'Instagram';

    // Google
    $google_sources = ['google', 'google.com'];
    if (in_array($source, $google_sources)) return 'Google';

    // Direct (as seen in screenshot)
    if ($source === 'direct') return 'Direct';
    if ($source === 'youtube') return 'Youtube';
    if ($source === 'tiktok') return 'Tiktok';
    
    // Other known types
    if ($source === 'referral') return 'Referral';
    if ($source === 'unknown/n/a' || empty($source)) return 'Unknown';

    // Default: return the original source, capitalized
    return ucfirst($source);
}

/**
 * Helper function to group admin sources.
 */
function br_group_admin_source($source) {
    $source = strtolower(trim($source));
    
    if ($source === 'admin') return 'No Source';
    if ($source === 'admin-messenger') return 'Messenger';
    if ($source === 'admin-tiktok') return 'Tiktok';
    if ($source === 'admin-whatsapp') return 'Whatsapp';
    if ($source === 'admin-instagram') return 'Instagram';

    // Default: return the part after 'admin-' or 'Admin' if no prefix
    if (strpos($source, 'admin-') === 0) {
        return ucfirst(str_replace('admin-', '', $source));
    }
    return 'Admin';
}

/**
 * Helper function to render a source list card.
 */
function br_render_source_list_card($title, $source_data, $total_count) {
    ?>
    <div class="br-kpi-card br-source-list-card">
        <h4><?php echo esc_html($title); ?></h4>
        <ul class="br-source-list">
            <?php
            if (empty($source_data)) {
                echo '<li>No data for this period.</li>';
            } else {
                // Sort by count descending
                uasort($source_data, function($a, $b) {
                    return $b['count'] <=> $a['count'];
                });

                foreach ($source_data as $name => $data) {
                    $percentage = ($total_count > 0) ? ($data['count'] / $total_count) * 100 : 0;
                    ?>
                    <li>
                        <span class="source-name"><?php echo esc_html($name); ?></span>
                        <span class="source-stats">
                            <span class="source-percent"><?php echo esc_html(number_format_i18n($percentage, 2)); ?>%</span>
                            <span class="source-count"><?php echo esc_html(number_format_i18n($data['count'])); ?> Orders</span>
                        </span>
                    </li>
                    <?php
                }
            }
            ?>
        </ul>
    </div>
    <?php
}

/**
 * Renders the HTML for the Source summary tab.
 */
function br_order_source_tab_html() {
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

    // --- Data Fetching Logic ---
    $all_sources = $wpdb->get_results($wpdb->prepare(
        "SELECT source, COUNT(id) as order_count
         FROM {$orders_table}
         WHERE order_date BETWEEN %s AND %s
         GROUP BY source",
        $start_date, $end_date
    ));

    $total_orders = 0;
    $total_web_orders = 0;
    $total_admin_orders = 0;
    $web_sources = [];
    $admin_sources = [];

    if (!empty($all_sources)) {
        foreach ($all_sources as $row) {
            $count = (int) $row->order_count;
            $source_val = $row->source;
            $total_orders += $count;

            if (strpos($source_val, 'admin') === 0) {
                // This is an Admin Created order
                $total_admin_orders += $count;
                $grouped_name = br_group_admin_source($source_val);
                if (!isset($admin_sources[$grouped_name])) {
                    $admin_sources[$grouped_name] = ['count' => 0];
                }
                $admin_sources[$grouped_name]['count'] += $count;
            } else {
                // This is a Web Order
                $total_web_orders += $count;
                $grouped_name = br_group_web_source($source_val);
                if (!isset($web_sources[$grouped_name])) {
                    $web_sources[$grouped_name] = ['count' => 0];
                }
                $web_sources[$grouped_name]['count'] += $count;
            }
        }
    }

    $web_order_percent = ($total_orders > 0) ? ($total_web_orders / $total_orders) * 100 : 0;
    $admin_order_percent = ($total_orders > 0) ? ($total_admin_orders / $total_orders) * 100 : 0;
    
    // Prepare data for Chart.js
    $chart_data = [
        'labels' => ['Web Order', 'Admin Create'],
        'datasets' => [
            [
                'data' => [$total_web_orders, $total_admin_orders],
                'backgroundColor' => ['#C084FC', '#6366F1'], // Purple, Indigo
                'hoverBackgroundColor' => ['#A855F7', '#4F46E5'],
                'borderWidth' => 0,
            ]
        ]
    ];
    $chart_data_json = wp_json_encode($chart_data);

    br_order_source_render_date_filters_html('source');
    ?>
    
    <div class="br-grid-three-column">
        <!-- Card 1: Order Source Donut Chart -->
        <div class="br-kpi-card br-chart-card">
            <h4>Order Source</h4>
            <div class="br-chart-container">
                <canvas id="br-source-donut-chart"></canvas>
            </div>
            <ul class="br-chart-legend">
                <li>
                    <span class="legend-color" style="background-color: #C084FC;"></span>
                    <span class="legend-label">Web Order</span>
                    <span class="legend-percent"><?php echo esc_html(number_format_i18n($web_order_percent, 2)); ?>%</span>
                    <span class="legend-value"><?php echo esc_html(number_format_i18n($total_web_orders)); ?></span>
                </li>
                <li>
                    <span class="legend-color" style="background-color: #6366F1;"></span>
                    <span class="legend-label">Admin Create</span>
                    <span class="legend-percent"><?php echo esc_html(number_format_i18n($admin_order_percent, 2)); ?>%</span>
                    <span class="legend-value"><?php echo esc_html(number_format_i18n($total_admin_orders)); ?></span>
                </li>
            </ul>
        </div>

        <!-- Card 2: Web Order Source List -->
        <?php br_render_source_list_card('Web Order Source', $web_sources, $total_web_orders); ?>

        <!-- Card 3: Admin Created List -->
        <?php br_render_source_list_card('Admin Created', $admin_sources, $total_admin_orders); ?>

    </div>

    <script type="text/javascript">
        jQuery(document).ready(function($) {
            if (typeof Chart !== 'undefined') {
                const ctx = document.getElementById('br-source-donut-chart');
                if (ctx) {
                    new Chart(ctx, {
                        type: 'doughnut',
                        data: <?php echo $chart_data_json; ?>,
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            cutout: '75%',
                            plugins: {
                                legend: {
                                    display: false // We use a custom HTML legend
                                },
                                tooltip: {
                                    enabled: true
                                }
                            }
                        }
                    });
                }
            }
        });
    </script>
    <?php
}

