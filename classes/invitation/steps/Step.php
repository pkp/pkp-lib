<?php
/**
 * @file classes/invitation/steps/Step.php
 *
 * Copyright (c) 2014-2024 Simon Fraser University
 * Copyright (c) 2000-2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Step
 *
 * @brief create a separate step with sections.
 */

namespace PKP\invitation\steps;

use stdClass;

class Step
{
    public string $id;
    public string $type;
    public string $name;
    public string $description;
    public string $stepLabel;
    public string $nextButtonLabel;
    public bool $skipInvitationUpdate;
    public array $sections = [];

    /**
     * @param string $id A unique id for this step
     * @param string $name The name of this step. Shown to the user.
     * @param string $description A description of this step. Shown to the user.
     * @param string $stepLabel A step label with step number of this step. Shown to the user.
     * @param string $nextButtonLabel A step button label of this step. Shown to the user.
     * @param string $type A type of this step.
     * @param bool $skipInvitationUpdate Skip invitation update.
     */
    public function __construct(string $id, string $name, string $description = '', string $stepLabel, string $nextButtonLabel, string $type, bool $skipInvitationUpdate = false)
    {
        $this->id = $id;
        $this->name = $name;
        $this->description = $description;
        $this->stepLabel = $stepLabel;
        $this->nextButtonLabel = $nextButtonLabel;
        $this->type = $type;
        $this->skipInvitationUpdate = $skipInvitationUpdate;
    }

    /**
     * Compile initial state data to pass to the frontend
     */
    public function getState(): stdClass
    {
        $config = new stdClass();
        $config->id = $this->id;
        $config->name = $this->name;
        $config->description = $this->description;
        $config->nextButtonLabel = $this->nextButtonLabel;
        $config->skipInvitationUpdate = $this->skipInvitationUpdate;
        $config->type = $this->type;
        $config->stepLabel = $this->stepLabel;
        $config->sections = $this->sections;
        return $config;
    }

    /**
     * Add a step to the workflow
     */
    public function addSectionToStep($sections): void
    {
        $this->sections = $sections;
    }
}
