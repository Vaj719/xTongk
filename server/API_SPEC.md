# VIP TONGK — عقد السيرفر

المضيف: `https://tongk.wisp.uno` — HTTPS فقط، التطبيق يرفض http.

## POST /connect

```
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

- `exdate` أو `EXP` أو `expired_date` — كلها مقبولة.
- يقبل التاريخ `yyyy-MM-dd HH:mm:ss` أو طابع زمني (ثواني أو مللي ثانية).
- يجب أن يكون التاريخ في المستقبل، وإلا يُرفض التفعيل.

## GET /v/status.json

```json
{"status":"active","message":"...","developer_url":"https://t.me/PythonToolsIQ"}
```

- `status` لازم `active` أو `stopped`.
- `developer_url` لازم رابط https على `t.me` أو `telegram.me`.
