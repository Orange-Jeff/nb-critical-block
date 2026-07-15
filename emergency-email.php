<?php
/**
 * NB Crash Block — Recovery Beacon
 * Version: 1.1.0
 *
 * Lives at webroot: yoursite.com/recover.php
 * Auto-deployed by NB Crash Block on activation.
 *
 * When visited, it silently emails the admin their current
 * emergency panel URL (Pathword link). Displays only "OK".
 * No token, no form, no indication of what it does.
 *
 * Rate-limited: max 1 email per hour to prevent abuse.
 *
 * v1.1.0: Silent mode - no UI, no token, just "OK"
 * v1.0.0: Initial version with token form
 */

// ============================================================
// RATE LIMIT — max 1 email per hour
// ============================================================
$lockfile = __DIR__ . '/.recover-lock';
if (file_exists($lockfile) && (time() - filemtime($lockfile)) < 3600) {
    echo 'OK';
    exit;
}
@touch($lockfile);

// ============================================================
// LOCATE wp-config.php
// ============================================================
$wp_config = null;
if (file_exists(__DIR__ . '/wp-config.php')) {
    $wp_config = __DIR__ . '/wp-config.php';
} elseif (file_exists(dirname(__DIR__) . '/wp-config.php')) {
    $wp_config = dirname(__DIR__) . '/wp-config.php';
}
if (!$wp_config) { echo 'OK'; exit; }

// ============================================================
// PARSE DATABASE CREDENTIALS
// ============================================================
$cfg = file_get_contents($wp_config);

$db_name = $db_user = $db_pass = $db_host = null;
foreach (['DB_NAME','DB_USER','DB_PASSWORD','DB_HOST'] as $k) {
    if (preg_match("/define\s*\(\s*['\"]" . $k . "['\"]\s*,\s*['\"]([^'\"]*?)['\"]\s*\)/", $cfg, $m)) {
        ${strtolower($k)} = $m[1];
    }
}
$prefix = 'wp_';
if (preg_match('/\$table_prefix\s*=\s*[\'"]([^\'"]+)[\'"]/', $cfg, $pm)) {
    $prefix = $pm[1];
}
if (!$db_name || !$db_user) { echo 'OK'; exit; }

// ============================================================
// CONNECT AND FETCH
// ============================================================
$conn = @new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) { echo 'OK'; exit; }

$r = $conn->query("SELECT option_value FROM {$prefix}options WHERE option_name = 'crash_block_panel_filename' LIMIT 1");
$panel = $r ? $r->fetch_assoc()['option_value'] : null;

$r = $conn->query("SELECT option_value FROM {$prefix}options WHERE option_name = 'admin_email' LIMIT 1");
$email = $r ? $r->fetch_assoc()['option_value'] : null;

$r = $conn->query("SELECT option_value FROM {$prefix}options WHERE option_name = 'siteurl' LIMIT 1");
$url = $r ? $r->fetch_assoc()['option_value'] : '';

$r = $conn->query("SELECT user_login FROM {$prefix}users ORDER BY ID ASC LIMIT 1");
$user = $r ? $r->fetch_assoc()['user_login'] : '';

$conn->close();
if (!$panel || !$email) { echo 'OK'; exit; }

// ============================================================
// SEND
// ============================================================
$panel_url = $url . '/wp-content/plugins/nb-crash-block/' . $panel;
$login_url = $url . '/wp-login.php';

$body  = "NB CRASH BLOCK RECOVERY\n";
$body .= "=======================\n\n";
$body .= "EMERGENCY PANEL:\n{$panel_url}\n\n";
$body .= "WP Login: {$login_url}\n";
$body .= "Admin: {$user}\n";
$body .= "Site: {$url}\n\n";
$body .= "If functions.php is broken, use the emergency panel\n";
$body .= "or rename it via cPanel/SSH.\n\n";
$body .= date('Y-m-d H:i:s T') . "\n";

$headers = "From: NB Crash Block <noreply@" . parse_url($url, PHP_URL_HOST) . ">\r\n";
@mail($email, 'NB Recovery - ' . parse_url($url, PHP_URL_HOST), $body, $headers);

echo 'OK';
