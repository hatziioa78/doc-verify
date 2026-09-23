<?php

declare(strict_types=1);

final class Settings
{
    private static ?array $cache = null;

    public static function flush(): void
    {
        self::$cache = null;
    }

    public static function all(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }
        $defaults = [
            'site_url' => '',
            'server_url' => '',
            'header_name' => 'ΣΦΡΑΓΙΣ',
            'footer_contact' => '',
            'footer_credits' => '',
            'default_validity_months' => '12',
            'stamp_placement' => 'footer',
            'smtp_host' => '',
            'smtp_port' => '587',
            'smtp_username' => '',
            'smtp_password' => '',
            'smtp_from_name' => '',
            'smtp_from_email' => '',
            'smtp_security' => 'tls',
            'notify_secretary' => '0',
            'notify_extra' => '0',
            'notify_extra_email' => '',
        ];
        if (!Config::installed()) {
            self::$cache = $defaults;
            return self::$cache;
        }
        try {
            $rows = Database::pdo()->query('SELECT skey, svalue FROM settings')->fetchAll();
            foreach ($rows as $row) {
                $defaults[(string) $row['skey']] = (string) $row['svalue'];
            }
        } catch (Throwable $e) {
            log_exception($e);
        }
        self::$cache = $defaults;
        return self::$cache;
    }

    public static function get(string $key, string $default = ''): string
    {
        $all = self::all();
        $value = trim((string) ($all[$key] ?? ''));
        return $value === '' ? $default : $value;
    }

    public static function secret(string $key): string
    {
        $all = self::all();
        return (string) ($all[$key] ?? '');
    }

    public static function flag(string $key): bool
    {
        return self::get($key, '0') === '1';
    }

    public static function setMany(array $pairs): void
    {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO settings (skey, svalue) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)'
        );
        foreach ($pairs as $key => $value) {
            $stmt->execute([(string) $key, (string) $value]);
        }
        self::flush();
    }

    public static function headerName(): string
    {
        return self::get('header_name', 'ΣΦΡΑΓΙΣ');
    }

    public static function siteUrl(): string
    {
        $configured = self::get('site_url', '');
        if ($configured !== '') {
            return rtrim($configured, '/');
        }
        return current_origin();
    }

    public static function serverUrl(): string
    {
        $configured = self::get('server_url', '');
        if ($configured !== '') {
            return rtrim($configured, '/');
        }
        return self::siteUrl();
    }

    public static function stampPlacement(): string
    {
        $value = self::get('stamp_placement', 'footer');
        if (!in_array($value, ['appendix', 'header', 'footer'], true)) {
            return 'footer';
        }
        return $value;
    }

    public static function validityMonths(): int
    {
        $months = (int) self::get('default_validity_months', '12');
        if ($months < 1 || $months > 120) {
            return 12;
        }
        return $months;
    }

    public static function defaultValidUntil(): string
    {
        return (new DateTimeImmutable('today'))
            ->modify('+' . self::validityMonths() . ' months')
            ->format('Y-m-d');
    }
}
