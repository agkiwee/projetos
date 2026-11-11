<div class="page-head"><h1>Minha conta</h1></div>
<section class="card card-form">
    <?php if ($error): ?><div class="alert alert-error" role="alert"><p><?= e($error) ?></p></div><?php endif; ?>
    <form method="post" action="<?= e(url('/conta')) ?>" class="form">
        <?= csrf_field() ?>
        <label for="email">E-mail</label>
        <input id="email" type="email" value="<?= e($user['email']) ?>" readonly autocomplete="username">
        <label for="nome">Nome</label>
        <input id="nome" name="nome" type="text" required maxlength="160" value="<?= e($user['name']) ?>" autocomplete="name">
        <h2 class="h3">Trocar senha</h2>
        <p class="hint">Deixe em branco para manter a senha atual.</p>
        <label for="senha_atual">Senha atual</label>
        <input id="senha_atual" name="senha_atual" type="password" autocomplete="current-password">
        <label for="senha">Nova senha</label>
        <input id="senha" name="senha" type="password" minlength="<?= KW_PASSWORD_MIN ?>" autocomplete="new-password">
        <label for="senha2">Repita a nova senha</label>
        <input id="senha2" name="senha2" type="password" minlength="<?= KW_PASSWORD_MIN ?>" autocomplete="new-password">
        <button type="submit" class="btn btn-primary">Salvar</button>
    </form>
</section>
