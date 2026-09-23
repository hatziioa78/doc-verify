<?php page_head('Διαχείριση', 'Χρήστες', 'Ο διαχειριστής ορίζει επώνυμο, όνομα, τμήμα, email και κωδικό. Οι ανενεργοί λογαριασμοί δεν συνδέονται.'); ?>
<div class="card-head bare">
    <p class="mb-0"><?= count($users) ?> λογαριασμοί</p>
    <a class="btn btn-seal" href="<?= e(url('/users/new')) ?>">Νέος χρήστης</a>
</div>
<section class="paper-card">
    <div class="table-wrap">
        <table class="doc-table">
            <thead>
                <tr>
                    <th>Ονοματεπώνυμο</th>
                    <th>Τμήμα</th>
                    <th>Email</th>
                    <th>Ρόλος</th>
                    <th>Έγγραφα</th>
                    <th>Κατάσταση</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $person): ?>
                    <tr>
                        <td><?= e(full_name($person)) ?></td>
                        <td><?= e((string) $person['department']) ?></td>
                        <td><?= e((string) $person['email']) ?></td>
                        <td><?= ($person['role'] ?? '') === 'manager' ? 'Διαχειριστής' : 'Χρήστης' ?></td>
                        <td><?= (int) $person['documents_count'] ?></td>
                        <td><?= (int) $person['active'] === 1 ? 'Ενεργός' : 'Ανενεργός' ?></td>
                        <td class="row-actions"><a href="<?= e(url('/users/' . $person['id'] . '/edit')) ?>">Επεξεργασία</a></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
