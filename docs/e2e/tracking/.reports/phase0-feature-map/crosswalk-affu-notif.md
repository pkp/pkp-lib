# Crosswalk — AFFU (001..288) + NOTIF (001..066) → unified features U1–U70

- **Date**: 2026-07-27 · Stage D input for FEATURE-MAP.md.
- **Basis**: synthesis.md §1 (fixed taxonomy U1–U67) as amended by RULINGS.md
  (all D-leans accepted incl. D4 fold of the reviewer dashboard list into U28,
  D16/D17 into U1, D20's U55; Q1–Q5 as ruled; new U68/U69/U70).
- **Rule applied**: notification types go to the feature whose behavior emits
  them (mechanism owner); panel *mounts* go to the mounting screen's feature,
  panel *mechanics* to the mechanism owner. Contiguous runs grouped; every
  exception on its own line. `?` = best candidate with one-line tension note.

## AFFU

AFFU-001..008 → U1
AFFU-009..035 → U2
AFFU-036..052 → U1
AFFU-053..064 → U3
AFFU-065..066 → U4
AFFU-067..092 → U3
AFFU-093..095 → U5 ? the flagged fragile seam: U3 owns the Notifications tab shell (AFFU-058), these are the allow/email toggles + save whose semantics U5 owns — assigned to the semantics owner
AFFU-096..098 → U3
AFFU-099..110 → U4
AFFU-111..121 → U5
AFFU-122..137 → U6
AFFU-138..169 → U28
AFFU-170..175 → U28 ? review-form elements as rendered in the wizard; the forms are built/configured in U29 — surface ownership wins, U29 spec cross-links for config→effect
AFFU-176..205 → U28
AFFU-206 → U6 ? generic invitation-URL landing (InvitationHandler accept/decline/confirmDecline) serving both role invitations and the reviewer one-click key link — homed with the invitation mechanism
AFFU-207..209 → U28
AFFU-210..244 → U45
AFFU-245 → OOS: OMP monographs/chapters + publication-formats clusters (synthesis §5 lists AFFU-245 explicitly; chapters/format authoring stay OOS per RULINGS Q2) ? its `publication` row type alone is plain U45 material — cover as U45's OMP variant line, not a claim on this atom
AFFU-246 → U45
AFFU-247..253 → U65
AFFU-254..268 → U64
AFFU-269..270 → U65
AFFU-271..282 → U64
AFFU-283 → U65
AFFU-284..288 → U64

## NOTIF

NOTIF-001..007 → U5 ? generic framework toast/feedback types (success/warning/error/forbidden/information/help/form-error) emitted by dozens of features — no single mechanism owner, so homed with the notifications mechanism (U5)
NOTIF-008 → U12
NOTIF-009..010 → U62
NOTIF-011 → UNASSIGNED: plugin-type base offset constant, zero references outside its definition (synthesis §4)
NOTIF-012 → U21
NOTIF-013 → U28
NOTIF-014 → U35
NOTIF-015 → OOS: OMP internal review stage (synthesis §5 names NOTIF-015)
NOTIF-016..018 → U35
NOTIF-019 → U27
NOTIF-020 → OOS: OMP internal review stage (synthesis §5 names NOTIF-020)
NOTIF-021..028 → U34
NOTIF-029 → U26
NOTIF-030 → OOS: OMP internal review stage (synthesis §5 names NOTIF-030)
NOTIF-031 → U26 ? emitted by U34's pending-revisions decision but lives/clears with the round's revision upload — homed with review-round revisions behavior
NOTIF-032 → U32
NOTIF-033 → U33
NOTIF-034 → U33 ? legacy index-assignment notice; production-era task per app NotificationManager mapping, but U32 (editing) arguable
NOTIF-035 → U49
NOTIF-036 → U52
NOTIF-037 → U49 ? approval-gate prompt on representations; its OMP publication-format surface is OOS, the approve-submission mechanism it tracks is U49's
NOTIF-038 → U70 ? synthesis §5 parked it with the OOS catalog; RULINGS Q2 scope extension brings catalog management in — the visit-catalog prompt is the add-to-catalog/approval flow's emission, mentionable in U49 as register note per synthesis caveat
NOTIF-039 → U35
NOTIF-040..041 → U37
NOTIF-042..043 → U32
NOTIF-044..045 → U33
NOTIF-046 → U35
NOTIF-047 → U52
NOTIF-048 → U27
NOTIF-049 → U65
NOTIF-050 → U49
NOTIF-051 → U65 ? scheduled reminder digest of outstanding editorial work, grouped with U65's report email; U23 (dashboard triage) arguable
NOTIF-052..053 → U14
NOTIF-054 → U49
NOTIF-055 → U50
NOTIF-056..065 → OOS: NOTIFICATION_TYPE_BOOK_* (explicit CHARTER drop; synthesis §5)
NOTIF-066 → U51

## Coverage check

- AFFU: 288/288 assigned (287 to features, 1 OOS). NOTIF: 66/66 assigned
  (51 to features, 1 UNASSIGNED, 14 OOS). Total 354, no gaps.
- Per-feature totals: U1 25 · U2 27 · U3 41 · U4 14 · U5 21 · U6 17 · U12 1 ·
  U14 2 · U21 1 · U26 2 · U27 2 · U28 61 · U32 3 · U33 4 · U34 8 · U35 6 ·
  U37 13 · U45 36 · U49 4 · U50 1 · U51 1 · U52 2 · U62 2 · U64 32 · U65 12 ·
  U70 1.
- UNASSIGNED: 1 (NOTIF-011). OOS: 15 (AFFU-245 · NOTIF-015/020/030 ·
  NOTIF-056..065).
- `?` flags: 11 lines covering 22 atoms.
