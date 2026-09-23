<?php

declare(strict_types=1);

final class PdfStamper
{
    public static function appendVerificationPage(string $sourcePdf, string $targetPdf, array $doc, string $verifyUrl): void
    {
        self::prepareTcpdf();
        $placement = Settings::stampPlacement();
        if ($placement === 'header' || $placement === 'footer') {
            self::overlayEdge($sourcePdf, $targetPdf, $doc, $verifyUrl, $placement);
            return;
        }
        $stamp = self::tempPath('stamp');
        try {
            self::renderAppendix($stamp, $doc, $verifyUrl);
            self::runQpdf(['qpdf', '--warning-exit-0', '--empty', '--pages', $sourcePdf, $stamp, '--', $targetPdf]);
            self::assertOutput($targetPdf);
        } finally {
            self::unlinkQuiet($stamp);
        }
    }

    private static function overlayEdge(string $sourcePdf, string $targetPdf, array $doc, string $verifyUrl, string $edge): void
    {
        $flat = self::tempPath('flat');
        $overlay = self::tempPath('overlay');
        try {
            self::runQpdf(['qpdf', '--warning-exit-0', '--flatten-rotation', $sourcePdf, $flat]);
            $pages = self::pageBoxes($flat);
            self::renderEdge($overlay, $pages, $doc, $verifyUrl, $edge);
            self::runQpdf(['qpdf', '--warning-exit-0', '--overlay', $overlay, '--', $flat, $targetPdf]);
            self::assertOutput($targetPdf);
        } finally {
            self::unlinkQuiet($flat);
            self::unlinkQuiet($overlay);
        }
    }

    private static function renderAppendix(string $stampPath, array $doc, string $verifyUrl): void
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

    private static function renderEdge(string $overlayPath, array $pages, array $doc, string $verifyUrl, string $edge): void
    {
        $lines = [
            ['bold' => true, 'text' => 'Ψηφιακή σφραγίδα · ' . Settings::headerName()],
            ['bold' => false, 'text' => 'Θέμα: ' . (string) $doc['subject']],
            ['bold' => false, 'text' => 'Πρωτ. ' . (string) $doc['protocol_number'] . ' · ' . (string) $doc['issuing_authority']],
            ['bold' => false, 'text' => 'Καταχώρηση ' . fmt_dt((string) $doc['registered_at']) . ' · Ισχύς έως ' . fmt_date((string) $doc['valid_until']) . ' · ' . (string) ($doc['owner_name'] ?? '')],
        ];
        $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetCreator('ΣΦΡΑΓΙΣ');
        $pdf->SetAuthor(Settings::headerName());
        $pdf->SetTitle('Ψηφιακή επικύρωση');
        $pdf->SetMargins(0, 0, 0);
        $pdf->SetAutoPageBreak(false, 0);
        $pdf->setCellPaddings(0, 0, 0, 0);
        foreach ($pages as $page) {
            $w = $page['width'];
            $h = $page['height'];
            $pdf->AddPage($w >= $h ? 'L' : 'P', [$w, $h]);
            self::drawBand($pdf, $w, $h, $page, $edge, $lines, $verifyUrl);
        }
        $pdf->Output($overlayPath, 'F');
    }

    private static function drawBand(TCPDF $pdf, float $width, float $height, array $page, string $edge, array $lines, string $verifyUrl): void
    {
        $cropW = max(10.0, $width - $page['left'] - $page['right']);
        $cropH = max(10.0, $height - $page['top'] - $page['bottom']);
        $band = min(18.0, max(11.0, $cropH * 0.22));
        $x = $page['left'];
        $y = $edge === 'header' ? $page['top'] : max($page['top'], $height - $page['bottom'] - $band);
        $pdf->SetFillColor(255, 255, 255);
        $pdf->Rect($x, $y, $cropW, $band, 'F');
        $pdf->SetDrawColor(184, 137, 61);
        $pdf->SetLineWidth(0.2);
        $lineY = $edge === 'header' ? $y + $band : $y;
        $pdf->Line($x, $lineY, $x + $cropW, $lineY);

        $qr = min(14.0, $band - 2.2);
        $qrX = $x + 1.3;
        $qrY = $y + ($band - $qr) / 2;
        $pdf->write2DBarcode($verifyUrl, 'QRCODE,M', $qrX, $qrY, $qr, $qr, [
            'border' => false,
            'fgcolor' => [16, 32, 51],
            'bgcolor' => [255, 255, 255],
            'padding' => 0,
        ], 'N');

        $textX = $qrX + $qr + 1.6;
        $textW = max(8.0, $x + $cropW - $textX - 1.2);
        $lineH = min(3.2, ($band - 1.6) / count($lines));
        $textY = $y + max(0.6, ($band - $lineH * count($lines)) / 2);
        foreach ($lines as $i => $line) {
            $pdf->SetFont('dejavusans', $line['bold'] ? 'B' : '', 6);
            $pdf->SetTextColor($line['bold'] ? 110 : 16, $line['bold'] ? 84 : 32, $line['bold'] ? 38 : 51);
            $pdf->SetXY($textX, $textY + ($i * $lineH));
            $pdf->Cell($textW, $lineH, self::fit($pdf, $line['text'], $textW), 0, 0, 'L');
        }
    }

    private static function fit(TCPDF $pdf, string $text, float $width): string
    {
        $text = preg_replace('/\s+/u', ' ', trim($text)) ?? '';
        if ($text === '' || $pdf->GetStringWidth($text) <= $width) {
            return $text;
        }
        $ellipsis = '…';
        $low = 0;
        $high = mb_strlen($text);
        while ($low < $high) {
            $mid = intdiv($low + $high + 1, 2);
            $candidate = mb_substr($text, 0, $mid) . $ellipsis;
            if ($pdf->GetStringWidth($candidate) <= $width) {
                $low = $mid;
            } else {
                $high = $mid - 1;
            }
        }
        return $low === 0 ? $ellipsis : mb_substr($text, 0, $low) . $ellipsis;
    }

    /** @return list<array{width: float, height: float, top: float, bottom: float, left: float, right: float}> */
    private static function pageBoxes(string $pdfPath): array
    {
        $raw = self::runQpdf(['qpdf', '--warning-exit-0', '--json', $pdfPath]);
        $data = json_decode($raw, true);
        if (!is_array($data) || !isset($data['pages'], $data['qpdf'][1]) || !is_array($data['pages'])) {
            throw new RuntimeException('Το PDF δεν μπόρεσε να σφραγιστεί. Χρησιμοποιήστε ένα έγκυρο, μη κλειδωμένο αρχείο PDF.');
        }
        $objects = $data['qpdf'][1];
        $pages = [];
        foreach ($data['pages'] as $page) {
            if (!is_array($page) || !isset($page['object'])) {
                continue;
            }
            $dict = $objects['obj:' . $page['object']]['value'] ?? null;
            if (!is_array($dict)) {
                continue;
            }
            $media = self::boxOf($objects, self::inherited($objects, $dict, '/MediaBox'));
            if ($media === null) {
                throw new RuntimeException('Το PDF δεν μπόρεσε να σφραγιστεί. Χρησιμοποιήστε ένα έγκυρο, μη κλειδωμένο αρχείο PDF.');
            }
            $crop = self::boxOf($objects, self::inherited($objects, $dict, '/CropBox')) ?? $media;
            $scale = 25.4 / 72;
            $width = ($media['urx'] - $media['llx']) * $scale;
            $height = ($media['ury'] - $media['lly']) * $scale;
            if ($width < 20 || $height < 20) {
                throw new RuntimeException('Το PDF δεν μπόρεσε να σφραγιστεί. Χρησιμοποιήστε ένα έγκυρο, μη κλειδωμένο αρχείο PDF.');
            }
            $pages[] = [
                'width' => $width,
                'height' => $height,
                'left' => max(0.0, ($crop['llx'] - $media['llx']) * $scale),
                'right' => max(0.0, ($media['urx'] - $crop['urx']) * $scale),
                'top' => max(0.0, ($media['ury'] - $crop['ury']) * $scale),
                'bottom' => max(0.0, ($crop['lly'] - $media['lly']) * $scale),
            ];
        }
        if ($pages === []) {
            throw new RuntimeException('Το PDF δεν μπόρεσε να σφραγιστεί. Χρησιμοποιήστε ένα έγκυρο, μη κλειδωμένο αρχείο PDF.');
        }
        return $pages;
    }

    private static function inherited(array $objects, array $dict, string $key, int $depth = 0): mixed
    {
        if (array_key_exists($key, $dict)) {
            return $dict[$key];
        }
        if ($depth > 6 || !isset($dict['/Parent']) || !is_string($dict['/Parent'])) {
            return null;
        }
        $parent = $objects['obj:' . $dict['/Parent']]['value'] ?? null;
        if (!is_array($parent)) {
            return null;
        }
        return self::inherited($objects, $parent, $key, $depth + 1);
    }

    /** @return array{llx: float, lly: float, urx: float, ury: float}|null */
    private static function boxOf(array $objects, mixed $value, int $depth = 0): ?array
    {
        if ($depth > 4) {
            return null;
        }
        if (is_string($value) && preg_match('/^\d+ \d+ R$/', $value) === 1) {
            return self::boxOf($objects, $objects['obj:' . $value]['value'] ?? null, $depth + 1);
        }
        if (!is_array($value) || count($value) !== 4) {
            return null;
        }
        $nums = [];
        foreach ($value as $item) {
            if (!is_numeric($item)) {
                return null;
            }
            $nums[] = (float) $item;
        }
        if ($nums[2] <= $nums[0] || $nums[3] <= $nums[1]) {
            return null;
        }
        return ['llx' => $nums[0], 'lly' => $nums[1], 'urx' => $nums[2], 'ury' => $nums[3]];
    }

    private static function runQpdf(array $command): string
    {
        $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, null, ['bypass_shell' => true]);
        if (!is_resource($process)) {
            throw new RuntimeException('Δεν ήταν δυνατή η προσθήκη της σελίδας επικύρωσης. Ελέγξτε ότι το qpdf είναι εγκατεστημένο.');
        }
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $code = proc_close($process);
        if ($code !== 0) {
            $detail = trim(($stdout === false ? '' : $stdout) . "\n" . ($stderr === false ? '' : $stderr));
            if (stripos($detail, 'password') !== false || stripos($detail, 'encrypt') !== false) {
                throw new RuntimeException('Το PDF είναι κλειδωμένο με κωδικό και δεν μπορεί να επικυρωθεί.');
            }
            throw new RuntimeException('Το PDF δεν μπόρεσε να σφραγιστεί. Χρησιμοποιήστε ένα έγκυρο, μη κλειδωμένο αρχείο PDF.');
        }
        return $stdout === false ? '' : $stdout;
    }

    private static function assertOutput(string $targetPdf): void
    {
        if (!is_file($targetPdf) || filesize($targetPdf) < 64) {
            throw new RuntimeException('Το PDF δεν μπόρεσε να σφραγιστεί. Χρησιμοποιήστε ένα έγκυρο, μη κλειδωμένο αρχείο PDF.');
        }
    }

    private static function prepareTcpdf(): void
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
    }

    private static function tempPath(string $prefix): string
    {
        return BASE_PATH . '/storage/tmp/' . $prefix . '-' . bin2hex(random_bytes(8)) . '.pdf';
    }

    private static function unlinkQuiet(string $path): void
    {
        if (is_file($path)) {
            @unlink($path);
        }
    }
}
