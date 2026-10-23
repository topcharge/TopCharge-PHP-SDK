<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use TopCharge\SDK\Exception\ApiException;
use TopCharge\SDK\Http\HttpClientInterface;
use TopCharge\SDK\Http\HttpResponse;
use TopCharge\SDK\TopChargeClient;

final class FakeHttpClient implements HttpClientInterface
{
    /** @var list<array{method: string, url: string, headers: array<string, string>, body: string|null}> */
    public array $requests = [];

    /** @param list<HttpResponse> $responses */
    public function __construct(private array $responses)
    {
    }

    public function request(string $method, string $url, array $headers, ?string $body = null): HttpResponse
    {
        $this->requests[] = compact('method', 'url', 'headers', 'body');
        return array_shift($this->responses) ?? new HttpResponse(500, '{}');
    }
}

function assertSame(mixed $expected, mixed $actual, string $message = ''): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message ?: 'Assertion failed.');
    }
}

$http = new FakeHttpClient([
    new HttpResponse(200, '{"availableBalance":"140000.0000"}'),
    new HttpResponse(201, '{"paymentId":"pay_123","paymentUrl":"https:\/\/checkout.example\/pay_123"}'),
    new HttpResponse(200, '{"items":[],"cursor":null}'),
    new HttpResponse(401, '{"ok":false,"err":{"message":"invalid API credentials"}}'),
]);
$client = new TopChargeClient('mch_test_example', 'sk_test_example', 'https://api.example.test/', $http);

assertSame('140000.0000', $client->account->getBalance()['availableBalance']);
$payment = $client->payments->create('25000', 'Invoice #1001');
assertSame('pay_123', $payment['paymentId']);
$client->giftCards->listCards(limit: 20, standalone: true);

assertSame('https://api.example.test/api/v1/account/balance/', $http->requests[0]['url']);
assertSame('mch_test_example', $http->requests[0]['headers']['x-api-key']);
assertSame('sk_test_example', $http->requests[0]['headers']['x-api-secret']);
assertSame('{"paymentAmount":"25000","description":"Invoice #1001"}', $http->requests[1]['body']);
assertSame('https://api.example.test/api/v1/giftcard/card/?limit=20&standalone=1&reveal=0', $http->requests[2]['url']);

try {
    $client->account->verifyCredentials();
    throw new RuntimeException('Expected ApiException was not thrown.');
} catch (ApiException $exception) {
    assertSame(401, $exception->getStatusCode());
    assertSame('invalid API credentials', $exception->getMessage());
}

echo "All SDK tests passed.\n";
