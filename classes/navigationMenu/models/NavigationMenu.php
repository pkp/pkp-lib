<?php

/**
 * @file classes/navigationMenu/models/NavigationMenu.php
 *
 * Copyright (c) 2014-2025 Simon Fraser University
 * Copyright (c) 2003-2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class NavigationMenu
 *
 * @brief Laravel Eloquent model for navigation menus
 */

namespace PKP\navigationMenu\models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class NavigationMenu extends Model
{
    protected $table = 'navigation_menus';
    protected $primaryKey = 'navigation_menu_id';
    public $timestamps = false;

    protected $fillable = [
        'context_id',
        'area_name',
        'title',
    ];

    protected $casts = [
        'navigation_menu_id' => 'int',
        'context_id' => 'int',
        'area_name' => 'string',
        'title' => 'string',
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
     * Navigation menu item assignments for this menu
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(\PKP\navigationMenu\models\NavigationMenuItemAssignment::class, 'navigation_menu_id');
    }

    /**
     * Navigation menu items assigned to this menu (through assignments)
     */
    public function menuItems(): BelongsToMany
    {
        return $this->belongsToMany(
            NavigationMenuItem::class,
            'navigation_menu_item_assignments',
            'navigation_menu_id',
            'navigation_menu_item_id'
        )->withPivot(['parent_id', 'seq']);
    }

}
