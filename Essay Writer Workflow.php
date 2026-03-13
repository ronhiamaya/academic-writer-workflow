<?php
/**
 * Plugin Name: Academic Writer Workflow
 * Description: Connects WooCommerce orders with project management for academic writing sites. Create custom academic products with per-page pricing.
 * Version: 1.0.1
 * Author: Nzuri Web
 * Text Domain: academic-writer-workflow
 * Requires Plugins: woocommerce
 * WC requires at least: 5.0
 * WC tested up to: 8.0
 */

// Prevent direct access.
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('AWF_VERSION', '1.0.1');
define('AWF_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('AWF_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * Check if WooCommerce is active
 */
function awf_check_woocommerce() {
    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', function() {
            ?>
            <div class="notice notice-error">
                <p><?php _e('Academic Writer Workflow requires WooCommerce to be installed and activated.', 'academic-writer-workflow'); ?></p>
            </div>
            <?php
        });
        return false;
    }
    return true;
}

// Run the check
add_action('plugins_loaded', 'awf_check_woocommerce');

/**
 * 1. Add Custom Product Type to WooCommerce
 */
add_filter('product_type_selector', 'awf_add_academic_product_type');

function awf_add_academic_product_type($product_types) {
    $product_types['academic_paper'] = __('Academic Paper', 'academic-writer-workflow');
    return $product_types;
}

/**
 * Add the custom product type to the product data tabs
 */
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

/**
 * Output the content of the custom tab
 */
add_action('woocommerce_product_data_panels', 'awf_academic_product_tab_content');
function awf_academic_product_tab_content() {
    global $post;
    ?>
    <div id='academic_product_data' class='panel woocommerce_options_panel'>
        <div class='options_group'>
            <?php
            // Academic Level Dropdown
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
            
            // Price Per Page Field
            woocommerce_wp_text_input(array(
                'id'          => '_awf_price_per_page',
                'label'       => __('Price Per Page (' . get_woocommerce_currency_symbol() . ')', 'academic-writer-workflow'),
                'description' => __('Set a base price per page. The total will be calculated as (Pages * Price Per Page).', 'academic-writer-workflow'),
                'type'        => 'number',
                'custom_attributes' => array(
                    'step' => '0.01',
                    'min'  => '0'
                )
            ));
            
            // Minimum Pages Field
            woocommerce_wp_text_input(array(
                'id'          => '_awf_min_pages',
                'label'       => __('Minimum Pages', 'academic-writer-workflow'),
                'description' => __('Set the minimum number of pages allowed.', 'academic-writer-workflow'),
                'type'        => 'number',
                'default'     => '1',
                'custom_attributes' => array(
                    'step' => '1',
                    'min'  => '1'
                )
            ));
            ?>
        </div>
    </div>
    <?php
}

/**
 * Save the custom product fields
 */
add_action('woocommerce_process_product_meta', 'awf_save_academic_product_settings');
function awf_save_academic_product_settings($post_id) {
    $academic_level = isset($_POST['_awf_default_level']) ? sanitize_text_field($_POST['_awf_default_level']) : '';
    update_post_meta($post_id, '_awf_default_level', $academic_level);

    $price_per_page = isset($_POST['_awf_price_per_page']) ? floatval($_POST['_awf_price_per_page']) : '';
    update_post_meta($post_id, '_awf_price_per_page', $price_per_page);
    
    $min_pages = isset($_POST['_awf_min_pages']) ? intval($_POST['_awf_min_pages']) : 1;
    update_post_meta($post_id, '_awf_min_pages', $min_pages);
}

/**
 * 2. Add Custom Order Meta Box for Academic Details
 */
add_action('add_meta_boxes', 'awf_add_order_metabox');

function awf_add_order_metabox() {
    // Get the correct screen ID for orders (HPOS compatible)
    $screen = class_exists('\Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController') 
        ? wc_get_page_screen_id('shop-order') 
        : 'shop_order';

    add_meta_box(
        'awf_academic_details',
        __('Academic Order Details', 'academic-writer-workflow'),
        'awf_render_order_metabox',
        $screen,
        'side',
        'high'
    );
}

function awf_render_order_metabox($post_or_order_object) {
    // Get order object in an HPOS-compatible way
    $order = ($post_or_order_object instanceof WP_Post) 
        ? wc_get_order($post_or_order_object->ID) 
        : $post_or_order_object;
    
    if (!$order) {
        echo '<p>' . __('Order not found.', 'academic-writer-workflow') . '</p>';
        return;
    }
    
    $order_id = $order->get_id();

    // Use nonce for security
    wp_nonce_field('awf_save_meta_data', 'awf_meta_nonce');

    // Retrieve existing values
    $academic_level = $order->get_meta('_awf_academic_level');
    $topic = $order->get_meta('_awf_topic');
    $pages = $order->get_meta('_awf_pages');
    $deadline = $order->get_meta('_awf_deadline');
    $instructions = $order->get_meta('_awf_instructions');
    ?>
    <p>
        <label for="awf_academic_level"><?php esc_html_e('Academic Level:', 'academic-writer-workflow'); ?></label>
        <select name="awf_academic_level" id="awf_academic_level" style="width:100%; margin-top:5px;">
            <option value=""><?php esc_html_e('-- Select Level --', 'academic-writer-workflow'); ?></option>
            <option value="high_school" <?php selected($academic_level, 'high_school'); ?>><?php esc_html_e('High School', 'academic-writer-workflow'); ?></option>
            <option value="college" <?php selected($academic_level, 'college'); ?>><?php esc_html_e('College', 'academic-writer-workflow'); ?></option>
            <option value="university" <?php selected($academic_level, 'university'); ?>><?php esc_html_e('University', 'academic-writer-workflow'); ?></option>
            <option value="masters" <?php selected($academic_level, 'masters'); ?>><?php esc_html_e('Master\'s', 'academic-writer-workflow'); ?></option>
            <option value="phd" <?php selected($academic_level, 'phd'); ?>><?php esc_html_e('PhD', 'academic-writer-workflow'); ?></option>
        </select>
    </p>
    <p>
        <label for="awf_topic"><?php esc_html_e('Topic/Title:', 'academic-writer-workflow'); ?></label>
        <input type="text" name="awf_topic" id="awf_topic" value="<?php echo esc_attr($topic); ?>" style="width:100%; margin-top:5px;" />
    </p>
     <p>
        <label for="awf_pages"><?php esc_html_e('Number of Pages:', 'academic-writer-workflow'); ?></label>
        <input type="number" name="awf_pages" id="awf_pages" value="<?php echo esc_attr($pages ?: '1'); ?>" style="width:100%; margin-top:5px;" min="1" />
    </p>
    <p>
        <label for="awf_deadline"><?php esc_html_e('Deadline:', 'academic-writer-workflow'); ?></label>
        <input type="datetime-local" name="awf_deadline" id="awf_deadline" value="<?php echo esc_attr($deadline); ?>" style="width:100%; margin-top:5px;" />
    </p>
    <p>
        <label for="awf_instructions"><?php esc_html_e('Special Instructions:', 'academic-writer-workflow'); ?></label>
        <textarea name="awf_instructions" id="awf_instructions" rows="3" style="width:100%; margin-top:5px;"><?php echo esc_textarea($instructions); ?></textarea>
    </p>
    <?php
}

/**
 * Save the meta data using HPOS-compatible methods
 */
add_action('woocommerce_before_order_object_save', 'awf_save_order_metabox_data', 10, 2);

function awf_save_order_metabox_data($order, $data_store) {
    // Only save if this is from our metabox
    if (!isset($_POST['awf_meta_nonce']) || !wp_verify_nonce($_POST['awf_meta_nonce'], 'awf_save_meta_data')) {
        return;
    }

    // Check user permissions
    if (!current_user_can('edit_shop_orders') && !current_user_can('manage_woocommerce')) {
        return;
    }

    // Save/update meta data using order methods
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
    if (isset($_POST['awf_instructions'])) {
        $order->update_meta_data('_awf_instructions', sanitize_textarea_field($_POST['awf_instructions']));
    }
}

/**
 * 3. Add custom fields to checkout page for frontend ordering
 */
add_action('woocommerce_before_order_notes', 'awf_checkout_fields');

function awf_checkout_fields($checkout) {
    // Check if cart contains academic products
    $has_academic_product = false;
    foreach (WC()->cart->get_cart() as $cart_item) {
        $product = $cart_item['data'];
        if ($product->get_type() === 'academic_paper') {
            $has_academic_product = true;
            break;
        }
    }
    
    if (!$has_academic_product) {
        return;
    }
    
    echo '<div id="awf_academic_fields"><h3>' . __('Paper Details', 'academic-writer-workflow') . '</h3>';
    
    woocommerce_form_field('awf_topic', array(
        'type'        => 'text',
        'class'       => array('form-row-wide'),
        'label'       => __('Paper Topic/Title', 'academic-writer-workflow'),
        'placeholder' => __('Enter the topic or title of your paper', 'academic-writer-workflow'),
        'required'    => true,
    ), $checkout->get_value('awf_topic'));
    
    woocommerce_form_field('awf_academic_level', array(
        'type'        => 'select',
        'class'       => array('form-row-wide'),
        'label'       => __('Academic Level', 'academic-writer-workflow'),
        'required'    => true,
        'options'     => array(
            ''            => __('-- Select Level --', 'academic-writer-workflow'),
            'high_school' => __('High School', 'academic-writer-workflow'),
            'college'     => __('College', 'academic-writer-workflow'),
            'university'  => __('University', 'academic-writer-workflow'),
            'masters'     => __('Master\'s', 'academic-writer-workflow'),
            'phd'         => __('PhD', 'academic-writer-workflow'),
        )
    ), $checkout->get_value('awf_academic_level'));
    
    woocommerce_form_field('awf_pages', array(
        'type'        => 'number',
        'class'       => array('form-row-wide'),
        'label'       => __('Number of Pages', 'academic-writer-workflow'),
        'required'    => true,
        'custom_attributes' => array(
            'min' => '1',
            'step' => '1'
        )
    ), $checkout->get_value('awf_pages'));
    
    woocommerce_form_field('awf_deadline', array(
        'type'        => 'datetime-local',
        'class'       => array('form-row-wide'),
        'label'       => __('Deadline', 'academic-writer-workflow'),
        'required'    => true,
    ), $checkout->get_value('awf_deadline'));
    
    woocommerce_form_field('awf_instructions', array(
        'type'        => 'textarea',
        'class'       => array('form-row-wide'),
        'label'       => __('Special Instructions', 'academic-writer-workflow'),
        'placeholder' => __('Any additional requirements or instructions for the writer', 'academic-writer-workflow'),
    ), $checkout->get_value('awf_instructions'));
    
    echo '</div>';
}

/**
 * Validate checkout fields
 */
add_action('woocommerce_checkout_process', 'awf_checkout_fields_validate');

function awf_checkout_fields_validate() {
    // Check if cart contains academic products
    $has_academic_product = false;
    foreach (WC()->cart->get_cart() as $cart_item) {
        $product = $cart_item['data'];
        if ($product->get_type() === 'academic_paper') {
            $has_academic_product = true;
            break;
        }
    }
    
    if (!$has_academic_product) {
        return;
    }
    
    if (!$_POST['awf_topic']) {
        wc_add_notice(__('Please enter the paper topic.', 'academic-writer-workflow'), 'error');
    }
    if (!$_POST['awf_academic_level']) {
        wc_add_notice(__('Please select academic level.', 'academic-writer-workflow'), 'error');
    }
    if (!$_POST['awf_pages'] || intval($_POST['awf_pages']) < 1) {
        wc_add_notice(__('Please enter a valid number of pages.', 'academic-writer-workflow'), 'error');
    }
    if (!$_POST['awf_deadline']) {
        wc_add_notice(__('Please select a deadline.', 'academic-writer-workflow'), 'error');
    }
}

/**
 * Save checkout fields to order meta
 */
add_action('woocommerce_checkout_update_order_meta', 'awf_save_checkout_fields');

function awf_save_checkout_fields($order_id) {
    if (!empty($_POST['awf_topic'])) {
        update_post_meta($order_id, '_awf_topic', sanitize_text_field($_POST['awf_topic']));
    }
    if (!empty($_POST['awf_academic_level'])) {
        update_post_meta($order_id, '_awf_academic_level', sanitize_text_field($_POST['awf_academic_level']));
    }
    if (!empty($_POST['awf_pages'])) {
        update_post_meta($order_id, '_awf_pages', intval($_POST['awf_pages']));
    }
    if (!empty($_POST['awf_deadline'])) {
        update_post_meta($order_id, '_awf_deadline', sanitize_text_field($_POST['awf_deadline']));
    }
    if (!empty($_POST['awf_instructions'])) {
        update_post_meta($order_id, '_awf_instructions', sanitize_textarea_field($_POST['awf_instructions']));
    }
}

/**
 * Display custom fields in order details (admin)
 */
add_action('woocommerce_admin_order_data_after_billing_address', 'awf_display_admin_order_meta');

function awf_display_admin_order_meta($order) {
    $topic = $order->get_meta('_awf_topic');
    $academic_level = $order->get_meta('_awf_academic_level');
    $pages = $order->get_meta('_awf_pages');
    $deadline = $order->get_meta('_awf_deadline');
    $instructions = $order->get_meta('_awf_instructions');
    
    if ($topic || $academic_level || $pages) {
        echo '<div style="clear:both; padding:10px 0;"><h4>' . __('Academic Paper Details', 'academic-writer-workflow') . '</h4>';
        
        if ($topic) {
            echo '<p><strong>' . __('Topic:', 'academic-writer-workflow') . '</strong> ' . esc_html($topic) . '</p>';
        }
        if ($academic_level) {
            $level_names = array(
                'high_school' => 'High School',
                'college' => 'College',
                'university' => 'University',
                'masters' => 'Master\'s',
                'phd' => 'PhD'
            );
            $level_display = isset($level_names[$academic_level]) ? $level_names[$academic_level] : $academic_level;
            echo '<p><strong>' . __('Academic Level:', 'academic-writer-workflow') . '</strong> ' . esc_html($level_display) . '</p>';
        }
        if ($pages) {
            echo '<p><strong>' . __('Pages:', 'academic-writer-workflow') . '</strong> ' . esc_html($pages) . '</p>';
        }
        if ($deadline) {
            echo '<p><strong>' . __('Deadline:', 'academic-writer-workflow') . '</strong> ' . esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($deadline))) . '</p>';
        }
        if ($instructions) {
            echo '<p><strong>' . __('Instructions:', 'academic-writer-workflow') . '</strong> ' . nl2br(esc_html($instructions)) . '</p>';
        }
        echo '</div>';
    }
}

/**
 * 4. WPNakama Integration (optional - only if plugin exists)
 */
add_action('woocommerce_order_status_completed', 'awf_create_wpnakama_project', 10, 1);

function awf_create_wpnakama_project($order_id) {
    // Check if WPNakama is active
    if (!function_exists('wpnakama_create_board') && !function_exists('wpnakama_create_project')) {
        // Silently fail - no WPNakama installed
        return;
    }
    
    $order = wc_get_order($order_id);
    if (!$order) {
        return;
    }

    // Check if a project was already created for this order
    if ($order->get_meta('_awf_nakama_project_id')) {
        return; // Project already exists
    }

    // Get the academic details
    $topic = $order->get_meta('_awf_topic');
    $pages = $order->get_meta('_awf_pages');
    $deadline = $order->get_meta('_awf_deadline');
    $academic_level = $order->get_meta('_awf_academic_level');
    $customer_email = $order->get_billing_email();

    // Basic validation
    if (empty($topic)) {
        $order->add_order_note(__('Could not create project: Topic is missing.', 'academic-writer-workflow'));
        return;
    }

    // Get customer user
    $client_user = get_user_by('email', $customer_email);
    if (!$client_user) {
        $order->add_order_note(__('Client user not found for project creation.', 'academic-writer-workflow'));
        return;
    }

    // Try different possible function names
    $project_id = null;
    
    if (function_exists('wpnakama_create_board')) {
        // WPNakama Kanban style
        $project_id = wpnakama_create_board([
            'title'       => sprintf(__('Order #%d: %s', 'academic-writer-workflow'), $order_id, $topic),
            'description' => sprintf(
                __('Academic Level: %s | Pages: %d | Deadline: %s', 'academic-writer-workflow'),
                $academic_level,
                $pages,
                $deadline
            ),
            'type'        => 'kanban',
            'client_id'   => $client_user->ID,
        ]);
    } elseif (function_exists('wpnakama_create_project')) {
        // Alternative function name
        $project_id = wpnakama_create_project([
            'name'        => sprintf(__('Order #%d: %s', 'academic-writer-workflow'), $order_id, $topic),
            'description' => sprintf(
                __('Academic Level: %s | Pages: %d | Deadline: %s', 'academic-writer-workflow'),
                $academic_level,
                $pages,
                $deadline
            ),
            'user_id'     => $client_user->ID,
        ]);
    }

    if ($project_id && !is_wp_error($project_id)) {
        $order->update_meta_data('_awf_nakama_project_id', $project_id);
        $order->add_order_note(__('Project created successfully in WPNakama.', 'academic-writer-workflow'));
        $order->save();
    } else {
        $error_message = is_wp_error($project_id) ? $project_id->get_error_message() : 'Unknown error';
        $order->add_order_note(sprintf(__('Failed to create project: %s', 'academic-writer-workflow'), $error_message));
        $order->save();
    }
}

/**
 * Add settings link on plugin page
 */
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'awf_add_settings_link');
function awf_add_settings_link($links) {
    $settings_link = '<a href="' . admin_url('admin.php?page=wc-settings&tab=products&section=academic') . '">' . __('Settings', 'academic-writer-workflow') . '</a>';
    array_push($links, $settings_link);
    return $links;
}

/**
 * Activation hook
 */
register_activation_hook(__FILE__, 'awf_activate');
function awf_activate() {
    // Check WooCommerce on activation
    if (!class_exists('WooCommerce')) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die(__('Academic Writer Workflow requires WooCommerce to be installed and activated.', 'academic-writer-workflow'));
    }
    
    // Flush rewrite rules
    flush_rewrite_rules();
}

/**
 * Deactivation hook
 */
register_deactivation_hook(__FILE__, 'awf_deactivate');
function awf_deactivate() {
    flush_rewrite_rules();
}
