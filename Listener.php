<?php

namespace Truonglv\Mailgun;

use Truonglv\Mailgun\Transport\MailGun;

class Listener
{
    public static function mailerTransportSetup(\XF\Container $container, &$transport = null)
    {
        $transport = new MailGun(
            \Swift_DependencyContainer::getInstance()->lookup('transport.eventdispatcher')
        );

        $options = \XF::app()->options();

        $transport->setApiKey($options->tmi_apiKey);
        $transport->setDomain($options->tmi_domain);

        $transport->registerPlugin(new \Swift_Plugins_AntiFloodPlugin(99));
    }
}
