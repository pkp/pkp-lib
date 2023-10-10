<?php
/**
 * @file classes/components/form/publication/Details.php
 *
 * Copyright (c) 2014-2023 Simon Fraser University
 * Copyright (c) 2000-2023 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Details
 *
 * @ingroup classes_controllers_form
 *
 * @brief The Details form in the submission wizard.
 */

namespace PKP\components\forms\publication;

use APP\publication\Publication;
use PKP\components\forms\FieldControlledVocab;
use PKP\context\Context;
use PKP\submission\SubmissionKeywordDAO;

class Details extends TitleAbstractForm
{
    /**
     * Constructor
     *
     * @param string $suggestionUrlBase The base URL to get suggestions for controlled vocab.
     */
    public function __construct(
        string $action,
        array $locales,
        Publication $publication,
        public Context $context,
        public string $suggestionUrlBase,
        int $abstractWordLimit = 0,
        bool $isAbstractRequired = false
    ) {
        parent::__construct($action, $locales, $publication, $abstractWordLimit, $isAbstractRequired);

        $this->removeField('prefix');
        $this->removeField('subtitle');

        if (in_array($context->getData('keywords'), [Context::METADATA_REQUEST, Context::METADATA_REQUIRE])) {
            $this->addField(new FieldControlledVocab('keywords', [
                'label' => __('common.keywords'),
                'description' => __('manager.setup.metadata.keywords.description'),
                'isMultilingual' => true,
                'apiUrl' => str_replace('__vocab__', SubmissionKeywordDAO::CONTROLLED_VOCAB_SUBMISSION_KEYWORD, $suggestionUrlBase),
                'locales' => $this->locales,
                'value' => (array) $publication->getData('keywords'),
                'isRequired' => $context->getData('keywords') === Context::METADATA_REQUIRE ? true : false,
            ]), [FIELD_POSITION_AFTER, 'title']);
        }
    }
}
