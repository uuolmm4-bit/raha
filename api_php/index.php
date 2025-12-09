<?php
// تفعيل عرض الأخطاء للتطوير (قم بتعطيله في الإنتاج)

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/utils/response.php';
require_once __DIR__ . '/db/connection.php';

// معالجة CORS أولاً
handleCORS();

// الحصول على المسار المطلوب
$requestUri = $_SERVER['REQUEST_URI'];
$requestMethod = $_SERVER['REQUEST_METHOD'];

// إزالة query string من URI
$path = parse_url($requestUri, PHP_URL_PATH);

// إزالة /api_php من البداية إذا كان موجوداً
$path = preg_replace('#^/api_php#', '', $path);
$path = trim($path, '/');

// Health check endpoint
if ($path === 'api/health' || $path === 'health' || empty($path)) {
    try {
        $db = getDB();
        $db->query('SELECT 1');
        sendSuccess([
            'status' => 'ok',
            'database' => 'reachable',
            'timestamp' => date('c'),
            'path' => $path,
            'request_uri' => $requestUri
        ]);
    } catch (Exception $e) {
        sendError('Database connection failed: ' . $e->getMessage(), 500);
    }
}

// توجيه الطلبات إلى الملفات المناسبة
$pathParts = explode('/', $path);

if (count($pathParts) < 2 || $pathParts[0] !== 'api') {
    sendError('Invalid API endpoint. Path: ' . $path, 404);
}

$resource = $pathParts[1];

// تحديد الملف المناسب بناءً على المسار
$routeFile = __DIR__ . '/routes/' . $resource . '.php';

if (!file_exists($routeFile)) {
    sendError('Resource not found: ' . $resource . ' (File: ' . $routeFile . ')', 404);
}

// حفظ المسار الكامل في متغير للاستخدام في ملفات routes
$_SERVER['REQUEST_URI'] = '/' . $path;

// تحميل ملف الـ route
require_once $routeFile;

// إذا لم يتم إرسال استجابة، أرسل خطأ 404
sendError('Endpoint not found. Path: ' . $path, 404);

