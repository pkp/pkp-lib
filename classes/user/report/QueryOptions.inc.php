<?php
/**
 * @defgroup lib_pkp_classes_user
 */

/**
 * @file lib/pkp/classes/user/report/QueryOptions.inc.php
 *
 * Copyright (c) 2003-2020 Simon Fraser University
 * Copyright (c) 2003-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file LICENSE.
 *
 * @class QueryOptions
 * @ingroup lib_pkp_classes_user
 *
 * @brief Options to customize the query.
 */

namespace PKP\User\Report;

class QueryOptions {
	/** @var string[] Current list of fields in the group by section */
	public $groupBy = [];

	/** @var string[] Current list of fields in the select */
	public $columns = [];
}
