<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use TopCharge\SDK\Exception\ApiException;
use TopCharge\SDK\TopChargeClient;

/** Run `php tests/integration.php help` to list the separate API examples. */
$action = $argv[1] ?? 'help';

if ($action === 'help') {
    printHelp();
    exit(0);
}

try {
    runAction(createClient(), $action);
} catch (ApiException $exception) {
    fwrite(STDERR, sprintf("TopCharge API error (%d): %s\n", $exception->getStatusCode(), $exception->getMessage()));
    exit(1);
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage() . "\n");
    exit(1);
}

function createClient(): TopChargeClient
{
    if (getenv('TOPCHARGE_RUN_INTEGRATION') !== '1') {
        throw new RuntimeException('Refusing to call the API. Set TOPCHARGE_RUN_INTEGRATION=1.');
    }

    return new TopChargeClient(
        requiredEnv('TOPCHARGE_CLIENT_ID'),
        requiredEnv('TOPCHARGE_SECRET_KEY'),
        optionalEnv('TOPCHARGE_BASE_URL') ?? 'https://api.topcharge.app',
    );
}

function runAction(TopChargeClient $client, string $action): void
{
    switch ($action) {
        case 'account:verify':
            $client->account->verifyCredentials();
            echo "Credentials are valid.\n";
            return;
        case 'account:balance':
            printJson($client->account->getBalance());
            return;
        case 'payment:create':
            printJson($client->payments->create(
                optionalEnv('TOPCHARGE_TEST_PAYMENT_AMOUNT') ?? '50000',
                optionalEnv('TOPCHARGE_PAYMENT_DESCRIPTION') ?? 'TopCharge PHP SDK manual test',
                optionalEnv('TOPCHARGE_REDIRECT_URL') ?? 'https://example.com/topcharge-sdk-test',
                optionalEnv('TOPCHARGE_CALLBACK_URL') ?? 'https://example.com/topcharge-sdk-webhook',
            ));
            return;
        case 'payment:status':
            printJson($client->payments->getStatus(requiredEnv('TOPCHARGE_PAYMENT_ID')));
            return;
        case 'payment:cancel':
            printJson($client->payments->cancel(requiredEnv('TOPCHARGE_PAYMENT_ID')));
            return;
        case 'payment:smoke':
            runPaymentSmokeTest($client);
            return;
        case 'withdrawal:create':
            requireConfirmation('TOPCHARGE_ALLOW_MONEY_MOVEMENT');
            printJson($client->withdrawals->create(
                requiredEnv('TOPCHARGE_PHONE_COUNTRY_CODE'),
                requiredEnv('TOPCHARGE_PHONE_NUMBER'),
                requiredEnv('TOPCHARGE_WITHDRAWAL_AMOUNT'),
            ));
            return;
        case 'giftcard:issue':
            requireConfirmation('TOPCHARGE_ALLOW_VALUE_ISSUANCE');
            printJson($client->giftCards->issue(
                requiredEnv('TOPCHARGE_GIFTCARD_AMOUNT'),
                requiredEnv('TOPCHARGE_IDEMPOTENCY_KEY'),
                optionalPositiveInt('TOPCHARGE_GIFTCARD_QUANTITY'),
                optionalEnv('TOPCHARGE_GIFTCARD_NAME'),
            ));
            return;
        case 'giftcard:batches':
            printJson($client->giftCards->listBatches(cursor(), limit(), paginationSort()));
            return;
        case 'giftcard:batch':
            printJson($client->giftCards->getBatch(requiredEnv('TOPCHARGE_BATCH_ID')));
            return;
        case 'giftcard:batch-cards':
            printJson($client->giftCards->listBatchCards(
                requiredEnv('TOPCHARGE_BATCH_ID'), cursor(), limit(), paginationSort(), booleanEnv('TOPCHARGE_REVEAL'),
            ));
            return;
        case 'giftcard:revoke-batch':
            requireConfirmation('TOPCHARGE_ALLOW_VALUE_REVOCATION');
            printJson($client->giftCards->revokeBatch(requiredEnv('TOPCHARGE_BATCH_ID'), optionalEnv('TOPCHARGE_REVOKE_REASON')));
            return;
        case 'giftcard:cards':
            printJson($client->giftCards->listCards(
                cursor(), limit(), paginationSort(), optionalEnv('TOPCHARGE_GIFTCARD_STATUS'), optionalEnv('TOPCHARGE_BATCH_ID'),
                nullableBooleanEnv('TOPCHARGE_STANDALONE'), booleanEnv('TOPCHARGE_REVEAL'),
            ));
            return;
        case 'giftcard:card':
            printJson($client->giftCards->getCard(requiredEnv('TOPCHARGE_CARD_ID'), booleanEnv('TOPCHARGE_REVEAL')));
            return;
        case 'giftcard:revoke-card':
            requireConfirmation('TOPCHARGE_ALLOW_VALUE_REVOCATION');
            printJson($client->giftCards->revokeCard(requiredEnv('TOPCHARGE_CARD_ID'), optionalEnv('TOPCHARGE_REVOKE_REASON')));
            return;
        default:
            throw new RuntimeException("Unknown action '{$action}'. Run: php tests/integration.php help");
    }
}

function runPaymentSmokeTest(TopChargeClient $client): void
{
    $paymentId = null;
    try {
        $payment = $client->payments->create(
            optionalEnv('TOPCHARGE_TEST_PAYMENT_AMOUNT') ?? '50000',
            'TopCharge PHP SDK smoke ' . bin2hex(random_bytes(8)),
            optionalEnv('TOPCHARGE_REDIRECT_URL') ?? 'https://example.com/topcharge-sdk-test',
            optionalEnv('TOPCHARGE_CALLBACK_URL') ?? 'https://example.com/topcharge-sdk-webhook',
        );
        $paymentId = $payment['paymentId'] ?? null;
        if (!is_string($paymentId) || $paymentId === '' || !isset($payment['paymentUrl'])) {
            throw new RuntimeException('Payment creation response is missing paymentId or paymentUrl.');
        }
        $status = $client->payments->getStatus($paymentId);
        if (($status['paymentId'] ?? null) !== $paymentId) {
            throw new RuntimeException('Payment status response does not match the created payment.');
        }
        echo "Payment link created and verified for payment ID: {$paymentId}\n";
    } finally {
        if (is_string($paymentId) && $paymentId !== '') {
            printJson($client->payments->cancel($paymentId));
            echo "Test payment cancelled.\n";
        }
    }
}

function requiredEnv(string $name): string
{
    return optionalEnv($name) ?? throw new RuntimeException("{$name} is required.");
}

function optionalEnv(string $name): ?string
{
    $value = getenv($name);
    return is_string($value) && $value !== '' ? $value : null;
}

function requireConfirmation(string $name): void
{
    if (getenv($name) !== '1') {
        throw new RuntimeException("This action changes value or money. Set {$name}=1 to continue.");
    }
}

function optionalPositiveInt(string $name): ?int
{
    $value = optionalEnv($name);
    if ($value === null) {
        return null;
    }
    if (filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) {
        throw new RuntimeException("{$name} must be a positive integer.");
    }
    return (int) $value;
}

function cursor(): ?string
{
    return optionalEnv('TOPCHARGE_CURSOR');
}

function limit(): ?int
{
    return optionalPositiveInt('TOPCHARGE_LIMIT');
}

function paginationSort(): ?string
{
    $value = optionalEnv('TOPCHARGE_SORT');
    if ($value !== null && !in_array($value, ['asc', 'desc'], true)) {
        throw new RuntimeException('TOPCHARGE_SORT must be asc or desc.');
    }
    return $value;
}

function booleanEnv(string $name, bool $default = false): bool
{
    $value = optionalEnv($name);
    if ($value === null) {
        return $default;
    }
    return match (strtolower($value)) {
        '1', 'true', 'yes' => true,
        '0', 'false', 'no' => false,
        default => throw new RuntimeException("{$name} must be true or false."),
    };
}

function nullableBooleanEnv(string $name): ?bool
{
    return optionalEnv($name) === null ? null : booleanEnv($name);
}

/** @param array<string, mixed> $response */
function printJson(array $response): void
{
    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
}

function printHelp(): void
{
    echo <<<'TEXT'
TopCharge PHP SDK integration examples

First set these for every API call:
  export TOPCHARGE_RUN_INTEGRATION=1
  export TOPCHARGE_CLIENT_ID='mch_...'
  export TOPCHARGE_SECRET_KEY='sk_...'

Read-only actions:
  account:verify
  account:balance
  payment:status          (TOPCHARGE_PAYMENT_ID)
  giftcard:batches        (optional TOPCHARGE_CURSOR, TOPCHARGE_LIMIT, TOPCHARGE_SORT)
  giftcard:batch          (TOPCHARGE_BATCH_ID)
  giftcard:batch-cards    (TOPCHARGE_BATCH_ID; optional TOPCHARGE_REVEAL=true)
  giftcard:cards          (optional TOPCHARGE_GIFTCARD_STATUS, TOPCHARGE_BATCH_ID, TOPCHARGE_STANDALONE=true, TOPCHARGE_REVEAL=true)
  giftcard:card           (TOPCHARGE_CARD_ID; optional TOPCHARGE_REVEAL=true)

Payment actions:
  payment:create          (optional TOPCHARGE_TEST_PAYMENT_AMOUNT; leaves a PENDING payment)
  payment:cancel          (TOPCHARGE_PAYMENT_ID)
  payment:smoke           (creates, reads, then cancels one test payment)

Value-moving actions require an extra explicit confirmation:
  withdrawal:create       (TOPCHARGE_ALLOW_MONEY_MOVEMENT=1, TOPCHARGE_PHONE_COUNTRY_CODE, TOPCHARGE_PHONE_NUMBER, TOPCHARGE_WITHDRAWAL_AMOUNT)
  giftcard:issue          (TOPCHARGE_ALLOW_VALUE_ISSUANCE=1, TOPCHARGE_GIFTCARD_AMOUNT, TOPCHARGE_IDEMPOTENCY_KEY; optional TOPCHARGE_GIFTCARD_QUANTITY, TOPCHARGE_GIFTCARD_NAME)
  giftcard:revoke-batch   (TOPCHARGE_ALLOW_VALUE_REVOCATION=1, TOPCHARGE_BATCH_ID; optional TOPCHARGE_REVOKE_REASON)
  giftcard:revoke-card    (TOPCHARGE_ALLOW_VALUE_REVOCATION=1, TOPCHARGE_CARD_ID; optional TOPCHARGE_REVOKE_REASON)

Example:
  php tests/integration.php payment:smoke
TEXT;
}
