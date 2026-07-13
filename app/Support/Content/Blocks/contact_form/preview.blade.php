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
    <div class="flex items-center gap-3 rounded-card border border-dashed border-slate-300 p-4 text-sm text-slate-500">
        @svg('heroicon-o-envelope', 'h-6 w-6 shrink-0 text-slate-400')
        <span>
            <span class="font-medium text-slate-700">Kontaktformular</span>
            <span class="mx-1 text-slate-400">&rarr;</span>{{ $recipient }}
        </span>
    </div>
</div>
