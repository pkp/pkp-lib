/**
 * @file lib/pkp/playwright/pages/DashboardPage.js
 *
 * The post-login landing for editorial roles (the submissions dashboard).
 */
const {BasePage} = require('./BasePage.js');

exports.DashboardPage = class DashboardPage extends BasePage {
    constructor(page) {
        super(page);
        this.userNavMenu = page.locator('.app__userNav, [data-cy="app-user-nav"]');
    }

    /**
     * @param {string} contextPath
     */
    async goto(contextPath) {
        await this.page.goto(this.contextUrl(contextPath, '/dashboard'));
    }
};
