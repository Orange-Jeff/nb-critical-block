<?php
/**
 * NetBound Ecosystem Bootstrap
 * Shared activation logic for all NB plugins
 *
 * Include this file in your plugin's activation hook to ensure dashboard is installed/activated.
 * AND triggers the standardized check-for-updates on the dashboard.
 *
 * Usage in plugin:
 *   register_activation_hook(__FILE__, 'my_plugin_activate');
 *   function my_plugin_activate() {
 *       require_once dirname(__FILE__) . '/nb-ecosystem-bootstrap.php';
 *       nb_ecosystem_bootstrap('my-plugin', 'My Plugin', '1.0.0');
 *   }
 *
 * Version: 1.3.7
 * Date: 2026-06-05
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('nb_ecosystem_bootstrap')) {
    function nb_ecosystem_bootstrap($plugin_slug, $plugin_name, $plugin_version) {
        return nb_ecosystem_bootstrap_v2($plugin_slug, $plugin_name, $plugin_version);
    }
}

/**
 * Bootstrap the NetBound ecosystem - ensure hub is installed, active, and updated
 */
if (!function_exists('nb_ecosystem_bootstrap_v2')) {
    function nb_ecosystem_bootstrap_v2($plugin_slug, $plugin_name, $plugin_version) {
        $messages = [];
        $messages[] = "[Start] {$plugin_name} activation starting (Bootstrap v1.3.5)...";
        $target_hub_version = '6.5.3'; // Minimum required version

        // Load required WordPress functions
        if (!function_exists('is_plugin_active')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        if (!function_exists('WP_Filesystem')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }

        // Check for Hub (New path OR Legacy path)
        $hub_candidates = [
            'nb-hub/nb-hub.php',
            'nb-hub/nb-hub.php'
        ];
        
        $hub_file = '';
        $hub_path = '';
        foreach ($hub_candidates as $candidate) {
            if (file_exists(WP_PLUGIN_DIR . '/' . $candidate)) {
                $hub_file = $candidate;
                $hub_path = WP_PLUGIN_DIR . '/' . $candidate;
                break;
            }
        }

        $hub_active = false;

        if ($hub_path) {
            // Hub exists - Check version
            $plugin_data = get_file_data($hub_path, ['Version' => 'Version']);
            $hub_version = $plugin_data['Version'] ?? '0.0.0';
            $messages[] = "[Info] Found NetBound Hub v{$hub_version} ($hub_file)";

            // Try to read local manifest to get the latest available Hub version
            $manifest_file = WP_CONTENT_DIR . '/nb-manifest.json';
            $manifest_loaded = false;
            if (file_exists($manifest_file)) {
                $manifest_data = json_decode(file_get_contents($manifest_file), true);
                if (!empty($manifest_data['plugins']['nb-hub']['available'])) {
                    $avail = $manifest_data['plugins']['nb-hub']['available'];
                    if (version_compare($avail, $target_hub_version, '>')) {
                        $target_hub_version = $avail;
                    }
                    $manifest_loaded = true;
                } elseif (!empty($manifest_data['plugins']['nb-hub']['version'])) {
                    $ver = $manifest_data['plugins']['nb-hub']['version'];
                    if (version_compare($ver, $target_hub_version, '>')) {
                        $target_hub_version = $ver;
                    }
                    $manifest_loaded = true;
                }
            }

            // Fetch remote manifest if local is missing/stale to ensure we get the latest Hub version
            if (!$manifest_loaded) {
                $response = wp_remote_get('https://netbound.ca/downloads/plugins/nb-manifest.json', ['timeout' => 3]);
                if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
                    $body = wp_remote_retrieve_body($response);
                    $manifest_data = json_decode($body, true);
                    if (!empty($manifest_data['plugins']['nb-hub']['available'])) {
                        $avail = $manifest_data['plugins']['nb-hub']['available'];
                        if (version_compare($avail, $target_hub_version, '>')) {
                            $target_hub_version = $avail;
                        }
                    } elseif (!empty($manifest_data['plugins']['nb-hub']['version'])) {
                        $ver = $manifest_data['plugins']['nb-hub']['version'];
                        if (version_compare($ver, $target_hub_version, '>')) {
                            $target_hub_version = $ver;
                        }
                    }
                }
            }

            // Auto-Update if outdated
            if (version_compare($hub_version, $target_hub_version, '<')) {
                $messages[] = "[Update] Hub outdated (v{$hub_version} < v{$target_hub_version}) - Forcing update...";
                $install_result = nb_ecosystem_install_hub_v2($messages);

                if ($install_result['status'] === 'failed') {
                    $messages[] = "[Warning] Update failed: " . ($install_result['error'] ?? 'Unknown error');
                } else {
                    $messages[] = "[Success] Updated to v{$install_result['version']}";
                    $hub_version = $install_result['version'];
                    // Refresh path as it might have changed during update
                    $hub_file = 'nb-hub/nb-hub.php';
                    $hub_path = WP_PLUGIN_DIR . '/' . $hub_file;
                }
            }

            if (!is_plugin_active($hub_file)) {
                $messages[] = "[Activating] Activating hub...";
                if (nb_ecosystem_activate_hub_v2($hub_file, $messages)) {
                    $hub_active = true;
                } else {
                    set_transient("{$plugin_slug}_activation_failed", $messages, 300);
                    wp_die(
                        "[Error] {$plugin_name} requires NetBound Hub to be active.<br><br>" .
                        "Hub exists but failed to activate.<br><br>" .
                        '<a href="' . admin_url('plugins.php') . '">&larr; Back to Plugins</a>',
                        'Activation Failed',
                        ['back_link' => true]
                    );
                }
            } else {
                $messages[] = "[Success] Hub already active";
                $hub_active = true;
            }
        } else {
            // Hub not installed - try to install it
            $messages[] = "[Installing] NetBound Hub not found - attempting install...";
            $install_result = nb_ecosystem_install_hub_v2($messages);

            if ($install_result['status'] === 'failed') {
                set_transient("{$plugin_slug}_activation_failed", $messages, 300);
                wp_die(
                     "[Error] {$plugin_name} requires NetBound Hub.<br><br>" .
                     "Automatic installation failed: " . ($install_result['error'] ?? 'Unknown error') . "<br><br>" .
                     '<strong>Solution:</strong> Download and install <a href="https://netbound.ca/downloads/plugins/nb-hub.zip">NetBound Hub</a> first.<br><br>' .
                     '<a href="' . admin_url('plugins.php') . '">&larr; Back to Plugins</a>',
                     'Activation Failed',
                     ['back_link' => true]
                );
            }

            if ($install_result['status'] === 'installed-inactive') {
                set_transient("{$plugin_slug}_activation_failed", $messages, 300);
                wp_die(
                     "[Warning] NetBound Hub was installed but could not be activated.<br><br>" .
                     '<strong>Solution:</strong> <a href="' . admin_url('plugins.php') . '">Activate NetBound Hub</a> manually, then try again.<br><br>' .
                     '<a href="' . admin_url('plugins.php') . '">&larr; Back to Plugins</a>',
                     'Manual Activation Required',
                     ['back_link' => true]
                );
            }

            $messages[] = "[Success] Hub v{$install_result['version']} installed and activated";
            $hub_active = true;
        }

        // Register with hub (legacy functionality, kept for transient)
        if (function_exists('nb_register_plugin_activation')) {
            nb_register_plugin_activation($plugin_slug, $plugin_name, $plugin_version);
        }

        // Tell hub to begin check update routine
        set_transient('nb_trigger_update_check', true, 60);

        // SET ANNOUNCEMENT TRANSIENT
        $transient_slug = str_replace('-', '_', $plugin_slug);
        
        $is_updating = false;
        if (is_admin()) {
            $option_keys = [
                "nb_{$transient_slug}_version",
                "{$transient_slug}_version",
                "crash_block_version",
            ];
            $has_option = false;
            foreach ($option_keys as $key) {
                $val = get_option($key);
                if ($val !== false) {
                    $has_option = true;
                    if (version_compare($val, $plugin_version, '<')) {
                        $is_updating = true;
                        break;
                    }
                }
            }
            if (!$has_option) {
                $is_updating = true;
            }
        }

        $is_activating = (
            (isset($_REQUEST['action']) && $_REQUEST['action'] === 'activate') ||
            (isset($_REQUEST['action']) && $_REQUEST['action'] === 'activate-selected') ||
            doing_action('activate_' . $plugin_slug . '/' . $plugin_slug . '.php') ||
            doing_action('activated_plugin') ||
            $is_updating
        );
        if ($is_activating) {
            set_transient('nb_last_activated_plugin', [
                'name' => $plugin_name,
                'slug' => $plugin_slug,
                'version' => $plugin_version,
                'is_updating' => $is_updating,
                'timestamp' => time()
            ], 60);
        }

        $messages[] = "[Done] {$plugin_name} activation complete!";
        // Suppress old style transients to prevent notice spam (handled globally by Hub)
        // set_transient("{$transient_slug}_activated", $messages, 300);

        return ['success' => true, 'messages' => $messages];
    }
}

/**
 * Activate hub with fallbacks
 */
if (!function_exists('nb_ecosystem_activate_hub_v2')) {
    function nb_ecosystem_activate_hub_v2($hub_file, &$messages) {
        wp_cache_delete('plugins', 'plugins');
        if (function_exists('wp_clean_plugins_cache')) {
            wp_clean_plugins_cache();
        }

        $result = activate_plugin($hub_file, '', false, true);

        if (is_wp_error($result)) {
            $messages[] = "[Warning] Standard activation failed: " . $result->get_error_message();
            $messages[] = "[Activating] Trying manual activation...";

            $current = get_option('active_plugins', []);
            if (!in_array($hub_file, $current)) {
                $current[] = $hub_file;
                sort($current);
                update_option('active_plugins', $current);
                $messages[] = "[Success] Hub manually activated";
                return true;
            } else {
                $messages[] = "[Success] Hub was already in active list";
                return true;
            }
        }

        $messages[] = "[Success] Hub activated";
        return true;
    }
}

/**
 * Install hub from ZIP (King) or download (Prince)
 */
if (!function_exists('nb_ecosystem_install_hub_v2')) {
    function nb_ecosystem_install_hub_v2(&$messages) {
        $king_zip_paths = [
            dirname(dirname(__FILE__)) . '/nb-hub.zip',                      // Dev local zip folder
            dirname(dirname(__FILE__)) . '/nb-hub/nb-hub.zip',               // Inside hub folder fallback
            ABSPATH . 'downloads/plugins/nb-hub.zip',                        // Standard production path
            dirname(dirname(__FILE__)) . '/downloads/plugins/nb-hub.zip'     // Legacy fallback
        ];

        $local_zip = false;
        foreach ($king_zip_paths as $path) {
            if (file_exists($path)) {
                $local_zip = $path;
                break;
            }
        }

        if ($local_zip && filesize($local_zip) > 1000) {
            $messages[] = "[King Mode] Found local ZIP at " . basename($local_zip);
            return nb_ecosystem_extract_zip_v2($local_zip, $messages);
        }

        $zip_path = sys_get_temp_dir() . '/nb-hub-' . time() . '.zip';
        $download_url = 'https://netbound.ca/downloads/plugins/nb-hub.zip';

        $messages[] = "[Prince Mode] Downloading Hub from Kingdom (netbound.ca)...";
        error_log("NB Bootstrap: Downloading Hub from $download_url");

        $response = wp_remote_get($download_url, ['timeout' => 45]);
        if (is_wp_error($response)) {
            return ['status' => 'failed', 'version' => '0.0.0', 'error' => 'Download failed: ' . $response->get_error_message()];
        }

        $body = wp_remote_retrieve_body($response);
        if (empty($body) || strlen($body) < 1000) {
            // Try legacy download if new path fails
            $download_url = 'https://netbound.ca/downloads/plugins/nb-hub.zip';
            $response = wp_remote_get($download_url, ['timeout' => 45]);
            $body = wp_remote_retrieve_body($response);
            if (empty($body) || strlen($body) < 1000) {
                return ['status' => 'failed', 'version' => '0.0.0', 'error' => 'Empty/Invalid response from Kingdom'];
            }
        }

        file_put_contents($zip_path, $body);
        $result = nb_ecosystem_extract_zip_v2($zip_path, $messages);
        @unlink($zip_path);
        return $result;
    }
}

/**
 * Extract hub ZIP and activate
 */
if (!function_exists('nb_ecosystem_extract_zip_v2')) {
    function nb_ecosystem_extract_zip_v2($zip_path, &$messages) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/plugin.php';

        if ( ! WP_Filesystem() ) {
            return ['status' => 'failed', 'version' => '0.0.0', 'error' => 'Filesystem permission error'];
        }

        $messages[] = "[Extracting] Extracting ZIP...";
        $result = unzip_file($zip_path, WP_PLUGIN_DIR);

        if (is_wp_error($result)) {
            return ['status' => 'failed', 'version' => '0.0.0', 'error' => 'Extract failed: ' . $result->get_error_message()];
        }

        // Verify and activate (Check both new and old names)
        $candidates = ['nb-hub/nb-hub.php', 'nb-hub/nb-hub.php'];
        $found_file = '';
        foreach ($candidates as $candidate) {
            if (file_exists(WP_PLUGIN_DIR . '/' . $candidate)) {
                $found_file = $candidate;
                break;
            }
        }

        if ($found_file) {
            $plugin_data = get_file_data(WP_PLUGIN_DIR . '/' . $found_file, ['Version' => 'Version']);
            $version = $plugin_data['Version'] ?? '1.0.0';
            
            wp_cache_delete('plugins', 'plugins');
            if (function_exists('wp_clean_plugins_cache')) {
                wp_clean_plugins_cache();
            }

            $activate_result = activate_plugin($found_file, '', false, true);
            if (is_wp_error($activate_result)) {
                return ['status' => 'installed-inactive', 'version' => $version, 'error' => $activate_result->get_error_message()];
            }

            return ['status' => 'installed', 'version' => $version];
        }

        return ['status' => 'failed', 'version' => '0.0.0', 'error' => 'Hub file not found after extraction'];
    }
}
