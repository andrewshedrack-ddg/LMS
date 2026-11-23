<?php
// ============================================================
// M-Pesa STK push callback (payment verification).
//
// Safaricom calls this address after the customer answers the
// prompt on their phone. This is the step that VERIFIES the
// payment: a payment is only marked Paid when Safaricom
// confirms it here with ResultCode 0. Until then it stays
// Pending, so an unpaid order can never be marked Paid by
// mistake. Do NOT open this page in a browser.
// ============================================================
require_once __DIR__ . '/config.php';

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
$callback = $data['Body']['stkCallback'] ?? null;

if (!$callback || empty($callback['CheckoutRequestID'])) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['ResultCode' => 1, 'ResultDesc' => 'Bad callback']);
    exit;
}

$checkoutId = $callback['CheckoutRequestID'];
$resultCode = (int) ($callback['ResultCode'] ?? 1);

// The M-Pesa receipt number, if Safaricom sent one.
$receipt = null;
foreach (($callback['CallbackMetadata']['Item'] ?? []) as $item) {
    if (($item['Name'] ?? '') === 'MpesaReceiptNumber') {
        $receipt = $item['Value'];
    }
}

if ($resultCode === 0) {
    // Payment confirmed by Safaricom. Mark this payment Paid
    // and save the receipt number for the records.
    $stmt = $pdo->prepare(
        'UPDATE Payment SET Payment_status = ?, Mpesa_receipt = ?
         WHERE Mpesa_checkout_id = ?'
    );
    $stmt->execute(['Paid', $receipt, $checkoutId]);
} else {
    // The customer cancelled, entered a wrong PIN, or had no money.
    // Mark it Failed so staff can see it did not go through.
    $stmt = $pdo->prepare(
        'UPDATE Payment SET Payment_status = ?
         WHERE Mpesa_checkout_id = ?'
    );
    $stmt->execute(['Failed', $checkoutId]);
}

// Always answer Safaricom so it knows we received the callback.
header('Content-Type: application/json');
echo json_encode(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
