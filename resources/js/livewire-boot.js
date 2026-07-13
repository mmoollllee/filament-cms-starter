import { Livewire, Alpine } from '../../vendor/livewire/livewire/dist/livewire.esm';

export function startLivewire() {
    window.Alpine = Alpine;
    window.Livewire = Livewire;
    Livewire.start();
}
