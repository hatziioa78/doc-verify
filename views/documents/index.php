<?php page_head('Μητρώο', 'Όλα τα έγγραφα', 'Αναζήτηση σε όλο το μητρώο. Επεξεργασία, ακύρωση και διαγραφή επιτρέπονται στον κάτοχο, στη Γραμματεία και στον διαχειριστή.'); ?>
<form class="paper-card filter-card" method="get" action="<?= e(url('/documents')) ?>">
    <div class="row g-3">
        <div class="col-md-4">
            <label class="form-label" for="subject">Θέμα</label>
            <input class="form-control" id="subject" name="subject" value="<?= e($filters['subject']) ?>">
        </div>
        <div class="col-md-4">
            <label class="form-label" for="protocol">Αριθμός πρωτοκόλλου</label>
            <input class="form-control" id="protocol" name="protocol" value="<?= e($filters['protocol']) ?>">
        </div>
        <div class="col-md-4">
            <label class="form-label" for="authority">Εκδούσα αρχή / τμήμα</label>
            <input class="form-control" id="authority" name="authority" value="<?= e($filters['authority']) ?>">
        </div>
        <div class="col-md-4">
            <label class="form-label" for="person">Χρήστης</label>
            <input class="form-control" id="person" name="person" value="<?= e($filters['person']) ?>" placeholder="Όνομα ή email">
        </div>
        <div class="col-md-4">
            <label class="form-label" for="info">Πληροφορίες</label>
            <input class="form-control" id="info" name="info" value="<?= e($filters['info']) ?>">
        </div>
        <div class="col-md-4">
            <label class="form-label" for="status">Κατάσταση</label>
            <select class="form-select" id="status" name="status">
                <option value="" <?= $filters['status'] === '' ? 'selected' : '' ?>>Όλες</option>
                <option value="pending" <?= $filters['status'] === 'pending' ? 'selected' : '' ?>>Προς επιβεβαίωση</option>
                <option value="active" <?= $filters['status'] === 'active' ? 'selected' : '' ?>>Ενεργά</option>
                <option value="cancelled" <?= $filters['status'] === 'cancelled' ? 'selected' : '' ?>>Ακυρωμένα</option>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label" for="registered_from">Καταχώρηση από</label>
            <input class="form-control" id="registered_from" name="registered_from" type="date" value="<?= e($filters['registered_from']) ?>">
        </div>
        <div class="col-md-3">
            <label class="form-label" for="registered_to">Καταχώρηση έως</label>
            <input class="form-control" id="registered_to" name="registered_to" type="date" value="<?= e($filters['registered_to']) ?>">
        </div>
        <div class="col-md-6">
            <label class="form-label" for="owner_id">Λογαριασμός καταχώρησης</label>
            <select class="form-select" id="owner_id" name="owner_id">
                <option value="0">Όλοι</option>
                <?php foreach ($owners as $owner): ?>
                    <option value="<?= (int) $owner['id'] ?>" <?= (int) $filters['owner_id'] === (int) $owner['id'] ? 'selected' : '' ?>><?= e(full_name($owner) . ' · ' . $owner['email']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-6 d-flex align-items-end gap-2">
            <button class="btn btn-ink" type="submit"><?= icon('search') ?> Αναζήτηση</button>
            <a class="btn btn-ghost" href="<?= e(url('/documents')) ?>">Καθαρισμός</a>
        </div>
    </div>
</form>
<section class="paper-card">
    <div class="card-head">
        <h2><?= (int) $pager['total'] ?> έγγραφα</h2>
        <a class="btn btn-seal btn-sm" href="<?= e(url('/documents/new')) ?>">Νέα επικύρωση</a>
    </div>
    <?php require BASE_PATH . '/views/partials/document_table.php'; ?>
    <?php require BASE_PATH . '/views/partials/pagination.php'; ?>
</section>
