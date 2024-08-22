<?php

namespace Truonglv\Mailgun;

use XF;
use Symfony\Component\Mailer\Transport\Dsn;

class Listener
{
    public static function mailerTransportSetup(\XF\Container $container, &$transport = null)
    {
        $factory = new MailgunFactory();
        $options = XF::app()->options();

        $transport = $factory->create(new Dsn(
            'https',
            'api.mailgun.net',
            null,
            null,
            443,
            [
                'api_key' => $options->tmi_apiKey,
                'domain' => $options->tmi_domain,
                'sender_name' => $options->tmi_senderName,
            ]
        ));
    }
}
