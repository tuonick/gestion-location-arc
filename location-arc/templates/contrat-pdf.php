<?php
if (!defined('ABSPATH')) exit;
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template file: variables are local to the including function scope, not global.
if (!isset($data) || !is_array($data)) $data = [];

function locarc_h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function locarc_money($v){
  if ($v === null || $v === '') return '';
  return number_format((float)$v, 2, ',', ' ') . ' €';
}

$styles = '
<style>
  @page { margin: 11mm 10mm 11mm 10mm; }
  body { font-family: DejaVu Sans, sans-serif; font-size: 9.4px; color: #111; line-height: 1.25; }
  .page { width: 100%; }
  .club { font-size: 8px; color: #444; line-height: 1.2; margin-bottom: 8px; }
  .title-row { width: 100%; border-collapse: collapse; margin-top: 6px; }
  .title-row td { vertical-align: top; }
  .title { font-size: 16px; font-weight: 700; margin: 0; }
  .subtitle { font-size: 10px; color: #444; margin-top: 2px; }
  .box { border: 1px solid #777; border-radius: 4px; padding: 6px 7px; }
  .return-box { border: 2px solid #222; padding: 8px 10px; margin: 8px 0; font-size: 11px; font-weight: 700; text-align: center; }
  .total-box { border: 2px solid #111; background: #f3f3f3; padding: 8px 10px; margin-top: 8px; text-align: center; }
  .total-box .total-label { display:block; font-size: 8px; color:#444; text-transform: uppercase; letter-spacing: 0.4px; }
  .total-box .total-value { display:block; font-size: 16px; font-weight: 700; margin-top: 3px; }
  .meta-grid { width: 100%; border-collapse: separate; border-spacing: 6px 6px; margin: 0 -6px; table-layout: fixed; }
  .meta-grid td { width: 50%; vertical-align: top; }
  .meta-cell { border: 1px solid #777; border-radius: 4px; padding: 6px 7px; }
  .label { display:block; font-size: 8px; color: #666; text-transform: uppercase; margin-bottom: 2px; }
  .value { font-size: 10px; font-weight: 700; }
  .section-title { font-size: 10px; font-weight: 700; margin: 8px 0 4px 0; text-transform: uppercase; }
  table.grid { width: 100%; border-collapse: collapse; }
  table.grid th, table.grid td { border: 1px solid #999; padding: 5px 6px; vertical-align: middle; }
  table.grid th { background: #efefef; font-size: 8.5px; }
  .note { border: 1px solid #bfbfbf; padding: 6px 7px; font-size: 8.2px; margin-top: 7px; }
  .signatures { width:100%; border-collapse: separate; border-spacing: 8px 0; margin: 8px -8px 0 -8px; }
  .signatures td { width: 50%; vertical-align: top; }
  .signbox { border: 1px solid #777; height: 56px; padding: 6px 7px; }
  .small { font-size: 8px; color: #555; }
  .tight td, .tight th { padding-top:4px; padding-bottom:4px; }
</style>';

$contract_number = locarc_h($data['contract_number'] ?? '');
$date_signature  = locarc_h($data['date_signature'] ?? '');
$type_contrat    = locarc_h($data['type_contrat'] ?? '');
$date_debut      = locarc_h($data['date_debut'] ?? '');
$date_fin        = locarc_h($data['date_fin'] ?? '');
$date_retour     = locarc_h($data['date_retour_visible'] ?? $date_fin);
$prenom = trim((string)($data['prenom'] ?? ''));
$nom = trim((string)($data['nom'] ?? ''));
$prenom_nom = locarc_h(trim($prenom . ' ' . $nom));
$licence = locarc_h($data['licence'] ?? '');
$adresse = trim((string)($data['adresse'] ?? ''));
$cp_ville = trim((string)($data['cp'] ?? '') . ' ' . (string)($data['ville'] ?? ''));
$email = trim((string)($data['email'] ?? ''));
$tel = trim((string)($data['tel'] ?? ''));
$cout_total = locarc_money($data['cout_total'] ?? '');
$payment_method_raw = trim((string)($data['payment_method'] ?? ''));
$payment_method = locarc_h($payment_method_raw);
$is_cheque_payment = (mb_strtolower($payment_method_raw, 'UTF-8') === 'chèque' || strtolower($payment_method_raw) === 'cheque');
$caution_amount = locarc_money($data['caution_amount'] ?? '');
$club_mail = locarc_h($data['club_mail'] ?? '');
$responsable = locarc_h($data['responsable_materiel'] ?? '');

$payment_dates_html = '';
$dates = $data['payment_due_dates'] ?? [];
if (is_array($dates) && !empty($dates)) {
  $dates = array_values(array_filter($dates, static function($d){ return $d !== null && $d !== ''; }));
  $date_count = count($dates);
  $installment_amounts = [];

  if ($is_cheque_payment && $date_count > 0 && isset($data['cout_total']) && $data['cout_total'] !== '') {
    $total_cents = (int) round(((float) $data['cout_total']) * 100);
    $base_cents = intdiv($total_cents, $date_count);
    $remainder_cents = $total_cents - ($base_cents * $date_count);

    for ($idx = 0; $idx < $date_count; $idx++) {
      $installment_cents = $base_cents + ($idx === ($date_count - 1) ? $remainder_cents : 0);
      $installment_amounts[$idx] = locarc_money($installment_cents / 100);
    }
  }

  $i = 1;
  foreach ($dates as $idx => $d) {
    $line = '<div><span class="label" style="display:inline;font-size:8.2px;color:#555;text-transform:none;margin:0;">T' . $i . '</span> ' . locarc_h($d);
    if ($is_cheque_payment && isset($installment_amounts[$idx])) {
      $line .= ' : <strong>' . $installment_amounts[$idx] . '</strong>';
    }
    $line .= '</div>';
    $payment_dates_html .= $line;
    $i++;
  }
}

$materiel_rows = '';
if (!empty($data['materiel']) && is_array($data['materiel'])) {
  foreach ($data['materiel'] as $m) {
    $materiel_rows .= '<tr>'
      . '<td>' . locarc_h($m['label'] ?? '') . '</td>'
      . '<td>' . locarc_h($m['identifiant'] ?? '-') . '</td>'
      . '<td>' . locarc_h($m['marque'] ?? '') . '</td>'
      . '<td>' . locarc_h($m['modele'] ?? '') . '</td>'
      . '<td>' . locarc_h($m['taille'] ?? '') . '</td>'
      . '<td>' . locarc_h($m['lateralite'] ?? '') . '</td>'
      . '<td>' . locarc_h($m['puissance'] ?? '') . '</td>'
      . '</tr>';
  }
}

// Build club header block from the editable option (plain text, first line is bold).
$club_header_raw = trim((string)($data['club_header'] ?? ''));
if ($club_header_raw === '') {
    $club_header_raw = "ACSIM - Association de loi 1901 - École Française de Tir à l'Arc\n"
                     . "Siège social : 5 avenue Jean Bouin - 92130 Issy-les-Moulineaux\n"
                     . "Correspondance et Jeux d'Arc : 6 Boulevard des Frères Voisin - 92130 Issy-les-Moulineaux";
}
$club_header_lines = explode("\n", str_replace("\r\n", "\n", $club_header_raw));
$club_header_html  = '';
foreach ($club_header_lines as $idx => $line) {
    $line = htmlspecialchars(trim($line), ENT_QUOTES, 'UTF-8');
    if ($line === '') continue;
    if ($idx === 0) {
        $club_header_html .= '<strong>' . $line . '</strong>';
    } else {
        $club_header_html .= '<br>' . $line;
    }
}

$html = '<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">' . $styles . '</head>
<body>
<div class="page">
  <div class="club">' . $club_header_html . '</div>

  <table class="title-row">
    <tr>
      <td style="width:68%;">
        <div class="title">Contrat de location</div>
        <div class="subtitle">N° ' . $contract_number . '</div>
      </td>
      <td style="width:32%;">
        <div class="box">
          <span class="label">Date de signature</span>
          <div class="value">' . $date_signature . '</div>
        </div>
      </td>
    </tr>
  </table>

  <div class="return-box">Date de rendu de l\'arc : ' . $date_retour . '</div>

  <table class="meta-grid">
    <tr>
      <td class="meta-cell">
        <span class="label">Loueur</span>
        <div class="value">' . $prenom_nom . '</div>
        N° licence : ' . $licence . '<br>
        ' . locarc_h($adresse) . '<br>
        ' . locarc_h($cp_ville) . '<br>
        ' . ($email !== '' ? ('Email : ' . locarc_h($email) . '<br>') : '') . '
        ' . ($tel !== '' ? ('Téléphone : ' . locarc_h($tel)) : '') . '
      </td>
      <td class="meta-cell">
        <span class="label">Contrat</span>
        <div><strong>Type :</strong> ' . $type_contrat . '</div>
        <div><strong>Début :</strong> ' . $date_debut . '</div>
        <div><strong>Fin :</strong> ' . $date_fin . '</div>
        <div class="total-box"><span class="total-label">Coût total de la location</span><span class="total-value">' . $cout_total . '</span></div>
      </td>
    </tr>
  </table>

  <div class="section-title">Matériel loué</div>
  <table class="grid tight">
    <thead>
      <tr>
        <th style="width:11%;">Élément</th>
        <th style="width:18%;">Identifiant</th>
        <th style="width:14%;">Marque</th>
        <th style="width:22%;">Modèle</th>
        <th style="width:10%;">Taille</th>
        <th style="width:13%;">Latéralité</th>
        <th style="width:12%;">Puissance</th>
      </tr>
    </thead>
    <tbody>' . $materiel_rows . '</tbody>
  </table>

  <div class="section-title">Paiement et caution</div>
  <table class="grid tight">
    <tr>
      <td style="width:24%;"><strong>Mode de paiement</strong></td>
      <td style="width:26%;">' . ($payment_method !== '' ? $payment_method : '-') . '<br><span class="small"><strong>Total :</strong> ' . $cout_total . '</span></td>
      <td style="width:22%;"><strong>Caution</strong></td>
      <td style="width:28%;">' . ($caution_amount !== '' ? $caution_amount : '-') . '</td>
    </tr>
    <tr>
      <td><strong>Mois d\'encaissement</strong></td>
      <td colspan="3">' . ($is_cheque_payment ? ($payment_dates_html !== '' ? $payment_dates_html : 'Aucun encaissement trimestriel renseigné') : 'Non applicable') . '</td>
    </tr>
  </table>

  <div class="note">
    Le matériel reste la propriété du club. Il doit être restitué dans le même état. En cas de non-retour, perte ou détérioration, le club pourra encaisser tout ou partie de la caution. Pour acter le rendu, envoyer un mail à ' . $club_mail . '.
  </div>

  <table class="signatures">
    <tr>
      <td>
        <div class="signbox">
          <strong>L\'emprunteur</strong><br>
          <span class="small">Signature précédée de la mention "Lu et approuvé"</span>
        </div>
      </td>
      <td>
        <div class="signbox">
          <strong>Le responsable du matériel</strong><br>
          <span class="small">' . $responsable . ' - ' . $club_mail . '</span>
        </div>
      </td>
    </tr>
  </table>
</div>
</body>
</html>';

return $html;
