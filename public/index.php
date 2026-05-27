<?php
require __DIR__ . '/../vendor/autoload.php';

use Atom\Application;
use Atom\Config;
use Atom\Http\{Request, Response};

$app = new Application(new Config(
    viewsDir: __DIR__ . '/../views',
    cacheDir: __DIR__ . '/../storage/cache',
));

$app->router->group('/api', [], function ($r) {
    $r->get('/users/{id}',       UserController::class . '@show', 'user.show');
    $r->get('/posts/{slug}',     PostController::class . '@show', 'post.show');
    $r->post('/posts',           PostController::class . '@create');
});

$app->router->get('/', 'HomeController@index');

$app->run();
