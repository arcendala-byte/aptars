<?php
require_once __DIR__ . '/../../includes/auth.php';
requireRole('admin');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect(baseUrl('admin/settings/index.php'));
csrfVerify();

$posted = (array)($_POST['settings'] ?? []);
$pdo = db();
$pdo->beginTransaction();
try {
    $stmt = $pdo->prepare('UPDATE settings SET setting_value=? WHERE setting_key=?');
    foreach ($posted as $key => $val) {
        $stmt->execute([trim((string)$val), (string)$key]);
    }
    $pdo->commit();
    logActivity((int)$_SESSION['user_id'], 'settings.save', 'Updated system settings');
    setFlash('success', 'Settings saved.');
} catch (Throwable $t) {
    $pdo->rollBack();
    setFlash('danger', 'Could not save settings.');
}
redirect(baseUrl('admin/settings/index.php'));