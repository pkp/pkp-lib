// @ts-check
/**
 * @file lib/pkp/playwright/support/mail.js
 *
 * Copyright (c) 2014-2026 Simon Fraser University
 * Copyright (c) 2003-2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * Mailpit HTTP wrapper — the suite's view of outgoing mail.
 *
 * ONE Mailpit instance is shared by every parallel worker AND by the OJS, OMP
 * and OPS fleets running side by side. Its inbox is therefore global mutable
 * state that nobody owns, and every method here is shaped by that:
 *
 * - `find()` is the canonical read and REQUIRES a recipient plus a content
 *   marker; an unscoped read is a race waiting to happen, so it throws.
 * - Negative assertions go through `expectNone()`, which bounds its wait on a
 *   control message instead of a timeout — "no mail yet" and "no mail ever" are
 *   otherwise indistinguishable.
 * - `clearAll()` wipes everyone's mail, including that of tests running right
 *   now in another worker or another app. It is permitted ONLY in the dedicated
 *   serial infrastructure spec.
 *
 * Seeding-side mail never arrives here: the scenario endpoints run under
 * Mail::fake(), so what Mailpit holds is exactly the mail test ACTIONS produced.
 */

const DEFAULT_TIMEOUT = 15_000;
const DEFAULT_POLL = 500;

class PkpMail {
	/**
	 * @param {import('@playwright/test').APIRequestContext} request configured with the Mailpit base URL
	 */
	constructor(request) {
		this.request = request;
	}

	/**
	 * Poll Mailpit for messages matching a recipient AND a content marker.
	 *
	 * @param {object} options
	 * @param {string} options.to           recipient address
	 * @param {string} [options.contains]   text that appears in the message (a per-test tag)
	 * @param {string} [options.subject]    text that appears in the subject
	 * @param {number} [options.timeoutMs]
	 * @param {number} [options.poll]
	 * @returns {Promise<object[]>} matching message summaries, newest first
	 */
	async find({to, contains, subject, timeoutMs = DEFAULT_TIMEOUT, poll = DEFAULT_POLL}) {
		if (!to || (!contains && !subject)) {
			throw new Error(
				'pkpMail.find() needs a recipient AND a content marker (contains and/or subject). ' +
					'The Mailpit inbox is shared by every worker and every app fleet; an unscoped ' +
					'read matches other tests’ mail.',
			);
		}

		const query = this.buildQuery({to, contains, subject});
		const deadline = Date.now() + timeoutMs;

		for (;;) {
			const messages = await this.search(query);

			if (messages.length) {
				return messages;
			}

			if (Date.now() >= deadline) {
				throw new Error(
					`No message matching \`${query}\` arrived within ${timeoutMs}ms.`,
				);
			}

			await new Promise((resolve) => setTimeout(resolve, poll));
		}
	}

	/**
	 * Assert that no message matching the target arrives.
	 *
	 * The wait is bounded by a CONTROL message taken the same way — once the
	 * control has arrived, anything the target would have produced has had at
	 * least as long to arrive. Use a throwaway recipient for the target: a seeded
	 * user's inbox carries other workers' mail.
	 *
	 * @param {object} options
	 * @param {string} options.to
	 * @param {string} [options.contains]
	 * @param {string} [options.subject]
	 * @param {{to: string, contains?: string, subject?: string}} options.afterControl
	 * @param {number} [options.timeoutMs]
	 */
	async expectNone({to, contains, subject, afterControl, timeoutMs = DEFAULT_TIMEOUT}) {
		if (!afterControl) {
			throw new Error(
				'pkpMail.expectNone() needs an `afterControl` message to bound the wait; ' +
					'a bare timeout only proves the mail was slow.',
			);
		}

		await this.find({...afterControl, timeoutMs});

		const matches = await this.search(this.buildQuery({to, contains, subject}));

		if (matches.length) {
			throw new Error(
				`Expected no message to ${to} matching ${contains ?? subject}, found ${matches.length}:\n` +
					matches.map((message) => `  - ${message.Subject}`).join('\n'),
			);
		}

		return [];
	}

	/**
	 * Every message addressed to an address, polled until at least one arrives.
	 *
	 * Inbox-wide: two mails to the same recipient from different workers both
	 * land here. Prefer `find()` with a tag whenever the assertion cares WHICH
	 * message it got.
	 *
	 * @param {string} email
	 * @param {{timeoutMs?: number, poll?: number}} [options]
	 */
	async inboxFor(email, {timeoutMs = DEFAULT_TIMEOUT, poll = DEFAULT_POLL} = {}) {
		const deadline = Date.now() + timeoutMs;

		for (;;) {
			const messages = await this.search(`to:${email}`);

			if (messages.length) {
				return messages;
			}

			if (Date.now() >= deadline) {
				throw new Error(`No message to ${email} arrived within ${timeoutMs}ms.`);
			}

			await new Promise((resolve) => setTimeout(resolve, poll));
		}
	}

	/**
	 * The newest message to an address. Same caveat as inboxFor().
	 *
	 * @param {string} email
	 * @param {{timeoutMs?: number, poll?: number}} [options]
	 */
	async latestTo(email, options) {
		return (await this.inboxFor(email, options))[0];
	}

	/** Total messages held by Mailpit, all recipients and all fleets. */
	async messageCount() {
		const response = await this.request.get('/api/v1/messages', {
			params: {limit: 1},
		});

		return (await response.json()).messages_count;
	}

	/**
	 * The full message: headers, HTML and text bodies, attachments.
	 *
	 * @param {string} id Mailpit message ID
	 */
	async fullMessage(id) {
		const response = await this.request.get(`/api/v1/message/${id}`);

		if (!response.ok()) {
			throw new Error(`Mailpit has no message ${id} (${response.status()}).`);
		}

		return response.json();
	}

	/**
	 * The href of the first link whose visible text matches — for click-the-link
	 * flows (password resets, invitations).
	 *
	 * @param {string} html
	 * @param {string|RegExp} linkText
	 */
	extractLink(html, linkText) {
		const pattern = /<a\b[^>]*href=["']([^"']+)["'][^>]*>([\s\S]*?)<\/a>/gi;
		const matcher =
			linkText instanceof RegExp
				? (text) => linkText.test(text)
				: (text) => text.includes(linkText);

		for (const match of html.matchAll(pattern)) {
			const text = match[2].replace(/<[^>]+>/g, '').trim();

			if (matcher(text)) {
				return this.decodeEntities(match[1]);
			}
		}

		return null;
	}

	/**
	 * Delete every message Mailpit holds.
	 *
	 * PERMITTED ONLY in the dedicated serial infrastructure spec: this destroys
	 * mail belonging to tests running concurrently in other workers and other app
	 * fleets. Everywhere else, scope with find()/expectNone() and throwaway
	 * recipients.
	 */
	async clearAll() {
		const response = await this.request.delete('/api/v1/messages');

		if (!response.ok()) {
			throw new Error(`Mailpit refused to clear messages (${response.status()}).`);
		}
	}

	//
	// Internals
	//

	/**
	 * @param {{to?: string, contains?: string, subject?: string}} scope
	 */
	buildQuery({to, contains, subject}) {
		const parts = [];

		if (to) {
			parts.push(`to:${to}`);
		}

		if (subject) {
			parts.push(`subject:"${subject}"`);
		}

		if (contains) {
			parts.push(`"${contains}"`);
		}

		return parts.join(' ');
	}

	/**
	 * Mailpit's /api/v1/messages ignores `query`; only /api/v1/search filters.
	 *
	 * @param {string} query
	 */
	async search(query) {
		const response = await this.request.get('/api/v1/search', {
			params: {query, limit: 50},
		});

		if (!response.ok()) {
			throw new Error(
				`Mailpit search failed (${response.status()}). Is Mailpit running? ` +
					'Local: `brew services start mailpit`.',
			);
		}

		return (await response.json()).messages ?? [];
	}

	/** @param {string} value */
	decodeEntities(value) {
		return value
			.replace(/&amp;/g, '&')
			.replace(/&lt;/g, '<')
			.replace(/&gt;/g, '>')
			.replace(/&quot;/g, '"')
			.replace(/&#0?39;/g, "'");
	}
}

module.exports = {PkpMail};
