{{-- Block: contact_form — eyebrow/title heading + intro rich text + the live KontaktForm. --}}
@props([
    'data' => [],
    'tenant' => null,
    'content' => null,
    'anchorId' => null,
    'navigationContext' => null,
    'layoutPreset' => '',
])

@php
    $eyebrow = $data['eyebrow'] ?? null;
    $title = $data['title'] ?? null;
    $heading = $data['heading'] ?? null;
    $resolvedTag = in_array($heading, ['h1', 'h2', 'h3']) ? $heading : 'h2';
    $intro = \Mmoollllee\Cms\Support\Content\RichText::render(data_get($data, 'content'));
    $contactEmail = filled($data['contact_email'] ?? '') ? $data['contact_email'] : null;
@endphp

<div {{ $attributes->class(['anim grid gap-5']) }} @if (filled($anchorId)) id="{{ $anchorId }}" @endif>
    @if (filled($eyebrow) || filled($title))
        <x-site.section-header :eyebrow="$eyebrow" :title="$title" :heading="$resolvedTag" />
    @endif

    @if (filled($intro))
        <div class="richtext">{!! $intro !!}</div>
    @endif

    <x-site.card>
        <livewire:kontakt-form
            :content-id="$content?->getKey()"
            :contact-email="$contactEmail"
            :key="'kontaktform-' . ($content?->getKey() ?? '0')"
        />
    </x-site.card>
</div>
