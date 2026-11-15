# VIP TONGK

تطبيق + سيرفر + لوحة تحكم. كل الاتصالات مشفّرة على مستوى التطبيق، فوق TLS.

- **المطور:** [@x_Tongk](https://t.me/x_Tongk)
- **القناة:** [PythonToolsIQ](https://t.me/PythonToolsIQ)
- **الحزمة:** `com.tongk.vip`
- **الإصدار:** 1.1

## التحميل

[VIP_Tongk_v1.1.apk](https://github.com/Vaj719/xTongk/releases/download/v1.1/VIP_Tongk_v1.1.apk)

## المحتوى

```
server/lib.php          النواة: تشفير AES-256-GCM + PBKDF2 + الحظر
server/connect.php      نقطة التفعيل المشفّرة
server/admin/index.php  لوحة التحكم
server/.htaccess        التوجيه + حماية الأسرار
server/SETUP.md         خطوات التشغيل
ENCRYPTION.md           شرح التشفير والحماية
```

## لوحة التحكم

`https://tongk.wisp.uno/admin/`

- دخول بكلمة سر — **3 محاولات**، بعدها حظر **30 دقيقة**
- إحصائيات: الكل · نشط · منتهي · موقوف · أجهزة مربوطة · ينتهي خلال 3 أيام
- إضافة كود (يدوي أو تلقائي) بأيام + ساعات + دقائق + حد أجهزة
- لكل كود: حذف · إيقاف/تشغيل · +أيام · تعديل حد الأجهزة · فصل الأجهزة

## التشفير

```
التطبيق: JSON → AES-256-GCM (IV عشوائي) → base64 → POST d=<...>
السيرفر: يفك ← يفحص ← يرد بنفس الطريقة
```

لا يوجد حقل مقروء في الطلب ولا في الرد. كل رسالة تحمل طابع وقت؛ السيرفر يرفض أي طلب أقدم من 3 دقائق.

## حماية التطبيق

تثبيت شهادة التوقيع · بصمة `classes.dex` · كشف المنقّح · كشف أدوات الحقن (Frida/Xposed/LSPosed/Substrate/Zygisk) · جلسة HMAC في Keystore · كشف رجوع الساعة. أي فشل = إغلاق فوري.

---

© @x_Tongk · https://t.me/PythonToolsIQ
