<?php

/**
 * @file classes/core/ValidationServiceProvider.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ValidationServiceProvider
 *
 * @brief Register session driver, manager and related services
 */

namespace PKP\core;

use PKP\facades\Locale;
use Illuminate\Support\Str;
use PKP\validation\MultilingualInput;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Validator as ValidationValidator;
use Illuminate\Translation\CreatesPotentiallyTranslatedStrings;

class ValidationServiceProvider extends \Illuminate\Validation\ValidationServiceProvider
{
    use CreatesPotentiallyTranslatedStrings;
    
    /**
     * Boot service provider
     * 
     * A good place to add any custom validation rules which will be availbale once the
     * Service provider get registered
     * 
     * @return void
     */
    public function boot()
    {
        Validator::extend('multilingual', function (string $attribute, mixed $value, array $parameters, ValidationValidator $validator): bool {
            
            $parameters = collect($parameters);
            $multilinngualInput = new MultilingualInput($parameters->shift(), $parameters->toArray());

            $multilinngualInput
                ->setValidator($validator)
                ->validate($attribute, $value, function ($attribute, $message = null) { // source : \Illuminate\Validation\InvokableValidationRule
                    return $this->pendingPotentiallyTranslatedString($attribute, $message);
                });

            return $multilinngualInput->passed();
        });

        Validator::extend('no_new_line', function (string $attribute, mixed $value, array $parameters, ValidationValidator $validator): bool {
            return strpos($value, PHP_EOL) === false;
        });

        Validator::extend('email_or_localhost', function (string $attribute, mixed $value, array $parameters, ValidationValidator $validator): bool {
            $validationFactory = app()->get('validator'); /** @var \Illuminate\Validation\Factory $validationFactory */
            
            $emailValidator = $validationFactory->make(
                ['value' => $value],
                ['value' => 'email']
            );

            if ($emailValidator->passes()) {
                return true;
            }

            $regexValidator = $validationFactory->make(
                ['value' => $value],
                ['value' => ['regex:/^[-a-zA-Z0-9!#\$%&\'\*\+\.\/=\?\^_\`\{\|\}~]*(@localhost)$/']]
            );

            if ($regexValidator->passes()) {
                return true;
            }

            return false;
        });

        Validator::extend('issn', function (string $attribute, mixed $value, array $parameters, ValidationValidator $validator): bool {
            $validationFactory = app()->get('validator'); /** @var \Illuminate\Validation\Factory $validationFactory */

            $regexValidator = $validationFactory->make(
                ['value' => $value],
                ['value' => 'regex:/^(\d{4})-(\d{3}[\dX])$/']
            );

            if ($regexValidator->fails()) {
                return false;
            }

            // ISSN check digit: http://www.loc.gov/issn/basics/basics-checkdigit.html
            $numbers = str_replace('-', '', $value);
            $check = 0;

            for ($i = 0; $i < 7; $i++) {
                $check += $numbers[$i] * (8 - $i);
            }

            $check = $check % 11;

            switch ($check) {
                case 0:
                    $check = '0';
                    break;
                case 1:
                    $check = 'X';
                    break;
                default:
                    $check = (string) (11 - $check);
            }

            return ($numbers[7] === $check);
        });

        Validator::extend('orcid', function (string $attribute, mixed $value, array $parameters, ValidationValidator $validator): bool {
            $validationFactory = app()->get('validator'); /** @var \Illuminate\Validation\Factory $validationFactory */

            $orcidRegexValidator = $validationFactory->make(
                ['value' => $value],
                ['value' => 'regex:/^https:\/\/(sandbox\.)?orcid.org\/(\d{4})-(\d{4})-(\d{4})-(\d{3}[0-9X])$/']
            );

            if ($orcidRegexValidator->fails()) {
                return false;
            }

            // ISNI check digit: http://www.isni.org/content/faq#FAQ16
            $digits = preg_replace('/[^0-9X]/', '', $value);

            $total = 0;
            for ($i = 0; $i < 15; $i++) {
                $total = ($total + $digits[$i]) * 2;
            }

            $remainder = $total % 11;
            $result = (12 - $remainder) % 11;

            return ($digits[15] == ($result == 10 ? 'X' : $result));
        });

        Validator::extend('currency', function (string $attribute, mixed $value, array $parameters, ValidationValidator $validator): bool {
            $currency = Locale::getCurrencies()->getByLetterCode((string) $value);
            return isset($currency);
        });

        Validator::extend('country', function (string $attribute, mixed $value, array $parameters, ValidationValidator $validator): bool {
            $country = Locale::getCountries()->getByAlpha2((string) $value);
            return isset($country);
        });
    }

    /**
     * Register the validation factory.
     *
     * @return void
     */
    protected function registerValidationFactory()
    {
        $this->app->singleton('validator', function ($app) {
            $validator = new class($app['translator'], $app) extends \Illuminate\Validation\Factory
            {    
                /**
                 * @see \Illuminate\Validation\Factory::resolve()
                 */
                protected function resolve(array $data, array $rules, array $messages, array $attributes)
                {
                    if (is_null($this->resolver)) {
                        return new class ($this->translator, $data, $rules, $messages, $attributes) extends \Illuminate\Validation\Validator
                        {
                            /**
                             * This override the core Validator's message construction system
                             * that allows multilingual support for validation error message
                             * 
                             * @see \Illuminate\Validation\Concerns\FormatsMessages::getMessage()
                             */
                            protected function getMessage($attribute, $rule)
                            {
                                $attributeWithPlaceholders = $attribute;
                                
                                $attribute = $this->replacePlaceholderInString($attribute);
                                
                                $inlineMessage = $this->getInlineMessage($attribute, $rule);
                                
                                // First we will retrieve the custom message for the validation rule if one
                                // exists. If a custom validation message is being used we'll return the
                                // custom message, otherwise we'll keep searching for a valid message.
                                if (! is_null($inlineMessage)) {
                                    return $inlineMessage;
                                }

                                $lowerRule = Str::snake($rule);

                                $customKey = "validation.custom.{$attribute}.{$lowerRule}";
                                
                                $customMessage = $this->getCustomMessageFromTranslator(
                                    in_array($rule, $this->sizeRules)
                                        ? [$customKey.".{$this->getAttributeType($attribute)}", $customKey]
                                        : $customKey
                                );
                                
                                // First we check for a custom defined validation message for the attribute
                                // and rule. This allows the developer to specify specific messages for
                                // only some attributes and rules that need to get specially formed.
                                if ($customMessage !== '##'.$customKey.'##') {
                                    return $customMessage;
                                }

                                // If the rule being validated is a "size" rule, we will need to gather the
                                // specific error message for the type of attribute being validated such
                                // as a number, file or string which all have different message types.
                                elseif (in_array($rule, $this->sizeRules)) {
                                    return $this->getSizeMessage($attributeWithPlaceholders, $rule);
                                }

                                // Finally, if no developer specified messages have been set, and no other
                                // special messages apply for this rule, we will just pull the default
                                // messages out of the translator service for this validation rule.
                                $key = "validator.{$lowerRule}";

                                if ($key !== ($value = $this->translator->get($key))) {
                                    return $value;
                                }

                                return $this->getFromLocalArray(
                                    $attribute, $lowerRule, $this->fallbackMessages
                                ) ?: $key;
                            }
                        };
                    }

                    return call_user_func($this->resolver, $this->translator, $data, $rules, $messages, $attributes);
                }
            };

            // The validation presence verifier is responsible for determining the existence of
            // values in a given data collection which is typically a relational database or
            // other persistent data stores. It is used to check for "uniqueness" as well.
            if (isset($app['db'], $app['validation.presence'])) {
                $validator->setPresenceVerifier($app['validation.presence']);
            }

            return $validator;
        });
    }
}
