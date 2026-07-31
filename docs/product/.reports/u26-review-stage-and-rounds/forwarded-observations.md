# U26 — observations forwarded to other features (orchestrator record)

Recorded by the orchestrator from test-author returns (2026-07-31). These are
NOT U26 findings; each waits for its owning feature's register. Facts only.

## For U35 — Stage participants (future register)

On OJS, a Section Editor's "Edit Assignment" save on a *fellow section
editor's* assignment is silently discarded: the save answers success
(`status:true`, re-rendered form), no error is shown, the modal stays open,
and the recommend-only flag remains unsaved. A Journal Manager's save on the
same assignment works. `Validation::canEditParticipant` reads as if the
section editor's save should be allowed. Fails closed (no privilege gained).
Observed live 2026-07-31 while building the U26 OJS suite (S11 setup);
workaround in the suite: the manager sets the recommend-only limitation.

## For U25 — Submission stage (future register)

Submit-time editor auto-assignment assigned no editor on scratch journals in
two probe fixtures (probe-P2.md, probe-P3.md Fixture sections) while working
on a third (probe-P5.md) and on scratch presses seeding via an existing
series. Recorded in U26's spec only as seeding caveat (footnote s); the
defect question belongs to the Submission stage feature.

## Harness parity note (recorded in docs/e2e/scenario-processor-audit.md)

Scenario builder: declaring `newExternalReviewRound` in `decisions[]`
alongside two `reviewRounds[]` plans seeds three rounds — the
promoting-decision branch never consumes the second round plan (the decision
reports no new stage id), so the remainder loop builds an extra round.
Workaround: declare extra rounds without the decision. Surfaced by the U26
OJS test author, 2026-07-31.
