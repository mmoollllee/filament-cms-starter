<?php

use Illuminate\Support\Facades\Route;
use Mmoollllee\Cms\Http\Controllers\Frontend\ContentFragmentController;
use Mmoollllee\Cms\Http\Controllers\Frontend\ContentShowController;
use Mmoollllee\Cms\Http\Controllers\Frontend\RobotsController;
use Mmoollllee\Cms\Http\Controllers\Frontend\SitemapController;

// Tenant scoping, redirects, visibility gate and the branded 404 renderer are
// wired globally on the web group in bootstrap/app.php.
// App routes go here — ABOVE the CMS catch-all.

Route::get('/robots.txt', RobotsController::class)->name('robots');
Route::get('/sitemap.xml', SitemapController::class)->name('sitemap');
Route::get('/_content', ContentFragmentController::class)->name('content.fragment');

Route::get('/{path?}', ContentShowController::class)
    ->where('path', '.*')
    ->name('content.show');
