<?php

/**
 * @file classes/migration/upgrade/v3_4_0/I10962_UpdateEmailTemplateVariables.php
 *
 * Copyright (c) 2014-2025 Simon Fraser University
 * Copyright (c) 2000-2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I10962_UpdateEmailTemplateVariables
 *
 * @brief Remap legacy {journal|press|server}* tokens to {context*} in email template subjects and bodies.
 */

namespace PKP\migration\upgrade\v3_4_0;

use Illuminate\Support\Facades\DB;
use PKP\install\DowngradeNotSupportedException;
use PKP\migration\Migration;
use APP\core\Application;

class I10962_UpdateEmailTemplateVariables extends Migration
{
    /** @return 'ojs2'|'omp'|'ops'|null */
    protected function getAppName(): ?string
    {
        return Application::get()?->getName();
    }

    /** @return array<string,string> */
    protected function getMappings(): array
    {
        return match ($this->getAppName()) {
            'ojs2' => [ // OJS
                'journalAcronym' => 'contextAcronym',
                'journalName' => 'contextName',
                'journalUrl' => 'contextUrl',
                'journalSignature'=> 'contextSignature',
            ],
            'omp' => [ // OMP
                'pressAcronym' => 'contextAcronym',
                'pressName' => 'contextName',
                'pressUrl' => 'contextUrl',
                'pressSignature' => 'contextSignature',
            ],
            'ops' => [ // OPS
                'serverAcronym' => 'contextAcronym',
                'serverName' => 'contextName',
                'serverUrl'  => 'contextUrl',
                'serverSignature' => 'contextSignature',
            ],
            default => [],
        };
    }

    public function up(): void
    {
        foreach ($this->getMappings() as $old => $new) {
            $oldToken = '{$' . $old . '}';
            $newToken = '{$' . $new . '}';
            $like = '%' . $oldToken . '%';

            DB::update(
                'UPDATE email_templates_default_data
                   SET subject = replace(subject, ?, ?),
                       body    = replace(body,    ?, ?)
                 WHERE subject LIKE ? OR body LIKE ?',
                [$oldToken, $newToken, $oldToken, $newToken, $like, $like]
            );

            DB::update(
                'UPDATE email_templates_settings
                    SET setting_value = replace(setting_value, ?, ?)
                  WHERE setting_name IN (?, ?)
                    AND setting_value LIKE ?',
                [$oldToken, $newToken, 'subject', 'body', $like]
            );
        }
    }

    public function down(): void
    {
        throw new DowngradeNotSupportedException(__CLASS__);
    }
}
