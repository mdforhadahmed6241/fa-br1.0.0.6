<?php
/**
 * Customer Report Page Controller
 *
 * @package BusinessReport
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

// Include Tabs and Classes
require_once BR_PLUGIN_DIR . 'includes/tabs/customer-summary-tab.php';
require_once BR_PLUGIN_DIR . 'includes/tabs/customer-list-tab.php';
require_once BR_PLUGIN_DIR . 'includes/classes/class-br-customer-list-table.php';

/**
 * Enqueue Scripts for Customer Report
 */
function br_customer_report_admin_enqueue_scripts( $hook ) {
    if ( 'business-report_page_br-customer-report' !== $hook ) {
        return;
    }

    // Enqueue Chart.js
    if ( ! wp_script_is( 'chart-js', 'registered' ) ) {
        wp_enqueue_script( 'chart-js', 'https://cdn.jsdelivr.net/npm/chart.js', [], '4.4.1', true );
    } else {
        wp_enqueue_script( 'chart-js' );
    }

    // Enqueue standard styles
    wp_enqueue_style( 'br-admin-global-styles', BR_PLUGIN_URL . 'assets/css/admin-global.css', [], BR_PLUGIN_VERSION );
    wp_enqueue_style( 'br-admin-dashboard-styles', BR_PLUGIN_URL . 'assets/css/admin-dashboard.css', [], BR_PLUGIN_VERSION );
    
    // Datepicker
    wp_enqueue_script('jquery-ui-datepicker');
    wp_enqueue_style('wp-jquery-ui-dialog');
}
add_action( 'admin_enqueue_scripts', 'br_customer_report_admin_enqueue_scripts' );

/**
 * Helper: Render Inline Date Filters (Links + Date Inputs)
 * UPDATED: Added 'lifetime' key to ranges array.
 */
function br_customer_render_filters($current_tab) {
    // Get current params
    $current_range = isset($_GET['range']) ? sanitize_key($_GET['range']) : 'today';
    $start_date = isset($_GET['start_date']) ? sanitize_text_field($_GET['start_date']) : '';
    $end_date = isset($_GET['end_date']) ? sanitize_text_field($_GET['end_date']) : '';
    
    // If start_date is present, we are in 'custom' mode, so preset buttons shouldn't look active
    if ( !empty($start_date) ) {
        $current_range = 'custom';
    }

    $ranges = [
        'today' => 'Today',
        'yesterday' => 'Yesterday', 
        'last_7_days' => '7D', 
        'last_30_days' => '30D', 
        'this_month' => 'This Month',
        'lifetime' => 'Lifetime' // Added Lifetime button
    ];
    
    $base_url = admin_url('admin.php?page=br-customer-report&tab=' . $current_tab);
    ?>
    <div class="br-dash-filters" style="display: flex; align-items: center; gap: 10px; background: #fff; padding: 10px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #d1d5db;">
        
        <!-- Preset Buttons (Links) -->
        <div class="br-filter-buttons" style="display:flex; gap:5px;">
            <?php foreach ($ranges as $key => $label): 
                $active = ($current_range === $key) ? 'active' : '';
                $btn_style = $active ? 'background-color: #490AA3; color: #fff; border-color: #490AA3;' : 'background-color: #fff; color: #490AA3; border: 1px solid #fff;';
                // Link ensures we clear start/end dates from URL
                $url = $base_url . '&range=' . $key;
            ?>
                <a href="<?php echo esc_url($url); ?>" class="button" style="<?php echo $btn_style; ?> text-decoration:none;">
                    <?php echo esc_html($label); ?>
                </a>
            <?php endforeach; ?>
        </div>

        <div style="border-left: 1px solid #ddd; height: 30px; margin: 0 10px;"></div>

        <!-- Custom Range Form -->
        <form method="GET" style="display: flex; align-items: center; gap: 5px; margin:0;">
            <input type="hidden" name="page" value="br-customer-report">
            <input type="hidden" name="tab" value="<?php echo esc_attr($current_tab); ?>">
            <!-- Force range=custom when submitting this form -->
            <input type="hidden" name="range" value="custom"> 
            
            <input type="text" name="start_date" class="br-datepicker" placeholder="Start Date" value="<?php echo esc_attr($start_date); ?>" style="width: 100px; text-align:center;">
            <input type="text" name="end_date" class="br-datepicker" placeholder="End Date" value="<?php echo esc_attr($end_date); ?>" style="width: 100px; text-align:center;">
            
            <button type="submit" class="button" style="background-color: #fff; border: 1px solid #d1d5db;">Go</button>
        </form>
    </div>
    
    <script>
    jQuery(document).ready(function($){
        $('.br-datepicker').datepicker({ dateFormat: 'yy-mm-dd' });
    });
    </script>
    <?php
}

/**
 * Render the Customer Report Page
 */
function br_customer_report_page_html() {
    if ( ! current_user_can( 'manage_woocommerce' ) ) {
        return;
    }

    $active_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'summary';
    ?>
    <div class="wrap br-wrap">
        <div class="br-header">
            <h1><?php _e( 'Customer Report', 'business-report' ); ?></h1>
        </div>

        <h2 class="nav-tab-wrapper">
            <a href="?page=br-customer-report&tab=summary" class="nav-tab <?php echo $active_tab == 'summary' ? 'nav-tab-active' : ''; ?>"><?php _e( 'Summary', 'business-report' ); ?></a>
            <a href="?page=br-customer-report&tab=list" class="nav-tab <?php echo $active_tab == 'list' ? 'nav-tab-active' : ''; ?>"><?php _e( 'Customer List', 'business-report' ); ?></a>
        </h2>

        <div class="br-page-content">
            <?php
            switch ( $active_tab ) {
                case 'summary':
                    br_customer_summary_tab_html();
                    break;
                case 'list':
                    br_customer_list_tab_html();
                    break;
                default:
                    br_customer_summary_tab_html();
                    break;
            }
            ?>
        </div>
    </div>
    <?php
}