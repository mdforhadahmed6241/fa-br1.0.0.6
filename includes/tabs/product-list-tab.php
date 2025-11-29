<?php
/**
 * Product Report - Product List (COGS) Tab
 * (Replaces old cogs-management.php page)
 *
 * @package BusinessReport
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Helper function to render taxonomy dropdown filter
 */
function br_product_taxonomy_filter_dropdown( $taxonomy_name, $select_name, $label ) {
    $current_value = isset($_GET[$select_name]) ? sanitize_text_field($_GET[$select_name]) : '';
    $terms = get_terms([
        'taxonomy'   => $taxonomy_name,
        'hide_empty' => false,
    ]);

    if (empty($terms) || is_wp_error($terms)) {
        return; // Don't show dropdown if taxonomy doesn't exist or has no terms
    }
    
    echo '<select name="' . esc_attr($select_name) . '" id="' . esc_attr($select_name) . '">';
    echo '<option value="">' . esc_html($label) . '</option>';
    foreach ($terms as $term) {
        printf(
            '<option value="%s" %s>%s</option>',
            esc_attr($term->slug),
            selected($current_value, $term->slug, false),
            esc_html($term->name)
        );
    }
    echo '</select>';
}

/**
 * Renders the HTML for the Product List (COGS) tab.
 * This replaces the old br_cogs_product_list_tab_html() function.
 */
function br_product_list_tab_html() {
    $cogs_list_table = new BR_COGS_List_Table();
    $cogs_list_table->prepare_items();
    ?>
    
    <!-- 
      This form uses method="get" to handle filtering and searching,
      which is standard for WP_List_Table.
    -->
    <form id="br-product-list-form" method="get">
        <input type="hidden" name="page" value="<?php echo esc_attr($_REQUEST['page']); ?>">
        <input type="hidden" name="tab" value="product_list">

        <div class="br-table-top-actions">
            <div class="br-filters-left">
                <?php
                // Add Category Filter
                br_product_taxonomy_filter_dropdown('product_cat', 'product_cat', __('All Categories', 'business-report'));

                // Add Brand Filter (checks if 'product_brand' taxonomy exists)
                if (taxonomy_exists('product_brand')) {
                    br_product_taxonomy_filter_dropdown('product_brand', 'product_brand', __('All Brands', 'business-report'));
                }

                // Add Filter button
                submit_button(__('Filter', 'business-report'), 'button', 'filter_action', false);

                // Add Search Box
                $cogs_list_table->search_box(__('Search Products', 'business-report'), 'product');
                ?>
            </div>
            <div class="br-filters-right">
                <a href="<?php echo admin_url('admin.php?page=br-settings&tab=cogs'); ?>" class="button button-primary"><?php _e('COGS Settings', 'business-report'); ?></a>
            </div>
        </div>
    </form>
    
    <!-- 
      A second form with method="post" is needed if you want to use
      bulk actions, but the search/filter form must be method="get".
      We wrap the table display in its own form for potential bulk actions.
    -->
    <form id="br-cogs-list-form-post" method="post">
        <?php
        // Display the table.
        $cogs_list_table->display();
        ?>
    </form>
    <?php
}
