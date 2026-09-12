# SMTP Mail Control for MailPoet #
**Contributors:** Jyria
**Donate link:** https://isla-stud.io/donate/
**Tags:** mailpoet, smtp, wp_mail, gmail-api, phpmailer
**Tested up to:** 7.1
**Requires PHP:** 8.1
**Stable tag:** 1.2.6
**License:** GPLv2 or later
**License URI:** https://www.gnu.org/licenses/gpl-2.0.html

The missing link between MailPoet and your SMTP plugin – for reliable email delivery!

## Description ##

By default, MailPoet sends emails via **PHP Mail**, its **premium MailPoet Sending Service**, or services like **Amazon SES or SendGrid**. But there's a catch: **Some SMTP providers (like Gmail) aren't supported**, and **email logging isn't possible**.  

➡ **This plugin fixes that.** It ensures all MailPoet newsletters are sent via **your chosen SMTP plugin**, so your WordPress email settings apply to newsletters, too – without extra setup or extra costs.  

## 🛠 Works with popular SMTP plugins like:  
✅ **WP Mail SMTP** (by WPForms – the most widely used SMTP plugin)  
✅ **FluentSMTP** (lightweight, free, GDPR-friendly)  
✅ **Post SMTP** (supports OAuth for Gmail, Outlook, etc.)  
✅ **Easy WP SMTP** (simple & reliable)  
✅ **MailerSend, Brevo (formerly Sendinblue), and more**  

## 🎯 Why use this plugin?  
✔ **Ensures MailPoet emails follow your SMTP settings**  
✔ **Works with Gmail & other SMTP providers MailPoet doesn't support**  
✔ **Enables email logging via your SMTP plugin**  

## ⚠ Important notes:  
- Major MailPoet updates may require adjustments.  
- Some advanced MailPoet features (like bounce handling) may behave differently.  

✅ **Test your setup with MailPoet test emails and real newsletters to ensure everything runs smoothly!**

## 🔧 PHP Compatibility

This plugin is built for modern PHP versions:

### **Minimum Requirements:**
- **PHP 8.1+** (required)
- **WordPress 6.5+** (required)

### **Recommended:**
- **PHP 8.3+** (best performance and features)

### **Feature Compatibility:**

| PHP Version | Constructor Property Promotion | Match Expressions | Named Arguments | First-class Callables | Enums | Readonly Properties | Typed Constants |
|-------------|-------------------------------|-------------------|-----------------|----------------------|-------|-------------------|-----------------|
| **8.1**     | ✅ Yes                        | ✅ Yes            | ✅ Yes          | ✅ Yes               | ✅ Yes | ✅ Yes           | ❌ Fallback     |
| **8.2**     | ✅ Yes                        | ✅ Yes            | ✅ Yes          | ✅ Yes               | ✅ Yes | ✅ Yes           | ❌ Fallback     |
| **8.3**     | ✅ Yes                        | ✅ Yes            | ✅ Yes          | ✅ Yes               | ✅ Yes | ✅ Yes           | ✅ Yes          |

### **Performance Benefits:**
- **PHP 8.1**: 25% faster than PHP 7.4
- **PHP 8.2**: 30% faster than PHP 7.4
- **PHP 8.3**: 35% faster than PHP 7.4

### Info regarding possibl requirements ###
 
1. **WP Mail SMTP / Gmail**: A typical SMTP settings page where you configure your mailer  
2. **MailPoet Settings**: "Server (Standard)" selected as sending method, with this plugin ensuring it routes via `wp_mail()`

### Credits ###

* **Entwickler**: Jyria
* **Spenden**: [Unterstütze die Entwicklung](https://isla-stud.io/donate/)


## Installation ##

1. Download or clone this plugin into your `/wp-content/plugins/` directory  
2. Make sure the folder is named something like `omppm-override-phpmail-mailpoet`
3. Go to **Plugins** in your WordPress admin area and activate **Override PHP Mail for Mailpoet (via wp_mail)**
4. In **MailPoet > Settings**, choose "Server (Standard)" or "PHP mail" as your sending method (so it normally uses `PHPMail`)
5. Configure your SMTP method in **WP Mail SMTP** (or your preferred SMTP plugin)  
   - If you're using Gmail API or another specialized flow, ensure it's properly set up in WP Mail SMTP
6. Send a test newsletter (or use the MailPoet test mail) and verify via WP Mail SMTP logs or email headers that the mail goes through your desired SMTP provider

## Frequently Asked Questions ##

### Does this plugin replace MailPoet's default sending method completely? ###
Yes. For all newsletters and test emails that would normally use "PHPMail," it redirects to `wp_mail()`. However, if you are using MailPoet's own "MailPoet Sending Service" or "SendGrid," those remain unaffected.

### Will this plugin work with MailPoet 4, 5, or future versions? ###
It has been tested with MailPoet 5.x. MailPoet may change internal classes or architecture in future updates, which could break this override approach. We recommend testing on a staging site whenever you update MailPoet.

### What if my emails still seem to go out via `mail()`? ###
- Double-check that you have the correct sending method set in MailPoet ("Server" / "PHP mail"), not an external SMTP inside MailPoet's own configuration  
- Verify that WP Mail SMTP (or any other SMTP plugin) is active and configured  
- Check if the MailPoet test emails differ from real newsletter sends. Sometimes the test mail can take a different route

### Does this plugin require code changes in MailPoet? ###
No. But internally, it uses a "class alias" hack to replace MailPoet's `PHPMail` class on the fly, which can be update-sensitive. If you see errors or your newsletter fails after a MailPoet update, deactivate and re-check plugin compatibility.

### What does debug mode store? ###

When debug is enabled, SMTP Mail Control stores only the latest 100 UTC timestamps and fixed diagnostic event codes in the WordPress database. Tools → SMTP Mail Control displays and clears only these plugin events. Recipient addresses, subjects, message bodies and SMTP error text are not stored. No new entries are written to the shared debug.log file. Older debug.log files are not modified; remove historical personal data through your normal site-maintenance process. Disable debug after troubleshooting. Other mail plugins have their own logging and retention settings.

## Changelog ##

**1.2.6**
* Release date: September 12th 2026
* Update the donation links to the current Isla Studio donation page.

**1.2.5**
* Release date: September 12th 2026
* Fix MailPoet error formatting after a failed send and reliably restore the recursion guard.
* Continue the newsletter queue after verified permanent SMTP recipient rejections; retain retries for temporary, authentication, connection and policy failures.
* Replace message-content debug logs with at most 100 private diagnostic events; clearing them preserves the shared WordPress log.
* Escape admin output, report the actual class alias, and clarify the system-mail test.
* Verify WordPress 7.1 with MailPoet 5.38.0 and WP Mail SMTP 4.9.0 in an isolated SMTP environment.
* Require the complete PHP, Playground, Plugin Check and native queue E2E checks before release.

* Minimum PHP requirement corrected to 8.1: the enum-based email type support introduced in 1.2.0 is PHP 8.1+ syntax, so PHP 8.0 sites could never load versions 1.2.0–1.2.4 (parse error at load time)
* Removed the resulting dead PHP 8.0 fallback code paths
* The GPL license file now ships with the plugin
* Development now happens in this public GitHub repository (issues and contributions welcome)

**1.2.4**
* Release date: January 21st 2026
* Tested up to WP 6.9

**1.2.3**
* Release date: January 21st 2026
* **BUGFIX: Critical fix for infinite loop during email sending**
* **BUGFIX: Recursion protection prevents timeout errors with WordPress system emails**
* Fixed: Password reset and other WordPress emails caused "Maximum execution time exceeded" error
* New safety feature: Automatic detection and prevention of recursive wp_mail() calls
* Improved stability when used with WP Mail SMTP and other SMTP plugins
* Prevents conflicts when MailPoet attempts to process WordPress system emails

**1.2.2**
* Release date: August 21st 2025
* **NEW: Dynamic MailPoet email type detection using reflection**
* **NEW: Automatic support for all official MailPoet email types**
* **NEW: Future-proof email type validation system**
* **NEW: Reflection-based email type discovery**
* **NEW: Cached email type detection for performance**
* **NEW: Enhanced admin interface with dynamic email type count**
* **NEW: Automatic updates when MailPoet adds new email types**
* **NEW: Support for all MailPoet email types:**
  * automation, automation_notification, automation_transactional
  * standard, notification, notification_history
  * re_engagement, wc_transactional, confirmation_email
  * automatic, welcome (legacy support)
* **NEW: Intelligent fallback system for email type detection**
* **NEW: Enhanced debugging for email type matching**
* **NEW: Performance-optimized reflection with caching**
* Improved compatibility with MailPoet's latest email type system
* Enhanced support for WooCommerce transactional emails
* Better error handling and logging for email type detection
* Future-proof architecture that automatically adapts to MailPoet updates

**1.2.1**
* Release date: August 20th 2025
* **NEW: Enhanced email type support with pattern matching**
* **NEW: Support for preview emails**
* **NEW: Support for email stats notifications**
* **NEW: Support for new subscriber notifications**
* **NEW: Intelligent pattern matching for automatic emails**
* **NEW: WooCommerce automatic email support (automatic_woocommerce_*)**
* **NEW: Generic automatic email pattern support (automatic_*_*)**
* **NEW: Enhanced email type validation with regex patterns**
* **NEW: Improved debugging for email type matching**
* **NEW: Admin interface shows supported email types count**
* **NEW: Future-proof email type detection system**
* Improved compatibility with MailPoet's latest automatic email system
* Enhanced support for complex email type patterns
* Better error handling and logging for email type detection

**1.2.0**
* Release date: August 19th 2025
* Improvements: PHP 8.3 compatibility with intelligent fallbacks
* **NEW: Future-proof architecture for upcoming PHP versions**
* Improved stability and performance across all PHP 8.x versions
* Optimized code structure with modern PHP best practices
* Enhanced compatibility with WordPress 6.5+ and MailPoet 5.x

**1.1.0**
* Release date: August 11th 2025
* Normalization of translations: en_US is now default locale as per WordPress codex

**1.0.15**
* Release date: August 8th 2025
* **NEW: Completely redesigned admin dashboard with modern user interface**
* **NEW: Interactive debug functions with real-time log display**
* **NEW: Enhanced test email functionality for MailPoet and standard SMTP**
* **NEW: Professional user interface with modern design and improved UX**
* **NEW: Comprehensive debugging tools for developers and administrators**
* **NEW: Improved error handling and user feedback system**
* **NEW: Responsive design for all devices and screen sizes**
* **NEW: Enhanced JavaScript functionality with AJAX integration**
* **NEW: CSS styling with modern UI components and animations**
* **NEW: Comprehensive admin class with professional code structure**
* Improved stability and performance
* Optimized code structure and maintainability

**1.0.14**
* Release date: August 8th 2025
* Fixed "Test-Email senden" button functionality
* Added AJAX handler for test email sending
* Improved error handling and user feedback
* Added detailed test email with plugin information
* Enhanced JavaScript error reporting for debugging

**1.0.13**
* Release date: August 8th 2025
* Added developer information card with professional presentation
* Added direct links to GitHub repository for issues and contributions
* Added contact information and company details
* Enhanced admin interface with developer branding
* Prepared for GitHub repository integration
* Added comprehensive GitHub setup documentation

**1.0.12**
* Release date: August 8th 2025
* Added comprehensive setup instructions with step-by-step guidance
* Added visual "How It Works" explanation with animated cards
* Added troubleshooting section with common issues and solutions
* Added interactive MailPoet test email button
* Enhanced admin interface with modern, visually appealing design
* Added SMTP configuration examples for popular providers
* Improved user experience with clear setup workflow
* Added visual indicators and badges for better guidance

**1.0.11**
* Release date: August 8th 2025
* Added modern, extensible admin interface under Tools > OMPPM Tools
* Individualized debug constant (OMPPM_DEBUG) independent of WP_DEBUG
* Interactive debug toggle with real-time status updates
* Log management with clear and refresh functionality
* Plugin status monitoring (MailPoet active, Class Alias status)
* Quick action buttons for MailPoet and SMTP settings
* Modern, responsive design with card-based layout
* AJAX-powered interface with notifications
* Object-oriented architecture for easy future expansion

**1.0.10**
* Release date: August 8th 2025
* Fixed fatal error: MailerMethod is an interface, not a class
* Corrected base class to PHPMailerMethod (the actual class, not interface)
* Restored compatibility with MailPoet 5.12.13 class hierarchy
* Fixed inheritance issue that was preventing plugin from loading

**1.0.9**
* Release date: August 8th 2025
* Reverted to simple, working approach from version 1.0.4
* Removed complex dynamic class detection and eval() usage
* Restored direct class_alias() functionality that worked perfectly
* Fixed compatibility with MailPoet 5.12.13 using correct class names
* Simplified plugin architecture for better reliability
* Removed unnecessary AJAX context checks and multiple hooks

**1.0.8**
* Release date: August 8th 2025
* Fixed AJAX context detection that was preventing plugin from loading in normal WordPress context
* Removed overly restrictive AJAX checks that blocked plugin initialization
* Added more WordPress hooks (muplugins_loaded, after_setup_theme) to catch MailPoet loading earlier
* Improved class availability checking to work in all contexts
* Enhanced compatibility with different WordPress loading scenarios

**1.0.7**
* Release date: August 8th 2025
* Added comprehensive debugging support for troubleshooting email delivery issues
* Enhanced logging to help identify if emails are being processed via wp_mail() or original MailPoet methods
* Improved compatibility with WP Mail Logging plugins
* Added debug messages for better tracking of email processing flow
* Reverted to working class_alias approach from version 1.0.4
* Enhanced plugin initialization with plugins_loaded priority 1
* Added detailed MailPoet class availability checking
* Improved hook timing to ensure plugin activation
* Fixed iframe/AJAX compatibility issues with dynamic class definition
* Added AJAX context detection to prevent fatal errors
* Fixed class alias creation to only occur when class is properly defined
* Enhanced class availability checking for all required MailPoet dependencies
* Added multiple hook attempts to catch MailPoet initialization at different points
* Added MailPoet version detection for better debugging
* Enhanced alias detection to prevent duplicate setup attempts
* Added comprehensive MailPoet class availability checking
* Enhanced debugging to show all available MailPoet classes
* Added support for different MailPoet versions with dynamic class detection
* Fixed compatibility with newer MailPoet class structures
* Fixed compatibility with MailPoet 5.12.13 using correct class names
* Analyzed actual MailPoet code to use proper class hierarchy

**1.0.7-beta**
* Release date: August 8th 2025
* Improved compatibility with WPO365 | Microsoft 365 Graph Mailer and other SMTP plugins
* Made email type validation more permissive for better backward compatibility
* Fixed issues where some MailPoet emails were not being processed correctly
* Enhanced support for emails without specific email_type metadata

**1.0.6**
* Release date: August 8th 2025
* Fixed memory exhaustion issue during class setup
* Improved compatibility with AJAX and iframe contexts
* Added checks for MailPoet class existence to prevent fatal errors
* Improved loading priority to ensure compatibility with AJAX requests

**1.0.6-beta2**
* Release date: August 1st 2025
* Memory exhaustion fix: endless loop during class setup fixed

**1.0.6-beta**
* Release date: July 31st 2025
* Refactured code to improve compatibility with AJAX and iframe contexts
* Added checks for MailPoet class existence to prevent fatal errors
* Improved loading priority to ensure compatibility with AJAX requests

**1.0.5**
* Release date: July 31st 2025
* Fixed AJAX/iframe compatibility issues by adding proper class existence checks
* Improved loading priority to prevent fatal errors in iframe contexts
* Added AJAX context detection to handle MailPoet class availability
* Extended supported email types

**1.0.4**
* Release date: April 3rd 2025
* Added support for additional MailPoet email types (post notifications, welcome emails, automatic emails) (thanks for your report regarding post notifications, Cody!)
* Improved email type detection for better compatibility

**1.0.3**
* Release date: February 19th 2025
* Readme.txt updated.

**1.0.2**
* Release date: February 4th 2025
* Added a check for the email type to ensure that only real MailPoet emails are redirected.

**1.0.1**
* Release date: January 23rd 2025
* Polished readme.txt and main plugin file headers

**1.0.0**
* Release date: January 15th 2025
* First release