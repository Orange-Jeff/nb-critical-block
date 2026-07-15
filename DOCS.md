# NB Crash Block Documentation

> **Plugin Slug:** `nb-crash-block`
> **Current Version:** `5.4.0`
> **Last Updated:** `2026-06-09`
> **Requirement:** NetBound Hub must be active.

## 1. Overview
**NB Crash Block** is an essential fail-safe engineering tool for WordPress. It is designed to prevent "White Screen of Death" (WSOD) scenarios by intercepting fatal PHP errors before they stop the site from loading. It provides developers and administrators with a secure, standalone recovery environment that remains operational even when the core WordPress installation is compromised.

In v5.4.0, Crash Block adds proactive **AI-era safety features** — purpose-built protections against the unique risks introduced by AI-generated code modifications.

## 2. Value Proposition
* **Zero-Downtime Recovery:** Regain admin access instantly when a theme or plugin crash locks you out of the standard dashboard.
* **Proactive Protection:** Automatically backups critical files like `functions.php` and shuts down failing plugins before they can cause cascading failures.
* **Cryptographic Security:** The emergency recovery panel uses a randomized, unguessable filename, ensuring only authorized personnel can access the disaster recovery tools.
* **AI-Era Safety:** 10-point health scanner, code quarantine API, and AI code fingerprinting catch problems before they crash your site.

## 3. Key Features
* **Early Error Trapping (MU-Plugin):** Installs a "Must-Use" plugin that loads at the absolute start of the WordPress lifecycle, catching errors that standard plugins cannot.
* **Standalone Recovery Panel:** A single-file PHP application (`cb-admin-*.php`) at the site root that works independently of the WordPress database and theme engine.
* **Auto-Restoration:** If a `functions.php` edit causes a crash, the plugin automatically detects the failure and restores the last known working backup.
* **WP-Config Interface:** View and edit critical site constants directly from the emergency panel.
* **Incident Alerting (SOS):** Sends immediate email notifications to the site administrator when a critical failure is trapped, including a direct, one-click link to the recovery panel.
* **NB Checkup:** Comprehensive 10-point health scan of `functions.php` — detects code after closing tags, duplicate functions, unmatched braces, dangerous functions, missing PHP tags, BOM characters, and AI-generated code patterns.
* **Versioned Backups:** Rolling 5-version backup timeline with timestamps and change attribution logging.
* **AI Output Quarantine:** Public API function `crash_block_quarantine_check($code)` for NB plugins to validate code before writing to `functions.php`.
* **PHP Syntax Pre-flight:** Runs `php -l` lint validation before saves. Gracefully degrades if `exec()` is disabled.
* **AI Code Fingerprinting:** Detects common AI-generated code patterns (ChatGPT/Claude/Gemini comments, placeholder function names, TODO/FIXME markers).
* **Change Attribution Log:** Tracks timestamp, user, file hash, and size delta for every `functions.php` modification.

## 4. Quick Start Guide
1. Activate **NetBound Hub** (nb-dashboard).
2. Activate **NB Crash Block**.
3. Go to `NetBound Hub > NB Crash Block`.
4. Note your **Emergency Recovery URL** (displayed on the settings page).
5. **Bookmark this URL** or email it to yourself using the built-in "Email URL" tool.
6. Verify the **MU-Plugin** status is "Installed" to ensure maximum protection.
7. Click **Run Checkup** to perform a comprehensive health scan of your site.

## 5. User Interface Reference
* **Admin Dashboard:** Manage backups, configure error trapping sensitivity, run NB Checkup scans, and manage child-theme snapshots.
* **NB Checkup Panel:** One-click comprehensive health scan with phased results, severity badges, and actionable recommendations.
* **Emergency Panel:** A clean, high-performance interface for disabling plugins, editing `wp-config.php`, and viewing detailed fatal error logs when WP is offline.

## 6. Technical Reference

### Standalone Recovery Assets
* **Panel Location:** `your-site.com/cb-admin-[random-hash].php`
* **Error Logs:** Stored in `wp-content/.crash-block-errors.json`.
* **Action Logs:** Stored in `wp-content/.crash-block-actions.log`.
* **Functions Audit:** Stored in `wp-content/.crash-block-functions-audit.json` (rolling 50 entries).

### MU-Plugin Handler
* **File:** `wp-content/mu-plugins/crash-block-handler.php`
* **Function:** Uses `register_shutdown_function` to catch `E_ERROR`, `E_PARSE`, and `E_COMPILE_ERROR` before WordPress finishes loading.

### AJAX Handlers
* `crash_block_backup_functions`: Creates a versioned snapshot of the active theme's `functions.php` (rolling 5 backups).
* `crash_block_run_checkup`: Runs the full NB Checkup health scan (functions.php analysis + backup status + child theme inventory + PHP lint + AI fingerprinting).
* `crash_block_check_functions`: Runs only the 10-point `functions.php` health check.
* `crash_block_save_notifications`: Persists alert email and Pulse preferences.

### Public API Functions
* `crash_block_quarantine_check($code)`: Validates proposed PHP code before writing to `functions.php`. Returns `['pass' => bool, 'issues' => array]`.
* `crash_block_check_functions_health()`: Returns a detailed health report for the active theme's `functions.php`.
* `crash_block_create_versioned_backup($file, $source)`: Creates a timestamped backup with automatic pruning.
* `crash_block_php_lint($file)`: Runs PHP syntax validation. Returns `true` on success or error string on failure.

## 7. FAQ
### Is the emergency panel safe to leave on the server?
Yes. The filename is 24 characters of random hex (over 2^96 combinations), making it statistically impossible to guess. It is safer than most standard login pages.

### Does it work with all themes?
Yes. It specifically protects `functions.php` in both parent and child themes and works with all major page builders.

### What does the NB Checkup scan for?
The 10-point scan checks for: missing PHP opening tags, BOM/whitespace before tags, code after closing `?>` tags, multiple opening tags, near-empty files, duplicate function definitions, unmatched braces, dangerous functions (`eval`, `exec`, etc.), test crash residue, and unusual file size.

## 8. Troubleshooting
* **Issue:** The emergency panel shows a 404 error.
* **Solution:** Go to the plugin settings and click "Regenerate Panel" to ensure the file exists on the server. Ensure your server allows execution of PHP files in the root directory.

## 9. Changelog

5.4.0 (2026-06-09)
* New: NB Checkup — comprehensive 10-point health scanner for functions.php.
* New: Versioned Backup Timeline — keeps 5 rolling backups with timestamps.
* New: Change Attribution Log — tracks who/what modified functions.php.
* New: AI Output Quarantine API — validates code before committing.
* New: PHP Syntax Pre-flight — runs php -l validation before saves.
* New: AI Code Fingerprinting — detects common AI-generated code patterns.
* Fix: Backup now works for parent theme functions.php when no child theme active.

5.2.10 (2026-05-23)
* Fixed: Removed raw emojis and replaced with HTML entities or plain text status. Implement Hub-First Bootstrap Loader.

5.2.9 (2026-05-23)
* Fixed: Character encoding / corrupted characters in ecosystem bootstrap file.

5.2.8 (2026-05-21)
* Fixed: Stopped forcing reactivation of NetBound Hub on admin_init to allow normal deactivation.
* Bump: Version header and internal constants updated.

5.2.7 (2026-05-14)
* Bump: Version bump and additional AJAX security hooks.

5.2.6 (2026-05-11)
* Sync: Synchronized with NetBound Hub v6.6.9 UI and design system.
* UI: Updated to use the standardized 2x2 button grid layout.
* Bump: Version header and internal constants updated.

5.2.1 (2026-05-02)
* New: Rebuild Hub stabilization. Renamed 'Restore Hub' to 'Rebuild Hub' for clarity.
* New: Automated Hub Rebuild now attempts direct database activation.
* Bump: Unified versioning across core files and templates.

4.8.2 (2026-04-23)
* Added: Emergency SOS Alert System with standalone PHP mail fallback.
* Added: NetBound Pulse centralized crash reporting.
* Added: Fault attribution logic for nb-* and netbound-* plugins in SOS emails.
* Improved: 3-column dashboard UI with orange underline aesthetic.

4.8.1 (2026-04-23)
* Added: Backup detection and one-click 'Restore' buttons in the recovery panel.
* Improved: Recovery Panel diagnostic header for real-time error parsing.

---
© 2026 NetBound
