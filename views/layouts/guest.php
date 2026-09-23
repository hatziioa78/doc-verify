<?php $org = Settings::headerName(); ?>
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
<body class="guest-body">
<main id="content">
    <?php if ($pageFlash): ?>
        <div class="guest-flash">
            <div class="alert alert-<?= e((string) $pageFlash['type']) ?> mb-0" role="status"><?= e((string) $pageFlash['message']) ?></div>
        </div>
    <?php endif; ?>
    <?= $pageContent ?>
</main>
<script src="<?= e(asset('vendor/bootstrap/bootstrap.bundle.min.js')) ?>"></script>
<script src="<?= e(asset('js/app.js')) ?>"></script>
</body>
</html>
