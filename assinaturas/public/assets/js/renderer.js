/*
 * KIWEE Assinaturas: gerador do HTML da assinatura.
 *
 * Recebe os dados da assinatura e devolve HTML feito só com tabelas e estilos em linha,
 * do jeito que Gmail, Outlook e Apple Mail conseguem exibir. Sem JavaScript e sem CSS externo.
 * O mesmo arquivo roda no navegador (prévia e exportação) e no Node (testes).
 */
(function (root, factory) {
  if (typeof module === 'object' && module.exports) {
    module.exports = factory();
  } else {
    root.KWRender = factory();
  }
})(typeof self !== 'undefined' ? self : this, function () {
  'use strict';

  var TEMPLATES = [
    { id: 'horizontal', name: 'Horizontal', hint: 'Logotipo à esquerda e dados à direita' },
    { id: 'vertical', name: 'Vertical', hint: 'Tudo empilhado, ideal para telas estreitas' },
    { id: 'compacta', name: 'Compacta', hint: 'Poucas linhas de texto, sem foto' },
    { id: 'foto', name: 'Com foto', hint: 'Foto do colaborador em destaque' },
    { id: 'destaque', name: 'Destaque', hint: 'Barra na cor da marca ao lado dos dados' }
  ];

  // Fontes presentes em praticamente todo computador e celular, com as fontes de reserva.
  var FONTS = {
    'Arial': 'Arial, Helvetica, sans-serif',
    'Helvetica': 'Helvetica, Arial, sans-serif',
    'Verdana': 'Verdana, Geneva, sans-serif',
    'Tahoma': 'Tahoma, Verdana, Segoe, sans-serif',
    'Trebuchet MS': "'Trebuchet MS', Helvetica, sans-serif",
    'Georgia': "Georgia, 'Times New Roman', serif",
    'Times New Roman': "'Times New Roman', Times, serif",
    'Courier New': "'Courier New', Courier, monospace"
  };

  var FIELDS = ['name', 'title', 'department', 'company', 'phone', 'mobile', 'whatsapp', 'email', 'website', 'address'];
  var CONTACT_FIELDS = ['phone', 'mobile', 'whatsapp', 'email', 'website', 'address'];

  var FIELD_LABELS = {
    name: 'Nome', title: 'Cargo', department: 'Departamento', company: 'Empresa', phone: 'Telefone',
    mobile: 'Celular', whatsapp: 'WhatsApp', email: 'E-mail', website: 'Site', address: 'Endereço'
  };
  // Rótulos curtos usados quando o cliente prefere texto no lugar dos ícones de contato.
  var SHORT_LABELS = { phone: 'Tel', mobile: 'Cel', whatsapp: 'WhatsApp', email: 'E-mail', website: 'Site', address: 'End' };

  var ICON_LABELS = {
    phone: 'Telefone', mobile: 'Celular', email: 'E-mail', website: 'Site', address: 'Endereço', link: 'Link',
    calendar: 'Agenda', cart: 'Loja', document: 'Documento', video: 'Vídeo', rss: 'Blog', chat: 'Chat',
    whatsapp: 'WhatsApp', facebook: 'Facebook', instagram: 'Instagram', linkedin: 'LinkedIn', x: 'X',
    youtube: 'YouTube', tiktok: 'TikTok', telegram: 'Telegram', pinterest: 'Pinterest', threads: 'Threads',
    github: 'GitHub', behance: 'Behance', spotify: 'Spotify', discord: 'Discord', vimeo: 'Vimeo',
    medium: 'Medium', linktree: 'Linktree', messenger: 'Messenger', twitch: 'Twitch', dribbble: 'Dribbble',
    maps: 'Google Maps', calendly: 'Calendly', tripadvisor: 'Tripadvisor', soundcloud: 'SoundCloud'
  };

  var ICON_SHAPES = [
    { id: 'outline-square', name: 'Quadrado vazado' },
    { id: 'outline-circle', name: 'Círculo vazado' },
    { id: 'square', name: 'Quadrado cheio' },
    { id: 'circle', name: 'Círculo cheio' },
    { id: 'plain', name: 'Só o símbolo' }
  ];

  function defaults() {
    var fields = {};
    FIELDS.forEach(function (key) { fields[key] = { value: '', show: true }; });
    return {
      v: 1,
      template: 'horizontal',
      fields: fields,
      order: CONTACT_FIELDS.slice(),
      logo: { url: '', link: '', width: 150, ratio: 0, show: true },
      photo: { url: '', urlCircle: '', size: 88, shape: 'circle', show: true },
      banner: { url: '', link: '', alt: '', width: 420, ratio: 0, show: true },
      social: [],
      disclaimer: { text: '', show: true },
      style: {
        font: 'Arial', colorName: '#2b2f2c', colorTitle: '#3d7f21', colorText: '#4a524d', colorLink: '#4a524d',
        colorDivider: '#c9d1cb', colorIcon: '#3d7f21', sizeName: 18, sizeTitle: 14, sizeText: 13,
        align: 'left', spacing: 8, gap: 20, dividers: true, contactStyle: 'icons',
        iconShape: 'outline-square', iconSize: 28, underline: false
      }
    };
  }

  function isObject(value) { return value !== null && typeof value === 'object' && !Array.isArray(value); }
  function clamp(value, min, max, fallback) {
    var number = Number(value);
    if (!isFinite(number)) { return fallback; }
    return Math.max(min, Math.min(max, Math.round(number)));
  }
  function color(value, fallback) { return /^#[0-9a-fA-F]{6}$/.test(String(value)) ? String(value).toLowerCase() : fallback; }
  function oneOf(value, list, fallback) { return list.indexOf(value) >= 0 ? value : fallback; }
  function text(value) { return value === null || value === undefined ? '' : String(value); }

  /** Completa e corrige os dados, para o desenho nunca quebrar com dados antigos ou incompletos. */
  function normalize(input) {
    var base = defaults();
    var data = isObject(input) ? input : {};
    var out = base;
    out.template = oneOf(data.template, TEMPLATES.map(function (t) { return t.id; }), 'horizontal');

    var fields = isObject(data.fields) ? data.fields : {};
    FIELDS.forEach(function (key) {
      var field = isObject(fields[key]) ? fields[key] : {};
      out.fields[key] = { value: text(field.value), show: field.show !== false };
    });

    var order = [];
    (Array.isArray(data.order) ? data.order : []).forEach(function (key) {
      if (CONTACT_FIELDS.indexOf(key) >= 0 && order.indexOf(key) < 0) { order.push(key); }
    });
    CONTACT_FIELDS.forEach(function (key) { if (order.indexOf(key) < 0) { order.push(key); } });
    out.order = order;

    var logo = isObject(data.logo) ? data.logo : {};
    out.logo = {
      url: text(logo.url), link: text(logo.link), width: clamp(logo.width, 40, 400, 150),
      ratio: Number(logo.ratio) > 0 ? Number(logo.ratio) : 0, show: logo.show !== false
    };
    var photo = isObject(data.photo) ? data.photo : {};
    out.photo = {
      url: text(photo.url), urlCircle: text(photo.urlCircle), size: clamp(photo.size, 40, 200, 88),
      shape: oneOf(photo.shape, ['circle', 'rounded', 'square'], 'circle'), show: photo.show !== false
    };
    var banner = isObject(data.banner) ? data.banner : {};
    out.banner = {
      url: text(banner.url), link: text(banner.link), alt: text(banner.alt), width: clamp(banner.width, 100, 600, 420),
      ratio: Number(banner.ratio) > 0 ? Number(banner.ratio) : 0, show: banner.show !== false
    };
    out.social = (Array.isArray(data.social) ? data.social : []).filter(isObject).slice(0, 12).map(function (item) {
      return {
        type: text(item.type), url: text(item.url), label: text(item.label),
        iconUrl: text(item.iconUrl), show: item.show !== false
      };
    });
    var disclaimer = isObject(data.disclaimer) ? data.disclaimer : {};
    out.disclaimer = { text: text(disclaimer.text), show: disclaimer.show !== false };

    var style = isObject(data.style) ? data.style : {};
    var s = base.style;
    out.style = {
      font: FONTS[style.font] ? style.font : s.font,
      colorName: color(style.colorName, s.colorName), colorTitle: color(style.colorTitle, s.colorTitle),
      colorText: color(style.colorText, s.colorText), colorLink: color(style.colorLink, s.colorLink),
      colorDivider: color(style.colorDivider, s.colorDivider), colorIcon: color(style.colorIcon, s.colorIcon),
      sizeName: clamp(style.sizeName, 12, 28, s.sizeName), sizeTitle: clamp(style.sizeTitle, 10, 22, s.sizeTitle),
      sizeText: clamp(style.sizeText, 10, 18, s.sizeText),
      align: oneOf(style.align, ['left', 'center', 'right'], s.align),
      spacing: clamp(style.spacing, 0, 24, s.spacing), gap: clamp(style.gap, 8, 48, s.gap),
      dividers: style.dividers !== false,
      contactStyle: oneOf(style.contactStyle, ['icons', 'labels', 'none'], s.contactStyle),
      iconShape: oneOf(style.iconShape, ICON_SHAPES.map(function (i) { return i.id; }), s.iconShape),
      iconSize: clamp(style.iconSize, 16, 40, s.iconSize), underline: style.underline === true
    };
    return out;
  }

  /* ------------------------------ Texto e links ------------------------------ */

  function esc(value) {
    return text(value).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  }

  /** Só deixa passar links http(s), mailto e tel. Um domínio sem esquema ganha https://. */
  function linkUrl(value) {
    var url = text(value).trim();
    if (!url || /[\s<>"'`\\]/.test(url)) { return ''; }
    if (/^https?:\/\//i.test(url) || /^(mailto:|tel:)/i.test(url)) { return url; }
    if (/^[a-z][a-z0-9+.\-]*:/i.test(url)) { return ''; }
    return 'https://' + url.replace(/^\/+/, '');
  }

  /** Imagens hospedadas na plataforma são gravadas como caminho e ganham o endereço do site aqui. */
  function imageUrl(value, baseUrl) {
    var url = text(value).trim();
    if (!url || /[\s<>"'`\\]/.test(url)) { return ''; }
    if (/^\/[^\/]/.test(url)) { return baseUrl + url; }
    return /^https?:\/\//i.test(url) ? url : '';
  }

  function phoneDigits(value) {
    var raw = text(value).trim();
    var digits = raw.replace(/\D/g, '');
    if (!digits) { return ''; }
    if (raw.charAt(0) === '+') { return digits; }
    digits = digits.replace(/^0+/, '');
    // Números brasileiros com DDD (10 ou 11 dígitos) recebem o código do país.
    return digits.length === 10 || digits.length === 11 ? '55' + digits : digits;
  }

  function fieldLink(key, value) {
    var digits;
    if (key === 'phone' || key === 'mobile') {
      digits = phoneDigits(value);
      return digits ? 'tel:+' + digits : '';
    }
    if (key === 'whatsapp') {
      digits = phoneDigits(value);
      return digits ? 'https://wa.me/' + digits : '';
    }
    if (key === 'email') {
      return /^[^\s@<>"']+@[^\s@<>"']+$/.test(text(value).trim()) ? 'mailto:' + text(value).trim() : '';
    }
    if (key === 'website') { return linkUrl(value); }
    if (key === 'address') {
      return 'https://www.google.com/maps/search/?api=1&query=' + encodeURIComponent(text(value).replace(/\s+/g, ' ').trim());
    }
    return '';
  }

  /* --------------------------------- Blocos --------------------------------- */

  function Builder(data, options) {
    this.d = data;
    this.s = data.style;
    this.baseUrl = text(options && options.baseUrl).replace(/\/+$/, '');
    this.font = FONTS[this.s.font];
  }

  Builder.prototype.value = function (key) {
    var field = this.d.fields[key];
    return field && field.show ? field.value.trim() : '';
  };

  Builder.prototype.table = function (rows, attrs) {
    return '<table role="presentation" cellpadding="0" cellspacing="0" border="0"' + (attrs || '') +
      ' style="border-collapse:collapse;">' + rows + '</table>';
  };

  Builder.prototype.textStyle = function (size, colorValue, extra) {
    return 'font-family:' + this.font + ';font-size:' + size + 'px;line-height:' + Math.round(size * 1.4) + 'px;color:' +
      colorValue + ';' + (extra || '');
  };

  Builder.prototype.anchor = function (href, inner, colorValue) {
    if (!href) { return inner; }
    return '<a href="' + esc(href) + '" target="_blank" style="color:' + colorValue + ';text-decoration:' +
      (this.s.underline ? 'underline' : 'none') + ';">' + inner + '</a>';
  };

  /**
   * Empilha blocos, um por linha, com o espaço pedido só entre blocos que existem.
   * Com divided, uma linha divisória separa os blocos. A divisória é a borda da própria célula,
   * então ela tem sempre a largura do conteúdo. Tabelas com largura de 100% são evitadas de propósito:
   * o Outlook clássico as estica até a largura da janela.
   */
  Builder.prototype.stack = function (blocks, gap, align, divided) {
    var present = blocks.filter(Boolean);
    if (present.length < 2) { return present[0] || ''; } // um bloco só dispensa a tabela em volta
    var alignment = align || this.s.align;
    var line = divided && this.s.dividers ? 'border-top:1px solid ' + this.s.colorDivider + ';' : '';
    var rows = '';
    present.forEach(function (block, index) {
      var last = index === present.length - 1;
      var top = index === 0 ? 0 : gap;
      var bottom = line && !last ? gap : 0;
      var style = (index > 0 ? line : '') + (top || bottom ? 'padding:' + top + 'px 0 ' + bottom + 'px 0;' : '');
      rows += '<tr><td align="' + alignment + '"' + (style ? ' style="' + style + '"' : '') + '>' + block + '</td></tr>';
    });
    return this.table(rows);
  };

  Builder.prototype.identity = function (align) {
    var s = this.s;
    var a = align || s.align;
    var rows = '';
    var name = this.value('name');
    var title = this.value('title');
    var extra = [this.value('department'), this.value('company')].filter(Boolean)
      .map(esc).join(' <span style="color:' + s.colorDivider + ';">|</span> ');
    if (name) {
      rows += '<tr><td align="' + a + '" style="' + this.textStyle(s.sizeName, s.colorName, 'font-weight:bold;') + '">' + esc(name) + '</td></tr>';
    }
    if (title) {
      rows += '<tr><td align="' + a + '" style="' + this.textStyle(s.sizeTitle, s.colorTitle, 'font-weight:bold;') +
        (name ? 'padding-top:2px;' : '') + '">' + esc(title) + '</td></tr>';
    }
    if (extra) {
      rows += '<tr><td align="' + a + '" style="' + this.textStyle(s.sizeText, s.colorText) +
        (name || title ? 'padding-top:2px;' : '') + '">' + extra + '</td></tr>';
    }
    return rows ? this.table(rows) : '';
  };

  Builder.prototype.icon = function (name, size, colorValue, shape, alt) {
    var src = this.baseUrl + '/icones/' + shape + '/' + colorValue.replace('#', '') + '/' + name + '.png';
    return '<img src="' + esc(src) + '" width="' + size + '" height="' + size + '" alt="' + esc(alt) +
      '" style="display:block;border:0;width:' + size + 'px;height:' + size + 'px;">';
  };

  /** Linhas de contato na ordem escolhida. Campos vazios ou ocultos não geram linha. */
  Builder.prototype.contacts = function () {
    var self = this;
    var s = this.s;
    var rows = '';
    var first = true;
    var iconSize = Math.max(12, s.sizeText + 2);
    this.d.order.forEach(function (key) {
      var value = self.value(key);
      if (!value) { return; }
      var shown = key === 'address' ? esc(value).replace(/\n/g, '<br>') : esc(value);
      var href = fieldLink(key, value);
      var pad = first ? 0 : s.spacing;
      var label = '';
      if (s.contactStyle === 'icons') {
        label = '<td valign="top" style="padding:' + (pad + Math.max(0, Math.round((s.sizeText * 1.4 - iconSize) / 2))) + 'px 8px 0 0;">' +
          self.icon(key === 'website' ? 'website' : key, iconSize, s.colorIcon, 'plain', FIELD_LABELS[key]) + '</td>';
      } else if (s.contactStyle === 'labels') {
        label = '<td valign="top" style="' + self.textStyle(s.sizeText, s.colorTitle, 'font-weight:bold;white-space:nowrap;padding:' + pad + 'px 6px 0 0;') + '">' +
          SHORT_LABELS[key] + ':</td>';
      }
      rows += '<tr>' + label + '<td valign="top" align="left" style="' +
        self.textStyle(s.sizeText, s.colorText, 'padding:' + pad + 'px 0 0 0;' + (key === 'address' ? 'max-width:340px;' : '')) + '">' +
        self.anchor(href, shown, s.colorLink) + '</td></tr>';
      first = false;
    });
    return rows ? this.table(rows) : '';
  };

  /** Contatos em uma linha só, usados no modelo Compacta. */
  Builder.prototype.contactsInline = function () {
    var self = this;
    var s = this.s;
    var parts = [];
    var address = '';
    this.d.order.forEach(function (key) {
      var value = self.value(key);
      if (!value) { return; }
      var prefix = s.contactStyle === 'labels' ? '<span style="color:' + s.colorTitle + ';font-weight:bold;">' + SHORT_LABELS[key] + ':</span> ' : '';
      var item = prefix + self.anchor(fieldLink(key, value), esc(value).replace(/\n/g, ', '), s.colorLink);
      if (key === 'address') { address = item; } else { parts.push(item); }
    });
    var separator = ' <span style="color:' + s.colorDivider + ';">|</span> ';
    var lines = [];
    if (parts.length) { lines.push(parts.join(separator)); }
    if (address) { lines.push(address); }
    return lines;
  };

  Builder.prototype.social = function (size) {
    var self = this;
    var s = this.s;
    var iconSize = size || s.iconSize;
    var cells = '';
    var first = true;
    this.d.social.forEach(function (item) {
      if (!item.show) { return; }
      var href = linkUrl(item.url);
      if (!href) { return; }
      var image;
      if (item.type === 'custom') {
        var src = imageUrl(item.iconUrl, self.baseUrl);
        if (!src) { return; }
        image = '<img src="' + esc(src) + '" width="' + iconSize + '" height="' + iconSize + '" alt="' + esc(item.label || 'Link') +
          '" style="display:block;border:0;width:' + iconSize + 'px;height:' + iconSize + 'px;">';
      } else if (ICON_LABELS[item.type]) {
        image = self.icon(item.type, iconSize, s.colorIcon, s.iconShape, item.label || ICON_LABELS[item.type]);
      } else {
        return;
      }
      cells += '<td style="padding:0 0 0 ' + (first ? 0 : 6) + 'px;"><a href="' + esc(href) + '" target="_blank" style="text-decoration:none;">' + image + '</a></td>';
      first = false;
    });
    return cells ? this.table('<tr>' + cells + '</tr>') : '';
  };

  Builder.prototype.logo = function (maxWidth) {
    var logo = this.d.logo;
    var src = logo.show ? imageUrl(logo.url, this.baseUrl) : '';
    if (!src) { return ''; }
    var width = Math.min(logo.width, maxWidth || logo.width);
    var height = logo.ratio > 0 ? Math.max(1, Math.round(width * logo.ratio)) : 0;
    var alt = this.d.fields.company.value.trim() || 'Logotipo';
    var image = '<img src="' + esc(src) + '" width="' + width + '"' + (height ? ' height="' + height + '"' : '') + ' alt="' + esc(alt) +
      '" style="display:block;border:0;width:' + width + 'px;' + (height ? 'height:' + height + 'px;' : 'height:auto;') + '">';
    var href = linkUrl(logo.link);
    return href ? '<a href="' + esc(href) + '" target="_blank" style="text-decoration:none;">' + image + '</a>' : image;
  };

  Builder.prototype.photo = function () {
    var photo = this.d.photo;
    if (!photo.show) { return ''; }
    // A versão já recortada em círculo funciona até onde cantos arredondados são ignorados.
    var circle = photo.shape === 'circle' ? imageUrl(photo.urlCircle, this.baseUrl) : '';
    var src = circle || imageUrl(photo.url, this.baseUrl);
    if (!src) { return ''; }
    var radius = photo.shape === 'circle' ? '50%' : (photo.shape === 'rounded' ? '10px' : '0');
    var alt = this.d.fields.name.value.trim() ? 'Foto de ' + this.d.fields.name.value.trim() : 'Foto';
    return '<img src="' + esc(src) + '" width="' + photo.size + '" height="' + photo.size + '" alt="' + esc(alt) +
      '" style="display:block;border:0;width:' + photo.size + 'px;height:' + photo.size + 'px;border-radius:' + radius + ';">';
  };

  Builder.prototype.banner = function () {
    var banner = this.d.banner;
    var src = banner.show ? imageUrl(banner.url, this.baseUrl) : '';
    if (!src) { return ''; }
    var height = banner.ratio > 0 ? Math.max(1, Math.round(banner.width * banner.ratio)) : 0;
    var image = '<img src="' + esc(src) + '" width="' + banner.width + '"' + (height ? ' height="' + height + '"' : '') +
      ' alt="' + esc(banner.alt || 'Banner') + '" style="display:block;border:0;width:' + banner.width + 'px;max-width:100%;' +
      (height ? '' : 'height:auto;') + '">';
    var href = linkUrl(banner.link);
    return href ? '<a href="' + esc(href) + '" target="_blank" style="text-decoration:none;">' + image + '</a>' : image;
  };

  Builder.prototype.disclaimer = function () {
    var value = this.d.disclaimer.show ? this.d.disclaimer.text.trim() : '';
    if (!value) { return ''; }
    return this.table('<tr><td style="' + this.textStyle(Math.max(10, this.s.sizeText - 2), '#8a8f8b', 'max-width:560px;') + '">' +
      esc(value).replace(/\n/g, '<br>') + '</td></tr>');
  };

  /* --------------------------------- Modelos --------------------------------- */

  var LAYOUTS = {
    horizontal: function (b) {
      var s = b.s;
      var gap = s.spacing + 4;
      var line = s.dividers ? '1px solid ' + s.colorDivider : '';
      var media = b.stack([b.photo(), b.logo()], gap, 'center');
      var identity = b.identity('left');
      var social = b.social();
      var contacts = b.contacts();
      var header = identity || social;
      var rows = '';
      if (identity && social) {
        rows += '<tr><td valign="top" align="left" style="padding:0 0 ' + (contacts ? gap : 0) + 'px 0;">' + identity +
          '</td><td valign="top" align="right" style="padding:0 0 ' + (contacts ? gap : 0) + 'px ' + (s.gap + 4) + 'px;">' + social + '</td></tr>';
      } else if (header) {
        rows += '<tr><td valign="top" align="left" style="padding:0 0 ' + (contacts ? gap : 0) + 'px 0;">' + header + '</td></tr>';
      }
      if (contacts) {
        // As divisórias acima e abaixo dos contatos só existem quando há algo acima deles.
        var borders = header && line ? 'border-top:' + line + ';border-bottom:' + line + ';' : '';
        rows += '<tr><td' + (identity && social ? ' colspan="2"' : '') + ' align="left" style="' + borders + 'padding:' +
          (header ? gap : 0) + 'px 0 ' + (borders ? gap : 0) + 'px 0;">' + contacts + '</td></tr>';
      }
      var info = rows ? b.table(rows) : '';
      if (!media) { return info; }
      if (!info) { return media; }
      return b.table('<tr><td valign="middle" style="padding:0 ' + s.gap + 'px 0 0;">' + media + '</td><td valign="top">' + info + '</td></tr>');
    },

    vertical: function (b) {
      var s = b.s;
      var gap = s.spacing + 4;
      var top = b.stack([b.logo(), b.photo(), b.identity()], gap);
      var body = b.stack([top, b.contacts()], gap, s.align, true);
      return b.stack([body, b.social()], gap, s.align);
    },

    compacta: function (b) {
      var s = b.s;
      var name = b.value('name');
      var title = b.value('title');
      var separator = ' <span style="color:' + s.colorDivider + ';">|</span> ';
      var head = [];
      if (name) { head.push('<span style="font-weight:bold;color:' + s.colorName + ';">' + esc(name) + '</span>'); }
      if (title) { head.push('<span style="color:' + s.colorTitle + ';font-weight:bold;">' + esc(title) + '</span>'); }
      [b.value('department'), b.value('company')].forEach(function (item) { if (item) { head.push(esc(item)); } });
      var lines = [];
      if (head.length) { lines.push(head.join(separator)); }
      lines = lines.concat(b.contactsInline());
      var rows = '';
      lines.forEach(function (line, index) {
        rows += '<tr><td align="' + s.align + '" style="' + b.textStyle(s.sizeText, s.colorText, index ? 'padding-top:' + Math.round(s.spacing / 2) + 'px;' : '') +
          '">' + line + '</td></tr>';
      });
      var textBlock = rows ? b.table(rows) : '';
      var info = b.stack([textBlock, b.social(Math.min(b.s.iconSize, 22))], s.spacing, s.align);
      var logo = b.logo(120);
      if (!logo) { return info; }
      if (!info) { return logo; }
      var border = s.dividers ? 'border-left:1px solid ' + s.colorDivider + ';' : '';
      return b.table('<tr><td valign="middle" style="padding:0 ' + Math.round(s.gap * 0.7) + 'px 0 0;">' + logo +
        '</td><td valign="middle" style="' + border + 'padding:0 0 0 ' + Math.round(s.gap * 0.7) + 'px;">' + info + '</td></tr>');
    },

    foto: function (b) {
      var s = b.s;
      var photo = b.photo();
      var info = b.stack([b.identity('left'), b.contacts()], s.spacing + 4, 'left');
      var top;
      if (photo && info) {
        top = b.table('<tr><td valign="top" style="padding:0 ' + s.gap + 'px 0 0;">' + photo + '</td><td valign="middle">' + info + '</td></tr>');
      } else {
        top = photo || info;
      }
      var logo = b.logo();
      var social = b.social();
      var footer;
      if (logo && social) {
        footer = b.table('<tr><td valign="middle" align="left">' + logo + '</td><td valign="middle" align="left" style="padding:0 0 0 ' +
          s.gap + 'px;">' + social + '</td></tr>');
      } else {
        footer = logo || social;
      }
      return b.stack([top, footer], s.spacing + 6, 'left', true);
    },

    destaque: function (b) {
      var s = b.s;
      var media = b.stack([b.photo(), b.logo(), b.social()], s.spacing + 6, 'center');
      var info = b.stack([b.identity('left'), b.contacts()], s.spacing + 4, 'left');
      if (!media) {
        return info ? b.table('<tr><td style="border-left:3px solid ' + s.colorTitle + ';padding:2px 0 2px ' + Math.round(s.gap * 0.7) + 'px;">' + info + '</td></tr>') : '';
      }
      if (!info) { return media; }
      return b.table('<tr><td valign="middle" style="padding:0 ' + s.gap + 'px 0 0;">' + media +
        '</td><td valign="middle" style="border-left:3px solid ' + s.colorTitle + ';padding:2px 0 2px ' + s.gap + 'px;">' + info + '</td></tr>');
    }
  };

  /** Gera o HTML da assinatura. options.baseUrl é o endereço público da plataforma. */
  function render(input, options) {
    var data = normalize(input);
    var b = new Builder(data, options || {});
    var main = LAYOUTS[data.template](b);
    var html = b.stack([main, b.banner(), b.disclaimer()], data.style.spacing + 8, 'left');
    return { html: html, text: plainText(data), length: html.length };
  }

  /** Versão em texto simples, usada como alternativa ao copiar. */
  function plainText(input) {
    var data = input && input.style && input.fields ? input : normalize(input);
    var value = function (key) { return data.fields[key].show ? data.fields[key].value.trim() : ''; };
    var lines = [];
    if (value('name')) { lines.push(value('name')); }
    var role = [value('title'), value('department'), value('company')].filter(Boolean).join(' | ');
    if (role) { lines.push(role); }
    data.order.forEach(function (key) {
      if (value(key)) { lines.push(SHORT_LABELS[key] + ': ' + value(key).replace(/\n/g, ', ')); }
    });
    data.social.forEach(function (item) {
      var href = item.show ? linkUrl(item.url) : '';
      if (href) { lines.push((item.label || ICON_LABELS[item.type] || 'Link') + ': ' + href); }
    });
    if (data.disclaimer.show && data.disclaimer.text.trim()) { lines.push('', data.disclaimer.text.trim()); }
    return lines.join('\n');
  }

  /** Documento HTML completo para o botão Baixar HTML. */
  function documentHtml(input, options) {
    var data = normalize(input);
    var title = data.fields.name.value.trim() ? 'Assinatura de ' + data.fields.name.value.trim() : 'Assinatura de e-mail';
    return '<!DOCTYPE html>\n<html lang="pt-BR">\n<head>\n<meta charset="utf-8">\n<meta name="viewport" content="width=device-width, initial-scale=1">\n<title>' +
      esc(title) + '</title>\n</head>\n<body style="margin:16px;background:#ffffff;">\n' + render(data, options).html + '\n</body>\n</html>\n';
  }

  /** Dados de exemplo usados na prévia do modelo padrão e na galeria de modelos. */
  function withSamples(input) {
    var data = normalize(input);
    var samples = {
      name: 'Nome do Colaborador', title: 'Cargo', department: 'Departamento', mobile: '(11) 99999-0000',
      email: 'nome@empresa.com.br'
    };
    Object.keys(samples).forEach(function (key) {
      if (!data.fields[key].value.trim()) { data.fields[key].value = samples[key]; }
    });
    return data;
  }

  return {
    TEMPLATES: TEMPLATES, FONTS: FONTS, FIELDS: FIELDS, CONTACT_FIELDS: CONTACT_FIELDS, FIELD_LABELS: FIELD_LABELS,
    ICON_LABELS: ICON_LABELS, ICON_SHAPES: ICON_SHAPES, GMAIL_LIMIT: 10000,
    defaults: defaults, normalize: normalize, render: render, plainText: plainText, documentHtml: documentHtml,
    withSamples: withSamples, linkUrl: linkUrl, imageUrl: imageUrl, phoneDigits: phoneDigits
  };
});
