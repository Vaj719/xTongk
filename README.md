# VIP TONGK

تطبيق + سيرفر Node.js + لوحة تحكم. كل الاتصالات مشفّرة على مستوى التطبيق، فوق TLS.

- **المطور:** [@x_Tongk](https://t.me/x_Tongk)
- **القناة:** [PythonToolsIQ](https://t.me/PythonToolsIQ)
- **الحزمة:** `com.tongk.vip`
- **الإصدار:** 1.6

## التحميل

- التطبيق: [VIP_Tongk_v1.6.apk](https://github.com/Vaj719/xTongk/releases/download/v1.6/VIP_Tongk_v1.6.apk)
- السيرفر: [tongk_server_node.zip](https://github.com/Vaj719/xTongk/releases/download/v1.3/tongk_server_node.zip)

## المحتوى

```
release/          حزم APK لكل إصدار
ENCRYPTION.md     شرح التشفير والحماية
```

السيرفر يُنزّل من صفحة الإصدارات — ملف `tongk_server_node.zip` فقط.

## لوحة التحكم

`https://tongk.wisp.uno/admin`

- دخول بكلمة سر — **3 محاولات**، بعدها حظر **30 دقيقة**
- جلسة تنتهي بعد 8 ساعات
- إحصائيات: الكل · نشط · منتهي · موقوف · أجهزة مربوطة · ينتهي خلال 3 أيام
- إضافة كود (يدوي أو تلقائي) بأيام + ساعات + دقائق + حد أجهزة
- لكل كود: حذف · إيقاف/تشغيل · +أيام · تعديل الحد · فصل الأجهزة

## نقاط النهاية

```
POST /connect          التفعيل المشفّر (AES-256-GCM)
GET  /v/status.json    الحالة — مفتاح إيقاف عن بعد
GET  /v/versions.json  آخر إصدار + أقل إصدار مسموح
GET  /v/health.json    فحص السيرفر
GET  /admin            اللوحة
```

## التشفير

```
التطبيق: JSON → AES-256-GCM (IV عشوائي) → base64 → POST d=<...>
السيرفر: يفك ← يفحص ← يرد بنفس الطريقة
```

لا يوجد حقل مقروء في الطلب ولا في الرد. كل رسالة تحمل طابع وقت؛ السيرفر يرفض أي طلب أقدم من 3 دقائق.

## الحماية

تثبيت شهادة التوقيع · بصمة `classes.dex` · كشف المنقّح · جلسة HMAC في Keystore · كشف رجوع الساعة · حد 30 طلب/دقيقة لكل IP · قاعدة مفاتيح مع نسخة احتياطية.

---

© @x_Tongk · https://t.me/PythonToolsIQ
