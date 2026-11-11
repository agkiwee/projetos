<?php
declare(strict_types=1);

const IMG_KINDS = ['logo', 'photo', 'icon', 'banner'];
const IMG_MAX_UPLOAD_BYTES = 4194304;   // 4 MB por arquivo enviado
const IMG_MAX_REMOTE_BYTES = 5242880;   // 5 MB ao importar por URL
const IMG_MAX_PIXELS = 16000000;        // protege a memória do servidor (fotos de celular têm cerca de 12 milhões)
const IMG_PHOTO_SIZE = 264;             // foto quadrada, 3x o tamanho padrão de exibição

/** Erro com mensagem pronta para ser mostrada ao usuário. */
class ImageProblem extends RuntimeException
{
}

/**
 * Valida os bytes de uma imagem e prepara os arquivos que serão hospedados.
 * Toda imagem é regravada pelo GD como PNG, o que remove metadados e qualquer conteúdo estranho
 * escondido no arquivo. Por isso um GIF animado vira uma imagem parada (o primeiro quadro).
 *
 * @return array{ext:string,mime:string,width:int,height:int,bytes:string,circle:?string}
 */
function image_prepare(string $bytes, string $kind): array
{
    if (!function_exists('imagecreatefromstring')) {
        throw new ImageProblem('O servidor está sem a extensão GD do PHP, necessária para tratar imagens.');
    }
    $info = @getimagesizefromstring($bytes);
    if ($info === false) {
        if (stripos(ltrim(substr($bytes, 0, 300)), '<svg') !== false || stripos(substr($bytes, 0, 300), '<?xml') !== false) {
            throw new ImageProblem('Arquivos SVG não funcionam em clientes de e-mail. Use PNG, JPG ou GIF.');
        }
        throw new ImageProblem('O arquivo não é uma imagem válida. Use PNG, JPG ou GIF.');
    }
    [$width, $height, $type] = $info;
    $allowed = [IMAGETYPE_PNG, IMAGETYPE_JPEG, IMAGETYPE_GIF, IMAGETYPE_WEBP];
    if (!in_array($type, $allowed, true)) {
        throw new ImageProblem('Formato de imagem não aceito. Use PNG, JPG ou GIF.');
    }
    if ($width < 1 || $height < 1 || $width * $height > IMG_MAX_PIXELS) {
        throw new ImageProblem('A imagem é grande demais. Reduza para no máximo 4000 pixels no lado maior.');
    }

    // Imagens grandes precisam de mais memória só durante o tratamento.
    @ini_set('memory_limit', '256M');
    $source = @imagecreatefromstring($bytes);
    if (!$source) {
        throw new ImageProblem('Não foi possível ler a imagem. Tente salvar o arquivo de novo como PNG ou JPG.');
    }
    imagepalettetotruecolor($source);
    if ($type === IMAGETYPE_JPEG) {
        $source = image_fix_orientation($source, $bytes);
        $width = imagesx($source);
        $height = imagesy($source);
    }

    if ($kind === 'photo') {
        $side = min($width, $height);
        $square = image_canvas(IMG_PHOTO_SIZE, IMG_PHOTO_SIZE);
        imagecopyresampled(
            $square, $source, 0, 0,
            (int) (($width - $side) / 2), (int) (($height - $side) / 2),
            IMG_PHOTO_SIZE, IMG_PHOTO_SIZE, $side, $side
        );
        $circle = image_circle($square);
        $result = [
            'ext' => 'png', 'mime' => 'image/png', 'width' => IMG_PHOTO_SIZE, 'height' => IMG_PHOTO_SIZE,
            'bytes' => image_png($square), 'circle' => image_png($circle),
        ];
        return $result;
    }

    $limits = ['logo' => [720, 480], 'icon' => [128, 128], 'banner' => [1200, 600]];
    [$maxWidth, $maxHeight] = $limits[$kind] ?? [720, 480];
    $scale = min(1, $maxWidth / $width, $maxHeight / $height);
    $newWidth = max(1, (int) round($width * $scale));
    $newHeight = max(1, (int) round($height * $scale));
    $canvas = image_canvas($newWidth, $newHeight);
    imagecopyresampled($canvas, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
    $result = [
        'ext' => 'png', 'mime' => 'image/png', 'width' => $newWidth, 'height' => $newHeight,
        'bytes' => image_png($canvas), 'circle' => null,
    ];
    return $result;
}

/** Tela transparente pronta para receber uma imagem com canal alfa. */
function image_canvas(int $width, int $height)
{
    $canvas = imagecreatetruecolor($width, $height);
    imagealphablending($canvas, false);
    imagesavealpha($canvas, true);
    imagefill($canvas, 0, 0, imagecolorallocatealpha($canvas, 255, 255, 255, 127));
    return $canvas;
}

function image_png($image): string
{
    ob_start();
    imagepng($image, null, 8);
    return (string) ob_get_clean();
}

/** Recorta a foto em círculo com borda suave. Funciona até no Outlook clássico, que ignora cantos arredondados. */
function image_circle($square)
{
    $size = imagesx($square);
    $circle = image_canvas($size, $size);
    $center = ($size - 1) / 2;
    $radius = $size / 2;
    for ($y = 0; $y < $size; $y++) {
        for ($x = 0; $x < $size; $x++) {
            $distance = sqrt(($x - $center) ** 2 + ($y - $center) ** 2);
            $coverage = max(0.0, min(1.0, $radius - $distance));
            if ($coverage <= 0) {
                continue;
            }
            $rgba = imagecolorat($square, $x, $y);
            $alpha = ($rgba >> 24) & 0x7F;
            $opacity = (1 - $alpha / 127) * $coverage;
            $color = imagecolorallocatealpha(
                $circle, ($rgba >> 16) & 0xFF, ($rgba >> 8) & 0xFF, $rgba & 0xFF,
                (int) round(127 - 127 * $opacity)
            );
            imagesetpixel($circle, $x, $y, $color);
        }
    }
    return $circle;
}

/** Fotos de celular guardam a rotação nos metadados. Aplica a rotação antes de regravar. */
function image_fix_orientation($image, string $bytes)
{
    if (!function_exists('exif_read_data')) {
        return $image;
    }
    $stream = fopen('php://temp', 'r+b');
    fwrite($stream, $bytes);
    rewind($stream);
    $exif = @exif_read_data($stream);
    fclose($stream);
    $angles = [3 => 180, 6 => -90, 8 => 90];
    $orientation = (int) ($exif['Orientation'] ?? 1);
    if (!isset($angles[$orientation])) {
        return $image;
    }
    $rotated = imagerotate($image, $angles[$orientation], 0);
    return $rotated ?: $image;
}

function uploads_dir(array $company): string
{
    return KW_PUBLIC . '/uploads/' . $company['public_key'];
}

/**
 * Grava uma imagem na pasta pública da empresa e registra no banco.
 * Com $replace, o arquivo existente é trocado mantendo o mesmo endereço,
 * o que atualiza as assinaturas já instaladas sem reinstalar.
 */
function asset_store(array $company, string $kind, string $bytes, string $originalName, ?int $userId, ?array $replace = null): array
{
    if (!in_array($kind, IMG_KINDS, true)) {
        throw new ImageProblem('Tipo de imagem desconhecido.');
    }
    $prepared = image_prepare($bytes, $kind);
    $dir = uploads_dir($company);
    if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
        throw new ImageProblem('Não foi possível criar a pasta de arquivos no servidor. Verifique as permissões da pasta uploads.');
    }

    if ($replace !== null) {
        $currentExt = pathinfo((string) $replace['path'], PATHINFO_EXTENSION);
        if ($currentExt !== $prepared['ext']) {
            throw new ImageProblem('Para manter o mesmo endereço, envie uma imagem do mesmo tipo da atual (' . strtoupper($currentExt) . ').');
        }
        $base = pathinfo((string) $replace['path'], PATHINFO_FILENAME);
    } else {
        $base = random_token(12);
    }

    $file = $base . '.' . $prepared['ext'];
    $pathCircle = null;
    if (@file_put_contents($dir . '/' . $file, $prepared['bytes'], LOCK_EX) === false) {
        throw new ImageProblem('Não foi possível gravar o arquivo no servidor.');
    }
    if ($prepared['circle'] !== null) {
        file_put_contents($dir . '/' . $base . '-c.png', $prepared['circle'], LOCK_EX);
        $pathCircle = '/uploads/' . $company['public_key'] . '/' . $base . '-c.png';
    }

    $row = [
        'kind' => $kind,
        'path' => '/uploads/' . $company['public_key'] . '/' . $file,
        'path_circle' => $pathCircle,
        'original_name' => mb_substr(basename($originalName), 0, 180),
        'mime' => $prepared['mime'],
        'size_bytes' => strlen($prepared['bytes']),
        'width' => $prepared['width'],
        'height' => $prepared['height'],
        'updated_at' => now_utc(),
    ];
    if ($replace !== null) {
        db_update('assets', $row, 'id = ? AND company_id = ?', [(int) $replace['id'], (int) $company['id']]);
        $id = (int) $replace['id'];
    } else {
        $id = db_insert('assets', $row + [
            'company_id' => (int) $company['id'],
            'created_by' => $userId,
            'created_at' => now_utc(),
        ]);
    }
    return asset_payload(db_one('SELECT * FROM assets WHERE id = ?', [$id]));
}

function asset_payload(array $row): array
{
    return [
        'id' => (int) $row['id'],
        'kind' => $row['kind'],
        'url' => $row['path'],
        'urlCircle' => $row['path_circle'] ?: '',
        'name' => $row['original_name'],
        'width' => (int) $row['width'],
        'height' => (int) $row['height'],
        'ratio' => $row['width'] > 0 ? round($row['height'] / $row['width'], 4) : 0,
        'updatedAt' => format_date($row['updated_at']),
        'version' => strtotime((string) $row['updated_at']),
    ];
}

/* ------------------------------------------------------------------------
 * Importação de imagens por endereço público
 * --------------------------------------------------------------------- */

/**
 * Reconhece links de compartilhamento conhecidos e devolve os endereços diretos a tentar.
 *
 * @return array{source:string,candidates:string[]}
 */
function image_url_candidates(string $url): array
{
    $parts = parse_url($url);
    $host = strtolower((string) ($parts['host'] ?? ''));
    $path = (string) ($parts['path'] ?? '');
    parse_str((string) ($parts['query'] ?? ''), $query);

    if (in_array($host, ['drive.google.com', 'docs.google.com', 'drive.usercontent.google.com'], true)) {
        $id = '';
        if (preg_match('#/(?:file/)?d/([A-Za-z0-9_-]{10,})#', $path, $match)) {
            $id = $match[1];
        } elseif (isset($query['id']) && is_string($query['id']) && preg_match('/^[A-Za-z0-9_-]{10,}$/', $query['id'])) {
            $id = $query['id'];
        }
        if ($id !== '') {
            return ['source' => 'drive', 'candidates' => [
                'https://lh3.googleusercontent.com/d/' . $id,
                'https://drive.usercontent.google.com/download?id=' . $id . '&export=view',
                'https://drive.google.com/uc?export=view&id=' . $id,
            ]];
        }
        if (strpos($path, '/folders/') !== false || strpos($path, '/drive/') !== false) {
            throw new ImageProblem('Este link é de uma pasta do Google Drive. Abra a imagem, use Compartilhar e copie o link do arquivo.');
        }
    }
    if (preg_match('/(^|\.)dropbox\.com$/', $host)) {
        $query['raw'] = '1';
        unset($query['dl']);
        return ['source' => 'dropbox', 'candidates' => ['https://' . $host . $path . '?' . http_build_query($query)]];
    }
    return ['source' => 'direct', 'candidates' => [$url]];
}

/** Confere esquema, host e destino de rede antes de qualquer acesso. Devolve [host, porta, ip]. */
function image_url_guard(string $url): array
{
    $insecure = (bool) config('dev_allow_insecure_url_import', false);
    $parts = parse_url($url);
    $scheme = strtolower((string) ($parts['scheme'] ?? ''));
    $host = strtolower((string) ($parts['host'] ?? ''));
    // Só nomes simples: evita que o PHP e o cURL entendam o mesmo endereço de formas diferentes.
    if ($parts === false || $host === '' || !preg_match('/^[a-z0-9.\-]+$/', $host)) {
        throw new ImageProblem('O endereço não é válido. Cole o link completo, começando com https://');
    }
    if ($scheme !== 'https' && !($insecure && $scheme === 'http')) {
        throw new ImageProblem('Use um endereço que comece com https://. Links http, ftp ou de arquivos locais não são aceitos em e-mails.');
    }
    if (isset($parts['user']) || isset($parts['pass'])) {
        throw new ImageProblem('O endereço não pode conter usuário e senha.');
    }
    $port = (int) ($parts['port'] ?? ($scheme === 'https' ? 443 : 80));
    if (!$insecure && $port !== 443) {
        throw new ImageProblem('O endereço precisa usar a porta padrão do https.');
    }

    $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : (@gethostbynamel($host) ?: []);
    if (!$ips) {
        throw new ImageProblem('Não foi possível encontrar o servidor "' . $host . '". Confira se o endereço está correto.');
    }
    foreach ($ips as $ip) {
        $public = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false
            && !ip_in_extra_blocked_range($ip);
        if (!$public && !$insecure) {
            throw new ImageProblem('Este endereço aponta para uma rede interna e não pode ser aberto por quem recebe o e-mail.');
        }
    }
    return [$host, $port, $ips[0]];
}

/**
 * Faixas que o filtro do PHP considera públicas mas que não são da internet aberta:
 * redes de operadora e de nuvem (100.64/10), testes (198.18/15), protocolos (192.0.0/24, 192.88.99/24) e multicast.
 */
function ip_in_extra_blocked_range(string $ip): bool
{
    $long = ip2long($ip);
    if ($long === false) {
        return true; // só IPv4 chega até aqui; qualquer outra coisa é recusada
    }
    $ranges = [['100.64.0.0', 10], ['192.0.0.0', 24], ['192.88.99.0', 24], ['198.18.0.0', 15], ['224.0.0.0', 4], ['240.0.0.0', 4], ['0.0.0.0', 8]];
    foreach ($ranges as [$base, $bits]) {
        $mask = -1 << (32 - $bits);
        if (($long & $mask) === (ip2long($base) & $mask)) {
            return true;
        }
    }
    return false;
}

/**
 * Baixa um endereço seguindo redirecionamentos um a um, validando cada destino.
 *
 * @return array{status:int,type:string,body:string,url:string}
 */
function image_url_fetch(string $url): array
{
    if (!function_exists('curl_init')) {
        throw new ImageProblem('O servidor está sem a extensão cURL do PHP, necessária para importar imagens por URL.');
    }
    for ($hop = 0; $hop < 5; $hop++) {
        [$host, $port, $ip] = image_url_guard($url);
        $body = '';
        $tooBig = false;
        $handle = curl_init($url);
        $usingProxy = getenv('HTTPS_PROXY') || getenv('https_proxy') || getenv('ALL_PROXY') || getenv('all_proxy');
        $options = [
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_HEADER => false,
            CURLOPT_CONNECTTIMEOUT => 6,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_USERAGENT => 'KiweeAssinaturas/1.0 (verificador de imagens)',
            CURLOPT_HTTPHEADER => ['Accept: image/png,image/jpeg,image/gif,image/*;q=0.8,*/*;q=0.5'],
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS | CURLPROTO_HTTP,
            CURLOPT_WRITEFUNCTION => static function ($curl, string $chunk) use (&$body, &$tooBig): int {
                $body .= $chunk;
                if (strlen($body) > IMG_MAX_REMOTE_BYTES) {
                    $tooBig = true;
                    return 0;
                }
                return strlen($chunk);
            },
        ];
        if (!$usingProxy) {
            // Fixa o IP já validado, para o servidor não ser levado a outro destino depois da checagem.
            $options[CURLOPT_RESOLVE] = [$host . ':' . $port . ':' . $ip];
        }
        curl_setopt_array($handle, $options);
        curl_exec($handle);
        $error = curl_errno($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $type = strtolower((string) curl_getinfo($handle, CURLINFO_CONTENT_TYPE));
        $location = (string) curl_getinfo($handle, CURLINFO_REDIRECT_URL);

        if ($tooBig) {
            throw new ImageProblem('A imagem tem mais de 5 MB. Reduza o arquivo antes de usar na assinatura.');
        }
        if ($error !== 0 && $status === 0) {
            throw new ImageProblem('Não foi possível abrir o endereço. Confira se o link está correto e se o site está no ar.');
        }
        if ($status >= 300 && $status < 400 && $location !== '') {
            $url = $location;
            continue;
        }
        return ['status' => $status, 'type' => $type, 'body' => $body, 'url' => $url];
    }
    throw new ImageProblem('O endereço redireciona vezes demais. Use o link direto da imagem.');
}

/**
 * Verifica se um endereço abre uma imagem compatível, sem login.
 *
 * @return array{url:string,source:string,width:int,height:int,ratio:float,mime:string,bytes:string,warning:?string}
 */
function image_url_inspect(string $url): array
{
    $url = trim($url);
    if ($url === '' || strlen($url) > 600 || preg_match('/[\s<>"\'`\\\\]/', $url)) {
        throw new ImageProblem('O endereço não é válido. Cole o link completo, começando com https://');
    }
    if (!preg_match('#^[a-z][a-z0-9+.\-]*://#i', $url)) {
        $url = 'https://' . $url;
    }
    // Cada endereço tentado passa por image_url_guard() dentro de image_url_fetch(), inclusive nos redirecionamentos.
    $plan = image_url_candidates($url);
    $lastProblem = null;

    foreach ($plan['candidates'] as $candidate) {
        try {
            $response = image_url_fetch($candidate);
            return image_url_evaluate($response, $plan['source']);
        } catch (ImageProblem $problem) {
            $lastProblem = $problem;
        }
    }
    throw $lastProblem ?? new ImageProblem('Não foi possível abrir o endereço.');
}

function image_url_evaluate(array $response, string $source): array
{
    $status = $response['status'];
    $body = $response['body'];
    $info = $status === 200 ? @getimagesizefromstring($body) : false;

    if ($info === false) {
        $looksHtml = strpos($response['type'], 'text/html') !== false || stripos(ltrim(substr($body, 0, 200)), '<!doctype html') === 0
            || stripos(ltrim(substr($body, 0, 200)), '<html') === 0;
        if ($source === 'drive') {
            throw new ImageProblem('Este arquivo do Google Drive não está público ou não é uma imagem. No Drive, clique em Compartilhar, '
                . 'escolha "Qualquer pessoa com o link" e tente de novo.');
        }
        if (in_array($status, [401, 403], true)) {
            throw new ImageProblem('O link é privado: o servidor pediu login ou negou o acesso. Deixe o arquivo público e tente de novo.');
        }
        if ($status === 404 || $status === 410) {
            throw new ImageProblem('Nenhum arquivo foi encontrado neste endereço. Confira se o link está completo.');
        }
        if ($status >= 500) {
            throw new ImageProblem('O servidor da imagem respondeu com erro. Tente novamente em alguns minutos.');
        }
        if (stripos(ltrim(substr($body, 0, 300)), '<svg') !== false || strpos($response['type'], 'svg') !== false) {
            throw new ImageProblem('Arquivos SVG não funcionam em clientes de e-mail. Use PNG, JPG ou GIF.');
        }
        if ($looksHtml) {
            throw new ImageProblem('O endereço abre uma página, não a imagem diretamente. Pode ser um link privado ou de visualização. '
                . 'Use o endereço que abre só a imagem, sem pedir login.');
        }
        throw new ImageProblem('O endereço não abre uma imagem compatível. Use um arquivo PNG, JPG ou GIF.');
    }

    [$width, $height, $type] = $info;
    $mimes = [IMAGETYPE_PNG => 'image/png', IMAGETYPE_JPEG => 'image/jpeg', IMAGETYPE_GIF => 'image/gif'];
    // Confere o arquivo inteiro, não só o cabeçalho.
    if (!isset($mimes[$type]) || !function_exists('imagecreatefromstring') || @imagecreatefromstring($body) === false) {
        throw new ImageProblem('O formato desta imagem não é aceito por todos os clientes de e-mail. Use PNG, JPG ou GIF.');
    }
    $warning = null;
    if ($source === 'drive') {
        $warning = 'Links do Google Drive podem parar de carregar em e-mails. Recomendamos guardar uma cópia na plataforma.';
    } elseif ($width > 1600) {
        $warning = 'A imagem é bem maior do que o necessário. Uma versão menor carrega mais rápido nos e-mails.';
    }
    return [
        'url' => $response['url'],
        'source' => $source,
        'width' => (int) $width,
        'height' => (int) $height,
        'ratio' => $width > 0 ? round($height / $width, 4) : 0.0,
        'mime' => $mimes[$type],
        'bytes' => $body,
        'warning' => $warning,
    ];
}
