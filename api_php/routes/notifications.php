<?php
require_once __DIR__ . '/../db/connection.php';
require_once __DIR__ . '/../utils/response.php';

$db = getDB();

// GET /api/notifications
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $userId = $_GET['userId'] ?? null;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
    
    if (!$userId) {
        sendError('userId is required', 400);
    }
    
    $stmt = $db->prepare("
        SELECT id, user_id, title, body, image, is_read, created_at
        FROM notifications
        WHERE user_id = ?
        ORDER BY created_at DESC
        LIMIT ?
    ");
    $stmt->execute([$userId, $limit]);
    $rows = $stmt->fetchAll();
    
    sendSuccess($rows);
}

// PATCH /api/notifications/:id/read
if ($_SERVER['REQUEST_METHOD'] === 'PATCH' && strpos($_SERVER['REQUEST_URI'], '/read') !== false) {
    $pathParts = explode('/', trim($_SERVER['REQUEST_URI'], '/'));
    $idIndex = array_search('notifications', $pathParts);
    $id = $idIndex !== false && isset($pathParts[$idIndex + 1]) ? $pathParts[$idIndex + 1] : null;
    
    if (!$id) {
        sendError('Notification ID is required', 400);
    }
    
    $data = getRequestData();
    $userId = $data['userId'] ?? null;
    
    if (!$userId) {
        sendError('userId is required', 400);
    }
    
    $stmt = $db->prepare("
        UPDATE notifications
        SET is_read = 1
        WHERE id = ? AND user_id = ?
    ");
    $stmt->execute([$id, $userId]);
    
    if ($stmt->rowCount() === 0) {
        sendError('Notification not found', 404);
    }
    
    sendSuccess(['success' => true]);
}

