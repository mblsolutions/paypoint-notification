<?php

namespace MBLSolutions\Notification\Services;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mime\Email;
use MBLSolutions\Notification\Exception\MailException;
use MBLSolutions\Notification\Http\Request;
use MBLSolutions\Notification\Jobs\CreateNotificationLog;

class PayPointNotificationService
{
    /**
     * The HTTP client instance.
     *
     * @var \GuzzleHttp\Client
     */
    protected $http;

    /**
     * The header for HTTP client request.
     *
     * @var array
     */
    protected $headers;

    /**
     * Create a new HTTP transport instance.
     *
     * @param  string  $endpoint
     * @param  string  $subscriptionKey
     * @return void
     */
    public function __construct(string $endpoint, string $subscriptionKey) 
    {
        // Add a trailing slash if it doesn't already exist.
        if (substr($endpoint, -1) !== '/') {
            $endpoint .= '/';
        }

        $this->http = new Client([
            // The 'base_uri' option is crucial. All requests will be relative to this URI.
            'base_uri' => $endpoint,
            
            // set a connection timeout
            'timeout' => config('notification.timeout'),
        ]);

        // The 'headers' option sets default headers for every request.
        $this->headers = [
            'Ocp-Apim-Subscription-Key' => $subscriptionKey,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];
    }

    public function send(SentMessage $message): void
    {
        $originalMessage = $message->getOriginalMessage();

        // Check if the original message is an instance of the specific Email class
        if ($originalMessage instanceof Email) {
            
            $headers = $originalMessage->getHeaders();

            // Get the template Id from Custom header
            $templateIdHeader = $headers->get('X-Template-Id');
            if (!$templateIdHeader){
                // Handle the case where the template id not in message header
                throw new MailException('Missing X-Template-Id in message header received by PayPointNotificationService');
            }
            $templateId = $templateIdHeader->getBody();
            
            // Get the template request body from custom header
            $requestBodyHeader = $headers->get('X-Request-Body');
            if (!$requestBodyHeader){
                // Handle the case where the request body not in message header
                throw new MailException('Missing X-Request-Body in message header received by PayPointNotificationService');
            }
            $requestBody = $requestBodyHeader->getBody();
            
        } else {
            // Handle the case where the message is not an Email object
            throw new MailException('Unsupported message type received by PayPointNotificationService');
        }
        
        $param = ['templateId' => $templateId];
        $this->validateRequestParameter($param);
        $this->validateRequestBody($templateId,$requestBody);
        
        $request = new Request('POST','request',$this->headers, $requestBody);
        try{
            $response = $this->http->send($request,[
                'query' => $param,
            ]);
        }catch (\GuzzleHttp\Exception\ClientException $e) {
            // Handle the exception and log it
            dispatch(new CreateNotificationLog($templateId,$request,$e->getResponse()));            
            throw new MailException('Error sending request to PayPoint Notification API', 0, $e);
        }
        
        dispatch(new CreateNotificationLog($templateId,$request,$response));
    }

    public function validateRequestParameter(array $param): bool
    {
        $rules = [
            'templateId' => ['required', 'uuid'],
        ];
        $validator = Validator::make($param, $rules);

        if ($validator->fails()) {
           throw new ValidationException($validator, 'Validation failed on template id.');
        }

        return true;
    }

    public function validateRequestBody($templateId,$requestBody): bool
    {
        if ($requestBody) {
            // The header value is a string, so we need to decode the JSON
            $body = json_decode($requestBody, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                // Handle the case where the custom data is not JSON
                throw new MailException('No request body received by PayPointNotificationService');
            }
        }

        $rules = config('notification.template.'.$templateId.'.validation_rule');
        if ($rules){
            $validator = Validator::make($body, $rules);

            if ($validator->fails()) {
            throw new ValidationException($validator, 'Validation failed on request body.');
            }
        }

        return true;
    }
}