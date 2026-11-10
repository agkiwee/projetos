<?php
declare(strict_types=1);

/**
 * Formato dos dados de uma assinatura.
 *
 * O mesmo formato vale para o modelo padrão da empresa e para cada assinatura.
 * O desenho (HTML) é sempre gerado por public/assets/js/renderer.js a partir destes dados,
 * então aqui só validamos e limitamos o que é gravado.
 */

const SIG_TEMPLATES = ['horizontal', 'vertical', 'compacta', 'foto', 'destaque'];
const SIG_FIELDS = ['name', 'title', 'department', 'company', 'phone', 'mobile', 'whatsapp', 'email', 'website', 'address'];
const SIG_CONTACT_FIELDS = ['phone', 'mobile', 'whatsapp', 'email', 'website', 'address'];
// Campos que pertencem ao colaborador e nunca são sobrescritos pelo padrão da empresa.
const SIG_PERSONAL_FIELDS = ['name', 'title', 'department', 'phone', 'mobile', 'whatsapp', 'email'];
const SIG_FONTS = ['Arial', 'Helvetica', 'Verdana', 'Tahoma', 'Trebuchet MS', 'Georgia', 'Times New Roman', 'Courier New'];
const SIG_ICON_SHAPES = ['plain', 'circle', 'square', 'outline-circle', 'outline-square'];
const SIG_MAX_SOCIAL = 12;

/** Nomes dos ícones disponíveis na biblioteca. */
function sig_icon_names(): array
{
    static $names = null;
    if ($names === null) {
        $index = json_decode((string) @file_get_contents(KW_ROOT . '/app/resources/icons/index.json'), true);
        $names = is_array($index['icons'] ?? null) ? $index['icons'] : [];
    }
    return $names;
}

function sig_defaults(): array
{
    $fields = [];
    foreach (SIG_FIELDS as $key) {
        $fields[$key] = ['value' => '', 'show' => true];
    }
    return [
        'v' => 1,
        'template' => 'horizontal',
        'fields' => $fields,
        'order' => SIG_CONTACT_FIELDS,
        'logo' => ['url' => '', 'link' => '', 'width' => 150, 'ratio' => 0.0, 'show' => true],
        'photo' => ['url' => '', 'urlCircle' => '', 'size' => 88, 'shape' => 'circle', 'show' => true],
        'banner' => ['url' => '', 'link' => '', 'alt' => '', 'width' => 420, 'ratio' => 0.0, 'show' => true],
        'social' => [],
        'disclaimer' => ['text' => '', 'show' => true],
        'style' => [
            'font' => 'Arial',
            'colorName' => '#2b2f2c',
            'colorTitle' => '#3d7f21',
            'colorText' => '#4a524d',
            'colorLink' => '#4a524d',
            'colorDivider' => '#c9d1cb',
            'colorIcon' => '#3d7f21',
            'sizeName' => 18,
            'sizeTitle' => 14,
            'sizeText' => 13,
            'align' => 'left',
            'spacing' => 8,
            'gap' => 20,
            'dividers' => true,
            'contactStyle' => 'icons',
            'iconShape' => 'outline-square',
            'iconSize' => 28,
            'underline' => false,
        ],
    ];
}

function sig_text($value, int $max, bool $multiline = false): string
{
    if (!is_string($value) && !is_int($value) && !is_float($value)) {
        return '';
    }
    $value = str_replace("\0", '', (string) $value);
    $value = str_replace(["\r\n", "\r"], "\n", $value);
    $value = preg_replace('/[\x00-\x08\x0B-\x1F\x7F]/u', '', $value) ?? '';
    if (!$multiline) {
        // Campo de uma linha: quebras, tabulações e espaços repetidos viram um espaço só.
        $value = preg_replace('/\s+/u', ' ', $value) ?? '';
    } else {
        $value = str_replace("\t", ' ', $value);
    }
    return mb_substr(trim($value), 0, $max);
}

function sig_int($value, int $min, int $max, int $default): int
{
    if (!is_numeric($value)) {
        return $default;
    }
    return max($min, min($max, (int) round((float) $value)));
}

function sig_color($value, string $default): string
{
    return is_string($value) && preg_match('/^#[0-9a-fA-F]{6}$/', $value) ? strtolower($value) : $default;
}

function sig_enum($value, array $allowed, string $default): string
{
    return is_string($value) && in_array($value, $allowed, true) ? $value : $default;
}

/** Aceita imagens hospedadas na plataforma (da própria empresa) ou em endereços http(s). */
function sig_image_url($value, string $companyKey): string
{
    $value = sig_text($value, 600);
    if ($value === '') {
        return '';
    }
    if ($value[0] === '/') {
        $pattern = '#^/uploads/' . preg_quote($companyKey, '#') . '/[a-f0-9]{24}(-c)?\.(png|gif)$#';
        return preg_match($pattern, $value) ? $value : '';
    }
    if (!preg_match('#^https?://#i', $value) || preg_match('/[\s<>"\'`\\\\]/', $value)) {
        return '';
    }
    return filter_var($value, FILTER_VALIDATE_URL) !== false ? $value : '';
}

/** Aceita links http(s), mailto, tel ou um domínio sem esquema (o desenho acrescenta https). */
function sig_link_url($value): string
{
    $value = sig_text($value, 600);
    if ($value === '' || preg_match('/[\s<>"\'`\\\\]/', $value)) {
        return '';
    }
    if (preg_match('#^(https?://|mailto:|tel:)#i', $value)) {
        return $value;
    }
    // Qualquer outro esquema (javascript:, data:, etc.) é recusado.
    if (preg_match('#^[a-z][a-z0-9+.\-]*:#i', $value)) {
        return '';
    }
    return $value;
}

function sig_ratio($value): float
{
    return is_numeric($value) ? round(max(0.0, min(10.0, (float) $value)), 4) : 0.0;
}

/** Valida e normaliza os dados recebidos do editor. Tudo o que não é conhecido é descartado. */
function sig_sanitize($input, string $companyKey): array
{
    $input = is_array($input) ? $input : [];
    $out = sig_defaults();
    $get = static function (array $source, string $key) {
        return $source[$key] ?? null;
    };

    $out['template'] = sig_enum($get($input, 'template'), SIG_TEMPLATES, 'horizontal');

    $fields = is_array($get($input, 'fields')) ? $input['fields'] : [];
    foreach (SIG_FIELDS as $key) {
        $field = is_array($fields[$key] ?? null) ? $fields[$key] : [];
        $out['fields'][$key] = [
            'value' => sig_text($field['value'] ?? '', $key === 'address' ? 300 : 160, $key === 'address'),
            'show' => ($field['show'] ?? true) !== false,
        ];
    }

    $order = [];
    foreach (is_array($get($input, 'order')) ? $input['order'] : [] as $key) {
        if (is_string($key) && in_array($key, SIG_CONTACT_FIELDS, true) && !in_array($key, $order, true)) {
            $order[] = $key;
        }
    }
    foreach (SIG_CONTACT_FIELDS as $key) {
        if (!in_array($key, $order, true)) {
            $order[] = $key;
        }
    }
    $out['order'] = $order;

    $logo = is_array($get($input, 'logo')) ? $input['logo'] : [];
    $out['logo'] = [
        'url' => sig_image_url($logo['url'] ?? '', $companyKey),
        'link' => sig_link_url($logo['link'] ?? ''),
        'width' => sig_int($logo['width'] ?? null, 40, 400, 150),
        'ratio' => sig_ratio($logo['ratio'] ?? 0),
        'show' => ($logo['show'] ?? true) !== false,
    ];

    $photo = is_array($get($input, 'photo')) ? $input['photo'] : [];
    $out['photo'] = [
        'url' => sig_image_url($photo['url'] ?? '', $companyKey),
        'urlCircle' => sig_image_url($photo['urlCircle'] ?? '', $companyKey),
        'size' => sig_int($photo['size'] ?? null, 40, 200, 88),
        'shape' => sig_enum($photo['shape'] ?? null, ['circle', 'rounded', 'square'], 'circle'),
        'show' => ($photo['show'] ?? true) !== false,
    ];

    $banner = is_array($get($input, 'banner')) ? $input['banner'] : [];
    $out['banner'] = [
        'url' => sig_image_url($banner['url'] ?? '', $companyKey),
        'link' => sig_link_url($banner['link'] ?? ''),
        'alt' => sig_text($banner['alt'] ?? '', 120),
        'width' => sig_int($banner['width'] ?? null, 100, 600, 420),
        'ratio' => sig_ratio($banner['ratio'] ?? 0),
        'show' => ($banner['show'] ?? true) !== false,
    ];

    $social = [];
    $types = array_merge(sig_icon_names(), ['custom']);
    foreach (is_array($get($input, 'social')) ? $input['social'] : [] as $item) {
        if (!is_array($item) || count($social) >= SIG_MAX_SOCIAL) {
            continue;
        }
        $type = sig_enum($item['type'] ?? null, $types, '');
        if ($type === '') {
            continue;
        }
        $social[] = [
            'type' => $type,
            'url' => sig_link_url($item['url'] ?? ''),
            'label' => sig_text($item['label'] ?? '', 60),
            'iconUrl' => $type === 'custom' ? sig_image_url($item['iconUrl'] ?? '', $companyKey) : '',
            'show' => ($item['show'] ?? true) !== false,
        ];
    }
    $out['social'] = $social;

    $disclaimer = is_array($get($input, 'disclaimer')) ? $input['disclaimer'] : [];
    $out['disclaimer'] = [
        'text' => sig_text($disclaimer['text'] ?? '', 1500, true),
        'show' => ($disclaimer['show'] ?? true) !== false,
    ];

    $style = is_array($get($input, 'style')) ? $input['style'] : [];
    $base = $out['style'];
    $out['style'] = [
        'font' => sig_enum($style['font'] ?? null, SIG_FONTS, $base['font']),
        'colorName' => sig_color($style['colorName'] ?? null, $base['colorName']),
        'colorTitle' => sig_color($style['colorTitle'] ?? null, $base['colorTitle']),
        'colorText' => sig_color($style['colorText'] ?? null, $base['colorText']),
        'colorLink' => sig_color($style['colorLink'] ?? null, $base['colorLink']),
        'colorDivider' => sig_color($style['colorDivider'] ?? null, $base['colorDivider']),
        'colorIcon' => sig_color($style['colorIcon'] ?? null, $base['colorIcon']),
        'sizeName' => sig_int($style['sizeName'] ?? null, 12, 28, $base['sizeName']),
        'sizeTitle' => sig_int($style['sizeTitle'] ?? null, 10, 22, $base['sizeTitle']),
        'sizeText' => sig_int($style['sizeText'] ?? null, 10, 18, $base['sizeText']),
        'align' => sig_enum($style['align'] ?? null, ['left', 'center', 'right'], $base['align']),
        'spacing' => sig_int($style['spacing'] ?? null, 0, 24, $base['spacing']),
        'gap' => sig_int($style['gap'] ?? null, 8, 48, $base['gap']),
        'dividers' => ($style['dividers'] ?? true) !== false,
        'contactStyle' => sig_enum($style['contactStyle'] ?? null, ['icons', 'labels', 'none'], $base['contactStyle']),
        'iconShape' => sig_enum($style['iconShape'] ?? null, SIG_ICON_SHAPES, $base['iconShape']),
        'iconSize' => sig_int($style['iconSize'] ?? null, 16, 40, $base['iconSize']),
        'underline' => ($style['underline'] ?? false) === true,
    ];

    return $out;
}

/** Modelo padrão da empresa. Antes da primeira configuração devolve um padrão neutro. */
function company_default(array $company): array
{
    $stored = json_decode((string) ($company['default_data'] ?? ''), true);
    if (is_array($stored)) {
        return sig_sanitize($stored, (string) $company['public_key']);
    }
    $data = sig_defaults();
    $data['fields']['company']['value'] = (string) $company['name'];
    return $data;
}

/** Ponto de partida de uma nova assinatura: o padrão da empresa sem os dados pessoais. */
function sig_from_default(array $default, ?string $template = null): array
{
    $data = $default;
    foreach (['name', 'title', 'department', 'mobile', 'whatsapp', 'email'] as $key) {
        $data['fields'][$key]['value'] = '';
    }
    $data['photo']['url'] = '';
    $data['photo']['urlCircle'] = '';
    if ($template !== null && in_array($template, SIG_TEMPLATES, true)) {
        $data['template'] = $template;
    }
    return $data;
}

/**
 * Aplica a identidade do padrão da empresa a uma assinatura existente.
 * Dados pessoais e foto do colaborador são mantidos; modelo, cores, fontes, logotipo,
 * redes, banner, aviso e dados institucionais passam a ser os do padrão.
 */
function sig_apply_default(array $signature, array $default): array
{
    $merged = $default;
    foreach (SIG_PERSONAL_FIELDS as $key) {
        $own = $signature['fields'][$key]['value'] ?? '';
        if ($own !== '') {
            $merged['fields'][$key]['value'] = $own;
        }
    }
    $merged['photo']['url'] = $signature['photo']['url'] ?? '';
    $merged['photo']['urlCircle'] = $signature['photo']['urlCircle'] ?? '';
    return $merged;
}

function sig_search_text(array $data): string
{
    $parts = [];
    foreach (['name', 'title', 'department', 'email'] as $key) {
        $parts[] = $data['fields'][$key]['value'] ?? '';
    }
    return mb_substr(mb_strtolower(trim(implode(' ', $parts))), 0, 700);
}

/** Busca uma assinatura garantindo que ela pertence à empresa informada. */
function signature_find(int $id, int $companyId): ?array
{
    return db_one('SELECT * FROM signatures WHERE id = ? AND company_id = ?', [$id, $companyId]);
}

function signature_payload(array $row, array $company): array
{
    $data = sig_sanitize(json_decode((string) $row['data'], true), (string) $company['public_key']);
    return [
        'id' => (int) $row['id'],
        'name' => $row['name'],
        'data' => $data,
        'shareUrl' => absolute_url('/i/' . $row['share_token']),
        'updatedAt' => format_date($row['updated_at']),
    ];
}
