<?php

/**
 * @file classes/validation/ValidatorDateConparison.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ValidatorDateConparison
 *
 * @ingroup validation
 *
 * @see Validator
 *
 * @brief Validation check for comparing with a given date
 */

namespace PKP\validation;

use Carbon\Carbon;
use DateTimeInterface;
use Exception;
use PKP\validation\ValidatorFactory;

class ValidatorDateConparison extends Validator
{
    public const DATE_COMPARE_RULE_EQUAL = 'equal';
    public const DATE_COMPARE_RULE_GREATER = 'greater';
    public const DATE_COMPARE_RULE_LESSER = 'lesser';
    public const DATE_COMPARE_RULE_GREATER_OR_EQUAL = 'greaterOrEqual';
    public const DATE_COMPARE_RULE_LESSER_OR_EQUAL = 'lesserOrEqual';

    protected DateTimeInterface|Carbon $comparingDate;

    protected string $comparingRule;

    protected array $validationRulesMapping = [
        self::DATE_COMPARE_RULE_EQUAL               => 'date_equals',
        self::DATE_COMPARE_RULE_GREATER             => 'after',
        self::DATE_COMPARE_RULE_LESSER              => 'before',
        self::DATE_COMPARE_RULE_GREATER_OR_EQUAL    => 'after_or_equal',
        self::DATE_COMPARE_RULE_LESSER_OR_EQUAL     => 'before_or_equal',
    ];

    public function __construct(DateTimeInterface|Carbon $comparingDate, string $comparingRule)
    {
        if (!in_array($comparingRule, static::getComparingRules())) {
            throw new Exception(
                sprintf(
                    'Invalid comparison rule %s given, must be among [%s]',
                    $comparingRule,
                    implode(',', static::getComparingRules())
                )
            );
        }

        $this->comparingDate = $comparingDate instanceof Carbon ? $comparingDate : Carbon::parse($comparingDate);
        $this->comparingRule = $comparingRule;
    }

    public static function getComparingRules(): array
    {
        return [
            static::DATE_COMPARE_RULE_EQUAL,
            static::DATE_COMPARE_RULE_GREATER,
            static::DATE_COMPARE_RULE_LESSER,
            static::DATE_COMPARE_RULE_GREATER_OR_EQUAL,
            static::DATE_COMPARE_RULE_LESSER_OR_EQUAL ,
        ];
    }

    /**
     * @copydoc Validator::isValid()
     */
    public function isValid($value)
    {
        $validator = ValidatorFactory::make(
            ['value' => $value],
            ['value' => [
                'date', 
                $this->getValidationApplicableRule($this->comparingRule) . ':' . $this->comparingDate->toDateString()
            ]]
        );

        return $validator->passes();
    }

    protected function getValidationApplicableRule(string $rule): mixed
    {
        return $this->validationRulesMapping[$rule];
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\validation\ValidatorDateConparison', '\ValidatorDateConparison');
}
