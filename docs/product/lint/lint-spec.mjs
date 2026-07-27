#!/usr/bin/env node
// lint-spec.mjs — the campaign's mechanical spec gate (RUNBOOK step 5, TEMPLATE "The lint gate").
// run:      node lint/lint-spec.mjs [specs/foo.md ...]     default: every specs/*.md
// self-test: node lint/lint-spec.mjs --self-test           embedded good/bad fixtures, no deps
// Checks (TEMPLATE rules 1, 10/APP-GLOSSARY, register anatomy, "everything clickable"):
// leak · glossary · register · links. Findings print "file:line — check — excerpt"; exit 1.

import fs from 'node:fs';
import path from 'node:path';
import os from 'node:os';
import { fileURLToPath } from 'node:url';

const SCRIPT_DIR = path.dirname(fileURLToPath(import.meta.url));
const PRODUCT_DIR = path.resolve(SCRIPT_DIR, '..');
const BADGES = ['🐞', '❓', '✅'];
const BADGE_ORDER = { '🐞': 0, '❓': 1, '✅': 2 };
const APPS = ['OJS', 'OMP', 'OPS'];

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
    // paragraphs (contiguous non-blank, non-skipped lines) give cheap context for the vocabulary checks
    const paras = [];
    for (let i = 0; i < lines.length; i++) {
        if (skip[i] || lines[i].trim() === '') continue;
        let j = i; while (j + 1 < lines.length && !skip[j + 1] && lines[j + 1].trim() !== '') j++;
        paras.push({ start: i, end: j, text: lines.slice(i, j + 1).join(' ') });
        i = j;
    }
    const paraAt = (i) => paras.find((p) => i >= p.start && i <= p.end) || { text: lines[i] || '' };
    return { file, lines, skip, h2, h3, front, tailStart, registerStart, paraAt, title: lines[findIdx(/^#\s+/)] || '' };
}

const excerpt = (s, n = 110) => { const t = String(s).trim().replace(/\s+/g, ' '); return t.length > n ? t.slice(0, n) + '…' : t; };

// ---------------------------------------------------------------- 1. leak rule (TEMPLATE rule 1)

// Ordinary product prose must survive: patterns key on code SHAPE, never on vocabulary.
const PRODUCT_WORDS = new Set(['JavaScript', 'GitHub', 'GitLab', 'PostgreSQL', 'MySQL', 'MariaDB',
    'PubMed', 'CrossRef', 'Crossref', 'DataCite', 'PayPal', 'OpenAthens', 'OpenAIRE', 'PhpMyAdmin']);

const LEAK_PATTERNS = [
    // Class::method / Class::CONSTANT — the canonical PHP anchor form, provenance only
    { re: /\b[A-Za-z_][\w]*::[A-Za-z_][\w]*/g },
    // any call form — foo(), Obj.method() — parentheses glued to an identifier
    { re: /\b[A-Za-z_][\w]*(?:\.[A-Za-z_][\w]*)*\(\)/g },
    // route / URL path: a leading slash plus two or more segments (not "and/or", not "../x.md")
    { re: /(?<![\w.])\/[A-Za-z][\w-]*(?:\/[\w{}$.-]+)+/g },
    // source file names (.md doc pointers are deliberately absent — those are reader links)
    { re: /\b[\w-]+\.(?:php|js|mjs|cjs|vue|tsx?|jsx?|xml|json|tpl|twig|sql|yaml|yml)\b/g },
    // Vue / HTML component tags with a capitalised name (<sup>, <br>, <a id> stay legal)
    { re: /<\/?[A-Z][A-Za-z0-9]*(?=[\s/>])/g },
    // SCREAMING_SNAKE constants — the underscore is required, so OJS / QA / AFFW-323 never match
    { re: /\b[A-Z][A-Z0-9]*(?:_[A-Z0-9]+)+\b/g },
    // snake_case identifiers — DB tables and columns; English prose has none
    { re: /\b[a-z][a-z0-9]*(?:_[a-z0-9]+)+\b/g },
    // bare PascalCase symbols (ClassName, PkpThing) — real product names are allowlisted above
    { re: /\b[A-Z][a-z0-9]+(?:[A-Z][a-z0-9]+)+\b/g, keep: (m) => !PRODUCT_WORDS.has(m) },
    // HTTP status codes / the protocol word — probe evidence, footnotes only (rule 4)
    { re: /\bHTTPS?\b|(?<![\w%-]|\d[.,])(?:200|201|204|301|302|303|304|307|308|400|401|403|404|405|409|410|422|429|500|502|503)(?![\w%-]|[.,]\d)/g },
    // `code spans` carrying symbols; spans quoting a UI label or a marker form are fine
    { re: /`([^`]+)`/g, keep: (_m, inner) => codeSpanIsCode(inner), report: (m) => m },
];

function codeSpanIsCode(s) {
    const t = s.trim();
    if (/^[\w./-]+\.md(#[\w-]+)?$/.test(t)) return false;             // doc pointer, e.g. `APP-GLOSSARY.md`
    if (/^[⚠\s]*\[[^\]]+\]\(#[\w-]+\)$/.test(t)) return false;        // marker form shown in the legend
    if (/^<sup>.*<\/sup>$/.test(t)) return false;                     // footnote-mark form shown in the legend
    if (/^\{[A-Z ]+\}$/.test(t)) return false;                        // app badge form shown in the legend
    // symbols, a path-shaped slash (so a `Accept/Decline` label survives), or camel/Pascal case
    return /::|\(\)|->|=>|\$|_/.test(t) || /(^|\s)\/[\w{]/.test(t)
        || /\.(php|js|mjs|vue|tsx?|xml|json|tpl|twig|sql)\b/.test(t)
        || /^[a-z]+[A-Z]/.test(t) || /^[A-Z][a-z]+[A-Z]/.test(t);
}

function checkLeak(doc, out) {
    for (let i = 0; i < doc.tailStart; i++) {
        if (doc.skip[i] || /^(Reference|Footnotes)/.test(doc.h2[i])) continue;
        const hits = [];
        for (const p of LEAK_PATTERNS) {
            p.re.lastIndex = 0;
            let m;
            while ((m = p.re.exec(doc.lines[i]))) {
                if (p.keep && !p.keep(m[0], m[1])) continue;
                hits.push({ start: m.index, end: m.index + m[0].length, text: m[0].trim() });
            }
        }
        // one finding per leaked symbol: keep the widest hit, drop anything overlapping it
        const kept = [];
        for (const h of hits.sort((a, b) => a.start - b.start || b.end - a.end))
            if (!kept.some((k) => h.start < k.end && k.start < h.end) && !kept.some((k) => k.text === h.text)) kept.push(h);
        for (const h of kept) out.push({ line: i + 1, check: 'leak', msg: `code in body: ${h.text}` });
    }
}

// Word-boundary term matcher, used by the glossary check. (Wording/style checks were removed
// 2026-07-28 — phrasing is the writer's judgment; the gate keeps only mechanical integrity.)
const termRe = (t) => new RegExp(`(?<![\\w-])${t.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}(?![\\w-])`, 'gi');

// --------------------------------------- 3. glossary vocabulary & app badges (rule 10, APP-GLOSSARY)

function loadGlossaries(dir = PRODUCT_DIR) {
    const crossApp = [];
    const appGlossary = path.join(dir, 'APP-GLOSSARY.md');
    if (fs.existsSync(appGlossary)) {
        const txt = fs.readFileSync(appGlossary, 'utf8');
        const section = txt.split(/^##\s+2\./m)[0]; // §1 Vocabulary map only — the contract says the OMP/OPS columns
        for (const row of section.split('\n')) {
            const cells = row.split('|').map((c) => c.trim());
            if (cells.length < 5 || /^[-: ]+$/.test(cells[2] || '')) continue;
            const [ojs, omp, ops] = [cells[1], cells[2], cells[3]];
            for (const [app, cell] of [['OMP', omp], ['OPS', ops]]) {
                const t = cleanTerm(cell);
                if (t && t.toLowerCase() !== cleanTerm(ojs)?.toLowerCase()) crossApp.push({ app, term: t });
            }
        }
    }
    const coined = [];
    const glossary = path.join(dir, 'GLOSSARY.md');
    if (fs.existsSync(glossary))
        for (const m of fs.readFileSync(glossary, 'utf8').matchAll(/^##\s+(.+)$/gm)) coined.push(m[1].trim());
    return { crossApp, coined };
}

// APP-GLOSSARY cells that are ordinary English words would misfire as cross-app vocabulary
const STOP_TERMS = new Set(['series', 'production only']);

function cleanTerm(cell) {
    if (!cell) return null;
    let t = cell.split(/[(;,]/)[0].replace(/\*+/g, '').replace(/^["'“”]|["'“”]$/g, '').trim();
    if (!t || t.startsWith('—') || t.startsWith('-') || t.includes('→') || t.includes(':')) return null;
    if (t.length < 4 || t.length > 40 || /^(no|not|none)\b/i.test(t)) return null;
    return STOP_TERMS.has(t.toLowerCase()) ? null : t;
}

function checkGlossary(doc, out, glo) {
    // title badge ⊆ {OJS OMP OPS} and frontmatter `apps:` says the same thing
    const badge = doc.title.match(/\{([^}]*)\}/);
    const declared = (doc.front.apps || '').replace(/[[\]]/g, '').split(',').map((s) => s.trim().toUpperCase()).filter(Boolean);
    const titleLine = doc.lines.indexOf(doc.title) + 1;
    let badged = null;
    if (badge) {
        badged = badge[1].trim().split(/\s+/);
        const bad = badged.filter((b) => !APPS.includes(b));
        if (bad.length) out.push({ line: titleLine, check: 'glossary', msg: `title badge has non-app value(s): ${bad.join(' ')}` });
    }
    const expected = badged || APPS;
    if (declared.length && (declared.length !== expected.length || expected.some((a) => !declared.includes(a))))
        out.push({ line: titleLine, check: 'glossary', msg: `title badge {${expected.join(' ')}} disagrees with frontmatter apps: [${declared.join(', ').toLowerCase()}]` });
    if (!declared.length) out.push({ line: 1, check: 'glossary', msg: 'frontmatter has no apps: list' });
    // inline badges anywhere must also be app values
    for (let i = 0; i < doc.tailStart; i++) {
        if (doc.skip[i]) continue;
        for (const m of doc.lines[i].matchAll(/\{([A-Z][A-Z ]*)\}/g)) {
            const vals = m[1].trim().split(/\s+/);
            if (vals.some((v) => !APPS.includes(v)))
                out.push({ line: i + 1, check: 'glossary', msg: `badge {${m[1].trim()}} is not a subset of {OJS OMP OPS}` });
        }
    }
    // cross-app vocabulary: specs are written in OJS terms; an OMP/OPS term needs an app-scoped context
    for (let i = 0; i < doc.registerStart; i++) {
        if (doc.skip[i] || /^How to read/i.test(doc.h2[i])) continue;
        const para = doc.paraAt(i).text;
        if (/OMP|OPS/.test(para)) continue; // app-scoped paragraph (badge, marker or explicit app name)
        const line = doc.lines[i].replace(/"[^"]*"/g, '""'); // quoted on-screen labels are the app's own words
        for (const { app, term } of glo.crossApp)
            if (termRe(term).test(line))
                out.push({ line: i + 1, check: 'glossary', msg: `${app} term "${term}" outside an app-scoped passage (write the OJS term, APP-GLOSSARY substitutes)` });
    }
    // coined terms: first use per spec carries a gloss or a GLOSSARY pointer (rule 10)
    for (const term of glo.coined) {
        const first = doc.lines.findIndex((l, i) => !doc.skip[i] && i < doc.tailStart && termRe(term).test(l));
        if (first === -1) continue;
        if (!/GLOSSARY\.md/.test(doc.paraAt(first).text))
            out.push({ line: first + 1, check: 'glossary', msg: `coined term "${term}" first used without a GLOSSARY pointer` });
    }
    // coined-looking bolded definitions that have no glossary home stay the writer's call — not linted.
}

// ---------------------------------------------------------------- 4. findings-register integrity

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
        if (doc.skip[i] || /^How to read/i.test(doc.h2[i])) continue; // the legend shows marker FORMS, not markers
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

// ---------------------------------------------------------------- 5. link & footnote resolution

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

function lintFile(file, glo) {
    const out = [];
    const doc = parseDoc(file);
    checkLeak(doc, out);
    checkGlossary(doc, out, glo);
    checkRegister(doc, out);
    checkLinks(doc, out);
    return out.sort((a, b) => a.line - b.line);
}

function run(files) {
    const glo = loadGlossaries();
    let total = 0;
    for (const file of files) {
        const findings = lintFile(file, glo);
        total += findings.length;
        const r = path.relative(process.cwd(), file);
        const rel = !r || r.startsWith('..') ? file : r;
        for (const f of findings) console.log(`${rel}:${f.line} — ${f.check} — ${excerpt(f.msg)}`);
    }
    if (total === 0) console.log(`OK — ${files.length} spec(s) clean (leak · glossary · register · links)`);
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

## How to read this file

An unmarked claim asserts the behavior is identical in OJS and OMP. \`⚠ [A1](#a1)\`
marks an as-built deviation; a plain \`[OMP1](#omp1)\` marks an intended
divergence. \`<sup>[a](#fn-a)</sup>\` marks link to the footnote tail.

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
    ['leak', 'The editor sees "Record decision"', 'The editor sees ReviewRound::create() write review_rounds'],
    ['leak', 'the round is open ⚠', 'the round is open at /workflow/access/12 ⚠'],
    ['leak', 'the author see the outcome', 'the author see a 403 from the ROLE_ID_AUTHOR check'],
    ['glossary', '# Sample feature {OJS OMP}', '# Sample feature {OJS OMP OPS}'],
    ['glossary', '# Sample feature {OJS OMP}', '# Sample feature {OJS DEV}'],
    ['glossary', '2. On a press the same button opens the catalog step instead [OMP1](#omp1).', '2. The press manager sees a monograph in the same row.'],
    ['register', '| [OMP1](#omp1) | The press flow lands on the catalog step | ✅ | minor | — |', '| [OMP1](#omp1) | The press flow lands on the catalog step | 🐞 | minor | — |'],
    ['register', '**A1 — Button disappears** · 🐞 · user-visible.', '**A1 — Button disappears** · user-visible.'],
    ['register', 'the round is open ⚠ [A1](#a1)', 'the round is open ⚠ [A2](#a2)'],
    ['register', 'the round is open ⚠ [A1](#a1)', 'the round is open [A1](#a1)'],
    ['register', '<a id="omp1"></a>\n**OMP1', '<a id="omp2"></a>\n**OMP2'],
    ['links', 'the same screen. <sup>[a](#fn-a)</sup>', 'the same screen. <sup>[z](#fn-z)</sup>'],
    ['links', 'marks link to the footnote tail.', 'marks link to the [footnote tail](../NOPE.md).'],
    ['links', '<a id="fn-omp1"></a>', '<a id="fn-a1"></a>'],
];

function selfTest() {
    const dir = fs.mkdtempSync(path.join(os.tmpdir(), 'lint-spec-'));
    fs.mkdirSync(path.join(dir, 'specs'));
    fs.writeFileSync(path.join(dir, 'GLOSSARY.md'), '# Glossary\n\n<a id="sample-term"></a>\n## sample term\n\nA coined term.\n');
    fs.writeFileSync(path.join(dir, 'APP-GLOSSARY.md'),
        '# App glossary\n\n## 1. Vocabulary map\n\n| OJS term (as written in specs) | OMP | OPS |\n|---|---|---|\n' +
        '| journal | press | preprint server |\n| article / submission | monograph | preprint |\n| Journal Manager | Press Manager | Preprint Server Manager |\n\n## 2. Capability names\n');
    const glo = loadGlossaries(dir);
    const write = (text) => { const f = path.join(dir, 'specs', 'sample.md'); fs.writeFileSync(f, text); anchorCache.clear(); return f; };
    let fails = 0;
    const good = lintFile(write(GOOD), glo);
    if (good.length) { fails++; console.log('FAIL clean fixture produced findings:'); good.forEach((f) => console.log(`  line ${f.line} — ${f.check} — ${f.msg}`)); }
    else console.log('pass  clean fixture — 0 findings');
    for (const [check, from, to] of CASES) {
        if (!GOOD.includes(from)) { fails++; console.log(`FAIL fixture mutation not applicable: ${excerpt(from, 40)}`); continue; }
        const findings = lintFile(write(GOOD.replace(from, to)), glo);
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
    ? targets.map((t) => (fs.existsSync(path.resolve(t)) ? path.resolve(t) : path.join(PRODUCT_DIR, t)))
    : fs.readdirSync(path.join(PRODUCT_DIR, 'specs')).filter((f) => f.endsWith('.md')).sort().map((f) => path.join(PRODUCT_DIR, 'specs', f));
for (const f of files) if (!fs.existsSync(f)) { console.error(`lint-spec: no such file: ${f}`); process.exit(2); }
process.exit(run(files));
