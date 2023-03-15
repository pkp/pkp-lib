<?php

/**
 * @file classes/section/DAO.php
 *
 * Copyright (c) 2014-2023 Simon Fraser University
 * Copyright (c) 2003-2023 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class DAO
 * @ingroup section
 *
 * @see Section
 *
 * @brief Operations for retrieving and modifying Section objects.
 */

namespace APP\section;

use PKP\services\PKPSchemaService;

class DAO extends \PKP\section\DAO
{
    /** @copydoc EntityDAO::$schema */
    public $schema = PKPSchemaService::SCHEMA_SECTION;

    /** @copydoc EntityDAO::$table */
    public $table = 'sections';

    /** @copydoc EntityDAO::$settingsTable */
    public $settingsTable = 'section_settings';

    /** @copydoc EntityDAO::$primaryKeyColumn */
    public $primaryKeyColumn = 'section_id';

    /** @copydoc EntityDAO::$primaryTableColumns */
    public $primaryTableColumns = [
        'id' => 'section_id',
        'contextId' => 'server_id',
        'reviewFormId' => 'review_form_id',
        'sequence' => 'seq',
        'editorRestricted' => 'editor_restricted',
        'metaIndexed' => 'meta_indexed',
        'metaReviewed' => 'meta_reviewed',
        'abstractsNotRequired' => 'abstracts_not_required',
        'hideTitle' => 'hide_title',
        'hideAuthor' => 'hide_author',
        'isInactive' => 'is_inactive',
        'wordCount' => 'abstract_word_count'
    ];

    /**
     * Get the parent object ID column name
     */
    public function getParentColumn(): string
    {
        return 'server_id';
    }
}
