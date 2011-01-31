<?php

/**
 * @file classes/install/Installer.inc.php
 *
 * Copyright (c) 2000-2011 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class Installer
 * @ingroup install
 *
 * @brief Base class for install and upgrade scripts.
 */


// Database installation files
define('INSTALLER_DATA_DIR', 'dbscripts/xml');

// Installer error codes
define('INSTALLER_ERROR_GENERAL', 1);
define('INSTALLER_ERROR_DB', 2);

// Default data
define('INSTALLER_DEFAULT_LOCALE', 'en_US');

import('lib.pkp.classes.db.DBDataXMLParser');
import('lib.pkp.classes.site.Version');
import('lib.pkp.classes.site.VersionDAO');
import('lib.pkp.classes.config.ConfigParser');

require_once './lib/pkp/lib/adodb/adodb-xmlschema.inc.php';

class Installer {

	/** @var string descriptor path (relative to INSTALLER_DATA_DIR) */
	var $descriptor;

	/** @var boolean indicates if a plugin is being installed (thus modifying the descriptor path) */
	var $isPlugin;

	/** @var array installation parameters */
	var $params;

	/** @var Version currently installed version */
	var $currentVersion;

	/** @var Version version after installation */
	var $newVersion;

	/** @var ADOConnection database connection */
	var $dbconn;

	/** @var string default locale */
	var $locale;

	/** @var string available locales */
	var $installedLocales;

	/** @var DBDataXMLParser database data parser */
	var $dataXMLParser;

	/** @var array installer actions to be performed */
	var $actions;

	/** @var array SQL statements for database installation */
	var $sql;

	/** @var array installation notes */
	var $notes;

	/** @var string contents of the updated config file */
	var $configContents;

	/** @var boolean indicating if config file was written or not */
	var $wroteConfig;

	/** @var int error code (null | INSTALLER_ERROR_GENERAL | INSTALLER_ERROR_DB) */
	var $errorType;

	/** @var string the error message, if an installation error has occurred */
	var $errorMsg;

	/** @var Logger logging object */
	var $logger;


	/**
	 * Constructor.
	 * @param $descriptor string descriptor path
	 * @param $params array installer parameters
	 * @param $isPlugin boolean true iff a plugin is being installed
	 */
	function Installer($descriptor, $params = array(), $isPlugin = false) {
		// Load all plugins. If any of them use installer hooks,
		// they'll need to be loaded here.
		PluginRegistry::loadAllPlugins();
		$this->isPlugin = $isPlugin;

		// Give the HookRegistry the opportunity to override this
		// method or alter its parameters.
		if (!HookRegistry::call('Installer::Installer', array(&$this, &$descriptor, &$params))) {
			$this->descriptor = $descriptor;
			$this->params = $params;
			$this->actions = array();
			$this->sql = array();
			$this->notes = array();
			$this->wroteConfig = true;
		}
	}

	/**
	 * Returns true iff this is an upgrade process.
	 */
	function isUpgrade() {
		die ('ABSTRACT CLASS');
	}

	/**
	 * Destroy / clean-up after the installer.
	 */
	function destroy() {
		if (isset($this->dataXMLParser)) {
			$this->dataXMLParser->destroy();
		}

		HookRegistry::call('Installer::destroy', array(&$this));
	}

	/**
	 * Pre-installation.
	 * @return boolean
	 */
	function preInstall() {
		$this->log('pre-install');
		if (!isset($this->dbconn)) {
			// Connect to the database.
			$conn =& DBConnection::getInstance();
			$this->dbconn =& $conn->getDBConn();

			if (!$conn->isConnected()) {
				$this->setError(INSTALLER_ERROR_DB, $this->dbconn->errorMsg());
				return false;
			}
		}

		if (!isset($this->currentVersion)) {
			// Retrieve the currently installed version
			$versionDao =& DAORegistry::getDAO('VersionDAO');
			$this->currentVersion =& $versionDao->getCurrentVersion();
		}

		if (!isset($this->locale)) {
			$this->locale = Locale::getLocale();
		}

		if (!isset($this->installedLocales)) {
			$this->installedLocales = array_keys(Locale::getAllLocales());
		}

		if (!isset($this->dataXMLParser)) {
			$this->dataXMLParser = new DBDataXMLParser();
			$this->dataXMLParser->setDBConn($this->dbconn);
		}

		$result = true;
		HookRegistry::call('Installer::preInstall', array(&$this, &$result));

		return $result;
	}

	/**
	 * Installation.
	 * @return boolean
	 */
	function execute() {
		// Ensure that the installation will not get interrupted if it takes
		// longer than max_execution_time (php.ini). Note that this does not
		// work under safe mode.
		@set_time_limit (0);

		if (!$this->preInstall()) {
			return false;
		}

		if (!$this->parseInstaller()) {
			return false;
		}

		if (!$this->executeInstaller()) {
			return false;
		}

		if (!$this->postInstall()) {
			return false;
		}

		return $this->updateVersion();
	}

	/**
	 * Post-installation.
	 * @return boolean
	 */
	function postInstall() {
		$this->log('post-install');
		$result = true;
		HookRegistry::call('Installer::postInstall', array(&$this, &$result));

		// Inform users that they'll have to run the update script
		// after doing a manual installation.
		if ($this->getParam('manualInstall')) {
			$this->log(Locale::translate('installer.pleaseUpgradeAfterManualInstall'));
		}

		return $result;
	}


	/**
	 * Record message to installation log.
	 * @var $message string
	 */
	function log($message) {
		if (isset($this->logger)) {
			call_user_func(array($this->logger, 'log'), $message);
		}
	}


	//
	// Main actions
	//

	/**
	 * Parse the installation descriptor XML file.
	 * @return boolean
	 */
	function parseInstaller() {
		// Read installation descriptor file
		$this->log(sprintf('load: %s', $this->descriptor));
		$xmlParser = new XMLParser();
		$installPath = $this->isPlugin ? $this->descriptor : INSTALLER_DATA_DIR . DIRECTORY_SEPARATOR . $this->descriptor;
		$installTree = $xmlParser->parse($installPath);
		if (!$installTree) {
			// Error reading installation file
			$xmlParser->destroy();
			$this->setError(INSTALLER_ERROR_GENERAL, 'installer.installFileError');
			return false;
		}

		$versionString = $installTree->getAttribute('version');
		if (isset($versionString)) {
			$this->newVersion =& Version::fromString($versionString);
		} else {
			$this->newVersion = $this->currentVersion;
		}

		// Parse descriptor
		$this->parseInstallNodes($installTree);
		$xmlParser->destroy();

		$result = $this->getErrorType() == 0;

		HookRegistry::call('Installer::parseInstaller', array(&$this, &$result));
		return $result;
	}

	/**
	 * Execute the installer actions.
	 * @return boolean
	 */
	function executeInstaller() {
		$this->log(sprintf('version: %s', $this->newVersion->getVersionString()));
		foreach ($this->actions as $action) {
			if (!$this->executeAction($action)) {
				return false;
			}
		}

		$result = true;
		HookRegistry::call('Installer::executeInstaller', array(&$this, &$result));

		return $result;
	}

	/**
	 * Update the version number.
	 * @return boolean
	 */
	function updateVersion() {
		if ($this->newVersion->compare($this->currentVersion) > 0) {
			if ($this->getParam('manualInstall')) {
				// FIXME Would be better to have a mode where $dbconn->execute() saves the query
				return $this->executeSQL(sprintf('INSERT INTO versions (major, minor, revision, build, date_installed, current, product_type, product, product_class_name, lazy_load, sitewide) VALUES (%d, %d, %d, %d, NOW(), 1, %s, %s, %s, %d, %d)', $this->newVersion->getMajor(), $this->newVersion->getMinor(), $this->newVersion->getRevision(), $this->newVersion->getBuild(), $this->dbconn->qstr($this->newVersion->getProductType()), $this->dbconn->qstr($this->newVersion->getProduct()), $this->dbconn->qstr($this->newVersion->getProductClassName()), ($this->newVersion->getLazyLoad()?1:0), ($this->newVersion->getSitewide()?1:0)));
			} else {
				$versionDao =& DAORegistry::getDAO('VersionDAO');
				if (!$versionDao->insertVersion($this->newVersion)) {
					return false;
				}
			}
		}

		$result = true;
		HookRegistry::call('Installer::updateVersion', array(&$this, &$result));

		return $result;
	}


	//
	// Installer Parsing
	//

	/**
	 * Parse children nodes in the install descriptor.
	 * @param $installTree XMLNode
	 */
	function parseInstallNodes(&$installTree) {
		foreach ($installTree->getChildren() as $node) {
			switch ($node->getName()) {
				case 'schema':
				case 'data':
				case 'code':
				case 'note':
					$this->addInstallAction($node);
					break;
				case 'upgrade':
					$minVersion = $node->getAttribute('minversion');
					$maxVersion = $node->getAttribute('maxversion');
					if ((!isset($minVersion) || $this->currentVersion->compare($minVersion) >= 0) && (!isset($maxVersion) || $this->currentVersion->compare($maxVersion) <= 0)) {
						$this->parseInstallNodes($node);
					}
					break;
			}
		}
	}

	/**
	 * Add an installer action from the descriptor.
	 * @param $node XMLNode
	 */
	function addInstallAction(&$node) {
		$fileName = $node->getAttribute('file');

		if (!isset($fileName)) {
			$this->actions[] = array('type' => $node->getName(), 'file' => null, 'attr' => $node->getAttributes());

		} else if (strstr($fileName, '{$installedLocale}')) {
			// Filename substitution for locales
			foreach ($this->installedLocales as $thisLocale) {
				$newFileName = str_replace('{$installedLocale}', $thisLocale, $fileName);
				$this->actions[] = array('type' => $node->getName(), 'file' => $newFileName, 'attr' => $node->getAttributes());
			}

		} else {
			$newFileName = str_replace('{$locale}', $this->locale, $fileName);
			if (!file_exists($newFileName)) {
				// Use version from default locale if data file is not available in the selected locale
				$newFileName = str_replace('{$locale}', INSTALLER_DEFAULT_LOCALE, $fileName);
			}

			$this->actions[] = array('type' => $node->getName(), 'file' => $newFileName, 'attr' => $node->getAttributes());
		}
	}


	//
	// Installer Execution
	//

	/**
	 * Execute a single installer action.
	 * @param $action array
	 * @return boolean
	 */
	function executeAction($action) {
		switch ($action['type']) {
			case 'schema':
				$fileName = $action['file'];
				$this->log(sprintf('schema: %s', $action['file']));

				require_once './lib/pkp/lib/adodb/adodb-xmlschema.inc.php';
				$schemaXMLParser = new adoSchema($this->dbconn);
				$dict =& $schemaXMLParser->dict;
				$dict->SetCharSet($this->dbconn->charSet);
				$sql = $schemaXMLParser->parseSchema($fileName);
				$schemaXMLParser->destroy();

				if ($sql) {
					return $this->executeSQL($sql);
				} else {
					$this->setError(INSTALLER_ERROR_DB, str_replace('{$file}', $fileName, Locale::translate('installer.installParseDBFileError')));
					return false;
				}
				break;
			case 'data':
				$fileName = $action['file'];
				$condition = isset($action['attr']['condition'])?$action['attr']['condition']:null;
				$includeAction = true;
				if ($condition) {
					$funcName = create_function('$installer,$action', $condition);
					$includeAction = $funcName($this, $action);
				}
				$this->log('data: ' . $action['file'] . ($includeAction?'':' (skipped)'));
				if (!$includeAction) break;

				$sql = $this->dataXMLParser->parseData($fileName);
				// We might get an empty SQL if the upgrade script has
				// been executed before.
				if ($sql) {
					return $this->executeSQL($sql);
				}
				break;
			case 'code':
				$this->log(sprintf('code: %s %s::%s', isset($action['file']) ? $action['file'] : 'Installer', isset($action['attr']['class']) ? $action['attr']['class'] : 'Installer', $action['attr']['function']));
				// FIXME Don't execute code with "manual install" ???
				if (isset($action['file'])) {
					require_once($action['file']);
				}
				if (isset($action['attr']['class'])) {
					return call_user_func(array($action['attr']['class'], $action['attr']['function']), $this, $action['attr']);
				} else {
					return call_user_func(array(&$this, $action['attr']['function']), $this, $action['attr']);
				}
				break;
			case 'note':
				$this->log(sprintf('note: %s', $action['file']));
				$this->notes[] = join('', file($action['file']));
				break;
		}

		return true;
	}

	/**
	 * Execute an SQL statement.
	 * @var $sql mixed
	 * @return boolean
	 */
	function executeSQL($sql) {
		if (is_array($sql)) {
			foreach($sql as $stmt) {
				if (!$this->executeSQL($stmt)) {
					return false;
				}
			}
		} else {
			if ($this->getParam('manualInstall')) {
				$this->sql[] = $sql;

			} else {
				$this->dbconn->execute($sql);
				if ($this->dbconn->errorNo() != 0) {
					$this->setError(INSTALLER_ERROR_DB, $this->dbconn->errorMsg());
					return false;
				}
			}
		}

		return true;
	}

	/**
	 * Update the specified configuration parameters.
	 * @param $configParams arrays
	 * @return boolean
	 */
	function updateConfig($configParams) {
		// Update config file
		$configParser = new ConfigParser();
		if (!$configParser->updateConfig(Config::getConfigFileName(), $configParams)) {
			// Error reading config file
			$this->setError(INSTALLER_ERROR_GENERAL, 'installer.configFileError');
			return false;
		}

		$this->configContents = $configParser->getFileContents();
		if (!$configParser->writeConfig(Config::getConfigFileName())) {
			$this->wroteConfig = false;
		}

		return true;
	}


	//
	// Accessors
	//

	/**
	 * Get the value of an installation parameter.
	 * @param $name
	 * @return mixed
	 */
	function getParam($name) {
		return isset($this->params[$name]) ? $this->params[$name] : null;
	}

	/**
	 * Return currently installed version.
	 * @return Version
	 */
	function &getCurrentVersion() {
		return $this->currentVersion;
	}

	/**
	 * Return new version after installation.
	 * @return Version
	 */
	function &getNewVersion() {
		return $this->newVersion;
	}

	/**
	 * Get the set of SQL statements required to perform the install.
	 * @return array
	 */
	function getSQL() {
		return $this->sql;
	}

	/**
	 * Get the set of installation notes.
	 * @return array
	 */
	function getNotes() {
		return $this->notes;
	}

	/**
	 * Get the contents of the updated configuration file.
	 * @return string
	 */
	function getConfigContents() {
		return $this->configContents;
	}

	/**
	 * Check if installer was able to write out new config file.
	 * @return boolean
	 */
	function wroteConfig() {
		return $this->wroteConfig;
	}

	/**
	 * Return the error code.
	 * Valid return values are:
	 *   - 0 = no error
	 *   - INSTALLER_ERROR_GENERAL = general installation error.
	 *   - INSTALLER_ERROR_DB = database installation error
	 * @return int
	 */
	function getErrorType() {
		return isset($this->errorType) ? $this->errorType : 0;
	}

	/**
	 * The error message, if an error has occurred.
	 * In the case of a database error, an unlocalized string containing the error message is returned.
	 * For any other error, a localization key for the error message is returned.
	 * @return string
	 */
	function getErrorMsg() {
		return $this->errorMsg;
	}

	/**
	 * Return the error message as a localized string.
	 * @return string.
	 */
	function getErrorString() {
		switch ($this->getErrorType()) {
			case INSTALLER_ERROR_DB:
				return 'DB: ' . $this->getErrorMsg();
			default:
				return Locale::translate($this->getErrorMsg());
		}
	}

	/**
	 * Set the error type and messgae.
	 * @param $type int
	 * @param $msg string
	 */
	function setError($type, $msg) {
		$this->errorType = $type;
		$this->errorMsg = $msg;
	}

	/**
	 * Set the logger for this installer.
	 * @var $logger Logger
	 */
	function setLogger(&$logger) {
		$this->logger = $logger;
	}

	/**
	 * Clear the data cache files (needed because of direct tinkering
	 * with settings tables)
	 * @return boolean
	 */
	function clearDataCache() {
		$cacheManager =& CacheManager::getManager();
		$cacheManager->flush(null, CACHE_TYPE_FILE);
		$cacheManager->flush(null, CACHE_TYPE_OBJECT);
		return true;
	}

	/**
	 * Set the current version for this installer.
	 * @var $version Version
	 */
	function setCurrentVersion(&$version) {
		$this->currentVersion = $version;
	}

	/**
	 * For upgrade: install email templates and data
	 * @param $installer object
	 * @param $attr array Attributes: array containing
	 * 		'key' => 'EMAIL_KEY_HERE',
	 * 		'locales' => 'en_US,fr_CA,...'
	 */
	function installEmailTemplate($installer, $attr) {
		$emailTemplateDao =& DAORegistry::getDAO('EmailTemplateDAO');
		$emailTemplateDao->installEmailTemplates($emailTemplateDao->getMainEmailTemplatesFilename(), false, $attr['key']);
		foreach (explode(',', $attr['locales']) as $locale) {
			$emailTemplateDao->installEmailTemplateData($emailTemplateDao->getMainEmailTemplateDataFilename($locale), false, $attr['key']);
		}
		return true;
	}


	/**
	 * Installs filter template entries into the filters table.
	 * FIXME: Move this to plug-in installation when moving filters to plug-ins, see #5157.
	 */
	function installFilterTemplates() {
		// Filters are supported on PHP5+ only.
		if (!checkPhpVersion('5.0.0')) return true;

		$filterDao =& DAORegistry::getDAO('FilterDAO');
		$filtersToBeInstalled = array(
			'lib.pkp.classes.citation.lookup.crossref.CrossrefNlmCitationSchemaFilter',
			'lib.pkp.classes.citation.lookup.pubmed.PubmedNlmCitationSchemaFilter',
			'lib.pkp.classes.citation.lookup.worldcat.WorldcatNlmCitationSchemaFilter',
			'lib.pkp.classes.citation.parser.freecite.FreeciteRawCitationNlmCitationSchemaFilter',
			'lib.pkp.classes.citation.parser.paracite.ParaciteRawCitationNlmCitationSchemaFilter',
			'lib.pkp.classes.citation.parser.parscit.ParscitRawCitationNlmCitationSchemaFilter',
			'lib.pkp.classes.citation.parser.regex.RegexRawCitationNlmCitationSchemaFilter',
			'lib.pkp.classes.citation.output.abnt.NlmCitationSchemaAbntFilter',
			'lib.pkp.classes.citation.output.apa.NlmCitationSchemaApaFilter',
			'lib.pkp.classes.citation.output.mla.NlmCitationSchemaMlaFilter',
			'lib.pkp.classes.citation.output.vancouver.NlmCitationSchemaVancouverFilter',
			'lib.pkp.classes.importexport.nlm.PKPSubmissionNlmXmlFilter'
		);
		import('lib.pkp.classes.citation.output.PlainTextReferencesListFilter');
		foreach($filtersToBeInstalled as $filterToBeInstalled) {
			// Instantiate filter.
			$filter =& instantiate($filterToBeInstalled, 'Filter');

			// Install citation output filters as non-configurable site-wide filter instances.
			if (is_a($filter, 'NlmCitationSchemaCitationOutputFormatFilter') ||
					is_a($filter, 'PKPSubmissionNlmXmlFilter')) {
				$filter->setIsTemplate(false);

				// Check whether the filter instance has been
				// installed before.
				$existingFilters =& $filterDao->getObjectsByClass($filterToBeInstalled, 0, false);

			// Install other filter as configurable templates.
			} else {
				$filter->setIsTemplate(true);

				// Check whether the filter template has been
				// installed before.
				$existingFilters =& $filterDao->getObjectsByClass($filterToBeInstalled, 0, true);
			}

			// Guarantee idempotence.
			if ($existingFilters->getCount()) continue;

			// Install the filter or template.
			$filterDao->insertObject($filter, 0);

			// If this is a citation output filter then also install a corresponding references list filter.
			if (is_a($filter, 'NlmCitationSchemaCitationOutputFormatFilter')) {
				// Only Vancouver Style listings require numerical ordering.
				if (is_a($filter, 'NlmCitationSchemaVancouverFilter')) {
					$ordering = REFERENCES_LIST_ORDERING_NUMERICAL;
				} else {
					$ordering = REFERENCES_LIST_ORDERING_ALPHABETICAL;
				}

				// Instantiate the filter.
				$referencesListFilter = new PlainTextReferencesListFilter($filter->getDisplayName(), $filter->getClassName(), $ordering);
				$referencesListFilter->setIsTemplate(false);

				// Install the filter.
				$filterDao->insertObject($referencesListFilter, 0);
				unset($referencesListFilter);
			}

			unset($filter);
		}

		// Composite filters are more complex to install because they
		// need to be constructed first:
		// 1) Check and install the ISBNdb filter template.
		$alreadyInstalled = false;
		$existingTemplatesFactory =& $filterDao->getObjectsByClass('lib.pkp.classes.filter.GenericSequencerFilter', 0, true);
		$existingTemplates =& $existingTemplatesFactory->toArray();
		foreach($existingTemplates as $existingTemplate) {
			$subFilters =& $existingTemplate->getFilters();
			if (count($subFilters) != 2) continue;
			if (!(isset($subFilters[1]) && is_a($subFilters[1], 'IsbndbNlmCitationSchemaIsbnFilter'))) continue;
			if (!(isset($subFilters[2]) && is_a($subFilters[2], 'IsbndbIsbnNlmCitationSchemaFilter'))) continue;
			$alreadyInstalled = true;
			break;
		}
		if (!$alreadyInstalled) {
			// Instantiate the filter as a configurable template.
			$isbndbTransformation = array(
				'metadata::lib.pkp.classes.metadata.nlm.NlmCitationSchema(CITATION)',
				'metadata::lib.pkp.classes.metadata.nlm.NlmCitationSchema(CITATION)'
			);
			import('lib.pkp.classes.filter.GenericSequencerFilter');
			$isbndbFilter = new GenericSequencerFilter('ISBNdb', $isbndbTransformation);
			$isbndbFilter->setIsTemplate(true);

			// Instantiate and add the NLM-to-ISBN filter.
			import('lib.pkp.classes.citation.lookup.isbndb.IsbndbNlmCitationSchemaIsbnFilter');
			$nlmToIsbnFilter = new IsbndbNlmCitationSchemaIsbnFilter();
			$isbndbFilter->addFilter($nlmToIsbnFilter);

			// Instantiate and add the ISBN-to-NLM filter.
			import('lib.pkp.classes.citation.lookup.isbndb.IsbndbIsbnNlmCitationSchemaFilter');
			$isbnToNlmFilter = new IsbndbIsbnNlmCitationSchemaFilter();
			$isbndbFilter->addFilter($isbnToNlmFilter);

			// Add the settings mapping.
			$isbndbFilter->setSettingsMapping(
					array(
						'apiKey' => array('seq'.$nlmToIsbnFilter->getSeq().'_apiKey', 'seq'.$isbnToNlmFilter->getSeq().'_apiKey'),
						'isOptional' => array('seq'.$nlmToIsbnFilter->getSeq().'_isOptional', 'seq'.$isbnToNlmFilter->getSeq().'_isOptional')
					));

			// Persist the composite filter.
			$filterDao->insertObject($isbndbFilter, 0);
		}

		// 3) Check and install the NLM XML 2.3 output filter.
		$alreadyInstalled = false;
		$existingTemplatesFactory =& $filterDao->getObjectsByClass('lib.pkp.classes.filter.GenericSequencerFilter', 0, false);
		$existingTemplates =& $existingTemplatesFactory->toArray();
		foreach($existingTemplates as $existingTemplate) {
			$subFilters =& $existingTemplate->getFilters();
			if (count($subFilters) != 2) continue;
			if (!(isset($subFilters[1]) && is_a($subFilters[1], 'PKPSubmissionNlmXmlFilter'))) continue;
			if (!(isset($subFilters[2]) && is_a($subFilters[2], 'XSLTransformationFilter'))) continue;
			$alreadyInstalled = true;
			break;
		}
		if (!$alreadyInstalled) {
			// Instantiate the filter as a non-configurable filter instance.
			$nlm23Transformation = array(
				'class::lib.pkp.classes.submission.Submission',
				'xml::*'
			);
			$nlm23Filter = new GenericSequencerFilter('NLM Journal Publishing V2.3 ref-list', $nlm23Transformation);
			$nlm23Filter->setIsTemplate(false);

			// Instantiate and add the NLM 3.0 export filter.
			import('lib.pkp.classes.importexport.nlm.PKPSubmissionNlmXmlFilter');
			$nlm30Filter = new PKPSubmissionNlmXmlFilter();
			$nlm23Filter->addFilter($nlm30Filter);

			// Instantiate, configure and add the NLM 3.0 to 2.3 downgrade XSL transformation.
			import('lib.pkp.classes.xslt.XSLTransformationFilter');
			$downgradeFilter = new XSLTransformationFilter(
				'NLM 3.0 to 2.3 ref-list downgrade',
				array('xml::*', 'xml::*'));
			$downgradeFilter->setXSLFilename('lib/pkp/classes/importexport/nlm/nlm-ref-list-30-to-23.xsl');
			$nlm23Filter->addFilter($downgradeFilter);

			// Persist the composite filter.
			$filterDao->insertObject($nlm23Filter, 0);
		}

		return true;
	}

	/**
	 * Check to see whether a column exists.
	 * Used in installer XML in conditional checks on <data> nodes.
	 * @param $tableName string
	 * @param $columnName string
	 * @return boolean
	 */
	function columnExists($tableName, $columnName) {
		$siteDao =& DAORegistry::getDAO('SiteDAO');
		$dict = NewDataDictionary($siteDao->_dataSource);

		// Make sure the table exists
		$tables = $dict->MetaTables('TABLES', false);
		if (!in_array($tableName, $tables)) return false;

		// Check to see whether it contains the specified column.
		// Oddly, MetaColumnNames doesn't appear to be available.
		$columns = $dict->MetaColumns($tableName);
		foreach ($columns as $column) {
			if ($column->name == $columnName) return true;
		}
		return false;
	}

	/**
	 * Insert or update plugin data in versions
	 * and plugin_settings tables.
	 * @return boolean
	 */
	function addPluginVersions() {
		$versionDao =& DAORegistry::getDAO('VersionDAO');
		import('lib.pkp.classes.site.VersionCheck');
		$categories = PluginRegistry::getCategories();
		foreach ($categories as $category) {
			PluginRegistry::loadCategory($category);
			$plugins = PluginRegistry::getPlugins($category);
			if (is_array($plugins)) {
				foreach ($plugins as $plugin) {
					$versionFile = $plugin->getPluginPath() . '/version.xml';

					if (FileManager::fileExists($versionFile)) {
						$versionInfo =& VersionCheck::parseVersionXML($versionFile);
						$pluginVersion = $versionInfo['version'];
					} else {
						$pluginVersion = new Version(
							1, 0, 0, 0, Core::getCurrentDate(), 1,
							'plugins.'.$category, basename($plugin->getPluginPath()), '', 0
						);
					}
					$versionDao->insertVersion($pluginVersion, true);
				}
			}
		}

		return true;
	}
}

?>
