<?php
declare(strict_types=1);

/**
 * VIP Tongk — shared core.
 * Application-layer AES-256-GCM: every byte between app and server is sealed,
 * on top of TLS. A proxy terminating TLS still sees only base64 noise.
 */

const MASTER_B64 = 'TIODz4j3zB27kIzdjh9/Y5CUGoU5KkzTeLdI63S2xdc=';
const PASS_SALT_B64 = 'VJYVxguhKt8oIP5J764rlw==';
const PASS_HASH_B64 = 'xQbNUEmtikkN3fvrWJf0ZK5PAUGmrQfe14N0egCb6xs=';
const PBKDF2_ROUNDS = 100000;

const DATA_DIR   = __DIR__ . '/data';
const KEYS_FILE  = DATA_DIR . '/keys.json';
const TRIES_FILE = DATA_DIR . '/attempts.json';

const MAX_TRIES  = 3;
const LOCK_SECONDS = 1800;      // 30 minutes
const CLOCK_SKEW = 180;         // accepted request age, seconds

function master_key(): string {
    static $k = null;
    if ($k === null) {
        $k = base64_decode(MASTER_B64, true);
    }
    return $k;
}

/** seal: base64( iv[12] || tag[16] || ciphertext ) */
function seal(string $plain): string {
    $iv = random_bytes(12);
    $tag = '';
    $ct = openssl_encrypt($plain, 'aes-256-gcm', master_key(), OPENSSL_RAW_DATA, $iv, $tag, '', 16);
    if ($ct === false) {
        return '';
    }
    return base64_encode($iv . $tag . $ct);
}

function unseal(string $b64): ?string {
    $raw = base64_decode($b64, true);
    if ($raw === false || strlen($raw) < 29) {
        return null;
    }
    $iv  = substr($raw, 0, 12);
    $tag = substr($raw, 12, 16);
    $ct  = substr($raw, 28);
    $p = openssl_decrypt($ct, 'aes-256-gcm', master_key(), OPENSSL_RAW_DATA, $iv, $tag);
    return $p === false ? null : $p;
}

function ensure_data(): void {
    if (!is_dir(DATA_DIR)) {
        @mkdir(DATA_DIR, 0750, true);
    }
    if (!is_file(KEYS_FILE)) {
        file_put_contents(KEYS_FILE, json_encode(
            ['bind_on_first_use' => true, 'keys' => new stdClass()],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
        ), LOCK_EX);
    }
}

function load_db(): array {
    ensure_data();
    $raw = @file_get_contents(KEYS_FILE);
    $db = json_decode((string)$raw, true);
    if (!is_array($db) || !isset($db['keys']) || !is_array($db['keys'])) {
        $db = ['bind_on_first_use' => true, 'keys' => []];
    }
    return $db;
}

function save_db(array $db): bool {
    ensure_data();
    $json = json_encode($db, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    return file_put_contents(KEYS_FILE, $json, LOCK_EX) !== false;
}

function check_password(string $given): bool {
    $salt = base64_decode(PASS_SALT_B64, true);
    $want = base64_decode(PASS_HASH_B64, true);
    $got = hash_pbkdf2('sha256', $given, $salt, PBKDF2_ROUNDS, 32, true);
    return hash_equals($want, $got);
}

/* ---------------- login throttle ---------------- */

function client_ip(): string {
    return (string)($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
}

function load_tries(): array {
    ensure_data();
    $j = json_decode((string)@file_get_contents(TRIES_FILE), true);
    return is_array($j) ? $j : [];
}

function save_tries(array $t): void {
    ensure_data();
    file_put_contents(TRIES_FILE, json_encode($t), LOCK_EX);
}

function lock_state(): array {
    $t = load_tries();
    $ip = client_ip();
    $rec = $t[$ip] ?? ['n' => 0, 'until' => 0];
    $now = time();
    if (($rec['until'] ?? 0) > $now) {
        return ['locked' => true, 'left' => (int)$rec['until'] - $now, 'tries' => MAX_TRIES];
    }
    if (($rec['until'] ?? 0) > 0 && ($rec['until'] ?? 0) <= $now) {
        unset($t[$ip]);
        save_tries($t);
        return ['locked' => false, 'left' => 0, 'tries' => MAX_TRIES];
    }
    return ['locked' => false, 'left' => 0, 'tries' => MAX_TRIES - (int)($rec['n'] ?? 0)];
}

function note_failure(): array {
    $t = load_tries();
    $ip = client_ip();
    $rec = $t[$ip] ?? ['n' => 0, 'until' => 0];
    $rec['n'] = (int)$rec['n'] + 1;
    if ($rec['n'] >= MAX_TRIES) {
        $rec['until'] = time() + LOCK_SECONDS;
        $rec['n'] = MAX_TRIES;
    }
    $t[$ip] = $rec;
    save_tries($t);
    return lock_state();
}

function note_success(): void {
    $t = load_tries();
    unset($t[client_ip()]);
    save_tries($t);
}

/* ---------------- key helpers ---------------- */

function new_code(): string {
    $a = strtoupper(bin2hex(random_bytes(2)));
    $b = strtoupper(bin2hex(random_bytes(2)));
    $c = strtoupper(bin2hex(random_bytes(2)));
    return "TONGK-$a-$b-$c";
}

function fmt_left(int $secs): string {
    if ($secs <= 0) {
        return '0';
    }
    return sprintf('%d يوم %02d:%02d', intdiv($secs, 86400), intdiv($secs % 86400, 3600), intdiv($secs % 3600, 60));
}

function key_status(array $k): string {
    if (empty($k['active'])) {
        return 'stopped';
    }
    $exp = strtotime((string)($k['exp'] ?? ''));
    if ($exp === false || $exp <= time()) {
        return 'expired';
    }
    return 'active';
}
