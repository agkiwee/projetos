// Testes do gerador de HTML da assinatura. Uso: node --test tests/renderer.test.mjs
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { createRequire } from 'node:module';

const require = createRequire(import.meta.url);
const R = require('../public/assets/js/renderer.js');
const OPTIONS = { baseUrl: 'https://assinatura.exemplo.com.br' };

function full() {
  const data = R.defaults();
  Object.assign(data.fields.name, { value: 'Ana Souza' });
  data.fields.title.value = 'Gerente de Marketing';
  data.fields.department.value = 'Marketing';
  data.fields.company.value = 'Empresa Exemplo';
  data.fields.phone.value = '(11) 2970-3020';
  data.fields.mobile.value = '(65) 99689-3826';
  data.fields.whatsapp.value = '65 99689-3826';
  data.fields.email.value = 'ana@exemplo.com.br';
  data.fields.website.value = 'www.exemplo.com.br';
  data.fields.address.value = 'Avenida Brasil, 188\nCampo Verde/MT';
  data.logo = { url: '/uploads/abcdefghij0123456789/aaaaaaaaaaaaaaaaaaaaaaaa.png', link: 'exemplo.com.br', width: 150, ratio: 0.3, show: true };
  data.photo = { url: '/uploads/abcdefghij0123456789/bbbbbbbbbbbbbbbbbbbbbbbb.png', urlCircle: '/uploads/abcdefghij0123456789/bbbbbbbbbbbbbbbbbbbbbbbb-c.png', size: 88, shape: 'circle', show: true };
  data.banner = { url: 'https://cdn.exemplo.com.br/banner.png', link: 'https://exemplo.com.br/campanha', alt: 'Campanha de verão', width: 420, ratio: 0.25, show: true };
  data.social = [
    { type: 'linkedin', url: 'https://linkedin.com/company/exemplo', show: true },
    { type: 'instagram', url: 'instagram.com/exemplo', show: true },
    { type: 'custom', url: 'https://exemplo.com.br/catalogo', label: 'Catálogo', iconUrl: 'https://cdn.exemplo.com.br/icone.png', show: true },
  ];
  data.disclaimer = { text: 'Mensagem confidencial.\nNão repasse.', show: true };
  return data;
}

for (const template of R.TEMPLATES) {
  test(`modelo ${template.id}: HTML compatível com e-mail`, () => {
    const data = full();
    data.template = template.id;
    const { html } = R.render(data, OPTIONS);
    assert.ok(html.startsWith('<table role="presentation"'), 'começa com tabela');
    assert.ok(!/<(script|style|link|div|p|iframe|form|svg)\b/i.test(html), 'só usa tabelas, sem tags de página');
    assert.ok(!/\sclass=|\sid=|on\w+=/i.test(html), 'sem classes, ids ou eventos: tudo em estilo em linha');
    assert.ok(!/width="100%"/.test(html), 'sem tabelas de largura 100%, que o Outlook clássico estica');
    assert.equal((html.match(/<table/g) || []).length, (html.match(/<\/table>/g) || []).length, 'tabelas fechadas');
    assert.equal((html.match(/<td/g) || []).length, (html.match(/<\/td>/g) || []).length, 'células fechadas');
    for (const image of html.match(/<img [^>]*>/g) || []) {
      assert.match(image, /src="https:\/\/[^"]+"/, 'imagem com endereço https completo');
      assert.match(image, /alt="[^"]+"/, 'imagem com texto alternativo');
      assert.match(image, /width="\d+"/, 'imagem com largura fixa');
      assert.match(image, /border:0/, 'imagem sem borda');
    }
    assert.ok(html.includes('Ana Souza') && html.includes('Gerente de Marketing'), 'texto real e selecionável');
    assert.ok(html.includes('font-family:Arial, Helvetica, sans-serif'), 'fonte com reservas');
    assert.ok(html.includes('href="mailto:ana@exemplo.com.br"'), 'e-mail clicável');
    assert.ok(html.includes('href="tel:+551129703020"'), 'telefone clicável');
    assert.ok(html.includes('href="https://wa.me/5565996893826"'), 'WhatsApp clicável');
    assert.ok(html.includes('href="https://www.exemplo.com.br"'), 'site clicável');
    assert.ok(html.length < R.GMAIL_LIMIT, `cabe no limite do Gmail (${html.length})`);
  });

  test(`modelo ${template.id}: sem dados não gera nada`, () => {
    const data = R.defaults();
    data.template = template.id;
    assert.equal(R.render(data, OPTIONS).html, '');
  });
}

test('campos vazios e ocultos somem sem deixar linha, ícone ou divisória', () => {
  const data = full();
  data.fields.mobile.value = '';
  data.fields.whatsapp.show = false;
  data.fields.address.value = '   ';
  const { html } = R.render(data, OPTIONS);
  assert.ok(!html.includes('/mobile.png') && !html.includes('/whatsapp.png') && !html.includes('/address.png'));
  assert.ok(!html.includes('wa.me'));
  assert.ok(!/<td[^>]*>\s*<\/td>/.test(html), 'nenhuma célula vazia');

  const only = R.defaults();
  only.fields.name.value = 'Só Nome';
  for (const template of ['horizontal', 'vertical', 'compacta', 'foto']) {
    only.template = template;
    const lone = R.render(only, OPTIONS).html;
    assert.ok(!/border-(top|bottom|left)/.test(lone), `${template}: sem divisória órfã`);
    assert.ok(!/padding[^;"]*[1-9]/.test(lone), `${template}: sem espaço sobrando`);
    assert.ok(!lone.includes('<img'), `${template}: sem imagem`);
  }
});

test('divisórias só aparecem entre blocos que existem', () => {
  const data = R.defaults();
  data.fields.phone.value = '(11) 2970-3020';
  assert.ok(!/border-top/.test(R.render(data, OPTIONS).html), 'só contatos: sem divisória');
  data.fields.name.value = 'Ana';
  assert.ok(/border-top:1px solid/.test(R.render(data, OPTIONS).html), 'nome e contatos: com divisória');
  data.style.dividers = false;
  assert.ok(!/border-top/.test(R.render(data, OPTIONS).html), 'divisórias desligadas');
});

test('rede oculta, sem endereço ou com ícone próprio sem imagem não aparece', () => {
  const data = full();
  data.social = [
    { type: 'linkedin', url: '', show: true },
    { type: 'instagram', url: 'instagram.com/x', show: false },
    { type: 'custom', url: 'https://exemplo.com.br', iconUrl: '', show: true },
    { type: 'inexistente', url: 'https://exemplo.com.br', show: true },
  ];
  const { html } = R.render(data, OPTIONS);
  assert.ok(!html.includes('linkedin.png') && !html.includes('instagram.png') && !html.includes('inexistente'));
});

/**
 * Confere a estrutura do HTML sem depender de um navegador: todo sinal de "<" precisa abrir uma tag conhecida,
 * escrita só com atributos permitidos no formato nome="valor". Texto digitado pelo usuário nunca pode criar tag ou atributo.
 */
function assertOnlySafeTags(html, label) {
  const allowedTags = ['table', 'tr', 'td', 'a', 'img', 'span', 'br'];
  const allowedAttributes = ['role', 'cellpadding', 'cellspacing', 'border', 'style', 'align', 'valign', 'colspan', 'href', 'target', 'src', 'width', 'height', 'alt'];
  const tag = /^<(\/?)([a-z]+)((?:\s+[a-z-]+="[^"<>]*")*)\s*>/;
  for (let index = html.indexOf('<'); index !== -1; index = html.indexOf('<', index + 1)) {
    const match = tag.exec(html.slice(index));
    assert.ok(match, `${label}: "<" fora de uma tag bem formada perto de ${html.slice(index, index + 60)}`);
    assert.ok(allowedTags.includes(match[2]), `${label}: tag inesperada <${match[2]}>`);
    for (const attribute of match[3].matchAll(/\s+([a-z-]+)="/g)) {
      assert.ok(allowedAttributes.includes(attribute[1]), `${label}: atributo inesperado ${attribute[1]}`);
    }
    for (const link of match[3].matchAll(/\s(?:href|src)="([^"]*)"/g)) {
      assert.match(link[1], /^(https:\/\/|mailto:|tel:)/, `${label}: endereço inesperado ${link[1]}`);
    }
  }
}

test('conteúdo digitado é sempre escapado', () => {
  const data = full();
  data.fields.name.value = '<script>alert(1)</script>';
  data.fields.title.value = '"><img src=x onerror=alert(1)>';
  data.fields.department.value = "' onmouseover='alert(1)";
  data.fields.address.value = '<b>Rua</b> & "Cia"';
  data.disclaimer.text = '</td></tr></table><script>x</script>';
  data.banner.alt = '" onload="alert(1)';
  data.social[2].label = '"><svg onload=alert(1)>';
  for (const template of R.TEMPLATES) {
    data.template = template.id;
    const { html } = R.render(data, OPTIONS);
    assertOnlySafeTags(html, template.id);
    assert.ok(html.includes('&lt;script&gt;alert(1)&lt;/script&gt;'), template.id);
  }
  assertOnlySafeTags(R.render(full(), OPTIONS).html, 'assinatura comum');
});

test('links e imagens perigosos são descartados', () => {
  assert.equal(R.linkUrl('javascript:alert(1)'), '');
  assert.equal(R.linkUrl('JaVaScRiPt:alert(1)'), '');
  assert.equal(R.linkUrl('data:text/html,<script>'), '');
  assert.equal(R.linkUrl('vbscript:x'), '');
  assert.equal(R.linkUrl('https://a.com/" onclick="x'), '');
  assert.equal(R.linkUrl('exemplo.com.br/pagina'), 'https://exemplo.com.br/pagina');
  assert.equal(R.linkUrl('mailto:a@b.co'), 'mailto:a@b.co');
  assert.equal(R.imageUrl('javascript:alert(1)', 'https://x'), '');
  assert.equal(R.imageUrl('//evil.com/a.png', 'https://x'), '');
  assert.equal(R.imageUrl('data:image/png;base64,AAAA', 'https://x'), '');
  assert.equal(R.imageUrl('/uploads/a/b.png', 'https://x'), 'https://x/uploads/a/b.png');

  const data = full();
  data.logo.link = 'javascript:alert(1)';
  data.social = [{ type: 'linkedin', url: 'javascript:alert(1)', show: true }];
  data.fields.website.value = 'javascript:alert(1)';
  data.fields.email.value = 'a@b.co" onclick="x';
  const { html } = R.render(data, OPTIONS);
  assert.ok(!/href="javascript:/i.test(html));
  assert.ok(!html.includes('onclick="x"'));
});

test('telefones brasileiros ganham o código do país nos links', () => {
  assert.equal(R.phoneDigits('(11) 2970-3020'), '551129703020');
  assert.equal(R.phoneDigits('065 99689-3826'), '5565996893826');
  assert.equal(R.phoneDigits('+1 (415) 555-0100'), '14155550100');
  assert.equal(R.phoneDigits('+55 11 2970-3020'), '551129703020');
  assert.equal(R.phoneDigits('ramal'), '');
});

test('foto redonda usa o arquivo já recortado; outros formatos usam o original', () => {
  const data = full();
  data.template = 'foto';
  assert.ok(R.render(data, OPTIONS).html.includes('bbbbbbbbbbbbbbbbbbbbbbbb-c.png'));
  data.photo.shape = 'square';
  const square = R.render(data, OPTIONS).html;
  assert.ok(square.includes('bbbbbbbbbbbbbbbbbbbbbbbb.png') && !square.includes('-c.png'));
});

test('trocar de modelo não altera os dados', () => {
  const data = R.normalize(full());
  const before = JSON.stringify({ ...data, template: '' });
  for (const template of R.TEMPLATES) {
    data.template = template.id;
    R.render(data, OPTIONS);
    assert.equal(JSON.stringify({ ...data, template: '' }), before);
  }
});

test('dados incompletos, antigos ou inválidos não quebram o desenho', () => {
  for (const bad of [null, undefined, 'texto', 42, [], { fields: null, social: 'x', style: { font: 'Comic Sans', colorName: 'red', sizeName: 999 } }]) {
    const normal = R.normalize(bad);
    assert.equal(normal.style.font, 'Arial');
    assert.equal(normal.style.colorName, '#2b2f2c');
    assert.ok(normal.style.sizeName <= 28);
    assert.equal(typeof R.render(bad, OPTIONS).html, 'string');
  }
});

test('documento baixado é HTML completo, sem scripts nem CSS externo', () => {
  const doc = R.documentHtml(full(), OPTIONS);
  assert.ok(doc.startsWith('<!DOCTYPE html>'));
  assert.ok(doc.includes('<meta charset="utf-8">') && doc.includes('<title>Assinatura de Ana Souza</title>'));
  assert.ok(!/<script|<link|<style|@import/i.test(doc));
});

test('versão em texto traz os dados principais', () => {
  const text = R.plainText(full());
  assert.ok(text.startsWith('Ana Souza\nGerente de Marketing | Marketing | Empresa Exemplo'));
  assert.ok(text.includes('E-mail: ana@exemplo.com.br') && text.includes('LinkedIn: https://linkedin.com/company/exemplo'));
});

test('ordem dos contatos é respeitada', () => {
  const data = full();
  data.order = ['email', 'address', 'phone', 'mobile', 'whatsapp', 'website'];
  const { html } = R.render(data, OPTIONS);
  assert.ok(html.indexOf('mailto:') < html.indexOf('maps/search') && html.indexOf('maps/search') < html.indexOf('tel:+551129703020'));
});
