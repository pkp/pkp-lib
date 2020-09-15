<?php
/**
 * @defgroup lib_pkp_classes_user
 */

/**
 * @file lib/pkp/classes/user/report/SerializerInterface.inc.php
 *
 * Copyright (c) 2003-2020 Simon Fraser University
 * Copyright (c) 2003-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file LICENSE.
 *
 * @class SerializerInterface
 * @ingroup lib_pkp_classes_user
 *
 * @brief The interface specifies a contract to serialize a Report instance.
 */

namespace PKP\User\Report;

interface SerializerInterface {
	/**
	 * Serializes an Report object to the given output
	 * @param Report $report An user report instance
	 * @param resource $output A ready to write stream
	 */
	public function serialize(Report $report, $output): void;
}
