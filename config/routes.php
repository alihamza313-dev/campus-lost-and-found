<?php
declare(strict_types=1);

use App\Controllers\HomeController;
use App\Core\Router;

return static function (Router $router): void {
    $router->get('/', [HomeController::class, 'index']);
    $router->get('/health', [HomeController::class, 'health']);
};
