/**
 * @file js/csrfHelper.js
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @brief Live CSRF-token accessor exposed as window.pkp.getCsrfToken().
 *  Walks the freshest-to-stalest source chain:
 *    1. XSRF-TOKEN cookie  — cross-tab synchronized via the browser cookie jar
 *    2. <meta name="csrf-token"> — fresh at page render, then frozen
 *    3. pkp.currentUser.csrfToken — backend-only render-time snapshot
 *
 *  Loaded on every page (backend + frontend) via PKPTemplateManager so legacy
 *  jQuery $.ajax callers, Vue useFetch, and any future caller can read the
 *  latest token regardless of which render-time snapshot they were bootstrapped
 *  with.
 */
(function () {
	window.pkp = window.pkp || {};
	window.pkp.getCsrfToken = function () {
		var match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);
		if (match) {
			return decodeURIComponent(match[1]);
		}
		var meta = document.querySelector('meta[name="csrf-token"]');
		if (meta) {
			return meta.getAttribute('content');
		}
		return (
			(window.pkp.currentUser && window.pkp.currentUser.csrfToken) || ''
		);
	};
})();
