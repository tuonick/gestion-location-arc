<?php
/**
 * Uninstall functions.
 *
 * @package LocArc
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange

global $wpdb;


$locarc_tables = array(
	$wpdb->prefix . 'locarc_contracts',
	$wpdb->prefix . 'locarc_logs',
	$wpdb->prefix . 'locarc_members',
	$wpdb->prefix . 'locarc_branches',
	$wpdb->prefix . 'locarc_handles',
	$wpdb->prefix . 'locarc_sights',
	$wpdb->prefix . 'locarc_stabilizations',
	$wpdb->prefix . 'locarc_init_bows',
);

foreach ( $locarc_tables as $locarc_table ) {
	$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $locarc_table ) );
}

$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE option_name LIKE %s', $wpdb->options, $wpdb->esc_like( 'locarc_' ) . '%' ) );

$locarc_ts = wp_next_scheduled( 'locarc_daily_check' );
if ( $locarc_ts ) {
	wp_unschedule_event( $locarc_ts, 'locarc_daily_check' );
}
