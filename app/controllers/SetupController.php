<?php

declare(strict_types=1);

final class SetupController
{
    public static function form(): void
    {
        $db = Config::db();
        render('setup/index', [
            'title' => 'Εγκατάσταση',
            'db' => $db,
            'errors' => [],
            'old' => [
                'header_name' => 'ΣΦΡΑΓΙΣ',
                'site_url' => current_origin(),
                'server_url' => current_origin(),
                'last_name' => '',
                'first_name' => '',
                'department' => '',
                'email' => '',
                'private_networks' => '1',
            ],
        ], 'layouts/guest');
    }

    public static function test(): void
    {
        $db = self::dbFromPost('');
        $errors = SqlAdmin::validate($db, true);
        if ($errors !== []) {
            self::redisplay($db, $errors, self::oldFromPost());
        }
        $result = SqlAdmin::test($db);
        flash($result['ok'] ? 'success' : 'danger', $result['message']);
        self::redisplay($db, [], self::oldFromPost());
    }

    public static function install(): void
    {
        $db = self::dbFromPost('');
        $old = self::oldFromPost();
        $errors = SqlAdmin::validate($db, true);
        $errors = array_merge($errors, self::managerErrors($old, post_raw('password')));
        if ($errors !== []) {
            self::redisplay($db, $errors, $old);
        }

        try {
            $message = SqlAdmin::initialize($db);
            $pdo = Database::tryConnect($db, true);
            $users = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
            if ($users > 0) {
                throw new RuntimeException('Η βάση περιέχει ήδη χρήστες. Χρησιμοποιήστε μια κενή βάση για την πρώτη εγκατάσταση.');
            }
            $stamp = now();
            $stmt = $pdo->prepare(
                'INSERT INTO users (last_name, first_name, department, email, password_hash, role, active, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, \'manager\', 1, ?, ?)'
            );
            $stmt->execute([
                $old['last_name'],
                $old['first_name'],
                $old['department'],
                $old['email'],
                password_hash(post_raw('password'), PASSWORD_DEFAULT),
                $stamp,
                $stamp,
            ]);
            $settings = $pdo->prepare('INSERT INTO settings (skey, svalue) VALUES (?, ?)');
            foreach ([
                'site_url' => rtrim($old['site_url'], '/'),
                'server_url' => rtrim($old['server_url'] !== '' ? $old['server_url'] : $old['site_url'], '/'),
                'header_name' => $old['header_name'],
                'footer_contact' => '',
                'footer_credits' => '',
                'default_validity_months' => '12',
            ] as $key => $value) {
                $settings->execute([$key, $value]);
            }
            if ($old['private_networks'] === '1') {
                $net = $pdo->prepare('INSERT INTO allowed_networks (cidr, label, created_at) VALUES (?, ?, ?)');
                foreach ([
                    ['10.0.0.0/8', 'Ιδιωτικό δίκτυο 10.0.0.0/8'],
                    ['172.16.0.0/12', 'Ιδιωτικό δίκτυο 172.16.0.0/12'],
                    ['192.168.0.0/16', 'Ιδιωτικό δίκτυο 192.168.0.0/16'],
                    ['127.0.0.1/32', 'Τοπικός διακομιστής'],
                ] as [$cidr, $label]) {
                    $net->execute([$cidr, $label, $stamp]);
                }
            }
            Config::write($db, true);
            Config::writeLock();
            Database::reset();
            Settings::flush();
        } catch (Throwable $e) {
            log_exception($e);
            $errors[] = $e->getMessage();
            self::redisplay($db, $errors, $old);
        }

        flash('success', 'Η εγκατάσταση ολοκληρώθηκε. Συνδεθείτε με τον λογαριασμό διαχειριστή. ' . $message);
        redirect('/login');
    }

    private static function dbFromPost(string $fallback): array
    {
        $password = post_raw('db_pass');
        return [
            'host' => post_string('db_host', 253),
            'port' => (int) post_raw('db_port'),
            'name' => post_string('db_name', 64),
            'user' => post_string('db_user', 64),
            'pass' => $password !== '' ? $password : $fallback,
        ];
    }

    private static function oldFromPost(): array
    {
        return [
            'header_name' => post_string('header_name', 120),
            'site_url' => post_string('site_url', 255),
            'server_url' => post_string('server_url', 255),
            'last_name' => post_string('last_name', 100),
            'first_name' => post_string('first_name', 100),
            'department' => post_string('department', 150),
            'email' => normalize_email(post_string('email', 190)),
            'private_networks' => post_raw('private_networks') === '1' ? '1' : '',
        ];
    }

    private static function managerErrors(array $old, string $password): array
    {
        $errors = [];
        if (!safe_person_name($old['last_name'])) {
            $errors[] = 'Συμπληρώστε έγκυρο επώνυμο διαχειριστή.';
        }
        if (!safe_person_name($old['first_name'])) {
            $errors[] = 'Συμπληρώστε έγκυρο όνομα διαχειριστή.';
        }
        if ($old['department'] !== '' && mb_strlen($old['department']) > 150) {
            $errors[] = 'Το τμήμα είναι πολύ μεγάλο.';
        }
        if (!valid_email($old['email'])) {
            $errors[] = 'Το email του διαχειριστή δεν είναι έγκυρο.';
        }
        $problem = password_problem($password);
        if ($problem !== null) {
            $errors[] = $problem;
        }
        if (post_raw('password_confirm') !== $password) {
            $errors[] = 'Η επιβεβαίωση του κωδικού δεν ταιριάζει.';
        }
        if (mb_strlen($old['header_name']) < 2) {
            $errors[] = 'Συμπληρώστε το όνομα που θα φαίνεται στην κεφαλίδα.';
        }
        if (!self::validSiteUrl($old['site_url'])) {
            $errors[] = 'Το URL του QR πρέπει να αρχίζει από http:// ή https://.';
        }
        if ($old['server_url'] !== '' && !self::validSiteUrl($old['server_url'])) {
            $errors[] = 'Το URL του διακομιστή πρέπει να αρχίζει από http:// ή https://.';
        }
        return $errors;
    }

    public static function validSiteUrl(string $url): bool
    {
        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            return false;
        }
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        return $scheme === 'http' || $scheme === 'https';
    }

    private static function redisplay(array $db, array $errors, array $old): never
    {
        render('setup/index', [
            'title' => 'Εγκατάσταση',
            'db' => $db,
            'errors' => $errors,
            'old' => $old,
        ], 'layouts/guest');
    }
}
