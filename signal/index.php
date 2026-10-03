<?php
// Weak Signal — the subscriber section of amsforum.com.
declare(strict_types=1);
require dirname(__DIR__) . '/lib/core.php';
require dirname(__DIR__) . '/lib/signal.php';

header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header('Cache-Control: private, no-store');

$c = af_content();
$g = fn(string $p, $d = '') => af_get($c, $p, $d);
$t = fn(string $k, string $d) => (string)af_get($c, 'signal_page.' . $k, $d);

// ---------- actions
$notice = ''; $noticeKind = '';
if (isset($_GET['logout'])) { ws_clear_session(); header('Location: ./'); exit; }

if (!empty($_GET['t'])) {
    $email = ws_redeem_token((string)$_GET['t']);
    if ($email && ws_is_active(ws_subscriber($email))) {
        ws_set_session($email); ws_touch_login($email);
        $n = (int)($_GET['n'] ?? 0);
        header('Location: ./' . ($n ? '?n=' . $n : '')); exit;
    }
    $notice = 'That sign-in link has expired or was already used. Ask for a new one below.'; $noticeKind = 'err';
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && ($_POST['action'] ?? '') === 'login') {
    $email = ws_norm_email((string)($_POST['email'] ?? ''));
    $n = (int)($_POST['n'] ?? 0);
    if (trim((string)($_POST['website'] ?? '')) !== '') { $email = ''; }
    if (!af_rate_ok('signal-login', 6, 900)) { $notice = 'Too many requests. Try again in fifteen minutes.'; $noticeKind = 'err'; }
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) { $notice = 'Enter the email address your subscription is under.'; $noticeKind = 'err'; }
    else {
        if (ws_is_active(ws_subscriber($email))) {
            ws_remember_base_url();
            [$subj, $html, $text] = ws_login_email($email, ws_issue_token($email), $n);
            ws_mail($email, $subj, $html, $text);
        }
        // Same answer either way, so the form can't be used to test who subscribes.
        $notice = 'If ' . $email . ' has an active subscription, a sign-in link is on its way. Check your inbox (and spam) in a minute.'; $noticeKind = 'ok';
    }
}

$me = ws_current_email();
$isAdmin = af_is_admin();
$access = $me !== null || $isAdmin;
$n = (int)($_GET['n'] ?? 0);
$signal = $n ? ws_signal_by_number($n, !$isAdmin) : null;
$all = ws_signals(!$isAdmin);
$shiftFilter = (string)($_GET['shift'] ?? '');
$shiftNames = array_values(array_filter(array_map(fn($s) => (string)($s['name'] ?? ''), (array)$g('evidence.shifts', []))));
if ($n && !$signal) http_response_code(404);

$title = $signal ? 'No. ' . ws_num($signal['number']) . ' · ' . $signal['title'] . ' · Weak Signal' : 'Weak Signal · The Amsterdam Forum';
$statusLabel = fn($s) => AF_SIGNAL_STATUSES[$s['status'] ?? 'open'] ?? 'Open';
$date = fn($s) => date('j M Y', strtotime((string)$s['date']));

function login_box(int $n, string $heading, string $text, string $notice, string $kind): string {
    ob_start(); ?>
    <div class="ws-login" id="signin">
      <h2><?= e($heading) ?></h2>
      <p><?= e($text) ?></p>
      <?php if ($notice): ?><p class="ws-notice <?= e($kind) ?>" role="status"><?= e($notice) ?></p><?php endif; ?>
      <form method="post" action="./#signin">
        <input type="hidden" name="action" value="login"><input type="hidden" name="n" value="<?= $n ?>">
        <label for="ws-email">Email</label>
        <div class="ws-row"><input id="ws-email" name="email" type="email" required autocomplete="email" placeholder="you@organisation.com"><button type="submit">Send sign-in link</button></div>
        <div class="hp" aria-hidden="true"><input name="website" tabindex="-1" autocomplete="off"></div>
      </form>
    </div>
    <?php return (string)ob_get_clean();
}
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?></title>
<meta name="description" content="<?= e($t('description', 'Weak Signal: a biweekly dispatch from the Amsterdam Forum on the shifts that do not have a name yet, scored in public against what happened next.')) ?>">
<meta name="robots" content="<?= $signal ? 'noindex' : 'index' ?>">
<link rel="icon" href="../assets/logo/af-favicon.svg" type="image/svg+xml">
<link rel="stylesheet" href="signal.css?v=<?= @filemtime(__DIR__ . '/signal.css') ?>">
</head>
<body>
<header class="ws-bar">
  <a class="ws-brand" href="./"><span class="sq" aria-hidden="true"></span><b>Weak Signal</b><span class="by">The Amsterdam Forum</span></a>
  <nav>
    <?php if ($access): ?>
      <a href="./">Archive</a>
      <span class="who"><?= e($me ?? 'Admin preview') ?></span>
      <?php if ($me): ?><a href="?logout=1">Sign out</a><?php endif; ?>
    <?php else: ?>
      <a href="../">amsforum.com</a>
      <a class="btn-line" href="#signin">Sign in</a>
    <?php endif; ?>
  </nav>
</header>

<main>
<?php if ($n && !$signal): ?>
  <section class="ws-wrap ws-empty"><p class="ws-k">Not found</p><h1>There is no signal No. <?= e(ws_num($n)) ?>.</h1><p><a href="./">Back to the archive</a></p></section>

<?php elseif ($signal && $access): ?>
  <article class="ws-wrap ws-signal">
    <header class="ws-head">
      <p class="ws-num"><small>No.</small><?= e(ws_num($signal["number"])) ?></p>
      <h1><?= e($signal['title']) ?></h1>
      <p class="ws-meta"><?= e($date($signal)) ?><?php foreach ($signal['shifts'] as $sh): ?> <span class="chip"><?= e($sh) ?></span><?php endforeach; ?> <span class="status st-<?= e($signal['status']) ?>"><?= e($statusLabel($signal)) ?></span><?php if (empty($signal['published'])): ?> <span class="chip draft">Draft · only you see this</span><?php endif; ?></p>
    </header>
    <?php $i = 0; foreach (AF_SIGNAL_PARTS as $k => $label): $i++; if (trim((string)($signal[$k] ?? '')) === '') continue; ?>
    <section class="ws-part"><p class="ws-k"><span><?= sprintf('%02d', $i) ?></span><?= e($label) ?></p><div class="ws-body"><?= ws_paras((string)$signal[$k]) ?></div></section>
    <?php endforeach; ?>
    <aside class="ws-ledger">
      <p class="ws-k">The Ledger</p>
      <p><span class="status st-<?= e($signal['status']) ?>"><?= e($statusLabel($signal)) ?></span></p>
      <?php if (!empty($signal['status_note'])): ?><div class="ws-body small"><?= ws_paras((string)$signal['status_note']) ?></div><?php endif; ?>
    </aside>
    <nav class="ws-pn">
      <?php $nums = array_map(fn($s) => (int)$s['number'], $all); $prev = null; $next = null;
      foreach ($nums as $x) { if ($x < (int)$signal['number'] && ($prev === null || $x > $prev)) $prev = $x; if ($x > (int)$signal['number'] && ($next === null || $x < $next)) $next = $x; } ?>
      <?php if ($prev): ?><a href="?n=<?= $prev ?>">← No. <?= e(ws_num($prev)) ?></a><?php else: ?><span></span><?php endif; ?>
      <a href="./">All signals</a>
      <?php if ($next): ?><a href="?n=<?= $next ?>">No. <?= e(ws_num($next)) ?> →</a><?php else: ?><span></span><?php endif; ?>
    </nav>
  </article>

<?php elseif ($signal): ?>
  <article class="ws-wrap ws-signal locked">
    <header class="ws-head">
      <p class="ws-num"><small>No.</small><?= e(ws_num($signal["number"])) ?></p>
      <h1><?= e($signal['title']) ?></h1>
      <p class="ws-meta"><?= e($date($signal)) ?><?php foreach ($signal['shifts'] as $sh): ?> <span class="chip"><?= e($sh) ?></span><?php endforeach; ?></p>
    </header>
    <section class="ws-part"><p class="ws-k"><span>01</span><?= e(AF_SIGNAL_PARTS['observed']) ?></p><div class="ws-body fade"><p><?= e(ws_preview($signal, 60)) ?></p></div></section>
    <?= login_box((int)$signal['number'], $t('locked_title', 'The rest is for subscribers.'), $t('locked_text', 'Sign in with the email address your subscription is under. We send you a link; no password needed.'), $notice, $noticeKind) ?>
  </article>

<?php elseif ($access): ?>
  <section class="ws-wrap ws-archive">
    <header class="ws-arch-head">
      <div><p class="ws-k"><?= e($t('archive_eyebrow', 'The archive')) ?></p><h1><?= e($t('archive_title', 'Signals')) ?></h1></div>
      <ul class="ws-legend" aria-label="Ledger statuses"><?php foreach (AF_SIGNAL_STATUSES as $k => $v): ?><li><span class="status st-<?= e($k) ?>"><?= e($v) ?></span></li><?php endforeach; ?></ul>
    </header>
    <?php if ($shiftNames): ?>
    <nav class="ws-filter" aria-label="Filter by shift"><a href="./" class="<?= $shiftFilter === '' ? 'on' : '' ?>">All</a><?php foreach ($shiftNames as $sh): ?><a href="?shift=<?= e(rawurlencode($sh)) ?>" class="<?= $shiftFilter === $sh ? 'on' : '' ?>"><?= e($sh) ?></a><?php endforeach; ?></nav>
    <?php endif; ?>
    <?php $list = array_values(array_filter($all, fn($s) => $shiftFilter === '' || in_array($shiftFilter, (array)$s['shifts'], true))); ?>
    <?php if (!$list): ?><p class="ws-none"><?= e($t('empty', 'The first signal arrives with Issue 001.')) ?></p><?php endif; ?>
    <ol class="ws-list">
      <?php foreach ($list as $s): ?>
      <li><a href="?n=<?= (int)$s['number'] ?>">
        <span class="n"><?= e(ws_num($s['number'])) ?></span>
        <span class="t"><b><?= e($s['title']) ?></b><span class="m"><?= e($date($s)) ?><?php if ($s['shifts']): ?> · <?= e(implode(' · ', $s['shifts'])) ?><?php endif; ?><?php if (empty($s['published'])): ?> · draft<?php endif; ?></span></span>
        <span class="status st-<?= e($s['status']) ?>"><?= e($statusLabel($s)) ?></span>
      </a></li>
      <?php endforeach; ?>
    </ol>
  </section>

<?php else: ?>
  <section class="ws-wrap ws-land">
    <div class="ws-hero">
      <div>
        <p class="ws-k"><?= e($t('eyebrow', 'Biweekly · from the Amsterdam Forum')) ?></p>
        <h1><?= e($t('title', 'Read the shifts before they are news.')) ?></h1>
        <p class="ws-lede"><?= e($t('lede', 'Weak Signal reports changes that do not have a name yet: observed in the documents where behaviour leaks before narrative forms, and scored in public, issue after issue, against what happened next.')) ?></p>
      </div>
      <div class="ws-grid" aria-hidden="true"><?php for ($i = 0; $i < 25; $i++): ?><span class="<?= $i === 8 ? 'sig' : '' ?>"></span><?php endfor; ?></div>
    </div>
    <ol class="ws-criteria">
      <li><span>01</span><b>Observed</b>Seen in a document or a decision, not predicted.</li>
      <li><span>02</span><b>Doesn’t fit</b>It contradicts the consensus story.</li>
      <li><span>03</span><b>Consequential</b>If it is real, capital, strategy or policy moves.</li>
      <li><span>04</span><b>Pre-narrative</b>Not yet in the news cycle.</li>
    </ol>
    <div class="ws-split">
      <?= login_box(0, $t('signin_title', 'Subscribers'), $t('signin_text', 'Sign in with the email address your subscription is under. We send you a link; no password needed.'), $notice, $noticeKind) ?>
      <div class="ws-join">
        <h2><?= e($t('join_title', 'Not a subscriber yet?')) ?></h2>
        <p><?= e($t('join_text', 'Founding subscribers pay €190 a year, locked for life, for the first 500. Institutions: €2,500 a year for up to ten readers. Forum I participants receive a year with their invitation.')) ?></p>
        <a class="btn-line" href="../#weak-signal"><?= e($t('join_button', 'Join the founding list')) ?></a>
      </div>
    </div>
    <?php if ($all): ?>
    <div class="ws-teaser">
      <p class="ws-k">Recent signals</p>
      <ol class="ws-list locked">
        <?php foreach (array_slice($all, 0, 8) as $s): ?>
        <li><a href="?n=<?= (int)$s['number'] ?>"><span class="n"><?= e(ws_num($s['number'])) ?></span><span class="t"><b><?= e($s['title']) ?></b><span class="m"><?= e($date($s)) ?></span></span><span class="lock" aria-label="Subscribers only">Subscribers</span></a></li>
        <?php endforeach; ?>
      </ol>
    </div>
    <?php endif; ?>
  </section>
<?php endif; ?>
</main>

<footer class="ws-foot"><span>Weak Signal is published by the Amsterdam Forum. No advertising, no sponsorship, ever.</span><a href="../">amsforum.com</a></footer>
</body>
</html>
