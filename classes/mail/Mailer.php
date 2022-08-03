<?php

/**
* @file classes/mail/Mailer.inc.php
*
 * Copyright (c) 2014-2021 Simon Fraser University
* Copyright (c) 2000-2021 John Willinsky
* Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Mailer
 * @ingroup mail
*
 * @brief Represents interface to manage emails sending and view
 */

namespace PKP\mail;

use Exception;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Mail\Mailer as IlluminateMailer;
use InvalidArgumentException;
use PKP\cache\CacheManager;
use PKP\cache\FileCache;
use PKP\context\Context;
use PKP\core\Core;
use PKP\core\PKPApplication;
use PKP\mail\traits\Configurable;
use PKP\observers\events\MessageSendingContext;
use PKP\observers\events\MessageSendingSite;
use PKP\plugins\HookRegistry;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Email;

class Mailer extends IlluminateMailer
{
    /**
     * Don't bind Laravel View Service, as it's not implemented
     *
     * @var null
     */
    protected $views = null;

    /**
     * Maximum number of notification emails that can be sent per job
     */
    public const BULK_EMAIL_SIZE_LIMIT = 50;

    /**
     * Creates new Mailer instance without binding with View
     *
     * @copydoc \Illuminate\Mail\Mailer::__construct()
     */
    public function __construct(string $name, TransportInterface $transport, Dispatcher $events = null)
    {
        $this->name = $name;
        $this->transport = $transport;
        $this->events = $events;
    }

    /**
     * Renders email content into HTML string
     *
     * @param string $view
     * @param array $data variable => value, 'message' is reserved for the Laravel's Swift Message (Illuminate\Mail\Message)
     *
     * @throws Exception
     *
     * @see \Illuminate\Mail\Mailer::renderView()
     */
    protected function renderView($view, $data): string
    {
        if ($view instanceof Htmlable) {
            // return HTML without data compiling
            return $view->toHtml();
        }

        if (!is_string($view)) {
            throw new InvalidArgumentException('View must be instance of ' . Htmlable::class . ' or a string, ' . get_class($view) . ' is given');
        }

        return $this->compileParams($view, $data);
    }

    /**
     * Compiles email templates by substituting variables with their real values
     *
     * @param string $view text or HTML string
     * @param array $data variables with their values passes ['variable' => value]
     *
     * @return string compiled string with substitute variables
     */
    public function compileParams(string $view, array $data): string
    {
        // Remove pre-set message template variable assigned by Illuminate Mailer
        unset($data[Mailable::DATA_KEY_MESSAGE]);

        $variables = [];
        $replacements = [];
        foreach ($data as $key => $value) {
            $variables[] = '/\{\$' . $key . '\}/';
            $replacements[] = $value;
        }

        return preg_replace($variables, $replacements, $view);
    }

    /**
     * @copydoc IlluminateMailer::send()
     *
     * @param null|mixed $callback
     */
    public function send($view, array $data = [], $callback = null)
    {
        if (is_a($view, Mailable::class)) {
            /** @var Mailable $view */
            $view->setData();
        }
        parent::send($view, $data, $callback);
    }

    /**
     * Overrides Illuminate Mailer method to provide additional parameters to the event
     *
     * @param \Symfony\Component\Mime\Email  $message
     * @param array $data
     *
     * @return bool
     */
    protected function shouldSendMessage($message, $data = [])
    {
        if (!$this->events) {
            return true;
        }

        $request = PKPApplication::get()->getRequest();
        $context = $request->getContext();
        if ($context) {
            return $this->events->until(new MessageSendingContext($context, $message, $data)) !== false;
        }

        $site = $request->getSite();
        return $this->events->until(new MessageSendingSite($site, $message, $data)) !== false;
    }

    /**
     * @return string[] mailable class names
     */
    public static function getMailables(Context $context): array
    {
        $mailables = static::getMailablesFromCache($context->getId());
        HookRegistry::call('Mailer::Mailables', [&$mailables]);

        return $mailables;
    }

    /**
     * @return string[] cached mailable class names
     */
    protected static function getMailablesFromCache(int $contextId): array
    {
        $cacheManager = CacheManager::getManager();
        $cache = $cacheManager->getCache('mailable', $contextId, function (FileCache $cache) {
            $cache->setEntireCache(static::scanMailables());
        });

        return $cache->getContents();
    }

    /**
     * Scans mailable directories to retrieve class names
     */
    protected static function scanMailables()
    {
        $finder = (new Finder())->files()->in(array_filter(static::discoverMailablesWithin(), function ($directory) {
            return is_dir($directory);
        }));

        $mailables = [];
        foreach ($finder as $file) {
            $className = Core::classFromFile($file);
            if (is_a($className, Mailable::class, true) && class_uses_recursive(Configurable::class)) {
                $mailables[] = $className;
            }
        }
        return $mailables;
    }

    /**
     * @return string[] dirs to scan for mailables
     */
    protected static function discoverMailablesWithin(): array
    {
        $mailables = [
            base_path('classes/mail/mailables'),
            base_path('lib/pkp/classes/mail/mailables')
        ];

        return $mailables;
    }

    /**
     * Override method to catch an exception while sending email instance
     *
     * @return \Symfony\Component\Mailer\SentMessage|null
     */
    protected function sendSymfonyMessage(Email $message)
    {
        $sentMessage = null;
        try {
            $sentMessage = $this->transport->send($message, Envelope::create($message));
        } catch (TransportException $e) {
            error_log($e->getMessage());
        }

        return $sentMessage;
    }
}
