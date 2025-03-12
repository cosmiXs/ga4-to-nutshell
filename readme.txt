=== GA4 to Nutshell CRM Integration ===
Contributors: cosmixs
Tags: nutshell, crm, ninja forms, ga4, analytics, lead generation
Requires at least: 5.0
Tested up to: 6.5
Stable tag: 1.0.0
Requires PHP: 7.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Integrate GA4 datalayer events with Nutshell CRM, focusing on Ninja Forms submissions for lead generation.

== Description ==

The GA4 to Nutshell CRM Integration plugin creates a seamless connection between your WordPress website's form submissions and your Nutshell CRM. It captures "book_a_demo" events from the GA4 datalayer (triggered by Ninja Forms submissions) and sends the data to Nutshell CRM, creating contacts and leads automatically.

= Key Features =

* Capture form submissions from Ninja Forms via GA4 datalayer events
* Automatically create or update contacts in Nutshell CRM
* Create leads with detailed information including traffic source and referrer URL
* Assign leads to specific Nutshell users based on form ID
* Support for multiple forms with different user assignments

= Requirements =

* WordPress 5.0 or higher
* Ninja Forms plugin
* Google Analytics 4 set up on your website
* Nutshell CRM account with API access

== Installation ==

1. Upload the `ga4-to-nutshell` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to Settings > GA4 to Nutshell to configure your Nutshell API credentials
4. Map your Ninja Forms to specific Nutshell users for lead assignment

== Frequently Asked Questions ==

= How does the plugin capture form submissions? =

The plugin captures events from the GA4 datalayer, specifically looking for the "book_a_demo" event which is triggered by Ninja Form submissions. It extracts the form data, identifies the form, and sends the information to Nutshell CRM.

= What data is sent to Nutshell CRM? =

The plugin sends the following data to Nutshell CRM:
* Form field data (name, email, phone, country, etc.)
* Traffic source (organic, cpc, direct, referral)
* Referrer URL
* Form submission URL
* Form name

= Can I map different forms to different Nutshell users? =

Yes, the plugin allows you to map each Ninja Form to a specific Nutshell user. This way, leads from different forms can be assigned to different team members in your Nutshell CRM.

= Do I need to modify my GA4 setup? =

The plugin expects your GA4 implementation to trigger a "book_a_demo" event when a Ninja Form is submitted. If this event is not already set up, you may need to configure your Google Tag Manager or GA4 implementation to trigger this event.

= How can I test if the integration is working? =

The plugin includes a "Test Connection" button in the settings page that verifies your Nutshell API credentials. For full testing, submit a form on your website and check if the contact and lead appear in your Nutshell CRM.

== Screenshots ==

1. Plugin settings page
2. Form to user mapping interface
3. Successful connection to Nutshell CRM

== Changelog ==

= 1.0.0 =
* Initial release

== Upgrade Notice ==

= 1.0.0 =
Initial release of the GA4 to Nutshell CRM Integration plugin.
