<?php

namespace Bow\Mail\Driver;

use Aws\Ses\SesClient;
use Aws\Exception\AwsException;
use Bow\Mail\Message;
use Bow\Mail\Contracts\MailDriverInterface;

class SesDriver implements MailDriverInterface
{
    /**
     * The SES Instance
     *
     * @var SesClient
     */
    private $ses;

    /**
     * SesDriver constructor
     *
     * @param array $config
     * @return void
     */
    public function __construct(array $config)
    {
        $this->ses = new SesClient($config);
    }

    /**
     * Private setting of the magic functions
     */
    private function __clone()
    {
    }

    /**
     * Send message
     *
     * @param Message $message
     * @return mixed
     */
    public function send(Message $message)
    {
        // TODO: Integration of AWS SES
    }
}
