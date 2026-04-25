<?php

date_default_timezone_set('Asia/Manila');

spl_autoload_register(function ($class) {
    $path = [
        __DIR__ . '/../app/Controllers/' . $class . '.php',
        __DIR__ . '/../app/Models/' . $class . '.php',
        __DIR__ . '/../app/Core' . $class . '.php'
    ];

    foreach ($path as $file) {
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }
});

//load session
require_once __DIR__ . '/../app/Core/Session.php';
Session::start();

//load routes
$routes = require __DIR__ . '/../app/Config/routes.php';

//parse url
$url = $_GET['url'] ?? '';
$url = '/' . trim($url, '/');

//remove /public from url
if(str_starts_with($url, '/public')) {
    $url = substr($url, 7);
}

//match route
if(!isset($routes[$url])){
    http_response_code(404);
    require_once __DIR__ . '/../app/Views/errors/404.php';
    exit;
}

//get controller and method
$route = explode('@', $routes [$url]);
$controllerName = $route[0] . 'Controller';
$methodName = $route[1] ?? 'index';

//check if controller exists
if(!class_exists($controllerName)) {
    http_response_code(500);
    echo "<h1>Error: Controller {$controllerName} not found</h1>";
    exit;
}

$controller = new $controllerName();
if(!method_exists($controller, $methodName)) {
    http_response_code(500);
    echo "<h1>Error: Method {$methodName} not found in controller {$controllerName}</h1>";
    exit;
}
$controller->$methodName();