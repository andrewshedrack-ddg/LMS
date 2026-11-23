<?php
// ============================================================
// M-Pesa Daraja helper: access token, STK push, phone numbers.
//
// Used by payment.php. The callback that VERIFIES the payment
// is handled separately by mpesa_callback.php.
// ============================================================
require_once __DIR__ . '/mpesa_config.php';

// Change 07xx, 7xx or +2547xx into 2547xx, which Daraja wants.
function mpesa_normalize_phone($phone) {
    $digits = preg_replace('/\D+/', '', (string) $phone);
    if ($digits === '') {
        return '';
    }
    if (strpos($digits, '0') === 0) {
        $digits = '254' . substr($digits, 1);
    } elseif (strpos($digits, '254') !== 0) {
        $digits = '254' . $digits;
    }
    return $digits;
}

// Get a Daraja access token. Returns the token, or null on failure.
function mpesa_access_token() {
    $url = mpesa_base_url() . '/oauth/v1/generate?grant_type=client_credentials';
    $credentials = base64_encode(MPESA_CONSUMER_KEY . ':' . MPESA_CONSUMER_SECRET);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER => ['Authorization: Basic ' . $credentials],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200 || $response === false) {
        return null;
    }
    $data = json_decode($response, true);
    return $data['access_token'] ?? null;
}

// Send an STK push to the customer's phone.
// Returns ['ok' => true, 'checkout_id' => ..., 'message' => ...]
// or ['ok' => false, 'error' => ...].
function mpesa_stk_push($phone, $amount, $orderId) {
    $token = mpesa_access_token();
    if (!$token) {
        return ['ok' => false, 'error' => 'Could not get an M-Pesa access token. Check the Daraja credentials in mpesa_config.php.'];
    }

    $timestamp = date('YmdHis');
    $password = base64_encode(MPESA_SHORTCODE . MPESA_PASSKEY . $timestamp);

    $body = [
        'BusinessShortCode' => MPESA_SHORTCODE,
        'Password' => $password,
        'Timestamp' => $timestamp,
        'TransactionType' => MPESA_TRANSACTION_TYPE,
        'Amount' => (int) round($amount),
        'PartyA' => $phone,
        'PartyB' => MPESA_SHORTCODE,
        'PhoneNumber' => $phone,
        'CallBackURL' => MPESA_CALLBACK_URL,
        'AccountReference' => 'ORDER-' . $orderId,
        'TransactionDesc' => 'Laundry order #' . $orderId . ' payment',
    ];

    $ch = curl_init(mpesa_base_url() . '/mpesa/stkpush/v1/processrequest');
    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token, 'Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($body),
        CURLOPT_TIMEOUT => 30,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200 || $response === false) {
        return ['ok' => false, 'error' => 'The M-Pesa request failed. Please try again.'];
    }
    $data = json_decode($response, true);
    if (($data['ResponseCode'] ?? '') !== '0') {
        return ['ok' => false, 'error' => $data['errorMessage'] ?? ($data['ResponseDescription'] ?? 'The M-Pesa request was rejected.')];
    }
    return [
        'ok' => true,
        'checkout_id' => $data['CheckoutRequestID'] ?? '',
        'message' => $data['CustomerMessage'] ?? 'Check your phone and enter your M-Pesa PIN.',
    ];
}
