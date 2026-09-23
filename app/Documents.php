<?php

declare(strict_types=1);

final class Documents
{
    public static function search(array $filters, int $page, int $perPage = 12): array
    {
        $where = ['d.deleted_at IS NULL'];
        $params = [];

        if (($filters['subject'] ?? '') !== '') {
            $where[] = "d.subject LIKE ? ESCAPE '\\\\'";
            $params[] = like_term((string) $filters['subject']);
        }
        if (($filters['protocol'] ?? '') !== '') {
            $where[] = "d.protocol_number LIKE ? ESCAPE '\\\\'";
            $params[] = like_term((string) $filters['protocol']);
        }
        if (($filters['authority'] ?? '') !== '') {
            $where[] = "d.issuing_authority LIKE ? ESCAPE '\\\\'";
            $params[] = like_term((string) $filters['authority']);
        }
        if (($filters['info'] ?? '') !== '') {
            $where[] = "d.info LIKE ? ESCAPE '\\\\'";
            $params[] = like_term((string) $filters['info']);
        }
        if (($filters['person'] ?? '') !== '') {
            $term = like_term((string) $filters['person']);
            $where[] = "(u.last_name LIKE ? ESCAPE '\\\\' OR u.first_name LIKE ? ESCAPE '\\\\' OR u.email LIKE ? ESCAPE '\\\\')";
            array_push($params, $term, $term, $term);
        }
        $status = (string) ($filters['status'] ?? '');
        if ($status === 'active') {
            $where[] = "d.status = 'active' AND d.valid_until >= ?";
            $params[] = today();
        } elseif ($status === 'expired') {
            $where[] = "d.status = 'active' AND d.valid_until < ?";
            $params[] = today();
        } elseif ($status === 'cancelled') {
            $where[] = "d.status = 'cancelled'";
        }
        $ownerId = (int) ($filters['owner_id'] ?? 0);
        if ($ownerId > 0) {
            $where[] = 'd.owner_id = ?';
            $params[] = $ownerId;
        }
        if (valid_date((string) ($filters['registered_from'] ?? ''))) {
            $where[] = 'd.registered_at >= ?';
            $params[] = $filters['registered_from'] . ' 00:00:00';
        }
        if (valid_date((string) ($filters['registered_to'] ?? ''))) {
            $where[] = 'd.registered_at <= ?';
            $params[] = $filters['registered_to'] . ' 23:59:59';
        }
        if (valid_date((string) ($filters['valid_from'] ?? ''))) {
            $where[] = 'd.valid_until >= ?';
            $params[] = $filters['valid_from'];
        }
        if (valid_date((string) ($filters['valid_to'] ?? ''))) {
            $where[] = 'd.valid_until <= ?';
            $params[] = $filters['valid_to'];
        }

        $sqlWhere = 'WHERE ' . implode(' AND ', $where);
        $countStmt = Database::pdo()->prepare(
            "SELECT COUNT(*) FROM documents d JOIN users u ON u.id = d.owner_id {$sqlWhere}"
        );
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();
        $pager = pager($total, $page, $perPage);

        $sql = self::selectSql() . " {$sqlWhere} ORDER BY d.registered_at DESC, d.id DESC LIMIT {$pager['per']} OFFSET {$pager['offset']}";
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        return ['rows' => $stmt->fetchAll(), 'pager' => $pager];
    }

    public static function findVisible(int $id): ?array
    {
        $stmt = Database::pdo()->prepare(self::selectSql() . ' WHERE d.id = ? AND d.deleted_at IS NULL LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function findByToken(string $token): ?array
    {
        if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
            return null;
        }
        $stmt = Database::pdo()->prepare(self::selectSql() . ' WHERE d.token = ? AND d.deleted_at IS NULL LIMIT 1');
        $stmt->execute([$token]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function canModify(array $doc, array $actor): bool
    {
        return ($actor['role'] ?? '') === 'manager' || (int) $doc['owner_id'] === (int) $actor['id'];
    }

    public static function create(array $owner, array $input, array $file): int
    {
        self::assertPdfUpload($file);
        $sha = hash_file('sha256', (string) $file['tmp_name']);
        if ($sha === false) {
            throw new RuntimeException('Το αρχείο δεν μπόρεσε να διαβαστεί.');
        }

        [$originalRel, $originalAbs] = Storage::allocate('originals');
        [$certifiedRel, $certifiedAbs] = Storage::allocate('certified');
        if (!move_uploaded_file((string) $file['tmp_name'], $originalAbs)) {
            throw new RuntimeException('Η αποθήκευση του PDF απέτυχε.');
        }
        chmod($originalAbs, 0640);

        $registeredAt = now();
        $token = bin2hex(random_bytes(32));
        $doc = [
            'subject' => $input['subject'],
            'protocol_number' => $input['protocol_number'],
            'issuing_authority' => $input['issuing_authority'],
            'info' => $input['info'],
            'registered_at' => $registeredAt,
            'valid_until' => $input['valid_until'],
            'sha256' => $sha,
            'owner_name' => full_name($owner),
        ];
        $verifyUrl = Settings::siteUrl() . '/v/' . $token;

        try {
            PdfStamper::appendVerificationPage($originalAbs, $certifiedAbs, $doc, $verifyUrl);
            chmod($certifiedAbs, 0640);
        } catch (Throwable $e) {
            Storage::remove($originalRel);
            Storage::remove($certifiedRel);
            throw $e;
        }

        $pdo = Database::pdo();
        $stmt = $pdo->prepare(
            'INSERT INTO documents
            (owner_id, subject, protocol_number, issuing_authority, info, registered_at, valid_until, token, status,
             original_name, original_path, certified_path, sha256, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, \'active\', ?, ?, ?, ?, ?)'
        );
        try {
            $stmt->execute([
                (int) $owner['id'],
                $input['subject'],
                $input['protocol_number'],
                $input['issuing_authority'],
                $input['info'],
                $registeredAt,
                $input['valid_until'],
                $token,
                self::safeOriginalName((string) ($file['name'] ?? 'document.pdf')),
                $originalRel,
                $certifiedRel,
                $sha,
                $registeredAt,
            ]);
        } catch (Throwable $e) {
            Storage::remove($originalRel);
            Storage::remove($certifiedRel);
            throw $e;
        }

        $id = (int) $pdo->lastInsertId();
        $summary = self::summary($input['subject'], $input['protocol_number']);
        Logger::record((int) $owner['id'], 'upload', 'Ανέβασμα αρχείου ' . $summary, $id);
        Logger::record((int) $owner['id'], 'certify', 'Επικύρωση εγγράφου ' . $summary, $id);
        return $id;
    }

    public static function cancel(array $doc, array $actor, string $reason): void
    {
        if (!self::canModify($doc, $actor)) {
            forbidden();
        }
        if (($doc['status'] ?? '') === 'cancelled') {
            throw new RuntimeException('Το έγγραφο είναι ήδη ακυρωμένο.');
        }
        $reason = trim($reason);
        if (mb_strlen($reason) > 500) {
            $reason = mb_substr($reason, 0, 500);
        }
        $stmt = Database::pdo()->prepare(
            'UPDATE documents
             SET status = \'cancelled\', cancellation_reason = ?, cancelled_at = ?, cancelled_by = ?
             WHERE id = ? AND deleted_at IS NULL AND status = \'active\''
        );
        $stmt->execute([
            $reason !== '' ? $reason : null,
            now(),
            (int) $actor['id'],
            (int) $doc['id'],
        ]);
        $detail = 'Ακύρωση εγγράφου ' . self::summary((string) $doc['subject'], (string) $doc['protocol_number']);
        if ($reason !== '') {
            $detail .= '. Αιτία: ' . $reason;
        }
        Logger::record((int) $actor['id'], 'cancel', $detail, (int) $doc['id']);
    }

    public static function delete(array $doc, array $actor): void
    {
        if (!self::canModify($doc, $actor)) {
            forbidden();
        }
        $stmt = Database::pdo()->prepare(
            'UPDATE documents SET deleted_at = ?, deleted_by = ? WHERE id = ? AND deleted_at IS NULL'
        );
        $stmt->execute([now(), (int) $actor['id'], (int) $doc['id']]);
        Storage::remove((string) $doc['original_path']);
        Storage::remove((string) $doc['certified_path']);
        Logger::record(
            (int) $actor['id'],
            'delete',
            'Διαγραφή εγγράφου ' . self::summary((string) $doc['subject'], (string) $doc['protocol_number']),
            (int) $doc['id']
        );
    }

    public static function summary(string $subject, string $protocol): string
    {
        return '«' . $subject . '» (πρωτ. ' . $protocol . ')';
    }

    private static function selectSql(): string
    {
        return 'SELECT d.*,
                u.first_name, u.last_name, u.email AS owner_email, u.department AS owner_department,
                c.first_name AS canceller_first_name, c.last_name AS canceller_last_name
            FROM documents d
            JOIN users u ON u.id = d.owner_id
            LEFT JOIN users c ON c.id = d.cancelled_by';
    }

    private static function assertPdfUpload(array $file): void
    {
        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE) {
            throw new RuntimeException('Το PDF ξεπερνά το επιτρεπόμενο μέγεθος (20 MB).');
        }
        if ($error !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Η μεταφόρτωση του PDF απέτυχε.');
        }
        $tmp = (string) ($file['tmp_name'] ?? '');
        $size = (int) ($file['size'] ?? 0);
        if ($size < 32 || $size > 20 * 1024 * 1024) {
            throw new RuntimeException('Το PDF πρέπει να είναι έως 20 MB.');
        }
        if (!is_uploaded_file($tmp)) {
            throw new RuntimeException('Μη έγκυρη μεταφόρτωση αρχείου.');
        }
        $handle = fopen($tmp, 'rb');
        if ($handle === false) {
            throw new RuntimeException('Το αρχείο δεν μπόρεσε να διαβαστεί.');
        }
        $magic = fread($handle, 5);
        fclose($handle);
        if ($magic !== '%PDF-') {
            throw new RuntimeException('Επιτρέπονται μόνο αρχεία PDF.');
        }
    }

    private static function safeOriginalName(string $name): string
    {
        $name = basename(str_replace(["\0", '\\'], ['', '/'], $name));
        $name = preg_replace('/[^\p{L}\p{N}._ ()-]+/u', '_', $name) ?? 'document.pdf';
        $name = trim($name, '. ');
        if ($name === '' || !str_ends_with(mb_strtolower($name), '.pdf')) {
            $name .= '.pdf';
        }
        return mb_substr($name, 0, 180);
    }
}
