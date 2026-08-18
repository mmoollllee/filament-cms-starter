<?php

namespace App\Providers\Filament;

use Filament\Panel;
use Mmoollllee\Cms\Filament\Providers\BasePanelProvider;

/**
 * Resources (catch-all content, fragments, core, site extensions), pages and
 * the RichEditor stack are the package defaults — override hooks only to add.
 * The media library ("Mediathek") and the Umami statistics page register
 * themselves whenever their optional packages are installed.
 *
 * Panel options are set fluently, Filament-style.
 */
class PanelProvider extends BasePanelProvider
{
    protected function configurePanel(Panel $panel): Panel
    {
        return $panel
            // resources/css/filament/theme.css — the panel theme built by Vite.
            // It carries the media-library and RichEditor stylesheets, so the
            // panel is unstyled in places without it.
            ->viteTheme('resources/css/filament/theme.css');
    }
}
