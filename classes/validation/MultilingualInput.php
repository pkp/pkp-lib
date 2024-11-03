<?php

/**
 * @file classes/validation/MultilingualInput.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class MultilingualInput
 *
 * @brief Validation rule to validate multilingual requirements
 *
 */

namespace PKP\validation;

use Closure;
use Illuminate\Validation\Validator;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\ValidatorAwareRule;

class MultilingualInput implements ValidationRule, ValidatorAwareRule
{
    /**
     * Required primary locale
     */
    protected ?string $primaryLocale = null;

    /**
     * Optional allowed locales that only acceptable
     */
    protected array $allowedLocales = [];

    /**
     * Validation pass status
     */
    protected bool $passed;
    
    /**
     * The validator instance.
     *
     * @var \Illuminate\Validation\Validator
     */
    protected Validator $validator;

    /**
     * Create a new instance
     */
    public function __construct(?string $primaryLocale = null, array $allowedLocale = [])
    {
        $this->primaryLocale = $primaryLocale;
        $this->allowedLocales = $allowedLocale;
    }
 
    /**
     * Set the current validator.
     */
    public function setValidator(Validator $validator): static
    {
        $this->validator = $validator;
 
        return $this;
    }

    /**
     * Run the validation rule.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @param  \Closure(string): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     * @return void
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $this->passed = true;
        
        // TODO : Should an array_filter needed to be applied ?
        // This will cause disallowed locales with null value to get bypassed even though those
        // locale with null value have no impact.
        $givenLocales = array_keys(array_filter($value));

        if ($this->primaryLocale && !empty($this->primaryLocale) && !in_array($this->primaryLocale, $givenLocales)) {
            $this->passed = false;
            $this->validator->errors()->add(
                "{$attribute}.{$this->primaryLocale}",
                __('validator.required')
            );
        }

        $disallowedLocales = array_diff(
            $givenLocales,
            array_filter(array_merge([$this->primaryLocale], $this->allowedLocales))
        );

        foreach($disallowedLocales as $locale) {
            $this->passed = false;
            $this->validator->errors()->add("{$attribute}.{$locale}", __('validator.locale'));
        }
    }

    /**
     * Has validation passed for this rule
     */
    public function passed(): bool
    {
        return $this->passed;
    }
}
