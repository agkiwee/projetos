<section class="card card-narrow">
    <h1>Entrar</h1>
    <p class="muted">Acesso exclusivo para empresas convidadas pela KIWEE.</p>
    <?php if ($error): ?><div class="alert alert-error" role="alert"><p><?= e($error) ?></p></div><?php endif; ?>
    <form method="post" action="<?= e(url('/entrar')) ?>" class="form">
        <?= csrf_field() ?>
        <label for="email">E-mail</label>
        <input id="email" name="email" type="email" autocomplete="username" required value="<?= e($email) ?>" autofocus>
        <label for="senha">Senha</label>
        <input id="senha" name="senha" type="password" autocomplete="current-password" required>
        <button type="submit" class="btn btn-primary btn-block">Entrar</button>
    </form>
    <p class="form-foot"><a href="<?= e(url('/esqueci-senha')) ?>">Esqueci minha senha</a></p>
</section>
