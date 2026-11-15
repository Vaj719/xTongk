# تشغيل السيرفر — 5 دقائق

المضيف: `tongk.wisp.uno` (يدعم PHP).

## 1. ارفع الملفات

من مجلد `server/` ارفع إلى جذر الموقع (`public_html`):

```
connect.php
keys.json
.htaccess
```

ثم انسخ `server/status.json` إلى مجلد باسم `v/` بحيث يصير:

```
/v/status.json
```

## 2. تأكد من المسار

افتح المتصفح وجرب:

```
https://tongk.wisp.uno/v/status.json
```

لازم يرجع JSON فيه `"status":"active"`.

## 3. أضف مفتاح

افتح `keys.json` وعدّل:

```json
{
    "bind_on_first_use": true,
    "keys": {
        "TONGK-1234-ABCD-0001": {
            "exp": "2026-12-31 23:59:59",
            "hwid": "",
            "active": true
        }
    }
}
```

- `exp` — تاريخ الانتهاء. لازم يكون بالمستقبل وإلا يُرفض المفتاح.
- `hwid` — اتركه فاضي، ينربط بأول جهاز يستخدم المفتاح تلقائياً.
- `active` — `false` يوقف المفتاح فوراً بدون حذفه.

## 4. جرب من الطرفية

```bash
curl -s -X POST https://tongk.wisp.uno/connect \
  -d "game=PUBG" \
  -d "user_key=TONGK-DEMO-0001-2026" \
  -d "hwid=testdevice123" \
  -d "device_name=Test"
```

المفروض يرجع:

```json
{"status":true,"data":{"exdate":"2026-12-31 23:59:59","EXP":"2026-12-31 23:59:59","hwid":"testdevice123","device_name":"Test"}}
```

## 5. امسح اختبار الجهاز

بعد تجربة `curl` صار المفتاح مربوط بـ`testdevice123`. افتح `keys.json` وامسح قيمة `hwid` عشان يرجع فاضي للجهاز الحقيقي.

---

## ملاحظات

- `.htaccess` يمنع تحميل `keys.json` من المتصفح ويحوّل `/connect` إلى `connect.php` ويجبر HTTPS.
- لو الاستضافة ما تدعم `.htaccess`، غيّر اسم `connect.php` إلى `index.php` وضع المفاتيح بنفس المجلد، ثم اضبط التطبيق على المسار الجديد.
- **نسخة احتياطية:** نزّل `keys.json` بين فترة وفترة — كل مفاتيحك فيه.
