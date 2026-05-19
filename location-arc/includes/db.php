<?php
if (!defined('ABSPATH')) exit;
// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- All DB queries use locarc_tables() = $wpdb->prefix-based table names, never user input.

function locarc_tables() {
    global $wpdb;
    $p = $wpdb->prefix . 'locarc_';
    return [
        'branches'  => $p . 'branches',
        'handles'   => $p . 'handles',
        'members'   => $p . 'members',
        'contracts' => $p . 'contracts',
        'logs'      => $p . 'logs',
    ];
}

function locarc_db_install() {
    global $wpdb;
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    $charset = $wpdb->get_charset_collate();
    $t = locarc_tables();

    $sql = [];

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
    // Do NOT add comments inside CREATE TABLE statements, otherwise dbDelta may
    // silently skip columns on install/upgrade.
    $sql[] = "CREATE TABLE {$t['contracts']} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        contract_number VARCHAR(32) NOT NULL,
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

    foreach ($sql as $q) {
        dbDelta($q);
    }

    // Inventory tables: ensure "comment" exists even if dbDelta skipped.
    locarc_db_ensure_inventory_columns();

    // Extra safety: ensure expected columns exist even if dbDelta skipped them
    // on previous versions.
    locarc_db_ensure_contracts_columns();
}

/**
 * Ensure inventory tables have expected columns.
 */
function locarc_db_ensure_inventory_columns() {
    global $wpdb;
    $t = locarc_tables();

    foreach (['branches', 'handles'] as $kind) {
        $table = $t[$kind];
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Schema introspection; no caching layer appropriate.
        $exists = $wpdb->get_var($wpdb->prepare(
            "SHOW TABLES LIKE %s",
            $table
        ));
        if ($exists !== $table) continue;

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $table comes from $wpdb->prefix, never user input.
        $cols = $wpdb->get_col("SHOW COLUMNS FROM {$table}", 0);
        if (!is_array($cols) || empty($cols)) continue;

        if (!in_array('comment', $cols, true)) {
            // Put comment near model/color for readability.
            $after = ($kind === 'handles') ? 'color' : 'model';
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $table/$after come from $wpdb->prefix and static string, never user input.
            $wpdb->query("ALTER TABLE {$table} ADD COLUMN comment TEXT NULL AFTER {$after}");
        }

    }
}

/**
 * Ensure contracts cached-equipment columns exist (some installs created tables
 * without them because dbDelta can skip columns when SQL contains comments).
 */
function locarc_db_ensure_contracts_columns() {
    global $wpdb;
    $t = locarc_tables();
    $table = $t['contracts'];

    // If table doesn't exist yet, nothing to do.
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Schema introspection; no caching layer appropriate.
    $exists = $wpdb->get_var($wpdb->prepare(
        "SHOW TABLES LIKE %s",
        $table
    ));
    if ($exists !== $table) return;

    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $table comes from $wpdb->prefix, never user input.
    $cols = $wpdb->get_col("SHOW COLUMNS FROM {$table}", 0);
    if (!is_array($cols) || empty($cols)) return;

    // Column => SQL definition (without `ADD COLUMN`)
    $wanted = [
        'handle_brand'       => "VARCHAR(64) NULL",
        'handle_model'       => "VARCHAR(128) NULL",
        'handle_size'        => "INT NULL",
        'handle_handedness'  => "VARCHAR(10) NULL",
        'branches_brand'     => "VARCHAR(64) NULL",
        'branches_model'     => "VARCHAR(128) NULL",
        'branches_size'      => "INT NULL",
        'branches_power'     => "INT NULL",
        'payment_method'      => "VARCHAR(16) NULL",
        'caution_amount'      => "DECIMAL(10,2) NULL",
        'payment_due_1'       => "DATE NULL",
        'payment_due_2'       => "DATE NULL",
        'payment_due_3'       => "DATE NULL",
        'payment_due_4'       => "DATE NULL",
    ];

    // Add missing columns (order is not critical for MySQL, but we keep it tidy).
    $after = 'branches_identifier';
    foreach ($wanted as $col => $def) {
        if (in_array($col, $cols, true)) {
            $after = $col; // keep chaining for nicer ordering if column already exists
            continue;
        }
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $table/$col/$def/$after come from $wpdb->prefix and hardcoded $wanted array, never user input.
        $wpdb->query("ALTER TABLE {$table} ADD COLUMN {$col} {$def} AFTER {$after}");
        $after = $col;
    }
}


function locarc_get_current_actor() {
    $user = wp_get_current_user();
    if (!$user || empty($user->ID)) {
        return ['id' => null, 'label' => 'Système'];
    }

    $label = trim((string) get_user_meta($user->ID, 'first_name', true));
    if ($label === '') {
        $label = trim((string) $user->display_name);
    }
    if ($label === '') {
        $label = trim((string) $user->user_login);
    }
    if ($label === '' && !empty($user->user_email)) {
        $label = (string) $user->user_email;
    }
    if ($label === '') $label = 'Utilisateur #' . intval($user->ID);
    return ['id' => intval($user->ID), 'label' => $label];
}

function locarc_availability_label($value) {
    $map = [
        0 => 'Non',
        1 => 'Oui',
        2 => 'FLAG',
        3 => 'Obsolète',
        4 => 'En Réparation',
        5 => 'H-S',
    ];
    $value = is_numeric($value) ? intval($value) : $value;
    return (string) ($map[$value] ?? (string) $value);
}

function locarc_payment_method_label($value) {
    $map = [
        '' => '—',
        'cheque' => 'Chèque',
        'carte_bancaire' => 'Carte bancaire',
        'helloasso' => 'HelloAsso',
        'especes' => 'Espèces',
    ];
    $key = strtolower(trim((string) $value));
    return (string) ($map[$key] ?? $value);
}

function locarc_contract_status_label($value) {
    $map = [
        'active' => 'Actif',
        'archived' => 'Archivé',
    ];
    $key = strtolower(trim((string) $value));
    return (string) ($map[$key] ?? $value);
}

function locarc_log_object_type_label($type) {
    $map = [
        'contract' => 'Contrat',
        'handle' => 'Poignée',
        'branch' => 'Branches',
    ];
    return (string) ($map[$type] ?? ucfirst((string) $type));
}

function locarc_log_action_label($action) {
    $map = [
        'create' => 'Création',
        'update' => 'Modification',
        'delete' => 'Suppression',
        'archive' => 'Archivage',
        'restore' => 'Restauration',
        'renew' => 'Renouvellement',
        'generate_pdf' => 'Génération contrat',
        'send_email' => 'Envoi contrat',
        'toggle_paid' => 'Mise à jour paiement',
        'update_pricing' => 'Mise à jour tarif',
    ];
    return (string) ($map[$action] ?? ucfirst(str_replace('_', ' ', (string) $action)));
}

function locarc_log_format_value($field, $value) {
    if ($value === null || $value === '') return '—';
    if (in_array($field, ['is_available'], true)) {
        return locarc_availability_label($value);
    }
    if (in_array($field, ['payment_method'], true)) {
        return locarc_payment_method_label($value);
    }
    if (in_array($field, ['status'], true)) {
        return locarc_contract_status_label($value);
    }
    if (in_array($field, ['contract_type'], true)) {
        return locarc_contract_type_label((string) $value);
    }
    if (in_array($field, ['is_paid'], true)) {
        return intval($value) ? 'Oui' : 'Non';
    }
    if (in_array($field, ['custom_price', 'caution_amount', 'purchase_price'], true)) {
        return number_format((float) $value, 2, ',', ' ') . ' €';
    }
    return (string) $value;
}

function locarc_log_extract_changes($before, $after, $field_labels) {
    $changes = [];
    foreach ($field_labels as $field => $label) {
        $old = array_key_exists($field, $before) ? $before[$field] : null;
        $new = array_key_exists($field, $after) ? $after[$field] : null;

        if (in_array($field, ['custom_price', 'caution_amount', 'purchase_price'], true)) {
            $old_cmp = ($old === null || $old === '') ? null : round((float) $old, 2);
            $new_cmp = ($new === null || $new === '') ? null : round((float) $new, 2);
        } else {
            $old_cmp = ($old === null ? '' : (string) $old);
            $new_cmp = ($new === null ? '' : (string) $new);
        }

        if ($old_cmp === $new_cmp) continue;

        $changes[] = $label . ' : ' . locarc_log_format_value($field, $old) . ' → ' . locarc_log_format_value($field, $new);
    }
    return $changes;
}


function locarc_member_display_name_from_licence($licence) {
    global $wpdb;
    $licence = trim((string) $licence);
    if ($licence === '') return '';

    $t = locarc_tables();
    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name comes from $wpdb->prefix, never user input.
    $member = $wpdb->get_row($wpdb->prepare(
        "SELECT first_name, last_name FROM {$t['members']} WHERE licence=%s",
        $licence
    ), ARRAY_A);

    if ($member) {
        $first = trim((string) ($member['first_name'] ?? ''));
        $last = trim((string) ($member['last_name'] ?? ''));
        $name = trim($first . ' ' . $last);
        if ($name !== '') return $name;
    }

    $user = get_user_by('login', $licence);
    if ($user) {
        $first = trim((string) get_user_meta($user->ID, 'first_name', true));
        $last = trim((string) get_user_meta($user->ID, 'last_name', true));
        $name = trim($first . ' ' . $last);
        if ($name !== '') return $name;

        $display = trim((string) $user->display_name);
        if ($display !== '') return $display;
    }

    return $licence;
}

function locarc_log_insert($object_type, $action, $object_id = null, $object_label = '', $details = '', $meta = null, $user_id = null, $user_label = '') {
    global $wpdb;
    $t = locarc_tables();

    $actor = null;
    if ($user_id === null && $user_label === '') {
        $actor = locarc_get_current_actor();
    }

    $row = [
        'created_at' => current_time('mysql'),
        'user_id' => ($user_id !== null ? intval($user_id) : ($actor['id'] ?? null)),
        'user_label' => (string) ($user_label !== '' ? $user_label : ($actor['label'] ?? 'Système')),
        'object_type' => sanitize_key((string) $object_type),
        'object_id' => ($object_id !== null ? intval($object_id) : null),
        'object_label' => sanitize_text_field((string) $object_label),
        'action' => sanitize_key((string) $action),
        'details' => (string) $details,
        'meta' => ($meta === null ? null : wp_json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
    ];

    $formats = ['%s','%d','%s','%s','%d','%s','%s','%s','%s'];
    $wpdb->insert($t['logs'], $row, $formats);
    return intval($wpdb->insert_id);
}

function locarc_log_contract_label_from_row($row) {
    if (!is_array($row)) return '';

    $licence = trim((string) ($row['licence'] ?? ''));
    $member_name = locarc_member_display_name_from_licence($licence);
    if ($member_name !== '') return $member_name;

    $number = trim((string) ($row['contract_number'] ?? ''));
    return $number !== '' ? $number : $licence;
}

function locarc_log_inventory_label_from_row($row) {
    if (!is_array($row)) return '';
    $parts = [];
    $identifier = trim((string) ($row['identifier'] ?? ''));
    if ($identifier !== '') $parts[] = $identifier;
    $brand = trim((string) ($row['brand'] ?? ''));
    $model = trim((string) ($row['model'] ?? ''));
    $bm = trim($brand . ' ' . $model);
    if ($bm !== '') $parts[] = $bm;
    return implode(' — ', $parts);
}

