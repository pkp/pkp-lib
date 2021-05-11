<?php
/**
 * @file classes/services/PKPAnnouncementService.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPAnnouncementService
 * @ingroup services
 *
 * @brief Helper class that encapsulates business logic for publications
 */

namespace PKP\services;

use APP\core\Services;
use PKP\core\Core;
use PKP\db\DAORegistry;
use PKP\db\DAOResultFactory;
use PKP\db\DBResultRange;
use PKP\services\interfaces\EntityPropertyInterface;
use PKP\services\interfaces\EntityReadInterface;
use PKP\services\interfaces\EntityWriteInterface;
use PKP\services\queryBuilders\PKPAnnouncementQueryBuilder;

use PKP\validation\ValidatorFactory;

class PKPAnnouncementService implements EntityPropertyInterface, EntityReadInterface, EntityWriteInterface
{
    /**
     * @copydoc \PKP\services\interfaces\EntityReadInterface::get()
     */
    public function get($announcementId)
    {
        return DAORegistry::getDAO('AnnouncementDAO')->getById($announcementId);
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
     * @copydoc \PKP\services\interfaces\EntityReadInterface::getMany()
     */
    public function getMany($args = [])
    {
        $range = null;
        if (isset($args['count'])) {
            $range = new DBResultRange($args['count'], null, $args['offset'] ?? 0);
        }
        // Pagination is handled by the DAO, so don't pass count and offset
        // arguments to the QueryBuilder.
        if (isset($args['count'])) {
            unset($args['count']);
        }
        if (isset($args['offset'])) {
            unset($args['offset']);
        }
        $announcementQO = $this->getQueryBuilder($args)->getQuery();
        $announcementDao = DAORegistry::getDAO('AnnouncementDAO');
        $result = $announcementDao->retrieveRange($announcementQO->toSql(), $announcementQO->getBindings(), $range);
        $queryResults = new DAOResultFactory($result, $announcementDao, '_fromRow');

        return $queryResults->toIterator();
    }

    /**
     * @copydoc \PKP\services\interfaces\EntityReadInterface::getMax()
     *
     * @param null|mixed $args
     */
    public function getMax($args = null)
    {
        // Don't accept args to limit the results
        if (isset($args['count'])) {
            unset($args['count']);
        }
        if (isset($args['offset'])) {
            unset($args['offset']);
        }
        return (int) $this->getQueryBuilder($args)->getCount();
    }

    /**
     * @copydoc \PKP\services\interfaces\EntityReadInterface::getQueryBuilder()
     */
    public function getQueryBuilder($args = [])
    {
        $defaultArgs = [
            'contextIds' => null,
            'searchPhrase' => '',
            'typeIds' => null,
        ];

        $args = array_merge($defaultArgs, $args);

        $announcementQB = new PKPAnnouncementQueryBuilder();
        if (!empty($args['contextIds'])) {
            $announcementQB->filterByContextIds($args['contextIds']);
        }
        if (!empty($args['searchPhrase'])) {
            $announcementQB->searchPhrase($args['searchPhrase']);
        }
        if (!empty($args['typeIds'])) {
            $announcementQB->filterByTypeIds($args['typeIds']);
        }

        \HookRegistry::call('Announcement::getMany::queryBuilder', [&$announcementQB, $args]);

        return $announcementQB;
    }

    /**
     * @copydoc \PKP\services\interfaces\EntityPropertyInterface::getProperties()
     *
     * @param null|mixed $args
     */
    public function getProperties($announcement, $props, $args = null)
    {
        $request = $args['request'];
        $announcementContext = $args['announcementContext'];
        $dispatcher = $request->getDispatcher();

        $values = [];

        foreach ($props as $prop) {
            switch ($prop) {
                case '_href':
                    $values[$prop] = $dispatcher->url(
                        $request,
                        \PKPApplication::ROUTE_API,
                        $announcementContext->getData('urlPath'),
                        'announcements/' . $announcement->getId()
                    );
                    break;
                default:
                    $values[$prop] = $announcement->getData($prop);
                    break;
            }
        }

        $values = Services::get('schema')->addMissingMultilingualValues(PKPSchemaService::SCHEMA_ANNOUNCEMENT, $values, $announcementContext->getSupportedFormLocales());

        \HookRegistry::call('Announcement::getProperties', [&$values, $announcement, $props, $args]);

        ksort($values);

        return $values;
    }

    /**
     * @copydoc \PKP\services\interfaces\EntityPropertyInterface::getSummaryProperties()
     *
     * @param null|mixed $args
     */
    public function getSummaryProperties($announcement, $args = null)
    {
        $props = Services::get('schema')->getSummaryProps(PKPSchemaService::SCHEMA_ANNOUNCEMENT);

        return $this->getProperties($announcement, $props, $args);
    }

    /**
     * @copydoc \PKP\services\interfaces\EntityPropertyInterface::getFullProperties()
     *
     * @param null|mixed $args
     */
    public function getFullProperties($announcement, $args = null)
    {
        $props = Services::get('schema')->getFullProps(PKPSchemaService::SCHEMA_ANNOUNCEMENT);

        return $this->getProperties($announcement, $props, $args);
    }

    /**
     * @copydoc \PKP\services\interfaces\EntityWriteInterface::validate()
     */
    public function validate($action, $props, $allowedLocales, $primaryLocale)
    {
        \AppLocale::requireComponents(
            LOCALE_COMPONENT_PKP_MANAGER,
            LOCALE_COMPONENT_APP_MANAGER
        );
        $schemaService = Services::get('schema');

        $validator = ValidatorFactory::make(
            $props,
            $schemaService->getValidationRules(PKPSchemaService::SCHEMA_ANNOUNCEMENT, $allowedLocales),
            [
                'dateExpire.date_format' => __('stats.dateRange.invalidDate'),
            ]
        );

        // Check required fields if we're adding a context
        ValidatorFactory::required(
            $validator,
            $action,
            $schemaService->getRequiredProps(PKPSchemaService::SCHEMA_ANNOUNCEMENT),
            $schemaService->getMultilingualProps(PKPSchemaService::SCHEMA_ANNOUNCEMENT),
            $allowedLocales,
            $primaryLocale
        );

        // Check for input from disallowed locales
        ValidatorFactory::allowedLocales($validator, $schemaService->getMultilingualProps(PKPSchemaService::SCHEMA_ANNOUNCEMENT), $allowedLocales);

        if ($validator->fails()) {
            $errors = $schemaService->formatValidationErrors($validator->errors(), $schemaService->get(PKPSchemaService::SCHEMA_ANNOUNCEMENT), $allowedLocales);
        }

        \HookRegistry::call('Announcement::validate', [&$errors, $action, $props, $allowedLocales, $primaryLocale]);

        return $errors;
    }

    /**
     * @copydoc \PKP\services\entityProperties\EntityWriteInterface::add()
     */
    public function add($announcement, $request)
    {
        $announcement->setData('datePosted', Core::getCurrentDate());
        DAORegistry::getDao('AnnouncementDAO')->insertObject($announcement);
        \HookRegistry::call('Announcement::add', [&$announcement, $request]);

        return $announcement;
    }

    /**
     * @copydoc \PKP\services\entityProperties\EntityWriteInterface::edit()
     */
    public function edit($announcement, $params, $request)
    {
        $newAnnouncement = DAORegistry::getDAO('AnnouncementDAO')->newDataObject();
        $newAnnouncement->_data = array_merge($announcement->_data, $params);

        \HookRegistry::call('Announcement::edit', [&$newAnnouncement, $announcement, $params, $request]);

        DAORegistry::getDAO('AnnouncementDAO')->updateObject($newAnnouncement);
        $newAnnouncement = $this->get($newAnnouncement->getId());

        return $newAnnouncement;
    }

    /**
     * @copydoc \PKP\services\entityProperties\EntityWriteInterface::delete()
     */
    public function delete($announcement)
    {
        \HookRegistry::call('Announcement::delete::before', [&$announcement]);
        DAORegistry::getDao('AnnouncementDAO')->deleteObject($announcement);
        \HookRegistry::call('Announcement::delete', [&$announcement]);
    }
}
