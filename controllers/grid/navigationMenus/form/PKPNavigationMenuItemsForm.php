<?php

/**
 * @file controllers/grid/navigationMenus/form/PKPNavigationMenuItemsForm.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPNavigationMenuItemsForm
 *
 * @ingroup controllers_grid_navigationMenus
 *
 * @brief Form for managers to create/edit navigationMenuItems.
 */

namespace PKP\controllers\grid\navigationMenus\form;

use APP\core\Services;
use APP\template\TemplateManager;
use PKP\db\DAORegistry;
use PKP\facades\Locale;
use PKP\form\Form;
use PKP\navigationMenu\NavigationMenuItem;
use PKP\navigationMenu\NavigationMenuItemDAO;

class PKPNavigationMenuItemsForm extends Form
{
    /** @var int $navigationMenuItemId the ID of the navigationMenuItem */
    public $navigationMenuItemId;

    public ?int $_contextId;

    /**
     * Constructor
     *
     * @param int $contextId
     * @param int $navigationMenuItemId
     */
    public function __construct(?int $contextId, $navigationMenuItemId)
    {
        $this->_contextId = $contextId;
        $this->navigationMenuItemId = $navigationMenuItemId;

        parent::__construct('controllers/grid/navigationMenus/form/navigationMenuItemsForm.tpl');

        $this->addCheck(new \PKP\form\validation\FormValidatorLocale($this, 'title', 'required', 'manager.navigationMenus.items.form.title.required', $this->defaultLocale));
        $this->addCheck(new \PKP\form\validation\FormValidatorPost($this));
        $this->addCheck(new \PKP\form\validation\FormValidatorCSRF($this));
    }


    //
    // Getters and setters.
    //

    /**
     * Get the current context id.
     */
    public function getContextId(): ?int
    {
        return $this->_contextId;
    }


    //
    // Extended methods from Form.
    //

    /**
     * @copydoc Form::fetch()
     *
     * @param null|mixed $template
     */
    public function fetch($request, $template = null, $display = false)
    {
        $templateMgr = TemplateManager::getManager($request);
        $templateMgr->assign('navigationMenuItemId', $this->navigationMenuItemId);

        $context = $request->getContext();
        if ($context) {
            $templateMgr->assign('allowedVariables', [
                'contactName' => __('plugins.generic.tinymce.variables.principalContactName', ['value' => $context->getData('contactName')]),
                'contactEmail' => __('plugins.generic.tinymce.variables.principalContactEmail', ['value' => $context->getData('contactEmail')]),
                'supportName' => __('plugins.generic.tinymce.variables.supportContactName', ['value' => $context->getData('supportName')]),
                'supportPhone' => __('plugins.generic.tinymce.variables.supportContactPhone', ['value' => $context->getData('supportPhone')]),
                'supportEmail' => __('plugins.generic.tinymce.variables.supportContactEmail', ['value' => $context->getData('supportEmail')]),
            ]);
        }
        $types = app()->get('navigationMenu')->getMenuItemTypes();

        $typeTitles = [0 => __('grid.navigationMenus.navigationMenu.selectType')];
        foreach ($types as $type => $settings) {
            $typeTitles[$type] = $settings['title'];
        }

        $typeDescriptions = [];
        foreach ($types as $type => $settings) {
            $typeDescriptions[$type] = $settings['description'];
        }

        $typeConditionalWarnings = [];
        foreach ($types as $type => $settings) {
            if (array_key_exists('conditionalWarning', $settings)) {
                $typeConditionalWarnings[$type] = $settings['conditionalWarning'];
            }
        }

        $customTemplates = app()->get('navigationMenu')->getMenuItemCustomEditTemplates();

        $templateArray = [
            'navigationMenuItemTypeTitles' => $typeTitles,
            'navigationMenuItemTypeDescriptions' => json_encode($typeDescriptions),
            'navigationMenuItemTypeConditionalWarnings' => json_encode($typeConditionalWarnings),
            'customTemplates' => $customTemplates,
        ];

        $templateMgr->assign($templateArray);

        return parent::fetch($request, $template, $display);
    }

    /**
     * Initialize form data from current navigation menu item.
     */
    public function initData()
    {
        $navigationMenuItemDao = DAORegistry::getDAO('NavigationMenuItemDAO'); /** @var NavigationMenuItemDAO $navigationMenuItemDao */
        $navigationMenuItem = $navigationMenuItemDao->getById($this->navigationMenuItemId);

        if ($navigationMenuItem) {
            app()->get('navigationMenu')
                ->setAllNMILocalizedTitles($navigationMenuItem);

            $formData = [
                'path' => $navigationMenuItem->getPath(),
                'title' => $navigationMenuItem->getTitle(null),
                'url' => $navigationMenuItem->getUrl(),
                'menuItemType' => $navigationMenuItem->getType(),
            ];

            $this->_data = $formData;

            $this->setData('content', $navigationMenuItem->getContent(null)); // Localized
            $this->setData('remoteUrl', $navigationMenuItem->getRemoteUrl(null)); // Localized
        }
    }

    /**
     * Assign form data to user-submitted data.
     */
    public function readInputData()
    {
        $this->readUserVars(['navigationMenuItemId', 'path', 'content', 'title', 'remoteUrl', 'menuItemType']);
    }

    /**
     * @copydoc Form::getLocaleFieldNames()
     */
    public function getLocaleFieldNames(): array
    {
        $navigationMenuItemDao = DAORegistry::getDAO('NavigationMenuItemDAO'); /** @var NavigationMenuItemDAO $navigationMenuItemDao */
        return $navigationMenuItemDao->getLocaleFieldNames();
    }

    /**
     * Save NavigationMenuItem.
     */
    public function execute(...$functionParams)
    {
        parent::execute(...$functionParams);

        $navigationMenuItemDao = DAORegistry::getDAO('NavigationMenuItemDAO'); /** @var NavigationMenuItemDAO $navigationMenuItemDao */

        $navigationMenuItem = $navigationMenuItemDao->getById($this->navigationMenuItemId);
        if (!$navigationMenuItem) {
            $navigationMenuItem = $navigationMenuItemDao->newDataObject();
            $navigationMenuItem->setTitle($this->getData('title'), null);
        } else {
            // The NMI will have localized title in the navigation_menu_item_settings table
            // only if the user is defining one explicitly.
            // This is not always the case for the default NMIs that, by default, get their titles
            // from the titleLocaleKey setting of the NMI.

            // Get the title that have been explicitly set in the settings tatble
            $localizedTitlesFromDB = $navigationMenuItem->getTitle(null) ?? [];

            // Get the set of all displayed titles, no matter the source. 
            Services::get('navigationMenu')
                ->setAllNMILocalizedTitles($navigationMenuItem);
            $originalDisplayTitles = $navigationMenuItem->getTitle(null);

            $newTitles = [];

            // Get the explicitly provided user titles
            $inputLocalizedTitles = $this->getData('title');

            // Explicit define the localized title of the NMI.
            foreach ($inputLocalizedTitles as $locale => $inputTitle) {
                $trimmedTitle = trim($inputTitle);

                // If empty, set to null (resets to default)
                if ($trimmedTitle === '') {
                    $newTitles[$locale] = null;
                    continue;
                }

                // Update title if explicitly defined or changed from default
                if (isset($localizedTitlesFromDB[$locale]) || ($originalDisplayTitles[$locale] ?? null) !== $trimmedTitle) {
                    $newTitles[$locale] = $trimmedTitle;
                }
            }

            $navigationMenuItem->setData('title', $newTitles);
        }

        $navigationMenuItem->setPath($this->getData('path'));
        $navigationMenuItem->setContent($this->getData('content'), null); // Localized
        $navigationMenuItem->setContextId($this->getContextId());
        $navigationMenuItem->setRemoteUrl($this->getData('remoteUrl'), null); // Localized
        $navigationMenuItem->setType($this->getData('menuItemType'));

        // Update or insert navigation menu item
        if ($navigationMenuItem->getId()) {
            $navigationMenuItemDao->updateObject($navigationMenuItem);
        } else {
            $navigationMenuItemDao->insertObject($navigationMenuItem);
        }

        $this->navigationMenuItemId = $navigationMenuItem->getId();

        return $navigationMenuItem->getId();
    }

    /**
     * Perform additional validation checks
     *
     * @copydoc Form::validate
     */
    public function validate($callHooks = true)
    {
        if ($this->getData('menuItemType') && $this->getData('menuItemType') != '') {
            if ($this->getData('menuItemType') == NavigationMenuItem::NMI_TYPE_CUSTOM) {
                if (!preg_match('/^[a-zA-Z0-9\/._-]+$/', $this->getData('path'))) {
                    $this->addError('path', __('manager.navigationMenus.form.pathRegEx'));
                }

                $navigationMenuItemDao = DAORegistry::getDAO('NavigationMenuItemDAO'); /** @var NavigationMenuItemDAO $navigationMenuItemDao */

                $navigationMenuItem = $navigationMenuItemDao->getByPath($this->_contextId, $this->getData('path'));
                if (isset($navigationMenuItem) && $navigationMenuItem->getId() != $this->navigationMenuItemId) {
                    $this->addError('path', __('manager.navigationMenus.form.duplicatePath'));
                }
            } elseif ($this->getData('menuItemType') == NavigationMenuItem::NMI_TYPE_REMOTE_URL) {
                $remoteUrls = $this->getData('remoteUrl');
                foreach ($remoteUrls as $locale => $remoteUrl) {
                    // URLs are optional for languages other than the primary locale.
                    if ($locale !== Locale::getPrimaryLocale() && $remoteUrl == '') {
                        continue;
                    }
                    if (!filter_var($remoteUrl, FILTER_VALIDATE_URL)) {
                        $this->addError('remoteUrl', __('manager.navigationMenus.form.customUrlError'));
                    }
                }
            }
        } else {
            $this->addError('path', __('manager.navigationMenus.form.typeMissing'));
        }

        return parent::validate($callHooks);
    }
}
