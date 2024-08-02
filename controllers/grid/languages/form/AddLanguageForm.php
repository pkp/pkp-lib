<?php

/**
 * @file controllers/grid/languages/form/AddLanguageForm.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class AddLanguageForm
 *
 * @ingroup controllers_grid_languages_form
 *
 * @brief Form for adding languages.
 */

namespace PKP\controllers\grid\languages\form;

use APP\core\Application;
use APP\template\TemplateManager;
use PKP\facades\Locale;
use PKP\form\Form;

class AddLanguageForm extends Form
{
    /**
     * Constructor.
     */
    public function __construct()
    {
        parent::__construct('controllers/grid/languages/addLanguageForm.tpl');
    }

    //
    // Overridden methods from Form.
    //
    /**
     * @copydoc Form::initData()
     */
    public function initData()
    {
        parent::initData();
        $this->setData('addedLocales', ((Application::get()->getRequest())->getContext())->getSupportedAddedSubmissionLocales());
    }

    /**
     * @copydoc Form::fetch()
     *
     * @param null|mixed $template
     */
    public function fetch($request, $template = null, $display = false)
    {
        $templateMgr = TemplateManager::getManager($request);
        $templateMgr->assign([
            'availableLocales' => collect(Locale::getSubmissionLocaleDisplayNames())
                ->map(fn ($name, $localeCode) => "[ $localeCode ] $name"),
            'addedLocales' => $request->getContext()->getSupportedAddedSubmissionLocales(),
        ]);

        return parent::fetch($request, $template, $display);
    }

    /**
     * @copydoc Form::readInputData()
     */
    public function readInputData()
    {
        parent::readInputData();

        $request = Application::get()->getRequest();
        $localesToAdd = $request->getUserVar('localesToAdd');
        $this->setData('localesToAdd', $localesToAdd);
    }

    /**
     * @copydoc Form::execute()
     */
    public function execute(...$functionArgs)
    {
        $request = Application::get()->getRequest();
        $context = $request->getContext();
        $localesToAdd = $this->getData('localesToAdd');

        parent::execute(...$functionArgs);

        if (isset($localesToAdd) && is_array($localesToAdd)) {
            $locales = array_values(array_filter($localesToAdd, fn (string $locale) => Locale::isSubmissionLocaleValid($locale)));

            if ($locales) {
                sort($locales);

                $removedLocales = array_values(array_diff($context->getSupportedAddedSubmissionLocales(), $locales));
                $defaultLocaleRemoved = in_array($context->getSupportedDefaultSubmissionLocale(), $removedLocales);

                $edit = fn (array $ll): array => collect($ll)
                    ->diff($removedLocales)
                    ->concat($defaultLocaleRemoved ? [$locales[0]] : [])
                    ->unique()
                    ->sort()
                    ->values()
                    ->toArray();

                app()->get('context')->edit(
                    $context,
                    [
                        'supportedAddedSubmissionLocales' => $locales,
                        'supportedSubmissionLocales' => $edit($context->getSupportedSubmissionLocales()),
                        'supportedSubmissionMetadataLocales' => $edit($context->getSupportedSubmissionMetadataLocales()),
                        ...($defaultLocaleRemoved ? ['supportedDefaultSubmissionLocale' => $locales[0]] : []),
                    ],
                    $request
                );
            }
        }
    }

    /**
     * Perform additional validation checks
     *
     * @copydoc Form::validate
     */
    public function validate($callHooks = true)
    {
        $localesToAdd = $this->getData('localesToAdd');
        if (!isset($localesToAdd) || !is_array($localesToAdd) || !array_filter($localesToAdd, fn (string $locale) => Locale::isSubmissionLocaleValid($locale))) {
            $this->addError('localesToAdd', __('manager.language.submission.from.error'));
        }
        return parent::validate($callHooks);
    }
}
