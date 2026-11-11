<?php
declare(strict_types=1);

/**
 * Inicialização da plataforma: configuração, bibliotecas, cabeçalhos de segurança e sessão.
 */

define('KW_ROOT', dirname(__DIR__));
define('KW_VERSION', '1.0.1');
// Pasta servida pela web. O public/index.php define o valor real; aqui fica o padrão para scripts e testes.
if (!defined('KW_PUBLIC')) {
    define('KW_PUBLIC', KW_ROOT . '/public');
}

mb_internal_encoding('UTF-8');
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

$kwConfigPath = getenv('KW_CONFIG') ?: KW_ROOT . '/config.php';
if (!is_file($kwConfigPath)) {
    http_response_code(503);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html lang="pt-BR"><meta charset="utf-8"><title>Configuração pendente</title>'
        . '<body style="font-family:system-ui,sans-serif;max-width:560px;margin:80px auto;padding:0 20px;line-height:1.5">'
        . '<h1>Configuração pendente</h1><p>Copie o arquivo <code>config.sample.php</code> para <code>config.php</code>, '
        . 'preencha o endereço do site e os dados do banco e recarregue esta página.</p></body></html>';
    exit;
}
/*
 * O config.php é editado à mão no servidor. Uma linha em branco antes da abertura do PHP ou depois do
 * fechamento sairia na frente de toda resposta e corromperia as imagens geradas.
 * Por isso tudo o que ele imprimir é descartado.
 */
ob_start();
$GLOBALS['kw_config'] = require $kwConfigPath;
ob_end_clean();
if (!is_array($GLOBALS['kw_config'])) {
    http_response_code(503);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html lang="pt-BR"><meta charset="utf-8"><title>Configuração inválida</title>'
        . '<body style="font-family:system-ui,sans-serif;max-width:560px;margin:80px auto;padding:0 20px;line-height:1.5">'
        . '<h1>Configuração inválida</h1><p>O arquivo <code>config.php</code> precisa começar com <code>&lt;?php</code> '
        . 'e terminar devolvendo a lista de opções, como no <code>config.sample.php</code>.</p></body></html>';
    exit;
}

require KW_ROOT . '/app/lib/helpers.php';
require KW_ROOT . '/app/lib/db.php';
require KW_ROOT . '/app/lib/auth.php';
require KW_ROOT . '/app/lib/mailer.php';
require KW_ROOT . '/app/lib/signatures.php';
require KW_ROOT . '/app/lib/images.php';
require KW_ROOT . '/app/lib/icons.php';
require KW_ROOT . '/app/routes.php';

date_default_timezone_set('UTC');

set_exception_handler(static function (Throwable $error): void {
    error_log('KIWEE Assinaturas: ' . get_class($error) . ': ' . $error->getMessage() . ' em ' . $error->getFile() . ':' . $error->getLine());
    if (!headers_sent()) {
        http_response_code(500);
    }
    $detail = config('debug', false) ? $error->getMessage() . ' (' . basename($error->getFile()) . ':' . $error->getLine() . ')' : '';
    if (function_exists('wants_json') && wants_json()) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => 'Ocorreu um erro no servidor. Tente novamente.', 'detail' => $detail], JSON_UNESCAPED_UNICODE);
        return;
    }
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html lang="pt-BR"><meta charset="utf-8"><title>Erro</title>'
        . '<body style="font-family:system-ui,sans-serif;max-width:560px;margin:80px auto;padding:0 20px;line-height:1.5">'
        . '<h1>Algo deu errado</h1><p>Ocorreu um erro no servidor. Tente novamente em instantes.</p>'
        . ($detail !== '' ? '<pre style="white-space:pre-wrap;background:#f4f4f4;padding:12px">' . htmlspecialchars($detail, ENT_QUOTES, 'UTF-8') . '</pre>' : '')
        . '</body></html>';
});

/** Cabeçalhos de segurança das páginas geradas pelo PHP. */
function send_security_headers(): void
{
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    // Imagens http só são aceitas no modo de teste local; em produção as assinaturas usam apenas https.
    $images = config('dev_allow_insecure_url_import', false) ? "'self' https: http: data: blob:" : "'self' https: data: blob:";
    header(
        "Content-Security-Policy: default-src 'self'; img-src $images; style-src 'self' 'unsafe-inline'; "
        . "script-src 'self'; frame-src 'self'; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'"
    );
    if (is_https()) {
        header('Strict-Transport-Security: max-age=15552000');
    }
}
