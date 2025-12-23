=== NB Critical Block ===
Contributors: OrangeJeff
Tags: functions, safe mode, recovery, child theme, fatal error, white screen of death, wsod, maintenance mode, emergency recovery, mu-plugins
Requires at least: 5.8
Tested up to: 6.7
Stable tag: 2.14.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A comprehensive fail-safe and recovery toolkit for WordPress. Prevents the "White Screen of Death" by catching fatal errors early and auto-restoring safe backups.

== Description ==

**Never get locked out by the WordPress "critical error" screen again!**

NB Critical Block provides multiple layers of protection that actually work. Unlike other recovery plugins that load too late to help, this plugin includes **early error handlers** that catch fatal errors BEFORE WordPress fully loads - so they can actually disable the crashing plugin and keep your site running.

**NEW in 2.13.0:** Recovery script now uses your WordPress admin login - same URL on all sites (`yoursite.com/nb-recovery.php`), no separate passwords to remember!

Part of the **NetBound Tools** suite.

= Protection Layers (All One-Click Install Except One) =

**Layer 1: Main Plugin** (Active when you install this plugin)
* Catches errors after WordPress loads
* Auto-disables plugins that cause fatal errors
* Backs up and restores functions.php on crash
* Maintenance mode control with bypass URL

**Layer 2: Must-Use Plugin** (One-Click Install from Settings)
* Installs with a single button click - no FTP needed
* Loads BEFORE regular plugins
* Catches fatal errors in other plugins before they crash the site
* Shows a friendly recovery page instead of the dreaded "critical error" screen
* Provides emergency bypass URL (`?netbound_emergency=1`)

**Layer 3: wp-config.php Snippet** (Optional - Requires FTP/File Manager)
* The ONLY component requiring manual installation (for security)
* Catches errors before ANYTHING else loads
* Easy Copy/Download buttons provided in admin
* Ultimate protection - even catches mu-plugin errors

= Key Features =

**Automatic Plugin Auto-Disable**
* When a plugin causes a fatal error, it gets automatically renamed/disabled.
* Your site keeps running on the remaining plugins.
* View and re-enable disabled plugins from the admin dashboard.
* Email notifications tell you exactly what happened.

**functions.php Fail-Safe (Child Themes)**
* Automatically creates a backup of your theme's `functions.php`.
* Detects fatal PHP errors in real-time.
* Instantly restores the safe backup when a fatal error is detected.
* Saves the faulty file for debugging (renamed with timestamp).

**Maintenance Mode Control**
* Toggle maintenance mode on/off from admin.
* Secret bypass URL so you can access your site while in maintenance.
* Detects "stuck" maintenance mode files (`.maintenance`) from failed updates.

**Standalone Emergency Recovery Script**
* Password-protected recovery script works even when WordPress is completely broken.
* Generates with a random, secure filename.
* Can disable all plugins, remove stuck maintenance files.
* Allows emergency `functions.php` uploads.

**Child Theme Creator**
* One-click child theme generator.
* Enables the fail-safe features instantly.

== Installation ==

1. Upload the `nb-critical-block` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu
3. Go to **NetBound Tools > Critical Block**
4. Click **"Install Must-Use Plugin"** button for early error protection
5. (Optional) Set a recovery script password for emergency access
6. (Optional) Add the `wp-config.php` snippet via FTP for ultimate protection

== Frequently Asked Questions ==

= What's the difference between the protection layers? =
* **Main Plugin**: Catches errors after WordPress loads. Good but can't prevent the critical error screen.
* **Must-Use Plugin**: Catches errors BEFORE regular plugins load. One-click install. Can prevent most critical error screens.
* **wp-config.php Snippet**: Catches errors before ANYTHING loads. Requires FTP access. Maximum protection.

= Why can't the wp-config.php snippet be installed automatically? =
Security. Automatically modifying `wp-config.php` could be exploited by malicious code. It's safer to require manual interaction (FTP or File Manager) for this one core component. We provide easy Copy/Download buttons to help.

= Why does functions.php protection only work with child themes? =
Modifying a parent theme's `functions.php` directly is risky - changes get wiped out when the theme updates. A child theme is the proper way to customize themes, and our plugin includes a one-click Child Theme Creator.

= How do I use the recovery script? =
After setting a password in Settings > NetBound Tools, you'll get a secret URL. Save this URL. If your site crashes completely, open that URL in your browser to access recovery tools.

== Screenshots ==

1. **Dashboard Overview:** See protection status, maintenance mode, and logs in one place.
2. **Early Error Handler:** One-click install for the Must-Use plugin and snippet tools for wp-config.
3. **Recovery Script:** The standalone emergency tool that works even when WordPress is dead.

== Changelog ==

= 2.11.1 =
*   Renamed to "NB Critical Block".
*   Integrated with NetBound Shared Menu System v2.1.0.
*   Added Copy/Download buttons for `wp-config.php` snippet.
*   Formatting improvements for disabled plugin error messages.

= 2.14.0 =
*   NEW: Debug mode toggle in recovery script - enable/disable WP_DEBUG remotely
*   NEW: Error log viewer - read debug.log and NetBound logs without FTP
*   NEW: One-click wp-config.php snippet installer from recovery script
*   NEW: Clear individual log files from recovery interface

= 2.13.0 =
*   NEW: Recovery script now uses WordPress admin credentials - no separate password!
*   NEW: Fixed URL for all sites: yoursite.com/nb-recovery.php
*   NEW: Auto-installs on plugin activation
*   IMPROVED: Login form shows username + password fields (same as wp-admin)

= 2.12.0 =
*   NEW: MU-Plugin error protection - catches fatal errors in must-use plugins.
*   NEW: Full-featured emergency recovery script with file browser.
*   NEW: Recovery script can disable/enable/delete mu-plugins, plugins, and themes.
*   NEW: Admin UI section for disabled MU-plugins.
*   NEW: Updated wp-config.php snippet with mu-plugin protection.
*   IMPROVED: Recovery script now has login security with lockout after failed attempts.

= 2.9.0 =
*   NEW: Must-Use Plugin - one-click install from admin.
*   NEW: wp-config.php snippet - optional manual install for ultimate protection.
*   NEW: Friendly recovery page instead of critical error screen.
*   NEW: Emergency bypass URL (?netbound_emergency=1).
*   NEW: Error log viewer in admin.
*   NEW: Protection status summary dashboard.

= 2.8.0 =
*   Plugin auto-disable on fatal error.
*   Maintenance mode control with bypass URL.
*   Email notifications for all actions.

= 1.0.0 =
*   Initial release.
*   Automatic functions.php fail-safe.
*   Password-protected emergency recovery script.
*   One-click child theme creator.
