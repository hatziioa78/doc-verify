<?php
$contact = Settings::get('footer_contact');
$credits = Settings::get('footer_credits');
$org = Settings::headerName();
?>
<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#102033">
    <title><?= e($pageTitle) ?> · <?= e($org) ?></title>
    <link rel="icon" href="<?= e(asset('favicon.svg')) ?>" type="image/svg+xml">
    <link rel="stylesheet" href="<?= e(asset('vendor/bootstrap/bootstrap.min.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body class="app-body">
<a class="skip-link" href="#content">Μετάβαση στο περιεχόμενο</a>
<div class="app-shell">
    <aside class="sidebar" data-sidebar>
        <a class="brand" href="<?= e(url('/')) ?>">
            <span class="brand-seal">ΣΦ</span>
            <span>
                <span class="brand-mark">ΣΦΡΑΓΙΣ</span>
                <span class="brand-sub">Μητρώο γνησιότητας</span>
            </span>
        </a>
        <nav class="side-nav" aria-label="Κύρια πλοήγηση">
            <a class="side-link<?= nav_on('dash') ?>" href="<?= e(url('/')) ?>"><?= icon('grid') ?> Επισκόπηση</a>
            <a class="side-link<?= nav_on('docs') ?>" href="<?= e(url('/documents')) ?>"><?= icon('files') ?> Έγγραφα</a>
            <a class="side-link<?= nav_on('new') ?>" href="<?= e(url('/documents/new')) ?>"><?= icon('plus') ?> Νέα επικύρωση</a>
            <?php if ($currentUser && ($currentUser['role'] ?? '') === 'manager'): ?>
                <a class="side-link<?= nav_on('users') ?>" href="<?= e(url('/users')) ?>"><?= icon('users') ?> Χρήστες</a>
            <?php endif; ?>
            <a class="side-link<?= nav_on('history') ?>" href="<?= e(url('/history')) ?>"><?= icon('history') ?> <?= $currentUser && ($currentUser['role'] ?? '') === 'manager' ? 'Ιστορικό αρχείων' : 'Το ιστορικό μου' ?></a>
            <?php if ($currentUser && ($currentUser['role'] ?? '') === 'manager'): ?>
                <a class="side-link<?= nav_on('logs') ?>" href="<?= e(url('/logs')) ?>"><?= icon('log') ?> Καταγραφές</a>
                <a class="side-link<?= nav_on('settings') ?>" href="<?= e(url('/settings')) ?>"><?= icon('settings') ?> Παράμετροι</a>
            <?php endif; ?>
        </nav>
        <div class="side-foot">
            <a class="side-link<?= nav_on('account') ?>" href="<?= e(url('/account')) ?>"><?= icon('user') ?> Λογαριασμός</a>
            <form method="post" action="<?= e(url('/logout')) ?>">
                <?= csrf_field() ?>
                <button class="side-link side-button" type="submit"><?= icon('logout') ?> Αποσύνδεση</button>
            </form>
        </div>
    </aside>
    <div class="nav-backdrop" data-backdrop></div>
    <div class="app-main">
        <header class="topbar">
            <button class="icon-button d-lg-none" type="button" data-sidebar-toggle aria-label="Μενού">
                <span></span><span></span><span></span>
            </button>
            <div class="topbar-org">
                <p class="kicker">Επίσημη κεφαλίδα</p>
                <strong><?= e($org) ?></strong>
            </div>
            <?php if ($currentUser): ?>
                <div class="user-chip">
                    <span class="user-avatar"><?= e(mb_substr((string) $currentUser['last_name'], 0, 1)) ?></span>
                    <span>
                        <strong><?= e(full_name($currentUser)) ?></strong>
                        <small><?= ($currentUser['role'] ?? '') === 'manager' ? 'Διαχειριστής' : 'Χρήστης' ?></small>
                    </span>
                </div>
            <?php endif; ?>
        </header>
        <main id="content" class="app-content">
            <?php if ($pageFlash): ?>
                <div class="alert alert-<?= e((string) $pageFlash['type']) ?> alert-dismissible fade show" role="status">
                    <?= e((string) $pageFlash['message']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Κλείσιμο"></button>
                </div>
            <?php endif; ?>
            <?= $pageContent ?>
        </main>
        <?php if ($contact !== '' || $credits !== ''): ?>
            <footer class="app-footer">
                <?php if ($contact !== ''): ?><p><?= nl2br(e($contact)) ?></p><?php endif; ?>
                <?php if ($credits !== ''): ?><p><?= nl2br(e($credits)) ?></p><?php endif; ?>
            </footer>
        <?php endif; ?>
    </div>
</div>
<script src="<?= e(asset('vendor/bootstrap/bootstrap.bundle.min.js')) ?>"></script>
<script src="<?= e(asset('js/app.js')) ?>"></script>
</body>
</html>
