<?php

declare(strict_types=1);

final class Storage
{
    public static function allocate(string $kind): array
    {
        if ($kind !== 'originals' && $kind !== 'certified') {
            throw new RuntimeException('Μη έγκυρος χώρος αρχείων.');
        }
        $name = bin2hex(random_bytes(16)) . '.pdf';
        $root = self::root();
        $dir = $root . DIRECTORY_SEPARATOR . $kind . DIRECTORY_SEPARATOR . substr($name, 0, 2);
        if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) {
            throw new RuntimeException('Αποτυχία δημιουργίας φακέλου αρχείων.');
        }
        $relative = $kind . '/' . substr($name, 0, 2) . '/' . $name;
        return [$relative, $dir . DIRECTORY_SEPARATOR . $name];
    }

    public static function pdfPath(string $relative): string
    {
        if (!preg_match('#^(originals|certified)/[a-f0-9]{2}/[a-f0-9]{32}\.pdf$#', $relative)) {
            throw new RuntimeException('Μη έγκυρη διαδρομή αρχείου.');
        }
        $root = self::root();
        $full = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
        $real = realpath($full);
        if ($real === false || !is_file($real) || !str_starts_with($real, $root . DIRECTORY_SEPARATOR)) {
            throw new RuntimeException('Το αρχείο δεν βρέθηκε.');
        }
        return $real;
    }

    public static function remove(string $relative): void
    {
        try {
            $path = self::pdfPath($relative);
        } catch (Throwable) {
            return;
        }
        if (is_file($path)) {
            @unlink($path);
        }
    }

    private static function root(): string
    {
        $root = realpath(BASE_PATH . '/storage/pdfs');
        if ($root === false) {
            throw new RuntimeException('Λείπει ο φάκελος αρχείων.');
        }
        return $root;
    }
}
