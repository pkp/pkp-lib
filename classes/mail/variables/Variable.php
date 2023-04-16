<?php

/**
 * @file classes/mail/variables/Variable.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Variable
 *
 * @ingroup mail_variables
 *
 * @brief A base class for email template variables
 */

namespace PKP\mail\variables;

use Exception;
use Illuminate\Support\Arr;
use PKP\context\Context;
use PKP\mail\Mailable;

abstract class Variable
{
    protected Mailable $mailable;

    public function __construct(Mailable $mailable)
    {
        $this->mailable = $mailable;
    }

    /**
     * Retrieve mailable context from associated variables, see pkp/pkp-lib#8204
     */
    protected function getContext(): Context
    {
        $contextEmailVariable = Arr::first($this->mailable->getVariables(), function (Variable $variable) {
            return $variable instanceof ContextEmailVariable;
        });

        if (!$contextEmailVariable) {
            throw new Exception(static::class . ' is unable to generate email variables without providing the context to the Mailable');
        }

        return $contextEmailVariable->getContextFromVariable();
    }

    /**
     * Get descriptions of the variables provided by this class
     *
     * @return string[]
     */
    abstract public static function descriptions(): array;

    /**
     * Get the value of variables supported by this class
     *
     * @return string[]
     */
    abstract public function values(string $locale): array;
}
