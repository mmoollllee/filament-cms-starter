# CMS-Architektur – Dokumentation

## Inhaltsverzeichnis

- [Überblick](#überblick)
- [Kernkonzepte](#kernkonzepte)
  - [1. Tenants (Mandanten)](#1-tenants-mandanten)
  - [2. Content (Inhalte)](#2-content-inhalte)
  - [3. Blueprints (Content Types)](#3-blueprints-content-types)
  - [4. Site Extensions](#4-site-extensions)
  - [5. Block-System (Page Builder)](#5-block-system-page-builder)
  - [6. Layout Presets](#6-layout-presets)
  - [7. RichEditor](#7-richeditor)
- [Datenfluss](#datenfluss)
- [Filament Admin-Panel](#filament-admin-panel)
- [Datenbankschema](#datenbankschema)
- [Enums](#enums)
- [Konfiguration](#konfiguration)
- [Optionale Integrationen](#optionale-integrationen)
- [Wichtige Dateien](#wichtige-dateien)
- [Erweiterung](#erweiterung)

---

## Überblick

Ein **Multi-Tenant CMS** auf Basis von Laravel 13 und Filament v5. Inhalte werden über ein **Blueprint-System** (Content Types als Code) definiert und mit einem **Block-basierten Page Builder** aufgebaut. Jeder Tenant hat eine eigene Domain, eigenes Branding und eigene Inhalte.

Die Engine selbst liegt im Package [`mmoollllee/filament-cms`](https://github.com/mmoollllee/filament-cms) (Namespace `Mmoollllee\Cms\`). Dieses Repo liefert nur die projektseitigen Teile: Models, Provider, eigene Blöcke, Views und Design. Alle Klassenverweise ohne `app/`-Pfad unten zeigen deshalb ins Package unter `vendor/mmoollllee/filament-cms/src/`.

---

## Kernkonzepte

### 1. Tenants (Mandanten)

**Model:** [`app/Models/Tenant.php`](../app/Models/Tenant.php)

Jeder Tenant repräsentiert eine Website mit eigener Domain, eigenem Branding und eigenen Inhalten.

| Feld | Beschreibung |
|------|-------------|
| `site_key` | Eindeutiger Schlüssel, bestimmt welche [SiteExtension](#4-site-extensions) geladen wird (z.B. `marketing`) |
| `primary_domain` | Domain der Website (z.B. `beispiel-bau.de.test`) |
| `brand_name`, `brand_claim`, `logo_path`, `primary_color` | Branding-Einstellungen |
| `visibility` | `public`, `private` oder `archived` |

**Branding-Vererbung:** Hat ein Tenant keinen eigenen `brand_name`, erbt er vom Standard-Branding-Tenant. Welcher Tenant als Standard dient, wird über die [CMS-Konfiguration](#konfiguration) gesteuert (`CMS_BRANDING_TENANT_ID` in `.env` oder Fallback: Tenant mit niedrigster ID).

**Tenant-Auflösung:** Die Middleware `ResolveTenantFromHost` setzt den Tenant anhand der Request-Domain. Zugriff im Code über:

```php
$tenant = app(CurrentTenant::class)->get();
```

---

### 2. Content (Inhalte)

**Model:** [`app/Models/Content.php`](../app/Models/Content.php)

Zentrale Entität des CMS. Speichert sowohl strukturierte Daten als auch Block-Inhalte in JSON-Spalten.

| Feld | Beschreibung |
|------|-------------|
| `tenant_id` | Zugehöriger [Tenant](#1-tenants-mandanten) |
| `parent_id` | Optionaler Eltern-Content (Hierarchie) |
| `content_type` | Typ-Identifier, verweist auf einen [Blueprint](#3-blueprints-content-types) (z.B. `default.page`, `marketing.service`) |
| `title` / `slug` / `path` | Titel, URL-Slug und vollständiger URL-Pfad |
| `visibility` | `public` oder `members` – siehe [ContentVisibility](#enums) |
| `publish_from` / `publish_until` | Veröffentlichungs-Zeitfenster (steuert den [Status](#status-logik)) |
| `blocks` | JSON – [Block-Builder](#5-block-system-page-builder)-Daten (der eigentliche Seiteninhalt) |
| `payload` | JSON – Strukturierte Felder je nach Content Type. Wird vom Blueprint über `payloadFormComponents()` befüllt (z.B. Standort, Gehalt bei Jobs; Beschreibung bei Weiterbildungen) |
| `references` | JSON – IDs referenzierter Content-Einträge. Ermöglicht Verknüpfungen zwischen Inhalten (z.B. Job → Weiterbildungen). Zugriff über `$content->referencedContents()` |
| `meta` | JSON – SEO/OG-Metadaten |
| `layout_preset_ids` | JSON – IDs der zugewiesenen [Layout-Presets](#6-layout-presets) auf Content-Ebene |
| `draft` | JSON – noch nicht angewendete Panel-Änderungen (`HasDraft`), siehe unten |
| `sort` | Sortierreihenfolge innerhalb des Eltern-Contents |

#### Entwurf & Versionierung

Zwei unabhängige, opt-in Traits am Model — beide sind im Starter aktiv, sowohl
auf `Content` als auch auf `Fragment`:

- **`HasDraft`** — „Entwurf speichern" legt den kompletten Formularstand in der
  `draft`-Spalte ab, ohne den Live-Datensatz anzufassen. Im Vorschau-Modus
  (session-sticky, tenant-gescopt) überlagert der Entwurf den Live-Inhalt
  überall dort, wo er gerendert wird.
- **`HasVersions`** — pro *angewendeter* Änderung ein Snapshot in der
  `versions`-Tabelle, mit Diff-Ansicht, Restore und dem Dashboard-Widget
  „Letzte Änderungen". Entwürfe bleiben bewusst draußen: sie waren nie live und
  wären als Wiederherstellungspunkt irreführend.

Ohne die Traits versteckt die Engine jedes zugehörige UI-Element — ein
vergessenes `use` kostet also lautlos den Entwurf-Button und den Revisionen-Tab.
Eigene Site-Resources brauchen zusätzlich eine `RevisionsPage` (3 Zeilen auf
`Mmoollllee\Cms\Filament\Resources\Concerns\ContentRevisionsPage`) und den
Eintrag `'revisions' => RevisionsPage::route('/{record}/revisions')` in
`getPages()`.

#### Status-Logik

Der Content-Status wird dynamisch aus `publish_from` und `publish_until` berechnet (kein eigenes Datenbankfeld):

| Status | Bedingung |
|--------|-----------|
| `Draft` | Kein `publish_from` gesetzt |
| `Scheduled` | `publish_from` liegt in der Zukunft |
| `Published` | Zwischen `publish_from` und `publish_until` |
| `Expired` | `publish_until` liegt in der Vergangenheit |

#### Model-Hooks (automatisch bei Speichern)

1. **Tenant-Zuweisung:** Wird automatisch dem aktuellen Tenant zugewiesen
2. **Slug-Generierung:** Wird aus dem Titel erzeugt, wenn leer
3. **Pfad-Berechnung:** `PathGenerator::generate()` setzt die `path`-Spalte

---

### 3. Blueprints (Content Types)

**Interface:** `src/Contracts/ContentBlueprint.php`
**Implementierung:** `src/Sites/ConfiguredContentBlueprint.php`

Blueprints definieren Content Types als Code – sie bestimmen, welche Felder, Blöcke und Verhaltensweisen ein Inhaltstyp hat.

```php
new ConfiguredContentBlueprint(
    key: 'marketing.service',
    label: 'Service',
    defaultTemplate: 'content.service',
    isRoutable: false,
    allowedParentTypes: ['default.section'],
    navigationLabel: 'Services',
)
```

**Wichtige Blueprint-Methoden:**

| Methode | Beschreibung |
|---------|-------------|
| `key()` | Eindeutiger Identifier (z.B. `marketing.service`) |
| `label()` | Anzeigename im Admin |
| `isRoutable()` | Hat eine eigene URL? |
| `participatesInOnepager()` | Teil des Onepagers? |
| `hasBuilder()` | [Block-Builder](#5-block-system-page-builder) aktiviert? |
| `allowedBlocks()` | Erlaubte Block-Typen (null = alle) |
| `payloadFormComponents()` | Zusätzliche Formularfelder für `payload`-Daten |
| `defaultTemplate()` | Standard-Blade-Template (siehe [Template-Auflösung](#template-auflösung)) |
| `urlPathPrefix()` | URL-Präfix (z.B. `/services/`) |
| `supportsTeasers()` | Teaser-Modus unterstützt? |
| `allowedParentTypes()` | Erlaubte Eltern-Content-Types |

---

### 4. Site Extensions

**Interface:** `src/Contracts/SiteExtension.php`
**Registry:** `src/Sites/SiteExtensionRegistry.php`

Site Extensions bündeln [Blueprints](#3-blueprints-content-types) und Filament-Ressourcen pro Website/Tenant.

**Verzeichnisstruktur:**

```
app/Sites/
├── Marketing/
│   ├── SiteExtension.php          # Registriert alle Blueprints & Resources
│   ├── Service/
│   │   ├── Blueprint.php          # Content-Type-Definition
│   │   └── Resource.php           # Filament-Admin-Resource
│   └── Project/
│       ├── Blueprint.php
│       └── Resource.php
├── Jobs/
│   ├── SiteExtension.php
│   └── Job/
│       ├── Blueprint.php
│       └── Resource.php
```

**Auto-Discovery:** Blueprints und Resources werden über die Traits `DiscoversSiteBlueprints` und `DiscoversSiteResources` automatisch erkannt. Die `SiteExtensionRegistry` verwaltet alle Extensions zentral und ordnet sie über den `site_key` dem richtigen Tenant zu.

---

### 5. Block-System (Page Builder)

**Basis-Klasse:** `src/Support/Content/Blocks/BaseBuilderBlock.php`
**Interface:** `src/Support/Content/Blocks/Contracts/BuilderBlock.php`
**Registry:** `src/Support/Content/Blocks/BuilderBlockRegistry.php`

Jeder Block implementiert zwei Methoden:

```php
interface BuilderBlock {
    public function key(): string;           // z.B. 'card', 'hero'
    public function make(?Tenant $tenant): Block;  // Filament Block-Konfiguration
}
```

#### Verfügbare Blöcke

| Block | Label | Beschreibung |
|-------|-------|-------------|
| `section` | Sektion | **Container-Block** – enthält andere Blöcke (verschachtelter Builder) |
| `card` | Karte | Karte mit Titel, Text, optionalem Eyebrow |
| `text` | Text | [RichEditor](#7-richeditor)-Inhalt |
| `hero` | Hero | Großes Heading mit optionalem CTA |
| `media` | Medien | Bild oder Video mit optionalem Poster |
| `section_header` | Abschnitts-Header | Eigenständiger Section-Header (Titel + Rich Content) |
| `checklist` | Checkliste | Liste von Items in einer Karte |
| `contact_cta` | Kontakt CTA | Kontaktbereich mit optionalen Steps und Sidebar |
| `listing` | Listing | Rendert referenzierte Content-Einträge |
| `listing_children` | Listing Children | Rendert automatisch Kind-Inhalte |
| `job_listing` | Job Listing | Job-spezifisches Listing |
| `tag_showcase` | Tag Showcase | Anzeige von Tags/Labels |
| `image_overlay` | Image Overlay | Bild mit Overlay-Text |
| `startscreen_split` | Startscreen Split | Split-Layout Hero |
| `bewerbung` | Bewerbung | Bewerbungsformular |
| `standard` | Standard | Generischer Rich-Text-Block |

Jeder Block liegt als eigenes Verzeichnis unter [`app/Support/Content/Blocks/{key}/`](../app/Support/Content/Blocks/) mit Block-Klasse, View und optionaler Preview.

#### Block-Datenformat (JSON in `Content.blocks`)

```json
[
  {
    "type": "section",
    "data": {
      "title": "Unsere Leistungen",
      "heading": "h2",
      "layout_preset_ids": [3],
      "blocks": [
        {
          "type": "card",
          "data": {
            "title": "Erdarbeiten",
            "text": "Professionelle Erdarbeiten...",
            "eyebrow": "Leistung",
            "heading": "h3"
          }
        }
      ]
    }
  }
]
```

#### Gemeinsame Block-Felder (`commonBlockFields()`)

Jeder Block erhält über `BaseBuilderBlock::commonBlockFields()` automatisch:
- **Anchor-ID** – Für `#anchor`-Links
- **Heading-Level** – Tag-Auswahl (h1, h2, h3, none)
- **[Layout-Preset](#6-layout-presets)** – Styling über LayoutPresets (Scope: `section-child`)

Diese Felder erscheinen in einem einklappbaren Bereich „Block-Optionen".

---

### 6. Layout Presets

**Model:** `src/Models/LayoutPreset.php`

Wiederverwendbare Tailwind-CSS-Klassen-Kombinationen, die auf verschiedenen Ebenen des Content-Systems angewendet werden.

| Feld | Beschreibung |
|------|-------------|
| `tenant_id` | `null` = globales Preset (für alle Tenants verfügbar), sonst tenant-spezifisch |
| `scope` | `content`, `section` oder `section-child` – bestimmt den Einsatzort |
| `type` | Optionale Gruppierung (z.B. `card-variants`, `grid-layouts`) |
| `title` | Anzeigename (z.B. „Zwei Spalten Grid") |
| `classes` | Tailwind-Klassen (z.B. `md:grid-cols-2 gap-5`) |

#### Sharing zwischen Tenants

Presets mit `tenant_id = null` sind **global** und für alle Tenants verfügbar. Der Scope `availableTo(?Tenant)` gibt immer globale Presets + tenant-spezifische Presets des jeweiligen Tenants zurück.

#### Nutzung auf drei Ebenen

| Ebene | Scope | Wo konfiguriert | Wie angewendet |
|-------|-------|-----------------|----------------|
| **Content** | `content` | Tab „Veröffentlichung" im Content-Formular | CSS-Klassen auf das äußere Content-Layout |
| **Section** | `section` | Im `section`-Block | CSS-Klassen auf das `<section>`-Element |
| **Block (Kind)** | `section-child` | „Block-Optionen" in jedem Block (via `commonBlockFields()`) | CSS-Klassen um den Block-Wrapper |

#### Inline-Erstellung

Presets können direkt aus dem Select-Feld heraus erstellt werden (Inline-Create-Modal). Dabei werden nur `title` und `classes` abgefragt – `scope` und `tenant_id` werden automatisch aus dem Kontext gesetzt.

#### Verwaltung

Es gibt aktuell keine eigenständige Filament-Resource zur Verwaltung von Layout Presets. Die Erstellung und Zuordnung erfolgt ausschließlich über die Inline-Create-Modals in den Content- und Block-Formularen.

---

### 7. RichEditor

**Konfiguration:** [`app/Providers/Filament/PanelProvider.php`](../app/Providers/Filament/PanelProvider.php)
**Renderer:** `src/Filament/RichEditor/Renderer.php`

Der RichEditor basiert auf TipTap und wird mit Custom Blocks und Extensions erweitert.

#### Custom Blocks (im Editor einfügbar)

| Block | Datei | Beschreibung |
|-------|-------|-------------|
| **ButtonGroup** | `ButtonGroupBlock.php` | Button-Gruppen mit CTAs. Varianten: `primary`, `secondary`, `surface`, `soft`, `dark`, `light`, `ghost-light`. Größen: `sm`, `md`, `lg` |
| **NavigationCardGroup** | `NavigationCardGroupBlock.php` | Mini-Karten mit Label, Beschreibung und Pfeil-Icon |

#### Custom TipTap Extensions

| Extension | Typ | Beschreibung |
|-----------|-----|-------------|
| `LinkPicker` | Mark | Erweitert TipTap-Links um `wire:navigate` und CSS-Klassen |
| `LeadExtension` | Node | Lead-Text-Formatierung |
| `SmallExtension` | Node | Kleine Text-Formatierung |
| `ImageExtension` | Node | Bild-Einbettung |
| `DetailsExtension` | Node | Aufklappbare Details/Accordion |
| `MergeTagExtension` | Node | Platzhalter-Tags |

**Dateien:**
- `src/Filament/RichEditor/LinkPickerPlugin.php`
- `src/Tiptap/Marks/LinkPicker.php`

---

## Datenfluss

### Admin-Panel → Datenbank

```
Filament Admin
  → TenantScopedContentResource (Form)
    → Blueprint bestimmt Felder & Blöcke
    → User füllt Titel, Inhalt, Blöcke, Presets
  → Content::save()
    → Auto: Slug aus Titel generieren
    → Auto: Path berechnen (PathGenerator)
    → Auto: Tenant zuweisen
  → Datenbank (contents-Tabelle)
```

### Request → Frontend-Rendering

```
Browser: GET /services/excavation
  → ResolveTenantFromHost Middleware (Tenant aus Domain)
  → ContentShowController
    → ContentResolver::findByPath() (Content anhand path-Spalte finden)
    → Prüfe: Onepager-Sektion? → OnepagerShellController
    → TemplateResolver::resolve() (Blade-Template bestimmen)
  → Blade-Template rendern
    → <x-site.content-blocks :blocks="$content->blocks" />
      → Für jeden Block: @includeIf('blocks::{type}.view', $data)
        → Block-View rendert HTML mit Tailwind-Klassen
        → Layout-Presets werden als CSS-Klassen aufgelöst
  → HTML-Response
```

### Template-Auflösung

Die `TemplateResolver` sucht Templates in dieser Reihenfolge:

1. **Content-Feld:** Explizit gesetztes Template (`Content.template`)
2. **Blueprint-Default:** `defaultTemplate()` des [Blueprints](#3-blueprints-content-types)
3. **Fallback:** `content.page`

Für jeden Kandidaten wird geprüft:
1. Site-spezifisch: `{site_key}.{template}` (z.B. `marketing.content.service`)
2. Allgemein: `{template}` (z.B. `content.service`)

---

## Filament Admin-Panel

### Content-Resource

**Basis:** `src/Filament/Resources/Contents/TenantScopedContentResource.php`

Das Formular ist in Tabs organisiert:

| Tab | Inhalt | Bedingung |
|-----|--------|-----------|
| **Details** | Payload-Felder (strukturierte Daten), SEO/Meta | Blueprint hat `payloadFormComponents()` |
| **Inhalt** | [Block-Builder](#5-block-system-page-builder) + Sidebar (Parent, Pfad, Typ) | Blueprint hat `hasBuilder()` |
| **Teaser** | Alternativer Builder für Teaser-Blöcke | Blueprint `supportsTeasers()` + Teaser aktiviert |
| **Veröffentlichung** | Sichtbarkeit, Zeitplanung, Template, [Layout-Preset](#6-layout-presets) | Immer sichtbar |

**Tabellen-Features:**
- Spalten: Titel, Content-Type, Pfad, Sichtbarkeit, Status
- Drag & Drop-Sortierung über `sort`-Spalte
- Bearbeiten- und Löschen-Actions

### Generische vs. spezialisierte Resources

- **`ContentResource`:** Catch-All – verwaltet alle Content Types, die keiner spezialisierten Resource zugeordnet sind
- **Site-spezifische Resources:** z.B. `Marketing/Service/Resource.php` – verwalten einen bestimmten Content Type mit angepasstem Formular

---

## Datenbankschema

### `contents`

```
id                  PK
tenant_id           FK → tenants (cascade)
parent_id           FK → contents (nullable, set null)
content_type        string
template            string (nullable)
layout_preset_ids   json (nullable)
title               string
slug                string (nullable)
path                string (nullable)
visibility          string (enum)
publish_from        timestamp (nullable)
publish_until       timestamp (nullable)
blocks              json (nullable)
payload             json (nullable)
references          json (nullable)
meta                json (nullable)
draft               json (nullable)   → HasDraft: noch nicht angewendete Panel-Änderungen
sort                unsigned int (default 0)
created_by          FK → users (nullable)
updated_by          FK → users (nullable)
timestamps

Indizes:
  UNIQUE (tenant_id, path)
  INDEX  (tenant_id, content_type)
  INDEX  (tenant_id, parent_id)
```

### `layout_presets`

```
id          PK
tenant_id   FK → tenants (nullable, cascade)
scope       string (content | section | section-child)
type        string (nullable)
title       string
classes     string
timestamps

Index: (tenant_id, scope)
```

### `tenants`

```
id                  PK
name                string
site_key            string (unique)
primary_domain      string
visibility          enum (public | private | archived)
brand_name          string (nullable)
brand_claim         string (nullable)
logo_path           string (nullable)
secondary_logo_path string (nullable)
mail_logo_path      string (nullable)   → Raster-Logo für HTML-Mails (SVG rendert dort nicht)
favicon_path        string (nullable)
primary_color       string
default_locale      string (nullable)
timezone            string (nullable)
created_by          FK → users
… Kontakt-, Impressum-, Datenschutz- und SEO-Felder (siehe Migration)
spam_questions      json (nullable)     → rotierende Sicherheitsfragen der Formulare
umami_url           string (nullable)   → optionale Analytics, siehe unten
umami_website_id    string (nullable, unique)
umami_replay        bool (default false)
timestamps
```

### `versions`

Snapshot-Historie für `HasVersions` (Content + Fragment). Pro angewendeter
Änderung ein Eintrag; Entwürfe bleiben draußen. Wie viele Snapshots pro Datensatz
gehalten werden, steuert `cms.versions.keep`.

```
id                  PK
user_id             unsigned big int (nullable)
versionable_type    string  ┐ morph
versionable_id      big int ┘
contents            json (nullable)
timestamps + softDeletes
```

### Mediathek

Optional (siehe [Optionale Integrationen](#optionale-integrationen)) und von den
jeweiligen Packages publiziert:

| Tabelle | Herkunft |
|---------|----------|
| `media` | `spatie/laravel-medialibrary` – die Dateien selbst |
| `filament_media_library` | `ralphjsmit/laravel-filament-media-library` – Item (Caption, Alt-Text, Ordner, Tenant) |
| `filament_media_library_folders` | dieselbe Quelle – Ordnerbaum je Tenant |

---

## Enums

### `ContentVisibility`

**Datei:** `src/Enums/ContentVisibility.php`

| Wert | Bedeutung |
|------|-----------|
| `public` | Für alle sichtbar |
| `members` | Nur für eingeloggte Tenant-Mitglieder |

### `ContentStatus`

**Datei:** `src/Enums/ContentStatus.php`

| Wert | Berechnung |
|------|-----------|
| `Draft` | Kein `publish_from` gesetzt |
| `Scheduled` | `publish_from` in der Zukunft |
| `Published` | Zwischen `publish_from` und `publish_until` |
| `Expired` | `publish_until` in der Vergangenheit |

---

## Konfiguration

**Datei:** [`config/cms.php`](../config/cms.php)

| Key | Env-Variable | Beschreibung |
|-----|-------------|-------------|
| `default_branding_tenant_id` | `CMS_BRANDING_TENANT_ID` | ID des Tenants, der als Standard-Branding-Quelle dient. Tenants ohne eigenes Branding erben von diesem Tenant. Fallback: Tenant mit niedrigster ID. |
| `dev_login.email` / `.password` | `CMS_DEV_LOGIN_*` | Nur bei `APP_ENV=local`: Zugangsdaten, die das Panel-Login vorbefüllt. Der Seeder legt damit den Superadmin an. |
| `redirects.*` | – | Redirects & 404-Management (Fuzzy-Auflösung, Hit-Zähler, Pruning). |
| `versions.keep` | `CMS_VERSIONS_KEEP` | Wie viele Snapshots pro Datensatz gehalten werden (0 = unbegrenzt). Ein Snapshot enthält das komplette `blocks`/`payload`-JSON. |

Strukturelles (Models, Blöcke, Menü-Locations, Mediathek-Knöpfe) steht **nicht**
in der Config, sondern als Code in [`app/Providers/CmsServiceProvider.php`](../app/Providers/CmsServiceProvider.php);
Panel-Optionen stehen fluent am Panel in [`app/Providers/Filament/PanelProvider.php`](../app/Providers/Filament/PanelProvider.php).

---

## Optionale Integrationen

Jede dieser Integrationen ist im Engine-Package als *Verdrahtung* vorhanden und
aktiviert sich allein dadurch, dass das jeweilige Package installiert ist. Der
Starter bringt alle drei mit; wer eine nicht braucht, entfernt sie aus der
`composer.json` — Code-Änderungen sind dafür nicht nötig.

| Integration | Package | Was sie bringt |
|-------------|---------|----------------|
| **Mediathek** | `ralphjsmit/laravel-filament-media-library` (kommerziell) + `spatie/laravel-medialibrary` | Zentrale Mediathek pro Tenant, Media-Picker auf allen Bildfeldern, Ordner, Alt-Texte. Ohne das Package fallen alle Felder auf tenant-gescopte `FileUpload`s zurück; gespeicherte Pfade rendern in beiden Fällen. |
| **Picker-UX** | `mmoollllee/filament-media-library-extensions` (MIT) | Upload-Button am Picker, Inline-/Drag-&-Drop-Uploads mit Fortschritts-Kacheln, Auto-Selektion frischer Uploads, erweiterte Vorschau. Nach jedem Update `php artisan filament:assets` laufen lassen — die Asset-URLs sind content-gehasht. |
| **Analytics** | `mmoollllee/filami` (MIT) | Umami: pro Tenant eine Website (automatisch angelegt), cookieloses Tracking-Snippet im Site-Layout, `tel:`/`mailto:`/Outbound-Events, Formular-Funnel und die Panel-Seite „Statistiken". Ohne `UMAMI_URL` bleibt alles inert. |
| **Consent** | `mmoollllee/filament-consent-control` | Banner + Content-Blocking, Kategorien in [`config/consent-control.php`](../config/consent-control.php). Die `analytics`-Kategorie dort gated Umamis Session-Replay-Recorder; die reine Reichweitenmessung läuft ungated und ist nur ausgewiesen. |

**Formular-Funnel:** Public-Forms auf `AbstractTenantAwareForm` benennen sich per
`protected ?string $analyticsName` und rufen als letzte Zeile von `submit()`
`$this->trackConversion([...])` auf — siehe [`app/Livewire/KontaktForm.php`](../app/Livewire/KontaktForm.php).
Auf dem `<form>`-Tag liefert `{!! $this->analyticsAttributes() !!}` die andere
Hälfte (`…-start` beim ersten Tastendruck). Beides ist ohne filami ein No-Op.
In die Event-Properties gehören nur Kategorien, nie Daten über den Absender —
sie landen neben dem Pageview in Umami.

---

## Wichtige Dateien

### Im Projekt (dieses Repo)

| Zweck | Datei |
|-------|-------|
| Content-Model | [`app/Models/Content.php`](../app/Models/Content.php) |
| Tenant-Model | [`app/Models/Tenant.php`](../app/Models/Tenant.php) |
| Fragment-Model | [`app/Models/Fragment.php`](../app/Models/Fragment.php) |
| Engine-Wiring (Models, Blöcke, Optionen) | [`app/Providers/CmsServiceProvider.php`](../app/Providers/CmsServiceProvider.php) |
| Panel-Optionen | [`app/Providers/Filament/PanelProvider.php`](../app/Providers/Filament/PanelProvider.php) |
| Beispiel-Block | [`app/Support/Content/Blocks/contact_form/`](../app/Support/Content/Blocks/contact_form) |
| Kontaktformular (Livewire) | [`app/Livewire/KontaktForm.php`](../app/Livewire/KontaktForm.php) |
| CMS-Konfiguration | [`config/cms.php`](../config/cms.php) |
| Consent-Kategorien | [`config/consent-control.php`](../config/consent-control.php) |
| Design-Tokens | [`resources/css/site/_design-tokens.css`](../resources/css/site/_design-tokens.css) |
| Panel-Theme | [`resources/css/filament/theme.css`](../resources/css/filament/theme.css) |
| Seeder | [`database/seeders/TenantSeeder.php`](../database/seeders/TenantSeeder.php) |
| Deployment | [`deploy.php`](../deploy.php) |

### In der Engine (`vendor/mmoollllee/filament-cms/`)

| Zweck | Datei |
|-------|-------|
| Registry (`Cms::use*`) | `src/Cms.php` |
| Layout-Presets-Model | `src/Models/LayoutPreset.php` |
| Block-Basis-Klasse | `src/Support/Content/Blocks/BaseBuilderBlock.php` |
| Block-Registry | `src/Support/Content/Blocks/BuilderBlockRegistry.php` |
| Block-Interface | `src/Support/Content/Blocks/Contracts/BuilderBlock.php` |
| Content-Resource (Basis) | `src/Filament/Resources/Contents/TenantScopedContentResource.php` |
| Content-Resource (Catch-All) | `src/Filament/Resources/Contents/CatchAllContentResource.php` |
| Revisions-Page (Basis) | `src/Filament/Resources/Concerns/ContentRevisionsPage.php` |
| Pfad-Generator | `src/Support/Content/PathGenerator.php` |
| Content-Resolver | `src/Support/Content/ContentResolver.php` |
| Template-Resolver | `src/Support/Content/TemplateResolver.php` |
| Frontend-Controller | `src/Http/Controllers/Frontend/ContentShowController.php` |
| Blueprint-Interface | `src/Contracts/ContentBlueprint.php` |
| Blueprint-Implementierung | `src/Sites/ConfiguredContentBlueprint.php` |
| SiteExtension-Interface | `src/Contracts/SiteExtension.php` |
| SiteExtension-Registry | `src/Sites/SiteExtensionRegistry.php` |
| RichEditor-Renderer | `src/Filament/RichEditor/Renderer.php` |
| Block-Renderer (Blade) | `resources/views/components/site/content-blocks.blade.php` |
| Analytics-Head (Umami) | `resources/views/components/site/analytics-head.blade.php` |

Jede dieser Views lässt sich projektseitig überschreiben, indem dieselbe
View-Datei unter `resources/views/…` angelegt wird.

---

## Erweiterung

### Neuen Content Type hinzufügen

1. **Blueprint erstellen** in `app/Sites/{SiteName}/{TypeName}/Blueprint.php` – implementiert `ContentBlueprint`
2. **Resource erstellen** (optional) in `app/Sites/{SiteName}/{TypeName}/Resource.php` – erweitert `TenantScopedContentResource`
3. **Blade-Templates** anlegen in `resources/views/{site_key}/content/{type}.blade.php`
4. **Block-Views** erstellen falls neue Blöcke benötigt (in `app/Support/Content/Blocks/{key}/`)
5. Auto-Discovery registriert Blueprint und Resource automatisch über die [SiteExtension](#4-site-extensions)

### Neuen Block hinzufügen

1. **Verzeichnis erstellen:** `app/Support/Content/Blocks/{block_key}/`
2. **Block-Klasse:** `{BlockName}Block.php` – implementiert `BuilderBlock`, erweitert `BaseBuilderBlock`
3. **View:** `{block_key}.blade.php` im selben Verzeichnis
4. **Optional:** `preview.blade.php` für die Admin-Vorschau
5. **Registrieren** in [`app/Providers/CmsServiceProvider.php`](../app/Providers/CmsServiceProvider.php) via
   `Cms::registerBlocks([...Cms::defaultBlocks(), MyBlock::class])` – die Reihenfolge
   ist zugleich die Reihenfolge im Block-Picker

Als Vorlage dient der mitgelieferte Block
[`app/Support/Content/Blocks/contact_form/`](../app/Support/Content/Blocks/contact_form).
Für Bildfelder `MediaField::image()` / `::media()` / `::document()` verwenden statt
roher Komponenten — die Factory liefert je nach Installation den Media-Picker oder
den klassischen `FileUpload`.

### Neuen RichEditor Custom Block hinzufügen

1. **Block-Klasse erstellen** in `app/Filament/RichEditor/Blocks/` – als Vorlage dient
   `ButtonGroupBlock` im Package (`src/Filament/RichEditor/Blocks/`)
2. **Registrieren** durch Überschreiben von `configureRichEditor()` im
   [`PanelProvider`](../app/Providers/Filament/PanelProvider.php) – die Methode setzt
   den kompletten Editor-Stack (Plugins, Custom Blocks, Toolbar) global
3. **Renderer erweitern**, damit die Frontend-Ausgabe den neuen Block kennt
   (`Support\Content\RichText::render()` ist der Einstiegspunkt der Views)
