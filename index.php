<?php
declare(strict_types=1);
require __DIR__ . '/lib/core.php';

$c = af_content();
$g = fn(string $p, $d = '') => af_get($c, $p, $d);

// Inline markup in list items: **bold**
function rich(string $s): string {
    return preg_replace('/\*\*(.+?)\*\*/', '<b>$1</b>', e($s));
}
function src_link(array $f): string {
    $u = af_url((string)($f['url'] ?? ''));
    $s = e($f['source'] ?? '');
    if ($s === '') return '';
    return $u ? '<a class="src" href="' . e($u) . '" target="_blank" rel="noopener">' . $s . '</a>' : '<span class="src">' . $s . '</span>';
}
function visual(string $img, string $alt, string $caption = ''): string {
    $img = af_img($img);
    if (!$img) return '';
    $h = '<figure class="visual"><img src="' . e($img) . '" alt="' . e($alt) . '" loading="lazy">';
    if ($caption !== '') $h .= '<figcaption>' . e($caption) . '</figcaption>';
    return $h . '</figure>';
}

// Theme: Amsterdam Forum design-system tokens, editable in /admin
$tok = ['paper','paper_raised','ink','ink_muted','rule','stone','vermilion','vermilion_text','canal','signal','edition','on_edition'];
$defL = ['paper'=>'#FFF8EC','paper_raised'=>'#FFFDF8','ink'=>'#0E0E14','ink_muted'=>'#4D4D5C','rule'=>'#E3DCCB','stone'=>'#9B98A8','vermilion'=>'#1F3BFF','vermilion_text'=>'#1F3BFF','canal'=>'#1F3BFF','signal'=>'#FF4FA3','edition'=>'#1F3BFF','on_edition'=>'#FFF8EC'];
$defD = ['paper'=>'#0E0E14','paper_raised'=>'#1A1A26','ink'=>'#FFF8EC','ink_muted'=>'#B4B2C4','rule'=>'#2E2E40','stone'=>'#6E6C80','vermilion'=>'#5C73FF','vermilion_text'=>'#8C9BFF','canal'=>'#8C9BFF','signal'=>'#FF7DBB','edition'=>'#8C9BFF','on_edition'=>'#0E0E14'];
$vars = function (string $set, array $def) use ($g, $tok) {
    $out = '';
    foreach ($tok as $k) $out .= '--' . str_replace('_', '-', $k) . ':' . af_safe_color((string)$g("theme.$set.$k", $def[$k]), $def[$k]) . ';';
    return $out;
};
$light = $vars('colors_light', $defL);
$dark = $vars('colors_dark', $defD);
$mode = (string)$g('theme.mode', 'light');
$fonts = [
    'sans'  => af_safe_font((string)$g('theme.fonts.sans', 'Schibsted Grotesk'), 'Schibsted Grotesk'),
    'serif' => af_safe_font((string)$g('theme.fonts.serif', 'Newsreader'), 'Newsreader'),
];
$fontVars = "--sans:\"{$fonts['sans']}\", \"Helvetica Neue\", Arial, sans-serif;--serif:\"{$fonts['serif']}\", Georgia, \"Times New Roman\", serif;";
// The brand faces are self-hosted; any other choice loads from Google Fonts.
$fontsUrl = af_google_fonts_url(array_filter($fonts, fn($f) => !in_array($f, ['Schibsted Grotesk', 'Newsreader'], true)));
$darkLogo = '.logo-light{display:none}.logo-dark{display:block}';

$shifts = (array)$g('evidence.shifts', []);
$seps = (array)$g('map.separations', []);
$mapData = [
    'shifts' => array_map(fn($s) => [
        'name' => (string)($s['name'] ?? ''),
        'links' => array_values(array_filter(array_map(fn($n) => (int)trim($n) - 1, explode(',', (string)($s['map_links'] ?? ''))), fn($n) => $n >= 0)),
        'hl' => !empty($s['map_highlight']),
    ], $shifts),
    'seps' => array_map('strval', $seps),
];
$share = af_img((string)$g('site.share_image'));
$scheme = isset($_SERVER['HTTP_HOST']) ? ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . '/' : '';
$heroImg = af_img((string)$g('hero.image'));
function status_line(string $t): string { return $t === '' ? '' : '<p class="status-line"><span aria-hidden="true"></span>' . e($t) . '</p>'; }
$privacyLink = '<a href="?privacy">' . e((string)$g('footer.privacy_link', 'Privacy')) . '</a>';
?><!doctype html>
<html lang="en"<?= $mode === 'light' || $mode === 'dark' ? ' data-theme="' . e($mode) . '"' : '' ?>>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($g('site.title')) ?></title>
<meta name="description" content="<?= e($g('site.description')) ?>">
<meta property="og:title" content="<?= e($g('site.title')) ?>">
<meta property="og:description" content="<?= e($g('site.description')) ?>">
<meta property="og:type" content="website">
<link rel="icon" href="assets/logo/xx-favicon.svg" type="image/svg+xml">
<link rel="apple-touch-icon" href="assets/logo/xx-app-icon.svg">
<?php if ($share): ?><meta property="og:image" content="<?= e($scheme . $share) ?>"><meta name="twitter:card" content="summary_large_image"><?php endif; ?>
<?php if ($fontsUrl): ?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="<?= e($fontsUrl) ?>">
<?php endif; ?>
<style>
:root{<?= $light . $fontVars ?>color-scheme:light}
<?php if ($mode === 'auto'): ?>@media (prefers-color-scheme: dark){:root:not([data-theme="light"]){<?= $dark ?>color-scheme:dark}:root:not([data-theme="light"]) <?= str_replace('}.', '}:root:not([data-theme="light"]) .', $darkLogo) ?>}<?php endif; ?>
:root[data-theme="dark"]{<?= $dark ?>color-scheme:dark}
:root[data-theme="dark"] <?= str_replace('}.', '}:root[data-theme="dark"] .', $darkLogo) ?>
</style>
<link rel="stylesheet" href="assets/site.css?v=<?= @filemtime(__DIR__ . '/assets/site.css') ?>">
</head>
<body>
<a class="skip" href="#top">Skip to content</a>
<nav class="nav" aria-label="Main">
  <div class="wrap">
    <a class="brand" href="#top" aria-label="Amsterdam Forum, home"><img class="logo-light" src="assets/logo/xxaf-horizontal-ink.svg" alt="Amsterdam Forum" width="345" height="30"><img class="logo-dark" src="assets/logo/xxaf-horizontal-reversed.svg" alt="Amsterdam Forum" width="345" height="30"></a>
    <ul>
      <?php foreach ((array)$g('nav.links', []) as $l): ?><li><a href="<?= e(af_url((string)($l['href'] ?? ''))) ?>"><?= e($l['label'] ?? '') ?></a></li><?php endforeach; ?>
    </ul>
    <a class="btn small" href="#apply" data-tab="invite"><?= e($g('nav.button')) ?></a>
  </div>
</nav>

<main id="top">
<?php if (isset($_GET['privacy'])): ?>
  <section class="privacy">
    <div class="wrap">
      <div class="sec-head">
        <span class="label"><?= e($g('privacy.updated')) ?></span>
        <h1 class="headline"><?= e($g('privacy.title', 'Privacy')) ?></h1>
        <p class="lede"><?= e($g('privacy.intro')) ?></p>
      </div>
      <?php foreach ((array)$g('privacy.sections', []) as $ps): ?>
      <div class="priv-sec"><h2><?= e($ps['heading'] ?? '') ?></h2><p class="body-serif"><?= e($ps['text'] ?? '') ?></p></div>
      <?php endforeach; ?>
      <div class="priv-sec"><h2><?= e($g('privacy.contact_text', 'Questions and requests:')) ?></h2><p class="body-serif"><?php $pe = (string)$g('site.contact_email'); ?><?= $pe ? '<a href="mailto:' . e($pe) . '">' . e($pe) . '</a>' : 'Use the contact address in the footer.' ?></p></div>
      <p><a class="btn ghost" href="./">Back to the Forum</a></p>
    </div>
  </section>
<?php else: ?>
  <header class="hero">
    <div class="wrap">
      <span class="label edition"><?= e($g('hero.eyebrow')) ?></span>
      <h1><span><?= e($g('hero.title_line_1')) ?></span><span><?= e($g('hero.title_line_2')) ?></span></h1>
      <div class="hero-grid">
        <div class="hero-text">
          <p class="lede"><?= e($g('hero.lede')) ?></p>
          <div class="ctas">
            <a class="btn" href="#apply" data-tab="invite"><?= e($g('hero.button_invite')) ?></a>
            <a class="btn ghost" href="#apply" data-tab="partner"><?= e($g('hero.button_partner')) ?></a>
            <a class="btn ghost" href="#apply" data-tab="speaker"><?= e($g('hero.button_witness')) ?></a>
          </div>
        </div>
        <?php if ($heroImg): ?>
        <?= visual((string)$g('hero.image'), (string)$g('hero.image_alt'), (string)$g('hero.image_caption')) ?>
        <?php else: ?>
        <div class="config" aria-hidden="true">
          <svg viewBox="0 0 560 440"><path class="xx" d="M47.81 34.82L25.18 12.19L32.19 5.18L54.82 27.81Z M54.82 12.19L32.19 34.82L25.18 27.81L47.81 5.18Z"/><path class="xx" d="M127.81 34.82L105.18 12.19L112.19 5.18L134.82 27.81Z M134.82 12.19L112.19 34.82L105.18 27.81L127.81 5.18Z"/><path class="xx" d="M207.81 34.82L185.18 12.19L192.19 5.18L214.82 27.81Z M214.82 12.19L192.19 34.82L185.18 27.81L207.81 5.18Z"/><path class="xx" d="M287.81 34.82L265.18 12.19L272.19 5.18L294.82 27.81Z M294.82 12.19L272.19 34.82L265.18 27.81L287.81 5.18Z"/><path class="xx" d="M367.81 34.82L345.18 12.19L352.19 5.18L374.82 27.81Z M374.82 12.19L352.19 34.82L345.18 27.81L367.81 5.18Z"/><path class="xx" d="M447.81 34.82L425.18 12.19L432.19 5.18L454.82 27.81Z M454.82 12.19L432.19 34.82L425.18 27.81L447.81 5.18Z"/><path class="xx" d="M527.81 34.82L505.18 12.19L512.19 5.18L534.82 27.81Z M534.82 12.19L512.19 34.82L505.18 27.81L527.81 5.18Z"/><path class="xx" d="M47.81 114.82L25.18 92.19L32.19 85.18L54.82 107.81Z M54.82 92.19L32.19 114.82L25.18 107.81L47.81 85.18Z"/><path class="xx" d="M127.81 114.82L105.18 92.19L112.19 85.18L134.82 107.81Z M134.82 92.19L112.19 114.82L105.18 107.81L127.81 85.18Z"/><path class="xx" d="M207.81 114.82L185.18 92.19L192.19 85.18L214.82 107.81Z M214.82 92.19L192.19 114.82L185.18 107.81L207.81 85.18Z"/><path class="xx" d="M287.81 114.82L265.18 92.19L272.19 85.18L294.82 107.81Z M294.82 92.19L272.19 114.82L265.18 107.81L287.81 85.18Z"/><path class="xx" d="M367.81 114.82L345.18 92.19L352.19 85.18L374.82 107.81Z M374.82 92.19L352.19 114.82L345.18 107.81L367.81 85.18Z"/><path class="xx" d="M447.81 114.82L425.18 92.19L432.19 85.18L454.82 107.81Z M454.82 92.19L432.19 114.82L425.18 107.81L447.81 85.18Z"/><path class="xx" d="M527.81 114.82L505.18 92.19L512.19 85.18L534.82 107.81Z M534.82 92.19L512.19 114.82L505.18 107.81L527.81 85.18Z"/><path class="xx" d="M47.81 194.82L25.18 172.19L32.19 165.18L54.82 187.81Z M54.82 172.19L32.19 194.82L25.18 187.81L47.81 165.18Z"/><path class="xx" d="M127.81 194.82L105.18 172.19L112.19 165.18L134.82 187.81Z M134.82 172.19L112.19 194.82L105.18 187.81L127.81 165.18Z"/><path class="xx" d="M207.81 194.82L185.18 172.19L192.19 165.18L214.82 187.81Z M214.82 172.19L192.19 194.82L185.18 187.81L207.81 165.18Z"/><path class="xx" d="M287.81 194.82L265.18 172.19L272.19 165.18L294.82 187.81Z M294.82 172.19L272.19 194.82L265.18 187.81L287.81 165.18Z"/><g class="plus"><path class="pl" d="M378.00 186.19L342.00 186.19L342.00 173.81L378.00 173.81Z M353.81 198.00L353.81 162.00L366.19 162.00L366.19 198.00Z"/></g><path class="xx" d="M447.81 194.82L425.18 172.19L432.19 165.18L454.82 187.81Z M454.82 172.19L432.19 194.82L425.18 187.81L447.81 165.18Z"/><path class="xx" d="M527.81 194.82L505.18 172.19L512.19 165.18L534.82 187.81Z M534.82 172.19L512.19 194.82L505.18 187.81L527.81 165.18Z"/><path class="xx" d="M47.81 274.82L25.18 252.19L32.19 245.18L54.82 267.81Z M54.82 252.19L32.19 274.82L25.18 267.81L47.81 245.18Z"/><path class="xx" d="M127.81 274.82L105.18 252.19L112.19 245.18L134.82 267.81Z M134.82 252.19L112.19 274.82L105.18 267.81L127.81 245.18Z"/><path class="xx" d="M207.81 274.82L185.18 252.19L192.19 245.18L214.82 267.81Z M214.82 252.19L192.19 274.82L185.18 267.81L207.81 245.18Z"/><path class="xx" d="M287.81 274.82L265.18 252.19L272.19 245.18L294.82 267.81Z M294.82 252.19L272.19 274.82L265.18 267.81L287.81 245.18Z"/><path class="xx" d="M367.81 274.82L345.18 252.19L352.19 245.18L374.82 267.81Z M374.82 252.19L352.19 274.82L345.18 267.81L367.81 245.18Z"/><path class="xx" d="M447.81 274.82L425.18 252.19L432.19 245.18L454.82 267.81Z M454.82 252.19L432.19 274.82L425.18 267.81L447.81 245.18Z"/><path class="xx" d="M527.81 274.82L505.18 252.19L512.19 245.18L534.82 267.81Z M534.82 252.19L512.19 274.82L505.18 267.81L527.81 245.18Z"/><path class="xx" d="M47.81 354.82L25.18 332.19L32.19 325.18L54.82 347.81Z M54.82 332.19L32.19 354.82L25.18 347.81L47.81 325.18Z"/><path class="xx" d="M127.81 354.82L105.18 332.19L112.19 325.18L134.82 347.81Z M134.82 332.19L112.19 354.82L105.18 347.81L127.81 325.18Z"/><path class="xx" d="M207.81 354.82L185.18 332.19L192.19 325.18L214.82 347.81Z M214.82 332.19L192.19 354.82L185.18 347.81L207.81 325.18Z"/><path class="xx" d="M287.81 354.82L265.18 332.19L272.19 325.18L294.82 347.81Z M294.82 332.19L272.19 354.82L265.18 347.81L287.81 325.18Z"/><path class="xx" d="M367.81 354.82L345.18 332.19L352.19 325.18L374.82 347.81Z M374.82 332.19L352.19 354.82L345.18 347.81L367.81 325.18Z"/><path class="xx" d="M447.81 354.82L425.18 332.19L432.19 325.18L454.82 347.81Z M454.82 332.19L432.19 354.82L425.18 347.81L447.81 325.18Z"/><path class="xx" d="M527.81 354.82L505.18 332.19L512.19 325.18L534.82 347.81Z M534.82 332.19L512.19 354.82L505.18 347.81L527.81 325.18Z"/><path class="xx" d="M47.81 434.82L25.18 412.19L32.19 405.18L54.82 427.81Z M54.82 412.19L32.19 434.82L25.18 427.81L47.81 405.18Z"/><path class="xx" d="M127.81 434.82L105.18 412.19L112.19 405.18L134.82 427.81Z M134.82 412.19L112.19 434.82L105.18 427.81L127.81 405.18Z"/><path class="xx" d="M207.81 434.82L185.18 412.19L192.19 405.18L214.82 427.81Z M214.82 412.19L192.19 434.82L185.18 427.81L207.81 405.18Z"/><path class="xx" d="M287.81 434.82L265.18 412.19L272.19 405.18L294.82 427.81Z M294.82 412.19L272.19 434.82L265.18 427.81L287.81 405.18Z"/><path class="xx" d="M367.81 434.82L345.18 412.19L352.19 405.18L374.82 427.81Z M374.82 412.19L352.19 434.82L345.18 427.81L367.81 405.18Z"/><path class="xx" d="M447.81 434.82L425.18 412.19L432.19 405.18L454.82 427.81Z M454.82 412.19L432.19 434.82L425.18 427.81L447.81 405.18Z"/><path class="xx" d="M527.81 434.82L505.18 412.19L512.19 405.18L534.82 427.81Z M534.82 412.19L512.19 434.82L505.18 427.81L527.81 405.18Z"/></svg>
        </div>
        <?php endif; ?>
      </div>
      <div class="hero-figs">
        <?php foreach ((array)$g('hero.figures', []) as $f): ?>
        <div><strong><?= e($f['value'] ?? '') ?></strong><span><?= e($f['text'] ?? '') ?></span><?= src_link($f) ?></div>
        <?php endforeach; ?>
      </div>
    </div>
  </header>

  <section id="evidence">
    <div class="wrap">
      <div class="sec-head">
        <span class="label"><?= e($g('evidence.eyebrow')) ?></span>
        <h2 class="headline"><?= e($g('evidence.title')) ?></h2>
        <p class="lede"><?= e($g('evidence.intro')) ?></p>
      </div>
      <div class="ledger">
        <?php foreach ($shifts as $s): ?>
        <article class="shift">
          <header><h3><?= e($s['name'] ?? '') ?></h3><span class="label"><?= e($s['tag'] ?? '') ?></span></header>
          <?= visual((string)($s['image'] ?? ''), (string)($s['image_alt'] ?? '')) ?>
          <div class="figs">
            <?php foreach ((array)($s['figures'] ?? []) as $f): ?>
            <div class="fig"><strong><?= e($f['value'] ?? '') ?></strong><p><?= e($f['text'] ?? '') ?></p><?= src_link($f) ?></div>
            <?php endforeach; ?>
          </div>
        </article>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <?php if ($g('long_view.title')): ?>
  <section id="long-view">
    <div class="wrap lv">
      <div class="sec-head">
        <span class="label"><?= e($g('long_view.eyebrow')) ?></span>
        <h2 class="headline"><?= e($g('long_view.title')) ?></h2>
        <p class="lede"><?= e($g('long_view.text')) ?></p>
      </div>
      <ol class="moments">
        <?php $mm = (array)$g('long_view.moments', []); foreach ($mm as $i => $m): ?>
        <li<?= $i === count($mm) - 1 ? ' class="now"' : '' ?>><span class="label"><?= e($m['when'] ?? '') ?></span><h3><?= e($m['title'] ?? '') ?></h3><p><?= e($m['text'] ?? '') ?></p></li>
        <?php endforeach; ?>
      </ol>
    </div>
  </section>
  <?php endif; ?>

  <section id="map">
    <div class="wrap map-grid">
      <div class="map-copy">
        <span class="label"><?= e($g('map.eyebrow')) ?></span>
        <h2 class="headline"><?= e($g('map.title')) ?></h2>
        <p class="body-serif"><?= e($g('map.intro')) ?></p>
        <ul class="sep-list"><?php foreach ($seps as $sp): ?><li><?= e(str_replace(' / ', ' and ', (string)$sp)) ?></li><?php endforeach; ?></ul>
        <p class="body-serif"><?= e($g('map.outro')) ?></p>
      </div>
      <div>
        <div class="map-box" id="mapbox">
          <svg viewBox="0 0 800 440" role="img" aria-labelledby="maptitle">
            <title id="maptitle"><?= e($g('map.title')) ?></title>
            <g id="edges"></g><g id="left"></g><g id="right"></g>
          </svg>
        </div>
        <p class="map-hint label"><?= e($g('map.hint')) ?></p>
      </div>
    </div>
    <script type="application/json" id="map-data"><?= json_encode($mapData, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
  </section>

  <section id="experience" class="experience">
    <div class="wrap">
      <div class="sec-head">
        <span class="label"><?= e($g('experience.eyebrow')) ?></span>
        <h2 class="headline"><?= e($g('experience.title')) ?></h2>
        <p class="lede"><?= e($g('experience.intro')) ?></p>
        <?= status_line((string)$g('experience.status')) ?>
      </div>
      <div class="exp-grid">
        <?php foreach ((array)$g('experience.items', []) as $i => $x): $img = af_img((string)($x['image'] ?? '')); ?>
        <article class="exp">
          <?php if ($img): ?><img src="<?= e($img) ?>" alt="<?= e($x['image_alt'] ?? '') ?>" loading="lazy"><?php endif; ?>
          <span class="label"><span class="n"><?= sprintf('%02d', $i + 1) ?></span><?= e($x['label'] ?? '') ?></span>
          <h3><?= e($x['title'] ?? '') ?></h3>
          <p><?= e($x['text'] ?? '') ?></p>
        </article>
        <?php endforeach; ?>
      </div>
      <?php if ($g('experience.note')): ?><p class="quote kicker"><?= e($g('experience.note')) ?></p><?php endif; ?>
    </div>
  </section>

  <?php if ($g('city.show') && af_img((string)$g('city.image'))): ?>
  <section class="city" aria-label="Amsterdam">
    <img src="<?= e(af_img((string)$g('city.image'))) ?>" alt="<?= e($g('city.image_alt')) ?>" loading="lazy">
    <div class="wrap over">
      <blockquote class="quote" style="margin:0"><?= e($g('city.quote')) ?></blockquote>
      <span class="label"><?= e($g('city.caption')) ?></span>
    </div>
  </section>
  <?php endif; ?>

  <section id="days">
    <div class="wrap">
      <div class="sec-head">
        <span class="label edition"><?= e($g('days.eyebrow')) ?></span>
        <h2 class="headline"><?= e($g('days.title')) ?></h2>
        <p class="lede"><?= e($g('days.intro')) ?></p>
        <?= status_line((string)$g('days.status')) ?>
      </div>
      <div class="moves">
        <?php foreach ((array)$g('days.movements', []) as $m): ?>
        <div class="move">
          <span class="when"><?= e($m['when'] ?? '') ?></span>
          <h3><?= e($m['title'] ?? '') ?></h3>
          <ul><?php foreach ((array)($m['items'] ?? []) as $it): ?><li><?= rich((string)$it) ?></li><?php endforeach; ?></ul>
        </div>
        <?php endforeach; ?>
      </div>
      <div class="leave">
        <span class="label"><?= e($g('days.leave_label')) ?></span>
        <ul><?php foreach ((array)$g('days.leave', []) as $it): ?><li><?= rich((string)$it) ?></li><?php endforeach; ?></ul>
      </div>
    </div>
  </section>

  <section id="speakers">
    <div class="wrap">
      <div class="sec-head">
        <span class="label edition"><?= e($g('speakers.eyebrow')) ?></span>
        <h2 class="headline"><?= e($g('speakers.title')) ?></h2>
        <p class="lede"><?= e($g('speakers.intro')) ?></p>
        <?php $kinds = array_filter((array)$g('speakers.kinds', [])); if ($kinds): ?><ul class="kinds"><?php foreach ($kinds as $k): ?><li><?= e($k) ?></li><?php endforeach; ?></ul><?php endif; ?>
        <?= status_line((string)$g('speakers.status')) ?>
      </div>
      <?php $people = (array)$g('speakers.people', []); $anyNamed = count(array_filter($people, fn($p) => trim((string)($p['name'] ?? '')) !== '')) > 0; ?>
      <?php if (!$anyNamed): ?>
      <div class="slot-list">
        <span class="label"><?= e($g('speakers.slots_label', 'The stage, as designed')) ?></span>
        <ol><?php foreach ($people as $p): ?><li><?= e($p['slot'] ?? '') ?></li><?php endforeach; ?></ol>
      </div>
      <?php else: ?>
      <div class="speakers">
        <?php foreach ((array)$g('speakers.people', []) as $i => $p): $img = af_img((string)($p['image'] ?? '')); $named = trim((string)($p['name'] ?? '')) !== ''; ?>
        <article class="speaker<?= $named ? '' : ' tba' ?><?= str_contains(strtolower((string)($p['slot'] ?? '')), 'opening') || str_contains(strtolower((string)($p['slot'] ?? '')), 'closing') ? ' key' : '' ?>">
          <div class="portrait">
            <?php if ($img): ?><img src="<?= e($img) ?>" alt="<?= e($p['image_alt'] ?? ($p['name'] ?? '')) ?>" loading="lazy">
            <?php else: ?><svg viewBox="0 0 120 120" aria-hidden="true"><?php $pp=[[20,60],[60,20],[100,60],[60,100],[100,100]][$i % 5]; ?><?php if (!($pp[0]==20 && $pp[1]==20)): ?><path class="xs" d="M24.39 28.33L11.67 15.61L15.61 11.67L28.33 24.39Z M28.33 15.61L15.61 28.33L11.67 24.39L24.39 11.67Z"/><?php endif; ?><?php if (!($pp[0]==20 && $pp[1]==60)): ?><path class="xs" d="M24.39 68.33L11.67 55.61L15.61 51.67L28.33 64.39Z M28.33 55.61L15.61 68.33L11.67 64.39L24.39 51.67Z"/><?php endif; ?><?php if (!($pp[0]==20 && $pp[1]==100)): ?><path class="xs" d="M24.39 108.33L11.67 95.61L15.61 91.67L28.33 104.39Z M28.33 95.61L15.61 108.33L11.67 104.39L24.39 91.67Z"/><?php endif; ?><?php if (!($pp[0]==60 && $pp[1]==20)): ?><path class="xs" d="M64.39 28.33L51.67 15.61L55.61 11.67L68.33 24.39Z M68.33 15.61L55.61 28.33L51.67 24.39L64.39 11.67Z"/><?php endif; ?><?php if (!($pp[0]==60 && $pp[1]==60)): ?><path class="xs" d="M64.39 68.33L51.67 55.61L55.61 51.67L68.33 64.39Z M68.33 55.61L55.61 68.33L51.67 64.39L64.39 51.67Z"/><?php endif; ?><?php if (!($pp[0]==60 && $pp[1]==100)): ?><path class="xs" d="M64.39 108.33L51.67 95.61L55.61 91.67L68.33 104.39Z M68.33 95.61L55.61 108.33L51.67 104.39L64.39 91.67Z"/><?php endif; ?><?php if (!($pp[0]==100 && $pp[1]==20)): ?><path class="xs" d="M104.39 28.33L91.67 15.61L95.61 11.67L108.33 24.39Z M108.33 15.61L95.61 28.33L91.67 24.39L104.39 11.67Z"/><?php endif; ?><?php if (!($pp[0]==100 && $pp[1]==60)): ?><path class="xs" d="M104.39 68.33L91.67 55.61L95.61 51.67L108.33 64.39Z M108.33 55.61L95.61 68.33L91.67 64.39L104.39 51.67Z"/><?php endif; ?><?php if (!($pp[0]==100 && $pp[1]==100)): ?><path class="xs" d="M104.39 108.33L91.67 95.61L95.61 91.67L108.33 104.39Z M108.33 95.61L95.61 108.33L91.67 104.39L104.39 91.67Z"/><?php endif; ?><g transform="translate(<?= $pp[0] ?> <?= $pp[1] ?>)"><path class="sq" d="M9.90 3.40L-9.90 3.40L-9.90 -3.40L9.90 -3.40Z M-3.40 9.90L-3.40 -9.90L3.40 -9.90L3.40 9.90Z"/></g></svg><?php endif; ?>
          </div>
          <span class="label"><?= e($p['slot'] ?? '') ?></span>
          <h3><?= $named ? e($p['name']) : e($g('speakers.placeholder', 'To be announced')) ?></h3>
          <?php if (!empty($p['role'])): ?><p><?= e($p['role']) ?></p><?php endif; ?>
        </article>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
      <div class="speakers-cta">
        <p class="body-serif"><?= e($g('speakers.cta_text')) ?></p>
        <a class="btn ghost" href="#apply" data-tab="speaker"><?= e($g('speakers.cta_button')) ?></a>
      </div>
    </div>
  </section>

  <section id="different">
    <div class="wrap">
      <div class="sec-head">
        <span class="label"><?= e($g('different.eyebrow')) ?></span>
        <h2 class="headline"><?= e($g('different.title')) ?></h2>
      </div>
      <div class="versus">
        <table>
          <thead><tr><th scope="col"></th><th scope="col"><?= e($g('different.column_usual')) ?></th><th scope="col"><?= e($g('different.column_forum')) ?></th></tr></thead>
          <tbody>
            <?php foreach ((array)$g('different.rows', []) as $r): ?>
            <tr><td><?= e($r['label'] ?? '') ?></td><td><?= e($r['usual'] ?? '') ?></td><td><?= e($r['forum'] ?? '') ?></td></tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <p class="quote kicker"><?= e($g('different.kicker')) ?></p>
    </div>
  </section>

  <section id="room">
    <div class="wrap">
      <div class="sec-head">
        <span class="label"><?= e($g('room.eyebrow')) ?></span>
        <h2 class="headline"><?= e($g('room.title')) ?></h2>
        <p class="lede"><?= e($g('room.intro')) ?></p>
      </div>
      <?php $segs = (array)$g('room.segments', []); ?>
      <div class="room-bar" role="img" aria-label="<?= e(implode(', ', array_map(fn($s) => ($s['number'] ?? '') . ' ' . ($s['text'] ?? ''), $segs))) ?>">
        <?php foreach ($segs as $s): $n = max(1, (int)preg_replace('/\D/', '', (string)($s['number'] ?? '1'))); ?><div style="flex:<?= $n ?>"><?= e($s['number'] ?? '') ?></div><?php endforeach; ?>
      </div>
      <div class="room-legend">
        <?php foreach ($segs as $s): ?><div><strong><?= e($s['number'] ?? '') ?></strong><span><?= e($s['text'] ?? '') ?></span></div><?php endforeach; ?>
      </div>
      <?php $crit = array_filter((array)$g('room.criteria', [])); if ($crit): ?>
      <div class="criteria">
        <span class="label"><?= e($g('room.criteria_label', 'How the room is composed')) ?></span>
        <ul><?php foreach ($crit as $it): ?><li><?= rich((string)$it) ?></li><?php endforeach; ?></ul>
      </div>
      <?php endif; ?>
    </div>
  </section>

  <?php if ($g('circles.title')): ?>
  <section id="circles" class="circles">
    <div class="wrap circles-grid">
      <div class="circles-copy">
        <span class="label"><?= e($g('circles.eyebrow')) ?></span>
        <h2 class="headline"><?= e($g('circles.title')) ?></h2>
        <p class="lede"><?= e($g('circles.text')) ?></p>
        <?php if ($g('circles.text_2')): ?><p class="body-serif"><?= e($g('circles.text_2')) ?></p><?php endif; ?>
        <?php $cities = array_filter((array)$g('circles.cities', [])); if ($cities): ?>
        <div class="cities"><span class="label"><?= e($g('circles.cities_label', 'Where Circles could meet')) ?></span><p><?php foreach (array_values($cities) as $i => $city): ?><span><?= e($city) ?></span> <?php endforeach; ?></p></div>
        <?php endif; ?>
        <?= status_line((string)$g('circles.status')) ?>
        <div class="ctas">
          <a class="btn" href="#apply" data-tab="circle"><?= e($g('circles.button')) ?></a>
        </div>
      </div>
      <div class="circles-side">
        <svg class="table8" viewBox="0 0 200 200" aria-hidden="true">
          <circle class="tbl" cx="100" cy="100" r="44"/>
          <circle class="seat" cx="127.6" cy="33.5" r="11"/><circle class="seat you" cx="166.5" cy="72.4" r="11"/><circle class="seat" cx="166.5" cy="127.6" r="11"/><circle class="seat" cx="127.6" cy="166.5" r="11"/><circle class="seat" cx="72.4" cy="166.5" r="11"/><circle class="seat" cx="33.5" cy="127.6" r="11"/><circle class="seat" cx="33.5" cy="72.4" r="11"/><circle class="seat" cx="72.4" cy="33.5" r="11"/>
        </svg>
        <div class="circle-facts">
          <?php foreach ((array)$g('circles.facts', []) as $f): ?><div><strong><?= e($f['number'] ?? '') ?></strong><span><?= e($f['text'] ?? '') ?></span></div><?php endforeach; ?>
        </div>
      </div>
    </div>
  </section>
  <?php endif; ?>

  <?php if ($g('movement.title')): ?>
  <section id="movement" class="movement">
    <div class="wrap mv">
      <div class="mv-mark" aria-hidden="true">
        <svg viewBox="0 0 40 123"><g transform="translate(0 1.5)"><path d="M30.25 37.45L0.55 7.75L9.75 -1.45L39.45 28.25Z M39.45 7.75L9.75 37.45L0.55 28.25L30.25 -1.45Z"/><path d="M30.25 79.45L0.55 49.75L9.75 40.55L39.45 70.25Z M39.45 49.75L9.75 79.45L0.55 70.25L30.25 40.55Z"/><path class="mv-pl" d="M38.90 108.50L1.10 108.50L1.10 95.50L38.90 95.50Z M13.50 120.90L13.50 83.10L26.50 83.10L26.50 120.90Z"/></g></svg>
      </div>
      <div class="mv-body">
        <span class="label"><?= e($g('movement.eyebrow')) ?></span>
        <h2><?= e($g('movement.title')) ?></h2>
        <p class="lede"><?= e($g('movement.text')) ?></p>
        <?php if ($g('movement.text_2')): ?><p class="body-serif"><?= e($g('movement.text_2')) ?></p><?php endif; ?>
        <div class="ctas">
          <?php if ($g('movement.button_1')): ?><a class="btn" href="<?= e(af_url((string)$g('movement.button_1_href', '#apply'))) ?>"<?= str_starts_with((string)$g('movement.button_1_href', '#apply'), '#apply') ? ' data-tab="invite"' : '' ?>><?= e($g('movement.button_1')) ?></a><?php endif; ?>
          <?php if ($g('movement.button_2')): ?><a class="btn ghost" href="<?= e(af_url((string)$g('movement.button_2_href', '#weak-signal'))) ?>"><?= e($g('movement.button_2')) ?></a><?php endif; ?>
        </div>
      </div>
    </div>
  </section>
  <?php endif; ?>

  <section id="apply" class="apply">
    <div class="wrap">
      <div class="sec-head">
        <span class="label"><?= e($g('apply.eyebrow')) ?></span>
        <h2 class="headline"><?= e($g('apply.title')) ?></h2>
      </div>
      <div class="tabs" role="tablist">
        <button class="tab" role="tab" id="t-invite" aria-controls="p-invite" aria-selected="true" data-tab="invite"><?= e($g('apply.invite.tab')) ?></button>
        <button class="tab" role="tab" id="t-partner" aria-controls="p-partner" aria-selected="false" data-tab="partner"><?= e($g('apply.partner.tab')) ?></button>
        <button class="tab" role="tab" id="t-speaker" aria-controls="p-speaker" aria-selected="false" data-tab="speaker"><?= e($g('apply.speaker.tab')) ?></button>
        <?php if ($g('apply.circle.tab')): ?><button class="tab" role="tab" id="t-circle" aria-controls="p-circle" aria-selected="false" data-tab="circle"><?= e($g('apply.circle.tab')) ?></button><?php endif; ?>
      </div>

      <div class="panel" role="tabpanel" id="p-invite" aria-labelledby="t-invite">
        <div class="about">
          <h3><?= e($g('apply.invite.title')) ?></h3>
          <p><?= e($g('apply.invite.text_1')) ?></p>
          <p><?= e($g('apply.invite.text_2')) ?></p>
        </div>
        <form data-kind="invitation" data-thanks="<?= e($g('apply.invite.thanks')) ?>" novalidate>
          <div class="field"><label for="i-name">Full name</label><input id="i-name" name="name" required autocomplete="name" maxlength="120"></div>
          <div class="field"><label for="i-email">Email</label><input id="i-email" name="email" type="email" required autocomplete="email" maxlength="160"></div>
          <div class="field"><label for="i-org">Organisation</label><input id="i-org" name="organisation" required autocomplete="organization" maxlength="160"></div>
          <div class="field"><label for="i-role">Role</label><input id="i-role" name="role" required autocomplete="organization-title" maxlength="160"></div>
          <div class="field"><label for="i-country">Country</label><input id="i-country" name="country" autocomplete="country-name" maxlength="80"></div>
          <div class="field"><label for="i-domain">Your domain</label><select id="i-domain" name="domain"><?php foreach ((array)$g('apply.invite.domains', []) as $d): ?><option><?= e($d) ?></option><?php endforeach; ?></select></div>
          <div class="field full"><label for="i-why"><?= e($g('apply.invite.question')) ?></label><textarea id="i-why" name="note" required maxlength="3000"></textarea></div>
          <div class="field"><label for="i-size">Seats</label><select id="i-size" name="seats"><option value="1">Just me</option><option value="2">Two from my organisation</option><option value="3">Three from my organisation</option></select></div>
          <div class="hp" aria-hidden="true"><label for="i-web">Website</label><input id="i-web" name="website" tabindex="-1" autocomplete="off"></div>
          <div class="form-foot"><small><?= e($g('apply.invite.privacy')) ?> <?= $privacyLink ?></small><button class="btn" type="submit"><?= e($g('apply.invite.button')) ?></button></div>
          <div class="notice" role="status" hidden></div>
        </form>
      </div>

      <div class="panel" role="tabpanel" id="p-partner" aria-labelledby="t-partner" hidden>
        <div class="about">
          <h3><?= e($g('apply.partner.title')) ?></h3>
          <p><?= e($g('apply.partner.text_1')) ?></p>
          <div class="tiers"><?php foreach ((array)$g('apply.partner.tiers', []) as $t): ?><div><span><?= e($t['name'] ?? '') ?></span><b><?= e($t['price'] ?? '') ?></b></div><?php endforeach; ?></div>
          <p><?= e($g('apply.partner.text_2')) ?></p>
        </div>
        <form data-kind="partner" data-thanks="<?= e($g('apply.partner.thanks')) ?>" novalidate>
          <div class="field"><label for="p-name">Full name</label><input id="p-name" name="name" required autocomplete="name" maxlength="120"></div>
          <div class="field"><label for="p-email">Email</label><input id="p-email" name="email" type="email" required autocomplete="email" maxlength="160"></div>
          <div class="field"><label for="p-org">Organisation</label><input id="p-org" name="organisation" required autocomplete="organization" maxlength="160"></div>
          <div class="field"><label for="p-role">Role</label><input id="p-role" name="role" autocomplete="organization-title" maxlength="160"></div>
          <div class="field full"><label for="p-tier">Interested in</label><select id="p-tier" name="tier"><?php foreach ((array)$g('apply.partner.tiers', []) as $t): ?><option><?= e(($t['name'] ?? '') . ' · ' . ($t['price'] ?? '')) ?></option><?php endforeach; ?><option>Underwriting Fellow seats</option><option>Not sure yet</option></select></div>
          <div class="field full"><label for="p-why"><?= e($g('apply.partner.question')) ?></label><textarea id="p-why" name="note" maxlength="3000"></textarea></div>
          <div class="hp" aria-hidden="true"><label for="p-web">Website</label><input id="p-web" name="website" tabindex="-1" autocomplete="off"></div>
          <div class="form-foot"><small><?= e($g('apply.partner.privacy')) ?> <?= $privacyLink ?></small><button class="btn" type="submit"><?= e($g('apply.partner.button')) ?></button></div>
          <div class="notice" role="status" hidden></div>
        </form>
      </div>

      <div class="panel" role="tabpanel" id="p-speaker" aria-labelledby="t-speaker" hidden>
        <div class="about">
          <h3><?= e($g('apply.speaker.title')) ?></h3>
          <p><?= e($g('apply.speaker.text_1')) ?></p>
          <p><?= e($g('apply.speaker.text_2')) ?></p>
        </div>
        <form data-kind="speaker" data-thanks="<?= e($g('apply.speaker.thanks')) ?>" novalidate>
          <div class="field"><label for="s-name">Your name</label><input id="s-name" name="name" required autocomplete="name" maxlength="120"></div>
          <div class="field"><label for="s-email">Your email</label><input id="s-email" name="email" type="email" required autocomplete="email" maxlength="160"></div>
          <div class="field"><label for="s-who">Speaker (if not you)</label><input id="s-who" name="speaker" maxlength="160"></div>
          <div class="field"><label for="s-role">Their role and organisation</label><input id="s-role" name="role" maxlength="160"></div>
          <div class="field"><label for="s-format">Format</label><select id="s-format" name="format"><option>Keynote</option><option>Panel</option><option>Either</option></select></div>
          <div class="field"><label for="s-shift">Which shift</label><select id="s-shift" name="shift"><?php foreach ($shifts as $sh): ?><option><?= e($sh['name'] ?? '') ?></option><?php endforeach; ?></select></div>
          <div class="field full"><label for="s-note"><?= e($g('apply.speaker.question')) ?></label><textarea id="s-note" name="note" required maxlength="3000"></textarea></div>
          <div class="field full"><label for="s-link">Link to a talk or bio</label><input id="s-link" name="link" type="url" maxlength="300" placeholder="https://"></div>
          <div class="hp" aria-hidden="true"><label for="s-web">Website</label><input id="s-web" name="website" tabindex="-1" autocomplete="off"></div>
          <div class="form-foot"><small><?= e($g('apply.speaker.privacy')) ?> <?= $privacyLink ?></small><button class="btn" type="submit"><?= e($g('apply.speaker.button')) ?></button></div>
          <div class="notice" role="status" hidden></div>
        </form>
      </div>
      <?php if ($g('apply.circle.tab')): ?>
      <div class="panel" role="tabpanel" id="p-circle" aria-labelledby="t-circle" hidden>
        <div class="about">
          <h3><?= e($g('apply.circle.title')) ?></h3>
          <p><?= e($g('apply.circle.text_1')) ?></p>
          <p><?= e($g('apply.circle.text_2')) ?></p>
        </div>
        <form data-kind="circle" data-thanks="<?= e($g('apply.circle.thanks')) ?>" novalidate>
          <div class="field"><label for="c-name">Your name</label><input id="c-name" name="name" required autocomplete="name" maxlength="120"></div>
          <div class="field"><label for="c-email">Your email</label><input id="c-email" name="email" type="email" required autocomplete="email" maxlength="160"></div>
          <div class="field"><label for="c-city">City</label><input id="c-city" name="city" required autocomplete="address-level2" maxlength="80"></div>
          <div class="field"><label for="c-country">Country</label><input id="c-country" name="country" autocomplete="country-name" maxlength="80"></div>
          <div class="field full"><label for="c-role">Your role and organisation</label><input id="c-role" name="role" autocomplete="organization-title" maxlength="160"></div>
          <div class="field full"><label for="c-note"><?= e($g('apply.circle.question')) ?></label><textarea id="c-note" name="note" required maxlength="3000"></textarea></div>
          <div class="hp" aria-hidden="true"><label for="c-web">Website</label><input id="c-web" name="website" tabindex="-1" autocomplete="off"></div>
          <div class="form-foot"><small><?= e($g('apply.circle.privacy')) ?> <?= $privacyLink ?></small><button class="btn" type="submit"><?= e($g('apply.circle.button')) ?></button></div>
          <div class="notice" role="status" hidden></div>
        </form>
      </div>
      <?php endif; ?>
    </div>
  </section>

  <section id="weak-signal">
    <div class="wrap ws">
      <div class="ws-config" aria-hidden="true"><svg viewBox="0 0 200 200"><path class="stn" d="M23.91 27.41L12.59 16.09L16.09 12.59L27.41 23.91Z M27.41 16.09L16.09 27.41L12.59 23.91L23.91 12.59Z"/><path class="stn" d="M63.91 27.41L52.59 16.09L56.09 12.59L67.41 23.91Z M67.41 16.09L56.09 27.41L52.59 23.91L63.91 12.59Z"/><path class="stn" d="M103.91 27.41L92.59 16.09L96.09 12.59L107.41 23.91Z M107.41 16.09L96.09 27.41L92.59 23.91L103.91 12.59Z"/><path class="stn" d="M143.91 27.41L132.59 16.09L136.09 12.59L147.41 23.91Z M147.41 16.09L136.09 27.41L132.59 23.91L143.91 12.59Z"/><path class="stn" d="M183.91 27.41L172.59 16.09L176.09 12.59L187.41 23.91Z M187.41 16.09L176.09 27.41L172.59 23.91L183.91 12.59Z"/><path class="stn" d="M23.91 67.41L12.59 56.09L16.09 52.59L27.41 63.91Z M27.41 56.09L16.09 67.41L12.59 63.91L23.91 52.59Z"/><path class="stn" d="M63.91 67.41L52.59 56.09L56.09 52.59L67.41 63.91Z M67.41 56.09L56.09 67.41L52.59 63.91L63.91 52.59Z"/><path class="stn" d="M103.91 67.41L92.59 56.09L96.09 52.59L107.41 63.91Z M107.41 56.09L96.09 67.41L92.59 63.91L103.91 52.59Z"/><path class="stn" d="M183.91 67.41L172.59 56.09L176.09 52.59L187.41 63.91Z M187.41 56.09L176.09 67.41L172.59 63.91L183.91 52.59Z"/><path class="stn" d="M23.91 107.41L12.59 96.09L16.09 92.59L27.41 103.91Z M27.41 96.09L16.09 107.41L12.59 103.91L23.91 92.59Z"/><path class="stn" d="M63.91 107.41L52.59 96.09L56.09 92.59L67.41 103.91Z M67.41 96.09L56.09 107.41L52.59 103.91L63.91 92.59Z"/><path class="stn" d="M103.91 107.41L92.59 96.09L96.09 92.59L107.41 103.91Z M107.41 96.09L96.09 107.41L92.59 103.91L103.91 92.59Z"/><path class="stn" d="M143.91 107.41L132.59 96.09L136.09 92.59L147.41 103.91Z M147.41 96.09L136.09 107.41L132.59 103.91L143.91 92.59Z"/><path class="stn" d="M183.91 107.41L172.59 96.09L176.09 92.59L187.41 103.91Z M187.41 96.09L176.09 107.41L172.59 103.91L183.91 92.59Z"/><path class="stn" d="M23.91 147.41L12.59 136.09L16.09 132.59L27.41 143.91Z M27.41 136.09L16.09 147.41L12.59 143.91L23.91 132.59Z"/><path class="stn" d="M63.91 147.41L52.59 136.09L56.09 132.59L67.41 143.91Z M67.41 136.09L56.09 147.41L52.59 143.91L63.91 132.59Z"/><path class="stn" d="M103.91 147.41L92.59 136.09L96.09 132.59L107.41 143.91Z M107.41 136.09L96.09 147.41L92.59 143.91L103.91 132.59Z"/><path class="stn" d="M143.91 147.41L132.59 136.09L136.09 132.59L147.41 143.91Z M147.41 136.09L136.09 147.41L132.59 143.91L143.91 132.59Z"/><path class="stn" d="M183.91 147.41L172.59 136.09L176.09 132.59L187.41 143.91Z M187.41 136.09L176.09 147.41L172.59 143.91L183.91 132.59Z"/><path class="stn" d="M23.91 187.41L12.59 176.09L16.09 172.59L27.41 183.91Z M27.41 176.09L16.09 187.41L12.59 183.91L23.91 172.59Z"/><path class="stn" d="M63.91 187.41L52.59 176.09L56.09 172.59L67.41 183.91Z M67.41 176.09L56.09 187.41L52.59 183.91L63.91 172.59Z"/><path class="stn" d="M103.91 187.41L92.59 176.09L96.09 172.59L107.41 183.91Z M107.41 176.09L96.09 187.41L92.59 183.91L103.91 172.59Z"/><path class="stn" d="M143.91 187.41L132.59 176.09L136.09 172.59L147.41 183.91Z M147.41 176.09L136.09 187.41L132.59 183.91L143.91 172.59Z"/><path class="stn" d="M183.91 187.41L172.59 176.09L176.09 172.59L187.41 183.91Z M187.41 176.09L176.09 187.41L172.59 183.91L183.91 172.59Z"/><path class="sig" d="M161.00 47.10L143.00 47.10L143.00 40.90L161.00 40.90Z M148.90 53.00L148.90 35.00L155.10 35.00L155.10 53.00Z"/></svg></div>
      <div class="body">
        <span class="label"><?= e($g('weak_signal.eyebrow')) ?></span>
        <h2 class="headline"><?= e($g('weak_signal.title')) ?></h2>
        <p class="body-serif"><?= e($g('weak_signal.text')) ?></p>
        <?php if ($g('weak_signal.text_2')): ?><p class="ws-between"><?= e($g('weak_signal.text_2')) ?></p><?php endif; ?>
      </div>
      <form data-kind="weak-signal" data-thanks="<?= e($g('weak_signal.thanks')) ?>" novalidate>
        <div class="field"><label for="ws-email">Email</label><input id="ws-email" name="email" type="email" required autocomplete="email" maxlength="160"></div>
        <button class="btn" type="submit"><?= e($g('weak_signal.button')) ?></button>
        <div class="hp" aria-hidden="true"><label for="ws-web">Website</label><input id="ws-web" name="website" tabindex="-1" autocomplete="off"></div>
        <div class="notice" role="status" hidden></div>
        <?php if ($g('weak_signal.signin_link')): ?><p class="ws-signin"><?= e($g('weak_signal.signin_text')) ?> <a href="signal/"><?= e($g('weak_signal.signin_link')) ?></a></p><?php endif; ?>
      </form>
    </div>
  </section>
<?php endif; ?>
</main>

<footer>
  <div class="wrap">
    <div>
      <img class="logo-light" src="assets/logo/xxaf-primary-reversed.svg" alt="Amsterdam Forum" height="72">
      <img class="logo-dark" src="assets/logo/xxaf-primary-ink.svg" alt="Amsterdam Forum" height="72">
      <div class="meta">
        <span><?= e($g('footer.line_1')) ?></span>
        <span><?= e($g('footer.line_2')) ?></span>
        <?= $privacyLink ?>
        <?php if ($g('site.contact_email')): ?><a href="mailto:<?= e($g('site.contact_email')) ?>"><?= e($g('site.contact_email')) ?></a><?php endif; ?>
      </div>
    </div>
    <?php if (!isset($_GET['privacy'])): ?><p class="note"><?= e($g('footer.note')) ?></p><?php endif; ?>
  </div>
</footer>
<script src="assets/site.js?v=<?= @filemtime(__DIR__ . '/assets/site.js') ?>" defer></script>
</body>
</html>
