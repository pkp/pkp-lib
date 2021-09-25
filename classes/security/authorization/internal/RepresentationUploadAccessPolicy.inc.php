<?php
/**
 * @file classes/security/authorization/internal/RepresentationUploadAccessPolicy.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class RepresentationUploadAccessPolicy
 * @ingroup security_authorization_internal
 *
 * @brief Policy that checks whether a file can be uploaded to a representation.
 *   It checks whether the user is allowed to access the representation file stage,
 *   whether the representation exists, whether it matches the authorized submission,
 *   and whether it is not part of a published publication. This policy expects an
 *   authorized submission in the authorization context.
 */

namespace PKP\security\authorization\internal;

use APP\core\Application;
use APP\facades\Repo;

use PKP\security\authorization\AuthorizationPolicy;
use PKP\security\authorization\DataObjectRequiredPolicy;
use PKP\submission\PKPSubmission;
use PKP\submissionFile\SubmissionFile;

class RepresentationUploadAccessPolicy extends DataObjectRequiredPolicy
{
    /** @var int */
    public $_representationId;

    /**
     * Constructor
     *
     * @param PKPRequest $request
     * @param array $args request parameters
     * @param int $representationId
     */
    public function __construct($request, &$args, $representationId)
    {
        parent::__construct($request, $args, 'user.authorization.accessDenied');
        $this->_representationId = $representationId;
    }

    //
    // Implement template methods from AuthorizationPolicy
    //
    /**
     * @see DataObjectRequiredPolicy::dataObjectEffect()
     */
    public function dataObjectEffect()
    {
        AppLocale::requireComponents([LOCALE_COMPONENT_PKP_SUBMISSION, LOCALE_COMPONENT_APP_SUBMISSION]);

        $assignedFileStages = $this->getAuthorizedContextObject(ASSOC_TYPE_ACCESSIBLE_FILE_STAGES);
        if (empty($assignedFileStages) || !in_array(SubmissionFile::SUBMISSION_FILE_PROOF, $assignedFileStages)) {
            return AuthorizationPolicy::AUTHORIZATION_DENY;
        }

        if (empty($this->_representationId)) {
            $this->setAdvice(AuthorizationPolicy::AUTHORIZATION_ADVICE_DENY_MESSAGE, 'user.authorization.representationNotFound');
            return AuthorizationPolicy::AUTHORIZATION_DENY;
        }

        $representationDao = Application::get()->getRepresentationDAO();
        $representation = $representationDao->getById($this->_representationId);

        if (!$representation) {
            return AuthorizationPolicy::AUTHORIZATION_DENY;
        }

        $submission = $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION);
        if (!$submission) {
            $this->setAdvice(AuthorizationPolicy::AUTHORIZATION_ADVICE_DENY_MESSAGE, 'user.authorization.invalidSubmission');
            return AuthorizationPolicy::AUTHORIZATION_DENY;
        }

        $publication = Repo::publication()->get($representation->getData('publicationId'));
        if (!$publication) {
            $this->setAdvice(AuthorizationPolicy::AUTHORIZATION_ADVICE_DENY_MESSAGE, 'galley.publicationNotFound');
            return AuthorizationPolicy::AUTHORIZATION_DENY;
        }

        // Publication and submission must match
        if ($publication->getData('submissionId') !== $submission->getId()) {
            $this->setAdvice(AuthorizationPolicy::AUTHORIZATION_ADVICE_DENY_MESSAGE, 'user.authorization.invalidPublication');
            return AuthorizationPolicy::AUTHORIZATION_DENY;
        }

        // Representations can not be modified on published publications
        if ($publication->getData('status') === PKPSubmission::STATUS_PUBLISHED) {
            $this->setAdvice(AuthorizationPolicy::AUTHORIZATION_ADVICE_DENY_MESSAGE, 'galley.editPublishedDisabled');
            return AuthorizationPolicy::AUTHORIZATION_DENY;
        }

        $this->addAuthorizedContextObject(ASSOC_TYPE_REPRESENTATION, $representation);

        return AuthorizationPolicy::AUTHORIZATION_PERMIT;
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\security\authorization\internal\RepresentationUploadAccessPolicy', '\RepresentationUploadAccessPolicy');
}
