<?php
/**
 * NB Crash Block Uninstall
 * Removes all files, options, and artifacts created by crash-block
 */
if (!defined('WP_UNINSTALL_PLUGIN')) {
	exit;
}

// ============================================================================
// STEP 1: Remove options and transients
// ============================================================================
delete_option('crash_block_version');
delete_option('crash_block_panel_filename');
delete_transient('crash_block_upgraded');
delete_transient('crash_block_activated');
delete_transient('crash_block_bootstrap_actions');

// ============================================================================
// STEP 2: Remove the standalone emergency panel from web root
// ============================================================================
$panel_filename = get_option('crash_block_panel_filename');
if ($panel_filename) {
	$panel_path = ABSPATH . $panel_filename;
	if (file_exists($panel_path)) {
		@unlink($panel_path);
	}
}

// ============================================================================
// STEP 3: Remove all log/data files from wp-content
// ============================================================================
$content_files = [
	'.crash-block-mu-log.txt',
	'.crash-block-errors.json',
	'.crash-block-actions.log',
	'.crash-block-url',
];
foreach ($content_files as $file) {
	$path = WP_CONTENT_DIR . '/' . $file;
	if (file_exists($path)) {
		@unlink($path);
	}
}

// ============================================================================
// STEP 4: Remove the MU plugin (most critical — runs on every page load)
// ============================================================================
$mu_file = WPMU_PLUGIN_DIR . '/crash-block-handler.php';
if (file_exists($mu_file)) {
	@unlink($mu_file);
	// Remove mu-plugins dir only if now empty
	if (is_dir(WPMU_PLUGIN_DIR)) {
		$remaining = array_diff(scandir(WPMU_PLUGIN_DIR), ['.', '..']);
		if (empty($remaining)) {
			@rmdir(WPMU_PLUGIN_DIR);
		}
	}
}

// ============================================================================
// STEP 5: Remove theme backup/snapshot files
// ============================================================================
$theme_dir = get_stylesheet_directory();
$theme_cleanup = [
	$theme_dir . '/functions.php.backup',
	$theme_dir . '/functions.php.snapshot',
];
foreach ($theme_cleanup as $file) {
	if (file_exists($file)) {
		@unlink($file);
	}
}

// ============================================================================
// STEP 6: Ecosystem Cleanup (Lightweight)
// ============================================================================
// We NO LONGER delete the dashboard here. It's too risky.
// Only remove the local version flag if it exists.
delete_option('nb_crash_block_version');


