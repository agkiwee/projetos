/* KIWEE Assinaturas: editor com prévia em tempo real. */
(function () {
  'use strict';

  var boot = KW.bootData = KW.boot();
  var R = KWRender;
  var root = document.querySelector('[data-editor]');
  if (!root) { return; }

  var mode = boot.mode;                       // "signature" ou "default"
  var state = R.normalize(boot.data);         // dados em edição
  var signature = boot.signature || null;     // { id, name, shareUrl } depois de salva
  var dirty = false;
  var saving = false;
  var revision = 0;                           // cresce a cada alteração, para saber o que já foi gravado
  var frame = root.querySelector('[data-preview-frame]');
  var meta = root.querySelector('[data-preview-meta]');
  var saveState = root.querySelector('[data-save-state]');
  var miniTimer = null;
  var colorTimer = null;

  // Redes mais usadas primeiro; as demais seguem em ordem alfabética.
  var COMMON = ['linkedin', 'instagram', 'facebook', 'whatsapp', 'youtube', 'x', 'tiktok', 'website', 'link'];

  /* ------------------------------ Estado ------------------------------ */

  function getPath(path) {
    return path.split('.').reduce(function (value, key) { return value == null ? undefined : value[key]; }, state);
  }

  function setPath(path, value) {
    var keys = path.split('.');
    var target = state;
    for (var i = 0; i < keys.length - 1; i++) { target = target[keys[i]]; }
    target[keys[keys.length - 1]] = value;
  }

  function changed() {
    dirty = true;
    revision++;
    saveState.textContent = 'Alterações não salvas';
    saveState.className = 'save-state save-state-dirty';
    renderPreview();
  }

  function resolveImage(url) { return R.imageUrl(url, boot.baseUrl); }

  /* ------------------------------ Prévia ------------------------------ */

  function previewData() { return mode === 'default' ? R.withSamples(state) : state; }

  function renderPreview() {
    var result = R.render(previewData(), { baseUrl: boot.baseUrl });
    var html = result.html || '<p style="font-family:Arial,sans-serif;font-size:13px;color:#8a8f8b;margin:0;">Preencha os dados para ver a assinatura.</p>';
    KW.mountPreview(frame, html);
    var size = result.length.toLocaleString('pt-BR');
    if (result.length > R.GMAIL_LIMIT) {
      meta.textContent = 'Tamanho: ' + size + ' caracteres. Passa do limite de 10.000 do Gmail. Remova links, o aviso legal ou campos para reduzir.';
      meta.className = 'preview-meta preview-meta-warn';
    } else {
      meta.textContent = 'Tamanho: ' + size + ' de 10.000 caracteres aceitos pelo Gmail.';
      meta.className = 'preview-meta';
    }
    updateOutputs();
    updateThumbs();
    clearTimeout(miniTimer);
    miniTimer = setTimeout(renderMinis, 250);
  }

  /* ----------------------- Campos ligados ao estado ----------------------- */

  function fillInputs() {
    Array.prototype.forEach.call(root.querySelectorAll('[data-bind]'), function (input) {
      var value = getPath(input.getAttribute('data-bind'));
      if (input.type === 'checkbox') {
        input.checked = value === true;
      } else if (input.type === 'radio') {
        input.checked = String(value) === input.value;
      } else if (!(input.disabled && input.hasAttribute('placeholder') && mode === 'default')) {
        input.value = value == null ? '' : value;
      }
    });
  }

  function updateOutputs() {
    Array.prototype.forEach.call(root.querySelectorAll('[data-output]'), function (output) {
      output.textContent = getPath(output.getAttribute('data-output')) + ' px';
    });
  }

  function onInput(event) {
    var input = event.target;
    if (!input.hasAttribute || !input.hasAttribute('data-bind')) { return; }
    var path = input.getAttribute('data-bind');
    var value;
    if (input.type === 'checkbox') {
      value = input.checked;
    } else if (input.type === 'radio') {
      if (!input.checked) { return; }
      value = input.value;
    } else if (input.type === 'range' || input.type === 'number') {
      value = Number(input.value);
    } else if (input.type === 'color' || input.hasAttribute('data-hex')) {
      value = input.value.trim();
      if (/^[0-9a-fA-F]{6}$/.test(value)) { value = '#' + value; }
      if (!/^#[0-9a-fA-F]{6}$/.test(value)) { return; } // espera o código completo da cor
      value = value.toLowerCase();
      // Mantém o seletor de cor e o campo de código iguais.
      Array.prototype.forEach.call(root.querySelectorAll('[data-bind="' + path + '"]'), function (other) {
        if (other !== input) { other.value = value; }
      });
    } else {
      value = input.value;
    }
    setPath(path, value);
    if (input.type === 'color') {
      // Arrastar o seletor de cor dispara muitos eventos: espera a cor parar antes de redesenhar.
      clearTimeout(colorTimer);
      colorTimer = setTimeout(function () { afterColor(path); }, 220);
      return;
    }
    afterColor(path);
  }

  function afterColor(path) {
    if (path === 'style.colorIcon') { renderShapes(); renderSocial(); }
    changed();
  }

  /* ------------------------------- Modelos ------------------------------- */

  function buildTemplates() {
    var list = root.querySelector('[data-template-list]');
    R.TEMPLATES.forEach(function (template) {
      var card = document.createElement('button');
      card.type = 'button';
      card.className = 'template-card';
      card.setAttribute('data-template', template.id);
      var holder = document.createElement('span');
      holder.className = 'template-preview';
      holder.setAttribute('data-max-height', '120');
      var mini = document.createElement('iframe');
      mini.setAttribute('sandbox', 'allow-same-origin');
      mini.setAttribute('scrolling', 'no');
      mini.setAttribute('tabindex', '-1');
      mini.setAttribute('aria-hidden', 'true');
      mini.title = 'Prévia do modelo ' + template.name;
      holder.appendChild(mini);
      var name = document.createElement('span');
      name.className = 'template-name';
      name.textContent = template.name;
      var hint = document.createElement('span');
      hint.className = 'template-hint';
      hint.textContent = template.hint;
      card.appendChild(holder);
      card.appendChild(name);
      card.appendChild(hint);
      card.addEventListener('click', function () {
        state.template = template.id;
        markTemplate();
        changed();
      });
      list.appendChild(card);
    });
    markTemplate();
  }

  function markTemplate() {
    Array.prototype.forEach.call(root.querySelectorAll('[data-template]'), function (card) {
      card.setAttribute('aria-pressed', card.getAttribute('data-template') === state.template ? 'true' : 'false');
    });
    var note = root.querySelector('[data-photo-note]');
    if (note) { note.hidden = state.template !== 'compacta'; }
  }

  function renderMinis() {
    var panel = root.querySelector('[data-panel="modelo"]');
    if (!panel || panel.hidden) { return; }
    var base = R.withSamples(state);
    Array.prototype.forEach.call(root.querySelectorAll('[data-template]'), function (card) {
      var data = JSON.parse(JSON.stringify(base));
      data.template = card.getAttribute('data-template');
      var holder = card.querySelector('.template-preview');
      var mini = card.querySelector('iframe');
      KW.mountPreview(mini, R.render(data, { baseUrl: boot.baseUrl }).html, {
        fit: false,
        onSize: function (width, height) { KW.fitMini(holder, mini, width, height); }
      });
    });
  }

  /* --------------------------- Ordem dos contatos --------------------------- */

  function orderContacts() {
    var list = root.querySelector('[data-contact-list]');
    state.order.forEach(function (key, index) {
      var row = list.querySelector('[data-contact="' + key + '"]');
      if (!row) { return; }
      list.appendChild(row);
      row.querySelector('[data-dir="-1"]').disabled = index === 0;
      row.querySelector('[data-dir="1"]').disabled = index === state.order.length - 1;
    });
  }

  function moveContact(key, direction) {
    var index = state.order.indexOf(key);
    var next = index + direction;
    if (index < 0 || next < 0 || next >= state.order.length) { return; }
    state.order.splice(index, 1);
    state.order.splice(next, 0, key);
    orderContacts();
    var button = root.querySelector('[data-contact="' + key + '"] [data-dir="' + direction + '"]');
    if (button && !button.disabled) { button.focus(); }
    changed();
  }

  /* ------------------------------ Redes e links ------------------------------ */

  function iconOptions() {
    var names = (boot.icons || []).slice();
    var rest = names.filter(function (name) { return COMMON.indexOf(name) < 0; }).sort(function (a, b) {
      return (R.ICON_LABELS[a] || a).localeCompare(R.ICON_LABELS[b] || b, 'pt-BR');
    });
    return COMMON.filter(function (name) { return names.indexOf(name) >= 0; }).concat(rest);
  }

  function iconSrc(type) {
    return R.iconUrl(boot.baseUrl, state.style.iconShape, state.style.colorIcon, type);
  }

  function renderSocial() {
    var list = root.querySelector('[data-social-list]');
    var focused = document.activeElement && document.activeElement.getAttribute ? document.activeElement.getAttribute('data-social-focus') : null;
    list.textContent = '';
    if (!state.social.length) {
      var empty = document.createElement('p');
      empty.className = 'muted';
      empty.textContent = 'Nenhum link adicionado.';
      list.appendChild(empty);
    }
    state.social.forEach(function (item, index) {
      var row = document.createElement('div');
      row.className = 'social-row';

      var thumb = document.createElement('span');
      thumb.className = 'social-thumb';
      var image = document.createElement('img');
      image.alt = '';
      image.width = 28;
      image.height = 28;
      var source = item.type === 'custom' ? resolveImage(item.iconUrl) : iconSrc(item.type);
      if (source) { image.src = source; thumb.appendChild(image); }
      row.appendChild(thumb);

      var select = document.createElement('select');
      select.setAttribute('aria-label', 'Tipo do link ' + (index + 1));
      iconOptions().forEach(function (name) {
        var option = document.createElement('option');
        option.value = name;
        option.textContent = R.ICON_LABELS[name] || name;
        select.appendChild(option);
      });
      var custom = document.createElement('option');
      custom.value = 'custom';
      custom.textContent = 'Ícone próprio';
      select.appendChild(custom);
      select.value = item.type;
      select.addEventListener('change', function () {
        item.type = select.value;
        renderSocial();
        changed();
      });
      row.appendChild(select);

      var url = document.createElement('input');
      url.type = 'text';
      url.inputMode = 'url';
      url.maxLength = 500;
      url.placeholder = 'https://';
      url.value = item.url;
      url.setAttribute('aria-label', 'Endereço do link ' + (index + 1));
      url.setAttribute('data-social-focus', 'url-' + index);
      url.addEventListener('input', function () { item.url = url.value; changed(); });
      row.appendChild(url);

      var controls = document.createElement('div');
      controls.className = 'social-controls';

      if (item.type === 'custom') {
        var pick = document.createElement('button');
        pick.type = 'button';
        pick.className = 'btn btn-small';
        pick.textContent = item.iconUrl ? 'Trocar ícone' : 'Escolher ícone';
        pick.addEventListener('click', function () {
          openPicker('icon', function (picked) {
            item.iconUrl = picked.url;
            renderSocial();
            changed();
          });
        });
        controls.appendChild(pick);
        var label = document.createElement('input');
        label.type = 'text';
        label.maxLength = 60;
        label.placeholder = 'Nome do link';
        label.value = item.label;
        label.className = 'social-label';
        label.setAttribute('aria-label', 'Nome do link ' + (index + 1));
        label.setAttribute('data-social-focus', 'label-' + index);
        label.addEventListener('input', function () { item.label = label.value; changed(); });
        controls.appendChild(label);
      }

      var show = document.createElement('label');
      show.className = 'switch';
      show.title = 'Mostrar na assinatura';
      var check = document.createElement('input');
      check.type = 'checkbox';
      check.checked = item.show;
      check.addEventListener('change', function () { item.show = check.checked; changed(); });
      var showText = document.createElement('span');
      showText.className = 'sr-only';
      showText.textContent = 'Mostrar link ' + (index + 1) + ' na assinatura';
      show.appendChild(check);
      show.appendChild(showText);
      controls.appendChild(show);

      [['↑', -1, 'Subir'], ['↓', 1, 'Descer']].forEach(function (spec) {
        var button = document.createElement('button');
        button.type = 'button';
        button.className = 'icon-btn';
        button.textContent = spec[0];
        button.setAttribute('aria-label', spec[2] + ' link ' + (index + 1));
        button.disabled = index + spec[1] < 0 || index + spec[1] >= state.social.length;
        button.addEventListener('click', function () {
          state.social.splice(index, 1);
          state.social.splice(index + spec[1], 0, item);
          renderSocial();
          changed();
        });
        controls.appendChild(button);
      });

      var remove = document.createElement('button');
      remove.type = 'button';
      remove.className = 'icon-btn icon-btn-danger';
      remove.textContent = '×';
      remove.setAttribute('aria-label', 'Remover link ' + (index + 1));
      remove.addEventListener('click', function () {
        state.social.splice(index, 1);
        renderSocial();
        changed();
      });
      controls.appendChild(remove);

      row.appendChild(controls);
      list.appendChild(row);
    });
    if (focused) {
      var again = list.querySelector('[data-social-focus="' + focused + '"]');
      if (again) { again.focus(); }
    }
    var add = root.querySelector('[data-action="add-social"]');
    add.disabled = state.social.length >= 12;
  }

  function addSocial() {
    var used = state.social.map(function (item) { return item.type; });
    var next = iconOptions().filter(function (name) { return used.indexOf(name) < 0; })[0] || 'link';
    state.social.push({ type: next, url: '', label: '', iconUrl: '', show: true });
    renderSocial();
    var inputs = root.querySelectorAll('[data-social-list] input[type="text"]');
    if (inputs.length) { inputs[inputs.length - 1].focus(); }
    changed();
  }

  function renderShapes() {
    var list = root.querySelector('[data-shape-list]');
    list.textContent = '';
    R.ICON_SHAPES.forEach(function (shape) {
      var label = document.createElement('label');
      label.className = 'shape-option';
      var radio = document.createElement('input');
      radio.type = 'radio';
      radio.name = 'icon-shape';
      radio.value = shape.id;
      radio.checked = state.style.iconShape === shape.id;
      radio.setAttribute('data-bind', 'style.iconShape');
      var image = document.createElement('img');
      image.alt = '';
      image.width = 28;
      image.height = 28;
      image.src = R.iconUrl(boot.baseUrl, shape.id, state.style.colorIcon, 'linkedin');
      var name = document.createElement('span');
      name.textContent = shape.name;
      label.appendChild(radio);
      label.appendChild(image);
      label.appendChild(name);
      list.appendChild(label);
    });
  }

  /* -------------------------------- Imagens -------------------------------- */

  function updateThumbs() {
    ['logo', 'photo', 'banner'].forEach(function (kind) {
      var slot = root.querySelector('[data-image-slot="' + kind + '"]');
      if (!slot) { return; }
      var thumb = slot.querySelector('[data-image-thumb]');
      var source = resolveImage(state[kind].url);
      var current = thumb.getAttribute('data-src') || '';
      if (source === current) { return; }
      thumb.setAttribute('data-src', source);
      thumb.textContent = '';
      if (source) {
        var image = document.createElement('img');
        image.src = source;
        image.alt = 'Imagem atual';
        thumb.appendChild(image);
      } else {
        var empty = document.createElement('span');
        empty.textContent = 'Sem imagem';
        thumb.appendChild(empty);
      }
    });
  }

  function applyImage(kind, image) {
    if (kind === 'logo') {
      state.logo.url = image.url;
      state.logo.ratio = image.ratio || 0;
      state.logo.show = true;
    } else if (kind === 'photo') {
      state.photo.url = image.url;
      state.photo.urlCircle = image.urlCircle || '';
      state.photo.show = true;
    } else if (kind === 'banner') {
      state.banner.url = image.url;
      state.banner.ratio = image.ratio || 0;
      state.banner.show = true;
    }
    fillInputs();
    changed();
  }

  var picker = {
    dialog: document.getElementById('dlg-image'),
    kind: 'logo',
    onPick: null
  };
  var KIND_TITLES = { logo: 'Escolher logotipo', photo: 'Escolher foto', banner: 'Escolher banner', icon: 'Escolher ícone' };
  var KIND_NAMES = { logo: 'Logotipo', photo: 'Foto', banner: 'Banner', icon: 'Ícone' };

  function pickerError(message) {
    var box = picker.dialog.querySelector('[data-image-error]');
    box.hidden = !message;
    box.textContent = message || '';
  }

  function pickerBusy(on) {
    picker.dialog.querySelector('[data-image-busy]').hidden = !on;
    Array.prototype.forEach.call(picker.dialog.querySelectorAll('button, input'), function (control) {
      if (!control.hasAttribute('data-close-dialog')) { control.disabled = on; }
    });
  }

  function openPicker(kind, onPick) {
    picker.kind = kind;
    picker.onPick = onPick || function (image) { applyImage(kind, image); };
    picker.dialog.querySelector('#dlg-image-title').textContent = KIND_TITLES[kind];
    picker.dialog.querySelector('[data-url-input]').value = '';
    pickerError('');
    pickerBusy(false);
    KW.activateTab(picker.dialog.querySelector('[data-tabs]'), 'upload');
    picker.dialog.showModal();
  }

  function picked(image) {
    picker.dialog.close();
    picker.onPick(image);
  }

  function uploadFile(file, replaceId) {
    if (!file) { return Promise.resolve(); }
    if (file.size > 4 * 1024 * 1024) {
      pickerError('O arquivo tem mais de 4 MB. Reduza a imagem e envie de novo.');
      return Promise.resolve();
    }
    var form = new FormData();
    form.append('arquivo', file);
    form.append('tipo', picker.kind);
    if (replaceId) { form.append('substituir', replaceId); }
    pickerError('');
    pickerBusy(true);
    return KW.api('/api/arquivos', { body: form }).then(function (result) {
      pickerBusy(false);
      return result.asset;
    }, function (error) {
      pickerBusy(false);
      pickerError(error.message);
      return null;
    });
  }

  function checkUrl() {
    var input = picker.dialog.querySelector('[data-url-input]');
    var value = input.value.trim();
    if (!value) {
      pickerError('Cole o endereço da imagem.');
      input.focus();
      return;
    }
    pickerError('');
    pickerBusy(true);
    KW.api('/api/imagem-url', {
      body: { url: value, tipo: picker.kind, copiar: picker.dialog.querySelector('[data-url-copy]').checked }
    }).then(function (result) {
      pickerBusy(false);
      if (result.warning) { KW.toast(result.warning, 'warning'); }
      picked(result.image);
    }, function (error) {
      pickerBusy(false);
      pickerError(error.message);
    });
  }

  function loadLibrary() {
    var grid = picker.dialog.querySelector('[data-asset-grid]');
    grid.textContent = 'Carregando...';
    KW.api('/api/arquivos').then(function (result) {
      grid.textContent = '';
      if (!result.assets.length) {
        grid.textContent = 'Nenhuma imagem enviada ainda. Use a aba Enviar arquivo.';
        return;
      }
      result.assets.forEach(function (asset) {
        var card = document.createElement('div');
        card.className = 'asset-card';
        var image = document.createElement('img');
        image.src = resolveImage(asset.url) + '?v=' + asset.version;
        image.alt = asset.name || 'Imagem';
        image.loading = 'lazy';
        var info = document.createElement('p');
        info.className = 'hint';
        info.textContent = KIND_NAMES[asset.kind] + ' · ' + asset.width + ' x ' + asset.height;
        var actions = document.createElement('div');
        actions.className = 'asset-actions';
        var use = document.createElement('button');
        use.type = 'button';
        use.className = 'btn btn-small btn-primary';
        use.textContent = 'Usar';
        use.addEventListener('click', function () { picked(asset); });
        var replace = document.createElement('button');
        replace.type = 'button';
        replace.className = 'btn btn-small';
        replace.textContent = 'Substituir';
        replace.addEventListener('click', function () {
          var input = picker.dialog.querySelector('[data-replace-input]');
          input.value = '';
          input.onchange = function () {
            var previousKind = picker.kind;
            picker.kind = asset.kind;
            uploadFile(input.files[0], asset.id).then(function (updated) {
              picker.kind = previousKind;
              if (!updated) { return; }
              // Recarrega o arquivo no navegador para a prévia mostrar a nova imagem.
              fetch(resolveImage(updated.url), { cache: 'reload' }).catch(function () {}).then(function () {
                KW.toast('Arquivo substituído. O endereço continua o mesmo.');
                loadLibrary();
                frame.kwReady = false;
                root.querySelector('[data-image-slot="' + asset.kind + '"] [data-image-thumb]') &&
                  root.querySelector('[data-image-slot="' + asset.kind + '"] [data-image-thumb]').removeAttribute('data-src');
                renderPreview();
              });
            });
          };
          input.click();
        });
        var remove = document.createElement('button');
        remove.type = 'button';
        remove.className = 'btn btn-small btn-danger';
        remove.textContent = 'Excluir';
        remove.addEventListener('click', function () {
          if (!window.confirm('Excluir este arquivo? Assinaturas que usam esta imagem, inclusive as já instaladas, vão ficar sem ela.')) { return; }
          KW.api('/api/arquivos/' + asset.id + '/excluir', { method: 'POST' }).then(loadLibrary, function (error) { pickerError(error.message); });
        });
        actions.appendChild(use);
        actions.appendChild(replace);
        actions.appendChild(remove);
        card.appendChild(image);
        card.appendChild(info);
        card.appendChild(actions);
        grid.appendChild(card);
      });
    }, function (error) {
      grid.textContent = error.message;
    });
  }

  /* ------------------------------ Salvar e exportar ------------------------------ */

  function showPanel(name) {
    Array.prototype.forEach.call(root.querySelectorAll('[data-panel-tab]'), function (tab) {
      tab.setAttribute('aria-selected', tab.getAttribute('data-panel-tab') === name ? 'true' : 'false');
    });
    Array.prototype.forEach.call(root.querySelectorAll('[data-panel]'), function (panel) {
      panel.hidden = panel.getAttribute('data-panel') !== name;
    });
    if (name === 'modelo') { renderMinis(); }
  }

  function markSaved() {
    dirty = false;
    var time = new Date().toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
    saveState.textContent = 'Salvo às ' + time;
    saveState.className = 'save-state save-state-ok';
  }

  function save() {
    if (saving) { return Promise.resolve(false); }
    if (mode === 'signature' && !state.fields.name.value.trim()) {
      showPanel('dados');
      var nameInput = document.getElementById('f-name');
      nameInput.focus();
      KW.toast('Preencha o nome do colaborador antes de salvar.', 'error');
      return Promise.resolve(false);
    }
    saving = true;
    saveState.textContent = 'Salvando...';
    saveState.className = 'save-state';
    var sentRevision = revision;
    var path = mode === 'default' ? '/api/padrao' : (signature ? '/api/assinaturas/' + signature.id : '/api/assinaturas');
    return KW.api(path, { body: { data: state } }).then(function (result) {
      saving = false;
      // Os dados em edição não são trocados pela resposta: os campos da tela continuam ligados a eles,
      // e o que foi digitado enquanto o servidor respondia não se perde.
      if (mode === 'signature') {
        if (!signature) {
          signature = { id: result.id, name: result.name, shareUrl: result.shareUrl };
          window.history.replaceState(null, '', result.editUrl);
        }
        signature.name = result.name;
        root.querySelector('[data-editor-title]').textContent = result.name;
        document.title = result.name + ' · KIWEE Assinaturas';
      }
      if (revision === sentRevision) {
        markSaved();
      } else {
        saveState.textContent = 'Alterações não salvas';
        saveState.className = 'save-state save-state-dirty';
      }
      KW.toast(mode === 'default' ? 'Modelo padrão salvo.' : 'Assinatura salva.');
      return true;
    }, function (error) {
      saving = false;
      saveState.textContent = 'Não foi possível salvar';
      saveState.className = 'save-state save-state-dirty';
      KW.toast(error.message, 'error');
      return false;
    });
  }

  function copySignature() {
    var result = R.render(state, { baseUrl: boot.baseUrl });
    if (!result.html) {
      KW.toast('Preencha os dados da assinatura antes de copiar.', 'error');
      return;
    }
    KW.copyRich(result).then(function (ok) {
      if (!ok) {
        KW.toast('Não foi possível copiar. Use Baixar HTML.', 'error');
      } else if (dirty) {
        KW.toast('Assinatura copiada. Lembre de salvar as alterações.', 'warning');
      } else {
        KW.toast('Assinatura copiada. Agora cole no seu programa de e-mail.');
      }
    });
  }

  function downloadSignature() {
    if (!R.render(state, { baseUrl: boot.baseUrl }).html) {
      KW.toast('Preencha os dados da assinatura antes de baixar.', 'error');
      return;
    }
    KW.download('assinatura-' + KW.slug(state.fields.name.value) + '.html', R.documentHtml(state, { baseUrl: boot.baseUrl }));
  }

  function shareLink() {
    if (!signature) {
      KW.toast('Salve a assinatura para gerar o link do colaborador.', 'error');
      return;
    }
    var dialog = document.getElementById('dlg-share');
    dialog.querySelector('[data-share-url]').value = signature.shareUrl;
    dialog.showModal();
    if (dirty) { KW.toast('Há alterações não salvas. O link mostra a última versão salva.', 'warning'); }
  }

  function applyDefault() {
    var count = boot.signatureCount || 0;
    if (!count) {
      KW.toast('Ainda não há assinaturas nesta empresa.', 'warning');
      return;
    }
    var question = 'Aplicar o modelo padrão a ' + count + ' assinatura(s)?\n\n' +
      'Modelo, cores, fontes, logotipo, redes, banner e dados da empresa serão trocados em todas. ' +
      'Nome, cargo, contatos e foto de cada colaborador serão mantidos.\n\n' +
      'Quem já instalou a assinatura precisa instalar de novo para ver as mudanças.';
    if (!window.confirm(question)) { return; }
    var ready = dirty ? save() : Promise.resolve(true);
    ready.then(function (ok) {
      if (!ok) { return; }
      KW.api('/api/padrao/aplicar', { method: 'POST', body: {} }).then(function (result) {
        KW.toast('Modelo padrão aplicado a ' + result.updated + ' assinatura(s).');
      }, function (error) { KW.toast(error.message, 'error'); });
    });
  }

  /* -------------------------------- Eventos -------------------------------- */

  root.addEventListener('input', onInput);
  root.addEventListener('change', onInput);

  document.addEventListener('click', function (event) {
    var tab = event.target.closest('[data-panel-tab]');
    if (tab) { showPanel(tab.getAttribute('data-panel-tab')); return; }

    var move = event.target.closest('[data-move]');
    if (move) { moveContact(move.getAttribute('data-move'), Number(move.getAttribute('data-dir'))); return; }

    var imageTab = event.target.closest('#dlg-image [data-tab]');
    if (imageTab) {
      pickerError('');
      if (imageTab.getAttribute('data-tab') === 'library') { loadLibrary(); }
    }

    var button = event.target.closest('[data-action]');
    if (!button) { return; }
    var action = button.getAttribute('data-action');
    if (action === 'save') { save(); }
    else if (action === 'copy') { copySignature(); }
    else if (action === 'download') { downloadSignature(); }
    else if (action === 'share') { shareLink(); }
    else if (action === 'help') { document.getElementById('dlg-help').showModal(); }
    else if (action === 'copy-share') {
      KW.copyText(signature.shareUrl).then(function (ok) { KW.toast(ok ? 'Link copiado.' : 'Não foi possível copiar o link.', ok ? 'success' : 'error'); });
    }
    else if (action === 'apply-default') { applyDefault(); }
    else if (action === 'add-social') { addSocial(); }
    else if (action === 'pick-image') { openPicker(button.getAttribute('data-kind')); }
    else if (action === 'clear-image') {
      var kind = button.getAttribute('data-kind');
      state[kind].url = '';
      if (kind === 'photo') { state.photo.urlCircle = ''; }
      changed();
    }
    else if (action === 'check-url') { checkUrl(); }
  });

  var uploadInput = picker.dialog.querySelector('[data-upload-input]');
  uploadInput.addEventListener('change', function () {
    uploadFile(uploadInput.files[0]).then(function (asset) {
      uploadInput.value = '';
      if (asset) { picked(asset); }
    });
  });
  var dropzone = picker.dialog.querySelector('.dropzone');
  ['dragenter', 'dragover'].forEach(function (name) {
    dropzone.addEventListener(name, function (event) { event.preventDefault(); dropzone.classList.add('dropzone-over'); });
  });
  ['dragleave', 'drop'].forEach(function (name) {
    dropzone.addEventListener(name, function (event) { event.preventDefault(); dropzone.classList.remove('dropzone-over'); });
  });
  dropzone.addEventListener('drop', function (event) {
    var file = event.dataTransfer && event.dataTransfer.files[0];
    uploadFile(file).then(function (asset) { if (asset) { picked(asset); } });
  });
  picker.dialog.querySelector('[data-url-input]').addEventListener('keydown', function (event) {
    if (event.key === 'Enter') { event.preventDefault(); checkUrl(); }
  });

  Array.prototype.forEach.call(root.querySelectorAll('[data-preview-option]'), function (input) {
    input.addEventListener('change', function () {
      var stage = root.querySelector('[data-preview-stage]');
      if (input.getAttribute('data-preview-option') === 'bg') {
        stage.classList.toggle('preview-dark', input.value === 'dark' && input.checked);
      } else {
        stage.classList.toggle('preview-mobile', input.value === 'mobile' && input.checked);
      }
      renderPreview();
    });
  });

  document.addEventListener('keydown', function (event) {
    if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 's') {
      event.preventDefault();
      save();
    }
  });

  window.addEventListener('beforeunload', function (event) {
    if (dirty) {
      event.preventDefault();
      event.returnValue = '';
    }
  });
  window.addEventListener('resize', function () { clearTimeout(miniTimer); miniTimer = setTimeout(renderMinis, 200); });

  /* ------------------------------- Início ------------------------------- */

  // Mantém a sessão viva durante edições longas.
  setInterval(function () { KW.refreshSession().catch(function () {}); }, 5 * 60 * 1000);

  (boot.flashes || []).forEach(function (flash) { KW.toast(flash.message, flash.type === 'success' ? 'success' : flash.type); });

  buildTemplates();
  fillInputs();
  orderContacts();
  renderSocial();
  renderShapes();
  renderPreview();
  if (mode === 'signature') {
    // Na assinatura de um colaborador o mais comum é mexer nos dados, então essa aba abre primeiro.
    showPanel('dados');
    if (!signature) {
      saveState.textContent = 'Assinatura ainda não salva';
      var first = document.getElementById('f-name');
      if (first) { first.focus(); }
    }
  }
})();
