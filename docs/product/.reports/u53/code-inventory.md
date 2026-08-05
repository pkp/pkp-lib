# U53 — Users management · code-structure inventory

**Method**: code reading only. No browser, no live install, no probe. Every claim below is a
statement about code structure, not about observed behavior. Anything that code cannot settle is
marked **needs a probe**.

**Repos read**: `ojs-main`, `omp-main`, `ops-main` and the `lib/pkp` submodule inside each.
All three `lib/pkp` checkouts are at the **same commit** (`cd12133340277295357c77d77191e649302e0ef5`),
so shared-file readings below apply verbatim to all three apps.

---

## 1. Atom → code map

21 atoms. All 21 pointers resolve. Notes flag where the atlas text is imprecise (see §9).

### The current (Vue) surface — Users & Roles → Users tab

| Atom | What it is | Stable symbol |
|---|---|---|
| VUE-013 | Page container for the whole Users & Roles screen | `lib/ui-library/src/components/Container/AccessPage.vue` (class `AccessPage`, extends `Page.vue`); registered per app in `<app>/js/load.js`; mounted by `lib/pkp/templates/management/access.tpl` via `pageComponent = 'AccessPage'` set in `ManagementHandler::access()` |
| VUE-051 | The users table itself | `lib/ui-library/src/managers/UserAccessManager/UserAccessManager.vue`; registered as `UserAccessManager` in **`lib/pkp/js/load.js`** (shared, not per-app); mounted by `lib/pkp/templates/management/accessUsers.tpl` (`<user-access-manager>`), which `access.tpl` includes inside tab `users` |
| AFFM-102 | Search users | `UserAccessManagerActionSearch.vue` → `Search.vue`, label `userAccess.search`; writes `UserAccessManagerStore.setSearchPhrase` (resets `currentPage` to 1) |
| AFFM-103 | Row action "Edit" | `useUserAccessManagerConfig::getItemActions` entry `Actions.USER_ACCESS_EDIT` (`'editUser'`), label `common.edit`, icon `Edit`; dispatched to `UserAccessManagerStore.editUser` → `useUrl('management/settings/user/{user.id}').redirectToPage()` → `ManagementHandler::editUser()` |
| AFFM-104 | Row action "Send email" | `Actions.USER_ACCESS_SEND_MAIL` (`'sendEmail'`), label `email.email`; `useUserAccessManagerActions::sendEmail` → `useLegacyGridUrl({component:'grid.settings.user.UserGridHandler', op:'edit-email', params:{rowId}})` → `openLegacyModal` titled `grid.user.email` |
| AFFM-106 | Row action "Remove" | `Actions.USER_ACCESS_REMOVE_USER` (`'removeUser'`), label `grid.user.remove`, `isWarnable: true`; `useUserAccessManagerActions::removeUser` → `useModal().openDialog` (title `common.remove`, message `manager.people.confirmRemove`, OK/Cancel) → POST to legacy op `remove-user` with `csrfToken` |
| AFFM-107 | Row action Enable/Disable | `Actions.USER_ACCESS_DISABLE_USER` (`'disableUser'`); label `grid.user.enable` when `user.disabled`, else `grid.user.disable`; `isWarnable: !user.disabled`; `useUserAccessManagerActions::disableUser` → legacy op `edit-disable-user` with `enable: user.disabled ? '1' : ''`; modal title `user.disabledModal.title` / `user.enabledModal.title`, description `user.disabledModal.description` listing the user's current role names |
| AFFM-108 | Row action "Merge user" | `Actions.USER_ACCESS_MERGE_USER` (`'mergeUser'`), label `grid.action.mergeUser`; `useUserAccessManagerActions::mergeUser` → legacy op `merge-users` with `oldUserId: user.id`, opened as a legacy modal |

Supporting (not U53 atoms but on the same component): `UserAccessManagerCellName/Email/UserGroups/StartDate/Affiliation/Actions.vue`;
`UserAccessManagerStore.js` (Pinia store, `useFetchPaginated` on `useUrl('users')`, page size 25,
query `{searchPhrase, status:'all', includePermissions:true}`); `UserAccessManagerCellActions.vue`
renders a `DropdownActions` with `button-variant="ellipsis"`, aria label `userAccess.management.options`.

### The legacy (grid) surface — site admin's per-context settings wizard, Users tab

| Atom | What it is | Stable symbol |
|---|---|---|
| GRID-050 | The users grid handler | `PKP\controllers\grid\settings\user\UserGridHandler` — ops exactly as the atlas lists: `fetchGrid, fetchRow, addUser, editUser, updateUser, updateUserRoles, editDisableUser, disableUser, removeUser, editEmail, sendEmail, mergeUsers`; JS handler `$.pkp.controllers.grid.users.UserGridHandler` (`lib/pkp/js/controllers/grid/users/UserGridHandler.js`) |
| GRID-003 | Headless username-suggestion API | `PKP\controllers\api\user\UserApiHandler` — no `addRoleAssignment`; single op `suggestUsername`; policy `PKPSiteAccessPolicy($request, ['suggestUsername'], SITE_ACCESS_ALL_ROLES)` |
| AFFM-203 | "Add User" grid action | `UserGridHandler::initialize()` `LinkAction 'addUser'` → `AjaxModal` on op `addUser`, title `grid.user.add`, id `modal_add_user` |
| AFFM-204 | Search/filter + pagination | `UserGridHandler::getFilterForm()` → `controllers/grid/settings/user/userGridFilter.tpl`; `UserGridHandler::initFeatures()` → `[new PagingFeature()]`; filter fields: `search` (text), `userGroup` (select, default `grid.user.allRoles`), `includeNoRole` (checkbox `user.noRoles.selectUsersWithoutRoles`), submit `common.search`. `getFilterSelectionData()` also reads `searchField` and `searchMatch`, but **the template renders no control for either** (see §9) |
| AFFM-205 | Row "Email" | `UserGridRow::initialize()` `LinkAction 'email'` → `AjaxModal` op `editEmail`, label `grid.user.email`, icon `notify` |
| AFFM-206 | Row "Edit" | `LinkAction 'edit'` → `AjaxModal` op `editUser`, label `grid.user.edit`, icon `edit` |
| AFFM-207 | Row Enable/Disable | `LinkAction 'enable'` (when `$element->getDisabled()`, sets `actionArgs['enable']=true`, label `common.enable`) else `LinkAction 'disable'` (`enable=false`, label `grid.user.disable`); both → `AjaxModal` op `editDisableUser` |
| AFFM-208 | Row "Remove" | `LinkAction 'remove'` → `RemoteActionConfirmationModal` (message `manager.people.confirmRemove`, button `common.remove`, style `negative`) on op `removeUser`; **guarded** by `$hasActiveUserGroups` (any active `UserUserGroup` in this context) |
| AFFM-210 | Merge (both variants) | Row-level: `LinkAction 'mergeUser'` → `AjaxModal` op `mergeUsers` with `oldUserId=$rowId`, label `grid.user.mergeUsers.mergeUser`. Target-pick pass: `UserGridRow` in `getOldUserId()` mode renders only `LinkAction 'mergeUser'` → `RemoteActionConfirmationModal` message `grid.user.mergeUsers.confirm` (interpolating `oldUsername`/`newUsername`), style `negative`, label `grid.user.mergeUsers.mergeIntoUser` |

### API and mail

| Atom | What it is | Stable symbol |
|---|---|---|
| API-047 | Users REST controller | `PKP\API\v1\users\PKPUserController`, handler path `users`. Routes exactly as the atlas lists: `GET reviewers` (`user.getReviewers`), `GET report` (`user.getReport`), `GET {userId}` (`user.getUser`), `GET ''` (`user.getManyUsers`), `PUT {userId}/endRole/{userGroupId}` (`user.endRole`), `PUT {userId}/masthead/{userUserGroupId}` (`user.masthead`) |
| MAIL-054 | New-user welcome mail | `PKP\mail\mailables\UserCreated`, key `USER_REGISTER`. Sent from `UserDetailsForm::execute()` on **create only**, when `generatePassword` (forces send) or `sendNotify` is set; `replyTo` = context contact |
| MAIL-056 | Role-ended notice | `PKP\mail\mailables\UserRoleEndNotify`, key `USER_ROLE_END`. Sent unconditionally by `PKPUserController::endRole()` |
| MAIL-057 | Masthead-changed notice | `PKP\mail\mailables\UserRoleMastheadUpdateNotify`, key `USER_ROLE_MASTHEAD_UPDATE`. Sent by `PKPUserController::masthead()` **only when the value actually changes** (equal value short-circuits and returns the mapped user without mailing) |

**No atom pointer failed to resolve.**

---

## 2. Subclass-chain check (multi-app rule 8), per app

Legend: **none** = no app-side subclass exists (positive evidence of shared behavior);
**empty** = subclass exists but does not touch this path; **override** = app-side behavioral change.

| Shared class (lib/pkp) | OJS | OMP | OPS |
|---|---|---|---|
| `PKP\controllers\grid\settings\user\UserGridHandler` | none | none | none |
| `PKP\controllers\grid\settings\user\UserGridRow` | none | none | none |
| `…\user\form\UserForm` | none | none | none |
| `…\user\form\UserDetailsForm` | none | none | none |
| `…\user\form\UserDisableForm` | none | none | none |
| `…\user\form\UserEmailForm` | none | none | none |
| `…\user\form\UserRoleForm` | none | none | none |
| `PKP\controllers\api\user\UserApiHandler` | none | none | none |
| `PKP\API\v1\users\PKPUserController` | none (no `api/v1/users/` dir) | none | none |
| `PKP\pages\admin\AdminHandler` | none | none | none |
| `PKP\user\maps\Schema` | none | none | none |
| `PKP\mail\mailables\UserCreated` / `UserRoleEndNotify` / `UserRoleMastheadUpdateNotify` | none | none | none |
| All 8 templates on the path (`management/access.tpl`, `management/accessUsers.tpl`, `admin/contextSettings.tpl`, `controllers/grid/settings/user/userGridFilter.tpl`, `…/form/userDetailsForm.tpl`, `…/userDisableForm.tpl`, `…/userEmailForm.tpl`, `…/userRoleForm.tpl`, `common/userDetails.tpl`) | none | none | none |
| `PKP\pages\management\ManagementHandler` | **`APP\pages\management\SettingsHandler`** — overrides `workflow()`, `distribution()`, email filters; **does NOT override `access()`, `editUser()` or `settings()`** | same shape — overrides `workflow()`, `distribution()`; **does NOT override `access()`, `editUser()`, `settings()`** | same shape — overrides `workflow()`, `distribution()`, email filters, `getEmailSetupForm()`, `getInformationForm()`; **does NOT override `access()`, `editUser()`, `settings()`** |
| `PKP\user\Repository` (`mergeUsers`) | **override**: `APP\user\Repository::mergeUsers()` — calls parent then transfers individual subscriptions, institutional subscriptions and `OJSCompletedPaymentDAO` payments | **override**: `APP\user\Repository::mergeUsers()` — calls parent then transfers `OMPCompletedPaymentDAO` payments | **none** — `ops-main/classes/user/` does not exist; `Repo::user()` binds `PKP\user\Repository` directly |
| `PKP\mail\Repository::map()` | **override, additive** (`parent::map()->merge([...])`) — base mailables all retained | **override, additive** — base retained | **override, REPLACING** (`return collect([...])`, no `parent::map()`) — includes `UserCreated`, **omits `UserRoleEndNotify` and `UserRoleMastheadUpdateNotify`** |

**Headline.** The entire user-management execution path is unsubclassed and identical in all
three apps. Only three seams diverge:

1. `SettingsHandler::__construct()` role assignments (§5).
2. `APP\user\Repository::mergeUsers()` — OJS and OMP extend it, OPS does not (and has nothing to
   extend it for: no subscriptions, no payments module). **This is an intended-looking divergence,
   not a silent one.**
3. `APP\mail\Repository::map()` on OPS is a *replacing* override, not additive — the classic silent
   divergence shape (§4, §7).

---

## 3. Both surfaces

The Notes line is right that the mechanism is invariant of surface, but the two surfaces are
**different code**, not the same component rendered twice.

### Surface A — context-level "Users & Roles" tab (current)

- Handler: `SettingsHandler` (op `settings`, arg `access`) or op `access` → `ManagementHandler::access()`.
- Template: `lib/pkp/templates/management/access.tpl`, tab `users`:
  `<user-invitation-manager>` (U6) **then** `{include file="management/accessUsers.tpl"}` →
  `<user-access-manager>` (U53).
- Data: `GET /{ctx}/api/v1/users` via `UserAccessManagerStore` (`useFetchPaginated`).
- Actions: 4 of the 6 row actions (`sendEmail`, `disableUser`, `removeUser`, `mergeUser`) do **not**
  have Vue implementations — they call into the **legacy grid ops** through
  `useLegacyGridUrl({component:'grid.settings.user.UserGridHandler', …})` and render the legacy
  modal. `editUser` navigates to the U6 wizard. `loginAs` (not a U53 atom) redirects to `login/signInAsUser`.
- **There is no "Add User" affordance on this surface.** `UserGridHandler`'s `addUser` LinkAction is
  only added in `UserGridHandler::initialize()`, which surface A never runs. Creating a user from
  surface A goes through the U6 invitation wizard instead.

### Surface B — site-level per-context settings wizard, Users tab (legacy)

- Handler: `PKP\pages\admin\AdminHandler::wizard($contextId)` (no app subclass anywhere).
- Template: `lib/pkp/templates/admin/contextSettings.tpl`, tab `users`:
  `{load_url_in_div}` on the component URL for `grid.settings.user.UserGridHandler::fetchGrid`,
  **built with `context=$editContext->getPath()`** — the grid request therefore runs inside the
  edited context, under that context's `ContextAccessPolicy`.
- Grid columns: `givenName`, `familyName`, `userName`, `roles` (anonymous `GridColumn` subclass
  computing active + active-in-future user groups for the context), `email`. The Vue table's column
  set is different: name, email, roles, **start date**, **affiliation**, actions.
- All 12 grid ops (AFFM-203..210) live here, including **Add User** and the two-pass merge flow.

### Where they share code

Both surfaces execute the **same server-side operations** for email, enable/disable, remove and
merge: `UserGridHandler::editEmail/sendEmail/editDisableUser/disableUser/removeUser/mergeUsers`
and the same four forms. That is why the mechanism is surface-invariant.

### Where they diverge

| | Surface A (Vue) | Surface B (legacy grid) |
|---|---|---|
| Create a user | absent (U6 wizard instead) | `addUser` → `UserDetailsForm` step 1 → `UserRoleForm` step 2 |
| Edit a user | navigates to the U6 wizard (`management/settings/user/{id}`) | `editUser` → `UserDetailsForm` modal (full identity + roles + masthead) |
| Filter by role / "users without roles" | not offered | offered (`userGroup` select, `includeNoRole` checkbox) |
| Columns | +start date, +affiliation | +none (5 legacy columns) |
| Remove-row guard | client-side `user.groups.find(g => g.dateEnd === null)` | server-side `$hasActiveUserGroups` query in `UserGridRow` |
| Merge guard | `user.canMergeUsers` from the API schema map | `Validation::getAdministrationLevel(...) === ADMINISTRATION_FULL` computed in `UserGridRow` |
| Pagination | `TablePagination`, 25/page from `UserAccessManagerStore.countPerPage` | `PagingFeature` (grid range info) |

### Which is legacy, which is current — evidence

Surface B is the older one and Surface A is current, on this evidence:
- Surface A's own action layer calls Surface B's ops through a composable literally named
  `useLegacyGridUrl` and a method named `openLegacyModal`.
- The atlas already labels AFFM-104/108's modals "legacy".
- `access.tpl` mounts Vue managers; `contextSettings.tpl` still uses `{load_url_in_div}` +
  `$.pkp.controllers.grid.*`.
- Surface A's edit path was re-pointed at the U6 invitation wizard, Surface B's was not.

Surface B is nevertheless **live and reachable** — it is not dead code; `AdminHandler::wizard` is a
current op in `AdminHandler`'s role assignment list.

---

## 4. Secondary seams

### `isOJS()` / `isOMP()` / `isOPS()`
**Zero occurrences** anywhere on the U53 path: `lib/pkp/controllers/grid/settings/user/**`,
`lib/pkp/api/v1/users/**`, `lib/pkp/classes/user/**`, `lib/pkp/pages/management/**`,
`lib/ui-library/src/managers/UserAccessManager/**`, `AccessPage.vue`.

### `registry/emailTemplates.xml`

| Key | OJS | OMP | OPS |
|---|---|---|---|
| `USER_REGISTER` (MAIL-054) | present | present | present |
| `USER_ROLE_END` (MAIL-056) | present | present | present |
| `USER_ROLE_MASTHEAD_UPDATE` (MAIL-057) | **present** | **ABSENT** | **ABSENT** |

Both the fresh-install seeder (`PKP\install\Installer` line reading `registry/emailTemplates.xml`)
and the default-data DAO (`PKP\emailTemplate\DAO::getDefaultTemplatesFilename()` → the same file)
read the **app's own** registry. The upgrade migrations
`PKP\migration\upgrade\v3_5_0\InstallEmailTemplates` and its subclass
`I11800_AddUserRoleMastheadUpdateEmail` iterate `registry/emailTemplates.xml` entries and `continue`
past any key not found there — so on OMP/OPS the key is never installed by upgrade either, even
though all three `dbscripts/xml/upgrade.xml` files wire the migration in.

The locale strings (`mailable.userRoleMastheadUpdateNotify.*`, `emails.userRoleMastheadUpdateNotify.*`)
DO exist, in shared `lib/pkp/locale/en/emails.po` — so this is a registry gap, not a translation gap.

### `PKP\mail\Repository::map()`
OPS's replacing override drops `UserRoleEndNotify` and `UserRoleMastheadUpdateNotify` from the
mailable list. Consequence in code: on OPS these two mailables are not enumerated by
`Repo::mailable()->getMany()`, which is what feeds Emails management. `USER_ROLE_END` is still
seeded in OPS's registry, so `endRole()` still finds a template to send with — the template is
simply not offered for customization. **needs a probe** (Emails management is U56's surface; the
observation belongs there, the cause is recorded here).

### `registry/userGroups.xml`
Relevant to who can reach these screens at all:
- **OJS**: three `ROLE_ID_MANAGER` groups (Journal manager, Journal editor, Production editor), all
  three `permitSettings="true"`; plus Section editor, Guest editor, 7 assistant groups, Author,
  Translator, External reviewer, Reader, **Subscription manager**, Editorial board member.
- **OMP**: three manager groups (same three names), all `permitSettings="true"`; Series editor;
  7 assistant groups; Author, Volume editor, Chapter author, Translator; **Internal** + External
  reviewer; Reader; Editorial board member. No Subscription manager.
- **OPS**: **one** manager group only (`default.groups.name.manager`, `permitSettings="true"`);
  Moderator (`sectionEditor` role id); Author; Reader; Editorial board member.
  **No reviewer group at all.**

The single-manager-group shape on OPS is why the U6 spec records that a manager cannot be stripped
of settings access there — there is no second manager-level group to clear the flag on.

### Config keys
None. The only `Config::getVar` calls anywhere on the path are `general.show_upgrade_warning`
(`ManagementHandler::context()`, `AdminHandler::settings()`) and `security.force_login_ssl`
(`AdminHandler`), neither of which affects user management.

### Per-app template overrides
None on this path in any app. The only `templates/controllers/grid/settings/user/` file in any app
repo is `omp-main/templates/controllers/grid/settings/user/userGroupsList.tpl`, which **no PHP or
template in `omp-main` references** — an orphaned file, not an override.

### Vue registration
`AccessPage` is imported and registered in each app's own `js/load.js` — identically in all three.
`UserAccessManager` (and `UserInvitationManager`) are registered in **shared** `lib/pkp/js/load.js`.

---

## 5. Roles and permissions as the code states them

### Page-level: reaching Users & Roles

`APP\pages\management\SettingsHandler::__construct()` — **this is where the apps diverge**:

| App | `ROLE_ID_SITE_ADMIN` gets ops | `ROLE_ID_MANAGER` gets ops |
|---|---|---|
| OJS | `access`, **`settings`** | `settings` |
| OMP | `access` | `settings` |
| OPS | `access` | `settings` |

`ManagementHandler::authorize()` adds `ContextAccessPolicy($request, $roleAssignments)` always, and
`CanAccessSettingsPolicy` **only** when the requested op is `settings` and the args are neither
`['announcements']` nor `['userComments']`. `CanAccessSettingsPolicy::effect()` permits if any
authorized user group is `ROLE_ID_SITE_ADMIN`, or is `ROLE_ID_MANAGER` **with `permitSettings`**.

So, from code:
- `/{ctx}/management/settings/access` — OJS: manager-with-`permitSettings` **or** site admin.
  OMP/OPS: manager-with-`permitSettings` only; a site admin who is not also a manager of that
  context has no role assignment for op `settings`.
- `/{ctx}/management/access` — all three apps: site admin only (no `CanAccessSettingsPolicy`,
  because the op is not `settings`).
- Both ops call the same `ManagementHandler::access()`.

This is the code behind U6's register entries A2 and A4; U53 inherits the same two doors.

`PKPTemplateManager` renders the Settings → "Users & Roles" menu item only when
`$hasSettingsAccess = array_reduce($userGroups, fn($c,$ug) => $c || $ug->permitSettings, false)` —
note this reduces over **every** authorized user group and does **not** filter by role id, so it
depends on whether a site-admin user group carries `permitSettings`. **needs a probe** (what the
site-admin group's `permitSettings` value actually is on a seeded install, and therefore whether a
site admin sees the menu entry on OJS).

### Page-level: reaching the site wizard's Users tab

`AdminHandler::__construct()` assigns every op, including `wizard`, to `ROLE_ID_SITE_ADMIN` only.
`AdminHandler::authorize()` adds `PKPSiteAccessPolicy` **and** `ReauthenticationRequiredPolicy`
(for every op except `confirmAccess`/`confirmAccessSubmit`), and then explicitly returns `false`
**if a context is present in the request** — the wizard is only reachable from the site-level URL.
The users grid inside it is fetched at the *edited context's* path, so it re-authorizes under
`UserGridHandler`'s own policies.

### Component-level: the users grid

`UserGridHandler::__construct()` assigns `[ROLE_ID_MANAGER, ROLE_ID_SITE_ADMIN]` to **all twelve**
ops. `UserGridHandler::authorize()` adds `ContextAccessPolicy` + `CanAccessSettingsPolicy`
unconditionally (no op exemption).

### Per-operation administration checks inside the grid handler

`PKP\security\Validation::getAdministrationLevel($administeredUserId, $administratorUserId, $contextId)`
returns `FULL` / `PARTIAL` / `PROHIBITED` by these rules, in order:
1. same user → `FULL`;
2. administered user holds `ROLE_ID_SITE_ADMIN` at site scope → `PROHIBITED` (nobody may administer
   a site admin, not even another site admin);
3. administrator holds `ROLE_ID_SITE_ADMIN` at site scope → `FULL`;
4. administrator holds no `ROLE_ID_MANAGER` group anywhere → `PROHIBITED`;
5. administered user belongs to any context the administrator does not manage → `PARTIAL` if
   `$contextId` was supplied and the administrator manages it, else `PROHIBITED`;
6. otherwise `FULL`.

How each op uses it:

| Op | Check | On failure |
|---|---|---|
| `editUser` / `updateUser` | `!== PROHIBITED`, **with** `$contextId`; `PARTIAL` → `UserDetailsForm::applyUserGroupUpdateOnly()` (roles + masthead only, identity read-only) | `JSONMessage(false, __('grid.user.cannotAdminister'))` |
| `updateUserRoles` | `=== FULL`, **without** `$contextId` | same |
| `editDisableUser` / `disableUser` | `=== FULL`, **without** `$contextId` | same |
| `removeUser` | `!== PROHIBITED`, **with** `$contextId`; also requires `checkCSRF()` | same |
| `mergeUsers` (second pass) | `=== FULL`, **without** `$contextId`; also requires `checkCSRF()` | falls through to re-rendering the target-pick grid rather than an error |
| `editEmail` / `sendEmail` | **different check**: `RoleDAO::userHasRole(SITE_CONTEXT_ID, currentUser, SITE_ADMIN)` OR `userHasRole(context, currentUser, MANAGER)` — a role check on the *sender*, not an administration-level check on the *target* | same |

Note the asymmetry: `editUser`/`removeUser` pass a `$contextId` (so a manager can act on a user who
also belongs to other contexts) while `disableUser`/`updateUserRoles`/`mergeUsers` do not (so any
cross-context membership makes them `PROHIBITED` for a non-site-admin). This is stated by the code;
whether the UI surfaces the resulting refusal legibly is **needs a probe**.

### API-level

`PKPUserController::getRouteGroupMiddleware()` = `has.user`, `has.context`, and
`roleAuthorizer([ROLE_ID_SITE_ADMIN, ROLE_ID_MANAGER, ROLE_ID_SUB_EDITOR])` — applied to the
**whole group**, so a Sub-editor (Section editor / Series editor / OPS Moderator) is admitted to
every route including `PUT endRole` and `PUT masthead`. `authorize()` adds `UserRolesRequiredPolicy`
and `ContextAccessPolicy`. **There is no per-route narrowing and no `getAdministrationLevel` check
in `endRole()` or `masthead()`.** This is the code behind U6's register entry A8.

`masthead()` refuses one case in code: if the target `UserUserGroup`'s group is `ROLE_ID_REVIEWER`,
it returns `api.400.reviewerMastheadCannotBeChanged`. (OPS has no reviewer group, so the branch is
unreachable there.)

`UserApiHandler` (`suggestUsername`) is gated by `PKPSiteAccessPolicy(..., SITE_ACCESS_ALL_ROLES)` —
any signed-in user.

### What the users table shows about each row

`PKP\user\maps\Schema::mapUsersWithPermissions()` builds a `permissionMap`:
site admin → all `true`; otherwise `Repo::user()->permissionMapForManager(currentUserId, userIds)`,
which marks a target `false` if the target belongs to **any** user group whose context the current
user does not manage. `canLoginAs` and `canMergeUsers` both read that map and both return `false`
for the current user's own row. Note the map does **not** reproduce rule 2 above — a site-admin
target is not specially excluded here, only in `getAdministrationLevel`. **needs a probe**: whether
a manager sees a Merge action on a site administrator's row that the server then refuses.

`UserAccessManagerStore` sends `includePermissions: true`, but `PKPUserController::getMany()`'s
`_processAllowedParams` allowlist does **not** contain `includePermissions` — the parameter is
dropped. The permission props are computed unconditionally by the schema map, so the flag appears
to be vestigial. **needs a probe** only if a claim depends on it.

---

## 6. Reachability inventory

### URLs a user could type or follow

| # | Path | Handler / op | Gate per code |
|---|---|---|---|
| R1 | `/{ctx}/management/settings/access` | `SettingsHandler` op `settings`, arg `access` → `ManagementHandler::access()` | `ContextAccessPolicy` + `CanAccessSettingsPolicy`; role assignment: manager (all apps), site admin (**OJS only**) |
| R2 | `/{ctx}/management/access` | `SettingsHandler` op `access` → same method | `ContextAccessPolicy` only; role assignment: site admin only (all apps) |
| R3 | `/{ctx}/management/settings/user/{userId}` | op `settings`, arg `user` → `ManagementHandler::editUser()` → `UserRoleAssignmentInviteUIController::createHandle()` | same as R1. **404 if `$args[0]` empty.** Target of AFFM-103. Screen itself is U6's |
| R4 | `/index/admin/contexts` | `AdminHandler::contexts` | site admin, `ReauthenticationRequiredPolicy`, no context in URL |
| R5 | `/index/admin/wizard/{contextId}` | `AdminHandler::wizard` | same; 404 if arg absent/non-numeric or context not found. Users tab is here |
| R6 | `/{ctx}/$$$call$$$/grid/settings/user/user-grid/{op}` where `{op}` ∈ `fetch-grid, fetch-row, add-user, edit-user, update-user, update-user-roles, edit-disable-user, disable-user, remove-user, edit-email, send-email, merge-users` | `UserGridHandler` | manager or site admin + `ContextAccessPolicy` + `CanAccessSettingsPolicy`, then the per-op checks in §5 |
| R7 | `/{ctx}/$$$call$$$/api/user/user-api/suggest-username` | `UserApiHandler::suggestUsername` | any signed-in user |
| R8 | `/{ctx}/api/v1/users` (GET) | `PKPUserController::getMany` | admin / manager / sub-editor |
| R9 | `/{ctx}/api/v1/users/{userId}` (GET) | `::get` | same |
| R10 | `/{ctx}/api/v1/users/reviewers` (GET) | `::getReviewers` | same — **rider, owned here, cited by U27 (Reviewer assignment)** |
| R11 | `/{ctx}/api/v1/users/report` (GET) | `::getReport` — streams `user-report-YYYY-MM-DD.csv` | same — **rider, cited by U65 (Statistics — editorial)**. No UI affordance calls this anywhere in `lib/ui-library/src`, `lib/pkp/templates` or app templates. **needs a probe** (is it reachable from any screen at all?) |
| R12 | `/{ctx}/api/v1/users/{userId}/endRole/{userGroupId}` (PUT) | `::endRole` | same |
| R13 | `/{ctx}/api/v1/users/{userId}/masthead/{userUserGroupId}` (PUT) | `::masthead` | same |
| R14 | `/{ctx}/login/signInAsUser/{userId}` | `LoginHandler` (ROUTE-016) | target of the Login-As row action — **U-login's territory, not U53's** |

R12 and R13 are U53's atoms but have **no caller on any U53 screen**. The only code in
`lib/ui-library/src` that calls them is `pages/userInvitation/UserInvitationUserGroupsTable.vue`
(U6's wizard). Recorded here because API-047 is U53's atom; the affordances are U6's.

### Affordances rendered on U53's screens

**Surface A — Users & Roles → Users tab** (below the invitations table, which is U6's):

| Affordance | Component / symbol | Condition in code |
|---|---|---|
| Heading `grid.user.currentUsers` + live count | `UserAccessManager.vue` `#label` slot | always |
| Search box (`userAccess.search`) | `UserAccessManagerActionSearch.vue` | always |
| Table columns: name, email (`about.contact.email`), roles (`user.roles`), start date (`userAccess.tableHeader.startDate`), affiliation (`user.affiliation`), actions (sr-only header `common.moreActions`) | `useUserAccessManagerConfig::getColumns` | always |
| Pagination control | `TablePagination` | always (25/page) |
| Row ellipsis menu (`userAccess.management.options`) | `UserAccessManagerCellActions.vue` → `DropdownActions` | always |
| ↳ **Edit** (`common.edit`) | AFFM-103 | **unconditional** — offered even on the current user's own row |
| ↳ **Email** (`email.email`) | AFFM-104 | **unconditional** — offered on own row |
| ↳ **Login As** (`grid.user.logInAs`) | AFFM-105 — *not a U53 atom* | `getCurrentUserId() !== user.id && user.canLoginAs` |
| ↳ **Remove** (`grid.user.remove`, warnable) | AFFM-106 | `!== self` **and** `user.groups.find(g => g.dateEnd === null)` |
| ↳ **Enable/Disable** (`grid.user.enable` / `grid.user.disable`) | AFFM-107 | `!== self`; label and `isWarnable` flip on `user.disabled` |
| ↳ **Merge user** (`grid.action.mergeUser`) | AFFM-108 | `!== self` **and** `user.canMergeUsers` |

Also on that screen but **not U53's**: the "Invite to a role" button and the invitations table
(U6, AFFM-099..101 / VUE-052). Also on the same page in other tabs: Roles (U54), Notify (U55),
Site access options (U54's AFFM-116), ORCID.

**Surface B — site wizard → Users tab** (`admin/contextSettings.tpl`):

| Affordance | Symbol | Condition |
|---|---|---|
| Grid title `grid.user.currentUsers` | `UserGridHandler::initialize` | always |
| **Add User** | AFFM-203 | always |
| Filter form: search text, role select (`grid.user.allRoles` default), `includeNoRole` checkbox, `common.search` button | AFFM-204 | always |
| Pagination | `PagingFeature` | always |
| Row **Email** | AFFM-205 | row id numeric and not in merge-target mode |
| Row **Edit** | AFFM-206 | same |
| Row **Enable** *or* **Disable** | AFFM-207 | same; branch on `$element->getDisabled()` |
| Row **Remove** | AFFM-208 | `$hasActiveUserGroups` in the current context |
| Row **Login As** | AFFM-209 — *not a U53 atom* | `!Validation::loggedInAs() && currentUser->getId() != rowId && $canAdminister` |
| Row **Merge User** | AFFM-210 | `currentUser->getId() != rowId && $canAdminister` |
| Merge-target grid: row **Merge Into User** only | AFFM-210 | `getOldUserId()` set, old user exists, and `oldUserId != newUserId` |

**Forms reached from those affordances** (all shared templates, no app overrides):

- `userDetailsForm.tpl` + `common/userDetails.tpl` (AFFM-203/206): given name*, family name,
  preferred public name, username (+ **Suggest** button → R7; text field only when creating,
  read-only display when editing), email*, password + repeat (required only when creating),
  **Generate password** checkbox (create only), **Must change password** checkbox, country,
  **Notify user** checkbox (suppressed when editing via `disableSendNotifySection`), URL, phone,
  working languages (only when the site has >1 locale), reviewing interests, affiliation,
  biography, mailing address, signature; **Gossip** textarea only when
  `Repo::user()->canCurrentUserGossip()`; **Roles** checkbox list (`userGroupIds[]`) and
  **Masthead** checkbox list (`mastheadUserGroupIds[]`, reviewer groups rendered `disabled`) —
  both only when `$userId` is set. In `ADMINISTRATION_PARTIAL` mode the identity block is replaced
  by `common/userDetailsReadOnly.tpl`.
- `userRoleForm.tpl` (step 2 after creating a user): heading `grid.user.step2`, the same two
  checkbox lists, submit `common.save`. Reached only from `updateUser` when `!$userId`.
- `userDisableForm.tpl` (AFFM-107/207): a single **reason** textarea, titled
  `grid.user.disableReason` or `grid.user.enableReason` with matching description, plus form buttons.
- `userEmailForm.tpl` (AFFM-104/205): subject* (text), **To** (disabled text showing
  `Full Name <email>`), body* (rich textarea), submit `common.sendEmail`.

---

## 7. The destructive actions

### Disable / Enable an account

- **Claims**: `grid.user.disable` / `common.enable`; modal title
  `user.disabledModal.title`/`user.enabledModal.title` (interpolating full name), description
  `user.disabledModal.description` interpolating the user's **current role names** — i.e. the modal
  names the roles even though the operation does not touch them.
- **Touches**: `users.disabled` and `users.disabled_reason` only
  (`UserDisableForm::execute()` → `$user->setDisabled()`, `setDisabledReason()`, `Repo::user()->edit()`).
  **No role or masthead row is changed.**
- **Side effect**: when the result is disabled,
  `SessionGuard::invalidateOtherSessions($user->getId())` — the target's other sessions are dropped.
- **Confirmation**: a form modal with a free-text reason (not a yes/no confirm). The reason is
  pre-filled from the existing `disabledReason` on both enable and disable.
- **Reversible**: yes, plainly — the same op with `enable=1`. The reason field is *not* cleared on
  enable; whatever is typed is stored.
- **Gate**: `getAdministrationLevel(...) === ADMINISTRATION_FULL`, computed **without** a context id.
- **needs a probe**: whether the row action re-renders correctly to "Enable" after a disable on
  Surface A (the store refetches via `triggerDataChangeCallback`), and what the description text
  says for a user with no roles.

### Remove (from active roles in this context)

- **Claims**: labelled `grid.user.remove` / `grid.action.remove`, confirm message
  `manager.people.confirmRemove`, confirm button `common.remove`.
- **Touches**: `UserGridHandler::removeUser()` sets `date_end = now()` on every **active**
  `user_user_groups` row whose group belongs to the current context. It is an **end-date, not a
  delete** — the assignment history is preserved. The user account itself is untouched, as are
  assignments in every other context.
- **Refusal in code**: if the count of active user groups in this context is 0, returns
  `grid.user.userNoRoles` rather than acting.
- **Confirmation**: Surface A — `useModal().openDialog` with OK (primary) / Cancel (warnable).
  Surface B — `RemoteActionConfirmationModal`, style `negative`. Both post with a CSRF token;
  `removeUser` calls `checkCSRF()`.
- **Reversible**: not by this screen. Re-adding the role via the edit form creates a *new*
  assignment (`Repo::userGroup()->assignUserToGroup`), it does not clear the end date.
- **Gate**: `getAdministrationLevel(..., contextId) !== ADMINISTRATION_PROHIBITED`.
- **Distinct from** `PUT users/{id}/endRole/{groupId}` (API-047), which ends **one** named role via
  `Repo::userGroup()->endAssignments($contextId, $userId, $userGroupId)` **and sends
  `UserRoleEndNotify`**. The grid's `removeUser` ends **all** roles in the context and **sends
  nothing**. Same underlying data change, different scope and different mail behavior — this is the
  most likely place for the spec to mislead a reader.
- **needs a probe**: whether the confirm dialog on Surface A names the context, the user, or the
  roles being ended; and whether the removed user disappears from the table or remains with an
  empty roles cell.

### Merge users

- **Claims**: "Merge User" opens a second copy of the users grid titled
  `grid.user.mergeUsers.mergeIntoUser`; each row then offers "Merge Into User" with the confirm
  message `grid.user.mergeUsers.confirm` interpolating **both usernames**.
- **Two-pass flow**: `mergeUsers` with only `oldUserId` re-instantiates `UserGridHandler`, retitles
  it, and returns `fetchGrid` — the same grid, filtered through `userGridFilter.tpl`'s
  `-userMerge` filter id so the two grids on screen do not collide. `mergeUsers` with both ids
  performs the merge and emits the global JS event `userMerged`, which
  `js/controllers/grid/users/UserGridHandler.js` binds to close the modal and refresh.
- **Touches** (`PKP\user\Repository::mergeUsers()`, in order): submission files' uploader id; notes;
  editorial decisions; review assignments' reviewer id; email log entries; event log entries;
  submission comments' author id; notifications; **invalidates the old user's other sessions**;
  deletes the old user's temporary files; deletes the old user's sub-editor assignments; copies each
  `user_user_groups` row to the new user **when the new user is not already in that group**
  (preserving `dateStart`, `dateEnd`, `masthead`), then **deletes all** of the old user's
  `user_user_groups` rows; transfers stage assignments, deleting rather than transferring any that
  would duplicate; then **`$this->delete($this->get($oldUserId, true))`** — the old user row is
  destroyed.
  - OJS additionally: individual subscriptions (transferred only when the old one is valid and the
    new user has none or an invalid one; **all remaining old-user individual subscriptions are then
    deleted**), institutional subscriptions (all transferred; the new user becomes the contact),
    completed payments.
  - OMP additionally: completed payments.
  - OPS: base behavior only.
- **Hook**: `UserAction::mergeUsers` fires before any of it.
- **Confirmation**: `RemoteActionConfirmationModal` (style `negative`) naming both usernames.
  On Surface A the whole thing runs inside a legacy modal opened by `useUserAccessManagerActions::mergeUser`.
- **Reversible**: **no.** The old user account is deleted. Nothing in the code writes an undo path.
- **Gates**: server — `getAdministrationLevel($oldUserId, $currentUserId) === ADMINISTRATION_FULL`
  (no context id, so any cross-context membership blocks a non-site-admin). Row rendering —
  Surface A `user.canMergeUsers`; Surface B `$canAdminister && currentUser != row`. Self-merge is
  blocked twice: the target grid skips the row when `oldUserId == newUserId`, and both permission
  computations return false for self.
- **needs a probe**: (a) what the screen shows after a merge — does the merged-away row vanish from
  both grids? (b) whether the merge grid offers Merge Into User on rows the server would refuse
  (`getAdministrationLevel !== FULL` computed *without* context, while `canMergeUsers` is computed
  from a *context-scoped* permission map — the two can disagree); (c) whether any confirmation
  states that the old account is permanently deleted (the confirm string names both usernames but
  the code gives no indication it warns about deletion).

### The two role-ending API operations (mechanics owned here, driven from U6's screen)

- `PUT users/{userId}/endRole/{userGroupId}` — ends **that one** assignment in the current context;
  404s if the user or an active assignment for that group is not found; always sends
  `UserRoleEndNotify`; returns the re-mapped user.
- `PUT users/{userId}/masthead/{userUserGroupId}` — 404s if the `UserUserGroup` does not belong to
  this user and context; 400 (`api.400.reviewerMastheadCannotBeChanged`) for a reviewer group;
  400 (`api.400.invalidMastheadParameter`) if the body's `masthead` is not coercible to a boolean;
  **no-ops silently and returns without mailing** if the value is unchanged; otherwise updates via
  `Repo::userGroup()->setUserUserGroupMasthead` and sends `UserRoleMastheadUpdateNotify`.
- **`masthead()` throws a raw `Exception`** when `Repo::emailTemplate()->getByKey(...,'USER_ROLE_MASTHEAD_UPDATE')`
  returns nothing, with the message *"Email template USER_ROLE_MASTHEAD_UPDATE not found. The
  migration script I11800_AddUserRoleMastheadUpdateEmail needs to be run."* Given §4, that template
  is **not seeded on OMP or OPS by either install or upgrade**. Code therefore predicts that
  changing a masthead choice fails on OMP and OPS after the data change has already been written
  (`setUserUserGroupMasthead` runs *before* the template lookup). **needs a probe** — this is an
  affordance/behavior claim and must be driven live on all three apps before it is asserted.
  `endRole()` performs the analogous lookup with **no null check** at all, but `USER_ROLE_END` is
  seeded in all three registries.
- Neither operation calls `getAdministrationLevel`; both admit `ROLE_ID_SUB_EDITOR`.

---

## 8. Neighbouring machinery to keep OUT

| Neighbour | Where the seam is |
|---|---|
| **Roles configuration (U54)** | `access.tpl` tab `roles` → `UserGroupGridHandler` (a different component). U53 owns the *container* VUE-013 and tab `users`; every control inside tab `roles`, plus the "Site access options" form (AFFM-116) in tab `access`, is U54's. Also U54's: what `permitSettings` / `permitSelfRegistration` / stage assignment mean. U53 only *reads* `permitSettings` as a gate |
| **User invitations (U6)** | Everything above the users table in tab `users`: "Invite to a role", the invitations table, row Edit / Cancel Invite (AFFM-099..101, VUE-052), and the whole 3-step wizard (VUE-011, `pages/userInvitation/**`, ROUTE-013/014). **U53 owns**: the users table, the row "Edit" action (AFFM-103) and the address it navigates to; and the *mechanics* of `PUT endRole` / `PUT masthead` and their two mailables (API-047, MAIL-056/057). **U6 owns**: the wizard screen those PUTs are fired from, its confirm dialogs (`user.removeRole.roleRemainMessage`, `user.masthead.update.title`), and the last-role guard (`numberOfActiveRoles <= 1`) — all of which live in `UserInvitationUserGroupsTable.vue`. That file is the only caller of either PUT in the entire front end. U6's spec already delegates the mechanics here and its footnote **s** is the pointer |
| **Notify users / bulk email (U55)** | `access.tpl` tab `notify`, `PKPNotifyUsersForm`, AFFM-114/115 — a sibling tab in the same container, not a users-table action. The per-user "Send email" (AFFM-104/205, `UserEmailForm`) IS U53's |
| **Statistics — editorial (U65)** | Cites `GET users/report` as a rider. U53 owns the sub-op's existence; what the CSV contains and where it is offered is U65's |
| **Reviewer assignment (U27)** | Cites `GET users/reviewers` as a rider. U53 owns the sub-op's existence; reviewer filters, ratings and the assignment screen are U27's |
| **Emails management (U56)** | Which mailables are listed, editable, disable-able, and what happens to a mailable with no default template. U53 records only that OPS's `map()` override drops two of its mailables and that OMP/OPS lack the `USER_ROLE_MASTHEAD_UPDATE` registry row |
| **Login / sessions** | "Login As" (AFFM-105 on the Vue table, AFFM-209 on the legacy grid) and `login/signInAsUser` (ROUTE-016) belong to the login feature (FEATURE-MAP line 36), even though both actions are rendered by U53's own components. `Validation::loggedInAs()` and `canLoginAs` are read on U53's path but are that feature's semantics |
| **User profile (self-service)** | `UserDetailsForm` is also used from author/contributor contexts (its constructor takes an `$author`); U53 covers only the manager-facing instantiations from `UserGridHandler` |

---

## 9. Atlas corrections proposed

*Flagged only — no atlas file was edited.*

1. **MAIL-056 apps column is `ojs omp`; should be `ojs omp ops`.**
   `USER_ROLE_END` is present in all three `registry/emailTemplates.xml` files
   (ojs line 83, omp line 75, ops line 38), and `PKPUserController::endRole()` is shared and
   unsubclassed. (Caveat that OPS's `APP\mail\Repository::map()` omits the mailable from the
   *Emails management list* — worth a Notes clause, but the mail is still sent on OPS.)

2. **MAIL-057 apps column is `ojs omp`; should be `ojs`.**
   `USER_ROLE_MASTHEAD_UPDATE` appears **only** in `ojs-main/registry/emailTemplates.xml`. Neither
   OMP nor OPS seeds it on install, and the `I11800_AddUserRoleMastheadUpdateEmail` upgrade skips
   keys absent from the app's own registry. OPS additionally drops the mailable from `map()`.

3. **AFFM-106/107/108 pointers name the i18n label keys as if they were action names.**
   The action names are `Actions.USER_ACCESS_REMOVE_USER` = `'removeUser'`,
   `USER_ACCESS_DISABLE_USER` = `'disableUser'`, `USER_ACCESS_MERGE_USER` = `'mergeUser'`
   (`useUserAccessManagerActions.js`). `grid.user.remove` / `grid.user.enable` / `grid.user.disable`
   / `grid.action.mergeUser` are the *labels*. Same for AFFM-102 (`userAccess.search` is the search
   field's label, not a name) and AFFM-105 (`grid.user.logInAs`). Suggest wording as
   "action `USER_ACCESS_REMOVE_USER` (label `grid.user.remove`)".

4. **AFFM-107's description "side modal with reason" understates it.** It is a legacy *form* modal
   (`userDisableForm.tpl`) opened through `useLegacyGridUrl`, whose title interpolates the user's
   full name and whose description lists the user's current roles.

5. **AFFM-204 should note that `searchField` and `searchMatch` are read but never rendered.**
   `UserGridHandler::getFilterSelectionData()` returns both and `renderFilter()` builds
   `fieldOptions`/`matchOptions`, but `userGridFilter.tpl` renders no control for either. Dead
   filter plumbing; the atlas pointer implies a field/match selector exists.

6. **GRID-050's description should name the surface.** The grid is *not* reachable from the
   Users & Roles tab; its only template mount is `admin/contextSettings.tpl` (site admin's
   per-context wizard). Surface A reaches individual **ops** via `useLegacyGridUrl` but never
   renders the grid except inside the merge modal. "Users management grid" reads as if it were the
   users table.

7. **VUE-051's "(lib/pkp management/accessUsers.tpl)" is right but incomplete** — the component is
   registered in shared `lib/pkp/js/load.js`, not in the per-app `load.js` (unlike VUE-013's
   `AccessPage`, which is registered per app). Worth stating because rule-8 chain checks on Vue
   atoms otherwise look at the wrong file.

8. **AFFM-203 is a `UserGridHandler` grid-level action, not a row action** — the atlas is correct;
   noting only that **no equivalent exists on Surface A**, so any "Add User" claim in a shared spec
   must be marked as legacy-surface-only.

9. **Candidate new atom**: `PKPUserController::getReport` (`GET users/report`) has **no caller** in
   `lib/ui-library/src`, `lib/pkp/templates`, or any app's `templates/`/`classes/components/`.
   Either an affordance atom is missing or the sub-op is orphaned. Recorded as **needs a probe**
   rather than an atlas edit.

---

## 10. "Needs a probe" list (10 items)

| # | Question | Screen / role |
|---|---|---|
| P1 | Does the Settings → Users & Roles menu entry appear for a Site Administrator on OJS (the `$hasSettingsAccess` reduce does not filter by role id, so it depends on the site-admin group's `permitSettings` value)? | backend menu, as site admin, all 3 apps |
| P2 | Does a manager see **Merge user** / **Login As** on a Site Administrator's row (the schema-map permission and `getAdministrationLevel`'s site-admin bar are computed differently)? | Users & Roles → Users, as manager |
| P3 | Does the Merge target grid offer "Merge Into User" on rows the server then refuses (context-scoped `canMergeUsers` vs context-free `ADMINISTRATION_FULL`)? | merge modal, as manager |
| P4 | What does the screen show after a completed merge — do both grids refresh, does the old row vanish? | merge modal, as site admin, scratch accounts |
| P5 | Does any confirmation on the merge path state that the old account is permanently deleted? | merge modal, all 3 apps |
| P6 | Does changing a masthead choice succeed on OMP and OPS, given `USER_ROLE_MASTHEAD_UPDATE` is not seeded there — and if it fails, does the change persist anyway? | U6 wizard's roles table, as manager, OMP + OPS (OJS control) |
| P7 | Are `UserRoleEndNotify` / `UserRoleMastheadUpdateNotify` listed in Emails management on OPS (its `map()` drops them)? | Settings → Workflow → Emails, as manager, OPS (OJS/OMP controls) |
| P8 | After Remove, does the row disappear or remain with an empty roles cell; and does the confirm dialog name the user, the context or the roles? | Users & Roles → Users, as manager |
| P9 | Is `GET users/report` reachable from any screen at all? | any backend screen, as manager/admin |
| P10 | Do "Edit" and "Send email" really appear on the signed-in user's own row (both are unguarded in `getItemActions`)? | Users & Roles → Users, as manager |

Cross-app control probes (multi-app rule 4) apply to P1, P2, P6, P7 and P10.
