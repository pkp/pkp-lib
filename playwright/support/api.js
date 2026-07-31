/**
 * @file lib/pkp/playwright/support/api.js
 *
 * pkpApi — client for the test-only API namespace (/api/v1/_test/*), gated by
 * the X-Test-Key header (env TEST_API_KEY). Site-wide routes:
 * /index.php/index/api/v1/_test/…
 *
 * Always drive the fleets via 127.0.0.1, never localhost: the test configs
 * pin allowed_hosts, and the _test API answering on either host while page
 * requests 400 is exactly the trap that cost three probe sessions.
 */
const {request} = require('@playwright/test');

const API_BASE = '/index.php/index/api/v1/_test';

class PkpApi {
    /**
     * @param {{baseURL: string}} options
     */
    static async create({baseURL}) {
        const context = await request.newContext({
            baseURL,
            extraHTTPHeaders: {
                'X-Test-Key': process.env.TEST_API_KEY || '',
            },
        });
        return new PkpApi(context);
    }

    /**
     * @param {import('@playwright/test').APIRequestContext} context
     */
    constructor(context) {
        this.context = context;
    }

    async dispose() {
        await this.context.dispose();
    }

    /**
     * Warm/cold probe. 200 with {installed, seeded} on a live install; any
     * error (500 on an empty DB, 404 when the namespace is off) means cold.
     *
     * @param {string} contextPath
     * @returns {Promise<import('@playwright/test').APIResponse>}
     */
    async bootstrapProbe(contextPath) {
        return this.context.get(`${API_BASE}/bootstrap`, {
            params: {context: contextPath},
            failOnStatusCode: false,
        });
    }

    /**
     * Declarative base seed (context + sections/series + categories + issues
     * + users). Warm calls no-op server-side.
     *
     * @param {object} spec the app's playwright/fixtures/bootstrap.js payload
     */
    async bootstrap(spec) {
        return this._post(`${API_BASE}/bootstrap`, spec);
    }

    /**
     * Seed a scratch context. @returns the created context summary.
     *
     * @param {object} spec {tag, context: {...}, users: [...], ...}
     */
    async createContext(spec) {
        return this._post(`${API_BASE}/scenarios/context`, spec);
    }

    /**
     * Seed a submission at a declared end-state.
     *
     * @param {object} spec {tag, context, submitter, title, ...}
     */
    async createSubmission(spec) {
        return this._post(`${API_BASE}/scenarios/submission`, spec);
    }

    async _post(url, data) {
        const response = await this.context.post(url, {
            data,
            failOnStatusCode: false,
        });
        if (!response.ok()) {
            const body = await response.text();
            throw new Error(
                `POST ${url} failed: ${response.status()}\n${body.slice(0, 2000)}`
            );
        }
        return response.json();
    }
}

module.exports = {PkpApi, API_BASE};
