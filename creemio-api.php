<?php        
/** 
 * Plugin Name: Creem.io API to WordPress Sync
 * Plugin URI: https://github.com/sinanisler/creem-io-api
 * Description: Automatically create WordPress users from Creem.io sales
 * Version: 0.3
 * Author: sinanisler
 * Author URI: https://github.com/sinanisler
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: snn
 */

if (!defined('ABSPATH')) {
    exit;
}

// Include GitHub auto-update functionality
require_once plugin_dir_path(__FILE__) . 'github-update.php';

class Creem_API_WordPress {

    private $option_name = 'creem_api_settings';
    private $log_option_name = 'creem_api_logs';
    
    public function __construct() {
        // Admin menu
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        
        // Cron job for API-based sales fetching
        add_action('creem_api_check_sales', array($this, 'check_recent_sales'));
        add_filter('cron_schedules', array($this, 'add_custom_cron_interval'));

        // Activation/Deactivation
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));

        // AJAX handlers
        add_action('wp_ajax_creem_test_api', array($this, 'test_api_connection'));
        add_action('wp_ajax_creem_clear_logs', array($this, 'clear_logs'));
        add_action('wp_ajax_creem_fetch_products', array($this, 'fetch_products'));
        add_action('wp_ajax_creem_uninstall_plugin', array($this, 'uninstall_plugin_data'));
        
        // Add admin styles
        add_action('admin_head', array($this, 'admin_styles'));
        
        // Add plugin row meta for uninstall link
        add_filter('plugin_row_meta', array($this, 'add_plugin_row_meta'), 10, 2);

        // Subscription renewal redirect
        add_action('template_redirect', array($this, 'check_subscription_renewal_redirect'));

        // Shortcode: [creem_billing_link]
        add_shortcode('creem_billing_link', array($this, 'creem_billing_link_shortcode'));
    }
    
    /**
     * Add admin styles
     */
    public function admin_styles() {
        $screen = get_current_screen();
        if (strpos($screen->id, 'creem-api') === false) {
            return;
        }
        ?>
<style>
.snn-creem-stats-grid { display: grid; grid-template-columns: 1fr 1fr 1fr 1fr; gap: 20px; margin-bottom: 30px; }
.snn-creem-stat-card { background: #fff; color: #333; padding: 25px; border-radius: 8px; border: 1px solid #ddd; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
.snn-creem-stat-card-header { font-size: 14px; margin-bottom: 10px; color: #666; }
.snn-creem-stat-card-value { font-size: 22px; font-weight: bold; color: #000; }
.snn-creem-stat-card-footer { font-size: 12px; margin-top: 10px; color: #666; }
.snn-creem-section { padding: 20px; background: white; border: 1px solid #ccd0d4; margin-bottom: 20px; border-radius: 4px; }
.snn-creem-section h2 { margin-top: 0; padding-bottom: 10px; border-bottom: 2px solid #000; }
.snn-creem-recent-logs-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
.snn-creem-recent-logs-header h2 { margin: 0; border-bottom: none; }
.snn-creem-products-notice { background: #f9f9f9; border-left: 4px solid #000; padding: 15px; margin: 20px 0; }
.snn-creem-products-loading { display: none; text-align: center; padding: 20px; }
.snn-creem-products-list { display: none; }
.snn-creem-products-list table { margin-top: 20px; }
.snn-creem-products-list th { padding: 10px; background: #f5f5f5; font-weight: 600; }
.snn-creem-products-list td { padding: 10px; vertical-align: top; }
.snn-creem-product-roles-checkboxes { }
.snn-creem-product-roles-checkboxes label { display: block; margin: 3px 0; font-weight: normal; }
.snn-creem-api-info { background: #f0f8ff; padding: 15px; border-left: 4px solid #000; }
.snn-creem-email-tags { margin-top: 15px; padding: 15px; background: #f0f0f1; border-left: 4px solid #000; }
.snn-creem-email-tags h4 { margin-top: 0; }
.snn-creem-email-tags ul { margin: 10px 0; padding-left: 20px; }
.snn-creem-search-filters { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 15px; }
.snn-creem-search-filters input { width: 100%; }
.snn-creem-search-actions { display: flex; gap: 10px; }
.snn-creem-search-total { margin-left: auto; align-self: center; color: #666; }
.snn-creem-log-entry { background: white; padding: 15px; margin-bottom: 10px; border: 1px solid #ccd0d4; border-radius: 4px; }
.snn-creem-log-header { cursor: pointer; display: flex; justify-content: space-between; align-items: center; }
.snn-creem-log-timestamp { margin-left: 15px; color: #666; font-size: 12px; }
.snn-creem-log-details { display: none; margin-top: 10px; padding-top: 10px; border-top: 1px solid #eee; }
.snn-creem-log-details pre { background: #f5f5f5; padding: 10px; overflow-x: auto; font-size: 12px; }
.snn-creem-user-entry { background: white; padding: 15px; margin-bottom: 10px; border: 1px solid #ccd0d4; border-radius: 4px; }
.snn-creem-user-entry:hover { box-shadow: 0 2px 4px rgba(0,0,0,0.1); transition: box-shadow 0.2s; }
.snn-creem-user-header { cursor: pointer; display: flex; justify-content: space-between; align-items: center; gap: 15px; }
.snn-creem-user-header:hover { background: #f9f9f9; }
.snn-creem-user-main-info { flex: 1; display: flex; align-items: center; gap: 15px; }
.snn-creem-user-meta-info { display: flex; gap: 15px; align-items: center; font-size: 12px; color: #666; }
.snn-creem-user-details { display: none; margin-top: 15px; padding-top: 15px; border-top: 1px solid #eee; }
.snn-creem-user-details-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
.snn-creem-user-actions { margin-top: 20px; padding-top: 15px; border-top: 1px solid #eee; display: flex; gap: 10px; }
.snn-creem-user-details h3 { margin-top: 0; font-size: 14px; color: #000; }
.snn-creem-user-details table { font-size: 13px; }
.snn-creem-user-details th { width: 40%; padding: 8px; background: #f9f9f9; }
.snn-creem-user-details td { padding: 8px; }
.snn-creem-purchase-history { max-height: 200px; overflow-y: auto; border: 1px solid #ddd; border-radius: 4px; }
.snn-creem-purchase-history table { font-size: 12px; }
.snn-creem-purchase-history th { padding: 6px; background: #f5f5f5; }
.snn-creem-purchase-history td { padding: 6px; }
.snn-creem-raw-data { background: #f5f5f5; padding: 10px; border-radius: 4px; max-height: 300px; overflow-y: auto; }
.snn-creem-raw-data pre { margin: 0; font-size: 11px; white-space: pre-wrap; word-wrap: break-word; }
.snn-creem-pagination { padding: 15px; background: white; border: 1px solid #ccd0d4; border-radius: 4px; margin-top: 10px; }
.snn-creem-pagination .tablenav-pages { text-align: center; }
.snn-creem-pagination .page-numbers { padding: 5px 10px; margin: 0 2px; border: 1px solid #ccd0d4; background: white; text-decoration: none; display: inline-block; color: #000; }
.snn-creem-pagination .page-numbers.current { background: #000; color: white; border-color: #000; }
.snn-creem-pagination .page-numbers:hover:not(.current) { background: #f0f0f1; }
.snn-creem-settings-form { display: flex; align-items: center; gap: 15px; }
.snn-creem-no-results { background: white; padding: 20px; border: 1px solid #ccd0d4; border-radius: 4px; text-align: center; }
.snn-creem-email-status-yes { color: green; }
.snn-creem-email-status-no { color: #999; }
.snn-creem-user-email-preview { color: #666; margin-left: 10px; font-size: 13px; }
.snn-creem-user-username { font-size: 14px; }
.snn-creem-save-reminder { background: #f9f9f9; border-left: 4px solid #000; padding: 15px; margin: 15px 0; }
.snn-creem-spinner-container { text-align: center; padding: 20px; }
.snn-creem-wide-layout { width: 100%; max-width: none; }        
</style>
        <?php
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        global $wpdb;
        
        // Set default options
        $default_options = array(
            'access_token' => '',
            'test_mode' => false, // Use test API endpoint
            'default_roles' => array('subscriber'),
            'product_roles' => array(),
            'product_auto_create' => array(), // Per-product auto create users setting
            'products' => array(),
            'cron_interval' => 120,
            'sales_limit' => 50,
            'send_welcome_email' => true,
            'email_subject' => 'Welcome to {{site_name}}!',
            'email_template' => $this->get_default_email_template(),
            'log_limit' => 500,
            'user_list_per_page' => 20,
            'handle_refunds' => true,
            'refund_action' => 'remove_roles',
            'handle_subscriptions' => true,
            'subscription_cancellation_action' => 'remove_roles',
            'subscription_renewal_page' => '', // Page ID for subscription renewal redirect
            'log_rotation_days' => 30
        );

        if (!get_option($this->option_name)) {
            add_option($this->option_name, $default_options);
        } else {
            // Backward compatibility: migrate old auto_create_users to product-specific settings
            $existing_settings = get_option($this->option_name);
            if (isset($existing_settings['auto_create_users']) && $existing_settings['auto_create_users']) {
                // If global auto_create_users was enabled, enable it for all existing products
                if (!isset($existing_settings['product_auto_create'])) {
                    $existing_settings['product_auto_create'] = array();
                    if (isset($existing_settings['products']) && is_array($existing_settings['products'])) {
                        foreach ($existing_settings['products'] as $product) {
                            $existing_settings['product_auto_create'][$product['id']] = true;
                        }
                    }
                }
                // Remove old setting
                unset($existing_settings['auto_create_users']);
                update_option($this->option_name, $existing_settings);
            }
        }

        // Add database indexes for frequently queried meta keys
        $this->add_meta_key_indexes();

        // Schedule cron
        $this->schedule_cron();
    }
    
    /**
     * Add database indexes for frequently queried meta keys
     */
    private function add_meta_key_indexes() {
        global $wpdb;
        
        // List of meta keys that are frequently queried
        $meta_keys = array(
            'creem_sale_id',
            'creem_product_name',
            'creem_created_date',
            'creem_email_sent',
            'creem_subscription_status',
            'creem_sale_data'
        );
        
        // Check if indexes already exist and create them if needed
        foreach ($meta_keys as $meta_key) {
            $index_name = 'ls_' . md5($meta_key);
            
            // Check if index exists
            $index_exists = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(1) 
                 FROM INFORMATION_SCHEMA.STATISTICS 
                 WHERE table_schema = DATABASE() 
                 AND table_name = %s 
                 AND index_name = %s",
                $wpdb->usermeta,
                $index_name
            ));
            
            // Create index if it doesn't exist
            if (!$index_exists) {
                // Direct query since index names can't be prepared
                $wpdb->query(
                    "ALTER TABLE {$wpdb->usermeta} 
                     ADD INDEX {$index_name} (meta_key(191), meta_value(100))"
                );
            }
        }
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        $timestamp = wp_next_scheduled('creem_api_check_sales');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'creem_api_check_sales');
        }
    }
    
    /**
     * Add custom cron interval
     */
    public function add_custom_cron_interval($schedules) {
        $settings = get_option($this->option_name);
        $interval = isset($settings['cron_interval']) ? intval($settings['cron_interval']) : 120;
        
        $schedules['creem_custom'] = array(
            'interval' => $interval,
            'display'  => sprintf(__('Every %d seconds', 'snn'), $interval)
        );
        
        return $schedules;
    }
    
    /**
     * Schedule cron job
     */
    private function schedule_cron() {
        $timestamp = wp_next_scheduled('creem_api_check_sales');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'creem_api_check_sales');
        }

        wp_schedule_event(time(), 'creem_custom', 'creem_api_check_sales');
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            __('Creem.io API', 'snn'),
            __('Creem.io API', 'snn'),
            'manage_options',
            'creem-api-dashboard',
            array($this, 'dashboard_page'),
            'dashicons-cart',
            120
        );

        add_submenu_page(
            'creem-api-dashboard',
            __('Dashboard', 'snn'),
            __('Dashboard', 'snn'),
            'manage_options',
            'creem-api-dashboard',
            array($this, 'dashboard_page')
        );

        add_submenu_page(
            'creem-api-dashboard',
            __('Settings', 'snn'),
            __('Settings', 'snn'),
            'manage_options',
            'creem-api-settings',
            array($this, 'settings_page')
        );

        add_submenu_page(
            'creem-api-dashboard',
            __('API Logs', 'snn'),
            __('API Logs', 'snn'),
            'manage_options',
            'creem-api-logs',
            array($this, 'logs_page')
        );

        add_submenu_page(
            'creem-api-dashboard',
            __('User List', 'snn'),
            __('User List', 'snn'),
            'manage_options',
            'creem-api-users',
            array($this, 'users_page')
        );

        add_submenu_page(
            'creem-api-dashboard',
            __('Uninstall Plugin', 'snn'),
            __('Uninstall Plugin', 'snn'),
            'manage_options',
            'creem-api-uninstall',
            array($this, 'uninstall_page')
        );
    }
    
    /**
     * Register settings
     */
    public function register_settings() {
        register_setting('creem_api_settings_group', $this->option_name);
    }

    /**
     * Create HTTP headers for Creem.io API requests
     */
    private function get_api_headers($api_key) {
        return array(
            'x-api-key' => $api_key,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json'
        );
    }

    /**
     * Parse Creem.io transactions response
     * Creem.io uses simple JSON format, not JSON:API
     */
    private function parse_creem_transactions($json_data) {
        $transactions = array();
        if (!isset($json_data['items']) || !is_array($json_data['items'])) {
            return $transactions;
        }

        foreach ($json_data['items'] as $item) {
            // Return the entire raw item
            $transactions[] = $item;
        }
        return $transactions;
    }

    /**
     * Extract subscription ID from transaction
     * In Creem.io, subscription ID is directly in the transaction object
     */
    private function extract_subscription_id($transaction_item, $api_key = '') {
        // Direct field access for Creem.io
        if (isset($transaction_item['subscription']) && !empty($transaction_item['subscription'])) {
            return $transaction_item['subscription'];
        }

        return '';
    }

    /**
     * Fetch subscription data from Creem.io API
     */
    private function fetch_subscription($api_key, $subscription_id) {
        if (empty($subscription_id) || empty($api_key)) {
            return new WP_Error('invalid_params', 'API key and subscription ID are required');
        }

        // Determine which API URL to use based on mode
        $settings = get_option($this->option_name);
        $test_mode = isset($settings['test_mode']) && $settings['test_mode'];
        $base_url = $test_mode ? 'https://test-api.creem.io' : 'https://api.creem.io';
        
        $url = "{$base_url}/v1/subscriptions?subscription_id={$subscription_id}";

        $this->log_activity('FETCHING SUBSCRIPTION', array(
            'url' => $url,
            'subscription_id' => $subscription_id
        ));

        $response = wp_remote_get($url, array(
            'headers' => $this->get_api_headers($api_key)
        ));

        if (is_wp_error($response)) {
            $this->log_activity('Subscription fetch error', array(
                'subscription_id' => $subscription_id,
                'url' => $url,
                'error' => $response->get_error_message()
            ));
            return $response;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        // Log raw subscription data for debugging
        $this->log_activity('RAW SUBSCRIPTION RESPONSE', array(
            'subscription_id' => $subscription_id,
            'url' => $url,
            'raw_response' => $data
        ));

        if (!isset($data['id'])) {
            $error_msg = isset($data['error']) ? $data['error'] : 'Unknown API error';
            $this->log_activity('Subscription API error', array(
                'subscription_id' => $subscription_id,
                'error' => $error_msg,
                'response' => $data
            ));
            return new WP_Error('api_error', $error_msg);
        }

        return $data;
    }

    /**
     * Parse Creem.io customer response
     */
    private function parse_creem_customer($json_data) {
        if (!isset($json_data['id'])) {
            return null;
        }
        return array(
            'id' => isset($json_data['id']) ? $json_data['id'] : '',
            'name' => isset($json_data['name']) ? $json_data['name'] : '',
            'email' => isset($json_data['email']) ? $json_data['email'] : ''
        );
    }

    /**
     * Parse Creem.io products response
     */
    private function parse_creem_products($json_data) {
        $products = array();
        if (!isset($json_data['items']) || !is_array($json_data['items'])) {
            return $products;
        }

        foreach ($json_data['items'] as $item) {
            // According to Creem.io API docs, status can be: "active", "archived", etc.
            $status = isset($item['status']) ? $item['status'] : 'unknown';
            $published = ($status === 'active');

            $products[] = array(
                'id' => isset($item['id']) ? strval($item['id']) : '',
                'name' => isset($item['name']) ? $item['name'] : 'Unnamed Product',
                'published' => $published,
                'status' => $status // Keep original status for debugging
            );
        }
        return $products;
    }

    /**
     * Get error message from Creem.io JSON response
     */
    private function get_api_error_message($json_data) {
        // Check if $json_data is null or not an array
        if (!is_array($json_data)) {
            return 'Invalid API response format';
        }

        // Check for 'error' field (can be string or object)
        if (isset($json_data['error'])) {
            if (is_string($json_data['error'])) {
                return $json_data['error'];
            }
            if (is_array($json_data['error'])) {
                if (isset($json_data['error']['message'])) {
                    return $json_data['error']['message'];
                }
                // Return the whole error array as JSON string
                return json_encode($json_data['error']);
            }
        }

        // Check for 'message' field
        if (isset($json_data['message'])) {
            return $json_data['message'];
        }

        // Check for 'detail' field (some APIs use this)
        if (isset($json_data['detail'])) {
            return $json_data['detail'];
        }

        // If we have the whole response, try to extract useful info
        if (isset($json_data['status']) && isset($json_data['title'])) {
            return $json_data['title'] . ' (Status: ' . $json_data['status'] . ')';
        }

        return 'Unknown API error - check logs for details';
    }

    /**
     * Check recent sales via API (cron job)
     */
    public function check_recent_sales() {
        $settings = get_option($this->option_name);
        $access_token = isset($settings['access_token']) ? $settings['access_token'] : '';

        if (empty($access_token)) {
            $this->log_activity('Cron error', array('error' => 'Access token not set'));
            return;
        }

        $sales_limit = isset($settings['sales_limit']) ? intval($settings['sales_limit']) : 50;

        // Determine which API URL to use based on mode
        $test_mode = isset($settings['test_mode']) && $settings['test_mode'];
        $base_url = $test_mode ? 'https://test-api.creem.io' : 'https://api.creem.io';

        // Fetch ALL transactions via pagination so no sales are ever missed
        $page_size = 100; // Max per page
        $page_number = 1;
        $transactions = array();

        do {
            $orders_url = "{$base_url}/v1/transactions/search?page_size={$page_size}&page_number={$page_number}";

            $this->log_activity('FETCHING ORDERS', array(
                'url' => $orders_url,
                'page' => $page_number,
                'sales_limit' => $sales_limit,
                'test_mode' => $test_mode
            ));

            $response = wp_remote_get($orders_url, array(
                'headers' => $this->get_api_headers($access_token),
                'timeout' => 30
            ));

            if (is_wp_error($response)) {
                $this->log_activity('Orders fetch error', array(
                    'url' => $orders_url,
                    'error' => $response->get_error_message()
                ));
                return;
            }

            $http_code = wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);

            $this->log_activity('RAW TRANSACTIONS RESPONSE', array(
                'url' => $orders_url,
                'http_code' => $http_code,
                'page' => $page_number,
                'items_on_page' => isset($data['items']) ? count($data['items']) : 0,
                'raw_response' => $data
            ));

            if ($http_code === 401) {
                $this->log_activity('Transactions API error', array('error' => 'Authentication failed: Missing API key', 'http_code' => $http_code));
                return;
            } elseif ($http_code === 403) {
                $this->log_activity('Transactions API error', array('error' => 'Authentication failed: Invalid API key', 'http_code' => $http_code));
                return;
            } elseif ($http_code >= 400) {
                $error_msg = $this->get_api_error_message($data);
                $this->log_activity('Transactions API error', array('url' => $orders_url, 'http_code' => $http_code, 'error' => $error_msg));
                return;
            }

            if (!isset($data['items']) || !is_array($data['items'])) {
                $this->log_activity('Transactions API error', array('error' => 'Invalid response structure', 'response' => $data));
                return;
            }

            $page_transactions = $this->parse_creem_transactions($data);
            $transactions = array_merge($transactions, $page_transactions);

            // Stop if we have enough or there are no more pages
            $has_next_page = !empty($data['pagination']['next_page']);
            $page_number++;

        } while ($has_next_page && count($transactions) < $sales_limit);

        // Log first transaction for debugging
        if (!empty($transactions)) {
            $this->log_activity('RAW TRANSACTION FROM API', array(
                'raw_transaction' => $transactions[0],
                'total_fetched' => count($transactions),
                'IMPORTANT' => 'This is the COMPLETE unmodified transaction data from Creem.io'
            ));
        }

        if (!empty($transactions)) {
            $new_sales_count = 0;
            $refunds_processed = 0;
            $subscriptions_updated = 0;

            foreach ($transactions as $sale) {
                $sale_id = isset($sale['id']) ? $sale['id'] : '';

                // Creem.io uses simple JSON structure, no attributes wrapper
                $customer_email = '';
                if (isset($sale['customer'])) {
                    // Customer might be ID string or object
                    if (is_string($sale['customer'])) {
                        // TODO: Fetch customer details if needed
                        $customer_email = '';
                    } else if (is_array($sale['customer']) && isset($sale['customer']['email'])) {
                        $customer_email = sanitize_email($sale['customer']['email']);
                    }
                }
                $email = $customer_email;

                // Handle refunds - check for refunded_amount in Creem.io transaction
                if (isset($settings['handle_refunds']) && $settings['handle_refunds']) {
                    // In Creem.io, check if refunded_amount > 0
                    $refunded_amount = isset($sale['refunded_amount']) ? floatval($sale['refunded_amount']) : 0;
                    if ($refunded_amount > 0) {
                        $refund_result = $this->handle_refund($sale);
                        if (!is_wp_error($refund_result)) {
                            $refunds_processed++;
                        }
                        continue;
                    }
                }

                // Handle subscription status changes - fetch actual subscription data
                if (isset($settings['handle_subscriptions']) && $settings['handle_subscriptions']) {
                    // Extract subscription ID (pass api_key)
                    $subscription_id = $this->extract_subscription_id($sale, $access_token);

                    if (!empty($subscription_id)) {
                        // Fetch the actual subscription to check its status
                        $subscription = $this->fetch_subscription($access_token, $subscription_id);

                        if ($subscription && !is_wp_error($subscription)) {
                            // Creem.io subscription status
                            $sub_status = isset($subscription['status']) ? $subscription['status'] : '';

                            // Check for subscription states that require action
                            // canceled = subscription ended
                            // unpaid = payment failed
                            if (in_array($sub_status, array('canceled', 'unpaid'))) {
                                $sub_result = $this->handle_subscription_change($sale, $subscription);
                                if (!is_wp_error($sub_result)) {
                                    $subscriptions_updated++;
                                }
                                continue;
                            } else if ($sub_status === 'scheduled_cancel') {
                                // scheduled_cancel in Creem means it will cancel at period end
                                $current_period_end = isset($subscription['current_period_end_date']) ? $subscription['current_period_end_date'] : '';
                                if (!empty($current_period_end) && strtotime($current_period_end) <= time()) {
                                    // Period ended, treat as cancelled
                                    $sub_result = $this->handle_subscription_change($sale, $subscription);
                                    if (!is_wp_error($sub_result)) {
                                        $subscriptions_updated++;
                                    }
                                    continue;
                                }
                            }
                        }
                    }
                }

                // If transaction customer is a string ID, inject full customer from subscription
                if (isset($sale['customer']) && is_string($sale['customer'])
                    && isset($subscription) && is_array($subscription) && !is_wp_error($subscription)
                    && isset($subscription['customer']) && is_array($subscription['customer'])) {
                    $sale['customer'] = $subscription['customer'];
                    $email = isset($subscription['customer']['email']) ? sanitize_email($subscription['customer']['email']) : $email;
                }

                // If transaction doesn't have product but subscription does, inject product data from subscription
                if (!isset($sale['product']) 
                    && isset($subscription) && is_array($subscription) && !is_wp_error($subscription)
                    && isset($subscription['product']) && is_array($subscription['product'])) {
                    $sale['product'] = $subscription['product'];
                }

                // Reliable check: does a WP user with this email already have this exact sale_id recorded?
                $should_process = true;
                if (!empty($email) && !empty($sale_id)) {
                    $existing_user = get_user_by('email', $email);
                    if ($existing_user) {
                        $stored_sale_id = get_user_meta($existing_user->ID, 'creem_sale_id', true);
                        if ($stored_sale_id === $sale_id) {
                            $should_process = false; // Already processed, user exists
                        }
                    }
                }

                if ($should_process) {
                    $result = $this->process_sale($sale);
                    if (!is_wp_error($result)) {
                        $new_sales_count++;
                    }
                }
            }

            $this->log_activity('Cron completed', array(
                'total_sales_checked' => count($transactions),
                'new_sales_processed' => $new_sales_count,
                'refunds_processed' => $refunds_processed,
                'subscriptions_updated' => $subscriptions_updated
            ));
        }

        // Also check existing users' subscriptions for status changes
        if (isset($settings['handle_subscriptions']) && $settings['handle_subscriptions']) {
            $subscriptions_checked = $this->check_existing_subscriptions($access_token);
            if ($subscriptions_checked > 0) {
                $this->log_activity('Existing subscriptions checked', array(
                    'total_checked' => $subscriptions_checked
                ));
            }
        }
    }

    /**
     * Check existing users' subscriptions for status changes
     * This catches subscription changes that may not appear in recent orders
     */
    private function check_existing_subscriptions($access_token) {
        if (empty($access_token)) {
            return 0;
        }

        $settings = get_option($this->option_name);

        // Get users with creem subscriptions who are still active (not marked as expired/unpaid)
        $args = array(
            'meta_query' => array(
                'relation' => 'AND',
                array(
                    'key' => 'creem_sale_data',
                    'compare' => 'EXISTS'
                ),
                array(
                    'relation' => 'OR',
                    array(
                        'key' => 'creem_subscription_status',
                        'compare' => 'NOT EXISTS'
                    ),
                    array(
                        'key' => 'creem_subscription_status',
                        'value' => array('expired', 'unpaid'),
                        'compare' => 'NOT IN'
                    )
                )
            ),
            'number' => 50, // Check 50 users per cron run to avoid timeouts
            'fields' => 'ids' // Only fetch user IDs for better performance
        );

        $user_query = new WP_User_Query($args);
        $user_ids = $user_query->get_results();
        $checked_count = 0;

        foreach ($user_ids as $user_id) {
            // Get stored sale data
            $sale_data_json = get_user_meta($user_id, 'creem_sale_data', true);
            if (empty($sale_data_json)) {
                continue;
            }

            $sale_data = json_decode($sale_data_json, true);
            if (!$sale_data) {
                continue;
            }

            // Extract subscription ID (pass access_token to fetch from links if needed)
            $subscription_id = $this->extract_subscription_id($sale_data, $access_token);
            if (empty($subscription_id)) {
                continue; // Not a subscription purchase
            }

            // Fetch current subscription status
            $subscription = $this->fetch_subscription($access_token, $subscription_id);
            if (!$subscription || is_wp_error($subscription)) {
                continue;
            }

            $sub_attrs = isset($subscription['attributes']) ? $subscription['attributes'] : array();
            $sub_status = isset($sub_attrs['status']) ? $sub_attrs['status'] : '';
            $ends_at = isset($sub_attrs['ends_at']) ? $sub_attrs['ends_at'] : '';

            $checked_count++;

            // Check if subscription needs action
            if (in_array($sub_status, array('expired', 'unpaid'))) {
                // Subscription has ended, remove roles
                $result = $this->handle_subscription_change($sale_data, $subscription);
                if (!is_wp_error($result)) {
                    $user = get_userdata($user_id);
                    $this->log_activity('Subscription auto-detected as ended', array(
                        'user_id' => $user_id,
                        'email' => $user ? $user->user_email : 'unknown',
                        'subscription_id' => $subscription_id,
                        'status' => $sub_status
                    ));
                }
            } else if ($sub_status === 'cancelled' && !empty($ends_at) && strtotime($ends_at) <= time()) {
                // Cancelled subscription, grace period ended
                $result = $this->handle_subscription_change($sale_data, $subscription);
                if (!is_wp_error($result)) {
                    $this->log_activity('Cancelled subscription grace period ended', array(
                        'user_id' => $user->ID,
                        'email' => $user->user_email,
                        'subscription_id' => $subscription_id,
                        'ends_at' => $ends_at
                    ));
                }
            }
        }

        return $checked_count;
    }
    
    /**
     * Process a sale and create/update user
     */
    private function process_sale($sale_data) {
        // Creem.io uses simple JSON structure, no attributes wrapper
        $email = '';
        $product_name = '';
        $product_id = '';
        
        // Get customer email
        if (isset($sale_data['customer'])) {
            if (is_array($sale_data['customer']) && isset($sale_data['customer']['email'])) {
                $email = sanitize_email($sale_data['customer']['email']);
            }
        }
        
        // Get product information from the transaction
        if (isset($sale_data['product'])) {
            if (is_array($sale_data['product'])) {
                $product_name = isset($sale_data['product']['name']) ? sanitize_text_field($sale_data['product']['name']) : '';
                $product_id = isset($sale_data['product']['id']) ? strval($sale_data['product']['id']) : '';
            } else if (is_string($sale_data['product'])) {
                // Product is just an ID reference
                $product_id = sanitize_text_field($sale_data['product']);
            }
        }
        
        $sale_id = isset($sale_data['id']) ? $sale_data['id'] : '';

        if (empty($email)) {
            return new WP_Error('invalid_email', 'Email address is required');
        }

        if (empty($product_id)) {
            $this->log_activity('MISSING PRODUCT ID', array(
                'email' => $email,
                'raw_sale' => $sale_data
            ));
            return new WP_Error('missing_product_id', 'Product ID is missing');
        }

        $settings = get_option($this->option_name);
        $product_auto_create = isset($settings['product_auto_create']) ? $settings['product_auto_create'] : array();
        
        // Check if auto user creation is enabled for THIS product
        if (!isset($product_auto_create[$product_id]) || !$product_auto_create[$product_id]) {
            $this->log_activity('User creation skipped', array(
                'reason' => 'Auto create users is not enabled for this product',
                'email' => $email,
                'product_name' => $product_name,
                'product_id' => $product_id,
                'sale_id' => $sale_id,
                'enabled_products' => array_keys(array_filter($product_auto_create)),
                'note' => 'Go to Settings and enable Auto Create Users checkbox for product ID: ' . $product_id
            ));
            return new WP_Error('auto_create_disabled', 'Auto create users not enabled for this product');
        }
        
        // Check if auto user creation is enabled for THIS specific product
        // Try both original and normalized product IDs to handle type mismatches
        $is_auto_create_enabled = false;
        if (isset($product_auto_create[$product_id]) && $product_auto_create[$product_id]) {
            $is_auto_create_enabled = true;
        } elseif (isset($product_auto_create[$product_id_normalized]) && $product_auto_create[$product_id_normalized]) {
            $is_auto_create_enabled = true;
            $product_id = $product_id_normalized; // Use normalized version
        }
        
        if (!$is_auto_create_enabled) {
            // Get list of enabled products with their details for debugging
            $enabled_products = array();
            foreach ($product_auto_create as $pid => $enabled) {
                if ($enabled) {
                    $enabled_products[$pid] = array(
                        'id' => $pid,
                        'type' => gettype($pid)
                    );
                }
            }
            
            $this->log_activity('User creation skipped', array(
                'reason' => 'Auto create users is not enabled for this product',
                'email' => $email,
                'product_name' => $log_product_name,
                'product_id' => $log_product_id,
                'product_id_type' => gettype($product_id),
                'enabled_products' => $enabled_products,
                'total_enabled_products' => count(array_filter($product_auto_create)),
                'all_configured_products' => array_keys($product_auto_create),
                'note' => 'Enable auto-create for this product in Settings > User Management > Product-Specific Configuration'
            ));
            return new WP_Error('auto_create_disabled', 'Automatic user creation is not enabled for this product');
        }

        // Check if user exists
        $user = get_user_by('email', $email);

        $default_roles = isset($settings['default_roles']) ? $settings['default_roles'] : array('subscriber');
        $product_roles = isset($settings['product_roles']) ? $settings['product_roles'] : array();

        // Determine roles for this product
        $roles = array();
        if (!empty($product_id) && isset($product_roles[$product_id]) && !empty($product_roles[$product_id])) {
            $roles = $product_roles[$product_id];
        } else {
            $roles = $default_roles;
        }

        // If no roles configured, skip user creation
        if (empty($roles)) {
            $this->log_activity('User creation skipped', array(
                'reason' => 'No roles configured for this product',
                'email' => $email,
                'product_name' => $product_name,
                'product_id' => $product_id
            ));
            return new WP_Error('no_roles_configured', 'No roles configured for this product');
        }
        
        if (!$user) {
            // Create new user
            $username = $this->generate_username($email);
            $password = wp_generate_password(12, true, true);

            $user_id = wp_create_user($username, $password, $email);

            if (is_wp_error($user_id)) {
                return $user_id;
            }

            $user = get_user_by('id', $user_id);

            // Set first name from email
            $first_name = $this->get_first_name_from_email($email);
            wp_update_user(array(
                'ID' => $user_id,
                'first_name' => $first_name
            ));

            // Assign roles
            $user->set_role($roles[0]); // Set primary role
            for ($i = 1; $i < count($roles); $i++) {
                $user->add_role($roles[$i]); // Add additional roles
            }

            // Store creem metadata
            update_user_meta($user_id, 'creem_sale_id', $sale_id);
            update_user_meta($user_id, 'creem_product_name', $product_name);
            update_user_meta($user_id, 'creem_product_id', $product_id);
            update_user_meta($user_id, 'creem_created_date', current_time('mysql'));
            update_user_meta($user_id, 'creem_sale_data', json_encode($sale_data));
            update_user_meta($user_id, 'creem_assigned_roles', json_encode($roles));

            // Store customer ID for billing portal access
            $creem_customer_id = '';
            if (isset($sale_data['customer'])) {
                if (is_string($sale_data['customer']) && !empty($sale_data['customer'])) {
                    $creem_customer_id = $sale_data['customer'];
                } elseif (is_array($sale_data['customer']) && !empty($sale_data['customer']['id'])) {
                    $creem_customer_id = $sale_data['customer']['id'];
                }
            }
            if (!empty($creem_customer_id)) {
                update_user_meta($user_id, 'creem_customer_id', $creem_customer_id);
            }

            // Send welcome email
            $email_sent = false;
            if (isset($settings['send_welcome_email']) && $settings['send_welcome_email']) {
                $email_sent = $this->send_welcome_email($user, $password, $product_name);
            }
            
            update_user_meta($user_id, 'creem_email_sent', $email_sent ? 'yes' : 'no');
            update_user_meta($user_id, 'creem_email_sent_date', $email_sent ? current_time('mysql') : '');
            
            $this->log_activity('User created', array(
                'user_id' => $user_id,
                'email' => $email,
                'username' => $username,
                'product_name' => $product_name,
                'product_id' => $product_id,
                'sale_id' => $sale_id,
                'roles' => $roles,
                'email_sent' => $email_sent,
                'first_name' => $first_name
            ));
            
            return $user_id;
        } else {
            // Update existing user roles if needed
            $roles_added = array();
            foreach ($roles as $role) {
                if (!in_array($role, (array) $user->roles)) {
                    $user->add_role($role);
                    $roles_added[] = $role;
                }
            }
            
            if (!empty($roles_added)) {
                // Update creem metadata for existing user
                $sale_id = isset($sale_data['id']) ? $sale_data['id'] : '';
                update_user_meta($user->ID, 'creem_last_purchase_date', current_time('mysql'));
                update_user_meta($user->ID, 'creem_last_product_name', $product_name);
                update_user_meta($user->ID, 'creem_last_product_id', $product_id);
                update_user_meta($user->ID, 'creem_last_sale_id', $sale_id);
                
                // Append to purchase history
                $purchase_history = get_user_meta($user->ID, 'creem_purchase_history', true);
                if (!$purchase_history) {
                    $purchase_history = array();
                } else {
                    $purchase_history = json_decode($purchase_history, true);
                }
                $purchase_history[] = array(
                    'date' => current_time('mysql'),
                    'product_name' => $product_name,
                    'product_id' => $product_id,
                    'sale_id' => $sale_id,
                    'roles_added' => $roles_added
                );
                update_user_meta($user->ID, 'creem_purchase_history', json_encode($purchase_history));
                
                $this->log_activity('User roles updated', array(
                    'user_id' => $user->ID,
                    'email' => $email,
                    'username' => $user->user_login,
                    'product_name' => $product_name,
                    'product_id' => $product_id,
                    'sale_id' => $sale_id,
                    'roles_added' => $roles_added,
                    'all_roles' => $user->roles
                ));
            }
            
            return $user->ID;
        }
    }
    
    /**
     * Generate unique username from email
     * Uses the full email as username for better uniqueness
     */
    private function generate_username($email) {
        // Use the full email as username (WordPress allows emails as usernames)
        $username = sanitize_user($email, true);

        // This should rarely happen since emails are unique, but just in case
        if (username_exists($username)) {
            $i = 1;
            while (username_exists($username . $i)) {
                $i++;
            }
            $username = $username . $i;
        }

        return $username;
    }

    /**
     * Extract first name from email
     * Takes the part before @ and capitalizes it
     */
    private function get_first_name_from_email($email) {
        $local_part = substr($email, 0, strpos($email, '@'));

        // Remove dots, underscores, and numbers to clean it up
        $clean_name = str_replace(array('.', '_', '-', '0', '1', '2', '3', '4', '5', '6', '7', '8', '9'), ' ', $local_part);

        // Get only the first part/word
        $first_name = trim($clean_name);
        $words = explode(' ', $first_name);
        $first_name = !empty($words[0]) ? ucfirst(strtolower($words[0])) : '';

        // If empty after cleaning, use the original local part
        if (empty($first_name)) {
            $first_name = ucfirst($local_part);
        }

        return $first_name;
    }
    
    /**
     * Send welcome email
     */
    private function send_welcome_email($user, $password, $product_name) {
        $settings = get_option($this->option_name);
        $email_template = isset($settings['email_template']) ? $settings['email_template'] : $this->get_default_email_template();
        $email_subject = isset($settings['email_subject']) ? $settings['email_subject'] : 'Welcome to {{site_name}}!';
        
        $reset_key = get_password_reset_key($user);
        $password_reset_url = network_site_url("wp-login.php?action=rp&key=$reset_key&login=" . rawurlencode($user->user_login), 'login');
        
        // Dynamic tags
        $tags = array(
            '{{site_name}}' => get_bloginfo('name'),
            '{{site_url}}' => get_site_url(),
            '{{product_name}}' => $product_name,
            '{{username}}' => $user->user_login,
            '{{password}}' => $password,
            '{{email}}' => $user->user_email,
            '{{login_url}}' => wp_login_url(),
            '{{password_reset_url}}' => $password_reset_url
        );
        
        $subject = str_replace(array_keys($tags), array_values($tags), $email_subject);
        $message = str_replace(array_keys($tags), array_values($tags), $email_template);
        
        $headers = array('Content-Type: text/html; charset=UTF-8');
        
        return wp_mail($user->user_email, $subject, $message, $headers);
    }
    
    /**
     * Get default email template
     */
    private function get_default_email_template() {
        return '<h2>Welcome to {{site_name}}!</h2>

<p>Hi there!</p>

<p>Thank you for purchasing <strong>{{product_name}}</strong>! Your account has been created automatically.</p>

<h3>Your Login Credentials:</h3>

<p><strong>Username:</strong> {{username}}<br>
<strong>Password:</strong> {{password}}<br>
<strong>Email:</strong> {{email}}</p>

<p><a href="{{login_url}}">Login to Your Account</a></p>

<p>If you prefer to reset your password, use this link:<br>
<a href="{{password_reset_url}}">Reset Password</a></p>

<p><strong>Important:</strong> Please keep this email safe as it contains your login credentials.</p>

<br>
<p>{{site_name}} - <a href="{{site_url}}">{{site_url}}</a></p>';
    }
    
    /**
     * Log activity
     */
    private function log_activity($type, $data) {
        $logs = get_option($this->log_option_name, array());
        $settings = get_option($this->option_name);
        $log_limit = isset($settings['log_limit']) ? intval($settings['log_limit']) : 500;
        $log_rotation_days = isset($settings['log_rotation_days']) ? intval($settings['log_rotation_days']) : 30;
        
        $log_entry = array(
            'timestamp' => current_time('mysql'),
            'type' => $type,
            'data' => $data
        );
        
        array_unshift($logs, $log_entry);
        
        // Log rotation by date - remove logs older than specified days
        $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$log_rotation_days} days"));
        $logs = array_filter($logs, function($log) use ($cutoff_date) {
            return isset($log['timestamp']) && $log['timestamp'] >= $cutoff_date;
        });
        
        // Also limit log size as backup
        if (count($logs) > $log_limit) {
            $logs = array_slice($logs, 0, $log_limit);
        }
        
        update_option($this->log_option_name, array_values($logs));
    }
    
    /**
     * Handle refund
     */
    private function handle_refund($sale_data) {
        $settings = get_option($this->option_name);

        // Creem.io simple JSON structure
        $sale_id = isset($sale_data['id']) ? $sale_data['id'] : '';
        
        $email = '';
        if (isset($sale_data['customer'])) {
            if (is_array($sale_data['customer']) && isset($sale_data['customer']['email'])) {
                $email = sanitize_email($sale_data['customer']['email']);
            }
        }
        
        $product_name = '';
        if (isset($sale_data['product']) && is_array($sale_data['product'])) {
            $product_name = isset($sale_data['product']['name']) ? sanitize_text_field($sale_data['product']['name']) : '';
        }
        
        $refunded_amount = isset($sale_data['refunded_amount']) ? $sale_data['refunded_amount'] : 0;

        if (empty($email)) {
            return new WP_Error('invalid_email', 'Email address is required');
        }

        $user = get_user_by('email', $email);
        if (!$user) {
            $this->log_activity('Refund processing skipped', array(
                'reason' => 'User not found',
                'email' => $email,
                'sale_id' => $sale_id
            ));
            return new WP_Error('user_not_found', 'User not found');
        }

        $refund_action = isset($settings['refund_action']) ? $settings['refund_action'] : 'remove_roles';

        if ($refund_action === 'delete_account') {
            require_once(ABSPATH.'wp-admin/includes/user.php');
            wp_delete_user($user->ID);

            $this->log_activity('User deleted due to refund', array(
                'user_id' => $user->ID,
                'email' => $email,
                'sale_id' => $sale_id,
                'product' => $product_name,
                'refunded_at' => $refunded_at
            ));
        } else {
            // Remove roles assigned by creem
            $assigned_roles = get_user_meta($user->ID, 'creem_assigned_roles', true);
            $roles_removed = array();
            if ($assigned_roles) {
                $roles = json_decode($assigned_roles, true);
                if (is_array($roles)) {
                    foreach ($roles as $role) {
                        $user->remove_role($role);
                        $roles_removed[] = $role;
                    }
                }
            }

            update_user_meta($user->ID, 'creem_refunded', 'yes');
            update_user_meta($user->ID, 'creem_refunded_date', current_time('mysql'));

            $this->log_activity('User roles removed due to refund', array(
                'user_id' => $user->ID,
                'email' => $email,
                'sale_id' => $sale_id,
                'product' => $product_name,
                'refunded_at' => $refunded_at,
                'roles_removed' => $roles_removed
            ));
        }

        return $user->ID;
    }
    
    /**
     * Handle subscription change (cancellation/end/expiration)
     *
     * @param array $transaction_data The transaction data from Creem.io API
     * @param array $subscription_data The subscription data from Creem.io API
     */
    private function handle_subscription_change($transaction_data, $subscription_data) {
        $settings = get_option($this->option_name);

        // Extract from transaction (Creem.io simple JSON)
        $email = '';
        $product_name = '';
        
        if (isset($transaction_data['customer']) && is_array($transaction_data['customer'])) {
            $email = isset($transaction_data['customer']['email']) ? sanitize_email($transaction_data['customer']['email']) : '';
        }
        
        if (isset($transaction_data['product']) && is_array($transaction_data['product'])) {
            $product_name = isset($transaction_data['product']['name']) ? sanitize_text_field($transaction_data['product']['name']) : '';
        }

        // Extract from subscription (Creem.io simple JSON)
        $subscription_id = isset($subscription_data['id']) ? $subscription_data['id'] : '';
        $sub_status = isset($subscription_data['status']) ? $subscription_data['status'] : '';
        $current_period_end = isset($subscription_data['current_period_end_date']) ? $subscription_data['current_period_end_date'] : '';
        $canceled_at = isset($subscription_data['canceled_at']) ? $subscription_data['canceled_at'] : '';

        if (empty($email)) {
            return new WP_Error('invalid_email', 'Email address is required');
        }

        $user = get_user_by('email', $email);
        if (!$user) {
            $this->log_activity('Subscription change processing skipped', array(
                'reason' => 'User not found',
                'email' => $email,
                'subscription_id' => $subscription_id,
                'subscription_status' => $sub_status
            ));
            return new WP_Error('user_not_found', 'User not found');
        }

        $action = isset($settings['subscription_cancellation_action']) ? $settings['subscription_cancellation_action'] : 'remove_roles';

        if ($action === 'delete_account') {
            require_once(ABSPATH.'wp-admin/includes/user.php');
            wp_delete_user($user->ID);

            $this->log_activity('User deleted due to subscription end', array(
                'user_id' => $user->ID,
                'email' => $email,
                'subscription_id' => $subscription_id,
                'subscription_status' => $sub_status,
                'product' => $product_name,
                'ends_at' => $ends_at
            ));
        } else {
            // Remove roles assigned by creem
            $assigned_roles = get_user_meta($user->ID, 'creem_assigned_roles', true);
            $roles_removed = array();
            if ($assigned_roles) {
                $roles = json_decode($assigned_roles, true);
                if (is_array($roles)) {
                    foreach ($roles as $role) {
                        $user->remove_role($role);
                        $roles_removed[] = $role;
                    }
                }
            }

            // Update subscription status in user meta
            update_user_meta($user->ID, 'creem_subscription_status', $sub_status);
            update_user_meta($user->ID, 'creem_subscription_ended_date', current_time('mysql'));
            if (!empty($ends_at)) {
                update_user_meta($user->ID, 'creem_subscription_ends_at', $ends_at);
            }

            $this->log_activity('Subscription ended - roles removed', array(
                'user_id' => $user->ID,
                'email' => $email,
                'subscription_id' => $subscription_id,
                'subscription_status' => $sub_status,
                'product' => $product_name,
                'action' => $action,
                'roles_removed' => $roles_removed,
                'ends_at' => $ends_at,
                'cancelled' => $cancelled
            ));
        }

        return $user->ID;
    }
    
    /**
     * Dashboard page
     */
    public function dashboard_page() {
        $stats = $this->get_dashboard_statistics();
        
        ?>
        <div class="wrap">
            <h1><?php _e('Creem.io API Dashboard', 'snn'); ?></h1>
            
            <!-- Statistics Grid -->
            <div class="snn-creem-stats-grid">
                
                <!-- Total Sales Processed -->
                <div class="snn-creem-stat-card">
                    <div class="snn-creem-stat-card-header"><?php _e('Total Sales Processed', 'snn'); ?></div>
                    <div class="snn-creem-stat-card-value"><?php echo number_format($stats['total_sales']); ?></div>
                    <div class="snn-creem-stat-card-footer"><?php _e('All time', 'snn'); ?></div>
                </div>
                
                <!-- Users Created This Month -->
                <div class="snn-creem-stat-card">
                    <div class="snn-creem-stat-card-header"><?php _e('Users Created This Month', 'snn'); ?></div>
                    <div class="snn-creem-stat-card-value"><?php echo number_format($stats['users_this_month']); ?></div>
                    <div class="snn-creem-stat-card-footer"><?php echo date('F Y'); ?></div>
                </div>
                
                <!-- Total Users -->
                <div class="snn-creem-stat-card">
                    <div class="snn-creem-stat-card-header"><?php _e('Total Creem.io Users', 'snn'); ?></div>
                    <div class="snn-creem-stat-card-value"><?php echo number_format($stats['total_users']); ?></div>
                    <div class="snn-creem-stat-card-footer"><?php _e('Active accounts', 'snn'); ?></div>
                </div>
                
                <!-- Email Success Rate -->
                <div class="snn-creem-stat-card">
                    <div class="snn-creem-stat-card-header"><?php _e('Email Success Rate', 'snn'); ?></div>
                    <div class="snn-creem-stat-card-value"><?php echo $stats['email_success_rate']; ?>%</div>
                    <div class="snn-creem-stat-card-footer"><?php echo $stats['emails_sent']; ?> <?php _e('sent', 'snn'); ?></div>
                </div>
                
                <!-- Most Popular Product -->
                <div class="snn-creem-stat-card">
                    <div class="snn-creem-stat-card-header"><?php _e('Most Popular Product', 'snn'); ?></div>
                    <div class="snn-creem-stat-card-value"><?php echo esc_html($stats['top_product_name']); ?></div>
                    <div class="snn-creem-stat-card-footer"><?php echo number_format($stats['top_product_count']); ?> <?php _e('purchases', 'snn'); ?></div>
                </div>
                
                <!-- Active Subscriptions -->
                <div class="snn-creem-stat-card">
                    <div class="snn-creem-stat-card-header"><?php _e('Active Subscriptions', 'snn'); ?></div>
                    <div class="snn-creem-stat-card-value"><?php echo number_format($stats['active_subscriptions']); ?></div>
                    <div class="snn-creem-stat-card-footer"><?php _e('Currently active', 'snn'); ?></div>
                </div>
                
                <!-- Refunds Processed -->
                <div class="snn-creem-stat-card">
                    <div class="snn-creem-stat-card-header"><?php _e('Refunds Processed', 'snn'); ?></div>
                    <div class="snn-creem-stat-card-value"><?php echo number_format($stats['total_refunds']); ?></div>
                    <div class="snn-creem-stat-card-footer"><?php _e('All time', 'snn'); ?></div>
                </div>
                
                <!-- Recent Activity -->
                <div class="snn-creem-stat-card">
                    <div class="snn-creem-stat-card-header"><?php _e('Recent Activity', 'snn'); ?></div>
                    <div class="snn-creem-stat-card-value"><?php echo number_format($stats['activity_last_24h']); ?></div>
                    <div class="snn-creem-stat-card-footer"><?php _e('events in last 24 hours', 'snn'); ?></div>
                </div>
                
            </div>
            
            <!-- Recent Logs Preview -->
            <div class="snn-creem-section">
                <div class="snn-creem-recent-logs-header">
                    <h2><?php _e('Recent Activity Logs', 'snn'); ?></h2>
                    <a href="<?php echo admin_url('admin.php?page=creem-api-logs'); ?>" class="button"><?php _e('View All Logs', 'snn'); ?></a>
                </div>
                
                <?php
                $recent_logs = array_slice(get_option($this->log_option_name, array()), 0, 5);
                if (empty($recent_logs)) {
                    echo '<p>' . __('No recent activity.', 'snn') . '</p>';
                } else {
                    echo '<table class="wp-list-table widefat fixed striped">';
                    echo '<thead><tr><th>' . __('Time', 'snn') . '</th><th>' . __('Type', 'snn') . '</th><th>' . __('Details', 'snn') . '</th></tr></thead><tbody>';
                    foreach ($recent_logs as $log) {
                        $data_preview = '';
                        if (isset($log['data']['email'])) $data_preview .= esc_html($log['data']['email']);
                        if (isset($log['data']['product'])) $data_preview .= ' - ' . esc_html($log['data']['product']);
                        echo '<tr>';
                        echo '<td class="snn-creem-log-timestamp">' . esc_html($log['timestamp']) . '</td>';
                        echo '<td><strong>' . esc_html($log['type']) . '</strong></td>';
                        echo '<td>' . $data_preview . '</td>';
                        echo '</tr>';
                    }
                    echo '</tbody></table>';
                }
                ?>
            </div>
            
            <!-- Product Statistics -->
            <?php if (!empty($stats['product_breakdown'])): ?>
            <div class="snn-creem-section">
                <h2><?php _e('Product Breakdown', 'snn'); ?></h2>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php _e('Product Name', 'snn'); ?></th>
                            <th><?php _e('Users Created', 'snn'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($stats['product_breakdown'] as $product => $count): ?>
                        <tr>
                            <td><?php echo esc_html($product); ?></td>
                            <td><?php echo number_format($count); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
            
        </div>
        <?php
    }
    
    /**
     * Get dashboard statistics
     */
    private function get_dashboard_statistics() {
        global $wpdb;
        
        $stats = array(
            'total_sales' => 0,
            'users_this_month' => 0,
            'total_users' => 0,
            'email_success_rate' => 0,
            'emails_sent' => 0,
            'top_product_name' => 'N/A',
            'top_product_count' => 0,
            'active_subscriptions' => 0,
            'total_refunds' => 0,
            'activity_last_24h' => 0,
            'product_breakdown' => array()
        );
        
        // Total users with creem metadata - using SQL count
        $total_users_query = $wpdb->prepare(
            "SELECT COUNT(DISTINCT um.user_id) 
             FROM {$wpdb->usermeta} um 
             WHERE um.meta_key = %s",
            'creem_sale_id'
        );
        $stats['total_users'] = (int) $wpdb->get_var($total_users_query);
        
        // Users created this month - using SQL count
        $month_start = date('Y-m-01 00:00:00');
        $users_this_month_query = $wpdb->prepare(
            "SELECT COUNT(DISTINCT um.user_id) 
             FROM {$wpdb->usermeta} um 
             WHERE um.meta_key = %s 
             AND um.meta_value >= %s",
            'creem_created_date',
            $month_start
        );
        $stats['users_this_month'] = (int) $wpdb->get_var($users_this_month_query);
        
        // Emails sent - using SQL count
        $emails_sent_query = $wpdb->prepare(
            "SELECT COUNT(DISTINCT um.user_id) 
             FROM {$wpdb->usermeta} um 
             WHERE um.meta_key = %s 
             AND um.meta_value = %s",
            'creem_email_sent',
            'yes'
        );
        $stats['emails_sent'] = (int) $wpdb->get_var($emails_sent_query);
        $stats['email_success_rate'] = $stats['total_users'] > 0 ? round(($stats['emails_sent'] / $stats['total_users']) * 100) : 0;
        
        // Product breakdown - using SQL group count
        $product_breakdown_query = $wpdb->prepare(
            "SELECT um.meta_value as product_name, COUNT(*) as count 
             FROM {$wpdb->usermeta} um 
             WHERE um.meta_key = %s 
             AND um.meta_value != '' 
             GROUP BY um.meta_value 
             ORDER BY count DESC 
             LIMIT 10",
            'creem_product_name'
        );
        $product_results = $wpdb->get_results($product_breakdown_query);
        $product_counts = array();
        
        if (!empty($product_results)) {
            foreach ($product_results as $row) {
                $product_counts[$row->product_name] = (int) $row->count;
            }
            $stats['product_breakdown'] = $product_counts;
            $stats['top_product_name'] = $product_results[0]->product_name;
            $stats['top_product_count'] = (int) $product_results[0]->count;
        }
        
        // Active subscriptions - using SQL query
        // Count users with subscription_id in sale_data and status not 'cancelled'
        $active_subs_query = $wpdb->prepare(
            "SELECT COUNT(DISTINCT um1.user_id) 
             FROM {$wpdb->usermeta} um1 
             WHERE um1.meta_key = %s 
             AND um1.meta_value LIKE %s
             AND um1.user_id NOT IN (
                 SELECT user_id FROM {$wpdb->usermeta} 
                 WHERE meta_key = %s AND meta_value = %s
             )",
            'creem_sale_data',
            '%subscription_id%',
            'creem_subscription_status',
            'cancelled'
        );
        $stats['active_subscriptions'] = (int) $wpdb->get_var($active_subs_query);
        
        // Count from logs
        $logs = get_option($this->log_option_name, array());
        $refund_count = 0;
        $activity_24h = 0;
        $cutoff_24h = date('Y-m-d H:i:s', strtotime('-24 hours'));
        
        foreach ($logs as $log) {
            if (isset($log['type']) && strpos(strtolower($log['type']), 'refund') !== false) {
                $refund_count++;
            }
            if (isset($log['timestamp']) && $log['timestamp'] >= $cutoff_24h) {
                $activity_24h++;
            }
            if (isset($log['type']) && $log['type'] === 'User created') {
                $stats['total_sales']++;
            }
        }
        
        $stats['total_refunds'] = $refund_count;
        $stats['activity_last_24h'] = $activity_24h;
        
        // If no users, use processed sales count
        if ($stats['total_sales'] === 0) {
            $processed_sales = get_option('creem_processed_sales', array());
            $stats['total_sales'] = count($processed_sales);
        }
        
        return $stats;
    }
    
    /**
     * Settings page
     */
    public function settings_page() {
        if (isset($_POST['creem_settings_nonce']) && wp_verify_nonce($_POST['creem_settings_nonce'], 'creem_save_settings')) {
            $this->save_settings($_POST);
            echo '<div class="notice notice-success"><p>' . __('Settings saved successfully!', 'snn') . '</p></div>';
        }
        
        $settings = get_option($this->option_name);
        $default_roles = isset($settings['default_roles']) ? $settings['default_roles'] : array('subscriber');
        $product_roles = isset($settings['product_roles']) ? $settings['product_roles'] : array();
        
        ?>
        <div class="wrap">
            <h1><?php _e('Creem.io API Settings', 'snn'); ?></h1>
            
            <form method="post" action="">
                <?php wp_nonce_field('creem_save_settings', 'creem_settings_nonce'); ?>
                
                <!-- API Connection Section -->
                <div class="snn-creem-section">
                    <h2><?php _e('API Connection', 'snn'); ?></h2>
                    <div class="snn-creem-api-info">
                        <strong>ℹ️ <?php _e('API-Based Sales Monitoring', 'snn'); ?></strong><br>
                        <?php _e('This plugin uses Creem.io API to automatically check for new sales. Configure the check interval in the "Cron Settings" tab.', 'snn'); ?>
                    </div>
                    <table class="form-table">
                        <tr>
                            <th scope="row"><label for="access_token"><?php _e('Creem.io API Key', 'snn'); ?></label></th>
                            <td>
                                <input type="password" name="access_token" id="access_token" value="<?php echo esc_attr($settings['access_token']); ?>" class="regular-text" />
                                <button type="button" class="button" onclick="togglePassword('access_token')"><?php _e('Show/Hide', 'snn'); ?></button>
                                <button type="button" class="button button-primary" onclick="testApiConnection()"><?php _e('Test & Fetch Products', 'snn'); ?></button>
                                <p class="description">
                                    <?php _e('1. Go to your Creem.io dashboard Settings → API Keys<br>2. Click "Create API key" or use an existing one<br>3. Copy the API key<br>4. Paste the key here', 'snn'); ?>
                                </p>
                                <div id="api-test-result"></div>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('Test Mode', 'snn'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="test_mode" value="1" <?php checked(isset($settings['test_mode']) && $settings['test_mode'], 1); ?> />
                                    <?php _e('Use Test API (test-api.creem.io)', 'snn'); ?>
                                </label>
                                <p class="description"><?php _e('Enable this to use the test API endpoint for testing. Make sure to use a test API key when this is enabled.', 'snn'); ?></p>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <!-- User Management Section -->
                <div class="snn-creem-section">
                    <h2><?php _e('User Management', 'snn'); ?></h2>
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php _e('Default User Roles', 'snn'); ?></th>
                            <td>
                                <p class="description"><?php _e('Select default role(s) to assign to newly created users. These roles will be used when no product-specific roles are configured.', 'snn'); ?></p>
                                <?php
                                global $wp_roles;
                                $all_roles = $wp_roles->roles;
                                foreach ($all_roles as $role_key => $role_info) {
                                    $checked = in_array($role_key, $default_roles) ? 'checked' : '';
                                    echo '<label><input type="checkbox" name="default_roles[]" value="' . esc_attr($role_key) . '" ' . $checked . ' /> ';
                                    echo esc_html($role_info['name']);
                                    echo '</label><br>';
                                }
                                ?>
                                <p class="description"><?php _e('Use role management plugins to create custom roles if needed.', 'snn'); ?></p>
                            </td>
                        </tr>
                    </table>

                    <hr>

                    <h3><?php _e('Product-Specific Configuration', 'snn'); ?></h3>
                    <p><?php _e('For each product below, you can enable automatic user creation and configure specific roles. This gives you complete control over which products trigger user account creation.', 'snn'); ?></p>
                    
                    <div id="products-notice" class="snn-creem-products-notice">
                        <p><strong><?php _e('⚠️ Please test your API connection first to load your products.', 'snn'); ?></strong></p>
                        <p><?php _e('Go to the "Connection" tab and click "Test & Fetch Products" button.', 'snn'); ?></p>
                    </div>
                    
                    <div id="products-loading" class="snn-creem-products-loading">
                        <span class="spinner is-active"></span>
                        <p><?php _e('Loading products...', 'snn'); ?></p>
                    </div>
                    
                    <div id="products-list" class="snn-creem-products-list">
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th><?php _e('Product Name', 'snn'); ?></th>
                                    <th><?php _e('Product ID', 'snn'); ?></th>
                                    <th><?php _e('Status', 'snn'); ?></th>
                                    <th style="width: 150px;"><?php _e('Auto Create Users', 'snn'); ?></th>
                                    <th><?php _e('Select roles to assign for this product', 'snn'); ?></th>
                                </tr>
                            </thead>
                            <tbody id="products-tbody">
                                <!-- Products will be loaded here via AJAX -->
                            </tbody>
                        </table>
                        <p class="description">
                            <strong><?php _e('💡 How it works:', 'snn'); ?></strong><br>
                            • <?php _e('Enable "Auto Create Users" for a product to automatically create WordPress accounts when that product is purchased', 'snn'); ?><br>
                            • <?php _e('Select specific roles for each product, or leave unchecked to use default roles', 'snn'); ?><br>
                            • <?php _e('If "Auto Create Users" is disabled for a product, no accounts will be created regardless of roles configured', 'snn'); ?><br>
                            • <?php _e('This gives you complete control - no automatic user creation happens unless explicitly enabled per product', 'snn'); ?>
                        </p>
                    </div>
                </div>
                
                <!-- Welcome Email Section -->
                <div class="snn-creem-section">
                    <h2><?php _e('Welcome Email Settings', 'snn'); ?></h2>
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php _e('Send Welcome Email', 'snn'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="send_welcome_email" value="1" <?php checked($settings['send_welcome_email'], 1); ?> />
                                    <?php _e('Send welcome email to new users', 'snn'); ?>
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="email_subject"><?php _e('Email Subject', 'snn'); ?></label></th>
                            <td>
                                <input type="text" name="email_subject" id="email_subject" value="<?php echo esc_attr($settings['email_subject']); ?>" class="large-text" />
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="email_template"><?php _e('Email Template (HTML)', 'snn'); ?></label></th>
                            <td>
                                <textarea name="email_template" id="email_template" rows="20" class="large-text code"><?php echo esc_textarea($settings['email_template']); ?></textarea>
                                <div class="snn-creem-email-tags">
                                    <h4>📧 <?php _e('Available Dynamic Tags:', 'snn'); ?></h4>
                                    <ul>
                                        <li><code>{{site_name}}</code> - <?php _e('Your site name', 'snn'); ?></li>
                                        <li><code>{{site_url}}</code> - <?php _e('Your site URL', 'snn'); ?></li>
                                        <li><code>{{product_name}}</code> - <?php _e('Purchased product', 'snn'); ?></li>
                                        <li><code>{{username}}</code> - <?php _e("User's username", 'snn'); ?></li>
                                        <li><code>{{password}}</code> - <?php _e('Generated password', 'snn'); ?></li>
                                        <li><code>{{email}}</code> - <?php _e("User's email", 'snn'); ?></li>
                                        <li><code>{{login_url}}</code> - <?php _e('WordPress login URL', 'snn'); ?></li>
                                        <li><code>{{password_reset_url}}</code> - <?php _e('Password reset link', 'snn'); ?></li>
                                    </ul>
                                    <h4>💡 <?php _e('Tips:', 'snn'); ?></h4>
                                    <ul>
                                        <li><?php _e('Full HTML support - style your email as you wish!', 'snn'); ?></li>
                                        <li><?php _e('Use dynamic tags by wrapping them in double curly braces: {{tag_name}}', 'snn'); ?></li>
                                        <li><?php _e('The template above shows the default email structure', 'snn'); ?></li>
                                        <li><?php _e('Emails are sent as HTML, so you can use any HTML tags and inline CSS', 'snn'); ?></li>
                                    </ul>
                                </div>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <!-- Cron Job Settings Section -->
                <div class="snn-creem-section">
                    <h2><?php _e('Cron Job Settings', 'snn'); ?></h2>
                    <table class="form-table">
                        <tr>
                            <th scope="row"><label for="cron_interval"><?php _e('Check Interval (seconds)', 'snn'); ?></label></th>
                            <td>
                                <input type="number" name="cron_interval" id="cron_interval" value="<?php echo esc_attr($settings['cron_interval']); ?>" class="small-text" min="30" />
                                <p class="description"><?php _e('How often to check for new sales (default: 120 seconds)', 'snn'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="sales_limit"><?php _e('Sales to Check', 'snn'); ?></label></th>
                            <td>
                                <input type="number" name="sales_limit" id="sales_limit" value="<?php echo esc_attr($settings['sales_limit']); ?>" class="small-text" min="1" max="200" />
                                <p class="description"><?php _e('Number of recent sales to check each time (default: 50)', 'snn'); ?></p>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <!-- Refund Handling Section -->
                <div class="snn-creem-section">
                    <h2><?php _e('Refund Handling', 'snn'); ?></h2>
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php _e('Handle Refunds', 'snn'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="handle_refunds" value="1" <?php checked(isset($settings['handle_refunds']) ? $settings['handle_refunds'] : true, 1); ?> />
                                    <strong><?php _e('Automatically process refunded sales', 'snn'); ?></strong>
                                </label>
                                <p class="description"><?php _e('When enabled, the plugin will detect refunded sales and take action.', 'snn'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('Refund Action', 'snn'); ?></th>
                            <td>
                                <label>
                                    <input type="radio" name="refund_action" value="remove_roles" <?php checked(isset($settings['refund_action']) ? $settings['refund_action'] : 'remove_roles', 'remove_roles'); ?> />
                                    <?php _e('Remove user roles (recommended)', 'snn'); ?>
                                </label><br>
                                <label>
                                    <input type="radio" name="refund_action" value="delete_account" <?php checked(isset($settings['refund_action']) ? $settings['refund_action'] : 'remove_roles', 'delete_account'); ?> />
                                    <?php _e('Delete user account', 'snn'); ?>
                                </label>
                                <p class="description"><?php _e('Choose what happens when a refund is detected.', 'snn'); ?></p>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <!-- Subscription Management Section -->
                <div class="snn-creem-section">
                    <h2><?php _e('Subscription Management', 'snn'); ?></h2>
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php _e('Handle Subscriptions', 'snn'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="handle_subscriptions" value="1" <?php checked(isset($settings['handle_subscriptions']) ? $settings['handle_subscriptions'] : true, 1); ?> />
                                    <strong><?php _e('Automatically track subscription status changes', 'snn'); ?></strong>
                                </label>
                                <p class="description"><?php _e('When enabled, the plugin will monitor subscription cancellations and expirations.', 'snn'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('Subscription End Action', 'snn'); ?></th>
                            <td>
                                <label>
                                    <input type="radio" name="subscription_cancellation_action" value="remove_roles" <?php checked(isset($settings['subscription_cancellation_action']) ? $settings['subscription_cancellation_action'] : 'remove_roles', 'remove_roles'); ?> />
                                    <?php _e('Remove user roles (recommended)', 'snn'); ?>
                                </label><br>
                                <label>
                                    <input type="radio" name="subscription_cancellation_action" value="delete_account" <?php checked(isset($settings['subscription_cancellation_action']) ? $settings['subscription_cancellation_action'] : 'remove_roles', 'delete_account'); ?> />
                                    <?php _e('Delete user account', 'snn'); ?>
                                </label>
                                <p class="description"><?php _e('Choose what happens when a subscription is cancelled or expires.', 'snn'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="subscription_renewal_page"><?php _e('Subscription Renewal Page', 'snn'); ?></label></th>
                            <td>
                                <?php
                                $selected_page_id = isset($settings['subscription_renewal_page']) ? $settings['subscription_renewal_page'] : '';
                                $selected_page_title = '';
                                if (!empty($selected_page_id)) {
                                    $page = get_post($selected_page_id);
                                    if ($page) {
                                        $selected_page_title = $page->post_title;
                                    }
                                }
                                ?>
                                <input type="text"
                                       id="subscription_renewal_page_search"
                                       class="regular-text"
                                       placeholder="<?php _e('Search for a page...', 'snn'); ?>"
                                       value="<?php echo esc_attr($selected_page_title); ?>"
                                       autocomplete="off"
                                       list="pages-datalist" />
                                <input type="hidden"
                                       name="subscription_renewal_page"
                                       id="subscription_renewal_page"
                                       value="<?php echo esc_attr($selected_page_id); ?>" />
                                <datalist id="pages-datalist">
                                    <?php
                                    $pages = get_pages(array(
                                        'post_status' => 'publish',
                                        'sort_column' => 'post_title',
                                        'sort_order' => 'ASC'
                                    ));
                                    foreach ($pages as $page) {
                                        echo '<option value="' . esc_attr($page->post_title) . '" data-id="' . esc_attr($page->ID) . '">' . esc_html($page->post_title) . ' (ID: ' . esc_html($page->ID) . ')</option>';
                                    }
                                    ?>
                                </datalist>
                                <button type="button" class="button" onclick="clearRenewalPage()"><?php _e('Clear', 'snn'); ?></button>
                                <p class="description">
                                    <?php _e('Select a page where users will be redirected when their subscription expires. Users will be forced to visit only this page until they renew their subscription.', 'snn'); ?>
                                    <?php if (!empty($selected_page_id) && !empty($selected_page_title)): ?>
                                        <br><strong><?php _e('Currently selected:', 'snn'); ?></strong> <?php echo esc_html($selected_page_title); ?> (ID: <?php echo esc_html($selected_page_id); ?>)
                                    <?php endif; ?>
                                </p>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <!-- Log Settings Section -->
                <div class="snn-creem-section">
                    <h2><?php _e('Log Settings', 'snn'); ?></h2>
                    <table class="form-table">
                        <tr>
                            <th scope="row"><label for="log_rotation_days"><?php _e('Log Rotation (days)', 'snn'); ?></label></th>
                            <td>
                                <input type="number" name="log_rotation_days" id="log_rotation_days" value="<?php echo esc_attr(isset($settings['log_rotation_days']) ? $settings['log_rotation_days'] : 30); ?>" class="small-text" min="1" />
                                <p class="description"><?php _e('Automatically delete logs older than this many days (default: 30)', 'snn'); ?></p>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <?php submit_button(); ?>
            </form>
        </div>
        
        <script>
        var creemProducts = [];
        
        function togglePassword(id) {
            var input = document.getElementById(id);
            input.type = input.type === "password" ? "text" : "password";
        }
        
        function testApiConnection() {
            var token = document.getElementById('access_token').value;
            var testMode = document.querySelector('input[name="test_mode"]').checked;
            var resultDiv = document.getElementById('api-test-result');

            if (!token) {
                resultDiv.innerHTML = '<p style="color: red;">⚠️ Please enter an access token first.</p>';
                return;
            }

            var modeText = testMode ? ' (Test Mode)' : ' (Production Mode)';
            resultDiv.innerHTML = '<p><span class="spinner is-active"></span> Testing connection and fetching products' + modeText + '...</p>';

            // First test the API
            jQuery.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'creem_test_api',
                    token: token,
                    test_mode: testMode,
                    nonce: '<?php echo wp_create_nonce('creem_test_api'); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        resultDiv.innerHTML = '<p style="color: green;">✓ ' + response.data.message + '</p>';
                        // Now fetch products
                        fetchProducts(token, testMode);
                    } else {
                        resultDiv.innerHTML = '<p style="color: red;">✗ ' + response.data.message + '</p>';
                    }
                },
                error: function(xhr, status, error) {
                    resultDiv.innerHTML = '<p style="color: red;">✗ Connection test failed: ' + error + '</p>';
                }
            });
        }
        
        function fetchProducts(token, testMode) {
            jQuery('#products-loading').show();
            jQuery('#products-notice').hide();

            jQuery.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'creem_fetch_products',
                    token: token,
                    test_mode: testMode,
                    nonce: '<?php echo wp_create_nonce('creem_fetch_products'); ?>'
                },
                success: function(response) {
                    jQuery('#products-loading').hide();
                    if (response.success) {
                        creemProducts = response.data.products;
                        displayProducts(response.data.products);
                        jQuery('#products-list').show();
                    } else {
                        alert('Failed to fetch products: ' + response.data.message);
                    }
                },
                error: function(xhr, status, error) {
                    jQuery('#products-loading').hide();
                    alert('Failed to fetch products: ' + error + '. Please check the browser console for more details.');
                    console.error('Products fetch error:', xhr.responseText);
                }
            });
        }
        
        function displayProducts(products) {
            var tbody = jQuery('#products-tbody');
            tbody.empty();
            
            if (products.length === 0) {
                tbody.append('<tr><td colspan="4" style="text-align: center;">No products found. Create products in your creem dashboard first.</td></tr>');
                return;
            }
            
            // Add hidden input with products data for persistence
            jQuery('#products-data-input').remove();
            jQuery('<input type="hidden" id="products-data-input" name="products_data" value="' + escapeHtml(JSON.stringify(products)) + '" />').insertAfter('#products-tbody');
            
            // Show save reminder
            jQuery('#save-products-reminder').remove();
            jQuery('<div id="save-products-reminder" class="snn-creem-save-reminder"><p><strong>⚠️ Products loaded successfully!</strong> Please scroll down and click <strong>"Save Changes"</strong> to persist these products.</p></div>').insertBefore('#products-list');

            var savedProductRoles = <?php echo json_encode($product_roles); ?>;
            var savedProductAutoCreate = <?php echo json_encode(isset($settings['product_auto_create']) ? $settings['product_auto_create'] : array()); ?>;

            products.forEach(function(product) {
                var statusBadge = product.published
                    ? '<span style="color: green;">● Published</span>'
                    : '<span style="color: gray;">● Unpublished</span>';

                var savedRoles = savedProductRoles[product.id] || [];
                var autoCreateEnabled = savedProductAutoCreate[product.id] || false;

                // Auto create users checkbox
                var autoCreateHtml = '<label style="display: flex; align-items: center; justify-content: center;">' +
                    '<input type="checkbox" name="product_auto_create[' + product.id + ']" value="1" ' +
                    (autoCreateEnabled ? 'checked' : '') + ' style="margin: 0;" />' +
                    '</label>';

                var rolesHtml = '<div class="snn-creem-product-roles-checkboxes">';
                <?php
                global $wp_roles;
                foreach ($wp_roles->roles as $role_key => $role_info) {
                    echo "rolesHtml += '<label><input type=\"checkbox\" name=\"product_roles[' + product.id + '][]\" value=\"" . esc_js($role_key) . "\" ' + (savedRoles.indexOf('" . esc_js($role_key) . "') !== -1 ? 'checked' : '') + ' /> " . esc_js($role_info['name']) . "</label>';";
                }
                ?>
                rolesHtml += '</div>';

                var row = '<tr>' +
                    '<td><strong>' + escapeHtml(product.name) + '</strong></td>' +
                    '<td><code>' + escapeHtml(product.id) + '</code></td>' +
                    '<td>' + statusBadge + '</td>' +
                    '<td style="text-align: center;">' + autoCreateHtml + '</td>' +
                    '<td>' + rolesHtml + '<input type="hidden" name="product_ids[]" value="' + escapeHtml(product.id) + '" /></td>' +
                    '</tr>';

                tbody.append(row);
            });
        }
        
        function escapeHtml(text) {
            var map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return text.replace(/[&<>"']/g, function(m) { return map[m]; });
        }
        
        // Clear renewal page selection
        function clearRenewalPage() {
            document.getElementById('subscription_renewal_page_search').value = '';
            document.getElementById('subscription_renewal_page').value = '';
        }

        // Load products on page load
        jQuery(document).ready(function($) {
            // Load products from saved settings on page load
            var savedProducts = <?php echo json_encode(isset($settings['products']) ? $settings['products'] : array()); ?>;

            if (savedProducts && savedProducts.length > 0) {
                // Products exist in database, display them immediately
                creemProducts = savedProducts;
                displayProducts(savedProducts);
                $('#products-notice').hide();
                $('#products-list').show();
            } else {
                // No products saved, check if token exists to show helpful message
                var token = $('#access_token').val();
                if (token && token.length > 0) {
                    $('#products-notice').html('<p><strong>👉 Products not loaded yet.</strong></p><p>Scroll up to the "API Connection" section and click <strong>"Test & Fetch Products"</strong> to load your creem products.</p>');
                }
            }

            // Handle page selection from datalist
            $('#subscription_renewal_page_search').on('change', function() {
                var selectedTitle = $(this).val();
                var selectedOption = $('#pages-datalist option[value="' + selectedTitle + '"]');

                if (selectedOption.length > 0) {
                    var pageId = selectedOption.attr('data-id');
                    $('#subscription_renewal_page').val(pageId);
                }
            });
        });
        </script>
        <?php
    }
    
    /**
     * Render product role row (deprecated, kept for compatibility)
     */
    private function render_product_role_row($product_id, $role) {
        // This method is no longer used but kept for backward compatibility
        return;
    }
    
    /**
     * Save settings
     */
    private function save_settings($post_data) {
        // Get existing settings to preserve products if not updated
        $existing_settings = get_option($this->option_name, array());

        $settings = array(
            'access_token' => isset($post_data['access_token']) ? sanitize_text_field($post_data['access_token']) : '',
            'test_mode' => isset($post_data['test_mode']) ? true : false,
            'default_roles' => isset($post_data['default_roles']) ? array_map('sanitize_text_field', $post_data['default_roles']) : array(),
            'cron_interval' => isset($post_data['cron_interval']) ? intval($post_data['cron_interval']) : 120,
            'sales_limit' => isset($post_data['sales_limit']) ? intval($post_data['sales_limit']) : 50,
            'send_welcome_email' => isset($post_data['send_welcome_email']) ? true : false,
            'email_subject' => isset($post_data['email_subject']) ? sanitize_text_field($post_data['email_subject']) : '',
            'email_template' => isset($post_data['email_template']) ? wp_kses_post($post_data['email_template']) : '',
            'log_limit' => isset($post_data['log_limit']) ? intval($post_data['log_limit']) : 500,
            'user_list_per_page' => isset($post_data['user_list_per_page']) ? intval($post_data['user_list_per_page']) : 20,
            'product_roles' => array(),
            'product_auto_create' => array(),
            'products' => isset($existing_settings['products']) ? $existing_settings['products'] : array(),
            'handle_refunds' => isset($post_data['handle_refunds']) ? true : false,
            'refund_action' => isset($post_data['refund_action']) ? sanitize_text_field($post_data['refund_action']) : 'remove_roles',
            'handle_subscriptions' => isset($post_data['handle_subscriptions']) ? true : false,
            'subscription_cancellation_action' => isset($post_data['subscription_cancellation_action']) ? sanitize_text_field($post_data['subscription_cancellation_action']) : 'remove_roles',
            'subscription_renewal_page' => isset($post_data['subscription_renewal_page']) ? intval($post_data['subscription_renewal_page']) : '',
            'log_rotation_days' => isset($post_data['log_rotation_days']) ? intval($post_data['log_rotation_days']) : 30
        );

        // Process product roles
        if (isset($post_data['product_roles']) && is_array($post_data['product_roles'])) {
            foreach ($post_data['product_roles'] as $product_id => $roles) {
                if (is_array($roles) && !empty($roles)) {
                    $settings['product_roles'][sanitize_text_field($product_id)] = array_map('sanitize_text_field', $roles);
                }
            }
        }

        // Process product auto create settings
        if (isset($post_data['product_auto_create']) && is_array($post_data['product_auto_create'])) {
            foreach ($post_data['product_auto_create'] as $product_id => $enabled) {
                $settings['product_auto_create'][sanitize_text_field($product_id)] = true;
            }
        }
        
        // Log settings save for debugging
        $this->log_activity('Settings saved', array(
            'auto_create_enabled_for_products' => array_keys($settings['product_auto_create']),
            'total_products_with_auto_create' => count($settings['product_auto_create']),
            'products_with_custom_roles' => array_keys($settings['product_roles']),
            'default_roles' => $settings['default_roles']
        ));

        // Process products data
        if (isset($post_data['products_data'])) {
            $products_json = stripslashes($post_data['products_data']);
            $products = json_decode($products_json, true);
            if (is_array($products)) {
                $settings['products'] = $products;
            }
        }

        update_option($this->option_name, $settings);

        // Reschedule cron with new interval
        $this->schedule_cron();
    }
    
    /**
     * Test API connection
     */
    public function test_api_connection() {
        check_ajax_referer('creem_test_api', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
        }

        $token = isset($_POST['token']) ? sanitize_text_field($_POST['token']) : '';

        if (empty($token)) {
            wp_send_json_error(array('message' => 'API key is required'));
        }

        // Determine which API URL to use based on test mode checkbox
        $test_mode = isset($_POST['test_mode']) && $_POST['test_mode'] === 'true';
        $base_url = $test_mode ? 'https://test-api.creem.io' : 'https://api.creem.io';

        // Test connection by fetching products (simpler than customers which need params)
        $response = wp_remote_get($base_url . '/v1/products/search?page_size=1', array(
            'headers' => $this->get_api_headers($token),
            'timeout' => 15
        ));

        if (is_wp_error($response)) {
            wp_send_json_error(array('message' => 'Connection failed: ' . $response->get_error_message()));
        }

        $http_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        // Log the test for debugging
        $this->log_activity('API Connection Test', array(
            'http_code' => $http_code,
            'test_mode' => $test_mode,
            'base_url' => $base_url,
            'response' => $data
        ));

        // Check for authentication errors
        if ($http_code === 401) {
            wp_send_json_error(array('message' => 'Authentication failed: Missing API key'));
        } elseif ($http_code === 403) {
            wp_send_json_error(array('message' => 'Authentication failed: Invalid API key'));
        } elseif ($http_code >= 400) {
            $error_message = $this->get_api_error_message($data);
            wp_send_json_error(array('message' => 'API Error (' . $http_code . '): ' . $error_message));
        }

        // Success - API connection works
        if ($http_code === 200 && isset($data['items'])) {
            $mode = $test_mode ? 'Test Mode' : 'Production Mode';
            wp_send_json_success(array('message' => 'Connected successfully! (' . $mode . ')'));
        } else {
            wp_send_json_error(array('message' => 'Unexpected response format from API'));
        }
    }
    
    /**
     * Fetch products from creem
     */
    public function fetch_products() {
        check_ajax_referer('creem_fetch_products', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
        }

        $token = isset($_POST['token']) ? sanitize_text_field($_POST['token']) : '';

        if (empty($token)) {
            wp_send_json_error(array('message' => 'API key is required'));
        }

        // Determine which API URL to use based on test mode checkbox
        $test_mode = isset($_POST['test_mode']) && $_POST['test_mode'] === 'true';
        $base_url = $test_mode ? 'https://test-api.creem.io' : 'https://api.creem.io';

        // Fetch all products with pagination
        $all_products = array();
        $page_number = 1;
        $page_size = 50; // Fetch 50 products per page

        do {
            $url = $base_url . '/v1/products/search?page_number=' . $page_number . '&page_size=' . $page_size;

            $response = wp_remote_get($url, array(
                'headers' => $this->get_api_headers($token),
                'timeout' => 15
            ));

            if (is_wp_error($response)) {
                wp_send_json_error(array('message' => 'Failed to fetch products: ' . $response->get_error_message()));
            }

            $http_code = wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);

            // Check for errors
            if ($http_code === 401) {
                wp_send_json_error(array('message' => 'Authentication failed: Missing API key'));
            } elseif ($http_code === 403) {
                wp_send_json_error(array('message' => 'Authentication failed: Invalid API key'));
            } elseif ($http_code >= 400) {
                $error_message = $this->get_api_error_message($data);
                wp_send_json_error(array('message' => 'API Error (' . $http_code . '): ' . $error_message));
            }

            // Parse products response
            $products = $this->parse_creem_products($data);
            if (!empty($products)) {
                $all_products = array_merge($all_products, $products);
            }

            // Check if there are more pages
            $has_more_pages = false;
            if (isset($data['pagination']) && isset($data['pagination']['next_page_number'])) {
                $has_more_pages = !is_null($data['pagination']['next_page_number']);
                $page_number = $data['pagination']['next_page_number'];
            }

        } while ($has_more_pages);

        // Log the fetch for debugging
        $this->log_activity('Products Fetched', array(
            'total_products' => count($all_products),
            'test_mode' => $test_mode
        ));

        if (!empty($all_products)) {
            wp_send_json_success(array('products' => $all_products));
        } else {
            wp_send_json_error(array('message' => 'No products found. Please create products in your Creem.io dashboard first.'));
        }
    }

    /**
     * Logs page
     */
    public function logs_page() {
        // Handle form submission for log limit
        if (isset($_POST['creem_log_settings_nonce']) && wp_verify_nonce($_POST['creem_log_settings_nonce'], 'creem_save_log_settings')) {
            $settings = get_option($this->option_name);
            $settings['log_limit'] = isset($_POST['log_limit']) ? intval($_POST['log_limit']) : 500;
            update_option($this->option_name, $settings);
            echo '<div class="notice notice-success"><p>' . __('Log settings saved successfully!', 'snn') . '</p></div>';
        }
        
        $settings = get_option($this->option_name);
        $logs = get_option($this->log_option_name, array());
        $per_page = 20;
        $page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $total_logs = count($logs);
        $total_pages = ceil($total_logs / $per_page);
        $offset = ($page - 1) * $per_page;
        $current_logs = array_slice($logs, $offset, $per_page);
        
        ?>
        <div class="wrap">
            <h1><?php _e('API Logs', 'snn'); ?></h1>
            
            <!-- Log Settings Section -->
            <div class="snn-creem-section">
                <h2><?php _e('Log Settings', 'snn'); ?></h2>
                <form method="post" action="">
                    <?php wp_nonce_field('creem_save_log_settings', 'creem_log_settings_nonce'); ?>
                    <table class="form-table">
                        <tr>
                            <th scope="row"><label for="log_limit"><?php _e('Log Limit', 'snn'); ?></label></th>
                            <td>
                                <input type="number" name="log_limit" id="log_limit" value="<?php echo esc_attr($settings['log_limit']); ?>" class="small-text" min="50" />
                                <p class="description"><?php _e('Maximum number of logs to keep (default: 500)', 'snn'); ?></p>
                            </td>
                        </tr>
                    </table>
                    <?php submit_button(__('Save Settings', 'snn'), 'primary', 'submit', false); ?>
                </form>
            </div>
            
            <div>
                <button type="button" class="button" onclick="if(confirm('Are you sure you want to clear all logs?')) clearLogs();"><?php _e('Clear All Logs', 'snn'); ?></button>
                <span><?php printf(__('Total: %d logs', 'snn'), $total_logs); ?></span>
            </div>
            
            <?php if (empty($current_logs)): ?>
                <p><?php _e('No logs found.', 'snn'); ?></p>
            <?php else: ?>
                <div id="logs-container">
                    <?php foreach ($current_logs as $index => $log): ?>
                        <div class="snn-creem-log-entry">
                            <div class="snn-creem-log-header" onclick="toggleLog(<?php echo $index; ?>)">
                                <div>
                                    <strong><?php echo esc_html($log['type']); ?></strong>
                                    <span class="snn-creem-log-timestamp"><?php echo esc_html($log['timestamp']); ?></span>
                                </div>
                                <span class="dashicons dashicons-arrow-down-alt2" id="icon-<?php echo $index; ?>"></span>
                            </div>
                            <div class="snn-creem-log-details" id="log-<?php echo $index; ?>">
                                <pre><?php echo esc_html(print_r($log['data'], true)); ?></pre>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <?php if ($total_pages > 1): ?>
                    <div class="snn-creem-pagination">
                        <div class="tablenav-pages">
                            <?php
                            echo paginate_links(array(
                                'base' => add_query_arg('paged', '%#%'),
                                'format' => '',
                                'prev_text' => __('&laquo;'),
                                'next_text' => __('&raquo;'),
                                'total' => $total_pages,
                                'current' => $page
                            ));
                            ?>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        
        <script>
        function toggleLog(index) {
            var details = document.getElementById('log-' + index);
            var icon = document.getElementById('icon-' + index);
            if (details.style.display === 'none') {
                details.style.display = 'block';
                icon.classList.remove('dashicons-arrow-down-alt2');
                icon.classList.add('dashicons-arrow-up-alt2');
            } else {
                details.style.display = 'none';
                icon.classList.remove('dashicons-arrow-up-alt2');
                icon.classList.add('dashicons-arrow-down-alt2');
            }
        }
        
        function clearLogs() {
            jQuery.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'creem_clear_logs',
                    nonce: '<?php echo wp_create_nonce('creem_clear_logs'); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    }
                }
            });
        }
        </script>
        <?php
    }
    
    /**
     * Clear logs
     */
    public function clear_logs() {
        check_ajax_referer('creem_clear_logs', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error();
        }
        
        update_option($this->log_option_name, array());
        wp_send_json_success();
    }
    
    /**
     * Add plugin row meta links
     */
    public function add_plugin_row_meta($links, $file) {
        if (plugin_basename(__FILE__) === $file) {
            $uninstall_url = admin_url('admin.php?page=creem-api-uninstall');
            $links[] = '<a href="' . esc_url($uninstall_url) . '" style="color: #d63638; font-weight: bold;">' . __('Uninstall', 'snn') . '</a>';
        }
        return $links;
    }
    
    /**
     * Users page - Display all users created by creem API
     */
    public function users_page() {
        // Handle form submission for per page setting
        if (isset($_POST['creem_user_list_settings_nonce']) && wp_verify_nonce($_POST['creem_user_list_settings_nonce'], 'creem_save_user_list_settings')) {
            $settings = get_option($this->option_name);
            $settings['user_list_per_page'] = isset($_POST['user_list_per_page']) ? max(1, intval($_POST['user_list_per_page'])) : 20;
            update_option($this->option_name, $settings);
            echo '<div class="notice notice-success"><p>' . __('Settings saved successfully!', 'snn') . '</p></div>';
        }
        
        $settings = get_option($this->option_name);
        $per_page = isset($settings['user_list_per_page']) ? intval($settings['user_list_per_page']) : 20;
        $page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        
        // Get search/filter parameters
        $search_email = isset($_GET['search_email']) ? sanitize_text_field($_GET['search_email']) : '';
        $search_product = isset($_GET['search_product']) ? sanitize_text_field($_GET['search_product']) : '';
        $search_sale_id = isset($_GET['search_sale_id']) ? sanitize_text_field($_GET['search_sale_id']) : '';
        $filter_date_from = isset($_GET['filter_date_from']) ? sanitize_text_field($_GET['filter_date_from']) : '';
        $filter_date_to = isset($_GET['filter_date_to']) ? sanitize_text_field($_GET['filter_date_to']) : '';
        $filter_role = isset($_GET['filter_role']) ? sanitize_text_field($_GET['filter_role']) : '';
        
        // Build query args
        $args = array(
            'meta_key' => 'creem_sale_id',
            'meta_compare' => 'EXISTS',
            'number' => $per_page,
            'paged' => $page,
            'orderby' => 'registered',
            'order' => 'DESC',
            'fields' => 'all' // Load full user objects only for display page
        );
        
        // Apply filters
        $meta_query = array('relation' => 'AND');
        
        if (!empty($search_sale_id)) {
            $meta_query[] = array(
                'key' => 'creem_sale_id',
                'value' => $search_sale_id,
                'compare' => 'LIKE'
            );
        }
        
        if (!empty($search_product)) {
            $meta_query[] = array(
                'key' => 'creem_product_name',
                'value' => $search_product,
                'compare' => 'LIKE'
            );
        }
        
        if (!empty($filter_date_from)) {
            $meta_query[] = array(
                'key' => 'creem_created_date',
                'value' => $filter_date_from . ' 00:00:00',
                'compare' => '>=',
                'type' => 'DATETIME'
            );
        }
        
        if (!empty($filter_date_to)) {
            $meta_query[] = array(
                'key' => 'creem_created_date',
                'value' => $filter_date_to . ' 23:59:59',
                'compare' => '<=',
                'type' => 'DATETIME'
            );
        }
        
        if (count($meta_query) > 1) {
            $args['meta_query'] = $meta_query;
        }
        
        if (!empty($search_email)) {
            $args['search'] = '*' . $search_email . '*';
            $args['search_columns'] = array('user_email');
        }
        
        if (!empty($filter_role)) {
            $args['role__in'] = array($filter_role);
        }
        
        $user_query = new WP_User_Query($args);
        $users = $user_query->get_results();
        $total_users = $user_query->get_total();
        $total_pages = ceil($total_users / $per_page);
        
        ?>
        <div class="wrap">
            <h1><?php _e('Creem.io Users', 'snn'); ?></h1>
            
            <!-- Search & Filter Section -->
            <div class="snn-creem-section">
                <h2><?php _e('Search & Filter', 'snn'); ?></h2>
                <form method="get" action="">
                    <input type="hidden" name="page" value="creem-api-users" />
                    
                    <div class="snn-creem-search-filters">
                        <div>
                            <label for="search_email"><strong><?php _e('Email', 'snn'); ?></strong></label>
                            <input type="text" name="search_email" id="search_email" value="<?php echo esc_attr($search_email); ?>" class="regular-text" placeholder="<?php _e('Search by email...', 'snn'); ?>" />
                        </div>
                        
                        <div>
                            <label for="search_product"><strong><?php _e('Product', 'snn'); ?></strong></label>
                            <input type="text" name="search_product" id="search_product" value="<?php echo esc_attr($search_product); ?>" class="regular-text" placeholder="<?php _e('Search by product...', 'snn'); ?>" />
                        </div>
                        
                        <div>
                            <label for="search_sale_id"><strong><?php _e('Sale ID', 'snn'); ?></strong></label>
                            <input type="text" name="search_sale_id" id="search_sale_id" value="<?php echo esc_attr($search_sale_id); ?>" class="regular-text" placeholder="<?php _e('Search by sale ID...', 'snn'); ?>" />
                        </div>
                        
                        <div>
                            <label for="filter_role"><strong><?php _e('Role', 'snn'); ?></strong></label>
                            <select name="filter_role" id="filter_role" class="regular-text">
                                <option value=""><?php _e('All Roles', 'snn'); ?></option>
                                <?php
                                global $wp_roles;
                                foreach ($wp_roles->roles as $role_key => $role_info) {
                                    $selected = ($filter_role === $role_key) ? 'selected' : '';
                                    echo '<option value="' . esc_attr($role_key) . '" ' . $selected . '>' . esc_html($role_info['name']) . '</option>';
                                }
                                ?>
                            </select>
                        </div>
                        
                        <div>
                            <label for="filter_date_from"><strong><?php _e('Date From', 'snn'); ?></strong></label>
                            <input type="date" name="filter_date_from" id="filter_date_from" value="<?php echo esc_attr($filter_date_from); ?>" class="regular-text" />
                        </div>
                        
                        <div>
                            <label for="filter_date_to"><strong><?php _e('Date To', 'snn'); ?></strong></label>
                            <input type="date" name="filter_date_to" id="filter_date_to" value="<?php echo esc_attr($filter_date_to); ?>" class="regular-text" />
                        </div>
                    </div>
                    
                    <div class="snn-creem-search-actions">
                        <button type="submit" class="button button-primary"><?php _e('Apply Filters', 'snn'); ?></button>
                        <a href="<?php echo admin_url('admin.php?page=creem-api-users'); ?>" class="button"><?php _e('Clear Filters', 'snn'); ?></a>
                        <span class="snn-creem-search-total"><?php printf(__('Total: %d users', 'snn'), $total_users); ?></span>
                    </div>
                </form>
            </div>
            
            <?php if (empty($users)): ?>
                <div class="snn-creem-no-results">
                    <p><?php _e('No users found. Users created through creem purchases will appear here.', 'snn'); ?></p>
                </div>
            <?php else: ?>
                <div id="users-container">
                    <?php foreach ($users as $index => $user): 
                        $sale_id = get_user_meta($user->ID, 'creem_sale_id', true);
                        $product_name = get_user_meta($user->ID, 'creem_product_name', true);
                        $product_id = get_user_meta($user->ID, 'creem_product_id', true);
                        $created_date = get_user_meta($user->ID, 'creem_created_date', true);
                        $email_sent = get_user_meta($user->ID, 'creem_email_sent', true);
                        $email_sent_date = get_user_meta($user->ID, 'creem_email_sent_date', true);
                        $sale_data = get_user_meta($user->ID, 'creem_sale_data', true);
                        $assigned_roles = get_user_meta($user->ID, 'creem_assigned_roles', true);
                        $last_purchase_date = get_user_meta($user->ID, 'creem_last_purchase_date', true);
                        $purchase_history = get_user_meta($user->ID, 'creem_purchase_history', true);
                        
                        $user_data = get_userdata($user->ID);
                        $registered_date = $user_data->user_registered;
                        $roles = $user_data->roles;
                        
                        // Email preview (first 30 chars)
                        $email_preview = strlen($user->user_email) > 30 ? substr($user->user_email, 0, 30) . '...' : $user->user_email;
                    ?>
                        <div class="snn-creem-user-entry">
                            <div class="snn-creem-user-header" onclick="toggleUser(<?php echo $user->ID; ?>)">
                                <div class="snn-creem-user-main-info">
                                    <span class="dashicons dashicons-arrow-right" id="icon-<?php echo $user->ID; ?>"></span>
                                    <div>
                                        <strong class="snn-creem-user-username"><?php echo esc_html($user->user_login); ?></strong>
                                        <span class="snn-creem-user-email-preview"><?php echo esc_html($email_preview); ?></span>
                                    </div>
                                </div>
                                <div class="snn-creem-user-meta-info">
                                    <span><strong><?php _e('Product:', 'snn'); ?></strong> <?php echo esc_html($product_name ? $product_name : 'N/A'); ?></span>
                                    <span><strong><?php _e('Created:', 'snn'); ?></strong> <?php echo esc_html($created_date ? $created_date : $registered_date); ?></span>
                                    <?php if ($email_sent === 'yes'): ?>
                                        <span class="snn-creem-email-status-yes">✓ <?php _e('Email Sent', 'snn'); ?></span>
                                    <?php else: ?>
                                        <span class="snn-creem-email-status-no">✗ <?php _e('No Email', 'snn'); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="snn-creem-user-details" id="user-<?php echo $user->ID; ?>">
                                <div class="snn-creem-user-details-grid">
                                    <!-- Left Column -->
                                    <div>
                                        <h3><?php _e('User Information', 'snn'); ?></h3>
                                        <table class="widefat">
                                            <tr><th><?php _e('User ID', 'snn'); ?></th><td><?php echo esc_html($user->ID); ?></td></tr>
                                            <tr><th><?php _e('Username', 'snn'); ?></th><td><?php echo esc_html($user->user_login); ?></td></tr>
                                            <tr><th><?php _e('Email', 'snn'); ?></th><td><?php echo esc_html($user->user_email); ?></td></tr>
                                            <tr><th><?php _e('Registered', 'snn'); ?></th><td><?php echo esc_html($registered_date); ?></td></tr>
                                            <tr><th><?php _e('Current Roles', 'snn'); ?></th><td><?php echo esc_html(implode(', ', $roles)); ?></td></tr>
                                        </table>
                                        
                                        <h3><?php _e('creem Information', 'snn'); ?></h3>
                                        <table class="widefat">
                                            <tr><th><?php _e('Sale ID', 'snn'); ?></th><td><code><?php echo esc_html($sale_id ? $sale_id : 'N/A'); ?></code></td></tr>
                                            <tr><th><?php _e('Product Name', 'snn'); ?></th><td><?php echo esc_html($product_name ? $product_name : 'N/A'); ?></td></tr>
                                            <tr><th><?php _e('Product ID', 'snn'); ?></th><td><code><?php echo esc_html($product_id ? $product_id : 'N/A'); ?></code></td></tr>
                                            <tr><th><?php _e('Created Date', 'snn'); ?></th><td><?php echo esc_html($created_date ? $created_date : 'N/A'); ?></td></tr>
                                            <tr><th><?php _e('Assigned Roles', 'snn'); ?></th><td><?php echo esc_html($assigned_roles ? implode(', ', json_decode($assigned_roles, true)) : 'N/A'); ?></td></tr>
                                        </table>
                                        
                                        <h3><?php _e('Email Status', 'snn'); ?></h3>
                                        <table class="widefat">
                                            <tr><th><?php _e('Email Sent', 'snn'); ?></th><td><?php echo $email_sent === 'yes' ? '<span class="snn-creem-email-status-yes">✓ Yes</span>' : '<span class="snn-creem-email-status-no">✗ No</span>'; ?></td></tr>
                                            <?php if ($email_sent === 'yes'): ?>
                                            <tr><th><?php _e('Email Sent Date', 'snn'); ?></th><td><?php echo esc_html($email_sent_date); ?></td></tr>
                                            <?php endif; ?>
                                        </table>
                                    </div>
                                    
                                    <!-- Right Column -->
                                    <div>
                                        <?php if ($last_purchase_date): ?>
                                        <h3><?php _e('Last Purchase', 'snn'); ?></h3>
                                        <table class="widefat">
                                            <tr><th><?php _e('Date', 'snn'); ?></th><td><?php echo esc_html($last_purchase_date); ?></td></tr>
                                            <tr><th><?php _e('Product', 'snn'); ?></th><td><?php echo esc_html(get_user_meta($user->ID, 'creem_last_product_name', true)); ?></td></tr>
                                        </table>
                                        <?php endif; ?>
                                        
                                        <?php if ($purchase_history): 
                                            $history = json_decode($purchase_history, true);
                                            if (is_array($history) && !empty($history)):
                                        ?>
                                        <h3><?php _e('Purchase History', 'snn'); ?></h3>
                                        <div class="snn-creem-purchase-history">
                                            <table class="widefat">
                                                <thead>
                                                    <tr>
                                                        <th><?php _e('Date', 'snn'); ?></th>
                                                        <th><?php _e('Product', 'snn'); ?></th>
                                                        <th><?php _e('Roles Added', 'snn'); ?></th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach (array_reverse($history) as $purchase): ?>
                                                    <tr>
                                                        <td><?php echo esc_html($purchase['date']); ?></td>
                                                        <td><?php echo esc_html($purchase['product_name']); ?></td>
                                                        <td><?php echo esc_html(implode(', ', $purchase['roles_added'])); ?></td>
                                                    </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                        <?php endif; endif; ?>
                                        
                                        <h3><?php _e('Raw Sale Data', 'snn'); ?></h3>
                                        <div class="snn-creem-raw-data">
                                            <pre><?php 
                                                if ($sale_data) {
                                                    $decoded_data = json_decode($sale_data, true);
                                                    echo esc_html(print_r($decoded_data, true));
                                                } else {
                                                    echo 'No raw sale data available';
                                                }
                                            ?></pre>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="snn-creem-user-actions">
                                    <a href="<?php echo admin_url('user-edit.php?user_id=' . $user->ID); ?>" class="button button-primary" target="_blank"><?php _e('Edit User', 'snn'); ?></a>
                                    <a href="mailto:<?php echo esc_attr($user->user_email); ?>" class="button"><?php _e('Send Email', 'snn'); ?></a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <?php if ($total_pages > 1): ?>
                    <div class="snn-creem-pagination">
                        <div class="tablenav-pages">
                            <?php
                            echo paginate_links(array(
                                'base' => add_query_arg('paged', '%#%'),
                                'format' => '',
                                'prev_text' => __('&laquo; Previous'),
                                'next_text' => __('Next &raquo;'),
                                'total' => $total_pages,
                                'current' => $page,
                                'type' => 'plain'
                            ));
                            ?>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- Save Button at Bottom -->
                <div class="snn-creem-section">
                    <form method="post" action="" class="snn-creem-settings-form">
                        <?php wp_nonce_field('creem_save_user_list_settings', 'creem_user_list_settings_nonce'); ?>
                        <label for="user_list_per_page_bottom"><strong><?php _e('Users per page:', 'snn'); ?></strong></label>
                        <input type="number" name="user_list_per_page" id="user_list_per_page_bottom" value="<?php echo esc_attr($per_page); ?>" class="small-text" min="1" max="100" />
                        <?php submit_button(__('Save', 'snn'), 'primary', 'submit', false); ?>
                    </form>
                </div>
            <?php endif; ?>
        </div>
        
        <script>
        function toggleUser(userId) {
            var details = document.getElementById('user-' + userId);
            var icon = document.getElementById('icon-' + userId);
            if (details.style.display === 'none') {
                details.style.display = 'block';
                icon.classList.remove('dashicons-arrow-right');
                icon.classList.add('dashicons-arrow-down');
            } else {
                details.style.display = 'none';
                icon.classList.remove('dashicons-arrow-down');
                icon.classList.add('dashicons-arrow-right');
            }
        }
        </script>
        <?php
    }
    
    /**
     * Uninstall page - Show data to be deleted and uninstall button
     */
    public function uninstall_page() {
        // Get statistics about data to be deleted
        $data_stats = $this->get_uninstall_data_statistics();
        
        ?>
        <div class="wrap">
            <h1><?php _e('Uninstall creem API Plugin', 'snn'); ?></h1>
            
            <div class="snn-creem-section">
                <h2 style="color: #d63638;"><?php _e('⚠️ WARNING: Complete Data Removal', 'snn'); ?></h2>
                <div style="background: #fee; border-left: 4px solid #d63638; padding: 15px; margin: 20px 0;">
                    <p><strong><?php _e('This action will PERMANENTLY DELETE all plugin data from your WordPress database!', 'snn'); ?></strong></p>
                    <p><?php _e('This action cannot be undone. Please make sure you have a backup before proceeding.', 'snn'); ?></p>
                </div>
                
                <h3><?php _e('The following data will be PERMANENTLY DELETED:', 'snn'); ?></h3>
                
                <div style="background: #f9f9f9; border: 1px solid #ddd; padding: 20px; margin: 20px 0;">
                    <h4><?php _e('Plugin Settings & Data', 'snn'); ?></h4>
                    <ul style="margin: 10px 0; padding-left: 20px;">
                        <li><strong><?php _e('Main Plugin Settings:', 'snn'); ?></strong> creem_api_settings</li>
                        <li><strong><?php _e('Activity Logs:', 'snn'); ?></strong> <?php echo number_format($data_stats['logs_count']); ?> entries</li>
                        <li><strong><?php _e('Processed Sales List:', 'snn'); ?></strong> <?php echo number_format($data_stats['processed_sales_count']); ?> sale IDs</li>
                        <li><strong><?php _e('Scheduled Cron Jobs:', 'snn'); ?></strong> creem_api_check_sales</li>
                    </ul>
                    
                    <h4><?php _e('User Metadata (from Creem.io Users)', 'snn'); ?></h4>
                    <ul style="margin: 10px 0; padding-left: 20px;">
                        <li><strong><?php _e('Total affected users:', 'snn'); ?></strong> <?php echo number_format($data_stats['creem_users_count']); ?></li>
                        <li><?php _e('creem_sale_id - Original sale ID', 'snn'); ?></li>
                        <li><?php _e('creem_product_name - Purchased product name', 'snn'); ?></li>
                        <li><?php _e('creem_product_id - Product ID', 'snn'); ?></li>
                        <li><?php _e('creem_created_date - User creation date', 'snn'); ?></li>
                        <li><?php _e('creem_sale_data - Raw sale data JSON', 'snn'); ?></li>
                        <li><?php _e('creem_assigned_roles - Roles assigned by plugin', 'snn'); ?></li>
                        <li><?php _e('creem_email_sent - Email sent status', 'snn'); ?></li>
                        <li><?php _e('creem_email_sent_date - Email sent timestamp', 'snn'); ?></li>
                        <li><?php _e('creem_last_purchase_date - Last purchase date', 'snn'); ?></li>
                        <li><?php _e('creem_last_product_name - Last purchased product', 'snn'); ?></li>
                        <li><?php _e('creem_last_product_id - Last product ID', 'snn'); ?></li>
                        <li><?php _e('creem_last_sale_id - Last sale ID', 'snn'); ?></li>
                        <li><?php _e('creem_purchase_history - Purchase history JSON', 'snn'); ?></li>
                        <li><?php _e('creem_refunded - Refund status', 'snn'); ?></li>
                        <li><?php _e('creem_refunded_date - Refund date', 'snn'); ?></li>
                        <li><?php _e('creem_subscription_status - Subscription status', 'snn'); ?></li>
                        <li><?php _e('creem_subscription_ended_date - Subscription end date', 'snn'); ?></li>
                    </ul>
                    
                    <p style="margin-top: 15px;"><strong><?php _e('Note:', 'snn'); ?></strong> <?php _e('WordPress user accounts will NOT be deleted, only the creem metadata will be removed.', 'snn'); ?></p>
                </div>
                
                <div style="background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 20px 0;">
                    <h4><?php _e('Before proceeding:', 'snn'); ?></h4>
                    <ul>
                        <li>✓ <?php _e('Make sure you have a complete backup of your database', 'snn'); ?></li>
                        <li>✓ <?php _e('Understand that this action cannot be undone', 'snn'); ?></li>
                        <li>✓ <?php _e('The plugin will be automatically deactivated after data deletion', 'snn'); ?></li>
                    </ul>
                </div>
                
                <div style="margin-top: 30px; text-align: center; padding: 20px; background: #f8f8f8; border: 1px solid #ddd;">
                    <h3 style="color: #d63638; margin: 0 0 20px 0;"><?php _e('Are you absolutely sure?', 'snn'); ?></h3>
                    <p><?php _e('Type "DELETE ALL DATA" in the box below and click the uninstall button:', 'snn'); ?></p>
                    <input type="text" id="confirmation-text" placeholder="<?php _e('Type DELETE ALL DATA', 'snn'); ?>" style="padding: 10px; font-size: 16px; width: 300px; margin: 10px;" />
                    <br><br>
                    <button type="button" id="uninstall-button" class="button" style="background: #d63638; color: white; font-size: 18px; padding: 15px 30px; border: none; cursor: pointer;" onclick="confirmUninstall()" disabled><?php _e('🗑️ UNINSTALL PLUGIN & DELETE ALL DATA', 'snn'); ?></button>
                    <div id="uninstall-result" style="margin-top: 20px;"></div>
                </div>
            </div>
        </div>
        
        <script>
        // Enable/disable uninstall button based on confirmation text
        document.getElementById('confirmation-text').addEventListener('input', function() {
            var button = document.getElementById('uninstall-button');
            if (this.value === 'DELETE ALL DATA') {
                button.disabled = false;
                button.style.background = '#d63638';
            } else {
                button.disabled = true;
                button.style.background = '#ccc';
            }
        });
        
        function confirmUninstall() {
            var confirmText = document.getElementById('confirmation-text').value;
            var resultDiv = document.getElementById('uninstall-result');
            
            if (confirmText !== 'DELETE ALL DATA') {
                alert('<?php _e('Please type "DELETE ALL DATA" exactly as shown.', 'snn'); ?>');
                return;
            }
            
            if (!confirm('<?php _e('FINAL WARNING: This will permanently delete all creem API plugin data and deactivate the plugin. This action cannot be undone!\\n\\nAre you absolutely sure you want to proceed?', 'snn'); ?>')) {
                return;
            }
            
            resultDiv.innerHTML = '<p><span class=\"spinner is-active\"></span> <?php _e('Deleting all plugin data...', 'snn'); ?></p>';
            document.getElementById('uninstall-button').disabled = true;
            
            jQuery.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'creem_uninstall_plugin',
                    confirmation: confirmText,
                    nonce: '<?php echo wp_create_nonce('creem_uninstall'); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        resultDiv.innerHTML = '<div style=\"background: #d1edff; border-left: 4px solid #0073aa; padding: 15px;\"><h3 style=\"color: #0073aa;\">✓ Uninstall Completed Successfully!</h3><p>' + response.data.message + '</p><p><strong><?php _e('The page will redirect to the plugins page in 3 seconds...', 'snn'); ?></strong></p></div>';
                        setTimeout(function() {
                            window.location.href = '<?php echo admin_url('plugins.php'); ?>';
                        }, 3000);
                    } else {
                        resultDiv.innerHTML = '<div style=\"background: #fee; border-left: 4px solid #d63638; padding: 15px;\"><h3 style=\"color: #d63638;\">✗ Uninstall Failed</h3><p>' + response.data.message + '</p></div>';
                        document.getElementById('uninstall-button').disabled = false;
                    }
                },
                error: function() {
                    resultDiv.innerHTML = '<div style=\"background: #fee; border-left: 4px solid #d63638; padding: 15px;\"><h3 style=\"color: #d63638;\">✗ Uninstall Failed</h3><p><?php _e('An unexpected error occurred. Please try again.', 'snn'); ?></p></div>';
                    document.getElementById('uninstall-button').disabled = false;
                }
            });
        }
        </script>
        <?php
    }
    
    /**
     * Get statistics about data to be deleted
     */
    private function get_uninstall_data_statistics() {
        $stats = array(
            'logs_count' => 0,
            'processed_sales_count' => 0,
            'creem_users_count' => 0
        );
        
        // Count logs
        $logs = get_option($this->log_option_name, array());
        $stats['logs_count'] = count($logs);
        
        // Count processed sales
        $processed_sales = get_option('creem_processed_sales', array());
        $stats['processed_sales_count'] = count($processed_sales);
        
        // Count users with creem metadata
        $user_query = new WP_User_Query(array(
            'meta_key' => 'creem_sale_id',
            'meta_compare' => 'EXISTS',
            'fields' => 'ID'
        ));
        $stats['creem_users_count'] = $user_query->get_total();
        
        return $stats;
    }
    
    /**
     * AJAX handler for plugin uninstall
     */
    public function uninstall_plugin_data() {
        check_ajax_referer('creem_uninstall', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('You do not have permission to perform this action.', 'snn')));
        }
        
        $confirmation = isset($_POST['confirmation']) ? sanitize_text_field($_POST['confirmation']) : '';
        
        if ($confirmation !== 'DELETE ALL DATA') {
            wp_send_json_error(array('message' => __('Invalid confirmation text.', 'snn')));
        }
        
        try {
            $deleted_data = $this->delete_all_plugin_data();
            
            // Deactivate the plugin
            deactivate_plugins(plugin_basename(__FILE__));
            
            wp_send_json_success(array(
                'message' => sprintf(
                    __('Successfully deleted all plugin data: %s. Plugin has been deactivated.', 'snn'),
                    implode(', ', $deleted_data)
                )
            ));
        } catch (Exception $e) {
            wp_send_json_error(array('message' => sprintf(__('Error during uninstall: %s', 'snn'), $e->getMessage())));
        }
    }
    
    /**
     * Delete all plugin data
     */
    private function delete_all_plugin_data() {
        global $wpdb;
        $deleted_data = array();
        
        // 1. Delete WordPress options
        if (delete_option($this->option_name)) {
            $deleted_data[] = __('Plugin Settings', 'snn');
        }
        
        if (delete_option($this->log_option_name)) {
            $deleted_data[] = __('Activity Logs', 'snn');
        }
        
        if (delete_option('creem_processed_sales')) {
            $deleted_data[] = __('Processed Sales List', 'snn');
        }
        
        // 2. Remove scheduled cron jobs
        $timestamp = wp_next_scheduled('creem_api_check_sales');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'creem_api_check_sales');
            $deleted_data[] = __('Cron Jobs', 'snn');
        }
        
        // 3. Delete all user meta data with creem prefix
        $creem_meta_keys = array(
            'creem_sale_id',
            'creem_product_name',
            'creem_product_id',
            'creem_created_date',
            'creem_sale_data',
            'creem_assigned_roles',
            'creem_email_sent',
            'creem_email_sent_date',
            'creem_last_purchase_date',
            'creem_last_product_name',
            'creem_last_product_id',
            'creem_last_sale_id',
            'creem_purchase_history',
            'creem_refunded',
            'creem_refunded_date',
            'creem_subscription_status',
            'creem_subscription_ended_date',
            'creem_customer_id'
        );
        
        $total_meta_deleted = 0;
        foreach ($creem_meta_keys as $meta_key) {
            $result = $wpdb->query($wpdb->prepare(
                "DELETE FROM {$wpdb->usermeta} WHERE meta_key = %s",
                $meta_key
            ));
            if ($result !== false) {
                $total_meta_deleted += $result;
            }
        }
        
        if ($total_meta_deleted > 0) {
            $deleted_data[] = sprintf(__('%d User Metadata Entries', 'snn'), $total_meta_deleted);
        }
        
        // 4. Clean up any remaining creem-related options (catch-all)
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE 'creem_%'");

        return $deleted_data;
    }

    /**
     * Check and redirect users with expired subscriptions to renewal page
     */
    public function check_subscription_renewal_redirect() {
        // Don't run on admin pages
        if (is_admin()) {
            return;
        }

        // Check if user is logged in
        if (!is_user_logged_in()) {
            return;
        }

        $current_user = wp_get_current_user();

        // Exception for administrators and editors - they are never affected
        if (in_array('administrator', $current_user->roles) || in_array('editor', $current_user->roles)) {
            return;
        }

        // Get plugin settings
        $settings = get_option($this->option_name);

        // Check if subscription handling is enabled
        if (!isset($settings['handle_subscriptions']) || !$settings['handle_subscriptions']) {
            return;
        }

        // Get renewal page setting
        $renewal_page_id = isset($settings['subscription_renewal_page']) ? intval($settings['subscription_renewal_page']) : 0;

        // If no renewal page is set, don't redirect
        if (empty($renewal_page_id)) {
            return;
        }

        // Don't redirect if user is already on the renewal page
        if (is_page($renewal_page_id)) {
            return;
        }

        // Check if user has creem metadata (meaning they were created by this plugin)
        $creem_sale_id = get_user_meta($current_user->ID, 'creem_sale_id', true);
        if (empty($creem_sale_id)) {
            // User was not created by creem, don't redirect
            return;
        }

        // Check subscription status
        $subscription_status = get_user_meta($current_user->ID, 'creem_subscription_status', true);

        // If subscription is cancelled or ended, redirect to renewal page
        if ($subscription_status === 'cancelled') {
            // Check if user lost their roles (which means subscription was processed as ended)
            $assigned_roles = get_user_meta($current_user->ID, 'creem_assigned_roles', true);
            if ($assigned_roles) {
                $roles = json_decode($assigned_roles, true);
                if (is_array($roles) && !empty($roles)) {
                    // Check if user still has any of the assigned roles
                    $has_any_role = false;
                    foreach ($roles as $role) {
                        if (in_array($role, $current_user->roles)) {
                            $has_any_role = true;
                            break;
                        }
                    }

                    // If user doesn't have any of their assigned roles, subscription has ended
                    if (!$has_any_role) {
                        // Get product information for logging
                        $product_name = get_user_meta($current_user->ID, 'creem_product_name', true);

                        $this->log_activity('Subscription renewal redirect', array(
                            'user_id' => $current_user->ID,
                            'email' => $current_user->user_email,
                            'product' => $product_name,
                            'subscription_status' => $subscription_status,
                            'renewal_page_id' => $renewal_page_id
                        ));

                        // Redirect to renewal page
                        wp_redirect(get_permalink($renewal_page_id));
                        exit;
                    }
                }
            }
        }
    }

    /**
     * Generate a Creem.io customer billing portal link via the API.
     *
     * @param string $customer_id The Creem.io customer ID.
     * @return string|WP_Error Portal URL on success, WP_Error on failure.
     */
    private function generate_customer_portal_link($customer_id) {
        $settings = get_option($this->option_name);
        $access_token = isset($settings['access_token']) ? $settings['access_token'] : '';

        if (empty($access_token) || empty($customer_id)) {
            return new WP_Error('invalid_params', 'API key and customer ID are required');
        }

        $test_mode = isset($settings['test_mode']) && $settings['test_mode'];
        $base_url = $test_mode ? 'https://test-api.creem.io' : 'https://api.creem.io';

        $response = wp_remote_post($base_url . '/v1/customers/billing', array(
            'headers' => $this->get_api_headers($access_token),
            'body'    => json_encode(array('customer_id' => $customer_id)),
            'timeout' => 15
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        $http_code = wp_remote_retrieve_response_code($response);
        $body      = wp_remote_retrieve_body($response);
        $data      = json_decode($body, true);

        if ($http_code !== 200 || !isset($data['customer_portal_link'])) {
            $error_msg = $this->get_api_error_message($data);
            return new WP_Error('api_error', $error_msg);
        }

        return $data['customer_portal_link'];
    }

    /**
     * Shortcode: [creem_billing_link]
     *
     * Renders a customer billing portal link for the currently logged-in user.
     *
     * Attributes:
     *   text                 - Anchor text (default: "Manage Subscription")
     *   class                - CSS class(es) to add to the <a> tag
     *   not_logged_in_text   - Text shown when user is not logged in (empty = show nothing)
     *   no_subscription_text - Text shown when user has no Creem customer record (empty = show nothing)
     *
     * Usage examples:
     *   [creem_billing_link]
     *   [creem_billing_link text="Manage Billing" class="button"]
     *   [creem_billing_link not_logged_in_text="Please log in to manage your subscription."]
     */
    public function creem_billing_link_shortcode($atts) {
        $atts = shortcode_atts(array(
            'text'                 => __('Manage Subscription', 'snn'),
            'class'                => '',
            'not_logged_in_text'   => '',
            'no_subscription_text' => '',
        ), $atts, 'creem_billing_link');

        if (!is_user_logged_in()) {
            return !empty($atts['not_logged_in_text'])
                ? '<span>' . esc_html($atts['not_logged_in_text']) . '</span>'
                : '';
        }

        $current_user = wp_get_current_user();

        // Retrieve stored customer ID.
        $customer_id = get_user_meta($current_user->ID, 'creem_customer_id', true);

        // If not cached, try to extract it from the raw sale data.
        if (empty($customer_id)) {
            $sale_data_json = get_user_meta($current_user->ID, 'creem_sale_data', true);
            if (!empty($sale_data_json)) {
                $sale_data = json_decode($sale_data_json, true);
                if (is_array($sale_data) && isset($sale_data['customer'])) {
                    if (is_string($sale_data['customer']) && !empty($sale_data['customer'])) {
                        $customer_id = $sale_data['customer'];
                    } elseif (is_array($sale_data['customer']) && !empty($sale_data['customer']['id'])) {
                        $customer_id = $sale_data['customer']['id'];
                    }
                }
                // Cache for future shortcode renders.
                if (!empty($customer_id)) {
                    update_user_meta($current_user->ID, 'creem_customer_id', $customer_id);
                }
            }
        }

        if (empty($customer_id)) {
            return !empty($atts['no_subscription_text'])
                ? '<span>' . esc_html($atts['no_subscription_text']) . '</span>'
                : '';
        }

        $portal_link = $this->generate_customer_portal_link($customer_id);

        if (is_wp_error($portal_link)) {
            return '';
        }

        $class_attr = !empty($atts['class']) ? ' class="' . esc_attr($atts['class']) . '"' : '';

        return '<a href="' . esc_url($portal_link) . '"' . $class_attr . ' target="_blank" rel="noopener noreferrer">'
            . esc_html($atts['text'])
            . '</a>';
    }
}

// Initialize the plugin
new Creem_API_WordPress();