<?php page_head('Επισκόπηση', 'Καλώς ήρθατε, ' . full_name($user), 'Από εδώ φαίνεται η κατάσταση του μητρώου και τα έγγραφα που μόλις σφραγίστηκαν.'); ?>
<?php if (($user['role'] ?? '') === 'manager' && !$restricted): ?>
    <div class="alert alert-warning">Δεν έχουν οριστεί εσωτερικά δίκτυα. Οι χρήστες μπορούν να συνδεθούν από οποιαδήποτε διεύθυνση μέχρι να δηλωθούν στη σελίδα παραμέτρων.</div>
<?php endif; ?>
<section class="stat-grid">
    <article class="stat-card">
        <p>Ενεργά</p>
        <strong><?= (int) ($stats['active_count'] ?? 0) ?></strong>
    </article>
    <article class="stat-card">
        <p>Ακυρωμένα</p>
        <strong><?= (int) ($stats['cancelled_count'] ?? 0) ?></strong>
    </article>
    <article class="stat-card">
        <p>Προς επιβεβαίωση</p>
        <strong><?= (int) ($stats['pending_count'] ?? 0) ?></strong>
    </article>
    <?php if (($user['role'] ?? '') === 'manager'): ?>
        <article class="stat-card">
            <p>Χρήστες</p>
            <strong><?= (int) $userCount ?></strong>
        </article>
    <?php endif; ?>
</section>
<div class="split-panels">
    <section class="paper-card">
        <div class="card-head">
            <h2>Πρόσφατα έγγραφα</h2>
            <a class="btn btn-seal btn-sm" href="<?= e(url('/documents/new')) ?>">Νέα επικύρωση</a>
        </div>
        <?php require BASE_PATH . '/views/partials/document_table.php'; ?>
    </section>
    <section class="paper-card">
        <div class="card-head">
            <h2><?= ($user['role'] ?? '') === 'manager' ? 'Κινήσεις αρχείων' : 'Οι κινήσεις μου' ?></h2>
            <a href="<?= e(url('/history')) ?>">Όλο το ιστορικό</a>
        </div>
        <?php if ($activity === []): ?>
            <p class="empty-copy">Δεν υπάρχουν ακόμη κινήσεις αρχείων.</p>
        <?php else: ?>
            <ul class="activity-list">
                <?php foreach ($activity as $row): ?>
                    <li>
                        <span class="act act-<?= e((string) $row['action']) ?>"><?= e(Logger::label((string) $row['action'])) ?></span>
                        <div>
                            <strong><?= e((string) $row['details']) ?></strong>
                            <small><?= e(fmt_dt((string) $row['created_at'])) ?><?php if (($user['role'] ?? '') === 'manager' && !empty($row['email'])): ?> · <?= e(full_name($row) !== '' ? full_name($row) : (string) $row['email']) ?><?php endif; ?></small>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
</div>
