<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once ROOT_PATH . '/app/routes.php';

$router = new Router();
register_routes($router);
$router->dispatch(method(), request_path());
