<?php
/**
 * @file classes/security/authorization/SubmissionFileAccessPolicy.inc.php
 *
 * Copyright (c) 2014-2018 Simon Fraser University
 * Copyright (c) 2000-2018 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class SubmissionFileAccessPolicy
 * @ingroup security_authorization
 *
 * @brief Base class to control (write) access to submissions and (read) access to
 * submission files.
 */

import('lib.pkp.classes.security.authorization.internal.ContextPolicy');
import('lib.pkp.classes.security.authorization.RoleBasedHandlerOperationPolicy');

// Define the bitfield for submission file access levels
define('SUBMISSION_FILE_ACCESS_READ', 1);
define('SUBMISSION_FILE_ACCESS_MODIFY', 2);

class SubmissionFileAccessPolicy extends ContextPolicy {

	/** var $_baseFileAccessPolicy the base file file policy before _SUB_EDITOR is considered */
	var $_baseFileAccessPolicy;

	/**
	 * Constructor
	 * @param $request PKPRequest
	 * @param $args array request parameters
	 * @param $roleAssignments array
	 * @param $mode int bitfield SUBMISSION_FILE_ACCESS_...
	 * @param $fileIdAndRevision string
	 * @param $submissionParameterName string the request parameter we expect
	 *  the submission id in.
	 */
	function __construct($request, $args, $roleAssignments, $mode, $fileIdAndRevision = null, $submissionParameterName = 'submissionId') {
		// TODO: Refine file access policies. Differentiate between
		// read and modify access using bitfield:
		// $mode & SUBMISSION_FILE_ACCESS_...

		parent::__construct($request);
		$this->_baseFileAccessPolicy = $this->buildFileAccessPolicy($request, $args, $roleAssignments, $mode, $fileIdAndRevision, $submissionParameterName);
	}

	/**
	 *
	 * @param PKPRequest $request
	 * @param array $args
	 * @param array $roleAssignments
	 * @param int bitfield $mode
	 * @param string $fileIdAndRevision
	 * @param string $submissionParameterName
	 */
	function buildFileAccessPolicy($request, $args, $roleAssignments, $mode, $fileIdAndRevision, $submissionParameterName) {
		// We need a submission matching the file in the request.
		import('lib.pkp.classes.security.authorization.internal.SubmissionRequiredPolicy');
		$this->addPolicy(new SubmissionRequiredPolicy($request, $args, $submissionParameterName));
		import('lib.pkp.classes.security.authorization.internal.SubmissionFileMatchesSubmissionPolicy');
		$this->addPolicy(new SubmissionFileMatchesSubmissionPolicy($request, $fileIdAndRevision));

		// Authors, managers and series editors potentially have
		// access to submission files. We'll have to define
		// differentiated policies for those roles in a policy set.
		$fileAccessPolicy = new PolicySet(COMBINING_PERMIT_OVERRIDES);


		//
		// Managerial role
		//
		if (isset($roleAssignments[ROLE_ID_MANAGER])) {
			// Managers can access all submission files as long as the manager has not
			// been assigned to a lesser role in the stage.
			$managerFileAccessPolicy = new PolicySet(COMBINING_DENY_OVERRIDES);
			$managerFileAccessPolicy->addPolicy(new RoleBasedHandlerOperationPolicy($request, ROLE_ID_MANAGER, $roleAssignments[ROLE_ID_MANAGER]));
			import('lib.pkp.classes.security.authorization.WorkflowStageAccessPolicy');
			$managerFileAccessPolicy->addPolicy(new WorkflowStageAccessPolicy($request, $args, $roleAssignments, 'submissionId', $request->getUserVar('stageId')));
			import('lib.pkp.classes.security.authorization.AssignedStageRoleHandlerOperationPolicy');
			$managerFileAccessPolicy->addPolicy(new AssignedStageRoleHandlerOperationPolicy($request, ROLE_ID_MANAGER, $roleAssignments[ROLE_ID_MANAGER], $request->getUserVar('stageId')));

			$fileAccessPolicy->addPolicy($managerFileAccessPolicy);
		}


		//
		// Author role
		//
		if (isset($roleAssignments[ROLE_ID_AUTHOR])) {
			// 1) Author role user groups can access whitelisted operations ...
			$authorFileAccessPolicy = new PolicySet(COMBINING_DENY_OVERRIDES);
			$authorFileAccessPolicy->addPolicy(new RoleBasedHandlerOperationPolicy($request, ROLE_ID_AUTHOR, $roleAssignments[ROLE_ID_AUTHOR]));

			// 2) ...if they are assigned to the workflow stage as an author.  Note: This loads the application-specific policy class.
			import('lib.pkp.classes.security.authorization.WorkflowStageAccessPolicy');
			$authorFileAccessPolicy->addPolicy(new WorkflowStageAccessPolicy($request, $args, $roleAssignments, 'submissionId', $request->getUserVar('stageId')));
			import('lib.pkp.classes.security.authorization.AssignedStageRoleHandlerOperationPolicy');
			$authorFileAccessPolicy->addPolicy(new AssignedStageRoleHandlerOperationPolicy($request, ROLE_ID_AUTHOR, $roleAssignments[ROLE_ID_AUTHOR], $request->getUserVar('stageId')));

			// 3) ...and if they meet one of the following requirements:
			$authorFileAccessOptionsPolicy = new PolicySet(COMBINING_PERMIT_OVERRIDES);

			// 3a) If the file was uploaded by the current user, allow...
			import('lib.pkp.classes.security.authorization.internal.SubmissionFileUploaderAccessPolicy');
			$authorFileAccessOptionsPolicy->addPolicy(new SubmissionFileUploaderAccessPolicy($request, $fileIdAndRevision));

			// 3b) ...or if the file is a file in a review round with requested revision decision, allow...
			// Note: This loads the application-specific policy class
			import('lib.pkp.classes.security.authorization.internal.SubmissionFileRequestedRevisionRequiredPolicy');
			$authorFileAccessOptionsPolicy->addPolicy(new SubmissionFileRequestedRevisionRequiredPolicy($request, $fileIdAndRevision));

			// ...or if we don't want to modify the file...
			if (!($mode & SUBMISSION_FILE_ACCESS_MODIFY)) {
				import('lib.pkp.classes.submission.SubmissionFile'); // for SUBMISSION_FILE_...

				// 3c) ...the file is at submission stage...
				import('lib.pkp.classes.security.authorization.internal.SubmissionFileStageRequiredPolicy');
				$authorFileAccessOptionsPolicy->addPolicy(new SubmissionFileStageRequiredPolicy($request, $fileIdAndRevision, SUBMISSION_FILE_SUBMISSION));

				// 3d) ...or the file is a viewable reviewer response...
				$authorFileAccessOptionsPolicy->addPolicy(new SubmissionFileStageRequiredPolicy($request, $fileIdAndRevision, SUBMISSION_FILE_REVIEW_ATTACHMENT, true));

				// 3e) ...or if the file is part of a query assigned to the user...
				import('lib.pkp.classes.security.authorization.internal.SubmissionFileAssignedQueryAccessPolicy');
				$authorFileAccessOptionsPolicy->addPolicy(new SubmissionFileAssignedQueryAccessPolicy($request, $fileIdAndRevision));

				// 3f) ...or the file is at revision stage...
				$authorFileAccessOptionsPolicy->addPolicy(new SubmissionFileStageRequiredPolicy($request, $fileIdAndRevision, SUBMISSION_FILE_REVIEW_REVISION));

				// 3g) ...or the file is a copyedited file...
				$authorFileAccessOptionsPolicy->addPolicy(new SubmissionFileStageRequiredPolicy($request, $fileIdAndRevision, SUBMISSION_FILE_COPYEDIT));

				// 3h) ...or the file is a representation (galley/publication format)...
				$authorFileAccessOptionsPolicy->addPolicy(new SubmissionFileStageRequiredPolicy($request, $fileIdAndRevision, SUBMISSION_FILE_PROOF));
			}

			// Add the rules from 3)
			$authorFileAccessPolicy->addPolicy($authorFileAccessOptionsPolicy);

			$fileAccessPolicy->addPolicy($authorFileAccessPolicy);
		}


		//
		// Reviewer role
		//
		if (isset($roleAssignments[ROLE_ID_REVIEWER])) {
			// 1) Reviewers can access whitelisted operations ...
			$reviewerFileAccessPolicy = new PolicySet(COMBINING_DENY_OVERRIDES);
			$reviewerFileAccessPolicy->addPolicy(new RoleBasedHandlerOperationPolicy($request, ROLE_ID_REVIEWER, $roleAssignments[ROLE_ID_REVIEWER]));

			// 2) ...if they meet one of the following requirements:
			$reviewerFileAccessOptionsPolicy = new PolicySet(COMBINING_PERMIT_OVERRIDES);

			// 2a) If the file was uploaded by the current user, allow.
			import('lib.pkp.classes.security.authorization.internal.SubmissionFileUploaderAccessPolicy');
			$reviewerFileAccessOptionsPolicy->addPolicy(new SubmissionFileUploaderAccessPolicy($request, $fileIdAndRevision));

			// 2b) If the file is part of an assigned review, and we're not
			// trying to modify it, allow.
			import('lib.pkp.classes.security.authorization.internal.SubmissionFileAssignedReviewerAccessPolicy');
			if (!($mode & SUBMISSION_FILE_ACCESS_MODIFY)) {
				$reviewerFileAccessOptionsPolicy->addPolicy(new SubmissionFileAssignedReviewerAccessPolicy($request, $fileIdAndRevision));
			}

			// 2c) If the file is part of a query assigned to the user, allow.
			import('lib.pkp.classes.security.authorization.internal.SubmissionFileAssignedQueryAccessPolicy');
			$reviewerFileAccessOptionsPolicy->addPolicy(new SubmissionFileAssignedQueryAccessPolicy($request, $fileIdAndRevision));

			// Add the rules from 2)
			$reviewerFileAccessPolicy->addPolicy($reviewerFileAccessOptionsPolicy);

			// Add this policy set
			$fileAccessPolicy->addPolicy($reviewerFileAccessPolicy);
		}


		//
		// Assistant role.
		//
		if (isset($roleAssignments[ROLE_ID_ASSISTANT])) {
			// 1) Assistants can access whitelisted operations...
			$contextAssistantFileAccessPolicy = new PolicySet(COMBINING_DENY_OVERRIDES);
			$contextAssistantFileAccessPolicy->addPolicy(new RoleBasedHandlerOperationPolicy($request, ROLE_ID_ASSISTANT, $roleAssignments[ROLE_ID_ASSISTANT]));

			// 2) ... but only if they have been assigned to the submission workflow as an assistant.
			// Note: This loads the application-specific policy class
			import('lib.pkp.classes.security.authorization.WorkflowStageAccessPolicy');
			$contextAssistantFileAccessPolicy->addPolicy(new WorkflowStageAccessPolicy($request, $args, $roleAssignments, 'submissionId', $request->getUserVar('stageId')));
			import('lib.pkp.classes.security.authorization.AssignedStageRoleHandlerOperationPolicy');
			$contextAssistantFileAccessPolicy->addPolicy(new AssignedStageRoleHandlerOperationPolicy($request, ROLE_ID_ASSISTANT, $roleAssignments[ROLE_ID_ASSISTANT], $request->getUserVar('stageId')));

			// 3) ...and if they meet one of the following requirements:
			$contextAssistantFileAccessOptionsPolicy = new PolicySet(COMBINING_PERMIT_OVERRIDES);

			// 3a) ...the file not part of a query...
			import('lib.pkp.classes.security.authorization.internal.SubmissionFileNotQueryAccessPolicy');
			$contextAssistantFileAccessOptionsPolicy->addPolicy(new SubmissionFileNotQueryAccessPolicy($request, $fileIdAndRevision));

			// 3b) ...or the file is part of a query they are assigned to...
			import('lib.pkp.classes.security.authorization.internal.SubmissionFileAssignedQueryAccessPolicy');
			$contextAssistantFileAccessOptionsPolicy->addPolicy(new SubmissionFileAssignedQueryAccessPolicy($request, $fileIdAndRevision));

			// Add the rules from 3
			$contextAssistantFileAccessPolicy->addPolicy($contextAssistantFileAccessOptionsPolicy);

			$fileAccessPolicy->addPolicy($contextAssistantFileAccessPolicy);
		}

		//
		// Sub editor role
		//
		if (isset($roleAssignments[ROLE_ID_SUB_EDITOR])) {
			// 1) Sub editors can access all operations on submissions ...
			$subEditorFileAccessPolicy = new PolicySet(COMBINING_DENY_OVERRIDES);
			$subEditorFileAccessPolicy->addPolicy(new RoleBasedHandlerOperationPolicy($request, ROLE_ID_SUB_EDITOR, $roleAssignments[ROLE_ID_SUB_EDITOR]));

			// 2) ... but only if they have been assigned as a subeditor to the requested submission ...
			import('lib.pkp.classes.security.authorization.internal.UserAccessibleWorkflowStageRequiredPolicy');
			$subEditorFileAccessPolicy->addPolicy(new UserAccessibleWorkflowStageRequiredPolicy($request));
			import('lib.pkp.classes.security.authorization.AssignedStageRoleHandlerOperationPolicy');
			$subEditorFileAccessPolicy->addPolicy(new AssignedStageRoleHandlerOperationPolicy($request, ROLE_ID_SUB_EDITOR, $roleAssignments[ROLE_ID_SUB_EDITOR], $request->getUserVar('stageId')));

			// 3) ... and only if they are not also assigned as an author and this is not part of a blind review
			import('lib.pkp.classes.security.authorization.internal.SubmissionFileAuthorEditorPolicy');
			$subEditorFileAccessPolicy->addPolicy(new SubmissionFileAuthorEditorPolicy($request, $fileIdAndRevision));

			$fileAccessPolicy->addPolicy($subEditorFileAccessPolicy);
		}

		$this->addPolicy($fileAccessPolicy);
		return $fileAccessPolicy;
	}
}


