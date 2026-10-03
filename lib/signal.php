<?php
// Weak Signal — signals, subscribers, sign-in links and email.
// Flat files in /data (never served): signals.json, subscribers.json, signal-tokens.json.
declare(strict_types=1);

define('AF_SIGNALS', AF_DATA . '/signals.json');
define('AF_SUBSCRIBERS', AF_DATA . '/subscribers.json');
define('AF_SIGNAL_TOKENS', AF_DATA . '/signal-tokens.json');
define('AF_SIGNAL_COOKIE', 'afws');
const AF_SIGNAL_STATUSES = ['open' => 'Open', 'strengthening' => 'Strengthening', 'confirmed' => 'Confirmed', 'fading' => 'Fading', 'dead' => 'Dead'];
const AF_SIGNAL_PLANS = ['founding' => 'Founding', 'individual' => 'Individual', 'institutional' => 'Institutional', 'forum' => 'Forum I participant', 'complimentary' => 'Complimentary'];
const AF_SIGNAL_PARTS = ['observed' => 'What we observed', 'doesnt_fit' => 'Why it doesn’t fit', 'if_real' => 'If this is real', 'implication' => 'The decision implication, and what to watch'];

function af_json_read(string $file, string $key): array {
    $d = @json_decode((string)@file_get_contents($file), true);
    return is_array($d[$key] ?? null) ? $d[$key] : [];
}
function af_json_write(string $file, string $key, array $rows): bool {
    return af_write_atomic($file, json_encode([$key => array_values($rows)], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}

// ---------------------------------------------------------------- signals
function ws_signals(bool $publishedOnly = false): array {
    $rows = af_json_read(AF_SIGNALS, 'signals');
    if ($publishedOnly) $rows = array_filter($rows, fn($s) => !empty($s['published']));
    usort($rows, fn($a, $b) => (int)($b['number'] ?? 0) <=> (int)($a['number'] ?? 0));
    return array_values($rows);
}
function ws_signal_by_number(int $n, bool $publishedOnly = true): ?array {
    foreach (ws_signals($publishedOnly) as $s) if ((int)$s['number'] === $n) return $s;
    return null;
}
function ws_clean_signal(array $in, array $old = []): array {
    $t = fn($k, $max = 200) => mb_substr(trim(str_replace("\0", '', (string)($in[$k] ?? ''))), 0, $max);
    $s = [
        'id' => $old['id'] ?? bin2hex(random_bytes(6)),
        'number' => max(1, (int)($in['number'] ?? 1)),
        'title' => $t('title', 200),
        'date' => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($in['date'] ?? '')) ? $in['date'] : date('Y-m-d'),
        'shifts' => array_values(array_filter(array_map(fn($x) => mb_substr(trim((string)$x), 0, 40), (array)($in['shifts'] ?? [])))),
        'status' => isset(AF_SIGNAL_STATUSES[$in['status'] ?? '']) ? $in['status'] : 'open',
        'status_note' => $t('status_note', 600),
        'published' => !empty($in['published']),
        'sent_at' => $old['sent_at'] ?? null,
        'sent_count' => (int)($old['sent_count'] ?? 0),
    ];
    foreach (array_keys(AF_SIGNAL_PARTS) as $p) $s[$p] = $t($p, 8000);
    return $s;
}
function ws_save_signal(array $in): array {
    $rows = af_json_read(AF_SIGNALS, 'signals');
    $id = (string)($in['id'] ?? '');
    $idx = null;
    foreach ($rows as $i => $r) if (($r['id'] ?? '') === $id) $idx = $i;
    $s = ws_clean_signal($in, $idx !== null ? $rows[$idx] : []);
    if ($s['title'] === '') return ['ok' => false, 'error' => 'Give the signal a title.'];
    foreach ($rows as $i => $r) if ($i !== $idx && (int)$r['number'] === $s['number']) return ['ok' => false, 'error' => 'Signal No. ' . ws_num($s['number']) . ' already exists.'];
    if ($idx === null) $rows[] = $s; else $rows[$idx] = $s;
    return ['ok' => af_json_write(AF_SIGNALS, 'signals', $rows), 'signal' => $s];
}
function ws_delete_signal(string $id): bool {
    return af_json_write(AF_SIGNALS, 'signals', array_filter(af_json_read(AF_SIGNALS, 'signals'), fn($r) => ($r['id'] ?? '') !== $id));
}
function ws_mark_sent(string $id, int $count): void {
    $rows = af_json_read(AF_SIGNALS, 'signals');
    foreach ($rows as &$r) if (($r['id'] ?? '') === $id) { $r['sent_at'] = gmdate('c'); $r['sent_count'] = $count; }
    unset($r);
    af_json_write(AF_SIGNALS, 'signals', $rows);
}
function ws_num($n): string { return str_pad((string)(int)$n, 3, '0', STR_PAD_LEFT); }

// Plain text with blank-line paragraphs and **bold**.
function ws_paras(string $t): string {
    $out = '';
    foreach (preg_split('/\n\s*\n/', trim($t)) ?: [] as $p) {
        if (trim($p) === '') continue;
        $h = preg_replace('/\*\*(.+?)\*\*/s', '<b>$1</b>', e(trim($p)));
        $out .= '<p>' . nl2br($h, false) . '</p>';
    }
    return $out;
}
function ws_preview(array $s, int $words = 60): string {
    $plain = trim(preg_replace('/\s+/', ' ', str_replace('**', '', (string)($s['observed'] ?? ''))));
    $w = preg_split('/\s+/', $plain) ?: [];
    return count($w) > $words ? implode(' ', array_slice($w, 0, $words)) . '…' : $plain;
}

// ---------------------------------------------------------------- subscribers
function ws_subscribers(): array { return af_json_read(AF_SUBSCRIBERS, 'subscribers'); }
function ws_norm_email(string $e): string { return strtolower(trim($e)); }
function ws_subscriber(string $email): ?array {
    $email = ws_norm_email($email);
    foreach (ws_subscribers() as $s) if (($s['email'] ?? '') === $email) return $s;
    return null;
}
function ws_is_active(?array $s): bool {
    if (!$s || ($s['status'] ?? '') !== 'active') return false;
    $exp = (string)($s['expires'] ?? '');
    return $exp === '' || $exp >= date('Y-m-d');
}
function ws_active_subscribers(): array { return array_values(array_filter(ws_subscribers(), 'ws_is_active')); }
function ws_save_subscriber(array $in): array {
    $email = ws_norm_email((string)($in['email'] ?? ''));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return ['ok' => false, 'error' => 'That email address is not valid.'];
    $rows = ws_subscribers();
    $old = null; $idx = null;
    foreach ($rows as $i => $r) if (($r['email'] ?? '') === $email) { $old = $r; $idx = $i; }
    $s = [
        'email' => $email,
        'name' => mb_substr(trim((string)($in['name'] ?? ($old['name'] ?? ''))), 0, 120),
        'plan' => isset(AF_SIGNAL_PLANS[$in['plan'] ?? '']) ? $in['plan'] : ($old['plan'] ?? 'individual'),
        'status' => in_array($in['status'] ?? '', ['active', 'paused'], true) ? $in['status'] : ($old['status'] ?? 'active'),
        'expires' => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($in['expires'] ?? '')) ? $in['expires'] : (array_key_exists('expires', $in) ? '' : ($old['expires'] ?? '')),
        'added' => $old['added'] ?? date('Y-m-d'),
        'last_login' => $old['last_login'] ?? null,
    ];
    if ($idx === null) $rows[] = $s; else $rows[$idx] = $s;
    return ['ok' => af_json_write(AF_SUBSCRIBERS, 'subscribers', $rows), 'subscriber' => $s, 'new' => $idx === null];
}
function ws_delete_subscriber(string $email): bool {
    $email = ws_norm_email($email);
    return af_json_write(AF_SUBSCRIBERS, 'subscribers', array_filter(ws_subscribers(), fn($r) => ($r['email'] ?? '') !== $email));
}
function ws_touch_login(string $email): void {
    $rows = ws_subscribers();
    foreach ($rows as &$r) if (($r['email'] ?? '') === $email) $r['last_login'] = gmdate('c');
    unset($r);
    af_json_write(AF_SUBSCRIBERS, 'subscribers', $rows);
}

// ---------------------------------------------------------------- sign-in (email link, no passwords)
function ws_secret(): string {
    $c = af_config();
    if (empty($c['signal_secret'])) { $c['signal_secret'] = bin2hex(random_bytes(32)); af_save_config($c); }
    return (string)$c['signal_secret'];
}
function ws_issue_token(string $email): string {
    $token = bin2hex(random_bytes(24));
    $fh = fopen(AF_SIGNAL_TOKENS, 'c+');
    flock($fh, LOCK_EX);
    $all = json_decode(stream_get_contents($fh) ?: '{}', true) ?: [];
    $now = time();
    $all = array_filter($all, fn($t) => ($t['exp'] ?? 0) > $now);
    $all[hash('sha256', $token)] = ['email' => $email, 'exp' => $now + 1800];
    ftruncate($fh, 0); rewind($fh); fwrite($fh, json_encode($all));
    flock($fh, LOCK_UN); fclose($fh);
    return $token;
}
function ws_redeem_token(string $token): ?string {
    if (!preg_match('/^[a-f0-9]{48}$/', $token) || !is_file(AF_SIGNAL_TOKENS)) return null;
    $fh = fopen(AF_SIGNAL_TOKENS, 'c+');
    flock($fh, LOCK_EX);
    $all = json_decode(stream_get_contents($fh) ?: '{}', true) ?: [];
    $h = hash('sha256', $token);
    $row = $all[$h] ?? null;
    unset($all[$h]);
    ftruncate($fh, 0); rewind($fh); fwrite($fh, json_encode($all));
    flock($fh, LOCK_UN); fclose($fh);
    return ($row && $row['exp'] > time()) ? (string)$row['email'] : null;
}
function ws_https(): bool { return !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'; }
function ws_set_session(string $email): void {
    $exp = time() + 60 * 86400;
    $payload = base64_encode($email) . '|' . $exp;
    $val = $payload . '|' . hash_hmac('sha256', $payload, ws_secret());
    setcookie(AF_SIGNAL_COOKIE, $val, ['expires' => $exp, 'path' => '/', 'secure' => ws_https(), 'httponly' => true, 'samesite' => 'Lax']);
}
function ws_clear_session(): void {
    setcookie(AF_SIGNAL_COOKIE, '', ['expires' => time() - 3600, 'path' => '/', 'secure' => ws_https(), 'httponly' => true, 'samesite' => 'Lax']);
}
// Returns the signed-in subscriber's email if they still have access.
function ws_current_email(): ?string {
    $v = (string)($_COOKIE[AF_SIGNAL_COOKIE] ?? '');
    $p = explode('|', $v);
    if (count($p) !== 3) return null;
    [$b, $exp, $sig] = $p;
    if (!hash_equals(hash_hmac('sha256', $b . '|' . $exp, ws_secret()), $sig) || (int)$exp < time()) return null;
    $email = (string)base64_decode($b, true);
    return ws_is_active(ws_subscriber($email)) ? $email : null;
}

// ---------------------------------------------------------------- email
function ws_base_url(): string {
    $c = af_config();
    if (!empty($c['site_url'])) return rtrim((string)$c['site_url'], '/');
    $host = preg_replace('/[^a-z0-9.:-]/i', '', $_SERVER['HTTP_HOST'] ?? 'localhost');
    return (ws_https() ? 'https://' : 'http://') . $host;
}
function ws_remember_base_url(): void {
    $c = af_config();
    $u = ws_base_url();
    if (($c['site_url'] ?? '') !== $u && !empty($_SERVER['HTTP_HOST'])) { $c['site_url'] = $u; af_save_config($c); }
}
function ws_from(): array {
    $c = af_config();
    $host = preg_replace('/^www\./', '', preg_replace('/[^a-z0-9.-]/i', '', parse_url(ws_base_url(), PHP_URL_HOST) ?: 'localhost'));
    $from = filter_var($c['signal_from'] ?? '', FILTER_VALIDATE_EMAIL) ? $c['signal_from'] : 'signal@' . $host;
    $reply = filter_var($c['signal_reply_to'] ?? '', FILTER_VALIDATE_EMAIL) ? $c['signal_reply_to'] : '';
    return [$from, $reply];
}
function ws_mail(string $to, string $subject, string $html, string $text): bool {
    if (!function_exists('mail') || !filter_var($to, FILTER_VALIDATE_EMAIL)) return false;
    [$from, $reply] = ws_from();
    $b = 'b' . bin2hex(random_bytes(8));
    $headers = "From: Weak Signal <{$from}>\r\nMIME-Version: 1.0\r\nContent-Type: multipart/alternative; boundary=\"{$b}\"\r\n";
    if ($reply) $headers .= "Reply-To: {$reply}\r\n";
    $body = "--{$b}\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($text))
          . "--{$b}\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($html))
          . "--{$b}--\r\n";
    return @mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $body, $headers, '-f' . $from);
}
function ws_email_shell(string $inner, string $preheader = ''): string {
    return '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width"><meta name="color-scheme" content="light dark"></head>'
        . '<body style="margin:0;padding:0;background:#1C1B19;">'
        . '<div style="display:none;max-height:0;overflow:hidden;">' . e($preheader) . '</div>'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#1C1B19;"><tr><td align="center" style="padding:32px 16px;">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;">'
        . '<tr><td style="padding:0 0 28px;font-family:Helvetica,Arial,sans-serif;font-size:12px;letter-spacing:2px;font-weight:bold;color:#F0EBE1;">'
        . '<span style="display:inline-block;width:10px;height:10px;background:#C6D62B;margin-right:10px;"></span>WEAK SIGNAL <span style="color:#8C877D;font-weight:normal;">· THE AMSTERDAM FORUM</span></td></tr>'
        . $inner
        . '<tr><td style="padding:36px 0 0;border-top:1px solid #3A3833;font-family:Helvetica,Arial,sans-serif;font-size:12px;line-height:18px;color:#8C877D;">'
        . 'You receive Weak Signal as a subscriber of the Amsterdam Forum. No advertising, no sponsorship, ever. Reply to this email to change your subscription.</td></tr>'
        . '</table></td></tr></table></body></html>';
}
function ws_signal_email(array $s): array {
    $url = ws_base_url() . '/signal/?n=' . (int)$s['number'];
    $pre = ws_preview($s, 60);
    $shifts = $s['shifts'] ? implode(' · ', $s['shifts']) : '';
    $inner = '<tr><td style="font-family:Helvetica,Arial,sans-serif;font-size:13px;letter-spacing:2px;font-weight:bold;color:#C6D62B;padding:0 0 10px;">NO. ' . ws_num($s['number']) . '</td></tr>'
        . '<tr><td style="font-family:Helvetica,Arial,sans-serif;font-size:28px;line-height:34px;font-weight:bold;color:#F0EBE1;padding:0 0 12px;">' . e($s['title']) . '</td></tr>'
        . '<tr><td style="font-family:Helvetica,Arial,sans-serif;font-size:12px;letter-spacing:1px;color:#A8A297;padding:0 0 24px;">' . e(strtoupper(date('j M Y', strtotime($s['date'])) . ($shifts ? ' · ' . $shifts : ''))) . '</td></tr>'
        . '<tr><td style="font-family:Helvetica,Arial,sans-serif;font-size:11px;letter-spacing:2px;font-weight:bold;color:#8C877D;padding:0 0 8px;">01 · WHAT WE OBSERVED</td></tr>'
        . '<tr><td style="font-family:Georgia,\'Times New Roman\',serif;font-size:18px;line-height:28px;color:#F0EBE1;padding:0 0 28px;">' . e($pre) . '</td></tr>'
        . '<tr><td style="padding:0 0 36px;"><a href="' . e($url) . '" style="display:inline-block;background:#C6D62B;color:#1C1B19;font-family:Helvetica,Arial,sans-serif;font-size:15px;font-weight:bold;text-decoration:none;padding:14px 22px;">Read the full signal →</a>'
        . '<div style="font-family:Helvetica,Arial,sans-serif;font-size:12px;color:#8C877D;padding-top:12px;">Sign in with this email address to read why it doesn’t fit, what follows if it is real, and what to decide.</div></td></tr>';
    $text = "WEAK SIGNAL · No. " . ws_num($s['number']) . "\n\n" . $s['title'] . "\n\nWhat we observed\n" . $pre . "\n\nRead the full signal: " . $url . "\n";
    return ['Weak Signal No. ' . ws_num($s['number']) . ' · ' . $s['title'], ws_email_shell($inner, $pre), $text];
}
function ws_login_email(string $email, string $token, int $n = 0): array {
    $url = ws_base_url() . '/signal/?t=' . $token . ($n ? '&n=' . $n : '');
    $inner = '<tr><td style="font-family:Helvetica,Arial,sans-serif;font-size:24px;line-height:30px;font-weight:bold;color:#F0EBE1;padding:0 0 16px;">Your sign-in link</td></tr>'
        . '<tr><td style="font-family:Georgia,serif;font-size:17px;line-height:26px;color:#F0EBE1;padding:0 0 24px;">Click below to open Weak Signal. The link works once and expires in 30 minutes.</td></tr>'
        . '<tr><td style="padding:0 0 28px;"><a href="' . e($url) . '" style="display:inline-block;background:#C6D62B;color:#1C1B19;font-family:Helvetica,Arial,sans-serif;font-size:15px;font-weight:bold;text-decoration:none;padding:14px 22px;">Sign in to Weak Signal →</a></td></tr>'
        . '<tr><td style="font-family:Helvetica,Arial,sans-serif;font-size:12px;color:#8C877D;padding:0 0 32px;">If you didn’t ask for this, ignore it. Nobody can sign in without this email.</td></tr>';
    return ['Your Weak Signal sign-in link', ws_email_shell($inner, 'Your sign-in link'), "Sign in to Weak Signal (works once, 30 minutes):\n" . $url . "\n"];
}
function ws_send_signal(array $s, string $to): bool {
    [$subj, $html, $text] = ws_signal_email($s);
    return ws_mail($to, $subj, $html, $text);
}
