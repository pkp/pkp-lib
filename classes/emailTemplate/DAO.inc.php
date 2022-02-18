<?php
/**
 * @file classes/emailTemplate/DAO.inc.php
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

use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;
use PKP\core\EntityDAO;
use PKP\db\XMLDAO;
use PKP\facades\Locale;
use PKP\facades\Repo;

class DAO extends EntityDAO
{
    /** @copydoc EntityDAO::$schema */
    public $schema = \PKP\services\PKPSchemaService::SCHEMA_EMAIL_TEMPLATE;

    /** @copydoc EntityDAO::$table */
    public $table = 'email_templates';

    /** @copydoc EntityDAO::$settingsTable */
    public $settingsTable = 'email_templates_settings';

    /** @copydoc EntityDAO::$primarykeyColumn */
    public $primaryKeyColumn = 'email_id';

    /** @copydoc EntityDAO::$primaryTableColumns */
    public $primaryTableColumns = [
        'id' => 'email_id',
        'key' => 'email_key',
        'contextId' => 'context_id',
        'enabled' => 'enabled',
        'canDisable' => 'can_disable',
        'canEdit' => 'can_edit',
        'fromRoleId' => 'from_role_id',
        'toRoleId' => 'to_role_id',
        'stageId' => 'stage_id',
    ];

    /**
     * Instantiate a new DataObject
     */
    public function newDataObject(): EmailTemplate
    {
        return app(EmailTemplate::class);
    }

    /**
     * @copydoc EntityDAO::insert()
     * @throws Exception
     */
    public function insert(EmailTemplate $object): int
    {
        // The object contains custom template information as well as the default data.
        // Strip default data from the object before calling insertObject so that it
        // doesn't try to write this data to the email templates settings table.
        $partialObject = clone $object;
        unset($partialObject->_data['canDisable']);
        unset($partialObject->_data['canEdit']);
        unset($partialObject->_data['description']);
        unset($partialObject->_data['fromRoleId']);
        unset($partialObject->_data['toRoleId']);
        unset($partialObject->_data['stageId']);

        return parent::_insert($partialObject);
    }

    /**
     * @copydoc EntityDAO::update()
     */
    public function update(EmailTemplate $object)
    {
        // The object contains custom template information as well as the default data.
        // Strip default data from the object before calling updateObject so that it
        // doesn't try to write this data to the email templates settings table.
        $partialObject = clone $object;
        unset($partialObject->_data['canDisable']);
        unset($partialObject->_data['canEdit']);
        unset($partialObject->_data['description']);
        unset($partialObject->_data['fromRoleId']);
        unset($partialObject->_data['toRoleId']);
        unset($partialObject->_data['stageId']);

        parent::_update($partialObject);
    }

    /**
     * @copydoc EntityDAO::delete()
     */
    public function delete(EmailTemplate $emailTemplate)
    {
        parent::_delete($emailTemplate);
    }

    /**
     * @copydoc EntityDAO::get()
     * @throws Exception
     */
    public function get(int $id): ?EmailTemplate
    {
        throw new Exception(__METHOD__ . ' is not supported. Email templates should be referenced by key instead of id.');
    }

    /**
     * Do not use this method.
     * @throws Exception
     */
    public function getIds()
    {
        throw new Exception(__METHOD__ . ' is not supported. Email templates should be referenced by key instead of id.');
    }

    /**
     * Get a collection of Email Templates matching the configured query
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
     * Get a singe email template that matches the given key
     */
    public function getByKey(int $contextId, string $key): ?EmailTemplate
    {
        $results = $this->getMany(
            Repo::emailTemplate()->getCollector()
                ->filterByContext($contextId)
                ->filterByKeys([$key])
        );
        return $results->isNotEmpty() ? $results->first() : null;
    }

    /**
     * Get the number of announcements matching the configured query
     */
    public function getCount(Collector $query): int
    {
        return $query
            ->getQueryBuilder()
            ->get()
            ->count();
    }

    /**
     * Retrieve template together with data from the email_template_default_data
     * @copydoc EntityDAO::fromRow()
     */
    public function fromRow(object $row): EmailTemplate
    {
	    /** @var EmailTemplate $emailTemplate */
        $emailTemplate = parent::fromRow($row);
        $schema = $this->schemaService->get($this->schema);

        $rows = DB::table('email_templates_default_data')
            ->where('email_key', '=', $emailTemplate->getData('key'))
            ->get();

        $props = ['subject', 'body', 'description'];

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
    }

    /**
     * Delete all default email templates for a specific locale.
     */
    public function deleteDefaultEmailTemplatesByLocale(string $locale)
    {
        DB::table('email_templates_default_data')->where('locale', $locale)->delete();
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
        return DB::table('email_templates_default')->where('email_key', $key)->exists();
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
     * @return bool
     */
    public function installEmailTemplates(
        string $templatesFile,
        array $locales = [],
        ?string $emailKey = null,
        bool $skipExisting = false
    ): bool
    {
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
            if ($skipExisting && $this->defaultTemplateIsInstalled($attrs['key'])) {
                continue;
            }

            DB::table('email_templates_default')->where('email_key', $attrs['key'])->delete();

            DB::table('email_templates_default')->insert([
                'email_key' => $attrs['key'],
                'can_disable' => $attrs['can_disable'] ? 1 : 0,
                'can_edit' => $attrs['can_edit'] ? 1 : 0,
                'from_role_id' => isset($attrs['from_role_id']) ? ((int) $attrs['from_role_id']) : null,
                'to_role_id' => isset($attrs['to_role_id']) ? ((int) $attrs['to_role_id']) : null,
                'stage_id' => isset($attrs['stage_id']) ? ((int) $attrs['stage_id']) : null,
            ]);

            // Add localized data
            $this->installEmailTemplateLocaleData($templatesFile, $locales, $attrs['key']);
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
     * @return bool
     */
    public function installEmailTemplateLocaleData(
        string $templatesFile,
        array $locales = [],
        ?string $emailKey = null
    ): bool
    {
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

            $subject = $attrs['subject'] ?? null;
            $body = $attrs['body'] ?? null;
            $description = $attrs['description'] ?? null;
            if ($subject && $body) {
                foreach ($locales as $locale) {
                    DB::table('email_templates_default_data')
                        ->where('email_key', $attrs['key'])
                        ->where('locale', $locale)
                        ->delete();

                    $previous = Locale::getMissingKeyHandler();
                    Locale::setMissingKeyHandler(fn (string $key): string => '');
                    $translatedSubject = __($subject, [], $locale);
                    $translatedBody = __($body, [], $locale);
                    Locale::setMissingKeyHandler($previous);
                    if ($translatedSubject !== null && $translatedBody !== null) {
                        DB::table('email_templates_default_data')->insert([
                            'email_key' => $attrs['key'],
                            'locale' => $locale,
                            'subject' => $this->renameApplicationVariables($translatedSubject),
                            'body' => $this->renameApplicationVariables($translatedBody),
                            'description' => __($description, [], $locale),
                        ]);
                    }
                }
            }
        }
        return true;
    }

    /**
     * Install email template localized data from an XML file.
     *
     * @deprecated Since OJS/OMP 3.2, this data should be supplied via the non-localized email template list and PO files. (pkp/pkp-lib#5461)
     *
     * @param string $templateDataFile Filename to install
     * @param string $locale Locale of template(s) to install
     * @param string|null $emailKey If specified, the key of the single template
     * to install (otherwise all are installed)
     *
     * @return array|boolean
     */
    public function installEmailTemplateData(
        string $templateDataFile,
        string $locale,
        ?string $emailKey = null
    ): bool
    {
        $xmlDao = new XMLDAO();
        $data = $xmlDao->parse($templateDataFile);
        if (!$data) {
            return false;
        }

        foreach ($data->getChildren() as $emailNode) {
            $subject = $emailNode->getChildValue('subject');
            $body = $emailNode->getChildValue('body');
            $description = $emailNode->getChildValue('description');

            // Translate variable contents
            foreach ([&$subject, &$body, &$description] as &$var) {
                $var = preg_replace_callback('{{translate key="([^"]+)"}}', fn($matches) => __($matches[1], [], $locale), $var);
            }

            if ($emailKey && $emailKey != $emailNode->getAttribute('key')) {
                continue;
            }
            DB::table('email_templates_default_data')
                ->where('email_key',$emailNode->getAttribute('key'))
                ->where('locale', $locale)
                ->delete();

            DB::table('email_templates_default_data')->insert([
                'email_key' => $emailNode->getAttribute('key'),
                'locale' => $locale,
                'subject' => $subject,
                'body' => $body,
                'description' => $description,
            ]);
        }
        return true;
    }

    /**
     * @param string $localizedData email template's localized subject or body
     * @return string
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
}
