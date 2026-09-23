<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Εγκατάσταση κλειδωμένη · ΣΦΡΑΓΙΣ</title>
    <link rel="stylesheet" href="<?= e(asset('vendor/bootstrap/bootstrap.min.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body class="guest-body">
<main class="error-panel">
    <p class="kicker">ΣΦΡΑΓΙΣ</p>
    <h1>Η εγκατάσταση είναι κλειδωμένη</h1>
    <p class="lede">Το αρχείο ρυθμίσεων λείπει ή η βάση δεν είναι διαθέσιμη, αλλά η πρώτη εγκατάσταση έχει ήδη γίνει. Επαναφέρετε το config/config.php από αντίγραφο. Νέα εγκατάσταση γίνεται μόνο αν αφαιρεθεί και το storage/install.lock από τον διακομιστή.</p>
</main>
</body>
</html>
