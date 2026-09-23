<?php

declare(strict_types=1);

final class ApprovalController
{
    public static function index(): void
    {
        Auth::requireApprover();
        $result = Documents::search(['status' => 'pending'], max(1, query_int('page')), 20);
        render('documents/approvals', [
            'title' => 'Προς επιβεβαίωση',
            'rows' => $result['rows'],
            'pager' => $result['pager'],
        ]);
    }

    public static function approve(int $id): void
    {
        $user = Auth::requireApprover();
        $doc = Documents::findVisible($id);
        if (!$doc) {
            not_found();
        }
        try {
            Documents::approve($doc, $user);
        } catch (Throwable $e) {
            log_exception($e);
            flash('danger', $e instanceof RuntimeException ? $e->getMessage() : 'Η επιβεβαίωση δεν ολοκληρώθηκε.');
            redirect('/documents/' . $id);
        }
        flash('success', 'Το έγγραφο επιβεβαιώθηκε και σφραγίστηκε με QR.');
        redirect('/documents/' . $id);
    }

    public static function magic(string $token): void
    {
        Approvals::open($token);
    }
}
