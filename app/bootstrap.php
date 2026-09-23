<?php

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));

ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.gc_maxlifetime', '7200');

date_default_timezone_set('Europe/Athens');

require BASE_PATH . '/vendor/autoload.php';
require BASE_PATH . '/app/helpers.php';
require BASE_PATH . '/app/Config.php';
require BASE_PATH . '/app/Database.php';
require BASE_PATH . '/app/Migrate.php';
require BASE_PATH . '/app/Csrf.php';
require BASE_PATH . '/app/Logger.php';
require BASE_PATH . '/app/Settings.php';
require BASE_PATH . '/app/NetworkGuard.php';
require BASE_PATH . '/app/Auth.php';
require BASE_PATH . '/app/Storage.php';
require BASE_PATH . '/app/PdfStamper.php';
require BASE_PATH . '/app/Documents.php';
require BASE_PATH . '/app/Mailer.php';
require BASE_PATH . '/app/Approvals.php';
require BASE_PATH . '/app/SqlAdmin.php';

Config::load();
Migrate::run();

$https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
session_name('SFRAGIS');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => base_path() === '' ? '/' : base_path(),
    'secure' => $https,
    'httponly' => true,
    'samesite' => 'Strict',
]);
session_start();

send_security_headers();

require BASE_PATH . '/app/controllers/SetupController.php';
require BASE_PATH . '/app/controllers/AuthController.php';
require BASE_PATH . '/app/controllers/DashboardController.php';
require BASE_PATH . '/app/controllers/DocumentController.php';
require BASE_PATH . '/app/controllers/VerifyController.php';
require BASE_PATH . '/app/controllers/UserController.php';
require BASE_PATH . '/app/controllers/HistoryController.php';
require BASE_PATH . '/app/controllers/SettingsController.php';
require BASE_PATH . '/app/controllers/ApprovalController.php';
require BASE_PATH . '/app/routes.php';
