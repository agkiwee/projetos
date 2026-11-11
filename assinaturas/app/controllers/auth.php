<?php
declare(strict_types=1);

/** Página inicial: leva cada perfil para a sua área. */
function page_home(): void
{
    $user = current_user();
    if ($user === null) {
        redirect('/entrar');
    }
    redirect(is_admin($user) ? '/admin' : '/assinaturas');
}

function page_login(): void
{
    if (current_user() !== null) {
        redirect('/');
    }
    // Instalação ainda sem administrador: leva direto para a configuração inicial.
    if (setup_available()) {
        redirect('/instalar');
    }
    render('auth/entrar', ['title' => 'Entrar', 'shell' => 'guest', 'email' => '', 'error' => null]);
}

function action_login(): void
{
    $email = mb_strtolower(input('email', 190));
    $password = isset($_POST['senha']) && is_string($_POST['senha']) ? $_POST['senha'] : '';
    $fail = static function (string $message) use ($email): void {
        http_response_code(422);
        render('auth/entrar', ['title' => 'Entrar', 'shell' => 'guest', 'email' => $email, 'error' => $message]);
        exit;
    };

    if ($email === '' || $password === '') {
        $fail('Informe seu e-mail e sua senha.');
    }
    if (login_throttled($email)) {
        $fail('Muitas tentativas seguidas. Aguarde 15 minutos e tente de novo.');
    }
    $user = db_one(
        'SELECT u.*, c.status AS company_status FROM users u LEFT JOIN companies c ON c.id = u.company_id WHERE u.email = ?',
        [$email]
    );
    // Verifica uma senha mesmo quando o e-mail não existe, para o tempo de resposta não revelar quem tem conta.
    $hash = $user['password_hash'] ?? '$2y$10$dKt.bEl7dfA8BIqYnR/R.ee.tpEAdtjkgOEbJudOiIpXJzvKXTJgW';
    if (!password_verify($password, $hash) || !$user) {
        login_record_failure($email);
        $fail('E-mail ou senha incorretos.');
    }
    $blocked = access_block_reason($user);
    if ($blocked !== null) {
        $fail($blocked);
    }
    login_clear_failures($email);
    if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
        $user['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
        db_update('users', ['password_hash' => $user['password_hash']], 'id = ?', [(int) $user['id']]);
    }
    login_user($user);
    redirect('/');
}

function action_logout(): void
{
    logout_user();
    redirect('/entrar');
}

function page_invite(string $token): void
{
    $invite = invite_find_valid($token);
    if ($invite === null) {
        http_response_code(410);
        render('erro', [
            'title' => 'Convite indisponível',
            'shell' => 'guest',
            'code' => 410,
            'message' => 'Este convite já foi usado, foi cancelado ou passou da validade. Peça um novo convite à KIWEE.',
        ]);
        return;
    }
    render('auth/convite', ['title' => 'Criar acesso', 'shell' => 'guest', 'invite' => $invite, 'token' => $token, 'error' => null, 'name' => $invite['name']]);
}

function action_invite(string $token): void
{
    $invite = invite_find_valid($token);
    if ($invite === null) {
        page_invite($token);
        return;
    }
    $name = input('nome', 160);
    $password = isset($_POST['senha']) && is_string($_POST['senha']) ? $_POST['senha'] : '';
    $confirm = isset($_POST['senha2']) && is_string($_POST['senha2']) ? $_POST['senha2'] : '';
    $error = $name === '' ? 'Informe seu nome.' : password_problem($password, $confirm);
    if ($error === null && db_value('SELECT COUNT(*) FROM users WHERE email = ?', [$invite['email']]) > 0) {
        $error = 'Já existe um acesso com este e-mail. Use a tela de entrada.';
    }
    if ($error !== null) {
        http_response_code(422);
        render('auth/convite', ['title' => 'Criar acesso', 'shell' => 'guest', 'invite' => $invite, 'token' => $token, 'error' => $error, 'name' => $name]);
        return;
    }

    $userId = db_insert('users', [
        'company_id' => $invite['role'] === 'admin' ? null : (int) $invite['company_id'],
        'name' => $name,
        'email' => $invite['email'],
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        'role' => $invite['role'] === 'admin' ? 'admin' : 'client',
        'status' => 'active',
        'created_at' => now_utc(),
    ]);
    db_update('invites', ['accepted_at' => now_utc()], 'id = ?', [(int) $invite['id']]);
    login_user(['id' => $userId]);
    flash('success', 'Acesso criado. Boas-vindas!');
    redirect('/');
}

function page_forgot(): void
{
    render('auth/esqueci', ['title' => 'Recuperar senha', 'shell' => 'guest', 'sent' => false]);
}

function action_forgot(): void
{
    $email = mb_strtolower(input('email', 190));
    // Limite próprio do envio de links, separado do limite de login: pedir links não tranca a conta de ninguém.
    $allowed = valid_email($email)
        && throttle_count('forgot:' . $email) < 3
        && throttle_count('forgot-ip:' . client_ip()) < 12;
    if ($allowed) {
        throttle_hit('forgot:' . $email);
        throttle_hit('forgot-ip:' . client_ip());
        $user = db_one('SELECT * FROM users WHERE email = ? AND status = ?', [$email, 'active']);
        if ($user) {
            $token = random_token(32);
            db_insert('password_resets', [
                'user_id' => (int) $user['id'],
                'token_hash' => hash('sha256', $token),
                'expires_at' => gmdate('Y-m-d H:i:s', time() + 3600),
                'created_at' => now_utc(),
            ]);
            $link = absolute_url('/redefinir-senha/' . $token);
            $text = "Olá, {$user['name']}.\n\nPara criar uma nova senha, abra o link abaixo. Ele vale por 1 hora.\n$link\n\n"
                . "Se você não pediu a troca de senha, ignore este e-mail.\n";
            $html = mail_layout(
                'Criar nova senha',
                '<p>Olá, ' . e($user['name']) . '.</p><p>Recebemos um pedido para trocar a senha do seu acesso.</p>',
                'Criar nova senha',
                $link,
                'O link vale por 1 hora. Se você não pediu a troca de senha, ignore este e-mail.'
            );
            send_mail($user['email'], 'Nova senha em ' . config('app_name', 'KIWEE Assinaturas'), $html, $text);
        }
    }
    // A resposta é sempre a mesma, exista ou não uma conta com o e-mail informado.
    render('auth/esqueci', ['title' => 'Recuperar senha', 'shell' => 'guest', 'sent' => true]);
}

function reset_find_valid(string $token): ?array
{
    $reset = db_one('SELECT * FROM password_resets WHERE token_hash = ?', [hash('sha256', $token)]);
    if (!$reset || $reset['used_at'] !== null || strtotime($reset['expires_at'] . ' UTC') < time()) {
        return null;
    }
    return $reset;
}

function page_reset(string $token, ?string $error = null): void
{
    if (reset_find_valid($token) === null) {
        http_response_code(410);
        render('erro', [
            'title' => 'Link indisponível',
            'shell' => 'guest',
            'code' => 410,
            'message' => 'Este link já foi usado ou passou da validade. Peça um novo na tela de recuperação de senha.',
        ]);
        return;
    }
    render('auth/redefinir', ['title' => 'Nova senha', 'shell' => 'guest', 'token' => $token, 'error' => $error]);
}

function action_reset(string $token): void
{
    $reset = reset_find_valid($token);
    if ($reset === null) {
        page_reset($token);
        return;
    }
    $password = isset($_POST['senha']) && is_string($_POST['senha']) ? $_POST['senha'] : '';
    $confirm = isset($_POST['senha2']) && is_string($_POST['senha2']) ? $_POST['senha2'] : '';
    $error = password_problem($password, $confirm);
    if ($error !== null) {
        http_response_code(422);
        page_reset($token, $error);
        return;
    }
    db_update('users', ['password_hash' => password_hash($password, PASSWORD_DEFAULT)], 'id = ?', [(int) $reset['user_id']]);
    db_run('UPDATE password_resets SET used_at = ? WHERE user_id = ? AND used_at IS NULL', [now_utc(), (int) $reset['user_id']]);
    // Quem acabou de provar que é dono do e-mail não fica preso por tentativas anteriores.
    $email = (string) db_value('SELECT email FROM users WHERE id = ?', [(int) $reset['user_id']]);
    login_clear_failures($email);
    flash('success', 'Senha alterada. Entre com a nova senha.');
    redirect('/entrar');
}

/* ------------------------------ Instalação ------------------------------ */

function setup_available(): bool
{
    return !schema_installed() || (int) db_value("SELECT COUNT(*) FROM users WHERE role = 'admin'") === 0;
}

function setup_checks(): array
{
    // Pastas que a publicação pode não ter criado: tenta criar antes de conferir.
    foreach ([KW_ROOT . '/storage', KW_PUBLIC . '/uploads', KW_PUBLIC . '/icones'] as $dir) {
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
    }
    return [
        ['PHP 8.0 ou mais novo', version_compare(PHP_VERSION, '8.0.0', '>='), 'Versão atual: ' . PHP_VERSION],
        ['Extensão GD (imagens e ícones)', function_exists('imagecreatetruecolor'), 'Ative a extensão gd no seletor de PHP do cPanel.'],
        ['Extensão cURL (importar imagem por URL)', function_exists('curl_init'), 'Ative a extensão curl no seletor de PHP do cPanel.'],
        ['Extensão mbstring', function_exists('mb_substr'), 'Ative a extensão mbstring.'],
        ['Pasta storage gravável', is_writable(KW_ROOT . '/storage'), 'Dê permissão de escrita (755) para a pasta storage.'],
        ['Pasta uploads gravável', is_writable(KW_PUBLIC . '/uploads'), 'Dê permissão de escrita (755) para a pasta uploads dentro da pasta pública.'],
        ['Pasta icones gravável', is_writable(KW_PUBLIC . '/icones'), 'Dê permissão de escrita (755) para a pasta icones dentro da pasta pública.'],
        ['Endereço do site configurado', trim((string) config('app_url', '')) !== '', 'Preencha app_url no config.php.'],
        ['Envio de arquivos de até 4 MB', upload_limit_bytes() >= IMG_MAX_UPLOAD_BYTES,
            'O PHP aceita arquivos de até ' . format_megabytes(upload_limit_bytes()) . '. Aumente upload_max_filesize e post_max_size para 8M no cPanel.'],
    ];
}

function page_setup(?string $error = null, array $old = []): void
{
    if (!setup_available()) {
        redirect('/entrar');
    }
    $dbError = null;
    try {
        db();
    } catch (Throwable $e) {
        $dbError = 'Não foi possível conectar ao banco de dados. Confira os dados em config.php.';
    }
    render('auth/instalar', [
        'title' => 'Configuração inicial', 'shell' => 'guest', 'checks' => setup_checks(),
        'dbError' => $dbError, 'error' => $error, 'old' => $old,
    ]);
}

function action_setup(): void
{
    if (!setup_available()) {
        redirect('/entrar');
    }
    $old = ['nome' => input('nome', 160), 'email' => mb_strtolower(input('email', 190))];
    $key = input('chave', 200);
    $password = isset($_POST['senha']) && is_string($_POST['senha']) ? $_POST['senha'] : '';
    $confirm = isset($_POST['senha2']) && is_string($_POST['senha2']) ? $_POST['senha2'] : '';
    $expected = (string) config('setup_key', '');

    $error = null;
    if (trim((string) config('app_url', '')) === '') {
        // Sem endereço fixo, os links dos e-mails seriam montados a partir do que o navegador informa.
        $error = 'Preencha app_url no config.php com o endereço do site antes de instalar.';
    } elseif ($expected === '' || $expected === 'troque-esta-chave') {
        $error = 'Defina uma chave própria em setup_key no config.php antes de continuar.';
    } elseif (!hash_equals($expected, $key)) {
        $error = 'A chave de instalação não confere com a do config.php.';
    } elseif ($old['nome'] === '' || !valid_email($old['email'])) {
        $error = 'Informe seu nome e um e-mail válido.';
    } else {
        $error = password_problem($password, $confirm);
    }
    if ($error !== null) {
        http_response_code(422);
        page_setup($error, $old);
        return;
    }

    schema_install();
    $userId = db_insert('users', [
        'company_id' => null,
        'name' => $old['nome'],
        'email' => $old['email'],
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        'role' => 'admin',
        'status' => 'active',
        'created_at' => now_utc(),
    ]);
    login_user(['id' => $userId]);
    flash('success', 'Plataforma instalada. Cadastre a primeira empresa.');
    redirect('/admin');
}

/* -------------------------------- Conta --------------------------------- */

function page_account(?string $error = null): void
{
    $user = require_login();
    render('auth/conta', ['title' => 'Minha conta', 'user' => $user, 'error' => $error]);
}

function action_account(): void
{
    $user = require_login();
    $name = input('nome', 160);
    $current = isset($_POST['senha_atual']) && is_string($_POST['senha_atual']) ? $_POST['senha_atual'] : '';
    $password = isset($_POST['senha']) && is_string($_POST['senha']) ? $_POST['senha'] : '';
    $confirm = isset($_POST['senha2']) && is_string($_POST['senha2']) ? $_POST['senha2'] : '';

    if ($name === '') {
        http_response_code(422);
        page_account('Informe seu nome.');
        return;
    }
    $changes = ['name' => $name];
    if ($password !== '') {
        $error = !password_verify($current, $user['password_hash']) ? 'A senha atual não confere.' : password_problem($password, $confirm);
        if ($error !== null) {
            http_response_code(422);
            page_account($error);
            return;
        }
        $changes['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
    }
    db_update('users', $changes, 'id = ?', [(int) $user['id']]);
    if (isset($changes['password_hash'])) {
        // Esta sessão continua valendo; as abertas em outros aparelhos são encerradas.
        $_SESSION['pw'] = password_fingerprint($changes['password_hash']);
    }
    flash('success', isset($changes['password_hash']) ? 'Senha alterada. Outros aparelhos conectados foram desconectados.' : 'Dados da conta atualizados.');
    redirect('/conta');
}

/* ------------------- Link de instalação do colaborador ------------------- */

/**
 * Página pública de uma assinatura, aberta pelo colaborador sem login.
 * Mostra apenas a assinatura pronta, com os botões de copiar e baixar.
 */
function page_share(string $token): void
{
    $row = db_one(
        "SELECT s.*, c.public_key, c.status AS company_status, c.name AS company_name
           FROM signatures s JOIN companies c ON c.id = s.company_id
          WHERE s.share_token = ?",
        [$token]
    );
    if (!$row || $row['company_status'] !== 'active') {
        not_found();
    }
    header('X-Robots-Tag: noindex, nofollow');
    render('publico/instalar', [
        'title' => 'Instalar assinatura',
        'shell' => 'guest-wide',
        'signatureName' => $row['name'],
        'companyName' => $row['company_name'],
        'boot' => [
            'baseUrl' => app_url(),
            'data' => sig_public_view(sig_sanitize(json_decode((string) $row['data'], true), (string) $row['public_key'])),
            'name' => $row['name'],
        ],
    ]);
}
