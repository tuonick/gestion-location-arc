<?php
/**
 * Import functions.
 *
 * @package LocArc
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Imports write to custom plugin tables and read temporary upload files validated upstream.
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter

/**
 * Import members from CSV exported licences.
 * Accepts CSV exports from the French archery federation (FFTir) software.
 * Required columns: Code Adherent (or Licence), Nom, Prenom.
 * Additional columns (date of birth, email, phone, address) are imported if present.
 * Both ; and , delimiters are supported; UTF-8 BOM is handled automatically.
 */
function locarc_import_members_from_csv( $csv_path ) {
	global $wpdb;
	$t = locarc_tables();

	if ( ! file_exists( $csv_path ) ) {
		return new WP_Error( 'file_missing', 'Fichier introuvable' );
	}

	if ( ! function_exists( 'WP_Filesystem' ) ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
	}
	global $wp_filesystem;
	WP_Filesystem();
	$raw = $wp_filesystem ? $wp_filesystem->get_contents( $csv_path ) : false;
	if ( false === $raw ) {
		return new WP_Error( 'open_failed', 'Impossible de lire le fichier' );
	}

	// Strip UTF-8 BOM and normalise line endings.
	if ( "\xEF\xBB\xBF" === substr( $raw, 0, 3 ) ) {
		$raw = substr( $raw, 3 );
	}
	$raw   = str_replace( array( "\r\n", "\r" ), "\n", $raw );
	$lines = explode( "\n", $raw );

	$first   = (string) array_shift( $lines );
	$delim   = ( substr_count( $first, ';' ) > substr_count( $first, ',' ) ) ? ';' : ',';
	$headers = str_getcsv( trim( $first ), $delim );

	// Normalize headers: strip BOM, lowercase, remove accents, keep only alphanumerics.
	$norm = function ( $s ) {
		$s = (string) $s;
		$s = preg_replace( '/^\xEF\xBB\xBF/', '', $s );
		$s = trim( $s );
		$s = mb_strtolower( $s, 'UTF-8' );
		// Lightweight French accent removal — no iconv dependency.
		$s = str_replace(
			array(
				"\xC3\xA0",
				"\xC3\xA2",
				"\xC3\xA4",
				"\xC3\xA1",
				"\xC3\xA3",
				"\xC3\xA5",
				"\xC3\xA7",
				"\xC3\xA9",
				"\xC3\xA8",
				"\xC3\xAA",
				"\xC3\xAB",
				"\xC3\xAD",
				"\xC3\xAC",
				"\xC3\xAE",
				"\xC3\xAF",
				"\xC3\xB1",
				"\xC3\xB3",
				"\xC3\xB2",
				"\xC3\xB4",
				"\xC3\xB6",
				"\xC3\xB5",
				"\xC3\xBA",
				"\xC3\xB9",
				"\xC3\xBB",
				"\xC3\xBC",
				"\xC3\xBD",
				"\xC3\xBF",
				"\xC5\x93",
				"\xC3\xA6",
			),
			array(
				'a',
				'a',
				'a',
				'a',
				'a',
				'a',
				'c',
				'e',
				'e',
				'e',
				'e',
				'i',
				'i',
				'i',
				'i',
				'n',
				'o',
				'o',
				'o',
				'o',
				'o',
				'u',
				'u',
				'u',
				'u',
				'y',
				'y',
				'oe',
				'ae',
			),
			$s
		);
		$s = preg_replace( '/[^a-z0-9]+/u', '', $s );
		return $s;
	};

	$map = array();
	foreach ( $headers as $i => $h ) {
		$k = $norm( $h );
		if ( '' === $k ) {
			continue;
		}
		if ( ! isset( $map[ $k ] ) ) {
			$map[ $k ] = $i; // First column wins.
		}
	}

	// Support several column name variants used by different FFTir exports.
	$k_lic = null;
	foreach ( array( 'codeadherent', 'codeadherant', 'licence', 'numerodelicence', 'nodelicence' ) as $k ) {
		if ( isset( $map[ $k ] ) ) {
			$k_lic = $k;
			break; }
	}
	$k_nom = null;
	foreach ( array( 'nom', 'lastname' ) as $k ) {
		if ( isset( $map[ $k ] ) ) {
			$k_nom = $k;
			break; }
	}
	$k_pre = null;
	foreach ( array( 'prenom', 'firstname' ) as $k ) {
		if ( isset( $map[ $k ] ) ) {
			$k_pre = $k;
			break; }
	}

	if ( ! $k_lic || ! $k_nom || ! $k_pre ) {
		return new WP_Error(
			'bad_headers',
			"En-t\xC3\xAAtes CSV inattendues. Colonnes requises\xC2\xA0: Code Adh\xC3\xA9rent (ou Licence), Nom, Pr\xC3\xA9nom."
		);
	}

	// Optional columns.
	$k_dob = null;
	foreach ( array( 'datedenaissance', 'naissance', 'dob' ) as $k ) {
		if ( isset( $map[ $k ] ) ) {
			$k_dob = $k;
			break; }
	}
	$k_email = null;
	foreach ( array( 'email', 'courriel', 'mail' ) as $k ) {
		if ( isset( $map[ $k ] ) ) {
			$k_email = $k;
			break; }
	}
	$k_phone = null;
	foreach ( array( 'telephone', 'tel', 'mobile', 'portable' ) as $k ) {
		if ( isset( $map[ $k ] ) ) {
			$k_phone = $k;
			break; }
	}
	$k_addr = null;
	foreach ( array( 'adresse', 'address', 'adresse1' ) as $k ) {
		if ( isset( $map[ $k ] ) ) {
			$k_addr = $k;
			break; }
	}
	$k_cp = null;
	foreach ( array( 'codepostal', 'cp', 'postalcode' ) as $k ) {
		if ( isset( $map[ $k ] ) ) {
			$k_cp = $k;
			break; }
	}
	$k_city = null;
	foreach ( array( 'ville', 'city', 'commune' ) as $k ) {
		if ( isset( $map[ $k ] ) ) {
			$k_city = $k;
			break; }
	}

	$count = 0;
	foreach ( $lines as $line ) {
		if ( trim( $line ) === '' ) {
			continue;
		}
		$row = str_getcsv( $line, $delim );
		$lic = trim( $row[ $map[ $k_lic ] ] ?? '' );
		if ( '' === $lic ) {
			continue;
		}

		// Date of birth: accept dd/mm/yyyy and yyyy-mm-dd.
		$dob_raw = $k_dob ? trim( $row[ $map[ $k_dob ] ] ?? '' ) : '';
		$dob     = null;
		if ( '' !== $dob_raw ) {
			if ( preg_match( '#^(\d{1,2})/(\d{1,2})/(\d{4})$#', $dob_raw, $m ) ) {
				$dob = sprintf( '%04d-%02d-%02d', intval( $m[3] ), intval( $m[2] ), intval( $m[1] ) );
			} elseif ( preg_match( '#^(\d{4})-(\d{1,2})-(\d{1,2})$#', $dob_raw, $m ) ) {
				$dob = sprintf( '%04d-%02d-%02d', intval( $m[1] ), intval( $m[2] ), intval( $m[3] ) );
			}
		}

		$data = array(
			'licence'     => $lic,
			'last_name'   => trim( $row[ $map[ $k_nom ] ] ?? '' ),
			'first_name'  => trim( $row[ $map[ $k_pre ] ] ?? '' ),
			'dob'         => $dob,
			'email'       => $k_email ? trim( $row[ $map[ $k_email ] ] ?? '' ) : '',
			'phone'       => $k_phone ? trim( $row[ $map[ $k_phone ] ] ?? '' ) : '',
			'address1'    => $k_addr ? trim( $row[ $map[ $k_addr ] ] ?? '' ) : '',
			'postal_code' => $k_cp ? trim( $row[ $map[ $k_cp ] ] ?? '' ) : '',
			'city'        => $k_city ? trim( $row[ $map[ $k_city ] ] ?? '' ) : '',
			'updated_at'  => current_time( 'mysql' ),
		);

		$wpdb->replace( $t['members'], $data, array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ) );
		++$count;
	}

	return $count;
}

/**
 * Import equipment (branches or handles) from CSV.
 * $kind must be 'branches' or 'handles'.
 */
function locarc_import_matos_from_csv( $csv_path, $kind ) {
	global $wpdb;
	$t = locarc_tables();

	if ( ! in_array( $kind, array( 'branches', 'handles' ), true ) ) {
		return new WP_Error( 'bad_kind', 'Type invalide' );
	}
	if ( ! file_exists( $csv_path ) ) {
		return new WP_Error( 'file_missing', 'Fichier introuvable' );
	}

	if ( ! function_exists( 'WP_Filesystem' ) ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
	}
	global $wp_filesystem;
	WP_Filesystem();
	$raw = $wp_filesystem ? $wp_filesystem->get_contents( $csv_path ) : false;
	if ( false === $raw ) {
		return new WP_Error( 'open_failed', 'Impossible de lire le fichier' );
	}

	// Strip UTF-8 BOM and normalise line endings.
	if ( "\xEF\xBB\xBF" === substr( $raw, 0, 3 ) ) {
		$raw = substr( $raw, 3 );
	}
	$raw   = str_replace( array( "\r\n", "\r" ), "\n", $raw );
	$lines = explode( "\n", $raw );

	$first   = (string) array_shift( $lines );
	$delim   = ( substr_count( $first, ';' ) > substr_count( $first, ',' ) ) ? ';' : ',';
	$headers = str_getcsv( trim( $first ), $delim );

	// Normalise headers: replace curly apostrophes from Excel (e.g. "d'achat" -> "d'achat").
	$norm_h = function ( $h ) {
		$h = (string) $h;
		$h = preg_replace( '/^\xEF\xBB\xBF/', '', $h );
		$h = trim( $h );
		// Replace RIGHT SINGLE QUOTATION MARK (U+2019) and LEFT SINGLE QUOTATION MARK (U+2018).
		// with a plain ASCII apostrophe so column lookups work regardless of Excel encoding.
		$h = str_replace( array( "\u{2019}", "\u{2018}" ), "'", $h );
		$h = preg_replace( '/\s+/', ' ', $h );
		return $h;
	};

	$map = array();
	foreach ( $headers as $i => $h ) {
		$k = $norm_h( $h );
		if ( '' === $k ) {
			continue;
		}
		if ( ! isset( $map[ $k ] ) ) {
			$map[ $k ] = $i;
		}
	}

	// Return the first matching column index from a list of candidate names.
	$get_col = function ( $primary, $aliases = array() ) use ( $map ) {
		foreach ( array_merge( array( $primary ), $aliases ) as $k ) {
			if ( isset( $map[ $k ] ) ) {
				return $map[ $k ];
			}
		}
		return null;
	};

	$count = 0;
	foreach ( $lines as $line ) {
		if ( '' === trim( $line ) ) {
			continue;
		}
		$row = str_getcsv( $line, $delim );

		$col_identifier = $get_col( 'Identificateur' );
		if ( null === $col_identifier ) {
			continue;
		}
		$identifier = trim( $row[ $col_identifier ] ?? '' );
		if ( '' === $identifier ) {
			continue;
		}

		if ( 'branches' === $kind ) {
			$col_size  = $get_col( 'Taille' );
			$col_power = $get_col( 'Puissance' );
			$col_dispo = $get_col( 'Dispo ?' );
			$col_price = $get_col( "Prix d'achat" );
			$col_date  = $get_col( "Date d'achat" );
			$col_brand = $get_col( 'Marque' );
			$col_model = $get_col( "Mod\xC3\xA8le" );

			$size          = intval( $row[ $col_size ] ?? 0 );
			$power         = intval( $row[ $col_power ] ?? 0 );
			$is_available  = ( 'oui' === mb_strtolower( trim( $row[ $col_dispo ] ?? '' ), 'UTF-8' ) ) ? 1 : 0;
			$price         = ( null !== $col_price ) ? floatval( str_replace( ',', '.', $row[ $col_price ] ?? '' ) ) : null;
			$purchase_year = null;
			$date_raw      = ( null !== $col_date ) ? trim( $row[ $col_date ] ?? '' ) : '';
			if ( preg_match( '#(\d{4})#', $date_raw, $m ) ) {
				$purchase_year = intval( $m[1] );
			}

			$wpdb->replace(
				$t['branches'],
				array(
					'identifier'     => $identifier,
					'brand'          => trim( $row[ $col_brand ] ?? '' ),
					'model'          => trim( $row[ $col_model ] ?? '' ),
					'size'           => $size,
					'power'          => $power,
					'is_available'   => $is_available,
					'purchase_year'  => $purchase_year,
					'purchase_price' => $price,
					'updated_at'     => current_time( 'mysql' ),
				),
				array( '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%f', '%s' )
			);
			++$count;
		} else {
			// Handles: Identificateur, Marque, Modele, Taille, Lateralite, Couleur, Dispo ?, Prix d'achat, Date d'achat.
			$col_size  = $get_col( 'Taille' );
			$col_hand  = $get_col( "Lat\xC3\xA9ralit\xC3\xA9" );
			$col_color = $get_col( 'Couleur' );
			$col_dispo = $get_col( 'Dispo ?' );
			$col_price = $get_col( "Prix d'achat" );
			$col_date  = $get_col( "Date d'achat" );
			$col_brand = $get_col( 'Marque' );
			$col_model = $get_col( "Mod\xC3\xA8le" );

			$size       = intval( $row[ $col_size ] ?? 0 );
			$handedness = trim( $row[ $col_hand ] ?? '' );
			if ( '' === $handedness ) {
				$handedness = 'Droite';
			}
			$is_available  = ( 'oui' === mb_strtolower( trim( $row[ $col_dispo ] ?? '' ), 'UTF-8' ) ) ? 1 : 0;
			$price         = ( null !== $col_price ) ? floatval( str_replace( ',', '.', $row[ $col_price ] ?? '' ) ) : null;
			$purchase_year = null;
			$date_raw      = ( null !== $col_date ) ? trim( $row[ $col_date ] ?? '' ) : '';
			if ( preg_match( '#(\d{4})#', $date_raw, $m ) ) {
				$purchase_year = intval( $m[1] );
			}

			$wpdb->replace(
				$t['handles'],
				array(
					'identifier'     => $identifier,
					'brand'          => trim( $row[ $col_brand ] ?? '' ),
					'model'          => trim( $row[ $col_model ] ?? '' ),
					'size'           => $size,
					'handedness'     => $handedness,
					'color'          => trim( $row[ $col_color ] ?? '' ),
					'is_available'   => $is_available,
					'purchase_year'  => $purchase_year,
					'purchase_price' => $price,
					'updated_at'     => current_time( 'mysql' ),
				),
				array( '%s', '%s', '%s', '%d', '%s', '%s', '%d', '%d', '%f', '%s' )
			);
			++$count;
		}
	}

	return $count;
}

/**
 * Import initial "Materiel loue" list (contracts + minimal members) from CSV.
 * Expected headers (semicolon-separated):
 * N Licence; Nom; Prenom; Date fin de contrat; Type de contrat; Poignee; Taille; Lateralite; Branches; Taille Branches
 */
function locarc_import_rented_from_csv( $csv_path ) {
	global $wpdb;
	$t = locarc_tables();

	if ( ! file_exists( $csv_path ) ) {
		return new WP_Error( 'file_missing', 'Fichier introuvable' );
	}

	if ( ! function_exists( 'WP_Filesystem' ) ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
	}
	global $wp_filesystem;
	WP_Filesystem();
	$raw = $wp_filesystem ? $wp_filesystem->get_contents( $csv_path ) : false;
	if ( false === $raw ) {
		return new WP_Error( 'open_failed', 'Impossible de lire le fichier' );
	}

	// Strip UTF-8 BOM and normalise line endings.
	if ( "\xEF\xBB\xBF" === substr( $raw, 0, 3 ) ) {
		$raw = substr( $raw, 3 );
	}
	$raw   = str_replace( array( "\r\n", "\r" ), "\n", $raw );
	$lines = explode( "\n", $raw );

	$first = (string) array_shift( $lines );
	if ( '' === $first ) {
		return new WP_Error( 'empty', 'Fichier vide' );
	}
	$delim   = ( substr_count( $first, ';' ) > substr_count( $first, ',' ) ) ? ';' : ',';
	$headers = str_getcsv( trim( $first ), $delim );
	$map     = array();
	foreach ( $headers as $i => $h ) {
		$map[ trim( $h ) ] = $i;
	}

	// Check required column headers are present.
	$required = array(
		"N\xC2\xB0Licence",
		'Nom',
		"Pr\xC3\xA9nom",
		'Date fin de contrat',
		'Type de contrat',
	);
	foreach ( $required as $r ) {
		if ( ! isset( $map[ $r ] ) ) {
			return new WP_Error( 'bad_headers', "En-t\xC3\xAAtes CSV inattendues pour Mat\xC3\xA9riel lou\xC3\xA9." );
		}
	}

	$type_map = array(
		'Arc complet'         => 'complet',
		'Arc nu'              => 'arc_nu',
		'Jeune'               => 'jeune',
		'Branches'            => 'branches',
		"Personnalis\xC3\xA9" => 'personnalise',
	);

	$count = 0;
	foreach ( $lines as $line ) {
		if ( trim( $line ) === '' ) {
			continue;
		}
		$row = str_getcsv( $line, $delim );
		$lic = trim( $row[ $map["N\xC2\xB0Licence"] ] ?? '' );
		if ( '' === $lic ) {
			continue;
		}
		$last   = trim( $row[ $map['Nom'] ] ?? '' );
		$firstn = trim( $row[ $map["Pr\xC3\xA9nom"] ] ?? '' );

		// Upsert minimal member record.
		$wpdb->replace(
			$t['members'],
			array(
				'licence'    => $lic,
				'last_name'  => $last,
				'first_name' => $firstn,
				'updated_at' => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s', '%s' )
		);

		// Parse end date: accept dd/mm/yyyy and yyyy-mm-dd.
		$end_raw = trim( $row[ $map['Date fin de contrat'] ] ?? '' );
		$end     = null;
		if ( preg_match( '#^(\d{1,2})/(\d{1,2})/(\d{4})$#', $end_raw, $m ) ) {
			$end = sprintf( '%04d-%02d-%02d', intval( $m[3] ), intval( $m[2] ), intval( $m[1] ) );
		} elseif ( preg_match( '#^(\d{4})-(\d{1,2})-(\d{1,2})$#', $end_raw, $m ) ) {
			$end = sprintf( '%04d-%02d-%02d', intval( $m[1] ), intval( $m[2] ), intval( $m[3] ) );
		}
		if ( ! $end ) {
			continue;
		}
		$start = wp_date( 'Y-m-d', strtotime( '-1 year', strtotime( $end ) ) );

		$type_raw = trim( $row[ $map['Type de contrat'] ] ?? '' );
		$type     = isset( $type_map[ $type_raw ] ) ? $type_map[ $type_raw ] : 'personnalise';

		$handle   = isset( $map["Poign\xC3\xA9e"] ) ? trim( $row[ $map["Poign\xC3\xA9e"] ] ?? '' ) : '';
		$branches = isset( $map['Branches'] ) ? trim( $row[ $map['Branches'] ] ?? '' ) : '';

		// Denormalize equipment characteristics from inventory (source of truth), the
		// same way the admin "save contract" handler does, so imported contracts show
		// brand/model/size/power in the generated PDF instead of blank values.
		$handle_brand      = null;
		$handle_model      = null;
		$handle_size       = null;
		$handle_handedness = null;
		if ( '' !== $handle ) {
			$h = $wpdb->get_row( $wpdb->prepare( 'SELECT brand, model, size, handedness FROM %i WHERE identifier=%s', $t['handles'], $handle ), ARRAY_A );
			if ( $h ) {
				$handle_brand      = $h['brand'] ?? null;
				$handle_model      = $h['model'] ?? null;
				$handle_size       = ( $h['size'] !== null && '' !== $h['size'] ) ? intval( $h['size'] ) : null;
				$handle_handedness = $h['handedness'] ?? null;
			}
		}
		$branches_brand = null;
		$branches_model = null;
		$branches_size  = null;
		$branches_power = null;
		if ( '' !== $branches ) {
			$b = $wpdb->get_row( $wpdb->prepare( 'SELECT brand, model, size, power FROM %i WHERE identifier=%s', $t['branches'], $branches ), ARRAY_A );
			if ( $b ) {
				$branches_brand = $b['brand'] ?? null;
				$branches_model = $b['model'] ?? null;
				$branches_size  = ( $b['size'] !== null && '' !== $b['size'] ) ? intval( $b['size'] ) : null;
				$branches_power = ( $b['power'] !== null && '' !== $b['power'] ) ? intval( $b['power'] ) : null;
			}
		}

		// Generate a stable unique contract number for seed imports.
		$now_import = current_datetime();
		$yy         = $now_import->format( 'y' );
		$mm         = $now_import->format( 'm' );
		$cn_base    = 'L' . preg_replace( '/[^A-Za-z0-9]/', '', $lic ) . $yy . $mm;
		$cn         = $cn_base;
		$n          = 2;
		while ( $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE contract_number=%s', $t['contracts'], $cn ) ) ) {
			$cn = $cn_base . '-' . $n;
			++$n;
		}

		$status = ( strtotime( $end ) < strtotime( wp_date( 'Y-m-d' ) ) ) ? 'archived' : 'active';

		$wpdb->insert(
			$t['contracts'],
			array(
				'contract_number'     => $cn,
				'licence'             => $lic,
				'contract_type'       => $type,
				'start_date'          => $start,
				'end_date'            => $end,
				'handle_identifier'   => ( '' !== $handle ? $handle : null ),
				'branches_identifier' => ( '' !== $branches ? $branches : null ),
				'handle_brand'        => $handle_brand,
				'handle_model'        => $handle_model,
				'handle_size'         => $handle_size,
				'handle_handedness'   => $handle_handedness,
				'branches_brand'      => $branches_brand,
				'branches_model'      => $branches_model,
				'branches_size'       => $branches_size,
				'branches_power'      => $branches_power,
				'is_paid'             => 0,
				'status'              => $status,
				'updated_at'          => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%d', '%d', '%d', '%s', '%s' )
		);

		++$count;
	}

	return $count;
}
