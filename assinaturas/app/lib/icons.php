<?php
declare(strict_types=1);

/**
 * Gerador dos ícones da biblioteca na cor escolhida pelo cliente.
 *
 * Endereço: /icones/{forma}/{cor}/{nome}.png  (cor em hexadecimal, sem #)
 * As máscaras ficam em app/resources/icons e são geradas por tools/build-icons.mjs.
 * Clientes de e-mail não exibem SVG, por isso cada ícone é entregue como PNG.
 */

function icon_mask_path(string $shape, string $name): ?string
{
    if (!in_array($shape, SIG_ICON_SHAPES, true) || !in_array($name, sig_icon_names(), true)) {
        return null;
    }
    $path = KW_ROOT . '/app/resources/icons/' . $shape . '/' . $name . '.png';
    return is_file($path) ? $path : null;
}

/** Monta o PNG do ícone: vermelho da máscara vira a cor pedida, verde vira branco. */
function icon_render(string $maskPath, string $hex): string
{
    $mask = imagecreatefrompng($maskPath);
    imagepalettetotruecolor($mask);
    $size = imagesx($mask);
    $red = hexdec(substr($hex, 0, 2));
    $green = hexdec(substr($hex, 2, 2));
    $blue = hexdec(substr($hex, 4, 2));

    $icon = imagecreatetruecolor($size, $size);
    imagealphablending($icon, false);
    imagesavealpha($icon, true);
    for ($y = 0; $y < $size; $y++) {
        for ($x = 0; $x < $size; $x++) {
            $pixel = imagecolorat($mask, $x, $y);
            $color = (($pixel >> 16) & 0xFF) / 255; // cobertura da cor
            $white = (($pixel >> 8) & 0xFF) / 255;  // cobertura de branco
            $opacity = min(1.0, $color + $white);
            if ($opacity <= 0.0) {
                imagesetpixel($icon, $x, $y, 0x7F000000);
                continue;
            }
            $r = (int) round(($red * $color + 255 * $white) / ($color + $white));
            $g = (int) round(($green * $color + 255 * $white) / ($color + $white));
            $b = (int) round(($blue * $color + 255 * $white) / ($color + $white));
            $alpha = (int) round(127 - 127 * $opacity);
            imagesetpixel($icon, $x, $y, ($alpha << 24) | ($r << 16) | ($g << 8) | $b);
        }
    }
    ob_start();
    imagepng($icon, null, 9);
    $png = (string) ob_get_clean();
    return $png;
}

/** Responde a /icones/... gerando o ícone na hora. Os arquivos prontos são gravados por icons_warm(). */
function icon_serve(string $shape, string $hex, string $name): void
{
    $hex = strtolower($hex);
    $mask = icon_mask_path($shape, $name);
    if ($mask === null || !preg_match('/^[0-9a-f]{6}$/', $hex) || !function_exists('imagecreatefrompng')) {
        http_response_code(404);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Ícone não encontrado.';
        exit;
    }
    $png = icon_render($mask, $hex);
    header('Content-Type: image/png');
    header('Content-Length: ' . strlen($png));
    header('Cache-Control: public, max-age=604800');
    header('X-Content-Type-Options: nosniff');
    echo $png;
    exit;
}

/**
 * Grava em public/icones os ícones usados por uma assinatura salva.
 * Depois disso o servidor entrega os arquivos direto, sem passar pelo PHP a cada e-mail aberto.
 * Só dados salvos geram arquivos, então a pasta não cresce com cores testadas no editor.
 */
function icons_warm(array $data): void
{
    if (!function_exists('imagecreatefrompng')) {
        return;
    }
    $hex = ltrim((string) ($data['style']['colorIcon'] ?? ''), '#');
    if (!preg_match('/^[0-9a-f]{6}$/', $hex)) {
        return;
    }
    $wanted = [];
    if (($data['style']['contactStyle'] ?? '') === 'icons') {
        foreach (SIG_CONTACT_FIELDS as $key) {
            $wanted[] = ['plain', $key];
        }
    }
    $shape = (string) ($data['style']['iconShape'] ?? 'plain');
    foreach ($data['social'] ?? [] as $item) {
        if (($item['type'] ?? 'custom') !== 'custom') {
            $wanted[] = [$shape, (string) $item['type']];
        }
    }
    foreach ($wanted as [$iconShape, $name]) {
        $mask = icon_mask_path($iconShape, $name);
        if ($mask === null) {
            continue;
        }
        $dir = KW_ROOT . '/public/icones/' . $iconShape . '/' . $hex;
        $file = $dir . '/' . $name . '.png';
        if (is_file($file)) {
            continue;
        }
        if (is_dir($dir) || @mkdir($dir, 0755, true) || is_dir($dir)) {
            @file_put_contents($file, icon_render($mask, $hex), LOCK_EX);
        }
    }
}
