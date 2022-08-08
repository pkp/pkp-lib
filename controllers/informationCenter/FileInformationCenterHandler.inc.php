<?php

/**
 * @file controllers/informationCenter/FileInformationCenterHandler.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class FileInformationCenterHandler
 * @ingroup controllers_informationCenter
 *
 * @brief Handle requests to view the information center for a file.
 */

import('lib.pkp.controllers.informationCenter.InformationCenterHandler');

use APP\facades\Repo;
use APP\template\TemplateManager;
use PKP\core\ArrayItemIterator;
use PKP\core\JSONMessage;
use PKP\log\EventLogEntry;
use PKP\notification\PKPNotification;
use PKP\security\authorization\WorkflowStageAccessPolicy;
use PKP\security\Role;
use APP\notification\NotificationManager;

class FileInformationCenterHandler extends InformationCenterHandler
{
    /** @var object */
    public $submissionFile;

    /** @var int */
    public $_stageId;

    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();
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
        // Require stage access
        $this->addPolicy(new WorkflowStageAccessPolicy($request, $args, $roleAssignments, 'submissionId', (int) $request->getUserVar('stageId')));

        return parent::authorize($request, $args, $roleAssignments);
    }

    /**
     * @copydoc InformationCenterHandler::initialize
     */
    public function initialize($request)
    {
        parent::initialize($request);

        $this->_stageId = $this->getAuthorizedContextObject(ASSOC_TYPE_WORKFLOW_STAGE);
        $this->submissionFile = Repo::submissionFile()->get($request->getUserVar('submissionFileId'));

        // Ensure data integrity.
        if (!$this->_submission || !$this->submissionFile || $this->_submission->getId() != $this->submissionFile->getData('submissionId')) {
            throw new \Exception('Unknown or invalid submission or submission file!');
        };
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

        // Assign variables to the template manager and display
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

        import('lib.pkp.controllers.informationCenter.form.NewFileNoteForm');
        $notesForm = new NewFileNoteForm($this->submissionFile->getId());
        $notesForm->initData();

        $templateMgr = TemplateManager::getManager($request);
        $templateMgr->assign('notesList', $this->_listNotes($args, $request));
        $templateMgr->assign('pastNotesList', $this->_listPastNotes($args, $request));

        return new JSONMessage(true, $notesForm->fetch($request));
    }

    /**
     * Display the list of existing notes from prior files.
     *
     * @param array $args
     * @param PKPRequest $request
     *
     * @return JSONMessage JSON object
     */
    public function _listPastNotes($args, $request)
    {
        $this->setupTemplate($request);

        $templateMgr = TemplateManager::getManager($request);
        $noteDao = DAORegistry::getDAO('NoteDAO'); /** @var NoteDAO $noteDao */

        $notes = [];
        $sourceSubmissionFileId = $this->submissionFile->getData('sourceSubmissionFileId');
        
        if (!is_null($sourceSubmissionFileId)) {
            $notes = $noteDao->getByAssoc($this->_getAssocType(), $sourceSubmissionFileId);
        }
        
        $templateMgr->assign('notes', $notes);

        $user = $request->getUser();
        $templateMgr->assign([
            'currentUserId' => $user->getId(),
            'notesDeletable' => false,
        ]);

        return $templateMgr->fetch('controllers/informationCenter/notesList.tpl');
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

        import('lib.pkp.controllers.informationCenter.form.NewFileNoteForm');
        $notesForm = new NewFileNoteForm($this->submissionFile->getId());
        $notesForm->readInputData();

        if ($notesForm->validate()) {
            $notesForm->execute();

            // Save to event log
            $this->_logEvent($request, $this->submissionFile, EventLogEntry::SUBMISSION_LOG_NOTE_POSTED, 'PKP\log\SubmissionFileLog');

            $user = $request->getUser();
            $notificationManager = new NotificationManager();
            $notificationManager->createTrivialNotification($user->getId(), PKPNotification::NOTIFICATION_TYPE_SUCCESS, ['contents' => __('notification.addedNote')]);

            $jsonViewNotesResponse = $this->viewNotes($args, $request);
            $json = new JSONMessage(true);
            $json->setEvent('dataChanged');
            $json->setEvent('noteAdded', $jsonViewNotesResponse->_content);

            return $json;
        } else {
            // Return a JSON string indicating failure
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
            $dispatcher->url($request, PKPApplication::ROUTE_COMPONENT, null, 'grid.eventLog.SubmissionFileEventLogGridHandler', 'fetchGrid', null, $this->_getLinkParams())
        );
    }

    /**
     * Get an array representing link parameters that subclasses
     * need to have passed to their various handlers (i.e. submission ID
     * to the delete note handler).
     *
     * @return array
     */
    public function _getLinkParams()
    {
        return array_merge(
            parent::_getLinkParams(),
            [
                'submissionFileId' => $this->submissionFile->getId(),
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
        return $this->submissionFile->getId();
    }

    /**
     * Get the association type for this information center view
     *
     * @return int
     */
    public function _getAssocType()
    {
        return ASSOC_TYPE_SUBMISSION_FILE;
    }

    /**
     * Set up the template
     *
     * @param PKPRequest $request
     */
    public function setupTemplate($request)
    {
        $templateMgr = TemplateManager::getManager($request);

        // Get the latest history item to display in the header
        $submissionFileEventLogDao = DAORegistry::getDAO('SubmissionFileEventLogDAO'); /** @var SubmissionFileEventLogDAO $submissionFileEventLogDao */
        $fileEvents = $submissionFileEventLogDao->getBySubmissionFileId($this->submissionFile->getId());
        $lastEvent = $fileEvents->next();
        if (isset($lastEvent)) {
            $templateMgr->assign('lastEvent', $lastEvent);

            // Get the user who created the last event.
            $user = Repo::user()->get($lastEvent->getUserId(), true);
            $templateMgr->assign('lastEventUser', $user);
        }

        return parent::setupTemplate($request);
    }
}
