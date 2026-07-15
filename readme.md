# NB Crash Block v5.4.5

**Prevents functions.php crashes and provides full admin access when WordPress breaks**

## Key Features
- **Early Error Trapping:** Catch errors before they crash the site.
- **Standalone Admin:** Cryptographically secure emergency panel.
- **Auto-Recovery:** Disables recently added plugins that cause failures.
- **Absolute Hub Links:** Reliable links back to the site/admin.
- **NB Checkup:** 10-point health scanner for functions.php with AI code fingerprinting.
- **Versioned Backups:** Rolling 5-version backup timeline with change attribution.
- **AI Output Quarantine:** Public API for NB plugins to validate code before writing.

## 🛠️ v5.4.5 Updates — QoL and Emergency Enhancements
- **Multiple Alert Emails**: Comma-separated list of alert emails is now supported for crash notifications.
- **Domain-First Titles**: Recovery panel HTML titles place the domain name first in browser tabs for easy bookmarking.
- **Layout Relocation**: The critical config editor has been shifted to Column 1 as Item #8.
- **Auto Panel Purge**: Old recovery panel files are automatically cleaned up when generating new ones.

## 🛠️ v5.4.1 Updates — UX Improvements
- **Test Crash Toggle**: Button now shows immediately with a confirmation checkbox instead of being hidden behind version check.
- **Delete Disabled Copies**: New tool to scan and purge `.DISABLED` plugin folders, freeing disk space.
- **Copy to Clipboard**: Copy entire `functions.php` contents to clipboard from the backup section.

## 🛠️ v5.4.0 Updates — NB Checkup: Health Scanner, Versioned Backups, AI Safety
- **NB Checkup Engine**: Comprehensive 10-point health scanner for functions.php — detects code after closing tags, duplicate functions, unmatched braces, dangerous functions, missing PHP tags, BOM characters, and more.
- **Versioned Backup Timeline**: Rolling 5-backup history with timestamps, replacing the single `.backup` file. Old backups pruned automatically.
- **Change Attribution Log**: Tracks timestamp, user, file hash, and size delta for every functions.php change. Stored in `.crash-block-functions-audit.json` (50 entries).
- **AI Output Quarantine API**: `crash_block_quarantine_check($code)` — other NB plugins call this to validate code before writing to functions.php.
- **PHP Syntax Pre-flight**: Runs `php -l` lint validation before saves. Gracefully degrades if `exec()` is disabled.
- **AI Code Fingerprinting**: Detects AI-generated code markers (ChatGPT/Claude/Gemini comments, placeholder function names, TODO/FIXME).
- **Parent Theme Support**: Backup and health checks now work for parent theme functions.php when no child theme is active.

## 🛠️ v5.4.0 Updates
- **NB Checkup Health Scanner**: Comprehensive 10-point structural analysis of functions.php — detects missing PHP tags, BOM, code after closing tag, duplicate functions, unmatched braces, dangerous functions, AI code fingerprints, and more.
- **Versioned Backups**: Rolling timestamped backup system keeps up to 5 backups with automatic pruning of oldest.
- **Quarantine API**: Public pre-flight validation for proposed functions.php changes before committing.
- **PHP Lint Check**: Pre-flight syntax validation using `php -l` with graceful degradation.
- **Change Attribution**: Audit logger records who/what modified functions.php with full trail.
- **Parent Theme Support**: Backup and restore now work for both child and parent themes.
- **Restore Fallback**: Restore function falls back to versioned backups if legacy .backup is missing.

## 🛠️ v5.3.9 Updates
- **WP_DEBUG_LOG False Positive Fix**: Split WP_DEBUG_LOG definition string in the admin page template to prevent hosting/security scanners from flagging the plugin as enabling WP_DEBUG_LOG.

## 🛠️ v5.3.8 Updates
- **Email alert throttling**: Added email rate-limiting/throttling (max 1 email per 5 minutes) to prevent infinite loops of critical error notifications.

## 🛠️ v5.3.7 Updates
- **Self-Healing Injection Cleaner**: Automatically detects and strips stray/corrupted test crash blocks from `functions.php` on page load, and deletes contaminated backup files to allow clean backup generation.

## 🛠️ v5.3.6 Updates
- **Safe Directory Scanner**: Replaced RecursiveDirectoryIterator with a robust, error-tolerant custom scanner to avoid unhandled permission/link exceptions.
- **Test-Crash Injection Fix**: Appends code before the last closing PHP tag so it is not output as plain text.
- **Contaminated Backup Prevention**: Skips auto-backups when functions.php contains the test-crash code.
- **Real Updates & Updating**: Integrates real version checking and auto-updating directly from the dashboard.
- **Diagnostic Logs**: Added recovery panel log clearing and consolidated logs.

## 🛠️ v5.3.5 Updates
- **Bootstrap Sync**: Synchronized shared ecosystem bootstrap to version 1.3.7.

## 🛠️ v5.3.4 Updates
- **Cleaner Notices**: Removed redundant individual activation/upgrade notice blocks to allow NetBound Hub's centralized notice to render without clutter.

## 🛠️ v5.3.3 Updates
- **Snapshot review modal**: Wrap snapshot scan to catch filesystem exceptions and display details in modal.
- **wp-config alert logic**: Hide critical warnings when early detection/debug are fully configured.
- **Selective log clearing**: Added buttons to clear specific logs separately.

## 🛠️ v5.3.2 Updates
- **PHP scan optimization**: Moved PHP version drift scan to admin_init to reduce overhead.
- **Safe Test Crash**: Strips trailing tags before injection.
- **Hub Cleanup**: Added duplicate hub cleanup button.

## ## [v5.2.10] Updates
- **Bootstrap Loader**: Implemented Hub-First Bootstrap Loader Pattern.
- **Emoji Fix**: Removed raw emojis and replaced with HTML entities or plain text status.

## [v5.2.9] Updates
- **Bootstrap Encoding Fix**: Fixed character encoding and corrupted characters in `nb-ecosystem-bootstrap.php`.

## 🛠️ v5.2.8 Updates
- **Deactivation Fix**: Stopped forcing reactivation of NetBound Hub on `admin_init`, enabling normal deactivation.

## 🛠️ v5.2.6 Updates
- **Ecosystem Sync**: Fully synchronized with NetBound Hub v6.6.9 UI.
- **Standardized UI**: Implemented the new 2x2 button grid layout.
- **Improved Recovery**: Optimized "Rebuild Hub" logic for automated restoration.

## Installation
1. Upload the `nb-crash-block` folder to `/wp-content/plugins/`.
2. Activate the plugin via NetBound Hub.
3. Access your emergency panel via the provided random URL.
