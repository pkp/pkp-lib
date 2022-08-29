<?php

/**
* @file classes/sushi/SushiException.php
*
* Copyright (c) 2022 Simon Fraser University
* Copyright (c) 2022 John Willinsky
* Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
*
* @class SushiException
* @ingroup sushi
*
* @brief Class that defines an COUNTER R5 exception
*
*/

namespace PKP\sushi;

use Exception;

class SushiException extends Exception
{
    /** The Severity element is deprecated and will be removed in the next COUNTER major release */
    protected $severity;

    /** Additional information that further describes the Exception */
    protected $data;

    protected $httpStatusCode;

    public function __construct(string $message, int $code, string $severity, string $data, int $httpStatusCode)
    {
        parent::__construct($message, $code);
        $this->severity = $severity;
        $this->data = $data;
        $this->httpStatusCode = $httpStatusCode;
    }

    /**
     * Get data prepared for the JSON response
     */
    public function getResponseData(): array
    {
        return [
            'Code' => $this->code,
            'Severity' => $this->severity,
            'Message' => $this->message,
            'Data' => $this->data
        ];
    }

    /**
     * Get the HTTP status code
     */
    public function getHttpStatusCode(): int
    {
        return $this->httpStatusCode;
    }
}
