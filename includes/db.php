<?php
/**
 * Db functions.
 *
 * @package LocArc
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Custom plugin tables are created and maintained through dbDelta/$wpdb.
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange

function locarc_tables() {
	global $wpdb;
	$p = $wpdb->prefix . 'locarc_';
	return array(
		'branches'       => $p . 'branches',
		'handles'        => $p . 'handles',
		'sights'         => $p . 'sights',
		'stabilizations' => $p . 'stabilizations',
		'init_bows'      => $p . 'init_bows',
		'members'        => $p . 'members',
		'contracts'      => $p . 'contracts',
		'logs'           => $p . 'logs',
	);
}


function locarc_db_install() {
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	$charset = $wpdb->get_charset_collate();
	$t       = locarc_tables();

	$sql = array();

	// Table names come from $wpdb->prefix + fixed suffix — server-defined, never user input.
	// dbDelta() does not support $wpdb->prepare(), so table names are interpolated directly.
	$sql[] = "CREATE TABLE {$t['branches']} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        identifier VARCHAR(64) NOT NULL,
        size INT NOT NULL,
        power INT NOT NULL,
        brand VARCHAR(64) NULL,
        model VARCHAR(128) NULL,
        comment TEXT NULL,
        is_available TINYINT(1) NOT NULL DEFAULT 1,
        purchase_year INT NULL,
        purchase_price DECIMAL(10,2) NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NULL,
        PRIMARY KEY (id),
        UNIQUE KEY uq_identifier (identifier)
    ) $charset;";

	$sql[] = "CREATE TABLE {$t['handles']} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        identifier VARCHAR(64) NOT NULL,
        size INT NOT NULL,
        handedness VARCHAR(10) NOT NULL,
        brand VARCHAR(64) NULL,
        model VARCHAR(128) NULL,
        color VARCHAR(64) NULL,
        comment TEXT NULL,
        is_available TINYINT(1) NOT NULL DEFAULT 1,
        purchase_year INT NULL,
        purchase_price DECIMAL(10,2) NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NULL,
        PRIMARY KEY (id),
        UNIQUE KEY uq_identifier (identifier)
    ) $charset;";

	$sql[] = "CREATE TABLE {$t['sights']} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        identifier VARCHAR(64) NOT NULL,
        brand VARCHAR(64) NULL,
        model VARCHAR(128) NULL,
        handedness VARCHAR(10) NULL,
        comment TEXT NULL,
        is_available TINYINT(1) NOT NULL DEFAULT 1,
        purchase_year INT NULL,
        purchase_price DECIMAL(10,2) NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NULL,
        PRIMARY KEY (id),
        UNIQUE KEY uq_identifier (identifier)
    ) $charset;";

	$sql[] = "CREATE TABLE {$t['stabilizations']} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        identifier VARCHAR(64) NOT NULL,
        brand VARCHAR(64) NULL,
        model VARCHAR(128) NULL,
        comment TEXT NULL,
        is_available TINYINT(1) NOT NULL DEFAULT 1,
        purchase_year INT NULL,
        purchase_price DECIMAL(10,2) NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NULL,
        PRIMARY KEY (id),
        UNIQUE KEY uq_identifier (identifier)
    ) $charset;";

	$sql[] = "CREATE TABLE {$t['init_bows']} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        identifier VARCHAR(64) NOT NULL,
        brand VARCHAR(64) NULL,
        model VARCHAR(128) NULL,
        size INT NULL,
        power INT NULL,
        handedness VARCHAR(10) NULL,
        comment TEXT NULL,
        is_available TINYINT(1) NOT NULL DEFAULT 1,
        purchase_year INT NULL,
        purchase_price DECIMAL(10,2) NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NULL,
        PRIMARY KEY (id),
        UNIQUE KEY uq_identifier (identifier)
    ) $charset;";

	$sql[] = "CREATE TABLE {$t['members']} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        licence VARCHAR(32) NOT NULL,
        last_name VARCHAR(128) NULL,
        first_name VARCHAR(128) NULL,
        dob DATE NULL,
        email VARCHAR(190) NULL,
        phone VARCHAR(64) NULL,
        address1 VARCHAR(190) NULL,
        postal_code VARCHAR(32) NULL,
        city VARCHAR(128) NULL,
        updated_at DATETIME NULL,
        PRIMARY KEY (id),
        UNIQUE KEY uq_licence (licence)
    ) $charset;";

	// NOTE: dbDelta is extremely sensitive to inline SQL comments (e.g. "--").
	// Do NOT add comments inside CREATE TABLE statements, otherwise dbDelta may.
	// silently skip columns on install/upgrade.
	$sql[] = "CREATE TABLE {$t['contracts']} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        contract_number VARCHAR(32) NOT NULL,
        invoice_number VARCHAR(32) NULL,
        invoice_issued_at DATETIME NULL,
        licence VARCHAR(32) NOT NULL,
        contract_type VARCHAR(32) NOT NULL,
        custom_price DECIMAL(10,2) NULL,
        start_date DATE NOT NULL,
        end_date DATE NOT NULL,
        handle_identifier VARCHAR(64) NULL,
        branches_identifier VARCHAR(64) NULL,
        handle_brand VARCHAR(64) NULL,
        handle_model VARCHAR(128) NULL,
        handle_size INT NULL,
        handle_handedness VARCHAR(10) NULL,
        branches_brand VARCHAR(64) NULL,
        branches_model VARCHAR(128) NULL,
        branches_size INT NULL,
        branches_power INT NULL,
        init_bow_identifier VARCHAR(64) NULL,
        init_bow_brand VARCHAR(64) NULL,
        init_bow_model VARCHAR(128) NULL,
        init_bow_size INT NULL,
        init_bow_power INT NULL,
        init_bow_handedness VARCHAR(10) NULL,
        payment_method VARCHAR(16) NULL,
        caution_amount DECIMAL(10,2) NULL,
        payment_due_1 DATE NULL,
        payment_due_2 DATE NULL,
        payment_due_3 DATE NULL,
        payment_due_4 DATE NULL,
        is_paid TINYINT(1) NOT NULL DEFAULT 0,
        status VARCHAR(16) NOT NULL DEFAULT 'active',
        pdf_path VARCHAR(255) NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NULL,
        PRIMARY KEY (id),
        UNIQUE KEY uq_contract_number (contract_number),
        UNIQUE KEY uq_invoice_number (invoice_number),
        KEY idx_licence (licence),
        KEY idx_end_date (end_date),
        KEY idx_status (status)
    ) $charset;";

	$sql[] = "CREATE TABLE {$t['logs']} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        user_id BIGINT UNSIGNED NULL,
        user_label VARCHAR(190) NULL,
        object_type VARCHAR(32) NOT NULL,
        object_id BIGINT UNSIGNED NULL,
        object_label VARCHAR(190) NULL,
        action VARCHAR(32) NOT NULL,
        details LONGTEXT NULL,
        meta LONGTEXT NULL,
        PRIMARY KEY (id),
        KEY idx_created_at (created_at),
        KEY idx_object_type (object_type),
        KEY idx_action (action),
        KEY idx_object_id (object_id)
    ) $charset;";

	foreach ( $sql as $q ) {
		dbDelta( $q );
	}

	// Inventory tables: ensure "comment" exists even if dbDelta skipped.
	locarc_db_ensure_inventory_columns();

	// Extra safety: ensure expected columns exist even if dbDelta skipped them.
	// on previous versions.
	locarc_db_ensure_contracts_columns();
}

/**
 * Ensure inventory tables have expected columns.
 */
function locarc_db_ensure_inventory_columns() {
	global $wpdb;
	$t = locarc_tables();

	foreach ( array( 'branches', 'handles', 'sights', 'stabilizations', 'init_bows' ) as $kind ) {
		$table  = $t[ $kind ];
		$exists = $wpdb->get_var(
			$wpdb->prepare(
				'SHOW TABLES LIKE %s',
				$table
			)
		);
		if ( $table !== $exists ) {
			continue;
		}

		$cols = $wpdb->get_col( $wpdb->prepare( 'SHOW COLUMNS FROM %i', $table ), 0 );
		if ( ! is_array( $cols ) || empty( $cols ) ) {
			continue;
		}

		if ( ! in_array( 'comment', $cols, true ) ) {
			// Put comment near model/color for readability.
			if ( 'handles' === $kind ) {
				$after = 'color';
			} elseif ( 'sights' === $kind ) {
				$after = 'handedness';
			} elseif ( 'stabilizations' === $kind ) {
				$after = 'model';
			} elseif ( 'init_bows' === $kind ) {
				$after = 'handedness';
			} else {
				$after = 'model';
			}
			$wpdb->query( $wpdb->prepare( 'ALTER TABLE %i ADD COLUMN comment TEXT NULL AFTER %i', $table, $after ) );
		}
	}
}

/**
 * Ensure contracts cached-equipment columns exist (some installs created tables
 * without them because dbDelta can skip columns when SQL contains comments).
 */
function locarc_db_ensure_contracts_columns() {
	global $wpdb;
	$t     = locarc_tables();
	$table = $t['contracts'];

	// If table doesn't exist yet, nothing to do.
	$exists = $wpdb->get_var(
		$wpdb->prepare(
			'SHOW TABLES LIKE %s',
			$table
		)
	);
	if ( $table !== $exists ) {
		return;
	}

	$cols = $wpdb->get_col( $wpdb->prepare( 'SHOW COLUMNS FROM %i', $table ), 0 );
	if ( ! is_array( $cols ) || empty( $cols ) ) {
		return;
	}

	// Column => SQL definition (without `ADD COLUMN`).
	$wanted = array(
		'invoice_number'           => 'VARCHAR(32) NULL',
		'invoice_issued_at'        => 'DATETIME NULL',
		'handle_brand'             => 'VARCHAR(64) NULL',
		'handle_model'             => 'VARCHAR(128) NULL',
		'handle_size'              => 'INT NULL',
		'handle_handedness'        => 'VARCHAR(10) NULL',
		'branches_brand'           => 'VARCHAR(64) NULL',
		'branches_model'           => 'VARCHAR(128) NULL',
		'branches_size'            => 'INT NULL',
		'branches_power'           => 'INT NULL',
		'sight_identifier'         => 'VARCHAR(64) NULL',
		'sight_brand'              => 'VARCHAR(64) NULL',
		'sight_model'              => 'VARCHAR(128) NULL',
		'sight_handedness'         => 'VARCHAR(10) NULL',
		'stabilization_identifier' => 'VARCHAR(64) NULL',
		'stabilization_brand'      => 'VARCHAR(64) NULL',
		'stabilization_model'      => 'VARCHAR(128) NULL',
		'init_bow_identifier'      => 'VARCHAR(64) NULL',
		'init_bow_brand'           => 'VARCHAR(64) NULL',
		'init_bow_model'           => 'VARCHAR(128) NULL',
		'init_bow_size'            => 'INT NULL',
		'init_bow_power'           => 'INT NULL',
		'init_bow_handedness'      => 'VARCHAR(10) NULL',
		'payment_method'           => 'VARCHAR(16) NULL',
		'caution_amount'           => 'DECIMAL(10,2) NULL',
		'payment_due_1'            => 'DATE NULL',
		'payment_due_2'            => 'DATE NULL',
		'payment_due_3'            => 'DATE NULL',
		'payment_due_4'            => 'DATE NULL',
	);

	// Add missing columns. $def is validated against the static $wanted array above — never user input.
	$valid_defs = array_values( $wanted );
	$after      = 'branches_identifier';
	foreach ( $wanted as $col => $def ) {
		if ( in_array( $col, $cols, true ) ) {
			$after = $col;
			continue;
		}
		if ( ! in_array( $def, $valid_defs, true ) ) {
			$after = $col;
			continue;
		}
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $def is validated against a static hardcoded whitelist; SQL type definitions cannot use %s value placeholders.
		$wpdb->query( $wpdb->prepare( "ALTER TABLE %i ADD COLUMN %i $def AFTER %i", $table, $col, $after ) );
		$after = $col;
	}
}


function locarc_get_current_actor() {
	$user = wp_get_current_user();
	if ( ! $user || empty( $user->ID ) ) {
		return array(
			'id'    => null,
			'label' => 'Système',
		);
	}

	$label = trim( (string) get_user_meta( $user->ID, 'first_name', true ) );
	if ( '' === $label ) {
		$label = trim( (string) $user->display_name );
	}
	if ( '' === $label ) {
		$label = trim( (string) $user->user_login );
	}
	if ( '' === $label && ! empty( $user->user_email ) ) {
		$label = (string) $user->user_email;
	}
	if ( '' === $label ) {
		$label = 'Utilisateur #' . intval( $user->ID );
	}
	return array(
		'id'    => intval( $user->ID ),
		'label' => $label,
	);
}

function locarc_availability_label( $value ) {
	$map   = array(
		0 => 'Non',
		1 => 'Oui',
		2 => 'FLAG',
		3 => 'Obsolète',
		4 => 'En Réparation',
		5 => 'H-S',
	);
	$value = is_numeric( $value ) ? intval( $value ) : $value;
	return (string) ( $map[ $value ] ?? (string) $value );
}

function locarc_payment_method_label( $value ) {
	$map = array(
		''               => '—',
		'cheque'         => 'Chèque',
		'carte_bancaire' => 'Carte bancaire',
		'helloasso'      => 'HelloAsso',
		'especes'        => 'Espèces',
	);
	$key = strtolower( trim( (string) $value ) );
	return (string) ( $map[ $key ] ?? $value );
}

function locarc_contract_status_label( $value ) {
	$map = array(
		'active'   => 'Actif',
		'archived' => 'Archivé',
	);
	$key = strtolower( trim( (string) $value ) );
	return (string) ( $map[ $key ] ?? $value );
}

function locarc_log_object_type_label( $type ) {
	$map = array(
		'contract' => 'Contrat',
		'handle'   => 'Poignée',
		'branch'   => 'Branches',
	);
	return (string) ( $map[ $type ] ?? ucfirst( (string) $type ) );
}

function locarc_log_action_label( $action ) {
	$map = array(
		'create'         => 'Création',
		'update'         => 'Modification',
		'delete'         => 'Suppression',
		'archive'        => 'Archivage',
		'restore'        => 'Restauration',
		'renew'          => 'Renouvellement',
		'generate_pdf'   => 'Génération contrat',
		'send_email'     => 'Envoi contrat',
		'toggle_paid'    => 'Mise à jour paiement',
		'update_pricing' => 'Mise à jour tarif',
	);
	return (string) ( $map[ $action ] ?? ucfirst( str_replace( '_', ' ', (string) $action ) ) );
}

function locarc_log_format_value( $field, $value ) {
	if ( null === $value || '' === $value ) {
		return '—';
	}
	if ( in_array( $field, array( 'is_available' ), true ) ) {
		return locarc_availability_label( $value );
	}
	if ( in_array( $field, array( 'payment_method' ), true ) ) {
		return locarc_payment_method_label( $value );
	}
	if ( in_array( $field, array( 'status' ), true ) ) {
		return locarc_contract_status_label( $value );
	}
	if ( in_array( $field, array( 'contract_type' ), true ) ) {
		return locarc_contract_type_label( (string) $value );
	}
	if ( in_array( $field, array( 'is_paid' ), true ) ) {
		return intval( $value ) ? 'Oui' : 'Non';
	}
	if ( in_array( $field, array( 'custom_price', 'caution_amount', 'purchase_price' ), true ) ) {
		return number_format( (float) $value, 2, ',', ' ' ) . ' €';
	}
	return (string) $value;
}

function locarc_log_extract_changes( $before, $after, $field_labels ) {
	$changes = array();
	foreach ( $field_labels as $field => $label ) {
		$old = array_key_exists( $field, $before ) ? $before[ $field ] : null;
		$new = array_key_exists( $field, $after ) ? $after[ $field ] : null;

		if ( in_array( $field, array( 'custom_price', 'caution_amount', 'purchase_price' ), true ) ) {
			$old_cmp = ( null === $old || '' === $old ) ? null : round( (float) $old, 2 );
			$new_cmp = ( null === $new || '' === $new ) ? null : round( (float) $new, 2 );
		} else {
			$old_cmp = ( null === $old ? '' : (string) $old );
			$new_cmp = ( null === $new ? '' : (string) $new );
		}

		if ( $old_cmp === $new_cmp ) {
			continue;
		}

		$changes[] = $label . ' : ' . locarc_log_format_value( $field, $old ) . ' → ' . locarc_log_format_value( $field, $new );
	}
	return $changes;
}


function locarc_member_display_name_from_licence( $licence ) {
	global $wpdb;
	$licence = trim( (string) $licence );
	if ( '' === $licence ) {
		return '';
	}

	$t      = locarc_tables();
	$member = $wpdb->get_row( $wpdb->prepare( 'SELECT first_name, last_name FROM %i WHERE licence=%s', $t['members'], $licence ), ARRAY_A );

	if ( $member ) {
		$first = trim( (string) ( $member['first_name'] ?? '' ) );
		$last  = trim( (string) ( $member['last_name'] ?? '' ) );
		$name  = trim( $first . ' ' . $last );
		if ( '' !== $name ) {
			return $name;
		}
	}

	$user = get_user_by( 'login', $licence );
	if ( $user ) {
		$first = trim( (string) get_user_meta( $user->ID, 'first_name', true ) );
		$last  = trim( (string) get_user_meta( $user->ID, 'last_name', true ) );
		$name  = trim( $first . ' ' . $last );
		if ( '' !== $name ) {
			return $name;
		}

		$display = trim( (string) $user->display_name );
		if ( '' !== $display ) {
			return $display;
		}
	}

	return $licence;
}

function locarc_log_insert( $object_type, $action, $object_id = null, $object_label = '', $details = '', $meta = null, $user_id = null, $user_label = '' ) {
	global $wpdb;
	$t = locarc_tables();

	$actor = null;
	if ( null === $user_id && '' === $user_label ) {
		$actor = locarc_get_current_actor();
	}

	$row = array(
		'created_at'   => current_time( 'mysql' ),
		'user_id'      => ( null !== $user_id ? intval( $user_id ) : ( $actor['id'] ?? null ) ),
		'user_label'   => (string) ( '' !== $user_label ? $user_label : ( $actor['label'] ?? 'Système' ) ),
		'object_type'  => sanitize_key( (string) $object_type ),
		'object_id'    => ( null !== $object_id ? intval( $object_id ) : null ),
		'object_label' => sanitize_text_field( (string) $object_label ),
		'action'       => sanitize_key( (string) $action ),
		'details'      => (string) $details,
		'meta'         => ( null === $meta ? null : wp_json_encode( $meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ),
	);

	$formats = array( '%s', '%d', '%s', '%s', '%d', '%s', '%s', '%s', '%s' );
	$wpdb->insert( $t['logs'], $row, $formats );
	return intval( $wpdb->insert_id );
}

function locarc_log_contract_label_from_row( $row ) {
	if ( ! is_array( $row ) ) {
		return '';
	}

	$licence     = trim( (string) ( $row['licence'] ?? '' ) );
	$member_name = locarc_member_display_name_from_licence( $licence );
	if ( '' !== $member_name ) {
		return $member_name;
	}

	$number = trim( (string) ( $row['contract_number'] ?? '' ) );
	return '' !== $number ? $number : $licence;
}

function locarc_log_inventory_label_from_row( $row ) {
	if ( ! is_array( $row ) ) {
		return '';
	}
	$parts      = array();
	$identifier = trim( (string) ( $row['identifier'] ?? '' ) );
	if ( '' !== $identifier ) {
		$parts[] = $identifier;
	}
	$brand = trim( (string) ( $row['brand'] ?? '' ) );
	$model = trim( (string) ( $row['model'] ?? '' ) );
	$bm    = trim( $brand . ' ' . $model );
	if ( '' !== $bm ) {
		$parts[] = $bm;
	}
	return implode( ' — ', $parts );
}
