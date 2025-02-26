<?php
/**
 * @file classes/validation/ValidatorFactory.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ValidatorFactory
 *
 * @ingroup validation
 *
 * @brief A factory class for creating a Validator from the Laravel framework.
 */

namespace PKP\validation;

use Illuminate\Support\Arr;
use Illuminate\Validation\Validator;
use PKP\config\Config;
use PKP\facades\Locale;
use PKP\file\TemporaryFileManager;
use PKP\i18n\LocaleMetadata;

class ValidatorFactory
{
    private function __construct()
    {       
    }

    /**
     * Create a validator
     *
     * This is a wrapper function for Laravel's validator factory. It loads the
     * necessary dependencies and instantiates Laravel's validation factory, then
     * calls the `make` method on that factory.
     *
     * @param array $props The properties to validate
     * @param array $rules The validation rules
     * @param array $messages Error messages
     */
    public static function make(array $props, array $rules, ?array $messages = []): Validator
    {
        /** @var \Illuminate\Validation\Factory $validationFactory */
        $validationFactory = app()->get('validator');

        return $validationFactory->make($props, $rules, self::getMessages($messages));   
    }

    /**
     * Compile translated error messages for each of the validation rules
     * we support.
     *
     * @param array $messages List of error messages to override the defaults.
     *
     * @return array
     */
    public static function getMessages($messages = [])
    {
        static $defaultMessages;
        $defaultMessages ??= [
            'accepted' => __('validator.accepted'),
            'active_url' => __('validator.active_url'),
            'after' => __('validator.after'),
            'alpha' => __('validator.alpha'),
            'alpha_dash' => __('validator.alpha_dash'),
            'alpha_num' => __('validator.alpha_num'),
            'array' => __('validator.array'),
            'before' => __('validator.before'),
            'between' => [
                'numeric' => __('validator.between.numeric'),
                'file' => __('validator.between.file'),
                'string' => __('validator.between.string'),
                'array' => __('validator.between.array'),
            ],
            'boolean' => __('validator.boolean'),
            'confirmed' => __('validator.confirmed'),
            'country' => __('validator.country'),
            'currency' => __('validator.currency'),
            'date' => __('validator.date'),
            'date_format' => __('validator.date_format'),
            'different' => __('validator.different'),
            'digits' => __('validator.digits'),
            'digits_between' => __('validator.digits_between'),
            'email' => __('validator.email'),
            'email_or_localhost' => __('validator.email'),
            'exists' => __('validator.exists'),
            'filled' => __('validator.filled'),
            'image' => __('validator.image'),
            'in' => __('validator.in'),
            'integer' => __('validator.integer'),
            'ip' => __('validator.ip'),
            'issn' => __('validator.issn'),
            'json' => __('validator.json'),
            'max' => [
                'numeric' => __('validator.max.numeric'),
                'file' => __('validator.max.file'),
                'string' => __('validator.max.string'),
                'array' => __('validator.max.array'),
            ],
            'mimes' => __('validator.mimes'),
            'min' => [
                'numeric' => __('validator.min.numeric'),
                'file' => __('validator.min.file'),
                'string' => __('validator.min.string'),
                'array' => __('validator.min.array'),
            ],
            'not_in' => __('validator.not_in'),
            'numeric' => __('validator.numeric'),
            'orcid' => __('user.orcid.orcidInvalid'),
            'present' => __('validator.present'),
            'regex' => __('validator.regex'),
            'required' => __('validator.required'),
            'required_if' => __('validator.required_if'),
            'required_unless' => __('validator.required_unless'),
            'required_with' => __('validator.required_with'),
            'required_with_all' => __('validator.required_with_all'),
            'required_without' => __('validator.required_without'),
            'required_without_all' => __('validator.required_without_all'),
            'same' => __('validator.same'),
            'size' => [
                'numeric' => __('validator.size.numeric'),
                'file' => __('validator.size.file'),
                'string' => __('validator.size.string'),
                'array' => __('validator.size.array'),
            ],
            'string' => __('validator.string'),
            'timezone' => __('validator.timezone'),
            'unique' => __('validator.unique'),
            'url' => __('validator.url'),
            'no_new_line' => __('validator.no_new_line'),
            'allowable_title_html_tags' => __('validator.allowable_title_html_tags', ['tags' => Config::getVar('security', 'allowed_title_html', 'b,i,u,sup,sub')]),
        ];

        $messages = array_merge($defaultMessages, $messages);

        // Convert variables in translated strings from {$variable} syntax to
        // Laravel's :variable syntax.
        foreach ($messages as $rule => $message) {
            if (is_string($message)) {
                $messages[$rule] = self::convertMessageSyntax($message);
            } else {
                foreach ($message as $subRule => $subMessage) {
                    $messages[$rule][$subRule] = self::convertMessageSyntax($subMessage);
                }
            }
        }

        return $messages;
    }

    /**
     * Convert variables in translated strings from {$variable} syntax to
     * Laravel's :variable syntax
     *
     * @param string $message
     *
     * @return string
     */
    public static function convertMessageSyntax($message)
    {
        return preg_replace('/\{\$([a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)\}/', ':\1', $message);
    }

    /**
     * A wrapper method that calls $validator->after to check if required props
     * are present
     *
     * When adding an object, required props must be present in the list of
     * props being passed for validation. When editing an object, required
     * props may be absent if they are not being edited. But if a required
     * prop is present it can not be empty.
     *
     * Required props that are also multilingual will only be required in the
     * primary locale.
     *
     * @param Validator $validator
     * @param ?mixed $object The object being validated or null if adding an object
     * @param array $requiredProps List of prop names
     * @param array $multilingualProps List of prop names
     * @param array $allowedLocales List of locale codes
     * @param string $primaryLocale Primary locale code
     */
    public static function required($validator, $object, $requiredProps, $multilingualProps, $allowedLocales, $primaryLocale)
    {
        $validator->after(function ($validator) use ($object, $requiredProps, $multilingualProps, $allowedLocales, $primaryLocale) {
            /** @var LocaleMetadata */
            $locale = Arr::first(Locale::getLocales(), fn (LocaleMetadata $locale) => $locale->locale === $primaryLocale);
            $primaryLocaleName = $locale ? $locale->getDisplayName() : $primaryLocale;
            $props = $validator->getData();

            foreach ($requiredProps as $requiredProp) {
                // Required multilingual props should only be
                // required in the primary locale
                if (in_array($requiredProp, $multilingualProps)) {
                    if (is_null($object)) {
                        if (self::isEmpty($props[$requiredProp]) || self::isEmpty($props[$requiredProp][$primaryLocale])) {
                            $validator->errors()->add($requiredProp . '.' . $primaryLocale, __('validator.required'));
                        }
                    } else {
                        if (isset($props[$requiredProp]) && array_key_exists($primaryLocale, $props[$requiredProp]) && self::isEmpty($props[$requiredProp][$primaryLocale])) {
                            $message = __('validator.required');
                            if (count($allowedLocales) > 1) {
                                $message = __('form.requirePrimaryLocale', ['language' => $primaryLocaleName]);
                            }
                            $validator->errors()->add($requiredProp . '.' . $primaryLocale, $message);
                        }
                    }
                } else {
                    if (is_null($object) && self::isEmpty($props[$requiredProp]) ||
                            ($object && array_key_exists($requiredProp, $props) && self::isEmpty($props[$requiredProp]))) {
                        $validator->errors()->add($requiredProp, __('validator.required'));
                    }
                }
            }
        });
    }

    /**
     * Checks whether the given value is an empty string
     *
     * @param string $value
     */
    private static function isEmpty($value)
    {
        return is_string($value)
            ? trim($value) == ''
            : $value == '';
    }

    /**
     * A wrapper method that calls $validator->after to check for data from
     * locales that are not allowed
     *
     * @param Validator $validator
     * @param array $multilingualProps List of prop names
     * @param array $allowedLocales List of locale codes
     */
    public static function allowedLocales($validator, $multilingualProps, $allowedLocales)
    {
        $validator->after(function ($validator) use ($multilingualProps, $allowedLocales) {
            $props = $validator->getData();
            foreach ($props as $propName => $propValue) {
                if (!in_array($propName, $multilingualProps)) {
                    continue;
                }
                if (!is_array($propValue)) {
                    $validator->errors()->add($propName . '.' . current($allowedLocales), __('validator.localeExpected'));
                    break;
                }
                foreach ($propValue as $localeKey => $localeValue) {
                    if (!in_array($localeKey, $allowedLocales)) {
                        $validator->errors()->add($propName . '.' . $localeKey, __('validator.locale'));
                        break;
                    }
                }
            }
        });
    }

    /**
     * A wrapper method that validates the temporaryFileId of new file uploads
     * when an object is edited
     *
     * @param Validator $validator
     * @param array $uploadProps List of prop names that may include a
     *  a temporaryFileId
     * @param array $multilingualUploadProps List of $uploadProps which are
     *  multilingual
     * @param array $props Key/value list of props
     * @param array $allowedLocales List of locale codes
     * @param int $userId The user ID which owns the temporary files
     */
    public static function temporaryFilesExist($validator, $uploadProps, $multilingualUploadProps, $props, $allowedLocales, $userId)
    {
        $validator->after(function ($validator) use ($uploadProps, $multilingualUploadProps, $props, $allowedLocales, $userId) {
            $temporaryFileManager = new TemporaryFileManager();
            foreach ($uploadProps as $uploadProp) {
                if (!isset($props[$uploadProp])) {
                    continue;
                }
                if (in_array($uploadProp, $multilingualUploadProps)) {
                    foreach ($allowedLocales as $localeKey) {
                        if (!isset($props[$uploadProp][$localeKey])
                        || !isset($props[$uploadProp][$localeKey]['temporaryFileId'])
                        || $validator->errors()->get($uploadProp . '.' . $localeKey)) {
                            continue;
                        }
                        if (!$temporaryFileManager->getFile($props[$uploadProp][$localeKey]['temporaryFileId'], $userId)) {
                            $validator->errors()->add($uploadProp . '.' . $localeKey, __('common.noTemporaryFile'));
                        }
                    }
                } else {
                    if (!isset($props[$uploadProp]['temporaryFileId'])) {
                        continue;
                    }
                    if (!$temporaryFileManager->getFile($props[$uploadProp]['temporaryFileId'], $userId)) {
                        $validator->errors()->add($uploadProp, __('common.noTemporaryFile'));
                    }
                }
            }
        });
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\validation\ValidatorFactory', '\ValidatorFactory');
}
