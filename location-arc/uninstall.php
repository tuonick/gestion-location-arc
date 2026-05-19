<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * Removes all plugin options from wp_options.
 * Database tables are intentionally preserved to avoid accidental data loss.
 * To drop the tables manually, run the queries in includes/db.php with DROP TABLE.
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Variables are local to this uninstall script scope, not global.
$options = [
    'locarc_version',
    'locarc_responsable_materiel',
    'locarc_club_email',
    'locarc_club_header_text',
    'locarc_contract_types',
    'locarc_email_subject',
    'locarc_email_body',
    'locarc_email_from',
    'locarc_email_to',
    'locarc_email_cc',
    'locarc_email_bcc',
];

foreach ($options as $option) {
    delete_option($option);
}
