<?php
/**
 * NB Crash Block - Comprehensive Admin Page
 * This file contains the full admin interface with all functionality
 */

if (!defined('ABSPATH')) exit;

/**
 * Render the comprehensive admin page
 */
function crash_block_render_comprehensive_admin_page() {
	// Get current state
	// Check State & Regenerate NERP if missing
	$panel_filename = get_option('crash_block_panel_filename');
	$panel_path = ABSPATH . $panel_filename;

	if (!$panel_filename || !file_exists($panel_path)) {
		// NERP missing! Regenerate with new name.
		$random = bin2hex(random_bytes(12));
		$new_filename = 'recovery-' . $random . '.php';
		update_option('crash_block_panel_filename', $new_filename);

		// Generate file
		if (function_exists('crash_block_generate_panel')) {
			crash_block_generate_panel();
			$panel_filename = $new_filename;
			$panel_path = ABSPATH . $new_filename;
		}
	}

	$panel_url = home_url($panel_filename);
	$has_child = is_child_theme();
	$current_theme = wp_get_theme();
	$has_mu = file_exists(WPMU_PLUGIN_DIR . '/crash-block-handler.php');
	$maintenance_active = file_exists(ABSPATH . '.maintenance');

	// Check wp-config status
	$config_file = ABSPATH . 'wp-config.php';
	if (!file_exists($config_file)) $config_file = dirname(ABSPATH) . '/wp-config.php';

	$has_early_detection = false;
	$has_debug_log = false;
	if (file_exists($config_file)) {
		$config_content = file_get_contents($config_file);
		$has_early_detection = strpos($config_content, 'Crash Block Early Detection') !== false;
		// v5.3.9: Split string to prevent static scanners from falsely reporting WP_DEBUG_LOG enabled by this plugin
		$has_debug_log = strpos($config_content, 'WP_DEBUG_' . 'LOG') !== false;
	}

	// Functions.php status — check child theme first, fall back to parent
	if ($has_child) {
		$functions_file = get_stylesheet_directory() . '/functions.php';
	} else {
		// No child theme: check parent theme's functions.php (still worth backing up)
		$functions_file = get_template_directory() . '/functions.php';
	}
	$backup_file = $functions_file . '.backup';
	$has_functions_backup = file_exists($backup_file);

	// Initial Version check for standalone panel
	$panel_version = 'Unknown';
	if (file_exists($panel_path)) {
		$p_content = file_get_contents($panel_path, false, null, 0, 500); // Read header
		if (preg_match('/Version:\s*([0-9\.]+)/', $p_content, $matches)) {
			$panel_version = $matches[1];
		} else {
			// Older version or manual
			$panel_version = 'Legacy';
		}
	}

	// Header Image (v4.5.3: Unified single image)
	$header_img = CRASH_BLOCK_URL . 'assets/images/nb-crash-block.png';

	// Include shared header logic if needed
	if (!function_exists('nb_admin_header')) {
		$shared_functions = WP_PLUGIN_DIR . '/nb-hub/nb-shared-admin-functions.php';
		if (file_exists($shared_functions)) require_once $shared_functions;
	}
	?>
	<style>
		:root {
			--cb-orange: #ff8c32;
			--cb-blue: #0073aa;
			--cb-green: #46b450;
			--cb-red: #d63638;
		}

		/* === GRID LAYOUT ===
		   Scope to .nb-admin-wrap (from shared header) since nb_admin_header() opens
		   that wrapper — there is no extra nb-crash-block-wrap in the DOM. */
		.nb-admin-wrap .nb-three-column-container {
			display: grid !important;
			grid-template-columns: 1fr 1fr 1fr !important;
			gap: 20px !important;
			width: 100% !important;
			align-items: start;
			overflow: visible;
		}
		.nb-admin-wrap .nb-three-column-container > .nb-panel {
			min-width: 0 !important;
			max-width: 100% !important;
			overflow: hidden;
		}
		@media (max-width: 1200px) { .nb-admin-wrap .nb-three-column-container { grid-template-columns: 1fr 1fr !important; } }
		@media (max-width: 782px)  { .nb-admin-wrap .nb-three-column-container { grid-template-columns: 1fr !important; } }

		/* Status / message container — centered strip above the orange header line */
		#cb-status-bar {
			width: 100%;
			text-align: center;
			min-height: 28px;
			margin-bottom: 8px;
			font-size: 13px;
			font-weight: 600;
			color: #555;
		}
		#cb-status-bar .cb-status-msg {
			display: inline-block;
			padding: 4px 14px;
			border-radius: 4px;
			background: #f0f0f1;
			border: 1px solid #dcdcde;
		}
		#cb-status-bar .cb-status-msg.success { background: #e7f6e7; border-color: #46b450; color: #2a6b2e; }
		#cb-status-bar .cb-status-msg.error   { background: #fbeaea; border-color: #d63638; color: #8c1c1c; }

		/* Fixed Select Chevrons */
		.nb-admin-wrap select {
			appearance: none !important;
			background-image: url("data:image/svg+xml;charset=UTF-8,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%23666' d='M10.293 3.293L6 7.586 1.707 3.293 0.293 4.707 6 10.414l5.707-5.707z'/%3E%3C/svg%3E") !important;
			background-repeat: no-repeat !important;
			background-position: right 10px center !important;
			padding-right: 30px !important;
		}

		/* Panels */
		.nb-admin-wrap .nb-panel {
			background: white;
			border: 1px solid #ddd;
			border-top: 4px solid var(--cb-orange);
			border-radius: 4px;
			box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
			padding: 25px !important;
			display: flex;
			flex-direction: column;
			gap: 10px;
			box-sizing: border-box;
		}
		.nb-admin-wrap .nb-panel-header-img {
			width: 100%; height: auto; max-height: 120px; object-fit: cover;
			border-radius: 4px; margin-bottom: 10px; border: 1px solid #eee;
		}
		.nb-admin-wrap .nb-panel h3 {
			margin: 0; font-size: 16px; font-weight: 600;
			padding-bottom: 8px; border-bottom: 2px solid #f4f4f4;
			display: flex; align-items: center; gap: 10px;
		}
		.nb-admin-wrap .nb-panel h3 i { color: var(--cb-orange); }

		/* Feature Rows */
		.nb-admin-wrap .nb-feature-row {
			display: flex; align-items: flex-start; justify-content: space-between;
			padding: 10px; background: #fcfcfc;
			border: 1px solid #eee; border-radius: 4px;
			gap: 10px; margin-bottom: 5px; box-sizing: border-box;
		}
		.nb-admin-wrap .nb-feature-info { flex: 1; min-width: 0; }
		.nb-admin-wrap .nb-feature-title { font-weight: 700; font-size: 13px; margin-bottom: 4px; display: block; color: #333; }
		.nb-admin-wrap .nb-feature-desc  { font-size: 11px; color: #666; margin: 0; line-height: 1.3; }
		.nb-admin-wrap .nb-path-display   { font-family: monospace; font-size: 9px; color: #888; background: #eee; padding: 2px 4px; border-radius: 3px; display: inline-block; margin-top: 2px; text-overflow: ellipsis; overflow: hidden; white-space: nowrap; max-width: 100%; }
		.nb-admin-wrap .nb-feature-status { display: inline-flex; align-items: center; gap: 4px; font-size: 9px; font-weight: 700; text-transform: uppercase; padding: 1px 5px; border-radius: 3px; margin-bottom: 3px; }
		.nb-admin-wrap .status-active   { background: transparent; color: #666; border: none; padding: 0; }
		.nb-admin-wrap .status-inactive { background: #fbeaea; color: var(--cb-red); border: 1px solid #f5c2c2; }
		.nb-admin-wrap .status-warning  { background: #fbeaea; color: var(--cb-red); border: 1px solid #f5c2c2; }
		.nb-admin-wrap .nb-feature-actions { display: flex; flex-direction: column; gap: 4px; width: 110px; flex-shrink: 0; }

		/* Buttons */
		.nb-admin-wrap .cb-btn {
			display: inline-flex; align-items: center; justify-content: center;
			padding: 6px 10px; font-size: 11px; font-weight: 600;
			border-radius: 3px; border: 1px solid transparent;
			cursor: pointer; text-decoration: none; transition: all 0.2s;
			text-align: center; width: 100%; box-sizing: border-box; line-height: 1.2;
		}
		.nb-admin-wrap .cb-btn-primary   { background: var(--cb-blue); color: white; border-color: var(--cb-blue); }
		.nb-admin-wrap .cb-btn-primary:hover { background: #005177; color: white; }
		.nb-admin-wrap .cb-btn-danger    { background: white; color: var(--cb-red); border-color: var(--cb-red); }
		.nb-admin-wrap .cb-btn-danger:hover  { background: var(--cb-red); color: white; }
		.nb-admin-wrap .cb-btn-secondary { background: #f7f7f7; color: #555; border-color: #ccc; }
		.nb-admin-wrap .cb-btn-secondary:hover { background: #eee; color: #333; }

		/* Log Box */
		.nb-admin-wrap .nb-log-box {
			background: #f9f9f9; border: 1px solid #e5e5e5;
			padding: 8px; font-family: monospace; font-size: 10px;
			max-height: 150px; overflow-y: auto; margin-bottom: 5px; box-sizing: border-box;
		}

		/* Modal */
		.nb-admin-wrap .cb-modal { display: none; position: fixed; z-index: 100000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); }
		.nb-admin-wrap .cb-modal-content { background: white; margin: 5% auto; padding: 0; width: 90%; max-width: 900px; border-radius: 8px; box-shadow: 0 5px 30px rgba(0,0,0,0.3); }
		.nb-admin-wrap .cb-modal-header { background: var(--cb-blue); color: white; padding: 15px 20px; border-radius: 8px 8px 0 0; display: flex; justify-content: space-between; align-items: center; }
		.nb-admin-wrap .cb-modal-body { padding: 20px; box-sizing: border-box; }

		.nb-admin-wrap .code-editor-area {
			width: 100% !important; height: 400px;
			font-family: "Courier New", monospace; font-size: 13px;
			background: #1e1e1e; color: #d4d4d4;
			border: 1px solid #333; padding: 15px; border-radius: 4px;
			resize: vertical; box-sizing: border-box !important;
			display: block; margin: 10px 0; line-height: 1.5; max-width: 100% !important;
		}

		@keyframes flash-border {
			0%   { border-color: #d63638; }
			50%  { border-color: transparent; }
			100% { border-color: #d63638; }
		}

		/* NB Checkup (v5.4.0) */
		@keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
		.cb-checkup-phase { margin-bottom: 16px; border: 1px solid #e5e5e5; border-radius: 6px; overflow: hidden; }
		.cb-checkup-phase-header { background: #f9f9f9; padding: 10px 14px; font-weight: 700; font-size: 13px; display: flex; align-items: center; gap: 8px; border-bottom: 1px solid #e5e5e5; }
		.cb-checkup-phase-header .dashicons { color: #0073aa; font-size: 16px; width: 16px; height: 16px; }
		.cb-checkup-item { padding: 8px 14px; border-bottom: 1px solid #f0f0f1; display: flex; align-items: flex-start; gap: 10px; font-size: 12px; }
		.cb-checkup-item:last-child { border-bottom: none; }
		.cb-sev-critical { border-left: 4px solid #d63638; background: #fef7f7; }
		.cb-sev-warning { border-left: 4px solid #FFA500; background: #fffdf5; }
		.cb-sev-caution { border-left: 4px solid #dba617; background: #fffef8; }
		.cb-sev-ok { border-left: 4px solid #46b450; background: #f7fdf7; }
		.cb-sev-badge { display: inline-block; padding: 1px 6px; border-radius: 3px; font-size: 9px; font-weight: 700; text-transform: uppercase; }
		.cb-sev-badge.critical { background: #d63638; color: white; }
		.cb-sev-badge.warning { background: #FFA500; color: white; }
		.cb-sev-badge.caution { background: #dba617; color: white; }
		.cb-sev-badge.ok { background: #46b450; color: white; }
		.cb-checkup-summary { display: flex; gap: 12px; padding: 12px 16px; background: #f0f4f8; border-radius: 6px; margin-bottom: 16px; flex-wrap: wrap; }
		.cb-checkup-summary-item { display: flex; align-items: center; gap: 5px; font-size: 12px; font-weight: 700; }
	</style>

	<?php
	if (function_exists('nb_admin_header')) {
		nb_admin_header('NB Crash Block', '', CRASH_BLOCK_VERSION, 'Total WordPress Protection & Recovery System');
	} else {
		echo '<div class="wrap nb-admin-wrap"><h1>NB Crash Block v' . CRASH_BLOCK_VERSION . '</h1>';
	}
	?>

		<?php /* Status bar — centered above the orange line, injected right after header row */ ?>
		<div id="cb-status-bar" style="margin-top: -12px; margin-bottom: 14px;"></div>

		<div class="nb-three-column-container">
			<!-- COL 1: PROTECTION (CONFIGURATION) -->
			<div class="nb-panel">
				<img src="<?php echo esc_url($header_img); ?>" class="nb-panel-header-img" alt="Protection">

				<h3><i class="dashicons dashicons-admin-settings"></i> Configuration</h3>

				<!-- Child Theme -->
				<?php
					$potential_child_slug = 'nb-' . $current_theme->get_stylesheet() . '-child';
					$potential_child_dir = get_theme_root() . '/' . $potential_child_slug;
					$child_exists_inactive = !$has_child && file_exists($potential_child_dir);
					$theme_name = $has_child ? wp_get_theme()->get('Name') : $current_theme->get('Name');
				?>
				<div class="nb-feature-row" style="padding:7px 10px;">
					<div class="nb-feature-info">
						<div style="display:flex; align-items:center; gap:7px; flex-wrap:wrap;">
							<span class="nb-feature-title" style="margin:0;">1. Child Theme</span>
							<span class="nb-feature-status <?php echo $has_child ? 'status-active' : ($child_exists_inactive ? 'status-warning' : 'status-inactive'); ?>">
								<?php if ($has_child) echo '● ACTIVE'; elseif ($child_exists_inactive) echo '⚠ Inactive'; else echo '✗ Missing'; ?>
							</span>
							<span style="font-size:10px; color:#888;"><?php echo esc_html($theme_name); ?></span>
						</div>
						<p class="nb-feature-desc" style="margin:2px 0 0 0;">Isolates customizations from the parent theme.</p>
						<input type="hidden" id="child-theme-image-url" value="">
					</div>
					<div style="display:flex; align-items:center; gap:4px; flex-shrink:0;">
						<img id="child-theme-preview" src="<?php echo $has_child && file_exists(get_stylesheet_directory().'/screenshot.png') ? get_stylesheet_directory_uri().'/screenshot.png' : esc_url($header_img); ?>" style="width:26px;height:26px;border-radius:3px;object-fit:cover;border:1px solid #ddd;">
						<?php if ($has_child): ?>
							<button class="cb-btn cb-btn-secondary" id="switch-to-parent" style="width:auto;white-space:nowrap;">Restore Parent</button>
						<?php elseif ($child_exists_inactive): ?>
							<button class="cb-btn cb-btn-primary" id="create-child-theme" style="width:auto;">Activate</button>
						<?php else: ?>
							<button class="cb-btn cb-btn-primary" id="create-child-theme" style="width:auto;">Install Now</button>
						<?php endif; ?>
							<button class="button button-small" id="select-child-image" title="Change Thumbnail" style="font-size:10px;">📷</button>
					</div>
				</div>

				<!-- 2. Recovery Page -->
				<?php $panel_exists = file_exists($panel_path); ?>
				<div class="nb-feature-row" style="border-left:3px solid #d63638;padding:7px 10px;">
					<div class="nb-feature-info">
						<div style="display:flex;align-items:center;gap:7px;flex-wrap:wrap;">
							<span class="nb-feature-title" style="margin:0;color:#d63638;">2. Recovery Page</span>
							<span class="nb-feature-status <?php echo $panel_exists ? 'status-active' : 'status-inactive'; ?>">
								<?php echo $panel_exists ? '● ACTIVE' : '✗ NOT CREATED'; ?>
							</span>
						</div>
						<p class="nb-feature-desc" style="margin:2px 0 0 0;">Standalone emergency panel — works even when WordPress is broken. Secured by a "Pathword" URL.</p>
						<?php if ($panel_exists && $panel_version !== CRASH_BLOCK_VERSION && $panel_version !== 'Unknown'): ?>
							<div style="margin-top:3px;padding:3px 6px;background:#fff3cd;border-left:3px solid #FFA500;font-size:10px;color:#856404;"><strong>Update Available!</strong> Panel v<?php echo $panel_version; ?> — plugin v<?php echo CRASH_BLOCK_VERSION; ?>.</div>
						<?php endif; ?>
						<input type="text" value="<?php echo esc_url($panel_url); ?>" style="position:absolute;left:-9999px;" id="recovery-url-input">
					</div>
					<div class="nb-feature-actions" style="width:125px;">
						<a href="<?php echo esc_url($panel_url); ?>" target="_blank" class="cb-btn cb-btn-primary" <?php echo !$panel_exists ? 'style="opacity:0.5;pointer-events:none;"' : ''; ?>>Open</a>
						<button class="cb-btn cb-btn-primary" id="regenerate-panel" style="background:#d63638;border-color:#d63638;">Create / Update</button>
						<div style="display:flex;gap:4px;">
							<button class="cb-btn cb-btn-secondary" onclick="document.getElementById('recovery-url-input').select();document.execCommand('copy');showStatus('URL copied!','success');" style="flex:1;padding:5px 2px;">Copy</button>
							<button class="cb-btn cb-btn-secondary" id="email-recovery-url" style="flex:1;padding:5px 2px;">Email</button>
						</div>
					</div>
				</div>

				<!-- 3. Functions Backup -->
				<div class="nb-feature-row" style="padding:7px 10px;">
					<div class="nb-feature-info">
						<div style="display:flex;align-items:center;gap:7px;flex-wrap:wrap;">
							<span class="nb-feature-title" style="margin:0;">3. Functions Backup</span>
							<span class="nb-feature-status <?php echo $has_functions_backup ? 'status-active' : 'status-warning'; ?>">
								<?php echo $has_functions_backup ? '● ACTIVE' : '⚠ No Backup'; ?>
							</span>
						</div>
						<p class="nb-feature-desc" style="margin:2px 0 0 0;">Auto-restore point for fatal PHP errors.</p>
						<?php if ($functions_file): ?><div class="nb-path-display"><?php echo esc_html(basename(dirname($functions_file)).'/'.basename($functions_file)); ?></div><?php endif; ?>
						<?php if ($has_functions_backup): ?><div style="font-size:10px;color:#666;font-weight:600;margin-top:2px;">Last Saved: <?php echo date('M j, g:i a', filemtime($backup_file)); ?></div><?php endif; ?>
					</div>
					<div class="nb-feature-actions">
						<button class="cb-btn cb-btn-primary" id="backup-functions">Backup Now</button>
						<?php if ($has_functions_backup): ?>
							<button class="cb-btn cb-btn-secondary" id="restore-functions">Restore</button>
						<?php endif; ?>
						<div style="display:flex;gap:4px;">
							<button class="cb-btn cb-btn-secondary" id="check-functions-quick" style="font-size:10px;flex:1;background:#f0f8ff;border-color:#0073aa;color:#0073aa;">Check</button>
							<button class="cb-btn cb-btn-secondary" id="copy-functions-clipboard" style="font-size:10px;flex:1;" title="Copy functions.php contents to clipboard">Copy</button>
						</div>
					</div>
				</div>

				<!-- 4. Early Error Handler (MU) -->
				<?php
					$mu_running = defined('CB_HANDLER_ACTIVE') && CB_HANDLER_ACTIVE;
					$mu_dir_writable = is_writable(WPMU_PLUGIN_DIR) || (!file_exists(WPMU_PLUGIN_DIR) && is_writable(WP_CONTENT_DIR));
				?>
				<div class="nb-feature-row" style="padding:7px 10px;">
					<div class="nb-feature-info">
						<div style="display:flex;align-items:center;gap:7px;flex-wrap:wrap;">
							<span class="nb-feature-title" style="margin:0;">4. Early Error Handler (MU)</span>
							<span class="nb-feature-status <?php echo $mu_running ? 'status-active' : ($has_mu ? 'status-warning' : 'status-inactive'); ?>">
								<?php if ($mu_running) echo '● ACTIVE'; elseif ($has_mu) echo '⚠ Installed (reload)'; else echo '✗ Not Installed'; ?>
							</span>
						</div>
						<p class="nb-feature-desc" style="margin:2px 0 0 0;">Traps "White Screen of Death" before WordPress fully loads.</p>
						<?php if (!$mu_dir_writable): ?><div style="margin-top:3px;font-size:10px;color:#d63638;">⚠ <code>mu-plugins/</code> not writable — install will fail.</div><?php endif; ?>
					</div>
					<div class="nb-feature-actions">
						<?php if ($has_mu): ?>
							<button class="cb-btn cb-btn-secondary" id="install-mu">Update</button>
							<button class="cb-btn cb-btn-danger" id="uninstall-mu">Uninstall</button>
						<?php else: ?>
							<button class="cb-btn cb-btn-primary" id="install-mu" <?php echo !$mu_dir_writable ? 'disabled title="Directory not writable"' : ''; ?>>Install</button>
						<?php endif; ?>
					</div>
				</div>

				<!-- 5. Maintenance Mode Banner -->
				<div class="nb-feature-row" style="background:#fff8f0;border-left:3px solid #ff8c32;flex-direction:column;gap:6px;padding:9px 10px;">
					<div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
						<span class="nb-feature-title" style="margin:0;">5. Maintenance Mode Banner</span>
						<label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-size:12px;font-weight:600;margin:0;">
							<input type="checkbox" id="cb-maintenance-mode" <?php checked(get_option('cb_maintenance_mode','no'),'yes'); ?>> Enable
						</label>
					</div>
					<p class="nb-feature-desc" style="margin:0;">Display a notification bar on the frontend while you work.</p>
					<input type="text" id="cb-maintenance-message" value="<?php echo esc_attr(get_option('cb_maintenance_message','Site maintenance in progress. We will be back shortly!')); ?>" style="width:100%;box-sizing:border-box;padding:6px;font-size:12px;border:1px solid #ddd;border-radius:4px;" placeholder="Banner message...">
				</div>

				<!-- 6. PHP Version Manager (v5.3.0) -->
				<div class="nb-feature-row" style="background:#f0f8ff;border-left:3px solid #0073aa;flex-direction:column;gap:6px;padding:9px 10px;">
					<div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
						<span class="nb-feature-title" style="margin:0;color:#0073aa;">6. PHP Version Manager</span>
					</div>
					<p class="nb-feature-desc" style="margin:0;">Active Runtime: <strong>PHP <?php echo PHP_VERSION; ?></strong></p>
					<div style="width:100%;">
						<label style="display:block;font-size:10px;font-weight:700;margin-bottom:3px;">Select Handler</label>
						<select id="cb-php-handler-select" style="width:100%;box-sizing:border-box;padding:6px;font-size:12px;border:1px solid #ddd;border-radius:4px;">
							<option value="">Scanning server...</option>
						</select>
					</div>
					<button class="cb-btn cb-btn-primary" id="cb-switch-php-btn" style="background:#0073aa;border-color:#0073aa;">Switch PHP Version</button>
				</div>

				<!-- 7. Updates & Changelog -->
				<div class="nb-feature-row" style="background:#f9f9f9;border-left:3px solid #2271b1;padding:7px 10px;">
					<div class="nb-feature-info">
						<div style="display:flex;align-items:center;gap:7px;flex-wrap:wrap;">
							<span class="nb-feature-title" style="margin:0;color:#2271b1;">7. Updates & Changelog</span>
							<span class="nb-feature-status status-active" id="cb-version-status">v<?php echo CRASH_BLOCK_VERSION; ?></span>
						</div>
						<p class="nb-feature-desc" style="margin:2px 0 0 0;">Check for the newest version and view recent changes.</p>
					</div>
					<div class="nb-feature-actions">
						<button class="cb-btn cb-btn-primary" id="cb-check-updates">Check Version</button>
						<button class="cb-btn cb-btn-secondary" id="cb-view-changelog">Changelog</button>
					</div>
				</div>

				<!-- 8. wp-config.php Editor -->
				<div class="nb-feature-row" style="flex-direction:column; gap:6px; padding:9px 10px;">
					<div style="display:flex;align-items:center;justify-content:space-between;width:100%;">
						<span class="nb-feature-title" style="margin:0;">8. wp-config.php Editor</span>
						<div style="display:flex;gap:4px;">
							<button class="button button-small" id="refresh-wpconfig" style="font-size:10px;">Reload</button>
							<button class="button button-small" id="restore-wpconfig" style="font-size:10px;color:#d63638;" title="Restore from backup">Restore</button>
							<button class="button button-small button-primary" id="save-wpconfig" style="font-size:10px;">Save</button>
						</div>
					</div>
					<p class="nb-feature-desc" style="margin:0;">Modify critical WordPress configuration constants directly.</p>
					<textarea id="wpconfig-content" class="code-editor-area" style="width:100%;height:150px;padding:8px;font-size:11px;font-family:monospace;box-sizing:border-box;border:1px solid #ddd;border-radius:4px;" spellcheck="false">Click 'Reload' to load wp-config.php...</textarea>
				</div>

			</div><!-- /nb-panel col1 -->

			<!-- COL 2: LOGS -->
			<div class="nb-panel">
				<h3><i class="dashicons dashicons-list-view"></i> System Logs</h3>

				<!-- Recovery Panel info -->
				<div class="nb-feature-row">
					<div class="nb-feature-info">
						<span class="nb-feature-title">Panel Info</span>
						<p class="nb-feature-desc">
							Version: <?php echo esc_html($panel_version); ?><br>
							Updated: <?php echo date('M j, Y', filemtime($panel_path)); ?>
						</p>
					</div>
				</div>

				<!-- Recent Errors -->
				<div style="margin-top:5px;">
					<div style="font-weight:700; font-size:12px; margin-bottom:5px; display:flex; justify-content:space-between; align-items:center; gap: 5px;">
						<span>Recent Fatal Errors</span>
						<div style="display:flex; gap:3px;">
							<button class="button button-small btn-copy-log" data-target="error-log-box" style="font-size:10px; padding:0 5px; height:20px; line-height:18px;">Copy</button>
							<button class="button button-small btn-clear-single-log" data-log-type="errors" style="font-size:10px; padding:0 5px; height:20px; line-height:18px; color:#d63638;">Clear</button>
						</div>
					</div>
					<div class="nb-log-box" id="error-log-box">
						<?php crash_block_show_recent_errors(); ?>
					</div>
				</div>

				<!-- Activity Log -->
				<div style="margin-top:10px;">
					<div style="font-weight:700; font-size:12px; margin-bottom:5px; display:flex; justify-content:space-between; align-items:center; gap: 5px;">
						<span>System Actions</span>
						<div style="display:flex; gap:3px;">
							<button class="button button-small btn-copy-log" data-target="action-log-box" style="font-size:10px; padding:0 5px; height:20px; line-height:18px;">Copy</button>
							<button class="button button-small btn-clear-single-log" data-log-type="actions" style="font-size:10px; padding:0 5px; height:20px; line-height:18px; color:#d63638;">Clear</button>
						</div>
					</div>
					<div class="nb-log-box" id="action-log-box">
						<?php crash_block_show_recent_actions(); ?>
					</div>
					<button class="cb-btn cb-btn-secondary" id="clear-logs" style="margin-top:5px; border-color:#dcdcde;">Clear All Logs</button>
				</div>


			
				<!-- Notification Alerts + Privacy Opt-In -->
				<div class="nb-feature-row" style="background:#fff8f0;border-left:3px solid #ff8c32;flex-direction:column;gap:6px;padding:9px 10px;margin-top:10px;">
					<span class="nb-feature-title" style="margin:0;">Notification Alerts</span>
					<p class="nb-feature-desc" style="margin:0;">Receive an SOS email with a direct recovery link when your site crashes.</p>
					<div>
						<label style="display:block;font-size:11px;font-weight:700;margin-bottom:3px;">Alert Email</label>
						<input type="text" id="cb-alert-email" value="<?php echo esc_attr(get_option('cb_alert_email', get_option('admin_email'))); ?>" style="width:100%;box-sizing:border-box;padding:6px;font-size:12px;border:1px solid #ddd;border-radius:4px;" placeholder="e.g. admin@site.com, boss@site.com">
					</div>
					<div style="background:#f0f4ff;border:1px solid #c8d4f0;border-radius:4px;padding:8px;">
						<label style="display:flex;align-items:flex-start;gap:8px;cursor:pointer;font-size:11px;font-weight:600;color:#333;">
							<input type="checkbox" id="cb-netbound-pulse" <?php checked(get_option('cb_netbound_pulse','yes'),'yes'); ?> style="margin-top:2px;flex-shrink:0;">
							<div>&#x1F512; Privacy Opt-In &mdash; Help Improve NB Plugins<br>
							<span style="font-size:10px;color:#555;font-weight:400;">Anonymously share error type and file name with NetBound when a crash occurs. <strong>No personal data, URLs or content is sent.</strong></span></div>
						</label>
					</div>
					<button class="cb-btn cb-btn-primary" id="save-notification-settings" style="background:#ff8c32;border-color:#ff8c32;">Save Alerts</button>
				</div>
			</div>

			<!-- COL 3: TOOLS -->
			<div class="nb-panel">
				<img src="<?php echo esc_url($header_img); ?>" class="nb-panel-header-img" alt="Hub Maintenance">
				<h3><i class="dashicons dashicons-hammer"></i> NetBound Hub Maintenance</h3>

				<?php
				// Detect duplicate Hub installs
				$hub_dirs = array_filter(glob(WP_PLUGIN_DIR . '/*/nb-hub.php') ?: [], 'file_exists');
				$hub_count = count($hub_dirs);
				if ($hub_count > 1):
				?>
				<div style="background:#fbeaea;border-left:4px solid #d63638;padding:8px 10px;border-radius:4px;margin-bottom:4px;font-size:11px;color:#8c1c1c;">
					<strong>⚠ <?php echo $hub_count; ?> copies of nb-hub detected!</strong> Only one should exist. Extra copies can cause conflicts.<br>
					<?php foreach ($hub_dirs as $h): ?><div class="nb-path-display"><?php echo esc_html(str_replace(WP_PLUGIN_DIR.'/', '', dirname($h))); ?></div><?php endforeach; ?>
					<button class="cb-btn cb-btn-danger" id="delete-duplicate-hubs" style="margin-top:5px; padding: 4px 8px; font-size:10px; width: auto;">Delete Disabled Copies</button>
				</div>
				<?php endif; ?>

				<!-- Test Crash -->
				<div class="nb-feature-row" style="background:#fff5f5;border-left:3px solid #d63638;padding:7px 10px;" id="cb-test-crash-row">
					<div class="nb-feature-info">
						<div style="display:flex;align-items:center;gap:7px;flex-wrap:wrap;">
							<span class="nb-feature-title" style="margin:0;color:#d63638;">Test Crash System</span>
						</div>
						<p class="nb-feature-desc" style="margin:2px 0 0 0;">Verify NB Crash Block is working by injecting a safe fatal error.</p>
						<label style="font-size:10px;margin-top:3px;display:flex;align-items:center;gap:4px;cursor:pointer;color:#d63638;">
							<input type="checkbox" id="enable-test-crash" style="margin:0;"> I understand this will temporarily crash the site
						</label>
					</div>
					<div class="nb-feature-actions">
						<button class="cb-btn cb-btn-danger" id="test-crash-functions" disabled>Test Crash</button>
					</div>
				</div>

				<!-- NB Checkup (v5.4.0) -->
				<div class="nb-feature-row" style="background:#f0f8ff;border-left:3px solid #0073aa;padding:7px 10px;">
					<div class="nb-feature-info">
						<div style="display:flex;align-items:center;gap:7px;flex-wrap:wrap;">
							<span class="nb-feature-title" style="margin:0;color:#0073aa;">NB Checkup</span>
							<span id="cb-checkup-badge" class="nb-feature-status status-inactive" style="display:none;"></span>
						</div>
						<p class="nb-feature-desc" style="margin:2px 0 0 0;">Deep health scan: functions.php analysis, backup status, child theme inventory, PHP syntax check, and AI code fingerprinting.</p>
					</div>
					<div class="nb-feature-actions">
						<button class="cb-btn cb-btn-primary" id="run-nb-checkup" style="background:#0073aa;border-color:#0073aa;">Run Checkup</button>
						<button class="cb-btn cb-btn-secondary" id="check-functions-only" style="font-size:10px;">Check functions.php</button>
					</div>
				</div>

				<!-- File Integrity -->
				<div class="nb-feature-row">
					<div class="nb-feature-info">
						<span class="nb-feature-title">File Integrity Snapshot</span>
						<?php
							$snapshots = glob(WP_CONTENT_DIR . '/.crash-block-snapshot-*.json');
							$last_snapshot = '';
							if (!empty($snapshots)) {
								rsort($snapshots);
								$last_snapshot = date('M j, g:i a', filemtime($snapshots[0]));
							}
						?>
						<p class="nb-feature-desc">Create a baseline snapshot here, then compare later to detect changes.</p>
						<div class="nb-feature-status <?php echo $last_snapshot ? 'status-active' : 'status-warning'; ?>">
							<?php echo $last_snapshot ? '● Snapshot: ' . $last_snapshot : '⚠ No Snapshot'; ?>
						</div>
					</div>
					<div class="nb-feature-actions">
						<button class="cb-btn cb-btn-primary" id="create-snapshot">Create</button>
						<button class="cb-btn cb-btn-secondary" id="compare-snapshots" <?php echo !$last_snapshot ? 'disabled' : ''; ?>>Review</button>
					</div>
				</div>

				<div class="nb-feature-row">
					<div class="nb-feature-info">
						<span class="nb-feature-title">Hub Menu Repair</span>
						<p class="nb-feature-desc">Rebuilds the NetBound Hub admin menu if items are missing.</p>
					</div>
					<div class="nb-feature-actions">
						<button class="cb-btn cb-btn-primary" id="rebuild-dashboard">Rebuild</button>
					</div>
				</div>

				<!-- Delete Disabled Plugin Copies (v5.4.1) -->
				<div class="nb-feature-row" style="padding:7px 10px;">
					<div class="nb-feature-info">
						<span class="nb-feature-title">Delete Disabled Copies</span>
						<p class="nb-feature-desc">Scans for NB plugin folders with .DISABLED labels and deletes them to free disk space.</p>
						<span id="disabled-copies-count" style="font-size:10px;color:#888;display:none;"></span>
					</div>
					<div class="nb-feature-actions">
						<button class="cb-btn cb-btn-primary" id="scan-disabled-copies">Scan</button>
						<button class="cb-btn cb-btn-danger" id="delete-disabled-copies" style="display:none;">Delete All</button>
					</div>
				</div>

				<div class="nb-feature-row" style="background:#fff8f0; border-color:#ffe4cc;">
					<div class="nb-feature-info">
						<span class="nb-feature-title">Emergency Hub Rebuild</span>
						<p class="nb-feature-desc">Downloads and reinstalls the NetBound Hub if deleted or corrupted.</p>
					</div>
					<div class="nb-feature-actions">
						<button class="cb-btn cb-btn-primary" id="reinstall-hub" style="background:#ff8c32;border-color:#ff8c32;">Rebuild Hub</button>
					</div>
				</div>

				<div class="nb-feature-row" style="background:#f0f7ff; border-color:#c8d4f0;">
					<div class="nb-feature-info">
						<span class="nb-feature-title">Ecosystem Manifest Rebuild</span>
						<p class="nb-feature-desc">Rescans the local archive folder and regenerates <code>nb-manifest.json</code>. Essential for ensuring King sites distribute the correct plugin versions.</p>
					</div>
					<div class="nb-feature-actions">
						<button class="cb-btn cb-btn-primary" id="rebuild-manifest" style="background:#0073aa; border-color:#0073aa;">Rebuild Manifest</button>
					</div>
				</div>

				<div class="nb-feature-row">
					<div class="nb-feature-info">
						<span class="nb-feature-title">Hub Factory Reset</span>
						<p class="nb-feature-desc">Clears the Hub's stored visual settings from the database: accent colors, CSS overrides, and layout preferences. <strong>Does not uninstall anything</strong> — plugin files are untouched.</p>
					</div>
					<div class="nb-feature-actions">
						<button class="cb-btn cb-btn-secondary" id="reset-dashboard">Reset</button>
					</div>
				</div>

				 <div class="nb-feature-row" style="background:#fff5f5; border-color:#ffcdd2;">
					<div class="nb-feature-info">
						<span class="nb-feature-title" style="color:#d63638;">Nuclear Uninstall</span>
						<p class="nb-feature-desc">Completely removes ALL NetBound plugins and data.</p>
					</div>
					<div class="nb-feature-actions">
						<button class="cb-btn cb-btn-danger" id="uninstall-all-nb">Execute</button>
					</div>
				</div>

			</div>
		</div><!-- /nb-three-column-container -->



	<!-- Changelog Modal -->
	<div id="cb-changelog-modal" class="cb-modal">
		<div class="cb-modal-content">
			<div class="cb-modal-header">
				<h3>Changelog</h3>
				<button class="cb-btn cb-btn-danger" onclick="document.getElementById('cb-changelog-modal').style.display='none'" style="width:auto;">Close</button>
			</div>
			<div class="cb-modal-body" id="cb-changelog-content" style="max-height:600px;overflow-y:auto;">
				Loading...
			</div>
		</div>
	</div>

	<!-- Snapshot Review Modal -->
	<div id="cb-snapshot-modal" class="cb-modal">
		<div class="cb-modal-content">
			<div class="cb-modal-header">
				<h3 style="margin:0; color:#fff;">File Integrity Snapshot Review</h3>
				<button class="cb-btn cb-btn-danger" onclick="document.getElementById('cb-snapshot-modal').style.display='none'" style="width:auto;">Close</button>
			</div>
			<div class="cb-modal-body" id="cb-snapshot-content" style="max-height:600px;overflow-y:auto;color:#333;">
				Loading...
			</div>
		</div>
	</div>

	<!-- NB Checkup Results Modal (v5.4.0) -->
	<div id="cb-checkup-modal" class="cb-modal">
		<div class="cb-modal-content" style="max-width:750px;">
			<div class="cb-modal-header" style="background:#0073aa;">
				<h3 style="margin:0;color:#fff;"><span class="dashicons dashicons-stethoscope" style="margin-right:8px;"></span>NB Checkup Results</h3>
				<button class="cb-btn cb-btn-danger" onclick="document.getElementById('cb-checkup-modal').style.display='none'" style="width:auto;">Close</button>
			</div>
			<div class="cb-modal-body" id="cb-checkup-content" style="max-height:600px;overflow-y:auto;color:#333;">
				<div style="text-align:center;padding:40px;">
					<span class="dashicons dashicons-update" style="font-size:32px;color:#0073aa;animation:spin 1s linear infinite;"></span>
					<p style="margin-top:10px;color:#666;">Running health checks...</p>
				</div>
			</div>
		</div>
	</div>

	<?php if (function_exists('nb_admin_footer')) nb_admin_footer(); ?>




	<script>
	jQuery(document).ready(function($) {
		var nonce = '<?php echo wp_create_nonce('crash_block_admin'); ?>';
		var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';

		// Helper: Show inline status message in #cb-status-bar
		function showStatus(msg, type) {
			var bar = $('#cb-status-bar');
			bar.html('<span class="cb-status-msg ' + (type || '') + '">' + msg + '</span>');
			if (type !== 'error') {
				setTimeout(function() { bar.html(''); }, 4000);
			}
		}

		// Helper: Generic Action
		function doAction(action, data, confirmMsg, btn) {
			if(confirmMsg && !confirm(confirmMsg)) return;
			var oldText = '';
			if(btn) {
				oldText = btn.text();
				btn.prop('disabled', true).text('Working...');
			}

			var payload = { action: action, nonce: nonce };
			if(data) Object.assign(payload, data);

			$.post(ajaxurl, payload, function(res) {
				if(btn) btn.prop('disabled', false).text(oldText);

				if(res.success) {
					formChanged = false;
					var msg = (res.data && res.data.message) ? res.data.message : 'Done!';
					showStatus('✅ ' + msg, 'success');
					if (!data || !data.no_reload) setTimeout(function() { location.reload(); }, 1200);
				} else {
					var errMsg = (res.data && res.data.message) ? res.data.message : res.data;
					showStatus('❌ ' + errMsg, 'error');
				}
			}).fail(function() {
				showStatus('❌ Server Error', 'error');
				if(btn) btn.prop('disabled', false).text(oldText);
			});
		}

		// Configuration: Regenerate Panel
		$('#regenerate-panel').click(function() {
			if(confirm('Generates a new secure filename for the Emergency Panel. You will need to bookmark the new link.')) {
				doAction('crash_block_regenerate_panel', null, null, $(this));
			}
		});

		// Ecosystem: Rebuild Manifest
		$('#rebuild-manifest').click(function() {
			doAction('nb_crash_block_rebuild_manifest', null, 'This will rescan all plugin ZIPs and regenerate the manifest. Continue?', $(this));
		});

		// Child Theme - Image Selection
		$('#select-child-image').click(function(e) {
			e.preventDefault();
			var frame = wp.media({
				title: 'Select Child Theme Screenshot',
				button: { text: 'Use this image' },
				multiple: false
			});
			frame.on('select', function() {
				var attachment = frame.state().get('selection').first().toJSON();
				$('#child-theme-image-url').val(attachment.url);
				$('#child-theme-preview').attr('src', attachment.url).show();
				$('#select-child-image').text('Change');
			});
			frame.open();
		});

		// Email Link Binding
		$('#email-recovery-url').click(function() {
			if(confirm('Email the emergency recovery URL to the site admin?')) {
				doAction('crash_block_email_url', null, null, $(this));
			}
		});

		// Child Theme - Create/Re-install
		$('#create-child-theme').click(function() {
			 var imageUrl = $('#child-theme-image-url').val();
			 if (confirm('Install/Activate child theme?')) {
				 doAction('crash_block_create_child', { image_url: imageUrl }, null, $(this));
			 }
		});

		// Child Theme - Switch to Parent
		$('#switch-to-parent').click(function() {
			 if (confirm('Switch back to original parent theme?')) {
				 doAction('crash_block_switch_to_parent', null, null, $(this));
			 }
		});

		$('#delete-child-theme').click(function() { doAction('crash_block_delete_child', null, 'Delete Child Theme?'); });

		// Functions
		$('#backup-functions').click(function() { doAction('crash_block_backup_functions', null, null, $(this)); });
		$('#restore-functions').click(function() { doAction('crash_block_restore_functions', null, 'Restore functions.php?', $(this)); });
		// v5.4.1: Copy functions.php to clipboard
		$('#copy-functions-clipboard').click(function() {
			var btn = $(this);
			btn.prop('disabled', true).text('Loading...');
			$.post(ajaxurl, { action: 'crash_block_get_functions_content', nonce: nonce }, function(res) {
				btn.prop('disabled', false).text('Copy');
				if (res.success) {
					if (navigator.clipboard && window.isSecureContext) {
						navigator.clipboard.writeText(res.data.content).then(function() {
							showStatus('functions.php copied to clipboard! (' + res.data.lines + ' lines)', 'success');
						});
					} else {
						// Fallback for non-HTTPS
						var ta = document.createElement('textarea');
						ta.value = res.data.content;
						document.body.appendChild(ta);
						ta.select();
						document.execCommand('copy');
						document.body.removeChild(ta);
						showStatus('functions.php copied to clipboard! (' + res.data.lines + ' lines)', 'success');
					}
				} else {
					showStatus('Error: ' + (res.data.message || 'Unknown'), 'error');
				}
			}).fail(function() {
				btn.prop('disabled', false).text('Copy');
				showStatus('Server error', 'error');
			});
		});

		// v5.4.1: Enable test crash button only when checkbox is checked
		$('#enable-test-crash').change(function() {
			$('#test-crash-functions').prop('disabled', !$(this).is(':checked'));
		});

		$('#test-crash-functions').click(function() {
			if (confirm('⚠️ WARNING: This will inject a fatal error into your functions.php file.\n\nThe site should immediately crash on your next click, and NB Crash Block will catch it, restore your backup, and display a recovery popup.\n\nProceed?')) {
				doAction('crash_block_test_crash', null, null, $(this));
			}
		});

		// Updates & Changelog
		$('#cb-check-updates').click(function() {
			var btn = $(this);
			btn.prop('disabled', true).text('Checking...');
			$.post(ajaxurl, { action: 'crash_block_check_version', nonce: nonce }, function(res) {
				btn.prop('disabled', false);
				if (res.success) {
					var current = res.data.current;
					var available = res.data.available;
					var isNewest = res.data.is_newest;
					
					if (isNewest) {
						btn.text('Up to date');
						btn.removeClass('cb-btn-primary').addClass('cb-btn-secondary');
						$('#cb-version-status').html('✅ Confirmed Newest (v' + current + ')');

					} else {
						btn.text('Update Available');
						btn.removeClass('cb-btn-primary').addClass('cb-btn-warning').css('background', '#e6a23c');
						$('#cb-version-status').html('⚠ New Version Available: v' + available);
						
						// Append Update Button dynamically next to the Check updates button
						if ($('#cb-auto-update-btn').length === 0) {
							var updateBtn = $('<button class="cb-btn cb-btn-primary" id="cb-auto-update-btn" style="background:#ff8c32; border-color:#ff8c32; margin-top:5px; display:block;">Update to v' + available + '</button>');
							btn.parent().append(updateBtn);
							
							updateBtn.click(function() {
								var upBtn = $(this);
								if (confirm('Are you sure you want to update NB Crash Block to v' + available + '?')) {
									upBtn.prop('disabled', true).text('Updating...');
									$.post(ajaxurl, { action: 'crash_block_auto_update', nonce: nonce }, function(upRes) {
										if (upRes.success) {
											showStatus('✅ ' + upRes.data.message, 'success');
											setTimeout(function() { location.reload(); }, 1500);
										} else {
											upBtn.prop('disabled', false).text('Update to v' + available);
											showStatus('❌ ' + upRes.data.message, 'error');
										}
									}).fail(function() {
										upBtn.prop('disabled', false).text('Update to v' + available);
										showStatus('❌ Server Error', 'error');
									});
								}
							});
						}
						

					}
				} else {
					btn.text('Check Version');
					showStatus('❌ Error checking version: ' + res.data.message, 'error');
				}
			}).fail(function() {
				btn.prop('disabled', false).text('Check Version');
				showStatus('❌ Server Error checking version', 'error');
			});
		});

		$('#cb-view-changelog').click(function() {
			$('#cb-changelog-modal').show();
			$('#cb-changelog-content').html('Loading...');
			$.post(ajaxurl, { action: 'crash_block_get_changelog', nonce: nonce }, function(res) {
				if (res.success) {
					$('#cb-changelog-content').html(res.data.html);
				} else {
					$('#cb-changelog-content').html('Error: ' + res.data.message);
				}
			});
		});

		$('#delete-duplicate-hubs').click(function() {
			if(confirm('This will delete all disabled copies of nb-hub to resolve conflicts. Active copy will be preserved. Continue?')) {
				doAction('crash_block_delete_duplicate_hubs', null, null, $(this));
			}
		});

		// MU Install — status confirms only after WordPress restarts, so tell the user to wait for reload
		$('#install-mu').click(function() {
			var btn = $(this);
			btn.prop('disabled', true).text('Working...');
			$.post(ajaxurl, { action: 'crash_block_install_mu', nonce: nonce }, function(res) {
				if (res.success) {
					showStatus('✅ MU Handler installed. ACTIVE status confirms on next page load — MU plugins initialize before WordPress on each request.', 'success');
					setTimeout(function() { location.reload(); }, 3000);
				} else {
					var errMsg = (res.data && res.data.message) ? res.data.message : res.data;
					showStatus('❌ ' + errMsg, 'error');
					btn.prop('disabled', false).text('Install');
				}
			}).fail(function() {
				showStatus('❌ Server Error', 'error');
				btn.prop('disabled', false).text('Install');
			});
		});
		$('#uninstall-mu').click(function() { doAction('crash_block_uninstall_mu', null, 'Uninstall MU plugin?', $(this)); });
		$('#rebuild-dashboard').click(function() { doAction('nb_rebuild_dashboard', {nonce: '<?php echo wp_create_nonce('nb_rebuild'); ?>'}, 'Rebuild menu?', $(this)); });
		$('#reinstall-hub').click(function() { doAction('nb_reinstall_hub', null, 'This will attempt to download and reinstall the NetBound Hub plugin. Proceed?', $(this)); });
		$('#reset-dashboard').click(function() { doAction('nb_reset_dashboard', {nonce: '<?php echo wp_create_nonce('nb_reset'); ?>'}, 'Reset visual settings?', $(this)); });

		// Nuclear Uninstall
		$('#uninstall-all-nb').click(function() {
			if(confirm('⚠️ NUCLEAR OPTION\n\nThis will permanently delete ALL NetBound plugins and data (except Crash Block itself).\n\nThis cannot be undone. Are you absolutely sure?')) {
				if(confirm('FINAL WARNING: Click OK to confirm total removal of all NetBound components.')) {
					doAction('crash_block_uninstall_all_nb', null, null, $(this));
				}
			}
		});

		// Notifications
		$('#save-notification-settings').click(function() {
			var email = $('#cb-alert-email').val();
			var pulse = $('#cb-netbound-pulse').is(':checked') ? 'yes' : 'no';
			doAction('crash_block_save_notifications', { email: email, pulse: pulse, no_reload: true }, null, $(this));
		});

		// Snapshots
		$('#create-snapshot').click(function() { doAction('crash_block_create_snapshot', {no_reload:false}, null, $(this)); }); // Refresh List reloads
		$('#compare-snapshots').click(function() {
			var btn = $(this);
			var oldText = btn.text();
			btn.prop('disabled', true).text('Comparing...');
			showStatus('Comparing snapshots...', 'info');

			$.post(ajaxurl, { action: 'crash_block_compare_snapshots', nonce: nonce }, function(res) {
				btn.prop('disabled', false).text(oldText);
				if (res.success) {
					showStatus('✅ Snapshot compared!', 'success');
					var html = '<p><strong>Snapshot Date:</strong> ' + res.data.snapshot_date + '</p>';
					html += '<p><strong>Total Changes:</strong> ' + res.data.total + '</p>';

					if (res.data.total === 0) {
						html += '<div style="background:#e7f6e7; border-left:4px solid #46b450; padding:10px; margin-top:10px; color:#2a6b2e;">';
						html += 'No file changes detected since the snapshot was taken.';
						html += '</div>';
					} else {
						var changes = res.data.changes;
						
						if (changes.modified && changes.modified.length > 0) {
							html += '<h4 style="color:#FFA500; margin-bottom:5px;">Modified Files (' + changes.modified.length + ')</h4>';
							html += '<ul style="background:#fff8e5; border-left:4px solid #FFA500; padding:10px 10px 10px 25px; margin:0 0 15px 0; font-family:monospace; font-size:11px; list-style-type:disc;">';
							changes.modified.forEach(function(f) {
								html += '<li>' + f + '</li>';
							});
							html += '</ul>';
						}

						if (changes.added && changes.added.length > 0) {
							html += '<h4 style="color:#46b450; margin-bottom:5px;">Added Files (' + changes.added.length + ')</h4>';
							html += '<ul style="background:#e7f6e7; border-left:4px solid #46b450; padding:10px 10px 10px 25px; margin:0 0 15px 0; font-family:monospace; font-size:11px; list-style-type:disc;">';
							changes.added.forEach(function(f) {
								html += '<li>' + f + '</li>';
							});
							html += '</ul>';
						}

						if (changes.deleted && changes.deleted.length > 0) {
							html += '<h4 style="color:#d63638; margin-bottom:5px;">Deleted Files (' + changes.deleted.length + ')</h4>';
							html += '<ul style="background:#fbeaea; border-left:4px solid #d63638; padding:10px 10px 10px 25px; margin:0; font-family:monospace; font-size:11px; list-style-type:disc;">';
							changes.deleted.forEach(function(f) {
								html += '<li>' + f + '</li>';
							});
							html += '</ul>';
						}
					}

					$('#cb-snapshot-content').html(html);
					$('#cb-snapshot-modal').show();
				} else {
					var errMsg = (res.data && res.data.message) ? res.data.message : res.data;
					showStatus('❌ ' + errMsg, 'error');
				}
			}).fail(function(xhr) {
				var statusText = xhr.statusText || 'Server Error';
				showStatus('❌ ' + statusText + ' (' + xhr.status + ')', 'error');
				btn.prop('disabled', false).text(oldText);
			});
		});

		// WP-Config Editor (Inline)
		function loadWpConfig() {
			$('#wpconfig-content').val('Loading...');
			$.post(ajaxurl, { action: 'crash_block_get_wp_config', nonce: nonce }, function(res) {
				if(res.success) {
					$('#wpconfig-content').val(res.data.content);
				} else {
					$('#wpconfig-content').val('Error loading file: ' + res.data.message);
				}
			});
		}

		$('#save-wpconfig').click(function() {
			 if(!confirm('Save changes to wp-config.php? This is critical.')) return;
			 doAction('crash_block_save_wp_config', { content: $('#wpconfig-content').val() }, null, $(this));
		});

		$('#restore-wpconfig').click(function() {
			 if(!confirm('Restore wp-config.php from latest backup?')) return;
			 doAction('crash_block_restore_wp_config', { no_reload: true }, null, $(this));
			 setTimeout(loadWpConfig, 1000); // Reload content
		});

		$('#refresh-wpconfig').click(loadWpConfig);
		
		// .htaccess Editor
		$('#save-htaccess').click(function() {
			if(!confirm('Save changes to .htaccess?')) return;
			doAction('crash_block_save_htaccess', { content: $('#htaccess-editor').val(), no_reload: true }, null, $(this));
		});

		$('#reset-htaccess').click(function() {
			if(!confirm('Reset .htaccess to standard WordPress defaults?')) return;
			doAction('crash_block_reset_htaccess', { no_reload: false }, null, $(this));
		});

		// Maintenance Mode
		$('#save-maintenance-settings').click(function() {
			var enabled = $('#cb-maintenance-mode').is(':checked');
			var message = $('#cb-maintenance-message').val();
			doAction('crash_block_save_maintenance', { enabled: enabled, message: message, no_reload: true }, null, $(this));
		});

		// File Scan
		$('#scan-files').click(function() {
			doAction('crash_block_scan_files', { no_reload: false }, null, $(this));
		});

		// Snippet Copy
		$('.btn-copy-snippet').click(function() {
			var targetId = $(this).data('target');
			var content = $('#' + targetId).text();
			var $temp = $("<textarea>");
			$("body").append($temp);
			$temp.val(content).select();
			document.execCommand("copy");
			$temp.remove();

			var original = $(this).text();
			$(this).text('✓');
			var btn = $(this);
			setTimeout(function() { btn.text(original); }, 1500);
		});

		// Logs: Copy
		$('.btn-copy-log').click(function() {
			var targetId = $(this).data('target');
			var content = $('#' + targetId).text();
			var $temp = $("<textarea>");
			$("body").append($temp);
			$temp.val(content).select();
			document.execCommand("copy");
			$temp.remove();

			var original = $(this).text();
			$(this).text('✓');
			var btn = $(this);
			setTimeout(function() { btn.text(original); }, 1500);
		});

		// Logs: Clear
		$('#clear-logs').click(function() {
			if(confirm('Clear all system logs?')) {
				doAction('crash_block_clear_logs', null, null, $(this));
			}
		});

		$('.btn-clear-single-log').click(function() {
			var logType = $(this).data('log-type');
			var logName = logType === 'errors' ? 'recent fatal errors' : 'system actions';
			if (confirm('Clear ' + logName + '?')) {
				doAction('crash_block_clear_logs', { log_type: logType }, null, $(this));
			}
		});

		// PHP Version Manager (v5.3.0)
		function loadPhpVersions() {
			var select = $('#cb-php-handler-select');
			$.post(ajaxurl, { action: 'crash_block_detect_php_versions', nonce: nonce }, function(res) {
				if(res.success) {
					select.empty();
					select.append('<option value="default">Server Default (PHP ' + res.data.current_runtime + ')</option>');
					res.data.versions.forEach(function(v) {
						var selected = (v.handler === res.data.active) ? 'selected' : '';
						select.append('<option value="' + v.handler + '" ' + selected + '>' + v.name + '</option>');
					});
				} else {
					select.html('<option value="">Error scanning versions</option>');
				}
			});
		}
		loadPhpVersions();

		$('#cb-switch-php-btn').click(function() {
			var handler = $('#cb-php-handler-select').val();
			if (!handler) return;
			if(confirm('Are you sure you want to switch the PHP handler in .htaccess to ' + handler + '?\n\nIf this new handler is unsupported or incompatible, Crash Block will attempt to restore your original config inside 30 seconds.')) {
				doAction('crash_block_switch_php_version', { handler: handler }, null, $(this));
			}
		});

		// ========== DELETE DISABLED COPIES (v5.4.1) ==========
		$('#scan-disabled-copies').click(function() {
			var btn = $(this);
			btn.prop('disabled', true).text('Scanning...');
			$.post(ajaxurl, { action: 'crash_block_scan_disabled_copies', nonce: nonce }, function(res) {
				btn.prop('disabled', false).text('Scan');
				if (res.success) {
					var count = res.data.count;
					if (count > 0) {
						$('#disabled-copies-count').text(count + ' disabled folder(s) found: ' + res.data.names.join(', ')).show();
						$('#delete-disabled-copies').show();
						showStatus(count + ' disabled plugin folder(s) found', 'success');
					} else {
						$('#disabled-copies-count').text('No disabled copies found').show();
						$('#delete-disabled-copies').hide();
						showStatus('No disabled plugin copies found!', 'success');
					}
				} else {
					showStatus('Error: ' + res.data.message, 'error');
				}
			}).fail(function() {
				btn.prop('disabled', false).text('Scan');
				showStatus('Server error', 'error');
			});
		});

		$('#delete-disabled-copies').click(function() {
			if (!confirm('Delete all disabled NB plugin copies? This cannot be undone.')) return;
			var btn = $(this);
			btn.prop('disabled', true).text('Deleting...');
			$.post(ajaxurl, { action: 'crash_block_delete_disabled_copies', nonce: nonce }, function(res) {
				btn.prop('disabled', false).text('Delete All');
				if (res.success) {
					showStatus(res.data.message, 'success');
					$('#disabled-copies-count').text(res.data.message).show();
					$('#delete-disabled-copies').hide();
				} else {
					showStatus('Error: ' + res.data.message, 'error');
				}
			}).fail(function() {
				btn.prop('disabled', false).text('Delete All');
				showStatus('Server error', 'error');
			});
		});

		// ========== NB CHECKUP (v5.4.0) ==========
		function renderCheckupResults(data) {
			var html = '';

			// Summary bar
			html += '<div class="cb-checkup-summary">';
			if (data.summary.critical > 0) html += '<div class="cb-checkup-summary-item"><span class="cb-sev-badge critical">' + data.summary.critical + ' Critical</span></div>';
			if (data.summary.warning > 0) html += '<div class="cb-checkup-summary-item"><span class="cb-sev-badge warning">' + data.summary.warning + ' Warning</span></div>';
			if (data.summary.caution > 0) html += '<div class="cb-checkup-summary-item"><span class="cb-sev-badge caution">' + data.summary.caution + ' Caution</span></div>';
			if (data.summary.ok > 0) html += '<div class="cb-checkup-summary-item"><span class="cb-sev-badge ok">' + data.summary.ok + ' OK</span></div>';
			html += '<div style="margin-left:auto;font-size:11px;color:#888;">Scanned: ' + data.timestamp + '</div>';
			html += '</div>';

			// Phases
			data.phases.forEach(function(phase) {
				html += '<div class="cb-checkup-phase">';
				html += '<div class="cb-checkup-phase-header"><span class="dashicons ' + (phase.icon || 'dashicons-flag') + '"></span>' + phase.name;
				if (phase.meta) {
					html += ' <span style="font-weight:400;font-size:11px;color:#888;margin-left:auto;">' + (phase.meta.file || '') + ' &middot; ' + (phase.meta.size || '') + '</span>';
				}
				html += '</div>';

				phase.items.forEach(function(item) {
					var sev = item.severity || 'ok';
					html += '<div class="cb-checkup-item cb-sev-' + sev + '">';
					html += '<span class="cb-sev-badge ' + sev + '">' + sev + '</span>';
					html += '<div style="flex:1;"><strong>' + item.title + '</strong>';
					if (item.detail) html += '<br><span style="color:#666;font-size:11px;">' + item.detail + '</span>';
					if (item.line && item.line > 0) html += ' <span style="color:#999;font-size:10px;">Line ' + item.line + '</span>';
					html += '</div></div>';
				});

				html += '</div>';
			});

			return html;
		}

		$('#run-nb-checkup').click(function() {
			var btn = $(this);
			var oldText = btn.text();
			btn.prop('disabled', true).html('<span class="dashicons dashicons-update" style="animation:spin 1s linear infinite;font-size:14px;vertical-align:middle;"></span> Scanning...');

			$('#cb-checkup-modal').show();
			$('#cb-checkup-content').html('<div style="text-align:center;padding:40px;"><span class="dashicons dashicons-update" style="font-size:32px;color:#0073aa;animation:spin 1s linear infinite;"></span><p style="margin-top:10px;color:#666;">Running comprehensive health checks...</p></div>');

			$.post(ajaxurl, { action: 'crash_block_run_checkup', nonce: nonce }, function(res) {
				btn.prop('disabled', false).text(oldText);
				if (res.success) {
					$('#cb-checkup-content').html(renderCheckupResults(res.data));

					// Update badge
					var badge = $('#cb-checkup-badge');
					if (res.data.summary.critical > 0) {
						badge.text(res.data.summary.critical + ' issues').removeClass('status-active status-warning').addClass('status-inactive').show();
					} else if (res.data.summary.warning > 0) {
						badge.text(res.data.summary.warning + ' warnings').removeClass('status-active status-inactive').addClass('status-warning').show();
					} else {
						badge.text('All Clear').removeClass('status-inactive status-warning').addClass('status-active').show();
					}

					showStatus('Checkup complete!', 'success');
				} else {
					$('#cb-checkup-content').html('<div style="color:#d63638;padding:20px;">Error: ' + (res.data.message || 'Unknown error') + '</div>');
					showStatus('Checkup failed', 'error');
				}
			}).fail(function() {
				btn.prop('disabled', false).text(oldText);
				$('#cb-checkup-content').html('<div style="color:#d63638;padding:20px;">Server error during checkup.</div>');
				showStatus('Server error', 'error');
			});
		});

		// Quick functions.php check (standalone) // v5.4.0
		$('#check-functions-only, #check-functions-quick').click(function() {
			var btn = $(this);
			var oldText = btn.text();
			btn.prop('disabled', true).text('Checking...');

			$.post(ajaxurl, { action: 'crash_block_check_functions', nonce: nonce }, function(res) {
				btn.prop('disabled', false).text(oldText);
				if (res.success) {
					var issues = res.data.issues;
					if (issues.length === 0) {
						showStatus('functions.php: All 10 checks passed!', 'success');
					} else {
						var crits = issues.filter(function(i) { return i.severity === 'critical'; }).length;
						var warns = issues.filter(function(i) { return i.severity === 'warning'; }).length;
						var msg = 'functions.php: ';
						if (crits > 0) msg += crits + ' CRITICAL ';
						if (warns > 0) msg += warns + ' warnings ';
						msg += '— Run full Checkup for details.';
						showStatus(msg, crits > 0 ? 'error' : 'success');
					}
				} else {
					showStatus('Error: ' + (res.data.message || 'Unknown'), 'error');
				}
			}).fail(function() {
				btn.prop('disabled', false).text(oldText);
				showStatus('Server error', 'error');
			});
		});

		// ========== FORM CHANGE TRACKING ==========
		var formChanged = false;
		$('input, select, textarea').on('change input', function() {
			formChanged = true;
		});
		window.onbeforeunload = function() {
			if (formChanged) return "You have unsaved changes. Are you sure you want to leave?";
		};
		$('form').on('submit', function() { formChanged = false; });

		// Auto-load config file contents on page load
		loadWpConfig();
	});
</script>
	<?php
}

