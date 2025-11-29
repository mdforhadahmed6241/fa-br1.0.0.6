<?php
/**
 * Product Report - Summary Tab
 *
 * @package BusinessReport
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Helper function to get all inventory data (both managed and unmanaged).
 * This function will be used by both the KPIs and the list table to ensure data consistency.
 *
 * @return array An array of product/variation data.
 */
function br_get_inventory_items() {
    $args = [
        'post_type'      => ['product', 'product_variation'],
        'posts_per_page' => -1,
        'post_status'    => 'publish',
        'fields'         => 'ids', // Get only IDs to optimize query
    ];

    $product_query = new WP_Query( $args );
    $item_ids = $product_query->posts;
    $inventory_data = [];

    if ( empty( $item_ids ) ) {
        return $inventory_data;
    }

    // Loop through IDs and get product objects
    foreach ( $item_ids as $item_id ) {
        $product = wc_get_product( $item_id );
        if ( ! $product ) {
            continue;
        }

        // Skip parent variable products, we only want simple/variations
        if ( $product->is_type( 'variable' ) ) {
            continue;
        }
        
        $stock_quantity = $product->get_stock_quantity();
        $manage_stock = $product->get_manage_stock(); // 'yes' or 'no' (bool)

        // Get all category term IDs for this product
        // For variations, get parent categories
        $product_id_for_cats = $product->is_type('variation') ? $product->get_parent_id() : $product->get_id();
        $category_ids = wc_get_product_term_ids( $product_id_for_cats, 'product_cat' );

        $inventory_data[] = [
            'id'             => $product->get_id(),
            'name'           => $product->get_name(),
            'price'          => (float) $product->get_price(),
            'cost'           => (float) br_get_product_cost( $product->get_id() ),
            'stock_quantity' => $stock_quantity ? (int) $stock_quantity : 0,
            'manage_stock'   => $manage_stock,
            'stock_status'   => $product->get_stock_status(), // 'instock', 'outofstock'
            'category_ids'   => $category_ids,
        ];
    }
    
    return $inventory_data;
}


/**
 * Renders the HTML for the Product Summary tab.
 * Uses new helper function 'br_get_inventory_items'
 */
function br_product_summary_tab_html() {

    // 1. Get all inventory data
    $inventory_items = br_get_inventory_items();

    // 2. Calculate KPI data
    $kpi_total_products_in_stock = 0;
    $kpi_total_product_price = 0;
    $kpi_total_cost_price = 0;
    $kpi_expected_profit = 0;
    $total_managed_stock_products = 0; // Count of unique products with managed stock

    // Get total categories count
    $total_categories_count = (int) wp_count_terms( ['taxonomy' => 'product_cat', 'hide_empty' => false] );
    
    // Get total products/variations count (all published)
    $total_products_count = count($inventory_items);

    if ( ! empty( $inventory_items ) ) {
        foreach ( $inventory_items as $item ) {
            
            // Check for "Managed Stock" items
            // We only sum values for items that are in stock, have managed stock, and have quantity > 0
            if ( $item['manage_stock'] === true && $item['stock_status'] === 'instock' && $item['stock_quantity'] > 0 ) {
                $total_managed_stock_products++; // This is the count for the "Total Products" small KPI
                $stock_qty = $item['stock_quantity'];
                
                $kpi_total_products_in_stock += $stock_qty;
                $kpi_total_product_price += $item['price'] * $stock_qty;
                $kpi_total_cost_price += $item['cost'] * $stock_qty;
            }
        }
    }
    
    $kpi_expected_profit = $kpi_total_product_price - $kpi_total_cost_price;

    ?>
    <div class="br-product-kpi-grid br-kpi-grid">
        <div class="br-kpi-card br-product-kpi-card">
            <h4><?php _e( 'Total Products In Stock', 'business-report' ); ?></h4>
            <p><?php echo esc_html( number_format_i18n( $kpi_total_products_in_stock ) ); ?></p>
        </div>
        <div class="br-kpi-card br-product-kpi-card">
            <h4><?php _e( 'Total Product Price', 'business-report' ); ?></h4>
            <p><?php echo wc_price( $kpi_total_product_price ); ?></p>
        </div>
        <div class="br-kpi-card br-product-kpi-card">
            <h4><?php _e( 'Total Cost Price', 'business-report' ); ?></h4>
            <p><?php echo wc_price( $kpi_total_cost_price ); ?></p>
        </div>
        <div class="br-kpi-card br-product-kpi-card">
            <h4><?php _e( 'Expected Profit', 'business-report' ); ?></h4>
            <p><?php echo wc_price( $kpi_expected_profit ); ?></p>
        </div>
    </div>

    <div class="br-inventory-breakdown-wrapper">
        <div class="br-inventory-header">
            <div class="br-inventory-header-left">
                <h3><?php _e( 'Inventory Breakdown By Category', 'business-report' ); ?></h3>
                <p><?php _e( 'Analyse inventory by product categories', 'business-report' ); ?></p>
            </div>
            <div class="br-inventory-header-right">
                <div class="br-inventory-kpi">
                    <span><?php _e( 'Total Products', 'business-report' ); ?></span>
                    <strong><?php echo esc_html( number_format_i18n( $total_products_count ) ); ?></strong>
                </div>
                <div class="br-inventory-kpi">
                    <span><?php _e( 'Total Categories', 'business-report' ); ?></span>
                    <strong><?php echo esc_html( number_format_i18n( $total_categories_count ) ); ?></strong>
                </div>
            </div>
        </div>

        <?php
        // Pass the already fetched inventory data to the list table
        $summary_list_table = new BR_Product_Summary_List_Table();
        $summary_list_table->set_inventory_data( $inventory_items ); // Pass data to the table
        $summary_list_table->prepare_items();
        ?>
        <form id="br-product-summary-list-form" method="post">
            <?php $summary_list_table->display(); ?>
        </form>
    </div>
    <?php
}

