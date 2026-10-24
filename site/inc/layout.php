<?php
/** Shared page pieces for the public site. */
declare(strict_types=1);
require_once __DIR__ . '/core.php';

const FR_FONTS = 'https://fonts.googleapis.com/css2?family=Montserrat+Alternates:wght@600;700;800&family=Manrope:wght@400;500;600;700&family=Sora:wght@500;600&family=Geist+Mono:wght@400&display=swap';

function asset_v(string $file): string { return $file . '?v=' . @filemtime(FR_ROOT . '/' . $file); }

function page_start(string $title, string $description, string $editLink = ''): void {
    $s = content()['site'];
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin'); ?>
<!doctype html>
<html lang="nl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<title><?= h($title) ?></title>
<meta name="description" content="<?= h($description) ?>">
<meta property="og:title" content="<?= h($title) ?>">
<meta property="og:description" content="<?= h($description) ?>">
<meta property="og:type" content="website">
<link rel="icon" href="<?= h(asset_v('assets/favicon.png')) ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="<?= h(FR_FONTS) ?>">
<link rel="stylesheet" href="<?= h(asset_v('assets/site.css')) ?>">
</head>
<body>
<?php if (is_admin()): ?>
<div class="adminbar"><span>Je bent ingelogd</span><a href="admin/<?= h($editLink) ?>">Bewerk deze pagina</a><a href="admin/">Beheer</a></div>
<?php endif; ?>
<header class="nav" id="nav">
  <div class="wrap">
    <a class="logo" href="./" aria-label="<?= h($s['naam'] ?? 'Frisbee') ?>, naar de homepage"><img src="assets/logo.png" alt="<?= h($s['naam'] ?? 'Frisbee') ?>" width="152" height="38"></a>
    <ul class="menu" id="menu">
      <li><a href="./#diensten">Diensten</a></li>
      <li><a href="./#realisaties">Realisaties</a></li>
      <li><a href="./#werkwijze">Werkwijze</a></li>
      <li><a href="./#contact">Contact</a></li>
    </ul>
    <a class="btn desk" href="./#contact"><?= h($s['nav_knop'] ?? 'Start je project') ?> <span class="arr">→</span></a>
    <button class="burger" id="burger" aria-label="Menu openen" aria-expanded="false" aria-controls="menu"><span></span><span></span></button>
  </div>
</header>
<?php }

function site_cta(): void {
    $s = content()['site']; $h = content()['home'];
    $contact = safe_url($s['contact_link'] ?? '') ?: 'mailto:' . ($s['email'] ?? ''); ?>
  <section class="cta" id="contact">
    <div class="wrap">
      <div class="cta-card reveal">
        <span class="eyebrow" style="color:var(--ink)"><?= h($h['cta_label'] ?? '') ?></span>
        <h2><?= accent($h['cta_titel'] ?? '') ?></h2>
        <p><?= h($h['cta_tekst'] ?? '') ?></p>
        <div class="cta-actions">
          <a class="btn" href="<?= h($contact) ?>"><?= h($h['cta_knop_tekst'] ?? 'Contacteer ons') ?> <span class="arr">→</span></a>
          <?php if (!empty($s['email'])): ?><div class="mailbox"><span id="mail"><?= h($s['email']) ?></span><button type="button" id="copy">Kopieer</button></div><?php endif; ?>
        </div>
      </div>
    </div>
  </section>
<?php }

function site_footer(): void {
    $c = content(); $s = $c['site'];
    $icons = [
      'facebook' => ['Facebook', '<path d="M13.5 22v-8h2.7l.4-3.2h-3.1V8.8c0-.9.3-1.5 1.6-1.5h1.7V4.4c-.3 0-1.3-.1-2.4-.1-2.4 0-4 1.5-4 4.1v2.4H7.7V14h2.7v8h3.1z"/>'],
      'instagram' => ['Instagram', '<path d="M12 7.3a4.7 4.7 0 1 0 0 9.4 4.7 4.7 0 0 0 0-9.4zm0 7.7a3 3 0 1 1 0-6 3 3 0 0 1 0 6zm6-7.9a1.1 1.1 0 1 1-2.2 0 1.1 1.1 0 0 1 2.2 0zM21.9 8c-.1-1.6-.4-3-1.6-4.2S17.6 2.2 16 2.1C14.4 2 9.6 2 8 2.1c-1.6.1-3 .4-4.2 1.6S2.2 6.4 2.1 8C2 9.6 2 14.4 2.1 16c.1 1.6.4 3 1.6 4.2s2.6 1.5 4.2 1.6c1.6.1 6.4.1 8 0 1.6-.1 3-.4 4.2-1.6s1.5-2.6 1.6-4.2c.1-1.6.1-6.4 0-8zm-2.1 9.8a3.3 3.3 0 0 1-1.8 1.8c-1.3.5-4.3.4-5.6.4s-4.4.1-5.6-.4a3.3 3.3 0 0 1-1.8-1.8c-.5-1.3-.4-4.3-.4-5.6s-.1-4.4.4-5.6A3.3 3.3 0 0 1 6.8 4.6c1.3-.5 4.3-.4 5.6-.4s4.4-.1 5.6.4a3.3 3.3 0 0 1 1.8 1.8c.5 1.3.4 4.3.4 5.6s.1 4.4-.4 5.6z"/>'],
      'linkedin' => ['LinkedIn', '<path d="M6.9 21H3.1V9h3.8v12zM5 7.4a2.2 2.2 0 1 1 0-4.4 2.2 2.2 0 0 1 0 4.4zM21 21h-3.8v-5.8c0-1.4 0-3.2-2-3.2s-2.2 1.5-2.2 3.1V21H9.2V9h3.6v1.6h.1c.5-1 1.7-2 3.6-2 3.8 0 4.5 2.5 4.5 5.8V21z"/>'],
    ]; ?>
<footer>
  <div class="wrap">
    <div class="foot">
      <div class="about">
        <img src="assets/logo.png" alt="<?= h($s['naam'] ?? 'Frisbee') ?>" width="152" height="38" style="height:38px;width:auto">
        <p><?= h($s['footer_tekst'] ?? '') ?></p>
        <div class="social">
          <?php foreach ($icons as $k => [$label, $path]): if ($u = safe_url($s[$k] ?? '')): ?>
          <a href="<?= h($u) ?>" aria-label="<?= $label ?>"><svg viewBox="0 0 24 24"><?= $path ?></svg></a>
          <?php endif; endforeach; ?>
        </div>
      </div>
      <div><h4>Diensten</h4><ul>
        <?php foreach ($c['diensten'] as $d): ?><li><a href="./#dienst-<?= h($d['id']) ?>"><?= h($d['titel']) ?></a></li><?php endforeach; ?>
      </ul></div>
      <div><h4>Realisaties</h4><ul>
        <?php foreach (array_slice(visible_cases(), 0, 5) as $cs): ?><li><a href="case.php?c=<?= h(rawurlencode($cs['slug'])) ?>"><?= h($cs['naam']) ?></a></li><?php endforeach; ?>
      </ul></div>
      <div><h4>Contact</h4><ul>
        <?php if (!empty($s['email'])): ?><li><a href="mailto:<?= h($s['email']) ?>"><?= h($s['email']) ?></a></li><?php endif; ?>
        <?php if ($u = safe_url($s['contact_link'] ?? '')): ?><li><a href="<?= h($u) ?>">Contactformulier</a></li><?php endif; ?>
      </ul></div>
    </div>
    <div class="legal"><span>© <?= date('Y') ?> <?= h($s['naam'] ?? '') ?></span><span><?= h($s['slogan'] ?? '') ?></span></div>
  </div>
</footer>
<?php }

function page_end(): void { ?>
<div class="toast" id="toast" role="status" aria-live="polite"></div>
<script src="<?= h(asset_v('assets/site.js')) ?>"></script>
</body>
</html>
<?php }
