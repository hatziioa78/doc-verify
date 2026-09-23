<?php

declare(strict_types=1);

final class Approvals
{
    public static function pendingCount(): int
    {
        return (int) Database::pdo()->query(
            "SELECT COUNT(*) FROM documents WHERE deleted_at IS NULL AND status = 'pending'"
        )->fetchColumn();
    }

    public static function notify(int $documentId): string
    {
        if (!Settings::flag('notify_secretary') && !Settings::flag('notify_extra')) {
            return '';
        }
        $doc = Documents::findVisible($documentId);
        if (!$doc || ($doc['status'] ?? '') !== 'pending') {
            return '';
        }
        $secretaries = self::secretaries();
        if ($secretaries === []) {
            return 'Το έγγραφο περιμένει επιβεβαίωση, αλλά δεν υπάρχει ενεργή Γραμματεία για να σταλεί το email.';
        }
        if (!Mailer::ready(Mailer::configFromSettings())) {
            return 'Το έγγραφο περιμένει επιβεβαίωση. Το email δεν στάλθηκε γιατί δεν έχουν συμπληρωθεί οι ρυθμίσεις SMTP.';
        }

        $jobs = [];
        if (Settings::flag('notify_secretary')) {
            foreach ($secretaries as $secretary) {
                $jobs[normalize_email((string) $secretary['email'])] = $secretary;
            }
        }
        if (Settings::flag('notify_extra')) {
            $extra = normalize_email(Settings::get('notify_extra_email'));
            if (valid_email($extra) && !isset($jobs[$extra])) {
                $jobs[$extra] = $secretaries[0];
            }
        }
        if ($jobs === []) {
            return '';
        }

        self::purgeExpired();
        $config = Mailer::configFromSettings();
        $sent = 0;
        $failed = 0;
        foreach ($jobs as $email => $secretary) {
            $token = self::issue((int) $doc['id'], (int) $secretary['id']);
            $link = Settings::serverUrl() . '/a/' . $token;
            try {
                Mailer::send($config, $email, 'Αίτημα επιβεβαίωσης εγγράφου', self::html($doc, $link), self::text($doc, $link));
                $sent++;
            } catch (Throwable $e) {
                log_exception($e);
                $failed++;
            }
        }
        $summary = Documents::summary((string) $doc['subject'], (string) $doc['protocol_number']);
        if ($sent > 0) {
            Logger::record((int) $doc['owner_id'], 'approval_request', 'Αποστολή email επιβεβαίωσης για ' . $summary . ' (' . $sent . ')', (int) $doc['id']);
        }
        if ($failed > 0 && $sent === 0) {
            return 'Το έγγραφο περιμένει επιβεβαίωση, αλλά η αποστολή του email απέτυχε.';
        }
        if ($failed > 0) {
            return 'Το αίτημα στάλθηκε, όμως ένα από τα email απέτυχε.';
        }
        return '';
    }

    public static function open(string $token): never
    {
        self::purgeExpired();
        if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
            self::reject('Ο σύνδεσμος επιβεβαίωσης δεν είναι έγκυρος.');
        }
        $stmt = Database::pdo()->prepare(
            'SELECT document_id, secretary_id FROM approval_links WHERE token = ? AND expires_at >= ? LIMIT 1'
        );
        $stmt->execute([$token, now()]);
        $link = $stmt->fetch();
        if (!$link) {
            self::reject('Ο σύνδεσμος επιβεβαίωσης έληξε ή δεν αναγνωρίζεται.');
        }
        $userStmt = Database::pdo()->prepare(
            "SELECT * FROM users WHERE id = ? AND role = 'secretary' AND active = 1 LIMIT 1"
        );
        $userStmt->execute([(int) $link['secretary_id']]);
        $secretary = $userStmt->fetch();
        if (!$secretary) {
            self::reject('Ο λογαριασμός Γραμματείας αυτού του συνδέσμου δεν είναι πλέον ενεργός.');
        }

        $documentId = (int) $link['document_id'];
        Auth::startMagicSession($secretary, $documentId);
        $doc = Documents::findVisible($documentId);
        if (!$doc) {
            flash('warning', 'Συνδεθήκατε ως Γραμματεία. Το έγγραφο δεν βρίσκεται πλέον στο μητρώο.');
            redirect('/approvals');
        }
        if (($doc['status'] ?? '') !== 'pending') {
            flash('success', 'Συνδεθήκατε ως Γραμματεία. Το έγγραφο έχει ήδη επιβεβαιωθεί.');
        } else {
            flash('success', 'Συνδεθήκατε ως Γραμματεία για την επιβεβαίωση αυτού του εγγράφου.');
        }
        redirect('/documents/' . $documentId);
    }

    private static function secretaries(): array
    {
        return Database::pdo()->query(
            "SELECT id, email, first_name, last_name FROM users WHERE role = 'secretary' AND active = 1 ORDER BY id"
        )->fetchAll();
    }

    private static function issue(int $documentId, int $secretaryId): string
    {
        $token = bin2hex(random_bytes(32));
        $expires = (new DateTimeImmutable('+7 days'))->format('Y-m-d H:i:s');
        $stmt = Database::pdo()->prepare(
            'INSERT INTO approval_links (token, document_id, secretary_id, expires_at, created_at) VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([$token, $documentId, $secretaryId, $expires, now()]);
        return $token;
    }

    private static function purgeExpired(): void
    {
        Database::pdo()->prepare('DELETE FROM approval_links WHERE expires_at < ?')->execute([now()]);
    }

    private static function html(array $doc, string $link): string
    {
        $owner = trim((string) $doc['last_name'] . ' ' . (string) $doc['first_name']);
        return '<p>Υποβλήθηκε έγγραφο προς επιβεβαίωση από τη Γραμματεία.</p>'
            . '<p><strong>Θέμα:</strong> ' . e((string) $doc['subject']) . '<br>'
            . '<strong>Αριθμός πρωτοκόλλου:</strong> ' . e((string) $doc['protocol_number']) . '<br>'
            . '<strong>Καταχωρητής:</strong> ' . e($owner) . '</p>'
            . '<p><a href="' . e($link) . '">Πατήστε εδώ</a></p>'
            . '<p>Ο σύνδεσμος σάς συνδέει στο σύστημα ως Γραμματεία για την επιβεβαίωση αυτού του εγγράφου και ισχύει για 7 ημέρες.</p>';
    }

    private static function text(array $doc, string $link): string
    {
        $owner = trim((string) $doc['last_name'] . ' ' . (string) $doc['first_name']);
        return "Υποβλήθηκε έγγραφο προς επιβεβαίωση από τη Γραμματεία.\n"
            . 'Θέμα: ' . $doc['subject'] . "\n"
            . 'Αριθμός πρωτοκόλλου: ' . $doc['protocol_number'] . "\n"
            . 'Καταχωρητής: ' . $owner . "\n\n"
            . 'Πατήστε εδώ: ' . $link . "\n";
    }

    private static function reject(string $message): never
    {
        http_response_code(404);
        render('auth/link', [
            'title' => 'Σύνδεσμος επιβεβαίωσης',
            'message' => $message,
        ], 'layouts/guest');
    }
}
