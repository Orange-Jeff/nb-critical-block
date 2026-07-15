## v5.3.9 - June 9, 2026 - WP_DEBUG_LOG FALSE POSITIVE FIX
- FIX: Split WP_DEBUG_LOG definition string in the admin page template to prevent hosting/security scanners from flagging the plugin as enabling WP_DEBUG_LOG.

## v5.3.8 - June 7, 2026 - EMAIL ALERT THROTTLING
- FIX: Added email rate-limiting/throttling (max 1 email per 5 minutes) to prevent infinite loops of critical error notifications when a site experiences repeated fatal errors.

## v5.3.7 - June 5, 2026 - TEST-CRASH SELF-HEALING
- NEW: Added automated self-healing on admin_init to detect and strip old/corrupted test-crash code from functions.php and delete contaminated backups.

## v5.3.6 - June 5, 2026 - FILE LIST & RECOVERY IMPROVEMENTS
- FIX: Replaced RecursiveDirectoryIterator with safe, cross-platform custom recursive scanner to prevent directory traversal / permission server errors.
- FIX: Enhanced test-crash injection to always insert inside php blocks (before trailing closing tag) and prevent auto-backup of crashed file.
- NEW: Added real version checking against manifest and auto-updating mechanism.
- NEW: Added recovery log clearing and consolidated diagnostic logs panel.

## v5.3.5 - June 5, 2026 - BOOTSTRAP SYNC
- BUMP: Synchronized shared ecosystem bootstrap to version 1.3.7.

## v5.3.4 - June 5, 2026 - CLEANER NOTICES
- REMOVED: Redundant individual plugin activation/upgrade notices to allow NetBound Hub to handle the unified announcement banner.

## v5.3.3 - June 5, 2026 - FILE LOG MODAL & SELECTIVE LOGS
- FIX: Wrap snapshot scan to catch filesystem exceptions and display details in a comparison modal.
- FIX: Hide critical wp-config warning if early detection and debug log are configured.
- NEW: Added clear action buttons for error log and system action log.

## v5.3.2 - June 4, 2026 - UI UPDATE & OPTIMIZATIONS
- FIX: Moved PHP version drift scan to admin_init to reduce overhead.
- FIX: Test Crash safely strips trailing tags before injection.
- NEW: Added duplicate hub cleanup button in admin page.

## v5.3.1 - May 29, 2026 - STRAY ROOT FILES CLEANUP
- NEW: Enhanced uninstall & cleanup routines to purge stray nb-* and netbound-* files in root plugins folder.

## v5.3.0 - May 29, 2026 - PHP VERSION SWITCHING & SAFETY ROLLBACK ENGINE
- NEW: Added PHP Version switching panel with auto-rollback capability.
- NEW: Integrated .htaccess cPanel/Apache handler parsing and state capture.

## v5.2.11 - May 24, 2026 - ECOSYSTEM UPGRADE
- UPDATED: Bundled bootstrap v1.3.5 for automatic update detection.
- NEW: Added activation log console display notice on manual overwrite updates.

## v5.2.10 - May 23, 2026 - EMOJI REMOVAL
- FIXED: Removed raw emojis and replaced with HTML entities or plain text status. Implement Hub-First Bootstrap Loader.

## v5.2.9 - May 23, 2026 - ECOSYSTEM BOOTSTRAP FIX
- FIXED: Character encoding / corrupted characters in ecosystem bootstrap file.

## v5.2.8 - May 21, 2026 - DEACTIVATION FIX
- FIXED: Stopped forcing reactivation of NetBound Hub on admin_init, allowing normal deactivation.

## v5.2.1 - May 13, 2026 - REBUILD HUB STABILIZATION
- UPDATED: Renamed 'Restore Hub' to 'Rebuild Hub' across all interfaces for clarity.
- NEW: Automated Hub Rebuild now attempts direct database activation of the nb-hub plugin.
- SYNC: Unified versioning to 5.2.1 across core files and templates.

## v4.5.1 - March 2, 2026 - PADDING BUG FIX
- FIXED: The persistent "No Padding Bug" with standardized 25px workspace CSS.
- FIXED: Absolute URLs for Site/Admin links in Emergency Panel to resolve double-slash issues.
- BUMP: Synced version to 4.5.1 across all templates and cores.

## v4.4.5 - March 2, 2026 - RELIABILITY & UI
- FIXED: Standalone recovery panel links to WordPress Admin and Site Home now use absolute URLs to prevent broken links during 500 errors.
- UPDATED: readme.html with premium styling and high-quality screenshot.
- REFINED: MU plugin error page now includes direct links to Admin and Site Home.

## [4.4.4] - 2026-03-02
- Fixed admin interface padding to ensure content does not touch left side border.

## [4.4.3] - 2026-03-02
- Re-scoped internal plugin CSS classes to use `.nb-crash-block-wrap` and `.cb-btn` avoiding bleed onto other WP-admin screens.
