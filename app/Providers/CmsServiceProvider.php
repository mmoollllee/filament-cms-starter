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

        // Notice banners ("Hinweise": company holidays, public holidays). The package's page
        // template shows them on the homepage (the onepager's start section). An app
        // with its own templates places <x-cms::notices /> and names them in `on:`.
        Cms::enableNotices();

        // Optional wiring — uncomment to customize:
        // Cms::registerBlocks([...Cms::defaultBlocks(), ContactFormBlock::class, \Mmoollllee\Cms\Support\Content\Blocks\fragment\FragmentBlock::class]); // + fragment embed
        // Cms::allowSectionChildren('my-site-key', ['text', 'media', 'listing']);
        // Cms::allowRootBlocks('my-site-key', ['section', 'hero']);
        // Cms::templateEmbeds('content.home', fragments: ['kontaktbox'], contentTypes: ['news.article']); // "Außerdem auf dieser Seite"
        // Cms::useFooterTagline('Mein Claim.');
        // Cms::enableContentPageHeader();
        // Cms::useMenuLocations(['header' => 'Hauptmenü', 'footer' => 'Sekundär-Navigation']);

        // Media library ("Mediathek"): wired automatically because
        // ralphjsmit/laravel-filament-media-library is installed — the picker UX
        // comes from mmoollllee/filament-media-library-extensions. Knobs:
        // Cms::disableMediaLibrary();               // classic uploads instead
        // Cms::useMediaDisk('media-library');       // e.g. a private, policy-served disk
        // Cms::useMediaDriver(MyDriver::class);     // scope, conversions, accepted types
        // Cms::useMediaFolderNames([MediaFolders::BRANDING => 'Marke']);

        // Umami analytics (mmoollllee/filami) needs no registration here: the
        // engine auto-provisions one website per tenant, renders the tracking
        // snippet in the site shell and adds the "Statistiken" panel page as
        // soon as the package is installed. Configure it in .env / per tenant
        // under Seiten-Einstellungen → Statistik.
    }
}
