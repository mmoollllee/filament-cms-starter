# filament-cms-starter

Starter für Multi-Tenant-Marketing-Websites auf Basis von
[`mmoollllee/filament-cms`](https://github.com/mmoollllee/filament-cms)
(Laravel 13 + Filament v5) — destilliert aus den Gemeinsamkeiten von
muench-tiefbau.de und pernes-hebesysteme.de.

Die Engine (Tenancy, Content-Types/Blueprints, Block-Builder, Layout-Presets,
Entwurf/Vorschau, Versionierung, Mediathek, RichEditor-Stack, Redirects/404,
Sitemap/Robots, Admin-Panel) kommt aus dem Package. Dieses Repo liefert das
projektseitige Gerüst drumherum:

- **Models & Provider** — `Content`/`Tenant`/`Fragment`/`User` verdrahtet
  (inkl. `HasDraft` + `HasVersions`), `CmsServiceProvider` (Engine-Wiring),
  `PanelProvider`, `AppServiceProvider` (Tables, HTML-Sanitizer,
  `blocks::`-View-Namespace, Tenant-View-Composer)
- **Beispiel-Block** `contact_form` unter `app/Support/Content/Blocks/` mit
  Livewire-`KontaktForm` (Honeypot + Spam-Quiz + Rate-Limit + `#[Locked]`-Härtung)
  und gebrandeten Mails (`KontaktAnfrage` an den Tenant, `KontaktBestaetigung`
  an den Absender)
- **Optionale Integrationen bereits verdrahtet** — Mediathek (Media Library Pro
  + Picker-Extensions), Umami-Analytics (`filami`) inkl. Formular-Funnel,
  Consent-Banner mit passenden Kategorien
- **Frontend-Gerüst** — `resources/css/site/*` (Design-Tokens, Base, Buttons,
  Cards, Content, Forms, Navigation, Scroll-Animate, Typography), Filament-Theme,
  JS-Features (Onepager, Child-Navigation, Scroll-Animate/Zoom, Livewire-Boot,
  Consent-Runtime), Icon-Set, Fehlerseiten, Mail-Layout
- **Demo-Seeder** — Tenant auf der `APP_URL`-Domain, Superadmin, Seiten
  (Start, Über uns, Kontakt, Impressum, Datenschutz), Menüs, Layout-Presets
- **Tooling** — Pest-Tests (Smoke, Kontaktformular, Wiring-Repros),
  DB-Snapshots, Boost-Konfiguration, und `mmoollllee/laravel-deployer` — das
  bringt neben den Deployer-Rezepten auch die beiden Diagnose-Commands
  `app:send-test-mail` und `app:localize-tenant-domains` mit

## Neues Projekt starten

```bash
# 1. Klonen & entkoppeln
git clone git@github.com:mmoollllee/filament-cms-starter.git mein-projekt.de
cd mein-projekt.de && rm -rf .git && git init

# 2. auth.json anlegen — Credentials für packages.filamentphp.com (richer-editor)
#    UND satis.ralphjsmit.com (Media Library Pro). Liegt NICHT im Repo:
cp ../pernes-hebesysteme.de/auth.json .

# 3. Setup (composer install, .env, key, migrate, storage:link, seed, npm build)
composer run setup
```

Danach:

1. `.env` anpassen: `APP_NAME`, `APP_URL` (= Tenant-Domain), `DB_DATABASE`,
   `CMS_DEV_LOGIN_*`
2. `/panel` auf der Tenant-Domain öffnen (Zugang: `CMS_DEV_LOGIN_EMAIL` / `…_PASSWORD`)
3. Branding, Kontaktdaten & Impressum/Datenschutz im Panel unter
   „Site Settings" pflegen
4. `php artisan boost:install` für die Agent-Guidelines (CLAUDE.md/AGENTS.md)

Kein Media-Library-Pro-Lizenz zur Hand? `composer remove
ralphjsmit/laravel-filament-media-library spatie/laravel-medialibrary
mmoollllee/filament-media-library-extensions` — alle Medienfelder fallen dann
auf tenant-gescopte `FileUpload`s zurück, ohne eine Zeile Code-Änderung.

## Projekt anpassen

| Was | Wo |
|-----|-----|
| Eigene Content-Types (Blueprints + Resources) | `app/Sites/<Site>/…` — Auto-Discovery via `site_key` |
| Eigene Builder-Blöcke | `app/Support/Content/Blocks/<key>/` + Registrierung im `CmsServiceProvider` |
| Design (Farben, Radii, Abstände) | `resources/css/site/_design-tokens.css` |
| Favicons | `resources/images/icon.svg` ersetzen, daraus `public/favicon.ico` + `public/apple-touch-icon{,-precomposed}.png` neu erzeugen (Browser fragen die im Document-Root an, unabhängig von `<link>`-Tags) |
| Templates je Content-Type | `resources/views/{site_key}/content/…` (siehe Template-Auflösung in `docs/cms-architecture.md`) |
| Panel-Optionen | `app/Providers/Filament/PanelProvider.php` |
| Consent-Kategorien | `config/consent-control.php` (bei jeder Änderung `version` hochzählen) |
| Deployment | `deploy.php` (Host/Pfad anpassen), `.env.prod` als Vorlage |

Feature-Referenz & Erweiterungspunkte: `docs/FEATURES.md` und
`docs/CUSTOMIZATION.md` im [filament-cms-Package](https://github.com/mmoollllee/filament-cms),
Architektur-Überblick in [`docs/cms-architecture.md`](docs/cms-architecture.md).

## Analytics (Umami)

Ohne `UMAMI_URL` bleibt die Integration komplett inert — das ist der
Default für lokale Entwicklung. Für Produktion in `.env`:

```dotenv
UMAMI_URL=https://a.example.com
UMAMI_USERNAME=provisioner
UMAMI_PASSWORD=…
UMAMI_RECORDER_CONSENT_CATEGORY=analytics
```

Jeder Tenant bekommt dann automatisch eine eigene Umami-Website; die Panel-Seite
„Statistiken" zeigt Besucher, Seitenaufrufe, Top-Seiten und Events. Ein Kunde mit
eigener Umami-Instanz trägt Endpunkt und Website-ID stattdessen im Panel unter
*Seiten-Einstellungen → Statistik* ein — die Credentials oben brauchen nur
Auto-Provisioning und die Widgets.

Bestandsinstallation nachziehen: `php artisan filami:sync`.

Session-Replay/Heatmaps hängen an der Consent-Kategorie `analytics` in
`config/consent-control.php`; die reine Reichweitenmessung läuft ungated und ist
dort nur ausgewiesen.

## Deployment

`deploy.php` liegt auf [`mmoollllee/laravel-deployer`](https://github.com/mmoollllee/laravel-deployer)
auf (In-Place-`git pull`-Deploy für Plesk/Managed Hosting) und enthält nur noch
Host + Projektkonfiguration. Das global installierte `dep` muss Deployer 8 sein:

```bash
composer global require deployer/deployer:^8.0
```

```bash
dep setup            # einmalig: frischen Remote non-destruktiv bootstrappen
dep deploy           # pull, composer, assets, migrate, optimize
dep deploy:quick     # nur Code (Blade/Config) — git pull + optimize
dep pull:db-refresh  # Inhalte vom Server in die lokale DB (danach *.test-Domains)
dep pull:files       # storage/app/public spiegeln
dep shell            # SSH im Webroot, mit Deploy-Key und gepinntem PHP
dep list             # alles Weitere (git:*, composer:*, logs*, artisan:*)
```

Das Package liefert zwei Artisan-Befehle mit: `app:localize-tenant-domains`
schreibt nach jedem DB-Pull die Tenant-Domains auf `*.test` um (hängt in
`deploy.php` an `pull:db-*` und weigert sich außerhalb von local/testing), und
`app:send-test-mail` schickt eine Testmail und gibt den Transport-Fehler im
Klartext aus — gedacht für den Server: `dep shell`, dann `art app:send-test-mail`.
Deshalb steht `mmoollllee/laravel-deployer` in `require`, nicht in `require-dev`.

`pull:db-full` leaves a production dump in `database/dumps/`, which is deliberately
not gitignored at the root (it stays visible in `git status` until deleted). The
pre-commit hook in `.githooks/` refuses to commit it; `core.hooksPath` is per-clone
config, so run `php artisan app:install-git-hooks` once after cloning (`composer
setup` does it for you).

Die Mediathek-Dateien liegen unter `storage/app/public` und kommen über
`dep pull:files`; die zugehörigen DB-Tabellen sind in `db_pull_include`
auskommentiert — beides zusammen einkommentieren, sonst zeigen lokale
Media-Items auf Dateien, die es nicht gibt.

## Entwicklung

```bash
composer run dev    # Server + Queue + Logs + Vite
php artisan test --compact
vendor/bin/pint --dirty
```

Nach jedem Update der Packages (besonders `filament-media-library-extensions`,
dessen Asset-URLs content-gehasht sind):

```bash
php artisan filament:assets && npm run build
```

Unter [Laravel Herd](https://herd.laravel.com) ist das Projekt automatisch als
`http://<ordnername>.test` erreichbar — `APP_URL` entsprechend setzen, der
Seeder legt den Tenant auf dieser Domain an.
