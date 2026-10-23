<?php

declare(strict_types=1);

namespace TopCharge\SDK\Service;

use TopCharge\SDK\TopChargeClient;

final class AccountService
{
    public function __construct(private readonly TopChargeClient $client)
    {
    }

    public function verifyCredentials(): void
    {
        $this->client->request('GET', '/api/v1/account/verify-credentials');
    }

    /**
     * @return array{totalBalance: string, reservedBalance: string, liabilityBalance: string, availableBalance: string, cashAvailableBalance: string}
     */
    public function getBalance(): array
    {
        return $this->client->requestJson('GET', '/api/v1/account/balance');
    }
}
