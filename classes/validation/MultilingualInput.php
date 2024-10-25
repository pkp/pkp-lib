<?php

namespace PKP\validation;

use Closure;
use Illuminate\Validation\Validator;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\ValidatorAwareRule;

class MultilingualInput implements ValidationRule, ValidatorAwareRule
{
    protected ?string $primaryLocale = null;

    protected array $allowedLocales = [];
    
    /**
     * The validator instance.
     *
     * @var \Illuminate\Validation\Validator
     */
    protected Validator $validator;

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
        $givenLocales = array_keys($value);

        if ($this->primaryLocale && !in_array($this->primaryLocale, $givenLocales)) {
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
            $this->validator->errors()->add("{$attribute}.{$locale}", __('validator.locale'));
        }
    }
}
