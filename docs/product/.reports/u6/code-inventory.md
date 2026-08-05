# U6 — User invitations · code-structure inventory

**Method**: code reading only (OJS `ojs-main`, OMP `omp-main`, OPS `ops-main`, shared `lib/pkp`,
shared `lib/ui-library`). No running install, no browser, no requests. Nothing here is a
behavioral claim; every ambiguity is marked **needs a probe** and left unresolved.
Symbols only, never line numbers. Paths relative to a repo root unless prefixed.

**Atoms mapped: 33.** **Items marked "needs a probe": 14 (P1–P14).**

---

## 1. Atom → code map

Format: `atom · what it is · stable symbol · resolves?`

### AFFM (invite affordances on Users & Roles)

| Atom | What it is | Symbol | Resolves |
|---|---|---|---|
| AFFM-099 | "Invite to a role" button | `UserInvitationManager.vue` → `PkpButton` in `#top-controls`, label `t('invitation.inviteToRole.btn')`; click → `UserInvitationManagerStore.createNewInvitation` → `useUrl('invitation/create/userRoleAssignment').redirectToPage()` | ✅ |
| AFFM-100 | Pending-row Edit invitation | `UserInvitationManagerStore.handleInvitationAction('editInvite')` → `handleEditInvitation` → dialog `userInvitation.edit.title` / `userInvitation.edit.message` → `useUrl('invitation/edit/{id}').redirectToPage()` | ✅ |
| AFFM-101 | Pending-row Cancel invitation | `UserInvitationManagerStore.handleCancelInvitation` + `UserInvitationManagerCancelInvitationDialogBody.vue`; confirm → `PUT invitations/{id}/cancel` then refetch | ✅ |
| AFFM-118 | Wizard step 1 — search existing user | `UserInvitationSearchFormStep.vue`; built by `SendInvitationStep::invitationSearchUser()` (step id `searchUser`) | ✅ |
| AFFM-119 | Wizard step 2 — details + role rows | `UserInvitationDetailsFormStep.vue` + `UserInvitationUserGroupsTable.vue`; built by `SendInvitationStep::invitationDetailsForm()` (step id `userDetails`) | ✅ |
| AFFM-120 | Wizard step 3 — email composer + nav/submit | `UserInvitationEmailComposerStep.vue` + `UserInvitationPage.vue` Cancel/Back/Continue buttons; built by `SendInvitationStep::invitationInvitedEmail()` (step id `userInvited`) | ✅ |

### AFFU (accept / decline / unavailable surfaces)

| Atom | What it is | Symbol | Resolves |
|---|---|---|---|
| AFFU-122 | Accept page mount | `lib/pkp/templates/invitation/acceptInvitation.tpl` → `<accept-invitation-page>` | ✅ |
| AFFU-123 | Decline confirm submit | `lib/pkp/templates/invitation/declineInvitation.tpl` `fbvElement` `#declineInvitationSubmit`, key `invitation.decline.confirm`, POST to `$declineUrl` with `{csrf}` | ✅ |
| AFFU-124 | Unavailable → Login link | `lib/pkp/templates/invitation/invitationUnavailable.tpl` `a.pkpButton` key `user.login`, href `$loginUrl` | ✅ |
| AFFU-125 | Unavailable → Register link | same template, key `user.login.registerNewAccount`, href `$registerUrl` | ✅ |
| AFFU-126 | Accept wizard step tabs | `AcceptInvitationPage.vue` `Steps @step:open="store.openStep"`; clickability gated inside `components/Steps/Steps.vue` by `startedSteps.includes(step.id)` | ✅ |
| AFFU-127 | Accept wizard Cancel | `AcceptInvitationPage.vue` `PkpButton t('common.cancel')` → `AcceptInvitationPageStore.cancel` → dialog `acceptInvitation.cancelInvite.title` (actions `acceptInvitation.cancelInvite.button` → redirect `submissions`; `userInvitation.cancel.goBack` → close) | ✅ |
| AFFU-128 | Accept wizard Back | `AcceptInvitationPage.vue` `PkpButton t('common.back')`, `v-if="!store.isOnFirstStep"` | ✅ |
| AFFU-129 | Accept wizard Continue/Accept | `AcceptInvitationPage.vue` `PkpButton :label="store.stepButtonTitle"` (server `currentStep.nextButtonLabel`), `v-if="store.currentStep.id !== 'verifyOrcid'"`. **No `:is-disabled` binding at all** | ✅ |
| AFFU-130 | Verify ORCID button | `AcceptInvitationVerifyOrcid.vue` `PkpButton t('acceptInvitation.verifyOrcid')` → `window.open(props.orcidOAuthUrl, '_blank', …)` | ✅ |
| AFFU-131 | Skip ORCID button | same file, `PkpButton t('acceptInvitation.skipVerifyOrcid')` → `store.openStep(store.steps[1 + store.currentStepIndex].id)` (bypasses the refine PUT) | ✅ |
| AFFU-132 | Username field | `AcceptInvitationUserAccountDetails.vue` `FieldText name="username"`, `:is-required="true"` | ✅ |
| AFFU-133 | Password field | same file, `FieldText name="password" input-type="password"`, description uses `pkp.const.MIN_PASSWORD_LENGTH` | ✅ |
| AFFU-134 | Privacy-consent checkbox | same file, `FieldOptions name="privacyStatement" type="checkbox"`; label links `useUrl('about/privacy')` | ✅ |
| AFFU-135 | User-details form group | `AcceptInvitationUserDetailsForms.vue` `PkpForm` bound to server `AcceptUserDetailsForm` | ✅ |
| AFFU-136 | Review → Edit user details | `AcceptInvitationReview.vue` `PkpButton t('common.edit')` → `store.openStep('userDetails')`, in the `v-else` of `v-if="store.userId != null"` | ✅ |
| AFFU-137 | Review read-only summaries | `AcceptInvitationReview.vue` + `AcceptInvitationUserRoles.vue` + `AcceptInvitationFormDisplay.vue` / `AcceptInvitationFormDisplayItemBasic.vue` / `AcceptInvitationFieldTextDisplay.vue` / `AcceptInvitationFieldSelectDisplay.vue` | ✅ (atlas writes `FormDisplay*/Field*Display` as a glob; the concrete set is these four files) |
| AFFU-206 | Public invitation-URL landing | `PKP\pages\invitation\InvitationHandler` ops `accept` / `decline` / `confirmDecline` | ✅ |

### ROUTE / VUE / API / MAIL / SET / JOB

| Atom | What it is | Symbol | Resolves |
|---|---|---|---|
| ROUTE-013 | Invitation UI bootstrap | `PKP\pages\invitation\InitializeInvitationUIHandler`; ops `create`, `edit`; dispatched by `lib/pkp/pages/invitation/index.php` | ✅ |
| ROUTE-014 | Invitation acceptance flow | `PKP\pages\invitation\InvitationHandler`; ops `accept`, `decline`, `confirmDecline` | ✅ |
| VUE-001 | Accept-invitation page | `pages/acceptInvitation/AcceptInvitationPage.vue`; registered as `VueRegistry.registerComponent('AcceptInvitationPage', …)` in `lib/pkp/js/load.js` | ✅ |
| VUE-011 | Send-invitation wizard | `pages/userInvitation/UserInvitationPage.vue`; registered as `'UserInvitationPage'` in `lib/pkp/js/load.js` | ✅ |
| VUE-052 | Pending-invitations table | `managers/UserInvitationManager/UserInvitationManager.vue`; registered as `'UserInvitationManager'` in `lib/pkp/js/load.js`; mounted by `lib/pkp/templates/management/access.tpl` | ✅ |
| API-024 | Invitations lifecycle API | `PKP\API\v1\invitations\InvitationController`; op set as listed in the atlas — verified complete against `getGroupRoutes()` | ✅ |
| MAIL-055 | Invitation email | `PKP\mail\mailables\UserRoleAssignmentInvitationNotify`, template key `USER_ROLE_ASSIGNMENT_INVITATION`. Badge `ojs omp` is **correct** — see §2 (OPS `APP\mail\Repository::map()` omits it) | ✅ |
| SET-064 | `[invitations]` config | `config.TEMPLATE.inc.php` section `[invitations]`, key `expiration_days` (default 3). Read by exactly one symbol: `Invitation::getExpiryDays()` → `Config::getVar('invitations','expiration_days', Invitation::DEFAULT_EXPIRY_DAYS)` | ✅ |
| JOB-013 | Queued cleanup job | `PKP\jobs\invitations\RemoveExpiredInvitationsJob` (`InvitationModel::expired()->delete()`) | ✅ |
| JOB-051 | Scheduled dispatcher | `PKP\task\RemoveExpiredInvitations`, registered `->daily()` in `PKPScheduler::registerSchedules()` | ✅ |

**No U6 atom pointer failed to resolve.** Two pointer-text inaccuracies noted in §7.

---

## 2. Subclass-chain check (RUNBOOK rule 8), per app

States: **EMPTY** = no app subclass, shared class used directly (positive evidence of shared
behavior) · **OVERRIDE** = app subclass exists and changes something on this path ·
**MISSING** = a sibling app overrides and this one does not.

Baseline fact that frames the whole table: **neither OJS, OMP nor OPS contains a
`classes/invitation/` directory, a `pages/invitation/` directory, a
`classes/components/forms/invitation/` directory, a `templates/invitation/` directory, or any
`*Invite.php`.** `InvitationServiceProvider::discoverInvitationsWithin()` scans
`lib/pkp/classes/invitation/invitations`, `<app>/classes/invitation/invitations` and
`<app>/plugins` — the app-side arm of that extension point is **unused in all three apps**.

| Load-bearing shared class | OJS | OMP | OPS |
|---|---|---|---|
| `PKP\invitation\core\Invitation` (abstract base) | EMPTY | EMPTY | EMPTY |
| `PKP\invitation\invitations\userRoleAssignment\UserRoleAssignmentInvite` | EMPTY | EMPTY | EMPTY |
| `…\payload\UserRoleAssignmentInvitePayload` | EMPTY | EMPTY | EMPTY |
| `PKP\invitation\models\InvitationModel` | EMPTY | EMPTY | EMPTY |
| `PKP\invitation\repositories\Repository` (`Repo::invitation()`) | EMPTY | EMPTY | EMPTY |
| `PKP\invitation\core\InvitationFactory` / `PKP\core\InvitationServiceProvider` | EMPTY | EMPTY | EMPTY |
| `PKP\pages\invitation\InvitationHandler` (ROUTE-014) | EMPTY | EMPTY | EMPTY |
| `PKP\pages\invitation\InitializeInvitationUIHandler` (ROUTE-013) | EMPTY | EMPTY | EMPTY |
| `PKP\API\v1\invitations\InvitationController` (API-024) | EMPTY — `api/v1/invitations/index.php` is a byte-identical 483-byte stub returning `new APIHandler(new InvitationController())` | EMPTY (identical stub) | EMPTY (identical stub) |
| `…\handlers\UserRoleAssignmentInviteUIController` | EMPTY | EMPTY | EMPTY |
| `…\handlers\UserRoleAssignmentInviteRedirectController` | EMPTY | EMPTY | EMPTY |
| `…\handlers\api\UserRoleAssignmentCreateController` | EMPTY | EMPTY | EMPTY |
| `…\handlers\api\UserRoleAssignmentReceiveController` | EMPTY | EMPTY | EMPTY |
| `PKP\invitation\stepTypes\SendInvitationStep` / `AcceptInvitationStep` / `InvitationStepTypes` | EMPTY | EMPTY | EMPTY |
| `PKP\components\forms\invitation\UserDetailsForm` | EMPTY | EMPTY | EMPTY |
| `PKP\components\forms\invitation\AcceptUserDetailsForm` | EMPTY | EMPTY | EMPTY |
| All 10 rules in `…\userRoleAssignment\rules\` | EMPTY | EMPTY | EMPTY |
| `PKP\mail\mailables\UserRoleAssignmentInvitationNotify` (MAIL-055) | EMPTY | EMPTY | EMPTY |
| **`PKP\mail\Repository::map()` (mailable catalogue)** | OVERRIDE, invitation mailable **retained** (`APP\mail\Repository::map()` re-declares the full list incl. `UserRoleAssignmentInvitationNotify`) | OVERRIDE, retained | **OVERRIDE that DROPS it** — `ops-main/classes/mail/Repository.php::map()` re-declares a short list and omits `UserRoleAssignmentInvitationNotify` (comment: "OPS uses distinct mailables from OJS and OMP") |
| `PKP\jobs\invitations\RemoveExpiredInvitationsJob` | EMPTY | EMPTY | EMPTY |
| `PKP\task\RemoveExpiredInvitations` | EMPTY | EMPTY | EMPTY |
| `PKP\scheduledTask\PKPScheduler` (registers JOB-051) | OVERRIDE, additive — `APP\…\Scheduler::registerSchedules()` calls `parent::registerSchedules()` | OVERRIDE, additive | OVERRIDE, additive |
| Vue: `UserInvitationManager.vue`, `UserInvitationPage.vue`, `AcceptInvitationPage.vue` + all sibling components/stores | EMPTY — `lib/ui-library` submodule, file lists identical across the three repos | EMPTY | EMPTY |
| Registration in `lib/pkp/js/load.js` | EMPTY (shared) | EMPTY | EMPTY |
| Templates `invitation/{accept,decline,userInvitation,invitationUnavailable}.tpl` + `management/access.tpl` + `management/accessUsers.tpl` | EMPTY (no `app:` override) | EMPTY | EMPTY |
| **`PKP\pages\management\ManagementHandler` (host of the invitation screens; ROUTE-017)** | OVERRIDE — `APP\pages\management\SettingsHandler` grants **`SITE_ADMIN → ['access','settings']`** + `MANAGER → ['settings']`; overrides `workflow`, `distribution`, `getEmailFromFilters`, `getEmailToFilters` | OVERRIDE — grants **`SITE_ADMIN → ['access']`** only + `MANAGER → ['settings']`; overrides `workflow`, `distribution` | OVERRIDE — grants **`SITE_ADMIN → ['access']`** only + `MANAGER → ['settings']`; overrides `workflow`, `distribution`, `getEmailGroupFilters`, `getEmailFromFilters`, `getEmailToFilters`, `getEmailSetupForm`, `getInformationForm` |

### The two divergences the chain check surfaces

**D-A — OPS drops the invitation mailable from the app's mailable catalogue.**
`ops-main/classes/mail/Repository.php::map()` overrides the shared list and omits
`UserRoleAssignmentInvitationNotify`. Code-level consequences, stated as code facts only:
- `ManagementHandler::manageEmails()` builds its list from `Repo::mailable()->getMany($context, …)`,
  which is `map()` filtered — so on OPS the invitation template has no row on the Emails
  management screen. **P1 needs a probe.**
- `EmailTemplate` schema mapping (`PKP\emailTemplate\maps\Schema::mapByProperties`) resolves
  `$mailableClass` via `Repo::mailable()->get(key)`; a null mailable forces
  `assignedUserGroupIds => []`. **P2 needs a probe.**
- `Repo::emailTemplate()` validation of `alternateTo` requires the key to be in
  `Repo::mailable()->getMany($context)` — so an OPS alternate template for this key would not validate. **P3 needs a probe.**
- Paths that do **not** consult `map()` and therefore look unaffected in code:
  `UserRoleAssignmentInvite::getMailable()` (goes straight to
  `Repo::emailTemplate()->getByKey($contextId, 'USER_ROLE_ASSIGNMENT_INVITATION')`) and
  `SendInvitationStep::invitationInvitedEmail()` (constructs the mailable directly). All three
  apps' `registry/emailTemplates.xml` install the `USER_ROLE_ASSIGNMENT_INVITATION` row, so the
  template row itself exists on OPS. Whether the wizard's composer step and the actual send work on
  OPS is **P4 needs a probe**.

**D-B — the site-admin role assignment on the host handler differs.**
OJS `SettingsHandler` assigns `SITE_ADMIN` to both `access` and `settings`; OMP and OPS assign it
only to `access`. Both invitation screens sit behind the `settings` op
(`settings/access`, `settings/user/{id}`), and `ManagementHandler::authorize()` additionally
applies `CanAccessSettingsPolicy` (admin OR manager with `permitSettings`) to the `settings` op.
So the *code* reads: on OMP/OPS a site administrator has no role assignment for the `settings`
op that hosts the invitations table, while on OJS they do. **P5 needs a probe** (cross-app control
per RUNBOOK rule 4).

### Missing-override check
No app overrides `UserRoleAssignmentInvite`, its controllers, the payload, the rules, the steps,
the forms, the handlers or the templates — so there is **no MISSING-override case inside the
invitation machinery itself**. The only "one app diverges, the others don't" cases are D-A
(OPS-only) and D-B (OJS-only), both on classes the invitation path *depends on* rather than owns.

---

## 3. Secondary seams

### 3a. `isOJS()/isOMP()/isOPS()` and app-type branches
**Zero.** Grep over `lib/pkp/classes/invitation/`, `lib/pkp/pages/invitation/`,
`lib/pkp/api/v1/invitations/`, `lib/pkp/classes/components/forms/invitation/`,
`UserRoleAssignmentInvitationNotify.php`, and the three ui-library trees finds no
`isOJS()/isOMP()/isOPS()`, no `Application::get()->getName()` comparison, no `pkp.appKey`, no
`$page.` app branch. The only `pkp.*` globals in the Vue are `pkp.const.ROLE_ID_REVIEWER`,
`pkp.const.MIN_PASSWORD_LENGTH`, `pkp.eventBus.$on('addOrcidInvitationData', …)` and
`pkp.context.*` via `useUrl`. All app-flavoured wording is locale strings, resolved server-side.

Indirect app-shaped behavior does exist, via shared code reading per-app data:
`Application::getContextDAO()` (mailable locale), `OrcidManager::isEnabled($context)` (gates the
ORCID step), `$context->getSupportedFormLocales()` / site primary locale (form locales),
`UserGroup::withContextIds([$context->getId()])` (the role menu).

### 3b. Registry / seed differences

**`registry/userGroups.xml`** — lib/pkp has none (only `dtd/userGroups.dtd`); each app declares its
own roster, and this roster is exactly what
`SendInvitationStep::getAllUserGroups()` feeds into the wizard's role picker.

- **OJS (18 groups)**: manager, editor, productionEditor · sectionEditor, guestEditor ·
  copyeditor, designer, funding, indexer, layoutEditor, marketing, proofreader ·
  author, translator · externalReviewer · reader · subscriptionManager · editorialBoardMember.
- **OMP (18 groups)**: same minus `guestEditor` and `subscriptionManager`, plus `volumeEditor`,
  `chapterAuthor`, `internalReviewer`; stage lists include stage 2 (internal review).
- **OPS (5 groups only)**: manager, sectionEditor, author, reader, editorialBoardMember.
  **No reviewer group at all**, no assistant/production roles, no translator, no
  subscriptionManager. OPS `manager` carries `stages="5"`; OPS `author` is the only seeded author
  with `permitMetadataEdit`.
- Masthead-eligible seeded groups: OJS = editor, sectionEditor, externalReviewer,
  editorialBoardMember; OMP = same; **OPS = sectionEditor, editorialBoardMember only**.
- Consequence to check at spec time, not resolvable by code: `UserInvitationUserGroupsTable.vue`
  computes `reviewerUserGroupIds` from `roleId === pkp.const.ROLE_ID_REVIEWER` and forces
  `masthead = true` for those rows. On OPS that branch has no seeded group to match.
  **P6 needs a probe.**

**`registry/emailTemplates.xml`** — lib/pkp has none (only the DTD); each app is the sole
declaration site, so nothing is "overridden".
- `USER_ROLE_ASSIGNMENT_INVITATION` (name `mailable.userRoleAssignmentInvitationNotify.name`,
  subject/body `emails.userRoleAssignmentInvitationNotify.subject`/`.body`) — **present and
  identical in all three apps.**
- Adjacent-but-out-of-scope keys, for boundary awareness: `CHANGE_EMAIL`, `USER_VALIDATE_CONTEXT`,
  `USER_VALIDATE_SITE`, `USER_REGISTER` present in all three; `REVIEWER_REGISTER` absent in OPS;
  `USER_ROLE_MASTHEAD_UPDATE` present in OJS only (lib/pkp ships its locale strings for all three —
  dangling on OMP/OPS; belongs to the roles/masthead feature, not U6).
- There is **no** `REGISTRATION_ACCESS*` key and **no** `REVIEWER_*ACCESS*` key anywhere:
  `RegistrationAccessInvite` and `ReviewerAccessInvite` have no dedicated email template.
- No invitation entry carries `alternateTo` or `isUnrestricted`. The DTD has no `mailables`/`group`
  element — grouping (`GROUP_OTHER`, `FROM_SYSTEM`, the six `toRoleIds`) lives in the mailable
  class, not the XML.
- Shared migrations that seed/patch the key (no app variants):
  `InstallEmailTemplates`, `I11603_AddMissingUserRoleAssignmentInvitationEmail`,
  `I12792_FixInvitationEmailButtons`, `I12608_RestoreActiveReviewerInvitations`.

**Locale files** — this is where essentially all per-app U6 variance lives.
- `lib/pkp/locale/<lang>/invitation.po` exists for 22 languages. OJS ships `invitation.po` for 20
  languages; OMP and OPS ship `en` only.
- All three apps' `locale/en/invitation.po` declare the **same 18 msgids**. Seventeen are app-only
  additions (lib/pkp has no such key); exactly **one is a true override of a lib/pkp string**:
  `userInvitation.searchUser.stepDescription`, overridden identically-in-shape by all three apps
  with journal/press/server substituted.
- The 18 app keys are the vocabulary seam: `invitation.role.masthead`,
  `userInvitation.roleTable.journalMasthead`, `userInvitation.search.userFound` / `.userNotFound`,
  `invitation.wizard.pageTitleDescription`, `userInvitation.sendMail.stepDescription`,
  `userInvitation.enterDetails.stepDescription`, `userInvitation.modal.message`,
  `acceptInvitation.accountDetails.stepName/.stepLabel/.stepDescription`,
  `acceptInvitation.detailsReview.nextButtonLabel/.stepDescription`,
  `acceptInvitation.modal.title/.message`, `acceptInvitation.verifyOrcid.stepDescription`,
  `invitation.orcid.acceptInvitation.message`, `userInvitation.searchUser.stepDescription`.
  Per-app difference is Journal/Press/Server and OJS/OMP/OPS product-name substitution only.
- OMP and OPS mark `invitation.orcid.acceptInvitation.message` `#, fuzzy`; OJS does not.
- All invitation **email** strings (`emails.userRoleAssignmentInvitationNotify.*`,
  `mailable.userRoleAssignmentInvitationNotify.*`, `emailTemplate.variable.invitation.*`) live
  **only** in `lib/pkp/locale/en/emails.po` — zero app overrides, zero app additions.
- Data defect in `lib/pkp/locale/en/invitation.po`: 7 msgids are declared twice
  (`invitation.reviewerAccess.validation.error.reviewAssignmentId.notExisting`,
  `invitation.api.error.invitationCantBeCanceled`,
  `invitation.api.error.initialization.noUserIdAndEmailTogether`,
  `invitation.userRoleAssignment.error.update.prohibitedForExistingUser`,
  `…prohibitedForNonExistingUser`, `invitation.userRoleAssignment.userGroup.startDate.mustBeAfterToday`,
  `invitation.validation.error.propertyProhibited`).
- **Keys referenced by the Vue with no `msgid` in lib/pkp or in any app locale**:
  `invitation.wizard.completeSteps` (the `Steps` aria label on **both** wizards) and
  `invitation.wizard.errors` (the review-step error notification). **P7 needs a probe.**

### 3c. Config keys
`[invitations] expiration_days = 3` is declared in all three apps' `config.TEMPLATE.inc.php`,
content-identical; lib/pkp has no config template. `omp-main/config.inc.php` (the live dev config)
also carries the section; OJS's and OPS's live `config.inc.php` do not, so they fall back to the
`Invitation::DEFAULT_EXPIRY_DAYS = 3` constant — same effective value.
Single consumer: `Invitation::getExpiryDays()`, called from `Invitation::setExpiryDate()`.
`ReviewerAccessInvite::getExpiryDays()` **overrides** it with `($context->getData('numWeeksPerReview') + 4) * 7` — that is the reviewer-access invite, out of U6 scope (§6).
No app PHP/tpl/js reads the section.

### 3d. Per-app template overrides
**None.** No app repo has a `templates/invitation/` directory, and none has
`templates/management/access.tpl` or `templates/management/accessUsers.tpl`. All five templates on
this path resolve to lib/pkp in all three apps. (App `templates/management/` contents: OJS —
`additionalDistributionTabs.tpl`, `context.tpl`, `dois.tpl`; OMP — `context.tpl`, `dois.tpl`,
`tools/`; OPS — `context.tpl`, `distribution.tpl`, `dois.tpl`.)

Template oddity, code-reading only: `lib/pkp/templates/invitation/acceptInvitation.tpl` and
`userInvitation.tpl` both begin with a stray literal `<?php` line before the Smarty doc-comment and
the `{extends}` tag. **P8 needs a probe** (whether anything renders).

---

## 4. Roles and permissions as the code states them

### 4a. What each entry point admits

| Entry point | Role slots the code admits | Additional policies |
|---|---|---|
| `InitializeInvitationUIHandler` ops `create`, `edit` (ROUTE-013) | `ROLE_ID_SITE_ADMIN`, `ROLE_ID_MANAGER`, `ROLE_ID_SUB_EDITOR`, `ROLE_ID_ASSISTANT` | `UserRequiredPolicy`, `UserRolesRequiredPolicy`, `ContextAccessPolicy`, then a `PolicySet(COMBINING_PERMIT_OVERRIDES)` of `RoleBasedHandlerOperationPolicy` per assigned role |
| `InvitationController` group routes (`getMany`, `get`, `add`, `populate`, `invite`, `getMailable`, `cancel`) | same four: `SITE_ADMIN`, `MANAGER`, `SUB_EDITOR`, `ASSISTANT` (via `self::roleAuthorizer([...])`) plus middleware `has.user`, `has.context` | `UserRoleAssignmentCreateController::authorize()` adds `UserRolesRequiredPolicy` + `ContextAccessPolicy` |
| `InvitationController` key routes (`receive`, `finalize`, `refine`, `decline`) | **outside the role group** — declared after the `Route::middleware(...)->group(...)` block. `$publicActions` triggers `setEnforceRestrictedSite(false)` + `PublicAccessPolicy` | `UserRoleAssignmentReceiveController::authorize()`: if no matching user → `AnonymousUserPolicy`; if a user exists and nobody is signed in → `Validation::registerUserSession($user)`; if a *different* user is signed in → `AuthorizationPolicy()` (deny) + `UserRequiredPolicy` |
| `InvitationHandler` ops `accept`, `decline`, `confirmDecline` (ROUTE-014) | **no `addRoleAssignment` and no `authorize()` override** → `PKPHandler::authorize()` with the page-router blacklist (`setDecisionIfNoPolicyApplies(PERMIT)`), so only `RestrictedSiteAccessPolicy`/`AllowedHostsPolicy` apply. Gate is possession of a valid `id`+`key` (`Repo::invitation()->getByIdAndKey()` → `notHandled()->notExpired()` + `password_verify`) | `confirmDecline` additionally requires `$request->isPost()` and `$request->checkCSRF()` |
| **Screen that mounts the invitations table** (`management/access.tpl` → `<user-invitation-manager>`) | `APP\pages\management\SettingsHandler`: OJS `SITE_ADMIN → ['access','settings']`, `MANAGER → ['settings']`; OMP/OPS `SITE_ADMIN → ['access']`, `MANAGER → ['settings']` | `ManagementHandler::authorize()`: `ContextAccessPolicy` always; **`CanAccessSettingsPolicy`** when op is `settings` and args are not `['announcements']`/`['userComments']`. `CanAccessSettingsPolicy` permits site admin, or manager whose user group has `permitSettings` |

### 4b. The genuine ambiguity — say it plainly
**The invitation handler and the invitation API admit four roles (site admin, manager, sub-editor,
assistant), but the only screen that renders the invitation entry points is gated to site admin +
manager-with-`permitSettings`.** A sub-editor or assistant has a role assignment on
`invitation/create/userRoleAssignment` and on the whole create-side API, yet no code path renders
the "Invite to a role" button to them, and `UserInvitationPageStore` redirects to
`management/settings/access` on both success and cancel — a screen their role assignment does not
cover. Code cannot settle what actually happens on a typed URL. **P9 needs a probe** (as sub-editor
and as assistant, in each app: visit `/{context}/invitation/create/userRoleAssignment` directly, and
visit `/{context}/management/settings/access`; record what each screen shows).

Related, same class: **P5** (site admin on OMP/OPS vs OJS, `settings` op) and
**P10** — manager whose user group lacks `permitSettings`: role assignment for `settings` exists,
`CanAccessSettingsPolicy` denies. Record what that manager sees on `settings/access`.

### 4c. Role-slot facts on the payload side (not gates, but role-shaped)
- `UserRoleAssignmentInvitationNotify::$toRoleIds` = `SUB_EDITOR`, `ASSISTANT`, `AUTHOR`, `READER`,
  `REVIEWER`, `SUBSCRIPTION_MANAGER`; `$fromRoleIds` = `FROM_SYSTEM`; `$groupIds` = `GROUP_OTHER`.
  Note `SUBSCRIPTION_MANAGER` and `REVIEWER` are OJS-only / non-OPS seeded roles (§3b).
- The role menu offered by the wizard is `UserGroup::withContextIds([$context->getId()])` — every
  user group in the context, unfiltered by role id.
- `UserGroupExistsRule` checks only `UserGroup::find($value)` — existence, **not** membership of the
  request's context. `AddUserGroupRule` rejects a group the invitee already holds actively.
  Whether the wizard can be walked into offering a foreign-context group is **P11 needs a probe**
  (screen-level: does the role dropdown ever list a group from another journal/press/server?).

---

## 5. Reachability inventory

### 5a. Page URLs (`/index.php/{contextPath}/{page}/{op}/{args}`)

| # | URL | Handler · op | What it renders | Gate as the code states it |
|---|---|---|---|---|
| R1 | `/{ctx}/management/settings/access` | `SettingsHandler` op `settings`, arg `access` → `ManagementHandler::access()` | Users & Roles page; `users` tab hosts `<user-invitation-manager>` above `<user-access-manager>` | role assignment for `settings` (OJS: admin+manager; OMP/OPS: manager) **and** `CanAccessSettingsPolicy` |
| R2 | `/{ctx}/management/access` | `SettingsHandler` op `access` → `ManagementHandler::access()` | same page | role assignment for `access` = `SITE_ADMIN` only, all three apps. **No `CanAccessSettingsPolicy`** (op ≠ `settings`). **P12 needs a probe** — this is a second, differently-gated door to the same screen |
| R3 | `/{ctx}/management/settings/user/{userId}` | `SettingsHandler` op `settings`, arg `user` → `ManagementHandler::editUser()` | Send-invitation wizard in `invitationMode = 'editUser'` (search step omitted; user's current roles preloaded) | same as R1. 404 if `$userId` empty; `NotFoundHttpException` if the user does not exist |
| R4 | `/{ctx}/invitation/create/{invitationType}` (UI uses `userRoleAssignment`) | `InitializeInvitationUIHandler::create` | Send-invitation wizard, `invitationMode = 'create'` | admin / manager / sub-editor / assistant (§4a). `NotFoundHttpException` on empty args or a numeric first arg |
| R5 | `/{ctx}/invitation/edit/{invitationId}` | `InitializeInvitationUIHandler::edit` | Send-invitation wizard, `invitationMode = 'edit'` | same four roles. `NotFoundHttpException` on empty args, non-numeric arg, or unknown id |
| R6 | `/{ctx}/invitation/accept?id={id}&key={key}` | `InvitationHandler::accept` | `acceptInvitation.tpl` → `<accept-invitation-page>`; server pre-computes `steps` via `AcceptInvitationStep::getSteps()`. Calls `changeInvitationUserIdUsingUserEmail()` first | key possession; `getByIdAndKey` requires not-handled **and** not-expired. Falls to R8 or 404 |
| R7 | `/{ctx}/invitation/decline?id={id}&key={key}` | `InvitationHandler::decline` | `declineInvitation.tpl` — a confirm page with a POST form to `$declineUrl` | key possession |
| R7b | `/{ctx}/invitation/confirmDecline` (POST) | `InvitationHandler::confirmDecline` | marks declined, redirects to `{ctx}/login` | POST-only (`MethodNotAllowedHttpException` otherwise) + `checkCSRF` (403 otherwise) + key possession + status must be `PENDING` (`GoneHttpException` otherwise) |
| R8 | (same URLs as R6/R7, spent or expired key) | `InvitationHandler::displayInvitationNotAvailablePage()` | `invitationUnavailable.tpl` — title/description + Login and Register links | reached when `getById($id)` exists **and** `password_verify($key, keyHash)` passes but the invitation is handled/expired; otherwise `NotFoundHttpException` |

Page targets the flow redirects to: `management/settings/access` (wizard success + cancel),
`submissions` (accept success, accept cancel, and the not-anonymous dialog's dismissal),
`login/signOut` (not-anonymous dialog action), `{ctx}/login` (confirmDecline), `about/privacy`
(link href on the consent checkbox).

### 5b. API endpoints (`/{ctx}/api/v1/invitations/...`) — `InvitationController::getGroupRoutes()`

Role-gated group (admin/manager/sub-editor/assistant; `has.user`, `has.context`):
`GET {type}` · `GET {invitationId}` · `POST add/{type}` · `PUT {invitationId}/populate` ·
`PUT {invitationId}/invite` · `GET {invitationId}/getMailable` · `PUT {invitationId}/cancel`.
Outside the group: `GET {invitationId}/key/{key}` · `PUT {invitationId}/key/{key}/finalize` ·
`PUT {invitationId}/key/{key}/refine` · `PUT {invitationId}/key/{key}/decline`.

Status preconditions asserted in `InvitationController::authorize()`:
`$actionsInvite` (`get`, `populate`, `invite`, `getMailable`) require status `INITIALIZED`;
`$actionsReceive` (`receive`, `finalize`, `refine`, `decline`, `cancel`) require status `PENDING`.

Adjacent endpoints the invitation screens call: `GET users?searchPhrase=&status=all` (search step),
`GET users/{id}` (reload current user), `PUT users/{userId}/endRole/{roleId}`,
`PUT users/{userId}/masthead/{userUserGroupId}` (role table), `emailTemplates` (composer). These
belong to U53/U54/U56; U6 only calls them.

### 5c. Affordances, by screen, with the condition the code wraps them in

**Screen A — Users & Roles › Users tab, invitations table (`UserInvitationManager.vue`)**

| Affordance | Trigger | Condition (verbatim) |
|---|---|---|
| `PkpButton` `t('invitation.inviteToRole.btn')` | redirect `invitation/create/userRoleAssignment` | *(none — always rendered)* |
| Table heading `t('invitation.header')` + item count | display | *(none)* |
| Row `DropdownActions` `:label="t('invitation.management.options')"` `button-variant="ellipsis"` | `store.handleInvitationAction(name, invitation)` | *(none — every row)* |
| Dropdown item `{label: t('common.edit'), name: 'editInvite', icon: 'Edit'}` | edit dialog → `invitation/edit/{id}` | *(none)* |
| Dropdown item `{label: t('invitation.cancelInvite.actionName'), icon: 'Cancel', name: 'cancelInvite', isWarnable: true}` | cancel dialog → `PUT invitations/{id}/cancel` | *(none)* |
| ORCID `Icon icon="Orcid"` in name cell | display | `v-if="invitation.existingUser?.orcid \|\| invitation.newUser?.orcid"` |
| `Pagination :show-adjacent-pages="3"` | `store.setCurrentPage` | wrapper `v-if="store.invitationsPagination.itemCount > 0"`; `:is-loading="store.isInvitationLoading"` |
| Edit dialog actions | `t('userInvitation.edit.title')` (primary) → redirect; `t('common.cancel')` (`isWarnable`) → close | dialog `title: t('userInvitation.edit.title')`, `message: t('userInvitation.edit.message')`, `modalStyle: 'primary'` |
| Cancel dialog actions | `t('invitation.cancelInvite.title')` (`isWarnable`) → PUT cancel + refetch; `t('common.cancel')` → close | dialog body = `UserInvitationManagerCancelInvitationDialogBody.vue` (read-only email/role/status/affiliation list), `modalStyle: 'negative'` |

Columns: `invitation.tableHeader.name`, `about.contact.email`, `invitation.header`, `common.status`,
`user.affiliation`, sr-only `common.moreActions`. Status cell is unconditionally
`t('userInvitation.status.invited', {date: …createdAt})` — the Vue has no other status branch.
Page size is hard-coded `countPerPage = ref(5)`.

**Entry from the neighbouring users table (owned by U53, rides into U6)**:
`useUserAccessManagerConfig.js` action `{label: t('common.edit'), name: Actions.USER_ACCESS_EDIT}`
→ `UserAccessManagerStore.editUser` → `useUrl('management/settings/user/${user.id}').redirectToPage()`
(= R3). This is AFFM-103, claimed by U53.

**Screen B — Send-invitation wizard (`UserInvitationPage.vue`)**

| Affordance | Trigger | Condition (verbatim) |
|---|---|---|
| `Steps` step tabs | `@step:open="store.openStep"` | `Steps` block `v-if="store.steps.length"`; each step is a clickable `<button>` only under `v-if="startedSteps.includes(step.id)"` (else an inert `<span>`) |
| `PkpButton t('common.cancel') :is-warnable="true"` | `store.cancel` | *(none)* |
| `PkpButton t('common.back')` | `store.previousStep` | `v-if="!store.isOnFirstStep"` |
| Primary `PkpButton` labelled `store.currentStep.nextButtonLabel` | `store.nextStep` | `:is-disabled="store.isSubmitting"` |
| `Notification type="warning" t('invitation.wizard.errors')` | display | `template v-if="step.type === 'review'"` + `v-if="Object.keys(store.errors).length > 0"` — **no send-step has `type: 'review'`** (steps are `emptySection`/`form`/`email`), so this block is unreachable as configured. **P13 needs a probe** |
| Step body `<component :is="userInvitationComponents[section.sectionComponent]">` | — | `v-if="store.currentStep.id === step.id"` |
| Inline error line `{{ store.errors.error }}`; search message `{{ store.userSearch.message }}` `:class="store.userSearch.class"` | display | *(none)* |
| *Search step*: `FieldText name="search"` `t('userInvitation.searchField')` | local field; the search runs from Continue via `store.registerActionForStepId('searchUser', searchUser)` | *(none)* |
| *Details step*: disabled banner `t('userInvitation.user.disableTitle')`/`.disableMessage` | display | `v-if="store.invitationPayload.disabled"` |
| *Details step*: `FormErrorSummary :errors="store.errors"` | display | `v-if="Object.keys(store.errors).length"` (inside the new-user branch) |
| *Details step*: `PkpForm v-bind="userForm" :show-error-footer="false"` (server `UserDetailsForm`: `inviteeEmail` required, `orcid` FieldHTML only when `OrcidManager::isEnabled()`, `givenName`/`familyName` multilingual optional) | `store.updatePayload(field.name, field.value, false)` | `v-if="store.invitationPayload.userId === null"` |
| *Details step*: read-only email/orcid/name/affiliation block | display | `v-if="store.invitationPayload.userId !== null"`; ORCID icon `v-if="store.invitationPayload.orcidIsVerified"` |
| *Details step*: `ShowMore t('common.viewMoreDetails')` → `UserInvitationExtendedMetaData` | expand | `v-if="store.invitationMode != 'create'"` |
| *Role table*: existing-role masthead `FieldSelect name="masthead"` (`invitation.masthead.show` / `.hidden`) | confirm dialog `user.masthead.update.title` → `PUT users/{userId}/masthead/{userUserGroupId}` + reload | `v-else` of `v-if="reviewerUserGroupIds.includes(currentUserGroup.id)"` (reviewer rows show a static `t('invitation.masthead.show')`) |
| *Role table*: existing-role `PkpButton :is-warnable t('invitation.role.removeRole.button')` | `removeUserGroup` → dialog → `PUT users/{userId}/endRole/{roleId}` | cell `v-if="!currentUserGroup.dateEnd"`; the `v-else` cell shows static `t('invitation.removeRoles')`. Guarded by `if (numberOfActiveRoles.value <= 1)` → `oneRoleRemain` dialog (`user.removeRole.roleRemainMessage`, single `common.close` action) |
| *Role table*: new-role `FieldSelect name="userGroupId"` `t('invitation.role.selectRole')` required | `updateUserGroup` | whole block `template v-if="!store.invitationPayload.disabled"` |
| *Role table*: new-role `FieldText name="dateStart" input-type="date"` required | `updateUserGroup` | same |
| *Role table*: new-role masthead `FieldSelect` | `updateUserGroup` | `v-else` of `v-if="reviewerUserGroupIds.includes(userGroupToAdd.userGroupId)"`; selecting a reviewer group auto-sets `masthead = true` |
| *Role table*: new-role `PkpButton :is-warnable t('invitation.role.removeRole.button')` | `removeInvitedUserGroup(index)` | `v-if="store.invitationPayload.userGroupsToAdd.length > 1 \|\| isUserGroupsToAddPopulated()"` |
| *Role table*: `#bottom-controls` `PkpButton t('invitation.role.addRole.button')` | `addUserGroup()` | `:is-disabled="store.invitationPayload.disabled"` |
| *Email step*: `Composer` (recipients, CC/BCC, template picker, insert-content, locale switch) | `@set` → `store.updatePayload('emailComposer', …, false)` | `:can-change-recipients="props.email.canChangeRecipients"` (server-provided) |

Continue-button enablement (`store.isSubmitting`): initialised
`invitationMode === 'editUser' ? true : invitationPayload.disabled`; a deep watcher resets it to
`invitationPayload.disabled` and then forces `true` `if (invitationPayload.userGroupsToAdd.length === 0)`.
**P14 needs a probe** — in `editUser` mode the primary button starts disabled by construction.

Client-side validation is thin: empty search → `store.errors.error = t('invitation.searchForm.emptyError')`
(blocks Continue); an email regex decides whether an unmatched search term becomes `inviteeEmail`.
Everything else is server `validationError.errors` (dot-notation keys like
`userGroupsToAdd.0.userGroupId`). `cancel()` uses a native `confirm(t('form.dataHasChanged'))` in
`editUser` mode and the styled dialog otherwise.

**Screen C — Accept-invitation wizard (`AcceptInvitationPage.vue`)**

| Affordance | Trigger | Condition (verbatim) |
|---|---|---|
| `Steps` step tabs | `@step:open="store.openStep"` | `div v-if="store.steps.length"`; same `startedSteps` gating |
| `PkpButton t('common.cancel') :is-warnable="true"` | `store.cancel` → dialog | *(none)* |
| `PkpButton t('common.back')` | `store.previousStep` | `v-if="!store.isOnFirstStep"` |
| Primary `PkpButton` labelled `store.stepButtonTitle` | `store.nextStep` | `v-if="store.currentStep.id !== 'verifyOrcid'"` — **no `:is-disabled` binding**, gating is post-click only |
| `Notification type="warning" t('invitation.wizard.errors')` | display | `template v-if="step.type === 'review'"` + `v-if="Object.keys(store.errors).length > 0"` (here `userCreateReview` *is* `type: 'review'`, so reachable) |
| Step body `<component :is="acceptInvitationComponents[…]">` | — | *(no per-step `v-if` — unlike the send wizard, all step bodies render)* |
| `PkpButton t('acceptInvitation.verifyOrcid')` | `window.open(props.orcidOAuthUrl, '_blank', 'toolbar=no, scrollbars=yes, width=540, height=700, …')` | *(none)* |
| `PkpButton t('acceptInvitation.skipVerifyOrcid')` | `store.openStep(store.steps[1 + store.currentStepIndex].id)` — skips the refine PUT | *(none)* |
| `FieldText name="username"` required | `store.updateAcceptInvitationPayload` | *(none)* |
| `FieldText name="password" input-type="password"` required | same | *(none)* |
| `FieldOptions name="privacyStatement" type="checkbox"` required; label links `about/privacy` | same | *(none)* |
| `PkpForm v-bind="userForm"` (server `AcceptUserDetailsForm`: `givenName` required, `familyName` optional, `affiliation` optional, `userCountry` required select) | `store.updateAcceptInvitationPayload` | *(none)* |
| Review: account-details block | display | `v-if="store.userId === null"` |
| Review: existing-user ORCID block | display | `v-if="store.userId != null"`; icon `v-if="userOrcidData.orcid && userOrcidData.orcidIsVerified"` |
| Review: `PkpButton t('common.edit')` | `store.openStep('userDetails')` | inside the `v-else` of `v-if="store.userId != null"` (new users only) |
| Review: `AcceptInvitationFormDisplay` | display | `v-if="store.userId === null"` |
| Review: `AcceptInvitationUserRoles` roles table | display | *(none)* — **read-only, no per-role control in the accept wizard** |

Dialogs: not-anonymous (`acceptInvitation.authorization.shouldBeAnonymous` / `.message`,
`modalStyle: 'negative'`; action `t('user.logOut')` → `login/signOut`; dismissal → `submissions`),
success (`acceptInvitation.modal.title`/`.message`, `modalStyle: 'success'`; action
`acceptInvitation.modal.button` → `submissions`), cancel (`acceptInvitation.cancelInvite.title`,
`acceptInvitation.cancel.message`; actions `acceptInvitation.cancelInvite.button` → `submissions`,
`userInvitation.cancel.goBack` → close).

Client-side validation: exactly one rule — `if (!acceptInvitationPayload.privacyStatement && currentStep.id !== 'verifyOrcid')`
→ `errors = {privacyStatement: [t('acceptInvitation.privacyStatement.validation')]}`, request skipped.
Source carries `// FIXME: Privacy statement check blocks ORCID from saving before hand.`

**Screen D — Decline confirm (`declineInvitation.tpl`)**: headings
`invitation.decline.confirm.title` / `.description`; one `fbvElement type="submit"`
`#declineInvitationSubmit` label `invitation.decline.confirm` in a POST form to `$declineUrl` with
`{csrf}`. Not Vue.

**Screen E — Invitation unavailable (`invitationUnavailable.tpl`)**: headings
`invitation.unavailable.title` / `.description`; two `a.pkpButton` links — `$loginUrl` (`user.login`)
and `$registerUrl` (`user.login.registerNewAccount`). Not Vue.

### 5d. Step sets (server-built, prop `steps`)
Both wizards take their step list from the server; neither Vue file nor store hard-codes one. The
JS hard-codes only the section-component whitelist and three literal step ids (`'searchUser'`,
`'verifyOrcid'`, `'userDetails'`).

- **Send** (`SendInvitationStep::getSteps()`): `searchUser` (type `emptySection`,
  `skipInvitationUpdate`) — **added only when `!$invitation && !$user`**, so absent in `edit` and
  `editUser` modes → `userDetails` (type `form`, props carry all context user groups) →
  `userInvited` (type `email`).
- **Accept** (`AcceptInvitationStep::getSteps()`):
  - existing user: `verifyOrcid` only when `!$user->hasVerifiedOrcid() && OrcidManager::isEnabled($context)`, then `userCreateReview`.
  - new user: `verifyOrcid` only when `OrcidManager::isEnabled($context)`, then `userCreate`, `userDetails`, `userCreateReview`.
  - `AcceptInvitationPageStore.receiveInvitation()` calls `await submit()` immediately when
    `steps.value.length === 0` — reachable in code for an existing user with a verified ORCID.

### 5e. Store facts worth carrying into the spec
- Both page stores are declared with `defineComponentStore('userInvitationPage', …)` —
  `UserInvitationPageStore.js` and `AcceptInvitationPageStore.js` use the **same store id string**
  (the manager store uses `'userInvitationsPage'`). They are mounted on different pages.
- Send wizard endpoints: `POST invitations/add/{type}` → `PUT invitations/{id}/populate` →
  `PUT invitations/{id}/invite`. Changing `inviteeEmail` nulls `invitationId`, forcing a fresh record.
- Accept wizard endpoints: `GET invitations/{id}/key/{key}` (on mount) →
  `PUT …/refine` → `PUT …/finalize`. `invitationRequestPayload` only builds a body when
  `currentStep.id === 'verifyOrcid' || !userId` — for an existing user nothing is refined outside the ORCID step.
- `UserRoleAssignmentReceiveController::finalize()` creates the user (username, given/family name,
  email, country, affiliation, ORCID OAuth data, `setInlineHelp(1)`, password), then assigns each
  `userGroupsToAdd` via `Repo::userGroup()->assignUserToGroup(...)` clamping a past `dateStart` to
  today, then `markAs(InvitationStatus::ACCEPTED)`.
- Server-side rules of note: `EmailMustNotExistRule` at FINALIZE, `NoUserGroupChangesRule` +
  `UserMustExistRule` at INVITE and FINALIZE, `AddUserGroupRule` (reject already-held active group),
  password `Password::min($site->getMinPasswordLength())->uncompromised()`,
  `notAccessibleBeforeInvite = [orcid, username, password]`, `notAccessibleAfterInvite = [userGroupsToAdd]`.

---

## 6. Neighbouring machinery to keep OUT

All of these share `Invitation`, `InvitationModel`, `Repo::invitation()`, `InvitationFactory`, the
`invitations` DB table, `[invitations] expiration_days`, and the `invitation/accept|decline|confirmDecline`
page URLs (AFFU-206). They are **not** U6 and their behavior is not documented here.

| Type | Class | Where it diverges from the user-role invitation |
|---|---|---|
| `registrationAccess` | `RegistrationAccessInvite` (+ `RegistrationAccessInviteRedirectController`) | Account validation after registration. Implements `IBackofficeHandleable` + `IMailableUrlUpdateable`, **not** `IApiHandleable` — so it never touches the invitations API. Payload is `EmptyInvitePayload`. `acceptHandle()` renders `frontend/pages/userConfirmActivation.tpl` and redirects; there is no wizard, no Vue, no steps. Owned by U2. |
| `reviewerAccess` | `ReviewerAccessInvite` (+ `ReviewerAccessInviteRedirectController`, `ReviewerAccessInvitePayload`) | Reviewer one-click access. Also `IBackofficeHandleable` + `IMailableUrlUpdateable`, not `IApiHandleable`. **Overrides `getExpiryDays()`** to `($context->getData('numWeeksPerReview') + 4) * 7`, so it ignores `[invitations] expiration_days`. Has `handleAccess()` / `_validateAccessKey()`. **No dedicated email template key** — it rides existing review-request mailables via `PKP\mail\traits\OneClickReviewerAccess`. Redeemed through `Repo::invitation()->getByKey()` (md5 key hash) from each app's `pages/reviewer/ReviewerHandler.php` — present in OJS and OMP, absent in OPS. Owned by U28. |
| `changeProfileEmail` | `ChangeProfileEmailInvite` (+ `ChangeProfileEmailInviteRedirectController`, `ChangeProfileEmailInvitePayload`, mailable `ChangeProfileEmailInvitationNotify`, template key `CHANGE_EMAIL`) | Email-change confirmation from the profile. `IBackofficeHandleable` + `HasMailable` + `ShouldValidate`, not `IApiHandleable`. Builds its own mailable with its own accept/decline URLs; `acceptHandle()` redirects, no wizard. Owned by U3. |

Also adjacent, deliberately excluded: `USER_ROLE_END` / `USER_ROLE_MASTHEAD_UPDATE` mailables and the
`PUT users/{id}/endRole/{roleId}` / `PUT users/{id}/masthead/{id}` endpoints — the invitation role
table *calls* them, but they are U53/U54 machinery.

---

## 7. Atlas corrections proposed (flag only — nothing edited)

1. **MAIL-055 badge `ojs omp` is CORRECT — do not "fix" it.** `ops-main/classes/mail/Repository.php::map()`
   overrides the shared list and omits `UserRoleAssignmentInvitationNotify`. Suggest appending the
   reason to the row so the next reader does not undo it, e.g. "…; absent from OPS
   `APP\mail\Repository::map()` (template key still seeded in OPS `registry/emailTemplates.xml`)."
2. **AFFM-103 pointer text is imprecise.** It reads `USER_ACCESS_EDIT → management/editUser`; the
   actual redirect in `UserAccessManagerStore.editUser` is `management/settings/user/{user.id}`,
   dispatched by `ManagementHandler::editUser()`. Propose correcting the pointer text (atom stays U53's).
3. **U6 is missing from ROUTE-017's rider list.** `ManagementHandler`'s `editUser` op is the
   second entry point into the send-invitation wizard (R3), and its `access` op is what mounts
   VUE-052. U7's Notes line fans ROUTE-017's ops out to U10/U58/U29/U37/U53/U54/U56/U14/U66/U12/U40
   but not U6. Propose adding a U6 rider for the `editUser` op, and a matching Notes line on U6.
4. **AFFU-137's pointer uses globs** (`FormDisplay*/Field*Display`). The concrete set is
   `AcceptInvitationFormDisplay.vue`, `AcceptInvitationFormDisplayItemBasic.vue`,
   `AcceptInvitationFieldTextDisplay.vue`, `AcceptInvitationFieldSelectDisplay.vue` — no other files
   match. Propose spelling them out.
5. **ROUTE-013's role note is accurate but incomplete for spec purposes.** "roles:
   admin/manager/sub-editor/assistant" is the handler's own assignment; the screen that offers the
   route is gated to admin + manager-with-`permitSettings`. Propose a one-clause note pointing at
   the host gate (`CanAccessSettingsPolicy` via `ManagementHandler::authorize()`), since the mismatch
   is the feature's main open question (§4b).
6. **No new atom proposed** for `management/settings/user/{userId}` — it is an op of ROUTE-017, and
   rule "one atom per handler class" holds. It is covered by correction 3.

---

## 8. Consolidated probe list (14 items, all phrased as screen actions)

| # | Item |
|---|---|
| P1 | As a manager in OPS, open Settings › Workflow › Emails and look for the invitation email row; do the same in OJS and OMP as controls. |
| P2 | On the same screen in each app, open the invitation email template and record whether a role/user-group restriction control is offered. |
| P3 | On the same screen in OPS, try to add an alternate template for the invitation email; record what the screen says. |
| P4 | As a manager in OPS, walk the invite wizard to the email step; record whether the composer renders a subject/body, and what the screen says after Invite. Controls in OJS and OMP. |
| P5 | As a site administrator in each app, type `/{ctx}/management/settings/access`; record what appears. |
| P6 | As a manager in OPS, open the invite wizard's role dropdown and record the full option list; note whether any masthead cell renders as a fixed value rather than a selector. Controls in OJS and OMP. |
| P7 | On both wizards in each app, record the step-tab group's accessible name and — after triggering a review-step error — the notification text (checking for raw keys `invitation.wizard.completeSteps` / `invitation.wizard.errors`). |
| P8 | Load the accept-invitation page and the invite wizard and record whether any literal `<?php` text appears on screen. |
| P9 | As a sub-editor, and again as an assistant, in each app: type `/{ctx}/invitation/create/userRoleAssignment`, and separately `/{ctx}/management/settings/access`; record what each screen shows. If the wizard opens, walk it to completion and record where Cancel/Submit lands. |
| P10 | As a manager whose user group does not permit settings access, type `/{ctx}/management/settings/access`; record what appears. |
| P11 | In the invite wizard's role dropdown, record whether any option belongs to a context other than the current one (seed a second journal/press/server first). |
| P12 | As a site administrator in each app, type `/{ctx}/management/access` (no `settings/` segment); record what appears and whether the invitations table is present. |
| P13 | Force a server validation error on the invite wizard's last step and record whether a warning notification appears above the buttons or only inline field errors. |
| P14 | From the users table, use the row Edit action to open the wizard in edit-user mode; record whether the primary button is enabled on arrival, and what makes it become enabled. |
