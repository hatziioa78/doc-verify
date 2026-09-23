<?php

declare(strict_types=1);

final class PdfStamper
{
    public static function appendVerificationPage(string $sourcePdf, string $targetPdf, array $doc, string $verifyUrl): void
    {
        if (!defined('K_TCPDF_THROW_EXCEPTION_ERROR')) {
            define('K_TCPDF_THROW_EXCEPTION_ERROR', true);
        }
        $cache = BASE_PATH . '/storage/tmp/';
        if (!is_dir($cache) && !mkdir($cache, 0750, true) && !is_dir($cache)) {
            throw new RuntimeException('Λείπει ο προσωρινός φάκελος.');
        }
        if (!defined('K_PATH_CACHE')) {
            define('K_PATH_CACHE', $cache);
        }

        $stamp = $cache . 'stamp-' . bin2hex(random_bytes(8)) . '.pdf';
        try {
            self::renderStamp($stamp, $doc, $verifyUrl);
            self::concatenate($sourcePdf, $stamp, $targetPdf);
        } finally {
            if (is_file($stamp)) {
                @unlink($stamp);
            }
        }
    }

    private static function renderStamp(string $stampPath, array $doc, string $verifyUrl): void
    {
        $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetCreator('ΣΦΡΑΓΙΣ');
        $pdf->SetAuthor(Settings::headerName());
        $pdf->SetTitle('Ψηφιακή επικύρωση');
        $pdf->SetMargins(18, 18, 18);
        $pdf->SetAutoPageBreak(true, 18);
        $pdf->AddPage();
        $pdf->SetLineStyle(['width' => 0.7, 'color' => [16, 32, 51]]);
        $pdf->Rect(12, 12, 186, 273);
        $pdf->SetLineStyle(['width' => 0.25, 'color' => [184, 137, 61]]);
        $pdf->Rect(14.2, 14.2, 181.6, 268.6);

        $pdf->SetTextColor(16, 32, 51);
        $pdf->SetFont('dejavusans', 'B', 16);
        $pdf->Cell(0, 10, Settings::headerName(), 0, 1, 'C');
        $pdf->SetFont('dejavusans', '', 10);
        $pdf->SetTextColor(110, 84, 38);
        $pdf->Cell(0, 6, 'ΠΑΡΑΡΤΗΜΑ ΨΗΦΙΑΚΗΣ ΕΠΙΚΥΡΩΣΗΣ', 0, 1, 'C');
        $pdf->Ln(2);
        $pdf->SetDrawColor(184, 137, 61);
        $pdf->SetLineWidth(0.4);
        $pdf->Line(28, $pdf->GetY(), 182, $pdf->GetY());
        $pdf->Ln(6);

        $pdf->SetTextColor(16, 32, 51);
        $pdf->SetFont('dejavusans', '', 10);
        $pdf->MultiCell(0, 5.5, 'Η γνησιότητα του εγγράφου επιβεβαιώνεται αποκλειστικά από τον επίσημο σύνδεσμο του μητρώου. Σαρώστε τον κωδικό QR ή ανοίξτε το πλήρες link. Η κατάσταση (ενεργό, ληγμένο, ακυρωμένο) ελέγχεται πάντα ζωντανά και μπορεί να αλλάξει μετά την εκτύπωση αυτής της σελίδας.', 0, 'L');
        $pdf->Ln(3);

        $rows = [
            'Θέμα' => (string) $doc['subject'],
            'Αριθμός πρωτοκόλλου' => (string) $doc['protocol_number'],
            'Εκδούσα αρχή / τμήμα' => (string) $doc['issuing_authority'],
            'Ημερομηνία καταχώρησης' => fmt_dt((string) $doc['registered_at']),
            'Ισχύς έως' => fmt_date((string) $doc['valid_until']),
            'Καταχωρίστηκε από' => (string) ($doc['owner_name'] ?? ''),
        ];
        foreach ($rows as $label => $value) {
            $pdf->SetFont('dejavusans', 'B', 9);
            $pdf->SetTextColor(110, 84, 38);
            $pdf->Cell(58, 6, $label, 0, 0, 'L');
            $pdf->SetFont('dejavusans', '', 10);
            $pdf->SetTextColor(16, 32, 51);
            $pdf->MultiCell(0, 6, $value !== '' ? $value : '—', 0, 'L');
        }

        $pdf->Ln(2);
        $pdf->SetFont('dejavusans', 'B', 9);
        $pdf->SetTextColor(110, 84, 38);
        $pdf->Cell(0, 6, 'Πληροφορίες', 0, 1);
        $pdf->SetFont('dejavusans', '', 10);
        $pdf->SetTextColor(16, 32, 51);
        $info = trim((string) ($doc['info'] ?? ''));
        if (mb_strlen($info) > 900) {
            $info = mb_substr($info, 0, 900) . '…';
        }
        $pdf->MultiCell(0, 5.2, $info !== '' ? $info : '—', 0, 'L');
        $pdf->Ln(4);

        $pdf->SetFont('dejavusans', '', 8);
        $pdf->SetTextColor(70, 80, 92);
        $pdf->MultiCell(0, 4.2, 'Αποτύπωμα αρχικού PDF (SHA-256): ' . (string) $doc['sha256'], 0, 'L');
        $pdf->Ln(2);

        if ($pdf->GetY() > 210) {
            $pdf->AddPage();
            $pdf->SetLineStyle(['width' => 0.7, 'color' => [16, 32, 51]]);
            $pdf->Rect(12, 12, 186, 273);
        }

        $qrY = $pdf->GetY();
        $pdf->write2DBarcode($verifyUrl, 'QRCODE,H', 82, $qrY, 46, 46, [
            'border' => false,
            'fgcolor' => [16, 32, 51],
            'bgcolor' => [255, 255, 255],
            'padding' => 1,
        ], 'N');
        $pdf->SetY($qrY + 48);
        $pdf->SetFont('dejavusans', '', 8);
        $pdf->SetTextColor(16, 32, 51);
        $pdf->MultiCell(0, 4, $verifyUrl, 0, 'C');
        $pdf->Ln(2);
        $pdf->SetTextColor(90, 96, 104);
        $pdf->SetFont('dejavusans', 'I', 8);
        $pdf->MultiCell(0, 4, 'Ο σύνδεσμος είναι μοναδικός για αυτό το έγγραφο. Τυχαίες διευθύνσεις δεν οδηγούν σε καταχώρηση.', 0, 'C');

        $pdf->Output($stampPath, 'F');
    }

    private static function concatenate(string $sourcePdf, string $stampPdf, string $targetPdf): void
    {
        $command = ['qpdf', '--warning-exit-0', '--empty', '--pages', $sourcePdf, $stampPdf, '--', $targetPdf];
        $descriptors = [
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $process = proc_open($command, $descriptors, $pipes, null, null, ['bypass_shell' => true]);
        if (!is_resource($process)) {
            throw new RuntimeException('Δεν ήταν δυνατή η προσθήκη της σελίδας επικύρωσης. Ελέγξτε ότι το qpdf είναι εγκατεστημένο.');
        }
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $code = proc_close($process);
        if ($code !== 0 || !is_file($targetPdf) || filesize($targetPdf) < 64) {
            $detail = trim($stdout . "\n" . $stderr);
            if (stripos($detail, 'password') !== false || stripos($detail, 'encrypt') !== false) {
                throw new RuntimeException('Το PDF είναι κλειδωμένο με κωδικό και δεν μπορεί να επικυρωθεί.');
            }
            throw new RuntimeException('Το PDF δεν μπόρεσε να σφραγιστεί. Χρησιμοποιήστε ένα έγκυρο, μη κλειδωμένο αρχείο PDF.');
        }
    }
}
