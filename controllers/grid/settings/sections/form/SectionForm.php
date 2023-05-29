<?php

/**
 * @file controllers/grid/settings/sections/form/SectionForm.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SectionForm
 *
 * @ingroup controllers_grid_settings_section_form
 *
 * @brief Form for adding/editing a section
 */

namespace APP\controllers\grid\settings\sections\form;

use APP\core\Application;
use APP\core\Request;
use APP\facades\Repo;
use APP\template\TemplateManager;
use PKP\controllers\grid\settings\sections\form\PKPSectionForm;

class SectionForm extends PKPSectionForm
{
    /**
     * Constructor.
     *
     * @param Request $request
     * @param int $sectionId optional
     */
    public function __construct($request, $sectionId = null)
    {
        parent::__construct(
            $request,
            'controllers/grid/settings/sections/form/sectionForm.tpl',
            $sectionId
        );

        // Validation checks for this form
        $this->addCheck(new \PKP\form\validation\FormValidatorLocale($this, 'title', 'required', 'manager.setup.form.section.nameRequired'));
        $this->addCheck(new \PKP\form\validation\FormValidatorLocale($this, 'abbrev', 'required', 'manager.sections.form.abbrevRequired'));
        $this->addCheck(new \PKP\form\validation\FormValidator($this, 'path', 'required', 'manager.setup.form.section.pathRequired'));
        $server = $request->getServer();
    }

    /**
     * Initialize form data from current settings.
     */
    public function initData()
    {
        $request = Application::get()->getRequest();
        $server = $request->getServer();

        $sectionId = $this->getSectionId();
        if ($sectionId) {
            $this->section = Repo::section()->get($sectionId, $server->getId());
        }

        if (isset($this->section)) {
            $this->setData([
                'title' => $this->section->getTitle(null), // Localized
                'abbrev' => $this->section->getAbbrev(null), // Localized
                'reviewFormId' => $this->section->getReviewFormId(),
                'isInactive' => $this->section->getIsInactive(),
                'metaIndexed' => !$this->section->getMetaIndexed(), // #2066: Inverted
                'metaReviewed' => !$this->section->getMetaReviewed(), // #2066: Inverted
                'abstractsNotRequired' => $this->section->getAbstractsNotRequired(),
                'identifyType' => $this->section->getIdentifyType(null), // Localized
                'editorRestriction' => $this->section->getEditorRestricted(),
                'hideTitle' => $this->section->getHideTitle(),
                'hideAuthor' => $this->section->getHideAuthor(),
                'policy' => $this->section->getPolicy(null), // Localized
                'wordCount' => $this->section->getAbstractWordCount(),
                'path' => $this->section->getPath(),
                'description' => $this->section->getDescription(null)
            ]);
        }

        parent::initData();
    }

    /**
     * @see Form::validate()
     */
    public function validate($callHooks = true)
    {
        // Validate if it can be inactive
        if ($this->getData('isInactive')) {
            $request = Application::get()->getRequest();
            $context = $request->getContext();
            $sectionId = $this->getSectionId();

            $activeSections = Repo::section()
                ->getCollector()
                ->filterByContextIds([$context->getId()])
                ->excludeInactive()
                ->getMany();
            $otherActiveSections = $activeSections->filter(function ($activeSection) use ($sectionId) {
                return $activeSection->getId() != $sectionId;
            });
            if ($otherActiveSections->count() < 1) {
                $this->addError('isInactive', __('manager.sections.confirmDeactivateSection.error'));
            }
        }

        return parent::validate($callHooks);
    }

    /**
     * @copydoc Form::fetch()
     *
     * @param null|mixed $template
     */
    public function fetch($request, $template = null, $display = false)
    {
        $templateMgr = TemplateManager::getManager($request);
        $templateMgr->assign('sectionId', $this->getSectionId());

        return parent::fetch($request, $template, $display);
    }

    /**
     * @copydoc Form::readInputData()
     */
    public function readInputData()
    {
        parent::readInputData();
        $this->readUserVars(['abbrev', 'path', 'description', 'policy', 'identifyType', 'isInactive', 'metaIndexed', 'abstractsNotRequired', 'editorRestriction', 'wordCount']);
    }

    /**
     * Get the names of fields for which localized data is allowed.
     *
     * @return array
     */
    public function getLocaleFieldNames()
    {
        return ['title', 'policy', 'abbrev', 'identifyType', 'description'];
    }

    /**
     * Save section.
     */
    public function execute(...$functionArgs)
    {
        $request = Application::get()->getRequest();
        $server = $request->getServer();

        // Get or create the section object
        if ($this->getSectionId()) {
            $section = Repo::section()->get($this->getSectionId(), $server->getId());
        } else {
            $section = Repo::section()->newDataObject();
            $section->setContextId($server->getId());
        }

        // Populate/update the section object from the form
        $section->setTitle($this->getData('title'), null); // Localized
        $section->setAbbrev($this->getData('abbrev'), null); // Localized
        $section->setPath($this->getData('path'));
        $section->setDescription($this->getData('description'), null); // Localized
        $section->setIsInactive($this->getData('isInactive') ? 1 : 0);
        $section->setMetaIndexed($this->getData('metaIndexed') ? 0 : 1); // #2066: Inverted
        $section->setAbstractsNotRequired($this->getData('abstractsNotRequired') ? 1 : 0);
        $section->setIdentifyType($this->getData('identifyType'), null); // Localized
        $section->setEditorRestricted($this->getData('editorRestriction') ? 1 : 0);
        $section->setPolicy($this->getData('policy'), null); // Localized
        $section->setAbstractWordCount((int) $this->getData('wordCount'));

        // Insert or update the section in the DB
        if ($this->getSectionId()) {
            Repo::section()->edit($section, []);
        } else {
            $section->setSequence(REALLY_BIG_NUMBER);
            $this->setSectionId(Repo::section()->add($section));
            Repo::section()->resequence($server->getId());
        }

        return parent::execute(...$functionArgs);
    }
}
