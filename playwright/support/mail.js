/**
 * @file lib/pkp/playwright/support/mail.js
 *
 * pkpMail — Mailpit HTTP API wrapper (default http://127.0.0.1:8025, override
 * via MAILPIT_URL).
 *
 * Mailpit is ONE shared instance across all three fleets and every parallel
 * worker. Scope EVERY assertion by a unique throwaway recipient address that
 * names the app and the test (e.g. u53top-omp@mail.test) — that is the only
 * scoping this install supports: there are no Mailpit tags here (verified
 * 2026-07-29: GET /api/v1/tags → [], nothing sets X-Tags). `contains` is a
 * CONTENT MARKER — a substring searched in subject/body — a supplement to the
 * recipient scope, never a substitute. Pair every silence claim with a
 * positive control (expectNone does this for you).
 *
 * Never clearAll() outside the dedicated serial infrastructure spec.
 */

class PkpMail {
    /**
     * @param {{url?: string}} options
     */
    constructor({url} = {}) {
        this.url = (url || process.env.MAILPIT_URL || 'http://127.0.0.1:8025').replace(/\/$/, '');
    }

    async _get(pathname, params = {}) {
        const search = new URLSearchParams(params).toString();
        const response = await fetch(`${this.url}${pathname}${search ? `?${search}` : ''}`);
        if (!response.ok) {
            throw new Error(`Mailpit GET ${pathname} failed: ${response.status}`);
        }
        return response.json();
    }

    /**
     * Search scoped by recipient (+ optional content marker / subject).
     * Returns the raw Mailpit search result.
     */
    async _search({to, contains, subject}) {
        if (!to) {
            throw new Error('pkpMail: every Mailpit read must be scoped by a recipient (to:)');
        }
        let query = `to:"${to}"`;
        if (subject) {
            query += ` subject:"${subject}"`;
        }
        if (contains) {
            query += ` "${contains}"`;
        }
        return this._get('/api/v1/search', {query});
    }

    /**
     * THE canonical assertion: poll Mailpit search scoped by recipient plus an
     * optional unique content marker until at least one message matches.
     *
     * @param {{to: string, contains?: string, subject?: string, timeoutMs?: number, poll?: number}} options
     * @returns {Promise<object>} the newest matching message summary
     */
    async find({to, contains, subject, timeoutMs = 20_000, poll = 500}) {
        const deadline = Date.now() + timeoutMs;
        let lastCount = 0;
        for (;;) {
            const result = await this._search({to, contains, subject});
            const messages = result.messages || [];
            lastCount = messages.length;
            if (messages.length > 0) {
                return messages[0];
            }
            if (Date.now() > deadline) {
                throw new Error(
                    `pkpMail.find: no message to "${to}"` +
                        (contains ? ` containing "${contains}"` : '') +
                        ` within ${timeoutMs} ms (matches: ${lastCount})`
                );
            }
            await new Promise((resolve) => setTimeout(resolve, poll));
        }
    }

    /**
     * Negative assertion done right: wait for the positive-control message
     * (bounding the wait), then assert zero matches for the target.
     *
     * @param {{to: string, contains?: string, subject?: string, afterControl: {to: string, contains?: string, subject?: string, timeoutMs?: number}}} options
     */
    async expectNone({to, contains, subject, afterControl}) {
        if (!afterControl || !afterControl.to) {
            throw new Error('pkpMail.expectNone: afterControl {to, ...} is required — an unbounded negative proves nothing');
        }
        await this.find(afterControl);
        const result = await this._search({to, contains, subject});
        const count = (result.messages || []).length;
        if (count > 0) {
            throw new Error(
                `pkpMail.expectNone: expected no message to "${to}"` +
                    (contains ? ` containing "${contains}"` : '') +
                    `, found ${count}`
            );
        }
    }

    /**
     * Count messages matching a recipient-scoped search. For exactly-N claims
     * ("only one change notice went out") AFTER a bounding find() — an
     * unbounded count proves nothing about silence.
     *
     * @param {{to: string, contains?: string, subject?: string}} options
     * @returns {Promise<number>}
     */
    async count({to, contains, subject}) {
        const result = await this._search({to, contains, subject});
        return (result.messages || []).length;
    }

    /**
     * Poll until at least one message addressed to `email` arrives.
     *
     * @returns {Promise<object[]>} matching message summaries, newest first
     */
    async inboxFor(email, {timeout = 20_000, poll = 500} = {}) {
        await this.find({to: email, timeoutMs: timeout, poll});
        const result = await this._search({to: email});
        return result.messages || [];
    }

    /** Convenience for inboxFor(email)[0]. */
    async latestTo(email) {
        return (await this.inboxFor(email))[0];
    }

    /** Total messages, any recipient (e.g. to assert Mail::fake() suppression). */
    async messageCount() {
        const result = await this._get('/api/v1/messages', {limit: '1'});
        return result.total;
    }

    /** Full message (HTML, Text, headers) by ID. */
    async fullMessage(id) {
        return this._get(`/api/v1/message/${id}`);
    }

    /**
     * Regex out the first <a href> whose visible text matches linkText.
     *
     * @param {string} html
     * @param {string|RegExp} linkText
     * @returns {string|null}
     */
    extractLink(html, linkText) {
        const pattern = linkText instanceof RegExp ? linkText : new RegExp(escapeRegExp(linkText), 'i');
        // href may be single- OR double-quoted (PKP's stored email templates
        // use single quotes, e.g. the role-invitation accept/decline buttons).
        const anchorRe = /<a\b[^>]*href=(["'])([^"']+)\1[^>]*>([\s\S]*?)<\/a>/gi;
        let match;
        while ((match = anchorRe.exec(html)) !== null) {
            const visibleText = match[3].replace(/<[^>]+>/g, '').trim();
            if (pattern.test(visibleText)) {
                return match[2].replace(/&amp;/g, '&');
            }
        }
        return null;
    }

    /**
     * DELETE the whole shared inbox. Permitted ONLY in the dedicated serial
     * test-infrastructure spec — every other caller scopes with find/expectNone.
     */
    async clearAll() {
        const response = await fetch(`${this.url}/api/v1/messages`, {method: 'DELETE'});
        if (!response.ok) {
            throw new Error(`Mailpit clearAll failed: ${response.status}`);
        }
    }
}

function escapeRegExp(text) {
    return text.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}

module.exports = {PkpMail};
