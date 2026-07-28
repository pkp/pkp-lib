# U6 top-up probe — the masthead select in an untouched role row

**Question.** On the invite wizard's "Enter details" step, does the masthead
select in an *untouched* role row display the text "Appear on the masthead"
while holding no value (the original OPS observation), or does it render empty
(the later OJS claim check)? And what does "Save And Continue" do when it is
left alone?

**Why.** The finding was retired on OJS evidence for a claim first made on OPS.
This settles it by looking at both apps, in one sitting, with the same
instrument.

**Method.** A signed-in manager of a scratch context, driving the wizard in a
browser. Both apps probed the same way, same day (2026-07-28), against the
frontend bundle both fleets have been serving since it was built on 2026-07-26
(`js/build.js`, identical mtime in `ojs-main` and `ops-main`) — so no rebuild
sits between the original OPS observation and this reading.

- OPS `http://127.0.0.1:8200`, scratch preprint server `u6topops0…`, manager
  `u6topops0…mgr`, wizard at `/{server}/invitation/create/userRoleAssignment`.
- OJS `http://127.0.0.1:8000`, scratch journal `scratch-u6topojs0…`, manager
  `u6topojs0…mgr`, same wizard path.
- Locator, row-structural (the role table reuses control identifiers across
  rows, so nothing is addressed by accessible name):
  `main table >> tbody tr` filtered by `has: select[name="userGroupId"]`
  → `.nth(0)` → `select[name="masthead"]`.
- Read with `selectedIndex` / `value` / `options[selectedIndex].text`, plus a
  screenshot of the row, before anything was touched.

Two presses of "Save And Continue" per app: (A) a wholly untouched row, and
(B) the discriminating case — given name, family name, role and start date all
filled, masthead alone untouched.

## Verdict — OPS

**The untouched select renders EMPTY.** It does not display "Appear on the
masthead".

```
selectedIndex: -1
value:         ""
displayed:     (none — no option is selected)
options:       "Appear on the masthead" (value="true")
               "Does not appear on the masthead" (value="false")
required:      true
```

The screenshot confirms the DOM reading: the row shows an empty role select, a
`dd.mm.yyyy` date placeholder and an **empty** "Server Masthead" select. The row
does not look complete — it looks blank, exactly like the two controls beside
it. There is no placeholder `<option>` and no selected option; the rendered
`<select>` is `<select … name="masthead" required>` with only the two real
options inside.

**"Save And Continue" leaves the row failed.** Case A: the step does not
advance, and three copies of the exact text

> This field is required.

appear — one under "Select a new role", one under "Start Date", one under
"Server Masthead" (the select also flips to `aria-invalid="true"`). Case B, with
everything else filled: exactly **one** "This field is required." remains, under
the masthead select, and the composer step is still not reached.

## Verdict — OJS

**Identical in every respect.** Same `selectedIndex: -1`, same empty `value`,
same two options, same `required`, same blank rendering; case A produces three
"This field is required." (role, start date, "Journal Masthead"), case B exactly
one, under the masthead select, with no advance to the composer.

The only difference between the apps is the column heading and the role
vocabulary — "Server Masthead" and five roles on OPS, "Journal Masthead" and
eighteen on OJS. The control itself is the same shared code behaving the same
way.

## Which reading was wrong

The two apps **agree**. The original OPS observation — "displays the text
'Appear on the masthead' while holding no value" — is a **misreading of the
half it got right**: the select does hold no value, and pressing "Save And
Continue" does fail the row with "This field is required.", but the control is
visibly *empty* while doing so, not falsely pre-filled. The later OJS claim
check was right, and its verdict now stands on evidence from the app where the
claim was originally made.

**The retired finding stays retired.** What made it a finding was the
looks-complete-but-isn't trap; that trap does not exist. A required select that
starts empty and says "This field is required." when skipped is ordinary
behaviour, not a defect.

(The `<select>` carries no placeholder option, so its empty state is conveyed by
blankness alone rather than by a "Choose…" line. That is a cosmetic
observation about a control that is otherwise labelled, marked `* Required`,
and validated — it is not the retired finding and is not proposed as a new one.)

## Proposed content (not written by this probe)

Nothing to reinstate in the register or the atlas.

One correction belongs in a test artifact, and is left to the caller rather than
done here: the OPS POM docblock at
`/Users/jarda/git/pkp/pkp-main/ops-main/playwright/pages/InvitationWizardPage.js`
(lines 26–29) still encodes the disproved half —

> - The "Server Masthead" select LOOKS pre-filled ("Appear on the masthead" is
>   the first option) but holds no value until it is chosen; leaving it alone
>   fails the step with "This field is required."

The second clause is true and worth keeping; "LOOKS pre-filled" is not. Suggested
replacement: *The "Server Masthead" select starts empty — no option is selected —
and leaving it alone fails the step with "This field is required."* No spec or
assertion depends on the wrong clause; nothing in `user-invitations.spec.js`
asserts a pre-filled masthead.
