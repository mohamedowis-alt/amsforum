<?php
// The Amsterdam Forum — website admin.
declare(strict_types=1);
require dirname(__DIR__) . '/lib/core.php';
require dirname(__DIR__) . '/lib/update.php';
require dirname(__DIR__) . '/lib/signal.php';

header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header('Cache-Control: no-store');

af_session();
$config = af_config();
$action = (string)($_GET['action'] ?? $_POST['action'] ?? '');
$setupFile = AF_DATA . '/SETUP-CODE.txt';

// ---------------------------------------------------------------- first-time setup
if (empty($config['password_hash'])) {
    $err = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'setup') {
        $code = trim((string)@file_get_contents($setupFile));
        $given = trim((string)($_POST['code'] ?? ''));
        $p1 = (string)($_POST['password'] ?? ''); $p2 = (string)($_POST['password2'] ?? '');
        if (!af_rate_ok('setup', 10, 900)) $err = 'Too many attempts. Wait fifteen minutes.';
        elseif ($code === '' || !hash_equals($code, $given)) $err = 'The setup code is not correct. Find it in data/SETUP-CODE.txt on your hosting.';
        elseif (mb_strlen($p1) < 10) $err = 'Use a password of at least 10 characters.';
        elseif ($p1 !== $p2) $err = 'The two passwords do not match.';
        else {
            af_save_config(['password_hash' => password_hash($p1, PASSWORD_DEFAULT), 'created' => gmdate('c')]);
            @unlink($setupFile);
            session_regenerate_id(true);
            $_SESSION['admin'] = true;
            header('Location: ./'); exit;
        }
    }
    auth_page('Set up the admin', 'setup', $err, true);
    exit;
}

// ---------------------------------------------------------------- login / logout
if ($action === 'logout') { $_SESSION = []; session_destroy(); header('Location: ./'); exit; }

if (!af_is_admin()) {
    $err = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'login') {
        if (!af_rate_ok('login', 8, 900)) $err = 'Too many attempts. Wait fifteen minutes.';
        elseif (password_verify((string)($_POST['password'] ?? ''), $config['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['admin'] = true;
            header('Location: ./'); exit;
        } else $err = 'That password is not correct.';
    }
    auth_page('Sign in', 'login', $err, false);
    exit;
}

// ---------------------------------------------------------------- authenticated API
$csrfHeader = $_SERVER['HTTP_X_CSRF'] ?? ($_POST['csrf'] ?? ($_GET['t'] ?? ''));

if ($action !== '') {
    if (!af_check_csrf((string)$csrfHeader)) af_json(['ok' => false, 'error' => 'Your session expired. Reload the page.'], 403);

    switch ($action) {
        case 'save':
            $body = json_decode((string)file_get_contents('php://input'), true);
            if (!is_array($body) || !is_array($body['content'] ?? null)) af_json(['ok' => false, 'error' => 'Nothing to save.'], 400);
            $content = $body['content'];
            if (empty($content['site']) || empty($content['theme']) || empty($content['hero'])) af_json(['ok' => false, 'error' => 'The content is incomplete; it was not saved.'], 422);
            af_json(['ok' => af_save_content($content)]);

        case 'upload':
            $f = $_FILES['file'] ?? null;
            if (!$f || ($f['error'] ?? 1) !== UPLOAD_ERR_OK) af_json(['ok' => false, 'error' => 'The upload failed. Images must be under 6 MB.'], 400);
            if ($f['size'] > AF_MAX_UPLOAD) af_json(['ok' => false, 'error' => 'Images must be under 6 MB.'], 400);
            $info = @getimagesize($f['tmp_name']);
            $types = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp', IMAGETYPE_GIF => 'gif'];
            if (!$info || !isset($types[$info[2]])) af_json(['ok' => false, 'error' => 'Use a JPG, PNG, WebP or GIF image.'], 400);
            $base = strtolower(preg_replace('/[^a-z0-9]+/i', '-', pathinfo((string)$f['name'], PATHINFO_FILENAME)) ?? 'image');
            $base = trim(substr($base, 0, 40), '-') ?: 'image';
            $name = $base . '-' . bin2hex(random_bytes(3)) . '.' . $types[$info[2]];
            if (!is_dir(AF_UPLOADS)) @mkdir(AF_UPLOADS, 0755, true);
            if (!move_uploaded_file($f['tmp_name'], AF_UPLOADS . '/' . $name)) af_json(['ok' => false, 'error' => 'The server could not store the image. Check that the uploads folder is writable.'], 500);
            @chmod(AF_UPLOADS . '/' . $name, 0644);
            af_json(['ok' => true, 'path' => 'uploads/' . $name, 'width' => $info[0], 'height' => $info[1]]);

        case 'submissions':
            af_json(['ok' => true, 'rows' => read_submissions()]);

        case 'delete_submission':
            $id = (string)($_POST['id'] ?? '');
            $rows = array_filter(read_submissions(), fn($r) => ($r['id'] ?? '') !== $id);
            $out = '';
            foreach (array_reverse($rows) as $r) $out .= json_encode($r, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
            af_json(['ok' => af_write_atomic(AF_SUBMISSIONS, $out)]);

        case 'export':
            $kind = (string)($_GET['kind'] ?? '');
            $rows = array_filter(read_submissions(), fn($r) => $kind === '' || ($r['kind'] ?? '') === $kind);
            $cols = ['received', 'kind', 'name', 'email', 'organisation', 'role', 'country', 'domain', 'seats', 'tier', 'speaker', 'format', 'witness', 'shift', 'link', 'note'];
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="amsterdam-forum-' . ($kind ?: 'all') . '-' . date('Y-m-d') . '.csv"');
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $cols);
            foreach ($rows as $r) {
                fputcsv($out, array_map(function ($c) use ($r) {
                    $v = (string)($r[$c] ?? '');
                    return preg_match('/^[=+\-@]/', $v) ? "'" . $v : $v; // stop spreadsheet formula injection
                }, $cols));
            }
            exit;

        case 'backups':
            $files = glob(AF_BACKUPS . '/content-*.json') ?: [];
            rsort($files);
            af_json(['ok' => true, 'backups' => array_map(fn($f) => basename($f), $files)]);

        case 'restore':
            $file = basename((string)($_POST['file'] ?? ''));
            if (!preg_match('/^content-\d{8}-\d{6}\.json$/', $file) || !is_file(AF_BACKUPS . '/' . $file)) af_json(['ok' => false, 'error' => 'Backup not found.'], 404);
            $data = json_decode((string)file_get_contents(AF_BACKUPS . '/' . $file), true);
            if (!is_array($data)) af_json(['ok' => false, 'error' => 'That backup is unreadable.'], 422);
            af_json(['ok' => af_save_content($data)]);

        case 'update_status':
            af_json(['ok' => true, 'settings' => af_update_settings(), 'current' => af_version()]);

        case 'update_settings':
            $repo = trim((string)($_POST['repo'] ?? ''));
            $repo = preg_replace('#^https?://github\.com/#i', '', rtrim($repo, '/'));
            $repo = preg_replace('#\.git$#', '', (string)$repo);
            if ($repo !== '' && !preg_match('#^[\w.-]+/[\w.-]+$#', $repo)) af_json(['ok' => false, 'error' => 'Write the repository as owner/name, for example mohamed/amsforum.'], 422);
            $branch = trim((string)($_POST['branch'] ?? 'main')) ?: 'main';
            if (!preg_match('#^[\w./-]+$#', $branch)) af_json(['ok' => false, 'error' => 'That branch name is not valid.'], 422);
            $config['update_repo'] = $repo; $config['update_branch'] = $branch;
            $tok = trim((string)($_POST['token'] ?? ''));
            if (!empty($_POST['clear_token'])) unset($config['update_token']);
            elseif ($tok !== '') $config['update_token'] = $tok;
            af_json(['ok' => af_save_config($config), 'settings' => af_update_settings()]);

        case 'update_check':
            af_json(af_update_check());

        case 'update_run':
            @set_time_limit(120);
            af_json(af_update_run());

        case 'update_rollback':
            af_json(af_update_rollback());

        // ---------------- Weak Signal
        case 'ws_list':
            ws_remember_base_url();
            [$from, $reply] = ws_from();
            $subs = ws_subscribers();
            af_json(['ok' => true, 'signals' => ws_signals(false), 'subscribers' => $subs,
                'active' => count(ws_active_subscribers()),
                'shifts' => array_values(array_filter(array_map(fn($s) => (string)($s['name'] ?? ''), (array)af_get(af_content(), 'evidence.shifts', [])))),
                'statuses' => AF_SIGNAL_STATUSES, 'plans' => AF_SIGNAL_PLANS, 'parts' => AF_SIGNAL_PARTS,
                'settings' => ['from' => $from, 'reply_to' => $reply, 'custom_from' => (string)($config['signal_from'] ?? '')],
                'leads' => count(array_filter(read_submissions(), fn($r) => ($r['kind'] ?? '') === 'weak-signal'))]);

        case 'ws_save_signal':
            $body = json_decode((string)file_get_contents('php://input'), true);
            af_json(ws_save_signal(is_array($body['signal'] ?? null) ? $body['signal'] : []));

        case 'ws_delete_signal':
            af_json(['ok' => ws_delete_signal((string)($_POST['id'] ?? ''))]);

        case 'ws_test':
            $sig = null; foreach (ws_signals(false) as $x) if ($x['id'] === ($_POST['id'] ?? '')) $sig = $x;
            $to = trim((string)($_POST['email'] ?? ''));
            if (!$sig) af_json(['ok' => false, 'error' => 'Save the signal first.'], 404);
            if (!filter_var($to, FILTER_VALIDATE_EMAIL)) af_json(['ok' => false, 'error' => 'Enter a valid email address for the test.'], 422);
            af_json(['ok' => ws_send_signal($sig, $to), 'error' => 'The server could not send the email. Check PHP mail with your host.']);

        case 'ws_send':
            @set_time_limit(120);
            $sig = null; foreach (ws_signals(false) as $x) if ($x['id'] === ($_POST['id'] ?? '')) $sig = $x;
            if (!$sig) af_json(['ok' => false, 'error' => 'Signal not found.'], 404);
            if (empty($sig['published'])) af_json(['ok' => false, 'error' => 'Publish the signal before sending it.'], 422);
            $list = ws_active_subscribers();
            $offset = max(0, (int)($_POST['offset'] ?? 0));
            $batch = array_slice($list, $offset, 20);
            $sent = 0; $failed = [];
            foreach ($batch as $sub) { if (ws_send_signal($sig, $sub['email'])) $sent++; else $failed[] = $sub['email']; usleep(150000); }
            $next = $offset + count($batch);
            $done = $next >= count($list);
            if ($done) ws_mark_sent($sig['id'], count($list));
            af_json(['ok' => true, 'sent' => $sent, 'failed' => $failed, 'next' => $next, 'total' => count($list), 'done' => $done]);

        case 'ws_save_subscriber':
            af_json(ws_save_subscriber($_POST));

        case 'ws_delete_subscriber':
            af_json(['ok' => ws_delete_subscriber((string)($_POST['email'] ?? ''))]);

        case 'ws_import':
            $lines = preg_split('/[\r\n]+/', (string)($_POST['lines'] ?? '')) ?: [];
            if (!empty($_POST['leads'])) foreach (read_submissions() as $r) if (($r['kind'] ?? '') === 'weak-signal' && !empty($r['email'])) $lines[] = $r['email'];
            $added = 0; $skipped = 0;
            foreach ($lines as $line) {
                $parts = array_map('trim', str_getcsv($line));
                $email = ws_norm_email($parts[0] ?? '');
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { if (trim($line) !== '') $skipped++; continue; }
                if (ws_subscriber($email)) { $skipped++; continue; }
                $r = ws_save_subscriber(['email' => $email, 'name' => $parts[1] ?? '', 'plan' => $_POST['plan'] ?? 'individual', 'status' => 'active', 'expires' => $_POST['expires'] ?? '']);
                if ($r['ok']) $added++;
            }
            af_json(['ok' => true, 'added' => $added, 'skipped' => $skipped]);

        case 'ws_settings':
            $from = trim((string)($_POST['from'] ?? '')); $reply = trim((string)($_POST['reply_to'] ?? ''));
            if ($from !== '' && !filter_var($from, FILTER_VALIDATE_EMAIL)) af_json(['ok' => false, 'error' => 'The sender address is not valid.'], 422);
            if ($reply !== '' && !filter_var($reply, FILTER_VALIDATE_EMAIL)) af_json(['ok' => false, 'error' => 'The reply-to address is not valid.'], 422);
            $config['signal_from'] = $from; $config['signal_reply_to'] = $reply;
            af_json(['ok' => af_save_config($config)]);

        case 'password':
            $cur = (string)($_POST['current'] ?? ''); $n1 = (string)($_POST['new'] ?? ''); $n2 = (string)($_POST['new2'] ?? '');
            if (!password_verify($cur, $config['password_hash'])) af_json(['ok' => false, 'error' => 'The current password is not correct.'], 403);
            if (mb_strlen($n1) < 10) af_json(['ok' => false, 'error' => 'Use at least 10 characters.'], 422);
            if ($n1 !== $n2) af_json(['ok' => false, 'error' => 'The new passwords do not match.'], 422);
            $config['password_hash'] = password_hash($n1, PASSWORD_DEFAULT);
            af_json(['ok' => af_save_config($config)]);
    }
    af_json(['ok' => false, 'error' => 'Unknown action.'], 400);
}

function read_submissions(): array {
    $rows = [];
    if (is_file(AF_SUBMISSIONS)) {
        foreach (file(AF_SUBMISSIONS, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            $r = json_decode($line, true);
            if (is_array($r)) $rows[] = $r;
        }
    }
    return array_reverse($rows);
}

function auth_page(string $title, string $action, string $err, bool $setup): void {
    ?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex">
<title><?= e($title) ?> · Amsterdam Forum admin</title><link rel="stylesheet" href="admin.css"></head>
<body class="auth"><form method="post" class="auth-box" autocomplete="off">
<img src="../assets/logo/af-horizontal-ink.svg" alt="Amsterdam Forum" style="height:26px;width:auto;margin-bottom:10px"><p class="kicker">Website admin</p><h1><?= e($title) ?></h1>
<?php if ($err): ?><p class="error" role="alert"><?= e($err) ?></p><?php endif; ?>
<input type="hidden" name="action" value="<?= e($action) ?>">
<?php if ($setup): ?>
<p class="help">Choose the admin password. To prove you own this hosting, enter the code in <code>data/SETUP-CODE.txt</code> (open it with your host's file manager).</p>
<label for="code">Setup code</label><input id="code" name="code" required>
<label for="pw">New password (10+ characters)</label><input id="pw" type="password" name="password" required minlength="10" autocomplete="new-password">
<label for="pw2">Repeat password</label><input id="pw2" type="password" name="password2" required minlength="10" autocomplete="new-password">
<button type="submit">Create admin password</button>
<?php else: ?>
<label for="pw">Password</label><input id="pw" type="password" name="password" required autofocus autocomplete="current-password">
<button type="submit">Sign in</button>
<?php endif; ?>
</form></body></html><?php
}

// ---------------------------------------------------------------- the editor shell
$boot = [
    'content' => af_content(),
    'csrf' => af_csrf(),
    'fonts' => af_font_choices(),
];
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>Website admin · Amsterdam Forum</title>
<link rel="icon" href="../assets/logo/af-favicon.svg" type="image/svg+xml">
<link rel="stylesheet" href="admin.css?v=<?= @filemtime(__DIR__ . '/admin.css') ?>">
</head>
<body>
<header class="bar">
  <div class="bar-l"><img src="../assets/logo/af-mark-reversed.svg" alt="" width="22" height="22" style="align-self:center"><b>Amsterdam Forum</b><span>Website admin</span></div>
  <nav class="views" id="views" aria-label="Admin sections"></nav>
  <div class="bar-r">
    <span id="status" class="status" role="status"></span>
    <a class="ghost" href="../" target="_blank" rel="noopener">View site</a>
    <button id="save" class="primary" disabled>Save changes</button>
    <a class="ghost" href="?action=logout">Sign out</a>
  </div>
</header>
<div class="layout">
  <aside id="sidenav" aria-label="Page sections"></aside>
  <main id="main"></main>
</div>
<script type="application/json" id="boot"><?= json_encode($boot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
<script src="admin.js?v=<?= @filemtime(__DIR__ . '/admin.js') ?>"></script>
</body>
</html>
