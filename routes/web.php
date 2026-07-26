<?php

use App\Http\Controllers\FeedController;
use App\Http\Controllers\HomeController;
use Illuminate\Support\Facades\Route;

// Public routes only. Everything under /admin is served by the Filament panel
// registered in App\Providers\Filament\AdminPanelProvider.
Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/page/{page}', [HomeController::class, 'index'])->whereNumber('page');
Route::get('/tag/{tag}', [HomeController::class, 'index'])->where('tag', '[a-zA-Z0-9\-_]+');
Route::get('/tag/{tag}/page/{page}', [HomeController::class, 'index'])->where('tag', '[a-zA-Z0-9\-_]+')->whereNumber('page');
Route::get('/category/{category}', [HomeController::class, 'index'])->where('category', '[a-zA-Z0-9\-_]+');
Route::get('/category/{category}/page/{page}', [HomeController::class, 'index'])->where('category', '[a-zA-Z0-9\-_]+')->whereNumber('page');

Route::get('/feeds', [FeedController::class, 'index'])->name('feeds');
Route::get('/feeds/page/{page}', [FeedController::class, 'index'])->whereNumber('page');
Route::get('/feeds/{slug}', [FeedController::class, 'show'])->name('feeds.show');
Route::get('/feeds/{slug}/page/{page}', [FeedController::class, 'show'])->whereNumber('page');

Route::get('/categories', [HomeController::class, 'categories'])->name('categories');
Route::get('/tags', [HomeController::class, 'tags'])->name('tags');

Route::get('/feed-builder', [FeedController::class, 'feedBuilder'])->name('feed-builder');
Route::get('/feed', [FeedController::class, 'rss']);
Route::get('/feed/rss', [FeedController::class, 'rss'])->name('feed.rss');
Route::get('/feed/json', [FeedController::class, 'json'])->name('feed.json');

Route::get('/random', [HomeController::class, 'random'])->name('random');
Route::get('/shuffle', [HomeController::class, 'shuffle'])->name('shuffle');

Route::view('/suggest-feed', 'suggest-feed')->name('suggest-feed');
