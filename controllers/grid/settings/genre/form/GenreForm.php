<?php

/**
 * @file controllers/grid/settings/genre/form/GenreForm.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class GenreForm
 *
 * @ingroup controllers_grid_settings_genre_form
 *
 * @brief Form for adding/editing a Submission File Genre.
 */

namespace PKP\controllers\grid\settings\genre\form;

use APP\core\Application;
use APP\template\TemplateManager;
use APP\facades\Repo;
use PKP\db\DAORegistry;
use PKP\form\Form;
use PKP\security\Validation;
use PKP\submission\genre\Genre;

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
            return $key == '' || !Genre::withKey($key)->withContext($context->getId())->withoutIds([(int) $form->getGenreId()])->exists();
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

        if ($this->getGenreId()) {
            $genre = Genre::findById((int) $this->getGenreId(),$context->getId());
        }

        if (isset($genre)) {
            $this->_data = [
                'genreId' => $this->getGenreId(),
                'name' => $genre->name,
                'category' => $genre->category,
                'dependent' => $genre->dependent,
                'supplementary' => $genre->supplementary,
                'required' => $genre->required,
                'key' => $genre->entryKey,
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
            Genre::GENRE_CATEGORY_DOCUMENT => __('submission.document'),
            Genre::GENRE_CATEGORY_ARTWORK => __('submission.art'),
            Genre::GENRE_CATEGORY_SUPPLEMENTARY => __('submission.supplementary'),
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
        $this->readUserVars(['genreId', 'name', 'category', 'dependent', 'supplementary', 'required', 'gridId', 'rowId', 'key']);
    }

    /**
     * @copydoc Form::execute()
     *
     * @return bool
     */
    public function execute(...$functionArgs)
    {
        $request = Application::get()->getRequest();
        $context = $request->getContext();

        // Update or insert genre
        if (!$this->getGenreId()) {
            $genre = new Genre();
            $genre->contextId = $context->getId();
        } else {
            $genre = Genre::findById((int) $this->getGenreId(), $context->getId());
        }

        $localizedNames = $this->getData('name');
        $attributes = [
            'name' => $localizedNames,
            'category' => $this->getData('category'),
            'dependent' => $this->getData('dependent'),
            'supplementary' => $this->getData('supplementary'),
            'required' => (bool) $this->getData('required'),
        ];

        if (!$genre->isDefault()) {
            $attributes['entryKey'] = $this->getData('key');
        }

        $genre->fill($attributes);
        $genre->save();

        // set genreId for newly created genre
        if (!$this->getGenreId()) {
            $this->setGenreId($genre->getKey());
        }

        parent::execute(...$functionArgs);
        return true;
    }
}
