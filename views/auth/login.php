<p class="login-system">Σύστημα Έκδοσης &amp; Επικύρωσης Εγγράφων</p>
<p class="login-version">v<?= e(app_version()) ?></p>
<div class="login-page">
<div class="login-shell">
    <section class="login-panel">
        <div class="seal-lockup">
            <div class="seal-wordmark">
                <span class="brand-seal brand-seal-lg">ΣΦ</span>
                <h1>ΣΦΡΑΓΙΣ</h1>
            </div>
            <p class="login-org"><?= e(Settings::headerName()) ?></p>
            <p class="lede">Βάζετε σφραγίδα σε ένα PDF. Όποιος σαρώνει τον κωδικό βλέπει αν το έγγραφο είναι γνήσιο.</p>
        </div>
        <ul class="trust-list">
            <li><?= icon('shield') ?> <span>Η σφραγίδα μπαίνει πάνω, κάτω ή σε χωριστή σελίδα.</span></li>
            <li><?= icon('ban') ?> <span>Φαίνεται αν είναι γνήσιο ή αν ακυρώθηκε.</span></li>
            <li><?= icon('files') ?> <span>Κάθε έγγραφο έχει τον δικό του κωδικό.</span></li>
            <li><?= icon('log') ?> <span>Μένει ποιος το κατέθεσε και ποιος το είδε.</span></li>
        </ul>
    </section>
    <section class="login-card">
        <p class="kicker">Είσοδος στο μητρώο</p>
        <h2>Σύνδεση</h2>
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger" role="alert"><?= e($error) ?></div>
        <?php endif; ?>
        <form method="post" action="<?= e(url('/login')) ?>" class="stack-form">
            <?= csrf_field() ?>
            <?php if (!empty($secure)): ?>
                <label class="form-label" for="user_id">Ονοματεπώνυμο</label>
                <select class="form-select" id="user_id" name="user_id" required>
                    <option value="">Επιλέξτε</option>
                    <?php foreach ($names as $person): ?>
                        <option value="<?= (int) $person['id'] ?>" <?= (int) $userId === (int) $person['id'] ? 'selected' : '' ?>><?= e((string) $person['label']) ?></option>
                    <?php endforeach; ?>
                </select>
            <?php else: ?>
                <label class="form-label" for="email">Email</label>
                <input class="form-control" id="email" name="email" type="email" autocomplete="username" required maxlength="190" value="<?= e($email) ?>">
            <?php endif; ?>
            <label class="form-label" for="password">Κωδικός</label>
            <div class="password-row">
                <input class="form-control" id="password" name="password" type="password" autocomplete="current-password" required>
                <button class="btn btn-link password-toggle" type="button" data-password-toggle="password">Εμφάνιση</button>
            </div>
            <button class="btn btn-seal btn-lg w-100" type="submit">Είσοδος</button>
        </form>
    </section>
</div>
<p class="login-credit">© <?= e(date('Y')) ?> Χατζηιωαννίδης Χρήστος - Δ.Δ.Ε. Φλώρινας</p>
</div>
