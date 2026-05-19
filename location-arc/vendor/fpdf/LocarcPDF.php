<?php
/**
 * LocarcPDF — Minimal PDF generator for Location d'Arc contracts.
 * Self-contained, no external dependencies.
 * Uses PDF built-in Type1 fonts: Helvetica / Helvetica-Bold.
 * Input strings must be UTF-8; they are converted to Windows-1252 internally
 * (supports €, —, «, », etc.).
 */
if (!defined('ABSPATH')) exit;

class LocarcPDF {

    const PW = 595;  // A4 width  (points)
    const PH = 842;  // A4 height (points)
    const ML = 40;   // margin left
    const MR = 40;   // margin right
    const CW = 515;  // content width = PW - ML - MR

    // ── Color palette ────────────────────────────────────────────────────────
    const C_ACCENT  = [0.09, 0.36, 0.62];   // deep blue  #175C9E
    const C_TINT    = [0.93, 0.96, 1.00];   // very light blue (alternate rows)
    const C_WHITE   = [1.00, 1.00, 1.00];
    const C_BORDER  = [0.78, 0.78, 0.78];   // light gray borders
    const C_DARK    = [0.08, 0.08, 0.08];   // near-black text
    const C_MED     = [0.40, 0.40, 0.40];   // medium gray (secondary text)

    /** @var string PDF page content stream */
    private string $cs = '';

    // ── Internal helpers ─────────────────────────────────────────────────────

    private function py(float $y): float { return self::PH - $y; }

    /** UTF-8 → Windows-1252 + PDF string escape. */
    private function enc(string $s): string {
        if (function_exists('mb_convert_encoding')) {
            $s = mb_convert_encoding($s, 'Windows-1252', 'UTF-8');
        } elseif (function_exists('iconv')) {
            $s = (string) iconv('UTF-8', 'CP1252//TRANSLIT//IGNORE', $s);
        }
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $s);
    }

    /** Approximate text width in points. */
    private function tw(string $s, float $sz, bool $bold = false): float {
        $enc = function_exists('mb_convert_encoding')
            ? mb_convert_encoding($s, 'Windows-1252', 'UTF-8')
            : $s;
        return strlen($enc) * $sz * ($bold ? 0.56 : 0.50);
    }

    private function op(string $cmd): void { $this->cs .= $cmd . "\n"; }

    /** Format [r,g,b] as "0.xxx 0.xxx 0.xxx". */
    private function rgb(array $c): string {
        return number_format($c[0], 3, '.', '') . ' '
             . number_format($c[1], 3, '.', '') . ' '
             . number_format($c[2], 3, '.', '');
    }

    // ── Drawing primitives ───────────────────────────────────────────────────

    public function text(float $x, float $y_top, string $t, bool $bold = false, float $sz = 10, array $rgb = [0.08,0.08,0.08]): void {
        $fn  = $bold ? 'F2' : 'F1';
        $bl  = $this->py($y_top + $sz * 0.78);
        $esc = $this->enc($t);
        $this->op("BT /$fn $sz Tf {$this->rgb($rgb)} rg $x $bl Td ($esc) Tj ET 0 0 0 rg");
    }

    public function ctext(float $y_top, string $t, bool $bold = false, float $sz = 10, array $rgb = [0.08,0.08,0.08]): void {
        $tw = $this->tw($t, $sz, $bold);
        $x  = max(self::ML, (self::PW - $tw) / 2);
        $this->text($x, $y_top, $t, $bold, $sz, $rgb);
    }

    public function fillRect(float $x, float $y, float $w, float $h, array $rgb): void {
        $py = $this->py($y + $h);
        $this->op("{$this->rgb($rgb)} rg $x $py $w $h re f 0 0 0 rg");
    }

    /** Stroke a rectangle (outline only). */
    public function strokeRect(float $x, float $y, float $w, float $h, array $color = [], float $lw = 0.3): void {
        $c  = empty($color) ? self::C_BORDER : $color;
        $py = $this->py($y + $h);
        $this->op("{$this->rgb($c)} RG $lw w $x $py $w $h re S 0 0 0 RG");
    }

    /** Draw a horizontal line. */
    public function hline(float $x, float $y, float $w, array $color = [], float $lw = 0.4): void {
        $c  = empty($color) ? self::C_BORDER : $color;
        $py = $this->py($y);
        $x2 = $x + $w;
        $this->op("{$this->rgb($c)} RG $lw w $x $py m $x2 $py l S 0 0 0 RG");
    }

    /** Draw a vertical line. */
    private function vline(float $x, float $y1, float $y2, array $color = [], float $lw = 0.25): void {
        $c   = empty($color) ? self::C_BORDER : $color;
        $py1 = $this->py($y1);
        $py2 = $this->py($y2);
        $this->op("{$this->rgb($c)} RG $lw w $x $py1 m $x $py2 l S 0 0 0 RG");
    }

    // ── High-level layout components ─────────────────────────────────────────

    /**
     * Section header bar (accent color + white bold text).
     * Returns Y just below.
     */
    public function section(float $y, string $label): float {
        $h = 18;
        $this->fillRect(self::ML, $y, self::CW, $h, self::C_ACCENT);
        $this->ctext($y + 4.5, $label, true, 9.5, self::C_WHITE);
        return $y + $h;
    }

    /**
     * Info cell: white background, thin border, accent-colored label, dark bold value.
     * Returns X just after the cell.
     */
    public function cell(float $x, float $y, float $w, float $h, string $label, string $value): float {
        $this->fillRect($x, $y, $w, $h, self::C_WHITE);
        $this->strokeRect($x, $y, $w, $h, self::C_BORDER, 0.3);
        $this->text($x + 4, $y + 2.5, $label, false, 7.0, self::C_ACCENT);
        $max_chars = (int)($w / 5.2);
        if (mb_strlen($value, 'UTF-8') > $max_chars) {
            $value = mb_substr($value, 0, max(1, $max_chars - 1), 'UTF-8') . '...';
        }
        $this->text($x + 4, $y + 10.5, $value, true, 9.5, self::C_DARK);
        return $x + $w;
    }

    /**
     * Table header row (accent background, white bold text).
     * $cols: array of [width, label]
     * Returns Y just below.
     */
    public function thead(float $x, float $y, float $h, array $cols): float {
        $cx = $x;
        foreach ($cols as [$cw, $lbl]) {
            $this->fillRect($cx, $y, $cw, $h, self::C_ACCENT);
            $tw = $this->tw($lbl, 8.0, true);
            $tx = $cx + ($cw - $tw) / 2;
            $this->text($tx, $y + ($h - 8.0) / 2, $lbl, true, 8.0, self::C_WHITE);
            // white separator between columns
            if ($cx + $cw < $x + array_sum(array_column($cols, 0))) {
                $this->vline($cx + $cw, $y, $y + $h, self::C_WHITE, 0.4);
            }
            $cx += $cw;
        }
        return $y + $h;
    }

    /**
     * Table data row (alternating white / light-blue, thin borders).
     * $cells: array of [width, value, bold=false]
     * Returns Y just below.
     */
    public function trow(float $x, float $y, float $h, array $cells, bool $shaded = false): float {
        $total_w = array_sum(array_column($cells, 0));
        $this->fillRect($x, $y, $total_w, $h, $shaded ? self::C_TINT : self::C_WHITE);
        // outer border
        $this->strokeRect($x, $y, $total_w, $h, self::C_BORDER, 0.25);
        $cx = $x;
        foreach ($cells as $cell) {
            [$cw, $val] = $cell;
            $bold = $cell[2] ?? false;
            $max_chars = (int)($cw / 5.0);
            if (mb_strlen((string)$val, 'UTF-8') > $max_chars) {
                $val = mb_substr($val, 0, max(1, $max_chars - 1), 'UTF-8') . '...';
            }
            $this->text($cx + 4, $y + ($h - 8.5) * 0.45, (string)$val, (bool)$bold, 8.5, self::C_DARK);
            // column separator
            if ($cx + $cw < $x + $total_w) {
                $this->vline($cx + $cw, $y, $y + $h, self::C_BORDER, 0.25);
            }
            $cx += $cw;
        }
        return $y + $h;
    }

    // ── PDF document output ───────────────────────────────────────────────────

    public function output(): string {
        $stream = $this->cs;

        $objs = [
            1 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
            2 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>',
            3 => '<< /Length ' . strlen($stream) . " >>\nstream\n" . $stream . "\nendstream",
            4 => '<< /Type /Page /Parent 5 0 R /MediaBox [0 0 595 842]'
               . ' /Contents 3 0 R /Resources << /Font << /F1 1 0 R /F2 2 0 R >> >> >>',
            5 => '<< /Type /Pages /Kids [4 0 R] /Count 1 >>',
            6 => '<< /Type /Catalog /Pages 5 0 R >>',
        ];

        $header  = "%PDF-1.4\n";
        $body    = '';
        $offsets = [];
        foreach ($objs as $n => $data) {
            $offsets[$n] = strlen($header) + strlen($body);
            $body .= "$n 0 obj\n$data\nendobj\n";
        }

        $xref_pos = strlen($header) + strlen($body);
        $count    = count($objs) + 1;

        $xref  = "xref\n0 $count\n";
        $xref .= "0000000000 65535 f \n";
        foreach ($offsets as $off) {
            $xref .= sprintf("%010d 00000 n \n", $off);
        }

        $trailer = "trailer\n<< /Size $count /Root 6 0 R >>\n"
                 . "startxref\n$xref_pos\n%%EOF";

        return $header . $body . $xref . $trailer;
    }
}


// ─────────────────────────────────────────────────────────────────────────────
// Contract builder
// ─────────────────────────────────────────────────────────────────────────────

function locarc_build_pdf_from_data(array $data): string {
    $pdf = new LocarcPDF();
    $ml  = LocarcPDF::ML;
    $cw  = LocarcPDF::CW;
    $pw  = LocarcPDF::PW;

    // ── 1. Header: accent top bar + club name ────────────────────────────────
    $pdf->fillRect(0, 0, $pw, 6, LocarcPDF::C_ACCENT);  // thin top strip

    $header_raw   = str_replace("\r\n", "\n", (string)($data['club_header'] ?? ''));
    $header_lines = array_values(array_filter(explode("\n", $header_raw)));

    $y = 18;
    if (!empty($header_lines)) {
        $pdf->ctext($y, $header_lines[0], true, 12, LocarcPDF::C_ACCENT);
        $y += 15;
        foreach (array_slice($header_lines, 1) as $line) {
            $pdf->ctext($y, trim($line), false, 8.5, LocarcPDF::C_MED);
            $y += 11;
        }
    }
    $y += 4;
    $pdf->hline($ml, $y, $cw, LocarcPDF::C_ACCENT, 0.6);
    $y += 8;

    // ── 2. Title bar ─────────────────────────────────────────────────────────
    $pdf->fillRect($ml, $y, $cw, 24, LocarcPDF::C_ACCENT);
    $pdf->ctext($y + 6, 'CONTRAT DE LOCATION DE MATERIEL', true, 13, LocarcPDF::C_WHITE);
    $y += 24 + 10;

    // ── 3. Contract info (2 x 2 cells) ───────────────────────────────────────
    $half = $cw / 2;
    $rh   = 28;
    $pdf->cell($ml,         $y, $half, $rh, 'N° Contrat',        $data['contract_number'] ?? '');
    $pdf->cell($ml + $half, $y, $half, $rh, 'Date de signature',  $data['date_signature'] ?? '');
    $y += $rh;
    $pdf->cell($ml,         $y, $half, $rh, 'Type de contrat',    $data['type_contrat'] ?? '');
    $pdf->cell($ml + $half, $y, $half, $rh, 'Periode',
        ($data['date_debut'] ?? '') . ' - ' . ($data['date_fin'] ?? ''));
    $y += $rh + 12;

    // ── 4. Section : Licencie ────────────────────────────────────────────────
    $y = $pdf->section($y, 'LICENCIE');
    $y += 5;

    $third = $cw / 3;
    $pdf->cell($ml,               $y, $third, $rh, 'Nom',        $data['nom'] ?? '');
    $pdf->cell($ml + $third,      $y, $third, $rh, 'Prenom',     $data['prenom'] ?? '');
    $pdf->cell($ml + 2 * $third,  $y, $third, $rh, 'N° Licence', $data['licence'] ?? '');
    $y += $rh;

    $pdf->cell($ml,                $y, $third * 2,   $rh, 'Adresse', $data['adresse'] ?? '');
    $pdf->cell($ml + $third * 2,   $y, $third * 0.5, $rh, 'CP',      $data['cp'] ?? '');
    $pdf->cell($ml + $third * 2.5, $y, $third * 0.5, $rh, 'Ville',   $data['ville'] ?? '');
    $y += $rh;

    $pdf->cell($ml,         $y, $half, $rh, 'Email',     $data['email'] ?? '');
    $pdf->cell($ml + $half, $y, $half, $rh, 'Telephone', $data['tel'] ?? '');
    $y += $rh + 12;

    // ── 5. Section : Materiel loue ───────────────────────────────────────────
    $y = $pdf->section($y, 'MATERIEL LOUE');
    $y += 5;

    $cols_w = [52, 68, 78, 90, 48, 58, 121];
    $cols   = [
        [$cols_w[0], 'Type'],
        [$cols_w[1], 'Identifiant'],
        [$cols_w[2], 'Marque'],
        [$cols_w[3], 'Modele'],
        [$cols_w[4], 'Taille'],
        [$cols_w[5], 'Lateralite'],
        [$cols_w[6], 'Puissance'],
    ];
    $y = $pdf->thead($ml, $y, 16, $cols);

    $mat = $data['materiel'] ?? [];
    foreach ($mat as $i => $m) {
        $cells = [
            [$cols_w[0], $m['label']       ?? '', false],
            [$cols_w[1], $m['identifiant'] ?? '', true],
            [$cols_w[2], $m['marque']      ?? '', false],
            [$cols_w[3], $m['modele']      ?? '', false],
            [$cols_w[4], $m['taille']      ?? '', false],
            [$cols_w[5], $m['lateralite']  ?? '', false],
            [$cols_w[6], $m['puissance']   ?? '', false],
        ];
        $y = $pdf->trow($ml, $y, 22, $cells, $i % 2 === 1);
    }
    $y += 12;

    // ── 6. Section : Conditions financieres ──────────────────────────────────
    $y = $pdf->section($y, 'CONDITIONS FINANCIERES');
    $y += 5;

    $cout     = number_format((float)($data['cout_total'] ?? 0), 2, ',', ' ') . ' EUR';
    $caution  = number_format((float)($data['caution_amount'] ?? 400), 2, ',', ' ') . ' EUR';
    $paiement = (string)($data['payment_method'] ?? '');
    $quarter  = $cw / 4;

    $pdf->cell($ml,                $y, $quarter,     $rh, 'Montant total',    $cout);
    $pdf->cell($ml + $quarter,     $y, $quarter,     $rh, 'Caution',          $caution);
    $pdf->cell($ml + $quarter * 2, $y, $quarter * 2, $rh, 'Mode de paiement', $paiement);
    $y += $rh;

    $dues = $data['payment_due_dates'] ?? [];
    if (!empty($dues)) {
        $pdf->cell($ml, $y, $cw, $rh, 'Echeances (cheques)', implode('  /  ', $dues));
        $y += $rh;
    }
    $y += 12;

    // ── 7. Conditions generales ──────────────────────────────────────────────
    $y = $pdf->section($y, 'CONDITIONS GENERALES (EXTRAIT)');
    $y += 7;
    $conditions = [
        '1. Le locataire s\'engage a utiliser le materiel conformement a sa destination sportive et a en prendre soin.',
        '2. En cas de perte ou de dommage, une indemnisation a hauteur du cout de remplacement pourra etre demandee.',
        '3. La caution est restituee a la fin du contrat, sous reserve de la restitution du materiel en bon etat.',
        '4. Le contrat est renouvelable. Le locataire doit informer le club 30 jours avant l\'echeance en cas de non-renouvellement.',
        '5. Le materiel reste la propriete exclusive du club. Toute cession ou sous-location est interdite.',
    ];
    foreach ($conditions as $line) {
        $pdf->text($ml + 3, $y, $line, false, 7.5, LocarcPDF::C_MED);
        $y += 10;
    }
    $y += 10;

    // ── 8. Signatures ────────────────────────────────────────────────────────
    $sig_height = 95;
    if ($y + $sig_height > LocarcPDF::PH - 28) {
        $y = LocarcPDF::PH - $sig_height - 28;
    }

    $sig_w = $cw / 2 - 12;
    $sig_h = 55;
    $sig_y = $y + 18;

    // Left
    $pdf->text($ml, $y, 'Signature du locataire :', false, 8.5, LocarcPDF::C_ACCENT);
    $pdf->strokeRect($ml, $sig_y, $sig_w, $sig_h, LocarcPDF::C_BORDER, 0.5);
    $pdf->text($ml + 4, $sig_y + $sig_h + 5,
        trim(($data['prenom'] ?? '') . ' ' . ($data['nom'] ?? '')),
        false, 8.0, LocarcPDF::C_MED);

    // Right
    $rx   = $ml + $cw / 2 + 12;
    $resp = (string)($data['responsable_materiel'] ?? '');
    $pdf->text($rx, $y, 'Signature du responsable materiel :', false, 8.5, LocarcPDF::C_ACCENT);
    $pdf->strokeRect($rx, $sig_y, $sig_w, $sig_h, LocarcPDF::C_BORDER, 0.5);
    if ($resp !== '') {
        $pdf->text($rx + 4, $sig_y + $sig_h + 5, $resp, false, 8.0, LocarcPDF::C_MED);
    }

    // ── 9. Footer ────────────────────────────────────────────────────────────
    $fy        = LocarcPDF::PH - 20;
    $club_mail = (string)($data['club_mail'] ?? '');
    $footer    = 'Contrat n° ' . ($data['contract_number'] ?? '')
               . '   |   Genere le ' . ($data['date_signature'] ?? '');
    if ($club_mail !== '') $footer .= '   |   ' . $club_mail;
    $pdf->hline($ml, $fy - 5, $cw, LocarcPDF::C_ACCENT, 0.5);
    $pdf->ctext($fy, $footer, false, 7.5, LocarcPDF::C_MED);

    return $pdf->output();
}
