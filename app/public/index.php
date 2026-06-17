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
use Lerama\Controllers\SuggestionController;
use Lerama\Middleware\AuthMiddleware;
use Lerama\Middleware\SecurityHeadersMiddleware;

ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
error_reporting(E_ALL);

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

if (($_ENV['APP_ENV'] ?? 'production') !== 'production') {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
}

$cookieSecure = filter_var($_ENV['SESSION_COOKIE_SECURE'] ?? true, FILTER_VALIDATE_BOOLEAN);
$cookieHttpOnly = filter_var($_ENV['SESSION_COOKIE_HTTPONLY'] ?? true, FILTER_VALIDATE_BOOLEAN);
$cookieSameSite = $_ENV['SESSION_COOKIE_SAMESITE'] ?? 'Strict';

session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '',
    'secure' => $cookieSecure,
    'httponly' => $cookieHttpOnly,
    'samesite' => $cookieSameSite,
]);

ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
ini_set('session.cookie_httponly', $cookieHttpOnly ? '1' : '0');
ini_set('session.cookie_secure', $cookieSecure ? '1' : '0');
ini_set('session.cookie_samesite', $cookieSameSite);

// Initialize translator
require_once __DIR__ . '/../src/Services/Translator.php';
\App\Services\Translator::getInstance();

// Initialize database connection
DB::$host = $_ENV['LERAMA_DB_HOST'];
DB::$user = $_ENV['LERAMA_DB_USER'];
DB::$password = $_ENV['LERAMA_DB_PASS'];
DB::$dbName = $_ENV['LERAMA_DB_NAME'];
DB::$port = (int)$_ENV['LERAMA_DB_PORT'];
DB::$encoding = 'utf8mb4';

// Create the router
$router = new Router();
$router->middleware(new SecurityHeadersMiddleware());

// Define routes
// Public routes
$router->map('GET', '/', [HomeController::class, 'index']);
$router->map('GET', '/page/{page:number}', [HomeController::class, 'index']);
$router->map('GET', '/tag/{tag}', [HomeController::class, 'index']);
$router->map('GET', '/tag/{tag}/page/{page:number}', [HomeController::class, 'index']);
$router->map('GET', '/category/{category}', [HomeController::class, 'index']);
$router->map('GET', '/category/{category}/page/{page:number}', [HomeController::class, 'index']);
$router->map('GET', '/feeds', [FeedController::class, 'index']);
$router->map('GET', '/feeds/{id:number}', [FeedController::class, 'show']);
$router->map('GET', '/feeds/{id:number}/page/{page:number}', [FeedController::class, 'show']);
$router->map('GET', '/feeds/page/{page:number}', [FeedController::class, 'index']);
$router->map('GET', '/categories', [HomeController::class, 'categories']);
$router->map('GET', '/tags', [HomeController::class, 'tags']);
$router->map('GET', '/feed-builder', [FeedController::class, 'feedBuilder']);
$router->map('GET', '/feed', [FeedController::class, 'rss']);
$router->map('GET', '/feed/json', [FeedController::class, 'json']);
$router->map('GET', '/feed/rss', [FeedController::class, 'rss']);
$router->map('GET', '/suggest-feed', [SuggestionController::class, 'suggestForm']);
$router->map('POST', '/suggest-feed', [SuggestionController::class, 'submitSuggestion']);
$router->map('GET', '/random', [HomeController::class, 'random']);
$router->map('GET', '/shuffle', [HomeController::class, 'shuffle']);
$router->map('GET', '/captcha', [SuggestionController::class, 'getCaptcha']);
$router->map('GET', '/admin/login', [AdminController::class, 'loginForm']);
$router->map('POST', '/admin/login', [AdminController::class, 'login']);

// Admin routes (protected by middleware)
$router->group('/admin', function ($router) {
    $router->map('GET', '/', [AdminController::class, 'index']);
    
    // Feeds management
    $router->map('GET', '/feeds', [AdminController::class, 'feeds']);
    $router->map('GET', '/feeds/new', [AdminController::class, 'newFeedForm']);
    $router->map('POST', '/feeds/new', [AdminController::class, 'newFeedForm']);
    $router->map('GET', '/feeds/{id:number}/edit', [AdminController::class, 'editFeedForm']);
    $router->map('POST', '/feeds/{id:number}/edit', [AdminController::class, 'editFeedForm']);
    $router->map('POST', '/feeds', [AdminController::class, 'createFeed']);
    $router->map('PUT', '/feeds/{id:number}', [AdminController::class, 'updateFeed']);
    $router->map('DELETE', '/feeds/{id:number}', [AdminController::class, 'deleteFeed']);
    $router->map('POST', '/feeds/bulk/categories', [AdminController::class, 'bulkUpdateFeedCategories']);
    $router->map('POST', '/feeds/bulk/tags', [AdminController::class, 'bulkUpdateFeedTags']);
    $router->map('POST', '/feeds/bulk/status', [AdminController::class, 'bulkUpdateFeedStatus']);
    $router->map('POST', '/feeds/{id:number}/shuffle', [AdminController::class, 'toggleShuffle']);
    $router->map('PUT', '/items/{id:number}', [AdminController::class, 'updateItem']);
    
    // Categories management
    $router->map('GET', '/categories', [AdminController::class, 'categories']);
    $router->map('GET', '/categories/new', [AdminController::class, 'newCategoryForm']);
    $router->map('POST', '/categories/new', [AdminController::class, 'newCategoryForm']);
    $router->map('GET', '/categories/{id:number}/edit', [AdminController::class, 'editCategoryForm']);
    $router->map('POST', '/categories/{id:number}/edit', [AdminController::class, 'editCategoryForm']);
    $router->map('POST', '/categories', [AdminController::class, 'createCategory']);
    $router->map('PUT', '/categories/{id:number}', [AdminController::class, 'updateCategory']);
    $router->map('DELETE', '/categories/{id:number}', [AdminController::class, 'deleteCategory']);
    
    // Tags management
    $router->map('GET', '/tags', [AdminController::class, 'tags']);
    $router->map('GET', '/tags/new', [AdminController::class, 'newTagForm']);
    $router->map('POST', '/tags/new', [AdminController::class, 'newTagForm']);
    $router->map('GET', '/tags/{id:number}/edit', [AdminController::class, 'editTagForm']);
    $router->map('POST', '/tags/{id:number}/edit', [AdminController::class, 'editTagForm']);
    $router->map('POST', '/tags', [AdminController::class, 'createTag']);
    $router->map('PUT', '/tags/{id:number}', [AdminController::class, 'updateTag']);
    $router->map('DELETE', '/tags/{id:number}', [AdminController::class, 'deleteTag']);
    
    $router->map('GET', '/logout', [AdminController::class, 'logout']);
})->middleware(new AuthMiddleware());

// Process the request
$request = ServerRequestFactory::fromGlobals();
$response = $router->dispatch($request);

// Emit the response
(new SapiEmitter())->emit($response);