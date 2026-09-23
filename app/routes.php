<?php

declare(strict_types=1);

function dispatch(string $method, string $path): void
{
    if ($path === '/index.php') {
        redirect('/');
    }

    if ($method === 'POST') {
        Csrf::verify();
    }

    if (!Config::installed()) {
        if (!Config::setupAllowed()) {
            http_response_code(503);
            require BASE_PATH . '/views/errors/locked.php';
            exit;
        }
        redirect('/setup.php');
    }

    if ($method === 'GET' && $path === '/setup') {
        redirect('/setup.php');
    }
    if ($method === 'GET' && $path === '/login') {
        AuthController::loginForm();
    }
    if ($method === 'POST' && $path === '/login') {
        AuthController::login();
    }
    if ($method === 'POST' && $path === '/logout') {
        AuthController::logout();
    }
    if ($method === 'GET' && preg_match('#^/v/([^/]+)$#', $path, $matches)) {
        VerifyController::show($matches[1]);
    }
    if ($method === 'GET' && preg_match('#^/v/([^/]+)/download$#', $path, $matches)) {
        VerifyController::download($matches[1]);
    }
    if ($method === 'GET' && preg_match('#^/a/([a-f0-9]{64})$#', $path, $matches)) {
        ApprovalController::magic($matches[1]);
    }
    if ($method === 'GET' && $path === '/') {
        DashboardController::index();
    }
    if ($method === 'GET' && $path === '/documents') {
        DocumentController::index();
    }
    if ($method === 'GET' && $path === '/documents/new') {
        DocumentController::createForm();
    }
    if ($method === 'POST' && $path === '/documents') {
        DocumentController::store();
    }
    if ($method === 'GET' && $path === '/approvals') {
        ApprovalController::index();
    }
    if ($method === 'GET' && preg_match('#^/documents/(\d+)/edit$#', $path, $matches)) {
        DocumentController::editForm((int) $matches[1]);
    }
    if ($method === 'POST' && preg_match('#^/documents/(\d+)$#', $path, $matches)) {
        DocumentController::update((int) $matches[1]);
    }
    if ($method === 'POST' && preg_match('#^/documents/(\d+)/approve$#', $path, $matches)) {
        ApprovalController::approve((int) $matches[1]);
    }
    if ($method === 'GET' && preg_match('#^/documents/(\d+)$#', $path, $matches)) {
        DocumentController::show((int) $matches[1]);
    }
    if ($method === 'GET' && preg_match('#^/documents/(\d+)/original$#', $path, $matches)) {
        DocumentController::original((int) $matches[1]);
    }
    if ($method === 'GET' && preg_match('#^/documents/(\d+)/download$#', $path, $matches)) {
        DocumentController::download((int) $matches[1]);
    }
    if ($method === 'GET' && preg_match('#^/documents/(\d+)/qr\.png$#', $path, $matches)) {
        DocumentController::qr((int) $matches[1]);
    }
    if ($method === 'POST' && preg_match('#^/documents/(\d+)/cancel$#', $path, $matches)) {
        DocumentController::cancel((int) $matches[1]);
    }
    if ($method === 'POST' && preg_match('#^/documents/(\d+)/delete$#', $path, $matches)) {
        DocumentController::delete((int) $matches[1]);
    }
    if ($method === 'GET' && $path === '/users') {
        UserController::index();
    }
    if ($method === 'GET' && $path === '/users/new') {
        UserController::createForm();
    }
    if ($method === 'POST' && $path === '/users') {
        UserController::store();
    }
    if ($method === 'GET' && preg_match('#^/users/(\d+)/edit$#', $path, $matches)) {
        UserController::editForm((int) $matches[1]);
    }
    if ($method === 'POST' && preg_match('#^/users/(\d+)$#', $path, $matches)) {
        UserController::update((int) $matches[1]);
    }
    if ($method === 'POST' && preg_match('#^/users/(\d+)/delete$#', $path, $matches)) {
        UserController::delete((int) $matches[1]);
    }
    if ($method === 'GET' && $path === '/logs') {
        HistoryController::logs();
    }
    if ($method === 'GET' && $path === '/history') {
        HistoryController::history();
    }
    if ($method === 'GET' && $path === '/account') {
        AuthController::account();
    }
    if ($method === 'POST' && $path === '/account') {
        AuthController::updatePassword();
    }
    if ($method === 'GET' && $path === '/settings') {
        SettingsController::index();
    }
    if ($method === 'POST' && $path === '/settings/profile') {
        SettingsController::saveProfile();
    }
    if ($method === 'POST' && $path === '/settings/mail') {
        SettingsController::saveMail();
    }
    if ($method === 'POST' && $path === '/settings/mail/test') {
        SettingsController::testMail();
    }
    if ($method === 'POST' && $path === '/settings/networks') {
        SettingsController::saveNetworks();
    }
    if ($method === 'POST' && $path === '/settings/sql/test') {
        SettingsController::testSql();
    }
    if ($method === 'POST' && $path === '/settings/sql/save') {
        SettingsController::saveSql();
    }
    if ($method === 'POST' && $path === '/settings/sql/init') {
        SettingsController::initSql();
    }
    if ($method === 'POST' && $path === '/settings/sql/backup') {
        SettingsController::backup();
    }
    if ($method === 'POST' && $path === '/settings/sql/restore') {
        SettingsController::restore();
    }
    if ($method === 'GET' && preg_match('#^/settings/backups/(sfragis-\d{8}-\d{6}\.sql)$#', $path, $matches)) {
        SettingsController::downloadBackup($matches[1]);
    }

    not_found();
}
