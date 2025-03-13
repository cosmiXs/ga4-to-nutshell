<?php

/**
 * Plugin Name: GA4 to Nutshell CRM Integration
 * Plugin URI: https://plusinfinit.com
 * Description: Integrates GA4 datalayer events with Nutshell CRM, focusing on Ninja Forms submissions.
 * Version: 1.0.3
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

        // Get enabled form types
        $enabled_form_types = isset($this->settings['enabled_form_types']) ? $this->settings['enabled_form_types'] : ['ninja_forms'];

        // Add form-specific integrations based on enabled types
        foreach ($enabled_form_types as $form_type) {
            $this->add_form_specific_integrations($form_type);
        }
    }

    /**
     * Add form-specific integrations based on form type
     *
     * @param string $form_type The form type to add integrations for
     */
    private function add_form_specific_integrations($form_type)
    {
        switch ($form_type) {
            case 'ninja_forms':
                if (class_exists('Ninja_Forms')) {
                    add_action('ninja_forms_after_submission', [$this, 'process_ninja_form_submission'], 10, 1);
                    ga4_to_nutshell_log('Added Ninja Forms integration hooks', null, 'info');
                }
                break;

            case 'contact_form_7':
                if (class_exists('WPCF7')) {
                    add_action('wpcf7_mail_sent', [$this, 'process_cf7_submission'], 10, 1);
                    ga4_to_nutshell_log('Added Contact Form 7 integration hooks', null, 'info');
                }
                break;

            case 'gravity_forms':
                if (class_exists('GFForms')) {
                    add_action('gform_after_submission', [$this, 'process_gravity_form_submission'], 10, 2);
                    ga4_to_nutshell_log('Added Gravity Forms integration hooks', null, 'info');
                }
                break;

            case 'wpforms':
                if (class_exists('WPForms')) {
                    add_action('wpforms_process_complete', [$this, 'process_wpforms_submission'], 10, 4);
                    ga4_to_nutshell_log('Added WPForms integration hooks', null, 'info');
                }
                break;

            case 'formidable':
                if (class_exists('FrmForm')) {
                    add_action('frm_after_create_entry', [$this, 'process_formidable_submission'], 30, 2);
                    ga4_to_nutshell_log('Added Formidable Forms integration hooks', null, 'info');
                }
                break;
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
        add_settings_section(
            'ga4_to_nutshell_integrations',
            __('Form Integrations & Triggers', 'ga4-to-nutshell'),
            [$this, 'render_integrations_section_description'],
            'ga4-to-nutshell'
        );

        add_settings_field(
            'enabled_form_types',
            __('Enabled Form Types', 'ga4-to-nutshell'),
            [$this, 'render_form_types_field'],
            'ga4-to-nutshell',
            'ga4_to_nutshell_integrations'
        );

        add_settings_field(
            'event_triggers',
            __('Event Triggers', 'ga4-to-nutshell'),
            [$this, 'render_event_triggers_field'],
            'ga4-to-nutshell',
            'ga4_to_nutshell_integrations'
        );

        add_settings_field(
            'custom_event_trigger',
            __('Custom Event Trigger', 'ga4-to-nutshell'),
            [$this, 'render_text_field'],
            'ga4-to-nutshell',
            'ga4_to_nutshell_integrations',
            [
                'field' => 'custom_event_trigger',
                'description' => __('Add a custom event name that should trigger lead creation (optional)', 'ga4-to-nutshell')
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
        echo '<p>' . __('Select a form to configure field mappings:', 'ga4-to-nutshell') . '</p>';

        echo '<div class="form-selector-container">';
        echo '<select id="form-type-selector" class="widefat">';
        echo '<option value="">' . __('-- Select Form Type --', 'ga4-to-nutshell') . '</option>';

        // Get available form types
        $available_form_types = $this->get_available_form_types();
        foreach ($available_form_types as $type => $data) {
            if ($data['available']) {
                echo '<option value="' . esc_attr($type) . '">' . esc_html($data['label']) . '</option>';
            }
        }

        echo '</select>';

        echo '<select id="form-selector" class="widefat" style="margin-top: 10px;">';
        echo '<option value="">' . __('-- Select a Form --', 'ga4-to-nutshell') . '</option>';
        echo '</select>';
        echo '</div>';

        echo '<div id="field-mapping-content" style="margin-top: 15px;">';
        echo '<p>' . __('Please select a form type and form to see available fields.', 'ga4-to-nutshell') . '</p>';
        echo '</div>';

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
                        <th><?php _e('Form Field', 'ga4-to-nutshell'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($nutshell_fields as $field_key => $field_label): ?>
                    <tr>
                        <td><?php echo esc_html($field_label); ?></td>
                        <td>
                            <select name="ga4_to_nutshell_settings[field_mappings][{{formId}}][<?php echo $field_key; ?>]" class="widefat">
                                <option value=""><?php _e('-- Not Mapped --', 'ga4-to-nutshell'); ?></option>
                                {{#formFields}}
                                <option value="{{id}}" {{#selected}}selected="selected"{{/selected}}>{{label}} ({{type}})</option>
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
        echo '<p>' . __('Loading forms and Nutshell users...', 'ga4-to-nutshell') . '</p>';
        echo '</div>';

        echo '<button type="button" class="button" id="ga4-to-nutshell-add-mapping">' . __('Add Mapping', 'ga4-to-nutshell') . '</button>';

        // Add template for mapping UI
        ?>
        <script type="text/template" id="form-mapping-template">
            <table class="widefat form-mapping-table">
                <thead>
                    <tr>
                        <th><?php _e('Form', 'ga4-to-nutshell'); ?></th>
                        <th><?php _e('Form Type', 'ga4-to-nutshell'); ?></th>
                        <th><?php _e('Nutshell User', 'ga4-to-nutshell'); ?></th>
                        <th><?php _e('Actions', 'ga4-to-nutshell'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    {{#mappings}}
                    <tr>
                        <td>
                            <select name="ga4_to_nutshell_settings[form_user_mappings][{{index}}][form_id]" class="form-select">
                                <option value="">-- <?php _e('Select Form', 'ga4-to-nutshell'); ?> --</option>
                                {{#forms}}
                                <option value="{{id}}" data-form-type="{{type}}" {{#selected}}selected="selected"{{/selected}}>{{title}} ({{plugin}})</option>
                                {{/forms}}
                            </select>
                            <input type="hidden" name="ga4_to_nutshell_settings[form_user_mappings][{{index}}][form_type]" value="{{form_type}}" class="form-type-input">
                        </td>
                        <td>{{form_type_label}}</td>
                        <td>
                            <select name="ga4_to_nutshell_settings[form_user_mappings][{{index}}][user_id]" class="user-select">
                                <option value="">-- <?php _e('Select User', 'ga4-to-nutshell'); ?> --</option>
                                {{#users}}
                                <option value="{{id}}" {{#selected}}selected="selected"{{/selected}}>{{name}}</option>
                                {{/users}}
                            </select>
                        </td>
                        <td>
                            <a href="#" class="ga4-to-nutshell-remove-mapping"><?php _e('Remove', 'ga4-to-nutshell'); ?></a>
                        </td>
                    </tr>
                    {{/mappings}}
                </tbody>
            </table>
        </script>
        <?php
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

            default:  // 'settings' tab or any other
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

    // Add these methods to the GA4_To_Nutshell class:

    /**
     * Render integrations section description
     */
    public function render_integrations_section_description()
    {
        echo '<p>' . __('Configure which form plugins to integrate with and which events should trigger lead creation.', 'ga4-to-nutshell') . '</p>';
    }

    /**
     * Render form types field
     */
    public function render_form_types_field()
    {
        $available_form_types = $this->get_available_form_types();
        $enabled_form_types = isset($this->settings['enabled_form_types']) ? $this->settings['enabled_form_types'] : ['ninja_forms'];

        echo '<div class="form-types-container">';

        foreach ($available_form_types as $type => $label) {
            $checked = in_array($type, $enabled_form_types) ? 'checked' : '';
            $disabled = $type === 'ninja_forms' && !$available_form_types['ninja_forms']['available'] ? 'disabled' : '';

            echo '<div class="form-type-option">';
            echo '<input type="checkbox" id="form_type_' . esc_attr($type) . '" 
                     name="ga4_to_nutshell_settings[enabled_form_types][]" 
                     value="' . esc_attr($type) . '" ' . $checked . ' ' . $disabled . '>';

            echo '<label for="form_type_' . esc_attr($type) . '">' . esc_html($label['label']);

            if (!$label['available']) {
                echo ' <span class="not-installed">(' . __('Not Installed', 'ga4-to-nutshell') . ')</span>';
            }

            echo '</label>';
            echo '</div>';
        }

        echo '</div>';
        echo '<p class="description">' . __('Select which form types to integrate with Nutshell CRM.', 'ga4-to-nutshell') . '</p>';
    }

    /**
     * Render event triggers field
     */
    public function render_event_triggers_field()
    {
        $default_triggers = [
            'book_a_demo' => __('Book a Demo Event', 'ga4-to-nutshell'),
            'nfFormSubmitResponse' => __('Ninja Forms Submission', 'ga4-to-nutshell'),
            'form_submission' => __('Generic Form Submission', 'ga4-to-nutshell'),
            'contact_form_submitted' => __('Contact Form Submission', 'ga4-to-nutshell')
        ];

        $enabled_triggers = isset($this->settings['event_triggers']) ? $this->settings['event_triggers'] : ['book_a_demo', 'nfFormSubmitResponse'];

        echo '<div class="event-triggers-container">';

        foreach ($default_triggers as $trigger => $label) {
            $checked = in_array($trigger, $enabled_triggers) ? 'checked' : '';

            echo '<div class="event-trigger-option">';
            echo '<input type="checkbox" id="event_trigger_' . esc_attr($trigger) . '" 
                     name="ga4_to_nutshell_settings[event_triggers][]" 
                     value="' . esc_attr($trigger) . '" ' . $checked . '>';

            echo '<label for="event_trigger_' . esc_attr($trigger) . '">' . esc_html($label) . '</label>';
            echo '</div>';
        }

        echo '</div>';
        echo '<p class="description">' . __('Select which events should trigger lead creation in Nutshell CRM.', 'ga4-to-nutshell') . '</p>';
    }

    /**
     * Get available form types
     * This detects which form plugins are installed and active
     */
    private function get_available_form_types()
    {
        $form_types = [
            'ninja_forms' => [
                'label' => __('Ninja Forms', 'ga4-to-nutshell'),
                'available' => class_exists('Ninja_Forms'),
                'class' => 'Ninja_Forms'
            ],
            'contact_form_7' => [
                'label' => __('Contact Form 7', 'ga4-to-nutshell'),
                'available' => class_exists('WPCF7'),
                'class' => 'WPCF7'
            ],
            'gravity_forms' => [
                'label' => __('Gravity Forms', 'ga4-to-nutshell'),
                'available' => class_exists('GFForms'),
                'class' => 'GFForms'
            ],
            'wpforms' => [
                'label' => __('WPForms', 'ga4-to-nutshell'),
                'available' => class_exists('WPForms'),
                'class' => 'WPForms'
            ],
            'formidable' => [
                'label' => __('Formidable Forms', 'ga4-to-nutshell'),
                'available' => class_exists('FrmForm'),
                'class' => 'FrmForm'
            ]
        ];

        return $form_types;
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
        // Get event triggers
        $event_triggers = isset($this->settings['event_triggers']) ? $this->settings['event_triggers'] : ['book_a_demo', 'nfFormSubmitResponse'];

        // Add custom event trigger if set
        if (!empty($this->settings['custom_event_trigger'])) {
            $event_triggers[] = $this->settings['custom_event_trigger'];
        }
        // Pass settings to JavaScript
        wp_localize_script('ga4-to-nutshell-integration', 'ga4ToNutshellSettings', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ga4-to-nutshell-frontend-nonce'),
            'mappings' => isset($this->settings['form_user_mappings']) ? $this->settings['form_user_mappings'] : [],
            'debug' => isset($this->settings['debug_mode']) && $this->settings['debug_mode'] ? true : false,
            'eventTriggers' => $event_triggers
        ]);
    }

    // ADD these additional form processing methods:

    /**
     * Process Contact Form 7 submission
     */
    public function process_cf7_submission($contact_form)
    {
        ga4_to_nutshell_log('Contact Form 7 submission received', $contact_form->id());

        // Get form data
        $submission = WPCF7_Submission::get_instance();

        if (!$submission) {
            ga4_to_nutshell_log('No submission instance found', null, 'error');
            return;
        }

        $form_data = $submission->get_posted_data();
        $form_id = $contact_form->id();
        $form_title = $contact_form->title();

        // Get form URL
        $current_url = $submission->get_meta('url');
        $referrer = $submission->get_meta('referer');

        ga4_to_nutshell_log('Extracted CF7 form data', [
            'form_id' => $form_id,
            'form_title' => $form_title,
            'fields' => array_keys($form_data)
        ]);

        // Find assigned user
        $assigned_user_id = '';
        if (isset($this->settings['form_user_mappings']) && is_array($this->settings['form_user_mappings'])) {
            foreach ($this->settings['form_user_mappings'] as $mapping) {
                if ($mapping['form_id'] == $form_id) {
                    $assigned_user_id = $mapping['user_id'];
                    break;
                }
            }
        }

        // Determine traffic source
        $traffic_source = '';
        if (!empty($referrer)) {
            $parsed_url = parse_url($referrer);
            if (isset($parsed_url['host'])) {
                $traffic_source = $parsed_url['host'];
            }
        }

        // Process the submission
        if (function_exists('ga4_to_nutshell_send_to_nutshell')) {
            $result = ga4_to_nutshell_send_to_nutshell(
                $this->settings,
                $form_data,
                $form_title,
                $assigned_user_id,
                $traffic_source,
                $referrer,
                $current_url,
                $form_id
            );

            if (is_wp_error($result)) {
                ga4_to_nutshell_log('Error sending CF7 data to Nutshell', $result->get_error_message(), 'error');
            } else {
                ga4_to_nutshell_log('Successfully sent CF7 data to Nutshell', ['lead_id' => $result]);
            }
        }
    }

    /**
     * Process Gravity Form submission
     */
    public function process_gravity_form_submission($entry, $form)
    {
        ga4_to_nutshell_log('Gravity Form submission received', [
            'form_id' => $form['id'],
            'form_title' => $form['title']
        ]);

        $form_id = $form['id'];
        $form_title = $form['title'];

        // Extract field values
        $form_data = [];

        foreach ($form['fields'] as $field) {
            if (!empty($field['inputs']) && is_array($field['inputs'])) {
                // Handle fields with multiple inputs (like name, address)
                foreach ($field['inputs'] as $input) {
                    $input_id = $input['id'];
                    $input_value = rgar($entry, $input_id);

                    if (!empty($input_value)) {
                        $input_label = $input['label'];
                        $form_data[$input_label] = $input_value;
                        $form_data['input_' . str_replace('.', '_', $input_id)] = $input_value;
                    }
                }
            } else {
                // Standard fields
                $field_id = $field['id'];
                $field_value = rgar($entry, $field_id);

                if (!empty($field_value)) {
                    $field_label = $field['label'];
                    $form_data[$field_label] = $field_value;
                    $form_data['field_' . $field_id] = $field_value;
                }
            }
        }

        // Get URL info
        $current_url = GFFormsModel::get_current_page_url();
        $referrer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';

        // Find assigned user
        $assigned_user_id = '';
        if (isset($this->settings['form_user_mappings']) && is_array($this->settings['form_user_mappings'])) {
            foreach ($this->settings['form_user_mappings'] as $mapping) {
                if ($mapping['form_id'] == $form_id) {
                    $assigned_user_id = $mapping['user_id'];
                    break;
                }
            }
        }

        // Determine traffic source
        $traffic_source = '';
        if (!empty($referrer)) {
            $parsed_url = parse_url($referrer);
            if (isset($parsed_url['host'])) {
                $traffic_source = $parsed_url['host'];
            }
        }

        // Process the submission
        if (function_exists('ga4_to_nutshell_send_to_nutshell')) {
            $result = ga4_to_nutshell_send_to_nutshell(
                $this->settings,
                $form_data,
                $form_title,
                $assigned_user_id,
                $traffic_source,
                $referrer,
                $current_url,
                $form_id
            );

            if (is_wp_error($result)) {
                ga4_to_nutshell_log('Error sending Gravity Form data to Nutshell', $result->get_error_message(), 'error');
            } else {
                ga4_to_nutshell_log('Successfully sent Gravity Form data to Nutshell', ['lead_id' => $result]);
            }
        }
    }

    /**
     * Process WPForms submission
     */
    public function process_wpforms_submission($fields, $entry, $form_data, $entry_id)
    {
        ga4_to_nutshell_log('WPForms submission received', [
            'form_id' => $form_data['id'],
            'form_title' => $form_data['settings']['form_title']
        ]);

        $form_id = $form_data['id'];
        $form_title = $form_data['settings']['form_title'];

        // Format form data
        $formatted_form_data = [];

        foreach ($fields as $field) {
            $field_id = $field['id'];
            $field_label = $field['name'];
            $field_value = $field['value'];

            $formatted_form_data[$field_label] = $field_value;
            $formatted_form_data['field_' . $field_id] = $field_value;
        }

        // Get URL info
        $current_url = isset($_SERVER['HTTP_HOST']) && isset($_SERVER['REQUEST_URI'])
            ? (is_ssl() ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']
            : '';
        $referrer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';

        // Find assigned user
        $assigned_user_id = '';
        if (isset($this->settings['form_user_mappings']) && is_array($this->settings['form_user_mappings'])) {
            foreach ($this->settings['form_user_mappings'] as $mapping) {
                if ($mapping['form_id'] == $form_id) {
                    $assigned_user_id = $mapping['user_id'];
                    break;
                }
            }
        }

        // Determine traffic source
        $traffic_source = '';
        if (!empty($referrer)) {
            $parsed_url = parse_url($referrer);
            if (isset($parsed_url['host'])) {
                $traffic_source = $parsed_url['host'];
            }
        }

        // Process the submission
        if (function_exists('ga4_to_nutshell_send_to_nutshell')) {
            $result = ga4_to_nutshell_send_to_nutshell(
                $this->settings,
                $formatted_form_data,
                $form_title,
                $assigned_user_id,
                $traffic_source,
                $referrer,
                $current_url,
                $form_id
            );

            if (is_wp_error($result)) {
                ga4_to_nutshell_log('Error sending WPForms data to Nutshell', $result->get_error_message(), 'error');
            } else {
                ga4_to_nutshell_log('Successfully sent WPForms data to Nutshell', ['lead_id' => $result]);
            }
        }
    }

    /**
     * Process Formidable Forms submission
     */
    public function process_formidable_submission($entry_id, $form_id)
    {
        ga4_to_nutshell_log('Formidable Forms submission received', [
            'entry_id' => $entry_id,
            'form_id' => $form_id
        ]);

        // Get form information
        $form = FrmForm::getOne($form_id);
        $form_title = $form->name;

        // Get form data
        $entry = FrmEntry::getOne($entry_id, true);
        $form_data = [];

        if (!empty($entry->metas)) {
            foreach ($entry->metas as $field_id => $value) {
                $field = FrmField::getOne($field_id);

                if ($field) {
                    $form_data[$field->name] = $value;
                    $form_data['field_' . $field_id] = $value;
                }
            }
        }

        // Get URL info
        $current_url = isset($_SERVER['HTTP_HOST']) && isset($_SERVER['REQUEST_URI'])
            ? (is_ssl() ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']
            : '';
        $referrer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';

        // Find assigned user
        $assigned_user_id = '';
        if (isset($this->settings['form_user_mappings']) && is_array($this->settings['form_user_mappings'])) {
            foreach ($this->settings['form_user_mappings'] as $mapping) {
                if ($mapping['form_id'] == $form_id) {
                    $assigned_user_id = $mapping['user_id'];
                    break;
                }
            }
        }

        // Determine traffic source
        $traffic_source = '';
        if (!empty($referrer)) {
            $parsed_url = parse_url($referrer);
            if (isset($parsed_url['host'])) {
                $traffic_source = $parsed_url['host'];
            }
        }

        // Process the submission
        if (function_exists('ga4_to_nutshell_send_to_nutshell')) {
            $result = ga4_to_nutshell_send_to_nutshell(
                $this->settings,
                $form_data,
                $form_title,
                $assigned_user_id,
                $traffic_source,
                $referrer,
                $current_url,
                $form_id
            );

            if (is_wp_error($result)) {
                ga4_to_nutshell_log('Error sending Formidable Forms data to Nutshell', $result->get_error_message(), 'error');
            } else {
                ga4_to_nutshell_log('Successfully sent Formidable Forms data to Nutshell', ['lead_id' => $result]);
            }
        }
    }

    /** Process Ninja Form submission (server-side option) */
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
                $extracted_fields[$field_key] = $field_value;  // Store by both ID and key for better matching

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
