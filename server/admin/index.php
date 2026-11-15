<?php
declare(strict_types=1);

require dirname(__DIR__) . '/lib.php';

session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'httponly' => true,
    'samesite' => 'Strict',
    'secure'   => (($_SERVER['HTTPS'] ?? '') === 'on'),
]);
session_name('tongkadmin');
session_start();

header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header('Cache-Control: no-store');

function h($s): string {
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function csrf(): string {
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(24));
    }
    return $_SESSION['csrf'];
}

function csrf_ok(): bool {
    return isset($_POST['csrf'], $_SESSION['csrf'])
        && hash_equals((string)$_SESSION['csrf'], (string)$_POST['csrf']);
}

$locked = lock_state();
$error = '';
$notice = '';

/* ---------- login ---------- */
if (isset($_POST['do']) && $_POST['do'] === 'login') {
    if ($locked['locked']) {
        $error = 'محظور — حاول بعد ' . fmt_left($locked['left']);
    } elseif (!csrf_ok()) {
        $error = 'جلسة غير صالحة';
    } elseif (check_password((string)($_POST['password'] ?? ''))) {
        note_success();
        $_SESSION['in'] = true;
        $_SESSION['at'] = time();
        session_regenerate_id(true);
        header('Location: ' . strtok((string)$_SERVER['REQUEST_URI'], '?'));
        exit;
    } else {
        $st = note_failure();
        $error = $st['locked']
            ? 'محاولات كثيرة — حاول بعد نصف ساعة'
            : 'كلمة السر غير صحيحة — بقى لك ' . $st['tries'] . ' محاولة';
        $locked = $st;
    }
}

if (isset($_GET['out'])) {
    $_SESSION = [];
    session_destroy();
    header('Location: ' . strtok((string)$_SERVER['REQUEST_URI'], '?'));
    exit;
}

$authed = !empty($_SESSION['in']);

/* ---------- actions ---------- */
if ($authed && isset($_POST['do']) && $_POST['do'] !== 'login') {
    if (!csrf_ok()) {
        $error = 'جلسة غير صالحة';
    } else {
        $db = load_db();
        $act = (string)$_POST['do'];
        $code = (string)($_POST['code'] ?? '');

        if ($act === 'add') {
            $c = strtoupper(trim((string)($_POST['newcode'] ?? '')));
            if ($c === '') {
                $c = new_code();
            }
            if (!preg_match('/^[A-Za-z0-9\-_]{4,64}$/', $c)) {
                $error = 'صيغة الكود غير صحيحة';
            } elseif (isset($db['keys'][$c])) {
                $error = 'الكود موجود مسبقاً';
            } else {
                $d = max(0, (int)($_POST['days'] ?? 0));
                $hh = max(0, min(23, (int)($_POST['hours'] ?? 0)));
                $mm = max(0, min(59, (int)($_POST['mins'] ?? 0)));
                $secs = $d * 86400 + $hh * 3600 + $mm * 60;
                if ($secs <= 0) {
                    $secs = 86400;
                }
                $db['keys'][$c] = [
                    'exp'         => date('Y-m-d H:i:s', time() + $secs),
                    'created'     => date('Y-m-d H:i:s'),
                    'active'      => true,
                    'max_devices' => max(1, (int)($_POST['maxdev'] ?? 1)),
                    'hwids'       => [],
                    'devices'     => [],
                ];
                save_db($db);
                $notice = 'تم إنشاء الكود: ' . $c;
            }
        } elseif ($act === 'delete' && $code !== '') {
            unset($db['keys'][$code]);
            save_db($db);
            $notice = 'تم حذف الكود';
        } elseif ($act === 'toggle' && $code !== '') {
            if (isset($db['keys'][$code])) {
                $db['keys'][$code]['active'] = empty($db['keys'][$code]['active']);
                save_db($db);
                $notice = empty($db['keys'][$code]['active']) ? 'تم إيقاف الكود' : 'تم تشغيل الكود';
            }
        } elseif ($act === 'extend' && $code !== '') {
            if (isset($db['keys'][$code])) {
                $add = max(1, (int)($_POST['days'] ?? 1)) * 86400;
                $base = max(time(), (int)strtotime((string)$db['keys'][$code]['exp']));
                $db['keys'][$code]['exp'] = date('Y-m-d H:i:s', $base + $add);
                save_db($db);
                $notice = 'تمت إضافة ' . intdiv($add, 86400) . ' يوم';
            }
        } elseif ($act === 'reset' && $code !== '') {
            if (isset($db['keys'][$code])) {
                $db['keys'][$code]['hwids'] = [];
                $db['keys'][$code]['devices'] = [];
                save_db($db);
                $notice = 'تم فصل الأجهزة';
            }
        } elseif ($act === 'maxdev' && $code !== '') {
            if (isset($db['keys'][$code])) {
                $db['keys'][$code]['max_devices'] = max(1, (int)($_POST['maxdev'] ?? 1));
                save_db($db);
                $notice = 'تم تحديث حد الأجهزة';
            }
        }
    }
}

$db = $authed ? load_db() : ['keys' => []];
$now = time();
$stat = ['total' => 0, 'active' => 0, 'expired' => 0, 'stopped' => 0, 'devices' => 0, 'soon' => 0];
foreach ($db['keys'] as $k => $v) {
    $stat['total']++;
    $st = key_status($v);
    $stat[$st]++;
    $stat['devices'] += count((array)($v['hwids'] ?? []));
    $e = (int)strtotime((string)($v['exp'] ?? ''));
    if ($st === 'active' && $e - $now < 3 * 86400) {
        $stat['soon']++;
    }
}
?>
<!doctype html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>VIP Tongk — لوحة التحكم</title>
<style>
:root{--bg:#04060b;--mid:#0b0a1e;--card:#0c1020;--gold:#f7c96b;--gold2:#d9a441;--vio:#a855f7;--cy:#22d3ee;--tx:#f6f3ff;--tx2:#b9b2d4;--err:#ff6b8b;--ok:#4ade80}
*{box-sizing:border-box;margin:0;padding:0}
body{background:radial-gradient(1200px 600px at 50% -10%,#170f2e 0%,var(--bg) 55%);color:var(--tx);font-family:system-ui,-apple-system,"Segoe UI",Tahoma,sans-serif;min-height:100vh;padding:22px}
a{color:var(--vio);text-decoration:none}
.wrap{max-width:1180px;margin:0 auto}
.brand{display:flex;align-items:center;gap:14px;margin-bottom:22px}
.logo{width:52px;height:52px;border-radius:50%;background:linear-gradient(135deg,#1a1030,#0a0b16);border:2px solid var(--gold);display:grid;place-items:center;color:var(--gold);font-weight:800;font-size:13px}
.brand h1{font-size:22px;letter-spacing:.08em;color:var(--gold)}
.brand p{font-size:11px;color:var(--tx2);letter-spacing:.12em}
.card{background:linear-gradient(180deg,rgba(17,13,38,.95),rgba(10,11,22,.95));border:1px solid rgba(247,201,107,.4);border-radius:20px;padding:22px;margin-bottom:18px}
.card h2{font-size:14px;color:var(--gold);letter-spacing:.1em;margin-bottom:16px;font-weight:700}
label{display:block;font-size:12px;color:var(--tx2);margin-bottom:6px}
input,select{width:100%;background:#0c1020;border:1px solid rgba(168,85,247,.45);border-radius:12px;padding:11px 13px;color:var(--tx);font-size:14px;outline:none}
input:focus{border-color:var(--gold)}
button{cursor:pointer;border:0;border-radius:12px;padding:11px 18px;font-weight:700;font-size:13px}
.btn{background:linear-gradient(90deg,var(--gold),var(--gold2),var(--gold));color:#0a0616}
.btn2{background:#1b0f36;color:var(--tx);border:1px solid var(--vio)}
.btn3{background:#2a0d1c;color:#ffd9e1;border:1px solid var(--err)}
.btn4{background:#0f1a2a;color:var(--cy);border:1px solid var(--cy)}
.row{display:flex;gap:12px;flex-wrap:wrap}
.row>div{flex:1;min-width:120px}
.msg{padding:11px 14px;border-radius:12px;font-size:13px;margin-bottom:14px}
.msg.err{background:rgba(255,107,139,.12);border:1px solid var(--err);color:#ffb3c2}
.msg.ok{background:rgba(74,222,128,.1);border:1px solid var(--ok);color:#a7f3c4}
.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px;margin-bottom:18px}
.stat{background:linear-gradient(180deg,#1a1136,#090a17);border:1px solid rgba(247,201,107,.35);border-radius:16px;padding:16px;text-align:center}
.stat b{display:block;font-size:26px;color:var(--gold);margin-bottom:4px}
.stat span{font-size:11px;color:var(--tx2);letter-spacing:.08em}
table{width:100%;border-collapse:collapse;font-size:12.5px}
th{text-align:right;color:var(--tx2);font-weight:600;padding:10px 8px;border-bottom:1px solid rgba(168,85,247,.25);font-size:11px;letter-spacing:.06em}
td{padding:11px 8px;border-bottom:1px solid rgba(255,255,255,.05);vertical-align:middle}
tr:hover td{background:rgba(168,85,247,.05)}
.code{font-family:ui-monospace,Consolas,monospace;color:var(--gold);font-size:12px;letter-spacing:.04em}
.pill{display:inline-block;padding:3px 9px;border-radius:20px;font-size:10.5px;font-weight:700}
.p-act{background:rgba(74,222,128,.14);color:#7df0a6;border:1px solid rgba(74,222,128,.4)}
.p-exp{background:rgba(255,107,139,.12);color:#ffa8ba;border:1px solid rgba(255,107,139,.4)}
.p-stp{background:rgba(185,178,212,.12);color:#cfc9e2;border:1px solid rgba(185,178,212,.35)}
.act{display:flex;gap:5px;flex-wrap:wrap}
.act button{padding:6px 9px;font-size:11px;border-radius:9px}
.act input[type=number]{width:56px;padding:6px;font-size:11px;border-radius:8px}
.login{max-width:400px;margin:8vh auto}
.scroll{overflow-x:auto}
.foot{text-align:center;color:var(--tx2);font-size:11px;margin-top:18px}
</style>
</head>
<body>
<div class="wrap">
<?php if (!$authed): ?>
  <div class="login">
    <div class="brand" style="justify-content:center">
      <div class="logo">VIP</div>
      <div><h1>VIP TONGK</h1><p>SERVER CONTROL</p></div>
    </div>
    <div class="card">
      <h2>تسجيل الدخول</h2>
      <?php if ($error): ?><div class="msg err"><?= h($error) ?></div><?php endif; ?>
      <?php if ($locked['locked']): ?>
        <div class="msg err">الحساب محظور — حاول بعد <?= h(fmt_left($locked['left'])) ?></div>
      <?php else: ?>
        <form method="post" autocomplete="off">
          <input type="hidden" name="do" value="login">
          <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
          <label>كلمة السر</label>
          <input type="password" name="password" autofocus required>
          <div style="height:14px"></div>
          <button class="btn" style="width:100%">دخول</button>
        </form>
        <p style="margin-top:12px;font-size:11px;color:var(--tx2);text-align:center">
          ثلاث محاولات فقط — بعدها حظر نصف ساعة
        </p>
      <?php endif; ?>
    </div>
  </div>
<?php else: ?>
  <div class="brand">
    <div class="logo">VIP</div>
    <div style="flex:1"><h1>VIP TONGK</h1><p>SERVER CONTROL PANEL</p></div>
    <a href="?out=1"><button class="btn2">خروج</button></a>
  </div>

  <?php if ($error): ?><div class="msg err"><?= h($error) ?></div><?php endif; ?>
  <?php if ($notice): ?><div class="msg ok"><?= h($notice) ?></div><?php endif; ?>

  <div class="grid">
    <div class="stat"><b><?= $stat['total'] ?></b><span>كل الأكواد</span></div>
    <div class="stat"><b><?= $stat['active'] ?></b><span>نشط</span></div>
    <div class="stat"><b><?= $stat['expired'] ?></b><span>منتهي</span></div>
    <div class="stat"><b><?= $stat['stopped'] ?></b><span>موقوف</span></div>
    <div class="stat"><b><?= $stat['devices'] ?></b><span>أجهزة مربوطة</span></div>
    <div class="stat"><b><?= $stat['soon'] ?></b><span>ينتهي خلال 3 أيام</span></div>
  </div>

  <div class="card">
    <h2>إضافة كود</h2>
    <form method="post" autocomplete="off">
      <input type="hidden" name="do" value="add">
      <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
      <div class="row">
        <div style="flex:2"><label>الكود (فارغ = توليد تلقائي)</label>
          <input name="newcode" placeholder="TONGK-XXXX-XXXX-XXXX"></div>
        <div><label>أيام</label><input type="number" name="days" value="30" min="0"></div>
        <div><label>ساعات</label><input type="number" name="hours" value="0" min="0" max="23"></div>
        <div><label>دقائق</label><input type="number" name="mins" value="0" min="0" max="59"></div>
        <div><label>حد الأجهزة</label><input type="number" name="maxdev" value="1" min="1" max="20"></div>
        <div style="flex:0 0 auto;display:flex;align-items:flex-end"><button class="btn">إنشاء</button></div>
      </div>
    </form>
  </div>

  <div class="card">
    <h2>الأكواد (<?= $stat['total'] ?>)</h2>
    <div class="scroll">
    <table>
      <tr>
        <th>الكود</th><th>الحالة</th><th>الانتهاء</th><th>المتبقي</th>
        <th>الأجهزة</th><th>آخر ظهور</th><th>إجراءات</th>
      </tr>
      <?php if (!$db['keys']): ?>
        <tr><td colspan="7" style="text-align:center;color:var(--tx2);padding:26px">لا توجد أكواد — أضف أول كود</td></tr>
      <?php endif; ?>
      <?php foreach ($db['keys'] as $code => $v):
        $st = key_status($v);
        $cls = $st === 'active' ? 'p-act' : ($st === 'expired' ? 'p-exp' : 'p-stp');
        $lbl = $st === 'active' ? 'نشط' : ($st === 'expired' ? 'منتهي' : 'موقوف');
        $exp = (int)strtotime((string)($v['exp'] ?? ''));
        $left = $exp - $now;
        $devs = (array)($v['devices'] ?? []);
        $last = '';
        foreach ($devs as $d) { if (($d['last'] ?? '') > $last) { $last = (string)$d['last']; } }
        $mx = (int)($v['max_devices'] ?? 1);
        $cnt = count((array)($v['hwids'] ?? []));
      ?>
      <tr>
        <td class="code"><?= h($code) ?></td>
        <td><span class="pill <?= $cls ?>"><?= $lbl ?></span></td>
        <td style="color:var(--tx2)"><?= h((string)($v['exp'] ?? '')) ?></td>
        <td style="color:<?= $left > 0 ? 'var(--cy)' : 'var(--err)' ?>"><?= h(fmt_left($left)) ?></td>
        <td><b style="color:var(--gold)"><?= $cnt ?></b> / <?= $mx ?></td>
        <td style="color:var(--tx2);font-size:11px"><?= h($last ?: '—') ?></td>
        <td>
          <div class="act">
            <form method="post" onsubmit="return confirm('حذف الكود؟')">
              <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
              <input type="hidden" name="do" value="delete">
              <input type="hidden" name="code" value="<?= h($code) ?>">
              <button class="btn3">حذف</button>
            </form>
            <form method="post">
              <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
              <input type="hidden" name="do" value="toggle">
              <input type="hidden" name="code" value="<?= h($code) ?>">
              <button class="btn2"><?= empty($v['active']) ? 'تشغيل' : 'إيقاف' ?></button>
            </form>
            <form method="post">
              <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
              <input type="hidden" name="do" value="extend">
              <input type="hidden" name="code" value="<?= h($code) ?>">
              <input type="number" name="days" value="1" min="1" max="365">
              <button class="btn4">+يوم</button>
            </form>
            <form method="post">
              <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
              <input type="hidden" name="do" value="maxdev">
              <input type="hidden" name="code" value="<?= h($code) ?>">
              <input type="number" name="maxdev" value="<?= $mx ?>" min="1" max="20">
              <button class="btn4">حد</button>
            </form>
            <form method="post">
              <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
              <input type="hidden" name="do" value="reset">
              <input type="hidden" name="code" value="<?= h($code) ?>">
              <button class="btn4">فصل</button>
            </form>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
    </table>
    </div>
  </div>

  <div class="foot">@x_Tongk · https://t.me/PythonToolsIQ</div>
<?php endif; ?>
</div>
</body>
</html>
