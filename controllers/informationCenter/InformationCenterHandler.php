<?php

/**
 * @file controllers/informationCenter/InformationCenterHandler.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class InformationCenterHandler
 *
 * @ingroup controllers_informationCenter
 *
 * @brief Parent class for file/submission information center handlers.
 */

namespace PKP\controllers\informationCenter;

use APP\core\Application;
use APP\facades\Repo;
use APP\handler\Handler;
use APP\notification\NotificationManager;
use APP\submission\Submission;
use APP\template\TemplateManager;
use PKP\core\Core;
use PKP\core\JSONMessage;
use PKP\core\PKPRequest;
use PKP\db\DAORegistry;
use PKP\log\event\EventLogEntry;
use PKP\note\Note;
use PKP\notification\Notification;
use PKP\security\authorization\SubmissionAccessPolicy;
use PKP\security\Role;
use PKP\security\Validation;
use PKP\submissionFile\SubmissionFile;

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
            [Role::ROLE_ID_SUB_EDITOR, Role::ROLE_ID_MANAGER, Role::ROLE_ID_SITE_ADMIN],
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
    public function initialize($request, $args = null)
    {
        parent::initialize($request, $args);

        // Fetch the submission and file to display information about
        $this->_submission = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION);
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
        $note = Note::find($noteId);

        if (!$request->checkCSRF() || $note?->assocType != $this->_getAssocType() || $note?->assocId != $this->_getAssocId()) {
            throw new \Exception('Invalid note!');
        }

        $note->delete();

        $user = $request->getUser();
        $notificationManager = new NotificationManager();
        $notificationManager->createTrivialNotification($user->getId(), Notification::NOTIFICATION_TYPE_SUCCESS, ['contents' => __('notification.removedNote')]);

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
     * @return string
     */
    public function _listNotes($args, $request)
    {
        $this->setupTemplate($request);

        $templateMgr = TemplateManager::getManager($request);
        $notes = Note::withAssoc($this->_getAssocType(), $this->_getAssocId())->get();
        $templateMgr->assign('notes', $notes);

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
     */
    public function _logEvent(
        PKPRequest $request,
        Submission|SubmissionFile $object,
        int $eventType, //SUBMISSION_LOG_... const
        int $assocType // PKPApplication::ASSOC_TYPE_SUBMISSION_FILE || PKPApplication::ASSOC_TYPE_SUBMISSION
    ) {
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

        $eventLog = Repo::eventLog()->newDataObject([
            'assocType' => $assocType,
            'assocId' => $object->getId(),
            'eventType' => $eventType,
            'userId' => Validation::loggedInAs() ?? $request->getUser()->getId(),
            'message' => $logMessage,
            'isTranslated' => false,
            'dateLogged' => Core::getCurrentDate()
        ]);
        Repo::eventLog()->add($eventLog);
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
