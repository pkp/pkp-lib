<?php
/**
 * @file classes/invitation/sections/Sections.php
 *
 * Copyright (c) 2014-2024 Simon Fraser University
 * Copyright (c) 2000-2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Section
 *
 * @brief A class to define sections in an invitation.
 */
namespace PKP\invitation\sections;

class Sections
{
    public string $id;
    public string $type;
    public string $name;
    public string $description;
    public string $sectionComponent;
    public array $sections = [];
    public array $props = [];

    /**
     */
    public function __construct(string $id, string $name, string $description = '', string $type, string $sectionComponent)
    {
        $this->id = $id;
        $this->name = $name;
        $this->description = $description;
        $this->sectionComponent = $sectionComponent;
        $this->type = $type;
    }
    /**
     * Add a step to the invitation
     */
    public function addSection($section, $props): void
    {
        if(is_null($section)) {
            $this->sections[] = $section;
        } else {
            $this->sections[$section->id] = $section;
        }
        $this->props = $props;
    }

    /**
     * get section states
     */
    public function getState(): array
    {
        $state = [];
        foreach ($this->sections as $section) {
            if(is_null($section)) {
                $props = [
                    ...$this->props
                ];
            } else {
                $props = [
                    ...$this->props,
                    $section->type => $section->getState(),
                ];
            }
            $state[] = [
                'id' => $this->id,
                'name' => $this->name,
                'description' => $this->description,
                'sectionComponent' => $this->sectionComponent,
                'props' => $props,
            ];
        }
        return $state;
    }
}
