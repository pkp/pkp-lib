<?php

/**
 * @file classes/invitation/core/traits/ShouldValidate.php
 *
 * Copyright (c) 2023 Simon Fraser University
 * Copyright (c) 2023 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ShouldValidate
 *
 * @brief Interface for all Invitation API Handlers
 */

namespace PKP\invitation\core\traits;

trait ShouldValidate {
    private array $errors = [];

    abstract public function validate(): bool;

    public function isValid(): bool {
        return empty($this->errors);
    }

    public function getErrors(): array 
    {
        return $this->errors;
    }

    protected function addError(string $error): void {
        $this->errors[] = $error;
    }

    protected function clearErrors(): void {
        $this->errors = [];
    }
}