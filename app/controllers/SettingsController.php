<?php

declare(strict_types=1);

final class SettingsController
{
    public static function index(): void
    {
        $user = Auth::requireManager();
        $db = Config::db();
        $db['pass'] = '';
        render('settings/index', [
            'title' => 'Παράμετροι',
            'db' => $db,
            'networks' => NetworkGuard::list(),
            'backups' => SqlAdmin::backups(),
            'summary' => self::summary(),
            'user' => $user,
        ]);
    }

    public static function saveProfile(): void
    {
        $actor = Auth::requireManager();
        $header = post_string('header_name', 120);
        $site = rtrim(post_string('site_url', 255), '/');
        $server = rtrim(post_string('server_url', 255), '/');
        $contact = post_string('footer_contact', 500);
        $credits = post_string('footer_credits', 500);
        $months = (int) post_raw('default_validity_months');
        $placement = post_string('stamp_placement', 20);
        $errors = [];
        if (mb_strlen($header) < 2) {
            $errors[] = 'Συμπληρώστε το όνομα της κεφαλίδας.';
        }
        if (!SetupController::validSiteUrl($site)) {
            $errors[] = 'Το URL του QR πρέπει να αρχίζει από http:// ή https://.';
        }
        if (!SetupController::validSiteUrl($server)) {
            $errors[] = 'Το URL του διακομιστή πρέπει να αρχίζει από http:// ή https://.';
        }
        if ($months < 0 || $months > 120) {
            $errors[] = 'Η προεπιλεγμένη διάρκεια ισχύος πρέπει να είναι από 0 έως 120 μήνες. Το 0 σημαίνει χωρίς λήξη.';
        }
        if (!in_array($placement, ['appendix', 'header', 'footer'], true)) {
            $errors[] = 'Επιλέξτε πού θα μπαίνει η ψηφιακή σφραγίδα.';
        }
        if ($errors !== []) {
            flash('danger', implode(' ', $errors));
            redirect('/settings');
        }
        Settings::setMany([
            'header_name' => $header,
            'site_url' => $site,
            'server_url' => $server,
            'footer_contact' => $contact,
            'footer_credits' => $credits,
            'default_validity_months' => (string) $months,
            'stamp_placement' => $placement,
        ]);
        Logger::record((int) $actor['id'], 'settings', 'Ενημέρωση εμφάνισης, URL και μορφής ψηφιακής σφραγίδας');
        flash('success', 'Οι παράμετροι αποθηκεύτηκαν. Τα νέα έγγραφα σφραγίζονται με τη μορφή που επιλέξατε.');
        redirect('/settings');
    }

    public static function saveMail(): void
    {
        $actor = Auth::requireManager();
        $mail = self::mailFromPost();
        $errors = self::mailErrors($mail, false);
        if ($errors !== []) {
            flash('danger', implode(' ', $errors));
            redirect('/settings');
        }
        Settings::setMany([
            'smtp_host' => $mail['host'],
            'smtp_port' => (string) $mail['port'],
            'smtp_username' => $mail['username'],
            'smtp_password' => $mail['password'],
            'smtp_from_name' => $mail['from_name'],
            'smtp_from_email' => $mail['from_email'],
            'smtp_security' => $mail['security'],
            'notify_secretary' => $mail['notify_secretary'],
            'notify_extra' => $mail['notify_extra'],
            'notify_extra_email' => $mail['notify_extra_email'],
        ]);
        Logger::record((int) $actor['id'], 'settings', 'Ενημέρωση ρυθμίσεων email');
        flash('success', 'Οι ρυθμίσεις email αποθηκεύτηκαν.');
        redirect('/settings');
    }

    public static function testMail(): void
    {
        $actor = Auth::requireManager();
        $mail = self::mailFromPost();
        $errors = self::mailErrors($mail, true);
        if ($errors !== []) {
            flash('danger', implode(' ', $errors));
            redirect('/settings');
        }
        try {
            Mailer::send(
                $mail,
                (string) $actor['email'],
                'Δοκιμαστικό μήνυμα ΣΦΡΑΓΙΣ',
                '<p>Αυτό είναι δοκιμαστικό μήνυμα από το ΣΦΡΑΓΙΣ.</p><p><a href="' . e(Settings::serverUrl()) . '">Πατήστε εδώ</a></p>',
                "Αυτό είναι δοκιμαστικό μήνυμα από το ΣΦΡΑΓΙΣ.\nΠατήστε εδώ: " . Settings::serverUrl() . "\n"
            );
        } catch (Throwable $e) {
            log_exception($e);
            Logger::record((int) $actor['id'], 'mail_test', 'Ανεπιτυχής δοκιμή email προς ' . $actor['email']);
            flash('danger', $e instanceof RuntimeException ? $e->getMessage() : 'Η δοκιμή email απέτυχε.');
            redirect('/settings');
        }
        Logger::record((int) $actor['id'], 'mail_test', 'Επιτυχής δοκιμή email προς ' . $actor['email']);
        flash('success', 'Το δοκιμαστικό μήνυμα στάλθηκε στο ' . $actor['email'] . '.');
        redirect('/settings');
    }

    public static function saveNetworks(): void
    {
        $actor = Auth::requireManager();
        $cidrs = $_POST['cidr'] ?? [];
        $labels = $_POST['label'] ?? [];
        if (!is_array($cidrs) || !is_array($labels)) {
            flash('danger', 'Μη έγκυρη υποβολή δικτύων.');
            redirect('/settings');
        }
        $networks = [];
        $errors = [];
        $count = max(count($cidrs), count($labels));
        for ($i = 0; $i < $count; $i++) {
            $raw = trim((string) ($cidrs[$i] ?? ''));
            $label = trim((string) ($labels[$i] ?? ''));
            if ($raw === '' && $label === '') {
                continue;
            }
            $cidr = normalize_cidr($raw);
            if ($cidr === null) {
                $errors[] = 'Μη έγκυρο δίκτυο: ' . mb_substr($raw, 0, 64);
                continue;
            }
            $networks[] = ['cidr' => $cidr, 'label' => mb_substr($label, 0, 150)];
        }
        if ($errors !== []) {
            flash('danger', implode(' ', $errors));
            redirect('/settings');
        }
        NetworkGuard::replace($networks);
        Logger::record((int) $actor['id'], 'network', 'Ενημέρωση επιτρεπόμενων δικτύων (' . count($networks) . ')');
        flash('success', count($networks) === 0
            ? 'Τα δίκτυα αφαιρέθηκαν. Οι χρήστες και η γραμματεία μπορούν προσωρινά να συνδεθούν από οποιαδήποτε διεύθυνση.'
            : 'Τα εσωτερικά δίκτυα ενημερώθηκαν. Οι χρήστες και η γραμματεία συνδέονται με κωδικό μόνο από αυτά.');
        redirect('/settings');
    }

    public static function testSql(): void
    {
        $actor = Auth::requireManager();
        $db = self::dbFromPost();
        $errors = SqlAdmin::validate($db, false);
        if ($db['pass'] === '') {
            $errors[] = 'Συμπληρώστε τον κωδικό MySQL για τον έλεγχο ή αποθηκεύστε πρώτα τα στοιχεία.';
        }
        if ($errors !== []) {
            flash('danger', implode(' ', $errors));
            redirect('/settings');
        }
        $result = SqlAdmin::test($db);
        Logger::record((int) $actor['id'], 'sql_test', ($result['ok'] ? 'Επιτυχής' : 'Ανεπιτυχής') . ' έλεγχος SQL για ' . $db['host'] . '/' . $db['name']);
        flash($result['database'] ? 'success' : ($result['ok'] ? 'warning' : 'danger'), $result['message']);
        redirect('/settings');
    }

    public static function saveSql(): void
    {
        $actor = Auth::requireManager();
        if (!Auth::verifyPassword($actor, post_raw('account_password'))) {
            flash('danger', 'Ο κωδικός διαχειριστή δεν επιβεβαιώθηκε.');
            redirect('/settings');
        }
        $db = self::dbFromPost();
        $errors = SqlAdmin::validate($db, true);
        if ($errors !== []) {
            flash('danger', implode(' ', $errors));
            redirect('/settings');
        }
        $result = SqlAdmin::test($db);
        if (!$result['database']) {
            flash('danger', $result['message']);
            redirect('/settings');
        }
        Config::write($db, true);
        Database::reset();
        Settings::flush();
        Logger::record((int) $actor['id'], 'settings', 'Ενημέρωση στοιχείων σύνδεσης MySQL για ' . $db['host'] . '/' . $db['name']);
        flash('success', 'Τα στοιχεία MySQL αποθηκεύτηκαν και η εφαρμογή συνδέθηκε στη βάση.');
        redirect('/settings');
    }

    public static function initSql(): void
    {
        $actor = Auth::requireManager();
        if (!Auth::verifyPassword($actor, post_raw('account_password'))) {
            flash('danger', 'Ο κωδικός διαχειριστή δεν επιβεβαιώθηκε.');
            redirect('/settings');
        }
        if (post_raw('understand') !== '1') {
            flash('danger', 'Επιβεβαιώστε ότι η εφαρμογή θα συνδεθεί σε αυτή τη βάση.');
            redirect('/settings');
        }
        $db = self::dbFromPost();
        $errors = SqlAdmin::validate($db, true);
        if ($errors !== []) {
            flash('danger', implode(' ', $errors));
            redirect('/settings');
        }
        try {
            $message = SqlAdmin::initialize($db);
            Config::write($db, true);
            Database::reset();
            Settings::flush();
            self::ensureDefaultSettings();
        } catch (Throwable $e) {
            log_exception($e);
            flash('danger', $e->getMessage());
            redirect('/settings');
        }
        Logger::record((int) $actor['id'], 'sql_init', $message);
        flash('success', $message);
        redirect('/settings');
    }

    public static function backup(): void
    {
        $actor = Auth::requireManager();
        if (!Auth::verifyPassword($actor, post_raw('account_password'))) {
            flash('danger', 'Ο κωδικός διαχειριστή δεν επιβεβαιώθηκε.');
            redirect('/settings');
        }
        try {
            $file = SqlAdmin::backup(Config::db());
        } catch (Throwable $e) {
            log_exception($e);
            flash('danger', $e->getMessage());
            redirect('/settings');
        }
        Logger::record((int) $actor['id'], 'sql_backup', 'Δημιουργία αντιγράφου ' . basename($file));
        send_download_sql($file);
    }

    public static function downloadBackup(string $name): void
    {
        Auth::requireManager();
        try {
            $path = SqlAdmin::backupPath($name);
        } catch (Throwable) {
            not_found();
        }
        send_download_sql($path);
    }

    public static function restore(): void
    {
        $actor = Auth::requireManager();
        if (!Auth::verifyPassword($actor, post_raw('account_password'))) {
            flash('danger', 'Ο κωδικός διαχειριστή δεν επιβεβαιώθηκε.');
            redirect('/settings');
        }
        if (post_string('confirm', 20) !== 'ΕΠΑΝΑΦΟΡΑ') {
            flash('danger', 'Για επαναφορά πληκτρολογήστε ακριβώς τη λέξη ΕΠΑΝΑΦΟΡΑ.');
            redirect('/settings');
        }
        $file = $_FILES['sql'] ?? null;
        if (!is_array($file) || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            flash('danger', 'Επιλέξτε ένα αρχείο SQL.');
            redirect('/settings');
        }
        $size = (int) ($file['size'] ?? 0);
        if ($size < 32 || $size > 64 * 1024 * 1024) {
            flash('danger', 'Το αρχείο SQL πρέπει να είναι έως 64 MB.');
            redirect('/settings');
        }
        $tmp = BASE_PATH . '/storage/tmp/restore-' . bin2hex(random_bytes(8)) . '.sql';
        if (!move_uploaded_file((string) $file['tmp_name'], $tmp)) {
            flash('danger', 'Η μεταφόρτωση του αρχείου απέτυχε.');
            redirect('/settings');
        }
        chmod($tmp, 0600);
        try {
            SqlAdmin::restore(Config::db(), $tmp);
            Database::reset();
            Settings::flush();
            Auth::flush();
        } catch (Throwable $e) {
            @unlink($tmp);
            log_exception($e);
            flash('danger', $e->getMessage());
            redirect('/settings');
        }
        @unlink($tmp);
        Logger::record((int) $actor['id'], 'sql_restore', 'Επαναφορά βάσης από αρχείο SQL');
        flash('success', 'Η βάση επαναφέρθηκε από το αρχείο SQL.');
        redirect('/settings');
    }

    private static function mailFromPost(): array
    {
        $security = post_string('smtp_security', 10);
        if (!in_array($security, ['tls', 'ssl', 'none'], true)) {
            $security = 'tls';
        }
        $password = post_raw('smtp_password');
        if ($password === '') {
            $password = Settings::secret('smtp_password');
        }
        $port = (int) post_raw('smtp_port');
        if ($port === 0) {
            $port = 587;
        }
        $mail = Mailer::normalize([
            'host' => post_string('smtp_host', 253),
            'port' => $port,
            'username' => post_string('smtp_username', 190),
            'password' => $password,
            'from_name' => post_string('smtp_from_name', 120),
            'from_email' => post_string('smtp_from_email', 190),
            'security' => $security,
        ]);
        $mail['notify_secretary'] = post_raw('notify_secretary') === '1' ? '1' : '0';
        $mail['notify_extra'] = post_raw('notify_extra') === '1' ? '1' : '0';
        $mail['notify_extra_email'] = normalize_email(post_string('notify_extra_email', 190));
        return $mail;
    }

    private static function mailErrors(array $mail, bool $requireReady): array
    {
        $errors = [];
        $wantsSend = ($mail['notify_secretary'] ?? '0') === '1' || ($mail['notify_extra'] ?? '0') === '1';
        if ($requireReady || $mail['host'] !== '' || $wantsSend) {
            if (!valid_host($mail['host'])) {
                $errors[] = 'Ο διακομιστής SMTP δεν είναι έγκυρος.';
            }
            if ($mail['port'] < 1 || $mail['port'] > 65535) {
                $errors[] = 'Η θύρα SMTP πρέπει να είναι από 1 έως 65535.';
            }
            if (mb_strlen($mail['from_name']) < 2) {
                $errors[] = 'Συμπληρώστε το όνομα αποστολέα.';
            }
            if (!valid_email($mail['from_email'])) {
                $errors[] = 'Το email αποστολέα δεν είναι έγκυρο.';
            }
        }
        if (strlen($mail['password']) > 200) {
            $errors[] = 'Ο κωδικός SMTP είναι πολύ μεγάλος.';
        }
        if (($mail['notify_extra'] ?? '0') === '1' && !valid_email((string) $mail['notify_extra_email'])) {
            $errors[] = 'Συμπληρώστε έγκυρο email για την επιπλέον προώθηση.';
        }
        return $errors;
    }

    private static function dbFromPost(): array
    {
        $current = Config::db();
        $password = post_raw('db_pass');
        return [
            'host' => post_string('db_host', 253),
            'port' => (int) post_raw('db_port'),
            'name' => post_string('db_name', 64),
            'user' => post_string('db_user', 64),
            'pass' => $password !== '' ? $password : (string) $current['pass'],
        ];
    }

    private static function ensureDefaultSettings(): void
    {
        $count = (int) Database::pdo()->query('SELECT COUNT(*) FROM settings')->fetchColumn();
        if ($count > 0) {
            return;
        }
        Settings::setMany([
            'site_url' => current_origin(),
            'server_url' => current_origin(),
            'header_name' => 'ΣΦΡΑΓΙΣ',
            'footer_contact' => '',
            'footer_credits' => '',
            'default_validity_months' => '12',
            'stamp_placement' => 'footer',
        ]);
    }

    private static function summary(): array
    {
        try {
            $pdo = Database::pdo();
            return [
                'documents' => (int) $pdo->query('SELECT COUNT(*) FROM documents WHERE deleted_at IS NULL')->fetchColumn(),
                'users' => (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn(),
                'host' => (string) Config::db()['host'],
                'name' => (string) Config::db()['name'],
            ];
        } catch (Throwable) {
            return ['documents' => 0, 'users' => 0, 'host' => '', 'name' => ''];
        }
    }
}
