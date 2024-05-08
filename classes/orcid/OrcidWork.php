<?php

/**
 * @file classes/orcid/OrcidWork.php
 *
 * Copyright (c) 2014-2024 Simon Fraser University
 * Copyright (c) 2000-2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class OrcidWork
 *
 * @brief Builds ORCID work object for deposit
 */

namespace APP\orcid;

use APP\core\Application;
use APP\plugins\generic\citationStyleLanguage\CitationStyleLanguagePlugin;
use APP\submission\Submission;
use PKP\orcid\PKPOrcidWork;
use PKP\plugins\PluginRegistry;

class OrcidWork extends PKPOrcidWork
{
    /**
     * @inheritDoc
     */
    protected function getOrcidPublicationType(): string
    {
        return 'preprint';
    }

    /**
     * @inheritdoc
     */
    protected function getBibtexCitation(Submission $submission): string
    {
        $request = Application::get()->getRequest();
        try {
            PluginRegistry::loadCategory('generic');
            /** @var CitationStyleLanguagePlugin $citationPlugin */
            $citationPlugin = PluginRegistry::getPlugin('generic', 'citationstylelanguageplugin');
            return trim(
                strip_tags(
                    $citationPlugin->getCitation(
                        $request,
                        $submission,
                        'bibtex',
                        publication: $this->publication
                    )
                )
            );
        } catch (\Exception $exception) {
            return '';
        }
    }
}
