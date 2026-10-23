<?php

declare(strict_types=1);

namespace TopCharge\SDK\Service;

use TopCharge\SDK\TopChargeClient;

final class GiftCardService
{
    public function __construct(private readonly TopChargeClient $client)
    {
    }

    /** @return array{batch: array<string, mixed>|null, cards: list<array<string, mixed>>, replayed: bool} */
    public function issue(string $amount, string $idempotencyKey, ?int $quantity = null, ?string $name = null): array
    {
        $payload = ['amount' => $amount, 'idempotencyKey' => $idempotencyKey];
        if ($quantity !== null) {
            $payload['quantity'] = $quantity;
        }
        if ($name !== null) {
            $payload['name'] = $name;
        }

        return $this->client->requestJson('POST', '/api/v1/giftcard/issue', $payload);
    }

    /** @return array{items: list<array<string, mixed>>, cursor: string|null} */
    public function listBatches(?string $cursor = null, ?int $limit = null, ?string $sort = null): array
    {
        return $this->client->requestJson('GET', '/api/v1/giftcard/batch', null, $this->paginationQuery($cursor, $limit, $sort));
    }

    /** @return array<string, mixed> */
    public function getBatch(string $batchId): array
    {
        return $this->client->requestJson('GET', '/api/v1/giftcard/batch/' . rawurlencode($batchId));
    }

    /** @return array{items: list<array<string, mixed>>, cursor: string|null} */
    public function listBatchCards(string $batchId, ?string $cursor = null, ?int $limit = null, ?string $sort = null, bool $reveal = false): array
    {
        return $this->client->requestJson(
            'GET',
            '/api/v1/giftcard/batch/' . rawurlencode($batchId) . '/cards',
            null,
            array_merge($this->paginationQuery($cursor, $limit, $sort), ['reveal' => $reveal]),
        );
    }

    /** @return array{revokedCount: int, skippedCount: int, refundedAmount: string} */
    public function revokeBatch(string $batchId, ?string $reason = null): array
    {
        return $this->client->requestJson('POST', '/api/v1/giftcard/batch/' . rawurlencode($batchId) . '/revoke', $this->reasonPayload($reason));
    }

    /** @return array{items: list<array<string, mixed>>, cursor: string|null} */
    public function listCards(?string $cursor = null, ?int $limit = null, ?string $sort = null, ?string $status = null, ?string $batchId = null, ?bool $standalone = null, bool $reveal = false): array
    {
        $query = $this->paginationQuery($cursor, $limit, $sort);
        foreach (compact('status', 'batchId', 'standalone', 'reveal') as $key => $value) {
            if ($value !== null) {
                $query[$key] = $value;
            }
        }

        return $this->client->requestJson('GET', '/api/v1/giftcard/card', null, $query);
    }

    /** @return array<string, mixed> */
    public function getCard(string $cardId, bool $reveal = false): array
    {
        return $this->client->requestJson('GET', '/api/v1/giftcard/card/' . rawurlencode($cardId), null, ['reveal' => $reveal]);
    }

    /** @return array{revokedCount: int, skippedCount: int, refundedAmount: string} */
    public function revokeCard(string $cardId, ?string $reason = null): array
    {
        return $this->client->requestJson('POST', '/api/v1/giftcard/card/' . rawurlencode($cardId) . '/revoke', $this->reasonPayload($reason));
    }

    /** @return array<string, string|int> */
    private function paginationQuery(?string $cursor, ?int $limit, ?string $sort): array
    {
        return array_filter(compact('cursor', 'limit', 'sort'), static fn (mixed $value): bool => $value !== null);
    }

    /** @return array<string, string> */
    private function reasonPayload(?string $reason): array
    {
        return $reason === null ? [] : ['reason' => $reason];
    }
}
