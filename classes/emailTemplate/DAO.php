<?php
/**
 * @file classes/emailTemplate/DAO.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class DAO
 *
 * @brief Read and write email templates to the database.
 */

namespace PKP\emailTemplate;

use APP\core\Application;
use APP\facades\Repo;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;
use Illuminate\Support\Str;
use PKP\core\EntityDAO;
use PKP\core\PKPApplication;
use PKP\db\DAORegistry;
use PKP\db\XMLDAO;
use PKP\facades\Locale;
use PKP\site\Site;
use PKP\site\SiteDAO;

/**
 * @template T of EmailTemplate
 *
 * @extends EntityDAO<T>
 */
class DAO extends EntityDAO
{
    /** @copydoc EntityDAO::$schema */
    public $schema = \PKP\services\PKPSchemaService::SCHEMA_EMAIL_TEMPLATE;

    /** @copydoc EntityDAO::$table */
    public $table = 'email_templates';

    /** @copydoc EntityDAO::$settingsTable */
    public $settingsTable = 'email_templates_settings';

    public $defaultTable = 'email_templates_default_data';

    /** @copydoc EntityDAO::$primaryKeyColumn */
    public $primaryKeyColumn = 'email_id';

    /** @copydoc EntityDAO::$primaryTableColumns */
    public $primaryTableColumns = [
        'id' => 'email_id',
        'key' => 'email_key',
        'alternateTo' => 'alternate_to',
        'contextId' => 'context_id',
        'enabled' => 'enabled',
    ];

    /**
     * Get the parent object ID column name
     */
    public function getParentColumn(): string
    {
        return 'context_id';
    }

    /**
     * Instantiate a new DataObject
     */
    public function newDataObject(): EmailTemplate
    {
        return app(EmailTemplate::class);
    }

    /**
     * @copydoc EntityDAO::insert()
     *
     * Custom email templates will need to generate a unique key,
     * but the key will be set when this template is a customization
     * of a default template.
     *
     * @throws Exception
     *
     * @return string The email template key
     */
    public function insert(EmailTemplate $object): string
    {
        if (!$object->getData('key')) {
            $object->setData('key', $this->getUniqueKey($object));
        }
        parent::_insert($object);
        return $object->getData('key');
    }

    /**
     * @copydoc EntityDAO::update()
     */
    public function update(EmailTemplate $object)
    {
        parent::_update($object);
    }

    /**
     * @copydoc EntityDAO::delete()
     */
    public function delete(EmailTemplate $emailTemplate)
    {
        parent::_delete($emailTemplate);
    }

    /**
     * Get a collection of Email Templates matching the configured query
     *
     * @return LazyCollection<int,T>
     */
    public function getMany(Collector $query): LazyCollection
    {
        $rows = $query
            ->getQueryBuilder()
            ->get();

        return LazyCollection::make(function () use ($rows) {
            foreach ($rows as $row) {
                yield $this->fromRow($row);
            }
        });
    }

    /**
     * Get a single email template that matches the given key
     */
    public function getByKey(int $contextId, string $key): ?EmailTemplate
    {
        $results = Repo::emailTemplate()->getCollector($contextId)
            ->filterByKeys([$key])
            ->getMany();

        return $results->isNotEmpty() ? $results->first() : null;
    }

    /**
     * Get the number of announcements matching the configured query
     */
    public function getCount(Collector $query): int
    {
        return $query
            ->getQueryBuilder()
            ->getCountForPagination();
    }

    /**
     * Retrieve template together with data from the email_template_default_data
     *
     * @copydoc EntityDAO::fromRow()
     */
    public function fromRow(object $row): EmailTemplate
    {
        /** @var EmailTemplate $emailTemplate */
        $emailTemplate = parent::fromRow($row);
        $schema = $this->schemaService->get($this->schema);
        $contextDao = Application::getContextDAO();

        $supportedLocalesJson = $row->context_id === PKPApplication::SITE_CONTEXT_ID
            ? DB::table('site')->first()->supported_locales
            : DB::table($contextDao->settingsTableName)
                ->where($contextDao->primaryKeyColumn, $row->context_id)
                ->where('setting_name', 'supportedLocales')
                ->value('setting_value');

        $rows = DB::table($this->defaultTable)
            ->where('email_key', '=', $emailTemplate->getData('key'))
            ->whereIn('locale', json_decode($supportedLocalesJson, true))
            ->get();

        $props = ['name', 'subject', 'body'];

        $rows->each(function ($row) use ($emailTemplate, $schema, $props) {
            foreach ($props as $prop) {
                // Don't allow default data to override custom template data
                if ($emailTemplate->getData($prop, $row->locale)) {
                    continue;
                }
                $emailTemplate->setData(
                    $prop,
                    $this->convertFromDB(
                        $row->{$prop},
                        $schema->properties->{$prop}->type
                    ),
                    $row->locale
                );
            }
        });

        return $emailTemplate;
    }

    /**
     * Delete all email templates for a specific locale.
     */
    public function deleteEmailTemplatesByLocale(string $locale)
    {
        DB::table($this->settingsTable)->where('locale', $locale)->delete();
        DB::table($this->defaultTable)->where('locale', $locale)->delete();
    }

    /**
     * Check if a template exists with the given email key for a journal/
     * conference/...
     *
     *
     * @return bool
     */
    public function defaultTemplateIsInstalled(string $key)
    {
        return DB::table($this->defaultTable)->where('email_key', $key)->exists();
    }

    /**
     * Get the main email template path and filename.
     *
     * TODO add to the Repository
     */
    public function getMainEmailTemplatesFilename()
    {
        return 'registry/emailTemplates.xml';
    }

    /**
     * Install email templates from an XML file.
     *
     * @param string $templatesFile Filename to install
     * @param array $locales List of locales to install data for
     * @param string|null $emailKey Optional name of single email key to install,
     * skipping others
     * @param bool $skipExisting If true, do not install email templates
     * that already exist in the database
     *
     */
    public function installEmailTemplates(
        string $templatesFile,
        array $locales = [],
        ?string $emailKey = null,
        bool $skipExisting = false
    ): bool {
        $xmlDao = new XMLDAO();
        $data = $xmlDao->parseStruct($templatesFile, ['email']);
        if (!isset($data['email'])) {
            return false;
        }

        // if locales is empty, it will use the site's installed locales
        $locales = array_filter(array_map('trim', $locales));
        if (empty($locales)) {
            $siteDao = DAORegistry::getDAO('SiteDAO'); /** @var SiteDAO $siteDao */
            $site = $siteDao->getSite(); /** @var Site $site */
            $locales = $site->getInstalledLocales();
        }

        // filter out any invalid locales that is not supported by site
        $allLocales = array_keys(Locale::getLocales());
        if (!empty($invalidLocales = array_diff($locales, $allLocales))) {
            $locales = array_diff($locales, $invalidLocales);
        }

        foreach ($data['email'] as $entry) {
            $attrs = $entry['attributes'];
            if ($emailKey && $emailKey != $attrs['key']) {
                continue;
            }
            if ($skipExisting && $this->defaultTemplateIsInstalled($attrs['key'])) {
                continue;
            }

            // Add localized data
            $this->installEmailTemplateLocaleData($templatesFile, $locales, $attrs['key']);

            if (isset($attrs['alternateTo'])) {
                $contextIds = app()->get('context')->getIds();
                foreach ($contextIds as $contextId) {
                    $this->installAlternateEmailTemplates($contextId, $attrs['key']);
                }
            }
        }
        return true;
    }

    /**
     * Install email template contents from an XML file.
     *
     * @param string $templatesFile Filename to install
     * @param array $locales List of locales to install data for
     * @param string|null $emailKey Optional name of single email key to install,
     * skipping others
     *
     */
    public function installEmailTemplateLocaleData(
        string $templatesFile,
        array $locales = [],
        ?string $emailKey = null
    ): bool {
        $xmlDao = new XMLDAO();
        $data = $xmlDao->parseStruct($templatesFile, ['email']);
        if (!isset($data['email'])) {
            return false;
        }

        foreach ($data['email'] as $entry) {
            $attrs = $entry['attributes'];
            if ($emailKey && $emailKey != $attrs['key']) {
                continue;
            }

            $name = $attrs['name'] ?? null;
            $subject = $attrs['subject'] ?? null;
            $body = $attrs['body'] ?? null;
            if ($name && $subject && $body) {
                foreach ($locales as $locale) {
                    DB::table($this->defaultTable)
                        ->where('email_key', $attrs['key'])
                        ->where('locale', $locale)
                        ->delete();

                    $previous = Locale::getMissingKeyHandler();
                    Locale::setMissingKeyHandler(fn (string $key): string => '');
                    $translatedName = $name ? __($name, [], $locale) : $attrs['key'];
                    $translatedSubject = __($subject, [], $locale);
                    $translatedBody = __($body, [], $locale);
                    Locale::setMissingKeyHandler($previous);
                    if ($translatedSubject !== null && $translatedBody !== null) {
                        DB::table($this->defaultTable)->insert([
                            'email_key' => $attrs['key'],
                            'locale' => $locale,
                            'name' => $translatedName,
                            'subject' => $this->renameApplicationVariables($translatedSubject),
                            'body' => $this->renameApplicationVariables($translatedBody),
                        ]);
                    }
                }
            }
        }
        return true;
    }

    /**
     * Installs the "extra" email templates for a context
     *
     * These are default email templates that are not the default email
     * template for a particular mailable. They are extra templates listed
     * alongside the default template for this mailable.
     *
     * These templates are defined by the presence of the `alternateTo`
     * attribute in the email templates XML file. For them to appear in the
     * UI, they must have an entry in the `email_templates` database.
     */
    public function installAlternateEmailTemplates(int $contextId, ?string $emailKey = null): void
    {
        $xmlDao = new XMLDAO();
        $data = $xmlDao->parseStruct(Repo::emailTemplate()->dao->getMainEmailTemplatesFilename(), ['email']);
        if (!isset($data['email'])) {
            throw new Exception('Unable to install email templates.');
        }

        foreach ($data['email'] as $entry) {
            $attrs = $entry['attributes'];
            $alternateTo = $attrs['alternateTo'] ?? null;

            if ($emailKey && $emailKey != $attrs['key']) {
                continue;
            }
            if (!$alternateTo) {
                continue;
            }

            $exists = DB::table($this->defaultTable)
                ->where('email_key', $alternateTo)
                ->exists();

            if (!$exists) {
                trigger_error(
                    'Tried to install email template as an alternate to `' . $alternateTo . '`, but no default template exists with this key. Installing ' . $alternateTo . ' email template first',
                    E_USER_WARNING
                );
                $this->installEmailTemplates(Repo::emailTemplate()->dao->getMainEmailTemplatesFilename(), [], $alternateTo);
            }

            DB::table($this->table)->insert([
                'email_key' => $attrs['key'],
                'context_id' => $contextId,
                'alternate_to' => $attrs['alternateTo'],
            ]);
        }
    }

    /**
     * @param string $localizedData email template's localized subject or body
     */
    protected function renameApplicationVariables(string $localizedData): string
    {
        $map = $this->variablesToRename();
        if (empty($map)) {
            return $localizedData;
        }

        $variables = [];
        $replacements = [];
        foreach ($map as $key => $value) {
            $variables[] = '/\{\$' . $key . '\}/';
            $replacements[] = '{$' . $value . '}';
        }

        return preg_replace($variables, $replacements, $localizedData);
    }

    /**
     * Override this function on an application level to rename app-specific template variables
     *
     * Example: ['contextName' => 'journalName']
     */
    protected function variablesToRename(): array
    {
        return [];
    }

    /**
     * Gets a unique key for an email template
     *
     * Use this to generate a unique key before adding a template to the
     * database.
     */
    protected function getUniqueKey(EmailTemplate $emailTemplate): string
    {
        $key = (string) Str::of($emailTemplate->getLocalizedData('name'))
            ->ascii()->kebab()->limit(30, '')->replaceMatches('[^a-z0-9\-\_.]', '');

        if (!$key) {
            $key = uniqid();
        }

        $emailTemplate = $this->getByKey($emailTemplate->getData('contextId'), $key);

        $i = 0;
        while ($emailTemplate) {
            $key = $i ? (substr($key, 0, -1) . $i) : ($key . $i);
            $emailTemplate = $this->getByKey($emailTemplate->getData('contextId'), $key);
            $i++;
        }

        return $key;
    }
}
