<?php

/**
 * @file classes/core/MailServiceProvider.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class MailServiceProvider
 * @ingroup core
 *
 * @brief Registers Laravel's Mailer service without support for markup rendering, such as blade or markdown templates
 */

namespace PKP\core;

use PKP\mail\Mailer;
use Illuminate\Mail\MailManager;
use Illuminate\Mail\MailServiceProvider as IlluminateMailService;
use InvalidArgumentException;
use Symfony\Component\Mailer\Transport\SendmailTransport;

class MailServiceProvider extends IlluminateMailService
{
    /**
     * Register mailer excluding markdown renderer
     */
    public function register() : void
    {
        $this->registerIlluminateMailer();
    }

    /**
     * @copydoc \Illuminate\Mail\MailServiceProvider::registerIlluminateMailer()
     */
    public function registerIlluminateMailer() : void
    {
        $this->app->singleton('mail.manager', function ($app) {
            return new class($app) extends MailManager
            {
                /**
                 * @see MailManager::resolve()
                 *
                 * @param string $name
                 *
                 * @throws InvalidArgumentException
                 */
                protected function resolve($name) : Mailer
                {
                    $config = $this->getConfig($name);

                    if (is_null($config)) {
                        throw new InvalidArgumentException("Mailer [{$name}] is not defined.");
                    }

                    // Override Illuminate mailer construction to remove unsupported view
                    $mailer = new Mailer(
                        $name,
                        $this->createSymfonyTransport($config),
                        $this->app['events']
                    );

                    if ($this->app->bound('queue')) {
                        $mailer->setQueue($this->app['queue']);
                    }

                    return $mailer;
                }

                // Override sendmail transport construction to allow default path
                protected function createSendmailTransport(array $config) : SendmailTransport
                {
                    $path = $config['path'] ?? $this->app['config']->get('mail.sendmail');
                    return $path ? new SendmailTransport($path) : new SendmailTransport();
                }
            };
        });

        $this->app->bind('mailer', function ($app) {
            return $app->make('mail.manager')->mailer();
        });
    }

    /**
     * @copydoc \Illuminate\Mail\MailServiceProvider::provides()
     */
    public function provides() : array
    {
        return
            [
                'mail.manager',
                'mailer',
            ];
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\core\MailServiceProvider', '\MailServiceProvider');
}
