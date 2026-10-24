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
<link rel="icon" type="image/png" sizes="32x32" href="<?= h(asset_v('assets/favicon-32.png')) ?>">
<link rel="icon" type="image/png" sizes="64x64" href="<?= h(asset_v('assets/favicon.png')) ?>">
<link rel="apple-touch-icon" href="<?= h(asset_v('assets/apple-touch-icon.png')) ?>">
<meta name="theme-color" content="#160C24">
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

function site_cta(string $dienst = '', string $titel = '', string $tekst = ''): void {
    require_once __DIR__ . '/contact.php';
    $s = content()['site']; $h = content()['home']; $f = form_settings();
    [$q, $token] = captcha_new();
    $ts = uses_turnstile() ? turnstile_keys()[0] : '';
    $diensten = visible_diensten();
    $budgets = array_filter(array_map('trim', explode("\n", $f['budget_opties']))); ?>
  <section class="cta" id="contact">
    <div class="wrap">
      <div class="contact-grid">
        <div class="cta-card reveal">
          <span class="eyebrow" style="color:var(--ink)"><?= h($h['cta_label'] ?? '') ?></span>
          <h2><?= accent($titel ?: ($h['cta_titel'] ?? '')) ?></h2>
          <p><?= h($tekst ?: ($h['cta_tekst'] ?? '')) ?></p>
          <?php if (!empty($s['email'])): ?><div class="cta-actions"><div class="mailbox"><span id="mail"><?= h($s['email']) ?></span><button type="button" id="copy">Kopieer</button></div></div><?php endif; ?>
        </div>
        <form class="cform reveal" id="cform" method="post" action="contact.php" novalidate>
          <div class="cform-head"><h3><?= h($f['titel']) ?></h3><?php if ($f['intro']): ?><p><?= h($f['intro']) ?></p><?php endif; ?></div>
          <input type="hidden" name="pagina" value="<?= h(strtok($_SERVER['REQUEST_URI'] ?? '/', '#')) ?>">
          <div class="hp" aria-hidden="true"><label for="cf-website">Laat dit veld leeg</label><input id="cf-website" name="website" tabindex="-1" autocomplete="off"></div>
          <div class="cgrid">
            <div class="cf"><label for="cf-naam">Naam *</label><input id="cf-naam" name="naam" required autocomplete="name" maxlength="120"><em data-err="naam"></em></div>
            <div class="cf"><label for="cf-email">E-mail *</label><input id="cf-email" name="email" type="email" required autocomplete="email" maxlength="200"><em data-err="email"></em></div>
            <?php if ($f['telefoon']): ?><div class="cf"><label for="cf-tel">Telefoon</label><input id="cf-tel" name="telefoon" type="tel" autocomplete="tel" maxlength="40"><em data-err="telefoon"></em></div><?php endif; ?>
            <?php if ($f['bedrijf']): ?><div class="cf"><label for="cf-bedrijf">Bedrijf</label><input id="cf-bedrijf" name="bedrijf" autocomplete="organization" maxlength="120"></div><?php endif; ?>
            <?php if ($diensten): ?><div class="cf"><label for="cf-dienst">Waarover gaat het?</label><select id="cf-dienst" name="dienst"><option value="">Kies een dienst (optioneel)</option><?php foreach ($diensten as $d): ?><option value="<?= h($d['id']) ?>"<?= $d['id'] === $dienst ? ' selected' : '' ?>><?= h($d['titel']) ?></option><?php endforeach; ?></select></div><?php endif; ?>
            <?php if ($f['budget'] && $budgets): ?><div class="cf"><label for="cf-budget">Budget</label><select id="cf-budget" name="budget"><option value="">Geen idee / liever niet</option><?php foreach ($budgets as $b): ?><option><?= h($b) ?></option><?php endforeach; ?></select></div><?php endif; ?>
            <div class="cf full"><label for="cf-bericht">Je bericht *</label><textarea id="cf-bericht" name="bericht" rows="5" required maxlength="5000"></textarea><em data-err="bericht"></em></div>
            <div class="cf full check"><label><input type="checkbox" name="privacy" value="1" required> <span><?= h($f['privacy']) ?></span></label><em data-err="privacy"></em></div>
            <?php if ($ts): ?>
            <div class="cf full"><div class="cf-turnstile" data-sitekey="<?= h($ts) ?>" data-language="nl"></div><em data-err="captcha"></em></div>
            <?php else: ?>
            <div class="cf captcha"><label for="cf-captcha" id="cf-q"><?= h($q) ?></label><input id="cf-captcha" name="captcha" inputmode="numeric" required maxlength="3" autocomplete="off"><input type="hidden" name="captcha_token" id="cf-token" value="<?= h($token) ?>"><em data-err="captcha"></em></div>
            <?php endif; ?>
          </div>
          <div class="cform-foot">
            <p class="cform-msg" id="cf-msg" role="status" aria-live="polite"></p>
            <button class="btn" type="submit"><span class="lbl"><?= h($h['cta_knop_tekst'] ?? 'Verstuur') ?></span> <span class="arr">→</span></button>
          </div>
        </form>
      </div>
    </div>
  </section>
  <?php if ($ts): ?><script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script><?php endif; ?>
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
        <?php foreach (visible_diensten() as $d): ?><li><a href="<?= h(dienst_url($d)) ?>"><?= h($d['titel']) ?></a></li><?php endforeach; ?>
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
