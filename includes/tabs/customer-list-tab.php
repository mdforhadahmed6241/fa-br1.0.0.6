<?php
/**
 * Customer Report - List Tab (Table)
 *
 * @package BusinessReport
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Renders the HTML for the Customer List tab.
 */
function br_customer_list_tab_html() {
    $list_table = new BR_Customer_List_Table();
    $list_table->prepare_items();
    
    // Render Shared Filters
    if (function_exists('br_customer_render_filters')) {
        br_customer_render_filters('list');
    }
    ?>
    
    <form id="br-customer-list-form" method="get">
        <input type="hidden" name="page" value="br-customer-report" />
        <input type="hidden" name="tab" value="list" />
        
        <!-- Preserve filters in table navigation -->
        <?php
        $current_range = isset($_GET['range']) ? sanitize_key($_GET['range']) : 'today';
        $start_date = isset($_GET['start_date']) ? sanitize_text_field($_GET['start_date']) : '';
        $end_date = isset($_GET['end_date']) ? sanitize_text_field($_GET['end_date']) : '';
        ?>
        <input type="hidden" name="range" value="<?php echo esc_attr($current_range); ?>">
        <input type="hidden" name="start_date" value="<?php echo esc_attr($start_date); ?>">
        <input type="hidden" name="end_date" value="<?php echo esc_attr($end_date); ?>">

        <?php 
        // Search box is handled by the table class, render it here
        $list_table->search_box( __('Search Customers', 'business-report'), 'customer' ); 
        
        $list_table->display(); 
        ?>
    </form>
    <?php
}