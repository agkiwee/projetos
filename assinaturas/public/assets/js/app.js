/* KIWEE Assinaturas: funções comuns a todas as telas. */
(function () {
  'use strict';

  var KW = window.KW = window.KW || {};

  /** Dados que o servidor deixou na página para o JavaScript. */
  KW.boot = function () {
    var node = document.getElementById('kw-boot');
    if (!node) { return {}; }
    try { return JSON.parse(node.textContent); } catch (error) { return {}; }
  };

  KW.toast = function (message, type) {
    var area = document.getElementById('toasts');
    if (!area) { return; }
    var item = document.createElement('div');
    item.className = 'toast toast-' + (type || 'success');
    item.setAttribute('role', type === 'error' ? 'alert' : 'status');
    item.textContent = message;
    area.appendChild(item);
    setTimeout(function () { item.classList.add('toast-out'); }, type === 'error' ? 6500 : 3500);
    setTimeout(function () { item.remove(); }, type === 'error' ? 7000 : 4000);
  };

  function legacyCopy(build) {
    var holder = document.createElement('div');
    holder.setAttribute('contenteditable', 'true');
    holder.style.cssText = 'position:fixed;left:-9999px;top:0;background:#ffffff;color:#000000;white-space:pre-wrap;';
    build(holder);
    document.body.appendChild(holder);
    var range = document.createRange();
    range.selectNodeContents(holder);
    var selection = window.getSelection();
    selection.removeAllRanges();
    selection.addRange(range);
    var ok = false;
    try { ok = document.execCommand('copy'); } catch (error) { ok = false; }
    selection.removeAllRanges();
    holder.remove();
    return ok;
  }

  KW.copyText = function (value) {
    if (navigator.clipboard && window.isSecureContext) {
      return navigator.clipboard.writeText(value).then(function () { return true; }, function () {
        return legacyCopy(function (holder) { holder.textContent = value; });
      });
    }
    return Promise.resolve(legacyCopy(function (holder) { holder.textContent = value; }));
  };

  /**
   * Copia a assinatura formatada, pronta para colar no cliente de e-mail.
   * Aceita o conteúdo pronto ou uma promessa dele (quando ainda vai ser buscado no servidor).
   */
  KW.copyRich = function (content) {
    var pending = Promise.resolve(content);
    if (navigator.clipboard && window.ClipboardItem && window.isSecureContext) {
      try {
        var item = new ClipboardItem({
          'text/html': pending.then(function (c) { return new Blob([c.html], { type: 'text/html' }); }),
          'text/plain': pending.then(function (c) { return new Blob([c.text || ''], { type: 'text/plain' }); })
        });
        return navigator.clipboard.write([item]).then(function () { return true; }, function () {
          return pending.then(function (c) { return legacyCopy(function (holder) { holder.innerHTML = c.html; }); });
        });
      } catch (error) { /* segue para o método antigo */ }
    }
    return pending.then(function (c) { return legacyCopy(function (holder) { holder.innerHTML = c.html; }); });
  };

  KW.download = function (filename, content) {
    var blob = new Blob([content], { type: 'text/html;charset=utf-8' });
    var link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = filename;
    document.body.appendChild(link);
    link.click();
    link.remove();
    setTimeout(function () { URL.revokeObjectURL(link.href); }, 2000);
  };

  KW.slug = function (value) {
    var slug = String(value || '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase()
      .replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');
    return slug || 'assinatura';
  };

  /**
   * Mostra o HTML da assinatura dentro de um iframe isolado, para o estilo do site não interferir.
   * options.fit: ajusta a altura do iframe ao conteúdo (padrão). options.onSize recebe largura e altura.
   */
  KW.mountPreview = function (frame, html, options) {
    options = options || {};
    var doc = frame.contentDocument;
    if (!doc) { return; }
    if (!frame.kwReady) {
      doc.open();
      doc.write('<!DOCTYPE html><html lang="pt-BR"><head><meta charset="utf-8"><style>' +
        'html,body{margin:0;padding:0;background:transparent;}body{padding:2px;display:inline-block;}' +
        '</style></head><body></body></html>');
      doc.close();
      frame.kwReady = true;
    }
    if (options.fit === false) {
      // Miniaturas: dá largura de sobra para o conteúdo se acomodar antes de medir e encolher.
      frame.style.transform = 'none';
      frame.style.width = '900px';
    }
    doc.body.innerHTML = html;
    var measure = function () {
      if (!frame.contentDocument || !frame.contentDocument.body) { return; }
      var body = frame.contentDocument.body;
      var width = Math.ceil(body.scrollWidth);
      var height = Math.ceil(body.scrollHeight);
      if (options.fit !== false) {
        frame.style.height = Math.max(height, 20) + 'px';
        frame.style.minWidth = width + 'px';
      }
      if (options.onSize) { options.onSize(width, height); }
    };
    measure();
    Array.prototype.forEach.call(doc.images, function (image) {
      if (!image.complete) {
        image.addEventListener('load', measure);
        image.addEventListener('error', measure);
      }
    });
  };

  /** Encolhe a prévia para caber no cartão, sem cortar. */
  KW.fitMini = function (holder, frame, width, height) {
    var available = holder.clientWidth - 16;
    var maxHeight = parseInt(holder.getAttribute('data-max-height') || '150', 10);
    var scale = Math.min(1, available / Math.max(width, 1), maxHeight / Math.max(height, 1));
    frame.style.width = width + 'px';
    frame.style.height = height + 'px';
    frame.style.transform = 'scale(' + scale + ')';
    holder.style.height = Math.ceil(height * scale + 16) + 'px';
  };

  /** Chamada JSON para a API com o token de segurança da sessão. */
  KW.api = function (path, options) {
    options = options || {};
    var boot = KW.bootData || (KW.bootData = KW.boot());
    var headers = { 'X-CSRF-Token': boot.csrf || '', 'Accept': 'application/json' };
    var body = options.body;
    if (body && !(body instanceof FormData)) {
      headers['Content-Type'] = 'application/json';
      body = JSON.stringify(body);
    }
    return fetch((boot.base || '') + path, {
      method: options.method || (body ? 'POST' : 'GET'),
      headers: headers,
      body: body,
      credentials: 'same-origin'
    }).then(function (response) {
      return response.json().catch(function () { return {}; }).then(function (data) {
        if (!response.ok || data.ok === false) {
          var error = new Error(data.error || 'Não foi possível concluir. Tente de novo.');
          error.status = response.status;
          error.data = data;
          throw error;
        }
        return data;
      });
    }, function () {
      throw new Error('Sem conexão com o servidor. Verifique sua internet e tente de novo.');
    });
  };

  /* -------------------------- Comportamentos gerais -------------------------- */

  function activateTab(container, name) {
    Array.prototype.forEach.call(container.querySelectorAll('[data-tab]'), function (tab) {
      if (tab.closest('[data-tabs]') !== container) { return; }
      tab.setAttribute('aria-selected', tab.getAttribute('data-tab') === name ? 'true' : 'false');
    });
    Array.prototype.forEach.call(container.querySelectorAll('[data-tab-panel]'), function (panel) {
      if (panel.closest('[data-tabs]') !== container) { return; }
      panel.hidden = panel.getAttribute('data-tab-panel') !== name;
    });
  }
  KW.activateTab = activateTab;

  document.addEventListener('click', function (event) {
    var target = event.target.closest('[data-copy-text],[data-open-dialog],[data-close-dialog],[data-nav-toggle],[data-tab]');
    if (target) {
      if (target.hasAttribute('data-copy-text')) {
        var message = target.getAttribute('data-copy-message') || 'Link copiado.';
        KW.copyText(target.getAttribute('data-copy-text')).then(function (ok) {
          KW.toast(ok ? message : 'Não foi possível copiar. Selecione o texto e copie manualmente.', ok ? 'success' : 'error');
        });
        var menu = target.closest('details');
        if (menu) { menu.open = false; }
      } else if (target.hasAttribute('data-open-dialog')) {
        var dialog = document.getElementById(target.getAttribute('data-open-dialog'));
        if (dialog && dialog.showModal) { dialog.showModal(); }
      } else if (target.hasAttribute('data-close-dialog')) {
        var open = target.closest('dialog');
        if (open) { open.close(); }
      } else if (target.hasAttribute('data-nav-toggle')) {
        var nav = document.getElementById(target.getAttribute('aria-controls'));
        var expanded = target.getAttribute('aria-expanded') === 'true';
        target.setAttribute('aria-expanded', expanded ? 'false' : 'true');
        if (nav) { nav.classList.toggle('open', !expanded); }
      } else if (target.hasAttribute('data-tab')) {
        var tabs = target.closest('[data-tabs]');
        if (tabs) { activateTab(tabs, target.getAttribute('data-tab')); }
      }
    }
    // Clique fora fecha os menus "Mais" e as janelas.
    Array.prototype.forEach.call(document.querySelectorAll('details.menu[open]'), function (menuNode) {
      if (!menuNode.contains(event.target)) { menuNode.open = false; }
    });
    if (event.target.tagName === 'DIALOG' && event.target.open) {
      var box = event.target.getBoundingClientRect();
      var inside = event.clientX >= box.left && event.clientX <= box.right && event.clientY >= box.top && event.clientY <= box.bottom;
      if (!inside) { event.target.close(); }
    }
  });

  document.addEventListener('submit', function (event) {
    var form = event.target;
    var question = form.getAttribute && form.getAttribute('data-confirm');
    if (question && !window.confirm(question)) {
      event.preventDefault();
    }
  });

  document.addEventListener('focusin', function (event) {
    if (event.target.hasAttribute && event.target.hasAttribute('data-select-on-focus')) {
      event.target.select();
    }
  });
})();
