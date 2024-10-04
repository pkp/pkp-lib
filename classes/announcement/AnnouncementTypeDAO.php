<?php

/**
 * @file classes/announcement/AnnouncementTypeDAO.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class AnnouncementTypeDAO
 *
 * @ingroup announcement
 *
 * @see AnnouncementType
 *
 * @brief Operations for retrieving and modifying AnnouncementType objects.
 */

namespace PKP\announcement;

use APP\core\Application;
use Generator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class AnnouncementTypeDAO extends \PKP\db\DAO
{
    /**
     * Generate a new data object.
     */
    public function newDataObject(): AnnouncementType
    {
        return new AnnouncementType();
    }

    /**
     * Retrieve an announcement type by announcement type ID.
     */
    public function getById(int $typeId, ?int $contextId = Application::SITE_CONTEXT_ID_ALL): ?AnnouncementType
    {
        $announcement = DB::table('announcement_types')
            ->where('type_id', '=', $typeId)
            ->when($contextId !== Application::SITE_CONTEXT_ID_ALL, fn (Builder $q) => $q->whereRaw('COALESCE(context_id, 0) = ?', [(int) $contextId]))
            ->first();
        return $announcement ? $this->_fromRow((array) $announcement) : null;
    }

    /**
     * Get the locale field names.
     */
    public function getLocaleFieldNames(): array
    {
        return ['name'];
    }

    /**
     * Internal function to return an AnnouncementType object from a row.
     */
    public function _fromRow(array $row): AnnouncementType
    {
        $announcementType = $this->newDataObject();
        $announcementType->setId($row['type_id']);
        $announcementType->setData('contextId', $row['context_id']);
        $this->getDataObjectSettings('announcement_type_settings', 'type_id', $row['type_id'], $announcementType);

        return $announcementType;
    }

    /**
     * Update the localized settings for this object
     */
    public function updateLocaleFields(AnnouncementType $announcementType): void
    {
        $this->updateDataObjectSettings(
            'announcement_type_settings',
            $announcementType,
            ['type_id' => (int) $announcementType->getId()]
        );
    }

    /**
     * Insert a new AnnouncementType.
     */
    public function insertObject(AnnouncementType $announcementType): int
    {
        $id = DB::table('announcement_types')->insertGetId(['context_id' => $announcementType->getContextId()], 'type_id');
        $announcementType->setId($id);
        $this->updateLocaleFields($announcementType);
        return $id;
    }

    /**
     * Update an existing announcement type.
     */
    public function updateObject(AnnouncementType $announcementType): bool
    {
        $affected = DB::table('announcement_types')
            ->where('type_id', '=', $announcementType->getId())
            ->update(['context_id' => $announcementType->getContextId()]);

        $this->updateLocaleFields($announcementType);
        return $affected > 0;
    }

    /**
     * Delete an announcement type. Note that all announcements with this type are also deleted.
     */
    public function deleteObject(AnnouncementType $announcementType): void
    {
        $this->deleteById($announcementType->getId());
    }

    /**
     * Delete an announcement type by announcement type ID. Note that all announcements with this type ID are also deleted.
     */
    public function deleteById(int $typeId): int
    {
        Announcement::withTypeIds([$typeId])->delete();

        return DB::table('announcement_types')
            ->where('type_id', '=', $typeId)
            ->delete();
    }

    /**
     * Delete announcement types by context ID.
     */
    public function deleteByContextId(?int $contextId): void
    {
        foreach ($this->getByContextId($contextId) as $type) {
            $this->deleteObject($type);
        }
    }

    /**
     * Retrieve an array of announcement types matching a particular context ID.
     *
     * @return Generator<int,AnnouncementType> Matching AnnouncementTypes
     */
    public function getByContextId(?int $contextId): Generator
    {
        $rows = DB::table('announcement_types')
            ->whereRaw('COALESCE(context_id, 0) = ?', [(int) $contextId])
            ->orderBy('type_id')
            ->get();
        foreach ($rows as $row) {
            yield $row->type_id => $this->_fromRow((array) $row);
        }
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\announcement\AnnouncementTypeDAO', '\AnnouncementTypeDAO');
}
