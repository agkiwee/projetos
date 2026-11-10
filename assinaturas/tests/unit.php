<?php
/**
 * Testes das regras do servidor que não dependem de navegador.
 * Uso: php tests/unit.php
 */
declare(strict_types=1);

$tmp = __DIR__ . '/.tmp';
@mkdir($tmp, 0755, true);
@unlink($tmp . '/unit.sqlite');
file_put_contents($tmp . '/unit-config.php', "<?php return ['app_url' => 'https://assinatura.exemplo.com.br', "
    . "'db' => ['driver' => 'sqlite', 'path' => __DIR__ . '/unit.sqlite'], 'mail' => ['driver' => 'log'], 'setup_key' => 'x'];");
putenv('KW_CONFIG=' . $tmp . '/unit-config.php');
require dirname(__DIR__) . '/app/bootstrap.php';
restore_exception_handler();

$passed = 0;
$failed = 0;
function check(string $name, bool $condition, string $detail = ''): void
{
    global $passed, $failed;
    if ($condition) {
        $passed++;
        echo "  ok    $name\n";
    } else {
        $failed++;
        echo "  FALHA $name" . ($detail !== '' ? " -> $detail" : '') . "\n";
    }
}
function problem(callable $run): string
{
    try {
        $run();
    } catch (ImageProblem $e) {
        return $e->getMessage();
    }
    return '';
}

$key = 'abcdefghij0123456789';
$other = 'zzzzzzzzzz9999999999';

echo "\nBanco de dados\n";
schema_install();
schema_install();
check('criar as tabelas duas vezes não dá erro', schema_installed());
$companyId = db_insert('companies', ['name' => 'Teste', 'public_key' => $key, 'status' => 'active', 'created_at' => now_utc()]);
check('busca escapa os curingas', db_like('100%_a!') === '%100!%!_a!!%');

echo "\nValidação dos dados da assinatura\n";
$clean = sig_sanitize([
    'template' => 'inexistente',
    'fields' => [
        'name' => ['value' => "  Ana\n Souza\0 ", 'show' => true],
        'title' => ['value' => str_repeat('x', 500)],
        'address' => ['value' => "Rua A\r\nCidade"],
        'hack' => ['value' => 'x'],
    ],
    'order' => ['email', 'email', 'hack', 'phone'],
    'logo' => ['url' => "/uploads/$key/" . str_repeat('a', 24) . '.png', 'link' => 'javascript:alert(1)', 'width' => 99999],
    'photo' => ['url' => "/uploads/$other/" . str_repeat('b', 24) . '.png', 'urlCircle' => '/uploads/../../config.php', 'shape' => 'star'],
    'banner' => ['url' => 'https://cdn.exemplo.com/b.png', 'link' => 'exemplo.com/promo', 'ratio' => 'abc'],
    'social' => array_merge(
        [['type' => 'linkedin', 'url' => 'https://linkedin.com/x'], ['type' => 'virus', 'url' => 'https://x.com'],
         ['type' => 'custom', 'url' => 'data:text/html,x', 'iconUrl' => 'https://cdn.exemplo.com/i.png" onerror="x']],
        array_fill(0, 30, ['type' => 'instagram', 'url' => 'instagram.com/x'])
    ),
    'style' => ['font' => 'Comic Sans', 'colorName' => 'red; background:url(x)', 'colorTitle' => '#ABCDEF', 'sizeName' => 400, 'align' => 'justify', 'underline' => 'yes'],
    'extra' => 'descartado',
], $key);
check('modelo desconhecido volta para o horizontal', $clean['template'] === 'horizontal');
check('texto de uma linha perde quebras e caracteres de controle', $clean['fields']['name']['value'] === 'Ana Souza', json_encode($clean['fields']['name']['value']));
check('texto é limitado em tamanho', mb_strlen($clean['fields']['title']['value']) === 160);
check('endereço mantém a quebra de linha', $clean['fields']['address']['value'] === "Rua A\nCidade");
check('campos desconhecidos são descartados', !isset($clean['fields']['hack']) && !isset($clean['extra']));
check('ordem dos contatos vira uma lista completa e sem repetição', $clean['order'] === ['email', 'phone', 'mobile', 'whatsapp', 'website', 'address']);
check('imagem da própria empresa é aceita', $clean['logo']['url'] !== '');
check('link javascript: é descartado', $clean['logo']['link'] === '');
check('largura fora do limite é ajustada', $clean['logo']['width'] === 400);
check('imagem hospedada de outra empresa é descartada', $clean['photo']['url'] === '');
check('caminho com ../ é descartado', $clean['photo']['urlCircle'] === '');
check('formato de foto desconhecido volta ao padrão', $clean['photo']['shape'] === 'circle');
check('imagem https externa é aceita', $clean['banner']['url'] === 'https://cdn.exemplo.com/b.png');
check('domínio sem esquema é aceito como link', $clean['banner']['link'] === 'exemplo.com/promo');
check('rede desconhecida é descartada e a lista é limitada', count($clean['social']) === SIG_MAX_SOCIAL && $clean['social'][0]['type'] === 'linkedin' && $clean['social'][1]['type'] === 'custom');
check('link data: e ícone com aspas são descartados', $clean['social'][1]['url'] === '' && $clean['social'][1]['iconUrl'] === '');
check('fonte fora da lista segura volta para Arial', $clean['style']['font'] === 'Arial');
check('cor inválida volta ao padrão e cor válida é normalizada', $clean['style']['colorName'] === '#2b2f2c' && $clean['style']['colorTitle'] === '#abcdef');
check('tamanho e alinhamento inválidos são ajustados', $clean['style']['sizeName'] === 28 && $clean['style']['align'] === 'left' && $clean['style']['underline'] === false);
check('entrada que não é lista vira o padrão', sig_sanitize('texto', $key) === sig_defaults());
check('validar duas vezes dá o mesmo resultado', sig_sanitize($clean, $key) === $clean);

echo "\nPadrão da empresa e assinaturas\n";
$default = sig_defaults();
$default['template'] = 'vertical';
$default['style']['colorTitle'] = '#112233';
$default['fields']['company']['value'] = 'Empresa';
$default['fields']['phone']['value'] = '(11) 3000-0000';
$default['fields']['name']['value'] = 'Nome de exemplo';
$fresh = sig_from_default($default, 'foto');
check('nova assinatura herda a identidade e troca o modelo quando pedido', $fresh['template'] === 'foto' && $fresh['style']['colorTitle'] === '#112233' && $fresh['fields']['company']['value'] === 'Empresa');
check('nova assinatura começa sem dados pessoais', $fresh['fields']['name']['value'] === '');
check('sem modelo pedido, vale o da empresa', sig_from_default($default)['template'] === 'vertical');
$person = sig_defaults();
$person['fields']['name']['value'] = 'Ana';
$person['fields']['phone']['value'] = '(11) 4000-1111';
$person['fields']['company']['value'] = 'Nome antigo';
$person['photo']['url'] = "/uploads/$key/" . str_repeat('c', 24) . '.png';
$applied = sig_apply_default($person, $default);
check('aplicar o padrão mantém nome, telefone próprio e foto', $applied['fields']['name']['value'] === 'Ana' && $applied['fields']['phone']['value'] === '(11) 4000-1111' && $applied['photo']['url'] !== '');
check('aplicar o padrão troca modelo, cores e dados da empresa', $applied['template'] === 'vertical' && $applied['style']['colorTitle'] === '#112233' && $applied['fields']['company']['value'] === 'Empresa');
$person['fields']['phone']['value'] = '';
check('sem telefone próprio, entra o telefone da empresa', sig_apply_default($person, $default)['fields']['phone']['value'] === '(11) 3000-0000');
$person['fields']['title']['value'] = 'Diretora';
$person['fields']['email']['value'] = 'ANA@Exemplo.com';
check('texto de busca fica em minúsculas', sig_search_text($person) === 'ana diretora  ana@exemplo.com');

echo "\nIsolamento entre empresas no banco\n";
$otherId = db_insert('companies', ['name' => 'Outra', 'public_key' => $other, 'status' => 'active', 'created_at' => now_utc()]);
$sigId = db_insert('signatures', ['company_id' => $companyId, 'name' => 'Ana', 'search_text' => 'ana', 'data' => '{}', 'share_token' => random_token(20), 'created_at' => now_utc(), 'updated_at' => now_utc()]);
check('assinatura é encontrada pela própria empresa', signature_find($sigId, $companyId) !== null);
check('assinatura não é encontrada por outra empresa', signature_find($sigId, $otherId) === null);

echo "\nImagens enviadas\n";
$fixtures = __DIR__ . '/fixtures';
$logo = image_prepare((string) file_get_contents($fixtures . '/logo.png'), 'logo');
check('logotipo PNG é aceito e regravado', $logo['ext'] === 'png' && $logo['width'] === 480 && @getimagesizefromstring($logo['bytes']) !== false);
$alpha = imagecolorat(imagecreatefromstring($logo['bytes']), 2, 2);
check('transparência do logotipo é preservada', (($alpha >> 24) & 0x7F) === 127);
$photo = image_prepare((string) file_get_contents($fixtures . '/foto.jpg'), 'photo');
check('foto é recortada em quadrado', $photo['width'] === IMG_PHOTO_SIZE && $photo['height'] === IMG_PHOTO_SIZE);
$circle = imagecreatefromstring((string) $photo['circle']);
check('versão redonda da foto tem cantos transparentes e centro opaco',
    ((imagecolorat($circle, 1, 1) >> 24) & 0x7F) === 127 && ((imagecolorat($circle, 132, 132) >> 24) & 0x7F) === 0);
$icon = image_prepare((string) file_get_contents($fixtures . '/logo.png'), 'icon');
check('ícone próprio é reduzido', $icon['width'] <= 128 && $icon['height'] <= 128);
check('arquivo que não é imagem é recusado', strpos(problem(fn () => image_prepare((string) file_get_contents($fixtures . '/falso.png'), 'logo')), 'não é uma imagem válida') !== false);
check('SVG é recusado com explicação', strpos(problem(fn () => image_prepare((string) file_get_contents($fixtures . '/vetor.svg'), 'logo')), 'SVG') !== false);
$gif = imagecreatetruecolor(20, 20);
ob_start();
imagegif($gif);
$gifBytes = (string) ob_get_clean();
check('GIF é mantido como GIF', image_prepare($gifBytes, 'banner')['ext'] === 'gif');
check('PHP disfarçado dentro de um PNG válido some ao regravar',
    strpos(image_prepare(file_get_contents($fixtures . '/logo.png') . '<?php system($_GET[1]); ?>', 'logo')['bytes'], '<?php') === false);

echo "\nEndereços de imagem\n";
$drive = image_url_candidates('https://drive.google.com/file/d/1AbCdEfGhIjKlMnOpQrStUvWxYz012345/view?usp=sharing');
check('link de compartilhamento do Google Drive vira endereço direto', $drive['source'] === 'drive' && $drive['candidates'][0] === 'https://lh3.googleusercontent.com/d/1AbCdEfGhIjKlMnOpQrStUvWxYz012345');
$drive = image_url_candidates('https://drive.google.com/open?id=1AbCdEfGhIjKlMnOpQrStUvWxYz012345');
check('link antigo do Drive com ?id= também é reconhecido', $drive['source'] === 'drive' && strpos($drive['candidates'][1], 'id=1AbCdEfGhIjKlMnOpQrStUvWxYz012345') !== false);
check('pasta do Drive recebe orientação', strpos(problem(fn () => image_url_candidates('https://drive.google.com/drive/folders/1AbCdEfGhIjKl')), 'pasta') !== false);
$dropbox = image_url_candidates('https://www.dropbox.com/s/abc123/logo.png?dl=0');
check('link do Dropbox passa a abrir o arquivo direto', $dropbox['source'] === 'dropbox' && strpos($dropbox['candidates'][0], 'raw=1') !== false && strpos($dropbox['candidates'][0], 'dl=0') === false);
check('endereço comum é usado como está', image_url_candidates('https://site.com.br/img/logo.png') === ['source' => 'direct', 'candidates' => ['https://site.com.br/img/logo.png']]);
check('http é recusado em produção', strpos(problem(fn () => image_url_guard('http://site.com.br/logo.png')), 'https://') !== false);
check('ftp é recusado', strpos(problem(fn () => image_url_guard('ftp://site.com.br/logo.png')), 'https://') !== false);
check('arquivo local é recusado', problem(fn () => image_url_guard('file:///etc/passwd')) !== '');
check('usuário e senha no endereço são recusados', strpos(problem(fn () => image_url_guard('https://user:pass@site.com.br/a.png')), 'usuário e senha') !== false);
check('porta diferente é recusada', strpos(problem(fn () => image_url_guard('https://site.com.br:8443/a.png')), 'porta') !== false);
foreach (['https://127.0.0.1/a.png', 'https://10.0.0.5/a.png', 'https://192.168.1.10/a.png', 'https://169.254.169.254/latest/meta-data', 'https://localhost/a.png'] as $internal) {
    check("rede interna é recusada: $internal", strpos(problem(fn () => image_url_guard($internal)), 'rede interna') !== false, problem(fn () => image_url_guard($internal)));
}
check('endereço com espaço ou aspas é recusado', problem(fn () => image_url_inspect('https://site.com.br/a b.png')) !== '' && problem(fn () => image_url_inspect('https://site.com.br/a".png')) !== '');
$private = problem(fn () => image_url_evaluate(['status' => 200, 'type' => 'text/html; charset=utf-8', 'body' => '<!DOCTYPE html><html>Sign in</html>', 'url' => 'x'], 'drive'));
check('Drive privado recebe orientação de compartilhamento', strpos($private, 'Qualquer pessoa com o link') !== false, $private);
$ok = image_url_evaluate(['status' => 200, 'type' => 'application/octet-stream', 'body' => (string) file_get_contents($fixtures . '/logo.png'), 'url' => 'https://lh3.googleusercontent.com/d/x'], 'drive');
check('imagem pública do Drive é aceita mesmo com tipo genérico, com aviso de estabilidade', $ok['width'] === 480 && strpos((string) $ok['warning'], 'cópia') !== false);

echo "\nÍcones\n";
$png = icon_render((string) icon_mask_path('circle', 'linkedin'), '2aa043');
$image = imagecreatefromstring($png);
$center = imagecolorat($image, 6, 48);
check('ícone é pintado na cor pedida', (($center >> 16) & 0xFF) === 0x2a && (($center >> 8) & 0xFF) === 0xa0 && ($center & 0xFF) === 0x43, dechex($center));
check('canto do ícone redondo é transparente', ((imagecolorat($image, 0, 0) >> 24) & 0x7F) === 127);
check('forma ou nome fora da biblioteca não gera ícone', icon_mask_path('circle', '../../config') === null && icon_mask_path('hexagon', 'linkedin') === null);
check('biblioteca tem os ícones de contato e de redes', count(sig_icon_names()) >= 30 && in_array('whatsapp', sig_icon_names(), true));

echo "\nAcesso\n";
check('usuário suspenso é bloqueado', access_block_reason(['status' => 'suspended', 'role' => 'client', 'company_id' => 1, 'company_status' => 'active']) !== null);
check('empresa suspensa bloqueia o usuário', access_block_reason(['status' => 'active', 'role' => 'client', 'company_id' => 1, 'company_status' => 'suspended']) !== null);
check('cliente sem empresa é bloqueado', access_block_reason(['status' => 'active', 'role' => 'client', 'company_id' => null, 'company_status' => null]) !== null);
check('cliente ativo de empresa ativa entra', access_block_reason(['status' => 'active', 'role' => 'client', 'company_id' => 1, 'company_status' => 'active']) === null);
check('administrador ativo entra', access_block_reason(['status' => 'active', 'role' => 'admin', 'company_id' => null]) === null);
check('senha curta ou diferente é recusada', password_problem('curta', 'curta') !== null && password_problem('senha-longa-1', 'senha-longa-2') !== null && password_problem('senha-longa-1', 'senha-longa-1') === null);
$link = invite_create($companyId, 'Ana', 'ana@exemplo.com', 'client', null);
$token = substr($link, -64);
check('convite válido é encontrado e guarda só o resumo do código', invite_find_valid($token) !== null && db_value('SELECT COUNT(*) FROM invites WHERE token_hash = ?', [$token]) == 0);
invite_create($companyId, 'Ana', 'ana@exemplo.com', 'client', null);
check('novo convite para o mesmo e-mail cancela o anterior', invite_find_valid($token) === null);
$expiring = substr(invite_create($companyId, 'Bia', 'bia@exemplo.com', 'client', null), -64);
check('convite novo vale', invite_find_valid($expiring) !== null);
db_run('UPDATE invites SET expires_at = ?', ['2020-01-01 00:00:00']);
check('convite vencido não vale', invite_find_valid($expiring) === null);
check('código de convite malformado não vale', invite_find_valid('abc') === null && invite_find_valid(str_repeat('g', 64)) === null);

echo "\n$passed verificações passaram, $failed falharam.\n";
exit($failed > 0 ? 1 : 0);
