<?php

namespace MBLSolutions\Notification\Tests;

use Mockery;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Config;
use GuzzleHttp\Exception\ClientException;
use MBLSolutions\Notification\Http\Request;
use Orchestra\Testbench\TestCase as OTBTestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class LaravelTestCase extends OTBTestCase
{
    use RefreshDatabase;

    protected function getMockGuzzleClient(string $endpoint, string $subscriptionKey): Client
    {
        // Create a mock of the Guzzle client
        $mockGuzzleClient = Mockery::mock(Client::class);
            
        $mockGuzzleClient->shouldReceive('send')
            ->zeroOrMoreTimes() // Allow zero or more calls to send because validation might not always trigger a request
            ->andReturnUsing(function (Request $request) use ($endpoint,$subscriptionKey) {

                // Check the mock on endpoint and throw an exception if it doesn't match
                if (env('PP_NOTIFICATION_endpoint') !== $endpoint) {
                    throw new ClientException(
                        'Resource Not Found',
                        $request,
                        new Response(404, [], '{"statusCode": 404,"message": "Resource not found"}')
                    );
                }

                // Check the mock on subscription key and throw an exception if it doesn't match
                if ($subscriptionKey !== env('PP_NOTIFICATION_SUBSCRIPTION_KEY')) {
                    throw new ClientException(
                        'Access Denied',
                        $request,
                        new Response(401, [], '{"statusCode": 401,"message": "Access denied due to invalid subscription key. Make sure to provide a valid key for an active subscription."}')
                    );
                }

                $body = $request->getContents();
                $data = json_decode($body, true);

                // Check the mock on request body and throw an exception if it doesn't match
                if (!isset($data['Model'])) {
                    throw new ClientException(
                        'Bad Request',
                        $request,
                        new Response(400, [], '{"type": "https://tools.ietf.org/html/rfc9110#section-15.5.1",
                            "title": "Bad Request",
                            "status": 400,
                            "traceId": "00-b34728f28bc4562d51df21248fe488c7-c74aef5b1610ff32-01"
                        }')
                    );
                    
                }     
                
                // Default response for any other case
                return new Response(200, [], '{"notificationId":"9f59f137-6adb-413d-91a7-972d9b19419b"}');
            });
        return $mockGuzzleClient;
    }

    protected function getRequestHeaders(string $subscriptionKey): array
    {
        return [
            'Ocp-Apim-Subscription-Key' => $subscriptionKey,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];
    }

    /**
     * Define environment setup.
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return void
     */
    protected function getEnvironmentSetUp($app)
    {
        // Set up the database name and load the migrations in database for test cases     
        Config::set('notification.database.table', 'notification_logs');
        $this->app = $app;
        $this->loadMigrationsFrom(__DIR__ . '/../src/Console/stubs');
    }

}