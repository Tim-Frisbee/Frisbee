<?php
declare(strict_types=1);
require __DIR__ . '/inc/layout.php';

$d = find_dienst((string)($_GET['d'] ?? ''));
$admin = is_admin();
if (!$d || (!$d['zichtbaar'] && !$admin)) {
    http_response_code(404);
    page_start('Pagina niet gevonden', '');
    echo '<main class="wrap" style="padding-block:120px;display:grid;gap:20px;justify-items:start"><span class="eyebrow">404</span><h1 style="font-size:clamp(2.4rem,6vw,4rem)">Deze dienst bestaat niet (meer).</h1><a class="btn" href="./#diensten">Bekijk al onze diensten <span class="arr">→</span></a></main>';
    site_footer(); page_end(); exit;
}

$hint = fn(string $label, string $text) => $admin ? '<div class="slot"><b>' . h($label) . '</b><span>' . h($text) . '</span></div>' : '';
$title = $d['pagina_titel'] ?: $d['titel'];
$hero = safe_img($d['hoofdbeeld']) ?: safe_img($d['afbeelding']);
$ill = preg_replace('/[^a-z]/', '', $d['illustratie']);
$illFile = $ill ? __DIR__ . "/inc/illustraties/$ill.svg" : '';
$cases = dienst_cases($d);
$others = array_values(array_filter(visible_diensten(), fn($x) => $x['id'] !== $d['id']));
$siteName = content()['site']['naam'] ?? '';

page_start($d['titel'] . ' · ' . $siteName, $d['tekst'] ?: $d['titel'], '?p=dienst&id=' . rawurlencode($d['id']));
?>
<main id="top" class="dienst">
  <?php if (!$d['zichtbaar']): ?><div class="draft-note">Deze dienst is verborgen en alleen zichtbaar omdat je ingelogd bent.</div><?php endif; ?>
  <div class="hero">
    <div class="stage" id="stage">
      <div class="stage-in wrap">
        <div class="hero-copy">
          <nav class="crumbs" aria-label="Kruimelpad"><a href="./">Home</a><span aria-hidden="true">/</span><a href="./#diensten">Diensten</a><span aria-hidden="true">/</span><span><?= h($d['titel']) ?></span></nav>
          <span class="eyebrow"><?= h($d['titel']) ?></span>
          <h1 class="dienst-title"><?= accent($title, 'alive') ?></h1>
          <?php if ($d['intro']): ?><div class="hero-lead dienst-intro"><?= paras($d['intro']) ?></div>
          <?php else: ?><p class="hero-lead"><?= h($d['tekst']) ?></p><?= $admin ? '<div class="slot on-dark"><b>Intro</b><span>Vertel in een paar zinnen wat deze dienst inhoudt en voor wie ze is. Vul dit in via Beheer → Diensten.</span></div>' : '' ?><?php endif; ?>
          <div class="hero-ctas">
            <a class="btn mint" href="#contact">Vraag een offerte <span class="arr">→</span></a>
            <?php if ($cases): ?><a class="btn ghost" href="#werk">Bekijk realisaties</a><?php endif; ?>
          </div>
        </div>
        <div class="dienst-visual" id="frame">
          <?php if ($hero): ?><img src="<?= h($hero) ?>" alt="<?= h($d['titel']) ?>">
          <?php elseif ($illFile && is_file($illFile)): ?><div class="svc-img art"><?php readfile($illFile); ?></div><?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <?php if ($d['secties'] || $admin): ?>
  <section class="case-section"><div class="wrap">
    <?php if ($d['secties']): ?>
    <div class="dsecs">
      <?php foreach ($d['secties'] as $i => $sec): $img = safe_img($sec['afbeelding'] ?? ''); ?>
      <article class="dsec reveal<?= $img ? '' : ' no-img' ?><?= $i % 2 ? ' flip' : '' ?>">
        <div class="dsec-txt"><?php if (!empty($sec['titel'])): ?><h2><?= accent($sec['titel']) ?></h2><?php endif; ?><div class="txt"><?= paras($sec['tekst'] ?? '') ?></div></div>
        <?php if ($img): ?><div class="dsec-img"><img src="<?= h($img) ?>" alt="" loading="lazy"></div><?php endif; ?>
      </article>
      <?php endforeach; ?>
    </div>
    <?php else: echo $hint('Inhoudsblokken', 'Voeg blokken toe met een titel, tekst en optioneel een beeld. Ze verschijnen afwisselend links en rechts.'); endif; ?>
  </div></section>
  <?php endif; ?>

  <?php $lev = array_values(array_filter($d['leveren'], fn($x) => trim($x['titel'] ?? '') !== '')); if ($lev || $admin): ?>
  <section class="case-section"><div class="wrap">
    <div class="sec-head reveal"><span class="eyebrow">Wat je krijgt</span><h2>Wat je van ons mag verwachten.</h2></div>
    <?php if ($lev): ?>
    <div class="deliver">
      <?php foreach ($lev as $x): ?>
      <div class="deliver-item reveal"><span class="tick" aria-hidden="true">✓</span><div><h3><?= h($x['titel']) ?></h3><?php if (!empty($x['tekst'])): ?><p><?= h($x['tekst']) ?></p><?php endif; ?></div></div>
      <?php endforeach; ?>
    </div>
    <?php else: echo $hint('Wat je krijgt', 'Som op wat de klant concreet krijgt, bv. "Ontwerp op maat" of "Tot 3 voorstellen".'); endif; ?>
  </div></section>
  <?php endif; ?>

  <?php if ($cases): ?>
  <section class="case-section" id="werk"><div class="wrap">
    <div class="sec-head reveal"><span class="eyebrow">Realisaties</span><h2><?= h($d['titel']) ?> die we eerder maakten.</h2></div>
    <div class="work">
      <?php foreach (array_slice($cases, 0, 8) as $cs): $logo = safe_img($cs['logo']); ?>
      <a class="proj reveal" style="--c:<?= h(safe_hex($cs['kleur'])) ?>" href="case.php?c=<?= h(rawurlencode($cs['slug'])) ?>"><?php if ($logo): ?><img class="proj-logo" src="<?= h($logo) ?>" alt="" loading="lazy"><?php endif; ?><span class="proj-cap"><span class="proj-txt"><small>Bekijk case</small><b><?= h($cs['naam']) ?></b></span><i aria-hidden="true">→</i></span></a>
      <?php endforeach; ?>
    </div>
  </div></section>
  <?php endif; ?>

  <?php if ($others): ?>
  <section class="case-section"><div class="wrap">
    <div class="sec-head reveal"><span class="eyebrow">Ook interessant</span><h2>Andere diensten.</h2></div>
    <div class="other-svcs">
      <?php foreach ($others as $o): ?>
      <a class="other-svc reveal" href="<?= h(dienst_url($o)) ?>"><b><?= h($o['titel']) ?></b><span><?= h($o['tekst']) ?></span><i aria-hidden="true">→</i></a>
      <?php endforeach; ?>
    </div>
  </div></section>
  <?php endif; ?>
</main>

<?php site_cta($d['id'], $d['cta_titel'], $d['cta_tekst']); ?>
<?php site_footer(); page_end();
