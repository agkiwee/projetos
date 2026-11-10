<?php
$scripts = ['js/renderer.js', 'js/editor.js'];
$isDefault = $mode === 'default';
$personal = ['name', 'title', 'department', 'mobile', 'whatsapp', 'email'];
$fieldMeta = [
    'name' => ['Nome', 'text', 'name'],
    'title' => ['Cargo', 'text', 'organization-title'],
    'department' => ['Departamento', 'text', 'off'],
    'company' => ['Empresa', 'text', 'organization'],
];
$contactMeta = [
    'phone' => ['Telefone', 'tel', '(11) 3000-0000'],
    'mobile' => ['Celular', 'tel', '(11) 99999-0000'],
    'whatsapp' => ['WhatsApp', 'tel', '(11) 99999-0000'],
    'email' => ['E-mail', 'email', 'nome@empresa.com.br'],
    'website' => ['Site', 'text', 'www.empresa.com.br'],
    'address' => ['Endereço', 'textarea', "Rua, número, sala\nCidade/UF, CEP"],
];
$colors = [
    'colorName' => 'Nome', 'colorTitle' => 'Cargo e destaques', 'colorText' => 'Texto',
    'colorLink' => 'Links', 'colorIcon' => 'Ícones', 'colorDivider' => 'Divisórias',
];
?>
<script type="application/json" id="kw-boot"><?= json_for_html($boot) ?></script>

<div class="editor" data-editor>
    <div class="editor-bar">
        <a class="back-link" href="<?= e(url('/assinaturas')) ?>" aria-label="Voltar para a lista de assinaturas">&larr; Assinaturas</a>
        <div class="editor-title">
            <h1 data-editor-title><?= $isDefault ? 'Modelo padrão da empresa' : e($title) ?></h1>
            <p class="save-state" data-save-state aria-live="polite"></p>
        </div>
        <div class="editor-actions">
            <?php if ($isDefault): ?>
                <button type="button" class="btn" data-action="apply-default">Aplicar às assinaturas existentes</button>
                <button type="button" class="btn btn-primary" data-action="save">Salvar modelo padrão</button>
            <?php else: ?>
                <button type="button" class="btn" data-action="help">Como instalar</button>
                <button type="button" class="btn" data-action="share">Link para o colaborador</button>
                <button type="button" class="btn" data-action="download">Baixar HTML</button>
                <button type="button" class="btn" data-action="copy">Copiar assinatura</button>
                <button type="button" class="btn btn-primary" data-action="save">Salvar</button>
            <?php endif; ?>
        </div>
    </div>

    <div class="editor-body">
        <section class="editor-panel" aria-label="Opções da assinatura">
            <div class="tab-list" role="tablist" aria-label="Seções do editor">
                <button type="button" role="tab" aria-selected="true" data-panel-tab="modelo">Modelo</button>
                <button type="button" role="tab" aria-selected="false" data-panel-tab="dados">Dados</button>
                <button type="button" role="tab" aria-selected="false" data-panel-tab="redes">Redes</button>
                <button type="button" role="tab" aria-selected="false" data-panel-tab="imagens">Imagens</button>
                <button type="button" role="tab" aria-selected="false" data-panel-tab="aparencia">Aparência</button>
            </div>

            <div class="panel-section" data-panel="modelo">
                <p class="hint">Trocar de modelo muda só a disposição. Os dados preenchidos são mantidos.</p>
                <div class="template-grid template-grid-panel" data-template-list></div>
            </div>

            <div class="panel-section" data-panel="dados" hidden>
                <?php if ($isDefault): ?>
                    <p class="hint">Aqui ficam os dados da empresa. Nome, cargo e contatos pessoais são preenchidos em cada assinatura. Use a chave ao lado de cada campo para decidir se ele aparece.</p>
                <?php endif; ?>
                <?php foreach ($fieldMeta as $key => [$label, $type, $auto]): $locked = $isDefault && in_array($key, $personal, true); ?>
                    <div class="field">
                        <label for="f-<?= $key ?>"><?= e($label) ?></label>
                        <div class="field-line">
                            <input id="f-<?= $key ?>" type="text" maxlength="160" autocomplete="<?= e($auto) ?>" data-bind="fields.<?= $key ?>.value"
                                <?= $locked ? 'disabled placeholder="Preenchido em cada assinatura"' : '' ?>>
                            <label class="switch" title="Mostrar na assinatura">
                                <input type="checkbox" data-bind="fields.<?= $key ?>.show">
                                <span class="sr-only">Mostrar <?= e(mb_strtolower($label)) ?> na assinatura</span>
                            </label>
                        </div>
                    </div>
                <?php endforeach; ?>

                <h3>Contatos</h3>
                <p class="hint">Campos vazios somem da assinatura. Use as setas para mudar a ordem.</p>
                <div data-contact-list>
                    <?php foreach ($contactMeta as $key => [$label, $type, $placeholder]): $locked = $isDefault && in_array($key, $personal, true); ?>
                        <div class="field" data-contact="<?= $key ?>">
                            <label for="f-<?= $key ?>"><?= e($label) ?></label>
                            <div class="field-line">
                                <?php if ($type === 'textarea'): ?>
                                    <textarea id="f-<?= $key ?>" rows="2" maxlength="300" data-bind="fields.<?= $key ?>.value" placeholder="<?= e($placeholder) ?>"></textarea>
                                <?php else: ?>
                                    <input id="f-<?= $key ?>" type="<?= e($type) ?>" maxlength="160" data-bind="fields.<?= $key ?>.value"
                                        <?= $locked ? 'disabled placeholder="Preenchido em cada assinatura"' : 'placeholder="' . e($placeholder) . '"' ?>>
                                <?php endif; ?>
                                <label class="switch" title="Mostrar na assinatura">
                                    <input type="checkbox" data-bind="fields.<?= $key ?>.show">
                                    <span class="sr-only">Mostrar <?= e(mb_strtolower($label)) ?> na assinatura</span>
                                </label>
                                <button type="button" class="icon-btn" data-move="<?= $key ?>" data-dir="-1" aria-label="Subir <?= e(mb_strtolower($label)) ?>">&uarr;</button>
                                <button type="button" class="icon-btn" data-move="<?= $key ?>" data-dir="1" aria-label="Descer <?= e(mb_strtolower($label)) ?>">&darr;</button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <h3>Aviso legal</h3>
                <div class="field">
                    <label for="f-disclaimer">Texto no rodapé da assinatura (opcional)</label>
                    <div class="field-line">
                        <textarea id="f-disclaimer" rows="3" maxlength="1500" data-bind="disclaimer.text" placeholder="Exemplo: esta mensagem pode conter informação confidencial."></textarea>
                        <label class="switch" title="Mostrar na assinatura">
                            <input type="checkbox" data-bind="disclaimer.show">
                            <span class="sr-only">Mostrar aviso legal na assinatura</span>
                        </label>
                    </div>
                </div>
            </div>

            <div class="panel-section" data-panel="redes" hidden>
                <p class="hint">Adicione redes sociais e outros links. Cada um aparece como um ícone clicável. Links sem endereço não aparecem.</p>
                <div data-social-list></div>
                <button type="button" class="btn" data-action="add-social">Adicionar link</button>
            </div>

            <div class="panel-section" data-panel="imagens" hidden>
                <?php
                $slots = [
                    'logo' => ['Logotipo', 'Largura', 40, 400],
                    'photo' => ['Foto do colaborador', 'Tamanho', 40, 200],
                    'banner' => ['Banner de campanha', 'Largura', 100, 600],
                ];
                foreach ($slots as $slot => [$label, $sizeLabel, $min, $max]):
                    $sizeKey = $slot === 'photo' ? 'size' : 'width';
                ?>
                    <fieldset class="image-slot" data-image-slot="<?= $slot ?>">
                        <legend><?= e($label) ?></legend>
                        <?php if ($slot === 'photo' && $isDefault): ?>
                            <p class="hint">A foto é escolhida em cada assinatura. Aqui você define o formato e o tamanho.</p>
                        <?php endif; ?>
                        <?php if ($slot === 'photo'): ?>
                            <p class="hint" data-photo-note hidden>O modelo Compacta não exibe a foto.</p>
                        <?php endif; ?>
                        <?php if ($slot === 'banner'): ?>
                            <p class="hint">Imagem opcional abaixo da assinatura, para divulgar uma campanha ou evento.</p>
                        <?php endif; ?>
                        <div class="image-row">
                            <div class="image-thumb" data-image-thumb><span>Sem imagem</span></div>
                            <div class="image-buttons">
                                <button type="button" class="btn btn-small" data-action="pick-image" data-kind="<?= $slot ?>"<?= $slot === 'photo' && $isDefault ? ' disabled' : '' ?>>Escolher imagem</button>
                                <button type="button" class="btn btn-small btn-ghost" data-action="clear-image" data-kind="<?= $slot ?>">Remover</button>
                                <label class="check"><input type="checkbox" data-bind="<?= $slot ?>.show"> Mostrar na assinatura</label>
                            </div>
                        </div>
                        <div class="field">
                            <label for="f-<?= $slot ?>-size"><?= e($sizeLabel) ?> <output data-output="<?= $slot ?>.<?= $sizeKey ?>"></output></label>
                            <input id="f-<?= $slot ?>-size" type="range" min="<?= $min ?>" max="<?= $max ?>" step="2" data-bind="<?= $slot ?>.<?= $sizeKey ?>">
                        </div>
                        <?php if ($slot === 'photo'): ?>
                            <div class="field">
                                <span class="label" id="photo-shape-label">Formato</span>
                                <div class="segmented" role="radiogroup" aria-labelledby="photo-shape-label">
                                    <label><input type="radio" name="photo-shape" value="circle" data-bind="photo.shape"><span>Redonda</span></label>
                                    <label><input type="radio" name="photo-shape" value="rounded" data-bind="photo.shape"><span>Cantos arredondados</span></label>
                                    <label><input type="radio" name="photo-shape" value="square" data-bind="photo.shape"><span>Quadrada</span></label>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="field">
                                <label for="f-<?= $slot ?>-link">Link ao clicar (opcional)</label>
                                <input id="f-<?= $slot ?>-link" type="text" inputmode="url" maxlength="500" placeholder="https://" data-bind="<?= $slot ?>.link">
                            </div>
                        <?php endif; ?>
                        <?php if ($slot === 'banner'): ?>
                            <div class="field">
                                <label for="f-banner-alt">Descrição do banner</label>
                                <input id="f-banner-alt" type="text" maxlength="120" placeholder="Texto lido quando a imagem não carrega" data-bind="banner.alt">
                            </div>
                        <?php endif; ?>
                    </fieldset>
                <?php endforeach; ?>
            </div>

            <div class="panel-section" data-panel="aparencia" hidden>
                <div class="field">
                    <label for="f-font">Fonte</label>
                    <select id="f-font" data-bind="style.font">
                        <?php foreach (SIG_FONTS as $font): ?><option value="<?= e($font) ?>"><?= e($font) ?></option><?php endforeach; ?>
                    </select>
                    <p class="hint">Só fontes presentes em todos os computadores e celulares, para a assinatura ficar igual para quem recebe.</p>
                </div>

                <h3>Cores</h3>
                <div class="color-grid">
                    <?php foreach ($colors as $key => $label): ?>
                        <div class="color-field">
                            <label for="c-<?= $key ?>"><?= e($label) ?></label>
                            <div class="color-line">
                                <input id="c-<?= $key ?>" type="color" data-bind="style.<?= $key ?>">
                                <input type="text" maxlength="7" pattern="#[0-9a-fA-F]{6}" data-bind="style.<?= $key ?>" data-hex aria-label="Código da cor de <?= e(mb_strtolower($label)) ?>">
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <h3>Tamanhos do texto</h3>
                <?php foreach (['sizeName' => ['Nome', 12, 28], 'sizeTitle' => ['Cargo', 10, 22], 'sizeText' => ['Demais textos', 10, 18]] as $key => [$label, $min, $max]): ?>
                    <div class="field">
                        <label for="s-<?= $key ?>"><?= e($label) ?> <output data-output="style.<?= $key ?>"></output></label>
                        <input id="s-<?= $key ?>" type="range" min="<?= $min ?>" max="<?= $max ?>" step="1" data-bind="style.<?= $key ?>">
                    </div>
                <?php endforeach; ?>

                <h3>Disposição</h3>
                <div class="field">
                    <span class="label" id="align-label">Alinhamento</span>
                    <div class="segmented" role="radiogroup" aria-labelledby="align-label">
                        <label><input type="radio" name="align" value="left" data-bind="style.align"><span>Esquerda</span></label>
                        <label><input type="radio" name="align" value="center" data-bind="style.align"><span>Centro</span></label>
                        <label><input type="radio" name="align" value="right" data-bind="style.align"><span>Direita</span></label>
                    </div>
                    <p class="hint">Vale para os modelos Vertical e Compacta. Nos demais o texto fica ao lado da imagem.</p>
                </div>
                <div class="field">
                    <label for="s-spacing">Espaço entre linhas <output data-output="style.spacing"></output></label>
                    <input id="s-spacing" type="range" min="0" max="24" step="1" data-bind="style.spacing">
                </div>
                <div class="field">
                    <label for="s-gap">Espaço entre colunas <output data-output="style.gap"></output></label>
                    <input id="s-gap" type="range" min="8" max="48" step="2" data-bind="style.gap">
                </div>
                <label class="check"><input type="checkbox" data-bind="style.dividers"> Mostrar linhas divisórias</label>
                <label class="check"><input type="checkbox" data-bind="style.underline"> Sublinhar links</label>

                <h3>Ícones</h3>
                <div class="field">
                    <span class="label" id="contact-style-label">Contatos</span>
                    <div class="segmented" role="radiogroup" aria-labelledby="contact-style-label">
                        <label><input type="radio" name="contact-style" value="icons" data-bind="style.contactStyle"><span>Com ícones</span></label>
                        <label><input type="radio" name="contact-style" value="labels" data-bind="style.contactStyle"><span>Com rótulos</span></label>
                        <label><input type="radio" name="contact-style" value="none" data-bind="style.contactStyle"><span>Só o texto</span></label>
                    </div>
                </div>
                <div class="field">
                    <span class="label" id="icon-shape-label">Formato dos ícones de redes</span>
                    <div class="shape-list" role="radiogroup" aria-labelledby="icon-shape-label" data-shape-list></div>
                </div>
                <div class="field">
                    <label for="s-icon">Tamanho dos ícones de redes <output data-output="style.iconSize"></output></label>
                    <input id="s-icon" type="range" min="16" max="40" step="2" data-bind="style.iconSize">
                </div>
            </div>
        </section>

        <section class="editor-preview" aria-label="Prévia da assinatura">
            <div class="preview-bar">
                <div class="segmented segmented-small" role="radiogroup" aria-label="Fundo da prévia">
                    <label><input type="radio" name="preview-bg" value="light" checked data-preview-option="bg"><span>Fundo claro</span></label>
                    <label><input type="radio" name="preview-bg" value="dark" data-preview-option="bg"><span>Fundo escuro</span></label>
                </div>
                <div class="segmented segmented-small" role="radiogroup" aria-label="Largura da prévia">
                    <label><input type="radio" name="preview-width" value="desktop" checked data-preview-option="width"><span>Computador</span></label>
                    <label><input type="radio" name="preview-width" value="mobile" data-preview-option="width"><span>Celular</span></label>
                </div>
            </div>
            <div class="preview-stage" data-preview-stage>
                <div class="preview-mail">
                    <p class="preview-fake">Olá, tudo bem?<br>Segue a proposta conforme combinamos.</p>
                    <p class="preview-fake">Atenciosamente,</p>
                    <iframe title="Prévia da assinatura" data-preview-frame sandbox="allow-same-origin" scrolling="no"></iframe>
                </div>
            </div>
            <p class="preview-meta" data-preview-meta></p>
            <?php if ($isDefault): ?>
                <p class="hint center">A prévia usa dados de exemplo no lugar do nome e dos contatos do colaborador.</p>
            <?php endif; ?>
        </section>
    </div>
</div>

<dialog id="dlg-image" class="dialog dialog-wide" aria-labelledby="dlg-image-title">
    <h2 id="dlg-image-title">Escolher imagem</h2>
    <div class="tabs" data-tabs>
        <div class="tab-list" role="tablist" aria-label="Origem da imagem">
            <button type="button" role="tab" aria-selected="true" data-tab="upload">Enviar arquivo</button>
            <button type="button" role="tab" aria-selected="false" data-tab="url">Usar um link</button>
            <button type="button" role="tab" aria-selected="false" data-tab="library">Arquivos da empresa</button>
        </div>
        <div role="tabpanel" data-tab-panel="upload">
            <p class="muted">PNG, JPG ou GIF com até 4 MB. O arquivo fica hospedado na plataforma em um endereço fixo.</p>
            <label class="dropzone" for="upload-input">
                <input id="upload-input" type="file" accept="image/png,image/jpeg,image/gif,image/webp" data-upload-input>
                <span><strong>Clique para escolher</strong> ou arraste a imagem até aqui</span>
            </label>
        </div>
        <div role="tabpanel" data-tab-panel="url" hidden>
            <p class="muted">Cole o endereço de uma imagem pública, com https. Funciona com arquivos do Google Drive compartilhados com "Qualquer pessoa com o link" e com imagens do site da empresa.</p>
            <div class="field">
                <label for="url-input">Endereço da imagem</label>
                <input id="url-input" type="url" inputmode="url" placeholder="https://www.empresa.com.br/imagens/logo.png" data-url-input>
                <p class="hint">Enviou o arquivo por FTP? Use o endereço público dele, por exemplo https://www.seusite.com.br/pasta/logo.png, e não o endereço ftp://.</p>
            </div>
            <label class="check"><input type="checkbox" data-url-copy checked> Guardar uma cópia na plataforma (recomendado)</label>
            <p class="hint">Com a cópia, a imagem continua aparecendo nos e-mails mesmo se o arquivo original for movido ou apagado.</p>
            <button type="button" class="btn btn-primary" data-action="check-url">Verificar e usar</button>
        </div>
        <div role="tabpanel" data-tab-panel="library" hidden>
            <p class="muted">Imagens já enviadas pela empresa. Substituir um arquivo mantém o mesmo endereço e atualiza as assinaturas já instaladas, sem reinstalar. O Outlook clássico é a exceção.</p>
            <div class="asset-grid" data-asset-grid></div>
            <input type="file" accept="image/png,image/jpeg,image/gif,image/webp" data-replace-input hidden>
        </div>
    </div>
    <div class="alert alert-error" role="alert" data-image-error hidden></div>
    <p class="busy" data-image-busy hidden>Processando a imagem...</p>
    <div class="dialog-actions">
        <button type="button" class="btn btn-ghost" data-close-dialog>Fechar</button>
    </div>
</dialog>

<dialog id="dlg-help" class="dialog dialog-wide" aria-labelledby="dlg-help-title">
    <h2 id="dlg-help-title">Como instalar a assinatura</h2>
    <?php require KW_ROOT . '/app/views/partials/instrucoes.php'; ?>
    <div class="dialog-actions">
        <button type="button" class="btn btn-ghost" data-close-dialog>Fechar</button>
        <button type="button" class="btn btn-primary" data-action="copy">Copiar assinatura</button>
    </div>
</dialog>

<dialog id="dlg-share" class="dialog" aria-labelledby="dlg-share-title">
    <h2 id="dlg-share-title">Link para o colaborador</h2>
    <p class="muted">Envie este link ao colaborador. Ele abre a assinatura pronta, copia e instala seguindo o passo a passo, sem precisar de senha. O link sempre mostra a última versão salva.</p>
    <div class="copy-line">
        <input type="text" readonly data-share-url aria-label="Link de instalação" data-select-on-focus>
        <button type="button" class="btn btn-primary" data-action="copy-share">Copiar link</button>
    </div>
    <div class="dialog-actions">
        <button type="button" class="btn btn-ghost" data-close-dialog>Fechar</button>
    </div>
</dialog>
