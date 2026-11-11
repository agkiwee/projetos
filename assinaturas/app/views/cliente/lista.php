<?php $scripts = ['js/renderer.js', 'js/lista.js']; ?>
<script type="application/json" id="kw-boot"><?= json_for_html($boot) ?></script>
<div class="page-head">
    <div>
        <h1>Assinaturas</h1>
        <p class="muted"><?= e($company['name']) ?> · <?= (int) $total ?> assinatura(s)</p>
    </div>
    <a class="btn btn-primary" href="<?= e(url('/assinaturas/nova')) ?>">Nova assinatura</a>
</div>

<?php if (!$hasDefault): ?>
    <div class="alert alert-info">
        <p><strong>Comece pelo modelo padrão.</strong> Defina logotipo, cores e links da empresa uma única vez. Toda nova assinatura já nasce com essa identidade.</p>
        <p><a class="btn btn-small" href="<?= e(url('/padrao')) ?>">Configurar modelo padrão</a></p>
    </div>
<?php endif; ?>

<?php if ($total > 0): ?>
    <form method="get" action="<?= e(url('/assinaturas')) ?>" class="search-bar" role="search">
        <label for="q" class="sr-only">Buscar colaborador</label>
        <input id="q" name="q" type="search" placeholder="Buscar por nome, cargo, departamento ou e-mail" value="<?= e($search) ?>">
        <button type="submit" class="btn">Buscar</button>
        <?php if ($search !== ''): ?><a class="btn btn-ghost" href="<?= e(url('/assinaturas')) ?>">Limpar</a><?php endif; ?>
    </form>
<?php endif; ?>

<?php if (!$signatures): ?>
    <section class="card empty">
        <?php if ($search !== ''): ?>
            <h2>Nenhuma assinatura encontrada</h2>
            <p>Não há colaboradores com "<?= e($search) ?>". Confira a grafia ou limpe a busca.</p>
        <?php else: ?>
            <h2>Nenhuma assinatura criada ainda</h2>
            <p>Crie a assinatura do primeiro colaborador. Leva poucos minutos.</p>
            <a class="btn btn-primary" href="<?= e(url('/assinaturas/nova')) ?>">Criar primeira assinatura</a>
        <?php endif; ?>
    </section>
<?php else: ?>
    <div class="table-wrap card">
        <table class="table table-signatures">
            <thead>
            <tr><th>Colaborador</th><th>Cargo</th><th>E-mail</th><th>Atualizada em</th><th><span class="sr-only">Ações</span></th></tr>
            </thead>
            <tbody>
            <?php foreach ($signatures as $signature): ?>
                <tr>
                    <td data-label="Colaborador"><a class="row-title" href="<?= e(url('/assinaturas/' . $signature['id'])) ?>"><?= e($signature['name']) ?></a></td>
                    <td data-label="Cargo"><?= e(trim($signature['title'] . ($signature['department'] !== '' ? ' · ' . $signature['department'] : ''), ' ·')) ?></td>
                    <td data-label="E-mail"><?= e($signature['email']) ?></td>
                    <td data-label="Atualizada em"><?= e($signature['updated']) ?></td>
                    <td class="actions">
                        <a class="btn btn-small" href="<?= e(url('/assinaturas/' . $signature['id'])) ?>">Editar</a>
                        <button type="button" class="btn btn-small" data-copy-signature="<?= (int) $signature['id'] ?>">Copiar</button>
                        <details class="menu">
                            <summary class="btn btn-small" aria-label="Mais ações para <?= e($signature['name']) ?>">Mais</summary>
                            <div class="menu-items">
                                <button type="button" data-copy-text="<?= e($signature['shareUrl']) ?>" data-copy-message="Link de instalação copiado.">Copiar link de instalação</button>
                                <form method="post" action="<?= e(url('/assinaturas/' . $signature['id'] . '/duplicar')) ?>">
                                    <?= csrf_field() ?>
                                    <button type="submit">Duplicar</button>
                                </form>
                                <form method="post" action="<?= e(url('/assinaturas/' . $signature['id'] . '/excluir')) ?>" data-confirm="Excluir a assinatura de <?= e($signature['name']) ?>? Esta ação não pode ser desfeita.">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="danger">Excluir</button>
                                </form>
                            </div>
                        </details>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
