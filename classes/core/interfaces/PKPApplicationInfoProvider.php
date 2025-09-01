<?php

/**
 * @file classes/core/interfaces/PKPApplication.php
 *
 * Copyright (c) 2014-2025 Simon Fraser University
 * Copyright (c) 2000-2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPApplicationInfoProvider
 *
 * @brief Contract to define the application info provider.
 *
 */

namespace PKP\core\interfaces;

use PKP\context\ContextDAO;
use PKP\db\DAO;
use PKP\submission\RepresentationDAOInterface;

interface PKPApplicationInfoProvider
{
    /**
     * Get the top-level context DAO.
     *
     * @hook PKPApplication::execute::catch ['throwable' => $t]
     */
    public static function getContextDAO(): ContextDAO;

    /**
     * Get the representation DAO.
     *
     * @hook PKPApplication::execute::catch ['throwable' => $t]
     */
    public static function getRepresentationDAO(): DAO|RepresentationDAOInterface;

    /**
     * Get the stages used by the application.
     *
     * @hook PKPApplication::execute::catch ['throwable' => $t]
     */
    public static function getApplicationStages(): array;

    /**
     * Get the file directory array map used by the application.
     * should return array('context' => ..., 'submission' => ...)
     *
     * @hook PKPApplication::execute::catch ['throwable' => $t]
     */
    public static function getFileDirectories(): array;

    /**
     * Returns the context type for this application.
     *
     * @hook PKPApplication::execute::catch ['throwable' => $t]
     */
    public static function getContextAssocType(): int;

    /**
     * Get the review workflow stages used by this application.
     *
     * @hook PKPApplication::execute::catch ['throwable' => $t]
     */
    public function getReviewStages(): array;

    /**
     * Define if the application has customizable reviewer recommendation functionality
     *
     * @hook PKPApplication::execute::catch ['throwable' => $t]
     */
    public function hasCustomizableReviewerRecommendation(): bool;

    /**
     * Define the application namespace (for example, APP\ for OJS)
     *
     * @hook PKPApplication::execute::catch ['throwable' => $t]
     */
    public function getNamespace(): string;
}
