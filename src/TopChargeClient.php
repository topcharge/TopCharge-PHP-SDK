<?php

declare(strict_types=1);

namespace TopCharge\SDK;

use JsonException;
use TopCharge\SDK\Exception\ApiException;
use TopCharge\SDK\Http\CurlHttpClient;
use TopCharge\SDK\Http\HttpClientInterface;
use TopCharge\SDK\Http\HttpResponse;
use TopCharge\SDK\Service\AccountService;
use TopCharge\SDK\Service\PaymentService;
use TopCharge\SDK\Service\WithdrawalService;

final class TopChargeClient
{
    public readonly AccountService $account;
    public readonly PaymentService $payments;
    public readonly WithdrawalService $withdrawals;

    private readonly HttpClientInterface $httpClient;
    private readonly string $baseUrl;

    public function __construct(
        private readonly string $clientId,
        private readonly ?string $secretKey = null,
        string $baseUrl = 'https://api.topcharge.app',
        ?HttpClientInterface $httpClient = null,
    ) {
        if (trim($clientId) === '') {
            throw new \InvalidArgumentException('TopCharge client ID must not be empty.');
        }
        if (trim($baseUrl) === '') {
            throw new \InvalidArgumentException('TopCharge base URL must not be empty.');
        }

        $this->baseUrl = rtrim($baseUrl, '/');
        $this->httpClient = $httpClient ?? new CurlHttpClient();
        $this->account = new AccountService($this);
        $this->payments = new PaymentService($this);
        $this->withdrawals = new WithdrawalService($this);
    }

    /**
     * @param array<string, mixed>|null $payload
     * @param array<string, string|int|bool|null> $query
     */
    public function request(string $method, string $path, ?array $payload = null, array $query = []): HttpResponse
    {
        // The public API canonicalizes endpoint URLs with a trailing slash.
        // Building canonical URLs avoids a 301 that could change a POST into a GET.
        $url = $this->baseUrl . '/' . trim($path, '/') . '/';
        $filteredQuery = array_filter($query, static fn (mixed $value): bool => $value !== null);
        if ($filteredQuery !== []) {
            $url .= '?' . http_build_query($filteredQuery, '', '&', PHP_QUERY_RFC3986);
        }

        $headers = [
            'Accept' => 'application/json',
            'User-Agent' => 'topcharge-php-sdk/1.0',
            'x-api-key' => $this->clientId,
        ];
        if ($this->secretKey !== null && $this->secretKey !== '') {
            $headers['x-api-secret'] = $this->secretKey;
        }

        $body = null;
        if ($payload !== null) {
            try {
                $body = json_encode($payload, JSON_THROW_ON_ERROR);
            } catch (JsonException $exception) {
                throw new \InvalidArgumentException('TopCharge request payload cannot be JSON encoded.', 0, $exception);
            }
            $headers['Content-Type'] = 'application/json';
        }

        $response = $this->httpClient->request($method, $url, $headers, $body);
        if ($response->statusCode < 200 || $response->statusCode >= 300) {
            throw new ApiException($this->errorMessage($response), $response->statusCode, $response->body);
        }

        return $response;
    }

    /**
     * @param array<string, mixed>|null $payload
     * @param array<string, string|int|bool|null> $query
     * @return array<string, mixed>
     */
    public function requestJson(string $method, string $path, ?array $payload = null, array $query = []): array
    {
        $response = $this->request($method, $path, $payload, $query);
        if ($response->body === '') {
            return [];
        }

        try {
            $decoded = json_decode($response->body, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new \UnexpectedValueException('TopCharge returned an invalid JSON response.', 0, $exception);
        }

        if (!is_array($decoded)) {
            throw new \UnexpectedValueException('TopCharge returned a JSON response that is not an object.');
        }

        return $decoded;
    }

    private function errorMessage(HttpResponse $response): string
    {
        try {
            $decoded = json_decode($response->body, true, 512, JSON_THROW_ON_ERROR);
            if (is_array($decoded) && isset($decoded['message']) && is_string($decoded['message'])) {
                return $decoded['message'];
            }
            if (
                is_array($decoded)
                && isset($decoded['err'])
                && is_array($decoded['err'])
                && isset($decoded['err']['message'])
                && is_string($decoded['err']['message'])
            ) {
                return $decoded['err']['message'];
            }
        } catch (JsonException) {
            // Fall back to a stable message for non-JSON error responses.
        }

        return 'TopCharge API request failed with HTTP status ' . $response->statusCode . '.';
    }
}
