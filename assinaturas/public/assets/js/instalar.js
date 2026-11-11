/* Página do colaborador: mostra a assinatura pronta e oferece copiar e baixar. */
(function () {
  'use strict';
  var boot = KW.boot();
  var rendered = KWRender.render(boot.data, { baseUrl: boot.baseUrl });
  var frame = document.querySelector('[data-preview-frame]');
  if (frame) { KW.mountPreview(frame, rendered.html); }

  document.addEventListener('click', function (event) {
    var button = event.target.closest('[data-action]');
    if (!button) { return; }
    if (button.getAttribute('data-action') === 'copy') {
      KW.copyRich(rendered).then(function (ok) {
        KW.toast(ok ? 'Assinatura copiada. Agora cole no seu programa de e-mail.' : 'Não foi possível copiar. Use Baixar HTML.', ok ? 'success' : 'error');
      });
    } else if (button.getAttribute('data-action') === 'download') {
      KW.download('assinatura-' + KW.slug(boot.name) + '.html', KWRender.documentHtml(boot.data, { baseUrl: boot.baseUrl }));
    }
  });
})();
