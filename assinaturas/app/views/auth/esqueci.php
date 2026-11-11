<section class="card card-narrow">
    <h1>Recuperar senha</h1>
    <?php if ($sent): ?>
        <div class="alert alert-success" role="status">
            <p>Se houver um acesso com esse e-mail, enviamos um link para criar uma nova senha. O link vale por 1 hora.</p>
        </div>
        <p class="form-foot"><a href="<?= e(url('/entrar')) ?>">Voltar para a entrada</a></p>
    <?php else: ?>
        <p class="muted">Informe seu e-mail e enviaremos um link para criar uma nova senha.</p>
        <form method="post" action="<?= e(url('/esqueci-senha')) ?>" class="form">
            <?= csrf_field() ?>
            <label for="email">E-mail</label>
            <input id="email" name="email" type="email" required autocomplete="username" autofocus>
            <button type="submit" class="btn btn-primary btn-block">Enviar link</button>
        </form>
        <p class="form-foot"><a href="<?= e(url('/entrar')) ?>">Voltar para a entrada</a></p>
    <?php endif; ?>
</section>
