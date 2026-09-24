<?php

declare(strict_types=1);

final class Migrate
{
    private const VERSION = '7';

    public static function run(): void
    {
        if (!Config::installed()) {
            return;
        }
        try {
            $pdo = Database::pdo();
            $stmt = $pdo->prepare('SELECT svalue FROM settings WHERE skey = ?');
            $stmt->execute(['schema_version']);
            if ((string) $stmt->fetchColumn() === self::VERSION) {
                return;
            }
            self::apply($pdo);
            $save = $pdo->prepare(
                'INSERT INTO settings (skey, svalue) VALUES (?, ?)
                 ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)'
            );
            $save->execute(['schema_version', self::VERSION]);
        } catch (Throwable $e) {
            log_exception($e);
        }
    }

    private static function apply(PDO $pdo): void
    {
        $pdo->exec("ALTER TABLE users MODIFY role ENUM('manager', 'secretary', 'user') NOT NULL DEFAULT 'user'");
        if (!self::hasColumn($pdo, 'users', 'certify_without_approval')) {
            $pdo->exec('ALTER TABLE users ADD COLUMN certify_without_approval TINYINT(1) NOT NULL DEFAULT 0 AFTER active');
        }

        $pdo->exec("ALTER TABLE documents MODIFY status ENUM('pending', 'active', 'cancelled') NOT NULL DEFAULT 'active'");
        $pdo->exec('ALTER TABLE documents MODIFY certified_path VARCHAR(255) NULL');
        if (!self::hasColumn($pdo, 'documents', 'confirmed_at')) {
            $pdo->exec('ALTER TABLE documents ADD COLUMN confirmed_at DATETIME NULL AFTER cancelled_by');
        }
        if (!self::hasColumn($pdo, 'documents', 'confirmed_by')) {
            $pdo->exec('ALTER TABLE documents ADD COLUMN confirmed_by INT UNSIGNED NULL AFTER confirmed_at');
        }
        self::addForeignKey(
            $pdo,
            'ALTER TABLE documents ADD CONSTRAINT fk_documents_confirmed_by FOREIGN KEY (confirmed_by) REFERENCES users (id)'
        );

        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS approval_links (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                token CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                document_id INT UNSIGNED NOT NULL,
                secretary_id INT UNSIGNED NOT NULL,
                expires_at DATETIME NOT NULL,
                created_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY uq_approval_token (token),
                KEY idx_approval_document (document_id),
                KEY idx_approval_expires (expires_at),
                CONSTRAINT fk_approval_document FOREIGN KEY (document_id) REFERENCES documents (id) ON DELETE CASCADE,
                CONSTRAINT fk_approval_secretary FOREIGN KEY (secretary_id) REFERENCES users (id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $exists = $pdo->prepare('SELECT svalue FROM settings WHERE skey = ?');
        $exists->execute(['server_url']);
        if ($exists->fetchColumn() === false) {
            $exists->execute(['site_url']);
            $site = (string) $exists->fetchColumn();
            if ($site !== '') {
                $insert = $pdo->prepare('INSERT INTO settings (skey, svalue) VALUES (?, ?)');
                $insert->execute(['server_url', $site]);
            }
        }

        $exists->execute(['stamp_placement']);
        if ($exists->fetchColumn() === false) {
            $insert = $pdo->prepare('INSERT INTO settings (skey, svalue) VALUES (?, ?)');
            $insert->execute(['stamp_placement', 'footer']);
        }

        if (self::hasColumn($pdo, 'documents', 'valid_until')) {
            $pdo->exec('ALTER TABLE documents DROP COLUMN valid_until');
        }
        $pdo->exec("DELETE FROM settings WHERE skey = 'default_validity_months'");

        if (!self::hasColumn($pdo, 'users', 'full_name')) {
            $pdo->exec("ALTER TABLE users ADD COLUMN full_name VARCHAR(200) NOT NULL DEFAULT '' AFTER id");
            if (self::hasColumn($pdo, 'users', 'last_name')) {
                $pdo->exec("UPDATE users SET full_name = TRIM(CONCAT(last_name, ' ', first_name))");
            }
        }
        if (self::hasColumn($pdo, 'users', 'first_name')) {
            $pdo->exec('ALTER TABLE users DROP COLUMN first_name');
        }
        if (self::hasColumn($pdo, 'users', 'last_name')) {
            $pdo->exec('ALTER TABLE users DROP COLUMN last_name');
        }
        if (!self::hasColumn($pdo, 'users', 'password_insecure')) {
            $pdo->exec('ALTER TABLE users ADD COLUMN password_insecure TINYINT(1) NOT NULL DEFAULT 0 AFTER password_hash');
        }
        if (!self::hasColumn($pdo, 'users', 'notify_approval')) {
            $pdo->exec('ALTER TABLE users ADD COLUMN notify_approval TINYINT(1) NOT NULL DEFAULT 1 AFTER certify_without_approval');
        }
    }

    private static function hasColumn(PDO $pdo, string $table, string $column): bool
    {
        $stmt = $pdo->query('SHOW COLUMNS FROM `' . $table . '` LIKE ' . $pdo->quote($column));
        return $stmt !== false && (bool) $stmt->fetch();
    }

    private static function addForeignKey(PDO $pdo, string $sql): void
    {
        try {
            $pdo->exec($sql);
        } catch (PDOException $e) {
            $code = (int) ($e->errorInfo[1] ?? 0);
            $message = $e->getMessage();
            if (in_array($code, [1005, 1022, 1061, 1826], true) || str_contains($message, 'Duplicate')) {
                return;
            }
            throw $e;
        }
    }
}
