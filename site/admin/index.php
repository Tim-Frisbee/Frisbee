<?php
/**
 * Frisbee CMS — beheerpaneel.
 * One file, routed with ?p=…  Every change is a POST with a CSRF token,
 * followed by a redirect (so refreshing never submits twice).
 */
declare(strict_types=1);
require __DIR__ . '/../inc/core.php';
require __DIR__ . '/forms.php';

header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header("Content-Security-Policy: default-src 'self'; img-src 'self' data: blob:; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src https://fonts.gstatic.com; script-src 'self'; form-action 'self'; frame-ancestors 'none'; base-uri 'none'");
header('Cache-Control: no-store');

start_session();
$p = preg_replace('/[^a-z]/', '', (string)($_GET['p'] ?? 'dashboard')) ?: 'dashboard';

function go(string $to): never { header('Location: ' . $to); exit; }
function flash(string $msg, string $type = 'ok'): void { $_SESSION['flash'][] = [$type, $msg]; }
function require_login(): void { if (empty($_SESSION['uid'])) go('?p=login'); }

/* ================= Install, login, logout ================= */

if (!is_installed() && $p !== 'setup') go('?p=setup');

if ($p === 'setup') {
    if (is_installed()) go('./');
    $codeFile = FR_DATA . '/installatiecode.php';
    $code = trim((string)guarded_read($codeFile));
    $error = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        csrf_check();
        if ($locked = login_locked()) $error = 'Te veel pogingen. Probeer opnieuw over ' . ceil($locked / 60) . ' minuten.';
        elseif ($code === '' || !hash_equals($code, trim((string)($_POST['code'] ?? '')))) { login_failed(); $error = 'De installatiecode klopt niet. Je vindt ze in het bestand INSTALLATIECODE.txt dat bij het pakket zat.'; }
        elseif (mb_strlen((string)($_POST['pw'] ?? '')) < 10) $error = 'Kies een wachtwoord van minstens 10 tekens.';
        elseif (($_POST['pw'] ?? '') !== ($_POST['pw2'] ?? '')) $error = 'De twee wachtwoorden zijn niet gelijk.';
        else {
            save_config(['hash' => password_hash((string)$_POST['pw'], PASSWORD_DEFAULT), 'aangemaakt' => date('c')]);
            @unlink($codeFile);
            if (guarded_read(FR_DATA . '/content.php') === null) save_content(content());
            login_cleared();
            session_regenerate_id(true);
            $_SESSION['uid'] = 1;
            flash('Welkom! Je beheerpaneel is klaar. Bewaar je wachtwoord goed.');
            go('./');
        }
    }
    auth_page('Beheer installeren', 'Stel je wachtwoord in om het beheerpaneel te activeren.', $error, '
      ' . csrf_field() . '
      <div class="field"><label for="code">Installatiecode</label><input id="code" name="code" autocomplete="off" required><small>Staat in INSTALLATIECODE.txt bij het installatiepakket.</small></div>
      <div class="field"><label for="pw">Nieuw wachtwoord</label><input id="pw" name="pw" type="password" autocomplete="new-password" minlength="10" required><small>Minstens 10 tekens.</small></div>
      <div class="field"><label for="pw2">Herhaal wachtwoord</label><input id="pw2" name="pw2" type="password" autocomplete="new-password" minlength="10" required></div>
      <button class="btn-main" type="submit">Beheer activeren</button>', $code === '' ? 'De installatiecode ontbreekt op de server. Upload het bestand data/installatiecode.php opnieuw uit het pakket.' : '');
    exit;
}

if ($p === 'login') {
    if (!empty($_SESSION['uid'])) go('./');
    $error = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        csrf_check();
        if ($locked = login_locked()) $error = 'Te veel mislukte pogingen. Probeer opnieuw over ' . ceil($locked / 60) . ' minuten.';
        elseif (password_verify((string)($_POST['pw'] ?? ''), config()['hash'] ?? '')) {
            login_cleared();
            session_regenerate_id(true);
            $_SESSION['uid'] = 1;
            if (password_needs_rehash(config()['hash'], PASSWORD_DEFAULT)) save_config(['hash' => password_hash((string)$_POST['pw'], PASSWORD_DEFAULT)] + config());
            go('./');
        } else { login_failed(); usleep(400000); $error = 'Dat wachtwoord klopt niet.'; }
    }
    auth_page('Inloggen', 'Log in om de inhoud van je website te beheren.', $error, '
      ' . csrf_field() . '
      <div class="field"><label for="pw">Wachtwoord</label><input id="pw" name="pw" type="password" autocomplete="current-password" required autofocus></div>
      <button class="btn-main" type="submit">Inloggen</button>');
    exit;
}

if ($p === 'logout') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') { csrf_check(); $_SESSION = []; session_regenerate_id(true); session_destroy(); }
    go('?p=login');
}

require_login();

/* ================= Save handlers ================= */

$c = content();
$cases = array_map('normalize_case', $c['cases']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    try {
        switch ($p) {
            case 'algemeen':
                foreach (['naam' => 120, 'beschrijving' => 300, 'footer_tekst' => 300, 'slogan' => 120, 'nav_knop' => 60] as $k => $max) $c['site'][$k] = post_str($_POST[$k] ?? '', $max);
                $email = post_str($_POST['email'] ?? '', 200);
                if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Dat e-mailadres lijkt niet geldig.');
                $c['site']['email'] = $email;
                foreach (['contact_link', 'facebook', 'instagram', 'linkedin'] as $k) {
                    $u = post_str($_POST[$k] ?? '', 500);
                    if ($u !== '' && safe_url($u) === '') throw new RuntimeException('De link bij "' . $k . '" is ongeldig. Begin met https://');
                    $c['site'][$k] = $u;
                }
                break;

            case 'home':
                $txt = ['hero_label' => 120, 'hero_titel' => 200, 'hero_tekst' => 600, 'hero_knop1_tekst' => 60, 'hero_knop1_link' => 300, 'hero_knop2_tekst' => 60, 'hero_knop2_link' => 300,
                        'uitgelicht_label' => 80, 'uitgelicht_knop' => 60, 'diensten_label' => 120, 'diensten_titel' => 200, 'diensten_tekst' => 400,
                        'realisaties_label' => 120, 'realisaties_titel' => 200, 'werkwijze_label' => 120, 'werkwijze_titel' => 200, 'werkwijze_tekst' => 600,
                        'cta_label' => 80, 'cta_titel' => 200, 'cta_tekst' => 400, 'cta_knop_tekst' => 60];
                foreach ($txt as $k => $max) $c['home'][$k] = post_str($_POST[$k] ?? '', $max);
                $c['home']['uitgelicht_case'] = find_case(post_str($_POST['uitgelicht_case'] ?? '')) ? post_str($_POST['uitgelicht_case']) : '';
                $c['home']['uitgelicht_kleur'] = safe_hex(post_str($_POST['uitgelicht_kleur'] ?? ''), '#05070a');
                $c['home']['hero_chips'] = array_column(read_repeater('hero_chips', ['tekst' => ['text']]), 'tekst');
                $c['home']['lichtkrant'] = read_repeater('lichtkrant', ['tekst' => ['text'], 'link' => ['text']]);
                $c['home']['stappen'] = read_repeater('stappen', ['titel' => ['text'], 'tekst' => ['area']]);
                break;

            case 'diensten':
                $rows = read_repeater('diensten', ['titel' => ['text'], 'tekst' => ['area'], 'illustratie' => [['' => '']], 'afbeelding' => ['image']]);
                $seen = [];
                foreach ($rows as &$r) {
                    $id = slugify($r['titel'] ?: 'dienst'); $base = $id; $i = 2;
                    while (isset($seen[$id])) $id = $base . '-' . $i++;
                    $seen[$id] = true;
                    $r = ['id' => $id] + $r;
                    if (!in_array($r['illustratie'], ['websites', 'logos', 'branding', 'software', ''], true)) $r['illustratie'] = '';
                }
                $c['diensten'] = $rows;
                break;

            case 'case':
                $orig = post_str($_POST['orig_slug'] ?? '');
                $naam = post_str($_POST['naam'] ?? '', 120);
                if ($naam === '') throw new RuntimeException('Geef de case een naam.');
                $slug = slugify(post_str($_POST['slug'] ?? '') ?: $naam);
                $taken = array_column(array_filter($cases, fn($x) => $x['slug'] !== $orig), 'slug');
                $base = $slug; $i = 2; while (in_array($slug, $taken, true)) $slug = $base . '-' . $i++;
                $gal = [];
                foreach ((array)($_POST['galerij'] ?? []) as $g) if ($g = safe_img(post_str($g))) $gal[] = $g;
                foreach (files_list($_FILES['galerij_nieuw'] ?? null) as $f) if ($u = store_upload($f)) $gal[] = $u;
                if ($add = safe_img(post_str($_POST['galerij_bestaand'] ?? ''))) $gal[] = $add;
                $new = normalize_case([
                    'slug' => $slug, 'naam' => $naam, 'zichtbaar' => !empty($_POST['zichtbaar']),
                    'tegel' => in_array($_POST['tegel'] ?? '', ['normaal', 'groot', 'breed'], true) ? $_POST['tegel'] : 'normaal',
                    'kleur' => safe_hex(post_str($_POST['kleur'] ?? '')),
                    'tagline' => post_str($_POST['tagline'] ?? '', 120), 'intro' => post_str($_POST['intro'] ?? '', 800),
                    'klant' => post_str($_POST['klant'] ?? '', 120),
                    'diensten' => array_values(array_filter(array_map('trim', explode(',', post_str($_POST['diensten'] ?? '', 300))))),
                    'jaar' => post_str($_POST['jaar'] ?? '', 20),
                    'website' => safe_url(post_str($_POST['website'] ?? '', 300)),
                    'logo' => read_image('logo', post_str($_POST['logo'] ?? '')),
                    'hoofdbeeld' => read_image('hoofdbeeld', post_str($_POST['hoofdbeeld'] ?? '')),
                    'vraag' => post_str($_POST['vraag'] ?? ''), 'aanpak' => post_str($_POST['aanpak'] ?? ''), 'resultaat' => post_str($_POST['resultaat'] ?? ''),
                    'galerij' => array_values(array_unique($gal)),
                    'kleuren' => array_values(array_filter(read_repeater('kleuren', ['hex' => ['color'], 'naam' => ['text']]), fn($k) => $k['hex'] !== '')),
                    'lettertype_naam' => post_str($_POST['lettertype_naam'] ?? '', 80), 'lettertype_gewichten' => post_str($_POST['lettertype_gewichten'] ?? '', 120),
                    'citaat_tekst' => post_str($_POST['citaat_tekst'] ?? '', 600), 'citaat_naam' => post_str($_POST['citaat_naam'] ?? '', 120), 'citaat_functie' => post_str($_POST['citaat_functie'] ?? '', 120),
                ]);
                $found = false;
                foreach ($cases as $k => $x) if ($x['slug'] === $orig && $orig !== '') { $cases[$k] = $new; $found = true; }
                if (!$found) $cases[] = $new;
                if ($orig !== '' && ($c['home']['uitgelicht_case'] ?? '') === $orig) $c['home']['uitgelicht_case'] = $slug;
                $c['cases'] = $cases;
                save_content($c);
                flash('Case "' . $naam . '" is opgeslagen.');
                go('?p=case&slug=' . rawurlencode($slug));

            case 'cases':
                $slug = post_str($_POST['slug'] ?? '');
                $i = array_search($slug, array_column($cases, 'slug'), true);
                if ($i === false) throw new RuntimeException('Die case bestaat niet meer.');
                $act = $_POST['actie'] ?? '';
                if ($act === 'omhoog' && $i > 0) [$cases[$i - 1], $cases[$i]] = [$cases[$i], $cases[$i - 1]];
                if ($act === 'omlaag' && $i < count($cases) - 1) [$cases[$i + 1], $cases[$i]] = [$cases[$i], $cases[$i + 1]];
                if ($act === 'zichtbaar') $cases[$i]['zichtbaar'] = empty($cases[$i]['zichtbaar']);
                if ($act === 'verwijder') { $name = $cases[$i]['naam']; array_splice($cases, $i, 1); flash('Case "' . $name . '" is verwijderd. Je kan ze terugzetten via Back-ups.'); }
                $c['cases'] = array_values($cases);
                save_content($c);
                if ($act !== 'verwijder') flash('Bijgewerkt.');
                go('?p=cases');

            case 'media':
                if (($_POST['actie'] ?? '') === 'upload') {
                    $n = 0; foreach (files_list($_FILES['bestanden'] ?? null) as $f) if (store_upload($f)) $n++;
                    flash($n === 1 ? '1 beeld geüpload.' : "$n beelden geüpload.");
                } elseif (($_POST['actie'] ?? '') === 'verwijder') {
                    $img = safe_img(post_str($_POST['bestand'] ?? ''));
                    if (!$img) throw new RuntimeException('Dat beeld bestaat niet.');
                    if (isset(used_images()[$img])) throw new RuntimeException('Dit beeld wordt nog gebruikt op de site. Haal het eerst weg uit de case of sectie.');
                    unlink(FR_ROOT . '/' . $img);
                    flash('Beeld verwijderd.');
                }
                go('?p=media');

            case 'backups':
                $file = basename(post_str($_POST['bestand'] ?? ''));
                $path = FR_DATA . '/backups/' . $file;
                if (!preg_match('/^inhoud-[0-9_-]+-[a-f0-9]{4}\.php$/', $file) || !is_file($path)) throw new RuntimeException('Die back-up bestaat niet.');
                $json = guarded_read($path);
                if (!json_decode((string)$json, true)) throw new RuntimeException('Die back-up is beschadigd.');
                save_content(json_decode($json, true));
                flash('Back-up teruggezet. De versie van net daarvoor staat bovenaan de lijst, mocht je terug willen.');
                go('?p=backups');

            case 'wachtwoord':
                if (!password_verify((string)($_POST['huidig'] ?? ''), config()['hash'] ?? '')) throw new RuntimeException('Je huidige wachtwoord klopt niet.');
                if (mb_strlen((string)($_POST['nieuw'] ?? '')) < 10) throw new RuntimeException('Kies een nieuw wachtwoord van minstens 10 tekens.');
                if (($_POST['nieuw'] ?? '') !== ($_POST['nieuw2'] ?? '')) throw new RuntimeException('De twee nieuwe wachtwoorden zijn niet gelijk.');
                save_config(['hash' => password_hash((string)$_POST['nieuw'], PASSWORD_DEFAULT)] + config());
                session_regenerate_id(true);
                flash('Je wachtwoord is gewijzigd.');
                go('?p=wachtwoord');

            default:
                go('./');
        }
        save_content($c);
        flash('Wijzigingen opgeslagen.');
        go('?p=' . $p);
    } catch (RuntimeException $e) {
        flash($e->getMessage(), 'err');
        // keep the user on the page they were on
        go('?' . http_build_query(array_filter(['p' => $p, 'slug' => $_GET['slug'] ?? null])));
    }
}

/* ================= Pages ================= */

$title = ''; $body = ''; $view = '';

switch ($p) {
    case 'dashboard':
        $title = 'Overzicht';
        $vis = count(array_filter($cases, fn($x) => $x['zichtbaar']));
        $todo = [];
        foreach ($cases as $x) {
            $miss = array_keys(array_filter(['intro' => $x['intro'], 'vraag' => $x['vraag'], 'aanpak' => $x['aanpak'], 'resultaat' => $x['resultaat'], 'beelden' => $x['galerij']], fn($v) => empty($v)));
            if ($miss) $todo[] = [$x, $miss];
        }
        $cards = [
            ['algemeen', 'Algemeen', 'Naam, e-mail, sociale media en footer.'],
            ['home', 'Homepage', 'Hero, lichtkrant, uitgelicht project, werkwijze en contactblok.'],
            ['diensten', 'Diensten', count($c['diensten']) . ' diensten met tekst en beeld.'],
            ['cases', 'Realisaties', count($cases) . ' cases, waarvan ' . $vis . ' zichtbaar.'],
            ['media', 'Beelden', count(all_uploads()) . ' beelden in de bibliotheek.'],
            ['backups', 'Back-ups', 'Elke opslag maakt automatisch een back-up.'],
        ];
        $body = '<div class="cards">';
        foreach ($cards as [$k, $t, $d]) $body .= '<a class="card" href="?p=' . $k . '"><b>' . h($t) . '</b><span>' . h($d) . '</span><i aria-hidden="true">→</i></a>';
        $body .= '</div>';
        if ($todo) {
            $body .= '<section class="panel"><h2>Nog aan te vullen</h2><p class="help">Deze cases missen nog inhoud. Bezoekers zien lege onderdelen niet, maar een volledige case overtuigt meer.</p><ul class="todo">';
            foreach ($todo as [$x, $miss]) $body .= '<li><a href="?p=case&amp;slug=' . h(rawurlencode($x['slug'])) . '"><i style="background:' . h(safe_hex($x['kleur'])) . '"></i><b>' . h($x['naam']) . '</b><span>' . h(implode(', ', $miss)) . '</span></a></li>';
            $body .= '</ul></section>';
        }
        $view = '../';
        break;

    case 'algemeen':
        $title = 'Algemeen'; $view = '../'; $s = $c['site'];
        $body = form_open() . '<section class="panel"><h2>Website</h2>'
            . f_text('naam', 'Naam van de website', $s['naam'] ?? '', 'Verschijnt in de browsertab en in de footer.')
            . f_area('beschrijving', 'Korte beschrijving', $s['beschrijving'] ?? '', 'Voor Google en bij het delen op sociale media (±150 tekens).', 2)
            . f_text('nav_knop', 'Knop rechtsboven', $s['nav_knop'] ?? '')
            . '</section><section class="panel"><h2>Contact</h2>'
            . f_text('email', 'E-mailadres', $s['email'] ?? '', '', ['type' => 'email'])
            . f_text('contact_link', 'Link van de knop "Contacteer ons"', $s['contact_link'] ?? '', 'Leeg laten = de knop opent een e-mail naar het adres hierboven.')
            . '</section><section class="panel"><h2>Sociale media</h2><p class="help">Laat een veld leeg om het icoon te verbergen.</p>'
            . f_text('facebook', 'Facebook', $s['facebook'] ?? '') . f_text('instagram', 'Instagram', $s['instagram'] ?? '') . f_text('linkedin', 'LinkedIn', $s['linkedin'] ?? '')
            . '</section><section class="panel"><h2>Footer</h2>'
            . f_area('footer_tekst', 'Tekst onder het logo', $s['footer_tekst'] ?? '', '', 2) . f_text('slogan', 'Slogan rechtsonder', $s['slogan'] ?? '')
            . '</section>' . form_close();
        break;

    case 'home':
        $title = 'Homepage'; $view = '../'; $hh = $c['home'];
        $accentHelp = 'Zet woorden tussen *sterretjes* om ze te laten opvallen.';
        $caseOpts = ['' => '— geen —'];
        foreach ($cases as $x) $caseOpts[$x['slug']] = $x['naam'];
        $body = form_open() . '<section class="panel"><h2>Hero (bovenste banner)</h2>'
            . f_text('hero_label', 'Klein label', $hh['hero_label'] ?? '')
            . f_area('hero_titel', 'Titel', $hh['hero_titel'] ?? '', 'Elke regel wordt een nieuwe regel in de titel. Woorden tussen *sterretjes* worden groen met een onderlijn.', 3)
            . f_area('hero_tekst', 'Tekst', $hh['hero_tekst'] ?? '', 'Woorden tussen *sterretjes* worden vet.', 3)
            . '<div class="grid2">' . f_text('hero_knop1_tekst', 'Knop 1: tekst', $hh['hero_knop1_tekst'] ?? '') . f_text('hero_knop1_link', 'Knop 1: link', $hh['hero_knop1_link'] ?? '', 'Bv. #contact of een volledige link.')
            . f_text('hero_knop2_tekst', 'Knop 2: tekst', $hh['hero_knop2_tekst'] ?? '') . f_text('hero_knop2_link', 'Knop 2: link', $hh['hero_knop2_link'] ?? '') . '</div>'
            . f_repeater('hero_chips', 'Labels rond de schijf', array_map(fn($t) => ['tekst' => $t], $hh['hero_chips'] ?? []), ['tekst' => ['text', 'Label', '']], 'Label toevoegen', 'Maximaal 4 worden getoond.')
            . '</section><section class="panel"><h2>Lichtkrant</h2>'
            . f_repeater('lichtkrant', 'Items', $hh['lichtkrant'] ?? [], ['tekst' => ['text', 'Tekst', ''], 'link' => ['text', 'Link', 'Bv. #dienst-websites (springt naar een dienst) of een volledige link.']], 'Item toevoegen')
            . '</section><section class="panel"><h2>Project in de kijker</h2>'
            . f_select('uitgelicht_case', 'Welke case', $hh['uitgelicht_case'] ?? '', $caseOpts, 'Gebruikt de naam, intro, diensten en het hoofdbeeld van de case.')
            . '<div class="grid2">' . f_text('uitgelicht_label', 'Label', $hh['uitgelicht_label'] ?? '') . f_text('uitgelicht_knop', 'Tekst van de link', $hh['uitgelicht_knop'] ?? '') . '</div>'
            . f_color('uitgelicht_kleur', 'Achtergrondkleur van het blok', $hh['uitgelicht_kleur'] ?? '', 'Kies de kleur van de rand van het hoofdbeeld, dan loopt het naadloos over.')
            . '</section><section class="panel"><h2>Titels van de secties</h2>'
            . '<div class="grid2">' . f_text('diensten_label', 'Diensten: label', $hh['diensten_label'] ?? '') . f_text('diensten_titel', 'Diensten: titel', $hh['diensten_titel'] ?? '', $accentHelp) . '</div>'
            . f_area('diensten_tekst', 'Diensten: tekst', $hh['diensten_tekst'] ?? '', '', 2)
            . '<div class="grid2">' . f_text('realisaties_label', 'Realisaties: label', $hh['realisaties_label'] ?? '') . f_text('realisaties_titel', 'Realisaties: titel', $hh['realisaties_titel'] ?? '', $accentHelp) . '</div>'
            . '</section><section class="panel"><h2>Werkwijze</h2>'
            . '<div class="grid2">' . f_text('werkwijze_label', 'Label', $hh['werkwijze_label'] ?? '') . f_text('werkwijze_titel', 'Titel', $hh['werkwijze_titel'] ?? '') . '</div>'
            . f_area('werkwijze_tekst', 'Tekst', $hh['werkwijze_tekst'] ?? '', '', 2)
            . f_repeater('stappen', 'Stappen', $hh['stappen'] ?? [], ['titel' => ['text', 'Titel', ''], 'tekst' => ['area', 'Uitleg', '']], 'Stap toevoegen', 'De nummers 01, 02, … worden automatisch toegevoegd.')
            . '</section><section class="panel"><h2>Contactblok</h2>'
            . '<div class="grid2">' . f_text('cta_label', 'Label', $hh['cta_label'] ?? '') . f_text('cta_titel', 'Titel', $hh['cta_titel'] ?? '', $accentHelp) . '</div>'
            . f_area('cta_tekst', 'Tekst', $hh['cta_tekst'] ?? '', '', 2) . f_text('cta_knop_tekst', 'Tekst op de knop', $hh['cta_knop_tekst'] ?? '')
            . '</section>' . form_close();
        break;

    case 'diensten':
        $title = 'Diensten'; $view = '../#diensten';
        $ills = ['websites' => 'Illustratie: websites', 'logos' => "Illustratie: logo's", 'branding' => 'Illustratie: branding', 'software' => 'Illustratie: software', '' => 'Geen illustratie'];
        $body = form_open() . '<section class="panel">'
            . f_repeater('diensten', 'Diensten', $c['diensten'], ['titel' => ['text', 'Titel', ''], 'tekst' => ['area', 'Tekst', ''], 'illustratie' => [$ills, 'Beeld', 'Kies een van de vier illustraties in huisstijl.'], 'afbeelding' => ['image', 'Eigen afbeelding (optioneel)', 'Vervangt de illustratie. Liefst liggend, 16:9.']], 'Dienst toevoegen',
                'De volgorde hier is de volgorde op de site. Elke dienst krijgt automatisch een anker, bv. #dienst-websites, dat je in de lichtkrant kan gebruiken.')
            . '</section>' . form_close();
        break;

    case 'cases':
        $title = 'Realisaties'; $view = '../#realisaties';
        $body = '<div class="toolbar"><a class="btn-main" href="?p=case">+ Nieuwe case</a><p class="help">De volgorde hier is de volgorde op de homepage. Verborgen cases zie je alleen als je ingelogd bent.</p></div><ul class="caselist">';
        foreach ($cases as $i => $x) {
            $logo = safe_img($x['logo']);
            $btn = fn(string $act, string $label, string $cls = '', string $extra = '') => '<form method="post" action="?p=cases"' . $extra . '>' . csrf_field() . '<input type="hidden" name="slug" value="' . h($x['slug']) . '"><input type="hidden" name="actie" value="' . $act . '"><button type="submit" class="icon ' . $cls . '" title="' . h($label) . '" aria-label="' . h($label) . '">' . ['omhoog' => '↑', 'omlaag' => '↓', 'zichtbaar' => $x['zichtbaar'] ? '●' : '○', 'verwijder' => '✕'][$act] . '</button></form>';
            $body .= '<li class="' . ($x['zichtbaar'] ? '' : 'hidden-case') . '"><span class="thumb" style="background:' . h(safe_hex($x['kleur'])) . '">' . ($logo ? '<img src="../' . h($logo) . '" alt="">' : '') . '</span>'
                . '<a class="cname" href="?p=case&amp;slug=' . h(rawurlencode($x['slug'])) . '"><b>' . h($x['naam']) . '</b><span>' . ($x['zichtbaar'] ? 'Zichtbaar' : 'Verborgen') . ' · tegel ' . h($x['tegel']) . ' · ' . count($x['galerij']) . ' beelden</span></a>'
                . '<div class="ctools">' . ($i > 0 ? $btn('omhoog', 'Omhoog') : '<span class="icon ghost"></span>') . ($i < count($cases) - 1 ? $btn('omlaag', 'Omlaag') : '<span class="icon ghost"></span>')
                . $btn('zichtbaar', $x['zichtbaar'] ? 'Verbergen' : 'Zichtbaar maken')
                . '<a class="icon" href="../case.php?c=' . h(rawurlencode($x['slug'])) . '" target="_blank" title="Bekijk op de site" aria-label="Bekijk op de site">↗</a>'
                . $btn('verwijder', 'Verwijderen', 'danger', ' data-confirm="Case ' . h($x['naam']) . ' verwijderen?"') . '</div></li>';
        }
        $body .= '</ul>';
        break;

    case 'case':
        $slug = (string)($_GET['slug'] ?? '');
        $x = $slug !== '' ? find_case($slug) : null;
        if ($slug !== '' && !$x) { flash('Die case bestaat niet.', 'err'); go('?p=cases'); }
        $x = $x ?? normalize_case(['zichtbaar' => false, 'kleur' => '#4b2a77']);
        $title = $x['naam'] ?: 'Nieuwe case';
        $view = $x['slug'] ? '../case.php?c=' . rawurlencode($x['slug']) : '';
        $galHtml = '<div class="gal-list" data-gallery>';
        foreach ($x['galerij'] as $g) if ($g = safe_img($g)) $galHtml .= '<div class="gal-item"><img src="../' . h($g) . '" alt=""><input type="hidden" name="galerij[]" value="' . h($g) . '"><div class="rep-tools"><button type="button" class="icon" data-gmove="-1" aria-label="Naar links">←</button><button type="button" class="icon" data-gmove="1" aria-label="Naar rechts">→</button><button type="button" class="icon danger" data-gremove aria-label="Verwijderen uit galerij">✕</button></div></div>';
        $galHtml .= '</div>';
        $existing = '<option value="">— kies een bestaand beeld —</option>';
        foreach (all_uploads() as $u) $existing .= '<option value="' . h($u) . '">' . h(basename($u)) . '</option>';
        $body = form_open('?p=case' . ($x['slug'] ? '&slug=' . rawurlencode($x['slug']) : '')) . '<input type="hidden" name="orig_slug" value="' . h($x['slug']) . '">'
            . '<section class="panel"><h2>Basis</h2><div class="grid2">' . f_text('naam', 'Naam', $x['naam'], '', ['required' => 'required'])
            . f_text('slug', 'Adres (slug)', $x['slug'], 'Wordt case.php?c=<b>' . h($x['slug'] ?: 'naam-van-de-case') . '</b>. Leeg = automatisch uit de naam.') . '</div>'
            . '<div class="grid3">' . f_color('kleur', 'Hoofdkleur', $x['kleur'], 'Achtergrond van tegel en banner.') . f_select('tegel', 'Tegel op de homepage', $x['tegel'], ['normaal' => 'Normaal', 'groot' => 'Groot (2×2)', 'breed' => 'Breed (2×1)'])
            . f_check('zichtbaar', 'Zichtbaar op de site', (bool)$x['zichtbaar'], 'Uitvinken om de case te verbergen terwijl je eraan werkt.') . '</div>'
            . f_text('tagline', 'Tagline', $x['tagline'], 'Klein label boven de titel, bv. "Snooker club & academy".')
            . f_area('intro', 'Intro', $x['intro'], 'Eén of twee zinnen: wie is de klant en wat hebben we gemaakt?', 3)
            . '</section><section class="panel"><h2>Gegevens</h2><div class="grid2">'
            . f_text('klant', 'Klant', $x['klant']) . f_text('diensten', 'Diensten', implode(', ', $x['diensten']), 'Gescheiden door komma\'s, bv. Logo, Website.')
            . f_text('jaar', 'Jaar', $x['jaar']) . f_text('website', 'Website van de klant', $x['website'], 'Volledige link, bv. https://…') . '</div>'
            . '</section><section class="panel"><h2>Beelden</h2><div class="grid2">'
            . f_image('logo', 'Logo / tegelbeeld', $x['logo'], 'Gebruikt voor de tegel op de homepage. Liefst vierkant met een effen achtergrond in de hoofdkleur.')
            . f_image('hoofdbeeld', 'Hoofdbeeld (optioneel)', $x['hoofdbeeld'], 'Grote foto of mockup bovenaan de case. Leeg = het logo wordt getoond.') . '</div>'
            . '<div class="field"><span class="label">Galerij</span>' . $galHtml . '<small>Gebruik de pijltjes om de volgorde te wijzigen. Het eerste beeld wordt groot getoond.</small></div>'
            . '<div class="grid2"><div class="field"><label for="gnew">Beelden toevoegen</label><input type="file" id="gnew" name="galerij_nieuw[]" multiple accept="image/jpeg,image/png,image/webp,image/gif"><small>Je kan meerdere beelden tegelijk kiezen (max. 8 MB per beeld).</small></div>'
            . '<div class="field"><label for="gexist">Of voeg een bestaand beeld toe</label><select id="gexist" name="galerij_bestaand">' . $existing . '</select></div></div>'
            . '</section><section class="panel"><h2>Het verhaal</h2>'
            . f_area('vraag', 'De vraag', $x['vraag'], 'Wat had de klant nodig? Een lege regel maakt een nieuwe alinea.', 5)
            . f_area('aanpak', 'Onze aanpak', $x['aanpak'], 'Hoe zijn we te werk gegaan en waarom?', 5)
            . f_area('resultaat', 'Het resultaat', $x['resultaat'], 'Wat is er opgeleverd en wat bracht het op?', 5)
            . '</section><section class="panel"><h2>Merkidentiteit</h2>'
            . f_repeater('kleuren', 'Kleurenpalet', $x['kleuren'], ['hex' => ['color', 'Kleur', ''], 'naam' => ['text', 'Naam', 'Bv. Bordeaux']], 'Kleur toevoegen')
            . '<div class="grid2">' . f_text('lettertype_naam', 'Lettertype', $x['lettertype_naam'], 'Bv. Montserrat') . f_text('lettertype_gewichten', 'Gewichten', $x['lettertype_gewichten'], 'Bv. Bold · Black') . '</div>'
            . '</section><section class="panel"><h2>Citaat van de klant</h2>'
            . f_area('citaat_tekst', 'Citaat', $x['citaat_tekst'], '', 3)
            . '<div class="grid2">' . f_text('citaat_naam', 'Naam', $x['citaat_naam']) . f_text('citaat_functie', 'Functie', $x['citaat_functie']) . '</div>'
            . '</section>' . form_close('Case opslaan');
        break;

    case 'media':
        $title = 'Beelden'; $used = used_images();
        $body = '<section class="panel"><h2>Beelden uploaden</h2><form method="post" action="?p=media" enctype="multipart/form-data" class="upload-form">' . csrf_field() . '<input type="hidden" name="actie" value="upload"><input type="file" name="bestanden[]" multiple required accept="image/jpeg,image/png,image/webp,image/gif" aria-label="Kies beelden"><button class="btn-main" type="submit">Uploaden</button></form><p class="help">JPG, PNG, WebP of GIF, max. 8 MB per beeld. Tip: verklein foto\'s tot ±2000 px breed voor een snelle site.</p></section><div class="media-grid">';
        foreach (all_uploads() as $u) {
            $in = isset($used[$u]);
            $body .= '<figure class="media"><img src="../' . h($u) . '" alt="" loading="lazy"><figcaption><span title="' . h(basename($u)) . '">' . h(basename($u)) . '</span>' . ($in ? '<em>In gebruik</em>' : '<form method="post" action="?p=media" data-confirm="Dit beeld definitief verwijderen?">' . csrf_field() . '<input type="hidden" name="actie" value="verwijder"><input type="hidden" name="bestand" value="' . h($u) . '"><button class="icon danger" type="submit" aria-label="Verwijderen" title="Verwijderen">✕</button></form>') . '</figcaption></figure>';
        }
        $body .= '</div>';
        break;

    case 'backups':
        $title = 'Back-ups';
        $files = glob(FR_DATA . '/backups/inhoud-*.php') ?: [];
        rsort($files);
        $body = '<section class="panel"><p class="help">Bij elke opslag wordt de vorige versie bewaard. De laatste ' . FR_BACKUPS_KEEP . ' blijven staan. Terugzetten maakt eerst zelf ook een back-up, dus je kan niets kwijtraken.</p>';
        if (!$files) $body .= '<p>Nog geen back-ups. Die verschijnen zodra je iets opslaat.</p>';
        else {
            $body .= '<ul class="backups">';
            foreach ($files as $f) {
                $b = basename($f);
                preg_match('/inhoud-(\d{4})-(\d\d)-(\d\d)_(\d\d)-(\d\d)-(\d\d)/', $b, $m);
                $label = $m ? "$m[3]/$m[2]/$m[1] om $m[4]:$m[5]:$m[6]" : $b;
                $body .= '<li><span>' . h($label) . '</span><form method="post" action="?p=backups" data-confirm="De site terugzetten naar de versie van ' . h($label) . '?">' . csrf_field() . '<input type="hidden" name="bestand" value="' . h($b) . '"><button class="btn-sec" type="submit">Terugzetten</button></form></li>';
            }
            $body .= '</ul>';
        }
        $body .= '</section>';
        break;

    case 'wachtwoord':
        $title = 'Wachtwoord wijzigen';
        $body = form_open() . '<section class="panel narrow">'
            . '<div class="field"><label for="huidig">Huidig wachtwoord</label><input id="huidig" name="huidig" type="password" autocomplete="current-password" required></div>'
            . '<div class="field"><label for="nieuw">Nieuw wachtwoord</label><input id="nieuw" name="nieuw" type="password" autocomplete="new-password" minlength="10" required><small>Minstens 10 tekens.</small></div>'
            . '<div class="field"><label for="nieuw2">Herhaal nieuw wachtwoord</label><input id="nieuw2" name="nieuw2" type="password" autocomplete="new-password" minlength="10" required></div>'
            . '</section>' . form_close('Wachtwoord wijzigen');
        break;

    default:
        go('./');
}

function form_open(string $action = ''): string {
    global $p;
    return '<form method="post" action="' . h($action ?: '?p=' . $p) . '" enctype="multipart/form-data" class="editor" data-dirty-guard>' . csrf_field();
}
function form_close(string $label = 'Opslaan'): string {
    return '<div class="savebar"><span class="dirty-note">Niet-opgeslagen wijzigingen</span><button class="btn-main" type="submit">' . h($label) . '</button></div></form>';
}

admin_page($title, $body, $p, $view);

/* ================= Shell ================= */

function admin_head(string $title): void { ?>
<!doctype html>
<html lang="nl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="robots" content="noindex,nofollow">
<title><?= h($title) ?> · Beheer</title>
<link rel="icon" href="../assets/favicon.png">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Montserrat+Alternates:wght@700;800&family=Manrope:wght@400;500;600;700&family=Sora:wght@500;600&display=swap">
<link rel="stylesheet" href="admin.css?v=<?= @filemtime(__DIR__ . '/admin.css') ?>">
</head>
<?php }

function auth_page(string $title, string $intro, string $error, string $fields, string $fatal = ''): void {
    admin_head($title); ?>
<body class="auth">
  <main class="auth-card">
    <img src="../assets/logo.png" alt="Frisbee" class="auth-logo">
    <h1><?= h($title) ?></h1>
    <p class="help"><?= h($intro) ?></p>
    <?php if ($fatal): ?><div class="msg err"><?= h($fatal) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="msg err" role="alert"><?= h($error) ?></div><?php endif; ?>
    <form method="post" class="editor"><?= $fields ?></form>
  </main>
</body>
</html>
<?php }

function admin_page(string $title, string $body, string $current, string $view): void {
    admin_head($title);
    $nav = ['dashboard' => 'Overzicht', 'algemeen' => 'Algemeen', 'home' => 'Homepage', 'diensten' => 'Diensten', 'cases' => 'Realisaties', 'media' => 'Beelden', 'backups' => 'Back-ups', 'wachtwoord' => 'Wachtwoord'];
    $active = $current === 'case' ? 'cases' : $current;
    $msgs = $_SESSION['flash'] ?? []; unset($_SESSION['flash']); ?>
<body>
<div class="shell">
  <aside class="side">
    <a class="brand" href="./"><img src="../assets/logo.png" alt="Frisbee"><span>Beheer</span></a>
    <nav aria-label="Beheer"><?php foreach ($nav as $k => $label): ?><a href="?p=<?= $k ?>"<?= $k === $active ? ' aria-current="page"' : '' ?>><?= h($label) ?></a><?php endforeach; ?></nav>
    <div class="side-foot">
      <a href="../" target="_blank">Bekijk website ↗</a>
      <form method="post" action="?p=logout"><?= csrf_field() ?><button type="submit" class="linklike">Uitloggen</button></form>
    </div>
  </aside>
  <main class="main">
    <header class="top">
      <div><?php if ($current === 'case'): ?><a class="back" href="?p=cases">← Alle realisaties</a><?php endif; ?><h1><?= h($title) ?></h1></div>
      <?php if ($view): ?><a class="btn-sec" href="<?= h($view) ?>" target="_blank">Bekijk op de site ↗</a><?php endif; ?>
    </header>
    <?php foreach ($msgs as [$type, $m]): ?><div class="msg <?= $type === 'err' ? 'err' : 'ok' ?>" role="<?= $type === 'err' ? 'alert' : 'status' ?>"><?= h($m) ?></div><?php endforeach; ?>
    <?= $body ?>
  </main>
</div>
<script src="admin.js?v=<?= @filemtime(__DIR__ . '/admin.js') ?>"></script>
</body>
</html>
<?php }
