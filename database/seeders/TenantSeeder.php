<?php

namespace Database\Seeders;

use App\Models\Content;
use App\Models\Tenant;
use App\Models\User;
use Datlechin\FilamentMenuBuilder\Models\MenuItem;
use Datlechin\FilamentMenuBuilder\Models\MenuLocation;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Mmoollllee\Cms\Enums\TenantVisibility;
use Mmoollllee\Cms\Models\LayoutPreset;
use Mmoollllee\Cms\Models\Menu;

/**
 * Seeds a working demo site: one tenant on the APP_URL domain, a superadmin
 * (credentials via CMS_DEV_LOGIN_* env vars), a handful of pages built from the
 * package blocks, header/footer menus and a starter set of layout presets.
 * Idempotent — safe to re-run.
 */
class TenantSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $appUrl = parse_url(config('app.url'));
        $baseDomain = $appUrl['host'];

        $superadmin = User::query()->firstOrCreate(
            ['email' => config('cms.dev_login.email')],
            [
                'name' => 'Superadmin',
                'password' => config('cms.dev_login.password'),
            ],
        );

        $superadmin->forceFill([
            'name' => filled($superadmin->name) ? $superadmin->name : 'Superadmin',
            'is_superadmin' => true,
        ])->save();

        $tenant = Tenant::query()->updateOrCreate(
            ['site_key' => 'default'],
            [
                'name' => 'Beispiel GmbH',
                'primary_domain' => $baseDomain,
                'visibility' => TenantVisibility::Public,

                'brand_name' => 'Beispiel GmbH',
                'brand_claim' => 'Der Claim der Beispiel GmbH.',
                'logo_path' => $this->storeDefaultTenantLogo(),
                'primary_color' => Tenant::DEFAULT_PRIMARY_COLOR,
                'created_by' => $superadmin->getKey(),

                'company_name' => 'Beispiel GmbH',
                'contact_email' => 'info@example.com',
                'contact_phone' => '+49 123 456789',
                'street' => 'Musterstraße 1',
                'postal_code' => '12345',
                'city' => 'Musterstadt',
                'country' => 'Deutschland',
            ],
        );

        $presets = $this->seedLayoutPresets($tenant);

        $this->seedPages($tenant, $superadmin, $presets);

        $this->seedMenus($tenant, [
            'header' => ['/', '/ueber-uns', '/kontakt'],
            'footer' => ['/impressum', '/datenschutz'],
        ]);
    }

    protected function storeDefaultTenantLogo(): string
    {
        $logoPath = 'branding/defaults/logo.svg';

        Storage::disk('public')->put($logoPath, File::get(resource_path('images/logo.svg')));

        return $logoPath;
    }

    /**
     * Starter layout presets on the three engine levels (section, section-child).
     * Extend per project — presets are plain Tailwind class combinations.
     *
     * @return array<string, LayoutPreset>
     */
    protected function seedLayoutPresets(Tenant $tenant): array
    {
        $definitions = [
            ['Seitenbreite', ['section'], 'shell'],
            ['Zwei Spalten', ['section'], 'gap-6 lg:grid-cols-2'],
            ['Drei Spalten', ['section'], 'gap-4 md:grid-cols-2 xl:grid-cols-3'],
            ['Panel Solid', ['section-child'], 'card p-6 md:p-7 rounded-panel h-full'],
            ['Panel Accent', ['section-child'], 'card card-accent p-6 md:p-7 rounded-panel h-full'],
            ['Volle Breite', ['section-child'], 'col-span-full'],
        ];

        $presets = [];

        foreach ($definitions as [$title, $scope, $classes]) {
            $presets[$title] = LayoutPreset::query()->updateOrCreate(
                ['tenant_id' => $tenant->getKey(), 'title' => $title],
                ['scope' => $scope, 'classes' => $classes],
            );
        }

        return $presets;
    }

    /**
     * @param  array<string, LayoutPreset>  $presets
     */
    protected function seedPages(Tenant $tenant, User $user, array $presets): void
    {
        $shellIds = [$presets['Seitenbreite']->getKey()];

        $textBlock = fn (string $title, string $content, string $heading = 'h2', array $presetIds = []): array => [
            'type' => 'text',
            'data' => [
                'title' => $title,
                'heading' => $heading,
                'content' => $content,
                'layout_preset_ids' => $presetIds,
            ],
        ];

        $section = fn (array $blocks, array $presetIds = []): array => [
            'type' => 'section',
            'data' => [
                'blocks' => $blocks,
                'layout_preset_ids' => $presetIds !== [] ? $presetIds : $shellIds,
            ],
        ];

        $pages = [
            [
                'title' => 'Start',
                'slug' => 'start',
                'path' => '/',
                'content_type' => 'default.section',
                'sort' => 10,
                'blocks' => [
                    $section([
                        $textBlock(
                            'Willkommen im Filament-CMS-Starter',
                            '<p>Diese Seite kommt aus dem <strong>TenantSeeder</strong>. Melden Sie sich unter <a href="/panel">/panel</a> an und bauen Sie los — Inhalte, Blöcke und Layout-Presets sind einsatzbereit.</p>',
                            'h1',
                        ),
                    ]),
                ],
            ],
            [
                'title' => 'Über uns',
                'slug' => 'ueber-uns',
                'path' => '/ueber-uns',
                'content_type' => 'default.section',
                'sort' => 20,
                'blocks' => [
                    $section([
                        $textBlock('Über uns', '<p>Platzhalter — hier entsteht die Über-uns-Seite.</p>'),
                    ]),
                ],
            ],
            [
                'title' => 'Kontakt',
                'slug' => 'kontakt',
                'path' => '/kontakt',
                'content_type' => 'default.section',
                'sort' => 30,
                'blocks' => [
                    $section([
                        [
                            'type' => 'contact_form',
                            'data' => [
                                'title' => 'Schreiben Sie uns',
                                'eyebrow' => 'Kontakt',
                                'content' => null,
                                'contact_email' => null,
                            ],
                        ],
                    ]),
                ],
            ],
            [
                'title' => 'Impressum',
                'slug' => 'impressum',
                'path' => '/impressum',
                'content_type' => 'default.page',
                'sort' => 40,
                'blocks' => [
                    $section([
                        $textBlock('Impressum', '<p>Platzhalter — Angaben gemäß § 5 DDG hier einfügen.</p>'),
                    ]),
                ],
            ],
            [
                'title' => 'Datenschutz',
                'slug' => 'datenschutz',
                'path' => '/datenschutz',
                'content_type' => 'default.page',
                'sort' => 50,
                'blocks' => [
                    $section([
                        $textBlock('Datenschutzerklärung', '<p>Platzhalter — Datenschutzerklärung hier einfügen.</p>'),
                    ]),
                ],
            ],
        ];

        foreach ($pages as $page) {
            Content::query()->updateOrCreate(
                [
                    'tenant_id' => $tenant->getKey(),
                    'path' => $page['path'],
                ],
                [
                    'content_type' => $page['content_type'],
                    'title' => $page['title'],
                    'slug' => $page['slug'],
                    'visibility' => 'public',
                    'publish_from' => now()->subDay(),
                    'sort' => $page['sort'],
                    'blocks' => $page['blocks'],
                    'created_by' => $user->getKey(),
                    'updated_by' => $user->getKey(),
                ],
            );
        }
    }

    /**
     * @param  array<string, list<string>>  $locations  location → ordered content paths
     */
    protected function seedMenus(Tenant $tenant, array $locations): void
    {
        $contentByPath = Content::query()
            ->where('tenant_id', $tenant->getKey())
            ->whereNotNull('path')
            ->get()
            ->keyBy('path');

        foreach ($locations as $location => $paths) {
            $menuName = match ($location) {
                'header' => 'Hauptmenü',
                'footer' => 'Sekundär-Navigation',
                default => $location,
            };

            $menu = Menu::query()->updateOrCreate(
                ['tenant_id' => $tenant->getKey(), 'name' => $menuName],
                ['is_visible' => true],
            );

            MenuLocation::query()->updateOrCreate(
                ['tenant_id' => $tenant->getKey(), 'location' => $location],
                ['menu_id' => $menu->getKey()],
            );

            $menu->menuItems()->delete();

            foreach (array_values($paths) as $order => $path) {
                $content = $contentByPath->get($path);

                if ($content === null) {
                    continue;
                }

                MenuItem::query()->create([
                    'menu_id' => $menu->getKey(),
                    'title' => $content->title,
                    'linkable_type' => $content->getMorphClass(),
                    'linkable_id' => $content->getKey(),
                    'order' => $order,
                ]);
            }
        }
    }
}
