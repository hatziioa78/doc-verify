<?php
$headings = [
    'active' => ['Γνήσιο έγγραφο', 'Είναι καταχωρημένο στο μητρώο.'],
    'cancelled' => ['Ακυρωμένο έγγραφο', 'Το έγγραφο έχει ακυρωθεί και δεν θεωρείται πλέον έγκυρο.'],
    'unknown' => ['Δεν αναγνωρίζεται', 'Δεν βρέθηκε έγγραφο για αυτόν τον σύνδεσμο. Μπορεί να είναι εσφαλμένος ή το έγγραφο να έχει αφαιρεθεί από το μητρώο.'],
    'limited' => ['Πάρα πολλές προσπάθειες', 'Ο έλεγχος σταμάτησε προσωρινά από αυτή τη διεύθυνση. Δοκιμάστε ξανά σε λίγο.'],
];
[$heading, $lead] = $headings[$state] ?? $headings['unknown'];
?>
<article class="certificate state-<?= e($state) ?>">
    <div class="wax wax-<?= e($state) ?>" aria-hidden="true">
        <span><?= $state === 'active' ? 'ΓΝΗΣΙΟ' : ($state === 'cancelled' ? 'ΑΚΥΡΟ' : '—') ?></span>
    </div>
    <p class="kicker">Αποτέλεσμα ελέγχου</p>
    <h1><?= e($heading) ?></h1>
    <p class="lede"><?= e($lead) ?></p>
    <?php if ($doc): ?>
        <h2><?= e((string) $doc['subject']) ?></h2>
        <dl class="cert-grid">
            <div><dt>Αριθμός πρωτοκόλλου</dt><dd><?= e((string) $doc['protocol_number']) ?></dd></div>
            <div><dt>Εκδούσα αρχή / τμήμα</dt><dd><?= e((string) $doc['issuing_authority']) ?></dd></div>
            <div><dt>Πληροφορίες</dt><dd class="pre"><?= e((string) $doc['info']) ?></dd></div>
            <div><dt>Ημερομηνία καταχώρησης</dt><dd><?= e(fmt_dt((string) $doc['registered_at'])) ?></dd></div>
            <div><dt>Καταχωρίστηκε από</dt><dd><?= e(trim((string) $doc['last_name'] . ' ' . (string) $doc['first_name'])) ?></dd></div>
        </dl>
        <?php if ($state === 'cancelled'): ?>
            <div class="reason-block">
                <strong>Αιτία ακύρωσης</strong>
                <p><?= e((string) ($doc['cancellation_reason'] ?: 'Δεν δηλώθηκε αιτία.')) ?></p>
            </div>
        <?php endif; ?>
        <p class="hash-line"><span>SHA-256 αρχικού PDF</span><code><?= e((string) $doc['sha256']) ?></code></p>
        <a class="btn btn-seal btn-lg" href="<?= e(url('/v/' . $doc['token'] . '/download')) ?>"><?= icon('download') ?> Λήψη εγγράφου</a>
    <?php endif; ?>
</article>
