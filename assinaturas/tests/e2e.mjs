// Teste de ponta a ponta da plataforma, com navegador real (Chromium via Playwright).
//
// Percorre o critério de entrega da primeira versão:
//   KIWEE instala, cadastra e convida empresas; o cliente aceita o convite, configura o modelo padrão,
//   cria duas assinaturas, reabre e edita cada uma, copia e baixa; uma empresa não acessa dados da outra.
//
// Uso: node tests/e2e.mjs            (sobe um servidor PHP próprio com banco SQLite temporário)
//      SHOTS=1 node tests/e2e.mjs    (também grava capturas de tela em tests/.tmp/shots)
import { createRequire } from 'node:module';
import { spawn } from 'node:child_process';
import { createServer } from 'node:http';
import { readFileSync, writeFileSync, mkdirSync, rmSync, existsSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const require = createRequire(import.meta.url);
const { chromium } = require('playwright');

const here = dirname(fileURLToPath(import.meta.url));
const root = join(here, '..');
const tmp = join(here, '.tmp');
const shots = join(tmp, 'shots');
const PORT = 8097;
const FIXTURE_PORT = 8096;
const BASE = `http://127.0.0.1:${PORT}`;
const FIXTURES = `http://127.0.0.1:${FIXTURE_PORT}`;
const SHOTS = process.env.SHOTS === '1';
// Por padrão usa SQLite. Com KW_TEST_DB=mysql usa um MySQL ou MariaDB local (o banco é esvaziado antes do teste).
const MYSQL = process.env.KW_TEST_DB === 'mysql';
const DB_CONFIG = MYSQL
  ? `['driver' => 'mysql', 'host' => '${process.env.KW_TEST_DB_HOST || '127.0.0.1'}', 'port' => 3306, 'name' => '${process.env.KW_TEST_DB_NAME || 'kw_teste'}', `
    + `'user' => '${process.env.KW_TEST_DB_USER || 'kw_teste'}', 'pass' => '${process.env.KW_TEST_DB_PASS || ''}']`
  : `['driver' => 'sqlite', 'path' => __DIR__ . '/e2e.sqlite']`;
const PASSWORD = 'senha-de-teste-123';

let failures = 0;
let passed = 0;
function check(name, condition, detail = '') {
  if (condition) {
    passed++;
    console.log(`  ok    ${name}`);
  } else {
    failures++;
    console.log(`  FALHA ${name}${detail ? ' -> ' + detail : ''}`);
  }
}
const section = (title) => console.log(`\n${title}`);

/* ----------------------------- Preparação ----------------------------- */

mkdirSync(shots, { recursive: true });
for (const file of ['e2e.sqlite', 'e2e.sqlite-wal', 'e2e.sqlite-shm']) {
  rmSync(join(tmp, file), { force: true });
}
const mailLog = join(root, 'storage', 'mail.log');
rmSync(mailLog, { force: true });
writeFileSync(join(tmp, 'e2e-config.php'), `<?php
return [
    'app_url' => '${BASE}',
    'app_name' => 'KIWEE Assinaturas',
    'timezone' => 'America/Sao_Paulo',
    'db' => ${DB_CONFIG},
    'mail' => ['driver' => 'log', 'from_email' => 'teste@kiwee.test', 'from_name' => 'KIWEE Assinaturas'],
    'setup_key' => 'chave-de-teste-e2e',
    'invite_days' => 7,
    'debug' => true,
    'dev_allow_insecure_url_import' => true,
];
`);

if (MYSQL) {
  // Esvazia o banco de teste para o roteiro começar da instalação.
  const { execFileSync } = await import('node:child_process');
  execFileSync('php', ['-r', `
    $c = (require '${join(tmp, 'e2e-config.php')}')['db'];
    $pdo = new PDO("mysql:host={$c['host']};dbname={$c['name']}", $c['user'], $c['pass']);
    $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
    foreach ($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $t) { $pdo->exec("DROP TABLE \`$t\`"); }
  `]);
}

const php = spawn('php', ['-S', `127.0.0.1:${PORT}`, '-t', 'public', 'bin/dev-router.php'], {
  cwd: root,
  env: { ...process.env, KW_CONFIG: join(tmp, 'e2e-config.php'), PHP_CLI_SERVER_WORKERS: '4' },
  stdio: ['ignore', 'ignore', 'pipe'],
});
let phpErrors = '';
php.stderr.on('data', (chunk) => {
  const text = chunk.toString();
  if (/PHP (Warning|Fatal|Notice|Deprecated|Parse)|KIWEE Assinaturas:/.test(text)) { phpErrors += text; }
});

// Servidor de apoio que simula o site do cliente: imagem pública, página, link privado, etc.
const fixtureServer = createServer((req, res) => {
  const send = (status, type, body) => { res.writeHead(status, { 'Content-Type': type }); res.end(body); };
  if (req.url === '/logo.png') { return send(200, 'image/png', readFileSync(join(here, 'fixtures', 'logo.png'))); }
  if (req.url === '/redireciona') { res.writeHead(302, { Location: '/logo.png' }); return res.end(); }
  if (req.url === '/pagina') { return send(200, 'text/html; charset=utf-8', '<!DOCTYPE html><html><body>Faça login para ver o arquivo</body></html>'); }
  if (req.url === '/privado.png') { return send(403, 'text/plain', 'Forbidden'); }
  if (req.url === '/vetor.svg') { return send(200, 'image/svg+xml', readFileSync(join(here, 'fixtures', 'vetor.svg'))); }
  return send(404, 'text/plain', 'Not found');
}).listen(FIXTURE_PORT, '127.0.0.1');

async function waitForServer() {
  for (let i = 0; i < 50; i++) {
    try { await fetch(`${BASE}/entrar`, { redirect: 'manual' }); return; } catch { await new Promise((r) => setTimeout(r, 100)); }
  }
  throw new Error('O servidor PHP não respondeu.');
}

function lastMailLink(email, kind) {
  const lines = readFileSync(mailLog, 'utf8').trim().split('\n').map((line) => JSON.parse(line));
  const pattern = new RegExp(`https?://\\S+/${kind}/[a-f0-9]{64}`);
  const mail = lines.reverse().find((item) => item.to === email && pattern.test(item.text));
  return mail ? mail.text.match(pattern)[0] : null;
}
const lastInviteLink = (email) => lastMailLink(email, 'convite');

async function shot(page, name) {
  if (SHOTS) { await page.screenshot({ path: join(shots, `${name}.png`), fullPage: true }); }
}

async function login(page, email) {
  await page.goto(`${BASE}/entrar`);
  await page.fill('#email', email);
  await page.fill('#senha', PASSWORD);
  await page.click('button[type="submit"]');
}

async function acceptInvite(page, link, name) {
  await page.goto(link);
  await page.fill('#nome', name);
  await page.fill('#senha', PASSWORD);
  await page.fill('#senha2', PASSWORD);
  await page.click('button[type="submit"]');
}

/** HTML que está sendo mostrado na prévia do editor. */
const previewHtml = (page) => page.evaluate(() => document.querySelector('[data-preview-frame]').contentDocument.body.innerHTML);

async function saveEditor(page) {
  await page.click('.editor-actions [data-action="save"]');
  await page.waitForFunction(() => /Salvo às/.test(document.querySelector('[data-save-state]').textContent));
}

/* -------------------------------- Roteiro -------------------------------- */

let browser;
try {
  await waitForServer();
  browser = await chromium.launch();
  const version = browser.version();
  console.log(`Chromium ${version} · banco ${MYSQL ? 'MySQL/MariaDB' : 'SQLite'}`);

  const adminContext = await browser.newContext({ viewport: { width: 1280, height: 900 }, deviceScaleFactor: SHOTS ? 2 : 1 });
  const admin = await adminContext.newPage();
  const consoleErrors = [];
  const watch = (page, label) => {
    page.on('console', (message) => {
      // Respostas 422 são as recusas esperadas (senha curta, imagem inválida) e o navegador as registra como erro de rede.
      if (message.type() === 'error' && !/status of 422/.test(message.text())) { consoleErrors.push(`${label}: ${message.text()}`); }
    });
    page.on('pageerror', (error) => consoleErrors.push(`${label}: ${error.message}`));
  };
  watch(admin, 'admin');

  section('1. Instalação e acesso por convite');
  await admin.goto(BASE);
  check('antes da instalação tudo leva para /instalar', admin.url().endsWith('/instalar'));
  await shot(admin, '01-instalar');
  await admin.fill('#chave', 'chave-errada');
  await admin.fill('#nome', 'Fabiano KIWEE');
  await admin.fill('#email', 'admin@kiwee.test');
  await admin.fill('#senha', PASSWORD);
  await admin.fill('#senha2', PASSWORD);
  await admin.click('button[type="submit"]');
  check('chave de instalação errada é recusada', (await admin.textContent('.alert-error')).includes('não confere'));
  await admin.fill('#chave', 'chave-de-teste-e2e');
  await admin.fill('#senha', PASSWORD);
  await admin.fill('#senha2', PASSWORD);
  await admin.click('button[type="submit"]');
  await admin.waitForURL(`${BASE}/admin`);
  check('instalação cria o administrador e abre o painel', await admin.isVisible('h1:has-text("Empresas")'));
  const again = await admin.request.get(`${BASE}/instalar`, { maxRedirects: 0 });
  check('instalação fica indisponível depois do primeiro administrador', again.status() === 303);
  const signup = await admin.request.get(`${BASE}/cadastro`);
  check('não existe cadastro público', signup.status() === 404);
  await shot(admin, '02-admin-vazio');

  section('2. Painel KIWEE: cadastrar e convidar empresas');
  await admin.click('.page-head [data-open-dialog="nova-empresa"]');
  await admin.fill('#empresa', 'Agro Conexão');
  await admin.fill('#nova-empresa #nome', 'Bruna Cliente');
  await admin.fill('#nova-empresa #email', 'bruna@agro.test');
  await admin.click('#nova-empresa button[type="submit"]');
  await admin.waitForURL(/\/admin\/empresas\/\d+$/);
  const companyAUrl = admin.url();
  const shownLink = await admin.inputValue('.copy-line input');
  const linkA = lastInviteLink('bruna@agro.test');
  check('convite é enviado por e-mail', !!linkA);
  check('link do convite também aparece para o administrador', shownLink === linkA, shownLink);
  check('convite pendente aparece na empresa', await admin.isVisible('text=Convites pendentes'));
  await shot(admin, '03-admin-empresa');

  await admin.goto(`${BASE}/admin`);
  await admin.click('.page-head [data-open-dialog="nova-empresa"]');
  await admin.fill('#empresa', 'Beta Engenharia');
  await admin.fill('#nova-empresa #nome', 'Carlos Beta');
  await admin.fill('#nova-empresa #email', 'carlos@beta.test');
  await admin.click('#nova-empresa button[type="submit"]');
  await admin.waitForURL(/\/admin\/empresas\/\d+$/);
  const companyBUrl = admin.url();
  const linkB = lastInviteLink('carlos@beta.test');
  await admin.goto(`${BASE}/admin`);
  check('as duas empresas aparecem na lista', (await admin.locator('.table tbody tr').count()) === 2);
  await shot(admin, '04-admin-empresas');

  section('3. Cliente aceita o convite');
  const contextA = await browser.newContext({ viewport: { width: 1360, height: 900 }, acceptDownloads: true, deviceScaleFactor: SHOTS ? 2 : 1 });
  await contextA.grantPermissions(['clipboard-read', 'clipboard-write'], { origin: BASE });
  const a = await contextA.newPage();
  watch(a, 'cliente A');
  await a.goto(linkA);
  await shot(a, '05-convite');
  await a.fill('#nome', 'Bruna Cliente');
  await a.fill('#senha', 'curta');
  await a.fill('#senha2', 'curta');
  await a.evaluate(() => document.querySelector('form').setAttribute('novalidate', ''));
  await a.click('button[type="submit"]');
  check('senha curta é recusada no convite', (await a.textContent('.alert-error')).includes('pelo menos'));
  await acceptInvite(a, linkA, 'Bruna Cliente');
  await a.waitForURL(`${BASE}/assinaturas`);
  check('convite aceito leva à área da empresa', (await a.textContent('.page-head')).includes('Agro Conexão'));
  const reused = await a.request.get(linkA);
  check('convite usado não funciona de novo', reused.status() === 410);
  check('usuário do cliente não entra no painel KIWEE', (await a.request.get(`${BASE}/admin`)).status() === 403);
  check('usuário do cliente não abre empresa pelo painel', (await a.request.get(companyBUrl)).status() === 403);
  await shot(a, '06-lista-vazia');

  section('4. Modelo padrão da empresa');
  await a.goto(`${BASE}/padrao`);
  await a.click('[data-panel-tab="dados"]');
  check('no padrão, dados pessoais ficam para cada assinatura', await a.isDisabled('#f-name'));
  await a.fill('#f-phone', '(11) 2970-3020');
  await a.fill('#f-website', 'agroconexao.com.br');
  await a.fill('#f-address', 'Avenida Brasil, 188, sala 2\nCampo Verde/MT CEP 78840-085');
  await a.click('[data-panel-tab="imagens"]');
  await a.click('[data-image-slot="logo"] [data-action="pick-image"]');
  await a.setInputFiles('[data-upload-input]', join(here, 'fixtures', 'logo.png'));
  await a.waitForFunction(() => !document.getElementById('dlg-image').open);
  let html = await previewHtml(a);
  const logoMatch = html.match(/src="([^"]+\/uploads\/[a-f0-9]{20}\/[a-f0-9]{24}\.png)"/);
  check('logotipo enviado aparece na prévia com endereço da plataforma', !!logoMatch, html.slice(0, 200));
  const logoUrl = logoMatch ? logoMatch[1] : '';
  const anonymous = await browser.newContext();
  const logoResponse = await anonymous.request.get(logoUrl);
  check('imagem enviada abre sem login (necessário para os e-mails)', logoResponse.status() === 200 && logoResponse.headers()['content-type'] === 'image/png');

  await a.click('[data-panel-tab="redes"]');
  await a.click('[data-action="add-social"]');
  await a.fill('[data-social-list] .social-row:nth-child(1) input[type="text"]', 'https://www.linkedin.com/company/agro');
  await a.click('[data-action="add-social"]');
  await a.fill('[data-social-list] .social-row:nth-child(2) input[type="text"]', 'instagram.com/agro');
  await a.click('[data-action="add-social"]');
  await a.click('[data-panel-tab="aparencia"]');
  await a.fill('[data-bind="style.colorTitle"][data-hex]', '#2aa043');
  await a.fill('[data-bind="style.colorIcon"][data-hex]', '#2aa043');
  await a.selectOption('#f-font', 'Verdana');
  await a.waitForTimeout(400);
  html = await previewHtml(a);
  check('cor da marca aplicada na prévia', html.includes('#2aa043') && html.includes('/icones/outline-square/2aa043/linkedin.png'));
  check('fonte escolhida tem fontes de reserva', html.includes('font-family:Verdana, Geneva, sans-serif'));
  check('rede sem endereço não aparece', !html.includes('facebook.png'));
  check('prévia do padrão usa dados de exemplo', html.includes('Nome do Colaborador'));
  await a.click('[data-panel-tab="modelo"]');
  await a.waitForTimeout(500);
  await shot(a, '07-padrao-modelos');
  await saveEditor(a);
  await a.click('[data-panel-tab="aparencia"]');
  await shot(a, '08-padrao-aparencia');

  section('5. Assinaturas dos colaboradores');
  await a.goto(`${BASE}/assinaturas/nova`);
  await a.waitForSelector('[data-sig-gallery] .template-card');
  check('tela de nova assinatura oferece o padrão e os cinco modelos', (await a.locator('[data-sig-gallery] .template-card').count()) === 5);
  await a.waitForTimeout(400);
  await shot(a, '09-nova');
  await a.click('.template-card-wide');
  await a.waitForSelector('[data-editor]');
  await a.click('.editor-actions [data-action="save"]');
  check('assinatura sem nome não é salva', await a.isVisible('.toast-error'));
  await a.fill('#f-name', 'Bruno Mamede');
  await a.fill('#f-title', 'Gerente de Marketing');
  await a.fill('#f-mobile', '(65) 99689-3826');
  await a.fill('#f-email', 'bruno@agroconexao.com.br');
  html = await previewHtml(a);
  check('nova assinatura herda logotipo, cores e links do padrão', html.includes(logoUrl) && html.includes('#2aa043') && html.includes('linkedin.png'));
  check('prévia atualiza enquanto digita', html.includes('Bruno Mamede') && html.includes('Gerente de Marketing'));
  check('telefone vira link clicável', html.includes('href="tel:+5565996893826"'));
  check('e-mail vira link clicável', html.includes('href="mailto:bruno@agroconexao.com.br"'));
  check('site ganha https', html.includes('href="https://agroconexao.com.br"'));
  check('campo vazio (WhatsApp) não gera linha', !html.includes('whatsapp.png'));
  await saveEditor(a);
  const sig1Url = a.url();
  check('ao salvar, a assinatura ganha endereço próprio', /\/assinaturas\/\d+$/.test(sig1Url), sig1Url);
  const sig1Id = sig1Url.split('/').pop();
  await shot(a, '10-editor-horizontal');

  await a.goto(`${BASE}/assinaturas/nova?modelo=foto`);
  await a.fill('#f-name', 'Ana Souza');
  await a.fill('#f-title', 'Diretora Comercial');
  await a.fill('#f-department', 'Vendas');
  await a.fill('#f-whatsapp', '11 98888-7777');
  await a.fill('#f-email', 'ana@agroconexao.com.br');
  await a.click('[data-panel-tab="imagens"]');
  await a.click('[data-image-slot="photo"] [data-action="pick-image"]');
  await a.setInputFiles('[data-upload-input]', join(here, 'fixtures', 'foto.jpg'));
  await a.waitForFunction(() => !document.getElementById('dlg-image').open);
  html = await previewHtml(a);
  check('foto enviada usa a versão recortada em círculo', /-c\.png" width="88" height="88" alt="Foto de Ana Souza"/.test(html), html.slice(0, 300));
  check('WhatsApp vira link wa.me com código do país', html.includes('href="https://wa.me/5511988887777"'));
  await saveEditor(a);
  const sig2Url = a.url();
  const sig2Id = sig2Url.split('/').pop();
  await shot(a, '11-editor-foto');

  await a.goto(`${BASE}/assinaturas`);
  const names = await a.locator('.table .row-title').allTextContents();
  check('lista mostra as assinaturas pelo nome, em ordem alfabética', names.join('|') === 'Ana Souza|Bruno Mamede', names.join('|'));
  await shot(a, '12-lista');
  await a.fill('#q', 'marketing');
  await a.click('.search-bar button[type="submit"]');
  check('busca por cargo encontra só o colaborador certo', (await a.locator('.table .row-title').allTextContents()).join('|') === 'Bruno Mamede');
  await a.fill('#q', 'ANA@AGRO');
  await a.click('.search-bar button[type="submit"]');
  check('busca por e-mail ignora maiúsculas', (await a.locator('.table .row-title').allTextContents()).join('|') === 'Ana Souza');
  await a.fill('#q', '100%_nada');
  await a.click('.search-bar button[type="submit"]');
  check('busca sem resultado mostra aviso', await a.isVisible('text=Nenhuma assinatura encontrada'));

  section('6. Reabrir e editar cada assinatura');
  await a.goto(sig1Url);
  check('dados salvos voltam ao reabrir', (await a.inputValue('#f-name')) === 'Bruno Mamede' && (await a.inputValue('#f-mobile')) === '(65) 99689-3826');
  await a.fill('#f-title', 'Diretor de Marketing');
  await a.click('[data-panel-tab="modelo"]');
  await a.click('[data-template="destaque"]');
  html = await previewHtml(a);
  check('trocar de modelo preserva os dados', html.includes('Bruno Mamede') && html.includes('Diretor de Marketing') && html.includes('border-left:3px solid'));
  await a.click('[data-template="horizontal"]');
  await a.click('[data-panel-tab="dados"]');
  await a.uncheck('[data-bind="fields.address.show"]');
  html = await previewHtml(a);
  check('campo oculto some da assinatura', !html.includes('Campo Verde'));
  await a.click('[data-move="email"][data-dir="-1"]');
  await a.click('[data-move="email"][data-dir="-1"]');
  await a.click('[data-move="email"][data-dir="-1"]');
  html = await previewHtml(a);
  check('ordem dos contatos pode ser alterada', html.indexOf('mailto:') < html.indexOf('tel:+551129703020'));
  await saveEditor(a);
  await a.reload();
  html = await previewHtml(a);
  check('edição persiste depois de recarregar', (await a.inputValue('#f-title')) === 'Diretor de Marketing' && !html.includes('Campo Verde')
    && html.indexOf('mailto:') < html.indexOf('tel:+551129703020'));

  await a.goto(sig2Url);
  html = await previewHtml(a);
  check('alterar uma assinatura não muda a outra', html.includes('Diretora Comercial') && html.includes('Campo Verde') && (await a.inputValue('#f-name')) === 'Ana Souza');
  await a.fill('#f-department', 'Comercial');
  await saveEditor(a);
  await a.reload();
  check('segunda assinatura também reabre e salva', (await a.inputValue('#f-department')) === 'Comercial');
  await a.goto(`${BASE}/padrao`);
  await a.click('[data-panel-tab="dados"]');
  check('editar assinaturas não altera o modelo padrão', (await a.inputValue('#f-address')).includes('Campo Verde') && await a.isChecked('[data-bind="fields.address.show"]'));

  section('7. Campos vazios não deixam espaços nem divisórias');
  await a.goto(`${BASE}/assinaturas/nova?modelo=horizontal`);
  await a.fill('#f-name', 'Só Nome');
  for (const key of ['phone', 'website', 'address']) { await a.fill(`#f-${key}`, ''); }
  await a.fill('#f-company', '');
  await a.click('[data-panel-tab="imagens"]');
  await a.click('[data-action="clear-image"][data-kind="logo"]');
  await a.click('[data-panel-tab="redes"]');
  while (await a.locator('[data-social-list] .icon-btn-danger').count()) { await a.click('[data-social-list] .icon-btn-danger >> nth=0'); }
  html = await previewHtml(a);
  check('só o nome: sem divisórias', !/border-(top|bottom|left)/.test(html), html);
  check('só o nome: sem imagens nem células vazias', !html.includes('<img') && !/<td[^>]*>\s*<\/td>/.test(html));
  check('só o nome: sem espaços sobrando', !/padding[^;"]*[1-9]/.test(html), html);

  section('8. Copiar e baixar');
  await a.goto(sig1Url);
  await a.click('.editor-actions [data-action="copy"]');
  await a.waitForSelector('.toast');
  const clip = await a.evaluate(async () => {
    const items = await navigator.clipboard.read();
    const out = {};
    for (const type of items[0].types) { out[type] = await (await items[0].getType(type)).text(); }
    return out;
  });
  check('copiar coloca a versão formatada (text/html) na área de transferência', (clip['text/html'] || '').includes('<table') && clip['text/html'].includes('Bruno Mamede'));
  check('copiar também leva uma versão em texto simples', (clip['text/plain'] || '').startsWith('Bruno Mamede'));

  // Cola em uma área editável, como a caixa de assinatura de um cliente de e-mail na web.
  const paste = await contextA.newPage();
  await paste.goto(`${BASE}/entrar`);
  await paste.setContent('<div id="caixa" contenteditable="true" style="min-height:200px;width:700px"></div>');
  await paste.focus('#caixa');
  await paste.keyboard.press('Control+V');
  await paste.waitForFunction(() => document.querySelector('#caixa table'), null, { timeout: 5000 }).catch(() => {});
  const pasted = await paste.evaluate(() => {
    const box = document.getElementById('caixa');
    return {
      tables: box.querySelectorAll('table').length,
      links: Array.from(box.querySelectorAll('a')).map((link) => link.getAttribute('href')),
      images: Array.from(box.querySelectorAll('img')).map((image) => ({ alt: image.alt, loaded: image.naturalWidth > 0 })),
      text: box.innerText,
    };
  });
  check('colagem mantém a estrutura em tabelas', pasted.tables > 0, JSON.stringify(pasted).slice(0, 200));
  check('colagem mantém os links clicáveis', pasted.links.includes('mailto:bruno@agroconexao.com.br') && pasted.links.includes('tel:+5565996893826'));
  check('colagem mantém o texto selecionável', pasted.text.includes('Bruno Mamede') && pasted.text.includes('Diretor de Marketing'));
  await paste.waitForTimeout(600);
  const pastedImages = await paste.evaluate(() => Array.from(document.querySelectorAll('#caixa img')).map((image) => image.naturalWidth > 0 && image.alt !== ''));
  check('imagens coladas carregam e têm texto alternativo', pastedImages.length > 0 && pastedImages.every(Boolean), JSON.stringify(pastedImages));
  if (SHOTS) { await paste.screenshot({ path: join(shots, '13-colagem.png') }); }
  await paste.close();

  const [download] = await Promise.all([a.waitForEvent('download'), a.click('.editor-actions [data-action="download"]')]);
  const file = readFileSync(await download.path(), 'utf8');
  check('arquivo baixado tem nome do colaborador', download.suggestedFilename() === 'assinatura-bruno-mamede.html', download.suggestedFilename());
  check('HTML baixado é um documento completo em tabelas', file.startsWith('<!DOCTYPE html>') && file.includes('<table role="presentation"'));
  check('HTML baixado não usa JavaScript, CSS externo nem bloco de estilo', !/<script|<link|<style|@import|javascript:/i.test(file));
  check('toda imagem tem texto alternativo e endereço completo', (file.match(/<img /g) || []).length > 0
    && (file.match(/<img /g) || []).length === (file.match(/<img src="http[^"]+"[^>]* alt="[^"]+"/g) || []).length);
  check('HTML cabe no limite de 10.000 caracteres do Gmail', file.length < 10000, String(file.length));
  writeFileSync(join(tmp, 'assinatura-exemplo.html'), file);

  await a.goto(`${BASE}/assinaturas`);
  await a.click(`[data-copy-signature="${sig2Id}"]`);
  await a.waitForSelector('.toast');
  const listClip = await a.evaluate(async () => {
    const items = await navigator.clipboard.read();
    return (await (await items[0].getType('text/html')).text());
  });
  check('copiar direto da lista funciona', listClip.includes('Ana Souza') && listClip.includes('<table'));

  section('9. Link de instalação do colaborador');
  await a.goto(sig1Url);
  await a.click('.editor-actions [data-action="share"]');
  const shareUrl = await a.inputValue('[data-share-url]');
  check('link de instalação é gerado', /\/i\/[a-f0-9]{40}$/.test(shareUrl), shareUrl);
  const guest = await anonymous.newPage();
  watch(guest, 'colaborador');
  await guest.goto(shareUrl);
  await guest.waitForSelector('[data-preview-frame]');
  check('colaborador abre a assinatura sem login', (await previewHtml(guest)).includes('Bruno Mamede'));
  check('página do colaborador traz instruções de Gmail, Outlook e Apple Mail', (await guest.locator('[data-tab]').allTextContents()).join('|') === 'Gmail|Outlook|Apple Mail');
  await shot(guest, '14-link-colaborador');
  check('link inválido não revela nada', (await anonymous.request.get(`${BASE}/i/${'0'.repeat(40)}`)).status() === 404);

  section('10. Importação de imagem por URL');
  const api = async (page, path, body) => {
    const csrf = await page.evaluate(() => JSON.parse(document.getElementById('kw-boot').textContent).csrf);
    const response = await page.request.post(`${BASE}${path}`, { data: body, headers: { 'X-CSRF-Token': csrf } });
    return { status: response.status(), json: await response.json().catch(() => ({})) };
  };
  await a.goto(sig1Url);
  let result = await api(a, '/api/imagem-url', { url: `${FIXTURES}/logo.png`, tipo: 'logo', copiar: false });
  check('imagem pública direta é aceita com as medidas', result.json.ok === true && result.json.image.width === 480 && result.json.hosted === false, JSON.stringify(result.json));
  result = await api(a, '/api/imagem-url', { url: `${FIXTURES}/redireciona`, tipo: 'logo', copiar: true });
  check('com cópia, a imagem passa a ficar hospedada na plataforma', result.json.ok === true && result.json.hosted === true && /^\/uploads\//.test(result.json.image.url), JSON.stringify(result.json));
  result = await api(a, '/api/imagem-url', { url: `${FIXTURES}/pagina`, tipo: 'logo' });
  check('link que abre uma página recebe mensagem clara', result.status === 422 && result.json.error.includes('abre uma página'), result.json.error);
  result = await api(a, '/api/imagem-url', { url: `${FIXTURES}/privado.png`, tipo: 'logo' });
  check('link privado recebe mensagem clara', result.status === 422 && result.json.error.includes('privado'), result.json.error);
  result = await api(a, '/api/imagem-url', { url: `${FIXTURES}/nao-existe.png`, tipo: 'logo' });
  check('link quebrado recebe mensagem clara', result.status === 422 && result.json.error.includes('Nenhum arquivo'), result.json.error);
  result = await api(a, '/api/imagem-url', { url: `${FIXTURES}/vetor.svg`, tipo: 'logo' });
  check('SVG é recusado com explicação', result.status === 422 && result.json.error.includes('SVG'), result.json.error);
  result = await api(a, '/api/imagem-url', { url: 'ftp://servidor.exemplo/logo.png', tipo: 'logo' });
  check('endereço ftp é recusado com orientação', result.status === 422 && result.json.error.includes('https://'), result.json.error);
  result = await api(a, '/api/imagem-url', { url: 'https://drive.google.com/drive/folders/1AbCdEfGhIjKlMnOp', tipo: 'logo' });
  check('link de pasta do Google Drive recebe orientação', result.status === 422 && result.json.error.includes('pasta'), result.json.error);

  // Pela tela: erro aparece dentro da janela e a imagem válida é aplicada.
  await a.click('[data-panel-tab="imagens"]');
  await a.click('[data-image-slot="banner"] [data-action="pick-image"]');
  await a.click('#dlg-image [data-tab="url"]');
  await a.fill('[data-url-input]', `${FIXTURES}/pagina`);
  await a.click('[data-action="check-url"]');
  await a.waitForSelector('[data-image-error]:not([hidden])');
  check('erro do link aparece na janela de imagem', (await a.textContent('[data-image-error]')).includes('abre uma página'));
  await shot(a, '15-url-privada');
  await a.fill('[data-url-input]', `${FIXTURES}/logo.png`);
  await a.uncheck('[data-url-copy]');
  await a.click('[data-action="check-url"]');
  await a.waitForFunction(() => !document.getElementById('dlg-image').open);
  html = await previewHtml(a);
  check('imagem por link direto entra na assinatura', html.includes(`${FIXTURES}/logo.png`));

  // Envio de arquivos inválidos.
  await a.click('[data-image-slot="logo"] [data-action="pick-image"]');
  await a.setInputFiles('[data-upload-input]', join(here, 'fixtures', 'falso.png'));
  await a.waitForSelector('[data-image-error]:not([hidden])');
  check('arquivo que não é imagem é recusado', (await a.textContent('[data-image-error]')).includes('não é uma imagem válida'));
  await a.setInputFiles('[data-upload-input]', join(here, 'fixtures', 'vetor.svg'));
  await a.waitForFunction(() => document.querySelector('[data-image-error]').textContent.includes('SVG'));
  check('SVG enviado é recusado com explicação', true);
  await a.click('#dlg-image [data-tab="library"]');
  await a.waitForSelector('.asset-card');
  check('biblioteca lista os arquivos da empresa', (await a.locator('.asset-card').count()) >= 3);
  await shot(a, '16-biblioteca');
  await a.click('#dlg-image [data-close-dialog]');

  section('11. Isolamento entre empresas');
  const contextB = await browser.newContext({ viewport: { width: 1280, height: 900 } });
  const b = await contextB.newPage();
  watch(b, 'cliente B');
  await acceptInvite(b, linkB, 'Carlos Beta');
  await b.waitForURL(`${BASE}/assinaturas`);
  check('empresa B começa sem assinaturas', await b.isVisible('text=Nenhuma assinatura criada ainda'));
  const direct = await b.request.get(sig1Url);
  check('URL direta da assinatura de outra empresa responde 404', direct.status() === 404);
  check('a resposta não vaza os dados', !(await direct.text()).includes('Bruno'));
  check('API de leitura da assinatura alheia responde 404', (await b.request.get(`${BASE}/api/assinaturas/${sig1Id}`)).status() === 404);
  await b.goto(`${BASE}/assinaturas/nova?modelo=vertical`);
  result = await api(b, `/api/assinaturas/${sig1Id}`, { data: { fields: { name: { value: 'Invadido', show: true } } } });
  check('API de gravação na assinatura alheia responde 404', result.status === 404);
  const csrfB = await b.evaluate(() => JSON.parse(document.getElementById('kw-boot').textContent).csrf);
  for (const action of ['duplicar', 'excluir']) {
    const response = await b.request.post(`${BASE}/assinaturas/${sig1Id}/${action}`, { form: { _csrf: csrfB } });
    check(`${action} assinatura alheia responde 404`, response.status() === 404);
  }
  const assetsA = await (await a.request.get(`${BASE}/api/arquivos`)).json();
  const assetsB = await (await b.request.get(`${BASE}/api/arquivos`)).json();
  check('empresa B não enxerga os arquivos da empresa A', assetsA.assets.length >= 3 && assetsB.assets.length === 0);
  result = await api(b, `/api/arquivos/${assetsA.assets[0].id}/excluir`, {});
  check('empresa B não consegue excluir arquivo da empresa A', result.status === 404);
  result = await api(b, '/api/assinaturas', { data: { fields: { name: { value: 'Usa imagem alheia', show: true } }, logo: { url: assetsA.assets[0].url } } });
  check('assinatura da empresa B não pode apontar para arquivo da empresa A', result.status === 201 && result.json.data.logo.url === '');
  await a.goto(sig1Url);
  check('assinatura da empresa A continua intacta', (await a.inputValue('#f-name')) === 'Bruno Mamede');
  const noCsrf = await b.request.post(`${BASE}/api/assinaturas`, { data: { data: {} } });
  check('requisição sem token de segurança é recusada', noCsrf.status() === 419);
  const loggedOut = await anonymous.request.get(sig1Url, { maxRedirects: 0 });
  check('sem login, a URL da assinatura leva para a entrada', loggedOut.status() === 303);
  check('sem login, a API responde 401', (await anonymous.request.get(`${BASE}/api/assinaturas/${sig1Id}`)).status() === 401);

  section('12. Aplicar o padrão às assinaturas existentes');
  await a.goto(`${BASE}/padrao`);
  await a.click('[data-panel-tab="aparencia"]');
  await a.fill('[data-bind="style.colorTitle"][data-hex]', '#0055aa');
  await a.waitForTimeout(100);
  a.once('dialog', (dialog) => dialog.accept());
  await a.click('[data-action="apply-default"]');
  await a.waitForSelector('.toast:has-text("aplicado")');
  await a.goto(sig2Url);
  html = await previewHtml(a);
  check('nova cor do padrão chega às assinaturas', html.includes('#0055aa'));
  check('dados pessoais e foto são preservados', html.includes('Ana Souza') && html.includes('Diretora Comercial') && /-c\.png/.test(html));

  section('13. Duplicar e excluir');
  await a.goto(`${BASE}/assinaturas`);
  await a.click('.table tbody tr:nth-child(1) summary');
  await a.click('.table tbody tr:nth-child(1) form[action$="/duplicar"] button');
  await a.waitForURL(/\/assinaturas\/\d+$/);
  check('duplicar abre a cópia para edição', (await a.inputValue('#f-name')) === 'Ana Souza (cópia)');
  const copyUrl = a.url();
  await a.goto(`${BASE}/assinaturas`);
  const before = await a.locator('.table tbody tr').count();
  a.once('dialog', (dialog) => dialog.accept());
  await a.click('.table tbody tr:nth-child(2) summary');
  await a.click('.table tbody tr:nth-child(2) form[action$="/excluir"] button');
  await a.waitForSelector('.alert-success');
  check('excluir remove só a assinatura escolhida', (await a.locator('.table tbody tr').count()) === before - 1
    && !(await a.locator('.table .row-title').allTextContents()).includes('Ana Souza (cópia)'));
  check('assinatura excluída não abre mais', (await a.request.get(copyUrl)).status() === 404);

  section('14. Suspender e reativar acessos');
  await admin.goto(companyAUrl);
  admin.once('dialog', (dialog) => dialog.accept());
  await admin.click('form[action$="/status"] button:has-text("Suspender empresa")');
  await admin.waitForSelector('.alert-success');
  const kicked = await a.request.get(`${BASE}/assinaturas`, { maxRedirects: 0 });
  check('empresa suspensa: quem estava conectado perde o acesso na hora', kicked.status() === 303);
  await login(a, 'bruna@agro.test');
  check('empresa suspensa: login é recusado com explicação', (await a.textContent('.alert-error')).includes('suspenso'));
  check('empresa suspensa: link de instalação deixa de abrir', (await anonymous.request.get(shareUrl)).status() === 404);
  check('empresa suspensa: imagens já instaladas continuam no ar', (await anonymous.request.get(logoUrl)).status() === 200);
  await admin.click('form[action$="/status"] button:has-text("Reativar empresa")');
  await login(a, 'bruna@agro.test');
  await a.waitForURL(`${BASE}/assinaturas`);
  check('empresa reativada: login volta a funcionar', true);

  await admin.goto(companyBUrl);
  await admin.click('.people form[action$="/status"] button:has-text("Suspender")');
  check('usuário suspenso perde o acesso na hora', (await b.request.get(`${BASE}/assinaturas`, { maxRedirects: 0 })).status() === 303);
  await admin.click('.people form[action$="/status"] button:has-text("Reativar")');

  section('15. Administrador dentro da área de uma empresa');
  await admin.goto(companyBUrl);
  await admin.click('button:has-text("Abrir área da empresa")');
  await admin.waitForURL(`${BASE}/assinaturas`);
  check('administrador vê a área da empresa escolhida', (await admin.textContent('.acting-bar')).includes('Beta Engenharia'));
  check('dentro da empresa B, o administrador também não vê assinaturas da A', (await admin.request.get(sig1Url)).status() === 404);
  await shot(admin, '17-admin-na-empresa');
  await admin.click('.acting-bar button');
  await admin.waitForURL(`${BASE}/admin`);

  section('16. Convites, equipe e senha');
  await admin.goto(companyBUrl);
  await admin.fill('form[action$="/convites"] #nome', 'Dani Beta');
  await admin.fill('form[action$="/convites"] #email', 'dani@beta.test');
  await admin.click('form[action$="/convites"] button[type="submit"]');
  const firstLink = lastInviteLink('dani@beta.test');
  await admin.click('form[action$="/reenviar"] button');
  await admin.waitForSelector('.copy-line input');
  const secondLink = lastInviteLink('dani@beta.test');
  check('reenviar gera um novo link e invalida o anterior', secondLink !== firstLink && (await anonymous.request.get(firstLink)).status() === 410
    && (await anonymous.request.get(secondLink)).status() === 200);
  admin.once('dialog', (dialog) => dialog.accept());
  await admin.click('form[action$="/cancelar"] button');
  await admin.waitForSelector('.alert-success');
  check('convite cancelado deixa de funcionar', (await anonymous.request.get(secondLink)).status() === 410);
  await admin.fill('form[action$="/convites"] #nome', 'Carlos de novo');
  await admin.fill('form[action$="/convites"] #email', 'carlos@beta.test');
  await admin.click('form[action$="/convites"] button[type="submit"]');
  check('não é possível convidar um e-mail que já tem acesso', (await admin.textContent('.alert-error')).includes('Já existe'));

  await admin.goto(`${BASE}/admin/equipe`);
  await admin.fill('#nome', 'Segunda Admin');
  await admin.fill('#email', 'admin2@kiwee.test');
  await admin.click('form[action$="/equipe/convites"] button[type="submit"]');
  const adminInvite = lastInviteLink('admin2@kiwee.test');
  const contextAdmin2 = await browser.newContext();
  const admin2 = await contextAdmin2.newPage();
  await acceptInvite(admin2, adminInvite, 'Segunda Admin');
  await admin2.waitForURL(`${BASE}/admin`);
  check('convite de administrador dá acesso ao painel KIWEE', await admin2.isVisible('h1:has-text("Empresas")'));
  await admin.goto(`${BASE}/admin/equipe`);
  check('administrador não pode suspender a si mesmo', (await admin.locator('.people li:has-text("Você") form').count()) === 0);
  await shot(admin, '17b-equipe');

  const guestPage = await anonymous.newPage();
  await guestPage.goto(`${BASE}/esqueci-senha`);
  await guestPage.fill('#email', 'carlos@beta.test');
  await guestPage.click('button[type="submit"]');
  const knownText = await guestPage.textContent('.alert-success');
  await guestPage.goto(`${BASE}/esqueci-senha`);
  await guestPage.fill('#email', 'ninguem@beta.test');
  await guestPage.click('button[type="submit"]');
  check('recuperar senha responde igual para e-mail existente ou não', knownText === await guestPage.textContent('.alert-success'));
  const resetLink = lastMailLink('carlos@beta.test', 'redefinir-senha');
  check('link de nova senha é enviado só para quem tem acesso', !!resetLink && lastMailLink('ninguem@beta.test', 'redefinir-senha') === null);
  await guestPage.goto(resetLink);
  await guestPage.fill('#senha', 'outra-senha-456');
  await guestPage.fill('#senha2', 'outra-senha-456');
  await guestPage.click('button[type="submit"]');
  await guestPage.waitForURL(`${BASE}/entrar`);
  check('link de nova senha só vale uma vez', (await anonymous.request.get(resetLink)).status() === 410);
  await guestPage.fill('#email', 'carlos@beta.test');
  await guestPage.fill('#senha', PASSWORD);
  await guestPage.click('button[type="submit"]');
  check('senha antiga deixa de valer', (await guestPage.textContent('.alert-error')).includes('incorretos'));
  await guestPage.fill('#email', 'carlos@beta.test');
  await guestPage.fill('#senha', 'outra-senha-456');
  await guestPage.click('button[type="submit"]');
  await guestPage.waitForURL(`${BASE}/assinaturas`);
  check('nova senha funciona', true);
  await guestPage.click('button:has-text("Sair")');
  await guestPage.waitForURL(`${BASE}/entrar`);
  for (let attempt = 0; attempt < 9; attempt++) {
    await guestPage.fill('#email', 'dani@beta.test');
    await guestPage.fill('#senha', 'errada-' + attempt);
    await guestPage.click('button[type="submit"]');
  }
  check('muitas tentativas de senha bloqueiam o login por um tempo', (await guestPage.textContent('.alert-error')).includes('Muitas tentativas'));
  await guestPage.close();

  section('17. Celular');
  const phone = await browser.newContext({ viewport: { width: 390, height: 844 }, deviceScaleFactor: 2, isMobile: true, hasTouch: true });
  const m = await phone.newPage();
  watch(m, 'celular');
  await login(m, 'bruna@agro.test');
  await m.waitForURL(`${BASE}/assinaturas`);
  const overflowList = await m.evaluate(() => document.documentElement.scrollWidth > 391);
  check('lista cabe na tela do celular sem rolagem lateral', !overflowList);
  await shot(m, '18-celular-lista');
  await m.goto(sig1Url);
  await m.waitForSelector('[data-preview-frame]');
  await m.waitForTimeout(300);
  const overflowEditor = await m.evaluate(() => document.documentElement.scrollWidth > 391);
  check('editor cabe na tela do celular sem rolagem lateral', !overflowEditor);
  await m.click('[data-panel-tab="dados"]');
  await m.fill('#f-title', 'Diretor de Marketing e Vendas');
  check('edição pelo celular atualiza a prévia', (await previewHtml(m)).includes('Diretor de Marketing e Vendas'));
  await shot(m, '19-celular-editor');
  await m.click('[data-nav-toggle]');
  check('menu do celular abre', await m.isVisible('#menu-principal a:has-text("Modelo padrão")'));
  await m.goto(`${BASE}/como-instalar`);
  await shot(m, '20-celular-instalar');

  section('18. Modelos e ícones');
  await a.goto(sig1Url);
  for (const template of ['horizontal', 'vertical', 'compacta', 'foto', 'destaque']) {
    await a.click('[data-panel-tab="modelo"]');
    await a.click(`[data-template="${template}"]`);
    html = await previewHtml(a);
    check(`modelo ${template} gera a assinatura com os dados`, html.includes('Bruno Mamede') && html.includes('<table'));
    if (SHOTS) {
      await a.waitForTimeout(350);
      await a.locator('[data-preview-stage]').screenshot({ path: join(shots, `modelo-${template}.png`) });
    }
  }
  const icon = await anonymous.request.get(`${BASE}/icones/circle/2aa043/instagram.png`);
  check('ícone da biblioteca é entregue como PNG, sem login', icon.status() === 200 && icon.headers()['content-type'] === 'image/png');
  check('ícone usado em assinatura salva fica gravado como arquivo', existsSync(join(root, 'public', 'icones', 'outline-square', '2aa043', 'linkedin.png')));
  check('ícone inexistente responde 404', (await anonymous.request.get(`${BASE}/icones/circle/2aa043/naoexiste.png`)).status() === 404);
  check('cor inválida responde 404', (await anonymous.request.get(`${BASE}/icones/circle/zzzzzz/instagram.png`)).status() === 404);

  section('19. Saúde geral');
  check('nenhum erro de JavaScript nas telas', consoleErrors.length === 0, consoleErrors.join(' | '));
  check('nenhum aviso ou erro do PHP', phpErrors === '', phpErrors.slice(0, 600));
} catch (error) {
  failures++;
  console.log(`\nERRO INESPERADO: ${error.stack || error}`);
  if (phpErrors) { console.log(`PHP: ${phpErrors.slice(0, 800)}`); }
} finally {
  if (browser) { await browser.close(); }
  php.kill();
  fixtureServer.close();
}

console.log(`\n${passed} verificações passaram, ${failures} falharam.`);
process.exit(failures ? 1 : 0);
