# Atlas — AFFU (per-screen affordances: user account · reviewer · DOI/stats)

- **Sub-modality**: AFFU — within-screen affordances for user account/profile/registration,
  reviewer's own screens, and DOI/statistics dashboard screens (split of `aff` per README).
- **Sweep date**: 2026-07-26
- **Trees swept**: `ojs-main/lib/pkp` (templates/, classes/, controllers/, pages/ — the three
  apps' lib/pkp checkouts verified content-identical; swept once), `ojs-main/templates`,
  `omp-main/templates`, `ops-main/templates` (override scan), `ojs-main/lib/ui-library/src`
  (pages/, managers/, components/). lib/pkp SHA: `ad4606f93e`.
- **Method (globs/greps)**:
  - `find <tree>/templates/{user,frontend/pages,reviewer,invitation,notification,stats,dois} -type f` per tree; per-basename override scan across the three app `templates/` trees
  - `grep -rn "{include"` per swept template (partial closure); `diff` of each app override against its lib/pkp counterpart (`reviewer/review/step1.tpl`, `step3.tpl`, `notificationSettingsForm.tpl`)
  - `grep -rli orcid ojs-main/lib/ui-library/src --include='*.vue'`; `ls src/pages/{acceptInvitation,dashboard,reviewerSubmission,counter,stats*}`; `find src/components/{Container,ListPanel/doi}` + per-component read of template blocks (`v-if` guards quoted verbatim)
  - `grep -rn "reviewAssignments|MyReviewAssignments" {ojs,omp,ops}-main/pages ojs-main/lib/ui-library/src/pages/dashboard`; `ls ops-main/pages/reviewer` (absent)
  - `grep -rln "reviewerKey|submissionKey|AccessKey" ojs-main/lib/pkp --include='*.php' --include='*.tpl'` (one-click access surfaces)
  - `grep -rn "notificationSettingCategories|disableInterestsSection|targetOp" lib/pkp + app classes` (data-driven control lists)
- **Screens**: login · registration (incl. reviewer-interest fields) · registration complete /
  account activation · lost password · password reset · login-forced password change · confirm
  password gate · user profile tabs (identity, contact, roles, public, password, notifications,
  API key) · ORCID inline field + frontend verify/about + FieldOrcid component · notifications
  tasks panel + email unsubscribe · invitation accept/decline · reviewer wizard steps 1–4 (+
  review-form elements, decline modal, discussions) · reviewer completed-review view + round
  history · reviewer dashboard entries · one-click access surfaces · DOIs management page ·
  statistics dashboards (editorial, publications, users, context, issues, reports, COUNTER R5).
- **Conventions**: one atom per control/action per screen; identical per-app occurrences
  collapsed (`apps:`). Role/state conditions recorded only as quoted mechanical guards
  (`{if …}` / `v-if`). No liveness judgment. Reviewer wizard templates are `ojs omp` (OPS has
  no `pages/reviewer/` dispatcher); the reviewer dashboard view is `ojs omp ops`
  (`DashboardPage::MyReviewAssignments` mounted by all three app dispatchers).
- **Boundary**: submission wizard + editorial workflow → AFFW; settings/site admin (incl. DOI
  registration-agency settings, send-invitation wizard) → AFFM; reader front end chrome (incl.
  login/register links there) → AFFR. The forms themselves are AFFU.

## Login

| ID | apps | pointer | description |
|----|------|---------|-------------|
| AFFU-001 | ojs omp ops | lib/pkp/templates/frontend/pages/userLogin.tpl `input#username` | Login · username-or-email text field · required, `name="username"` Claimed by: login-and-sessions. |
| AFFU-002 | ojs omp ops | lib/pkp/templates/frontend/pages/userLogin.tpl `input#password` | Login · password field · required, maxlength 32 Claimed by: login-and-sessions. |
| AFFU-003 | ojs omp ops | lib/pkp/templates/frontend/pages/userLogin.tpl link `user.login.forgotPassword` | Login · "Forgot your password?" link · to `{url page="login" op="lostPassword"}` Claimed by: login-and-sessions. |
| AFFU-004 | ojs omp ops | lib/pkp/templates/frontend/pages/userLogin.tpl `input#remember` | Login · "Keep me logged in" checkbox · `name="remember"` value 1 Claimed by: login-and-sessions. |
| AFFU-005 | ojs omp ops | lib/pkp/templates/frontend/pages/userLogin.tpl `div.g-recaptcha` | Login · reCAPTCHA widget · shown when `{if $recaptchaPublicKey}` Claimed by: login-and-sessions. |
| AFFU-006 | ojs omp ops | lib/pkp/templates/frontend/pages/userLogin.tpl `altcha-widget` | Login · ALTCHA spam-check widget · shown when `{if $altchaEnabled}` Claimed by: login-and-sessions. |
| AFFU-007 | ojs omp ops | lib/pkp/templates/frontend/pages/userLogin.tpl `button.submit` key `user.login` | Login · Login submit button · POSTs form `#login` to `$loginUrl` Claimed by: login-and-sessions. |
| AFFU-008 | ojs omp ops | lib/pkp/templates/frontend/pages/userLogin.tpl `a.register` key `user.login.registerNewAccount` | Login · Register link · shown when `{if !$disableUserReg}` Claimed by: login-and-sessions. |

## Registration

Sources: `frontend/pages/userRegister.tpl` + included `frontend/components/registrationForm.tpl`, `frontend/components/registrationFormContexts.tpl`. The ORCID connect block included via `form/orcidProfile.tpl` is enumerated once in the "ORCID inline" section (AFFU-099–103, register variant).

| ID | apps | pointer | description |
|----|------|---------|-------------|
| AFFU-009 | ojs omp ops | lib/pkp/templates/frontend/components/registrationForm.tpl `input#givenName` | Registration · given name text field · required, maxlength 255 |
| AFFU-010 | ojs omp ops | lib/pkp/templates/frontend/components/registrationForm.tpl `input#familyName` | Registration · family name text field · optional, maxlength 255 |
| AFFU-011 | ojs omp ops | lib/pkp/templates/frontend/components/registrationForm.tpl `input#affiliation` | Registration · affiliation text field · required |
| AFFU-012 | ojs omp ops | lib/pkp/templates/frontend/components/registrationForm.tpl `select#country` | Registration · country dropdown · required, options from `$countries` |
| AFFU-013 | ojs omp ops | lib/pkp/templates/frontend/components/registrationForm.tpl `input#email` | Registration · email field · required, maxlength 90 |
| AFFU-014 | ojs omp ops | lib/pkp/templates/frontend/components/registrationForm.tpl `input#username` | Registration · username text field · required, maxlength 32 |
| AFFU-015 | ojs omp ops | lib/pkp/templates/frontend/components/registrationForm.tpl `input#password` | Registration · password field · required, maxlength 32 |
| AFFU-016 | ojs omp ops | lib/pkp/templates/frontend/components/registrationForm.tpl `input#password2` | Registration · repeat-password field · required, maxlength 32 |
| AFFU-017 | ojs omp ops | lib/pkp/templates/frontend/pages/userRegister.tpl checkbox `privacyConsent` (context branch) | Registration · privacy-policy consent checkbox · shown when `{if $currentContext}` and `{if $currentContext->getData('privacyStatement')}`; label links to about/privacy |
| AFFU-018 | ojs omp ops | lib/pkp/templates/frontend/pages/userRegister.tpl checkbox `emailConsent` (context branch) | Registration · public-email-notifications opt-in checkbox · in `{if $currentContext}` branch |
| AFFU-019 | ojs omp ops | lib/pkp/templates/frontend/pages/userRegister.tpl checkboxes `reviewerGroup[<id>]` (`#reviewerOptinGroup`) | Registration · sign-up-as-reviewer checkbox per reviewer group with `$userGroup->permitSelfRegistration` · inside `{if $currentContext}` and `{if $userCanRegisterReviewer}` |
| AFFU-020 | ojs omp ops | lib/pkp/templates/frontend/pages/userRegister.tpl `input#interests` (`#reviewerInterests`) | Registration · reviewing-interests text field · inside `{if $userCanRegisterReviewer}` (context branch) |
| AFFU-021 | ojs omp ops | lib/pkp/templates/frontend/components/registrationFormContexts.tpl checkboxes `readerGroup[<id>]` (`#contextOptinGroup`) | Registration · per-context reader-role checkbox, one per context/group with `permitSelfRegistration` · fieldset only when `{if !$currentContext}` (site-level registration) |
| AFFU-022 | ojs omp ops | lib/pkp/templates/frontend/components/registrationFormContexts.tpl checkboxes `reviewerGroup[<id>]` (`#contextOptinGroup`) | Registration · per-context reviewer-role checkbox · same guards as AFFU-021 |
| AFFU-023 | ojs omp ops | lib/pkp/templates/frontend/components/registrationFormContexts.tpl checkbox `privacyConsent[<contextId>]` | Registration · per-context privacy consent checkbox · shown when `{if !$enableSiteWidePrivacyStatement && $context->getData('privacyStatement')}` |
| AFFU-024 | ojs omp ops | lib/pkp/templates/frontend/pages/userRegister.tpl `input#interests` (`.reviewer_nocontext_interests`) | Registration · site-level reviewing-interests text field · shown when `{if !$currentContext}` |
| AFFU-025 | ojs omp ops | lib/pkp/templates/frontend/pages/userRegister.tpl checkbox `privacyConsent[<siteContextId>]` | Registration · site-wide privacy consent checkbox · shown when `{if !$currentContext}` and `{if $siteWidePrivacyStatement}` |
| AFFU-026 | ojs omp ops | lib/pkp/templates/frontend/pages/userRegister.tpl checkbox `emailConsent` (no-context branch) | Registration · email opt-in checkbox · shown when `{if !$currentContext}` |
| AFFU-027 | ojs omp ops | lib/pkp/templates/frontend/pages/userRegister.tpl `div.g-recaptcha` | Registration · reCAPTCHA widget · shown when `{if $recaptchaPublicKey}` |
| AFFU-028 | ojs omp ops | lib/pkp/templates/frontend/pages/userRegister.tpl `altcha-widget` | Registration · ALTCHA widget · shown when `{if $altchaEnabled}` |
| AFFU-029 | ojs omp ops | lib/pkp/templates/frontend/pages/userRegister.tpl `button.submit` key `user.register` | Registration · Register submit button · POSTs form `#register` |
| AFFU-030 | ojs omp ops | lib/pkp/templates/frontend/pages/userRegister.tpl `a.login` key `user.login` | Registration · Login link · to login page with `source=profile/roles` |

## Registration complete + account activation

| ID | apps | pointer | description |
|----|------|---------|-------------|
| AFFU-031 | ojs omp ops | lib/pkp/templates/frontend/pages/userRegisterComplete.tpl link `user.login.registrationComplete.manageSubmissions` | Registration complete · "View pending submissions" link · shown when `array_intersect(...MANAGER, SUB_EDITOR, ASSISTANT, REVIEWER..., (array)$userRoles)` |
| AFFU-032 | ojs omp ops | lib/pkp/templates/frontend/pages/userRegisterComplete.tpl link `user.login.registrationComplete.newSubmission` | Registration complete · "Make a new submission" link · shown when `{if $currentContext}` |
| AFFU-033 | ojs omp ops | lib/pkp/templates/frontend/pages/userRegisterComplete.tpl link `user.editMyProfile` | Registration complete · "Edit my profile" link · to user/profile |
| AFFU-034 | ojs omp ops | lib/pkp/templates/frontend/pages/userRegisterComplete.tpl link `user.login.registrationComplete.continueBrowsing` | Registration complete · "Continue browsing" link · to site index |
| AFFU-035 | ojs omp ops | lib/pkp/templates/frontend/pages/userConfirmActivation.tpl `a.pkp_button` key `user.login.activate` | Confirm activation · "Activate account" button-link · navigates to `$activationUrl` |

## Lost password

| ID | apps | pointer | description |
|----|------|---------|-------------|
| AFFU-036 | ojs omp ops | lib/pkp/templates/frontend/pages/userLostPassword.tpl `input#email` | Lost password · registered-email field · required Claimed by: login-and-sessions. |
| AFFU-037 | ojs omp ops | lib/pkp/templates/frontend/pages/userLostPassword.tpl `altcha-widget` | Lost password · ALTCHA widget · shown when `{if $altchaEnabled}` Claimed by: login-and-sessions. |
| AFFU-038 | ojs omp ops | lib/pkp/templates/frontend/pages/userLostPassword.tpl `button.submit` key `user.login.resetPassword` | Lost password · "Reset password" submit · POSTs to login/requestResetPassword Claimed by: login-and-sessions. |
| AFFU-039 | ojs omp ops | lib/pkp/templates/frontend/pages/userLostPassword.tpl `a.register` key `user.login.registerNewAccount` | Lost password · Register link · shown when `{if !$disableUserReg}` Claimed by: login-and-sessions. |

## Password reset (hash link landing)

| ID | apps | pointer | description |
|----|------|---------|-------------|
| AFFU-040 | ojs omp ops | lib/pkp/templates/user/userPasswordReset.tpl fbvElement `password` | Password reset · new password field · required, maxlength 32 Claimed by: login-and-sessions. |
| AFFU-041 | ojs omp ops | lib/pkp/templates/user/userPasswordReset.tpl fbvElement `password2` | Password reset · repeat new password field · required, maxlength 32 Claimed by: login-and-sessions. |
| AFFU-042 | ojs omp ops | lib/pkp/templates/user/userPasswordReset.tpl hidden fbvElements `username`, `hash` | Password reset · hidden username + reset-hash fields · carried in form `#updateResetPassword` Claimed by: login-and-sessions. |
| AFFU-043 | ojs omp ops | lib/pkp/templates/user/userPasswordReset.tpl `{fbvFormButtons submitText="common.save" hideCancel=true}` | Password reset · Save submit (no cancel) · POSTs login/updateResetPassword Claimed by: login-and-sessions. |

## Login-forced password change

| ID | apps | pointer | description |
|----|------|---------|-------------|
| AFFU-044 | ojs omp ops | lib/pkp/templates/user/loginChangePassword.tpl fbvElement `username` | Login change-password · username field · required, maxlength 32 Claimed by: login-and-sessions. |
| AFFU-045 | ojs omp ops | lib/pkp/templates/user/loginChangePassword.tpl fbvElement `oldPassword` | Login change-password · current password field · required Claimed by: login-and-sessions. |
| AFFU-046 | ojs omp ops | lib/pkp/templates/user/loginChangePassword.tpl fbvElement `password` | Login change-password · new password field · required Claimed by: login-and-sessions. |
| AFFU-047 | ojs omp ops | lib/pkp/templates/user/loginChangePassword.tpl fbvElement `password2` | Login change-password · repeat new password field · required Claimed by: login-and-sessions. |
| AFFU-048 | ojs omp ops | lib/pkp/templates/user/loginChangePassword.tpl `{fbvFormButtons hideCancel=true}` (default submitText `common.ok`) | Login change-password · OK submit · POSTs login/savePassword Claimed by: login-and-sessions. |

## Confirm password gate

| ID | apps | pointer | description |
|----|------|---------|-------------|
| AFFU-049 | ojs omp ops | lib/pkp/templates/user/confirmPassword.tpl fbvElement `password` | Confirm password gate · password field · required, autocomplete off Claimed by: login-and-sessions. |
| AFFU-050 | ojs omp ops | lib/pkp/templates/user/confirmPassword.tpl hidden inputs `cancelUrl`, `source`, `isActionRequest` | Confirm password gate · hidden routing fields · `isActionRequest` only when `{if $isActionRequest}` Claimed by: login-and-sessions. |
| AFFU-051 | ojs omp ops | lib/pkp/templates/user/confirmPassword.tpl `a.pkp_button` key `common.cancel` | Confirm password gate · Cancel button-link · navigates to `$cancelUrl` Claimed by: login-and-sessions. |
| AFFU-052 | ojs omp ops | lib/pkp/templates/user/confirmPassword.tpl `button.pkp_button_primary` key `form.submit` | Confirm password gate · Submit button · POSTs form `#confirmPassword` to `$submitUrl` Claimed by: login-and-sessions. |

## Profile — page shell

Tabs load via `tab.user.ProfileTabHandler` component ops.

| ID | apps | pointer | description |
|----|------|---------|-------------|
| AFFU-053 | ojs omp ops | lib/pkp/templates/user/profile.tpl `#profileTabs` a[name=identity] | Profile shell · tab "Identity" (`user.profile.identity`) · loads identity form tab |
| AFFU-054 | ojs omp ops | lib/pkp/templates/user/profile.tpl `#profileTabs` a[name=contact] | Profile shell · tab "Contact" (`user.profile.contact`) · loads contact form tab |
| AFFU-055 | ojs omp ops | lib/pkp/templates/user/profile.tpl `#profileTabs` a[name=roles] | Profile shell · tab "Roles" (`user.roles`) · loads roles form tab |
| AFFU-056 | ojs omp ops | lib/pkp/templates/user/profile.tpl `#profileTabs` a[name=publicProfile] | Profile shell · tab "Public" (`user.profile.public`) · loads public profile form tab |
| AFFU-057 | ojs omp ops | lib/pkp/templates/user/profile.tpl `#profileTabs` a[name=changePassword] | Profile shell · tab "Password" (`user.password`) · loads change-password form tab |
| AFFU-058 | ojs omp ops | lib/pkp/templates/user/profile.tpl `#profileTabs` a[name=notificationSettings] | Profile shell · tab "Notifications" (`notification.notifications`) · loads notification settings tab |
| AFFU-059 | ojs omp ops | lib/pkp/templates/user/profile.tpl `#profileTabs` a[name=apiSettings] | Profile shell · tab "API Key" (`user.apiKey`) · loads API profile tab |
| AFFU-060 | ojs omp ops | lib/pkp/templates/user/profile.tpl `<Notification>` future-role banner | Profile shell · static banner (`user.futureRole.notification.message`), no controls · guard `{if $userFutureRoleStartDate}` |

## Profile — Identity tab

Form class `PKP\user\form\IdentityForm`; posts to `saveIdentity`.

| ID | apps | pointer | description |
|----|------|---------|-------------|
| AFFU-061 | ojs omp ops | lib/pkp/templates/user/identityForm.tpl `#identityForm` username display | Identity · read-only username display (`user.username`) |
| AFFU-062 | ojs omp ops | lib/pkp/templates/user/identityForm.tpl fields `givenName`/`familyName` | Identity · multilingual given/family name pair (`user.givenName` required, `user.familyName`) |
| AFFU-063 | ojs omp ops | lib/pkp/templates/user/identityForm.tpl field `preferredPublicName` | Identity · multilingual text field (`user.preferredPublicName`) · sets display name |
| AFFU-064 | ojs omp ops | lib/pkp/templates/user/identityForm.tpl field `preferredAvatarInitials` | Identity · 2-char text field (`user.preferredAvatarInitials`) · JS auto-uppercases on keyup |
| AFFU-065 | ojs omp ops | lib/pkp/templates/user/identityForm.tpl field `orcid` | Identity · ORCID text field (`user.orcid`) · guard `{if $orcidEnabled}`; hidden by orcidProfile.tpl JS when `$targetOp eq 'profile'` Claimed by: orcid-integration. |
| AFFU-066 | ojs omp ops | lib/pkp/templates/user/identityForm.tpl `#deleteOrcidButton` | Identity · button "Delete" (`common.delete`) · confirm modal (`orcid.field.deleteOrcidModal.message`) then submits with injected `removeOrcidId` checkbox · guard `{if $orcidEnabled}` + `{if $orcid && $orcidAuthenticated}` Claimed by: orcid-integration. |
| AFFU-067 | ojs omp ops | lib/pkp/templates/user/identityForm.tpl privacy link `user.privacyLink` | Identity · link to about/privacy page |
| AFFU-068 | ojs omp ops | lib/pkp/templates/user/identityForm.tpl `{fbvFormButtons submitText="common.save"}` | Identity · button "Save" · submits to `saveIdentity` (cancel hidden) |

## Profile — Contact tab

Form class `PKP\user\form\ContactForm`; posts to `saveContact`.

| ID | apps | pointer | description |
|----|------|---------|-------------|
| AFFU-069 | ojs omp ops | lib/pkp/templates/user/contactForm.tpl button `name=action value=cancelPendingEmail` | Contact · button "Cancel" (`common.cancel`) · cancels a pending email change · guard `{if $changeEmailPending}` (renders hidden `pendingEmail` + message `user.pendingEmailChange`) |
| AFFU-070 | ojs omp ops | lib/pkp/templates/user/contactForm.tpl field `email` | Contact · email field (`user.email`, required) · readonly when `$changeEmailPending` |
| AFFU-071 | ojs omp ops | lib/pkp/templates/user/contactForm.tpl field `signature` | Contact · multilingual rich textarea (`user.signature`) |
| AFFU-072 | ojs omp ops | lib/pkp/templates/user/contactForm.tpl field `phone` | Contact · tel field (`user.phone`) |
| AFFU-073 | ojs omp ops | lib/pkp/templates/user/contactForm.tpl field `affiliation` | Contact · multilingual text field (`user.affiliation`) |
| AFFU-074 | ojs omp ops | lib/pkp/templates/user/contactForm.tpl field `mailingAddress` | Contact · rich textarea (`common.mailingAddress`) |
| AFFU-075 | ojs omp ops | lib/pkp/templates/user/contactForm.tpl select `country` | Contact · country dropdown (`common.country`, required) · options from `$countries` |
| AFFU-076 | ojs omp ops | lib/pkp/templates/user/contactForm.tpl checkboxes `locales[]` (`#locales-$localeKey`) | Contact · working-languages checkbox group (`user.workingLanguages`), one per site locale · guard `{if count($availableLocales) > 1}` |
| AFFU-077 | ojs omp ops | lib/pkp/templates/user/contactForm.tpl privacy link + `{fbvFormButtons submitText="common.save"}` | Contact · privacy link + button "Save" · submits to `saveContact` |

## Profile — Roles tab

Form class `PKP\user\form\RolesForm`; posts to `saveRoles`; includes `user/userGroups.tpl` → `user/userGroupSelfRegistration.tpl` (same partial the registration flow's role chunk uses).

| ID | apps | pointer | description |
|----|------|---------|-------------|
| AFFU-078 | ojs omp ops | lib/pkp/templates/user/userGroupSelfRegistration.tpl checkboxes `readerGroup[$userGroupId]` | Roles · reader-role self-registration checkboxes, one per reader group · guard `{if $userGroup->permitSelfRegistration}` |
| AFFU-079 | ojs omp ops | lib/pkp/templates/user/userGroupSelfRegistration.tpl checkboxes `authorGroup[$userGroupId]` | Roles · author-role self-registration checkboxes, one per author group · guard `{if $userGroup->permitSelfRegistration}` |
| AFFU-080 | ojs omp ops | lib/pkp/templates/user/userGroupSelfRegistration.tpl checkboxes `reviewerGroup[$userGroupId]` | Roles · reviewer-role self-registration checkboxes, one per reviewer group · guard `{if $userGroup->permitSelfRegistration}` |
| AFFU-081 | ojs omp ops | lib/pkp/templates/user/userGroups.tpl `#userGroupExtras` extras-on-demand toggle | Roles · expander "show/hide other contexts" (`user.profile.form.showOtherContexts`/`hideOtherContexts`) · reveals per-context role sections · guards `{if $showOtherContexts}` + `{if $currentContext}` (without current context the sections render inline, no toggle) |
| AFFU-082 | ojs omp | lib/pkp/templates/user/userGroups.tpl field `interests` (`type="interests"`) | Roles · reviewing-interests tag input (`user.interests`) · guard `{if !$disableInterestsSection}` — `RolesForm` assigns `disableInterestsSection = (app name === 'ops')`, so absent in OPS |
| AFFU-083 | ojs omp ops | lib/pkp/templates/user/rolesForm.tpl privacy link + `{fbvFormButtons submitText="common.save"}` | Roles · privacy link + button "Save" · submits to `saveRoles` |

## Profile — Public tab

Form class `PKP\user\form\PublicProfileForm`; posts to `savePublicProfile`; hook `User::PublicProfile::AdditionalItems` may inject plugin fields.

| ID | apps | pointer | description |
|----|------|---------|-------------|
| AFFU-084 | ojs omp ops | lib/pkp/templates/user/publicProfileForm.tpl `#plupload` upload container | Public · profile-image file upload (plupload; jpg/jpeg/png/gif, resize/crop to `$profileImageMaxWidth/Height`) · uploads to `uploadProfileImage` |
| AFFU-085 | ojs omp ops | lib/pkp/templates/user/publicProfileForm.tpl delete button → `#deleteProfileImageForm` | Public · button "Delete" (`common.delete`) next to current image · separate POST to `deleteProfileImage` · guard `{if $profileImage}` |
| AFFU-086 | ojs omp ops | lib/pkp/templates/user/publicProfileForm.tpl field `biography` | Public · multilingual rich textarea (`user.biography`) |
| AFFU-087 | ojs omp ops | lib/pkp/templates/user/publicProfileForm.tpl field `userUrl` | Public · text field (`user.url`) · homepage URL |
| AFFU-088 | ojs omp ops | lib/pkp/templates/user/publicProfileForm.tpl privacy link + `{fbvFormButtons submitText="common.save"}` | Public · privacy link + button "Save" · submits to `savePublicProfile` |

## Profile — Password tab

`lib/pkp/templates/user/changePassword.tpl` (posts to `savePassword`); the same template also serves the AJAX change-password form outside the tab — controls identical, enumerated once here.

| ID | apps | pointer | description |
|----|------|---------|-------------|
| AFFU-089 | ojs omp ops | lib/pkp/templates/user/changePassword.tpl field `oldPassword` | Password · current-password field (`user.profile.oldPassword`) |
| AFFU-090 | ojs omp ops | lib/pkp/templates/user/changePassword.tpl fields `password`/`password2` | Password · new password + repeat pair · sublabel `user.register.form.passwordLengthRestriction` with `$minPasswordLength` |
| AFFU-091 | ojs omp ops | lib/pkp/templates/user/changePassword.tpl `{fbvFormButtons submitText="common.save"}` | Password · buttons "Save" + "Cancel" (this form does NOT pass `hideCancel`, unlike other profile tabs) · submits to `savePassword` |
| AFFU-092 | ojs omp ops | lib/pkp/templates/user/changePassword.tpl privacy link `user.privacyLink` | Password · link to about/privacy page |

## Profile — Notifications tab

Template loops generically over `$notificationSettingCategories` (form classes `PKPNotificationSettingsForm` + per-app `NotificationSettingsForm`); each setting row renders an allow checkbox and an email checkbox JS-slaved to it. Per-app category lists (from PHP, mechanical): base = `notification.type.public` → NEW_ANNOUNCEMENT; `notification.type.submissions` → SUBMISSION_SUBMITTED, PUBLICATION_PUBLISHED, EDITOR_ASSIGNMENT_REQUIRED, NEW_QUERY, QUERY_ACTIVITY; `notification.type.reviewing` → REVIEWER_COMMENT; `user.role.editors` → EDITORIAL_REMINDER + EDITORIAL_REPORT (guard `$context && $context->getData('editorialStatsEmail')`); hook `...::getNotificationSettingCategories` can extend. OJS appends NOTIFICATION_TYPE_PUBLISHED_ISSUE + NOTIFICATION_TYPE_OPEN_ACCESS to the public category; OMP/OPS subclasses are empty. The `ops-main/templates/user/notificationSettingsForm.tpl` override is a pure `{include file="core:user/notificationSettingsForm.tpl"}` pass-through (zero control delta).

| ID | apps | pointer | description |
|----|------|---------|-------------|
| AFFU-093 | ojs omp ops | lib/pkp/templates/user/notificationSettingsForm.tpl checkbox `$settingName` per setting | Notifications · "Enable these types of notifications" checkbox (`notification.allow`), one per notification type · unchecking blocks the type |
| AFFU-094 | ojs omp ops | lib/pkp/templates/user/notificationSettingsForm.tpl checkbox `$emailSettingName` per setting | Notifications · "Send me an email" checkbox (`notification.email`), one per type · JS-disabled when its allow-pair is unchecked |
| AFFU-095 | ojs omp ops | lib/pkp/templates/user/notificationSettingsForm.tpl privacy link + `{fbvFormButtons submitText="common.save"}` | Notifications · privacy link + button "Save" · submits to `saveNotificationSettings` |

## Profile — API key tab

Form class `PKP\user\form\APIProfileForm`; posts to `saveAPIProfile`.

| ID | apps | pointer | description |
|----|------|---------|-------------|
| AFFU-096 | ojs omp ops | lib/pkp/templates/user/apiProfileForm.tpl field `apiKey` | API key · read-only field showing current key or "None" (`common.none`) |
| AFFU-097 | ojs omp ops | lib/pkp/templates/user/apiProfileForm.tpl submit button (`$apiKeyActionTextKey`) | API key · single toggle button: generates key when `$apiKeyAction === API_KEY_NEW`, deletes key (JS `confirm()` on `user.apiKey.remove.confirmation.message`) when `API_KEY_DELETE` · guard `{if !$apiSecretMissing}` (absent when config `api_key_secret` unset); companion warning `user.apiKey.generateWarning`/`removeWarning` |
| AFFU-098 | ojs omp ops | lib/pkp/templates/user/apiProfileForm.tpl privacy link `user.privacyLink` | API key · link to about/privacy page |

## ORCID inline (form/orcidProfile.tpl — identity tab + registration)

Assigned by `IdentityForm` with `targetOp='profile'` and `RegistrationForm` with `targetOp='register'`.

| ID | apps | pointer | description |
|----|------|---------|-------------|
| AFFU-099 | ojs omp ops | lib/pkp/templates/form/orcidProfile.tpl `#connect-orcid-button` (authorise variant) | ORCID inline · button "Authorise" (`orcid.authorise`) + unauthenticated-icon link to `$orcid` · guard `{if $orcid && !$orcidAuthenticated}` · runs `openORCID()` (ORCID logout ping then OAuth popup to `$orcidOAuthUrl`) Claimed by: orcid-integration. |
| AFFU-100 | ojs omp ops | lib/pkp/templates/form/orcidProfile.tpl `#connect-orcid-button` (connect variant) | ORCID inline · button "Connect" (`orcid.connect`) · guard `{else}` (no unauthenticated orcid) · same `openORCID()` OAuth popup Claimed by: orcid-integration. |
| AFFU-101 | ojs omp ops | lib/pkp/templates/form/orcidProfile.tpl about link (`orcid.about.title`) | ORCID inline · link to page=orcid op=about · `onclick="return openORCID();"` intercepts and opens the OAuth popup instead Claimed by: orcid-integration. |
| AFFU-102 | ojs omp ops | lib/pkp/templates/form/orcidProfile.tpl `#orcid-link` | ORCID inline · authenticated ORCID display link (icon + `$orcidDisplayValue`, opens orcid.org) · guard `{if $orcidAuthenticated}` Claimed by: orcid-integration. |
| AFFU-103 | ojs omp ops | lib/pkp/templates/form/orcidProfile.tpl hidden field `orcid` (register variant) | ORCID inline · hidden orcid input + connect button rendered inline · guard `{if $targetOp eq 'register'}`; in profile mode JS instead hides identityForm's `input[name=orcid]` and injects link-or-button after it Claimed by: orcid-integration. |

## ORCID frontend pages

| ID | apps | pointer | description |
|----|------|---------|-------------|
| AFFU-104 | ojs omp ops | lib/pkp/templates/frontend/pages/orcidAbout.tpl | ORCID about page · static informational page, no controls of its own (chrome is AFFR) · content varies on `{if $isMemberApi}` Claimed by: orcid-integration. |
| AFFU-105 | ojs omp ops | lib/pkp/templates/frontend/pages/orcidVerify.tpl orcid link | ORCID verify page · link to verified `$orcid` on orcid.org · guard `{if $verifySuccess}`; otherwise static branch messages (`$sendSubmission`, `$submissionNotPublished`, `$orcidAPIError`, `$invalidClient`, `$duplicateOrcid`, `$denied`, `$authFailure`) + JS auto-redirect to `$currentUrl` after 10s when `$verifySuccess` Claimed by: orcid-integration. |

## ORCID FieldOrcid component (Vue forms)

`lib/ui-library/src/components/Form/fields/FieldOrcid.vue` (extends FieldBase; used in Vue forms, e.g. contributor forms — the legacy profile identity tab uses the Smarty surface above).

| ID | apps | pointer | description |
|----|------|---------|-------------|
| AFFU-106 | ojs omp ops | components/Form/fields/FieldOrcid.vue orcid display link (`orcidDisplayText`) | FieldOrcid · `<a target="_blank">` to the ORCID URL · `v-if="hasOrcid"` Claimed by: orcid-integration. |
| AFFU-107 | ojs omp ops | components/Form/fields/FieldOrcid.vue delete PkpButton (`common.delete`) | FieldOrcid · button "Delete" · confirm dialog `deleteOrcid`, on Yes POSTs `orcid/deleteForAuthor/{authorId}` · `v-if="hasOrcid"`, `:is-disabled="isButtonDisabled"` Claimed by: orcid-integration. |
| AFFU-108 | ojs omp ops | components/Form/fields/FieldOrcid.vue request-verification PkpButton (`orcid.field.verification.request`/`.requested`) | FieldOrcid · button "Request verification" · confirm dialog `sendAuthorEmail`, on Yes POSTs `orcid/requestAuthorVerification/{authorId}` (defers via `currentValue='shouldRequestVerification'` when `authorId` is 0) · `v-if="!hasOrcid"`, `:disabled="verificationRequested \|\| isButtonDisabled"` Claimed by: orcid-integration. |
| AFFU-109 | ojs omp ops | components/Form/fields/FieldOrcid.vue resend PkpButton (`orcid.field.verification.resendRequest`) | FieldOrcid · link-style "Resend" button · reopens send-email dialog · `v-if="!hasOrcid && verificationRequested"` Claimed by: orcid-integration. |
| AFFU-110 | ojs omp ops | components/Form/fields/FieldOrcid.vue unverified description | FieldOrcid · static description `orcid.field.unverified.shouldRequest` · `v-if="!isVerified && hasOrcid"` (verified/unverified icon swap on same guards) Claimed by: orcid-integration. |

## Notifications — tasks panel (backend header)

`templates/controllers/page/tasks.tpl` loads `TaskNotificationsGridHandler::fetchGrid`; grid controls are declared in `PKP\controllers\grid\notifications\NotificationsGridHandler` (PHP, not .tpl — cited by class symbol).

| ID | apps | pointer | description |
|----|------|---------|-------------|
| AFFU-111 | ojs omp ops | lib/pkp/templates/controllers/page/tasks.tpl `load_url_in_div #notificationsGrid` | Tasks panel · loads TaskNotificationsGridHandler fetchGrid (template itself has no direct controls) |
| AFFU-112 | ojs omp ops | `NotificationsGridHandler::initFeatures` SelectableItemsFeature `selectedNotifications` | Tasks grid · per-row selection checkbox |
| AFFU-113 | ojs omp ops | `NotificationsGridHandler` LinkAction `markNew` key `grid.action.markNew` | Tasks grid · "Mark new" action on selected rows · POST markNewUrl |
| AFFU-114 | ojs omp ops | `NotificationsGridHandler` LinkAction `markRead` key `grid.action.markRead` | Tasks grid · "Mark read" action on selected rows · POST markReadUrl; clicking a notification title also routes through markRead with `redirect` |
| AFFU-115 | ojs omp ops | `NotificationsGridHandler` LinkAction `deleteNotification` key `grid.action.delete` | Tasks grid · "Delete" action on selected rows · POST deleteUrl (deleteNotifications) |
| AFFU-116 | ojs omp ops | `NotificationsGridHandler::initFeatures` PagingFeature | Tasks grid · pagination controls |
| AFFU-117 | ojs omp ops | lib/pkp/templates/controllers/notification/notificationOptions.tpl | Notification options · JS config fragment only (fetchNotificationUrl, requestOptions JSON) — zero visible controls |

## Notifications — email unsubscribe

| ID | apps | pointer | description |
|----|------|---------|-------------|
| AFFU-118 | ojs omp ops | lib/pkp/templates/notification/unsubscribeNotificationsForm.tpl checkboxes `$emailSetting.emailSettingName` | Email unsubscribe · one checkbox per email setting (foreach `$emailSettings`), checked by default |
| AFFU-119 | ojs omp ops | lib/pkp/templates/notification/unsubscribeNotificationsForm.tpl hidden inputs `validate`, `id` | Email unsubscribe · hidden validation token + notification id |
| AFFU-120 | ojs omp ops | lib/pkp/templates/notification/unsubscribeNotificationsForm.tpl `button.submit` key `notification.unsubscribeNotifications` | Email unsubscribe · Unsubscribe submit · POSTs notification/unsubscribe |
| AFFU-121 | ojs omp ops | lib/pkp/templates/notification/unsubscribeNotificationsResult.tpl | Email unsubscribe result · message only, no controls · profile link embedded in translate string (`profileNotificationUrl`); branch on `{if $unsubscribeResult}` |

## Invitation accept / decline

Smarty shells + Vue `pages/acceptInvitation/` (the send-invitation wizard is AFFM).

| ID | apps | pointer | description |
|----|------|---------|-------------|
| AFFU-122 | ojs omp ops | lib/pkp/templates/invitation/acceptInvitation.tpl `accept-invitation-page` element | Accept invitation · mounts Vue AcceptInvitationPage with steps/invitationId/invitationKey props (backend layout) · Claimed by: user-invitations. |
| AFFU-123 | ojs omp ops | lib/pkp/templates/invitation/declineInvitation.tpl fbvElement submit `#declineInvitationSubmit` key `invitation.decline.confirm` | Decline invitation · "Decline" confirm submit · POSTs to `$declineUrl` · Claimed by: user-invitations. |
| AFFU-124 | ojs omp ops | lib/pkp/templates/invitation/invitationUnavailable.tpl `a.pkpButton` key `user.login` | Invitation unavailable · Login button-link · to `$loginUrl` · Claimed by: user-invitations. |
| AFFU-125 | ojs omp ops | lib/pkp/templates/invitation/invitationUnavailable.tpl `a.pkpButton` key `user.login.registerNewAccount` | Invitation unavailable · Register button-link · to `$registerUrl` · Claimed by: user-invitations. |
| AFFU-126 | ojs omp ops | pages/acceptInvitation/AcceptInvitationPage.vue `Steps @step:open` | Accept-invitation wizard · step tabs · clicking a started step opens it via `store.openStep` · Claimed by: user-invitations. |
| AFFU-127 | ojs omp ops | pages/acceptInvitation/AcceptInvitationPage.vue PkpButton key `common.cancel` (`store.cancel`) | Accept-invitation wizard · Cancel button · confirm dialog with `acceptInvitation.cancelInvite.button` (redirect away) / `userInvitation.cancel.goBack` (close) · Claimed by: user-invitations. |
| AFFU-128 | ojs omp ops | pages/acceptInvitation/AcceptInvitationPage.vue PkpButton key `common.back` (`store.previousStep`) | Accept-invitation wizard · Back button · `v-if="!store.isOnFirstStep"` · Claimed by: user-invitations. |
| AFFU-129 | ojs omp ops | pages/acceptInvitation/AcceptInvitationPage.vue PkpButton `store.stepButtonTitle` (`store.nextStep`) | Accept-invitation wizard · Continue/Accept primary button · label is server-provided `currentStep.nextButtonLabel` · `v-if="store.currentStep.id !== 'verifyOrcid'"` · Claimed by: user-invitations. |
| AFFU-130 | ojs omp ops | pages/acceptInvitation/AcceptInvitationVerifyOrcid.vue PkpButton key `acceptInvitation.verifyOrcid` | Accept invitation · "Verify ORCID" button · opens ORCID OAuth popup (`props.orcidOAuthUrl`) · Claimed by: user-invitations. |
| AFFU-131 | ojs omp ops | pages/acceptInvitation/AcceptInvitationVerifyOrcid.vue PkpButton key `acceptInvitation.skipVerifyOrcid` | Accept invitation · "Skip ORCID verification" button · advances via `store.openStep` · Claimed by: user-invitations. |
| AFFU-132 | ojs omp ops | pages/acceptInvitation/AcceptInvitationUserAccountDetails.vue FieldText `username` | Accept invitation · username field · required, prefilled from payload · Claimed by: user-invitations. |
| AFFU-133 | ojs omp ops | pages/acceptInvitation/AcceptInvitationUserAccountDetails.vue FieldText `password` (input-type password) | Accept invitation · password field · required, description shows MIN_PASSWORD_LENGTH · Claimed by: user-invitations. |
| AFFU-134 | ojs omp ops | pages/acceptInvitation/AcceptInvitationUserAccountDetails.vue FieldOptions `privacyStatement` (checkbox) | Accept invitation · privacy-consent checkbox · required; label links to about/privacy · Claimed by: user-invitations. |
| AFFU-135 | ojs omp ops | pages/acceptInvitation/AcceptInvitationUserDetailsForms.vue PkpForm | Accept invitation · user-details form fields group · fields come from the server form object (`props.form`); payload synced via `updateUserDetailsForm` · Claimed by: user-invitations. |
| AFFU-136 | ojs omp ops | pages/acceptInvitation/AcceptInvitationReview.vue PkpButton key `common.edit` (`openStep 'userDetails'`) | Accept-invitation review · Edit user-details button · shown in `v-else` branch of `v-if="store.userId != null"` (new users only) · Claimed by: user-invitations. |
| AFFU-137 | ojs omp ops | pages/acceptInvitation/AcceptInvitationReview.vue + AcceptInvitationUserRoles.vue + AcceptInvitationFormDisplay.vue + AcceptInvitationFormDisplayItemBasic.vue + AcceptInvitationFieldTextDisplay.vue + AcceptInvitationFieldSelectDisplay.vue | Accept-invitation review · read-only summaries (username, ORCID, user-details display, roles table) · display-only · Claimed by: user-invitations. |

## Reviewer wizard — step header

Wizard templates are `ojs omp`: OPS has no `pages/reviewer/` dispatcher. Tab enablement driven by `$.pkp.pages.reviewer.ReviewerTabHandler` init args `reviewStep`/`selected`.

| ID | apps | pointer | description |
|----|------|---------|-------------|
| AFFU-138 | ojs omp | lib/pkp/templates/reviewer/review/reviewStepHeader.tpl `#reviewTabs` a `{url op="step" step=1}` key `reviewer.reviewSteps.request` | Wizard header · step tab 1 "Request" |
| AFFU-139 | ojs omp | lib/pkp/templates/reviewer/review/reviewStepHeader.tpl `#reviewTabs` a `{url op="step" step=2}` key `reviewer.reviewSteps.guidelines` | Wizard header · step tab 2 "Guidelines" |
| AFFU-140 | ojs omp | lib/pkp/templates/reviewer/review/reviewStepHeader.tpl `#reviewTabs` a `{url op="step" step=3}` key `reviewer.reviewSteps.download` | Wizard header · step tab 3 "Download & Review" |
| AFFU-141 | ojs omp | lib/pkp/templates/reviewer/review/reviewStepHeader.tpl `#reviewTabs` a `{url op="step" step=4}` key `reviewer.reviewSteps.completion` | Wizard header · step tab 4 "Completion" |
| AFFU-142 | ojs omp | lib/pkp/templates/reviewer/review/reviewStepHeader.tpl `<reviewer-submission-page v-bind="pageInitConfig">` mount | Wizard header · embeds ReviewerSubmissionPage.vue (round-history box, AFFU-192–196) above the tabs |

## Reviewer wizard — step 1 (request)

| ID | apps | pointer | description |
|----|------|---------|-------------|
| AFFU-143 | ojs omp | lib/pkp/templates/reviewer/review/step1.tpl form `#reviewStep1Form` action `{url op="saveStep" step="1"}` | Step 1 · form · posts accept/CI/consent data via AjaxFormHandler |
| AFFU-144 | ojs omp | lib/pkp/templates/reviewer/review/step1.tpl `{load_url_in_div id="reviewFilesStep1"}` → `grid.files.review.ReviewerReviewFilesGridHandler` | Step 1 · review-files grid · lists/downloads files under review · guard `{if !$restrictReviewerFileAccess}` |
| AFFU-145 | ojs omp | lib/pkp/templates/reviewer/review/step1.tpl linkAction `$viewMetadataAction` (contextId `reviewStep1Form`) | Step 1 · "View All Submission Details" link · opens submission metadata modal |
| AFFU-146 | ojs omp | lib/pkp/templates/reviewer/review/step1.tpl readonly fbvElements `dateNotified`, `responseDue`, `dateDue` | Step 1 · review-schedule fields · display request/response-due/review-due dates (readonly) |
| AFFU-147 | ojs omp | lib/pkp/templates/reviewer/review/step1.tpl linkAction `$aboutDueDatesAction` (contextId `reviewStep1`) | Step 1 · "About Due Dates" link · opens due-dates help modal |
| AFFU-148 | ojs omp | lib/pkp/templates/reviewer/review/step1.tpl linkAction `$competingInterestsAction` | Step 1 · competing-interests policy link · opens CI policy modal · guard `{if $competingInterestsAction}` |
| AFFU-149 | ojs omp | lib/pkp/templates/reviewer/review/step1.tpl radio `#noCompetingInterests` name `competingInterestOption` key `reviewer.submission.noCompetingInterests` | Step 1 · CI radio "no competing interests" · hides CI textarea · guard `{if $currentContext->getData('competingInterests')}`, `disabled=$reviewIsClosed` |
| AFFU-150 | ojs omp | lib/pkp/templates/reviewer/review/step1.tpl radio `#hasCompetingInterests` name `competingInterestOption` key `reviewer.submission.hasCompetingInterests` | Step 1 · CI radio "has competing interests" · shows CI textarea · same guards |
| AFFU-151 | ojs omp | lib/pkp/templates/reviewer/review/step1.tpl rich textarea `#reviewerCompetingInterests` (section `#reviewerCompetingInterestsContainer`) | Step 1 · CI statement rich text entry · guard `{if $currentContext->getData('competingInterests')}`, `disabled=$reviewIsClosed` |
| AFFU-152 | ojs omp | lib/pkp/templates/reviewer/review/step1.tpl checkbox `#dataCollectionCheck` name `privacyConsent` key `user.register.form.privacyConsent` | Step 1 · privacy-consent checkbox (required) · guard `{if !$reviewAssignment->getDateConfirmed() && $currentContext->getData('privacyStatement')}` |
| AFFU-153 | ojs omp | lib/pkp/templates/reviewer/review/step1.tpl `{fbvFormButtons submitText="reviewer.submission.acceptReview" cancelText="reviewer.submission.declineReview" cancelAction=$declineReviewAction}` | Step 1 · "Accept Review, Continue to Step #2" submit + "Decline Review Request" button (opens regretMessage modal, AFFU-176–178) · guard `{elseif !$reviewAssignment->getDateConfirmed()}`, `submitDisabled=$reviewIsClosed` |
| AFFU-154 | ojs omp | lib/pkp/templates/reviewer/review/step1.tpl `{fbvFormButtons hideCancel=true submitText="common.saveAndContinue"}` | Step 1 · "Save and continue" submit (already-accepted state) · guard `{if $reviewAssignment->getDateConfirmed()}`, `submitDisabled=$reviewIsClosed` |
| AFFU-155 | ojs | ojs-main/templates/reviewer/review/step1.tpl `{assign var=descriptionFieldKey value="article.abstract"}` + include `core:reviewer/review/step1.tpl` | Step 1 (OJS delta) · abstract section labeled `article.abstract` · no control added/removed |
| AFFU-156 | omp | omp-main/templates/reviewer/review/step1.tpl `{assign var=descriptionFieldKey value="submission.description"}` + include core | Step 1 (OMP delta) · description section labeled `submission.description` · no control added/removed |

## Reviewer wizard — step 2 (guidelines)

| ID | apps | pointer | description |
|----|------|---------|-------------|
| AFFU-157 | ojs omp | lib/pkp/templates/reviewer/review/step2.tpl form `#reviewStep2Form` action `{url op="saveStep" step="2"}` | Step 2 · form wrapping read-only guidelines display (`$reviewerGuidelines`) |
| AFFU-158 | ojs omp | lib/pkp/templates/reviewer/review/step2.tpl `{fbvFormButtons submitText="reviewer.submission.continueToStepThree" cancelText="navigation.goBack" cancelUrl=…step=1}` | Step 2 · "Continue to Step #3" submit + "Go Back" link to step 1 · `submitDisabled=$reviewIsClosed` |

## Reviewer wizard — step 3 (download & review)

| ID | apps | pointer | description |
|----|------|---------|-------------|
| AFFU-159 | ojs omp | lib/pkp/templates/reviewer/review/step3.tpl form `#reviewStep3Form` action `{url op="saveStep" step="3"}` + hidden input `isSave` | Step 3 · form via `ReviewerReviewStep3FormHandler` (`isSave` distinguishes save-for-later vs submit) |
| AFFU-160 | ojs omp | lib/pkp/templates/reviewer/review/step3.tpl `{load_url_in_div id="reviewFilesStep3"}` → `grid.files.review.ReviewerReviewFilesGridHandler` | Step 3 · review-files grid · download files under review (no restrict guard here, unlike step 1) |
| AFFU-161 | ojs omp | lib/pkp/templates/reviewer/review/step3.tpl linkAction `$viewGuidelinesAction` (`#viewGuidelines`) | Step 3 · "Reviewer Guidelines" link · opens guidelines modal · guard `{if $viewGuidelinesAction}` |
| AFFU-162 | ojs omp | lib/pkp/templates/reviewer/review/step3.tpl rich textarea `#comments` name `comments` label `submission.comments.canShareWithAuthor` | Step 3 · review text for author+editor · guard `{else}` of `{if $reviewForm}` (only when no review form configured) · `readonly=$reviewIsClosed` |
| AFFU-163 | ojs omp | lib/pkp/templates/reviewer/review/step3.tpl rich textarea `#commentsPrivate` name `commentsPrivate` label `submission.comments.cannotShareWithAuthor` | Step 3 · editor-only review text · same guard |
| AFFU-164 | ojs omp | lib/pkp/templates/reviewer/review/step3.tpl `{load_url_in_div id="reviewAttachmentsGridContainer"}` → `grid.files.attachment.ReviewerReviewAttachmentsGridHandler` | Step 3 · reviewer file upload grid (upload/manage own attachments) · passes `reviewIsClosed` |
| AFFU-165 | ojs omp | lib/pkp/templates/reviewer/review/step3.tpl `<discussion-manager-reviewer>` mount `#discussionManager-{$uuid}` | Step 3 · discussions panel (AFFU-181–191) |
| AFFU-166 | ojs omp | lib/pkp/templates/reviewer/review/step3.tpl `{$additionalFormFields}` placeholder (before `#reviewStep3Actions`) | Step 3 · app-injected extra fields slot (OJS fills it with AFFU-169; OMP renders it empty) |
| AFFU-167 | ojs omp | lib/pkp/templates/reviewer/review/step3.tpl `{fbvFormButtons submitText="reviewer.submission.submitReview" confirmSubmit="reviewer.confirmSubmit" modalStyle="primary" saveText="reviewer.submission.saveReviewForLater" cancelText="navigation.goBack"}` (`#reviewStep3Actions`) | Step 3 · "Submit Review" (confirm dialog key `reviewer.confirmSubmit`) + "Save for Later" + "Go Back" to step 2 · `submitDisabled=$reviewIsClosed` |
| AFFU-168 | ojs omp | lib/pkp/templates/reviewer/review/step3.tpl `#reviewStep3MessageBox` notification (keys `reviewer.submission.reviewFormResponse.form.responseRequired`/`.notFilledIn`) | Step 3 · inline required-response error box · hidden by default (`style="display:none"`) |
| AFFU-169 | ojs | ojs-main/templates/reviewer/review/step3.tpl + reviewerRecommendations.tpl select `#reviewerRecommendationId` from `$reviewerRecommendationOptions` label `reviewer.article.recommendation` defaultLabel `common.chooseOne` | Step 3 (OJS delta) · recommendation dropdown injected via `{capture assign="additionalFormFields"}` · `required=$required\|default:true`, `disabled=$readOnly` |

## Reviewer wizard — review-form elements (step 3, configured review form)

All guarded by `{if $reviewForm}` in step3.tpl; per element `readonly/disabled=$disabled`, `required=$req`.

| ID | apps | pointer | description |
|----|------|---------|-------------|
| AFFU-170 | ojs omp | lib/pkp/templates/reviewer/review/reviewFormResponse.tpl `{fbvElement type="text" size=SMALL}` name `reviewFormResponses[$elementId]` | Review form · small text field element (`REVIEW_FORM_ELEMENT_TYPE_SMALL_TEXT_FIELD`) |
| AFFU-171 | ojs omp | lib/pkp/templates/reviewer/review/reviewFormResponse.tpl `{fbvElement type="text"}` name `reviewFormResponses[$elementId]` | Review form · single-line text field element (`REVIEW_FORM_ELEMENT_TYPE_TEXT_FIELD`) |
| AFFU-172 | ojs omp | lib/pkp/templates/reviewer/review/reviewFormResponse.tpl `{fbvElement type="textarea" rows=4}` name `reviewFormResponses[$elementId]` | Review form · extended textarea element (`REVIEW_FORM_ELEMENT_TYPE_TEXTAREA`) |
| AFFU-173 | ojs omp | lib/pkp/templates/reviewer/review/reviewFormResponse.tpl fieldset `role="group"` + `{fbvElement type="checkbox"}` name `reviewFormResponses[$elementId][]` | Review form · checkbox group element (`REVIEW_FORM_ELEMENT_TYPE_CHECKBOXES`) |
| AFFU-174 | ojs omp | lib/pkp/templates/reviewer/review/reviewFormResponse.tpl fieldset `role="radiogroup"` + `{fbvElement type="radio"}` name `reviewFormResponses[$elementId]` | Review form · radio group element (`REVIEW_FORM_ELEMENT_TYPE_RADIO_BUTTONS`) |
| AFFU-175 | ojs omp | lib/pkp/templates/reviewer/review/reviewFormResponse.tpl `{fbvElement type="select" defaultLabel="" defaultValue=""}` name `reviewFormResponses[$elementId]` | Review form · dropdown element (`REVIEW_FORM_ELEMENT_TYPE_DROP_DOWN_BOX`) |

## Reviewer wizard — decline-review modal

| ID | apps | pointer | description |
|----|------|---------|-------------|
| AFFU-176 | ojs omp | lib/pkp/templates/reviewer/review/modal/regretMessage.tpl form `#declineReviewForm` action `{url op="saveDeclineReview"}` + hidden `#declineCompetingInterestOption`, `#declineReviewerCompetingInterests` | Decline modal · posts decline; JS copies CI radio/textarea values from the live step-1 form into the hidden inputs |
| AFFU-177 | ojs omp | lib/pkp/templates/reviewer/review/modal/regretMessage.tpl rich textarea `#declineReviewMessage` value `$declineMessageBody` | Decline modal · editable regret message body to the editor |
| AFFU-178 | ojs omp | lib/pkp/templates/reviewer/review/modal/regretMessage.tpl `{fbvFormButtons submitText="reviewer.submission.declineReview" hideCancel=true}` | Decline modal · "Decline Review Request" submit button |

## Reviewer wizard — step 4 (completion)

| ID | apps | pointer | description |
|----|------|---------|-------------|
| AFFU-179 | ojs omp | lib/pkp/templates/reviewer/review/reviewCompleted.tpl headings keys `reviewer.complete`/`reviewer.complete.whatNext` | Step 4 · static confirmation text, no form controls |
| AFFU-180 | ojs omp | lib/pkp/templates/reviewer/review/reviewCompleted.tpl `<discussion-manager-reviewer>` mount `#discussionManagerComplete-{$uuid}` | Step 4 · discussions panel remains available post-submission |

## Reviewer discussions panel (DiscussionManagerReviewer)

Shared Vue mounted only from the `ojs omp` wizard templates; reviewer is in `DiscussionManagerConfigurations.permissions[0].roles` (`ROLE_ID_REVIEWER`) and `getManagerConfig` passes `isCurrentUserAssignedAsReviewer(submission)`.

| ID | apps | pointer | description |
|----|------|---------|-------------|
| AFFU-181 | ojs omp | managers/DiscussionManager/DiscussionManagerReviewer.vue `<DiscussionManager>` after `v-if="submission"` | Discussions · wrapper fetches `submissions/{id}` then renders manager table |
| AFFU-182 | ojs omp | managers/DiscussionManager/useDiscussionManagerConfig.js getTopItems · DiscussionManagerActionButton label `common.add` action `TASKS_AND_DISCUSSIONS_ADD` | Discussions · "Add" button · opens DiscussionManagerFormModal (new discussion/task) · guard `enabledActions.includes(Actions.TASKS_AND_DISCUSSIONS_ADD)` |
| AFFU-183 | ojs omp | useDiscussionManagerConfig.js getColumns headers `common.name`, `submission.query.activityName`, `common.dueDate`, `submission.query.started`, `submission.query.closed`, sr-only `common.moreActions` | Discussions · table columns (name/activity/due date/started/closed/actions) |
| AFFU-184 | ojs omp | managers/DiscussionManager/DiscussionManagerCellName.vue PkpButton `discussion_name_{workItem.id}` @click `discussionManagerStore.discussionView({workItem})` | Discussions · row title link · opens DiscussionManagerFormDisplayModal (read view + messages) |
| AFFU-185 | ojs omp | managers/DiscussionManager/DiscussionManagerFormDisplayModal.vue PkpButton label `common.edit` @click `editForm` | Discussion view modal · Edit button · guard `v-if="discussionManagerStore.userHasWriteAccess({workItem})"`, `:disabled="isWorkItemClosed \|\| isLoadingWorkItem"` |
| AFFU-186 | ojs omp | managers/DiscussionManager/DiscussionMessages.vue PkpButton label `discussion.addNewMessage` @click `addMessage` + FieldRichTextarea `#newMessage` with FileAttacherAttachedFiles footer | Discussion view modal · add-message button revealing rich reply field with file attach · guard `v-if="hasAccessToAddMessage"` (else text `discussion.noAccessToAddMessage`) |
| AFFU-187 | ojs omp | managers/DiscussionManager/DiscussionManagerCellActions.vue DropdownActions label `common.moreActions` from `getItemActions` | Discussions · row ellipsis menu · guard `v-if="itemActions.length"` |
| AFFU-188 | ojs omp | useDiscussionManagerConfig.js getItemActions item `common.edit` (`TASKS_AND_DISCUSSIONS_EDIT`) | Row menu · Edit · guard `userHasWriteAccess` (`isCurrentUserJournalManager() \|\| isCurrentUserTheOwner \|\| isCurrentUserResponsibleParticipant`), `disabled: !!workItem?.dateClosed` |
| AFFU-189 | ojs omp | useDiscussionManagerConfig.js getItemActions item `discussion.addTaskDetails` (`TASKS_AND_DISCUSSIONS_ADD_TASK_DETAILS`) | Row menu · Add task details · guard `workItem.type === EDITORIAL_TASK_TYPE_DISCUSSION && workItem.status === EDITORIAL_TASK_STATUS_IN_PROGRESS && currentUserHasWriteAccess` |
| AFFU-190 | ojs omp | useDiscussionManagerConfig.js getItemActions item `common.history` (`TASKS_AND_DISCUSSIONS_HISTORY`) | Row menu · History · opens DiscussionManagerHistoryModal · guard permittedActions includes HISTORY `&& currentUserHasWriteAccess` |
| AFFU-191 | ojs omp | useDiscussionManagerConfig.js getItemActions item `common.delete` (`TASKS_AND_DISCUSSIONS_DELETE`, `isWarnable`) | Row menu · Delete discussion · guard permittedActions includes DELETE `&& currentUserHasWriteAccess` |

## Reviewer completed-review view + round history

`pages/reviewerSubmission/`; apps `ojs omp` because the only mount is reviewStepHeader.tpl.

| ID | apps | pointer | description |
|----|------|---------|-------------|
| AFFU-192 | ojs omp | pages/reviewerSubmission/ReviewerSubmissionPage.vue round-history info box | Reviewer submission page · per-round line keys `reviewer.submission.reviewRound.info`/`.info.submittedOn` · guard `v-if="store.reviewRoundHistories.length"` |
| AFFU-193 | ojs omp | pages/reviewerSubmission/ReviewerSubmissionPage.vue PkpButton `is-link` @click `store.openRoundHistoryModal(review)` key `reviewer.submission.reviewRound.info.read` | Reviewer submission page · "Read Review Round N" button · opens RoundHistoryModal via `openSideModal` (reviewerSubmissionPageStore.js) |
| AFFU-194 | ojs omp | pages/reviewerSubmission/RoundHistoryModal.vue SideModalBody title key `reviewer.submission.reviewRound.info.modal.title` | Round history modal · read-only display: decline date + decline email log (`v-if="store.isDeclined"`), not-completed notice (`v-else-if="store.isIncomplete"`), recommendation (`v-if="store.reviewRoundHistory.recommendation"`), author/editor + editor-only comments, metadata columns |
| AFFU-195 | ojs omp | pages/reviewerSubmission/RoundHistoryModal.vue ListingFilesListPanel title key `reviewer.submission.reviewRound.attachments` | Round history modal · reviewer attachment download list · guard `v-if="store.reviewRoundHistory.attachments.length"` |
| AFFU-196 | ojs omp | pages/reviewerSubmission/RoundHistoryModal.vue ListingFilesListPanel title key `reviewer.submission.reviewRound.files` | Round history modal · review-round files download list · guard `v-if="store.reviewRoundHistory.files.length"` |

## Reviewer dashboard entries (view `myReviewAssignments`)

`ojs omp ops`: all three app `pages/dashboard/index.php` dispatchers construct their DashboardHandler with `PKP\pages\dashboard\DashboardPage::MyReviewAssignments` (verified in ops-main). Only the reviewer-specific surface is enumerated; the editorial dashboard is AFFW.

| ID | apps | pointer | description |
|----|------|---------|-------------|
| AFFU-197 | ojs omp ops | pages/dashboard/composables/useDashboardConfig.js getLeftControls · DashboardActionButton label `common.filter` action `openFiltersModal` | Review assignments list · Filter button · opens filters modal |
| AFFU-198 | ojs omp ops | useDashboardConfig.js getRightControls · DashboardControlSearch (Search.vue label `editor.submission.search`) | Review assignments list · search box · hidden when `currentViewId.value === SEARCH_VIEW_ID` (dashboardPageStore.js) |
| AFFU-199 | ojs omp ops | pages/dashboard/DashboardPage.vue DashboardActiveFilters `@clear-filters` `@remove-filter` `@clear-search` | Review assignments list · active-filter chips with per-chip remove and clear-all/clear-search |
| AFFU-200 | ojs omp ops | pages/dashboard/DashboardPage.vue DashboardTable `@sort-column="store.applySort"` `@set-page="store.setCurrentPage"` | Review assignments list · column sort + pagination controls |
| AFFU-201 | ojs omp ops | useDashboardConfig.js getColumns (`dashboardPage === DashboardPageTypes.MY_REVIEW_ASSIGNMENTS`) → DashboardCellReviewAssignmentId (sortable), DashboardCellReviewAssignmentTitle, DashboardCellReviewAssignmentActivity, DashboardCellReviewAssignmentActions | Review assignments list · row columns: submission id, localized publication title, activity status, action |
| AFFU-202 | ojs omp ops | pages/dashboard/components/DashboardCellReviewAssignmentActivity.vue → useDashboardConfigEditorialActivity.js `getEditorialActivityForMyReviewAssignments` | Review assignments list · activity-cell status alert selected by `REVIEW_ASSIGNMENT_STATUS_*` (keys `dashboard.reviewAssignment.declined`, `submissions.incomplete`, `.acceptOrDeclineRequestDate`, `.deadlineForRespondingAcceptOrDecline`, `.completeReviewByDate`, `.deadlineForCompletingReviewHasPassed`, `.reviewSubmitted`) incl. response-due and review-due dates |
| AFFU-203 | ojs omp ops | pages/dashboard/components/DashboardCellReviewAssignmentActions.vue PkpButton `is-link` @click `dashboardPageStore.openReviewerForm({submissionId})` | Review assignments list · row action button · labels `dashboard.actions.respondToRequest` (AWAITING_RESPONSE/RESPONSE_OVERDUE/REQUEST_RESEND), `dashboard.actions.finishReview` (ACCEPTED/REVIEW_OVERDUE), `common.view` (default); `null` for DECLINED and for incomplete assignments in EDITING/PRODUCTION stages · `openReviewerForm` redirects to `reviewer/submission/{submissionId}` |
| AFFU-204 | ojs omp ops | pages/dashboard/dashboardPageStore.js + useDashboardBulkDelete.js | Review assignments list · bulk controls render empty on this view · guard `bulkDeleteIsAvailableForUser` returns true only for `EDITORIAL_DASHBOARD` (admin/manager) or `MY_SUBMISSIONS` |

## One-click access surfaces

Mechanical note: legacy `AccessKey` symbols no longer exist in lib/pkp (migrated by `classes/migration/upgrade/v3_5_0/I9197_MigrateAccessKeys.php`); the current one-click mechanism is the invitation system below.

| ID | apps | pointer | description |
|----|------|---------|-------------|
| AFFU-205 | ojs omp ops | lib/pkp/classes/mail/traits/OneClickReviewerAccess.php `setOneClickAccessUrl()` | One-click access · injects `ReviewerAccessInvite` URL into reviewer mailables · guard `if (!$context->getData('reviewerAccessKeysEnabled')) return;` |
| AFFU-206 | ojs omp ops | lib/pkp/pages/invitation/InvitationHandler.php ops `accept`/`decline`/`confirmDecline` | One-click access · public invitation-URL landing (accept/decline entry surface for the emailed key link) · Claimed by: user-invitations. |
| AFFU-207 | ojs omp | lib/pkp/classes/invitation/invitations/reviewerAccess/handlers/ReviewerAccessInviteRedirectController.php `acceptHandle()` | One-click access · on accept, redirects to page `reviewer` op `submission` with `submissionId` + `reviewId` (lands on the review wizard) · guard `$this->invitation->getStatus() !== InvitationStatus::PENDING` → 404 |
| AFFU-208 | ojs omp ops | ReviewerAccessInviteRedirectController.php `confirmDecline()` | One-click access · after decline confirmation redirects to login page · guard status `!== InvitationStatus::DECLINED` → 404 |
| AFFU-209 | ojs omp | lib/pkp/pages/reviewer/PKPReviewerHandler.php ops `submission`, `step`, `saveStep`, `showDeclineReview`, `saveDeclineReview` | One-click access / wizard endpoints the wizard controls post to · app dispatchers exist only at ojs-main/pages/reviewer/ and omp-main/pages/reviewer/ (no ops-main/pages/reviewer) |

## DOIs management page

Page shell lives per-app at `<app>/templates/management/dois.tpl` (not lib/pkp); the three copies are structurally identical apart from tab labels — recorded as shared rows with label deltas. Vue: `components/Container/DoiPageOJS/OMP/OPS.vue` + `components/ListPanel/doi/`.

| ID | apps | pointer | description |
|----|------|---------|-------------|
| AFFU-210 | ojs omp ops | templates/management/dois.tpl (per-app copy) `notification type="warning"` key `manager.dois.settings.prefixRequired` | DOIs · prefix-required warning with link to distribution settings · guard `{if $currentContext->getData('enableDois') && !$currentContext->getData('doiPrefix')}` |
| AFFU-211 | ojs omp ops | templates/management/dois.tpl `<tabs :track-history="true">` tab `id="submission-doi-management"` | DOIs · submissions tab (label `article.articles` ojs / `submission.list.monographs` omp / `common.publications` ops) · guard `{if $displaySubmissionsTab}` |
| AFFU-212 | ojs | ojs-main/templates/management/dois.tpl tab `id="issue-doi-management"` label `issue.issues` | DOIs · issues tab mounting the issue DoiListPanel · guard `{if $displayIssuesTab}` |
| AFFU-213 | ojs omp ops | components/ListPanel/doi/DoiListPanel.vue `<Search @search-phrase-changed="setSearchPhrase">` | DOIs · search field filtering the panel list |
| AFFU-214 | ojs omp ops | components/ListPanel/doi/DoiListPanel.vue `Dropdown` label `manager.dois.actions.bulkActions` class `doiListPanel__bulkActions` | DOIs · bulk-actions dropdown toggle |
| AFFU-215 | ojs omp ops | DoiListPanel.vue `@click="toggleSelectAll"` (`common.selectAll`/`common.selectNone`) | DOIs · select-all / select-none toggle in bulk dropdown |
| AFFU-216 | ojs omp ops | DoiListPanel.vue `@click="toggleExpandAll"` (`list.expandAll`/`list.collapseAll`) | DOIs · expand-all / collapse-all toggle in bulk dropdown |
| AFFU-217 | ojs omp ops | DoiListPanel.vue `@click="openBulkExport"` label `manager.dois.actions.export.label` | DOIs · bulk export selected (confirm dialog, downloads temporary files) · guard `v-if="isRegistrationPluginConfigured"` (= `registrationAgencyInfo['isConfigured']`) |
| AFFU-218 | ojs omp ops | DoiListPanel.vue `@click="openBulkMarkRegistered"` label `manager.dois.actions.markRegistered.label` | DOIs · bulk mark-registered selected via confirm dialog → `PUT …/markRegistered` |
| AFFU-219 | ojs omp ops | DoiListPanel.vue `@click="openBulkMarkUnregistered"` label `manager.dois.actions.markUnregistered.label` | DOIs · bulk mark-unregistered selected via confirm dialog |
| AFFU-220 | ojs omp ops | DoiListPanel.vue `@click="openBulkMarkStale"` label `manager.dois.actions.markStale.label` | DOIs · bulk mark-stale selected via confirm dialog |
| AFFU-221 | ojs omp ops | DoiListPanel.vue `@click="openBulkAssign"` label `manager.dois.actions.assign.label` | DOIs · bulk assign new DOIs to selected · guard `v-if="canAssignDois"` (= `this.doiPrefix && this.doiPrefix?.length > 0 && this.enabledDoiTypes.length > 0`) |
| AFFU-222 | ojs omp ops | DoiListPanel.vue `@click="openBulkDeposit"` label `manager.dois.actions.deposit.label` | DOIs · bulk deposit selected to registration agency · guard `v-if="isRegistrationPluginConfigured"` |
| AFFU-223 | ojs omp ops | DoiListPanel.vue `PkpButton @click="openBulkDepositAll"` label `manager.dois.actions.deposit.all` | DOIs · header primary button, deposits ALL outstanding DOIs (`PUT …/depositAll`) · guard `v-if="isRegistrationPluginConfigured"` |
| AFFU-224 | ojs omp ops | DoiListPanel.vue `button.doiListPanel__statusInfoButton @click="openStatusInfoModal"` | DOIs · sidebar info icon opening DoiStatusInfoModal (static status-legend table; modal itself has no controls) |
| AFFU-225 | ojs omp ops | lib/pkp/classes/components/listPanels/PKPDoiListPanel.php filter chips heading `common.status`: `param 'hasDois'` values `'0'` (`manager.dois.status.needsDoi`) / `'1'` (`manager.dois.filters.doiAssigned`) | DOIs · has-DOI status filter chips |
| AFFU-226 | ojs omp ops | PKPDoiListPanel.php filter chips heading `manager.setup.dois.registration`: `param 'unregistered' value 'true'`; `param 'doiStatus'` values `Doi::STATUS_SUBMITTED`, `STATUS_REGISTERED`, `STATUS_ERROR`, `STATUS_STALE` | DOIs · registration-status filter chips (unregistered chip also injects publishedStatuses filter — `addFilter` special case `param === 'unregistered'`) |
| AFFU-227 | omp ops | omp-main/ops-main classes/components/listPanels/DoiListPanel.php heading `manager.dois.publicationStatus`: `param 'status'` values `STATUS_PUBLISHED` / `STATUS_QUEUED, STATUS_SCHEDULED` | DOIs · published/unpublished filter chips (app-added) |
| AFFU-228 | ojs | ojs-main/classes/components/listPanels/DoiListPanel.php `filterType 'pkp-filter-autosuggest'`, `component 'field-select-issues'`, `param 'issueIds'` | DOIs · issue autosuggest filter on submissions tab · guard `if ($this->includeIssuesFilter)` |
| AFFU-229 | ojs omp ops | DoiListPanel.vue `Pagination @set-page="setPage"` | DOIs · footer pagination · guard `v-if="lastPage > 1"` |
| AFFU-230 | ojs omp ops | components/ListPanel/doi/DoiListItem.vue `input[type=checkbox]` in `.doiListItem__selector` `@click="toggleSelected"` | DOIs · per-item selection checkbox |
| AFFU-231 | ojs omp ops | DoiListItem.vue title `<a :href="item.urlPublished" target="_blank">` | DOIs · per-item title link to published view |
| AFFU-232 | ojs omp ops | DoiListItem.vue `Expander @toggle="toggleExpanded"` | DOIs · per-item expand/collapse toggle |
| AFFU-233 | ojs omp ops | DoiListItem.vue DOI `<input class="pkpFormField--text__input">` per doiObject row | DOIs · expanded panel per-DOI-type editable identifier field · `:readonly="!(isEditingDois && !isSaving)"`, `:disabled="isEditingDois && row.disabled"` |
| AFFU-234 | ojs omp ops | DoiListItem.vue row `PkpButton is-link @click="openViewErrorModal(row.errorMessage)"` label `common.viewError` | DOIs · per-DOI-row view-error dialog · guard `v-if="hasErrors(row.depositStatus)"` |
| AFFU-235 | ojs omp ops | DoiListItem.vue `button @click="openVersionModal"` label `doi.manager.versions.view` | DOIs · open per-item versions side modal · guard `v-if="item.versions.length > 1 && !isEditingDois && !isSaving && versionDois"` |
| AFFU-236 | ojs omp ops | DoiListItem.vue `PkpButton @click="isEditingDois ? saveDois() : editDois()"` (`common.edit`/`common.save`) | DOIs · per-item edit/save DOI fields (save POSTs add/edit/delete per changed field) · `:is-disabled="isDeposited(itemDepositStatus) \|\| isSaving"` |
| AFFU-237 | ojs omp ops | DoiListItem.vue `.doiListItem__depositorDetails` panel | DOIs · per-item registration-agency panel · guard `v-if="isRegistrationPluginConfigured"` |
| AFFU-238 | ojs omp ops | DoiListItem.vue `ref="recordedMessageModalButton"` `@click="viewRecord"` label `manager.dois.registration.viewRecord` | DOIs · view registration-record dialog · guard `v-if="isDeposited(itemDepositStatus) && hasRegisteredMessage"`, `:is-disabled="isEditingDois"` |
| AFFU-239 | ojs omp ops | DoiListItem.vue `@click="handleDepositorActions"` label `manager.dois.registration.depositDois` | DOIs · per-item deposit button (emits `deposit-triggered` → panel `openBulkDeposit([id])`) · guard `v-else-if="!isDeposited(itemDepositStatus) && item.isPublished"` |
| AFFU-240 | ojs omp ops | DoiListItem.vue `ref="errorMessageModalButton"` label `manager.dois.registration.viewError` | DOIs · per-item view deposit-error dialog · guard `v-if="hasErrors(itemDepositStatus) && hasErrorMessage"` |
| AFFU-241 | ojs omp ops | components/ListPanel/doi/DoiItemVersionModal.vue version header `<a :href="version.urlPublished" target="_blank">` | DOIs · versions modal per-version link |
| AFFU-242 | ojs omp ops | DoiItemVersionModal.vue per-row DOI `<input>` + view-error `PkpButton is-link` (guard `v-if="hasErrors(row.depositStatus)"`) + Edit/Save `PkpButton` (`:is-disabled="isDeposited(itemDepositStatus) \|\| isSaving"`) | DOIs · versions modal repeats the field-edit/save + view-error controls per version |
| AFFU-243 | ojs omp ops | components/ListPanel/doi/DoiFailedActionDialogBody.vue / DoiItemViewErrorDialogBody.vue / DoiItemViewRegisteredMessageDialogBody.vue dialog action `common.ok` | DOIs · message dialogs, OK-close only |
| AFFU-244 | ojs | components/ListPanel/doi/DoiListPanelOJS.vue `addDoiObjects` | DOIs (OJS delta) · expanded-item row types: `publication` (`article.article`), `representation` (galley label), `peerReview`, `authorResponse` on submissions tab; `issue` (`issue.issue`) when `itemType === 'issue'` · each guarded by `this.enabledDoiTypes.includes(...)` |
| AFFU-245 | omp | components/ListPanel/doi/DoiListPanelOMP.vue `addDoiObjects` | DOIs (OMP delta) · row types: `publication` (`submission.monograph`), `chapter` (chapter title), `representation` (`manager.dois.formatIdentifier.file` per publication format), `file` (format / file name) · guarded by `enabledDoiTypes` |
| AFFU-246 | ops | components/ListPanel/doi/DoiListPanelOPS.vue `addDoiObjects` | DOIs (OPS delta) · row types: `publication` (`submission.publication`), `representation` (galley label); no itemType branch (preprints only) |

## Statistics — editorial activity

| ID | apps | pointer | description |
|----|------|---------|-------------|
| AFFU-247 | ojs omp | lib/pkp/templates/stats/editorial.tpl `<doughnut-chart :chart-data="chartData">` block | Editorial stats · active-submissions-by-stage doughnut chart + stage counts · guard `v-if="activeByStage"` (display, not interactive) |
| AFFU-248 | ops | ops-main/pages/stats/StatsHandler.php `removeEditorialStatsChartView()` | Editorial stats (OPS delta) · `if ($template == 'stats/editorial.tpl') { $templateMgr->setState(['activeByStage' => null]); }` → the `v-if="activeByStage"` chart block never renders in OPS |
| AFFU-249 | ojs omp ops | lib/pkp/templates/stats/editorial.tpl `<date-range unique-id="editorial-stats-date-range" @set-range="setDateRange" @updated:current-range="setCurrentDateRange">` | Editorial stats · date-range picker (presets + custom range); selection also relabels the table's date-range column |
| AFFU-250 | ojs omp ops | lib/pkp/templates/stats/editorial.tpl `pkp-button @click="toggleSidebar"` icon `Filter` | Editorial stats · filters sidebar toggle · guard `v-if="filters.length"` |
| AFFU-251 | ojs ops | app pages/stats/StatsHandler.php `addSectionFilters` chips `param 'sectionIds'` heading `section.sections` | Editorial stats · per-section filter chips (`pkp-filter @add-filter/@remove-filter`) · OPS guard `if (count($sectionFilters) < 2) return;` |
| AFFU-252 | omp | omp-main/pages/stats/StatsHandler.php `addSectionFilters` chips `param 'seriesIds'` heading `series.series` | Editorial stats · per-series filter chips · guard `if (empty($seriesFilters)) return;` |
| AFFU-253 | ojs omp ops | lib/pkp/templates/stats/editorial.tpl `pkp-table.pkpTable--editorialStats` + `tooltip` per row | Editorial stats · trends table (name / date-range / total) with per-stat tooltip `v-if="row.description"` · no sorting wired, no download control in editorial.tpl |

## Statistics — publications

| ID | apps | pointer | description |
|----|------|---------|-------------|
| AFFU-254 | ojs omp ops | lib/pkp/templates/stats/publications.tpl `<date-range unique-id="publication-stats-date-range" @set-range="setDateRange">` | Publications stats · date-range picker |
| AFFU-255 | ojs omp ops | lib/pkp/templates/stats/publications.tpl `pkp-button @click="toggleSidebar"` | Publications stats · filters sidebar toggle · guard `v-if="filters.length"` |
| AFFU-256 | ojs ops | app pages/stats/StatsHandler.php `addSectionFilters` chips `param 'sectionIds'` | Publications stats · section filter chips (same source as editorial) |
| AFFU-257 | omp | omp-main/pages/stats/StatsHandler.php chips `param 'seriesIds'` | Publications stats · series filter chips |
| AFFU-258 | ojs | ojs-main/pages/stats/StatsHandler.php `addSectionFilters` chips `param 'issueIds'` heading `issue.issues` | Publications stats · per-published-issue filter chips · guard `if ($template == 'stats/publications.tpl')` |
| AFFU-259 | ojs omp ops | lib/pkp/templates/stats/publications.tpl `.pkpStats__graphSelector--timelineType` buttons `setTimelineType('abstract')`/`setTimelineType('files')` | Publications stats · timeline metric toggle (abstract vs file views), `:aria-pressed` state · guard on whole graph `v-if="chartData"` |
| AFFU-260 | ojs omp ops | lib/pkp/templates/stats/publications.tpl `.pkpStats__graphSelector--timelineInterval` buttons `setTimelineInterval('day')`/`('month')` | Publications stats · chart granularity toggle · `:disabled="!isDailyIntervalEnabled"` / `:disabled="!isMonthlyIntervalEnabled"` |
| AFFU-261 | ojs omp ops | lib/pkp/templates/stats/publications.tpl `ref="downloadReportModalButton"` `@click="openDownloadReportModal"` label `common.downloadReport` | Publications stats · open download-report side modal |
| AFFU-262 | ojs omp ops | lib/pkp/templates/stats/publications.tpl `<search class="pkpStats__titleSearch" @search-phrase-changed="setSearchPhrase">` (inside title table-column) | Publications stats · submission-title search embedded in table header |
| AFFU-263 | ojs omp ops | lib/pkp/templates/stats/publications.tpl `pkp-table @sort="setOrderBy"` with `:allows-sorting="column.name === 'total'"` | Publications stats · explicit column sort, total column only |
| AFFU-264 | ojs omp ops | lib/pkp/templates/stats/publications.tpl `pagination id="publicationDetailTablePagination" @set-page="setPage"` | Publications stats · table pagination · guard `v-if="lastPage > 1"` |
| AFFU-265 | ojs omp ops | pages/statsPublications/PublicationsDownloadReportModal.vue `emit('downloadReport', null)` label `stats.publications.downloadReport.downloadSubmissions` | Publications stats · modal: download submissions CSV |
| AFFU-266 | ojs omp ops | PublicationsDownloadReportModal.vue `emit('downloadReport', 'files')` | Publications stats · modal: download files report |
| AFFU-267 | ojs omp ops | PublicationsDownloadReportModal.vue `emit('downloadReport', 'timeline')` | Publications stats · modal: download timeline report (respects current type/interval) |
| AFFU-268 | ojs omp ops | PublicationsDownloadReportModal.vue `emit('downloadReport', geoReportType)` | Publications stats · modal: download geographic report · guard `v-if="geoReportType"` (prop from `PKPStatsHandler` state `'geoReportType' => $geoAPIEndPoint`) |

## Statistics — users

| ID | apps | pointer | description |
|----|------|---------|-------------|
| AFFU-269 | ojs omp ops | lib/pkp/templates/stats/users.tpl `pkp-button ref="exportButton" @click="openExportModal"` label `common.export` | Users stats · open export side modal (only control on page; role-count table is server-rendered `{foreach from=$userStats}`) |
| AFFU-270 | ojs omp ops | pages/statsUsers/UserExportModal.vue `<PkpForm v-bind="usersReportForm" @success="…emit('loadExport'…)">` title `manager.export.usersToCsv.label` | Users stats · users-to-CSV report form; on success `StatsUsersPage.vue::loadExport(url)` does `window.location = url` (fields defined in the server-side users-report PHP form) |

## Statistics — context

| ID | apps | pointer | description |
|----|------|---------|-------------|
| AFFU-271 | ojs omp ops | lib/pkp/templates/stats/context.tpl `<date-range unique-id="context-stats-date-range" @set-range="setDateRange">` | Context stats · date-range picker |
| AFFU-272 | ojs omp ops | lib/pkp/templates/stats/context.tpl `.pkpStats__graphSelector--timelineInterval` `setTimelineInterval('day')`/`('month')` | Context stats · chart granularity toggle · guard `v-if="chartData"` on graph; `:disabled="!isDailyIntervalEnabled"` / `"!isMonthlyIntervalEnabled"` |
| AFFU-273 | ojs omp ops | lib/pkp/templates/stats/context.tpl `ref="downloadReportModalButton"` `@click="openDownloadReportModal"` label `common.downloadReport` | Context stats · open download-report modal (heading tooltip only; no search, no sort, no filters sidebar in this tpl) |
| AFFU-274 | ojs omp ops | pages/statsContext/ContextDownloadReportModal.vue `emit('downloadReport', null)` / `emit('downloadReport', 'timeline')` | Context stats · modal: download context report; download timeline report |

## Statistics — issues (OJS only)

| ID | apps | pointer | description |
|----|------|---------|-------------|
| AFFU-275 | ojs | ojs-main/templates/stats/issues.tpl `<date-range unique-id="issue-stats-date-range" @set-range="setDateRange">` | Issues stats · date-range picker |
| AFFU-276 | ojs | ojs-main/templates/stats/issues.tpl `--timelineType` buttons `setTimelineType('toc')` (`stats.views`) / `setTimelineType('files')` (`stats.downloads`) | Issues stats · timeline metric toggle (TOC views vs issue-galley downloads) · guard `v-if="chartData"` |
| AFFU-277 | ojs | ojs-main/templates/stats/issues.tpl `--timelineInterval` buttons `setTimelineInterval('day')`/`('month')` | Issues stats · chart granularity toggle · `:disabled="!isDailyIntervalEnabled"` / `"!isMonthlyIntervalEnabled"` |
| AFFU-278 | ojs | ojs-main/templates/stats/issues.tpl `pkp-button @click="openDownloadReportModal"` label `common.downloadReport` | Issues stats · open download-report modal |
| AFFU-279 | ojs | ojs-main/templates/stats/issues.tpl `<search class="pkpStats__titleSearch" @search-phrase-changed="setSearchPhrase">` label key `stats.issues.searchIssueDescription` | Issues stats · issue search in table header |
| AFFU-280 | ojs | ojs-main/templates/stats/issues.tpl `pkp-table @sort="setOrderBy"` `:allows-sorting="column.name === 'total'"` | Issues stats · total-column sort |
| AFFU-281 | ojs | ojs-main/templates/stats/issues.tpl `pagination id="issueDetailTablePagination" @set-page="setPage"` | Issues stats · pagination · guard `v-if="lastPage > 1"` |
| AFFU-282 | ojs | pages/statsIssues/IssueDownloadReportModal.vue `emit('downloadReport', null)` / `emit('downloadReport', 'timeline')` | Issues stats · modal: download issues report; download timeline report (search phrase echoed `v-if="searchPhrase"`) |

## Reports UI

| ID | apps | pointer | description |
|----|------|---------|-------------|
| AFFU-283 | ojs omp ops | lib/pkp/templates/stats/reports.tpl `{foreach from=$reportPlugins}` `<a href="{url op="reports" path="report" pluginName=…}">` | Reports · one link per registered report plugin, immediate report generation/download · page has no other controls (server-rendered list; per-plugin UIs not swept here) |

## COUNTER R5

| ID | apps | pointer | description |
|----|------|---------|-------------|
| AFFU-284 | ojs omp ops | lib/pkp/templates/stats/counterReports.tpl `<counter-reports-page v-bind="pageInitConfig" />` | COUNTER · page shell only, all controls in Vue |
| AFFU-285 | ojs omp ops | pages/counter/CounterReportsPage.vue `Notification type="warning"` key `manager.statistics.counterR5Reports.usageNotPossible` | COUNTER · warning banner · guard `v-if="usageNotPossible"` |
| AFFU-286 | ojs omp ops | pages/counter/components/CounterReportsListPanel.vue `#item-actions` `PkpButton @click="openEditModal(item.Report_ID)"` label `common.edit` | COUNTER · per-report Edit button opening CounterReportsEditModal · report list fetched from `stats/sushi/reports`; report set per app (ojs: PR, PR_P1, TR, TR_J3, IR, IR_A1; omp: PR, PR_P1, TR, TR_B3; ops: PR, PR_P1, IR — from each app's `classes/components/forms/counter/CounterReportForm.php::setReportFields`) |
| AFFU-287 | ojs omp ops | pages/counter/components/CounterReportsEditModal.vue `<PkpForm v-bind="form">`, submit button `common.download` (`PKPCounterReportForm.php::addPage`) | COUNTER · report-parameters form; per-report fields from `APP\sushi\*::getReportSettingsFormFields()`; serialized params per `getReportParams`: `customer_id`, `begin_date`, `end_date`, `yop`, `item_id`, `metric_type` (pipe-joined), `attributes_to_show` (pipe-joined), `include_parent_details` (→ `'True'`), `granularity` (→ `'Totals'`) |
| AFFU-288 | ojs omp ops | CounterReportsEditModal.vue `customSubmit` blob download `link.download = 'counterReport.tsv'` | COUNTER · submit triggers TSV download of the report; COUNTER error `Code/Message/Data` surfaced as warning notify |
