<?php
/**
 * @defgroup lib_pkp_classes_user
 */

/**
 * @file lib/pkp/classes/user/report/QueryListenerInterface.inc.php
 *
 * Copyright (c) 2003-2020 Simon Fraser University
 * Copyright (c) 2003-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file LICENSE.
 *
 * @class QueryListenerInterface
 * @ingroup lib_pkp_classes_user
 *
 * @brief Options to customize the query.
 */

namespace PKP\User\Report;

interface QueryListenerInterface {
	/**
	 * The method will be called right before executing the getQuery method
	 * @param \Illuminate\Database\Query\Builder $query The current query
	 * @param QueryOptions $options Object exposing internals of the query for customization purposes
	 */
	public function onQuery(\Illuminate\Database\Query\Builder $query, QueryOptions $options): void;
}
