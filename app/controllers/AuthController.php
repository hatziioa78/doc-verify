<?php

declare(strict_types=1);

final class AuthController
{
    public static function loginForm(): void
    {
        if (Auth::check()) {
            redirect('/');
        }
        render('auth/login', [
            'title' => 'Σύνδεση',
            'email' => '',
            'error' => '',
        ], 'layouts/guest');
    }

    public static function login(): void
    {
        if (Auth::check()) {
            redirect('/');
        }
        $email = normalize_email(post_string('email', 190));
        $result = Auth::attempt($email, post_raw('password'));
        if (!$result['ok']) {
            render('auth/login', [
                'title' => 'Σύνδεση',
                'email' => $email,
                'error' => $result['error'],
            ], 'layouts/guest');
        }
        redirect('/');
    }

    public static function logout(): void
    {
        Auth::logout();
        flash('success', 'Αποσυνδεθήκατε.');
        redirect('/login');
    }

    public static function account(): void
    {
        $user = Auth::requireUser();
        render('account/index', [
            'title' => 'Ο λογαριασμός μου',
            'user' => $user,
            'errors' => [],
        ]);
    }

    public static function updatePassword(): void
    {
        $user = Auth::requireUser();
        $errors = [];
        $current = post_raw('current_password');
        $next = post_raw('new_password');
        if (!Auth::verifyPassword($user, $current)) {
            $errors[] = 'Ο τρέχων κωδικός δεν είναι σωστός.';
        }
        $problem = password_problem($next);
        if ($problem !== null) {
            $errors[] = $problem;
        }
        if ($next !== post_raw('new_password_confirm')) {
            $errors[] = 'Η επιβεβαίωση του νέου κωδικού δεν ταιριάζει.';
        }
        if ($errors !== []) {
            render('account/index', [
                'title' => 'Ο λογαριασμός μου',
                'user' => $user,
                'errors' => $errors,
            ]);
        }
        $stmt = Database::pdo()->prepare('UPDATE users SET password_hash = ?, updated_at = ? WHERE id = ?');
        $stmt->execute([password_hash($next, PASSWORD_DEFAULT), now(), (int) $user['id']]);
        Logger::record((int) $user['id'], 'password', 'Αλλαγή κωδικού του ' . $user['email']);
        flash('success', 'Ο κωδικός άλλαξε.');
        redirect('/account');
    }
}
