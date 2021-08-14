<?php

/**
 * @file controllers/grid/settings/genre/form/GenreForm.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class GenreForm
 * @ingroup controllers_grid_settings_genre_form
 *
 * @brief Form for adding/editing a Submission File Genre.
 */

use APP\template\TemplateManager;

use PKP\form\Form;

class GenreForm extends Form
{
    /** @var int the id for the genre being edited */
    public $_genreId;

    /**
     * Set the genre id
     *
     * @param int $genreId
     */
    public function setGenreId($genreId)
    {
        $this->_genreId = $genreId;
    }

    /**
     * Get the genre id
     *
     * @return int
     */
    public function getGenreId()
    {
        return $this->_genreId;
    }


    /**
     * Constructor.
     *
     * @param null|mixed $genreId
     */
    public function __construct($genreId = null)
    {
        $this->setGenreId($genreId);
        parent::__construct('controllers/grid/settings/genre/form/genreForm.tpl');

        $request = Application::get()->getRequest();
        $context = $request->getContext();

        // Validation checks for this form
        $form = $this;
        $this->addCheck(new \PKP\form\validation\FormValidatorLocale($this, 'name', 'required', 'manager.setup.form.genre.nameRequired'));
        $this->addCheck(new \PKP\form\validation\FormValidatorCustom($this, 'key', 'optional', 'manager.setup.genres.key.exists', function ($key) use ($context, $form) {
            $genreDao = DAORegistry::getDAO('GenreDAO'); /** @var GenreDAO $genreDao */
            return $key == '' || !$genreDao->keyExists($key, $context->getId(), $form->getGenreId());
        }));
        $this->addCheck(new \PKP\form\validation\FormValidatorRegExp($this, 'key', 'optional', 'manager.setup.genres.key.alphaNumeric', '/^[a-z0-9]+([\-_][a-z0-9]+)*$/i'));
        $this->addCheck(new \PKP\form\validation\FormValidatorPost($this));
        $this->addCheck(new \PKP\form\validation\FormValidatorCSRF($this));
    }

    /**
     * Initialize form data from current settings.
     *
     * @param array $args
     */
    public function initData($args = [])
    {
        $request = Application::get()->getRequest();
        $context = $request->getContext();

        $genreDao = DAORegistry::getDAO('GenreDAO'); /** @var GenreDAO $genreDao */

        if ($this->getGenreId()) {
            $genre = $genreDao->getById($this->getGenreId(), $context->getId());
        }

        if (isset($genre)) {
            $this->_data = [
                'genreId' => $this->getGenreId(),
                'name' => $genre->getName(null),
                'category' => $genre->getCategory(),
                'dependent' => $genre->getDependent(),
                'supplementary' => $genre->getSupplementary(),
                'key' => $genre->getKey(),
                'keyReadOnly' => $genre->isDefault(),
            ];
        } else {
            $this->_data = [
                'name' => [],
            ];
        }

        // grid related data
        $this->_data['gridId'] = $args['gridId'];
        $this->_data['rowId'] = $args['rowId'] ?? null;
    }

    /**
     * @copydoc Form::fetch()
     *
     * @param null|mixed $template
     */
    public function fetch($request, $template = null, $display = false)
    {
        $templateMgr = TemplateManager::getManager($request);
        $templateMgr->assign('submissionFileCategories', [
            GENRE_CATEGORY_DOCUMENT => __('submission.document'),
            GENRE_CATEGORY_ARTWORK => __('submission.art'),
            GENRE_CATEGORY_SUPPLEMENTARY => __('submission.supplementary'),
        ]);
        return parent::fetch($request, $template, $display);
    }

    /**
     * Assign form data to user-submitted data.
     *
     * @see Form::readInputData()
     */
    public function readInputData()
    {
        $this->readUserVars(['genreId', 'name', 'category', 'dependent', 'supplementary', 'gridId', 'rowId', 'key']);
    }

    /**
     * @copydoc Form::execute()
     *
     * @return bool
     */
    public function execute(...$functionArgs)
    {
        $genreDao = DAORegistry::getDAO('GenreDAO'); /** @var GenreDAO $genreDao */
        $request = Application::get()->getRequest();
        $context = $request->getContext();

        // Update or insert genre
        if (!$this->getGenreId()) {
            $genre = $genreDao->newDataObject();
            $genre->setContextId($context->getId());
        } else {
            $genre = $genreDao->getById($this->getGenreId(), $context->getId());
        }

        $genre->setData('name', $this->getData('name'), null); // Localized
        $genre->setCategory($this->getData('category'));
        $genre->setDependent($this->getData('dependent'));
        $genre->setSupplementary($this->getData('supplementary'));

        if (!$genre->isDefault()) {
            $genre->setKey($this->getData('key'));
        }

        if (!$this->getGenreId()) {
            $this->setGenreId($genreDao->insertObject($genre));
        } else {
            $genreDao->updateObject($genre);
        }
        parent::execute(...$functionArgs);
        return true;
    }
}
