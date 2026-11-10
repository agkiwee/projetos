# Publicação no cPanel

Este guia publica a plataforma em `https://assinatura.agkiwee.com.br`. Para usar outro endereço, troque o nome nos passos e o valor de `app_url` no `config.php`. Nada mais precisa mudar.

## O que o servidor precisa ter

* PHP 8.0 ou mais novo, com as extensões `gd`, `curl`, `mbstring`, `pdo_mysql` e `fileinfo`. No cPanel, confira em **Selecionar versão do PHP**.
* MySQL ou MariaDB.
* Apache ou LiteSpeed com `.htaccess` ativo (padrão no cPanel).
* HTTPS no subdomínio. Sem HTTPS as imagens não aparecem nos e-mails e o botão de copiar fica limitado.

A tela `/instalar` confere esses itens e mostra o que estiver faltando.

## 1. Criar o subdomínio

1. No cPanel, abra **Domínios** e clique em **Criar um novo domínio**.
2. Digite `assinatura.agkiwee.com.br`.
3. Desmarque a opção de compartilhar a raiz do documento.
4. Em **Raiz do documento**, use `assinatura.agkiwee.com.br/public`. O código fica na pasta `assinatura.agkiwee.com.br` e só a pasta `public` fica visível na internet.
5. Se o DNS do domínio fica fora do cPanel (Registro.br, Cloudflare), crie lá um registro A chamado `assinatura` apontando para o IP do servidor.
6. Em **SSL/TLS Status**, rode o **AutoSSL** para o subdomínio.

Se a hospedagem não deixar escolher a raiz do documento, use a pasta `assinatura.agkiwee.com.br` mesmo. O arquivo `.htaccess` da raiz do projeto encaminha tudo para `public` e bloqueia as pastas internas. As duas formas foram testadas.

## 2. Criar o banco de dados

1. Em **Bancos de dados MySQL**, crie um banco, por exemplo `usuario_assinaturas`.
2. Crie um usuário com senha forte.
3. Adicione o usuário ao banco com **todos os privilégios**.

## 3. Enviar os arquivos

### Opção A: publicação automática pelo GitHub (recomendada)

O repositório já traz o fluxo `.github/workflows/assinaturas-publicar.yml`. Ele envia a pasta `assinaturas/` por FTP a cada alteração que chega à branch principal do repositório no GitHub.

1. No cPanel, em **Contas de FTP**, crie uma conta com o diretório `assinatura.agkiwee.com.br`.
2. No GitHub, abra o repositório, vá em **Settings**, **Secrets and variables**, **Actions** e crie três segredos:

```
FTP_SERVER
FTP_USERNAME
FTP_PASSWORD
```

`FTP_SERVER` é o endereço de FTP (por exemplo `ftp.agkiwee.com.br`). `FTP_USERNAME` é o usuário completo criado no cPanel.

3. Faça o merge do pull request na branch principal ou rode o fluxo manualmente em **Actions**, **Publicar assinaturas**, **Run workflow**.

Dois ajustes opcionais ficam em **Variables**, na mesma tela dos segredos:

* `FTP_SERVER_DIR`: pasta de destino dentro da conta de FTP. O padrão é `./`, a pasta inicial da conta.
* `FTP_PROTOCOL`: o padrão é `ftps`, que é o FTP com criptografia. Use `ftp` só se a hospedagem não aceitar `ftps`.

O fluxo nunca apaga o `config.php`, as imagens enviadas pelos clientes nem o conteúdo de `storage`. Enquanto os segredos não existirem, ele termina sem publicar e avisa no resumo.

### Opção B: envio manual

Envie o conteúdo da pasta `assinaturas/` para `assinatura.agkiwee.com.br` pelo Gerenciador de Arquivos ou por FTP. As pastas `tests`, `tools` e `docs` não precisam ir.

## 4. Configurar

1. No Gerenciador de Arquivos, dentro de `assinatura.agkiwee.com.br`, copie `config.sample.php` para `config.php`.
2. Edite o `config.php`:
   * `app_url`: `https://assinatura.agkiwee.com.br`, sem barra no final.
   * `db`: nome do banco, usuário e senha criados no passo 2.
   * `mail`: veja o item 6.
   * `setup_key`: troque por um texto longo e aleatório.
3. Confira se as pastas `storage`, `public/uploads` e `public/icones` têm permissão de escrita (755).

## 5. Instalar

1. Abra `https://assinatura.agkiwee.com.br/instalar`.
2. Confira a verificação do servidor.
3. Digite a chave de instalação (`setup_key`), seu nome, e-mail e senha.
4. Pronto: você entra no painel da KIWEE. A tela de instalação deixa de funcionar assim que existe um administrador.

## 6. E-mails de convite

A plataforma envia convites e links de redefinição de senha.

* `driver` igual a `mail` usa o envio padrão do servidor. Funciona na maioria dos cPanel.
* `driver` igual a `smtp` usa uma conta de e-mail autenticada e costuma ter melhor entrega. Crie a conta `assinaturas@agkiwee.com.br` no cPanel e preencha `host`, `port` (465 com `ssl`, ou 587 com `tls`), `user` e `pass`.

Se um e-mail não puder ser enviado, o painel avisa e mostra o link do convite para você copiar e enviar por outro canal. O link também aparece quando o envio dá certo.

## 7. Primeiro uso

1. No painel, clique em **Nova empresa**, informe o nome e, se quiser, o primeiro usuário.
2. O usuário recebe o convite, cria a senha e cai na área da empresa.
3. Ele configura o **Modelo padrão** e cria as assinaturas.

Você também pode entrar na área de qualquer empresa pelo botão **Abrir área da empresa**, para configurar tudo em nome do cliente.

## Trocar de endereço depois

1. Crie o novo domínio ou subdomínio apontando para a mesma pasta.
2. Troque `app_url` no `config.php`.

As assinaturas salvas guardam as imagens como caminhos internos e passam a usar o novo endereço sozinhas. **As assinaturas já instaladas nos programas de e-mail continuam apontando para o endereço antigo.** Mantenha o endereço antigo no ar ou peça aos colaboradores que instalem a assinatura de novo.

## Cópias de segurança

Guarde três coisas: o banco de dados, a pasta `public/uploads` e o arquivo `config.php`. O backup completo do cPanel já inclui as três.

## Atualizações

Com a opção A, cada merge na branch principal publica a nova versão. As tabelas do banco são criadas na instalação. Se uma versão futura precisar de tabelas novas, isso virá descrito nas notas da versão.

## Problemas comuns

| Sintoma | Causa provável | O que fazer |
|---|---|---|
| "Configuração pendente" | Falta o `config.php` | Copie `config.sample.php` para `config.php` e preencha |
| Erro ao conectar ao banco | Dados do banco errados | Confira nome, usuário e senha. No cPanel o nome leva o prefixo da conta |
| Imagens não aparecem na prévia | Pasta sem permissão ou GD desligado | Dê permissão 755 em `public/uploads` e ative a extensão `gd` |
| Ícones não aparecem | Extensão GD desligada | Ative `gd` no seletor de PHP |
| Convites não chegam | Envio do servidor bloqueado ou caindo em spam | Use `smtp` com uma conta do próprio domínio |
| Página 404 em tudo, menos na inicial | `.htaccess` não enviado | Envie os arquivos ocultos. Alguns programas de FTP os escondem |
| Botão de copiar não funciona | Site sem HTTPS | Ative o SSL do subdomínio |
