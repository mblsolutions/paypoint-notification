<?php

namespace MBLSolutions\Notification\Tests\Unit\Services;

use Mockery;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Config;
use Illuminate\Validation\ValidationException;
use MBLSolutions\Notification\Exception\MailException;
use MBLSolutions\Notification\Jobs\CreateNotificationLog;
use MBLSolutions\Notification\Services\PayPointNotificationService;
use MBLSolutions\Notification\Tests\LaravelTestCase;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mime\Header\Headers;
use Symfony\Component\Mime\Header\UnstructuredHeader;

class PayPointNotificationServiceTest extends LaravelTestCase
{
    protected $mockEmail;

    protected $mockSentMessage;

    protected $payPointNotificationService;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Temporarily override the config value
        Config::set('notification.protected_keys', [
            'password',
            'password_confirmation',
        ]);

        Config::set('notification.max_loggable_length', 10024);

        $this->payPointNotificationService = Mockery::mock(PayPointNotificationService::class)
            ->makePartial()
            ->shouldAllowMockingProtectedMethods();

        // Use a closure to set the protected $http and $header property.
        // This is necessary because the constructor cannot be easily mocked.
        $setter = function () {
            // Create a mock of the Guzzle client
            $mockGuzzleClient = Mockery::mock(Client::class);
            
            $mockGuzzleClient->shouldReceive('send')
                ->zeroOrMoreTimes() // Allow zero or more calls to send because validation might not always trigger a request
                ->andReturn(new Response(200, [], '{"notificationId":"9f59f137-6adb-413d-91a7-972d9b19419b"}'));
            /**@var mixed $this */
            $this->http = $mockGuzzleClient;
            $this->headers = [
                'Ocp-Apim-Subscription-Key' => 'test-subscription-key',
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ];
        };
        $boundSetter = $setter->bindTo($this->payPointNotificationService, $this->payPointNotificationService);
        $boundSetter();    

        // Create a mock of the Email class
        $this->mockEmail = Mockery::mock(Email::class);       

        // Create a mock of the SentMessage class
        $this->mockSentMessage = Mockery::mock(SentMessage::class);
        
    }

    /** @test **/
    public function can_send_paypoint_notification(): void
    {
        Queue::fake();
        
        // Create a mock for the header
        $headers = new Headers();
        $headers->add(new UnstructuredHeader('X-Template-Id', '373c1fa4-2d9c-4a25-afb5-ffb21c537d85'));
        $headers->add(new UnstructuredHeader('X-Request-Body', \json_encode(
            [   
                'CallbackUrl' => '',
                'Model' =>
                    [
                    'NotificationModel' =>
                        [
                            'Recipients' =>
                            [
                                [
                                    'Name' => 'Test Customer',
                                    'Email' => 'test@test.com'
                                ]
                            ],
                            "Sender" =>
                            [
                                'Email' => 'no-reply@test.com',
                                'DisplayName' => 'My Test Company',
                                'Firstname' => 'Test'
                            ],
                        ]
                    ]                 
                ]
        )));

        // Configure the mock Email object to return the mocked Headers instance
        $this->mockEmail->shouldReceive('getHeaders')
                ->once()
                ->andReturn($headers);
        
        // Configure the mock to return the mock Email when getOriginalMessage() is called
        $this->mockSentMessage->shouldReceive('getOriginalMessage')
                        ->once()
                        ->andReturn($this->mockEmail);

        // send method with the mocked SentMessage
        $this->payPointNotificationService->send($this->mockSentMessage);
        $this->assertTrue(true);
        Queue::assertPushed(CreateNotificationLog::class);
    }

    /** @test **/
    public function cannot_send_paypoint_notification_with_invalid_templateId(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('The template id field must be a valid UUID.');

        // Create a mock for the header
        $headers = new Headers();
        $headers->add(new UnstructuredHeader('X-Template-Id', '123'));
        $headers->add(new UnstructuredHeader('X-Request-Body', \json_encode(
            [   
                'CallbackUrl' => '',
                'Model' =>
                    [
                    'NotificationModel' =>
                        [
                            'Recipients' =>
                            [
                                [
                                    'Name' => 'Test Customer',
                                    'Email' => 'test@test.com'
                                ]
                            ],
                            "Sender" =>
                            [
                                'Email' => 'no-reply@test.com',
                                'DisplayName' => 'My Test Company',
                                'Firstname' => 'Test'
                            ],
                        ]
                    ]                 
                ]
        )));

        // Configure the mock Email object to return the mocked Headers instance
        $this->mockEmail->shouldReceive('getHeaders')
                ->once()
                ->andReturn($headers);
        
        // Configure the mock to return the mock Email when getOriginalMessage() is called
        $this->mockSentMessage->shouldReceive('getOriginalMessage')
                        ->once()
                        ->andReturn($this->mockEmail);

        // send method with the mocked SentMessage
        $this->payPointNotificationService->send($this->mockSentMessage);
        $this->assertTrue(true);
    }

    /** @test **/
    public function cannot_send_paypoint_notification_with_invalid_request_body(): void
    {
        $this->expectException(MailException::class);

        // Create a mock for the header
        $headers = new Headers();
        $headers->add(new UnstructuredHeader('X-Template-Id', '373c1fa4-2d9c-4a25-afb5-ffb21c537d85'));
        $headers->add(new UnstructuredHeader('X-Request-Body', '{'));

        // Configure the mock Email object to return the mocked Headers instance
        $this->mockEmail->shouldReceive('getHeaders')
                ->once()
                ->andReturn($headers);
        
        // Configure the mock to return the mock Email when getOriginalMessage() is called
        $this->mockSentMessage->shouldReceive('getOriginalMessage')
                        ->once()
                        ->andReturn($this->mockEmail);

        // send method with the mocked SentMessage
        $this->payPointNotificationService->send($this->mockSentMessage);
        
    }

    /** @test **/
    public function cannot_send_paypoint_notification_with_failed_validation_on_request_body(): void
    {
        Config::set('notification.template', [
            'default_id' => env('TEMPLATE_ID'),
            env('TEMPLATE_ID') => [
                'validation_rule' => [
                    '*.*.Recipients.*.Name' => ['required', 'string', 'max:255'],
                    '*.*.Recipients.*.Email' => ['required', 'email', 'max:255'],
                ],
            ]
         ]);

        $this->expectException(ValidationException::class);

        // Create a mock for the header
        $headers = new Headers();
        $headers->add(new UnstructuredHeader('X-Template-Id', '373c1fa4-2d9c-4a25-afb5-ffb21c537d85'));
        $headers->add(new UnstructuredHeader('X-Request-Body', \json_encode(
            [   
                'CallbackUrl' => '',
                'Model' =>
                    [
                    'NotificationModel' =>
                        [
                            'Recipients' =>
                            [
                                [
                                    'Name' => 'Test Customer',
                                    'Email' => 'test'
                                ]
                            ],
                            "Sender" =>
                            [
                                'Email' => 'no-reply@test.com',
                                'DisplayName' => 'My Test Company',
                                'Firstname' => 'Test'
                            ],
                        ]
                    ]                 
                ]
        )));

        // Configure the mock Email object to return the mocked Headers instance
        $this->mockEmail->shouldReceive('getHeaders')
                ->once()
                ->andReturn($headers);

        // Configure the mock to return the mock Email when getOriginalMessage() is called
        $this->mockSentMessage->shouldReceive('getOriginalMessage')
                        ->once()
                        ->andReturn($this->mockEmail);

        // send method with the mocked SentMessage
        $this->payPointNotificationService->send($this->mockSentMessage);
        $this->assertTrue(true);

    }

}