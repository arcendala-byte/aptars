<?php
declare(strict_types=1);

// ============================================================
// DARAJA SANDBOX CREDENTIALS
// ============================================================
define('MPESA_ENV', 'sandbox');
define('MPESA_CONSUMER_KEY', 'sju3u1zG3XmaYoMmCy4yGLy4dOWwS00jrPOJGfPEYYk31KBA'); 
define('MPESA_CONSUMER_SECRET', 'f3J4WyHpl8ksppgdCGHQnxwMXQ06YHOfq12KzYw5Iwf8xetTYnl8It4KgKnVexbR'); 
define('MPESA_SHORTCODE', '174379'); 
define('MPESA_PASSKEY', 'bfb279f9aa9bdbcf158e97dd71a467cd2e0c893059b10f78e6b72ada1ed2c919'); 

// Use your ngrok URL
define('MPESA_CALLBACK_URL', 'https://extrovertish-crinkly-kina.ngrok-free.dev/aptars/student/daraja_callback.php');

function getMpesaToken() {
    $url = 'https://sandbox.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials';
    $credentials = base64_encode(MPESA_CONSUMER_KEY . ':' . MPESA_CONSUMER_SECRET);
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Basic ' . $credentials]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $response = curl_exec($ch);
    curl_close($ch);
    
    $result = json_decode($response, true);
    return $result['access_token'] ?? null;
}

function initiateStkPush(int $studentId, int $semesterId, float $amount, string $phone, string $purpose): array {
    $pdo = db();

    // Format phone to 2547XXXXXXXX
    $phone = preg_replace('/\s+/', '', $phone);
    if (preg_match('/^0(7\d{8})$/', $phone, $m))  $phone = '254' . $m[1];
    if (preg_match('/^(\d{9})$/', $phone, $m))    $phone = '254' . $m[1];
    if (!preg_match('/^2547\d{8}$/', $phone)) {
        return ['ok'=>false, 'error'=>'Phone must be a valid Safaricom number (e.g. 07XX XXX XXX).'];
    }
    if ($amount < 1) return ['ok'=>false, 'error'=>'Amount must be at least 1 KES.'];

    $token = getMpesaToken();
    if (!$token) {
        return ['ok'=>false, 'error'=>'Failed to authenticate with Safaricom. Check your Consumer Key/Secret.'];
    }

    $timestamp = date('YmdHis');
    $password = base64_encode(MPESA_SHORTCODE . MPESA_PASSKEY . $timestamp);

    $payload = [
        'BusinessShortCode' => MPESA_SHORTCODE,
        'Password'          => $password,
        'Timestamp'         => $timestamp,
        'TransactionType'   => 'CustomerPayBillOnline',
        'Amount'            => round($amount),
        // Safaricom Sandbox requires PartyA to be the test number (254708374149) 
        // for the request to succeed.
        'PartyA'            => '254708374149', 
        'PartyB'            => MPESA_SHORTCODE,
        // PhoneNumber is where the actual STK Push will go.
        'PhoneNumber'       => $phone, 
        'CallBackURL'       => MPESA_CALLBACK_URL,
        'AccountReference'  => 'APTARS Fees',
        'TransactionDesc'   => $purpose
    ];

    $ch = curl_init('https://sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest');
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $response = curl_exec($ch);
    curl_close($ch);

    $result = json_decode($response, true);

    if (isset($result['CheckoutRequestID'])) {
        // Success! Save to database
        $pdo->prepare("
            INSERT INTO payments (student_id, semester_id, amount, purpose, phone, checkout_request_id, merchant_request_id, status)
            VALUES (?,?,?,?,?,?,?,'pending')
        ")->execute([
            $studentId, $semesterId, $amount, $purpose, $phone, 
            $result['CheckoutRequestID'], $result['MerchantRequestID']
        ]);

        return [
            'ok' => true,
            'payment_id' => (int)$pdo->lastInsertId(),
            'checkout_request_id' => $result['CheckoutRequestID'],
            'message' => 'STK Push sent! Please check your phone and enter your M-Pesa PIN.',
            'simulated' => false
        ];
    }

    // Catch Safaricom errors
    $errorMsg = $result['errorMessage'] ?? $result['ResponseDescription'] ?? 'Unknown error from Daraja.';
    return ['ok'=>false, 'error'=>'Daraja Error: ' . $errorMsg];
}

function paymentStatus(int $paymentId): ?array {
    $stmt = db()->prepare('SELECT * FROM payments WHERE id=?');
    $stmt->execute([$paymentId]);
    return $stmt->fetch() ?: null;
}