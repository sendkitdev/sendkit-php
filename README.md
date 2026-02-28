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
