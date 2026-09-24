<?php

declare(strict_types=1);

final class UserController
{
    public static function index(): void
    {
        Auth::requireManager();
        $users = Database::pdo()->query(
            "SELECT u.*, (SELECT COUNT(*) FROM documents d WHERE d.owner_id = u.id AND d.deleted_at IS NULL) AS documents_count
             FROM users u ORDER BY FIELD(u.role, 'manager', 'secretary', 'user'), u.full_name"
        )->fetchAll();
        render('users/index', [
            'title' => 'Χρήστες',
            'users' => $users,
        ]);
    }

    public static function createForm(): void
    {
        Auth::requireManager();
        render('users/form', [
            'title' => 'Νέος χρήστης',
            'errors' => [],
            'editing' => null,
            'old' => self::blank(),
        ]);
    }

    public static function store(): void
    {
        $actor = Auth::requireManager();
        $old = self::fromPost(true);
        $errors = self::validate($old, null, post_raw('password'));
        if ($errors !== []) {
            render('users/form', [
                'title' => 'Νέος χρήστης',
                'errors' => $errors,
                'editing' => null,
                'old' => $old,
            ]);
        }
        $stamp = now();
        $password = post_raw('password');
        $stmt = Database::pdo()->prepare(
            'INSERT INTO users (full_name, department, email, password_hash, password_insecure, role, active, certify_without_approval, notify_approval, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        try {
            $stmt->execute([
                $old['full_name'],
                $old['department'],
                $old['email'],
                password_hash($password, PASSWORD_DEFAULT),
                password_is_insecure($password) ? 1 : 0,
                $old['role'],
                $old['active'],
                $old['certify_without_approval'],
                $old['notify_approval'],
                $stamp,
                $stamp,
            ]);
        } catch (PDOException) {
            $errors[] = 'Υπάρχει ήδη χρήστης με αυτό το email.';
            render('users/form', [
                'title' => 'Νέος χρήστης',
                'errors' => $errors,
                'editing' => null,
                'old' => $old,
            ]);
        }
        Logger::record((int) $actor['id'], 'user_create', 'Δημιουργία χρήστη ' . $old['email'] . ' (' . role_label($old['role']) . ')');
        flash_password_saved('Ο χρήστης δημιουργήθηκε.', $password);
        redirect('/users');
    }

    public static function editForm(int $id): void
    {
        Auth::requireManager();
        $editing = self::find($id);
        render('users/form', [
            'title' => 'Επεξεργασία χρήστη',
            'errors' => [],
            'editing' => $editing,
            'old' => self::fromUser($editing),
        ]);
    }

    public static function update(int $id): void
    {
        $actor = Auth::requireManager();
        $editing = self::find($id);
        $old = self::fromPost(false, $editing);
        $password = post_raw('password');
        $errors = self::validate($old, $editing, $password);
        $errors = array_merge($errors, self::guardRole($actor, $editing, $old));
        if ($errors !== []) {
            render('users/form', [
                'title' => 'Επεξεργασία χρήστη',
                'errors' => $errors,
                'editing' => $editing,
                'old' => $old,
            ]);
        }
        if ($password !== '') {
            $stmt = Database::pdo()->prepare(
                'UPDATE users SET full_name = ?, department = ?, email = ?, password_hash = ?, password_insecure = ?, role = ?, active = ?, certify_without_approval = ?, notify_approval = ?, updated_at = ? WHERE id = ?'
            );
            $params = [$old['full_name'], $old['department'], $old['email'], password_hash($password, PASSWORD_DEFAULT), password_is_insecure($password) ? 1 : 0, $old['role'], $old['active'], $old['certify_without_approval'], $old['notify_approval'], now(), $id];
        } else {
            $stmt = Database::pdo()->prepare(
                'UPDATE users SET full_name = ?, department = ?, email = ?, role = ?, active = ?, certify_without_approval = ?, notify_approval = ?, updated_at = ? WHERE id = ?'
            );
            $params = [$old['full_name'], $old['department'], $old['email'], $old['role'], $old['active'], $old['certify_without_approval'], $old['notify_approval'], now(), $id];
        }
        try {
            $stmt->execute($params);
        } catch (PDOException) {
            $errors[] = 'Υπάρχει ήδη χρήστης με αυτό το email.';
            render('users/form', [
                'title' => 'Επεξεργασία χρήστη',
                'errors' => $errors,
                'editing' => $editing,
                'old' => $old,
            ]);
        }
        Logger::record((int) $actor['id'], 'user_update', 'Ενημέρωση χρήστη ' . $old['email']);
        Auth::flush();
        if ($password !== '') {
            flash_password_saved('Τα στοιχεία του χρήστη αποθηκεύτηκαν.', $password);
        } else {
            flash('success', 'Τα στοιχεία του χρήστη αποθηκεύτηκαν.');
        }
        redirect('/users');
    }

    public static function delete(int $id): void
    {
        $actor = Auth::requireManager();
        $editing = self::find($id);
        if ((int) $actor['id'] === $id) {
            flash('danger', 'Δεν μπορείτε να διαγράψετε τον δικό σας λογαριασμό.');
            redirect('/users');
        }
        $docs = Database::pdo()->prepare('SELECT COUNT(*) FROM documents WHERE owner_id = ? OR cancelled_by = ? OR deleted_by = ? OR confirmed_by = ?');
        $docs->execute([$id, $id, $id, $id]);
        if ((int) $docs->fetchColumn() > 0) {
            flash('danger', 'Ο χρήστης συνδέεται με έγγραφα του μητρώου και δεν διαγράφεται. Μπορείτε να τον απενεργοποιήσετε.');
            redirect('/users/' . $id . '/edit');
        }
        if (($editing['role'] ?? '') === 'manager') {
            $count = (int) Database::pdo()->query("SELECT COUNT(*) FROM users WHERE role = 'manager' AND active = 1")->fetchColumn();
            if ($count < 2) {
                flash('danger', 'Πρέπει να παραμείνει τουλάχιστον ένας ενεργός διαχειριστής.');
                redirect('/users');
            }
        }
        Database::pdo()->prepare('DELETE FROM approval_links WHERE secretary_id = ?')->execute([$id]);
        $stmt = Database::pdo()->prepare('DELETE FROM users WHERE id = ?');
        $stmt->execute([$id]);
        Logger::record((int) $actor['id'], 'user_delete', 'Διαγραφή χρήστη ' . $editing['email']);
        flash('success', 'Ο χρήστης διαγράφηκε.');
        redirect('/users');
    }

    private static function find(int $id): array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $user = $stmt->fetch();
        if (!$user) {
            not_found();
        }
        return $user;
    }

    private static function blank(): array
    {
        return [
            'full_name' => '',
            'department' => '',
            'email' => '',
            'role' => 'user',
            'active' => 1,
            'certify_without_approval' => 0,
            'notify_approval' => 1,
        ];
    }

    private static function fromUser(array $user): array
    {
        return [
            'full_name' => (string) $user['full_name'],
            'department' => (string) $user['department'],
            'email' => (string) $user['email'],
            'role' => (string) $user['role'],
            'active' => (int) $user['active'],
            'certify_without_approval' => (int) ($user['certify_without_approval'] ?? 0),
            'notify_approval' => (int) ($user['notify_approval'] ?? 1),
        ];
    }

    private static function fromPost(bool $creating, ?array $editing = null): array
    {
        $role = post_string('role', 20);
        if (!in_array($role, ['manager', 'secretary', 'user'], true)) {
            $role = 'user';
        }
        $direct = $role === 'user' && post_raw('certify_without_approval') === '1' ? 1 : 0;
        if ($role === 'secretary') {
            $notify = post_raw('notify_approval') === '1' ? 1 : 0;
        } elseif ($editing !== null) {
            $notify = (int) ($editing['notify_approval'] ?? 1) === 1 ? 1 : 0;
        } else {
            $notify = 1;
        }
        return [
            'full_name' => post_string('full_name', 200),
            'department' => post_string('department', 150),
            'email' => normalize_email(post_string('email', 190)),
            'role' => $role,
            'active' => post_raw('active') === '1' ? 1 : 0,
            'certify_without_approval' => $direct,
            'notify_approval' => $notify,
        ];
    }

    private static function validate(array $old, ?array $editing, string $password): array
    {
        $errors = [];
        if (!safe_person_name($old['full_name'])) {
            $errors[] = 'Συμπληρώστε έγκυρο ονοματεπώνυμο.';
        }
        if (!valid_email($old['email'])) {
            $errors[] = 'Το email δεν είναι έγκυρο.';
        }
        if ($editing === null || $password !== '') {
            $problem = password_problem($password);
            if ($problem !== null) {
                $errors[] = $problem;
            }
        }
        return $errors;
    }

    private static function guardRole(array $actor, array $editing, array $old): array
    {
        $errors = [];
        $isSelf = (int) $actor['id'] === (int) $editing['id'];
        $wasManager = ($editing['role'] ?? '') === 'manager' && (int) $editing['active'] === 1;
        $staysManager = $old['role'] === 'manager' && (int) $old['active'] === 1;
        if ($wasManager && !$staysManager) {
            $count = (int) Database::pdo()->query("SELECT COUNT(*) FROM users WHERE role = 'manager' AND active = 1")->fetchColumn();
            if ($count < 2) {
                $errors[] = 'Πρέπει να παραμείνει τουλάχιστον ένας ενεργός διαχειριστής.';
            }
        }
        if ($isSelf && $old['role'] !== 'manager') {
            $errors[] = 'Δεν μπορείτε να αφαιρέσετε τον ρόλο διαχειριστή από τον εαυτό σας.';
        }
        if ($isSelf && (int) $old['active'] !== 1) {
            $errors[] = 'Δεν μπορείτε να απενεργοποιήσετε τον δικό σας λογαριασμό.';
        }
        return $errors;
    }
}
