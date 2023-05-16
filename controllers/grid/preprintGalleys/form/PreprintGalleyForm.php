<?php

/**
 * @file controllers/grid/preprintGalleys/form/PreprintGalleyForm.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PreprintGalleyForm
 *
 * @ingroup controllers_grid_preprintGalleys_form
 *
 * @see Galley
 *
 * @brief Preprint galley editing form.
 */

namespace APP\controllers\grid\preprintGalleys\form;

use APP\core\Request;
use APP\facades\Repo;
use APP\publication\Publication;
use APP\submission\Submission;
use APP\template\TemplateManager;

use PKP\form\Form;
use PKP\galley\Galley;

class PreprintGalleyForm extends Form
{
    /** @var Submission */
    public $_submission = null;

    /** @var Publication */
    public $_publication = null;

    /** @var Galley current galley */
    public $_preprintGalley = null;

    /** @var bool indicates whether the form is editable */
    public bool $_isEditable = true;

    /**
     * Constructor.
     *
     * @param Request $request
     * @param Submission $submission
     * @param Publication $publication
     * @param Galley $preprintGalley (optional)
     * @param bool $isEditable (optional, default = true)
     */
    public function __construct($request, $submission, $publication, $preprintGalley = null, $isEditable = true)
    {
        parent::__construct('controllers/grid/preprintGalleys/form/preprintGalleyForm.tpl');
        $this->_submission = $submission;
        $this->_publication = $publication;
        $this->_preprintGalley = $preprintGalley;
        $this->_isEditable = $isEditable;

        $this->addCheck(new \PKP\form\validation\FormValidator($this, 'label', 'required', 'editor.submissions.galleyLabelRequired'));
        $this->addCheck(new \PKP\form\validation\FormValidatorRegExp($this, 'urlPath', 'optional', 'validator.alpha_dash_period', '/^[a-zA-Z0-9]+([\\.\\-_][a-zA-Z0-9]+)*$/'));
        $this->addCheck(new \PKP\form\validation\FormValidatorPost($this));
        $this->addCheck(new \PKP\form\validation\FormValidatorCSRF($this));

        // Ensure a locale is provided and valid
        $server = $request->getServer();
        $this->addCheck(
            new \PKP\form\validation\FormValidator(
                $this,
                'locale',
                'required',
                'editor.submissions.galleyLocaleRequired'
            ),
            function ($locale) use ($server) {
                return in_array($locale, $server->getSupportedSubmissionLocaleNames());
            }
        );
    }

    /**
     * @copydoc Form::fetch()
     *
     * @param null|mixed $template
     */
    public function fetch($request, $template = null, $display = false)
    {
        $templateMgr = TemplateManager::getManager($request);
        if ($this->_preprintGalley) {
            $preprintGalleyFile = $this->_preprintGalley->getFile();
            $filepath = $preprintGalleyFile ? $preprintGalleyFile->getData('path') : null;
            $templateMgr->assign([
                'representationId' => $this->_preprintGalley->getId(),
                'preprintGalley' => $this->_preprintGalley,
                'preprintGalleyFile' => $preprintGalleyFile,
                'supportsDependentFiles' => $preprintGalleyFile ? Repo::submissionFile()->supportsDependentFiles($preprintGalleyFile, $filepath) : null,
            ]);
        }
        $context = $request->getContext();
        $templateMgr->assign([
            'supportedLocales' => $context->getSupportedSubmissionLocaleNames(),
            'submissionId' => $this->_submission->getId(),
            'publicationId' => $this->_publication->getId(),
            'formDisabled' => !$this->_isEditable
        ]);

        return parent::fetch($request, $template, $display);
    }

    /**
     * @copydoc Form::validate
     */
    public function validate($callHooks = true)
    {
        /// Validate the urlPath
        if (strlen($this->getData('urlPath'))) {
            if (ctype_digit((string) $this->getData('urlPath'))) {
                $this->addError('urlPath', __('publication.urlPath.numberInvalid'));
                $this->addErrorField('urlPath');
            } else {
                $existingGalley = Repo::galley()->getByUrlPath((string) $this->getData('urlPath'), $this->_publication);
                if ($existingGalley && $this->_preprintGalley?->getId() !== $existingGalley->getId()) {
                    $this->addError('urlPath', __('publication.urlPath.duplicate'));
                    $this->addErrorField('urlPath');
                }
            }
        }

        if (!$this->_isEditable) {
            $this->addError('', __('galley.cantEditPublished'));
        }

        return parent::validate($callHooks);
    }

    /**
     * Initialize form data from current galley (if applicable).
     */
    public function initData()
    {
        if ($this->_preprintGalley) {
            $this->_data = [
                'label' => $this->_preprintGalley->getLabel(),
                'locale' => $this->_preprintGalley->getLocale(),
                'urlPath' => $this->_preprintGalley->getData('urlPath'),
                'urlRemote' => $this->_preprintGalley->getData('urlRemote'),
            ];
        } else {
            $this->_data = [];
        }
    }

    /**
     * Assign form data to user-submitted data.
     */
    public function readInputData()
    {
        $this->readUserVars(
            [
                'label',
                'locale',
                'urlPath',
                'urlRemote',
            ]
        );
    }

    /**
     * Save changes to the galley.
     *
     * @return PreprintGalley The resulting preprint galley.
     */
    public function execute(...$functionArgs)
    {
        $data = [
            'publicationId' => $this->_publication->getId(),
            'label' => $this->getData('label'),
            'locale' => $this->getData('locale'),
            'urlPath' => strlen($urlPath = (string) $this->getData('urlPath')) ? $urlPath : null,
            'urlRemote' => strlen($urlRemote = (string) $this->getData('urlRemote')) ? $urlRemote : null
        ];
        if ($this->_preprintGalley) {
            // Update galley in the db
            Repo::galley()->edit($this->_preprintGalley, $data);
            $galleyId = $this->_preprintGalley->getId();
        } else {
            // Create a new galley
            $galleyId = Repo::galley()->add(Repo::galley()->newDataObject($data));
        }

        parent::execute(...$functionArgs);

        return $this->_preprintGalley = Repo::galley()->get($galleyId);
    }
}
