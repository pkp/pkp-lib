<?php

namespace PKP\log\core\maps;

use Illuminate\Support\Enumerable;
use PKP\log\EmailLogEntry;
use PKP\services\PKPSchemaService;

class Schema extends \PKP\core\maps\Schema
{
    public string $schema = PKPSchemaService::SCHEMA_EMAIL_LOG;

    /**
     * Map an email log
     *
     * Includes all properties in the emailLog schema.
     */
    public function map(EmailLogEntry $item): array
    {
        return $this->mapByProperties($this->getProps(), $item);
    }

    /**
     * Summarize an email log
     *
     * Includes properties with the apiSummary flag in the email log entry schema.
     */
    public function summarize(EmailLogEntry $entry): array
    {
        return $this->mapByProperties($this->getSummaryProps(), $entry);
    }

    /**
     * Map a collection of email log entries
     *
     * @see self::map
     */
    public function mapMany(Enumerable $collection): Enumerable
    {
        return $collection->map(function ($category) {
            return $this->map($category);
        });
    }

    /**
     * Summarize a collection of email log entries
     *
     * @see self::summarize
     */
    public function summarizeMany(Enumerable $collection): Enumerable
    {
        return $collection->map(function ($category) {
            return $this->summarize($category);
        });
    }

    /**
     * Map schema properties of an email log entry to an associative array
     */
    protected function mapByProperties(array $props, EmailLogEntry $entry): array
    {
        $output = [];

        foreach ($props as $prop) {
            switch ($prop) {
                case '_href':
                    $output[$prop] = $this->getApiUrl(
                        'emails/' . $entry->id,
                        $this->context->getData('urlPath')
                    );
                    break;
                default:
                    $output[$prop] = $entry->{$prop};
                    break;
            }
        }

        return $output;
    }
}
