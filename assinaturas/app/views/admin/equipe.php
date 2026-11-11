<div class="page-head">
    <div>
        <h1>Equipe KIWEE</h1>
        <p class="muted">Pessoas que administram a plataforma: cadastram empresas, enviam convites e controlam acessos.</p>
    </div>
</div>
<div class="grid-2">
    <section class="card">
        <h2>Administradores</h2>
        <ul class="people">
            <?php foreach ($admins as $person): ?>
                <li>
                    <div>
                        <strong><?= e($person['name']) ?></strong>
                        <?php if ((int) $person['id'] === (int) $me['id']): ?><span class="badge">Você</span><?php endif; ?>
                        <?php if ($person['status'] !== 'active'): ?><span class="badge badge-off">Suspenso</span><?php endif; ?>
                        <br><span class="muted"><?= e($person['email']) ?></span>
                    </div>
                    <?php if ((int) $person['id'] !== (int) $me['id']): ?>
                        <div class="row-actions">
                            <form method="post" action="<?= e(url('/admin/usuarios/' . $person['id'] . '/status')) ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="status" value="<?= $person['status'] === 'active' ? 'suspended' : 'active' ?>">
                                <button type="submit" class="btn btn-small"><?= $person['status'] === 'active' ? 'Suspender' : 'Reativar' ?></button>
                            </form>
                            <form method="post" action="<?= e(url('/admin/usuarios/' . $person['id'] . '/remover')) ?>" data-confirm="Remover o acesso de <?= e($person['name']) ?>?">
                                <?= csrf_field() ?>
                                <button type="submit" class="btn btn-small btn-danger">Remover</button>
                            </form>
                        </div>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php if ($invites): ?>
            <h3>Convites pendentes</h3>
            <ul class="people">
                <?php foreach ($invites as $invite): ?>
                    <li>
                        <div><strong><?= e($invite['name']) ?></strong><br><span class="muted"><?= e($invite['email']) ?></span></div>
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
    <section class="card">
        <h2>Convidar administrador</h2>
        <p class="muted">Administradores têm acesso a todas as empresas. Convide apenas pessoas da equipe KIWEE.</p>
        <form method="post" action="<?= e(url('/admin/equipe/convites')) ?>" class="form">
            <?= csrf_field() ?>
            <label for="nome">Nome</label>
            <input id="nome" name="nome" type="text" required maxlength="160" autocomplete="off">
            <label for="email">E-mail</label>
            <input id="email" name="email" type="email" required maxlength="190" autocomplete="off">
            <button type="submit" class="btn btn-primary">Enviar convite</button>
        </form>
    </section>
</div>
