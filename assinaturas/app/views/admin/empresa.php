<?php $active = $company['status'] === 'active'; ?>
<p class="breadcrumb"><a href="<?= e(url('/admin')) ?>">Empresas</a> <span aria-hidden="true">/</span> <?= e($company['name']) ?></p>
<div class="page-head">
    <div>
        <h1><?= e($company['name']) ?> <span class="badge badge-<?= $active ? 'ok' : 'off' ?>"><?= $active ? 'Ativa' : 'Suspensa' ?></span></h1>
        <p class="muted"><?= (int) $signatureCount ?> assinatura(s) · cadastrada em <?= e(format_date($company['created_at'], 'd/m/Y')) ?></p>
    </div>
    <div class="head-actions">
        <form method="post" action="<?= e(url('/admin/empresas/' . $company['id'] . '/abrir')) ?>">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-primary">Abrir área da empresa</button>
        </form>
    </div>
</div>

<div class="grid-2">
    <section class="card">
        <h2>Usuários</h2>
        <?php if (!$users): ?>
            <p class="muted">Nenhum usuário ativo ainda. Envie um convite ao lado.</p>
        <?php else: ?>
            <ul class="people">
                <?php foreach ($users as $person): ?>
                    <li>
                        <div>
                            <strong><?= e($person['name']) ?></strong>
                            <?php if ($person['status'] !== 'active'): ?><span class="badge badge-off">Suspenso</span><?php endif; ?>
                            <br><span class="muted"><?= e($person['email']) ?></span>
                            <br><span class="hint">Último acesso: <?= $person['last_login_at'] ? e(format_date($person['last_login_at'])) : 'nunca' ?></span>
                        </div>
                        <div class="row-actions">
                            <form method="post" action="<?= e(url('/admin/usuarios/' . $person['id'] . '/status')) ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="status" value="<?= $person['status'] === 'active' ? 'suspended' : 'active' ?>">
                                <button type="submit" class="btn btn-small"><?= $person['status'] === 'active' ? 'Suspender' : 'Reativar' ?></button>
                            </form>
                            <form method="post" action="<?= e(url('/admin/usuarios/' . $person['id'] . '/remover')) ?>" data-confirm="Remover o acesso de <?= e($person['name']) ?>? As assinaturas da empresa continuam salvas.">
                                <?= csrf_field() ?>
                                <button type="submit" class="btn btn-small btn-danger">Remover</button>
                            </form>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <?php if ($invites): ?>
            <h3>Convites pendentes</h3>
            <ul class="people">
                <?php foreach ($invites as $invite): $expired = strtotime($invite['expires_at'] . ' UTC') < time(); ?>
                    <li>
                        <div>
                            <strong><?= e($invite['name']) ?></strong>
                            <?php if ($expired): ?><span class="badge badge-off">Vencido</span><?php endif; ?>
                            <br><span class="muted"><?= e($invite['email']) ?></span>
                            <br><span class="hint">Enviado em <?= e(format_date($invite['created_at'])) ?> · vale até <?= e(format_date($invite['expires_at'], 'd/m/Y')) ?></span>
                        </div>
                        <div class="row-actions">
                            <form method="post" action="<?= e(url('/admin/convites/' . $invite['id'] . '/reenviar')) ?>">
                                <?= csrf_field() ?>
                                <button type="submit" class="btn btn-small">Reenviar</button>
                            </form>
                            <form method="post" action="<?= e(url('/admin/convites/' . $invite['id'] . '/cancelar')) ?>" data-confirm="Cancelar o convite de <?= e($invite['name']) ?>?">
                                <?= csrf_field() ?>
                                <button type="submit" class="btn btn-small btn-danger">Cancelar</button>
                            </form>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

    <div class="stack">
        <section class="card">
            <h2>Convidar usuário</h2>
            <p class="muted">A pessoa recebe um link por e-mail para criar a própria senha.</p>
            <form method="post" action="<?= e(url('/admin/empresas/' . $company['id'] . '/convites')) ?>" class="form">
                <?= csrf_field() ?>
                <label for="nome">Nome</label>
                <input id="nome" name="nome" type="text" required maxlength="160" autocomplete="off">
                <label for="email">E-mail</label>
                <input id="email" name="email" type="email" required maxlength="190" autocomplete="off">
                <button type="submit" class="btn btn-primary">Enviar convite</button>
            </form>
        </section>

        <section class="card">
            <h2>Dados da empresa</h2>
            <form method="post" action="<?= e(url('/admin/empresas/' . $company['id'] . '/editar')) ?>" class="form">
                <?= csrf_field() ?>
                <label for="empresa">Nome da empresa</label>
                <input id="empresa" name="empresa" type="text" required maxlength="160" value="<?= e($company['name']) ?>">
                <button type="submit" class="btn">Salvar nome</button>
            </form>
        </section>

        <section class="card">
            <h2>Acesso da empresa</h2>
            <?php if ($active): ?>
                <p class="muted">Ao suspender, todos os usuários desta empresa perdem o acesso na hora e os links de instalação param de abrir. As assinaturas já instaladas nos e-mails continuam funcionando.</p>
                <form method="post" action="<?= e(url('/admin/empresas/' . $company['id'] . '/status')) ?>" data-confirm="Suspender o acesso da empresa <?= e($company['name']) ?>?">
                    <?= csrf_field() ?>
                    <input type="hidden" name="status" value="suspended">
                    <button type="submit" class="btn btn-danger">Suspender empresa</button>
                </form>
            <?php else: ?>
                <p class="muted">A empresa está suspensa. Ninguém dela consegue entrar.</p>
                <form method="post" action="<?= e(url('/admin/empresas/' . $company['id'] . '/status')) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="status" value="active">
                    <button type="submit" class="btn btn-primary">Reativar empresa</button>
                </form>
            <?php endif; ?>
        </section>
    </div>
</div>
