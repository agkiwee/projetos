<div class="page-head">
    <div>
        <h1>Empresas</h1>
        <p class="muted">Cadastre as empresas clientes e convide quem vai cuidar das assinaturas de cada uma.</p>
    </div>
    <button type="button" class="btn btn-primary" data-open-dialog="nova-empresa">Nova empresa</button>
</div>

<form method="get" action="<?= e(url('/admin')) ?>" class="search-bar" role="search">
    <label for="q" class="sr-only">Buscar empresa</label>
    <input id="q" name="q" type="search" placeholder="Buscar empresa pelo nome" value="<?= e($search) ?>">
    <button type="submit" class="btn">Buscar</button>
    <?php if ($search !== ''): ?><a class="btn btn-ghost" href="<?= e(url('/admin')) ?>">Limpar</a><?php endif; ?>
</form>

<?php if (!$companies): ?>
    <section class="card empty">
        <?php if ($search !== ''): ?>
            <h2>Nenhuma empresa encontrada</h2>
            <p>Não há empresas com "<?= e($search) ?>" no nome.</p>
        <?php else: ?>
            <h2>Nenhuma empresa cadastrada ainda</h2>
            <p>Cadastre a primeira empresa e envie o convite para o responsável.</p>
            <button type="button" class="btn btn-primary" data-open-dialog="nova-empresa">Cadastrar empresa</button>
        <?php endif; ?>
    </section>
<?php else: ?>
    <div class="table-wrap card">
        <table class="table">
            <thead>
            <tr><th>Empresa</th><th>Situação</th><th class="num">Usuários</th><th class="num">Convites pendentes</th><th class="num">Assinaturas</th><th>Cadastro</th><th><span class="sr-only">Ações</span></th></tr>
            </thead>
            <tbody>
            <?php foreach ($companies as $company): ?>
                <tr>
                    <td data-label="Empresa"><a class="row-title" href="<?= e(url('/admin/empresas/' . $company['id'])) ?>"><?= e($company['name']) ?></a></td>
                    <td data-label="Situação"><span class="badge badge-<?= $company['status'] === 'active' ? 'ok' : 'off' ?>"><?= $company['status'] === 'active' ? 'Ativa' : 'Suspensa' ?></span></td>
                    <td data-label="Usuários" class="num"><?= (int) $company['user_count'] ?></td>
                    <td data-label="Convites pendentes" class="num"><?= (int) $company['invite_count'] ?></td>
                    <td data-label="Assinaturas" class="num"><?= (int) $company['signature_count'] ?></td>
                    <td data-label="Cadastro"><?= e(format_date($company['created_at'], 'd/m/Y')) ?></td>
                    <td class="actions"><a class="btn btn-small" href="<?= e(url('/admin/empresas/' . $company['id'])) ?>">Gerenciar</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<dialog id="nova-empresa" class="dialog" aria-labelledby="nova-empresa-titulo">
    <form method="post" action="<?= e(url('/admin/empresas')) ?>" class="form">
        <?= csrf_field() ?>
        <h2 id="nova-empresa-titulo">Nova empresa</h2>
        <label for="empresa">Nome da empresa</label>
        <input id="empresa" name="empresa" type="text" required maxlength="160">
        <fieldset>
            <legend>Primeiro usuário (opcional)</legend>
            <p class="hint">Ele recebe um convite por e-mail para criar a senha. Você também pode convidar depois.</p>
            <label for="nome">Nome</label>
            <input id="nome" name="nome" type="text" maxlength="160" autocomplete="off">
            <label for="email">E-mail</label>
            <input id="email" name="email" type="email" maxlength="190" autocomplete="off">
        </fieldset>
        <div class="dialog-actions">
            <button type="button" class="btn btn-ghost" data-close-dialog>Cancelar</button>
            <button type="submit" class="btn btn-primary">Cadastrar empresa</button>
        </div>
    </form>
</dialog>
