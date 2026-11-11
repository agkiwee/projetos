/* Lista de assinaturas: copiar a assinatura formatada sem abrir o editor. */
(function () {
  'use strict';
  var boot = KW.bootData = KW.boot();

  document.addEventListener('click', function (event) {
    var button = event.target.closest('[data-copy-signature]');
    if (!button) { return; }
    var id = button.getAttribute('data-copy-signature');
    button.disabled = true;
    var content = KW.api('/api/assinaturas/' + id).then(function (result) {
      var rendered = KWRender.render(result.data, { baseUrl: boot.baseUrl });
      if (!rendered.html) { throw new Error('Esta assinatura ainda não tem dados para copiar.'); }
      return rendered;
    });
    KW.copyRich(content).then(function (ok) {
      KW.toast(ok ? 'Assinatura copiada. Agora cole no seu programa de e-mail.' : 'Não foi possível copiar. Abra a assinatura e use Baixar HTML.', ok ? 'success' : 'error');
    }, function (error) {
      KW.toast(error.message, 'error');
    }).then(function () { button.disabled = false; });
  });
})();
