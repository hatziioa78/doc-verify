<?php

declare(strict_types=1);

final class SqlAdmin
{
    public static function validate(array $db, bool $passwordRequired): array
    {
        $errors = [];
        if (!valid_host((string) $db['host'])) {
            $errors[] = 'Η διεύθυνση του MySQL δεν είναι έγκυρη.';
        }
        $port = (int) $db['port'];
        if ($port < 1 || $port > 65535) {
            $errors[] = 'Η θύρα του MySQL δεν είναι έγκυρη.';
        }
        if (!preg_match('/^[A-Za-z0-9_]{1,64}$/', (string) $db['name'])) {
            $errors[] = 'Το όνομα της βάσης επιτρέπει μόνο λατινικά γράμματα, αριθμούς και κάτω παύλα.';
        }
        $user = (string) $db['user'];
        if ($user === '' || strlen($user) > 64 || preg_match('/[\s\'"\\\\]/', $user)) {
            $errors[] = 'Το όνομα χρήστη MySQL δεν είναι έγκυρο.';
        }
        $pass = (string) $db['pass'];
        if ($passwordRequired && $pass === '') {
            $errors[] = 'Συμπληρώστε τον κωδικό του MySQL.';
        }
        if (strlen($pass) > 128 || str_contains($pass, "\n") || str_contains($pass, "\r") || str_contains($pass, "\0")) {
            $errors[] = 'Ο κωδικός MySQL δεν είναι αποδεκτός.';
        }
        return $errors;
    }

    public static function test(array $db): array
    {
        try {
            $server = Database::tryConnect($db, false);
            $server->query('SELECT 1');
        } catch (Throwable $e) {
            return [
                'ok' => false,
                'database' => false,
                'message' => 'Αποτυχία σύνδεσης με τον διακομιστή MySQL. ' . self::safe($e, (string) $db['pass']),
            ];
        }
        try {
            $pdo = Database::tryConnect($db, true);
            $pdo->query('SELECT 1');
            return [
                'ok' => true,
                'database' => true,
                'message' => 'Η σύνδεση πέτυχε και η βάση «' . $db['name'] . '» είναι διαθέσιμη.',
            ];
        } catch (Throwable $e) {
            return [
                'ok' => true,
                'database' => false,
                'message' => 'Ο διακομιστής απάντησε, όμως η βάση δεν είναι προσβάσιμη. Μπορείτε να την αρχικοποιήσετε. ' . self::safe($e, (string) $db['pass']),
            ];
        }
    }

    public static function initialize(array $db): string
    {
        $server = Database::tryConnect($db, false);
        $name = str_replace('`', '', (string) $db['name']);
        $server->exec(
            'CREATE DATABASE IF NOT EXISTS `' . $name . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'
        );
        $pdo = Database::tryConnect($db, true);
        self::runSchema($pdo);
        return 'Η βάση «' . $name . '» δημιουργήθηκε ή υπήρχε ήδη και οι πίνακες του ΣΦΡΑΓΙΣ είναι έτοιμοι.';
    }

    public static function runSchema(PDO $pdo): void
    {
        $sql = file_get_contents(BASE_PATH . '/sql/schema.sql');
        if ($sql === false) {
            throw new RuntimeException('Λείπει το αρχείο σχήματος της βάσης.');
        }
        $sql = preg_replace('/--.*$/m', '', $sql) ?? $sql;
        foreach (preg_split('/;\s*(?:\n|$)/', $sql) ?: [] as $statement) {
            $statement = trim($statement);
            if ($statement === '') {
                continue;
            }
            $pdo->exec($statement);
        }
    }

    public static function backup(array $db): string
    {
        $dir = BASE_PATH . '/storage/backups';
        if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) {
            throw new RuntimeException('Λείπει ο φάκελος αντιγράφων.');
        }
        $file = $dir . '/sfragis-' . date('Ymd-His') . '.sql';
        $cnf = self::defaultsFile($db);
        try {
            $command = [
                'mysqldump',
                '--defaults-extra-file=' . $cnf,
                '--single-transaction',
                '--skip-lock-tables',
                '--default-character-set=utf8mb4',
                '--result-file=' . $file,
                (string) $db['name'],
            ];
            self::run($command, 'Η δημιουργία αντιγράφου SQL απέτυχε.');
        } finally {
            @unlink($cnf);
        }
        if (!is_file($file) || filesize($file) < 32) {
            @unlink($file);
            throw new RuntimeException('Το αντίγραφο SQL δεν δημιουργήθηκε.');
        }
        chmod($file, 0600);
        return $file;
    }

    public static function restore(array $db, string $sqlFile): void
    {
        $sample = file_get_contents($sqlFile, false, null, 0, 4000);
        if ($sample === false || !preg_match('/CREATE TABLE|INSERT INTO|-- MariaDB|-- MySQL/i', $sample)) {
            throw new RuntimeException('Το αρχείο δεν μοιάζει με αντίγραφο SQL του ΣΦΡΑΓΙΣ.');
        }
        $cnf = self::defaultsFile($db);
        try {
            $command = [
                'mysql',
                '--defaults-extra-file=' . $cnf,
                '--default-character-set=utf8mb4',
                (string) $db['name'],
            ];
            self::run($command, 'Η επαναφορά SQL απέτυχε.', $sqlFile);
        } finally {
            @unlink($cnf);
        }
    }

    public static function backups(): array
    {
        $dir = BASE_PATH . '/storage/backups';
        $files = glob($dir . '/sfragis-*.sql') ?: [];
        rsort($files);
        $rows = [];
        foreach ($files as $file) {
            $base = basename($file);
            if (!preg_match('/^sfragis-\d{8}-\d{6}\.sql$/', $base)) {
                continue;
            }
            $rows[] = [
                'name' => $base,
                'size' => (int) filesize($file),
                'mtime' => (int) filemtime($file),
            ];
        }
        return $rows;
    }

    public static function backupPath(string $name): string
    {
        if (!preg_match('/^sfragis-\d{8}-\d{6}\.sql$/', $name)) {
            throw new RuntimeException('Μη έγκυρο αντίγραφο.');
        }
        $path = BASE_PATH . '/storage/backups/' . $name;
        $real = realpath($path);
        $root = realpath(BASE_PATH . '/storage/backups');
        if ($real === false || $root === false || !str_starts_with($real, $root . DIRECTORY_SEPARATOR)) {
            throw new RuntimeException('Το αντίγραφο δεν βρέθηκε.');
        }
        return $real;
    }

    private static function defaultsFile(array $db): string
    {
        $file = BASE_PATH . '/storage/tmp/my-' . bin2hex(random_bytes(8)) . '.cnf';
        $content = "[client]\n"
            . 'host=' . self::iniQuote((string) $db['host']) . "\n"
            . 'port=' . (int) $db['port'] . "\n"
            . 'user=' . self::iniQuote((string) $db['user']) . "\n"
            . 'password=' . self::iniQuote((string) $db['pass']) . "\n";
        if (file_put_contents($file, $content, LOCK_EX) === false) {
            throw new RuntimeException('Αποτυχία προετοιμασίας σύνδεσης MySQL.');
        }
        chmod($file, 0600);
        return $file;
    }

    private static function iniQuote(string $value): string
    {
        return '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $value) . '"';
    }

    private static function run(array $command, string $failure, ?string $stdinFile = null): void
    {
        $descriptors = [
            0 => $stdinFile ? ['file', $stdinFile, 'r'] : ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $process = proc_open($command, $descriptors, $pipes, null, null, ['bypass_shell' => true]);
        if (!is_resource($process)) {
            throw new RuntimeException($failure);
        }
        if (!$stdinFile && isset($pipes[0]) && is_resource($pipes[0])) {
            fclose($pipes[0]);
        }
        $stderr = (string) stream_get_contents($pipes[1]) . (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $code = proc_close($process);
        if ($code !== 0) {
            $clean = trim(preg_replace('/password=\S+/i', 'password=***', $stderr) ?? '');
            throw new RuntimeException($failure . ($clean !== '' ? ' ' . mb_substr($clean, 0, 300) : ''));
        }
    }

    private static function safe(Throwable $e, string $secret): string
    {
        $message = $e->getMessage();
        if ($secret !== '') {
            $message = str_replace($secret, '***', $message);
        }
        return mb_substr($message, 0, 220);
    }
}
