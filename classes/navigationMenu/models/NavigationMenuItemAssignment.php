<?php

/**
 * @file classes/navigationMenu/models/NavigationMenuItemAssignment.php
 *
 * Copyright (c) 2014-2025 Simon Fraser University
 * Copyright (c) 2003-2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class NavigationMenuItemAssignment
 *
 * @brief Laravel Eloquent model for navigation menu item assignments
 */

namespace PKP\navigationMenu\models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NavigationMenuItemAssignment extends Model
{
    protected $table = 'navigation_menu_item_assignments';
    protected $primaryKey = 'navigation_menu_item_assignment_id';
    public $timestamps = false;

    protected $fillable = [
        'navigation_menu_id',
        'navigation_menu_item_id',
        'parent_id',
        'seq',
    ];

    protected $casts = [
        'navigation_menu_item_assignment_id' => 'int',
        'navigation_menu_id' => 'int',
        'navigation_menu_item_id' => 'int',
        'parent_id' => 'int',
        'seq' => 'int',
    ];

    /**
     * Accessor and Mutator for primary key => id
     */
    protected function id(): Attribute
    {
        return Attribute::make(
            get: fn ($value, $attributes) => $attributes[$this->primaryKey] ?? null,
            set: fn ($value) => [$this->primaryKey => $value],
        );
    }

    /**
     * Navigation menu this assignment belongs to
     */
    public function navigationMenu(): BelongsTo
    {
        return $this->belongsTo(NavigationMenu::class, 'navigation_menu_id');
    }

    /**
     * Navigation menu item this assignment refers to
     */
    public function navigationMenuItem(): BelongsTo
    {
        return $this->belongsTo(NavigationMenuItem::class, 'navigation_menu_item_id');
    }

    /**
     * Parent assignment (for nested menu items)
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id', 'navigation_menu_item_assignment_id');
    }

    /**
     * Child assignments (for nested menu items)
     */
    public function children()
    {
        return $this->hasMany(self::class, 'parent_id', 'navigation_menu_item_assignment_id');
    }

}
