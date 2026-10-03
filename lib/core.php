<?php
// The Amsterdam Forum — shared helpers (content, security, storage).
declare(strict_types=1);

define('AF_ROOT', dirname(__DIR__));
define('AF_DATA', AF_ROOT . '/data');
define('AF_UPLOADS', AF_ROOT . '/uploads');
define('AF_CONTENT', AF_DATA . '/content.json');
define('AF_CONFIG', AF_DATA . '/config.php');
define('AF_SUBMISSIONS', AF_DATA . '/submissions.jsonl');
define('AF_BACKUPS', AF_DATA . '/backups');
define('AF_MAX_UPLOAD', 6 * 1024 * 1024);

function e($v): string { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

function af_content(): array {
    $raw = @file_get_contents(AF_CONTENT);
    $data = $raw ? json_decode($raw, true) : null;
    return is_array($data) ? $data : [];
}

function af_get(array $a, string $path, $default = '') {
    foreach (explode('.', $path) as $k) {
        if (!is_array($a) || !array_key_exists($k, $a)) return $default;
        $a = $a[$k];
    }
    return $a;
}

function af_write_atomic(string $file, string $data): bool {
    $tmp = $file . '.tmp' . bin2hex(random_bytes(4));
    if (file_put_contents($tmp, $data, LOCK_EX) === false) return false;
    return rename($tmp, $file);
}

function af_save_content(array $data): bool {
    if (!is_dir(AF_BACKUPS)) @mkdir(AF_BACKUPS, 0750, true);
    if (is_file(AF_CONTENT)) {
        @copy(AF_CONTENT, AF_BACKUPS . '/content-' . date('Ymd-His') . '.json');
        $all = glob(AF_BACKUPS . '/content-*.json') ?: [];
        sort($all);
        while (count($all) > 30) { @unlink(array_shift($all)); }
    }
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    return $json !== false && af_write_atomic(AF_CONTENT, $json);
}

function af_config(): array {
    if (!is_file(AF_CONFIG)) return [];
    $c = include AF_CONFIG;
    return is_array($c) ? $c : [];
}

function af_save_config(array $c): bool {
    return af_write_atomic(AF_CONFIG, "<?php\nreturn " . var_export($c, true) . ";\n");
}

function af_session(): void {
    if (session_status() === PHP_SESSION_ACTIVE) return;
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    session_name('afadmin');
    session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => $secure, 'httponly' => true, 'samesite' => 'Strict']);
    session_start();
}

function af_csrf(): string {
    af_session();
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(24));
    return $_SESSION['csrf'];
}

function af_check_csrf(?string $t): bool {
    af_session();
    return is_string($t) && !empty($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $t);
}

function af_is_admin(): bool { af_session(); return !empty($_SESSION['admin']); }

function af_json($payload, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function af_client_ip(): string { return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'; }

// Simple file-based rate limit: $max events per $window seconds per key.
function af_rate_ok(string $bucket, int $max, int $window): bool {
    $file = AF_DATA . '/rate-' . preg_replace('/[^a-z0-9_-]/i', '', $bucket) . '.json';
    $now = time();
    $key = hash('sha256', af_client_ip());
    $fh = fopen($file, 'c+');
    if (!$fh) return true;
    flock($fh, LOCK_EX);
    $data = json_decode(stream_get_contents($fh) ?: '{}', true) ?: [];
    foreach ($data as $k => $times) {
        $data[$k] = array_values(array_filter($times, fn($t) => $t > $now - $window));
        if (!$data[$k]) unset($data[$k]);
    }
    $ok = count($data[$key] ?? []) < $max;
    if ($ok) $data[$key][] = $now;
    ftruncate($fh, 0); rewind($fh); fwrite($fh, json_encode($data));
    flock($fh, LOCK_UN); fclose($fh);
    return $ok;
}

// Curated Google Fonts offered in the admin; any other family name can be typed in.
function af_font_choices(): array {
    // The design system uses two families only. Alternatives are listed for experiments; the brand faces are first.
    return [
        'sans'  => ['Schibsted Grotesk', 'Inter Tight', 'IBM Plex Sans', 'Public Sans', 'Work Sans', 'Archivo'],
        'serif' => ['Newsreader', 'Source Serif 4', 'Literata', 'Lora', 'Gelasio', 'EB Garamond'],
    ];
}

function af_google_fonts_url(array $fonts): string {
    $spec = [
        'sans'  => 'ital,wght@0,400;0,500;0,600;0,700',
        'serif' => 'ital,opsz,wght@0,6..72,400;0,6..72,500;1,6..72,400',
    ];
    $fams = []; $seen = [];
    foreach ($fonts as $role => $f) {
        $f = trim((string)$f);
        if ($f === '' || isset($seen[$f]) || !preg_match('/^[A-Za-z0-9 ]{2,60}$/', $f)) continue;
        $seen[$f] = true;
        $fams[] = 'family=' . str_replace(' ', '+', $f) . ($role === 'sans' ? ':wght@400;500;600;700' : ':ital,wght@0,400;0,500;1,400');
    }
    return $fams ? 'https://fonts.googleapis.com/css2?' . implode('&', $fams) . '&display=swap' : '';
}

function af_safe_color(string $c, string $fallback): string {
    return preg_match('/^#[0-9a-fA-F]{3,8}$/', $c) ? $c : $fallback;
}

function af_safe_font(string $f, string $fallback): string {
    return preg_match('/^[A-Za-z0-9 ]{2,60}$/', $f) ? $f : $fallback;
}

// Only allow http(s) and in-page links in content.
function af_url(string $u): string {
    $u = trim($u);
    if ($u === '') return '';
    if ($u[0] === '#' || $u[0] === '/') return $u;
    return preg_match('#^(https?:|mailto:)#i', $u) ? $u : '';
}

// Images stored under /uploads/ only.
function af_img(string $u): string {
    $u = trim($u);
    return preg_match('#^uploads/[A-Za-z0-9._-]+$#', $u) ? $u : '';
}
