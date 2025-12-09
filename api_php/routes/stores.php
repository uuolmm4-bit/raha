<?php
require_once __DIR__ . '/../db/connection.php';
require_once __DIR__ . '/../utils/response.php';

$db = getDB();

// دالة خاصة للمتاجر - تضيف type: 'store'
function mapStoreRow($row) {
    $cuisines = !empty($row['cuisines']) ? explode(',', $row['cuisines']) : [];
    
    $result = [
        'id' => $row['id'],
        'name' => $row['name'],
        'city' => $row['city'] ?? null,
        'image' => $row['image'] ?? '',
        'description' => $row['description'] ?? null,
        'latitude' => $row['latitude'] ? (float)$row['latitude'] : null,
        'longitude' => $row['longitude'] ? (float)$row['longitude'] : null,
        'averagePreparationTime' => $row['average_delivery_time'] ?? $row['average_preparation_time'] ?? null,
        'rating' => $row['rating'] ? (float)$row['rating'] : null,
        'deliveryFee' => isset($row['delivery_fee']) ? (float)$row['delivery_fee'] : 0,
        'isOpen' => (bool)$row['is_open'],
        'displayOrder' => (int)($row['display_order'] ?? 0),
        'createdAt' => $row['created_at'],
        'updatedAt' => $row['updated_at'],
        'cuisines' => $cuisines,
        'type' => 'store', // إضافة type للمتاجر
    ];
    
    return $result;
}

// GET /api/stores
$pathParts = explode('/', trim($_SERVER['REQUEST_URI'], '/'));
$idIndex = array_search('stores', $pathParts);
$hasId = $idIndex !== false && isset($pathParts[$idIndex + 1]) && !empty($pathParts[$idIndex + 1]);

if ($_SERVER['REQUEST_METHOD'] === 'GET' && !$hasId) {
    try {
        $params = [];
        $query = "
            SELECT s.*, GROUP_CONCAT(sc.category ORDER BY sc.category SEPARATOR ',') AS cuisines
            FROM stores s
            LEFT JOIN store_categories sc ON sc.store_id = s.id
            WHERE 1=1
        ";
        
        if (isset($_GET['search']) && $_GET['search']) {
            $search = '%' . $_GET['search'] . '%';
            $query .= ' AND (s.name LIKE ? OR sc.category LIKE ?)';
            $params[] = $search;
            $params[] = $search;
        }
        
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
        $query .= "
            GROUP BY s.id
            ORDER BY s.is_open DESC, s.display_order ASC, s.created_at DESC
            LIMIT ?
        ";
        $params[] = $limit;
        
        $stmt = $db->prepare($query);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
        
        // إذا لم توجد متاجر، أرجع قائمة فارغة بدلاً من خطأ
        if (empty($rows)) {
            sendSuccess([]);
        }
        
        $result = array_map('mapStoreRow', $rows);
        sendSuccess($result);
    } catch (Exception $e) {
        // في حالة الخطأ، أرجع قائمة فارغة مع رسالة خطأ في التطوير
        error_log('Stores API Error: ' . $e->getMessage());
        sendSuccess([]);
    }
}

// GET /api/stores/:id
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $hasId) {
    $id = $pathParts[$idIndex + 1];
    
    $stmt = $db->prepare("
        SELECT s.*, GROUP_CONCAT(sc.category ORDER BY sc.category SEPARATOR ',') AS cuisines
        FROM stores s
        LEFT JOIN store_categories sc ON sc.store_id = s.id
        WHERE s.id = ?
        GROUP BY s.id
    ");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    
    if (!$row) {
        sendError('Store not found', 404);
    }
    
    sendSuccess(mapStoreRow($row));
}

