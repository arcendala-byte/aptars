<?php
require_once __DIR__ . '/../config/database.php';

// Safaricom POSTs JSON data here
$callbackJSONData = file_get_contents('php://input');
$data = json_decode($callbackJSONData, true);

if (!$data || !isset($data['Body']['stkCallback'])) {
    http_response_code(400);
    exit;
}

$callback = $data['Body']['stkCallback'];
$resultCode = $callback['ResultCode'];
$checkoutRequestID = $callback['CheckoutRequestID'];
$resultDesc = $callback['ResultDesc'];

$pdo = db();

if ($resultCode == 0) {
    // SUCCESS
    $receiptNumber = null;
    foreach ($callback['CallbackMetadata']['Item'] as $item) {
        if ($item['Name'] === 'MpesaReceiptNumber') {
            $receiptNumber = $item['Value'];
            break;
        }
    }
    
    $stmt = $pdo->prepare("UPDATE payments SET status='success', mpesa_receipt_number=?, result_desc=? WHERE checkout_request_id=?");
    $stmt->execute([$receiptNumber, $resultDesc, $checkoutRequestID]);
    
    // Notify student
    $stmt = $pdo->prepare("SELECT id, student_id, amount FROM payments WHERE checkout_request_id=?");
    $stmt->execute([$checkoutRequestID]);
    if ($payment = $stmt->fetch()) {
        $stmt = $pdo->prepare('SELECT user_id FROM students WHERE id=?');
        $stmt->execute([$payment['student_id']]);
        $userId = (int)$stmt->fetchColumn();
        
        if ($userId) {
            require_once __DIR__ . '/../includes/notifications.php';
            createNotification($userId, 'Payment Received', "KES " . number_format((float)$payment['amount'], 2) . " received. Receipt: $receiptNumber", 'success');
        }
    }
} else {
    // FAILED / CANCELLED
    $stmt = $pdo->prepare("UPDATE payments SET status='failed', result_desc=? WHERE checkout_request_id=?");
    $stmt->execute([$resultDesc, $checkoutRequestID]);
}

// Always respond to Safaricom with a 200 OK
echo json_encode(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);