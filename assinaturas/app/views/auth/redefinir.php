<section class="card card-narrow">
    <h1>Nova senha</h1>
    <?php if ($error): ?><div class="alert alert-error" role="alert"><p><?= e($error) ?></p></div><?php endif; ?>
    <form method="post" action="<?= e(url('/redefinir-senha/' . $token)) ?>" class="form">
        <?= csrf_field() ?>
        <label for="senha">Nova senha</label>
        <input id="senha" name="senha" type="password" required minlength="<?= KW_PASSWORD_MIN ?>" autocomplete="new-password" autofocus aria-describedby="senha-dica">
        <p id="senha-dica" class="hint">Pelo menos <?= KW_PASSWORD_MIN ?> caracteres.</p>
        <label for="senha2">Repita a nova senha</label>
        <input id="senha2" name="senha2" type="password" required minlength="<?= KW_PASSWORD_MIN ?>" autocomplete="new-password">
        <button type="submit" class="btn btn-primary btn-block">Salvar nova senha</button>
    </form>
</section>
