# Registro de testes

Data: 10/11/2026.

## Resumo

**O que foi testado.** Todo o funcionamento da plataforma, com testes automáticos em navegador real, em dois bancos de dados e em Apache.

**O que não foi testado.** A colagem, o envio e a leitura da assinatura dentro do Gmail, do Outlook e do Apple Mail. O ambiente em que a plataforma foi construída não tem acesso a contas de e-mail nem a esses programas. A lista no fim deste documento serve para a KIWEE fazer e registrar esse teste antes de liberar para os clientes.

## Versões usadas nos testes automáticos

| Programa | Versão |
|---|---|
| Chromium (via Playwright 1.63.0) | 153.0.8010.12 |
| PHP | 8.3.6 |
| MariaDB | 10.11.14 |
| SQLite | a que acompanha o PHP 8.3.6 |
| Apache | 2.4.58, com mod_php |
| Node.js | 22.23.4 |
| Sistema | Ubuntu 24.04 |

## Testes automáticos

| Conjunto | Comando | Resultado |
|---|---|---|
| Fluxo completo no navegador, com SQLite | `node tests/e2e.mjs` | 129 verificações, todas passaram |
| Fluxo completo no navegador, com MariaDB | `KW_TEST_DB=mysql node tests/e2e.mjs` | 129 verificações, todas passaram |
| Gerador do HTML da assinatura | `node --test tests/renderer.test.mjs` | 22 testes, todos passaram |
| Regras do servidor | `php tests/unit.php` | 74 verificações, todas passaram |
| Envio de e-mail por SMTP (servidor falso, sem criptografia) | `node tests/smtp.test.mjs` | 7 verificações, todas passaram |

### O que o fluxo completo cobre

Ele repete o critério de entrega da primeira versão:

1. A KIWEE instala a plataforma, cadastra duas empresas e convida um usuário de cada.
2. O cliente aceita o convite e configura o modelo padrão com logotipo, cores, fonte e links.
3. O cliente cria duas assinaturas, uma a partir do padrão e outra escolhendo outro modelo, com foto.
4. Cada assinatura é reaberta, editada e salva. A edição de uma não altera a outra nem o padrão.
5. A assinatura é copiada pelo botão e colada em uma área editável do navegador. Tabelas, links, texto e imagens chegam inteiros.
6. O arquivo HTML é baixado e conferido: só tabelas, estilos em linha, sem JavaScript e sem CSS externo.
7. O usuário de uma empresa tenta abrir, ler, gravar, duplicar e excluir a assinatura da outra pela URL direta e pela API. Todas as tentativas recebem "não encontrado".

Também cobre: reenvio e cancelamento de convites, convite de administrador, recuperação de senha, bloqueio por excesso de tentativas de login, busca, duplicar e excluir, campos vazios e ocultos, ordem dos contatos, troca de modelo, envio de imagens inválidas, importação por URL (pública, privada, página, quebrada, SVG, ftp, pasta do Drive), link de instalação do colaborador, suspensão de empresa e de usuário, administrador dentro da área de uma empresa, telas em celular de 390 pixels e os cinco modelos.

### Servidor Apache

A plataforma foi executada em Apache 2.4.58 nas duas formas de apontar o subdomínio (raiz em `public` e raiz na pasta do projeto). Nas duas: as telas abrem, os ícones são gerados, as imagens enviadas abrem sem login, arquivos PHP dentro de `uploads` são bloqueados e as pastas `app`, `storage`, `bin`, `docs` e o arquivo de configuração não ficam acessíveis.

## Limites conhecidos

* **Google Drive.** A conversão do link de compartilhamento e as mensagens de erro foram testadas com respostas simuladas. Não foi possível buscar um arquivo real do Drive, porque o ambiente não alcança os servidores do Google. Por isso a tela recomenda guardar uma cópia na plataforma.
* **SMTP com SSL ou TLS.** O envio foi testado sem criptografia contra um servidor falso. Os modos `ssl` e `tls` usam o mesmo código depois da conexão, mas não foram exercitados. Teste enviando um convite real depois de publicar.
* **Função mail() do servidor.** Só pode ser testada no servidor de hospedagem.
* **LiteSpeed.** Muitos cPanel usam LiteSpeed no lugar do Apache. As regras usadas são as comuns aos dois, mas o teste foi feito em Apache.
* **Modo escuro dos programas de e-mail.** Alguns invertem cores por conta própria. O editor tem a prévia em fundo escuro para conferir o logotipo.

## Teste nos programas de e-mail (a fazer pela KIWEE)

Faça depois de publicar, com uma assinatura real que tenha logotipo, foto, redes e banner. Para cada programa: cole a assinatura, envie um e-mail para outra conta e abra a mensagem recebida.

| Programa | Versão testada | Data | Colou certo | Enviou certo | Abriu certo no destino | Observações |
|---|---|---|---|---|---|---|
| Gmail no Chrome | | | | | | |
| Gmail no aplicativo Android ou iPhone (leitura) | | | | | | |
| Novo Outlook ou Outlook na Web | | | | | | |
| Outlook clássico para Windows | | | | | | |
| Outlook para Mac | | | | | | |
| Apple Mail no Mac | | | | | | |
| Mail no iPhone | | | | | | |

O que conferir em cada um:

1. Logotipo, foto e ícones aparecem, no tamanho certo e sem distorção.
2. Nome, cargo e contatos estão com a fonte, as cores e os tamanhos escolhidos.
3. Telefone, WhatsApp, e-mail, site e redes abrem ao clicar.
4. Não há espaços grandes, linhas soltas ou tabelas com borda.
5. No Outlook clássico, a foto redonda aparece redonda e as divisórias têm a largura do texto.
6. No Gmail, a assinatura é aceita sem o aviso de tamanho.
7. Ao responder um e-mail, a assinatura continua inteira.

Se algum programa mostrar diferença, anote na tabela com uma captura de tela. Os ajustes são feitos em um único arquivo, `public/assets/js/renderer.js`.
