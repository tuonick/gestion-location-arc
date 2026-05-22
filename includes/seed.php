<?php
if (!defined('ABSPATH')) exit;

// Seed data targets custom plugin tables; table names come from locarc_tables().
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter

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
