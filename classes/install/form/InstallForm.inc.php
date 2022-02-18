<?php

/**
 * @file classes/install/form/InstallForm.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class InstallForm
 * @ingroup install
 *
 * @see Install
 *
 * @brief Form for system installation.
 */

namespace PKP\install\form;

use APP\core\Application;
use APP\install\Install;
use APP\template\TemplateManager;
use DateTime;
use DateTimeZone;
use PKP\core\Core;
use PKP\core\PKPApplication;

use PKP\core\PKPString;
use PKP\facades\Locale;
use PKP\i18n\LocaleMetadata;
use PKP\install\Installer;
use PKP\xslt\XSLTransformer;

class InstallForm extends MaintenanceForm
{
    /** @var array locales supported by this system */
    public $supportedLocales;

    /** @var array locale completeness booleans */
    public $localesComplete;

    /** @var array database drivers supported by this system */
    public $supportedDatabaseDrivers = [
        // <driver> => array(<php-module>, <name>)
        'mysqli' => ['mysqli', 'MySQLi'],
        'postgres9' => ['pgsql', 'PostgreSQL'],
        'mysql' => ['mysql', 'MySQL']
    ];

    /**
     * Constructor.
     *
     * @param PKPRequest $request
     */
    public function __construct($request)
    {
        parent::__construct($request, 'install/install.tpl');

        $this->supportedLocales = array_map(fn(LocaleMetadata $locale) => $locale->getDisplayName(), Locale::getLocales());
        $this->localesComplete = array_map(fn (LocaleMetadata $locale) => $locale->isComplete(), Locale::getLocales());

        foreach ($this->supportedDatabaseDrivers as $driver => [$module]) {
            if (!extension_loaded($module)) {
                unset($this->supportedDatabaseDrivers[$driver]);
            }
        }

        // Validation checks for this form
        $form = $this;
        $this->addCheck(new \PKP\form\validation\FormValidatorInSet($this, 'locale', 'required', 'installer.form.localeRequired', array_keys($this->supportedLocales)));
        $this->addCheck(new \PKP\form\validation\FormValidatorCustom($this, 'locale', 'required', 'installer.form.localeRequired', fn(string $locale) => Locale::isLocaleValid($locale)));
        $this->addCheck(new \PKP\form\validation\FormValidator($this, 'filesDir', 'required', 'installer.form.filesDirRequired'));
        $this->addCheck(new \PKP\form\validation\FormValidator($this, 'adminUsername', 'required', 'installer.form.usernameRequired'));
        $this->addCheck(new \PKP\form\validation\FormValidatorUsername($this, 'adminUsername', 'required', 'installer.form.usernameAlphaNumeric'));
        $this->addCheck(new \PKP\form\validation\FormValidator($this, 'adminPassword', 'required', 'installer.form.passwordRequired'));
        $this->addCheck(new \PKP\form\validation\FormValidatorCustom($this, 'adminPassword', 'required', 'installer.form.passwordsDoNotMatch', fn(string $password) => $password == $form->getData('adminPassword2')));
        $this->addCheck(new \PKP\form\validation\FormValidatorEmail($this, 'adminEmail', 'required', 'installer.form.emailRequired'));
        $this->addCheck(new \PKP\form\validation\FormValidatorInSet($this, 'databaseDriver', 'required', 'installer.form.databaseDriverRequired', array_keys($this->supportedDatabaseDrivers)));
        $this->addCheck(new \PKP\form\validation\FormValidator($this, 'databaseName', 'required', 'installer.form.databaseNameRequired'));
        $this->addCheck(new \PKP\form\validation\FormValidatorInSet($this, 'timeZone', 'required', 'installer.form.timeZoneRequired', DateTimeZone::listIdentifiers()));
    }

    /**
     * @copydoc Form::display
     *
     * @param null|mixed $request
     * @param null|mixed $template
     */
    public function display($request = null, $template = null)
    {
        $timeZones = array_reduce(DateTimeZone::listIdentifiers(), function ($timeZones, $current) {
            if ($current !== 'UTC') {
                $time = (new DateTime('now', new DateTimeZone($current)))->format('P');
                $groups = explode('/', $current);
                $continent = array_shift($groups);
                $timeZone = str_replace('_', ' ', implode(' - ', $groups));
                $timeZones[$continent ?? $timeZone][$current] = $timeZone . " (${time})";
            }
            return $timeZones;
        }, ['UTC' => 'UTC']);

        $templateMgr = TemplateManager::getManager($request);
        $languages = array_map(fn (LocaleMetadata $locale) => $locale->getDisplayName($locale->locale), Locale::getLocales());
        asort($languages);
        $templateMgr->assign([
            'timeZoneOptions' => $timeZones,
            'languageOptions' => $languages,
            'localeOptions' => $this->supportedLocales,
            'localesComplete' => $this->localesComplete,
            'allowFileUploads' => ini_get('file_uploads') ? __('common.yes') : __('common.no'),
            'maxFileUploadSize' => ini_get('upload_max_filesize'),
            'databaseDriverOptions' => $this->getDatabaseDriversOptions(),
            'supportsMBString' => PKPString::hasMBString() ? __('common.yes') : __('common.no'),
            'phpIsSupportedVersion' => version_compare(PKPApplication::PHP_REQUIRED_VERSION, PHP_VERSION) != 1,
            'xslEnabled' => XSLTransformer::checkSupport(),
            'xslRequired' => REQUIRES_XSL,
            'phpRequiredVersion' => PKPApplication::PHP_REQUIRED_VERSION,
            'phpVersion' => PHP_VERSION,
        ]);

        parent::display($request, $template);
    }

    /**
     * @copydoc MaintenanceForm::initData
     */
    public function initData()
    {
        $docRoot = dirname($_SERVER['DOCUMENT_ROOT']);
        if (Core::isWindows()) {
            // Replace backslashes with slashes for the default files directory.
            $docRoot = str_replace('\\', '/', $docRoot);
        }

        // Add a trailing slash for paths that aren't filesystem root
        if ($docRoot !== '/') {
            $docRoot .= '/';
        }

        $this->_data = [
            'timeZone' => 'UTC',
            'locale' => Locale::getLocale(),
            'additionalLocales' => [],
            'filesDir' => $docRoot . 'files',
            'databaseDriver' => 'mysqli',
            'databaseHost' => 'localhost',
            'databaseUsername' => Application::getName(),
            'databasePassword' => '',
            'databaseName' => Application::getName(),
            'oaiRepositoryId' => Application::getName() . '.' . $this->_request->getServerHost(),
            'enableBeacon' => true,
        ];
    }

    /**
     * Assign form data to user-submitted data.
     */
    public function readInputData()
    {
        $this->readUserVars([
            'timeZone',
            'locale',
            'additionalLocales',
            'filesDir',
            'adminUsername',
            'adminPassword',
            'adminPassword2',
            'adminEmail',
            'databaseDriver',
            'databaseHost',
            'databaseUsername',
            'databasePassword',
            'databaseName',
            'oaiRepositoryId',
            'enableBeacon',
        ]);

        if ($this->getData('additionalLocales') == null || !is_array($this->getData('additionalLocales'))) {
            $this->setData('additionalLocales', []);
        }
    }

    /**
     * Perform installation.
     *
     * @param mixed[] ...$functionArgs Function arguments
     */
    public function execute(...$functionArgs)
    {
        parent::execute(...$functionArgs);

        $templateMgr = TemplateManager::getManager($this->_request);
        $installer = new Install($this->_data);

        if ($installer->execute()) {
            if (!$installer->wroteConfig()) {
                // Display config file contents for manual replacement
                $templateMgr->assign(['writeConfigFailed' => true, 'configFileContents' => $installer->getConfigContents()]);
            }

            $templateMgr->display('install/installComplete.tpl');
        } else {
            switch ($installer->getErrorType()) {
                case Installer::INSTALLER_ERROR_DB:
                    $this->dbInstallError($installer->getErrorMsg());
                    break;
                default:
                    $this->installError($installer->getErrorMsg());
                    break;
            }
        }

        $installer->destroy();
    }

    /**
     * Retrieve the available databases
     *
     * @return array
     */
    public function getDatabaseDriversOptions()
    {
        return array_map(fn($item) => $item[1], $this->supportedDatabaseDrivers);
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\install\form\InstallForm', '\InstallForm');
}
