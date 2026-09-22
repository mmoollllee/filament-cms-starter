<?php

/*
 * Livewire caps the dot-path depth of a single property update
 * (`livewire.payload.max_nesting_depth`) and throws
 * MaxNestingDepthExceededException above it — which the panel surfaces as the
 * generic "Fehler beim Laden der Seite" notification, with the edit lost.
 *
 * The block editor produces paths far deeper than a plain form: a builder item,
 * a nested builder item, the rich editor's entangled state and then one
 * ProseMirror level per list/quote/table nesting. A text block inside a section
 * — the shape every page starts with — already needs 17 segments for a bullet
 * list.
 */

use App\Models\Content;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Mmoollllee\Cms\Filament\Resources\Contents\Pages\EditContent;

beforeEach(function () {
    [$this->tenant] = actingAsTenantAdmin('default', 'deep-editor.test');

    Filament::setCurrentPanel(Filament::getPanel('panel'));
    Filament::setTenant($this->tenant);
});

/**
 * The path the rich editor sends for text inside a bullet list, in a text block,
 * inside a section block.
 *
 * @return array{0: string, 1: int}
 */
function deepEditorPath(string $outerKey, string $innerKey): array
{
    $path = "data.blocks.{$outerKey}.data.blocks.{$innerKey}.data._content_editor"
        .'.content.0.content.1.content.0.content.0.text';

    return [$path, count(explode('.', $path))];
}

/**
 * A complete ProseMirror document whose SECOND list item carries the leaf the
 * deep path addresses. It has to be valid all the way down: the editor
 * serializes the whole doc on every round trip, so a stub tree fails in tiptap
 * rather than at the depth guard this test is about.
 *
 * @return array<string, mixed>
 */
function bulletListDocument(): array
{
    $listItem = fn (string $text): array => [
        'type' => 'listItem',
        'content' => [[
            'type' => 'paragraph',
            'content' => [['type' => 'text', 'text' => $text]],
        ]],
    ];

    return [
        'type' => 'doc',
        'content' => [[
            'type' => 'bulletList',
            'content' => [$listItem('Erster Punkt'), $listItem('Platzhalter')],
        ]],
    ];
}

it('accepts a rich editor update nested two builder levels deep', function () {
    $content = Content::factory()->for($this->tenant)->create([
        'content_type' => 'default.page',
        'title' => 'Leistungen',
        'path' => '/leistungen',
        'blocks' => [[
            'type' => 'section',
            'data' => [
                'active' => true,
                'blocks' => [[
                    'type' => 'text',
                    'data' => ['title' => 'Leistungen', 'content' => '<ul><li><p>Kurz</p></li></ul>'],
                ]],
            ],
        ]],
    ]);

    $component = Livewire::test(EditContent::class, ['record' => $content->getRouteKey()]);

    $outerKey = array_key_first($component->get('data.blocks'));
    $innerKey = array_key_first($component->get("data.blocks.{$outerKey}.data.blocks"));

    [$path, $depth] = deepEditorPath($outerKey, $innerKey);

    // Guards the fixture itself: a shallower path would pass under any limit
    // and quietly stop testing what this covers.
    expect($depth)->toBe(17);

    // The editor opens with a document, then updates one leaf inside it — the
    // shallow set stands in for that first hydration.
    $component->set(
        "data.blocks.{$outerKey}.data.blocks.{$innerKey}.data._content_editor",
        bulletListDocument(),
    );

    $component->set($path, '30 Tage Mietdauer');

    expect($component->get($path))->toBe('30 Tage Mietdauer');
});

it('keeps headroom above the deepest path the block library can produce', function () {
    // 17 is a bullet list in a nested block; a table already needs 21. Further
    // list levels cost 2 each, a third builder level 3, so the limit has to sit
    // above that sum rather than on top of the current worst case.
    expect(config('livewire.payload.max_nesting_depth'))->toBeGreaterThanOrEqual(30);
});
