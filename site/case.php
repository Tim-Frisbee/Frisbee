<?php
declare(strict_types=1);
require __DIR__ . '/inc/layout.php';

$slug = (string)($_GET['c'] ?? '');
$case = find_case($slug);
$admin = is_admin();
if (!$case || (empty($case['zichtbaar']) && !$admin)) {
    http_response_code(404);
    page_start('Pagina niet gevonden', '');
    echo '<main class="wrap" style="padding-block:120px;display:grid;gap:20px;justify-items:start"><span class="eyebrow">404</span><h1 style="font-size:clamp(2.4rem,6vw,4rem)">Deze case bestaat niet (meer).</h1><a class="btn" href="./#realisaties">Bekijk alle realisaties <span class="arr">→</span></a></main>';
    site_footer(); page_end(); exit;
}

// Next visible case (wraps around)
$list = visible_cases();
$idx = array_search($case['slug'], array_column($list, 'slug'), true);
$next = $list ? $list[(($idx === false ? -1 : $idx) + 1) % count($list)] : null;
if ($next && $next['slug'] === $case['slug']) $next = null;

$color = safe_hex($case['kleur'] ?? '');
$light = is_light($color);
$hint = fn(string $label, string $text, bool $onColor = false) => $admin
    ? '<div class="slot' . ($onColor ? ' on-color' : '') . '">' . ($label ? '<b>' . h($label) . '</b>' : '') . '<span>' . h($text) . '</span></div>' : '';

page_start($case['naam'] . ' · ' . (content()['site']['naam'] ?? ''), $case['intro'] ?: ($case['naam'] . ' — realisatie door ' . (content()['site']['naam'] ?? '')), '?p=case&slug=' . rawurlencode($case['slug']));

$meta = [];
$meta[] = ['Klant', $case['klant'] ? h($case['klant']) : $hint('', 'Naam klant', true)];
$meta[] = ['Diensten', $case['diensten'] ? '<span class="chips">' . implode('', array_map(fn($d) => '<span class="chip-s">' . h($d) . '</span>', $case['diensten'])) . '</span>' : $hint('', 'Logo, website, …', true)];
$meta[] = ['Jaar', $case['jaar'] ? h($case['jaar']) : $hint('', 'Jaartal', true)];
$site = safe_url($case['website'] ?? '');
$meta[] = ['Website', $site ? '<a href="' . h($site) . '" rel="noopener">' . h(preg_replace('#^https?://(www\.)?|/$#', '', $site)) . '</a>' : $hint('', 'Link naar site', true)];
$meta = array_filter($meta, fn($m) => $m[1] !== '');

$hero = safe_img($case['hoofdbeeld'] ?? '');
$logo = safe_img($case['logo'] ?? '');
$gal = array_values(array_filter(array_map('safe_img', $case['galerij'] ?? [])));
$n = count($gal);
$lastFit = function (int $n): string {
    $d = $n === 1 ? ' ld3' : ($n > 2 ? ([1 => ' ld3', 2 => ' ld2'][($n - 3) % 3] ?? '') : '');
    $m = ($n <= 2 || ($n - 1) % 2 === 1) ? ' lm2' : '';
    return $d . $m;
};
$kleuren = array_values(array_filter($case['kleuren'] ?? [], fn($k) => preg_match('/^#[0-9a-f]{6}$/i', $k['hex'] ?? '')));
?>
<main id="app" class="case<?= $light ? ' light' : '' ?>" style="--c:<?= h($color) ?>">
  <?php if (empty($case['zichtbaar'])): ?><div class="draft-note">Deze case is verborgen en alleen zichtbaar omdat je ingelogd bent.</div><?php endif; ?>
  <header class="case-hero">
    <div class="wrap">
      <nav class="crumbs" aria-label="Kruimelpad"><a href="./">Home</a><span aria-hidden="true">/</span><a href="./#realisaties">Realisaties</a><span aria-hidden="true">/</span><span><?= h($case['naam']) ?></span></nav>
      <div class="case-head">
        <div><?php if ($case['tagline']): ?><span class="case-tagline"><?= h($case['tagline']) ?></span><?php endif; ?><h1 class="case-title"><?= h($case['naam']) ?></h1></div>
        <div><?= $case['intro'] ? '<p class="case-intro">' . h($case['intro']) . '</p>' : $hint('Intro', 'Eén of twee zinnen: wie is de klant en wat hebben we voor hen gemaakt?', true) ?></div>
      </div>
      <?php if ($meta): ?><dl class="meta"><?php foreach ($meta as [$k, $v]): ?><div><dt><?= $k ?></dt><dd><?= $v ?></dd></div><?php endforeach; ?></dl><?php endif; ?>
    </div>
  </header>
  <div class="case-visual wrap">
    <?php if ($hero): ?><div class="frame" id="frame"><img src="<?= h($hero) ?>" alt="<?= h($case['naam']) ?>"></div>
    <?php elseif ($logo): ?><div class="frame logo" id="frame"><img src="<?= h($logo) ?>" alt="Logo <?= h($case['naam']) ?>"></div><?php endif; ?>
  </div>

  <?php
  $story = [['De vraag', 'vraag', 'Wat had de klant nodig? Welk probleem wilden ze oplossen?'], ['Onze aanpak', 'aanpak', 'Hoe zijn we te werk gegaan? Welke keuzes hebben we gemaakt en waarom?'], ['Het resultaat', 'resultaat', 'Wat is er opgeleverd en wat heeft het de klant opgebracht?']];
  $rows = '';
  foreach ($story as [$title, $key, $help]) {
      if (!empty($case[$key])) $rows .= '<div class="story-row reveal"><h2>' . $title . '</h2><div class="txt">' . paras($case[$key]) . '</div></div>';
      elseif ($admin) $rows .= '<div class="story-row"><h2>' . $title . '</h2>' . $hint('Nog invullen', $help) . '</div>';
  }
  if ($rows): ?>
  <section class="case-section"><div class="wrap"><div class="story"><?= $rows ?></div></div></section>
  <?php endif; ?>

  <?php if ($n || $admin): ?>
  <section class="case-section"><div class="wrap">
    <div class="sec-head reveal"><span class="eyebrow">Beelden</span><h2>Het werk in beeld.</h2></div>
    <?php if ($n): ?>
    <div class="gallery" id="gallery">
      <?php foreach ($gal as $i => $src):
        $cls = ($i === 0 && $n > 2 ? ' big' : '') . ($i === 0 && $n === 2 ? ' wide' : '') . ($i === $n - 1 ? $lastFit($n) : ''); ?>
      <button type="button" class="shot reveal<?= $cls ?>" data-src="<?= h($src) ?>" aria-label="Bekijk beeld <?= $i + 1 ?> van <?= $n ?>"><img src="<?= h($src) ?>" alt="" loading="lazy"></button>
      <?php endforeach; ?>
    </div>
    <?php else: echo $hint('Galerij', 'Voeg in het beheer beelden toe: mockups, logovarianten, schermen of foto\'s van de oplevering.'); endif; ?>
  </div></section>
  <?php endif; ?>

  <?php $font = trim($case['lettertype_naam'] ?? ''); if ($kleuren || $font || $admin): ?>
  <section class="case-section"><div class="wrap">
    <div class="sec-head reveal"><span class="eyebrow">Merkidentiteit</span><h2>Kleuren en typografie.</h2></div>
    <div class="kit<?= (!$font && !$admin) || (!$kleuren && !$admin) ? ' single' : '' ?>">
      <?php if ($kleuren): ?>
      <div class="swatches"><?php foreach ($kleuren as $k): $hx = safe_hex($k['hex']); ?>
        <button type="button" class="swatch" style="--sw:<?= h($hx) ?>;--sw-on:<?= is_light($hx) ? '#160C24' : '#fff' ?>" data-hex="<?= h(strtoupper($hx)) ?>" aria-label="Kopieer <?= h(strtoupper($hx)) ?>"><b><?= h($k['naam'] ?? '') ?></b><span><?= h(strtoupper($hx)) ?></span></button>
      <?php endforeach; ?></div>
      <?php else: echo $hint('Kleurenpalet', 'Voeg in het beheer de merkkleuren toe.'); endif; ?>
      <?php if ($font): ?>
      <div class="type-card"><small>Lettertype</small><div class="aa">Aa</div><p><b style="color:#fff"><?= h($font) ?></b><br><?= h($case['lettertype_gewichten'] ?? '') ?></p></div>
      <?php else: echo $hint('Lettertype', 'Vul in het beheer de naam en gewichten van het lettertype in.'); endif; ?>
    </div>
  </div></section>
  <?php endif; ?>

  <?php if (!empty($case['citaat_tekst'])): ?>
  <section class="case-section"><div class="wrap">
    <figure class="quote reveal"><blockquote><?= h($case['citaat_tekst']) ?></blockquote><figcaption><i></i><span><b><?= h($case['citaat_naam'] ?? '') ?></b><?= h($case['citaat_functie'] ?? '') ?></span></figcaption></figure>
  </div></section>
  <?php elseif ($admin): ?>
  <section class="case-section"><div class="wrap"><?= $hint('Citaat van de klant', 'Een korte quote maakt de case persoonlijk. Vul tekst, naam en functie in via het beheer.') ?></div></section>
  <?php endif; ?>

  <?php if ($next): $nc = safe_hex($next['kleur'] ?? ''); $nl = is_light($nc); ?>
  <section class="case-section"><div class="wrap">
    <a class="next reveal" href="case.php?c=<?= h(rawurlencode($next['slug'])) ?>" style="--c:<?= h($nc) ?>;<?= $nl ? '--on:#160C24;--on-soft:rgba(22,12,36,.7)' : '--on:#fff;--on-soft:rgba(255,255,255,.7)' ?>">
      <div class="next-in"><div><small>Volgende case</small><h2><?= h($next['naam']) ?> <i aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M5 12h14M13 6l6 6-6 6"/></svg></i></h2></div><?php if ($nlogo = safe_img($next['logo'] ?? '')): ?><img src="<?= h($nlogo) ?>" alt="" loading="lazy"><?php endif; ?></div>
    </a>
  </div></section>
  <?php endif; ?>
</main>

<?php site_cta(); ?>

<div class="lb" id="lb" role="dialog" aria-modal="true" aria-label="Beeld vergroot" aria-hidden="true">
  <img id="lbImg" alt="">
  <button type="button" class="x" id="lbClose" aria-label="Sluiten">✕</button>
  <button type="button" class="prev" id="lbPrev" aria-label="Vorige">←</button>
  <button type="button" class="nx" id="lbNext" aria-label="Volgende">→</button>
  <span class="count" id="lbCount"></span>
</div>
<?php site_footer(); page_end();
