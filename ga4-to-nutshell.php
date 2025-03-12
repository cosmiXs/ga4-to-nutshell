<?php

/**
 * Plugin Name: GA4 to Nutshell CRM Integration
 * Plugin URI: https://plusinfinit.com
 * Description: Integrates GA4 datalayer events with Nutshell CRM, focusing on Ninja Forms submissions.
 * Version: 1.0.0
 * Author: cosmixs
 * Author URI: https://plusinfinit.com
 * Text Domain: ga4-to-nutshell
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class GA4_To_Nutshell
{

    // Plugin version
    const VERSION = '1.0.0';

    // Singleton instance
    private static $instance = null;

    // Plugin settings
    private $settings = [];

    /**
     * Get singleton instance
     */
    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct()
    {
        // Define constants
        $this->define_constants();

        // Include required files
        $this->include_files();

        // Load settings
        $this->settings = get_option('ga4_to_nutshell_settings', []);

        // Initialize hooks
        $this->init_hooks();
    }

    /**
     * Define plugin constants
     */
    private function define_constants()
    {
        define('GA4_TO_NUTSHELL_VERSION', self::VERSION);
        define('GA4_TO_NUTSHELL_PLUGIN_DIR', plugin_dir_path(__FILE__));
        define('GA4_TO_NUTSHELL_PLUGIN_URL', plugin_dir_url(__FILE__));
        define('GA4_TO_NUTSHELL_INCLUDES_DIR', GA4_TO_NUTSHELL_PLUGIN_DIR . 'includes/');
        define('GA4_TO_NUTSHELL_ASSETS_URL', GA4_TO_NUTSHELL_PLUGIN_URL . 'assets/');
    }

    /**
     * Include required files
     */
    private function include_files()
    {
        // Include admin functions
        require_once GA4_TO_NUTSHELL_INCLUDES_DIR . 'admin-functions.php';

        // Include AJAX handler
        require_once GA4_TO_NUTSHELL_INCLUDES_DIR . 'ajax-handler.php';
    }

    /**
     * Initialize WordPress hooks
     */
    private function init_hooks()
    {
        // Admin hooks
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_enqueue_scripts', [$this, 'admin_enqueue_scripts']);

        // Frontend hooks for GA4 integration
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_scripts']);

        // AJAX handlers
        add_action('wp_ajax_ga4_to_nutshell_get_users', [$this, 'ajax_get_nutshell_users']);
        add_action('wp_ajax_ga4_to_nutshell_get_ninja_forms', [$this, 'ajax_get_ninja_forms']);
        add_action('wp_ajax_ga4_to_nutshell_get_form_fields', [$this, 'ajax_get_form_fields']);
        add_action('wp_ajax_ga4_to_nutshell_test_connection', [$this, 'ajax_test_connection']);
        add_action('wp_ajax_ga4_to_nutshell_process_data', [$this, 'process_ajax_data']);
        add_action('wp_ajax_nopriv_ga4_to_nutshell_process_data', [$this, 'process_ajax_data']);

        // Direct Ninja Forms integration - This is the key addition
        if (class_exists('Ninja_Forms')) {
            add_action('ninja_forms_after_submission', [$this, 'process_ninja_form_submission'], 10, 1);
        }
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu()
    {
        add_options_page(
            __('GA4 to Nutshell CRM', 'ga4-to-nutshell'),
            __('GA4 to Nutshell', 'ga4-to-nutshell'),
            'manage_options',
            'ga4-to-nutshell',
            [$this, 'render_settings_page']
        );
    }

    /**
     * Register plugin settings
     */
    public function register_settings()
    {
        register_setting('ga4_to_nutshell_settings', 'ga4_to_nutshell_settings');

        add_settings_section(
            'ga4_to_nutshell_nutshell_api',
            __('Nutshell CRM API Settings', 'ga4-to-nutshell'),
            [$this, 'render_api_section_description'],
            'ga4-to-nutshell'
        );


        add_settings_field(
            'nutshell_username',
            __('Nutshell Username/Email', 'ga4-to-nutshell'),
            [$this, 'render_text_field'],
            'ga4-to-nutshell',
            'ga4_to_nutshell_nutshell_api',
            [
                'field' => 'nutshell_username',
                'description' => __('Your Nutshell API username or email address', 'ga4-to-nutshell')
            ]
        );

        add_settings_field(
            'nutshell_api_key',
            __('Nutshell API Key', 'ga4-to-nutshell'),
            [$this, 'render_text_field'],
            'ga4-to-nutshell',
            'ga4_to_nutshell_nutshell_api',
            [
                'field' => 'nutshell_api_key',
                'description' => __('Your Nutshell API key', 'ga4-to-nutshell'),
                'type' => 'password'
            ]
        );

        add_settings_section(
            'ga4_to_nutshell_form_mapping',
            __('Form to User Mapping', 'ga4-to-nutshell'),
            [$this, 'render_mapping_section_description'],
            'ga4-to-nutshell'
        );

        add_settings_field(
            'form_user_mapping',
            __('Form to User Assignment', 'ga4-to-nutshell'),
            [$this, 'render_form_user_mapping'],
            'ga4-to-nutshell',
            'ga4_to_nutshell_form_mapping'
        );
        // Add Field Mapping section
        add_settings_section(
            'ga4_to_nutshell_field_mapping',
            __('Field Mapping', 'ga4-to-nutshell'),
            [$this, 'render_field_mapping_section_description'],
            'ga4-to-nutshell'
        );

        add_settings_field(
            'field_mapping',
            __('Ninja Forms Field Mapping', 'ga4-to-nutshell'),
            [$this, 'render_field_mapping'],
            'ga4-to-nutshell',
            'ga4_to_nutshell_field_mapping'
        );
        add_settings_section(
            'ga4_to_nutshell_debug',
            __('Debug Settings', 'ga4-to-nutshell'),
            [$this, 'render_debug_section_description'],
            'ga4-to-nutshell'
        );

        add_settings_field(
            'debug_mode',
            __('Enable Debug Mode', 'ga4-to-nutshell'),
            [$this, 'render_checkbox_field'],
            'ga4-to-nutshell',
            'ga4_to_nutshell_debug',
            [
                'field' => 'debug_mode',
                'description' => __('Log debug information to the browser console', 'ga4-to-nutshell')
            ]
        );
    }

    /**
     * Render API section description
     */
    public function render_api_section_description()
    {
        echo '<p>' . __('Enter your Nutshell CRM API credentials below. You can find these in your Nutshell account settings.', 'ga4-to-nutshell') . '</p>';
    }

    /**
     * Render mapping section description
     */
    public function render_mapping_section_description()
    {
        echo '<p>' . __('Map each Ninja Form to a Nutshell CRM user to assign leads appropriately.', 'ga4-to-nutshell') . '</p>';
    }
    /**
     * Render field mapping section description
     * - Add this as a new method in the class
     */
    public function render_field_mapping_section_description()
    {
        echo '<p>' . __('Map your Ninja Form fields to Nutshell CRM contact fields. This ensures data is correctly sent to Nutshell.', 'ga4-to-nutshell') . '</p>';
    }

    /**
     * Render field mapping UI
     * - Add this as a new method in the class
     */
    public function render_field_mapping()
    {
        // Define the Nutshell fields we support mapping to
        $nutshell_fields = [
            'email' => __('Email', 'ga4-to-nutshell'),
            'name' => __('Name', 'ga4-to-nutshell'),
            'phone' => __('Phone', 'ga4-to-nutshell'),
            'company' => __('Company', 'ga4-to-nutshell'),
            'address' => __('Address', 'ga4-to-nutshell'),
            'country' => __('Country', 'ga4-to-nutshell')
        ];

        echo '<div id="ga4-to-nutshell-field-mapping-container">';
        echo '<p>' . __('Select a Ninja Form to configure field mappings:', 'ga4-to-nutshell') . '</p>';

        echo '<select id="ninja-form-selector" class="widefat">';
        echo '<option value="">' . __('-- Select a Form --', 'ga4-to-nutshell') . '</option>';
        echo '</select>';

        echo '<div id="field-mapping-content" style="margin-top: 15px;">';
        echo '<p>' . __('Loading...', 'ga4-to-nutshell') . '</p>';
        echo '</div>';
        // At the end of the render_field_mapping method, add:
        echo '<div class="debug-tools" style="margin-top: 15px; padding: 10px; background: #f8f8f8; border: 1px solid #ddd;">';
        echo '<button type="button" class="button" id="debug-field-mappings">Debug Field Mappings</button>';
        echo '<div id="debug-output" style="margin-top: 10px; white-space: pre-wrap;"></div>';
        echo '</div>';
        echo '</div>';

        // Template for field mapping table
?>
        <script type="text/template" id="field-mapping-template">
            <table class="widefat field-mapping-table">
            <thead>
                <tr>
                    <th><?php _e('Nutshell Field', 'ga4-to-nutshell'); ?></th>
                    <th><?php _e('Ninja Form Field', 'ga4-to-nutshell'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($nutshell_fields as $field_key => $field_label) : ?>
                <tr>
                    <td><?php echo esc_html($field_label); ?></td>
                    <td>
                        <select name="ga4_to_nutshell_settings[field_mappings][{{formId}}][<?php echo $field_key; ?>]" class="widefat">
                            <option value=""><?php _e('-- Not Mapped --', 'ga4-to-nutshell'); ?></option>
                            {{#formFields}}
                            <option value="{{id}}" {{#selected}}selected="selected"{{/selected}}>{{label}}</option>
                            {{/formFields}}
                        </select>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </script>
<?php
    }
    /**
     * Render debug section description
     */
    public function render_debug_section_description()
    {
        echo '<p>' . __('Debug settings for troubleshooting the integration.', 'ga4-to-nutshell') . '</p>';
    }

    /**
     * Render text field
     */
    public function render_text_field($args)
    {
        $field = $args['field'];
        $type = isset($args['type']) ? $args['type'] : 'text';
        $value = isset($this->settings[$field]) ? $this->settings[$field] : '';

        echo '<input type="' . esc_attr($type) . '" id="' . esc_attr($field) . '" name="ga4_to_nutshell_settings[' . esc_attr($field) . ']" value="' . esc_attr($value) . '" class="regular-text" />';

        if (isset($args['description'])) {
            echo '<p class="description">' . esc_html($args['description']) . '</p>';
        }
    }

    /**
     * Render checkbox field
     */
    public function render_checkbox_field($args)
    {
        $field = $args['field'];
        $checked = isset($this->settings[$field]) && $this->settings[$field] ? 'checked' : '';

        echo '<input type="checkbox" id="' . esc_attr($field) . '" name="ga4_to_nutshell_settings[' . esc_attr($field) . ']" value="1" ' . $checked . ' />';

        if (isset($args['description'])) {
            echo '<p class="description">' . esc_html($args['description']) . '</p>';
        }
    }

    /**
     * Render form to user mapping field
     */
    public function render_form_user_mapping()
    {
        echo '<div id="ga4-to-nutshell-mapping-container">';
        echo '<p>' . __('Loading Ninja Forms and Nutshell users...', 'ga4-to-nutshell') . '</p>';
        echo '</div>';

        echo '<button type="button" class="button" id="ga4-to-nutshell-add-mapping">' . __('Add Mapping', 'ga4-to-nutshell') . '</button>';
    }

    /**
     * Render settings page
     */
    public function render_settings_page()
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        // Get current tab
        $current_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'settings';

        echo '<div class="wrap">';
        echo '<h1>' . esc_html(get_admin_page_title()) . '</h1>';

        // Add tabs if function exists
        if (function_exists('ga4_to_nutshell_add_settings_tabs')) {
            $current_tab = ga4_to_nutshell_add_settings_tabs();
        }

        // Display appropriate content based on tab
        switch ($current_tab) {
            case 'logs':
                if (function_exists('ga4_to_nutshell_display_logs')) {
                    ga4_to_nutshell_display_logs();
                }
                break;

            case 'test':
                if (function_exists('ga4_to_nutshell_display_test_tools')) {
                    ga4_to_nutshell_display_test_tools();
                }
                break;

            default: // 'settings' tab or any other
                echo '<form action="options.php" method="post">';
                settings_fields('ga4_to_nutshell_settings');
                do_settings_sections('ga4-to-nutshell');

                echo '<div class="ga4-to-nutshell-test-connection">';
                echo '<h2>' . __('Test Connection', 'ga4-to-nutshell') . '</h2>';
                echo '<p>' . __('Test your Nutshell CRM API connection:', 'ga4-to-nutshell') . '</p>';
                echo '<button type="button" class="button" id="ga4-to-nutshell-test-connection">' . __('Test Connection', 'ga4-to-nutshell') . '</button>';
                echo '<div id="ga4-to-nutshell-test-result"></div>';
                echo '</div>';

                submit_button();
                echo '</form>';
                break;
        }

        echo '</div>';
    }

    /**
     * Enqueue admin scripts and styles
     */
    public function admin_enqueue_scripts($hook)
    {
        if ('settings_page_ga4-to-nutshell' !== $hook) {
            return;
        }

        wp_enqueue_style(
            'ga4-to-nutshell-admin',
            GA4_TO_NUTSHELL_ASSETS_URL . 'css/admin.css',
            [],
            GA4_TO_NUTSHELL_VERSION
        );

        wp_enqueue_script(
            'ga4-to-nutshell-admin',
            GA4_TO_NUTSHELL_ASSETS_URL . 'js/admin.js',
            ['jquery'],
            GA4_TO_NUTSHELL_VERSION,
            true
        );

        wp_localize_script('ga4-to-nutshell-admin', 'ga4ToNutshell', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ga4-to-nutshell-nonce'),
            'settings' => $this->settings,
        ]);
    }

    /**
     * Enqueue frontend scripts
     */
    public function enqueue_frontend_scripts()
    {
        // Only load if we have API credentials
        if (empty($this->settings['nutshell_username']) || empty($this->settings['nutshell_api_key'])) {
            return;
        }

        wp_enqueue_script(
            'ga4-to-nutshell-integration',
            GA4_TO_NUTSHELL_ASSETS_URL . 'js/integration.js',
            [],
            GA4_TO_NUTSHELL_VERSION,
            true
        );

        // Pass settings to JavaScript
        wp_localize_script('ga4-to-nutshell-integration', 'ga4ToNutshellSettings', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ga4-to-nutshell-frontend-nonce'),
            'mappings' => isset($this->settings['form_user_mappings']) ? $this->settings['form_user_mappings'] : [],
            'debug' => isset($this->settings['debug_mode']) && $this->settings['debug_mode'] ? true : false,
        ]);
    }

    /**
     * Process Ninja Form submission (server-side option)
     */
    // public function process_ninja_form_submission($form_data)
    // {
    //     // This is an optional server-side handler for Ninja Forms submissions
    //     // We're primarily using client-side JS to handle the GA4 datalayer events
    //     // but this could be useful as a fallback or for server-side processing

    //     // Get form ID and form title
    //     $form_id = $form_data['form_id'];
    //     $form_title = isset($form_data['settings']['title']) ? $form_data['settings']['title'] : '';

    //     // Check if we have a mapping for this form
    //     if (!isset($this->settings['form_user_mappings']) || !is_array($this->settings['form_user_mappings'])) {
    //         return;
    //     }

    //     $assigned_user = null;
    //     foreach ($this->settings['form_user_mappings'] as $mapping) {
    //         if ($mapping['form_id'] == $form_id) {
    //             $assigned_user = $mapping['user_id'];
    //             break;
    //         }
    //     }

    //     if (!$assigned_user) {
    //         return;
    //     }

    //     // Extract form field values
    //     $field_values = [];
    //     foreach ($form_data['fields'] as $field) {
    //         $field_values[$field['key']] = $field['value'];
    //     }

    //     // Get referrer and URL info
    //     $referrer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
    //     $current_url = isset($_SERVER['HTTP_HOST']) && isset($_SERVER['REQUEST_URI'])
    //         ? (is_ssl() ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']
    //         : '';

    //     // Send to Nutshell - we'll use the function from ajax-handler.php
    //     if (function_exists('ga4_to_nutshell_send_to_nutshell')) {
    //         ga4_to_nutshell_send_to_nutshell(
    //             $this->settings,
    //             $field_values,
    //             $form_title,
    //             $assigned_user,
    //             '',
    //             $referrer,
    //             $current_url
    //         );
    //     } else {
    //         error_log('GA4 to Nutshell: ga4_to_nutshell_send_to_nutshell function not found');
    //     }
    // }
    /**
     * Process Ninja Form submission directly
     * Completely rewritten to properly extract field data
     */
    public function process_ninja_form_submission($form_data)
    {
        ga4_to_nutshell_log('Ninja Forms submission received', $form_data);

        // Get form ID
        $form_id = $form_data['form_id'];
        $form_title = isset($form_data['settings']['title']) ? $form_data['settings']['title'] : 'Ninja Form ' . $form_id;

        ga4_to_nutshell_log('Processing form submission', [
            'form_id' => $form_id,
            'form_title' => $form_title
        ]);

        // Check if we have a mapping for this form
        $assigned_user_id = '';
        if (isset($this->settings['form_user_mappings']) && is_array($this->settings['form_user_mappings'])) {
            foreach ($this->settings['form_user_mappings'] as $mapping) {
                if ($mapping['form_id'] == $form_id) {
                    $assigned_user_id = $mapping['user_id'];
                    ga4_to_nutshell_log('Found user mapping for form', [
                        'form_id' => $form_id,
                        'user_id' => $assigned_user_id
                    ]);
                    break;
                }
            }
        }

        // Extract field values
        $extracted_fields = [];

        if (isset($form_data['fields']) && is_array($form_data['fields'])) {
            foreach ($form_data['fields'] as $field_id => $field) {
                // Skip HTML fields and other non-data fields
                if (isset($field['type']) && in_array($field['type'], ['html', 'submit', 'hr', 'divider'])) {
                    continue;
                }

                $field_key = isset($field['key']) ? $field['key'] : $field_id;
                $field_value = isset($field['value']) ? $field['value'] : '';

                $extracted_fields[$field_id] = $field_value;
                $extracted_fields[$field_key] = $field_value; // Store by both ID and key for better matching

                ga4_to_nutshell_log('Extracted field', [
                    'field_id' => $field_id,
                    'field_key' => $field_key,
                    'field_value' => $field_value
                ]);
            }
        }

        // Get referrer and URL info
        $referrer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
        $current_url = isset($_SERVER['HTTP_HOST']) && isset($_SERVER['REQUEST_URI'])
            ? (is_ssl() ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']
            : '';

        // Determine traffic source
        $traffic_source = '';
        if (!empty($referrer)) {
            $parsed_url = parse_url($referrer);
            if (isset($parsed_url['host'])) {
                $traffic_source = $parsed_url['host'];
            }
        }

        ga4_to_nutshell_log('Sending data to Nutshell', [
            'form_id' => $form_id,
            'form_title' => $form_title,
            'assigned_user_id' => $assigned_user_id,
            'referrer' => $referrer,
            'current_url' => $current_url,
            'traffic_source' => $traffic_source,
            'field_count' => count($extracted_fields)
        ]);

        // Send data to Nutshell
        if (function_exists('ga4_to_nutshell_send_to_nutshell')) {
            $result = ga4_to_nutshell_send_to_nutshell(
                $this->settings,
                $extracted_fields,
                $form_title,
                $assigned_user_id,
                $traffic_source,
                $referrer,
                $current_url,
                $form_id
            );

            if (is_wp_error($result)) {
                ga4_to_nutshell_log('Error sending to Nutshell', $result->get_error_message(), 'error');
            } else {
                ga4_to_nutshell_log('Successfully sent to Nutshell', ['lead_id' => $result]);
            }
        } else {
            ga4_to_nutshell_log('Function ga4_to_nutshell_send_to_nutshell not found', null, 'error');
        }
    }
    /**
     * Process AJAX data - For the JS integration path
     */
    public function process_ajax_data()
    {
        // Start logging
        ga4_to_nutshell_log('Processing data from frontend', $_POST, 'info');

        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'ga4-to-nutshell-frontend-nonce')) {
            ga4_to_nutshell_log('Nonce verification failed', $_POST, 'error');
            wp_send_json_error(['message' => 'Security verification failed']);
            return;
        }

        // Get submitted data
        $json_data = isset($_POST['data']) ? sanitize_text_field($_POST['data']) : '';
        if (empty($json_data)) {
            ga4_to_nutshell_log('No data provided', $_POST, 'error');
            wp_send_json_error(['message' => 'No data provided']);
            return;
        }

        // Decode JSON data
        $data = json_decode(stripslashes($json_data), true);
        if (!$data) {
            ga4_to_nutshell_log('Invalid JSON data', $json_data, 'error');
            wp_send_json_error(['message' => 'Invalid JSON data']);
            return;
        }

        ga4_to_nutshell_log('JSON data decoded successfully', $data, 'info');

        // Extract data
        $form_data = isset($data['formData']) ? $data['formData'] : [];
        $form_id = isset($data['formId']) ? sanitize_text_field($data['formId']) : '';
        $form_name = isset($data['formName']) ? sanitize_text_field($data['formName']) : '';
        $traffic_source = isset($data['trafficSource']) ? sanitize_text_field($data['trafficSource']) : '';
        $referrer_url = isset($data['referrerUrl']) ? esc_url_raw($data['referrerUrl']) : '';
        $current_url = isset($data['currentUrl']) ? esc_url_raw($data['currentUrl']) : '';
        $assigned_user_id = isset($data['assignedUserId']) ? sanitize_text_field($data['assignedUserId']) : '';

        ga4_to_nutshell_log('Extracted data from request', [
            'form_data' => $form_data,
            'form_id' => $form_id,
            'form_name' => $form_name,
            'traffic_source' => $traffic_source,
            'referrer_url' => $referrer_url,
            'current_url' => $current_url,
            'assigned_user_id' => $assigned_user_id
        ], 'info');

        // Send data to Nutshell
        if (function_exists('ga4_to_nutshell_send_to_nutshell')) {
            $result = ga4_to_nutshell_send_to_nutshell(
                $this->settings,
                $form_data,
                $form_name,
                $assigned_user_id,
                $traffic_source,
                $referrer_url,
                $current_url,
                $form_id
            );

            if (is_wp_error($result)) {
                ga4_to_nutshell_log('Error sending data to Nutshell', $result->get_error_message(), 'error');
                wp_send_json_error(['message' => $result->get_error_message()]);
                return;
            }

            ga4_to_nutshell_log('Data sent to Nutshell successfully', ['lead_id' => $result], 'info');
            wp_send_json_success(['message' => 'Data sent to Nutshell successfully', 'lead_id' => $result]);
        } else {
            ga4_to_nutshell_log('Function ga4_to_nutshell_send_to_nutshell not found', null, 'error');
            wp_send_json_error(['message' => 'Internal error: Function not found']);
        }
    }
    /**
     * AJAX: Get Nutshell users
     */
    public function ajax_get_nutshell_users()
    {
        check_ajax_referer('ga4-to-nutshell-nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
            return;
        }

        $username = isset($_POST['username']) ? sanitize_text_field($_POST['username']) : '';
        $api_key = isset($_POST['api_key']) ? sanitize_text_field($_POST['api_key']) : '';

        if (empty($username) || empty($api_key)) {
            wp_send_json_error(['message' => 'Missing API credentials']);
            return;
        }

        $api_url = 'https://app.nutshell.com/api/v1/json';
        $payload = [
            'jsonrpc' => '2.0',
            'method' => 'findUsers',
            'params' => [
                // 'query' => [
                //     'isActive' => true
                // ]
            ],
            'id' => wp_generate_uuid4()
        ];

        $args = [
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Basic ' . base64_encode($username . ':' . $api_key)
            ],
            'body' => json_encode($payload),
            'timeout' => 30,
            'method' => 'POST'
        ];

        $response = wp_remote_request($api_url, $args);

        if (is_wp_error($response)) {
            wp_send_json_error(['message' => $response->get_error_message()]);
            return;
        }

        $response_body = json_decode(wp_remote_retrieve_body($response), true);

        if (isset($response_body['error'])) {
            wp_send_json_error(['message' => $response_body['error']['message']]);
            return;
        }

        $users = isset($response_body['result']) ? $response_body['result'] : [];

        wp_send_json_success(['users' => $users]);
    }

    /**
     * AJAX: Get Ninja Forms
     */
    public function ajax_get_ninja_forms()
    {
        check_ajax_referer('ga4-to-nutshell-nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
            return;
        }

        // Check if Ninja Forms is active
        if (!class_exists('Ninja_Forms')) {
            wp_send_json_error(['message' => 'Ninja Forms plugin is not active']);
            return;
        }

        // Get all forms
        $forms = Ninja_Forms()->form()->get_forms();

        $form_data = [];
        foreach ($forms as $form) {
            $form_data[] = [
                'id' => $form->get_id(),
                'title' => $form->get_setting('title')
            ];
        }

        wp_send_json_success(['forms' => $form_data]);
    }

    /**
     * AJAX: Test Nutshell connection
     */
    public function ajax_test_connection()
    {
        check_ajax_referer('ga4-to-nutshell-nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
            return;
        }

        $username = isset($_POST['username']) ? sanitize_text_field($_POST['username']) : '';
        $api_key = isset($_POST['api_key']) ? sanitize_text_field($_POST['api_key']) : '';

        if (empty($username) || empty($api_key)) {
            wp_send_json_error(['message' => 'Missing API credentials']);
            return;
        }

        // According to Nutshell API documentation, we'll use the "findUsers" method 
        // to test connectivity - this is a safe read-only operation
        $api_url = 'https://app.nutshell.com/api/v1/json';
        $payload = [
            'jsonrpc' => '2.0',
            'method' => 'findUsers',
            'params' => [
                'limit' => 1  // Only request one user to minimize response size
            ],
            'id' => wp_generate_uuid4()
        ];

        $args = [
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Basic ' . base64_encode($username . ':' . $api_key)
            ],
            'body' => json_encode($payload),
            'timeout' => 30,
            'method' => 'POST'
        ];

        $response = wp_remote_request($api_url, $args);

        if (is_wp_error($response)) {
            wp_send_json_error(['message' => $response->get_error_message()]);
            return;
        }

        $response_body = json_decode(wp_remote_retrieve_body($response), true);

        if (isset($response_body['error'])) {
            wp_send_json_error(['message' => $response_body['error']['message']]);
            return;
        }

        // If we got here and have a result, the connection is working
        if (isset($response_body['result'])) {
            wp_send_json_success(['message' => 'Connection successful!']);
            return;
        }

        // If we got here but don't have a result or error, something is wrong
        wp_send_json_error(['message' => 'Invalid response from Nutshell API. Please check your credentials.']);
    }

    public function ajax_get_form_fields()
    {
        check_ajax_referer('ga4-to-nutshell-nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
            return;
        }

        $form_id = isset($_POST['form_id']) ? sanitize_text_field($_POST['form_id']) : '';

        if (empty($form_id)) {
            wp_send_json_error(['message' => 'Missing form ID']);
            return;
        }

        // Check if Ninja Forms is active
        if (!class_exists('Ninja_Forms')) {
            wp_send_json_error(['message' => 'Ninja Forms plugin is not active']);
            return;
        }

        try {
            // Get form fields
            $form = Ninja_Forms()->form($form_id)->get();
            $fields = Ninja_Forms()->form($form_id)->get_fields();

            $field_data = [];
            foreach ($fields as $field) {
                // Skip submit buttons and other non-data fields
                $type = $field->get_setting('type');
                if (in_array($type, ['submit', 'html', 'hr', 'heading', 'divider'])) {
                    continue;
                }

                $field_data[] = [
                    'id' => $field->get_id(),
                    'key' => $field->get_setting('key'),
                    'label' => $field->get_setting('label'),
                    'type' => $type
                ];
            }

            wp_send_json_success(['fields' => $field_data]);
        } catch (Exception $e) {
            wp_send_json_error(['message' => 'Error getting form fields: ' . $e->getMessage()]);
            return;
        }
    }
}

// Initialize plugin
function ga4_to_nutshell_init()
{
    GA4_To_Nutshell::get_instance();
}
add_action('plugins_loaded', 'ga4_to_nutshell_init');
