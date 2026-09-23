<?php

declare(strict_types=1);

final class SetupController
{
    public static function form(): void
    {
        $db = Config::db();
        $db['pass'] = '';
        $checks = self::checks();
        render('setup/index', [
            'title' => 'Εγκατάσταση',
            'db' => $db,
            'errors' => [],
            'checks' => $checks,
            'ready' => self::ready($checks),
            'old' => self::blank(),
        ], 'layouts/guest');
    }

    public static function checks(): array
    {
        $checks = [];
        $phpOk = PHP_VERSION_ID >= 80200;
        $checks[] = self::check(
            'PHP 8.2 ή νεότερη',
            $phpOk,
            $phpOk ? 'Εκτελείται η PHP ' . PHP_VERSION . '.' : 'Βρέθηκε η PHP ' . PHP_VERSION . '. Χρειάζεται 8.2 ή νεότερη.',
            true
        );

        $extensions = [
            'pdo_mysql' => 'Σύνδεση MySQL',
            'mbstring' => 'Κείμενο στα ελληνικά',
            'fileinfo' => 'Έλεγχος αρχείων PDF',
            'gd' => 'Εικόνα του QR',
            'openssl' => 'Κρυπτογραφημένο SMTP',
            'json' => 'Ρυθμίσεις εφαρμογής',
        ];
        $missing = [];
        foreach ($extensions as $name => $why) {
            if (!extension_loaded($name)) {
                $missing[] = $name . ' (' . $why . ')';
            }
        }
        $checks[] = self::check(
            'Επεκτάσεις PHP',
            $missing === [],
            $missing === [] ? 'Υπάρχουν οι pdo_mysql, mbstring, fileinfo, gd, openssl και json.' : 'Λείπουν: ' . implode(', ', $missing) . '.',
            true
        );

        $vendor = is_file(BASE_PATH . '/vendor/autoload.php');
        $checks[] = self::check(
            'Βιβλιοθήκες Composer',
            $vendor,
            $vendor ? 'Ο φάκελος vendor είναι στη θέση του.' : 'Τρέξτε composer install στον κατάλογο της εφαρμογής.',
            true
        );

        $procDisabled = in_array('proc_open', self::disabledFunctions(), true) || !function_exists('proc_open');
        $checks[] = self::check(
            'Εκτέλεση εξωτερικών προγραμμάτων',
            !$procDisabled,
            $procDisabled ? 'Η proc_open είναι απενεργοποιημένη και το qpdf δεν μπορεί να σφραγίσει PDF.' : 'Η proc_open είναι διαθέσιμη.',
            true
        );

        $qpdf = self::qpdfVersion();
        $checks[] = self::check(
            'Εργαλείο qpdf',
            $qpdf !== null,
            $qpdf !== null ? $qpdf : 'Εγκαταστήστε το πακέτο qpdf. Χωρίς αυτό δεν προστίθεται η σελίδα του QR.',
            true
        );

        $folders = self::unwritableFolders();
        $checks[] = self::check(
            'Φάκελοι εγγραφής',
            $folders === [],
            $folders === [] ? 'Μπορούν να γραφτούν οι ρυθμίσεις, τα PDF και τα αντίγραφα.' : 'Δεν γράφονται: ' . implode(', ', $folders) . '. Δώστε δικαίωμα στον χρήστη του Apache.',
            true
        );

        $upload = self::iniBytes((string) ini_get('upload_max_filesize'));
        $post = self::iniBytes((string) ini_get('post_max_size'));
        $limitsOk = ($upload === 0 || $upload >= 20 * 1024 * 1024) && ($post === 0 || $post >= 24 * 1024 * 1024);
        $checks[] = self::check(
            'Όριο μεταφόρτωσης',
            $limitsOk,
            $limitsOk
                ? 'Τα όρια είναι upload_max_filesize=' . ini_get('upload_max_filesize') . ' και post_max_size=' . ini_get('post_max_size') . '.'
                : 'Τώρα είναι upload_max_filesize=' . ini_get('upload_max_filesize') . ' και post_max_size=' . ini_get('post_max_size') . '. Ορίστε τουλάχιστον 21M και 24M.',
            true
        );

        $apache = isset($_SERVER['SERVER_SOFTWARE']) && stripos((string) $_SERVER['SERVER_SOFTWARE'], 'Apache') !== false;
        $rewrite = null;
        if (function_exists('apache_get_modules')) {
            $rewrite = in_array('mod_rewrite', apache_get_modules(), true);
        }
        if ($apache && $rewrite === false) {
            $checks[] = self::check('Apache rewrite', false, 'Ενεργοποιήστε το mod_rewrite με a2enmod rewrite και επιτρέψτε το .htaccess με AllowOverride All.', true);
        } else {
            $detail = $rewrite === true
                ? 'Το mod_rewrite είναι ενεργό.'
                : ($apache
                    ? 'Εντοπίστηκε Apache. Με php-fpm βεβαιωθείτε ότι ισχύουν τα a2enmod rewrite και AllowOverride All στον φάκελο public/.'
                    : 'Δεν εντοπίστηκε Apache σε αυτή την αίτηση. Στον Ubuntu ο ιστότοπος πρέπει να δείχνει στον φάκελο public/.');
            $checks[] = self::check('Apache και rewrite', $rewrite === true, $detail, false);
        }

        $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
        $checks[] = self::check(
            'HTTPS',
            $https,
            $https ? 'Η αίτηση ήρθε με HTTPS.' : 'Σε παραγωγικό διακομιστή εξυπηρετήστε το ΣΦΡΑΓΙΣ με HTTPS.',
            false
        );

        return $checks;
    }

    public static function ready(array $checks): bool
    {
        foreach ($checks as $check) {
            if ($check['required'] && !$check['ok']) {
                return false;
            }
        }
        return true;
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

    public static function testMail(): void
    {
        $db = self::dbFromPost('');
        $old = self::oldFromPost();
        $errors = self::mailErrors($old, true);
        $recipient = $old['mail_test_to'] !== '' ? $old['mail_test_to'] : $old['email'];
        if (!valid_email($recipient)) {
            $errors[] = 'Συμπληρώστε έγκυρο email παραλήπτη για τη δοκιμή, ή το email του διαχειριστή.';
        }
        if ($errors !== []) {
            self::redisplay($db, $errors, $old);
        }
        try {
            Mailer::send(
                $old,
                $recipient,
                'Δοκιμαστικό μήνυμα ΣΦΡΑΓΙΣ',
                '<p>Η δοκιμή SMTP του ΣΦΡΑΓΙΣ πέτυχε.</p><p><a href="' . e(self::linkBase($old)) . '">Πατήστε εδώ</a></p>',
                "Η δοκιμή SMTP του ΣΦΡΑΓΙΣ πέτυχε.\nΠατήστε εδώ: " . self::linkBase($old) . "\n"
            );
        } catch (Throwable $e) {
            log_exception($e);
            $errors[] = $e instanceof RuntimeException ? $e->getMessage() : 'Η δοκιμή email απέτυχε.';
            self::redisplay($db, $errors, $old);
        }
        flash('success', 'Το δοκιμαστικό μήνυμα στάλθηκε στο ' . $recipient . '.');
        self::redisplay($db, [], $old);
    }

    public static function install(): void
    {
        $db = self::dbFromPost('');
        $old = self::oldFromPost();
        $errors = [];
        if (!self::ready(self::checks())) {
            $errors[] = 'Η εγκατάσταση σταμάτησε επειδή δεν πέρασαν όλοι οι υποχρεωτικοί έλεγχοι του διακομιστή.';
        }
        $errors = array_merge($errors, SqlAdmin::validate($db, true), self::managerErrors($old, post_raw('password')));
        if (self::mailTouched($old)) {
            $errors = array_merge($errors, self::mailErrors($old, true));
        }
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
                'smtp_host' => $old['host'],
                'smtp_port' => (string) $old['port'],
                'smtp_username' => $old['username'],
                'smtp_password' => $old['password'],
                'smtp_from_name' => $old['from_name'],
                'smtp_from_email' => $old['from_email'],
                'smtp_security' => $old['security'],
                'notify_secretary' => self::mailTouched($old) && $old['notify_secretary'] === '1' ? '1' : '0',
                'notify_extra' => '0',
                'notify_extra_email' => '',
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
        $security = post_string('smtp_security', 10);
        $posted = [
            'header_name' => post_string('header_name', 120),
            'site_url' => post_string('site_url', 255),
            'server_url' => post_string('server_url', 255),
            'last_name' => post_string('last_name', 100),
            'first_name' => post_string('first_name', 100),
            'department' => post_string('department', 150),
            'email' => normalize_email(post_string('email', 190)),
            'private_networks' => post_raw('private_networks') === '1' ? '1' : '',
            'host' => post_string('smtp_host', 253),
            'port' => (int) (post_raw('smtp_port') !== '' ? post_raw('smtp_port') : 587),
            'username' => post_string('smtp_username', 190),
            'password' => post_raw('smtp_password'),
            'from_name' => post_string('smtp_from_name', 120),
            'from_email' => normalize_email(post_string('smtp_from_email', 190)),
            'security' => in_array($security, ['tls', 'ssl', 'none'], true) ? $security : 'tls',
            'notify_secretary' => post_raw('notify_secretary') === '1' ? '1' : '0',
            'mail_test_to' => normalize_email(post_string('mail_test_to', 190)),
        ];
        return array_merge($posted, Mailer::normalize($posted));
    }

    private static function blank(): array
    {
        $origin = current_origin();
        return [
            'header_name' => 'ΣΦΡΑΓΙΣ',
            'site_url' => $origin,
            'server_url' => $origin,
            'last_name' => '',
            'first_name' => '',
            'department' => '',
            'email' => '',
            'private_networks' => '1',
            'host' => '',
            'port' => 587,
            'username' => '',
            'password' => '',
            'from_name' => 'ΣΦΡΑΓΙΣ',
            'from_email' => '',
            'security' => 'tls',
            'notify_secretary' => '1',
            'mail_test_to' => '',
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
        $checks = self::checks();
        render('setup/index', [
            'title' => 'Εγκατάσταση',
            'db' => $db,
            'errors' => $errors,
            'checks' => $checks,
            'ready' => self::ready($checks),
            'old' => $old,
        ], 'layouts/guest');
    }

    private static function mailTouched(array $old): bool
    {
        return $old['host'] !== '' || $old['username'] !== '' || $old['from_email'] !== '' || $old['password'] !== '';
    }

    private static function mailErrors(array $old, bool $requireReady): array
    {
        $errors = [];
        if ($requireReady || self::mailTouched($old)) {
            if (!valid_host($old['host'])) {
                $errors[] = 'Ο διακομιστής SMTP δεν είναι έγκυρος.';
            }
            if ($old['port'] < 1 || $old['port'] > 65535) {
                $errors[] = 'Η θύρα SMTP πρέπει να είναι από 1 έως 65535.';
            }
            if (mb_strlen($old['from_name']) < 2) {
                $errors[] = 'Συμπληρώστε το όνομα αποστολέα.';
            }
            if (!valid_email($old['from_email'])) {
                $errors[] = 'Το email αποστολέα δεν είναι έγκυρο.';
            }
        }
        if (strlen($old['password']) > 200) {
            $errors[] = 'Ο κωδικός SMTP είναι πολύ μεγάλος.';
        }
        return $errors;
    }

    private static function linkBase(array $old): string
    {
        if (self::validSiteUrl($old['server_url'])) {
            return rtrim($old['server_url'], '/');
        }
        if (self::validSiteUrl($old['site_url'])) {
            return rtrim($old['site_url'], '/');
        }
        return current_origin();
    }

    private static function check(string $label, bool $ok, string $detail, bool $required): array
    {
        return [
            'label' => $label,
            'ok' => $ok,
            'detail' => $detail,
            'required' => $required,
        ];
    }

    private static function disabledFunctions(): array
    {
        $raw = (string) ini_get('disable_functions');
        return array_values(array_filter(array_map('trim', explode(',', strtolower($raw)))));
    }

    private static function qpdfVersion(): ?string
    {
        if (!function_exists('proc_open') || in_array('proc_open', self::disabledFunctions(), true)) {
            return null;
        }
        $process = proc_open(['qpdf', '--version'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (!is_resource($process)) {
            return null;
        }
        $output = trim((string) stream_get_contents($pipes[1]) . "\n" . (string) stream_get_contents($pipes[2]));
        fclose($pipes[1]);
        fclose($pipes[2]);
        $code = proc_close($process);
        if ($code !== 0 || $output === '') {
            return null;
        }
        $line = strtok($output, "\r\n") ?: $output;
        return $line;
    }

    private static function unwritableFolders(): array
    {
        $failed = [];
        foreach ([
            'config',
            'storage',
            'storage/pdfs',
            'storage/pdfs/originals',
            'storage/pdfs/certified',
            'storage/tmp',
            'storage/logs',
            'storage/backups',
        ] as $relative) {
            $path = BASE_PATH . '/' . $relative;
            if (!is_dir($path) && !@mkdir($path, 0750, true) && !is_dir($path)) {
                $failed[] = $relative;
                continue;
            }
            if (!is_writable($path)) {
                $failed[] = $relative;
            }
        }
        return $failed;
    }

    private static function iniBytes(string $value): int
    {
        $value = trim($value);
        if ($value === '' || $value === '-1') {
            return 0;
        }
        $unit = strtolower(substr($value, -1));
        $number = (float) $value;
        return (int) match ($unit) {
            'g' => $number * 1073741824,
            'm' => $number * 1048576,
            'k' => $number * 1024,
            default => $number,
        };
    }
}
