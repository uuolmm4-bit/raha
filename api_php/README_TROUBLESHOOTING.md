# حل مشاكل API

## المشكلة: التطبيق لا يجلب البيانات

### 1. تحقق من أن PHP API يعمل

افتح المتصفح واذهب إلى:
- `http://localhost/api_php/test.php` - يجب أن ترى رسالة نجاح
- `http://localhost/api_php/debug.php` - يجب أن ترى معلومات الإعدادات
- `http://localhost/api_php/api/health` - يجب أن ترى حالة قاعدة البيانات

### 2. تحقق من المسار في التطبيق

في `lib/services/api_config.dart`، تأكد من أن المسار صحيح:

```dart
// للـ Android Emulator
static const String phpApiUrl = 'http://10.0.2.2/api_php/api';

// للـ iOS Simulator أو المتصفح
static const String phpApiUrl = 'http://localhost/api_php/api';

// للجهاز الحقيقي (استبدل IP بجهازك)
static const String phpApiUrl = 'http://192.168.1.100/api_php/api';
```

### 3. تحقق من قاعدة البيانات

تأكد من:
- قاعدة البيانات موجودة
- الجداول منشأة (استخدم `database_schema.sql`)
- إعدادات الاتصال في `config.php` صحيحة

### 4. تحقق من Apache/Nginx

#### Apache:
- تأكد من تفعيل `mod_rewrite`
- تأكد من أن `.htaccess` موجود ويعمل

#### Nginx:
أضف هذا التكوين:
```nginx
location /api_php {
    try_files $uri $uri/ /api_php/index.php?$query_string;
}
```

### 5. تحقق من الأخطاء

افتح ملف `error_log` في Apache أو Nginx لرؤية الأخطاء.

أو أضف هذا في بداية `index.php`:
```php
error_reporting(E_ALL);
ini_set('display_errors', 1);
```

### 6. اختبر API مباشرة

استخدم Postman أو curl:

```bash
# اختبار health
curl http://localhost/api_php/api/health

# اختبار restaurants
curl http://localhost/api_php/api/restaurants

# اختبار مع query
curl "http://localhost/api_php/api/restaurants?limit=5"
```

### 7. تحقق من CORS

إذا كانت المشكلة في CORS، تأكد من أن `handleCORS()` يتم استدعاؤها في `index.php`.

### 8. تحقق من المسار في Flutter

في Flutter، افتح DevTools واذهب إلى Network tab لرؤية الطلبات والأخطاء.

### 9. حلول سريعة

1. **إعادة تشغيل Apache/Nginx**
2. **مسح cache المتصفح**
3. **إعادة بناء التطبيق**: `flutter clean && flutter pub get`
4. **تحقق من firewall** - قد يحجب المنفذ 80

### 10. اختبار الاتصال من التطبيق

أضف هذا الكود في التطبيق للاختبار:

```dart
try {
  final response = await http.get(Uri.parse('http://10.0.2.2/api_php/api/health'));
  print('Status: ${response.statusCode}');
  print('Body: ${response.body}');
} catch (e) {
  print('Error: $e');
}
```

