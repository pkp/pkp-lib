<?php

/**
 * @file controllers/grid/settings/reviewForms/ReviewFormGridHandler.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ReviewFormGridHandler
 *
 * @ingroup controllers_grid_settings_reviewForms
 *
 * @brief Handle review form grid requests.
 */

namespace PKP\controllers\grid\settings\reviewForms;

use APP\core\Application;
use APP\facades\Repo;
use APP\notification\NotificationManager;
use APP\template\TemplateManager;
use PKP\controllers\grid\feature\OrderGridItemsFeature;
use PKP\controllers\grid\GridColumn;
use PKP\controllers\grid\GridHandler;
use PKP\controllers\grid\settings\reviewForms\form\PreviewReviewForm;
use PKP\controllers\grid\settings\reviewForms\form\ReviewFormForm;
use PKP\core\JSONMessage;
use PKP\core\PKPApplication;
use PKP\core\PKPRequest;
use PKP\db\DAORegistry;
use PKP\linkAction\LinkAction;
use PKP\linkAction\request\AjaxModal;
use PKP\reviewForm\ReviewFormDAO;
use PKP\reviewForm\ReviewFormElementDAO;
use PKP\security\authorization\PolicySet;
use PKP\security\authorization\RoleBasedHandlerOperationPolicy;
use PKP\security\Role;

class ReviewFormGridHandler extends GridHandler
{
    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();
        $this->addRoleAssignment(
            [Role::ROLE_ID_MANAGER, Role::ROLE_ID_SITE_ADMIN],
            ['fetchGrid', 'fetchRow', 'createReviewForm', 'editReviewForm', 'updateReviewForm',
                'reviewFormBasics', 'reviewFormElements', 'copyReviewForm',
                'reviewFormPreview', 'activateReviewForm', 'deactivateReviewForm', 'deleteReviewForm',
                'saveSequence']
        );
    }


    //
    // Implement template methods from PKPHandler.
    //
    /**
     * @copydoc GridHandler::initialize()
     *
     * @param null|mixed $args
     */
    public function initialize($request, $args = null)
    {
        parent::initialize($request, $args);

        // Basic grid configuration.
        $this->setTitle('manager.reviewForms');

        // Grid actions.
        $router = $request->getRouter();

        $this->addAction(
            new LinkAction(
                'createReviewForm',
                new AjaxModal(
                    $router->url($request, null, null, 'createReviewForm', null, null),
                    __('manager.reviewForms.create'),
                    null,
                    true
                ),
                __('manager.reviewForms.create'),
                'add_item'
            )
        );

        //
        // Grid columns.
        //
        $reviewFormGridCellProvider = new ReviewFormGridCellProvider();

        // Review form name.
        $this->addColumn(
            new GridColumn(
                'name',
                'manager.reviewForms.title',
                null,
                null,
                $reviewFormGridCellProvider
            )
        );

        // Review Form 'in review'
        $this->addColumn(
            new GridColumn(
                'inReview',
                'manager.reviewForms.inReview',
                null,
                null,
                $reviewFormGridCellProvider
            )
        );

        // Review Form 'completed'.
        $this->addColumn(
            new GridColumn(
                'completed',
                'manager.reviewForms.completed',
                null,
                null,
                $reviewFormGridCellProvider
            )
        );

        // Review form 'activate/deactivate'
        // if ($element->getActive()) {
        $this->addColumn(
            new GridColumn(
                'active',
                'common.active',
                null,
                'controllers/grid/common/cell/selectStatusCell.tpl',
                $reviewFormGridCellProvider
            )
        );
    }

    /**
     * @see PKPHandler::authorize()
     */
    public function authorize($request, &$args, $roleAssignments)
    {
        $rolePolicy = new PolicySet(PolicySet::COMBINING_PERMIT_OVERRIDES);

        foreach ($roleAssignments as $role => $operations) {
            $rolePolicy->addPolicy(new RoleBasedHandlerOperationPolicy($request, $role, $operations));
        }
        $this->addPolicy($rolePolicy);

        return parent::authorize($request, $args, $roleAssignments);
    }

    //
    // Implement methods from GridHandler.
    //
    /**
     * @see GridHandler::getRowInstance()
     *
     * @return ReviewFormGridRow
     */
    protected function getRowInstance()
    {
        return new ReviewFormGridRow();
    }

    /**
     * @copydoc GridHandler::loadData()
     *
     * @param null|mixed $filter
     */
    protected function loadData($request, $filter = null)
    {
        // Get all review forms.
        $reviewFormDao = DAORegistry::getDAO('ReviewFormDAO'); /** @var ReviewFormDAO $reviewFormDao */
        $context = $request->getContext();
        $reviewForms = $reviewFormDao->getByAssocId(Application::getContextAssocType(), $context->getId());

        return $reviewForms->toAssociativeArray();
    }

    /**
     * @copydoc GridHandler::setDataElementSequence()
     */
    public function setDataElementSequence($request, $rowId, $gridDataElement, $newSequence)
    {
        $reviewFormDao = DAORegistry::getDAO('ReviewFormDAO'); /** @var ReviewFormDAO $reviewFormDao */
        $gridDataElement->setSequence($newSequence);
        $reviewFormDao->updateObject($gridDataElement);
    }

    /**
     * @see lib/pkp/classes/controllers/grid/GridHandler::getDataElementSequence()
     */
    public function getDataElementSequence($reviewForm)
    {
        return $reviewForm->getSequence();
    }

    /**
     * @see GridHandler::addFeatures()
     */
    public function initFeatures($request, $args)
    {
        return [new OrderGridItemsFeature()];
    }


    //
    // Public grid actions.
    //
    /**
     * Preview a review form.
     *
     * @param array $args
     * @param PKPRequest $request
     *
     * @return JSONMessage JSON object
     */
    public function reviewFormPreview($args, $request)
    {
        // Identify the review form ID.
        $reviewFormId = (int) $request->getUserVar('reviewFormId');

        // Identify the context id.
        $context = $request->getContext();

        // Get review form object
        $reviewFormDao = DAORegistry::getDAO('ReviewFormDAO'); /** @var ReviewFormDAO $reviewFormDao */
        $reviewForm = $reviewFormDao->getById($reviewFormId, Application::getContextAssocType(), $context->getId());

        $previewReviewForm = new PreviewReviewForm($reviewFormId);
        $previewReviewForm->initData();
        return new JSONMessage(true, $previewReviewForm->fetch($request));
    }

    /**
     * Add a new review form.
     *
     * @param array $args
     * @param PKPRequest $request
     *
     * @return JSONMessage JSON object
     */
    public function createReviewForm($args, $request)
    {
        // Form handling.
        $reviewFormForm = new ReviewFormForm(null);
        $reviewFormForm->initData();
        return new JSONMessage(true, $reviewFormForm->fetch($request));
    }

    /**
     * Edit an existing review form.
     *
     * @param array $args
     * @param PKPRequest $request
     *
     * @return JSONMessage JSON object
     */
    public function editReviewForm($args, $request)
    {
        $reviewFormDao = DAORegistry::getDAO('ReviewFormDAO'); /** @var ReviewFormDAO $reviewFormDao */
        $context = $request->getContext();
        $reviewForm = $reviewFormDao->getById(
            $request->getUserVar('rowId'),
            Application::getContextAssocType(),
            $context->getId()
        );

        // Display 'editReviewForm' tabs
        $templateMgr = TemplateManager::getManager($request);
        $templateMgr->assign([
            'preview' => $request->getUserVar('preview'),
            'reviewFormId' => $reviewForm->getId(),
            'canEdit' => $reviewForm->getIncompleteCount() == 0 && $reviewForm->getCompleteCount() == 0,
        ]);
        return new JSONMessage(true, $templateMgr->fetch('controllers/grid/settings/reviewForms/editReviewForm.tpl'));
    }

    /**
     * Edit an existing review form's basics (title, description)
     *
     * @param array $args
     * @param PKPRequest $request
     *
     * @return JSONMessage JSON object
     */
    public function reviewFormBasics($args, $request)
    {
        // Identify the review form Id
        $reviewFormId = (int) $request->getUserVar('reviewFormId');

        // Form handling
        $reviewFormForm = new ReviewFormForm($reviewFormId);
        $reviewFormForm->initData();
        return new JSONMessage(true, $reviewFormForm->fetch($request));
    }


    /**
     * Display a list of the review form elements within a review form.
     *
     * @param array $args
     * @param PKPRequest $request
     *
     * @return JSONMessage JSON object
     */
    public function reviewFormElements($args, $request)
    {
        $templateMgr = TemplateManager::getManager($request);
        $dispatcher = $request->getDispatcher();
        return $templateMgr->fetchAjax(
            'reviewFormElementsGridContainer',
            $dispatcher->url(
                $request,
                PKPApplication::ROUTE_COMPONENT,
                null,
                'grid.settings.reviewForms.ReviewFormElementsGridHandler',
                'fetchGrid',
                null,
                ['reviewFormId' => (int) $request->getUserVar('reviewFormId')]
            )
        );
    }

    /**
     * Update an existing review form.
     *
     * @param array $args
     * @param PKPRequest $request
     *
     * @return JSONMessage JSON message
     */
    public function updateReviewForm($args, $request)
    {
        // Identify the review form Id.
        $reviewFormId = (int) $request->getUserVar('reviewFormId');

        // Identify the context id.
        $context = $request->getContext();

        // Get review form object
        $reviewFormDao = DAORegistry::getDAO('ReviewFormDAO'); /** @var ReviewFormDAO $reviewFormDao */
        $reviewForm = $reviewFormDao->getById($reviewFormId, Application::getContextAssocType(), $context->getId());

        // Form handling.
        $reviewFormForm = new ReviewFormForm(!isset($reviewFormId) || empty($reviewFormId) ? null : $reviewFormId);
        $reviewFormForm->readInputData();

        if ($reviewFormForm->validate()) {
            $reviewFormForm->execute();

            // Create the notification.
            $notificationMgr = new NotificationManager();
            $user = $request->getUser();
            $notificationMgr->createTrivialNotification($user->getId());

            return \PKP\db\DAO::getDataChangedEvent($reviewFormId);
        }

        return new JSONMessage(false);
    }

    /**
     * Copy a review form.
     *
     * @param array $args
     * @param PKPRequest $request
     *
     * @return JSONMessage JSON object
     */
    public function copyReviewForm($args, $request)
    {
        // Identify the current review form
        $reviewFormId = (int) $request->getUserVar('rowId');

        // Identify the context id.
        $context = $request->getContext();

        // Get review form object
        $reviewFormDao = DAORegistry::getDAO('ReviewFormDAO'); /** @var ReviewFormDAO $reviewFormDao */
        $reviewForm = $reviewFormDao->getById($reviewFormId, Application::getContextAssocType(), $context->getId());

        if ($request->checkCSRF() && isset($reviewForm)) {
            $reviewForm->setActive(0);
            $reviewForm->setSequence(REALLY_BIG_NUMBER);
            $newReviewFormId = $reviewFormDao->insertObject($reviewForm);
            $reviewFormDao->resequenceReviewForms(Application::getContextAssocType(), $context->getId());

            $reviewFormElementDao = DAORegistry::getDAO('ReviewFormElementDAO'); /** @var ReviewFormElementDAO $reviewFormElementDao */
            $reviewFormElements = $reviewFormElementDao->getByReviewFormId($reviewFormId);
            while ($reviewFormElement = $reviewFormElements->next()) {
                $reviewFormElement->setReviewFormId($newReviewFormId);
                $reviewFormElement->setSequence(REALLY_BIG_NUMBER);
                $reviewFormElementDao->insertObject($reviewFormElement);
                $reviewFormElementDao->resequenceReviewFormElements($newReviewFormId);
            }

            // Create the notification.
            $notificationMgr = new NotificationManager();
            $user = $request->getUser();
            $notificationMgr->createTrivialNotification($user->getId());

            return \PKP\db\DAO::getDataChangedEvent($newReviewFormId);
        }

        return new JSONMessage(false);
    }

    /**
     * Activate a review form.
     *
     * @param array $args
     * @param PKPRequest $request
     *
     * @return JSONMessage JSON object
     */
    public function activateReviewForm($args, $request)
    {
        // Identify the current review form
        $reviewFormId = (int) $request->getUserVar('reviewFormKey');

        // Identify the context id.
        $context = $request->getContext();

        // Get review form object
        $reviewFormDao = DAORegistry::getDAO('ReviewFormDAO'); /** @var ReviewFormDAO $reviewFormDao */
        $reviewForm = $reviewFormDao->getById($reviewFormId, Application::getContextAssocType(), $context->getId());

        if ($request->checkCSRF() && isset($reviewForm) && !$reviewForm->getActive()) {
            $reviewForm->setActive(1);
            $reviewFormDao->updateObject($reviewForm);

            // Create the notification.
            $notificationMgr = new NotificationManager();
            $user = $request->getUser();
            $notificationMgr->createTrivialNotification($user->getId());

            return \PKP\db\DAO::getDataChangedEvent($reviewFormId);
        }

        return new JSONMessage(false);
    }


    /**
     * Deactivate a review form.
     *
     * @param array $args
     * @param PKPRequest $request
     *
     * @return JSONMessage JSON object
     */
    public function deactivateReviewForm($args, $request)
    {
        // Identify the current review form
        $reviewFormId = (int) $request->getUserVar('reviewFormKey');

        // Identify the context id.
        $context = $request->getContext();

        // Get review form object
        $reviewFormDao = DAORegistry::getDAO('ReviewFormDAO'); /** @var ReviewFormDAO $reviewFormDao */
        $reviewForm = $reviewFormDao->getById($reviewFormId, Application::getContextAssocType(), $context->getId());

        if ($request->checkCSRF() && isset($reviewForm) && $reviewForm->getActive()) {
            $reviewForm->setActive(0);
            $reviewFormDao->updateObject($reviewForm);

            // Create the notification.
            $notificationMgr = new NotificationManager();
            $user = $request->getUser();
            $notificationMgr->createTrivialNotification($user->getId());

            return \PKP\db\DAO::getDataChangedEvent($reviewFormId);
        }

        return new JSONMessage(false);
    }

    /**
     * Delete a review form.
     *
     * @param array $args
     * @param PKPRequest $request
     *
     * @return JSONMessage JSON object
     */
    public function deleteReviewForm($args, $request)
    {
        // Identify the current review form
        $reviewFormId = (int) $request->getUserVar('rowId');

        // Identify the context id.
        $context = $request->getContext();

        // Get review form object
        $reviewFormDao = DAORegistry::getDAO('ReviewFormDAO'); /** @var ReviewFormDAO $reviewFormDao */
        $reviewForm = $reviewFormDao->getById($reviewFormId, Application::getContextAssocType(), $context->getId());

        if ($request->checkCSRF() && isset($reviewForm) && $reviewForm->getCompleteCount() == 0 && $reviewForm->getIncompleteCount() == 0) {
            $reviewAssignments = Repo::reviewAssignment()->getCollector()->filterByReviewFormIds([$reviewFormId])->getMany();

            foreach ($reviewAssignments as $reviewAssignment) {
                Repo::reviewAssignment()->edit($reviewAssignment, ['reviewFormId' => null]);
            }

            $reviewFormDao->deleteById($reviewFormId);

            // Create the notification.
            $notificationMgr = new NotificationManager();
            $user = $request->getUser();
            $notificationMgr->createTrivialNotification($user->getId());

            return \PKP\db\DAO::getDataChangedEvent($reviewFormId);
        }

        return new JSONMessage(false);
    }
}
