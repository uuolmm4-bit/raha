<?php
require_once __DIR__ . '/../db/connection.php';
require_once __DIR__ . '/../utils/response.php';

$db = getDB();

// تخزين OTP في session (في الإنتاج، استخدم Redis أو قاعدة بيانات)
session_start();
if (!isset($_SESSION['otp_store'])) {
    $_SESSION['otp_store'] = [];
}
$otpStore = &$_SESSION['otp_store'];

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

// POST /api/auth/otp/send
if ($_SERVER['REQUEST_METHOD'] === 'POST' && strpos($_SERVER['REQUEST_URI'], '/otp/send') !== false) {
    $data = getRequestData();
    $phone = $data['phone'] ?? null;
    
    if (!$phone) {
        sendError('phone is required', 400);
    }
    
    $code = str_pad(rand(100000, 999999), 6, '0', STR_PAD_LEFT);
    $otpStore[$phone] = [
        'code' => $code,
        'expiresAt' => time() + (5 * 60), // 5 دقائق
    ];
    
    // إرسال OTP عبر SMS Provider
    if (OTP_PROVIDER_URL) {
        $params = http_build_query([
            'orgName' => OTP_ORG_NAME,
            'userName' => OTP_USERNAME,
            'password' => OTP_PASSWORD,
            'mobileNo' => '967' . $phone,
            'text' => "رمز التحقق من راحة هو $code",
            'coding' => '2',
        ]);
        
        $ch = curl_init(OTP_PROVIDER_URL . '?' . $params);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_exec($ch);
        curl_close($ch);
    }
    
    $response = ['success' => true];
    if (getenv('APP_ENV') === 'development') {
        $response['demoCode'] = $code;
    }
    sendSuccess($response);
}

// POST /api/auth/otp/verify
if ($_SERVER['REQUEST_METHOD'] === 'POST' && strpos($_SERVER['REQUEST_URI'], '/otp/verify') !== false) {
    $data = getRequestData();
    $phone = $data['phone'] ?? null;
    $code = $data['code'] ?? null;
    
    if (!$phone || !$code) {
        sendError('phone and code are required', 400);
    }
    
    if (!isset($otpStore[$phone]) || 
        $otpStore[$phone]['code'] !== $code || 
        $otpStore[$phone]['expiresAt'] < time()) {
        sendError('Invalid or expired code', 400);
    }
    
    unset($otpStore[$phone]);
    sendSuccess(['success' => true]);
}

// POST /api/auth/login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && strpos($_SERVER['REQUEST_URI'], '/login') !== false) {
    $data = getRequestData();
    $phone = $data['phone'] ?? null;
    
    if (!$phone) {
        sendError('phone is required', 400);
    }
    
    $stmt = $db->prepare('SELECT * FROM users WHERE phone = ? LIMIT 1');
    $stmt->execute([$phone]);
    $user = $stmt->fetch();
    
    if (!$user) {
        sendError('User not found', 404);
    }
    
    sendSuccess(sanitizeUser($user));
}

// POST /api/auth/register
if ($_SERVER['REQUEST_METHOD'] === 'POST' && strpos($_SERVER['REQUEST_URI'], '/register') !== false) {
    $data = getRequestData();
    $name = $data['name'] ?? null;
    $phone = $data['phone'] ?? null;
    $latitude = $data['latitude'] ?? 0;
    $longitude = $data['longitude'] ?? 0;
    $address = $data['address'] ?? '';
    
    if (!$name || !$phone) {
        sendError('name and phone are required', 400);
    }
    
    $id = generateUUID();
    
    try {
        $stmt = $db->prepare('
            INSERT INTO users (id, name, phone, address, latitude, longitude)
            VALUES (?, ?, ?, ?, ?, ?)
        ');
        $stmt->execute([$id, $name, $phone, $address, $latitude, $longitude]);
        
        $stmt = $db->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->execute([$id]);
        $user = $stmt->fetch();
        
        sendSuccess(sanitizeUser($user), 201);
    } catch (PDOException $e) {
        if ($e->getCode() == 23000) { // Duplicate entry
            sendError('Phone already registered', 409);
        }
        sendError('Registration failed: ' . $e->getMessage(), 500);
    }
}

