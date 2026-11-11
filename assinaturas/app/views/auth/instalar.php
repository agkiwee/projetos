<?php $allOk = $dbError === null; foreach ($checks as $check) { $allOk = $allOk && $check[1]; } ?>
<section class="card card-narrow">
    <h1>Configuração inicial</h1>
    <p class="muted">Este passo cria as tabelas do banco e o primeiro administrador da KIWEE. Ele só fica disponível enquanto não existir um administrador.</p>

    <h2 class="h3">Verificação do servidor</h2>
    <ul class="checklist">
        <li class="<?= $dbError === null ? 'ok' : 'fail' ?>">
            <strong>Conexão com o banco de dados</strong>
            <?php if ($dbError): ?><span><?= e($dbError) ?></span><?php endif; ?>
        </li>
        <?php foreach ($checks as [$label, $ok, $help]): ?>
            <li class="<?= $ok ? 'ok' : 'fail' ?>">
                <strong><?= e($label) ?></strong>
                <?php if (!$ok): ?><span><?= e($help) ?></span><?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ul>

    <?php if ($error): ?><div class="alert alert-error" role="alert"><p><?= e($error) ?></p></div><?php endif; ?>

    <?php if ($dbError === null): ?>
        <form method="post" action="<?= e(url('/instalar')) ?>" class="form">
            <?= csrf_field() ?>
            <label for="chave">Chave de instalação</label>
            <input id="chave" name="chave" type="password" required autocomplete="off" aria-describedby="chave-dica">
            <p id="chave-dica" class="hint">É o valor de <code>setup_key</code> no arquivo config.php.</p>
            <label for="nome">Seu nome</label>
            <input id="nome" name="nome" type="text" required maxlength="160" value="<?= e($old['nome'] ?? '') ?>" autocomplete="name">
            <label for="email">Seu e-mail</label>
            <input id="email" name="email" type="email" required value="<?= e($old['email'] ?? '') ?>" autocomplete="username">
            <label for="senha">Senha</label>
            <input id="senha" name="senha" type="password" required minlength="<?= KW_PASSWORD_MIN ?>" autocomplete="new-password">
            <label for="senha2">Repita a senha</label>
            <input id="senha2" name="senha2" type="password" required minlength="<?= KW_PASSWORD_MIN ?>" autocomplete="new-password">
            <button type="submit" class="btn btn-primary btn-block">Instalar e criar administrador</button>
        </form>
        <?php if (!$allOk): ?><p class="hint">Alguns itens acima precisam de ajuste. Você pode instalar agora e corrigir depois, mas imagens e ícones dependem deles.</p><?php endif; ?>
    <?php endif; ?>
</section>
