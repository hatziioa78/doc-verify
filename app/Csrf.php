<?php

declare(strict_types=1);

final class Csrf
{
    public static function token(): string
    {
        if (empty($_SESSION['_csrf']) || !is_string($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf'];
    }

    public static function verify(): void
    {
        $sent = (string) ($_POST['_csrf'] ?? '');
        if ($sent === '' || !hash_equals(self::token(), $sent)) {
            flash('danger', 'Η φόρμα έληξε για λόγους ασφάλειας. Δοκιμάστε ξανά.');
            $fallback = Config::installed() && !empty($_SESSION['uid']) ? '/' : (Config::setupAllowed() ? '/setup' : '/login');
            redirect($fallback);
        }
    }

    public static function rotate(): void
    {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
}
