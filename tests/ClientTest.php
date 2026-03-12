<?php

declare(strict_types=1);

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use SendKit\Client;
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
            'is_valid' => true,
            'evaluations' => [
                'has_valid_syntax' => true,
                'has_valid_dns' => true,
                'mailbox_exists' => true,
                'is_role_address' => false,
                'is_disposable' => false,
                'is_random_input' => false,
            ],
            'should_block' => false,
            'block_reason' => null,
            'validated_at' => '2026-03-05 12:00:00',
        ])),
    ]);

    $result = $client->validateEmail('user@example.com');

    expect($result['email'])->toBe('user@example.com');
    expect($result['is_valid'])->toBeTrue();
    expect($result['evaluations']['has_valid_syntax'])->toBeTrue();
    expect($result['evaluations']['is_disposable'])->toBeFalse();
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
            'is_valid' => false,
            'evaluations' => [
                'has_valid_syntax' => true,
                'has_valid_dns' => false,
                'mailbox_exists' => false,
                'is_role_address' => false,
                'is_disposable' => true,
                'is_random_input' => false,
            ],
            'should_block' => true,
            'block_reason' => 'disposable',
            'validated_at' => '2026-03-05 12:00:00',
        ])),
    ]);

    $result = $client->emailValidations()->validate('test@example.com');

    expect($result['is_valid'])->toBeFalse();
    expect($result['evaluations']['is_disposable'])->toBeTrue();
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
