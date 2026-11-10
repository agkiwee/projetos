<?php
declare(strict_types=1);

/*
 * Área do cliente. A empresa vem sempre de require_company(), nunca da requisição,
 * e toda consulta de assinatura leva o id da empresa junto.
 */

function client_list(): void
{
    $company = require_company();
    $search = isset($_GET['q']) && is_string($_GET['q']) ? trim(mb_substr($_GET['q'], 0, 100)) : '';
    $params = [(int) $company['id']];
    $where = '';
    if ($search !== '') {
        $where = " AND search_text LIKE ? ESCAPE '!'";
        $params[] = db_like(mb_strtolower($search));
    }
    $rows = db_all("SELECT * FROM signatures WHERE company_id = ?$where ORDER BY name, id", $params);
    $signatures = [];
    foreach ($rows as $row) {
        $data = json_decode((string) $row['data'], true);
        $signatures[] = [
            'id' => (int) $row['id'],
            'name' => $row['name'],
            'title' => (string) ($data['fields']['title']['value'] ?? ''),
            'department' => (string) ($data['fields']['department']['value'] ?? ''),
            'email' => (string) ($data['fields']['email']['value'] ?? ''),
            'template' => (string) ($data['template'] ?? ''),
            'updated' => format_date($row['updated_at']),
            'shareUrl' => absolute_url('/i/' . $row['share_token']),
        ];
    }
    $total = (int) db_value('SELECT COUNT(*) FROM signatures WHERE company_id = ?', [(int) $company['id']]);
    render('cliente/lista', [
        'title' => 'Assinaturas', 'company' => $company, 'signatures' => $signatures,
        'search' => $search, 'total' => $total, 'hasDefault' => $company['default_data'] !== null,
        'boot' => ['baseUrl' => app_url(), 'csrf' => csrf_token(), 'base' => base_path()],
    ]);
}

function editor_boot(array $company, string $mode, array $data, ?array $signature): array
{
    return [
        'mode' => $mode, // signature ou default
        'base' => base_path(),
        'baseUrl' => app_url(),
        'csrf' => csrf_token(),
        'company' => ['name' => $company['name']],
        'data' => $data,
        'default' => company_default($company),
        'signature' => $signature,
        'icons' => sig_icon_names(),
        'fonts' => SIG_FONTS,
    ];
}

/** Escolha do ponto de partida e, com ?modelo=, o editor de uma assinatura ainda não gravada. */
function client_new(): void
{
    $company = require_company();
    $default = company_default($company);
    $choice = isset($_GET['modelo']) && is_string($_GET['modelo']) ? $_GET['modelo'] : '';

    if ($choice === '') {
        render('cliente/nova', [
            'title' => 'Nova assinatura', 'company' => $company,
            'hasDefault' => $company['default_data'] !== null,
            'boot' => ['baseUrl' => app_url(), 'data' => sig_from_default($default), 'base' => base_path()],
        ]);
        return;
    }
    $template = in_array($choice, SIG_TEMPLATES, true) ? $choice : null; // "padrao" mantém o modelo da empresa
    render('cliente/editor', [
        'title' => 'Nova assinatura', 'shell' => 'editor', 'company' => $company, 'mode' => 'signature',
        'boot' => editor_boot($company, 'signature', sig_from_default($default, $template), null),
    ]);
}

function client_edit(string $id): void
{
    $company = require_company();
    $row = signature_find((int) $id, (int) $company['id']);
    if ($row === null) {
        not_found();
    }
    $payload = signature_payload($row, $company);
    render('cliente/editor', [
        'title' => $row['name'], 'shell' => 'editor', 'company' => $company, 'mode' => 'signature',
        'boot' => editor_boot($company, 'signature', $payload['data'], [
            'id' => $payload['id'], 'name' => $payload['name'], 'shareUrl' => $payload['shareUrl'],
        ]),
    ]);
}

function client_default(): void
{
    $company = require_company();
    render('cliente/editor', [
        'title' => 'Modelo padrão', 'shell' => 'editor', 'company' => $company, 'mode' => 'default',
        'boot' => editor_boot($company, 'default', company_default($company), null) + [
            'signatureCount' => (int) db_value('SELECT COUNT(*) FROM signatures WHERE company_id = ?', [(int) $company['id']]),
        ],
    ]);
}

function client_duplicate(string $id): void
{
    $company = require_company();
    $user = require_login();
    $row = signature_find((int) $id, (int) $company['id']);
    if ($row === null) {
        not_found();
    }
    $data = sig_sanitize(json_decode((string) $row['data'], true), (string) $company['public_key']);
    $name = mb_substr($row['name'] . ' (cópia)', 0, 160);
    $data['fields']['name']['value'] = $name;
    $newId = db_insert('signatures', [
        'company_id' => (int) $company['id'],
        'name' => $name,
        'search_text' => sig_search_text($data),
        'data' => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'share_token' => random_token(20),
        'created_by' => (int) $user['id'],
        'created_at' => now_utc(),
        'updated_at' => now_utc(),
    ]);
    flash('success', 'Assinatura duplicada. Ajuste os dados do novo colaborador.');
    redirect('/assinaturas/' . $newId);
}

function client_delete(string $id): void
{
    $company = require_company();
    $row = signature_find((int) $id, (int) $company['id']);
    if ($row === null) {
        not_found();
    }
    db_run('DELETE FROM signatures WHERE id = ? AND company_id = ?', [(int) $row['id'], (int) $company['id']]);
    flash('success', 'Assinatura de ' . $row['name'] . ' excluída.');
    redirect('/assinaturas');
}

function client_install_help(): void
{
    require_login();
    render('cliente/como-instalar', ['title' => 'Como instalar']);
}
