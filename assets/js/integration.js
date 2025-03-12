/**
 * Frontend JavaScript for GA4 to Nutshell Integration
 * 
 * This script captures GA4 datalayer events specifically the "book_a_demo" event
 * which is triggered by "ninjaFormSubmission" and sends the data to Nutshell CRM.
 */
(function() {
    'use strict';
    
    // Force debug mode on for testing
    const forceDebug = true;
    
    // Store for form mappings
    let formMappings = {};
    
    // Initialize when DOM is fully loaded
    document.addEventListener('DOMContentLoaded', function() {
        log('GA4 to Nutshell script initialized', null, 'info');
        
        // Set up datalayer event capture
        initDataLayerCapture();
        
        // Add direct Ninja Forms handling
        setupNinjaFormListeners();
        
        // Process form mappings
        if (typeof ga4ToNutshellSettings !== 'undefined') {
            log('GA4 to Nutshell settings loaded', ga4ToNutshellSettings);
            
            if (ga4ToNutshellSettings.mappings && ga4ToNutshellSettings.mappings.length) {
                // Convert mappings array to an object for faster lookup
                ga4ToNutshellSettings.mappings.forEach(function(mapping) {
                    formMappings[mapping.form_id] = mapping.user_id;
                });
                
                log('Form mappings initialized', formMappings);
            } else {
                log('No form mappings found in settings', ga4ToNutshellSettings);
            }
        } else {
            log('GA4 to Nutshell settings not found', null, 'error');
        }
        
        // Add manual test button for development (remove in production)
        // if (forceDebug) {
        //     addTestButton();
        // }
    });
    
    /**
     * Add a test button to the page for development
     */
    function addTestButton() {
        const button = document.createElement('button');
        button.textContent = 'Test GA4 to Nutshell';
        button.style.position = 'fixed';
        button.style.bottom = '20px';
        button.style.right = '20px';
        button.style.zIndex = '9999';
        button.style.padding = '10px';
        button.style.backgroundColor = '#0073aa';
        button.style.color = 'white';
        button.style.border = 'none';
        button.style.borderRadius = '4px';
        button.style.cursor = 'pointer';
        
        button.addEventListener('click', function() {
            log('Test button clicked, creating sample event');
            
            // Create a test event
            const testEvent = {
                event: 'book_a_demo',
                formId: Object.keys(formMappings)[0] || '1', // Use first mapping or default
                formName: 'Test Form',
                formData: {
                    name: 'Test User',
                    email: 'test@example.com',
                    phone: '123-456-7890',
                    country: 'Test Country'
                }
            };
            
            log('Dispatching test event', testEvent);
            window.dataLayer.push(testEvent);
        });
        
        document.body.appendChild(button);
    }
    
    /**
     * Initialize the dataLayer event capture
     */
    function initDataLayerCapture() {
        log('Initializing dataLayer event capture');
        
        // Create dataLayer if it doesn't exist
        window.dataLayer = window.dataLayer || [];
        log('Current dataLayer state', window.dataLayer);
        
        // Store the original push method
        const originalPush = window.dataLayer.push;
        
        // Override dataLayer.push to intercept events
        window.dataLayer.push = function() {
            log('dataLayer.push called with arguments', arguments);
            
            // Call the original method with all arguments
            const result = originalPush.apply(window.dataLayer, arguments);
            
            // Process the event
            const event = arguments[0];
            if (event && typeof event === 'object') {
                processDataLayerEvent(event);
            }
            
            return result;
        };
        
        log('DataLayer event capture initialized');
    }
    
    /**
     * Process a dataLayer event
     */
    function processDataLayerEvent(event) {
        // Check if this is a "book_a_demo" event
        if (event.event === 'book_a_demo') {
            log('Detected book_a_demo event', event);
            handleBookDemoEvent(event);
        }
        
        // Also check for ninjaFormSubmission directly
        if (event.event === 'ninjaFormSubmission') {
            log('Detected ninjaFormSubmission event', event);
            handleNinjaFormSubmission(event);
        }
    }
    
    /**
 * Also update the handleBookDemoEvent function to better extract form data
 */
function handleBookDemoEvent(event) {
    log('Handling book_a_demo event', event);
    
    // Extract relevant data
    let formData = {};
    
    if (event.formData && typeof event.formData === 'object') {
        formData = event.formData;
    } else if (event.form_data && typeof event.form_data === 'object') {
        formData = event.form_data;
    } else {
        log('No form data found in event', event, 'warning');
    }
    
    const formId = event.formId || event.form_id || '';
    const formName = event.formName || event.form_name || ('Ninja Form ' + formId);
    
    log('Extracted form information', { formId, formName, formDataKeys: Object.keys(formData) });
    
    // Get traffic source information
    const trafficSource = getTrafficSource();
    const currentUrl = window.location.href;
    const referrerUrl = document.referrer;
    
    // Process the data
    processFormData(formData, formId, formName, trafficSource, referrerUrl, currentUrl);
}
    /**
 * Add a new function to directly capture Ninja Form submissions
 * This is a more direct approach than relying on the dataLayer
 */
function setupNinjaFormListeners() {
    log('Setting up Ninja Forms listeners');
    
    // Check if Ninja Forms is present
    if (typeof Ninja_Forms !== 'undefined') {
        log('Ninja Forms object found, adding submission listener');
        
        // When Ninja Forms is done rendering all forms, add our listener
        $(document).on('nfFormReady', function(e, formData) {
            log('Ninja Forms ready event', formData);
            
            // Listen for form submissions
            $(document).on('nfFormSubmitResponse', function(e, response) {
                log('Ninja Forms submission event', response);
                
                if (response && response.form_id) {
                    const formId = response.form_id;
                    let formName = 'Ninja Form ' + formId;
                    
                    // Extract fields
                    const formData = {};
                    
                    if (response.fields && Array.isArray(response.fields)) {
                        response.fields.forEach(function(field) {
                            if (field.id && field.value !== undefined) {
                                formData[field.id] = field.value;
                            }
                        });
                    }
                    
                    log('Captured Ninja Form submission', { formId, formName, formData });
                    
                    // Create and dispatch book_a_demo event
                    const trafficSource = getTrafficSource();
                    const currentUrl = window.location.href;
                    const referrerUrl = document.referrer;
                    
                    window.dataLayer.push({
                        'event': 'book_a_demo',
                        'formId': formId,
                        'formName': formName,
                        'formData': formData,
                        'trafficSource': trafficSource,
                        'currentUrl': currentUrl,
                        'referrerUrl': referrerUrl
                    });
                }
            });
        });
    } else {
        log('Ninja Forms not found on page', null, 'warning');
    }
}
    /**
     * Handle ninjaFormSubmission event
     */
    function handleNinjaFormSubmission(event) {
        log('Detected ninjaFormSubmission event', event);
        
        // Extract form data - Ninja Forms specific handling
        let formId = '';
        let formName = '';
        let formData = {};
        
        // Check if we have form_id in the event
        if (event.form_id) {
            formId = event.form_id;
            log('Found form_id in event', formId);
        }
        
        // Check if we have form ID directly in the event object
        if (event.formId) {
            formId = event.formId;
            log('Found formId in event', formId);
        }
        
        // Get form name
        if (event.form_name) {
            formName = event.form_name;
        } else if (event.formName) {
            formName = event.formName;
        } else {
            formName = 'Ninja Form ' + formId;
        }
        
        log('Using form name', formName);
        
        // Get form data - check various structures Ninja Forms might use
        if (event.formData && typeof event.formData === 'object') {
            formData = event.formData;
            log('Found formData object in event', formData);
        } else if (event.form_data && typeof event.form_data === 'object') {
            formData = event.form_data;
            log('Found form_data object in event', formData);
        } else if (event.response && typeof event.response === 'object') {
            formData = event.response;
            log('Found response object in event', formData);
        } else if (event.fields && typeof event.fields === 'object') {
            // Convert fields array to key-value pairs
            event.fields.forEach(function(field) {
                if (field.id && field.value !== undefined) {
                    formData[field.id] = field.value;
                }
            });
            log('Extracted form data from fields array', formData);
        } else {
            // Last resort - check all properties of the event for possible field data
            log('No standard form data structure found, checking all event properties', event);
            for (const key in event) {
                if (typeof event[key] === 'object' && event[key] !== null && !Array.isArray(event[key])) {
                    formData = { ...formData, ...event[key] };
                    log('Found potential form data in property', { key, data: event[key] });
                }
            }
        }
        
        // If the form data is still empty, look for any string properties that might be field values
        if (Object.keys(formData).length === 0) {
            log('Form data still empty, checking for string properties', event);
            for (const key in event) {
                if (typeof event[key] === 'string' && 
                    key !== 'event' && 
                    key !== 'form_id' && 
                    key !== 'form_name' && 
                    key !== 'formId' && 
                    key !== 'formName') {
                    formData[key] = event[key];
                    log('Added string property as form field', { key, value: event[key] });
                }
            }
        }
        
        // Get traffic source information
        const trafficSource = getTrafficSource();
        const currentUrl = window.location.href;
        const referrerUrl = document.referrer;
        
        // Create and dispatch book_a_demo event
        log('Creating book_a_demo event with form data', { formId, formName, formData });
        window.dataLayer.push({
            'event': 'book_a_demo',
            'formId': formId,
            'formName': formName,
            'formData': formData,
            'trafficSource': trafficSource,
            'currentUrl': currentUrl,
            'referrerUrl': referrerUrl
        });
    }
    
    /**
     * Process form data and send to Nutshell
     */
    function processFormData(formData, formId, formName, trafficSource, referrerUrl, currentUrl) {
        // Find the assigned user based on form ID
        const assignedUserId = formMappings[formId] || null;
        
        // Prepare data for Nutshell
        const nutshellData = {
            formData: formData,
            formId: formId,
            formName: formName,
            trafficSource: trafficSource,
            referrerUrl: referrerUrl,
            currentUrl: currentUrl,
            assignedUserId: assignedUserId
        };
        
        // Send data to Nutshell via AJAX
        sendToNutshell(nutshellData);
    }
    
    /**
     * Send data to Nutshell via WordPress AJAX
     */
    function sendToNutshell(data) {
        // Create form data
        const formData = new FormData();
        formData.append('action', 'ga4_to_nutshell_process_data');
        formData.append('nonce', ga4ToNutshellSettings.nonce);
        formData.append('data', JSON.stringify(data));
        
        // Send AJAX request
        fetch(ga4ToNutshellSettings.ajaxUrl, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        })
        .then(response => response.json())
        .then(response => {
            if (response.success) {
                log('Successfully sent data to Nutshell', response.data);
            } else {
                log('Error sending data to Nutshell', response.data.message, 'error');
            }
        })
        .catch(error => {
            log('Failed to send data to Nutshell', error.message, 'error');
        });
    }
    
    /**
     * Get the traffic source from GA4 or URL parameters
     */
    function getTrafficSource() {
        // Try to get from GA4 data
        if (typeof gtag !== 'undefined' && typeof gtag.get === 'function') {
            try {
                return gtag.get('traffic_source');
            } catch (e) {
                log('Error getting traffic source from gtag', e.message, 'error');
            }
        }
        
        // Try to get from URL parameters (utm_source)
        const urlParams = new URLSearchParams(window.location.search);
        const utmSource = urlParams.get('utm_source');
        if (utmSource) {
            return utmSource;
        }
        
        // Try to determine from referrer
        if (document.referrer) {
            const referrerUrl = new URL(document.referrer);
            const hostname = referrerUrl.hostname;
            
            // Google
            if (hostname.includes('google')) {
                return 'google';
            }
            
            // Bing
            if (hostname.includes('bing')) {
                return 'bing';
            }
            
            // Facebook
            if (hostname.includes('facebook') || hostname.includes('fb.com')) {
                return 'facebook';
            }
            
            // Twitter/X
            if (hostname.includes('twitter') || hostname.includes('x.com')) {
                return 'twitter';
            }
            
            // LinkedIn
            if (hostname.includes('linkedin')) {
                return 'linkedin';
            }
            
            // Return the hostname as the source
            return hostname;
        }
        
        // If all else fails, return direct
        return 'direct';
    }
    
    /**
     * Logging function - enhanced with forced debug option
     */
    function log(message, data = null, level = 'info') {
        if ((!forceDebug && typeof ga4ToNutshellSettings === 'undefined') || 
            (!forceDebug && !ga4ToNutshellSettings.debug)) {
            return;
        }
        
        const timestamp = new Date().toISOString();
        const prefix = `[GA4-Nutshell ${timestamp}]`;
        
        if (level === 'error') {
            console.error(prefix, message, data);
        } else {
            console.log(prefix, message, data);
        }
    }
})();
