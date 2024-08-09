<?php

/**
 * @file classes/invitation/core/traits/ShouldValidate.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ShouldValidate
 *
 * @brief Trait that have all the invitations that define validation rules on their payload properties
 */

namespace PKP\invitation\core\traits;

trait ShouldValidate
{
    private array $errors = [];

    abstract public function validate(): bool;

    public function isValid(): bool
    {
        return empty($this->errors);
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    protected function addError(string $field, string $error): void
    {
        $this->errors[$field] = $error;
    }

    protected function clearErrors(): void
    {
        $this->errors = [];
    }
}
