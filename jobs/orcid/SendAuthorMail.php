<?php

/**
 * @file jobs/orcid/SendAuthorMail.php
 *
 * Copyright (c) 2014-2024 Simon Fraser University
 * Copyright (c) 2000-2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SendAuthorMail
 *
 * @ingroup jobs
 *
 * @brief Job to send email to author for ORCID verification.
 */

namespace PKP\jobs\orcid;

use APP\author\Author;
use APP\facades\Repo;
use Illuminate\Support\Facades\Mail;
use PKP\context\Context;
use PKP\jobs\BaseJob;
use PKP\mail\mailables\OrcidCollectAuthorId;
use PKP\mail\mailables\OrcidRequestAuthorAuthorization;
use PKP\orcid\OrcidManager;

class SendAuthorMail extends BaseJob
{
    public function __construct(
        private Author $author,
        private Context $context,
        /** @var bool $updateAuthor If true, update the author fields in the database. Use only if not called from a function, which will already update the author. */
        private bool $updateAuthor = false
    ) {
    }

    /**
     * handle the queue job execution process
     */
    public function handle()
    {
        if ($this->context === null) {
            throw new \Exception('Author ORCID emails should only be sent from a Context, never site-wide');
        }

        $contextId = $this->context->getId();
        $publicationId = $this->author->getData('publicationId');
        $publication = Repo::publication()->get($publicationId);
        $submission = Repo::submission()->get($publication->getData('submissionId'));

        $emailToken = md5(microtime() . $this->author->getEmail());
        $this->author->setData('orcidEmailToken', $emailToken);
        $oauthUrl = OrcidManager::buildOAuthUrl('verify', ['token' => $emailToken, 'state' => $publicationId]);

        if (OrcidManager::isMemberApiEnabled($this->context)) {
            $mailable = new OrcidRequestAuthorAuthorization($this->context, $submission, $oauthUrl);
        } else {
            $mailable = new OrcidCollectAuthorId($this->context, $submission, $oauthUrl);
        }

        // Set From to primary journal contact
        $mailable->from($this->context->getData('contactEmail'), $this->context->getData('contactName'));

        // Send to author
        $mailable->recipients([$this->author]);
        $emailTemplateKey = $mailable::getEmailTemplateKey();
        $emailTemplate = Repo::emailTemplate()->getByKey($contextId, $emailTemplateKey);
        $mailable->body($emailTemplate->getLocalizedData('body'))
            ->subject($emailTemplate->getLocalizedData('subject'));
        Mail::send($mailable);

        if ($this->updateAuthor) {
            Repo::author()->dao->update($this->author);
        }
    }
}
