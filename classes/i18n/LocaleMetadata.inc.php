<?php
declare(strict_types = 1);

/**
 * @defgroup i18n I18N
 * Implements localization concerns such as locale files, time zones, and country lists.
 */

/**
 * @file classes/i18n/LocaleMetadata.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class LocaleMetadata
 * @ingroup i18n
 *
 * @brief Holds metadata about a system locale
 */

namespace PKP\i18n;

use PKP\core\ExportableTrait;
use SimpleXMLElement;

class LocaleMetadata
{
    use ExportableTrait;

    /** @var string Locale identification */
    public $key;

    /** @var string Locale name as written in its native form */
    public $name;

    /** @var string Locale representation in the iso639-2b format */
    public $iso639_2b;

    /** @var string Locale representation in the iso639-3 format */
    public $iso639_3;

    /** @var bool Whether the locale is complete */
    public $isComplete;

    /** @var bool Whether the locale expects text on the right-to-left format */
    public $isRtlDirection;

    /**
     * Creates a new instance from a XML object
     */
    public static function createFromXml(SimpleXMLElement $item): self
    {
        $instance = new static();
        $instance->key = (string) $item['key'];
        $instance->name = (string) $item['name'];
        $instance->iso639_2b = (string) $item['iso639-2b'];
        $instance->iso639_3 = (string) $item['iso639-3'];
        $instance->isComplete = ($item['complete'] ?? 'true') == 'true';
        $instance->isRtlDirection = ($item['direction'] ?? null) == 'rtl';
        return $instance;
    }
}
