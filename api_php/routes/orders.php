<?php
require_once __DIR__ . '/../db/connection.php';
require_once __DIR__ . '/../utils/response.php';

$db = getDB();
// افترض أن لديك كلاس Database لإدارة المعاملات
// require_once __DIR__ . '/../db/Database.php'; 
$database = Database::getInstance();

function hydrateOrder($orderRow, $itemsRows = []) {
    return [
        'id' => $orderRow['id'],
        'userId' => $orderRow['user_id'],
        'userName' => $orderRow['user_name'],
        'userPhone' => $orderRow['user_phone'],
        'deliveryAddress' => $orderRow['delivery_address'],
        'deliveryLatitude' => $orderRow['delivery_latitude'] ? (float)$orderRow['delivery_latitude'] : null,
        'deliveryLongitude' => $orderRow['delivery_longitude'] ? (float)$orderRow['delivery_longitude'] : null,
        'subtotal' => (float)$orderRow['subtotal'],
        'deliveryFee' => (float)$orderRow['delivery_fee'],
        'totalAmount' => (float)$orderRow['total_amount'],
        'status' => $orderRow['status'],
        'restaurantNames' => $orderRow['restaurant_names'],
        'notes' => $orderRow['notes'],
        'paymentMethod' => $orderRow['payment_method'],
        'isPaid' => (bool)$orderRow['is_paid'],
        'orderDate' => $orderRow['order_date'],
        'createdAt' => $orderRow['created_at'],
        'updatedAt' => $orderRow['updated_at'],
        'items' => array_map(function($item) {
            return [
                'id' => $item['id'],
                'orderId' => $item['order_id'],
                'productId' => $item['product_id'],
                'productName' => $item['product_name'],
                'restaurantId' => $item['restaurant_id'],
                'restaurantName' => $item['restaurant_name'],
                'unitPrice' => (float)$item['unit_price'],
                'quantity' => (int)$item['quantity'],
                'totalPrice' => (float)$item['total_price'],
            ];
        }, $itemsRows),
    ];
}

// GET /api/orders
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $_SERVER['REQUEST_URI'] === '/api/orders') {
    try {
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
        $offset = ($page - 1) * $limit;
        
        // معلمات التصفية
        $userId = $_GET['userId'] ?? null;
        $status = $_GET['status'] ?? null;
        $startDate = $_GET['startDate'] ?? null;
        $endDate = $_GET['endDate'] ?? null;
        
        $whereConditions = [];
        $params = [];
        
        if ($userId) {
            $whereConditions[] = "o.user_id = ?";
            $params[] = $userId;
        }
        
        if ($status) {
            $whereConditions[] = "o.status = ?";
            $params[] = $status;
        }
        
        if ($startDate) {
            $whereConditions[] = "o.order_date >= ?";
            $params[] = $startDate;
        }
        
        if ($endDate) {
            $whereConditions[] = "o.order_date <= ?";
            $params[] = $endDate;
        }
        
        $whereClause = $whereConditions ? "WHERE " . implode(" AND ", $whereConditions) : "";
        
        // جلب الطلبات
        $stmt = $db->prepare("
            SELECT o.*, u.name as user_name, u.phone as user_phone
            FROM orders o
            LEFT JOIN users u ON o.user_id = u.id
            $whereClause
            ORDER BY o.created_at DESC
            LIMIT ? OFFSET ?
        ");
        
        $finalParams = array_merge($params, [$limit, $offset]);
        $stmt->execute($finalParams);
        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // جلب العدد الإجمالي للصفحات
        $countStmt = $db->prepare("
            SELECT COUNT(*) as total
            FROM orders o
            $whereClause
        ");
        $countStmt->execute($params);
        $totalResult = $countStmt->fetch(PDO::FETCH_ASSOC);
        $totalOrders = $totalResult['total'];
        $totalPages = ceil($totalOrders / $limit);
        
        // جلب العناصر لكل طلب
        $orderIds = array_column($orders, 'id');
        $orderItems = [];
        
        if (!empty($orderIds)) {
            $placeholders = str_repeat('?,', count($orderIds) - 1) . '?';
            $itemsStmt = $db->prepare("
                SELECT * FROM order_items
                WHERE order_id IN ($placeholders)
            ");
            $itemsStmt->execute($orderIds);
            $allItems = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
            
            // تجميع العناصر حسب order_id
            foreach ($allItems as $item) {
                $orderItems[$item['order_id']][] = $item;
            }
        }
        
        // تحويل البيانات
        $hydratedOrders = [];
        foreach ($orders as $order) {
            $items = $orderItems[$order['id']] ?? [];
            $hydratedOrders[] = hydrateOrder($order, $items);
        }
        
        sendSuccess([
            'orders' => $hydratedOrders,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'totalOrders' => $totalOrders,
                'totalPages' => $totalPages
            ]
        ]);
        
    } catch (Exception $e) {
        sendError('Failed to fetch orders: ' . $e->getMessage(), 500);
    }
    exit;
}

// GET /api/orders/:id
if ($_SERVER['REQUEST_METHOD'] === 'GET' && preg_match('/\/api\/orders\/([^\/]+)$/', $_SERVER['REQUEST_URI'], $matches)) {
    $orderId = $matches[1];
    
    try {
        // جلب الطلب الرئيسي
        $stmt = $db->prepare("
            SELECT o.*, u.name as user_name, u.phone as user_phone
            FROM orders o
            LEFT JOIN users u ON o.user_id = u.id
            WHERE o.id = ?
        ");
        $stmt->execute([$orderId]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$order) {
            sendError('Order not found', 404);
        }
        
        // جلب العناصر
        $itemsStmt = $db->prepare("SELECT * FROM order_items WHERE order_id = ?");
        $itemsStmt->execute([$orderId]);
        $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
        
        $hydratedOrder = hydrateOrder($order, $items);
        sendSuccess($hydratedOrder);
        
    } catch (Exception $e) {
        sendError('Failed to fetch order: ' . $e->getMessage(), 500);
    }
    exit;
}

// POST /api/orders
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_SERVER['REQUEST_URI'] === '/api/orders') {
    $data = getRequestData();
    
    $userId = $data['userId'] ?? null;
    $items = $data['items'] ?? [];
    
    if (!$userId || empty($items)) {
        sendError('userId and items are required', 400);
    }
    
    $orderId = generateUUID();
    
    try {
        $database->withTransaction(function($conn) use ($orderId, $data, $items) {
            $restaurantNames = array_unique(array_filter(array_map(function($item) {
                return $item['restaurantName'] ?? $item['storeName'] ?? null;
            }, $items)));
            $restaurantNamesStr = implode(', ', $restaurantNames);
            
            $stmt = $conn->prepare("
                INSERT INTO orders (
                    id, user_id, user_name, user_phone, delivery_address,
                    delivery_latitude, delivery_longitude, subtotal, delivery_fee,
                    total_amount, status, restaurant_names, notes, payment_method,
                    is_paid
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, ?, ?, 0)
            ");
            $stmt->execute([
                $orderId,
                $data['userId'],
                $data['userName'] ?? '',
                $data['userPhone'] ?? '',
                $data['deliveryAddress'] ?? '',
                $data['deliveryLatitude'] ?? null,
                $data['deliveryLongitude'] ?? null,
                $data['subtotal'] ?? 0,
                $data['deliveryFee'] ?? 0,
                $data['totalAmount'] ?? 0,
                $restaurantNamesStr,
                $data['notes'] ?? null,
                $data['paymentMethod'] ?? 'cash',
            ]);
            
            $itemStmt = $conn->prepare("
                INSERT INTO order_items (
                    order_id, product_id, product_name, restaurant_id,
                    restaurant_name, unit_price, quantity, total_price
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");

            foreach ($items as $item) {
                // استخلاص المعرّف الأصلي للمنتج من المعرّف المخصص
                $originalProductId = $item['productId'];
                $parts = explode('_', $originalProductId, 2); // الفصل عند أول '_' فقط
                if (count($parts) > 1) {
                    $originalProductId = $parts[0];
                }

                $itemStmt->execute([
                    $orderId,
                    $originalProductId, // <-- استخدام المعرّف الأصلي بعد استخلاصه
                    $item['productName'], // الاسم المخصص مع الخيارات يبقى كما هو
                    $item['restaurantId'] ?? null,
                    $item['restaurantName'] ?? null,
                    $item['unitPrice'] ?? 0,
                    $item['quantity'] ?? 1,
                    ($item['unitPrice'] ?? 0) * ($item['quantity'] ?? 1),
                ]);
            }
        });
        
        sendSuccess(['id' => $orderId], 201);
    } catch (Exception $e) {
        sendError('Order creation failed: ' . $e->getMessage(), 500);
    }
    exit;
}

// PATCH /api/orders/:id/status
if ($_SERVER['REQUEST_METHOD'] === 'PATCH' && preg_match('/\/api\/orders\/([^\/]+)\/status$/', $_SERVER['REQUEST_URI'], $matches)) {
    $orderId = $matches[1];
    
    if (!$orderId) {
        sendError('Order ID is required', 400);
    }
    
    $data = getRequestData();
    $status = $data['status'] ?? null;
    
    if (!$status) {
        sendError('status is required', 400);
    }
    
    $allowed = ['pending', 'confirmed', 'preparing', 'onWay', 'delivered', 'cancelled'];
    if (!in_array($status, $allowed)) {
        sendError('invalid status value', 400);
    }
    
    $stmt = $db->prepare("
        UPDATE orders
        SET status = ?, updated_at = CURRENT_TIMESTAMP
        WHERE id = ?
    ");
    $stmt->execute([$status, $orderId]);
    
    if ($stmt->rowCount() === 0) {
        sendError('Order not found', 404);
    }
    
    sendSuccess(['success' => true]);
    exit;
}

// إذا لم يطابق أي endpoint
sendError('Endpoint not found. Path: ' . $_SERVER['REQUEST_URI'], 404);