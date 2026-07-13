<?php

namespace App\Providers;

use Filament\Tables\Table;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Mmoollllee\Cms\Support\Tenancy\CurrentTenant;
use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;

class AppServiceProvider extends ServiceProvider
{
    // Engine wiring (LoginResponse binding, LayoutPresetResolver + BuilderBlockRegistry
    // singletons, HtmlPreservePlugin + merge tags on the RichEditor) comes from the
    // filament-cms package — the app-side registration (models, blocks, page header,
    // tagline) lives in App\Providers\CmsServiceProvider.

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureTable();
        $this->allowHTMLinRichEditor();
        $this->loadViewsFrom(app_path('Support/Content/Blocks'), 'blocks');
        Blade::anonymousComponentPath(app_path('Support/Content/Blocks'), 'block');

        View::composer('*', function (\Illuminate\View\View $view) {
            if (! $view->offsetExists('tenant')) {
                $view->with('tenant', app(CurrentTenant::class)->get());
            }
        });
    }

    private function configureTable(): void
    {
        Table::configureUsing(function (Table $table): void {
            $table->striped()
                ->deferLoading();
        });
    }

    private function allowHTMLinRichEditor(): void
    {
        $this->app->singleton(HtmlSanitizerInterface::class, function () {
            $config = (new HtmlSanitizerConfig)
                ->allowSafeElements()
                ->allowRelativeLinks(true)
                ->allowLinkSchemes(['http', 'https', 'mailto', 'tel'])
                ->allowElement('a', ['href', 'rel', 'target', 'title', 'class', 'wire:navigate'])
                ->allowElement('div', '*')
                ->allowElement('span', '*')
                ->allowElement('iframe', '*')

                // Allow inline SVG icons rendered by Blade Icons
                ->allowElement('svg', ['xmlns', 'fill', 'viewbox', 'stroke-width', 'stroke', 'class', 'aria-hidden', 'role', 'focusable', 'height', 'width'])
                ->allowElement('g', ['fill', 'stroke', 'stroke-width', 'class'])
                ->allowElement('path', ['stroke-linecap', 'stroke-linejoin', 'd', 'fill', 'stroke'])
                ->allowElement('circle', ['cx', 'cy', 'r', 'fill', 'stroke', 'stroke-width'])
                ->allowElement('rect', ['x', 'y', 'width', 'height', 'rx', 'ry', 'fill', 'stroke', 'stroke-width'])
                ->allowElement('line', ['x1', 'y1', 'x2', 'y2', 'stroke', 'stroke-width'])
                ->allowElement('polyline', ['points', 'fill', 'stroke', 'stroke-width', 'stroke-linecap', 'stroke-linejoin'])
                ->allowElement('polygon', ['points', 'fill', 'stroke', 'stroke-width', 'stroke-linecap', 'stroke-linejoin']);

            return new HtmlSanitizer($config);
        });
    }
}
