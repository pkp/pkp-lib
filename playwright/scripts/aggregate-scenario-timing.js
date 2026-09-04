#!/usr/bin/env node
// @ts-check

/**
 * Aggregate the per-call scenario timing log written by
 * `lib/pkp/playwright/support/api.js` into a markdown table grouped by
 * `endpoint × spec-shape`.
 *
 * Spec-shape is the sorted list of top-level keys on the spec, joined
 * by `,`. Roughly:
 *   submission-draft       → "journal,participants,section,submitter,tag"
 *   submission-in-review   → "decisions,journal,participants,section,submitter,tag"
 *   submission-in-round-2  → "decisions,journal,participants,reviewRounds,section,submitter,tag"
 *   submission-published   → "decisions,journal,participants,publications,section,submitter,tag"
 *   journal (scratch)      → varies
 *
 * Usage:
 *   node lib/pkp/playwright/scripts/aggregate-scenario-timing.js .scenario-timing.log
 */

const fs = require('fs');

const path = process.argv[2] || '.scenario-timing.log';
if (!fs.existsSync(path)) {
	console.error(`No timing log at ${path}`);
	process.exit(1);
}

/** @type {Map<string, number[]>} */
const buckets = new Map();
const lines = fs.readFileSync(path, 'utf8').split('\n').filter(Boolean);

for (const line of lines) {
	let entry;
	try {
		entry = JSON.parse(line);
	} catch {
		continue;
	}
	if (typeof entry.ms !== 'number') continue;
	const shape = (entry.keys || []).slice().sort().join(',');
	const bucket = `${entry.endpoint}\t${shape}`;
	if (!buckets.has(bucket)) buckets.set(bucket, []);
	buckets.get(bucket).push(entry.ms);
}

function median(arr) {
	const s = arr.slice().sort((a, b) => a - b);
	const n = s.length;
	if (!n) return 0;
	return n % 2 ? s[(n - 1) / 2] : Math.round((s[n / 2 - 1] + s[n / 2]) / 2);
}

const rows = [...buckets.entries()].map(([key, samples]) => {
	const [endpoint, shape] = key.split('\t');
	return {
		endpoint,
		shape,
		count: samples.length,
		median: median(samples),
		max: Math.max(...samples),
		total: samples.reduce((a, b) => a + b, 0),
	};
});

rows.sort((a, b) => b.total - a.total);

console.log('| Endpoint | Spec shape | Calls | Median (ms) | Max (ms) | Total (ms) |');
console.log('|---|---|---|---|---|---|');
for (const r of rows) {
	console.log(
		`| ${r.endpoint} | \`${r.shape}\` | ${r.count} | ${r.median} | ${r.max} | ${r.total} |`,
	);
}
