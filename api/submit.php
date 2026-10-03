<?php
// Receives the website's forms. Stores every submission in data/submissions.jsonl
// and, when a notification address is set in /admin, emails a copy.
declare(strict_types=1);
require dirname(__DIR__) . '/lib/core.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') af_json(['ok' => false, 'error' => 'Method not allowed.'], 405);

// Honeypot: real people never fill the hidden "website" field.
if (trim((string)($_POST['website'] ?? '')) !== '') af_json(['ok' => true]);

if (!af_rate_ok('submit', 8, 3600)) af_json(['ok' => false, 'error' => 'Too many submissions from this connection. Please try again in an hour.'], 429);

$kinds = ['invitation', 'partner', 'speaker', 'circle', 'witness', 'weak-signal'];
$kind = (string)($_POST['kind'] ?? '');
if (!in_array($kind, $kinds, true)) af_json(['ok' => false, 'error' => 'Unknown form.'], 400);

$fields = [
    'invitation'  => ['name' => 120, 'email' => 160, 'organisation' => 160, 'role' => 160, 'country' => 80, 'domain' => 60, 'note' => 3000, 'seats' => 2],
    'partner'     => ['name' => 120, 'email' => 160, 'organisation' => 160, 'role' => 160, 'tier' => 120, 'note' => 3000],
    'speaker'     => ['name' => 120, 'email' => 160, 'speaker' => 160, 'role' => 160, 'format' => 20, 'shift' => 60, 'note' => 3000, 'link' => 300],
    'circle'      => ['name' => 120, 'email' => 160, 'city' => 80, 'country' => 80, 'role' => 160, 'note' => 3000],
    'witness'     => ['name' => 120, 'email' => 160, 'witness' => 160, 'shift' => 60, 'note' => 3000],
    'weak-signal' => ['email' => 160],
];
$required = [
    'invitation'  => ['name', 'email', 'organisation', 'role', 'note'],
    'partner'     => ['name', 'email', 'organisation'],
    'speaker'     => ['name', 'email', 'note'],
    'circle'      => ['name', 'email', 'city', 'note'],
    'witness'     => ['name', 'email', 'note'],
    'weak-signal' => ['email'],
];

$row = ['id' => bin2hex(random_bytes(8)), 'kind' => $kind, 'received' => gmdate('c')];
foreach ($fields[$kind] as $f => $max) {
    $v = trim(str_replace("\0", '', (string)($_POST[$f] ?? '')));
    $v = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $v) ?? '';
    if (mb_strlen($v) > $max) $v = mb_substr($v, 0, $max);
    $row[$f] = $v;
}
foreach ($required[$kind] as $f) {
    if ($row[$f] === '') af_json(['ok' => false, 'error' => 'Please complete every required field.'], 422);
}
if (!filter_var($row['email'], FILTER_VALIDATE_EMAIL)) af_json(['ok' => false, 'error' => 'Please enter a valid email address.'], 422);
$row['ip_hash'] = substr(hash('sha256', af_client_ip()), 0, 16);

$line = json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
if (@file_put_contents(AF_SUBMISSIONS, $line, FILE_APPEND | LOCK_EX) === false) {
    af_json(['ok' => false, 'error' => 'We could not save that just now. Please try again shortly.'], 500);
}

// Optional email notification (uses the host's mail system).
$c = af_content();
$to = (string)af_get($c, 'site.notify_email', '');
if ($to && filter_var($to, FILTER_VALIDATE_EMAIL) && function_exists('mail')) {
    $labels = ['invitation' => 'Invitation request', 'partner' => 'Partner enquiry', 'speaker' => 'Speaker proposal', 'circle' => 'Circle proposal', 'witness' => 'Witness proposal', 'weak-signal' => 'Weak Signal founding list'];
    $subject = '[Amsterdam Forum] ' . $labels[$kind] . (!empty($row['name']) ? ' — ' . $row['name'] : '');
    $body = '';
    foreach ($row as $k => $v) { if ($k !== 'ip_hash') $body .= ucfirst($k) . ': ' . $v . "\n"; }
    $host = preg_replace('/[^a-z0-9.-]/i', '', $_SERVER['HTTP_HOST'] ?? 'localhost');
    $headers = "From: Amsterdam Forum website <no-reply@{$host}>\r\nContent-Type: text/plain; charset=UTF-8\r\n";
    if (filter_var($row['email'], FILTER_VALIDATE_EMAIL)) $headers .= 'Reply-To: ' . $row['email'] . "\r\n";
    @mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $body, $headers);
}

af_json(['ok' => true]);
