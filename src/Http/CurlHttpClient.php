<?php

declare(strict_types=1);

namespace TopCharge\SDK\Http;

use RuntimeException;

final class CurlHttpClient implements HttpClientInterface
{
    public function __construct(private readonly int $timeoutSeconds = 30)
    {
        if ($timeoutSeconds < 1) {
            throw new \InvalidArgumentException('The timeout must be at least one second.');
        }
    }

    public function request(string $method, string $url, array $headers, ?string $body = null): HttpResponse
    {
        if (!extension_loaded('curl')) {
            throw new RuntimeException('The cURL extension is required by the default TopCharge HTTP client.');
        }

        $handle = curl_init($url);
        if ($handle === false) {
            throw new RuntimeException('Unable to initialize the cURL client.');
        }

        $responseHeaders = [];
        $headerLines = [];
        foreach ($headers as $name => $value) {
            $headerLines[] = $name . ': ' . $value;
        }

        curl_setopt_array($handle, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headerLines,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => min(10, $this->timeoutSeconds),
            CURLOPT_TIMEOUT => $this->timeoutSeconds,
            CURLOPT_HEADERFUNCTION => static function ($curl, string $header) use (&$responseHeaders): int {
                $parts = explode(':', $header, 2);
                if (count($parts) === 2) {
                    $responseHeaders[strtolower(trim($parts[0]))] = trim($parts[1]);
                }

                return strlen($header);
            },
        ]);

        if ($body !== null) {
            curl_setopt($handle, CURLOPT_POSTFIELDS, $body);
        }

        $responseBody = curl_exec($handle);
        if ($responseBody === false) {
            $message = curl_error($handle);
            throw new RuntimeException('TopCharge request failed: ' . $message);
        }

        $statusCode = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);

        return new HttpResponse($statusCode, $responseBody, $responseHeaders);
    }
}
