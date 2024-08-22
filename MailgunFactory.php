<?php

namespace Truonglv\Mailgun;

use Truonglv\Mailgun\Transport\MailGun;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mailer\Transport\AbstractTransportFactory;

class MailgunFactory extends AbstractTransportFactory
{
    /**
     * @var MailGun
     */
    protected $transport;

    protected function getSupportedSchemes(): array
    {
        return ['https'];
    }

    public function create(Dsn $dsn): TransportInterface
    {
        $this->transport = new MailGun($this->dispatcher, $this->logger);
        $this->transport->setDomain($dsn->getOption('domain'));
        $this->transport->setApiKey($dsn->getOption('api_key'));
        $this->transport->setSenderName($dsn->getOption('sender_name'));

        return $this->transport;
    }
}
