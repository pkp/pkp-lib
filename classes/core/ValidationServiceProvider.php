<?php

namespace PKP\core;

use Illuminate\Support\Str;
use PKP\validation\MultilingualInput;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Validator as ValidationValidator;
use Illuminate\Translation\CreatesPotentiallyTranslatedStrings;

class ValidationServiceProvider extends \Illuminate\Validation\ValidationServiceProvider
{
    use CreatesPotentiallyTranslatedStrings;
    
    public function boot()
    {
        Validator::extend('multilingual', function (string $attribute, mixed $value, array $parameters, ValidationValidator $validator) {
            
            $parameters = collect($parameters);
            $multilinngualInput = new MultilingualInput($parameters->shift(), $parameters->toArray());
            $multilinngualInput
                ->setValidator($validator)
                ->validate($attribute, $value, function ($attribute, $message = null) {
                    return $this->pendingPotentiallyTranslatedString($attribute, $message);
                });

            return $multilinngualInput->passed();
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
            $validator = new class($app['translator'], $app) extends \Illuminate\Validation\Factory {
                
                /**
                 * Resolve a new Validator instance.
                 *
                 * @param  array  $data
                 * @param  array  $rules
                 * @param  array  $messages
                 * @param  array  $attributes
                 * @return \Illuminate\Validation\Validator
                 */
                protected function resolve(array $data, array $rules, array $messages, array $attributes)
                {
                    if (is_null($this->resolver)) {
                        return new class ($this->translator, $data, $rules, $messages, $attributes) extends \Illuminate\Validation\Validator {

                            /**
                             * Get the validation message for an attribute and rule.
                             *
                             * @param  string  $attribute
                             * @param  string  $rule
                             * @return string
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
