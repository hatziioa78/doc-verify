<?php page_head('Σύστημα', 'Παράμετροι', 'Το όνομα της κεφαλίδας, η μορφή της σφραγίδας, το URL του QR, το URL του διακομιστή, το email, το υποσέλιδο, η MySQL και τα δίκτυα σύνδεσης.'); ?>
<section class="summary-strip">
    <span>Συνδεδεμένη βάση <strong><?= e($summary['name']) ?></strong> στο <?= e($summary['host']) ?></span>
    <span><?= (int) $summary['documents'] ?> έγγραφα</span>
    <span><?= (int) $summary['users'] ?> χρήστες</span>
</section>

<form class="paper-card stack-form" method="post" action="<?= e(url('/settings/profile')) ?>">
    <?= csrf_field() ?>
    <h2>Ταυτότητα ιστοτόπου</h2>
    <div class="row g-3">
        <div class="col-md-6">
            <label class="form-label" for="header_name">Όνομα στην κεφαλίδα</label>
            <input class="form-control" id="header_name" name="header_name" required maxlength="120" value="<?= e(Settings::headerName()) ?>">
        </div>
        <div class="col-md-6">
            <label class="form-label" for="site_url">URL για το QR</label>
            <input class="form-control" id="site_url" name="site_url" type="url" required value="<?= e(Settings::siteUrl()) ?>">
            <p class="field-hint">Μπαίνει στον κωδικό QR: <?= e(Settings::siteUrl()) ?>/v/… Τα ήδη σφραγισμένα PDF κρατούν το URL της στιγμής που δημιουργήθηκαν.</p>
        </div>
        <div class="col-md-6">
            <label class="form-label" for="server_url">URL διακομιστή</label>
            <input class="form-control" id="server_url" name="server_url" type="url" required value="<?= e(Settings::serverUrl()) ?>">
            <p class="field-hint">Χρησιμοποιείται στους συνδέσμους των email. Μπορεί να διαφέρει από το URL του QR όταν η εφαρμογή είναι πίσω από web proxy.</p>
        </div>
        <div class="col-md-6">
            <label class="form-label" for="footer_contact">Επικοινωνία στο υποσέλιδο</label>
            <textarea class="form-control" id="footer_contact" name="footer_contact" rows="3" maxlength="500"><?= e(Settings::get('footer_contact')) ?></textarea>
        </div>
        <div class="col-md-6">
            <label class="form-label" for="footer_credits">Ιδιοκτησία / κατασκευή στο υποσέλιδο</label>
            <textarea class="form-control" id="footer_credits" name="footer_credits" rows="3" maxlength="500"><?= e(Settings::get('footer_credits')) ?></textarea>
        </div>
        <div class="col-12">
            <p class="form-label">Ψηφιακή σφραγίδα</p>
            <?php $placement = Settings::stampPlacement(); ?>
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
            <p class="field-hint">Ισχύει για τις επόμενες επικυρώσεις. Ένα ήδη σφραγισμένο PDF αλλάζει μορφή μόνο αν το έγγραφο επεξεργαστεί ξανά. Τα πλήρη στοιχεία μένουν στη σελίδα επαλήθευσης.</p>
        </div>
    </div>
    <button class="btn btn-seal" type="submit">Αποθήκευση εμφάνισης</button>
</form>

<form class="paper-card stack-form" method="post" action="<?= e(url('/settings/mail')) ?>">
    <?= csrf_field() ?>
    <h2>Email επιβεβαίωσης</h2>
    <p class="field-hint">Κάθε αίτημα επιβεβαίωσης μπορεί να σταλεί στη Γραμματεία και σε μία ακόμη διεύθυνση. Ο σύνδεσμος «Πατήστε εδώ» συνδέει τον παραλήπτη ως Γραμματεία, στο συγκεκριμένο έγγραφο. Ο κωδικός SMTP αποθηκεύεται στη βάση και περιλαμβάνεται στα αντίγραφα SQL.</p>
    <div class="row g-3">
        <div class="col-md-5">
            <label class="form-label" for="smtp_host">Διακομιστής SMTP</label>
            <input class="form-control" id="smtp_host" name="smtp_host" value="<?= e(Settings::get('smtp_host')) ?>" maxlength="253">
        </div>
        <div class="col-md-2">
            <label class="form-label" for="smtp_port">Θύρα</label>
            <input class="form-control" id="smtp_port" name="smtp_port" inputmode="numeric" value="<?= e(Settings::get('smtp_port', '587')) ?>">
        </div>
        <div class="col-md-5">
            <label class="form-label" for="smtp_security">Ασφάλεια</label>
            <select class="form-select" id="smtp_security" name="smtp_security">
                <?php $security = Settings::get('smtp_security', 'tls'); ?>
                <option value="tls" <?= $security === 'tls' ? 'selected' : '' ?>>TLS (συνήθως 587)</option>
                <option value="ssl" <?= $security === 'ssl' ? 'selected' : '' ?>>SSL (συνήθως 465)</option>
                <option value="none" <?= $security === 'none' ? 'selected' : '' ?>>Χωρίς κρυπτογράφηση</option>
            </select>
        </div>
        <div class="col-md-6">
            <label class="form-label" for="smtp_username">Όνομα χρήστη</label>
            <input class="form-control" id="smtp_username" name="smtp_username" value="<?= e(Settings::get('smtp_username')) ?>" maxlength="190" autocomplete="off">
        </div>
        <div class="col-md-6">
            <label class="form-label" for="smtp_password">Κωδικός</label>
            <input class="form-control" id="smtp_password" name="smtp_password" type="password" autocomplete="new-password" placeholder="<?= Settings::secret('smtp_password') !== '' ? 'Αποθηκευμένος · κενό = χωρίς αλλαγή' : '' ?>">
        </div>
        <div class="col-md-6">
            <label class="form-label" for="smtp_from_name">Όνομα αποστολέα</label>
            <input class="form-control" id="smtp_from_name" name="smtp_from_name" maxlength="120" value="<?= e(Settings::get('smtp_from_name')) ?>">
        </div>
        <div class="col-md-6">
            <label class="form-label" for="smtp_from_email">Email αποστολέα</label>
            <input class="form-control" id="smtp_from_email" name="smtp_from_email" type="email" maxlength="190" value="<?= e(Settings::get('smtp_from_email')) ?>">
        </div>
        <div class="col-12">
            <label class="check-line">
                <input type="checkbox" name="notify_secretary" value="1" <?= Settings::flag('notify_secretary') ? 'checked' : '' ?>>
                <span>Να στέλνεται email στη Γραμματεία που έχει ενεργή τη λήψη</span>
            </label>
        </div>
        <div class="col-md-6">
            <label class="check-line">
                <input type="checkbox" name="notify_extra" value="1" <?= Settings::flag('notify_extra') ? 'checked' : '' ?>>
                <span>Να προωθείται και σε άλλο email</span>
            </label>
        </div>
        <div class="col-md-6">
            <label class="form-label" for="notify_extra_email">Άλλο email</label>
            <input class="form-control" id="notify_extra_email" name="notify_extra_email" type="email" maxlength="190" value="<?= e(Settings::get('notify_extra_email')) ?>">
        </div>
    </div>
    <div class="action-row">
        <button class="btn btn-seal" type="submit">Αποθήκευση email</button>
        <button class="btn btn-ink" type="submit" formaction="<?= e(url('/settings/mail/test')) ?>">Δοκιμαστική αποστολή</button>
    </div>
</form>

<form class="paper-card stack-form" method="post" action="<?= e(url('/settings/networks')) ?>">
    <?= csrf_field() ?>
    <h2>Εσωτερικά δίκτυα χρηστών</h2>
    <p class="field-hint">Οι χρήστες και η γραμματεία συνδέονται με κωδικό μόνο από αυτές τις διευθύνσεις. Ο διαχειριστής συνδέεται από οπουδήποτε. Ο σύνδεσμος email επιβεβαίωσης συνδέει τη Γραμματεία και εκτός λίστας. Αν η λίστα είναι κενή, δεν υπάρχει περιορισμός.</p>
    <div data-network-list>
        <?php
        $rows = $networks;
        $rows[] = ['cidr' => '', 'label' => ''];
        foreach ($rows as $network):
        ?>
            <div class="row g-2 network-row">
                <div class="col-md-5">
                    <input class="form-control" name="cidr[]" placeholder="192.168.1.0/24" value="<?= e((string) $network['cidr']) ?>">
                </div>
                <div class="col-md-5">
                    <input class="form-control" name="label[]" placeholder="Περιγραφή" value="<?= e((string) $network['label']) ?>">
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <div class="action-row">
        <button class="btn btn-ghost" type="button" data-add-network>Προσθήκη δικτύου</button>
        <button class="btn btn-seal" type="submit">Αποθήκευση δικτύων</button>
    </div>
</form>

<section class="paper-card">
    <h2>MySQL</h2>
    <form class="stack-form" method="post" action="<?= e(url('/settings/sql/test')) ?>">
        <?= csrf_field() ?>
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label" for="db_host">Διεύθυνση</label>
                <input class="form-control" id="db_host" name="db_host" required value="<?= e((string) $db['host']) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label" for="db_port">Θύρα</label>
                <input class="form-control" id="db_port" name="db_port" required value="<?= e((string) $db['port']) ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label" for="db_name">Βάση</label>
                <input class="form-control" id="db_name" name="db_name" required value="<?= e((string) $db['name']) ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label" for="db_user">Χρήστης</label>
                <input class="form-control" id="db_user" name="db_user" required autocomplete="off" value="<?= e((string) $db['user']) ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label" for="db_pass">Κωδικός</label>
                <input class="form-control" id="db_pass" name="db_pass" type="password" autocomplete="new-password" placeholder="Κενό = ο αποθηκευμένος κωδικός">
            </div>
        </div>
        <div class="action-row">
            <button class="btn btn-ink" type="submit">Έλεγχος σύνδεσης</button>
            <button class="btn btn-ghost" type="submit" formaction="<?= e(url('/settings/sql/save')) ?>">Αποθήκευση στοιχείων</button>
            <button class="btn btn-ghost" type="submit" formaction="<?= e(url('/settings/sql/init')) ?>">Αρχικοποίηση βάσης</button>
        </div>
        <label class="check-line">
            <input type="checkbox" name="understand" value="1">
            <span>Κατανοώ ότι η αρχικοποίηση θα δημιουργήσει τη βάση, αν λείπει, και θα συνδέσει την εφαρμογή σε αυτή.</span>
        </label>
        <label class="form-label" for="account_password">Κωδικός διαχειριστή για αποθήκευση, αρχικοποίηση, αντίγραφο ή επαναφορά</label>
        <input class="form-control narrow-field" id="account_password" name="account_password" type="password" autocomplete="current-password">
    </form>
</section>

<div class="split-panels">
    <form class="paper-card stack-form" method="post" action="<?= e(url('/settings/sql/backup')) ?>">
        <?= csrf_field() ?>
        <h2>Αντίγραφο SQL</h2>
        <p class="field-hint">Κατεβαίνει αρχείο με τους πίνακες του μητρώου. Τα PDF μένουν στον φάκελο αρχείων του διακομιστή.</p>
        <label class="form-label" for="backup_password">Κωδικός διαχειριστή</label>
        <input class="form-control" id="backup_password" name="account_password" type="password" autocomplete="current-password" required>
        <button class="btn btn-seal" type="submit">Λήψη αντιγράφου</button>
        <?php if ($backups !== []): ?>
            <ul class="backup-list">
                <?php foreach ($backups as $backup): ?>
                    <li>
                        <a href="<?= e(url('/settings/backups/' . $backup['name'])) ?>"><?= e($backup['name']) ?></a>
                        <small><?= e(format_bytes((int) $backup['size'])) ?> · <?= e(date('d/m/Y H:i', (int) $backup['mtime'])) ?></small>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </form>
    <form class="paper-card stack-form danger-card" method="post" action="<?= e(url('/settings/sql/restore')) ?>" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <h2>Επαναφορά SQL</h2>
        <p>Η επαναφορά αντικαθιστά τα δεδομένα της τρέχουσας βάσης.</p>
        <label class="form-label" for="sql">Αρχείο .sql</label>
        <input class="form-control" id="sql" name="sql" type="file" accept=".sql,text/plain" required>
        <label class="form-label" for="restore_confirm">Πληκτρολογήστε ΕΠΑΝΑΦΟΡΑ</label>
        <input class="form-control" id="restore_confirm" name="confirm" required autocomplete="off">
        <label class="form-label" for="restore_password">Κωδικός διαχειριστή</label>
        <input class="form-control" id="restore_password" name="account_password" type="password" autocomplete="current-password" required>
        <button class="btn btn-wax" type="submit">Επαναφορά</button>
    </form>
</div>
