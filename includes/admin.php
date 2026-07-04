<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Custom plugin tables are accessed through $wpdb; table names come from locarc_tables().
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.PHP.DevelopmentFunctions.error_log_error_log

add_action( 'admin_menu', 'locarc_admin_menu' );
add_action( 'admin_enqueue_scripts', 'locarc_admin_assets' );
add_action( 'admin_bar_menu', 'locarc_admin_bar_shortcut', 90 );
add_filter( 'plugin_action_links_' . plugin_basename( LOCARC_PLUGIN_FILE ), 'locarc_plugin_action_links' );
add_filter( 'plugin_row_meta', 'locarc_plugin_row_meta', 10, 2 );

/**
 * Make the settings page easy to reach from the Plugins screen.
 */
function locarc_plugin_action_links( $links ) {
	$settings_url = admin_url( 'admin.php?page=locarc&tab=settings' );
	array_unshift(
		$links,
		'<a href="' . esc_url( $settings_url ) . '">' . esc_html__( 'Settings', 'gestion-location-darc' ) . '</a>'
	);
	return $links;
}

/**
 * Explain the standard WordPress deletion behavior without intercepting it.
 */
function locarc_plugin_row_meta( $links, $plugin_file ) {
	if ( $plugin_file !== plugin_basename( LOCARC_PLUGIN_FILE ) ) {
		return $links;
	}

	$links[] = '<strong>'
		. esc_html__( 'Deletion permanently removes the plugin data. Deactivation keeps it.', 'gestion-location-darc' )
		. '</strong>';
	return $links;
}

/**
 * Add a shortcut in the front-end admin bar ("Tableau de bord" dropdown).
 */
function locarc_admin_bar_shortcut( $wp_admin_bar ) {
	if ( ! is_admin_bar_showing() ) {
		return;
	}
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$url = admin_url( 'admin.php?page=locarc' );

	// On the front-end, the site name node is the main dropdown (with "Tableau de bord").
	// We attach our shortcut there to match the user's expectation.
	// In wp-admin, we also try to attach under the same node when available.
	$parent = $wp_admin_bar->get_node( 'site-name' ) ? 'site-name' : ( $wp_admin_bar->get_node( 'dashboard' ) ? 'dashboard' : false );
	if ( ! $parent ) {
		return;
	}

	$wp_admin_bar->add_node(
		array(
			'id'     => 'locarc-shortcut',
			'parent' => $parent,
			'title'  => "Location d'Arc",
			'href'   => $url,
			'meta'   => array(
				'class' => 'locarc-adminbar-shortcut',
			),
		)
	);
}

function locarc_admin_menu() {
	add_menu_page(
		"Location d'Arc",
		"Location d'Arc",
		'manage_options',
		'locarc',
		'locarc_render_admin',
		'dashicons-archive',
		56
	);
}

function locarc_admin_assets( $hook ) {
	if ( strpos( $hook, 'toplevel_page_locarc' ) === false ) {
		return;
	}

	$css     = LOCARC_PLUGIN_DIR . 'assets/admin.css';
	$js      = LOCARC_PLUGIN_DIR . 'assets/admin.js';
	$ver_css = file_exists( $css ) ? filemtime( $css ) : LOCARC_VERSION;
	$ver_js  = file_exists( $js ) ? filemtime( $js ) : LOCARC_VERSION;

	wp_enqueue_style( 'locarc-admin', plugins_url( '../assets/admin.css', __FILE__ ), array(), $ver_css );
	wp_enqueue_script( 'locarc-admin', plugins_url( '../assets/admin.js', __FILE__ ), array( 'jquery' ), $ver_js, true );
	$contract_config = locarc_contract_types_active();
	$contract_prices = array();
	$contract_labels = array();
	foreach ( $contract_config as $type_key => $type_row ) {
		$contract_prices[ $type_key ] = floatval( $type_row['price'] ?? 0 );
		$contract_labels[ $type_key ] = (string) ( $type_row['label'] ?? $type_key );
	}
	$options_html = '';
	foreach ( $contract_config as $type_key => $type_row ) {
		$label         = (string) ( $type_row['label'] ?? $type_key );
		$price         = floatval( $type_row['price'] ?? 0 );
		$text          = ( $type_key === 'personnalise' ) ? $label : sprintf( '%s (%s€)', $label, number_format( $price, 0, ',', ' ' ) );
		$options_html .= '<option value="' . esc_attr( $type_key ) . '"__SELECTED__' . esc_attr( $type_key ) . '__>' . esc_html( $text ) . '</option>';
	}

	wp_localize_script(
		'locarc-admin',
		'LOCARC',
		array(
			'ajax_url'             => admin_url( 'admin-ajax.php' ),
			'nonce'                => wp_create_nonce( 'locarc_nonce' ),
			'contracts_url'        => admin_url( 'admin.php?page=locarc&tab=contracts' ),
			'contract_prices'      => $contract_prices,
			'contract_type_labels' => $contract_labels,
			'contract_types_html'  => $options_html,
		)
	);
}

function locarc_tabs() {
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only tab navigation, no state change.
	$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'contracts';

	$sights_enabled         = (bool) get_option( 'locarc_enable_sights', 0 );
	$stabilizations_enabled = (bool) get_option( 'locarc_enable_stabilizations', 0 );
	$init_bows_enabled      = (bool) get_option( 'locarc_enable_init_bows', 0 );
	if (
		current_user_can( 'manage_options' )
		&& isset( $_POST['locarc_save_modules'] )
		&& wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ?? '' ) ), 'locarc_save_modules' )
	) {
		$sights_enabled         = isset( $_POST['locarc_enable_sights'] );
		$stabilizations_enabled = isset( $_POST['locarc_enable_stabilizations'] );
		$init_bows_enabled      = isset( $_POST['locarc_enable_init_bows'] );
	}

	// Group 1 : gestion quotidienne (inventaires optionnels inclus si activés).
	$gestion = array(
		'contracts' => 'Contrats',
		'rented'    => 'Matériel loué',
		'branches'  => 'Branches',
		'handles'   => 'Poignées',
	);
	if ( $sights_enabled ) {
		$gestion['sights'] = 'Viseurs';
	}
	if ( $stabilizations_enabled ) {
		$gestion['stabilizations'] = 'Stabilisations';
	}
	if ( $init_bows_enabled ) {
		$gestion['init_bows'] = "Arcs d'Init.";
	}
	// Group 2 : administration / configuration.
	$admin_tabs = array(
		'imports'     => 'Imports',
		'licencies'   => 'Licenciés',
		'email'       => 'Email',
		'alerts'      => 'Alertes',
		'modules_cfg' => 'Modules',
		'settings'    => 'Réglages',
		'logs'        => 'Logs',
	);

	$in_gestion = isset( $gestion[ $tab ] );
	$in_admin   = ! $in_gestion;

	echo '<h1 class="locarc-page-title">Location d\'Arc</h1>';
	echo '<div class="locarc-tab-groups">';

	// Group pills.
	echo '<div class="locarc-tab-group-pills">';
	echo '<span class="locarc-tab-group-pill' . ( $in_gestion ? ' is-active' : '' ) . '" data-group="gestion">Gestion</span>';
	echo '<span class="locarc-tab-group-pill' . ( $in_admin ? ' is-active' : '' ) . '" data-group="admin">Administration</span>';
	echo '</div>';

	// Tabs row — Gestion.
	echo '<h2 class="nav-tab-wrapper locarc-tabs locarc-tabs--group-gestion' . ( $in_gestion ? '' : ' locarc-tabs--hidden' ) . '">';
	foreach ( $gestion as $k => $label ) {
		$class = ( $tab === $k ) ? 'nav-tab nav-tab-active' : 'nav-tab';
		echo '<a class="' . esc_attr( $class ) . '" href="' . esc_url( admin_url( 'admin.php?page=locarc&tab=' . $k ) ) . '">' . esc_html( $label ) . '</a>';
	}
	echo '</h2>';

	// Tabs row — Administration.
	echo '<h2 class="nav-tab-wrapper locarc-tabs locarc-tabs--group-admin' . ( $in_admin ? '' : ' locarc-tabs--hidden' ) . '">';
	foreach ( $admin_tabs as $k => $label ) {
		$class = ( $tab === $k ) ? 'nav-tab nav-tab-active' : 'nav-tab';
		echo '<a class="' . esc_attr( $class ) . '" href="' . esc_url( admin_url( 'admin.php?page=locarc&tab=' . $k ) ) . '">' . esc_html( $label ) . '</a>';
	}
	echo '</h2>';

	echo '</div>'; // .locarc-tab-groups
	return $tab;
}

function locarc_render_admin() {
	$tab = locarc_tabs();
	echo '<div class="wrap locarc-wrap">';
	if ( $tab === 'branches' ) {
		locarc_render_branches();
	} elseif ( $tab === 'handles' ) {
		locarc_render_handles();
	} elseif ( $tab === 'sights' ) {
		locarc_render_sights();
	} elseif ( $tab === 'stabilizations' ) {
		locarc_render_stabilizations();
	} elseif ( $tab === 'init_bows' ) {
		locarc_render_init_bows();
	} elseif ( $tab === 'rented' ) {
		locarc_render_rented();
	} elseif ( $tab === 'imports' ) {
		locarc_render_imports();
	} elseif ( $tab === 'licencies' ) {
		locarc_render_licencies();
	} elseif ( $tab === 'email' ) {
		locarc_render_email_settings();
	} elseif ( $tab === 'alerts' ) {
		locarc_render_alerts_settings();
	} elseif ( $tab === 'modules_cfg' ) {
		locarc_render_modules_cfg();
	} elseif ( $tab === 'settings' ) {
		locarc_render_settings();
	} elseif ( $tab === 'logs' ) {
		locarc_render_logs();
	} else {
		locarc_render_contracts();
	}
	echo '<p class="description" style="margin-top:24px;color:#adb5bd;font-size:11px;">'
		. 'Location d\'Arc v' . esc_html( LOCARC_VERSION ) . ' &mdash; Développé par <strong>Florian Bossard</strong> pour ACSIM'
		. '</p>';
	echo '</div>';
}


function locarc_render_settings() {
	$defaults = locarc_default_contract_types();
	$config   = locarc_contract_types_config();

	if ( isset( $_POST['locarc_save_contract_settings'] ) ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Forbidden', 'gestion-location-darc' ) );
		}
		check_admin_referer( 'locarc_save_contract_settings' );

        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- each value is sanitized individually below.
		$posted_labels = isset( $_POST['contract_labels'] ) && is_array( $_POST['contract_labels'] ) ? wp_unslash( $_POST['contract_labels'] ) : array();
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$posted_prices = isset( $_POST['contract_prices'] ) && is_array( $_POST['contract_prices'] ) ? wp_unslash( $_POST['contract_prices'] ) : array();
		$new_config    = array();
		foreach ( $defaults as $key => $def ) {
			$label = sanitize_text_field( $posted_labels[ $key ] ?? $def['label'] );
			if ( $label === '' ) {
				$label = $def['label'];
			}
			$raw_price = $posted_prices[ $key ] ?? $def['price'];
			$price     = max( 0, floatval( str_replace( ',', '.', (string) $raw_price ) ) );
			if ( $key === 'personnalise' ) {
				$price = 0;
			}
			$new_config[ $key ] = array(
				'label' => $label,
				'price' => $price,
			);
		}
		update_option( 'locarc_contract_types', $new_config );

		// Club identity fields.
		$responsable = sanitize_text_field( wp_unslash( $_POST['locarc_responsable_materiel'] ?? '' ) );
		update_option( 'locarc_responsable_materiel', $responsable );
		$club_email = sanitize_email( wp_unslash( $_POST['locarc_club_email'] ?? '' ) );
		update_option( 'locarc_club_email', $club_email );
		$club_siret = sanitize_text_field( wp_unslash( $_POST['locarc_club_siret'] ?? '' ) );
		$club_siret = preg_match( '/^\d{14}$/', $club_siret ) ? $club_siret : '';
		update_option( 'locarc_club_siret', $club_siret );
		$vat_mention = sanitize_text_field( wp_unslash( $_POST['locarc_club_vat_mention'] ?? '' ) );
		update_option( 'locarc_club_vat_mention', $vat_mention );

		// Club header text (multi-line, printed at top of PDFs).
		$club_header = sanitize_textarea_field( wp_unslash( $_POST['locarc_club_header_text'] ?? '' ) );
		if ( trim( $club_header ) === '' ) {
			$club_header = locarc_default_club_header();
		}
		update_option( 'locarc_club_header_text', $club_header );

		$config = locarc_contract_types_config();
		echo '<div class="notice notice-success is-dismissible"><p>✅ Réglages enregistrés.</p></div>';
	}

	$saved_responsable = (string) get_option( 'locarc_responsable_materiel', '' );
	$saved_club_email  = (string) get_option( 'locarc_club_email', '' );
	$saved_club_header = (string) get_option( 'locarc_club_header_text', locarc_default_club_header() );
	$saved_club_siret  = (string) get_option( 'locarc_club_siret', '41982058400011' );
	$saved_vat_mention = (string) get_option( 'locarc_club_vat_mention', 'Association non assujettie à la TVA' );

	echo '<div class="notice notice-warning"><p><strong>Suppression des donn&eacute;es :</strong> '
		. 'la d&eacute;sactivation conserve les contrats, les licenci&eacute;s, les inventaires et les r&eacute;glages. '
		. 'En revanche, la suppression d&eacute;finitive du plugin depuis la page Extensions efface toutes ces donn&eacute;es. '
		. 'Pensez &agrave; exporter les donn&eacute;es n&eacute;cessaires avant de cliquer sur Supprimer.</p></div>';

	echo '<h2>Informations du club</h2>';
	echo '<p>Ces informations apparaissent sur les contrats PDF générés.</p>';
	echo '<form method="post">';
	wp_nonce_field( 'locarc_save_contract_settings' );
	echo '<table class="form-table"><tbody>';

	echo '<tr><th scope="row"><label for="locarc-club-header">En-tête du PDF (association)</label></th><td>';
	echo '<textarea id="locarc-club-header" name="locarc_club_header_text" class="large-text" rows="4" style="font-family:monospace;">'
		. esc_textarea( $saved_club_header ) . '</textarea>';
	echo '<p class="description">Texte affiché en haut de chaque contrat PDF. '
		. 'La <strong>première ligne</strong> sera automatiquement mise en gras (nom de l\'association). '
		. 'Séparer chaque ligne par un retour à la ligne.</p></td></tr>';

	echo '<tr><th scope="row"><label for="locarc-responsable">Responsable matériel</label></th><td>';
	echo '<input id="locarc-responsable" type="text" class="regular-text" name="locarc_responsable_materiel" value="' . esc_attr( $saved_responsable ) . '" />';
	echo '<p class="description">Nom affiché comme signataire sur les contrats.</p></td></tr>';
	echo '<tr><th scope="row"><label for="locarc-club-email">Email du club (location)</label></th><td>';
	echo '<input id="locarc-club-email" type="email" class="regular-text" name="locarc_club_email" value="' . esc_attr( $saved_club_email ) . '" />';
	echo '<p class="description">Adresse affichée sur les contrats PDF.</p></td></tr>';
	echo '<tr><th scope="row"><label for="locarc-club-siret">SIRET</label></th><td>';
	echo '<input id="locarc-club-siret" type="text" class="regular-text" name="locarc_club_siret" value="' . esc_attr( $saved_club_siret ) . '" inputmode="numeric" pattern="[0-9]{14}" />';
	echo '<p class="description">Identifiant légal affiché sur les contrats-factures.</p></td></tr>';
	echo '<tr><th scope="row"><label for="locarc-club-vat">Mention TVA</label></th><td>';
	echo '<input id="locarc-club-vat" type="text" class="large-text" name="locarc_club_vat_mention" value="' . esc_attr( $saved_vat_mention ) . '" />';
	echo '<p class="description">Mention affichée sur les contrats-factures. Valeur par défaut : association non assujettie à la TVA.</p></td></tr>';
	echo '</tbody></table>';

	echo '<h2 style="margin-top:32px;">Réglage des contrats</h2>';
	echo '<p>Tu peux modifier ici les libellés des types de contrats et le prix appliqué par défaut. Le type « Personnalisé » garde toujours un montant saisi au cas par cas.</p>';
	echo '<table class="widefat striped" style="max-width:900px"><thead><tr><th>Type technique</th><th>Libellé affiché</th><th>Prix (€)</th></tr></thead><tbody>';
	foreach ( $defaults as $key => $def ) {
		$row      = $config[ $key ] ?? $def;
		$disabled = ( $key === 'personnalise' ) ? 'readonly' : '';
		$hint     = ( $key === 'personnalise' ) ? '<div class="description">Le montant est saisi dans chaque contrat.</div>' : '';
		echo '<tr>';
		echo '<td><code>' . esc_html( $key ) . '</code></td>';
		echo '<td><input type="text" class="regular-text" name="contract_labels[' . esc_attr( $key ) . ']" value="' . esc_attr( $row['label'] ) . '" /></td>';
		echo '<td><input type="number" step="0.01" min="0" name="contract_prices[' . esc_attr( $key ) . ']" value="' . esc_attr( $row['price'] ) . '" ' . esc_attr( $disabled ) . ' />' . wp_kses_post( $hint ) . '</td>';
		echo '</tr>';
	}
	echo '</tbody></table>';

	submit_button( 'Enregistrer', 'primary', 'locarc_save_contract_settings' );
	echo '</form>';

	echo '<h2 style="margin-top:40px;">Shortcodes disponibles</h2>';
	echo '<p>Copiez-collez ces shortcodes dans n\'importe quelle page ou article WordPress.</p>';
	echo '<table class="widefat striped" style="max-width:900px"><thead><tr><th>Shortcode</th><th>Description</th></tr></thead><tbody>';
	$shortcodes = array(
		'[locarc_dashboard]'            => 'Tableau de bord complet de gestion des locations (réservé aux responsables matériel).',
		'[locarc_poignees_disponibles]' => 'Liste publique des poignées disponibles à la location.',
		'[locarc_branches_disponibles]' => 'Liste publique des branches disponibles à la location.',
		'[locarc_mon_materiel]'         => 'Affiche le matériel actuellement loué par le licencié connecté.',
	);
	foreach ( $shortcodes as $code => $desc ) {
		echo '<tr>';
		echo '<td><code style="user-select:all;">' . esc_html( $code ) . '</code></td>';
		echo '<td>' . esc_html( $desc ) . '</td>';
		echo '</tr>';
	}
	echo '</tbody></table>';
}


function locarc_render_modules_cfg() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Forbidden', 'gestion-location-darc' ) );
	}

	if ( isset( $_POST['locarc_save_modules'] ) ) {
		check_admin_referer( 'locarc_save_modules' );
		update_option( 'locarc_enable_sights', isset( $_POST['locarc_enable_sights'] ) ? 1 : 0 );
		update_option( 'locarc_sight_required', isset( $_POST['locarc_sight_required'] ) ? 1 : 0 );
		update_option( 'locarc_enable_stabilizations', isset( $_POST['locarc_enable_stabilizations'] ) ? 1 : 0 );
		update_option( 'locarc_stabilization_required', isset( $_POST['locarc_stabilization_required'] ) ? 1 : 0 );
		update_option( 'locarc_enable_init_bows', isset( $_POST['locarc_enable_init_bows'] ) ? 1 : 0 );
		echo '<div class="notice notice-success is-dismissible"><p>✅ Modules enregistrés.</p></div>';
	}

	$en_sights          = get_option( 'locarc_enable_sights', 0 );
	$req_sights         = get_option( 'locarc_sight_required', 0 );
	$en_stabilizations  = get_option( 'locarc_enable_stabilizations', 0 );
	$req_stabilizations = get_option( 'locarc_stabilization_required', 0 );
	$en_init_bows       = get_option( 'locarc_enable_init_bows', 0 );

	echo '<h2>Modules optionnels</h2>';
	echo '<p>Activez les inventaires supplémentaires. Les onglets correspondants apparaîtront dans le groupe <strong>Modules</strong> de la navigation.</p>';
	echo '<form method="post">';
	wp_nonce_field( 'locarc_save_modules' );
	echo '<table class="form-table"><tbody>';
	echo '<tr><th scope="row">Viseurs</th><td>';
	echo '<label><input type="checkbox" name="locarc_enable_sights" value="1" ' . checked( 1, $en_sights, false ) . '> Activer l\'inventaire des viseurs</label><br>';
	echo '<label style="margin-top:6px;display:inline-block"><input type="checkbox" name="locarc_sight_required" value="1" ' . checked( 1, $req_sights, false ) . '> Viseur obligatoire sur les contrats</label>';
	echo '<p class="description">Ajoute un champ "Viseur" optionnel (ou obligatoire) sur les formulaires de contrat.</p></td></tr>';
	echo '<tr><th scope="row">Stabilisations</th><td>';
	echo '<label><input type="checkbox" name="locarc_enable_stabilizations" value="1" ' . checked( 1, $en_stabilizations, false ) . '> Activer l\'inventaire des stabilisations</label><br>';
	echo '<label style="margin-top:6px;display:inline-block"><input type="checkbox" name="locarc_stabilization_required" value="1" ' . checked( 1, $req_stabilizations, false ) . '> Stabilisation obligatoire sur les contrats</label>';
	echo '<p class="description">Ajoute un champ "Stabilisation" optionnel (ou obligatoire) sur les formulaires de contrat.</p></td></tr>';
	echo '<tr><th scope="row">Arcs d\'Initiation</th><td>';
	echo '<label><input type="checkbox" name="locarc_enable_init_bows" value="1" ' . checked( 1, $en_init_bows, false ) . '> Activer l\'inventaire des arcs d\'initiation</label>';
	echo '<p class="description">Active le type de contrat "Arc d\'Initiation" et l\'inventaire associé.</p></td></tr>';
	echo '</tbody></table>';
	submit_button( 'Enregistrer', 'primary', 'locarc_save_modules' );
	echo '</form>';
}

function locarc_render_logs() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	global $wpdb;
	$t = locarc_tables();

    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only log filters, no state change.
	$object_type = sanitize_key( wp_unslash( $_GET['log_object_type'] ?? '' ) );
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$action = sanitize_key( wp_unslash( $_GET['log_action'] ?? '' ) );
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$search = sanitize_text_field( wp_unslash( $_GET['log_s'] ?? '' ) );
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$date_from = sanitize_text_field( wp_unslash( $_GET['log_from'] ?? '' ) );
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$date_to = sanitize_text_field( wp_unslash( $_GET['log_to'] ?? '' ) );

	// All filter values are sanitized above. Static SQL with ( %s = '' OR cond ) skips each filter when empty.
	$like_search = $search !== '' ? '%' . $wpdb->esc_like( $search ) . '%' : '';
	$rows        = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT * FROM %i
             WHERE ( %s = '' OR object_type = %s )
               AND ( %s = '' OR action = %s )
               AND ( %s = '' OR ( object_label LIKE %s OR details LIKE %s OR user_label LIKE %s ) )
               AND ( %s = '' OR DATE(created_at) >= %s )
               AND ( %s = '' OR DATE(created_at) <= %s )
             ORDER BY created_at DESC, id DESC LIMIT 200",
			$t['logs'],
			$object_type,
			$object_type,
			$action,
			$action,
			$search,
			$like_search,
			$like_search,
			$like_search,
			$date_from,
			$date_from,
			$date_to,
			$date_to
		),
		ARRAY_A
	);
	echo '<div class="locarc-toolbar">';
	echo '<div class="locarc-toolbar-left"><span class="locarc-pill">' . intval( count( $rows ) ) . ' logs affichés</span></div>';
	echo '</div>';

	echo '<form method="get" class="locarc-filters" style="margin:12px 0 16px;padding:12px;border:1px solid #ddd;border-radius:12px;background:#fff;">';
	echo '<input type="hidden" name="page" value="locarc" />';
	echo '<input type="hidden" name="tab" value="logs" />';
	echo '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;align-items:end;">';
	echo '<p><label for="locarc-log-object"><strong>Objet</strong></label><br><select id="locarc-log-object" name="log_object_type" style="width:100%;">';
	echo '<option value="">Tous</option>';
	foreach ( array(
		'contract' => 'Contrat',
		'handle'   => 'Poignée',
		'branch'   => 'Branches',
	) as $k => $label ) {
		echo '<option value="' . esc_attr( $k ) . '"' . selected( $object_type, $k, false ) . '>' . esc_html( $label ) . '</option>';
	}
	echo '</select></p>';

	echo '<p><label for="locarc-log-action"><strong>Action</strong></label><br><select id="locarc-log-action" name="log_action" style="width:100%;">';
	echo '<option value="">Toutes</option>';
	foreach ( array( 'create', 'update', 'delete', 'archive', 'restore', 'renew', 'generate_pdf', 'send_email', 'toggle_paid', 'update_pricing' ) as $k ) {
		echo '<option value="' . esc_attr( $k ) . '"' . selected( $action, $k, false ) . '>' . esc_html( locarc_log_action_label( $k ) ) . '</option>';
	}
	echo '</select></p>';

	echo '<p><label for="locarc-log-search"><strong>Recherche</strong></label><br><input id="locarc-log-search" type="search" name="log_s" value="' . esc_attr( $search ) . '" style="width:100%;" placeholder="Contrat, identifiant, utilisateur..." /></p>';
	echo '<p><label for="locarc-log-from"><strong>Du</strong></label><br><input id="locarc-log-from" type="date" name="log_from" value="' . esc_attr( $date_from ) . '" style="width:100%;" /></p>';
	echo '<p><label for="locarc-log-to"><strong>Au</strong></label><br><input id="locarc-log-to" type="date" name="log_to" value="' . esc_attr( $date_to ) . '" style="width:100%;" /></p>';
	echo '<p><button class="button button-primary">Filtrer</button> <a class="button" href="' . esc_url( admin_url( 'admin.php?page=locarc&tab=logs' ) ) . '">Réinitialiser</a></p>';
	echo '</div></form>';

	echo '<table class="widefat striped"><thead><tr>';
	echo '<th style="width:160px">Date</th><th style="width:130px">Utilisateur</th><th style="width:110px">Objet</th><th style="width:180px">Référence</th><th style="width:150px">Action</th><th>Détail</th>';
	echo '</tr></thead><tbody>';

	if ( empty( $rows ) ) {
		echo '<tr><td colspan="6">Aucun log trouvé.</td></tr>';
	} else {
		foreach ( $rows as $row ) {
			echo '<tr>';
			echo '<td>' . esc_html( mysql2date( 'd/m/Y H:i', $row['created_at'] ) ) . '</td>';
			echo '<td>' . esc_html( $row['user_label'] ?: 'Système' ) . '</td>';
			echo '<td>' . esc_html( locarc_log_object_type_label( $row['object_type'] ) ) . '</td>';
			echo '<td>' . esc_html( $row['object_label'] ?: ( '#' . intval( $row['object_id'] ) ) ) . '</td>';
			echo '<td>' . esc_html( locarc_log_action_label( $row['action'] ) ) . '</td>';
			echo '<td style="white-space:pre-line;">' . esc_html( $row['details'] ) . '</td>';
			echo '</tr>';
		}
	}
	echo '</tbody></table>';
	echo '<p class="description" style="margin-top:10px;">Seuls les 200 logs les plus récents correspondant aux filtres sont affichés.</p>';
}

function locarc_render_alerts_settings() {
	echo '<h2>Alertes par email</h2>';

	if ( isset( $_POST['locarc_save_alerts'] ) ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Forbidden', 'gestion-location-darc' ) );
		}
		check_admin_referer( 'locarc_save_alerts' );

		$admin_to = sanitize_email( wp_unslash( $_POST['alerts_admin_to'] ?? '' ) );
		if ( $admin_to === '' ) {
			$admin_to = get_option( 'admin_email' );
		}

		update_option( 'locarc_alerts_admin_to', $admin_to );
		update_option( 'locarc_alerts_unpaid_enabled', isset( $_POST['alerts_unpaid_enabled'] ) ? '1' : '0' );
		update_option( 'locarc_alerts_admin_expiring_enabled', isset( $_POST['alerts_admin_expiring_enabled'] ) ? '1' : '0' );
		update_option( 'locarc_alerts_renter_expiring_enabled', isset( $_POST['alerts_renter_expiring_enabled'] ) ? '1' : '0' );
		update_option( 'locarc_use_wp_users_fallback', isset( $_POST['use_wp_users_fallback'] ) ? '1' : '0' );

		echo '<div class="notice notice-success is-dismissible"><p>✅ Alertes enregistrées.</p></div>';
	}

	$admin_to        = get_option( 'locarc_alerts_admin_to', get_option( 'admin_email' ) );
	$unpaid          = get_option( 'locarc_alerts_unpaid_enabled', '0' ) === '1';
	$admin_exp       = get_option( 'locarc_alerts_admin_expiring_enabled', '0' ) === '1';
	$renter_exp      = get_option( 'locarc_alerts_renter_expiring_enabled', '0' ) === '1';
	$use_wp_fallback = get_option( 'locarc_use_wp_users_fallback', '1' ) === '1';

	echo '<form method="post">';
	wp_nonce_field( 'locarc_save_alerts' );
	echo '<table class="form-table"><tbody>';
	echo '<tr><th scope="row"><label for="locarc-alerts-admin-to">Destinataire (club)</label></th><td>';
	echo '<input id="locarc-alerts-admin-to" type="email" class="regular-text" style="width:420px;max-width:100%" name="alerts_admin_to" value="' . esc_attr( $admin_to ) . '" />';
	echo '<p class="description">Adresse qui reçoit les récapitulatifs (ex : locationarc@arcclubissy.fr).</p>';
	echo '</td></tr>';

	echo '<tr><th scope="row">Source des adhérents</th><td>';
	echo '<label><input type="checkbox" name="use_wp_users_fallback" value="1" ' . checked( $use_wp_fallback, true, false ) . '> Autoriser le fallback sur les utilisateurs WordPress (recherche + noms) si la table licenciés n’est pas à jour</label>';
	echo '<p class="description">À désactiver si tu veux optimiser les performances sur un WordPress avec beaucoup d’utilisateurs/usermeta.</p>';
	echo '</td></tr>';

	echo '<tr><th scope="row">Rappels fin de semaine</th><td>';
	echo '<label><input type="checkbox" name="alerts_unpaid_enabled" ' . checked( $unpaid, true, false ) . ' /> Contrats non payés (avec la liste des personnes)</label><br>';
	echo '<label><input type="checkbox" name="alerts_admin_expiring_enabled" ' . checked( $admin_exp, true, false ) . ' /> Contrats qui se terminent dans 1 à 2 semaines</label>';
	echo '<p class="description">Envoi le vendredi (si WP-Cron s’exécute).</p>';
	echo '</td></tr>';

	echo '<tr><th scope="row">Rappel au loueur</th><td>';
	echo '<label><input type="checkbox" name="alerts_renter_expiring_enabled" ' . checked( $renter_exp, true, false ) . ' /> Envoyer un email au loueur quand son contrat se termine dans 7 jours</label>';
	echo '<p class="description">Le contenu de ce mail se règle dans l’onglet <strong>Email</strong> (section “Rappel fin de contrat”).</p>';
	echo '</td></tr>';

	echo '</tbody></table>';
	submit_button( 'Enregistrer', 'primary', 'locarc_save_alerts' );
	echo '</form>';
}

function locarc_member_display_name( $licence ) {
	global $wpdb;
	$t = locarc_tables();
	$m = $wpdb->get_row( $wpdb->prepare( 'SELECT first_name, last_name FROM %i WHERE licence=%s', $t['members'], $licence ), ARRAY_A );
	if ( $m && ( trim( ( $m['first_name'] ?? '' ) . ( $m['last_name'] ?? '' ) ) !== '' ) ) {
		return trim( ( $m['first_name'] ?? '' ) . ' ' . ( $m['last_name'] ?? '' ) );
	}
	$u = get_user_by( 'login', $licence );
	if ( $u ) {
		$fn   = trim( (string) get_user_meta( $u->ID, 'first_name', true ) );
		$ln   = trim( (string) get_user_meta( $u->ID, 'last_name', true ) );
		$name = trim( $fn . ' ' . $ln );
		if ( $name !== '' ) {
			return $name;
		}
		if ( ! empty( $u->display_name ) ) {
			return $u->display_name;
		}
	}
	return $licence;
}

/** Return [first_name, last_name] for a licence.
 *  Priority: locarc_members (import) -> WP usermeta (first_name/last_name) -> best-effort parse display_name.
 */

/**
 * Whether Location d'Arc is allowed to fall back to WordPress users for member data.
 * Default: enabled (1).
 */
function locarc_use_wp_users_fallback() {
	return get_option( 'locarc_use_wp_users_fallback', '1' ) === '1';
}

/**
 * Prime a per-request cache for member names to avoid N+1 queries.
 *
 * @param array $licences
 */
function locarc_prime_member_names( $licences ) {
	global $wpdb;
	$t = locarc_tables();
	if ( ! is_array( $licences ) || empty( $licences ) ) {
		return;
	}

	if ( ! isset( $GLOBALS['locarc_member_names_cache'] ) || ! is_array( $GLOBALS['locarc_member_names_cache'] ) ) {
		$GLOBALS['locarc_member_names_cache'] = array();
	}

	// Normalize + de-duplicate.
	$lics = array();
	foreach ( $licences as $lic ) {
		$lic = trim( (string) $lic );
		if ( $lic === '' ) {
			continue;
		}
		$lics[ $lic ] = true;
	}
	$lics = array_keys( $lics );
	if ( empty( $lics ) ) {
		return;
	}

	// Filter already cached
	$need = array();
	foreach ( $lics as $lic ) {
		if ( ! array_key_exists( $lic, $GLOBALS['locarc_member_names_cache'] ) ) {
			$need[] = $lic;
		}
	}
	if ( empty( $need ) ) {
		return;
	}

	// 1) locarc_members (preferred) — load full table and filter in PHP to avoid a
	// dynamic IN list whose placeholder count the static analyser cannot verify.
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$all_rows = $wpdb->get_results(
		$wpdb->prepare(
			'SELECT licence, first_name, last_name FROM %i',
			$t['members']
		),
		ARRAY_A
	);
	$need_set = array_flip( $need );
	$found    = array();
	foreach ( $all_rows as $r ) {
		$lic = (string) ( $r['licence'] ?? '' );
		if ( $lic === '' || ! isset( $need_set[ $lic ] ) ) {
			continue;
		}
		$fn = trim( (string) ( $r['first_name'] ?? '' ) );
		$ln = trim( (string) ( $r['last_name'] ?? '' ) );
		$GLOBALS['locarc_member_names_cache'][ $lic ] = array( $fn, $ln );
		$found[ $lic ]                                = true;
	}

	// Remaining licences not found in locarc_members
	$remaining = array();
	foreach ( $need as $lic ) {
		if ( ! isset( $found[ $lic ] ) ) {
			$remaining[] = $lic;
		}
	}

	if ( empty( $remaining ) ) {
		return;
	}
	if ( ! locarc_use_wp_users_fallback() ) {
		foreach ( $remaining as $lic ) {
			$GLOBALS['locarc_member_names_cache'][ $lic ] = array( '', '' );
		}
		return;
	}

	// 2) WordPress users fallback: user_login often equals licence.
	// Join against the contracts table to retrieve only users whose login appears as a
	// contract licence — eliminates the dynamic IN list and its unpredictable placeholder count.
	$wp_users      = $wpdb->users;
	$wp_meta       = $wpdb->usermeta;
	$remaining_set = array_flip( $remaining );

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$u_rows = $wpdb->get_results(
		$wpdb->prepare(
			'SELECT DISTINCT u.ID, u.user_login, u.display_name
             FROM %i u
             INNER JOIN %i c ON c.licence = u.user_login',
			$wp_users,
			$t['contracts']
		),
		ARRAY_A
	);

	$by_login = array();
	foreach ( $u_rows as $u ) {
		$login = (string) ( $u['user_login'] ?? '' );
		if ( $login === '' || ! isset( $remaining_set[ $login ] ) ) {
			continue;
		}
		$by_login[ $login ] = $u;
	}

	$meta_by_uid = array();
	if ( ! empty( $by_login ) ) {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$m_rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT um.user_id, um.meta_key, um.meta_value
                 FROM %i um
                 INNER JOIN %i u ON u.ID = um.user_id
                 INNER JOIN %i c ON c.licence = u.user_login
                 WHERE um.meta_key IN ('first_name','last_name')",
				$wp_meta,
				$wp_users,
				$t['contracts']
			),
			ARRAY_A
		);
		foreach ( $m_rows as $mr ) {
			$uid = intval( $mr['user_id'] ?? 0 );
			$k   = (string) ( $mr['meta_key'] ?? '' );
			$v   = trim( (string) ( $mr['meta_value'] ?? '' ) );
			if ( ! isset( $meta_by_uid[ $uid ] ) ) {
				$meta_by_uid[ $uid ] = array(
					'first_name' => '',
					'last_name'  => '',
				);
			}
			if ( $k === 'first_name' ) {
				$meta_by_uid[ $uid ]['first_name'] = $v;
			}
			if ( $k === 'last_name' ) {
				$meta_by_uid[ $uid ]['last_name'] = $v;
			}
		}
	}

	foreach ( $remaining as $lic ) {
		if ( isset( $by_login[ $lic ] ) ) {
			$u   = $by_login[ $lic ];
			$uid = intval( $u['ID'] ?? 0 );
			$fn  = trim( (string) ( $meta_by_uid[ $uid ]['first_name'] ?? '' ) );
			$ln  = trim( (string) ( $meta_by_uid[ $uid ]['last_name'] ?? '' ) );
			if ( $fn === '' && $ln === '' ) {
				$dn = trim( (string) ( $u['display_name'] ?? '' ) );
				if ( $dn !== '' ) {
					// Best-effort split
					$parts = preg_split( '/\s+/', $dn );
					if ( count( $parts ) >= 2 ) {
						$fn = $parts[0];
						$ln = implode( ' ', array_slice( $parts, 1 ) );
					} else {
						$ln = $dn;
					}
				}
			}
			$GLOBALS['locarc_member_names_cache'][ $lic ] = array( $fn, $ln );
		} else {
			$GLOBALS['locarc_member_names_cache'][ $lic ] = array( '', '' );
		}
	}
}

function locarc_member_names( $licence ) {
	$licence = trim( (string) $licence );
	if ( $licence === '' ) {
		return array( '', '' );
	}

	if ( ! isset( $GLOBALS['locarc_member_names_cache'] ) || ! is_array( $GLOBALS['locarc_member_names_cache'] ) ) {
		$GLOBALS['locarc_member_names_cache'] = array();
	}

	if ( array_key_exists( $licence, $GLOBALS['locarc_member_names_cache'] ) ) {
		return $GLOBALS['locarc_member_names_cache'][ $licence ];
	}

	// Prime just this one licence (also fills cache)
	locarc_prime_member_names( array( $licence ) );
	return $GLOBALS['locarc_member_names_cache'][ $licence ] ?? array( '', '' );
}

function locarc_sync_availability() {
	global $wpdb;
	$t = locarc_tables();

	// Keep manual states intact:
	// 1 = Oui, 0 = Non, 2 = FLAG, 3 = Obsolète, 4 = En Réparation, 5 = H-S
	// We only auto-toggle the contract-driven state between Oui (1) and Non (0).

	// 1) Free equipment that is currently "Non" (0) ONLY if it is NOT in an active contract.
	$wpdb->query(
		$wpdb->prepare(
			"UPDATE %i
         SET is_available=1
         WHERE is_available=0
           AND identifier NOT IN (
             SELECT DISTINCT branches_identifier
             FROM %i
             WHERE status='active' AND branches_identifier IS NOT NULL AND branches_identifier<>''
           )",
			$t['branches'],
			$t['contracts']
		)
	);
	$wpdb->query(
		$wpdb->prepare(
			"UPDATE %i
         SET is_available=1
         WHERE is_available=0
           AND identifier NOT IN (
             SELECT DISTINCT handle_identifier
             FROM %i
             WHERE status='active' AND handle_identifier IS NOT NULL AND handle_identifier<>''
           )",
			$t['handles'],
			$t['contracts']
		)
	);

	// 2) Mark equipment assigned in ACTIVE contracts as unavailable (0), using subqueries (no dynamic IN list).
	// Special manual states (FLAG=2, Obsolète=3, En Réparation=4, H-S=5) are preserved:
	// we only ever touch items currently at state 0 (Non) or 1 (Oui).
	$wpdb->query(
		$wpdb->prepare(
			"UPDATE %i SET is_available=0 WHERE is_available IN (0,1)
           AND identifier IN (SELECT DISTINCT branches_identifier FROM %i
             WHERE status='active' AND branches_identifier IS NOT NULL AND branches_identifier<>'')",
			$t['branches'],
			$t['contracts']
		)
	);

	$wpdb->query(
		$wpdb->prepare(
			"UPDATE %i SET is_available=0 WHERE is_available IN (0,1)
           AND identifier IN (SELECT DISTINCT handle_identifier FROM %i
             WHERE status='active' AND handle_identifier IS NOT NULL AND handle_identifier<>'')",
			$t['handles'],
			$t['contracts']
		)
	);

	if ( get_option( 'locarc_enable_sights', 0 ) ) {
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE %i SET is_available=1 WHERE is_available=0
               AND identifier NOT IN (SELECT DISTINCT sight_identifier FROM %i
                 WHERE status='active' AND sight_identifier IS NOT NULL AND sight_identifier<>'')",
				$t['sights'],
				$t['contracts']
			)
		);
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE %i SET is_available=0 WHERE is_available IN (0,1)
               AND identifier IN (SELECT DISTINCT sight_identifier FROM %i
                 WHERE status='active' AND sight_identifier IS NOT NULL AND sight_identifier<>'')",
				$t['sights'],
				$t['contracts']
			)
		);
	}

	if ( get_option( 'locarc_enable_stabilizations', 0 ) ) {
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE %i SET is_available=1 WHERE is_available=0
               AND identifier NOT IN (SELECT DISTINCT stabilization_identifier FROM %i
                 WHERE status='active' AND stabilization_identifier IS NOT NULL AND stabilization_identifier<>'')",
				$t['stabilizations'],
				$t['contracts']
			)
		);
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE %i SET is_available=0 WHERE is_available IN (0,1)
               AND identifier IN (SELECT DISTINCT stabilization_identifier FROM %i
                 WHERE status='active' AND stabilization_identifier IS NOT NULL AND stabilization_identifier<>'')",
				$t['stabilizations'],
				$t['contracts']
			)
		);
	}

	if ( get_option( 'locarc_enable_init_bows', 0 ) ) {
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE %i SET is_available=1 WHERE is_available=0
               AND identifier NOT IN (SELECT DISTINCT init_bow_identifier FROM %i
                 WHERE status='active' AND init_bow_identifier IS NOT NULL AND init_bow_identifier<>'')",
				$t['init_bows'],
				$t['contracts']
			)
		);
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE %i SET is_available=0 WHERE is_available IN (0,1)
               AND identifier IN (SELECT DISTINCT init_bow_identifier FROM %i
                 WHERE status='active' AND init_bow_identifier IS NOT NULL AND init_bow_identifier<>'')",
				$t['init_bows'],
				$t['contracts']
			)
		);
	}
}

function locarc_unassign_equipment_from_active_contracts( $kind, $identifier ) {
	global $wpdb;
	$t = locarc_tables();

	$identifier = trim( (string) $identifier );
	if ( $identifier === '' || ! in_array( $kind, array( 'branches', 'handles', 'sights', 'stabilizations', 'init_bows' ), true ) ) {
		return 0;
	}

	if ( $kind === 'branches' ) {
		$data  = array(
			'branches_identifier' => null,
			'branches_brand'      => null,
			'branches_model'      => null,
			'branches_size'       => null,
			'branches_power'      => null,
			'updated_at'          => current_time( 'mysql' ),
		);
		$where = array(
			'status'              => 'active',
			'branches_identifier' => $identifier,
		);
	} elseif ( $kind === 'handles' ) {
		$data  = array(
			'handle_identifier' => null,
			'handle_brand'      => null,
			'handle_model'      => null,
			'handle_size'       => null,
			'handle_handedness' => null,
			'updated_at'        => current_time( 'mysql' ),
		);
		$where = array(
			'status'            => 'active',
			'handle_identifier' => $identifier,
		);
	} elseif ( $kind === 'sights' ) {
		$data  = array(
			'sight_identifier' => null,
			'sight_brand'      => null,
			'sight_model'      => null,
			'sight_handedness' => null,
			'updated_at'       => current_time( 'mysql' ),
		);
		$where = array(
			'status'           => 'active',
			'sight_identifier' => $identifier,
		);
	} elseif ( $kind === 'stabilizations' ) {
		$data  = array(
			'stabilization_identifier' => null,
			'stabilization_brand'      => null,
			'stabilization_model'      => null,
			'updated_at'               => current_time( 'mysql' ),
		);
		$where = array(
			'status'                   => 'active',
			'stabilization_identifier' => $identifier,
		);
	} else {
		$data  = array(
			'init_bow_identifier' => null,
			'init_bow_brand'      => null,
			'init_bow_model'      => null,
			'init_bow_size'       => null,
			'init_bow_power'      => null,
			'init_bow_handedness' => null,
			'updated_at'          => current_time( 'mysql' ),
		);
		$where = array(
			'status'              => 'active',
			'init_bow_identifier' => $identifier,
		);
	}

	return (int) $wpdb->update( $t['contracts'], $data, $where );
}

/** Helpers **/
function locarc_is_equipment_assigned( $identifier, $kind, $exclude_contract_id = 0 ) {
	global $wpdb;
	$t       = locarc_tables();
	$col_map = array(
		'branches'       => 'branches_identifier',
		'handles'        => 'handle_identifier',
		'sights'         => 'sight_identifier',
		'stabilizations' => 'stabilization_identifier',
		'init_bows'      => 'init_bow_identifier',
	);
	if ( ! isset( $col_map[ $kind ] ) ) {
		return null;
	}
	$col = $col_map[ $kind ];
	if ( $exclude_contract_id ) {
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT id, licence FROM %i WHERE status='active' AND %i=%s AND id <> %d", $t['contracts'], $col, $identifier, $exclude_contract_id ), ARRAY_A );
	} else {
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT id, licence FROM %i WHERE status='active' AND %i=%s", $t['contracts'], $col, $identifier ), ARRAY_A );
	}
	if ( $row ) {
		$row['display_name'] = locarc_member_display_name( $row['licence'] );
	}
	return $row ?: null;
}

function locarc_render_branches() {
	global $wpdb;
	$t    = locarc_tables();
	$rows = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT b.*, c.licence AS renter_licence, m.first_name AS renter_first_name, m.last_name AS renter_last_name
             FROM %i b
             LEFT JOIN %i c ON c.status='active' AND c.branches_identifier = b.identifier
             LEFT JOIN %i m ON m.licence = c.licence
             ORDER BY b.is_available DESC, CAST(b.size AS UNSIGNED) ASC, CAST(b.power AS DECIMAL(5,2)) ASC, b.identifier ASC",
			$t['branches'],
			$t['contracts'],
			$t['members']
		),
		ARRAY_A
	);

	// Build Excel-like filter lists (unique values)
	$sizes  = array();
	$powers = array();
	$brands = array();
	$models = array();
	$years  = array();
	foreach ( $rows as $r ) {
		if ( $r['size'] !== '' && $r['size'] !== null ) {
			$sizes[ (string) $r['size'] ] = true;
		}
		if ( $r['power'] !== '' && $r['power'] !== null ) {
			$powers[ (string) $r['power'] ] = true;
		}
		if ( ! empty( $r['brand'] ) ) {
			$brands[ (string) $r['brand'] ] = true;
		}
		if ( ! empty( $r['model'] ) ) {
			$models[ (string) $r['model'] ] = true;
		}
		if ( ! empty( $r['purchase_year'] ) ) {
			$years[ (string) $r['purchase_year'] ] = true;
		}
	}
	$sizes = array_keys( $sizes );
	sort( $sizes, SORT_NUMERIC );
	$powers = array_keys( $powers );
	sort( $powers, SORT_NUMERIC );
	$brands = array_keys( $brands );
	natcasesort( $brands );
	$models = array_keys( $models );
	natcasesort( $models );
	$years = array_keys( $years );
	rsort( $years, SORT_NUMERIC );

	// Counters (initial values; updated live in JS on filter/sort)
	$count_total    = count( $rows );
	$count_dispo    = 0;
	$count_repair   = 0;
	$count_obsolete = 0;
	$total_invested = 0;
	foreach ( $rows as $r ) {
		$s = intval( $r['is_available'] ?? 0 );
		if ( $s === 1 ) {
			++$count_dispo;
		} elseif ( $s === 4 ) {
			++$count_repair;
		} elseif ( $s === 3 ) {
			++$count_obsolete;
		}
		$total_invested += floatval( $r['purchase_price'] ?? 0 );
	}

	echo '<div class="locarc-toolbar">'
		. '<div class="locarc-toolbar-left">'
		. '<button class="button button-primary" id="locarc-add-branch">Ajouter une paire de branches</button> '
		. '<span class="locarc-pill">' . intval( count( $rows ) ) . ' branches</span>'
		. '</div>'
		. '<div class="locarc-counters" data-table="locarc-branches-table">'
		. '<span class="locarc-pill">Total: <strong data-count="total">' . intval( $count_total ) . '</strong></span>'
		. '<span class="locarc-pill">Dispo: <strong data-count="dispo">' . intval( $count_dispo ) . '</strong></span>'
		. '<span class="locarc-pill">En réparation: <strong data-count="repair">' . intval( $count_repair ) . '</strong></span>'
		. '<span class="locarc-pill">Obsolètes: <strong data-count="obsolete">' . intval( $count_obsolete ) . '</strong></span>'
		. '<span class="locarc-pill locarc-pill--money">Investi&nbsp;: <strong>' . number_format( $total_invested, 0, ',', "\xc2\xa0" ) . '&nbsp;€</strong></span>'
		. '</div>'
		. '<details class="locarc-filters" open>'
		. '<summary>Filtres</summary>'
		. '<div class="locarc-toolbar-right">'
		. '<input type="search" class="locarc-filter-input" data-table="locarc-branches-table" placeholder="Filtrer (texte libre)" />'
		. '<select multiple class="locarc-filter-select" data-table="locarc-branches-table" data-col="6"><option value="">Dispo (toutes)</option><option value="Oui">Oui</option><option value="Non">Non</option><option value="FLAG">FLAG</option><option value="Obsolète">Obsolète</option><option value="En Réparation">En Réparation</option><option value="H-S">H-S</option></select>'
		. '<select multiple class="locarc-filter-select" data-table="locarc-branches-table" data-col="4"><option value="">Taille (toutes)</option>';
	foreach ( $sizes as $v ) {
		echo '<option value="' . esc_attr( $v ) . '">' . esc_html( $v ) . '</option>';
	}
	echo '</select>'
		. '<select multiple class="locarc-filter-select" data-table="locarc-branches-table" data-col="5"><option value="">Puissance (toutes)</option>';
	foreach ( $powers as $v ) {
		echo '<option value="' . esc_attr( $v ) . '">' . esc_html( $v ) . '</option>';
	}
	echo '</select>'
		. '<select multiple class="locarc-filter-select" data-table="locarc-branches-table" data-col="2"><option value="">Marque (toutes)</option>';
	foreach ( $brands as $v ) {
		echo '<option value="' . esc_attr( $v ) . '">' . esc_html( $v ) . '</option>';
	}
	echo '</select>'
		. '<select multiple class="locarc-filter-select" data-table="locarc-branches-table" data-col="3"><option value="">Modèle (tous)</option>';
	foreach ( $models as $v ) {
		echo '<option value="' . esc_attr( $v ) . '">' . esc_html( $v ) . '</option>';
	}
	echo '</select>'
		. '<select multiple class="locarc-filter-select" data-table="locarc-branches-table" data-col="9"><option value="">Année (toutes)</option>';
	foreach ( $years as $v ) {
		echo '<option value="' . esc_attr( $v ) . '">' . esc_html( $v ) . '</option>';
	}
	echo '</select>'
		. '</div>'
		. '</details>'
		. '</div>';

	echo '<table id="locarc-branches-table" class="widefat striped locarc-table locarc-sortable"><thead><tr>
        <th data-sort="num">#</th><th data-sort="text">Identifiant</th><th data-sort="text">Marque</th><th data-sort="text">Modèle</th><th data-sort="num">Taille</th><th data-sort="num">Puissance</th><th data-sort="text">Dispo</th><th data-sort="text">Commentaire</th><th data-sort="text">Loueur</th><th data-sort="num">Année</th><th data-sort="num">Prix</th><th data-sort="none">Actions</th>
    </tr></thead><tbody>';

	$i = 1;
	foreach ( $rows as $r ) {
		$renter_name = '';
		if ( ! empty( $r['renter_licence'] ) ) {
			$renter_name = trim( ( $r['renter_first_name'] ?? '' ) . ' ' . ( $r['renter_last_name'] ?? '' ) );
			if ( $renter_name === '' ) {
				$renter_name = locarc_member_display_name( $r['renter_licence'] );
			}
		}
		$row_class = ( (int) ( $r['is_available'] ?? 0 ) === 2 ) ? 'locarc-flag' : '';
		$disp      = ( ( (int) $r['is_available'] === 2 ) ? 'FLAG' : ( ( (int) $r['is_available'] === 3 ) ? 'Obsolète' : ( ( (int) $r['is_available'] === 4 ) ? 'En Réparation' : ( ( (int) $r['is_available'] === 5 ) ? 'H-S' : ( $r['is_available'] ? 'Oui' : 'Non' ) ) ) ) );
		echo '<tr class="' . esc_attr( $row_class ) . '" data-id="' . esc_attr( $r['id'] ) . '">
            <td>' . intval( $i++ ) . '</td>
            <td><code>' . esc_html( $r['identifier'] ) . '</code></td>
            <td>' . esc_html( $r['brand'] ) . '</td>
            <td>' . esc_html( $r['model'] ) . '</td>
            <td>' . esc_html( $r['size'] ) . '</td>
            <td>' . esc_html( $r['power'] ) . '</td>
            <td>' . esc_html( $disp ) . '</td>
            <td>' . esc_html( $r['comment'] ?? '' ) . '</td>
            <td>' . esc_html( ( $r['is_available'] ? '' : $renter_name ) ) . '</td>
            <td>' . esc_html( $r['purchase_year'] ) . '</td>
            <td>' . esc_html( $r['purchase_price'] ) . '</td>
            <td>
              <button class="button locarc-edit" data-kind="branches">Modifier</button>
              <button class="button button-link-delete locarc-delete" data-kind="branches">Supprimer</button>
            </td>
        </tr>';
	}
	echo '</tbody></table>';
	locarc_modal_markup();
}

function locarc_render_handles() {
	global $wpdb;
	$t    = locarc_tables();
	$rows = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT h.*, c.licence AS renter_licence, m.first_name AS renter_first_name, m.last_name AS renter_last_name
             FROM %i h
             LEFT JOIN %i c ON c.status='active' AND c.handle_identifier = h.identifier
             LEFT JOIN %i m ON m.licence = c.licence
             ORDER BY h.is_available DESC, CAST(h.size AS UNSIGNED) ASC, h.handedness ASC, h.identifier ASC",
			$t['handles'],
			$t['contracts'],
			$t['members']
		),
		ARRAY_A
	);

	// Build Excel-like filter lists (unique values)
	$sizes  = array();
	$brands = array();
	$models = array();
	$years  = array();
	foreach ( $rows as $r ) {
		if ( $r['size'] !== '' && $r['size'] !== null ) {
			$sizes[ (string) $r['size'] ] = true;
		}
		if ( ! empty( $r['brand'] ) ) {
			$brands[ (string) $r['brand'] ] = true;
		}
		if ( ! empty( $r['model'] ) ) {
			$models[ (string) $r['model'] ] = true;
		}
		if ( ! empty( $r['purchase_year'] ) ) {
			$years[ (string) $r['purchase_year'] ] = true;
		}
	}
	$sizes = array_keys( $sizes );
	sort( $sizes, SORT_NUMERIC );
	$brands = array_keys( $brands );
	natcasesort( $brands );
	$models = array_keys( $models );
	natcasesort( $models );
	$years = array_keys( $years );
	rsort( $years, SORT_NUMERIC );

	// Counters for handles.
	$count_total       = count( $rows );
	$count_dispo_left  = 0;
	$count_dispo_right = 0;
	$count_repair      = 0;
	$total_invested    = 0;
	foreach ( $rows as $r ) {
		$s = intval( $r['is_available'] ?? 0 );
		if ( $s === 4 ) {
			++$count_repair;
		}
		if ( $s === 1 ) {
			$h = trim( (string) ( $r['handedness'] ?? '' ) );
			if ( $h === 'Gauche' ) {
				++$count_dispo_left;
			} else {
				++$count_dispo_right;
			}
		}
		$total_invested += floatval( $r['purchase_price'] ?? 0 );
	}

	echo '<div class="locarc-toolbar">'
		. '<div class="locarc-toolbar-left">'
		. '<button class="button button-primary" id="locarc-add-handle">Ajouter une poignée</button> '
		. '<span class="locarc-pill">' . intval( count( $rows ) ) . ' poignées</span>'
		. '</div>'
		. '<div class="locarc-counters" data-table="locarc-handles-table">'
		. '<span class="locarc-pill">Total: <strong data-count="total">' . intval( $count_total ) . '</strong></span>'
		. '<span class="locarc-pill">Dispo gauchers: <strong data-count="left">' . intval( $count_dispo_left ) . '</strong></span>'
		. '<span class="locarc-pill">Dispo droitiers: <strong data-count="right">' . intval( $count_dispo_right ) . '</strong></span>'
		. '<span class="locarc-pill">En réparation: <strong data-count="repair">' . intval( $count_repair ) . '</strong></span>'
		. '<span class="locarc-pill locarc-pill--money">Investi&nbsp;: <strong>' . number_format( $total_invested, 0, ',', "\xc2\xa0" ) . '&nbsp;€</strong></span>'
		. '</div>'
		. '<details class="locarc-filters" open>'
		. '<summary>Filtres</summary>'
		. '<div class="locarc-toolbar-right">'
		. '<input type="search" class="locarc-filter-input" data-table="locarc-handles-table" placeholder="Filtrer (texte libre)" />'
		. '<select multiple class="locarc-filter-select" data-table="locarc-handles-table" data-col="7"><option value="">Dispo (toutes)</option><option value="Oui">Oui</option><option value="Non">Non</option><option value="FLAG">FLAG</option><option value="Obsolète">Obsolète</option><option value="En Réparation">En Réparation</option><option value="H-S">H-S</option></select>'
		. '<select multiple class="locarc-filter-select" data-table="locarc-handles-table" data-col="4"><option value="">Taille (toutes)</option>';
	foreach ( $sizes as $v ) {
		echo '<option value="' . esc_attr( $v ) . '">' . esc_html( $v ) . '</option>';
	}
	echo '</select>'
		. '<select multiple class="locarc-filter-select" data-table="locarc-handles-table" data-col="2"><option value="">Marque (toutes)</option>';
	foreach ( $brands as $v ) {
		echo '<option value="' . esc_attr( $v ) . '">' . esc_html( $v ) . '</option>';
	}
	echo '</select>'
		. '<select multiple class="locarc-filter-select" data-table="locarc-handles-table" data-col="3"><option value="">Modèle (tous)</option>';
	foreach ( $models as $v ) {
		echo '<option value="' . esc_attr( $v ) . '">' . esc_html( $v ) . '</option>';
	}
	echo '</select>'
		. '<select multiple class="locarc-filter-select" data-table="locarc-handles-table" data-col="10"><option value="">Année (toutes)</option>';
	foreach ( $years as $v ) {
		echo '<option value="' . esc_attr( $v ) . '">' . esc_html( $v ) . '</option>';
	}
	echo '</select>'
		. '</div>'
		. '</details>'
		. '</div>';

	echo '<table id="locarc-handles-table" class="widefat striped locarc-table locarc-sortable"><thead><tr>
        <th data-sort="num">#</th><th data-sort="text">Identifiant</th><th data-sort="text">Marque</th><th data-sort="text">Modèle</th><th data-sort="num">Taille</th><th data-sort="text">Latéralité</th><th data-sort="text">Couleur</th><th data-sort="text">Dispo</th><th data-sort="text">Commentaire</th><th data-sort="text">Loueur</th><th data-sort="num">Année</th><th data-sort="num">Prix</th><th data-sort="none">Actions</th>
    </tr></thead><tbody>';

	$i = 1;
	foreach ( $rows as $r ) {
		$renter_name = '';
		if ( ! empty( $r['renter_licence'] ) ) {
			$renter_name = trim( ( $r['renter_first_name'] ?? '' ) . ' ' . ( $r['renter_last_name'] ?? '' ) );
			if ( $renter_name === '' ) {
				$renter_name = locarc_member_display_name( $r['renter_licence'] );
			}
		}
		$row_class = ( (int) ( $r['is_available'] ?? 0 ) === 2 ) ? 'locarc-flag' : '';
		$disp      = ( ( (int) $r['is_available'] === 2 ) ? 'FLAG' : ( ( (int) $r['is_available'] === 3 ) ? 'Obsolète' : ( ( (int) $r['is_available'] === 4 ) ? 'En Réparation' : ( ( (int) $r['is_available'] === 5 ) ? 'H-S' : ( $r['is_available'] ? 'Oui' : 'Non' ) ) ) ) );
		echo '<tr class="' . esc_attr( $row_class ) . '" data-id="' . esc_attr( $r['id'] ) . '">
            <td>' . intval( $i++ ) . '</td>
            <td><code>' . esc_html( $r['identifier'] ) . '</code></td>
            <td>' . esc_html( $r['brand'] ) . '</td>
            <td>' . esc_html( $r['model'] ) . '</td>
            <td>' . esc_html( $r['size'] ) . '</td>
            <td>' . esc_html( $r['handedness'] ) . '</td>
            <td>' . esc_html( $r['color'] ) . '</td>
            <td>' . esc_html( $disp ) . '</td>
            <td>' . esc_html( $r['comment'] ?? '' ) . '</td>
            <td>' . esc_html( ( $r['is_available'] ? '' : $renter_name ) ) . '</td>
            <td>' . esc_html( $r['purchase_year'] ) . '</td>
            <td>' . esc_html( $r['purchase_price'] ) . '</td>
            <td>
              <button class="button locarc-edit" data-kind="handles">Modifier</button>
              <button class="button button-link-delete locarc-delete" data-kind="handles">Supprimer</button>
            </td>
        </tr>';
	}
	echo '</tbody></table>';
	locarc_modal_markup();
}

function locarc_render_sights() {
	global $wpdb;
	$t              = locarc_tables();
	$rows           = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT s.*, c.licence AS renter_licence, m.first_name AS renter_first_name, m.last_name AS renter_last_name
             FROM %i s
             LEFT JOIN %i c ON c.status='active' AND c.sight_identifier = s.identifier
             LEFT JOIN %i m ON m.licence = c.licence
             ORDER BY s.is_available DESC, s.identifier ASC",
			$t['sights'],
			$t['contracts'],
			$t['members']
		),
		ARRAY_A
	);
	$total_invested = 0;
	foreach ( $rows as $r ) {
		$total_invested += floatval( $r['purchase_price'] ?? 0 ); }
	echo '<div class="locarc-toolbar">'
		. '<button class="button button-primary" id="locarc-add-sight">Ajouter un viseur</button> '
		. '<span class="locarc-pill">' . intval( count( $rows ) ) . ' viseurs</span>'
		. '<span class="locarc-pill locarc-pill--money">Investi&nbsp;: <strong>' . number_format( $total_invested, 0, ',', "\xc2\xa0" ) . '&nbsp;€</strong></span>'
		. '</div>';
	echo '<table id="locarc-sights-table" class="widefat striped locarc-table locarc-sortable"><thead><tr>
        <th data-sort="num">#</th><th data-sort="text">Identifiant</th><th data-sort="text">Marque</th><th data-sort="text">Modèle</th><th data-sort="text">Latéralité</th><th data-sort="text">Dispo</th><th data-sort="text">Commentaire</th><th data-sort="text">Loueur</th><th data-sort="num">Année</th><th data-sort="num">Prix</th><th data-sort="none">Actions</th>
    </tr></thead><tbody>';
	$i = 1;
	foreach ( $rows as $r ) {
		$renter_name = '';
		if ( ! empty( $r['renter_licence'] ) ) {
			$renter_name = trim( ( $r['renter_first_name'] ?? '' ) . ' ' . ( $r['renter_last_name'] ?? '' ) );
			if ( $renter_name === '' ) {
				$renter_name = locarc_member_display_name( $r['renter_licence'] );
			}
		}
		$row_class = ( (int) ( $r['is_available'] ?? 0 ) === 2 ) ? 'locarc-flag' : '';
		$disp      = ( ( (int) $r['is_available'] === 2 ) ? 'FLAG' : ( ( (int) $r['is_available'] === 3 ) ? 'Obsolète' : ( ( (int) $r['is_available'] === 4 ) ? 'En Réparation' : ( ( (int) $r['is_available'] === 5 ) ? 'H-S' : ( $r['is_available'] ? 'Oui' : 'Non' ) ) ) ) );
		echo '<tr class="' . esc_attr( $row_class ) . '" data-id="' . esc_attr( $r['id'] ) . '">
            <td>' . intval( $i++ ) . '</td>
            <td><code>' . esc_html( $r['identifier'] ) . '</code></td>
            <td>' . esc_html( $r['brand'] ) . '</td>
            <td>' . esc_html( $r['model'] ) . '</td>
            <td>' . esc_html( $r['handedness'] ) . '</td>
            <td>' . esc_html( $disp ) . '</td>
            <td>' . esc_html( $r['comment'] ?? '' ) . '</td>
            <td>' . esc_html( $r['is_available'] ? '' : $renter_name ) . '</td>
            <td>' . esc_html( $r['purchase_year'] ) . '</td>
            <td>' . esc_html( $r['purchase_price'] ) . '</td>
            <td>
              <button class="button locarc-edit" data-kind="sights">Modifier</button>
              <button class="button button-link-delete locarc-delete" data-kind="sights">Supprimer</button>
            </td>
        </tr>';
	}
	echo '</tbody></table>';
	locarc_modal_markup();
}

function locarc_render_stabilizations() {
	global $wpdb;
	$t              = locarc_tables();
	$rows           = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT s.*, c.licence AS renter_licence, m.first_name AS renter_first_name, m.last_name AS renter_last_name
             FROM %i s
             LEFT JOIN %i c ON c.status='active' AND c.stabilization_identifier = s.identifier
             LEFT JOIN %i m ON m.licence = c.licence
             ORDER BY s.is_available DESC, s.identifier ASC",
			$t['stabilizations'],
			$t['contracts'],
			$t['members']
		),
		ARRAY_A
	);
	$total_invested = 0;
	foreach ( $rows as $r ) {
		$total_invested += floatval( $r['purchase_price'] ?? 0 ); }
	echo '<div class="locarc-toolbar">'
		. '<button class="button button-primary" id="locarc-add-stabilization">Ajouter une stabilisation</button> '
		. '<span class="locarc-pill">' . intval( count( $rows ) ) . ' stabilisations</span>'
		. '<span class="locarc-pill locarc-pill--money">Investi&nbsp;: <strong>' . number_format( $total_invested, 0, ',', "\xc2\xa0" ) . '&nbsp;€</strong></span>'
		. '</div>';
	echo '<table id="locarc-stabilizations-table" class="widefat striped locarc-table locarc-sortable"><thead><tr>
        <th data-sort="num">#</th><th data-sort="text">Identifiant</th><th data-sort="text">Marque</th><th data-sort="text">Mod&egrave;le</th><th data-sort="text">&Eacute;tat</th><th data-sort="text">Commentaire</th><th data-sort="text">Loueur</th><th data-sort="num">Ann&eacute;e</th><th data-sort="num">Prix</th><th data-sort="none">Actions</th>
    </tr></thead><tbody>';
	$i = 1;
	foreach ( $rows as $r ) {
		$renter_name = '';
		if ( ! empty( $r['renter_licence'] ) ) {
			$renter_name = trim( ( $r['renter_first_name'] ?? '' ) . ' ' . ( $r['renter_last_name'] ?? '' ) );
			if ( $renter_name === '' ) {
				$renter_name = locarc_member_display_name( $r['renter_licence'] );
			}
		}
		$row_class = ( (int) ( $r['is_available'] ?? 0 ) === 2 ) ? 'locarc-flag' : '';
		$disp      = ( ( (int) $r['is_available'] === 2 ) ? 'FLAG' : ( ( (int) $r['is_available'] === 3 ) ? 'ObsolÃ¨te' : ( ( (int) $r['is_available'] === 4 ) ? 'En RÃ©paration' : ( ( (int) $r['is_available'] === 5 ) ? 'H-S' : ( $r['is_available'] ? 'Oui' : 'Non' ) ) ) ) );
		echo '<tr class="' . esc_attr( $row_class ) . '" data-id="' . esc_attr( $r['id'] ) . '">
            <td>' . intval( $i++ ) . '</td>
            <td><code>' . esc_html( $r['identifier'] ) . '</code></td>
            <td>' . esc_html( $r['brand'] ) . '</td>
            <td>' . esc_html( $r['model'] ) . '</td>
            <td>' . esc_html( $disp ) . '</td>
            <td>' . esc_html( $r['comment'] ?? '' ) . '</td>
            <td>' . esc_html( $r['is_available'] ? '' : $renter_name ) . '</td>
            <td>' . esc_html( $r['purchase_year'] ) . '</td>
            <td>' . esc_html( $r['purchase_price'] ) . '</td>
            <td>
              <button class="button locarc-edit" data-kind="stabilizations">Modifier</button>
              <button class="button button-link-delete locarc-delete" data-kind="stabilizations">Supprimer</button>
            </td>
        </tr>';
	}
	echo '</tbody></table>';
	locarc_modal_markup();
}

function locarc_render_init_bows() {
	global $wpdb;
	$t              = locarc_tables();
	$rows           = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT ib.*, c.licence AS renter_licence, m.first_name AS renter_first_name, m.last_name AS renter_last_name
             FROM %i ib
             LEFT JOIN %i c ON c.status='active' AND c.init_bow_identifier = ib.identifier
             LEFT JOIN %i m ON m.licence = c.licence
             ORDER BY ib.is_available DESC, CAST(ib.size AS UNSIGNED) ASC, CAST(ib.power AS DECIMAL(5,2)) ASC, ib.identifier ASC",
			$t['init_bows'],
			$t['contracts'],
			$t['members']
		),
		ARRAY_A
	);
	$total_invested = 0;
	foreach ( $rows as $r ) {
		$total_invested += floatval( $r['purchase_price'] ?? 0 ); }
	echo '<div class="locarc-toolbar">'
		. '<button class="button button-primary" id="locarc-add-init_bow">Ajouter un arc d\'initiation</button> '
		. '<span class="locarc-pill">' . intval( count( $rows ) ) . " arcs d'initiation</span>"
		. '<span class="locarc-pill locarc-pill--money">Investi&nbsp;: <strong>' . number_format( $total_invested, 0, ',', "\xc2\xa0" ) . '&nbsp;€</strong></span>'
		. '</div>';
	echo '<table id="locarc-init_bows-table" class="widefat striped locarc-table locarc-sortable"><thead><tr>
        <th data-sort="num">#</th><th data-sort="text">Identifiant</th><th data-sort="text">Poign&eacute;e</th><th data-sort="text">Branches</th><th data-sort="num">Taille</th><th data-sort="num">Puissance</th><th data-sort="text">Lat&eacute;ralit&eacute;</th><th data-sort="text">Dispo</th><th data-sort="text">Commentaire</th><th data-sort="text">Loueur</th><th data-sort="num">Ann&eacute;e</th><th data-sort="num">Prix</th><th data-sort="none">Actions</th>
    </tr></thead><tbody>';
	$i = 1;
	foreach ( $rows as $r ) {
		$renter_name = '';
		if ( ! empty( $r['renter_licence'] ) ) {
			$renter_name = trim( ( $r['renter_first_name'] ?? '' ) . ' ' . ( $r['renter_last_name'] ?? '' ) );
			if ( $renter_name === '' ) {
				$renter_name = locarc_member_display_name( $r['renter_licence'] );
			}
		}
		$row_class = ( (int) ( $r['is_available'] ?? 0 ) === 2 ) ? 'locarc-flag' : '';
		$disp      = ( ( (int) $r['is_available'] === 2 ) ? 'FLAG' : ( ( (int) $r['is_available'] === 3 ) ? 'Obsolète' : ( ( (int) $r['is_available'] === 4 ) ? 'En Réparation' : ( ( (int) $r['is_available'] === 5 ) ? 'H-S' : ( $r['is_available'] ? 'Oui' : 'Non' ) ) ) ) );
		echo '<tr class="' . esc_attr( $row_class ) . '" data-id="' . esc_attr( $r['id'] ) . '">
            <td>' . intval( $i++ ) . '</td>
            <td><code>' . esc_html( $r['identifier'] ) . '</code></td>
            <td>' . esc_html( $r['brand'] ) . '</td>
            <td>' . esc_html( $r['model'] ) . '</td>
            <td>' . esc_html( $r['size'] ) . '</td>
            <td>' . esc_html( $r['power'] ) . '</td>
            <td>' . esc_html( $r['handedness'] ) . '</td>
            <td>' . esc_html( $disp ) . '</td>
            <td>' . esc_html( $r['comment'] ?? '' ) . '</td>
            <td>' . esc_html( $r['is_available'] ? '' : $renter_name ) . '</td>
            <td>' . esc_html( $r['purchase_year'] ) . '</td>
            <td>' . esc_html( $r['purchase_price'] ) . '</td>
            <td>
              <button class="button locarc-edit" data-kind="init_bows">Modifier</button>
              <button class="button button-link-delete locarc-delete" data-kind="init_bows">Supprimer</button>
            </td>
        </tr>';
	}
	echo '</tbody></table>';
	locarc_modal_markup();
}

function locarc_render_contracts() {
	global $wpdb;
	$t = locarc_tables();

	$show_archived = isset( $_GET['archived'] ) && sanitize_key( wp_unslash( $_GET['archived'] ) ) === '1'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only view toggle, no state change.
	$status        = $show_archived ? 'archived' : 'active';

	$rows = $wpdb->get_results(
		$wpdb->prepare(
			'SELECT c.*, m.first_name, m.last_name
         FROM %i c
         LEFT JOIN %i m ON m.licence = c.licence
         WHERE c.status=%s
         ORDER BY end_date ASC',
			$t['contracts'],
			$t['members'],
			$status
		),
		ARRAY_A
	);

	// Prime member names cache to avoid N+1 lookups when some rows have no joined member name.
	$missing_licences = array();
	foreach ( $rows as $r0 ) {
		if ( trim( (string) ( $r0['first_name'] ?? '' ) ) === '' && trim( (string) ( $r0['last_name'] ?? '' ) ) === '' ) {
			$lic0 = trim( (string) ( $r0['licence'] ?? '' ) );
			if ( $lic0 !== '' ) {
				$missing_licences[] = $lic0;
			}
		}
	}
	if ( ! empty( $missing_licences ) ) {
		locarc_prime_member_names( $missing_licences );
	}

	$active_count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM %i WHERE status='active'", $t['contracts'] ) );
	// Revenu potentiel = somme des contrats actifs, indépendamment du statut payé.
	// Personnalisé : utilise custom_price (0 si vide).
	$active_contracts = $wpdb->get_results( $wpdb->prepare( "SELECT contract_type, custom_price FROM %i WHERE status='active'", $t['contracts'] ), ARRAY_A );
	$potential        = 0;
	foreach ( $active_contracts as $contract_row ) {
		$ctype      = (string) ( $contract_row['contract_type'] ?? '' );
		$potential += ( $ctype === 'personnalise' )
			? floatval( $contract_row['custom_price'] ?? 0 )
			: floatval( locarc_contract_price_eur( $ctype ) );
	}

	// Counts by contract type for the current view (active vs archived)
	$type_counts = $wpdb->get_results( $wpdb->prepare( 'SELECT contract_type, COUNT(*) AS cnt FROM %i WHERE status=%s GROUP BY contract_type', $t['contracts'], $status ), ARRAY_A );
	$counts      = array();
	foreach ( $type_counts as $tc ) {
		$k = (string) ( $tc['contract_type'] ?? '' );
		if ( $k === '' ) {
			continue;
		}
		$counts[ $k ] = intval( $tc['cnt'] ?? 0 );
	}

	// ── Investissement total (somme purchase_price de tous les inventaires) ──
	$total_invested_all = 0;
	foreach ( array( 'branches', 'handles', 'sights', 'stabilizations', 'init_bows' ) as $_inv_key ) {
		$total_invested_all += floatval( $wpdb->get_var( $wpdb->prepare( 'SELECT COALESCE(SUM(purchase_price), 0) FROM %i', $t[ $_inv_key ] ) ) );
	}

	// ── KPI row: 2 large primary cards + invest card + type breakdown chips ──
	echo '<div class="locarc-kpi-row">';
	if ( ! $show_archived ) {
		echo '<div class="locarc-kpi locarc-kpi--primary">'
			. '<div class="locarc-kpi-label">Revenu potentiel</div>'
			. '<div class="locarc-kpi-value">' . number_format( $potential, 0, ',', "\xc2\xa0" ) . '&nbsp;€</div>'
			. '</div>';
	}
	echo '<div class="locarc-kpi locarc-kpi--primary">'
		. '<div class="locarc-kpi-label">Contrats ' . ( $show_archived ? 'archivés' : 'actifs' ) . '</div>'
		. '<div class="locarc-kpi-value">' . intval( count( $rows ) ) . '</div>'
		. '</div>';
	echo '<div class="locarc-kpi locarc-kpi--invest">'
		. '<div class="locarc-kpi-label">Investissement total</div>'
		. '<div class="locarc-kpi-value">' . number_format( $total_invested_all, 0, ',', "\xc2\xa0" ) . '&nbsp;€</div>'
		. '</div>';
	echo '<div class="locarc-kpi-breakdown">';
	$kpi_types = array( 'complet', 'arc_nu', 'branches', 'jeune', 'personnalise', 'pret' );
	if ( get_option( 'locarc_enable_init_bows', 0 ) ) {
		array_splice( $kpi_types, 2, 0, array( 'arc_initiation' ) );
	}
	foreach ( $kpi_types as $k ) {
		$label = locarc_contract_type_label( $k );
		$val   = intval( $counts[ $k ] ?? 0 );
		echo '<div class="locarc-kpi-chip">'
			. '<span class="locarc-kpi-chip-val">' . esc_html( $val ) . '</span>'
			. '<span class="locarc-kpi-chip-lbl">' . esc_html( $label ) . '</span>'
			. '</div>';
	}
	echo '</div>';
	echo '</div>'; // .locarc-kpi-row

	$toggle_url = admin_url( 'admin.php?page=locarc&tab=contracts' . ( $show_archived ? '' : '&archived=1' ) );
	echo '<div class="locarc-toolbar">';
	echo '<button class="button button-primary" id="locarc-add-contract">Nouveau contrat</button> ';
	echo '<a class="button" href="' . esc_url( $toggle_url ) . '">' . ( $show_archived ? 'Voir les contrats actifs' : 'Voir les contrats archivés' ) . '</a> ';
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline-block;">';
	wp_nonce_field( 'locarc_export_cheques_csv' );
	echo '<input type="hidden" name="action" value="locarc_export_cheques_csv" />';
	echo '<button class="button" type="submit">&#x2B07; Export ch&#232;ques CSV</button>';
	echo '</form>';
	echo '<span class="locarc-hint" style="margin-left:10px;">Astuce : cliquez sur les en-têtes pour trier (Nom, Fin…).</span>';
	echo '</div>';
	echo '<div class="locarc-toolbar"><details class="locarc-filters" open><summary>Filtres</summary><div class="locarc-toolbar-right"><input type="search" class="locarc-filter-input" data-table="locarc-contracts-table" placeholder="Filtrer (texte libre)" /></div></details></div>';

	echo '<table id="locarc-contracts-table" class="widefat striped locarc-table locarc-sortable"><thead><tr>
        <th data-sort="num">#</th>
        <th data-sort="text">N° licence</th>
        <th data-sort="text">Nom</th>
        <th data-sort="text">Prénom</th>
        <th data-sort="text">Type</th>
        <th data-sort="number">Montant</th>
        <th data-sort="date">Fin</th>
        <th data-sort="num">Payé</th>
        <th data-sort="none" style="width:120px">Actions</th>
    </tr></thead><tbody>';

	$i = 1;
	foreach ( $rows as $r ) {
		// Ensure name columns are filled even if locarc_members table doesn't contain the user.
		if ( trim( (string) ( $r['first_name'] ?? '' ) ) === '' && trim( (string) ( $r['last_name'] ?? '' ) ) === '' ) {
			[$fn, $ln] = locarc_member_names( $r['licence'] );
			if ( $fn !== '' || $ln !== '' ) {
				$r['first_name'] = $fn;
				$r['last_name']  = $ln;
			}
		}
		$row_class = ( $r['status'] === 'active' ) ? ( $r['is_paid'] ? 'locarc-paid' : 'locarc-unpaid' ) : '';
		if ( ( $r['contract_type'] ?? '' ) === 'pret' ) {
			// 'Prêt' is always 0€ and has no paid status.
			$row_class = '';
		}
		// ── Date formatting ────────────────────────────────────────────────
		$end_raw    = (string) ( $r['end_date'] ?? '' );
		$end_disp   = '';
		$date_class = '';
		if ( $end_raw !== '' ) {
			$end_ts = strtotime( $end_raw );
			if ( $end_ts !== false ) {
				$end_disp  = wp_date( 'd/m/Y', $end_ts );
				$now_ts    = current_datetime()->getTimestamp();
				$days_left = ( $end_ts - $now_ts ) / 86400;
				if ( $days_left < 0 ) {
					$date_class = 'locarc-date-expired';
				} elseif ( $days_left < 30 ) {
					$date_class = 'locarc-date-expiring';
				}
			}
		}

		// ── Paid badge ─────────────────────────────────────────────────────
		$is_pret     = ( ( $r['contract_type'] ?? '' ) === 'pret' );
		$is_paid_val = (int) ( $r['is_paid'] ?? 0 );
		if ( $is_pret ) {
			$paid_cell = '—';
		} else {
			$badge_mod = $is_paid_val ? 'locarc-paid-badge--paid' : 'locarc-paid-badge--unpaid';
			$badge_lbl = $is_paid_val ? 'Payé' : 'Non payé';
			$paid_cell = '<span class="locarc-paid-sort" style="display:none">' . $is_paid_val . '</span>'
						. '<button class="locarc-paid-badge ' . $badge_mod . '" type="button">' . esc_html( $badge_lbl ) . '</button>';
		}

		// ── Actions dropdown ───────────────────────────────────────────────
		$pdf_url = $r['pdf_path'] ? locarc_get_contract_pdf_url( $r ) : null;
		$dd      = '<div class="locarc-dropdown">'
			. '<button class="button locarc-dropdown-toggle" type="button">Actions &#9662;</button>'
			. '<ul class="locarc-dropdown-menu">';
		if ( $pdf_url ) {
			$dd .= '<li><a class="locarc-dropdown-item" href="' . esc_url( $pdf_url ) . '" target="_blank" rel="noopener">&#8659; Télécharger PDF</a></li>';
			$dd .= '<li><button class="locarc-dropdown-item locarc-pdf" type="button">&#8635; Régénérer PDF</button></li>';
		} else {
			$dd .= '<li><button class="locarc-dropdown-item locarc-pdf" type="button">&#8853; Générer PDF</button></li>';
		}
		$dd .= '<li><button class="locarc-dropdown-item locarc-send" type="button">&#9993; Envoyer par email</button></li>';
		$dd .= '<li role="separator" class="locarc-dropdown-sep"></li>';
		if ( $r['status'] === 'active' ) {
			$dd .= '<li><button class="locarc-dropdown-item locarc-edit-contract" type="button">&#9998; Modifier</button></li>';
			$dd .= '<li><button class="locarc-dropdown-item locarc-renew" type="button">&#8635; Renouveler</button></li>';
			$dd .= '<li><button class="locarc-dropdown-item locarc-archive locarc-dropdown-item--danger" type="button">&#10005; Archiver</button></li>';
		} else {
			$dd .= '<li><button class="locarc-dropdown-item locarc-restore" type="button">&#8617; Restaurer</button></li>';
			$dd .= '<li><button class="locarc-dropdown-item locarc-delete-contract locarc-dropdown-item--danger" type="button">&#8855; Supprimer définitivement</button></li>';
		}
		$dd .= '</ul></div>';

		$type_label     = locarc_contract_type_label( $r['contract_type'] );
		$amount_display = ( $r['contract_type'] === 'personnalise' )
			? ( ( $r['custom_price'] !== null && $r['custom_price'] !== '' ) ? (float) $r['custom_price'] : 0 )
			: locarc_contract_price_eur( $r['contract_type'] );
		$amount_display = number_format( (float) $amount_display, 0, ',', ' ' ) . ' €';

		$highlight_new_id = intval( wp_unslash( $_GET['new_id'] ?? 0 ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only highlight of newly created row after redirect, no state change.
		echo '<tr class="' . esc_attr( $row_class ) . '" data-id="' . esc_attr( $r['id'] ) . '"'
			. ( intval( $r['id'] ) === $highlight_new_id ? ' data-new="1"' : '' ) . '>'
			. '<td>' . intval( $i++ ) . '</td>'
			. '<td><code>' . esc_html( $r['licence'] ) . '</code></td>'
			. '<td>' . esc_html( $r['last_name'] ) . '</td>'
			. '<td>' . esc_html( $r['first_name'] ) . '</td>'
			. '<td><span class="locarc-type-label" data-type="' . esc_attr( $r['contract_type'] ) . '">' . esc_html( $type_label ) . '</span></td>'
			. '<td><span class="locarc-amount-display">' . esc_html( $amount_display ) . '</span></td>'
			. '<td class="' . esc_attr( $date_class ) . '">' . esc_html( $end_disp ) . '</td>'
			. '<td>' . wp_kses_post( $paid_cell ) . '</td>'
			. '<td>' . wp_kses_post( $dd ) . '</td>'
			. '</tr>';
	}

	echo '</tbody></table>';
	locarc_modal_markup();
}

function locarc_render_rented() {
	global $wpdb;
	$t    = locarc_tables();
	$rows = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT c.*, m.first_name, m.last_name FROM %i c LEFT JOIN %i m ON m.licence=c.licence WHERE c.status='active' ORDER BY m.last_name ASC, m.first_name ASC",
			$t['contracts'],
			$t['members']
		),
		ARRAY_A
	);

	// Filters: contract types
	$types = array();
	foreach ( $rows as $r ) {
		$k = (string) ( $r['contract_type'] ?? '' );
		if ( $k !== '' ) {
			$types[ $k ] = true;
		}
	}
	$types = array_keys( $types );
	natcasesort( $types );

	echo '<div class="locarc-toolbar">'
		. '<details class="locarc-filters" open>'
		. '<summary>Filtres</summary>'
		. '<div class="locarc-toolbar-right">'
		. '<input type="search" class="locarc-filter-input" data-table="locarc-rented-table" placeholder="Filtrer (texte libre)" />'
		. '<select multiple class="locarc-filter-select" data-table="locarc-rented-table" data-col="3"><option value="">Type (tous)</option>';
	foreach ( $types as $k ) {
		$label = locarc_contract_type_label( $k );
		echo '<option value="' . esc_attr( $label ) . '">' . esc_html( $label ) . '</option>';
	}
	echo '</select>'
		. '</div>'
		. '</details>'
		. '</div>';

	echo '<p class="description">Liste basée sur les contrats actifs. Cliquez sur un identifiant pour voir les caractéristiques. Modifier pour changer l\'affectation (autocomplete).</p>';

	echo '<table id="locarc-rented-table" class="widefat striped locarc-table locarc-sortable"><thead><tr>
        <th data-sort="num">#</th><th data-sort="text">Prénom</th><th data-sort="text">Nom</th><th data-sort="text">Type de contrat</th><th data-sort="text">Poignée</th><th data-sort="text">Branches</th><th data-sort="none">Actions</th>
    </tr></thead><tbody>';

	$i = 1;
	foreach ( $rows as $r ) {
		if ( trim( (string) ( $r['first_name'] ?? '' ) ) === '' && trim( (string) ( $r['last_name'] ?? '' ) ) === '' ) {
			[$fn, $ln] = locarc_member_names( $r['licence'] );
			if ( $fn !== '' || $ln !== '' ) {
				$r['first_name'] = $fn;
				$r['last_name']  = $ln;
			}
		}
		$type_label = locarc_contract_type_label( $r['contract_type'] ?? '' );
		echo '<tr data-id="' . esc_attr( $r['id'] ) . '">
            <td>' . intval( $i++ ) . '</td>
            <td>' . esc_html( $r['first_name'] ) . '</td>
            <td>' . esc_html( $r['last_name'] ) . '</td>
            <td>' . esc_html( $type_label ) . '</td>
            <td><a href="#" class="locarc-eq" data-kind="handles" data-identifier="' . esc_attr( $r['handle_identifier'] ) . '">' . esc_html( $r['handle_identifier'] ?: '-' ) . '</a></td>
            <td><a href="#" class="locarc-eq" data-kind="branches" data-identifier="' . esc_attr( $r['branches_identifier'] ) . '">' . esc_html( $r['branches_identifier'] ?: '-' ) . '</a></td>
            <td><button class="button locarc-edit-assignment">Modifier</button></td>
        </tr>';
	}
	echo '</tbody></table>';
	locarc_modal_markup();
}

function locarc_render_imports() {
	echo '<h2>Imports</h2>';

	$imported_count = isset( $_GET['imported'] ) ? intval( wp_unslash( $_GET['imported'] ) ) : null; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only success notice after redirect from nonce-verified admin_post handler.
	if ( $imported_count !== null ) {
		echo '<div class="notice notice-success is-dismissible"><p>✅ Import terminé : <strong>' . intval( $imported_count ) . '</strong> lignes traitées.</p></div>';
	}
	$import_error = isset( $_GET['import_error'] ) ? sanitize_text_field( wp_unslash( $_GET['import_error'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only error notice after redirect from nonce-verified admin_post handler.
	if ( $import_error !== '' ) {
		echo '<div class="notice notice-error is-dismissible"><p>❌ Import impossible : ' . esc_html( $import_error ) . '</p></div>';
	}

	echo '<p>Le plugin peut importer des fichiers <strong>CSV</strong> (export Excel) pour rester léger (sans dépendances PHPSpreadsheet).</p>';

	echo '<div class="locarc-import-grid">';
	echo '<div class="locarc-import-card">';
	echo '<h3>Adhérents (licences)</h3>';
	echo '<p>CSV avec en-têtes : Code Adhérent, Nom, Prénom, Date de naissance, Email, Téléphone, Adresse, Code postal, Ville…</p>';
	echo '<form method="post" enctype="multipart/form-data" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
	wp_nonce_field( 'locarc_import_members' );
	echo '<input type="hidden" name="action" value="locarc_import_members" />';
	echo '<input type="file" name="csv" accept=".csv" required /> ';
	echo '<button class="button button-primary">Importer</button>';
	echo '</form>';
	echo '</div>';

	echo '<div class="locarc-import-card">';
	echo '<h3>Matériel (Branches)</h3>';
	echo '<p>CSV avec en-têtes : Identificateur, Marque, Modèle, Taille, Puissance, Dispo ?, Prix d’achat, Date d’achat…</p>';
	echo '<form method="post" enctype="multipart/form-data" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
	wp_nonce_field( 'locarc_import_branches' );
	echo '<input type="hidden" name="action" value="locarc_import_branches" />';
	echo '<input type="file" name="csv" accept=".csv" required /> ';
	echo '<button class="button button-primary">Importer</button>';
	echo '</form>';
	echo '</div>';

	echo '<div class="locarc-import-card">';
	echo '<h3>Matériel (Poignées)</h3>';
	echo '<p>CSV avec en-têtes : Identificateur, Marque, Modèle, Taille, Latéralité, Couleur, Dispo ?, Prix d’achat, Date d’achat…</p>';
	echo '<form method="post" enctype="multipart/form-data" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
	wp_nonce_field( 'locarc_import_handles' );
	echo '<input type="hidden" name="action" value="locarc_import_handles" />';
	echo '<input type="file" name="csv" accept=".csv" required /> ';
	echo '<button class="button button-primary">Importer</button>';
	echo '</form>';
	echo '</div>';

	echo '<div class="locarc-import-card">';
	echo '<h3>Initialiser « Matériel loué »</h3>';
	echo '<p>Charge une liste initiale (contrats + affectations) fournie avec le plugin.</p>';
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
	wp_nonce_field( 'locarc_seed_rented' );
	echo '<input type="hidden" name="action" value="locarc_seed_rented" />';
	echo '<button class="button">Charger la liste</button>';
	echo '</form>';
	echo '</div>';
	echo '</div>';
}


function locarc_render_email_settings() {
	echo '<h2>Email automatique</h2>';
	if ( isset( $_POST['locarc_save_email'] ) ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Forbidden', 'gestion-location-darc' ) );
		}
		check_admin_referer( 'locarc_save_email' );

		$subject    = sanitize_text_field( wp_unslash( $_POST['email_subject'] ?? '' ) );
		$body       = wp_kses_post( wp_unslash( $_POST['email_body'] ?? '' ) );
		$email_from = sanitize_text_field( wp_unslash( $_POST['email_from'] ?? '' ) );
		$email_to   = sanitize_text_field( wp_unslash( $_POST['email_to'] ?? '' ) );
		$email_cc   = sanitize_text_field( wp_unslash( $_POST['email_cc'] ?? '' ) );
		$email_bcc  = sanitize_text_field( wp_unslash( $_POST['email_bcc'] ?? '' ) );

		$rem_subject   = sanitize_text_field( wp_unslash( $_POST['reminder_subject'] ?? '' ) );
		$rem_body      = wp_kses_post( wp_unslash( $_POST['reminder_body'] ?? '' ) );
		$reminder_from = sanitize_text_field( wp_unslash( $_POST['reminder_from'] ?? '' ) );
		$reminder_to   = sanitize_text_field( wp_unslash( $_POST['reminder_to'] ?? '' ) );
		$reminder_cc   = sanitize_text_field( wp_unslash( $_POST['reminder_cc'] ?? '' ) );
		$reminder_bcc  = sanitize_text_field( wp_unslash( $_POST['reminder_bcc'] ?? '' ) );

		update_option( 'locarc_email_subject', $subject );
		update_option( 'locarc_email_body', $body );
		update_option( 'locarc_email_from', $email_from );
		update_option( 'locarc_email_to', $email_to );
		update_option( 'locarc_email_cc', $email_cc );
		update_option( 'locarc_email_bcc', $email_bcc );

		update_option( 'locarc_reminder_subject', $rem_subject );
		update_option( 'locarc_reminder_body', $rem_body );
		update_option( 'locarc_reminder_from', $reminder_from );
		update_option( 'locarc_reminder_to', $reminder_to );
		update_option( 'locarc_reminder_cc', $reminder_cc );
		update_option( 'locarc_reminder_bcc', $reminder_bcc );

		echo '<div class="notice notice-success is-dismissible"><p>✅ Email enregistré.</p></div>';
	}

	$subject    = wp_unslash( (string) get_option( 'locarc_email_subject', 'Votre contrat de location et facture' ) );
	$body       = wp_unslash(
		(string) get_option(
			'locarc_email_body',
			'Bonjour {{prenom}},

Veuillez trouver votre contrat en pièce jointe.

Cordialement,
ACSIM'
		)
	);
	$email_from = wp_unslash( (string) get_option( 'locarc_email_from', get_option( 'admin_email' ) ) );
	$email_to   = wp_unslash( (string) get_option( 'locarc_email_to', '{{email}}' ) );
	$email_cc   = wp_unslash( (string) get_option( 'locarc_email_cc', '' ) );
	$email_bcc  = wp_unslash( (string) get_option( 'locarc_email_bcc', '' ) );

	$rem_subject   = wp_unslash( (string) get_option( 'locarc_reminder_subject', 'Votre contrat se termine bientôt' ) );
	$rem_body      = wp_unslash(
		(string) get_option(
			'locarc_reminder_body',
			'Bonjour {{prenom}},

Petit rappel : votre contrat de location se termine le {{date_fin}}.

Merci de prendre contact avec le club pour le renouvellement ou la restitution.

Cordialement,
ACSIM'
		)
	);
	$reminder_from = wp_unslash( (string) get_option( 'locarc_reminder_from', get_option( 'admin_email' ) ) );
	$reminder_to   = wp_unslash( (string) get_option( 'locarc_reminder_to', '{{email}}' ) );
	$reminder_cc   = wp_unslash( (string) get_option( 'locarc_reminder_cc', '' ) );
	$reminder_bcc  = wp_unslash( (string) get_option( 'locarc_reminder_bcc', '' ) );

	echo '<form method="post">';
	wp_nonce_field( 'locarc_save_email' );
	echo '<table class="form-table"><tbody>';

	echo '<tr><th scope="row"><label for="locarc-email-from">De</label></th><td><input id="locarc-email-from" type="text" name="email_from" value="' . esc_attr( $email_from ) . '" class="regular-text" style="width:100%" />';
	echo '<p class="description">Adresse d’envoi utilisée pour l’email de contrat.</p></td></tr>';
	echo '<tr><th scope="row"><label for="locarc-email-to">Destinataire</label></th><td><input id="locarc-email-to" type="text" name="email_to" value="' . esc_attr( $email_to ) . '" class="regular-text" style="width:100%" />';
	echo '<p class="description">Une ou plusieurs adresses, séparées par une virgule ou un point-virgule. Variable recommandée : <code>{{email}}</code>.</p></td></tr>';
	echo '<tr><th scope="row"><label for="locarc-email-cc">CC</label></th><td><input id="locarc-email-cc" type="text" name="email_cc" value="' . esc_attr( $email_cc ) . '" class="regular-text" style="width:100%" />';
	echo '<p class="description">Adresse(s) en copie pour l’envoi du contrat. Utiliser <code>{{club_email}}</code> pour reprendre l’adresse du club.</p></td></tr>';
	echo '<tr><th scope="row"><label for="locarc-email-bcc">CCI</label></th><td><input id="locarc-email-bcc" type="text" name="email_bcc" value="' . esc_attr( $email_bcc ) . '" class="regular-text" style="width:100%" />';
	echo '<p class="description">Adresse(s) en copie cachée pour l’envoi du contrat.</p></td></tr>';
	echo '<tr><th scope="row"><label for="locarc-email-subject">Sujet</label></th><td><input id="locarc-email-subject" type="text" name="email_subject" value="' . esc_attr( $subject ) . '" class="regular-text" style="width:100%" /></td></tr>';
	echo '<tr><th scope="row"><label for="locarc-email-body">Contenu</label></th><td>';
	wp_editor(
		$body,
		'locarc_email_body_editor',
		array(
			'textarea_name' => 'email_body',
			'textarea_rows' => 12,
		)
	);
	echo '<p class="description">Variables disponibles : <code>{{prenom}}</code> <code>{{nom}}</code> <code>{{licence}}</code> <code>{{date_fin}}</code> <code>{{contract_number}}</code> <code>{{email}}</code> <code>{{club_email}}</code></p>';
	echo '</td></tr>';

	echo '<tr><th scope="row" colspan="2"><hr><h3 style="margin:0">Rappel fin de contrat (loueur)</h3></th></tr>';
	echo '<tr><th scope="row"><label for="locarc-reminder-from">De</label></th><td><input id="locarc-reminder-from" type="text" name="reminder_from" value="' . esc_attr( $reminder_from ) . '" class="regular-text" style="width:100%" />';
	echo '<p class="description">Adresse d’envoi utilisée pour le rappel.</p></td></tr>';
	echo '<tr><th scope="row"><label for="locarc-reminder-to">Destinataire</label></th><td><input id="locarc-reminder-to" type="text" name="reminder_to" value="' . esc_attr( $reminder_to ) . '" class="regular-text" style="width:100%" />';
	echo '<p class="description">Une ou plusieurs adresses, séparées par une virgule ou un point-virgule. Variable recommandée : <code>{{email}}</code>.</p></td></tr>';
	echo '<tr><th scope="row"><label for="locarc-reminder-cc">CC</label></th><td><input id="locarc-reminder-cc" type="text" name="reminder_cc" value="' . esc_attr( $reminder_cc ) . '" class="regular-text" style="width:100%" /></td></tr>';
	echo '<tr><th scope="row"><label for="locarc-reminder-bcc">CCI</label></th><td><input id="locarc-reminder-bcc" type="text" name="reminder_bcc" value="' . esc_attr( $reminder_bcc ) . '" class="regular-text" style="width:100%" /></td></tr>';
	echo '<tr><th scope="row"><label for="locarc-rem-subject">Sujet</label></th><td><input id="locarc-rem-subject" type="text" name="reminder_subject" value="' . esc_attr( $rem_subject ) . '" class="regular-text" style="width:100%" /></td></tr>';
	echo '<tr><th scope="row"><label for="locarc-rem-body">Contenu</label></th><td>';
	wp_editor(
		$rem_body,
		'locarc_reminder_body_editor',
		array(
			'textarea_name' => 'reminder_body',
			'textarea_rows' => 10,
		)
	);
	echo '<p class="description">Variables disponibles : <code>{{prenom}}</code> <code>{{nom}}</code> <code>{{licence}}</code> <code>{{date_fin}}</code> <code>{{contract_number}}</code> <code>{{email}}</code></p>';
	echo '</td></tr>';

	echo '</tbody></table>';
	submit_button( 'Enregistrer', 'primary', 'locarc_save_email' );
	echo '</form>';
}

/** Modal skeleton */
function locarc_modal_markup() {
	echo '<div id="locarc-modal" class="locarc-modal" style="display:none;">
        <div class="locarc-modal-backdrop"></div>
        <div class="locarc-modal-dialog">
            <div class="locarc-modal-header">
                <div class="locarc-modal-title"></div>
                <button class="locarc-modal-close" aria-label="Fermer">&times;</button>
            </div>
            <div class="locarc-modal-body"></div>
            <div class="locarc-modal-footer"></div>
        </div>
    </div>';
}


/**
 * Validate an uploaded CSV file: checks upload error, size limit, and MIME type.
 * Returns WP_Error on failure, true on success.
 */
function locarc_validate_csv_upload( $file_key = 'csv' ) {
	$file = $_FILES[ $file_key ] ?? null; // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- nonce is verified by caller (check_admin_referer); tmp_name is a server-generated path, not user-controlled data.
	if ( ! $file || empty( $file['tmp_name'] ) ) {
		return new WP_Error( 'no_file', 'Aucun fichier reçu.' );
	}
	if ( $file['error'] !== UPLOAD_ERR_OK ) {
		return new WP_Error( 'upload_error', 'Erreur lors de l\'upload (code : ' . intval( $file['error'] ) . '). Fichier trop volumineux ?' );
	}
	// 5 MB hard cap (CSV files should never be this large).
	if ( $file['size'] > 5 * 1024 * 1024 ) {
		return new WP_Error( 'too_large', 'Le fichier dépasse la limite de 5 Mo.' );
	}
	// Allowed MIME types for CSV exports from Excel / LibreOffice.
	$allowed_mimes = array(
		'text/csv',
		'text/plain',
		'application/csv',
		'application/vnd.ms-excel',
		'application/octet-stream',
	);
	$finfo         = new finfo( FILEINFO_MIME_TYPE );
	$detected      = $finfo ? $finfo->file( $file['tmp_name'] ) : '';
	// Also accept the client-declared type as a secondary signal.
	$client = strtolower( trim( (string) ( $file['type'] ?? '' ) ) );
	if ( ! in_array( $detected, $allowed_mimes, true ) && ! in_array( $client, $allowed_mimes, true ) ) {
		return new WP_Error( 'bad_mime', 'Type de fichier non autorisé (détecté : ' . esc_html( $detected ) . '). Veuillez uploader un fichier CSV.' );
	}
	return true;
}

/** Admin-post imports */
add_action(
	'admin_post_locarc_import_members',
	function () {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Forbidden', 'gestion-location-darc' ) );
		}
		check_admin_referer( 'locarc_import_members' );
		$validate = locarc_validate_csv_upload( 'csv' );
		if ( is_wp_error( $validate ) ) {
			wp_safe_redirect( admin_url( 'admin.php?page=locarc&tab=imports&import_error=' . rawurlencode( $validate->get_error_message() ) ) );
			exit;
		}
		$csv_tmp_name = isset( $_FILES['csv']['tmp_name'] ) ? (string) $_FILES['csv']['tmp_name'] : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- tmp_name is a server-generated temp path, never user input.
		$count        = locarc_import_members_from_csv( $csv_tmp_name );
		if ( is_wp_error( $count ) ) {
			wp_safe_redirect( admin_url( 'admin.php?page=locarc&tab=imports&import_error=' . rawurlencode( $count->get_error_message() ) ) );
			exit;
		}
		wp_safe_redirect( admin_url( 'admin.php?page=locarc&tab=imports&imported=' . intval( $count ) ) );
		exit;
	}
);
add_action(
	'admin_post_locarc_import_branches',
	function () {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Forbidden', 'gestion-location-darc' ) );
		}
		check_admin_referer( 'locarc_import_branches' );
		$validate = locarc_validate_csv_upload( 'csv' );
		if ( is_wp_error( $validate ) ) {
			wp_safe_redirect( admin_url( 'admin.php?page=locarc&tab=imports&import_error=' . rawurlencode( $validate->get_error_message() ) ) );
			exit;
		}
		$csv_tmp_name = isset( $_FILES['csv']['tmp_name'] ) ? (string) $_FILES['csv']['tmp_name'] : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- tmp_name is a server-generated temp path, never user input.
		$count        = locarc_import_matos_from_csv( $csv_tmp_name, 'branches' );
		if ( is_wp_error( $count ) ) {
			wp_safe_redirect( admin_url( 'admin.php?page=locarc&tab=imports&import_error=' . rawurlencode( $count->get_error_message() ) ) );
			exit;
		}
		wp_safe_redirect( admin_url( 'admin.php?page=locarc&tab=imports&imported=' . intval( $count ) ) );
		exit;
	}
);
add_action(
	'admin_post_locarc_import_handles',
	function () {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Forbidden', 'gestion-location-darc' ) );
		}
		check_admin_referer( 'locarc_import_handles' );
		$validate = locarc_validate_csv_upload( 'csv' );
		if ( is_wp_error( $validate ) ) {
			wp_safe_redirect( admin_url( 'admin.php?page=locarc&tab=imports&import_error=' . rawurlencode( $validate->get_error_message() ) ) );
			exit;
		}
		$csv_tmp_name = isset( $_FILES['csv']['tmp_name'] ) ? (string) $_FILES['csv']['tmp_name'] : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- tmp_name is a server-generated temp path, never user input.
		$count        = locarc_import_matos_from_csv( $csv_tmp_name, 'handles' );
		if ( is_wp_error( $count ) ) {
			wp_safe_redirect( admin_url( 'admin.php?page=locarc&tab=imports&import_error=' . rawurlencode( $count->get_error_message() ) ) );
			exit;
		}
		wp_safe_redirect( admin_url( 'admin.php?page=locarc&tab=imports&imported=' . intval( $count ) ) );
		exit;
	}
);

add_action(
	'admin_post_locarc_seed_rented',
	function () {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Forbidden', 'gestion-location-darc' ) );
		}
		check_admin_referer( 'locarc_seed_rented' );
		$csv   = LOCARC_PLUGIN_DIR . 'data/materiel-loue-seed.csv';
		$count = locarc_import_rented_from_csv( $csv );
		if ( is_wp_error( $count ) ) {
			wp_safe_redirect( admin_url( 'admin.php?page=locarc&tab=imports&import_error=' . rawurlencode( $count->get_error_message() ) ) );
			exit;
		}
		wp_safe_redirect( admin_url( 'admin.php?page=locarc&tab=imports&imported=' . intval( $count ) ) );
		exit;
	}
);


function locarc_log_contract_field_labels() {
	return array(
		'licence'                  => 'Licence',
		'contract_type'            => 'Type',
		'custom_price'             => 'Montant personnalisé',
		'start_date'               => 'Date de début',
		'end_date'                 => 'Date de fin',
		'handle_identifier'        => 'Poignée',
		'branches_identifier'      => 'Branches',
		'handle_brand'             => 'Marque poignée',
		'handle_model'             => 'Modèle poignée',
		'handle_size'              => 'Taille poignée',
		'handle_handedness'        => 'Latéralité poignée',
		'branches_brand'           => 'Marque branches',
		'branches_model'           => 'Modèle branches',
		'branches_size'            => 'Taille branches',
		'branches_power'           => 'Puissance branches',
		'sight_identifier'         => 'Viseur',
		'sight_brand'              => 'Marque viseur',
		'sight_model'              => 'ModÃ¨le viseur',
		'sight_handedness'         => 'LatÃ©ralitÃ© viseur',
		'stabilization_identifier' => 'Stabilisation',
		'stabilization_brand'      => 'Marque stabilisation',
		'stabilization_model'      => 'ModÃ¨le stabilisation',
		'payment_method'           => 'Paiement',
		'caution_amount'           => 'Caution',
		'payment_due_1'            => 'Échéance 1',
		'payment_due_2'            => 'Échéance 2',
		'payment_due_3'            => 'Échéance 3',
		'payment_due_4'            => 'Échéance 4',
		'is_paid'                  => 'Payé',
		'status'                   => 'Statut',
	);
}

function locarc_log_inventory_field_labels( $kind ) {
	if ( $kind === 'branches' ) {
		return array(
			'identifier'     => 'Identifiant',
			'brand'          => 'Marque',
			'model'          => 'Modèle',
			'size'           => 'Taille',
			'power'          => 'Puissance',
			'comment'        => 'Commentaire',
			'is_available'   => 'Disponibilité',
			'purchase_year'  => 'Année',
			'purchase_price' => 'Prix',
		);
	}
	return array(
		'identifier'     => 'Identifiant',
		'brand'          => 'Marque',
		'model'          => 'Modèle',
		'size'           => 'Taille',
		'handedness'     => 'Latéralité',
		'color'          => 'Couleur',
		'comment'        => 'Commentaire',
		'is_available'   => 'Disponibilité',
		'purchase_year'  => 'Année',
		'purchase_price' => 'Prix',
	);
}

function locarc_normalize_date_or_error( $value, $label, $required = false ) {
	$value = sanitize_text_field( (string) $value );
	if ( $value === '' ) {
		if ( $required ) {
			return new WP_Error( 'invalid_date', $label . ' est obligatoire.' );
		}
		return '';
	}
	$dt = DateTime::createFromFormat( '!Y-m-d', $value );
	if ( ! $dt || $dt->format( 'Y-m-d' ) !== $value ) {
		return new WP_Error( 'invalid_date', $label . ' doit être une date valide au format AAAA-MM-JJ.' );
	}
	return $value;
}

function locarc_validate_contract_dates( $start, $end, array $payment_dues = array() ) {
	$start = locarc_normalize_date_or_error( $start, 'La date de début', true );
	if ( is_wp_error( $start ) ) {
		return $start;
	}
	$end = locarc_normalize_date_or_error( $end, 'La date de fin', true );
	if ( is_wp_error( $end ) ) {
		return $end;
	}
	if ( strtotime( $end ) < strtotime( $start ) ) {
		return new WP_Error( 'invalid_date_order', 'La date de fin doit être postérieure ou égale à la date de début.' );
	}
	$clean_dues = array();
	foreach ( $payment_dues as $index => $due ) {
		$clean = locarc_normalize_date_or_error( $due, 'L’échéance ' . ( $index + 1 ), false );
		if ( is_wp_error( $clean ) ) {
			return $clean;
		}
		$clean_dues[ $index ] = $clean;
	}
	return array(
		'start'        => $start,
		'end'          => $end,
		'payment_dues' => $clean_dues,
	);
}

/** AJAX endpoints */
add_action( 'wp_ajax_locarc_get_item', 'locarc_ajax_get_item' );
function locarc_ajax_get_item() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( 'forbidden', 403 );
	}
	check_ajax_referer( 'locarc_nonce', 'nonce' );
	global $wpdb;
	$t    = locarc_tables();
	$kind = sanitize_key( wp_unslash( $_GET['kind'] ?? '' ) );
	$id   = intval( wp_unslash( $_GET['id'] ?? 0 ) );
	if ( ! $id || ! in_array( $kind, array( 'branches', 'handles', 'sights', 'stabilizations', 'init_bows', 'contracts', 'members' ), true ) ) {
		wp_send_json_error( 'bad_request', 400 );
	}

	$table = $t[ $kind ]; // $kind is validated against a whitelist above; $table comes from locarc_tables().
	// For members, only return non-sensitive fields needed by the UI.
	if ( $kind === 'members' ) {
		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT id, licence, first_name, last_name, dob, email, phone, address1, postal_code, city, updated_at FROM %i WHERE id=%d',
				$table,
				$id
			),
			ARRAY_A
		);
	} else {
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id=%d', $table, $id ), ARRAY_A );
	}

	if ( ! $row ) {
		wp_send_json_error( 'not_found', 404 );
	}
	wp_send_json_success( $row );
}

add_action( 'wp_ajax_locarc_save_item', 'locarc_ajax_save_item' );
function locarc_ajax_save_item() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( 'forbidden', 403 );
	}
	check_ajax_referer( 'locarc_nonce', 'nonce' );

	global $wpdb;
	$t    = locarc_tables();
	$kind = sanitize_key( wp_unslash( $_POST['kind'] ?? '' ) );
	$id   = intval( wp_unslash( $_POST['id'] ?? 0 ) );
	if ( ! in_array( $kind, array( 'branches', 'handles', 'sights', 'stabilizations', 'init_bows', 'members' ), true ) ) {
		wp_send_json_error( 'bad_kind', 400 );
	}

	$data    = array();
	$formats = array();

	if ( $kind === 'members' ) {
		$fields = array( 'licence', 'last_name', 'first_name', 'dob', 'email', 'phone', 'address1', 'postal_code', 'city' );
	} elseif ( $kind === 'sights' ) {
		$fields = array( 'identifier', 'brand', 'model', 'handedness', 'comment', 'is_available', 'purchase_year', 'purchase_price' );
	} elseif ( $kind === 'stabilizations' ) {
		$fields = array( 'identifier', 'brand', 'model', 'comment', 'is_available', 'purchase_year', 'purchase_price' );
	} elseif ( $kind === 'init_bows' ) {
		$fields = array( 'identifier', 'brand', 'model', 'size', 'power', 'handedness', 'comment', 'is_available', 'purchase_year', 'purchase_price' );
	} else {
		$fields = ( $kind === 'branches' )
			? array( 'identifier', 'size', 'power', 'brand', 'model', 'comment', 'is_available', 'purchase_year', 'purchase_price' )
			: array( 'identifier', 'size', 'handedness', 'brand', 'model', 'color', 'comment', 'is_available', 'purchase_year', 'purchase_price' );
	}

	foreach ( $fields as $f ) {
		if ( ! isset( $_POST[ $f ] ) ) {
			continue;
		}
		$v = wp_unslash( $_POST[ $f ] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized below per field type.
		if ( $f === 'dob' ) {
			$data[ $f ] = ( $v === '' ? null : sanitize_text_field( $v ) );
			$formats[]  = '%s';
		} elseif ( in_array( $f, array( 'size', 'power', 'purchase_year' ), true ) ) {
			$data[ $f ] = intval( $v );
			$formats[]  = '%d'; } elseif ( $f === 'is_available' ) {
			$ival       = intval( $v );
			$data[ $f ] = in_array( $ival, array( 0, 1, 2, 3, 4, 5 ), true ) ? $ival : 0;
			$formats[]  = '%d';
			} elseif ( $f === 'purchase_price' ) {
				$data[ $f ] = ( $v === '' ? null : floatval( str_replace( ',', '.', $v ) ) );
				$formats[]  = '%f'; } else {
				$data[ $f ] = sanitize_text_field( $v );
				$formats[]  = '%s'; }
	}
	$data['updated_at'] = current_time( 'mysql' );
	$formats[]          = '%s';

	$table = $t[ $kind ]; // $kind is validated against a whitelist above; $table comes from locarc_tables().

	$previous = null;
	if ( $id > 0 ) {
		$previous = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id=%d', $table, $id ), ARRAY_A );
		$result   = $wpdb->update( $table, $data, array( 'id' => $id ), $formats, array( '%d' ) );
		if ( $result === false ) {
			wp_send_json_error( 'Erreur base de données : ' . $wpdb->last_error, 500 );
		}
		$saved_id = $id;
	} else {
		$result = $wpdb->insert( $table, $data, $formats );
		if ( $result === false ) {
			$db_err = $wpdb->last_error;
			if ( $kind === 'members' && strpos( $db_err, 'uq_licence' ) !== false ) {
				wp_send_json_error( 'Un licencié avec ce numéro de licence existe déjà.', 409 );
			}
			wp_send_json_error( 'Erreur base de données : ' . $db_err, 500 );
		}
		$saved_id = intval( $wpdb->insert_id );
	}

	$saved = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id=%d', $table, $saved_id ), ARRAY_A );

	if ( 'members' !== $kind && $saved ) {
		$object_type  = ( $kind === 'branches' ) ? 'branch' : ( ( $kind === 'handles' ) ? 'handle' : $kind );
		$object_label = locarc_log_inventory_label_from_row( $saved );
		if ( $id > 0 && $previous ) {
			$changes = locarc_log_extract_changes( $previous, $saved, locarc_log_inventory_field_labels( $kind ) );
			if ( ! empty( $changes ) ) {
				locarc_log_insert( $object_type, 'update', $saved_id, $object_label, implode( "\n", $changes ) );
			}
		} else {
			$summary = array();
			foreach ( locarc_log_inventory_field_labels( $kind ) as $field => $label ) {
				if ( ! array_key_exists( $field, $saved ) ) {
					continue;
				}
				$summary[] = $label . ' : ' . locarc_log_format_value( $field, $saved[ $field ] );
			}
			locarc_log_insert( $object_type, 'create', $saved_id, $object_label, implode( "\n", $summary ) );
		}
	}

	// If an item manually goes from "Non" (0) to "Oui" (1), detach it from the active contract
	// so the "Matériel loué" view immediately reflects the release.
	if ( $kind !== 'members' && $previous && isset( $previous['is_available'], $saved['is_available'] ) ) {
		$old_status = intval( $previous['is_available'] );
		$new_status = intval( $saved['is_available'] );
		if ( $old_status === 0 && $new_status === 1 && ! empty( $saved['identifier'] ) ) {
			locarc_unassign_equipment_from_active_contracts( $kind, $saved['identifier'] );
			locarc_sync_availability();
			$saved = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id=%d', $table, $saved_id ), ARRAY_A );
		}
	}

	// Sync denormalized characteristics on active contracts when inventory is edited.
	// If a handle or branches item is modified, propagate brand/model/size/etc. to every
	// active contract that references this identifier (source of truth = inventory table).
	if ( $id > 0 && $saved && 'members' !== $kind && ! empty( $saved['identifier'] ) ) {
		$identifier = (string) $saved['identifier'];
		if ( $kind === 'branches' ) {
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE %i
                 SET branches_brand  = %s,
                     branches_model  = %s,
                     branches_size   = %s,
                     branches_power  = %s,
                     updated_at      = %s
                 WHERE status = 'active'
                   AND branches_identifier = %s",
					$t['contracts'],
					$saved['brand'] ?? '',
					$saved['model'] ?? '',
					( $saved['size'] !== null && $saved['size'] !== '' ) ? (string) $saved['size'] : null,
					( $saved['power'] !== null && $saved['power'] !== '' ) ? (string) $saved['power'] : null,
					current_time( 'mysql' ),
					$identifier
				)
			);
		} elseif ( $kind === 'handles' ) {
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE %i
                 SET handle_brand     = %s,
                     handle_model     = %s,
                     handle_size      = %s,
                     handle_handedness = %s,
                     updated_at       = %s
                 WHERE status = 'active'
                   AND handle_identifier = %s",
					$t['contracts'],
					$saved['brand'] ?? '',
					$saved['model'] ?? '',
					( $saved['size'] !== null && $saved['size'] !== '' ) ? (string) $saved['size'] : null,
					$saved['handedness'] ?? '',
					current_time( 'mysql' ),
					$identifier
				)
			);
		} elseif ( $kind === 'sights' ) {
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE %i
                 SET sight_brand      = %s,
                     sight_model      = %s,
                     sight_handedness = %s,
                     updated_at       = %s
                 WHERE status = 'active'
                   AND sight_identifier = %s",
					$t['contracts'],
					$saved['brand'] ?? '',
					$saved['model'] ?? '',
					$saved['handedness'] ?? '',
					current_time( 'mysql' ),
					$identifier
				)
			);
		} elseif ( $kind === 'stabilizations' ) {
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE %i
                 SET stabilization_brand = %s,
                     stabilization_model = %s,
                     updated_at          = %s
                 WHERE status = 'active'
                   AND stabilization_identifier = %s",
					$t['contracts'],
					$saved['brand'] ?? '',
					$saved['model'] ?? '',
					current_time( 'mysql' ),
					$identifier
				)
			);
		}
	}

	wp_send_json_success(
		array(
			'id'   => $saved_id,
			'item' => $saved,
		)
	);
}

add_action( 'wp_ajax_locarc_delete_item', 'locarc_ajax_delete_item' );
function locarc_ajax_delete_item() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( 'forbidden', 403 );
	}
	check_ajax_referer( 'locarc_nonce', 'nonce' );
	global $wpdb;
	$t    = locarc_tables();
	$kind = sanitize_key( wp_unslash( $_POST['kind'] ?? '' ) );
	$id   = intval( wp_unslash( $_POST['id'] ?? 0 ) );
	if ( ! $id || ! in_array( $kind, array( 'branches', 'handles', 'sights', 'stabilizations', 'init_bows' ), true ) ) {
		wp_send_json_error( 'bad_request', 400 );
	}
	$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id=%d', $t[ $kind ], $id ), ARRAY_A );
	if ( ! $row ) {
		wp_send_json_error( 'not_found', 404 );
	}
	$object_type  = ( $kind === 'branches' ) ? 'branch' : ( ( $kind === 'handles' ) ? 'handle' : $kind );
	$object_label = locarc_log_inventory_label_from_row( $row );
	$details      = array();
	foreach ( locarc_log_inventory_field_labels( $kind ) as $field => $label ) {
		if ( ! array_key_exists( $field, $row ) ) {
			continue;
		}
		$details[] = $label . ' : ' . locarc_log_format_value( $field, $row[ $field ] );
	}
	locarc_log_insert( $object_type, 'delete', $id, $object_label, implode( "\n", $details ) );
	$wpdb->delete( $t[ $kind ], array( 'id' => $id ), array( '%d' ) );
	locarc_sync_availability();
	wp_send_json_success( true );
}

add_action( 'wp_ajax_locarc_update_paid', 'locarc_ajax_update_paid' );
function locarc_ajax_update_paid() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( 'forbidden', 403 );
	}
	check_ajax_referer( 'locarc_nonce', 'nonce' );
	global $wpdb;
	$t    = locarc_tables();
	$id   = intval( wp_unslash( $_POST['id'] ?? 0 ) );
	$paid = intval( wp_unslash( $_POST['is_paid'] ?? 0 ) ) ? 1 : 0;
	if ( ! $id ) {
		wp_send_json_error( 'bad_request', 400 );
	}
	$before = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id=%d', $t['contracts'], $id ), ARRAY_A );
	if ( ! $before ) {
		wp_send_json_error( 'not_found', 404 );
	}
	$wpdb->update(
		$t['contracts'],
		array(
			'is_paid'    => $paid,
			'updated_at' => current_time( 'mysql' ),
		),
		array( 'id' => $id ),
		array( '%d', '%s' ),
		array( '%d' )
	);
	$after = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id=%d', $t['contracts'], $id ), ARRAY_A );
	if ( $after ) {
		$details = 'Payé : ' . locarc_log_format_value( 'is_paid', $before['is_paid'] ?? null ) . ' → ' . locarc_log_format_value( 'is_paid', $after['is_paid'] ?? null );
		locarc_log_insert( 'contract', 'toggle_paid', $id, locarc_log_contract_label_from_row( $after ), $details );
	}
	wp_send_json_success( true );
}

add_action( 'wp_ajax_locarc_archive_contract', 'locarc_ajax_archive_contract' );
function locarc_ajax_archive_contract() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( 'forbidden', 403 );
	}
	check_ajax_referer( 'locarc_nonce', 'nonce' );
	global $wpdb;
	$t  = locarc_tables();
	$id = intval( wp_unslash( $_POST['id'] ?? 0 ) );
	if ( ! $id ) {
		wp_send_json_error( 'bad_request', 400 );
	}
	$before = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id=%d', $t['contracts'], $id ), ARRAY_A );
	if ( ! $before ) {
		wp_send_json_error( 'not_found', 404 );
	}
	$wpdb->update(
		$t['contracts'],
		array(
			'status'     => 'archived',
			'updated_at' => current_time( 'mysql' ),
		),
		array( 'id' => $id ),
		array( '%s', '%s' ),
		array( '%d' )
	);
	locarc_sync_availability();
	$after = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id=%d', $t['contracts'], $id ), ARRAY_A );
	if ( $after ) {
		locarc_log_insert( 'contract', 'archive', $id, locarc_log_contract_label_from_row( $after ), 'Statut : ' . locarc_log_format_value( 'status', $before['status'] ?? null ) . ' → ' . locarc_log_format_value( 'status', $after['status'] ?? null ) );
	}
	wp_send_json_success( true );
}

add_action( 'wp_ajax_locarc_restore_contract', 'locarc_ajax_restore_contract' );
function locarc_ajax_restore_contract() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( 'forbidden', 403 );
	}
	check_ajax_referer( 'locarc_nonce', 'nonce' );
	global $wpdb;
	$t  = locarc_tables();
	$id = intval( wp_unslash( $_POST['id'] ?? 0 ) );
	if ( ! $id ) {
		wp_send_json_error( 'bad_request', 400 );
	}
	$before = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id=%d', $t['contracts'], $id ), ARRAY_A );
	if ( ! $before ) {
		wp_send_json_error( 'not_found', 404 );
	}
	$wpdb->update(
		$t['contracts'],
		array(
			'status'     => 'active',
			'updated_at' => current_time( 'mysql' ),
		),
		array( 'id' => $id ),
		array( '%s', '%s' ),
		array( '%d' )
	);
	locarc_sync_availability();
	$after = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id=%d', $t['contracts'], $id ), ARRAY_A );
	if ( $after ) {
		locarc_log_insert( 'contract', 'restore', $id, locarc_log_contract_label_from_row( $after ), 'Statut : ' . locarc_log_format_value( 'status', $before['status'] ?? null ) . ' → ' . locarc_log_format_value( 'status', $after['status'] ?? null ) );
	}
	wp_send_json_success( true );
}

add_action( 'wp_ajax_locarc_delete_contract_permanent', 'locarc_ajax_delete_contract_permanent' );
function locarc_ajax_delete_contract_permanent() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( 'forbidden', 403 );
	}
	check_ajax_referer( 'locarc_nonce', 'nonce' );
	global $wpdb;
	$t  = locarc_tables();
	$id = intval( wp_unslash( $_POST['id'] ?? 0 ) );
	if ( ! $id ) {
		wp_send_json_error( 'bad_request', 400 );
	}
	// Only allow hard delete for archived contracts
	$row    = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id=%d', $t['contracts'], $id ), ARRAY_A );
	$status = $row['status'] ?? '';
	if ( $status !== 'archived' ) {
		wp_send_json_error( 'only_archived', 409 );
	}
	if ( $row ) {
		$details = array();
		foreach ( locarc_log_contract_field_labels() as $field => $label ) {
			if ( ! array_key_exists( $field, $row ) ) {
				continue;
			}
			$details[] = $label . ' : ' . locarc_log_format_value( $field, $row[ $field ] );
		}
		locarc_log_insert( 'contract', 'delete', $id, locarc_log_contract_label_from_row( $row ), implode( "\n", $details ) );
	}
	$wpdb->delete( $t['contracts'], array( 'id' => $id ), array( '%d' ) );
	wp_send_json_success( true );
}

add_action( 'wp_ajax_locarc_generate_pdf', 'locarc_ajax_generate_pdf' );
function locarc_ajax_generate_pdf() {
	locarc_ajax_generate_pdf_impl();
}

/**
 * Generate a contract PDF from the dedicated AJAX action.
 */
function locarc_ajax_generate_pdf_impl() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( 'forbidden', 403 );
	}
	check_ajax_referer( 'locarc_nonce', 'nonce' );
	global $wpdb;
	$t  = locarc_tables();
	$id = intval( wp_unslash( $_POST['id'] ?? 0 ) );
	if ( ! $id ) {
		wp_send_json_error( 'bad_request', 400 );
	}

	// Refresh denormalized equipment data from inventory before generating PDF,
	// so that any equipment edits since contract creation are reflected.
	$c = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id=%d', $t['contracts'], $id ), ARRAY_A );
	if ( $c ) {
		$refresh = array();
		if ( ! empty( $c['handle_identifier'] ) ) {
			$h = $wpdb->get_row( $wpdb->prepare( 'SELECT brand, model, size, handedness FROM %i WHERE identifier=%s', $t['handles'], $c['handle_identifier'] ), ARRAY_A );
			if ( $h ) {
				$refresh['handle_brand']      = $h['brand'] ?? '';
				$refresh['handle_model']      = $h['model'] ?? '';
				$refresh['handle_size']       = ( $h['size'] !== null && $h['size'] !== '' ) ? intval( $h['size'] ) : null;
				$refresh['handle_handedness'] = $h['handedness'] ?? '';
			}
		}
		if ( ! empty( $c['branches_identifier'] ) ) {
			$b = $wpdb->get_row( $wpdb->prepare( 'SELECT brand, model, size, power FROM %i WHERE identifier=%s', $t['branches'], $c['branches_identifier'] ), ARRAY_A );
			if ( $b ) {
				$refresh['branches_brand'] = $b['brand'] ?? '';
				$refresh['branches_model'] = $b['model'] ?? '';
				$refresh['branches_size']  = ( $b['size'] !== null && $b['size'] !== '' ) ? intval( $b['size'] ) : null;
				$refresh['branches_power'] = ( $b['power'] !== null && $b['power'] !== '' ) ? intval( $b['power'] ) : null;
			}
		}
		if ( ! empty( $c['sight_identifier'] ) ) {
			$s = $wpdb->get_row( $wpdb->prepare( 'SELECT brand, model, handedness FROM %i WHERE identifier=%s', $t['sights'], $c['sight_identifier'] ), ARRAY_A );
			if ( $s ) {
				$refresh['sight_brand']      = $s['brand'] ?? '';
				$refresh['sight_model']      = $s['model'] ?? '';
				$refresh['sight_handedness'] = $s['handedness'] ?? '';
			}
		}
		if ( ! empty( $c['stabilization_identifier'] ) ) {
			$stab = $wpdb->get_row( $wpdb->prepare( 'SELECT brand, model FROM %i WHERE identifier=%s', $t['stabilizations'], $c['stabilization_identifier'] ), ARRAY_A );
			if ( $stab ) {
				$refresh['stabilization_brand'] = $stab['brand'] ?? '';
				$refresh['stabilization_model'] = $stab['model'] ?? '';
			}
		}
		if ( ! empty( $c['init_bow_identifier'] ) ) {
			$ib = $wpdb->get_row( $wpdb->prepare( 'SELECT brand, model, size, power, handedness FROM %i WHERE identifier=%s', $t['init_bows'], $c['init_bow_identifier'] ), ARRAY_A );
			if ( $ib ) {
				$refresh['init_bow_brand']      = $ib['brand'] ?? '';
				$refresh['init_bow_model']      = $ib['model'] ?? '';
				$refresh['init_bow_size']       = ( $ib['size'] !== null ) ? intval( $ib['size'] ) : null;
				$refresh['init_bow_power']      = ( $ib['power'] !== null ) ? intval( $ib['power'] ) : null;
				$refresh['init_bow_handedness'] = $ib['handedness'] ?? '';
			}
		}
		if ( ! empty( $refresh ) ) {
			$wpdb->update( $t['contracts'], $refresh, array( 'id' => $id ) );
		}
	}

	$path = locarc_generate_contract_pdf_for_ajax( $id );
	if ( is_wp_error( $path ) ) {
		wp_send_json_error( $path->get_error_message(), 500 );
	}

	$contract = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id=%d', $t['contracts'], $id ), ARRAY_A );
	$pdf_url  = $contract ? locarc_get_contract_pdf_url( $contract ) : '';

	wp_send_json_success(
		array(
			'path'    => $path,
			'pdf_url' => $pdf_url,
		)
	);
}

add_action( 'wp_ajax_locarc_autocomplete', 'locarc_ajax_autocomplete' );
function locarc_ajax_autocomplete() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( 'forbidden', 403 );
	}
	check_ajax_referer( 'locarc_nonce', 'nonce' );
	global $wpdb;
	$t    = locarc_tables();
	$kind = sanitize_key( wp_unslash( $_GET['kind'] ?? '' ) );
	$term = sanitize_text_field( wp_unslash( $_GET['term'] ?? '' ) );
	if ( ! in_array( $kind, array( 'branches', 'handles', 'sights', 'stabilizations', 'init_bows', 'members', 'members_by_firstname' ), true ) ) {
		wp_send_json_error( 'bad_kind', 400 );
	}
	// members_by_firstname uses the same table as members
	$table = ( $kind === 'members_by_firstname' ) ? $t['members'] : $t[ $kind ];

	if ( $kind === 'members' ) {
		// Autocomplete licence or name.
		// IMPORTANT: search over ALL members, not only those who currently have a contract.
		// Flexible searches supported:
		// - licence prefix
		// - first/last contains
		// - "first last" / "last first" contains
		// - multi-token terms (e.g. "amb gos" matches first/last in any order)
		// Prefer locarc_members (imported licences) but fall back to WordPress users (broader source).

		$out  = array();
		$seen = array();

		$like_contains = '%' . $wpdb->esc_like( $term ) . '%';
		$like_prefix   = $term . '%';
		$tokens        = preg_split( '/\s+/', trim( $term ) );
		$tokens        = array_values( array_filter( array_map( 'trim', $tokens ) ) );

		// 1) locarc_members table (preferred).
		// Static SQL with ( %s = '' OR cond ) pattern to avoid dynamic WHERE arrays.
		// Up to 3 tokens supported; extra tokens are ignored.
		$tokens = array_slice( $tokens, 0, 3 );
		$tl     = array(); // LIKE patterns per token (empty string = no token)
		for ( $i = 0; $i < 3; $i++ ) {
			$tl[ $i ] = isset( $tokens[ $i ] ) ? '%' . $wpdb->esc_like( $tokens[ $i ] ) . '%' : '';
		}
		// Multi-token AND conditions only activate when count >= 2
		$mt0 = count( $tokens ) >= 2 ? $tl[0] : '';
		$mt1 = count( $tokens ) >= 2 ? $tl[1] : '';
		$mt2 = count( $tokens ) >= 3 ? $tl[2] : '';

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT licence, first_name, last_name, email, phone, address1, postal_code, city FROM %i
                 WHERE ( licence LIKE %s OR first_name LIKE %s OR last_name LIKE %s
                         OR CONCAT(first_name,' ',last_name) LIKE %s
                         OR CONCAT(last_name,' ',first_name) LIKE %s )
                   AND ( %s = '' OR (first_name LIKE %s OR last_name LIKE %s) )
                   AND ( %s = '' OR (first_name LIKE %s OR last_name LIKE %s) )
                   AND ( %s = '' OR (first_name LIKE %s OR last_name LIKE %s) )
                 ORDER BY last_name ASC, first_name ASC LIMIT 20",
				$table,
				$like_prefix,
				$like_contains,
				$like_contains,
				$like_contains,
				$like_contains,
				$mt0,
				$tl[0],
				$tl[0],
				$mt1,
				$tl[1],
				$tl[1],
				$mt2,
				$tl[2],
				$tl[2]
			),
			ARRAY_A
		);
		foreach ( $rows as $r ) {
			$lic = (string) ( $r['licence'] ?? '' );
			if ( $lic === '' ) {
				continue;
			}
			$seen[ $lic ] = true;
			$out[]        = array(
				'value'       => $lic,
				'label'       => trim( $lic . ' - ' . ( $r['first_name'] ?? '' ) . ' ' . ( $r['last_name'] ?? '' ) ),
				'licence'     => $lic,
				'first_name'  => $r['first_name'] ?? '',
				'last_name'   => $r['last_name'] ?? '',
				'email'       => $r['email'] ?? '',
				'phone'       => $r['phone'] ?? '',
				'address'     => $r['address1'] ?? '',
				'postal_code' => $r['postal_code'] ?? '',
				'city'        => $r['city'] ?? '',
			);
		}

		if ( locarc_use_wp_users_fallback() ) {
			// 2) WordPress users (fallback/broader) – static SQL, max 3 tokens, ( %s <> '' AND cond ) adds OR branches.
			// $tl[] and $tokens are already set above (same for both queries).
			$u_mt0 = count( $tokens ) >= 2 ? $tl[0] : '';
			$u_mt1 = count( $tokens ) >= 2 ? $tl[1] : '';
			$u_mt2 = count( $tokens ) >= 3 ? $tl[2] : '';

			$u_rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT u.user_login, u.display_name, um_fn.meta_value AS first_name, um_ln.meta_value AS last_name
                 FROM %i u
                 LEFT JOIN %i um_fn ON um_fn.user_id = u.ID AND um_fn.meta_key = 'first_name'
                 LEFT JOIN %i um_ln ON um_ln.user_id = u.ID AND um_ln.meta_key = 'last_name'
                 WHERE ( u.user_login LIKE %s OR u.display_name LIKE %s
                         OR um_fn.meta_value LIKE %s OR um_ln.meta_value LIKE %s
                         OR ( %s <> '' AND ( u.display_name LIKE %s OR um_fn.meta_value LIKE %s OR um_ln.meta_value LIKE %s ) )
                         OR ( %s <> '' AND ( u.display_name LIKE %s OR um_fn.meta_value LIKE %s OR um_ln.meta_value LIKE %s ) )
                         OR ( %s <> '' AND ( u.display_name LIKE %s OR um_fn.meta_value LIKE %s OR um_ln.meta_value LIKE %s ) )
                 )
                 ORDER BY u.display_name ASC LIMIT 20",
					$wpdb->users,
					$wpdb->usermeta,
					$wpdb->usermeta,
					$like_prefix,
					$like_contains,
					$like_contains,
					$like_contains,
					$u_mt0,
					$u_mt0,
					$u_mt0,
					$u_mt0,
					$u_mt1,
					$u_mt1,
					$u_mt1,
					$u_mt1,
					$u_mt2,
					$u_mt2,
					$u_mt2,
					$u_mt2
				),
				ARRAY_A
			);
			foreach ( $u_rows as $u ) {
				$lic = (string) ( $u['user_login'] ?? '' );
				if ( $lic === '' || isset( $seen[ $lic ] ) ) {
					continue;
				}
				$seen[ $lic ] = true;
				$fn           = trim( (string) ( $u['first_name'] ?? '' ) );
				$ln           = trim( (string) ( $u['last_name'] ?? '' ) );
				$dn           = (string) ( $u['display_name'] ?? '' );
				$name         = trim( $fn . ' ' . $ln );
				if ( $name === '' ) {
					$name = $dn;
				}
				$out[] = array(
					'value'      => $lic,
					'label'      => trim( $lic . ' - ' . $name ),
					'licence'    => $lic,
					'first_name' => $fn,
					'last_name'  => $ln,
				);
			}
		}

		wp_send_json_success( $out );
	} elseif ( $kind === 'members_by_firstname' ) {
		// Autocomplete by first name -> returns first_name as value and extra fields to fill last name + licence
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT licence, first_name, last_name FROM %i WHERE first_name LIKE %s OR last_name LIKE %s LIMIT 20', $table, $term . '%', $term . '%' ), ARRAY_A );
		$out  = array();
		foreach ( $rows as $r ) {
			$out[] = array(
				'value'      => $r['first_name'],
				'label'      => trim( $r['first_name'] . ' ' . $r['last_name'] . ' (' . $r['licence'] . ')' ),
				'licence'    => $r['licence'],
				'first_name' => $r['first_name'],
				'last_name'  => $r['last_name'],
			);
		}
		wp_send_json_success( $out );
	} else {

		// Allow typing identifiers without dashes (e.g. "EXW70241" matches "EX-W-7024-1").
		$term_compact        = preg_replace( '/-+/', '', $term );
		$like_prefix         = $term . '%';
		$like_prefix_compact = $term_compact . '%';

		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT identifier, is_available FROM %i WHERE (identifier LIKE %s OR REPLACE(identifier,'-','') LIKE %s) ORDER BY identifier ASC LIMIT 50", $table, $like_prefix, $like_prefix_compact ), ARRAY_A );

		$out = array();
		foreach ( $rows as $r ) {
			$ia   = (int) ( $r['is_available'] ?? 0 );
			$disp = '';
			if ( $ia === 0 ) {
				$disp = ' (indispo)';
			} elseif ( $ia === 2 ) {
				$disp = ' (FLAG)';
			} elseif ( $ia === 3 ) {
				$disp = ' (Obsolète)';
			} elseif ( $ia === 4 ) {
				$disp = ' (En Réparation)';
			} elseif ( $ia === 5 ) {
				$disp = ' (H-S)';
			}
			$out[] = array(
				'value' => $r['identifier'],
				'label' => $r['identifier'] . $disp,
			);
		}

		wp_send_json_success( $out );
	}
}

add_action( 'wp_ajax_locarc_get_by_identifier', 'locarc_ajax_get_by_identifier' );
function locarc_ajax_get_by_identifier() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( 'forbidden', 403 );
	}
	check_ajax_referer( 'locarc_nonce', 'nonce' );
	global $wpdb;
	$t          = locarc_tables();
	$get        = wp_unslash( $_GET );
	$kind       = sanitize_key( $get['kind'] ?? '' );
	$identifier = sanitize_text_field( $get['identifier'] ?? '' );
	if ( ! $identifier || ! in_array( $kind, array( 'branches', 'handles', 'sights', 'stabilizations', 'init_bows' ), true ) ) {
		wp_send_json_error( 'bad_request', 400 );
	}
	$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE identifier=%s', $t[ $kind ], $identifier ), ARRAY_A );
	if ( ! $row ) {
		// Fallback: accept identifier without dashes
		$compact = preg_replace( '/-+/', '', $identifier );
		if ( $compact !== $identifier ) {
			$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM %i WHERE REPLACE(identifier,'-','')=%s", $t[ $kind ], $compact ), ARRAY_A );
		}
	}
	if ( ! $row ) {
		wp_send_json_error( 'not_found', 404 );
	}
	$assigned = locarc_is_equipment_assigned( $identifier, $kind );
	if ( $assigned ) {
		$row['_assigned_to'] = $assigned;
	}
	wp_send_json_success( $row );
}

add_action( 'wp_ajax_locarc_get_member_by_licence', 'locarc_ajax_get_member_by_licence' );
function locarc_ajax_get_member_by_licence() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( 'forbidden', 403 );
	}
	check_ajax_referer( 'locarc_nonce', 'nonce' );
	global $wpdb;
	$t       = locarc_tables();
	$get     = wp_unslash( $_GET );
	$licence = sanitize_text_field( $get['licence'] ?? '' );
	if ( $licence === '' ) {
		wp_send_json_error( 'bad_request', 400 );
	}
	$m = $wpdb->get_row( $wpdb->prepare( 'SELECT licence, first_name, last_name, email, phone, address1, postal_code, city FROM %i WHERE licence=%s', $t['members'], $licence ), ARRAY_A );
	if ( $m ) {
		wp_send_json_success(
			array(
				'licence'     => $m['licence'] ?? $licence,
				'first_name'  => $m['first_name'] ?? '',
				'last_name'   => $m['last_name'] ?? '',
				'email'       => $m['email'] ?? '',
				'phone'       => $m['phone'] ?? '',
				'address'     => $m['address1'] ?? '',
				'postal_code' => $m['postal_code'] ?? '',
				'city'        => $m['city'] ?? '',
			)
		);
	}
	// fallback: WordPress user
	$u = get_user_by( 'login', $licence );
	if ( $u ) {
		$fn = trim( (string) get_user_meta( $u->ID, 'first_name', true ) );
		$ln = trim( (string) get_user_meta( $u->ID, 'last_name', true ) );
		wp_send_json_success(
			array(
				'licence'     => $licence,
				'first_name'  => $fn,
				'last_name'   => $ln,
				'email'       => $u->user_email ?? '',
				'phone'       => '',
				'address'     => '',
				'postal_code' => '',
				'city'        => '',
			)
		);
	}
	wp_send_json_error( 'not_found', 404 );
}

add_action( 'wp_ajax_locarc_save_contract', 'locarc_ajax_save_contract' );

function locarc_ajax_save_contract() {
	locarc_ajax_save_contract_impl();
}

/**
 * Generate a PDF during an AJAX request without corrupting the JSON response.
 *
 * @param int $contract_id Contract ID.
 * @return string|WP_Error
 */
function locarc_generate_contract_pdf_for_ajax( $contract_id ) {
	ob_start();
	try {
		return locarc_generate_contract_pdf( $contract_id );
	} catch ( Throwable $error ) {
		return new WP_Error( 'pdf_generation_failed', 'Impossible de générer le fichier PDF.' );
	} finally {
		ob_end_clean();
	}
}

function locarc_ajax_save_contract_impl() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( 'forbidden', 403 );
	}
	check_ajax_referer( 'locarc_nonce', 'nonce' );
	global $wpdb;
	$t    = locarc_tables();
	$post = wp_unslash( $_POST );

	$id          = intval( $post['id'] ?? 0 );
	$licence     = sanitize_text_field( $post['licence'] ?? '' );
	$type        = sanitize_key( $post['contract_type'] ?? 'complet' );
	$start       = sanitize_text_field( $post['start_date'] ?? wp_date( 'Y-m-d' ) );
	$end         = sanitize_text_field( $post['end_date'] ?? wp_date( 'Y-m-d', strtotime( '+1 year', strtotime( $start ) ) ) );
	$handle_id   = sanitize_text_field( $post['handle_identifier'] ?? '' );
	$branches_id = sanitize_text_field( $post['branches_identifier'] ?? '' );
	// Equipment characteristics (optional; used for 'Prêt' or cached display/PDF)
	$handle_brand      = sanitize_text_field( $post['handle_brand'] ?? '' );
	$handle_model      = sanitize_text_field( $post['handle_model'] ?? '' );
	$handle_size       = ( $post['handle_size'] ?? '' ) === '' ? null : intval( $post['handle_size'] );
	$handle_handedness = sanitize_text_field( $post['handle_handedness'] ?? '' );

	$branches_brand      = sanitize_text_field( $post['branches_brand'] ?? '' );
	$branches_model      = sanitize_text_field( $post['branches_model'] ?? '' );
	$branches_size       = ( $post['branches_size'] ?? '' ) === '' ? null : intval( $post['branches_size'] );
	$branches_power      = ( $post['branches_power'] ?? '' ) === '' ? null : intval( $post['branches_power'] );
	$sight_id            = sanitize_text_field( $post['sight_identifier'] ?? '' );
	$sight_brand         = sanitize_text_field( $post['sight_brand'] ?? '' );
	$sight_model         = sanitize_text_field( $post['sight_model'] ?? '' );
	$sight_handedness    = sanitize_text_field( $post['sight_handedness'] ?? '' );
	$stabilization_id    = sanitize_text_field( $post['stabilization_identifier'] ?? '' );
	$stabilization_brand = sanitize_text_field( $post['stabilization_brand'] ?? '' );
	$stabilization_model = sanitize_text_field( $post['stabilization_model'] ?? '' );
	$init_bow_id         = sanitize_text_field( $post['init_bow_identifier'] ?? '' );
	$init_bow_brand      = sanitize_text_field( $post['init_bow_brand'] ?? '' );
	$init_bow_model      = sanitize_text_field( $post['init_bow_model'] ?? '' );
	$init_bow_size       = ( $post['init_bow_size'] ?? '' ) === '' ? null : intval( $post['init_bow_size'] );
	$init_bow_power      = ( $post['init_bow_power'] ?? '' ) === '' ? null : intval( $post['init_bow_power'] );
	$init_bow_handedness = sanitize_text_field( $post['init_bow_handedness'] ?? '' );
	$payment_method      = sanitize_key( $post['payment_method'] ?? '' );
	if ( ! in_array( $payment_method, array( '', 'cheque', 'carte_bancaire', 'helloasso', 'especes' ), true ) ) {
		$payment_method = '';
	}
	$caution_amount_raw = $post['caution_amount'] ?? '';
	$caution_amount     = ( $caution_amount_raw === '' ? null : floatval( str_replace( ',', '.', sanitize_text_field( $caution_amount_raw ) ) ) );
	$payment_due_1      = sanitize_text_field( $post['payment_due_1'] ?? '' );
	$payment_due_2      = sanitize_text_field( $post['payment_due_2'] ?? '' );
	$payment_due_3      = sanitize_text_field( $post['payment_due_3'] ?? '' );
	$payment_due_4      = sanitize_text_field( $post['payment_due_4'] ?? '' );
	if ( $payment_method !== 'cheque' ) {
		$payment_due_1 = $payment_due_2 = $payment_due_3 = $payment_due_4 = '';
	}

	$dates = locarc_validate_contract_dates( $start, $end, array( $payment_due_1, $payment_due_2, $payment_due_3, $payment_due_4 ) );
	if ( is_wp_error( $dates ) ) {
		wp_send_json_error( array( 'message' => $dates->get_error_message() ) );
	}
	$start = $dates['start'];
	$end   = $dates['end'];
	[$payment_due_1, $payment_due_2, $payment_due_3, $payment_due_4] = array_pad( $dates['payment_dues'], 4, '' );

	$paid             = intval( $post['is_paid'] ?? 0 ) ? 1 : 0;
	$custom_price_raw = $post['custom_price'] ?? '';
	$custom_price     = ( $custom_price_raw === '' ? null : floatval( str_replace( ',', '.', sanitize_text_field( $custom_price_raw ) ) ) );

	if ( $licence === '' || $type === '' ) {
		wp_send_json_error( array( 'message' => 'Champs obligatoires manquants (licence ou type).' ) );
	}
	if ( get_option( 'locarc_enable_sights', 0 ) && get_option( 'locarc_sight_required', 0 ) && $sight_id === '' ) {
		wp_send_json_error( array( 'message' => 'Viseur obligatoire sur ce contrat.' ) );
	}
	if ( get_option( 'locarc_enable_stabilizations', 0 ) && get_option( 'locarc_stabilization_required', 0 ) && $stabilization_id === '' ) {
		wp_send_json_error( array( 'message' => 'Stabilisation obligatoire sur ce contrat.' ) );
	}

	if ( $type === 'personnalise' ) {
		if ( $custom_price === null || $custom_price < 0 ) {
			wp_send_json_error( array( 'message' => 'Montant requis pour un contrat personnalisé.' ) );
		}
	} else {
		$custom_price = null;
	}

	// 'Prêt' contracts do not have a paid status and must not carry payment info.
	if ( $type === 'pret' ) {
		$paid           = 0;
		$custom_price   = null;
		$payment_method = '';
		$caution_amount = null;
		$payment_due_1  = $payment_due_2 = $payment_due_3 = $payment_due_4 = '';
	} elseif ( $caution_amount === null ) {
		$caution_amount = ( 'branches' === $type ) ? 200.0 : 400.0;
	}

	// check equipment already assigned
	// On edit, only re-validate a piece of equipment if the identifier actually changes.
	// This avoids false AJAX errors when the current contract already carries a duplicated
	// assignment created earlier and the admin only edits the other equipment / metadata.
	$current_contract = null;
	if ( $id > 0 ) {
		$current_contract = $wpdb->get_row( $wpdb->prepare( 'SELECT id, handle_identifier, branches_identifier, sight_identifier, stabilization_identifier, init_bow_identifier FROM %i WHERE id=%d', $t['contracts'], $id ), ARRAY_A );
	}

	$should_check_handle        = ( $handle_id !== '' );
	$should_check_branches      = ( $branches_id !== '' );
	$should_check_sight         = ( $sight_id !== '' );
	$should_check_stabilization = ( $stabilization_id !== '' );
	$should_check_init_bow      = ( $init_bow_id !== '' );
	if ( $current_contract ) {
		if ( ( $current_contract['handle_identifier'] ?? '' ) === $handle_id ) {
			$should_check_handle = false;
		}
		if ( ( $current_contract['branches_identifier'] ?? '' ) === $branches_id ) {
			$should_check_branches = false;
		}
		if ( ( $current_contract['sight_identifier'] ?? '' ) === $sight_id ) {
			$should_check_sight = false;
		}
		if ( ( $current_contract['stabilization_identifier'] ?? '' ) === $stabilization_id ) {
			$should_check_stabilization = false;
		}
		if ( ( $current_contract['init_bow_identifier'] ?? '' ) === $init_bow_id ) {
			$should_check_init_bow = false;
		}
	}

	if ( $should_check_handle ) {
		$a = locarc_is_equipment_assigned( $handle_id, 'handles', $id );
		if ( $a ) {
			wp_send_json_error( array( 'message' => 'Poignée déjà affectée à ' . ( $a['display_name'] ?? $a['licence'] ) ) );
		}
	}
	if ( $should_check_branches ) {
		$a = locarc_is_equipment_assigned( $branches_id, 'branches', $id );
		if ( $a ) {
			wp_send_json_error( array( 'message' => 'Branches déjà affectées à ' . ( $a['display_name'] ?? $a['licence'] ) ) );
		}
	}
	if ( $should_check_sight ) {
		$a = locarc_is_equipment_assigned( $sight_id, 'sights', $id );
		if ( $a ) {
			wp_send_json_error( array( 'message' => 'Viseur déjà affecté à ' . ( $a['display_name'] ?? $a['licence'] ) ) );
		}
	}
	if ( $should_check_stabilization ) {
		$a = locarc_is_equipment_assigned( $stabilization_id, 'stabilizations', $id );
		if ( $a ) {
			wp_send_json_error( array( 'message' => 'Stabilisation déjà affectée à ' . ( $a['display_name'] ?? $a['licence'] ) ) );
		}
	}
	if ( $should_check_init_bow ) {
		$a = locarc_is_equipment_assigned( $init_bow_id, 'init_bows', $id );
		if ( $a ) {
			wp_send_json_error( array( 'message' => "Arc d'initiation déjà affecté à " . ( $a['display_name'] ?? $a['licence'] ) ) );
		}
	}

	// If identifiers are provided, always pull characteristics from inventory (source of truth)
	if ( $handle_id ) {
		$h = $wpdb->get_row( $wpdb->prepare( 'SELECT brand, model, size, handedness FROM %i WHERE identifier=%s', $t['handles'], $handle_id ), ARRAY_A );
		if ( $h ) {
			$handle_brand      = $h['brand'] ?? '';
			$handle_model      = $h['model'] ?? '';
			$handle_size       = ( $h['size'] !== null && $h['size'] !== '' ) ? intval( $h['size'] ) : null;
			$handle_handedness = $h['handedness'] ?? '';
		}
	}
	if ( $branches_id ) {
		$b = $wpdb->get_row( $wpdb->prepare( 'SELECT brand, model, size, power FROM %i WHERE identifier=%s', $t['branches'], $branches_id ), ARRAY_A );
		if ( $b ) {
			$branches_brand = $b['brand'] ?? '';
			$branches_model = $b['model'] ?? '';
			$branches_size  = ( $b['size'] !== null && $b['size'] !== '' ) ? intval( $b['size'] ) : null;
			$branches_power = ( $b['power'] !== null && $b['power'] !== '' ) ? intval( $b['power'] ) : null;
		}
	}

	if ( $sight_id ) {
		$s = $wpdb->get_row( $wpdb->prepare( 'SELECT brand, model, handedness FROM %i WHERE identifier=%s', $t['sights'], $sight_id ), ARRAY_A );
		if ( $s ) {
			$sight_brand      = $s['brand'] ?? '';
			$sight_model      = $s['model'] ?? '';
			$sight_handedness = $s['handedness'] ?? ''; }
	}
	if ( $stabilization_id ) {
		$stab = $wpdb->get_row( $wpdb->prepare( 'SELECT brand, model FROM %i WHERE identifier=%s', $t['stabilizations'], $stabilization_id ), ARRAY_A );
		if ( $stab ) {
			$stabilization_brand = $stab['brand'] ?? '';
			$stabilization_model = $stab['model'] ?? ''; }
	}
	if ( $init_bow_id ) {
		$ib = $wpdb->get_row( $wpdb->prepare( 'SELECT brand, model, size, power, handedness FROM %i WHERE identifier=%s', $t['init_bows'], $init_bow_id ), ARRAY_A );
		if ( $ib ) {
			$init_bow_brand      = $ib['brand'] ?? '';
			$init_bow_model      = $ib['model'] ?? '';
			$init_bow_size       = ( $ib['size'] !== null ) ? intval( $ib['size'] ) : null;
			$init_bow_power      = ( $ib['power'] !== null ) ? intval( $ib['power'] ) : null;
			$init_bow_handedness = $ib['handedness'] ?? ''; }
	}
	if ( ! $sight_id ) {
		$sight_brand      = '';
		$sight_model      = '';
		$sight_handedness = ''; }
	if ( ! $stabilization_id ) {
		$stabilization_brand = '';
		$stabilization_model = ''; }
	if ( ! $init_bow_id ) {
		$init_bow_brand      = '';
		$init_bow_model      = '';
		$init_bow_size       = null;
		$init_bow_power      = null;
		$init_bow_handedness = ''; }

	// For non-'pret' contracts, if no identifier then clear manual characteristics
	if ( $type !== 'pret' ) {
		if ( ! $handle_id ) {
			$handle_brand      = '';
			$handle_model      = '';
			$handle_size       = null;
			$handle_handedness = ''; }
		if ( ! $branches_id ) {
			$branches_brand = '';
			$branches_model = '';
			$branches_size  = null;
			$branches_power = null; }
	}
	// Build contract number only on create
	if ( $id === 0 ) {
		$m    = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE licence=%s', $t['members'], $licence ), ARRAY_A );
		$base = locarc_build_contract_number( $m['first_name'] ?? '', $m['last_name'] ?? '', $m['dob'] ?? null );
		// Ensure uniqueness even if the same member re-signs within the same month.
		$cn = $base;
		$n  = 2;
		while ( $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE contract_number=%s', $t['contracts'], $cn ) ) ) {
			$cn = $base . '-' . $n;
			++$n;
		}
		$data             = array(
			'contract_number'          => $cn,
			'licence'                  => $licence,
			'contract_type'            => $type,
			'custom_price'             => $custom_price,
			'start_date'               => $start,
			'end_date'                 => $end,
			'handle_identifier'        => $handle_id ?: null,
			'branches_identifier'      => $branches_id ?: null,
			'handle_brand'             => $handle_brand ?: null,
			'handle_model'             => $handle_model ?: null,
			'handle_size'              => $handle_size,
			'handle_handedness'        => $handle_handedness ?: null,
			'branches_brand'           => $branches_brand ?: null,
			'branches_model'           => $branches_model ?: null,
			'branches_size'            => $branches_size,
			'branches_power'           => $branches_power,
			'sight_identifier'         => $sight_id ?: null,
			'sight_brand'              => $sight_brand ?: null,
			'sight_model'              => $sight_model ?: null,
			'sight_handedness'         => $sight_handedness ?: null,
			'stabilization_identifier' => $stabilization_id ?: null,
			'stabilization_brand'      => $stabilization_brand ?: null,
			'stabilization_model'      => $stabilization_model ?: null,
			'init_bow_identifier'      => $init_bow_id ?: null,
			'init_bow_brand'           => $init_bow_brand ?: null,
			'init_bow_model'           => $init_bow_model ?: null,
			'init_bow_size'            => $init_bow_size,
			'init_bow_power'           => $init_bow_power,
			'init_bow_handedness'      => $init_bow_handedness ?: null,
			'payment_method'           => ( $payment_method ?: null ),
			'caution_amount'           => $caution_amount,
			'payment_due_1'            => ( $payment_due_1 ?: null ),
			'payment_due_2'            => ( $payment_due_2 ?: null ),
			'payment_due_3'            => ( $payment_due_3 ?: null ),
			'payment_due_4'            => ( $payment_due_4 ?: null ),
			'is_paid'                  => $paid,
			'status'                   => 'active',
			'updated_at'               => current_time( 'mysql' ),
		);
		$contract_formats = array(
			'%s', // contract_number
			'%s', // licence
			'%s', // contract_type
			'%f', // custom_price
			'%s', // start_date
			'%s', // end_date
			'%s', // handle_identifier
			'%s', // branches_identifier
			'%s', // handle_brand
			'%s', // handle_model
			'%d', // handle_size
			'%s', // handle_handedness
			'%s', // branches_brand
			'%s', // branches_model
			'%d', // branches_size
			'%d', // branches_power
			'%s', // sight_identifier
			'%s', // sight_brand
			'%s', // sight_model
			'%s', // sight_handedness
			'%s', // stabilization_identifier
			'%s', // stabilization_brand
			'%s', // stabilization_model
			'%s', // init_bow_identifier
			'%s', // init_bow_brand
			'%s', // init_bow_model
			'%d', // init_bow_size
			'%d', // init_bow_power
			'%s', // init_bow_handedness
			'%s', // payment_method
			'%f', // caution_amount
			'%s', // payment_due_1
			'%s', // payment_due_2
			'%s', // payment_due_3
			'%s', // payment_due_4
			'%d', // is_paid
			'%s', // status
			'%s', // updated_at
		);
		$wpdb->insert( $t['contracts'], $data, $contract_formats );
		if ( $wpdb->last_error ) {
			error_log( '[locarc] DB error (insert contrat): ' . $wpdb->last_error );
			wp_send_json_error( 'Erreur interne lors de l\'enregistrement. Veuillez réessayer.', 500 );
		}
		$new_id = intval( $wpdb->insert_id );
		if ( ! $new_id ) {
			wp_send_json_error( 'Erreur base de données: insert_id vide. Vérifie le schéma de table.', 500 );
		}

		// Keep inventory availability in sync with active contracts
		locarc_sync_availability();

		$saved_contract = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id=%d', $t['contracts'], $new_id ), ARRAY_A );
		if ( $saved_contract ) {
			$summary = array();
			foreach ( locarc_log_contract_field_labels() as $field => $label ) {
				if ( ! array_key_exists( $field, $saved_contract ) ) {
					continue;
				}
				$summary[] = $label . ' : ' . locarc_log_format_value( $field, $saved_contract[ $field ] );
			}
			locarc_log_insert( 'contract', 'create', $new_id, locarc_log_contract_label_from_row( $saved_contract ), implode( "\n", $summary ) );
		}

		// Auto-génération PDF (sans envoi email à la création)
		$pdf_path  = locarc_generate_contract_pdf_for_ajax( $new_id );
		$pdf_error = is_wp_error( $pdf_path ) ? $pdf_path->get_error_message() : null;

		wp_send_json_success(
			array(
				'id'              => $new_id,
				'contract_number' => $cn,
				'pdf_generated'   => ! is_wp_error( $pdf_path ),
				'pdf_error'       => $pdf_error,
			)
		);

	} else {
		$data            = array(
			'licence'                  => $licence,
			'contract_type'            => $type,
			'custom_price'             => $custom_price,
			'start_date'               => $start,
			'end_date'                 => $end,
			'handle_identifier'        => $handle_id ?: null,
			'branches_identifier'      => $branches_id ?: null,
			'handle_brand'             => $handle_brand ?: null,
			'handle_model'             => $handle_model ?: null,
			'handle_size'              => $handle_size,
			'handle_handedness'        => $handle_handedness ?: null,
			'branches_brand'           => $branches_brand ?: null,
			'branches_model'           => $branches_model ?: null,
			'branches_size'            => $branches_size,
			'branches_power'           => $branches_power,
			'sight_identifier'         => $sight_id ?: null,
			'sight_brand'              => $sight_brand ?: null,
			'sight_model'              => $sight_model ?: null,
			'sight_handedness'         => $sight_handedness ?: null,
			'stabilization_identifier' => $stabilization_id ?: null,
			'stabilization_brand'      => $stabilization_brand ?: null,
			'stabilization_model'      => $stabilization_model ?: null,
			'init_bow_identifier'      => $init_bow_id ?: null,
			'init_bow_brand'           => $init_bow_brand ?: null,
			'init_bow_model'           => $init_bow_model ?: null,
			'init_bow_size'            => $init_bow_size,
			'init_bow_power'           => $init_bow_power,
			'init_bow_handedness'      => $init_bow_handedness ?: null,
			'payment_method'           => ( $payment_method ?: null ),
			'caution_amount'           => $caution_amount,
			'payment_due_1'            => ( $payment_due_1 ?: null ),
			'payment_due_2'            => ( $payment_due_2 ?: null ),
			'payment_due_3'            => ( $payment_due_3 ?: null ),
			'payment_due_4'            => ( $payment_due_4 ?: null ),
			'is_paid'                  => $paid,
			'updated_at'               => current_time( 'mysql' ),
		);
		$before_contract = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id=%d', $t['contracts'], $id ), ARRAY_A );
		$res             = $wpdb->update( $t['contracts'], $data, array( 'id' => $id ) );
		if ( $wpdb->last_error ) {
			error_log( '[locarc] DB error (update contrat): ' . $wpdb->last_error );
			wp_send_json_error( 'Erreur interne lors de l\'enregistrement. Veuillez réessayer.', 500 );
		}

		// Keep inventory availability in sync with active contracts
		locarc_sync_availability();

		$after_contract = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id=%d', $t['contracts'], $id ), ARRAY_A );
		if ( $before_contract && $after_contract ) {
			$changes = locarc_log_extract_changes( $before_contract, $after_contract, locarc_log_contract_field_labels() );
			if ( ! empty( $changes ) ) {
				locarc_log_insert( 'contract', 'update', $id, locarc_log_contract_label_from_row( $after_contract ), implode( "\n", $changes ) );
			}
		}

		// Régénérer le PDF après modification
		$pdf_path  = locarc_generate_contract_pdf_for_ajax( $id );
		$pdf_error = is_wp_error( $pdf_path ) ? $pdf_path->get_error_message() : null;

		wp_send_json_success(
			array(
				'id'            => $id,
				'pdf_generated' => ! is_wp_error( $pdf_path ),
				'pdf_error'     => $pdf_error,
			)
		);
	}
}

add_action( 'wp_ajax_locarc_send_contract_email', 'locarc_ajax_send_contract_email' );
function locarc_ajax_send_contract_email() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( 'forbidden', 403 );
	}
	check_ajax_referer( 'locarc_nonce', 'nonce' );
	global $wpdb;
	$t  = locarc_tables();
	$id = intval( wp_unslash( $_POST['id'] ?? 0 ) );
	if ( ! $id ) {
		wp_send_json_error( 'bad_request', 400 );
	}
	$c = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id=%d', $t['contracts'], $id ), ARRAY_A );
	if ( ! $c ) {
		wp_send_json_error( 'not_found', 404 );
	}

	// Ensure PDF exists
	$pdf_path = null;
	if ( ! empty( $c['pdf_path'] ) ) {
		$pdf_path = $c['pdf_path'];
	} else {
		$gen = locarc_generate_contract_pdf( $id );
		if ( is_wp_error( $gen ) ) {
			wp_send_json_error( 'PDF: ' . $gen->get_error_message(), 500 );
		}
		$pdf_path = $gen;
	}

	$sent = locarc_send_contract_email( $id, $pdf_path );
	if ( is_wp_error( $sent ) ) {
		wp_send_json_error( $sent->get_error_message(), 500 );
	}
	locarc_log_insert( 'contract', 'send_email', $id, locarc_log_contract_label_from_row( $c ), 'Email envoyé avec pièce jointe : ' . basename( (string) $pdf_path ) );
	wp_send_json_success( true );
}

add_action( 'wp_ajax_locarc_renew_contract', 'locarc_ajax_renew_contract' );
function locarc_ajax_renew_contract() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( 'forbidden', 403 );
	}
	check_ajax_referer( 'locarc_nonce', 'nonce' );
	global $wpdb;
	$t    = locarc_tables();
	$post = wp_unslash( $_POST );

	$id = intval( $post['id'] ?? 0 );
	if ( ! $id ) {
		wp_send_json_error( 'bad_request', 400 );
	}

	$old = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id=%d', $t['contracts'], $id ), ARRAY_A );
	if ( ! $old ) {
		wp_send_json_error( 'not_found', 404 );
	}
	if ( ( $old['status'] ?? 'active' ) !== 'active' ) {
		wp_send_json_error( array( 'message' => 'Seuls les contrats actifs peuvent être renouvelés.' ) );
	}

	$licence          = sanitize_text_field( $post['licence'] ?? ( $old['licence'] ?? '' ) );
	$type             = sanitize_key( $post['contract_type'] ?? ( $old['contract_type'] ?? 'complet' ) );
	$start            = sanitize_text_field( $post['start_date'] ?? ( $old['end_date'] ?? wp_date( 'Y-m-d' ) ) );
	$end              = sanitize_text_field( $post['end_date'] ?? wp_date( 'Y-m-d', strtotime( '+1 year', strtotime( $start ) ) ) );
	$handle_id        = sanitize_text_field( $post['handle_identifier'] ?? ( $old['handle_identifier'] ?? '' ) );
	$branches_id      = sanitize_text_field( $post['branches_identifier'] ?? ( $old['branches_identifier'] ?? '' ) );
	$sight_id         = sanitize_text_field( $post['sight_identifier'] ?? ( $old['sight_identifier'] ?? '' ) );
	$stabilization_id = sanitize_text_field( $post['stabilization_identifier'] ?? ( $old['stabilization_identifier'] ?? '' ) );

	$handle_brand      = sanitize_text_field( $post['handle_brand'] ?? ( $old['handle_brand'] ?? '' ) );
	$handle_model      = sanitize_text_field( $post['handle_model'] ?? ( $old['handle_model'] ?? '' ) );
	$handle_size       = ( $post['handle_size'] ?? '' ) === '' ? ( ( $old['handle_size'] ?? '' ) === '' ? null : intval( $old['handle_size'] ) ) : intval( $post['handle_size'] );
	$handle_handedness = sanitize_text_field( $post['handle_handedness'] ?? ( $old['handle_handedness'] ?? '' ) );

	$branches_brand      = sanitize_text_field( $post['branches_brand'] ?? ( $old['branches_brand'] ?? '' ) );
	$branches_model      = sanitize_text_field( $post['branches_model'] ?? ( $old['branches_model'] ?? '' ) );
	$branches_size       = ( $post['branches_size'] ?? '' ) === '' ? ( ( $old['branches_size'] ?? '' ) === '' ? null : intval( $old['branches_size'] ) ) : intval( $post['branches_size'] );
	$branches_power      = ( $post['branches_power'] ?? '' ) === '' ? ( ( $old['branches_power'] ?? '' ) === '' ? null : intval( $old['branches_power'] ) ) : intval( $post['branches_power'] );
	$sight_brand         = sanitize_text_field( $post['sight_brand'] ?? ( $old['sight_brand'] ?? '' ) );
	$sight_model         = sanitize_text_field( $post['sight_model'] ?? ( $old['sight_model'] ?? '' ) );
	$sight_handedness    = sanitize_text_field( $post['sight_handedness'] ?? ( $old['sight_handedness'] ?? '' ) );
	$stabilization_brand = sanitize_text_field( $post['stabilization_brand'] ?? ( $old['stabilization_brand'] ?? '' ) );
	$stabilization_model = sanitize_text_field( $post['stabilization_model'] ?? ( $old['stabilization_model'] ?? '' ) );

	$payment_method = sanitize_key( $post['payment_method'] ?? ( $old['payment_method'] ?? '' ) );
	if ( ! in_array( $payment_method, array( '', 'cheque', 'carte_bancaire', 'helloasso', 'especes' ), true ) ) {
		$payment_method = '';
	}
	$caution_amount_raw = $post['caution_amount'] ?? ( $old['caution_amount'] ?? '' );
	$caution_amount     = ( $caution_amount_raw === '' || $caution_amount_raw === null ? null : floatval( str_replace( ',', '.', sanitize_text_field( (string) $caution_amount_raw ) ) ) );
	$payment_due_1      = sanitize_text_field( $post['payment_due_1'] ?? ( $old['payment_due_1'] ?? '' ) );
	$payment_due_2      = sanitize_text_field( $post['payment_due_2'] ?? ( $old['payment_due_2'] ?? '' ) );
	$payment_due_3      = sanitize_text_field( $post['payment_due_3'] ?? ( $old['payment_due_3'] ?? '' ) );
	$payment_due_4      = sanitize_text_field( $post['payment_due_4'] ?? ( $old['payment_due_4'] ?? '' ) );
	if ( $payment_method !== 'cheque' ) {
		$payment_due_1 = $payment_due_2 = $payment_due_3 = $payment_due_4 = '';
	}

	$dates = locarc_validate_contract_dates( $start, $end, array( $payment_due_1, $payment_due_2, $payment_due_3, $payment_due_4 ) );
	if ( is_wp_error( $dates ) ) {
		wp_send_json_error( array( 'message' => $dates->get_error_message() ) );
	}
	$start = $dates['start'];
	$end   = $dates['end'];
	[$payment_due_1, $payment_due_2, $payment_due_3, $payment_due_4] = array_pad( $dates['payment_dues'], 4, '' );

	$paid             = intval( $post['is_paid'] ?? 0 ) ? 1 : 0;
	$custom_price_raw = $post['custom_price'] ?? ( $old['custom_price'] ?? '' );
	$custom_price     = ( $custom_price_raw === '' || $custom_price_raw === null ? null : floatval( str_replace( ',', '.', sanitize_text_field( (string) $custom_price_raw ) ) ) );

	if ( $licence === '' || $type === '' ) {
		wp_send_json_error( array( 'message' => 'Champs obligatoires manquants (licence ou type).' ) );
	}
	if ( get_option( 'locarc_enable_sights', 0 ) && get_option( 'locarc_sight_required', 0 ) && $sight_id === '' ) {
		wp_send_json_error( array( 'message' => 'Viseur obligatoire sur ce contrat.' ) );
	}
	if ( get_option( 'locarc_enable_stabilizations', 0 ) && get_option( 'locarc_stabilization_required', 0 ) && $stabilization_id === '' ) {
		wp_send_json_error( array( 'message' => 'Stabilisation obligatoire sur ce contrat.' ) );
	}

	if ( $type === 'personnalise' ) {
		if ( $custom_price === null || $custom_price < 0 ) {
			wp_send_json_error( array( 'message' => 'Montant requis pour un contrat personnalisé.' ) );
		}
	} else {
		$custom_price = null;
	}

	if ( $type === 'pret' ) {
		$paid           = 0;
		$custom_price   = null;
		$payment_method = '';
		$caution_amount = null;
		$payment_due_1  = $payment_due_2 = $payment_due_3 = $payment_due_4 = '';
	} elseif ( null === $caution_amount ) {
		$caution_amount = ( $type === 'branches' ) ? 200.0 : 400.0;
	}

	// On renewal, the current active contract is the one being archived, so it
	// must not block reusing the same equipment in the new contract.
	if ( $sight_id ) {
		$a = locarc_is_equipment_assigned( $sight_id, 'sights', $id );
		if ( $a ) {
			wp_send_json_error( array( 'message' => 'Viseur déjà affecté à ' . ( $a['display_name'] ?? $a['licence'] ) ) );
		}
	}
	if ( $stabilization_id ) {
		$a = locarc_is_equipment_assigned( $stabilization_id, 'stabilizations', $id );
		if ( $a ) {
			wp_send_json_error( array( 'message' => 'Stabilisation déjà affectée à ' . ( $a['display_name'] ?? $a['licence'] ) ) );
		}
	}

	if ( $handle_id ) {
		$a = locarc_is_equipment_assigned( $handle_id, 'handles', $id );
		if ( $a ) {
			wp_send_json_error( array( 'message' => 'Poignée déjà affectée à ' . ( $a['display_name'] ?? $a['licence'] ) ) );
		}
	}
	if ( $branches_id ) {
		$a = locarc_is_equipment_assigned( $branches_id, 'branches', $id );
		if ( $a ) {
			wp_send_json_error( array( 'message' => 'Branches déjà affectées à ' . ( $a['display_name'] ?? $a['licence'] ) ) );
		}
	}

	if ( $handle_id ) {
		$h = $wpdb->get_row( $wpdb->prepare( 'SELECT brand, model, size, handedness FROM %i WHERE identifier=%s', $t['handles'], $handle_id ), ARRAY_A );
		if ( $h ) {
			$handle_brand      = $h['brand'] ?? '';
			$handle_model      = $h['model'] ?? '';
			$handle_size       = ( $h['size'] !== null && $h['size'] !== '' ) ? intval( $h['size'] ) : null;
			$handle_handedness = $h['handedness'] ?? '';
		}
	}
	if ( $branches_id ) {
		$b = $wpdb->get_row( $wpdb->prepare( 'SELECT brand, model, size, power FROM %i WHERE identifier=%s', $t['branches'], $branches_id ), ARRAY_A );
		if ( $b ) {
			$branches_brand = $b['brand'] ?? '';
			$branches_model = $b['model'] ?? '';
			$branches_size  = ( $b['size'] !== null && $b['size'] !== '' ) ? intval( $b['size'] ) : null;
			$branches_power = ( $b['power'] !== null && $b['power'] !== '' ) ? intval( $b['power'] ) : null;
		}
	}
	if ( $sight_id ) {
		$s = $wpdb->get_row( $wpdb->prepare( 'SELECT brand, model, handedness FROM %i WHERE identifier=%s', $t['sights'], $sight_id ), ARRAY_A );
		if ( $s ) {
			$sight_brand      = $s['brand'] ?? '';
			$sight_model      = $s['model'] ?? '';
			$sight_handedness = $s['handedness'] ?? ''; }
	}
	if ( $stabilization_id ) {
		$stab = $wpdb->get_row( $wpdb->prepare( 'SELECT brand, model FROM %i WHERE identifier=%s', $t['stabilizations'], $stabilization_id ), ARRAY_A );
		if ( $stab ) {
			$stabilization_brand = $stab['brand'] ?? '';
			$stabilization_model = $stab['model'] ?? ''; }
	}
	if ( ! $sight_id ) {
		$sight_brand      = '';
		$sight_model      = '';
		$sight_handedness = ''; }
	if ( ! $stabilization_id ) {
		$stabilization_brand = '';
		$stabilization_model = ''; }

	if ( 'pret' !== $type ) {
		if ( ! $handle_id ) {
			$handle_brand      = '';
			$handle_model      = '';
			$handle_size       = null;
			$handle_handedness = ''; }
		if ( ! $branches_id ) {
			$branches_brand = '';
			$branches_model = '';
			$branches_size  = null;
			$branches_power = null; }
	}

	$m    = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE licence=%s', $t['members'], $licence ), ARRAY_A );
	$base = locarc_build_contract_number( $m['first_name'] ?? '', $m['last_name'] ?? '', $m['dob'] ?? null );
	$cn   = $base;
	$n    = 2;
	while ( $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE contract_number=%s', $t['contracts'], $cn ) ) ) {
		$cn = $base . '-' . $n;
		++$n;
	}

	$wpdb->query( 'START TRANSACTION' );

	$archive_ok = $wpdb->update(
		$t['contracts'],
		array(
			'status'     => 'archived',
			'updated_at' => current_time( 'mysql' ),
		),
		array( 'id' => $id ),
		array( '%s', '%s' ),
		array( '%d' )
	);
	if ( $archive_ok === false ) {
		$wpdb->query( 'ROLLBACK' );
		error_log( '[locarc] DB error (archive ancien contrat): ' . $wpdb->last_error );
		wp_send_json_error( 'Erreur interne lors de l\'archivage. Veuillez réessayer.', 500 );
	}

	$data      = array(
		'contract_number'          => $cn,
		'licence'                  => $licence,
		'contract_type'            => $type,
		'custom_price'             => $custom_price,
		'start_date'               => $start,
		'end_date'                 => $end,
		'handle_identifier'        => $handle_id ?: null,
		'branches_identifier'      => $branches_id ?: null,
		'handle_brand'             => $handle_brand ?: null,
		'handle_model'             => $handle_model ?: null,
		'handle_size'              => $handle_size,
		'handle_handedness'        => $handle_handedness ?: null,
		'branches_brand'           => $branches_brand ?: null,
		'branches_model'           => $branches_model ?: null,
		'branches_size'            => $branches_size,
		'branches_power'           => $branches_power,
		'sight_identifier'         => $sight_id ?: null,
		'sight_brand'              => $sight_brand ?: null,
		'sight_model'              => $sight_model ?: null,
		'sight_handedness'         => $sight_handedness ?: null,
		'stabilization_identifier' => $stabilization_id ?: null,
		'stabilization_brand'      => $stabilization_brand ?: null,
		'stabilization_model'      => $stabilization_model ?: null,
		'payment_method'           => ( $payment_method ?: null ),
		'caution_amount'           => $caution_amount,
		'payment_due_1'            => ( $payment_due_1 ?: null ),
		'payment_due_2'            => ( $payment_due_2 ?: null ),
		'payment_due_3'            => ( $payment_due_3 ?: null ),
		'payment_due_4'            => ( $payment_due_4 ?: null ),
		'is_paid'                  => $paid,
		'status'                   => 'active',
		'updated_at'               => current_time( 'mysql' ),
	);
	$insert_ok = $wpdb->insert( $t['contracts'], $data );
	if ( ! $insert_ok ) {
		$wpdb->query( 'ROLLBACK' );
		error_log( '[locarc] DB error (création renouvellement): ' . $wpdb->last_error );
		wp_send_json_error( 'Erreur interne lors du renouvellement. Veuillez réessayer.', 500 );
	}
	$new_id = intval( $wpdb->insert_id );

	$wpdb->query( 'COMMIT' );

	locarc_sync_availability();

	$new_contract      = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id=%d', $t['contracts'], $new_id ), ARRAY_A );
	$archived_contract = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id=%d', $t['contracts'], $id ), ARRAY_A );
	if ( $archived_contract ) {
		locarc_log_insert( 'contract', 'archive', $id, locarc_log_contract_label_from_row( $archived_contract ), 'Contrat archivé suite au renouvellement vers ' . ( $new_contract['contract_number'] ?? ( '#' . $new_id ) ) );
	}
	if ( $new_contract ) {
		$renew_details = array( 'Renouvellement de : ' . ( $old['contract_number'] ?? ( '#' . $id ) ) );
		foreach ( locarc_log_contract_field_labels() as $field => $label ) {
			if ( ! array_key_exists( $field, $new_contract ) ) {
				continue;
			}
			$renew_details[] = $label . ' : ' . locarc_log_format_value( $field, $new_contract[ $field ] );
		}
		locarc_log_insert( 'contract', 'renew', $new_id, locarc_log_contract_label_from_row( $new_contract ), implode( "\n", $renew_details ) );
	}

	$pdf_path  = locarc_generate_contract_pdf( $new_id );
	$pdf_error = is_wp_error( $pdf_path ) ? $pdf_path->get_error_message() : null;

	wp_send_json_success(
		array(
			'id'              => $new_id,
			'archived_id'     => $id,
			'contract_number' => $cn,
			'start_date'      => $start,
			'end_date'        => $end,
			'pdf_generated'   => ! is_wp_error( $pdf_path ),
			'pdf_error'       => $pdf_error,
		)
	);
}


function locarc_render_licencies() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	global $wpdb;
	$t     = locarc_tables();
	$table = $t['members']; // Table name from locarc_tables(), server-defined.
	$rows  = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i ORDER BY last_name ASC, first_name ASC', $table ), ARRAY_A );

	echo '<div class="locarc-toolbar">'
		. '<div class="locarc-toolbar-left">'
		. '<button class="button button-primary" id="locarc-add-member">Ajouter un licencié</button> '
		. '<span class="locarc-pill">' . intval( count( $rows ) ) . ' licenciés</span>'
		. '</div>'
		. '<div class="locarc-toolbar-right">'
		. '<input type="search" class="locarc-filter-input" data-table="locarc-members-table" placeholder="Filtrer (texte libre)" />'
		. '</div>'
		. '</div>';

	echo '<table id="locarc-members-table" class="widefat striped locarc-table locarc-sortable"><thead><tr>'
		. '<th data-sort="text">Code Adhérent</th>'
		. '<th data-sort="text">Nom</th>'
		. '<th data-sort="text">Prénom</th>'
		. '<th data-sort="date">Date de naissance</th>'
		. '<th data-sort="text">Email</th>'
		. '<th data-sort="text">Téléphone</th>'
		. '<th data-sort="text">Adresse</th>'
		. '<th data-sort="text">Code postal</th>'
		. '<th data-sort="text">Ville</th>'
		. '<th data-sort="none">Actions</th>'
		. '</tr></thead><tbody>';

	foreach ( $rows as $r ) {
		echo '<tr data-id="' . esc_attr( $r['id'] ) . '">'
			. '<td><code>' . esc_html( $r['licence'] ) . '</code></td>'
			. '<td>' . esc_html( $r['last_name'] ) . '</td>'
			. '<td>' . esc_html( $r['first_name'] ) . '</td>'
			. '<td>' . esc_html( $r['dob'] ) . '</td>'
			. '<td>' . esc_html( $r['email'] ) . '</td>'
			. '<td>' . esc_html( $r['phone'] ) . '</td>'
			. '<td>' . esc_html( $r['address1'] ) . '</td>'
			. '<td>' . esc_html( $r['postal_code'] ) . '</td>'
			. '<td>' . esc_html( $r['city'] ) . '</td>'
			. '<td><button class="button locarc-edit" data-kind="members">Modifier</button></td>'
			. '</tr>';
	}
	echo '</tbody></table>';
	locarc_modal_markup();
}

// ---------------------------------------------------------------------------
// Deactivation modal — ask the admin whether to delete plugin data.
// Fires on the plugins.php page footer; intercepts the "Désactiver" link.
/**
 * CSV export: one row per cheque instalment on paid cheque contracts.
 * Columns: Type de contrat | Adhérent | Date de paiement | Montant (€)
 * Sorted ascending by payment date.
 */
add_action(
	'admin_post_locarc_export_cheques_csv',
	function () {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Forbidden', 'gestion-location-darc' ) );
		}
		check_admin_referer( 'locarc_export_cheques_csv' );

		global $wpdb;
		$t = locarc_tables();

		// All paid cheque contracts across all statuses (active + archived).
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT c.id, c.contract_type, c.custom_price, c.licence,
                    c.payment_due_1, c.payment_due_2, c.payment_due_3, c.payment_due_4,
                    m.first_name, m.last_name
             FROM %i c
             LEFT JOIN %i m ON m.licence = c.licence
             WHERE c.payment_method = 'cheque'
               AND c.is_paid = 1",
				$t['contracts'],
				$t['members']
			),
			ARRAY_A
		);

		// Build one line per payment date per contract.
		$lines = array();
		foreach ( (array) $rows as $r ) {
			$ctype = (string) ( $r['contract_type'] ?? '' );
			$label = locarc_contract_type_label( $ctype );

			$total = ( $ctype === 'personnalise' )
			? floatval( $r['custom_price'] ?? 0 )
			: locarc_contract_price_eur( $ctype );

			$fn = trim( (string) ( $r['first_name'] ?? '' ) );
			$ln = trim( (string) ( $r['last_name'] ?? '' ) );
			if ( $fn === '' && $ln === '' ) {
				[$fn, $ln] = locarc_member_names( (string) ( $r['licence'] ?? '' ) );
			}
			$name = trim( $ln . ' ' . $fn );
			if ( $name === '' ) {
				$name = (string) ( $r['licence'] ?? '' );
			}

			// Collect non-null payment due dates.
			$dates = array();
			foreach ( array( 'payment_due_1', 'payment_due_2', 'payment_due_3', 'payment_due_4' ) as $field ) {
				if ( ! empty( $r[ $field ] ) ) {
					$dates[] = (string) $r[ $field ]; // 'YYYY-MM-DD'
				}
			}

			if ( empty( $dates ) ) {
				// No due date recorded — single row, no date.
				$lines[] = array(
					'date_sort' => '',
					'type'      => $label,
					'name'      => $name,
					'date_disp' => '',
					'amount'    => number_format( $total, 2, ',', ' ' ),
				);
				continue;
			}

			$installment = $total / count( $dates );
			foreach ( $dates as $date_raw ) {
				$ts      = strtotime( $date_raw );
				$lines[] = array(
					'date_sort' => $date_raw,
					'type'      => $label,
					'name'      => $name,
					'date_disp' => $ts ? gmdate( 'd/m/Y', $ts ) : $date_raw,
					'amount'    => number_format( $installment, 2, ',', ' ' ),
				);
			}
		}

		// Sort by payment date ascending (rows without dates go last).
		usort(
			$lines,
			function ( $a, $b ) {
				if ( $a['date_sort'] === '' && $b['date_sort'] === '' ) {
					return 0;
				}
				if ( $a['date_sort'] === '' ) {
					return 1;
				}
				if ( $b['date_sort'] === '' ) {
					return -1;
				}
				return strcmp( $a['date_sort'], $b['date_sort'] );
			}
		);

		// Stream CSV with UTF-8 BOM so Excel opens it correctly.
		$filename = 'cheques-' . gmdate( 'Y-m-d' ) . '.csv';
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		echo "\xEF\xBB\xBF"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Binary UTF-8 BOM for the CSV download.
		$out = fopen( 'php://output', 'w' );
		fputcsv( $out, array( 'Type de contrat', 'Adhérent', 'Date de paiement', 'Montant (€)' ), ';' );
		foreach ( $lines as $line ) {
			fputcsv( $out, array( $line['type'], $line['name'], $line['date_disp'], $line['amount'] ), ';' );
		}
		exit;
	}
);
