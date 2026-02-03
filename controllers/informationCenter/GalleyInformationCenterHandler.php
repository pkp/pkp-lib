<?php

/**
 * @file controllers/informationCenter/GalleyInformationCenterHandler.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class GalleyInformationCenterHandler
 *
 * @brief Handle requests to view the information center for a galley.
 */

namespace PKP\controllers\informationCenter;

use APP\core\Application;
use APP\facades\Repo;
use APP\notification\NotificationManager;
use APP\template\TemplateManager;
use PKP\controllers\informationCenter\InformationCenterHandler;
use PKP\controllers\informationCenter\form\NewGalleyNoteForm;
use PKP\core\Core;
use PKP\core\JSONMessage;
use PKP\core\PKPApplication;
use PKP\core\PKPRequest;
use PKP\galley\Galley;
use PKP\log\event\EventLogEntry;
use PKP\notification\Notification;
use PKP\security\authorization\internal\RepresentationRequiredPolicy;
use PKP\security\authorization\PublicationAccessPolicy;
use PKP\security\authorization\WorkflowStageAccessPolicy;
use PKP\security\Role;
use PKP\security\Validation;

class GalleyInformationCenterHandler extends InformationCenterHandler
{
    /** @var Galley The galley */
    public $galley;

    /** @var int The stage ID */
    public $_stageId;

    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();
        // Add ROLE_ID_ASSISTANT same as FileInformationCenterHandler
        $this->addRoleAssignment(
            [Role::ROLE_ID_ASSISTANT],
            [
                'viewInformationCenter',
                'viewHistory',
                'viewNotes', 'listNotes', 'saveNote', 'deleteNote',
            ]
        );
    }

    /**
     * @copydoc PKPHandler::authorize()
     */
    public function authorize($request, &$args, $roleAssignments)
    {
        $this->addPolicy(new WorkflowStageAccessPolicy(
            $request,
            $args,
            $roleAssignments,
            'submissionId',
            (int) $request->getUserVar('stageId') // FIXME: should only allow for WORKFLOW_STAGE_ID_PRODUCTION ?
        ));

        $this->addPolicy(new PublicationAccessPolicy($request, $args, $roleAssignments));
        $this->addPolicy(new RepresentationRequiredPolicy($request, $args));

        return parent::authorize($request, $args, $roleAssignments);
    }

    /**
     * @copydoc InformationCenterHandler::initialize
     *
     * @param null|mixed $args
     */
    public function initialize($request, $args = null)
    {
        parent::initialize($request, $args);

        $this->_stageId = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_WORKFLOW_STAGE);
        $this->galley = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_REPRESENTATION);
    }

    /**
     * Display the main information center modal.
     *
     * @param array $args
     * @param PKPRequest $request
     */
    public function viewInformationCenter($args, $request)
    {
        $this->setupTemplate($request);

        $templateMgr = TemplateManager::getManager($request);
        $templateMgr->assign('removeHistoryTab', (int) $request->getUserVar('removeHistoryTab'));

        return parent::viewInformationCenter($args, $request);
    }

    /**
     * Display the notes tab.
     *
     * @param array $args
     * @param PKPRequest $request
     *
     * @return JSONMessage JSON object
     */
    public function viewNotes($args, $request)
    {
        $this->setupTemplate($request);

        $notesForm = new NewGalleyNoteForm($this->galley->getId());
        $notesForm->initData();

        $templateMgr = TemplateManager::getManager($request);
        $templateMgr->assign('notesList', $this->_listNotes($args, $request));

        return new JSONMessage(true, $notesForm->fetch($request));
    }

    /**
     * Save a note.
     *
     * @param array $args
     * @param PKPRequest $request
     *
     * @return JSONMessage JSON object
     */
    public function saveNote($args, $request)
    {
        $this->setupTemplate($request);

        $notesForm = new NewGalleyNoteForm($this->galley->getId());
        $notesForm->readInputData();

        if ($notesForm->validate()) {
            $notesForm->execute();

            $eventLog = Repo::eventLog()->newDataObject([
                'assocType' => PKPApplication::ASSOC_TYPE_REPRESENTATION,
                'assocId' => $this->galley->getId(),
                'eventType' => EventLogEntry::SUBMISSION_LOG_NOTE_POSTED,
                'userId' => Validation::loggedInAs() ?? $request->getUser()->getId(),
                'message' => 'informationCenter.history.notePosted',
                'isTranslated' => false,
                'dateLogged' => Core::getCurrentDate()
            ]);
            Repo::eventLog()->add($eventLog);

            $user = $request->getUser();
            $notificationManager = new NotificationManager();
            $notificationManager->createTrivialNotification(
                $user->getId(),
                Notification::NOTIFICATION_TYPE_SUCCESS,
                ['contents' => __('notification.addedNote')]
            );

            $jsonViewNotesResponse = $this->viewNotes($args, $request);
            $json = new JSONMessage(true);
            $json->setEvent('dataChanged');
            $json->setEvent('noteAdded', $jsonViewNotesResponse->_content);

            return $json;
        } else {
            return new JSONMessage(false);
        }
    }

    /**
     * Fetch the contents of the event log.
     *
     * @param array $args
     * @param PKPRequest $request
     *
     * @return JSONMessage JSON object
     */
    public function viewHistory($args, $request)
    {
        $this->setupTemplate($request);
        $templateMgr = TemplateManager::getManager($request);
        $dispatcher = $request->getDispatcher();

        return $templateMgr->fetchAjax(
            'eventLogGrid',
            $dispatcher->url(
                $request,
                PKPApplication::ROUTE_COMPONENT,
                null,
                'grid.eventLog.GalleyFileEventLogGridHandler',
                'fetchGrid',
                null,
                $this->_getLinkParams()
            )
        );
    }

    /**
     * @copydoc PKP\controllers\informationCenter\InformationCenterHandler::_getLinkParams()
     *
     * @return array
     */
    public function _getLinkParams()
    {
        return array_merge(
            parent::_getLinkParams(),
            [
                'representationId' => $this->galley->getId(),
                'publicationId' => $this->galley->getData('publicationId'),
                'stageId' => $this->_stageId,
            ]
        );
    }

    /**
     * Get the association ID for this information center view
     *
     * @return int
     */
    public function _getAssocId()
    {
        return $this->galley->getId();
    }

    /**
     * Get the association type for this information center view
     *
     * @return int
     */
    public function _getAssocType()
    {
        return Application::ASSOC_TYPE_REPRESENTATION;
    }

    /**
     * Set up the template
     *
     * @param PKPRequest $request
     */
    public function setupTemplate($request)
    {
        $templateMgr = TemplateManager::getManager($request);

        // Get last event for this galley (from associated files)
        $galleyFiles = Repo::submissionFile()
            ->getCollector()
            ->filterByAssoc(PKPApplication::ASSOC_TYPE_REPRESENTATION, [$this->galley->getId()])
            ->getMany();

        $fileIds = $galleyFiles->map(fn ($file) => $file->getId())->toArray();

        $lastEvent = null;
        if (!empty($fileIds)) {
            $lastEvent = Repo::eventLog()->getCollector()
                ->filterByAssoc(PKPApplication::ASSOC_TYPE_SUBMISSION_FILE, $fileIds)
                ->getMany()
                ->first();
        }

        if ($lastEvent) {
            $templateMgr->assign('lastEvent', $lastEvent);
            $userId = $lastEvent->getUserId();
            $user = $userId ? Repo::user()->get($userId, true) : null;
            $templateMgr->assign('lastEventUser', $user);
        }

        return parent::setupTemplate($request);
    }
}
