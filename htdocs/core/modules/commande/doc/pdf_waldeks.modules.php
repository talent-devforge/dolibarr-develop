<?php

require_once DOL_DOCUMENT_ROOT . '/core/modules/commande/modules_commande.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/pdf.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/company.lib.php';
require_once DOL_DOCUMENT_ROOT . '/includes/tecnickcom/tcpdf/tcpdf.php';

class PDFWithFooter extends TCPDF
{
	public $footerCallback;
	public $headerCallback;
	public function Header()
	{
		if (is_callable($this->headerCallback)) {
			call_user_func($this->headerCallback, $this);
		}
	}
	public function Footer()
	{
		if (is_callable($this->footerCallback)) {
			call_user_func($this->footerCallback, $this);
		}
	}
}

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

		$this->marge_gauche = getDolGlobalInt('MAIN_PDF_MARGIN_LEFT', 17);
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

	private function drawTableHeader($pdf, $default_font_size)
	{
		$headerHeight = 10;

		$w1 = 15;
		$w2 = 15;
		$w3 = 15;
		$w5 = 20;
		$w6 = 20;
		$w4 = $this->page_largeur - $this->marge_gauche - $this->marge_droite - ($w1 + $w2 + $w3 + $w5 + $w6);

		$headers = [
			['label' => 'Pos.', 'width' => $w1],
			['label' => 'Menge', 'width' => $w2],
			['label' => "Unsere\nArt.-Nr", 'width' => $w3],
			['label' => 'Bezeichnung', 'width' => $w4],
			['label' => "Einzelpreis\n(€)", 'width' => $w5],
			['label' => "Gesamt\n(€)", 'width' => $w6],
		];

		$posy = $pdf->GetY() + 5;

		// Top line
		$pdf->SetLineWidth(0.2);
		$pdf->Line($this->marge_gauche, $posy, $this->page_largeur - $this->marge_droite, $posy);

		$pdf->SetFont('', '', $default_font_size);

		$x = $this->marge_gauche;
		foreach ($headers as $h) {
			$pdf->SetXY($x, $posy + 1);

			$lines = explode("\n", $h['label']);
			$lineCount = count($lines);
			$lineHeight = 4;
			$textBlockHeight = $lineCount * $lineHeight;
			$yOffset = ($headerHeight - $textBlockHeight) / 2;

			// Start at vertically centered position
			$y = $posy + 1 + $yOffset;

			// MultiCell allows vertical stacking with proper centering
			$pdf->SetXY($x, $y);
			$pdf->MultiCell(
				$h['width'],
				$lineHeight,
				$h['label'],
				0,
				'C',
				false,
				0
			);

			$x += $h['width'];
		}


		// Bottom line under header
		$pdf->SetLineWidth(0.4);
		$pdf->Line($this->marge_gauche, $posy + $headerHeight + 1, $this->page_largeur - $this->marge_droite, $posy + $headerHeight + 1);
		$pdf->SetY($posy + $headerHeight + 2);
	}


	public function write_file($object, $outputlangs, $srctemplatepath = '', $hidedetails = 0, $hidedesc = 0, $hideref = 0)
	{
		global $conf, $langs, $user, $object;

		if (!is_object($outputlangs)) {
			$outputlangs = $langs;
		}


		$outputlangs->loadLangs(["main", "orders", "companies"]);
		$object->fetch_thirdparty();

		$default_font_size = pdf_getPDFFontSize($outputlangs);

		// $pdf = pdf_getInstance($this->format);
		$pdf = new PDFWithFooter(PDF_PAGE_ORIENTATION, PDF_UNIT, $this->format, true, 'UTF-8', false);
		$pdf->SetAutoPageBreak(true, $this->marge_basse);
		$pdf->SetMargins($this->marge_gauche, $this->marge_haute, $this->marge_droite);
		$pdf->setPrintHeader(false);
		$pdf->SetFont(pdf_getPDFFont($outputlangs), '', $default_font_size);
		$pdf->AddPage();

		// === DIN 676 Standard Fold Marks ===
		$pdf->SetDrawColor(0, 0, 0); // black
		$pdf->SetLineWidth(0.4);

		// Left X position (just inside left edge)
		$markerX = $this->marge_gauche - 5;

		// Faltmarke 1 (fold at 87mm)
		$pdf->Line($markerX, 87, $markerX + 2, 87);

		// Faltmarke 2 (fold at 192mm)
		$pdf->Line($markerX, 192, $markerX + 2, 192);

		// === Header
		$pdf->headerCallback = function ($pdf) use ($object, $default_font_size, $outputlangs) {
			$startY = 10;
			$page_width = $pdf->getPageWidth();
			$margins = $pdf->getMargins();
			$left = $margins['left'];

			// Waldeks Logo
			$logo_path = DOL_DATA_ROOT . "/mycompany/logos/" . $GLOBALS['conf']->global->MAIN_INFO_SOCIETE_LOGO;
			if (is_readable($logo_path)) {
				$pdf->Image($logo_path, $left, $startY, 60);
			}

			// Draw order strip **only on pages after page 1**
			if ($pdf->getPage() > 1) {
				$pdf->SetY($startY + 20);
				$pdf->SetTextColor(0, 0, 0);

				$ref = $object->ref;
				$date = dol_print_date($object->date, 'day', '', $outputlangs);
				$client = $object->thirdparty->name;
				$code_client = $object->thirdparty->code_client;

				$boldPart = "Auftrags Nr. $ref vom $date";
				$normalPart = " an $client / $code_client";

				$pdf->SetX($left);

				// Bold part
				$pdf->SetFont('', 'B', $default_font_size);
				$pdf->Cell($pdf->GetStringWidth($boldPart), 10, $boldPart, 0, 0, 'L');

				// Normal part
				$pdf->SetFont('', '', $default_font_size);
				$pdf->Cell(0, 10, $normalPart, 0, 1, 'L');
			}

		};

		// === Footer
		$pdf->footerCallback = function ($pdf) use ($default_font_size) {
			$page_width = $pdf->getPageWidth();
			$page_height = $pdf->getPageHeight();
			$margins = $pdf->getMargins();
			$footer_y = $page_height - 22;
			$line_height = 4;

			$pdf->SetFont('', '', $default_font_size - 2);
			$pdf->SetTextColor(0, 0, 0);

			// Block content
			$blocks = [
				"WALDEKS GmbH\nDieselstraße 2, 83043 Bad Aibling\nTel.: +49 8061 2406\ninfo@waldeks.com\nwww.waldeks.de",
				"Sitz: Bad Aibling\nAmtsgericht: Traunstein HRB 28276\nUSt-IdNr.: DE327061763\nCEO: Filipe Colombini",
				"Bankverbindung:\nVolksbank Raiffeisenbank\nRosenheim-Chiemsee eG",
				"IBAN:\nDE59 7116 0000 0008 0679 37\nBIC:\nGENODEF1VR"
			];

			// 1. Calculate width of each block
			$blockWidths = [];
			foreach ($blocks as $text) {
				$maxLineWidth = 0;
				foreach (explode("\n", $text) as $line) {
					$w = $pdf->GetStringWidth($line);
					if ($w > $maxLineWidth)
						$maxLineWidth = $w;
				}
				$blockWidths[] = $maxLineWidth + 2; // Add a small padding
			}

			// 2. Total block content width
			$totalBlockWidth = array_sum($blockWidths);

			// 3. Remaining space for gaps
			$usableWidth = $page_width - $margins['left'] - $margins['right'];
			$spaceLeft = $usableWidth - $totalBlockWidth;

			// 4. Equal spacing between blocks (3 gaps between 4 blocks)
			$gap = $spaceLeft / (count($blocks) - 1);

			// 5. Draw blocks with calculated positions
			$currentX = $margins['left'];
			for ($i = 0; $i < count($blocks); $i++) {
				$pdf->SetXY($currentX, $footer_y);
				$pdf->MultiCell($blockWidths[$i], $line_height, $blocks[$i], 0, 'L');
				$currentX += $blockWidths[$i] + $gap;
			}

			// Page number aligned right
			$pdf->SetFont('', 'B', $default_font_size + 1);
			$pdf->SetXY($page_width - $margins['right'] - 35, $footer_y - 12);
			$pdf->Cell(50, 10, "Seite " . $pdf->getAliasNumPage() . " von " . $pdf->getAliasNbPages(), 0, 0, 'R');
		};



		$pdf->setPrintHeader(true);
		$pdf->SetAutoPageBreak(true, $this->marge_basse);
		$pdf->SetMargins($this->marge_gauche, $this->marge_haute, $this->marge_droite);
		$pdf->SetFont(pdf_getPDFFont($outputlangs), '', $default_font_size);



		$posy = $this->marge_haute;

		// Company logo (top left)
		if (!empty($conf->global->MAIN_INFO_SOCIETE_LOGO) && $this->option_logo) {
			$logo = $conf->mycompany->dir_output . "/logos/" . $conf->global->MAIN_INFO_SOCIETE_LOGO;
			if (is_readable($logo)) {
				$pdf->Image($logo, $this->marge_gauche, $posy, 60);
			}
		}

		$iso_logo_path = DOL_DOCUMENT_ROOT . '/iso_logo.png'; // Set correct path
		if (file_exists($iso_logo_path)) {
			$pdf->Image($iso_logo_path, 170, $posy, 25);
		}

		$posy += 25;

		// Sender info
		$web = $conf->global->MAIN_INFO_SOCIETE_WEB ?? '';
		$phone = !empty($user->office_phone) ? "T " . $user->office_phone : "-";
		$mobile = !empty($user->user_mobile) ? "M " . $user->user_mobile : "-";
		$email = !empty($user->email) ? $user->email : "-";

		$pdf->SetTextColor(50, 50, 50); // Dark gray text
		$startX = 155;
		$colWidth = 50;
		$lineHeight = 5;

		$pdf->SetXY($startX, $posy);

		// Line 1: Waldeks GmbH (bold)
		$pdf->SetFont('', 'B', $default_font_size);
		$pdf->MultiCell($colWidth, $lineHeight, $conf->global->MAIN_INFO_SOCIETE_NOM, 0, 'L');
		$pdf->SetX($startX);

		// Line 2: Address
		$pdf->SetFont('', '', $default_font_size);
		$pdf->MultiCell($colWidth, $lineHeight, $conf->global->MAIN_INFO_SOCIETE_ADDRESS, 0, 'L');
		$pdf->SetX($startX);

		// Line 3: ZIP + Town
		$pdf->MultiCell($colWidth, $lineHeight, $conf->global->MAIN_INFO_SOCIETE_ZIP . ' ' . $conf->global->MAIN_INFO_SOCIETE_TOWN, 0, 'L');
		$pdf->SetX($startX);

		// Line 4: Country (manually written as standardized)
		$pdf->MultiCell($colWidth, $lineHeight, 'Deutschland', 0, 'L');
		$pdf->SetX($startX);

		// Line 5: Website
		if (!empty($web)) {
			$pdf->MultiCell($colWidth, $lineHeight, $web, 0, 'L');
			$pdf->SetX($startX);
		}

		// Spacer
		$pdf->Ln(2);
		$pdf->SetX($startX);

		// Line 6: "Ihr Ansprechpartner" (bold)
		$pdf->SetFont('', 'B', $default_font_size);
		$pdf->MultiCell($colWidth, $lineHeight, 'Ihr Ansprechpartner', 0, 'L');
		$pdf->SetX($startX);

		// Line 7–9: Phone, Mobile, Email (normal)
		$pdf->SetFont('', '', $default_font_size);
		$pdf->MultiCell($colWidth, $lineHeight, $phone ?: '-', 0, 'L');
		$pdf->SetX($startX);
		$pdf->MultiCell($colWidth, $lineHeight, $mobile ?: '-', 0, 'L');
		$pdf->SetX($startX);
		$pdf->MultiCell($colWidth, $lineHeight, $email ?: '-', 0, 'L');

		// Reset text color to black
		$pdf->SetTextColor(0, 0, 0);


		$posy += 5;

		$company_info = $conf->global->MAIN_INFO_SOCIETE_NOM . " - " .
			$conf->global->MAIN_INFO_SOCIETE_ADDRESS . " - " .
			$conf->global->MAIN_INFO_SOCIETE_ZIP . " " .
			$conf->global->MAIN_INFO_SOCIETE_TOWN . " - " .
			preg_replace('/^.*:/', '', $conf->global->MAIN_INFO_SOCIETE_COUNTRY);

		$pdf->SetFont('', '', $default_font_size - 1);
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
		$box_width = 80; // Keep width as is, or adjust
		$box_x = $this->page_largeur - $this->marge_droite - $box_width;

		$box_y = $posy;         // Current Y from flow
		$line_height = 5;
		$col1_width = 28;
		$col2_width = $box_width - 32;

		// Draw rectangle box
		$box_height = $line_height * 6 + 4; // 6 rows + 2 padding
		$pdf->SetLineWidth(0.2);
		$pdf->Rect($box_x, $box_y, $box_width, $box_height);
		// $pdf->SetLineWidth(0.2);

		// Title: Order confirmation (underlined, right-aligned)
		$pdf->SetFont('', 'U', $default_font_size);
		$pdf->SetXY($box_x + 2 + $col1_width, $box_y + 2); // match the ref's X
		$pdf->Cell($col2_width, $line_height, "Auftragsbestätigung", 0, 0, 'R');

		// Switch to content font
		$pdf->SetFont('', '', $default_font_size);

		// Content rows
		$current_y = $box_y + $line_height + 2; // Leave space under title

		// Auftragsnummer
		$pdf->SetXY($box_x + 2, $current_y);
		$pdf->Cell($col1_width, $line_height, "Auftragsnummer:");
		$pdf->Cell($col2_width, $line_height, $object->ref, 0, 0, 'R');
		$current_y += $line_height;

		// Angebotsnummer (from propal origin)
		$quotation_ref = '-';

		// Step 1: Lookup in element_element table to find linked propal
		$sql = "SELECT fk_source 
        FROM " . MAIN_DB_PREFIX . "element_element 
        WHERE sourcetype = 'propal' 
          AND targettype = 'commande' 
          AND fk_target = " . ((int) $object->id);

		$resql = $this->db->query($sql);
		if ($resql && $this->db->num_rows($resql)) {
			$obj = $this->db->fetch_object($resql);

			// Step 2: Load Propal (Proposal) object
			require_once DOL_DOCUMENT_ROOT . '/comm/propal/class/propal.class.php';
			$propal = new Propal($this->db);

			if ($propal->fetch($obj->fk_source) > 0) {
				$quotation_ref = $propal->ref;
			}
		}

		$pdf->SetXY($box_x + 2, $current_y);
		$pdf->Cell($col1_width, $line_height, "Angebotsnummer:");
		$pdf->Cell($col2_width, $line_height, $quotation_ref, 0, 0, 'R');
		$current_y += $line_height;

		// Ihre Kundennummer
		$pdf->SetXY($box_x + 2, $current_y);
		$pdf->Cell($col1_width, $line_height, "Ihre Kundennummer:");
		$pdf->Cell($col2_width, $line_height, $object->thirdparty->code_client, 0, 0, 'R');
		$current_y += $line_height;

		// Ihre BestellNr (custom field, use your logic here or fallback to ref_client)
		$pdf->SetXY($box_x + 2, $current_y);
		$pdf->Cell($col1_width, $line_height, "Ihre BestellNr:");
		$bestellnr = !empty($object->array_options['options_clientordernumber']) ? $object->array_options['options_clientordernumber'] : '-';
		$pdf->Cell($col2_width, $line_height, $bestellnr, 0, 0, 'R');
		$current_y += $line_height;

		// Auftragsdatum in German date format: DD.MM.YYYY
		$pdf->SetXY($box_x + 2, $current_y);
		$pdf->Cell($col1_width, $line_height, "Auftragsdatum:");
		$pdf->Cell($col2_width, $line_height, dol_print_date($object->date, 'day', '', $outputlangs), 0, 0, 'R');
		// or force format:
		$current_y += $line_height;


		// Move Y cursor for next content
		$posy = $box_y + $box_height + 5;

		$pdf->SetXY($this->marge_gauche, $posy);
		$pdf->SetFont('', '', $default_font_size);

		$paragraph = "Wir danken für Ihren Auftrag, den wir freibleibend entsprechend unserer Verfügbarkeit unter Zugrundelegung unserer allgemeinen Geschäftsbedingungen (einzusehen unter https://www.waldeks.de/agb) bestätigen. Auf Wunsch übersenden wir Ihnen diese auch per Post oder Mail.";

		$width = $this->page_largeur - $this->marge_gauche - $this->marge_droite;
		$line_height = 5;

		$pdf->SetFont('', '', $default_font_size);
		$pdf->SetXY($this->marge_gauche, $posy);

		// 1. Split text into lines using TCPDF’s getNumLines
		$words = explode(' ', $paragraph);
		$lines = [];
		$currentLine = '';

		foreach ($words as $word) {
			$testLine = $currentLine === '' ? $word : $currentLine . ' ' . $word;
			$numLines = $pdf->getNumLines($testLine, $width);

			if ($numLines > 1) {
				// Push current line to stack
				$lines[] = $currentLine;
				$currentLine = $word;
			} else {
				$currentLine = $testLine;
			}
		}

		if (!empty($currentLine)) {
			$lines[] = $currentLine;
		}

		// 2. Render each line
		foreach ($lines as $i => $line) {
			$isLast = ($i === count($lines) - 1);
			$align = $isLast ? 'L' : 'J'; // last line left-aligned, others justified

			$pdf->MultiCell(
				$width,
				$line_height,
				$line,
				0,
				$align,
				false,
				1,
				'',
				'',
				true,
				0,
				false,
				true,
				0,
				'T'
			);
		}



		$w1 = 15;
		$w2 = 15;
		$w3 = 15;
		$w5 = 20;
		$w6 = 20;

		$totalFixedWidth = $w1 + $w2 + $w3 + $w5 + $w6;
		$w4 = $this->page_largeur - $this->marge_gauche - $this->marge_droite - $totalFixedWidth;

		// === Table Header Top Line ===
		// Draw Header
		$this->drawTableHeader($pdf, $default_font_size);


		$pdf->Ln(1);
		$pdf->SetLineWidth(0.2);
		$posy = $pdf->GetY();
		$pdf->SetFont('', '', $default_font_size);
		$pos = 1;

		// === Table Body ===
		foreach ($object->lines as $line) {
			if ($line->subtype != 0)
				continue;

			// Check for page break
			if ($pdf->GetY() > ($this->page_hauteur - $this->marge_basse - 40)) {
				$pdf->AddPage();

				$pdf->SetY($this->marge_haute + 30);

				// === DIN 676 Standard Fold Marks ===
				$pdf->SetDrawColor(0, 0, 0); // black
				$pdf->SetLineWidth(0.6);

				// Left X position (just inside left edge)
				$markerX = $this->marge_gauche - 5;

				// Faltmarke 1 (fold at 87mm)
				$pdf->Line($markerX, 87, $markerX + 2, 87);

				// Faltmarke 2 (fold at 192mm)
				$pdf->Line($markerX, 192, $markerX + 2, 192);

				// Redraw header on new page
				$this->drawTableHeader($pdf, $default_font_size);
			}

			$startX = $this->marge_gauche;
			$startY = $pdf->GetY();

			// ==== DESCRIPTION FIELD (Bezeichnung) ====
			$descX = $startX + $w1 + $w2 + $w3;
			$descWidth = $w4;
			$textWidth = $w4 - 5; // simulate padding-right by limiting text width

			$vertical_padding = 2;

			// 1. Measure text height
			$tmpY = $pdf->GetY();
			$pdf->SetXY($descX, $tmpY + $vertical_padding); // Push text down for top padding
			$pdf->MultiCell($textWidth, 5, $line->desc, 0, 'L');
			$descTextHeight = $pdf->GetY() - ($tmpY + $vertical_padding);

			// 2. Total height includes top and bottom padding
			$descHeight = $descTextHeight + 2 * $vertical_padding;
			$rowHeight = max(6, $descHeight);

			// === Draw other columns ===
			$pdf->SetXY($startX, $startY);
			$pdf->Cell($w1, $rowHeight, $pos++, 0, 0, 'C');
			$pdf->Cell($w2, $rowHeight, $line->qty, 0, 0, 'C');
			$pdf->Cell($w3, $rowHeight, $line->product_ref ?: '-', 0, 0, 'C');

			// Bezeichnung already printed with MultiCell, skip drawing here

			// Einzelpreis & Gesamt
			$pdf->SetXY($descX + $w4, $startY);
			$pdf->Cell($w5 - 2, $rowHeight, price($line->subprice), 0, 0, 'R');
			$pdf->Cell($w6, $rowHeight, price($line->total_ht), 0, 0, 'R');

			// === Draw separator line ===
			$pdf->SetDrawColor(200, 200, 200);
			$pdf->Line($this->marge_gauche, $startY + $rowHeight, $this->page_largeur - $this->marge_droite, $startY + $rowHeight);
			$pdf->SetDrawColor(0, 0, 0);

			// Move to next line
			$pdf->SetY($startY + $rowHeight);
		}


		// === Bold Bottom Line After Table ===
		// === Bold Bottom Line After Table ===
		$pdf->SetLineWidth(0.4);
		// Optional: draw final separator if needed
// $pdf->Line($this->marge_gauche, $posy + 1, $this->page_largeur - $this->marge_droite, $posy + 1);
		$pdf->SetLineWidth(0.2);

		// === Final Table Y Position ===
		$posy = $pdf->GetY();

		// === Space check for footer protection ===
		$safeY = $this->page_hauteur - $this->marge_basse - 30; // Reserve bottom 30mm
		$requiredHeight = 70; // Approx height of totals + Liefertermin + notes

		if ($posy + $requiredHeight > $safeY) {
			$pdf->AddPage();

			// Set Y below header area after page break (adjust offset if needed)
			$header_offset = 35; // 35mm is safe for company logo + contact
			$posy = $this->marge_haute + $header_offset;

			$pdf->SetY($posy);
		} else {
			$posy = $pdf->GetY();
		}

		// === Total Block ===
		$labelWidth = 40;
		$valueWidth = 30;
		$totalRightX = $this->page_largeur - $this->marge_droite;
		$labelX = $totalRightX - $labelWidth - $valueWidth;

		$pdf->SetXY($labelX, $posy);
		$pdf->Cell($labelWidth, 6, "Gesamt Netto", 0, 0, 'R');
		$pdf->Cell($valueWidth - 2, 6, price($object->total_ht), 0, 1, 'R');

		$pdf->SetLineWidth(0.3);
		$pdf->Line($labelX - 10, $pdf->GetY(), $totalRightX, $pdf->GetY());

		$pdf->SetXY($labelX, $pdf->GetY() + 2);
		$pdf->Cell($labelWidth, 6, "Summe MwSt (19,0 % USt)", 0, 0, 'R');
		$pdf->Cell($valueWidth - 2, 6, price($object->total_tva), 0, 1, 'R');

		$pdf->SetFont('', 'B');
		$pdf->SetXY($labelX, $pdf->GetY() + 2);
		$pdf->Cell($labelWidth, 6, "Gesamt Brutto", 0, 0, 'R');
		$pdf->Cell($valueWidth - 2, 6, price($object->total_ttc), 0, 1, 'R');

		$pdf->SetLineWidth(0.4);
		$pdf->Line($labelX - 10, $pdf->GetY() + 2, $totalRightX, $pdf->GetY() + 2);
		$pdf->SetLineWidth(0.2);

		// Update position after totals
		$posy = $pdf->GetY() - 10;

		// === Liefertermin + Notes ===
		$pdf->SetXY($this->marge_gauche, $posy);
		$pdf->SetFont('', '', $default_font_size);
		$note_width = 100;

		// Liefertermin
		$pdf->SetFont('', 'B', $default_font_size);
		$pdf->MultiCell($note_width, 5, "Liefertermin:", 0, 'L');
		$pdf->SetFont('', '', $default_font_size);
		$pdf->MultiCell($note_width, 5, dol_print_date($object->delivery_date, 'day') . " - unter üblichen Vorbehalten", 0, 'L');

		// Spacer
		$pdf->Ln(1);

		// Lieferbedingungen
		$pdf->SetFont('', 'B', $default_font_size);
		$pdf->MultiCell($note_width, 5, "Lieferbedingungen:", 0, 'L');
		$pdf->SetFont('', '', $default_font_size);
		$pdf->MultiCell(
			$note_width,
			5,
			"Die Preise verstehen sich ab Werk ausschl. Verpackung.\n" .
			"Die Ware bleibt bis zur restlosen Bezahlung unser Eigentum.\n" .
			"Erfüllungsort und Gerichtsstand ist Rosenheim",
			0,
			'L'
		);

		// Spacer
		$pdf->Ln(1);

		// Zahlungsbedingungen
		$pdf->SetFont('', 'B', $default_font_size);
		$pdf->MultiCell($note_width, 5, "Zahlungsbedingungen:", 0, 'L');
		$pdf->SetFont('', '', $default_font_size);
		$pdf->MultiCell($note_width, 5, $object->cond_reglement_code ?: '30 Tage netto', 0, 'L');


		// Save
		$dir = $conf->commande->dir_output . "/" . dol_sanitizeFileName($object->ref);
		dol_mkdir($dir);
		$file = $dir . "/" . dol_sanitizeFileName($object->ref) . ".pdf";
		$pdf->Output($file, 'F');

		return 1;
	}

}
