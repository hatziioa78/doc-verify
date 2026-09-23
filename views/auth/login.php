<div class="login-shell">
    <section class="login-panel">
        <div class="seal-lockup">
            <span class="brand-seal brand-seal-lg">ΣΦ</span>
            <p class="kicker">Ψηφιακή σφραγίδα</p>
            <h1><?= e(Settings::headerName()) ?></h1>
            <p class="lede">Κάθε έγγραφο κλείνει με έναν σύνδεσμο που δεν μαντεύεται. Όποιος σαρώνει τον κωδικό βλέπει αν είναι γνήσιο, ακυρωμένο ή αν έχει λήξει.</p>
        </div>
        <ul class="trust-list">
            <li><?= icon('shield') ?> <span>Σύνδεσμος 256 bit, μοναδικός για κάθε PDF.</span></li>
            <li><?= icon('ban') ?> <span>Ακύρωση με προαιρετική αιτία, ορατή στο QR.</span></li>
            <li><?= icon('log') ?> <span>Καταγραφή σύνδεσης, μεταφόρτωσης και προβολής.</span></li>
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
            <label class="form-label" for="email">Email</label>
            <input class="form-control" id="email" name="email" type="email" autocomplete="username" required maxlength="190" value="<?= e($email) ?>">
            <label class="form-label" for="password">Κωδικός</label>
            <div class="password-row">
                <input class="form-control" id="password" name="password" type="password" autocomplete="current-password" required>
                <button class="btn btn-link password-toggle" type="button" data-password-toggle="password">Εμφάνιση</button>
            </div>
            <button class="btn btn-seal btn-lg w-100" type="submit">Είσοδος</button>
        </form>
    </section>
</div>
