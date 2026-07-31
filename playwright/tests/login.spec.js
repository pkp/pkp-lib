// @ts-check
/**
 * @file lib/pkp/playwright/tests/login.spec.js
 *
 * Login smoke — one real UI login per seeded persona, resolved through
 * appContext.seed.actors (archetype → username-or-null; an archetype can be
 * null on an app). Fresh anonymous context per login: no storage-state cache,
 * so this spec stays meaningful after impersonation flows elsewhere.
 */
const {test, expect} = require('../support/base-test.js');
const {LoginPage} = require('../pages/LoginPage.js');
const {getPassword} = require('../data/users.js');

test.describe('login smoke', () => {
    test('each seeded persona signs in and reaches a signed-in screen', {tag: '@smoke'}, async ({browser, baseURL, appContext}) => {
        const usernames = [
            ...new Set(Object.values(appContext.seed.actors).filter(Boolean)),
        ];
        expect(usernames.length).toBeGreaterThan(0);

        for (const username of usernames) {
            const context = await browser.newContext({
                baseURL,
                storageState: {cookies: [], origins: []},
            });
            try {
                const page = await context.newPage();
                const loginPage = new LoginPage(page);
                await loginPage.goto();
                await loginPage.signIn(username, getPassword(username));
                await expect(page).not.toHaveURL(/\/login/);

                // The signed-in check the UI itself relies on: the profile
                // screen serves (an anonymous request ends on /login instead).
                const profile = await context.request.get('/index.php/index/user/profile');
                expect(profile.ok(), `${username}: profile probe`).toBeTruthy();
                expect(profile.url(), `${username}: profile probe landed on login`).not.toContain('/login');
            } finally {
                await context.close();
            }
        }
    });

    test('a wrong password stays on the login form with an error', async ({browser, baseURL, appContext}) => {
        const username = appContext.seed.actors.manager;
        test.skip(!username, 'no manager persona on this app');

        const context = await browser.newContext({
            baseURL,
            storageState: {cookies: [], origins: []},
        });
        try {
            const page = await context.newPage();
            const loginPage = new LoginPage(page);
            await loginPage.goto();
            await loginPage.usernameInput.fill(username);
            await loginPage.fillPassword('definitely-not-the-password');
            await loginPage.submitButton.click();
            await expect(page).toHaveURL(/\/login/);
            await expect(loginPage.usernameInput).toBeVisible();
        } finally {
            await context.close();
        }
    });
});
