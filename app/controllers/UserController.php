<?php

declare(strict_types=1);

final class UserController
{
    public static function index(): void
    {
        Auth::requireManager();
        $users = Database::pdo()->query(
            'SELECT u.*, (SELECT COUNT(*) FROM documents d WHERE d.owner_id = u.id AND d.deleted_at IS NULL) AS documents_count
             FROM users u ORDER BY u.role DESC, u.last_name, u.first_name'
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
        $stmt = Database::pdo()->prepare(
            'INSERT INTO users (last_name, first_name, department, email, password_hash, role, active, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        try {
            $stmt->execute([
                $old['last_name'],
                $old['first_name'],
                $old['department'],
                $old['email'],
                password_hash(post_raw('password'), PASSWORD_DEFAULT),
                $old['role'],
                $old['active'],
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
        Logger::record((int) $actor['id'], 'user_create', 'Δημιουργία χρήστη ' . $old['email'] . ' (' . ($old['role'] === 'manager' ? 'διαχειριστής' : 'χρήστης') . ')');
        flash('success', 'Ο χρήστης δημιουργήθηκε.');
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
        $old = self::fromPost(false);
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
                'UPDATE users SET last_name = ?, first_name = ?, department = ?, email = ?, password_hash = ?, role = ?, active = ?, updated_at = ? WHERE id = ?'
            );
            $params = [$old['last_name'], $old['first_name'], $old['department'], $old['email'], password_hash($password, PASSWORD_DEFAULT), $old['role'], $old['active'], now(), $id];
        } else {
            $stmt = Database::pdo()->prepare(
                'UPDATE users SET last_name = ?, first_name = ?, department = ?, email = ?, role = ?, active = ?, updated_at = ? WHERE id = ?'
            );
            $params = [$old['last_name'], $old['first_name'], $old['department'], $old['email'], $old['role'], $old['active'], now(), $id];
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
        flash('success', 'Τα στοιχεία του χρήστη αποθηκεύτηκαν.');
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
        $docs = Database::pdo()->prepare('SELECT COUNT(*) FROM documents WHERE owner_id = ? OR cancelled_by = ? OR deleted_by = ?');
        $docs->execute([$id, $id, $id]);
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
            'last_name' => '',
            'first_name' => '',
            'department' => '',
            'email' => '',
            'role' => 'user',
            'active' => 1,
        ];
    }

    private static function fromUser(array $user): array
    {
        return [
            'last_name' => (string) $user['last_name'],
            'first_name' => (string) $user['first_name'],
            'department' => (string) $user['department'],
            'email' => (string) $user['email'],
            'role' => (string) $user['role'],
            'active' => (int) $user['active'],
        ];
    }

    private static function fromPost(bool $creating): array
    {
        $role = post_string('role', 20);
        if (!in_array($role, ['manager', 'user'], true)) {
            $role = 'user';
        }
        return [
            'last_name' => post_string('last_name', 100),
            'first_name' => post_string('first_name', 100),
            'department' => post_string('department', 150),
            'email' => normalize_email(post_string('email', 190)),
            'role' => $role,
            'active' => post_raw('active') === '1' ? 1 : 0,
        ];
    }

    private static function validate(array $old, ?array $editing, string $password): array
    {
        $errors = [];
        if (!safe_person_name($old['last_name'])) {
            $errors[] = 'Συμπληρώστε έγκυρο επώνυμο.';
        }
        if (!safe_person_name($old['first_name'])) {
            $errors[] = 'Συμπληρώστε έγκυρο όνομα.';
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
