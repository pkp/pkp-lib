<?php

/**
 * @file classes/services/GalleyService.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class GalleyService
 * @ingroup services
 *
 * @brief Helper class that encapsulates galley business logic
 */

namespace APP\services;

use APP\core\Services;
use APP\facades\Repo;
use APP\services\queryBuilders\GalleyQueryBuilder;
use APP\submission\Submission;
use PKP\core\PKPApplication;
use PKP\db\DAORegistry;
use PKP\db\DAOResultFactory;
use PKP\plugins\HookRegistry;
use PKP\services\interfaces\EntityPropertyInterface;
use PKP\services\interfaces\EntityReadInterface;
use PKP\services\interfaces\EntityWriteInterface;
use PKP\services\PKPSchemaService;
use PKP\validation\ValidatorFactory;

class GalleyService implements EntityReadInterface, EntityWriteInterface, EntityPropertyInterface
{
    /**
     * @copydoc \PKP\services\interfaces\EntityReadInterface::get()
     */
    public function get($galleyId)
    {
        $preprintGalleyDao = DAORegistry::getDAO('PreprintGalleyDAO'); /** @var PreprintGalleyDAO $preprintGalleyDao */
        return $preprintGalleyDao->getById($galleyId);
    }

    /**
     * @copydoc \PKP\services\interfaces\EntityReadInterface::getCount()
     */
    public function getCount($args = [])
    {
        return $this->getQueryBuilder($args)->getCount();
    }

    /**
     * @copydoc \PKP\services\interfaces\EntityReadInterface::getIds()
     */
    public function getIds($args = [])
    {
        return $this->getQueryBuilder($args)->getIds();
    }

    /**
     * Get a collection of Galley objects limited, filtered
     * and sorted by $args
     *
     * @param array $args {
     *    @option int|array publicationIds
     * }
     *
     * @return \Iterator
     */
    public function getMany($args = [])
    {
        $galleyQO = $this->getQueryBuilder($args)->getQuery();
        $galleyDao = DAORegistry::getDAO('PreprintGalleyDAO');
        $result = $galleyDao->retrieveRange($galleyQO->toSql(), $galleyQO->getBindings());
        $queryResults = new DAOResultFactory($result, $galleyDao, '_fromRow');

        return $queryResults->toIterator();
    }

    /**
     * @copydoc \PKP\services\interfaces\EntityReadInterface::getMax()
     */
    public function getMax($args = [])
    {
        // Count/offset is not supported so getMax is always
        // the same as getCount
        return $this->getCount();
    }

    /**
     * @copydoc \PKP\services\interfaces\EntityReadInterface::getQueryBuilder()
     *
     * @return GalleryQueryBuilder
     */
    public function getQueryBuilder($args = [])
    {
        $galleyQB = new GalleyQueryBuilder();

        if (!empty($args['contextIds'])) {
            $galleyQB->filterByContexts($args['contextIds']);
        }
        if (!empty($args['publicationIds'])) {
            $galleyQB->filterByPublicationIds($args['publicationIds']);
        }

        HookRegistry::call('Galley::getMany::queryBuilder', [&$galleyQB, $args]);

        return $galleyQB;
    }

    /**
     * @copydoc \PKP\services\interfaces\EntityPropertyInterface::getProperties()
     *
     * @param null|mixed $args
     */
    public function getProperties($galley, $props, $args = null)
    {
        $request = $args['request'];
        $context = $request->getContext();
        $dispatcher = $request->getDispatcher();

        $publication = !empty($args['publication'])
            ? $args['publication']
            : $args['publication'] = Repo::publication()->get($galley->getData('publicationId'));

        $submission = !empty($args['submission'])
            ? $args['submission']
            : $args['submission'] = Repo::submission()->get($publication->getData('submissionId'));
        $values = [];

        foreach ($props as $prop) {
            switch ($prop) {
                case 'doiObject':
                    if ($galley->getData('doiObject')) {
                        $retVal = Repo::doi()->getSchemaMap()->summarize($galley->getData('doiObject'));
                    } else {
                        $retVal = null;
                    }
                    $values[$prop] = $retVal;
                    break;
                case 'urlPublished':
                    $values[$prop] = $dispatcher->url(
                        $request,
                        PKPApplication::ROUTE_PAGE,
                        $context->getPath(),
                        'preprint',
                        'view',
                        [
                            $submission->getBestId(),
                            'version',
                            $publication->getId(),
                            $galley->getBestGalleyId(),
                        ]
                    );
                    break;
                case 'file':
                    $values[$prop] = null;
                    $submissionFile = Repo::submissionFile()->get($galley->getData('submission_file_id'));

                    if (empty($submissionFile)) {
                        break;
                    }

                    $values[$prop] = Repo::submissionFile()
                        ->getSchemaMap()
                        ->map($submissionFile);

                    break;
                default:
                    $values[$prop] = $galley->getData($prop);
                    break;
            }
        }

        $values = Services::get('schema')->addMissingMultilingualValues(PKPSchemaService::SCHEMA_GALLEY, $values, $context->getSupportedSubmissionLocales());

        HookRegistry::call('Galley::getProperties::values', [&$values, $galley, $props, $args]);

        ksort($values);

        return $values;
    }

    /**
     * @copydoc \PKP\services\interfaces\EntityPropertyInterface::getSummaryProperties()
     *
     * @param null|mixed $args
     */
    public function getSummaryProperties($galley, $args = null)
    {
        $props = Services::get('schema')->getSummaryProps(PKPSchemaService::SCHEMA_GALLEY);

        return $this->getProperties($galley, $props, $args);
    }

    /**
     * @copydoc \PKP\services\interfaces\EntityPropertyInterface::getFullProperties()
     *
     * @param null|mixed $args
     */
    public function getFullProperties($galley, $args = null)
    {
        $props = Services::get('schema')->getFullProps(PKPSchemaService::SCHEMA_GALLEY);

        return $this->getProperties($galley, $props, $args);
    }

    /**
     * @copydoc \PKP\services\entityProperties\EntityWriteInterface::validate()
     */
    public function validate($action, $props, $allowedLocales, $primaryLocale)
    {
        $schemaService = Services::get('schema');

        $validator = ValidatorFactory::make(
            $props,
            $schemaService->getValidationRules(PKPSchemaService::SCHEMA_GALLEY, $allowedLocales),
            [
                'locale.regex' => __('validator.localeKey'),
                'urlPath.regex' => __('validator.alpha_dash_period'),
            ]
        );

        // Check required fields
        ValidatorFactory::required(
            $validator,
            $action,
            $schemaService->getRequiredProps(PKPSchemaService::SCHEMA_GALLEY),
            $schemaService->getMultilingualProps(PKPSchemaService::SCHEMA_GALLEY),
            $allowedLocales,
            $primaryLocale
        );

        // Check for input from disallowed locales
        ValidatorFactory::allowedLocales($validator, $schemaService->getMultilingualProps(PKPSchemaService::SCHEMA_GALLEY), $allowedLocales);

        // The publicationId must match an existing publication that is not yet published
        $validator->after(function ($validator) use ($props) {
            if (isset($props['publicationId']) && !$validator->errors()->get('publicationId')) {
                $publication = Repo::publication()->get($props['publicationId']);
                if (!$publication) {
                    $validator->errors()->add('publicationId', __('galley.publicationNotFound'));
                } elseif ($publication->getData('status') === Submission::STATUS_PUBLISHED) {
                    $validator->errors()->add('publicationId', __('galley.editPublishedDisabled'));
                }
            }
        });

        if ($validator->fails()) {
            $errors = $schemaService->formatValidationErrors($validator->errors(), $schemaService->get(PKPSchemaService::SCHEMA_GALLEY), $allowedLocales);
        }

        HookRegistry::call('Galley::validate', [&$errors, $action, $props, $allowedLocales, $primaryLocale]);

        return $errors;
    }

    /**
     * @copydoc \PKP\services\entityProperties\EntityWriteInterface::add()
     */
    public function add($galley, $request)
    {
        $preprintGalleyDao = DAORegistry::getDAO('PreprintGalleyDAO'); /** @var PreprintGalleyDAO $preprintGalleyDao */
        $galleyId = $preprintGalleyDao->insertObject($galley);
        $galley = $this->get($galleyId);

        HookRegistry::call('Galley::add', [&$galley, $request]);

        return $galley;
    }

    /**
     * @copydoc \PKP\services\entityProperties\EntityWriteInterface::edit()
     */
    public function edit($galley, $params, $request)
    {
        $galleyDao = DAORegistry::getDAO('PreprintGalleyDAO');

        $newGalley = $galleyDao->newDataObject();
        $newGalley->_data = array_merge($galley->_data, $params);

        HookRegistry::call('Galley::edit', [&$newGalley, $galley, $params, $request]);

        $galleyDao->updateObject($newGalley);
        $newGalley = $this->get($newGalley->getId());

        return $newGalley;
    }

    /**
     * @copydoc \PKP\services\entityProperties\EntityWriteInterface::delete()
     */
    public function delete($galley)
    {
        HookRegistry::call('Galley::delete::before', [&$galley]);

        $preprintGalleyDao = DAORegistry::getDAO('PreprintGalleyDAO'); /** @var PreprintGalleyDAO $preprintGalleyDao */
        $preprintGalleyDao->deleteObject($galley);

        // Delete related submission files

        $collector = Repo::submissionFile()
            ->getCollector()
            ->filterByAssoc(ASSOC_TYPE_GALLEY, [$galley->getId()]);

        $submissionFiles = Repo::submissionFile()->getMany($collector);
        foreach ($submissionFiles as $submissionFile) {
            Repo::submissionFile()->delete($submissionFile);
        }

        HookRegistry::call('Galley::delete', [&$galley]);
    }
}
