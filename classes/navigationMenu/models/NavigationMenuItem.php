<?php

/**
 * @file classes/navigationMenu/models/NavigationMenuItem.php
 *
 * Copyright (c) 2014-2025 Simon Fraser University
 * Copyright (c) 2003-2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class NavigationMenuItem
 *
 * @brief Laravel Eloquent model for navigation menu items with settings support
 */

namespace PKP\navigationMenu\models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use PKP\core\traits\ModelWithSettings;

class NavigationMenuItem extends Model
{
    use ModelWithSettings;

    protected $table = 'navigation_menu_items';
    protected $primaryKey = 'navigation_menu_item_id';
    public $timestamps = false;

    protected $fillable = [
        'context_id',
        'path',
        'type',
    ];

    protected $casts = [
        'navigation_menu_item_id' => 'int',
        'context_id' => 'int',
        'path' => 'string',
        'type' => 'string',
    ];

    /**
     * @inheritDoc
     */
    public function getSettingsTable(): string
    {
        return 'navigation_menu_item_settings';
    }

    /**
     * @inheritDoc
     */
    public static function getSchemaName(): ?string
    {
        return null;
    }

    /**
     * @inheritDoc
     */
    public function getSettings(): array
    {
        return [
            'title',
            'titleLocaleKey',
            'sequence',
            'remoteUrl',
        ];
    }

    /**
     * @inheritDoc
     */
    public function getMultilingualProps(): array
    {
        return [
            'title',
        ];
    }

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
     * Navigation menu item assignments for this item
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(NavigationMenuItemAssignment::class, 'navigation_menu_item_id');
    }

    /**
     * Navigation menus this item is assigned to (through assignments)
     */
    public function navigationMenus(): BelongsToMany
    {
        return $this->belongsToMany(
            NavigationMenu::class,
            'navigation_menu_item_assignments',
            'navigation_menu_item_id',
            'navigation_menu_id'
        )->withPivot(['parent_id', 'seq']);
    }

    /**
     * Settings for this navigation menu item
     */
    public function settings(): HasMany
    {
        return $this->hasMany(NavigationMenuItemSetting::class, 'navigation_menu_item_id');
    }

    /**
     * Get localized title from settings
     */
    public function getLocalizedTitle(?string $locale = null): string
    {
        $locale = $locale ?? app()->getLocale();
        $titleKey = $this->getSetting('titleLocaleKey', $locale);
        if ($titleKey) {
            return __($titleKey, [], $locale);
        }

        return $this->getSetting('title', $locale) ?? '';
    }

}
