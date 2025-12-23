<?php
// if uninstall.php is not called by WordPress, die
if (!defined('WP_UNINSTALL_PLUGIN')) {
    die;
}

delete_option('netbound_recovery_script_filename');
delete_option('netbound_recovery_password_hash');

// for site options in Multisite
delete_site_option('netbound_recovery_script_filename');
delete_site_option('netbound_recovery_password_hash');
