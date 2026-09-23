<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Εγκατάσταση κλειδωμένη · ΣΦΡΑΓΙΣ</title>
    <link rel="stylesheet" href="<?= e(asset('vendor/bootstrap/bootstrap.min.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body class="guest-body">
<main class="error-panel">
    <p class="kicker">ΣΦΡΑΓΙΣ</p>
    <h1>Η εγκατάσταση έχει ολοκληρωθεί</h1>
    <p class="lede">Το setup.php τρέχει μόνο την πρώτη φορά. Οι παράμετροι SQL και email αλλάζουν πλέον από τον λογαριασμό διαχειριστή.</p>
    <p><a class="btn btn-seal" href="<?= e(url('/login')) ?>">Σύνδεση</a></p>
</main>
</body>
</html>
