<?php
/**
 * @file controllers/modals/submission/ViewSubmissionMetadataHandler.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ViewSubmissionMetadataHandler
 * @ingroup controllers_modals_viewSubmissionMetadataHandler
 *
 * @brief Display submission metadata.
 */

use APP\handler\Handler;
use APP\template\TemplateManager;

use PKP\security\authorization\SubmissionAccessPolicy;
use PKP\security\Role;

class ViewSubmissionMetadataHandler extends handler
{
    /**
     * Constructor.
     */
    public function __construct()
    {
        parent::__construct();
        $this->addRoleAssignment([Role::ROLE_ID_REVIEWER], ['display']);
    }

    /**
     * @copydoc PKPHandler::authorize()
     */
    public function authorize($request, &$args, $roleAssignments)
    {
        $this->addPolicy(new SubmissionAccessPolicy($request, $args, $roleAssignments));
        return parent::authorize($request, $args, $roleAssignments);
    }

    /**
     * Display metadata
     */
    public function display($args, $request)
    {
        $submission = $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION);
        $reviewAssignment = $this->getAuthorizedContextObject(ASSOC_TYPE_REVIEW_ASSIGNMENT);
        $context = $request->getContext();
        $templateMgr = TemplateManager::getManager($request);
        $publication = $submission->getCurrentPublication();

        if ($reviewAssignment->getReviewMethod() != SUBMISSION_REVIEW_METHOD_DOUBLEANONYMOUS) { /* SUBMISSION_REVIEW_METHOD_ANONYMOUS or _OPEN */
            $userGroupDao = DAORegistry::getDAO('UserGroupDAO'); /** @var UserGroupDAO $userGroupDao */
            $userGroups = $userGroupDao->getByContextId($context->getId())->toArray();
            $templateMgr->assign('authors', $publication->getAuthorString($userGroups));
        }

        $templateMgr->assign('publication', $publication);

        if ($publication->getLocalizedData('keywords')) {
            $additionalMetadata[] = [__('common.keywords'), implode(', ', $publication->getLocalizedData('keywords'))];
        }
        if ($publication->getLocalizedData('subjects')) {
            $additionalMetadata[] = [__('common.subjects'), implode(', ', $publication->getLocalizedData('subjects'))];
        }
        if ($publication->getLocalizedData('disciplines')) {
            $additionalMetadata[] = [__('common.discipline'), implode(', ', $publication->getLocalizedData('disciplines'))];
        }
        if ($publication->getLocalizedData('agencies')) {
            $additionalMetadata[] = [__('submission.agencies'), implode(', ', $publication->getLocalizedData('agencies'))];
        }
        if ($publication->getLocalizedData('languages')) {
            $additionalMetadata[] = [__('common.languages'), implode(', ', $publication->getLocalizedData('languages'))];
        }

        $templateMgr->assign('additionalMetadata', $additionalMetadata);

        return $templateMgr->fetchJson('controllers/modals/submission/viewSubmissionMetadata.tpl');
    }
}
