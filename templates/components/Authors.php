<?php

namespace PKP\components;

use Illuminate\View\Component;

class Authors extends Component
{
    /**
     * The publication object
     *
     * @var mixed
     */
    public $publication;

    /**
     * The authors collection
     *
     * @var array
     */
    public $authors = [];

    /**
     * Create a new component instance.
     *
     * @param mixed $publication
     */
    public function __construct($publication = null)
    {
        error_log('AUTHORS CLASS');
        $this->publication = $publication;
        
        // Get authors from publication's authors field
        if ($publication && isset($publication->authors)) {
            $this->authors = $publication->authors;
        } else {
            $this->authors = [];
        }
    }

    /**
     * Get the view / contents that represent the component.
     *
     * @return \Illuminate\Contracts\View\View|\Closure|string
     */
    public function render()
    {
        // The component instance is available as $component in the view
        return view('components.authors')->with('component', $this);
    }
}