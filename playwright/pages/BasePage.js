/**
 * @file lib/pkp/playwright/pages/BasePage.js
 *
 * POM base class. POMs hold the Playwright page reference and locators as
 * instance properties. Shared (all-apps) POMs live here; app-only POMs live in
 * each app's playwright/pages/.
 */

exports.BasePage = class BasePage {
    /**
     * @param {import('@playwright/test').Page} page
     */
    constructor(page) {
        this.page = page;
    }

    /**
     * Site-scoped URL (the `index` context), e.g. siteUrl('/login').
     *
     * @param {string} pathname
     * @returns {string}
     */
    siteUrl(pathname) {
        return `/index.php/index${pathname}`;
    }

    /**
     * Context-scoped URL, e.g. contextUrl('publicknowledge', '/dashboard').
     *
     * @param {string} contextPath
     * @param {string} pathname
     * @returns {string}
     */
    contextUrl(contextPath, pathname) {
        return `/index.php/${contextPath}${pathname}`;
    }
};
