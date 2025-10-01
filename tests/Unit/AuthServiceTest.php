<?php

namespace Tests\Unit;

use App\Services\ShipPrimus\AuthService;
use PHPUnit\Framework\TestCase;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;


class AuthServiceTest extends TestCase
{
    #[Test]
    public function test_get_valid_token()
    {
        // 1. Mock the network response (successful login)
        $mock = new MockHandler([
            new Response(200, [], json_encode([
                'data' => ['accessToken' => 'fake_jwt_token_valid']
            ])),
        ]);

        // 2. Create a Guzzle instance using the mock
        $handlerStack = HandlerStack::create($mock);
        $httpClient = new Client(['handler' => $handlerStack]);

        // 3. Create the service with the mocked client and test data
        $authService = new AuthService(['username' => 'test', 'password' => '1234']);
        // Override the HTTP client with the mocked one
        $authService->setHttpClient($httpClient);

        // 4. Execute the method
        $token = $authService->getValidToken();

        // 5. Assert the result
        $this->assertEquals('fake_jwt_token_valid', $token);
    }

    #[Test]
    public function test_refresh_token() {

        $oldToken = 'expired_jwt_token'; // A token that simulates having expired
        $newToken = 'new_valid_jwt_token'; // The token we expect to receive

        // 1. Mock the network response for the 'refreshtoken' endpoint
        $mock = new MockHandler([
            // Simulate a successful server response to the 'refreshtoken' endpoint
            new Response(200, [], json_encode([
                'token' => $newToken // The API returns the new token in the 'token' field
            ])),
        ]);

        // 2. Create a Guzzle instance using the mock
        $handlerStack = HandlerStack::create($mock);
        $httpClient = new Client(['handler' => $handlerStack]);

        // 3. Create the service with the mocked client and test data
        $authService = new AuthService(['username' => 'test', 'password' => '1234']);
        $authService->setHttpClient($httpClient); // Inject the mock

        // 4. Execute the method with the old token
        $refreshedToken = $authService->refreshToken($oldToken);

        // 5. Assert the result
        // Verify that the returned token is the new token
        $this->assertEquals($newToken, $refreshedToken);
    }

    #[Test]
    public function test_it_returns_cached_token_if_still_valid()
    {
        // Create a simulated JWT token that expires in the future (e.g., 1 hour)
        // Payload: {"exp": 1761922800} (future date)
        $futureExp = time() + 3600;
        $validJwt = 'header.' . base64_encode(json_encode(['exp' => $futureExp])) . '.signature';

        // 1. Mock the network to fail (this tests that it does NOT attempt to call 'login')
        $mock = new MockHandler([
            new Response(400, [], json_encode(['error' => 'Should not call login'])),
        ]);
        $handlerStack = HandlerStack::create($mock);
        $httpClient = new Client(['handler' => $handlerStack]);

        $authService = new AuthService(['username' => 'test', 'password' => '1234']);
        $authService->setHttpClient($httpClient);

        // Initialize tokenData internally (You'll need a setTokenData() method or modify the constructor)
        $authService->setTokenData(['token' => $validJwt]);

        // 2. Execute the method
        $token = $authService->getValidToken();

        // 3. Assert
        $this->assertEquals($validJwt, $token);
        // Assert that the MockHandler was not used (the network call was not made)
        // (This assertion depends on the mocking tool, but in PHPUnit/Pest, the test will pass
        // if the simulated 400 error was not thrown, confirming the network was not used.)
    }

    #[Test]
    public function test_it_throws_exception_if_login_fails_to_return_token()
    {
        // 1. Mock the network response (failed login, status 401 or missing field)
        $mock = new MockHandler([
            new Response(401, [], json_encode(['message' => 'Invalid credentials'])),
        ]);
        $handlerStack = HandlerStack::create($mock);
        $httpClient = new Client(['handler' => $handlerStack]);

        $authService = new AuthService(['username' => 'wrong', 'password' => 'pass']);
        $authService->setHttpClient($httpClient);

        // 2. Assert that an exception is expected
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Could not obtain token from auth endpoint');

        // 3. Execute the method
        $authService->getValidToken();
    }

    #[Test]
    public function test_it_returns_null_for_invalid_jwt_format()
    {
        $authService = new AuthService();

        // 1. Token without the header.payload.signature format
        $malformedToken = 'just.two.parts';
        $result = $this->callProtectedMethod($authService, 'decodeToken', [$malformedToken]);
        $this->assertNull($result);

        $malformedToken2 = 'just_one_part'; // Your logic count($parts) < 2 should handle this
        $result = $this->callProtectedMethod($authService, 'decodeToken', [$malformedToken]);
        $this->assertNull($result);
    }

    #[Test]
    public function it_returns_null_when_jwt_payload_is_not_valid_json()
    {
        // 1. Define a string that is NOT valid JSON.
        $nonJsonString = 'This is a normal string, not valid JSON {';

        // 2. Encode the non-JSON string using Base64 URL-safe.
        // This simulates a token with a payload that is valid Base64 but invalid JSON content.
        $malformedPayload = strtr(base64_encode($nonJsonString), '+/', '-_');

        // 3. Construct the full token (header.payload.signature)
        $tokenWithInvalidJsonPayload = "header." . $malformedPayload . ".signature";

        $authService = new AuthService(); // Assuming configuration is handled by default/env

        // 4. Execute the protected decodeToken method using the reflection helper
        // If you made decodeToken 'public', use: $result = $authService->decodeToken(...);
        $result = $this->callProtectedMethod($authService, 'decodeToken', [$tokenWithInvalidJsonPayload]);

        // 5. Assert
        // We assert null because json_decode() fails to parse the string and returns null.
        $this->assertNull($result);
    }

    protected function callProtectedMethod($object, $methodName, array $parameters = [])
    {
        $reflection = new \ReflectionClass(get_class($object));
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true); // allow access to protected method

        return $method->invokeArgs($object, $parameters);
    }
}
