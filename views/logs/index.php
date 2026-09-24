<?php
$isHistory = $mode === 'history';
page_head(
    $isHistory ? 'Ιστορικό' : 'Ασφάλεια',
    $pageTitle,
    $isHistory
        ? 'Ποιος ανέβασε, επικύρωσε, ακύρωσε, διέγραψε ή κατέβασε έγγραφο. Κάθε χρήστης βλέπει μόνο τις δικές του κινήσεις αρχείων.'
        : 'Κάθε σύνδεση και ενέργεια: διεύθυνση, χρόνος και είδος.'
);
?>
<form class="paper-card filter-card" method="get" action="<?= e(url($isHistory ? '/history' : '/logs')) ?>">
    <div class="row g-3">
        <?php if (!$isHistory): ?>
            <div class="col-md-3">
                <label class="form-label" for="action">Ενέργεια</label>
                <select class="form-select" id="action" name="action">
                    <option value="">Όλες</option>
                    <?php foreach (Logger::LABELS as $key => $label): ?>
                        <option value="<?= e($key) ?>" <?= $filters['action'] === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="ip">IP</label>
                <input class="form-control" id="ip" name="ip" value="<?= e($filters['ip']) ?>">
            </div>
        <?php endif; ?>
        <?php if (!$isHistory || ($currentUser['role'] ?? '') === 'manager'): ?>
            <div class="col-md-3">
                <label class="form-label" for="person">Χρήστης</label>
                <input class="form-control" id="person" name="person" value="<?= e($filters['person']) ?>">
            </div>
        <?php endif; ?>
        <div class="col-md-3">
            <label class="form-label" for="q">Λεπτομέρειες</label>
            <input class="form-control" id="q" name="q" value="<?= e($filters['q']) ?>">
        </div>
        <div class="col-md-3">
            <label class="form-label" for="from">Από</label>
            <input class="form-control" id="from" name="from" type="date" value="<?= e($filters['from']) ?>">
        </div>
        <div class="col-md-3">
            <label class="form-label" for="to">Έως</label>
            <input class="form-control" id="to" name="to" type="date" value="<?= e($filters['to']) ?>">
        </div>
        <div class="col-md-3 d-flex align-items-end">
            <button class="btn btn-ink" type="submit">Αναζήτηση</button>
        </div>
    </div>
</form>
<section class="paper-card">
    <?php if ($rows === []): ?>
        <p class="empty-copy">Δεν βρέθηκαν εγγραφές.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="doc-table">
                <thead>
                    <tr>
                        <th>Χρόνος</th>
                        <th>IP</th>
                        <th>Ενέργεια</th>
                        <?php if (!$isHistory || ($currentUser['role'] ?? '') === 'manager'): ?><th>Χρήστης</th><?php endif; ?>
                        <th>Λεπτομέρειες</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <td><?= e(fmt_dt((string) $row['created_at'])) ?></td>
                            <td class="proto"><?= e((string) $row['ip']) ?></td>
                            <td><span class="act act-<?= e((string) $row['action']) ?>"><?= e(Logger::label((string) $row['action'])) ?></span></td>
                            <?php if (!$isHistory || ($currentUser['role'] ?? '') === 'manager'): ?>
                                <td><?= e(full_name($row)) ?><?php if (!empty($row['email'])): ?><small><?= e((string) $row['email']) ?></small><?php endif; ?></td>
                            <?php endif; ?>
                            <td>
                                <?= e((string) $row['details']) ?>
                                <?php if (!empty($row['subject']) && empty($row['deleted_at'])): ?>
                                    <small><a href="<?= e(url('/documents/' . $row['document_id'])) ?>"><?= e((string) $row['subject']) ?></a></small>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php require BASE_PATH . '/views/partials/pagination.php'; ?>
    <?php endif; ?>
</section>
