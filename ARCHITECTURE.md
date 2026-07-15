# NB Crash Block - Architecture

## NetBound Ecosystem Integration

Crash Block follows the **NB Ecosystem Bootstrap Pattern v2** used by all NetBound plugins.

### Activation Sequence

**Phase 1: Ecosystem Bootstrap** (ALWAYS FIRST)
1. Check if `nb-dashboard` is installed
2. Auto-install dashboard if missing (King/Prince mode downloads)
3. Auto-update dashboard if version < 3.0.0
4. Activate dashboard if inactive
5. Register plugin with dashboard via `nb_register_plugin_activation()`
6. Set transients for dashboard notifications

**Phase 2: Crash-Block Bootstrap** (plugin-specific)
1. Generate random emergency panel filename
2. Create standalone panel file in webroot
3. Backup functions.php (if child theme exists)
4. Install MU plugin for early error catching
5. Take initial functions.php snapshot
6. Log all actions

This ensures crash-block appears in the **unified NB Dashboard menu** and integrates with the ecosystem's update checker.

---

## Two Separate Interfaces

### 1. WordPress Admin Page
**Location:** `/wp-admin/admin.php?page=crash-block`

**Features:**
- Full WordPress integration (admin bar, menu, dashboard)
- Requires WordPress to be working
- Shows protection setup, logs, actions
- Provides controls: backup, install MU, snapshot, regenerate URL
- 3-column dashboard with tooltips
- Uses WordPress functions (`wp_mail`, `update_option`, etc.)

**When to use:** Regular administration, setup, monitoring

---

### 2. Standalone Emergency Panel
**Location:** `/cb-admin-[24-random-chars].php` (e.g., `/cb-admin-a7b9c2e4f1d8b3a5f9e2d1c4.php`)

**Features:**
- **Works WITHOUT WordPress** (pure PHP)
- Connects directly to database via PDO
- Functional even when WordPress crashes
- Actions: disable plugins, restore functions.php, view logs, clear maintenance
- Emails admin on every access (using PHP `mail()`, not WordPress)
- Logs all access (IP, user agent, timestamp)

**When to use:** Emergency recovery when site is broken

---

## How They Work Together

```
Plugin Activation
    ↓
BOOTSTRAP ROUTINES:
    1. Generate random emergency panel filename
    2. Create standalone panel file in webroot
    3. Backup functions.php (if child theme exists)
    4. Install MU plugin for early error catching
    5. Take initial functions.php snapshot
    6. Log all actions
    ↓
WordPress Admin Page shows:
    - Emergency panel URL (BOOKMARK THIS!)
    - Bootstrap completion report
    - Protection status (4 steps)
    - Error logs (fatal, debug, actions, access)
    - File snapshot comparison
    - Capabilities guide
    ↓
When WordPress breaks:
    ↓
Emergency Panel accessible at saved URL
    - No WordPress needed
    - Direct database access
    - File system operations
    - Recovery actions
    - Sends email on access
```

---

## Bootstrap Routines (On Activation)

### 1. Emergency Panel Creation
- Generates 24-character random filename
- Creates standalone PHP file in webroot
- Saves filename to `wp_options`
- **Result:** Backdoor access URL created

### 2. Initial Backup
- Checks if child theme active
- Copies `functions.php` to `functions.php.backup`
- Only if backup doesn't already exist
- **Result:** Recovery point established

### 3. MU Plugin Installation
- Creates `/wp-content/mu-plugins/` if needed
- Installs `crash-block-handler.php`
- Loads before all other plugins
- Catches fatal errors early
- **Result:** First line of defense active

### 4. Initial Snapshot
- Takes MD5 hash of functions.php
- Saves to `functions.php.snapshot`
- Enables change detection
- **Result:** Baseline comparison established

---

## File Structure

```
/wordpress-root/
├── cb-admin-[random].php          ← Standalone emergency panel (generated)
│
└── wp-content/
    ├── .crash-block-errors.json    ← Fatal error log
    ├── .crash-block-actions.log    ← Recovery actions log
    ├── .crash-block-panel-access.log ← Panel access log
    │
    ├── mu-plugins/
    │   └── crash-block-handler.php ← Early error catcher
    │
    ├── plugins/
    │   └── crash-block/
    │       ├── crash-block.php          ← Main plugin (WordPress integration)
    │       ├── admin-panel-template.php ← Template for standalone panel
    │       └── readme.md
    │
    └── themes/
        └── [your-child-theme]/
            ├── functions.php         ← Your code
            ├── functions.php.backup  ← Auto-backup (restored on crash)
            └── functions.php.snapshot ← MD5 hash (change detection)
```

---

## Email Notifications

### Via WordPress (when working)
- **Function:** `wp_mail()`
- **Trigger:** Fatal error detected (functions.php or plugin)
- **Content:** Error details + emergency panel URL

### Via Pure PHP (no WordPress)
- **Function:** PHP `mail()`
- **Trigger:** Emergency panel accessed
- **Content:** Access details (IP, time, user agent) + regenerate instructions
- **How:** Connects to database via PDO, reads admin email from `wp_options`

---

## Security Model

**Random URL = Password:**
- 24 random characters = 2^96 possible combinations
- Mathematically impossible to guess
- No separate authentication needed

**If Compromised:**
1. Click "🔄 Regenerate URL" in WordPress admin
2. Old panel file deleted immediately
3. New random URL generated
4. Attacker's bookmarked URL stops working
5. New URL shown for bookmarking

**Access Monitoring:**
- Every access logged (time, IP, UA)
- Email sent to admin on every access
- Visible in WordPress admin dashboard
- Anomaly detection by admin

---

## Why Two Separate Pages?

**WordPress Admin Page:**
- Rich UI with WordPress styling
- Access to WordPress functions
- Database integration via WP API
- Safe, convenient regular use

**Standalone Emergency Panel:**
- NO WordPress dependencies
- Works when WP crashes
- Direct PHP + MySQL only
- Last resort recovery tool

**They serve different purposes:**
- Admin page = **prevention & monitoring**
- Emergency panel = **recovery & rescue**

---

## Key Design Principles

1. **Separation of Concerns:** Admin ≠ Emergency
2. **Zero WordPress Dependency:** Emergency panel uses raw PHP
3. **Proactive Protection:** MU plugin catches errors before cascade
4. **Auto-Recovery:** Functions.php auto-restores from backup
5. **Access Transparency:** Every emergency access logged and emailed
6. **Security Through Obscurity:** Random URL acts as authentication
7. **Bootstrap Everything:** Activation sets up complete protection
