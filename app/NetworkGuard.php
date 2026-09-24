<?php

declare(strict_types=1);

final class NetworkGuard
{
    public static function list(): array
    {
        if (!Config::installed()) {
            return [];
        }
        return Database::pdo()->query(
            'SELECT id, cidr, label, created_at FROM allowed_networks ORDER BY id ASC'
        )->fetchAll();
    }

    public static function isRestricted(): bool
    {
        if (!Config::installed()) {
            return false;
        }
        return (int) Database::pdo()->query('SELECT COUNT(*) FROM allowed_networks')->fetchColumn() > 0;
    }

    public static function onSecureNetwork(?string $ip = null): bool
    {
        $ip ??= client_ip();
        return self::isRestricted() && self::allows($ip);
    }

    public static function allows(string $ip): bool
    {
        $networks = self::list();
        if ($networks === []) {
            return true;
        }
        foreach ($networks as $network) {
            if (self::matches($ip, (string) $network['cidr'])) {
                return true;
            }
        }
        return false;
    }

    public static function matches(string $ip, string $cidr): bool
    {
        if (filter_var($cidr, FILTER_VALIDATE_IP)) {
            return strcasecmp($ip, $cidr) === 0;
        }
        if (!str_contains($cidr, '/')) {
            return false;
        }
        [$subnet, $bits] = explode('/', $cidr, 2);
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) || !filter_var($subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return strcasecmp($ip, $cidr) === 0;
        }
        $bits = (int) $bits;
        if ($bits < 0 || $bits > 32) {
            return false;
        }
        $ipLong = self::unsignedIp($ip);
        $subLong = self::unsignedIp($subnet);
        if ($ipLong === null || $subLong === null) {
            return false;
        }
        $mask = $bits === 0 ? 0 : ((-1 << (32 - $bits)) & 0xFFFFFFFF);
        return ($ipLong & $mask) === ($subLong & $mask);
    }

    private static function unsignedIp(string $ip): ?int
    {
        $long = ip2long($ip);
        if ($long === false) {
            return null;
        }
        return $long & 0xFFFFFFFF;
    }

    public static function replace(array $networks): void
    {
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $pdo->exec('DELETE FROM allowed_networks');
            $stmt = $pdo->prepare('INSERT INTO allowed_networks (cidr, label, created_at) VALUES (?, ?, ?)');
            $seen = [];
            foreach ($networks as $network) {
                $cidr = (string) ($network['cidr'] ?? '');
                if ($cidr === '' || isset($seen[$cidr])) {
                    continue;
                }
                $seen[$cidr] = true;
                $stmt->execute([$cidr, mb_substr((string) ($network['label'] ?? ''), 0, 150), now()]);
            }
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }
}
