# KIWEE Assinaturas

Plataforma de assinaturas de e-mail para os clientes da KIWEE. A KIWEE administra os acessos. Cada empresa tem uma área privada para configurar a identidade visual e criar, salvar e editar as assinaturas dos colaboradores.

Primeira versão: gratuita, com acesso só por convite, sem planos, cobrança ou cadastro público.

## O que tem

**Painel da KIWEE**

* Cadastro de empresas e convite de usuários por e-mail.
* Suspender e reativar empresas e usuários, com efeito imediato.
* Abrir a área de qualquer empresa para configurar em nome do cliente.
* Equipe de administradores.

**Área do cliente**

* Modelo padrão da empresa: logotipo, cores, fonte, ícones e links institucionais.
* Lista de assinaturas pelo nome do colaborador, com busca, criar, editar, duplicar e excluir.
* Aplicar o modelo padrão a todas as assinaturas, mantendo os dados pessoais.

**Editor**

* Cinco modelos: Horizontal, Vertical, Compacta, Com foto e Destaque. Trocar de modelo mantém os dados.
* Prévia em tempo real, em fundo claro ou escuro e em largura de computador ou celular.
* Campos: nome, cargo, departamento, empresa, telefone, celular, WhatsApp, e-mail, site, endereço, foto, redes, banner e aviso legal.
* Ocultar qualquer campo ou ícone. Campos vazios somem sem deixar espaço nem divisória.
* Cores, fontes compatíveis com e-mail, tamanhos, alinhamento, espaçamento e ordem dos contatos e das redes.
* Biblioteca com 36 ícones em 5 formatos, na cor da marca.
* Imagens por envio de arquivo, por link público https (Google Drive, site do cliente) ou da biblioteca da empresa.

**Exportação**

* Copiar assinatura, Baixar HTML e link de instalação para o colaborador.
* HTML em tabelas, com estilos em linha, textos selecionáveis, links clicáveis, texto alternativo e fontes de reserva. Sem JavaScript e sem CSS externo.
* Passo a passo para Gmail, Outlook e Apple Mail.

## Documentos

* [Publicação no cPanel](docs/PUBLICACAO.md)
* [Análise dos sites de referência](docs/ANALISE-REFERENCIAS.md)
* [Registro de testes](docs/TESTES.md)

## Tecnologia

PHP 8 sem dependências externas, MySQL ou MariaDB (SQLite como alternativa) e JavaScript puro no navegador. A publicação é uma cópia de arquivos, o que funciona em qualquer cPanel.

```
assinaturas/
  public/            raiz do site: index.php, estilos, scripts, imagens enviadas e ícones gerados
  app/
    bootstrap.php    inicialização e cabeçalhos de segurança
    routes.php       tabela de rotas
    controllers/     login e convites, painel KIWEE, área do cliente, API
    lib/             banco, sessão, e-mail, imagens, ícones e regras das assinaturas
    views/           telas
    resources/icons  máscaras dos ícones
  storage/           banco SQLite (se usado) e registro de e-mails em modo de teste
  bin/               roteador do servidor de desenvolvimento
  tests/             testes automáticos
  tools/             gerador das máscaras de ícones
  docs/              documentação
  config.sample.php  modelo de configuração
```

O HTML de todas as assinaturas é gerado por um único arquivo, `public/assets/js/renderer.js`. O servidor guarda apenas os dados e as opções de cada assinatura, depois de validar tudo em `app/lib/signatures.php`.

## Rodar no computador

```bash
cp config.sample.php config.php
```

No `config.php`, use `'app_url' => 'http://127.0.0.1:8080'`, o banco `sqlite` e o e-mail em modo `log`. Depois:

```bash
php -S 127.0.0.1:8080 -t public bin/dev-router.php
```

Abra `http://127.0.0.1:8080/instalar`. No modo `log`, os convites são gravados em `storage/mail.log`.

## Testes

```bash
php tests/unit.php
```

```bash
node --test tests/renderer.test.mjs
```

```bash
node tests/smtp.test.mjs
```

```bash
node tests/layout.test.mjs
```

```bash
node tests/e2e.mjs
```

O último precisa do Playwright com Chromium. Com `SHOTS=1` ele grava capturas de tela em `tests/.tmp/shots`. Com `KW_TEST_DB=mysql` ele usa um MySQL local.

## Segurança

* Senhas guardadas com `password_hash`. Convites e redefinições guardam só o resumo do código.
* Toda alteração exige o token da sessão (CSRF). Limite de tentativas de login.
* O usuário e a empresa são relidos do banco a cada requisição. Suspender vale na hora. Trocar a senha encerra as sessões de outros aparelhos.
* A empresa vem sempre da sessão. Toda consulta de assinatura e de arquivo leva o id da empresa. Recurso de outra empresa responde "não encontrado".
* Toda imagem enviada é regravada pelo servidor como PNG e recebe nome aleatório. A pasta de envios não executa scripts.
* Cada gravação do editor informa a empresa em uso. O servidor recusa se ela mudou em outra aba.
* A importação por URL só aceita https, bloqueia redes internas e confere cada redirecionamento.
* Política de conteúdo restrita: sem scripts em linha e sem recursos de terceiros.

## Ícones

Os desenhos das redes vêm do pacote Simple Icons (CC0) e os de contato do pacote Lucide (ISC). As marcas pertencem aos respectivos donos. Para acrescentar ícones, edite `tools/build-icons.mjs` e rode `npm install && npm run icones` dentro de `tools`.

## Marca

O símbolo e as cores da plataforma são provisórios. Para usar o logotipo oficial da KIWEE, troque `app/views/partials/logo.php` e `public/assets/img/favicon.svg`. As cores ficam no começo de `public/assets/css/app.css`.
