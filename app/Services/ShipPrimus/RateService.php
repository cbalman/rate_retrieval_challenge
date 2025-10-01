<?php
namespace App\Services\ShipPrimus;

use GuzzleHttp\Client;

class RateService {
    protected Client $http;
    protected AuthService $auth;
    protected string $base;
    protected string $vendorId;

    public function __construct(AuthService $auth, array $config = [])
    {
        $this->auth = $auth;
        $this->base = $config['base'] ?? env('SHIPPRIMUS_API_BASE', 'https://sandbox-api.shipprimus.com/api/v1');
        $this->vendorId = $config['vendorId'] ?? env('SHIPPRIMUS_VENDOR_ID', '1901539643');
        $this->http = new Client(['base_uri' => $this->base, 'timeout' => 20.0]);
    }

    /**
     * Allows injecting a different Guzzle client,
     * used exclusively for testing purposes (Mocking).
     */
    public function setHttpClient(Client $client): void
    {
        $this->http = $client;
    }

    public function fetchRates(array $params = []): array
    {
        $token = $this->auth->getValidToken();
        $url = "database/vendor/contract/{$this->vendorId}/rate";

        try {
            $resp = $this->http->get($url, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                    'Accept' => 'application/json'
                ],
                'query' => $params
            ]);
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            $response = $e->getResponse();
            $code = $response ? $response->getStatusCode() : 0;
            if ($code === 401) {
                $token = $this->auth->refreshToken($token);
                $resp = $this->http->get($url, [
                    'headers' => ['Authorization' => 'Bearer ' . $token, 'Accept' => 'application/json'],
                    'query' => $params
                ]);
            } else {
                throw $e;
            }
        }

        $body = json_decode((string)$resp->getBody(), true);
        $results = $body['data']['results'] ?? $body['results'] ?? [];
        return $results;
    }

    public function transformRates(array $results): array
    {
        $out = [];
        foreach ($results as $r) {
            $out[] = [
                'CARRIER' => $r['name'] ?? ($r['carrier'] ?? 'UNKNOWN'),
                'SERVICE LEVEL' => $r['serviceLevel'] ?? '',
                'RATE TYPE' => $r['rateType'] ?? '',
                'TOTAL' => isset($r['total']) ? (float)$r['total'] : null,
                'TRANSIT TIME' => isset($r['transitDays']) ? (int)$r['transitDays'] : null,
            ];
        }
        return $out;
    }

    public function cheapestPerServiceLevel(array $transformed): array
    {
        $groups = [];
        foreach ($transformed as $row) {
            $svc = $row['SERVICE LEVEL'] ?? '__UNKNOWN__';
            if (!isset($groups[$svc]) || $row['TOTAL'] < $groups[$svc]['TOTAL']) {
                $groups[$svc] = $row;
            }
        }
        return array_values($groups);
    }
}
