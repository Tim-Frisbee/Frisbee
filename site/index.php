<?php
declare(strict_types=1);
require __DIR__ . '/inc/layout.php';

$c = content(); $s = $c['site']; $h = $c['home'];
$cases = visible_cases();
$feat = find_case($h['uitgelicht_case'] ?? '');
$titleLines = preg_split('/\R/u', trim($h['hero_titel'] ?? ''));

page_start(($s['naam'] ?? 'Frisbee Agency'), $s['beschrijving'] ?? '', '?p=home');
?>
<main id="top">
  <div class="hero">
    <div class="stage" id="stage">
     <div class="stage-in wrap">
      <div class="hero-copy">
        <span class="eyebrow"><?= h($h['hero_label'] ?? '') ?></span>
        <h1>
          <?php foreach ($titleLines as $line): ?>
          <span class="line"><span><?= preg_replace('/\*(.+?)\*/u', '<span class="alive">$1<svg viewBox="0 0 400 30" preserveAspectRatio="none" aria-hidden="true"><path d="M4 22 C 90 4, 230 2, 396 16"/></svg></span>', h($line)) ?></span></span>
          <?php endforeach; ?>
        </h1>
        <p class="hero-lead"><?= preg_replace('/\*(.+?)\*/u', '<strong>$1</strong>', h($h['hero_tekst'] ?? '')) ?></p>
        <div class="hero-ctas">
          <?php if (!empty($h['hero_knop1_tekst'])): ?><a class="btn mint" href="<?= h(safe_url($h['hero_knop1_link'] ?? '') ?: '#contact') ?>"><?= h($h['hero_knop1_tekst']) ?> <span class="arr">→</span></a><?php endif; ?>
          <?php if (!empty($h['hero_knop2_tekst'])): ?><a class="btn ghost" href="<?= h(safe_url($h['hero_knop2_link'] ?? '') ?: '#realisaties') ?>"><?= h($h['hero_knop2_tekst']) ?></a><?php endif; ?>
        </div>
      </div>
      <div class="disc-zone" id="discZone" aria-hidden="true">
        <div class="disc" id="disc">
          <div class="rim"></div><div class="face"></div><div class="grooves"></div><div class="mark"><i></i></div>
        </div>
        <div class="orbit">
          <?php foreach (array_slice($h['hero_chips'] ?? [], 0, 4) as $chip): ?><span class="chip"><?= h($chip) ?></span><?php endforeach; ?>
        </div>
      </div>
     </div>
    </div>
  </div>

  <?php $items = $h['lichtkrant'] ?? []; if ($items):
    // repeat the set so the band is always wider than the screen; the 2nd half is a hidden copy
    $set = fn(bool $hidden) => implode('', array_map(fn($i) => '<a href="' . h(safe_url($i['link'] ?? '') ?: '#diensten') . '"' . ($hidden ? ' tabindex="-1" aria-hidden="true"' : '') . '>' . h($i['tekst'] ?? '') . '</a>', $items));
    $reps = max(1, (int)ceil(8 / count($items))); ?>
  <nav class="marquee" aria-label="Onze diensten">
    <div class="marquee-track">
      <?= $set(false) . str_repeat($set(true), $reps - 1) . str_repeat($set(true), $reps) ?>
    </div>
  </nav>
  <?php endif; ?>

  <?php if ($feat): $fimg = safe_img($feat['hoofdbeeld'] ?? '') ?: safe_img($feat['logo'] ?? ''); ?>
  <section id="project" style="padding-block:clamp(24px,4vw,48px) clamp(16px,2vw,24px)">
    <div class="wrap">
      <article class="feature reveal" style="--fbg:<?= h(safe_hex($h['uitgelicht_kleur'] ?? '', safe_hex($feat['kleur'] ?? ''))) ?>">
        <div class="feature-copy">
          <span class="eyebrow"><?= h($h['uitgelicht_label'] ?? '') ?></span>
          <h3 class="ftitle"><?= h($feat['naam']) ?></h3>
          <?php if (!empty($feat['intro'])): ?><p><?= h($feat['intro']) ?></p><?php endif; ?>
          <?php if (!empty($feat['diensten'])): ?><div class="tags"><?php foreach ($feat['diensten'] as $d): ?><span class="tag">#<?= h(mb_strtolower($d)) ?></span><?php endforeach; ?></div><?php endif; ?>
          <a class="link-arrow" href="case.php?c=<?= h(rawurlencode($feat['slug'])) ?>"><?= h($h['uitgelicht_knop'] ?? 'Lees meer') ?> <b aria-hidden="true">→</b></a>
        </div>
        <div class="feature-visual"><?php if ($fimg): ?><img src="<?= h($fimg) ?>" alt="<?= h($feat['naam']) ?>" loading="lazy"><?php endif; ?></div>
      </article>
    </div>
  </section>
  <?php endif; ?>

  <section id="diensten" style="padding-top:clamp(24px,3vw,40px)">
    <div class="wrap">
      <div class="sec-head split reveal">
        <div style="display:grid;gap:18px">
          <span class="eyebrow"><?= h($h['diensten_label'] ?? '') ?></span>
          <h2><?= accent($h['diensten_titel'] ?? '') ?></h2>
        </div>
        <p><?= h($h['diensten_tekst'] ?? '') ?></p>
      </div>
      <div class="bento">
        <?php foreach ($c['diensten'] as $d):
          $img = safe_img($d['afbeelding'] ?? '');
          $ill = preg_replace('/[^a-z]/', '', $d['illustratie'] ?? ''); ?>
        <article class="svc reveal" id="dienst-<?= h($d['id']) ?>">
          <div class="svc-img art"><?php
            if ($img) echo '<img src="' . h($img) . '" alt="" loading="lazy" class="svc-photo">';
            elseif ($ill && is_file(__DIR__ . "/inc/illustraties/$ill.svg")) readfile(__DIR__ . "/inc/illustraties/$ill.svg"); ?></div>
          <div class="svc-body"><div class="row"><h3><?= h($d['titel']) ?></h3><span class="go" aria-hidden="true">→</span></div>
          <p><?= h($d['tekst']) ?></p></div>
        </article>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <section id="realisaties" style="padding-top:0">
    <div class="wrap">
      <div class="sec-head split reveal">
        <div style="display:grid;gap:18px">
          <span class="eyebrow"><?= h($h['realisaties_label'] ?? '') ?></span>
          <h2><?= accent($h['realisaties_titel'] ?? '') ?></h2>
        </div>
      </div>
      <div class="work">
        <?php foreach ($cases as $cs):
          $size = ['groot' => ' big', 'breed' => ' wide'][$cs['tegel'] ?? ''] ?? '';
          $logo = safe_img($cs['logo'] ?? ''); ?>
        <a class="proj<?= $size ?> reveal" style="--c:<?= h(safe_hex($cs['kleur'] ?? '')) ?>" href="case.php?c=<?= h(rawurlencode($cs['slug'])) ?>"><?php if ($logo): ?><img class="proj-logo" src="<?= h($logo) ?>" alt="" loading="lazy"><?php endif; ?><span class="proj-cap"><span class="proj-txt"><small>Bekijk case</small><b><?= h($cs['naam']) ?></b></span><i aria-hidden="true">→</i></span></a>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <div class="wrap">
    <section class="process" id="werkwijze">
      <div class="wrap">
        <div class="sec-head reveal">
          <span class="eyebrow"><?= h($h['werkwijze_label'] ?? '') ?></span>
          <h2><?= accent($h['werkwijze_titel'] ?? '') ?></h2>
          <p><?= h($h['werkwijze_tekst'] ?? '') ?></p>
        </div>
        <?php $steps = $h['stappen'] ?? []; ?>
        <div class="steps" id="steps" role="list" style="--n:<?= max(1, count($steps)) ?>">
          <svg class="flight" viewBox="0 0 1000 60" preserveAspectRatio="none" aria-hidden="true">
            <path class="track" d="M10 54 C 200 54, 250 10, 500 14 S 820 52, 990 20"/>
            <path class="trail" d="M10 54 C 200 54, 250 10, 500 14 S 820 52, 990 20"/>
          </svg>
          <?php foreach ($steps as $i => $st): ?>
          <div class="step reveal" role="listitem"><span class="num"><?= str_pad((string)($i + 1), 2, '0', STR_PAD_LEFT) ?></span><h3><?= h($st['titel'] ?? '') ?></h3><p><?= h($st['tekst'] ?? '') ?></p></div>
          <?php endforeach; ?>
        </div>
      </div>
    </section>
  </div>

  <?php site_cta(); ?>
</main>
<?php site_footer(); page_end();
