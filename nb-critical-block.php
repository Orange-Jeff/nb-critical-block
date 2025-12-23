<?php
/**
 * Plugin Name: NB Critical Block
 * Description: A fail-safe recovery toolkit that catches fatal errors and auto-restores functions.php from backups.
 * Version: 2.19.0
 * Author: Orange Jeff
 * Text Domain: nb-critical-block
 * Domain Path: /languages
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * Version 2.19.0 - 2025-12-22 - Recovery script stays installed (it's your parachute!), added security scanner whitelist note, bookmark helper
 * Version 2.18.0 - 2025-12-22 - Recovery script on-demand (reverted - bad idea!)
 * Version 2.17.0 - 2025-12-22 - Manual snippet now opens in popup modal (cleaner UI), link only shows if not installed
 * Version 2.16.0 - 2025-12-22 - wp-config backup/restore system, auto-comments existing WP_DEBUG when installing snippet
 * Version 2.15.0 - 2025-12-22 - wp-config snippet now includes WP_DEBUG constants (safe logging to file, not screen)
 * Version 2.14.0 - 2025-12-22 - Added debug mode toggle, error log viewer, wp-config snippet installer to recovery script
 * Version 2.13.0 - 2025-12-22 - Recovery script now uses WP admin credentials, fixed URL (nb-recovery.php)
 * Version 2.12.0 - 2025-12-22 - Added mu-plugins protection, emergency recovery file
 * Version 2.11.1 - 2025-12-07 - Renamed to NB Critical Block, updated shared menu to v2.1.0
 */

// Exit if accessed directly.
if (! defined('ABSPATH')) {
    exit;
}

// ============================================================================
// NETBOUND SHARED MENU SYSTEM v2.1 (embedded - works standalone or with other NB plugins)
// ============================================================================
if (!defined('NB_SHARED_MENU_VERSION')) {
    define('NB_SHARED_MENU_VERSION', '2.1.0');

    global $nb_registered_plugins;
    if (!isset($nb_registered_plugins)) {
        $nb_registered_plugins = array();
    }

    function nb_get_all_plugins() {
        return array(
            'nb-critical-block' => array('name' => 'NB Critical Block', 'description' => 'Fail-safe recovery toolkit - auto-restores functions.php on fatal errors, maintenance mode control.', 'icon' => 'dashicons-shield', 'url' => 'https://netbound.ca/plugins/critical-block'),
            'nb-camera' => array('name' => 'NB Camera', 'description' => 'Front-end webcam capture for photos and videos. Saves to Media Library.', 'icon' => 'dashicons-camera', 'url' => 'https://netbound.ca/plugins/nb-camera'),
            'nb-snapshot' => array('name' => 'NB Snapshot', 'description' => 'Quick webcam snapshots for profile pictures and featured images.', 'icon' => 'dashicons-camera-alt', 'url' => 'https://netbound.ca/plugins/nb-snapshot'),
            'nb-shortcode-sync' => array('name' => 'NB Local Transfer', 'description' => 'Multi-purpose file transfer. Sync shortcodes or upload directly to Media Library.', 'icon' => 'dashicons-update', 'url' => 'https://netbound.ca/plugins/local-transfer'),
            'nb-vdoninja' => array('name' => 'NB VDO.Ninja', 'description' => 'Embed VDO.Ninja streams in WordPress for control rooms and live streaming.', 'icon' => 'dashicons-video-alt3', 'url' => 'https://netbound.ca/plugins/vdoninja'),
            'nb-file-sync' => array('name' => 'NB File Sync', 'description' => 'Windows desktop app connector for syncing files to WordPress.', 'icon' => 'dashicons-download', 'url' => 'https://netbound.ca/plugins/file-sync'),
            'nb-social-stream-ninja' => array('name' => 'NB Social Stream Ninja', 'description' => 'Display live chat overlays from YouTube, Twitch, Facebook, and Restream.', 'icon' => 'dashicons-format-chat', 'url' => 'https://netbound.ca/plugins/social-stream-ninja'),
        );
    }

    function nb_register_plugin($slug, $name, $desc = '', $version = '1.0', $icon = 'dashicons-admin-generic', $menu_slug = '') {
        global $nb_registered_plugins;
        $nb_registered_plugins[$slug] = array('name' => $name, 'description' => $desc, 'version' => $version, 'icon' => $icon, 'menu_slug' => $menu_slug ?: $slug);
    }

    function nb_create_parent_menu() {
        global $admin_page_hooks;
        if (isset($admin_page_hooks['nb_netbound_tools'])) return;
        add_menu_page('NetBound Tools', 'NetBound Tools', 'manage_options', 'nb_netbound_tools', 'nb_render_index_page', 'dashicons-shield', 80);
        add_submenu_page('nb_netbound_tools', 'NetBound Tools', 'All Tools', 'manage_options', 'nb_netbound_tools', 'nb_render_index_page');
    }
    add_action('admin_menu', 'nb_create_parent_menu', 5);

    function nb_render_index_page() {
        global $nb_registered_plugins;
        $all_plugins = nb_get_all_plugins();
        $installed = count($nb_registered_plugins);
        $available = count($all_plugins) - $installed;
        ?>
        <div class="wrap">
            <h1><span class="dashicons dashicons-shield" style="font-size:30px;margin-right:10px;"></span> NetBound Tools</h1>
            <p style="font-size:14px;color:#666;">Your WordPress toolkit by <a href="https://netbound.ca" target="_blank">NetBound.ca</a> — <?php echo $installed; ?> installed<?php if($available > 0) echo ", $available more available"; ?></p>
            <?php if (!empty($nb_registered_plugins)): ?>
            <h2 style="margin-top:30px;border-bottom:1px solid #ccc;padding-bottom:10px;">✅ Installed</h2>
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:20px;margin-top:20px;">
                <?php foreach ($nb_registered_plugins as $slug => $p): ?>
                <div class="card" style="margin:0;padding:20px;border-left:4px solid #00a32a;">
                    <h2 style="margin-top:0;display:flex;align-items:center;gap:10px;"><span class="dashicons <?php echo esc_attr($p['icon']); ?>" style="font-size:24px;color:#00a32a;"></span><?php echo esc_html($p['name']); ?></h2>
                    <p style="color:#666;min-height:40px;"><?php echo esc_html($p['description']); ?></p>
                    <p style="margin-bottom:0;"><span style="color:#00a32a;font-size:12px;">✓ v<?php echo esc_html($p['version']); ?></span><?php if(!empty($p['menu_slug'])): ?><a href="<?php echo esc_url(admin_url('admin.php?page='.$p['menu_slug'])); ?>" class="button button-primary" style="float:right;">Open →</a><?php endif; ?></p>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            <?php $not_installed = array_diff_key($all_plugins, $nb_registered_plugins); if (!empty($not_installed)): ?>
            <h2 style="margin-top:30px;border-bottom:1px solid #ccc;padding-bottom:10px;">📦 More NetBound Plugins</h2>
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:20px;margin-top:20px;">
                <?php foreach ($not_installed as $slug => $p): ?>
                <div class="card" style="margin:0;padding:20px;border-left:4px solid #ddd;opacity:0.85;">
                    <h2 style="margin-top:0;display:flex;align-items:center;gap:10px;"><span class="dashicons <?php echo esc_attr($p['icon']); ?>" style="font-size:24px;color:#999;"></span><?php echo esc_html($p['name']); ?></h2>
                    <p style="color:#666;min-height:40px;"><?php echo esc_html($p['description']); ?></p>
                    <p style="margin-bottom:0;"><span style="color:#999;font-size:12px;">Not installed</span><a href="<?php echo esc_url($p['url']); ?>" class="button" style="float:right;" target="_blank">Learn More →</a></p>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            <div class="card" style="margin-top:30px;padding:20px;background:#f0f6fc;border-left:4px solid #2271b1;">
                <h2 style="margin-top:0;">🛡️ About NetBound Tools</h2>
                <p>WordPress plugins by <a href="https://netbound.ca" target="_blank">netbound.ca</a>. Each works standalone or together!</p>
                <p style="margin-bottom:0;color:#666;font-size:12px;"><?php echo $installed; ?> of <?php echo count($all_plugins); ?> plugins installed</p>
            </div>
        </div>
        <?php
    }

    function nb_get_parent_slug() { return 'nb_netbound_tools'; }
}

// Register this plugin
nb_register_plugin('nb-critical-block', 'NB Critical Block', 'Fail-safe recovery toolkit', '2.14.0', 'dashicons-shield', 'nb-critical-block');
// ============================================================================

// Load plugin textdomain.
function netbound_tools_load_textdomain()
{
    load_plugin_textdomain('nb-critical-block', false, dirname(plugin_basename(__FILE__)) . '/languages/');
}
add_action('plugins_loaded', 'netbound_tools_load_textdomain');

// Email notification system
function netbound_tools_send_notification($subject, $message, $include_site_info = true)
{
    $admin_email = get_option('admin_email');
    $site_name = get_option('blogname');
    $site_url = get_option('siteurl');

    if ($include_site_info) {
        $full_message = sprintf(
            "Site: %s (%s)\nTime: %s\n\n%s\n\n---\nNetBound Tools Emergency Plugin\nThis is an automated notification.",
            $site_name,
            $site_url,
            current_time('Y-m-d H:i:s'),
            $message
        );
    } else {
        $full_message = $message;
    }

    $headers = array(
        'Content-Type: text/plain; charset=UTF-8',
        'From: NetBound Tools <' . $admin_email . '>'
    );

    return wp_mail($admin_email, '[NetBound Tools] ' . $subject, $full_message, $headers);
}

// Maintenance mode bypass functionality
function netbound_tools_maintenance_bypass()
{
    // Check for bypass parameter and set cookie
    if (isset($_GET['netbound_maintenance_bypass']) && $_GET['netbound_maintenance_bypass'] === '1') {
        setcookie('netbound_maintenance_bypass', '1', time() + 3600, '/'); // Cookie lasts 1 hour
        $_COOKIE['netbound_maintenance_bypass'] = '1'; // Set for immediate use

        // Send notification about bypass usage
        netbound_tools_send_notification(
            'Maintenance Bypass Used',
            sprintf(
                "Maintenance bypass was activated from IP: %s\nUser-Agent: %s\n\nThis allows access to the site while maintenance mode is active.",
                isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'Unknown',
                isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : 'Unknown'
            )
        );
    }

    // Check if bypass is active (parameter or cookie)
    if ((isset($_GET['netbound_maintenance_bypass']) && $_GET['netbound_maintenance_bypass'] === '1') ||
        (isset($_COOKIE['netbound_maintenance_bypass']) && $_COOKIE['netbound_maintenance_bypass'] === '1')
    ) {

        // Remove maintenance mode temporarily for this request
        $maintenance_file = ABSPATH . '.maintenance';
        if (file_exists($maintenance_file)) {
            global $maintenance_bypass_active;
            $maintenance_bypass_active = true;

            // Hook into maintenance mode check to bypass it
            add_filter('enable_maintenance_mode', '__return_false');
        }
    }
}
add_action('init', 'netbound_tools_maintenance_bypass', 1); // Run very early

// === Core Plugin Logic ===

// Display an admin notice about requirements and recovery script status.
function netbound_tools_admin_notices()
{
    // Check for stuck maintenance mode
    $maintenance_file = ABSPATH . '.maintenance';
    if (file_exists($maintenance_file)) {
        $file_age = time() - filemtime($maintenance_file);
        $hours_old = round($file_age / 3600, 1);
        if ($hours_old > 1) { // If older than 1 hour, warn about stuck maintenance
?>
            <div class="notice notice-warning is-dismissible">
                <p><?php printf(
                        /* translators: %s: hours old */
                        esc_html__('NetBound Tools: Maintenance mode file detected and is %s hours old. This may be stuck from a failed update. <a href="%s">Access recovery script</a> to remove it.', 'functional-functions'),
                        '<strong>' . esc_html($hours_old) . '</strong>',
                        esc_url(admin_url('options-general.php?page=netbound-tools'))
                    ); ?></p>
            </div>
        <?php
        } else {
            // Normal maintenance mode notice
            $bypass_url = home_url('?netbound_maintenance_bypass=1');
        ?>
            <div class="notice notice-info is-dismissible">
                <p><?php printf(
                        /* translators: %s: bypass URL */
                        wp_kses(__('NetBound Tools: Maintenance mode is active. <strong>Bypass URL:</strong> <a href="%s" target="_blank">%s</a>', 'functional-functions'), array('a' => array('href' => array(), 'target' => array()), 'strong' => array())),
                        esc_url($bypass_url),
                        esc_html($bypass_url)
                    ); ?></p>
            </div>
        <?php
        }
    }

    // Notice for disabled plugins
    $disabled_plugins = get_option('netbound_disabled_plugins', array());
    if (!empty($disabled_plugins)) {
        ?>
        <div class="notice notice-error">
            <p><?php
                $count = count($disabled_plugins);
                printf(
                    wp_kses_post(_n(
                        'NetBound Tools: <strong>%d plugin</strong> has been automatically disabled due to a fatal error. <a href="%s">View and re-enable</a>',
                        'NetBound Tools: <strong>%d plugins</strong> have been automatically disabled due to fatal errors. <a href="%s">View and re-enable</a>',
                        $count,
                        'functional-functions'
                    )),
                    $count,
                    esc_url(admin_url('options-general.php?page=netbound-tools'))
                );
            ?></p>
        </div>
        <?php
    }

    // Notice for disabled MU-plugins (v2.12.0)
    $disabled_mu_plugins = get_option('netbound_disabled_mu_plugins', array());
    if (!empty($disabled_mu_plugins)) {
        ?>
        <div class="notice notice-error" style="border-left-color: #d63638;">
            <p><?php
                $count = count($disabled_mu_plugins);
                printf(
                    wp_kses_post(_n(
                        '⚠️ NetBound Tools: <strong>%d MU-plugin</strong> was automatically disabled due to a CRITICAL error. MU-plugins load before regular plugins - errors there can crash your entire site! <a href="%s">View details and re-enable</a>',
                        '⚠️ NetBound Tools: <strong>%d MU-plugins</strong> were automatically disabled due to CRITICAL errors. MU-plugins load before regular plugins - errors there can crash your entire site! <a href="%s">View details and re-enable</a>',
                        $count,
                        'functional-functions'
                    )),
                    $count,
                    esc_url(admin_url('options-general.php?page=netbound-tools'))
                );
            ?></p>
        </div>
        <?php
    }

    // Notice for non-child themes.
    if (! is_child_theme()) {
        ?>
        <div class="notice notice-warning">
            <p><?php
                echo wp_kses_post(__('NetBound Tools: The functions.php fail-safe is <strong>NOT RECOMMENDED</strong> without a child theme. Modifying the parent theme\'s functions.php directly can cause issues during theme updates.', 'functional-functions'));
            ?></p>
        </div>
    <?php
    }

    // v2.19: Recovery script notice - only show if NOT installed (it should be!)
    $recovery_filename = 'nb-recovery.php';
    if (!file_exists(ABSPATH . $recovery_filename)) {
    ?>
        <div class="notice notice-warning">
            <p><?php printf(
                    wp_kses_post(__('🚨 <strong>NB Critical Block:</strong> Recovery script not installed! <a href="%s">Install it now</a> — it\'s your emergency parachute when WordPress crashes.', 'functional-functions')),
                    esc_url(admin_url('options-general.php?page=netbound-tools'))
                ); ?></p>
        </div>
    <?php
    }
}
add_action('admin_notices', 'netbound_tools_admin_notices');


// Add the admin menu page.
function netbound_tools_menu_page()
{
    // Use shared NetBound menu system
    $parent_slug = function_exists('nb_get_parent_slug') ? nb_get_parent_slug() : 'options-general.php';

    add_submenu_page(
        $parent_slug,
        __('Critical Block', 'functional-functions'),
        __('Critical Block', 'functional-functions'),
        'manage_options',
        'nb-critical-block',
        'netbound_tools_page_content'
    );
}
add_action('admin_menu', 'netbound_tools_menu_page', 20);

// Create a child theme.
function netbound_tools_create_child_theme()
{
    if (!current_user_can('install_themes')) {
        return false;
    }

    $parent_theme = wp_get_theme();
    $parent_theme_slug = $parent_theme->get_stylesheet();
    $parent_theme_name = $parent_theme->get('Name');

    $child_theme_slug = $parent_theme_slug . '-child';
    $child_theme_name = $parent_theme_name . ' Child';
    $child_theme_path = get_theme_root() . '/' . $child_theme_slug;

    if (file_exists($child_theme_path)) {
        return true;
    }

    if (!wp_mkdir_p($child_theme_path)) {
        return false;
    }

    $style_css_content = sprintf(
        "/*
 Theme Name:   %s
 Template:     %s
 Version:      1.0.0
 Author:       Gemini
*/",
        $child_theme_name,
        $parent_theme_slug
    );

    $functions_php_content = "<?php
add_action( 'wp_enqueue_scripts', 'my_theme_enqueue_styles' );
function my_theme_enqueue_styles() {
    wp_enqueue_style( 'parent-style', get_template_directory_uri() . '/style.css' );
}
?>";

    if (!file_put_contents($child_theme_path . '/style.css', $style_css_content)) {
        return false;
    }

    if (!file_put_contents($child_theme_path . '/functions.php', $functions_php_content)) {
        return false;
    }

    return true;
}

// Display the admin page content.
function netbound_tools_page_content()
{
    // Handle plugin re-enable
    if (isset($_POST['action']) && $_POST['action'] === 'reenable_plugin' && isset($_POST['_wpnonce']) && wp_verify_nonce($_POST['_wpnonce'], 'reenable_plugin_nonce')) {
        $plugin_index = isset($_POST['plugin_index']) ? intval($_POST['plugin_index']) : -1;
        $disabled_plugins = get_option('netbound_disabled_plugins', array());

        if ($plugin_index >= 0 && isset($disabled_plugins[$plugin_index])) {
            $plugin_info = $disabled_plugins[$plugin_index];
            $disabled_path = WP_PLUGIN_DIR . '/' . $plugin_info['disabled_path'];
            $original_path = WP_PLUGIN_DIR . '/' . $plugin_info['folder'];

            if (is_dir($disabled_path) && rename($disabled_path, $original_path)) {
                // Remove from disabled list
                array_splice($disabled_plugins, $plugin_index, 1);
                update_option('netbound_disabled_plugins', $disabled_plugins);

                add_settings_error('netbound-tools-notices', 'plugin_reenabled',
                    sprintf(__('Plugin "%s" has been re-enabled. Please test your site carefully.', 'functional-functions'), $plugin_info['folder']),
                    'success');

                // Send notification
                netbound_tools_send_notification(
                    'Plugin Re-enabled',
                    sprintf(
                        "Plugin '%s' has been manually re-enabled from the admin dashboard.\n\nOriginal error was: %s\n\nPlease monitor the site for any issues.",
                        $plugin_info['folder'],
                        $plugin_info['error']
                    )
                );
            } else {
                add_settings_error('netbound-tools-notices', 'plugin_reenable_failed',
                    __('Failed to re-enable plugin. Check file permissions.', 'functional-functions'),
                    'error');
            }
        }
    }

    // Handle MU-plugin re-enable (v2.12.0)
    if (isset($_POST['action']) && $_POST['action'] === 'reenable_mu_plugin' && isset($_POST['_wpnonce']) && wp_verify_nonce($_POST['_wpnonce'], 'reenable_mu_plugin_nonce')) {
        $plugin_index = isset($_POST['mu_plugin_index']) ? intval($_POST['mu_plugin_index']) : -1;
        $disabled_mu_plugins = get_option('netbound_disabled_mu_plugins', array());

        if ($plugin_index >= 0 && isset($disabled_mu_plugins[$plugin_index])) {
            $plugin_info = $disabled_mu_plugins[$plugin_index];
            $disabled_path = WPMU_PLUGIN_DIR . '/' . $plugin_info['file'] . '.disabled';
            $original_path = WPMU_PLUGIN_DIR . '/' . $plugin_info['file'];

            if (file_exists($disabled_path) && rename($disabled_path, $original_path)) {
                // Remove from disabled list
                array_splice($disabled_mu_plugins, $plugin_index, 1);
                update_option('netbound_disabled_mu_plugins', $disabled_mu_plugins);

                add_settings_error('netbound-tools-notices', 'mu_plugin_reenabled',
                    sprintf(__('MU-Plugin "%s" has been re-enabled. Please test your site carefully!', 'functional-functions'), $plugin_info['file']),
                    'success');

                // Send notification
                netbound_tools_send_notification(
                    'CRITICAL: MU-Plugin Re-enabled',
                    sprintf(
                        "MU-Plugin '%s' has been manually re-enabled from the admin dashboard.\n\nOriginal error was: %s\n\nPlease monitor the site carefully - MU-plugin errors can crash the entire site!",
                        $plugin_info['file'],
                        $plugin_info['error']
                    )
                );
            } else {
                add_settings_error('netbound-tools-notices', 'mu_plugin_reenable_failed',
                    __('Failed to re-enable MU-plugin. The file may have been deleted or permissions changed.', 'functional-functions'),
                    'error');
            }
        }
    }

    // Handle maintenance mode toggle
    if (isset($_POST['action']) && $_POST['action'] === 'toggle_maintenance' && isset($_POST['_wpnonce']) && wp_verify_nonce($_POST['_wpnonce'], 'toggle_maintenance_nonce')) {
        $maintenance_file = ABSPATH . '.maintenance';
        if (file_exists($maintenance_file)) {
            unlink($maintenance_file);
            add_settings_error('netbound-tools-notices', 'maintenance_disabled', __('Maintenance mode disabled.', 'functional-functions'), 'success');

            // Send notification
            netbound_tools_send_notification(
                'Maintenance Mode Disabled',
                "Maintenance mode has been manually disabled from the admin dashboard.\n\nSite is now live and accessible to visitors."
            );
        } else {
            $maintenance_content = '<?php $upgrading = ' . time() . '; ?>';
            file_put_contents($maintenance_file, $maintenance_content);
            add_settings_error('netbound-tools-notices', 'maintenance_enabled', __('Maintenance mode enabled. Use this bypass URL to access your site: <a href="' . home_url('?netbound_maintenance_bypass=1') . '" target="_blank">' . home_url('?netbound_maintenance_bypass=1') . '</a>', 'functional-functions'), 'info');

            // Send notification
            netbound_tools_send_notification(
                'Maintenance Mode Enabled',
                sprintf(
                    "Maintenance mode has been manually enabled from the admin dashboard.\n\nBypass URL: %s\n\nVisitors will see a maintenance message until this is disabled.",
                    home_url('?netbound_maintenance_bypass=1')
                )
            );
        }
    }

    if (isset($_POST['action']) && $_POST['action'] === 'create_child_theme' && isset($_POST['_wpnonce']) && wp_verify_nonce($_POST['_wpnonce'], 'create_child_theme_nonce')) {
        if (netbound_tools_create_child_theme()) {
            add_settings_error('netbound-tools-notices', 'child_theme_created', __('Child theme created successfully! Please go to <a href="' . admin_url('themes.php') . '">Appearance &gt; Themes</a> to activate it.', 'functional-functions'), 'success');

            // Send notification
            netbound_tools_send_notification(
                'Child Theme Created',
                sprintf(
                    "A child theme has been created from the admin dashboard.\n\nTheme: %s\n\nThe functions.php fail-safe is now active for this child theme.",
                    wp_get_theme()->get('Name') . ' Child'
                )
            );
        } else {
            add_settings_error('netbound-tools-notices', 'child_theme_failed', __('Could not create child theme. Please check file permissions.', 'functional-functions'), 'error');
        }
    }

    // Handle mu-plugin install
    if (isset($_POST['action']) && $_POST['action'] === 'install_mu_plugin' && isset($_POST['_wpnonce']) && wp_verify_nonce($_POST['_wpnonce'], 'mu_plugin_nonce')) {
        $result = netbound_tools_install_mu_plugin();
        add_settings_error('netbound-tools-notices', 'mu_plugin_result', $result['message'], $result['success'] ? 'success' : 'error');
    }

    // Handle mu-plugin remove
    if (isset($_POST['action']) && $_POST['action'] === 'remove_mu_plugin' && isset($_POST['_wpnonce']) && wp_verify_nonce($_POST['_wpnonce'], 'mu_plugin_nonce')) {
        $result = netbound_tools_remove_mu_plugin();
        add_settings_error('netbound-tools-notices', 'mu_plugin_result', $result['message'], $result['success'] ? 'success' : 'error');
    }

    // Handle recovery script install (v2.13.0)
    if (isset($_POST['action']) && $_POST['action'] === 'install_recovery_script' && isset($_POST['_wpnonce']) && wp_verify_nonce($_POST['_wpnonce'], 'install_recovery_script_nonce')) {
        if (netbound_tools_install_recovery_script()) {
            add_settings_error('netbound-tools-notices', 'recovery_installed',
                sprintf(__('Recovery script created! <strong>Bookmark the URL now:</strong> <a href="%s" target="_blank">%s</a> - then delete the script when done troubleshooting.', 'functional-functions'),
                    esc_url(site_url('/nb-recovery.php')),
                    esc_html(site_url('/nb-recovery.php'))
                ), 'success');
        } else {
            add_settings_error('netbound-tools-notices', 'recovery_failed',
                __('Could not create recovery script. Check file permissions in your WordPress root directory.', 'functional-functions'), 'error');
        }
    }

    // Handle error log clear
    if (isset($_POST['action']) && $_POST['action'] === 'clear_error_logs' && isset($_POST['_wpnonce']) && wp_verify_nonce($_POST['_wpnonce'], 'clear_logs_nonce')) {
        $log_files = array(
            WP_CONTENT_DIR . '/netbound-error-log.txt',
            WP_CONTENT_DIR . '/netbound-critical-errors.log',
            WP_CONTENT_DIR . '/netbound-php-errors.log'
        );
        $cleared = 0;
        foreach ($log_files as $log) {
            if (file_exists($log) && unlink($log)) {
                $cleared++;
            }
        }
        add_settings_error('netbound-tools-notices', 'logs_cleared', sprintf(__('Cleared %d error log file(s).', 'functional-functions'), $cleared), 'success');
    }
    ?>
    <div class="wrap">
        <h1><?php esc_html_e('NetBound Tools', 'functional-functions'); ?></h1>
        <?php settings_errors('netbound-tools-notices'); ?>
        <p><?php esc_html_e('This is the central hub for your functions.php fail-safe and site recovery plugin.', 'functional-functions'); ?></p>

        <div class="card">
            <h2><?php esc_html_e('Maintenance Mode Control', 'functional-functions'); ?></h2>
            <?php
            $maintenance_file = ABSPATH . '.maintenance';
            $in_maintenance = file_exists($maintenance_file);
            $bypass_url = home_url('?netbound_maintenance_bypass=1');
            ?>
            <p><?php printf(
                    esc_html__('Current status: %s', 'functional-functions'),
                    $in_maintenance ? '<strong style="color: #d9534f;">MAINTENANCE MODE ACTIVE</strong>' : '<strong style="color: #5cb85c;">Site is live</strong>'
                ); ?></p>
            <?php if ($in_maintenance): ?>
                <p><?php printf(
                        wp_kses(__('Visitors see a maintenance message. <strong>Bypass URL:</strong> <a href="%s" target="_blank">%s</a> (keep this private)', 'functional-functions'), array('a' => array('href' => array(), 'target' => array()), 'strong' => array())),
                        esc_url($bypass_url),
                        esc_html($bypass_url)
                    ); ?></p>
            <?php endif; ?>
            <form method="post">
                <input type="hidden" name="action" value="toggle_maintenance">
                <?php wp_nonce_field('toggle_maintenance_nonce'); ?>
                <?php submit_button($in_maintenance ? __('Disable Maintenance Mode', 'functional-functions') : __('Enable Maintenance Mode', 'functional-functions'), $in_maintenance ? 'secondary' : 'primary'); ?>
            </form>
        </div>
        <?php
        // Display disabled plugins section
        $disabled_plugins = get_option('netbound_disabled_plugins', array());
        if (!empty($disabled_plugins)) : ?>
            <div class="card">
                <h2><?php esc_html_e('Disabled Plugins', 'functional-functions'); ?></h2>
                <p><?php esc_html_e('The following plugins were automatically disabled due to fatal errors:', 'functional-functions'); ?></p>
                <table class="widefat" style="margin-top: 10px;">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Plugin', 'functional-functions'); ?></th>
                            <th><?php esc_html_e('Error Details', 'functional-functions'); ?></th>
                            <th><?php esc_html_e('Disabled', 'functional-functions'); ?></th>
                            <th><?php esc_html_e('Action', 'functional-functions'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($disabled_plugins as $index => $plugin) : ?>
                            <tr>
                                <td><strong><?php echo esc_html($plugin['folder']); ?></strong></td>
                                <td>
                                    <div style="word-wrap: break-word; overflow-wrap: break-word; max-width: 400px;">
                                        <?php echo esc_html($plugin['error']); ?>
                                    </div>
                                    <small><?php echo esc_html($plugin['file']); ?>:<?php echo esc_html($plugin['line']); ?></small>
                                </td>
                                <td><?php echo esc_html(human_time_diff($plugin['time'])) . ' ' . __('ago', 'functional-functions'); ?></td>
                                <td>
                                    <form method="post" style="display: inline;">
                                        <input type="hidden" name="action" value="reenable_plugin">
                                        <input type="hidden" name="plugin_index" value="<?php echo esc_attr($index); ?>">
                                        <?php wp_nonce_field('reenable_plugin_nonce'); ?>
                                        <button type="submit" class="button button-secondary" onclick="return confirm('<?php esc_attr_e('Are you sure? Make sure the plugin error is fixed first.', 'functional-functions'); ?>');">
                                            <?php esc_html_e('Re-enable', 'functional-functions'); ?>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <?php
        // Display disabled MU-plugins section (v2.12.0)
        $disabled_mu_plugins = get_option('netbound_disabled_mu_plugins', array());
        if (!empty($disabled_mu_plugins)) : ?>
            <div class="card" style="border-left: 4px solid #d63638;">
                <h2 style="color: #d63638;">⚠️ <?php esc_html_e('Disabled MU-Plugins', 'functional-functions'); ?></h2>
                <p><?php esc_html_e('The following must-use plugins were automatically disabled by the early error handler due to fatal errors:', 'functional-functions'); ?></p>
                <p class="description" style="color: #d63638;"><strong><?php esc_html_e('Note: MU-plugins load before regular plugins, so errors there can prevent WordPress from loading entirely. These were caught by the wp-config.php early handler.', 'functional-functions'); ?></strong></p>
                <table class="widefat" style="margin-top: 10px;">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('MU-Plugin File', 'functional-functions'); ?></th>
                            <th><?php esc_html_e('Error Details', 'functional-functions'); ?></th>
                            <th><?php esc_html_e('Disabled', 'functional-functions'); ?></th>
                            <th><?php esc_html_e('Action', 'functional-functions'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($disabled_mu_plugins as $index => $plugin) : ?>
                            <tr>
                                <td><strong><?php echo esc_html($plugin['file']); ?></strong></td>
                                <td>
                                    <div style="word-wrap: break-word; overflow-wrap: break-word; max-width: 400px;">
                                        <?php echo esc_html($plugin['error']); ?>
                                    </div>
                                    <small><?php echo esc_html($plugin['original_file'] ?? $plugin['file']); ?>:<?php echo esc_html($plugin['line']); ?></small>
                                </td>
                                <td><?php echo esc_html(human_time_diff($plugin['time'])) . ' ' . __('ago', 'functional-functions'); ?></td>
                                <td>
                                    <form method="post" style="display: inline;">
                                        <input type="hidden" name="action" value="reenable_mu_plugin">
                                        <input type="hidden" name="mu_plugin_index" value="<?php echo esc_attr($index); ?>">
                                        <?php wp_nonce_field('reenable_mu_plugin_nonce'); ?>
                                        <button type="submit" class="button button-secondary" onclick="return confirm('<?php esc_attr_e('Are you sure? Make sure the MU-plugin error is fixed first. A bad MU-plugin can crash your entire site!', 'functional-functions'); ?>');">
                                            <?php esc_html_e('Re-enable', 'functional-functions'); ?>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>


        <?php if (! is_child_theme()) : ?>
            <div class="card">
                <h2><?php esc_html_e('Create a Child Theme', 'functional-functions'); ?></h2>
                <p><?php printf(esc_html__('To use the functions.php fail-safe, you need a child theme. You can create one here based on your current active theme (%s).'), '<strong>' . esc_html(wp_get_theme()->get('Name')) . '</strong>'); ?></p>
                <form method="post">
                    <input type="hidden" name="action" value="create_child_theme">
                    <?php wp_nonce_field('create_child_theme_nonce'); ?>
                    <?php submit_button(__('Create Child Theme', 'functional-functions')); ?>
                </form>
            </div>
        <?php endif; ?>

        <div class="card">
            <h2><?php esc_html_e('Emergency Recovery Script', 'functional-functions'); ?></h2>
            <p><?php echo wp_kses_post(__('A standalone recovery script that works even when WordPress is completely broken. <strong>Uses your WordPress admin login</strong> - no separate password to remember!', 'functional-functions')); ?></p>

            <?php
            $recovery_filename = 'nb-recovery.php';
            $recovery_installed = file_exists(ABSPATH . $recovery_filename);
            $recovery_url = site_url('/' . $recovery_filename);
            ?>

            <?php if ($recovery_installed): ?>
                <p>
                    <?php esc_html_e('Status:', 'functional-functions'); ?>
                    <strong style="color: #00a32a;">✓ <?php esc_html_e('ACTIVE', 'functional-functions'); ?></strong>
                </p>

                <div style="background: #1d2327; padding: 15px; border-radius: 4px; margin: 10px 0;">
                    <p style="margin: 0 0 5px 0;"><strong><?php esc_html_e('Emergency URL:', 'functional-functions'); ?></strong></p>
                    <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                        <code style="background: #2c3338; padding: 8px 12px; border-radius: 4px; word-break: break-all; flex: 1;">
                            <a href="<?php echo esc_url($recovery_url); ?>" target="_blank" style="color: #72aee6;" id="nb-recovery-url"><?php echo esc_html($recovery_url); ?></a>
                        </code>
                        <button type="button" class="button" onclick="navigator.clipboard.writeText('<?php echo esc_js($recovery_url); ?>'); this.textContent='Copied!'; setTimeout(() => this.textContent='Copy URL', 1500);">
                            <?php esc_html_e('Copy URL', 'functional-functions'); ?>
                        </button>
                    </div>
                    <p style="margin: 10px 0 0 0; color: #72aee6; font-size: 13px;">
                        ⭐ <strong><?php esc_html_e('Bookmark this URL now!', 'functional-functions'); ?></strong>
                        <?php esc_html_e('Press', 'functional-functions'); ?> <kbd style="background:#2c3338; padding:2px 6px; border-radius:3px;">Ctrl+D</kbd>
                        <?php esc_html_e('or drag this link to your bookmarks bar:', 'functional-functions'); ?>
                        <a href="<?php echo esc_url($recovery_url); ?>" style="color: #dba617; text-decoration: underline;" title="<?php esc_attr_e('Drag me to your bookmarks bar!', 'functional-functions'); ?>">
                            🚨 <?php echo esc_html(parse_url(home_url(), PHP_URL_HOST)); ?> Recovery
                        </a>
                    </p>
                    <p style="margin: 5px 0 0 0; color: #a7aaad; font-size: 12px;">
                        🔐 <?php esc_html_e('Login with your WordPress admin username and password', 'functional-functions'); ?>
                    </p>
                </div>

                <form method="post" style="margin-top: 10px;">
                    <input type="hidden" name="action" value="install_recovery_script">
                    <?php wp_nonce_field('install_recovery_script_nonce'); ?>
                    <button type="submit" class="button"><?php esc_html_e('Regenerate Script', 'functional-functions'); ?></button>
                </form>

                <!-- Security Scanner Note -->
                <div style="background: #fef8ee; border-left: 4px solid #dba617; padding: 12px 15px; margin: 15px 0 0 0; border-radius: 0 4px 4px 0;">
                    <p style="margin: 0; color: #1d2327;">
                        <strong>🛡️ <?php esc_html_e('About Security Scanners', 'functional-functions'); ?></strong><br>
                        <span style="font-size: 13px;"><?php echo wp_kses_post(__('This plugin uses advanced techniques to save your website when a plugin crash would otherwise lock you out. Security scanners like <strong>Wordfence</strong> may flag the recovery script as suspicious — this is expected! The file <code>nb-recovery.php</code> is safe to whitelist. It\'s your emergency parachute — you need it BEFORE disaster strikes.', 'functional-functions')); ?></span>
                    </p>
                </div>

            <?php else: ?>
                <p>
                    <?php esc_html_e('Status:', 'functional-functions'); ?>
                    <strong style="color: #d63638;">✗ <?php esc_html_e('NOT INSTALLED', 'functional-functions'); ?></strong>
                </p>
                <form method="post" style="margin-top: 10px;">
                    <input type="hidden" name="action" value="install_recovery_script">
                    <?php wp_nonce_field('install_recovery_script_nonce'); ?>
                    <button type="submit" class="button button-primary">
                        <?php esc_html_e('Install Recovery Script', 'functional-functions'); ?>
                    </button>
                </form>
            <?php endif; ?>
        </div>

        <!-- EARLY ERROR HANDLER SECTION (v2.9.0) -->
        <div class="card" style="border-left: 4px solid #2271b1;">
            <h2>🛡️ <?php esc_html_e('Early Error Handler (Advanced Protection)', 'functional-functions'); ?></h2>
            <p><?php echo wp_kses_post(__('Standard WordPress plugins load too late to prevent the "critical error" screen. These early handlers catch fatal errors <strong>BEFORE</strong> WordPress fully loads, so they can actually disable the crashing plugin and keep your site running.', 'functional-functions')); ?></p>

            <h3><?php esc_html_e('Must-Use Plugin (One-Click Install)', 'functional-functions'); ?></h3>
            <?php $mu_installed = netbound_tools_mu_plugin_installed(); ?>
            <p>
                <?php esc_html_e('Status:', 'functional-functions'); ?>
                <?php if ($mu_installed): ?>
                    <strong style="color: #00a32a;">✓ <?php esc_html_e('INSTALLED', 'functional-functions'); ?></strong>
                    <br><small><?php echo esc_html(WPMU_PLUGIN_DIR . '/nb-early-error-handler.php'); ?></small>
                <?php else: ?>
                    <strong style="color: #d63638;">✗ <?php esc_html_e('NOT INSTALLED', 'functional-functions'); ?></strong>
                <?php endif; ?>
            </p>
            <p class="description"><?php esc_html_e('Click the button below to install. Must-use plugins load before regular plugins, allowing this to catch and handle plugin errors before they crash the site.', 'functional-functions'); ?></p>
            <form method="post" style="display: inline;">
                <input type="hidden" name="action" value="<?php echo $mu_installed ? 'remove_mu_plugin' : 'install_mu_plugin'; ?>">
                <?php wp_nonce_field('mu_plugin_nonce'); ?>
                <?php submit_button($mu_installed ? __('Remove Must-Use Plugin', 'functional-functions') : __('Install Must-Use Plugin', 'functional-functions'), $mu_installed ? 'secondary' : 'primary', 'submit', false); ?>
            </form>

            <hr style="margin: 20px 0;">

            <h3><?php esc_html_e('wp-config.php Snippet (Optional - Requires FTP Access)', 'functional-functions'); ?></h3>
            <?php $wpconfig_installed = netbound_tools_wpconfig_snippet_installed(); ?>
            <p>
                <?php esc_html_e('Status:', 'functional-functions'); ?>
                <?php if ($wpconfig_installed): ?>
                    <strong style="color: #00a32a;">✓ <?php esc_html_e('DETECTED', 'functional-functions'); ?></strong>
                <?php else: ?>
                    <strong style="color: #dba617;">⚠ <?php esc_html_e('NOT INSTALLED (Optional)', 'functional-functions'); ?></strong>
                    - <a href="#" id="nb_show_snippet_btn" style="color: #2271b1;"><?php esc_html_e('View code for manual install', 'functional-functions'); ?></a>
                <?php endif; ?>
            </p>
            <p class="description"><?php echo wp_kses_post(__('<strong>Optional extra protection.</strong> This catches errors even earlier than the must-use plugin.', 'functional-functions')); ?></p>

            <!-- Hidden modal for manual snippet -->
            <div id="nb_snippet_modal" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.8); z-index:100000; padding:20px; overflow:auto;">
                <div style="max-width:800px; margin:40px auto; background:#1d2327; border-radius:8px; padding:20px;">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
                        <h3 style="margin:0; color:#f0f0f1;">📋 wp-config.php Snippet (Manual Install)</h3>
                        <button type="button" id="nb_close_snippet_modal" style="background:#d63638; color:#fff; border:none; padding:8px 16px; border-radius:4px; cursor:pointer;">✕ Close</button>
                    </div>
                    <div style="margin-bottom:15px;">
                        <button type="button" class="button button-primary" id="nb_copy_snippet_btn">
                            <span class="dashicons dashicons-clipboard" style="margin-top:3px;"></span> <?php esc_html_e('Copy to Clipboard', 'functional-functions'); ?>
                        </button>
                        <button type="button" class="button" id="nb_download_snippet_btn">
                            <span class="dashicons dashicons-download" style="margin-top:3px;"></span> <?php esc_html_e('Download as File', 'functional-functions'); ?>
                        </button>
                        <span id="nb_snippet_action_msg" style="margin-left: 10px; color: #00a32a; display: none;"></span>
                    </div>
                    <div style="background: #0a0a0a; color: #00ff00; padding: 15px; border-radius: 4px; overflow-x: auto; max-height:400px; overflow-y:auto;">
                        <pre id="nb_wpconfig_code" style="margin: 0; white-space: pre-wrap; font-size: 11px;"><?php echo esc_html(netbound_tools_get_wpconfig_snippet()); ?></pre>
                    </div>
                    <p style="margin-top:15px; color:#a7aaad; font-size:12px;">
                        <strong>Instructions:</strong> Add this code to your <code style="background:#2c3338;padding:2px 5px;">wp-config.php</code> file via FTP or File Manager plugin.<br>
                        <strong>Important:</strong> Delete any existing <code style="background:#2c3338;padding:2px 5px;">WP_DEBUG</code> line first.<br>
                        Place <em>AFTER</em> all defines, <em>BEFORE</em> <code style="background:#2c3338;padding:2px 5px;">require_once ABSPATH . 'wp-settings.php';</code>
                    </p>
                </div>
            </div>

            <script>
            document.addEventListener('DOMContentLoaded', function() {
                var modal = document.getElementById('nb_snippet_modal');
                var showBtn = document.getElementById('nb_show_snippet_btn');
                var closeBtn = document.getElementById('nb_close_snippet_modal');

                if (showBtn) {
                    showBtn.addEventListener('click', function(e) {
                        e.preventDefault();
                        modal.style.display = 'block';
                    });
                }
                if (closeBtn) {
                    closeBtn.addEventListener('click', function() {
                        modal.style.display = 'none';
                    });
                }
                // Close on backdrop click
                modal.addEventListener('click', function(e) {
                    if (e.target === modal) modal.style.display = 'none';
                });
                // Close on Escape
                document.addEventListener('keydown', function(e) {
                    if (e.key === 'Escape' && modal.style.display === 'block') {
                        modal.style.display = 'none';
                    }
                });

                // Copy to clipboard
                document.getElementById('nb_copy_snippet_btn').addEventListener('click', function() {
                    var code = document.getElementById('nb_wpconfig_code').innerText;
                    navigator.clipboard.writeText(code).then(function() {
                        var msg = document.getElementById('nb_snippet_action_msg');
                        msg.innerText = 'Copied!';
                        msg.style.display = 'inline';
                        setTimeout(function() { msg.style.display = 'none'; }, 2000);
                    }, function(err) {
                        alert('Could not copy text: ' + err);
                    });
                });

                // Download as file
                document.getElementById('nb_download_snippet_btn').addEventListener('click', function() {
                    var code = document.getElementById('nb_wpconfig_code').innerText;
                    var blob = new Blob([code], { type: 'text/plain' });
                    var url = window.URL.createObjectURL(blob);
                    var a = document.createElement('a');
                    a.href = url;
                    a.download = 'wp-config-snippet.txt';
                    document.body.appendChild(a);
                    a.click();
                    window.URL.revokeObjectURL(url);
                    document.body.removeChild(a);
                });
            });
            </script>
        </div>

        <!-- ERROR LOGS SECTION -->
        <?php $error_logs = netbound_tools_get_error_logs(); ?>

        <div class="card">
            <h2>📋 <?php esc_html_e('Error Logs', 'functional-functions'); ?></h2>

            <p>
                <strong><?php esc_html_e('Debugging Status:', 'functional-functions'); ?></strong>
                <?php if (defined('WP_DEBUG') && WP_DEBUG): ?>
                    <span style="color:#00a32a;">WP_DEBUG ON</span>
                <?php else: ?>
                    <span style="color:#888;">WP_DEBUG OFF</span>
                <?php endif; ?>
                |
                <?php if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG): ?>
                    <span style="color:#00a32a;">LOGGING ON</span>
                <?php else: ?>
                    <span style="color:#888;">LOGGING OFF (See <a href="https://wordpress.org/support/article/debugging-in-wordpress/" target="_blank">codex</a>)</span>
                <?php endif; ?>
            </p>

            <?php if (!empty($error_logs)): ?>
                <p><?php esc_html_e('Recent errors caught by the early error handler and standard logs:', 'functional-functions'); ?></p>
            <?php else: ?>
                <p><em><?php esc_html_e('No error logs found. This is good!', 'functional-functions'); ?></em></p>
                <?php if (!defined('WP_DEBUG_LOG') || !WP_DEBUG_LOG): ?>
                    <p class="description"><?php esc_html_e('To see the Standard Debug Log, you must enable WP_DEBUG_LOG in your wp-config.php file.', 'functional-functions'); ?></p>
                <?php endif; ?>
            <?php endif; ?>

            <?php if (!empty($error_logs)): ?>
                <?php foreach ($error_logs as $log_name => $log_data): ?>
                    <details style="margin-bottom: 15px;">
                        <summary style="cursor: pointer; font-weight: 600;">
                            <?php echo esc_html($log_name); ?>
                            <span style="font-weight: normal; color: #646970;">
                                (<?php echo esc_html(size_format($log_data['size'])); ?>,
                                <?php echo esc_html(human_time_diff($log_data['modified'])); ?> <?php esc_html_e('ago', 'functional-functions'); ?>)
                            </span>
                        </summary>
                        <div style="background: #1d2327; color: #f0f0f1; padding: 15px; border-radius: 4px; margin-top: 10px; max-height: 300px; overflow-y: auto;">
                            <pre style="margin: 0; white-space: pre-wrap; font-size: 11px;"><?php echo esc_html($log_data['content']); ?></pre>
                        </div>
                    </details>
                <?php endforeach; ?>

                <form method="post" style="margin-top: 15px;">
                    <input type="hidden" name="action" value="clear_error_logs">
                    <?php wp_nonce_field('clear_logs_nonce'); ?>
                    <?php submit_button(__('Clear All Error Logs', 'functional-functions'), 'secondary', 'submit', false); ?>
                </form>
            <?php endif; ?>
        </div>

        <!-- PROTECTION STATUS SUMMARY -->
        <div class="card" style="background: #f0f6fc; border-left: 4px solid #72aee6;">
            <h2>📊 <?php esc_html_e('Protection Status Summary', 'functional-functions'); ?></h2>
            <table class="widefat" style="background: transparent; border: none;">
                <tbody>
                    <tr>
                        <td><strong><?php esc_html_e('Main Plugin', 'functional-functions'); ?></strong></td>
                        <td><span style="color: #00a32a;">✓ <?php esc_html_e('Active', 'functional-functions'); ?></span></td>
                        <td><?php esc_html_e('Catches errors after WordPress loads', 'functional-functions'); ?></td>
                    </tr>
                    <tr>
                        <td><strong><?php esc_html_e('Must-Use Plugin', 'functional-functions'); ?></strong></td>
                        <td>
                            <?php if (netbound_tools_mu_plugin_installed()): ?>
                                <span style="color: #00a32a;">✓ <?php esc_html_e('Installed', 'functional-functions'); ?></span>
                            <?php else: ?>
                                <span style="color: #dba617;">⚠ <?php esc_html_e('Not installed', 'functional-functions'); ?></span>
                            <?php endif; ?>
                        </td>
                        <td><?php esc_html_e('Catches errors before regular plugins load', 'functional-functions'); ?></td>
                    </tr>
                    <tr>
                        <td><strong><?php esc_html_e('wp-config.php Handler', 'functional-functions'); ?></strong></td>
                        <td>
                            <?php if (netbound_tools_wpconfig_snippet_installed()): ?>
                                <span style="color: #00a32a;">✓ <?php esc_html_e('Detected', 'functional-functions'); ?></span>
                            <?php else: ?>
                                <span style="color: #dba617;">⚠ <?php esc_html_e('Not detected', 'functional-functions'); ?></span>
                            <?php endif; ?>
                        </td>
                        <td><?php esc_html_e('Catches errors before ANYTHING loads (ultimate protection)', 'functional-functions'); ?></td>
                    </tr>
                    <tr>
                        <td><strong><?php esc_html_e('Recovery Script', 'functional-functions'); ?></strong></td>
                        <td>
                            <?php
                            $recovery_script = get_option('netbound_recovery_script_filename');
                            if (!empty($recovery_script) && file_exists(ABSPATH . $recovery_script)):
                            ?>
                                <span style="color: #00a32a;">✓ <?php esc_html_e('Configured', 'functional-functions'); ?></span>
                            <?php else: ?>
                                <span style="color: #d63638;">✗ <?php esc_html_e('Not configured', 'functional-functions'); ?></span>
                            <?php endif; ?>
                        </td>
                        <td><?php esc_html_e('Emergency access when all else fails', 'functional-functions'); ?></td>
                    </tr>
                </tbody>
            </table>
        </div>

    </div>
<?php
}

// Register settings and fields for the admin page.
function netbound_tools_register_settings()
{
    register_setting('netbound_tools_options', 'netbound_recovery_password', 'netbound_tools_handle_password_update');
    add_settings_section('netbound_tools_section', __('Password Settings', 'functional-functions'), null, 'netbound-tools');
    add_settings_field('netbound_recovery_password', __('New Recovery Password', 'functional-functions'), 'netbound_tools_password_field_html', 'netbound-tools', 'netbound_tools_section');
}
add_action('admin_init', 'netbound_tools_register_settings');

function netbound_tools_password_field_html()
{
?>
    <input type="password" name="netbound_recovery_password" value="" class="regular-text" placeholder="<?php esc_attr_e('Enter a strong password', 'functional-functions'); ?>" />
    <p class="description"><?php esc_html_e('Leave blank to keep the current password. A new recovery script with a new random filename will be generated on save.', 'functional-functions'); ?></p>
<?php
}

// Handle password update and generate the recovery script.
// v2.13.0: Now uses fixed filename and WordPress credentials - no password needed
function netbound_tools_handle_password_update($password)
{
    if (! current_user_can('manage_options')) {
        return;
    }

    // v2.13.0: Generate/regenerate the recovery script with fixed filename
    netbound_tools_install_recovery_script();

    // Return false to not store anything
    return false;
}

// Install the recovery script with fixed filename (v2.13.0)
function netbound_tools_install_recovery_script() {
    $fixed_filename = 'nb-recovery.php';
    $recovery_script_content = netbound_tools_get_recovery_script_content();

    // Delete any old random-named recovery scripts
    $old_script = get_option('netbound_recovery_script_filename');
    if (!empty($old_script) && $old_script !== $fixed_filename && file_exists(ABSPATH . $old_script)) {
        @unlink(ABSPATH . $old_script);
    }

    // Write the new script to the root directory
    if (file_put_contents(ABSPATH . $fixed_filename, $recovery_script_content)) {
        update_option('netbound_recovery_script_filename', $fixed_filename);

        // Send notification about recovery script
        netbound_tools_send_notification(
            'Recovery Script Installed',
            sprintf(
                "Emergency recovery script installed.\n\nURL: %s\nLogin: Use your WordPress admin credentials\n\nThis URL is the same on all sites with NetBound Critical Block!",
                home_url('/' . $fixed_filename)
            )
        );

        return true;
    }

    return false;
}

// v2.18: Recovery script is now created ON DEMAND only (not auto-installed)
// This avoids security scanner false positives from having a login bypass file sitting in root
register_activation_hook(__FILE__, 'netbound_tools_on_activation');
function netbound_tools_on_activation() {
    // Don't auto-install recovery script - user creates it when needed
    // Just set up default options if needed
}

// Remove recovery script on plugin deactivation (optional - leave it for safety?)
// Commented out - better to leave it in case site breaks
// register_deactivation_hook(__FILE__, 'netbound_tools_on_deactivation');
// function netbound_tools_on_deactivation() {
//     $filename = 'nb-recovery.php';
//     if (file_exists(ABSPATH . $filename)) {
//         @unlink(ABSPATH . $filename);
//     }
// }


// Activate the fail-safe (works with or without child theme)
register_shutdown_function('netbound_tools_fail_safe_handler');
add_action('wp_loaded', 'netbound_tools_create_copy');

// --- FUNCTIONS FAIL-SAFE LOGIC ---

// This function runs on PHP shutdown to catch fatal errors.
function netbound_tools_fail_safe_handler()
{
    $last_error = error_get_last();
    if ($last_error && in_array($last_error['type'], array(E_ERROR, E_PARSE, E_COMPILE_ERROR, E_RECOVERABLE_ERROR))) {

        $error_file = $last_error['file'];
        $error_message = $last_error['message'];
        $plugins_dir = WP_PLUGIN_DIR;
        $mu_plugins_dir = defined('WPMU_PLUGIN_DIR') ? WPMU_PLUGIN_DIR : WP_CONTENT_DIR . '/mu-plugins';

        // Check if error might be PHP version related
        if (netbound_tools_is_php_version_error($error_message)) {
            netbound_tools_handle_php_version_error($last_error);
            return;
        }

        // Check if error is in mu-plugins (MUST USE plugins)
        if (strpos($error_file, $mu_plugins_dir) === 0) {
            netbound_tools_handle_mu_plugin_error($last_error, $error_file, $mu_plugins_dir);
            return;
        }

        // Check if error is in a regular plugin
        if (strpos($error_file, $plugins_dir) === 0) {
            netbound_tools_handle_plugin_error($last_error, $error_file, $plugins_dir);
            return;
        }

        // Check if error is in theme's functions.php
        $functions_path = get_stylesheet_directory() . '/functions.php';
        if ($error_file === $functions_path) {
            netbound_tools_handle_functions_error($last_error, $functions_path);
            return;
        }
    }
}

// Check if error message indicates PHP version incompatibility
function netbound_tools_is_php_version_error($error_message)
{
    $php_version_indicators = array(
        'syntax error',
        'unexpected',
        'Parse error',
        'requires PHP',
        'incompatible',
        'deprecated',
        'Fatal error: Cannot use'
    );

    foreach ($php_version_indicators as $indicator) {
        if (stripos($error_message, $indicator) !== false) {
            return true;
        }
    }

    return false;
}

// Handle PHP version related errors
function netbound_tools_handle_php_version_error($last_error)
{
    $result = attempt_php_rollback(ABSPATH);

    if ($result['success']) {
        // Enable maintenance mode
        $maintenance_content = '<?php $upgrading = ' . time() . '; ?>';
        file_put_contents(ABSPATH . '.maintenance', $maintenance_content);

        // Send notification
        netbound_tools_send_notification(
            'CRITICAL: PHP Version Rollback Attempted',
            sprintf(
                "A potential PHP version incompatibility error was detected!\n\nError: %s in %s on line %s\n\nAction taken: %s\n\nMaintenance mode has been enabled.\n\nPlease verify your site is working properly and update your code to be compatible with the current PHP version.",
                $last_error['message'],
                $last_error['file'],
                $last_error['line'],
                $result['message']
            )
        );
    }
}

// Handle plugin-related fatal errors
function netbound_tools_handle_plugin_error($last_error, $error_file, $plugins_dir)
{
    // Extract plugin folder name from error file path
    $relative_path = str_replace($plugins_dir . '/', '', $error_file);
    $plugin_folder = explode('/', $relative_path)[0];
    $plugin_path = $plugins_dir . '/' . $plugin_folder;

    // Don't disable this plugin (nb-critical-block)
    if ($plugin_folder === 'nb-critical-block') {
        return;
    }

    // Rename plugin folder to disable it
    $disabled_path = $plugin_path . '-DISABLED-' . time();
    if (is_dir($plugin_path) && rename($plugin_path, $disabled_path)) {

        // Enable maintenance mode
        $maintenance_content = '<?php $upgrading = ' . time() . '; ?>';
        file_put_contents(ABSPATH . '.maintenance', $maintenance_content);

        // Store disabled plugin info
        $disabled_plugins = get_option('netbound_disabled_plugins', array());
        $disabled_plugins[] = array(
            'folder' => $plugin_folder,
            'disabled_path' => basename($disabled_path),
            'error' => $last_error['message'],
            'file' => $last_error['file'],
            'line' => $last_error['line'],
            'time' => time()
        );
        update_option('netbound_disabled_plugins', $disabled_plugins);

        // Send critical notification
        netbound_tools_send_notification(
            'CRITICAL: Plugin Auto-Disabled',
            sprintf(
                "EMERGENCY: A fatal error was detected in plugin '%s'!\n\nError: %s in %s on line %s\n\nThe plugin has been automatically disabled by renaming its folder to prevent site crash.\n\nMaintenance mode has been enabled.\n\nDisabled folder: %s\n\nYou can re-enable it from the NetBound Tools admin page after fixing the issue.",
                $plugin_folder,
                $last_error['message'],
                $last_error['file'],
                $last_error['line'],
                basename($disabled_path)
            )
        );
    }
}

// Handle functions.php related fatal errors
function netbound_tools_handle_functions_error($last_error, $functions_path)
{
    $copy_path = get_stylesheet_directory() . '/functions-copy.php';
    $failed_path = get_stylesheet_directory() . '/functions-failed-' . time() . '.php';

    if (file_exists($copy_path)) {
        // Rename failed file and restore backup
        rename($functions_path, $failed_path);
        rename($copy_path, $functions_path);

        // Send critical notification
        netbound_tools_send_notification(
            'CRITICAL: functions.php Fail-Safe Activated',
            sprintf(
                "EMERGENCY: A fatal error was detected in your theme's functions.php file!\n\nError: %s in %s on line %s\n\nThe fail-safe has automatically restored a safe backup to prevent site crash.\n\nFailed file has been renamed to: %s\n\nPlease review and fix the error before re-uploading.",
                $last_error['message'],
                $last_error['file'],
                $last_error['line'],
                basename($failed_path)
            )
        );
    }
}

// Handle mu-plugin (must-use plugin) related fatal errors
function netbound_tools_handle_mu_plugin_error($last_error, $error_file, $mu_plugins_dir)
{
    // Extract mu-plugin filename from error path
    $relative_path = str_replace($mu_plugins_dir . '/', '', $error_file);
    $mu_plugin_file = explode('/', $relative_path)[0]; // Could be just filename.php or folder/file.php

    // For simple files like recipe-shortcodes.php
    if (strpos($mu_plugin_file, '.php') !== false) {
        $plugin_path = $mu_plugins_dir . '/' . $mu_plugin_file;
    } else {
        // For folder-based mu-plugins
        $plugin_path = $mu_plugins_dir . '/' . $mu_plugin_file;
    }

    // Don't disable nb-critical-block's own mu-plugin
    if (strpos($mu_plugin_file, 'nb-early-error-handler') !== false) {
        return;
    }

    // Rename mu-plugin file/folder to disable it
    $disabled_path = $plugin_path . '.DISABLED-' . time();

    $renamed = false;
    if (is_file($plugin_path)) {
        $renamed = rename($plugin_path, $disabled_path);
    } elseif (is_dir($plugin_path)) {
        $renamed = rename($plugin_path, $disabled_path);
    }

    if ($renamed) {
        // Enable maintenance mode
        $maintenance_content = '<?php $upgrading = ' . time() . '; ?>';
        file_put_contents(ABSPATH . '.maintenance', $maintenance_content);

        // Store disabled mu-plugin info
        $disabled_mu_plugins = get_option('netbound_disabled_mu_plugins', array());
        $disabled_mu_plugins[] = array(
            'file' => $mu_plugin_file,
            'disabled_path' => basename($disabled_path),
            'error' => $last_error['message'],
            'original_file' => $last_error['file'],
            'line' => $last_error['line'],
            'time' => time()
        );
        update_option('netbound_disabled_mu_plugins', $disabled_mu_plugins);

        // Send critical notification
        netbound_tools_send_notification(
            'CRITICAL: MU-Plugin Auto-Disabled',
            sprintf(
                "EMERGENCY: A fatal error was detected in must-use plugin '%s'!\n\nError: %s in %s on line %s\n\nThe mu-plugin has been automatically disabled by renaming it to prevent site crash.\n\nMaintenance mode has been enabled.\n\nDisabled file: %s\n\nYou can re-enable it from the NetBound Tools admin page after fixing the issue.",
                $mu_plugin_file,
                $last_error['message'],
                $last_error['file'],
                $last_error['line'],
                basename($disabled_path)
            )
        );
    }
}


// --- AUTOMATIC FUNCTIONS.PHP BACKUP ---

// This function creates or updates the functions-copy.php file.
function netbound_tools_create_copy()
{
    $functions_path = get_stylesheet_directory() . '/functions.php';
    $copy_path = get_stylesheet_directory() . '/functions-copy.php';

    if (! file_exists($functions_path)) return;

    if (! file_exists($copy_path) || md5_file($functions_path) !== md5_file($copy_path)) {
        copy($functions_path, $copy_path);
    }
}


function attempt_php_rollback($wp_root)
{
    $htaccess_path = $wp_root . '/.htaccess';
    if (!file_exists($htaccess_path)) {
        return array('success' => false, 'message' => '.htaccess file not found.');
    }

    $htaccess_content = file_get_contents($htaccess_path);
    $original_content = $htaccess_content;

    // Look for existing PHP version directives
    $patterns = array(
        '/# php_value (.+)/i',
        '/php_value (.+)/i',
        '/# <Files.*>\s*php_value (.+)\s*<\/Files>/i',
        '/<Files.*>\s*php_value (.+)\s*<\/Files>/i'
    );

    $modified = false;
    foreach ($patterns as $pattern) {
        if (preg_match_all($pattern, $htaccess_content, $matches)) {
            foreach ($matches[0] as $match) {
                // Comment out the PHP version directive
                $htaccess_content = str_replace($match, '# ' . $match, $htaccess_content);
                $modified = true;
            }
        }
    }

    if ($modified) {
        if (file_put_contents($htaccess_path, $htaccess_content)) {
            return array('success' => true, 'message' => 'PHP version directives commented out in .htaccess. Server may use default PHP version.');
        } else {
            return array('success' => false, 'message' => 'Failed to write .htaccess file. Check file permissions.');
        }
    } else {
        return array('success' => true, 'message' => 'No PHP version directives found in .htaccess. Server is likely using default PHP version.');
    }
}

function upload_functions_file($file, $wp_root)
{
    $child_theme_dirs = glob($wp_root . '/wp-content/themes/*-child');
    if (empty($child_theme_dirs)) {
        return array('success' => false, 'message' => 'No child theme found. Please create a child theme first.');
    }

    $child_theme_path = $child_theme_dirs[0];
    $target_path = $child_theme_path . '/functions.php';

    // Validate file type
    $allowed_types = array('application/php', 'text/php', 'application/x-php', 'text/x-php', 'text/plain');
    if (!in_array($file['type'], $allowed_types) && !preg_match('/\.php$/i', $file['name'])) {
        return array('success' => false, 'message' => 'Invalid file type. Only PHP files are allowed.');
    }

    // Move uploaded file
    if (move_uploaded_file($file['tmp_name'], $target_path)) {
        return array('success' => true, 'message' => 'functions.php uploaded successfully to ' . basename($child_theme_path) . ' theme.');
    } else {
        return array('success' => false, 'message' => 'Failed to upload file. Check file permissions.');
    }
}

// Catch shortcode errors
function netbound_tools_catch_shortcode_errors($output, $tag, $attr, $m)
{
    // Start error capturing
    $error_handler = set_error_handler(function($errno, $errstr, $errfile, $errline) use ($tag) {
        // Log the shortcode error
        $shortcode_errors = get_option('netbound_shortcode_errors', array());

        $error_key = md5($tag . $errstr . $errfile . $errline);

        if (!isset($shortcode_errors[$error_key])) {
            $shortcode_errors[$error_key] = array(
                'shortcode' => $tag,
                'error' => $errstr,
                'file' => $errfile,
                'line' => $errline,
                'count' => 1,
                'first_seen' => time(),
                'last_seen' => time()
            );
        } else {
            $shortcode_errors[$error_key]['count']++;
            $shortcode_errors[$error_key]['last_seen'] = time();
        }

        // Keep only last 50 errors
        if (count($shortcode_errors) > 50) {
            array_shift($shortcode_errors);
        }

        update_option('netbound_shortcode_errors', $shortcode_errors);

        return false; // Don't stop execution
    });

    // Restore error handler after shortcode execution
    if ($error_handler !== null) {
        restore_error_handler();
    }

    return $output;
}

// Check and display shortcode errors in admin
function netbound_tools_check_shortcode_errors()
{
    $shortcode_errors = get_option('netbound_shortcode_errors', array());

    if (!empty($shortcode_errors)) {
        // Check if there are recent errors (within last hour)
        $recent_errors = array_filter($shortcode_errors, function($error) {
            return (time() - $error['last_seen']) < 3600;
        });

        if (!empty($recent_errors)) {
            add_action('admin_notices', function() use ($recent_errors) {
                ?>
                <div class="notice notice-warning is-dismissible">
                    <p><?php
                        $count = count($recent_errors);
                        printf(
                            wp_kses_post(_n(
                                'NetBound Tools: <strong>%d shortcode error</strong> detected in the last hour. <a href="%s">View details</a>',
                                'NetBound Tools: <strong>%d shortcode errors</strong> detected in the last hour. <a href="%s">View details</a>',
                                $count,
                                'functional-functions'
                            )),
                            $count,
                            esc_url(admin_url('options-general.php?page=netbound-tools#shortcode-errors'))
                        );
                    ?></p>
                </div>
                <?php
            });
        }
    }
}

function netbound_tools_get_recovery_script_content($password_hash = '')
{
    // Full-featured emergency recovery script v2.13.0
    // Now uses WordPress admin credentials - no separate password needed!
    $script = '<?php
/**
 * NetBound Tools Emergency Recovery Script v2.13.0
 * This standalone script works WITHOUT WordPress fully loading
 * Use this when your site shows "critical error" or white screen
 *
 * LOGIN: Use your WordPress admin username and password
 * URL: yoursite.com/nb-recovery.php (same on all sites!)
 */

session_start();
error_reporting(0);

// Configuration
$MAX_LOGIN_ATTEMPTS = 5;
$LOCKOUT_TIME = 900; // 15 minutes

// Security: Check login attempts
$attempts_file = __DIR__ . \'/wp-content/.nb-recovery-attempts\';
$attempts = file_exists($attempts_file) ? json_decode(file_get_contents($attempts_file), true) : [];
$ip = $_SERVER[\'REMOTE_ADDR\'] ?? \'unknown\';
$now = time();

// Clean old attempts
if (isset($attempts[$ip]) && $attempts[$ip][\'time\'] < ($now - $LOCKOUT_TIME)) {
    unset($attempts[$ip]);
}

// Check if locked out
if (isset($attempts[$ip]) && $attempts[$ip][\'count\'] >= $MAX_LOGIN_ATTEMPTS) {
    die(\'Too many login attempts. Please wait 15 minutes.\');
}

// Load WordPress database config (minimal - just what we need)
$wp_config = __DIR__ . \'/wp-config.php\';
if (!file_exists($wp_config)) {
    die(\'Error: wp-config.php not found. This script must be in the WordPress root directory.\');
}

// Parse wp-config.php to get database credentials
$config_content = file_get_contents($wp_config);
preg_match("/define\s*\(\s*[\'\\"]DB_NAME[\'\\"]\s*,\s*[\'\\"]([^\'\\"]+)[\'\\"]\s*\)/", $config_content, $db_name);
preg_match("/define\s*\(\s*[\'\\"]DB_USER[\'\\"]\s*,\s*[\'\\"]([^\'\\"]+)[\'\\"]\s*\)/", $config_content, $db_user);
preg_match("/define\s*\(\s*[\'\\"]DB_PASSWORD[\'\\"]\s*,\s*[\'\\"]([^\'\\"]+)[\'\\"]\s*\)/", $config_content, $db_pass);
preg_match("/define\s*\(\s*[\'\\"]DB_HOST[\'\\"]\s*,\s*[\'\\"]([^\'\\"]+)[\'\\"]\s*\)/", $config_content, $db_host);
preg_match("/\\\$table_prefix\s*=\s*[\'\\"]([^\'\\"]+)[\'\\"]/", $config_content, $table_prefix);

$db_name = $db_name[1] ?? \'\';
$db_user = $db_user[1] ?? \'\';
$db_pass = $db_pass[1] ?? \'\';
$db_host = $db_host[1] ?? \'localhost\';
$table_prefix = $table_prefix[1] ?? \'wp_\';

if (empty($db_name) || empty($db_user)) {
    die(\'Error: Could not parse database credentials from wp-config.php\');
}

// Connect to database
try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die(\'Database connection failed: \' . $e->getMessage());
}

// Function to verify WordPress password
function nb_verify_wp_password($password, $hash) {
    // WordPress uses phpass - we need to handle it
    if (strpos($hash, \'$P$\') === 0 || strpos($hash, \'$H$\') === 0) {
        // PHPass hash
        $itoa64 = \'./0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz\';
        $count_log2 = strpos($itoa64, $hash[3]);
        $count = 1 << $count_log2;
        $salt = substr($hash, 4, 8);
        $check_hash = md5($salt . $password, true);
        for ($i = 0; $i < $count; $i++) {
            $check_hash = md5($check_hash . $password, true);
        }
        $encoded = substr($hash, 0, 12);
        $encoded .= nb_encode64($check_hash, 16, $itoa64);
        return $encoded === $hash;
    }
    // Try regular password_verify for newer hashes
    return password_verify($password, $hash);
}

function nb_encode64($input, $count, $itoa64) {
    $output = \'\';
    $i = 0;
    while ($i < $count) {
        $value = ord($input[$i++]);
        $output .= $itoa64[$value & 0x3f];
        if ($i < $count) $value |= ord($input[$i]) << 8;
        $output .= $itoa64[($value >> 6) & 0x3f];
        if ($i++ >= $count) break;
        if ($i < $count) $value |= ord($input[$i]) << 16;
        $output .= $itoa64[($value >> 12) & 0x3f];
        if ($i++ >= $count) break;
        $output .= $itoa64[($value >> 18) & 0x3f];
    }
    return $output;
}

// Check if user is admin
function nb_is_admin($user_id, $pdo, $table_prefix) {
    // Check usermeta for wp_capabilities containing administrator
    $stmt = $pdo->prepare("SELECT meta_value FROM {$table_prefix}usermeta WHERE user_id = ? AND meta_key = ?");
    $cap_key = $table_prefix . \'capabilities\';
    $stmt->execute([$user_id, $cap_key]);
    $caps = $stmt->fetchColumn();
    if ($caps && strpos($caps, \'administrator\') !== false) {
        return true;
    }
    return false;
}

$logged_in = isset($_SESSION[\'nb_recovery_user_id\']);
$error = \'\';
$success = \'\';
$username_display = \'\';

if ($logged_in) {
    // Get username for display
    $stmt = $pdo->prepare("SELECT user_login FROM {$table_prefix}users WHERE ID = ?");
    $stmt->execute([$_SESSION[\'nb_recovery_user_id\']]);
    $username_display = $stmt->fetchColumn();
}

// Handle login
if (isset($_POST[\'username\']) && isset($_POST[\'password\'])) {
    $username = trim($_POST[\'username\']);
    $password = $_POST[\'password\'];

    // Look up user
    $stmt = $pdo->prepare("SELECT ID, user_pass FROM {$table_prefix}users WHERE user_login = ? OR user_email = ?");
    $stmt->execute([$username, $username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && nb_verify_wp_password($password, $user[\'user_pass\'])) {
        // Check if admin
        if (nb_is_admin($user[\'ID\'], $pdo, $table_prefix)) {
            $_SESSION[\'nb_recovery_user_id\'] = $user[\'ID\'];
            $logged_in = true;
            $username_display = $username;
            // Clear attempts on successful login
            unset($attempts[$ip]);
            @file_put_contents($attempts_file, json_encode($attempts));
        } else {
            $error = \'Access denied. Administrator privileges required.\';
        }
    } else {
        // Record failed attempt
        if (!isset($attempts[$ip])) {
            $attempts[$ip] = [\'count\' => 0, \'time\' => $now];
        }
        $attempts[$ip][\'count\']++;
        $attempts[$ip][\'time\'] = $now;
        @file_put_contents($attempts_file, json_encode($attempts));
        $error = \'Invalid username or password. Attempt \' . $attempts[$ip][\'count\'] . \' of \' . $MAX_LOGIN_ATTEMPTS;
    }
}

// Handle logout
if (isset($_GET[\'logout\'])) {
    unset($_SESSION[\'nb_recovery_user_id\']);
    header(\'Location: \' . $_SERVER[\'PHP_SELF\']);
    exit;
}

// Base paths
$wp_content = __DIR__ . \'/wp-content\';
$plugins_dir = $wp_content . \'/plugins\';
$mu_plugins_dir = $wp_content . \'/mu-plugins\';
$themes_dir = $wp_content . \'/themes\';

// Handle actions
if ($logged_in && isset($_POST[\'action\'])) {
    $action = $_POST[\'action\'];
    $target = isset($_POST[\'target\']) ? basename($_POST[\'target\']) : \'\';
    $type = isset($_POST[\'type\']) ? $_POST[\'type\'] : \'plugin\';

    // Determine base directory
    switch ($type) {
        case \'mu_plugin\':
            $base_dir = $mu_plugins_dir;
            break;
        case \'theme\':
            $base_dir = $themes_dir;
            break;
        default:
            $base_dir = $plugins_dir;
    }

    $target_path = $base_dir . \'/\' . $target;

    switch ($action) {
        case \'disable\':
            if (file_exists($target_path)) {
                if (is_dir($target_path)) {
                    if (rename($target_path, $target_path . \'-DISABLED-\' . time())) {
                        $success = "Disabled: $target";
                    } else {
                        $error = "Failed to disable: $target (permission error?)";
                    }
                } else {
                    // Single file (like mu-plugins)
                    if (rename($target_path, $target_path . \'.disabled\')) {
                        $success = "Disabled: $target";
                    } else {
                        $error = "Failed to disable: $target (permission error?)";
                    }
                }
            }
            break;

        case \'enable\':
            // Check for various disabled naming patterns
            $disabled_patterns = [
                $target_path . \'-DISABLED-*\',
                $target_path . \'.disabled\'
            ];
            $found = false;
            foreach ($disabled_patterns as $pattern) {
                $matches = glob($pattern);
                if (!empty($matches)) {
                    $disabled_path = $matches[0];
                    $original = preg_replace(\'/-DISABLED-\d+$/\', \'\', $disabled_path);
                    $original = preg_replace(\'/\.disabled$/\', \'\', $original);
                    if (rename($disabled_path, $original)) {
                        $success = "Re-enabled: " . basename($original);
                        $found = true;
                    }
                    break;
                }
            }
            if (!$found && !file_exists($target_path)) {
                $error = "Could not find disabled version of: $target";
            }
            break;

        case \'delete\':
            if (file_exists($target_path)) {
                if (is_dir($target_path)) {
                    // Recursively delete directory
                    $deleted = nb_recovery_delete_dir($target_path);
                    if ($deleted) {
                        $success = "Deleted: $target";
                    } else {
                        $error = "Failed to delete: $target";
                    }
                } else {
                    if (unlink($target_path)) {
                        $success = "Deleted: $target";
                    } else {
                        $error = "Failed to delete: $target";
                    }
                }
            }
            break;

        case \'remove_maintenance\':
            $maintenance_file = __DIR__ . \'/.maintenance\';
            if (file_exists($maintenance_file) && unlink($maintenance_file)) {
                $success = "Maintenance mode disabled!";
            } else {
                $error = "Could not remove maintenance file";
            }
            break;

        case \'deactivate_all_plugins\':
            // Rename all plugin folders
            $plugins = glob($plugins_dir . \'/*\', GLOB_ONLYDIR);
            $count = 0;
            foreach ($plugins as $plugin) {
                $name = basename($plugin);
                if (strpos($name, \'-DISABLED-\') === false && $name !== \'nb-critical-block\') {
                    if (rename($plugin, $plugin . \'-DISABLED-\' . time())) {
                        $count++;
                    }
                }
            }
            $success = "Disabled $count plugins";
            break;

        case \'install_wpconfig_snippet\':
            // Install the early error handler snippet into wp-config.php
            $wpconfig_file = __DIR__ . \'/wp-config.php\';
            if (!file_exists($wpconfig_file)) {
                $error = "wp-config.php not found!";
                break;
            }

            $wpconfig_content = file_get_contents($wpconfig_file);

            // Check if already installed
            if (strpos($wpconfig_content, \'NETBOUND EARLY ERROR HANDLER\') !== false) {
                $error = "Snippet already installed in wp-config.php";
                break;
            }

            // Create backup first
            $backup_file = __DIR__ . \'/wp-config.php.backup-\' . date(\'Y-m-d-His\');
            if (!copy($wpconfig_file, $backup_file)) {
                $error = "Could not create backup of wp-config.php";
                break;
            }

            // Comment out existing WP_DEBUG lines (our snippet will add proper ones)
            $wpconfig_content = preg_replace(
                "/^(\\s*define\\s*\\(\\s*[\'\\"]WP_DEBUG[\'\\"].*?;)/m",
                "// Commented by NetBound - new debug settings in snippet below\\n// \\$1",
                $wpconfig_content
            );
            $wpconfig_content = preg_replace(
                "/^(\\s*define\\s*\\(\\s*[\'\\"]WP_DEBUG_LOG[\'\\"].*?;)/m",
                "// \\$1",
                $wpconfig_content
            );
            $wpconfig_content = preg_replace(
                "/^(\\s*define\\s*\\(\\s*[\'\\"]WP_DEBUG_DISPLAY[\'\\"].*?;)/m",
                "// \\$1",
                $wpconfig_content
            );

            // The snippet to add
            $snippet = \'
// ============================================================================
// NETBOUND EARLY ERROR HANDLER v2.15 - Auto-installed via Recovery Script
// ============================================================================

// WordPress Debug Settings (safe logging - errors go to file, NOT displayed to visitors)
define( \\\'WP_DEBUG\\\', true );           // Enable debug mode
define( \\\'WP_DEBUG_LOG\\\', true );       // Log errors to /wp-content/debug.log
define( \\\'WP_DEBUG_DISPLAY\\\', false );  // IMPORTANT: Don\\\'t show errors on screen!
@ini_set( \\\'display_errors\\\', 0 );      // Extra safety: hide PHP errors from visitors

// Enable error logging
@ini_set(\\\'display_errors\\\', 0);
@ini_set(\\\'log_errors\\\', 1);
@ini_set(\\\'error_log\\\', __DIR__ . \\\'/wp-content/netbound-php-errors.log\\\');

register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error[\\\'type\\\'], array(E_ERROR, E_PARSE, E_COMPILE_ERROR, E_CORE_ERROR))) {
        $log = sprintf("[%s] WPCONFIG CATCH: %s in %s on line %d\\\\n",
            date(\\\'Y-m-d H:i:s\\\'), $error[\\\'message\\\'], $error[\\\'file\\\'], $error[\\\'line\\\']);
        @file_put_contents(__DIR__ . \\\'/wp-content/netbound-critical-errors.log\\\', $log, FILE_APPEND);

        // Check mu-plugins first
        $mu_plugins_dir = __DIR__ . \\\'/wp-content/mu-plugins\\\';
        if (strpos($error[\\\'file\\\'], $mu_plugins_dir) === 0) {
            $relative = str_replace($mu_plugins_dir . \\\'/\\\', \\\'\\\', $error[\\\'file\\\']);
            $mu_plugin_file = explode(\\\'/\\\', $relative)[0];
            if ($mu_plugin_file !== \\\'nb-early-error-handler.php\\\') {
                $mu_plugin_path = $mu_plugins_dir . \\\'/\\\' . $mu_plugin_file;
                if (file_exists($mu_plugin_path)) {
                    @rename($mu_plugin_path, $mu_plugin_path . \\\'.disabled\\\');
                    $disabled_info = array(\\\'file\\\' => $mu_plugin_file, \\\'error\\\' => $error[\\\'message\\\'], \\\'line\\\' => $error[\\\'line\\\'], \\\'time\\\' => time());
                    $info_file = __DIR__ . \\\'/wp-content/netbound-disabled-mu-plugins.json\\\';
                    $existing = file_exists($info_file) ? json_decode(file_get_contents($info_file), true) : array();
                    if (!is_array($existing)) $existing = array();
                    $existing[] = $disabled_info;
                    @file_put_contents($info_file, json_encode($existing, JSON_PRETTY_PRINT));
                    if (function_exists(\\\'ob_end_clean\\\')) @ob_end_clean();
                    @header(\\\'Location: \\\' . $_SERVER[\\\'REQUEST_URI\\\']);
                    exit;
                }
            }
        }

        // Check regular plugins
        $plugins_dir = __DIR__ . \\\'/wp-content/plugins\\\';
        if (strpos($error[\\\'file\\\'], $plugins_dir) === 0) {
            $relative = str_replace($plugins_dir . \\\'/\\\', \\\'\\\', $error[\\\'file\\\']);
            $plugin_folder = explode(\\\'/\\\', $relative)[0];
            $plugin_path = $plugins_dir . \\\'/\\\' . $plugin_folder;
            if ($plugin_folder !== \\\'nb-critical-block\\\' && is_dir($plugin_path)) {
                @rename($plugin_path, $plugin_path . \\\'-DISABLED-\\\' . time());
            }
        }
    }
});
// ============================================================================
// END NETBOUND EARLY ERROR HANDLER
// ============================================================================

\';

            // Find where to insert (before require wp-settings.php)
            $insert_before = "require_once ABSPATH . \'wp-settings.php\';";
            $alt_insert_before = \'require_once( ABSPATH . \\\'wp-settings.php\\\' );\';

            if (strpos($wpconfig_content, $insert_before) !== false) {
                $wpconfig_content = str_replace($insert_before, $snippet . $insert_before, $wpconfig_content);
            } elseif (strpos($wpconfig_content, $alt_insert_before) !== false) {
                $wpconfig_content = str_replace($alt_insert_before, $snippet . $alt_insert_before, $wpconfig_content);
            } else {
                $error = "Could not find wp-settings.php require line in wp-config.php";
                break;
            }

            if (file_put_contents($wpconfig_file, $wpconfig_content)) {
                $success = "✅ wp-config.php snippet installed! Backup saved to: " . basename($backup_file);
            } else {
                $error = "Failed to write to wp-config.php - check permissions";
            }
            break;

        case \'toggle_debug\':
            // Toggle WP_DEBUG in wp-config.php
            $wpconfig_file = __DIR__ . \'/wp-config.php\';
            if (!file_exists($wpconfig_file)) {
                $error = "wp-config.php not found!";
                break;
            }

            $wpconfig_content = file_get_contents($wpconfig_file);

            // Check current debug state
            if (preg_match("/define\s*\(\s*[\'\\"]WP_DEBUG[\'\\"]\s*,\s*(true|false)\s*\)/i", $wpconfig_content, $matches)) {
                $current_state = strtolower($matches[1]) === \'true\';
                $new_state = $current_state ? \'false\' : \'true\';

                // Replace the WP_DEBUG line
                $wpconfig_content = preg_replace(
                    "/define\s*\(\s*[\'\\"]WP_DEBUG[\'\\"]\s*,\s*(true|false)\s*\)/i",
                    "define( \'WP_DEBUG\', $new_state )",
                    $wpconfig_content
                );

                // Also handle WP_DEBUG_LOG and WP_DEBUG_DISPLAY
                if ($new_state === \'true\') {
                    // Enable debug logging
                    if (strpos($wpconfig_content, \'WP_DEBUG_LOG\') === false) {
                        $wpconfig_content = preg_replace(
                            "/define\s*\(\s*[\'\\"]WP_DEBUG[\'\\"]\s*,\s*true\s*\)/i",
                            "define( \'WP_DEBUG\', true );\\ndefine( \'WP_DEBUG_LOG\', true );\\ndefine( \'WP_DEBUG_DISPLAY\', false )",
                            $wpconfig_content
                        );
                        // Remove the duplicate semicolon
                        $wpconfig_content = str_replace(\'; );\', \' )\', $wpconfig_content);
                    }
                }

                if (file_put_contents($wpconfig_file, $wpconfig_content)) {
                    $success = $new_state === \'true\'
                        ? "✅ Debug mode ENABLED - Errors will be logged to wp-content/debug.log"
                        : "✅ Debug mode DISABLED";
                } else {
                    $error = "Failed to write to wp-config.php";
                }
            } else {
                $error = "Could not find WP_DEBUG in wp-config.php";
            }
            break;

        case \'clear_log\':
            $log_file = isset($_POST[\'log_file\']) ? basename($_POST[\'log_file\']) : \'\';
            $allowed_logs = [\'debug.log\', \'netbound-php-errors.log\', \'netbound-critical-errors.log\', \'error_log\'];

            if (in_array($log_file, $allowed_logs)) {
                $full_path = $wp_content . \'/\' . $log_file;
                if (file_exists($full_path)) {
                    if (unlink($full_path)) {
                        $success = "Cleared: $log_file";
                    } else {
                        $error = "Could not delete $log_file";
                    }
                } else {
                    $error = "Log file not found: $log_file";
                }
            } else {
                $error = "Invalid log file";
            }
            break;

        case \'restore_wpconfig\':
            // Restore wp-config.php from a backup
            $backup_name = isset($_POST[\'backup_file\']) ? basename($_POST[\'backup_file\']) : \'\';
            if (empty($backup_name) || !preg_match(\'/^wp-config\\.php\\.backup-[0-9-]+$/\', $backup_name)) {
                $error = "Invalid backup file specified";
                break;
            }
            $backup_path = __DIR__ . \'/\' . $backup_name;
            $wpconfig_path = __DIR__ . \'/wp-config.php\';

            if (!file_exists($backup_path)) {
                $error = "Backup file not found: $backup_name";
                break;
            }

            // Before restoring, make a backup of the current (possibly corrupt) file
            $corrupt_backup = __DIR__ . \'/wp-config.php.corrupt-\' . date(\'Y-m-d-His\');
            if (file_exists($wpconfig_path)) {
                @copy($wpconfig_path, $corrupt_backup);
            }

            // Restore from backup
            if (copy($backup_path, $wpconfig_path)) {
                $success = "✅ wp-config.php restored from backup: $backup_name";
            } else {
                $error = "Failed to restore from backup - check file permissions";
            }
            break;

        case \'delete_wpconfig_backup\':
            // Delete a wp-config backup file
            $backup_name = isset($_POST[\'backup_file\']) ? basename($_POST[\'backup_file\']) : \'\';
            if (empty($backup_name) || !preg_match(\'/^wp-config\\.php\\.(backup|corrupt)-[0-9-]+$/\', $backup_name)) {
                $error = "Invalid backup file";
                break;
            }
            $backup_path = __DIR__ . \'/\' . $backup_name;
            if (file_exists($backup_path) && unlink($backup_path)) {
                $success = "Deleted: $backup_name";
            } else {
                $error = "Could not delete backup file";
            }
            break;
    }
}

// Get wp-config.php backup files
function nb_recovery_get_wpconfig_backups() {
    $backups = [];
    $pattern = __DIR__ . \'/wp-config.php.{backup,corrupt}-*\';
    $files = glob($pattern, GLOB_BRACE);
    if ($files) {
        foreach ($files as $file) {
            $name = basename($file);
            $backups[] = [
                \'name\' => $name,
                \'path\' => $file,
                \'size\' => size_format(filesize($file)),
                \'date\' => date(\'M j, Y g:i a\', filemtime($file)),
                \'is_corrupt\' => strpos($name, \'.corrupt-\') !== false
            ];
        }
        // Sort by date, newest first
        usort($backups, function($a, $b) {
            return filemtime($b[\'path\']) - filemtime($a[\'path\']);
        });
    }
    return $backups;
}

// Recursive delete helper
function nb_recovery_delete_dir($dir) {
    if (!is_dir($dir)) return false;
    $files = array_diff(scandir($dir), [\'.\', \'..\']);
    foreach ($files as $file) {
        $path = $dir . \'/\' . $file;
        is_dir($path) ? nb_recovery_delete_dir($path) : unlink($path);
    }
    return rmdir($dir);
}

// Get error logs
function nb_recovery_get_logs($wp_content) {
    $logs = [];
    $log_files = [
        \'debug.log\' => \'WordPress Debug Log\',
        \'netbound-php-errors.log\' => \'NetBound PHP Errors\',
        \'netbound-critical-errors.log\' => \'NetBound Critical Errors\',
        \'error_log\' => \'Server Error Log\'
    ];

    foreach ($log_files as $file => $label) {
        $path = $wp_content . \'/\' . $file;
        if (file_exists($path)) {
            $size = filesize($path);
            $modified = filemtime($path);
            // Read last 50KB of log (tail)
            $content = \'\';
            if ($size > 0) {
                $fp = fopen($path, \'r\');
                if ($size > 51200) {
                    fseek($fp, -51200, SEEK_END);
                    fgets($fp); // Skip partial line
                }
                $content = fread($fp, 51200);
                fclose($fp);
            }
            $logs[] = [
                \'file\' => $file,
                \'label\' => $label,
                \'size\' => $size,
                \'size_formatted\' => $size > 1048576 ? round($size/1048576, 1) . \' MB\' : round($size/1024, 1) . \' KB\',
                \'modified\' => date(\'Y-m-d H:i:s\', $modified),
                \'content\' => $content
            ];
        }
    }
    return $logs;
}

// Check if WP_DEBUG is enabled
function nb_recovery_is_debug_enabled() {
    $wpconfig_file = __DIR__ . \'/wp-config.php\';
    if (!file_exists($wpconfig_file)) return false;
    $content = file_get_contents($wpconfig_file);
    if (preg_match("/define\s*\(\s*[\'\\"]WP_DEBUG[\'\\"]\s*,\s*true\s*\)/i", $content)) {
        return true;
    }
    return false;
}

// Get list of items
function nb_recovery_get_items($dir, $type) {
    $items = [];
    if (!is_dir($dir)) return $items;

    $entries = scandir($dir);
    foreach ($entries as $entry) {
        if ($entry === \'.\' || $entry === \'..\') continue;

        $path = $dir . \'/\' . $entry;
        $is_disabled = (strpos($entry, \'-DISABLED-\') !== false || strpos($entry, \'.disabled\') !== false);
        $original_name = preg_replace(\'/-DISABLED-\d+$/\', \'\', $entry);
        $original_name = preg_replace(\'/\.disabled$/\', \'\', $original_name);

        $items[] = [
            \'name\' => $entry,
            \'original_name\' => $original_name,
            \'is_dir\' => is_dir($path),
            \'is_disabled\' => $is_disabled,
            \'type\' => $type,
            \'size\' => is_dir($path) ? \'folder\' : round(filesize($path) / 1024, 1) . \'KB\'
        ];
    }
    return $items;
}

// Check site status
$maintenance_active = file_exists(__DIR__ . \'/.maintenance\');

?><!DOCTYPE html>
<html>
<head>
    <title>NetBound Recovery</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <style>
        * { box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; margin: 0; padding: 20px; background: #1d2327; color: #f0f0f1; }
        .container { max-width: 1000px; margin: 0 auto; }
        h1 { color: #fff; border-bottom: 2px solid #2271b1; padding-bottom: 10px; }
        h2 { color: #c3c4c7; margin-top: 30px; }
        .card { background: #2c3338; border-radius: 4px; padding: 20px; margin-bottom: 20px; }
        .error { background: #d63638; color: #fff; padding: 10px 15px; border-radius: 4px; margin-bottom: 15px; }
        .success { background: #00a32a; color: #fff; padding: 10px 15px; border-radius: 4px; margin-bottom: 15px; }
        .warning { background: #dba617; color: #1d2327; padding: 10px 15px; border-radius: 4px; margin-bottom: 15px; }
        .info { background: #2271b1; color: #fff; padding: 10px 15px; border-radius: 4px; margin-bottom: 15px; }
        input[type="password"], input[type="text"] { width: 100%; padding: 10px; border: 1px solid #50575e; border-radius: 4px; background: #1d2327; color: #f0f0f1; margin-bottom: 10px; }
        button, .btn { padding: 8px 16px; border: none; border-radius: 4px; cursor: pointer; font-size: 14px; margin: 2px; }
        .btn-primary { background: #2271b1; color: #fff; }
        .btn-primary:hover { background: #135e96; }
        .btn-danger { background: #d63638; color: #fff; }
        .btn-danger:hover { background: #b32d2e; }
        .btn-warning { background: #dba617; color: #1d2327; }
        .btn-success { background: #00a32a; color: #fff; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #3c434a; }
        th { background: #1d2327; }
        .disabled { opacity: 0.6; font-style: italic; }
        .tag { display: inline-block; padding: 2px 8px; border-radius: 3px; font-size: 12px; margin-left: 5px; }
        .tag-disabled { background: #50575e; }
        .tag-active { background: #00a32a; }
        .logout { float: right; }
        .user-badge { background: #2271b1; padding: 4px 10px; border-radius: 3px; font-size: 13px; margin-right: 10px; }
        @media (max-width: 600px) {
            .actions { display: flex; flex-direction: column; }
            .actions button { width: 100%; margin: 2px 0; }
        }
    </style>
</head>
<body>
<div class="container">
    <h1>🛡️ NetBound Emergency Recovery</h1>

    <?php if (!$logged_in): ?>
        <div class="card">
            <h2>WordPress Admin Login</h2>
            <p style="color: #a7aaad;">Use your WordPress administrator username and password to access recovery tools.</p>
            <?php if ($error): ?><div class="error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
            <form method="post">
                <input type="text" name="username" placeholder="WordPress username or email" required autofocus>
                <input type="password" name="password" placeholder="Password" required>
                <button type="submit" class="btn btn-primary">Login</button>
            </form>
            <p style="margin-top: 15px; font-size: 12px; color: #72aee6;">💡 This is the same login you use for wp-admin</p>
        </div>
    <?php else: ?>
        <span class="user-badge">👤 <?php echo htmlspecialchars($username_display); ?></span>
        <a href="?logout=1" class="btn btn-danger logout">Logout</a>

        <?php if ($error): ?><div class="error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
        <?php if ($success): ?><div class="success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>

        <?php if ($maintenance_active): ?>
            <div class="warning">
                ⚠️ <strong>Maintenance Mode Active</strong> - Your site is showing "Briefly unavailable for scheduled maintenance"
                <form method="post" style="display: inline; margin-left: 10px;">
                    <input type="hidden" name="action" value="remove_maintenance">
                    <button type="submit" class="btn btn-warning">Remove Maintenance Mode</button>
                </form>
            </div>
        <?php endif; ?>

        <div class="card">
            <h2>⚡ Quick Actions</h2>
            <form method="post" style="display: inline;">
                <input type="hidden" name="action" value="deactivate_all_plugins">
                <button type="submit" class="btn btn-warning" onclick="return confirm(\'Disable ALL plugins except NetBound Critical Block?\');">Disable All Plugins</button>
            </form>
            <a href="/" class="btn btn-primary">Visit Site</a>
            <a href="/wp-admin/" class="btn btn-primary">WordPress Admin</a>
        </div>

        <!-- WP-CONFIG PROTECTION SECTION -->
        <?php
        $wpconfig_file = __DIR__ . \'/wp-config.php\';
        $wpconfig_has_snippet = file_exists($wpconfig_file) && strpos(file_get_contents($wpconfig_file), \'NETBOUND EARLY ERROR HANDLER\') !== false;
        ?>
        <div class="card" style="border-left: 4px solid <?php echo $wpconfig_has_snippet ? \'#00a32a\' : \'#dba617\'; ?>;">
            <h2>🛡️ wp-config.php Protection</h2>
            <?php if ($wpconfig_has_snippet): ?>
                <p style="color: #00a32a;"><strong>✓ INSTALLED</strong> - Early error handler is active in wp-config.php</p>
                <p style="color: #a7aaad; font-size: 13px;">This catches fatal errors in mu-plugins and plugins BEFORE WordPress loads, automatically disabling the crashing file.</p>
            <?php else: ?>
                <p style="color: #dba617;"><strong>⚠ NOT INSTALLED</strong> - Your site has no early protection against mu-plugin crashes!</p>
                <p style="color: #a7aaad; font-size: 13px;">Installing this adds code to wp-config.php that catches fatal errors before WordPress loads. A backup will be created automatically.</p>
                <form method="post" style="margin-top: 10px;">
                    <input type="hidden" name="action" value="install_wpconfig_snippet">
                    <button type="submit" class="btn btn-success" onclick="return confirm(\'Install protection snippet into wp-config.php?\\n\\nA backup will be created first.\');">Install wp-config.php Protection</button>
                </form>
            <?php endif; ?>

            <!-- wp-config.php Backups (Emergency Restore) -->
            <?php $wpconfig_backups = nb_recovery_get_wpconfig_backups(); ?>
            <?php if (!empty($wpconfig_backups)): ?>
            <details style="margin-top: 15px;">
                <summary style="cursor: pointer; color: #72aee6; font-weight: 600;">
                    🗄️ wp-config.php Backups (<?php echo count($wpconfig_backups); ?>)
                </summary>
                <div style="margin-top: 10px; background: #1d2327; border-radius: 4px; padding: 10px;">
                    <p style="color: #dba617; font-size: 12px; margin-bottom: 10px;">⚠️ <strong>Emergency restore:</strong> If your site crashed due to corrupt wp-config.php, restore from a backup here.</p>
                    <table style="font-size: 12px;">
                        <tr><th>Backup File</th><th>Size</th><th>Date</th><th>Actions</th></tr>
                        <?php foreach ($wpconfig_backups as $backup): ?>
                        <tr>
                            <td>
                                <?php if ($backup[\'is_corrupt\']): ?>
                                    <span style="color: #d63638;">💀</span>
                                <?php else: ?>
                                    <span style="color: #00a32a;">✓</span>
                                <?php endif; ?>
                                <?php echo htmlspecialchars($backup[\'name\']); ?>
                            </td>
                            <td><?php echo $backup[\'size\']; ?></td>
                            <td><?php echo $backup[\'date\']; ?></td>
                            <td class="actions">
                                <?php if (!$backup[\'is_corrupt\']): ?>
                                <form method="post" style="display: inline;">
                                    <input type="hidden" name="action" value="restore_wpconfig">
                                    <input type="hidden" name="backup_file" value="<?php echo htmlspecialchars($backup[\'name\']); ?>">
                                    <button type="submit" class="btn btn-warning" style="padding: 2px 8px; font-size: 11px;" onclick="return confirm(\'Restore wp-config.php from this backup?\\n\\nThe current file will be saved as .corrupt backup first.\');">Restore</button>
                                </form>
                                <?php endif; ?>
                                <form method="post" style="display: inline;">
                                    <input type="hidden" name="action" value="delete_wpconfig_backup">
                                    <input type="hidden" name="backup_file" value="<?php echo htmlspecialchars($backup[\'name\']); ?>">
                                    <button type="submit" class="btn btn-danger" style="padding: 2px 8px; font-size: 11px;" onclick="return confirm(\'Delete this backup?\');">Delete</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </table>
                </div>
            </details>
            <?php endif; ?>
        </div>

        <!-- MU-PLUGINS SECTION (Most Critical!) -->
        <div class="card" style="border-left: 4px solid #d63638;">
            <h2>🔴 Must-Use Plugins (mu-plugins)</h2>
            <p style="color: #dba617;"><strong>Warning:</strong> These load FIRST before anything else. Errors here can completely crash your site!</p>
            <?php
            $mu_plugins = nb_recovery_get_items($mu_plugins_dir, \'mu_plugin\');
            if (empty($mu_plugins)): ?>
                <p>No mu-plugins found.</p>
            <?php else: ?>
                <table>
                    <tr><th>File</th><th>Size</th><th>Status</th><th>Actions</th></tr>
                    <?php foreach ($mu_plugins as $item):
                        if ($item[\'name\'] === \'nb-early-error-handler.php\') continue; // Skip our own handler
                    ?>
                        <tr class="<?php echo $item[\'is_disabled\'] ? \'disabled\' : \'\'; ?>">
                            <td><?php echo htmlspecialchars($item[\'original_name\']); ?></td>
                            <td><?php echo $item[\'size\']; ?></td>
                            <td>
                                <?php if ($item[\'is_disabled\']): ?>
                                    <span class="tag tag-disabled">DISABLED</span>
                                <?php else: ?>
                                    <span class="tag tag-active">ACTIVE</span>
                                <?php endif; ?>
                            </td>
                            <td class="actions">
                                <form method="post" style="display: inline;">
                                    <input type="hidden" name="target" value="<?php echo htmlspecialchars($item[\'name\']); ?>">
                                    <input type="hidden" name="type" value="mu_plugin">
                                    <?php if ($item[\'is_disabled\']): ?>
                                        <input type="hidden" name="action" value="enable">
                                        <button type="submit" class="btn btn-success">Enable</button>
                                    <?php else: ?>
                                        <input type="hidden" name="action" value="disable">
                                        <button type="submit" class="btn btn-warning">Disable</button>
                                    <?php endif; ?>
                                </form>
                                <form method="post" style="display: inline;">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="target" value="<?php echo htmlspecialchars($item[\'name\']); ?>">
                                    <input type="hidden" name="type" value="mu_plugin">
                                    <button type="submit" class="btn btn-danger" onclick="return confirm(\'Permanently DELETE this file?\');">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            <?php endif; ?>
        </div>

        <!-- REGULAR PLUGINS SECTION -->
        <div class="card">
            <h2>🔌 Plugins</h2>
            <?php
            $plugins = nb_recovery_get_items($plugins_dir, \'plugin\');
            if (empty($plugins)): ?>
                <p>No plugins found.</p>
            <?php else: ?>
                <table>
                    <tr><th>Plugin</th><th>Type</th><th>Status</th><th>Actions</th></tr>
                    <?php foreach ($plugins as $item):
                        if ($item[\'name\'] === \'nb-critical-block\') continue; // Don\'t show ourselves
                    ?>
                        <tr class="<?php echo $item[\'is_disabled\'] ? \'disabled\' : \'\'; ?>">
                            <td><?php echo htmlspecialchars($item[\'original_name\']); ?></td>
                            <td><?php echo $item[\'is_dir\'] ? \'Folder\' : \'File\'; ?></td>
                            <td>
                                <?php if ($item[\'is_disabled\']): ?>
                                    <span class="tag tag-disabled">DISABLED</span>
                                <?php else: ?>
                                    <span class="tag tag-active">ACTIVE</span>
                                <?php endif; ?>
                            </td>
                            <td class="actions">
                                <form method="post" style="display: inline;">
                                    <input type="hidden" name="target" value="<?php echo htmlspecialchars($item[\'name\']); ?>">
                                    <input type="hidden" name="type" value="plugin">
                                    <?php if ($item[\'is_disabled\']): ?>
                                        <input type="hidden" name="action" value="enable">
                                        <button type="submit" class="btn btn-success">Enable</button>
                                    <?php else: ?>
                                        <input type="hidden" name="action" value="disable">
                                        <button type="submit" class="btn btn-warning">Disable</button>
                                    <?php endif; ?>
                                </form>
                                <form method="post" style="display: inline;">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="target" value="<?php echo htmlspecialchars($item[\'name\']); ?>">
                                    <input type="hidden" name="type" value="plugin">
                                    <button type="submit" class="btn btn-danger" onclick="return confirm(\'Permanently DELETE this plugin?\');">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            <?php endif; ?>
        </div>

        <!-- THEMES SECTION -->
        <div class="card">
            <h2>🎨 Themes</h2>
            <?php
            $themes = nb_recovery_get_items($themes_dir, \'theme\');
            if (empty($themes)): ?>
                <p>No themes found.</p>
            <?php else: ?>
                <table>
                    <tr><th>Theme</th><th>Status</th><th>Actions</th></tr>
                    <?php foreach ($themes as $item): ?>
                        <tr class="<?php echo $item[\'is_disabled\'] ? \'disabled\' : \'\'; ?>">
                            <td><?php echo htmlspecialchars($item[\'original_name\']); ?></td>
                            <td>
                                <?php if ($item[\'is_disabled\']): ?>
                                    <span class="tag tag-disabled">DISABLED</span>
                                <?php else: ?>
                                    <span class="tag tag-active">AVAILABLE</span>
                                <?php endif; ?>
                            </td>
                            <td class="actions">
                                <form method="post" style="display: inline;">
                                    <input type="hidden" name="target" value="<?php echo htmlspecialchars($item[\'name\']); ?>">
                                    <input type="hidden" name="type" value="theme">
                                    <?php if ($item[\'is_disabled\']): ?>
                                        <input type="hidden" name="action" value="enable">
                                        <button type="submit" class="btn btn-success">Enable</button>
                                    <?php else: ?>
                                        <input type="hidden" name="action" value="disable">
                                        <button type="submit" class="btn btn-warning">Disable</button>
                                    <?php endif; ?>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            <?php endif; ?>
        </div>

        <!-- DEBUG MODE SECTION -->
        <?php $debug_enabled = nb_recovery_is_debug_enabled(); ?>
        <div class="card" style="border-left: 4px solid <?php echo $debug_enabled ? \'#dba617\' : \'#50575e\'; ?>;">
            <h2>🐛 Debug Mode</h2>
            <p>
                <?php esc_html_e(\'Status:\', \'functional-functions\'); ?>
                <?php if ($debug_enabled): ?>
                    <strong style="color: #dba617;">⚠ ENABLED</strong> - Errors are being logged to wp-content/debug.log
                <?php else: ?>
                    <strong style="color: #a7aaad;">○ DISABLED</strong> - Normal production mode
                <?php endif; ?>
            </p>
            <form method="post" style="margin-top: 10px;">
                <input type="hidden" name="action" value="toggle_debug">
                <button type="submit" class="btn <?php echo $debug_enabled ? \'btn-warning\' : \'btn-primary\'; ?>">
                    <?php echo $debug_enabled ? \'Disable Debug Mode\' : \'Enable Debug Mode\'; ?>
                </button>
            </form>
            <p style="margin-top: 10px; font-size: 12px; color: #a7aaad;">
                💡 Enable debug mode to capture detailed error information. Remember to disable it on production sites when done troubleshooting.
            </p>
        </div>

        <!-- ERROR LOGS SECTION -->
        <?php $logs = nb_recovery_get_logs($wp_content); ?>
        <?php if (!empty($logs)): ?>
        <div class="card">
            <h2>📋 Error Logs</h2>
            <p style="color: #a7aaad;">View and manage error log files. Shows last 50KB of each log.</p>

            <?php foreach ($logs as $log): ?>
            <div style="margin-top: 20px; border: 1px solid #3c434a; border-radius: 4px; overflow: hidden;">
                <div style="background: #1d2327; padding: 10px 15px; display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <strong><?php echo htmlspecialchars($log[\'label\']); ?></strong>
                        <span style="color: #a7aaad; margin-left: 10px; font-size: 12px;">
                            <?php echo htmlspecialchars($log[\'file\']); ?> • <?php echo $log[\'size_formatted\']; ?> • <?php echo $log[\'modified\']; ?>
                        </span>
                    </div>
                    <form method="post" style="margin: 0;">
                        <input type="hidden" name="action" value="clear_log">
                        <input type="hidden" name="log_file" value="<?php echo htmlspecialchars($log[\'file\']); ?>">
                        <button type="submit" class="btn btn-danger" style="padding: 4px 10px; font-size: 12px;" onclick="return confirm(\'Delete this log file?\');">Clear</button>
                    </form>
                </div>
                <details>
                    <summary style="padding: 10px 15px; cursor: pointer; background: #2c3338; color: #72aee6;">Click to view log contents</summary>
                    <pre style="margin: 0; padding: 15px; background: #0a0a0a; color: #00ff00; font-size: 11px; max-height: 400px; overflow: auto; white-space: pre-wrap; word-wrap: break-word;"><?php echo htmlspecialchars($log[\'content\'] ?: \'(empty)\'); ?></pre>
                </details>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="card">
            <h2>📋 Error Logs</h2>
            <p style="color: #a7aaad;">No error logs found. This is good - it means no errors have been logged!</p>
            <p style="font-size: 12px; color: #72aee6;">💡 Enable Debug Mode above to start capturing errors.</p>
        </div>
        <?php endif; ?>

        <div class="card">
            <p style="color: #72aee6;">💡 <strong>Tip:</strong> If your site shows "critical error", try disabling plugins one by one starting with recently installed ones. MU-plugins are the most likely culprit for complete crashes!</p>
        </div>
    <?php endif; ?>
</div>
</body>
</html>';

    return $script;
}


// Backup critical configuration files
function netbound_tools_backup_config_files()
{
    $files_to_backup = array(
        ABSPATH . '.htaccess' => ABSPATH . '.htaccess.backup',
        ABSPATH . 'wp-config.php' => ABSPATH . 'wp-config.php.backup'
    );

    foreach ($files_to_backup as $source => $backup) {
        if (file_exists($source)) {
            // Only create backup if it doesn't exist or source is newer
            if (!file_exists($backup) || filemtime($source) > filemtime($backup)) {
                copy($source, $backup);
            }
        }
    }
}

// Check for and handle config file errors
function netbound_tools_check_config_file_errors()
{
    // Check if site is returning 500 error
    $site_url = home_url();
    $response = wp_remote_get($site_url, array('timeout' => 5, 'sslverify' => false));

    if (is_wp_error($response)) {
        return; // Can't check, network error
    }

    $response_code = wp_remote_retrieve_response_code($response);

    // If 500 error, try to restore config files
    if ($response_code === 500) {
        $restored = array();

        // Try restoring .htaccess
        $htaccess = ABSPATH . '.htaccess';
        $htaccess_backup = ABSPATH . '.htaccess.backup';
        if (file_exists($htaccess_backup)) {
            $current_hash = file_exists($htaccess) ? md5_file($htaccess) : '';
            $backup_hash = md5_file($htaccess_backup);

            if ($current_hash !== $backup_hash) {
                // Rename current broken file
                if (file_exists($htaccess)) {
                    rename($htaccess, ABSPATH . '.htaccess.broken-' . time());
                }
                copy($htaccess_backup, $htaccess);
                $restored[] = '.htaccess';
            }
        }

        // Try restoring wp-config.php
        $wpconfig = ABSPATH . 'wp-config.php';
        $wpconfig_backup = ABSPATH . 'wp-config.php.backup';
        if (file_exists($wpconfig_backup)) {
            $current_hash = file_exists($wpconfig) ? md5_file($wpconfig) : '';
            $backup_hash = md5_file($wpconfig_backup);

            if ($current_hash !== $backup_hash) {
                // Rename current broken file
                if (file_exists($wpconfig)) {
                    rename($wpconfig, ABSPATH . 'wp-config.php.broken-' . time());
                }
                copy($wpconfig_backup, $wpconfig);
                $restored[] = 'wp-config.php';
            }
        }

        if (!empty($restored)) {
            // Send notification
            netbound_tools_send_notification(
                'CRITICAL: Config File Restored',
                sprintf(
                    "A 500 Internal Server Error was detected!\n\nThe following configuration files have been restored from backup:\n%s\n\nBroken files have been renamed with timestamp.\n\nPlease review the changes and test your site.",
                    implode("\n", $restored)
                )
            );
        }
    }
}

// Hook to backup config files regularly
add_action('wp_loaded', 'netbound_tools_backup_config_files');

// Check config files health periodically (only in admin)
if (is_admin()) {
    add_action('admin_init', 'netbound_tools_check_config_file_errors');
}

// ============================================================================
// EARLY ERROR HANDLER COMPONENTS (v2.9.0)
// These components catch fatal errors BEFORE WordPress fully loads
// ============================================================================

/**
 * Get the must-use plugin content
 * This loads before ALL regular plugins and can catch errors in them
 */
function netbound_tools_get_mu_plugin_content() {
    return '<?php
/**
 * NetBound Early Error Handler (Must-Use Plugin)
 * Generated by NetBound Tools - Critical Block v2.12.0
 *
 * This file loads BEFORE regular plugins, allowing it to catch fatal errors
 * that would otherwise cause the WordPress critical error screen.
 *
 * DO NOT EDIT - This file is managed by the NetBound Tools plugin.
 * Generated: ' . date('Y-m-d H:i:s') . '
 */

// Prevent direct access
if (!defined("ABSPATH")) {
    exit;
}

// Set up early error logging
define("NETBOUND_EARLY_ERROR_LOG", WP_CONTENT_DIR . "/netbound-error-log.txt");

// Register shutdown function to catch fatal errors EARLY
register_shutdown_function("netbound_mu_shutdown_handler");

function netbound_mu_shutdown_handler() {
    $error = error_get_last();

    if ($error && in_array($error["type"], array(E_ERROR, E_PARSE, E_COMPILE_ERROR, E_CORE_ERROR))) {

        // Log the error
        $log_entry = sprintf(
            "[%s] FATAL ERROR: %s in %s on line %d\n",
            date("Y-m-d H:i:s"),
            $error["message"],
            $error["file"],
            $error["line"]
        );
        file_put_contents(NETBOUND_EARLY_ERROR_LOG, $log_entry, FILE_APPEND);

        $plugins_dir = WP_PLUGIN_DIR;
        $mu_plugins_dir = defined("WPMU_PLUGIN_DIR") ? WPMU_PLUGIN_DIR : WP_CONTENT_DIR . "/mu-plugins";

        // Check if error is in mu-plugins (MUST USE plugins) - CHECK FIRST!
        if (strpos($error["file"], $mu_plugins_dir) === 0) {
            netbound_mu_disable_mu_plugin($error, $mu_plugins_dir);
        }
        // Check if error is in regular plugins
        elseif (strpos($error["file"], $plugins_dir) === 0) {
            netbound_mu_disable_problem_plugin($error, $plugins_dir);
        }

        // Check if emergency bypass is requested
        if (isset($_GET["netbound_emergency"]) && $_GET["netbound_emergency"] === "1") {
            // Allow access despite error - useful for debugging
            return;
        }

        // Show a friendlier error page instead of white screen
        if (!headers_sent()) {
            http_response_code(503);
            netbound_mu_show_error_page($error);
            exit;
        }
    }
}

function netbound_mu_disable_mu_plugin($error, $mu_plugins_dir) {
    // Extract mu-plugin filename from error path
    $relative = str_replace($mu_plugins_dir . "/", "", $error["file"]);
    $mu_plugin_file = explode("/", $relative)[0];

    // Never disable nb-early-error-handler.php (this file!)
    if ($mu_plugin_file === "nb-early-error-handler.php") {
        return;
    }

    $plugin_path = $mu_plugins_dir . "/" . $mu_plugin_file;
    $disabled_path = $plugin_path . ".DISABLED-" . time();

    // Disable by renaming (works for files and folders)
    if (file_exists($plugin_path)) {
        @rename($plugin_path, $disabled_path);

        // Log the action
        $log_entry = sprintf(
            "[%s] AUTO-DISABLED mu-plugin: %s (renamed to %s)\n",
            date("Y-m-d H:i:s"),
            $mu_plugin_file,
            basename($disabled_path)
        );
        file_put_contents(NETBOUND_EARLY_ERROR_LOG, $log_entry, FILE_APPEND);

        // Store info for the main plugin to pick up
        $disabled_info = array(
            "file" => $mu_plugin_file,
            "disabled_path" => basename($disabled_path),
            "error" => $error["message"],
            "original_file" => $error["file"],
            "line" => $error["line"],
            "time" => time(),
            "source" => "mu-plugin-handler",
            "type" => "mu-plugin"
        );

        // Write to a file that the main plugin can read
        $info_file = WP_CONTENT_DIR . "/netbound-disabled-mu-plugins.json";
        $existing = array();
        if (file_exists($info_file)) {
            $existing = json_decode(file_get_contents($info_file), true) ?: array();
        }
        $existing[] = $disabled_info;
        file_put_contents($info_file, json_encode($existing, JSON_PRETTY_PRINT));
    }
}

function netbound_mu_disable_problem_plugin($error, $plugins_dir) {
    // Extract plugin folder from error path
    $relative = str_replace($plugins_dir . "/", "", $error["file"]);
    $plugin_folder = explode("/", $relative)[0];
    $plugin_path = $plugins_dir . "/" . $plugin_folder;

    // Never disable nb-critical-block
    if ($plugin_folder === "nb-critical-block") {
        return;
    }

    // Check if already disabled
    $disabled_marker = $plugin_path . "-DISABLED-";
    $existing_disabled = glob($plugins_dir . "/" . $plugin_folder . "-DISABLED-*");
    if (!empty($existing_disabled)) {
        return; // Already handled
    }

    // Disable the plugin by renaming
    $disabled_path = $plugin_path . "-DISABLED-" . time();
    if (is_dir($plugin_path)) {
        @rename($plugin_path, $disabled_path);

        // Log the action
        $log_entry = sprintf(
            "[%s] AUTO-DISABLED plugin: %s (renamed to %s)\n",
            date("Y-m-d H:i:s"),
            $plugin_folder,
            basename($disabled_path)
        );
        file_put_contents(NETBOUND_EARLY_ERROR_LOG, $log_entry, FILE_APPEND);

        // Store info for the main plugin to pick up
        $disabled_info = array(
            "folder" => $plugin_folder,
            "disabled_path" => basename($disabled_path),
            "error" => $error["message"],
            "file" => $error["file"],
            "line" => $error["line"],
            "time" => time(),
            "source" => "mu-plugin"
        );

        // Write to a file that the main plugin can read
        $info_file = WP_CONTENT_DIR . "/netbound-disabled-plugins.json";
        $existing = array();
        if (file_exists($info_file)) {
            $existing = json_decode(file_get_contents($info_file), true) ?: array();
        }
        $existing[] = $disabled_info;
        file_put_contents($info_file, json_encode($existing, JSON_PRETTY_PRINT));
    }
}

function netbound_mu_show_error_page($error) {
    $site_url = isset($_SERVER["HTTP_HOST"]) ? $_SERVER["HTTP_HOST"] : "your site";
    $bypass_url = (isset($_SERVER["HTTPS"]) ? "https" : "http") . "://" . $_SERVER["HTTP_HOST"] . "/?netbound_emergency=1";
    $admin_url = (isset($_SERVER["HTTPS"]) ? "https" : "http") . "://" . $_SERVER["HTTP_HOST"] . "/wp-admin/";

    echo \'<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Site Temporarily Unavailable - NetBound Recovery</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #1d2327; color: #f0f0f1; margin: 0; padding: 20px; min-height: 100vh; display: flex; align-items: center; justify-content: center; }
        .container { max-width: 600px; background: #2c3338; padding: 40px; border-radius: 8px; box-shadow: 0 4px 20px rgba(0,0,0,0.3); }
        h1 { color: #f0f0f1; margin: 0 0 20px; font-size: 28px; }
        .status { background: #d63638; color: white; padding: 10px 15px; border-radius: 4px; margin-bottom: 20px; }
        .status.recovering { background: #dba617; }
        .info { background: #3c434a; padding: 15px; border-radius: 4px; margin: 15px 0; font-family: monospace; font-size: 12px; word-break: break-all; }
        .actions { margin-top: 25px; }
        .btn { display: inline-block; padding: 12px 24px; margin: 5px; border-radius: 4px; text-decoration: none; font-weight: 600; }
        .btn-primary { background: #2271b1; color: white; }
        .btn-secondary { background: #3c434a; color: #f0f0f1; }
        .btn:hover { opacity: 0.9; }
        .footer { margin-top: 30px; font-size: 12px; color: #8c8f94; }
        .logo { font-size: 14px; color: #2271b1; margin-bottom: 10px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo">🛡️ NetBound Tools Recovery</div>
        <h1>Site Temporarily Unavailable</h1>
        <div class="status recovering">A fatal error was detected and is being handled automatically.</div>
        <p>The problem has been logged and the offending code may have been automatically disabled.</p>
        <div class="info">
            <strong>Error:</strong> \' . htmlspecialchars($error["message"]) . \'<br>
            <strong>File:</strong> \' . htmlspecialchars($error["file"]) . \'<br>
            <strong>Line:</strong> \' . $error["line"] . \'
        </div>
        <div class="actions">
            <a href="\' . htmlspecialchars($admin_url) . \'" class="btn btn-primary">Try Admin Dashboard</a>
            <a href="\' . htmlspecialchars($bypass_url) . \'" class="btn btn-secondary">Emergency Bypass</a>
        </div>
        <p class="footer">
            If you continue to see this page, the error may need manual intervention.<br>
            Check <code>wp-content/netbound-error-log.txt</code> for details.
        </p>
    </div>
</body>
</html>\';
}
?>';
}

/**
 * Get the wp-config.php snippet content
 * This is the EARLIEST possible error catching - before even mu-plugins load
 * v2.15: Now includes WP_DEBUG constants for safe logging (errors to file, not screen)
 */
function netbound_tools_get_wpconfig_snippet() {
    return "
// ============================================================================
// NETBOUND EARLY ERROR HANDLER v2.15 - Add this AFTER the 'define' statements
// but BEFORE the 'require_once ABSPATH . 'wp-settings.php'' line
// ============================================================================

// WordPress Debug Settings (safe logging - errors go to file, NOT displayed to visitors)
define( 'WP_DEBUG', true );           // Enable debug mode
define( 'WP_DEBUG_LOG', true );       // Log errors to /wp-content/debug.log
define( 'WP_DEBUG_DISPLAY', false );  // IMPORTANT: Don't show errors on screen!
@ini_set( 'display_errors', 0 );      // Extra safety: hide PHP errors from visitors

// Enable error logging
@ini_set('display_errors', 0);
@ini_set('log_errors', 1);
@ini_set('error_log', __DIR__ . '/wp-content/netbound-php-errors.log');

// Register ultra-early shutdown handler
register_shutdown_function(function() {
    \$error = error_get_last();
    if (\$error && in_array(\$error['type'], array(E_ERROR, E_PARSE, E_COMPILE_ERROR, E_CORE_ERROR))) {
        // Log to dedicated file
        \$log = sprintf(\"[%s] WPCONFIG CATCH: %s in %s on line %d\\n\",
            date('Y-m-d H:i:s'), \$error['message'], \$error['file'], \$error['line']);
        @file_put_contents(__DIR__ . '/wp-content/netbound-critical-errors.log', \$log, FILE_APPEND);

        // Check if error is in mu-plugins directory (HIGHEST PRIORITY)
        \$mu_plugins_dir = __DIR__ . '/wp-content/mu-plugins';
        if (strpos(\$error['file'], \$mu_plugins_dir) === 0) {
            \$relative = str_replace(\$mu_plugins_dir . '/', '', \$error['file']);
            \$mu_plugin_file = explode('/', \$relative)[0];

            // Don't disable our own error handler
            if (\$mu_plugin_file !== 'nb-early-error-handler.php') {
                \$mu_plugin_path = \$mu_plugins_dir . '/' . \$mu_plugin_file;

                if (file_exists(\$mu_plugin_path)) {
                    // Rename with .disabled extension
                    @rename(\$mu_plugin_path, \$mu_plugin_path . '.disabled');

                    // Save info about disabled mu-plugin for admin UI
                    \$disabled_info = array(
                        'file' => \$mu_plugin_file,
                        'original_file' => \$error['file'],
                        'error' => \$error['message'],
                        'line' => \$error['line'],
                        'time' => time()
                    );

                    \$info_file = __DIR__ . '/wp-content/netbound-disabled-mu-plugins.json';
                    \$existing = file_exists(\$info_file) ? json_decode(file_get_contents(\$info_file), true) : array();
                    if (!is_array(\$existing)) \$existing = array();
                    \$existing[] = \$disabled_info;
                    @file_put_contents(\$info_file, json_encode(\$existing, JSON_PRETTY_PRINT));

                    // Clear output and redirect
                    if (function_exists('ob_end_clean')) @ob_end_clean();
                    @header('HTTP/1.1 302 Found');
                    @header('Location: ' . (isset(\$_SERVER['REQUEST_URI']) ? \$_SERVER['REQUEST_URI'] : '/'));
                    exit;
                }
            }
        }

        // Check if error is in plugins directory
        \$plugins_dir = __DIR__ . '/wp-content/plugins';
        if (strpos(\$error['file'], \$plugins_dir) === 0) {
            \$relative = str_replace(\$plugins_dir . '/', '', \$error['file']);
            \$plugin_folder = explode('/', \$relative)[0];
            \$plugin_path = \$plugins_dir . '/' . \$plugin_folder;

            // Don't disable the recovery plugin itself
            if (\$plugin_folder !== 'nb-critical-block' && is_dir(\$plugin_path)) {
                @rename(\$plugin_path, \$plugin_path . '-DISABLED-' . time());
            }
        }
    }
});
// ============================================================================
// END NETBOUND EARLY ERROR HANDLER
// ============================================================================
";
}

/**
 * Check if must-use plugin is installed
 */
function netbound_tools_mu_plugin_installed() {
    $mu_path = WPMU_PLUGIN_DIR . '/nb-early-error-handler.php';
    return file_exists($mu_path);
}

/**
 * Install the must-use plugin
 */
function netbound_tools_install_mu_plugin() {
    // Create mu-plugins directory if it doesn't exist
    if (!is_dir(WPMU_PLUGIN_DIR)) {
        if (!wp_mkdir_p(WPMU_PLUGIN_DIR)) {
            return array('success' => false, 'message' => 'Could not create mu-plugins directory. Check permissions.');
        }
    }

    $mu_path = WPMU_PLUGIN_DIR . '/nb-early-error-handler.php';
    $content = netbound_tools_get_mu_plugin_content();

    if (file_put_contents($mu_path, $content)) {
        netbound_tools_send_notification(
            'Early Error Handler Installed',
            "The must-use plugin early error handler has been installed.\n\nLocation: " . $mu_path . "\n\nThis provides an additional layer of protection that catches errors BEFORE regular plugins load."
        );
        return array('success' => true, 'message' => 'Must-use plugin installed successfully!');
    }

    return array('success' => false, 'message' => 'Could not write must-use plugin file. Check permissions.');
}

/**
 * Remove the must-use plugin
 */
function netbound_tools_remove_mu_plugin() {
    $mu_path = WPMU_PLUGIN_DIR . '/nb-early-error-handler.php';

    if (file_exists($mu_path)) {
        if (unlink($mu_path)) {
            return array('success' => true, 'message' => 'Must-use plugin removed successfully.');
        }
        return array('success' => false, 'message' => 'Could not delete must-use plugin. Check permissions.');
    }

    return array('success' => true, 'message' => 'Must-use plugin was not installed.');
}

/**
 * Sync disabled plugins from mu-plugin's JSON file
 */
function netbound_tools_sync_disabled_plugins() {
    $info_file = WP_CONTENT_DIR . '/netbound-disabled-plugins.json';

    if (file_exists($info_file)) {
        $mu_disabled = json_decode(file_get_contents($info_file), true);

        if (!empty($mu_disabled) && is_array($mu_disabled)) {
            $existing = get_option('netbound_disabled_plugins', array());

            foreach ($mu_disabled as $plugin_info) {
                // Check if already in our list
                $found = false;
                foreach ($existing as $existing_plugin) {
                    if ($existing_plugin['folder'] === $plugin_info['folder']) {
                        $found = true;
                        break;
                    }
                }

                if (!$found) {
                    $existing[] = $plugin_info;

                    // Send notification about the disabled plugin
                    netbound_tools_send_notification(
                        'CRITICAL: Plugin Auto-Disabled (Early Handler)',
                        sprintf(
                            "EMERGENCY: A fatal error was caught by the early error handler!\n\nPlugin '%s' has been automatically disabled.\n\nError: %s\nFile: %s\nLine: %s\n\nYou can re-enable it from the NetBound Tools admin page after fixing the issue.",
                            $plugin_info['folder'],
                            $plugin_info['error'],
                            $plugin_info['file'],
                            $plugin_info['line']
                        )
                    );
                }
            }

            update_option('netbound_disabled_plugins', $existing);

            // Clear the JSON file
            unlink($info_file);
        }
    }
}

// Sync disabled plugins from mu-plugin's JSON file
add_action('admin_init', 'netbound_tools_sync_disabled_plugins');

// Sync disabled MU-plugins from mu-plugin's JSON file
add_action('admin_init', 'netbound_tools_sync_disabled_mu_plugins');

function netbound_tools_sync_disabled_mu_plugins() {
    $info_file = WP_CONTENT_DIR . '/netbound-disabled-mu-plugins.json';

    if (file_exists($info_file)) {
        $mu_disabled = json_decode(file_get_contents($info_file), true);

        if (!empty($mu_disabled) && is_array($mu_disabled)) {
            $existing = get_option('netbound_disabled_mu_plugins', array());

            foreach ($mu_disabled as $plugin_info) {
                // Check if already in our list
                $found = false;
                foreach ($existing as $existing_plugin) {
                    if ($existing_plugin['file'] === $plugin_info['file']) {
                        $found = true;
                        break;
                    }
                }

                if (!$found) {
                    $existing[] = $plugin_info;

                    // Send notification about the disabled mu-plugin
                    netbound_tools_send_notification(
                        'CRITICAL: MU-Plugin Auto-Disabled (Early Handler)',
                        sprintf(
                            "EMERGENCY: A fatal error was caught by the early error handler!\n\nMU-Plugin '%s' has been automatically disabled.\n\nError: %s\nFile: %s\nLine: %s\n\nYou can re-enable it from the NetBound Tools admin page after fixing the issue.",
                            $plugin_info['file'],
                            $plugin_info['error'],
                            $plugin_info['original_file'],
                            $plugin_info['line']
                        )
                    );
                }
            }

            update_option('netbound_disabled_mu_plugins', $existing);

            // Clear the JSON file
            unlink($info_file);
        }
    }
}

/**
 * Check wp-config.php for our snippet
 */
function netbound_tools_wpconfig_snippet_installed() {
    $wpconfig_path = ABSPATH . 'wp-config.php';
    if (!file_exists($wpconfig_path)) {
        return false;
    }

    $content = file_get_contents($wpconfig_path);
    return strpos($content, 'NETBOUND EARLY ERROR HANDLER') !== false;
}

/**
 * Get error log contents for display
 */
function netbound_tools_get_error_logs() {
    $logs = array();

    $log_files = array(
        'Early Handler Log' => WP_CONTENT_DIR . '/netbound-error-log.txt',
        'Critical Errors Log' => WP_CONTENT_DIR . '/netbound-critical-errors.log',
        'PHP Errors Log' => WP_CONTENT_DIR . '/netbound-php-errors.log',
        'Standard Debug Log' => WP_CONTENT_DIR . '/debug.log'
    );

    foreach ($log_files as $name => $path) {
        if (file_exists($path)) {
            $content = file_get_contents($path);
            // Get last 50 lines
            $lines = explode("\n", $content);
            $lines = array_slice($lines, -50);
            $logs[$name] = array(
                'path' => $path,
                'content' => implode("\n", $lines),
                'size' => filesize($path),
                'modified' => filemtime($path)
            );
        }
    }

    return $logs;
}
