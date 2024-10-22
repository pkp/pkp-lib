<?php

/**
 * @file classes/core/casts/MultilingualSettingAttribute.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class MultilingualSettingAttribute
 *
 * @brief   Caster to cast the viven values of multilingual attribute in proper
 *          format before storing in DB.
 */

namespace PKP\core\casts;

use Exception;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Contracts\Database\Eloquent\CastsInboundAttributes;
use PKP\core\traits\ModelWithSettings;
use PKP\facades\Locale;

class MultilingualSettingAttribute implements CastsInboundAttributes
{
    /**
     * Prepare the given value for storage.
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): array
    {
        if (!in_array(ModelWithSettings::class, class_uses_recursive(get_class($model)))) {
            throw new Exception(
                sprintf(
                    "model class %s does not support multilingual setting attributes/properties",
                    get_class($model)
                )
            );
        }

        $key = Str::camel($key);

        if (!in_array($key, $model->getMultilingualProps())) {
            throw new Exception(
                'Applying multilingual casting on non-maltilingual attribute is not allowed'
            );
        }
        
        if (is_string($value)) {
            return [$key =>  [Locale::getLocale() => $value]];
        }

        return [$key => array_filter($value)];
    }
}