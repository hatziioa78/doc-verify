<?php page_head('Νέα καταχώρηση', 'Σφράγισμα PDF', 'Το αρχείο μένει ως έχει και στο τέλος του προστίθεται σελίδα με τον κωδικό QR. Η ημερομηνία καταχώρησης ορίζεται από το σύστημα.'); ?>
<?php errors_box($errors); ?>
<form class="paper-card stack-form" method="post" action="<?= e(url('/documents')) ?>" enctype="multipart/form-data" data-loading>
    <?= csrf_field() ?>
    <div class="row g-3">
        <div class="col-lg-7">
            <label class="form-label" for="pdf">Αρχείο PDF</label>
            <input class="form-control" id="pdf" name="pdf" type="file" accept="application/pdf,.pdf" required>
            <p class="field-hint">Μόνο μη κλειδωμένο PDF, έως 20 MB.</p>
        </div>
        <div class="col-lg-5">
            <label class="form-label">Ημερομηνία καταχώρησης</label>
            <input class="form-control" value="<?= e(fmt_dt(now())) ?>" readonly>
            <p class="field-hint">Καταγράφεται αυτόματα τη στιγμή της αποθήκευσης.</p>
        </div>
        <div class="col-md-8">
            <label class="form-label" for="subject">Θέμα</label>
            <input class="form-control" id="subject" name="subject" required maxlength="255" value="<?= e($old['subject']) ?>">
        </div>
        <div class="col-md-4">
            <label class="form-label" for="protocol_number">Αριθμός πρωτοκόλλου</label>
            <input class="form-control" id="protocol_number" name="protocol_number" required maxlength="120" value="<?= e($old['protocol_number']) ?>">
        </div>
        <div class="col-md-8">
            <label class="form-label" for="issuing_authority">Εκδούσα αρχή / τμήμα</label>
            <input class="form-control" id="issuing_authority" name="issuing_authority" required maxlength="255" value="<?= e($old['issuing_authority']) ?>">
        </div>
        <div class="col-md-4">
            <label class="form-label" for="valid_until">Έγκυρο έως</label>
            <input class="form-control" id="valid_until" name="valid_until" type="date" required value="<?= e($old['valid_until']) ?>">
            <p class="field-hint">Προεπιλογή: <?= (int) Settings::validityMonths() ?> μήνες.</p>
        </div>
        <div class="col-12">
            <label class="form-label" for="info">Πληροφορίες</label>
            <textarea class="form-control" id="info" name="info" rows="5" required><?= e($old['info']) ?></textarea>
            <p class="field-hint">Προσυμπληρώνονται το ονοματεπώνυμο και το email σας. Μπορείτε να τα συμπληρώσετε.</p>
        </div>
    </div>
    <button class="btn btn-seal btn-lg" type="submit" data-loading-text="Γίνεται η επικύρωση…">Επικύρωση και προσθήκη QR</button>
</form>
