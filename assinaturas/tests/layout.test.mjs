// Testa a publicação em que a pasta pública do servidor não se chama "public".
// É o caso comum em painéis de hospedagem: o conteúdo de public/ vai para public_html/
// e o restante (app, storage, config.php) fica uma pasta acima.
//
// Uso: node tests/layout.test.mjs
import { spawn } from 'node:child_process';
import { cpSync, mkdirSync, rmSync, writeFileSync, readFileSync, existsSync, readdirSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const here = dirname(fileURLToPath(import.meta.url));
const root = join(here, '..');
const site = join(here, '.tmp', 'layout-site');
const PORT = 8094;
const BASE = `http://127.0.0.1:${PORT}`;

let failures = 0;
const check = (name, ok, detail = '') => { console.log(`  ${ok ? 'ok   ' : 'FALHA'} ${name}${ok ? '' : ' -> ' + detail}`); if (!ok) { failures++; } };

rmSync(site, { recursive: true, force: true });
mkdirSync(join(site, 'storage'), { recursive: true });
cpSync(join(root, 'app'), join(site, 'app'), { recursive: true });
cpSync(join(root, 'bin'), join(site, 'bin'), { recursive: true });
cpSync(join(root, 'public'), join(site, 'public_html'), {
  recursive: true,
  filter: (source) => !/public\/(uploads|icones)\/[^.]/.test(source),
});
// Config com linha em branco antes do "<?php" e fechamento "?>" seguido de linhas, como costuma ficar no servidor.
writeFileSync(join(site, 'config.php'), `

<?php
return [
    'app_url' => '${BASE}',
    'db' => ['driver' => 'sqlite', 'path' => __DIR__ . '/storage/app.sqlite'],
    'mail' => ['driver' => 'log'],
    'setup_key' => 'chave-do-teste-de-pastas',
];
?>

`);
// Arquivo deixado por uma versão antiga na pasta "public" ao lado da pasta pública de verdade.
const strayKey = 'aaaaaaaaaaaaaaaaaaaa';
const strayFile = 'b'.repeat(24) + '.png';
mkdirSync(join(site, 'public', 'uploads', strayKey), { recursive: true });
cpSync(join(here, 'fixtures', 'logo.png'), join(site, 'public', 'uploads', strayKey, strayFile));

const php = spawn('php', ['-d', 'upload_max_filesize=8M', '-d', 'post_max_size=8M', '-S', `127.0.0.1:${PORT}`, '-t', 'public_html', 'bin/dev-router.php'], {
  cwd: site, env: { ...process.env, KW_CONFIG: join(site, 'config.php') }, stdio: 'ignore',
});

const jar = new Map();
async function call(path, options = {}) {
  const headers = { ...(options.headers || {}), Cookie: [...jar].map(([k, v]) => `${k}=${v}`).join('; ') };
  const response = await fetch(BASE + path, { ...options, headers, redirect: 'manual' });
  for (const cookie of response.headers.getSetCookie()) {
    const [pair] = cookie.split(';');
    const [name, value] = pair.split('=');
    jar.set(name, value);
  }
  return response;
}
const csrfOf = (html) => (html.match(/name="_csrf" value="([^"]+)"/) || [])[1];
const form = (data) => ({ method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: new URLSearchParams(data).toString() });

try {
  for (let i = 0; i < 50; i++) { try { await fetch(BASE + '/entrar', { redirect: 'manual' }); break; } catch { await new Promise((r) => setTimeout(r, 100)); } }

  const setupPage = await (await call('/instalar')).text();
  check('página começa no documento mesmo com linhas em branco no config.php', setupPage.startsWith('<!DOCTYPE html>'), JSON.stringify(setupPage.slice(0, 20)));
  check('verificação do servidor passa com a pasta pública chamada public_html', !setupPage.includes('<li class="fail">'));
  check('arquivos de estilo ganham carimbo de versão', /app\.css\?v=[1-9]\d+/.test(setupPage));

  const install = await call('/instalar', form({ _csrf: csrfOf(setupPage), chave: 'chave-do-teste-de-pastas', nome: 'Admin', email: 'admin@kiwee.test', senha: 'senha-de-teste-123', senha2: 'senha-de-teste-123' }));
  check('instalação conclui', install.status === 303 && install.headers.get('location').endsWith('/admin'));

  const adminPage = await (await call('/admin')).text();
  const created = await call('/admin/empresas', form({ _csrf: csrfOf(adminPage), empresa: 'Cliente Teste', nome: '', email: '' }));
  const companyPath = new URL(created.headers.get('location'), BASE).pathname;
  const companyPage = await (await call(companyPath)).text();
  await call(companyPath + '/abrir', form({ _csrf: csrfOf(companyPage) }));

  const editor = await (await call('/padrao')).text();
  const boot = JSON.parse(editor.match(/<script type="application\/json" id="kw-boot">([\s\S]*?)<\/script>/)[1]);
  const apiHeaders = { 'X-CSRF-Token': boot.csrf, 'X-KW-Company': boot.companyKey };

  const upload = new FormData();
  upload.append('tipo', 'logo');
  upload.append('arquivo', new Blob([readFileSync(join(here, 'fixtures', 'logo.png'))], { type: 'image/png' }), 'logo.png');
  const uploaded = await (await call('/api/arquivos', { method: 'POST', headers: apiHeaders, body: upload })).json();
  check('envio de imagem funciona', uploaded.ok === true, JSON.stringify(uploaded));
  const imageUrl = uploaded.asset ? uploaded.asset.url : '/nada';
  const image = await fetch(BASE + imageUrl);
  check('imagem enviada abre pelo endereço público', image.status === 200 && image.headers.get('content-type') === 'image/png', `${image.status} ${imageUrl}`);
  check('imagem foi gravada dentro de public_html', existsSync(join(site, 'public_html', imageUrl)));

  const icon = await fetch(`${BASE}/icones/2/circle/2aa043/whatsapp.png`);
  const bytes = Buffer.from(await icon.arrayBuffer());
  check('ícone chega como PNG íntegro', icon.status === 200 && bytes.subarray(0, 8).toString('latin1') === '\x89PNG\r\n\x1a\n', JSON.stringify(bytes.subarray(0, 8).toString('latin1')));

  const data = { fields: { name: { value: 'Ana', show: true }, whatsapp: { value: '(11) 98888-7777', show: true } }, social: [{ type: 'linkedin', url: 'https://linkedin.com/x', show: true }] };
  const saved = await (await call('/api/padrao', { method: 'POST', headers: { ...apiHeaders, 'Content-Type': 'application/json' }, body: JSON.stringify({ data }) })).json();
  check('salvar funciona', saved.ok === true, JSON.stringify(saved).slice(0, 200));
  check('ícones da assinatura salva são gravados dentro de public_html', existsSync(join(site, 'public_html', 'icones', '2', 'outline-square', '3d7f21', 'linkedin.png')));

  const adopted = await fetch(`${BASE}/uploads/${strayKey}/${strayFile}`);
  check('imagem gravada na pasta errada por versão antiga é levada para a pasta pública', adopted.status === 200 && existsSync(join(site, 'public_html', 'uploads', strayKey, strayFile)));
  check('a pasta errada é esvaziada', !existsSync(join(site, 'public', 'uploads')), existsSync(join(site, 'public', 'uploads')) ? readdirSync(join(site, 'public', 'uploads')).join(',') : '');
} catch (error) {
  failures++;
  console.log('ERRO: ' + (error.stack || error));
} finally {
  php.kill();
  rmSync(site, { recursive: true, force: true });
}
console.log(failures ? `\n${failures} falha(s).` : '\nPasta pública com outro nome: tudo certo.');
process.exit(failures ? 1 : 0);
