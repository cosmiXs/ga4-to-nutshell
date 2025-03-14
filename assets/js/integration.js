/**
 * Enhanced Frontend JavaScript for GA4 to Nutshell Integration
 *
 * This script captures form submissions and GA4 datalayer events and sends the data to Nutshell CRM.
 * It supports multiple form types and configurable event triggers.
 */
(function () {
    "use strict";

    // Initialize variables
    const debug =
        (typeof ga4ToNutshellSettings !== "undefined" &&
            ga4ToNutshellSettings.debug) ||
        false;
    const formMappings =
        (typeof ga4ToNutshellSettings !== "undefined" &&
            ga4ToNutshellSettings.mappings) ||
        {};

    // Get event triggers from settings
    const eventTriggers = (typeof ga4ToNutshellSettings !== "undefined" &&
        ga4ToNutshellSettings.eventTriggers) || [
            "book_a_demo",
            "nfFormSubmitResponse",
            "form_submission",
            "contact_form_submitted",
        ];

    // Track processed form submissions to prevent duplicates
    const processedForms = new Set();

    // Initialize when DOM is fully loaded
    document.addEventListener("DOMContentLoaded", function () {
        log(
            "GA4 to Nutshell script initialized",
            {
                eventTriggers: eventTriggers,
                debug: debug,
                mappingsCount: Object.keys(formMappings).length,
            },
            "info"
        );

        // Set up various event listeners
        initDataLayerCapture();
        setupFormPluginListeners();
        setupGenericFormListener();
    });

    /**
     * Initialize the dataLayer event capture
     */
    function initDataLayerCapture() {
        log("Initializing dataLayer event capture");

        // Create dataLayer if it doesn't exist
        window.dataLayer = window.dataLayer || [];

        // Store the original push method
        const originalPush = window.dataLayer.push;

        // Override dataLayer.push to intercept events
        window.dataLayer.push = function () {
            // Call the original method with all arguments
            const result = originalPush.apply(window.dataLayer, arguments);

            try {
                // Process the event
                const event = arguments[0];
                if (event && typeof event === "object" && event.event) {
                    log("dataLayer event detected", event, "debug");

                    // Check if this event type is one we should process
                    if (eventTriggers.includes(event.event)) {
                        log("Processing tracked event type: " + event.event, event);
                        processDataLayerEvent(event);
                    }
                }
            } catch (e) {
                log("Error processing dataLayer event", e.message, "error");
            }

            return result;
        };

        log("DataLayer event capture initialized");
    }

    /**
     * Set up listeners for specific form plugins
     */
    function setupFormPluginListeners() {
        log("Setting up form plugin specific listeners");

        // Setup Ninja Forms listeners
        if (typeof Ninja_Forms !== "undefined" || typeof nfRadio !== "undefined") {
            log("Ninja Forms detected, setting up listeners");
            setupNinjaFormsListeners();
        }

        // Setup Contact Form 7 listeners
        if (typeof wpcf7 !== "undefined") {
            log("Contact Form 7 detected, setting up listeners");
            setupContactForm7Listeners();
        }

        // Setup Gravity Forms listeners
        if (
            typeof gform !== "undefined" ||
            document.querySelector(".gform_wrapper")
        ) {
            log("Gravity Forms detected, setting up listeners");
            setupGravityFormsListeners();
        }

        // Setup WPForms listeners
        if (
            typeof wpforms !== "undefined" ||
            document.querySelector(".wpforms-form")
        ) {
            log("WPForms detected, setting up listeners");
            setupWPFormsListeners();
        }

        // Setup Formidable Forms listeners
        if (typeof frm_js !== "undefined" || document.querySelector(".frm_forms")) {
            log("Formidable Forms detected, setting up listeners");
            setupFormidableFormsListeners();
        }
    }

    /**
      * Setup Ninja Forms specific listeners
      * 
      * Prioritizing the nfRadio approach for better stability and to avoid duplication
      */
    function setupNinjaFormsListeners() {
        // Store a flag to avoid double-processing the same submission
        window._ga4ToNutshellProcessedForms = window._ga4ToNutshellProcessedForms || {};

        // The preferred approach - using nfRadio (Ninja Forms 3+)
        if (typeof nfRadio !== "undefined") {
            log("Using nfRadio for Ninja Forms (preferred method)");

            nfRadio.channel("forms").on("submit:response", function (response) {
                log("Ninja Forms submission via nfRadio", {
                    form_id: response?.data?.form_id || 'unknown',
                    field_count: response?.data?.fields?.length || 0
                });

                if (response && response.data && response.data.form_id) {
                    const formId = response.data.form_id;

                    // Create a unique submission ID
                    const submissionId = "ninja_" + formId + "_" + Date.now();

                    // Check if we've already processed this submission
                    if (processedForms.has(submissionId) ||
                        window._ga4ToNutshellProcessedForms[formId]) {
                        log("Ignoring duplicate Ninja Forms submission", submissionId);
                        return;
                    }

                    // Mark as processed
                    processedForms.add(submissionId);
                    window._ga4ToNutshellProcessedForms[formId] = true;

                    // Clean up after 5 seconds
                    setTimeout(() => {
                        processedForms.delete(submissionId);
                        window._ga4ToNutshellProcessedForms[formId] = false;
                    }, 5000);

                    const formData = extractNinjaFormsData(response);
                    const formName = "Ninja Form " + formId;

                    // Create and dispatch event
                    dispatchFormEvent(formId, formName, formData, "ninja_forms");
                }
            });

            // No need for the document event listener if nfRadio is available
            return;
        }

        // Fallback approach - only use if nfRadio is not available
        log("nfRadio not found, using DOM event listener as fallback");

        document.addEventListener("nfFormSubmitResponse", function (event) {
            log("Ninja Forms submission via DOM event (fallback method)", {
                form_id: event?.detail?.response?.data?.form_id || 'unknown'
            });

            if (event.detail && event.detail.response && event.detail.response.data) {
                const formId = event.detail.response.data.form_id;

                // Create a unique submission ID
                const submissionId = "ninja_" + formId + "_" + Date.now();

                // Check if we've already processed this submission
                if (processedForms.has(submissionId) ||
                    window._ga4ToNutshellProcessedForms[formId]) {
                    log("Ignoring duplicate Ninja Forms submission", submissionId);
                    return;
                }

                // Mark as processed
                processedForms.add(submissionId);
                window._ga4ToNutshellProcessedForms[formId] = true;

                // Clean up after 5 seconds
                setTimeout(() => {
                    processedForms.delete(submissionId);
                    window._ga4ToNutshellProcessedForms[formId] = false;
                }, 5000);

                const formData = extractNinjaFormsData(event.detail);
                const formName = "Ninja Form " + formId;

                // Create and dispatch event
                dispatchFormEvent(formId, formName, formData, "ninja_forms");
            }
        });
    }

    /**
     * Setup Contact Form 7 specific listeners
     */
    function setupContactForm7Listeners() {
        // For Contact Form 7, listen for the wpcf7mailsent event
        document.addEventListener("wpcf7mailsent", function (event) {
            log("Contact Form 7 submission detected", event);

            const formId = event.detail.contactFormId;
            const formData = {};

            // Extract form data from inputs array
            if (event.detail.inputs && Array.isArray(event.detail.inputs)) {
                event.detail.inputs.forEach(function (field) {
                    if (field.name && field.value !== undefined) {
                        formData[field.name] = field.value;
                    }
                });
            }

            const formName = "Contact Form " + formId;

            // Create and dispatch event
            dispatchFormEvent(formId, formName, formData, "contact_form_7");
        });
    }

    /**
     * Setup Gravity Forms specific listeners
     */
    function setupGravityFormsListeners() {
        // For Gravity Forms, listen for the gform_confirmation_loaded event
        document.addEventListener("gform_confirmation_loaded", function (event) {
            log("Gravity Forms submission detected", event);

            const formId = event.detail.formId || "";

            // Since we can't get the form data after submission in the confirmation,
            // we need to store it when the form is initially submitted
            if (typeof gformPreSubmit === "function") {
                const originalGformPreSubmit = gformPreSubmit;

                gformPreSubmit = function (formId) {
                    // Store form data before submission
                    const formElement = document.getElementById("gform_" + formId);
                    if (formElement) {
                        const formData = extractFormData(formElement);
                        // Store temporarily
                        window._ga4ToNutshellGravityFormData = formData;
                    }

                    // Call original function
                    return originalGformPreSubmit(formId);
                };
            }

            // Get stored form data if available
            const formData = window._ga4ToNutshellGravityFormData || {};
            const formName = "Gravity Form " + formId;

            // Create and dispatch event
            dispatchFormEvent(formId, formName, formData, "gravity_forms");

            // Clear stored data
            window._ga4ToNutshellGravityFormData = null;
        });
    }

    /**
     * Setup WPForms specific listeners
     */
    function setupWPFormsListeners() {
        // For WPForms, listen for the wpformsAjaxSubmitSuccess event
        document.addEventListener("wpformsAjaxSubmitSuccess", function (event) {
            log("WPForms submission detected", event);

            let formId = "";
            let formData = {};

            // Try to extract form ID from the event detail or target
            if (event.detail && event.detail.formId) {
                formId = event.detail.formId;
            } else if (event.target && event.target.querySelector) {
                const formIdField = event.target.querySelector(
                    'input[name="wpforms[id]"]'
                );
                if (formIdField) {
                    formId = formIdField.value;
                }
            }

            // Extract form data
            if (event.target && formId) {
                formData = extractFormData(event.target);
            }

            const formName = "WPForm " + formId;

            // Create and dispatch event
            dispatchFormEvent(formId, formName, formData, "wpforms");
        });
    }

    /**
     * Setup Formidable Forms specific listeners
     */
    function setupFormidableFormsListeners() {
        // For Formidable Forms, listen for the frm_ajax_complete event
        document.addEventListener("frmFormComplete", function (event) {
            log("Formidable Forms submission detected", event);

            let formId = "";
            let formData = {};

            // Try to extract form ID
            if (event.detail && event.detail.formID) {
                formId = event.detail.formID;
            } else if (event.target && event.target.getAttribute) {
                formId = event.target.getAttribute("data-form") || "";
            }

            // Extract form data
            if (event.target && formId) {
                formData = extractFormData(event.target);
            }

            const formName = "Formidable Form " + formId;

            // Create and dispatch event
            dispatchFormEvent(formId, formName, formData, "formidable");
        });
    }

    /**
     * Setup a generic form submission listener for all other forms
     */
    function setupGenericFormListener() {
        // Only add this if form_submission is in the event triggers
        if (!eventTriggers.includes("form_submission")) {
            return;
        }

        log("Setting up generic form submission listener");

        // Listen for all form submissions
        document.addEventListener(
            "submit",
            function (event) {
                const form = event.target;

                // Skip if this isn't a form element
                if (form.tagName !== "FORM") {
                    return;
                }

                // Skip admin forms and search forms
                if (
                    form.closest("#wpadminbar") ||
                    form.closest(".wp-admin") ||
                    form.classList.contains("search-form") ||
                    form.getAttribute("role") === "search"
                ) {
                    return;
                }

                // Skip if this form has a class that indicates it's handled by a specific plugin
                if (
                    form.classList.contains("ninja-forms-form") ||
                    form.classList.contains("wpcf7-form") ||
                    form.classList.contains("gform_wrapper") ||
                    form.classList.contains("wpforms-form") ||
                    form.classList.contains("frm_forms")
                ) {
                    return;
                }

                log("Generic form submission detected", form);

                // Try to get form ID
                const formId =
                    form.id ||
                    form.getAttribute("data-form-id") ||
                    "generic_" + Date.now();

                // Extract form data
                const formData = extractFormData(form);

                // Get form name
                const formName =
                    form.getAttribute("data-form-name") ||
                    form.getAttribute("name") ||
                    form.id ||
                    "Web Form";

                // Create and dispatch event - mark as generic form
                dispatchFormEvent(formId, formName, formData, "generic");

                // Don't prevent default submission - just capture the data
            },
            true
        ); // Use capture to ensure we get the event before submission
    }

    /**
     * Process a dataLayer event
     */
    function processDataLayerEvent(event) {
        // Check for specific event type handlers
        if (event.event === "book_a_demo") {
            handleBookDemoEvent(event);
        } else if (event.event === "ninjaFormSubmission") {
            handleNinjaFormSubmission(event);
        } else if (event.event === "form_submission") {
            // Generic form submission event
            const formId = event.formId || event.form_id || "";
            const formName = event.formName || event.form_name || "Form " + formId;
            const formData = event.formData || event.form_data || {};
            const formType = event.formType || event.form_type || "generic";

            // Send to Nutshell
            sendToNutshell(formId, formName, formData, formType);
        }
    }

    /**
     * Handle book_a_demo event
     */
    function handleBookDemoEvent(event) {
        log("Handling book_a_demo event", event);

        // Extract form data
        let formData = {};

        if (event.formData && typeof event.formData === "object") {
            formData = event.formData;
        } else if (event.form_data && typeof event.form_data === "object") {
            formData = event.form_data;
        } else {
            log("No form data found in book_a_demo event", event, "warning");
        }

        // Get IDs and names
        const formId = event.formId || event.form_id || "";
        const formType = event.formType || event.form_type || "unknown";
        const formName = event.formName || event.form_name || "Demo Form " + formId;

        // Send to Nutshell
        sendToNutshell(formId, formName, formData, formType);
    }

    /**
     * Handle ninjaFormSubmission event (legacy support)
     */
    function handleNinjaFormSubmission(event) {
        log("Handling ninjaFormSubmission event", event);

        // Extract form data
        let formId = "";
        let formName = "";
        let formData = {};

        // Check for form ID
        if (event.form_id) {
            formId = event.form_id;
        } else if (event.formId) {
            formId = event.formId;
        }

        // Get form name
        if (event.form_name) {
            formName = event.form_name;
        } else if (event.formName) {
            formName = event.formName;
        } else {
            formName = "Ninja Form " + formId;
        }

        // Get form data
        if (event.formData && typeof event.formData === "object") {
            formData = event.formData;
        } else if (event.form_data && typeof event.form_data === "object") {
            formData = event.form_data;
        } else if (event.fields && Array.isArray(event.fields)) {
            // Convert fields array to key-value pairs
            event.fields.forEach(function (field) {
                if (field.id && field.value !== undefined) {
                    formData[field.id] = field.value;
                }
            });
        }

        // Send to Nutshell
        sendToNutshell(formId, formName, formData, "ninja_forms");
    }

    /**
     * Extract data from Ninja Forms response
     */
    function extractNinjaFormsData(response) {
        const formData = {};

        if (
            response &&
            response.response &&
            response.response.data &&
            response.response.data.fields
        ) {
            const fields = response.response.data.fields;

            for (let i = 0; i < fields.length; i++) {
                const field = fields[i];

                // Skip certain field types
                if (["submit", "html", "hr", "divider"].includes(field.type)) {
                    continue;
                }

                // Extract field data
                if (field.id) {
                    formData[field.id] = field.value;

                    if (field.key) {
                        formData[field.key] = field.value;
                    }

                    if (field.label) {
                        formData[field.label] = field.value;
                    }
                }
            }
        }

        return formData;
    }

    /**
     * Extract form data from a form element
     */
    function extractFormData(form) {
        const formData = {};

        if (!form || !form.elements) {
            return formData;
        }

        for (let i = 0; i < form.elements.length; i++) {
            const element = form.elements[i];

            // Skip buttons and hidden fields (except those with important data)
            if (
                (element.type === "submit" || element.type === "button") &&
                !element.name.includes("email") &&
                !element.name.includes("name")
            ) {
                continue;
            }

            // Get element name or id
            const fieldName = element.name || element.id;

            if (fieldName) {
                // Handle different form element types
                if (element.type === "checkbox" || element.type === "radio") {
                    if (element.checked) {
                        formData[fieldName] = element.value;
                    }
                } else if (element.type === "select-multiple") {
                    const selectedValues = [];
                    for (let j = 0; j < element.options.length; j++) {
                        if (element.options[j].selected) {
                            selectedValues.push(element.options[j].value);
                        }
                    }
                    formData[fieldName] = selectedValues.join(", ");
                } else {
                    formData[fieldName] = element.value;
                }

                // Also store by label if possible
                const label = form.querySelector(`label[for="${element.id}"]`);
                if (label && label.textContent) {
                    formData[label.textContent.trim()] = element.value;
                }
            }
        }

        return formData;
    }

    /**
     * Dispatch a form submission event
     */
    function dispatchFormEvent(formId, formName, formData, formType) {
        log("Dispatching form event", {
            formId: formId,
            formName: formName,
            formType: formType,
            fieldCount: Object.keys(formData).length,
        });

        // Get traffic source information
        const trafficSource = getTrafficSource();
        const currentUrl = window.location.href;
        const referrerUrl = document.referrer;

        // First, send data directly to Nutshell
        sendToNutshell(
            formId,
            formName,
            formData,
            formType,
            trafficSource,
            referrerUrl,
            currentUrl
        );

        // Also dispatch an event to the dataLayer for GA4 tracking
        window.dataLayer.push({
            event: "form_submission",
            formId: formId,
            formName: formName,
            formType: formType,
            formData: formData,
            trafficSource: trafficSource,
            referrerUrl: referrerUrl,
            currentUrl: currentUrl,
        });
    }

    /**
     * Send data to Nutshell via AJAX
     */
    function sendToNutshell(
        formId,
        formName,
        formData,
        formType,
        trafficSource,
        referrerUrl,
        currentUrl
    ) {
        // Check if we have the required settings
        if (
            typeof ga4ToNutshellSettings === "undefined" ||
            !ga4ToNutshellSettings.ajaxUrl
        ) {
            log(
                "Missing ga4ToNutshellSettings, cannot send to Nutshell",
                null,
                "error"
            );
            return;
        }

        // Find assigned user based on form ID
        let assignedUserId = "";
        if (formMappings && Array.isArray(formMappings)) {
            for (let i = 0; i < formMappings.length; i++) {
                if (formMappings[i].form_id == formId) {
                    assignedUserId = formMappings[i].user_id;
                    log("Found user mapping for form", {
                        formId: formId,
                        userId: assignedUserId,
                    });
                    break;
                }
            }
        }

        // Set traffic source if not provided
        if (!trafficSource) {
            trafficSource = getTrafficSource();
        }

        // Set URLs if not provided
        if (!currentUrl) {
            currentUrl = window.location.href;
        }

        if (!referrerUrl) {
            referrerUrl = document.referrer;
        }

        // Prepare the data to send
        const dataToSend = {
            formData: formData,
            formId: formId,
            formName: formName,
            formType: formType,
            trafficSource: trafficSource,
            referrerUrl: referrerUrl,
            currentUrl: currentUrl,
            assignedUserId: assignedUserId,
        };

        log("Sending data to Nutshell", dataToSend);

        // Create form data for AJAX request
        const ajaxFormData = new FormData();
        ajaxFormData.append("action", "ga4_to_nutshell_process_data");
        ajaxFormData.append("nonce", ga4ToNutshellSettings.nonce);
        ajaxFormData.append("data", JSON.stringify(dataToSend));

        // Send AJAX request
        fetch(ga4ToNutshellSettings.ajaxUrl, {
            method: "POST",
            body: ajaxFormData,
            credentials: "same-origin",
        })
            .then((response) => response.json())
            .then((response) => {
                if (response.success) {
                    log("Successfully sent data to Nutshell", response.data);
                } else {
                    log("Error sending data to Nutshell", response.data, "error");
                }
            })
            .catch((error) => {
                log("Failed to send data to Nutshell", error.message, "error");
            });
    }

    /**
     * Get the traffic source from GA4 or URL parameters
     * Enhanced to also return medium information
     */
    function getTrafficSource() {
        let source = "direct";
        let medium = "";

        // Try to get medium from URL parameters
        const urlParams = new URLSearchParams(window.location.search);
        const utmMedium = urlParams.get("utm_medium");
        if (utmMedium) {
            medium = utmMedium;
            log("Found utm_medium parameter", medium);
        }

        // Try to get from GA4 data
        if (typeof gtag !== "undefined" && typeof gtag.get === "function") {
            try {
                const gtagSource = gtag.get("traffic_source");
                if (gtagSource) {
                    source = gtagSource;
                    log("Got traffic source from gtag", source);
                }
            } catch (e) {
                log("Error getting traffic source from gtag", e.message, "error");
            }
        }

        // Try to get source from URL parameters (utm_source)
        const utmSource = urlParams.get("utm_source");
        if (utmSource) {
            source = utmSource;
            log("Found utm_source parameter", source);

            // If we have a source but no medium, try to infer medium
            if (!medium) {
                if (
                    ["google", "bing", "yahoo", "duckduckgo"].includes(
                        source.toLowerCase()
                    )
                ) {
                    medium = "organic";
                } else if (
                    [
                        "facebook",
                        "instagram",
                        "twitter",
                        "linkedin",
                        "pinterest",
                    ].includes(source.toLowerCase())
                ) {
                    medium = "social";
                }
            }
        }

        // Try to determine from referrer if no UTM source
        if (!utmSource && document.referrer) {
            try {
                const referrerUrl = new URL(document.referrer);
                const hostname = referrerUrl.hostname;

                // Google
                if (hostname.includes("google")) {
                    source = "google";
                    if (!medium) medium = "organic";
                }

                // Bing
                else if (hostname.includes("bing")) {
                    source = "bing";
                    if (!medium) medium = "organic";
                }

                // Facebook
                else if (hostname.includes("facebook") || hostname.includes("fb.com")) {
                    source = "facebook";
                    if (!medium) medium = "social";
                }

                // Twitter/X
                else if (hostname.includes("twitter") || hostname.includes("x.com")) {
                    source = "twitter";
                    if (!medium) medium = "social";
                }

                // LinkedIn
                else if (hostname.includes("linkedin")) {
                    source = "linkedin";
                    if (!medium) medium = "social";
                }

                // Any other domain is a referral
                else {
                    source = hostname;
                    if (!medium) medium = "referral";
                }

                log("Determined source and medium from referrer", { source, medium });
            } catch (e) {
                log("Error parsing referrer URL", e.message, "warning");
            }
        }

        // If source is direct and no medium, set medium to direct
        if (source === "direct" && !medium) {
            medium = "direct";
        }

        // For backwards compatibility, return both the source string and an object with source and medium
        const result = source;

        // Add medium property to the string (won't affect string usage)
        Object.defineProperty(result, "medium", {
            value: medium,
            enumerable: false,
        });

        log("Final traffic source and medium", { source, medium });
        return result;
    }

    /**
     * Process form data and send to Nutshell
     * Updated to handle medium information
     */
    function processFormData(
        formData,
        formId,
        formName,
        trafficSource,
        referrerUrl,
        currentUrl
    ) {
        // Find the assigned user based on form ID
        const assignedUserId = formMappings[formId] || null;

        // Get medium if available (from the enhanced traffic source)
        const trafficMedium = trafficSource.medium || "";

        // Prepare data for Nutshell
        const nutshellData = {
            formData: formData,
            formId: formId,
            formName: formName,
            trafficSource: trafficSource, // This is still a string for backwards compatibility
            trafficMedium: trafficMedium, // New medium parameter
            referrerUrl: referrerUrl,
            currentUrl: currentUrl,
            assignedUserId: assignedUserId,
        };

        log("Preparing data for Nutshell", nutshellData);

        // Send data to Nutshell via AJAX
        sendToNutshell(nutshellData);
    }
    /**
     * Add a new function to directly capture Ninja Form submissions
     * This is a more direct approach than relying on the dataLayer
     */
    function setupNinjaFormListeners() {
        log("Setting up Ninja Forms listeners");

        // Check if Ninja Forms is present
        if (typeof Ninja_Forms !== "undefined") {
            log("Ninja Forms object found, adding submission listener");

            // When Ninja Forms is done rendering all forms, add our listener
            $(document).on("nfFormReady", function (e, formData) {
                log("Ninja Forms ready event", formData);

                // Listen for form submissions
                $(document).on("nfFormSubmitResponse", function (e, response) {
                    log("Ninja Forms submission event", response);

                    if (response && response.form_id) {
                        const formId = response.form_id;
                        let formName = "Ninja Form " + formId;

                        // Extract fields
                        const formData = {};

                        if (response.fields && Array.isArray(response.fields)) {
                            response.fields.forEach(function (field) {
                                if (field.id && field.value !== undefined) {
                                    formData[field.id] = field.value;
                                }
                            });
                        }

                        log("Captured Ninja Form submission", {
                            formId,
                            formName,
                            formData,
                        });

                        // Get traffic source and medium
                        const trafficSource = getTrafficSource();
                        const trafficMedium = trafficSource.medium || "";
                        const currentUrl = window.location.href;
                        const referrerUrl = document.referrer;

                        // Create and dispatch book_a_demo event
                        window.dataLayer.push({
                            event: "book_a_demo",
                            formId: formId,
                            formName: formName,
                            formData: formData,
                            trafficSource: trafficSource,
                            trafficMedium: trafficMedium, // Added medium
                            currentUrl: currentUrl,
                            referrerUrl: referrerUrl,
                        });
                    }
                });
            });
        } else {
            log("Ninja Forms not found on page", null, "warning");
        }
    }
    /**
     * Logging function
     */
    function log(message, data = null, level = "info") {
        if (!debug && level !== "error") {
            return;
        }

        const timestamp = new Date().toISOString();
        const prefix = `[GA4-Nutshell ${timestamp}]`;

        if (level === "error") {
            console.error(prefix, message, data);
        } else if (level === "warning") {
            console.warn(prefix, message, data);
        } else {
            console.log(prefix, message, data);
        }
    }
})();