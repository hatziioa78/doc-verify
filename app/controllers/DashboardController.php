<?php

declare(strict_types=1);

final class DashboardController
{
    public static function index(): void
    {
        $user = Auth::requireUser();
        $pdo = Database::pdo();
        $stats = $pdo->query(
            "SELECT
                SUM(deleted_at IS NULL) AS total,
                SUM(deleted_at IS NULL AND status = 'active' AND valid_until >= CURDATE()) AS active_count,
                SUM(deleted_at IS NULL AND status = 'active' AND valid_until < CURDATE()) AS expired_count,
                SUM(deleted_at IS NULL AND status = 'cancelled') AS cancelled_count,
                SUM(deleted_at IS NULL AND status = 'active' AND valid_until >= CURDATE() AND valid_until <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)) AS soon_count
             FROM documents"
        )->fetch() ?: [];
        $userCount = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
        $recent = Documents::search([], 1, 5);
        $historyUser = Auth::isManager($user) ? 0 : (int) $user['id'];
        $activity = HistoryController::entries($historyUser, Auth::isManager($user) ? Logger::FILE_ACTIONS : Logger::OWN_FILE_ACTIONS, 1, 6);

        render('dashboard', [
            'title' => 'Επισκόπηση',
            'user' => $user,
            'stats' => $stats,
            'userCount' => $userCount,
            'recent' => $recent['rows'],
            'activity' => $activity['rows'],
            'restricted' => NetworkGuard::isRestricted(),
        ]);
    }
}
