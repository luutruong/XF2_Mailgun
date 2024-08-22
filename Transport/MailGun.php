<?php
/**
 * @license
 * Copyright 2018 TruongLuu. All Rights Reserved.
 */

namespace Truonglv\Mailgun\Transport;

use XF;
use Exception;
use Throwable;
use LogicException;
use function in_array;
use function json_encode;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mime\MessageConverter;
use Symfony\Component\Mailer\Exception\RuntimeException;
use Symfony\Component\Mailer\Transport\AbstractTransport;

class MailGun extends AbstractTransport
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
     * @var string
     */
    protected $senderName;

    public function setApiKey(string $apiKey): void
    {
        $this->apiKey = $apiKey;
    }

    public function setDomain(string $domain): void
    {
        $this->domain = $domain;
    }

    public function setSenderName(string $senderName): void
    {
        $this->senderName = $senderName;
    }

    private function assertApiWasSetup(): void
    {
        if (trim($this->domain) === '' || trim($this->apiKey) === '') {
            throw new LogicException('MailGun was not setup correctly.');
        }
    }

    protected function doSend(SentMessage $message): void
    {
        $this->assertApiWasSetup();
        $client = XF::app()->http()->client();

        try {
            $email = MessageConverter::toEmail($message->getOriginalMessage());
        } catch (Exception $e) {
            throw new RuntimeException(sprintf('Unable to send message with the "%s" transport: ', __CLASS__) . $e->getMessage(), 0, $e);
        }

        $senderName = trim($this->senderName);
        if ($senderName === '') {
            $senderName = self::DEFAULT_SENDER_NAME;
        }

        $payload = [
            'from' => $senderName . '@' . $this->domain,
            'subject' => $email->getSubject(),
            'text' => $email->getBody(),
        ];
        if ($email->getHtmlCharset() !== null) {
            $payload['html'] = $email->getHtmlBody();
        }

        foreach ($this->getRecipients($email, $message->getEnvelope()) as $recipient) {
            $payload['to'] = $recipient->getAddress();

            try {
                $client->post(self::API_BASE . '/' . $this->domain . '/messages', [
                    'auth' => [
                        'api',
                        $this->apiKey
                    ],
                    'form_params' => $payload,
                    'http_errors' => true,
                ]);
            } catch (Throwable $e) {
                XF::app()->logException($e, 'failed to send message: ' . json_encode($payload) . ' ');
            }
        }
    }

    protected function getRecipients(Email $email, Envelope $envelope): array
    {
        return array_filter($envelope->getRecipients(), function (Address $address) use ($email) {
            return false === in_array($address, array_merge($email->getCc(), $email->getBcc()), true);
        });
    }

    public function __toString(): string
    {
        return 'mailgun';
    }
}
