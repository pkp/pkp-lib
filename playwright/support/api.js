// @ts-check
/**
 * @file lib/pkp/playwright/support/api.js
 *
 * Copyright (c) 2014-2026 Simon Fraser University
 * Copyright (c) 2003-2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * Client for the test-only API namespace (`/api/v1/_test/*`).
 *
 * The namespace is site-wide, so every route sits under the context-less `index`
 * segment; it exists at all only when TEST_API_KEY is in the server's process
 * environment, and every request carries that key in `X-Test-Key`. A missing key
 * answers 404 (the namespace is not registered), a wrong one 403 — the client
 * reports both verbatim rather than masking them, because "the endpoint does not
 * exist" and "the key did not reach PHP" are the two failures worth telling
 * apart at a glance.
 */

const TEST_API_BASE = '/index.php/index/api/v1/_test';

class PkpApi {
	/**
	 * @param {import('@playwright/test').APIRequestContext} request
	 */
	constructor(request) {
		this.request = request;
	}

	/**
	 * @param {string} pathname route below /api/v1/_test
	 * @param {object} [params]
	 */
	async get(pathname, params) {
		const response = await this.request.get(`${TEST_API_BASE}${pathname}`, {
			params,
		});

		return this.unwrap(response, 'GET', pathname);
	}

	/**
	 * @param {string} pathname route below /api/v1/_test
	 * @param {object} spec
	 */
	async post(pathname, spec) {
		const response = await this.request.post(`${TEST_API_BASE}${pathname}`, {
			data: spec,
		});

		return this.unwrap(response, 'POST', pathname);
	}

	/**
	 * @param {import('@playwright/test').APIResponse} response
	 * @param {string} method
	 * @param {string} pathname
	 */
	async unwrap(response, method, pathname) {
		const body = await response.text();
		let parsed;

		try {
			parsed = JSON.parse(body);
		} catch {
			throw new Error(
				`${method} ${pathname} answered ${response.status()} with a non-JSON body:\n` +
					body.slice(0, 2000),
			);
		}

		if (!response.ok()) {
			throw new Error(
				`${method} ${pathname} failed with ${response.status()}: ${JSON.stringify(parsed, null, 2)}`,
			);
		}

		return parsed;
	}

	//
	// Routes
	//

	/**
	 * Has the shared base context been seeded? The cheap probe the setup project
	 * uses to decide between a cold seed and a no-op.
	 *
	 * @param {string} contextPath
	 * @returns {Promise<{seeded: boolean, contextId: number|null}>}
	 */
	async bootstrapStatus(contextPath) {
		return this.get('/bootstrap', {context: contextPath});
	}

	/**
	 * Seed the shared base context and its user roster. Idempotent: a database
	 * that already has the context answers `{seeded: false}`.
	 *
	 * @param {object} payload
	 */
	async bootstrap(payload) {
		return this.post('/bootstrap', payload);
	}

	/**
	 * Create a scratch context — what a test uses when it needs to mutate
	 * context-level state, since the base context is read-only.
	 *
	 * @param {object} spec
	 */
	async createContext(spec) {
		return this.post('/scenarios/context', spec);
	}

	/**
	 * Seed a submission at the state the spec describes.
	 *
	 * @param {object} spec
	 */
	async createSubmission(spec) {
		return this.post('/scenarios/submission', spec);
	}
}

module.exports = {PkpApi, TEST_API_BASE};
