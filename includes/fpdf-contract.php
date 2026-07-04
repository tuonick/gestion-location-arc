<?php
/**
 * FPDF renderer for the combined rental contract and invoice document.
 *
 * @package GestionLocationArc
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Convert UTF-8 text for the core FPDF fonts.
 *
 * @param mixed $value Value to convert.
 * @return string
 */
function locarc_fpdf_text( $value ) {
	$value = (string) $value;
	if ( function_exists( 'iconv' ) ) {
		$converted = iconv( 'UTF-8', 'ISO-8859-1//TRANSLIT', $value );
		if ( false !== $converted ) {
			return $converted;
		}
	}
	return $value;
}

/**
 * Format an amount in euros.
 *
 * @param mixed $value Numeric amount.
 * @return string
 */
function locarc_fpdf_money( $value ) {
	return number_format( (float) $value, 2, ',', ' ' ) . ' EUR';
}

/**
 * Print an FPDF cell after converting its text.
 *
 * @param FPDF   $pdf       PDF document.
 * @param float  $width     Cell width.
 * @param float  $height    Cell height.
 * @param string $text      Cell text.
 * @param mixed  $border    Border definition.
 * @param int    $next_line Next-line mode.
 * @param string $align     Text alignment.
 * @param bool   $fill      Whether to fill the cell.
 * @return void
 */
function locarc_fpdf_cell( $pdf, $width, $height, $text = '', $border = 0, $next_line = 0, $align = '', $fill = false ) {
	$pdf->Cell( $width, $height, locarc_fpdf_text( $text ), $border, $next_line, $align, $fill );
}

/**
 * Print an FPDF multi-line cell after converting its text.
 *
 * @param FPDF   $pdf    PDF document.
 * @param float  $width  Cell width.
 * @param float  $height Line height.
 * @param string $text   Cell text.
 * @param mixed  $border Border definition.
 * @param string $align  Text alignment.
 * @param bool   $fill   Whether to fill the cell.
 * @return void
 */
function locarc_fpdf_multicell( $pdf, $width, $height, $text, $border = 0, $align = 'L', $fill = false ) {
	$pdf->MultiCell( $width, $height, locarc_fpdf_text( $text ), $border, $align, $fill );
}

/**
 * Print a document section heading.
 *
 * @param FPDF   $pdf   PDF document.
 * @param string $title Section title.
 * @return void
 */
function locarc_fpdf_section( $pdf, $title ) {
	$pdf->Ln( 3 );
	$pdf->SetFont( 'Helvetica', 'B', 8.5 );
	$pdf->SetTextColor( 68, 68, 68 );
	$title = function_exists( 'mb_strtoupper' )
		? mb_strtoupper( $title, 'UTF-8' )
		: strtoupper(
			strtr(
				$title,
				array(
					'à' => 'À',
					'â' => 'Â',
					'ç' => 'Ç',
					'é' => 'É',
					'è' => 'È',
					'ê' => 'Ê',
					'ë' => 'Ë',
					'î' => 'Î',
					'ï' => 'Ï',
					'ô' => 'Ô',
					'ù' => 'Ù',
					'û' => 'Û',
					'ü' => 'Ü',
				)
			)
		);
	locarc_fpdf_cell( $pdf, 0, 5, $title, 'B', 1 );
	$pdf->SetTextColor( 26, 26, 26 );
}

/**
 * Print one key/value line in the contract summary.
 *
 * @param FPDF   $pdf    PDF document.
 * @param float  $x      Horizontal position.
 * @param float  $y      Vertical position.
 * @param string $label  Row label.
 * @param string $value  Row value.
 * @param bool   $strong Whether to emphasize the row.
 * @return float
 */
function locarc_fpdf_summary_row( $pdf, $x, $y, $label, $value, $strong = false ) {
	if ( '' === (string) $value ) {
		return $y;
	}
	$pdf->SetXY( $x, $y );
	$pdf->SetFont( 'Helvetica', $strong ? 'B' : '', 8 );
	$pdf->SetTextColor( 102, 102, 102 );
	locarc_fpdf_cell( $pdf, 31, 4, $label );
	$pdf->SetFont( 'Helvetica', $strong ? 'B' : '', 8.5 );
	$pdf->SetTextColor( 26, 26, 26 );
	locarc_fpdf_cell( $pdf, 55, 4, $value );
	return $y + 4;
}

/**
 * Print the invoiced service line.
 *
 * @param FPDF   $pdf        PDF document.
 * @param string $label      Service description.
 * @param string $quantity   Quantity.
 * @param float  $unit_price Unit price.
 * @param float  $total      Total amount.
 * @return void
 */
function locarc_fpdf_invoice_row( $pdf, $label, $quantity, $unit_price, $total ) {
	$widths = array( 104, 18, 32, 32 );
	$pdf->SetFont( 'Helvetica', '', 8 );
	locarc_fpdf_cell( $pdf, $widths[0], 6, $label, 1 );
	locarc_fpdf_cell( $pdf, $widths[1], 6, $quantity, 1, 0, 'C' );
	locarc_fpdf_cell( $pdf, $widths[2], 6, locarc_fpdf_money( $unit_price ), 1, 0, 'R' );
	locarc_fpdf_cell( $pdf, $widths[3], 6, locarc_fpdf_money( $total ), 1, 1, 'R' );
}

/**
 * Build the combined rental contract and invoice PDF.
 *
 * @param array $data Document data.
 * @return string
 */
function locarc_render_contract_fpdf( $data ) {
	$pdf = new FPDF( 'P', 'mm', 'A4' );
	$pdf->SetMargins( 12, 10, 12 );
	$pdf->SetAutoPageBreak( true, 10 );
	$pdf->SetTitle( 'Contrat de location et facture ' . ( $data['invoice_number'] ?? '' ), true );
	$pdf->SetAuthor( 'ACSIM', true );
	$pdf->AddPage();

	$left  = 12;
	$right = 198;
	$width = $right - $left;

	$pdf->SetFont( 'Helvetica', 'B', 7.5 );
	$pdf->SetTextColor( 85, 85, 85 );
	$header_lines = preg_split( '/\r\n|\r|\n/', trim( (string) ( $data['club_header'] ?? '' ) ) );
	foreach ( (array) $header_lines as $index => $line ) {
		if ( '' === trim( $line ) ) {
			continue;
		}
		$pdf->SetFont( 'Helvetica', 0 === $index ? 'B' : '', 7.5 );
		locarc_fpdf_cell( $pdf, 122, 3.3, trim( $line ), 0, 1 );
	}
	$pdf->SetFont( 'Helvetica', '', 7.5 );
	locarc_fpdf_cell( $pdf, 122, 3.3, 'SIRET : ' . ( $data['club_siret'] ?? '' ), 0, 1 );
	locarc_fpdf_cell( $pdf, 122, 3.3, $data['vat_mention'] ?? '', 0, 1 );

	$pdf->SetXY( 148, 12 );
	$pdf->SetDrawColor( 187, 187, 187 );
	$pdf->Rect( 148, 12, 50, 18 );
	$pdf->SetFont( 'Helvetica', '', 7 );
	$pdf->SetTextColor( 136, 136, 136 );
	locarc_fpdf_cell( $pdf, 50, 6, 'DATE D\'EMISSION', 0, 1, 'C' );
	$pdf->SetX( 148 );
	$pdf->SetFont( 'Helvetica', 'B', 10 );
	$pdf->SetTextColor( 26, 26, 26 );
	locarc_fpdf_cell( $pdf, 50, 7, $data['date_signature'] ?? '', 0, 1, 'C' );

	$pdf->SetXY( $left, 36 );
	$pdf->SetFont( 'Helvetica', 'B', 16 );
	locarc_fpdf_cell( $pdf, 130, 7, 'Contrat de location et facture', 0, 1 );
	$pdf->SetFont( 'Helvetica', '', 8.5 );
	$pdf->SetTextColor( 102, 102, 102 );
	locarc_fpdf_cell( $pdf, 130, 4, 'Contrat N° ' . ( $data['contract_number'] ?? '' ) . ' - Facture N° ' . ( $data['invoice_number'] ?? '' ), 0, 1 );

	$pdf->Ln( 2 );
	$pdf->SetFillColor( 240, 240, 240 );
	$pdf->SetDrawColor( 51, 51, 51 );
	$pdf->SetTextColor( 26, 26, 26 );
	$pdf->SetFont( 'Helvetica', 'B', 9 );
	locarc_fpdf_cell( $pdf, $width, 7, 'Période de location : ' . ( $data['date_debut'] ?? '' ) . ' au ' . ( $data['date_fin'] ?? '' ), 'L', 1, '', true );

	$box_y = $pdf->GetY() + 3;
	$pdf->SetDrawColor( 204, 204, 204 );
	$pdf->Rect( $left, $box_y, 94, 42 );
	$pdf->Rect( 110, $box_y, 88, 42 );

	$pdf->SetXY( $left + 3, $box_y + 3 );
	$pdf->SetFont( 'Helvetica', 'B', 7 );
	$pdf->SetTextColor( 136, 136, 136 );
	locarc_fpdf_cell( $pdf, 88, 4, 'LOCATAIRE / CLIENT', 'B', 1 );
	$pdf->SetX( $left + 3 );
	$pdf->SetFont( 'Helvetica', 'B', 10 );
	$pdf->SetTextColor( 26, 26, 26 );
	locarc_fpdf_cell( $pdf, 88, 5, trim( ( $data['prenom'] ?? '' ) . ' ' . ( $data['nom'] ?? '' ) ), 0, 1 );
	$pdf->SetX( $left + 3 );
	$pdf->SetFont( 'Helvetica', '', 8 );
	locarc_fpdf_cell( $pdf, 88, 4, 'Licence : ' . ( $data['licence'] ?? '' ), 0, 1 );
	foreach ( array( $data['adresse'] ?? '', trim( ( $data['cp'] ?? '' ) . ' ' . ( $data['ville'] ?? '' ) ), $data['email'] ?? '', $data['tel'] ?? '' ) as $line ) {
		if ( '' !== trim( (string) $line ) ) {
			$pdf->SetX( $left + 3 );
			locarc_fpdf_cell( $pdf, 88, 4, $line, 0, 1 );
		}
	}

	$pdf->SetXY( 113, $box_y + 3 );
	$pdf->SetFont( 'Helvetica', 'B', 7 );
	$pdf->SetTextColor( 136, 136, 136 );
	locarc_fpdf_cell( $pdf, 82, 4, 'CONTRAT', 'B', 1 );
	$row_y = $box_y + 9;
	$row_y = locarc_fpdf_summary_row( $pdf, 113, $row_y, 'Type', $data['type_contrat'] ?? '' );
	$row_y = locarc_fpdf_summary_row( $pdf, 113, $row_y, 'Début', $data['date_debut'] ?? '' );
	$row_y = locarc_fpdf_summary_row( $pdf, 113, $row_y, 'Fin', $data['date_fin'] ?? '' );
	$row_y = locarc_fpdf_summary_row( $pdf, 113, $row_y, 'Paiement', $data['payment_method'] ?? '' );
	$row_y = locarc_fpdf_summary_row( $pdf, 113, $row_y, 'Caution', locarc_fpdf_money( $data['caution_amount'] ?? 0 ) );
	locarc_fpdf_summary_row( $pdf, 113, $row_y, 'Location', locarc_fpdf_money( $data['cout_total'] ?? 0 ), true );

	$pdf->SetY( $box_y + 44 );
	locarc_fpdf_section( $pdf, 'Facture' );
	$pdf->SetFillColor( 244, 244, 244 );
	$pdf->SetFont( 'Helvetica', 'B', 7.5 );
	$pdf->SetTextColor( 85, 85, 85 );
	locarc_fpdf_cell( $pdf, 104, 6, 'PRESTATION', 1, 0, '', true );
	locarc_fpdf_cell( $pdf, 18, 6, 'QTÉ', 1, 0, 'C', true );
	locarc_fpdf_cell( $pdf, 32, 6, 'PRIX UNITAIRE', 1, 0, 'R', true );
	locarc_fpdf_cell( $pdf, 32, 6, 'TOTAL', 1, 1, 'R', true );
	$amount = (float) ( $data['cout_total'] ?? 0 );
	locarc_fpdf_invoice_row( $pdf, 'Location de matériel d\'archerie - ' . ( $data['type_contrat'] ?? '' ), '1', $amount, $amount );
	$pdf->SetFont( 'Helvetica', 'B', 8 );
	locarc_fpdf_cell( $pdf, 154, 6, 'TOTAL À PAYER', 1, 0, 'R', true );
	locarc_fpdf_cell( $pdf, 32, 6, locarc_fpdf_money( $amount ), 1, 1, 'R', true );
	$pdf->SetFont( 'Helvetica', '', 7.5 );
	locarc_fpdf_cell( $pdf, 0, 4, $data['vat_mention'] ?? '', 0, 1 );

	locarc_fpdf_section( $pdf, 'Matériel loué' );
	$material_widths = array( 23, 34, 25, 43, 18, 25, 18 );
	$headers         = array( 'ÉLÉMENT', 'IDENTIFIANT', 'MARQUE', 'MODÈLE', 'TAILLE', 'LATÉRALITÉ', 'PUISSANCE' );
	$pdf->SetFillColor( 244, 244, 244 );
	$pdf->SetFont( 'Helvetica', 'B', 7 );
	foreach ( $headers as $index => $header ) {
		locarc_fpdf_cell( $pdf, $material_widths[ $index ], 5.5, $header, 1, count( $headers ) - 1 === $index ? 1 : 0, '', true );
	}
	$pdf->SetFont( 'Helvetica', '', 7.5 );
	foreach ( (array) ( $data['materiel'] ?? array() ) as $item ) {
		$values = array( $item['label'] ?? '', $item['identifiant'] ?? '', $item['marque'] ?? '', $item['modele'] ?? '', $item['taille'] ?? '', $item['lateralite'] ?? '', $item['puissance'] ?? '' );
		foreach ( $values as $index => $value ) {
			locarc_fpdf_cell( $pdf, $material_widths[ $index ], 5.5, $value, 1, count( $values ) - 1 === $index ? 1 : 0 );
		}
	}

	locarc_fpdf_section( $pdf, 'Paiement et caution' );
	$pdf->SetFont( 'Helvetica', 'B', 7.5 );
	$pdf->SetFillColor( 244, 244, 244 );
	locarc_fpdf_cell( $pdf, 45, 5.5, 'MODE DE PAIEMENT', 1, 0, '', true );
	$pdf->SetFont( 'Helvetica', '', 8 );
	locarc_fpdf_cell( $pdf, 48, 5.5, $data['payment_method'] ?? '', 1 );
	$pdf->SetFont( 'Helvetica', 'B', 7.5 );
	locarc_fpdf_cell( $pdf, 40, 5.5, 'CAUTION', 1, 0, '', true );
	$pdf->SetFont( 'Helvetica', '', 8 );
	locarc_fpdf_cell( $pdf, 53, 5.5, locarc_fpdf_money( $data['caution_amount'] ?? 0 ), 1, 1 );

	$dates = array_values( array_filter( (array) ( $data['payment_due_dates'] ?? array() ) ) );
	$pdf->SetFont( 'Helvetica', 'B', 7.5 );
	locarc_fpdf_cell( $pdf, 45, 5.5, 'ÉCHÉANCES', 1, 0, '', true );
	$pdf->SetFont( 'Helvetica', '', 7.5 );
	if ( ! empty( $dates ) ) {
		$count = count( $dates );
		$cents = (int) round( $amount * 100 );
		$base  = intdiv( $cents, $count );
		foreach ( $dates as $index => $date ) {
			$part = $base + ( $index === $count - 1 ? $cents - ( $base * $count ) : 0 );
			if ( $index > 0 ) {
				locarc_fpdf_cell( $pdf, 45, 5.5, '', 1 );
			}
			locarc_fpdf_cell( $pdf, 141, 5.5, 'T' . ( $index + 1 ) . ' ' . $date . ' - ' . locarc_fpdf_money( $part / 100 ), 1, 1 );
		}
	} else {
		locarc_fpdf_cell( $pdf, 141, 5.5, 'Non applicable', 1, 1 );
	}

	$pdf->Ln( 3 );
	$pdf->SetFont( 'Helvetica', '', 7 );
	$pdf->SetTextColor( 102, 102, 102 );
	locarc_fpdf_multicell( $pdf, $width, 3.2, 'Le matériel reste la propriété du club. Il doit être restitué dans le même état. En cas de non-retour, perte ou détérioration, le club pourra encaisser tout ou partie de la caution. Pour acter le rendu, envoyer un mail à ' . ( $data['club_mail'] ?? '' ) . '.', 1 );

	$sign_y = $pdf->GetY() + 4;
	$pdf->SetTextColor( 26, 26, 26 );
	$pdf->Rect( $left, $sign_y, 89, 20 );
	$pdf->Rect( 109, $sign_y, 89, 20 );
	$pdf->SetXY( $left + 3, $sign_y + 3 );
	$pdf->SetFont( 'Helvetica', 'B', 8.5 );
	locarc_fpdf_cell( $pdf, 83, 4, 'L\'emprunteur', 0, 1 );
	$pdf->SetX( $left + 3 );
	$pdf->SetFont( 'Helvetica', '', 7 );
	$pdf->SetTextColor( 136, 136, 136 );
	locarc_fpdf_cell( $pdf, 83, 4, 'Signature précédée de "Lu et approuvé"' );
	$pdf->SetXY( 112, $sign_y + 3 );
	$pdf->SetFont( 'Helvetica', 'B', 8.5 );
	$pdf->SetTextColor( 26, 26, 26 );
	locarc_fpdf_cell( $pdf, 83, 4, 'Le responsable du matériel', 0, 1 );
	$pdf->SetX( 112 );
	$pdf->SetFont( 'Helvetica', '', 7 );
	$pdf->SetTextColor( 136, 136, 136 );
	locarc_fpdf_cell( $pdf, 83, 4, trim( ( $data['responsable_materiel'] ?? '' ) . ' - ' . ( $data['club_mail'] ?? '' ) ) );

	return $pdf->Output( 'S' );
}
