<?php
/**
 * @file classes/submissionFile/Repository.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class submission
 *
 * @brief A repository to find and manage submissions.
 */

namespace APP\submissionFile;

use APP\core\Request;

use PKP\services\PKPSchemaService;
use PKP\submissionFile\Repository as BaseRepository;

class Repository extends BaseRepository
{
    /** @var DAO $dao */
    public $dao;

    public function __construct(
        DAO $dao,
        Request $request,
        PKPSchemaService $schemaService
    ) {
        parent::__construct($dao, $request, $schemaService);

        $this->dao = $dao;
    }
}
