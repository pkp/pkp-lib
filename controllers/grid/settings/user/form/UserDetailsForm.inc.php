<?php

/**
 * @file controllers/grid/settings/user/form/UserDetailsForm.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class UserDetailsForm
 * @ingroup controllers_grid_settings_user_form
 *
 * @brief Form for editing user profiles.
 */

import('lib.pkp.controllers.grid.settings.user.form.UserForm');

use APP\facades\Repo;
use APP\notification\NotificationManager;
use PKP\facades\Locale;
use APP\template\TemplateManager;
use PKP\identity\Identity;
use PKP\mail\MailTemplate;
use PKP\notification\PKPNotification;
use PKP\user\InterestManager;

class UserDetailsForm extends UserForm
{
    /** @var User */
    public $user;

    /** @var Author An optional author to base this user on */
    public $author;

    /**
     * Constructor.
     *
     * @param PKPRequest $request
     * @param int $userId optional
     * @param Author $author optional
     */
    public function __construct($request, $userId = null, $author = null)
    {
        parent::__construct('controllers/grid/settings/user/form/userDetailsForm.tpl', $userId);

        if (isset($author)) {
            $this->author = & $author;
        } else {
            $this->author = null;
        }

        // the users register for the site, thus
        // the site primary locale is the required default locale
        $site = $request->getSite();
        $this->addSupportedFormLocale($site->getPrimaryLocale());

        // Validation checks for this form
        $form = $this;
        if ($userId == null) {
            $this->addCheck(new \PKP\form\validation\FormValidator($this, 'username', 'required', 'user.profile.form.usernameRequired'));
            $this->addCheck(new \PKP\form\validation\FormValidatorCustom($this, 'username', 'required', 'user.register.form.usernameExists', function ($username, $userId) {
                $user = Repo::user()->getByUsername($username, true);
                return !$user || $user->getId() == $userId;
            }, [$this->userId]));
            $this->addCheck(new \PKP\form\validation\FormValidatorUsername($this, 'username', 'required', 'user.register.form.usernameAlphaNumeric'));

            $this->addCheck(new \PKP\form\validation\FormValidator($this, 'password', 'required', 'user.profile.form.passwordRequired'));
            $this->addCheck(new \PKP\form\validation\FormValidatorCustom($this, 'password', 'required', 'user.register.form.passwordLengthRestriction', function ($password) use ($form, $site) {
                return $form->getData('generatePassword') || PKPString::strlen($password) >= $site->getMinPasswordLength();
            }, [], false, ['length' => $site->getMinPasswordLength()]));
            $this->addCheck(new \PKP\form\validation\FormValidatorCustom($this, 'password', 'required', 'user.register.form.passwordsDoNotMatch', function ($password) use ($form) {
                return $password == $form->getData('password2');
            }));
        } else {
            $this->user = Repo::user()->get($userId, true);

            $this->addCheck(new \PKP\form\validation\FormValidatorCustom($this, 'password', 'optional', 'user.register.form.passwordLengthRestriction', function ($password) use ($form, $site) {
                return $form->getData('generatePassword') || PKPString::strlen($password) >= $site->getMinPasswordLength();
            }, [], false, ['length' => $site->getMinPasswordLength()]));
            $this->addCheck(new \PKP\form\validation\FormValidatorCustom($this, 'password', 'optional', 'user.register.form.passwordsDoNotMatch', function ($password) use ($form) {
                return $password == $form->getData('password2');
            }));
        }
        $this->addCheck(new \PKP\form\validation\FormValidatorLocale($this, 'givenName', 'required', 'user.profile.form.givenNameRequired', $site->getPrimaryLocale()));
        $this->addCheck(new \PKP\form\validation\FormValidatorCustom($this, 'familyName', 'optional', 'user.profile.form.givenNameRequired.locale', function ($familyName) use ($form) {
            $givenNames = $form->getData('givenName');
            foreach ($familyName as $locale => $value) {
                if (!empty($value) && empty($givenNames[$locale])) {
                    return false;
                }
            }
            return true;
        }));
        $this->addCheck(new \PKP\form\validation\FormValidatorUrl($this, 'userUrl', 'optional', 'user.profile.form.urlInvalid'));
        $this->addCheck(new \PKP\form\validation\FormValidatorEmail($this, 'email', 'required', 'user.profile.form.emailRequired'));
        $this->addCheck(new \PKP\form\validation\FormValidatorCustom($this, 'email', 'required', 'user.register.form.emailExists', function ($email, $currentUserId) {
            $user = Repo::user()->getByEmail($email, true);
            return !$user || $user->getId() == $currentUserId;
        }, [$this->userId]));
        $this->addCheck(new \PKP\form\validation\FormValidatorORCID($this, 'orcid', 'optional', 'user.orcid.orcidInvalid'));
        $this->addCheck(new \PKP\form\validation\FormValidatorPost($this));
        $this->addCheck(new \PKP\form\validation\FormValidatorCSRF($this));
    }

    /**
     * Initialize form data from current user profile.
     */
    public function initData()
    {
        $request = Application::get()->getRequest();
        $context = $request->getContext();
        $contextId = $context ? $context->getId() : \PKP\core\PKPApplication::CONTEXT_ID_NONE;

        $data = [];

        if (isset($this->user)) {
            $user = $this->user;

            $interestManager = new InterestManager();

            $data = [
                'authId' => $user->getAuthId(),
                'username' => $user->getUsername(),
                'givenName' => $user->getGivenName(null), // Localized
                'familyName' => $user->getFamilyName(null), // Localized
                'preferredPublicName' => $user->getPreferredPublicName(null), // Localized
                'signature' => $user->getSignature(null), // Localized
                'affiliation' => $user->getAffiliation(null), // Localized
                'email' => $user->getEmail(),
                'userUrl' => $user->getUrl(),
                'phone' => $user->getPhone(),
                'orcid' => $user->getOrcid(),
                'mailingAddress' => $user->getMailingAddress(),
                'country' => $user->getCountry(),
                'biography' => $user->getBiography(null), // Localized
                'interests' => $interestManager->getInterestsForUser($user),
                'locales' => $user->getLocales(),
            ];
            $data['canCurrentUserGossip'] = Repo::user()->canCurrentUserGossip($user->getId());
            if ($data['canCurrentUserGossip']) {
                $data['gossip'] = $user->getGossip();
            }
        } elseif (isset($this->author)) {
            $author = $this->author;
            $data = [
                'givenName' => $author->getGivenName(null), // Localized
                'familyName' => $author->getFamilyName(null), // Localized
                'affiliation' => $author->getAffiliation(null), // Localized
                'preferredPublicName' => $author->getPreferredPublicName(null), // Localized
                'email' => $author->getEmail(),
                'userUrl' => $author->getUrl(),
                'orcid' => $author->getOrcid(),
                'country' => $author->getCountry(),
                'biography' => $author->getBiography(null), // Localized
            ];
        } else {
            $data = [
                'mustChangePassword' => true,
            ];
        }
        foreach ($data as $key => $value) {
            $this->setData($key, $value);
        }

        parent::initData();
    }

    /**
     * @copydoc UserForm::display
     *
     * @param null|mixed $request
     * @param null|mixed $template
     */
    public function display($request = null, $template = null)
    {
        $site = $request->getSite();
        $countries = [];
        foreach (Locale::getCountries() as $country) {
            $countries[$country->getAlpha2()] = $country->getLocalName();
        }
        asort($countries);
        $templateMgr = TemplateManager::getManager($request);

        $templateMgr->assign([
            'minPasswordLength' => $site->getMinPasswordLength(),
            'source' => $request->getUserVar('source'),
            'userId' => $this->userId,
            'sitePrimaryLocale' => $site->getPrimaryLocale(),
            'availableLocales' => $site->getSupportedLocaleNames(),
            'countries' => $countries,
        ]);

        if (isset($this->user)) {
            $templateMgr->assign('username', $this->user->getUsername());
        }

        $authDao = DAORegistry::getDAO('AuthSourceDAO'); /** @var AuthSourceDAO $authDao */
        $authSources = $authDao->getSources();
        $authSourceOptions = [];
        foreach ($authSources->toArray() as $auth) {
            $authSourceOptions[$auth->getAuthId()] = $auth->getTitle();
        }
        if (!empty($authSourceOptions)) {
            $templateMgr->assign('authSourceOptions', $authSourceOptions);
        }

        return parent::display($request, $template);
    }


    /**
     * Assign form data to user-submitted data.
     *
     * @see Form::readInputData()
     */
    public function readInputData()
    {
        parent::readInputData();

        $this->readUserVars([
            'authId',
            'password',
            'password2',
            'givenName',
            'familyName',
            'preferredPublicName',
            'signature',
            'affiliation',
            'email',
            'userUrl',
            'phone',
            'orcid',
            'mailingAddress',
            'country',
            'biography',
            'gossip',
            'interests',
            'locales',
            'generatePassword',
            'sendNotify',
            'mustChangePassword'
        ]);
        if ($this->userId == null) {
            $this->readUserVars(['username']);
        }

        if ($this->getData('locales') == null || !is_array($this->getData('locales'))) {
            $this->setData('locales', []);
        }
    }

    /**
     * Get all locale field names
     */
    public function getLocaleFieldNames()
    {
        return ['biography', 'signature', 'affiliation', Identity::IDENTITY_SETTING_GIVENNAME, Identity::IDENTITY_SETTING_FAMILYNAME, 'preferredPublicName'];
    }

    /**
     * Create or update a user.
     */
    public function execute(...$functionParams)
    {
        $request = Application::get()->getRequest();
        $context = $request->getContext();

        if (!isset($this->user)) {
            $this->user = Repo::user()->newDataObject();
            $this->user->setInlineHelp(1); // default new users to having inline help visible
        }

        $this->user->setGivenName($this->getData('givenName'), null); // Localized
        $this->user->setFamilyName($this->getData('familyName'), null); // Localized
        $this->user->setPreferredPublicName($this->getData('preferredPublicName'), null); // Localized
        $this->user->setAffiliation($this->getData('affiliation'), null); // Localized
        $this->user->setSignature($this->getData('signature'), null); // Localized
        $this->user->setEmail($this->getData('email'));
        $this->user->setUrl($this->getData('userUrl'));
        $this->user->setPhone($this->getData('phone'));
        $this->user->setOrcid($this->getData('orcid'));
        $this->user->setMailingAddress($this->getData('mailingAddress'));
        $this->user->setCountry($this->getData('country'));
        $this->user->setBiography($this->getData('biography'), null); // Localized
        $this->user->setMustChangePassword($this->getData('mustChangePassword') ? 1 : 0);
        $this->user->setAuthId((int) $this->getData('authId'));
        // Users can never view/edit their own gossip fields
        if (Repo::user()->canCurrentUserGossip($this->user->getId())) {
            $this->user->setGossip($this->getData('gossip'));
        }

        $site = $request->getSite();
        $availableLocales = $site->getSupportedLocales();

        $locales = [];
        foreach ($this->getData('locales') as $locale) {
            if (Locale::isLocaleValid($locale) && in_array($locale, $availableLocales)) {
                array_push($locales, $locale);
            }
        }
        $this->user->setLocales($locales);

        if ($this->user->getAuthId()) {
            $authDao = DAORegistry::getDAO('AuthSourceDAO'); /** @var AuthSourceDAO $authDao */
            $auth = & $authDao->getPlugin($this->user->getAuthId());
        }

        parent::execute(...$functionParams);

        if ($this->user->getId() != null) {
            if ($this->getData('password') !== '') {
                if (isset($auth)) {
                    $auth->doSetUserPassword($this->user->getUsername(), $this->getData('password'));
                    $this->user->setPassword(Validation::encryptCredentials($this->user->getId(), Validation::generatePassword())); // Used for PW reset hash only
                } else {
                    $this->user->setPassword(Validation::encryptCredentials($this->user->getUsername(), $this->getData('password')));
                }
            }

            if (isset($auth)) {
                // FIXME Should try to create user here too?
                $auth->doSetUserInfo($this->user);
            }

            Repo::user()->edit($this->user);
        } else {
            $this->user->setUsername($this->getData('username'));
            if ($this->getData('generatePassword')) {
                $password = Validation::generatePassword();
                $sendNotify = true;
            } else {
                $password = $this->getData('password');
                $sendNotify = $this->getData('sendNotify');
            }

            if (isset($auth)) {
                $this->user->setPassword($password);
                // FIXME Check result and handle failures
                $auth->doCreateUser($this->user);
                $this->user->setAuthId($auth->authId);
                $this->user->setPassword(Validation::encryptCredentials($this->user->getId(), Validation::generatePassword())); // Used for PW reset hash only
            } else {
                $this->user->setPassword(Validation::encryptCredentials($this->getData('username'), $password));
            }

            $this->user->setDateRegistered(Core::getCurrentDate());
            Repo::user()->add($this->user);
            $userId = $this->user->getId();

            if ($sendNotify) {
                // Send welcome email to user
                $mail = new MailTemplate('USER_REGISTER');
                $mail->setReplyTo($context->getData('contactEmail'), $context->getData('contactName'));
                $mail->assignParams(['recipientUsername' => $this->getData('username'), 'password' => $password, 'recipientName' => $this->user->getFullName()]);
                $mail->addRecipient($this->user->getEmail(), $this->user->getFullName());
                if (!$mail->send()) {
                    $notificationMgr = new NotificationManager();
                    $notificationMgr->createTrivialNotification($request->getUser()->getId(), PKPNotification::NOTIFICATION_TYPE_ERROR, ['contents' => __('email.compose.error')]);
                }
            }
        }

        $interestManager = new InterestManager();
        $interestManager->setInterestsForUser($this->user, $this->getData('interests'));

        return $this->user;
    }
}
