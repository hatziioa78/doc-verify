<?php

declare(strict_types=1);

final class Logger
{
    public const LABELS = [
        'login' => 'Σύνδεση',
        'logout' => 'Αποσύνδεση',
        'login_failed' => 'Αποτυχία σύνδεσης',
        'login_blocked' => 'Απόρριψη εκτός δικτύου',
        'upload' => 'Ανέβασμα αρχείου',
        'certify' => 'Επικύρωση',
        'cancel' => 'Ακύρωση',
        'delete' => 'Διαγραφή',
        'download' => 'Λήψη αρχείου',
        'qr_view' => 'Προβολή από QR code',
        'qr_denied' => 'Αποτυχία προβολής QR',
        'user_create' => 'Δημιουργία χρήστη',
        'user_update' => 'Ενημέρωση χρήστη',
        'user_delete' => 'Διαγραφή χρήστη',
        'settings' => 'Αλλαγή παραμέτρων',
        'sql_test' => 'Έλεγχος σύνδεσης SQL',
        'sql_init' => 'Αρχικοποίηση βάσης',
        'sql_backup' => 'Αντίγραφο ασφαλείας SQL',
        'sql_restore' => 'Επαναφορά SQL',
        'network' => 'Ενημέρωση δικτύων',
        'password' => 'Αλλαγή κωδικού',
    ];

    public const FILE_ACTIONS = ['upload', 'certify', 'cancel', 'delete', 'download', 'qr_view'];

    public const OWN_FILE_ACTIONS = ['upload', 'certify', 'cancel', 'delete', 'download'];

    public static function label(string $action): string
    {
        return self::LABELS[$action] ?? $action;
    }

    public static function record(?int $userId, string $action, string $details = '', ?int $documentId = null): void
    {
        if (!isset(self::LABELS[$action])) {
            return;
        }
        $stmt = Database::pdo()->prepare(
            'INSERT INTO activity_log (user_id, document_id, ip, action, details, created_at)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $userId,
            $documentId,
            client_ip(),
            $action,
            mb_substr($details, 0, 1000),
            now(),
        ]);
    }
}
