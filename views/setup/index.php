<div class="setup-shell">
    <header class="page-head text-center">
        <p class="kicker">Πρώτη εκκίνηση</p>
        <h1>Εγκατάσταση του ΣΦΡΑΓΙΣ</h1>
        <p class="lede">Έλεγχοι για Apache σε Ubuntu, δοκιμή MySQL και SMTP, και δημιουργία του πρώτου διαχειριστή. Μετά την ολοκλήρωση ο οδηγός κλειδώνει.</p>
    </header>
    <?php errors_box($errors); ?>
    <section class="paper-card">
        <h2><?= icon('shield') ?> Έλεγχοι διακομιστή</h2>
        <ul class="check-list">
            <?php foreach ($checks as $check): ?>
                <?php
                $state = $check['ok'] ? 'active' : ($check['required'] ? 'cancelled' : 'expired');
                $mark = $check['ok'] ? 'Εντάξει' : ($check['required'] ? 'Απαιτείται' : 'Προσοχή');
                ?>
                <li>
                    <span class="status-pill <?= e(state_class($state)) ?>"><?= e($mark) ?></span>
                    <div>
                        <strong><?= e($check['label']) ?></strong>
                        <p><?= e($check['detail']) ?></p>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>
    <form method="post" action="<?= e(url('/setup.php')) ?>" class="setup-grid" id="setup-form">
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
            <h2>Email SMTP</h2>
            <p class="field-hint">Προαιρετικό στην εγκατάσταση. Αν συμπληρωθεί, η δοκιμή στέλνει μήνυμα πριν κλειδώσει ο οδηγός. Τα αιτήματα επιβεβαίωσης μπορούν να πηγαίνουν στη Γραμματεία.</p>
            <div class="row g-3">
                <div class="col-md-5">
                    <label class="form-label" for="smtp_host">Διακομιστής SMTP</label>
                    <input class="form-control" id="smtp_host" name="smtp_host" value="<?= e((string) $old['host']) ?>" maxlength="253">
                </div>
                <div class="col-md-2">
                    <label class="form-label" for="smtp_port">Θύρα</label>
                    <input class="form-control" id="smtp_port" name="smtp_port" inputmode="numeric" value="<?= e((string) $old['port']) ?>">
                </div>
                <div class="col-md-5">
                    <label class="form-label" for="smtp_security">Ασφάλεια</label>
                    <select class="form-select" id="smtp_security" name="smtp_security">
                        <option value="tls" <?= $old['security'] === 'tls' ? 'selected' : '' ?>>TLS (συνήθως 587)</option>
                        <option value="ssl" <?= $old['security'] === 'ssl' ? 'selected' : '' ?>>SSL (συνήθως 465)</option>
                        <option value="none" <?= $old['security'] === 'none' ? 'selected' : '' ?>>Χωρίς κρυπτογράφηση</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="smtp_username">Όνομα χρήστη</label>
                    <input class="form-control" id="smtp_username" name="smtp_username" value="<?= e((string) $old['username']) ?>" maxlength="190" autocomplete="off">
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="smtp_password">Κωδικός</label>
                    <input class="form-control" id="smtp_password" name="smtp_password" type="password" autocomplete="new-password" value="<?= e((string) $old['password']) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="smtp_from_name">Όνομα αποστολέα</label>
                    <input class="form-control" id="smtp_from_name" name="smtp_from_name" maxlength="120" value="<?= e((string) $old['from_name']) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="smtp_from_email">Email αποστολέα</label>
                    <input class="form-control" id="smtp_from_email" name="smtp_from_email" type="email" maxlength="190" value="<?= e((string) $old['from_email']) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="mail_test_to">Παραλήπτης δοκιμής</label>
                    <input class="form-control" id="mail_test_to" name="mail_test_to" type="email" maxlength="190" value="<?= e((string) $old['mail_test_to']) ?>" placeholder="Αν μείνει κενό, χρησιμοποιείται το email του διαχειριστή">
                </div>
                <div class="col-12">
                    <label class="check-line">
                        <input type="checkbox" name="notify_secretary" value="1" <?= $old['notify_secretary'] === '1' ? 'checked' : '' ?>>
                        <span>Να στέλνεται email σε κάθε ενεργή Γραμματεία όταν ζητείται επιβεβαίωση</span>
                    </label>
                </div>
            </div>
        </section>
        <section class="paper-card">
            <h2><?= icon('user') ?> Διαχειριστής</h2>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label" for="full_name">Ονοματεπώνυμο</label>
                    <input class="form-control" id="full_name" name="full_name" required maxlength="200" value="<?= e($old['full_name']) ?>">
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
                    <p class="field-hint"><?= e(password_policy_hint()) ?></p>
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
                    <p class="form-label">Ψηφιακή σφραγίδα</p>
                    <?php $placement = (string) ($old['stamp_placement'] ?? 'footer'); ?>
                    <label class="check-line">
                        <input type="radio" name="stamp_placement" value="footer" <?= $placement === 'footer' ? 'checked' : '' ?>>
                        <span>Υποσέλιδο σε κάθε σελίδα, με μικρά στοιχεία και QR.</span>
                    </label>
                    <label class="check-line">
                        <input type="radio" name="stamp_placement" value="header" <?= $placement === 'header' ? 'checked' : '' ?>>
                        <span>Κεφαλίδα σε κάθε σελίδα, με μικρά στοιχεία και QR.</span>
                    </label>
                    <label class="check-line">
                        <input type="radio" name="stamp_placement" value="appendix" <?= $placement === 'appendix' ? 'checked' : '' ?>>
                        <span>Παράρτημα: ξεχωριστή τελευταία σελίδα.</span>
                    </label>
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
            <button class="btn btn-ink" type="submit" name="setup_action" value="test-sql">Έλεγχος σύνδεσης SQL</button>
            <button class="btn btn-ink" type="submit" name="setup_action" value="test-mail">Δοκιμή email</button>
            <button class="btn btn-seal" type="submit" name="setup_action" value="install" <?= $ready ? '' : 'disabled' ?>>Δημιουργία βάσης και ολοκλήρωση</button>
        </div>
        <?php if (!$ready): ?>
            <p class="field-hint">Η ολοκλήρωση μένει κλειστή μέχρι να περάσουν οι υποχρεωτικοί έλεγχοι του διακομιστή.</p>
        <?php endif; ?>
    </form>
</div>
