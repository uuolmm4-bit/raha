<?php
require_once __DIR__ . '/../db/connection.php';
require_once __DIR__ . '/../utils/response.php';

$db = getDB();

// دالة لجلب خيارات المنتج من قاعدة البيانات
function getProductOptions($productId, $db) {
    $stmt = $db->prepare('
        SELECT group_name, option_type, option_name, option_price, display_order
        FROM product_options
        WHERE product_id = ?
        ORDER BY display_order, group_name
    ');
    $stmt->execute([$productId]);
    $options = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // إعادة هيكلة الخيارات في مجموعات
    $groupedOptions = [];
    foreach ($options as $option) {
        $groupName = $option['group_name'];
        if (!isset($groupedOptions[$groupName])) {
            $groupedOptions[$groupName] = [
                'groupName' => $groupName,
                'optionType' => $option['option_type'],
                'items' => []
            ];
        }
        $groupedOptions[$groupName]['items'][] = [
            'name' => $option['option_name'],
            'price' => (float)$option['option_price']
        ];
    }
    return array_values($groupedOptions); // إرجاع مصفوفة مفهرسة رقميًا
}

function mapProductRow($row, $db) {
    $product = [
        'id' => $row['id'],
        'name' => $row['name'],
        'description' => $row['description'] ?? '',
        'image' => $row['image'] ?? '',
        'price' => (float)$row['price'],
        'discount' => $row['discount'] ? (float)$row['discount'] : null,
        'category' => $row['category'] ?? '',
        'isActive' => (bool)$row['is_active'],
        'hasOptions' => (bool)$row['has_options'],
        'restaurantId' => $row['restaurant_id'] ?? null,
        'storeId' => $row['store_id'] ?? null,
        'createdAt' => $row['created_at'],
        'updatedAt' => $row['updated_at'],
        'options' => [], // إضافة حقل الخيارات
    ];

    // إذا كان المنتج يحتوي على خيارات، قم بجلبها
    if ($product['hasOptions']) {
        $product['options'] = getProductOptions($product['id'], $db);
    }

    return $product;
}

// GET /api/products
$pathParts = explode('/', trim($_SERVER['REQUEST_URI'], '/'));
$idIndex = array_search('products', $pathParts);
$hasId = $idIndex !== false && isset($pathParts[$idIndex + 1]) && !empty($pathParts[$idIndex + 1]);
$hasSearch = isset($_GET['q']);

if ($_SERVER['REQUEST_METHOD'] === 'GET' && !$hasId && !$hasSearch) {
    $restaurantId = $_GET['restaurantId'] ?? null;
    $storeId = $_GET['storeId'] ?? null;
    
    if (!$restaurantId && !$storeId) {
        sendError('restaurantId or storeId is required', 400);
    }
    
    $params = [];
    $query = "
        SELECT p.*
        FROM products p
        WHERE p.is_active = 1
    ";
    
    if ($restaurantId) {
        $query .= ' AND p.restaurant_id = ?';
        $params[] = $restaurantId;
    }
    
    if ($storeId) {
        $query .= ' AND p.store_id = ?';
        $params[] = $storeId;
    }
    
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 100;
    $query .= "
        ORDER BY p.created_at DESC
        LIMIT ?
    ";
    $params[] = $limit;
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
    
    // تم تمرير $db إلى الدالة mapProductRow
    $result = array_map(function($row) use ($db) {
        return mapProductRow($row, $db);
    }, $rows);
    sendSuccess($result);
}

// GET /api/products/:id
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $hasId && !$hasSearch) {
    $id = $pathParts[$idIndex + 1];
    
    $stmt = $db->prepare('SELECT * FROM products WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    
    if (!$row) {
        sendError('Product not found', 404);
    }
    
    // تم تمرير $db إلى الدالة mapProductRow
    sendSuccess(mapProductRow($row, $db));
}

// GET /api/products/search
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $hasSearch) {
    $search = '%' . $_GET['q'] . '%';
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
    
    $stmt = $db->prepare("
        SELECT p.*
        FROM products p
        WHERE p.is_active = 1 AND (p.name LIKE ? OR p.description LIKE ?)
        ORDER BY p.created_at DESC
        LIMIT ?
    ");
    $stmt->execute([$search, $search, $limit]);
    $rows = $stmt->fetchAll();
    
    // تم تمرير $db إلى الدالة mapProductRow
    $result = array_map(function($row) use ($db) {
        return mapProductRow($row, $db);
    }, $rows);
    sendSuccess($result);
}
