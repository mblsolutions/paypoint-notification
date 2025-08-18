<?php

use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Config;
use MBLSolutions\Notification\Http\Request;
use MBLSolutions\Notification\Tests\LaravelTestCase;
use MBLSolutions\Notification\Jobs\CreateNotificationLog;

class CreateNotificationLogTest extends LaravelTestCase
{
    
    protected function setUp(): void
    {
        parent::setUp();
        
        // Temporarily override the config value
        Config::set('notification.protected_keys', [
            'password',
            'password_confirmation',
        ]);

        Config::set('notification.max_loggable_length', 10024);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test **/
    public function can_log_paypoint_notification_in_database(): void
    {
        $requestBody = json_encode([
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
                        ]);

        $request = new Request('POST','request',$this->getRequestHeaders(env('PP_NOTIFICATION_SUBSCRIPTION_KEY')), $requestBody);
        $response = new Response(200, [], '{"notificationId":"9f59f137-6adb-413d-91a7-972d9b19419b"}');

        dispatch(new CreateNotificationLog(env('TEMPLATE_ID'),$request,$response));
        
        $this->assertDatabaseHas(config('notification.database.table'), [
            'template_id' => env('TEMPLATE_ID'),
            'method' => 'POST',
            'status' => 200,
        ]);
    }
}