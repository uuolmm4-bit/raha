<?php
require_once __DIR__ . '/../db/connection.php';
require_once __DIR__ . '/../utils/response.php';

$db = getDB();

function sanitizeUser($row) {
    return [
        'id' => $row['id'],
        'name' => $row['name'],
        'phone' => $row['phone'],
        'address' => $row['address'] ?? null,
        'latitude' => $row['latitude'] ? (float)$row['latitude'] : null,
        'longitude' => $row['longitude'] ? (float)$row['longitude'] : null,
        'isBlocked' => (bool)$row['is_blocked'],
        'createdAt' => $row['created_at'],
        'updatedAt' => $row['updated_at'],
    ];
}

// GET /api/users?phone=...
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['phone'])) {
    $phone = $_GET['phone'];
    
    $stmt = $db->prepare('SELECT * FROM users WHERE phone = ? LIMIT 1');
    $stmt->execute([$phone]);
    $user = $stmt->fetch();
    
    if (!$user) {
        sendError('User not found', 404);
    }
    
    sendSuccess(sanitizeUser($user));
}

// GET /api/users/:id
if ($_SERVER['REQUEST_METHOD'] === 'GET' && !isset($_GET['phone'])) {
    $pathParts = explode('/', trim($_SERVER['REQUEST_URI'], '/'));
    $idIndex = array_search('users', $pathParts);
    $id = $idIndex !== false && isset($pathParts[$idIndex + 1]) ? $pathParts[$idIndex + 1] : null;
    
    if ($id && strpos($id, 'tokens') === false) {
        $stmt = $db->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $user = $stmt->fetch();
        
        if (!$user) {
            sendError('User not found', 404);
        }
        
        sendSuccess(sanitizeUser($user));
    }
}

// PUT /api/users/:id
if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    $pathParts = explode('/', trim($_SERVER['REQUEST_URI'], '/'));
    $idIndex = array_search('users', $pathParts);
    $id = $idIndex !== false && isset($pathParts[$idIndex + 1]) ? $pathParts[$idIndex + 1] : null;
    
    if (!$id) {
        sendError('User ID is required', 400);
    }
    
    $data = getRequestData();
    $allowed = ['name', 'phone', 'address', 'latitude', 'longitude', 'is_blocked'];
    $fields = [];
    $values = [];
    
    foreach ($allowed as $key) {
        if (isset($data[$key])) {
            $fields[] = "$key = ?";
            $values[] = $data[$key];
        }
    }
    
    if (empty($fields)) {
        sendError('No fields to update', 400);
    }
    
    $values[] = $id;
    $query = "UPDATE users SET " . implode(', ', $fields) . ", updated_at = CURRENT_TIMESTAMP WHERE id = ?";
    
    $stmt = $db->prepare($query);
    $stmt->execute($values);
    
    $stmt = $db->prepare('SELECT * FROM users WHERE id = ?');
    $stmt->execute([$id]);
    $user = $stmt->fetch();
    
    sendSuccess(sanitizeUser($user));
}

// DELETE /api/users/:id
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $pathParts = explode('/', trim($_SERVER['REQUEST_URI'], '/'));
    $idIndex = array_search('users', $pathParts);
    $id = $idIndex !== false && isset($pathParts[$idIndex + 1]) ? $pathParts[$idIndex + 1] : null;
    
    if (!$id) {
        sendError('User ID is required', 400);
    }
    
    $stmt = $db->prepare('DELETE FROM users WHERE id = ?');
    $stmt->execute([$id]);
    
    if ($stmt->rowCount() === 0) {
        sendError('User not found', 404);
    }
    
    sendSuccess(['success' => true]);
}

// POST /api/users/:id/tokens
if ($_SERVER['REQUEST_METHOD'] === 'POST' && strpos($_SERVER['REQUEST_URI'], '/tokens') !== false) {
    $pathParts = explode('/', trim($_SERVER['REQUEST_URI'], '/'));
    $idIndex = array_search('users', $pathParts);
    $id = $idIndex !== false && isset($pathParts[$idIndex + 1]) ? $pathParts[$idIndex + 1] : null;
    
    if (!$id) {
        sendError('User ID is required', 400);
    }
    
    $data = getRequestData();
    $token = $data['token'] ?? null;
    $platform = $data['platform'] ?? null;
    
    if (!$token) {
        sendError('token is required', 400);
    }
    
    $stmt = $db->prepare("
        INSERT INTO user_devices (user_id, token, platform)
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE platform = VALUES(platform), created_at = CURRENT_TIMESTAMP
    ");
    $stmt->execute([$id, $token, $platform]);
    
    sendSuccess(['success' => true]);
}

// DELETE /api/users/:id/tokens/:token
if ($_SERVER['REQUEST_METHOD'] === 'DELETE' && strpos($_SERVER['REQUEST_URI'], '/tokens/') !== false) {
    $pathParts = explode('/', trim($_SERVER['REQUEST_URI'], '/'));
    $idIndex = array_search('users', $pathParts);
    $id = $idIndex !== false && isset($pathParts[$idIndex + 1]) ? $pathParts[$idIndex + 1] : null;
    $token = $idIndex !== false && isset($pathParts[$idIndex + 3]) ? $pathParts[$idIndex + 3] : null;
    
    if (!$id || !$token) {
        sendError('User ID and token are required', 400);
    }
    
    $stmt = $db->prepare('DELETE FROM user_devices WHERE user_id = ? AND token = ?');
    $stmt->execute([$id, $token]);
    
    sendSuccess(['success' => true]);
}

// ============================================================================
// إدارة العناوين المتعددة للمستخدم
// ============================================================================

// GET /api/users/:userId/addresses - جلب جميع عناوين المستخدم
if ($_SERVER['REQUEST_METHOD'] === 'GET' && strpos($_SERVER['REQUEST_URI'], '/addresses') !== false && strpos($_SERVER['REQUEST_URI'], '/addresses/') === false) {
    $pathParts = explode('/', trim($_SERVER['REQUEST_URI'], '/'));
    $idIndex = array_search('users', $pathParts);
    $userId = $idIndex !== false && isset($pathParts[$idIndex + 1]) ? $pathParts[$idIndex + 1] : null;
    
    if (!$userId) {
        sendError('User ID is required', 400);
    }
    
    try {
        $stmt = $db->prepare("
            SELECT * FROM user_addresses 
            WHERE user_id = ? 
            ORDER BY is_default DESC, created_at DESC
        ");
        $stmt->execute([$userId]);
        $addresses = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        sendSuccess($addresses);
    } catch (Exception $e) {
        sendError('Failed to fetch addresses: ' . $e->getMessage(), 500);
    }
    exit;
}

// POST /api/users/:userId/addresses - إضافة عنوان جديد
if ($_SERVER['REQUEST_METHOD'] === 'POST' && strpos($_SERVER['REQUEST_URI'], '/addresses') !== false && strpos($_SERVER['REQUEST_URI'], '/addresses/') === false) {
    $pathParts = explode('/', trim($_SERVER['REQUEST_URI'], '/'));
    $idIndex = array_search('users', $pathParts);
    $userId = $idIndex !== false && isset($pathParts[$idIndex + 1]) ? $pathParts[$idIndex + 1] : null;
    $data = getRequestData();
    
    $label = $data['label'] ?? null;
    $address = $data['address'] ?? null;
    $latitude = $data['latitude'] ?? null;
    $longitude = $data['longitude'] ?? null;
    $isDefault = isset($data['is_default']) ? (int)$data['is_default'] : 0;
    
    if (!$userId || !$label || !$address || $latitude === null || $longitude === null) {
        sendError('userId, label, address, latitude, and longitude are required', 400);
    }
    
    try {
        $db->beginTransaction();
        
        // إذا كان العنوان الجديد هو الافتراضي، إلغاء الافتراضية من العناوين الأخرى
        if ($isDefault) {
            $updateStmt = $db->prepare("
                UPDATE user_addresses 
                SET is_default = 0 
                WHERE user_id = ?
            ");
            $updateStmt->execute([$userId]);
        }
        
        $addressId = generateUUID();
        $stmt = $db->prepare("
            INSERT INTO user_addresses (
                id, user_id, label, address, latitude, longitude, is_default
            ) VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $addressId,
            $userId,
            $label,
            $address,
            $latitude,
            $longitude,
            $isDefault
        ]);
        
        $db->commit();
        sendSuccess(['id' => $addressId], 201);
    } catch (Exception $e) {
        $db->rollBack();
        sendError('Failed to add address: ' . $e->getMessage(), 500);
    }
    exit;
}

// PUT /api/users/addresses/:addressId - تحديث عنوان
if ($_SERVER['REQUEST_METHOD'] === 'PUT' && strpos($_SERVER['REQUEST_URI'], '/users/addresses/') !== false) {
    $pathParts = explode('/', trim($_SERVER['REQUEST_URI'], '/'));
    $addressIndex = array_search('addresses', $pathParts);
    $addressId = $addressIndex !== false && isset($pathParts[$addressIndex + 1]) ? $pathParts[$addressIndex + 1] : null;
    $data = getRequestData();
    
    if (!$addressId) {
        sendError('Address ID is required', 400);
    }
    
    try {
        // جلب معلومات العنوان الحالي
        $getStmt = $db->prepare("SELECT user_id, is_default FROM user_addresses WHERE id = ?");
        $getStmt->execute([$addressId]);
        $currentAddress = $getStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$currentAddress) {
            sendError('Address not found', 404);
        }
        
        $userId = $currentAddress['user_id'];
        $isDefault = isset($data['is_default']) ? (int)$data['is_default'] : $currentAddress['is_default'];
        
        $db->beginTransaction();
        
        // إذا كان العنوان الجديد هو الافتراضي، إلغاء الافتراضية من العناوين الأخرى
        if ($isDefault && !$currentAddress['is_default']) {
            $updateStmt = $db->prepare("
                UPDATE user_addresses 
                SET is_default = 0 
                WHERE user_id = ? AND id != ?
            ");
            $updateStmt->execute([$userId, $addressId]);
        }
        
        $stmt = $db->prepare("
            UPDATE user_addresses 
            SET label = ?, address = ?, latitude = ?, longitude = ?, is_default = ?, updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");
        $stmt->execute([
            $data['label'] ?? null,
            $data['address'] ?? null,
            $data['latitude'] ?? null,
            $data['longitude'] ?? null,
            $isDefault,
            $addressId
        ]);
        
        $db->commit();
        sendSuccess(['success' => true]);
    } catch (Exception $e) {
        $db->rollBack();
        sendError('Failed to update address: ' . $e->getMessage(), 500);
    }
    exit;
}

// DELETE /api/users/addresses/:addressId - حذف عنوان
if ($_SERVER['REQUEST_METHOD'] === 'DELETE' && strpos($_SERVER['REQUEST_URI'], '/users/addresses/') !== false) {
    $pathParts = explode('/', trim($_SERVER['REQUEST_URI'], '/'));
    $addressIndex = array_search('addresses', $pathParts);
    $addressId = $addressIndex !== false && isset($pathParts[$addressIndex + 1]) ? $pathParts[$addressIndex + 1] : null;
    
    if (!$addressId) {
        sendError('Address ID is required', 400);
    }
    
    try {
        $stmt = $db->prepare("DELETE FROM user_addresses WHERE id = ?");
        $stmt->execute([$addressId]);
        
        if ($stmt->rowCount() === 0) {
            sendError('Address not found', 404);
        }
        
        sendSuccess(['success' => true]);
    } catch (Exception $e) {
        sendError('Failed to delete address: ' . $e->getMessage(), 500);
    }
    exit;
}

// PATCH /api/users/:userId/addresses/:addressId/set-default - تعيين عنوان كافتراضي
if ($_SERVER['REQUEST_METHOD'] === 'PATCH' && strpos($_SERVER['REQUEST_URI'], '/set-default') !== false) {
    $pathParts = explode('/', trim($_SERVER['REQUEST_URI'], '/'));
    $idIndex = array_search('users', $pathParts);
    $userId = $idIndex !== false && isset($pathParts[$idIndex + 1]) ? $pathParts[$idIndex + 1] : null;
    $addressIndex = array_search('addresses', $pathParts);
    $addressId = $addressIndex !== false && isset($pathParts[$addressIndex + 1]) ? $pathParts[$addressIndex + 1] : null;
    
    if (!$userId || !$addressId) {
        sendError('User ID and Address ID are required', 400);
    }
    
    try {
        $db->beginTransaction();
        
        // إلغاء الافتراضية من جميع العناوين
        $updateStmt = $db->prepare("
            UPDATE user_addresses 
            SET is_default = 0 
            WHERE user_id = ?
        ");
        $updateStmt->execute([$userId]);
        
        // تعيين العنوان المحدد كافتراضي
        $stmt = $db->prepare("
            UPDATE user_addresses 
            SET is_default = 1, updated_at = CURRENT_TIMESTAMP
            WHERE id = ? AND user_id = ?
        ");
        $stmt->execute([$addressId, $userId]);
        
        if ($stmt->rowCount() === 0) {
            $db->rollBack();
            sendError('Address not found', 404);
        }
        
        $db->commit();
        sendSuccess(['success' => true]);
    } catch (Exception $e) {
        $db->rollBack();
        sendError('Failed to set default address: ' . $e->getMessage(), 500);
    }
    exit;
}

