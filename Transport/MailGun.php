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

    protected $domain;
    protected $apiKey;
    protected $httpClient;
    protected $eventDispatcher;

    public function __construct(\Swift_Events_EventDispatcher $eventDispatcher)
    {
        $this->httpClient = \XF::app()->http()->client();
        $this->eventDispatcher = $eventDispatcher;
    }

    public function isStarted()
    {
        return false;
    }

    /**
     * @param string $apiKey
     */
    public function setApiKey($apiKey)
    {
        $this->apiKey = $apiKey;
    }

    /**
     * @param string $domain
     */
    public function setDomain($domain)
    {
        $this->domain = $domain;
    }

    public function stop()
    {
        // TODO: Implement stop() method.
    }

    public function registerPlugin(Swift_Events_EventListener $plugin)
    {
        $this->eventDispatcher->bindEventListener($plugin);
    }

    public function start()
    {
        // TODO: Implement start() method.
    }

    public function send(\Swift_Mime_Message $message, &$failedRecipients = [])
    {
        $this->assertApiWasSetup();

        $failedRecipients = (array) $failedRecipients;

        if ($evt = $this->eventDispatcher->createSendEvent($this, $message)) {
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

        $payload = [
            'from' => 'xenforo@' . $this->domain,
            'subject' => $message->getSubject()
        ];

        if ($message->getContentType() === 'text/html') {
            $payload['html'] = $message->toString();
        } else {
            $payload['text'] = $message->toString();
        }

        /** @var \Swift_MimePart $children */
        foreach ($message->getChildren() as $children) {
            /** @var \Swift_Mime_Headers_ParameterizedHeader $header */
            foreach ($children->getHeaders()->getAll() as $header) {
                $payload['h:' . $header->getFieldName()] = $header->getFieldBody();
            }
        }

        $this->doSendMessage($evt, $payload, array_keys($to), $failedRecipients);
        $this->doSendMessage($evt, $payload, array_keys($cc), $failedRecipients);
        $this->doSendMessage($evt, $payload, array_keys($bcc), $failedRecipients);

        return $count;
    }

    private function doSendMessage(\Swift_Events_SendEvent $event, array $payload, array $recipients, array &$failedRecipients = [])
    {
        foreach ($recipients as $recipient) {
            $response = null;

            $payload['to'] = $recipient;

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

    private function logError($error)
    {
        if ($error instanceof \Exception) {
            \XF::logException($error, false, '[tl] Mailgun Integration: ');
        } else {
            \XF::logError("[tl] Mailgun Integration: {$error}");
        }
    }

    private function assertApiWasSetup()
    {
        if (!$this->domain || !$this->apiKey) {
            throw new \LogicException('MailGun was not setup correctly.');
        }
    }
}
