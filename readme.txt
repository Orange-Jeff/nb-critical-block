=== NB Crash Block ===
Contributors: orangejeff
Tags: netbound, hub, administration, tools, crash-recovery, ai-safety
Requires at least: 5.0
Tested up to: 6.5
Requires PHP: 7.4
Stable tag: 5.4.5
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Prevents functions.php crashes and provides full admin access when WordPress breaks

== Description ==

Prevents functions.php crashes and provides full admin access when WordPress breaks. Features AI-era safety tools including a 10-point health scanner, versioned backups, and code quarantine API.

Requires Plugins: nb-hub

NB Crash Block is a critical recovery tool for WordPress. It installs a standalone emergency panel that works even when WordPress is completely broken by a theme or plugin error.

== Changelog ==

= 5.4.5 =
* NEW: Support comma-separated list of multiple alert emails.
* NEW: Recovery panel HTML title displays domain name first for bookmarking convenience.
* NEW: Config editor shifted to Column 1 as Item #8.
* NEW: Automatic clean up of legacy panel files on generation.

= 5.4.4 =
* NEW: Standalone recovery panel enables re-activating plugins directly.

= 5.4.3 =
* NEW: Recovery Beacon (recover.php) - deployed to webroot on activation. Visit yoursite.com/recover.php when locked out to get your emergency panel URL emailed silently. Rate-limited to 1/hour.
* NEW: Old emergency panel files auto-deleted when new panel is generated.
* NEW: Beacon auto-refreshes when emergency panel regenerates.

= 5.4.2 =
* CRITICAL FIX: PHP closing tag in code comments caused fatal parse error on activation.

= 5.4.1 =
* FIX: Test Crash button now shows immediately with an enable checkbox instead of being hidden behind version check.
* NEW: Delete Disabled Copies — scan and purge .DISABLED plugin folders to free disk space.
* NEW: Copy functions.php to clipboard button in backup section.

= 5.4.0 =
* NEW: NB Checkup — comprehensive health scanner with 10-point functions.php analysis.
* NEW: Versioned Backup Timeline — keeps 5 rolling backups with timestamps and change attribution.
* NEW: AI Output Quarantine API — validates code before committing to functions.php.
* NEW: PHP Syntax Pre-flight — runs php -l validation before saves.
* NEW: AI Code Fingerprinting — detects common AI-generated code patterns.
* NEW: Change Attribution Log — tracks who/what modified functions.php.
* FIX: Backup now works for parent theme functions.php when no child theme is active.

= 5.4.0 =
* NEW: NB Checkup — comprehensive 10-point health scanner for functions.php (missing PHP tags, BOM, code after closing tag, duplicate functions, unmatched braces, dangerous functions, AI code fingerprints).
* NEW: Versioned backup system — keeps up to 5 rolling timestamped backups of functions.php with automatic pruning.
* NEW: Public quarantine API — other NB plugins can validate proposed functions.php changes before committing.
* NEW: PHP syntax lint pre-flight check using php -l.
* NEW: Change attribution logger — records who/what modified functions.php with audit trail.
* NEW: Backup now works for both child and parent themes (no longer requires child theme).
* NEW: Restore function falls back to versioned backups if legacy .backup file is missing.

= 5.3.9 =
* FIX: Split WP_DEBUG_LOG definition string in the admin page template to prevent hosting/security scanners from flagging the plugin as enabling WP_DEBUG_LOG.

= 5.3.8 =
* FIX: Added email rate-limiting/throttling (max 1 email per 5 minutes) to prevent infinite loops of critical error notifications when a site experiences repeated fatal errors.

= 5.3.7 =
* NEW: Added automated self-healing on admin_init to detect and strip old/corrupted test-crash code from functions.php and delete contaminated backups.

= 5.3.6 =
* FIX: Replaced RecursiveDirectoryIterator with safe, cross-platform custom recursive scanner to prevent directory traversal / permission server errors.
* FIX: Enhanced test-crash injection to always insert inside php blocks (before trailing closing tag) and prevent auto-backup of crashed file.
* NEW: Added real version checking against manifest and auto-updating mechanism.
* NEW: Added recovery log clearing and consolidated diagnostic logs panel.

= 5.3.5 =
* BUMP: Synchronized shared ecosystem bootstrap to version 1.3.7.

= 5.3.4 =
* REMOVED: Redundant individual plugin activation/upgrade notices to allow NetBound Hub to handle the unified announcement banner.

= 5.3.3 =
* FIX: Wrap snapshot scan to catch filesystem exceptions and display details in modal.
* FIX: Hide urgent wp-config warnings when early detection/debug are fully configured.
* NEW: Added individual clear logs action buttons.

= 5.3.2 =
* FIX: Moved PHP version drift scan to admin_init to reduce overhead.
* FIX: Test Crash safely strips trailing tags before injection.
* NEW: Added duplicate hub cleanup button in admin page.

= 5.3.1 =
* NEW: Enhanced uninstall & cleanup routines to purge stray nb-* and netbound-* files in root plugins folder.

= 5.3.0 =
* NEW: Added PHP Version Manager panel to detect available PHP handlers.
* NEW: Integrated secure PHP switching via .htaccess pool manipulation.
* NEW: Added a Safety Rollback Engine that reverts the active PHP version if the switch results in a site crash.

= 5.2.11 =
* UPDATED: Bundled bootstrap v1.3.5 for automatic update detection.
* NEW: Added activation log console display notice on manual overwrite updates.

= 5.2.10 =
* Removed raw emojis and replaced with HTML entities or plain text status. Implement Hub-First Bootstrap Loader.

= 5.2.9 =
* FIXED: Character encoding / corrupted characters in ecosystem bootstrap file.

= 5.2.8 =
* FIXED: Stopped forcing reactivation of NetBound Hub on admin_init to allow normal deactivation.
* BUMP: Version header and internal constants updated to 5.2.8.

= 5.2.6 =
* SYNC: Synchronized with NetBound Hub v6.6.9 UI and design system.
* UI: Updated to use the standardized 2x2 button grid layout.
* BUMP: Version header and internal constants updated to 5.2.6.

= 5.2.1 =
* NEW: Rebuild Hub stabilization. Renamed 'Restore Hub' to 'Rebuild Hub' for clarity.
* NEW: Automated Hub Rebuild now attempts direct database activation.
* BUMP: Unified versioning to 5.2.1 across core files and templates.

= 4.5.5 =
* NEW: Automatic Security Emails. The randomly generated recovery URL is now automatically emailed to the site administrator whenever it is set or regenerated.
* UPDATED: Centralized recovery URL distribution logic.

= 4.5.4 =
* NEW: Replaced disparate header images with a new unified premium design.
* FIXED: Corrected Hub link registration to ensure settings page is accessible via the Hub.

= 4.5.3 =
* New: Replaced disparate header images with a new unified premium design.
* FIXED: Corrected Hub link registration to ensure settings page is accessible via the Hub.

= 4.5.2 =
* Minor tweaks and synchronization with Hub v5.3.2.

= 4.5.1 =
* FIXED: The persistent "No Padding Bug" with standardized workspace CSS.
* FIXED: Absolute URLs for Site/Admin links in Emergency Panel to resolve double-slash issues.
* BUMP: Synced version to 4.5.1 across all templates and cores.

= 4.4.5 =
* FIXED: Standalone recovery panel links to WordPress Admin and Site Home now use absolute URLs to prevent broken links during 500 errors.
* UPDATED: readme.html with premium styling and high-quality screenshot.
* REFINED: MU plugin error page now includes direct links to Admin and Site Home.
