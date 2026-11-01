<?php
require_once __DIR__ . '/../../includes/auth.php';
requireRole('admin');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect(baseUrl('admin/lecturers/index.php'));
csrfVerify();
$id = (int)post('id', 0);
if ($id <= 0) { setFlash('danger', 'Invalid lecturer.'); redirect(baseUrl('admin/lecturers/index.php')); }
$pdo = db();
$pdo->beginTransaction();
try {
    $stmt = $pdo->prepare('SELECT user_id FROM lecturers WHERE id=?');
    $stmt->execute([$id]);
    $userId = (int)($stmt->fetchColumn() ?: 0);
    $pdo->prepare("UPDATE lecturers SET status='inactive' WHERE id=?")->execute([$id]);
    if ($userId) $pdo->prepare("UPDATE users SET status='inactive' WHERE id=?")->execute([$userId]);
    $pdo->commit();
    logActivity((int)$_SESSION['user_id'], 'lecturer.archive', "Archived lecturer #$id");
    setFlash('success', 'Lecturer archived and user account deactivated.');
} catch (Throwable $t) {
    $pdo->rollBack();
    setFlash('danger', 'Could not archive lecturer.');
}
redirect(baseUrl('admin/lecturers/index.php'));