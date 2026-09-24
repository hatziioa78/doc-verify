<?php
$isEdit = !empty($editing);
$needsApproval = !$isEdit && !Documents::certifiesImmediately($user);
if ($isEdit) {
    page_head('Επεξεργασία', 'Στοιχεία εγγράφου', 'Η αλλαγή ενημερώνει το μητρώο. Αν το έγγραφο έχει ήδη σφραγιστεί, ανανεώνεται και η ψηφιακή σφραγίδα του PDF.');
} elseif ($needsApproval) {
    page_head('Νέα καταχώρηση', 'Υποβολή για επιβεβαίωση', 'Το PDF αποθηκεύεται και περιμένει τη Γραμματεία. Η σφράγιση με QR γίνεται μετά την επιβεβαίωση.');
} else {
    $stampHint = match (Settings::stampPlacement()) {
        'header' => 'Σε κάθε σελίδα μπαίνει κεφαλίδα με τα στοιχεία και τον κωδικό QR.',
        'appendix' => 'Στο τέλος προστίθεται σελίδα παραρτήματος με τον κωδικό QR.',
        default => 'Σε κάθε σελίδα μπαίνει υποσέλιδο με τα στοιχεία και τον κωδικό QR.',
    };
    page_head('Νέα καταχώρηση', 'Σφράγισμα PDF', $stampHint . ' Η ημερομηνία καταχώρησης ορίζεται από το σύστημα.');
}
?>
<?php errors_box($errors); ?>
<form class="paper-card stack-form" method="post" action="<?= e(url($isEdit ? '/documents/' . (int) $editing['id'] : '/documents')) ?>" <?= $isEdit ? '' : 'enctype="multipart/form-data" data-loading' ?>>
    <?= csrf_field() ?>
    <div class="row g-3">
        <?php if (!$isEdit): ?>
            <div class="col-lg-7">
                <label class="form-label" for="pdf">Αρχείο PDF</label>
                <input class="form-control" id="pdf" name="pdf" type="file" accept="application/pdf,.pdf" required>
                <p class="field-hint">Μόνο μη κλειδωμένο PDF, έως 20 MB.</p>
            </div>
        <?php endif; ?>
        <div class="col-lg-5">
            <label class="form-label">Ημερομηνία καταχώρησης</label>
            <input class="form-control" value="<?= e($isEdit ? fmt_dt((string) $editing['registered_at']) : fmt_dt(now())) ?>" readonly>
            <p class="field-hint"><?= $isEdit ? 'Η ημερομηνία καταχώρησης δεν αλλάζει.' : 'Καταγράφεται αυτόματα τη στιγμή της αποθήκευσης.' ?></p>
        </div>
        <div class="col-md-8">
            <label class="form-label" for="subject">Θέμα</label>
            <input class="form-control" id="subject" name="subject" required maxlength="255" value="<?= e($old['subject']) ?>">
        </div>
        <div class="col-md-4">
            <label class="form-label" for="protocol_number">Αριθμός πρωτοκόλλου</label>
            <input class="form-control" id="protocol_number" name="protocol_number" required maxlength="120" value="<?= e($old['protocol_number']) ?>">
        </div>
        <div class="col-12">
            <label class="form-label" for="issuing_authority">Εκδούσα αρχή / τμήμα</label>
            <input class="form-control" id="issuing_authority" name="issuing_authority" required maxlength="255" value="<?= e($old['issuing_authority']) ?>">
        </div>
        <div class="col-12">
            <label class="form-label" for="info">Πληροφορίες</label>
            <textarea class="form-control" id="info" name="info" rows="5" required><?= e($old['info']) ?></textarea>
            <?php if (!$isEdit): ?>
                <p class="field-hint">Προσυμπληρώνονται το ονοματεπώνυμο και το email σας. Μπορείτε να τα συμπληρώσετε.</p>
            <?php endif; ?>
        </div>
    </div>
    <?php if ($isEdit): ?>
        <button class="btn btn-seal btn-lg" type="submit">Αποθήκευση αλλαγών</button>
    <?php elseif ($needsApproval): ?>
        <button class="btn btn-seal btn-lg" type="submit" data-loading-text="Γίνεται η υποβολή…">Υποβολή για επιβεβαίωση</button>
    <?php else: ?>
        <button class="btn btn-seal btn-lg" type="submit" data-loading-text="Γίνεται η επικύρωση…">Επικύρωση και προσθήκη QR</button>
    <?php endif; ?>
</form>
