// Teste do envio por SMTP contra um servidor falso local. Uso: node tests/smtp.test.mjs
import { createServer } from 'node:net';
import { execFile } from 'node:child_process';
import { promisify } from 'node:util';
import { writeFileSync, mkdirSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const here = dirname(fileURLToPath(import.meta.url));
const tmp = join(here, '.tmp');
mkdirSync(tmp, { recursive: true });
const PORT = 8095;
const session = { commands: [], data: '' };

const server = createServer((socket) => {
  let inData = false;
  let buffer = '';
  socket.write('220 falso.local ESMTP\r\n');
  socket.on('data', (chunk) => {
    buffer += chunk.toString('utf8');
    let index;
    while ((index = buffer.indexOf('\r\n')) !== -1) {
      const line = buffer.slice(0, index);
      buffer = buffer.slice(index + 2);
      if (inData) {
        if (line === '.') { inData = false; socket.write('250 OK queued\r\n'); } else { session.data += line + '\n'; }
        continue;
      }
      session.commands.push(line);
      if (/^EHLO/i.test(line)) { socket.write('250-falso.local\r\n250 AUTH LOGIN\r\n'); }
      else if (/^AUTH LOGIN/i.test(line)) { socket.write('334 VXNlcm5hbWU6\r\n'); }
      else if (session.commands[session.commands.length - 2] === 'AUTH LOGIN') { socket.write('334 UGFzc3dvcmQ6\r\n'); }
      else if (session.commands[session.commands.length - 3] === 'AUTH LOGIN') { socket.write('235 Authenticated\r\n'); }
      else if (/^DATA/i.test(line)) { inData = true; socket.write('354 Go ahead\r\n'); }
      else if (/^QUIT/i.test(line)) { socket.write('221 Bye\r\n'); socket.end(); }
      else { socket.write('250 OK\r\n'); }
    }
  });
}).listen(PORT, '127.0.0.1');

writeFileSync(join(tmp, 'smtp-config.php'), `<?php return [
  'app_url' => 'https://assinatura.exemplo.com.br',
  'db' => ['driver' => 'sqlite', 'path' => __DIR__ . '/smtp.sqlite'],
  'mail' => ['driver' => 'smtp', 'host' => '127.0.0.1', 'port' => ${PORT}, 'encryption' => 'none',
             'user' => 'conta@exemplo.com.br', 'pass' => 'segredo', 'from_email' => 'assinaturas@exemplo.com.br', 'from_name' => 'KIWEE Assinaturas'],
  'setup_key' => 'x',
];`);

let failures = 0;
const check = (name, ok, detail = '') => { console.log(`  ${ok ? 'ok   ' : 'FALHA'} ${name}${ok ? '' : ' -> ' + detail}`); if (!ok) { failures++; } };

try {
  // Chamada assíncrona: o servidor falso roda neste mesmo processo e precisa continuar atendendo.
  const { stdout: output } = await promisify(execFile)('php', ['-r', `
    require '${join(here, '..', 'app', 'bootstrap.php')}';
    restore_exception_handler();
    $link = 'https://assinatura.exemplo.com.br/convite/' . str_repeat('a', 64);
    echo invite_send_email('João da Silva', 'joao@cliente.com.br', $link, 'Empresa Ação') ? 'enviado' : 'falhou';
  `], { env: { ...process.env, KW_CONFIG: join(tmp, 'smtp-config.php') } });

  const decode = (text) => Buffer.from(text, 'base64').toString('utf8');
  const parts = session.data.split(/--kw_[a-f0-9]+/).map((part) => part.split('\n\n').slice(1).join('').replace(/\s+/g, ''));
  check('função de envio devolve sucesso', output === 'enviado', output);
  check('autentica com usuário e senha', session.commands.includes(Buffer.from('conta@exemplo.com.br').toString('base64'))
    && session.commands.includes(Buffer.from('segredo').toString('base64')));
  check('remetente e destinatário corretos', session.commands.includes('MAIL FROM:<assinaturas@exemplo.com.br>') && session.commands.includes('RCPT TO:<joao@cliente.com.br>'));
  check('assunto com acentos é codificado', /Subject: =\?UTF-8\?B\?/.test(session.data) || /Subject: Seu convite/.test(session.data), session.data.slice(0, 300));
  check('mensagem tem versão em texto e em HTML', /Content-Type: text\/plain/.test(session.data) && /Content-Type: text\/html/.test(session.data));
  check('texto traz o link do convite e os acentos', decode(parts[1] || '').includes('/convite/' + 'a'.repeat(64)) && decode(parts[1] || '').includes('João da Silva'), decode(parts[1] || '').slice(0, 200));
  check('HTML traz o botão com o link', decode(parts[2] || '').includes('Criar minha senha') && decode(parts[2] || '').includes('Empresa Ação'));
} catch (error) {
  failures++;
  console.log('ERRO: ' + error.message);
} finally {
  server.close();
}
console.log(failures ? `\n${failures} falha(s).` : '\nEnvio por SMTP: tudo certo.');
process.exit(failures ? 1 : 0);
