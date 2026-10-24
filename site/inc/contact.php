<?php
/**
 * Contact form: captcha, spam protection, storage and e-mail.
 * - Built-in captcha: a small sum, signed with a server secret (no cookies needed),
 *   plus a hidden honeypot field, a minimum fill-in time and a per-IP rate limit.
 * - Optional: Cloudflare Turnstile instead of the sum, when keys are filled in.
 * - Every message is stored in data/berichten.php first, so nothing gets lost if mail fails.
 */
declare(strict_types=1);
require_once __DIR__ . '/core.php';

const FR_FORM_DEFAULTS = ['titel' => 'Vertel ons over je project', 'intro' => 'Vul het formulier in en we nemen binnen twee werkdagen contact met je op.',
    'ontvangers' => '', 'onderwerp' => 'Nieuw bericht via frisbee.agency', 'bedankt' => 'Bedankt! We hebben je bericht goed ontvangen en nemen snel contact op.',
    'telefoon' => true, 'bedrijf' => true, 'budget' => false, 'budget_opties' => "Minder dan € 1.000\n€ 1.000 – € 5.000\n€ 5.000 – € 10.000\nMeer dan € 10.000",
    'privacy' => 'Ik ga ermee akkoord dat Frisbee Agency mijn gegevens gebruikt om op mijn vraag te antwoorden.'];

function form_settings(): array {
    $f = array_replace(FR_FORM_DEFAULTS, array_intersect_key(content()['formulier'] ?? [], FR_FORM_DEFAULTS));
    // texts that must never be empty fall back to the default
    foreach (['titel', 'onderwerp', 'bedankt', 'privacy'] as $k) if (trim((string)$f[$k]) === '') $f[$k] = FR_FORM_DEFAULTS[$k];
    return $f;
}

/** Server-side secret used to sign captcha tokens; created on first use. */
function form_secret(): string {
    $cfg = config();
    if (empty($cfg['geheim'])) { $cfg['geheim'] = bin2hex(random_bytes(32)); if (!empty($cfg['hash'])) save_config($cfg); else return hash('sha256', __DIR__ . php_uname()); }
    return $cfg['geheim'];
}

function turnstile_keys(): array { $c = config(); return [trim($c['turnstile_site'] ?? ''), trim($c['turnstile_geheim'] ?? '')]; }
function uses_turnstile(): bool { [$a, $b] = turnstile_keys(); return $a !== '' && $b !== ''; }

/** New captcha: [question, token]. The answer itself never leaves the server unsigned. */
function captcha_new(): array {
    $a = random_int(2, 9); $b = random_int(2, 9);
    $ts = (string)time(); $nonce = bin2hex(random_bytes(8));
    $sig = hash_hmac('sha256', "$ts|$nonce|" . ($a + $b), form_secret());
    return ["Hoeveel is $a + $b?", "$ts.$nonce.$sig"];
}

function captcha_check(string $token, string $answer): string {
    $parts = explode('.', $token);
    if (count($parts) !== 3) return 'De controlevraag is ongeldig. Vernieuw de pagina en probeer opnieuw.';
    [$ts, $nonce, $sig] = $parts;
    $age = time() - (int)$ts;
    if ($age < 3) return 'Dat ging wel erg snel. Wacht een paar seconden en verstuur opnieuw.';
    if ($age > 7200) return 'Het formulier stond te lang open. Vernieuw de pagina en probeer opnieuw.';
    $expected = hash_hmac('sha256', "$ts|$nonce|" . (int)trim($answer), form_secret());
    if (!hash_equals($expected, $sig)) return 'Het antwoord op de controlevraag klopt niet.';
    // each token works once
    $used = json_decode(guarded_read(FR_DATA . '/captcha-gebruikt.php') ?? '{}', true) ?: [];
    $used = array_filter($used, fn($t) => $t > time() - 7200);
    if (isset($used[$nonce])) return 'Dit formulier werd al verstuurd. Vernieuw de pagina om nog een bericht te sturen.';
    $used[$nonce] = time();
    guarded_write(FR_DATA . '/captcha-gebruikt.php', json_encode($used));
    return '';
}

function turnstile_check(string $response): string {
    [, $secret] = turnstile_keys();
    $ctx = stream_context_create(['http' => ['method' => 'POST', 'timeout' => 8, 'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
        'content' => http_build_query(['secret' => $secret, 'response' => $response, 'remoteip' => client_ip()])]]);
    $res = @file_get_contents('https://challenges.cloudflare.com/turnstile/v0/siteverify', false, $ctx);
    $ok = $res && (json_decode($res, true)['success'] ?? false);
    return $ok ? '' : 'De captcha kon niet bevestigd worden. Probeer opnieuw.';
}

/** Max 5 messages per IP per hour. */
function form_rate_limited(): bool {
    $file = FR_DATA . '/formulier-ip.php';
    $d = json_decode(guarded_read($file) ?? '{}', true) ?: [];
    $ip = hash('sha256', client_ip());
    foreach ($d as $k => $v) { $d[$k] = array_values(array_filter($v, fn($t) => $t > time() - 3600)); if (!$d[$k]) unset($d[$k]); }
    $limited = count($d[$ip] ?? []) >= 5;
    if (!$limited) { $d[$ip][] = time(); guarded_write($file, json_encode($d)); }
    return $limited;
}

/* ---------- Messages store ---------- */

function messages(): array { return json_decode(guarded_read(FR_DATA . '/berichten.php') ?? '[]', true) ?: []; }
function save_messages(array $m): void { guarded_write(FR_DATA . '/berichten.php', json_encode(array_slice(array_values($m), 0, 1000), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)); }

/* ---------- Mail ---------- */

function one_line(string $s): string { return trim(preg_replace('/[\r\n\t]+/', ' ', $s)); }
function mime_header(string $s): string { return preg_match('/[^\x20-\x7e]/', $s) ? '=?UTF-8?B?' . base64_encode($s) . '?=' : $s; }

/** Send a plain-text mail. Uses SMTP when configured, otherwise PHP mail(). Returns '' or an error. */
function send_mail(array $to, string $subject, string $body, string $replyTo = '', string $replyName = ''): string {
    $smtp = config()['smtp'] ?? [];
    $host = parse_url('https://' . ($_SERVER['HTTP_HOST'] ?? 'localhost'), PHP_URL_HOST) ?: 'localhost';
    $from = trim($smtp['afzender'] ?? '') ?: 'website@' . preg_replace('/^www\./', '', $host);
    $fromName = content()['site']['naam'] ?? 'Website';
    $headers = [
        'Date' => date('r'),
        'From' => mime_header($fromName) . " <$from>",
        'To' => implode(', ', $to),
        'Subject' => mime_header(one_line($subject)),
        'Message-ID' => '<' . bin2hex(random_bytes(12)) . '@' . substr(strrchr($from, '@'), 1) . '>',
        'MIME-Version' => '1.0',
        'Content-Type' => 'text/plain; charset=UTF-8',
        'Content-Transfer-Encoding' => 'base64',
    ];
    if ($replyTo && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) $headers['Reply-To'] = ($replyName ? mime_header(one_line($replyName)) . ' ' : '') . "<$replyTo>";
    $encoded = rtrim(chunk_split(base64_encode($body), 76, "\r\n"));

    if (!empty($smtp['host'])) return smtp_send($smtp, $from, $to, $headers, $encoded);

    $hdr = '';
    foreach ($headers as $k => $v) if (!in_array($k, ['To', 'Subject'], true)) $hdr .= "$k: $v\r\n";
    $ok = @mail(implode(', ', $to), $headers['Subject'], $encoded, rtrim($hdr), '-f' . $from);
    return $ok ? '' : 'De server kon de mail niet versturen (mail() mislukt).';
}

/** Minimal SMTP client: SSL (465) or STARTTLS (587), AUTH LOGIN. */
function smtp_send(array $s, string $from, array $to, array $headers, string $body): string {
    $port = (int)($s['poort'] ?? 587) ?: 587;
    $secure = $s['beveiliging'] ?? 'tls';
    $target = ($secure === 'ssl' ? 'ssl://' : 'tcp://') . $s['host'] . ':' . $port;
    $ctx = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'SNI_enabled' => true]]);
    $fp = @stream_socket_client($target, $errno, $err, 15, STREAM_CLIENT_CONNECT, $ctx);
    if (!$fp) return "Kan geen verbinding maken met de mailserver ($err).";
    stream_set_timeout($fp, 15);
    $read = function () use ($fp): array { $data = ''; while (($line = fgets($fp, 515)) !== false) { $data .= $line; if (isset($line[3]) && $line[3] === ' ') break; } return [(int)substr($data, 0, 3), trim($data)]; };
    $cmd = function (string $c, array $okCodes) use ($fp, $read): string { fwrite($fp, $c . "\r\n"); [$code, $msg] = $read(); return in_array($code, $okCodes, true) ? '' : $msg; };
    $ehloHost = parse_url('https://' . ($_SERVER['HTTP_HOST'] ?? 'localhost'), PHP_URL_HOST) ?: 'localhost';
    try {
        [$code, $msg] = $read(); if ($code !== 220) return "Mailserver weigert: $msg";
        if ($e = $cmd("EHLO $ehloHost", [250])) return "EHLO mislukt: $e";
        if ($secure === 'tls') {
            if ($e = $cmd('STARTTLS', [220])) return "STARTTLS mislukt: $e";
            if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT)) return 'Beveiligde verbinding mislukt.';
            if ($e = $cmd("EHLO $ehloHost", [250])) return "EHLO na TLS mislukt: $e";
        }
        if (!empty($s['gebruiker'])) {
            if ($e = $cmd('AUTH LOGIN', [334])) return "Inloggen niet ondersteund: $e";
            if ($e = $cmd(base64_encode($s['gebruiker']), [334])) return "Gebruikersnaam geweigerd: $e";
            if ($e = $cmd(base64_encode($s['wachtwoord'] ?? ''), [235])) return 'Inloggen op de mailserver mislukt. Controleer gebruikersnaam en wachtwoord.';
        }
        if ($e = $cmd("MAIL FROM:<$from>", [250])) return "Afzender geweigerd: $e";
        foreach ($to as $r) if ($e = $cmd("RCPT TO:<$r>", [250, 251])) return "Ontvanger $r geweigerd: $e";
        if ($e = $cmd('DATA', [354])) return "DATA geweigerd: $e";
        $raw = '';
        foreach ($headers as $k => $v) $raw .= "$k: $v\r\n";
        $raw .= "\r\n" . preg_replace('/^\./m', '..', $body) . "\r\n.";
        if ($e = $cmd($raw, [250])) return "Bericht geweigerd: $e";
        $cmd('QUIT', [221]);
        return '';
    } finally { fclose($fp); }
}

function form_recipients(): array {
    $list = preg_split('/[\s,;]+/', form_settings()['ontvangers'], -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $list = array_values(array_filter($list, fn($e) => filter_var($e, FILTER_VALIDATE_EMAIL)));
    if (!$list && ($e = content()['site']['email'] ?? '') && filter_var($e, FILTER_VALIDATE_EMAIL)) $list = [$e];
    return $list;
}

/* ---------- Handle a submission ---------- */

/** Returns ['ok' => bool, 'fouten' => [field => message], 'melding' => string]. */
function handle_contact(array $in): array {
    $f = form_settings();
    $err = [];
    // honeypot: humans never see this field
    if (trim((string)($in['website'] ?? '')) !== '') return ['ok' => true, 'fouten' => [], 'melding' => $f['bedankt']];

    $v = fn(string $k, int $max) => mb_substr(trim(str_replace("\r\n", "\n", (string)($in[$k] ?? ''))), 0, $max);
    $data = ['naam' => one_line($v('naam', 120)), 'email' => one_line($v('email', 200)), 'telefoon' => one_line($v('telefoon', 40)),
             'bedrijf' => one_line($v('bedrijf', 120)), 'dienst' => one_line($v('dienst', 80)), 'budget' => one_line($v('budget', 80)), 'bericht' => $v('bericht', 5000)];

    if ($data['naam'] === '') $err['naam'] = 'Vul je naam in.';
    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) $err['email'] = 'Vul een geldig e-mailadres in.';
    if (mb_strlen($data['bericht']) < 10) $err['bericht'] = 'Vertel ons iets meer over je vraag (minstens 10 tekens).';
    if ($data['telefoon'] !== '' && !preg_match('/^[0-9 +().\/-]{6,40}$/', $data['telefoon'])) $err['telefoon'] = 'Dat telefoonnummer lijkt niet te kloppen.';
    if (empty($in['privacy'])) $err['privacy'] = 'Vink dit aan zodat we je mogen antwoorden.';
    $dienstNaam = '';
    if ($data['dienst'] !== '') { $dd = find_dienst($data['dienst']); $dienstNaam = $dd ? $dd['titel'] : ''; }

    if (!$err) {
        $cap = uses_turnstile() ? turnstile_check((string)($in['cf-turnstile-response'] ?? '')) : captcha_check((string)($in['captcha_token'] ?? ''), (string)($in['captcha'] ?? ''));
        if ($cap) $err['captcha'] = $cap;
    }
    if ($err) return ['ok' => false, 'fouten' => $err, 'melding' => 'Controleer de gemarkeerde velden.'];
    if (form_rate_limited()) return ['ok' => false, 'fouten' => [], 'melding' => 'Je hebt de voorbije tijd al enkele berichten verstuurd. Probeer het later opnieuw of mail ons rechtstreeks.'];

    $msg = ['id' => bin2hex(random_bytes(6)), 'datum' => date('c'), 'gelezen' => false, 'dienst_naam' => $dienstNaam,
            'pagina' => one_line(mb_substr((string)($in['pagina'] ?? ''), 0, 200))] + $data;

    $lines = ["Nieuw bericht via het contactformulier van " . (content()['site']['naam'] ?? 'de website') . ".", ''];
    foreach (['Naam' => $data['naam'], 'E-mail' => $data['email'], 'Telefoon' => $data['telefoon'], 'Bedrijf' => $data['bedrijf'], 'Dienst' => $dienstNaam, 'Budget' => $data['budget']] as $k => $val)
        if ($val !== '') $lines[] = str_pad($k . ':', 10) . $val;
    $lines[] = ''; $lines[] = 'Bericht:'; $lines[] = $data['bericht']; $lines[] = '';
    $lines[] = '—'; $lines[] = 'Verstuurd op ' . date('d/m/Y \o\m H:i') . ($msg['pagina'] ? ' vanaf ' . $msg['pagina'] : '') . '. Antwoord rechtstreeks op deze mail om de afzender te bereiken.';

    $to = form_recipients();
    $mailErr = $to ? send_mail($to, $f['onderwerp'] . ($dienstNaam ? " ($dienstNaam)" : ''), implode("\n", $lines), $data['email'], $data['naam']) : 'Er is geen ontvanger ingesteld.';
    $msg['mail'] = $mailErr === '' ? 'verstuurd' : 'mislukt: ' . $mailErr;

    $all = messages(); array_unshift($all, $msg); save_messages($all);
    return ['ok' => true, 'fouten' => [], 'melding' => $f['bedankt']];
}
