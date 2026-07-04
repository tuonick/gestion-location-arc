<?php
/**
 * Privacy functions.
 *
 * @package LocArc
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter

add_filter( 'wp_privacy_personal_data_exporters', 'locarc_register_privacy_exporter' );
add_filter( 'wp_privacy_personal_data_erasers', 'locarc_register_privacy_eraser' );

function locarc_register_privacy_exporter( $exporters ) {
	$exporters['locarc'] = array(
		'exporter_friendly_name' => __( 'Location d\'Arc — Données membre', 'gestion-location-darc' ),
		'callback'               => 'locarc_privacy_exporter',
	);
	return $exporters;
}

function locarc_register_privacy_eraser( $erasers ) {
	$erasers['locarc'] = array(
		'eraser_friendly_name' => __( 'Location d\'Arc — Données membre', 'gestion-location-darc' ),
		'callback'             => 'locarc_privacy_eraser',
	);
	return $erasers;
}

function locarc_privacy_exporter( $email, $page = 1 ) {
	global $wpdb;
	$t = locarc_tables();
	unset( $page );

	$member = $wpdb->get_row(
		$wpdb->prepare( 'SELECT * FROM %i WHERE email = %s', $t['members'], $email ),
		ARRAY_A
	);

	$data_to_export = array();

	if ( $member ) {
		$data_to_export[] = array(
			'group_id'    => 'locarc-member',
			'group_label' => __( 'Données membre — Location d\'Arc', 'gestion-location-darc' ),
			'item_id'     => 'locarc-member-' . (int) $member['id'],
			'data'        => array(
				array(
					'name'  => __( 'Licence', 'gestion-location-darc' ),
					'value' => $member['licence'],
				),
				array(
					'name'  => __( 'Prénom', 'gestion-location-darc' ),
					'value' => $member['first_name'],
				),
				array(
					'name'  => __( 'Nom', 'gestion-location-darc' ),
					'value' => $member['last_name'],
				),
				array(
					'name'  => __( 'Date de naissance', 'gestion-location-darc' ),
					'value' => $member['dob'],
				),
				array(
					'name'  => __( 'Email', 'gestion-location-darc' ),
					'value' => $member['email'],
				),
				array(
					'name'  => __( 'Téléphone', 'gestion-location-darc' ),
					'value' => $member['phone'],
				),
				array(
					'name'  => __( 'Adresse', 'gestion-location-darc' ),
					'value' => $member['address1'],
				),
				array(
					'name'  => __( 'Code postal', 'gestion-location-darc' ),
					'value' => $member['postal_code'],
				),
				array(
					'name'  => __( 'Ville', 'gestion-location-darc' ),
					'value' => $member['city'],
				),
			),
		);

		$contracts = $wpdb->get_results(
			$wpdb->prepare( 'SELECT * FROM %i WHERE licence = %s', $t['contracts'], $member['licence'] ),
			ARRAY_A
		);

		foreach ( $contracts as $contract ) {
			$data_to_export[] = array(
				'group_id'    => 'locarc-contract',
				'group_label' => __( 'Contrats de location — Location d\'Arc', 'gestion-location-darc' ),
				'item_id'     => 'locarc-contract-' . (int) $contract['id'],
				'data'        => array(
					array(
						'name'  => __( 'Numéro de contrat', 'gestion-location-darc' ),
						'value' => $contract['contract_number'],
					),
					array(
						'name'  => __( 'Type', 'gestion-location-darc' ),
						'value' => $contract['contract_type'],
					),
					array(
						'name'  => __( 'Début', 'gestion-location-darc' ),
						'value' => $contract['start_date'],
					),
					array(
						'name'  => __( 'Fin', 'gestion-location-darc' ),
						'value' => $contract['end_date'],
					),
					array(
						'name'  => __( 'Statut', 'gestion-location-darc' ),
						'value' => $contract['status'],
					),
				),
			);
		}
	}

	return array(
		'data' => $data_to_export,
		'done' => true,
	);
}

function locarc_privacy_eraser( $email, $page = 1 ) {
	global $wpdb;
	$t = locarc_tables();
	unset( $page );

	$member = $wpdb->get_row(
		$wpdb->prepare( 'SELECT * FROM %i WHERE email = %s', $t['members'], $email ),
		ARRAY_A
	);

	$items_removed  = 0;
	$items_retained = 0;
	$messages       = array();

	if ( $member ) {
		// Unlink contracts from the member (keep records for accounting, remove personal identifier).
		$wpdb->update( $t['contracts'], array( 'licence' => '' ), array( 'licence' => $member['licence'] ), array( '%s' ), array( '%s' ) );

		// Delete the member record (all personal data).
		$wpdb->delete( $t['members'], array( 'id' => $member['id'] ), array( '%d' ) );
		++$items_removed;
	}

	return array(
		'items_removed'  => $items_removed,
		'items_retained' => $items_retained,
		'messages'       => $messages,
		'done'           => true,
	);
}
