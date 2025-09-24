<?php

namespace PKP\components;

use Illuminate\View\Component;

class Author extends Component
{
    /**
     * The author object
     *
     * @var mixed
     */
    public $author;

    /**
     * The author's affiliations
     *
     * @var array
     */
    public $affiliations = [];

    /**
     * The author's credit roles
     *
     * @var array
     */
    public $creditRoles = [];

    /**
     * Create a new component instance.
     *
     * @param mixed $author
     */
    public function __construct($author = null)
    {
        $this->author = $author;
        
        // Get affiliations and credit roles from author object
        if ($author) {
            $this->affiliations = $author->affiliations ?? [];
            $this->creditRoles = $author->creditRoles ?? [];
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
        return view('components.author')->with('component', $this);
    }
}