<?php

declare(strict_types=1);

final class Auth
{
    private static ?array $user = null;
    private static bool $resolved = false;

    public static function user(): ?array
    {
        if (self::$resolved) {
            return self::$user;
        }
        self::$resolved = true;
        $id = (int) ($_SESSION['uid'] ?? 0);
        if ($id < 1 || !Config::installed()) {
            return null;
        }
        $stmt = Database::pdo()->prepare(
            'SELECT id, full_name, department, email, password_hash, password_insecure, role, active, certify_without_approval, created_at
             FROM users WHERE id = ? LIMIT 1'
        );
        $stmt->execute([$id]);
        $user = $stmt->fetch();
        self::$user = $user ?: null;
        return self::$user;
    }

    public static function flush(): void
    {
        self::$user = null;
        self::$resolved = false;
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function isManager(?array $user = null): bool
    {
        $user ??= self::user();
        return $user !== null && ($user['role'] ?? '') === 'manager';
    }

    public static function canApprove(?array $user = null): bool
    {
        $user ??= self::user();
        return can_approve($user);
    }

    public static function attempt(string $email, string $password): array
    {
        $email = normalize_email($email);
        $stmt = Database::pdo()->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch() ?: null;
        return self::finishAttempt($user, $password, client_ip(), $email);
    }

    public static function attemptId(int $id, string $password): array
    {
        $ip = client_ip();
        if (!NetworkGuard::onSecureNetwork($ip)) {
            return ['ok' => false, 'error' => 'Η επιλογή ονόματος επιτρέπεται μόνο από ασφαλές υποδίκτυο.'];
        }
        $user = null;
        if ($id > 0) {
            $stmt = Database::pdo()->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
            $stmt->execute([$id]);
            $user = $stmt->fetch() ?: null;
        }
        return self::finishAttempt($user, $password, $ip, $user ? (string) $user['email'] : '');
    }

    public static function logout(): void
    {
        $user = self::user();
        if ($user) {
            try {
                Logger::record((int) $user['id'], 'logout', 'Αποσύνδεση του ' . $user['email']);
            } catch (Throwable $e) {
                log_exception($e);
            }
        }
        $_SESSION = [];
        session_regenerate_id(true);
        self::flush();
    }

    public static function requireUser(): array
    {
        $user = self::user();
        if (!$user) {
            flash('warning', 'Συνδεθείτε για να συνεχίσετε.');
            redirect('/login');
        }
        $fingerprint = (string) ($_SESSION['_fp'] ?? '');
        if ($fingerprint === '' || !hash_equals($fingerprint, self::fingerprint())) {
            self::logout();
            flash('warning', 'Η συνεδρία διακόπηκε για λόγους ασφάλειας. Συνδεθείτε ξανά.');
            redirect('/login');
        }
        $last = (int) ($_SESSION['_last'] ?? 0);
        if ($last > 0 && (time() - $last) > 7200) {
            self::logout();
            flash('warning', 'Η συνεδρία έληξε λόγω αδράνειας. Συνδεθείτε ξανά.');
            redirect('/login');
        }
        if (time() - (int) ($_SESSION['_regen'] ?? 0) > 900) {
            session_regenerate_id(true);
            $_SESSION['_regen'] = time();
        }
        $_SESSION['_last'] = time();

        if ((int) $user['active'] !== 1) {
            self::logout();
            flash('danger', 'Ο λογαριασμός είναι ανενεργός.');
            redirect('/login');
        }

        $magicSecretary = (int) ($_SESSION['_magic'] ?? 0) === 1 && ($user['role'] ?? '') === 'secretary';
        $block = $magicSecretary ? null : self::networkBlock($user, client_ip());
        if ($block !== null) {
            $email = (string) $user['email'];
            $id = (int) $user['id'];
            self::logout();
            Logger::record($id, 'login_blocked', 'Διακοπή συνεδρίας εκτός εγκεκριμένου δικτύου για ' . $email);
            flash('danger', $block);
            redirect('/login');
        }

        return $user;
    }

    public static function requireManager(): array
    {
        $user = self::requireUser();
        if (($user['role'] ?? '') !== 'manager') {
            forbidden();
        }
        return $user;
    }

    public static function requireApprover(): array
    {
        $user = self::requireUser();
        if (!self::canApprove($user)) {
            forbidden();
        }
        return $user;
    }

    public static function startMagicSession(array $user, int $documentId): void
    {
        session_regenerate_id(true);
        $_SESSION = [];
        $_SESSION['uid'] = (int) $user['id'];
        $_SESSION['_fp'] = self::fingerprint();
        $_SESSION['_last'] = time();
        $_SESSION['_regen'] = time();
        $_SESSION['_magic'] = 1;
        Csrf::rotate();
        self::flush();
        Logger::record((int) $user['id'], 'login', 'Σύνδεση Γραμματείας από σύνδεσμο επιβεβαίωσης', $documentId);
    }

    public static function fingerprint(): string
    {
        return hash('sha256', (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));
    }

    public static function verifyPassword(array $user, string $password): bool
    {
        return $password !== '' && password_verify($password, (string) ($user['password_hash'] ?? ''));
    }

    private static function finishAttempt(?array $user, string $password, string $ip, string $email): array
    {
        if (self::tooManyAttempts($ip, $email)) {
            return ['ok' => false, 'error' => 'Πάρα πολλές προσπάθειες σύνδεσης. Δοκιμάστε ξανά σε λίγα λεπτά.'];
        }

        $valid = $user && password_verify($password, (string) $user['password_hash']);
        if (!$valid) {
            self::recordAttempt($ip, $email);
            Logger::record($user ? (int) $user['id'] : null, 'login_failed', 'Αποτυχημένη προσπάθεια για ' . ($email !== '' ? $email : 'κενό email'));
            return ['ok' => false, 'error' => 'Τα στοιχεία σύνδεσης δεν είναι σωστά.'];
        }

        if ((int) $user['active'] !== 1) {
            self::recordAttempt($ip, $email);
            Logger::record((int) $user['id'], 'login_failed', 'Προσπάθεια σύνδεσης σε ανενεργό λογαριασμό');
            return ['ok' => false, 'error' => 'Ο λογαριασμός είναι ανενεργός.'];
        }

        $block = self::networkBlock($user, $ip);
        if ($block !== null) {
            Logger::record((int) $user['id'], 'login_blocked', 'Απόρριψη σύνδεσης από ' . $ip . ' για ' . $user['email']);
            return ['ok' => false, 'error' => $block];
        }

        self::clearAttempts($ip, $email);
        session_regenerate_id(true);
        $_SESSION['uid'] = (int) $user['id'];
        $_SESSION['_fp'] = self::fingerprint();
        $_SESSION['_last'] = time();
        $_SESSION['_regen'] = time();
        unset($_SESSION['_magic']);
        Csrf::rotate();
        self::flush();
        Logger::record((int) $user['id'], 'login', 'Επιτυχής σύνδεση του ' . $user['email']);
        return ['ok' => true, 'error' => ''];
    }

    private static function networkBlock(array $user, string $ip): ?string
    {
        $allows = NetworkGuard::allows($ip);
        if ((int) ($user['password_insecure'] ?? 0) === 1 && !$allows) {
            return 'Ο κωδικός δεν είναι ασφαλής. Η σύνδεση επιτρέπεται μόνο από ασφαλές υποδίκτυο.';
        }
        if (($user['role'] ?? '') !== 'manager' && !$allows) {
            return 'Η σύνδεση χρηστών και γραμματείας επιτρέπεται μόνο από τα εγκεκριμένα εσωτερικά δίκτυα.';
        }
        return null;
    }

    private static function tooManyAttempts(string $ip, string $email): bool
    {
        $pdo = Database::pdo();
        $pdo->prepare('DELETE FROM login_attempts WHERE created_at < ?')->execute([date('Y-m-d H:i:s', time() - 86400)]);
        $since = date('Y-m-d H:i:s', time() - 900);
        $byIp = $pdo->prepare('SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND created_at >= ?');
        $byIp->execute([$ip, $since]);
        if ((int) $byIp->fetchColumn() >= 8) {
            return true;
        }
        if ($email === '') {
            return false;
        }
        $byEmail = $pdo->prepare('SELECT COUNT(*) FROM login_attempts WHERE email = ? AND created_at >= ?');
        $byEmail->execute([$email, $since]);
        return (int) $byEmail->fetchColumn() >= 8;
    }

    private static function recordAttempt(string $ip, string $email): void
    {
        $stmt = Database::pdo()->prepare('INSERT INTO login_attempts (ip, email, created_at) VALUES (?, ?, ?)');
        $stmt->execute([$ip, mb_substr($email, 0, 190), now()]);
    }

    private static function clearAttempts(string $ip, string $email): void
    {
        $stmt = Database::pdo()->prepare('DELETE FROM login_attempts WHERE ip = ? OR email = ?');
        $stmt->execute([$ip, $email]);
    }
}
