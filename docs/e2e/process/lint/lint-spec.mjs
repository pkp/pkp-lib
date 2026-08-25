#!/usr/bin/env node
// lint-spec.mjs — the campaign's mechanical spec gate (RUNBOOK step 5, TEMPLATE "The lint gate").
// run:      node lint/lint-spec.mjs [specs/foo.md ...]     default: every specs/*.md
// self-test: node lint/lint-spec.mjs --self-test           embedded good/bad fixtures, no deps
// REFERENCE INTEGRITY ONLY (maintainer, 2026-07-31 — wording, vocabulary and the leak rule
// are the writer's judgment, never linted): campaign identifiers a reader cannot resolve
// (TEMPLATE rules 6–7) · register anatomy · link/anchor/footnote resolution.
// Checks: campaign · register · links. Findings print "file:line — check — excerpt"; exit 1.

import fs from 'node:fs';
import path from 'node:path';
import os from 'node:os';
import { fileURLToPath } from 'node:url';

const SCRIPT_DIR = path.dirname(fileURLToPath(import.meta.url));
const SPECS_DIR = path.resolve(SCRIPT_DIR, '../../specs');
const BADGES = ['🐞', '❓', '✅'];
const BADGE_ORDER = { '🐞': 0, '❓': 1, '✅': 2 };

// ---------------------------------------------------------------- document model

// A spec is: frontmatter · BODY (product language) · "## Footnotes" tail + Reference sections.
// Fenced blocks and HTML comments are template guidance / raw skeleton: skipped by prose checks.
function parseDoc(file) {
    const lines = fs.readFileSync(file, 'utf8').split('\n');
    const skip = new Array(lines.length).fill(false);
    const h2 = new Array(lines.length).fill('');
    const h3 = new Array(lines.length).fill('');
    let inFront = lines[0]?.trim() === '---', inFence = false, inComment = false;
    let curH2 = '', curH3 = '';
    const front = {};
    for (let i = 0; i < lines.length; i++) {
        const l = lines[i];
        if (inFront) {
            skip[i] = true;
            if (i > 0 && l.trim() === '---') inFront = false;
            else { const m = l.match(/^([a-z-]+):\s*(.*)$/); if (m) front[m[1]] = m[2].trim(); }
        } else if (inFence) { skip[i] = true; if (/^\s*```/.test(l)) inFence = false; }
        else if (/^\s*```/.test(l)) { skip[i] = true; inFence = true; }
        else if (inComment) { skip[i] = true; if (l.includes('-->')) inComment = false; }
        else if (l.includes('<!--')) { skip[i] = true; if (!l.includes('-->')) inComment = true; }
        else if (/^##\s+/.test(l)) { curH2 = l.replace(/^##\s+/, '').trim(); curH3 = ''; }
        else if (/^###\s+/.test(l)) { curH3 = l.replace(/^###\s+/, '').trim(); }
        h2[i] = curH2; h3[i] = curH3;
    }
    const findIdx = (re) => lines.findIndex((l, i) => !skip[i] && re.test(l));
    const tailStart = (() => { const i = findIdx(/^##\s+Footnotes/); return i === -1 ? lines.length : i; })();
    const registerStart = (() => { const i = findIdx(/^##\s+Findings register/); return i === -1 ? tailStart : i; })();
    return { file, lines, skip, h2, h3, front, tailStart, registerStart, title: lines[findIdx(/^#\s+/)] || '' };
}

const excerpt = (s, n = 110) => { const t = String(s).trim().replace(/\s+/g, ' '); return t.length > n ? t.slice(0, n) + '…' : t; };

// (The leak rule and the glossary/vocabulary checks were removed 2026-07-31 — TEMPLATE rule 1
// and rule 10 bind by the writer's judgment; the gate keeps only reference integrity.)

// ------------------------------------------------- 1. campaign identifiers (TEMPLATE rules 6–7)
// FEATURE-MAP row codes (U26) and atlas atom IDs (AFFW-323) are the campaign's own bookkeeping.
// A PO or QA reader cannot resolve them, so they never appear in the readable body: a
// cross-feature pointer NAMES the feature ("see *Stage participants*") and links once that
// spec exists. Atom IDs stay legal in the Reference tables and the footnote tail, which this
// check skips along with the leak rule.
const CAMPAIGN_PATTERNS = [
    { re: /(?<![\w-])U\d{1,2}(?![\w-])/g, what: 'FEATURE-MAP row code' },
    { re: /\b(?:AFF[A-Z]{1,2}|GRID|VUE|API|ROUTE|MAIL|NOTIF|JOB|SET|CLI|PLUG)-\d+\b/g, what: 'atlas atom ID' },
];

function checkCampaign(doc, out) {
    for (let i = 0; i < doc.tailStart; i++) {
        if (doc.skip[i] || /^(Reference|Footnotes)/.test(doc.h2[i])) continue;
        const seen = new Set();
        for (const p of CAMPAIGN_PATTERNS) {
            p.re.lastIndex = 0;
            let m;
            while ((m = p.re.exec(doc.lines[i]))) {
                if (seen.has(m[0])) continue;
                seen.add(m[0]);
                out.push({ line: i + 1, check: 'campaign', msg: `${p.what} in body: ${m[0]} — name the feature instead` });
            }
        }
    }
}

// ---------------------------------------------------------------- 2. findings-register integrity

const MARKER_RE = /(⚠\s*)?\[([A-Z]{1,3}\d+)\]\(#([a-z]{1,3}\d+)\)/g;

function checkRegister(doc, out) {
    if (doc.registerStart >= doc.tailStart) { out.push({ line: 1, check: 'register', msg: 'no "## Findings register" section' }); return; }
    const entries = [], summary = [];
    for (let i = doc.registerStart; i < doc.tailStart; i++) {
        if (doc.skip[i]) continue;
        const line = doc.lines[i];
        const anchor = line.match(/^<a id="([a-z]{1,3}\d+)"><\/a>\s*$/);
        if (anchor) {
            const head = doc.lines[i + 1] || '';
            const hm = head.match(/^\*\*([A-Z]{1,3}\d+)\s*[—-]/);
            const badge = BADGES.find((b) => head.includes(b)) || null;
            if (!hm) { out.push({ line: i + 2, check: 'register', msg: `entry #${anchor[1]} has no "**ID — title**" opening line` }); continue; }
            if (!badge) out.push({ line: i + 2, check: 'register', msg: `entry ${hm[1]} has no badge (🐞/❓/✅)` });
            if (hm[1].toLowerCase() !== anchor[1]) out.push({ line: i + 2, check: 'register', msg: `entry ${hm[1]} does not match its anchor #${anchor[1]}` });
            entries.push({ id: hm[1], anchor: anchor[1], badge, line: i + 2, group: doc.h3[i] || '' });
            continue;
        }
        const row = line.match(/^\|\s*\[([A-Z]{1,3}\d+)\]\(#([a-z]{1,3}\d+)\)\s*\|/);
        if (row) summary.push({ id: row[1], badge: BADGES.find((b) => line.includes(b)) || null, line: i + 1 });
    }
    // summary table ↔ entries, 1:1 on ID and badge, sorted 🐞 → ❓ → ✅
    for (const s of summary) {
        const e = entries.find((x) => x.id === s.id);
        if (!e) out.push({ line: s.line, check: 'register', msg: `summary row ${s.id} has no entry` });
        else if (e.badge && s.badge !== e.badge) out.push({ line: s.line, check: 'register', msg: `summary row ${s.id} badge ${s.badge || '(none)'} ≠ entry badge ${e.badge}` });
    }
    for (const e of entries) if (!summary.some((s) => s.id === e.id))
        out.push({ line: e.line, check: 'register', msg: `entry ${e.id} is missing from the summary table` });
    for (let i = 1; i < summary.length; i++) {
        const [a, b] = [summary[i - 1], summary[i]];
        if (a.badge && b.badge && BADGE_ORDER[b.badge] < BADGE_ORDER[a.badge])
            out.push({ line: b.line, check: 'register', msg: `summary out of order: ${b.badge} ${b.id} after ${a.badge} ${a.id} (sort 🐞 → ❓ → ✅)` });
    }
    // IDs dense per section: A1..An / OMP1.. / OPS1.., no gaps
    const groups = {};
    for (const e of entries) (groups[e.id.replace(/\d+$/, '')] ||= []).push(e);
    for (const [prefix, list] of Object.entries(groups))
        list.forEach((e, n) => {
            if (Number(e.id.slice(prefix.length)) !== n + 1)
                out.push({ line: e.line, check: 'register', msg: `${prefix} IDs must be dense and in order — expected ${prefix}${n + 1}, found ${e.id}` });
        });
    // markers ↔ entries, both ways. A 🐞/❓ entry is ⚠-marked at its home; repeat mentions are bare
    // markers (TEMPLATE "Rules & state"), so ⚠ is required once per entry, not on every occurrence.
    const marked = new Set(), warned = new Set();
    for (let i = 0; i < doc.registerStart; i++) {
        if (doc.skip[i]) continue;
        for (const m of doc.lines[i].matchAll(MARKER_RE)) {
            const [, adjacent, id, target] = m;
            if (id.toLowerCase() !== target) { out.push({ line: i + 1, check: 'register', msg: `marker [${id}] points at #${target}` }); continue; }
            const e = entries.find((x) => x.anchor === target);
            if (!e) { out.push({ line: i + 1, check: 'register', msg: `marker [${id}] has no register entry` }); continue; }
            marked.add(e.id);
            // the ⚠ may sit anywhere earlier in the sentence, including the wrapped previous line
            const before = doc.lines[i].slice(0, m.index);
            if (adjacent || before.includes('⚠') || /⚠\s*$/.test(doc.lines[i - 1] || '')) warned.add(e.id);
            if (e.badge === '✅' && adjacent) out.push({ line: i + 1, check: 'register', msg: `marker [${id}] is ⚠ but the entry is an intended divergence (✅ takes a plain link)` });
        }
    }
    for (const e of entries) {
        if (!marked.has(e.id)) out.push({ line: e.line, check: 'register', msg: `entry ${e.id} is never marked in the body` });
        else if (e.badge && e.badge !== '✅' && !warned.has(e.id))
            out.push({ line: e.line, check: 'register', msg: `entry ${e.id} (${e.badge}) has no ⚠ marker in the body — the home mention carries ⚠` });
    }
}

// ---------------------------------------------------------------- 3. link & footnote resolution

const anchorCache = new Map();
function anchorsOf(file) {
    if (anchorCache.has(file)) return anchorCache.get(file);
    let set = new Set();
    if (fs.existsSync(file)) {
        const txt = fs.readFileSync(file, 'utf8');
        for (const m of txt.matchAll(/<a id="([^"]+)"><\/a>/g)) set.add(m[1]);
        for (const m of txt.matchAll(/^#{1,6}\s+(.+)$/gm)) set.add(slug(m[1]));
    }
    anchorCache.set(file, set);
    return set;
}
const slug = (s) => s.toLowerCase().replace(/[^\w\s-]/g, '').trim().replace(/\s+/g, '-');

function checkLinks(doc, out) {
    const usedFootnotes = new Set();
    const own = new Set(), dupes = new Set();
    for (let i = 0; i < doc.lines.length; i++) {
        if (doc.skip[i]) continue;
        for (const m of doc.lines[i].matchAll(/<a id="([^"]+)"><\/a>/g)) {
            if (own.has(m[1]) && !dupes.has(m[1])) { dupes.add(m[1]); out.push({ line: i + 1, check: 'links', msg: `duplicate anchor id "${m[1]}"` }); }
            own.add(m[1]);
        }
    }
    const localAnchors = anchorsOf(doc.file);
    for (let i = 0; i < doc.lines.length; i++) {
        if (doc.skip[i]) continue;
        for (const m of doc.lines[i].matchAll(/\[[^\]]*\]\(([^)\s]+)\)/g)) {
            const target = m[1];
            if (/^(https?:|mailto:|#!)/.test(target)) continue;
            const [filePart, anchorPart] = target.split('#');
            let resolved = doc.file, exists = true;
            if (filePart) {
                resolved = path.resolve(path.dirname(doc.file), filePart);
                exists = fs.existsSync(resolved);
                if (!exists) out.push({ line: i + 1, check: 'links', msg: `link target missing: ${target}` });
            }
            if (exists && anchorPart && !anchorsOf(resolved).has(anchorPart))
                out.push({ line: i + 1, check: 'links', msg: `anchor does not resolve: ${target}` });
        }
        // <sup> marks: linked form <sup>[a](#fn-a)</sup> or bare <sup>a</sup> → block #fn-a
        for (const m of doc.lines[i].matchAll(/<sup>(.*?)<\/sup>/g)) {
            const link = m[1].match(/\]\(#([\w-]+)\)/);
            const id = link ? link[1] : 'fn-' + m[1].trim();
            if (!localAnchors.has(id)) out.push({ line: i + 1, check: 'links', msg: `footnote mark <sup>${excerpt(m[1], 20)}</sup> has no block #${id}` });
            else usedFootnotes.add(id);
        }
    }
    // every footnote block in the tail is reached by at least one mark
    for (let i = doc.tailStart; i < doc.lines.length; i++) {
        if (doc.skip[i]) continue;
        for (const m of doc.lines[i].matchAll(/<a id="(fn-[^"]+)"><\/a>/g))
            if (!usedFootnotes.has(m[1])) out.push({ line: i + 1, check: 'links', msg: `footnote block #${m[1]} has no mark in the body` });
    }
}

// ---------------------------------------------------------------- driver

function lintFile(file) {
    const out = [];
    const doc = parseDoc(file);
    checkCampaign(doc, out);
    checkRegister(doc, out);
    checkLinks(doc, out);
    return out.sort((a, b) => a.line - b.line);
}

function run(files) {
    let total = 0;
    for (const file of files) {
        const findings = lintFile(file);
        total += findings.length;
        const r = path.relative(process.cwd(), file);
        const rel = !r || r.startsWith('..') ? file : r;
        for (const f of findings) console.log(`${rel}:${f.line} — ${f.check} — ${excerpt(f.msg)}`);
    }
    if (total === 0) console.log(`OK — ${files.length} spec(s) clean (campaign · register · links)`);
    else console.log(`\n${total} finding(s) in ${files.length} spec(s)`);
    return total === 0 ? 0 : 1;
}

// ---------------------------------------------------------------- self-test

const GOOD = `---
name: sample-feature
scope: A tester records a decision on the sample surface
apps: [ojs, omp]
shared: pkp-lib
status: draft
atlas-claims: [AFFW-001]
---

# Sample feature {OJS OMP}

> Conventions (markers, badges, footnotes): [Reading a spec](GLOSSARY.md#reading-a-spec).

## Purpose

The sample surface lets an editor record a decision on the review round and lets
the author see the outcome on the same screen. <sup>[a](#fn-a)</sup>

## Rules & state

1. The editor sees "Record decision" while the round is open ⚠ [A1](#a1).
2. On a press the same button opens the catalog step instead [OMP1](#omp1).

## Findings register

Verdicts are the author's judgment (claude, 2026-07-27), unreviewed unless an
entry notes otherwise. Sorted 🐞 → ❓ → ✅ in the summary; entries are the source.

| ID | Finding (one line, symptom) | Bug? | Impact | Review |
|----|-----------------------------|------|--------|--------|
| [A1](#a1) | The button disappears after the first upload | 🐞 | user-visible | — |
| [OMP1](#omp1) | The press flow lands on the catalog step | ✅ | minor | — |

### All apps

<a id="a1"></a>
**A1 — Button disappears** · 🐞 · user-visible.
The author uploads one file and the button vanishes. Expected: it stays.
Basis: probe. <sup>[f-a1](#fn-a1)</sup>

### OMP

<a id="omp1"></a>
**OMP1 — Catalog step instead** · ✅ · minor.
On a press the decision opens the catalog step. Intended divergence.
Basis: judgment. <sup>[f-omp1](#fn-omp1)</sup>

---

<a id="footnotes"></a>
## Footnotes — mechanism & evidence

<a id="fn-a"></a>
**a** — Stage access: \`WorkflowStageAccessPolicy::authorize()\`, live-probed 302.

<a id="fn-a1"></a>
**f-a1** — The show-list omits the resubmitted status.

<a id="fn-omp1"></a>
**f-omp1** — The catalog entry replaces the issue step.
`;

// each case: [expected check, substring of the clean fixture, the bad replacement]
const CASES = [
    ['campaign', 'the author see the outcome', 'the author see the outcome (participants: U35)'],
    ['campaign', 'the author see the outcome', 'the author see the outcome, per atom AFFW-042'],
    ['register', '| [OMP1](#omp1) | The press flow lands on the catalog step | ✅ | minor | — |', '| [OMP1](#omp1) | The press flow lands on the catalog step | 🐞 | minor | — |'],
    ['register', '**A1 — Button disappears** · 🐞 · user-visible.', '**A1 — Button disappears** · user-visible.'],
    ['register', 'the round is open ⚠ [A1](#a1)', 'the round is open ⚠ [A2](#a2)'],
    ['register', 'the round is open ⚠ [A1](#a1)', 'the round is open [A1](#a1)'],
    ['register', '<a id="omp1"></a>\n**OMP1', '<a id="omp2"></a>\n**OMP2'],
    ['links', 'the same screen. <sup>[a](#fn-a)</sup>', 'the same screen. <sup>[z](#fn-z)</sup>'],
    ['links', '[Reading a spec](GLOSSARY.md#reading-a-spec)', '[Reading a spec](../NOPE.md#reading-a-spec)'],
    ['links', '<a id="fn-omp1"></a>', '<a id="fn-a1"></a>'],
];

function selfTest() {
    const dir = fs.mkdtempSync(path.join(os.tmpdir(), 'lint-spec-'));
    fs.mkdirSync(path.join(dir, 'specs'));
    // the shared legend the fixture's conventions line points at (specs/GLOSSARY.md § Reading a spec)
    fs.writeFileSync(path.join(dir, 'specs', 'GLOSSARY.md'), '# Glossary\n\n## Reading a spec\n\nThe one legend for every spec file.\n');
    const write = (text) => { const f = path.join(dir, 'specs', 'sample.md'); fs.writeFileSync(f, text); anchorCache.clear(); return f; };
    let fails = 0;
    const good = lintFile(write(GOOD));
    if (good.length) { fails++; console.log('FAIL clean fixture produced findings:'); good.forEach((f) => console.log(`  line ${f.line} — ${f.check} — ${f.msg}`)); }
    else console.log('pass  clean fixture — 0 findings');
    for (const [check, from, to] of CASES) {
        if (!GOOD.includes(from)) { fails++; console.log(`FAIL fixture mutation not applicable: ${excerpt(from, 40)}`); continue; }
        const findings = lintFile(write(GOOD.replace(from, to)));
        const hit = findings.some((f) => f.check === check);
        console.log(`${hit ? 'pass ' : 'FAIL'} ${check} — ${excerpt(to, 60)}`);
        if (!hit) { fails++; findings.forEach((f) => console.log(`      (got ${f.check}: ${f.msg})`)); }
    }
    fs.rmSync(dir, { recursive: true, force: true });
    console.log(fails ? `\n${fails} self-test failure(s)` : '\nself-test OK');
    return fails ? 1 : 0;
}

// ---------------------------------------------------------------- entry point

const args = process.argv.slice(2);
if (args.includes('--self-test')) process.exit(selfTest());
const targets = args.filter((a) => !a.startsWith('-'));
const files = targets.length
    ? targets.map((t) => (fs.existsSync(path.resolve(t)) ? path.resolve(t) : path.join(SPECS_DIR, t)))
    : fs.readdirSync(SPECS_DIR).filter((f) => /^U\d{2}-.*\.md$/.test(f)).sort().map((f) => path.join(SPECS_DIR, f));
for (const f of files) if (!fs.existsSync(f)) { console.error(`lint-spec: no such file: ${f}`); process.exit(2); }
process.exit(run(files));
