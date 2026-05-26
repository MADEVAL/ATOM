<?php
require __DIR__ . '/../vendor/autoload.php';

use Atom\Application;
use Atom\Http\{Request, Response};

$app = new Application([
    'views_dir' => __DIR__ . '/../views',
    'cache_dir' => __DIR__ . '/../storage/cache',
]);

// Группы, middleware, именованные маршруты
$app->router->group('/api', [], function ($r) {
    $r->get('/users/{id}',       UserController::class . '@show', 'user.show');
    $r->get('/posts/{slug}',     PostController::class . '@show', 'post.show');
    $r->post('/posts',           PostController::class . '@create');
});

$app->router->get('/', function () use ($app) {
    return $app->view->render('home.twig', ['title' => 'Atom ⚛']);
});

// Генерация URL
// $app->router->url('user.show', ['id' => 42]); // => /api/users/42

$app->run();
