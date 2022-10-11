<?php
/**
 * @file classes/core/APIResponse.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class APIResponse
 * @ingroup core
 *
 * @brief Extends the Response class in the Slim microframework.
 */

namespace PKP\core;

use Slim\Http\Response;

class APIResponse extends Response
{
    public const RESPONSE_CSV = 'text/csv';

    /**
     * CSV Response
     *
     * @param integer $maxRows The total amount of rows, that is provided within the onw X-Total-Count header field
     */
    public function withCSV(array $rows, array $columns, int $maxRows): self
    {
        $fp = fopen('php://output', 'wt');
        //Add BOM (byte order mark) to fix UTF-8 in Excel
        fprintf($fp, chr(0xEF) . chr(0xBB) . chr(0xBF));
        fputcsv($fp, ['']);
        fputcsv($fp, $columns);
        foreach ($rows as $row) {
            fputcsv($fp, $row);
        }
        $csvData = stream_get_contents($fp);
        fclose($fp);
        $this->getBody()->rewind();
        $this->getBody()->write($csvData);
        $this->withStatus(200);
        return $this->withHeader('X-Total-Count', $maxRows)->withHeader('Content-Type', self::RESPONSE_CSV);
    }

    /**
     * Response with an error message
     *
     * @param string $msg The message translation key
     * @param string $params Optional parameters to pass to the translation
     *
     * @return APIResponse
     */
    public function withJsonError($msg, $params = null)
    {
        return $this->withJson(
            [
                'error' => $msg,
                'errorMessage' => __($msg, $params ?? []),
            ]
        );
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\core\APIResponse', '\APIResponse');
}
