<?php
/**
 * Frisbee CMS — core helpers: storage, escaping, sessions, auth, CSRF, uploads.
 * No database: content lives in data/content.php (JSON behind a PHP exit guard,
 * so the file can never be downloaded even if the web server serves it raw).
 */
declare(strict_types=1);

const FR_ROOT = __DIR__ . '/..';
const FR_DATA = FR_ROOT . '/data';
const FR_UPLOADS = FR_ROOT . '/uploads';
const FR_GUARD = "<?php http_response_code(404); exit; ?>\n";
const FR_MAX_UPLOAD = 8 * 1024 * 1024;       // 8 MB per image
const FR_BACKUPS_KEEP = 30;
const FR_SESSION_IDLE = 2 * 60 * 60;          // logged out after 2 hours idle

/* ---------- Escaping & text ---------- */

function h(?string $s): string { return htmlspecialchars($s ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

/** Escape text and turn *word* into a highlighted span (used in titles). */
function accent(?string $s, string $class = 'hl'): string {
    return preg_replace('/\*(.+?)\*/u', '<span class="' . $class . '">$1</span>', h($s));
}

/** Paragraphs from plain text: blank line = new paragraph, single newline = <br>. */
function paras(?string $s): string {
    $s = trim((string)$s);
    if ($s === '') return '';
    $out = '';
    foreach (preg_split('/\R\s*\R/u', $s) as $p) $out .= '<p>' . nl2br(h(trim($p)), false) . '</p>';
    return $out;
}

/** Only allow safe link targets: http(s), mailto, tel, in-page anchors and relative paths. */
function safe_url(?string $u): string {
    $u = trim((string)$u);
    if ($u === '') return '';
    if (preg_match('#^(https?://|mailto:|tel:|\#|/|[a-z0-9._-]+(\.php|\.html)?([?\#].*)?$)#i', $u) && !preg_match('#^\s*(javascript|data|vbscript):#i', $u)) return $u;
    return '';
}

function safe_hex(?string $c, string $fallback = '#160C24'): string {
    $c = trim((string)$c);
    return preg_match('/^#[0-9a-f]{6}$/i', $c) ? strtolower($c) : $fallback;
}

/** Path to an uploaded image, only if it really is one of ours. */
function safe_img(?string $p): string {
    $p = trim((string)$p);
    if (!preg_match('#^uploads/[A-Za-z0-9._-]+\.(jpe?g|png|webp|gif)$#i', $p)) return '';
    return is_file(FR_ROOT . '/' . $p) ? $p : '';
}

function slugify(string $s): string {
    $s = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s) ?: $s;
    $s = strtolower(preg_replace('/[^A-Za-z0-9]+/', '-', $s));
    return trim($s, '-') ?: 'case';
}

function is_light(string $hex): bool {
    $hex = ltrim(safe_hex($hex), '#');
    $c = array_map(fn($i) => hexdec(substr($hex, $i, 2)) / 255, [0, 2, 4]);
    $c = array_map(fn($v) => $v <= 0.03928 ? $v / 12.92 : (($v + 0.055) / 1.055) ** 2.4, $c);
    return (0.2126 * $c[0] + 0.7152 * $c[1] + 0.0722 * $c[2]) > 0.28;
}

/* ---------- Guarded file storage ---------- */

function guarded_read(string $file): ?string {
    if (!is_file($file)) return null;
    $raw = file_get_contents($file);
    if ($raw === false) return null;
    return str_starts_with($raw, FR_GUARD) ? substr($raw, strlen(FR_GUARD)) : $raw;
}

function guarded_write(string $file, string $data): void {
    $dir = dirname($file);
    if (!is_dir($dir)) mkdir($dir, 0750, true);
    $tmp = $file . '.tmp-' . bin2hex(random_bytes(4));
    if (file_put_contents($tmp, FR_GUARD . $data, LOCK_EX) === false) throw new RuntimeException('Kan niet schrijven naar ' . basename($file) . '. Controleer de schrijfrechten van de map data/.');
    @chmod($tmp, 0640);
    rename($tmp, $file);
}

/* ---------- Content ---------- */

function content(): array {
    static $c = null;
    if ($c !== null) return $c;
    $json = guarded_read(FR_DATA . '/content.php');
    if ($json === null) $json = file_get_contents(__DIR__ . '/standaard-inhoud.json');
    $c = json_decode($json, true) ?: [];
    // make sure every key exists so templates never hit undefined indexes
    $c += ['site' => [], 'home' => [], 'diensten' => [], 'cases' => []];
    return $c;
}

function save_content(array $c): void {
    // keep a backup of the current version before overwriting
    $current = guarded_read(FR_DATA . '/content.php');
    if ($current !== null) {
        guarded_write(FR_DATA . '/backups/inhoud-' . date('Y-m-d_H-i-s') . '-' . bin2hex(random_bytes(2)) . '.php', $current);
        $all = glob(FR_DATA . '/backups/inhoud-*.php') ?: [];
        sort($all);
        foreach (array_slice($all, 0, max(0, count($all) - FR_BACKUPS_KEEP)) as $old) @unlink($old);
    }
    guarded_write(FR_DATA . '/content.php', json_encode($c, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}

const FR_CASE_DEFAULTS = ['slug' => '', 'naam' => '', 'zichtbaar' => false, 'tegel' => 'normaal', 'kleur' => '#160c24', 'tagline' => '', 'intro' => '',
    'klant' => '', 'diensten' => [], 'jaar' => '', 'website' => '', 'logo' => '', 'hoofdbeeld' => '', 'vraag' => '', 'aanpak' => '', 'resultaat' => '',
    'galerij' => [], 'galerijen' => [], 'kleuren' => [], 'lettertype_naam' => '', 'lettertype_gewichten' => '', 'citaat_tekst' => '', 'citaat_naam' => '', 'citaat_functie' => ''];

function normalize_case(array $c): array {
    $isOld = !array_key_exists('galerijen', $c);
    $c = array_replace(FR_CASE_DEFAULTS, array_intersect_key($c, FR_CASE_DEFAULTS));
    $c['galerijen'] = is_array($c['galerijen']) ? array_filter(array_map(fn($l) => array_values((array)$l), $c['galerijen'])) : [];
    // Cases saved before the split had one gallery. If such a case belongs to exactly
    // one service, its images move to that service; otherwise they stay in "Overige beelden".
    if ($isOld && $c['galerij']) {
        $ids = case_dienst_ids($c);
        if (count($ids) === 1) { $c['galerijen'] = [$ids[0] => $c['galerij']]; $c['galerij'] = []; }
    }
    return $c;
}

/** Service ids a case is tagged with (matching "Logo" to "Logo's" etc.). */
function case_dienst_ids(array $c): array {
    $ids = [];
    foreach (content()['diensten'] as $d) {
        $keys = [dienst_key($d['id'] ?? ''), dienst_key($d['titel'] ?? '')];
        foreach ((array)$c['diensten'] as $tag) if (in_array(dienst_key((string)$tag), $keys, true)) { $ids[] = $d['id']; break; }
    }
    return array_values(array_unique($ids));
}

/**
 * Image groups for a case page, in the order of the services.
 * Only groups that have images are returned; images of unknown services and the
 * old single gallery end up in "Overige beelden".
 */
function case_galleries(array $c): array {
    $out = []; $rest = array_values(array_filter(array_map('safe_img', $c['galerij'])));
    $known = [];
    foreach (content()['diensten'] as $d) {
        $known[$d['id']] = true;
        $imgs = array_values(array_filter(array_map('safe_img', $c['galerijen'][$d['id']] ?? [])));
        if ($imgs) $out[] = ['id' => $d['id'], 'titel' => $d['titel'], 'beelden' => $imgs];
    }
    foreach ($c['galerijen'] as $id => $list) if (!isset($known[$id])) $rest = array_merge($rest, array_values(array_filter(array_map('safe_img', $list))));
    if ($rest) $out[] = ['id' => 'overige', 'titel' => $out ? 'Overige beelden' : 'Beelden', 'beelden' => array_values(array_unique($rest))];
    return $out;
}

/** All images of a case, for counts. */
function case_image_count(array $c): int { return array_sum(array_map(fn($g) => count($g['beelden']), case_galleries($c))); }

function visible_cases(): array {
    return array_values(array_filter(array_map('normalize_case', content()['cases']), fn($c) => !empty($c['zichtbaar'])));
}

function find_case(string $slug): ?array {
    foreach (content()['cases'] as $c) if (($c['slug'] ?? '') === $slug) return normalize_case($c);
    return null;
}

/* ---------- Sessions & auth ---------- */

function start_session(): void {
    if (session_status() === PHP_SESSION_ACTIVE) return;
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    session_name('frisbee_admin');
    session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => $https, 'httponly' => true, 'samesite' => 'Strict']);
    ini_set('session.use_strict_mode', '1');
    session_start();
    if (!empty($_SESSION['uid']) && (time() - ($_SESSION['seen'] ?? 0)) > FR_SESSION_IDLE) {
        $_SESSION = [];
        session_regenerate_id(true);
    }
    $_SESSION['seen'] = time();
}

/** True when an admin is logged in. On public pages we only look if the cookie exists. */
function is_admin(): bool {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        if (empty($_COOKIE['frisbee_admin'])) return false;
        start_session();
    }
    return !empty($_SESSION['uid']);
}

function config(): array {
    $json = guarded_read(FR_DATA . '/config.php');
    return $json ? (json_decode($json, true) ?: []) : [];
}

function save_config(array $cfg): void { guarded_write(FR_DATA . '/config.php', json_encode($cfg, JSON_PRETTY_PRINT)); }

function is_installed(): bool { return !empty(config()['hash']); }

/* Login throttling: max 5 failed attempts per IP per 15 minutes. */
function throttle_file(): string { return FR_DATA . '/pogingen.php'; }
function throttle_data(): array { return json_decode(guarded_read(throttle_file()) ?? '[]', true) ?: []; }
function client_ip(): string { return $_SERVER['REMOTE_ADDR'] ?? '0'; }
function login_locked(): int {
    $d = throttle_data(); $ip = hash('sha256', client_ip());
    $hits = array_filter($d[$ip] ?? [], fn($t) => $t > time() - 900);
    return count($hits) >= 5 ? (int)(min($hits) + 900 - time()) : 0;
}
function login_failed(): void {
    $d = throttle_data(); $ip = hash('sha256', client_ip());
    foreach ($d as $k => $v) { $d[$k] = array_values(array_filter($v, fn($t) => $t > time() - 900)); if (!$d[$k]) unset($d[$k]); }
    $d[$ip][] = time();
    guarded_write(throttle_file(), json_encode($d));
}
function login_cleared(): void { $d = throttle_data(); unset($d[hash('sha256', client_ip())]); guarded_write(throttle_file(), json_encode($d)); }

/* ---------- CSRF ---------- */

function csrf_token(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function csrf_field(): string { return '<input type="hidden" name="_csrf" value="' . h(csrf_token()) . '">'; }
function csrf_check(): void {
    if (!hash_equals($_SESSION['csrf'] ?? '', (string)($_POST['_csrf'] ?? ''))) {
        http_response_code(400);
        exit('De sessie is verlopen of het formulier is ongeldig. Ga terug, vernieuw de pagina en probeer opnieuw.');
    }
}

/* ---------- Uploads ---------- */

/**
 * Store one uploaded image from $_FILES-style array. Returns "uploads/xxx.ext".
 * Checks size, real image type (not the browser's claim) and gives it a random name.
 */
function store_upload(array $f): string {
    if (($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return '';
    if ($f['error'] !== UPLOAD_ERR_OK) throw new RuntimeException('Upload mislukt (foutcode ' . (int)$f['error'] . '). Is het bestand kleiner dan 8 MB?');
    if ($f['size'] > FR_MAX_UPLOAD) throw new RuntimeException('"' . $f['name'] . '" is groter dan 8 MB. Verklein het beeld en probeer opnieuw.');
    if (!is_uploaded_file($f['tmp_name'])) throw new RuntimeException('Ongeldige upload.');
    $types = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
    if (!isset($types[$mime]) || @getimagesize($f['tmp_name']) === false) throw new RuntimeException('"' . $f['name'] . '" is geen geldige afbeelding. Gebruik JPG, PNG, WebP of GIF.');
    $base = substr(slugify(pathinfo($f['name'], PATHINFO_FILENAME)), 0, 40);
    $name = $base . '-' . bin2hex(random_bytes(3)) . '.' . $types[$mime];
    if (!is_dir(FR_UPLOADS)) mkdir(FR_UPLOADS, 0755, true);
    if (!move_uploaded_file($f['tmp_name'], FR_UPLOADS . '/' . $name)) throw new RuntimeException('Kan de afbeelding niet opslaan. Controleer de schrijfrechten van de map uploads/.');
    @chmod(FR_UPLOADS . '/' . $name, 0644);
    return 'uploads/' . $name;
}

/** Normalise $_FILES['x'] with multiple files into a list of single-file arrays. */
function files_list(?array $f): array {
    if (!$f || !is_array($f['name'] ?? null)) return $f ? [$f] : [];
    $out = [];
    foreach ($f['name'] as $i => $n) $out[] = ['name' => $n, 'type' => $f['type'][$i], 'tmp_name' => $f['tmp_name'][$i], 'error' => $f['error'][$i], 'size' => $f['size'][$i]];
    return $out;
}

function all_uploads(): array {
    $list = glob(FR_UPLOADS . '/*.{jpg,jpeg,png,webp,gif}', GLOB_BRACE) ?: [];
    usort($list, fn($a, $b) => filemtime($b) <=> filemtime($a));
    return array_map(fn($p) => 'uploads/' . basename($p), $list);
}

/** Every image path referenced anywhere in the content. */
function used_images(): array {
    $used = [];
    $c = content();
    array_walk_recursive($c, function ($v) use (&$used) { if (is_string($v) && str_starts_with($v, 'uploads/')) $used[$v] = true; });
    return $used;
}

/* ---------- Services ---------- */

const FR_DIENST_DEFAULTS = ['id' => '', 'titel' => '', 'tekst' => '', 'illustratie' => '', 'afbeelding' => '', 'zichtbaar' => true,
    'pagina_titel' => '', 'intro' => '', 'hoofdbeeld' => '', 'secties' => [], 'leveren' => [], 'galerij' => [], 'cases' => [],
    'cta_titel' => '', 'cta_tekst' => ''];

function normalize_dienst(array $d): array {
    $d = array_replace(FR_DIENST_DEFAULTS, array_intersect_key($d, FR_DIENST_DEFAULTS));
    $d['zichtbaar'] = $d['zichtbaar'] !== false;
    return $d;
}

function all_diensten(): array { return array_map('normalize_dienst', content()['diensten']); }
function visible_diensten(): array { return array_values(array_filter(all_diensten(), fn($d) => $d['zichtbaar'])); }

function find_dienst(string $id): ?array {
    foreach (all_diensten() as $d) if ($d['id'] === $id) return $d;
    return null;
}

/** Simplified key for matching a service to the "diensten" tags of a case: "Logo's" ~ "logo" ~ "Logos". */
function dienst_key(string $s): string { return rtrim(slugify($s), 's'); }

/** Cases shown on a service page: the hand-picked ones, or else every visible case tagged with this service. */
function dienst_cases(array $d): array {
    $cases = visible_cases();
    if ($d['cases']) {
        $bySlug = array_column($cases, null, 'slug');
        return array_values(array_filter(array_map(fn($s) => $bySlug[$s] ?? null, $d['cases'])));
    }
    $keys = [dienst_key($d['id']), dienst_key($d['titel'])];
    return array_values(array_filter($cases, fn($c) => array_intersect($keys, array_map('dienst_key', $c['diensten']))));
}

function dienst_url(array $d): string { return 'dienst.php?d=' . rawurlencode($d['id']); }
