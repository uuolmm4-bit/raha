<?php
require_once __DIR__ . '/../db/connection.php';
require_once __DIR__ . '/../utils/response.php';

$db = getDB();

// GET /api/offers
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $db->prepare("
        SELECT id, title, description, name, image, created_at, updated_at
        FROM offers
        ORDER BY created_at DESC
        LIMIT 20
    ");
    $stmt->execute();
    $rows = $stmt->fetchAll();
    
    sendSuccess($rows);
}

