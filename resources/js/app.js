import './bootstrap';

import siteOnepager from './features/site-onepager';
import siteChildNavigation from './features/site-child-navigation';

// Register Alpine extensions — fires when any Alpine instance starts.
// Works regardless of whether Alpine comes from standalone or Livewire ESM.
document.addEventListener('alpine:init', () => {
    window.Alpine.data('siteOnepager', siteOnepager);
    window.Alpine.data('siteChildNavigation', siteChildNavigation);
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
