<?php

declare(strict_types=1);

namespace TopCharge\SDK\Service;

use TopCharge\SDK\TopChargeClient;

final class PaymentService
{
    public function __construct(private readonly TopChargeClient $client)
    {
    }

    /** @return array<string, mixed> */
    public function create(string $amount, ?string $description = null, ?string $redirectUrl = null, ?string $callbackUrl = null): array
    {
        $payload = ['paymentAmount' => $amount];
        if ($description !== null) {
            $payload['description'] = $description;
        }
        if ($redirectUrl !== null) {
            $payload['redirectUrl'] = $redirectUrl;
        }
        if ($callbackUrl !== null) {
            $payload['callbackUrl'] = $callbackUrl;
        }

        return $this->client->requestJson('POST', '/api/v1/payment/create', $payload);
    }

    /** @return array<string, mixed> */
    public function getStatus(string $paymentId): array
    {
        return $this->client->requestJson('GET', '/api/v1/payment/status/' . rawurlencode($paymentId));
    }

    /** @return array{success: bool, message: string, paymentId: string, status: string} */
    public function cancel(string $paymentId): array
    {
        return $this->client->requestJson('POST', '/api/v1/payment/cancel/' . rawurlencode($paymentId));
    }
}
