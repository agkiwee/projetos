<?php
declare(strict_types=1);

/** Lê um valor da configuração usando notação com ponto, por exemplo config('db.driver'). */
function config(string $key, $default = null)
{
    $value = $GLOBALS['kw_config'] ?? [];
    foreach (explode('.', $key) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return $default;
        }
        $value = $value[$part];
    }
    return $value;
}

/** Escapa texto para HTML. */
function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function is_https(): bool
{
    if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
        return true;
    }
    return strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
}

/** Endereço público da plataforma, sem barra final. */
function app_url(): string
{
    static $url = null;
    if ($url !== null) {
        return $url;
    }
    $configured = rtrim(trim((string) config('app_url', '')), '/');
    if ($configured !== '') {
        return $url = $configured;
    }
    // Sem endereço configurado: usa o da requisição (apenas para desenvolvimento).
    $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
    if (!preg_match('/^[a-z0-9.\-]+(:\d{1,5})?$/i', $host)) {
        $host = 'localhost';
    }
    return $url = (is_https() ? 'https' : 'http') . '://' . $host;
}

/** Caminho base quando a plataforma fica em uma subpasta do domínio. */
function base_path(): string
{
    $path = (string) parse_url(app_url(), PHP_URL_PATH);
    return rtrim($path, '/');
}

/** Monta um caminho interno, já com o caminho base. */
function url(string $path = '/'): string
{
    return base_path() . '/' . ltrim($path, '/');
}

/** Monta um endereço completo, usado em e-mails e links de instalação. */
function absolute_url(string $path = '/'): string
{
    return app_url() . '/' . ltrim($path, '/');
}

function redirect(string $path): void
{
    header('Location: ' . url($path), true, 303);
    exit;
}

function json_out(array $data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/** JSON seguro para colocar dentro de uma tag <script type="application/json">. */
function json_for_html($data): string
{
    return json_encode($data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function now_utc(): string
{
    return gmdate('Y-m-d H:i:s');
}

/** Formata uma data gravada em UTC para o fuso configurado. */
function format_date(?string $utc, string $format = 'd/m/Y H:i'): string
{
    if (!$utc) {
        return '';
    }
    try {
        $date = new DateTime($utc, new DateTimeZone('UTC'));
        $date->setTimezone(new DateTimeZone((string) config('timezone', 'America/Sao_Paulo')));
        return $date->format($format);
    } catch (Throwable $e) {
        return '';
    }
}

function random_token(int $bytes = 32): string
{
    return bin2hex(random_bytes($bytes));
}

function client_ip(): string
{
    return (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
}

function request_method(): string
{
    return strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
}

function wants_json(): bool
{
    return strpos(request_path(), '/api/') === 0;
}

/** Caminho da requisição sem o caminho base e sem query string. */
function request_path(): string
{
    static $path = null;
    if ($path !== null) {
        return $path;
    }
    $raw = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
    $raw = rawurldecode($raw);
    $base = base_path();
    if ($base !== '' && strpos($raw, $base) === 0) {
        $raw = substr($raw, strlen($base));
    }
    $raw = '/' . trim($raw, '/');
    return $path = $raw;
}

/** Texto de um campo de formulário, aparado e limitado. */
function input(string $key, int $max = 500): string
{
    $value = $_POST[$key] ?? '';
    if (!is_string($value)) {
        return '';
    }
    $value = trim(str_replace("\0", '', $value));
    return mb_substr($value, 0, $max);
}

/** Corpo JSON de uma requisição de API. */
function json_body(int $maxBytes = 262144): array
{
    $raw = file_get_contents('php://input', false, null, 0, $maxBytes + 1);
    if ($raw === false || strlen($raw) > $maxBytes) {
        json_out(['ok' => false, 'error' => 'Os dados enviados são grandes demais.'], 413);
    }
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        json_out(['ok' => false, 'error' => 'Não foi possível ler os dados enviados.'], 400);
    }
    return $data;
}

function flash(string $type, string $message, array $extra = []): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message] + $extra;
}

function take_flashes(): array
{
    $items = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $items;
}

function valid_email(string $email): bool
{
    return strlen($email) <= 190 && filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/** Resposta de página não encontrada. Usada também quando o recurso é de outra empresa. */
function not_found(): void
{
    if (wants_json()) {
        json_out(['ok' => false, 'error' => 'Não encontrado.'], 404);
    }
    http_response_code(404);
    render('erro', [
        'title' => 'Página não encontrada',
        'code' => 404,
        'message' => 'O endereço não existe ou você não tem acesso a ele.',
    ]);
    exit;
}

function forbidden(string $message = 'Você não tem permissão para acessar esta área.'): void
{
    if (wants_json()) {
        json_out(['ok' => false, 'error' => $message], 403);
    }
    http_response_code(403);
    render('erro', ['title' => 'Acesso negado', 'code' => 403, 'message' => $message]);
    exit;
}

/** Renderiza uma view dentro do layout padrão. */
function render(string $view, array $data = [], string $layout = 'layout'): void
{
    $data['view'] = $view;
    extract($data, EXTR_SKIP);
    ob_start();
    require KW_ROOT . '/app/views/' . $view . '.php';
    $content = ob_get_clean();
    if ($layout === '') {
        echo $content;
        return;
    }
    require KW_ROOT . '/app/views/' . $layout . '.php';
}

/** Endereço de um arquivo estático com carimbo de versão para evitar cache antigo. */
function asset(string $path): string
{
    $file = KW_ROOT . '/public/assets/' . ltrim($path, '/');
    $version = is_file($file) ? (string) filemtime($file) : '0';
    return url('/assets/' . ltrim($path, '/')) . '?v=' . $version;
}

/** Converte valores do php.ini como "8M" em bytes. */
function ini_bytes(string $value): int
{
    $value = trim($value);
    $number = (float) $value;
    switch (strtolower(substr($value, -1))) {
        case 'g':
            $number *= 1024;
            // sem break: continua multiplicando
        case 'm':
            $number *= 1024;
            // sem break
        case 'k':
            $number *= 1024;
    }
    return (int) $number;
}

/** Maior arquivo que este servidor aceita receber, segundo o php.ini. */
function upload_limit_bytes(): int
{
    $limits = array_filter([ini_bytes((string) ini_get('upload_max_filesize')), ini_bytes((string) ini_get('post_max_size'))]);
    return $limits ? (int) min($limits) : PHP_INT_MAX;
}

function format_megabytes(int $bytes): string
{
    return rtrim(rtrim(number_format($bytes / 1048576, 1, ',', '.'), '0'), ',') . ' MB';
}
