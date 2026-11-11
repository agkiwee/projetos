<?php
declare(strict_types=1);

/* Painel administrativo da KIWEE. Todas as funções exigem o papel de administrador. */

function admin_companies(): void
{
    require_admin();
    $search = isset($_GET['q']) && is_string($_GET['q']) ? trim(mb_substr($_GET['q'], 0, 100)) : '';
    $companies = db_all(
        "SELECT c.*,
                (SELECT COUNT(*) FROM users u WHERE u.company_id = c.id) AS user_count,
                (SELECT COUNT(*) FROM signatures s WHERE s.company_id = c.id) AS signature_count,
                (SELECT COUNT(*) FROM invites i WHERE i.company_id = c.id AND i.accepted_at IS NULL AND i.cancelled_at IS NULL AND i.expires_at > ?) AS invite_count
           FROM companies c ORDER BY c.name",
        [now_utc()]
    );
    if ($search !== '') {
        // Filtro feito aqui para tratar acentos e maiúsculas do mesmo jeito em MySQL e SQLite.
        $companies = array_values(array_filter($companies, static function (array $company) use ($search): bool {
            return mb_stripos($company['name'], $search) !== false;
        }));
    }
    render('admin/empresas', ['title' => 'Empresas', 'companies' => $companies, 'search' => $search]);
}

function admin_company_create(): void
{
    $admin = require_admin();
    $name = input('empresa', 160);
    $contactName = input('nome', 160);
    $contactEmail = mb_strtolower(input('email', 190));

    if ($name === '') {
        flash('error', 'Informe o nome da empresa.');
        redirect('/admin');
    }
    if ($contactEmail !== '' && ($contactName === '' || !valid_email($contactEmail))) {
        flash('error', 'Para convidar o primeiro usuário, informe nome e um e-mail válido.');
        redirect('/admin');
    }
    if ($contactEmail !== '' && db_value('SELECT COUNT(*) FROM users WHERE email = ?', [$contactEmail]) > 0) {
        flash('error', 'Já existe um usuário com o e-mail ' . $contactEmail . '. A empresa não foi criada.');
        redirect('/admin');
    }

    $companyId = db_insert('companies', [
        'name' => $name,
        'public_key' => random_token(10),
        'status' => 'active',
        'created_at' => now_utc(),
    ]);
    flash('success', 'Empresa ' . $name . ' cadastrada.');
    if ($contactEmail !== '') {
        admin_send_invite($companyId, $name, $contactName, $contactEmail, 'client', (int) $admin['id']);
    }
    redirect('/admin/empresas/' . $companyId);
}

/** Cria o convite, tenta enviar por e-mail e mostra o link ao administrador. */
function admin_send_invite(?int $companyId, ?string $companyName, string $name, string $email, string $role, int $adminId): void
{
    $link = invite_create($companyId, $name, $email, $role, $adminId);
    $sent = invite_send_email($name, $email, $link, $companyName);
    flash(
        $sent ? 'success' : 'warning',
        $sent
            ? 'Convite enviado para ' . $email . '. Se preferir, copie o link e envie você mesmo.'
            : 'O convite foi criado, mas o e-mail para ' . $email . ' não pôde ser enviado. Copie o link e envie você mesmo.',
        ['copy' => $link]
    );
}

function admin_company_or_404(string $id): array
{
    $company = db_one('SELECT * FROM companies WHERE id = ?', [(int) $id]);
    if ($company === null) {
        not_found();
    }
    return $company;
}

function admin_company(string $id): void
{
    require_admin();
    $company = admin_company_or_404($id);
    $users = db_all('SELECT * FROM users WHERE company_id = ? ORDER BY name', [(int) $company['id']]);
    $invites = db_all(
        'SELECT * FROM invites WHERE company_id = ? AND accepted_at IS NULL AND cancelled_at IS NULL ORDER BY created_at DESC',
        [(int) $company['id']]
    );
    $signatureCount = (int) db_value('SELECT COUNT(*) FROM signatures WHERE company_id = ?', [(int) $company['id']]);
    render('admin/empresa', [
        'title' => $company['name'], 'company' => $company, 'users' => $users,
        'invites' => $invites, 'signatureCount' => $signatureCount,
    ]);
}

function admin_company_rename(string $id): void
{
    require_admin();
    $company = admin_company_or_404($id);
    $name = input('empresa', 160);
    if ($name === '') {
        flash('error', 'Informe o nome da empresa.');
    } else {
        db_update('companies', ['name' => $name], 'id = ?', [(int) $company['id']]);
        flash('success', 'Nome da empresa atualizado.');
    }
    redirect('/admin/empresas/' . $company['id']);
}

function admin_company_status(string $id): void
{
    require_admin();
    $company = admin_company_or_404($id);
    $status = input('status', 20) === 'suspended' ? 'suspended' : 'active';
    db_update('companies', ['status' => $status], 'id = ?', [(int) $company['id']]);
    flash('success', $status === 'active'
        ? 'Empresa reativada. Os usuários já podem entrar.'
        : 'Empresa suspensa. Os usuários perderam o acesso e os links de instalação foram desativados.');
    redirect('/admin/empresas/' . $company['id']);
}

function admin_company_invite(string $id): void
{
    $admin = require_admin();
    $company = admin_company_or_404($id);
    $name = input('nome', 160);
    $email = mb_strtolower(input('email', 190));
    if ($name === '' || !valid_email($email)) {
        flash('error', 'Informe o nome e um e-mail válido para o convite.');
    } elseif (db_value('SELECT COUNT(*) FROM users WHERE email = ?', [$email]) > 0) {
        flash('error', 'Já existe um usuário com o e-mail ' . $email . '.');
    } else {
        admin_send_invite((int) $company['id'], $company['name'], $name, $email, 'client', (int) $admin['id']);
    }
    redirect('/admin/empresas/' . $company['id']);
}

/** O administrador passa a ver a área do cliente da empresa escolhida. */
function admin_company_open(string $id): void
{
    require_admin();
    $company = admin_company_or_404($id);
    $_SESSION['acting_company_id'] = (int) $company['id'];
    redirect('/assinaturas');
}

function admin_company_leave(): void
{
    require_admin();
    unset($_SESSION['acting_company_id']);
    redirect('/admin');
}

function admin_invite_or_404(string $id): array
{
    $invite = db_one(
        'SELECT i.*, c.name AS company_name FROM invites i LEFT JOIN companies c ON c.id = i.company_id WHERE i.id = ?',
        [(int) $id]
    );
    if ($invite === null) {
        not_found();
    }
    return $invite;
}

function admin_invite_back(array $invite): void
{
    redirect($invite['company_id'] ? '/admin/empresas/' . $invite['company_id'] : '/admin/equipe');
}

function admin_invite_resend(string $id): void
{
    $admin = require_admin();
    $invite = admin_invite_or_404($id);
    if ($invite['accepted_at'] !== null) {
        flash('error', 'Este convite já foi aceito.');
    } else {
        admin_send_invite(
            $invite['company_id'] ? (int) $invite['company_id'] : null,
            $invite['company_name'],
            $invite['name'], $invite['email'], $invite['role'], (int) $admin['id']
        );
    }
    admin_invite_back($invite);
}

function admin_invite_cancel(string $id): void
{
    require_admin();
    $invite = admin_invite_or_404($id);
    db_run('UPDATE invites SET cancelled_at = ? WHERE id = ? AND accepted_at IS NULL', [now_utc(), (int) $invite['id']]);
    flash('success', 'Convite cancelado. O link antigo não funciona mais.');
    admin_invite_back($invite);
}

function admin_user_or_404(string $id): array
{
    $user = db_one('SELECT * FROM users WHERE id = ?', [(int) $id]);
    if ($user === null) {
        not_found();
    }
    return $user;
}

function admin_user_back(array $user): void
{
    redirect($user['company_id'] ? '/admin/empresas/' . $user['company_id'] : '/admin/equipe');
}

/** Impede que a KIWEE fique sem nenhum administrador ativo. */
function admin_is_last_active_admin(array $user): bool
{
    if ($user['role'] !== 'admin' || $user['status'] !== 'active') {
        return false;
    }
    return (int) db_value("SELECT COUNT(*) FROM users WHERE role = 'admin' AND status = 'active'") <= 1;
}

function admin_user_status(string $id): void
{
    $admin = require_admin();
    $user = admin_user_or_404($id);
    $status = input('status', 20) === 'suspended' ? 'suspended' : 'active';
    if ($status === 'suspended' && (int) $user['id'] === (int) $admin['id']) {
        flash('error', 'Você não pode suspender o seu próprio acesso.');
    } elseif ($status === 'suspended' && admin_is_last_active_admin($user)) {
        flash('error', 'É preciso manter pelo menos um administrador ativo.');
    } else {
        db_update('users', ['status' => $status], 'id = ?', [(int) $user['id']]);
        flash('success', $status === 'active' ? 'Acesso de ' . $user['name'] . ' reativado.' : 'Acesso de ' . $user['name'] . ' suspenso.');
    }
    admin_user_back($user);
}

function admin_user_remove(string $id): void
{
    $admin = require_admin();
    $user = admin_user_or_404($id);
    if ((int) $user['id'] === (int) $admin['id']) {
        flash('error', 'Você não pode remover o seu próprio acesso.');
    } elseif (admin_is_last_active_admin($user)) {
        flash('error', 'É preciso manter pelo menos um administrador ativo.');
    } else {
        db_run('DELETE FROM users WHERE id = ?', [(int) $user['id']]);
        flash('success', 'Acesso de ' . $user['name'] . ' removido. As assinaturas da empresa foram mantidas.');
    }
    admin_user_back($user);
}

function admin_team(): void
{
    $admin = require_admin();
    $admins = db_all("SELECT * FROM users WHERE role = 'admin' ORDER BY name");
    $invites = db_all(
        "SELECT * FROM invites WHERE role = 'admin' AND accepted_at IS NULL AND cancelled_at IS NULL ORDER BY created_at DESC"
    );
    render('admin/equipe', ['title' => 'Equipe KIWEE', 'admins' => $admins, 'invites' => $invites, 'me' => $admin]);
}

function admin_team_invite(): void
{
    $admin = require_admin();
    $name = input('nome', 160);
    $email = mb_strtolower(input('email', 190));
    if ($name === '' || !valid_email($email)) {
        flash('error', 'Informe o nome e um e-mail válido para o convite.');
    } elseif (db_value('SELECT COUNT(*) FROM users WHERE email = ?', [$email]) > 0) {
        flash('error', 'Já existe um usuário com o e-mail ' . $email . '.');
    } else {
        admin_send_invite(null, null, $name, $email, 'admin', (int) $admin['id']);
    }
    redirect('/admin/equipe');
}
