import './bootstrap';

// Flyout-/Onepager-Mechanik aus dem filament-cms-Package (siteOnepager,
// siteChildNavigation inkl. menuOpen/toggleMenu) — Vendor-Import statt
// lokaler Kopien; Override-Hooks siehe Doku in frontend/index.js.
import { registerCmsFrontend } from '../../vendor/mmoollllee/filament-cms/resources/js/frontend/index.js';

document.addEventListener('alpine:init', () => {
    registerCmsFrontend(window.Alpine);
});

// Pages with @livewireScriptConfig set window.livewireScriptConfig before
// this deferred module executes. Use its presence to load Livewire+Alpine
// or standalone Alpine.
if (window.livewireScriptConfig) {
    import('./livewire-boot').then(({ startLivewire }) => startLivewire());
} else {
    import('alpinejs').then(({ default: Alpine }) => {
        window.Alpine = Alpine;
        Alpine.start();
    });
}

import { init as initScrollAnimate } from './features/scroll-animate';
import { init as initScrollZoom } from './features/scroll-zoom';
initScrollAnimate();
initScrollZoom();

// Consent runtime, bundled here; <x-consent-control-scripts :assets="false" /> only boots it.
import '../../vendor/mmoollllee/laravel-consent-control/resources/dist/js/consent-control.js';
