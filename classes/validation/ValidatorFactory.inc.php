<?php
/**
 * @file classes/validation/ValidatorFactory.php
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2000-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ValidatorFactory
 * @ingroup validation
 *
 * @brief A factory class for creating a Validator from the Laravel framework.
 */
use Illuminate\Container\Container;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Translation\FileLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\DatabasePresenceVerifier;
use Illuminate\Validation\Factory;

class ValidatorFactory {

	/**
	 * Create a validator
	 *
	 * This is a wrapper function for Laravel's validator factory. It loads the
	 * necessary dependencies and instantiates Laravel's validation factory, then
	 * calls the `make` method on that factory.
	 *
	 * @param $props array The properties to validate
	 * @param $rules array The validation rules
	 * @param $messages array Error messages
	 * @return Illuminate\Validation\Validator
	 */
	static public function make($props, $rules, $messages = []) {

		// This configures a non-existent translation file, but it is necessary to
		// instantiate Laravel's validator. We override the messages with our own
		// translated strings before returning the validator.
		$loader = new FileLoader(new Filesystem, 'lang');
		$translator = new Translator($loader, 'en');
		$validation = new Factory($translator, new Container);

		// Add custom validation rule which extends Laravel's email rule to accept
		// @localhost addresses. @localhost addresses are only loosely validated
		// for allowed characters.
		$validation->extend('email_or_localhost', function($attribute, $value, $parameters, $validator) use ($validation) {
			$emailValidator = $validation->make(
				['value' => $value],
				['value' => 'email']
			);
			if ($emailValidator->passes()) {
				return true;
			}
			$regexValidator = $validation->make(
				['value' => $value],
				['value' => ['regex:/^[-a-zA-Z0-9!#\$%&\'\*\+\.\/=\?\^_\`\{\|\}~]*(@localhost)$/']]
			);
			if ($regexValidator->passes()) {
				return true;
			}

			return false;
		});

		// Add custom validation rule for ISSNs
		$validation->extend('issn', function($attribute, $value, $parameters, $validator) use ($validation) {
			$regexValidator = $validation->make(['value' => $value], ['value' => 'regex:/^(\d{4})-(\d{3}[\dX])$/']);
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

		// Add custom validation rule for orcids
		$validation->extend('orcid', function($attribute, $value, $parameters, $validator) use ($validation) {
			$orcidRegexValidator = $validation->make(
				['value' => $value],
				['value' => 'regex:/^http[s]?:\/\/orcid.org\/(\d{4})-(\d{4})-(\d{4})-(\d{3}[0-9X])$/']
			);
			if ($orcidRegexValidator->fails()) {
				return false;
			}
			// ISNI check digit: http://www.isni.org/content/faq#FAQ16
			$digits = preg_replace("/[^0-9X]/", "", $value);

			$total = 0;
			for ($i = 0; $i < 15; $i++) {
				$total = ($total + $digits[$i]) * 2;
			}

			$remainder = $total % 11;
			$result = (12 - $remainder) % 11;

			return ($digits[15] == ($result == 10 ? 'X' : $result));
		});

		// Add custom validation rule for currency
		$validation->extend('currency', function($attribute, $value, $parameters, $validator) {
			$isoCodes = new \Sokil\IsoCodes\IsoCodesFactory();
			$currency = $isoCodes->getCurrencies()->getByLetterCode($value);
			return isset($currency);
		});

		$validator = $validation->make($props, $rules, ValidatorFactory::getMessages($messages));

		return $validator;
	}

	/**
	 * Compile translated error messages for each of the validation rules
	 * we support.
	 *
	 * @param $messages array List of error messages to override the defaults.
	 * @return array
	 */
	static public function getMessages($messages = []) {

		static $defaultMessages = [];

		if (empty($defaultMessages)) {
			$defaultMessages = [
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
			];
		}

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
	 * @param $message string
	 * @return string
	 */
	static public function convertMessageSyntax($message) {
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
	 * @param $validator Illuminate\Validation\Validator
	 * @param $action string One of VALIDATE_ACTION_* constants
	 * @param $requiredProps array List of prop names
	 * @param $multilingualProps array List of prop names
	 * @param $allowedLocales array List of locale codes
	 * @param $primaryLocale string Primary locale code
	 */
	static public function required($validator, $action, $requiredProps, $multilingualProps, $allowedLocales, $primaryLocale) {
		$validator->after(function($validator) use ($action, $requiredProps, $multilingualProps, $allowedLocales, $primaryLocale) {

			$allLocales = AppLocale::getAllLocales();
			$primaryLocaleName = $primaryLocale;
			foreach ($allLocales as $locale => $name) {
				if ($locale === $primaryLocale) {
					$primaryLocaleName = $name;
				}
			}

			$props = $validator->getData();

			foreach ($requiredProps as $requiredProp) {

				// Required multilingual props should only be
				// required in the primary locale
				if (in_array($requiredProp, $multilingualProps)) {
					if ($action === VALIDATE_ACTION_ADD) {
						if (self::isEmpty($props[$requiredProp]) || self::isEmpty($props[$requiredProp][$primaryLocale])) {
							$validator->errors()->add($requiredProp . '.' . $primaryLocale, __('form.missingRequired'));
						}
					} else {
						if (isset($props[$requiredProp]) && array_key_exists($primaryLocale, $props[$requiredProp]) && self::isEmpty($props[$requiredProp][$primaryLocale])) {
							if (count($allowedLocales) === 1) {
								$validator->errors()->add($requiredProp, __('form.missingRequired'));
							} else {
								$validator->errors()->add($requiredProp . '.' . $primaryLocale, __('form.requirePrimaryLocale', array('language' => $primaryLocaleName)));
							}
						}
					}

				} else {
					if (($action === VALIDATE_ACTION_ADD && self::isEmpty($props[$requiredProp])) ||
							($action === VALIDATE_ACTION_EDIT && array_key_exists($requiredProp, $props) && self::isEmpty($props[$requiredProp]))) {
						$validator->errors()->add($requiredProp, __('form.missingRequired'));
					}
				}
			}
		});
	}

	/**
	 * Checks whether the given value is an empty string
	 *
	 * @param $value string
	 */
	static private function isEmpty($value) {
		return $value == '';
	}

	/**
	 * A wrapper method that calls $validator->after to check for data from
	 * locales that are not allowed
	 *
	 * @param $validator Illuminate\Validation\Validator
	 * @param $multilingualProps array List of prop names
	 * @param $allowedLocales array List of locale codes
	 */
	static public function allowedLocales($validator, $multilingualProps, $allowedLocales) {
		$validator->after(function($validator) use ($multilingualProps, $allowedLocales) {
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
	 * @param $validator Illuminate\Validation\Validator
	 * @param $uploadProps array List of prop names that may include a
	 *  a temporaryFileId
	 * @param $multilingualUploadProps array List of $uploadProps which are
	 *  multiligual
	 * @param $props array Key/value list of props
	 * @param $allowedLocales array List of locale codes
	 * @param $userId int The user ID which owns the temporary files
	 */
	static public function temporaryFilesExist($validator, $uploadProps, $multilingualUploadProps, $props, $allowedLocales, $userId) {
		$validator->after(function($validator) use ($uploadProps, $multilingualUploadProps, $props, $allowedLocales, $userId) {
			import('lib.pkp.classes.file.TemporaryFileManager');
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
