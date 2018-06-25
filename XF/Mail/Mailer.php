<?php
/**
 * @license
 * Copyright 2018 TruongLuu. All Rights Reserved.
 */
 
namespace Truonglv\Mails\XF\Mail;

class Mailer extends XFCP_Mailer
{
    public function send(\Swift_Mime_Message $message, \Swift_Transport $transport = null)
    {
        $class = \XF::extendClass('Truonglv\Mails\Transport\MailGun');
        /** @var \Truonglv\Mails\Transport\MailGun $mailGun */
        $mailGun = new $class($this->newMail());

        return $mailGun->send($message);
    }
}
