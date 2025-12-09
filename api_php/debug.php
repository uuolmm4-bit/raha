<?php
// ملف للتحقق من إعدادات API
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$info = [
    'php_version' => PHP_VERSION,
    'server' => $_SERVER['SERVER_NAME'] ?? 'unknown',
    'request_uri' => $_SERVER['REQUEST_URI'] ?? 'unknown',
    'request_method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown',
    'script_name' => $_SERVER['SCRIPT_NAME'] ?? 'unknown',
    'document_root' => $_SERVER['DOCUMENT_ROOT'] ?? 'unknown',
    'path' => parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH),
    'query_string' => $_SERVER['QUERY_STRING'] ?? '',
    'config_exists' => file_exists(__DIR__ . '/config.php'),
    'db_connection' => 'not tested',
];

// اختبار الاتصال بقاعدة البيانات
try {
    require_once __DIR__ . '/config.php';
    require_once __DIR__ . '/db/connection.php';
    $db = getDB();
    $db->query('SELECT 1');
    $info['db_connection'] = 'success';
} catch (Exception $e) {
    $info['db_connection'] = 'failed: ' . $e->getMessage();
}

// اختبار الملفات
$info['files'] = [
    'index.php' => file_exists(__DIR__ . '/index.php'),
    'config.php' => file_exists(__DIR__ . '/config.php'),
    'routes/restaurants.php' => file_exists(__DIR__ . '/routes/restaurants.php'),
    'routes/stores.php' => file_exists(__DIR__ . '/routes/stores.php'),
    'routes/products.php' => file_exists(__DIR__ . '/routes/products.php'),
];

echo json_encode($info, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

