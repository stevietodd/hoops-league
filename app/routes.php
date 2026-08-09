<?php

declare(strict_types=1);

require_once ROOT_PATH . '/app/controllers/HomeController.php';
require_once ROOT_PATH . '/app/controllers/AuthController.php';
require_once ROOT_PATH . '/app/controllers/ScheduleController.php';
require_once ROOT_PATH . '/app/controllers/TeamController.php';
require_once ROOT_PATH . '/app/controllers/ManageController.php';

function register_routes(Router $router): void
{
    $router->get('/', [HomeController::class, 'home']);
    $router->get('/standings', [HomeController::class, 'standings']);

    $router->get('/login', [AuthController::class, 'loginForm']);
    $router->post('/login', [AuthController::class, 'login']);
    $router->post('/logout', [AuthController::class, 'logout']);

    $router->get('/schedule', [ScheduleController::class, 'index']);
    $router->get('/games/{id}', [ScheduleController::class, 'show']);
    $router->get('/games/{id}/report', [ScheduleController::class, 'reportForm']);
    $router->post('/games/{id}/report', [ScheduleController::class, 'report']);

    $router->get('/teams', [TeamController::class, 'index']);
    $router->get('/teams/{id}', [TeamController::class, 'show']);
    $router->post('/teams/{id}/roster/add', [TeamController::class, 'addPlayer']);
    $router->post('/teams/{teamId}/roster/{playerId}/remove', [TeamController::class, 'removePlayer']);
    $router->post('/teams/{teamId}/roster/{playerId}/update', [TeamController::class, 'updatePlayer']);
    $router->post('/teams/{id}/update', [TeamController::class, 'update']);
    $router->post('/teams/{id}/captain', [TeamController::class, 'assignCaptain']);

    $router->get('/manage', [ManageController::class, 'hub']);
    $router->get('/manage/games/new', [ManageController::class, 'gameCreateForm']);
    $router->post('/manage/games/new', [ManageController::class, 'gameCreate']);
    $router->get('/manage/games/{id}/edit', [ManageController::class, 'gameEditForm']);
    $router->post('/manage/games/{id}/edit', [ManageController::class, 'gameEdit']);
    $router->get('/manage/users', [ManageController::class, 'users']);
    $router->post('/manage/users/{id}/toggle-commissioner', [ManageController::class, 'toggleCommissioner']);
}
