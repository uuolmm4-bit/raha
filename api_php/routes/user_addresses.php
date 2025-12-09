<?php
require_once __DIR__ . '/../db/connection.php';
require_once __DIR__ . '/../utils/response.php';

$db = getDB();

// GET /api/users/:userId/addresses - جلب جميع عناوين المستخدم
if ($_SERVER['REQUEST_METHOD'] === 'GET' && preg_match('/\/api\/users\/([^\/]+)\/addresses$/', $_SERVER['REQUEST_URI'], $matches)) {
    $userId = $matches[1];
    
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
if ($_SERVER['REQUEST_METHOD'] === 'POST' && preg_match('/\/api\/users\/([^\/]+)\/addresses$/', $_SERVER['REQUEST_URI'], $matches)) {
    $userId = $matches[1];
    $data = getRequestData();
    
    $label = $data['label'] ?? null;
    $address = $data['address'] ?? null;
    $latitude = $data['latitude'] ?? null;
    $longitude = $data['longitude'] ?? null;
    $isDefault = isset($data['is_default']) ? (int)$data['is_default'] : 0;
    
    if (!$label || !$address || $latitude === null || $longitude === null) {
        sendError('label, address, latitude, and longitude are required', 400);
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
if ($_SERVER['REQUEST_METHOD'] === 'PUT' && preg_match('/\/api\/users\/addresses\/([^\/]+)$/', $_SERVER['REQUEST_URI'], $matches)) {
    $addressId = $matches[1];
    $data = getRequestData();
    
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
if ($_SERVER['REQUEST_METHOD'] === 'DELETE' && preg_match('/\/api\/users\/addresses\/([^\/]+)$/', $_SERVER['REQUEST_URI'], $matches)) {
    $addressId = $matches[1];
    
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
if ($_SERVER['REQUEST_METHOD'] === 'PATCH' && preg_match('/\/api\/users\/([^\/]+)\/addresses\/([^\/]+)\/set-default$/', $_SERVER['REQUEST_URI'], $matches)) {
    $userId = $matches[1];
    $addressId = $matches[2];
    
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

sendError('Endpoint not found. Path: ' . $_SERVER['REQUEST_URI'], 404);
