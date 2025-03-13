=== GA4 to Nutshell CRM Integration ===
Contributors: cosmixs
Tags: nutshell, crm, ninja forms, contact form 7, gravity forms, wpforms, formidable forms, ga4, analytics, lead generation
Requires at least: 5.0
Tested up to: 6.5
Stable tag: 1.0.3
Requires PHP: 7.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Integrate GA4 datalayer events with Nutshell CRM, supporting multiple form plugins for lead generation.

== Description ==

The GA4 to Nutshell CRM Integration plugin creates a seamless connection between your WordPress website's form submissions and your Nutshell CRM. It captures form submission events from GA4 datalayer and various form plugins, sending the data to Nutshell CRM to automatically create contacts, companies, and leads.

= Key Features =

* Support for multiple form plugins: Ninja Forms, Contact Form 7, Gravity Forms, WPForms, and Formidable Forms
* Configurable event triggers to control when leads are created
* Automatically create or update contacts in Nutshell CRM
* Automatically create or update companies/accounts in Nutshell CRM
* Create leads with detailed information including traffic source and referrer URL
* Assign leads to specific Nutshell users based on form ID
* Support for multiple forms with different user assignments
* Enhanced field detection and mapping
* Robust error handling and logging

= Requirements =

* WordPress 5.0 or higher
* At least one supported form plugin (Ninja Forms, Contact Form 7, Gravity Forms, WPForms, or Formidable Forms)
* Google Analytics 4 set up on your website (optional for datalayer event capture)
* Nutshell CRM account with API access

== Installation ==

1. Upload the `ga4-to-nutshell` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to Settings > GA4 to Nutshell to configure your Nutshell API credentials
4. Select which form plugins you want to enable integration with
5. Configure which events should trigger lead creation
6. Map your forms to specific Nutshell users for lead assignment
7. Map form fields to Nutshell contact fields for accurate data transfer

== Frequently Asked Questions ==

= Which form plugins are supported? =

The plugin now supports:
* Ninja Forms
* Contact Form 7
* Gravity Forms
* WPForms
* Formidable Forms
* Generic HTML forms

= How does the plugin capture form submissions? =

The plugin uses multiple methods to capture form submissions:
1. Direct integration with form plugin events and hooks
2. Monitoring GA4 datalayer events
3. Listening for configurable custom events
4. A fallback generic form submission listener

= What data is sent to Nutshell CRM? =

The plugin sends the following data to Nutshell CRM:
* Contact information (name, email, phone, etc.)
* Company information (when available)
* Traffic source information
* Referrer URL
* Form submission URL
* Form name
* All form field values (as a note in the lead)

= Can I map different forms to different Nutshell users? =

Yes, the plugin allows you to map each form to a specific Nutshell user. This way, leads from different forms can be assigned to different team members in your Nutshell CRM.

= Do I need to modify my GA4 setup? =

While the plugin works best with GA4 datalayer events, it's not required. The plugin can capture form submissions directly through form plugin integrations. If you want to use GA4 events, you can configure which events trigger lead creation in the plugin settings.

= How can I test if the integration is working? =

The plugin includes a "Test Connection" button in the settings page that verifies your Nutshell API credentials. For full testing, submit a form on your website and check if the contact, company, and lead appear in your Nutshell CRM. You can also view the debug logs in the plugin's "Debug Logs" tab.

= What happens if there's an error during submission? =

The plugin includes comprehensive error handling and logging. If an error occurs, it will be logged in the debug logs with detailed information and recovery suggestions. Administrators can view these logs in the plugin's "Debug Logs" tab.

= Can I customize which events trigger lead creation? =

Yes, you can select which events should trigger lead creation in the plugin settings. You can also add custom event names to listen for.

== Screenshots ==

1. Plugin settings page
2. Form type and event trigger configuration
3. Form to user mapping interface
4. Field mapping configuration
5. Debug logs and error information

== Changelog ==

= 1.0.2 =
* Added support for multiple form plugins (Contact Form 7, Gravity Forms, WPForms, Formidable Forms)
* Added company/account creation and detection
* Enhanced contact creation with better field mapping
* Added configurable event triggers
* Improved error handling with recovery suggestions
* Enhanced logging with different log levels
* Fixed issues with lead-contact-account relationships
* Improved field detection across different form types
* Added form type detection

= 1.0.1 =
* Fixed issues with contact data extraction
* Improved Ninja Forms integration
* Added debugging tools

= 1.0.0 =
* Initial release

== Upgrade Notice ==

= 1.0.2 =
Major update with support for multiple form plugins, improved API integration, and better error handling. This version significantly enhances lead creation reliability.

= 1.0.1 =
This version fixes important issues with contact data extraction and improves Ninja Forms integration.

= 1.0.0 =
Initial release of the GA4 to Nutshell CRM Integration plugin.

== Development Roadmap ==

= Upcoming Features =

* Custom field mapping for Nutshell custom fields
* Advanced lead assignment rules
* Duplicate lead prevention
* Data transformation options
* Performance optimizations
* Webhook support
* Integration with more form plugins
* Batch processing for high-volume sites

= Current Development Focus =

* Improving API integration reliability
* Enhancing form data extraction
* Expanding form plugin support

== Credits ==

This plugin was developed by cosmixs at plusinfinit.com and is not officially affiliated with Nutshell CRM.