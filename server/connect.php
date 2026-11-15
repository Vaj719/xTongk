<?php
declare(strict_types=1);

/**
 * VIP Tongk — licence endpoint.
 * Upload as /connect on the host (see .htaccess).
 * Keys live in keys.json next to this file.
 */

const KEYS_FILE = __DIR__ . '/keys.json';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('X-Content-Type-Options: nosniff');

function fail(string $reason, int $code = 200): void {
    http_response_code($code);
    echo json_encode(['status' => false, 'reason' => $reason], JSON_UNESCAPED_UNICODE);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    fail('POST only', 405);
}

$key    = trim((string)($_POST['user_key'] ?? ''));
$hwid   = trim((string)($_POST['hwid'] ?? ($_POST['serial'] ?? '')));
$device = trim((string)($_POST['device_name'] ?? ''));

if ($key === '') {
    fail('المفتاح مطلوب');
}
if (!preg_match('/^[A-Za-z0-9\-_]{4,64}$/', $key)) {
    fail('صيغة المفتاح غير صحيحة');
}
if ($hwid === '') {
    fail('معرف الجهاز مطلوب');
}

if (!is_file(KEYS_FILE)) {
    fail('قاعدة المفاتيح غير موجودة');
}
$db = json_decode((string)file_get_contents(KEYS_FILE), true);
if (!is_array($db) || !isset($db['keys']) || !is_array($db['keys'])) {
    fail('قاعدة المفاتيح تالفة');
}

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
    fail('المفتاح غير موجود');
}
if (isset($entry['active']) && $entry['active'] === false) {
    fail('المفتاح موقوف');
}

$exp = strtotime((string)($entry['exp'] ?? ''));
if ($exp === false || $exp <= time()) {
    fail('انتهت صلاحية المفتاح');
}

$bound = trim((string)($entry['hwid'] ?? ''));
if ($bound !== '') {
    if (!hash_equals($bound, $hwid)) {
        fail('المفتاح مرتبط بجهاز آخر');
    }
} elseif (!empty($db['bind_on_first_use'])) {
    $db['keys'][$canon]['hwid'] = $hwid;
    $db['keys'][$canon]['bound_at'] = date('Y-m-d H:i:s');
    file_put_contents(
        KEYS_FILE,
        json_encode($db, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        LOCK_EX
    );
}

$stamp = date('Y-m-d H:i:s', $exp);
echo json_encode([
    'status' => true,
    'data' => [
        'exdate'      => $stamp,
        'EXP'         => $stamp,
        'hwid'        => $hwid,
        'device_name' => $device,
    ],
], JSON_UNESCAPED_UNICODE);
