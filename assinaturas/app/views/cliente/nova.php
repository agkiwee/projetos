<?php $scripts = ['js/renderer.js', 'js/galeria.js']; ?>
<script type="application/json" id="kw-boot"><?= json_for_html($boot) ?></script>
<p class="breadcrumb"><a href="<?= e(url('/assinaturas')) ?>">Assinaturas</a> <span aria-hidden="true">/</span> Nova assinatura</p>
<div class="page-head">
    <div>
        <h1>Nova assinatura</h1>
        <p class="muted">Escolha por onde começar. Você pode trocar de modelo depois sem perder os dados preenchidos.</p>
    </div>
</div>

<section class="card">
    <h2>Padrão da empresa</h2>
    <?php if (!$hasDefault): ?>
        <p class="muted">O modelo padrão ainda não foi configurado. Você pode <a href="<?= e(url('/padrao')) ?>">configurar agora</a> ou começar com o modelo básico.</p>
    <?php else: ?>
        <p class="muted">Usa o modelo, o logotipo, as cores e os links já definidos para <?= e($company['name']) ?>.</p>
    <?php endif; ?>
    <a class="template-card template-card-wide" href="<?= e(url('/assinaturas/nova?modelo=padrao')) ?>">
        <span class="template-preview" data-sig-preview="padrao"></span>
        <span class="template-name">Usar o padrão da empresa</span>
    </a>
</section>

<section class="card">
    <h2>Ou escolha outro modelo</h2>
    <p class="muted">A identidade da empresa é mantida. Muda apenas a disposição dos elementos.</p>
    <div class="template-grid" data-sig-gallery></div>
</section>
