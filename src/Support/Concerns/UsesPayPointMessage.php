<?php

namespace MBLSolutions\Notification\Support\Concerns;

use Illuminate\Support\Facades\Auth;

trait UsesPayPointMessage
{
    protected string $templateId;

    protected array $requestBody;
    
    /**
     * Set user in authentication for stateless guard like job
     *
     * @param  string  $endpoint
     * @param  string  $subscriptionKey
     * @return void
     */
    public function withUser($user)
    {        
        if (config('notification.auth_guard')!=null){
            $auth = Auth::guard(config('notification.auth_guard'));
            $auth->setUser($user);
        }

        return $this;
    }

    public function setTemplateId(string $templateId): void
    {
        $this->templateId = $templateId;
    }
    
    public function getTemplateId(): string
    {
        return $this->templateId;
    }

    public function setRequestBody(array $requestBody): void
    {
        $this->requestBody = $requestBody;
    }

    public function getRequestBody(): array
    {
        return $this->getRequestBody();
    }

    public function fillRequestBody(array $data): void
    {
        $notificationModel = &$this->requestBody['Model']['NotificationModel'];
        $notificationModel = array_merge($notificationModel, $data);
    }

}