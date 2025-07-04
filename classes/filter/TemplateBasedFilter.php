<?php

/**
 * @file classes/filter/TemplateBasedFilter.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class TemplateBasedFilter
 *
 * @ingroup classes_filter
 *
 * @brief Abstract base class for all filters that transform
 *  their input via smarty templates.
 */

namespace PKP\filter;

use APP\core\Application;
use APP\template\TemplateManager;
use PKP\facades\Locale;

class TemplateBasedFilter extends PersistableFilter
{
    //
    // Abstract template methods
    //
    /**
     * Return the base path of the filter so that we
     * can find the filter templates.
     *
     * @return string
     */
    public function getBasePath()
    {
        // Must be implemented by sub-classes.
        assert(false);
    }

    /**
     * Return the template name to be used by this filter.
     *
     * @return string
     */
    public function getTemplateName()
    {
        // Must be implemented by sub-classes.
        assert(false);
    }

    /**
     * Sub-classes must implement this method to add
     * template variables to the template.
     *
     * @param TemplateManager $templateMgr
     * @param mixed $input the filter input
     * @param \APP\core\Request $request
     * @param string $locale
     */
    public function addTemplateVars($templateMgr, &$input, $request, &$locale)
    {
        // Must be implemented by sub-classes.
        assert(false);
    }


    //
    // Implement template methods from Filter
    //
    /**
     * @see Filter::process()
     */
    public function &process(&$input)
    {
        // Initialize view
        $locale = Locale::getLocale();
        $request = Application::get()->getRequest();
        $templateMgr = TemplateManager::getManager($request);

        // Add the filter's directory as additional template dir so that
        // templates can include sub-templates in the same folder.
        array_unshift($templateMgr->template_dir, $this->getBasePath());

        // Give sub-filters a chance to add their variables
        // to the template.
        $this->addTemplateVars($templateMgr, $input, $request, $locale);

        // Use a base path hash as compile id to make sure that we don't
        // get namespace problems if several filters use the same
        // template names.
        $previousCompileId = $templateMgr->compile_id;
        $templateMgr->compile_id = md5($this->getBasePath());

        // Let the template engine render the citation.
        $output = $templateMgr->fetch($this->getTemplateName());

        // Remove the additional template dir
        array_shift($templateMgr->template_dir);

        // Restore the compile id.
        $templateMgr->compile_id = $previousCompileId;

        return $output;
    }
}
