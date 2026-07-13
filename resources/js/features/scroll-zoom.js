/**
 * Scroll-driven zoom effect for media elements.
 *
 * Elements with `.scroll-zoom` scale their child <img>/<video> from a
 * zoomed-in state (simulating a tight crop) down to normal scale as the
 * element travels through the viewport — a smooth "cover → reveal" feel.
 *
 * A MutationObserver ensures dynamically loaded content (e.g. one-pager
 * AJAX sections) is picked up automatically.
 */

const SELECTOR = '.scroll-zoom';
const SCALE_FROM = 1.5;
const SCALE_TO = 1;

/** CSS animation-timeline handles scroll-zoom natively when supported. */
const cssHandlesZoom = CSS.supports('animation-timeline', 'scroll()');

/** @type {Set<HTMLElement>} */
const tracked = new Set();
let ticking = false;
let mutationObserver = null;

function updateAll() {
    const vh = window.innerHeight;

    for (const el of tracked) {
        const rect = el.getBoundingClientRect();

        // Progress: 0 when element bottom enters viewport, 1 when element top exits.
        const progress = 1 - rect.bottom / (vh + rect.height);
        const clamped = Math.max(0, Math.min(1, progress));

        const scale = SCALE_FROM - (SCALE_FROM - SCALE_TO) * clamped;

        const media = el.querySelector('img, video');

        if (media) {
            media.style.transform = `scale(${scale.toFixed(4)})`;
        }
    }
}

function onScroll() {
    if (ticking) return;

    ticking = true;

    requestAnimationFrame(() => {
        updateAll();
        ticking = false;
    });
}

/** Scan a container for .scroll-zoom elements and start tracking them. */
export function observe(root = document) {
    root.querySelectorAll(SELECTOR).forEach((el) => tracked.add(el));
}

/** Initialise scroll tracking. Call once after DOM is ready. */
export function init() {
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        return;
    }

    // CSS animation-timeline handles the zoom effect natively — skip JS.
    if (cssHandlesZoom) {
        return;
    }

    observe(document);

    window.addEventListener('scroll', onScroll, { passive: true });
    window.addEventListener('resize', onScroll, { passive: true });

    // Initial paint.
    onScroll();
    startMutationObserver();

    // Re-initialise after Livewire SPA navigation replaces the body.
    document.addEventListener('livewire:navigated', () => {
        tracked.clear();
        observe(document);
        startMutationObserver();
        onScroll();
    });
}

function startMutationObserver() {
    if (mutationObserver) {
        mutationObserver.disconnect();
    }

    mutationObserver = new MutationObserver((mutations) => {
        let added = false;

        for (const mutation of mutations) {
            for (const node of mutation.addedNodes) {
                if (node.nodeType !== Node.ELEMENT_NODE) continue;

                if (node.matches?.(SELECTOR)) {
                    tracked.add(node);
                    added = true;
                }

                if (node.querySelectorAll) {
                    node.querySelectorAll(SELECTOR).forEach((el) => {
                        tracked.add(el);
                        added = true;
                    });
                }
            }
        }

        if (added) onScroll();
    });

    mutationObserver.observe(document.body, {
        childList: true,
        subtree: true,
    });
}
