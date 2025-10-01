<?php
namespace App\Services\ShipPrimus;

use GuzzleHttp\Client;

class AuthService {
    protected Client $http;
    protected string $base;
    protected ?string $username;
    protected ?string $password;
    protected ?array $tokenData = null;

    public function __construct(array $config = [])
    {
        $this->base = $config['base'] ?? env('SHIPPRIMUS_API_BASE', 'https://sandbox-api.shipprimus.com/api/v1');
        $this->username = $config['username'] ?? env('SHIPPRIMUS_USERNAME');
        $this->password = $config['password'] ?? env('SHIPPRIMUS_PASSWORD');
        $this->http = new Client(['base_uri' => $this->base, 'timeout' => 15.0]);
    }

    /**
     * Allows injecting a different Guzzle client,
     * used exclusively for testing purposes.
     */
    public function setHttpClient(Client $client): void
    {
        $this->http = $client;
    }

    /**
     * Allows setting internal token data,
     * used exclusively for testing purposes.
     */
    public function setTokenData(array $data): void
    {
        $this->tokenData = $data;
    }

    protected function decodeToken(string $jwt): ?array {
        $parts = explode('.', $jwt);
        if (count($parts) < 2) return null;
        $payload = $parts[1];
        $decoded = json_decode(base64_decode(strtr($payload, '-_', '+/')), true);
        return $decoded ?: null;
    }

    public function getValidToken(): string
    {
        if (!empty($this->tokenData) && isset($this->tokenData['token'])) {
            $payload = $this->decodeToken($this->tokenData['token']);
            if ($payload && isset($payload['exp']) && $payload['exp'] > time() + 10) {
                return $this->tokenData['token'];
            }
        }

        try {
            $resp = $this->http->post('login', [
                'json' => [
                    'username' => $this->username,
                    'password' => $this->password
                ]
            ]);

            $body = json_decode((string)$resp->getBody(), true);
            $token = $body['accessToken'] ?? ($body['data']['accessToken'] ?? null);

            $this->tokenData = ['token' => $token];
            return $token;
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            // Here we convert the Guzzle exception into our own exception
            throw new \Exception('Could not obtain token from auth endpoint', 0, $e);
        }
    }

    public function refreshToken(string $oldToken): string
    {
        $resp = $this->http->post('refreshtoken', [
            'json' => ['token' => $oldToken]
        ]);
        $body = json_decode((string)$resp->getBody(), true);
        $token = $body['token'] ?? ($body['data']['token'] ?? null);
        if (!$token) throw new \Exception('Could not refresh token');
        $this->tokenData = ['token' => $token];
        return $token;
    }
}
