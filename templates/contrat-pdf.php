<?php
if (!defined('ABSPATH')) exit;
// Template variables are intentionally local to the included PDF template.
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound

if (!isset($data) || !is_array($data)) $data = [];

function locarc_h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function locarc_h_multiline($v){
  $clean = str_replace(['<br />', '<br/>', '<br>'], "\n", (string)$v);
  return nl2br(htmlspecialchars($clean, ENT_QUOTES, 'UTF-8'));
}
function locarc_money($v){
  if ($v === null || $v === '') return '';
  return number_format((float)$v, 2, ',', ' ') . ' €';
}

$styles = '
<style>
  @page { margin: 12mm 11mm 12mm 11mm; }
  body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #1a1a1a; line-height: 1.3; }
  .page { width: 100%; }

  .club { font-size: 7.5px; color: #555; margin-bottom: 10px; }

  .doc-title { font-size: 17px; font-weight: 700; color: #111; margin: 0 0 2px 0; }
  .doc-num   { font-size: 8.5px; color: #666; }
  .sig-box-header { border: 1px solid #bbb; border-radius: 3px; padding: 5px 8px; display: inline-block; min-width: 90px; }
  .sig-box-header .lbl { font-size: 7px; color: #888; text-transform: uppercase; letter-spacing: 0.3px; margin-bottom: 2px; }
  .sig-box-header .val { font-size: 10px; font-weight: 700; }

  .rendu-bar { background: #f0f0f0; border-left: 3px solid #333; padding: 5px 10px; margin: 8px 0; font-size: 10px; font-weight: 700; }

  .two-col { width: 100%; border-collapse: collapse; }
  .two-col .col-left  { width: 52%; vertical-align: top; padding-right: 5px; }
  .two-col .col-right { width: 48%; vertical-align: top; padding-left:  5px; }

  .block { border: 1px solid #ccc; border-radius: 3px; padding: 8px 10px; }
  .summary-block { height: 128px; }
  .block-lbl { font-size: 7px; color: #888; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 5px; border-bottom: 1px solid #eee; padding-bottom: 3px; }
  .name-big  { font-size: 11px; font-weight: 700; margin-bottom: 3px; }
  .info-line { font-size: 8.5px; color: #333; margin-bottom: 1px; }

  .ct-row  { width: 100%; border-collapse: collapse; margin-bottom: 3px; }
  .ct-row td { padding: 0; }
  .ct-lbl  { font-size: 8px; color: #666; width: 38%; vertical-align: top; }
  .ct-val  { font-size: 9px; font-weight: 400; }
  .ct-row--strong .ct-lbl,
  .ct-row--strong .ct-val { font-weight: 700; color: #111; }

  .section-hd { font-size: 8.5px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.4px; color: #444; margin: 10px 0 3px 0; border-bottom: 1px solid #ddd; padding-bottom: 2px; }

  table.grid { width: 100%; border-collapse: collapse; }
  table.grid th { background: #f4f4f4; font-size: 7.5px; font-weight: 700; color: #555; text-transform: uppercase; padding: 4px 5px; border: 1px solid #ddd; text-align: left; }
  table.grid td { font-size: 8.5px; padding: 4px 5px; border: 1px solid #eee; vertical-align: middle; }
  table.grid tr:nth-child(even) td { background: #fafafa; }

  table.pay-grid { width: 100%; border-collapse: collapse; margin-top: 4px; }
  table.pay-grid td { border: 1px solid #ddd; padding: 5px 7px; vertical-align: top; font-size: 8.5px; }
  table.pay-grid .hd { background: #f4f4f4; font-size: 7.5px; font-weight: 700; text-transform: uppercase; color: #555; }
  .due-line { font-size: 8px; line-height: 1.7; }
  .due-tag  { font-size: 7.5px; color: #888; margin-right: 3px; }

  .note { font-size: 7.5px; color: #666; border: 1px solid #e0e0e0; border-radius: 3px; padding: 5px 8px; margin-top: 8px; }

  table.sign-table { width: 100%; border-collapse: separate; border-spacing: 8px 0; margin: 10px -8px 0 -8px; }
  table.sign-table td { width: 50%; vertical-align: top; }
  .sign-box { border: 1px solid #bbb; border-radius: 3px; height: 52px; padding: 5px 8px; }
  .sign-title { font-size: 8.5px; font-weight: 700; margin-bottom: 2px; }
  .sign-sub   { font-size: 7px; color: #888; }
</style>';

/* ── Données ── */
$contract_number    = locarc_h($data['contract_number'] ?? '');
$date_signature     = locarc_h($data['date_signature'] ?? '');
$type_contrat       = locarc_h($data['type_contrat'] ?? '');
$date_debut         = locarc_h($data['date_debut'] ?? '');
$date_fin           = locarc_h($data['date_fin'] ?? '');
$date_retour        = locarc_h($data['date_retour_visible'] ?? $data['date_fin'] ?? '');
$prenom             = trim((string)($data['prenom'] ?? ''));
$nom                = trim((string)($data['nom'] ?? ''));
$prenom_nom         = locarc_h(trim($prenom . ' ' . $nom));
$licence            = locarc_h($data['licence'] ?? '');
$adresse            = trim((string)($data['adresse'] ?? ''));
$cp_ville           = trim(trim((string)($data['cp'] ?? '')) . ' ' . trim((string)($data['ville'] ?? '')));
$email              = trim((string)($data['email'] ?? ''));
$tel                = trim((string)($data['tel'] ?? ''));
$cout_total         = locarc_money($data['cout_total'] ?? '');
$payment_method_raw = trim((string)($data['payment_method'] ?? ''));
$payment_method     = locarc_h($payment_method_raw);
$is_cheque          = (mb_strtolower($payment_method_raw, 'UTF-8') === 'chèque' || strtolower($payment_method_raw) === 'cheque');
$caution_amount     = locarc_money($data['caution_amount'] ?? '');
$club_mail          = locarc_h($data['club_mail'] ?? '');
$responsable        = locarc_h($data['responsable_materiel'] ?? '');

/* ── Échéances ── */
$payment_dates_html = '';
$dates = $data['payment_due_dates'] ?? [];
if (is_array($dates) && !empty($dates)) {
  $dates      = array_values(array_filter($dates, static function($d){ return $d !== null && $d !== ''; }));
  $date_count = count($dates);
  $amounts    = [];

  if ($is_cheque && $date_count > 0 && isset($data['cout_total']) && $data['cout_total'] !== '') {
    $total_cents = (int) round(((float)$data['cout_total']) * 100);
    $base_cents  = intdiv($total_cents, $date_count);
    $rem_cents   = $total_cents - ($base_cents * $date_count);
    for ($i = 0; $i < $date_count; $i++) {
      $amounts[$i] = locarc_money(($base_cents + ($i === $date_count - 1 ? $rem_cents : 0)) / 100);
    }
  }

  foreach ($dates as $i => $d) {
    $payment_dates_html .= '<div class="due-line"><span class="due-tag">T' . ($i + 1) . '</span>' . locarc_h($d);
    if ($is_cheque && isset($amounts[$i])) $payment_dates_html .= ' &mdash; <strong>' . $amounts[$i] . '</strong>';
    $payment_dates_html .= '</div>';
  }
}

/* ── Lignes matériel ── */
$materiel_rows = '';
if (!empty($data['materiel']) && is_array($data['materiel'])) {
  foreach ($data['materiel'] as $m) {
    $materiel_rows .= '<tr>'
      . '<td>' . locarc_h($m['label']      ?? '') . '</td>'
      . '<td>' . locarc_h($m['identifiant'] ?? '-') . '</td>'
      . '<td>' . locarc_h($m['marque']     ?? '') . '</td>'
      . '<td>' . locarc_h($m['modele']     ?? '') . '</td>'
      . '<td>' . locarc_h($m['taille']     ?? '') . '</td>'
      . '<td>' . locarc_h($m['lateralite'] ?? '') . '</td>'
      . '<td>' . locarc_h($m['puissance']  ?? '') . '</td>'
      . '</tr>';
  }
}

/* ── En-tête club ── */
$club_header_raw = trim((string)($data['club_header'] ?? ''));
if ($club_header_raw === '') {
  $club_header_raw = "ACSIM - Association de loi 1901 - École Française de Tir à l'Arc\n"
                   . "Siège social : 5 avenue Jean Bouin - 92130 Issy-les-Moulineaux\n"
                   . "Correspondance et Jeux d'Arc : 6 Boulevard des Frères Voisin - 92130 Issy-les-Moulineaux";
}
$lines = explode("\n", str_replace("\r\n", "\n", $club_header_raw));
$club_header_html = '';
foreach ($lines as $i => $line) {
  $line = htmlspecialchars(trim($line), ENT_QUOTES, 'UTF-8');
  if ($line === '') continue;
  $club_header_html .= ($i === 0 ? '<strong>' . $line . '</strong>' : '<br>' . $line);
}

/* ── Helper : ligne contrat ── */
function ct_row($label, $value, $class = '') {
  if ($value === '' || $value === null) return '';
  $class_attr = trim('ct-row ' . $class);
  return '<table class="' . $class_attr . '"><tr><td class="ct-lbl">' . $label . '</td><td class="ct-val">' . $value . '</td></tr></table>';
}

$html = '<!doctype html>
<html lang="fr">
<head><meta charset="utf-8">' . $styles . '</head>
<body>
<div class="page">

  <!-- En-tête : titre + date signature -->
  <table style="width:100%;border-collapse:collapse;margin-bottom:4px;">
    <tr>
      <td style="vertical-align:top;">
        <div class="club">' . $club_header_html . '</div>
        <div class="doc-title">Contrat de location</div>
        <div class="doc-num">N°&nbsp;' . $contract_number . '</div>
      </td>
      <td style="vertical-align:top;text-align:right;padding-top:14px;">
        <div class="sig-box-header">
          <div class="lbl">Date de signature</div>
          <div class="val">' . $date_signature . '</div>
        </div>
      </td>
    </tr>
  </table>

  <!-- Barre date de fin -->
  <div class="rendu-bar">Date de fin de contrat : ' . $date_fin . '</div>

  <!-- Bloc identité (gauche) + Bloc contrat (droite) -->
  <table class="two-col">
    <tr>
      <td class="col-left">
        <div class="block summary-block">
          <div class="block-lbl">Locataire</div>
          <div class="name-big">' . $prenom_nom . '</div>
          <div class="info-line">Licence : <strong>' . $licence . '</strong></div>'
          . ($adresse  !== ''   ? '<div class="info-line">' . locarc_h_multiline($adresse) . '</div>' : '')
          . (trim($cp_ville) !== '' ? '<div class="info-line">' . locarc_h($cp_ville) . '</div>' : '')
          . ($email !== '' ? '<div class="info-line">Email : ' . locarc_h($email) . '</div>' : '')
          . ($tel   !== '' ? '<div class="info-line">Tél : '   . locarc_h($tel)   . '</div>' : '') . '
        </div>
      </td>
      <td class="col-right">
        <div class="block summary-block">
          <div class="block-lbl">Contrat</div>'
          . ct_row('Type',      $type_contrat)
          . ct_row('Début',     $date_debut)
          . ct_row('Fin',       $date_fin)
          . ct_row('Paiement',  $payment_method)
          . ct_row('Caution',   $caution_amount)
          . ct_row('Montant de la location', $cout_total, 'ct-row--strong') . '
        </div>
      </td>
    </tr>
  </table>

  <!-- Matériel loué -->
  <div class="section-hd">Matériel loué</div>
  <table class="grid">
    <thead><tr>
      <th style="width:11%;">Élément</th>
      <th style="width:18%;">Identifiant</th>
      <th style="width:14%;">Marque</th>
      <th style="width:22%;">Modèle</th>
      <th style="width:10%;">Taille</th>
      <th style="width:13%;">Latéralité</th>
      <th style="width:12%;">Puissance</th>
    </tr></thead>
    <tbody>' . $materiel_rows . '</tbody>
  </table>

  <!-- Paiement et caution -->
  <div class="section-hd">Paiement et caution</div>
  <table class="pay-grid">
    <tr>
      <td class="hd" style="width:24%;">Mode de paiement</td>
      <td style="width:26%;">' . ($payment_method !== '' ? $payment_method : '—') . '</td>
      <td class="hd" style="width:22%;">Caution</td>
      <td style="width:28%;">' . ($caution_amount !== '' ? $caution_amount : '—') . '</td>
    </tr>
    <tr>
      <td class="hd">Échéances</td>
      <td colspan="3">'
        . ($is_cheque
            ? ($payment_dates_html !== '' ? $payment_dates_html : '<span style="color:#aaa;font-size:8px;">Aucune échéance renseignée</span>')
            : '<span style="color:#aaa;font-size:8px;">Non applicable</span>')
      . '</td>
    </tr>
  </table>

  <!-- Note -->
  <div class="note">Le matériel reste la propriété du club. Il doit être restitué dans le même état. En cas de non-retour, perte ou détérioration, le club pourra encaisser tout ou partie de la caution. Pour acter le rendu, envoyer un mail à ' . $club_mail . '.</div>

  <!-- Signatures -->
  <table class="sign-table">
    <tr>
      <td>
        <div class="sign-box">
          <div class="sign-title">L\'emprunteur</div>
          <div class="sign-sub">Signature précédée de "Lu et approuvé"</div>
        </div>
      </td>
      <td>
        <div class="sign-box">
          <div class="sign-title">Le responsable du matériel</div>
          <div class="sign-sub">' . $responsable . ($club_mail !== '' ? ' &mdash; ' . $club_mail : '') . '</div>
        </div>
      </td>
    </tr>
  </table>

</div>
</body>
</html>';

return $html;
