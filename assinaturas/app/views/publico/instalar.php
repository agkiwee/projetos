<?php $scripts = ['js/renderer.js', 'js/instalar.js']; ?>
<script type="application/json" id="kw-boot"><?= json_for_html($boot) ?></script>
<section class="card">
    <h1>Assinatura de <?= e($signatureName) ?></h1>
    <p class="muted"><?= e($companyName) ?> · Copie a assinatura e instale no seu programa de e-mail seguindo os passos abaixo.</p>
    <div class="share-preview">
        <iframe title="Sua assinatura de e-mail" data-preview-frame sandbox="allow-same-origin" scrolling="no"></iframe>
    </div>
    <div class="share-actions">
        <button type="button" class="btn btn-primary" data-action="copy">Copiar assinatura</button>
        <button type="button" class="btn" data-action="download">Baixar HTML</button>
    </div>
</section>
<section class="card">
    <h2>Como instalar</h2>
    <?php require KW_ROOT . '/app/views/partials/instrucoes.php'; ?>
</section>
