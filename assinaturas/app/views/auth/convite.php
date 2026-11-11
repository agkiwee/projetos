<section class="card card-narrow">
    <h1>Criar seu acesso</h1>
    <p class="muted">
        <?php if ($invite['role'] === 'admin'): ?>
            Você foi convidado para a equipe que administra a plataforma.
        <?php else: ?>
            Você foi convidado para gerenciar as assinaturas de e-mail da empresa <strong><?= e($invite['company_name']) ?></strong>.
        <?php endif; ?>
    </p>
    <?php if ($error): ?><div class="alert alert-error" role="alert"><p><?= e($error) ?></p></div><?php endif; ?>
    <form method="post" action="<?= e(url('/convite/' . $token)) ?>" class="form">
        <?= csrf_field() ?>
        <label for="email">E-mail</label>
        <input id="email" type="email" value="<?= e($invite['email']) ?>" readonly autocomplete="username">
        <label for="nome">Seu nome</label>
        <input id="nome" name="nome" type="text" required maxlength="160" value="<?= e($name) ?>" autocomplete="name">
        <label for="senha">Crie uma senha</label>
        <input id="senha" name="senha" type="password" required minlength="<?= KW_PASSWORD_MIN ?>" autocomplete="new-password" aria-describedby="senha-dica">
        <p id="senha-dica" class="hint">Pelo menos <?= KW_PASSWORD_MIN ?> caracteres.</p>
        <label for="senha2">Repita a senha</label>
        <input id="senha2" name="senha2" type="password" required minlength="<?= KW_PASSWORD_MIN ?>" autocomplete="new-password">
        <button type="submit" class="btn btn-primary btn-block">Criar acesso e entrar</button>
    </form>
</section>
