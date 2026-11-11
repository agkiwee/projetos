<?php
/** @var string $content */
$shell = $shell ?? 'app';
$user = in_array($shell, ['guest', 'guest-wide'], true) ? null : current_user();
$appName = (string) config('app_name', 'KIWEE Assinaturas');
$acting = null;
if ($user && is_admin($user) && !empty($_SESSION['acting_company_id'])) {
    $acting = db_one('SELECT id, name FROM companies WHERE id = ?', [(int) $_SESSION['acting_company_id']]);
}
$path = request_path();
$isActive = static function (string $prefix) use ($path): string {
    return strpos($path, $prefix) === 0 ? ' aria-current="page"' : '';
};
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?= e(($title ?? '') !== '' ? $title . ' · ' . $appName : $appName) ?></title>
    <link rel="icon" href="<?= e(asset('img/favicon.svg')) ?>" type="image/svg+xml">
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body class="shell-<?= e($shell) ?>">
<a class="skip-link" href="#conteudo">Pular para o conteúdo</a>

<?php if ($shell === 'guest' || $shell === 'guest-wide'): ?>
    <main id="conteudo" class="guest <?= $shell === 'guest-wide' ? 'guest-wide' : '' ?>">
        <div class="guest-brand"><?php require KW_ROOT . '/app/views/partials/logo.php'; ?></div>
        <?php require KW_ROOT . '/app/views/partials/flashes.php'; ?>
        <?= $content ?>
    </main>
<?php else: ?>
    <header class="topbar">
        <a class="topbar-brand" href="<?= e(url('/')) ?>"><?php require KW_ROOT . '/app/views/partials/logo.php'; ?></a>
        <?php if ($user): ?>
            <button type="button" class="nav-toggle btn btn-small" aria-expanded="false" aria-controls="menu-principal" data-nav-toggle>Menu</button>
            <nav id="menu-principal" class="topbar-nav" aria-label="Principal">
                <?php if (is_admin($user)): ?>
                    <a href="<?= e(url('/admin')) ?>"<?= strpos($path, '/admin') === 0 && strpos($path, '/admin/equipe') !== 0 ? ' aria-current="page"' : '' ?>>Empresas</a>
                    <a href="<?= e(url('/admin/equipe')) ?>"<?= $isActive('/admin/equipe') ?>>Equipe KIWEE</a>
                <?php endif; ?>
                <?php if (!is_admin($user) || $acting): ?>
                    <a href="<?= e(url('/assinaturas')) ?>"<?= $isActive('/assinaturas') ?>>Assinaturas</a>
                    <a href="<?= e(url('/padrao')) ?>"<?= $isActive('/padrao') ?>>Modelo padrão</a>
                <?php endif; ?>
                <a href="<?= e(url('/como-instalar')) ?>"<?= $isActive('/como-instalar') ?>>Como instalar</a>
                <span class="topbar-spacer"></span>
                <a href="<?= e(url('/conta')) ?>"<?= $isActive('/conta') ?>><?= e($user['name']) ?></a>
                <form method="post" action="<?= e(url('/sair')) ?>" class="inline-form">
                    <?= csrf_field() ?>
                    <button type="submit" class="link-button">Sair</button>
                </form>
            </nav>
        <?php endif; ?>
    </header>

    <?php if ($acting): ?>
        <div class="acting-bar">
            <span>Você está na área da empresa <strong><?= e($acting['name']) ?></strong>.</span>
            <form method="post" action="<?= e(url('/admin/sair-da-empresa')) ?>" class="inline-form">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-small">Voltar ao painel KIWEE</button>
            </form>
        </div>
    <?php endif; ?>

    <main id="conteudo" class="<?= $shell === 'editor' ? 'editor-main' : 'page' ?>">
        <?php if ($shell !== 'editor'): ?>
            <?php require KW_ROOT . '/app/views/partials/flashes.php'; ?>
        <?php endif; ?>
        <?= $content ?>
    </main>
<?php endif; ?>

<div class="toast-area" id="toasts" aria-live="polite"></div>
<script src="<?= e(asset('js/app.js')) ?>"></script>
<?php foreach ($scripts ?? [] as $script): ?>
    <script src="<?= e(asset($script)) ?>"></script>
<?php endforeach; ?>
</body>
</html>
