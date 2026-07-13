/**
 * Scroll-triggered reveal animations.
 *
 * Elements with `.anim` start invisible (via CSS) and receive `.is-visible`
 * once they reach the viewport, triggering a CSS transition. Reveal is driven
 * by a direct scroll/resize check rather than IntersectionObserver: the
 * observer's first callback can race page layout and leave already-in-view
 * elements permanently hidden until the user scrolls. The check is
 * deterministic — it reveals anything in view AND anything already scrolled
 * past, so content is never stuck hidden (e.g. after a hash jump or fast
 * scroll). A MutationObserver picks up dynamically loaded content.
 *
 * The reveal runs synchronously on each scroll event (no requestAnimationFrame
 * deferral) so it also works in background/inactive tabs where rAF is paused.
 * The query targets only not-yet-revealed elements, so the work shrinks to
 * nothing once everything is visible.
 */

const SELECTOR = '.anim';
const VISIBLE_CLASS = 'is-visible';
// Reveal slightly before fully in view for a smoother feel.
const REVEAL_RATIO = 0.92;

let mutationObserver = null;

/** Reveal every not-yet-visible `.anim` that has reached (or passed) the viewport. */
function revealInView() {
    const viewportHeight = window.innerHeight || document.documentElement.clientHeight;

    document.querySelectorAll(`${SELECTOR}:not(.${VISIBLE_CLASS})`).forEach((el) => {
        if (el.getBoundingClientRect().top < viewportHeight * REVEAL_RATIO) {
            el.classList.add(VISIBLE_CLASS);
        }
    });
}

/** Scan and reveal any `.anim` elements already in view. */
export function observe() {
    revealInView();
}

/** Initialise reveal handling. Call once after the module loads. */
export function init() {
    // Reduced motion: CSS shows `.anim` at full opacity, so there is nothing to do.
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        return;
    }

    revealInView();

    window.addEventListener('scroll', revealInView, { passive: true });
    window.addEventListener('resize', revealInView, { passive: true });
    // Layout can shift after fonts/images load — re-check once everything is in.
    window.addEventListener('load', revealInView);

    startMutationObserver();

    // Re-check after Livewire SPA navigation replaces the body.
    document.addEventListener('livewire:navigated', () => {
        revealInView();
        startMutationObserver();
    });
}

function startMutationObserver() {
    if (mutationObserver) {
        mutationObserver.disconnect();
    }

    mutationObserver = new MutationObserver((mutations) => {
        for (const mutation of mutations) {
            for (const node of mutation.addedNodes) {
                if (node.nodeType !== Node.ELEMENT_NODE) continue;

                if (node.matches?.(SELECTOR) || node.querySelector?.(SELECTOR)) {
                    revealInView();
                    return;
                }
            }
        }
    });

    mutationObserver.observe(document.body, {
        childList: true,
        subtree: true,
    });
}
