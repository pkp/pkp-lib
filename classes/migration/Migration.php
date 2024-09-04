<?php

/**
 * @file classes/migration/Migration.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Migration
 *
 * @brief Base class for PKP migrations.
 */

namespace PKP\migration;

use PKP\install\Installer;
use Illuminate\Support\Facades\Schema;

abstract class Migration extends \Illuminate\Database\Migrations\Migration
{
    protected array $_attributes;
    protected Installer $_installer;

    /**
     * Constructor
     */
    public function __construct(Installer $installer, array $attributes)
    {
        $this->_attributes = $attributes;
        $this->_installer = $installer;
    }

    /**
     * Check if the given key is set as a foreign in the given table
     */
    public function hasForeignKey(string $tableName, string $keyName): bool
    {
        return collect(Schema::getForeignKeys($tableName))
            ->pluck('name')
            ->contains($keyName);
    }

    /**
     * Run the migrations.
     */
    abstract public function up(): void;

    /**
     * Reverse the migration.
     */
    abstract public function down(): void;
}
