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
    expect($request->getUri()->getPath())->toBe('/v1/emails');

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
    expect($request->getUri()->getPath())->toBe('/v1/emails/mime');

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
    expect($request->getUri()->getPath())->toBe('/v1/emails/validate');

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
