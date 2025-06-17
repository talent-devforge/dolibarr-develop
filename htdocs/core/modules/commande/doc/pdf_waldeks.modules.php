<?php

require_once DOL_DOCUMENT_ROOT.'/core/modules/commande/modules_commande.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/pdf.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/company.lib.php';

class pdf_waldeks extends ModelePDFCommandes
{
    public $name = 'waldeks';
    public $description = 'Minimal clean PDF module for Waldeks Sales Orders';

    public function __construct($db)
    {
        global $conf;

        $this->db = $db;
        $this->type = 'pdf';

        // Load format and margins from Dolibarr settings
        $formatarray = pdf_getFormat();
        $this->page_largeur = $formatarray['width'];
        $this->page_hauteur = $formatarray['height'];
        $this->format = array($this->page_largeur, $this->page_hauteur);

        $this->marge_gauche = getDolGlobalInt('MAIN_PDF_MARGIN_LEFT', 10);
        $this->marge_droite = getDolGlobalInt('MAIN_PDF_MARGIN_RIGHT', 10);
        $this->marge_haute = getDolGlobalInt('MAIN_PDF_MARGIN_TOP', 10);
        $this->marge_basse = getDolGlobalInt('MAIN_PDF_MARGIN_BOTTOM', 10);
        $this->corner_radius = getDolGlobalInt('MAIN_PDF_FRAME_CORNER_RADIUS', 0);

        // Keep functional flags
        $this->option_logo = 1;
        $this->option_tva = 1;
        $this->option_modereg = 1;
        $this->option_condreg = 1;
        $this->option_multilang = 1;
        $this->option_escompte = 0;
        $this->option_credit_note = 0;
        $this->option_freetext = 1;
        $this->option_draft_watermark = 0; // disabled watermark support
        $this->watermark = '';
    }

    public function write_file($object, $outputlangs, $srctemplatepath = '', $hidedetails = 0, $hidedesc = 0, $hideref = 0)
		{
				global $conf, $langs;

				if (!is_object($outputlangs)) {
						$outputlangs = $langs;
				}

				$outputlangs->loadLangs(["main", "orders", "companies"]);
				$object->fetch_thirdparty();

				$pdf = pdf_getInstance($this->format);
				$default_font_size = pdf_getPDFFontSize($outputlangs);

				$pdf->setPrintHeader(false);
				$pdf->SetAutoPageBreak(true, $this->marge_basse);
				$pdf->SetMargins($this->marge_gauche, $this->marge_haute, $this->marge_droite);
				$pdf->SetFont(pdf_getPDFFont($outputlangs), '', $default_font_size);
				$pdf->AddPage();

				$posy = $this->marge_haute;

				// Company logo (top left)
				if (!empty($conf->global->MAIN_INFO_SOCIETE_LOGO) && $this->option_logo) {
						$logo = $conf->mycompany->dir_output . "/logos/" . $conf->global->MAIN_INFO_SOCIETE_LOGO;
						if (is_readable($logo)) {
								$pdf->Image($logo, $this->marge_gauche, $posy, 40);
						}
				}

				$iso_logo_path = DOL_DOCUMENT_ROOT . '/iso_logo.png'; // Set correct path
				if (file_exists($iso_logo_path)) {
						$pdf->Image($iso_logo_path, 170, $posy, 25);
				}

				$posy += 25;

				// Sender info
				$web = $conf->global->MAIN_INFO_SOCIETE_WEB ?? '';
				$phone = $conf->global->MAIN_INFO_SOCIETE_PHONE ?? 'T +49 8061 2409';
				$mobile = $conf->global->MAIN_INFO_SOCIETE_MOBILE ?? 'M +49 176 3560 7848';
				$email = $conf->global->MAIN_INFO_SOCIETE_EMAIL ?? 'f.colombini@waldeks.com';

				$sender_text = $conf->global->MAIN_INFO_SOCIETE_NOM . "\n" .
						$conf->global->MAIN_INFO_SOCIETE_ADDRESS . "\n" .
						$conf->global->MAIN_INFO_SOCIETE_ZIP . " " . $conf->global->MAIN_INFO_SOCIETE_TOWN . "\n" .
						"Deutschland\n" .
						$web . "\n\n" .
						"Ihr Ansprechpartner\n" .
						$phone . "\n" .
						$mobile . "\n" .
						$email;

				$pdf->SetXY(155, $posy);
				$pdf->SetFont('', '', $default_font_size);
				$pdf->MultiCell(90, 5, $sender_text);

				$posy += 5;
				
				$company_info = "Waldeks GmbH - Dieselstraße2 - 83043 Bad Aibling-Deutschland";
				$pdf->SetFont('', '', $default_font_size - 2); 
				$pdf->SetXY($this->marge_gauche, $posy);
				$pdf->MultiCell(100, 6, $company_info);

				$posy += 10;

				$recipient = $object->thirdparty;
				$recipient_text = $recipient->name . "\n" .
						$recipient->address . "\n" .
						$recipient->zip . " " . $recipient->town . "\n" . 
						$recipient->country;                               

				$pdf->SetFont('', '', $default_font_size + 2); 
				$pdf->SetXY($this->marge_gauche, $posy);
				$pdf->MultiCell(100, 6, $recipient_text);

				$posy += 33;

				$pdf->SetFont('', 'B', 14);
				$pdf->SetXY($this->marge_gauche, $posy);
				$pdf->Cell(0, 8, $outputlangs->transnoentities("Auftragsbestätigung"), 0, 1, 'L');

				$pdf->SetFont('', '', $default_font_size - 1);

				// Coordinates
				$box_x = 130;           // Your custom X
				$box_y = $posy;         // Current Y from flow
				$box_width = 70;        // Width to fit on page (A4 = 210mm - margins)
				$line_height = 5;
				$col1_width = 28;
				$col2_width = $box_width - 32;

				// Draw rectangle box
				$box_height = $line_height * 6 + 4; // 6 rows + 2 padding
				$pdf->Rect($box_x, $box_y, $box_width, $box_height);

				// Title: Order confirmation (underlined, right-aligned)
				$pdf->SetFont('', 'U', $default_font_size);
				$pdf->SetXY($box_x, $box_y + 2);
				$pdf->Cell($box_width, $line_height, "Auftragsbestätigung", 0, 0, 'R');

				// Switch to content font
				$pdf->SetFont('', '', $default_font_size);

				// Content rows
				$current_y = $box_y + $line_height + 2; // Leave space under title
				$pdf->SetXY($box_x + 2, $current_y);
				$pdf->Cell($col1_width, $line_height, "Auftragsnummer:");
				$pdf->Cell($col2_width, $line_height, $object->ref, 0, 0, 'R');
				$current_y += $line_height;

				$pdf->SetXY($box_x + 2, $current_y);
				$pdf->Cell($col1_width, $line_height, "Angebotsnummer:");
				$pdf->Cell($col2_width, $line_height, $object->ref_client ?: '—', 0, 0, 'R');
				$current_y += $line_height;

				$pdf->SetXY($box_x + 2, $current_y);
				$pdf->Cell($col1_width, $line_height, "Ihre Kundennummer:");
				$pdf->Cell($col2_width, $line_height, $object->thirdparty->code_client, 0, 0, 'R');
				$current_y += $line_height;

				$origin = $object->origin; // should be 'propal'
				$origin_id = $object->origin_id;

				$quotation_ref = '';
				if ($origin === 'propal' && $origin_id > 0) {
						require_once DOL_DOCUMENT_ROOT.'/comm/propal/class/propal.class.php';
						$propal = new Propal($this->db);
						if ($propal->fetch($origin_id) > 0) {
								$quotation_ref = $propal->ref;
						}
				}

				$pdf->SetXY($box_x + 2, $current_y);
				$pdf->Cell($col1_width, $line_height, "Ihre BestellNr:");
				$pdf->Cell($col2_width, $line_height, ($object->ref_client ?: 'per Mail'), 0, 0, 'R');
				$current_y += $line_height;

				$pdf->SetXY($box_x + 2, $current_y);
				$pdf->Cell($col1_width, $line_height, "Auftragsdatum:");
				$pdf->Cell($col2_width, $line_height, dol_print_date($object->date, 'daytext'), 0, 0, 'R');

				// Move Y cursor for next content
				$posy = $box_y + $box_height + 5;

				$pdf->SetXY($this->marge_gauche, $posy);
				$pdf->SetFont('', '', $default_font_size);
				$pdf->MultiCell(
						$this->page_largeur - $this->marge_gauche - $this->marge_droite,
						6,
						"Wir danken für Ihren Auftrag, den wir freibleibend entsprechend unserer Verfügbarkeit unter Zugrundelegung unserer allgemeinen Geschäftsbedingungen (einzusehen unter https://www.waldeks.de/agb) bestätigen. Auf Wunsch übersenden wir Ihnen diese auch per Post oder Mail.",
						0,
						'L'
				);

				// === Table Header Top Line ===
				$posy = $pdf->GetY() + 5;

				// Solid line before the header
				$pdf->SetLineWidth(0.2);
				$pdf->Line($this->marge_gauche, $posy, $this->page_largeur - $this->marge_droite, $posy);

				// Draw Header
				$pdf->SetFont('', 'B', $default_font_size);
				$pdf->SetXY($this->marge_gauche, $posy);
				$pdf->Cell(10, 6, "Pos.", 0, 0, 'L');
				$pdf->Cell(15, 6, "Menge", 0, 0, 'L');
				$pdf->Cell(30, 6, "Unsere\nArt.-Nr", 0, 0, 'L');
				$pdf->Cell(80, 6, "Bezeichnung", 0, 0, 'L');
				$pdf->Cell(25, 6, "Einzelpreis\n(€)", 0, 0, 'R');
				$pdf->Cell(30, 6, "Gesamt\n(€)", 0, 0, 'R');
				$pdf->Ln();

				// Bold line after the header
				$pdf->SetLineWidth(0.6);
				$pdf->Line($this->marge_gauche, $pdf->GetY(), $this->page_largeur - $this->marge_droite, $pdf->GetY());
				$pdf->SetLineWidth(0.2);

				$posy = $pdf->GetY();
				$pdf->SetFont('', '', $default_font_size);
				$pos = 1;

				// === Table Body ===
				foreach ($object->lines as $line) {
					if ($line->subtype != 0) continue;

					$pdf->SetXY($this->marge_gauche, $posy);
					$pdf->Cell(10, 6, $pos++, 0, 0, 'L');
					$pdf->Cell(15, 6, $line->qty, 0, 0, 'L');
					$pdf->Cell(30, 6, $line->product_ref, 0, 0, 'L');
					$pdf->Cell(80, 6, dol_trunc($line->desc, 60), 0, 0, 'L');
					$pdf->Cell(25, 6, price($line->subprice), 0, 0, 'R');
					$pdf->Cell(30, 6, price($line->total_ht), 0, 0, 'R');
					$pdf->Ln();

					$y = $pdf->GetY();

					// Draw dashed line only if more than one visible line
					$visible_lines = array();
					foreach ($object->lines as $line) {
							if ($line->subtype != 0) continue; // pula linhas de subtipo (título, etc.)
							$visible_lines[] = $line;
					}

					$posy = $y;
				}

				// === Bold Bottom Line After Table ===
				$pdf->SetLineWidth(0.6);
				$pdf->Line($this->marge_gauche, $posy + 1, $this->page_largeur - $this->marge_droite, $posy + 1);
				$pdf->SetLineWidth(0.2);


				// Position cursor below table
				$posy += 10;
				$pdf->SetXY($this->marge_gauche, $posy);
				$pdf->SetFont('', '', $default_font_size);

				// Get delivery date and payment terms from database
				$delivery_date = dol_print_date($object->delivery_date, 'day');
				$payment_terms = $object->cond_reglement_code ?: '30 Tage netto';

				// Left column width
				$note_width = 100;

				// Text block
				$note_text = "Liefertermin:\n";
				$pdf->SetFont('', 'B', $default_font_size);
				$pdf->MultiCell($note_width, 5, $note_text, 0, 'L');

				$note_text = $delivery_date . " - unter üblichen Vorbehalten\n";
				$pdf->SetFont('', '', $default_font_size);
				$pdf->MultiCell($note_width, 5, $note_text, 0, 'L');

				$pdf->MultiCell($note_width, 2, "", 0, 'L');

				$pdf->SetFont('', 'B', $default_font_size);
				$pdf->MultiCell($note_width, 5, "Lieferbedingungen:", 0, 'L');

				$pdf->SetFont('', '', $default_font_size);
				$pdf->MultiCell($note_width, 5, "Die Preise verstehen sich ab Werk ausschl. Verpackung.\nDie Ware bleibt bis zur restlosen Bezahlung unser Eigentum.\nErfüllungsort und Gerichtsstand ist Rosenheim", 0, 'L');

				$pdf->MultiCell($note_width, 5, "", 0, 'L');

				$pdf->SetFont('', 'B', $default_font_size);
				$pdf->MultiCell($note_width, 5, "Zahlungsbedingungen:", 0, 'L');

				$pdf->SetFont('', '', $default_font_size);
				$pdf->MultiCell($note_width, 5, $payment_terms, 0, 'L');

				// Totals
				$posy -= 4;
				$pdf->SetXY(130, $posy);
				$pdf->Cell(40, 6, "Gesamt Netto", 0, 0, 'R');
				$pdf->Cell(30, 6, price($object->total_ht), 0, 1, 'R');

				$pdf->SetLineWidth(0.3);
				$pdf->Line(120, $pdf->GetY(), $this->page_largeur - $this->marge_droite, $pdf->GetY());

				$pdf->SetXY(130, $posy + 6);
				$pdf->Cell(40, 6, "Summe MwSt (19,0 % USt)", 0, 0, 'R');
				$pdf->Cell(30, 6, price($object->total_tva), 0, 1, 'R');

				$pdf->SetFont('', 'B');
				$pdf->SetXY(130, $posy + 12);
				$pdf->Cell(40, 6, "Gesamt Brutto", 0, 0, 'R');
				$pdf->Cell(30, 6, price($object->total_ttc), 0, 1, 'R');
				$pdf->SetLineWidth(0.6);
				$pdf->Line(120, $pdf->GetY() + 2, $this->page_largeur - $this->marge_droite, $pdf->GetY() + 2);

				$pdf->SetLineWidth(0.2);


				// === Smart Footer with Large Page Number ===
				$pagecount = $pdf->getNumPages();
				$footer_font_size = $default_font_size - 2;
				$page_number_font_size = $default_font_size + 1;

				for ($i = 1; $i <= $pagecount; $i++) {
						$pdf->setPage($i);

						// Compute footer Y position based on page height
						$footer_bottom_y = $this->page_hauteur - $this->marge_basse;
						$footer_table_y = $footer_bottom_y - 18;
						$footer_pageno_y = $footer_table_y - 6;

						$column_width = ($this->page_largeur - $this->marge_gauche - $this->marge_droite) / 4;

						// --- Large Page Number
						$pdf->SetFont('', 'B', $page_number_font_size);
						$pdf->SetXY($this->page_largeur - $this->marge_droite - 40, $footer_pageno_y);
						$pdf->Cell(40, 6, "Seite $i von $pagecount", 0, 0, 'R');

						// --- Footer 4x1 Block
						$pdf->SetFont('', '', $footer_font_size);

						$pdf->SetXY($this->marge_gauche, $footer_table_y);
						$pdf->MultiCell($column_width, 4,
								"waldeks GmbH\nDieselstraße 2, 83043 Bad Aibling\nTel.: +49 8061 2406\ninfo@waldeks.com\nwww.waldeks.de",
								0, 'L'
						);

						$pdf->SetXY($this->marge_gauche + $column_width, $footer_table_y);
						$pdf->MultiCell($column_width, 4,
								"Sitz: Bad Aibling\nAmtsgericht: Traunstein HRB 28276\nUst-IdNr.: DE327061763\nCEO: Filipe Colombini",
								0, 'L'
						);

						$pdf->SetXY($this->marge_gauche + $column_width * 2, $footer_table_y);
						$pdf->MultiCell($column_width, 4,
								"Bankverbindung:\nVolksbank Raiffeisenbank\nRosenheim-Chiemsee eG",
								0, 'L'
						);

						$pdf->SetXY($this->marge_gauche + $column_width * 3, $footer_table_y);
						$pdf->MultiCell($column_width, 4,
								"IBAN:\nDE59 7116 0000 0008 0679 37\n\nBIC:\nGENODEF1VR",
								0, 'L'
						);
				}

				// Save
				$dir = $conf->commande->dir_output . "/" . dol_sanitizeFileName($object->ref);
				dol_mkdir($dir);
				$file = $dir . "/" . dol_sanitizeFileName($object->ref) . ".pdf";
				$pdf->Output($file, 'F');

				return 1;
		}

}
