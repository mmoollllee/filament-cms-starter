<?php

namespace App\Providers;

use App\Models\Content;
use App\Models\Fragment;
use App\Models\Tenant;
use App\Support\Content\Blocks\contact_form\ContactFormBlock;
use Illuminate\Support\ServiceProvider;
use Mmoollllee\Cms\Cms;

/**
 * Central CMS engine wiring. Everything structural is registered in code
 * (Cashier/Sanctum-style statics) — only environment-driven settings live in
 * config/cms.php. Panel options belong in App\Providers\Filament\PanelProvider.
 */
class CmsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        Cms::useContentModel(Content::class);
        Cms::useTenantModel(Tenant::class);
        Cms::useFragmentModel(Fragment::class);

        // Builder blocks offered in the panel pickers (order = picker order):
        // the package core set plus the app-level example block. Add your own
        // blocks under app/Support/Content/Blocks/{key}/ and register them here.
        Cms::registerBlocks([
            ...Cms::defaultBlocks(),
            ContactFormBlock::class,
        ]);

        // Optional wiring — uncomment to customize:
        // Cms::allowSectionChildren('my-site-key', ['text', 'media', 'listing']);
        // Cms::allowRootBlocks('my-site-key', ['section', 'hero']);
        // Cms::useFooterTagline('Mein Claim.');
        // Cms::enableContentPageHeader();
        // Cms::useMenuLocations(['header' => 'Hauptmenü', 'footer' => 'Sekundär-Navigation']);
    }
}
