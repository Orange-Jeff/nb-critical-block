# Crash Block - Comprehensive Refactor Plan

## Current State
The admin page exists but is missing several key features requested.

## Required Additions

### 1. Child Theme Management (PRIORITY HIGH)
**Location:** Add to AJAX handlers section

```php
// Create child theme with nb-(parent)-child naming
add_action('wp_ajax_crash_block_create_child', 'crash_block_ajax_create_child_new');
function crash_block_ajax_create_child_new() {
    check_ajax_referer('crash_block_admin', 'nonce');
    if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized');

    $parent = wp_get_theme();
    $child_slug = 'nb-' . $parent->get_stylesheet() . '-child';
    $child_dir = get_theme_root() . '/' . $child_slug;

    if (file_exists($child_dir)) {
        wp_send_json_error('Child theme already exists');
    }

    wp_mkdir_p($child_dir);

    // Create style.css
    $style = "/*\nTheme Name: " . $parent->get('Name') . " Child\nTemplate: " . $parent->get_stylesheet() . "\n*/\n";
    file_put_contents($child_dir . '/style.css', $style);

    // Create functions.php
    $functions = "<?php\n// Child Theme Functions\nadd_action('wp_enqueue_scripts', function() {\n    wp_enqueue_style('parent-style', get_template_directory_uri() . '/style.css');\n});\n";
    file_put_contents($child_dir . '/functions.php', $functions);

    // Copy parent screenshot if exists
    $parent_screenshot = get_theme_root() . '/' . $parent->get_stylesheet() . '/screenshot.png';
    if (file_exists($parent_screenshot)) {
        copy($parent_screenshot, $child_dir . '/screenshot.png');
    }

    // Activate child theme
    switch_theme($child_slug);

    wp_send_json_success('Child theme created and activated!');
}

// Delete child theme
add_action('wp_ajax_crash_block_delete_child', 'crash_block_ajax_delete_child');
function crash_block_ajax_delete_child() {
    check_ajax_referer('crash_block_admin', 'nonce');
    if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized');

    if (!is_child_theme()) {
        wp_send_json_error('Not using a child theme');
    }

    $child_theme = wp_get_theme();
    $parent_slug = $child_theme->get_template();
    $child_dir = get_stylesheet_directory();

    // Switch to parent first
    switch_theme($parent_slug);

    // Delete child theme directory
    crash_block_recursive_delete($child_dir);

    wp_send_json_success('Child theme deleted, switched to parent');
}

function crash_block_recursive_delete($dir) {
    if (!file_exists($dir)) return true;
    if (!is_dir($dir)) return unlink($dir);
    foreach (scandir($dir) as $item) {
        if ($item == '.' || $item == '..') continue;
        if (!crash_block_recursive_delete($dir . DIRECTORY_SEPARATOR . $item)) return false;
    }
    return rmdir($dir);
}
```

### 2. Functions.php Restore
```php
add_action('wp_ajax_crash_block_restore_functions', 'crash_block_ajax_restore_functions');
function crash_block_ajax_restore_functions() {
    check_ajax_referer('crash_block_admin', 'nonce');
    if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized');

    $functions_file = get_stylesheet_directory() . '/functions.php';
    $backup_file = $functions_file . '.backup';

    if (!file_exists($backup_file)) {
        wp_send_json_error('No backup exists');
    }

    // Save current as BEFORE-RESTORE
    $current_backup = $functions_file . '.BEFORE-RESTORE';
    copy($functions_file, $current_backup);

    // Restore from backup
    copy($backup_file, $functions_file);

    wp_send_json_success('functions.php restored from backup');
}
```

### 3. MU Plugin Uninstall
```php
add_action('wp_ajax_crash_block_uninstall_mu', 'crash_block_ajax_uninstall_mu');
function crash_block_ajax_uninstall_mu() {
    check_ajax_referer('crash_block_admin', 'nonce');
    if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized');

    $mu_file = WPMU_PLUGIN_DIR . '/crash-block-handler.php';

    if (file_exists($mu_file)) {
        unlink($mu_file);
        wp_send_json_success('Early error handler uninstalled');
    } else {
        wp_send_json_error('Handler not installed');
    }
}
```

### 4. File Snapshot System
```php
add_action('wp_ajax_crash_block_create_snapshot', 'crash_block_ajax_create_snapshot');
function crash_block_ajax_create_snapshot() {
    check_ajax_referer('crash_block_admin', 'nonce');
    if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized');

    $snapshot_file = WP_CONTENT_DIR . '/.crash-block-snapshot.json';
    $wp_root = ABSPATH;
    $files = crash_block_scan_wordpress_files($wp_root);

    $snapshot = [
        'date' => date('Y-m-d H:i:s'),
        'files' => $files
    ];

    file_put_contents($snapshot_file, json_encode($snapshot, JSON_PRETTY_PRINT));

    wp_send_json_success('Snapshot created with ' . count($files) . ' files');
}

function crash_block_scan_wordpress_files($dir) {
    $files = [];
    $exclude_dirs = ['wp-content/uploads', 'wp-content/cache', 'node_modules'];

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($iterator as $file) {
        if ($file->isFile()) {
            $path = $file->getPathname();
            $relative = str_replace($dir, '', $path);

            // Skip user content directories
            $skip = false;
            foreach ($exclude_dirs as $exclude) {
                if (strpos($relative, $exclude) !== false) {
                    $skip = true;
                    break;
                }
            }

            if (!$skip && preg_match('/\.(php|js|css)$/', $relative)) {
                $files[$relative] = [
                    'size' => $file->getSize(),
                    'modified' => $file->getMTime(),
                    'hash' => md5_file($path)
                ];
            }
        }
    }

    return $files;
}

add_action('wp_ajax_crash_block_compare_snapshots', 'crash_block_ajax_compare_snapshots');
function crash_block_ajax_compare_snapshots() {
    check_ajax_referer('crash_block_admin', 'nonce');
    if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized');

    $snapshot_file = WP_CONTENT_DIR . '/.crash-block-snapshot.json';

    if (!file_exists($snapshot_file)) {
        wp_send_json_error('No snapshot exists. Create one first.');
    }

    $old_snapshot = json_decode(file_get_contents($snapshot_file), true);
    $current_files = crash_block_scan_wordpress_files(ABSPATH);

    $changes = [];
    $added = [];
    $deleted = [];
    $modified = [];

    // Find modified and deleted
    foreach ($old_snapshot['files'] as $path => $old_data) {
        if (!isset($current_files[$path])) {
            $deleted[] = $path;
        } elseif ($current_files[$path]['hash'] !== $old_data['hash']) {
            $modified[] = $path;
        }
    }

    // Find added
    foreach ($current_files as $path => $data) {
        if (!isset($old_snapshot['files'][$path])) {
            $added[] = $path;
        }
    }

    $report = "Snapshot from: " . $old_snapshot['date'] . "\n\n";
    $report .= "Added: " . count($added) . " files\n";
    $report .= "Modified: " . count($modified) . " files\n";
    $report .= "Deleted: " . count($deleted) . " files\n\n";

    if (!empty($added)) {
        $report .= "ADDED FILES:\n";
        foreach (array_slice($added, 0, 10) as $file) {
            $report .= "  + " . $file . "\n";
        }
        if (count($added) > 10) $report .= "  ... and " . (count($added) - 10) . " more\n";
    }

    if (!empty($modified)) {
        $report .= "\nMODIFIED FILES:\n";
        foreach (array_slice($modified, 0, 10) as $file) {
            $report .= "  * " . $file . "\n";
        }
        if (count($modified) > 10) $report .= "  ... and " . (count($modified) - 10) . " more\n";
    }

    if (!empty($deleted)) {
        $report .= "\nDELETED FILES:\n";
        foreach (array_slice($deleted, 0, 10) as $file) {
            $report .= "  - " . $file . "\n";
        }
        if (count($deleted) > 10) $report .= "  ... and " . (count($deleted) - 10) . " more\n";
    }

    wp_send_json_success($report);
}
```

### 5. Update Admin Page Function

Replace the existing `crash_block_admin_page()` function starting around line 610 with:

```php
function crash_block_admin_page() {
    // Load comprehensive admin page
    if (file_exists(CRASH_BLOCK_PATH . 'admin-page-comprehensive.php')) {
        require_once CRASH_BLOCK_PATH . 'admin-page-comprehensive.php';
        crash_block_render_comprehensive_admin_page();
    } else {
        echo '<div class="wrap"><h1>Error: Admin page template not found</h1></div>';
    }
}
```

## Implementation Order
1. Add all AJAX handlers to the AJAX HANDLERS section (after line 1151)
2. Replace the admin page function
3. Test each feature individually

## Files Created
- `admin-page-comprehensive.php` - New comprehensive admin interface (ALREADY CREATED)
- This file - Implementation guide

## Next Steps
Add the AJAX handlers code block to crash-block.php right after the existing AJAX handlers section.
