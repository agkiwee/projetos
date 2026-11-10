/* Tela Nova assinatura: prévias dos modelos com a identidade da empresa. */
(function () {
  'use strict';
  var boot = KW.bootData = KW.boot();
  var R = KWRender;

  function preview(holder, template) {
    var data = R.withSamples(boot.data);
    if (template) { data.template = template; }
    var frame = document.createElement('iframe');
    frame.setAttribute('sandbox', 'allow-same-origin');
    frame.setAttribute('scrolling', 'no');
    frame.setAttribute('tabindex', '-1');
    frame.setAttribute('aria-hidden', 'true');
    frame.title = 'Prévia do modelo';
    holder.appendChild(frame);
    KW.mountPreview(frame, R.render(data, { baseUrl: boot.baseUrl }).html, {
      fit: false,
      onSize: function (width, height) { KW.fitMini(holder, frame, width, height); }
    });
  }

  var standard = document.querySelector('[data-sig-preview="padrao"]');
  if (standard) { preview(standard, null); }

  var gallery = document.querySelector('[data-sig-gallery]');
  if (gallery) {
    R.TEMPLATES.forEach(function (template) {
      var card = document.createElement('a');
      card.className = 'template-card';
      card.href = (boot.base || '') + '/assinaturas/nova?modelo=' + template.id;
      var holder = document.createElement('span');
      holder.className = 'template-preview';
      var name = document.createElement('span');
      name.className = 'template-name';
      name.textContent = template.name;
      var hint = document.createElement('span');
      hint.className = 'template-hint';
      hint.textContent = template.hint;
      card.appendChild(holder);
      card.appendChild(name);
      card.appendChild(hint);
      gallery.appendChild(card);
      preview(holder, template.id);
    });
  }
})();
