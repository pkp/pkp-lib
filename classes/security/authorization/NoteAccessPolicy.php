<?php
/**
 * @file classes/security/authorization/NoteAccessPolicy.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class NoteAccessPolicy
 * @ingroup security_authorization
 *
 * @brief Class to control access to a note.
 *
 * NB: This policy expects previously authorized submission, query and
 * accessibile workflow stages in the authorization context.
 */

namespace PKP\security\authorization;

use PKP\db\DAORegistry;

class NoteAccessPolicy extends AuthorizationPolicy
{
    public const NOTE_ACCESS_READ = 1;
    public const NOTE_ACCESS_WRITE = 2;

    /** @var Request */
    private $_request;

    /** @var int */
    private $_noteId;

    /** @var int */
    private $_accessMode;

    /**
     * Constructor
     *
     * @param PKPRequest $request
     * @param int $noteId
     * @param int $accessMode NOTE_ACCESS_...
     */
    public function __construct($request, $noteId, $accessMode)
    {
        parent::__construct('user.authorization.unauthorizedNote');
        $this->_request = $request;
        $this->_noteId = $noteId;
        $this->_accessMode = $accessMode;
    }

    //
    // Implement template methods from AuthorizationPolicy
    //
    /**
     * @see AuthorizationPolicy::effect()
     */
    public function effect()
    {
        if (!$this->_noteId) {
            return AuthorizationPolicy::AUTHORIZATION_DENY;
        }

        $query = $this->getAuthorizedContextObject(ASSOC_TYPE_QUERY);
        $submission = $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION);
        $assignedStages = $this->getAuthorizedContextObject(ASSOC_TYPE_ACCESSIBLE_WORKFLOW_STAGES);

        if (!$query || !$submission || empty($assignedStages)) {
            return AuthorizationPolicy::AUTHORIZATION_DENY;
        }

        $noteDao = DAORegistry::getDAO('NoteDAO'); /** @var NoteDAO $noteDao */
        $note = $noteDao->getById($this->_noteId);

        if (!$note instanceof \PKP\note\Note) {
            return AuthorizationPolicy::AUTHORIZATION_DENY;
        }

        // Note, query, submission and assigned stages must match
        if ($note->getAssocId() != $query->getId()
                || $note->getAssocType() != ASSOC_TYPE_QUERY
                || $query->getAssocId() != $submission->getId()
                || $query->getAssocType() != ASSOC_TYPE_SUBMISSION
                || !array_key_exists($query->getStageId(), $assignedStages)
                || empty($assignedStages[$query->getStageId()])) {
            return AuthorizationPolicy::AUTHORIZATION_DENY;
        }

        // Notes can only be edited by their original creators
        if ($this->_accessMode === self::NOTE_ACCESS_WRITE
                && $note->getUserId() != $this->_request->getUser()->getId()) {
            return AuthorizationPolicy::AUTHORIZATION_DENY;
        }

        $this->addAuthorizedContextObject(ASSOC_TYPE_NOTE, $note);

        return AuthorizationPolicy::AUTHORIZATION_PERMIT;
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\security\authorization\NoteAccessPolicy', '\NoteAccessPolicy');
    define('NOTE_ACCESS_READ', \NoteAccessPolicy::NOTE_ACCESS_READ);
    define('NOTE_ACCESS_WRITE', \NoteAccessPolicy::NOTE_ACCESS_WRITE);
}
