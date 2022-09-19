<?php

/**
* @file classes/mail/Mailer.php
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

use APP\core\Application;
use Exception;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Mail\Mailer as IlluminateMailer;
use Illuminate\Mail\Message;
use InvalidArgumentException;
use PKP\cache\CacheManager;
use PKP\cache\FileCache;
use PKP\config\Config;
use PKP\context\Context;
use PKP\core\Core;
use PKP\mail\traits\Configurable;
use PKP\observers\events\MessageSendingContext;
use PKP\observers\events\MessageSendingSite;
use PKP\plugins\Hook;
use PKP\site\Site;
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
        $variables = [];
        $replacements = [];
        foreach ($data as $key => $value) {
            // Don't compile pre-set message data variables not belonging to the template
            if (in_array($key, Mailable::getReservedDataKeys())) {
                continue;
            }
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

        if (array_key_exists(Mailable::DATA_KEY_CONTEXT, $data)) {
            $context = $data[Mailable::DATA_KEY_CONTEXT];
            return $this->events->until(new MessageSendingContext($context, $message, $data)) !== false;
        }

        $site = Application::get()->getRequest()->getSite();
        return $this->events->until(new MessageSendingSite($site, $message, $data)) !== false;
    }

    /**
     * @return string[] mailable class names
     */
    public static function getMailables(Context $context): array
    {
        $mailables = static::getMailablesFromCache($context->getId());
        Hook::call('Mailer::Mailables', [&$mailables]);

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
            if (is_a($className, Mailable::class, true)
                &&
                in_array(Configurable::class, class_uses_recursive($className))) {
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

    /**
     * Overrides Illuminate Mailer method to modify email header
     *
     * @copydoc Illuminate\Mail\Mailer::addContent()
     */
    protected function addContent($message, $view, $plain, $raw, $data): void
    {
        parent::addContent($message, $view, $plain, $raw, $data);

        $this->setEnvelopeSenderDefault($message, $data);
        $this->setDmarcCompliantFrom($message);
    }

    /**
     * Sets envelope sender, either the default one or from the context settings
     */
    protected function setEnvelopeSenderDefault(Message $message, array $data): void
    {
        // Force default site-wide envelope sender if set
        $configDefaultEnvelopeSender = Config::getVar('email', 'default_envelope_sender');
        if (Config::getVar('email', 'force_default_envelope_sender') && $configDefaultEnvelopeSender) {
            $message->sender($configDefaultEnvelopeSender);
            return;
        }

        // Don't provide further checks if envelope sender isn't allowed in the config
        if (!Config::getVar('email', 'allow_envelope_sender')) {
            return;
        }

        // Set the sender provided in the context settings
        $context = $data[Mailable::DATA_KEY_CONTEXT] ?? null;
        if ($context && $sender = $context->getData('envelopeSender')) {
            $message->sender($sender);
            return;
        }

        // Finally, provide default sender from the config if not specified
        if (!$message->getSender() && $configDefaultEnvelopeSender) {
            $message->sender($configDefaultEnvelopeSender);
        }
    }

    /**
     * Set DMARC compliant From header field body
     */
    protected function setDmarcCompliantFrom(Message $message): void
    {
        if (empty($message->getFrom())) {
            return;
        }

        if (!(Config::getVar('email', 'force_default_envelope_sender')
            && Config::getVar('email', 'default_envelope_sender')
            && Config::getVar('email', 'force_dmarc_compliant_from')
        )) {
            return;
        }

        $this->promoteFromToReplyTo($message);
    }

    /**
     * If a DMARC compliant RFC5322.From was requested we need to promote the original RFC5322. From into a Reply-to header
     * and then munge the RFC5322.From
     */
    protected function promoteFromToReplyTo(Message $message): void
    {
        $replyToEmails = array_map(fn($x) => $x->getAddress(), $message->getReplyTo());
        $fromEmails = array_map(fn($x) => $x->getAddress(), $message->getFrom());
        $alreadyExists = array_intersect($replyToEmails, $fromEmails);

        foreach ($message->getFrom() as $address) {
            if (!in_array($address->getAddress(), $alreadyExists)) {
                $message->addReplyTo($address);
            }
        }

        $site = Application::get()->getRequest()->getSite(); /* @var $site Site **/
        $dmarcFromName = '';
        if (Config::getVar('email', 'dmarc_compliant_from_displayname')) {
            $patterns = ['#%n#', '#%s#'];
            $replacements = [
                implode(',', array_map(fn($x) => $x->getName(), $message->getFrom())),
                $site->getLocalizedData('title'),
            ];
            $dmarcFromName = preg_replace($patterns, $replacements, Config::getVar('email', 'dmarc_compliant_from_displayname'));
        }

        $message->from(Config::getVar('email', 'default_envelope_sender'), $dmarcFromName);
    }
}
