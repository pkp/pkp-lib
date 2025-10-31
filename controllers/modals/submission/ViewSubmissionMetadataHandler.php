<?php
/**
 * @file controllers/modals/submission/ViewSubmissionMetadataHandler.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ViewSubmissionMetadataHandler
 *
 * @ingroup controllers_modals_viewSubmissionMetadataHandler
 *
 * @brief Display submission metadata.
 */

namespace PKP\controllers\modals\submission;

use APP\core\Application;
use APP\facades\Repo;
use APP\handler\Handler;
use APP\template\TemplateManager;
use PKP\security\authorization\SubmissionAccessPolicy;
use PKP\security\Role;
use PKP\submission\reviewAssignment\ReviewAssignment;

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
        $submission = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION);
        $reviewAssignment = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_REVIEW_ASSIGNMENT);
        $context = $request->getContext();
        $templateMgr = TemplateManager::getManager($request);
        $publication = $submission->getCurrentPublication();
        
        if ($reviewAssignment->getReviewMethod() != ReviewAssignment::SUBMISSION_REVIEW_METHOD_DOUBLEANONYMOUS) { /* ReviewAssignment::SUBMISSION_REVIEW_METHOD_ANONYMOUS or _OPEN */
            $templateMgr->assign('authors', $publication->getAuthorString());

            if ($publication->getLocalizedData('dataAvailability')) {
                $templateMgr->assign('dataAvailability', $publication->getLocalizedData('dataAvailability'));
            }
        }

        $templateMgr->assign('publication', $publication);

        $additionalMetadata = [];
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

        $templateMgr->assign('additionalMetadata', $additionalMetadata);

        return $templateMgr->fetchJson('controllers/modals/submission/viewSubmissionMetadata.tpl');
    }
}
