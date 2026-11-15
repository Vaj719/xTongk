# VIP TONGK — التشفير والحماية

تطبيق مبني من الصفر (Java + إطار أندرويد المباشر، بدون أي مكتبة خارجية).

- الحزمة: `com.tongk.vip`
- الاسم: VIP Tongk
- أقل إصدار: أندرويد 8.0 (API 26) — الهدف API 34
- التوقيع: RSA-4096، SHA-256 للشهادة `4ABE7EA8999B31F4877B3B2B1A07B74DDC5BA0A3A4B3732576EFA981C11A3A31`

---

## 1. تشفير الروابط — ثلاث طبقات

كل رابط في التطبيق (السيرفر `/connect`، قناة المطور، رابط الحالة `/v/status.json`)
**غير موجود كنص** في أي ملف داخل الـAPK.

### الطبقة الأولى — التقطيع
الـBase64 الكامل يُقطع إلى ثلاث خزّنات نصية منفصلة:

```
Keys.S0 + Keys.S1 + Keys.S2   →   تُجمع وقت التشغيل فقط
```

لا واحدة منها تشبه رابطاً، ولا واحدة تكفي وحدها.

### الطبقة الثانية — XOR
بعد فك الـBase64، كل بايت يُعكس بمفتاح 32 بايت (`Keys.SM`) بترتيب دائري:

```
byte[i] = byte[i] XOR mask[i % 32]
```

### الطبقة الثالثة — AES-256-GCM
- أول 12 بايت = IV
- الباقي = ciphertext + tag (128-bit)

المفتاح مشتق وقت التشغيل:

```
key = SHA-256( keyA ‖ keyB )      // مفتاحان 32 بايت لكل منهما
```

**النتيجة:** لا يوجد أي مفتاح مكتوب صراحةً، ولا أي رابط. حتى لو فكّ أحد الـAPK
ووجد `Keys.class`، ما يحصل إلا على مصفوفات بايتات عشوائية.

---

## 2. حماية التطبيق — ست طبقات

| # | الطبقة | ما تفعله |
|---|--------|-----------|
| 1 | تثبيت الشهادة | تقارن SHA-256 لشهادة التوقيع مع القيمة المخزّنة. أي إعادة توقيع = إغلاق فوري |
| 2 | بصمة `classes.dex` | الـSHA-256 للـdex مخزّنة في `assets/integrity/classes.sha256` وتُقارن عند كل إقلاع |
| 3 | كشف المنقّح | `Debug.isDebuggerConnected()` — يمنع التتبع |
| 4 | جلسة HMAC | HMAC-SHA256 بمفتاح داخل **AndroidKeyStore** غير قابل للتصدير |
| 5 | عمر الجلسة | 7 أيام كحد أقصى، وبعدها يجب إعادة التفعيل من السيرفر |
| 6 | كشف رجوع الساعة | إذا رجّع المستخدم وقت الجهاز أكثر من دقيقتين → مسح الجلسة وقفل |

### كيف تعمل الجلسة
```
payload = issuedAt + ":" + SHA-256(licenseKey)
mac     = HMAC-SHA256(payload)   ← مفتاح داخل الـKeystore
```
عند كل فحص: يُعاد حساب الـHMAC ويُقارن. تعديل الـ`SharedPreferences` يدوياً
يكسر التوقيع → جلسة مرفوضة.

---

## 3. عداد الانتهاء

- السيرفر يرسل تاريخ الانتهاء `exdate` (أو `EXP` / `expired_date`).
- يُخزَّن بالمللي ثانية في `SharedPreferences`.
- `Handler` يحدّث البلاطات الأربع (أيام / ساعات / دقائق / ثواني) كل ثانية.
- عند الوصول لصفر: قفل فوري لأزرار الحقن + مسح الجلسة.

---

## 4. سيرفر الترخيص

```
POST https://tongk.wisp.uno/connect
Content-Type: application/x-www-form-urlencoded

game=PUBG&user_key=<KEY>&serial=<ANDROID_ID>&hwid=<ANDROID_ID>&device_name=<BRAND MODEL>
```

نجاح:
```json
{"status": true, "data": {"exdate": "2026-12-31 23:59:59"}}
```

فشل:
```json
{"status": false, "reason": "المفتاح منتهي"}
```

فحص الحالة (اختياري):
```
GET https://tongk.wisp.uno/v/status.json
{"status":"active","message":"...","developer_url":"https://t.me/PythonToolsIQ"}
```

---

## 5. ملفات الحقن — داخل التطبيق

الملفات مخزّنة في `assets/` وتُستخرج مباشرة إلى مجلد اللعبة، بدون أي تنزيل:

| الملف | المكان |
|-------|--------|
| `game_patch_4.7.0.21758.pak` | `.../Saved/Paks/puffer_temp/pak/n5la/` |
| `n5la.lua` | `.../files/` |
| `MON5LA_settings.txt` | `.../Saved/Paks/` |

السورس الكامل للهاك موجود في `assets/src/` داخل التطبيق نفسه.

---

## 6. الصلاحيات المطلوبة

- `MANAGE_EXTERNAL_STORAGE` — للكتابة داخل مجلد اللعبة
- `INTERNET` — للتحقق من المفتاح

---

## 7. البناء من السورس

```
aapt2 compile --dir res -o res.zip
aapt2 link -o base.apk -I android.jar --manifest AndroidManifest.xml -R res.zip --java gen
javac -source 8 -target 8 -bootclasspath android.jar -d classes @sources.txt
d8 --lib android.jar --min-api 26 --release --output dex classes/**/*.class
# ضع dex/classes.dex داخل base.apk ثم:
zipalign -f -p 4 unsigned.apk aligned.apk
apksigner sign --ks tongk.jks --out signed.apk aligned.apk
```

---

المطور: `@x_Tongk`
القناة: `https://t.me/PythonToolsIQ`
