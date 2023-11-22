<?php

/**
 * @file classes/userGroup/relationships/UserUserGroup.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class \PKP\userGroup\relationships\UserUserGroup
 *
 * @brief UserUserGroup metadata class.
 */

namespace PKP\userGroup\relationships;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use ReflectionProperty;

class CustomQueryBuilder extends Builder
{
    // public function with($relations, $callback = null)
    // {
    //     if (is_string($relations)) {
    //         $relations = func_get_args();
    //         // Remove the callback from the relations array.
    //         array_pop($relations);
    //     }

    //     $customRelations = [];
    //     $eloquentRelations = [];

    //     foreach ($relations as $relation) {
    //         if (method_exists($this->model, $relation) && is_a($this->model->$relation(), Attribute::class)) {
    //             $customRelations[] = $relation;
    //         } else {
    //             $eloquentRelations[] = $relation;
    //         }
    //     }

    //     if (!empty($customRelations)) {
    //         // Handle loading of custom relations.
    //         // You can define this logic according to your needs.
    //     }

    //     return parent::with($eloquentRelations, $callback);
    // }
    public function with($relations, $callback = null)
    {
        if (is_string($relations)) {
            $relations = func_get_args();
            // Remove the callback from the relations array.
            if (is_callable(end($relations))) {
                array_pop($relations);
            }
        }

        $customRelations = [];
        $eloquentRelations = [];

        foreach ($relations as $relation) {
            if (method_exists($this->model, $relation) && is_a($this->model->$relation(), Attribute::class)) {
                $customRelations[] = $relation;
            } else {
                $eloquentRelations[] = $relation;
            }
        }

        // Set a property on the builder indicating the custom relations to be loaded.
        $this->withCustom = $customRelations;

        return parent::with($eloquentRelations, $callback);
    }

    public function get($columns = ['*'])
    {
        $results = parent::get($columns);

        if (!empty($this->withCustom)) {
            foreach ($results as $result) {
                foreach ($this->withCustom as $relation) {
                    // Fetch the Attribute instance from the relation method
                    $attributeInstance = $result->$relation();
                    
                    // Use the Attribute's get method to fetch the related object
                    $reflection = new ReflectionProperty($attributeInstance, 'get');
                    $reflection->setAccessible(true);
                    $getClosure = $reflection->getValue($attributeInstance);
                    
                    // Use the retrieved closure to fetch the related object
                    $value = $getClosure(fn() => null, $result->getAttributes());
                    
                    // Set the fetched object on the result
                    $result->setAttribute($relation, $value);
                }
            }
        }

        return $results;
    }
}
