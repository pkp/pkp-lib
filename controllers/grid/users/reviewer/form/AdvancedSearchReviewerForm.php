<?php

/**
 * @file controllers/grid/users/reviewer/form/AdvancedSearchReviewerForm.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class AdvancedSearchReviewerForm
 *
 * @ingroup controllers_grid_users_reviewer_form
 *
 * @brief Form for an advanced search and for adding a reviewer to a submission.
 */

namespace PKP\controllers\grid\users\reviewer\form;

use APP\core\Application;
use APP\facades\Repo;
use APP\submission\Submission;
use APP\template\TemplateManager;
use Illuminate\Support\Facades\Mail;
use PKP\controllers\grid\users\reviewer\PKPReviewerGridHandler;
use PKP\core\PKPApplication;
use PKP\db\DAORegistry;
use PKP\emailTemplate\EmailTemplate;
use PKP\facades\Locale;
use PKP\linkAction\LinkAction;
use PKP\linkAction\request\AjaxAction;
use PKP\mail\mailables\ReviewRequest;
use PKP\mail\mailables\ReviewRequestSubsequent;
use PKP\security\Role;
use PKP\stageAssignment\StageAssignment;
use PKP\submission\reviewAssignment\ReviewAssignment;
use PKP\submission\reviewRound\ReviewRound;
use PKP\submission\reviewRound\ReviewRoundDAO;

class AdvancedSearchReviewerForm extends ReviewerForm
{
    /**
     * Constructor.
     *
     * @param Submission $submission
     * @param ReviewRound $reviewRound
     */
    public function __construct($submission, $reviewRound)
    {
        parent::__construct($submission, $reviewRound);
        $this->setTemplate('controllers/grid/users/reviewer/form/advancedSearchReviewerForm.tpl');

        $this->addCheck(new \PKP\form\validation\FormValidator($this, 'reviewerId', 'required', 'editor.review.mustSelect'));
    }

    /**
     * Assign form data to user-submitted data.
     *
     * @see Form::readInputData()
     */
    public function readInputData()
    {
        parent::readInputData();

        $this->readUserVars(['reviewerId']);
    }

    /**
     * @copydoc Form::initData()
     */
    public function initData()
    {
        parent::initData();

        $request = Application::get()->getRequest();
        $context = $request->getContext();
        $mailable = $this->getMailable();

        $templates = Repo::emailTemplate()->getCollector($context->getId())
            ->filterByKeys([ReviewRequest::getEmailTemplateKey(), ReviewRequestSubsequent::getEmailTemplateKey()])
            ->getMany();

        $templates = Repo::emailTemplate()
            ->filterTemplatesByUserAccess($templates, $request->getUser(), $context->getId())
            ->mapWithKeys(function (EmailTemplate $item, int $key) use ($mailable) {
                return [$item->getData('key') => Mail::compileParams($item->getLocalizedData('body'), $mailable->viewData)];
            });

        $this->setData('personalMessage', '');
        $this->setData('reviewerMessages', $templates->toArray());
    }

    /**
     * @copydoc Form::fetch()
     *
     * @param null|mixed $template
     */
    public function fetch($request, $template = null, $display = false)
    {
        // Get submission context
        $submissionContext = app()->get('context')->get($this->getSubmission()->getData('contextId'));

        // Pass along the request vars
        $actionArgs = $request->getUserVars();
        $reviewRound = $this->getReviewRound();
        $actionArgs['reviewRoundId'] = $reviewRound->getId();
        $actionArgs['selectionType'] = PKPReviewerGridHandler::REVIEWER_SELECT_ADVANCED_SEARCH;
        // but change the selectionType for each action
        $advancedSearchAction = new LinkAction(
            'advancedSearch',
            new AjaxAction($request->url(null, null, 'reloadReviewerForm', null, $actionArgs)),
            __('manager.reviewerSearch.change'),
            'user_search'
        );

        $this->setReviewerFormAction($advancedSearchAction);

        // get reviewer IDs already assign to this submission and this round
        $reviewAssignments = Repo::reviewAssignment()->getCollector()
            ->filterBySubmissionIds([$this->getSubmissionId()])
            ->filterByReviewRoundIds([$this->getReviewRound()->getId()])
            ->getMany();

        $currentlyAssigned = [];
        if ($reviewAssignments->isNotEmpty()) {
            foreach ($reviewAssignments as $reviewAssignment) {
                $currentlyAssigned[] = (int) $reviewAssignment->getReviewerId();
            }
        }

        // Get user IDs already assigned to this submission, and admins and
        // managers who may have access to author identities and can not guarantee
        // anonymous reviews
        // Replaces StageAssignmentDAO::getBySubmissionAndStageId
        $warnOnAssignment = StageAssignment::withSubmissionIds([$this->getSubmissionId()])
            ->get()
            ->pluck('userId')
            ->all();

        // Get a list of users in the managerial and admin user groups
        // Managers are assigned only to contexts; site admins are assigned only to site.
        // Therefore filtering by both context IDs and role IDs will not cause problems.
        $userIds = Repo::user()->getCollector()
            ->filterByRoleIds([Role::ROLE_ID_MANAGER, Role::ROLE_ID_SITE_ADMIN])
            ->filterByContextIds([$submissionContext->getId(), PKPApplication::SITE_CONTEXT_ID])
            ->getIds()
            ->toArray();
        $warnOnAssignment = array_merge($warnOnAssignment, $userIds);
        $warnOnAssignment = array_values(array_unique(array_map('intval', $warnOnAssignment)));

        $locale = Locale::getLocale();
        $submissionAuthors = $this->getSubmission()->getCurrentPublication()->getData('authors');
        $authorAffiliations = [];
        $authors = [];
        foreach($submissionAuthors as $submissionAuthor) {
            $affiliation = $submissionAuthor->getLocalizedData('affiliation');
            $authorAffiliations[] = $affiliation;
            $authors[$submissionAuthor->getFullName(true, false, $locale)] = $affiliation;
        }

        // Get reviewers list
        $selectReviewerListPanel = new \PKP\components\listPanels\PKPSelectReviewerListPanel(
            'selectReviewer',
            __('editor.submission.findAndSelectReviewer'),
            [
                'apiUrl' => $request->getDispatcher()->url(
                    $request,
                    PKPApplication::ROUTE_API,
                    $submissionContext->getPath(),
                    'users/reviewers'
                ),
                'authorAffiliations' => $authorAffiliations,
                'currentlyAssigned' => $currentlyAssigned,
                'getParams' => [
                    'contextId' => $submissionContext->getId(),
                    'reviewStage' => $reviewRound->getStageId(),
                ],
                'selectorName' => 'reviewerId',
                'warnOnAssignment' => $warnOnAssignment,
            ]
        );

        // Get reviewers who completed a review in the last round
        $lastRoundReviewerIds = [];
        if ($this->getReviewRound()->getRound() > 1) {
            /** @var ReviewRoundDAO */
            $reviewRoundDao = DAORegistry::getDAO('ReviewRoundDAO');
            $previousRound = $this->getReviewRound()->getRound() - 1;
            $lastReviewRound = $reviewRoundDao->getReviewRound($this->getSubmissionId(), $this->getReviewRound()->getStageId(), $previousRound);

            if ($lastReviewRound) {
                $lastReviewAssignments = Repo::reviewAssignment()->getCollector()->filterByReviewerIds([$lastReviewRound->getId()])->getMany();
                foreach ($lastReviewAssignments as $reviewAssignment) {
                    if (in_array($reviewAssignment->getStatus(), [ReviewAssignment::REVIEW_ASSIGNMENT_STATUS_THANKED, ReviewAssignment::REVIEW_ASSIGNMENT_STATUS_COMPLETE])) {
                        $lastRoundReviewerIds[] = (int) $reviewAssignment->getReviewerId();
                    }
                }

                $lastRoundReviewers = Repo::user()->getCollector()
                    ->filterByContextIds([$submissionContext->getId()])
                    ->filterByRoleIds([Role::ROLE_ID_REVIEWER])
                    ->filterByUserIds($lastRoundReviewerIds)
                    ->includeReviewerData()
                    ->getMany();

                if (count($lastRoundReviewers)) {
                    $selectReviewerListPanel->set([
                        'lastRoundReviewers' => $lastRoundReviewers,
                    ]);
                }
            }
        }

        $templateMgr = TemplateManager::getManager($request);
        // Used to determine the right email template
        $templateMgr->assign('lastRoundReviewerIds', $lastRoundReviewerIds);

        $selectReviewerListPanel->set([
            'items' => $selectReviewerListPanel->getItems($request),
            'itemsMax' => $selectReviewerListPanel->getItemsMax(),
        ]);

        $templateMgr->assign('selectReviewerListData', [
            'authors' => $authors,
            'labels' => [
                'showAll' => __('showAll'),
                'showLess' => __('showLess'),
                'submissionAuthorList' => __('submission.author.list'),
                'authorsLabel' => __('submission.authors.label')
            ],
            'components' => [
                'selectReviewer' => $selectReviewerListPanel->getConfig(),
            ]
        ]);

        // Only add actions to forms where user can operate.
        if (array_intersect($this->getUserRoles(), [Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR])) {
            $actionArgs['selectionType'] = PKPReviewerGridHandler::REVIEWER_SELECT_CREATE;
            // but change the selectionType for each action
            $advancedSearchAction = new LinkAction(
                'selectCreate',
                new AjaxAction($request->url(null, null, 'reloadReviewerForm', null, $actionArgs)),
                __('editor.review.createReviewer'),
                'add_user'
            );

            $this->setReviewerFormAction($advancedSearchAction);
            $actionArgs['selectionType'] = PKPReviewerGridHandler::REVIEWER_SELECT_ENROLL_EXISTING;
            // but change the selectionType for each action
            $advancedSearchAction = new LinkAction(
                'enrolExisting',
                new AjaxAction($request->url(null, null, 'reloadReviewerForm', null, $actionArgs)),
                __('editor.review.enrollReviewer.short'),
                'enroll_user'
            );

            $this->setReviewerFormAction($advancedSearchAction);
        }

        return parent::fetch($request, $template, $display);
    }

    protected function getEmailTemplates(): array
    {
        $contextId = Application::get()->getRequest()->getContext()->getId();
        $subsequentTemplate = Repo::emailTemplate()->getByKey($contextId, ReviewRequestSubsequent::getEmailTemplateKey());

        $alternateTemplates = Repo::emailTemplate()->getCollector(Application::get()->getRequest()->getContext()->getId())
            ->alternateTo([ReviewRequestSubsequent::getEmailTemplateKey()])
            ->getMany();

        $templateKeys = parent::getEmailTemplates();
        $user = Application::get()->getRequest()->getUser();

        if(Repo::emailTemplate()->isTemplateAccessibleToUser($user, $subsequentTemplate, $contextId)) {
            $templateKeys[ReviewRequestSubsequent::getEmailTemplateKey()] = $subsequentTemplate->getLocalizedData('name');
        }

        foreach ($alternateTemplates as $alternateTemplate) {
            if (Repo::emailTemplate()->isTemplateAccessibleToUser($user, $subsequentTemplate, $contextId)) {
                $templateKeys[$alternateTemplate->getData('key')] = $alternateTemplate->getLocalizedData('name');
            }
        }

        return $templateKeys;
    }
}
