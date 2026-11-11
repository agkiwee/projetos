<?php
declare(strict_types=1);

// A pasta pública é a pasta deste arquivo, seja qual for o nome dela no servidor (public, public_html, htdocs).
define('KW_PUBLIC', __DIR__);

require dirname(__DIR__) . '/app/bootstrap.php';

kw_dispatch();
