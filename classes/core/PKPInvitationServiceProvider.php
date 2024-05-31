<?php

/**
 * @file classes/core/PKPInvitationServiceProvider.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPInvitationServiceProvider
 *
 * @brief Registers a new service provider for Invitations
 */

namespace PKP\core;

use Illuminate\Support\ServiceProvider;
use PKP\invitation\core\PKPInvitationFactory;
use PKP\invitation\invitations\Invitation;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class PKPInvitationServiceProvider extends ServiceProvider
{
    public function register(): void 
    {
        // Ensures that PKPInvitationFactory is initialized only once
        $this->app->singleton(Invitation::class, function ($app) {
            PKPInvitationFactory::init();
            return PKPInvitationFactory::getInstance();
        });
    }

    public function boot(): void
    {
        $this->preloadInvitationClasses();
    }

    protected function preloadInvitationClasses(): void
    {
        $invitationDirectories = [
            'PKP\invitations' => __DIR__ . '/../../invitations',
            'APP\invitations' => __DIR__ . '/../../../../invitations',
        ];

        foreach ($invitationDirectories as $namespace => $path) {
            if (!is_dir($path)) {
                continue;
            }

            $invitationDirectory = new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS);
            $iterator = new RecursiveIteratorIterator($invitationDirectory);

            foreach ($iterator as $file) {
                if ($file->isFile() && preg_match('/Invite\.php$/', $file->getFilename())) {
                    $className = $namespace . '\\' . str_replace('.php', '', $file->getFilename());

                    class_exists($className, true);
                }
            }
        }
    }
}
