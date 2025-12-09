<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once __DIR__ . '/../db/connection.php';
require_once __DIR__ . '/../utils/response.php';

$db = getDB();

function mapRestaurantRow($row) {
    $cuisines = !empty($row['cuisines']) ? explode(',', $row['cuisines']) : [];
    
    return [
        'id' => $row['id'],
        'name' => $row['name'],
        'city' => $row['city'] ?? null,
        'image' => $row['image'] ?? '',
        'description' => $row['description'] ?? null,
        'latitude' => $row['latitude'] ? (float)$row['latitude'] : null,
        'longitude' => $row['longitude'] ? (float)$row['longitude'] : null,
        'averagePreparationTime' => $row['average_preparation_time'] ?? null,
        'rating' => $row['rating'] ? (float)$row['rating'] : null,
        'deliveryFee' => $row['delivery_fee'] ? (float)$row['delivery_fee'] : 0,
        'isOpen' => (bool)$row['is_open'],
        'displayOrder' => (int)($row['display_order'] ?? 0),
        'createdAt' => $row['created_at'],
        'updatedAt' => $row['updated_at'],
        'cuisines' => $cuisines,
    ];
}

// GET /api/restaurants
$pathParts = explode('/', trim($_SERVER['REQUEST_URI'], '/'));
$idIndex = array_search('restaurants', $pathParts);
$hasId = $idIndex !== false && isset($pathParts[$idIndex + 1]) && !empty($pathParts[$idIndex + 1]);

if ($_SERVER['REQUEST_METHOD'] === 'GET' && !$hasId) {
    $params = [];
    $query = "
        SELECT r.*, GROUP_CONCAT(rc.cuisine ORDER BY rc.cuisine SEPARATOR ',') AS cuisines
        FROM restaurants r
        LEFT JOIN restaurant_cuisines rc ON rc.restaurant_id = r.id
        WHERE 1=1
    ";
    
    if (isset($_GET['city']) && $_GET['city']) {
        $query .= ' AND r.city = ?';
        $params[] = $_GET['city'];
    }
    
    if (isset($_GET['search']) && $_GET['search']) {
        $search = '%' . $_GET['search'] . '%';
        $query .= ' AND (r.name LIKE ? OR rc.cuisine LIKE ?)';
        $params[] = $search;
        $params[] = $search;
    }
    
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
    $query .= "
        GROUP BY r.id
        ORDER BY r.is_open DESC, r.display_order ASC, r.created_at DESC
        LIMIT ?
    ";
    $params[] = $limit;
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
    
    $result = array_map('mapRestaurantRow', $rows);
    sendSuccess($result);
}

// GET /api/restaurants/:id
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $hasId) {
    $id = $pathParts[$idIndex + 1];
    
    $stmt = $db->prepare("
        SELECT r.*, GROUP_CONCAT(rc.cuisine ORDER BY rc.cuisine SEPARATOR ',') AS cuisines
        FROM restaurants r
        LEFT JOIN restaurant_cuisines rc ON rc.restaurant_id = r.id
        WHERE r.id = ?
        GROUP BY r.id
    ");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    
    if (!$row) {
        sendError('Restaurant not found', 404);
    }
    
    sendSuccess(mapRestaurantRow($row));
}

