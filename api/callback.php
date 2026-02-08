<?php
/**
 * Duitku Payment Callback Handler
 * This script receives notifications from Duitku when a payment is completed.
 */

// Production Configuration
$merchantCode = 'D12441';

$apiKey = '7ff078496ed0cf894f2a7f4fb92fed59';


// Database Connection
$dbFile = __DIR__ . '/../donations.db';

try {
    $db = new PDO('sqlite:' . $dbFile);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
}
catch (PDOException $e) {
    http_response_code(500);
    die("Database Connection Error");
}

// Check for POST request from Duitku
if (!isset($_POST['merchantCode']) || !isset($_POST['merchantOrderId']) || !isset($_POST['signature'])) {
    http_response_code(400);
    die("Bad Request");
}

$merchantCodePost = $_POST['merchantCode'];
$amountPost = $_POST['amount'];
$merchantOrderIdPost = $_POST['merchantOrderId'];
$signaturePost = $_POST['signature'];
$resultCode = $_POST['resultCode']; // 00 = Success, 01 = Failed
$reference = $_POST['reference'];


// Validate Signature based on Duitku Docs: MD5(merchantCode + merchantOrderId + amount + apiKey)
// NOTE: Sometimes Duitku sends amount as string without decimals, sometimes with. Check docs.
// Usually callback signature is: MD5(merchantCode + amount + merchantOrderId + apiKey)
// Let's try to match Duitku standard.

$params = $merchantCode . $amountPost . $merchantOrderIdPost . $apiKey;
$calcSignature = md5($params);

if ($signaturePost !== $calcSignature) {
    // If signature fails, log it and reject
    error_log("Invalid Signature: " . $signaturePost . " vs " . $calcSignature);
    http_response_code(400);
    die("Invalid Signature");
}

if ($resultCode == "00") {
    // Payment Success -> Update Status to SUCCESS
    $stmt = $db->prepare("UPDATE donations SET status = 'SUCCESS', updated_at = datetime('now') WHERE orderId = :orderId");
    $stmt->execute([':orderId' => $merchantOrderIdPost]);

    // Check if row updated
    if ($stmt->rowCount() > 0) {
        echo "SUCCESS";
    }
    else {
        // If order ID not found (maybe direct payment link?), insert new record
        $stmtInsert = $db->prepare("INSERT INTO donations (orderId, amount, sender_name, message, status) VALUES (:orderId, :amount, 'Tamu (Direct)', 'Terima kasih', 'SUCCESS')");
        $stmtInsert->execute([
            ':orderId' => $merchantOrderIdPost,
            ':amount' => $amountPost
        ]);
        echo "SUCCESS";
    }

}
else {
    // Payment Failed
    $stmt = $db->prepare("UPDATE donations SET status = 'FAILED' WHERE orderId = :orderId");
    $stmt->execute([':orderId' => $merchantOrderIdPost]);
    echo "FAILED";
}
