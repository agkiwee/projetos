<?php
declare(strict_types=1);

/* API usada pelo editor. Responde em JSON e segue as mesmas regras de acesso das páginas. */

/**
 * Mantém a sessão viva enquanto o editor está aberto e entrega o token de segurança atual.
 * Também serve para o editor se recuperar depois de um novo login feito em outra aba.
 */
function api_session(): void
{
    $company = require_company();
    json_out(['ok' => true, 'csrf' => csrf_token(), 'companyKey' => $company['public_key']]);
}

function api_signature_name(array $data): string
{
    $name = trim((string) ($data['fields']['name']['value'] ?? ''));
    if ($name === '') {
        json_out(['ok' => false, 'error' => 'Preencha o nome do colaborador antes de salvar.', 'field' => 'name'], 422);
    }
    return $name;
}

function api_signature_create(): void
{
    $company = require_company();
    $user = require_login();
    $body = json_body();
    $data = sig_sanitize($body['data'] ?? null, (string) $company['public_key']);
    $name = api_signature_name($data);
    $token = random_token(20);
    icons_warm($data);
    $id = db_insert('signatures', [
        'company_id' => (int) $company['id'],
        'name' => $name,
        'search_text' => sig_search_text($data),
        'data' => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'share_token' => $token,
        'created_by' => (int) $user['id'],
        'created_at' => now_utc(),
        'updated_at' => now_utc(),
    ]);
    json_out([
        'ok' => true, 'id' => $id, 'name' => $name, 'data' => $data,
        'shareUrl' => absolute_url('/i/' . $token), 'editUrl' => url('/assinaturas/' . $id),
    ], 201);
}

function api_signature_get(string $id): void
{
    $company = require_company();
    $row = signature_find((int) $id, (int) $company['id']);
    if ($row === null) {
        not_found();
    }
    json_out(['ok' => true] + signature_payload($row, $company));
}

function api_signature_update(string $id): void
{
    $company = require_company();
    $row = signature_find((int) $id, (int) $company['id']);
    if ($row === null) {
        not_found();
    }
    $body = json_body();
    $data = sig_sanitize($body['data'] ?? null, (string) $company['public_key']);
    $name = api_signature_name($data);
    icons_warm($data);
    db_update('signatures', [
        'name' => $name,
        'search_text' => sig_search_text($data),
        'data' => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'updated_at' => now_utc(),
    ], 'id = ? AND company_id = ?', [(int) $row['id'], (int) $company['id']]);
    json_out(['ok' => true, 'id' => (int) $row['id'], 'name' => $name, 'data' => $data]);
}

function api_default_save(): void
{
    $company = require_company();
    $body = json_body();
    $data = sig_sanitize($body['data'] ?? null, (string) $company['public_key']);
    icons_warm($data);
    db_update('companies', [
        'default_data' => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'default_updated_at' => now_utc(),
    ], 'id = ?', [(int) $company['id']]);
    json_out(['ok' => true, 'data' => $data]);
}

/** Leva a identidade do modelo padrão para todas as assinaturas da empresa, preservando os dados pessoais. */
function api_default_apply(): void
{
    $company = require_company();
    if ($company['default_data'] === null) {
        json_out(['ok' => false, 'error' => 'Salve o modelo padrão antes de aplicar às assinaturas.'], 422);
    }
    $default = company_default($company);
    $rows = db_all('SELECT * FROM signatures WHERE company_id = ?', [(int) $company['id']]);
    $pdo = db();
    $pdo->beginTransaction();
    try {
        foreach ($rows as $row) {
            $current = sig_sanitize(json_decode((string) $row['data'], true), (string) $company['public_key']);
            $merged = sig_apply_default($current, $default);
            db_update('signatures', [
                'search_text' => sig_search_text($merged),
                'data' => json_encode($merged, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'updated_at' => now_utc(),
            ], 'id = ? AND company_id = ?', [(int) $row['id'], (int) $company['id']]);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
    json_out(['ok' => true, 'updated' => count($rows)]);
}

function api_assets_list(): void
{
    $company = require_company();
    $rows = db_all('SELECT * FROM assets WHERE company_id = ? ORDER BY updated_at DESC, id DESC', [(int) $company['id']]);
    json_out(['ok' => true, 'assets' => array_map('asset_payload', $rows)]);
}

function api_asset_upload(): void
{
    $company = require_company();
    $user = require_login();
    $kind = input('tipo', 20);
    $file = $_FILES['arquivo'] ?? null;

    $limit = min(IMG_MAX_UPLOAD_BYTES, upload_limit_bytes());
    $tooBig = 'O arquivo passa do limite de ' . format_megabytes($limit) . '. Reduza a imagem e envie de novo.';
    if (!is_array($file) || !isset($file['error']) || is_array($file['error'])) {
        // Quando o envio passa do limite do PHP, o arquivo nem chega até aqui.
        $sentBytes = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
        json_out(['ok' => false, 'error' => $sentBytes > $limit ? $tooBig : 'Nenhum arquivo foi recebido.'], 422);
    }
    if (in_array($file['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true) || $file['size'] > $limit) {
        json_out(['ok' => false, 'error' => $tooBig], 422);
    }
    if ($file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
        json_out(['ok' => false, 'error' => 'O envio do arquivo falhou. Tente de novo.'], 422);
    }

    $replace = null;
    $replaceId = (int) input('substituir', 12);
    if ($replaceId > 0) {
        $replace = db_one('SELECT * FROM assets WHERE id = ? AND company_id = ?', [$replaceId, (int) $company['id']]);
        if ($replace === null) {
            not_found();
        }
        $kind = $replace['kind'];
    }
    try {
        $asset = asset_store($company, $kind, (string) file_get_contents($file['tmp_name']), (string) $file['name'], (int) $user['id'], $replace);
    } catch (ImageProblem $problem) {
        json_out(['ok' => false, 'error' => $problem->getMessage()], 422);
    }
    json_out(['ok' => true, 'asset' => $asset], 201);
}

function api_asset_delete(string $id): void
{
    $company = require_company();
    $asset = db_one('SELECT * FROM assets WHERE id = ? AND company_id = ?', [(int) $id, (int) $company['id']]);
    if ($asset === null) {
        not_found();
    }
    foreach ([$asset['path'], $asset['path_circle']] as $path) {
        // O caminho vem do banco e sempre fica dentro da pasta da própria empresa.
        if ($path && strpos($path, '/uploads/' . $company['public_key'] . '/') === 0) {
            @unlink(KW_ROOT . '/public' . $path);
        }
    }
    db_run('DELETE FROM assets WHERE id = ? AND company_id = ?', [(int) $asset['id'], (int) $company['id']]);
    json_out(['ok' => true]);
}

/** Confere um endereço de imagem e, se pedido, guarda uma cópia na plataforma. */
function api_image_url(): void
{
    $company = require_company();
    $user = require_login();
    $body = json_body(8192);
    $url = is_string($body['url'] ?? null) ? $body['url'] : '';
    $kind = is_string($body['tipo'] ?? null) && in_array($body['tipo'], IMG_KINDS, true) ? $body['tipo'] : 'logo';
    $copy = ($body['copiar'] ?? false) === true;

    try {
        $found = image_url_inspect($url);
        if ($copy) {
            $name = basename((string) parse_url($found['url'], PHP_URL_PATH)) ?: 'imagem';
            $asset = asset_store($company, $kind, $found['bytes'], $name, (int) $user['id']);
            json_out(['ok' => true, 'hosted' => true, 'image' => $asset, 'source' => $found['source']]);
        }
    } catch (ImageProblem $problem) {
        json_out(['ok' => false, 'error' => $problem->getMessage()], 422);
    }
    json_out([
        'ok' => true,
        'hosted' => false,
        'source' => $found['source'],
        'warning' => $found['warning'],
        'image' => [
            'url' => $found['url'], 'urlCircle' => '', 'width' => $found['width'],
            'height' => $found['height'], 'ratio' => $found['ratio'],
        ],
    ]);
}
