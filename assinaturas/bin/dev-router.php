<?php
/**
 * Roteador para o servidor embutido do PHP, usado só em desenvolvimento e nos testes:
 *   php -S 127.0.0.1:8080 -t public bin/dev-router.php
 * Em produção quem faz este papel é o public/.htaccess.
 */
$path = rawurldecode((string) parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
$file = dirname(__DIR__) . '/public' . $path;
if ($path !== '/' && strpos($path, '..') === false && is_file($file) && !preg_match('/\.php$/i', $path)) {
    return false; // arquivo estático
}
require dirname(__DIR__) . '/public/index.php';
