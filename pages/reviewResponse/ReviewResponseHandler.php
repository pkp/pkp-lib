<?php

namespace PKP\pages\reviewResponse;

use APP\facades\Repo;
use APP\handler\Handler;
use APP\template\TemplateManager;
use PKP\components\RequestAuthorReviewResponsePage;
use PKP\core\PKPRequest;
use PKP\db\DAORegistry;
use PKP\security\authorization\internal\ReviewRoundRequiredPolicy;
use PKP\security\authorization\ReviewStageAccessPolicy;
use PKP\security\authorization\SubmissionAccessPolicy;
use PKP\security\Role;

class ReviewResponseHandler extends Handler
{
    /** @copydoc PKPHandler::_isBackendPage */
    public $_isBackendPage = true;

    public function __construct()
    {
        $this->addRoleAssignment(
            [Role::ROLE_ID_MANAGER, Role::ROLE_ID_SITE_ADMIN, Role::ROLE_ID_SUB_EDITOR],
            'requestAuthorResponse'
        );

        parent::__construct();

    }

    public function authorize($request, &$args, $roleAssignments)
    {
        $this->addPolicy(new SubmissionAccessPolicy($request, $args, $roleAssignments, 'submissionId'));
        $this->addPolicy(new ReviewStageAccessPolicy($request, $args, $roleAssignments, 'submissionId', 3));

        $this->addPolicy(new ReviewRoundRequiredPolicy($request, $args, 'reviewRoundId', ['requestAuthorResponse']));
        return parent::authorize($request, $args, $roleAssignments);
    }


    public function requestAuthorResponse(array $args, PKPRequest $request)
    {
        $context = $request->getContext();
        $reviewRoundId = (int)$request->getUserVar('reviewRoundId');
        $authorId = (int)$request->getUserVar('authorId');
        $stageId = (int)$request->getUserVar('stageId');

        /** @var \PKP\submission\reviewRound\ReviewRoundDAO $reviewRoundDao */
        $reviewRoundDao = DAORegistry::getDAO('ReviewRoundDAO');
        $reviewRound = $reviewRoundDao->getById($reviewRoundId);

        if (!$reviewRound) {
            throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException();
        }


        /**
         * Ensure the review round belongs to the context within the request is made
         * */
        $relatedPublication = Repo::publication()->get($reviewRound->getPublicationId());
        $submission = Repo::submission()->get($relatedPublication->getData('submissionId'));
        $context = $request->getContext();
        $submissionContextId = $submission->getData('contextId');

        if ($context->getId() != $submissionContextId) {
            throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException();
        }

        $requestAuthorReviewResponsePage = new RequestAuthorReviewResponsePage(
            reviewRound: $reviewRound,
            submission: $submission,
            stageId: $stageId,
            context: $context,
            locales: $context->getSupportedLocales(),
        );

        $this->setupTemplate($request);
        $templateMgr = TemplateManager::getManager($request);
        $templateMgr->setState([
            'pageInitConfig' => $requestAuthorReviewResponsePage->getConfig()
        ]);

        $templateMgr->assign([
            'breadcrumbs' => $requestAuthorReviewResponsePage->getBreadcrumb($submission, $context, $request),
            'pageWidth' => TemplateManager::PAGE_WIDTH_FULL,
        ]);

        $templateMgr->display('reviewResponse/requestAuthorResponse.tpl');
    }


}
