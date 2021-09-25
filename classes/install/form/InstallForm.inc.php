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
use PKP\facades\Locale;
use APP\install\Install;
use APP\template\TemplateManager;
use PKP\core\Core;

use PKP\core\PKPApplication;
use PKP\core\PKPString;
use PKP\install\Installer;
use PKP\xslt\XSLTransformer;

class InstallForm extends MaintenanceForm
{
    /** @var array locales supported by this system */
    public $supportedLocales;

    /** @var array locale completeness booleans */
    public $localesComplete;

    /** @var array client character sets supported by this system */
    public $supportedClientCharsets;

    /** @var array connection character sets supported by this system */
    public $supportedConnectionCharsets;

    /** @var array database character sets supported by this system */
    public $supportedDatabaseCharsets;

    /** @var array database drivers supported by this system */
    public $supportedDatabaseDrivers;

    /**
     * Constructor.
     *
     * @param PKPRequest $request
     */
    public function __construct($request)
    {
        parent::__construct($request, 'install/install.tpl');

        // FIXME Move the below options to an external configuration file?
        $this->supportedLocales = Locale::getAllLocales();
        $this->localesComplete = [];
        foreach ($this->supportedLocales as $key => $name) {
            $this->localesComplete[$key] = Locale::getLocaleMetadata($key)->isLocaleComplete ?? false;
        }

        $this->supportedClientCharsets = [
            'utf-8' => 'Unicode (UTF-8)',
            'iso-8859-1' => 'Western (ISO-8859-1)'
        ];

        $this->supportedConnectionCharsets = [
            '' => __('common.notApplicable'),
            'utf8' => 'Unicode (UTF-8)'
        ];

        $this->supportedDatabaseCharsets = [
            '' => __('common.notApplicable'),
            'utf8' => 'Unicode (UTF-8)'
        ];

        $this->supportedDatabaseDrivers = [
            // <adodb-driver> => array(<php-module>, <name>)
            'mysql' => ['mysql', 'MySQL'],
            'mysqli' => ['mysqli', 'MySQLi'],
            'postgres9' => ['pgsql', 'PostgreSQL'],
            'oracle' => ['oci8', 'Oracle'],
            'mssql' => ['mssql', 'MS SQL Server'],
            'fbsql' => ['fbsql', 'FrontBase'],
            'ibase' => ['ibase', 'Interbase'],
            'firebird' => ['ibase', 'Firebird'],
            'informix' => ['ifx', 'Informix'],
            'sybase' => ['sybase', 'Sybase'],
            'odbc' => ['odbc', 'ODBC'],
        ];

        // Validation checks for this form
        $form = $this;
        $this->addCheck(new \PKP\form\validation\FormValidatorInSet($this, 'locale', 'required', 'installer.form.localeRequired', array_keys($this->supportedLocales)));
        $this->addCheck(new \PKP\form\validation\FormValidatorCustom($this, 'locale', 'required', 'installer.form.localeRequired', function ($locale) {
            return Locale::isLocaleValid($locale);
        }));
        $this->addCheck(new \PKP\form\validation\FormValidatorInSet($this, 'clientCharset', 'required', 'installer.form.clientCharsetRequired', array_keys($this->supportedClientCharsets)));
        $this->addCheck(new \PKP\form\validation\FormValidator($this, 'filesDir', 'required', 'installer.form.filesDirRequired'));
        $this->addCheck(new \PKP\form\validation\FormValidator($this, 'adminUsername', 'required', 'installer.form.usernameRequired'));
        $this->addCheck(new \PKP\form\validation\FormValidatorUsername($this, 'adminUsername', 'required', 'installer.form.usernameAlphaNumeric'));
        $this->addCheck(new \PKP\form\validation\FormValidator($this, 'adminPassword', 'required', 'installer.form.passwordRequired'));
        $this->addCheck(new \PKP\form\validation\FormValidatorCustom($this, 'adminPassword', 'required', 'installer.form.passwordsDoNotMatch', function ($password) use ($form) {
            return $password == $form->getData('adminPassword2');
        }));
        $this->addCheck(new \PKP\form\validation\FormValidatorEmail($this, 'adminEmail', 'required', 'installer.form.emailRequired'));
        $this->addCheck(new \PKP\form\validation\FormValidatorInSet($this, 'databaseDriver', 'required', 'installer.form.databaseDriverRequired', array_keys($this->supportedDatabaseDrivers)));
        $this->addCheck(new \PKP\form\validation\FormValidator($this, 'databaseName', 'required', 'installer.form.databaseNameRequired'));
    }

    /**
     * @copydoc Form::display
     *
     * @param null|mixed $request
     * @param null|mixed $template
     */
    public function display($request = null, $template = null)
    {
        $templateMgr = TemplateManager::getManager($request);
        $templateMgr->assign([
            'localeOptions' => $this->supportedLocales,
            'localesComplete' => $this->localesComplete,
            'clientCharsetOptions' => $this->supportedClientCharsets,
            'connectionCharsetOptions' => $this->supportedConnectionCharsets,
            'allowFileUploads' => get_cfg_var('file_uploads') ? __('common.yes') : __('common.no'),
            'maxFileUploadSize' => get_cfg_var('upload_max_filesize'),
            'databaseDriverOptions' => $this->checkDBDrivers(),
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
            'locale' => Locale::getLocale(),
            'additionalLocales' => [],
            'clientCharset' => 'utf-8',
            'connectionCharset' => 'utf8',
            'filesDir' => $docRoot . 'files',
            'databaseDriver' => 'mysql',
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
            'locale',
            'additionalLocales',
            'clientCharset',
            'connectionCharset',
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
     * Check if database drivers have the required PHP module loaded.
     * The names of drivers that appear to be unavailable are bracketed.
     *
     * @return array
     */
    public function checkDBDrivers()
    {
        $dbDrivers = [];
        foreach ($this->supportedDatabaseDrivers as $driver => $info) {
            [$module, $name] = $info;
            if (!extension_loaded($module)) {
                $name = '[ ' . $name . ' ]';
            }
            $dbDrivers[$driver] = $name;
        }
        return $dbDrivers;
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\install\form\InstallForm', '\InstallForm');
}
