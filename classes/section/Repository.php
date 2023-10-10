<?php
/**
 * @file classes/section/Repository.php
 *
 * Copyright (c) 2014-2023 Simon Fraser University
 * Copyright (c) 2003-2023 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Repository
 *
 * @brief A repository to find and manage sections.
 */

namespace PKP\section;

use APP\core\Request;
use APP\core\Services;
use APP\facades\Repo;
use APP\section\DAO;
use APP\section\Section;
use Illuminate\Support\Facades\App;
use PKP\context\Context;
use PKP\plugins\Hook;
use PKP\services\PKPSchemaService;
use PKP\validation\ValidatorFactory;

class Repository
{
    public DAO $dao;
    public string $schemaMap = maps\Schema::class;
    protected Request $request;
    protected PKPSchemaService $schemaService;

    public function __construct(DAO $dao, Request $request, PKPSchemaService $schemaService)
    {
        $this->dao = $dao;
        $this->request = $request;
        $this->schemaService = $schemaService;
    }

    /** @copydoc DAO::newDataObject() */
    public function newDataObject(array $params = []): Section
    {
        $object = $this->dao->newDataObject();
        if (!empty($params)) {
            $object->setAllData($params);
        }
        return $object;
    }

    /** @copydoc DAO::exists() */
    public function exists(int $id, int $contextId = null): bool
    {
        return $this->dao->exists($id, $contextId);
    }

    /** @copydoc DAO::get() */
    public function get(int $id, int $contextId = null): ?Section
    {
        return $this->dao->get($id, $contextId);
    }

    public function getCollector(): Collector
    {
        return App::make(Collector::class);
    }

    /**
     * Get an instance of the map class for mapping
     * sections to their schema
     */
    public function getSchemaMap(): maps\Schema
    {
        return app('maps')->withExtensions($this->schemaMap);
    }

    /**
     * Validate properties for a section
     *
     * Perform validation checks on data used to add or edit a section.
     *
     * @param Section|null $object Section being edited. Pass `null` if creating a new section
     * @param array $props A key/value array with the new data to validate
     *
     * @return array A key/value array with validation errors. Empty if no errors
     */
    public function validate(?Section $object, array $props, Context $context): array
    {
        $errors = [];
        $allowedLocales = $context->getSupportedSubmissionLocales();
        $primaryLocale = $context->getPrimaryLocale();

        $validator = ValidatorFactory::make(
            $props,
            $this->schemaService->getValidationRules($this->dao->schema, $allowedLocales)
        );

        // Check required fields if we're adding a section
        ValidatorFactory::required(
            $validator,
            $object,
            $this->schemaService->getRequiredProps($this->dao->schema),
            $this->schemaService->getMultilingualProps($this->dao->schema),
            $allowedLocales,
            $primaryLocale
        );

        // Check for input from disallowed locales
        ValidatorFactory::allowedLocales($validator, $this->schemaService->getMultilingualProps($this->dao->schema), $allowedLocales);

        // The contextId must match an existing context
        $validator->after(function ($validator) use ($props) {
            if (isset($props['contextId']) && !$validator->errors()->get('contextId')) {
                $sectionContext = Services::get('context')->get($props['contextId']);
                if (!$sectionContext) {
                    $validator->errors()->add('contextId', __('manager.sections.noContext'));
                }
            }
        });

        if ($validator->fails()) {
            $errors = $this->schemaService->formatValidationErrors($validator->errors());
        }

        Hook::call('Section::validate', [&$errors, $object, $props, $allowedLocales, $primaryLocale]);

        return $errors;
    }

    /** @copydoc DAO::insert() */
    public function add(Section $section): int
    {
        $id = $this->dao->insert($section);
        Hook::call('Section::add', [$section]);
        return $id;
    }

    /** @copydoc DAO::update() */
    public function edit(Section $section, array $params): void
    {
        $newSection = clone $section;
        $newSection->setAllData(array_merge($newSection->_data, $params));
        Hook::call('Section::edit', [$newSection, $section, $params]);
        $this->dao->update($newSection);
    }

    /**
     * Deletes all sections for a given context
     */
    public function deleteByContextId(int $contextId)
    {
        $collector = $this->getCollector()->filterByContextIds([$contextId]);
        $this->deleteMany($collector);
    }

    /** @copydoc DAO::delete() */
    public function delete(Section $section): void
    {
        Hook::call('Section::delete::before', [$section]);
        $this->dao->delete($section);
        Hook::call('Section::delete', [$section]);
    }

    /**
     * Delete a collection of sections
     */
    public function deleteMany(Collector $collector): void
    {
        foreach ($collector->getMany() as $section) {
            $this->delete($section);
        }
    }

    /**
     * Check if the section has any submissions assigned to it.
     */
    public function isEmpty(int $sectionId, int $contextId): bool
    {
        return Repo::submission()
            ->getCollector()
            ->filterByContextIds([$contextId])
            ->filterBySectionIds([$sectionId])
            ->getCount() === 0;
    }

    /**
     * Sequentially renumber sections in their sequence order.
     */
    public function resequence(int $contextId): void
    {
        $sections = $this->getCollector()->filterByContextIds([$contextId])->getMany();
        $seq = 0;
        foreach ($sections as $section) {
            $section->setSequence($seq);
            $this->dao->update($section);
            $seq++;
        }
    }

    /**
     * Get array of sections containing id, title and group (isInactive) key
     */
    public function getSectionList(int $contextId, bool $excludeInactive = false): array
    {
        return $this->getCollector()
            ->filterByContextIds([$contextId])
            ->excludeInactive($excludeInactive)
            ->getMany()
            ->map(fn (Section $section) => [
                'id' => $section->getId(),
                'title' => $section->getLocalizedTitle(),
                'group' => $section->getIsInactive()
            ])
            ->all();
    }
}
