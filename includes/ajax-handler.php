<?php

/**
 * AJAX Handler for GA4 to Nutshell WordPress Plugin
 */

// Add the AJAX handler for frontend data processing
add_action('wp_ajax_ga4_to_nutshell_process_data', 'ga4_to_nutshell_process_data');
add_action('wp_ajax_nopriv_ga4_to_nutshell_process_data', 'ga4_to_nutshell_process_data');

/**
 * Process data from frontend and send to Nutshell
 */
function ga4_to_nutshell_process_data()
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

    // Get settings
    $settings = get_option('ga4_to_nutshell_settings', []);
    ga4_to_nutshell_log('Loaded settings', $settings, 'info');

    // Check for API credentials
    if (empty($settings['nutshell_username']) || empty($settings['nutshell_api_key'])) {
        ga4_to_nutshell_log('Missing Nutshell API credentials', null, 'error');
        wp_send_json_error(['message' => 'Missing Nutshell API credentials']);
        return;
    }

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
    $result = ga4_to_nutshell_send_to_nutshell(
        $settings,
        $form_data,
        $form_name,
        $assigned_user_id,
        $traffic_source,
        $referrer_url,
        $current_url,
        $form_id  // Added form_id parameter
    );

    if (is_wp_error($result)) {
        ga4_to_nutshell_log('Error sending data to Nutshell', $result->get_error_message(), 'error');
        wp_send_json_error(['message' => $result->get_error_message()]);
        return;
    }

    ga4_to_nutshell_log('Data sent to Nutshell successfully', ['lead_id' => $result], 'info');
    wp_send_json_success(['message' => 'Data sent to Nutshell successfully', 'lead_id' => $result]);
}

/**
 * Send data to Nutshell CRM - with enhanced debugging and fixed API format
 */
function ga4_to_nutshell_send_to_nutshell($settings, $form_data, $form_name, $assigned_user_id, $traffic_source, $referrer_url, $current_url, $form_id = '') {
    ga4_to_nutshell_log('Starting send to Nutshell process', [
        'form_id' => $form_id,
        'form_name' => $form_name,
        'assigned_user_id' => $assigned_user_id,
        'traffic_source' => $traffic_source
    ]);
    
    // If form ID is not provided or empty, try to detect it
    if (empty($form_id)) {
        $form_id = ga4_to_nutshell_detect_form_id($form_data, $form_name, $current_url);
    }
    
    // If we found a form ID but no assigned user, try to find it from mappings
    if (!empty($form_id) && empty($assigned_user_id)) {
        if (isset($settings['form_user_mappings']) && is_array($settings['form_user_mappings'])) {
            foreach ($settings['form_user_mappings'] as $mapping) {
                if ($mapping['form_id'] == $form_id) {
                    $assigned_user_id = $mapping['user_id'];
                    ga4_to_nutshell_log('Found user mapping for detected form', [
                        'form_id' => $form_id,
                        'user_id' => $assigned_user_id
                    ]);
                    break;
                }
            }
        }
    }
    
    // Extract contact info from form data using the form ID
    $contact = ga4_to_nutshell_extract_contact_from_form_data($form_data, $form_id);
    if (!$contact || empty($contact['email'])) {
        ga4_to_nutshell_log('No valid contact data found', $form_data, 'error');
        return new WP_Error('invalid_contact', 'No valid contact data found in form submission');
    }
    
    ga4_to_nutshell_log('Extracted contact information', $contact);
    
    // Prepare the API request for Nutshell
    $api_url = 'https://app.nutshell.com/api/v1/json';
    $username = $settings['nutshell_username'];
    $api_key = $settings['nutshell_api_key'];
    
    ga4_to_nutshell_log('Using Nutshell API credentials', [
        'username' => $username,
        'api_url' => $api_url
    ]);
    
    // Find or create contact
    $contact_id = ga4_to_nutshell_find_or_create_contact($api_url, $username, $api_key, $contact);
    if (is_wp_error($contact_id)) {
        ga4_to_nutshell_log('Error with contact creation/lookup', $contact_id->get_error_message(), 'error');
        return $contact_id;
    }
    
    ga4_to_nutshell_log('Successfully got contact ID', ['contact_id' => $contact_id]);
    
    // Create lead with updated structure according to Nutshell API
    $lead = [
        'contacts' => [ // Changed from primaryContact to contacts
            [
                'id' => $contact_id
            ]
        ],
        'note' => "Lead created from " . esc_html($form_name) . " form submission.\n" .
                  "Traffic source: " . esc_html($traffic_source) . "\n" .
                  "Referrer URL: " . esc_html($referrer_url) . "\n" .
                  "Form URL: " . esc_html($current_url),
        'description' => 'Website form lead: ' . esc_html($form_name),
        'sources' => ['Website'],
    ];
    
    // Get contact's country if available
    if (!empty($contact['country'])) {
        $lead['note'] .= "\nCountry: " . esc_html($contact['country']);
    }
    
    // Assign to user if specified
    if (!empty($assigned_user_id)) {
        $lead['assignees'] = [ // Changed from assignedTo to assignees
            [
                'entityType' => 'Users',
                'id' => $assigned_user_id
            ]
        ];
        ga4_to_nutshell_log('Assigning lead to user ID', ['user_id' => $assigned_user_id]);
    } else {
        ga4_to_nutshell_log('No user assigned for this lead', null, 'warning');
    }
    
    $lead_payload = [
        'jsonrpc' => '2.0',
        'method' => 'newLead',
        'params' => [
            'lead' => $lead
        ],
        'id' => wp_generate_uuid4()
    ];
    
    ga4_to_nutshell_log('Creating lead with payload', $lead_payload);
    
    $args = [
        'headers' => [
            'Content-Type' => 'application/json',
            'Authorization' => 'Basic ' . base64_encode($username . ':' . $api_key)
        ],
        'body' => json_encode($lead_payload),
        'timeout' => 30,
        'method' => 'POST'
    ];
    
    $response = wp_remote_request($api_url, $args);
    
    if (is_wp_error($response)) {
        ga4_to_nutshell_log('Error connecting to Nutshell API', $response->get_error_message(), 'error');
        return new WP_Error('api_error', 'Error connecting to Nutshell API: ' . $response->get_error_message());
    }
    
    ga4_to_nutshell_log('Lead creation API response', [
        'code' => wp_remote_retrieve_response_code($response),
        'body' => wp_remote_retrieve_body($response)
    ]);
    
    $response_body = json_decode(wp_remote_retrieve_body($response), true);
    
    if (isset($response_body['error'])) {
        ga4_to_nutshell_log('Nutshell API error when creating lead', $response_body['error'], 'error');
        return new WP_Error('api_error', 'Nutshell API error: ' . $response_body['error']['message']);
    }
    
    $lead_id = $response_body['result']['id'];
    ga4_to_nutshell_log('Successfully created lead in Nutshell', ['lead_id' => $lead_id]);
    return $lead_id;
}

/**
 * Find or create a contact in Nutshell - Fixed to match Nutshell API requirements
 */
function ga4_to_nutshell_find_or_create_contact($api_url, $username, $api_key, $contact)
{
    ga4_to_nutshell_log('Finding or creating contact', $contact);

    // Try to find contact by email
    $find_payload = [
        'jsonrpc' => '2.0',
        'method' => 'searchContacts',
        'params' => [
            'string' => $contact['email'],
            'limit' => 1
        ],
        'id' => wp_generate_uuid4()
    ];

    ga4_to_nutshell_log('Search contact API payload', $find_payload);

    $args = [
        'headers' => [
            'Content-Type' => 'application/json',
            'Authorization' => 'Basic ' . base64_encode($username . ':' . $api_key)
        ],
        'body' => json_encode($find_payload),
        'timeout' => 30,
        'method' => 'POST'
    ];

    $response = wp_remote_request($api_url, $args);

    if (is_wp_error($response)) {
        ga4_to_nutshell_log('Error searching for contact', $response->get_error_message(), 'error');
        return new WP_Error('api_error', 'Error connecting to Nutshell API: ' . $response->get_error_message());
    }

    ga4_to_nutshell_log('Search contact API response', [
        'code' => wp_remote_retrieve_response_code($response),
        'body' => wp_remote_retrieve_body($response)
    ]);

    $response_body = json_decode(wp_remote_retrieve_body($response), true);

    // If contact found, return ID
    if (!empty($response_body['result']) && count($response_body['result']) > 0) {
        $contact_id = $response_body['result'][0]['id'];
        ga4_to_nutshell_log('Found existing contact', ['id' => $contact_id]);
        return $contact_id;
    }

    ga4_to_nutshell_log('No existing contact found, creating new contact');

    // Format the contact data according to Nutshell API docs
    // https://developers.nutshell.com/reference/contacts
    $contact_data = [
        'name' => $contact['name'],
        'emails' => [
            [
                'email' => $contact['email'] // Changed from emailAddress to email
            ]
        ]
    ];

    // Add phone if available
    if (!empty($contact['phone'])) {
        $contact_data['phone_numbers'] = [ // Changed from phone to phone_numbers
            [
                'number' => $contact['phone'] // Changed from phoneNumber to number
            ]
        ];
    }

    // Add address if available
    if (!empty($contact['address'])) {
        $contact_data['address'] = [
            'address_1' => $contact['address']
        ];
    }

    // Add country if available
    if (!empty($contact['country'])) {
        // If we have an address, add country to it
        if (isset($contact_data['address'])) {
            $contact_data['address']['country'] = $contact['country'];
        } else {
            // Otherwise create a new address with just country
            $contact_data['address'] = [
                'country' => $contact['country']
            ];
        }
    }

    // Create new contact
    $create_payload = [
        'jsonrpc' => '2.0',
        'method' => 'newContact',
        'params' => [
            'contact' => $contact_data
        ],
        'id' => wp_generate_uuid4()
    ];

    ga4_to_nutshell_log('Create contact API payload', $create_payload);

    $args = [
        'headers' => [
            'Content-Type' => 'application/json',
            'Authorization' => 'Basic ' . base64_encode($username . ':' . $api_key)
        ],
        'body' => json_encode($create_payload),
        'timeout' => 30,
        'method' => 'POST'
    ];

    $response = wp_remote_request($api_url, $args);

    if (is_wp_error($response)) {
        ga4_to_nutshell_log('Error creating contact', $response->get_error_message(), 'error');
        return new WP_Error('api_error', 'Error connecting to Nutshell API: ' . $response->get_error_message());
    }

    ga4_to_nutshell_log('Create contact API response', [
        'code' => wp_remote_retrieve_response_code($response),
        'body' => wp_remote_retrieve_body($response)
    ]);

    $response_body = json_decode(wp_remote_retrieve_body($response), true);

    if (isset($response_body['error'])) {
        ga4_to_nutshell_log('Nutshell API error when creating contact', $response_body['error'], 'error');
        return new WP_Error('api_error', 'Nutshell API error: ' . $response_body['error']['message']);
    }

    $new_contact_id = $response_body['result']['id'];
    ga4_to_nutshell_log('Successfully created new contact', ['id' => $new_contact_id]);
    return $new_contact_id;
}

/**
 * Extract contact info from form data using field mappings
 * Completely rewritten to handle Ninja Forms field IDs better
 */
function ga4_to_nutshell_extract_contact_from_form_data($form_data, $form_id = '')
{
    ga4_to_nutshell_log('Extracting contact info from form data', [
        'form_data_keys' => array_keys($form_data),
        'form_id' => $form_id
    ]);

    $settings = get_option('ga4_to_nutshell_settings', []);
    $contact = [
        'name' => '',
        'email' => '',
        'phone' => '',
        'company' => '',
        'address' => '',
        'country' => ''
    ];

    // Log the available form data keys for debugging
    ga4_to_nutshell_log('Available form data keys', array_keys($form_data));

    // If we have field mappings for this form, use them
    if (!empty($form_id) && isset($settings['field_mappings'][$form_id])) {
        $mappings = $settings['field_mappings'][$form_id];
        ga4_to_nutshell_log('Using field mappings for form', $mappings);

        foreach ($mappings as $nutshell_field => $ninja_field_id) {
            if (empty($ninja_field_id)) {
                continue; // Skip empty mappings
            }

            ga4_to_nutshell_log('Looking for mapped field', [
                'nutshell_field' => $nutshell_field,
                'ninja_field_id' => $ninja_field_id
            ]);

            // First try direct match by field ID
            if (isset($form_data[$ninja_field_id])) {
                $contact[$nutshell_field] = sanitize_text_field($form_data[$ninja_field_id]);
                ga4_to_nutshell_log("Mapped {$nutshell_field} to value using direct ID match", [
                    'field_id' => $ninja_field_id,
                    'value' => $contact[$nutshell_field]
                ]);
                continue;
            }

            // If direct match fails, try to find a field key or ID that contains the mapped field ID
            foreach ($form_data as $field_key => $field_value) {
                // Skip empty values
                if (empty($field_value)) {
                    continue;
                }

                // Check if the field key contains the ID or ends with the ID
                if (
                    strpos($field_key, $ninja_field_id) !== false ||
                    substr($field_key, -strlen($ninja_field_id)) === $ninja_field_id
                ) {
                    $contact[$nutshell_field] = sanitize_text_field($field_value);
                    ga4_to_nutshell_log("Mapped {$nutshell_field} to value using partial ID match", [
                        'field_key' => $field_key,
                        'field_id' => $ninja_field_id,
                        'value' => $contact[$nutshell_field]
                    ]);
                    break;
                }
            }
        }
    } else {
        ga4_to_nutshell_log('No field mappings found for form ID: ' . $form_id, null, 'warning');
        ga4_to_nutshell_log('Using field pattern detection method', null, 'info');

        // Pattern detection method
        // Common field patterns for each contact field
        $field_patterns = [
            'email' => ['email', 'e-mail', 'mail', 'email_address'],
            'name' => ['name', 'full_name', 'fullname', 'customer_name', 'client_name'],
            'phone' => ['phone', 'telephone', 'tel', 'mobile', 'phone_number'],
            'company' => ['company', 'business', 'organization', 'organisation', 'company_name'],
            'country' => ['country', 'nation', 'location', 'region'],
            'address' => ['address', 'street', 'location', 'postal_address']
        ];

        // Look for field keys that match our patterns
        foreach ($field_patterns as $contact_field => $patterns) {
            foreach ($form_data as $field_key => $field_value) {
                // Skip empty values
                if (empty($field_value)) {
                    continue;
                }

                // Check if any pattern is in the field key (case insensitive)
                $field_key_lower = strtolower($field_key);
                foreach ($patterns as $pattern) {
                    if (strpos($field_key_lower, $pattern) !== false) {
                        $contact[$contact_field] = sanitize_text_field($field_value);
                        ga4_to_nutshell_log("Detected {$contact_field} field using pattern match", [
                            'field_key' => $field_key,
                            'pattern' => $pattern,
                            'value' => $contact[$contact_field]
                        ]);
                        break 2; // Break out of both loops
                    }
                }
            }
        }

        // Email is special - make sure it's a valid email
        if (!empty($contact['email']) && !is_email($contact['email'])) {
            ga4_to_nutshell_log('Found email field but value is not a valid email', [
                'value' => $contact['email']
            ], 'warning');
            $contact['email'] = '';
        }

        // If we couldn't find the email field, look for any field value that looks like an email
        if (empty($contact['email'])) {
            foreach ($form_data as $field_key => $field_value) {
                if (is_email($field_value)) {
                    $contact['email'] = sanitize_email($field_value);
                    ga4_to_nutshell_log('Found email value in field', [
                        'field_key' => $field_key,
                        'email' => $contact['email']
                    ]);
                    break;
                }
            }
        }
    }

    // Log the extracted contact info
    ga4_to_nutshell_log('Contact info extraction results', $contact);

    // If no email found, return empty (but log all field data for debugging)
    if (empty($contact['email'])) {
        ga4_to_nutshell_log('No email found in form data', $form_data, 'warning');
        return null;
    }

    // If no name found, use email as name
    if (empty($contact['name'])) {
        $contact['name'] = $contact['email'];
        ga4_to_nutshell_log('No name found, using email as name', $contact['name']);
    }

    ga4_to_nutshell_log('Successfully extracted contact info', $contact);
    return $contact;
}
/**
 * Attempt to detect the form ID from form data or URL
 */
function ga4_to_nutshell_detect_form_id($form_data, $form_name = '', $current_url = '')
{
    ga4_to_nutshell_log('Attempting to detect form ID', [
        'form_name' => $form_name,
        'current_url' => $current_url
    ]);

    // Check if form ID is in the form data
    foreach ($form_data as $key => $value) {
        if (($key === 'form_id' || $key === 'formId') && !empty($value)) {
            ga4_to_nutshell_log('Found form ID in form data', ['form_id' => $value]);
            return $value;
        }
    }

    // Get settings to check available form mappings
    $settings = get_option('ga4_to_nutshell_settings', []);
    $form_mappings = isset($settings['form_user_mappings']) ? $settings['form_user_mappings'] : [];

    // If we have a form name, try to match it to known forms
    if (!empty($form_name) && class_exists('Ninja_Forms')) {
        $forms = Ninja_Forms()->form()->get_forms();

        foreach ($forms as $form) {
            $title = $form->get_setting('title');
            $id = $form->get_id();

            if (
                strtolower($title) === strtolower($form_name) ||
                strpos(strtolower($title), strtolower($form_name)) !== false
            ) {
                ga4_to_nutshell_log('Matched form by name', [
                    'form_name' => $form_name,
                    'matched_form' => $title,
                    'form_id' => $id
                ]);
                return $id;
            }
        }
    }

    // Try to detect from URL path
    if (!empty($current_url)) {
        $path = parse_url($current_url, PHP_URL_PATH);

        // Map known URLs to form IDs
        $url_mappings = [
            '/see-how-it-works/' => '17', // Example mapping
            '/contact/' => '17',  // Example mapping
            '/request-demo/' => '17',  // Example mapping
        ];

        foreach ($url_mappings as $url_path => $form_id) {
            if (strpos($path, $url_path) !== false) {
                ga4_to_nutshell_log('Matched form by URL path', [
                    'url_path' => $url_path,
                    'form_id' => $form_id
                ]);
                return $form_id;
            }
        }
    }

    // If we have only one form mapping, assume it's that form
    if (count($form_mappings) === 1) {
        $form_id = $form_mappings[0]['form_id'];
        ga4_to_nutshell_log('Using the only available form mapping', ['form_id' => $form_id]);
        return $form_id;
    }

    ga4_to_nutshell_log('Could not detect form ID', null, 'warning');
    return '';
}
