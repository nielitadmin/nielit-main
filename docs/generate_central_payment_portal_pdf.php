<?php
/**
 * Generate PDF: Central Payment Portal (payment.nielitbhubaneswar.in)
 * CLI:  php docs/generate_central_payment_portal_pdf.php
 * Web:  /docs/generate_central_payment_portal_pdf.php
 */

require_once __DIR__ . '/../libraries/tcpdf/tcpdf.php';

$outputDir = __DIR__ . '/payment-portal';
if (!is_dir($outputDir)) {
    mkdir($outputDir, 0755, true);
}
$outputFile = $outputDir . '/NIELIT_Central_Payment_Portal_Report.pdf';

class PaymentPortalReportPDF extends TCPDF
{
    public function Header()
    {
        $this->SetFont('helvetica', 'B', 10);
        $this->SetTextColor(26, 38, 64);
        $this->Cell(0, 8, 'NIELIT Bhubaneswar — Central Payment Portal', 0, false, 'L');
        $this->SetFont('helvetica', '', 8);
        $this->Cell(0, 8, date('d M Y'), 0, false, 'R');
        $this->Ln(10);
        $this->SetDrawColor(26, 86, 219);
        $this->SetLineWidth(0.4);
        $this->Line(15, 18, 195, 18);
    }

    public function Footer()
    {
        $this->SetY(-15);
        $this->SetFont('helvetica', 'I', 8);
        $this->SetTextColor(100, 100, 100);
        $this->Cell(0, 10, 'Page ' . $this->getAliasNumPage() . ' of ' . $this->getAliasNbPages(), 0, false, 'C');
    }

    public function flowBox($x, $y, $w, $h, $text, $fill = [240, 249, 255], $border = [26, 86, 219], $fontSize = 8)
    {
        $this->SetFillColor($fill[0], $fill[1], $fill[2]);
        $this->SetDrawColor($border[0], $border[1], $border[2]);
        $this->SetLineWidth(0.4);
        $this->RoundedRect($x, $y, $w, $h, 2, '1111', 'DF');
        $this->SetTextColor(26, 38, 64);
        $this->SetFont('helvetica', '', $fontSize);
        $this->setXY($x + 2, $y + 2);
        $this->MultiCell($w - 4, $h - 4, $text, 0, 'C', false, 1, '', '', true, 0, false, true, $h - 4, 'M');
    }

    public function arrowDown($x, $y1, $y2)
    {
        $this->SetDrawColor(107, 114, 128);
        $this->SetLineWidth(0.35);
        $this->Line($x, $y1, $x, $y2 - 3);
        $this->Polygon([$x, $y2, $x - 2, $y2 - 4, $x + 2, $y2 - 4], 'F');
    }

    public function arrowRight($x1, $y, $x2)
    {
        $this->SetDrawColor(107, 114, 128);
        $this->SetLineWidth(0.35);
        $this->Line($x1, $y, $x2 - 3, $y);
        $this->Polygon([$x2, $y, $x2 - 4, $y - 2, $x2 - 4, $y + 2], 'F');
    }

    public function entityBox($x, $y, $w, $title, $fields, $color = [10, 22, 40])
    {
        $lineH = 5;
        $h = 8 + count($fields) * $lineH;
        $this->SetFillColor($color[0], $color[1], $color[2]);
        $this->Rect($x, $y, $w, 7, 'F');
        $this->SetDrawColor(26, 86, 219);
        $this->SetLineWidth(0.4);
        $this->Rect($x, $y, $w, $h, 'D');
        $this->SetTextColor(255, 255, 255);
        $this->SetFont('helvetica', 'B', 8);
        $this->setXY($x + 2, $y + 1.5);
        $this->Cell($w - 4, 5, $title, 0, 0, 'C');
        $this->SetTextColor(31, 41, 55);
        $this->SetFont('helvetica', '', 7);
        $fy = $y + 8;
        foreach ($fields as $f) {
            $this->setXY($x + 2, $fy);
            $this->Cell($w - 4, $lineH, $f, 0, 0, 'L');
            $fy += $lineH;
        }
        return $h;
    }

    public function relationLine($x1, $y1, $x2, $y2, $label = '')
    {
        $this->SetDrawColor(107, 114, 128);
        $this->SetLineWidth(0.3);
        $this->Line($x1, $y1, $x2, $y2);
        if ($label !== '') {
            $mx = ($x1 + $x2) / 2;
            $my = ($y1 + $y2) / 2;
            $this->SetFont('helvetica', 'I', 6);
            $this->SetTextColor(75, 85, 99);
            $this->setXY($mx - 8, $my - 3);
            $this->Cell(16, 4, $label, 0, 0, 'C');
        }
    }

    public function sectionTitle($title)
    {
        $this->SetFont('helvetica', 'B', 13);
        $this->SetTextColor(10, 22, 40);
        $this->Cell(0, 9, $title, 0, 1, 'L');
        $this->SetDrawColor(26, 86, 219);
        $this->Line(15, $this->GetY(), 95, $this->GetY());
        $this->Ln(4);
    }

    public function bodyText($text)
    {
        $this->SetFont('helvetica', '', 9);
        $this->SetTextColor(51, 65, 85);
        $this->MultiCell(0, 5, $text, 0, 'L');
        $this->Ln(2);
    }

    public function tableBlock(array $headers, array $rows, array $widths)
    {
        $this->SetFont('helvetica', 'B', 8);
        $this->SetFillColor(248, 250, 252);
        $this->SetTextColor(30, 41, 59);
        foreach ($headers as $i => $h) {
            $w = $widths[$i] ?? 40;
            $this->Cell($w, 7, $h, 1, 0, 'L', true);
        }
        $this->Ln();
        $this->SetFont('helvetica', '', 7.5);
        $fill = false;
        foreach ($rows as $row) {
            $startY = $this->GetY();
            if ($startY > 265) {
                $this->AddPage();
                $startY = $this->GetY();
            }
            $startX = $this->GetX();
            $maxH = 7;
            foreach ($row as $i => $cell) {
                $w = $widths[$i] ?? 40;
                $maxH = max($maxH, $this->getStringHeight($w, (string) $cell) + 2);
            }
            foreach ($row as $i => $cell) {
                $w = $widths[$i] ?? 40;
                $this->SetXY($startX, $startY);
                $ln = ($i === count($row) - 1) ? 1 : 0;
                $this->MultiCell($w, $maxH, (string) $cell, 1, 'L', $fill, $ln);
                $startX += $w;
            }
            $fill = !$fill;
        }
        $this->Ln(3);
    }
}

function drawCover(PaymentPortalReportPDF $pdf)
{
    $pdf->SetFont('helvetica', 'B', 22);
    $pdf->SetTextColor(10, 22, 40);
    $pdf->Ln(22);
    $pdf->Cell(0, 12, 'NIELIT Bhubaneswar', 0, 1, 'C');
    $pdf->SetFont('helvetica', '', 14);
    $pdf->SetTextColor(26, 86, 219);
    $pdf->Cell(0, 10, 'Central Payment Portal', 0, 1, 'C');
    $pdf->SetFont('helvetica', 'B', 15);
    $pdf->SetTextColor(55, 65, 81);
    $pdf->Cell(0, 10, 'Architecture, Integration & Implementation Report', 0, 1, 'C');
    $pdf->Ln(6);
    $pdf->SetFont('helvetica', '', 10);
    $pdf->SetTextColor(107, 114, 128);
    $pdf->MultiCell(0, 6, "Portal URL: https://payment.nielitbhubaneswar.in\nGateway: Razorpay (single integration point)\nDate: " . date('d F Y'), 0, 'C');
    $pdf->Ln(8);
    $pdf->SetFillColor(240, 249, 255);
    $pdf->Rect(35, $pdf->GetY(), 140, 42, 'F');
    $pdf->SetFont('helvetica', '', 9);
    $pdf->SetTextColor(26, 38, 64);
    $pdf->setXY(40, $pdf->GetY() + 5);
    $pdf->MultiCell(130, 5, "• One portal for MIS, mock test, and future sites\n• Central invoices and payment register\n• Websites never call Razorpay directly\n• Signed server-to-server checkout API\n• Webhook-confirmed payments only", 0, 'L');
}

function drawArchitectureDiagram(PaymentPortalReportPDF $pdf)
{
    $pdf->AddPage();
    $pdf->sectionTitle('Diagram 1 — Centralized architecture');
    $pdf->bodyText('Every NIELIT Bhubaneswar website sends payers to the payment portal. Only the portal holds Razorpay credentials and issues invoices.');

    $pdf->flowBox(12, 38, 52, 16, "MIS Portal\nnielitbhubaneswar.in\nCourse / workshop fees", [219, 234, 254]);
    $pdf->flowBox(72, 38, 52, 16, "Mock Test Site\nMock / re-attempt fees", [219, 234, 254]);
    $pdf->flowBox(132, 38, 52, 16, "Future sites\nHostel, events,\nrecruitment", [243, 244, 246], [156, 163, 175]);

    $pdf->arrowDown(38, 54, 62);
    $pdf->arrowDown(98, 54, 62);
    $pdf->arrowDown(158, 54, 62);

    $pdf->flowBox(45, 64, 110, 22, "payment.nielitbhubaneswar.in\nCheckout API • Pay page • Webhooks\nInvoices • Admin register • Receipt PDF", [237, 233, 254], [109, 40, 217], 8);

    $pdf->arrowDown(100, 86, 96);
    $pdf->flowBox(55, 98, 90, 14, "Razorpay\nOrders • UPI / Card / Netbanking\nSettlement & refunds", [255, 251, 235], [245, 158, 11]);

    $pdf->SetFont('helvetica', 'B', 8);
    $pdf->SetTextColor(220, 38, 38);
    $pdf->setXY(15, 118);
    $pdf->MultiCell(180, 5, 'Razorpay Key Secret stays ONLY on the payment server — not on MIS or mock test.', 0, 'L');
}

function drawBeforeAfter(PaymentPortalReportPDF $pdf)
{
    $pdf->sectionTitle('Diagram 2 — Before vs after');
    $pdf->flowBox(15, 48, 82, 28, "TODAY (per site)\nEach site embeds Razorpay Checkout\nKeys copied to MIS, mock, etc.\nSeparate invoice logic\nHard to audit centrally", [254, 226, 226], [220, 38, 38], 7.5);
    $pdf->flowBox(108, 48, 82, 28, "TARGET (central hub)\nSite calls payment portal API\nPortal opens Razorpay once\nOne invoice series NIELIT-BBSR-FY-######\nOffice sees all sources in one register", [220, 252, 231], [22, 163, 74], 7.5);
    $pdf->SetFont('helvetica', '', 8);
    $pdf->SetTextColor(51, 65, 85);
    $pdf->setXY(15, 82);
    $pdf->MultiCell(180, 5, 'Current MIS code: includes/payment_gateway_helper.php and student/fees.php. After cutover, MIS redirects to the hub and removes local Razorpay keys.', 0, 'L');
}

function drawPaymentFlow(PaymentPortalReportPDF $pdf)
{
    $pdf->AddPage();
    $pdf->sectionTitle('Diagram 3 — End-to-end payment flow');
    $x = 105;
    $y = 32;
    $steps = [
        'Student clicks Pay on MIS or mock test',
        'Source site (server) POST /api/v1/checkout\nSigned JSON + amount in paise',
        'Portal returns pay link\n/pay/{token}',
        'Browser on payment.nielitbhubaneswar.in\nPurpose + amount shown',
        'Razorpay Checkout opens',
        'Return signature verified\nWebhook payment.captured',
        'Invoice NIELIT-BBSR-2026-27-######\nNotify source site (signed)',
        'Student back on MIS / mock\nCourse or test unlocked',
    ];
    foreach ($steps as $i => $text) {
        $pdf->flowBox(55, $y, 100, 12, ($i + 1) . '. ' . $text, [240, 249, 255], [26, 86, 219], 7);
        if ($i < count($steps) - 1) {
            $pdf->arrowDown($x, $y + 12, $y + 16);
        }
        $y += 16;
    }
}

function drawSequenceDiagram(PaymentPortalReportPDF $pdf)
{
    $pdf->AddPage();
    $pdf->sectionTitle('Diagram 4 — Server interactions (sequence)');
    $cols = [
        ['MIS / Mock', 22],
        ['Payment portal', 82],
        ['Razorpay', 142],
    ];
    $pdf->SetFont('helvetica', 'B', 8);
    foreach ($cols as $c) {
        $pdf->SetFillColor(248, 250, 252);
        $pdf->Rect($c[1], 32, 48, 8, 'DF');
        $pdf->setXY($c[1], 33);
        $pdf->Cell(48, 6, $c[0], 0, 0, 'C');
    }
    $messages = [
        [35, '1. POST checkout (signed)', 82],
        [45, '2. Create order', 142],
        [55, '3. order_id + checkout', 82],
        [65, '4. Redirect payer to /pay/', 22],
        [75, '5. payment + signature', 82],
        [85, '6. webhook captured', 82],
        [95, '7. POST notify_url (signed)', 22],
    ];
    $pdf->SetFont('helvetica', '', 7);
    foreach ($messages as $m) {
        $y = $m[0];
        $pdf->setXY(15, $y);
        $pdf->Cell(165, 4, $m[1], 0, 0, 'L');
        $pdf->SetDrawColor(26, 86, 219);
        $pdf->Line($m[2] + 24, 36, $m[2] + 24, 100);
    }
    $pdf->SetFont('helvetica', 'I', 8);
    $pdf->SetTextColor(75, 85, 99);
    $pdf->setXY(15, 108);
    $pdf->MultiCell(180, 5, 'The source website marks a fee as paid only after step 7 (signed notify) or after querying GET /api/v1/payments/{id} with status paid.', 0, 'L');
}

function drawErDiagram(PaymentPortalReportPDF $pdf)
{
    $pdf->AddPage();
    $pdf->sectionTitle('Diagram 5 — Payment portal database (ER)');

    $h1 = $pdf->entityBox(15, 38, 48, 'payment_sources', [
        'id (PK)',
        'code (mis, mock)',
        'display_name',
        'return_url_prefix',
        'shared_secret_hash',
        'is_active',
    ]);

    $h2 = $pdf->entityBox(78, 35, 52, 'hub_checkouts', [
        'id (PK)',
        'source_id (FK)',
        'token (UK)',
        'amount_paise',
        'status',
        'expires_at',
    ]);

    $h3 = $pdf->entityBox(141, 38, 48, 'hub_payments', [
        'id (PK)',
        'checkout_id (FK)',
        'source_reference',
        'payer fields',
        'razorpay_order_id',
        'razorpay_payment_id',
        'status',
    ]);

    $h4 = $pdf->entityBox(50, 88, 52, 'hub_invoices', [
        'id (PK)',
        'payment_id (FK)',
        'invoice_number (UK)',
        'fy_label',
        'pdf_path',
        'issued_at',
    ]);

    $h5 = $pdf->entityBox(115, 88, 52, 'webhook_events', [
        'id (PK)',
        'event_id (UK)',
        'payload_json',
        'processed_at',
    ]);

    $pdf->relationLine(63, 55, 78, 52, '1:N');
    $pdf->relationLine(130, 50, 141, 50, '1:1');
    $pdf->relationLine(104, 35 + $h2, 76, 88, '1:1');
    $pdf->relationLine(127, 100, 115, 100);
    $pdf->relationLine(165, 38 + $h3, 141, 88);

    $pdf->SetFont('helvetica', 'B', 8);
    $pdf->SetTextColor(26, 86, 219);
    $pdf->setXY(15, 118);
    $pdf->MultiCell(180, 5, 'Database name suggestion: nielit_payments (separate from MIS nielit_bhubaneswar).', 0, 'L');
}

function drawSecurityDiagram(PaymentPortalReportPDF $pdf)
{
    $pdf->AddPage();
    $pdf->sectionTitle('Diagram 6 — Security layers');
    $layers = [
        ['Layer 1', 'HTTPS only on payment.nielitbhubaneswar.in'],
        ['Layer 2', 'HMAC signature on checkout request (X-Payment-Signature)'],
        ['Layer 3', 'Return URL whitelist per registered source'],
        ['Layer 4', 'One-time checkout token with expiry (e.g. 30 min)'],
        ['Layer 5', 'Razorpay return signature verification'],
        ['Layer 6', 'Webhook secret + idempotent event_id storage'],
        ['Layer 7', 'Signed notify to MIS / mock before local “paid” flag'],
    ];
    $y = 38;
    foreach ($layers as $layer) {
        $pdf->flowBox(25, $y, 160, 10, $layer[0] . ': ' . $layer[1], [240, 249, 255], [26, 86, 219], 7.5);
        $y += 13;
    }
}

function drawAdminModules(PaymentPortalReportPDF $pdf)
{
    $pdf->AddPage();
    $pdf->sectionTitle('Diagram 7 — Admin portal modules');
    $mods = [
        ['Today dashboard', 'Paid / failed / pending by source'],
        ['Payment register', 'Filter: date, source, centre, invoice, Razorpay id'],
        ['Invoices', 'PDF download + email link to payer'],
        ['Sources', 'Register MIS, mock test; rotate secrets'],
        ['Gateway settings', 'Razorpay keys & webhook (restricted role)'],
        ['Export', 'CSV for accounts / Tally'],
    ];
    $x = 15;
    $y = 40;
    $i = 0;
    foreach ($mods as $m) {
        $pdf->flowBox($x, $y, 58, 18, $m[0] . "\n" . $m[1], [236, 253, 245], [16, 185, 129], 6.5);
        $x += 62;
        $i++;
        if ($i % 3 === 0) {
            $x = 15;
            $y += 22;
        }
    }
    $pdf->SetFont('helvetica', '', 9);
    $pdf->SetTextColor(51, 65, 85);
    $pdf->setXY(15, 95);
    $pdf->MultiCell(180, 5, 'Answers for accounts: Where was the transaction? → source website + return host. To whom? → payer name, email, phone, external student id from that site.', 0, 'L');
}

function drawPhases(PaymentPortalReportPDF $pdf)
{
    $pdf->sectionTitle('Diagram 8 — Implementation phases');
    $phases = [
        'P1' => "Portal host\nDB + checkout API\nRazorpay + webhook\nInvoice PDF",
        'P2' => "MIS integration\nReplace fees.php\nNotify + return",
        'P3' => "Mock test +\nother sources",
        'P4' => "CSV export\nGST on invoice\nLegacy import\nRefunds / CN",
    ];
    $px = 15;
    $py = $pdf->GetY() + 2;
    $keys = array_keys($phases);
    foreach ($keys as $idx => $k) {
        $pdf->flowBox($px, $py, 42, 22, $k . "\n" . $phases[$k], [255, 251, 235], [245, 158, 11], 6.5);
        if ($idx < count($keys) - 1) {
            $pdf->arrowRight($px + 42, $py + 11, $px + 48);
        }
        $px += 46;
    }
    $pdf->SetFont('helvetica', 'B', 8);
    $pdf->SetTextColor(220, 38, 38);
    $pdf->setXY(15, $py + 28);
    $pdf->Cell(0, 5, 'Do not disable current MIS Razorpay until Phase 2 completes one live payment.', 0, 1);
}

function writeDetailedSections(PaymentPortalReportPDF $pdf)
{
    $pdf->AddPage();
    $pdf->sectionTitle('1. Executive summary');
    $pdf->bodyText(
        'NIELIT Bhubaneswar can operate one payment hub at payment.nielitbhubaneswar.in. MIS, the mock test website, and any future property redirect payers there. The hub integrates with Razorpay once, stores every transaction, issues a single invoice number series, and notifies each source site when money is captured. This is technically feasible with a signed REST API and is aligned with how the MIS already stores online_course_payments today.'
    );

    $pdf->sectionTitle('2. Connected websites');
    $pdf->tableBlock(
        ['Source code', 'Website', 'Payment examples'],
        [
            ['mis', 'nielitbhubaneswar.in', 'Course fee, workshop fee'],
            ['mock', 'Mock test portal', 'Mock test fee, re-attempt'],
            ['(future)', 'New NIELIT sites', 'Hostel, recruitment, events'],
        ],
        [28, 52, 100]
    );

    $pdf->sectionTitle('3. Checkout API (summary)');
    $pdf->bodyText('POST https://payment.nielitbhubaneswar.in/api/v1/checkout with signed JSON body. Required fields: source, reference, amount (paise), currency, purpose, description, payer {name, email, phone, external_id}, context {centre, course_id, ...}, return_url, notify_url.');
    $pdf->tableBlock(
        ['Field', 'Example'],
        [
            ['source', 'mis'],
            ['reference', 'MIS-FEE-18422'],
            ['amount', '50000 (= Rs 500.00)'],
            ['return_url', 'https://nielitbhubaneswar.in/student/payment_return.php'],
            ['notify_url', 'https://nielitbhubaneswar.in/api/payment_hub_notify.php'],
        ],
        [45, 135]
    );

    $pdf->AddPage();
    $pdf->sectionTitle('4. Checkout and payment status');
    $pdf->tableBlock(
        ['Status', 'Meaning'],
        [
            ['created', 'Payment link issued'],
            ['attempted', 'Razorpay order created'],
            ['paid', 'Captured; invoice issued'],
            ['failed', 'Razorpay failure'],
            ['cancelled', 'User closed checkout'],
            ['expired', 'Link past allowed time'],
        ],
        [35, 145]
    );

    $pdf->sectionTitle('5. Invoice series');
    $pdf->bodyText('Format: NIELIT-BBSR-{financial year}-{6 digits}. Example: NIELIT-BBSR-2026-27-000184. Financial year April–March (same as Report Monitor). Credit notes: NIELIT-BBSR-CN-2026-27-000012 linked to original invoice.');

    $pdf->sectionTitle('6. MIS changes after go-live');
    $pdf->tableBlock(
        ['#', 'Change'],
        [
            ['1', 'student/fees.php redirects to hub; no checkout.razorpay.com on MIS'],
            ['2', 'Remove Razorpay secrets from MIS admin (or show hub-only message)'],
            ['3', 'online_course_payments + hub_payment_id, hub_invoice_number'],
            ['4', 'api/payment_hub_notify.php verifies signed paid callback'],
            ['5', 'payment_return.php polls hub status before unlock'],
            ['6', 'Optional legacy import of old paid rows into hub register'],
        ],
        [12, 168]
    );

    $pdf->AddPage();
    $pdf->sectionTitle('7. Razorpay configuration');
    $pdf->tableBlock(
        ['Setting', 'Value'],
        [
            ['Webhook URL', 'https://payment.nielitbhubaneswar.in/api/v1/razorpay/webhook'],
            ['Events', 'payment.captured, payment.failed, refund.processed'],
            ['Order notes', 'portal_payment_id, source, source_reference'],
            ['Keys storage', 'Payment portal server only'],
        ],
        [50, 130]
    );

    $pdf->sectionTitle('8. Hosting');
    $pdf->tableBlock(
        ['Item', 'Recommendation'],
        [
            ['DNS', 'payment.nielitbhubaneswar.in → dedicated app'],
            ['Database', 'MySQL nielit_payments'],
            ['PHP', 'Same major version as production MIS'],
            ['Secrets', 'Environment variables / outside web root'],
            ['Backup', 'Daily DB + invoice PDF storage'],
        ],
        [45, 135]
    );

    $pdf->sectionTitle('9. Decisions before build');
    $pdf->tableBlock(
        ['#', 'Question for accounts / IT'],
        [
            ['1', 'One Razorpay merchant for all sites, or per centre?'],
            ['2', 'Invoice prefix final text and GST on PDF?'],
            ['3', 'Who gets payment admin login?'],
            ['4', 'First live integration after MIS: mock test or other?'],
        ],
        [12, 168]
    );

    $pdf->sectionTitle('10. Related document');
    $pdf->bodyText('Full markdown specification: docs/payment-portal/CENTRAL_PAYMENT_PORTAL.md. This PDF is generated by docs/generate_central_payment_portal_pdf.php.');
}

$pdf = new PaymentPortalReportPDF('P', 'mm', 'A4', true, 'UTF-8', false);
$pdf->SetCreator('NIELIT Bhubaneswar');
$pdf->SetAuthor('NIELIT Admin System');
$pdf->SetTitle('Central Payment Portal — Complete Report');
$pdf->SetMargins(15, 25, 15);
$pdf->SetAutoPageBreak(true, 20);

$pdf->AddPage();
drawCover($pdf);
drawArchitectureDiagram($pdf);
drawBeforeAfter($pdf);
drawPaymentFlow($pdf);
drawSequenceDiagram($pdf);
drawErDiagram($pdf);
drawSecurityDiagram($pdf);
drawAdminModules($pdf);
$pdf->AddPage();
drawPhases($pdf);
writeDetailedSections($pdf);

$pdf->Output($outputFile, 'F');

if (php_sapi_name() === 'cli') {
    echo "PDF created: $outputFile\n";
} else {
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="NIELIT_Central_Payment_Portal_Report.pdf"');
    readfile($outputFile);
    exit;
}
