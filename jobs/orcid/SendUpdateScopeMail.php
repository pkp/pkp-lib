<?php

/**
 * @file jobs/orcid/SendUpdateScopeMail.php
 *
 * Copyright (c) 2014-2025 Simon Fraser University
 * Copyright (c) 2000-2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SendUpdateScopeMail
 *
 * @ingroup jobs
 *
 * @brief Job to send email to user to update ORCID OAuth scope.
 */

namespace PKP\jobs\orcid;

use APP\author\Author;
use APP\core\Application;
use APP\facades\Repo;
use APP\submission\Submission;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\Mail;
use PKP\identity\Identity;
use PKP\jobs\BaseJob;
use PKP\mail\mailables\OrcidRequestUpdateScope;
use PKP\orcid\enums\OrcidDepositType;
use PKP\orcid\OrcidManager;
use PKP\user\User;

class SendUpdateScopeMail extends BaseJob implements ShouldBeUnique
{
    public function __construct(
        /** @var Identity $identity Will be either a user or an author */
        private Identity $identity,
        private int $contextId,
        /** @var int $itemId Will be a publication ID or a review assignment ID depending on deposit type */
        private int $itemId,
        private OrcidDepositType $depositType,
    ) {
    }

    /**
     * @inheritDoc
     */
    public function handle()
    {
        $context = Application::getContextDAO()->getById($this->contextId);
        if ($context === null) {
            $this->fail("ORCID emails can only be sent from a context. A context could not be found for contextId: $this->contextId.");
        }

        $submission = $this->getSubmission();
        if (!$submission) {
            $this->fail('A submission could not be found for the associated item');
        }

        $emailToken = md5(microtime() . $this->identity->getEmail());
        $this->identity->setData('orcidEmailToken', $emailToken);
        $oauthUrl = OrcidManager::buildOAuthUrl(
            'updateScope',
            [
                'token' => $emailToken,
                'itemId' => $this->itemId,
                'itemType' => $this->depositType->value,
                'userId' => $this->identity->getId(),
                'userIdType' => $this->identity instanceof User ? 'user' : 'author',
            ],
            $context
        );

        $mailable = new OrcidRequestUpdateScope($context, $submission, $oauthUrl);

        // Set From to primary journal contact
        $mailable->from($context->getData('contactEmail'), $context->getData('contactName'));

        // Send mail
        $mailable->recipients([$this->identity]);
        $emailTemplateKey = $mailable::getEmailTemplateKey();
        $emailTemplate = Repo::emailTemplate()->getByKey($context->getId(), $emailTemplateKey);
        $mailable->body($emailTemplate->getLocalizedData('body'))
            ->subject($emailTemplate->getLocalizedData('subject'));
        Mail::send($mailable);

        $this->saveIdentity();
    }

    /**
     * Saves identity information using the correct DAO (`Author` or `User`).
     */
    private function saveIdentity(): void
    {
        if ($this->identity instanceof Author) {
            Repo::author()->dao->update($this->identity);
        } else if ($this->identity instanceof User) {
            Repo::user()->dao->update($this->identity);
        }
    }

    /**
     * Gets the associated submission in the correct way depending on the type of item deposit (work or review).
     */
    private function getSubmission(): ?Submission
    {
        switch ($this->depositType) {
            case OrcidDepositType::WORK:
                $publication = Repo::publication()->get($this->itemId);
                 return Repo::submission()->get($publication->getData('submissionId'));
            case OrcidDepositType::REVIEW:
                $reviewAssigment = Repo::reviewAssignment()->get($this->itemId);
                return Repo::submission()->get($reviewAssigment->getSubmissionId());
        }

        return null;
    }

    /**
     * Provides a unique ID for the job to ensure only one job is dispatched per User/Author at a time.
     */
    public function uniqueId(): string
    {
        return $this->identity->getId();
    }
}
