<?php

function sendResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function sendError($message, $statusCode = 400) {
    sendResponse(['message' => $message], $statusCode);
}

function sendSuccess($data = null, $statusCode = 200) {
    if ($data === null) {
        sendResponse(['success' => true], $statusCode);
    } else {
        sendResponse($data, $statusCode);
    }
}

function handleCORS() {
    // السماح بجميع المصادر في التطوير
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '*';
    
    if (defined('ALLOW_ORIGINS') && ALLOW_ORIGINS !== '*') {
        $allowedOrigins = explode(',', ALLOW_ORIGINS);
        if (in_array($origin, $allowedOrigins)) {
            header("Access-Control-Allow-Origin: $origin");
        }
    } else {
        header("Access-Control-Allow-Origin: *");
    }
    
    header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
    header('Access-Control-Max-Age: 86400');
    header('Access-Control-Allow-Credentials: true');
    
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit;
    }
}

function getRequestData() {
    $data = json_decode(file_get_contents('php://input'), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        sendError('Invalid JSON', 400);
    }
    return $data ?? [];
}

function getQueryParams() {
    return $_GET;
}

function generateUUID() {
    return sprintf(
        '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
}

