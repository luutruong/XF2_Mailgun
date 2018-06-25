<?php
/**
 * @license
 * Copyright 2018 TruongLuu. All Rights Reserved.
 */
namespace Truonglv\Mails\Transport;

abstract class AbstractProvider
{
    protected $httpClient;

    public function __construct()
    {
        $this->httpClient = \XF::app()->http()->client();
    }

    abstract public function send(\Swift_Mime_Message $message);
}
