<?php
/** Form building blocks for the admin. All output is escaped. */
declare(strict_types=1);

function f_text(string $name, string $label, ?string $value, string $help = '', array $attr = []): string {
    $id = 'f_' . preg_replace('/[^a-z0-9]+/i', '_', $name);
    $extra = '';
    foreach ($attr as $k => $v) $extra .= ' ' . h($k) . '="' . h((string)$v) . '"';
    return '<div class="field"><label for="' . $id . '">' . h($label) . '</label><input type="text" id="' . $id . '" name="' . h($name) . '" value="' . h($value) . '"' . $extra . '>' . ($help ? '<small>' . $help . '</small>' : '') . '</div>';
}

function f_area(string $name, string $label, ?string $value, string $help = '', int $rows = 4): string {
    $id = 'f_' . preg_replace('/[^a-z0-9]+/i', '_', $name);
    return '<div class="field"><label for="' . $id . '">' . h($label) . '</label><textarea id="' . $id . '" name="' . h($name) . '" rows="' . $rows . '">' . h($value) . '</textarea>' . ($help ? '<small>' . $help . '</small>' : '') . '</div>';
}

function f_color(string $name, string $label, ?string $value, string $help = ''): string {
    $id = 'f_' . preg_replace('/[^a-z0-9]+/i', '_', $name);
    $v = safe_hex($value, '#160c24');
    return '<div class="field"><label for="' . $id . '">' . h($label) . '</label><div class="color-pair"><input type="color" value="' . h($v) . '" data-sync="' . $id . '" aria-label="Kies kleur"><input type="text" id="' . $id . '" name="' . h($name) . '" value="' . h($v) . '" pattern="#[0-9a-fA-F]{6}" maxlength="7"></div>' . ($help ? '<small>' . $help . '</small>' : '') . '</div>';
}

function f_select(string $name, string $label, ?string $value, array $options, string $help = ''): string {
    $id = 'f_' . preg_replace('/[^a-z0-9]+/i', '_', $name);
    $o = '';
    foreach ($options as $k => $v) $o .= '<option value="' . h((string)$k) . '"' . ((string)$k === (string)$value ? ' selected' : '') . '>' . h($v) . '</option>';
    return '<div class="field"><label for="' . $id . '">' . h($label) . '</label><select id="' . $id . '" name="' . h($name) . '">' . $o . '</select>' . ($help ? '<small>' . $help . '</small>' : '') . '</div>';
}

function f_check(string $name, string $label, bool $checked, string $help = ''): string {
    $id = 'f_' . preg_replace('/[^a-z0-9]+/i', '_', $name);
    return '<div class="field check"><label><input type="checkbox" id="' . $id . '" name="' . h($name) . '" value="1"' . ($checked ? ' checked' : '') . '> ' . h($label) . '</label>' . ($help ? '<small>' . $help . '</small>' : '') . '</div>';
}

/** Image field: preview + pick an existing upload + upload a new one + remove. */
function f_image(string $name, string $label, ?string $value, string $help = ''): string {
    $id = 'f_' . preg_replace('/[^a-z0-9]+/i', '_', $name);
    $v = safe_img($value);
    $opts = '<option value="">— geen afbeelding —</option>';
    foreach (all_uploads() as $u) $opts .= '<option value="' . h($u) . '"' . ($u === $v ? ' selected' : '') . '>' . h(basename($u)) . '</option>';
    return '<div class="field img-field"><span class="label">' . h($label) . '</span>
      <div class="img-row">
        <div class="img-preview" data-preview="' . $id . '">' . ($v ? '<img src="../' . h($v) . '" alt="">' : '<span>Geen beeld</span>') . '</div>
        <div class="img-controls">
          <label class="mini" for="' . $id . '">Kies uit bestaande beelden</label>
          <select id="' . $id . '" name="' . h($name) . '" data-img-select>' . $opts . '</select>
          <label class="mini" for="' . $id . '_up">Of upload een nieuw beeld</label>
          <input type="file" id="' . $id . '_up" name="' . h(upload_name($name)) . '" accept="image/jpeg,image/png,image/webp,image/gif" data-img-upload="' . $id . '">
        </div>
      </div>' . ($help ? '<small>' . $help . '</small>' : '') . '</div>';
}

/** File input name for an image field (brackets are not allowed in $_FILES keys we read back). */
function upload_name(string $field): string { return 'up__' . preg_replace('/[^a-z0-9]+/i', '__', $field); }

/** Resolve an image field on save: a new upload wins, otherwise the selected existing image. */
function read_image(string $field, ?string $selected): string {
    $f = $_FILES[upload_name($field)] ?? null;
    if ($f && ($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) return store_upload($f);
    return safe_img($selected);
}

/** Text from POST, trimmed, newlines normalised, length-capped. */
function post_str(mixed $v, int $max = 5000): string {
    $v = is_string($v) ? str_replace("\r\n", "\n", trim($v)) : '';
    return mb_substr($v, 0, $max);
}

/** Repeater: rows with stable keys. $fields = [key => [type, label, help]]. */
function f_repeater(string $name, string $title, array $rows, array $fields, string $addLabel, string $help = ''): string {
    $render = function (string $key, array $row) use ($name, $fields): string {
        $html = '<div class="rep-row"><div class="rep-fields">';
        foreach ($fields as $f => [$type, $label, $fhelp]) {
            $fn = $name . '[' . $key . '][' . $f . ']';
            $val = $row[$f] ?? '';
            $html .= match ($type) {
                'area' => f_area($fn, $label, (string)$val, $fhelp, 3),
                'color' => f_color($fn, $label, (string)$val, $fhelp),
                'image' => f_image($fn, $label, (string)$val, $fhelp),
                default => is_array($type) ? f_select($fn, $label, (string)$val, $type, $fhelp) : f_text($fn, $label, (string)$val, $fhelp),
            };
        }
        return $html . '</div><div class="rep-tools"><button type="button" class="icon" data-move="-1" title="Omhoog" aria-label="Omhoog">↑</button><button type="button" class="icon" data-move="1" title="Omlaag" aria-label="Omlaag">↓</button><button type="button" class="icon danger" data-remove title="Verwijderen" aria-label="Verwijderen">✕</button></div></div>';
    };
    $out = '<fieldset class="repeater" data-name="' . h($name) . '"><legend>' . h($title) . '</legend>' . ($help ? '<p class="help">' . $help . '</p>' : '') . '<div class="rep-list">';
    foreach (array_values($rows) as $i => $row) $out .= $render('r' . $i, is_array($row) ? $row : ['tekst' => $row]);
    $out .= '</div><template>' . $render('__KEY__', []) . '</template><button type="button" class="btn-sec" data-add>+ ' . h($addLabel) . '</button></fieldset>';
    return $out;
}

/** Read repeater rows back in form order, dropping fully empty rows. */
function read_repeater(string $name, array $fields): array {
    $rows = [];
    foreach ((array)($_POST[$name] ?? []) as $key => $row) {
        if (!is_array($row)) continue;
        $out = [];
        foreach ($fields as $f => [$type]) {
            $fieldName = $name . '[' . $key . '][' . $f . ']';
            $out[$f] = match ($type) {
                'color' => safe_hex(post_str($row[$f] ?? ''), ''),
                'image' => read_image($fieldName, post_str($row[$f] ?? '')),
                default => post_str($row[$f] ?? ''),
            };
        }
        if (implode('', $out) !== '') $rows[] = $out;
    }
    return $rows;
}

/** Gallery editor for one group: current images with move/remove, plus multi-upload and "add existing". */
function f_gallery(array $items, string $key, string $title, string $help = ''): string {
    $k = preg_replace('/[^a-z0-9-]/', '', $key);
    $html = '<fieldset class="repeater gal-set"><legend>' . h($title) . ' <span class="gal-n">' . count(array_filter(array_map('safe_img', $items))) . '</span></legend>' . ($help ? '<p class="help">' . $help . '</p>' : '') . '<div class="gal-list" data-gallery>';
    foreach ($items as $g) if ($g = safe_img($g)) $html .= '<div class="gal-item"><img src="../' . h($g) . '" alt=""><input type="hidden" name="galerij[' . $k . '][]" value="' . h($g) . '"><div class="rep-tools"><button type="button" class="icon" data-gmove="-1" aria-label="Naar links">←</button><button type="button" class="icon" data-gmove="1" aria-label="Naar rechts">→</button><button type="button" class="icon danger" data-gremove aria-label="Verwijderen uit deze groep">✕</button></div></div>';
    $existing = '<option value="">— kies een bestaand beeld —</option>';
    foreach (all_uploads() as $u) $existing .= '<option value="' . h($u) . '">' . h(basename($u)) . '</option>';
    return $html . '</div><div class="grid2"><div class="field"><label for="gnew-' . $k . '">Beelden toevoegen</label><input type="file" id="gnew-' . $k . '" name="galerij_nieuw_' . $k . '[]" multiple accept="image/jpeg,image/png,image/webp,image/gif"></div>'
        . '<div class="field"><label for="gexist-' . $k . '">Of een bestaand beeld</label><select id="gexist-' . $k . '" name="galerij_bestaand[' . $k . ']">' . $existing . '</select></div></div></fieldset>';
}

/** Read one gallery group back: kept images in their new order, then new uploads, then a picked existing image. */
function read_gallery(string $key): array {
    $k = preg_replace('/[^a-z0-9-]/', '', $key);
    $gal = [];
    foreach ((array)($_POST['galerij'][$k] ?? []) as $g) if ($g = safe_img(post_str($g))) $gal[] = $g;
    foreach (files_list($_FILES['galerij_nieuw_' . $k] ?? null) as $f) if ($u = store_upload($f)) $gal[] = $u;
    if ($add = safe_img(post_str($_POST['galerij_bestaand'][$k] ?? ''))) $gal[] = $add;
    return array_values(array_unique($gal));
}

/** Small list row with action buttons (used for services). */
function list_action(string $page, string $keyName, string $key, string $act, string $label, string $icon, string $cls = '', string $confirm = ''): string {
    return '<form method="post" action="?p=' . h($page) . '"' . ($confirm ? ' data-confirm="' . h($confirm) . '"' : '') . '>' . csrf_field()
        . '<input type="hidden" name="' . h($keyName) . '" value="' . h($key) . '"><input type="hidden" name="actie" value="' . h($act) . '">'
        . '<button type="submit" class="icon ' . h($cls) . '" title="' . h($label) . '" aria-label="' . h($label) . '">' . $icon . '</button></form>';
}
