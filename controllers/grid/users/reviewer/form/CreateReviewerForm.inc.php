<?php

/**
 * @file controllers/grid/users/reviewer/form/CreateReviewerForm.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class CreateReviewerForm
 * @ingroup controllers_grid_users_reviewer_form
 *
 * @brief Form for creating and subsequently adding a reviewer to a submission.
 */

import('lib.pkp.controllers.grid.users.reviewer.form.ReviewerForm');

use APP\facades\Repo;
use APP\notification\NotificationManager;
use APP\template\TemplateManager;
use PKP\mail\MailTemplate;
use PKP\notification\PKPNotification;
use PKP\user\InterestManager;
use PKP\facades\Locale;

class CreateReviewerForm extends ReviewerForm
{
    /**
     * Constructor.
     *
     * @param Submission $submission
     * @param ReviewRound $reviewRound
     */
    public function __construct($submission, $reviewRound)
    {
        parent::__construct($submission, $reviewRound);
        $this->setTemplate('controllers/grid/users/reviewer/form/createReviewerForm.tpl');

        // the users register for the site, thus
        // the site primary locale is the required default locale
        $site = Application::get()->getRequest()->getSite();
        $this->addSupportedFormLocale($site->getPrimaryLocale());

        $form = $this;
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
        $this->addCheck(new \PKP\form\validation\FormValidatorCustom($this, 'username', 'required', 'user.register.form.usernameExists', [Repo::user(), 'getByUsername'], [true], true));
        $this->addCheck(new \PKP\form\validation\FormValidatorUsername($this, 'username', 'required', 'user.register.form.usernameAlphaNumeric'));
        $this->addCheck(new \PKP\form\validation\FormValidatorEmail($this, 'email', 'required', 'user.profile.form.emailRequired'));
        $this->addCheck(new \PKP\form\validation\FormValidatorCustom($this, 'email', 'required', 'user.register.form.emailExists', function ($email) {
            return !Repo::user()->getByEmail($email, true);
        }));
        $this->addCheck(new \PKP\form\validation\FormValidator($this, 'userGroupId', 'required', 'user.profile.form.usergroupRequired'));
        $this->addCheck(new \PKP\form\validation\FormValidator($this, 'preferredLanguage', 'required', 'user.profile.form.preferrendLanguageRequired'));
    }


    /**
     * @copydoc ReviewerForm::fetch
     *
     * @param null|mixed $template
     */
    public function fetch($request, $template = null, $display = false)
    {
        $advancedSearchAction = $this->getAdvancedSearchAction($request);
        $this->setReviewerFormAction($advancedSearchAction);
        $site = $request->getSite();
        $templateMgr = TemplateManager::getManager($request);
        $requestPreferredLanguage = $request->getPreferredLanguage();

        $templateMgr->assign(['sitePrimaryLocale', $site->getPrimaryLocale(),
            'availableLocales' => $site->getSupportedLocaleNames(),
            'locales' => [$requestPreferredLanguage],
            'preferredLanguage' => $requestPreferredLanguage,
            ]);

        return parent::fetch($request, $template, $display);
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
            'givenName',
            'familyName',
            'affiliation',
            'interests',
            'username',
            'email',
            'skipEmail',
            'userGroupId',
            'locales',
            'preferredLanguage'
        ]);

        if ($this->getData('locales') == null || !is_array($this->getData('locales'))) {
            $this->setData('locales', []);
        }
    }


    /**
     * @copydoc Form::validate()
     */
    public function validate($callHooks = true)
    {
        $request = Application::get()->getRequest();
        //Ensure a preferredLanguage has been selected and is valid, also locales are valid
        $site = $request->getSite();
        $availableLocales = $site->getSupportedLocales();
        if ( ! (Locale::isLocaleValid($this->getData('preferredLanguage')) &&
                in_array($this->getData('preferredLanguage'), $availableLocales)) ) {
            $this->addError('preferredLanguage', __('user.register.form.badLocale'));
        }

        foreach ($this->getData('locales') as $locale) {
            if (! (Locale::isLocaleValid($locale) && in_array($locale, $availableLocales)) ) {
                $this->addError('locales', __('user.register.form.badLocale'));
            }
        }
        return parent::validate($callHooks);
    }


    /**
     * @copydoc Form::execute()
     */
    public function execute(...$functionArgs)
    {
        $user = Repo::user()->newDataObject();

        $user->setGivenName($this->getData('givenName'), null);
        $user->setFamilyName($this->getData('familyName'), null);
        $user->setEmail($this->getData('email'));
        $user->setAffiliation($this->getData('affiliation'), null); // Localized

        $authDao = DAORegistry::getDAO('AuthSourceDAO'); /** @var AuthSourceDAO $authDao */
        $auth = $authDao->getDefaultPlugin();
        $user->setAuthId($auth ? $auth->getAuthId() : 0);
        $user->setInlineHelp(1); // default new reviewers to having inline help visible

        $user->setUsername($this->getData('username'));
        $password = Validation::generatePassword();

        if (isset($auth)) {
            $user->setPassword($password);
            // FIXME Check result and handle failures
            $auth->doCreateUser($user);
            $user->setAuthId($auth->authId);
            $user->setPassword(Validation::encryptCredentials($user->getId(), Validation::generatePassword())); // Used for PW reset hash only
        } else {
            $user->setPassword(Validation::encryptCredentials($this->getData('username'), $password));
        }
        $user->setMustChangePassword(true); // Emailed P/W not safe
        $user->setPreferredLanguage($this->getData('preferredLanguage'));
        $request = Application::get()->getRequest();
        $site = $request->getSite();

        $availableLocales = $site->getSupportedLocales();
        $locales = [];
        foreach ($this->getData('locales') as $locale) {
            if (Locale::isLocaleValid($locale) && in_array($locale, $availableLocales)) {
                array_push($locales, $locale);
            }
        }
        $user->setLocales($locales);

        $user->setDateRegistered(Core::getCurrentDate());
        $reviewerId = Repo::user()->add($user);

        // Set the reviewerId in the Form for the parent class to use
        $this->setData('reviewerId', $reviewerId);

        // Insert the user interests
        $interestManager = new InterestManager();
        $interestManager->setInterestsForUser($user, $this->getData('interests'));

        // Assign the selected user group ID to the user
        $userGroupDao = DAORegistry::getDAO('UserGroupDAO'); /** @var UserGroupDAO $userGroupDao */
        $userGroupId = (int) $this->getData('userGroupId');
        $userGroupDao->assignUserToGroup($reviewerId, $userGroupId);

        if (!$this->getData('skipEmail')) {
            // Send welcome email to user
            $mail = new MailTemplate('REVIEWER_REGISTER');
            if ($mail->isEnabled()) {
                $request = Application::get()->getRequest();
                $context = $request->getContext();
                $mail->setReplyTo($context->getData('contactEmail'), $context->getData('contactName'));
                $mail->assignParams(['recipientUsername' => $this->getData('username'), 'password' => $password, 'recipientName' => $user->getFullName()]);
                $mail->addRecipient($user->getEmail(), $user->getFullName());
                if (!$mail->send($request)) {
                    $notificationMgr = new NotificationManager();
                    $notificationMgr->createTrivialNotification($request->getUser()->getId(), PKPNotification::NOTIFICATION_TYPE_ERROR, ['contents' => __('email.compose.error')]);
                }
            }
        }

        return parent::execute(...$functionArgs);
    }
}
