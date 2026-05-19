<?php
if (!defined('ABSPATH')) exit;
// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- All DB queries use locarc_tables() = $wpdb->prefix-based table names, never user input.

/**
 * Import members from XLSX exported licences.
 * We avoid external libs; we expect the user to export CSV from Excel if PHPSpreadsheet is not available.
 * However, the plugin also supports a very small XLSX reader if PHP ZipArchive is enabled.
 */
function locarc_import_members_from_csv($csv_path) {
    global $wpdb;
    $t = locarc_tables();

    if (!file_exists($csv_path)) return new WP_Error('file_missing', 'Fichier introuvable');

    $fh = fopen($csv_path, 'r'); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- reading an uploaded CSV tmp file; WP_Filesystem is not appropriate for stream-based CSV parsing.
    if (!$fh) return new WP_Error('open_failed', 'Impossible de lire le fichier');

    // Detect delimiter ; or ,
    $first = fgets($fh);
    $delim = (substr_count($first, ';') > substr_count($first, ',')) ? ';' : ',';
    $headers = str_getcsv(trim($first), $delim);

    // Normalize headers to be robust to BOM, accents, casing, extra spaces.
    $norm = function($s) {
        $s = (string)$s;
        // Strip UTF-8 BOM if present.
        $s = preg_replace('/^\xEF\xBB\xBF/', '', $s);
        $s = trim($s);
        $s = mb_strtolower($s, 'UTF-8');

        // Replace common French accents (lightweight, no iconv dependency).
        $from = ['à','â','ä','á','ã','å','ç','é','è','ê','ë','í','ì','î','ï','ñ','ó','ò','ô','ö','õ','ú','ù','û','ü','ý','ÿ','œ','æ'];
        $to   = ['a','a','a','a','a','a','c','e','e','e','e','i','i','i','i','n','o','o','o','o','o','u','u','u','u','y','y','oe','ae'];
        $s = str_replace($from, $to, $s);

        // Keep only letters/numbers to tolerate "Code postal" vs "Code Postal" vs "Code_postal".
        $s = preg_replace('/[^a-z0-9]+/u', '', $s);
        return $s;
    };

    $map = [];
    foreach ($headers as $i => $h) {
        $k = $norm($h);
        if ($k === '') continue;
        // First wins.
        if (!isset($map[$k])) $map[$k] = $i;
    }

    // Support a few variants.
    $k_lic = null;
    foreach (['codeadherent','codeadherant','licence','numerodelicence','nodelicence'] as $k) {
        if (isset($map[$k])) { $k_lic = $k; break; }
    }
    $k_nom = null;
    foreach (['nom','lastname'] as $k) { if (isset($map[$k])) { $k_nom = $k; break; } }
    $k_pre = null;
    foreach (['prenom','firstname'] as $k) { if (isset($map[$k])) { $k_pre = $k; break; } }

    if (!$k_lic || !$k_nom || !$k_pre) {
        fclose($fh); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- closing a stream-based CSV handle; WP_Filesystem has no equivalent.
        return new WP_Error('bad_headers', 'En-têtes CSV inattendues. Colonnes requises : Code Adhérent (ou Licence), Nom, Prénom.');
    }

    // Optional columns.
    $k_dob = null; foreach (['datedenaissance','naissance','dob'] as $k) { if (isset($map[$k])) { $k_dob = $k; break; } }
    $k_email = null; foreach (['email','courriel','mail'] as $k) { if (isset($map[$k])) { $k_email = $k; break; } }
    $k_phone = null; foreach (['telephone','tel','mobile','portable'] as $k) { if (isset($map[$k])) { $k_phone = $k; break; } }
    $k_addr  = null; foreach (['adresse','address','adresse1'] as $k) { if (isset($map[$k])) { $k_addr = $k; break; } }
    $k_cp    = null; foreach (['codepostal','cp','postalcode'] as $k) { if (isset($map[$k])) { $k_cp = $k; break; } }
    $k_city  = null; foreach (['ville','city','commune'] as $k) { if (isset($map[$k])) { $k_city = $k; break; } }

    $count = 0;
    while (($row = fgetcsv($fh, 0, $delim)) !== false) {
        $lic = trim($row[$map[$k_lic]] ?? '');
        if ($lic === '') continue;

        $dob_raw = $k_dob ? trim($row[$map[$k_dob]] ?? '') : '';
        $dob = null;
        if ($dob_raw !== '') {
            // Try dd/mm/yyyy then yyyy-mm-dd
            if (preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})$#', $dob_raw, $m)) {
                $dob = sprintf('%04d-%02d-%02d', intval($m[3]), intval($m[2]), intval($m[1]));
            } elseif (preg_match('#^(\d{4})-(\d{1,2})-(\d{1,2})$#', $dob_raw, $m)) {
                $dob = sprintf('%04d-%02d-%02d', intval($m[1]), intval($m[2]), intval($m[3]));
            }
        }

        $data = [
            'licence' => $lic,
            'last_name' => trim($row[$map[$k_nom]] ?? ''),
            'first_name' => trim($row[$map[$k_pre]] ?? ''),
            'dob' => $dob,
            'email' => $k_email ? trim($row[$map[$k_email]] ?? '') : '',
            'phone' => $k_phone ? trim($row[$map[$k_phone]] ?? '') : '',
            'address1' => $k_addr ? trim($row[$map[$k_addr]] ?? '') : '',
            'postal_code' => $k_cp ? trim($row[$map[$k_cp]] ?? '') : '',
            'city' => $k_city ? trim($row[$map[$k_city]] ?? '') : '',
            'updated_at' => current_time('mysql'),
        ];

        $wpdb->replace($t['members'], $data, ['%s','%s','%s','%s','%s','%s','%s','%s','%s','%s']);
        $count++;
    }

    fclose($fh); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- closing a stream-based CSV handle; WP_Filesystem has no equivalent.
    return $count;
}

function locarc_import_matos_from_csv($csv_path, $kind) {
    global $wpdb;
    $t = locarc_tables();

    if (!in_array($kind, ['branches','handles'], true)) return new WP_Error('bad_kind', 'Type invalide');
    if (!file_exists($csv_path)) return new WP_Error('file_missing', 'Fichier introuvable');

    $fh = fopen($csv_path, 'r'); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- reading an uploaded CSV tmp file; WP_Filesystem is not appropriate for stream-based CSV parsing.
    if (!$fh) return new WP_Error('open_failed', 'Impossible de lire le fichier');

    $first = fgets($fh);
    $delim = (substr_count($first, ';') > substr_count($first, ',')) ? ';' : ',';
    $headers = str_getcsv(trim($first), $delim);

	// Normalize headers to tolerate curly apostrophes coming from Excel (“d’achat” vs "d'achat").
	$norm_h = function($h) {
		$h = (string) $h;
		$h = preg_replace('/^\xEF\xBB\xBF/', '', $h); // BOM
		$h = trim($h);
		$h = str_replace(["\u{2019}", '’'], "'", $h);
		$h = preg_replace('/\s+/', ' ', $h);
		return $h;
	};

	$map = [];
	foreach ($headers as $i => $h) {
		$k = $norm_h($h);
		if ($k === '') continue;
		if (!isset($map[$k])) $map[$k] = $i;
	}

	// Helper to get a column index with fallback aliases.
	$get_col = function($primary, $aliases = []) use ($map) {
		$keys = array_merge([$primary], $aliases);
		foreach ($keys as $k) {
			if (isset($map[$k])) return $map[$k];
		}
		return null;
	};

    $count = 0;
    while (($row = fgetcsv($fh, 0, $delim)) !== false) {
		$col_identifier = $get_col('Identificateur');
		if ($col_identifier === null) continue;
		$identifier = trim($row[$col_identifier] ?? '');
        if ($identifier === '') continue;

        if ($kind === 'branches') {
			$col_size = $get_col('Taille');
			$col_power = $get_col('Puissance');
			$col_dispo = $get_col('Dispo ?');
			$col_price = $get_col("Prix d'achat", ["Prix d’achat"]);
			$col_date = $get_col("Date d'achat", ["Date d’achat"]);

			$size = intval($row[$col_size] ?? 0);
			$power = intval($row[$col_power] ?? 0);
			$is_available = (mb_strtolower(trim($row[$col_dispo] ?? ''), 'UTF-8') === 'oui') ? 1 : 0;
			$price = ($col_price !== null) ? floatval(str_replace(',', '.', $row[$col_price] ?? '')) : null;
			$purchase_year = null;
			$date_raw = ($col_date !== null) ? trim($row[$col_date] ?? '') : '';
            if (preg_match('#(\d{4})#', $date_raw, $m)) $purchase_year = intval($m[1]);

			$col_brand = $get_col('Marque');
			$col_model = $get_col('Modèle');
			$wpdb->replace($t['branches'], [
                'identifier' => $identifier,
				'brand' => trim($row[$col_brand] ?? ''),
				'model' => trim($row[$col_model] ?? ''),
                'size' => $size,
                'power' => $power,
                'is_available' => $is_available,
                'purchase_year' => $purchase_year,
                'purchase_price' => $price,
                'updated_at' => current_time('mysql')
            ], ['%s','%s','%s','%d','%d','%d','%d','%f','%s']);
            $count++;
        } else {
            // Handles CSV expected columns: Identificateur, Marque, Modèle, Taille, Latéralité, Couleur, Dispo ?, Prix d’achat, Date d’achat
			$col_size = $get_col('Taille');
			$col_hand = $get_col('Latéralité');
			$col_color = $get_col('Couleur');
			$col_dispo = $get_col('Dispo ?');
			$col_price = $get_col("Prix d'achat", ["Prix d’achat"]);
			$col_date = $get_col("Date d'achat", ["Date d’achat"]);
			$col_brand = $get_col('Marque');
			$col_model = $get_col('Modèle');

			$size = intval($row[$col_size] ?? 0);
			$handedness = trim($row[$col_hand] ?? '');
            if ($handedness === '') $handedness = 'Droite';
			$is_available = (mb_strtolower(trim($row[$col_dispo] ?? ''), 'UTF-8') === 'oui') ? 1 : 0;
			$price = ($col_price !== null) ? floatval(str_replace(',', '.', $row[$col_price] ?? '')) : null;
            $purchase_year = null;
			$date_raw = ($col_date !== null) ? trim($row[$col_date] ?? '') : '';
            if (preg_match('#(\d{4})#', $date_raw, $m)) $purchase_year = intval($m[1]);

            $wpdb->replace($t['handles'], [
                'identifier' => $identifier,
				'brand' => trim($row[$col_brand] ?? ''),
				'model' => trim($row[$col_model] ?? ''),
                'size' => $size,
                'handedness' => $handedness,
				'color' => trim($row[$col_color] ?? ''),
                'is_available' => $is_available,
                'purchase_year' => $purchase_year,
                'purchase_price' => $price,
                'updated_at' => current_time('mysql')
            ], ['%s','%s','%s','%d','%s','%s','%d','%d','%f','%s']);
            $count++;
        }
    }

    fclose($fh); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- closing a stream-based CSV handle; WP_Filesystem has no equivalent.
    return $count;
}

/**
 * Import initial "Matériel loué" list (contracts + minimal members) from CSV.
 * Expected headers:
 * N°Licence;Nom;Prénom;Date fin de contrat;Type de contrat;Poignée;Taille;Latéralité;Branches;Taille Branches
 */
function locarc_import_rented_from_csv($csv_path) {
    global $wpdb;
    $t = locarc_tables();

    if (!file_exists($csv_path)) return new WP_Error('file_missing', 'Fichier introuvable');
    $fh = fopen($csv_path, 'r'); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- reading an uploaded CSV tmp file; WP_Filesystem is not appropriate for stream-based CSV parsing.
    if (!$fh) return new WP_Error('open_failed', 'Impossible de lire le fichier');

    $first = fgets($fh);
    if ($first === false) { fclose($fh); return new WP_Error('empty', 'Fichier vide'); } // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
    $delim = (substr_count($first, ';') > substr_count($first, ',')) ? ';' : ',';
    $headers = str_getcsv(trim($first), $delim);
    $map = [];
    foreach ($headers as $i => $h) $map[trim($h)] = $i;

    $required = ['N°Licence', 'Nom', 'Prénom', 'Date fin de contrat', 'Type de contrat'];
    foreach ($required as $r) {
        if (!isset($map[$r])) {
            fclose($fh); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- closing a stream-based CSV handle; WP_Filesystem has no equivalent.
            return new WP_Error('bad_headers', 'En-têtes CSV inattendues pour Matériel loué.');
        }
    }

    $type_map = [
        'Arc complet' => 'complet',
        'Arc nu' => 'arc_nu',
        'Jeune' => 'jeune',
        'Branches' => 'branches',
        'Personnalisé' => 'personnalise',
    ];

    $count = 0;
    while (($row = fgetcsv($fh, 0, $delim)) !== false) {
        $lic = trim($row[$map['N°Licence']] ?? '');
        if ($lic === '') continue;
        $last = trim($row[$map['Nom']] ?? '');
        $firstn = trim($row[$map['Prénom']] ?? '');

        // Upsert minimal member
        $wpdb->replace($t['members'], [
            'licence' => $lic,
            'last_name' => $last,
            'first_name' => $firstn,
            'updated_at' => current_time('mysql'),
        ], ['%s','%s','%s','%s']);

        // Parse end date dd/mm/yyyy
        $end_raw = trim($row[$map['Date fin de contrat']] ?? '');
        $end = null;
        if (preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})$#', $end_raw, $m)) {
            $end = sprintf('%04d-%02d-%02d', intval($m[3]), intval($m[2]), intval($m[1]));
        } elseif (preg_match('#^(\d{4})-(\d{1,2})-(\d{1,2})$#', $end_raw, $m)) {
            $end = sprintf('%04d-%02d-%02d', intval($m[1]), intval($m[2]), intval($m[3]));
        }
        if (!$end) continue;
        $start = wp_date('Y-m-d', strtotime('-1 year', strtotime($end)));

        $type_raw = trim($row[$map['Type de contrat']] ?? '');
        $type = $type_map[$type_raw] ?? 'personnalise';

        $handle = isset($map['Poignée']) ? trim($row[$map['Poignée']] ?? '') : '';
        $branches = isset($map['Branches']) ? trim($row[$map['Branches']] ?? '') : '';

        // Stable unique contract number for seed imports
        $now_import = current_datetime();
        $yy = $now_import->format('y');
        $mm = $now_import->format('m');
        $cn_base = 'L' . preg_replace('/[^A-Za-z0-9]/', '', $lic) . $yy . $mm;
        $cn = $cn_base;
        $n = 2;
        while ($wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$t['contracts']} WHERE contract_number=%s", $cn))) {
            $cn = $cn_base . '-' . $n;
            $n++;
        }

        $status = (strtotime($end) < strtotime(wp_date('Y-m-d'))) ? 'archived' : 'active';

        $wpdb->insert($t['contracts'], [
            'contract_number' => $cn,
            'licence' => $lic,
            'contract_type' => $type,
            'start_date' => $start,
            'end_date' => $end,
            'handle_identifier' => ($handle !== '' ? $handle : null),
            'branches_identifier' => ($branches !== '' ? $branches : null),
            'is_paid' => 0,
            'status' => $status,
            'updated_at' => current_time('mysql'),
        ], ['%s','%s','%s','%s','%s','%s','%s','%d','%s','%s']);

        $count++;
    }

    fclose($fh); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- closing a stream-based CSV handle; WP_Filesystem has no equivalent.
    return $count;
}
