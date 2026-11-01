<?php
require_once __DIR__ . '/../../includes/auth.php';
requireRole('admin');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect(baseUrl('admin/students/index.php'));
csrfVerify();
$id = (int)post('id', 0);
if ($id <= 0) { setFlash('danger', 'Invalid student.'); redirect(baseUrl('admin/students/index.php')); }
$pdo = db();
$pdo->beginTransaction();
try {
    $stmt = $pdo->prepare('SELECT user_id FROM students WHERE id=?');
    $stmt->execute([$id]);
    $userId = (int)($stmt->fetchColumn() ?: 0);
    $pdo->prepare("UPDATE students SET status='inactive' WHERE id=?")->execute([$id]);
    if ($userId) $pdo->prepare("UPDATE users SET status='inactive' WHERE id=?")->execute([$userId]);
    $pdo->commit();
    logActivity((int)$_SESSION['user_id'], 'student.archive', "Archived student #$id");
    setFlash('success', 'Student archived and user account deactivated.');
} catch (Throwable $t) {
    $pdo->rollBack();
    setFlash('danger', 'Could not archive student.');
}
redirect(baseUrl('admin/students/index.php'));