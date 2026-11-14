<?php
declare(strict_types=1);

/* ============================================================
   الإعدادات الحساسة — تبقى في السيرفر فقط
   ============================================================ */

// توكن GitHub الشخصي (Fine-grained token بصلاحية gist فقط)
// لا تضعه أبداً في HTML/JS
define('GITHUB_TOKEN', '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');

// معرف الـ Gist (الجزء بعد اسم المستخدم في الرابط)
define('GIST_ID', 'dbbc4d9af80e06394a12a0a5dcdb5fa9');

// اسم الملف داخل الـ Gist
define('GIST_FILENAME', 'jokeruk.json');

// الرابط السري للاشتراك (happ://)
define('SECRET_SUB_URL', 'happ://crypt5/fzvdi0GqgbpCb9EKTPaxPxXc14IxRq0h2uGnr2LLq+nVeWCq1NrmXq3iPWlCoWHZpqcE3BDJqEwGJXAFkwaBykQvR7piE+OozxuuS6N84jmexYmXAErT1B++Euhkv9T6nvssUGIk7FodjnrDAJgWw9a5RL6jxrqML35nM9aW6GI=bD8t2waHjFrS4E5jS/MjU99i0Z+S3vJ1XolNeCsjLbm8QUL2JU/2qMSnp765PK3EYi96EmGVJoZb53GGJ3SF+oes9s2bcuw520BJGs9UWh5kPlVilGsM6b+Jj/+FTsjxZExB2MF+1A1xPJB2dxAbN33lgC4t0GmuR33agdq3x3MSIqvdewwd38U0m+s+opOnaytyS1TgkEvDIT83qYpuKsXJ0dCvfBIIHqWK8dIXn06zbVnwVEXOvmY/EqOy2TWN9yS4pQiVPsMmX0QnWaxDpxsPWgXU3Qw4YoZvb8yzpaJmVzGIjrc2fIgPGmY7MZPWc8lFWF4EqkAaLNN770u8TD+0fk0V4xBPDTWvBWLlABWUM/+DgCGeZCcJF2ySfLE+1JomTf/3uVfMXDRvpWP9zLPWdhAXw6RbBsEaz02+oKGlu1nv9royKb9lt/Nj0S1q36DSkTHCkqN+Du+QjmucNhvuS/ShfBGYaf0gbZLLoNvVzJQVq5/yhBKd7QNZKyYTSiY5boHS6NDIh1y0adx2aYrABSEKI8+ptI/xnIhjxoCEM7GTBfoPzAh3LsfNc7EUtTUI6XUvS0UHHq/mJYitTfKdf8GKrRgOV0WLz9jRoz7MTFqAdL07oK6dDljGzAtpdHTcWwTO8ZmMWglRIQT8Vv7Tx/WAjYY0c8S+uWFzfog=ff');

// رابط التلغرام (يظهر في الواجهة)
define('TELEGRAM_URL', 'https://t.me/Dark_Mod_Owner1');

// اسم الكاش المحلي لتقليل استهلاك GitHub API
define('CACHE_FILE', __DIR__ . '/.gist_cache.json');
define('CACHE_TTL', 30); // ثانية

/* ============================================================
   دوال GitHub Gist
   ============================================================ */

function githubApi(string $method, string $endpoint, ?array $body = null): array {
    $ch = curl_init('https://api.github.com' . $endpoint);
    $headers = [
        'Authorization: Bearer ' . GITHUB_TOKEN,
        'Accept: application/vnd.github+json',
        'X-GitHub-Api-Version: 2022-11-28',
        'User-Agent: DarkMod-Client',
    ];
    if ($body !== null) {
        $headers[] = 'Content-Type: application/json';
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    }
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $res  = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($res === false) return ['ok' => false, 'error' => 'curl_failed', 'code' => 0];
    $json = json_decode($res, true);
    return ['ok' => $code >= 200 && $code < 300, 'code' => $code, 'data' => $json];
}

/**
 * قراءة محتوى الـ Gist (مع كاش محلي)
 */
function getGistContent(bool $forceRefresh = false): ?array {
    if (!$forceRefresh && file_exists(CACHE_FILE)) {
        $age = time() - filemtime(CACHE_FILE);
        if ($age < CACHE_TTL) {
            $cached = json_decode((string)file_get_contents(CACHE_FILE), true);
            if (is_array($cached)) return $cached;
        }
    }

    $res = githubApi('GET', '/gists/' . GIST_ID);
    if (!$res['ok'] || empty($res['data']['files'][GIST_FILENAME]['content'])) {
        // إذا فشل الطلب الخارجي، حاول استخدام الكاش القديم كخطة بديلة
        if (file_exists(CACHE_FILE)) {
            $cached = json_decode((string)file_get_contents(CACHE_FILE), true);
            if (is_array($cached)) return $cached;
        }
        return null;
    }

    $content = json_decode($res['data']['files'][GIST_FILENAME]['content'], true);
    if (!is_array($content)) return null;

    @file_put_contents(CACHE_FILE, json_encode($content), LOCK_EX);
    return $content;
}

/**
 * كتابة محتوى الـ Gist (لتحديث عدّاد الأجهزة أو المفاتيح)
 */
function saveGistContent(array $content): bool {
    $res = githubApi('PATCH', '/gists/' . GIST_ID, [
        'files' => [
            GIST_FILENAME => ['content' => json_encode($content, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)]
        ]
    ]);
    if ($res['ok']) {
        @file_put_contents(CACHE_FILE, json_encode($content), LOCK_EX);
        return true;
    }
    return false;
}