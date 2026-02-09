<?php
/**
 * Wishes API
 * GET  - Fetch all wishes
 * POST - Add new wish
 */

// Use UTC timezone - JavaScript will convert to user's local timezone
date_default_timezone_set('UTC');

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$dataFile = __DIR__ . '/../data/wishes.json';

// Ensure data directory exists
if (!file_exists(dirname($dataFile))) {
    mkdir(dirname($dataFile), 0755, true);
}

// Initialize file if not exists
if (!file_exists($dataFile)) {
    file_put_contents($dataFile, '[]');
}

// Read wishes
function getWishes()
{
    global $dataFile;
    $content = file_get_contents($dataFile);
    return json_decode($content, true) ?: [];
}

// Save wishes
function saveWishes($wishes)
{
    global $dataFile;
    file_put_contents($dataFile, json_encode($wishes, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

// GET - Fetch all wishes
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $wishes = getWishes();
    // Sort by newest first
    usort($wishes, function ($a, $b) {
        return strtotime($b['created_at']) - strtotime($a['created_at']);
    });
    echo json_encode(['success' => true, 'wishes' => $wishes]);
    exit;
}

// POST - Add new wish
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);

    $name = trim($input['name'] ?? '');
    $message = trim($input['message'] ?? '');

    if (empty($name) || empty($message)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Nama dan pesan harus diisi']);
        exit;
    }

    $wishes = getWishes();

    // Generate new ID
    $maxId = 0;
    foreach ($wishes as $w) {
        if ($w['id'] > $maxId)
            $maxId = $w['id'];
    }

    $newWish = [
        'id' => $maxId + 1,
        'name' => htmlspecialchars($name),
        'message' => htmlspecialchars($message),
        'created_at' => date('c') // ISO 8601 format with timezone
    ];

    $wishes[] = $newWish;
    saveWishes($wishes);

    echo json_encode(['success' => true, 'wish' => $newWish]);
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Method not allowed']);
