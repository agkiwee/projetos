<?php foreach (take_flashes() as $flash): ?>
    <div class="alert alert-<?= e($flash['type']) ?>" role="status">
        <p><?= e($flash['message']) ?></p>
        <?php if (!empty($flash['copy'])): ?>
            <div class="copy-line">
                <input type="text" readonly value="<?= e($flash['copy']) ?>" aria-label="Link do convite" data-select-on-focus>
                <button type="button" class="btn btn-small" data-copy-text="<?= e($flash['copy']) ?>">Copiar link</button>
            </div>
        <?php endif; ?>
    </div>
<?php endforeach; ?>
