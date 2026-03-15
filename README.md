# SendKit PHP SDK

Official PHP SDK for the [SendKit](https://sendkit.dev) email API.

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

### Contacts

#### Create or Update a Contact (Upsert)

```php
$contact = $client->contacts()->create([
    'email' => 'john@example.com',
    'first_name' => 'John',
    'last_name' => 'Doe',
    'unsubscribed' => false,
    'list_ids' => ['list-uuid-1', 'list-uuid-2'],
    'properties' => ['COMPANY' => 'Acme'],
]);

echo $contact['data']['id'];
```

If a contact with that email already exists, it will be updated instead.

#### List Contacts

```php
$contacts = $client->contacts()->list();
$contacts = $client->contacts()->list(['page' => 2]);

$contacts['data'];           // array of contacts
$contacts['meta']['total'];  // total count
```

#### Get a Contact

```php
$contact = $client->contacts()->get('contact-uuid');
```

#### Update a Contact

```php
$contact = $client->contacts()->update('contact-uuid', [
    'first_name' => 'Johnny',
    'unsubscribed' => true,
]);
```

#### Delete a Contact

```php
$client->contacts()->delete('contact-uuid');
```

#### Add a Contact to Lists

```php
$contact = $client->contacts()->addToLists('contact-uuid', ['list-uuid-1', 'list-uuid-2']);
```

#### List a Contact's Lists

```php
$lists = $client->contacts()->listLists('contact-uuid');
$lists = $client->contacts()->listLists('contact-uuid', ['page' => 2]);
```

#### Remove a Contact from a List

```php
$client->contacts()->removeFromList('contact-uuid', 'list-uuid');
```

### Contact Properties

#### Create a Property

```php
$property = $client->contactProperties()->create([
    'key' => 'company',
    'type' => 'string',           // "string", "number", or "date"
    'fallback_value' => 'N/A',    // optional
]);

echo $property['data']['id'];
```

#### List Properties

```php
$properties = $client->contactProperties()->list();
$properties = $client->contactProperties()->list(['page' => 2]);
```

#### Update a Property

```php
$property = $client->contactProperties()->update('property-uuid', [
    'key' => 'organization',
    'fallback_value' => 'Unknown',
]);
```

#### Delete a Property

```php
$client->contactProperties()->delete('property-uuid');
```

> **Note:** A `SendKitException` with status 409 is thrown if the property is used in segment filters.

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
