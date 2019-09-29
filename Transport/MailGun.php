<?php
/**
 * @license
 * Copyright 2018 TruongLuu. All Rights Reserved.
 */

namespace Truonglv\Mailgun\Transport;

use Swift_Events_EventListener;

class MailGun implements \Swift_Transport
{
    const API_BASE = 'https://api.mailgun.net/v3';
    const DEFAULT_SENDER_NAME = 'xenforo';

    /**
     * @var string
     */
    protected $domain;

    /**
     * @var string
     */
    protected $apiKey;

    /**
     * @var \GuzzleHttp\Client
     */
    protected $httpClient;

    /**
     * @var \Swift_Events_EventDispatcher
     */
    protected $eventDispatcher;

    /**
     * @var string
     */
    protected $senderName;

    public function __construct(\Swift_Events_EventDispatcher $eventDispatcher)
    {
        $this->httpClient = \XF::app()->http()->client();
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * @return bool
     */
    public function isStarted()
    {
        return false;
    }

    /**
     * @param string $apiKey
     * @return void
     */
    public function setApiKey($apiKey)
    {
        $this->apiKey = $apiKey;
    }

    /**
     * @param string $domain
     * @return void
     */
    public function setDomain($domain)
    {
        $this->domain = $domain;
    }

    /**
     * @param string $senderName
     * @return void
     */
    public function setSenderName(string $senderName)
    {
        $this->senderName = $senderName;
    }

    /**
     * @return void
     */
    public function stop()
    {
        // TODO: Implement stop() method.
    }

    /**
     * @param Swift_Events_EventListener $plugin
     * @return void
     */
    public function registerPlugin(Swift_Events_EventListener $plugin)
    {
        $this->eventDispatcher->bindEventListener($plugin);
    }

    /**
     * @return void
     */
    public function start()
    {
        // TODO: Implement start() method.
    }

    /**
     * @param \Swift_Mime_Message $message
     * @param mixed $failedRecipients
     * @return int
     */
    public function send(\Swift_Mime_Message $message, &$failedRecipients = [])
    {
        $this->assertApiWasSetup();

        $failedRecipients = (array) $failedRecipients;

        /** @var mixed $evt */
        $evt = $this->eventDispatcher->createSendEvent($this, $message);
        if ($evt instanceof \Swift_Events_SendEvent) {
            $this->eventDispatcher->dispatchEvent($evt, 'beforeSendPerformed');
            if ($evt->bubbleCancelled()) {
                return 0;
            }
        }

        $getRecipients = function ($method) use ($message) {
            /** @var callable $callable */
            $callable = [$message, $method];
            $mixed = call_user_func($callable);

            return (array) $mixed;
        };

        $to = $getRecipients('getTo');
        $cc = $getRecipients('getCc');
        $bcc = $getRecipients('getBcc');

        $count = (count($to) + count($cc) + count($bcc));

        $senderName = trim($this->senderName);
        if ($senderName === '') {
            $senderName = self::DEFAULT_SENDER_NAME;
        }

        $payload = [
            'from' => $senderName . '@' . $this->domain,
            'subject' => $message->getSubject(),
            'text' => $message->getBody()
        ];

        foreach ($message->getChildren() as $children) {
            if ($children->getContentType() === 'text/html') {
                $payload['html'] = $children->getBody();
            } elseif ($children->getContentType() === 'text/plain') {
                $payload['text'] = $children->getBody();
            }
        }

        $this->doSendMessage($evt, $payload, 'to', array_keys($to), $failedRecipients);
        $this->doSendMessage($evt, $payload, 'cc', array_keys($cc), $failedRecipients);
        $this->doSendMessage($evt, $payload, 'bcc', array_keys($bcc), $failedRecipients);

        return $count;
    }

    /**
     * @param \Swift_Events_SendEvent $event
     * @param array $payload
     * @param string $recipientKey
     * @param array $recipients
     * @param array $failedRecipients
     * @return bool
     */
    private function doSendMessage(
        \Swift_Events_SendEvent $event,
        array $payload,
        $recipientKey,
        array $recipients,
        array &$failedRecipients = []
    ) {
        foreach ($recipients as $recipient) {
            $response = null;

            $payload[$recipientKey] = $recipient;

            try {
                $response = $this->httpClient->post(self::API_BASE . '/' . $this->domain . '/messages', [
                    'auth' => [
                        'api',
                        $this->apiKey
                    ],
                    'form_params' => $payload
                ]);
            } catch (\Exception $e) {
                $_GET['__doSendMessagePayload'] = $payload;
                $this->logError($e);
                unset($_GET['__doSendMessagePayload']);
            }

            if (!$response || $response->getStatusCode() !== 200) {
                $failedRecipients[] = $recipient;

                return false;
            }

            $json = json_decode($response->getBody()->getContents(), true);
            if (!isset($json['id'])) {
                $failedRecipients[] = $recipient;

                $_GET['__doSendMessageResponse'] = $json;
                $this->logError('Bad json response!');
                unset($_GET['__doSendMessageResponse']);

                return false;
            }

            $this->eventDispatcher->dispatchEvent($event, 'sendPerformed');
        }

        return true;
    }

    /**
     * @param string|\Exception $error
     * @return void
     */
    private function logError($error)
    {
        if ($error instanceof \Exception) {
            \XF::logException($error, false, '[tl] Mailgun Integration: ');
        } else {
            \XF::logError("[tl] Mailgun Integration: {$error}");
        }
    }

    /**
     * @return void
     */
    private function assertApiWasSetup()
    {
        if (trim($this->domain) === '' || trim($this->apiKey) === '') {
            throw new \LogicException('MailGun was not setup correctly.');
        }
    }
}
