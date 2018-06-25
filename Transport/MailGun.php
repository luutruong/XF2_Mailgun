<?php
/**
 * @license
 * Copyright 2018 TruongLuu. All Rights Reserved.
 */
namespace Truonglv\Mails\Transport;

class MailGun extends AbstractProvider
{
    protected $apiRoot = 'https://api.mailgun.net/v3';

    protected $domain;
    protected $apiKey;

    public function __construct()
    {
        parent::__construct();

        $this->domain = \XF::options()->tl_Mails_mailgun_domain;
        $this->apiKey = \XF::options()->tl_Mails_mailgun_apiKey;
    }

    public function send(\Swift_Mime_Message $message)
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
                'body' => $params
            ]);
        } catch (\Exception $e) {
            \XF::logException($e, false, "Email to {$toEmails} failed:");

            return false;
        }

        $body = $response->getBody();

        return $body;
    }
}
