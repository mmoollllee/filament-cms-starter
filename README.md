# filament-cms-starter

Starter für Multi-Tenant-Marketing-Websites auf Basis von
[`mmoollllee/filament-cms`](https://github.com/mmoollllee/filament-cms)
(Laravel 13 + Filament v5) — destilliert aus den Gemeinsamkeiten von
muench-tiefbau.de und pernes-hebesysteme.de.

Die Engine (Tenancy, Content-Types/Blueprints, Block-Builder, Layout-Presets,
RichEditor-Stack, Redirects/404, Sitemap/Robots, Admin-Panel) kommt aus dem
Package. Dieses Repo liefert das projektseitige Gerüst drumherum:

- **Models & Provider** — `Content`/`Tenant`/`Fragment`/`User` verdrahtet,
  `CmsServiceProvider` (Engine-Wiring), `PanelProvider`, `AppServiceProvider`
  (Tables, HTML-Sanitizer, `blocks::`-View-Namespace, Tenant-View-Composer)
- **Beispiel-Block** `contact_form` unter `app/Support/Content/Blocks/` mit
  Livewire-`KontaktForm` (Honeypot + Spam-Quiz + Rate-Limit) und gebrandeten
  Mails (`KontaktAnfrage` an den Tenant, `KontaktBestaetigung` an den Absender)
- **Frontend-Gerüst** — `resources/css/site/*` (Design-Tokens, Base, Buttons,
  Cards, Content, Forms, Navigation, Scroll-Animate, Typography), Filament-Theme,
  JS-Features (Onepager, Child-Navigation, Scroll-Animate/Zoom, Livewire-Boot,
  Consent-Runtime), Icon-Set, Fehlerseiten, Mail-Layout
- **Demo-Seeder** — Tenant auf der `APP_URL`-Domain, Superadmin, Seiten
  (Start, Über uns, Kontakt, Impressum, Datenschutz), Menüs, Layout-Presets
- **Tooling** — Pest-Tests (Smoke + Kontaktformular), Deployer-Template,
  Consent-Control, DB-Snapshots, Boost-Konfiguration

## Neues Projekt starten

```bash
# 1. Klonen & entkoppeln
git clone git@github.com:mmoollllee/filament-cms-starter.git mein-projekt.de
cd mein-projekt.de && rm -rf .git && git init

# 2. auth.json anlegen (filamentphp.com-Credentials für awcodes/richer-editor,
#    liegt NICHT im Repo — von einem bestehenden Projekt kopieren)
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

## Projekt anpassen

| Was | Wo |
|-----|-----|
| Eigene Content-Types (Blueprints + Resources) | `app/Sites/<Site>/…` — Auto-Discovery via `site_key` |
| Eigene Builder-Blöcke | `app/Support/Content/Blocks/<key>/` + Registrierung im `CmsServiceProvider` |
| Design (Farben, Radii, Abstände) | `resources/css/site/_design-tokens.css` |
| Templates je Content-Type | `resources/views/{site_key}/content/…` (siehe Template-Auflösung in `docs/cms-architecture.md`) |
| Panel-Optionen | `app/Providers/Filament/PanelProvider.php` |
| Deployment | `deploy.php` (Host/Pfad anpassen), `.env.prod` als Vorlage |

Feature-Referenz & Erweiterungspunkte: `docs/FEATURES.md` und
`docs/CUSTOMIZATION.md` im [filament-cms-Package](https://github.com/mmoollllee/filament-cms),
Architektur-Überblick in [`docs/cms-architecture.md`](docs/cms-architecture.md).

## Entwicklung

```bash
composer run dev    # Server + Queue + Logs + Vite
php artisan test --compact
vendor/bin/pint --dirty
```

Unter [Laravel Herd](https://herd.laravel.com) ist das Projekt automatisch als
`http://<ordnername>.test` erreichbar — `APP_URL` entsprechend setzen, der
Seeder legt den Tenant auf dieser Domain an.
