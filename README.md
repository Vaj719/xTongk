# VIP TONGK

تطبيق تحميل حزم اللعب — مبني من الصفر، سورس كامل، مشفّر بالكامل.

- **المطور:** [@x_Tongk](https://t.me/x_Tongk)
- **القناة:** [PythonToolsIQ](https://t.me/PythonToolsIQ)
- **الحزمة:** `com.tongk.vip`
- **الإصدار:** 1.0

## التحميل

`release/VIP_Tongk_v1.0.apk`

## المحتوى

```
release/VIP_Tongk_v1.0.apk   التطبيق الموقّع
server/                      ملفات السيرفر (API + الحالة + الإصدارات)
ENCRYPTION.md                شرح التشفير والحماية بالتفصيل
```

## الميزات

- تفعيل بمفتاح عبر `https://tongk.wisp.uno/connect` (HTTPS فقط)
- عداد انتهاء اشتراك حقيقي (أيام / ساعات / دقائق / ثواني)
- خمس نسخ مدعومة: Global · Korea · Taiwan · Vietnam · BGMI
- حقن مباشر من داخل التطبيق — الملفات مضمّنة في الـAPK
- تشفير ثلاثي الطبقات لكل الروابط (Base64 مجزّأ → XOR → AES-256-GCM)
- حماية: تثبيت الشهادة + بصمة الـdex + جلسة HMAC في Keystore + كشف رجوع الساعة

## البناء

راجع `ENCRYPTION.md` — القسم 7.

---

© @x_Tongk · https://t.me/PythonToolsIQ
