<?php

namespace Tests\Support\Fakes;

use OpenKOS\Core\Contracts\MailDriver;
use OpenKOS\Core\Data\Mail\DriverHealthResult;
use OpenKOS\Core\Data\Mail\MailMessage;
use OpenKOS\Core\Data\Mail\MailSendResult;

class MailManagerConfigProbeDriver implements MailDriver
{
    public static array $configs = [];

    public function __construct(private array $config = [])
    {
        self::$configs[] = $config;
    }

    public function send(MailMessage $message): MailSendResult
    {
        return new MailSendResult;
    }

    public function health(): DriverHealthResult
    {
        return new DriverHealthResult(true);
    }
}
