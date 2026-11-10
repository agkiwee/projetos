<?php
declare(strict_types=1);

const KW_SESSION_IDLE_SECONDS = 43200; // 12 horas sem uso encerram a sessão
const KW_PASSWORD_MIN = 8;
const KW_LOGIN_MAX_ATTEMPTS = 8;
const KW_LOGIN_WINDOW_SECONDS = 900;

function session_boot(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_name('kwsess');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => base_path() === '' ? '/' : base_path() . '/',
        'secure' => is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();

    $last = (int) ($_SESSION['last_seen'] ?? 0);
    if ($last > 0 && time() - $last > KW_SESSION_IDLE_SECONDS) {
        $_SESSION = [];
        session_regenerate_id(true);
    }
    $_SESSION['last_seen'] = time();
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = random_token(24);
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

/** Toda requisição que altera dados precisa do token da sessão. */
function csrf_check(): void
{
    $sent = $_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!is_string($sent) || $sent === '' || !hash_equals(csrf_token(), $sent)) {
        if (wants_json()) {
            json_out(['ok' => false, 'error' => 'Sua sessão expirou. Recarregue a página e tente de novo.'], 419);
        }
        http_response_code(419);
        render('erro', [
            'title' => 'Sessão expirada',
            'code' => 419,
            'message' => 'Por segurança, recarregue a página e envie o formulário novamente.',
        ]);
        exit;
    }
}

/**
 * Usuário da sessão, relido do banco a cada requisição.
 * Assim uma suspensão vale na hora, mesmo para quem já estava conectado.
 */
function current_user(): ?array
{
    static $loaded = false;
    static $user = null;
    if ($loaded) {
        return $user;
    }
    $loaded = true;
    $id = (int) ($_SESSION['user_id'] ?? 0);
    if ($id <= 0) {
        return null;
    }
    $row = db_one(
        'SELECT u.*, c.status AS company_status, c.name AS company_name
           FROM users u LEFT JOIN companies c ON c.id = u.company_id
          WHERE u.id = ?',
        [$id]
    );
    if (!$row || access_block_reason($row) !== null) {
        logout_user();
        return null;
    }
    return $user = $row;
}

/** Motivo pelo qual um usuário não pode entrar, ou null quando está liberado. */
function access_block_reason(array $user): ?string
{
    if ($user['status'] !== 'active') {
        return 'Seu acesso está suspenso. Fale com a KIWEE.';
    }
    if ($user['role'] === 'admin') {
        return null;
    }
    if (empty($user['company_id']) || ($user['company_status'] ?? '') !== 'active') {
        return 'O acesso da sua empresa está suspenso. Fale com a KIWEE.';
    }
    return null;
}

function is_admin(?array $user = null): bool
{
    $user = $user ?? current_user();
    return $user !== null && $user['role'] === 'admin';
}

function login_user(array $user): void
{
    session_regenerate_id(true);
    $_SESSION = ['user_id' => (int) $user['id'], 'last_seen' => time()];
    csrf_token();
    db_update('users', ['last_login_at' => now_utc()], 'id = ?', [(int) $user['id']]);
}

function logout_user(): void
{
    $_SESSION = [];
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }
}

function require_login(): array
{
    $user = current_user();
    if ($user === null) {
        if (wants_json()) {
            json_out(['ok' => false, 'error' => 'Faça login para continuar.'], 401);
        }
        redirect('/entrar');
    }
    return $user;
}

function require_admin(): array
{
    $user = require_login();
    if (!is_admin($user)) {
        forbidden('Esta área é exclusiva da equipe KIWEE.');
    }
    return $user;
}

/**
 * Empresa em uso na área do cliente.
 * Usuário do cliente: sempre a própria empresa, vinda do banco e nunca da requisição.
 * Administrador KIWEE: a empresa que ele escolheu abrir no painel.
 */
function require_company(): array
{
    $user = require_login();
    if (is_admin($user)) {
        $id = (int) ($_SESSION['acting_company_id'] ?? 0);
        $company = $id > 0 ? db_one('SELECT * FROM companies WHERE id = ?', [$id]) : null;
        if ($company === null) {
            if (wants_json()) {
                json_out(['ok' => false, 'error' => 'Escolha uma empresa no painel da KIWEE.'], 409);
            }
            redirect('/admin');
        }
        return $company;
    }
    $company = db_one('SELECT * FROM companies WHERE id = ?', [(int) $user['company_id']]);
    if ($company === null || $company['status'] !== 'active') {
        logout_user();
        redirect('/entrar');
    }
    return $company;
}

function password_problem(string $password, string $confirm): ?string
{
    if (mb_strlen($password) < KW_PASSWORD_MIN) {
        return 'A senha precisa ter pelo menos ' . KW_PASSWORD_MIN . ' caracteres.';
    }
    if (strlen($password) > 200) {
        return 'A senha é longa demais.';
    }
    if ($password !== $confirm) {
        return 'As duas senhas não são iguais.';
    }
    return null;
}

function login_throttled(string $email): bool
{
    $since = time() - KW_LOGIN_WINDOW_SECONDS;
    $count = static function (string $key) use ($since): int {
        return (int) db_value('SELECT COUNT(*) FROM login_attempts WHERE attempt_key = ? AND created_at > ?', [$key, $since]);
    };
    // Limite por e-mail e um limite mais folgado por endereço IP (redes compartilhadas).
    return $count('e:' . $email) >= KW_LOGIN_MAX_ATTEMPTS || $count('i:' . client_ip()) >= KW_LOGIN_MAX_ATTEMPTS * 4;
}

function login_record_failure(string $email): void
{
    db_insert('login_attempts', ['attempt_key' => 'e:' . $email, 'created_at' => time()]);
    db_insert('login_attempts', ['attempt_key' => 'i:' . client_ip(), 'created_at' => time()]);
    db_run('DELETE FROM login_attempts WHERE created_at < ?', [time() - 86400]);
}

function login_clear_failures(string $email): void
{
    db_run('DELETE FROM login_attempts WHERE attempt_key = ?', ['e:' . $email]);
}

/** Cria um convite e devolve o link que deve ser enviado à pessoa convidada. */
function invite_create(?int $companyId, string $name, string $email, string $role, ?int $createdBy): string
{
    $token = random_token(32);
    // Um novo convite substitui os pendentes do mesmo e-mail.
    db_run(
        'UPDATE invites SET cancelled_at = ? WHERE email = ? AND accepted_at IS NULL AND cancelled_at IS NULL',
        [now_utc(), $email]
    );
    db_insert('invites', [
        'company_id' => $companyId,
        'name' => $name,
        'email' => $email,
        'role' => $role,
        'token_hash' => hash('sha256', $token),
        'expires_at' => gmdate('Y-m-d H:i:s', time() + 86400 * max(1, (int) config('invite_days', 7))),
        'created_by' => $createdBy,
        'created_at' => now_utc(),
    ]);
    return absolute_url('/convite/' . $token);
}

function invite_find_valid(string $token): ?array
{
    if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
        return null;
    }
    $invite = db_one(
        'SELECT i.*, c.name AS company_name, c.status AS company_status
           FROM invites i LEFT JOIN companies c ON c.id = i.company_id
          WHERE i.token_hash = ?',
        [hash('sha256', $token)]
    );
    if (!$invite || $invite['accepted_at'] !== null || $invite['cancelled_at'] !== null) {
        return null;
    }
    if (strtotime($invite['expires_at'] . ' UTC') < time()) {
        return null;
    }
    if ($invite['role'] !== 'admin' && ($invite['company_status'] ?? '') !== 'active') {
        return null;
    }
    return $invite;
}

function invite_send_email(string $name, string $email, string $link, ?string $companyName): bool
{
    $app = (string) config('app_name', 'KIWEE Assinaturas');
    $area = $companyName !== null ? 'a área da empresa ' . $companyName : 'o painel administrativo';
    $days = max(1, (int) config('invite_days', 7));
    $subject = 'Seu convite para ' . $app;
    $text = "Olá, $name.\n\nVocê recebeu um convite para acessar $area em $app.\n\n"
        . "Para criar sua senha, abra o link abaixo:\n$link\n\n"
        . "O convite vale por $days dias. Se você não esperava este e-mail, pode ignorá-lo.\n";
    $html = mail_layout(
        'Você foi convidado',
        '<p>Olá, ' . e($name) . '.</p><p>Você recebeu um convite para acessar ' . e($area) . ' em ' . e($app) . '.</p>',
        'Criar minha senha',
        $link,
        'O convite vale por ' . $days . ' dias. Se você não esperava este e-mail, pode ignorá-lo.'
    );
    return send_mail($email, $subject, $html, $text);
}
