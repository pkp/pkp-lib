<?php

/**
 * @file classes/validation/traits/HasMultilingualRule.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class HasMultilingualRule
 *
 * @brief Halper trait to perform multilingual validation in a form request
 *
 */

namespace PKP\validation\traits;

use PKP\validation\MultilingualInput;

trait HasMultilingualRule
{
    /**
     * Define the multilingual fields
     */
    abstract public function multilingualInputs(): array;

    /**
     * Define the primary locale
     */
    abstract public function primaryLocale(): ?string;

    /**
     * Define the optional allowed locales that only acceptable
     */
    abstract public function allowedLocales(): array;

    /**
     * @see \Illuminate\Foundation\Http\FormRequest::validated()
     */
    public function validated($key = null, $default = null)
    {
        $validated = parent::validated($key, $default);

        return collect($validated)->map(
            fn (mixed $value, string $input): mixed => $this->isMultilingual($input) && is_array($value)
                ? array_filter($value)
                : $value
        )->toArray();
    }

    /**
     * @see \Illuminate\Foundation\Http\FormRequest::validationRules()
     */
    protected function validationRules()
    {
        $rules = method_exists($this, 'rules') ? $this->container->call([$this, 'rules']) : [];

        if (empty($rules)) {
            $rules;
        }

        $primaryLocale = $this->primaryLocale();
        $allowedLocale = $this->allowedLocales();

        foreach($this->multilingualInputs() as $input) {
            if (!isset($rules[$input])) {
                continue;
            }

            // TODO : check if this string check necessary ?
            if (is_string($rules[$input])) {
                $rules[$input] = array_map('trim', explode('|', $rules[$input]));
            }

            if (!in_array('array', $rules[$input])) {
                array_push($rules[$input], 'array');
            }

            array_push(
                $rules[$input],
                new MultilingualInput($primaryLocale, $allowedLocale)
            );
        }

        return $rules;
    }

    protected function isMultilingual(string $inputName): bool
    {
        return in_array($inputName, $this->multilingualInputs());
    }
}
