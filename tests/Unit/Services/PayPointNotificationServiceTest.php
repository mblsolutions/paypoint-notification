<?php

namespace MBLSolutions\Notification\Tests\Unit\Services;

use Mockery;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Config;
use Illuminate\Validation\ValidationException;
use MBLSolutions\Notification\Exception\MailException;
use MBLSolutions\Notification\Jobs\CreateNotificationLog;
use MBLSolutions\Notification\Services\PayPointNotificationService;
use MBLSolutions\Notification\Tests\LaravelTestCase;
use PHPUnit\Util\Test;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mime\Header\Headers;
use Symfony\Component\Mime\Header\UnstructuredHeader;
use Symfony\Component\Mime\RawMessage;

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

        // Create a mock of the Email class
        $this->mockEmail = Mockery::mock(Email::class);       

        // Create a mock of the SentMessage class
        $this->mockSentMessage = Mockery::mock(SentMessage::class);
        
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    protected function createMockPaypointNotificationService(string $endpoint,string $subscriptionKey): void
    {
        // Use a closure to set the protected $http and $header property.
        // This is necessary because the constructor cannot be easily mocked.
        $mockGuzzleClient = $this->getMockGuzzleClient($endpoint, $subscriptionKey);
        $headers = $this->getRequestHeaders($subscriptionKey);
        $setter = function () use ($mockGuzzleClient, $headers) {
            /**@var mixed $this */
            $this->http = $mockGuzzleClient;
            $this->headers = $headers;
        };
        $boundSetter = $setter->bindTo($this->payPointNotificationService, $this->payPointNotificationService);
        $boundSetter();  
    }

    #[Test]
    public function test_successful_send_out_paypoint_notification(): void
    {
        Queue::fake();
        $this->createMockPaypointNotificationService(
            env('PP_NOTIFICATION_endpoint'),
            env('PP_NOTIFICATION_SUBSCRIPTION_KEY')
        );
        // Create a mock for the header
        $headers = new Headers();
        $headers->add(new UnstructuredHeader('X-Template-Id', env('TEMPLATE_ID')));
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
        Queue::assertPushed(CreateNotificationLog::class, function ($event) {
            return $event->data['template_id'] === env('TEMPLATE_ID') &&
                   $event->data['method'] === 'POST' &&
                   $event->data['status'] === 200;
        });
    }
    
    #[Test]
    public function test_failing_send_out_paypoint_notification_with_invalid_templateId(): void
    {
        $this->createMockPaypointNotificationService(
            env('PP_NOTIFICATION_endpoint'),
            env('PP_NOTIFICATION_SUBSCRIPTION_KEY')
        );
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

    #[Test]
    public function test_failing_send_out_paypoint_notification_with_invalid_request_body_format(): void
    {
        $this->expectException(MailException::class);
        $this->expectExceptionMessage('No request body received by PayPointNotificationService');

        $this->createMockPaypointNotificationService(
            env('PP_NOTIFICATION_endpoint'),
            env('PP_NOTIFICATION_SUBSCRIPTION_KEY')
        );
        // Create a mock for the header
        $headers = new Headers();
        $headers->add(new UnstructuredHeader('X-Template-Id', env('TEMPLATE_ID')));
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

    #[Test]
    public function test_failing_send_out_paypoint_notification_with_failed_validation_on_request_body(): void
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

        $this->createMockPaypointNotificationService(
            env('PP_NOTIFICATION_endpoint'),
            env('PP_NOTIFICATION_SUBSCRIPTION_KEY')
        );
        // Create a mock for the header
        $headers = new Headers();
        $headers->add(new UnstructuredHeader('X-Template-Id', env('TEMPLATE_ID')));
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
    }

    #[Test]
    public function test_failing_send_out_paypoint_notification_without_templateId_in_email_header(): void
    {
        $this->expectException(MailException::class);
        $this->expectExceptionMessage('Missing X-Template-Id in message header received by PayPointNotificationService');

        $this->createMockPaypointNotificationService(
            env('PP_NOTIFICATION_endpoint'),
            env('PP_NOTIFICATION_SUBSCRIPTION_KEY')
        );
        // Create a mock for the header
        $headers = new Headers();

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

    #[Test]
    public function test_failing_send_out_paypoint_notification_without_request_body_in_email_header(): void
    {
        $this->expectException(MailException::class);
        $this->expectExceptionMessage('Missing X-Request-Body in message header received by PayPointNotificationService');

        $this->createMockPaypointNotificationService(
            env('PP_NOTIFICATION_endpoint'),
            env('PP_NOTIFICATION_SUBSCRIPTION_KEY')
        );
        // Create a mock for the header
        $headers = new Headers();
        $headers->add(new UnstructuredHeader('X-Template-Id', env('TEMPLATE_ID')));

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

    #[Test]
    public function test_failing_send_out_paypoint_notification_with_incorrect_message_type(): void
    {
        $this->expectException(MailException::class);
        $this->expectExceptionMessage('Unsupported message type received by PayPointNotificationService');

        $this->createMockPaypointNotificationService(
            env('PP_NOTIFICATION_endpoint'),
            env('PP_NOTIFICATION_SUBSCRIPTION_KEY')
        );
        // Create a mock for the header
        $headers = new Headers();
        $headers->add(new UnstructuredHeader('X-Template-Id', env('TEMPLATE_ID')));
        
        // Configure the mock to return the raw message when getOriginalMessage() is called
        $this->mockSentMessage->shouldReceive('getOriginalMessage')
                        ->once()
                        ->andReturn(new RawMessage('This is not an Email object'));

        // send method with the mocked SentMessage
        $this->payPointNotificationService->send($this->mockSentMessage);
    }

    #[Test]
    public function test_handling_error_response_on_invalid_endpoint_from_paypoint_service(): void
    {
        $this->expectException(MailException::class);
        $this->expectExceptionMessage('Error sending request to PayPoint Notification API');

        $this->createMockPaypointNotificationService(
            '123',
            env('PP_NOTIFICATION_SUBSCRIPTION_KEY')
        );
        // Create a mock for the header
        $headers = new Headers();
        $headers->add(new UnstructuredHeader('X-Template-Id', env('TEMPLATE_ID')));
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
        $this->assertDatabaseHas(config('notification.database.table'), [
            'template_id' => env('TEMPLATE_ID'),
            'method' => 'POST',
            'status' => 404,
        ]);
    }

    #[Test]
    public function test_handling_error_response_on_invalid_subscriptionkey_from_paypoint_service(): void
    {
        $this->expectException(MailException::class);
        $this->expectExceptionMessage('Error sending request to PayPoint Notification API');

        $this->createMockPaypointNotificationService(
            env('PP_NOTIFICATION_endpoint'),
            ''
        );
        // Create a mock for the header
        $headers = new Headers();
        $headers->add(new UnstructuredHeader('X-Template-Id', env('TEMPLATE_ID')));
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
        $this->assertDatabaseHas(config('notification.database.table'), [
            'template_id' => env('TEMPLATE_ID'),
            'method' => 'POST',
            'status' => 401,
        ]);
    }

    #[Test]
    public function test_handling_error_response_on_missing_field_of_request_body_from_paypoint_service(): void
    {
        $this->expectException(MailException::class);
        $this->expectExceptionMessage('Error sending request to PayPoint Notification API');

        $this->createMockPaypointNotificationService(
            env('PP_NOTIFICATION_endpoint'),
            env('PP_NOTIFICATION_SUBSCRIPTION_KEY')
        );
        // Create a mock for the header
        $headers = new Headers();
        $headers->add(new UnstructuredHeader('X-Template-Id', env('TEMPLATE_ID')));
        $headers->add(new UnstructuredHeader('X-Request-Body', \json_encode(
            [   
                'CallbackUrl' => '',
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
        $this->assertDatabaseHas(config('notification.database.table'), [
            'template_id' => env('TEMPLATE_ID'),
            'method' => 'POST',
            'status' => 400,
        ]);
    }
}