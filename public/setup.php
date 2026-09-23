<?php

declare(strict_types=1);

$root = dirname(__DIR__);
if (!is_file($root . '/vendor/autoload.php')) {
    http_response_code(500);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html lang="el"><head><meta charset="utf-8"><title>ΣΦΡΑΓΙΣ</title></head><body><p>Λείπουν οι βιβλιοθήκες. Στον κατάλογο της εφαρμογής τρέξτε <code>composer install</code> και ξανανοίξτε το setup.php.</p></body></html>';
    exit;
}

require $root . '/app/bootstrap.php';

if (!Config::setupAllowed()) {
    http_response_code(403);
    require BASE_PATH . '/views/setup/closed.php';
    exit;
}

try {
    if (request_method() === 'POST') {
        Csrf::verify();
        $action = post_string('setup_action', 20);
        if ($action === 'test-sql') {
            SetupController::test();
        }
        if ($action === 'test-mail') {
            SetupController::testMail();
        }
        if ($action === 'install') {
            SetupController::install();
        }
    }
    SetupController::form();
} catch (Throwable $e) {
    log_exception($e);
    if (!headers_sent()) {
        http_response_code(500);
    }
    $message = public_error_message($e);
    require BASE_PATH . '/views/errors/500.php';
}
