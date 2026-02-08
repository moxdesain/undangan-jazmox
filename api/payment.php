<?php
/**
 * Duitku Payment API Endpoint
 * This file handles payment requests from the frontend
 * 
 * IMPORTANT: Replace the credentials below with your actual Duitku credentials
 */

// Enable CORS for local development
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

// Handle preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// =============================================
// DUITKU CONFIGURATION - UPDATE THESE VALUES
// =============================================
$merchantCode = 'D12441'; // Production Merchant Code
$apiKey = '7ff078496ed0cf894f2a7f4fb92fed59'; // Production API Key

// Environment URLs
$sandbox = false; // Set to false for Production (Live Mode)
$baseUrl = $sandbox
    ? 'https://sandbox.duitku.com/webapi/api/merchant/v2/inquiry'
    : 'https://passport.duitku.com/webapi/api/merchant/v2/inquiry';

// =============================================
// HANDLE POST REQUEST
// =============================================
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['amount'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Amount is required']);
    exit();
}

$amount = intval($input['amount']);

// Validate amount (minimum Rp 10,000 for most payment methods)
if ($amount < 10000) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Minimum amount is Rp 10.000']);
    exit();
}

// Generate unique order ID
$merchantOrderId = 'GIFT-' . time() . '-' . rand(1000, 9999);

// Database Initialization (Create if not exists)
$dbFile = __DIR__ . '/../donations.db';
$db = new PDO('sqlite:' . $dbFile);
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Create table if not exists (Only during first run)
$db->exec("CREATE TABLE IF NOT EXISTS donations (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    orderId TEXT UNIQUE,
    amount INTEGER,
    sender_name TEXT,
    message TEXT,
    status TEXT DEFAULT 'PENDING',
    payment_url TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)");

// Insert Pending Transaction
$stmt = $db->prepare("INSERT INTO donations (orderId, amount, sender_name, message, status) VALUES (:orderId, :amount, :name, :message, 'PENDING')");
$stmt->execute([
    ':orderId' => $merchantOrderId,
    ':amount' => $amount,
    ':name' => $input['name'] ?? 'Hamba Allah',
    ':message' => $input['message'] ?? 'Selamat Menempuh Hidup Baru!'
]);

// Payment details
$productDetails = 'Tanda Kasih Pernikahan Bima & Jasmine';
$email = 'guest@wedding.com'; // Default email for guests
$customerVaName = $input['name'] ?? 'Tamu Undangan';
$callbackUrl = 'https://sarangdigital.id/duitku/callback'; // Your callback URL
$returnUrl = 'https://jazmox.com/'; // Redirect after payment
$expiryPeriod = 1440; // 24 hours in minutes

// Generate signature
$signature = md5($merchantCode . $merchantOrderId . $amount . $apiKey);

// Prepare request payload
$payload = [
    'merchantCode' => $merchantCode,
    'paymentAmount' => $amount,
    'merchantOrderId' => $merchantOrderId,
    'productDetails' => $productDetails,
    'email' => $email,
    'customerVaName' => $customerVaName,
    'callbackUrl' => $callbackUrl,
    'returnUrl' => $returnUrl,
    'signature' => $signature,
    'expiryPeriod' => $expiryPeriod
];

// Make API request to Duitku
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $baseUrl,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($payload),
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json'
    ],
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_TIMEOUT => 30
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

// Handle curl errors
if ($curlError) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Connection error: ' . $curlError
    ]);
    exit();
}

// Parse response
$result = json_decode($response, true);

if ($httpCode === 200 && isset($result['paymentUrl'])) {
    // Success - return payment URL and reference
    echo json_encode([
        'success' => true,
        'paymentUrl' => $result['paymentUrl'],
        'reference' => $result['reference'] ?? $merchantOrderId,
        'merchantOrderId' => $merchantOrderId,
        'amount' => $amount
    ]);
}
else {
    // Error from Duitku
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $result['Message'] ?? $result['statusMessage'] ?? 'Payment request failed',
        'code' => $result['statusCode'] ?? 'UNKNOWN'
    ]);
}
