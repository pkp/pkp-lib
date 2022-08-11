<?php

/**
 * @file classes/migration/upgrade/v3_4_0/I7264_UpdateEmailTemplates.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I7264_UpdateEmailTemplates
 * @brief Describe upgrade/downgrade operations for DB table email_templates.
 */

namespace PKP\migration\upgrade\v3_4_0;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

abstract class I7264_UpdateEmailTemplates extends \PKP\migration\Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Rename email template keys
        foreach ([
            'email_templates',
            'email_templates_default',
            'email_templates_default_data'
        ] as $tableName) {
            DB::table($tableName)->where('email_key', 'USER_VALIDATE')
                ->update(['email_key' => 'USER_VALIDATE_CONTEXT']);

            DB::table($tableName)->where('email_key', 'PUBLISH_NOTIFY')
                ->update(['email_key' => 'ISSUE_PUBLISH_NOTIFY']);

            DB::table($tableName)->where('email_key', 'REVIEW_REQUEST_REMIND_AUTO')
                ->update(['email_key' => 'REVIEW_RESPONSE_OVERDUE_AUTO']);

            DB::table($tableName)->where('email_key', 'REVIEW_REQUEST_REMIND_AUTO_ONECLICK')
                ->update(['email_key' => 'REVIEW_RESPONSE_OVERDUE_AUTO_ONECLICK']);
        }

        // Add new template for email which is sent to a user registered from a site
        DB::table('email_templates_default')->insert([
            'email_key' => 'USER_VALIDATE_SITE',
            'can_disable' => 0,
        ]);

        DB::table('email_templates_default_data')->insertUsing([
            'email_key',
            'locale',
            'subject',
            'body',
            'description'
        ], function (Builder $q) {
            $q->selectRaw('? as email_key', ['USER_VALIDATE_SITE'])
                ->addSelect('locale', 'subject', 'body', 'description')
                ->from('email_templates_default_data')
                ->where('email_key', '=', 'USER_VALIDATE_CONTEXT');
        });

        // Replace all template variables
        $oldNewVariablesMap = $this->oldNewVariablesMap();
        $this->renameTemplateVariables($oldNewVariablesMap);
    }

    /**
     * Reverse the downgrades
     */
    public function down(): void
    {
        // Revert variables renaming
        $newOldVariablesMap = [];
        foreach ($this->oldNewVariablesMap() as $emailKey => $variablesMap) {
            $newOldVariablesMap[$emailKey] = array_flip($variablesMap);
        }
        $this->renameTemplateVariables($newOldVariablesMap);

        // Revert renaming email template keys
        foreach ([
            'email_templates',
            'email_templates_default',
            'email_templates_default_data'
        ] as $tableName) {
            DB::table($tableName)->where('email_key', 'USER_VALIDATE_CONTEXT')
                ->update(['email_key' => 'USER_VALIDATE']);

            DB::table($tableName)->where('email_key', 'ISSUE_PUBLISH_NOTIFY')
                ->update(['email_key' => 'PUBLISH_NOTIFY']);

            DB::table($tableName)->where('email_key', 'REVIEW_RESPONSE_OVERDUE_AUTO')
                ->update(['email_key' => 'REVIEW_REQUEST_REMIND_AUTO']);

            DB::table($tableName)->where('email_key', 'REVIEW_RESPONSE_OVERDUE_AUTO_ONECLICK')
                ->update(['email_key' => 'REVIEW_REQUEST_REMIND_AUTO_ONECLICK']);

            DB::table($tableName)->where('email_key', 'USER_VALIDATE_SITE')->delete();
        }
    }

    /**
     * Replaces email template variables in templates' subject and body
     */
    protected function renameTemplateVariables(array $oldNewVariablesMap): void
    {
        foreach ($oldNewVariablesMap as $emailKey => $variablesMap) {
            $variables = [];
            $replacements = [];
            foreach ($variablesMap as $key => $value) {
                $variables[] = '/\{\$' . $key . '\}/';
                $replacements[] = '{$' . $value . '}';
            }

            // Default templates
            $data = DB::table('email_templates_default_data')->where('email_key', $emailKey)->get();
            $data->each(function (object $entry) use ($variables, $replacements) {
                $subject = preg_replace($variables, $replacements, $entry->subject);
                $body = preg_replace($variables, $replacements, $entry->body);
                DB::table('email_templates_default_data')
                    ->where('email_key', $entry->{'email_key'})
                    ->where('locale', $entry->{'locale'})
                    ->update(['subject' => $subject, 'body' => $body]);
            });

            // Custom templates
            $customData = DB::table('email_templates')->where('email_key', $emailKey)->get();
            $customData->each(function (object $customEntry) use ($variables, $replacements) {
                $emailRows = DB::table('email_templates_settings')->where('email_id', $customEntry->{'email_id'})->get();
                foreach ($emailRows as $emailRow) {
                    $value = preg_replace($variables, $replacements, $emailRow->{'setting_value'});
                    DB::table('email_templates_settings')
                        ->where('email_id', $emailRow->{'email_id'})
                        ->where('locale', $emailRow->{'locale'})
                        ->where('setting_name', $emailRow->{'setting_name'})
                        ->update(['setting_value' => $value]);
                }
            });
        }
    }

    /**
     * @return array [email_key => [old_variable => new_variable]]
     */
    protected function oldNewVariablesMap(): array
    {
        return [
            'NOTIFICATION' => [
                'url' => 'notificationUrl',
                'principalContactSignature' => 'contextSignature',
                'siteTitle' => 'contextName',
            ],
            'NOTIFICATION_CENTER_DEFAULT' => [
                'contextName' => 'contextName',
            ],
            'PASSWORD_RESET_CONFIRM' => [
                'url' => 'passwordResetUrl',
                'principalContactSignature' => 'siteContactName',
            ],
            'PASSWORD_RESET' => [
                'principalContactSignature' => 'siteContactName',
                'username' => 'recipientUsername',
            ],
            'USER_REGISTER' => [
                'userFullName' => 'recipientName',
                'principalContactSignature' => 'contextSignature',
                'contextName' => 'contextName',
                'username' => 'recipientUsername',
            ],
            // new template from USER_VALIDATE
            'USER_VALIDATE_CONTEXT' => [
                'userFullName' => 'recipientName',
                'principalContactSignature' => 'contextSignature',
                'contextName' => 'contextName',
            ],
            // new template from USER_VALIDATE
            'USER_VALIDATE_SITE' => [
                'userFullName' => 'recipientName',
                'principalContactSignature' => 'siteContactName',
                'contextName' => 'siteTitle',
            ],
            'REVIEWER_REGISTER' => [
                'contextName' => 'contextName',
                'principalContactSignature' => 'contextSignature',
                'username' => 'recipientUsername'
            ],
            // renamed from PUBLISH_NOTIFY
            'ISSUE_PUBLISH_NOTIFY' => [
                'contextName' => 'contextName',
                'contextUrl' => 'contextUrl',
                'editorialContactSignature' => 'signature',
            ],
            'LOCKSS_EXISTING_ARCHIVE' => [
                'contextName' => 'contextName',
                'contextUrl' => 'contextUrl',
                'principalContactSignature' => 'signature',
            ],
            'LOCKSS_NEW_ARCHIVE' => [
                'contextName' => 'contextName',
                'contextUrl' => 'contextUrl',
                'principalContactSignature' => 'signature',
            ],
            'SUBMISSION_ACK' => [
                'authorName' => 'recipientName',
                'contextName' => 'contextName',
                'authorUsername' => 'recipientUsername',
                'editorContactSignature' => 'contextSignature',
                'submissionUrl' => 'authorSubmissionUrl',
            ],
            'SUBMISSION_ACK_NOT_USER' => [
                'contextName' => 'contextName',
                'editorialContactSignature' => 'contextSignature',
            ],
            // submissionUrl and editorUsername/recipientUsername are used only in the old template
            'EDITOR_ASSIGN' => [
                'editorialContactName' => 'recipientName',
                'contextName' => 'contextName',
                'editorUsername' => 'recipientUsername',
            ],
            'REVIEW_CANCEL' => [
                'reviewerName' => 'recipientName',
                'contextName' => 'contextName',
            ],
            'REVIEW_REINSTATE' => [
                'reviewerName' => 'recipientName',
                'contextName' => 'contextName',
            ],
            'REVIEW_REQUEST' => [
                'reviewerName' => 'recipientName',
                'contextName' => 'contextName',
                'contextUrl' => 'contextUrl',
                'passwordResetUrl' => 'passwordLostUrl',
                'submissionReviewUrl' => 'reviewAssignmentUrl',
                'editorialContactSignature' => 'signature',
            ],
            'REVIEW_REQUEST_SUBSEQUENT' => [
                'reviewerName' => 'recipientName',
                'contextName' => 'contextName',
                'contextUrl' => 'contextUrl',
                'passwordResetUrl' => 'passwordLostUrl',
                'submissionReviewUrl' => 'reviewAssignmentUrl',
                'editorialContactSignature' => 'signature',
            ],
            'REVIEW_REQUEST_ONECLICK' => [
                'reviewerName' => 'recipientName',
                'contextName' => 'contextName',
                'submissionReviewUrl' => 'reviewAssignmentUrl',
                'editorialContactSignature' => 'signature',
            ],
            'REVIEW_REQUEST_ONECLICK_SUBSEQUENT' => [
                'reviewerName' => 'recipientName',
                'contextName' => 'contextName',
                'submissionReviewUrl' => 'reviewAssignmentUrl',
                'editorialContactSignature' => 'signature',
            ],
            'REVIEW_REQUEST_ATTACHED' => [
                'reviewerName' => 'recipientName',
                'editorialContactSignature' => 'signature',
            ],
            'REVIEW_REQUEST_ATTACHED_SUBSEQUENT' => [
                'reviewerName' => 'recipientName',
                'contextName' => 'contextName',
                'editorialContactSignature' => 'signature',
            ],
            // renamed from REVIEW_REQUEST_REMIND_AUTO
            'REVIEW_RESPONSE_OVERDUE_AUTO' => [
                'reviewerName' => 'recipientName',
                'contextName' => 'contextName',
                'contextUrl' => 'contextUrl',
                'submissionReviewUrl' => 'reviewAssignmentUrl',
                'editorialContactSignature' => 'contextSignature',
            ],
            // renamed from REVIEW_REQUEST_REMIND_AUTO_ONECLICK
            'REVIEW_RESPONSE_OVERDUE_AUTO_ONECLICK' => [
                'reviewerName' => 'recipientName',
                'contextName' => 'contextName',
                'submissionReviewUrl' => 'reviewAssignmentUrl',
                'editorialContactSignature' => 'contextSignature',
            ],
            'REVIEW_CONFIRM' => [
                'contextName' => 'contextName',
                'reviewerName' => 'senderName',
            ],
            'REVIEW_DECLINE' => [
                'contextName' => 'contextName',
                'reviewerName' => 'senderName',
            ],
            'REVIEW_ACK' => [
                'reviewerName' => 'recipientName',
                'contextName' => 'contextName',
            ],
            'REVIEW_REMIND' => [
                'reviewerName' => 'recipientName',
                'contextName' => 'contextName',
                'submissionReviewUrl' => 'reviewAssignmentUrl',
                'editorialContactSignature' => 'signature',
            ],
            'REVIEW_REMIND_AUTO' => [
                'reviewerName' => 'recipientName',
                'contextName' => 'contextName',
                'submissionReviewUrl' => 'reviewAssignmentUrl',
                'editorialContactSignature' => 'contextSignature',
            ],
            'REVIEW_REMIND_ONECLICK' => [
                'reviewerName' => 'recipientName',
                'contextName' => 'contextName',
                'submissionReviewUrl' => 'reviewAssignmentUrl',
                'editorialContactSignature' => 'signature',
            ],
            'REVIEW_REMIND_AUTO_ONECLICK' => [
                'reviewerName' => 'recipientName',
                'contextName' => 'contextName',
                'submissionReviewUrl' => 'reviewAssignmentUrl',
                'editorialContactSignature' => 'contextSignature',
            ],
            'EDITOR_DECISION_ACCEPT' => [
                'authorName' => 'authors',
                'contextName' => 'contextName',
                'submissionUrl' => 'authorSubmissionUrl',
            ],
            'EDITOR_DECISION_SEND_TO_EXTERNAL' => [
                'authorName' => 'authors',
                'submissionUrl' => 'authorSubmissionUrl',
            ],
            'EDITOR_DECISION_SEND_TO_PRODUCTION' => [
                'authorName' => 'authors',
                'submissionUrl' => 'authorSubmissionUrl',
            ],
            'EDITOR_DECISION_REVISIONS' => [
                'authorName' => 'authors',
                'submissionUrl' => 'authorSubmissionUrl',
            ],
            'EDITOR_DECISION_RESUBMIT' => [
                'authorName' => 'authors',
                'contextName' => 'contextName',
                'submissionUrl' => 'authorSubmissionUrl',
            ],
            'EDITOR_DECISION_DECLINE' => [
                'authorName' => 'authors',
                'contextName' => 'contextName',
                'submissionUrl' => 'authorSubmissionUrl',
            ],
            'EDITOR_DECISION_INITIAL_DECLINE' => [
                'authorName' => 'authors',
                'submissionUrl' => 'authorSubmissionUrl',
            ],
            'EDITOR_RECOMMENDATION' => [
                'contextName' => 'contextName',
            ],
            'COPYEDIT_REQUEST' => [
                'participantName' => 'recipientName',
                'contextName' => 'contextName',
                'contextUrl' => 'contextUrl',
                'participantUsername' => 'recipientUsername',
                'contextAcronym' => 'journalAcronym',
            ],
            'LAYOUT_REQUEST' => [
                'participantName' => 'recipientName',
                'contextName' => 'contextName',
                'contextUrl' => 'contextUrl',
                'participantUsername' => 'recipientUsername',
            ],
            'LAYOUT_COMPLETE' => [
                'editorialContactName' => 'recipientName',
                'contextName' => 'contextName',
                'participantName' => 'senderName',
            ],
            'EMAIL_LINK' => [
                'authorName' => 'authors',
                'contextName' => 'contextName',
                'articleUrl' => 'submissionUrl',
                'monographUrl' => 'submissionUrl',
            ],
            'SUBSCRIPTION_NOTIFY' => [
                'subscriberName' => 'recipientName',
                'contextName' => 'contextName',
                'username' => 'recipientUsername',
                'subscriptionContactSignature' => 'subscriptionSignature',
            ],
            'OPEN_ACCESS_NOTIFY' => [
                'contextName' => 'contextName',
                'contextUrl' => 'contextUrl',
                'editorialContactSignature' => 'contextSignature',
            ],
            'SUBSCRIPTION_BEFORE_EXPIRY' => [
                'subscriberName' => 'recipientName',
                'contextName' => 'contextName',
                'username' => 'recipientUsername',
                'subscriptionContactSignature' => 'subscriptionSignature',
            ],
            'SUBSCRIPTION_AFTER_EXPIRY' => [
                'subscriberName' => 'recipientName',
                'contextName' => 'contextName',
                'username' => 'recipientUsername',
                'subscriptionContactSignature' => 'subscriptionSignature',
            ],
            'SUBSCRIPTION_AFTER_EXPIRY_LAST' => [
                'subscriberName' => 'recipientName',
                'contextName' => 'contextName',
                'username' => 'recipientUsername',
                'subscriptionContactSignature' => 'subscriptionSignature',
            ],
            'SUBSCRIPTION_PURCHASE_INDL' => [
                'contextName' => 'contextName',
                'userDetails' => 'subscriberDetails',
            ],
            'SUBSCRIPTION_PURCHASE_INSTL' => [
                'contextName' => 'contextName',
                'userDetails' => 'subscriberDetails',
            ],
            'SUBSCRIPTION_RENEW_INDL' => [
                'contextName' => 'contextName',
                'userDetails' => 'subscriberDetails',
            ],
            'SUBSCRIPTION_RENEW_INSTL' => [
                'contextName' => 'contextName',
                'userDetails' => 'subscriberDetails',
            ],
            'CITATION_EDITOR_AUTHOR_QUERY' => [
                'authorFirstName' => 'recipientName',
                'userFirstName' => 'senderName',
                'contextName' => 'contextName',
            ],
            'REVISED_VERSION_NOTIFY' => [
                'editorialContactSignature' => 'signature',
                'authorName' => 'submitterName',
            ],
            'STATISTICS_REPORT_NOTIFICATION' => [
                'principalContactSignature' => 'contextSignature',
                'name' => 'recipientName',
            ],
            'ANNOUNCEMENT' => [
                'title' => 'announcementTitle',
                'summary' => 'announcementSummary',
                'url' => 'announcementUrl',
            ],
            // in OPS only
            'POSTED_ACK' => [
                'authorName' => 'authorPrimary',
                'publicationUrl' => 'submissionUrl',
                'editorialContactSignature' => 'signature'
            ]
        ];
    }
}
