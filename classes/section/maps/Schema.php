<?php
/**
 * @file classes/section/maps/Schema.php
 *
 * Copyright (c) 2014-2023 Simon Fraser University
 * Copyright (c) 2003-2023 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Schema
 *
 * @brief Map sections to the properties defined in the section schema
 */

namespace APP\section\maps;

use APP\core\Application;
use APP\section\Section;

class Schema extends \PKP\section\maps\Schema
{
    /**
     * Map schema properties of an Section to an assoc array
     */
    protected function mapByProperties(array $props, Section $section): array
    {
        $output = parent::mapByProperties($props, $section);

        if (in_array('urlPublished', $props)) {
            $output['urlPublished'] = $this->request->getDispatcher()->url(
                $this->request,
                Application::ROUTE_PAGE,
                $this->context->getPath(),
                'preprints',
                'section',
                $section->getPath()
            );
        }
        $output = $this->schemaService->addMissingMultilingualValues($this->schema, $output, $this->context->getSupportedFormLocales());
        ksort($output);
        return $this->withExtensions($output, $section);
    }
}
