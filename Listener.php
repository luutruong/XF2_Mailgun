<?php

namespace Truonglv\Mailgun;

use Truonglv\Mailgun\Transport\MailGun;

class Listener
{
    /**
     * @param \XF\Container $container
     * @param null|mixed $transport
     * @throws \Swift_DependencyException
     * @return void
     */
    public static function mailerTransportSetup(\XF\Container $container, &$transport = null)
    {
        $transport = new MailGun(
            \Swift_DependencyContainer::getInstance()->lookup('transport.eventdispatcher')
        );

        $options = \XF::app()->options();

        $transport->setApiKey($options->tmi_apiKey);
        $transport->setDomain($options->tmi_domain);
        $transport->setSenderName($options->tmi_senderName);

        $transport->registerPlugin(new \Swift_Plugins_AntiFloodPlugin(99));
    }
}
