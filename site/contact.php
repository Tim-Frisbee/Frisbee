<?php
/** Receives the contact form. Answers JSON for the in-page form, or a simple page without JavaScript. */
declare(strict_types=1);
require __DIR__ . '/inc/layout.php';
require_once __DIR__ . '/inc/contact.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: ./#contact'); exit; }

$res = handle_contact($_POST);

if (str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')) {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    if (!$res['ok']) {
        http_response_code(422);
        [$q, $t] = captcha_new();   // fresh question for the next attempt
        $res['captcha'] = ['vraag' => $q, 'token' => $t];
    }
    echo json_encode($res, JSON_UNESCAPED_UNICODE);
    exit;
}

// Fallback without JavaScript
page_start($res['ok'] ? 'Bericht verstuurd' : 'Bericht niet verstuurd', '');
?>
<main class="wrap" style="padding-block:clamp(64px,10vw,120px);display:grid;gap:20px;justify-items:start;max-width:760px">
  <span class="eyebrow"><?= $res['ok'] ? 'Verstuurd' : 'Niet verstuurd' ?></span>
  <h1 style="font-size:clamp(2rem,5vw,3.4rem)"><?= h($res['melding']) ?></h1>
  <?php if ($res['fouten']): ?><ul><?php foreach ($res['fouten'] as $e): ?><li><?= h($e) ?></li><?php endforeach; ?></ul><?php endif; ?>
  <a class="btn" href="<?= $res['ok'] ? './' : './#contact' ?>"><?= $res['ok'] ? 'Terug naar de homepage' : 'Terug naar het formulier' ?> <span class="arr">→</span></a>
</main>
<?php site_footer(); page_end();
