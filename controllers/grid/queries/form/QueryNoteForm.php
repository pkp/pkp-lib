<?php

/**
 * @file controllers/grid/queries/form/QueryNoteForm.php
 *
 * Copyright (c) 2014-2024 Simon Fraser University
 * Copyright (c) 2003-2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class QueryNoteForm
 *
 * @ingroup controllers_grid_queries_form
 *
 * @brief Form for adding/editing a new query note.
 */

namespace PKP\controllers\grid\queries\form;

use APP\core\Application;
use APP\facades\Repo;
use APP\template\TemplateManager;
use PKP\form\Form;
use PKP\form\validation\FormValidator;
use PKP\form\validation\FormValidatorCSRF;
use PKP\form\validation\FormValidatorPost;
use PKP\note\Note;
use PKP\query\Query;
use PKP\query\QueryParticipant;
use PKP\user\User;

class QueryNoteForm extends Form
{
    /** @var array Action arguments */
    public $_actionArgs;

    /** @var Query */
    public $_query;

    /** @var int Note ID */
    public $_noteId;

    /** @var bool Whether or not this is a new note */
    public $_isNew;

    /**
     * Constructor.
     *
     * @param array $actionArgs Action arguments
     * @param Query $query
     * @param User $user The current user ID
     * @param int $noteId The note ID to edit, or null for new.
     */
    public function __construct($actionArgs, $query, $user, $noteId = null)
    {
        parent::__construct('controllers/grid/queries/form/queryNoteForm.tpl');
        $this->_actionArgs = $actionArgs;
        $this->setQuery($query);

        if ($noteId === null) {
            // Create a new (placeholder) note.
            $note = new Note;
            $note->assocType = Application::ASSOC_TYPE_QUERY;
            $note->assocId = $query->id;
            $note->userId = $user->getId();
            $note->save();
            $this->_noteId = $note->id;
            $this->_isNew = true;
        } else {
            $this->_noteId = $noteId;
            $this->_isNew = false;
        }

        // Validation checks for this form
        $this->addCheck(new FormValidator($this, 'comment', 'required', 'submission.queries.messageRequired'));
        $this->addCheck(new FormValidatorPost($this));
        $this->addCheck(new FormValidatorCSRF($this));
    }

    //
    // Getters and Setters
    //
    /**
     * Get the query
     *
     * @return Query
     */
    public function getQuery()
    {
        return $this->_query;
    }

    /**
     * Set the query
     *
     * @param Query $query
     */
    public function setQuery($query)
    {
        $this->_query = $query;
    }

    /**
     * Assign form data to user-submitted data.
     *
     * @see Form::readInputData()
     */
    public function readInputData()
    {
        $this->readUserVars([
            'comment',
        ]);
    }

    /**
     * @copydoc Form::fetch
     *
     * @param null|mixed $template
     */
    public function fetch($request, $template = null, $display = false)
    {
        $templateMgr = TemplateManager::getManager($request);
        $templateMgr->assign([
            'actionArgs' => $this->_actionArgs,
            'noteId' => $this->_noteId,
            'csrfToken' => $request->getSession()->token(),
        ]);
        return parent::fetch($request, $template, $display);
    }

    /**
     * @copydoc Form::execute()
     *
     * @return Note The created note object.
     */
    public function execute(...$functionArgs)
    {
        $request = Application::get()->getRequest();
        $user = $request->getUser();

        $note = Note::find($this->_noteId);
        $note->userId = $request->getUser()->getId();
        $note->contents = $this->getData('comment');
        $note->save();

        // Check whether the query needs re-opening
        $query = $this->getQuery();
        if ($query->closed) {
            $headNote = Repo::note()->getHeadNote($query->id);
            if ($user->getId() != $headNote->userId) {
                // Re-open the query.
                $query->closed = false;
                $query->save();
            }
        }

        // Always include current user to query participants
        $participantIds = QueryParticipant::withQueryId($query->id)
            ->pluck('user_id')
            ->all();
        if (!in_array($user->getId(), $participantIds)) {
            QueryParticipant::create([
                'queryId' => $query->id,
                'userId' => $user->getId()
            ]);
        }

        parent::execute(...$functionArgs);

        return $note;
    }
}
