<?php

declare(strict_types=1);

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use SendKit\Client;
use SendKit\ContactProperties;
use SendKit\Contacts;
use SendKit\Emails;
use SendKit\Exceptions\SendKitException;
use SendKit\SendKit;

function createMockClient(array &$history, array $responses): Client
{
    $mock = new MockHandler($responses);
    $stack = HandlerStack::create($mock);
    $stack->push(Middleware::history($history));

    $guzzle = new GuzzleClient([
        'handler' => $stack,
        'base_uri' => 'https://api.sendkit.dev',
        'headers' => [
            'Authorization' => 'Bearer test-api-key',
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ],
    ]);

    return new Client('test-api-key', http: $guzzle);
}

it('creates a client via static factory', function () {
    $client = SendKit::client('my-api-key');

    expect($client)->toBeInstanceOf(Client::class);
});

it('creates a client with custom base URL', function () {
    $client = SendKit::client('my-api-key', 'https://custom.api.com');

    expect($client)->toBeInstanceOf(Client::class);
});

it('sends a structured email', function () {
    $history = [];
    $client = createMockClient($history, [
        new Response(200, [], json_encode(['id' => 'email-uuid-123'])),
    ]);

    $result = $client->emails()->send([
        'from' => 'sender@example.com',
        'to' => 'recipient@example.com',
        'subject' => 'Test Email',
        'html' => '<p>Hello</p>',
    ]);

    expect($result)->toBe(['id' => 'email-uuid-123']);
    expect($history)->toHaveCount(1);

    $request = $history[0]['request'];
    expect($request->getMethod())->toBe('POST');
    expect($request->getUri()->getPath())->toBe('/emails');

    $body = json_decode($request->getBody()->getContents(), true);
    expect($body['from'])->toBe('sender@example.com');
    expect($body['to'])->toBe('recipient@example.com');
    expect($body['subject'])->toBe('Test Email');
});

it('sends a MIME email', function () {
    $history = [];
    $client = createMockClient($history, [
        new Response(200, [], json_encode(['id' => 'mime-uuid-456'])),
    ]);

    $result = $client->emails()->sendMime(
        'sender@example.com',
        'recipient@example.com',
        'From: sender@example.com\r\nTo: recipient@example.com\r\n\r\nHello',
    );

    expect($result)->toBe(['id' => 'mime-uuid-456']);
    expect($history)->toHaveCount(1);

    $request = $history[0]['request'];
    expect($request->getMethod())->toBe('POST');
    expect($request->getUri()->getPath())->toBe('/emails/mime');

    $body = json_decode($request->getBody()->getContents(), true);
    expect($body['envelope_from'])->toBe('sender@example.com');
    expect($body['envelope_to'])->toBe('recipient@example.com');
    expect($body['raw_message'])->toContain('Hello');
});

it('throws SendKitException on API error', function () {
    $history = [];
    $client = createMockClient($history, [
        new Response(422, [], json_encode(['message' => 'Validation failed'])),
    ]);

    $client->emails()->send([
        'from' => 'sender@example.com',
        'to' => 'recipient@example.com',
        'subject' => 'Test',
        'html' => '<p>Hi</p>',
    ]);
})->throws(SendKitException::class, 'Validation failed');

it('returns the emails service', function () {
    $client = new Client('test-key');

    $emails = $client->emails();

    expect($emails)->toBeInstanceOf(Emails::class);
    expect($client->emails())->toBe($emails);
});

it('returns the email validations service', function () {
    $client = new Client('test-key');

    $validations = $client->emailValidations();

    expect($validations)->toBeInstanceOf(\SendKit\EmailValidations::class);
    expect($client->emailValidations())->toBe($validations);
});

it('validates an email address', function () {
    $history = [];
    $client = createMockClient($history, [
        new Response(200, [], json_encode([
            'email' => 'user@example.com',
            'is_valid' => 'HIGH',
            'evaluations' => [
                'has_valid_syntax' => 'YES',
                'has_valid_dns' => 'YES',
                'mailbox_exists' => 'YES',
                'is_role_address' => 'NO',
                'is_disposable' => 'NO',
                'is_random_input' => 'NO',
            ],
            'should_block' => false,
            'block_reason' => null,
            'validated_at' => '2026-03-05 12:00:00',
        ])),
    ]);

    $result = $client->validateEmail('user@example.com');

    expect($result['email'])->toBe('user@example.com');
    expect($result['is_valid'])->toBe('HIGH');
    expect($result['evaluations']['has_valid_syntax'])->toBe('YES');
    expect($result['evaluations']['is_disposable'])->toBe('NO');
    expect($result['should_block'])->toBeFalse();
    expect($history)->toHaveCount(1);

    $request = $history[0]['request'];
    expect($request->getMethod())->toBe('POST');
    expect($request->getUri()->getPath())->toBe('/emails/validate');

    $body = json_decode($request->getBody()->getContents(), true);
    expect($body['email'])->toBe('user@example.com');
});

it('validates an email via emailValidations service directly', function () {
    $history = [];
    $client = createMockClient($history, [
        new Response(200, [], json_encode([
            'email' => 'test@example.com',
            'is_valid' => 'LOW',
            'evaluations' => [
                'has_valid_syntax' => 'YES',
                'has_valid_dns' => 'NO',
                'mailbox_exists' => 'NO',
                'is_role_address' => 'NO',
                'is_disposable' => 'YES',
                'is_random_input' => 'NO',
            ],
            'should_block' => true,
            'block_reason' => 'disposable',
            'validated_at' => '2026-03-05 12:00:00',
        ])),
    ]);

    $result = $client->emailValidations()->validate('test@example.com');

    expect($result['is_valid'])->toBe('LOW');
    expect($result['evaluations']['is_disposable'])->toBe('YES');
    expect($result['should_block'])->toBeTrue();
    expect($result['block_reason'])->toBe('disposable');
});

it('throws SendKitException when validation credits are insufficient', function () {
    $history = [];
    $client = createMockClient($history, [
        new Response(402, [], json_encode(['message' => 'Insufficient validation credits.'])),
    ]);

    $client->validateEmail('user@example.com');
})->throws(SendKitException::class, 'Insufficient validation credits.');

it('sends an email to multiple recipients', function () {
    $history = [];
    $client = createMockClient($history, [
        new Response(200, [], json_encode(['id' => 'multi-uuid'])),
    ]);

    $result = $client->emails()->send([
        'from' => 'sender@example.com',
        'to' => ['alice@example.com', 'bob@example.com'],
        'subject' => 'Multi Recipient',
        'html' => '<p>Hello all</p>',
    ]);

    expect($result)->toBe(['id' => 'multi-uuid']);

    $body = json_decode($history[0]['request']->getBody()->getContents(), true);
    expect($body['to'])->toBe(['alice@example.com', 'bob@example.com']);
});

it('sends an email with display name format in to field', function () {
    $history = [];
    $client = createMockClient($history, [
        new Response(200, [], json_encode(['id' => 'display-uuid'])),
    ]);

    $result = $client->emails()->send([
        'from' => 'Sender <sender@example.com>',
        'to' => 'Alice <alice@example.com>',
        'subject' => 'Display Name Test',
        'html' => '<p>Hi Alice</p>',
    ]);

    expect($result)->toBe(['id' => 'display-uuid']);

    $body = json_decode($history[0]['request']->getBody()->getContents(), true);
    expect($body['to'])->toBe('Alice <alice@example.com>');
    expect($body['from'])->toBe('Sender <sender@example.com>');
});

it('sends an email with text body', function () {
    $history = [];
    $client = createMockClient($history, [
        new Response(200, [], json_encode(['id' => 'text-uuid'])),
    ]);

    $result = $client->emails()->send([
        'from' => 'sender@example.com',
        'to' => 'recipient@example.com',
        'subject' => 'Plain Text',
        'text' => 'Hello, plain text!',
    ]);

    expect($result)->toBe(['id' => 'text-uuid']);

    $body = json_decode($history[0]['request']->getBody()->getContents(), true);
    expect($body['text'])->toBe('Hello, plain text!');
    expect($body)->not->toHaveKey('html');
});

it('sends an email with cc and bcc', function () {
    $history = [];
    $client = createMockClient($history, [
        new Response(200, [], json_encode(['id' => 'cc-bcc-uuid'])),
    ]);

    $result = $client->emails()->send([
        'from' => 'sender@example.com',
        'to' => 'recipient@example.com',
        'subject' => 'CC/BCC Test',
        'html' => '<p>Test</p>',
        'cc' => ['cc1@example.com', 'cc2@example.com'],
        'bcc' => ['bcc1@example.com'],
    ]);

    expect($result)->toBe(['id' => 'cc-bcc-uuid']);

    $body = json_decode($history[0]['request']->getBody()->getContents(), true);
    expect($body['cc'])->toBe(['cc1@example.com', 'cc2@example.com']);
    expect($body['bcc'])->toBe(['bcc1@example.com']);
});

it('sends an email with reply_to', function () {
    $history = [];
    $client = createMockClient($history, [
        new Response(200, [], json_encode(['id' => 'reply-uuid'])),
    ]);

    $result = $client->emails()->send([
        'from' => 'sender@example.com',
        'to' => 'recipient@example.com',
        'subject' => 'Reply-To Test',
        'html' => '<p>Test</p>',
        'reply_to' => 'replies@example.com',
    ]);

    expect($result)->toBe(['id' => 'reply-uuid']);

    $body = json_decode($history[0]['request']->getBody()->getContents(), true);
    expect($body['reply_to'])->toBe(['replies@example.com']);
});

it('sends an email with reply_to as array', function () {
    $history = [];
    $client = createMockClient($history, [
        new Response(200, [], json_encode(['id' => 'reply-arr-uuid'])),
    ]);

    $result = $client->emails()->send([
        'from' => 'sender@example.com',
        'to' => 'recipient@example.com',
        'subject' => 'Reply-To Array Test',
        'html' => '<p>Test</p>',
        'reply_to' => ['replies@example.com', 'support@example.com'],
    ]);

    expect($result)->toBe(['id' => 'reply-arr-uuid']);

    $body = json_decode($history[0]['request']->getBody()->getContents(), true);
    expect($body['reply_to'])->toBe(['replies@example.com', 'support@example.com']);
});

it('sends an email with custom headers', function () {
    $history = [];
    $client = createMockClient($history, [
        new Response(200, [], json_encode(['id' => 'headers-uuid'])),
    ]);

    $result = $client->emails()->send([
        'from' => 'sender@example.com',
        'to' => 'recipient@example.com',
        'subject' => 'Headers Test',
        'html' => '<p>Test</p>',
        'headers' => [
            'X-Custom-Header' => 'custom-value',
            'X-Another' => 'another-value',
        ],
    ]);

    expect($result)->toBe(['id' => 'headers-uuid']);

    $body = json_decode($history[0]['request']->getBody()->getContents(), true);
    expect($body['headers'])->toBe([
        'X-Custom-Header' => 'custom-value',
        'X-Another' => 'another-value',
    ]);
});

it('sends an email with tags in name-value format', function () {
    $history = [];
    $client = createMockClient($history, [
        new Response(200, [], json_encode(['id' => 'tags-uuid'])),
    ]);

    $result = $client->emails()->send([
        'from' => 'sender@example.com',
        'to' => 'recipient@example.com',
        'subject' => 'Tags Test',
        'html' => '<p>Test</p>',
        'tags' => [
            ['name' => 'category', 'value' => 'transactional'],
            ['name' => 'priority', 'value' => 'high'],
        ],
    ]);

    expect($result)->toBe(['id' => 'tags-uuid']);

    $body = json_decode($history[0]['request']->getBody()->getContents(), true);
    expect($body['tags'])->toBe([
        ['name' => 'category', 'value' => 'transactional'],
        ['name' => 'priority', 'value' => 'high'],
    ]);
});

it('sends an email with scheduled_at', function () {
    $history = [];
    $client = createMockClient($history, [
        new Response(200, [], json_encode(['id' => 'scheduled-uuid'])),
    ]);

    $result = $client->emails()->send([
        'from' => 'sender@example.com',
        'to' => 'recipient@example.com',
        'subject' => 'Scheduled Test',
        'html' => '<p>Test</p>',
        'scheduled_at' => '2026-12-25T10:00:00Z',
    ]);

    expect($result)->toBe(['id' => 'scheduled-uuid']);

    $body = json_decode($history[0]['request']->getBody()->getContents(), true);
    expect($body['scheduled_at'])->toBe('2026-12-25T10:00:00Z');
});

it('sends an email with attachments', function () {
    $history = [];
    $client = createMockClient($history, [
        new Response(200, [], json_encode(['id' => 'attach-uuid'])),
    ]);

    $result = $client->emails()->send([
        'from' => 'sender@example.com',
        'to' => 'recipient@example.com',
        'subject' => 'Attachment Test',
        'html' => '<p>See attached</p>',
        'attachments' => [
            [
                'filename' => 'report.pdf',
                'content' => base64_encode('fake-pdf-content'),
                'content_type' => 'application/pdf',
            ],
        ],
    ]);

    expect($result)->toBe(['id' => 'attach-uuid']);

    $body = json_decode($history[0]['request']->getBody()->getContents(), true);
    expect($body['attachments'])->toHaveCount(1);
    expect($body['attachments'][0]['filename'])->toBe('report.pdf');
    expect($body['attachments'][0]['content_type'])->toBe('application/pdf');
});

it('omits null fields from the JSON payload', function () {
    $history = [];
    $client = createMockClient($history, [
        new Response(200, [], json_encode(['id' => 'null-filter-uuid'])),
    ]);

    $result = $client->emails()->send([
        'from' => 'sender@example.com',
        'to' => 'recipient@example.com',
        'subject' => 'Null Filter Test',
        'html' => '<p>Test</p>',
        'cc' => null,
        'bcc' => null,
        'reply_to' => null,
        'headers' => null,
        'tags' => null,
        'scheduled_at' => null,
        'attachments' => null,
    ]);

    expect($result)->toBe(['id' => 'null-filter-uuid']);

    $body = json_decode($history[0]['request']->getBody()->getContents(), true);
    expect($body)->toBe([
        'from' => 'sender@example.com',
        'to' => 'recipient@example.com',
        'subject' => 'Null Filter Test',
        'html' => '<p>Test</p>',
    ]);
});

it('throws SendKitException with missing_api_key name when API key is empty', function () {
    new Client('');
})->throws(SendKitException::class, 'The SendKit API key is not set.');

it('throws SendKitException with missing_api_key name via static factory', function () {
    SendKit::client('');
})->throws(SendKitException::class, 'The SendKit API key is not set.');

it('has the missing_api_key name on the exception', function () {
    try {
        new Client('');
    } catch (SendKitException $e) {
        expect($e->name)->toBe('missing_api_key');
        expect($e->status)->toBe(0);

        return;
    }

    test()->fail('Expected SendKitException was not thrown.');
});

it('falls back to SENDKIT_API_KEY environment variable', function () {
    putenv('SENDKIT_API_KEY=env-api-key');

    try {
        $client = new Client('');
        expect($client)->toBeInstanceOf(Client::class);
    } finally {
        putenv('SENDKIT_API_KEY');
    }
});

it('normalizes cc from string to array', function () {
    $history = [];
    $client = createMockClient($history, [
        new Response(200, [], json_encode(['id' => 'cc-string-uuid'])),
    ]);

    $result = $client->emails()->send([
        'from' => 'sender@example.com',
        'to' => 'recipient@example.com',
        'subject' => 'CC String Test',
        'html' => '<p>Test</p>',
        'cc' => 'cc@example.com',
    ]);

    expect($result)->toBe(['id' => 'cc-string-uuid']);

    $body = json_decode($history[0]['request']->getBody()->getContents(), true);
    expect($body['cc'])->toBe(['cc@example.com']);
});

it('normalizes bcc from string to array', function () {
    $history = [];
    $client = createMockClient($history, [
        new Response(200, [], json_encode(['id' => 'bcc-string-uuid'])),
    ]);

    $result = $client->emails()->send([
        'from' => 'sender@example.com',
        'to' => 'recipient@example.com',
        'subject' => 'BCC String Test',
        'html' => '<p>Test</p>',
        'bcc' => 'bcc@example.com',
    ]);

    expect($result)->toBe(['id' => 'bcc-string-uuid']);

    $body = json_decode($history[0]['request']->getBody()->getContents(), true);
    expect($body['bcc'])->toBe(['bcc@example.com']);
});

it('prefers explicit API key over environment variable', function () {
    putenv('SENDKIT_API_KEY=env-key');

    try {
        $history = [];
        $mock = new \GuzzleHttp\Handler\MockHandler([
            new Response(200, [], json_encode(['id' => 'explicit-uuid'])),
        ]);
        $stack = \GuzzleHttp\HandlerStack::create($mock);
        $stack->push(\GuzzleHttp\Middleware::history($history));

        $guzzle = new \GuzzleHttp\Client([
            'handler' => $stack,
            'base_uri' => 'https://api.sendkit.dev',
            'headers' => [
                'Authorization' => 'Bearer explicit-key',
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ],
        ]);

        $client = new Client('explicit-key', http: $guzzle);
        expect($client)->toBeInstanceOf(Client::class);
    } finally {
        putenv('SENDKIT_API_KEY');
    }
});

// --- Contacts ---

it('returns the contacts service', function () {
    $client = new Client('test-key');

    $contacts = $client->contacts();

    expect($contacts)->toBeInstanceOf(Contacts::class);
    expect($client->contacts())->toBe($contacts);
});

it('creates a contact', function () {
    $history = [];
    $client = createMockClient($history, [
        new Response(201, [], json_encode([
            'data' => [
                'id' => 'contact-uuid-123',
                'email' => 'john@example.com',
                'first_name' => 'John',
                'last_name' => 'Doe',
                'user_id' => null,
                'unsubscribed' => false,
                'properties' => [],
                'lists' => [],
                'created_at' => '2026-03-14 12:00:00',
                'updated_at' => '2026-03-14 12:00:00',
            ],
        ])),
    ]);

    $result = $client->contacts()->create([
        'email' => 'john@example.com',
        'first_name' => 'John',
        'last_name' => 'Doe',
    ]);

    expect($result['data']['id'])->toBe('contact-uuid-123');
    expect($result['data']['email'])->toBe('john@example.com');
    expect($result['data']['properties'])->toBe([]);
    expect($result['data']['lists'])->toBe([]);
    expect($history)->toHaveCount(1);

    $request = $history[0]['request'];
    expect($request->getMethod())->toBe('POST');
    expect($request->getUri()->getPath())->toBe('/contacts');

    $body = json_decode($request->getBody()->getContents(), true);
    expect($body['email'])->toBe('john@example.com');
    expect($body['first_name'])->toBe('John');
    expect($body['last_name'])->toBe('Doe');
});

it('upserts an existing contact and returns 200', function () {
    $history = [];
    $client = createMockClient($history, [
        new Response(200, [], json_encode([
            'data' => [
                'id' => 'contact-uuid-123',
                'email' => 'john@example.com',
                'first_name' => 'Johnny',
                'last_name' => 'Doe',
                'user_id' => null,
                'unsubscribed' => false,
                'properties' => ['COMPANY' => 'Acme'],
                'lists' => [],
                'created_at' => '2026-03-14 12:00:00',
                'updated_at' => '2026-03-14 13:00:00',
            ],
        ])),
    ]);

    $result = $client->contacts()->create([
        'email' => 'john@example.com',
        'first_name' => 'Johnny',
    ]);

    expect($result['data']['first_name'])->toBe('Johnny');
    expect($result['data']['properties']['COMPANY'])->toBe('Acme');

    $request = $history[0]['request'];
    expect($request->getMethod())->toBe('POST');
    expect($request->getUri()->getPath())->toBe('/contacts');
});

it('creates a contact with list_ids and properties', function () {
    $history = [];
    $client = createMockClient($history, [
        new Response(201, [], json_encode([
            'data' => [
                'id' => 'contact-uuid-456',
                'email' => 'jane@example.com',
                'first_name' => 'Jane',
                'last_name' => null,
                'user_id' => 'user-123',
                'unsubscribed' => false,
                'properties' => ['COMPANY' => 'Acme'],
                'lists' => [
                    ['id' => 'list-1', 'name' => 'Newsletter', 'created_at' => '2026-03-14 12:00:00', 'updated_at' => '2026-03-14 12:00:00'],
                    ['id' => 'list-2', 'name' => 'Updates', 'created_at' => '2026-03-14 12:00:00', 'updated_at' => '2026-03-14 12:00:00'],
                ],
                'created_at' => '2026-03-14 12:00:00',
                'updated_at' => '2026-03-14 12:00:00',
            ],
        ])),
    ]);

    $result = $client->contacts()->create([
        'email' => 'jane@example.com',
        'first_name' => 'Jane',
        'user_id' => 'user-123',
        'list_ids' => ['list-1', 'list-2'],
        'properties' => ['COMPANY' => 'Acme'],
    ]);

    expect($result['data']['id'])->toBe('contact-uuid-456');
    expect($result['data']['properties']['COMPANY'])->toBe('Acme');
    expect($result['data']['lists'])->toHaveCount(2);

    $body = json_decode($history[0]['request']->getBody()->getContents(), true);
    expect($body['list_ids'])->toBe(['list-1', 'list-2']);
    expect($body['properties'])->toBe(['COMPANY' => 'Acme']);
});

it('omits null fields when creating a contact', function () {
    $history = [];
    $client = createMockClient($history, [
        new Response(201, [], json_encode([
            'data' => [
                'id' => 'contact-uuid',
                'email' => 'test@example.com',
                'first_name' => null,
                'last_name' => null,
                'user_id' => null,
                'unsubscribed' => false,
                'properties' => [],
                'lists' => [],
                'created_at' => '2026-03-14 12:00:00',
                'updated_at' => '2026-03-14 12:00:00',
            ],
        ])),
    ]);

    $client->contacts()->create([
        'email' => 'test@example.com',
        'first_name' => null,
        'last_name' => null,
    ]);

    $body = json_decode($history[0]['request']->getBody()->getContents(), true);
    expect($body)->toBe(['email' => 'test@example.com']);
});

it('lists contacts', function () {
    $history = [];
    $client = createMockClient($history, [
        new Response(200, [], json_encode([
            'data' => [
                [
                    'id' => 'c1',
                    'email' => 'a@example.com',
                    'first_name' => 'Alice',
                    'last_name' => null,
                    'user_id' => null,
                    'unsubscribed' => false,
                    'properties' => [],
                    'lists' => [],
                    'created_at' => '2026-03-14 12:00:00',
                    'updated_at' => '2026-03-14 12:00:00',
                ],
                [
                    'id' => 'c2',
                    'email' => 'b@example.com',
                    'first_name' => 'Bob',
                    'last_name' => null,
                    'user_id' => null,
                    'unsubscribed' => false,
                    'properties' => [],
                    'lists' => [],
                    'created_at' => '2026-03-14 12:00:00',
                    'updated_at' => '2026-03-14 12:00:00',
                ],
            ],
            'links' => [
                'first' => 'https://api.sendkit.dev/contacts?page=1',
                'last' => 'https://api.sendkit.dev/contacts?page=1',
                'prev' => null,
                'next' => null,
            ],
            'meta' => [
                'current_page' => 1,
                'from' => 1,
                'last_page' => 1,
                'per_page' => 25,
                'to' => 2,
                'total' => 2,
                'path' => 'https://api.sendkit.dev/contacts',
            ],
        ])),
    ]);

    $result = $client->contacts()->list();

    expect($result['data'])->toHaveCount(2);
    expect($result['meta']['current_page'])->toBe(1);
    expect($result['meta']['total'])->toBe(2);
    expect($result['meta']['per_page'])->toBe(25);
    expect($result['links']['prev'])->toBeNull();
    expect($result['links']['next'])->toBeNull();
    expect($history)->toHaveCount(1);

    $request = $history[0]['request'];
    expect($request->getMethod())->toBe('GET');
    expect($request->getUri()->getPath())->toBe('/contacts');
});

it('lists contacts with query parameters', function () {
    $history = [];
    $client = createMockClient($history, [
        new Response(200, [], json_encode([
            'data' => [],
            'links' => [
                'first' => 'https://api.sendkit.dev/contacts?page=1',
                'last' => 'https://api.sendkit.dev/contacts?page=3',
                'prev' => 'https://api.sendkit.dev/contacts?page=1',
                'next' => 'https://api.sendkit.dev/contacts?page=3',
            ],
            'meta' => [
                'current_page' => 2,
                'from' => 26,
                'last_page' => 3,
                'per_page' => 25,
                'to' => 50,
                'total' => 75,
                'path' => 'https://api.sendkit.dev/contacts',
            ],
        ])),
    ]);

    $result = $client->contacts()->list(['page' => 2]);

    expect($result['meta']['current_page'])->toBe(2);
    expect($result['meta']['last_page'])->toBe(3);
    expect($result['links']['prev'])->not->toBeNull();
    expect($result['links']['next'])->not->toBeNull();
    expect($history)->toHaveCount(1);

    $request = $history[0]['request'];
    expect($request->getMethod())->toBe('GET');
    expect($request->getUri()->getPath())->toBe('/contacts');
    expect($request->getUri()->getQuery())->toBe('page=2');
});

it('gets a single contact', function () {
    $history = [];
    $client = createMockClient($history, [
        new Response(200, [], json_encode([
            'data' => [
                'id' => 'contact-uuid-123',
                'email' => 'john@example.com',
                'first_name' => 'John',
                'last_name' => 'Doe',
                'user_id' => null,
                'unsubscribed' => false,
                'properties' => ['COMPANY' => 'Acme'],
                'lists' => [
                    ['id' => 'list-1', 'name' => 'Newsletter', 'created_at' => '2026-03-14 12:00:00', 'updated_at' => '2026-03-14 12:00:00'],
                ],
                'created_at' => '2026-03-14 12:00:00',
                'updated_at' => '2026-03-14 12:00:00',
            ],
        ])),
    ]);

    $result = $client->contacts()->get('contact-uuid-123');

    expect($result['data']['id'])->toBe('contact-uuid-123');
    expect($result['data']['email'])->toBe('john@example.com');
    expect($result['data']['properties']['COMPANY'])->toBe('Acme');
    expect($result['data']['lists'])->toHaveCount(1);
    expect($result['data']['lists'][0]['name'])->toBe('Newsletter');
    expect($history)->toHaveCount(1);

    $request = $history[0]['request'];
    expect($request->getMethod())->toBe('GET');
    expect($request->getUri()->getPath())->toBe('/contacts/contact-uuid-123');
});

it('updates a contact', function () {
    $history = [];
    $client = createMockClient($history, [
        new Response(200, [], json_encode([
            'data' => [
                'id' => 'contact-uuid-123',
                'email' => 'john@example.com',
                'first_name' => 'Johnny',
                'last_name' => 'Doe',
                'user_id' => null,
                'unsubscribed' => false,
                'properties' => [],
                'lists' => [],
                'created_at' => '2026-03-14 12:00:00',
                'updated_at' => '2026-03-14 13:00:00',
            ],
        ])),
    ]);

    $result = $client->contacts()->update('contact-uuid-123', [
        'first_name' => 'Johnny',
    ]);

    expect($result['data']['first_name'])->toBe('Johnny');
    expect($history)->toHaveCount(1);

    $request = $history[0]['request'];
    expect($request->getMethod())->toBe('PUT');
    expect($request->getUri()->getPath())->toBe('/contacts/contact-uuid-123');

    $body = json_decode($request->getBody()->getContents(), true);
    expect($body['first_name'])->toBe('Johnny');
});

it('updates a contact with unsubscribed flag', function () {
    $history = [];
    $client = createMockClient($history, [
        new Response(200, [], json_encode([
            'data' => [
                'id' => 'contact-uuid-123',
                'email' => 'john@example.com',
                'first_name' => 'John',
                'last_name' => 'Doe',
                'user_id' => null,
                'unsubscribed' => true,
                'properties' => [],
                'lists' => [],
                'created_at' => '2026-03-14 12:00:00',
                'updated_at' => '2026-03-14 13:00:00',
            ],
        ])),
    ]);

    $result = $client->contacts()->update('contact-uuid-123', [
        'unsubscribed' => true,
    ]);

    expect($result['data']['unsubscribed'])->toBeTrue();

    $body = json_decode($history[0]['request']->getBody()->getContents(), true);
    expect($body['unsubscribed'])->toBeTrue();
});

it('deletes a contact', function () {
    $history = [];
    $client = createMockClient($history, [
        new Response(204),
    ]);

    $client->contacts()->delete('contact-uuid-123');

    expect($history)->toHaveCount(1);

    $request = $history[0]['request'];
    expect($request->getMethod())->toBe('DELETE');
    expect($request->getUri()->getPath())->toBe('/contacts/contact-uuid-123');
});

it('adds a contact to lists', function () {
    $history = [];
    $client = createMockClient($history, [
        new Response(200, [], json_encode([
            'data' => [
                'id' => 'contact-uuid-123',
                'email' => 'john@example.com',
                'first_name' => 'John',
                'last_name' => 'Doe',
                'user_id' => null,
                'unsubscribed' => false,
                'properties' => [],
                'lists' => [
                    ['id' => 'list-1', 'name' => 'Newsletter', 'created_at' => '2026-03-14 12:00:00', 'updated_at' => '2026-03-14 12:00:00'],
                    ['id' => 'list-2', 'name' => 'Updates', 'created_at' => '2026-03-14 12:00:00', 'updated_at' => '2026-03-14 12:00:00'],
                ],
                'created_at' => '2026-03-14 12:00:00',
                'updated_at' => '2026-03-14 12:00:00',
            ],
        ])),
    ]);

    $result = $client->contacts()->addToLists('contact-uuid-123', ['list-1', 'list-2']);

    expect($result['data']['lists'])->toHaveCount(2);
    expect($result['data']['lists'][0]['name'])->toBe('Newsletter');
    expect($result['data']['lists'][1]['name'])->toBe('Updates');
    expect($history)->toHaveCount(1);

    $request = $history[0]['request'];
    expect($request->getMethod())->toBe('POST');
    expect($request->getUri()->getPath())->toBe('/contacts/contact-uuid-123/lists');

    $body = json_decode($request->getBody()->getContents(), true);
    expect($body['list_ids'])->toBe(['list-1', 'list-2']);
});

it('lists a contact\'s lists', function () {
    $history = [];
    $client = createMockClient($history, [
        new Response(200, [], json_encode([
            'data' => [
                ['id' => 'list-1', 'name' => 'Newsletter', 'created_at' => '2026-03-14 12:00:00', 'updated_at' => '2026-03-14 12:00:00'],
            ],
            'links' => [
                'first' => 'https://api.sendkit.dev/contacts/contact-uuid-123/lists?page=1',
                'last' => 'https://api.sendkit.dev/contacts/contact-uuid-123/lists?page=1',
                'prev' => null,
                'next' => null,
            ],
            'meta' => [
                'current_page' => 1,
                'from' => 1,
                'last_page' => 1,
                'per_page' => 25,
                'to' => 1,
                'total' => 1,
                'path' => 'https://api.sendkit.dev/contacts/contact-uuid-123/lists',
            ],
        ])),
    ]);

    $result = $client->contacts()->listLists('contact-uuid-123');

    expect($result['data'])->toHaveCount(1);
    expect($result['data'][0]['name'])->toBe('Newsletter');
    expect($result['meta']['total'])->toBe(1);
    expect($history)->toHaveCount(1);

    $request = $history[0]['request'];
    expect($request->getMethod())->toBe('GET');
    expect($request->getUri()->getPath())->toBe('/contacts/contact-uuid-123/lists');
});

it('lists a contact\'s lists with query parameters', function () {
    $history = [];
    $client = createMockClient($history, [
        new Response(200, [], json_encode([
            'data' => [],
            'links' => [
                'first' => 'https://api.sendkit.dev/contacts/contact-uuid-123/lists?page=1',
                'last' => 'https://api.sendkit.dev/contacts/contact-uuid-123/lists?page=2',
                'prev' => null,
                'next' => 'https://api.sendkit.dev/contacts/contact-uuid-123/lists?page=2',
            ],
            'meta' => [
                'current_page' => 1,
                'from' => 1,
                'last_page' => 2,
                'per_page' => 10,
                'to' => 10,
                'total' => 15,
                'path' => 'https://api.sendkit.dev/contacts/contact-uuid-123/lists',
            ],
        ])),
    ]);

    $result = $client->contacts()->listLists('contact-uuid-123', ['per_page' => 10]);

    expect($result['meta']['per_page'])->toBe(10);
    expect($result['meta']['last_page'])->toBe(2);
    expect($history)->toHaveCount(1);

    $request = $history[0]['request'];
    expect($request->getUri()->getQuery())->toBe('per_page=10');
});

it('removes a contact from a list', function () {
    $history = [];
    $client = createMockClient($history, [
        new Response(204),
    ]);

    $client->contacts()->removeFromList('contact-uuid-123', 'list-1');

    expect($history)->toHaveCount(1);

    $request = $history[0]['request'];
    expect($request->getMethod())->toBe('DELETE');
    expect($request->getUri()->getPath())->toBe('/contacts/contact-uuid-123/lists/list-1');
});

it('throws SendKitException on contacts API error', function () {
    $history = [];
    $client = createMockClient($history, [
        new Response(422, [], json_encode(['message' => 'The email field is required.'])),
    ]);

    $client->contacts()->create([]);
})->throws(SendKitException::class, 'The email field is required.');

it('throws SendKitException when contact is not found', function () {
    $history = [];
    $client = createMockClient($history, [
        new Response(404, [], json_encode(['message' => 'Contact not found.'])),
    ]);

    $client->contacts()->get('nonexistent-id');
})->throws(SendKitException::class, 'Contact not found.');

// --- Contact Properties ---

it('returns the contact properties service', function () {
    $client = new Client('test-key');

    $properties = $client->contactProperties();

    expect($properties)->toBeInstanceOf(ContactProperties::class);
    expect($client->contactProperties())->toBe($properties);
});

it('creates a contact property', function () {
    $history = [];
    $client = createMockClient($history, [
        new Response(201, [], json_encode([
            'data' => [
                'id' => 'prop-uuid-123',
                'key' => 'company',
                'type' => 'string',
                'fallback_value' => 'N/A',
                'created_at' => '2026-03-14 12:00:00',
                'updated_at' => '2026-03-14 12:00:00',
            ],
        ])),
    ]);

    $result = $client->contactProperties()->create([
        'key' => 'company',
        'type' => 'string',
        'fallback_value' => 'N/A',
    ]);

    expect($result['data']['id'])->toBe('prop-uuid-123');
    expect($result['data']['key'])->toBe('company');
    expect($result['data']['type'])->toBe('string');
    expect($result['data']['fallback_value'])->toBe('N/A');
    expect($history)->toHaveCount(1);

    $request = $history[0]['request'];
    expect($request->getMethod())->toBe('POST');
    expect($request->getUri()->getPath())->toBe('/properties');

    $body = json_decode($request->getBody()->getContents(), true);
    expect($body['key'])->toBe('company');
    expect($body['type'])->toBe('string');
    expect($body['fallback_value'])->toBe('N/A');
});

it('creates a contact property without fallback_value', function () {
    $history = [];
    $client = createMockClient($history, [
        new Response(201, [], json_encode([
            'data' => [
                'id' => 'prop-uuid-456',
                'key' => 'age',
                'type' => 'number',
                'fallback_value' => null,
                'created_at' => '2026-03-14 12:00:00',
                'updated_at' => '2026-03-14 12:00:00',
            ],
        ])),
    ]);

    $result = $client->contactProperties()->create([
        'key' => 'age',
        'type' => 'number',
        'fallback_value' => null,
    ]);

    expect($result['data']['key'])->toBe('age');
    expect($result['data']['type'])->toBe('number');
    expect($result['data']['fallback_value'])->toBeNull();

    $body = json_decode($history[0]['request']->getBody()->getContents(), true);
    expect($body)->toBe(['key' => 'age', 'type' => 'number']);
});

it('creates a contact property with date type', function () {
    $history = [];
    $client = createMockClient($history, [
        new Response(201, [], json_encode([
            'data' => [
                'id' => 'prop-uuid-789',
                'key' => 'birthday',
                'type' => 'date',
                'fallback_value' => null,
                'created_at' => '2026-03-14 12:00:00',
                'updated_at' => '2026-03-14 12:00:00',
            ],
        ])),
    ]);

    $result = $client->contactProperties()->create([
        'key' => 'birthday',
        'type' => 'date',
    ]);

    expect($result['data']['type'])->toBe('date');

    $body = json_decode($history[0]['request']->getBody()->getContents(), true);
    expect($body['type'])->toBe('date');
});

it('lists contact properties', function () {
    $history = [];
    $client = createMockClient($history, [
        new Response(200, [], json_encode([
            'data' => [
                [
                    'id' => 'prop-uuid-1',
                    'key' => 'company',
                    'type' => 'string',
                    'fallback_value' => 'N/A',
                    'created_at' => '2026-03-14 12:00:00',
                    'updated_at' => '2026-03-14 12:00:00',
                ],
                [
                    'id' => 'prop-uuid-2',
                    'key' => 'age',
                    'type' => 'number',
                    'fallback_value' => null,
                    'created_at' => '2026-03-14 12:00:00',
                    'updated_at' => '2026-03-14 12:00:00',
                ],
            ],
            'links' => [
                'first' => 'https://api.sendkit.dev/properties?page=1',
                'last' => 'https://api.sendkit.dev/properties?page=1',
                'prev' => null,
                'next' => null,
            ],
            'meta' => [
                'current_page' => 1,
                'from' => 1,
                'last_page' => 1,
                'per_page' => 15,
                'to' => 2,
                'total' => 2,
                'path' => 'https://api.sendkit.dev/properties',
            ],
        ])),
    ]);

    $result = $client->contactProperties()->list();

    expect($result['data'])->toHaveCount(2);
    expect($result['data'][0]['key'])->toBe('company');
    expect($result['data'][1]['key'])->toBe('age');
    expect($result['meta']['total'])->toBe(2);
    expect($result['meta']['per_page'])->toBe(15);
    expect($history)->toHaveCount(1);

    $request = $history[0]['request'];
    expect($request->getMethod())->toBe('GET');
    expect($request->getUri()->getPath())->toBe('/properties');
});

it('lists contact properties with query parameters', function () {
    $history = [];
    $client = createMockClient($history, [
        new Response(200, [], json_encode([
            'data' => [],
            'links' => [
                'first' => 'https://api.sendkit.dev/properties?page=1',
                'last' => 'https://api.sendkit.dev/properties?page=3',
                'prev' => 'https://api.sendkit.dev/properties?page=1',
                'next' => 'https://api.sendkit.dev/properties?page=3',
            ],
            'meta' => [
                'current_page' => 2,
                'from' => 16,
                'last_page' => 3,
                'per_page' => 15,
                'to' => 30,
                'total' => 42,
                'path' => 'https://api.sendkit.dev/properties',
            ],
        ])),
    ]);

    $result = $client->contactProperties()->list(['page' => 2]);

    expect($result['meta']['current_page'])->toBe(2);
    expect($result['meta']['last_page'])->toBe(3);
    expect($history)->toHaveCount(1);

    $request = $history[0]['request'];
    expect($request->getUri()->getQuery())->toBe('page=2');
});

it('updates a contact property', function () {
    $history = [];
    $client = createMockClient($history, [
        new Response(200, [], json_encode([
            'data' => [
                'id' => 'prop-uuid-123',
                'key' => 'organization',
                'type' => 'string',
                'fallback_value' => 'Unknown',
                'created_at' => '2026-03-14 12:00:00',
                'updated_at' => '2026-03-14 13:00:00',
            ],
        ])),
    ]);

    $result = $client->contactProperties()->update('prop-uuid-123', [
        'key' => 'organization',
        'fallback_value' => 'Unknown',
    ]);

    expect($result['data']['key'])->toBe('organization');
    expect($result['data']['fallback_value'])->toBe('Unknown');
    expect($history)->toHaveCount(1);

    $request = $history[0]['request'];
    expect($request->getMethod())->toBe('PUT');
    expect($request->getUri()->getPath())->toBe('/properties/prop-uuid-123');

    $body = json_decode($request->getBody()->getContents(), true);
    expect($body['key'])->toBe('organization');
    expect($body['fallback_value'])->toBe('Unknown');
});

it('deletes a contact property', function () {
    $history = [];
    $client = createMockClient($history, [
        new Response(204),
    ]);

    $client->contactProperties()->delete('prop-uuid-123');

    expect($history)->toHaveCount(1);

    $request = $history[0]['request'];
    expect($request->getMethod())->toBe('DELETE');
    expect($request->getUri()->getPath())->toBe('/properties/prop-uuid-123');
});

it('throws SendKitException when property key already exists', function () {
    $history = [];
    $client = createMockClient($history, [
        new Response(422, [], json_encode(['message' => 'The key has already been taken.'])),
    ]);

    $client->contactProperties()->create([
        'key' => 'company',
        'type' => 'string',
    ]);
})->throws(SendKitException::class, 'The key has already been taken.');

it('throws SendKitException when deleting property used in segments', function () {
    $history = [];
    $client = createMockClient($history, [
        new Response(409, [], json_encode(['message' => 'This property is used in segment filters and cannot be deleted.'])),
    ]);

    $client->contactProperties()->delete('prop-uuid-123');
})->throws(SendKitException::class, 'This property is used in segment filters and cannot be deleted.');

it('throws SendKitException when property is not found', function () {
    $history = [];
    $client = createMockClient($history, [
        new Response(404, [], json_encode(['message' => 'Not found.'])),
    ]);

    $client->contactProperties()->update('nonexistent-id', ['key' => 'test']);
})->throws(SendKitException::class, 'Not found.');
