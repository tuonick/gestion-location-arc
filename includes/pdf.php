<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Custom plugin tables are accessed through $wpdb; table names come from locarc_tables().
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter

/**
 * PDF generation using the bundled FPDF library.
 */

/**
 * Default club header text printed at the top of every contract PDF.
 * Editable in Réglages → Location d'Arc.
 */
function locarc_default_club_header() {
	return "ACSIM - Association de loi 1901 - École Française de Tir à l'Arc\n"
		. "Siège social : 5 avenue Jean Bouin - 92130 Issy-les-Moulineaux\n"
		. "Correspondance et Jeux d'Arc : 6 Boulevard des Frères Voisin - 92130 Issy-les-Moulineaux";
}

function locarc_default_contract_types() {
	return array(
		'complet'        => array(
			'label' => 'Complet',
			'price' => 200,
		),
		'arc_nu'         => array(
			'label' => 'Arc nu',
			'price' => 120,
		),
		'arc_initiation' => array(
			'label'  => "Arc d'Initiation",
			'price'  => 50,
			'module' => 'init_bows',
		),
		'jeune'          => array(
			'label' => 'Jeune',
			'price' => 160,
		),
		'branches'       => array(
			'label' => 'Branches',
			'price' => 80,
		),
		'personnalise'   => array(
			'label' => 'Personnalisé',
			'price' => 0,
		),
		'pret'           => array(
			'label' => 'Prêt',
			'price' => 0,
		),
	);
}

function locarc_contract_types_config() {
	$defaults = locarc_default_contract_types();
	$saved    = get_option( 'locarc_contract_types', array() );
	if ( ! is_array( $saved ) ) {
		$saved = array();
	}

	$config = array();
	foreach ( $defaults as $key => $def ) {
		$row            = is_array( $saved[ $key ] ?? null ) ? $saved[ $key ] : array();
		$label          = trim( (string) ( $row['label'] ?? $def['label'] ) );
		$price          = isset( $row['price'] ) && '' !== $row['price'] ? floatval( $row['price'] ) : floatval( $def['price'] );
		$config[ $key ] = array(
			'label' => '' !== $label ? $label : $def['label'],
			'price' => max( 0, $price ),
		);
	}
	return $config;
}

function locarc_contract_type_label( $type ) {
	$config = locarc_contract_types_config();
	return (string) ( $config[ $type ]['label'] ?? $type );
}

function locarc_contract_type_options_html( $selected = 'complet' ) {
	$config = locarc_contract_types_config();
	$html   = '';
	foreach ( $config as $key => $row ) {
		$label = (string) ( $row['label'] ?? $key );
		$price = floatval( $row['price'] ?? 0 );
		if ( 'personnalise' === $key ) {
			$text = $label;
		} else {
			$text = sprintf( '%s (%s€)', $label, number_format( $price, 0, ',', ' ' ) );
		}
		$html .= '<option value="' . esc_attr( $key ) . '"' . selected( $selected, $key, false ) . '>' . esc_html( $text ) . '</option>';
	}
	return $html;
}

function locarc_contract_price_eur( $type ) {
	$config = locarc_contract_types_config();
	return floatval( $config[ $type ]['price'] ?? 0 );
}

/**
 * Returns only contract types whose required module is enabled.
 * Use this for dropdowns; use locarc_contract_types_config() for settings pages.
 */
function locarc_contract_types_active() {
	$config   = locarc_contract_types_config();
	$defaults = locarc_default_contract_types();
	$result   = array();
	foreach ( $config as $key => $row ) {
		$module = $defaults[ $key ]['module'] ?? null;
		if ( $module && ! get_option( 'locarc_enable_' . $module, 0 ) ) {
			continue;
		}
		$result[ $key ] = $row;
	}
	return $result;
}

function locarc_build_contract_number( $first_name, $last_name, $dob ) {
	$fn_raw = mb_substr( trim( $first_name ), 0, 1, 'UTF-8' );
	$ln_raw = mb_substr( trim( $last_name ), 0, 1, 'UTF-8' );

	// iconv may not be available on all hosts — fall back to mb_strtoupper.
	if ( function_exists( 'iconv' ) ) {
		$fn = strtoupper( (string) iconv( 'UTF-8', 'ASCII//TRANSLIT', $fn_raw ) );
		$ln = strtoupper( (string) iconv( 'UTF-8', 'ASCII//TRANSLIT', $ln_raw ) );
	} else {
		$fn = mb_strtoupper( $fn_raw, 'UTF-8' );
		$ln = mb_strtoupper( $ln_raw, 'UTF-8' );
	}
	// Keep only A-Z characters.
	$fn = preg_replace( '/[^A-Z]/', '', $fn );
	$ln = preg_replace( '/[^A-Z]/', '', $ln );

	$day = '00';
	if ( $dob ) {
		$ts = strtotime( $dob );
		if ( $ts ) {
			$day = wp_date( 'd', $ts );
		}
	}
	$now = current_datetime();
	$yy  = $now->format( 'y' );
	$mm  = $now->format( 'm' );
	return "{$fn}{$ln}{$day}{$yy}{$mm}";
}

function locarc_upload_base_dir() {
	$upload = wp_upload_dir();
	$dir    = trailingslashit( $upload['basedir'] ) . 'location-arc';
	if ( ! file_exists( $dir ) ) {
		wp_mkdir_p( $dir );
	}
	return $dir;
}

function locarc_upload_contracts_dir() {
	$dir = trailingslashit( locarc_upload_base_dir() ) . 'contracts';
	if ( ! file_exists( $dir ) ) {
		wp_mkdir_p( $dir );
	}
	// Protect directory from direct HTTP access.
	if ( ! function_exists( 'WP_Filesystem' ) ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
	}
	global $wp_filesystem;
	if ( empty( $wp_filesystem ) ) {
		WP_Filesystem();
	}
	$htaccess = trailingslashit( $dir ) . '.htaccess';
	if ( ! file_exists( $htaccess ) && $wp_filesystem ) {
		$wp_filesystem->put_contents( $htaccess, "<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nOrder Deny,Allow\nDeny from all\n</IfModule>\n", FS_CHMOD_FILE );
	}
	$webconfig = trailingslashit( $dir ) . 'web.config';
	if ( ! file_exists( $webconfig ) && $wp_filesystem ) {
		$wp_filesystem->put_contents( $webconfig, "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<configuration><system.webServer><authorization><deny users=\"*\" /></authorization></system.webServer></configuration>\n", FS_CHMOD_FILE );
	}
	// Also drop an index.php to stop directory listing on servers that ignore .htaccess.
	$index = trailingslashit( $dir ) . 'index.php';
	if ( ! file_exists( $index ) && $wp_filesystem ) {
		$wp_filesystem->put_contents( $index, "<?php // Silence is golden.\n", FS_CHMOD_FILE );
	}
	return $dir;
}

function locarc_load_fpdf() {
	if ( class_exists( 'FPDF' ) && function_exists( 'locarc_render_contract_fpdf' ) ) {
		return true;
	}

	$library  = trailingslashit( LOCARC_PLUGIN_DIR ) . 'lib/fpdf/fpdf.php';
	$renderer = trailingslashit( LOCARC_PLUGIN_DIR ) . 'includes/fpdf-contract.php';
	if ( ! file_exists( $renderer ) || ( ! class_exists( 'FPDF' ) && ! file_exists( $library ) ) ) {
		return new WP_Error( 'fpdf_missing', 'La bibliothèque FPDF intégrée est introuvable.' );
	}

	// Another plugin may already provide the global FPDF class. Requiring our
	// bundled copy in that situation would cause a fatal class redeclaration.
	if ( ! class_exists( 'FPDF' ) ) {
		require_once $library;
	}
	if ( ! function_exists( 'locarc_render_contract_fpdf' ) ) {
		require_once $renderer;
	}
	return class_exists( 'FPDF' ) && function_exists( 'locarc_render_contract_fpdf' )
		? true
		: new WP_Error( 'fpdf_invalid', 'Impossible de charger le moteur PDF FPDF intégré.' );
}

function locarc_month_year_label( $date ) {
	if ( empty( $date ) ) {
		return '';
	}
	$ts = strtotime( $date );
	if ( ! $ts ) {
		return '';
	}
	return wp_date( 'F Y', $ts );
}

function locarc_compute_payment_schedule( $start_date, $payment_method = '' ) {
	if ( empty( $start_date ) ) {
		return array();
	}
	$method = strtolower( trim( (string) $payment_method ) );
	if ( 'autre' === $method ) {
		return array();
	}

	$base = strtotime( $start_date );
	if ( ! $base ) {
		return array();
	}

	$dates = array();
	for ( $i = 0; $i < 4; $i++ ) {
		$dates[] = wp_date( 'Y-m-d', strtotime( '+' . ( $i * 3 ) . ' months', $base ) );
	}
	return $dates;
}

function locarc_generate_contract_filename( $member, $start_date ) {
	$last  = sanitize_title( $member['last_name'] ?? '' );
	$first = sanitize_title( $member['first_name'] ?? '' );
	$last  = '' !== $last ? ucwords( str_replace( '-', ' ', $last ) ) : 'SansNom';
	$first = '' !== $first ? ucwords( str_replace( '-', ' ', $first ) ) : 'SansPrenom';
	$ts    = $start_date ? strtotime( $start_date ) : false;
	$month = $ts ? wp_date( 'm', $ts ) : wp_date( 'm' );
	$year  = $ts ? wp_date( 'Y', $ts ) : wp_date( 'Y' );
	return sanitize_file_name( sprintf( 'Contrat-Facture %s %s %s %s.pdf', $last, $first, $month, $year ) );
}

function locarc_assign_invoice_number( $contract ) {
	global $wpdb;
	$t = locarc_tables();
	if ( ! empty( $contract['invoice_number'] ) ) {
		return $contract;
	}

	$issued_at = current_time( 'mysql' );
	$prefix    = 'FAC-' . wp_date( 'Y' ) . '-';
	for ( $attempt = 0; $attempt < 5; $attempt++ ) {
		$existing_numbers = $wpdb->get_col(
			$wpdb->prepare(
				'SELECT invoice_number FROM %i WHERE invoice_number LIKE %s',
				$t['contracts'],
				$wpdb->esc_like( $prefix ) . '%'
			)
		);
		$sequence         = 1;
		foreach ( (array) $existing_numbers as $existing_number ) {
			$sequence = max( $sequence, intval( substr( (string) $existing_number, strlen( $prefix ) ) ) + 1 );
		}
		$number  = $prefix . str_pad( (string) $sequence, 4, '0', STR_PAD_LEFT );
		$updated = $wpdb->query(
			$wpdb->prepare(
				'UPDATE %i SET invoice_number=%s, invoice_issued_at=%s WHERE id=%d AND invoice_number IS NULL',
				$t['contracts'],
				$number,
				$issued_at,
				intval( $contract['id'] )
			)
		);
		if ( false !== $updated ) {
			$stored = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id=%d', $t['contracts'], intval( $contract['id'] ) ), ARRAY_A );
			if ( $stored && ! empty( $stored['invoice_number'] ) ) {
				return $stored;
			}
		}
	}

	return new WP_Error( 'invoice_number_failed', 'Impossible d\'attribuer un numéro de facture unique.' );
}

function locarc_generate_contract_pdf( $contract_id ) {
	global $wpdb;
	$t = locarc_tables();
	$c = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id=%d', $t['contracts'], $contract_id ), ARRAY_A );
	if ( ! $c ) {
		return new WP_Error( 'not_found', 'Contrat introuvable' );
	}
	$c = locarc_assign_invoice_number( $c );
	if ( is_wp_error( $c ) ) {
		return $c;
	}

	$m = locarc_pdf_get_member( $c['licence'] );

	$amount = ( 'personnalise' === $c['contract_type'] ) ? (float) ( $c['custom_price'] ?? 0 ) : locarc_contract_price_eur( $c['contract_type'] );

	$format_date = function ( $date ) {
		if ( empty( $date ) ) {
			return '';
		}
		$ts = strtotime( $date );
		return $ts ? wp_date( 'd/m/Y', $ts ) : $date;
	};

	$payment_method_raw    = strtolower( trim( (string) ( $c['payment_method'] ?? '' ) ) );
	$payment_method_labels = array(
		''               => '',
		'cheque'         => 'Chèque',
		'carte_bancaire' => 'Carte bancaire',
		'helloasso'      => 'HelloAsso',
		'especes'        => 'Espèces',
	);
	$payment_method_label  = $payment_method_labels[ $payment_method_raw ] ?? ucfirst( str_replace( '_', ' ', $payment_method_raw ) );

	$stored_due_dates   = array_values(
		array_filter(
			array(
				$c['payment_due_1'] ?? '',
				$c['payment_due_2'] ?? '',
				$c['payment_due_3'] ?? '',
				$c['payment_due_4'] ?? '',
			)
		)
	);
	$computed_due_dates = ( 'cheque' === $payment_method_raw ) ? locarc_compute_payment_schedule( $c['start_date'], $payment_method_raw ) : array();
	$final_due_dates    = ( 'cheque' === $payment_method_raw ) ? ( ! empty( $computed_due_dates ) ? $computed_due_dates : $stored_due_dates ) : array();
	$encaissements      = array();
	foreach ( $final_due_dates as $due ) {
		$label = locarc_month_year_label( $due );
		if ( '' !== $label ) {
			$encaissements[] = $label;
		}
	}

	$caution_amount = $c['caution_amount'];
	if ( null === $caution_amount || '' === $caution_amount || (float) $caution_amount <= 0 ) {
		$caution_amount = 400;
	}

	$responsable = (string) get_option( 'locarc_responsable_materiel', '' );
	$club_mail   = (string) get_option( 'locarc_club_email', '' );
	$end_label   = $format_date( $c['end_date'] );

	$data = array(
		'contract_number'      => $c['contract_number'],
		'invoice_number'       => $c['invoice_number'],
		'date_signature'       => $format_date( $c['invoice_issued_at'] ),
		'type_contrat'         => locarc_contract_type_label( $c['contract_type'] ),
		'date_debut'           => $format_date( $c['start_date'] ),
		'date_fin'             => $end_label,
		'date_retour_visible'  => $end_label,
		'cout_total'           => $amount,
		'nom'                  => $m['last_name'] ?? '',
		'prenom'               => $m['first_name'] ?? '',
		'licence'              => $c['licence'],
		'adresse'              => $m['address1'] ?? '',
		'cp'                   => $m['postal_code'] ?? '',
		'ville'                => $m['city'] ?? '',
		'email'                => $m['email'] ?? '',
		'tel'                  => $m['phone'] ?? '',
		'materiel'             => ( function () use ( $c ) {
			$materiel = array();
			if ( ! empty( $c['handle_identifier'] ) ) {
				$materiel[] = array(
					'label'       => 'Poignée',
					'identifiant' => $c['handle_identifier'],
					'marque'      => $c['handle_brand'] ?? '',
					'modele'      => $c['handle_model'] ?? '',
					'taille'      => ( $c['handle_size'] ? ( $c['handle_size'] . '"' ) : '' ),
					'lateralite'  => $c['handle_handedness'] ?? '',
					'puissance'   => '',
				);
			}
			if ( ! empty( $c['branches_identifier'] ) ) {
				$materiel[] = array(
					'label'       => 'Branches',
					'identifiant' => $c['branches_identifier'],
					'marque'      => $c['branches_brand'] ?? '',
					'modele'      => $c['branches_model'] ?? '',
					'taille'      => ( $c['branches_size'] ? ( $c['branches_size'] . '"' ) : '' ),
					'lateralite'  => '',
					'puissance'   => ( $c['branches_power'] ? ( $c['branches_power'] . '#' ) : '' ),
				);
			}
			if ( ! empty( $c['sight_identifier'] ) ) {
				$materiel[] = array(
					'label'       => 'Viseur',
					'identifiant' => $c['sight_identifier'],
					'marque'      => $c['sight_brand'] ?? '',
					'modele'      => $c['sight_model'] ?? '',
					'taille'      => '',
					'lateralite'  => $c['sight_handedness'] ?? '',
					'puissance'   => '',
				);
			}
			if ( ! empty( $c['stabilization_identifier'] ) ) {
				$materiel[] = array(
					'label'       => 'Stabilisation',
					'identifiant' => $c['stabilization_identifier'],
					'marque'      => $c['stabilization_brand'] ?? '',
					'modele'      => $c['stabilization_model'] ?? '',
					'taille'      => '',
					'lateralite'  => '',
					'puissance'   => '',
				);
			}
			if ( ! empty( $c['init_bow_identifier'] ) ) {
				$materiel[] = array(
					'label'       => "Arc d'Init.",
					'identifiant' => $c['init_bow_identifier'],
					'marque'      => $c['init_bow_brand'] ?? '',
					'modele'      => $c['init_bow_model'] ?? '',
					'taille'      => ( $c['init_bow_size'] ? ( $c['init_bow_size'] . '"' ) : '' ),
					'lateralite'  => $c['init_bow_handedness'] ?? '',
					'puissance'   => ( $c['init_bow_power'] ? ( $c['init_bow_power'] . '#' ) : '' ),
				);
			}
			if ( empty( $materiel ) ) {
				$materiel[] = array(
					'label'       => '—',
					'identifiant' => '—',
					'marque'      => '',
					'modele'      => '',
					'taille'      => '',
					'lateralite'  => '',
					'puissance'   => '',
				);
			}
			return $materiel;
		} )(),
		'payment_method'       => $payment_method_label,
		'caution_amount'       => $caution_amount,
		'payment_due_dates'    => $encaissements,
		'club_mail'            => $club_mail,
		'responsable_materiel' => $responsable,
		'club_header'          => (string) get_option( 'locarc_club_header_text', locarc_default_club_header() ),
		'club_siret'           => (string) get_option( 'locarc_club_siret', '41982058400011' ),
		'vat_mention'          => (string) get_option( 'locarc_club_vat_mention', 'Association non assujettie à la TVA' ),
	);

	$loaded = locarc_load_fpdf();
	if ( is_wp_error( $loaded ) ) {
		return $loaded;
	}

	$dir      = locarc_upload_contracts_dir();
	$filename = locarc_generate_contract_filename( $m ? $m : array(), $c['start_date'] ?? '' );
	$path     = trailingslashit( $dir ) . $filename;
	$output   = locarc_render_contract_fpdf( $data );

	// Write via WP Filesystem API for hosting-level compatibility.
	if ( ! function_exists( 'WP_Filesystem' ) ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
	}
	global $wp_filesystem;
	WP_Filesystem();
	if ( ! $wp_filesystem || ! $wp_filesystem->put_contents( $path, $output, FS_CHMOD_FILE ) ) {
		return new WP_Error( 'pdf_write_failed', 'Impossible d\'écrire le fichier PDF : ' . $path );
	}

	$upload = wp_upload_dir();
	$rel    = str_replace( trailingslashit( $upload['basedir'] ), '', $path );
	$wpdb->update(
		$t['contracts'],
		array(
			'pdf_path'   => $rel,
			'updated_at' => current_time( 'mysql' ),
		),
		array( 'id' => $contract_id ),
		array( '%s', '%s' ),
		array( '%d' )
	);
	$updated_contract = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id=%d', $t['contracts'], $contract_id ), ARRAY_A );
	if ( $updated_contract ) {
		locarc_log_insert( 'contract', 'generate_pdf', $contract_id, locarc_log_contract_label_from_row( $updated_contract ), 'PDF généré : ' . basename( $path ) );
	}

	return $path;
}

function locarc_pdf_get_member( $licence ) {
	global $wpdb;
	$t = locarc_tables();
	$m = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE licence=%s', $t['members'], $licence ), ARRAY_A );
	if ( $m ) {
		return $m;
	}
	$u = get_user_by( 'login', $licence );
	if ( ! $u ) {
		return null;
	}
	return array(
		'licence'     => $licence,
		'first_name'  => (string) get_user_meta( $u->ID, 'first_name', true ),
		'last_name'   => (string) get_user_meta( $u->ID, 'last_name', true ),
		'email'       => (string) ( $u->user_email ?? '' ),
		'phone'       => (string) get_user_meta( $u->ID, 'billing_phone', true ),
		'address1'    => (string) get_user_meta( $u->ID, 'billing_address_1', true ),
		'postal_code' => (string) get_user_meta( $u->ID, 'billing_postcode', true ),
		'city'        => (string) get_user_meta( $u->ID, 'billing_city', true ),
	);
}


function locarc_parse_email_list( $raw, $vars = array() ) {
	$raw = trim( (string) wp_unslash( $raw ) );
	if ( '' === $raw ) {
		return array();
	}
	if ( ! empty( $vars ) ) {
		$raw = strtr( $raw, $vars );
	}
	$parts = preg_split( '/[;,]+/', $raw );
	$clean = array();
	foreach ( (array) $parts as $email ) {
		$email = sanitize_email( trim( (string) $email ) );
		if ( '' !== $email ) {
			$clean[] = $email;
		}
	}
	return array_values( array_unique( $clean ) );
}

/**
 * Returns a signed, authenticated URL to download a contract PDF.
 * The file is never served directly from uploads/ (protected by .htaccess).
 * Admins get an admin-ajax URL; members get a front-end AJAX URL scoped to their licence.
 */
function locarc_get_contract_pdf_url( $contract_row ) {
	if ( empty( $contract_row['pdf_path'] ) ) {
		return null;
	}
	$contract_id = intval( $contract_row['id'] ?? 0 );
	if ( ! $contract_id ) {
		return null;
	}

	$nonce = wp_create_nonce( 'locarc_download_pdf_' . $contract_id );
	return add_query_arg(
		array(
			'action'      => 'locarc_download_pdf',
			'contract_id' => $contract_id,
			'nonce'       => $nonce,
		),
		admin_url( 'admin-ajax.php' )
	);
}

function locarc_contract_pdf_abs_path( $maybe_rel_path ) {
	if ( empty( $maybe_rel_path ) ) {
		return null;
	}
	$upload        = wp_upload_dir();
	$contracts_dir = realpath( locarc_upload_contracts_dir() );
	if ( ! $contracts_dir ) {
		return null;
	}

	$maybe_rel_path = wp_normalize_path( (string) $maybe_rel_path );
	if ( '/' === $maybe_rel_path[0] || preg_match( '#^[A-Za-z]:/#', $maybe_rel_path ) ) {
		return null;
	}
	if ( false !== strpos( $maybe_rel_path, '..' ) ) {
		return null;
	}

	$path = realpath( trailingslashit( $upload['basedir'] ) . ltrim( $maybe_rel_path, '/' ) );
	if ( ! $path ) {
		return null;
	}

	$path           = wp_normalize_path( $path );
	$contracts_dir  = wp_normalize_path( $contracts_dir );
	$allowed_prefix = trailingslashit( $contracts_dir );
	if ( $path !== $contracts_dir && strpos( $path, $allowed_prefix ) !== 0 ) {
		return null;
	}
	return $path;
}

function locarc_send_contract_email( $contract_id, $pdf_path ) {
	global $wpdb;
	$t = locarc_tables();
	$c = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id=%d', $t['contracts'], $contract_id ), ARRAY_A );
	if ( ! $c ) {
		return new WP_Error( 'not_found', 'Contrat introuvable' );
	}

	$m = locarc_pdf_get_member( $c['licence'] );
	if ( ! $m || empty( $m['email'] ) ) {
		return new WP_Error( 'no_email', 'Email adhérent manquant' );
	}

	$subject = get_option( 'locarc_email_subject', 'Votre contrat de location et facture' );
	$body    = get_option(
		'locarc_email_body',
		'Bonjour {{prenom}},

Veuillez trouver votre contrat de location et facture en pièce jointe.

Cordialement,
ACSIM'
	);

	$repl    = array(
		'{{prenom}}'          => $m['first_name'] ?? '',
		'{{nom}}'             => $m['last_name'] ?? '',
		'{{licence}}'         => $c['licence'],
		'{{date_fin}}'        => $c['end_date'],
		'{{contract_number}}' => $c['contract_number'],
		'{{email}}'           => $m['email'] ?? '',
		'{{club_email}}'      => (string) get_option( 'locarc_club_email', '' ),
	);
	$subject = str_replace( array_keys( $repl ), array_values( $repl ), wp_unslash( $subject ) );
	$body    = str_replace( array_keys( $repl ), array_values( $repl ), wp_unslash( $body ) );

	$to = locarc_parse_email_list( get_option( 'locarc_email_to', '{{email}}' ), $repl );
	if ( empty( $to ) && ! empty( $m['email'] ) ) {
		$to = array( $m['email'] );
	}
	if ( empty( $to ) ) {
		return new WP_Error( 'no_recipient', 'Aucun destinataire valide pour le contrat' );
	}

	$headers = array( 'Content-Type: text/html; charset=UTF-8' );

	$from = sanitize_email( wp_unslash( (string) get_option( 'locarc_email_from', get_option( 'admin_email' ) ) ) );
	if ( '' !== $from ) {
		$headers[] = 'From: ' . $from;
		$headers[] = 'Reply-To: ' . $from;
	}

	$cc = locarc_parse_email_list( get_option( 'locarc_email_cc', '' ), $repl );
	if ( ! empty( $cc ) ) {
		$headers[] = 'Cc: ' . implode( ', ', $cc );
	}

	$bcc = locarc_parse_email_list( get_option( 'locarc_email_bcc', '' ), $repl );
	if ( ! empty( $bcc ) ) {
		$headers[] = 'Bcc: ' . implode( ', ', $bcc );
	}

	$attachments = array();
	$pdf_path    = locarc_contract_pdf_abs_path( $pdf_path );
	if ( $pdf_path && file_exists( $pdf_path ) ) {
		$attachments[] = $pdf_path;
	}

	$ok = wp_mail( implode( ', ', $to ), $subject, nl2br( $body ), $headers, $attachments );
	return $ok ? true : new WP_Error( 'mail_failed', 'Echec envoi email' );
}

// ---------------------------------------------------------------------------
// Secure PDF download endpoint (replaces direct uploads URL access).
// Admins can download any contract. Logged-in members can only download their
// own active contract (matched by licence = user_login).
// ---------------------------------------------------------------------------
add_action( 'wp_ajax_locarc_download_pdf', 'locarc_ajax_download_pdf' );
add_action( 'wp_ajax_nopriv_locarc_download_pdf', 'locarc_ajax_download_pdf' );

function locarc_ajax_download_pdf() {
	$contract_id = intval( wp_unslash( $_GET['contract_id'] ?? 0 ) );
	if ( $contract_id <= 0 ) {
		wp_die( 'Paramètre manquant.', 400 );
	}

	// Verify nonce (works for both logged-in and nopriv because wp_create_nonce
	// uses the user ID; guests share user ID 0 but the nonce is still validated).
	if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['nonce'] ?? '' ) ), 'locarc_download_pdf_' . $contract_id ) ) {
		wp_die( 'Lien expiré ou invalide. Veuillez rafraîchir la page.', 403 );
	}

	global $wpdb;
	$t        = locarc_tables();
	$contract = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id=%d', $t['contracts'], $contract_id ), ARRAY_A );

	if ( ! $contract ) {
		wp_die( 'Contrat introuvable.', 404 );
	}

	// Access control:
	// - WordPress admins (manage_options) → always allowed.
	// - Logged-in members → only their own active contract.
	// - Anyone else → forbidden.
	if ( ! current_user_can( 'manage_options' ) ) {
		if ( ! is_user_logged_in() ) {
			wp_die( 'Accès refusé. Veuillez vous connecter.', 403 );
		}
		$current_login = wp_get_current_user()->user_login;
		if ( $current_login !== $contract['licence'] || 'active' !== $contract['status'] ) {
			wp_die( 'Accès refusé.', 403 );
		}
	}

	$abs_path = locarc_contract_pdf_abs_path( $contract['pdf_path'] ?? '' );
	if ( ! $abs_path || ! file_exists( $abs_path ) ) {
		wp_die( 'Fichier PDF introuvable. Veuillez le régénérer depuis l\'interface admin.', 404 );
	}

	$filename = basename( $abs_path );
	header( 'Content-Type: application/pdf' );
	header( 'Content-Disposition: inline; filename="' . $filename . '"' );
	header( 'Content-Length: ' . filesize( $abs_path ) );
	header( 'Cache-Control: private, no-store' );
	header( 'X-Content-Type-Options: nosniff' );

	// Prevent output buffering from swallowing the binary stream.
	if ( ob_get_level() ) {
		ob_end_clean();
	}

	readfile( $abs_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile -- Streams a verified PDF download to the browser.
	exit;
}
