<?php
/**
 * Crash Block - Standalone Admin Panel Template
 * This file works WITHOUT WordPress loaded
 * It provides full admin access even when WordPress is broken
 */

if (!defined('ABSPATH') && !defined('CB_STANDALONE')) {
	define('CB_STANDALONE', true);
}

function crash_block_get_panel_template() {
	return str_replace('__CB_VERSION__', CRASH_BLOCK_VERSION, <<<'PANEL'
<?php
/**
 * Crash Block Standalone Admin Panel
 * Version: __CB_VERSION__
 *
 * This panel works WITHOUT WordPress
 * Provides full admin access for emergency recovery
 */

define('CB_VERSION', '__CB_VERSION__');
define('CB_ROOT', __DIR__);
define('CB_CONTENT', CB_ROOT . '/wp-content');
define('CB_PLUGINS', CB_CONTENT . '/plugins');
define('CB_THEMES', CB_CONTENT . '/themes');

// ============================================================================
// ACCESS LOGGING & EMAIL NOTIFICATION & OLD PANEL PURGE
// ============================================================================

// Purge any old/obsolete recovery files in CB_ROOT
$current_panel_file = basename($_SERVER['SCRIPT_NAME'] ?? '');
if (empty($current_panel_file) || strpos($current_panel_file, 'recovery-') === false) {
    $current_panel_file = basename(__FILE__);
}
$all_recovery_panels = glob(CB_ROOT . '/recovery-*.php');
if ($all_recovery_panels) {
    foreach ($all_recovery_panels as $p_file) {
        if (basename($p_file) !== $current_panel_file) {
            @unlink($p_file);
        }
    }
}

$access_log = CB_CONTENT . '/.crash-block-panel-access.log';
$access_entry = date('Y-m-d H:i:s') . ' | IP: ' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown') . ' | UA: ' . ($_SERVER['HTTP_USER_AGENT'] ?? 'unknown') . PHP_EOL;
@file_put_contents($access_log, $access_entry, FILE_APPEND);

// Handle actions
$action = $_GET['action'] ?? '';
$result = null;

if ($action) {
    switch ($action) {
        case 'disable_plugins':
            $result = cb_disable_plugins($_GET['scope'] ?? 'all');
            break;
        case 'enable_plugins':
            $result = cb_enable_plugins();
            break;
        case 'restore_functions':
            $result = cb_restore_functions();
            break;
        case 'view_logs':
            $result = cb_view_logs();
            break;
        case 'list_plugins':
            $result = cb_list_plugins();
            break;
        case 'delete_plugin':
            $result = cb_delete_plugin($_GET['plugin'] ?? '');
            break;
        case 'enable_plugin':
            $result = cb_enable_plugin($_GET['plugin'] ?? '');
            break;
        case 'list_themes':
            $result = cb_list_themes();
            break;
        case 'view_file':
            $result = cb_view_file($_GET['file'] ?? '');
            break;
        case 'save_file':
            $result = cb_save_file($_POST['file'] ?? '', $_POST['content'] ?? '');
            break;
        case 'restore_file':
            $result = cb_restore_file($_GET['file'] ?? '');
            break;
        case 'list_recent':
            $result = cb_list_recent_files();
            break;
        case 'clear_maintenance':
            $result = cb_clear_maintenance();
            break;
        case 'set_maintenance':
            $result = cb_set_maintenance();
            break;
        case 'reset_htaccess':
            $result = cb_reset_htaccess();
            break;
        case 'nuke_cache':
            $result = cb_nuke_cache();
            break;
        case 'get_env':
            $result = cb_get_env_status();
            break;
        case 'delete_self':
            $result = cb_self_destruct();
            break;
        case 'reinstall_hub':
            $result = cb_reinstall_hub();
            break;
        case 'upload_file':
            $result = cb_upload_file();
            break;
        case 'clear_logs':
            $result = cb_clear_logs();
            break;
    }
}

function cb_reinstall_hub() {
    $zip_url = 'https://netbound.ca/downloads/plugins/nb-hub.zip';
    $tmp_zip = CB_CONTENT . '/nb-hub-temp.zip';
    $dest = CB_PLUGINS . '/nb-hub';

    // Download using file_get_contents with context for timeout/SSL
    $ctx = stream_context_create([
        'http' => ['timeout' => 60],
        'ssl'  => ['verify_peer' => false, 'verify_peer_name' => false]
    ]);
    
    $data = @file_get_contents($zip_url, false, $ctx);
    
    if (!$data) {
        // Try curl if file_get_contents fails (sometimes allow_url_fopen is off)
        if (function_exists('curl_init')) {
            $ch = curl_init($zip_url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            $data = curl_exec($ch);
            curl_close($ch);
        }
    }

    if (!$data || strlen($data) < 1000) {
        return ['success' => false, 'message' => 'Failed to download Hub. Please ensure NetBound.ca is reachable.'];
    }

    if (!@file_put_contents($tmp_zip, $data)) {
        return ['success' => false, 'message' => 'Failed to save ZIP to wp-content. Check permissions.'];
    }

    // Extract
    if (class_exists('ZipArchive')) {
        $zip = new ZipArchive;
        if ($zip->open($tmp_zip) === TRUE) {
            $zip->extractTo(CB_PLUGINS);
            $zip->close();
            @unlink($tmp_zip);
            
            // v5.2.1: Attempt Automated Activation via DB
            $activated_msg = "";
            $pdo = cb_get_db();
            if ($pdo) {
                $config_content = file_get_contents(CB_ROOT . '/wp-config.php');
                preg_match("/\$table_prefix\s*=\s*['\"]([^'\"]+)['\"]/", $config_content, $prefix_match);
                $prefix = $prefix_match[1] ?? 'wp_';
                
                $stmt = $pdo->query("SELECT option_value FROM {$prefix}options WHERE option_name = 'active_plugins' LIMIT 1");
                if ($stmt) {
                    $val = $stmt->fetchColumn();
                    $active = @unserialize($val);
                    if (is_array($active)) {
                        $hub_plugin = 'nb-hub/nb-hub.php';
                        if (!in_array($hub_plugin, $active)) {
                            $active[] = $hub_plugin;
                            sort($active);
                            $new_val = serialize($active);
                            $update = $pdo->prepare("UPDATE {$prefix}options SET option_value = ? WHERE option_name = 'active_plugins'");
                            if ($update->execute([$new_val])) {
                                $activated_msg = " and auto-activated in the database!";
                            }
                        } else {
                            $activated_msg = " (already active in database).";
                        }
                    }
                }
            }

            return ['success' => true, 'message' => 'NetBound Hub has been rebuilt' . $activated_msg . ' You can now try refreshing your site.'];
        }
    }

    return ['success' => false, 'message' => 'ZIP saved but ZipArchive is not available for extraction. Manually extract wp-content/nb-hub-temp.zip'];
}

function cb_self_destruct() {
    $file = $_SERVER['SCRIPT_FILENAME'];
    if (file_exists($file)) {
        if (unlink($file)) {
            die('Emergency panel deleted successfully. You can close this window.');
        }
    }
    return ['success' => false, 'message' => 'Failed to delete file. Check permissions.'];
}

// ============================================================================
// ACTIONS
// ============================================================================

function cb_disable_plugins($scope = 'all') {
    $timestamp = time();
    if ($scope === 'today') {
        $cutoff = time() - 86400; // 24 hours ago
        $disabled = [];
        if (is_dir(CB_PLUGINS)) {
             $items = scandir(CB_PLUGINS);
             foreach ($items as $item) {
                 if ($item === '.' || $item === '..' || strpos($item, '.DISABLED') !== false) continue;
                 $path = CB_PLUGINS . '/' . $item;
                 if (is_dir($path) && filemtime($path) > $cutoff) {
                     rename($path, $path . '.DISABLED-' . $timestamp);
                     $disabled[] = $item;
                 }
             }
             return ['success' => true, 'message' => 'Disabled ' . count($disabled) . ' recent plugins'];
        }
    }

    if (is_dir(CB_PLUGINS)) {
        if (rename(CB_PLUGINS, CB_PLUGINS . '.DISABLED-' . $timestamp)) {
            return ['success' => true, 'message' => 'All plugins disabled'];
        }
    }
    return ['success' => false, 'message' => 'Could not disable plugins'];
}

function cb_enable_plugins() {
    $disabled_dirs = glob(CB_PLUGINS . '.DISABLED-*');
    if (!empty($disabled_dirs)) {
        if (rename($disabled_dirs[0], CB_PLUGINS)) {
            return ['success' => true, 'message' => 'All plugins enabled'];
        }
    }
    return ['success' => false, 'message' => 'Plugins directory not found'];
}

function cb_enable_plugin($plugin) {
    if (empty($plugin)) return ['success' => false, 'message' => 'No plugin specified'];
    $path = CB_PLUGINS . '/' . basename($plugin);
    if (is_dir($path) && strpos($plugin, '.DISABLED') !== false) {
        $new_path = preg_replace('/\.DISABLED(-\d+)?$/', '', $path);
        if (rename($path, $new_path)) {
            return ['success' => true, 'message' => "Plugin '{$plugin}' re-enabled"];
        }
    }
    return ['success' => false, 'message' => 'Plugin not found or not disabled'];
}

function cb_restore_functions() {
    $theme = cb_get_active_theme();
    if (!$theme) return ['success' => false, 'message' => 'Active theme not found'];
    
    $functions = CB_THEMES . '/' . $theme . '/functions.php';
    $backup = $functions . '.backup';

    if (file_exists($backup)) {
        $failed = $functions . '.FAILED-' . date('Ymd-His');
        @rename($functions, $failed);
        @copy($backup, $functions);
        return ['success' => true, 'message' => 'functions.php restored from backup'];
    }

    return ['success' => false, 'message' => 'No backup found in ' . $theme];
}

function cb_view_logs() {
    $logs = [];
    $cb_error_log = CB_CONTENT . '/.crash-block-errors.json';
    if (file_exists($cb_error_log)) {
        $data = json_decode(file_get_contents($cb_error_log), true);
        $formatted = [];
        if (is_array($data)) {
            foreach ($data as $entry) {
                $time = $entry['date'] ?? 'Unknown';
                $msg = $entry['message'] ?? 'Error';
                $file = isset($entry['file']) ? basename($entry['file']) . ':' . ($entry['line'] ?? '?') : '';
                $formatted[] = "[$time] $msg ($file)";
            }
        }
        $logs['Crash Block Errors'] = array_slice($formatted, 0, 20);
    }

    $actions_log = CB_CONTENT . '/.crash-block-actions.log';
    if (file_exists($actions_log)) {
        $logs['System Actions'] = cb_read_last_lines($actions_log, 50);
    }

    $debug = CB_CONTENT . '/debug.log';
    if (file_exists($debug)) {
        $logs['WordPress Debug'] = cb_read_last_lines($debug, 50);
    }
    return ['success' => true, 'logs' => $logs];
}

function cb_read_last_lines($filepath, $num_lines = 50) {
    if (!file_exists($filepath) || !is_readable($filepath)) {
        return [];
    }
    $file_size = filesize($filepath);
    if ($file_size === 0) {
        return [];
    }
    $fp = fopen($filepath, 'r');
    if (!$fp) {
        return [];
    }
    $chunk_size = 32768; // 32KB
    $offset = max(0, $file_size - $chunk_size);
    fseek($fp, $offset);
    $data = fread($fp, $chunk_size);
    fclose($fp);
    $lines = explode("\n", $data);
    $lines = array_map('trim', $lines);
    $lines = array_filter($lines);
    return array_slice(array_reverse($lines), 0, $num_lines);
}

function cb_clear_logs() {
    $files = [
        CB_CONTENT . '/.crash-block-errors.json',
        CB_CONTENT . '/.crash-block-mu-log.txt',
        CB_CONTENT . '/.crash-block-actions.log'
    ];
    $cleared = 0;
    foreach ($files as $f) {
        if (file_exists($f)) {
            @file_put_contents($f, ''); // Truncate to empty
            @unlink($f); // Attempt to delete
            $cleared++;
        }
    }
    return ['success' => true, 'message' => 'All recovery logs cleared successfully.'];
}

function cb_list_plugins() {
    $plugins = [];
    if (is_dir(CB_PLUGINS)) {
        $items = scandir(CB_PLUGINS);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;
            $path = CB_PLUGINS . '/' . $item;
            if (is_dir($path)) {
                $plugins[] = [
                    'name' => $item,
                    'disabled' => strpos($item, '.DISABLED') !== false,
                    'time' => filemtime($path),
                    'date' => date('M j, Y', filemtime($path))
                ];
            }
        }
    }
    usort($plugins, function($a, $b) { return $b['time'] - $a['time']; });
    return ['success' => true, 'plugins' => $plugins];
}

function cb_delete_plugin($plugin) {
    $path = CB_PLUGINS . '/' . basename($plugin);
    if (is_dir($path)) {
        cb_delete_dir($path);
        return ['success' => true, 'message' => "Plugin '$plugin' deleted"];
    }
    return ['success' => false, 'message' => 'Plugin not found'];
}

function cb_list_themes() {
    $themes = [];
    if (is_dir(CB_THEMES)) {
        $items = scandir(CB_THEMES);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;
            $path = CB_THEMES . '/' . $item;
            if (is_dir($path)) {
                $themes[] = ['name' => $item, 'time' => filemtime($path)];
            }
        }
    }
    return ['success' => true, 'themes' => $themes];
}

function cb_view_file($file) {
    if (strpos($file, '..') !== false) return ['success' => false, 'message' => 'Path traversal detected'];
    $full_path = CB_ROOT . '/' . ltrim($file, '/');
    if (file_exists($full_path) && is_file($full_path)) {
        return ['success' => true, 'content' => file_get_contents($full_path), 'file' => $file];
    }
    return ['success' => false, 'message' => 'File not found'];
}

function cb_save_file($file, $content) {
    if (strpos($file, '..') !== false) return ['success' => false, 'message' => 'Path traversal detected'];
    $full_path = CB_ROOT . '/' . ltrim($file, '/');
    if (file_exists($full_path)) {
        @copy($full_path, $full_path . '.cb-bak');
        if (@file_put_contents($full_path, $content) !== false) {
            return ['success' => true, 'message' => 'File saved. Backup created.'];
        }
    }
    return ['success' => false, 'message' => 'Failed to save'];
}

function cb_restore_file($file) {
    $full_path = CB_ROOT . '/' . ltrim($file, '/');
    $backup = '';
    if (file_exists($full_path . '.nbak')) $backup = $full_path . '.nbak';
    elseif (file_exists($full_path . '.backup')) $backup = $full_path . '.backup';
    
    if ($backup && file_exists($full_path)) {
        @rename($full_path, $full_path . '.failed-' . time());
        if (@copy($backup, $full_path)) return ['success' => true, 'message' => 'Restored!'];
    }
    return ['success' => false, 'message' => 'No backup found'];
}

function cb_scan_directory_recursive($dir, &$results = [], $exclude_patterns = []) {
    if (empty($dir) || !is_dir($dir) || !is_readable($dir)) {
        return $results;
    }
    $items = @scandir($dir);
    if ($items === false) {
        return $results;
    }
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        $pathname = $dir . '/' . $item;
        $norm_path = str_replace('\\', '/', $pathname);
        
        $exclude = false;
        foreach ($exclude_patterns as $pattern) {
            if (strpos($norm_path, $pattern) !== false) {
                $exclude = true;
                break;
            }
        }
        if ($exclude) {
            continue;
        }

        if (@is_link($pathname)) {
            continue;
        }

        if (@is_dir($pathname)) {
            cb_scan_directory_recursive($pathname, $results, $exclude_patterns);
        } elseif (@is_file($pathname) && @is_readable($pathname)) {
            $results[] = $pathname;
        }
    }
    return $results;
}

function cb_list_recent_files() {
    $recent = [];
    $dirs = [CB_PLUGINS, CB_THEMES, CB_CONTENT . '/mu-plugins'];
    $cutoff = time() - (86400 * 3);
    $exclude = ['node_modules', '.git', 'vendor', 'cache', '.sass-cache'];
    foreach ($dirs as $dir) {
        if (!is_dir($dir)) continue;
        $files = [];
        cb_scan_directory_recursive($dir, $files, $exclude);
        foreach ($files as $pathname) {
            $mtime = @filemtime($pathname);
            if ($mtime > $cutoff) {
                $ext = strtolower(pathinfo($pathname, PATHINFO_EXTENSION));
                if (in_array($ext, ['php', 'css', 'js'])) {
                    $path = ltrim(str_replace(CB_ROOT, '', $pathname), '/\\');
                    $has_backup = file_exists($pathname . '.nbak') || file_exists($pathname . '.backup') || file_exists($pathname . '.cb-bak');
                    
                    $recent[] = [
                        'file' => $path,
                        'time' => $mtime,
                        'date' => date('M j, g:i a', $mtime),
                        'has_backup' => $has_backup
                    ];
                }
            }
        }
    }
    usort($recent, function($a, $b) { return $b['time'] - $a['time']; });
    return ['success' => true, 'files' => array_slice($recent, 0, 20)];
}

function cb_clear_maintenance() {
    $maintenance = CB_ROOT . '/.maintenance';
    if (file_exists($maintenance)) {
        unlink($maintenance);
        return ['success' => true, 'message' => 'Maintenance mode cleared'];
    }
    return ['success' => false, 'message' => '.maintenance file not found'];
}

function cb_set_maintenance() {
    $maintenance = CB_ROOT . '/.maintenance';
    if (@file_put_contents($maintenance, time())) {
        return ['success' => true, 'message' => 'Maintenance mode enabled'];
    }
    return ['success' => false, 'message' => 'Could not create .maintenance file'];
}

function cb_upload_file() {
    if (empty($_FILES['file'])) return ['success' => false, 'message' => 'No file received'];
    $f = $_FILES['file'];
    $target_dir = $_POST['dest'] ?? 'wp-content/plugins';
    // Sanitize: only allow plugins/themes/mu-plugins subdirs
    $allowed_roots = ['wp-content/plugins', 'wp-content/themes', 'wp-content/mu-plugins'];
    $safe = false;
    foreach ($allowed_roots as $r) {
        if (strpos($target_dir, $r) === 0 && strpos($target_dir, '..') === false) { $safe = true; break; }
    }
    if (!$safe) return ['success' => false, 'message' => 'Upload destination not allowed'];
    $dest = CB_ROOT . '/' . ltrim($target_dir, '/') . '/' . basename($f['name']);
    if (move_uploaded_file($f['tmp_name'], $dest)) {
        return ['success' => true, 'message' => 'Uploaded: ' . basename($f['name']) . ' to /' . $target_dir];
    }
    return ['success' => false, 'message' => 'Upload failed — check permissions on ' . $target_dir];
}

function cb_reset_htaccess() {
    $file = CB_ROOT . '/.htaccess';
    if (file_exists($file)) {
        @rename($file, $file . '.broken-' . time());
    }
    $default = "# BEGIN WordPress\n<IfModule mod_rewrite.c>\nRewriteEngine On\nRewriteBase /\nRewriteRule ^index\.php$ - [L]\nRewriteCond %{REQUEST_FILENAME} !-f\nRewriteCond %{REQUEST_FILENAME} !-d\nRewriteRule . /index.php [L]\n</IfModule>\n# END WordPress";
    if (@file_put_contents($file, $default)) {
        return ['success' => true, 'message' => '.htaccess has been reset to WordPress defaults.'];
    }
    return ['success' => false, 'message' => 'Failed to write .htaccess. Check permissions.'];
}

function cb_nuke_cache() {
    $files = [CB_CONTENT . '/object-cache.php', CB_CONTENT . '/advanced-cache.php'];
    $removed = [];
    foreach ($files as $f) {
        if (file_exists($f)) {
            if (@unlink($f)) $removed[] = basename($f);
        }
    }
    if (!empty($removed)) return ['success' => true, 'message' => 'Removed cache files: ' . implode(', ', $removed)];
    return ['success' => false, 'message' => 'No active drop-in cache files found.'];
}

function cb_get_env_status() {
    $db_status = cb_get_db() ? 'Connected' : 'Connection Failed';
    $disk_free = function_exists('disk_free_space') ? cb_format_size(disk_free_space(CB_ROOT)) : 'Unknown';
    return [
        'success' => true,
        'php' => PHP_VERSION,
        'db' => $db_status,
        'disk' => $disk_free,
        'memory' => ini_get('memory_limit')
    ];
}

function cb_format_size($bytes) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    for ($i = 0; $bytes > 1024; $i++) $bytes /= 1024;
    return round($bytes, 2) . ' ' . $units[$i];
}

// ============================================================================
// UTILITIES
// ============================================================================

function cb_get_db() {
    if (!class_exists('PDO')) return null;
    static $pdo = null;
    if ($pdo) return $pdo;
    $config_file = CB_ROOT . '/wp-config.php';
    if (!file_exists($config_file)) return null;
    $config_content = file_get_contents($config_file);
    preg_match("/define\s*\(\s*['\"]DB_NAME['\"]\s*,\s*['\"]([^'\"]+)['\"]/", $config_content, $db_name);
    preg_match("/define\s*\(\s*['\"]DB_USER['\"]\s*,\s*['\"]([^'\"]+)['\"]/", $config_content, $db_user);
    preg_match("/define\s*\(\s*['\"]DB_PASSWORD['\"]\s*,\s*['\"]([^'\"]+)['\"]/", $config_content, $db_pass);
    preg_match("/define\s*\(\s*['\"]DB_HOST['\"]\s*,\s*['\"]([^'\"]+)['\"]/", $config_content, $db_host);
    if (empty($db_name[1]) || empty($db_user[1])) return null;
    try {
        $pdo = new PDO('mysql:host=' . ($db_host[1] ?? 'localhost') . ';dbname=' . $db_name[1], $db_user[1], $db_pass[1] ?? '', [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_SILENT,
            PDO::ATTR_TIMEOUT => 2
        ]);
        return $pdo;
    } catch (Throwable $e) { return null; }
}

function cb_get_active_theme() {
    $pdo = cb_get_db();
    if ($pdo) {
        $config_content = file_get_contents(CB_ROOT . '/wp-config.php');
        preg_match("/\$table_prefix\s*=\s*['\"]([^'\"]+)['\"]/", $config_content, $prefix);
        $prefix = $prefix[1] ?? 'wp_';
        try {
            $stmt = $pdo->query("SELECT option_value FROM {$prefix}options WHERE option_name = 'stylesheet' LIMIT 1");
            if ($stmt) return $stmt->fetchColumn();
        } catch (Throwable $e) { }
    }
    return null;
}

function cb_delete_dir($dir) {
    if (!is_dir($dir)) return false;
    $items = scandir($dir);
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        $path = $dir . '/' . $item;
        is_dir($path) ? cb_delete_dir($path) : unlink($path);
    }
    return rmdir($dir);
}

// AJAX handler
if (!empty($action)) {
    header('Content-Type: application/json');
    echo json_encode($result);
    exit;
}

// Diagnostic: Get last error
$last_error = null;
$cb_error_log = CB_CONTENT . '/.crash-block-errors.json';
if (file_exists($cb_error_log)) {
    $errors = json_decode(file_get_contents($cb_error_log), true);
    if (!empty($errors)) $last_error = $errors[0];
}

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($_SERVER['HTTP_HOST'] ?? 'Site', ENT_QUOTES, 'UTF-8'); ?> - Recovery Panel</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        :root {
            --cb-orange: #ff8c32;
            --cb-blue: #2271b1;
            --cb-red: #d63638;
            --cb-green: #00a32a;
            --cb-dark: #23282d;
            --cb-shadow: 0 10px 40px rgba(0,0,0,0.15);
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: #f0f0f1;
            color: #3c434a;
            line-height: 1.5;
            padding: 40px 20px;
        }
        
        /* Modal Container Style */
        .modal-look {
            max-width: 1100px;
            margin: 0 auto;
            background: white;
            border-radius: 12px;
            box-shadow: var(--cb-shadow);
            overflow: hidden;
            border: 1px solid #ddd;
        }

        /* Header */
        .cb-header {
            background: white;
            padding: 30px;
            border-bottom: 4px solid var(--cb-orange);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .cb-header h1 {
            font-size: 26px;
            font-weight: 700;
            color: var(--cb-dark);
            margin: 0;
        }
        .cb-header-meta { font-size: 11px; color: #888; text-align: right; }

        /* Diagnostics Bar */
        .diagnostic-bar {
            padding: 20px 30px;
            background: #fdf2f2;
            border-bottom: 1px solid #f8d7da;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .diag-icon { font-size: 24px; color: var(--cb-red); }
        .diag-info { flex: 1; }
        .diag-title { font-weight: 700; color: var(--cb-red); font-size: 14px; margin-bottom: 2px; }
        .diag-desc { font-size: 12px; color: #555; }

        /* Main Content Grid */
        .cb-grid {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 30px;
            padding: 30px;
            background: #fff;
        }
        @media (max-width: 1000px) { .cb-grid { grid-template-columns: 1fr; } }

        .cb-col h2 {
            font-size: 16px;
            font-weight: 700;
            margin-bottom: 20px;
            color: var(--cb-dark);
            display: flex;
            align-items: center;
            gap: 8px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f0f0f1;
        }

        /* Status Items */
        .status-item {
            background: #f9f9f9;
            padding: 15px;
            border-radius: 6px;
            border: 1px solid #eee;
            margin-bottom: 15px;
        }
        .status-item strong { display: block; font-size: 13px; margin-bottom: 5px; }
        .status-item p { font-size: 11px; color: #666; }
        
        .badge {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            margin-top: 5px;
        }
        .badge-success { background: #e7f6e7; color: var(--cb-green); }
        .badge-error { background: #fbeaea; color: var(--cb-red); }
        .badge-warning { background: #fff8e5; color: #996800; }

        /* Buttons */
        .btn {
            display: block;
            width: 100%;
            padding: 12px;
            border: none;
            border-radius: 4px;
            font-size: 13px;
            font-weight: 600;
            text-align: center;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.2s;
            margin-bottom: 10px;
        }
        .btn-primary { background: var(--cb-blue); color: white; }
        .btn-primary:hover { background: #135e96; }
        .btn-orange { background: var(--cb-orange); color: white; }
        .btn-orange:hover { background: #e67e22; }
        .btn-danger { background: white; color: var(--cb-red); border: 1px solid var(--cb-red); }
        .btn-danger:hover { background: var(--cb-red); color: white; }
        .btn-secondary { background: #f0f0f1; color: #3c434a; border: 1px solid #ccc; }
        .btn-secondary:hover { background: #e0e0e0; }

        /* Log View */
        .log-box {
            background: #1e1e1e;
            color: #d4d4d4;
            padding: 15px;
            border-radius: 4px;
            font-family: "Courier New", monospace;
            font-size: 11px;
            max-height: 200px;
            overflow-y: auto;
            margin-top: 10px;
            border: 1px solid #333;
        }

        /* Iframe Preview */
        .preview-section {
            padding: 0 30px 30px;
            background: white;
        }
        .preview-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        .preview-title { font-weight: 700; font-size: 14px; }
        .preview-frame {
            width: 100%;
            height: 400px;
            border: 1px solid #ddd;
            border-radius: 6px;
            background: #f9f9f9;
        }

        /* Footer */
        .cb-footer {
            padding: 20px 30px;
            background: #f9f9f9;
            border-top: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 12px;
            color: #888;
        }
    </style>
</head>
<body>

<div class="modal-look">
    <!-- Standardized Header -->
    <div class="cb-header">
        <h1>🛡️ <?php echo htmlspecialchars($_SERVER['HTTP_HOST'] ?? 'Site', ENT_QUOTES, 'UTF-8'); ?> Recovery Panel</h1>
        <div class="cb-header-meta">
            v<?php echo CB_VERSION; ?><br>
            Emergency Standalone Mode
        </div>
    </div>

    <!-- Crash Diagnostics -->
    <div class="diagnostic-bar" style="<?php echo $last_error ? '' : 'background:#f2faf2; border-color:#d4edda;'; ?>">
        <div class="diag-icon"><?php echo $last_error ? '⚠️' : '✅'; ?></div>
        <div class="diag-info">
            <div class="diag-title" style="<?php echo $last_error ? '' : 'color:#155724;'; ?>">
                <?php echo $last_error ? 'FATAL ERROR DETECTED' : 'SYSTEM HEALTH: GOOD'; ?>
            </div>
            <div class="diag-desc">
                <?php 
                if ($last_error) {
                    echo '<strong>Reason:</strong> ' . htmlspecialchars($last_error['message']) . '<br>';
                    echo '<strong>Source:</strong> ' . htmlspecialchars(basename($last_error['file'])) . ' on line ' . $last_error['line'];
                } else {
                    echo 'No critical errors found in recent logs. Your site should be loading normally.';
                }
                ?>
            </div>
        </div>
        <?php if ($last_error): ?>
            <button class="btn btn-primary" style="width:auto; padding:8px 15px;" onclick="location.reload()">Refresh Scan</button>
        <?php endif; ?>
    </div>

    <!-- 3-Column Recovery Content -->
    <div class="cb-grid">
        
        <!-- COLUMN 1: Site Status -->
        <div class="cb-col">
            <h2>📊 Site Status</h2>
            
            <?php
            $maintenance_file = CB_ROOT . '/.maintenance';
            $maintenance_active = file_exists($maintenance_file);
            ?>
            <div class="status-item" style="border-left:4px solid <?php echo $maintenance_active ? 'var(--cb-red)' : 'var(--cb-green)'; ?>;">
                <strong>Maintenance Mode</strong>
                <span class="badge <?php echo $maintenance_active ? 'badge-error' : 'badge-success'; ?>">
                    <?php echo $maintenance_active ? '● ON — Site shows "Briefly unavailable"' : '● OFF — Site is live'; ?>
                </span>
                <p style="margin-top:5px;">The WordPress .maintenance file controls whether visitors see a maintenance screen.</p>
                <div style="display:grid; gap:6px; margin-top:10px;">
                    <?php if ($maintenance_active): ?>
                        <button class="btn btn-primary" onclick="runAction('clear_maintenance')">Clear Maintenance Mode</button>
                    <?php else: ?>
                        <button class="btn btn-secondary" onclick="if(confirm('Put site into maintenance mode?')) runAction('set_maintenance')">Enable Maintenance Mode</button>
                    <?php endif; ?>
                </div>
            </div>

            <div class="status-item">
                <strong>🚨 Recent Fatal Errors</strong>
                <p>Fatal PHP errors intercepted by the handler.</p>
                <div id="errors-container" class="log-box" style="margin-top:10px; height:180px; font-family:monospace; font-size:11px; background:#fff5f5; border-color:#ffe3e3; padding:10px; overflow-y:auto; white-space:pre-wrap; border:1px solid #ddd; border-radius:4px;">Loading errors...</div>
                <button class="btn btn-secondary" onclick="loadLogs()" style="margin-top:8px; font-size:11px; padding:6px; display:inline-block; width:auto;">↺ Refresh Logs</button>
            </div>

            <div class="status-item">
                <strong>📋 System Actions & Activity Log</strong>
                <p>Logs of recent administrative actions.</p>
                <div id="actions-container" class="log-box" style="margin-top:10px; height:180px; font-family:monospace; font-size:11px; padding:10px; overflow-y:auto; white-space:pre-wrap; border:1px solid #ddd; border-radius:4px;">Loading activity...</div>
                <button class="btn btn-danger" onclick="if(confirm('Clear all logs?')) runAction('clear_logs')" style="margin-top:8px; font-size:11px; padding:6px; display:inline-block; width:auto; background:var(--cb-red); color:#fff; border:none;">Clear All Logs</button>
            </div>
            
            <div class="status-item" id="env-status-box" style="background: #fff8f0; border-color: #ffe4cc;">
                <strong>🌐 Environmental Status</strong>
                <div id="env-data" style="font-size: 11px; margin: 10px 0;">
                    <em>Loading environment data...</em>
                </div>
                <button class="btn btn-secondary" onclick="loadEnv()" style="margin-top:5px; font-size: 10px; padding: 5px;">Refresh Health</button>
            </div>
        </div>

        <!-- COLUMN 2: Healing Center -->
        <div class="cb-col">
            <h2>🛡️ Healing Center</h2>
            
            <div class="status-item">
                <strong>Safe Mode (Plugins)</strong>
                <p>If your site crashed today, disable "Recent Changes" first. If that fails, disable All.</p>
                <div style="display:grid; gap:8px;">
                    <button class="btn btn-orange" onclick="if(confirm('Disable only plugins activated in last 24 hours?')) runAction('disable_plugins', {scope:'today'})">Disable Today's Changes</button>
                    <button class="btn btn-danger" onclick="if(confirm('Disable ALL plugins?')) runAction('disable_plugins')">Disable ALL Plugins</button>
                    <button class="btn btn-primary" onclick="runAction('enable_plugins')">Re-Enable All</button>
                </div>
            </div>

            <div class="status-item" style="background:#fff8f0; border-color:#ffe4cc;">
                <strong>🚑 Rebuild Hub</strong>
                <p>Automated redownloading and restoration of the NetBound Hub ecosystem.</p>
                <button class="btn btn-orange" onclick="if(confirm('Download and rebuild NetBound Hub?')) runAction('reinstall_hub')">Rebuild Hub</button>
            </div>

            <div class="status-item">
                <strong>Heal functions.php</strong>
                <p>Restore the last working backup of your functions.php file.</p>
                <button class="btn btn-orange" onclick="if(confirm('Restore functions.php from backup?')) runAction('restore_functions')">Restore from Backup</button>
            </div>
        </div>

        <!-- COLUMN 3: Manual Fixes -->
        <div class="cb-col">
            <h2>🛠️ Manual Fixes</h2>
            
            <div class="status-item">
                <strong>Recent File Activity</strong>
                <p>Scans theme and plugin folders for changes in the last 72 hours.</p>
                <button class="btn btn-primary" onclick="loadRecentFiles()">Scan for Changes</button>
                <div id="recent-files-list" style="margin-top:10px; max-height:250px; overflow-y:auto; border:1px solid #eee; border-radius:4px; font-size:11px;"></div>
            </div>

            <div class="status-item">
                <strong>Plugin Manager</strong>
                <p>View and manage installed plugins manually.</p>
                <button class="btn btn-secondary" onclick="loadPlugins()" style="margin-top:8px;">List Plugins</button>
                <div id="plugin-list" style="margin-top:10px; max-height:250px; overflow-y:auto; border:1px solid #eee; border-radius:4px; font-size:11px;"></div>
            </div>

            <div class="status-item" style="border-left: 4px solid var(--cb-orange);">
                <strong>📤 Upload Plugin File</strong>
                <p>Send a replacement .php or .zip file directly to a plugin or theme folder.</p>
                <div style="margin-top:10px;">
                    <label style="font-size:11px; font-weight:700; display:block; margin-bottom:3px;">Destination Folder</label>
                    <input id="upload-dest" type="text" value="wp-content/plugins" style="width:100%; padding:5px; font-size:11px; border:1px solid #ddd; border-radius:3px; margin-bottom:8px;" placeholder="wp-content/plugins/my-plugin">
                    
                    <label class="btn btn-orange" style="display:inline-flex; align-items:center; justify-content:center; cursor:pointer; width:100%; margin-bottom:4px; height:34px; font-size:12px; font-weight:700; border-radius:4px; text-align:center;">
                        📂 Choose File
                        <input id="upload-file-input" type="file" style="display:none;" onchange="document.getElementById('upload-file-name').textContent = this.files[0] ? 'Selected: ' + this.files[0].name : '';">
                    </label>
                    <div id="upload-file-name" style="font-size:10px; color:#555; margin-bottom:8px; text-align:center; word-break:break-all; font-style:italic;">No file chosen</div>
                    
                    <button class="btn btn-primary" onclick="uploadPluginFile()" style="width:100%;">Upload File</button>
                    <div id="upload-status" style="margin-top:8px; font-size:11px; color:#555;"></div>
                </div>
            </div>

            <div class="status-item" style="border-left: 4px solid var(--cb-orange);">
                <strong>🚑 Environmental Lifeboat</strong>
                <p>Use these when theme/plugin fixes aren't enough.</p>
                <div style="display:grid; gap:8px; margin-top:10px;">
                    <button class="btn btn-secondary" onclick="if(confirm('This will rename your .htaccess and create a clean WordPress default. Proceed?')) runAction('reset_htaccess')">Reset .htaccess</button>
                    <button class="btn btn-secondary" onclick="if(confirm('Delete object-cache.php and advanced-cache.php?')) runAction('nuke_cache')">Flush Cache Files</button>
                    <button class="btn btn-orange" onclick="openConfigEditor()">Edit wp-config.php</button>
                </div>
            </div>
        </div>

    </div>

    <!-- Live Preview Section -->
    <div class="preview-section">
        <div class="preview-header">
            <span class="preview-title">Live Site Preview</span>
            <div style="display:flex; gap:10px;">
                <a href="/" target="_blank" class="btn btn-secondary" style="width:auto; margin:0; padding:5px 15px;">Open Site New Tab</a>
                <button class="btn btn-primary" onclick="document.getElementById('site-preview').src = document.getElementById('site-preview').src" style="width:auto; margin:0; padding:5px 15px;">Reload Preview</button>
            </div>
        </div>
        <iframe id="site-preview" src="/" class="preview-frame"></iframe>
    </div>

    <!-- Footer -->
    <div class="cb-footer">
        <div>NetBound System Recovery | Authorized Personnel Only</div>
        <div>
            <a href="?action=delete_self" style="color:var(--cb-red); text-decoration:none; font-weight:700;" onclick="return confirm('Delete this recovery page? Use this only once your site is fully fixed.')">Permanent Disposal (Self Destruct)</a>
        </div>
    </div>
</div>

<script>
    // Pathword™ 2.0: Scrub the real filename from the address bar.
    // The token IS the URL path — hide it after load so copy/paste shows a decoy.
    const cbRealFile = '<?php echo basename(__FILE__); ?>';
    (function() {
        var base = window.location.pathname.replace(/recovery-[a-f0-9]+\.php/, 'recovery.php');
        history.replaceState({}, document.title, base);
    })();

    async function runAction(action, params = {}) {
        const query = new URLSearchParams({ action, ...params }).toString();
        const res = await fetch(cbRealFile + '?' + query);
        const data = await res.json();
        if (data.success) {
            alert('✅ ' + data.message);
            location.reload();
        } else {
            alert('❌ ' + data.message);
        }
    }

    async function loadLogs() {
        const errorsBox = document.getElementById('errors-container');
        const actionsBox = document.getElementById('actions-container');
        if (errorsBox) errorsBox.innerHTML = 'Scanning logs...';
        if (actionsBox) actionsBox.innerHTML = 'Scanning logs...';
        const res = await fetch(cbRealFile + '?action=view_logs');
        const data = await res.json();
        if (data.success) {
            if (errorsBox) {
                const errorLines = data.logs['Crash Block Errors'] || [];
                errorsBox.innerHTML = errorLines.join('\n') || 'No fatal errors recorded.';
            }
            if (actionsBox) {
                const actionLines = data.logs['System Actions'] || [];
                actionsBox.innerHTML = actionLines.join('\n') || 'No system actions recorded.';
            }
        }
    }

    async function loadRecentFiles() {
        const res = await fetch(cbRealFile + '?action=list_recent');
        const data = await res.json();
        const list = document.getElementById('recent-files-list');
        if (data.success && data.files.length > 0) {
            list.innerHTML = data.files.map(f => `
                <div style="padding:10px; border-bottom:1px solid #eee; display:flex; justify-content:space-between; align-items:center; ${f.has_backup ? 'background:#f2faf2;' : ''}">
                    <div style="max-width:60%;">
                        <div style="font-weight:700; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
                            ${f.file.split(/[\\\/]/).pop()}
                            ${f.has_backup ? '<span class="badge badge-success" style="font-size:8px; vertical-align:middle; margin-left:5px;">✓ Protected</span>' : ''}
                        </div>
                        <div style="color:#888; font-size:10px;">${f.date}</div>
                        <div style="color:#aaa; font-size:9px; overflow:hidden; text-overflow:ellipsis;">/${f.file}</div>
                    </div>
                    <div style="display:flex; gap:5px;">
                        ${f.has_backup ? `<button class="btn btn-orange" style="width:auto; margin:0; padding:4px 8px; font-size:10px;" onclick="if(confirm('Restore this file from backup?')) runAction('restore_file', {file:'${f.file.replace(/\\/g, '/')}'})">Restore</button>` : ''}
                        <button class="btn btn-secondary" style="width:auto; margin:0; padding:4px 8px; font-size:10px;" onclick="alert('Path: /${f.file}')">Info</button>
                    </div>
                </div>
            `).join('');
        } else {
            list.innerHTML = '<p style="padding:15px; color:#999; text-align:center;">No files modified in last 72h.</p>';
        }
    }

    async function loadPlugins() {
        const res = await fetch(cbRealFile + '?action=list_plugins');
        const data = await res.json();
        const list = document.getElementById('plugin-list');
        if (data.success && data.plugins.length > 0) {
            list.innerHTML = data.plugins.map(p => `
                <div style="padding:8px 10px; border-bottom:1px solid #eee; display:flex; justify-content:space-between; align-items:center; ${p.disabled ? 'background:#fff8f0;' : ''}">
                    <div>
                        <span style="font-weight:700; font-size:11px;">${p.name.replace(/\.DISABLED(-\d+)?$/, '')}</span>
                        <span style="font-size:9px; color:${p.disabled ? '#d63638' : '#00a32a'}; font-weight:700; margin-left:6px;">${p.disabled ? '● DISABLED' : '● ACTIVE'}</span>
                        <div style="font-size:9px; color:#999;">${p.date}</div>
                    </div>
                    <div>
                        ${p.disabled ? `<button class="btn btn-primary" style="width:auto; margin:0; padding:4px 8px; font-size:10px;" onclick="if(confirm('Re-enable ${p.name}?')) runAction('enable_plugin', {plugin:'${p.name}'})">Re-enable</button>` : ''}
                        <button class="btn btn-danger" style="width:auto; margin:0; padding:4px 8px; font-size:10px; background:var(--cb-red); color:white; border:none;" onclick="if(confirm('Permanently delete ${p.name}?')) runAction('delete_plugin', {plugin:'${p.name}'})">Delete</button>
                    </div>
                </div>
            `).join('');
        } else {
            list.innerHTML = '<p style="padding:10px; color:#999;">No plugins found.</p>';
        }
    }

    async function loadEnv() {
        const div = document.getElementById('env-data');
        const res = await fetch(cbRealFile + '?action=get_env');
        const data = await res.json();
        if (data.success) {
            div.innerHTML = `
                <b>PHP:</b> ${data.php}<br>
                <b>Database:</b> <span style="color:${data.db === 'Connected' ? 'green' : 'red'}">${data.db}</span><br>
                <b>Memory:</b> ${data.memory}<br>
                <b>Disk Free:</b> ${data.disk}
            `;
        }
    }

    async function openConfigEditor() {
        const res = await fetch(cbRealFile + '?action=view_file&file=wp-config.php');
        const data = await res.json();
        if (data.success) {
            const newContent = prompt('Editing wp-config.php\n\nBE EXTREMELY CAREFUL. A mistake here will break your database connection.', data.content);
            if (newContent !== null && newContent !== data.content) {
                const form = new FormData();
                form.append('file', 'wp-config.php');
                form.append('content', newContent);
                const saveRes = await fetch(cbRealFile + '?action=save_file', { method: 'POST', body: form });
                const saveData = await saveRes.json();
                alert(saveData.message);
            }
        } else {
            alert('Could not open wp-config.php');
        }
    }

    async function uploadPluginFile() {
        const input = document.getElementById('upload-file-input');
        const dest = document.getElementById('upload-dest').value;
        const status = document.getElementById('upload-status');
        if (!input.files.length) { status.textContent = '⚠ Please select a file.'; return; }
        const form = new FormData();
        form.append('file', input.files[0]);
        form.append('dest', dest);
        status.textContent = 'Uploading...';
        const res = await fetch(cbRealFile + '?action=upload_file', { method: 'POST', body: form });
        const data = await res.json();
        status.style.color = data.success ? '#00a32a' : '#d63638';
        status.textContent = (data.success ? '✅ ' : '❌ ') + data.message;
    }

    // Auto-load on page open
    loadEnv();
    loadLogs();
</script>

</body>
</html>
PANEL
	);
}
