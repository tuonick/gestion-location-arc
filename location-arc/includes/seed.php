<?php
if (!defined('ABSPATH')) exit;
// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- All DB queries use locarc_tables() = $wpdb->prefix-based table names, never user input.

function locarc_seed_if_empty() {
    global $wpdb;
    $t = locarc_tables();

    $count = intval($wpdb->get_var("SELECT COUNT(*) FROM {$t['branches']}"));
    if ($count === 0) {
        $csv = LOCARC_PLUGIN_DIR . 'data/matos-branches.csv';
        if (file_exists($csv)) {
            locarc_import_matos_from_csv($csv, 'branches');
        }
    }
}
