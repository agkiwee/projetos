<?php
/**
 * Configuração da plataforma KIWEE Assinaturas.
 *
 * Copie este arquivo para config.php e ajuste os valores.
 * O config.php não vai para o repositório porque guarda senhas.
 */
return [
    // Endereço público da plataforma, sem barra no final.
    // É usado nos convites por e-mail e nas imagens das assinaturas.
    // Exemplo: https://assinatura.agkiwee.com.br
    'app_url' => 'https://assinatura.agkiwee.com.br',

    'app_name' => 'KIWEE Assinaturas',

    // Fuso horário usado para exibir datas.
    'timezone' => 'America/Sao_Paulo',

    // Banco de dados. No cPanel, crie o banco e o usuário em "Bancos de dados MySQL".
    'db' => [
        'driver' => 'mysql',
        'host' => 'localhost',
        'port' => 3306,
        'name' => 'usuario_assinaturas',
        'user' => 'usuario_assinaturas',
        'pass' => 'troque-esta-senha',
        // Alternativa sem MySQL (um arquivo dentro de storage/):
        // 'driver' => 'sqlite',
        // 'path' => __DIR__ . '/storage/app.sqlite',
    ],

    // Envio de e-mails (convites e redefinição de senha).
    //   mail = função mail() do servidor (padrão do cPanel)
    //   smtp = servidor SMTP autenticado (recomendado para melhor entrega)
    //   log  = não envia, apenas grava em storage/mail.log (testes)
    'mail' => [
        'driver' => 'mail',
        'from_email' => 'assinaturas@agkiwee.com.br',
        'from_name' => 'KIWEE Assinaturas',
        'host' => 'mail.agkiwee.com.br',
        'port' => 465,
        'encryption' => 'ssl', // ssl, tls ou none
        'user' => 'assinaturas@agkiwee.com.br',
        'pass' => '',
    ],

    // Chave pedida uma única vez em /instalar para criar o primeiro administrador.
    // Troque por um texto longo e aleatório antes de publicar.
    'setup_key' => 'troque-esta-chave',

    // Validade dos convites, em dias.
    'invite_days' => 7,

    // Mostra detalhes de erros na tela. Mantenha false em produção.
    'debug' => false,

    // Somente para testes locais: aceita importar imagens de endereços http e de rede interna.
    'dev_allow_insecure_url_import' => false,
];
