<?php
/**
 * Admin Wishes API
 * DELETE - Remove a wish by ID
 * Requires password authentication
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Admin-Password');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// ========================================
// ADMIN PASSWORD - GANTI SESUAI KEINGINAN
// ========================================
define('ADMIN_PASSWORD', 'bimajas2026');

$dataFile = __DIR__ . '/../data/wishes.json';

// Verify password
function verifyPassword()
{
    $password = $_SERVER['HTTP_X_ADMIN_PASSWORD'] ?? '';
    return $password === ADMIN_PASSWORD;
}

// Read wishes
function getWishes()
{
    global $dataFile;
    if (!file_exists($dataFile))
        return [];
    $content = file_get_contents($dataFile);
    return json_decode($content, true) ?: [];
}

// Save wishes
function saveWishes($wishes)
{
    global $dataFile;
    file_put_contents($dataFile, json_encode(array_values($wishes), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

// Check authentication
if (!verifyPassword()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Password salah']);
    exit;
}

// GET - Fetch all wishes (admin view with more details)
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $wishes = getWishes();
    usort($wishes, function ($a, $b) {
        return strtotime($b['created_at']) - strtotime($a['created_at']);
    });
    echo json_encode(['success' => true, 'wishes' => $wishes, 'total' => count($wishes)]);
    exit;
}

// DELETE - Remove a wish (returns deleted wish for undo)
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $input = json_decode(file_get_contents('php://input'), true);
    $id = intval($input['id'] ?? 0);

    if ($id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID tidak valid']);
        exit;
    }

    $wishes = getWishes();
    $deletedWish = null;

    foreach ($wishes as $key => $wish) {
        if (intval($wish['id']) === $id) {
            $deletedWish = $wish; // Store for undo
            unset($wishes[$key]);
            break;
        }
    }

    if (!$deletedWish) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Pesan tidak ditemukan']);
        exit;
    }

    saveWishes($wishes);
    echo json_encode(['success' => true, 'message' => 'Pesan berhasil dihapus', 'deleted' => $deletedWish]);
    exit;
}

// POST - Restore a deleted wish (undo)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!isset($input['restore']) || !$input['restore']) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid request']);
        exit;
    }

    $wishToRestore = $input['wish'] ?? null;

    if (!$wishToRestore || !isset($wishToRestore['id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Data pesan tidak valid']);
        exit;
    }

    $wishes = getWishes();
    $wishes[] = $wishToRestore;
    saveWishes($wishes);

    echo json_encode(['success' => true, 'message' => 'Pesan berhasil dikembalikan']);
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Method not allowed']);
