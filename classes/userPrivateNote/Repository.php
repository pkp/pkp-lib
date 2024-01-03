<?php
/**
 * @file classes/userPrivateNote/Repository.php
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2000-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class \PKP\userPrivateNote\Repository
 *
 * @brief A repository to find and manage UserPrivateNote.
 */

namespace PKP\userPrivateNote;

use APP\core\Request;
use APP\facades\Repo;
use Exception;
use PKP\plugins\Hook;
use PKP\services\PKPSchemaService;

class Repository
{
    /** @var DAO */
    public DAO $dao;

    /** @var string $schemaMap The name of the class to map this entity to its schema */
    public string $schemaMap = maps\Schema::class;

    /** @var Request */
    protected Request $request;

    /** @var PKPSchemaService<UserPrivateNote> */
    protected PKPSchemaService $schemaService;

    public function __construct(DAO $dao, Request $request, PKPSchemaService $schemaService)
    {
        $this->dao = $dao;
        $this->request = $request;
        $this->schemaService = $schemaService;
    }

    /** @copydoc DAO::newDataObject() */
    public function newDataObject(array $params = []): UserPrivateNote
    {
        $object = $this->dao->newDataObject();
        if (!empty($params)) {
            $object->setAllData($params);
        }
        return $object;
    }

    /** @copydoc DAO::get() */
    public function get(int $id, int $contextId = null): ?UserPrivateNote
    {
        return $this->dao->get($id, $contextId);
    }

    /** @copydoc DAO::exists() */
    public function exists(int $id, int $contextId = null): bool
    {
        return $this->dao->exists($id, $contextId);
    }

    /** @copydoc DAO::getCollector() */
    public function getCollector(): Collector
    {
        return app(Collector::class);
    }

    /**
     * Get an instance of the map class for mapping
     * user private notes to their schema
     */
    public function getSchemaMap(): maps\Schema
    {
        return app('maps')->withExtensions($this->schemaMap);
    }

    /**
     * @throws Exception
     */
    public function add(UserPrivateNote $userPrivateNote): int
    {
        $userPrivateNoteId = $this->dao->insert($userPrivateNote);
        $userPrivateNote = Repo::userPrivateNote()->get($userPrivateNoteId);

        Hook::call('UserPrivateNote::add', [$userPrivateNote]);

        return $userPrivateNote->getId();
    }

    public function edit(UserPrivateNote $userPrivateNote, array $params): void
    {
        $newUserPrivateNote = Repo::userPrivateNote()->newDataObject(array_merge($userPrivateNote->_data, $params));

        Hook::call('UserPrivateNote::edit', [$newUserPrivateNote, $userPrivateNote, $params]);

        $this->dao->update($newUserPrivateNote);

        Repo::userPrivateNote()->get($newUserPrivateNote->getId());
    }

    public function delete(UserPrivateNote $userPrivateNote): void
    {
        Hook::call('UserPrivateNote::delete::before', [$userPrivateNote]);

        $this->dao->delete($userPrivateNote);

        Hook::call('UserPrivateNote::delete', [$userPrivateNote]);
    }

    /**
     * Get the user private note for the specified context.
     *
     * This returns the user private note, as the "user ID/context ID" key should be unique.
     */
    public function getUserPrivateNote(int $userId, int $contextId): ?UserPrivateNote
    {
        return Repo::userPrivateNote()
            ->getCollector()
            ->filterByUserIds([$userId])
            ->filterByContextIds([$contextId])
            ->limit(1)
            ->getMany()
            ->first();
    }
}
