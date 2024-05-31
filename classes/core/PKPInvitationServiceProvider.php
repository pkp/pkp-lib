<?php

namespace PKP\core;

use Illuminate\Support\Facades\Cache;
use DateInterval;
use Illuminate\Support\ServiceProvider;
use PKP\invitation\core\PKPInvitationFactory;
use PKP\invitation\core\Invitation;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;

class PKPInvitationServiceProvider extends ServiceProvider
{
    protected const MAX_CACHE_LIFETIME = '1 day';
    
    public function register(): void 
    {
        // Ensures that PKPInvitationFactory is initialized only once
        $this->app->singleton(Invitation::class, function ($app) {
            PKPInvitationFactory::init($this->getInvitations());
            return PKPInvitationFactory::getInstance();
        });
    }

    protected function discoverInvitationsWithin(): array
    {
        return [
            $this->app->basePath('lib/pkp/classes/invitation/invitations'),
            $this->app->basePath('classes/invitation/invitations'),
            $this->app->basePath('plugins'),
        ];
    }

    public function discoverInvitations(): array
    {
        return collect($this->discoverInvitationsWithin())
            ->reject(function ($directory) {
                return !is_dir($directory);
            })
            ->reduce(function ($discovered, $directory) {
                return array_merge_recursive(
                    $discovered,
                    $this->scanDirectoryForInvitations($directory)
                );
            }, []);
    }

    protected function scanDirectoryForInvitations(string $directory): array
    {
        $invitationClasses = [];

        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));

        foreach ($iterator as $file) {
            if ($file->isFile() && preg_match('/Invite\.php$/', $file->getFilename())) {
                $class = Core::classFromFile($file);
                if (class_exists($class) && is_subclass_of($class, Invitation::class)) {
                    $reflectedClass = new ReflectionClass($class);

                    if (!$reflectedClass->isAbstract()) {
                        $type = $class::getType();
                        $invitationClasses[$type] = $class;
                    }
                }
            }
        }

        return $invitationClasses;
    }

    /**
     * Clears the invitation cache
     */
    public static function clearCache(): void
    {
        Cache::forget(static::getCacheKey());
    }

    /**
     * Retrieves a unique and static key to store the invitation cache
     */
    private static function getCacheKey(): string
    {
        return __METHOD__ . static::MAX_CACHE_LIFETIME;
    }

    public function getInvitations(): array
    {
        $expiration = DateInterval::createFromDateString(static::MAX_CACHE_LIFETIME);
        return Cache::remember(static::getCacheKey(), $expiration, fn () => $this->discoverInvitations());
    }
}
