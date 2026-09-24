<?php if (empty($rows) && empty($recent)): ?>
    <p class="empty-copy">Δεν υπάρχουν έγγραφα που να ταιριάζουν.</p>
<?php else: ?>
    <?php $list = $rows ?? $recent ?? []; ?>
    <?php if ($list === []): ?>
        <p class="empty-copy">Δεν υπάρχουν έγγραφα που να ταιριάζουν.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="doc-table">
                <thead>
                    <tr>
                        <th>Πρωτόκολλο</th>
                        <th>Θέμα</th>
                        <th>Εκδούσα αρχή</th>
                        <th>Καταχώρηση</th>
                        <th>Κατάσταση</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($list as $doc): ?>
                        <?php $state = document_state($doc); ?>
                        <tr>
                            <td class="proto"><?= e((string) $doc['protocol_number']) ?></td>
                            <td>
                                <a class="doc-link" href="<?= e(url('/documents/' . $doc['id'])) ?>"><?= e((string) $doc['subject']) ?></a>
                                <small><?= e(full_name($doc)) ?></small>
                            </td>
                            <td><?= e((string) $doc['issuing_authority']) ?></td>
                            <td><?= e(fmt_dt((string) $doc['registered_at'])) ?></td>
                            <td>
                                <span class="status-pill <?= e(state_class($state)) ?>"><?= e(state_label($state)) ?></span>
                                <?php if ($state === 'cancelled' && !empty($doc['cancellation_reason'])): ?>
                                    <small class="reason-note"><?= e((string) $doc['cancellation_reason']) ?></small>
                                <?php endif; ?>
                            </td>
                            <td class="row-actions">
                                <?php if (($doc['status'] ?? '') !== 'pending'): ?>
                                    <a href="<?= e(url('/documents/' . $doc['id'] . '/download')) ?>" aria-label="Λήψη"><?= icon('download') ?></a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
<?php endif; ?>
