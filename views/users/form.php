<?php page_head($editing ? 'Επεξεργασία' : 'Νέος λογαριασμός', $editing ? full_name($editing) : 'Στοιχεία χρήστη'); ?>
<?php errors_box($errors); ?>
<form class="paper-card stack-form" method="post" action="<?= e(url($editing ? '/users/' . $editing['id'] : '/users')) ?>">
    <?= csrf_field() ?>
    <div class="row g-3">
        <div class="col-md-6">
            <label class="form-label" for="last_name">Επώνυμο</label>
            <input class="form-control" id="last_name" name="last_name" required maxlength="100" value="<?= e($old['last_name']) ?>">
        </div>
        <div class="col-md-6">
            <label class="form-label" for="first_name">Όνομα</label>
            <input class="form-control" id="first_name" name="first_name" required maxlength="100" value="<?= e($old['first_name']) ?>">
        </div>
        <div class="col-md-6">
            <label class="form-label" for="department">Τμήμα</label>
            <input class="form-control" id="department" name="department" maxlength="150" value="<?= e($old['department']) ?>">
        </div>
        <div class="col-md-6">
            <label class="form-label" for="email">Email</label>
            <input class="form-control" id="email" name="email" type="email" required maxlength="190" value="<?= e($old['email']) ?>">
        </div>
        <div class="col-md-6">
            <label class="form-label" for="password">Κωδικός<?= $editing ? ' (κενό = χωρίς αλλαγή)' : '' ?></label>
            <input class="form-control" id="password" name="password" type="password" autocomplete="new-password" <?= $editing ? '' : 'required' ?>>
        </div>
        <div class="col-md-6">
            <label class="form-label" for="role">Ρόλος</label>
            <select class="form-select" id="role" name="role">
                <option value="user" <?= $old['role'] === 'user' ? 'selected' : '' ?>>Χρήστης</option>
                <option value="secretary" <?= $old['role'] === 'secretary' ? 'selected' : '' ?>>Γραμματεία</option>
                <option value="manager" <?= $old['role'] === 'manager' ? 'selected' : '' ?>>Διαχειριστής</option>
            </select>
        </div>
        <div class="col-12">
            <label class="check-line">
                <input type="checkbox" name="active" value="1" <?= (int) $old['active'] === 1 ? 'checked' : '' ?>>
                <span>Ενεργός λογαριασμός</span>
            </label>
        </div>
        <div class="col-12" data-user-only>
            <label class="check-line">
                <input type="checkbox" name="certify_without_approval" value="1" <?= (int) ($old['certify_without_approval'] ?? 0) === 1 ? 'checked' : '' ?>>
                <span>Επικύρωση εγγράφων χωρίς επιβεβαίωση από τη Γραμματεία</span>
            </label>
            <p class="field-hint">Ισχύει μόνο για τον ρόλο Χρήστης και είναι εξ ορισμού ανενεργή. Ο διαχειριστής και η Γραμματεία επικυρώνουν πάντα αμέσως.</p>
        </div>
    </div>
    <button class="btn btn-seal" type="submit">Αποθήκευση</button>
</form>
<?php if ($editing): ?>
    <form class="paper-card danger-card" method="post" action="<?= e(url('/users/' . $editing['id'] . '/delete')) ?>">
        <?= csrf_field() ?>
        <h2>Διαγραφή λογαριασμού</h2>
        <p>Επιτρέπεται μόνο αν ο χρήστης δεν έχει κανένα έγγραφο. Αλλιώς απενεργοποιήστε τον.</p>
        <button class="btn btn-wax" type="submit">Διαγραφή χρήστη</button>
    </form>
<?php endif; ?>
