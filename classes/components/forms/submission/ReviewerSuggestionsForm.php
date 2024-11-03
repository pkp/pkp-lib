<?php
/**
 * @file classes/components/form/submission/ReviewerSuggestionsForm.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ReviewerSuggestionsForm
 *
 * @brief A preset form for setting submission's associated reviewer suggestions
 */

namespace PKP\components\forms\submission;

use APP\submission\Submission;
use PKP\components\forms\FieldRichTextarea;
use PKP\components\forms\FieldText;
use PKP\components\forms\FormComponent;
use PKP\context\Context;
use PKP\orcid\OrcidManager;

class ReviewerSuggestionsForm extends FormComponent
{
    public const FORM_CONTRIBUTOR = 'reviewerSuggestions';
    
    /**
     * @copydoc FormComponent::$id
     */
    public $id = self::FORM_CONTRIBUTOR;

    /**
     * @copydoc FormComponent::$method
     */
    public $method = 'POST';

    public Submission $submission;
    public Context $context;

    public function __construct(string $action, array $locales, Submission $submission, Context $context)
    {
        $this->action = $action;
        $this->locales = $locales;
        $this->submission = $submission;
        $this->context = $context;

        $this
            ->addField(new FieldText('givenName', [
                'label' => __('user.givenName'),
                'isMultilingual' => true,
                'isRequired' => true,
            ]))
            ->addField(new FieldText('familyName', [
                'label' => __('user.familyName'),
                'isMultilingual' => true,
                'isRequired' => true,
            ]))
            ->addField(new FieldText('email', [
                'label' => __('user.email'),
                'isRequired' => true,
            ]))
            ->addField(new FieldText('affiliation', [
                'label' => __('user.affiliation'),
                'isRequired' => true,
                'isMultilingual' => true,
            ]))
            ->addField(new FieldRichTextarea('suggestionReason', [
                'label' => __('reviewerSuggestion.suggestionReason'),
                'description' => __('reviewerSuggestion.suggestionReason.description'),
                'isRequired' => true,
                'isMultilingual' => true,
            ]));

        if (OrcidManager::isEnabled()) {
            $this->addField(new FieldText('orcidId', [
                'label' => __('user.orcid'),
                'tooltip' => __('orcid.about.orcidExplanation'),
                'isRequired' => false,
            ]), [FIELD_POSITION_AFTER, 'email']);
        }   
    }
}
