<?php

/**
 * @file classes/form/validation/FormValidatorReCaptcha.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class FormValidatorReCaptcha
 *
 * @ingroup form_validation
 *
 * @brief Form validation check reCaptcha values.
 */

namespace PKP\form\validation;

use APP\core\Application;
use Exception;
use InvalidArgumentException;
use PKP\config\Config;
use PKP\form\Form;

class FormValidatorReCaptcha extends FormValidator
{
    /** @var string The response field containing the reCaptcha response */
    private const RECAPTCHA_RESPONSE_FIELD = 'g-recaptcha-response';
    /** @var string The request URL */
    private const RECAPTCHA_URL = 'https://www.google.com/recaptcha/api/siteverify';
    /** @var string The initiating IP address of the user */
    private $_userIp;
    /** @var string The hostname to expect in the validation response */
    private $_hostname;

    /**
     * Constructor.
     *
     * @param \PKP\form\Form $form
     * @param string $userIp IP address of user request
     * @param string $message Key of message to display on mismatch
     * @param string|null $hostname Hostname to expect in validation response
     */
    public function __construct(Form $form, string $userIp, string $message, ?string $hostname = null)
    {
        parent::__construct($form, self::RECAPTCHA_RESPONSE_FIELD, FormValidator::FORM_VALIDATOR_REQUIRED_VALUE, $message);
        $this->_userIp = $userIp;
        $this->_hostname = $hostname;
    }

    //
    // Public methods
    //
    /**
     * @see FormValidator::isValid()
     * Determine whether or not the form meets this ReCaptcha constraint.
     *
     */
    public function isValid(): bool
    {
        $form = $this->getForm();
        try {
            $this->validateResponse($form->getData(self::RECAPTCHA_RESPONSE_FIELD), $this->_userIp, $this->_hostname);
            return true;
        } catch (Exception $exception) {
            $this->_message = 'common.captcha.error.missing-input-response';
            return false;
        }
    }

    /**
     * Validates the reCaptcha response
     *
     * @param string|null $response The reCaptcha response
     * @param string|null $ip The user IP address (defaults to null)
     * @param string|null $hostname The application hostname (defaults to null)
     *
     * @throws Exception Throws in case the validation fails
     */
    public static function validateResponse(?string $response, ?string $ip = null, ?string $hostname = null): void
    {
        if (!empty($ip) && !filter_var($ip, FILTER_VALIDATE_IP)) {
            throw new InvalidArgumentException('Invalid IP address.');
        }

        if (empty($response)) {
            throw new InvalidArgumentException('The reCaptcha user response is required.');
        }

        $privateKey = Config::getVar('captcha', 'recaptcha_private_key');
        if (empty($privateKey)) {
            throw new Exception('The reCaptcha is not configured correctly, the secret key is missing.');
        }

        $httpClient = Application::get()->getHttpClient();
        $response = $httpClient->request(
            'POST',
            self::RECAPTCHA_URL,
            [
                'multipart' => [
                    ['name' => 'secret', 'contents' => $privateKey],
                    ['name' => 'response', 'contents' => $response],
                    ['name' => 'remoteip', 'contents' => $ip]
                ]
            ]
        );

        $response = json_decode($response->getBody(), true);
        if (Config::getVar('captcha', 'recaptcha_enforce_hostname') && ($response['hostname'] ?? null) != $hostname) {
            throw new Exception('The hostname validation of the reCaptcha response failed.');
        }

        $errorMap = [
            'missing-input-secret' => 'The secret parameter is missing.',
            'invalid-input-secret' => 'The secret parameter is invalid or malformed.',
            'missing-input-response' => 'The response parameter is missing.',
            'invalid-input-response' => 'The response parameter is invalid or malformed.',
            'invalid-keys' => 'The configured keys are invalid.',
            'bad-request' => 'The request is invalid or malformed.',
            'timeout-or-duplicate' => 'The response is no longer valid: either is too old or has been used previously.'
        ];

        if (!($response['success'] ?? false)) {
            $errors = [];
            foreach ($response['error-codes'] ?? [] as $error) {
                $errors[] = $errorMap[$error] ?? $error;
            }
            throw new Exception(implode("\n", $errors) ?: 'The reCaptcha validation failed.');
        }
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\form\validation\FormValidatorReCaptcha', '\FormValidatorReCaptcha');
}
