<?php

/**
 * @file controllers/grid/users/reviewer/form/ReviewerGossipForm.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ReviewerGossipForm
 * @ingroup controllers_grid_users_reviewer_form
 *
 * @brief Form for viewing and editing gossip about a reviewer
 */

namespace PKP\controllers\grid\users\reviewer\form;

use APP\facades\Repo;
use APP\template\TemplateManager;
use PKP\form\Form;

class ReviewerGossipForm extends Form
{
    /** @var User The user to gossip about */
    public $_user;

    /** @var array Arguments used to route the form op */
    public $_requestArgs;

    /**
     * Constructor.
     *
     * @param User $user The user to gossip about
     * @param array $requestArgs Arguments used to route the form op to the
     *  correct submission, stage and review round
     */
    public function __construct($user, $requestArgs)
    {
        parent::__construct('controllers/grid/users/reviewer/form/reviewerGossipForm.tpl');
        $this->_user = $user;
        $this->_requestArgs = $requestArgs;
        $this->addCheck(new \PKP\form\validation\FormValidatorPost($this));
        $this->addCheck(new \PKP\form\validation\FormValidatorCSRF($this));
    }

    /**
     * @copydoc Form::readInputData()
     */
    public function readInputData()
    {
        $this->readUserVars([
            'gossip',
        ]);
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
            'requestArgs' => $this->_requestArgs,
            'gossip' => $this->_user->getGossip(),
        ]);

        return parent::fetch($request, $template, $display);
    }

    /**
     * @copydoc Form::execute()
     */
    public function execute(...$functionArgs)
    {
        $this->_user->setGossip($this->getData('gossip'));
        Repo::user()->edit($this->_user);
        parent::execute(...$functionArgs);
    }
}
