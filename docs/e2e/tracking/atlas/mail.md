# Atlas — mail (mailables + registry email templates)

- **Modality**: mail
- **Sweep date**: 2026-07-26
- **Trees swept**:
  - Shared: `lib/pkp/classes/mail/mailables/` (59 classes) — swept once; `diff -rq` confirmed `lib/pkp/classes/mail/` is byte-identical across the ojs-main, omp-main and ops-main working trees. Working-tree submodule SHA in all three checkouts: `ad4606f93ef153d018e3c4cce751698258c9bbcd` (note: task brief said `9db481cf4d`, which matches no recorded gitlink — ojs-main HEAD records `9a91dc2ee9`, omp/ops record `ad4606f93e`; ojs-main's `lib/pkp` is locally modified. Recorded mechanically, no judgment.)
  - App: `/Users/jarda/git/pkp/pkp-main/ojs-main/classes/mail/mailables/` (11), `/Users/jarda/git/pkp/pkp-main/omp-main/classes/mail/mailables/` (1), `/Users/jarda/git/pkp/pkp-main/ops-main/classes/mail/mailables/` (3). (The task brief's ~19/~5/~7 counts for `classes/mail/` include non-mailable support files — `Repository.php`, `traits/`, `variables/` — which are not atomized: one atom per mailable.)
  - Registries: `registry/emailTemplates.xml` — ojs 75 keys, omp 66, ops 29 (brief said 79/70/33; actual `grep -c '<email '` counts recorded here). Plugin-level registries found by `find . -name emailTemplates.xml`: `ojs/plugins/paymethod/manual/` (1 key), `omp/plugins/paymethod/manual/` (1 key), `ops/plugins/generic/orcidProfile/` (2 keys, both declared by shared Orcid mailables). The manual-payment plugin also ships its own mailable (`ManualPaymentNotify`), atomized below.
- **Globs/greps used**: `ls */classes/mail/mailables/*.php`; `grep -H "emailTemplateKey = '...'" ` per mailable (every mailable in all trees declares its key via the static `$emailTemplateKey` property — none uses a dynamic `getEmailTemplateKey()` override); `grep -o 'key="[^"]*"' registry/emailTemplates.xml` per app; orphans = `comm -23` of registry keys vs declared mailable keys (shared + same-app); mailable→app registration cross-checked mechanically against `PKP\mail\Repository::map()` and each `APP\mail\Repository::map()`.
- **Apps column convention**: shared mailable classes exist in all three trees via the submodule; the apps listed are the apps whose `Repository::map()` registers the class (OPS overrides `map()` with an explicit subset — shared mailables it omits are listed `ojs omp`). Three shared mailables appear in **no** `map()` (dispatched directly, not template-manageable): noted inline, listed `ojs omp ops` since the code path exists in all three.
- OPS-only mechanical note: `APP\mail\Repository::isMailableEnabled()` (OPS) gates `PostedAcknowledgement` on the context setting `postedAcknowledgement`.

## Mailables — shared (`lib/pkp/classes/mail/mailables/`)

| ID | Apps | Pointer | Description |
|---|---|---|---|
| MAIL-001 | ojs omp ops | `PKP\mail\mailables\AnnouncementNotify` | Notify users about a new announcement. Key: `ANNOUNCEMENT` |
| MAIL-002 | ojs omp | `PKP\mail\mailables\AuthorPublicationPublished` | Automatic email to authors when a publication is published. Key: `AUTHOR_PUBLICATION_PUBLISHED` |
| MAIL-003 | ojs omp ops | `PKP\mail\mailables\ChangeProfileEmailInvitationNotify` | Sent when a user requests a profile email change (in no `Repository::map()` — dispatched directly). Key: `CHANGE_EMAIL` |
| MAIL-004 | ojs omp ops | `PKP\mail\mailables\DecisionAcceptNotifyAuthor` | Author notification for the Accept decision. Key: `EDITOR_DECISION_ACCEPT` |
| MAIL-005 | ojs omp | `PKP\mail\mailables\DecisionBackFromCopyeditingNotifyAuthor` | Author notification for the back-from-copyediting decision. Key: `EDITOR_DECISION_BACK_FROM_COPYEDITING` |
| MAIL-006 | ojs omp | `PKP\mail\mailables\DecisionBackFromProductionNotifyAuthor` | Author notification for the back-from-production decision. Key: `EDITOR_DECISION_BACK_FROM_PRODUCTION` |
| MAIL-007 | ojs omp | `PKP\mail\mailables\DecisionCancelReviewRoundNotifyAuthor` | Author notification for the cancel-review-round decision. Key: `EDITOR_DECISION_CANCEL_REVIEW_ROUND` |
| MAIL-008 | ojs omp | `PKP\mail\mailables\DecisionDeclineNotifyAuthor` | Author notification for the Decline decision (after review). Key: `EDITOR_DECISION_DECLINE` |
| MAIL-009 | ojs omp ops | `PKP\mail\mailables\DecisionInitialDeclineNotifyAuthor` | Author notification for the initial (desk) Decline decision. Key: `EDITOR_DECISION_INITIAL_DECLINE` |
| MAIL-010 | ojs omp | `PKP\mail\mailables\DecisionNewReviewRoundNotifyAuthor` | Author notification for the new-review-round decision. Key: `EDITOR_DECISION_NEW_ROUND` |
| MAIL-011 | ojs omp ops | `PKP\mail\mailables\DecisionNotifyOtherAuthors` | Notifies a submission's other (non-recipient) authors of an editorial decision. Key: `EDITOR_DECISION_NOTIFY_OTHER_AUTHORS` |
| MAIL-012 | ojs omp | `PKP\mail\mailables\DecisionNotifyReviewer` | Notifies reviewers who completed a review of the round's decision. Key: `EDITOR_DECISION_NOTIFY_REVIEWERS` |
| MAIL-013 | ojs omp | `PKP\mail\mailables\DecisionRequestRevisionsNotifyAuthor` | Author notification for the request-revisions decision. Key: `EDITOR_DECISION_REVISIONS` |
| MAIL-014 | ojs omp | `PKP\mail\mailables\DecisionResubmitNotifyAuthor` | Author notification for the resubmit-for-review decision. Key: `EDITOR_DECISION_RESUBMIT` |
| MAIL-015 | ojs omp | `PKP\mail\mailables\DecisionRevertDeclineNotifyAuthor` | Author notification for the revert-decline decision. Key: `EDITOR_DECISION_REVERT_DECLINE` |
| MAIL-016 | ojs omp ops | `PKP\mail\mailables\DecisionRevertInitialDeclineNotifyAuthor` | Author notification for the revert-initial-decline decision. Key: `EDITOR_DECISION_REVERT_INITIAL_DECLINE` |
| MAIL-017 | ojs omp | `PKP\mail\mailables\DecisionSendExternalReviewNotifyAuthor` | Author notification for the send-to-external-review decision. Key: `EDITOR_DECISION_SEND_TO_EXTERNAL` |
| MAIL-018 | ojs omp | `PKP\mail\mailables\DecisionSendToProductionNotifyAuthor` | Author notification for the send-to-production decision. Key: `EDITOR_DECISION_SEND_TO_PRODUCTION` |
| MAIL-019 | ojs omp | `PKP\mail\mailables\DecisionSkipExternalReviewNotifyAuthor` | Author notification for the skip-external-review decision. Key: `EDITOR_DECISION_SKIP_REVIEW` |
| MAIL-020 | ojs omp | `PKP\mail\mailables\DiscussionCopyediting` | New discussion/reply notification, copyediting stage. Key: `DISCUSSION_NOTIFICATION_COPYEDITING` |
| MAIL-021 | ojs omp ops | `PKP\mail\mailables\DiscussionProduction` | New discussion/reply notification, production stage. Key: `DISCUSSION_NOTIFICATION_PRODUCTION` |
| MAIL-022 | ojs omp | `PKP\mail\mailables\DiscussionReview` | New discussion/reply notification, (external) review stage. Key: `DISCUSSION_NOTIFICATION_REVIEW` |
| MAIL-023 | ojs omp | `PKP\mail\mailables\DiscussionSubmission` | New discussion/reply notification, submission stage. Key: `DISCUSSION_NOTIFICATION_SUBMISSION` |
| MAIL-024 | ojs omp ops | `PKP\mail\mailables\EditorAssigned` | Email to editors assigned to a submission. Key: `EDITOR_ASSIGN` |
| MAIL-025 | ojs omp | `PKP\mail\mailables\EditorialReminder` | Automatic reminder to an editor of outstanding editorial tasks. Key: `EDITORIAL_REMINDER` |
| MAIL-026 | ojs omp | `PKP\mail\mailables\EditReviewNotify` | Automatic email to a reviewer when their review assignment details change. Key: `REVIEW_EDIT` Claimed by: reviewer-assignment-and-management. |
| MAIL-027 | ojs omp | `PKP\mail\mailables\OrcidCollectAuthorId` | Automatic email asking authors to add ORCIDs to a submission (OPS: template installed by `plugins/generic/orcidProfile` registry, class not in OPS `map()`). Key: `ORCID_COLLECT_AUTHOR_ID` Claimed by: orcid-integration. |
| MAIL-028 | ojs omp | `PKP\mail\mailables\OrcidRequestAuthorAuthorization` | Automatic email asking authors for ORCID authorization/metadata push permission (OPS: template installed by `plugins/generic/orcidProfile` registry, class not in OPS `map()`). Key: `ORCID_REQUEST_AUTHOR_AUTHORIZATION` Claimed by: orcid-integration. |
| MAIL-029 | ojs omp ops | `PKP\mail\mailables\OrcidRequestUpdateScope` | Automatic email requesting users update their ORCID OAuth scope for member API deposits (in no `Repository::map()` — dispatched directly). Key: `ORCID_REQUEST_UPDATE_SCOPE` Claimed by: orcid-integration. |
| MAIL-030 | ojs omp ops | `PKP\mail\mailables\PasswordResetRequested` | Automatic email when a user requests a password reset. Key: `PASSWORD_RESET_CONFIRM` Claimed by: login-and-sessions. |
| MAIL-031 | ojs omp ops | `PKP\mail\mailables\PublicationVersionNotify` | Automatic email to assigned editors when a new publication version is created. Key: `VERSION_CREATED` |
| MAIL-032 | ojs omp | `PKP\mail\mailables\RecommendationNotifyEditors` | Message to deciding editors when a recommend-only decision is recorded. Key: `EDITOR_RECOMMENDATION` |
| MAIL-033 | ojs omp | `PKP\mail\mailables\RequestReviewRoundAuthorResponse` | Email to author(s) requesting a response to reviewers' comments. Key: `REQUEST_REVIEW_ROUND_AUTHOR_RESPONSE` |
| MAIL-034 | ojs omp | `PKP\mail\mailables\ReviewAcknowledgement` | Editor confirms receipt of a completed review and thanks the reviewer. Key: `REVIEW_ACK` Claimed by: reviewer-assignment-and-management. |
| MAIL-035 | ojs omp | `PKP\mail\mailables\ReviewCompleteNotifyEditors` | Automatic email to assigned editors when a reviewer completes a review. Key: `REVIEW_COMPLETE` |
| MAIL-036 | ojs omp | `PKP\mail\mailables\ReviewConfirm` | Automatic email after a reviewer accepts a review request. Key: `REVIEW_CONFIRM` |
| MAIL-037 | ojs omp | `PKP\mail\mailables\ReviewDecline` | Email sent when a reviewer declines a review request. Key: `REVIEW_DECLINE` |
| MAIL-038 | ojs omp | `PKP\mail\mailables\ReviewerRegister` | Automatic email to a newly registered reviewer (Create Reviewer form). Key: `REVIEWER_REGISTER` Claimed by: reviewer-assignment-and-management. |
| MAIL-039 | ojs omp | `PKP\mail\mailables\ReviewerReinstate` | Email to a reviewer whose assignment is reinstated. Key: `REVIEW_REINSTATE` Claimed by: reviewer-assignment-and-management. |
| MAIL-040 | ojs omp | `PKP\mail\mailables\ReviewerResendRequest` | Email asking a reviewer who declined to reconsider. Key: `REVIEW_RESEND_REQUEST` Claimed by: reviewer-assignment-and-management. |
| MAIL-041 | ojs omp | `PKP\mail\mailables\ReviewerUnassign` | Email sent when a reviewer is unassigned. Key: `REVIEW_CANCEL` Claimed by: reviewer-assignment-and-management. |
| MAIL-042 | ojs omp | `PKP\mail\mailables\ReviewRemind` | Editor-sent reminder to a reviewer about the review request. Key: `REVIEW_REMIND` Claimed by: reviewer-assignment-and-management. |
| MAIL-043 | ojs omp | `PKP\mail\mailables\ReviewRemindAuto` | Automatic reminder to a reviewer after the review due date. Key: `REVIEW_REMIND_AUTO` Claimed by: reviewer-assignment-and-management. |
| MAIL-044 | ojs omp | `PKP\mail\mailables\ReviewRequest` | Review request to a reviewer (accept/decline), first round. Key: `REVIEW_REQUEST` Claimed by: reviewer-assignment-and-management. |
| MAIL-045 | ojs omp | `PKP\mail\mailables\ReviewRequestSubsequent` | Review request to a reviewer on subsequent review rounds. Key: `REVIEW_REQUEST_SUBSEQUENT` Claimed by: reviewer-assignment-and-management. |
| MAIL-046 | ojs omp | `PKP\mail\mailables\ReviewResponseRemindAuto` | Automatic reminder to a reviewer after the response deadline. Key: `REVIEW_RESPONSE_OVERDUE_AUTO` Claimed by: reviewer-assignment-and-management. |
| MAIL-047 | ojs omp | `PKP\mail\mailables\RevisedVersionNotify` | Automatic email to the assigned editor when an author uploads a revised version. Key: `REVISED_VERSION_NOTIFY` Claimed by: review-stage-and-rounds. |
| MAIL-048 | ojs omp ops | `PKP\mail\mailables\StatisticsReportNotify` | Scheduled editorial/statistics report email (class docblock @brief is a copy-paste of AnnouncementNotify's). Key: `STATISTICS_REPORT_NOTIFICATION` |
| MAIL-049 | ojs omp ops | `PKP\mail\mailables\SubmissionAcknowledgement` | Acknowledgement to the submitting author on submission. Key: `SUBMISSION_ACK` |
| MAIL-050 | ojs omp ops | `PKP\mail\mailables\SubmissionAcknowledgementNotAuthor` | Acknowledgement to contributors named on a new submission. Key: `SUBMISSION_ACK_NOT_USER` |
| MAIL-051 | ojs omp ops | `PKP\mail\mailables\SubmissionAcknowledgementOtherAuthors` | Acknowledgement to other authors named as contributors on a new submission (in no `Repository::map()` — dispatched directly; shares its template key with MAIL-050). Key: `SUBMISSION_ACK_NOT_USER` |
| MAIL-052 | ojs omp | `PKP\mail\mailables\SubmissionNeedsEditor` | Email to managers when a new submission has no assigned editor. Key: `SUBMISSION_NEEDS_EDITOR` |
| MAIL-053 | ojs omp | `PKP\mail\mailables\SubmissionSavedForLater` | Email to a submitting author when they save an incomplete submission for later. Key: `SUBMISSION_SAVED_FOR_LATER` |
| MAIL-054 | ojs omp ops | `PKP\mail\mailables\UserCreated` | Email to a new user created from the user management screen. Key: `USER_REGISTER` |
| MAIL-055 | ojs omp | `PKP\mail\mailables\UserRoleAssignmentInvitationNotify` | Invitation email for a user to take on specific roles. Key: `USER_ROLE_ASSIGNMENT_INVITATION`. Badge is `ojs omp` deliberately — OPS's `APP\mail\Repository::map()` omits this mailable, so it has no row on the OPS email-templates screen, though the template key is still seeded in OPS `registry/emailTemplates.xml` and sending works. Do not "fix" the badge. Claimed by: user-invitations. |
| MAIL-056 | ojs omp ops | `PKP\mail\mailables\UserRoleEndNotify` | Email when a user is removed from a role. Key: `USER_ROLE_END`. Badge corrected to all three apps (2026-07-29): the notice is sent on OPS too — watched live in the Users management claim check; only its row on the OPS email-templates screen is missing |
| MAIL-057 | ojs | `PKP\mail\mailables\UserRoleMastheadUpdateNotify` | Email when a user's masthead visibility for a role changes. Badge narrowed to OJS (2026-07-29): the template is not installed on OMP or OPS, where the change saves and the manager then sees an error and no mail leaves — see the Users management spec's register Key: `USER_ROLE_MASTHEAD_UPDATE` |
| MAIL-058 | ojs omp ops | `PKP\mail\mailables\ValidateEmailContext` | Registration email-validation message (context level). Key: `USER_VALIDATE_CONTEXT` |
| MAIL-059 | ojs omp ops | `PKP\mail\mailables\ValidateEmailSite` | Registration email-validation message (site level). Key: `USER_VALIDATE_SITE` |

## Mailables — OJS (`classes/mail/mailables/`)

| ID | Apps | Pointer | Description |
|---|---|---|---|
| MAIL-060 | ojs | `APP\mail\mailables\IssuePublishedNotify` | Automatic email to registered users when a new issue is published. Key: `ISSUE_PUBLISH_NOTIFY` |
| MAIL-061 | ojs | `APP\mail\mailables\OpenAccessNotify` | Notify users when an issue becomes open access. Key: `OPEN_ACCESS_NOTIFY` |
| MAIL-062 | ojs | `APP\mail\mailables\PaymentRequest` | Automatic email notifying submission authors of a required payment. Key: `PAYMENT_REQUEST_NOTIFICATION` |
| MAIL-063 | ojs | `APP\mail\mailables\SubscriptionExpired` | Automatic notice to a subscriber that their subscription expired. Key: `SUBSCRIPTION_AFTER_EXPIRY` |
| MAIL-064 | ojs | `APP\mail\mailables\SubscriptionExpiredLast` | Second (final) automatic notice after subscription expiry. Key: `SUBSCRIPTION_AFTER_EXPIRY_LAST` |
| MAIL-065 | ojs | `APP\mail\mailables\SubscriptionExpiresSoon` | Automatic notice to a subscriber that their subscription expires soon. Key: `SUBSCRIPTION_BEFORE_EXPIRY` |
| MAIL-066 | ojs | `APP\mail\mailables\SubscriptionNotify` | Notify a user about their new subscription. Key: `SUBSCRIPTION_NOTIFY` |
| MAIL-067 | ojs | `APP\mail\mailables\SubscriptionPurchaseIndividual` | Notify the subscription manager of a purchased individual subscription. Key: `SUBSCRIPTION_PURCHASE_INDL` |
| MAIL-068 | ojs | `APP\mail\mailables\SubscriptionPurchaseInstitutional` | Notify the subscription manager of a purchased institutional subscription. Key: `SUBSCRIPTION_PURCHASE_INSTL` |
| MAIL-069 | ojs | `APP\mail\mailables\SubscriptionRenewIndividual` | Notify the subscription manager of an individual subscription renewal. Key: `SUBSCRIPTION_RENEW_INDL` |
| MAIL-070 | ojs | `APP\mail\mailables\SubscriptionRenewInstitutional` | Notify the subscription manager of an institutional subscription renewal. Key: `SUBSCRIPTION_RENEW_INSTL` |

## Mailables — OMP (`classes/mail/mailables/`)

| ID | Apps | Pointer | Description |
|---|---|---|---|
| MAIL-071 | omp | `APP\mail\mailables\DecisionSendInternalReviewNotifyAuthor` | Author notification for the send-to-internal-review decision. Key: `EDITOR_DECISION_SEND_TO_INTERNAL` |

## Mailables — OPS (`classes/mail/mailables/`)

| ID | Apps | Pointer | Description |
|---|---|---|---|
| MAIL-072 | ops | `APP\mail\mailables\PostedAcknowledgement` | Email to the submitting author when their preprint is posted (gated by context setting `postedAcknowledgement` via OPS `Repository::isMailableEnabled()`). Key: `POSTED_ACK` |
| MAIL-073 | ops | `APP\mail\mailables\PostedNewVersionAcknowledgement` | Email to the submitting author when a new version of their preprint is posted. Key: `POSTED_NEW_VERSION_ACK` |
| MAIL-074 | ops | `APP\mail\mailables\SubmissionAcknowledgementCanPost` | Submission acknowledgement variant for authors who can post the preprint themselves. Key: `SUBMISSION_ACK_CAN_POST` |

## Mailables — bundled plugin (found via registry `find`)

| ID | Apps | Pointer | Description |
|---|---|---|---|
| MAIL-075 | ojs omp | `APP\plugins\paymethod\manual\mailables\ManualPaymentNotify` (`plugins/paymethod/manual/`) | Automatic email notifying the manager of a manual payment needing processing; template installed by the plugin's own `emailTemplates.xml`. Key: `MANUAL_PAYMENT_NOTIFICATION` |

## Orphan registry template keys (in `registry/emailTemplates.xml`, declared by no mailable)

All eight carry an `alternateTo` attribute in the registry XML (recorded mechanically; no liveness judgment).

| ID | Apps | Pointer | Description |
|---|---|---|---|
| MAIL-076 | ojs omp | registry key `COPYEDIT_REQUEST` | Orphan template (name `mailable.copyeditRequest.name`); `alternateTo="DISCUSSION_NOTIFICATION_COPYEDITING"` |
| MAIL-077 | ojs omp ops | registry key `EDITOR_ASSIGN_PRODUCTION` | Orphan template (name `mailable.editorAssignedManual.name`); `alternateTo="DISCUSSION_NOTIFICATION_PRODUCTION"` |
| MAIL-078 | ojs omp | registry key `EDITOR_ASSIGN_REVIEW` | Orphan template (name `mailable.editorAssignedManual.name`); `alternateTo="DISCUSSION_NOTIFICATION_REVIEW"` |
| MAIL-079 | ojs omp | registry key `EDITOR_ASSIGN_SUBMISSION` | Orphan template (name `mailable.editorAssignedManual.name`); `alternateTo="DISCUSSION_NOTIFICATION_SUBMISSION"` |
| MAIL-080 | omp | registry key `INDEX_COMPLETE` | Orphan template (name `mailable.indexComplete.name`); `alternateTo="DISCUSSION_NOTIFICATION_PRODUCTION"` |
| MAIL-081 | omp | registry key `INDEX_REQUEST` | Orphan template (name `mailable.indexRequest.name`); `alternateTo="DISCUSSION_NOTIFICATION_PRODUCTION"` |
| MAIL-082 | ojs omp | registry key `LAYOUT_COMPLETE` | Orphan template (name `mailable.layoutComplete.name`); `alternateTo="DISCUSSION_NOTIFICATION_PRODUCTION"` |
| MAIL-083 | ojs omp | registry key `LAYOUT_REQUEST` | Orphan template (name `mailable.layoutRequest.name`); `alternateTo="DISCUSSION_NOTIFICATION_PRODUCTION"` |
