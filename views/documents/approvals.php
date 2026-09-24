<?php page_head('Γραμματεία', 'Προς επιβεβαίωση', 'Έγγραφα που καταχωρίστηκαν από χρήστες και περιμένουν σφράγιση. Ο διαχειριστής και η Γραμματεία μπορούν να τα επιβεβαιώσουν.'); ?>
<section class="paper-card">
    <div class="card-head">
        <h2><?= (int) $pager['total'] ?> σε αναμονή</h2>
    </div>
    <?php if ($rows === []): ?>
        <p class="empty-copy">Δεν υπάρχουν έγγραφα προς επιβεβαίωση.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="doc-table">
                <thead>
                    <tr>
                        <th>Πρωτόκολλο</th>
                        <th>Θέμα</th>
                        <th>Καταχωρητής</th>
                        <th>Καταχώρηση</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $doc): ?>
                        <tr>
                            <td class="proto"><?= e((string) $doc['protocol_number']) ?></td>
                            <td><a class="doc-link" href="<?= e(url('/documents/' . $doc['id'])) ?>"><?= e((string) $doc['subject']) ?></a></td>
                            <td><?= e(full_name($doc)) ?></td>
                            <td><?= e(fmt_dt((string) $doc['registered_at'])) ?></td>
                            <td class="row-actions">
                                <form method="post" action="<?= e(url('/documents/' . $doc['id'] . '/approve')) ?>">
                                    <?= csrf_field() ?>
                                    <button class="btn btn-seal btn-sm" type="submit">Επιβεβαίωση</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php require BASE_PATH . '/views/partials/pagination.php'; ?>
    <?php endif; ?>
</section>
