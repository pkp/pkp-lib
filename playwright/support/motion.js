/**
 * @file lib/pkp/playwright/support/motion.js
 *
 * Disable UI animations and transitions in every page of a BrowserContext so
 * tests never wait on cosmetic motion — the side modal's 450ms reka-ui
 * slide-in/out is the big one (reka-ui keeps the closing DialogContent
 * mounted until animationend).
 *
 * Durations are forced to 0.01ms, NOT 0: Vue <Transition> and the
 * reka-ui/headlessui presence helpers wait on transitionend/animationend (or
 * read the computed duration), and a true 0 can mean "no event ever fires",
 * which would leave leaving elements mounted forever. 0.01ms still fires the
 * events, one frame later.
 *
 * Registered via context.addInitScript so it reaches every page and popup the
 * context opens; pairs with `reducedMotion: 'reduce'` in config-factory.js
 * for anything honoring prefers-reduced-motion.
 */

const DISABLE_MOTION_CSS = `
    *, *::before, *::after {
        transition-duration: 0.01ms !important;
        transition-delay: 0ms !important;
        animation-duration: 0.01ms !important;
        animation-delay: 0ms !important;
        animation-iteration-count: 1 !important;
    }
    html {
        scroll-behavior: auto !important;
    }
`;

/**
 * Register the animation-disabling stylesheet on a BrowserContext.
 * Call once per context, before its pages navigate.
 *
 * @param {import('@playwright/test').BrowserContext} context
 */
async function disableMotion(context) {
    await context.addInitScript((css) => {
        const inject = () => {
            const style = document.createElement('style');
            style.setAttribute('data-pkp-test', 'disable-motion');
            style.textContent = css;
            document.head.appendChild(style);
        };
        // Init scripts run at document-start, before <head> exists.
        if (document.head) {
            inject();
        } else {
            document.addEventListener('DOMContentLoaded', inject);
        }
    }, DISABLE_MOTION_CSS);
}

module.exports = {disableMotion};
