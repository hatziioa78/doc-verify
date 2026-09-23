<div class="setup-shell">
    <header class="page-head text-center">
        <p class="kicker">Πρώτη εκκίνηση</p>
        <h1>Εγκατάσταση του ΣΦΡΑΓΙΣ</h1>
        <p class="lede">Σύνδεση με MySQL, δημιουργία της βάσης και ο πρώτος λογαριασμός διαχειριστή. Μετά την ολοκλήρωση ο οδηγός κλειδώνει.</p>
    </header>
    <?php errors_box($errors); ?>
    <form method="post" class="setup-grid" id="setup-form">
        <?= csrf_field() ?>
        <section class="paper-card">
            <h2><?= icon('database') ?> MySQL</h2>
            <div class="row g-3">
                <div class="col-md-8">
                    <label class="form-label" for="db_host">Διεύθυνση</label>
                    <input class="form-control" id="db_host" name="db_host" required value="<?= e((string) $db['host']) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="db_port">Θύρα</label>
                    <input class="form-control" id="db_port" name="db_port" required inputmode="numeric" value="<?= e((string) $db['port']) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="db_name">Όνομα βάσης</label>
                    <input class="form-control" id="db_name" name="db_name" required value="<?= e((string) $db['name']) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="db_user">Χρήστης</label>
                    <input class="form-control" id="db_user" name="db_user" required autocomplete="off" value="<?= e((string) $db['user']) ?>">
                </div>
                <div class="col-12">
                    <label class="form-label" for="db_pass">Κωδικός</label>
                    <input class="form-control" id="db_pass" name="db_pass" type="password" autocomplete="new-password" value="<?= e((string) ($db['pass'] ?? '')) ?>">
                </div>
            </div>
        </section>
        <section class="paper-card">
            <h2><?= icon('user') ?> Διαχειριστής</h2>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label" for="last_name">Επώνυμο</label>
                    <input class="form-control" id="last_name" name="last_name" required value="<?= e($old['last_name']) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="first_name">Όνομα</label>
                    <input class="form-control" id="first_name" name="first_name" required value="<?= e($old['first_name']) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="department">Τμήμα</label>
                    <input class="form-control" id="department" name="department" value="<?= e($old['department']) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="email">Email</label>
                    <input class="form-control" id="email" name="email" type="email" required value="<?= e($old['email']) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="password">Κωδικός</label>
                    <input class="form-control" id="password" name="password" type="password" autocomplete="new-password" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="password_confirm">Επιβεβαίωση</label>
                    <input class="form-control" id="password_confirm" name="password_confirm" type="password" autocomplete="new-password" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="header_name">Όνομα στην κεφαλίδα</label>
                    <input class="form-control" id="header_name" name="header_name" required value="<?= e($old['header_name']) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="site_url">URL για το QR</label>
                    <input class="form-control" id="site_url" name="site_url" type="url" required value="<?= e($old['site_url']) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="server_url">URL διακομιστή</label>
                    <input class="form-control" id="server_url" name="server_url" type="url" value="<?= e((string) ($old['server_url'] ?? '')) ?>">
                    <p class="field-hint">Για τους συνδέσμους email. Αν μείνει κενό, χρησιμοποιείται το URL του QR. Μπορεί να διαφέρει πίσω από web proxy.</p>
                </div>
                <div class="col-12">
                    <label class="check-line">
                        <input type="checkbox" name="private_networks" value="1" <?= $old['private_networks'] === '1' ? 'checked' : '' ?>>
                        <span>Να επιτρέπονται τα ιδιωτικά δίκτυα και ο τοπικός διακομιστής για τη σύνδεση χρηστών.</span>
                    </label>
                </div>
            </div>
        </section>
        <div class="setup-actions">
            <button class="btn btn-ink" type="submit" formaction="<?= e(url('/setup/test')) ?>">Έλεγχος σύνδεσης SQL</button>
            <button class="btn btn-seal" type="submit" formaction="<?= e(url('/setup/install')) ?>">Δημιουργία βάσης και ολοκλήρωση</button>
        </div>
    </form>
</div>
