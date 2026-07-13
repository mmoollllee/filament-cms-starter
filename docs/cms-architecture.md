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
- [Wichtige Dateien](#wichtige-dateien)
- [Erweiterung](#erweiterung)

---

## Überblick

Ein **Multi-Tenant CMS** auf Basis von Laravel 12 und Filament v5. Inhalte werden über ein **Blueprint-System** (Content Types als Code) definiert und mit einem **Block-basierten Page Builder** aufgebaut. Jeder Tenant hat eine eigene Domain, eigenes Branding und eigene Inhalte.

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
| `sort` | Sortierreihenfolge innerhalb des Eltern-Contents |

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
3. **Pfad-Berechnung:** [`PathGenerator::generate()`](../app/Support/Content/PathGenerator.php) setzt die `path`-Spalte

---

### 3. Blueprints (Content Types)

**Interface:** [`app/Sites/Contracts/ContentBlueprint.php`](../app/Sites/Contracts/ContentBlueprint.php)
**Implementierung:** [`app/Sites/ConfiguredContentBlueprint.php`](../app/Sites/ConfiguredContentBlueprint.php)

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

**Interface:** [`app/Sites/Contracts/SiteExtension.php`](../app/Sites/Contracts/SiteExtension.php)
**Registry:** [`app/Sites/SiteExtensionRegistry.php`](../app/Sites/SiteExtensionRegistry.php)

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

**Auto-Discovery:** Blueprints und Resources werden über die Traits `DiscoversSiteBlueprints` und `DiscoversSiteResources` automatisch erkannt. Die [`SiteExtensionRegistry`](../app/Sites/SiteExtensionRegistry.php) verwaltet alle Extensions zentral und ordnet sie über den `site_key` dem richtigen Tenant zu.

---

### 5. Block-System (Page Builder)

**Basis-Klasse:** [`app/Support/Content/Blocks/BaseBuilderBlock.php`](../app/Support/Content/Blocks/BaseBuilderBlock.php)
**Interface:** [`app/Support/Content/Blocks/Contracts/BuilderBlock.php`](../app/Support/Content/Blocks/Contracts/BuilderBlock.php)
**Registry:** [`app/Support/Content/Blocks/BuilderBlockRegistry.php`](../app/Support/Content/Blocks/BuilderBlockRegistry.php)

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

Jeder Block erhält über [`BaseBuilderBlock::commonBlockFields()`](../app/Support/Content/Blocks/BaseBuilderBlock.php) automatisch:
- **Anchor-ID** – Für `#anchor`-Links
- **Heading-Level** – Tag-Auswahl (h1, h2, h3, none)
- **[Layout-Preset](#6-layout-presets)** – Styling über LayoutPresets (Scope: `section-child`)

Diese Felder erscheinen in einem einklappbaren Bereich „Block-Optionen".

---

### 6. Layout Presets

**Model:** [`app/Models/LayoutPreset.php`](../app/Models/LayoutPreset.php)

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
**Renderer:** [`app/Filament/Forms/RichEditor/Renderer.php`](../app/Filament/Forms/RichEditor/Renderer.php)

Der RichEditor basiert auf TipTap und wird mit Custom Blocks und Extensions erweitert.

#### Custom Blocks (im Editor einfügbar)

| Block | Datei | Beschreibung |
|-------|-------|-------------|
| **ButtonGroup** | [`ButtonGroupBlock.php`](../app/Filament/Forms/RichEditor/Blocks/ButtonGroupBlock.php) | Button-Gruppen mit CTAs. Varianten: `primary`, `secondary`, `surface`, `soft`, `dark`, `light`, `ghost-light`. Größen: `sm`, `md`, `lg` |
| **NavigationCardGroup** | [`NavigationCardGroupBlock.php`](../app/Filament/Forms/RichEditor/Blocks/NavigationCardGroupBlock.php) | Mini-Karten mit Label, Beschreibung und Pfeil-Icon |

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
- [`app/Filament/Forms/RichEditor/LinkPickerPlugin.php`](../app/Filament/Forms/RichEditor/LinkPickerPlugin.php)
- [`app/Tiptap/Marks/LinkPicker.php`](../app/Tiptap/Marks/LinkPicker.php)

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

Die [`TemplateResolver`](../app/Support/Content/TemplateResolver.php) sucht Templates in dieser Reihenfolge:

1. **Content-Feld:** Explizit gesetztes Template (`Content.template`)
2. **Blueprint-Default:** `defaultTemplate()` des [Blueprints](#3-blueprints-content-types)
3. **Fallback:** `content.page`

Für jeden Kandidaten wird geprüft:
1. Site-spezifisch: `{site_key}.{template}` (z.B. `marketing.content.service`)
2. Allgemein: `{template}` (z.B. `content.service`)

---

## Filament Admin-Panel

### Content-Resource

**Basis:** [`app/Filament/Resources/Contents/TenantScopedContentResource.php`](../app/Filament/Resources/Contents/TenantScopedContentResource.php)

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

- **[`ContentResource`](../app/Filament/Resources/Contents/ContentResource.php):** Catch-All – verwaltet alle Content Types, die keiner spezialisierten Resource zugeordnet sind
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
primary_color       string
default_locale      string (nullable)
timezone            string (nullable)
created_by          FK → users
timestamps
```

---

## Enums

### `ContentVisibility`

**Datei:** [`app/Enums/ContentVisibility.php`](../app/Enums/ContentVisibility.php)

| Wert | Bedeutung |
|------|-----------|
| `public` | Für alle sichtbar |
| `members` | Nur für eingeloggte Tenant-Mitglieder |

### `ContentStatus`

**Datei:** [`app/Enums/ContentStatus.php`](../app/Enums/ContentStatus.php)

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

---

## Wichtige Dateien

| Zweck | Datei |
|-------|-------|
| Content-Model | [`app/Models/Content.php`](../app/Models/Content.php) |
| Layout-Presets-Model | [`app/Models/LayoutPreset.php`](../app/Models/LayoutPreset.php) |
| Tenant-Model | [`app/Models/Tenant.php`](../app/Models/Tenant.php) |
| Block-Basis-Klasse | [`app/Support/Content/Blocks/BaseBuilderBlock.php`](../app/Support/Content/Blocks/BaseBuilderBlock.php) |
| Block-Registry | [`app/Support/Content/Blocks/BuilderBlockRegistry.php`](../app/Support/Content/Blocks/BuilderBlockRegistry.php) |
| Block-Interface | [`app/Support/Content/Blocks/Contracts/BuilderBlock.php`](../app/Support/Content/Blocks/Contracts/BuilderBlock.php) |
| Content-Resource (Basis) | [`app/Filament/Resources/Contents/TenantScopedContentResource.php`](../app/Filament/Resources/Contents/TenantScopedContentResource.php) |
| Content-Resource (generisch) | [`app/Filament/Resources/Contents/ContentResource.php`](../app/Filament/Resources/Contents/ContentResource.php) |
| Pfad-Generator | [`app/Support/Content/PathGenerator.php`](../app/Support/Content/PathGenerator.php) |
| Content-Resolver | [`app/Support/Content/ContentResolver.php`](../app/Support/Content/ContentResolver.php) |
| Template-Resolver | [`app/Support/Content/TemplateResolver.php`](../app/Support/Content/TemplateResolver.php) |
| Frontend-Controller | [`app/Http/Controllers/Frontend/ContentShowController.php`](../app/Http/Controllers/Frontend/ContentShowController.php) |
| Block-Renderer (Blade) | [`resources/views/components/site/content-blocks.blade.php`](../resources/views/components/site/content-blocks.blade.php) |
| Blueprint-Interface | [`app/Sites/Contracts/ContentBlueprint.php`](../app/Sites/Contracts/ContentBlueprint.php) |
| Blueprint-Implementierung | [`app/Sites/ConfiguredContentBlueprint.php`](../app/Sites/ConfiguredContentBlueprint.php) |
| SiteExtension-Interface | [`app/Sites/Contracts/SiteExtension.php`](../app/Sites/Contracts/SiteExtension.php) |
| SiteExtension-Registry | [`app/Sites/SiteExtensionRegistry.php`](../app/Sites/SiteExtensionRegistry.php) |
| RichEditor-Renderer | [`app/Filament/Forms/RichEditor/Renderer.php`](../app/Filament/Forms/RichEditor/Renderer.php) |
| CMS-Konfiguration | [`config/cms.php`](../config/cms.php) |
| Seeder | [`database/seeders/TenantSeeder.php`](../database/seeders/TenantSeeder.php) |

---

## Erweiterung

### Neuen Content Type hinzufügen

1. **Blueprint erstellen** in `app/Sites/{SiteName}/{TypeName}/Blueprint.php` – implementiert [`ContentBlueprint`](../app/Sites/Contracts/ContentBlueprint.php)
2. **Resource erstellen** (optional) in `app/Sites/{SiteName}/{TypeName}/Resource.php` – erweitert [`TenantScopedContentResource`](../app/Filament/Resources/Contents/TenantScopedContentResource.php)
3. **Blade-Templates** anlegen in `resources/views/{site_key}/content/{type}.blade.php`
4. **Block-Views** erstellen falls neue Blöcke benötigt (in `app/Support/Content/Blocks/{key}/`)
5. Auto-Discovery registriert Blueprint und Resource automatisch über die [SiteExtension](#4-site-extensions)

### Neuen Block hinzufügen

1. **Verzeichnis erstellen:** `app/Support/Content/Blocks/{block_key}/`
2. **Block-Klasse:** `{BlockName}Block.php` – implementiert [`BuilderBlock`](../app/Support/Content/Blocks/Contracts/BuilderBlock.php), erweitert [`BaseBuilderBlock`](../app/Support/Content/Blocks/BaseBuilderBlock.php)
3. **View:** `view.blade.php` im selben Verzeichnis
4. **Optional:** `preview.blade.php` für die Admin-Vorschau
5. **Registrieren** in [`BuilderBlockRegistry`](../app/Support/Content/Blocks/BuilderBlockRegistry.php) (oder via Service Provider)

### Neuen RichEditor Custom Block hinzufügen

1. **Block-Klasse erstellen** in `app/Filament/Forms/RichEditor/Blocks/` – siehe [`ButtonGroupBlock`](../app/Filament/Forms/RichEditor/Blocks/ButtonGroupBlock.php) als Vorlage
2. **Registrieren** in [`PanelProvider.php`](../app/Providers/Filament/PanelProvider.php) im `boot()` der RichEditor-Konfiguration
3. **Renderer erweitern** in [`Renderer.php`](../app/Filament/Forms/RichEditor/Renderer.php) für die Frontend-Ausgabe
