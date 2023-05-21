<?php
/**
 * @file classes/security/authorization/SubmissionFileAccessPolicy.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SubmissionFileAccessPolicy
 *
 * @ingroup security_authorization
 *
 * @brief Base class to control (write) access to submissions and (read) access to
 * submission files.
 */

namespace PKP\security\authorization;

use PKP\core\PKPRequest;
use PKP\security\authorization\internal\ContextPolicy;
use PKP\security\authorization\internal\SubmissionFileAssignedQueryAccessPolicy;
use PKP\security\authorization\internal\SubmissionFileAssignedReviewerAccessPolicy;
use PKP\security\authorization\internal\SubmissionFileAuthorEditorPolicy;
use PKP\security\authorization\internal\SubmissionFileMatchesSubmissionPolicy;
use PKP\security\authorization\internal\SubmissionFileMatchesWorkflowStageIdPolicy;
use PKP\security\authorization\internal\SubmissionFileNotQueryAccessPolicy;
use PKP\security\authorization\internal\SubmissionFileRequestedRevisionRequiredPolicy;
use PKP\security\authorization\internal\SubmissionFileStageRequiredPolicy;
use PKP\security\authorization\internal\SubmissionFileUploaderAccessPolicy;
use PKP\security\authorization\internal\SubmissionRequiredPolicy;
use PKP\security\authorization\internal\UserAccessibleWorkflowStageRequiredPolicy;
use PKP\security\authorization\internal\WorkflowStageRequiredPolicy;
use PKP\security\Role;
use PKP\submissionFile\SubmissionFile;

class SubmissionFileAccessPolicy extends ContextPolicy
{
    // Define the bitfield for submission file access levels
    public const SUBMISSION_FILE_ACCESS_READ = 1;
    public const SUBMISSION_FILE_ACCESS_MODIFY = 2;

    /** @var PolicySet $_baseFileAccessPolicy the base file file policy before _SUB_EDITOR is considered */
    public $_baseFileAccessPolicy;

    /**
     * Constructor
     *
     * @param PKPRequest $request
     * @param array $args request parameters
     * @param array $roleAssignments
     * @param int $mode bitfield SubmissionFileAccessPolicy::SUBMISSION_FILE_ACCESS_...
     * @param int $submissionFileId
     * @param string $submissionParameterName the request parameter we expect
     *  the submission id in.
     */
    public function __construct($request, $args, $roleAssignments, $mode, $submissionFileId = null, $submissionParameterName = 'submissionId')
    {
        // TODO: Refine file access policies. Differentiate between
        // read and modify access using bitfield:
        // $mode & SubmissionFileAccessPolicy::SUBMISSION_FILE_ACCESS_...

        parent::__construct($request);
        $this->_baseFileAccessPolicy = $this->buildFileAccessPolicy($request, $args, $roleAssignments, $mode, $submissionFileId, $submissionParameterName);
    }

    /**
     *
     * @param PKPRequest $request
     * @param array $args
     * @param array $roleAssignments
     * @param int $mode bitfield
     * @param int $submissionFileId
     * @param string $submissionParameterName
     */
    public function buildFileAccessPolicy($request, $args, $roleAssignments, $mode, $submissionFileId, $submissionParameterName)
    {
        // We need a submission matching the file in the request.
        $this->addPolicy(new SubmissionRequiredPolicy($request, $args, $submissionParameterName));
        $this->addPolicy(new SubmissionFileMatchesSubmissionPolicy($request, $submissionFileId));

        // Authors, managers and series editors potentially have
        // access to submission files. We'll have to define
        // differentiated policies for those roles in a policy set.
        $fileAccessPolicy = new PolicySet(PolicySet::COMBINING_PERMIT_OVERRIDES);

        //
        // Site administrator role
        if (isset($roleAssignments[Role::ROLE_ID_SITE_ADMIN])) {
            $adminPolicy = new PolicySet(PolicySet::COMBINING_DENY_OVERRIDES);
            $adminPolicy->addPolicy(new RoleBasedHandlerOperationPolicy($request, Role::ROLE_ID_SITE_ADMIN, $roleAssignments[Role::ROLE_ID_SITE_ADMIN]));
            // A valid workflow stage needs to be in the authorized objects for most file operations
            $stageId = (int) $request->getUserVar('stageId');
            $adminPolicy->addPolicy(new WorkflowStageRequiredPolicy($stageId));
            $fileAccessPolicy->addPolicy($adminPolicy);
        }

        //
        // Managerial role
        //
        if (isset($roleAssignments[Role::ROLE_ID_MANAGER])) {
            // Managers can access all submission files as long as the manager has not
            // been assigned to a lesser role in the stage.
            $managerFileAccessPolicy = new PolicySet(PolicySet::COMBINING_DENY_OVERRIDES);
            $managerFileAccessPolicy->addPolicy(new RoleBasedHandlerOperationPolicy($request, Role::ROLE_ID_MANAGER, $roleAssignments[Role::ROLE_ID_MANAGER]));

            $stageId = $request->getUserVar('stageId'); // WORKFLOW_STAGE_ID_...
            $managerFileAccessPolicy->addPolicy(new SubmissionFileMatchesWorkflowStageIdPolicy($request, $submissionFileId, $stageId));
            $managerFileAccessPolicy->addPolicy(new WorkflowStageAccessPolicy($request, $args, $roleAssignments, 'submissionId', $stageId));
            $managerFileAccessPolicy->addPolicy(new AssignedStageRoleHandlerOperationPolicy($request, Role::ROLE_ID_MANAGER, $roleAssignments[Role::ROLE_ID_MANAGER], $stageId));

            $fileAccessPolicy->addPolicy($managerFileAccessPolicy);
        }


        //
        // Author role
        //
        if (isset($roleAssignments[Role::ROLE_ID_AUTHOR])) {
            // 1) Author role user groups can access whitelisted operations ...
            $authorFileAccessPolicy = new PolicySet(PolicySet::COMBINING_DENY_OVERRIDES);
            $authorFileAccessPolicy->addPolicy(new RoleBasedHandlerOperationPolicy($request, Role::ROLE_ID_AUTHOR, $roleAssignments[Role::ROLE_ID_AUTHOR]));

            // 2) ...if they are assigned to the workflow stage as an author.  Note: This loads the application-specific policy class.
            $stageId = $request->getUserVar('stageId');
            $authorFileAccessPolicy->addPolicy(new WorkflowStageAccessPolicy($request, $args, $roleAssignments, 'submissionId', $stageId));
            $authorFileAccessPolicy->addPolicy(new SubmissionFileMatchesWorkflowStageIdPolicy($request, $submissionFileId, $stageId));
            $authorFileAccessPolicy->addPolicy(new AssignedStageRoleHandlerOperationPolicy($request, Role::ROLE_ID_AUTHOR, $roleAssignments[Role::ROLE_ID_AUTHOR], $stageId));

            // 3) ...and if they meet one of the following requirements:
            $authorFileAccessOptionsPolicy = new PolicySet(PolicySet::COMBINING_PERMIT_OVERRIDES);

            // 3a) If the file was uploaded by the current user, allow...
            $authorFileAccessOptionsPolicy->addPolicy(new SubmissionFileUploaderAccessPolicy($request, $submissionFileId));

            // 3b) ...or if the file is a file in a review round with requested revision decision, allow...
            // Note: This loads the application-specific policy class
            $authorFileAccessOptionsPolicy->addPolicy(new SubmissionFileRequestedRevisionRequiredPolicy($request, $submissionFileId));

            // ...or if we don't want to modify the file...
            if (!($mode & self::SUBMISSION_FILE_ACCESS_MODIFY)) {
                // 3c) ...the file is at submission stage...
                $authorFileAccessOptionsPolicy->addPolicy(new SubmissionFileStageRequiredPolicy($request, $submissionFileId, SubmissionFile::SUBMISSION_FILE_SUBMISSION));

                // 3d) ...or the file is a viewable reviewer response...
                $authorFileAccessOptionsPolicy->addPolicy(new SubmissionFileStageRequiredPolicy($request, $submissionFileId, SubmissionFile::SUBMISSION_FILE_REVIEW_ATTACHMENT, true));

                // 3e) ...or if the file is part of a query assigned to the user...
                $authorFileAccessOptionsPolicy->addPolicy(new SubmissionFileAssignedQueryAccessPolicy($request, $submissionFileId));

                // 3f) ...or the file is at revision stage...
                $authorFileAccessOptionsPolicy->addPolicy(new SubmissionFileStageRequiredPolicy($request, $submissionFileId, SubmissionFile::SUBMISSION_FILE_REVIEW_REVISION));

                // 3f) ...or the file is at revision stage...
                $authorFileAccessOptionsPolicy->addPolicy(new SubmissionFileStageRequiredPolicy($request, $submissionFileId, SubmissionFile::SUBMISSION_FILE_INTERNAL_REVIEW_REVISION));

                // 3g) ...or the file is a copyedited file...
                $authorFileAccessOptionsPolicy->addPolicy(new SubmissionFileStageRequiredPolicy($request, $submissionFileId, SubmissionFile::SUBMISSION_FILE_COPYEDIT));

                // 3h) ...or the file is a representation (galley/publication format)...
                $authorFileAccessOptionsPolicy->addPolicy(new SubmissionFileStageRequiredPolicy($request, $submissionFileId, SubmissionFile::SUBMISSION_FILE_PROOF));
            }

            // Add the rules from 3)
            $authorFileAccessPolicy->addPolicy($authorFileAccessOptionsPolicy);

            $fileAccessPolicy->addPolicy($authorFileAccessPolicy);
        }


        //
        // Reviewer role
        //
        if (isset($roleAssignments[Role::ROLE_ID_REVIEWER])) {
            // 1) Reviewers can access whitelisted operations ...
            $reviewerFileAccessPolicy = new PolicySet(PolicySet::COMBINING_DENY_OVERRIDES);
            $reviewerFileAccessPolicy->addPolicy(new RoleBasedHandlerOperationPolicy($request, Role::ROLE_ID_REVIEWER, $roleAssignments[Role::ROLE_ID_REVIEWER]));

            // 2) ...if they meet one of the following requirements:
            $reviewerFileAccessOptionsPolicy = new PolicySet(PolicySet::COMBINING_PERMIT_OVERRIDES);

            // 2a) If the file was uploaded by the current user, allow.
            $reviewerFileAccessOptionsPolicy->addPolicy(new SubmissionFileUploaderAccessPolicy($request, $submissionFileId));

            // 2b) If the file is part of an assigned review, and we're not
            // trying to modify it, allow.
            if (!($mode & self::SUBMISSION_FILE_ACCESS_MODIFY)) {
                $reviewerFileAccessOptionsPolicy->addPolicy(new SubmissionFileAssignedReviewerAccessPolicy($request, $submissionFileId));
            }

            // 2c) If the file is part of a query assigned to the user, allow.
            $reviewerFileAccessOptionsPolicy->addPolicy(new SubmissionFileAssignedQueryAccessPolicy($request, $submissionFileId));

            // Add the rules from 2)
            $reviewerFileAccessPolicy->addPolicy($reviewerFileAccessOptionsPolicy);

            // Add this policy set
            $fileAccessPolicy->addPolicy($reviewerFileAccessPolicy);
        }


        //
        // Assistant role.
        //
        if (isset($roleAssignments[Role::ROLE_ID_ASSISTANT])) {
            // 1) Assistants can access whitelisted operations...
            $contextAssistantFileAccessPolicy = new PolicySet(PolicySet::COMBINING_DENY_OVERRIDES);
            $contextAssistantFileAccessPolicy->addPolicy(new RoleBasedHandlerOperationPolicy($request, Role::ROLE_ID_ASSISTANT, $roleAssignments[Role::ROLE_ID_ASSISTANT]));

            // 2) ... but only if they have been assigned to the submission workflow as an assistant.
            // Note: This loads the application-specific policy class
            $stageId = $request->getUserVar('stageId');
            $contextAssistantFileAccessPolicy->addPolicy(new WorkflowStageAccessPolicy($request, $args, $roleAssignments, 'submissionId', $stageId));
            $contextAssistantFileAccessPolicy->addPolicy(new SubmissionFileMatchesWorkflowStageIdPolicy($request, $submissionFileId, $stageId));
            $contextAssistantFileAccessPolicy->addPolicy(new AssignedStageRoleHandlerOperationPolicy($request, Role::ROLE_ID_ASSISTANT, $roleAssignments[Role::ROLE_ID_ASSISTANT], $stageId));

            // 3) ...and if they meet one of the following requirements:
            $contextAssistantFileAccessOptionsPolicy = new PolicySet(PolicySet::COMBINING_PERMIT_OVERRIDES);

            // 3a) ...the file not part of a query...
            $contextAssistantFileAccessOptionsPolicy->addPolicy(new SubmissionFileNotQueryAccessPolicy($request, $submissionFileId));

            // 3b) ...or the file is part of a query they are assigned to...
            $contextAssistantFileAccessOptionsPolicy->addPolicy(new SubmissionFileAssignedQueryAccessPolicy($request, $submissionFileId));

            // Add the rules from 3
            $contextAssistantFileAccessPolicy->addPolicy($contextAssistantFileAccessOptionsPolicy);

            $fileAccessPolicy->addPolicy($contextAssistantFileAccessPolicy);
        }

        //
        // Sub editor role
        //
        if (isset($roleAssignments[Role::ROLE_ID_SUB_EDITOR])) {
            // 1) Sub editors can access all operations on submissions ...
            $subEditorFileAccessPolicy = new PolicySet(PolicySet::COMBINING_DENY_OVERRIDES);
            $subEditorFileAccessPolicy->addPolicy(new RoleBasedHandlerOperationPolicy($request, Role::ROLE_ID_SUB_EDITOR, $roleAssignments[Role::ROLE_ID_SUB_EDITOR]));

            // 2) ... but only if they have been assigned as a subeditor to the requested submission ...
            $stageId = $request->getUserVar('stageId');
            $subEditorFileAccessPolicy->addPolicy(new WorkflowStageAccessPolicy($request, $args, $roleAssignments, 'submissionId', $stageId));
            $subEditorFileAccessPolicy->addPolicy(new UserAccessibleWorkflowStageRequiredPolicy($request));
            $subEditorFileAccessPolicy->addPolicy(new AssignedStageRoleHandlerOperationPolicy($request, Role::ROLE_ID_SUB_EDITOR, $roleAssignments[Role::ROLE_ID_SUB_EDITOR], $stageId));
            $subEditorFileAccessPolicy->addPolicy(new SubmissionFileMatchesWorkflowStageIdPolicy($request, $submissionFileId, $stageId));

            // 3) ...and if they meet one of the following requirements:
            $subEditorQueryFileAccessPolicy = new PolicySet(PolicySet::COMBINING_PERMIT_OVERRIDES);

            // 3a) ...the file not part of a query...
            $subEditorQueryFileAccessPolicy->addPolicy(new SubmissionFileNotQueryAccessPolicy($request, $submissionFileId));

            // 3b) ...or the file is part of a query they are assigned to...
            $subEditorQueryFileAccessPolicy->addPolicy(new SubmissionFileAssignedQueryAccessPolicy($request, $submissionFileId));

            // Add the rules from 3
            $subEditorFileAccessPolicy->addPolicy($subEditorQueryFileAccessPolicy);

            // 4) ... and only if they are not also assigned as an author and this is not part of a anonymous review
            $subEditorFileAccessPolicy->addPolicy(new SubmissionFileAuthorEditorPolicy($request, $submissionFileId));

            $fileAccessPolicy->addPolicy($subEditorFileAccessPolicy);
        }

        $this->addPolicy($fileAccessPolicy);

        return $fileAccessPolicy;
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\security\authorization\SubmissionFileAccessPolicy', '\SubmissionFileAccessPolicy');
    define('SUBMISSION_FILE_ACCESS_READ', SubmissionFileAccessPolicy::SUBMISSION_FILE_ACCESS_READ);
    define('SUBMISSION_FILE_ACCESS_MODIFY', SubmissionFileAccessPolicy::SUBMISSION_FILE_ACCESS_MODIFY);
}
