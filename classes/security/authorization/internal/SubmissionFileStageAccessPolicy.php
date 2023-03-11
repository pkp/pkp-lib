<?php
/**
 * @file classes/security/authorization/internal/SubmissionFileStageAccessPolicy.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SubmissionFileStageAccessPolicy
 * @ingroup security_authorization_internal
 *
 * @brief Submission file policy to ensure that the user can read or write to a particular
 * 	file stage based on their stage assignments. This policy expects submission, user roles
 *  and workflow stage assignments in the authorized context.
 */

namespace PKP\security\authorization\internal;

use APP\core\Application;
use APP\decision\Decision;
use APP\facades\Repo;
use PKP\db\DAORegistry;
use PKP\security\authorization\AuthorizationPolicy;
use PKP\security\authorization\SubmissionFileAccessPolicy;

use PKP\security\Role;
use PKP\submissionFile\SubmissionFile;

class SubmissionFileStageAccessPolicy extends AuthorizationPolicy
{
    /** @var int SUBMISSION_FILE_... */
    public $_fileStage;

    /** @var int SUBMISSION_FILE_ACCESS_READ... */
    public $_action;

    /**
     * Constructor
     *
     * @param int $fileStage SUBMISSION_FILE_...
     * @param int $action SUBMISSION_FILE_ACCESS_READ or SUBMISSION_FILE_ACCESS_MODIFY
     * @param string $message The message to display when authorization is denied
     */
    public function __construct($fileStage, $action, $message)
    {
        parent::__construct($message);
        $this->_fileStage = $fileStage;
        $this->_action = $action;
    }


    //
    // Implement template methods from AuthorizationPolicy
    //
    /**
     * @see AuthorizationPolicy::effect()
     */
    public function effect()
    {
        $submission = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION);
        $userRoles = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_USER_ROLES);
        $stageAssignments = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_ACCESSIBLE_WORKFLOW_STAGES);

        // File stage required
        if (empty($this->_fileStage)) {
            $this->setAdvice(AuthorizationPolicy::AUTHORIZATION_ADVICE_DENY_MESSAGE, $msg = 'api.submissionFiles.400.noFileStageId');
            return AuthorizationPolicy::AUTHORIZATION_DENY;
        }

        // Managers and site admins can access file stages when not assigned or when assigned as a manager
        if (empty($stageAssignments)) {
            if (count(array_intersect([Role::ROLE_ID_MANAGER, Role::ROLE_ID_SITE_ADMIN], $userRoles))) {
                return AuthorizationPolicy::AUTHORIZATION_PERMIT;
            }
            return AuthorizationPolicy::AUTHORIZATION_DENY;
        }

        // Determine the allowed file stages
        $assignedFileStages = Repo::submissionFile()
            ->getAssignedFileStages(
                $stageAssignments,
                $this->_action
            );

        // Authors may write to the submission files stage if the submission
        // is not yet complete
        if ($this->_fileStage === SubmissionFile::SUBMISSION_FILE_SUBMISSION && $this->_action === SubmissionFileAccessPolicy::SUBMISSION_FILE_ACCESS_MODIFY) {
            if (!empty($stageAssignments[WORKFLOW_STAGE_ID_SUBMISSION])
                    && count($stageAssignments[WORKFLOW_STAGE_ID_SUBMISSION]) === 1
                    && in_array(Role::ROLE_ID_AUTHOR, $stageAssignments[WORKFLOW_STAGE_ID_SUBMISSION])
                    && $submission->getData('submissionProgress')) {
                $assignedFileStages[] = SubmissionFile::SUBMISSION_FILE_SUBMISSION;
            }
        }

        // Authors may write to the revision files stage if an accept or request revisions
        // decision has been made in the latest round
        if (in_array($this->_fileStage, [SubmissionFile::SUBMISSION_FILE_INTERNAL_REVIEW_REVISION, SubmissionFile::SUBMISSION_FILE_REVIEW_REVISION]) && $this->_action === SubmissionFileAccessPolicy::SUBMISSION_FILE_ACCESS_MODIFY) {
            $reviewStage = $this->_fileStage === SubmissionFile::SUBMISSION_FILE_INTERNAL_REVIEW_REVISION
                ? WORKFLOW_STAGE_ID_INTERNAL_REVIEW
                : WORKFLOW_STAGE_ID_EXTERNAL_REVIEW;

            if (count($stageAssignments[$reviewStage]) === 1 && in_array(Role::ROLE_ID_AUTHOR, $stageAssignments[$reviewStage])) {
                $reviewRoundDao = DAORegistry::getDAO('ReviewRoundDAO'); /** @var ReviewRoundDAO $reviewRoundDao */
                $reviewRound = $reviewRoundDao->getLastReviewRoundBySubmissionId($submission->getId(), $reviewStage);
                if ($reviewRound) {
                    $countDecisions = Repo::decision()->getCollector()
                        ->filterBySubmissionIds([$submission->getId()])
                        ->filterByStageIds([$reviewRound->getStageId()])
                        ->filterByReviewRoundIds([$reviewRound->getId()])
                        ->filterByDecisionTypes([
                            Decision::ACCEPT,
                            Decision::PENDING_REVISIONS,
                            Decision::NEW_EXTERNAL_ROUND,
                            Decision::RESUBMIT
                        ])
                        ->getCount();

                    if ($countDecisions) {
                        $assignedFileStages[] = $this->_fileStage;
                    }
                }
            }
        }

        if (in_array($this->_fileStage, $assignedFileStages)) {
            $this->addAuthorizedContextObject(Application::ASSOC_TYPE_ACCESSIBLE_FILE_STAGES, $assignedFileStages);
            return AuthorizationPolicy::AUTHORIZATION_PERMIT;
        }

        return AuthorizationPolicy::AUTHORIZATION_DENY;
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\security\authorization\internal\SubmissionFileStageAccessPolicy', '\SubmissionFileStageAccessPolicy');
}
