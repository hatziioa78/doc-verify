<?php

declare(strict_types=1);

final class DocumentController
{
    public static function index(): void
    {
        Auth::requireUser();
        $filters = self::filters();
        $result = Documents::search($filters, max(1, query_int('page')), 12);
        render('documents/index', [
            'title' => 'Έγγραφα',
            'filters' => $filters,
            'rows' => $result['rows'],
            'pager' => $result['pager'],
            'owners' => self::owners(),
        ]);
    }

    public static function createForm(): void
    {
        $user = Auth::requireUser();
        render('documents/form', [
            'title' => 'Νέα επικύρωση',
            'user' => $user,
            'errors' => [],
            'old' => self::defaults($user),
            'editing' => null,
        ]);
    }

    public static function store(): void
    {
        $user = Auth::requireUser();
        $old = [
            'subject' => post_string('subject', 255),
            'protocol_number' => post_string('protocol_number', 120),
            'issuing_authority' => post_string('issuing_authority', 255),
            'info' => self::infoFromPost(),
            'valid_until' => post_string('valid_until', 10),
        ];
        if ($old['issuing_authority'] === '') {
            $old['issuing_authority'] = trim((string) $user['department']);
        }
        if ($old['info'] === '') {
            $old['info'] = default_info($user);
        }
        if ($old['valid_until'] === '') {
            $old['valid_until'] = Settings::defaultValidUntil();
        }
        $errors = self::validate($old);
        if ($errors !== []) {
            render('documents/form', [
                'title' => 'Νέα επικύρωση',
                'user' => $user,
                'errors' => $errors,
                'old' => $old,
                'editing' => null,
            ]);
        }
        try {
            $created = Documents::create($user, $old, $_FILES['pdf'] ?? []);
        } catch (Throwable $e) {
            log_exception($e);
            $errors[] = $e instanceof RuntimeException ? $e->getMessage() : 'Η καταχώρηση δεν ολοκληρώθηκε.';
            render('documents/form', [
                'title' => 'Νέα επικύρωση',
                'user' => $user,
                'errors' => $errors,
                'old' => $old,
                'editing' => null,
            ]);
        }
        if ($created['pending']) {
            $message = 'Το έγγραφο καταχωρίστηκε και περιμένει επιβεβαίωση από τη Γραμματεία.';
            if ($created['notice'] !== '') {
                $message .= ' ' . $created['notice'];
            }
            flash('warning', $message);
        } else {
            flash('success', 'Το έγγραφο καταχωρίστηκε και σφραγίστηκε με QR.');
        }
        redirect('/documents/' . $created['id']);
    }

    public static function editForm(int $id): void
    {
        $user = Auth::requireUser();
        $doc = Documents::findVisible($id);
        if (!$doc) {
            not_found();
        }
        if (!Documents::canModify($doc, $user)) {
            forbidden();
        }
        render('documents/form', [
            'title' => 'Επεξεργασία εγγράφου',
            'user' => $user,
            'errors' => [],
            'old' => self::fromDocument($doc),
            'editing' => $doc,
        ]);
    }

    public static function update(int $id): void
    {
        $user = Auth::requireUser();
        $doc = Documents::findVisible($id);
        if (!$doc) {
            not_found();
        }
        if (!Documents::canModify($doc, $user)) {
            forbidden();
        }
        $old = [
            'subject' => post_string('subject', 255),
            'protocol_number' => post_string('protocol_number', 120),
            'issuing_authority' => post_string('issuing_authority', 255),
            'info' => self::infoFromPost(),
            'valid_until' => post_string('valid_until', 10),
        ];
        $errors = self::validateMeta($old);
        if ($errors !== []) {
            render('documents/form', [
                'title' => 'Επεξεργασία εγγράφου',
                'user' => $user,
                'errors' => $errors,
                'old' => $old,
                'editing' => $doc,
            ]);
        }
        try {
            Documents::updateMeta($doc, $user, $old);
        } catch (Throwable $e) {
            log_exception($e);
            flash('danger', $e instanceof RuntimeException ? $e->getMessage() : 'Η αποθήκευση δεν ολοκληρώθηκε.');
            redirect('/documents/' . $id . '/edit');
        }
        flash('success', 'Τα στοιχεία του εγγράφου αποθηκεύτηκαν.');
        redirect('/documents/' . $id);
    }

    public static function show(int $id): void
    {
        $user = Auth::requireUser();
        $doc = Documents::findVisible($id);
        if (!$doc) {
            not_found();
        }
        render('documents/show', [
            'title' => (string) $doc['subject'],
            'doc' => $doc,
            'user' => $user,
            'canModify' => Documents::canModify($doc, $user),
            'canApprove' => Documents::canApprove($user),
            'verifyUrl' => Settings::siteUrl() . '/v/' . $doc['token'],
        ]);
    }

    public static function download(int $id): void
    {
        $user = Auth::requireUser();
        $doc = Documents::findVisible($id);
        if (!$doc) {
            not_found();
        }
        if (($doc['status'] ?? '') === 'pending' || (string) ($doc['certified_path'] ?? '') === '') {
            flash('warning', 'Το σφραγισμένο PDF θα είναι διαθέσιμο μετά την επιβεβαίωση.');
            redirect('/documents/' . $id);
        }
        Logger::record((int) $user['id'], 'download', 'Λήψη εγγράφου ' . Documents::summary((string) $doc['subject'], (string) $doc['protocol_number']), (int) $doc['id']);
        send_download(Storage::pdfPath((string) $doc['certified_path']), self::downloadName($doc));
    }

    public static function original(int $id): void
    {
        $user = Auth::requireUser();
        $doc = Documents::findVisible($id);
        if (!$doc) {
            not_found();
        }
        Logger::record((int) $user['id'], 'download', 'Λήψη πρωτοτύπου ' . Documents::summary((string) $doc['subject'], (string) $doc['protocol_number']), (int) $doc['id']);
        send_download(Storage::pdfPath((string) $doc['original_path']), self::downloadName($doc));
    }

    public static function qr(int $id): void
    {
        Auth::requireUser();
        $doc = Documents::findVisible($id);
        if (!$doc || ($doc['status'] ?? '') === 'pending' || (string) ($doc['certified_path'] ?? '') === '') {
            not_found();
        }
        $barcode = new TCPDF2DBarcode(Settings::siteUrl() . '/v/' . $doc['token'], 'QRCODE,H');
        $png = $barcode->getBarcodePngData(6, 6, [16, 32, 51]);
        header('Content-Type: image/png');
        header('Content-Length: ' . (string) strlen($png));
        echo $png;
        exit;
    }

    public static function cancel(int $id): void
    {
        $user = Auth::requireUser();
        $doc = Documents::findVisible($id);
        if (!$doc) {
            not_found();
        }
        try {
            Documents::cancel($doc, $user, post_string('reason', 500));
        } catch (Throwable $e) {
            flash('danger', $e->getMessage());
            redirect('/documents/' . $id);
        }
        if (($doc['status'] ?? '') === 'pending') {
            flash('success', 'Το έγγραφο ακυρώθηκε πριν από την επιβεβαίωση και δεν δημοσιεύεται.');
        } else {
            flash('success', 'Το έγγραφο ακυρώθηκε. Η σελίδα του QR το εμφανίζει πλέον ως ακυρωμένο.');
        }
        redirect('/documents/' . $id);
    }

    public static function delete(int $id): void
    {
        $user = Auth::requireUser();
        $doc = Documents::findVisible($id);
        if (!$doc) {
            not_found();
        }
        if (post_string('confirm', 20) !== 'ΔΙΑΓΡΑΦΗ') {
            flash('danger', 'Για διαγραφή πληκτρολογήστε ακριβώς τη λέξη ΔΙΑΓΡΑΦΗ.');
            redirect('/documents/' . $id);
        }
        Documents::delete($doc, $user);
        flash('success', 'Το έγγραφο διαγράφηκε και δεν εμφανίζεται πλέον στο μητρώο ούτε στο QR.');
        redirect('/documents');
    }

    private static function filters(): array
    {
        $status = query_string('status', 20);
        if (!in_array($status, ['', 'active', 'expired', 'cancelled', 'pending'], true)) {
            $status = '';
        }
        return [
            'subject' => query_string('subject', 255),
            'protocol' => query_string('protocol', 120),
            'authority' => query_string('authority', 255),
            'info' => query_string('info', 255),
            'person' => query_string('person', 190),
            'status' => $status,
            'owner_id' => max(0, query_int('owner_id')),
            'registered_from' => query_string('registered_from', 10),
            'registered_to' => query_string('registered_to', 10),
            'valid_from' => query_string('valid_from', 10),
            'valid_to' => query_string('valid_to', 10),
        ];
    }

    private static function owners(): array
    {
        return Database::pdo()->query(
            'SELECT id, last_name, first_name, email FROM users ORDER BY last_name, first_name'
        )->fetchAll();
    }

    private static function defaults(array $user): array
    {
        return [
            'subject' => '',
            'protocol_number' => '',
            'issuing_authority' => (string) $user['department'],
            'info' => default_info($user),
            'valid_until' => Settings::defaultValidUntil(),
        ];
    }

    private static function infoFromPost(): string
    {
        $info = str_replace("\0", '', post_raw('info'));
        $info = str_replace("\r\n", "\n", $info);
        $info = trim($info);
        if (mb_strlen($info) > 4000) {
            $info = mb_substr($info, 0, 4000);
        }
        return $info;
    }

    private static function fromDocument(array $doc): array
    {
        return [
            'subject' => (string) $doc['subject'],
            'protocol_number' => (string) $doc['protocol_number'],
            'issuing_authority' => (string) $doc['issuing_authority'],
            'info' => (string) $doc['info'],
            'valid_until' => substr((string) $doc['valid_until'], 0, 10),
        ];
    }

    private static function validate(array $old): array
    {
        $errors = self::validateMeta($old);
        $file = $_FILES['pdf'] ?? null;
        if (!is_array($file) || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            $errors[] = 'Επιλέξτε ένα αρχείο PDF.';
        }
        return $errors;
    }

    private static function validateMeta(array $old): array
    {
        $errors = [];
        if (mb_strlen($old['subject']) < 2) {
            $errors[] = 'Συμπληρώστε το θέμα του εγγράφου.';
        }
        if (mb_strlen($old['protocol_number']) < 1) {
            $errors[] = 'Συμπληρώστε τον αριθμό πρωτοκόλλου.';
        }
        if (mb_strlen($old['issuing_authority']) < 2) {
            $errors[] = 'Συμπληρώστε την εκδούσα αρχή / τμήμα.';
        }
        if (mb_strlen($old['info']) < 2) {
            $errors[] = 'Συμπληρώστε τις πληροφορίες του εγγράφου.';
        }
        if (!valid_date($old['valid_until'])) {
            $errors[] = 'Η ημερομηνία ισχύος δεν είναι έγκυρη.';
        }
        return $errors;
    }

    public static function downloadName(array $doc): string
    {
        $name = trim((string) $doc['protocol_number']);
        $name = $name !== '' ? $name : 'eggrafo';
        return $name . '.pdf';
    }
}
