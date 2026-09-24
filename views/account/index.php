<?php page_head('Λογαριασμός', full_name($user), (string) $user['email'] . (($user['department'] ?? '') !== '' ? ' · ' . $user['department'] : '')); ?>
<?php errors_box($errors); ?>
<?php if ((int) ($user['password_insecure'] ?? 0) === 1): ?>
    <div class="alert alert-warning" role="alert"><?= e(password_insecure_notice()) ?></div>
<?php endif; ?>
<form class="paper-card stack-form narrow" method="post" action="<?= e(url('/account')) ?>">
    <?= csrf_field() ?>
    <h2>Αλλαγή κωδικού</h2>
    <label class="form-label" for="current_password">Τρέχων κωδικός</label>
    <input class="form-control" id="current_password" name="current_password" type="password" autocomplete="current-password" required>
    <label class="form-label" for="new_password">Νέος κωδικός</label>
    <input class="form-control" id="new_password" name="new_password" type="password" autocomplete="new-password" required>
    <p class="field-hint"><?= e(password_policy_hint()) ?></p>
    <label class="form-label" for="new_password_confirm">Επιβεβαίωση</label>
    <input class="form-control" id="new_password_confirm" name="new_password_confirm" type="password" autocomplete="new-password" required>
    <button class="btn btn-seal" type="submit">Ενημέρωση κωδικού</button>
</form>
