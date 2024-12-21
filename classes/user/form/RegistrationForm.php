<?php
/**
 * @defgroup user_form User Forms
 */

/**
 * @file classes/user/form/RegistrationForm.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class RegistrationForm
 *
 * @ingroup user_form
 *
 * @brief Form for user registration.
 */

namespace PKP\user\form;

use APP\core\Application;
use APP\facades\Repo;
use APP\notification\form\NotificationSettingsForm;
use APP\template\TemplateManager;
use PKP\config\Config;
use PKP\core\Core;
use PKP\db\DAORegistry;
use PKP\facades\Locale;
use PKP\form\Form;
use PKP\orcid\OrcidManager;
use PKP\security\Role;
use PKP\security\Validation;
use PKP\site\Site;
use PKP\user\User;
use PKP\userGroup\UserGroup;

class RegistrationForm extends Form
{
    /** @var User The user object being created (available to hooks during registrationform::execute hook) */
    public $user;

    /** @var bool user is already registered with another context */
    public $existingUser;

    /** @var bool whether or not captcha is enabled for this form */
    public $captchaEnabled;

    /**
     * Constructor.
     *
     * @param Site $site
     */
    public function __construct($site)
    {
        parent::__construct('frontend/pages/userRegister.tpl');

        // Validation checks for this form
        $form = $this;
        $this->addCheck(new \PKP\form\validation\FormValidatorCustom($this, 'username', 'required', 'user.register.form.usernameExists', [Repo::user(), 'getByUsername'], [true], true));
        $this->addCheck(new \PKP\form\validation\FormValidator($this, 'username', 'required', 'user.profile.form.usernameRequired'));
        $this->addCheck(new \PKP\form\validation\FormValidator($this, 'password', 'required', 'user.profile.form.passwordRequired'));
        $this->addCheck(new \PKP\form\validation\FormValidatorUsername($this, 'username', 'required', 'user.register.form.usernameAlphaNumeric'));
        $this->addCheck(new \PKP\form\validation\FormValidatorLength($this, 'password', 'required', 'user.register.form.passwordLengthRestriction', '>=', $site->getMinPasswordLength()));
        $this->addCheck(new \PKP\form\validation\FormValidatorCustom($this, 'password', 'required', 'user.register.form.passwordsDoNotMatch', fn ($password) => $password == $form->getData('password2')));

        $this->addCheck(new \PKP\form\validation\FormValidator($this, 'givenName', 'required', 'user.profile.form.givenNameRequired'));

        $this->addCheck(new \PKP\form\validation\FormValidator($this, 'country', 'required', 'user.profile.form.countryRequired'));

        // Email checks
        $this->addCheck(new \PKP\form\validation\FormValidatorEmail($this, 'email', 'required', 'user.profile.form.emailRequired'));
        $this->addCheck(new \PKP\form\validation\FormValidatorCustom($this, 'email', 'required', 'user.register.form.emailExists', [Repo::user(), 'getByEmail'], [true], true));

        $this->captchaEnabled = Config::getVar('captcha', 'captcha_on_register') && Config::getVar('captcha', 'recaptcha');
        if ($this->captchaEnabled) {
            $request = Application::get()->getRequest();
            $this->addCheck(new \PKP\form\validation\FormValidatorReCaptcha($this, $request->getRemoteAddr(), 'common.captcha.error.invalid-input-response', $request->getServerHost()));
        }

        $context = Application::get()->getRequest()->getContext();
        if ($context && $context->getData('privacyStatement')) {
            $this->addCheck(new \PKP\form\validation\FormValidator($this, 'privacyConsent', 'required', 'user.profile.form.privacyConsentRequired'));
        }

        $this->addCheck(new \PKP\form\validation\FormValidatorPost($this));
        $this->addCheck(new \PKP\form\validation\FormValidatorCSRF($this));
    }

    /**
     * @copydoc Form::fetch()
     *
     * @param null|mixed $template
     */
    public function fetch($request, $template = null, $display = false)
    {
        $templateMgr = TemplateManager::getManager($request);
        $site = $request->getSite();

        if ($this->captchaEnabled) {
            $templateMgr->assign('recaptchaPublicKey', Config::getVar('captcha', 'recaptcha_public_key'));
        }

        $countries = [];
        foreach (Locale::getCountries() as $country) {
            $countries[$country->getAlpha2()] = $country->getLocalName();
        }
        asort($countries);
        $templateMgr->assign('countries', $countries);

        $userFormHelper = new UserFormHelper();
        $userFormHelper->assignRoleContent($templateMgr, $request);

        $templateMgr->assign([
            'source' => $request->getUserVar('source'),
            'minPasswordLength' => $site->getMinPasswordLength(),
            'enableSiteWidePrivacyStatement' => Config::getVar('general', 'sitewide_privacy_statement'),
            'siteWidePrivacyStatement' => $site->getData('privacyStatement'),
        ]);

        // FIXME: ORCID OAuth assumes a context so ORCID profile information cannot be filled from the site index
        //        registration page.
        if ($request->getContext() !== null && OrcidManager::isEnabled()) {
            $targetOp = 'register';
            $templateMgr->assign([
                'orcidEnabled' => true,
                'targetOp' => $targetOp,
                'orcidUrl' => OrcidManager::getOrcidUrl(),
                'orcidOAuthUrl' => OrcidManager::buildOAuthUrl('authorizeOrcid', ['targetOp' => $targetOp]),
                'orcidIcon' => OrcidManager::getIcon(),
            ]);
        } else {
            $templateMgr->assign([
                'orcidEnabled' => false,
            ]);
        }

        return parent::fetch($request, $template, $display);
    }

    /**
     * @copydoc Form::initData()
     */
    public function initData()
    {
        $this->_data = [
            'locales' => [],
            'userGroupIds' => [],
        ];
    }

    /**
     * Assign form data to user-submitted data.
     */
    public function readInputData()
    {
        parent::readInputData();

        $this->readUserVars([
            'username',
            'password',
            'password2',
            'givenName',
            'familyName',
            'affiliation',
            'email',
            'country',
            'interests',
            'emailConsent',
            'orcid',
            'privacyConsent',
            'readerGroup',
            'reviewerGroup',
        ]);

        if ($this->captchaEnabled) {
            $this->readUserVars([
                'g-recaptcha-response',
            ]);
        }

        // Collect the specified user group IDs into a single piece of data
        $this->setData('userGroupIds', array_merge(
            array_keys((array) $this->getData('readerGroup')),
            array_keys((array) $this->getData('reviewerGroup'))
        ));
    }

    /**
     * @copydoc Form::validate()
     */
    public function validate($callHooks = true)
    {
        $request = Application::get()->getRequest();

        // Ensure the consent checkbox has been completed for the site and any user
        // group sign-ups if we're in the site-wide registration form
        if (!$request->getContext()) {
            if ($request->getSite()->getData('privacyStatement')) {
                $privacyConsent = $this->getData('privacyConsent');
                if (!is_array($privacyConsent) || !array_key_exists(intval(Application::SITE_CONTEXT_ID), $privacyConsent)) {
                    $this->addError('privacyConsent[' . intval(Application::SITE_CONTEXT_ID) . ']', __('user.register.form.missingSiteConsent'));
                }
            }

            if (!Config::getVar('general', 'sitewide_privacy_statement')) {
                $userGroupIds = $this->getData('userGroupIds');

                // Fetch all user groups in a single query
                $userGroups = UserGroup::query()->withUserGroupIds($userGroupIds)->get();

                // Collect context IDs using the 'map' method
                $contextIds = $userGroups->map(function ($userGroup) {
                    return $userGroup->contextId;
                })->unique()->toArray();

                if (!empty($contextIds)) {
                    $contextDao = Application::getContextDao();
                    $privacyConsent = (array) $this->getData('privacyConsent');
                    foreach ($contextIds as $contextId) {
                        $context = $contextDao->getById($contextId);
                        if ($context->getData('privacyStatement') && !array_key_exists($contextId, $privacyConsent)) {
                            $this->addError('privacyConsent[' . $contextId . ']', __('user.register.form.missingContextConsent'));
                            break;
                        }
                    }
                }
            }
        }

        return parent::validate($callHooks);
    }

    /**
     * Register a new user.
     *
     * @return int|null User ID, or false on failure
     */
    public function execute(...$functionArgs)
    {
        $requireValidation = Config::getVar('email', 'require_validation');

        // New user
        $this->user = $user = Repo::user()->newDataObject();

        $user->setUsername($this->getData('username'));

        // The multilingual user data (givenName, familyName and affiliation) will be saved
        // in the current UI locale and copied in the site's primary locale too
        $request = Application::get()->getRequest();
        $site = $request->getSite();
        $sitePrimaryLocale = $site->getPrimaryLocale();
        $currentLocale = Locale::getLocale();

        // Set the base user fields (name, etc.)
        $user->setGivenName($this->getData('givenName'), $currentLocale);
        $user->setFamilyName($this->getData('familyName'), $currentLocale);
        $user->setEmail($this->getData('email'));
        $user->setCountry($this->getData('country'));
        $user->setAffiliation($this->getData('affiliation'), $currentLocale);

        // FIXME: ORCID OAuth and assignment to users assumes a context so we currently ignore
        //        ability to assign ORCIDs at the site-level registration page
        if ($request->getContext() !== null && OrcidManager::isEnabled()) {
            $user->setOrcid($this->getData('orcid'));
        }

        if ($sitePrimaryLocale != $currentLocale) {
            $user->setGivenName($this->getData('givenName'), $sitePrimaryLocale);
            $user->setFamilyName($this->getData('familyName'), $sitePrimaryLocale);
            $user->setAffiliation($this->getData('affiliation'), $sitePrimaryLocale);
        }

        $user->setDateRegistered(Core::getCurrentDate());
        $user->setInlineHelp(1); // default new users to having inline help visible.
        $user->setPassword(Validation::encryptCredentials($this->getData('username'), $this->getData('password')));

        if ($requireValidation) {
            // The account should be created in a disabled
            // state.
            $user->setDisabled(true);
            $user->setDisabledReason(__('user.login.accountNotValidated', ['email' => $this->getData('email')]));
        }

        parent::execute(...$functionArgs);

        Repo::user()->add($user);
        $userId = $user->getId();
        if (!$userId) {
            return false;
        }

        $request->getSession()->put('username', $user->getUsername());
        $request->getSessionGuard()->updateSession($user->getId());

        // Save the selected roles or assign the Reader role if none selected
        if ($request->getContext() && !$this->getData('reviewerGroup')) {
            $defaultReaderGroup = Repo::userGroup()->getByRoleIds([Role::ROLE_ID_READER], $request->getContext()->getId(), true)->first();
            if ($defaultReaderGroup) {
                Repo::userGroup()->assignUserToGroup($user->getId(), $defaultReaderGroup->id);
            }
        } else {
            $userFormHelper = new UserFormHelper();
            $userFormHelper->saveRoleContent($this, $user);
        }

        // Save the email notification preference
        if ($request->getContext() && !$this->getData('emailConsent')) {
            // Get the public notification types
            $notificationSettingsForm = new NotificationSettingsForm();
            $notificationCategories = $notificationSettingsForm->getNotificationSettingCategories($request->getContext());
            foreach ($notificationCategories as $notificationCategory) {
                if ($notificationCategory['categoryKey'] === 'notification.type.public') {
                    $publicNotifications = $notificationCategory['settings'];
                }
            }
            if (isset($publicNotifications)) {
                $notificationSubscriptionSettingsDao = DAORegistry::getDAO('NotificationSubscriptionSettingsDAO'); /** @var NotificationSubscriptionSettingsDAO $notificationSubscriptionSettingsDao */
                $notificationSubscriptionSettingsDao->updateNotificationSubscriptionSettings(
                    'blocked_emailed_notification',
                    $publicNotifications,
                    $user->getId(),
                    $request->getContext()->getId()
                );
            }
        }

        // Insert the user interests
        Repo::userInterest()->setInterestsForUser($user, $this->getData('interests'));

        return $userId;
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\user\form\RegistrationForm', '\RegistrationForm');
}
