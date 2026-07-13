<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Mmoollllee\Cms\Exceptions\NotFoundRenderer;
use Mmoollllee\Cms\Http\Middleware\CanonicalizeTrailingSlash;
use Mmoollllee\Cms\Http\Middleware\EnsureTenantIsVisible;
use Mmoollllee\Cms\Http\Middleware\ResolveActiveRedirects;
use Mmoollllee\Cms\Http\Middleware\ResolveTenantFromHost;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(
            prepend: [
                CanonicalizeTrailingSlash::class,
            ],
            append: [
                ResolveTenantFromHost::class,
                // Active-redirect resolution runs BEFORE the visibility gate + content lookup, so
                // a redirect can shadow live content (a single cached-map lookup, no DB on a hit).
                ResolveActiveRedirects::class,
                EnsureTenantIsVisible::class,
            ],
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        // Turn frontend 404s into the tenant-branded error page (+ 404 collection). Covers every
        // NotFoundHttpException source uniformly; the renderer falls through for panel/JSON/no-tenant.
        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            return app(NotFoundRenderer::class)->handle($request, $e);
        });
    })->create();
