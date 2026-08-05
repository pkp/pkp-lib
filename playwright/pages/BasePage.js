// @ts-check
/**
 * @file lib/pkp/playwright/pages/BasePage.js
 *
 * Copyright (c) 2014-2026 Simon Fraser University
 * Copyright (c) 2003-2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * Base class for page objects. A POM holds the Playwright page and exposes
 * locators as instance properties plus a few intention-revealing actions; it
 * never asserts — assertions belong to the spec.
 */

class BasePage {
	/** @param {import('@playwright/test').Page} page */
	constructor(page) {
		this.page = page;
	}

	/**
	 * @param {string} url
	 * @param {Parameters<import('@playwright/test').Page['goto']>[1]} [options]
	 */
	async goto(url, options) {
		return this.page.goto(url, options);
	}

	/** The site-wide (context-less) URL prefix. */
	static siteUrl(pathname = '') {
		return `/index.php/index${pathname}`;
	}

	/**
	 * A context-scoped URL. `locale` is only needed on multilingual contexts,
	 * where a bare front-end URL 302s to the locale-prefixed form.
	 *
	 * @param {string} contextPath
	 * @param {string} pathname
	 * @param {string|null} [locale]
	 */
	static contextUrl(contextPath, pathname = '', locale = null) {
		return `/index.php/${contextPath}${locale ? `/${locale}` : ''}${pathname}`;
	}
}

module.exports = {BasePage};
