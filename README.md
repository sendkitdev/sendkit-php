# SendKit PHP SDK

Official PHP SDK for the [SendKit](https://sendkit.com) email API.

## Installation

```bash
composer require sendkit/sendkit-php
```

## Usage

### Create a Client

```php
use SendKit\SendKit;

$client = SendKit::client('your-api-key');
```

### Send an Email

```php
$response = $client->emails()->send([
    'from' => 'you@example.com',
    'to' => 'recipient@example.com',
    'subject' => 'Hello from SendKit',
    'html' => '<h1>Welcome!</h1>',
]);

echo $response['id']; // Email ID
```

### Send a MIME Email

```php
$response = $client->emails()->sendMime(
    envelopeFrom: 'you@example.com',
    envelopeTo: 'recipient@example.com',
    rawMessage: $mimeString,
);

echo $response['id'];
```

### Validate an Email

```php
$result = $client->validateEmail('recipient@example.com');

$result['is_valid'];       // true or false
$result['should_block'];   // true if the email should be blocked
$result['block_reason'];   // reason for blocking, or null
$result['evaluations'];    // detailed evaluation results
```

The `evaluations` array contains:
- `has_valid_syntax` — whether the email has valid syntax
- `has_valid_dns` — whether the domain has valid DNS records
- `mailbox_exists` — whether the mailbox exists
- `is_role_address` — whether it's a role address (e.g. info@, admin@)
- `is_disposable` — whether it's a disposable email
- `is_random_input` — whether it appears to be random input

> **Note:** Each validation costs credits. A `SendKitException` with status 402 is thrown when credits are insufficient.

### Error Handling

```php
use SendKit\Exceptions\SendKitException;

try {
    $client->emails()->send([...]);
} catch (SendKitException $e) {
    echo $e->getMessage(); // Error message
    echo $e->status;       // HTTP status code
}
```
