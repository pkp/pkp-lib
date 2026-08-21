// @ts-check

/**
 * Wraps Mailpit's HTTP API for tests that assert on emails sent during
 * normal app requests. Scenario-seeding emails are discarded by the
 * scenario controllers' Mail::fake(); only test-action mail (decisions
 * submitted via UI, password resets, invitations, etc.) reaches Mailpit.
 *
 * Tests opt in by destructuring `pkpMail` from the test fixture:
 *
 *   test('something', async ({page, pkpMail}) => {
 *     await pkpMail.clearAll();
 *     await page.goto('/index.php/index/login/lostPassword');
 *     ...
 *     const messages = await pkpMail.inboxFor('dbarnes@mailinator.com');
 *     expect(messages[0].Subject).toContain('Password Reset');
 *   });
 *
 * Mailpit's API conventions (verified live against v1.29.7):
 *  - GET    /api/v1/messages?query=<search>  → {messages: [{ID, From, To, Subject, Created, Snippet}, ...]}
 *  - DELETE /api/v1/messages                 → 200 on success
 *  - GET    /api/v1/message/:id              → full message body (HTML/Text/Headers)
 * Search-query syntax uses prefixes like `to:`, `from:`, `subject:`.
 */
exports.createMailClient = function ({mailpitUrl, request}) {
	const base = mailpitUrl ?? process.env.MAILPIT_URL ?? 'http://127.0.0.1:8025';

	return {
		/**
		 * Delete every message in Mailpit's inbox.
		 *
		 * **Race warning** — Mailpit is shared across parallel workers.
		 * Calling `clearAll()` while another worker is mid-flow can wipe
		 * mail it just sent. Prefer `deleteForRecipient(email)` (scoped
		 * delete) or filter-by-tag in the Subject when reading
		 * (see `inboxFor` callers in issues.spec.js for the pattern).
		 * Use `clearAll()` only in serial-mode specs or before the very
		 * first send of a run.
		 */
		async clearAll() {
			const res = await request.delete(`${base}/api/v1/messages`);
			if (!res.ok()) {
				throw new Error(
					`Mailpit clearAll failed: ${res.status()} ${await res.text()}`,
				);
			}
		},

		/**
		 * Delete every message addressed to `email` (matches To, Cc, and
		 * Bcc — same semantics Mailpit's `to:` search uses). Safe under
		 * parallel-worker load: workers operating on disjoint recipients
		 * don't race.
		 */
		async deleteForRecipient(email) {
			const res = await request.delete(
				`${base}/api/v1/search?query=${encodeURIComponent('to:' + email)}`,
			);
			if (!res.ok()) {
				throw new Error(
					`Mailpit deleteForRecipient failed: ${res.status()} ${await res.text()}`,
				);
			}
		},

		/**
		 * Poll Mailpit until at least one message addressed to `email`
		 * appears, then return the message list (Mailpit returns newest
		 * first). Throws if no message arrives within `timeout` ms.
		 *
		 * Each entry has Mailpit's PascalCase shape: {ID, From, To,
		 * Subject, Created, Snippet, ...}. Use `fullMessage(id)` for the
		 * complete body.
		 */
		async inboxFor(email, {timeout = 10_000, poll = 250} = {}) {
			const deadline = Date.now() + timeout;
			let lastStatus = null;
			let lastBodyPreview = null;
			while (Date.now() < deadline) {
				const res = await request.get(
					`${base}/api/v1/messages?query=${encodeURIComponent('to:' + email)}`,
				);
				if (!res.ok()) {
					throw new Error(
						`Mailpit query failed: ${res.status()} ${await res.text()}`,
					);
				}
				const body = await res.json();
				lastStatus = `total=${body.messages_count ?? body.total ?? 0}`;
				// Capture a body slice so an unexpected response shape
				// (Mailpit version drift; field rename) is recognisable
				// from the timeout error rather than masquerading as
				// "0 messages".
				lastBodyPreview = JSON.stringify(body).slice(0, 200);
				if (body.messages && body.messages.length > 0) {
					return body.messages;
				}
				await new Promise((r) => setTimeout(r, poll));
			}
			throw new Error(
				`No mail for ${email} within ${timeout}ms ` +
					`(last poll: ${lastStatus}; body: ${lastBodyPreview})`,
			);
		},

		/**
		 * Convenience: return the most recent message addressed to `email`,
		 * or throw if none arrives within the timeout.
		 */
		async latestTo(email, opts) {
			const messages = await this.inboxFor(email, opts);
			return messages[0];
		},

		/**
		 * Total number of messages currently in Mailpit (any recipient,
		 * any subject). Useful for leak-detection assertions — e.g.
		 * confirming `Mail::fake()` in scenario controllers really
		 * suppresses every seeding email.
		 */
		async messageCount() {
			const res = await request.get(`${base}/api/v1/messages`);
			if (!res.ok()) {
				throw new Error(
					`Mailpit count query failed: ${res.status()} ${await res.text()}`,
				);
			}
			const body = await res.json();
			return body.messages_count ?? body.total ?? 0;
		},

		/**
		 * Fetch the full body of a single message by Mailpit ID. Returned
		 * shape includes HTML, Text, Headers — mirror Mailpit's API.
		 */
		async fullMessage(id) {
			const res = await request.get(`${base}/api/v1/message/${id}`);
			if (!res.ok()) {
				throw new Error(
					`Mailpit fetch ${id} failed: ${res.status()} ${await res.text()}`,
				);
			}
			return res.json();
		},

		/**
		 * Pull the first <a href="..."> matching `linkText` out of an HTML
		 * body. Used for click-the-link flows (password reset, invitation
		 * accept, etc.).
		 */
		extractLink(html, linkText) {
			const re = new RegExp(
				`<a[^>]+href="([^"]+)"[^>]*>[^<]*${escapeRegex(linkText)}[^<]*</a>`,
				'i',
			);
			const match = html.match(re);
			if (!match) {
				throw new Error(`Link "${linkText}" not found in mail body`);
			}
			return match[1];
		},
	};
};

function escapeRegex(s) {
	return s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}
