<?php

namespace MBLSolutions\Notification\Mailer;

use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use MBLSolutions\Notification\Services\PayPointNotificationService;

class PayPointNotificationTransport extends AbstractTransport
{
    public function __construct(
      protected PayPointNotificationService $service
    ) {
        parent::__construct();
    }

    protected function doSend(SentMessage $message): void
    {
        $this->service->send($message);
    }

    public function __toString(): string
    {
        return 'paypoint';
    }

}