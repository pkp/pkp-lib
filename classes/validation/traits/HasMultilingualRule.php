<?php

namespace PKP\validation\traits;

use PKP\validation\MultilingualInput;

trait HasMultilingualRule
{
    abstract public function multilingualInputs(): array;

    abstract public function primaryLocale(): ?string;

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
