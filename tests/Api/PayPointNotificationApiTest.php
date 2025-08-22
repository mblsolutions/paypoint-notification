<?php

use GuzzleHttp\Exception\ClientException;
use MBLSolutions\Notification\Http\Request;
use MBLSolutions\Notification\Tests\LaravelTestCase;
use PHPUnit\Util\Test;

class PayPointNotificationApiTest extends LaravelTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[Test]
    public function test_throw_out_error_response_on_invalid_endpoint_from_paypoint_service(): void
    {
        $this->expectException(ClientException::class);
        $this->expectExceptionCode(404); //Invalid endpoint

        $endpoint = '123';
        $subscriptionKey = env('PP_NOTIFICATION_SUBSCRIPTION_KEY');
        $requestBody = json_encode([ 
                            'CallbackUrl' => '',
                        ]                
                        );

        $request = new Request('POST','request',$this->getRequestHeaders($subscriptionKey), $requestBody);
        $response = $this->getMockGuzzleClient($endpoint,$subscriptionKey)->send($request,[
            'query' => ['template_id' => env('TEMPLATE_ID')],
        ]);
        
    }

    #[Test]
    public function test_throw_out_error_response_on_invalid_subscriptionkey_from_paypoint_service_api(): void
    {
        $this->expectException(ClientException::class);
        $this->expectExceptionCode(401);

        $endpoint = env('PP_NOTIFICATION_endpoint');
        $subscriptionKey = ''; // Invalid subscription key
        $requestBody = json_encode(
                        [   
                            'CallbackUrl' => '',
                        ]                
                        );

        $request = new Request('POST','request',$this->getRequestHeaders($subscriptionKey), $requestBody);
        $response = $this->getMockGuzzleClient($endpoint,$subscriptionKey)->send($request,[
            'query' => ['template_id' => env('TEMPLATE_ID')],
        ]);
    }

    #[Test]
    public function test_throw_out_error_response_on_missing_field_of_request_body_from_paypoint_service(): void
    {
        $this->expectException(ClientException::class);
        $this->expectExceptionCode(400);

        $endpoint = env('PP_NOTIFICATION_endpoint');
        $subscriptionKey = env('PP_NOTIFICATION_SUBSCRIPTION_KEY');
        $requestBody = json_encode(
                            [   
                                'CallbackUrl' => '',
                            ]                
                            );

        $request = new Request('POST','request',$this->getRequestHeaders($subscriptionKey), $requestBody);
        $response = $this->getMockGuzzleClient($endpoint,$subscriptionKey)->send($request,[
            'query' => ['template_id' => env('TEMPLATE_ID')],
        ]);
    }
}