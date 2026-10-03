<?php
// The Amsterdam Forum — one-click updates from GitHub.
// Pulls the site code from a GitHub repository, keeps everything the admin owns
// (content edits, images, password, submissions, .htaccess) and merges new
// content sections without overwriting text that was edited in the admin.
declare(strict_types=1);

define('AF_APPLIED', AF_DATA . '/content.applied.json'); // the defaults last applied, used to tell edits apart
define('AF_VERSION', AF_DATA . '/version.json');

// Files an update may write. Everything else (root .htaccess, data, uploads) is never touched.
const AF_UPDATE_ALLOW = '#^(index\.php|robots\.txt|assets/.+|admin/.+|api/.+|lib/.+|uploads/\.htaccess|data/\.htaccess|data/index\.php|data/backups/\.htaccess)$#';

function af_update_settings(): array {
    $c = af_config();
    return ['repo' => (string)($c['update_repo'] ?? ''), 'branch' => (string)($c['update_branch'] ?? 'main'), 'has_token' => !empty($c['update_token'])];
}

function af_version(): array {
    $v = @json_decode((string)@file_get_contents(AF_VERSION), true);
    return is_array($v) ? $v : [];
}

function af_http_get(string $url, string $token, bool $binary = false): array {
    $headers = ['User-Agent: AmsterdamForumSite', 'Accept: ' . ($binary ? 'application/octet-stream' : 'application/vnd.github+json'), 'X-GitHub-Api-Version: 2022-11-28'];
    if ($token !== '') $headers[] = 'Authorization: Bearer ' . $token;
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 5,
            CURLOPT_HTTPHEADER => $headers, CURLOPT_TIMEOUT => 60, CURLOPT_CONNECTTIMEOUT => 15]);
        $body = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        return [$code, $body === false ? '' : (string)$body, $err];
    }
    $ctx = stream_context_create(['http' => ['header' => implode("\r\n", $headers), 'timeout' => 60, 'ignore_errors' => true, 'follow_location' => 1]]);
    $body = @file_get_contents($url, false, $ctx);
    $code = 0;
    foreach ($http_response_header ?? [] as $h) if (preg_match('#^HTTP/\S+\s+(\d+)#', $h, $m)) $code = (int)$m[1];
    return [$code, $body === false ? '' : (string)$body, $body === false ? 'Could not connect.' : ''];
}

function af_gh_error(int $code, string $err): string {
    if ($code === 401 || $code === 403) return 'GitHub refused access. Check the access token in Updates.';
    if ($code === 404) return 'Repository not found. Check its name, and the token if the repository is private.';
    return 'Could not reach GitHub' . ($err ? ' (' . $err . ')' : '') . '. Try again in a minute.';
}

function af_update_check(): array {
    $c = af_config();
    $repo = (string)($c['update_repo'] ?? ''); $branch = (string)($c['update_branch'] ?? 'main');
    if (!preg_match('#^[\w.-]+/[\w.-]+$#', $repo)) return ['ok' => false, 'error' => 'Set the repository first (owner/name).'];
    [$code, $body, $err] = af_http_get("https://api.github.com/repos/$repo/commits/" . rawurlencode($branch), (string)($c['update_token'] ?? ''));
    if ($code !== 200) return ['ok' => false, 'error' => af_gh_error($code, $err)];
    $j = json_decode($body, true);
    $latest = ['sha' => (string)($j['sha'] ?? ''), 'date' => (string)($j['commit']['committer']['date'] ?? ''), 'message' => strtok((string)($j['commit']['message'] ?? ''), "\n")];
    $cur = af_version();
    return ['ok' => true, 'latest' => $latest, 'current' => $cur, 'available' => ($cur['sha'] ?? '') !== $latest['sha']];
}

function af_is_assoc($a): bool {
    if (!is_array($a) || $a === []) return false;
    return array_keys($a) !== range(0, count($a) - 1);
}

// Three-way merge: $cur = live content, $old = defaults last applied, $new = incoming defaults.
// Anything the admin changed since the last update is kept; everything else follows the update.
function af_merge($cur, $old, $new) {
    if (af_is_assoc($new) && af_is_assoc($cur)) {
        $out = [];
        foreach ($new as $k => $v) {
            $out[$k] = array_key_exists($k, $cur) ? af_merge($cur[$k], is_array($old) && array_key_exists($k, $old) ? $old[$k] : null, $v) : $v;
        }
        foreach ($cur as $k => $v) {
            if (array_key_exists($k, $out)) continue;
            $wasDefault = is_array($old) && array_key_exists($k, $old) && $old[$k] == $v;
            if (!$wasDefault) $out[$k] = $v; // keep anything the admin added or changed
        }
        return $out;
    }
    if ($old === null) return $cur; // no record of the last defaults: never overwrite
    return $cur == $old ? $new : $cur;
}

function af_safe_rel(string $rel): bool {
    return $rel !== '' && strpos($rel, '..') === false && strpos($rel, '\\') === false && strpos($rel, "\0") === false && $rel[0] !== '/';
}

function af_write_file(string $rel, string $data): bool {
    $path = AF_ROOT . '/' . $rel;
    $dir = dirname($path);
    if (!is_dir($dir) && !@mkdir($dir, 0755, true)) return false;
    $tmp = $path . '.tmp' . bin2hex(random_bytes(3));
    if (@file_put_contents($tmp, $data) === false) return false;
    @chmod($tmp, 0644);
    return @rename($tmp, $path);
}

function af_backup_code(array $rels): ?string {
    if (!class_exists('ZipArchive')) return null;
    if (!is_dir(AF_BACKUPS)) @mkdir(AF_BACKUPS, 0750, true);
    $file = AF_BACKUPS . '/code-' . date('Ymd-His') . '.zip';
    $z = new ZipArchive();
    if ($z->open($file, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) return null;
    foreach (array_merge($rels, ['data/content.json', 'data/content.applied.json', 'data/version.json']) as $rel) {
        if (is_file(AF_ROOT . '/' . $rel)) $z->addFile(AF_ROOT . '/' . $rel, $rel);
    }
    $z->close();
    $all = glob(AF_BACKUPS . '/code-*.zip') ?: [];
    sort($all);
    while (count($all) > 5) @unlink(array_shift($all));
    return $file;
}

function af_update_run(): array {
    if (!class_exists('ZipArchive')) return ['ok' => false, 'error' => 'This hosting has no ZIP support for PHP. Ask the host to enable the "zip" extension.'];
    $check = af_update_check();
    if (!$check['ok']) return $check;
    $c = af_config();
    $repo = (string)$c['update_repo']; $sha = $check['latest']['sha'];
    [$code, $body, $err] = af_http_get("https://api.github.com/repos/$repo/zipball/$sha", (string)($c['update_token'] ?? ''), true);
    if ($code !== 200 || strlen($body) < 100) return ['ok' => false, 'error' => af_gh_error($code, $err)];

    $tmp = AF_DATA . '/update-' . bin2hex(random_bytes(4)) . '.zip';
    file_put_contents($tmp, $body);
    $r = af_update_apply($tmp, $check['latest']);
    @unlink($tmp);
    return $r;
}

function af_update_apply(string $tmp, array $latest): array {
    $z = new ZipArchive();
    if ($z->open($tmp) !== true) { return ['ok' => false, 'error' => 'The download was damaged. Try again.']; }

    $files = []; $defaults = null;
    for ($i = 0; $i < $z->numFiles; $i++) {
        $name = (string)$z->getNameIndex($i);
        $rel = substr($name, (int)strpos($name, '/') + 1); // strip "owner-repo-sha/"
        if ($rel === '' || substr($rel, -1) === '/' || !af_safe_rel($rel)) continue;
        if ($rel === 'defaults/content.json') { $defaults = json_decode((string)$z->getFromIndex($i), true); continue; }
        if (!preg_match(AF_UPDATE_ALLOW, $rel)) continue;
        $files[$rel] = (string)$z->getFromIndex($i);
    }
    $z->close();

    foreach (['index.php', 'lib/core.php', 'admin/index.php', 'assets/site.css'] as $must) {
        if (empty($files[$must])) return ['ok' => false, 'error' => "The update is incomplete ($must missing). Nothing was changed."];
    }

    af_backup_code(array_keys($files));
    $failed = [];
    foreach ($files as $rel => $data) if (!af_write_file($rel, $data)) $failed[] = $rel;
    if ($failed) return ['ok' => false, 'error' => 'Some files could not be written: ' . implode(', ', array_slice($failed, 0, 5)) . '. Use "Roll back" and check folder permissions.'];

    $sections = [];
    if (is_array($defaults)) {
        $cur = af_content();
        $old = @json_decode((string)@file_get_contents(AF_APPLIED), true);
        $merged = af_merge($cur, is_array($old) ? $old : null, $defaults);
        $sections = array_values(array_diff(array_keys($merged), array_keys($cur)));
        if ($merged != $cur) af_save_content($merged);
        af_write_atomic(AF_APPLIED, json_encode($defaults, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
    $v = $latest + ['updated' => gmdate('c')];
    af_write_atomic(AF_VERSION, json_encode($v, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    return ['ok' => true, 'version' => $v, 'files' => count($files), 'new_sections' => $sections];
}

function af_update_rollback(): array {
    if (!class_exists('ZipArchive')) return ['ok' => false, 'error' => 'No ZIP support on this hosting.'];
    $all = glob(AF_BACKUPS . '/code-*.zip') ?: [];
    if (!$all) return ['ok' => false, 'error' => 'There is no earlier version to go back to.'];
    sort($all);
    $file = array_pop($all);
    $z = new ZipArchive();
    if ($z->open($file) !== true) return ['ok' => false, 'error' => 'The backup could not be opened.'];
    $restoreData = '#^data/(content\.json|content\.applied\.json|version\.json)$#';
    if ($z->locateName('data/version.json') === false) @unlink(AF_VERSION);
    for ($i = 0; $i < $z->numFiles; $i++) {
        $rel = (string)$z->getNameIndex($i);
        if (!af_safe_rel($rel) || (!preg_match(AF_UPDATE_ALLOW, $rel) && !preg_match($restoreData, $rel))) continue;
        af_write_file($rel, (string)$z->getFromIndex($i));
    }
    $z->close();
    @unlink($file);
    return ['ok' => true, 'version' => af_version()];
}
