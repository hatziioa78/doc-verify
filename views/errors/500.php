<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Σφάλμα · ΣΦΡΑΓΙΣ</title>
    <link rel="stylesheet" href="<?= e(asset('vendor/bootstrap/bootstrap.min.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body class="guest-body">
<main class="error-panel">
    <p class="kicker">Σφάλμα</p>
    <h1>Κάτι δεν ολοκληρώθηκε</h1>
    <p class="lede"><?= e($message ?? 'Παρουσιάστηκε απρόσμενο σφάλμα.') ?></p>
</main>
</body>
</html>
