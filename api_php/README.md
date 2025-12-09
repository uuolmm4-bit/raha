# Raha API - PHP Version

API backend لتطبيق راحة مكتوب بـ PHP.

## المتطلبات

- PHP 7.4 أو أحدث
- MySQL 5.7 أو أحدث
- Apache مع mod_rewrite أو Nginx
- PDO extension مفعل

## التثبيت

1. انسخ الملفات إلى مجلد على الخادم
2. انسخ `.env.example` إلى `.env` وعدّل الإعدادات
3. تأكد من أن قاعدة البيانات موجودة والجداول منشأة
4. تأكد من أن مجلد `api_php` قابل للكتابة (للـ logs إذا لزم الأمر)

## الإعدادات

عدّل ملف `.env` أو `config.php` مباشرة:

```env
DB_HOST=127.0.0.1
DB_PORT=3306
DB_USER=root
DB_PASSWORD=your_password
DB_NAME=raha_app
```

## الاستخدام

### Apache

تأكد من تفعيل `mod_rewrite`:

```bash
sudo a2enmod rewrite
sudo systemctl restart apache2
```

### Nginx

أضف التكوين التالي:

```nginx
location /api_php {
    try_files $uri $uri/ /api_php/index.php?$query_string;
}
```

## Endpoints

### Health Check
```
GET /api/health
```

### Authentication
```
POST /api/auth/otp/send
POST /api/auth/otp/verify
POST /api/auth/login
POST /api/auth/register
```

### Restaurants
```
GET /api/restaurants
GET /api/restaurants/:id
```

### Stores
```
GET /api/stores
GET /api/stores/:id
```

### Products
```
GET /api/products?restaurantId=...&storeId=...
GET /api/products/:id
GET /api/products/search?q=...
```

### Orders
```
GET /api/orders?userId=...
GET /api/orders/:id
POST /api/orders
PATCH /api/orders/:id/status
```

### Users
```
GET /api/users?phone=...
GET /api/users/:id
PUT /api/users/:id
DELETE /api/users/:id
POST /api/users/:id/tokens
DELETE /api/users/:id/tokens/:token
```

### Notifications
```
GET /api/notifications?userId=...
PATCH /api/notifications/:id/read
```

### Offers
```
GET /api/offers
```

## ملاحظات

- جميع الاستجابات بصيغة JSON
- جميع الطلبات يجب أن تحتوي على header: `Content-Type: application/json`
- في حالة الخطأ، يتم إرجاع `{"message": "error message"}` مع كود HTTP مناسب

