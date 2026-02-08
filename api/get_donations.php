<?php
/**
 * API Endpoint: Get Recent Success Donations
 */

header('Content-Type: application/json');

$dbFile = __DIR__ . '/../donations.db';

if (!file_exists($dbFile)) {
    echo json_encode(['donations' => []]);
    exit();
}

try {
    $db = new PDO('sqlite:' . $dbFile);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt = $db->query("SELECT sender_name, amount, message, created_at FROM donations WHERE status = 'SUCCESS' ORDER BY created_at DESC LIMIT 5");
    $donations = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['donations' => $donations]);

}
catch (PDOException $e) {
    echo json_encode(['donations' => []]);
}
