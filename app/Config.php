<?php

declare(strict_types=1);

final class Config
{
    private static array $data = [
        'db' => [
            'host' => '127.0.0.1',
            'port' => 3306,
            'name' => 'sfragis',
            'user' => '',
            'pass' => '',
        ],
        'installed' => false,
    ];

    public static function load(): void
    {
        $file = self::path();
        if (is_file($file)) {
            $loaded = require $file;
            if (is_array($loaded)) {
                self::$data = array_replace_recursive(self::$data, $loaded);
            }
        }
        self::$data['db']['port'] = (int) (self::$data['db']['port'] ?? 3306);
        self::$data['installed'] = (bool) (self::$data['installed'] ?? false);
    }

    public static function path(): string
    {
        return BASE_PATH . '/config/config.php';
    }

    public static function lockPath(): string
    {
        return BASE_PATH . '/storage/install.lock';
    }

    public static function installed(): bool
    {
        return (bool) self::$data['installed'];
    }

    public static function setupAllowed(): bool
    {
        return !self::installed() && !is_file(self::lockPath());
    }

    public static function db(): array
    {
        return self::$data['db'];
    }

    public static function write(array $db, bool $installed): void
    {
        self::$data = [
            'db' => [
                'host' => (string) $db['host'],
                'port' => (int) $db['port'],
                'name' => (string) $db['name'],
                'user' => (string) $db['user'],
                'pass' => (string) $db['pass'],
            ],
            'installed' => $installed,
        ];
        $php = "<?php\n\ndeclare(strict_types=1);\n\nreturn " . var_export(self::$data, true) . ";\n";
        $path = self::path();
        $tmp = $path . '.' . bin2hex(random_bytes(4)) . '.tmp';
        if (file_put_contents($tmp, $php, LOCK_EX) === false) {
            throw new RuntimeException('Αποτυχία εγγραφής του αρχείου ρυθμίσεων.');
        }
        chmod($tmp, 0600);
        if (!rename($tmp, $path)) {
            @unlink($tmp);
            throw new RuntimeException('Αποτυχία εγγραφής του αρχείου ρυθμίσεων.');
        }
        chmod($path, 0600);
    }

    public static function writeLock(): void
    {
        $path = self::lockPath();
        file_put_contents($path, "locked " . now() . PHP_EOL, LOCK_EX);
        chmod($path, 0600);
    }
}
