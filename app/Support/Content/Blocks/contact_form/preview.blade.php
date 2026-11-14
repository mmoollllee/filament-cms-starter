@php
    $eyebrow = $eyebrow ?? null;
    $title = $title ?? null;
    $heading = $heading ?? null;
    $resolvedTag = in_array($heading, ['h1', 'h2', 'h3']) ? $heading : 'h2';
    $intro = \Mmoollllee\Cms\Support\Content\RichText::render($content ?? null);
    $recipient = filled($contact_email ?? null) ? $contact_email : 'Kontakt-E-Mail des Tenants';
@endphp
<div class="grid gap-4 [&_*]:pointer-events-none">
    @if (filled($eyebrow))
        <p class="eyebrow">{{ $eyebrow }}</p>
    @endif

    @if (filled($title))
        <{{ $resolvedTag }}>{{ $title }}</{{ $resolvedTag }}>
    @endif

    @if (filled($intro))
        <div class="richtext">{!! $intro !!}</div>
    @endif

    {{-- Abstract placeholder for the live KontaktForm (Livewire) — the real, stateful
         form only renders on the frontend, so here we just represent it (like the
         Fragment block) instead of rendering a half-broken skeleton. --}}
    <x-cms::block-placeholder icon="heroicon-o-envelope" label="Kontaktformular">
        {{ $recipient }}
    </x-cms::block-placeholder>
</div>
