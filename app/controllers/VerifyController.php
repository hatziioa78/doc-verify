<?php

declare(strict_types=1);

final class VerifyController
{
    public static function show(string $token): void
    {
        if (self::limited()) {
            http_response_code(429);
            render('verify/result', [
                'title' => 'Πάρα πολλές προσπάθειες',
                'state' => 'limited',
                'doc' => null,
            ], 'layouts/verify');
        }
        $doc = self::resolve($token);
        if (!$doc) {
            self::recordDenied();
            http_response_code(404);
            render('verify/result', [
                'title' => 'Δεν αναγνωρίζεται',
                'state' => 'unknown',
                'doc' => null,
            ], 'layouts/verify');
        }
        Logger::record(null, 'qr_view', 'Προβολή από QR για έγγραφο ' . Documents::summary((string) $doc['subject'], (string) $doc['protocol_number']), (int) $doc['id']);
        $state = document_state($doc);
        render('verify/result', [
            'title' => state_label($state),
            'state' => $state,
            'doc' => $doc,
        ], 'layouts/verify');
    }

    public static function download(string $token): void
    {
        if (self::limited()) {
            http_response_code(429);
            render('verify/result', [
                'title' => 'Πάρα πολλές προσπάθειες',
                'state' => 'limited',
                'doc' => null,
            ], 'layouts/verify');
        }
        $doc = self::resolve($token);
        if (!$doc) {
            self::recordDenied();
            http_response_code(404);
            render('verify/result', [
                'title' => 'Δεν αναγνωρίζεται',
                'state' => 'unknown',
                'doc' => null,
            ], 'layouts/verify');
        }
        Logger::record(null, 'download', 'Δημόσια λήψη μέσω QR για έγγραφο ' . Documents::summary((string) $doc['subject'], (string) $doc['protocol_number']), (int) $doc['id']);
        send_download(Storage::pdfPath((string) $doc['certified_path']), DocumentController::downloadName($doc));
    }

    private static function resolve(string $token): ?array
    {
        if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
            return null;
        }
        return Documents::findByToken($token);
    }

    private static function limited(): bool
    {
        $stmt = Database::pdo()->prepare(
            "SELECT COUNT(*) FROM activity_log WHERE ip = ? AND action = 'qr_denied' AND created_at >= ?"
        );
        $stmt->execute([client_ip(), date('Y-m-d H:i:s', time() - 600)]);
        return (int) $stmt->fetchColumn() >= 120;
    }

    private static function recordDenied(): void
    {
        Logger::record(null, 'qr_denied', 'Αίτημα προβολής με άγνωστο σύνδεσμο QR');
    }
}
