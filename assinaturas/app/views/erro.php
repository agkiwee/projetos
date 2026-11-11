<section class="card card-narrow center">
    <p class="error-code"><?= e((string) ($code ?? '')) ?></p>
    <h1><?= e($title ?? 'Erro') ?></h1>
    <p><?= e($message ?? '') ?></p>
    <p><a class="btn" href="<?= e(url('/')) ?>">Ir para o início</a></p>
</section>
