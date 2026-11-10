# Análise dos sites de referência

Data da análise: 10/11/2026.

## Como a análise foi feita

**Nenhum dos quatro produtos pôde ser testado na prática.** Os editores de todos ficam atrás de cadastro, período de teste ou contato comercial, e não criamos contas em nome da KIWEE. Tudo o que está abaixo foi lido nas páginas públicas de cada site. Onde uma informação veio só de texto de divulgação, isso está indicado.

| Site | Páginas lidas | Testado na prática |
|---|---|---|
| Signature Hound | Início, planos, gerador para Gmail, gerador para Outlook, documentação, "imagens que sumiram", tela de cadastro | Não. O editor exige conta (teste de 30 dias). |
| Hands4IT | Página "Assinaturas de e-mail profissional" | Não. É um serviço contratado por formulário, com implantação feita pela empresa. |
| Crossware 365 | Página inicial | Não. O teste de 30 dias exige formulário comercial. |
| MySignature | Página "Gerador de assinatura para Gmail" em português | Não. O editor exige conta. |

Limites desta leitura: não vimos as telas internas, a quantidade real de modelos nem o HTML que cada produto gera. Os contadores da página do MySignature (modelos, layouts, ícones) aparecem zerados no código da página e só são preenchidos por animação, então não citamos números.

## O que cada referência oferece

### Signature Hound

Lido nas páginas públicas:

* Fluxo em quatro passos: escolher um modelo, preencher os dados e personalizar (logotipo, fonte, cores), instalar, compartilhar com colegas.
* Vários telefones e redes sociais por assinatura.
* Imagens hospedadas pelo próprio serviço, para não virarem anexo.
* Instalação: escolha do cliente de e-mail com instruções próprias, botão de copiar, copiar o HTML e baixar o arquivo HTML. No Gmail há instalação direta com autorização da conta Google.
* HTML em tabelas e estilos em linha, pensado para o Outlook clássico.
* Aviso sobre o limite de tamanho da assinatura no Gmail ("a assinatura é longa demais").
* Equipes: um desenho mestre e um link para cada colega preencher os próprios dados. Envio em lote por planilha CSV.
* Troca da imagem de campanha sem reinstalar, desde que a imagem seja substituída no mesmo endereço. Não vale para o Outlook clássico, que guarda cópia local das imagens.
* Modelos próprios em HTML com variáveis, telefone sem espaços para o link de ligação e trechos condicionais que somem quando o campo está vazio.
* Foto de perfil recortada em círculo.
* Hoje é pago, com 30 dias de teste. A própria página informa que deixou de ser gratuito.

### Hands4IT

Lido na página pública (texto comercial):

* Serviço gerenciado para Microsoft 365 e Exchange. A assinatura é aplicada no servidor, então vale em qualquer aparelho sem instalar nada.
* Painel único para logotipo, banner e aviso legal, com mudança refletida para todos os usuários.
* Dados puxados do diretório da empresa (nome, cargo, telefone, foto).
* Banners de campanha agendados por data ou por setor, avisos legais automáticos, modelos ilimitados, regras por departamento, múltiplos domínios e contagem de cliques.
* A página lista as dores que o serviço resolve: imagens que não carregam, logotipos distorcidos, cargos desatualizados e tutoriais longos de colagem no Outlook.

### Crossware 365

Lido na página pública (texto comercial):

* Gestão central para Microsoft 365, Google Workspace, Exchange e HCL Domino, com aplicação no servidor depois do envio.
* Editor com blocos de arrastar e soltar, campos e imagens condicionais, modelos prontos.
* Regras por remetente, destinatário interno ou externo, mensagem nova ou resposta, e período.
* Prévia na tela com os dados de cada usuário.
* Cada departamento pode cuidar das próprias assinaturas.
* Banners de campanha e avisos legais.

### MySignature

Lido na página pública:

* Painel com a lista de assinaturas e botão "Nova assinatura".
* Editor em etapas: dados, logotipo, banner, ícones de redes, aviso legal, depois fonte, estilo, cor e tamanho, depois escolha do modelo, sempre com prévia.
* Botão "Copiar para a área de transferência", com a recomendação de usar Chrome ou Firefox para manter a formatação.
* Extensão do Chrome que instala e atualiza a assinatura no Gmail, rastreamento de aberturas e cliques, e gestão central para Google Workspace e Microsoft 365 nos planos pagos.
* Passo a passo para iPhone: copiar pelo aparelho, colar nos ajustes do Mail e agitar o telefone para desfazer a mudança de formatação.
* Dica de testar a assinatura enviando um e-mail para si mesmo.

## Comparação nos cinco pontos pedidos

| Ponto | Signature Hound | Hands4IT | Crossware 365 | MySignature | KIWEE Assinaturas (primeira versão) |
|---|---|---|---|---|---|
| Escolha de modelos | Primeiro passo do fluxo | Modelos ilimitados, criados pelo serviço | Modelos prontos ou desenho próprio | Galeria de modelos no editor | Galeria com cinco modelos, mostrados já com a marca da empresa |
| Facilidade de edição | Formulário com logotipo, fonte e cores | Feita pela equipe do serviço | Blocos de arrastar e soltar | Etapas guiadas | Cinco abas: Modelo, Dados, Redes, Imagens e Aparência |
| Prévia | Não descrita em detalhe | Não descrita | Prévia com os dados de cada usuário | Prévia durante a edição | Prévia em tempo real com os dados do colaborador, fundo claro ou escuro, largura de computador ou celular |
| Gestão por empresa | Desenho mestre e link para colegas, CSV | Painel único, regras por setor | Painel único, regras e departamentos | Painel central nos planos pagos | Área privada por empresa, modelo padrão e lista de assinaturas com busca |
| Instalação | Copiar, baixar HTML, instruções por cliente, Gmail direto | Aplicada no servidor | Aplicada no servidor | Copiar, extensão do Chrome | Copiar assinatura, Baixar HTML, link de instalação para o colaborador e passo a passo para Gmail, Outlook e Apple Mail |

## Ideias incorporadas à primeira versão

| Ideia | De onde veio | Como ficou na KIWEE Assinaturas |
|---|---|---|
| Começar pelo modelo e só depois preencher | Signature Hound, MySignature | Tela "Nova assinatura" com o padrão da empresa e a galeria de modelos |
| Identidade definida uma vez para todos | Hands4IT, Crossware | Modelo padrão da empresa com logotipo, cores, fontes, ícones e links |
| Atualizar todos de uma vez | Hands4IT, Crossware | Botão "Aplicar às assinaturas existentes", que preserva os dados pessoais |
| Prévia com os dados de cada pessoa | Crossware | Prévia em tempo real no editor |
| Campos vazios somem | Signature Hound (trechos condicionais), Crossware | Campos vazios ou ocultos não geram linha, ícone, espaço nem divisória |
| Imagens hospedadas em endereço fixo | Signature Hound, Hands4IT | Arquivos enviados ficam em endereços estáveis da plataforma |
| Trocar imagem sem reinstalar | Signature Hound | "Substituir" na biblioteca mantém o mesmo endereço do arquivo |
| Logotipo sem distorção | Hands4IT (dor citada) | Largura e altura fixas no HTML, calculadas a partir do arquivo |
| Foto recortada em círculo | Signature Hound | A plataforma gera a versão redonda, que funciona até no Outlook clássico |
| Copiar e baixar HTML | Signature Hound, MySignature | Botões "Copiar assinatura" e "Baixar HTML" |
| Aviso do limite do Gmail | Signature Hound | O editor mostra o tamanho e avisa acima de 10.000 caracteres |
| HTML em tabelas e estilos em linha | Signature Hound | Todo o HTML gerado segue esse formato, sem largura de 100% |
| Link para cada colega instalar | Signature Hound | Link de instalação por assinatura, sem necessidade de senha |
| Telefone clicável sem espaços | Signature Hound | Links de ligação e de WhatsApp com código do país |
| Banner de campanha e aviso legal | Hands4IT, Crossware, MySignature | Campos opcionais em cada assinatura e no padrão |
| Passo a passo por cliente, com o truque do iPhone | Signature Hound, MySignature | Guia para Gmail, Outlook e Apple Mail no editor e no link do colaborador |

## O que ficou de fora, e por quê

* **Aplicação no servidor de e-mail** (Hands4IT, Crossware). Exige integração com o Microsoft 365 ou o Google Workspace de cada cliente. É o caminho natural para uma segunda versão.
* **Instalação direta no Gmail** por autorização Google ou extensão (Signature Hound, MySignature). Exige aprovação do aplicativo no Google.
* **Envio em lote por planilha CSV** (Signature Hound). Fácil de acrescentar depois.
* **Contagem de cliques, agendamento de banners e regras por destinatário** (Hands4IT, Crossware). Fora do escopo de uma primeira versão gratuita.
* **Sincronização com o diretório da empresa** (Hands4IT, Crossware).
* **Modelos próprios em HTML e editor de arrastar e soltar** (Signature Hound, Crossware).

Todo o código, os modelos e a identidade visual da plataforma foram escritos para a KIWEE. Nada foi copiado dos sites analisados.
