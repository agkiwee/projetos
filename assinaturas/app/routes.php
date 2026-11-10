<?php
declare(strict_types=1);

/**
 * Tabela de rotas. Cada linha: método, expressão do caminho, função que atende.
 * As funções ficam em app/controllers.
 */
function kw_routes(): array
{
    return [
        ['GET', '#^/$#', 'page_home'],
        ['GET', '#^/entrar$#', 'page_login'],
        ['POST', '#^/entrar$#', 'action_login'],
        ['POST', '#^/sair$#', 'action_logout'],
        ['GET', '#^/convite/([a-f0-9]{64})$#', 'page_invite'],
        ['POST', '#^/convite/([a-f0-9]{64})$#', 'action_invite'],
        ['GET', '#^/esqueci-senha$#', 'page_forgot'],
        ['POST', '#^/esqueci-senha$#', 'action_forgot'],
        ['GET', '#^/redefinir-senha/([a-f0-9]{64})$#', 'page_reset'],
        ['POST', '#^/redefinir-senha/([a-f0-9]{64})$#', 'action_reset'],
        ['GET', '#^/instalar$#', 'page_setup'],
        ['POST', '#^/instalar$#', 'action_setup'],
        ['GET', '#^/conta$#', 'page_account'],
        ['POST', '#^/conta$#', 'action_account'],
        ['GET', '#^/i/([a-f0-9]{40})$#', 'page_share'],

        ['GET', '#^/admin$#', 'admin_companies'],
        ['POST', '#^/admin/empresas$#', 'admin_company_create'],
        ['GET', '#^/admin/empresas/(\d+)$#', 'admin_company'],
        ['POST', '#^/admin/empresas/(\d+)/editar$#', 'admin_company_rename'],
        ['POST', '#^/admin/empresas/(\d+)/status$#', 'admin_company_status'],
        ['POST', '#^/admin/empresas/(\d+)/convites$#', 'admin_company_invite'],
        ['POST', '#^/admin/empresas/(\d+)/abrir$#', 'admin_company_open'],
        ['POST', '#^/admin/sair-da-empresa$#', 'admin_company_leave'],
        ['POST', '#^/admin/convites/(\d+)/reenviar$#', 'admin_invite_resend'],
        ['POST', '#^/admin/convites/(\d+)/cancelar$#', 'admin_invite_cancel'],
        ['POST', '#^/admin/usuarios/(\d+)/status$#', 'admin_user_status'],
        ['POST', '#^/admin/usuarios/(\d+)/remover$#', 'admin_user_remove'],
        ['GET', '#^/admin/equipe$#', 'admin_team'],
        ['POST', '#^/admin/equipe/convites$#', 'admin_team_invite'],

        ['GET', '#^/assinaturas$#', 'client_list'],
        ['GET', '#^/assinaturas/nova$#', 'client_new'],
        ['GET', '#^/assinaturas/(\d+)$#', 'client_edit'],
        ['POST', '#^/assinaturas/(\d+)/duplicar$#', 'client_duplicate'],
        ['POST', '#^/assinaturas/(\d+)/excluir$#', 'client_delete'],
        ['GET', '#^/padrao$#', 'client_default'],
        ['GET', '#^/como-instalar$#', 'client_install_help'],

        ['POST', '#^/api/assinaturas$#', 'api_signature_create'],
        ['GET', '#^/api/assinaturas/(\d+)$#', 'api_signature_get'],
        ['POST', '#^/api/assinaturas/(\d+)$#', 'api_signature_update'],
        ['POST', '#^/api/padrao$#', 'api_default_save'],
        ['POST', '#^/api/padrao/aplicar$#', 'api_default_apply'],
        ['GET', '#^/api/arquivos$#', 'api_assets_list'],
        ['POST', '#^/api/arquivos$#', 'api_asset_upload'],
        ['POST', '#^/api/arquivos/(\d+)/excluir$#', 'api_asset_delete'],
        ['POST', '#^/api/imagem-url$#', 'api_image_url'],
    ];
}

function kw_dispatch(): void
{
    $path = request_path();
    $method = request_method();
    if ($method === 'HEAD') {
        $method = 'GET';
    }

    // Ícones são públicos (aparecem nos e-mails) e não usam sessão.
    if ($method === 'GET' && preg_match('#^/icones/([a-z\-]+)/([0-9a-fA-F]{6})/([a-z0-9]+)\.png$#', $path, $match)) {
        icon_serve($match[1], $match[2], $match[3]);
    }

    send_security_headers();
    session_boot();
    foreach (['auth', 'admin', 'client', 'api'] as $controller) {
        require_once KW_ROOT . '/app/controllers/' . $controller . '.php';
    }

    // Antes da instalação, qualquer endereço leva à tela de configuração inicial.
    if (!schema_installed() && $path !== '/instalar') {
        redirect('/instalar');
    }

    foreach (kw_routes() as [$routeMethod, $pattern, $handler]) {
        if (!preg_match($pattern, $path, $match)) {
            continue;
        }
        if ($routeMethod !== $method) {
            continue;
        }
        if ($method === 'POST') {
            csrf_check();
        }
        array_shift($match);
        $handler(...$match);
        return;
    }
    not_found();
}
