<?php

namespace MBLSolutions\Notification\Message;

use Symfony\Component\Mime\Email;
use Illuminate\Notifications\Messages\MailMessage;
use MBLSolutions\Notification\Support\Concerns\UsesPayPointMessage;

class PayPointMailMessage extends MailMessage
{
    use UsesPayPointMessage;

    public function __construct(string $templateId, array $requestBody) 
    {        
        $this->templateId = $templateId;
        $this->requestBody = $requestBody;
        $this->withSymfonyMessage(function (Email $email) use ($templateId,$requestBody){
            $email->getHeaders()->addTextHeader('X-Template-Id', $templateId);
            $email->getHeaders()->addTextHeader('X-Request-Body', \json_encode($requestBody));
        });
    }

}