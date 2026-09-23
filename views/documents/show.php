<?php
$state = document_state($doc);
$owner = trim((string) $doc['last_name'] . ' ' . (string) $doc['first_name']);
$canceller = trim((string) ($doc['canceller_last_name'] ?? '') . ' ' . (string) ($doc['canceller_first_name'] ?? ''));
?>
<?php page_head('Έγγραφο μητρώου', (string) $doc['subject'], 'Η σελίδα επαλήθευσης είναι η ζωντανή εικόνα του εγγράφου. Η ακύρωση φαίνεται αμέσως σε όποιον σαρώνει το QR.'); ?>
<section class="doc-hero paper-card">
    <div>
        <span class="status-pill <?= e(state_class($state)) ?>"><?= e(state_label($state)) ?></span>
        <?php if ($state === 'cancelled'): ?>
            <p class="reason-block">
                <strong>Αιτία ακύρωσης:</strong>
                <?= e((string) ($doc['cancellation_reason'] ?: 'Δεν δηλώθηκε αιτία.')) ?>
                <?php if ($canceller !== ''): ?><br><small>Από <?= e($canceller) ?> · <?= e(fmt_dt((string) $doc['cancelled_at'])) ?></small><?php endif; ?>
            </p>
        <?php endif; ?>
        <dl class="meta-grid">
            <div><dt>Αριθμός πρωτοκόλλου</dt><dd class="proto"><?= e((string) $doc['protocol_number']) ?></dd></div>
            <div><dt>Εκδούσα αρχή / τμήμα</dt><dd><?= e((string) $doc['issuing_authority']) ?></dd></div>
            <div><dt>Καταχώρηση</dt><dd><?= e(fmt_dt((string) $doc['registered_at'])) ?></dd></div>
            <div><dt>Έγκυρο έως</dt><dd><?= e(fmt_date((string) $doc['valid_until'])) ?></dd></div>
            <div><dt>Καταχωρίστηκε από</dt><dd><?= e($owner) ?><small><?= e((string) $doc['owner_email']) ?></small></dd></div>
            <div><dt>Αρχικό αρχείο</dt><dd><?= e((string) $doc['original_name']) ?></dd></div>
        </dl>
        <h2>Πληροφορίες</h2>
        <div class="info-block"><?= nl2br(e((string) $doc['info'])) ?></div>
        <p class="hash-line"><span>SHA-256 αρχικού PDF</span><code id="doc-hash"><?= e((string) $doc['sha256']) ?></code></p>
    </div>
    <aside class="qr-panel">
        <img src="<?= e(url('/documents/' . $doc['id'] . '/qr.png')) ?>" alt="Κωδικός QR επαλήθευσης" width="220" height="220">
        <label class="form-label" for="verify-url">Σύνδεσμος επαλήθευσης</label>
        <input class="form-control" id="verify-url" readonly value="<?= e($verifyUrl) ?>">
        <button class="btn btn-ghost" type="button" data-copy="#verify-url">Αντιγραφή συνδέσμου</button>
        <a class="btn btn-seal" href="<?= e(url('/documents/' . $doc['id'] . '/download')) ?>"><?= icon('download') ?> Λήψη σφραγισμένου PDF</a>
    </aside>
</section>
<?php if ($canModify && $state !== 'cancelled'): ?>
    <div class="action-row">
        <button class="btn btn-wax" type="button" data-bs-toggle="modal" data-bs-target="#cancelModal">Ακύρωση εγγράφου</button>
        <button class="btn btn-ghost" type="button" data-bs-toggle="modal" data-bs-target="#deleteModal">Διαγραφή</button>
    </div>
<?php elseif ($canModify): ?>
    <div class="action-row">
        <button class="btn btn-ghost" type="button" data-bs-toggle="modal" data-bs-target="#deleteModal">Διαγραφή</button>
    </div>
<?php else: ?>
    <p class="field-hint">Μπορείτε να δείτε και να κατεβάσετε το έγγραφο. Ακύρωση και διαγραφή επιτρέπονται μόνο στον κάτοχο ή στον διαχειριστή.</p>
<?php endif; ?>

<div class="modal fade" id="cancelModal" tabindex="-1" aria-labelledby="cancelTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content" method="post" action="<?= e(url('/documents/' . $doc['id'] . '/cancel')) ?>">
            <?= csrf_field() ?>
            <div class="modal-header">
                <h2 class="modal-title h5" id="cancelTitle">Ακύρωση εγγράφου</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Κλείσιμο"></button>
            </div>
            <div class="modal-body">
                <p>Το έγγραφο θα συνεχίσει να εμφανίζεται, με ένδειξη ακύρωσης, και στο QR.</p>
                <label class="form-label" for="reason">Αιτία ακύρωσης, προαιρετικά</label>
                <textarea class="form-control" id="reason" name="reason" rows="3" maxlength="500"></textarea>
            </div>
            <div class="modal-footer">
                <button class="btn btn-ghost" type="button" data-bs-dismiss="modal">Άκυρο</button>
                <button class="btn btn-wax" type="submit">Ακύρωση</button>
            </div>
        </form>
    </div>
</div>
<div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content" method="post" action="<?= e(url('/documents/' . $doc['id'] . '/delete')) ?>">
            <?= csrf_field() ?>
            <div class="modal-header">
                <h2 class="modal-title h5" id="deleteTitle">Οριστική απόκρυψη</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Κλείσιμο"></button>
            </div>
            <div class="modal-body">
                <p>Το έγγραφο δεν θα εμφανίζεται πουθενά και ο σύνδεσμος QR δεν θα το βρίσκει. Η ενέργεια μένει στο ιστορικό.</p>
                <label class="form-label" for="confirm">Πληκτρολογήστε ΔΙΑΓΡΑΦΗ</label>
                <input class="form-control" id="confirm" name="confirm" required autocomplete="off">
            </div>
            <div class="modal-footer">
                <button class="btn btn-ghost" type="button" data-bs-dismiss="modal">Άκυρο</button>
                <button class="btn btn-wax" type="submit">Διαγραφή</button>
            </div>
        </form>
    </div>
</div>
