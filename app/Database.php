<?php

declare(strict_types=1);

final class Database
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }
        self::$pdo = self::tryConnect(Config::db(), true);
        return self::$pdo;
    }

    public static function reset(): void
    {
        self::$pdo = null;
    }

    public static function tryConnect(array $db, bool $withDatabase): PDO
    {
        $host = (string) ($db['host'] ?? '');
        $name = (string) ($db['name'] ?? '');
        $port = (int) ($db['port'] ?? 3306);
        if (!valid_host($host) || $port < 1 || $port > 65535) {
            throw new InvalidArgumentException('Μη έγκυρα στοιχεία διακομιστή MySQL.');
        }
        if ($withDatabase && !preg_match('/^[A-Za-z0-9_]{1,64}$/', $name)) {
            throw new InvalidArgumentException('Μη έγκυρο όνομα βάσης.');
        }
        $dsn = 'mysql:host=' . $host . ';port=' . $port . ';charset=utf8mb4';
        if ($withDatabase) {
            $dsn .= ';dbname=' . $name;
        }
        $pdo = new PDO($dsn, (string) ($db['user'] ?? ''), (string) ($db['pass'] ?? ''), [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_TIMEOUT => 5,
            PDO::MYSQL_ATTR_LOCAL_INFILE => false,
        ]);
        $pdo->exec("SET SESSION sql_mode = 'STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");
        $offset = (new DateTimeImmutable('now', new DateTimeZone('Europe/Athens')))->format('P');
        $pdo->exec('SET time_zone = ' . $pdo->quote($offset));
        return $pdo;
    }
}
