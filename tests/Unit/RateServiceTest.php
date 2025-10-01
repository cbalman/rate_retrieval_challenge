<?php

namespace Tests\Unit;

use App\Services\ShipPrimus\AuthService;
use App\Services\ShipPrimus\RateService;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

class RateServiceTest extends TestCase
{
    protected function createMockHttpClient(array $responses): Client
    {
        $mock = new MockHandler($responses);
        $handlerStack = HandlerStack::create($mock);
        // NOTE: The base_uri is not strictly necessary here, but good practice.
        return new Client(['handler' => $handlerStack]);
    }

    // --- Pure Logic Tests (No Network Calls) ---

    public function test_it_transforms_rates_to_the_required_format_and_handles_missing_keys()
    {
        // Sample data from the external API (simulating various rate key formats)
        $apiResults = [
            // Standard format
            ['name' => 'Carrier A', 'serviceLevel' => 'STD', 'rateType' => 'LTL', 'total' => 1200.50, 'transitDays' => 5],
            // Alternative keys (using 'carrier') and missing optional data
            ['carrier' => 'Carrier B', 'serviceLevel' => 'EXP', 'rateType' => 'EXPRESS'],
            // Missing total/days (should be null)
            ['name' => 'Carrier C', 'serviceLevel' => 'STD', 'rateType' => 'FTL', 'total' => 999.99],
        ];

        // Mock AuthService (required by constructor but not used by transformRates)
        $mockAuth = $this->createMock(AuthService::class);
        $rateService = new RateService($mockAuth);

        $transformed = $rateService->transformRates($apiResults);

        $this->assertCount(3, $transformed);

        // 1. Verify all required keys are present and types are correct
        $this->assertEquals('Carrier A', $transformed[0]['CARRIER']);
        $this->assertEquals(1200.50, $transformed[0]['TOTAL']);
        $this->assertSame(5, $transformed[0]['TRANSIT TIME']);

        // 2. Verify handling of alternative/missing keys
        $this->assertEquals('Carrier B', $transformed[1]['CARRIER']);
        $this->assertNull($transformed[1]['TOTAL']);
        $this->assertNull($transformed[1]['TRANSIT TIME']);

        // 3. Verify cast to float and null handling
        $this->assertSame(999.99, $transformed[2]['TOTAL']);
        $this->assertNull($transformed[2]['TRANSIT TIME']);
    }

    public function test_it_returns_the_cheapest_rate_for_each_service_level()
    {
        // Transformed data to test filtering logic
        $transformedData = [
            ['SERVICE LEVEL' => 'Standard', 'TOTAL' => 1200, 'CARRIER' => 'Carrier A'],
            ['SERVICE LEVEL' => 'Standard', 'TOTAL' => 950, 'CARRIER' => 'Carrier B', 'TRANSIT TIME' => 6], // Cheapest STD
            ['SERVICE LEVEL' => 'Express', 'TOTAL' => 1800, 'CARRIER' => 'Carrier C'], // Cheapest EXP
            ['SERVICE LEVEL' => 'Express', 'TOTAL' => 2100, 'CARRIER' => 'Carrier D'],
            ['SERVICE LEVEL' => 'Standard', 'TOTAL' => 950.01, 'CARRIER' => 'Carrier E'],
        ];

        $mockAuth = $this->createMock(AuthService::class);
        $rateService = new RateService($mockAuth);

        $cheapest = $rateService->cheapestPerServiceLevel($transformedData);

        $this->assertCount(2, $cheapest);
        $this->assertArrayNotHasKey('__UNKNOWN__', $cheapest); // Ensures the temporary key is not returned

        // Cheapest Standard (Carrier B, $950)
        $this->assertEquals(950, $cheapest[0]['TOTAL']);
        $this->assertEquals('Carrier B', $cheapest[0]['CARRIER']);

        // Cheapest Express (Carrier C, $1800)
        $this->assertEquals(1800, $cheapest[1]['TOTAL']);
        $this->assertEquals('Carrier C', $cheapest[1]['CARRIER']);
    }

    // --- Flow Tests (With Mocked Network and Mocked Auth) ---

    public function test_it_fetches_rates_successfully_on_first_attempt()
    {
        $expectedResults = [['name' => 'Carrier Test', 'total' => 1000]];
        $successBody = json_encode(['data' => ['results' => $expectedResults]]);

        // 1. Configure Guzzle Mock for success
        $httpClient = $this->createMockHttpClient([
            new Response(200, [], $successBody)
        ]);

        // 2. Configure AuthService Mock to return a valid token
        $mockAuth = $this->createMock(AuthService::class);
        $mockAuth->method('getValidToken')->willReturn('valid-token-123');
        $mockAuth->expects($this->never())->method('refreshToken'); // Refresh MUST NOT be called

        // 3. Create Service and inject the mock HTTP client
        $rateService = new RateService($mockAuth);
        // NOTE: setHttpClient must be implemented in RateService
        $rateService->setHttpClient($httpClient);

        // 4. Execute and assert
        $rates = $rateService->fetchRates(['origin' => 'TEST']);

        $this->assertEquals($expectedResults, $rates);
    }

    public function test_it_refreshes_token_and_retries_successfully_on_401_error()
    {
        $initialToken = 'expired-token-456';
        $newToken = 'fresh-token-789';
        $expectedResults = [['name' => 'Carrier Retry', 'total' => 500]];
        $successBody = json_encode(['results' => $expectedResults]);

        // 1. Configure Guzzle Mock with 2 responses: 401 Failure, 200 Success
        $httpClient = $this->createMockHttpClient([
            // First call: Fails with 401 (throws ClientException)
            new Response(401, ['WWW-Authenticate' => 'Bearer'], '{"error": "Unauthorized"}'),
            // Second call: Success (the retry call)
            new Response(200, [], $successBody),
        ]);

        // 2. Configure AuthService Mock:
        $mockAuth = $this->createMock(AuthService::class);
        $mockAuth->method('getValidToken')->willReturn($initialToken);
        // refreshToken MUST be called ONCE and MUST return the new token
        $mockAuth->expects($this->once())
            ->method('refreshToken')
            ->with($this->equalTo($initialToken))
            ->willReturn($newToken);

        // 3. Create Service and inject the mock HTTP client
        $rateService = new RateService($mockAuth);
        $rateService->setHttpClient($httpClient);

        // 4. Execute and assert
        $rates = $rateService->fetchRates(['origin' => 'TEST']);

        // Verify that the response from the second (successful) call is returned
        $this->assertEquals($expectedResults, $rates);
    }

    public function test_it_throws_exception_if_error_is_not_401()
    {
        $initialToken = 'token-123';

        // 1. Configure Guzzle Mock: Fails with 500 (an unhandled error)
        $httpClient = $this->createMockHttpClient([
            new Response(500, [], '{"error": "Server error"}')
        ]);

        // 2. Configure AuthService Mock
        $mockAuth = $this->createMock(AuthService::class);
        $mockAuth->method('getValidToken')->willReturn($initialToken);
        $mockAuth->expects($this->never())->method('refreshToken'); // MUST NOT attempt to refresh

        // 3. Create Service and inject the mock HTTP client
        $rateService = new RateService($mockAuth);
        $rateService->setHttpClient($httpClient);

        // 4. Assert that the original Guzzle exception (ClientException or ServerException) is thrown
        // Since we are mocking the response with a 500 status code, Guzzle throws a ServerException
        // or a RequestException. We use the parent type to be safe.
        $this->expectException(\GuzzleHttp\Exception\RequestException::class);

        // 5. Execute
        $rateService->fetchRates(['origin' => 'TEST']);
    }
}
