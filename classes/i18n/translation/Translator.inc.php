<?php
declare(strict_types = 1);

/**
 * @defgroup i18n I18N
 * Implements localization concerns such as locale files, time zones, and country lists.
 */

/**
 * @file classes/i18n/translation/Translator.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Translator
 * @ingroup i18n
 *
 * @brief Extends the default GetText translator with serialization and the possibility to detect when translations failed
 */

namespace PKP\i18n\translation;

use Gettext\Translator as GetTextTranslator;
use PKP\core\ExportableTrait;

class Translator extends GetTextTranslator
{
    use ExportableTrait;

    /**
     * Builds a translator instance from arrays
     */
    public static function createFromTranslationsArray(array ...$translations): self
    {
        $translator = new static();
        foreach ($translations as $translationSet) {
            $translator->addTranslations($translationSet);
        }
        return $translator;
    }

    /**
     * Retrieves a singular translation
     *
     * @return string Empty string when a translation cannot be retrieved
     */
    public function getSingular(string $original): string
    {
        $translation = $this->getTranslation(null, null, $original);
        return $translation[0] ?? '';
    }

    /**
     * Retrieves a plural translation
     *
     * @return string Empty string when a translation cannot be retrieved
     */
    public function getPlural(string $original, int $value): string
    {
        $translation = $this->getTranslation(null, null, $original);
        $key = $this->getPluralIndex(null, $value, $translation === null);

        return $translation[$key] ?? '';
    }

    /**
     * Retrieves the raw translator data
     */
    public function getEntries(): array
    {
        $context = '';
        return $this->dictionary[$this->domain][$context] ?? [];
    }
}
