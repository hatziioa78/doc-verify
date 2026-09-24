<?php

declare(strict_types=1);

final class HistoryController
{
    public static function logs(): void
    {
        Auth::requireManager();
        $action = query_string('action', 40);
        if (!isset(Logger::LABELS[$action])) {
            $action = '';
        }
        $filters = [
            'action' => $action,
            'person' => query_string('person', 190),
            'ip' => query_string('ip', 45),
            'q' => query_string('q', 200),
            'from' => query_string('from', 10),
            'to' => query_string('to', 10),
        ];
        $result = self::entries(0, array_keys(Logger::LABELS), max(1, query_int('page')), 25, $filters);
        render('logs/index', [
            'title' => 'Καταγραφές',
            'rows' => $result['rows'],
            'pager' => $result['pager'],
            'filters' => $filters,
            'mode' => 'logs',
        ]);
    }

    public static function history(): void
    {
        $user = Auth::requireUser();
        $manager = Auth::isManager($user);
        $filters = [
            'action' => '',
            'person' => $manager ? query_string('person', 190) : '',
            'ip' => '',
            'q' => query_string('q', 200),
            'from' => query_string('from', 10),
            'to' => query_string('to', 10),
        ];
        $actions = $manager ? Logger::FILE_ACTIONS : Logger::OWN_FILE_ACTIONS;
        $userId = $manager ? 0 : (int) $user['id'];
        $result = self::entries($userId, $actions, max(1, query_int('page')), 20, $filters);
        render('logs/index', [
            'title' => $manager ? 'Ιστορικό αρχείων' : 'Το ιστορικό μου',
            'rows' => $result['rows'],
            'pager' => $result['pager'],
            'filters' => $filters,
            'mode' => 'history',
        ]);
    }

    public static function entries(int $userId, array $actions, int $page, int $perPage, array $filters = []): array
    {
        $actions = array_values(array_filter($actions, static fn ($action) => isset(Logger::LABELS[$action])));
        if ($actions === []) {
            return ['rows' => [], 'pager' => pager(0, 1, $perPage)];
        }
        $placeholders = implode(',', array_fill(0, count($actions), '?'));
        $where = ["l.action IN ({$placeholders})"];
        $params = $actions;
        if ($userId > 0) {
            $where[] = 'l.user_id = ?';
            $params[] = $userId;
        }
        if (($filters['action'] ?? '') !== '' && isset(Logger::LABELS[$filters['action']])) {
            $where[] = 'l.action = ?';
            $params[] = $filters['action'];
        }
        if (($filters['ip'] ?? '') !== '') {
            $where[] = 'l.ip = ?';
            $params[] = $filters['ip'];
        }
        if (($filters['person'] ?? '') !== '') {
            $term = like_term((string) $filters['person']);
            $where[] = "(u.full_name LIKE ? ESCAPE '\\\\' OR u.email LIKE ? ESCAPE '\\\\')";
            array_push($params, $term, $term);
        }
        if (($filters['q'] ?? '') !== '') {
            $where[] = "l.details LIKE ? ESCAPE '\\\\'";
            $params[] = like_term((string) $filters['q']);
        }
        if (valid_date((string) ($filters['from'] ?? ''))) {
            $where[] = 'l.created_at >= ?';
            $params[] = $filters['from'] . ' 00:00:00';
        }
        if (valid_date((string) ($filters['to'] ?? ''))) {
            $where[] = 'l.created_at <= ?';
            $params[] = $filters['to'] . ' 23:59:59';
        }
        $sqlWhere = 'WHERE ' . implode(' AND ', $where);
        $from = 'FROM activity_log l
            LEFT JOIN users u ON u.id = l.user_id
            LEFT JOIN documents d ON d.id = l.document_id';
        $count = Database::pdo()->prepare("SELECT COUNT(*) {$from} {$sqlWhere}");
        $count->execute($params);
        $pager = pager((int) $count->fetchColumn(), $page, $perPage);
        $sql = "SELECT l.*, u.full_name, u.email,
                d.subject, d.protocol_number, d.deleted_at
            {$from} {$sqlWhere}
            ORDER BY l.id DESC
            LIMIT {$pager['per']} OFFSET {$pager['offset']}";
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        return ['rows' => $stmt->fetchAll(), 'pager' => $pager];
    }
}
