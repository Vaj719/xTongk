<?php
declare(strict_types=1);

/**
 * POST /connect  — sealed licence endpoint.
 * Body: d=<base64 sealed JSON>   (application/x-www-form-urlencoded)
 * Reply: sealed JSON { s:1|0, e:expiry, m:message, dv:devicesLeft }
 */

require __DIR__ . '/lib.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

function reply(int $ok, string $msg, string $exp = '', int $dv = 0): void {
    echo json_encode(['d' => seal(json_encode(
        ['s' => $ok, 'm' => $msg, 'e' => $exp, 'dv' => $dv],
        JSON_UNESCAPED_UNICODE
    ))], JSON_UNESCAPED_UNICODE);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    reply(0, 'POST only');
}

$sealed = (string)($_POST['d'] ?? '');
if ($sealed === '') {
    reply(0, 'Bad request');
}

$plain = unseal($sealed);
if ($plain === null) {
    reply(0, 'Bad request');
}

$req = json_decode($plain, true);
if (!is_array($req)) {
    reply(0, 'Bad request');
}

$ts = (int)($req['t'] ?? 0);
if ($ts <= 0 || abs(time() - $ts) > CLOCK_SKEW) {
    reply(0, 'انتهت مهلة الطلب، تحقق من وقت الجهاز');
}

$key  = trim((string)($req['k'] ?? ''));
$hwid = trim((string)($req['h'] ?? ''));
$dev  = trim((string)($req['d'] ?? ''));

if ($key === '' || !preg_match('/^[A-Za-z0-9\-_]{4,64}$/', $key)) {
    reply(0, 'صيغة المفتاح غير صحيحة');
}
if ($hwid === '') {
    reply(0, 'معرف الجهاز مطلوب');
}

$db = load_db();
$entry = null;
$canon = $key;
foreach ($db['keys'] as $k => $v) {
    if (strcasecmp((string)$k, $key) === 0) {
        $entry = $v;
        $canon = (string)$k;
        break;
    }
}
if ($entry === null) {
    reply(0, 'المفتاح غير موجود');
}
if (empty($entry['active'])) {
    reply(0, 'المفتاح موقوف');
}

$exp = strtotime((string)($entry['exp'] ?? ''));
if ($exp === false || $exp <= time()) {
    reply(0, 'انتهت صلاحية المفتاح');
}

$max = max(1, (int)($entry['max_devices'] ?? 1));
$hwids = array_values(array_filter(array_map('strval', (array)($entry['hwids'] ?? []))));
$devices = (array)($entry['devices'] ?? []);

$known = in_array($hwid, $hwids, true);
if (!$known) {
    if (count($hwids) >= $max) {
        reply(0, 'المفتاح مستخدم على ' . count($hwids) . ' جهاز — الحد ' . $max);
    }
    $hwids[] = $hwid;
    $devices[] = [
        'hwid' => $hwid,
        'name' => $dev,
        'first' => date('Y-m-d H:i:s'),
        'last' => date('Y-m-d H:i:s'),
    ];
    $db['keys'][$canon]['hwids'] = $hwids;
    $db['keys'][$canon]['devices'] = $devices;
    save_db($db);
} else {
    foreach ($db['keys'][$canon]['devices'] as $i => $d) {
        if (($d['hwid'] ?? '') === $hwid) {
            $db['keys'][$canon]['devices'][$i]['last'] = date('Y-m-d H:i:s');
            if ($dev !== '') {
                $db['keys'][$canon]['devices'][$i]['name'] = $dev;
            }
            break;
        }
    }
    save_db($db);
}

reply(1, 'تم التفعيل', date('Y-m-d H:i:s', $exp), max(0, $max - count($hwids)));
