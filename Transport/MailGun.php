<?php
/**
 * @license
 * Copyright 2018 TruongLuu. All Rights Reserved.
 */

namespace Truonglv\Mailgun\Transport;

use Swift_Events_EventListener;

class MailGun implements \Swift_Transport
{
    protected $apiRoot = 'https://api.mailgun.net/v3';

    protected $domain;
    protected $apiKey;
    protected $httpClient;

    public function __construct()
    {
        $this->httpClient = \XF::app()->http()->client();
        $this->domain = \XF::options()->tl_Mails_mailgun_domain;
        $this->apiKey = \XF::options()->tl_Mails_mailgun_apiKey;
    }

    public function isStarted()
    {
        return false;
    }

    public function stop()
    {
        // TODO: Implement stop() method.
    }

    public function registerPlugin(Swift_Events_EventListener $plugin)
    {
        // TODO: Implement registerPlugin() method.
    }

    public function start()
    {
        // TODO: Implement start() method.
    }

    public function send(\Swift_Mime_Message $message, &$failedRecipients = [])
    {
        if (!$this->domain || !$this->apiKey) {
            throw new \LogicException('MailGun not setup correctly.');
        }

        $to = $message->getTo();
        $toEmails = $to ? implode(', ', array_keys($to)) : '[unknown]';

        $client = $this->httpClient;

        $params = [
            'from' => 'xenforo@' . $this->domain,
            'to' => $toEmails,
            'subject' => $message->getSubject()
        ];

        /** @var \Swift_MimePart $children */
        foreach ($message->getChildren() as $children) {
            if ($children->getContentType() === 'text/html') {
                $params['html'] = $children->getBody();

                /** @var \Swift_Mime_Headers_ParameterizedHeader $header */
                foreach ($children->getHeaders()->getAll() as $header) {
                    $params['h:' . $header->getFieldName()] = $header->getFieldBody();
                }

                break;
            } elseif ($children->getContentType() === 'text/plain') {
                $params['text'] = $children->getBody();

                /** @var \Swift_Mime_Headers_ParameterizedHeader $header */
                foreach ($children->getHeaders()->getAll() as $header) {
                    $params['h:' . $header->getFieldName()] = $header->getFieldBody();
                }

                break;
            } else {
                throw new \LogicException('Unknown email content type (' . $children->getContentType() . ')');
            }
        }

        try {
            $response = $client->post($this->apiRoot . '/' . $this->domain . '/messages', [
                'auth' => [
                    'api',
                    $this->apiKey
                ],
                'form_params' => $params
            ]);
        } catch (\Exception $e) {
            \XF::logException($e, false, "Email to {$toEmails} failed:");

            return false;
        }

        $body = $response->getBody();

        return $body;
    }
}
