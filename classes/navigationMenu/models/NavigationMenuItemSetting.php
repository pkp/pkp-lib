<?php

/**
 * @file classes/navigationMenu/models/NavigationMenuItemSetting.php
 *
 * Copyright (c) 2014-2025 Simon Fraser University
 * Copyright (c) 2003-2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class NavigationMenuItemSetting
 *
 * @brief Laravel Eloquent model for navigation menu item settings
 */

namespace PKP\navigationMenu\models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NavigationMenuItemSetting extends Model
{
    protected $table = 'navigation_menu_item_settings';
    protected $primaryKey = 'navigation_menu_item_setting_id';
    public $timestamps = false;

    protected $fillable = [
        'navigation_menu_item_id',
        'locale',
        'setting_name',
        'setting_value',
        'setting_type',
    ];

    protected $casts = [
        'navigation_menu_item_setting_id' => 'int',
        'navigation_menu_item_id' => 'int',
        'locale' => 'string',
        'setting_name' => 'string',
        'setting_value' => 'string',
        'setting_type' => 'string',
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
     * Navigation menu item this setting belongs to
     */
    public function navigationMenuItem(): BelongsTo
    {
        return $this->belongsTo(NavigationMenuItem::class, 'navigation_menu_item_id');
    }

}
