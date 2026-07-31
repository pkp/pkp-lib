# Scenario-endpoint parity ledger

Recreated empty at FULL RESET #2 (2026-07-31, PROGRESS banner) — the prior
rows described builders scratched with the harness; git history keeps them.
Contract: PRINCIPLES §"Architecture principles" 2 — a seeded scenario must
leave the same database state, fire the same hooks, and produce the same
notifications as a user performing the equivalent steps through the UI/REST
API. **Any change to a Processor requires a parity entry here before it
merges**; deliberate deviations are recorded as such, with rationale.

Format: one row per audited builder path per app. Evidence detail stays in
`.reports/`; this file records the verdict.

| Date | App | Builder path | Parity check | Verdict | Notes |
|---|---|---|---|---|---|
