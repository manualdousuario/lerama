<?php
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Laminas\Diactoros\ServerRequestFactory;
use Laminas\Diactoros\Response;
use Laminas\HttpHandlerRunner\Emitter\SapiEmitter;
use League\Route\Router;
use Lerama\Controllers\HomeController;
use Lerama\Controllers\FeedController;
use Lerama\Controllers\AdminController;
use Lerama\Middleware\AuthMiddleware;

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

// Initialize database connection
DB::$host = $_ENV['DB_HOST'];
DB::$user = $_ENV['DB_USER'];
DB::$password = $_ENV['DB_PASS'];
DB::$dbName = $_ENV['DB_NAME'];
DB::$port = (int)$_ENV['DB_PORT'];
DB::$encoding = 'utf8mb4';

// Create the router
$router = new Router();

// Define routes
// Public routes
$router->map('GET', '/', [HomeController::class, 'index']);
$router->map('GET', '/page/{page:number}', [HomeController::class, 'index']);
$router->map('GET', '/feeds', [FeedController::class, 'index']);
$router->map('GET', '/feed', [FeedController::class, 'rss']);
$router->map('GET', '/feed/json', [FeedController::class, 'json']);
$router->map('GET', '/feed/rss', [FeedController::class, 'rss']);
$router->map('GET', '/admin/login', [AdminController::class, 'loginForm']);
$router->map('POST', '/admin/login', [AdminController::class, 'login']);

// Admin routes (protected by middleware)
$router->group('/admin', function ($router) {
    $router->map('GET', '/', [AdminController::class, 'index']);
    $router->map('GET', '/feeds', [AdminController::class, 'feeds']);
    $router->map('GET', '/feeds/new', [AdminController::class, 'newFeedForm']);
    $router->map('POST', '/feeds/new', [AdminController::class, 'newFeedForm']);
    $router->map('GET', '/feeds/{id:number}/edit', [AdminController::class, 'editFeedForm']);
    $router->map('POST', '/feeds/{id:number}/edit', [AdminController::class, 'editFeedForm']);
    $router->map('POST', '/feeds', [AdminController::class, 'createFeed']);
    $router->map('PUT', '/feeds/{id:number}', [AdminController::class, 'updateFeed']);
    $router->map('DELETE', '/feeds/{id:number}', [AdminController::class, 'deleteFeed']);
    $router->map('PUT', '/items/{id:number}', [AdminController::class, 'updateItem']);
    $router->map('GET', '/logout', [AdminController::class, 'logout']);
})->middleware(new AuthMiddleware());

// Process the request
$request = ServerRequestFactory::fromGlobals();
$response = $router->dispatch($request);

// Emit the response
(new SapiEmitter())->emit($response);