// @ts-check

/**
 * Shared HTTP client shell. Wraps Playwright's APIRequestContext and
 * exposes canonical cross-app endpoints (login, CSRF, context). OJS-only
 * endpoints live in playwright/support/ojs-api.js at the OJS root.
 *
 * Stub — method bodies fill in during spec-by-spec migration. Mirrors
 * the app-agnostic subset of lib/pkp/cypress/support/api.js.
 */
exports.createApiClient = function createApiClient({request, baseURL}) {
	return {
		request,
		baseURL,

		async getCsrfToken() {
			throw new Error('TODO: fetch CSRF token from /api/v1/_csrf');
		},

		async login(/* username, password */) {
			throw new Error('TODO: POST /login then persist session cookie');
		},
	};
};
