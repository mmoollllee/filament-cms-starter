<div>
    @if ($submitted)
        <div class="mb-6 rounded-panel border border-emerald-200 bg-emerald-50 p-6" role="status">
            <h3 class="text-lg font-semibold text-emerald-900">Vielen Dank für Ihre Anfrage!</h3>
            <p class="mt-2 text-emerald-800">Wir haben Ihre Nachricht erhalten und melden uns kurzfristig bei Ihnen.</p>
        </div>
    @endif

        <form wire:submit="submit" class="grid gap-4">
            {{-- Honeypot --}}
            <div class="hidden" aria-hidden="true">
                <label>Website
                    <input type="text" wire:model="website" tabindex="-1" autocomplete="off">
                </label>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-1">
                    <label class="text-sm font-medium text-slate-700">Name *</label>
                    <input type="text" wire:model="data.name" class="rounded-lg border border-slate-300 px-3 py-2">
                    @error('data.name') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
                </div>
                <div class="grid gap-1">
                    <label class="text-sm font-medium text-slate-700">E-Mail *</label>
                    <input type="email" wire:model="data.email" class="rounded-lg border border-slate-300 px-3 py-2">
                    @error('data.email') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
                </div>
            </div>

            <div class="grid gap-1">
                <label class="text-sm font-medium text-slate-700">Telefon</label>
                <input type="text" wire:model="data.phone" class="rounded-lg border border-slate-300 px-3 py-2">
            </div>

            <div class="grid gap-1">
                <label class="text-sm font-medium text-slate-700">Nachricht *</label>
                <textarea wire:model="data.message" rows="5" class="rounded-lg border border-slate-300 px-3 py-2"></textarea>
                @error('data.message') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
            </div>

            <div class="grid gap-1">
                <label class="text-sm font-medium text-slate-700">Sicherheitsfrage: {{ $this->spamQuizQuestion() }}</label>
                <input type="text" wire:model="quizAnswer" class="max-w-xs rounded-lg border border-slate-300 px-3 py-2">
                @error('quizAnswer') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
            </div>

            <div class="grid gap-2">
                <label class="flex items-start gap-2 text-sm text-slate-600">
                    <input type="checkbox" wire:model="data.privacy_accepted" class="mt-1">
                    <span>Ich habe die <a href="/datenschutz" target="_blank" class="font-semibold text-primary">Datenschutzerklärung</a> gelesen und akzeptiere sie. *</span>
                </label>
                @error('data.privacy_accepted') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
            </div>

            <div>
                <button type="submit" class="btn btn-brand" wire:loading.attr="disabled" wire:target="submit">
                    <span wire:loading.remove wire:target="submit">Anfrage senden</span>
                    <span wire:loading wire:target="submit">Wird gesendet …</span>
                </button>
            </div>
        </form>
</div>
