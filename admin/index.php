<?php
declare(strict_types=1);

/* ============================================================
   JOK3R UK — ADMIN PANEL (Fixed Version)
   ============================================================ */

/* ================== الإعدادات (عدّلها) ================== */
const ADMIN_USER    = 'admin';                       
const ADMIN_PASS    = 'JOKER3484649';                
const GITHUB_TOKEN  = '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'; 
const GIST_ID       = 'dbbc4d9af80e06394a12a0a5dcdb5fa9';         
const GIST_FILENAME = 'joker.json';                

/* ================== الجلسة ================== */
session_set_cookie_params([
    'lifetime' => 0, 'path' => '/',
    'secure'   => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'httponly' => true, 'samesite' => 'Strict',
]);
session_start();

/* ================== دوال Gist ================== */
function gistApi(string $method, ?array $body = null): array {
    $url = 'https://api.github.com/gists/' . GIST_ID;
    $headers = [
        'Authorization: Bearer ' . GITHUB_TOKEN,
        'Accept: application/vnd.github+json',
        'X-GitHub-Api-Version: 2022-11-28',
        'User-Agent: JokerUK-Admin',
    ];
    if ($body !== null) {
        $headers[] = 'Content-Type: application/json';
    }
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        if ($body !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false
        ]);
        $res = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($code >= 200 && $code < 300) return ['ok'=>true, 'data'=>json_decode((string)$res, true)];
    }
    $options = [
        'http' => [
            'method' => $method, 'header' => implode("\r\n", $headers),
            'content' => $body !== null ? json_encode($body) : null,
            'timeout' => 15, 'ignore_errors' => true
        ],
        'ssl' => ['verify_peer' => false, 'verify_peer_name' => false]
    ];
    $context = stream_context_create($options);
    $res = @file_get_contents($url, false, $context);
    $code = 0;
    if (isset($http_response_header[0])) {
        preg_match('{HTTP\/\S*\s(\d{3})}', $http_response_header[0], $match);
        $code = (int)($match[1] ?? 0);
    }
    return ['ok' => $code >= 200 && $code < 300, 'data' => $res ? json_decode($res, true) : null];
}

function gistRead(): ?array {
    $r = gistApi('GET');
    if (!$r['ok'] || empty($r['data']['files'][GIST_FILENAME]['content'])) return null;
    $c = json_decode($r['data']['files'][GIST_FILENAME]['content'], true);
    return is_array($c) ? $c : [];
}

function gistWrite(array $content): bool {
    $r = gistApi('PATCH', ['files' => [GIST_FILENAME => ['content' => json_encode($content, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)]]]);
    return $r['ok'];
}

/* ================== CSRF ================== */
if (empty($_SESSION['adm_csrf'])) $_SESSION['adm_csrf'] = bin2hex(random_bytes(32));
function csrfOk(): bool { return hash_equals($_SESSION['adm_csrf'], (string)($_POST['csrf'] ?? '')); }

/* ================== Auth ================== */
$flash = ''; $flashType = 'ok'; $loginErr = '';

if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: ' . strtok($_SERVER["REQUEST_URI"], '?'));
    exit;
}

if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['act']??'')==='login') {
    $u = (string)($_POST['user'] ?? ''); $p = (string)($_POST['pass'] ?? '');
    if (hash_equals(ADMIN_USER, $u) && hash_equals(ADMIN_PASS, $p)) {
        $_SESSION['adm_in'] = true;
        header('Location: ' . strtok($_SERVER["REQUEST_URI"], '?')); exit;
    }
    $loginErr = 'بيانات الدخول خاطئة';
}

$logged = !empty($_SESSION['adm_in']);

/* ================== Actions ================== */
if ($logged && $_SERVER['REQUEST_METHOD']==='POST' && ($_POST['act']??'')!=='login') {
    if (!csrfOk()) { $flash='CSRF غير صالح'; $flashType='err'; }
    else {
        $act = (string)($_POST['act'] ?? ''); $cfg = gistRead();
        if ($cfg === null) $cfg = ['keys'=>[], 'resellers'=>[], 'developers'=>[], 'maintenance'=>['active'=>false,'message'=>''], 'telegram'=>''];
        
        if ($act==='maintenance') {
            $on = !empty($_POST['active']);
            $cfg['maintenance'] = ['active'=>$on, 'message'=>(string)($_POST['msg']??'')];
            $ok = gistWrite($cfg);
            $flash = $ok ? ($on?'تم تفعيل الصيانة':'تم إيقاف الصيانة') : 'فشل الحفظ'; $flashType = $ok ? 'ok' : 'err';
        }
        elseif ($act==='telegram') {
            $cfg['telegram'] = trim((string)($_POST['telegram']??''));
            $ok = gistWrite($cfg); $flash = $ok ? 'تم تحديث التلغرام' : 'فشل الحفظ'; $flashType = $ok ? 'ok' : 'err';
        }
        elseif ($act==='save_key') {
            $bucket = in_array($_POST['bucket']??'', ['keys','resellers','developers'], true) ? $_POST['bucket'] : 'keys';
            $prefix = strtoupper(trim((string)($_POST['prefix']??'JOKER')));
            $qty    = max(1, (int)($_POST['qty']??1));
            $days    = max(0, (int)($_POST['duration_days']??0));
            $hours   = max(0, (int)($_POST['duration_hours']??0));
            $minutes = max(0, (int)($_POST['duration_minutes']??0));
            $maxDev  = max(1, (int)($_POST['max_devices']??1));
            $duration_seconds = ($days * 86400) + ($hours * 3600) + ($minutes * 60);
            $created = time();
            $expires = ($duration_seconds > 0) ? ($created + $duration_seconds) : 0;
            
            if (!isset($cfg[$bucket]) || !is_array($cfg[$bucket])) $cfg[$bucket]=[];
            $generated = [];
            for ($i=0; $i<$qty; $i++) {
                $rand = strtoupper(bin2hex(random_bytes(4))); 
                $key = $prefix . '-' . substr($rand,0,4) . '-' . substr($rand,4,4);
                $cfg[$bucket][$key] = ['created_at'=>$created, 'duration_days'=>$days, 'expires_at'=>$expires, 'max_devices'=>$maxDev, 'devices'=>[]];
                if ($bucket==='developers') $cfg[$bucket][$key]['role']='developer';
                $generated[] = $key;
            }
            $ok = gistWrite($cfg);
            if ($ok) {
                $flash = "تم إنشاء " . count($generated) . " مفتاح بنجاح";
                if (count($generated) <= 5) $flash .= ": " . implode(' | ', $generated);
                $flashType = 'ok';
            } else { $flash = 'فشل الحفظ في Gist'; $flashType = 'err'; }
        }
        elseif ($act==='del_key') {
            $bucket = (string)($_POST['bucket']??''); $key = strtoupper(trim((string)($_POST['key']??'')));
            if (isset($cfg[$bucket][$key])) {
                unset($cfg[$bucket][$key]); $ok = gistWrite($cfg);
                $flash = $ok ? "تم حذف المفتاح: $key" : 'فشل الحذف'; $flashType = $ok ? 'ok' : 'err';
            }
        }
        elseif ($act==='reset_devices') {
            $bucket = (string)($_POST['bucket']??''); $key = strtoupper(trim((string)($_POST['key']??'')));
            if (isset($cfg[$bucket][$key])) {
                $cfg[$bucket][$key]['devices'] = []; $ok = gistWrite($cfg);
                $flash = $ok ? "تم تصفير أجهزة: $key" : 'فشل'; $flashType = $ok ? 'ok' : 'err';
            }
        }
    }
}

$cfg = $logged ? gistRead() : null;
if ($cfg === null && $logged) $cfg = [];
$maint = $cfg['maintenance'] ?? ['active'=>false,'message'=>''];
$tgUrl = $cfg['telegram'] ?? '';
$keys = $cfg['keys'] ?? [];
$resellers = $cfg['resellers'] ?? [];
$developers = $cfg['developers'] ?? [];
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>JOK3R UK — Admin</title>
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;900&family=JetBrains+Mono:wght@400;700&display=swap" rel="stylesheet">
<style>
:root { --bg: #0a0a0a; --card: #151515; --border: #2a2a2a; --text: #f5f5f5; --muted: #8a8a8a; --red: #e11d2e; --success: #22c55e; --error: #ef4444; --warning: #f59e0b; --font-ar: 'Cairo', sans-serif; --font-mono: 'JetBrains Mono', monospace; }
*{margin:0;padding:0;box-sizing:border-box;font-family:var(--font-ar);}
body{background:var(--bg);color:var(--text);min-height:100dvh;padding:20px;background-image:linear-gradient(rgba(225,29,46,.03) 1px,transparent 1px),linear-gradient(90deg,rgba(225,29,46,.03) 1px,transparent 1px);background-size:44px 44px;}
.wrap{max-width:1200px;margin:0 auto;} a{text-decoration:none;}
.login{max-width:380px;margin:80px auto;background:var(--card);border:1px solid var(--border);border-top:3px solid var(--red);border-radius:12px;padding:32px;}
.login h1{font-size:1.8rem;text-align:center;margin-bottom:6px;color:var(--red);font-weight:900;}
.login .sub{text-align:center;color:var(--muted);font-size:.8rem;margin-bottom:24px;}
.login label{display:block;font-size:.75rem;color:var(--red);margin-bottom:6px;font-weight:700;}
.login input{width:100%;padding:12px;background:#0f0f0f;border:1px solid var(--border);border-radius:6px;color:#fff;margin-bottom:16px;}
.btn{display:inline-block;padding:10px 18px;border:none;border-radius:6px;cursor:pointer;font-weight:700;font-size:.85rem;}
.btn-primary{background:var(--red);color:#fff;width:100%;padding:14px;font-size:1rem;}
.btn-sm{padding:6px 12px;font-size:.75rem;} .btn-danger{background:rgba(239,68,68,.1);color:#fca5a5;border:1px solid rgba(239,68,68,.4);} .btn-warn{background:rgba(245,158,11,.1);color:#fcd34d;border:1px solid rgba(245,158,11,.4);}
.flash{padding:12px 16px;border-radius:8px;margin-bottom:16px;font-size:.85rem;font-weight:700;} .flash.ok{background:rgba(34,197,94,.1);border:1px solid rgba(34,197,94,.4);color:#86efac;} .flash.err{background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.4);color:#fca5a5;}
.header{display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;padding-bottom:14px;border-bottom:1px solid var(--border);}
.header h1{font-size:1.4rem;color:#fff;} .header h1 span{color:var(--red);}
.badge{background:#1f1f1f;border:1px solid var(--border);color:var(--muted);padding:4px 10px;border-radius:20px;font-size:.7rem;font-family:var(--font-mono);margin-top:6px;display:inline-block;}
.logout{background:rgba(225,29,46,.1);color:var(--red);border:1px solid rgba(225,29,46,.4);padding:8px 14px;border-radius:6px;font-size:.75rem;font-weight:700;}
.panel{background:var(--card);border:1px solid var(--border);border-radius:10px;padding:20px;margin-bottom:16px;}
.panel h2{font-size:.95rem;margin-bottom:16px;color:#fff;display:flex;align-items:center;gap:8px;}
.dot{width:8px;height:8px;background:var(--red);border-radius:50%;}
.stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:12px;}
.stat{background:#0f0f0f;border:1px solid var(--border);border-radius:8px;padding:16px;text-align:center;}
.stat .n{font-size:1.8rem;font-weight:800;font-family:var(--font-mono);color:var(--red);}
.stat .l{font-size:.7rem;color:var(--muted);margin-top:4px;font-weight:700;}
.field{margin-bottom:12px;} .field label{display:block;font-size:.75rem;color:var(--muted);margin-bottom:6px;font-weight:700;}
.field input,.field select{width:100%;padding:10px 12px;background:#0f0f0f;border:1px solid var(--border);border-radius:6px;color:#fff;font-size:.85rem;}
.toggle{display:flex;align-items:center;gap:10px;margin-bottom:12px;} .toggle input{width:20px;height:20px;cursor:pointer;accent-color:var(--red);}
.grid-4{display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:12px;}
.tbl{width:100%;border-collapse:collapse;font-size:.82rem;} .tbl th{background:#1a1a1a;padding:12px 10px;text-align:right;font-size:.75rem;color:var(--red);border-bottom:2px solid var(--border);} .tbl td{padding:12px 10px;border-bottom:1px solid #1f1f1f;} .tbl tr:hover{background:#121212;}
.key{font-family:var(--font-mono);font-size:.8rem;color:var(--white);font-weight:700;direction:ltr;text-align:right;}
.expired{color:var(--error);font-weight:700;} .active{color:var(--success);font-weight:700;} .never{color:var(--warning);font-weight:700;} .actions{display:flex;gap:6px;flex-wrap:wrap;} .muted{color:var(--muted);font-size:.8rem;}
</style>
</head>
<body>
<div class="wrap">

<?php if (!$logged): ?>
  <div class="login">
    <h1>JOK3R UK</h1><div class="sub">ADMIN PANEL</div>
    <?php if ($loginErr): ?><div class="flash err"><?=htmlspecialchars($loginErr)?></div><?php endif;?>
    <form method="post" autocomplete="off">
      <input type="hidden" name="act" value="login">
      <label>USERNAME</label><input type="text" name="user" required autofocus>
      <label>PASSWORD</label><input type="password" name="pass" required>
      <button class="btn btn-primary" type="submit">تسجيل الدخول</button>
    </form>
  </div>
<?php else: ?>

  <div class="header">
    <div><h1>JOK3R <span>UK</span> — ADMIN</h1><div class="badge">Gist: <?=htmlspecialchars(substr(GIST_ID,0,10))?>…</div></div>
    <a href="?logout=1" class="logout">خروج</a>
  </div>

  <?php if ($flash): ?><div class="flash <?=$flashType?>"><?=htmlspecialchars($flash)?></div><?php endif;?>

  <div class="panel">
    <h2><span class="dot"></span>نظرة عامة</h2>
    <div class="stats">
      <div class="stat"><div class="n"><?=count($keys)?></div><div class="l">مستخدمون</div></div>
      <div class="stat"><div class="n"><?=count($resellers)?></div><div class="l">موزعون</div></div>
      <div class="stat"><div class="n"><?=count($developers)?></div><div class="l">مطورون</div></div>
      <div class="stat"><div class="n"><?=count($keys)+count($resellers)+count($developers)?></div><div class="l">المجموع</div></div>
    </div>
  </div>

  <div class="panel">
    <h2><span class="dot"></span>الصيانة</h2>
    <form method="post">
      <input type="hidden" name="csrf" value="<?=$_SESSION['adm_csrf']?>"><input type="hidden" name="act" value="maintenance">
      <div class="toggle"><input type="checkbox" id="m" name="active" value="1" <?=!empty($maint['active'])?'checked':''?>><label for="m">تفعيل الصيانة</label></div>
      <div class="field"><label>رسالة الصيانة</label><input type="text" name="msg" value="<?=htmlspecialchars((string)($maint['message']??''))?>"></div>
      <button class="btn btn-primary" type="submit" style="width:auto;">حفظ</button>
    </form>
  </div>

  <div class="panel">
    <h2><span class="dot"></span>رابط التلغرام</h2>
    <form method="post">
      <input type="hidden" name="csrf" value="<?=$_SESSION['adm_csrf']?>"><input type="hidden" name="act" value="telegram">
      <div class="field"><label>TELEGRAM URL</label><input type="url" name="telegram" value="<?=htmlspecialchars($tgUrl)?>" placeholder="https://t.me/..." style="direction:ltr;"></div>
      <button class="btn btn-primary" type="submit" style="width:auto;">حفظ</button>
    </form>
  </div>

  <div class="panel" style="border-right:3px solid var(--red);">
    <h2><span class="dot"></span>توليد مفاتيح جديدة</h2>
    <form method="post">
      <input type="hidden" name="csrf" value="<?=$_SESSION['adm_csrf']?>"><input type="hidden" name="act" value="save_key">
      <div class="grid-4" style="margin-bottom:16px;">
        <div class="field"><label>النوع</label><select name="bucket"><option value="keys">مستخدم</option><option value="resellers">موزع</option><option value="developers">مطور</option></select></div>
        <div class="field"><label>بادئة المفتاح</label><input type="text" name="prefix" value="JOKER" maxlength="10"></div>
        <div class="field"><label>العدد</label><input type="number" name="qty" value="1" min="1" max="100"></div>
        <div class="field"><label>حد الأجهزة</label><input type="number" name="max_devices" value="1" min="1"></div>
      </div>
      <div class="grid-4" style="margin-bottom:16px;">
        <div class="field"><label>أيام</label><input type="number" name="duration_days" value="0" min="0"></div>
        <div class="field"><label>ساعات</label><input type="number" name="duration_hours" value="0" min="0" max="23"></div>
        <div class="field"><label>دقائق</label><input type="number" name="duration_minutes" value="0" min="0" max="59"></div>
      </div>
      <button class="btn btn-primary" type="submit" style="width:auto;">توليد المفاتيح</button>
    </form>
  </div>

  <div class="panel">
    <h2><span class="dot"></span>المفاتيح الحالية</h2>
    <?php if (empty($keys) && empty($resellers) && empty($developers)): ?>
      <p class="muted">لا توجد مفاتيح حتى الآن.</p>
    <?php else: ?>
      <table class="tbl">
        <thead><tr><th>المفتاح</th><th>النوع</th><th>الأجهزة</th><th>الانتهاء</th><th>إجراءات</th></tr></thead>
        <tbody>
          <?php 
          $allBuckets = ['keys'=>$keys, 'resellers'=>$resellers, 'developers'=>$developers];
          foreach ($allBuckets as $bName => $bData): 
            foreach ($bData as $k => $v): 
              $exp = $v['expires_at'] ?? 0;
              $isExp = ($exp > 0 && $exp < time());
          ?>
          <tr>
            <td class="key"><?=htmlspecialchars((string)$k)?></td>
            <td><?=htmlspecialchars($bName)?></td>
            <td><?=count($v['devices'] ?? [])?> / <?=htmlspecialchars((string)($v['max_devices']??1))?></td>
            <td><?php if ($exp == 0): ?><span class="never">دائم</span><?php elseif ($isExp): ?><span class="expired">منتهي</span><?php else: ?><span class="active"><?=date('Y-m-d', $exp)?></span><?php endif; ?></td>
            <td class="actions">
              <form method="post" style="display:inline;"><input type="hidden" name="csrf" value="<?=$_SESSION['adm_csrf']?>"><input type="hidden" name="act" value="del_key"><input type="hidden" name="bucket" value="<?=$bName?>"><input type="hidden" name="key" value="<?=htmlspecialchars((string)$k)?>"><button class="btn btn-danger btn-sm" type="submit">حذف</button></form>
              <form method="post" style="display:inline;"><input type="hidden" name="csrf" value="<?=$_SESSION['adm_csrf']?>"><input type="hidden" name="act" value="reset_devices"><input type="hidden" name="bucket" value="<?=$bName?>"><input type="hidden" name="key" value="<?=htmlspecialchars((string)$k)?>"><button class="btn btn-warn btn-sm" type="submit">تصفير</button></form>
            </td>
          </tr>
          <?php endforeach; endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>

</div>
</body>
</html>
