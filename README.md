# TopCharge PHP SDK

PHP client for the TopCharge merchant API.

## Requirements

- PHP 8.1+
- The `curl` extension when using the default HTTP client

## Release status

This SDK is currently distributed from this repository for local development. The Composer package name below is reserved for its future public release; do not assume it is available on Packagist yet.

## Install locally

Add a path repository to your application's `composer.json` (use the absolute path on your machine):

```json
{
  "repositories": [
    {
      "type": "path",
      "url": "/absolute/path/to/sdk/php",
      "options": { "symlink": true }
    }
  ],
  "require": {
    "topcharge/php-sdk": "*"
  }
}
```

Then run `composer update topcharge/php-sdk`. Once the package is published, use `composer require topcharge/php-sdk` instead.

## Quick start

Store credentials in environment variables. Do not commit them or put them in source code.

```php
use TopCharge\SDK\TopChargeClient;

$client = new TopChargeClient(
    clientId: getenv('TOPCHARGE_CLIENT_ID'),
    secretKey: getenv('TOPCHARGE_SECRET_KEY'),
);

$balance = $client->account->getBalance();

$payment = $client->payments->create(
    amount: '25000',
    description: 'Invoice #1001',
    redirectUrl: 'https://merchant.example/success',
    callbackUrl: 'https://merchant.example/webhooks/payment',
);

header('Location: ' . $payment['paymentUrl']);
exit;
```

The default base URL is `https://api.topcharge.app`. Pass `baseUrl:` to use a test or staging environment.

`clientId` is sent as `x-api-key` and `secretKey` as `x-api-secret`. Keep both only in server-side secret storage or environment variables; never expose them in browser code, logs, error reports, or version control.

## Services

```php
$client->account->verifyCredentials();
$client->account->getBalance();

$client->payments->create('25000', 'Invoice #1001');
$client->payments->getStatus($paymentId);
$client->payments->cancel($paymentId);

$client->withdrawals->create('964', '7701234567', '50000');

```

All successful JSON responses are returned as associative arrays. `verifyCredentials()` returns `void`.

## Integration examples

The ordinary test suite never calls the live API. Each API operation has its own opt-in integration example. They use environment variables only and never write credentials to disk.

```bash
TOPCHARGE_RUN_INTEGRATION=1 \
TOPCHARGE_CLIENT_ID='mch_...' \
TOPCHARGE_SECRET_KEY='sk_...' \
php tests/integration.php help
```

Examples:

```bash
# Creates a PENDING payment and prints its paymentId and paymentUrl.
php tests/integration.php payment:create

# Set the ID printed by create, then inspect or cancel that exact payment.
export TOPCHARGE_PAYMENT_ID='01...'
php tests/integration.php payment:status
php tests/integration.php payment:cancel

# A full payment-link smoke test: create, read status, then cancel automatically.
php tests/integration.php payment:smoke

# Read account balance.
php tests/integration.php account:balance
```

Run `php tests/integration.php help` to see the separate command and required variables for every Account, Payment, and Withdrawal method. `payment:create` defaults to `50000` IQD; change it with `TOPCHARGE_TEST_PAYMENT_AMOUNT`.

Optionally set `TOPCHARGE_BASE_URL` for a non-production environment. The withdrawal example requires an additional explicit environment confirmation because it moves value.

## Errors

API failures throw `TopCharge\SDK\Exception\ApiException`. Inspect `getStatusCode()` and `getResponseBody()` when handling an error.

```php
use TopCharge\SDK\Exception\ApiException;

try {
    $client->payments->getStatus($paymentId);
} catch (ApiException $exception) {
    if ($exception->getStatusCode() === 404) {
        // Payment does not exist for this merchant.
    }

    throw $exception;
}
```

## Reliability

The SDK does not automatically retry requests. Retrying reads is normally safe. Do not blindly retry a payment creation or withdrawal if the network result is unknown; first resolve its outcome with your own business record or the API. The default HTTP timeout is 30 seconds.

## License

[MIT License](LICENSE)
