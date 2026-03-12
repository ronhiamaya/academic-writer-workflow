<?php
/**
 * Plugin Name: Academic Writer Workflow
 * Description: Glues WooCommerce orders to a project management plugin for academic writing sites.
 * Version: 1.0
 * Author: Your Name
 * Text Domain: academic-writer-workflow
 */

// Prevent direct access.
if (!defined('ABSPATH')) {
    exit;
}

// Check if WooCommerce is active.
if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {

    /**
     * 1. Add Custom Order Meta Box for Academic Details (Topic, Pages, etc.)
     */
    add_action('add_meta_boxes', 'awf_add_order_metabox');

    function awf_add_metabox() {
        global $post;
        // Use the HPOS-compatible screen check [citation:6]
        $screen = class_exists('\Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController') && wc_get_container()->get(Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController::class)->custom_orders_table_usage_is_enabled()
            ? wc_get_page_screen_id('shop-order')
            : 'shop_order';

        add_meta_box(
            'awf_academic_details',
            __('Academic Order Details', 'academic-writer-workflow'),
            'awf_render_metabox',
            $screen, // Use the dynamic screen
            'side',
            'high'
        );
    }

    function awf_render_metabox($post_or_order_object) {
        // Get order object in an HPOS-compatible way [citation:6]
        $order = ($post_or_order_object instanceof WP_Post) ? wc_get_order($post_or_order_object->ID) : $post_or_order_object;
        $order_id = $order->get_id();

        // Use nonce for security.
        wp_nonce_field('awf_save_meta_data', 'awf_meta_nonce');

        // Retrieve existing values.
        $academic_level = $order->get_meta('_awf_academic_level');
        $topic = $order->get_meta('_awf_topic');
        $pages = $order->get_meta('_awf_pages');
        $deadline = $order->get_meta('_awf_deadline');
        ?>
        <p>
            <label for="awf_academic_level"><?php esc_html_e('Academic Level:', 'academic-writer-workflow'); ?></label>
            <select name="awf_academic_level" id="awf_academic_level" style="width:100%;">
                <option value="high_school" <?php selected($academic_level, 'high_school'); ?>><?php esc_html_e('High School', 'academic-writer-workflow'); ?></option>
                <option value="college" <?php selected($academic_level, 'college'); ?>><?php esc_html_e('College', 'academic-writer-workflow'); ?></option>
                <option value="university" <?php selected($academic_level, 'university'); ?>><?php esc_html_e('University', 'academic-writer-workflow'); ?></option>
                <option value="masters" <?php selected($academic_level, 'masters'); ?>><?php esc_html_e('Master\'s', 'academic-writer-workflow'); ?></option>
                <option value="phd" <?php selected($academic_level, 'phd'); ?>><?php esc_html_e('PhD', 'academic-writer-workflow'); ?></option>
            </select>
        </p>
        <p>
            <label for="awf_topic"><?php esc_html_e('Topic/Title:', 'academic-writer-workflow'); ?></label>
            <input type="text" name="awf_topic" id="awf_topic" value="<?php echo esc_attr($topic); ?>" style="width:100%;" />
        </p>
         <p>
            <label for="awf_pages"><?php esc_html_e('Number of Pages:', 'academic-writer-workflow'); ?></label>
            <input type="number" name="awf_pages" id="awf_pages" value="<?php echo esc_attr($pages ?: '1'); ?>" style="width:100%;" />
        </p>
        <p>
            <label for="awf_deadline"><?php esc_html_e('Deadline:', 'academic-writer-workflow'); ?></label>
            <input type="datetime-local" name="awf_deadline" id="awf_deadline" value="<?php echo esc_attr($deadline); ?>" style="width:100%;" />
        </p>
        <?php
    }

    // Save the meta data using HPOS-compatible methods [citation:6]
    add_action('woocommerce_before_order_object_save', 'awf_save_metabox_data');

    function awf_save_metabox_data($order) {
        // Verify nonce.
        if (!isset($_POST['awf_meta_nonce']) || !wp_verify_nonce($_POST['awf_meta_nonce'], 'awf_save_meta_data')) {
            return;
        }

        // Check user permissions (HPOS compatible).
        if (!current_user_can('edit_shop_orders') && !current_user_can('manage_woocommerce')) {
            return;
        }

        // Save/update meta data using order methods.
        if (isset($_POST['awf_academic_level'])) {
            $order->update_meta_data('_awf_academic_level', sanitize_text_field($_POST['awf_academic_level']));
        }
        if (isset($_POST['awf_topic'])) {
            $order->update_meta_data('_awf_topic', sanitize_text_field($_POST['awf_topic']));
        }
        if (isset($_POST['awf_pages'])) {
            $order->update_meta_data('_awf_pages', intval($_POST['awf_pages']));
        }
        if (isset($_POST['awf_deadline'])) {
            $order->update_meta_data('_awf_deadline', sanitize_text_field($_POST['awf_deadline']));
        }
    }

    /**
     * 2. Automate Project Creation in WPNakama on Payment Complete
     */
    add_action('woocommerce_order_status_completed', 'awf_create_wpnakama_project', 10, 1);

    function awf_create_wpnakama_project($order_id) {
        $order = wc_get_order($order_id);

        // Check if a project was already created for this order.
        if ($order->get_meta('_awf_nakama_project_id')) {
            return; // Project already exists.
        }

        // Get the academic details.
        $topic = $order->get_meta('_awf_topic');
        $pages = $order->get_meta('_awf_pages');
        $deadline = $order->get_meta('_awf_deadline');
        $customer_email = $order->get_billing_email();

        // Basic validation.
        if (empty($topic)) {
            $order->add_order_note(__('Could not create WPNakama project: Topic is missing.', 'academic-writer-workflow'));
            return;
        }

        // --- WPNakama Integration Logic ---
        // This is a conceptual example using WPNakama's potential hooks/APIs.
        // You'll need to consult WPNakama's developer docs for exact function names.

        // 1. Find or create a client user in WPNakama based on customer email.
        $client_user = get_user_by('email', $customer_email);
        if (!$client_user) {
            // Optionally create a user, but usually the client already has a WP account from checkout.
            $order->add_order_note(__('Client user not found for WPNakama project.', 'academic-writer-workflow'));
            return;
        }

        // 2. Create a new project/board in WPNakama.
        // This is a HYPOTHETICAL function based on the plugin's description [citation:4].
        if (function_exists('wpnakama_create_board')) {
            $board_data = [
                'title'       => sprintf(__('Order #%d: %s', 'academic-writer-workflow'), $order_id, $topic),
                'description' => sprintf(
                    __('Academic Level: %s | Pages: %d | Deadline: %s', 'academic-writer-workflow'),
                    $order->get_meta('_awf_academic_level'),
                    $pages,
                    $deadline
                ),
                'type'        => 'kanban', // Assuming Kanban board
                'client_id'   => $client_user->ID, // Assign client to this board
            ];
            $board_id = wpnakama_create_board($board_data);

            if ($board_id && !is_wp_error($board_id)) {
                // 3. Create an initial task for the writer.
                $task_id = wpnakama_create_task([
                    'board_id'    => $board_id,
                    'title'       => __('Write Paper', 'academic-writer-workflow'),
                    'description' => __('Use the order details to complete this paper.', 'academic-writer-workflow'),
                    'deadline'    => $deadline,
                ]);

                // 4. Store the project ID in the order meta for future reference.
                $order->update_meta_data('_awf_nakama_project_id', $board_id);
                if (isset($task_id)) {
                    $order->update_meta_data('_awf_nakama_task_id', $task_id);
                }
                $order->add_order_note(__('WPNakama project created successfully.', 'academic-writer-workflow'));

            } else {
                $order->add_order_note(__('Failed to create WPNakama project.', 'academic-writer-workflow'));
            }
        } else {
            $order->add_order_note(__('WPNakama plugin is not active or its functions are not available.', 'academic-writer-workflow'));
        }

        // IMPORTANT: Save the order after updating meta data.
        $order->save();
    }

    /**
     * 3. (Optional) Add a custom product type for "Academic Writing"
     */
    add_filter('woocommerce_products', 'awf_add_academic_product_type');

    function awf_add_academic_product_type($product_types) {
        $product_types['academic_paper'] = __('Academic Paper', 'academic-writer-workflow');
        return $product_types;
    }

    // Add the custom product type to the product data tabs
    add_filter('woocommerce_product_data_tabs', 'awf_academic_product_tab');
    function awf_academic_product_tab($tabs) {
        $tabs['academic'] = array(
            'label'    => __('Academic Settings', 'academic-writer-workflow'),
            'target'   => 'academic_product_data',
            'class'    => array('show_if_academic_paper'),
            'priority' => 21,
        );
        return $tabs;
    }

    // Output the content of the custom tab
    add_action('woocommerce_product_data_panels', 'awf_academic_product_tab_content');
    function awf_academic_product_tab_content() {
        global $post;
        ?>
        <div id='academic_product_data' class='panel woocommerce_options_panel'>
            <div class='options_group'>
                <?php
                woocommerce_wp_select(array(
                    'id'          => '_awf_default_level',
                    'label'       => __('Default Academic Level', 'academic-writer-workflow'),
                    'options'     => array(
                        'high_school' => __('High School', 'academic-writer-workflow'),
                        'college'     => __('College', 'academic-writer-workflow'),
                        'university'  => __('University', 'academic-writer-workflow'),
                        'masters'     => __('Master\'s', 'academic-writer-workflow'),
                        'phd'         => __('PhD', 'academic-writer-workflow'),
                    ),
                    'desc_tip'    => true,
                    'description' => __('Select the default academic level for this product.', 'academic-writer-workflow'),
                ));
                woocommerce_wp_text_input(array(
                    'id'          => '_awf_price_per_page',
                    'label'       => __('Price Per Page ('.$currency = get_woocommerce_currency_symbol().')', 'academic-writer-workflow'),
                    'description' => __('Set a base price per page. The total will be calculated as (Pages * Price Per Page).', 'academic-writer-workflow'),
                    'type'        => 'number',
                    'custom_attributes' => array(
                        'step' => 'any',
                        'min' => '0'
                    )
                ));
                ?>
            </div>
        </div>
        <?php
    }

    // Save the custom fields
    add_action('woocommerce_process_product_meta', 'awf_save_academic_product_settings');
    function awf_save_academic_product_settings($post_id) {
        $academic_level = isset($_POST['_awf_default_level']) ? sanitize_text_field($_POST['_awf_default_level']) : '';
        update_post_meta($post_id, '_awf_default_level', $academic_level);

        $price_per_page = isset($_POST['_awf_price_per_page']) ? floatval($_POST['_awf_price_per_page']) : '';
        update_post_meta($post_id, '_awf_price_per_page', $price_per_page);
    }
}