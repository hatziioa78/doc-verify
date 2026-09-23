<?php
$org = Settings::headerName();
$contact = Settings::get('footer_contact');
$credits = Settings::get('footer_credits');
?>
<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#e7dcc4">
    <title><?= e($pageTitle) ?> · <?= e($org) ?></title>
    <link rel="icon" href="<?= e(asset('favicon.svg')) ?>" type="image/svg+xml">
    <link rel="stylesheet" href="<?= e(asset('vendor/bootstrap/bootstrap.min.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body class="verify-body">
<main class="verify-wrap" id="content">
    <header class="verify-brand">
        <span class="brand-seal">ΣΦ</span>
        <span>
            <p class="kicker">Μητρώο γνησιότητας</p>
            <strong><?= e($org) ?></strong>
        </span>
    </header>
    <?= $pageContent ?>
    <footer class="verify-foot">
        <?php if ($contact !== ''): ?><p><?= nl2br(e($contact)) ?></p><?php endif; ?>
        <?php if ($credits !== ''): ?><p><?= nl2br(e($credits)) ?></p><?php endif; ?>
        <p>Η επιβεβαίωση ισχύει μόνο σε αυτή τη σελίδα του επίσημου μητρώου.</p>
    </footer>
</main>
</body>
</html>
