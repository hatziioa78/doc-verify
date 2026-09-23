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
        $contact = post_string('footer_contact', 500);
        $credits = post_string('footer_credits', 500);
        $months = (int) post_raw('default_validity_months');
        $errors = [];
        if (mb_strlen($header) < 2) {
            $errors[] = 'Συμπληρώστε το όνομα της κεφαλίδας.';
        }
        if (!SetupController::validSiteUrl($site)) {
            $errors[] = 'Το URL του ιστοτόπου πρέπει να αρχίζει από http:// ή https://.';
        }
        if ($months < 1 || $months > 120) {
            $errors[] = 'Η προεπιλεγμένη διάρκεια ισχύος πρέπει να είναι από 1 έως 120 μήνες.';
        }
        if ($errors !== []) {
            flash('danger', implode(' ', $errors));
            redirect('/settings');
        }
        Settings::setMany([
            'header_name' => $header,
            'site_url' => $site,
            'footer_contact' => $contact,
            'footer_credits' => $credits,
            'default_validity_months' => (string) $months,
        ]);
        Logger::record((int) $actor['id'], 'settings', 'Ενημέρωση εμφάνισης και προεπιλεγμένης ισχύος');
        flash('success', 'Οι παράμετροι εμφάνισης αποθηκεύτηκαν. Τα νέα QR χρησιμοποιούν το δηλωμένο URL.');
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
            ? 'Τα δίκτυα αφαιρέθηκαν. Οι χρήστες μπορούν προσωρινά να συνδεθούν από οποιαδήποτε διεύθυνση.'
            : 'Τα εσωτερικά δίκτυα ενημερώθηκαν. Οι χρήστες συνδέονται μόνο από αυτά.');
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
            'header_name' => 'ΣΦΡΑΓΙΣ',
            'footer_contact' => '',
            'footer_credits' => '',
            'default_validity_months' => '12',
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
