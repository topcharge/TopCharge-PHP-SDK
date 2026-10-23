<?php

declare(strict_types=1);

namespace TopCharge\SDK\Service;

use TopCharge\SDK\TopChargeClient;

final class WithdrawalService
{
    public function __construct(private readonly TopChargeClient $client)
    {
    }

    /** @return array<string, mixed> */
    public function create(string $phoneNumberCountryCode, string $phoneNumber, string $amount): array
    {
        return $this->client->requestJson('POST', '/api/v1/withdrawal/create', [
            'phoneNumberCountryCode' => $phoneNumberCountryCode,
            'phoneNumber' => $phoneNumber,
            'amount' => $amount,
        ]);
    }
}
