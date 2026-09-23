<?php

declare(strict_types=1);

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function now(): string
{
    return (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');
}

function today(): string
{
    return (new DateTimeImmutable('today'))->format('Y-m-d');
}

function base_path(): string
{
    $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '/index.php'));
    if (!in_array(basename($script), ['index.php', 'router.php'], true)) {
        return '';
    }
    $dir = rtrim(str_replace('\\', '/', dirname($script)), '/');
    if ($dir === '' || $dir === '.' || $dir === '/') {
        return '';
    }
    return $dir;
}

function url(string $path = '/'): string
{
    if ($path === '') {
        $path = '/';
    }
    if (!str_starts_with($path, '/')) {
        $path = '/' . $path;
    }
    return base_path() . $path;
}

function asset(string $path): string
{
    $file = BASE_PATH . '/public/assets/' . ltrim($path, '/');
    $version = is_file($file) ? (string) filemtime($file) : '1';
    return url('/assets/' . ltrim($path, '/')) . '?v=' . $version;
}

function request_method(): string
{
    $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    return $method === 'HEAD' ? 'GET' : $method;
}

function request_path(): string
{
    $uri = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
    $uri = rawurldecode(is_string($uri) && $uri !== '' ? $uri : '/');
    $base = base_path();
    if ($base !== '' && str_starts_with($uri, $base)) {
        $uri = substr($uri, strlen($base));
        if ($uri === '' || $uri === false) {
            $uri = '/';
        }
    }
    $path = '/' . trim($uri, '/');
    return $path === '//' ? '/' : $path;
}

function client_ip(): string
{
    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
    if ($ip === '::1' || str_starts_with($ip, '::ffff:')) {
        $mapped = str_starts_with($ip, '::ffff:') ? substr($ip, 7) : '127.0.0.1';
        if (filter_var($mapped, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return $mapped;
        }
    }
    return $ip;
}

function redirect(string $path): never
{
    header('Location: ' . url($path), true, 302);
    exit;
}

function flash(string $type, string $message): void
{
    $_SESSION['_flash'] = ['type' => $type, 'message' => $message];
}

function pull_flash(): ?array
{
    if (empty($_SESSION['_flash']) || !is_array($_SESSION['_flash'])) {
        return null;
    }
    $flash = $_SESSION['_flash'];
    unset($_SESSION['_flash']);
    return $flash;
}

function render(string $view, array $data = [], string $layout = 'layouts/app'): never
{
    $pageTitle = (string) ($data['title'] ?? 'ΣΦΡΑΓΙΣ');
    $pageFlash = pull_flash();
    $currentUser = Config::installed() ? Auth::user() : null;
    $viewFile = BASE_PATH . '/views/' . $view . '.php';
    if (!is_file($viewFile)) {
        throw new RuntimeException('Λείπει η προβολή ' . $view);
    }
    extract($data, EXTR_SKIP);
    ob_start();
    require $viewFile;
    $pageContent = ob_get_clean();
    require BASE_PATH . '/views/' . $layout . '.php';
    exit;
}

function not_found(): never
{
    http_response_code(404);
    render('errors/404', ['title' => 'Δεν βρέθηκε'], 'layouts/guest');
}

function forbidden(): never
{
    http_response_code(403);
    render('errors/403', ['title' => 'Απαγορεύεται'], 'layouts/guest');
}

function post_raw(string $key): string
{
    return (string) ($_POST[$key] ?? '');
}

function post_string(string $key, int $max = 1000): string
{
    $value = str_replace("\0", '', post_raw($key));
    $value = trim($value);
    if (mb_strlen($value) > $max) {
        $value = mb_substr($value, 0, $max);
    }
    return $value;
}

function post_int(string $key): int
{
    return (int) ($_POST[$key] ?? 0);
}

function query_string(string $key, int $max = 200): string
{
    $value = str_replace("\0", '', (string) ($_GET[$key] ?? ''));
    $value = trim($value);
    if (mb_strlen($value) > $max) {
        $value = mb_substr($value, 0, $max);
    }
    return $value;
}

function query_int(string $key): int
{
    return (int) ($_GET[$key] ?? 0);
}

function valid_date(string $value): bool
{
    $dt = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    return $dt instanceof DateTimeImmutable && $dt->format('Y-m-d') === $value;
}

function fmt_date(?string $value): string
{
    if ($value === null || $value === '') {
        return '—';
    }
    $dt = DateTimeImmutable::createFromFormat('!Y-m-d', substr($value, 0, 10));
    return $dt instanceof DateTimeImmutable ? $dt->format('d/m/Y') : '—';
}

function fmt_dt(?string $value): string
{
    if ($value === null || $value === '') {
        return '—';
    }
    $dt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $value);
    return $dt instanceof DateTimeImmutable ? $dt->format('d/m/Y H:i') : '—';
}

function full_name(array $user): string
{
    return trim((string) ($user['last_name'] ?? '') . ' ' . (string) ($user['first_name'] ?? ''));
}

function default_info(array $user): string
{
    return "Ονοματεπώνυμο: " . full_name($user) . "\nEmail: " . (string) $user['email'];
}

function like_term(string $value): string
{
    $value = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    return '%' . $value . '%';
}

function document_state(array $doc): string
{
    if (($doc['status'] ?? '') === 'cancelled') {
        return 'cancelled';
    }
    $until = substr((string) ($doc['valid_until'] ?? ''), 0, 10);
    if ($until !== '' && $until < today()) {
        return 'expired';
    }
    return 'active';
}

function state_label(string $state): string
{
    return match ($state) {
        'cancelled' => 'Ακυρωμένο',
        'expired' => 'Έληξε η ισχύς',
        'active' => 'Ενεργό',
        default => 'Άγνωστο',
    };
}

function state_class(string $state): string
{
    return match ($state) {
        'cancelled' => 'is-cancelled',
        'expired' => 'is-expired',
        'active' => 'is-active-doc',
        default => '',
    };
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(Csrf::token()) . '">';
}

function icon(string $name): string
{
    $paths = [
        'grid' => '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>',
        'files' => '<path d="M8 4h8l4 4v12a1 1 0 0 1-1 1H8a1 1 0 0 1-1-1V5a1 1 0 0 1 1-1z"/><path d="M16 4v4h4"/><path d="M5 8H4a1 1 0 0 0-1 1v11a1 1 0 0 0 1 1h11"/>',
        'plus' => '<path d="M12 5v14M5 12h14"/>',
        'users' => '<path d="M16 20v-1.2A3.8 3.8 0 0 0 12.2 15H7.8A3.8 3.8 0 0 0 4 18.8V20"/><circle cx="10" cy="8" r="3"/><path d="M20 20v-1.1A3.4 3.4 0 0 0 17.2 15.6"/><path d="M16 5.2a3 3 0 0 1 0 5.6"/>',
        'history' => '<path d="M4 12a8 8 0 1 0 2.3-5.6"/><path d="M4 4v4h4"/><path d="M12 8v5l3 2"/>',
        'log' => '<path d="M6 4h12v16H6z"/><path d="M9 8h6M9 12h6M9 16h4"/>',
        'settings' => '<circle cx="12" cy="12" r="3"/><path d="M12 3.5v2.2M12 18.3v2.2M4.8 6.8l1.6 1.6M17.6 15.6l1.6 1.6M3.5 12h2.2M18.3 12h2.2M4.8 17.2l1.6-1.6M17.6 8.4l1.6-1.6"/>',
        'logout' => '<path d="M10 7V5a1 1 0 0 1 1-1h8v16h-8a1 1 0 0 1-1-1v-2"/><path d="M4 12h11"/><path d="M12 8l4 4-4 4"/>',
        'search' => '<circle cx="11" cy="11" r="6"/><path d="M20 20l-3.5-3.5"/>',
        'download' => '<path d="M12 4v10"/><path d="M8 10l4 4 4-4"/><path d="M5 19h14"/>',
        'shield' => '<path d="M12 3l7 3v6c0 4.2-2.8 7.2-7 8.5C7.8 19.2 5 16.2 5 12V6l7-3z"/><path d="M9 12l2 2 4-4"/>',
        'ban' => '<circle cx="12" cy="12" r="8"/><path d="M7 7l10 10"/>',
        'user' => '<circle cx="12" cy="8" r="3"/><path d="M6 19v-1a4 4 0 0 1 4-4h4a4 4 0 0 1 4 4v1"/>',
        'link' => '<path d="M10 13a5 5 0 0 0 7.1.1l1.4-1.5a5 5 0 0 0-7.1-7.1L10 6"/><path d="M14 11a5 5 0 0 0-7.1-.1L5.5 12.4a5 5 0 0 0 7.1 7.1L14 18"/>',
        'database' => '<ellipse cx="12" cy="6" rx="7" ry="3"/><path d="M5 6v6c0 1.7 3.1 3 7 3s7-1.3 7-3V6"/><path d="M5 12v6c0 1.7 3.1 3 7 3s7-1.3 7-3v-6"/>',
    ];
    $body = $paths[$name] ?? $paths['shield'];
    return '<svg class="icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">' . $body . '</svg>';
}

function page_head(string $kicker, string $heading, string $lede = ''): void
{
    echo '<header class="page-head">';
    echo '<p class="kicker">' . e($kicker) . '</p>';
    echo '<h1>' . e($heading) . '</h1>';
    if ($lede !== '') {
        echo '<p class="lede">' . e($lede) . '</p>';
    }
    echo '</header>';
}

function errors_box(array $errors): void
{
    if ($errors === []) {
        return;
    }
    echo '<div class="alert alert-danger" role="alert"><ul class="mb-0">';
    foreach ($errors as $error) {
        echo '<li>' . e((string) $error) . '</li>';
    }
    echo '</ul></div>';
}

function qs(array $overrides = []): string
{
    $query = array_merge($_GET, $overrides);
    unset($query['_csrf']);
    foreach ($query as $key => $value) {
        if ($value === '' || $value === null) {
            unset($query[$key]);
        }
    }
    $built = http_build_query($query);
    return $built === '' ? '' : '?' . $built;
}

function nav_on(string $section): string
{
    $path = request_path();
    $on = match ($section) {
        'dash' => $path === '/',
        'docs' => $path === '/documents' || (bool) preg_match('#^/documents/\d+$#', $path),
        'new' => $path === '/documents/new',
        'users' => str_starts_with($path, '/users'),
        'history' => $path === '/history',
        'logs' => $path === '/logs',
        'settings' => str_starts_with($path, '/settings'),
        'account' => $path === '/account',
        default => false,
    };
    return $on ? ' is-active' : '';
}

function safe_person_name(string $value): bool
{
    return (bool) preg_match('/^[\p{L}\s.\'’\-]{2,100}$/u', $value);
}

function normalize_email(string $value): string
{
    return mb_strtolower(trim($value));
}

function valid_email(string $value): bool
{
    return (bool) filter_var($value, FILTER_VALIDATE_EMAIL) && mb_strlen($value) <= 190;
}

function password_problem(string $password): ?string
{
    if (strlen($password) < 8 || strlen($password) > 128) {
        return 'Ο κωδικός πρέπει να έχει 8 έως 128 χαρακτήρες.';
    }
    if (!preg_match('/\p{L}/u', $password) || !preg_match('/\d/', $password)) {
        return 'Ο κωδικός χρειάζεται τουλάχιστον ένα γράμμα και έναν αριθμό.';
    }
    return null;
}

function valid_host(string $host): bool
{
    if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        return true;
    }
    return (bool) preg_match('/^(?=.{1,253}$)(?:[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?)(?:\.(?:[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?))*$/', $host);
}

function normalize_cidr(string $raw): ?string
{
    $raw = trim($raw);
    if ($raw === '') {
        return null;
    }
    if (str_contains($raw, '/')) {
        [$ip, $bits] = explode('/', $raw, 2);
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) || !preg_match('/^\d{1,2}$/', $bits)) {
            return null;
        }
        $bits = (int) $bits;
        if ($bits < 0 || $bits > 32) {
            return null;
        }
        return $ip . '/' . $bits;
    }
    if (filter_var($raw, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        return $raw . '/32';
    }
    if (filter_var($raw, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
        return strtolower($raw);
    }
    return null;
}

function log_exception(Throwable $e): void
{
    $dir = BASE_PATH . '/storage/logs';
    if (!is_dir($dir)) {
        @mkdir($dir, 0750, true);
    }
    $line = date('c') . ' ' . $e::class . ' ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine() . PHP_EOL;
    @file_put_contents($dir . '/app.log', $line, FILE_APPEND | LOCK_EX);
}

function public_error_message(Throwable $e): string
{
    if ($e instanceof PDOException) {
        return 'Η εφαρμογή δεν μπορεί να επικοινωνήσει με τη βάση δεδομένων. Ο διαχειριστής μπορεί να ελέγξει τα στοιχεία MySQL στο αρχείο ρυθμίσεων.';
    }
    return 'Παρουσιάστηκε απρόσμενο σφάλμα. Η ενέργεια δεν ολοκληρώθηκε.';
}

function send_security_headers(): void
{
    header('Content-Language: el');
    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: no-referrer');
    header('Permissions-Policy: geolocation=(), camera=(), microphone=()');
    header('X-Permitted-Cross-Domain-Policies: none');
    header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self'; font-src 'self'; script-src 'self'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'; object-src 'none'");
    header('Cache-Control: no-store, max-age=0');
    $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    if ($https) {
        header('Strict-Transport-Security: max-age=15552000');
    }
}

function current_origin(): string
{
    $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    $scheme = $https ? 'https' : 'http';
    $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
    return $scheme . '://' . $host . base_path();
}

function send_download_sql(string $path): never
{
    $name = basename($path);
    header('Content-Type: application/sql; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    header('Content-Length: ' . (string) filesize($path));
    header('Content-Disposition: attachment; filename="' . $name . '"');
    readfile($path);
    exit;
}

function send_download(string $path, string $downloadName): never
{
    $ascii = preg_replace('/[^A-Za-z0-9._-]+/', '-', $downloadName) ?: 'document.pdf';
    if (!str_ends_with(strtolower($ascii), '.pdf')) {
        $ascii .= '.pdf';
    }
    header('Content-Type: application/pdf');
    header('X-Content-Type-Options: nosniff');
    header('Content-Length: ' . (string) filesize($path));
    header("Content-Disposition: attachment; filename=\"{$ascii}\"; filename*=UTF-8''" . rawurlencode($downloadName));
    readfile($path);
    exit;
}

function format_bytes(int $bytes): string
{
    if ($bytes < 1024) {
        return $bytes . ' B';
    }
    if ($bytes < 1048576) {
        return number_format($bytes / 1024, 1, ',', '.') . ' KB';
    }
    return number_format($bytes / 1048576, 1, ',', '.') . ' MB';
}

function pager(int $total, int $page, int $perPage): array
{
    $pages = max(1, (int) ceil($total / $perPage));
    $page = min(max(1, $page), $pages);
    return [
        'total' => $total,
        'page' => $page,
        'pages' => $pages,
        'per' => $perPage,
        'offset' => ($page - 1) * $perPage,
    ];
}
