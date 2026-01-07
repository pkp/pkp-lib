<?php

/**
 * @file classes/form/validation/FormValidatorAltcha.php
 *
 * Copyright (c) 2014-2024 Simon Fraser University
 * Copyright (c) 2000-2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class FormValidatorAltcha
 *
 * @ingroup form_validation
 *
 * @brief Form validation check Altcha values.
 */

namespace PKP\form\validation;

use AltchaOrg\Altcha\Altcha;
use AltchaOrg\Altcha\ChallengeOptions;
use AltchaOrg\Altcha\Hasher\Algorithm;
use APP\core\Application;
use APP\template\TemplateManager;
use Exception;
use InvalidArgumentException;
use PKP\config\Config;
use PKP\form\Form;

class FormValidatorAltcha extends FormValidator
{
    /** @var string The response field containing the ALTCHA response */
    private const ALTCHA_RESPONSE_FIELD = 'altcha';

    /** @var string The initiating IP address of the user */
    private $_userIp;

    /**
     * Constructor.
     *
     * @param string $userIp IP address of user request
     * @param string $message Key of message to display on mismatch
     */
    public function __construct(Form $form, string $userIp, string $message)
    {
        parent::__construct($form, self::ALTCHA_RESPONSE_FIELD, FormValidator::FORM_VALIDATOR_REQUIRED_VALUE, $message);
        $this->_userIp = $userIp;
    }

    /**
     * @see FormValidator::isValid()
     * Determine whether or not the form meets this ALTCHA constraint.
     */
    public function isValid(): bool
    {
        $form = $this->getForm();
        try {
            $this->validateResponse($form->getData(self::ALTCHA_RESPONSE_FIELD), $this->_userIp);
            return true;
        } catch (Exception $exception) {
            $this->_message = 'common.captcha.error.missing-input-response';
            return false;
        }
    }

    /**
     * Creates an Altcha instance with the configured HMAC key.
     *
     * @throws Exception If the HMAC key is not configured
     */
    private static function createAltchaInstance(): Altcha
    {
        $hmacKey = Config::getVar('captcha', 'altcha_hmackey');
        if (empty($hmacKey)) {
            throw new Exception('The ALTCHA is not configured correctly, the HMAC key is missing.');
        }

        return new Altcha($hmacKey);
    }

    /**
     * Validates the ALTCHA response
     *
     * @param $response The ALTCHA response
     * @param $ip The user IP address (defaults to null)
     *
     * @throws Exception Throws in case the validation fails
     */
    public static function validateResponse(?string $response, ?string $ip = null): void
    {
        if (!empty($ip) && !filter_var($ip, FILTER_VALIDATE_IP)) {
            throw new InvalidArgumentException('Invalid IP address.');
        }

        if (empty($response)) {
            throw new InvalidArgumentException('The ALTCHA user response is required.');
        }

        $altcha = self::createAltchaInstance();
        $payload = (array) json_decode(base64_decode($response));

        if (!$altcha->verifySolution($payload)) {
            throw new Exception('The ALTCHA validation failed.');
        }
    }

    public static function addAltchaJavascript(TemplateManager $templateMgr): void
    {
        $request = Application::get()->getRequest();
        $altchaPath = $request->getBaseUrl() . '/lib/pkp/js/lib/altcha/altcha.js';

        $altchaHeader = '<script async defer src="' . $altchaPath . '" type="module"></script>';
        $templateMgr->addHeader('altcha', $altchaHeader);
    }

    public static function insertFormChallenge(TemplateManager $templateMgr): void
    {
        $altcha = self::createAltchaInstance();

        // Default maxNumber value for a 3 to 5 seconds average solving time
        $maxNumber = (int) (Config::getVar('captcha', 'altcha_encrypt_number') ?: 10000);

        $options = new ChallengeOptions(
            algorithm: Algorithm::SHA256,
            maxNumber: $maxNumber
        );

        $challenge = $altcha->createChallenge($options);

        $templateMgr->assign('altchaEnabled', true);
        $templateMgr->assign('altchaChallenge', [
            'algorithm' => $challenge->algorithm,
            'challenge' => $challenge->challenge,
            'maxnumber' => $challenge->maxNumber,
            'salt' => $challenge->salt,
            'signature' => $challenge->signature,
        ]);
    }
}
