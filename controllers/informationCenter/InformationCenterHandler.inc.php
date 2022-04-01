<?php

/**
 * @file controllers/informationCenter/InformationCenterHandler.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class InformationCenterHandler
 * @ingroup controllers_informationCenter
 *
 * @brief Parent class for file/submission information center handlers.
 */

use APP\handler\Handler;
use APP\template\TemplateManager;

use PKP\core\JSONMessage;
use PKP\log\EventLogEntry;
use PKP\log\SubmissionFileLog;
use PKP\notification\PKPNotification;
use PKP\security\authorization\SubmissionAccessPolicy;
use PKP\security\Role;

abstract class InformationCenterHandler extends Handler
{
    /** @var Submission */
    public $_submission;

    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();
        $this->addRoleAssignment(
            [Role::ROLE_ID_SUB_EDITOR, Role::ROLE_ID_MANAGER],
            [
                'viewInformationCenter',
                'viewHistory',
                'viewNotes', 'listNotes', 'saveNote', 'deleteNote',
            ]
        );
    }


    //
    // Implement template methods from PKPHandler.
    //
    /**
     * @copydoc PKPHandler::authorize()
     */
    public function authorize($request, &$args, $roleAssignments)
    {
        // Require a submission
        $this->addPolicy(new SubmissionAccessPolicy($request, $args, $roleAssignments, 'submissionId'));
        return parent::authorize($request, $args, $roleAssignments);
    }

    /**
     * Fetch and store away objects
     *
     * @param PKPRequest $request
     */
    public function initialize($request)
    {
        parent::initialize($request);

        // Fetch the submission and file to display information about
        $this->_submission = $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION);
    }


    //
    // Public operations
    //
    /**
     * Display the main information center modal.
     *
     * @param array $args
     * @param PKPRequest $request
     *
     * @return JSONMessage JSON object
     */
    public function viewInformationCenter($args, $request)
    {
        $this->setupTemplate($request);
        $templateMgr = TemplateManager::getManager($request);
        return $templateMgr->fetchJson('controllers/informationCenter/informationCenter.tpl');
    }

    /**
     * View a list of notes posted on the item.
     * Subclasses must implement.
     */
    abstract public function viewNotes($args, $request);

    /**
     * Save a note.
     * Subclasses must implement.
     *
     * @param array $args
     * @param PKPRequest $request
     */
    abstract public function saveNote($args, $request);

    /**
     * Delete a note.
     *
     * @param array $args
     * @param PKPRequest $request
     *
     * @return JSONMessage JSON object
     */
    public function deleteNote($args, $request)
    {
        $this->setupTemplate($request);

        $noteId = (int) $request->getUserVar('noteId');
        $noteDao = DAORegistry::getDAO('NoteDAO'); /** @var NoteDAO $noteDao */
        $note = $noteDao->getById($noteId);

        if (!$request->checkCSRF() || !$note || $note->getAssocType() != $this->_getAssocType() || $note->getAssocId() != $this->_getAssocId()) {
            fatalError('Invalid note!');
        }
        $noteDao->deleteById($noteId);

        $user = $request->getUser();
        NotificationManager::createTrivialNotification($user->getId(), PKPNotification::NOTIFICATION_TYPE_SUCCESS, ['contents' => __('notification.removedNote')]);

        $json = new JSONMessage(true);
        $jsonViewNotesResponse = $this->viewNotes($args, $request);
        $json->setEvent('dataChanged');
        $json->setEvent('noteDeleted', $jsonViewNotesResponse->_content);

        return $json;
    }

    /**
     * Display the list of existing notes.
     *
     * @param array $args
     * @param PKPRequest $request
     *
     * @return JSONMessage JSON object
     */
    public function _listNotes($args, $request)
    {
        $this->setupTemplate($request);

        $templateMgr = TemplateManager::getManager($request);
        $noteDao = DAORegistry::getDAO('NoteDAO'); /** @var NoteDAO $noteDao */
        $templateMgr->assign('notes', $noteDao->getByAssoc($this->_getAssocType(), $this->_getAssocId()));

        $user = $request->getUser();
        $templateMgr->assign('currentUserId', $user->getId());
        $templateMgr->assign('notesDeletable', true);

        $templateMgr->assign('notesListId', 'notesList');

        return $templateMgr->fetch('controllers/informationCenter/notesList.tpl');
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
        return [
            'submissionId' => $this->_submission->getId(),
        ];
    }

    /**
     * Log an event for this file or submission
     *
     * @param PKPRequest $request
     * @param Submission|SubmissionFile $object
     * @param int $eventType SUBMISSION_LOG_...
     * @param SubmissionLog|SubmissionFileLog $logClass
     */
    public function _logEvent($request, $object, $eventType, $logClass)
    {
        // Get the log event message
        switch ($eventType) {
            case EventLogEntry::SUBMISSION_LOG_NOTE_POSTED:
                $logMessage = 'informationCenter.history.notePosted';
                break;
            case EventLogEntry::SUBMISSION_LOG_MESSAGE_SENT:
                $logMessage = 'informationCenter.history.messageSent';
                break;
            default:
                assert(false);
        }
        $logClass::logEvent($request, $object, $eventType, $logMessage);
    }

    public function setupTemplate($request)
    {
        $linkParams = $this->_getLinkParams();
        $templateMgr = TemplateManager::getManager($request);

        // Preselect tab from keywords 'notes', 'notify', 'history'
        switch ($request->getUserVar('tab')) {
            case 'history':
                $templateMgr->assign('selectedTabIndex', 2);
                break;
            case 'notify':
                $userId = (int) $request->getUserVar('userId');
                if ($userId) {
                    $linkParams['userId'] = $userId; // user validated in Listbuilder.
                }
                $templateMgr->assign('selectedTabIndex', 1);
                break;
            // notes is default
            default:
                $templateMgr->assign('selectedTabIndex', 0);
                break;
        }

        $templateMgr->assign('linkParams', $linkParams);
        parent::setupTemplate($request);
    }

    /**
     * Get the association ID for this information center view
     *
     * @return int
     */
    abstract public function _getAssocId();

    /**
     * Get the association type for this information center view
     *
     * @return int
     */
    abstract public function _getAssocType();
}
