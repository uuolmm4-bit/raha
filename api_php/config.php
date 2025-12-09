<?php
// إعدادات قاعدة البيانات
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASSWORD', getenv('DB_PASSWORD') ?: '');
define('DB_NAME', getenv('DB_NAME') ?: 'xxx');

// إعدادات CORS
define('ALLOW_ORIGINS', getenv('ALLOW_ORIGINS') ?: '*');

// إعدادات OTP Provider
define('OTP_PROVIDER_URL', getenv('OTP_PROVIDER_URL') ?: '');
define('OTP_ORG_NAME', getenv('OTP_ORG_NAME') ?: '');
define('OTP_USERNAME', getenv('OTP_USERNAME') ?: '');
define('OTP_PASSWORD', getenv('OTP_PASSWORD') ?: '');

// إعدادات أخرى
define('TIMEZONE', 'UTC');
date_default_timezone_set(TIMEZONE);

// إعدادات الخطأ (للإنتاج قم بتعطيل عرض الأخطاء)
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

