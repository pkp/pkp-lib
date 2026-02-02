<?php

/**
 * @file I11583_ClassNamespaceFromDotNotationClassPath.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I11583_ClassNamespaceFromDotNotationClassPath
 *
 * @brief 
 */

namespace PKP\migration\upgrade\v3_6_0;

use Illuminate\Support\Facades\DB;
use PKP\install\DowngradeNotSupportedException;
use PKP\migration\Migration;

class I11583_ClassNamespaceFromDotNotationClassPath extends Migration
{
    /**
     * @inheritDoc
     */
    public function up(): void
    {
        $rows = DB::table('filter_groups')->get();

        foreach ($rows as $row) {
            $updated = false;

            $new_input_type = $this->convertClassPath($row->input_type);
            if ($new_input_type !== $row->input_type) {
                $updated = true;
            }

            $new_output_type = $this->convertClassPath($row->output_type);
            if ($new_output_type !== $row->output_type) {
                $updated = true;
            }

            if ($updated) {
                DB::table('filter_groups')
                    ->where('filter_group_id', $row->filter_group_id)
                    ->update([
                        'input_type' => $new_input_type,
                        'output_type' => $new_output_type,
                    ]);
            }
        }
    }

    /**
     * @inheritDoc
     */
    public function down(): void
    {
        throw new DowngradeNotSupportedException();
    }

    /**
     * Convert dot notation class path to namespace if applicable.
     */
    private function convertClassPath(string $type): string
    {
        if (!str_starts_with($type, 'class::')) {
            return $type;
        }

        $path = substr($type, 7); // Remove 'class::'
        $is_array = str_ends_with($path, '[]');
        if ($is_array) {
            $path = substr($path, 0, -2);
        }

        $namespace_path = $path;

        if (strpos($path, '.') !== false) {
            // Dot notation, convert to namespace
            $parts = explode('.', $path);

            if ($parts[0] === 'lib' && isset($parts[1]) && $parts[1] === 'pkp') {
                $start = 2;
                if (isset($parts[2]) && $parts[2] === 'classes') {
                    $start = 3;
                }
                $namespace_path = 'PKP\\' . implode('\\', array_slice($parts, $start));
            } elseif ($parts[0] === 'classes') {
                $namespace_path = 'APP\\' . implode('\\', array_slice($parts, 1));
            } elseif ($parts[0] === 'plugins') {
                $namespace_path = 'APP\\' . implode('\\', $parts);
            }
            // Else leave as is
        } else {
            // Already in namespace format, ensure leading backslash
            if (strpos($path, '\\') !== false && !str_starts_with($path, '\\')) {
                $namespace_path = '\\' . $path;
            }
        }

        return 'class::' . $namespace_path . ($is_array ? '[]' : '');
    }
}
