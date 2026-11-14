<?php
declare(strict_types=1);
session_start();

/* ================== الإعدادات ================== */
const ADMIN_USER    = 'admin';                       
const ADMIN_PASS    = 'JOKER3484649';                
const GITHUB_TOKEN  = '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'; 
const GIST_ID       = 'dbbc4d9af80e06394a12a0a5dcdb5fa9';         
const GIST_FILENAME = 'joker.json';                

/* ================== دوال Gist ================== */
function gistApi(string $method, ?array $body = null): array {
    $url = 'https://api.github.com/gists/' . GIST_ID;
    $headers = [
        'Authorization: Bearer ' . GITHUB_TOKEN,
        'Accept: application/vnd.github+json',
        'User-Agent: JokerUK-Admin',
    ];
    if ($body !== null) {
        $headers[] = 'Content-Type: application/json';
    }
    $ch = curl_init($url);
    if ($body !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_SSL_VERIFYPEER => false
    ]);
    $res = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($code >= 200 && $code < 300) return ['ok'=>true, 'data'=>json_decode((string)$res, true)];
    return ['ok'=>false, 'data'=>null];
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

/* ================== Auth ================== */
$flash = ''; $flashType = 'ok'; $loginErr = '';

if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: index.php'); exit;
}

if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['act']??'')==='login') {
    if ($_POST['user'] === ADMIN_USER && $_POST['pass'] === ADMIN_PASS) {
        $_SESSION['adm_in'] = true;
        header('Location: index.php'); exit;
    }
    $loginErr = 'بيانات الدخول خاطئة';
}

$logged = !empty($_SESSION['adm_in']);

/* ================== Actions ================== */
if ($logged && $_SERVER['REQUEST_METHOD']==='POST') {
    $act = (string)($_POST['act'] ?? ''); 
    $cfg = gistRead() ?? [];
    
    if ($act==='maintenance') {
        $cfg['maintenance'] = ['active'=>!empty($_POST['active']), 'message'=>(string)($_POST['msg']??'')];
        gistWrite($cfg); $flash = 'تم حفظ الصيانة'; $flashType = 'ok';
    }
    elseif ($act==='telegram') {
        $cfg['telegram'] = trim((string)($_POST['telegram']??''));
        gistWrite($cfg); $flash = 'تم حفظ التلغرام'; $flashType = 'ok';
    }
    elseif ($act==='save_key') {
        $bucket = in_array($_POST['bucket']??'', ['keys','resellers','developers'], true) ? $_POST['bucket'] : 'keys';
        $qty = max(1, (int)($_POST['qty']??1));
        $maxDev = max(1, (int)($_POST['max_devices']??1));
        $days = max(0, (int)($_POST['duration_days']??0));
        
        if (!isset($cfg[$bucket])) $cfg[$bucket] = [];
        for ($i=0; $i<$qty; $i++) {
            $key = 'JOKER-' . strtoupper(bin2hex(random_bytes(2))) . '-' . strtoupper(bin2hex(random_bytes(2)));
            $cfg[$bucket][$key] = [
                'created_at' => time(),
                'expires_at' => $days > 0 ? time() + ($days * 86400) : 0,
                'max_devices' => $maxDev,
                'devices' => [],
            ];
        }
        gistWrite($cfg); $flash = "تم توليد $qty مفتاح"; $flashType = 'ok';
    }
    elseif ($act==='del_key') {
        $bucket = (string)($_POST['bucket']??''); $key = (string)($_POST['key']??'');
        if (isset($cfg[$bucket][$key])) {
            unset($cfg[$bucket][$key]);
            gistWrite($cfg); $flash = "تم حذف المفتاح"; $flashType = 'ok';
        }
    }
}

$cfg = $logged ? (gistRead() ?? []) : [];
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
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;700&display=swap" rel="stylesheet">
<style>
body{background:#0a0a0a;color:#f5f5f5;font-family:'Cairo',sans-serif;padding:20px;}
.wrap{max-width:1000px;margin:0 auto;}
.login{max-width:350px;margin:50px auto;background:#151515;border:1px solid #2a2a2a;border-top:3px solid #e11d2e;border-radius:8px;padding:25px;text-align:center;}
input,select{width:100%;padding:10px;margin-bottom:10px;background:#0f0f0f;border:1px solid #2a2a2a;color:#fff;border-radius:5px;font-family:inherit;}
button{background:#e11d2e;color:#fff;border:none;padding:10px 15px;border-radius:5px;cursor:pointer;font-weight:700;width:100%;font-family:inherit;}
.panel{background:#151515;border:1px solid #2a2a2a;border-radius:8px;padding:15px;margin-bottom:15px;}
h2{font-size:1rem;color:#e11d2e;margin-bottom:10px;border-bottom:1px solid #2a2a2a;padding-bottom:5px;}
.flash{padding:10px;border-radius:5px;margin-bottom:10px;font-weight:700;background:rgba(34,197,94,.1);color:#22c55e;border:1px solid #22c55e;}
table{width:100%;border-collapse:collapse;margin-top:10px;}
th,td{padding:8px;text-align:right;border-bottom:1px solid #2a2a2a;font-size:0.85rem;}
th{color:#e11d2e;}
.btn-sm{padding:5px;font-size:0.7rem;width:auto;background:#222;color:#fff;}
.header{display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;}
.logout{color:#e11d2e;text-decoration:none;font-weight:700;}
</style>
</head>
<body>
<div class="wrap">
<?php if (!$logged): ?>
  <div class="login">
    <h1>JOK3R UK</h1>
    <?php if ($loginErr): ?><div style="color:red;margin-bottom:10px;"><?=$loginErr?></div><?php endif;?>
    <form method="post">
      <input type="hidden" name="act" value="login">
      <input type="text" name="user" placeholder="Username" required>
      <input type="password" name="pass" placeholder="Password" required>
      <button type="submit">دخول</button>
    </form>
  </div>
<?php else: ?>
  <div class="header">
    <h1>JOK3R UK — ADMIN</h1>
    <a href="?logout=1" class="logout">خروج</a>
  </div>
  
  <?php if ($flash): ?><div class="flash"><?=$flash?></div><?php endif;?>

  <div class="panel">
    <h2>الصيانة</h2>
    <form method="post">
      <input type="hidden" name="act" value="maintenance">
      <label><input type="checkbox" name="active" value="1" <?=!empty($maint['active'])?'checked':''?>> تفعيل وضع الصيانة</label>
      <input type="text" name="msg" value="<?=htmlspecialchars((string)($maint['message']??''))?>" placeholder="رسالة الصيانة">
      <button type="submit">حفظ</button>
    </form>
  </div>

  <div class="panel">
    <h2>رابط التلغرام</h2>
    <form method="post">
      <input type="hidden" name="act" value="telegram">
      <input type="url" name="telegram" value="<?=htmlspecialchars($tgUrl)?>" placeholder="https://t.me/..." dir="ltr">
      <button type="submit">حفظ</button>
    </form>
  </div>

  <div class="panel">
    <h2>توليد مفاتيح</h2>
    <form method="post">
      <input type="hidden" name="act" value="save_key">
      <select name="bucket">
        <option value="keys">مستخدم عادي</option>
        <option value="resellers">موزع</option>
        <option value="developers">مطور</option>
      </select>
      <input type="number" name="qty" value="1" min="1" placeholder="العدد">
      <input type="number" name="max_devices" value="1" min="1" placeholder="حد الأجهزة">
      <input type="number" name="duration_days" value="0" min="0" placeholder="الأيام (0=دائم)">
      <button type="submit">توليد</button>
    </form>
  </div>

  <div class="panel">
    <h2>المفاتيح الحالية</h2>
    <table>
      <tr><th>المفتاح</th><th>النوع</th><th>الأيام</th><th>أجراء</th></tr>
      <?php foreach (['keys'=>$keys, 'resellers'=>$resellers, 'developers'=>$developers] as $bName => $bData): ?>
        <?php foreach ($bData as $k => $v): ?>
          <tr>
            <td dir="ltr"><?=htmlspecialchars($k)?></td>
            <td><?=$bName?></td>
            <td><?=($v['expires_at']==0)?'دائم':date('Y-m-d', $v['expires_at'])?></td>
            <td>
              <form method="post" style="display:inline;">
                <input type="hidden" name="act" value="del_key">
                <input type="hidden" name="bucket" value="<?=$bName?>">
                <input type="hidden" name="key" value="<?=htmlspecialchars($k)?>">
                <button type="submit" class="btn-sm">حذف</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php endforeach; ?>
    </table>
  </div>

<?php endif; ?>
</div>
</body>
</html>
