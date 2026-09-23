<?php if (($pager['pages'] ?? 1) > 1): ?>
    <nav class="pager" aria-label="Σελιδοποίηση">
        <?php for ($i = 1; $i <= $pager['pages']; $i++): ?>
            <?php if ($i === 1 || $i === $pager['pages'] || abs($i - $pager['page']) <= 2): ?>
                <a class="<?= $i === $pager['page'] ? 'is-current' : '' ?>" href="<?= e(url(request_path()) . qs(['page' => $i])) ?>"><?= $i ?></a>
            <?php elseif ($i === 2 || $i === $pager['pages'] - 1): ?>
                <span>…</span>
            <?php endif; ?>
        <?php endfor; ?>
    </nav>
<?php endif; ?>
