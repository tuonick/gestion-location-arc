<?php
/*
Plugin Name:       Location d'Arc (ACSIM)
Plugin URI:        https://github.com/florianbossard/location-arc
Description:       Gestion de location d'arcs pour clubs de tir à l'arc : contrats, inventaire matériel (poignées, branches), génération PDF, envoi e-mail.
Version:           0.2.60
Requires at least: 5.9
Requires PHP:      7.4
Author:            Florian Bossard
Author URI:        https://github.com/florianbossard
License:           GPL v2 or later
License URI:       https://www.gnu.org/licenses/gpl-2.0.html
Text Domain:       location-arc
Domain Path:       /languages
*/

if (!defined('ABSPATH')) exit;

// Public shortcodes query custom plugin tables; table names come from locarc_tables().
// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter

define('LOCARC_VERSION', '0.2.60');
define('LOCARC_PLUGIN_FILE', __FILE__);
define('LOCARC_PLUGIN_DIR', plugin_dir_path(__FILE__));

require_once LOCARC_PLUGIN_DIR . 'includes/db.php';
require_once LOCARC_PLUGIN_DIR . 'includes/import.php';
require_once LOCARC_PLUGIN_DIR . 'includes/seed.php';
require_once LOCARC_PLUGIN_DIR . 'includes/pdf.php';
require_once LOCARC_PLUGIN_DIR . 'includes/admin.php';
require_once LOCARC_PLUGIN_DIR . 'includes/frontend-dashboard.php';
require_once LOCARC_PLUGIN_DIR . 'includes/cron.php';

// Public shortcodes
add_shortcode('locarc_poignees_disponibles', 'locarc_shortcode_available_handles');
add_shortcode('locarc_branches_disponibles', 'locarc_shortcode_available_branches');
add_shortcode('locarc_mon_materiel', 'locarc_shortcode_my_equipment');

function locarc_public_css_once() {
    static $done = false;
    if ($done) return;
    $done = true;

    $css = '.locarc-public-table-wrap{width:100%;overflow-x:auto;-webkit-overflow-scrolling:touch;margin:10px 0;}'
        .'.locarc-public-table{width:100%;min-width:640px;border-collapse:collapse;margin:0;}'
        .'.locarc-public-table th,.locarc-public-table td{border:1px solid rgba(0,0,0,.15);padding:8px;vertical-align:top;}'
        .'.locarc-public-table th{background:rgba(0,0,0,.04);text-align:left;}'
        .'.locarc-public-box{border:1px solid rgba(0,0,0,.12);border-radius:12px;padding:16px;background:#fff;margin:12px 0;}'
        .'.locarc-public-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:14px;}'
        .'.locarc-public-empty{padding:18px;border:1px solid rgba(0,0,0,.12);border-radius:12px;background:#fff;}'
        .'.locarc-public-actions{margin-top:16px;}';

    // Register a dummy handle to attach inline CSS
    wp_register_style('locarc-public', false);
    wp_enqueue_style('locarc-public');
    wp_add_inline_style('locarc-public', $css);
}

function locarc_shortcode_available_handles() {
    locarc_public_css_once();
    global $wpdb;
    $t = locarc_tables();
    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $rows = $wpdb->get_results(
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        "SELECT h.brand, h.model, h.size, h.handedness
         FROM {$t['handles']} h
         LEFT JOIN {$t['contracts']} c
           ON c.status='active' AND c.handle_identifier = h.identifier
         WHERE c.id IS NULL
           AND h.is_available = 1
         ORDER BY CAST(h.size AS UNSIGNED) ASC, h.handedness ASC, h.brand ASC, h.model ASC",
        ARRAY_A
    );
    if (!$rows) return '<p>Aucune poignée disponible pour le moment.</p>';

    $out = '<div class="locarc-public-table-wrap"><table class="locarc-public-table"><thead><tr>'
        .'<th>#</th><th>Marque</th><th>Modèle</th><th>Taille</th><th>Latéralité</th>'
        .'</tr></thead><tbody>';
    $i = 1;
    foreach ($rows as $r) {
        $out .= '<tr>'
            .'<td>' . intval($i++) . '</td>'
            .'<td>' . esc_html($r['brand']) . '</td>'
            .'<td>' . esc_html($r['model']) . '</td>'
            .'<td>' . esc_html($r['size']) . '</td>'
            .'<td>' . esc_html($r['handedness']) . '</td>'
            .'</tr>';
    }
    $out .= '</tbody></table></div>';
    return $out;
}

function locarc_shortcode_available_branches() {
    locarc_public_css_once();
    global $wpdb;
    $t = locarc_tables();
    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $rows = $wpdb->get_results(
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        "SELECT b.brand, b.model, b.size, b.power
         FROM {$t['branches']} b
         LEFT JOIN {$t['contracts']} c
           ON c.status='active' AND c.branches_identifier = b.identifier
         WHERE c.id IS NULL
           AND b.is_available = 1
         ORDER BY CAST(b.size AS UNSIGNED) ASC, CAST(b.power AS DECIMAL(5,2)) ASC, b.brand ASC, b.model ASC",
        ARRAY_A
    );
    if (!$rows) return '<p>Aucune paire de branches disponible pour le moment.</p>';

    $out = '<div class="locarc-public-table-wrap"><table class="locarc-public-table"><thead><tr>'
        .'<th>#</th><th>Marque</th><th>Modèle</th><th>Taille</th><th>Puissance</th>'
        .'</tr></thead><tbody>';
    $i = 1;
    foreach ($rows as $r) {
        $out .= '<tr>'
            .'<td>' . intval($i++) . '</td>'
            .'<td>' . esc_html($r['brand']) . '</td>'
            .'<td>' . esc_html($r['model']) . '</td>'
            .'<td>' . esc_html($r['size']) . '</td>'
            .'<td>' . esc_html($r['power']) . '</td>'
            .'</tr>';
    }
    $out .= '</tbody></table></div>';
    return $out;
}

register_activation_hook(__FILE__, 'locarc_activate');
register_deactivation_hook(__FILE__, 'locarc_deactivate');

function locarc_activate() {
    locarc_db_install();
    locarc_seed_if_empty();
    locarc_cron_schedule();
    update_option('locarc_version', LOCARC_VERSION);
}

function locarc_deactivate() {
    locarc_cron_unschedule();
}

// Upgrade DB schema when plugin is updated (dbDelta is safe to run).
add_action('plugins_loaded', function(){
    $v = get_option('locarc_version');
    if ($v !== LOCARC_VERSION) {
        locarc_db_install();
        update_option('locarc_version', LOCARC_VERSION);
    }
});

function locarc_shortcode_my_equipment() {
    if (!is_user_logged_in()) {
        return '<div class="locarc-public-empty">Connectez-vous pour voir votre matériel de location.</div>';
    }
    global $wpdb;
    $t = locarc_tables();
    $user = wp_get_current_user();
    $licence = (string)($user->user_login ?? '');
    if ($licence === '') {
        return '<div class="locarc-public-empty">Impossible de retrouver votre licence utilisateur.</div>';
    }

    $contract = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->prepare(
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            "SELECT * FROM {$t['contracts']} WHERE licence=%s AND status='active' ORDER BY COALESCE(updated_at, created_at) DESC, id DESC LIMIT 1",
            $licence
        ),
        ARRAY_A
    );

    if (!$contract) {
        return '<div class="locarc-public-empty">Pas de contrat en cours.</div>';
    }

    $handle = null;
    if (!empty($contract['handle_identifier'])) {
        $handle = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->prepare(
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                "SELECT identifier, brand, model, size, handedness FROM {$t['handles']} WHERE identifier=%s",
                $contract['handle_identifier']
            ),
            ARRAY_A
        );
    }
    $branches = null;
    if (!empty($contract['branches_identifier'])) {
        $branches = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->prepare(
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                "SELECT identifier, brand, model, size, power FROM {$t['branches']} WHERE identifier=%s",
                $contract['branches_identifier']
            ),
            ARRAY_A
        );
    }

    $pdf_url = !empty($contract['pdf_path']) ? locarc_get_contract_pdf_url($contract) : null;

    $out = '<div class="locarc-public-box">';
    $out .= '<h3 style="margin-top:0">Mon matériel de location</h3>';
    $out .= '<p><strong>Contrat :</strong> ' . esc_html($contract['contract_number']) . '<br>';
    $out .= '<strong>Type :</strong> ' . esc_html(locarc_contract_type_label($contract['contract_type'])) . '<br>';
    $out .= '<strong>Fin de contrat :</strong> ' . esc_html($contract['end_date']) . '</p>';
    $out .= '<div class="locarc-public-grid">';

    if ($handle) {
        $out .= '<div><h4>Poignée</h4><div class="locarc-public-table-wrap"><table class="locarc-public-table"><tbody>'
            . '<tr><th>Identifiant</th><td>' . esc_html($handle['identifier']) . '</td></tr>'
            . '<tr><th>Marque</th><td>' . esc_html($handle['brand']) . '</td></tr>'
            . '<tr><th>Modèle</th><td>' . esc_html($handle['model']) . '</td></tr>'
            . '<tr><th>Latéralité</th><td>' . esc_html($handle['handedness']) . '</td></tr>'
            . '<tr><th>Hauteur</th><td>' . esc_html($handle['size']) . '</td></tr>'
            . '</tbody></table></div></div>';
    }

    if ($branches) {
        $out .= '<div><h4>Branches</h4><div class="locarc-public-table-wrap"><table class="locarc-public-table"><tbody>'
            . '<tr><th>Identifiant</th><td>' . esc_html($branches['identifier']) . '</td></tr>'
            . '<tr><th>Marque</th><td>' . esc_html($branches['brand']) . '</td></tr>'
            . '<tr><th>Modèle</th><td>' . esc_html($branches['model']) . '</td></tr>'
            . '<tr><th>Hauteur</th><td>' . esc_html($branches['size']) . '</td></tr>'
            . '<tr><th>Puissance</th><td>' . esc_html($branches['power']) . ' #</td></tr>'
            . '</tbody></table></div></div>';
    }

    if (!$handle && !$branches) {
        $out .= '<div class="locarc-public-empty">Aucun matériel n’est actuellement affecté à votre contrat.</div>';
    }

    $out .= '</div>';
    if ($pdf_url) {
        $out .= '<div class="locarc-public-actions"><a class="button" href="' . esc_url($pdf_url) . '" target="_blank" rel="noopener">Télécharger mon contrat</a></div>';
    }
    $out .= '</div>';
    return $out;
}

// ---------------------------------------------------------------------------
// Admin notice: warn if Dompdf is not installed (PDF generation will fail).
// ---------------------------------------------------------------------------
add_action('admin_notices', function () {
    if (!current_user_can('manage_options')) return;
    $screen = get_current_screen();
    if (!$screen || strpos($screen->id, 'locarc') === false) return;

    // Check whether Dompdf is already loaded or can be found in any candidate path.
    if (class_exists('Dompdf\\Dompdf')) return;

    $candidates = [];
    $admin_path = trim((string) get_option('locarc_dompdf_autoload_path', ''));
    if ($admin_path !== '') $candidates[] = $admin_path;
    $candidates[] = trailingslashit(ABSPATH) . 'vendor/autoload.php';
    $candidates[] = trailingslashit(WP_CONTENT_DIR) . 'vendor/autoload.php';
    $candidates[] = trailingslashit(WP_CONTENT_DIR) . 'vendor/dompdf/autoload.inc.php';
    $candidates[] = trailingslashit(LOCARC_PLUGIN_DIR) . 'vendor/autoload.php';
    $candidates[] = trailingslashit(LOCARC_PLUGIN_DIR) . 'vendor/dompdf/autoload.inc.php';

    foreach ($candidates as $path) {
        if ($path !== '' && file_exists($path)) return; // found — notice not needed
    }

    echo '<div class="notice notice-warning is-dismissible">'
       . '<p><strong>Location d\'Arc :</strong> Dompdf est introuvable — la génération PDF ne fonctionnera pas.<br>'
       . 'Solution recommandée : <code>composer require dompdf/dompdf</code> à la racine WordPress, '
       . 'ou renseignez un chemin personnalisé dans <a href="' . esc_url(admin_url('admin.php?page=locarc&tab=settings')) . '">Réglages → Location d\'Arc</a>.'
       . '</p></div>';
});
