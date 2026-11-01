<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/mpesa.php';
requireRole('student');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['ok'=>false,'error'=>'POST required.']); exit; }
csrfVerify();

$paymentId = (int)post('payment_id', 0);
$success   = post('success', '0') === '1';
if ($paymentId <= 0) { echo json_encode(['ok'=>false,'error'=>'Invalid payment.']); exit; }

$stud = currentProfile();
$stmt = db()->prepare('SELECT student_id FROM payments WHERE id=?');
$stmt->execute([$paymentId]);
$owner = (int)$stmt->fetchColumn();
if (!$stud || $owner !== (int)$stud['id']) {
    echo json_encode(['ok'=>false,'error'=>'Not authorized.']); exit;
}

simulateCallback($paymentId, $success);
$p = paymentStatus($paymentId);
echo json_encode(['ok'=>true, 'status'=>$p['status']]);