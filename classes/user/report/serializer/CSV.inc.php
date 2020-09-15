<?php
/**
 * @defgroup lib_pkp_classes_user
 */

/**
 * @file lib/pkp/classes/user/report/serializer/CSV.inc.php
 *
 * Copyright (c) 2003-2020 Simon Fraser University
 * Copyright (c) 2003-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file LICENSE.
 *
 * @class CSV
 * @ingroup lib_pkp_classes_user
 *
 * @brief Serializes the user report as a CSV.
 */

namespace PKP\User\Report\Serializer;
use PKP\User\Report\{Report, SerializerInterface};

class CSV implements SerializerInterface {
	/**
	 * @copydoc SerializerInterface::serialize()
	 */
	public function serialize(Report $report, $output): void
	{
		// Adds BOM (byte order mark) to enforce the UTF-8 format
		$data = "\xEF\xBB\xBF";
		fwrite($output, $data);

		// Outputs column headings
		fputcsv($output, array_map(
			function(?string $heading): ?string
			{
				return \PKPString::html2text($heading);
			},
			$report->getHeadings()
		));

		// Outputs each user
		foreach($report as $dataRow) {
			fputcsv($output, array_map(
				function (?string $data): ?string
				{
					return \PKPString::html2text($data);
				},
				$dataRow
			));
		}
	}
}